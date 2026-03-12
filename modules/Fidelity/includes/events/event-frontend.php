<?php

/**
 * Author: Meteora Web <https://meteoraweb.com>
 */

if (!defined('ABSPATH')) {
    exit;
}

add_shortcode('richiedi_ticket', 'ucg_events_render_ticket_form');
add_shortcode('verifica_ticket', 'ucg_events_render_checkin_form');
add_shortcode('ticket_pr', 'ucg_events_render_ticket_pr_form');

/**
 * Detect shortcode usage inside the current post content or metadata.
 *
 * Some page builders store shortcode definitions inside post meta instead of
 * the main post content. When that happens `has_shortcode()` returns `false`
 * and the frontend assets are not enqueued, leaving interactive elements such
 * as the ticket and PR dropdowns unusable. This helper inspects both the
 * content and the most common meta storage to determine whether our
 * shortcodes are present so assets can be reliably loaded.
 *
 * @param WP_Post|null $post       Post object being rendered.
 * @param array        $shortcodes List of shortcode tags to detect.
 * @return array<string,bool>      Map indicating which shortcodes were found.
 */
function ucg_events_detect_shortcodes_in_post($post, $shortcodes) {
    $detected = array();
    foreach ($shortcodes as $tag) {
        $detected[$tag] = false;
    }

    if (!$post || !isset($post->ID)) {
        return $detected;
    }

    $post_id = (int) $post->ID;
    $content = isset($post->post_content) ? $post->post_content : '';
    foreach ($shortcodes as $tag) {
        if (has_shortcode($content, $tag) || stripos($content, '[' . $tag) !== false) {
            $detected[$tag] = true;
        }
    }

    if (!in_array(false, $detected, true) || !function_exists('get_post_meta')) {
        return $detected;
    }

    $meta_values = get_post_meta($post_id);
    if (empty($meta_values)) {
        return $detected;
    }

    foreach ($meta_values as $values) {
        foreach ((array) $values as $value) {
            $value = maybe_unserialize($value);
            if (is_array($value) || is_object($value)) {
                $value = function_exists('wp_json_encode') ? wp_json_encode($value) : json_encode($value);
            }
            if (!is_string($value) || $value === '') {
                continue;
            }

            foreach ($shortcodes as $tag) {
                if (!$detected[$tag] && stripos($value, '[' . $tag) !== false) {
                    $detected[$tag] = true;
                }
            }

            if (!in_array(false, $detected, true)) {
                break 2;
            }
        }
    }

    return $detected;
}

add_action('template_redirect', 'ucg_events_handle_ticket_form_submission');
add_action('template_redirect', 'ucg_events_handle_checkin_submission');
add_action('template_redirect', 'ucg_events_handle_ticket_pr_submission');
add_action('template_redirect', 'ucg_events_maybe_redirect_order_received', 20);
add_action('wp_enqueue_scripts', 'ucg_events_enqueue_front_assets');
add_action('wp_ajax_ucg_events_validate_ticket_step', 'ucg_events_ajax_validate_ticket_step');
add_action('wp_ajax_nopriv_ucg_events_validate_ticket_step', 'ucg_events_ajax_validate_ticket_step');
add_action('wp_ajax_ucg_events_process_ticket_order', 'ucg_events_ajax_process_ticket_order');
add_action('wp_ajax_nopriv_ucg_events_process_ticket_order', 'ucg_events_ajax_process_ticket_order');
add_action('wp_ajax_ucg_events_fetch_order_summary', 'ucg_events_ajax_fetch_order_summary');
add_action('wp_ajax_nopriv_ucg_events_fetch_order_summary', 'ucg_events_ajax_fetch_order_summary');

if (function_exists('add_action')) {
    add_action('woocommerce_checkout_create_order_line_item', 'ucg_events_checkout_item_meta', 10, 4);
    add_action('woocommerce_payment_complete', 'ucg_events_handle_completed_order');
    add_action('woocommerce_order_status_processing', 'ucg_events_handle_completed_order');
    add_action('woocommerce_order_status_completed', 'ucg_events_handle_completed_order');
    add_action('woocommerce_thankyou', 'ucg_events_output_wc_redirect');
}

/**
 * Enqueue frontend assets when needed.
 */
function ucg_events_enqueue_front_assets() {
    if (!is_singular()) {
        return;
    }

    $post = get_post();
    if (!$post) {
        return;
    }

    $shortcodes = array('richiedi_ticket', 'verifica_ticket', 'ticket_pr');
    $detected = ucg_events_detect_shortcodes_in_post($post, $shortcodes);

    $has_request = !empty($detected['richiedi_ticket']);
    $has_checkin = !empty($detected['verifica_ticket']);
    $has_pr = !empty($detected['ticket_pr']);

    if ($has_request || $has_checkin || $has_pr) {
        wp_enqueue_style('ucg-events-frontend', UCG_PLUGIN_URL . 'assets/css/ucg-events-frontend.css', array(), UCG_VERSION);
    }

    if ($has_request) {
        $phone_js_path = UCG_PLUGIN_DIR . 'assets/js/ucg-phone-fields.js';
        $phone_js_version = file_exists($phone_js_path) ? filemtime($phone_js_path) : UCG_VERSION;

        wp_enqueue_script(
            'ucg-phone-fields',
            UCG_PLUGIN_URL . 'assets/js/ucg-phone-fields.js',
            array(),
            $phone_js_version,
            true
        );

        $wizard_js_path = UCG_PLUGIN_DIR . 'assets/js/ucg-ticket-wizard.js';
        $wizard_js_version = file_exists($wizard_js_path) ? filemtime($wizard_js_path) : UCG_VERSION;

        wp_enqueue_script(
            'ucg-ticket-wizard',
            UCG_PLUGIN_URL . 'assets/js/ucg-ticket-wizard.js',
            array(),
            $wizard_js_version,
            true
        );

        $confirm_pay_label = esc_html__('Conferma e paga', 'unique-coupon-generator');
        $confirm_reserve_label = esc_html__('Conferma prenotazione', 'unique-coupon-generator');
        $processing_payment_label = esc_html__('Reindirizzamento al pagamento…', 'unique-coupon-generator');
        $processing_reservation_label = esc_html__('Prenotazione in corso…', 'unique-coupon-generator');

        $wizard_settings = array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce'   => wp_create_nonce('ucg_ticket_wizard'),
            'i18n'    => array(
                'genericError'      => esc_html__('Si è verificato un errore inatteso. Riprova più tardi.', 'unique-coupon-generator'),
                'step1Error'        => esc_html__('Controlla i dati inseriti prima di procedere.', 'unique-coupon-generator'),
                'step2Error'        => esc_html__('Seleziona un metodo di pagamento per continuare.', 'unique-coupon-generator'),
                'paymentModeError'  => esc_html__('Seleziona come vuoi pagare per continuare.', 'unique-coupon-generator'),
                'processingPayment' => $processing_payment_label,
                'processingReservation' => $processing_reservation_label,
                'loading'           => esc_html__('Attendere…', 'unique-coupon-generator'),
                'polling'           => esc_html__('Verifica pagamento in corso…', 'unique-coupon-generator'),
                'downloadPdf'       => esc_html__('Download PDF Ticket', 'unique-coupon-generator'),
                'downloadPng'       => esc_html__('Download PNG Ticket', 'unique-coupon-generator'),
                'whatsapp'          => esc_html__('Ricevi via WhatsApp', 'unique-coupon-generator'),
                'confirmPay'        => $confirm_pay_label,
                'confirmReserve'    => $confirm_reserve_label,
            ),
        );

        wp_localize_script('ucg-ticket-wizard', 'ucgTicketWizardSettings', $wizard_settings);

        if (function_exists('WC')) {
            $checkout = WC()->checkout();
            if ($checkout && method_exists($checkout, 'enqueue_scripts')) {
                $checkout->enqueue_scripts();
            }
        }
    }

    if ($has_checkin || $has_pr) {
        $html5_qr_path = UCG_PLUGIN_DIR . 'assets/js/html5-qrcode.min.js';
        $html5_qr_version = file_exists($html5_qr_path) ? filemtime($html5_qr_path) : UCG_VERSION;
        wp_enqueue_script(
            'html5-qrcode',
            UCG_PLUGIN_URL . 'assets/js/html5-qrcode.min.js',
            array(),
            $html5_qr_version,
            true
        );

        $frontend_js_path = UCG_PLUGIN_DIR . 'assets/js/ucg-events-frontend.js';
        $frontend_js_version = file_exists($frontend_js_path) ? filemtime($frontend_js_path) : UCG_VERSION;
        wp_enqueue_script(
            'ucg-events-frontend',
            UCG_PLUGIN_URL . 'assets/js/ucg-events-frontend.js',
            array('html5-qrcode'),
            $frontend_js_version,
            true
        );
    }
}

/**
 * Render the ticket request form.
 */
function ucg_events_render_ticket_form($atts) {
    $atts = shortcode_atts(array('base' => 0), $atts, 'richiedi_ticket');
    $event_id = absint($atts['base']);

    if (!$event_id) {
        return '<div class="ucg-event-notice error">' . esc_html__('Evento non trovato.', 'unique-coupon-generator') . '</div>';
    }

    $event = ucg_events_get_event($event_id);
    if (!$event) {
        return '<div class="ucg-event-notice error">' . esc_html__('Evento non disponibile.', 'unique-coupon-generator') . '</div>';
    }

    if ($event->stato === 'bozza') {
        return '';
    }

    if ($event->stato === 'chiuso') {
        return '<div class="ucg-event-notice warning">' . esc_html__('Evento concluso, ticket non più disponibili.', 'unique-coupon-generator') . '</div>';
    }

    $show_whatsapp_opt_in = !isset($event->mostra_whatsapp) || (int) $event->mostra_whatsapp !== 0;
    $allow_png_download = !empty($event->mostra_download_png);
    $allow_pdf_download = !empty($event->mostra_download_pdf);
    $allow_wc = !empty($event->pagamento_woocommerce);
    $allow_in_loco = !empty($event->pagamento_in_loco);
    if ($allow_wc && function_exists('WC')) {
        $gateway_controller = WC()->payment_gateways();
        if ($gateway_controller && method_exists($gateway_controller, 'get_available_payment_gateways')) {
            $available_map = $gateway_controller->get_available_payment_gateways();
            $available_map = ucg_events_filter_gateway_map_for_event($event, $available_map);
            if (empty($available_map)) {
                $allow_wc = false;
            }
        }
    }

    $blocked = false;
    if (!empty($event->blocco_ticket)) {
        $blocked = current_time('timestamp') > strtotime($event->blocco_ticket);
    }

    $global_remaining = ucg_events_get_global_remaining($event);
    $tickets = $event->tipi_ticket;
    if (!is_array($tickets)) {
        $tickets = array();
    }

    $normalized_tickets = array();
    foreach ($tickets as $index => $raw_ticket) {
        if (is_object($raw_ticket)) {
            $raw_ticket = (array) $raw_ticket;
        }
        if (!is_array($raw_ticket)) {
            continue;
        }

        $ticket_id = '';
        if (!empty($raw_ticket['id'])) {
            $ticket_id = sanitize_title($raw_ticket['id']);
        }
        if ($ticket_id === '' && !empty($raw_ticket['name'])) {
            $ticket_id = sanitize_title($raw_ticket['name']);
        }
        if ($ticket_id === '') {
            continue;
        }

        $raw_ticket['id'] = $ticket_id;
        $raw_ticket['name'] = isset($raw_ticket['name']) ? sanitize_text_field($raw_ticket['name']) : $ticket_id;
        $raw_ticket['price'] = isset($raw_ticket['price']) ? (float) $raw_ticket['price'] : 0;
        $raw_ticket['max'] = isset($raw_ticket['max']) ? (int) $raw_ticket['max'] : 0;

        $normalized_tickets[] = $raw_ticket;
    }

    $tickets = $normalized_tickets;

    $notice = ucg_events_get_front_notice();

    $output = '<div class="ucg-event-wrapper">';
    if ($notice) {
        $output .= $notice;
    }

    if ($blocked) {
        $output .= '<div class="ucg-event-notice warning">' . esc_html__('Il tempo di emissione dei ticket è esaurito o i ticket non sono più disponibili. Contatta l’assistenza o l’organizzatore dell’evento.', 'unique-coupon-generator') . '</div>';
        $output .= '</div>';
        return $output;
    }

    if ($global_remaining !== -1 && $global_remaining <= 0) {
        $output .= '<div class="ucg-event-notice warning">' . esc_html__('I ticket sono terminati.', 'unique-coupon-generator') . '</div>';
        $output .= '</div>';
        return $output;
    }

    if ($event->mostra_contenuto) {
        $output .= '<div class="ucg-event-header">';
        $event_image = ucg_events_safe_url($event->immagine ?? '');
        if ($event_image !== '') {
            $output .= '<div class="ucg-event-image"><img src="' . esc_url($event_image) . '" alt="' . esc_attr($event->titolo) . '"></div>';
        }
        $output .= '<div class="ucg-event-info">';
        $output .= '<h2 class="ucg-event-title">' . esc_html($event->titolo) . '</h2>';
        if (!empty($event->data_evento)) {
            $datetime = date_i18n(get_option('date_format'), strtotime($event->data_evento));
            if (!empty($event->ora_evento)) {
                $datetime .= ' ' . esc_html($event->ora_evento);
            }
            $output .= '<p class="ucg-event-meta"><strong>' . esc_html__('Data:', 'unique-coupon-generator') . '</strong> ' . esc_html($datetime) . '</p>';
        }
        if (!empty($event->luogo)) {
            $output .= '<p class="ucg-event-meta"><strong>' . esc_html__('Luogo:', 'unique-coupon-generator') . '</strong> ' . esc_html($event->luogo) . '</p>';
        }
        if (!empty($event->descrizione)) {
            $output .= '<div class="ucg-event-description">' . wp_kses_post(wpautop($event->descrizione)) . '</div>';
        }
        $output .= '</div></div>';
    }

    $available_ticket = false;
    $ticket_options = '';
    foreach ($tickets as $ticket) {
        $remaining = ucg_events_get_ticket_remaining($event, $ticket);
        $label = $ticket['name'];
        if (!empty($ticket['price'])) {
            $price = function_exists('wc_price') ? wc_price($ticket['price']) : number_format_i18n($ticket['price'], 2);
            $label .= ' – ' . wp_strip_all_tags($price);
        }
        if ($remaining === 0) {
            $ticket_options .= '<option value="' . esc_attr($ticket['id']) . '" disabled>' . esc_html($label . ' (' . __('Esaurito', 'unique-coupon-generator') . ')') . '</option>';
        } else {
            $available_ticket = true;
            if ($remaining > 0) {
                $extra = ' (' . sprintf(_n('%d disponibile', '%d disponibili', $remaining, 'unique-coupon-generator'), $remaining) . ')';
            } elseif ($remaining < 0) {
                $extra = ' (' . esc_html__('Disponibilità illimitata', 'unique-coupon-generator') . ')';
            } else {
                $extra = '';
            }
            $ticket_options .= '<option value="' . esc_attr($ticket['id']) . '">' . esc_html($label . $extra) . '</option>';
        }
    }

    $pr_blocked = false;
    $pr_options = '';
    if ($event->gestione_pr) {
        $prs = ucg_events_get_pr_list($event->id);
        if (!empty($prs)) {
            foreach ($prs as $pr) {
                $remaining = ucg_events_get_pr_remaining($event->id, $pr);
                if ($remaining === 0) {
                    continue;
                }
                $pr_options .= '<option value="' . esc_attr($pr->id) . '">' . esc_html($pr->nome_pr) . '</option>';
            }
            if ($pr_options === '') {
                $pr_blocked = true;
            }
        }
    }

    $request_uri = isset($_SERVER['REQUEST_URI']) ? wp_unslash($_SERVER['REQUEST_URI']) : '';
    if ($request_uri !== '') {
        $request_uri = remove_query_arg(array('ucg_order_key', 'ucg_wizard_token'), $request_uri);
    }
    $wizard_return_url = $request_uri !== '' ? ucg_events_safe_url(home_url($request_uri)) : '';
    if (!$wizard_return_url && function_exists('get_permalink')) {
        $wizard_return_url = ucg_events_safe_url(get_permalink());
    }
    if (!$wizard_return_url) {
        $wizard_return_url = ucg_events_safe_url(home_url('/'));
    }

    $config = array(
        'eventId'    => $event->id,
        'allowWoo'   => (bool) $allow_wc,
        'allowInLoco'=> (bool) $allow_in_loco,
        'returnUrl'  => $wizard_return_url,
    );

    $config_json = wp_json_encode($config);
    $config_attr = $config_json ? esc_attr($config_json) : '{}';

    $manual_in_loco_available = (bool) $allow_in_loco;

    $output .= '<div class="ucg-ticket-wizard" data-ucg-config="' . $config_attr . '">';
    $output .= '<form class="ucg-wizard-form" data-ucg-form novalidate>';
    $output .= '<input type="hidden" name="event_id" value="' . esc_attr($event->id) . '">';
    $output .= '<div class="ucg-wizard-alert" data-ucg-alert hidden aria-live="polite"></div>';

    $phone_field_id = 'ucg-phone-number-' . $event->id;
    $phone_warning = esc_html__('Il numero deve iniziare con +39 e contenere 10 cifre (es. +393451234567).', 'unique-coupon-generator');

    $ticket_select_id = 'ucg-ticket-type-' . $event->id;
    $pr_select_id = 'ucg-ticket-pr-' . $event->id;
    $output .= '<section class="ucg-wizard-step is-active" data-ucg-step="1">';
    $output .= '<header class="ucg-wizard-step-header"><span class="ucg-wizard-step-number">1</span><h3>' . esc_html__('Dati utente', 'unique-coupon-generator') . '</h3></header>';
    $output .= '<div class="ucg-form-grid">';
    $output .= '<div class="ucg-form-field"><label>' . esc_html__('Nome', 'unique-coupon-generator') . '</label><input type="text" name="nome" required></div>';
    $output .= '<div class="ucg-form-field"><label>' . esc_html__('Cognome', 'unique-coupon-generator') . '</label><input type="text" name="cognome" required></div>';
    $output .= '<div class="ucg-form-field"><label>' . esc_html__('Email', 'unique-coupon-generator') . '</label><input type="email" name="email" required></div>';
    $output .= '<div class="ucg-form-field"><label for="' . esc_attr($phone_field_id) . '">' . esc_html__('Telefono', 'unique-coupon-generator') . '</label>';
    $output .= '<input type="tel" name="telefono" id="' . esc_attr($phone_field_id) . '" value="+39" inputmode="tel" autocomplete="tel" required pattern="^\+39\d{10}$" title="' . esc_attr__('Inserisci il numero in formato +39 seguito da 10 cifre.', 'unique-coupon-generator') . '" maxlength="13" data-ucg-phone-input>';
    $output .= '<p class="ucg-phone-warning" data-ucg-phone-warning hidden aria-live="polite">' . $phone_warning . '</p></div>';
    $output .= '</div>';

    $delivery_options = '';
    if ($show_whatsapp_opt_in) {
        $delivery_options .= '<label class="ucg-option-entry"><input type="checkbox" name="whatsapp_opt_in" value="1" data-ucg-delivery-option> ' . esc_html__('Voglio ricevere il QR anche su WhatsApp', 'unique-coupon-generator') . '</label>';
    }
    if ($allow_png_download) {
        $delivery_options .= '<label class="ucg-option-entry"><input type="checkbox" name="download_png" value="1" data-ucg-delivery-option> ' . esc_html__('Scarica il QR in formato PNG', 'unique-coupon-generator') . '</label>';
    }
    if ($allow_pdf_download) {
        $delivery_options .= '<label class="ucg-option-entry"><input type="checkbox" name="download_pdf" value="1" data-ucg-delivery-option> ' . esc_html__('Scarica il PDF con i dettagli del ticket', 'unique-coupon-generator') . '</label>';
    }
    if ($delivery_options !== '') {
        $output .= '<fieldset class="ucg-form-field ucg-form-checkbox-group" data-ucg-delivery-group data-ucg-delivery-required>';
        $output .= '<legend>' . esc_html__('Come desideri ricevere il QR code?', 'unique-coupon-generator') . '</legend>';
        $output .= $delivery_options;
        $output .= '<p class="ucg-delivery-warning" data-ucg-delivery-warning hidden aria-live="polite">' . esc_html__('Seleziona una sola modalità di ricezione per il QR code.', 'unique-coupon-generator') . '</p>';
        $output .= '</fieldset>';
    }

    $ticket_disabled_attr = $ticket_options === '' ? ' disabled' : '';
    $output .= '<div class="ucg-form-field ucg-form-field--full"><label for="' . esc_attr($ticket_select_id) . '">' . esc_html__('Tipologia di ticket', 'unique-coupon-generator') . '</label><select class="ucg-select ucg-select--ticket" name="ticket_type" id="' . esc_attr($ticket_select_id) . '" required' . $ticket_disabled_attr . '>';
    $output .= '<option value="" disabled selected hidden></option>';
    $output .= $ticket_options;
    $output .= '</select></div>';

    if (!$available_ticket) {
        $output .= '<div class="ucg-event-notice warning">' . esc_html__('Nessun ticket disponibile al momento.', 'unique-coupon-generator') . '</div>';
    }

    if ($event->gestione_pr) {
        $pr_disabled_attr = $pr_blocked ? ' disabled' : '';
        $output .= '<div class="ucg-form-field ucg-form-field--full"><label for="' . esc_attr($pr_select_id) . '">' . esc_html__('Seleziona PR', 'unique-coupon-generator') . '</label><select class="ucg-select ucg-select--pr" name="pr_id" id="' . esc_attr($pr_select_id) . '"' . $pr_disabled_attr . ' required>';
        $output .= '<option value="" disabled selected hidden></option>';
        $output .= $pr_options;
        $output .= '</select>';
        if ($pr_blocked) {
            $output .= '<p class="ucg-event-notice warning">' . esc_html__('Nessun ticket disponibile per i PR selezionati.', 'unique-coupon-generator') . '</p>';
        }
        $output .= '</div>';
    }

    if ($event->mostra_privacy && $event->privacy_page_id) {
        $privacy_url = ucg_events_safe_url(get_permalink($event->privacy_page_id));
        $output .= '<div class="ucg-form-field ucg-form-checkbox">';
        $output .= '<label><input type="checkbox" name="privacy_accept" value="1" required> ' . sprintf(wp_kses(__('Ho letto e accetto la <a href="%s" target="_blank">privacy policy</a>.', 'unique-coupon-generator'), array('a' => array('href' => array(), 'target' => array()))), esc_url($privacy_url)) . '</label>';
        $output .= '</div>';
    }

    $payment_mode_options = array();
    if ($allow_wc) {
        $payment_mode_options['online'] = array(
            'label' => esc_html__('Paga online', 'unique-coupon-generator'),
            'description' => esc_html__('Completa subito l’ordine con carta o altri metodi WooCommerce.', 'unique-coupon-generator'),
        );
    }
    if ($manual_in_loco_available) {
        $payment_mode_options['loco'] = array(
            'label' => esc_html__('Paga in loco', 'unique-coupon-generator'),
            'description' => esc_html__('Prenota il tuo posto adesso e paga all’ingresso dell’evento.', 'unique-coupon-generator'),
        );
    }

    if (empty($payment_mode_options)) {
        $payment_mode_options['manual'] = array(
            'label' => esc_html__('Conferma prenotazione', 'unique-coupon-generator'),
            'description' => esc_html__('Completa la richiesta per generare il ticket.', 'unique-coupon-generator'),
        );
    }

    $output .= '<fieldset class="ucg-form-field ucg-form-radio-group" data-ucg-payment-mode>';
    $output .= '<legend>' . esc_html__('Come vuoi pagare?', 'unique-coupon-generator') . '</legend>';
    $index = 0;
    $count_modes = count($payment_mode_options);
    foreach ($payment_mode_options as $mode_key => $mode_data) {
        $mode_label = $mode_data['label'];
        $mode_desc = $mode_data['description'];
        $checked = ($count_modes === 1) ? ' checked' : '';
        $required_attr = ($count_modes > 1 && $index === 0) ? ' required' : '';
        $output .= '<label class="ucg-option-entry ucg-option-entry-radio">';
        $output .= '<input type="radio" name="payment_mode" value="' . esc_attr($mode_key) . '"' . $checked . $required_attr . '>';
        $output .= '<span class="ucg-option-title">' . esc_html($mode_label) . '</span>';
        if ($mode_desc !== '') {
            $output .= '<span class="ucg-option-desc">' . esc_html($mode_desc) . '</span>';
        }
        $output .= '</label>';
        $index++;
    }
    $output .= '</fieldset>';

    $step1_disabled = (!$available_ticket || $pr_blocked) ? ' disabled' : '';
    $output .= '<div class="ucg-wizard-actions">';
    $output .= '<button type="button" class="ucg-wizard-button ucg-wizard-next" data-ucg-next="2"' . $step1_disabled . '>' . esc_html__('Continua', 'unique-coupon-generator') . '</button>';
    $output .= '</div>';
    $output .= '</section>';

    $gateway_markup = '';
    $available_gateways = array();
    if ($allow_wc && function_exists('WC')) {
        $gateways = WC()->payment_gateways();
        if ($gateways && method_exists($gateways, 'get_available_payment_gateways')) {
            $available_gateways = $gateways->get_available_payment_gateways();
            $available_gateways = ucg_events_filter_gateway_map_for_event($event, $available_gateways);
        }
    }

    if (!empty($available_gateways)) {
        foreach ($available_gateways as $gateway_id => $gateway) {
            $title = $gateway->get_title();
            $description = $gateway->get_description();
            $gateway_markup .= '<div class="ucg-payment-option" data-gateway="' . esc_attr($gateway_id) . '">';
            $gateway_markup .= '<label class="ucg-payment-label"><input type="radio" name="payment_method" value="' . esc_attr($gateway_id) . '">';
            $gateway_markup .= '<span class="ucg-payment-name">' . esc_html($title) . '</span>';
            if ($description) {
                $gateway_markup .= '<span class="ucg-payment-desc">' . wp_kses_post($description) . '</span>';
            }
            $gateway_markup .= '</label>';
            if (!empty($gateway->has_fields)) {
                ob_start();
                $gateway->payment_fields();
                $fields_html = ob_get_clean();
                if ($fields_html) {
                    $gateway_markup .= '<div class="ucg-payment-fields" data-gateway-fields="' . esc_attr($gateway_id) . '" hidden>' . $fields_html . '</div>';
                }
            }
            $gateway_markup .= '</div>';
        }
    }

    $allow_wc = $allow_wc && !empty($available_gateways);
    $has_payment_options = !empty($available_gateways);

    $output .= '<section class="ucg-wizard-step" data-ucg-step="2">';
    $output .= '<header class="ucg-wizard-step-header"><span class="ucg-wizard-step-number">2</span><h3>' . esc_html__('Metodi di pagamento', 'unique-coupon-generator') . '</h3></header>';
    $output .= '<div class="ucg-payment-wrapper">';
    if (!empty($gateway_markup)) {
        $output .= $gateway_markup;
    }
    if (!$has_payment_options) {
        $output .= '<p class="ucg-form-hint">' . esc_html__('Nessun metodo di pagamento disponibile. Contatta l’organizzatore per maggiori informazioni.', 'unique-coupon-generator') . '</p>';
    }
    $output .= '</div>';
    $confirm_pay_label = esc_html__('Conferma e paga', 'unique-coupon-generator');
    $confirm_reserve_label = esc_html__('Conferma prenotazione', 'unique-coupon-generator');
    $confirm_button_label = (!empty($available_gateways)) ? $confirm_pay_label : $confirm_reserve_label;

    $confirm_button_attrs = ' data-ucg-confirm-default="' . esc_attr($confirm_pay_label) . '" data-ucg-confirm-manual="' . esc_attr($confirm_reserve_label) . '"';

    $output .= '<div class="ucg-wizard-actions">';
    $output .= '<button type="button" class="ucg-wizard-button ucg-wizard-prev" data-ucg-prev="1">' . esc_html__('Indietro', 'unique-coupon-generator') . '</button>';
    $output .= '<button type="button" class="ucg-wizard-button ucg-wizard-next ucg-wizard-confirm" data-ucg-next="3"' . ($has_payment_options ? '' : ' disabled') . $confirm_button_attrs . '>' . $confirm_button_label . '</button>';
    $output .= '</div>';
    $output .= '</section>';

    $output .= '<section class="ucg-wizard-step" data-ucg-step="3">';
    $output .= '<header class="ucg-wizard-step-header"><span class="ucg-wizard-step-number">3</span><h3>' . esc_html__('Conferma e ticket', 'unique-coupon-generator') . '</h3></header>';
    $output .= '<div class="ucg-wizard-summary" data-ucg-summary>';
    $output .= '<p class="ucg-wizard-placeholder">' . esc_html__('Completa i passaggi precedenti per visualizzare il tuo ticket.', 'unique-coupon-generator') . '</p>';
    $output .= '</div>';
    $output .= '<div class="ucg-wizard-actions">';
    $output .= '<button type="button" class="ucg-wizard-button ucg-wizard-prev" data-ucg-prev="2">' . esc_html__('Torna ai pagamenti', 'unique-coupon-generator') . '</button>';
    $output .= '</div>';
    $output .= '</section>';

    $output .= '</form>';
    $output .= '</div>';
    $output .= '</div>';

    return $output;
}

/**
 * Validate and store the first step of the ticket wizard via AJAX.
 */
function ucg_events_ajax_validate_ticket_step() {
    check_ajax_referer('ucg_ticket_wizard', 'nonce');

    $event_id = isset($_POST['event_id']) ? absint(wp_unslash($_POST['event_id'])) : 0;
    if (!$event_id) {
        wp_send_json_error(array('message' => esc_html__('Evento non valido.', 'unique-coupon-generator')));
    }

    $event = ucg_events_get_event($event_id);
    if (!$event) {
        wp_send_json_error(array('message' => esc_html__('Evento non disponibile.', 'unique-coupon-generator')));
    }

    $form_raw = isset($_POST['form']) ? wp_unslash($_POST['form']) : '';
    $form_data = array();
    if ($form_raw !== '') {
        $decoded = json_decode($form_raw, true);
        if (is_array($decoded)) {
            $form_data = $decoded;
        }
    }

    if (empty($form_data)) {
        $fallback_fields = array('nome', 'cognome', 'email', 'telefono', 'ticket_type', 'pr_id', 'whatsapp_opt_in', 'download_png', 'download_pdf', 'privacy_accept', 'payment_mode');
        foreach ($fallback_fields as $field) {
            if (isset($_POST[$field])) {
                $form_data[$field] = wp_unslash($_POST[$field]);
            }
        }
    }

    $validation = ucg_events_prepare_wizard_request($event, $form_data, array('validate_payment' => false));
    if (is_wp_error($validation)) {
        wp_send_json_error(array('message' => $validation->get_error_message()));
    }

    $ticket_label = '';
    if (!empty($validation['ticket']['name'])) {
        $ticket_label = $validation['ticket']['name'];
        if (isset($validation['ticket']['price']) && $validation['ticket']['price'] !== '') {
            $price_value = (float) $validation['ticket']['price'];
            if ($price_value > 0) {
                $ticket_label .= ' – ' . wp_strip_all_tags(function_exists('wc_price') ? wc_price($price_value) : number_format_i18n($price_value, 2));
            }
        }
    }

    $payment_mode = isset($validation['payment_mode']) ? sanitize_key($validation['payment_mode']) : '';
    if ($payment_mode === 'loco' || $payment_mode === 'manual') {
        $manual_mode = ($payment_mode === 'manual') ? 'manual' : 'loco';
        $manual_result = ucg_events_generate_manual_ticket_response($event, $validation, $manual_mode);

        if (is_wp_error($manual_result)) {
            wp_send_json_error(array('message' => $manual_result->get_error_message()));
        }

        wp_send_json_success($manual_result);
    }

    $token = ucg_events_generate_wizard_token();
    ucg_events_store_wizard_data($token, $validation);

    $response = array(
        'token'         => $token,
        'payment_mode'  => $validation['payment_mode'],
        'allow_wc'      => !empty($validation['allow_wc']),
        'allow_in_loco' => !empty($validation['allow_in_loco']),
        'data'          => array(
            'nome'         => $validation['nome'],
            'cognome'      => $validation['cognome'],
            'email'        => $validation['email'],
            'telefono'     => $validation['telefono'] !== '' ? $validation['telefono'] : $validation['phone_full'],
            'ticket'       => $ticket_label,
            'payment_mode' => $validation['payment_mode'],
            'allow_wc'     => !empty($validation['allow_wc']),
            'allow_in_loco'=> !empty($validation['allow_in_loco']),
        ),
    );

    wp_send_json_success($response);
}

/**
 * Handle the wizard confirmation step: process payments or generate offline tickets.
 */
function ucg_events_ajax_process_ticket_order() {
    check_ajax_referer('ucg_ticket_wizard', 'nonce');

    $event_id = isset($_POST['event_id']) ? absint(wp_unslash($_POST['event_id'])) : 0;
    $token = isset($_POST['token']) ? sanitize_text_field(wp_unslash($_POST['token'])) : '';
    $payment_method = isset($_POST['payment_method']) ? sanitize_text_field(wp_unslash($_POST['payment_method'])) : '';
    $requested_mode = isset($_POST['payment_mode']) ? sanitize_key(wp_unslash($_POST['payment_mode'])) : '';
    $return_url = isset($_POST['return_url']) ? ucg_events_safe_url(wp_unslash($_POST['return_url'])) : '';

    if (!$event_id || $token === '') {
        wp_send_json_error(array('message' => esc_html__('Richiesta non valida.', 'unique-coupon-generator')));
    }

    $event = ucg_events_get_event($event_id);
    if (!$event) {
        wp_send_json_error(array('message' => esc_html__('Evento non disponibile.', 'unique-coupon-generator')));
    }

    $stored_data = ucg_events_get_wizard_data($token);
    if (empty($stored_data) || !is_array($stored_data)) {
        wp_send_json_error(array('message' => esc_html__('Sessione scaduta, ricomincia la procedura.', 'unique-coupon-generator')));
    }

    $validation = ucg_events_prepare_wizard_request($event, $stored_data, array('validate_payment' => false));
    if (is_wp_error($validation)) {
        ucg_events_delete_wizard_data($token);
        wp_send_json_error(array('message' => $validation->get_error_message()));
    }

    $allow_wc = !empty($validation['allow_wc']);
    $allow_in_loco = !empty($validation['allow_in_loco']);
    $wizard_payment_mode = isset($validation['payment_mode']) ? sanitize_key($validation['payment_mode']) : '';

    if ($requested_mode !== '' && in_array($requested_mode, array('online', 'loco', 'manual'), true)) {
        $wizard_payment_mode = $requested_mode;
    }

    $is_manual_mode = ($wizard_payment_mode === 'manual');
    $is_in_loco_mode = ($wizard_payment_mode === 'loco');

    $should_bypass_wc = $is_manual_mode || $is_in_loco_mode;
    if (!$should_bypass_wc && $payment_method === '' && !$allow_wc && $allow_in_loco) {
        $should_bypass_wc = true;
        $is_in_loco_mode = true;
        $wizard_payment_mode = 'loco';
    }

    if ($should_bypass_wc) {
        if ($is_in_loco_mode && !$allow_in_loco) {
            wp_send_json_error(array('message' => esc_html__('Il pagamento in loco non è disponibile.', 'unique-coupon-generator')));
        }

        $manual_mode = $is_manual_mode ? 'manual' : 'loco';
        $manual_result = ucg_events_generate_manual_ticket_response($event, $validation, $manual_mode);
        if (is_wp_error($manual_result)) {
            wp_send_json_error(array('message' => $manual_result->get_error_message()));
        }

        ucg_events_delete_wizard_data($token);

        wp_send_json_success($manual_result);
    }

    if (!$allow_wc || !function_exists('WC')) {
        wp_send_json_error(array('message' => esc_html__('Il pagamento online non è disponibile al momento.', 'unique-coupon-generator')));
    }

    $gateways = WC()->payment_gateways();
    $available_gateways = ($gateways && method_exists($gateways, 'get_available_payment_gateways')) ? $gateways->get_available_payment_gateways() : array();
    $available_gateways = ucg_events_filter_gateway_map_for_event($event, $available_gateways);

    if (empty($available_gateways)) {
        ucg_events_delete_wizard_data($token);
        wp_send_json_error(array('message' => esc_html__('Il pagamento online non è disponibile al momento.', 'unique-coupon-generator')));
    }

    if (empty($payment_method) || empty($available_gateways[$payment_method])) {
        wp_send_json_error(array('message' => esc_html__('Metodo di pagamento non valido.', 'unique-coupon-generator')));
    }

    $gateway = $available_gateways[$payment_method];

    $gateway_payload = array();
    if (!empty($_POST['gateway_data'])) {
        $decoded = json_decode(wp_unslash($_POST['gateway_data']), true);
        if (is_array($decoded)) {
            $gateway_payload = $decoded;
        }
    }

    $order_result = ucg_events_create_order_for_ticket($event, $validation['ticket'], $validation, $gateway, $return_url);
    if (is_wp_error($order_result)) {
        wp_send_json_error(array('message' => $order_result->get_error_message()));
    }

    $order = $order_result['order'];
    $wizard_token = $order_result['wizard_token'];
    $order_id = $order->get_id();

    $clean_payload = ucg_events_sanitize_gateway_data($gateway_payload);
    $original_post = $_POST;
    foreach ($clean_payload as $key => $value) {
        $_POST[$key] = $value;
    }
    $_POST['payment_method'] = $payment_method;
    $_POST['terms'] = 'on';
    $_POST['woocommerce-process-checkout-nonce'] = wp_create_nonce('woocommerce-process_checkout');

    $billing_first = sanitize_text_field($validation['nome']);
    $billing_last = sanitize_text_field($validation['cognome']);
    $billing_email = sanitize_email($validation['email']);
    $billing_phone_source = $validation['phone_full'] !== '' ? $validation['phone_full'] : $validation['phone_digits'];
    $billing_phone = sanitize_text_field($billing_phone_source);
    $billing_address = !empty($event->luogo) ? sanitize_text_field($event->luogo) : '';
    $billing_city = $billing_address !== '' ? $billing_address : sanitize_text_field(__('Online', 'unique-coupon-generator'));

    $_POST['billing_first_name'] = $billing_first;
    $_POST['billing_last_name'] = $billing_last;
    $_POST['billing_email'] = $billing_email;
    $_POST['billing_phone'] = $billing_phone;
    if ($billing_address !== '') {
        $_POST['billing_address_1'] = $billing_address;
    }
    $_POST['billing_city'] = $billing_city;

    if (function_exists('wc_get_base_location')) {
        $base_location = wc_get_base_location();
        if (!empty($base_location['country'])) {
            $_POST['billing_country'] = sanitize_text_field($base_location['country']);
        }
        if (!empty($base_location['state'])) {
            $_POST['billing_state'] = sanitize_text_field($base_location['state']);
        }
    }

    if (method_exists($gateway, 'validate_fields')) {
        $fields_valid = $gateway->validate_fields();
        if ($fields_valid === false) {
            $error_message = '';
            if (function_exists('wc_get_notices')) {
                $error_notices = wc_get_notices('error');
                if (!empty($error_notices)) {
                    $messages = array();
                    foreach ($error_notices as $notice) {
                        if (is_array($notice) && isset($notice['notice'])) {
                            $messages[] = wp_strip_all_tags($notice['notice']);
                        } elseif (is_string($notice)) {
                            $messages[] = wp_strip_all_tags($notice);
                        }
                    }
                    if (!empty($messages)) {
                        $error_message = implode(' ', array_filter($messages));
                    }
                }
                wc_clear_notices();
            }

            if ($error_message === '') {
                $error_message = esc_html__('Compila i campi obbligatori del pagamento.', 'unique-coupon-generator');
            }

            $_POST = $original_post;
            wp_send_json_error(array('message' => $error_message));
        }
    }

    $payment_result = $gateway->process_payment($order_id);

    $_POST = $original_post;

    if (!is_array($payment_result) || ($payment_result['result'] ?? '') !== 'success') {
        ucg_events_delete_wizard_data($token);
        $message = !empty($payment_result['message']) ? wp_strip_all_tags($payment_result['message']) : esc_html__('Pagamento non riuscito. Riprova o seleziona un altro metodo.', 'unique-coupon-generator');
        wp_send_json_error(array('message' => $message));
    }

    ucg_events_delete_wizard_data($token);

    if (!empty($payment_result['redirect'])) {
        wp_send_json_success(array(
            'status'       => 'redirect',
            'redirect_url' => ucg_events_safe_url($payment_result['redirect']),
            'order_key'    => $order->get_order_key(),
            'wizard_token' => $wizard_token,
            'message'      => esc_html__('Reindirizzamento al pagamento…', 'unique-coupon-generator'),
        ));
    }

    $order = wc_get_order($order_id);
    if (!$order) {
        wp_send_json_error(array('message' => esc_html__('Ordine non trovato dopo il pagamento.', 'unique-coupon-generator')));
    }

    $order_gateway = $order->get_payment_method();
    $allow_pending = ucg_events_is_offline_gateway($order_gateway);

    $handler_args = array();
    if ($allow_pending) {
        $handler_args = array(
            'allow_pending' => true,
            'pending_state' => 'da pagare',
        );
    }

    ucg_events_handle_completed_order($order_id, $handler_args);
    $order = wc_get_order($order_id);
    if (!$order) {
        wp_send_json_error(array('message' => esc_html__('Ordine non trovato dopo il pagamento.', 'unique-coupon-generator')));
    }

    $paid_statuses = apply_filters('ucg_events_paid_statuses', array('processing', 'completed'));
    $has_paid_status = !empty($paid_statuses) ? $order->has_status($paid_statuses) : false;

    if (!$has_paid_status && !$order->is_paid() && !$allow_pending) {
        wp_send_json_error(array('message' => esc_html__('Pagamento non ancora completato.', 'unique-coupon-generator')));
    }

    $response_args = array();
    if ($allow_pending && !$order->is_paid()) {
        $response_args = array(
            'mode'    => 'in_loco',
            'message' => ucg_events_get_pending_gateway_message($order_gateway),
        );
    }

    $response = ucg_events_build_order_ticket_response($order, $validation, $wizard_token, $response_args);
    wp_send_json_success($response);
}

/**
 * Generate a ticket for manual or in-loco payment modes without invoking WooCommerce.
 *
 * @param object $event      Event configuration.
 * @param array  $validation Sanitized wizard data.
 * @param string $mode       Selected payment mode ("manual" or "loco").
 *
 * @return array|WP_Error Response payload for the wizard.
 */
function ucg_events_generate_manual_ticket_response($event, $validation, $mode = 'loco') {
    $mode = ($mode === 'manual') ? 'manual' : 'loco';

    $whatsapp_enabled = !isset($event->mostra_whatsapp) || (int) $event->mostra_whatsapp !== 0;
    $allow_png = !empty($event->mostra_download_png);
    $allow_pdf = !empty($event->mostra_download_pdf);

    $ticket_status = ($mode === 'manual') ? 'pagato' : 'da pagare';
    $status_label = ucg_events_get_ticket_status_label($ticket_status);

    $send_whatsapp = $whatsapp_enabled && !empty($validation['whatsapp_opt_in']);
    $download_png = $allow_png && !empty($validation['download_png']);
    $download_pdf = $allow_pdf && !empty($validation['download_pdf']);

    $has_delivery_choice = $send_whatsapp || $download_png || $download_pdf;
    if (!$has_delivery_choice) {
        $download_png = $allow_png ? true : false;
        $download_pdf = $allow_pdf ? true : false;
    }

    $ticket_code = ucg_events_generate_ticket_code($event->id);
    $qr_url = ucg_events_generate_qr_code($ticket_code);
    $phone_for_ticket = $validation['telefono'] !== '' ? $validation['telefono'] : ($validation['phone_full'] ?? '');

    $ticket_id = ucg_events_insert_ticket($event->id, array(
        'utente_nome'     => $validation['full_name'],
        'utente_email'    => $validation['email'],
        'utente_telefono' => $phone_for_ticket,
        'tipo_ticket'     => $validation['ticket_slug'],
        'prezzo'          => $validation['ticket']['price'] ?? 0,
        'stato'           => $ticket_status,
        'qr_code'         => $qr_url,
        'ticket_code'     => $ticket_code,
        'pr_id'           => $validation['pr_id'] ?? 0,
    ));

    if (!$ticket_id) {
        return new WP_Error('ucg_ticket_generation_failed', esc_html__('Impossibile generare il ticket. Riprova più tardi.', 'unique-coupon-generator'));
    }

    $ticket_row = ucg_events_get_ticket_by_code($ticket_code);
    if ($ticket_row) {
        ucg_events_send_ticket_email($event, $ticket_row, 'confirmation');
    }

    ucg_events_refresh_wc_stock($event->id);

    $pdf_url = ucg_events_generate_ticket_pdf($event, $ticket_code, $qr_url, $validation['full_name'], $validation['email'], $phone_for_ticket);

    $ticket_entry = array(
        'code'         => $ticket_code,
        'status'       => $ticket_status,
        'status_label' => $status_label,
    );

    if ($download_pdf && $pdf_url !== '') {
        $ticket_entry['pdf_url'] = $pdf_url;
    }

    if ($download_png && $qr_url !== '') {
        $ticket_entry['png_url'] = $qr_url;
    }

    if (!$has_delivery_choice) {
        if ($allow_pdf && $pdf_url !== '') {
            $ticket_entry['pdf_url'] = $pdf_url;
        }
        if ($allow_png && $qr_url !== '') {
            $ticket_entry['png_url'] = $qr_url;
        }
    }

    $whatsapp_link = '';
    if ($send_whatsapp) {
        $ticket_url_for_whatsapp = '';
        if ($download_pdf && $pdf_url !== '') {
            $ticket_url_for_whatsapp = $pdf_url;
        } elseif ($download_png && $qr_url !== '') {
            $ticket_url_for_whatsapp = $qr_url;
        } elseif ($allow_pdf && $pdf_url !== '') {
            $ticket_url_for_whatsapp = $pdf_url;
        } elseif ($qr_url !== '') {
            $ticket_url_for_whatsapp = $qr_url;
        }

        if ($ticket_url_for_whatsapp !== '') {
            $whatsapp_link_raw = ucg_generate_whatsapp_link(
                $validation['phone_full'],
                array(
                    'qr_link'     => $ticket_url_for_whatsapp,
                    'coupon_code' => $ticket_code,
                    'user_name'   => $validation['full_name'],
                    'template'    => ucg_events_get_whatsapp_template($event),
                )
            );

            if ($whatsapp_link_raw) {
                $whatsapp_link = ucg_events_safe_url($whatsapp_link_raw);
            }
        }
    }

    $response_message = ($mode === 'manual')
        ? esc_html__('Ticket generato – nessun pagamento richiesto.', 'unique-coupon-generator')
        : esc_html__('Ticket prenotato – paga all’ingresso.', 'unique-coupon-generator');

    return array(
        'status'        => 'success',
        'mode'          => $mode === 'manual' ? 'manual' : 'in_loco',
        'message'       => $response_message,
        'tickets'       => array($ticket_entry),
        'whatsapp_link' => $whatsapp_link,
    );
}

/**
 * Retrieve ticket data for a completed order after redirecting from the payment gateway.
 */
function ucg_events_ajax_fetch_order_summary() {
    check_ajax_referer('ucg_ticket_wizard', 'nonce');

    $order_key = isset($_POST['order_key']) ? sanitize_text_field(wp_unslash($_POST['order_key'])) : '';
    $wizard_token = isset($_POST['wizard_token']) ? sanitize_text_field(wp_unslash($_POST['wizard_token'])) : '';

    if ($order_key === '' || $wizard_token === '') {
        wp_send_json_error(array('message' => esc_html__('Parametri mancanti.', 'unique-coupon-generator')));
    }

    if (!function_exists('wc_get_order_id_by_order_key')) {
        wp_send_json_error(array('message' => esc_html__('WooCommerce non è disponibile.', 'unique-coupon-generator')));
    }

    $order_id = wc_get_order_id_by_order_key($order_key);
    if (!$order_id) {
        wp_send_json_error(array('message' => esc_html__('Ordine non trovato.', 'unique-coupon-generator')));
    }

    $order = wc_get_order($order_id);
    if (!$order) {
        wp_send_json_error(array('message' => esc_html__('Ordine non trovato.', 'unique-coupon-generator')));
    }

    $stored_token = $order->get_meta('_ucg_wizard_token', true);
    if (empty($stored_token) || !hash_equals((string) $stored_token, (string) $wizard_token)) {
        wp_send_json_error(array('message' => esc_html__('Impossibile verificare l’ordine.', 'unique-coupon-generator')));
    }

    $order_gateway = $order->get_payment_method();
    $allow_pending = ucg_events_is_offline_gateway($order_gateway);

    $handler_args = array();
    if ($allow_pending) {
        $handler_args = array(
            'allow_pending' => true,
            'pending_state' => 'da pagare',
        );
    }

    ucg_events_handle_completed_order($order_id, $handler_args);
    $order = wc_get_order($order_id);
    if (!$order) {
        wp_send_json_error(array('message' => esc_html__('Ordine non disponibile.', 'unique-coupon-generator')));
    }

    $paid_statuses = apply_filters('ucg_events_paid_statuses', array('processing', 'completed'));
    $has_paid_status = !empty($paid_statuses) ? $order->has_status($paid_statuses) : false;
    if (!$has_paid_status && !$order->is_paid() && !$allow_pending) {
        wp_send_json_error(array('message' => esc_html__('Pagamento non ancora completato.', 'unique-coupon-generator')));
    }

    $reference_data = array();
    foreach ($order->get_items() as $item) {
        $form_json = $item->get_meta('_ucg_event_form_data', true);
        if ($form_json) {
            $decoded = json_decode($form_json, true);
            if (is_array($decoded)) {
                $reference_data = $decoded;
                break;
            }
        }
    }

    $response_args = array();
    if ($allow_pending && !$order->is_paid()) {
        $response_args = array(
            'mode'    => 'in_loco',
            'message' => ucg_events_get_pending_gateway_message($order_gateway),
        );
    }

    $response = ucg_events_build_order_ticket_response($order, $reference_data, $wizard_token, $response_args);
    wp_send_json_success($response);
}

/**
 * Normalize and validate wizard form data.
 *
 * @param object $event   Event configuration.
 * @param array  $params  Raw form parameters.
 * @param array  $options Optional settings.
 *
 * @return array|WP_Error
 */
function ucg_events_prepare_wizard_request($event, $params, $options = array()) {
    $options = wp_parse_args($options, array(
        'validate_payment' => true,
    ));

    if (!$event) {
        return new WP_Error('ucg_invalid_event', __('Evento non valido.', 'unique-coupon-generator'));
    }

    if ($event->stato !== 'pubblicato') {
        return new WP_Error('ucg_event_closed', __('L’evento non è attualmente disponibile.', 'unique-coupon-generator'));
    }

    if (!empty($event->blocco_ticket) && current_time('timestamp') > strtotime($event->blocco_ticket)) {
        return new WP_Error('ucg_event_blocked', __('Il tempo di emissione dei ticket è esaurito o i ticket non sono più disponibili. Contatta l’assistenza o l’organizzatore dell’evento.', 'unique-coupon-generator'));
    }

    $allow_wc = !empty($event->pagamento_woocommerce);
    $allow_in_loco = !empty($event->pagamento_in_loco);
    $show_whatsapp = !isset($event->mostra_whatsapp) || (int) $event->mostra_whatsapp !== 0;
    $allow_png = !empty($event->mostra_download_png);
    $allow_pdf = !empty($event->mostra_download_pdf);

    $nome = sanitize_text_field($params['nome'] ?? '');
    $cognome = sanitize_text_field($params['cognome'] ?? '');
    $full_name = trim($nome . ' ' . $cognome);
    $email = sanitize_email($params['email'] ?? '');

    $telefono_input = isset($params['telefono']) ? sanitize_text_field($params['telefono']) : '';
    $normalized_phone = ucg_normalize_phone_number($telefono_input);
    $phone_digits = $normalized_phone['digits'] ?? '';
    $phone_full = $normalized_phone['full'] ?? '';
    $phone_display = $normalized_phone['display'] ?? '';
    if ($phone_display === '' && $phone_full !== '') {
        $phone_display = $phone_full;
    }

    $ticket_slug = '';
    if (isset($params['ticket_type'])) {
        $ticket_slug = sanitize_text_field($params['ticket_type']);
    }
    if ($ticket_slug === '' && isset($params['ticket_slug'])) {
        $ticket_slug = sanitize_text_field($params['ticket_slug']);
    }
    $pr_id = $event->gestione_pr ? absint($params['pr_id'] ?? 0) : 0;

    $send_whatsapp = $show_whatsapp && !empty($params['whatsapp_opt_in']);
    $download_png = $allow_png && !empty($params['download_png']);
    $download_pdf = $allow_pdf && !empty($params['download_pdf']);

    $available_delivery = ($show_whatsapp ? 1 : 0) + ($allow_png ? 1 : 0) + ($allow_pdf ? 1 : 0);
    $selected_delivery = ($send_whatsapp ? 1 : 0) + ($download_png ? 1 : 0) + ($download_pdf ? 1 : 0);
    if ($available_delivery > 0 && $selected_delivery !== 1) {
        return new WP_Error('ucg_delivery_required', __('Seleziona una sola modalità di ricezione per il QR code.', 'unique-coupon-generator'));
    }

    if (empty($normalized_phone['is_valid'])) {
        return new WP_Error('ucg_phone_invalid', __('Il numero di telefono deve iniziare con +39 e contenere 10 cifre.', 'unique-coupon-generator'));
    }

    if ($full_name === '' || $email === '' || $ticket_slug === '') {
        return new WP_Error('ucg_fields_missing', __('Compila tutti i campi obbligatori.', 'unique-coupon-generator'));
    }

    if ($event->mostra_privacy && $event->privacy_page_id && empty($params['privacy_accept'])) {
        return new WP_Error('ucg_privacy', __('Devi accettare la privacy policy per proseguire.', 'unique-coupon-generator'));
    }

    $selected_ticket = null;
    foreach ($event->tipi_ticket as $ticket) {
        if ($ticket['id'] === $ticket_slug) {
            $selected_ticket = $ticket;
            break;
        }
    }

    if (!$selected_ticket) {
        return new WP_Error('ucg_ticket_invalid', __('Tipologia di ticket non valida.', 'unique-coupon-generator'));
    }

    $remaining_global = ucg_events_get_global_remaining($event);
    $remaining_ticket = ucg_events_get_ticket_remaining($event, $selected_ticket);
    if (($remaining_global !== -1 && $remaining_global <= 0) || $remaining_ticket === 0) {
        return new WP_Error('ucg_ticket_unavailable', __('I ticket selezionati non sono più disponibili.', 'unique-coupon-generator'));
    }

    if ($event->gestione_pr) {
        if (!$pr_id) {
            return new WP_Error('ucg_pr_required', __('Seleziona il PR di riferimento.', 'unique-coupon-generator'));
        }
        $valid_pr = null;
        $prs = ucg_events_get_pr_list($event->id);
        foreach ($prs as $pr) {
            if ((int) $pr->id === $pr_id) {
                $valid_pr = $pr;
                break;
            }
        }
        if (!$valid_pr) {
            return new WP_Error('ucg_pr_invalid', __('PR selezionato non valido.', 'unique-coupon-generator'));
        }
        $pr_remaining = ucg_events_get_pr_remaining($event->id, $valid_pr);
        if ($pr_remaining === 0) {
            return new WP_Error('ucg_pr_empty', __('Il PR selezionato ha esaurito i ticket disponibili.', 'unique-coupon-generator'));
        }
    }

    $available_payment_modes = array();
    if ($allow_wc) {
        $available_payment_modes[] = 'online';
    }
    if ($allow_in_loco) {
        $available_payment_modes[] = 'loco';
    }
    if (empty($available_payment_modes)) {
        $available_payment_modes[] = 'manual';
    }

    $requested_payment_mode = isset($params['payment_mode']) ? sanitize_key($params['payment_mode']) : '';
    if ($requested_payment_mode === '' && count($available_payment_modes) === 1) {
        $requested_payment_mode = $available_payment_modes[0];
    }

    if ($requested_payment_mode === '') {
        return new WP_Error('ucg_payment_invalid', __('Seleziona un metodo di pagamento per continuare.', 'unique-coupon-generator'));
    }

    if (!in_array($requested_payment_mode, array('online', 'loco', 'manual'), true)) {
        return new WP_Error('ucg_payment_invalid', __('Metodo di pagamento non valido.', 'unique-coupon-generator'));
    }

    if (!in_array($requested_payment_mode, $available_payment_modes, true)) {
        return new WP_Error('ucg_payment_invalid', __('Il metodo di pagamento selezionato non è disponibile.', 'unique-coupon-generator'));
    }

    if ($requested_payment_mode === 'online' && !$allow_wc) {
        return new WP_Error('ucg_payment_invalid', __('Il metodo di pagamento selezionato non è disponibile.', 'unique-coupon-generator'));
    }

    if ($requested_payment_mode === 'loco' && !$allow_in_loco) {
        return new WP_Error('ucg_payment_invalid', __('Il metodo di pagamento selezionato non è disponibile.', 'unique-coupon-generator'));
    }

    $payment_mode = $requested_payment_mode;

    $contact_digits = $phone_digits !== '' ? $phone_digits : preg_replace('/\D+/', '', $phone_full);
    if ($contact_digits !== '' && ucg_events_ticket_exists_for_contact($event->id, $email, $contact_digits)) {
        return new WP_Error('ucg_ticket_exists', __('Hai già richiesto un ticket per questo evento con i contatti forniti.', 'unique-coupon-generator'));
    }

    return array(
        'event_id'        => $event->id,
        'nome'            => $nome,
        'cognome'         => $cognome,
        'full_name'       => $full_name,
        'email'           => $email,
        'telefono'        => $phone_display,
        'phone_full'      => $phone_full,
        'phone_digits'    => $contact_digits,
        'ticket_type'     => $selected_ticket['id'],
        'ticket_slug'     => $selected_ticket['id'],
        'ticket'          => $selected_ticket,
        'pr_id'           => $pr_id,
        'whatsapp_opt_in' => $send_whatsapp ? 1 : 0,
        'download_png'    => $download_png ? 1 : 0,
        'download_pdf'    => $download_pdf ? 1 : 0,
        'allow_wc'        => $allow_wc,
        'allow_in_loco'   => $allow_in_loco,
        'payment_mode'    => $payment_mode,
        'privacy_accept'  => !empty($params['privacy_accept']) ? 1 : 0,
    );
}

/**
 * Generate a short-lived token for the wizard session.
 */
function ucg_events_generate_wizard_token() {
    return wp_generate_password(16, false, false);
}

/**
 * Store wizard data in the WooCommerce session or transients.
 */
function ucg_events_store_wizard_data($token, $data) {
    if ($token === '' || empty($data) || !is_array($data)) {
        return false;
    }

    if (function_exists('WC') && WC()->session) {
        WC()->session->set('ucg_wizard_' . $token, $data);
        return true;
    }

    set_transient('ucg_wizard_' . $token, $data, 30 * MINUTE_IN_SECONDS);
    return true;
}

/**
 * Retrieve wizard data from session storage.
 */
function ucg_events_get_wizard_data($token) {
    if ($token === '') {
        return array();
    }

    if (function_exists('WC') && WC()->session) {
        $value = WC()->session->get('ucg_wizard_' . $token);
        if (!empty($value) && is_array($value)) {
            return $value;
        }
    }

    $value = get_transient('ucg_wizard_' . $token);
    return is_array($value) ? $value : array();
}

/**
 * Delete wizard data from storage.
 */
function ucg_events_delete_wizard_data($token) {
    if ($token === '') {
        return;
    }

    if (function_exists('WC') && WC()->session) {
        WC()->session->set('ucg_wizard_' . $token, null);
    }

    delete_transient('ucg_wizard_' . $token);
}

/**
 * Sanitize gateway payload arrays before passing them to process_payment.
 */
function ucg_events_sanitize_gateway_data($data) {
    if (!is_array($data)) {
        return array();
    }

    $clean = array();
    foreach ($data as $key => $value) {
        $normalized_key = is_string($key) ? $key : (string) $key;
        if (is_array($value)) {
            $clean[$normalized_key] = ucg_events_sanitize_gateway_data($value);
        } else {
            $clean[$normalized_key] = function_exists('wc_clean') ? wc_clean($value) : sanitize_text_field($value);
        }
    }

    return $clean;
}

/**
 * Create a WooCommerce order for the selected ticket.
 *
 * @param object               $event      Event data.
 * @param array                $ticket     Selected ticket information.
 * @param array                $form_data  Sanitized wizard data.
 * @param WC_Payment_Gateway   $gateway    Selected gateway instance.
 * @param string               $return_url URL to redirect after payment.
 *
 * @return array|WP_Error
 */
function ucg_events_create_order_for_ticket($event, $ticket, $form_data, $gateway, $return_url) {
    if (!function_exists('wc_create_order')) {
        return new WP_Error('ucg_wc_missing', __('WooCommerce non è disponibile.', 'unique-coupon-generator'));
    }

    if (empty($ticket['product_id'])) {
        $synced = ucg_events_sync_wc_products($event->id, $event->titolo, $event->tipi_ticket, $event->stato, $event->numero_ticket);
        foreach ($synced as $candidate) {
            if ($candidate['id'] === $ticket['id']) {
                $ticket['product_id'] = $candidate['product_id'];
                break;
            }
        }
    }

    if (empty($ticket['product_id'])) {
        return new WP_Error('ucg_product_missing', __('Prodotto WooCommerce non disponibile per questo ticket.', 'unique-coupon-generator'));
    }

    $product = wc_get_product($ticket['product_id']);
    if (!$product) {
        return new WP_Error('ucg_product_invalid', __('Prodotto WooCommerce non valido per questo ticket.', 'unique-coupon-generator'));
    }

    $order = wc_create_order();
    if (is_wp_error($order)) {
        return $order;
    }

    $price = isset($ticket['price']) ? (float) $ticket['price'] : (float) $product->get_price();
    $item_id = $order->add_product($product, 1, array(
        'subtotal' => $price,
        'total'    => $price,
    ));

    if (!$item_id) {
        return new WP_Error('ucg_order_item', __('Impossibile aggiungere il ticket all’ordine.', 'unique-coupon-generator'));
    }

    $item = $order->get_item($item_id);
    if ($item) {
        $item->add_meta_data('_ucg_event_id', absint($event->id));
        $item->add_meta_data('_ucg_event_ticket_slug', sanitize_text_field($ticket['id']));
        $item->add_meta_data('_ucg_event_ticket_name', sanitize_text_field($ticket['name'] ?? ''));
        $item->add_meta_data('_ucg_event_pr_id', absint($form_data['pr_id'] ?? 0));
        $item->add_meta_data('_ucg_event_form_data', wp_json_encode($form_data));
        $item->save();
    }

    $billing_phone = $form_data['telefono'] !== '' ? $form_data['telefono'] : ($form_data['phone_full'] ?? '');
    $billing = array(
        'first_name' => $form_data['nome'],
        'last_name'  => $form_data['cognome'],
        'email'      => $form_data['email'],
        'phone'      => $billing_phone,
    );

    $order->set_address($billing, 'billing');
    $order->set_payment_method($gateway);
    $order->set_payment_method_title($gateway->get_method_title());
    if (function_exists('wc_get_customer_ip_address')) {
        $order->set_customer_ip_address(wc_get_customer_ip_address());
    }
    if (function_exists('wc_get_user_agent')) {
        $order->set_customer_user_agent(wc_get_user_agent());
    }

    $order->calculate_taxes();
    $order->calculate_totals();

    $wizard_token = ucg_events_generate_wizard_token();
    $order->update_meta_data('_ucg_wizard_token', $wizard_token);
    if ($return_url) {
        $order->update_meta_data('_ucg_wizard_return_url', ucg_events_safe_url($return_url));
    }

    $order->save();

    return array(
        'order'        => $order,
        'wizard_token' => $wizard_token,
    );
}

/**
 * Build the response payload with ticket download links for an order.
 */
function ucg_events_build_order_ticket_response($order, $reference_data, $wizard_token, $args = array()) {
    if (!is_array($args)) {
        $args = array();
    }

    $args = wp_parse_args($args, array(
        'mode'    => 'online',
        'message' => '',
    ));

    $mode = sanitize_key($args['mode']);
    if ($mode === '') {
        $mode = 'online';
    }

    $message = $args['message'];
    if ($message !== '') {
        $message = wp_strip_all_tags($message);
    }

    $pdf_meta = $order->get_meta('_ucg_ticket_pdf', true);
    $png_meta = $order->get_meta('_ucg_ticket_png', true);

    $pdf_urls = array();
    if (is_array($pdf_meta)) {
        $pdf_urls = array_map('ucg_events_safe_url', array_filter($pdf_meta));
    } elseif (is_string($pdf_meta) && $pdf_meta !== '') {
        $pdf_urls = array(ucg_events_safe_url($pdf_meta));
    }

    $png_urls = array();
    if (is_array($png_meta)) {
        $png_urls = array_map('ucg_events_safe_url', array_filter($png_meta));
    } elseif (is_string($png_meta) && $png_meta !== '') {
        $png_urls = array(ucg_events_safe_url($png_meta));
    }

    $tickets = array();
    $max_items = max(count($pdf_urls), count($png_urls));
    for ($i = 0; $i < $max_items; $i++) {
        $tickets[] = array(
            'pdf_url' => $pdf_urls[$i] ?? '',
            'png_url' => $png_urls[$i] ?? '',
        );
    }

    $phone_reference = '';
    if (is_array($reference_data)) {
        if (!empty($reference_data['phone_full'])) {
            $phone_reference = $reference_data['phone_full'];
        } elseif (!empty($reference_data['telefono'])) {
            $phone_reference = $reference_data['telefono'];
        }
    }

    if ($phone_reference === '') {
        $phone_reference = $order->get_billing_phone();
    }

    $primary_pdf = $tickets[0]['pdf_url'] ?? '';

    $event_id = 0;
    if (is_array($reference_data) && !empty($reference_data['event_id'])) {
        $event_id = (int) $reference_data['event_id'];
    }

    if (!$event_id) {
        foreach ($order->get_items() as $item) {
            $maybe_event = (int) $item->get_meta('_ucg_event_id', true);
            if ($maybe_event) {
                $event_id = $maybe_event;
                break;
            }
        }
    }

    $event_template = '';
    if ($event_id && function_exists('ucg_events_get_event')) {
        $event_object = ucg_events_get_event($event_id);
        if ($event_object) {
            $event_template = ucg_events_get_whatsapp_template($event_object);
        }
    }

    $customer_name = trim($order->get_billing_first_name() . ' ' . $order->get_billing_last_name());
    if ($customer_name === '') {
        $customer_name = $order->get_billing_first_name() ?: $order->get_billing_last_name();
    }

    $whatsapp_link = '';
    if ($primary_pdf !== '') {
        $whatsapp_link = ucg_events_build_whatsapp_link($phone_reference, $primary_pdf, array(
            'user_name' => $customer_name,
            'coupon_code' => '',
            'template' => $event_template,
        ));
        if ($whatsapp_link !== '') {
            $whatsapp_link = ucg_events_safe_url($whatsapp_link);
        }
    }

    if ($message === '') {
        if ($mode === 'in_loco') {
            $message = esc_html__('Ticket prenotato – paga all’ingresso.', 'unique-coupon-generator');
        } else {
            $message = esc_html__('Pagamento completato! Il tuo ticket è pronto.', 'unique-coupon-generator');
        }
    }

    return array(
        'status'        => 'success',
        'mode'          => $mode,
        'message'       => $message,
        'order_id'      => $order->get_id(),
        'order_key'     => $order->get_order_key(),
        'wizard_token'  => $wizard_token,
        'tickets'       => $tickets,
        'whatsapp_link' => $whatsapp_link,
    );
}

/**
 * Build a WhatsApp deeplink for the provided phone number and ticket URL.
 */
function ucg_events_build_whatsapp_link($phone_full, $ticket_url, $placeholders = array()) {
    $ticket_url = ucg_events_safe_url($ticket_url);
    if ($ticket_url === '') {
        return '';
    }

    if (!is_array($placeholders)) {
        $placeholders = array();
    }

    $data = array(
        'qr_link'     => $ticket_url,
        'coupon_code' => isset($placeholders['coupon_code']) ? $placeholders['coupon_code'] : '',
        'user_name'   => isset($placeholders['user_name']) ? $placeholders['user_name'] : '',
    );

    if (!empty($placeholders['template'])) {
        $data['template'] = $placeholders['template'];
    }

    return ucg_generate_whatsapp_link($phone_full, $data);
}

/**
 * Handle ticket form submissions.
 */
function ucg_events_handle_ticket_form_submission() {
    if (empty($_POST['ucg_event_request'])) {
        return;
    }

    $nonce = isset($_POST['ucg_event_nonce']) ? wp_unslash($_POST['ucg_event_nonce']) : '';
    if (!wp_verify_nonce($nonce, 'ucg_event_request')) {
        return;
    }

    $event_id = isset($_POST['ucg_event_id']) ? absint(wp_unslash($_POST['ucg_event_id'])) : 0;
    $event = ucg_events_get_event($event_id);
    if (!$event) {
        ucg_events_redirect_with_notice(__('Evento non valido.', 'unique-coupon-generator'), 'error');
        return;
    }

    if ($event->stato !== 'pubblicato') {
        ucg_events_redirect_with_notice(__('L’evento non è attualmente disponibile.', 'unique-coupon-generator'), 'error');
        return;
    }

    if (!empty($event->blocco_ticket) && current_time('timestamp') > strtotime($event->blocco_ticket)) {
        ucg_events_redirect_with_notice(__('Il tempo di emissione dei ticket è esaurito o i ticket non sono più disponibili. Contatta l’assistenza o l’organizzatore dell’evento.', 'unique-coupon-generator'), 'error');
        return;
    }

    $show_whatsapp_opt_in = !isset($event->mostra_whatsapp) || (int) $event->mostra_whatsapp !== 0;
    $allow_png_download = !empty($event->mostra_download_png);
    $allow_pdf_download = !empty($event->mostra_download_pdf);
    $nome = sanitize_text_field(wp_unslash($_POST['nome'] ?? ''));
    $cognome = sanitize_text_field(wp_unslash($_POST['cognome'] ?? ''));
    $full_name = trim(($nome ?? '') . ' ' . ($cognome ?? ''));
    $email = sanitize_email(wp_unslash($_POST['email'] ?? ''));
    $telefono_input = isset($_POST['telefono']) ? sanitize_text_field(wp_unslash($_POST['telefono'])) : '';
    $normalized_phone = ucg_normalize_phone_number($telefono_input);
    $telefono_display = $normalized_phone['display'] !== '' ? sanitize_text_field($normalized_phone['display']) : '';
    $ticket_slug = sanitize_text_field(wp_unslash($_POST['ticket_type'] ?? ''));
    $pr_id = $event->gestione_pr ? absint(wp_unslash($_POST['pr_id'] ?? 0)) : 0;
    $send_whatsapp = $show_whatsapp_opt_in && !empty($_POST['whatsapp_opt_in']);
    $download_png = $allow_png_download && !empty($_POST['download_png']);
    $download_pdf = $allow_pdf_download && !empty($_POST['download_pdf']);

    $available_delivery = ($show_whatsapp_opt_in ? 1 : 0) + ($allow_png_download ? 1 : 0) + ($allow_pdf_download ? 1 : 0);
    $selected_delivery = ($send_whatsapp ? 1 : 0) + ($download_png ? 1 : 0) + ($download_pdf ? 1 : 0);
    if ($available_delivery > 0 && $selected_delivery !== 1) {
        ucg_events_redirect_with_notice(__('Seleziona una sola modalità di ricezione per il QR code.', 'unique-coupon-generator'), 'error');
        return;
    }

    if (empty($normalized_phone['is_valid'])) {
        ucg_events_redirect_with_notice(__('Il numero di telefono deve iniziare con +39 e contenere 10 cifre.', 'unique-coupon-generator'), 'error');
        return;
    }

    if (empty($full_name) || empty($email) || empty($ticket_slug)) {
        ucg_events_redirect_with_notice(__('Compila tutti i campi obbligatori.', 'unique-coupon-generator'), 'error');
        return;
    }

    if ($event->mostra_privacy && $event->privacy_page_id && empty($_POST['privacy_accept'])) {
        ucg_events_redirect_with_notice(__('Devi accettare la privacy policy per proseguire.', 'unique-coupon-generator'), 'error');
        return;
    }

    $tickets = $event->tipi_ticket;
    $selected_ticket = null;
    foreach ($tickets as $ticket) {
        if ($ticket['id'] === $ticket_slug) {
            $selected_ticket = $ticket;
            break;
        }
    }

    if (!$selected_ticket) {
        ucg_events_redirect_with_notice(__('Tipologia di ticket non valida.', 'unique-coupon-generator'), 'error');
        return;
    }

    $contact_phone_digits = $normalized_phone['digits'];
    if (ucg_events_ticket_exists_for_contact($event->id, $email, $contact_phone_digits)) {
        ucg_events_redirect_with_notice(__('Hai già richiesto un ticket per questo evento con i contatti forniti.', 'unique-coupon-generator'), 'error');
        return;
    }

    $remaining_global = ucg_events_get_global_remaining($event);
    $remaining_ticket = ucg_events_get_ticket_remaining($event, $selected_ticket);

    if (($remaining_global !== -1 && $remaining_global <= 0) || $remaining_ticket === 0) {
        ucg_events_redirect_with_notice(__('I ticket selezionati non sono più disponibili.', 'unique-coupon-generator'), 'error');
        return;
    }

    if ($event->gestione_pr) {
        if (!$pr_id) {
            ucg_events_redirect_with_notice(__('Seleziona il PR di riferimento.', 'unique-coupon-generator'), 'error');
            return;
        }
        $pr_rows = ucg_events_get_pr_list($event->id);
        $valid_pr = null;
        foreach ($pr_rows as $pr) {
            if ((int) $pr->id === $pr_id) {
                $valid_pr = $pr;
                break;
            }
        }
        if (!$valid_pr) {
            ucg_events_redirect_with_notice(__('PR selezionato non valido.', 'unique-coupon-generator'), 'error');
            return;
        }
        $pr_remaining = ucg_events_get_pr_remaining($event->id, $valid_pr);
        if ($pr_remaining === 0) {
            ucg_events_redirect_with_notice(__('Il PR selezionato ha esaurito i ticket disponibili.', 'unique-coupon-generator'), 'error');
            return;
        }
    }

    $allow_wc = !empty($event->pagamento_woocommerce);
    $allow_in_loco = !empty($event->pagamento_in_loco);

    $payment_mode = $allow_wc ? 'online' : ($allow_in_loco ? 'loco' : 'manual');
    if (isset($_POST['payment_mode'])) {
        $payment_mode = sanitize_key(wp_unslash($_POST['payment_mode']));
    }

    if ($payment_mode === 'online' && !$allow_wc) {
        ucg_events_redirect_with_notice(__('Il metodo di pagamento selezionato non è disponibile.', 'unique-coupon-generator'), 'error');
        return;
    }

    if ($payment_mode === 'loco' && !$allow_in_loco) {
        ucg_events_redirect_with_notice(__('Il metodo di pagamento selezionato non è disponibile.', 'unique-coupon-generator'), 'error');
        return;
    }

    if ($payment_mode === 'manual' && ($allow_wc || $allow_in_loco)) {
        ucg_events_redirect_with_notice(__('Metodo di pagamento non valido.', 'unique-coupon-generator'), 'error');
        return;
    }

    if (!in_array($payment_mode, array('online', 'loco', 'manual'), true)) {
        ucg_events_redirect_with_notice(__('Metodo di pagamento non valido.', 'unique-coupon-generator'), 'error');
        return;
    }

    if ($payment_mode === 'online') {
        if (function_exists('WC')) {
            ucg_events_process_woocommerce_checkout($event, $selected_ticket, array(
                'nome' => $nome,
                'cognome' => $cognome,
                'full_name' => $full_name,
                'email' => $email,
                'telefono' => $telefono_display !== '' ? $telefono_display : $normalized_phone['full'],
                'phone_full' => $normalized_phone['full'],
                'phone_digits' => $normalized_phone['digits'],
                'whatsapp_opt_in' => $send_whatsapp ? 1 : 0,
                'download_png' => $download_png ? 1 : 0,
                'download_pdf' => $download_pdf ? 1 : 0,
                'pr_id' => $pr_id,
            ));
            return;
        }

        ucg_events_redirect_with_notice(__('WooCommerce non è attivo, impossibile completare il pagamento.', 'unique-coupon-generator'), 'error');
        return;
    }

    $status = $payment_mode === 'loco' ? 'da pagare' : 'pagato';
    $ticket_code = ucg_events_generate_ticket_code($event->id);
    $qr_url = ucg_events_generate_qr_code($ticket_code);

    $ticket_id = ucg_events_insert_ticket($event->id, array(
        'utente_nome' => $full_name,
        'utente_email' => $email,
        'utente_telefono' => $telefono_display !== '' ? $telefono_display : $normalized_phone['full'],
        'tipo_ticket' => $selected_ticket['id'],
        'prezzo' => $selected_ticket['price'],
        'stato' => $status,
        'qr_code' => $qr_url,
        'ticket_code' => $ticket_code,
        'pr_id' => $pr_id,
    ));

    if ($ticket_id) {
        $ticket = ucg_events_get_ticket_by_code($ticket_code);
        ucg_events_send_ticket_email($event, $ticket, 'confirmation');
        ucg_events_refresh_wc_stock($event->id);
    }

    $whatsapp_key = '';
    if ($ticket_id && $send_whatsapp && $normalized_phone['full'] !== '') {
        $whatsapp_link = ucg_generate_whatsapp_link(
            $normalized_phone['full'],
            array(
                'qr_link'     => $qr_url,
                'coupon_code' => $ticket_code,
                'user_name'   => $full_name,
                'template'    => ucg_events_get_whatsapp_template($event),
            )
        );

        if ($whatsapp_link) {
            $whatsapp_key = ucg_queue_whatsapp_link($whatsapp_link);
        }
    }

    $message = $payment_mode === 'loco'
        ? __('Richiesta ricevuta! Riceverai una email con il tuo ticket da pagare in loco.', 'unique-coupon-generator')
        : __('Ticket generato con successo! Controlla la tua email per i dettagli.', 'unique-coupon-generator');

    $redirect = ucg_events_get_thankyou_redirect($event);
    if ($whatsapp_key) {
        $redirect = add_query_arg('ucg_whatsapp', $whatsapp_key, $redirect);
    } elseif ($download_png && !empty($qr_url)) {
        $redirect = $qr_url;
    } elseif ($download_pdf) {
        $pdf_phone = $telefono_display !== '' ? $telefono_display : $normalized_phone['full'];
        $pdf_url = ucg_events_generate_ticket_pdf($event, $ticket_code, $qr_url, $full_name, $email, $pdf_phone);
        if ($pdf_url) {
            $redirect = $pdf_url;
        }
    }
    ucg_events_redirect_with_notice($message, 'success', $redirect);
}

/**
 * Process WooCommerce checkout redirection.
 */
function ucg_events_process_woocommerce_checkout($event, $ticket, $user_data) {
    if (!function_exists('WC')) {
        ucg_events_redirect_with_notice(__('WooCommerce non è attivo.', 'unique-coupon-generator'), 'error');
        return;
    }

    if (function_exists('wc_load_cart')) {
        wc_load_cart();
    } elseif (method_exists(WC(), 'initialize_cart')) {
        WC()->initialize_cart();
    }

    if (empty($ticket['product_id'])) {
        $tickets = ucg_events_sync_wc_products($event->id, $event->titolo, $event->tipi_ticket, $event->stato, $event->numero_ticket);
        foreach ($tickets as $t) {
            if ($t['id'] === $ticket['id']) {
                $ticket['product_id'] = $t['product_id'];
                break;
            }
        }
        if (empty($ticket['product_id'])) {
            ucg_events_redirect_with_notice(__('Prodotto WooCommerce non disponibile per questo ticket.', 'unique-coupon-generator'), 'error');
            return;
        }
    }

    $cart = WC()->cart;
    if (!$cart) {
        ucg_events_redirect_with_notice(__('Impossibile accedere al carrello WooCommerce.', 'unique-coupon-generator'), 'error');
        return;
    }

    $cart->add_to_cart($ticket['product_id'], 1, 0, array(), array(
        'ucg_event_id' => $event->id,
        'ucg_event_ticket_slug' => $ticket['id'],
        'ucg_event_ticket_name' => $ticket['name'],
        'ucg_event_form_data' => wp_json_encode($user_data),
        'ucg_event_pr_id' => absint($user_data['pr_id'] ?? 0),
        'ucg_unique_key' => uniqid('ucg_', true),
    ));

    $checkout_url = function_exists('wc_get_checkout_url') ? wc_get_checkout_url() : wc_get_page_permalink('checkout');
    wp_safe_redirect($checkout_url);
    exit;
}

/**
 * Add metadata to WooCommerce order items.
 */
function ucg_events_checkout_item_meta($item, $cart_item_key, $values, $order) {
    if (empty($values['ucg_event_id'])) {
        return;
    }

    $item->add_meta_data('_ucg_event_id', absint($values['ucg_event_id']));
    $item->add_meta_data('_ucg_event_ticket_slug', sanitize_text_field($values['ucg_event_ticket_slug']));
    $item->add_meta_data('_ucg_event_ticket_name', sanitize_text_field($values['ucg_event_ticket_name']));
    $item->add_meta_data('_ucg_event_form_data', $values['ucg_event_form_data']);
    $item->add_meta_data('_ucg_event_pr_id', absint($values['ucg_event_pr_id']));
}

/**
 * Generate tickets once the WooCommerce order is completed.
 */
function ucg_events_handle_completed_order($order_id, $args = array()) {
    if (!function_exists('wc_get_order')) {
        return;
    }

    if (!is_array($args)) {
        $args = array();
    }

    $args = wp_parse_args($args, array(
        'allow_pending' => false,
        'pending_state' => 'da pagare',
    ));

    $order = wc_get_order($order_id);
    if (!$order) {
        return;
    }

    $paid_statuses = apply_filters('ucg_events_paid_statuses', array('processing', 'completed'));
    $has_paid_status = !empty($paid_statuses) ? $order->has_status($paid_statuses) : false;

    if (!$has_paid_status && !$order->is_paid()) {
        if (empty($args['allow_pending'])) {
            return;
        }

        $ticket_status = sanitize_text_field($args['pending_state']);
        if ($ticket_status === '') {
            $ticket_status = 'da pagare';
        }
    } else {
        $ticket_status = 'pagato';
    }

    $generated_tickets = false;
    $has_event_items = false;
    $has_other_items = false;

    $existing_pdf_meta = $order->get_meta('_ucg_ticket_pdf', true);
    $existing_png_meta = $order->get_meta('_ucg_ticket_png', true);
    $pdf_links = array();
    if (is_array($existing_pdf_meta)) {
        $pdf_links = array_map('ucg_events_safe_url', array_filter($existing_pdf_meta));
    } elseif (is_string($existing_pdf_meta) && $existing_pdf_meta !== '') {
        $pdf_links[] = ucg_events_safe_url($existing_pdf_meta);
    }

    $png_links = array();
    if (is_array($existing_png_meta)) {
        $png_links = array_map('ucg_events_safe_url', array_filter($existing_png_meta));
    } elseif (is_string($existing_png_meta) && $existing_png_meta !== '') {
        $png_links[] = ucg_events_safe_url($existing_png_meta);
    }

    foreach ($order->get_items() as $item_id => $item) {
        $event_id = $item->get_meta('_ucg_event_id');
        if (!$event_id) {
            $has_other_items = true;
            continue;
        }

        $has_event_items = true;

        if ($item->get_meta('_ucg_ticket_generated')) {
            continue;
        }

        $event = ucg_events_get_event($event_id);
        if (!$event) {
            continue;
        }

        $ticket_slug = $item->get_meta('_ucg_event_ticket_slug');
        $ticket_name = $item->get_meta('_ucg_event_ticket_name');
        $pr_id = absint($item->get_meta('_ucg_event_pr_id'));
        $form_data = json_decode($item->get_meta('_ucg_event_form_data'), true);
        if (!is_array($form_data)) {
            $form_data = array();
        }
        $full_name = $form_data['full_name'] ?? trim(($form_data['nome'] ?? '') . ' ' . ($form_data['cognome'] ?? ''));
        if (empty($full_name)) {
            $full_name = $order->get_formatted_billing_full_name();
        }
        $email = !empty($form_data['email']) ? $form_data['email'] : $order->get_billing_email();
        $phone_full = isset($form_data['phone_full']) ? sanitize_text_field($form_data['phone_full']) : '';
        $form_phone_digits = isset($form_data['phone_digits']) ? preg_replace('/\D+/', '', (string) $form_data['phone_digits']) : '';
        $telefono = isset($form_data['telefono']) ? sanitize_text_field($form_data['telefono']) : '';
        if ($telefono === '' && $phone_full !== '') {
            $telefono = $phone_full;
        } elseif ($telefono === '' && $form_phone_digits !== '') {
            $telefono = '+' . $form_phone_digits;
        } elseif ($telefono === '' && !empty($form_data['phone_prefix'])) {
            $legacy_raw = sanitize_text_field($form_data['phone_prefix']) . $form_phone_digits;
            $legacy_phone = ucg_normalize_phone_number($legacy_raw);
            if ($legacy_phone['full'] !== '') {
                $telefono = $legacy_phone['full'];
            }
        }
        if ($telefono === '') {
            $telefono = $order->get_billing_phone();
        }
        $send_whatsapp = !empty($form_data['whatsapp_opt_in']);
        $download_png = !empty($form_data['download_png']);
        $download_pdf = !empty($form_data['download_pdf']);

        $ticket_config = null;
        foreach ($event->tipi_ticket as $ticket) {
            if ($ticket['id'] === $ticket_slug) {
                $ticket_config = $ticket;
                break;
            }
        }

        $price = $ticket_config ? $ticket_config['price'] : $item->get_total();

        $quantity = max(1, (int) $item->get_quantity());
        for ($i = 0; $i < $quantity; $i++) {
            $ticket_code = ucg_events_generate_ticket_code($event->id);
            $qr_url = ucg_events_generate_qr_code($ticket_code);
            $png_links[] = ucg_events_safe_url($qr_url);
            $pdf_url = ucg_events_generate_ticket_pdf($event, $ticket_code, $qr_url, $full_name, $email, $telefono);
            if ($pdf_url) {
                $pdf_links[] = ucg_events_safe_url($pdf_url);
            }
            $ticket_id = ucg_events_insert_ticket($event->id, array(
                'utente_nome' => $full_name,
                'utente_email' => $email,
                'utente_telefono' => $telefono,
                'tipo_ticket' => $ticket_slug,
                'prezzo' => $price,
                'stato' => $ticket_status,
                'qr_code' => $qr_url,
                'ticket_code' => $ticket_code,
                'pr_id' => $pr_id,
                'order_id' => $order_id,
                'order_item_id' => $item_id,
            ));

            if ($ticket_id) {
                $ticket_row = ucg_events_get_ticket_by_code($ticket_code);
                ucg_events_send_ticket_email($event, $ticket_row, 'confirmation');
                $generated_tickets = true;
            }

            $redirect_meta = $order->get_meta('_ucg_post_purchase_redirect', true);
            if (empty($redirect_meta)) {
                $redirect_payload = array();
                if ($send_whatsapp) {
                    $normalized = ucg_normalize_phone_number($telefono);
                    if ($normalized['full'] !== '') {
                        $whatsapp_link = ucg_generate_whatsapp_link($normalized['full'], array(
                            'qr_link'     => $qr_url,
                            'coupon_code' => $ticket_code,
                            'user_name'   => $full_name,
                            'template'    => ucg_events_get_whatsapp_template($event),
                        ));
                        if ($whatsapp_link) {
                            $redirect_payload = array('type' => 'whatsapp', 'target' => $whatsapp_link);
                        }
                    }
                } elseif ($download_png && !empty($qr_url)) {
                    $redirect_payload = array('type' => 'png', 'target' => $qr_url);
                } elseif ($download_pdf && !empty($pdf_url)) {
                    $redirect_payload = array('type' => 'pdf', 'target' => $pdf_url);
                }

                if (!empty($redirect_payload)) {
                    $order->update_meta_data('_ucg_post_purchase_redirect', $redirect_payload);
                    $order->save();
                }
            }
        }

        $item->add_meta_data('_ucg_ticket_generated', 1, true);
        $item->save();
        ucg_events_refresh_wc_stock($event->id);
    }

    if (!empty($pdf_links)) {
        $pdf_links = array_values(array_unique(array_filter($pdf_links)));
        $order->update_meta_data('_ucg_ticket_pdf', $pdf_links);
    }

    if (!empty($png_links)) {
        $png_links = array_values(array_unique(array_filter($png_links)));
        $order->update_meta_data('_ucg_ticket_png', $png_links);
    }

    if (!empty($pdf_links) || !empty($png_links)) {
        $order->save();
    }

    if ($generated_tickets && $has_event_items && !$has_other_items && $order->has_status(array('processing')) && $order->is_paid()) {
        $order->update_status('completed');
    }
}

/**
 * Retrieve redirect metadata for a WooCommerce order, ensuring tickets are prepared.
 *
 * @param int $order_id WooCommerce order identifier.
 * @return array Array containing the order object, redirect data array, and whether event items exist.
 */
function ucg_events_resolve_wc_order_redirect($order_id) {
    if (empty($order_id) || !function_exists('wc_get_order')) {
        return array(null, array(), false);
    }

    $order = wc_get_order($order_id);
    if (!$order) {
        return array(null, array(), false);
    }

    $has_event_items = false;
    foreach ($order->get_items() as $item) {
        if ($item->get_meta('_ucg_event_id')) {
            $has_event_items = true;
            break;
        }
    }

    $redirect_data = $order->get_meta('_ucg_post_purchase_redirect', true);
    if (!is_array($redirect_data) || empty($redirect_data['target'])) {
        if ($has_event_items) {
            $handler_args = array();
            if (ucg_events_is_offline_gateway($order->get_payment_method())) {
                $handler_args = array(
                    'allow_pending' => true,
                    'pending_state' => 'da pagare',
                );
            }

            ucg_events_handle_completed_order($order_id, $handler_args);
            $order = wc_get_order($order_id);
            if (!$order) {
                return array(null, array(), true);
            }

            $redirect_data = $order->get_meta('_ucg_post_purchase_redirect', true);
            if (!is_array($redirect_data)) {
                $redirect_data = array();
            }
        } else {
            $redirect_data = array();
        }
    }

    return array($order, $redirect_data, $has_event_items);
}

/**
 * Perform an immediate redirect on the thank you page when tickets are present in the order.
 */
function ucg_events_maybe_redirect_order_received() {
    if (is_admin()) {
        return;
    }

    if (function_exists('wp_doing_ajax') && wp_doing_ajax()) {
        return;
    }

    if (!function_exists('is_order_received_page') || !is_order_received_page()) {
        return;
    }

    if (!isset($_GET['key'])) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        return;
    }

    $order_key = sanitize_text_field(wp_unslash($_GET['key'])); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
    if ($order_key === '') {
        return;
    }

    if (!function_exists('wc_get_order_id_by_order_key')) {
        return;
    }

    $order_id = wc_get_order_id_by_order_key($order_key);
    if (!$order_id) {
        return;
    }

    $order = wc_get_order($order_id);
    if ($order) {
        $wizard_return = $order->get_meta('_ucg_wizard_return_url', true);
        $wizard_token = $order->get_meta('_ucg_wizard_token', true);
        if (!empty($wizard_return) && !empty($wizard_token)) {
            $order->delete_meta_data('_ucg_wizard_return_url');
            $order->save();

            $redirect_url = add_query_arg(
                array(
                    'ucg_order_key'    => $order_key,
                    'ucg_wizard_token' => $wizard_token,
                ),
                $wizard_return
            );

            if ($redirect_url) {
                $validated = '';
                if (function_exists('wp_http_validate_url')) {
                    try {
                        $validated = wp_http_validate_url($redirect_url);
                    } catch (Throwable $throwable) {
                        $validated = '';
                    }
                }

                if ($validated) {
                    wp_redirect($validated);
                } else {
                    wp_safe_redirect($redirect_url);
                }
                exit;
            }
        }
    }

    list($order, $redirect_data) = ucg_events_resolve_wc_order_redirect($order_id);
    if (!$order || empty($redirect_data) || empty($redirect_data['target'])) {
        return;
    }

    $target_url = ucg_events_safe_url($redirect_data['target']);
    if ($target_url === '') {
        return;
    }

    $order->delete_meta_data('_ucg_post_purchase_redirect');
    $order->save();

    $validated = '';
    if (function_exists('wp_http_validate_url')) {
        try {
            $validated = wp_http_validate_url($target_url);
        } catch (Throwable $throwable) {
            $validated = '';
        }
    }

    if ($validated) {
        wp_redirect($validated);
    } else {
        wp_safe_redirect($target_url);
    }
    exit;
}

/**
 * Redirect users on the WooCommerce thank you page based on their preferences.
 */
function ucg_events_output_wc_redirect($order_id) {
    list($order, $redirect_data) = ucg_events_resolve_wc_order_redirect($order_id);
    if (!$order || empty($redirect_data) || empty($redirect_data['target'])) {
        return;
    }

    $target = ucg_events_safe_url($redirect_data['target']);
    if ($target === '') {
        return;
    }

    $order->delete_meta_data('_ucg_post_purchase_redirect');
    $order->save();

    $target_js = wp_json_encode($target);
    if (!$target_js) {
        return;
    }

    echo '<script type="text/javascript">(function(){var target=' . $target_js . ';if(!target){return;}function ucgRedirect(){window.location.href=target;}if(document.readyState==="complete"){ucgRedirect();}else{window.addEventListener("load", ucgRedirect);}})();</script>';
}

/**
 * Render the check-in form.
 */
function ucg_events_render_checkin_form($atts) {
    $notice = ucg_events_get_front_notice();
    $code_prefill = isset($_GET['ticket_code']) ? sanitize_text_field(wp_unslash($_GET['ticket_code'])) : '';

    $ticket = null;
    $event = null;
    $ticket_error = '';
    if ($code_prefill !== '') {
        $ticket = ucg_events_get_ticket_by_code($code_prefill);
        if ($ticket) {
            $event = ucg_events_get_event($ticket->evento_id);
        } else {
            $ticket_error = '<div class="ucg-event-notice error">' . esc_html__('Ticket non trovato.', 'unique-coupon-generator') . '</div>';
        }
    }

    $output = '<div class="ucg-checkin-wrapper">';
    if ($notice) {
        $output .= $notice;
    }
    if ($ticket_error !== '') {
        $output .= $ticket_error;
    }

    $output .= '<form method="post" class="ucg-checkin-form">';
    $output .= wp_nonce_field('ucg_check_ticket', 'ucg_check_nonce', true, false);
    $output .= '<input type="hidden" name="ucg_check_ticket" value="1">';
    $output .= '<input type="hidden" name="ticket_action" value="lookup">';
    $output .= '<label>' . esc_html__('Inserisci il codice ticket o scansiona il QR', 'unique-coupon-generator') . '</label>';
    $output .= '<input type="text" name="ticket_code" value="' . esc_attr($code_prefill) . '" required>';
    $output .= '<div class="ucg-qr-wrapper" data-input="input[name=\'ticket_code\']" data-param="ticket_code" data-autostart="1" data-autosubmit="1" data-text-start="' . esc_attr__('Avvia scansione QR', 'unique-coupon-generator') . '" data-text-stop="' . esc_attr__('Interrompi scansione', 'unique-coupon-generator') . '">';
    $output .= '<button type="button" class="ucg-qr-toggle">' . esc_html__('Avvia scansione QR', 'unique-coupon-generator') . '</button>';
    $output .= '<div class="ucg-qr-reader" id="ucg-checkin-qr-reader"></div>';
    $output .= '<p class="ucg-qr-hint">' . esc_html__('Inquadra il QR code del ticket per compilare automaticamente il campo.', 'unique-coupon-generator') . '</p>';
    $output .= '</div>';
    $output .= '<button type="submit" class="ucg-submit">' . esc_html__('Verifica ticket', 'unique-coupon-generator') . '</button>';
    $output .= '</form>';

    if ($ticket) {
        $status_label = ucg_events_get_ticket_status_label($ticket->stato);
        $status_class = 'ucg-status-' . sanitize_title($ticket->stato);

        $payment_done = in_array(strtolower($ticket->stato ?? ''), array('pagato', 'usato'), true);
        $payment_label = $payment_done ? __('Pagato', 'unique-coupon-generator') : __('Da pagare', 'unique-coupon-generator');
        $payment_class = $payment_done ? 'paid' : 'pending';

        $ticket_type = $event ? ucg_events_get_ticket_label($event, $ticket->tipo_ticket) : $ticket->tipo_ticket;
        $pr_name = $ticket->pr_id ? ucg_events_get_pr_name($ticket->pr_id) : '';

        $event_title = $event ? $event->titolo : '';
        $event_date = '';
        if ($event && !empty($event->data_evento)) {
            $event_date = date_i18n(get_option('date_format'), strtotime($event->data_evento));
            if (!empty($event->ora_evento)) {
                $event_date .= ' ' . $event->ora_evento;
            }
        }
        $event_location = $event && !empty($event->luogo) ? $event->luogo : '';
        $event_link = ($event && !empty($event->page_id)) ? ucg_events_safe_url(get_permalink($event->page_id)) : '';

        $price_display = '';
        $price_value = isset($ticket->prezzo) ? (float) $ticket->prezzo : 0;
        if ($price_value > 0) {
            if (function_exists('wc_price')) {
                $price_display = wp_strip_all_tags(wc_price($price_value));
            } else {
                $price_display = number_format_i18n($price_value, 2);
            }
        }

        $created_at = '';
        if (!empty($ticket->data_creazione)) {
            $created_at = date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($ticket->data_creazione));
        }

        $output .= '<div class="ucg-ticket-details">';
        $output .= '<div class="ucg-ticket-summary">';
        $output .= '<div class="ucg-ticket-code-block">';
        $output .= '<span class="ucg-ticket-code-label">' . esc_html__('Codice ticket', 'unique-coupon-generator') . '</span>';
        $output .= '<span class="ucg-ticket-code-value">' . esc_html($ticket->ticket_code) . '</span>';
        $output .= '</div>';
        $output .= '<span class="ucg-status-badge ' . esc_attr($status_class) . '">' . esc_html($status_label) . '</span>';
        $output .= '</div>';

        $output .= '<div class="ucg-ticket-columns">';
        $output .= '<div class="ucg-ticket-column">';
        $output .= '<h4>' . esc_html__('Partecipante', 'unique-coupon-generator') . '</h4>';
        $output .= '<ul class="ucg-ticket-list">';
        $output .= '<li><span class="label">' . esc_html__('Nome', 'unique-coupon-generator') . '</span><span class="value">' . esc_html($ticket->utente_nome) . '</span></li>';
        if (!empty($ticket->utente_email)) {
            $output .= '<li><span class="label">' . esc_html__('Email', 'unique-coupon-generator') . '</span><span class="value">' . esc_html($ticket->utente_email) . '</span></li>';
        }
        if (!empty($ticket->utente_telefono)) {
            $output .= '<li><span class="label">' . esc_html__('Telefono', 'unique-coupon-generator') . '</span><span class="value">' . esc_html($ticket->utente_telefono) . '</span></li>';
        }
        if ($pr_name !== '') {
            $output .= '<li><span class="label">' . esc_html__('PR assegnato', 'unique-coupon-generator') . '</span><span class="value">' . esc_html($pr_name) . '</span></li>';
        }
        if ($created_at !== '') {
            $output .= '<li><span class="label">' . esc_html__('Richiesto il', 'unique-coupon-generator') . '</span><span class="value">' . esc_html($created_at) . '</span></li>';
        }
        $output .= '</ul>';
        $output .= '</div>';

        $output .= '<div class="ucg-ticket-column">';
        $output .= '<h4>' . esc_html__('Evento', 'unique-coupon-generator') . '</h4>';
        $output .= '<ul class="ucg-ticket-list">';
        if ($event_title !== '') {
            $output .= '<li><span class="label">' . esc_html__('Titolo', 'unique-coupon-generator') . '</span><span class="value">' . esc_html($event_title) . '</span></li>';
        }
        if ($event_date !== '') {
            $output .= '<li><span class="label">' . esc_html__('Data', 'unique-coupon-generator') . '</span><span class="value">' . esc_html($event_date) . '</span></li>';
        }
        if ($event_location !== '') {
            $output .= '<li><span class="label">' . esc_html__('Luogo', 'unique-coupon-generator') . '</span><span class="value">' . esc_html($event_location) . '</span></li>';
        }
        if ($event_link) {
            $output .= '<li><span class="label">' . esc_html__('Pagina evento', 'unique-coupon-generator') . '</span><span class="value"><a href="' . esc_url($event_link) . '" target="_blank" rel="noopener noreferrer">' . esc_html__('Apri', 'unique-coupon-generator') . '</a></span></li>';
        }
        $output .= '<li><span class="label">' . esc_html__('Tipo di ticket', 'unique-coupon-generator') . '</span><span class="value">' . esc_html($ticket_type) . '</span></li>';
        if ($price_display !== '') {
            $output .= '<li><span class="label">' . esc_html__('Prezzo', 'unique-coupon-generator') . '</span><span class="value">' . esc_html($price_display) . '</span></li>';
        }
        $output .= '<li><span class="label">' . esc_html__('Pagamento', 'unique-coupon-generator') . '</span><span class="value"><span class="ucg-status-badge ucg-payment-' . esc_attr($payment_class) . '">' . esc_html($payment_label) . '</span></span></li>';
        $output .= '</ul>';
        $output .= '</div>';
        $output .= '</div>';

        if ($ticket->stato !== 'usato') {
            $output .= '<form method="post" class="ucg-ticket-actions">';
            $output .= wp_nonce_field('ucg_check_ticket', 'ucg_check_nonce', true, false);
            $output .= '<input type="hidden" name="ucg_check_ticket" value="1">';
            $output .= '<input type="hidden" name="ticket_action" value="use">';
            $output .= '<input type="hidden" name="ticket_code" value="' . esc_attr($ticket->ticket_code) . '">';
            $output .= '<button type="submit" class="ucg-submit ucg-ticket-use">' . esc_html__('Segna come usato', 'unique-coupon-generator') . '</button>';
            $output .= '</form>';
        }

        $output .= '</div>';
    }

    $output .= '</div>';

    return $output;
}

/**
 * Render the PR ticket payment management form.
 */
function ucg_events_render_ticket_pr_form($atts) {
    $notice = ucg_events_get_front_notice();
    $code_prefill = isset($_GET['ticket_code']) ? sanitize_text_field(wp_unslash($_GET['ticket_code'])) : '';

    $redirect = '';
    if (function_exists('get_queried_object_id')) {
        $page_id = get_queried_object_id();
        if ($page_id) {
            $redirect = get_permalink($page_id);
        }
    }
    if (!$redirect && isset($_SERVER['REQUEST_URI'])) {
        $redirect = home_url(wp_unslash($_SERVER['REQUEST_URI']));
    }
    if (!$redirect) {
        $redirect = home_url('/');
    }

    if (!is_user_logged_in()) {
        $output = '<div class="ucg-checkin-wrapper ucg-pr-wrapper">';
        if ($notice) {
            $output .= $notice;
        }
        $output .= '<div class="ucg-pr-login">';
        $output .= '<h3>' . esc_html__('Accedi per gestire i pagamenti dei ticket', 'unique-coupon-generator') . '</h3>';
        $output .= '<p class="ucg-pr-login-hint">' . esc_html__('Inserisci le tue credenziali di accesso per continuare.', 'unique-coupon-generator') . '</p>';
        $output .= wp_login_form(array(
            'echo' => false,
            'redirect' => ucg_events_safe_url($redirect),
            'label_username' => esc_html__('Nome utente', 'unique-coupon-generator'),
            'label_password' => esc_html__('Password', 'unique-coupon-generator'),
            'label_remember' => esc_html__('Ricordami', 'unique-coupon-generator'),
            'label_log_in' => esc_html__('Accedi', 'unique-coupon-generator'),
        ));
        $output .= '</div>';
        $output .= '</div>';

        return $output;
    }

    if (!current_user_can('manage_woocommerce')) {
        $output = '<div class="ucg-checkin-wrapper ucg-pr-wrapper">';
        if ($notice) {
            $output .= $notice;
        }
        $output .= '<div class="ucg-event-notice error">' . esc_html__('Non hai i permessi necessari per accedere a questa pagina.', 'unique-coupon-generator') . '</div>';
        $output .= '</div>';

        return $output;
    }

    $ticket = null;
    $event = null;
    $ticket_error = '';
    if ($code_prefill !== '') {
        $ticket = ucg_events_get_ticket_by_code($code_prefill);
        if ($ticket) {
            $event = ucg_events_get_event($ticket->evento_id);
        } else {
            $ticket_error = '<div class="ucg-event-notice error">' . esc_html__('Ticket non trovato.', 'unique-coupon-generator') . '</div>';
        }
    }

    $output = '<div class="ucg-checkin-wrapper ucg-pr-wrapper">';
    if ($notice) {
        $output .= $notice;
    }
    if ($ticket_error !== '') {
        $output .= $ticket_error;
    }

    $output .= '<form method="post" class="ucg-checkin-form ucg-pr-form">';
    $output .= wp_nonce_field('ucg_pr_ticket', 'ucg_pr_nonce', true, false);
    $output .= '<input type="hidden" name="ucg_pr_ticket" value="1">';
    $output .= '<input type="hidden" name="ticket_action" value="lookup">';
    $output .= '<label>' . esc_html__('Inserisci il codice ticket o scansiona il QR', 'unique-coupon-generator') . '</label>';
    $output .= '<input type="text" name="ticket_code" value="' . esc_attr($code_prefill) . '" required>';
    $output .= '<div class="ucg-qr-wrapper" data-input="input[name=\'ticket_code\']" data-param="ticket_code" data-autostart="1" data-autosubmit="1" data-text-start="' . esc_attr__('Avvia scansione QR', 'unique-coupon-generator') . '" data-text-stop="' . esc_attr__('Interrompi scansione', 'unique-coupon-generator') . '">';
    $output .= '<button type="button" class="ucg-qr-toggle">' . esc_html__('Avvia scansione QR', 'unique-coupon-generator') . '</button>';
    $output .= '<div class="ucg-qr-reader" id="ucg-pr-qr-reader"></div>';
    $output .= '<p class="ucg-qr-hint">' . esc_html__('Inquadra il QR code assegnato al ticket per compilarlo automaticamente.', 'unique-coupon-generator') . '</p>';
    $output .= '</div>';
    $output .= '<button type="submit" class="ucg-submit">' . esc_html__('Cerca ticket', 'unique-coupon-generator') . '</button>';
    $output .= '</form>';

    if ($ticket) {
        $status_label = ucg_events_get_ticket_status_label($ticket->stato);
        $status_class = 'ucg-status-' . sanitize_title($ticket->stato);

        $payment_done = in_array(strtolower($ticket->stato ?? ''), array('pagato', 'usato'), true);
        $payment_label = $payment_done ? __('Pagato', 'unique-coupon-generator') : __('Da pagare', 'unique-coupon-generator');
        $payment_toggle = $payment_done ? __('Pagato? Sì', 'unique-coupon-generator') : __('Pagato? No', 'unique-coupon-generator');
        $payment_class = $payment_done ? 'paid' : 'pending';

        $ticket_type = $event ? ucg_events_get_ticket_label($event, $ticket->tipo_ticket) : $ticket->tipo_ticket;
        $pr_name = $ticket->pr_id ? ucg_events_get_pr_name($ticket->pr_id) : '';

        $event_title = $event ? $event->titolo : '';
        $event_date = '';
        if ($event && !empty($event->data_evento)) {
            $event_date = date_i18n(get_option('date_format'), strtotime($event->data_evento));
            if (!empty($event->ora_evento)) {
                $event_date .= ' ' . $event->ora_evento;
            }
        }
        $event_location = $event && !empty($event->luogo) ? $event->luogo : '';
        $event_link = ($event && !empty($event->page_id)) ? ucg_events_safe_url(get_permalink($event->page_id)) : '';

        $price_display = '';
        $price_value = isset($ticket->prezzo) ? (float) $ticket->prezzo : 0;
        if ($price_value > 0) {
            if (function_exists('wc_price')) {
                $price_display = wp_strip_all_tags(wc_price($price_value));
            } else {
                $price_display = number_format_i18n($price_value, 2);
            }
        }

        $created_at = '';
        if (!empty($ticket->data_creazione)) {
            $created_at = date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($ticket->data_creazione));
        }

        $output .= '<div class="ucg-ticket-details">';
        $output .= '<div class="ucg-ticket-summary">';
        $output .= '<div class="ucg-ticket-code-block">';
        $output .= '<span class="ucg-ticket-code-label">' . esc_html__('Codice ticket', 'unique-coupon-generator') . '</span>';
        $output .= '<span class="ucg-ticket-code-value">' . esc_html($ticket->ticket_code) . '</span>';
        $output .= '</div>';
        $output .= '<span class="ucg-status-badge ' . esc_attr($status_class) . '">' . esc_html($status_label) . '</span>';
        $output .= '</div>';

        $payment_display = sprintf('%s · %s', $payment_label, $payment_toggle);
        $output .= '<div class="ucg-payment-summary ucg-payment-' . esc_attr($payment_class) . '">';
        $output .= '<span class="label">' . esc_html__('Stato pagamento', 'unique-coupon-generator') . '</span>';
        $output .= '<span class="value">' . esc_html($payment_display) . '</span>';
        $output .= '</div>';

        $output .= '<div class="ucg-ticket-columns">';
        $output .= '<div class="ucg-ticket-column">';
        $output .= '<h4>' . esc_html__('Partecipante', 'unique-coupon-generator') . '</h4>';
        $output .= '<ul class="ucg-ticket-list">';
        $output .= '<li><span class="label">' . esc_html__('Nome', 'unique-coupon-generator') . '</span><span class="value">' . esc_html($ticket->utente_nome) . '</span></li>';
        if (!empty($ticket->utente_email)) {
            $output .= '<li><span class="label">' . esc_html__('Email', 'unique-coupon-generator') . '</span><span class="value">' . esc_html($ticket->utente_email) . '</span></li>';
        }
        if (!empty($ticket->utente_telefono)) {
            $output .= '<li><span class="label">' . esc_html__('Telefono', 'unique-coupon-generator') . '</span><span class="value">' . esc_html($ticket->utente_telefono) . '</span></li>';
        }
        if ($pr_name !== '') {
            $output .= '<li><span class="label">' . esc_html__('PR assegnato', 'unique-coupon-generator') . '</span><span class="value">' . esc_html($pr_name) . '</span></li>';
        }
        if ($created_at !== '') {
            $output .= '<li><span class="label">' . esc_html__('Richiesto il', 'unique-coupon-generator') . '</span><span class="value">' . esc_html($created_at) . '</span></li>';
        }
        $output .= '</ul>';
        $output .= '</div>';

        $output .= '<div class="ucg-ticket-column">';
        $output .= '<h4>' . esc_html__('Evento', 'unique-coupon-generator') . '</h4>';
        $output .= '<ul class="ucg-ticket-list">';
        if ($event_title !== '') {
            $output .= '<li><span class="label">' . esc_html__('Titolo', 'unique-coupon-generator') . '</span><span class="value">' . esc_html($event_title) . '</span></li>';
        }
        if ($event_date !== '') {
            $output .= '<li><span class="label">' . esc_html__('Data', 'unique-coupon-generator') . '</span><span class="value">' . esc_html($event_date) . '</span></li>';
        }
        if ($event_location !== '') {
            $output .= '<li><span class="label">' . esc_html__('Luogo', 'unique-coupon-generator') . '</span><span class="value">' . esc_html($event_location) . '</span></li>';
        }
        if ($event_link) {
            $output .= '<li><span class="label">' . esc_html__('Pagina evento', 'unique-coupon-generator') . '</span><span class="value"><a href="' . esc_url($event_link) . '" target="_blank" rel="noopener noreferrer">' . esc_html__('Apri', 'unique-coupon-generator') . '</a></span></li>';
        }
        $output .= '<li><span class="label">' . esc_html__('Tipo di ticket', 'unique-coupon-generator') . '</span><span class="value">' . esc_html($ticket_type) . '</span></li>';
        if ($price_display !== '') {
            $output .= '<li><span class="label">' . esc_html__('Prezzo', 'unique-coupon-generator') . '</span><span class="value">' . esc_html($price_display) . '</span></li>';
        }
        $output .= '<li><span class="label">' . esc_html__('Pagamento', 'unique-coupon-generator') . '</span><span class="value"><span class="ucg-status-badge ucg-payment-' . esc_attr($payment_class) . '">' . esc_html($payment_label) . '</span></span></li>';
        $output .= '<li><span class="label">' . esc_html__('Pagato?', 'unique-coupon-generator') . '</span><span class="value">' . esc_html($payment_done ? __('Sì', 'unique-coupon-generator') : __('No', 'unique-coupon-generator')) . '</span></li>';
        $output .= '</ul>';
        $output .= '</div>';
        $output .= '</div>';

        if (!$payment_done) {
            $output .= '<form method="post" class="ucg-ticket-actions ucg-ticket-payment">';
            $output .= wp_nonce_field('ucg_pr_ticket', 'ucg_pr_nonce', true, false);
            $output .= '<input type="hidden" name="ucg_pr_ticket" value="1">';
            $output .= '<input type="hidden" name="ticket_action" value="pay">';
            $output .= '<input type="hidden" name="ticket_code" value="' . esc_attr($ticket->ticket_code) . '">';
            $output .= '<button type="submit" class="ucg-submit ucg-ticket-pay">' . esc_html__('Pagato? Sì', 'unique-coupon-generator') . '</button>';
            $output .= '</form>';
        }

        $output .= '</div>';
    }

    $output .= '</div>';

    return $output;
}

/**
 * Handle PR ticket payment submissions.
 */
function ucg_events_handle_ticket_pr_submission() {
    if (empty($_POST['ucg_pr_ticket'])) {
        return;
    }

    $nonce = isset($_POST['ucg_pr_nonce']) ? wp_unslash($_POST['ucg_pr_nonce']) : '';
    if (!wp_verify_nonce($nonce, 'ucg_pr_ticket')) {
        return;
    }

    $base_redirect = ucg_events_get_safe_redirect();
    $base_redirect = remove_query_arg(array('ticket_code', 'ucg_event_notice'), $base_redirect);

    if (!is_user_logged_in()) {
        wp_safe_redirect(wp_login_url($base_redirect));
        exit;
    }

    if (!current_user_can('manage_woocommerce')) {
        ucg_events_redirect_with_notice(__('Non hai i permessi necessari per eseguire questa operazione.', 'unique-coupon-generator'), 'error', $base_redirect);
        return;
    }

    $action = isset($_POST['ticket_action']) ? sanitize_text_field(wp_unslash($_POST['ticket_action'])) : 'lookup';
    $code = sanitize_text_field(wp_unslash($_POST['ticket_code'] ?? ''));

    if ($code === '') {
        ucg_events_redirect_with_notice(__('Inserisci un codice ticket valido.', 'unique-coupon-generator'), 'error', $base_redirect);
        return;
    }

    $ticket = ucg_events_get_ticket_by_code($code);
    if (!$ticket) {
        ucg_events_redirect_with_notice(__('Ticket non trovato.', 'unique-coupon-generator'), 'error', $base_redirect);
        return;
    }

    $redirect_with_code = add_query_arg('ticket_code', $ticket->ticket_code, $base_redirect);

    if ($action === 'pay') {
        $current_status = strtolower($ticket->stato ?? '');
        if ($current_status === 'pagato') {
            ucg_events_redirect_with_notice(__('Il ticket risulta già pagato.', 'unique-coupon-generator'), 'warning', $redirect_with_code);
            return;
        }

        if ($current_status === 'usato') {
            ucg_events_redirect_with_notice(__('Il ticket è già stato utilizzato: impossibile modificarne il pagamento.', 'unique-coupon-generator'), 'warning', $redirect_with_code);
            return;
        }

        ucg_events_update_ticket_status($ticket->id, 'pagato');
        $message = __('Lo stato di pagamento del ticket è stato aggiornato con successo - Ticket PAGATO', 'unique-coupon-generator');
        ucg_events_redirect_with_notice($message, 'success', $redirect_with_code);
        return;
    }

    ucg_events_redirect_with_notice(__('Ticket trovato! Controlla i dettagli qui sotto.', 'unique-coupon-generator'), 'success', $redirect_with_code);
}

/**
 * Handle check-in submissions.
 */
function ucg_events_handle_checkin_submission() {
    if (empty($_POST['ucg_check_ticket'])) {
        return;
    }

    $nonce = isset($_POST['ucg_check_nonce']) ? wp_unslash($_POST['ucg_check_nonce']) : '';
    if (!wp_verify_nonce($nonce, 'ucg_check_ticket')) {
        return;
    }

    $action = isset($_POST['ticket_action']) ? sanitize_text_field(wp_unslash($_POST['ticket_action'])) : 'lookup';
    $code = sanitize_text_field(wp_unslash($_POST['ticket_code'] ?? ''));

    if ($code === '') {
        ucg_events_redirect_with_notice(__('Inserisci un codice ticket valido.', 'unique-coupon-generator'), 'error');
        return;
    }

    $base_redirect = ucg_events_get_safe_redirect();
    $base_redirect = remove_query_arg(array('ticket_code', 'ucg_event_notice'), $base_redirect);

    $ticket = ucg_events_get_ticket_by_code($code);
    if (!$ticket) {
        ucg_events_redirect_with_notice(__('Ticket non trovato.', 'unique-coupon-generator'), 'error', $base_redirect);
        return;
    }

    $redirect_with_code = add_query_arg('ticket_code', $ticket->ticket_code, $base_redirect);

    if ($action === 'use') {
        if ($ticket->stato === 'usato') {
            $message = sprintf(__('Il ticket %s risulta già utilizzato.', 'unique-coupon-generator'), esc_html($ticket->ticket_code));
            ucg_events_redirect_with_notice($message, 'warning', $redirect_with_code);
            return;
        }

        ucg_events_update_ticket_status($ticket->id, 'usato');
        $event = ucg_events_get_event($ticket->evento_id);
        $event_title = $event ? $event->titolo : '';
        $message = sprintf(__('Ticket %1$s segnato come USATO per %2$s.', 'unique-coupon-generator'), esc_html($ticket->ticket_code), esc_html($event_title));
        ucg_events_redirect_with_notice($message, 'success', $redirect_with_code);
        return;
    }

    ucg_events_redirect_with_notice(__('Ticket trovato! Controlla i dettagli qui sotto.', 'unique-coupon-generator'), 'success', $redirect_with_code);
}

/**
 * Store front-end notices and redirect.
 */
function ucg_events_redirect_with_notice($message, $type = 'success', $redirect = '') {
    $key = wp_generate_password(10, false, false);
    set_transient('ucg_event_notice_' . $key, array('message' => $message, 'type' => $type), 120);

    $redirect = ucg_events_get_safe_redirect($redirect);
    $redirect = remove_query_arg('ucg_event_notice', $redirect);
    $redirect = add_query_arg('ucg_event_notice', $key, $redirect);
    wp_safe_redirect($redirect);
    exit;
}

/**
 * Retrieve front-end notices if present.
 */
function ucg_events_get_front_notice() {
    if (empty($_GET['ucg_event_notice'])) {
        return '';
    }

    $key = sanitize_key(wp_unslash($_GET['ucg_event_notice']));
    $notice = get_transient('ucg_event_notice_' . $key);
    if (!$notice) {
        return '';
    }

    delete_transient('ucg_event_notice_' . $key);
    $type = $notice['type'] ?? 'success';
    $message = $notice['message'] ?? '';

    return '<div class="ucg-event-notice ' . esc_attr($type) . '">' . wp_kses_post($message) . '</div>';
}

/**
 * Determine the thank-you redirect URL.
 */
function ucg_events_get_thankyou_redirect($event) {
    if ($event && !empty($event->thankyou_page_id)) {
        $url = get_permalink($event->thankyou_page_id);
        if ($url) {
            return $url;
        }
    }

    return wp_get_referer() ?: home_url('/');
}

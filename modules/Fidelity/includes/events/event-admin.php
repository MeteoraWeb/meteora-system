<?php

/**
 * Author: Meteora Web <https://meteoraweb.com>
 */

if (!defined('ABSPATH')) {
    exit;
}

add_action('admin_menu', 'mms_events_register_menu', 30);
add_action('admin_enqueue_scripts', 'mms_events_admin_assets');
add_action('admin_post_ucg_save_event', 'mms_events_handle_save_event');
add_action('admin_post_ucg_change_event_status', 'mms_events_handle_change_status');
add_action('admin_post_ucg_delete_ticket', 'mms_events_handle_delete_ticket');
add_action('admin_post_ucg_delete_event', 'mms_events_handle_delete_event');

/**
 * Register the admin menu for the event manager.
 */
function mms_events_register_menu() {
    $parent_slug = 'ucc-gestione-coupon';

    ucg_safe_add_submenu_page(
        $parent_slug,
        __('Gestione Eventi', 'unique-coupon-generator'),
        __('Gestione Eventi', 'unique-coupon-generator'),
        'manage_options',
        'ucg-eventi',
        'mms_events_render_admin_page'
    );

    ucg_safe_add_submenu_page(
        $parent_slug,
        __('Visualizza Ticket', 'unique-coupon-generator'),
        __('Visualizza Ticket', 'unique-coupon-generator'),
        'manage_options',
        'ucg-eventi-ticket',
        'mms_events_render_tickets_page'
    );

    ucg_safe_add_submenu_page(
        $parent_slug,
        __('Report PR', 'unique-coupon-generator'),
        __('Report PR', 'unique-coupon-generator'),
        'manage_options',
        'ucg-eventi-report-pr',
        'mms_events_render_pr_report_page'
    );
}

/**
 * Enqueue admin assets for the event pages.
 */
function mms_events_admin_assets($hook) {
    if (strpos($hook ?? '', 'ucg-eventi') === false && strpos($hook ?? '', 'ucg-admin-events') === false) {
        return;
    }

    wp_enqueue_media();
    wp_enqueue_style('ucg-events-admin', UCG_PLUGIN_URL . 'assets/css/ucg-events-admin.css', array(), UCG_VERSION);
    wp_enqueue_script('ucg-events-admin', UCG_PLUGIN_URL . 'assets/js/ucg-events-admin.js', array('jquery'), UCG_VERSION, true);

    wp_localize_script('ucg-events-admin', 'UCGEventsAdmin', array(
        'addTicket' => __('Aggiungi tipo di ticket', 'unique-coupon-generator'),
        'remove' => __('Rimuovi', 'unique-coupon-generator'),
        'addPR' => __('Aggiungi PR', 'unique-coupon-generator'),
        'confirmRemove' => __('Sei sicuro di voler rimuovere questa riga?', 'unique-coupon-generator'),
        'selectImage' => __('Seleziona immagine', 'unique-coupon-generator'),
    ));
}

/**
 * Build a plugin admin URL supporting optional tab arguments.
 */
function mms_events_admin_url($slug, $tab = '', $args = array()) {
    $slug = is_string($slug) ? preg_replace('/[^a-z0-9_-]/i', '', $slug) : '';
    if ('' === $slug) {
        return admin_url('admin.php');
    }

    $url = admin_url('admin.php?page=' . $slug);
    if (!empty($tab)) {
        $tab = preg_replace('/[^a-z0-9_-]/i', '', (string) $tab);
        if ($tab !== '') {
            $url = add_query_arg('tab', $tab, $url);
        }
    }

    if (!empty($args) && is_array($args)) {
        $url = add_query_arg($args, $url);
    }

    return $url;
}

/**
 * Ensure the redirect URL is safe and fallbacks to the legacy page when needed.
 */
function mms_events_capture_redirect($redirect, $fallback_slug = 'ucg-eventi', $fallback_tab = '') {
    $redirect = is_string($redirect) ? esc_url_raw($redirect) : '';
    if ('' === $redirect) {
        $redirect = mms_events_admin_url($fallback_slug, $fallback_tab);
    }

    return $redirect;
}

/**
 * Append the event information to a redirect URL preserving the current tab.
 */
function mms_events_append_event_to_redirect($redirect, $event_id = 0) {
    if ('' === $redirect) {
        return $redirect;
    }

    $redirect = remove_query_arg(array('event', 'action'), $redirect);
    if ($event_id) {
        $redirect = add_query_arg(
            array(
                'action' => 'edit',
                'event'  => (int) $event_id,
            ),
            $redirect
        );
    }

    return $redirect;
}

/**
 * Handle the save event action.
 */
function mms_events_handle_save_event() {
    if (!current_user_can('manage_options')) {
        wp_die(__('Non hai i permessi sufficienti per eseguire questa azione.', 'unique-coupon-generator'));
    }

    check_admin_referer('ucg_save_event');

    $redirect_param = isset($_POST['ucg_redirect']) ? wp_unslash($_POST['ucg_redirect']) : '';
    $redirect_base  = mms_events_capture_redirect($redirect_param);

    $event_id = isset($_POST['event_id']) ? absint(wp_unslash($_POST['event_id'])) : 0;
    $current_event = $event_id ? mms_events_get_event($event_id) : null;

    $titolo = sanitize_text_field(wp_unslash($_POST['titolo_evento'] ?? ''));
    $descrizione = wp_kses_post(wp_unslash($_POST['descrizione_evento'] ?? ''));
    $immagine = sanitize_text_field(wp_unslash($_POST['immagine_evento'] ?? ''));
    $data_evento = sanitize_text_field(wp_unslash($_POST['data_evento'] ?? ''));
    $ora_evento = sanitize_text_field(wp_unslash($_POST['ora_evento'] ?? ''));
    $luogo = sanitize_text_field(wp_unslash($_POST['luogo_evento'] ?? ''));
    $numero_ticket = isset($_POST['numero_ticket']) ? max(0, intval(wp_unslash($_POST['numero_ticket']))) : 0;
    $thankyou_page_id = isset($_POST['thankyou_page_id']) ? absint(wp_unslash($_POST['thankyou_page_id'])) : 0;
    $reminder_days = isset($_POST['reminder_days']) ? max(0, intval(wp_unslash($_POST['reminder_days']))) : 3;
    $mostra_contenuto = isset($_POST['mostra_contenuto']) ? 1 : 0;
    $pagamento_wc = !empty($_POST['pagamento_woocommerce']) ? 1 : 0;
    $pagamento_in_loco = !empty($_POST['pagamento_in_loco']) ? 1 : 0;
    $gateway_selection = isset($_POST['pagamento_wc_gateways']) ? (array) wp_unslash($_POST['pagamento_wc_gateways']) : array();
    $gateway_selection = array_map('sanitize_key', $gateway_selection);
    $gateway_selection = array_values(array_filter(array_unique($gateway_selection)));
    if (!$pagamento_wc) {
        $gateway_selection = array();
    }
    $mostra_privacy = !empty($_POST['mostra_privacy']) ? 1 : 0;
    $privacy_page_id = $mostra_privacy ? absint(wp_unslash($_POST['privacy_page_id'] ?? 0)) : 0;
    $mostra_whatsapp = !empty($_POST['mostra_whatsapp']) ? 1 : 0;
    $mostra_download_png = !empty($_POST['mostra_download_png']) ? 1 : 0;
    $mostra_download_pdf = !empty($_POST['mostra_download_pdf']) ? 1 : 0;
    $whatsapp_message = isset($_POST['whatsapp_message']) ? ucg_sanitize_whatsapp_message(wp_unslash($_POST['whatsapp_message'])) : '';
    $gestione_pr = !empty($_POST['gestione_pr']) ? 1 : 0;
    $stato = sanitize_text_field(wp_unslash($_POST['stato_evento'] ?? 'bozza'));
    if (!in_array($stato, array('bozza', 'pubblicato', 'chiuso'), true)) {
        $stato = 'bozza';
    }

    $email_subject_confirm = sanitize_text_field(wp_unslash($_POST['email_subject_confirm'] ?? ''));
    $email_subject_reminder = sanitize_text_field(wp_unslash($_POST['email_subject_reminder'] ?? ''));
    $email_body_confirm = wp_kses_post(wp_unslash($_POST['email_body_confirm'] ?? ''));
    $email_body_reminder = wp_kses_post(wp_unslash($_POST['email_body_reminder'] ?? ''));
    $email_sender_raw = isset($_POST['email_sender']) ? wp_unslash($_POST['email_sender']) : '';
    $email_sender = sanitize_email($email_sender_raw);

    $blocco_data = sanitize_text_field(wp_unslash($_POST['blocco_data'] ?? ''));
    $blocco_ora = sanitize_text_field(wp_unslash($_POST['blocco_ora'] ?? ''));
    $blocco_ticket = '';
    if ($blocco_data) {
        $blocco_ticket = $blocco_data;
        if ($blocco_ora) {
            $blocco_ticket .= ' ' . $blocco_ora;
        } else {
            $blocco_ticket .= ' 00:00:00';
        }
    }

    $tickets = mms_events_parse_ticket_types($_POST);
    if (empty($tickets)) {
        mms_events_add_admin_notice(__('Devi inserire almeno un tipo di ticket.', 'unique-coupon-generator'), 'error');
        wp_safe_redirect(mms_events_append_event_to_redirect($redirect_base, $event_id));
        exit;
    }

    if ($numero_ticket <= 0) {
        mms_events_add_admin_notice(__('Il numero massimo di ticket deve essere maggiore di zero.', 'unique-coupon-generator'), 'error');
        wp_safe_redirect(mms_events_append_event_to_redirect($redirect_base, $event_id));
        exit;
    }

    if ($email_sender_raw !== '' && $email_sender === '') {
        mms_events_add_admin_notice(__('L\'indirizzo email del mittente non è valido.', 'unique-coupon-generator'), 'error');
        wp_safe_redirect(mms_events_append_event_to_redirect($redirect_base, $event_id));
        exit;
    }

    $event_data = array(
        'titolo' => $titolo,
        'descrizione' => $descrizione,
        'immagine' => $immagine,
        'data_evento' => $data_evento,
        'ora_evento' => $ora_evento,
        'luogo' => $luogo,
        'numero_ticket' => $numero_ticket,
        'tipi_ticket' => mms_events_encode_ticket_types($tickets),
        'pagamento_woocommerce' => $pagamento_wc,
        'pagamento_wc_gateways' => wp_json_encode($gateway_selection),
        'pagamento_in_loco' => $pagamento_in_loco,
        'privacy_page_id' => $privacy_page_id,
        'mostra_privacy' => $mostra_privacy,
        'mostra_whatsapp' => $mostra_whatsapp,
        'mostra_download_png' => $mostra_download_png,
        'mostra_download_pdf' => $mostra_download_pdf,
        'whatsapp_message' => $whatsapp_message,
        'gestione_pr' => $gestione_pr,
        'blocco_ticket' => $blocco_ticket ? $blocco_ticket : null,
        'thankyou_page_id' => $thankyou_page_id,
        'reminder_days' => $reminder_days,
        'mostra_contenuto' => $mostra_contenuto,
        'stato' => $stato,
        'email_subject_confirm' => $email_subject_confirm,
        'email_body_confirm' => $email_body_confirm,
        'email_subject_reminder' => $email_subject_reminder,
        'email_body_reminder' => $email_body_reminder,
        'email_sender' => $email_sender,
        'page_id' => $current_event ? (int) $current_event->page_id : 0,
    );

    if (empty($data_evento)) {
        mms_events_add_admin_notice(__('Devi specificare la data dell\'evento.', 'unique-coupon-generator'), 'error');
        wp_safe_redirect(mms_events_append_event_to_redirect($redirect_base, $event_id));
        exit;
    }

    $event_id = mms_events_upsert_event($event_id, $event_data, $tickets, $current_event);

    if ($gestione_pr) {
        mms_events_sync_pr_entries($event_id, $_POST);
    } else {
        mms_events_sync_pr_entries($event_id, array());
    }

    mms_events_add_admin_notice(__('Evento salvato correttamente.', 'unique-coupon-generator'));
    wp_safe_redirect(mms_events_append_event_to_redirect($redirect_base, $event_id));
    exit;
}

/**
 * Parse ticket types from the request.
 */
function mms_events_parse_ticket_types($request) {
    $names = isset($request['ticket_name']) ? (array) wp_unslash($request['ticket_name']) : array();
    $prices = isset($request['ticket_price']) ? (array) wp_unslash($request['ticket_price']) : array();
    $max = isset($request['ticket_max']) ? (array) wp_unslash($request['ticket_max']) : array();
    $ids = isset($request['ticket_id']) ? (array) wp_unslash($request['ticket_id']) : array();
    $product_ids = isset($request['ticket_product_id']) ? (array) wp_unslash($request['ticket_product_id']) : array();

    $tickets = array();

    foreach ((array) $names as $index => $name) {
        $name = sanitize_text_field($name);
        if (empty($name)) {
            continue;
        }

        $id = isset($ids[$index]) && $ids[$index] ? sanitize_title($ids[$index]) : mms_events_normalize_ticket_slug($name, $index);
        $price = isset($prices[$index]) ? ucg_parse_float($prices[$index]) : 0;
        $max_count = isset($max[$index]) ? max(0, intval($max[$index])) : 0;
        $product_id = isset($product_ids[$index]) ? absint($product_ids[$index]) : 0;

        $tickets[$id] = array(
            'id' => $id,
            'name' => $name,
            'price' => $price,
            'max' => $max_count,
            'product_id' => $product_id,
        );
    }

    return array_values($tickets);
}

/**
 * Insert or update an event in the database.
 */
function mms_events_upsert_event($event_id, $data, $tickets, $existing_event = null) {
    global $wpdb;
    $table = mms_events_table('events');

    $now = current_time('mysql');

    $page_id = isset($data['page_id']) ? (int) $data['page_id'] : 0;

    $data['blocco_ticket'] = !empty($data['blocco_ticket']) ? date('Y-m-d H:i:s', strtotime($data['blocco_ticket'])) : null;

    $format_map = array(
        'titolo' => '%s',
        'descrizione' => '%s',
        'immagine' => '%s',
        'data_evento' => '%s',
        'ora_evento' => '%s',
        'luogo' => '%s',
        'numero_ticket' => '%d',
        'tipi_ticket' => '%s',
        'pagamento_woocommerce' => '%d',
        'pagamento_wc_gateways' => '%s',
        'pagamento_in_loco' => '%d',
        'privacy_page_id' => '%d',
        'mostra_privacy' => '%d',
        'mostra_whatsapp' => '%d',
        'mostra_download_png' => '%d',
        'mostra_download_pdf' => '%d',
        'whatsapp_message' => '%s',
        'gestione_pr' => '%d',
        'blocco_ticket' => '%s',
        'thankyou_page_id' => '%d',
        'reminder_days' => '%d',
        'mostra_contenuto' => '%d',
        'email_subject_confirm' => '%s',
        'email_body_confirm' => '%s',
        'email_subject_reminder' => '%s',
        'email_body_reminder' => '%s',
        'email_sender' => '%s',
        'stato' => '%s',
        'page_id' => '%d',
        'created_at' => '%s',
        'updated_at' => '%s',
    );

    if ($event_id) {
        $data['updated_at'] = $now;
        $formats = array();
        foreach ($data as $key => $value) {
            if (!isset($format_map[$key])) {
                continue;
            }
            $formats[] = $format_map[$key];
        }

        $result = $wpdb->update($table, $data, array('id' => $event_id), $formats, array('%d'));
        if ($result === false) {
            ucg_log_error('Errore durante l\'aggiornamento evento: ' . $wpdb->last_error);
        }
    } else {
        $data['created_at'] = $now;
        $data['updated_at'] = $now;
        $formats = array();
        foreach ($data as $key => $value) {
            if (!isset($format_map[$key])) {
                continue;
            }
            $formats[] = $format_map[$key];
        }

        $result = $wpdb->insert($table, $data, $formats);
        if ($result === false) {
            ucg_log_error('Errore durante la creazione evento: ' . $wpdb->last_error);
        }
        $event_id = (int) $wpdb->insert_id;
    }

    if ($event_id && !empty($data['pagamento_woocommerce'])) {
        $tickets = mms_events_sync_wc_products($event_id, $data['titolo'], $tickets, $data['stato'], $data['numero_ticket']);
        $wpdb->update(
            $table,
            array('tipi_ticket' => mms_events_encode_ticket_types($tickets), 'updated_at' => current_time('mysql')),
            array('id' => $event_id),
            array('%s', '%s'),
            array('%d')
        );
    }

    if ($event_id && function_exists('mms_events_sync_event_page')) {
        $page_payload = array(
            'titolo' => $data['titolo'],
            'descrizione' => $data['descrizione'],
            'immagine' => $data['immagine'],
            'data_evento' => $data['data_evento'],
            'ora_evento' => $data['ora_evento'],
            'luogo' => $data['luogo'],
            'mostra_contenuto' => $data['mostra_contenuto'],
            'tipi_ticket' => $tickets,
            'page_id' => $page_id,
            'stato' => $data['stato'],
        );

        $synced_page_id = mms_events_sync_event_page($event_id, $page_payload, $existing_event);
        if ($synced_page_id) {
            if ($synced_page_id !== $page_id) {
                $wpdb->update(
                    $table,
                    array('page_id' => $synced_page_id, 'updated_at' => current_time('mysql')),
                    array('id' => $event_id),
                    array('%d', '%s'),
                    array('%d')
                );
            }
            $page_id = $synced_page_id;
        }
    }

    return $event_id;
}

/**
 * Synchronise PR entries for an event.
 */
function mms_events_sync_pr_entries($event_id, $request) {
    global $wpdb;
    $table = mms_events_table('pr');
    $event_id = absint($event_id);
    if (!$event_id) {
        return;
    }

    $wpdb->delete($table, array('evento_id' => $event_id), array('%d'));

    if (empty($request['pr_nome'])) {
        return;
    }

    $names = isset($request['pr_nome']) ? (array) wp_unslash($request['pr_nome']) : array();
    $max = isset($request['pr_max']) ? (array) wp_unslash($request['pr_max']) : array();

    foreach ($names as $index => $name) {
        $name = sanitize_text_field($name);
        if (empty($name)) {
            continue;
        }
        $max_ticket = isset($max[$index]) ? max(0, intval($max[$index])) : 0;
        $wpdb->insert(
            $table,
            array(
                'evento_id' => $event_id,
                'nome_pr' => $name,
                'max_ticket' => $max_ticket,
                'created_at' => current_time('mysql'),
            ),
            array('%d', '%s', '%d', '%s')
        );
    }
}

/**
 * Synchronise WooCommerce products for ticket types.
 */
function mms_events_sync_wc_products($event_id, $event_title, $tickets, $event_status, $event_capacity = 0) {
    if (!function_exists('wc_get_product')) {
        return $tickets;
    }

    $event_capacity = max(0, (int) $event_capacity);

    foreach ($tickets as $index => $ticket) {
        $product_id = !empty($ticket['product_id']) ? absint($ticket['product_id']) : 0;
        $product_title = $event_title . ' – ' . $ticket['name'];

        $product_args = array(
            'post_title' => $product_title,
            'post_status' => 'publish',
            'post_type' => 'product',
        );

        if ($product_id && get_post($product_id)) {
            $product_args['ID'] = $product_id;
            $product_id = wp_update_post($product_args, true);
        } else {
            $product_id = wp_insert_post($product_args, true);
        }

        if (is_wp_error($product_id)) {
            ucg_log_error('Errore creazione prodotto WooCommerce: ' . $product_id->get_error_message());
            continue;
        }

        update_post_meta($product_id, '_virtual', 'yes');
        update_post_meta($product_id, '_downloadable', 'no');
        update_post_meta($product_id, '_price', $ticket['price']);
        update_post_meta($product_id, '_regular_price', $ticket['price']);
        update_post_meta($product_id, '_sold_individually', 'yes');
        $ticket_limit = isset($ticket['max']) ? (int) $ticket['max'] : 0;
        $has_limit = $ticket_limit > 0;
        $should_manage_stock = $has_limit || $event_capacity > 0;

        update_post_meta($product_id, '_manage_stock', $should_manage_stock ? 'yes' : 'no');

        if ($should_manage_stock) {
            $seed_stock = $has_limit ? $ticket_limit : $event_capacity;
            if ($seed_stock < 0) {
                $seed_stock = 0;
            }
            update_post_meta($product_id, '_stock', $seed_stock);
            update_post_meta($product_id, '_stock_status', $seed_stock > 0 ? 'instock' : 'outofstock');
        } else {
            update_post_meta($product_id, '_stock', '');
            update_post_meta($product_id, '_stock_status', 'instock');
        }
        update_post_meta($product_id, 'ucg_event_id', $event_id);
        update_post_meta($product_id, 'ucg_event_ticket_type', $ticket['id']);

        $tickets[$index]['product_id'] = (int) $product_id;
    }

    mms_events_refresh_wc_stock($event_id);

    return $tickets;
}

/**
 * Render the admin notices stored in transient.
 */
function mms_events_render_notices() {
    $notices = get_transient('mms_events_notices');
    if (!empty($notices) && is_array($notices)) {
        foreach ($notices as $notice) {
            $type = !empty($notice['type']) ? $notice['type'] : 'updated';
            printf('<div class="notice notice-%1$s"><p>%2$s</p></div>', esc_attr($type), wp_kses_post($notice['message']));
        }
        delete_transient('mms_events_notices');
    }
}

/**
 * Store an admin notice in a transient to display later.
 */
function mms_events_add_admin_notice($message, $type = 'updated') {
    $notices = get_transient('mms_events_notices');
    if (!is_array($notices)) {
        $notices = array();
    }
    $notices[] = array('message' => $message, 'type' => $type);
    set_transient('mms_events_notices', $notices, 30);
}

/**
 * Render the main event admin page.
 */
function mms_events_render_admin_page($context = array()) {
    if (!current_user_can('manage_options')) {
        return;
    }

    $defaults = array(
        'embedded'     => false,
        'page_slug'    => 'ucg-eventi',
        'tab'          => '',
        'tickets_slug' => 'ucg-eventi-ticket',
        'tickets_tab'  => '',
        'report_slug'  => 'ucg-eventi-report-pr',
        'report_tab'   => '',
    );
    $context = wp_parse_args($context, $defaults);

    $embedded     = !empty($context['embedded']);
    $page_slug    = $context['page_slug'];
    $page_tab     = $context['tab'];
    $tickets_slug = $context['tickets_slug'];
    $tickets_tab  = $context['tickets_tab'];
    $report_slug  = $context['report_slug'];
    $report_tab   = $context['report_tab'];

    global $wpdb;
    $table = mms_events_table('events');

    $current_event_id = isset($_GET['event']) ? absint(wp_unslash($_GET['event'])) : 0;
    $current_action = isset($_GET['action']) ? sanitize_text_field(wp_unslash($_GET['action'])) : '';
    $current_event = $current_event_id ? mms_events_get_event($current_event_id) : null;

    $events = $wpdb->get_results("SELECT * FROM {$table} ORDER BY data_evento DESC, created_at DESC");

    $pages = get_pages(array('post_status' => array('publish')));

    $base_url        = mms_events_admin_url($page_slug, $page_tab);
    $current_view_url = $base_url;
    if ($current_action !== '') {
        $current_view_url = add_query_arg('action', $current_action, $current_view_url);
    }
    if ($current_event_id) {
        $current_view_url = add_query_arg('event', $current_event_id, $current_view_url);
    }

    $wrapper_class = 'ucg-events-admin';
    if ($embedded) {
        echo '<div class="' . esc_attr($wrapper_class . ' ucg-events-admin--embedded') . '">';
        echo '<h2 class="ucg-section-title">' . esc_html__('Gestione Eventi', 'unique-coupon-generator') . '</h2>';
    } else {
        echo '<div class="wrap ' . esc_attr($wrapper_class) . '">';
        echo '<h1>' . esc_html__('Gestione Eventi', 'unique-coupon-generator') . '</h1>';
    }

    mms_events_render_notices();

    if ($current_event) {
        echo '<h2>' . esc_html(sprintf(__('Modifica evento: %s', 'unique-coupon-generator'), $current_event->titolo)) . '</h2>';
    } else {
        echo '<h2>' . esc_html__('Crea nuovo evento', 'unique-coupon-generator') . '</h2>';
    }

    $form_action = esc_url(admin_url('admin-post.php'));
    echo '<form method="post" action="' . $form_action . '">';
    wp_nonce_field('ucg_save_event');
    echo '<input type="hidden" name="action" value="ucg_save_event">';
    echo '<input type="hidden" name="event_id" value="' . esc_attr($current_event ? $current_event->id : 0) . '">';
    $redirect_target = $current_event ? mms_events_admin_url($page_slug, $page_tab, array('action' => 'edit', 'event' => $current_event->id)) : $base_url;
    echo '<input type="hidden" name="ucg_redirect" value="' . esc_url($redirect_target) . '">';

    $titolo = $current_event ? $current_event->titolo : '';
    $descrizione = $current_event ? $current_event->descrizione : '';
    $immagine = $current_event ? $current_event->immagine : '';
    $data_evento = $current_event ? $current_event->data_evento : '';
    $ora_evento = $current_event ? $current_event->ora_evento : '';
    $luogo = $current_event ? $current_event->luogo : '';
    $numero_ticket = $current_event ? $current_event->numero_ticket : '';
    $pagamento_wc = $current_event ? (int) $current_event->pagamento_woocommerce : 0;
    $pagamento_in_loco = $current_event ? (int) $current_event->pagamento_in_loco : 0;
    $pagamento_wc_gateways = $current_event ? mms_events_get_event_gateways($current_event) : array();
    $wc_gateway_options = array();
    if (function_exists('WC')) {
        $gateways_controller = WC()->payment_gateways();
        if ($gateways_controller) {
            if (method_exists($gateways_controller, 'payment_gateways')) {
                $gateway_map = $gateways_controller->payment_gateways();
            } elseif (method_exists($gateways_controller, 'get_available_payment_gateways')) {
                $gateway_map = $gateways_controller->get_available_payment_gateways();
            } else {
                $gateway_map = array();
            }

            foreach ((array) $gateway_map as $gateway_id => $gateway_obj) {
                if (!is_object($gateway_obj)) {
                    continue;
                }

                $enabled = property_exists($gateway_obj, 'enabled') ? $gateway_obj->enabled : 'yes';
                if ('yes' !== $enabled) {
                    continue;
                }

                $title = method_exists($gateway_obj, 'get_title') ? $gateway_obj->get_title() : $gateway_id;
                $description = method_exists($gateway_obj, 'get_description') ? $gateway_obj->get_description() : '';

                $wc_gateway_options[$gateway_id] = array(
                    'id' => $gateway_id,
                    'title' => $title !== '' ? $title : $gateway_id,
                    'label' => wp_strip_all_tags($title !== '' ? $title : $gateway_id),
                    'description' => $description,
                );
            }
        }
    }
    $privacy_page_id = $current_event ? (int) $current_event->privacy_page_id : 0;
    $mostra_privacy = $current_event ? (int) $current_event->mostra_privacy : 0;
    $mostra_whatsapp = $current_event ? (int) $current_event->mostra_whatsapp : 1;
    $mostra_download_png = $current_event ? (int) $current_event->mostra_download_png : 0;
    $mostra_download_pdf = $current_event ? (int) $current_event->mostra_download_pdf : 0;
    $gestione_pr = $current_event ? (int) $current_event->gestione_pr : 0;
    $thankyou_page_id = $current_event ? (int) $current_event->thankyou_page_id : 0;
    $reminder_days = $current_event ? (int) $current_event->reminder_days : 3;
    $mostra_contenuto = $current_event ? (int) $current_event->mostra_contenuto : 1;
    $stato = $current_event ? $current_event->stato : 'bozza';
    $blocco_ticket = $current_event && !empty($current_event->blocco_ticket) ? $current_event->blocco_ticket : '';
    $blocco_data = $blocco_ticket ? date('Y-m-d', strtotime($blocco_ticket)) : '';
    $blocco_ora = $blocco_ticket ? date('H:i', strtotime($blocco_ticket)) : '';
    $email_subject_confirm = $current_event ? $current_event->email_subject_confirm : '';
    $email_body_confirm = $current_event ? $current_event->email_body_confirm : '';
    $email_subject_reminder = $current_event ? $current_event->email_subject_reminder : '';
    $email_body_reminder = $current_event ? $current_event->email_body_reminder : '';
    $email_sender = $current_event ? $current_event->email_sender : '';
    $event_whatsapp_message = $current_event && isset($current_event->whatsapp_message)
        ? ucg_sanitize_whatsapp_message((string) $current_event->whatsapp_message)
        : '';

    echo '<table class="form-table ucg-event-form">';
    echo '<tr><th><label for="titolo_evento">' . esc_html__('Titolo evento', 'unique-coupon-generator') . '</label></th>';
    echo '<td><input type="text" id="titolo_evento" name="titolo_evento" class="regular-text" value="' . esc_attr($titolo) . '" required></td></tr>';

    echo '<tr><th>' . esc_html__('Descrizione evento', 'unique-coupon-generator') . '</th><td>';
    wp_editor($descrizione, 'descrizione_evento', array('textarea_name' => 'descrizione_evento', 'media_buttons' => true));
    echo '</td></tr>';

    echo '<tr><th><label for="immagine_evento">' . esc_html__('Immagine evento', 'unique-coupon-generator') . '</label></th>';
    echo '<td><div class="ucg-image-field">';
    echo '<input type="text" id="immagine_evento" name="immagine_evento" value="' . esc_attr($immagine) . '" class="regular-text">';
    echo '<button type="button" class="button" id="ucg-event-image-button">' . esc_html__('Seleziona immagine', 'unique-coupon-generator') . '</button>';
    if ($immagine) {
        echo '<div class="ucg-image-preview"><img src="' . esc_url($immagine) . '" alt=""></div>';
    }
    echo '</div></td></tr>';

    echo '<tr><th><label for="data_evento">' . esc_html__('Data evento', 'unique-coupon-generator') . '</label></th>';
    echo '<td><input type="date" id="data_evento" name="data_evento" value="' . esc_attr($data_evento) . '" required></td></tr>';

    echo '<tr><th><label for="ora_evento">' . esc_html__('Ora evento', 'unique-coupon-generator') . '</label></th>';
    echo '<td><input type="time" id="ora_evento" name="ora_evento" value="' . esc_attr($ora_evento) . '"></td></tr>';

    echo '<tr><th><label for="luogo_evento">' . esc_html__('Luogo evento', 'unique-coupon-generator') . '</label></th>';
    echo '<td><input type="text" id="luogo_evento" name="luogo_evento" class="regular-text" value="' . esc_attr($luogo) . '"></td></tr>';

    echo '<tr><th><label for="numero_ticket">' . esc_html__('Numero massimo ticket', 'unique-coupon-generator') . '</label></th>';
    echo '<td><input type="number" id="numero_ticket" name="numero_ticket" min="1" value="' . esc_attr($numero_ticket) . '" required></td></tr>';

    echo '<tr><th>' . esc_html__('Tipi di ticket', 'unique-coupon-generator') . '</th><td>';
    echo '<table class="widefat fixed striped ucg-ticket-table"><thead><tr>';
    echo '<th>' . esc_html__('Nome', 'unique-coupon-generator') . '</th>';
    echo '<th>' . esc_html__('Prezzo', 'unique-coupon-generator') . '</th>';
    echo '<th>' . esc_html__('Massimo', 'unique-coupon-generator') . '</th>';
    echo '<th>' . esc_html__('Azioni', 'unique-coupon-generator') . '</th>';
    echo '</tr></thead><tbody id="ucg-ticket-rows">';

    $ticket_rows = $current_event && !empty($current_event->tipi_ticket) ? $current_event->tipi_ticket : array();
    if (empty($ticket_rows)) {
        $ticket_rows = array(array('id' => '', 'name' => '', 'price' => '', 'max' => '', 'product_id' => 0));
    }

    foreach ($ticket_rows as $ticket) {
        echo '<tr class="ucg-ticket-row">';
        echo '<td><input type="text" name="ticket_name[]" value="' . esc_attr($ticket['name']) . '" required>';
        echo '<input type="hidden" name="ticket_id[]" value="' . esc_attr($ticket['id']) . '">';
        echo '<input type="hidden" name="ticket_product_id[]" value="' . esc_attr($ticket['product_id'] ?? 0) . '"></td>';
        echo '<td><input type="text" name="ticket_price[]" value="' . esc_attr($ticket['price']) . '" placeholder="0,00"></td>';
        echo '<td><input type="number" name="ticket_max[]" min="0" value="' . esc_attr($ticket['max']) . '"></td>';
        echo '<td><button type="button" class="button link-delete ucg-remove-ticket">' . esc_html__('Rimuovi', 'unique-coupon-generator') . '</button></td>';
        echo '</tr>';
    }

    echo '</tbody></table>';
    echo '<p><button type="button" class="button" id="ucg-add-ticket">' . esc_html__('Aggiungi tipo di ticket', 'unique-coupon-generator') . '</button></p>';
    echo '</td></tr>';

    $gateway_summary_default = __('Tutti i gateway WooCommerce attivi verranno mostrati.', 'unique-coupon-generator');
    $selected_gateway_labels = array();
    foreach ($pagamento_wc_gateways as $gateway_id) {
        if (!empty($wc_gateway_options[$gateway_id]['label'])) {
            $selected_gateway_labels[] = $wc_gateway_options[$gateway_id]['label'];
        }
    }
    $gateway_summary_text = !empty($selected_gateway_labels) ? implode(', ', $selected_gateway_labels) : $gateway_summary_default;
    $gateway_controls_style = $pagamento_wc ? '' : ' style="display:none;"';
    $gateway_warning_text = __('Sconsigliamo di abilitare Bonifico bancario, Assegni o Contrassegno: possono causare errori durante la generazione dei ticket.', 'unique-coupon-generator');
    $missing_gateways = array_diff($pagamento_wc_gateways, array_keys($wc_gateway_options));
    $missing_message = '';
    if (!empty($missing_gateways)) {
        $missing_count = count($missing_gateways);
        $missing_message = sprintf(
            _n('%d gateway selezionato non è più attivo in WooCommerce.', '%d gateway selezionati non sono più attivi in WooCommerce.', $missing_count, 'unique-coupon-generator'),
            $missing_count
        );
    }

    echo '<tr><th>' . esc_html__('Opzioni pagamento', 'unique-coupon-generator') . '</th><td class="ucg-payment-options-cell">';
    echo '<label><input type="checkbox" id="pagamento_woocommerce" name="pagamento_woocommerce" value="1" data-ucg-gateway-toggle="1" ' . checked($pagamento_wc, 1, false) . '> ' . esc_html__('Pagamento con WooCommerce', 'unique-coupon-generator') . '</label>';
    if (!empty($wc_gateway_options)) {
        echo '<div class="ucg-gateway-controls" data-ucg-gateway-controls' . $gateway_controls_style . '>';
        echo '<button type="button" class="button button-secondary" data-ucg-gateway-open>' . esc_html__('Scegli gateway WooCommerce', 'unique-coupon-generator') . '</button>';
        echo '<p class="description ucg-gateway-summary"><strong>' . esc_html__('Gateway selezionati:', 'unique-coupon-generator') . '</strong> <span data-ucg-gateway-summary data-default="' . esc_attr($gateway_summary_default) . '">' . esc_html($gateway_summary_text) . '</span></p>';
        echo '<p class="description ucg-gateway-note">' . esc_html($gateway_warning_text) . '</p>';
        echo '</div>';

        if ($missing_message !== '') {
            echo '<p class="description ucg-gateway-warning">' . esc_html($missing_message) . '</p>';
        }

        echo '<div class="ucg-wc-gateway-modal" id="ucg-wc-gateway-modal" aria-hidden="true">';
        echo '<div class="ucg-wc-gateway-overlay" data-ucg-gateway-overlay data-ucg-gateway-close></div>';
        echo '<div class="ucg-wc-gateway-dialog" role="dialog" aria-modal="true" aria-labelledby="ucg-wc-gateway-modal-title" tabindex="-1">';
        echo '<header class="ucg-wc-gateway-header"><h2 id="ucg-wc-gateway-modal-title">' . esc_html__('Seleziona i gateway WooCommerce', 'unique-coupon-generator') . '</h2></header>';
        echo '<div class="ucg-wc-gateway-body">';
        echo '<p class="description">' . esc_html__('Sconsigliamo di abilitare Bonifico bancario, Assegni o Contrassegno per evitare errori durante la generazione dei ticket.', 'unique-coupon-generator') . '</p>';
        echo '<div class="ucg-wc-gateway-list">';
        foreach ($wc_gateway_options as $gateway_id => $gateway_data) {
            $is_checked = in_array($gateway_id, $pagamento_wc_gateways, true) ? ' checked' : '';
            $gateway_label = $gateway_data['label'] !== '' ? $gateway_data['label'] : $gateway_id;
            $title_display = $gateway_data['title'] !== '' ? $gateway_data['title'] : $gateway_id;
            echo '<label class="ucg-gateway-option">';
            echo '<input type="checkbox" name="pagamento_wc_gateways[]" value="' . esc_attr($gateway_id) . '" data-label="' . esc_attr($gateway_label) . '"' . $is_checked . '>';
            echo '<span class="ucg-gateway-option-name">' . esc_html($title_display) . '</span>';
            echo '<span class="ucg-gateway-option-id"><code>' . esc_html($gateway_id) . '</code></span>';
            if (!empty($gateway_data['description'])) {
                echo '<span class="ucg-gateway-option-desc">' . wp_kses_post($gateway_data['description']) . '</span>';
            }
            echo '</label>';
        }
        echo '</div>';
        echo '</div>';
        echo '<footer class="ucg-wc-gateway-footer">';
        echo '<button type="button" class="button button-primary" data-ucg-gateway-apply>' . esc_html__('Salva selezione', 'unique-coupon-generator') . '</button>';
        echo '<button type="button" class="button button-secondary" data-ucg-gateway-close>' . esc_html__('Chiudi', 'unique-coupon-generator') . '</button>';
        echo '</footer>';
        echo '</div>';
        echo '</div>';
    } else {
        echo '<p class="description ucg-gateway-empty">' . esc_html__('Nessun gateway WooCommerce attivo è disponibile al momento.', 'unique-coupon-generator') . '</p>';
    }
    echo '<br><label><input type="checkbox" name="pagamento_in_loco" value="1" ' . checked($pagamento_in_loco, 1, false) . '> ' . esc_html__('Pagamento in loco (ticket in stato "Da pagare")', 'unique-coupon-generator') . '</label>';
    echo '</td></tr>';

    echo '<tr><th>' . esc_html__('Opzione WhatsApp', 'unique-coupon-generator') . '</th><td>';
    echo '<label><input type="checkbox" name="mostra_whatsapp" value="1" ' . checked($mostra_whatsapp, 1, false) . ' data-ucg-modal-trigger="whatsapp-reminder"> ' . esc_html__('Mostra “Invio tramite WhatsApp” nel form pubblico', 'unique-coupon-generator') . '</label>';
    echo '<p class="description">' . esc_html__('Quando attivo, gli utenti potranno richiedere il QR anche tramite WhatsApp.', 'unique-coupon-generator') . '</p>';
    echo '<label for="whatsapp_message">' . esc_html__('Messaggio WhatsApp personalizzato', 'unique-coupon-generator') . '</label>';
    echo '<textarea name="whatsapp_message" id="whatsapp_message" rows="5" class="large-text" placeholder="' . esc_attr__('Lascia vuoto per usare il messaggio globale predefinito.', 'unique-coupon-generator') . '">' . esc_textarea($event_whatsapp_message) . '</textarea>';
    echo '<p class="description">' . esc_html__('Se compilato, sostituirà il testo WhatsApp predefinito solo per questo evento.', 'unique-coupon-generator') . '</p>';

    $event_placeholders = ucg_get_whatsapp_placeholders();
    if (!empty($event_placeholders)) {
        echo '<div class="ucg-field ucg-field--help ucg-field--whatsapp">';
        echo '<p><strong>' . esc_html__('Variabili disponibili', 'unique-coupon-generator') . '</strong></p>';
        echo '<ul>';
        foreach ($event_placeholders as $placeholder => $description) {
            echo '<li><code>' . esc_html($placeholder) . '</code> — ' . esc_html($description) . '</li>';
        }
        echo '</ul>';
        echo '</div>';
    }

    $event_preview_sample = ucg_get_whatsapp_preview_data();
    $event_preview_qr     = isset($event_preview_sample['qr_link']) ? esc_url($event_preview_sample['qr_link']) : '';
    $event_preview_code   = isset($event_preview_sample['coupon_code']) ? $event_preview_sample['coupon_code'] : '';
    $event_preview_name   = isset($event_preview_sample['user_name']) ? $event_preview_sample['user_name'] : '';

    echo '<div class="ucg-field ucg-field--preview ucg-field--whatsapp">';
    echo '<button type="button" class="button button-secondary ucg-whatsapp-preview-button" data-ucg-whatsapp-preview="#whatsapp_message" data-ucg-whatsapp-default="' . esc_attr(ucg_get_default_whatsapp_message()) . '" data-ucg-preview-qr="' . esc_attr($event_preview_qr) . '" data-ucg-preview-code="' . esc_attr($event_preview_code) . '" data-ucg-preview-name="' . esc_attr($event_preview_name) . '">';
    echo '<span class="dashicons dashicons-visibility" aria-hidden="true"></span> ' . esc_html__('Anteprima messaggio WhatsApp', 'unique-coupon-generator');
    echo '</button>';
    echo '<p class="description">' . esc_html__('Mostra un esempio con le variabili sostituite. Usa {line_break} per andare a capo.', 'unique-coupon-generator') . '</p>';
    echo '<div class="ucg-whatsapp-preview" data-ucg-whatsapp-output aria-live="polite"></div>';
    echo '</div>';
    echo '<label><input type="checkbox" name="mostra_download_png" value="1" ' . checked($mostra_download_png, 1, false) . '> ' . esc_html__('Mostra “Scarica PNG” nel form pubblico', 'unique-coupon-generator') . '</label><br>';
    echo '<label><input type="checkbox" name="mostra_download_pdf" value="1" ' . checked($mostra_download_pdf, 1, false) . '> ' . esc_html__('Mostra “Scarica PDF” nel form pubblico', 'unique-coupon-generator') . '</label>';
    echo '</td></tr>';

    echo '<tr><th>' . esc_html__('Privacy policy', 'unique-coupon-generator') . '</th><td>';
    echo '<label><input type="checkbox" name="mostra_privacy" value="1" ' . checked($mostra_privacy, 1, false) . '> ' . esc_html__('Mostra privacy policy nel form', 'unique-coupon-generator') . '</label><br>';
    echo '<select name="privacy_page_id"><option value="0">' . esc_html__('Seleziona pagina', 'unique-coupon-generator') . '</option>';
    foreach ($pages as $page) {
        $selected = selected($privacy_page_id, $page->ID, false);
        echo '<option value="' . esc_attr($page->ID) . '" ' . $selected . '>' . esc_html($page->post_title) . '</option>';
    }
    echo '</select>';
    echo '</td></tr>';

    echo '<tr><th>' . esc_html__('Gestione PR', 'unique-coupon-generator') . '</th><td>';
    echo '<label><input type="checkbox" id="gestione_pr" name="gestione_pr" value="1" ' . checked($gestione_pr, 1, false) . '> ' . esc_html__('Abilita Gestione PR', 'unique-coupon-generator') . '</label>';
    echo '<div id="ucg-pr-wrapper" ' . ($gestione_pr ? '' : 'style="display:none;"') . '>';
    echo '<table class="widefat fixed striped"><thead><tr>';
    echo '<th>' . esc_html__('Nome PR', 'unique-coupon-generator') . '</th>';
    echo '<th>' . esc_html__('Max ticket assegnabili', 'unique-coupon-generator') . '</th>';
    echo '<th>' . esc_html__('Azioni', 'unique-coupon-generator') . '</th>';
    echo '</tr></thead><tbody id="ucg-pr-rows">';

    $pr_rows = array();
    if ($current_event && $gestione_pr) {
        $pr_rows = mms_events_get_pr_list($current_event->id);
    }
    if (empty($pr_rows)) {
        $pr_rows = array((object) array('nome_pr' => '', 'max_ticket' => ''));
    }

    foreach ($pr_rows as $pr) {
        echo '<tr class="ucg-pr-row">';
        echo '<td><input type="text" name="pr_nome[]" value="' . esc_attr($pr->nome_pr ?? '') . '"></td>';
        echo '<td><input type="number" name="pr_max[]" min="0" value="' . esc_attr($pr->max_ticket ?? 0) . '"></td>';
        echo '<td><button type="button" class="button link-delete ucg-remove-pr">' . esc_html__('Rimuovi', 'unique-coupon-generator') . '</button></td>';
        echo '</tr>';
    }

    echo '</tbody></table>';
    echo '<p><button type="button" class="button" id="ucg-add-pr">' . esc_html__('Aggiungi PR', 'unique-coupon-generator') . '</button></p>';
    echo '</div>';
    echo '</td></tr>';

    echo '<tr><th>' . esc_html__('Blocco emissione ticket', 'unique-coupon-generator') . '</th><td>';
    echo '<input type="date" name="blocco_data" value="' . esc_attr($blocco_data) . '"> ';
    echo '<input type="time" name="blocco_ora" value="' . esc_attr($blocco_ora) . '">';
    echo '<p class="description">' . esc_html__('Oltre questa data/ora il form front-end verrà bloccato.', 'unique-coupon-generator') . '</p>';
    echo '</td></tr>';

    echo '<tr><th>' . esc_html__('Pagina di ringraziamento', 'unique-coupon-generator') . '</th><td>';
    echo '<select name="thankyou_page_id"><option value="0">' . esc_html__('Usa pagina corrente', 'unique-coupon-generator') . '</option>';
    foreach ($pages as $page) {
        $selected = selected($thankyou_page_id, $page->ID, false);
        echo '<option value="' . esc_attr($page->ID) . '" ' . $selected . '>' . esc_html($page->post_title) . '</option>';
    }
    echo '</select>';
    echo '</td></tr>';

    echo '<tr><th><label for="reminder_days">' . esc_html__('Reminder (giorni prima)', 'unique-coupon-generator') . '</label></th>';
    echo '<td><input type="number" name="reminder_days" id="reminder_days" min="0" value="' . esc_attr($reminder_days) . '"></td></tr>';

    echo '<tr><th><label for="email_sender">' . esc_html__('Email mittente', 'unique-coupon-generator') . '</label></th>';
    echo '<td><input type="email" id="email_sender" name="email_sender" class="regular-text" value="' . esc_attr($email_sender) . '">';
    echo '<p class="description">' . esc_html__('Indirizzo email utilizzato come mittente delle comunicazioni dell’evento. Lascia vuoto per usare l’indirizzo predefinito.', 'unique-coupon-generator') . '</p>';
    echo '</td></tr>';

    $placeholder_help = mms_events_get_email_placeholder_descriptions();
    echo '<tr><th>' . esc_html__('Template email', 'unique-coupon-generator') . '</th><td>';
    echo '<p class="description">' . esc_html__('Personalizza oggetto e contenuto delle email inviate dopo la richiesta del ticket e prima dell’evento. Lascia vuoto per usare il testo predefinito.', 'unique-coupon-generator') . '</p>';
    if (!empty($placeholder_help)) {
        echo '<ul class="ucg-placeholder-list">';
        foreach ($placeholder_help as $placeholder => $description) {
            echo '<li><code>' . esc_html($placeholder) . '</code> – ' . esc_html($description) . '</li>';
        }
        echo '</ul>';
    }

    echo '<div class="ucg-email-template-wrapper">';
    echo '<div class="ucg-email-template-block">';
    echo '<h4>' . esc_html__('Email di conferma', 'unique-coupon-generator') . '</h4>';
    echo '<label for="email_subject_confirm" class="screen-reader-text">' . esc_html__('Oggetto email di conferma', 'unique-coupon-generator') . '</label>';
    echo '<input type="text" id="email_subject_confirm" name="email_subject_confirm" class="regular-text" value="' . esc_attr($email_subject_confirm) . '" placeholder="' . esc_attr__('Inserisci l\'oggetto', 'unique-coupon-generator') . '">';
    wp_editor($email_body_confirm, 'ucg_email_body_confirm', array(
        'textarea_name' => 'email_body_confirm',
        'textarea_rows' => 8,
        'media_buttons' => false,
        'tinymce' => true,
        'quicktags' => true,
    ));
    echo '</div>';

    echo '<div class="ucg-email-template-block">';
    echo '<h4>' . esc_html__('Email promemoria', 'unique-coupon-generator') . '</h4>';
    echo '<label for="email_subject_reminder" class="screen-reader-text">' . esc_html__('Oggetto email promemoria', 'unique-coupon-generator') . '</label>';
    echo '<input type="text" id="email_subject_reminder" name="email_subject_reminder" class="regular-text" value="' . esc_attr($email_subject_reminder) . '" placeholder="' . esc_attr__('Inserisci l\'oggetto', 'unique-coupon-generator') . '">';
    wp_editor($email_body_reminder, 'ucg_email_body_reminder', array(
        'textarea_name' => 'email_body_reminder',
        'textarea_rows' => 8,
        'media_buttons' => false,
        'tinymce' => true,
        'quicktags' => true,
    ));
    echo '</div>';
    echo '</div>';
    echo '<p class="description">' . esc_html__('Suggerimento: inserisci {qr_code} nel corpo dell’email se vuoi mostrare l’immagine del ticket.', 'unique-coupon-generator') . '</p>';
    echo '</td></tr>';

    echo '<tr><th>' . esc_html__('Mostra contenuti evento', 'unique-coupon-generator') . '</th><td>';
    echo '<label><input type="checkbox" name="mostra_contenuto" value="1" ' . checked($mostra_contenuto, 1, false) . '> ' . esc_html__('Mostra titolo, descrizione e immagine nel front-end', 'unique-coupon-generator') . '</label>';
    echo '</td></tr>';

    echo '<tr><th><label for="stato_evento">' . esc_html__('Stato evento', 'unique-coupon-generator') . '</label></th>';
    echo '<td><select name="stato_evento" id="stato_evento">';
    $stati = array('bozza' => __('Bozza', 'unique-coupon-generator'), 'pubblicato' => __('Pubblicato', 'unique-coupon-generator'), 'chiuso' => __('Chiuso', 'unique-coupon-generator'));
    foreach ($stati as $value => $label) {
        echo '<option value="' . esc_attr($value) . '" ' . selected($stato, $value, false) . '>' . esc_html($label) . '</option>';
    }
    echo '</select></td></tr>';

    echo '</table>';

    submit_button($current_event ? __('Aggiorna evento', 'unique-coupon-generator') : __('Crea evento', 'unique-coupon-generator'));

    echo '</form>';

    echo '<hr>';
    echo '<h2>' . esc_html__('Eventi esistenti', 'unique-coupon-generator') . '</h2>';

    if (empty($events)) {
        echo '<p>' . esc_html__('Non ci sono eventi al momento.', 'unique-coupon-generator') . '</p>';
    } else {
        echo '<table class="widefat striped"><thead><tr>';
        echo '<th>' . esc_html__('ID', 'unique-coupon-generator') . '</th>';
        echo '<th>' . esc_html__('Titolo', 'unique-coupon-generator') . '</th>';
        echo '<th>' . esc_html__('Data', 'unique-coupon-generator') . '</th>';
        echo '<th>' . esc_html__('Stato', 'unique-coupon-generator') . '</th>';
        echo '<th>' . esc_html__('Ticket generati', 'unique-coupon-generator') . '</th>';
        echo '<th>' . esc_html__('Shortcode', 'unique-coupon-generator') . '</th>';
        echo '<th>' . esc_html__('Azioni', 'unique-coupon-generator') . '</th>';
        echo '</tr></thead><tbody>';
        foreach ($events as $event) {
            $count = mms_events_count_tickets($event->id);
            $status_label = $stati[$event->stato] ?? $event->stato;
            echo '<tr>';
            echo '<td>' . esc_html($event->id) . '</td>';
            echo '<td>' . esc_html($event->titolo) . '</td>';
            echo '<td>' . esc_html(date_i18n(get_option('date_format'), strtotime($event->data_evento))) . '</td>';
            echo '<td><span class="ucg-status ucg-status-' . esc_attr($event->stato) . '">' . esc_html($status_label) . '</span></td>';
            echo '<td>' . esc_html($count) . ' / ' . esc_html($event->numero_ticket) . '</td>';
            echo '<td><code>[richiedi_ticket base="' . esc_attr($event->id) . '"]</code></td>';
            $edit_url = mms_events_admin_url($page_slug, $page_tab, array('action' => 'edit', 'event' => $event->id));
            $status_actions = array();
            foreach ($stati as $value => $label) {
                if ($value === $event->stato) {
                    continue;
                }
                $status_redirect = mms_events_admin_url($page_slug, $page_tab, array('action' => 'edit', 'event' => $event->id));
                $status_url = wp_nonce_url(
                    add_query_arg(
                        array(
                            'action'   => 'ucg_change_event_status',
                            'event_id' => $event->id,
                            'new_status' => $value,
                            'redirect' => rawurlencode($status_redirect),
                        ),
                        admin_url('admin-post.php')
                    ),
                    'ucg_change_event_status_' . $event->id
                );
                $status_actions[] = '<a href="' . esc_url($status_url) . '">' . esc_html($label) . '</a>';
            }
            $ticket_url = mms_events_admin_url($tickets_slug, $tickets_tab, array('evento_id' => $event->id));
            $delete_form  = '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" class="ucg-inline-form ucg-event-delete-form">';
            $delete_form .= wp_nonce_field('ucg_delete_event_' . $event->id, '_wpnonce', true, false);
            $delete_form .= '<input type="hidden" name="action" value="ucg_delete_event">';
            $delete_form .= '<input type="hidden" name="event_id" value="' . esc_attr($event->id) . '">';
            $delete_form .= '<input type="hidden" name="redirect" value="' . esc_attr($base_url) . '">';
            $delete_form .= '<button type="submit" class="button-link delete-link" onclick="return confirm(\'' . esc_js(__('Sei sicuro di voler eliminare questo evento?', 'unique-coupon-generator')) . '\');">' . esc_html__('Elimina', 'unique-coupon-generator') . '</button>';
            $delete_form .= '</form>';

            echo '<td><a class="button" href="' . esc_url($edit_url) . '">' . esc_html__('Modifica', 'unique-coupon-generator') . '</a> ';
            echo '<a class="button" href="' . esc_url($ticket_url) . '">' . esc_html__('Ticket', 'unique-coupon-generator') . '</a> ';
            if (!empty($status_actions)) {
                echo implode(' | ', $status_actions);
            }
            echo ' | ' . $delete_form;
            echo '</td>';
            echo '</tr>';
        }
        echo '</tbody></table>';
    }

    echo '</div>';

    if (function_exists('ucg_render_whatsapp_reminder_modal')) {
        echo ucg_render_whatsapp_reminder_modal(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
    }
}

/**
 * Handle status change requests.
 */
function mms_events_handle_change_status() {
    if (!current_user_can('manage_options')) {
        wp_die(__('Permesso negato.', 'unique-coupon-generator'));
    }

    $event_id = isset($_GET['event_id']) ? absint(wp_unslash($_GET['event_id'])) : 0;
    $new_status = isset($_GET['new_status']) ? sanitize_text_field(wp_unslash($_GET['new_status'])) : '';
    $redirect_param = isset($_GET['redirect']) ? wp_unslash($_GET['redirect']) : '';
    $redirect_base  = mms_events_capture_redirect($redirect_param);

    check_admin_referer('ucg_change_event_status_' . $event_id);

    if (!$event_id || !in_array($new_status, array('bozza', 'pubblicato', 'chiuso'), true)) {
        mms_events_add_admin_notice(__('Stato non valido.', 'unique-coupon-generator'), 'error');
        wp_safe_redirect(mms_events_append_event_to_redirect($redirect_base, $event_id));
        exit;
    }

    global $wpdb;
    $table = mms_events_table('events');

    $wpdb->update(
        $table,
        array('stato' => $new_status, 'updated_at' => current_time('mysql')),
        array('id' => $event_id),
        array('%s', '%s'),
        array('%d')
    );

    mms_events_add_admin_notice(__('Stato evento aggiornato.', 'unique-coupon-generator'));
    wp_safe_redirect(mms_events_append_event_to_redirect($redirect_base, $event_id));
    exit;
}

/**
 * Handle ticket deletion requests.
 */
function mms_events_handle_delete_ticket() {
    if (!current_user_can('manage_options')) {
        wp_die(__('Permesso negato.', 'unique-coupon-generator'));
    }

    $ticket_id = isset($_POST['ticket_id']) ? absint(wp_unslash($_POST['ticket_id'])) : 0;
    $redirect_param = isset($_POST['redirect']) ? wp_unslash($_POST['redirect']) : '';
    $redirect_base = mms_events_capture_redirect($redirect_param, 'ucg-eventi-ticket');

    check_admin_referer('ucg_delete_ticket_' . $ticket_id);

    if (!$ticket_id) {
        mms_events_add_admin_notice(__('Ticket non valido.', 'unique-coupon-generator'), 'error');
        wp_safe_redirect($redirect_base);
        exit;
    }

    $deleted = mms_events_delete_ticket($ticket_id);
    if ($deleted) {
        mms_events_add_admin_notice(__('Ticket eliminato correttamente.', 'unique-coupon-generator'));
    } else {
        mms_events_add_admin_notice(__('Impossibile eliminare il ticket selezionato.', 'unique-coupon-generator'), 'error');
    }

    wp_safe_redirect($redirect_base);
    exit;
}

/**
 * Handle event deletion requests.
 */
function mms_events_handle_delete_event() {
    if (!current_user_can('manage_options')) {
        wp_die(__('Permesso negato.', 'unique-coupon-generator'));
    }

    $event_id = isset($_POST['event_id']) ? absint(wp_unslash($_POST['event_id'])) : 0;
    $redirect_param = isset($_POST['redirect']) ? wp_unslash($_POST['redirect']) : '';
    $redirect_base = mms_events_capture_redirect($redirect_param, 'ucg-eventi');

    check_admin_referer('ucg_delete_event_' . $event_id);

    if (!$event_id) {
        mms_events_add_admin_notice(__('Evento non valido.', 'unique-coupon-generator'), 'error');
        wp_safe_redirect($redirect_base);
        exit;
    }

    $deleted = mms_events_delete_event($event_id);
    if ($deleted) {
        mms_events_add_admin_notice(__('Evento eliminato correttamente.', 'unique-coupon-generator'));
    } else {
        mms_events_add_admin_notice(__('Impossibile eliminare l’evento selezionato.', 'unique-coupon-generator'), 'error');
    }

    wp_safe_redirect($redirect_base);
    exit;
}

/**
 * Render the ticket listing page.
 */
function mms_events_render_tickets_page($context = array()) {
    if (!current_user_can('manage_options')) {
        return;
    }

    $defaults = array(
        'embedded'  => false,
        'page_slug' => 'ucg-eventi-ticket',
        'tab'       => '',
    );
    $context = wp_parse_args($context, $defaults);

    $embedded  = !empty($context['embedded']);
    $page_slug = $context['page_slug'];
    $page_tab  = $context['tab'];

    global $wpdb;
    $events_table = mms_events_table('events');
    $tickets_table = mms_events_table('tickets');
    $pr_table = mms_events_table('pr');

    $events = $wpdb->get_results("SELECT id, titolo FROM {$events_table} ORDER BY data_evento DESC");
    $evento_id = isset($_GET['evento_id']) ? absint(wp_unslash($_GET['evento_id'])) : 0;

    if (isset($_GET['export']) && $evento_id) {
        check_admin_referer('ucg_export_tickets_' . $evento_id);
        mms_events_export_tickets_csv($evento_id);
    }

    $base_url = mms_events_admin_url($page_slug, $page_tab);
    $current_list_url = mms_events_admin_url($page_slug, $page_tab, $evento_id ? array('evento_id' => $evento_id) : array());

    if ($embedded) {
        echo '<div class="ucg-events-admin ucg-events-admin--embedded">';
        echo '<h2 class="ucg-section-title">' . esc_html__('Ticket generati', 'unique-coupon-generator') . '</h2>';
    } else {
        echo '<div class="wrap">';
        echo '<h1>' . esc_html__('Ticket generati', 'unique-coupon-generator') . '</h1>';
    }
    mms_events_render_notices();

    echo '<form method="get" class="ucg-filter-form">';
    echo '<input type="hidden" name="page" value="' . esc_attr($page_slug) . '">';
    if ($page_tab !== '') {
        echo '<input type="hidden" name="tab" value="' . esc_attr($page_tab) . '">';
    }
    echo '<label>' . esc_html__('Evento:', 'unique-coupon-generator') . ' <select name="evento_id">';
    echo '<option value="0">' . esc_html__('Tutti gli eventi', 'unique-coupon-generator') . '</option>';
    foreach ($events as $event) {
        echo '<option value="' . esc_attr($event->id) . '" ' . selected($evento_id, $event->id, false) . '>' . esc_html($event->titolo) . '</option>';
    }
    echo '</select></label> ';
    submit_button(__('Filtra', 'unique-coupon-generator'), 'secondary', '', false);
    if ($evento_id) {
        $export_url = mms_events_admin_url($page_slug, $page_tab, array('evento_id' => $evento_id, 'export' => 1));
        $export_url = wp_nonce_url($export_url, 'ucg_export_tickets_' . $evento_id);
        echo ' <a class="button" href="' . esc_url($export_url) . '">' . esc_html__('Esporta CSV', 'unique-coupon-generator') . '</a>';
    }
    echo '</form>';

    $where = '';
    $params = array();
    if ($evento_id) {
        $where = 'WHERE t.evento_id = %d';
        $params[] = $evento_id;
    }

    $sql = "SELECT t.*, e.titolo, pr.nome_pr FROM {$tickets_table} t
            LEFT JOIN {$events_table} e ON e.id = t.evento_id
            LEFT JOIN {$pr_table} pr ON pr.id = t.pr_id
            {$where}
            ORDER BY t.data_creazione DESC LIMIT 500";

    $tickets = $where ? $wpdb->get_results($wpdb->prepare($sql, $params)) : $wpdb->get_results($sql);

    if (empty($tickets)) {
        echo '<p>' . esc_html__('Nessun ticket trovato.', 'unique-coupon-generator') . '</p>';
    } else {
        echo '<table class="widefat striped"><thead><tr>';
        echo '<th>' . esc_html__('ID', 'unique-coupon-generator') . '</th>';
        echo '<th>' . esc_html__('Evento', 'unique-coupon-generator') . '</th>';
        echo '<th>' . esc_html__('Cliente', 'unique-coupon-generator') . '</th>';
        echo '<th>' . esc_html__('Email', 'unique-coupon-generator') . '</th>';
        echo '<th>' . esc_html__('Telefono', 'unique-coupon-generator') . '</th>';
        echo '<th>' . esc_html__('Tipo ticket', 'unique-coupon-generator') . '</th>';
        echo '<th>' . esc_html__('Prezzo', 'unique-coupon-generator') . '</th>';
        echo '<th>' . esc_html__('Stato', 'unique-coupon-generator') . '</th>';
        echo '<th>' . esc_html__('PR', 'unique-coupon-generator') . '</th>';
        echo '<th>' . esc_html__('Ticket code', 'unique-coupon-generator') . '</th>';
        echo '<th>' . esc_html__('QR code', 'unique-coupon-generator') . '</th>';
        echo '<th>' . esc_html__('Azioni', 'unique-coupon-generator') . '</th>';
        echo '</tr></thead><tbody>';
        foreach ($tickets as $ticket) {
            echo '<tr>';
            echo '<td>' . esc_html($ticket->id) . '</td>';
            echo '<td>' . esc_html($ticket->titolo) . '</td>';
            echo '<td>' . esc_html($ticket->utente_nome) . '</td>';
            echo '<td><a href="mailto:' . esc_attr($ticket->utente_email) . '">' . esc_html($ticket->utente_email) . '</a></td>';
            echo '<td>' . esc_html($ticket->utente_telefono) . '</td>';
            echo '<td>' . esc_html($ticket->tipo_ticket) . '</td>';
            $price_display = function_exists('wc_price') ? wc_price($ticket->prezzo) : number_format_i18n($ticket->prezzo, 2);
            echo '<td>' . wp_kses_post($price_display) . '</td>';
            echo '<td>' . esc_html($ticket->stato) . '</td>';
            echo '<td>' . esc_html($ticket->nome_pr ?? '-') . '</td>';
            echo '<td><code>' . esc_html($ticket->ticket_code) . '</code></td>';
            if ($ticket->qr_code) {
                echo '<td><a href="' . esc_url($ticket->qr_code) . '" target="_blank">' . esc_html__('Scarica', 'unique-coupon-generator') . '</a></td>';
            } else {
                echo '<td>-</td>';
            }
            $delete_form  = '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" class="ucg-inline-form ucg-ticket-delete-form">';
            $delete_form .= wp_nonce_field('ucg_delete_ticket_' . $ticket->id, '_wpnonce', true, false);
            $delete_form .= '<input type="hidden" name="action" value="ucg_delete_ticket">';
            $delete_form .= '<input type="hidden" name="ticket_id" value="' . esc_attr($ticket->id) . '">';
            $delete_form .= '<input type="hidden" name="redirect" value="' . esc_attr($current_list_url) . '">';
            $delete_form .= '<button type="submit" class="button-link delete-link" onclick="return confirm(\'' . esc_js(__('Sei sicuro di voler eliminare questo ticket?', 'unique-coupon-generator')) . '\');">' . esc_html__('Elimina', 'unique-coupon-generator') . '</button>';
            $delete_form .= '</form>';
            echo '<td>' . $delete_form . '</td>';
            echo '</tr>';
        }
        echo '</tbody></table>';
    }

    echo '</div>';
}

/**
 * Export tickets in CSV format.
 */
function mms_events_export_tickets_csv($evento_id) {
    if (!current_user_can('manage_options')) {
        return;
    }

    global $wpdb;
    $events_table = mms_events_table('events');
    $tickets_table = mms_events_table('tickets');
    $pr_table = mms_events_table('pr');

    $sql = "SELECT t.*, e.titolo, pr.nome_pr FROM {$tickets_table} t
            LEFT JOIN {$events_table} e ON e.id = t.evento_id
            LEFT JOIN {$pr_table} pr ON pr.id = t.pr_id
            WHERE t.evento_id = %d ORDER BY t.data_creazione DESC";

    $tickets = $wpdb->get_results($wpdb->prepare($sql, $evento_id));

    if (headers_sent()) {
        return;
    }

    nocache_headers();
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="ticket-evento-' . $evento_id . '.csv"');

    $output = fopen('php://output', 'w');
    fputcsv($output, array('ID', 'Evento', 'Cliente', 'Email', 'Telefono', 'Tipo ticket', 'Prezzo', 'Stato', 'PR', 'Ticket code', 'QR code', 'Data creazione'));

    foreach ($tickets as $ticket) {
        fputcsv($output, array(
            $ticket->id,
            $ticket->titolo,
            $ticket->utente_nome,
            $ticket->utente_email,
            $ticket->utente_telefono,
            $ticket->tipo_ticket,
            $ticket->prezzo,
            $ticket->stato,
            $ticket->nome_pr,
            $ticket->ticket_code,
            $ticket->qr_code,
            $ticket->data_creazione,
        ));
    }

    fclose($output);
    exit;
}

/**
 * Render the PR report page.
 */
function mms_events_render_pr_report_page($context = array()) {
    if (!mms_events_user_can_manage_pr_reports()) {
        return;
    }

    $defaults = array(
        'embedded'  => false,
        'page_slug' => 'ucg-eventi-report-pr',
        'tab'       => '',
    );
    $context = wp_parse_args($context, $defaults);

    $embedded  = !empty($context['embedded']);
    $page_slug = $context['page_slug'];
    $page_tab  = $context['tab'];

    global $wpdb;
    $events_table = mms_events_table('events');
    $pr_table = mms_events_table('pr');

    $events = $wpdb->get_results("SELECT id, titolo, gestione_pr FROM {$events_table} ORDER BY data_evento DESC");
    $evento_id = isset($_GET['evento_id']) ? absint(wp_unslash($_GET['evento_id'])) : 0;

    if (isset($_GET['export']) && $evento_id) {
        check_admin_referer('ucg_export_pr_' . $evento_id);
        mms_events_export_pr_report_csv($evento_id);
    }

    $base_url = mms_events_admin_url($page_slug, $page_tab);

    if ($embedded) {
        echo '<div class="ucg-events-admin ucg-events-admin--embedded">';
        echo '<h2 class="ucg-section-title">' . esc_html__('Report PR', 'unique-coupon-generator') . '</h2>';
    } else {
        echo '<div class="wrap">';
        echo '<h1>' . esc_html__('Report PR', 'unique-coupon-generator') . '</h1>';
    }
    mms_events_render_notices();

    echo '<form method="get" class="ucg-filter-form">';
    echo '<input type="hidden" name="page" value="' . esc_attr($page_slug) . '">';
    if ($page_tab !== '') {
        echo '<input type="hidden" name="tab" value="' . esc_attr($page_tab) . '">';
    }
    echo '<label>' . esc_html__('Evento:', 'unique-coupon-generator') . ' <select name="evento_id">';
    echo '<option value="0">' . esc_html__('Seleziona evento', 'unique-coupon-generator') . '</option>';
    foreach ($events as $event) {
        echo '<option value="' . esc_attr($event->id) . '" ' . selected($evento_id, $event->id, false) . '>' . esc_html($event->titolo) . '</option>';
    }
    echo '</select></label> ';
    submit_button(__('Filtra', 'unique-coupon-generator'), 'secondary', '', false);
    if ($evento_id) {
        $export_url = mms_events_admin_url($page_slug, $page_tab, array('evento_id' => $evento_id, 'export' => 1));
        $export_url = wp_nonce_url($export_url, 'ucg_export_pr_' . $evento_id);
        echo ' <a class="button" href="' . esc_url($export_url) . '">' . esc_html__('Esporta CSV', 'unique-coupon-generator') . '</a>';
    }
    echo '</form>';

    if (!$evento_id) {
        echo '<p>' . esc_html__('Seleziona un evento per visualizzare il report.', 'unique-coupon-generator') . '</p>';
        echo '</div>';
        return;
    }

    $event = mms_events_get_event($evento_id);
    if (!$event || !$event->gestione_pr) {
        echo '<p>' . esc_html__('La gestione PR non è attiva per questo evento.', 'unique-coupon-generator') . '</p>';
        echo '</div>';
        return;
    }

    $prs = mms_events_get_pr_list($evento_id);
    if (empty($prs)) {
        echo '<p>' . esc_html__('Nessun PR configurato per questo evento.', 'unique-coupon-generator') . '</p>';
        echo '</div>';
        return;
    }

    $counts = array();
    $max_count = 0;
    foreach ($prs as $pr) {
        $count = mms_events_count_tickets_by_pr($evento_id, $pr->id);
        $counts[$pr->id] = $count;
        if ($count > $max_count) {
            $max_count = $count;
        }
    }

    echo '<table class="widefat striped ucg-pr-report"><thead><tr>';
    echo '<th>' . esc_html__('Nome PR', 'unique-coupon-generator') . '</th>';
    echo '<th>' . esc_html__('Ticket assegnati', 'unique-coupon-generator') . '</th>';
    echo '<th>' . esc_html__('Limite', 'unique-coupon-generator') . '</th>';
    echo '</tr></thead><tbody>';

    foreach ($prs as $pr) {
        $count = $counts[$pr->id] ?? 0;
        $class = $count === $max_count && $count > 0 ? ' class="ucg-pr-top"' : '';
        echo '<tr' . $class . '>';
        echo '<td>' . esc_html($pr->nome_pr) . '</td>';
        echo '<td>' . esc_html($count) . '</td>';
        echo '<td>' . esc_html($pr->max_ticket) . '</td>';
        echo '</tr>';
    }

    echo '</tbody></table>';
    echo '</div>';
}

/**
 * Export PR report in CSV.
 */
function mms_events_export_pr_report_csv($evento_id) {
    if (!mms_events_user_can_manage_pr_reports()) {
        return;
    }

    $event = mms_events_get_event($evento_id);
    if (!$event) {
        return;
    }

    $prs = mms_events_get_pr_list($evento_id);

    if (headers_sent()) {
        return;
    }

    nocache_headers();
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="report-pr-evento-' . $evento_id . '.csv"');

    $output = fopen('php://output', 'w');
    fputcsv($output, array('PR', 'Ticket assegnati', 'Limite'));

    foreach ($prs as $pr) {
        $count = mms_events_count_tickets_by_pr($evento_id, $pr->id);
        fputcsv($output, array($pr->nome_pr, $count, $pr->max_ticket));
    }

    fclose($output);
    exit;
}

function ucg_render_tab_events_manage($context = array()) {
    $defaults = array(
        'page_slug'    => 'ucg-admin-events',
        'tab'          => 'manage',
        'tickets_slug' => 'ucg-admin-events',
        'tickets_tab'  => 'tickets',
        'report_slug'  => 'ucg-admin-events',
        'report_tab'   => 'reports',
    );
    $context = wp_parse_args($context, $defaults);
    $context['embedded'] = true;

    mms_events_render_admin_page($context);
}

function ucg_render_tab_events_tickets($context = array()) {
    $defaults = array(
        'page_slug' => 'ucg-admin-events',
        'tab'       => 'tickets',
    );
    $context = wp_parse_args($context, $defaults);
    $context['embedded'] = true;

    mms_events_render_tickets_page($context);
}

function ucg_render_tab_events_pr($context = array()) {
    $defaults = array(
        'page_slug' => 'ucg-admin-events',
        'tab'       => 'reports',
    );
    $context = wp_parse_args($context, $defaults);
    $context['embedded'] = true;

    mms_events_render_pr_report_page($context);
}

function ucg_render_tab_events_pages($context = array()) {
    if (!function_exists('mms_events_table')) {
        echo '<p>' . esc_html__('La gestione eventi non è disponibile.', 'unique-coupon-generator') . '</p>';
        return;
    }

    global $wpdb;
    $defaults = array(
        'page_slug'    => 'ucg-admin-events',
        'tab'          => 'pages',
        'manage_slug'  => 'ucg-admin-events',
        'manage_tab'   => 'manage',
    );
    $context = wp_parse_args($context, $defaults);

    $events_table = mms_events_table('events');
    if (!$events_table) {
        echo '<p>' . esc_html__('Nessun evento disponibile.', 'unique-coupon-generator') . '</p>';
        return;
    }

    $events = $wpdb->get_results("SELECT id, titolo, page_id, data_evento, stato FROM {$events_table} ORDER BY data_evento DESC, id DESC");

    echo '<section class="ucg-card ucg-card--table">';
    echo '<h2><span class="dashicons dashicons-calendar" aria-hidden="true"></span> ' . esc_html__('Shortcode eventi', 'unique-coupon-generator') . '</h2>';
    echo '<p class="ucg-card__intro">' . esc_html__('Crea o modifica le pagine pubbliche dedicate alla vendita, verifica e gestione dei ticket evento.', 'unique-coupon-generator') . '</p>';
    echo '<table class="widefat fixed striped">';
    echo '<thead><tr><th>' . esc_html__('Shortcode', 'unique-coupon-generator') . '</th><th>' . esc_html__('Descrizione', 'unique-coupon-generator') . '</th><th>' . esc_html__('Azioni', 'unique-coupon-generator') . '</th></tr></thead><tbody>';

    if (!empty($events)) {
        foreach ($events as $event) {
            $shortcode = '[richiedi_ticket base="' . $event->id . '"]';
            $slug = sanitize_title('evento-' . $event->id);
            $page = !empty($event->page_id) ? get_post($event->page_id) : null;
            $description = sprintf(__('Modulo richiesta ticket per l\'evento "%s"', 'unique-coupon-generator'), $event->titolo);

            echo '<tr>';
            echo '<td><code>' . esc_html($shortcode) . '</code></td>';
            echo '<td>' . esc_html($description) . '</td>';
            echo '<td>';

            if ($page && 'trash' !== get_post_status($page)) {
                $edit_link = get_edit_post_link($page->ID, '');
                $view_link = get_permalink($page);
                if ($edit_link) {
                    echo '<a class="button button-secondary" href="' . esc_url($edit_link) . '">' . esc_html__('Modifica pagina evento', 'unique-coupon-generator') . '</a> ';
                }
                if ($view_link) {
                    echo '<a class="button" href="' . esc_url($view_link) . '" target="_blank" rel="noopener noreferrer">' . esc_html__('APRI LINK', 'unique-coupon-generator') . '</a>';
                }
            } else {
                $create_url = ucg_get_shortcode_page_creation_url($slug, $shortcode, $event->titolo);
                echo '<a class="button button-primary" href="' . esc_url($create_url) . '">' . esc_html__('Crea pagina evento', 'unique-coupon-generator') . '</a>';
            }

            echo '</td>';
            echo '</tr>';
        }
    } else {
        echo '<tr><td colspan="3">' . esc_html__('Non sono presenti eventi.', 'unique-coupon-generator') . '</td></tr>';
    }

    $events_grid_shortcode = '[ucg_eventi_attivi]';
    $events_grid_desc = __('Griglia degli eventi attivi', 'unique-coupon-generator');
    $events_grid_slug = 'eventi-attivi';
    $events_grid_page = get_page_by_path($events_grid_slug);
    echo '<tr><td><code>' . esc_html($events_grid_shortcode) . '</code></td><td>' . esc_html($events_grid_desc) . '</td><td>';
    if ($events_grid_page) {
        $edit_link = get_edit_post_link($events_grid_page->ID, '');
        $view_link = get_permalink($events_grid_page);
        if ($edit_link) {
            echo '<a class="button button-secondary" href="' . esc_url($edit_link) . '">' . esc_html__('Modifica pagina', 'unique-coupon-generator') . '</a> ';
        }
        if ($view_link) {
            echo '<a class="button" href="' . esc_url($view_link) . '" target="_blank" rel="noopener noreferrer">' . esc_html__('APRI LINK', 'unique-coupon-generator') . '</a>';
        }
    } else {
        $events_grid_url = ucg_get_shortcode_page_creation_url($events_grid_slug, $events_grid_shortcode, $events_grid_slug);
        echo '<a class="button button-primary" href="' . esc_url($events_grid_url) . '">' . esc_html__('Crea pagina', 'unique-coupon-generator') . '</a>';
    }
    echo '</td></tr>';

    $verify_shortcode = '[verifica_ticket]';
    $verify_desc = __('Pagina per la verifica e il check-in dei ticket', 'unique-coupon-generator');
    $verify_slug = 'verifica-ticket';
    $verify_page = get_page_by_path($verify_slug);
    echo '<tr><td><code>' . esc_html($verify_shortcode) . '</code></td><td>' . esc_html($verify_desc) . '</td><td>';
    if ($verify_page) {
        $edit_link = get_edit_post_link($verify_page->ID, '');
        $view_link = get_permalink($verify_page);
        if ($edit_link) {
            echo '<a class="button button-secondary" href="' . esc_url($edit_link) . '">' . esc_html__('Modifica pagina', 'unique-coupon-generator') . '</a> ';
        }
        if ($view_link) {
            echo '<a class="button" href="' . esc_url($view_link) . '" target="_blank" rel="noopener noreferrer">' . esc_html__('APRI LINK', 'unique-coupon-generator') . '</a>';
        }
    } else {
        $verify_title = __('Verifica Ticket', 'unique-coupon-generator');
        $verify_url = ucg_get_shortcode_page_creation_url($verify_slug, $verify_shortcode, $verify_title);
        echo '<a class="button button-primary" href="' . esc_url($verify_url) . '">' . esc_html__('Crea pagina', 'unique-coupon-generator') . '</a>';
    }
    echo '</td></tr>';

    $pr_shortcode = '[ticket_pr]';
    $pr_desc = __('Area per i PR dedicata al pagamento dei ticket', 'unique-coupon-generator');
    $pr_slug = 'ticket-pr';
    $pr_page = get_page_by_path($pr_slug);
    echo '<tr><td><code>' . esc_html($pr_shortcode) . '</code></td><td>' . esc_html($pr_desc) . '</td><td>';
    if ($pr_page) {
        $edit_link = get_edit_post_link($pr_page->ID, '');
        $view_link = get_permalink($pr_page);
        if ($edit_link) {
            echo '<a class="button button-secondary" href="' . esc_url($edit_link) . '">' . esc_html__('Modifica pagina', 'unique-coupon-generator') . '</a> ';
        }
        if ($view_link) {
            echo '<a class="button" href="' . esc_url($view_link) . '" target="_blank" rel="noopener noreferrer">' . esc_html__('APRI LINK', 'unique-coupon-generator') . '</a>';
        }
    } else {
        $pr_title = __('Ticket PR', 'unique-coupon-generator');
        $pr_url = ucg_get_shortcode_page_creation_url($pr_slug, $pr_shortcode, $pr_title);
        echo '<a class="button button-primary" href="' . esc_url($pr_url) . '">' . esc_html__('Crea pagina', 'unique-coupon-generator') . '</a>';
    }
    echo '</td></tr>';

    echo '</tbody></table>';
    echo '</section>';

    echo '<section class="ucg-card ucg-card--table">';
    echo '<h2><span class="dashicons dashicons-admin-page" aria-hidden="true"></span> ' . esc_html__('Pagine evento generate', 'unique-coupon-generator') . '</h2>';
    echo '<p class="ucg-card__intro">' . esc_html__('Consulta lo stato delle pagine pubbliche create per gli eventi e accedi rapidamente alle azioni principali.', 'unique-coupon-generator') . '</p>';
    echo '<table class="widefat fixed striped">';
    echo '<thead><tr><th>' . esc_html__('Evento', 'unique-coupon-generator') . '</th><th>' . esc_html__('Shortcode', 'unique-coupon-generator') . '</th><th>' . esc_html__('Pagina generata', 'unique-coupon-generator') . '</th><th>' . esc_html__('Azioni', 'unique-coupon-generator') . '</th></tr></thead><tbody>';

    if (empty($events)) {
        echo '<tr><td colspan="4">' . esc_html__('Non sono presenti eventi configurati.', 'unique-coupon-generator') . '</td></tr>';
    } else {
        foreach ($events as $event) {
            $shortcode = '[richiedi_ticket base="' . $event->id . '"]';
            $page = $event->page_id ? get_post($event->page_id) : null;
            $page_label = $page ? get_the_title($page) : __('Pagina non generata', 'unique-coupon-generator');
            $manage_url = mms_events_admin_url($context['manage_slug'], $context['manage_tab'], array('action' => 'edit', 'event' => $event->id));

            echo '<tr>';
            echo '<td>' . esc_html($event->titolo) . '</td>';
            echo '<td><code>' . esc_html($shortcode) . '</code></td>';

            if ($page && 'trash' !== get_post_status($page)) {
                $view_link = get_permalink($page);
                $edit_link = get_edit_post_link($page->ID, '');
                echo '<td>' . esc_html($page_label) . '</td>';
                echo '<td>';
                if ($edit_link) {
                    echo '<a class="button button-secondary" href="' . esc_url($edit_link) . '">' . esc_html__('Modifica pagina', 'unique-coupon-generator') . '</a> ';
                }
                if ($view_link) {
                    echo '<a class="button" href="' . esc_url($view_link) . '" target="_blank" rel="noopener noreferrer">' . esc_html__('APRI LINK', 'unique-coupon-generator') . '</a> ';
                }
                echo '<a class="button" href="' . esc_url($manage_url) . '">' . esc_html__('Modifica evento', 'unique-coupon-generator') . '</a>';
                echo '</td>';
            } else {
                $slug = sanitize_title('evento-' . $event->id);
                $create_url = ucg_get_shortcode_page_creation_url($slug, $shortcode, $event->titolo);
                echo '<td>' . esc_html__('Pagina non generata', 'unique-coupon-generator') . '</td>';
                echo '<td>';
                echo '<a class="button button-primary" href="' . esc_url($create_url) . '">' . esc_html__('Crea pagina evento', 'unique-coupon-generator') . '</a> ';
                echo '<a class="button" href="' . esc_url($manage_url) . '">' . esc_html__('Modifica evento', 'unique-coupon-generator') . '</a>';
                echo '</td>';
            }

            echo '</tr>';
        }
    }

    echo '</tbody></table>';
    echo '</section>';
}

function ucg_render_tab_events_verify($context = array()) {
    if (!current_user_can('manage_options')) {
        return;
    }

    $defaults = array(
        'page_slug' => 'ucg-admin-events',
        'tab'       => 'verify',
    );
    $context = wp_parse_args($context, $defaults);

    $action       = '';
    $code         = '';
    $ticket       = null;
    $event        = null;
    $notice       = '';
    $notice_class = '';

    if (!empty($_POST['ucg_admin_ticket_action'])) {
        $action = sanitize_text_field(wp_unslash($_POST['ucg_admin_ticket_action']));
        $code   = sanitize_text_field(wp_unslash($_POST['ticket_code'] ?? ''));

        if (empty($_POST['ucg_admin_ticket_nonce']) || !wp_verify_nonce(wp_unslash($_POST['ucg_admin_ticket_nonce']), 'ucg_admin_ticket_action')) {
            $notice       = __('Operazione non valida. Ricarica la pagina e riprova.', 'unique-coupon-generator');
            $notice_class = 'error';
        } elseif ($code === '') {
            $notice       = __('Inserisci un codice ticket valido.', 'unique-coupon-generator');
            $notice_class = 'error';
        } else {
            $ticket = mms_events_get_ticket_by_code($code);
            if (!$ticket) {
                $notice       = __('Ticket non trovato.', 'unique-coupon-generator');
                $notice_class = 'error';
            } else {
                $current_status = strtolower($ticket->stato ?? '');
                if ($action === 'mark_paid') {
                    if ($current_status === 'pagato') {
                        $notice       = __('Il ticket risulta già pagato.', 'unique-coupon-generator');
                        $notice_class = 'warning';
                    } elseif ($current_status === 'usato') {
                        $notice       = __('Il ticket è già stato utilizzato: impossibile modificarne il pagamento.', 'unique-coupon-generator');
                        $notice_class = 'warning';
                    } else {
                        mms_events_update_ticket_status($ticket->id, 'pagato');
                        $notice       = __('Lo stato di pagamento del ticket è stato aggiornato con successo - Ticket PAGATO', 'unique-coupon-generator');
                        $notice_class = 'success';
                        $ticket       = mms_events_get_ticket_by_code($code);
                    }
                } elseif ($action === 'mark_used') {
                    if ($current_status === 'usato') {
                        $code_label   = sanitize_text_field($ticket->ticket_code);
                        $notice       = sprintf(__('Il ticket %s risulta già utilizzato.', 'unique-coupon-generator'), $code_label);
                        $notice_class = 'warning';
                    } else {
                        mms_events_update_ticket_status($ticket->id, 'usato');
                        $event        = mms_events_get_event($ticket->evento_id);
                        $event_title  = $event ? sanitize_text_field($event->titolo) : '';
                        $code_label   = sanitize_text_field($ticket->ticket_code);
                        $notice       = sprintf(__('Ticket %1$s segnato come USATO per %2$s.', 'unique-coupon-generator'), $code_label, $event_title);
                        $notice_class = 'success';
                        $ticket       = mms_events_get_ticket_by_code($code);
                    }
                } else {
                    $notice       = __('Ticket trovato! Controlla i dettagli qui sotto.', 'unique-coupon-generator');
                    $notice_class = 'success';
                }
            }
        }
    }

    if ($ticket && !$event) {
        $event = mms_events_get_event($ticket->evento_id);
    }

    if (!$ticket && $code !== '' && empty($action)) {
        $ticket = mms_events_get_ticket_by_code($code);
        if ($ticket) {
            $event = mms_events_get_event($ticket->evento_id);
        }
    }

    $html5_qr_path    = UCG_PLUGIN_DIR . 'assets/js/html5-qrcode.min.js';
    $html5_qr_version = file_exists($html5_qr_path) ? filemtime($html5_qr_path) : UCG_VERSION;
    wp_enqueue_script('html5-qrcode', UCG_PLUGIN_URL . 'assets/js/html5-qrcode.min.js', array(), $html5_qr_version, true);

    echo '<section class="ucg-card">';
    echo '<h2><span class="dashicons dashicons-yes-alt" aria-hidden="true"></span> ' . esc_html__('Verifica ticket evento', 'unique-coupon-generator') . '</h2>';
    echo '<p class="ucg-card__intro">' . esc_html__('Inserisci il codice o utilizza lo scanner QR per verificare, segnare come pagato o validare un ticket.', 'unique-coupon-generator') . '</p>';

    if ($notice) {
        $class_map = array(
            'success' => 'notice notice-success',
            'warning' => 'notice notice-warning',
            'error'   => 'notice notice-error',
            'info'    => 'notice notice-info',
        );
        $class = isset($class_map[$notice_class]) ? $class_map[$notice_class] : $class_map['info'];
        echo '<div class="' . esc_attr($class) . '"><p>' . esc_html($notice) . '</p></div>';
    }

    echo '<form method="post" id="ucg-admin-ticket-form" class="ucg-ticket-verify-form">';
    wp_nonce_field('ucg_admin_ticket_action', 'ucg_admin_ticket_nonce');
    echo '<input type="hidden" name="ucg_admin_ticket_action" value="lookup">';
    echo '<p><label for="ucg-admin-ticket-code" class="screen-reader-text">' . esc_html__('Codice ticket', 'unique-coupon-generator') . '</label>';
    echo '<input type="text" id="ucg-admin-ticket-code" name="ticket_code" value="' . esc_attr($code) . '" class="regular-text" placeholder="' . esc_attr__('Scansiona o inserisci il codice', 'unique-coupon-generator') . '"></p>';
    echo '<p><button type="submit" class="button button-secondary">' . esc_html__('Verifica ticket', 'unique-coupon-generator') . '</button></p>';
    echo '</form>';

    echo '<div id="ucg-admin-ticket-qr" class="ucg-ticket-qr"></div>';

    if ($ticket) {
        $price_display = '';
        if (isset($ticket->prezzo)) {
            $price_display = function_exists('wc_price') ? wc_price($ticket->prezzo) : number_format_i18n((float) $ticket->prezzo, 2);
        }

        echo '<div class="ucg-ticket-summary">';
        echo '<h3>' . esc_html__('Dettagli ticket', 'unique-coupon-generator') . '</h3>';
        echo '<ul>';
        echo '<li><strong>' . esc_html__('Codice:', 'unique-coupon-generator') . '</strong> <span class="ucg-ticket-code-value">' . esc_html($ticket->ticket_code) . '</span></li>';
        if ($event) {
            echo '<li><strong>' . esc_html__('Evento:', 'unique-coupon-generator') . '</strong> ' . esc_html($event->titolo) . '</li>';
        }
        echo '<li><strong>' . esc_html__('Cliente:', 'unique-coupon-generator') . '</strong> ' . esc_html($ticket->utente_nome) . '</li>';
        if (!empty($ticket->utente_email)) {
            echo '<li><strong>' . esc_html__('Email:', 'unique-coupon-generator') . '</strong> <a href="mailto:' . esc_attr($ticket->utente_email) . '">' . esc_html($ticket->utente_email) . '</a></li>';
        }
        if (!empty($ticket->utente_telefono)) {
            echo '<li><strong>' . esc_html__('Telefono:', 'unique-coupon-generator') . '</strong> ' . esc_html($ticket->utente_telefono) . '</li>';
        }
        if (!empty($ticket->tipo_ticket)) {
            echo '<li><strong>' . esc_html__('Tipo ticket:', 'unique-coupon-generator') . '</strong> ' . esc_html($ticket->tipo_ticket) . '</li>';
        }
        if ($price_display !== '') {
            echo '<li><strong>' . esc_html__('Prezzo:', 'unique-coupon-generator') . '</strong> ' . wp_kses_post($price_display) . '</li>';
        }
        echo '<li><strong>' . esc_html__('Stato:', 'unique-coupon-generator') . '</strong> ' . esc_html($ticket->stato) . '</li>';
        echo '</ul>';

        echo '<div class="ucg-ticket-action-buttons">';

        echo '<form method="post" class="ucg-ticket-inline-form">';
        wp_nonce_field('ucg_admin_ticket_action', 'ucg_admin_ticket_nonce');
        echo '<input type="hidden" name="ucg_admin_ticket_action" value="mark_paid">';
        echo '<input type="hidden" name="ticket_code" value="' . esc_attr($ticket->ticket_code) . '">';
        echo '<button type="submit" class="button button-primary">' . esc_html__('Segna come PAGATO', 'unique-coupon-generator') . '</button>';
        echo '</form>';

        echo '<form method="post" class="ucg-ticket-inline-form">';
        wp_nonce_field('ucg_admin_ticket_action', 'ucg_admin_ticket_nonce');
        echo '<input type="hidden" name="ucg_admin_ticket_action" value="mark_used">';
        echo '<input type="hidden" name="ticket_code" value="' . esc_attr($ticket->ticket_code) . '">';
        echo '<button type="submit" class="button">' . esc_html__('Segna come UTILIZZATO', 'unique-coupon-generator') . '</button>';
        echo '</form>';

        echo '</div>';
        echo '</div>';
    }

    echo '</section>';

    $inline = "(function(){\n        if (typeof Html5QrcodeScanner === 'undefined') { return; }\n        var container = document.getElementById('ucg-admin-ticket-qr');\n        if (!container) { return; }\n        var scanner = new Html5QrcodeScanner('ucg-admin-ticket-qr', { fps: 10, qrbox: 220 });\n        scanner.render(function(decodedText){\n            var code = decodedText;\n            try {\n                var url = new URL(decodedText);\n                var param = url.searchParams.get('ticket_code');\n                if (param) { code = param; }\n            } catch (e) {}\n            var input = document.getElementById('ucg-admin-ticket-code');\n            if (input) {\n                input.value = code;\n            }\n            var form = document.getElementById('ucg-admin-ticket-form');\n            if (form) {\n                form.submit();\n            }\n        });\n    })();";
    wp_add_inline_script('html5-qrcode', $inline);
}

<?php

/**
 * Author: Meteora Web <https://meteoraweb.com>
 */


if (!defined('ABSPATH')) {
    exit;
}

/**
 * Render the tab dedicated to coupon set management.
 *
 * @param array $context Rendering context.
 */
function ucg_render_tab_coupon_sets($context = array()) {
    $modal_markup = '';
    $privacy_default_page = (int) get_option('wp_page_for_privacy_policy');
    $notice = array();

    if (function_exists('wp_enqueue_media')) {
        wp_enqueue_media();
    }

    $coupon_sets = ucg_get_coupon_sets();
    $editing_set_key = '';

    if (isset($_GET['edit_set'])) {
        $candidate = sanitize_text_field(wp_unslash($_GET['edit_set']));
        if (isset($coupon_sets[$candidate])) {
            $editing_set_key = $candidate;
        }
    }

    $show_whatsapp_checked    = true;
    $allow_png_checked        = true;
    $allow_pdf_checked        = true;
    $coupon_set_whatsapp_value = '';

    if (isset($_POST['save_options'])) {
        $show_whatsapp_checked = !empty($_POST['show_whatsapp_opt_in']);
        $allow_png_checked     = !empty($_POST['allow_png_download']);
        $allow_pdf_checked     = !empty($_POST['allow_pdf_download']);
        check_admin_referer('ucc_save_options_nonce');

        $coupon_set_whatsapp_value = isset($_POST['coupon_set_whatsapp_message'])
            ? ucg_sanitize_whatsapp_message(wp_unslash($_POST['coupon_set_whatsapp_message']))
            : '';

        $discount_type_input = sanitize_text_field(wp_unslash($_POST['ucc_coupon_discount_type'] ?? 'fixed_cart'));
        $coupon_amount_input = sanitize_text_field(wp_unslash($_POST['ucc_coupon_amount'] ?? ''));

        update_option('ucc_coupon_discount_type', $discount_type_input);
        update_option('ucc_coupon_amount', $coupon_amount_input);

        if (!empty($_POST['base_coupon_code'])) {
            $base_coupon_code = sanitize_text_field(wp_unslash($_POST['base_coupon_code']));
            $expiry_days       = isset($_POST['expiry_days']) ? max(0, intval($_POST['expiry_days'])) : 0;
            $fixed_expiry_date = sanitize_text_field(wp_unslash($_POST['fixed_expiry_date'] ?? ''));
            $use_expiry_days   = !empty($_POST['use_expiry_days']);
            $use_fixed_expiry  = !empty($_POST['use_fixed_expiry']);
            if ($use_fixed_expiry) {
                $use_expiry_days = false;
                if (empty($fixed_expiry_date)) {
                    $use_fixed_expiry = false;
                }
            }
            $thank_you_page    = intval($_POST['thank_you_page'] ?? 0);
            $create_verify     = !empty($_POST['create_verify_page']);
            $create_thank_you  = !empty($_POST['create_thank_you_page']);

            $default_fields  = array('first_name', 'last_name', 'email', 'phone');
            $selected_fields = isset($_POST['fields_required'])
                ? array_map('sanitize_text_field', (array) wp_unslash($_POST['fields_required']))
                : $default_fields;
            $allowed_fields  = array('first_name', 'last_name', 'email', 'phone', 'birth_date', 'city');
            $fields_required = array_values(array_intersect($selected_fields, $allowed_fields));

            $enable_fidelity = !empty($_POST['enable_fidelity']);
            $points_per_euro = isset($_POST['points_per_euro']) ? max(1, intval($_POST['points_per_euro'])) : 1;
            $signup_points   = isset($_POST['signup_points']) ? max(0, intval($_POST['signup_points'])) : 0;

            $custom_field_label = sanitize_text_field(wp_unslash($_POST['custom_field_label'] ?? ''));
            $custom_field_key   = sanitize_key(wp_unslash($_POST['custom_field_key'] ?? ''));
            if ($custom_field_label && $custom_field_key) {
                $fields_required[] = $custom_field_key;
            }

            $privacy_required = !empty($_POST['require_privacy']);
            $privacy_page_id  = intval($_POST['privacy_policy_page'] ?? 0);
            if (!$privacy_page_id && $privacy_default_page) {
                $privacy_page_id = $privacy_default_page;
            }

            $fields_required = array_values(array_unique($fields_required));

            update_option('ucc_coupon_expiry_days_' . $base_coupon_code, $expiry_days);
            update_option('ucc_coupon_fixed_expiry_' . $base_coupon_code, $fixed_expiry_date);
            update_option('ucc_coupon_use_relative_' . $base_coupon_code, $use_expiry_days ? 'yes' : 'no');
            update_option('ucc_coupon_use_fixed_' . $base_coupon_code, $use_fixed_expiry ? 'yes' : 'no');
            update_option('ucc_coupon_thank_you_page_' . $base_coupon_code, $thank_you_page);

            $editing_set_key = $base_coupon_code;
            $set_created = !isset($coupon_sets[$base_coupon_code]);

            if ($set_created) {
                $coupon_sets[$base_coupon_code] = array(
                    'name'      => $base_coupon_code,
                    'shortcode' => '[richiedi_coupon base="' . $base_coupon_code . '"]',
                );

                $page_id = wp_insert_post(array(
                    'post_title'   => sprintf(__('Richiedi %s', 'unique-coupon-generator'), $base_coupon_code),
                    'post_content' => '[richiedi_coupon base="' . $base_coupon_code . '"]',
                    'post_status'  => 'publish',
                    'post_type'    => 'page',
                ));

                if (!is_wp_error($page_id)) {
                    $coupon_sets[$base_coupon_code]['page_id'] = (int) $page_id;
                    $notice = array(
                        'type'    => 'success',
                        'message' => sprintf(
                            __('Set creato con successo. %sVisualizza pagina%s', 'unique-coupon-generator'),
                            '<a href="' . esc_url(get_permalink($page_id)) . '" target="_blank">',
                            '</a>'
                        ),
                    );
                }
            }
            $set_data = $coupon_sets[$base_coupon_code] ?? array('name' => $base_coupon_code);
            $set_data['shortcode'] = '[richiedi_coupon base="' . $base_coupon_code . '"]';
            $set_data['fields']    = $fields_required;
            $set_data['custom_field_label'] = $custom_field_label;
            $set_data['custom_field_key']   = $custom_field_key;
            $set_data['custom_fields']      = array();
            if ($custom_field_label && $custom_field_key) {
                $set_data['custom_fields'][] = array(
                    'label' => $custom_field_label,
                    'key'   => $custom_field_key,
                );
            }
            $set_data['fidelity'] = array(
                'enabled'        => $enable_fidelity,
                'points_per_euro'=> $points_per_euro,
                'signup_points'  => $signup_points,
            );
            $set_data['privacy'] = array(
                'required' => $privacy_required,
                'page_id'  => $privacy_page_id,
            );
            $set_data['show_whatsapp_opt_in'] = $show_whatsapp_checked ? 1 : 0;
            $set_data['allow_png_download']   = $allow_png_checked ? 1 : 0;
            $set_data['allow_pdf_download']   = $allow_pdf_checked ? 1 : 0;
            $set_data['whatsapp_message']     = $coupon_set_whatsapp_value;

            $status_input = sanitize_key(wp_unslash($_POST['coupon_set_status'] ?? 'active'));
            $status_options = array_keys(ucg_coupon_set_statuses());
            if (!in_array($status_input, $status_options, true)) {
                $status_input = 'active';
            }
            $set_data['status'] = $status_input;

            $image_id = isset($_POST['coupon_set_image']) ? absint(wp_unslash($_POST['coupon_set_image'])) : 0;
            $image_url_input = isset($_POST['coupon_set_image_url']) ? esc_url_raw(wp_unslash($_POST['coupon_set_image_url'])) : '';
            $image_url = '';
            if ($image_id && function_exists('wp_get_attachment_image_url')) {
                $image_url = wp_get_attachment_image_url($image_id, 'large');
            }
            if (!$image_url && $image_url_input) {
                $image_url = $image_url_input;
            }
            $set_data['image_id']  = $image_id;
            $set_data['image_url'] = $image_url;

            $coupon_sets[$base_coupon_code] = $set_data;
            update_option('mms_coupon_sets', $coupon_sets);
            $coupon_sets = ucg_get_coupon_sets();

            if ($create_thank_you && !get_page_by_path('thank-you')) {
                wp_insert_post(array(
                    'post_title'   => __('Thank You', 'unique-coupon-generator'),
                    'post_name'    => 'thank-you',
                    'post_content' => '<h2>' . esc_html__('Grazie per aver richiesto il tuo coupon!', 'unique-coupon-generator') . '</h2>',
                    'post_status'  => 'publish',
                    'post_type'    => 'page',
                ));
            }

            if ($create_verify && !get_page_by_path('verifica-qr')) {
                wp_insert_post(array(
                    'post_title'   => __('Verifica QR', 'unique-coupon-generator'),
                    'post_name'    => 'verifica-qr',
                    'post_content' => '[verifica_qr]',
                    'post_status'  => 'publish',
                    'post_type'    => 'page',
                ));
            }

            if (empty($notice)) {
                $notice = array(
                    'type'    => 'success',
                    'message' => __('Impostazioni del set salvate correttamente.', 'unique-coupon-generator'),
                );
            }

            if ($set_created) {
                $email_options_url = esc_url(admin_url('admin.php?page=ucg-admin&tab=emails'));
                ob_start();
                ?>
                <div class="ucg-modal" data-ucg-modal="email" data-ucg-require-confirm>
                    <div class="ucg-modal__dialog" role="alertdialog" aria-modal="true">
                        <div class="ucg-modal__icon dashicons dashicons-email-alt"></div>
                        <h2><?php esc_html_e('Configura le email prima di inviare i coupon', 'unique-coupon-generator'); ?></h2>
                        <p><?php esc_html_e('Guarda la configurazione della Mail, controlla se l\'indirizzo del Mittente combacia con il tuo dominio e dopodiché clicca su salva. Se non fai questo potrebbero esserci ERRORE D\'INVIO da parte del plugin.', 'unique-coupon-generator'); ?></p>
                        <label class="ucg-modal__confirm">
                            <input type="checkbox" data-ucg-modal-confirm>
                            <span><?php esc_html_e('Ho controllato e salvato le impostazioni email.', 'unique-coupon-generator'); ?></span>
                        </label>
                        <div class="ucg-modal__actions">
                            <a class="button button-primary" href="<?php echo $email_options_url; ?>"><?php esc_html_e('Apri impostazioni email', 'unique-coupon-generator'); ?></a>
                            <button type="button" class="button button-secondary ucg-modal-close" data-ucg-close-modal disabled><?php esc_html_e('Ho completato la configurazione', 'unique-coupon-generator'); ?></button>
                        </div>
                    </div>
                </div>
                <?php
                $modal_markup = ob_get_clean();
            }
        } else {
            $notice = array(
                'type'    => 'info',
                'message' => __('Le impostazioni generali sono state salvate. Inserisci un codice base per creare un nuovo set.', 'unique-coupon-generator'),
            );
        }
    }

    $discount_type = get_option('ucc_coupon_discount_type', 'fixed_cart');
    $amount        = get_option('ucc_coupon_amount', '10');
    $pages         = get_pages();
    $status_options = ucg_coupon_set_statuses();

    $base_coupon_code_value   = '';
    $expiry_days_value        = 15;
    $fixed_expiry_value       = '';
    $use_expiry_days_checked  = true;
    $use_fixed_expiry_checked = false;
    $selected_thank_you       = 0;
    $create_verify_checked    = isset($_POST['save_options']) ? !empty($_POST['create_verify_page']) : false;
    $create_thank_checked     = isset($_POST['save_options']) ? !empty($_POST['create_thank_you_page']) : false;
    $fidelity_checked         = false;
    $points_per_euro_value    = 1;
    $signup_points_value      = 0;
    $show_whatsapp_checked    = true;
    $allow_png_checked        = true;
    $allow_pdf_checked        = true;
    $selected_form_fields     = array('first_name', 'last_name', 'email', 'phone');
    $custom_field_label_val   = '';
    $custom_field_key_val     = '';
    $privacy_checkbox_checked = false;
    $selected_privacy_page    = $privacy_default_page;
    $set_status_value         = 'active';
    $set_image_id_value       = 0;
    $set_image_url_value      = '';

    if (isset($_POST['save_options'])) {
        $base_coupon_code_value = sanitize_text_field(wp_unslash($_POST['base_coupon_code'] ?? ''));
        $expiry_days_value      = isset($_POST['expiry_days']) ? intval($_POST['expiry_days']) : 15;
        $fixed_expiry_value     = isset($_POST['fixed_expiry_date']) ? sanitize_text_field(wp_unslash($_POST['fixed_expiry_date'])) : '';
        $use_expiry_days_checked  = !empty($_POST['use_expiry_days']);
        $use_fixed_expiry_checked = !empty($_POST['use_fixed_expiry']);
        if ($use_fixed_expiry_checked) {
            $use_expiry_days_checked = false;
        }
        $selected_thank_you     = isset($_POST['thank_you_page']) ? intval($_POST['thank_you_page']) : 0;
        $fidelity_checked       = !empty($_POST['enable_fidelity']);
        $points_per_euro_value  = isset($_POST['points_per_euro']) ? intval($_POST['points_per_euro']) : 1;
        $signup_points_value    = isset($_POST['signup_points']) ? intval($_POST['signup_points']) : 0;
        $selected_form_fields   = isset($_POST['fields_required'])
            ? array_map('sanitize_text_field', (array) wp_unslash($_POST['fields_required']))
            : $selected_form_fields;
        $custom_field_label_val = isset($_POST['custom_field_label']) ? sanitize_text_field(wp_unslash($_POST['custom_field_label'])) : '';
        $custom_field_key_val   = isset($_POST['custom_field_key']) ? sanitize_text_field(wp_unslash($_POST['custom_field_key'])) : '';
        $privacy_checkbox_checked = !empty($_POST['require_privacy']);
        $selected_privacy_page    = isset($_POST['privacy_policy_page']) ? intval($_POST['privacy_policy_page']) : $privacy_default_page;
        $show_whatsapp_checked    = !empty($_POST['show_whatsapp_opt_in']);
        $allow_png_checked        = !empty($_POST['allow_png_download']);
        $allow_pdf_checked        = !empty($_POST['allow_pdf_download']);
        $set_status_value         = sanitize_key(wp_unslash($_POST['coupon_set_status'] ?? 'active'));
        if (!isset($status_options[$set_status_value])) {
            $set_status_value = 'active';
        }
        $set_image_id_value  = isset($_POST['coupon_set_image']) ? absint(wp_unslash($_POST['coupon_set_image'])) : 0;
        $set_image_url_value = isset($_POST['coupon_set_image_url']) ? esc_url_raw(wp_unslash($_POST['coupon_set_image_url'])) : '';
    } elseif ($editing_set_key && isset($coupon_sets[$editing_set_key])) {
        $base_coupon_code_value = $editing_set_key;
        $expiry_days_value      = intval(get_option('ucc_coupon_expiry_days_' . $editing_set_key, 15));
        $fixed_expiry_value     = sanitize_text_field(get_option('ucc_coupon_fixed_expiry_' . $editing_set_key, ''));
        $use_expiry_days_checked  = get_option('ucc_coupon_use_relative_' . $editing_set_key, 'yes') === 'yes';
        $use_fixed_expiry_checked = get_option('ucc_coupon_use_fixed_' . $editing_set_key, 'no') === 'yes';
        if ($use_fixed_expiry_checked) {
            $use_expiry_days_checked = false;
        }
        $selected_thank_you = intval(get_option('ucc_coupon_thank_you_page_' . $editing_set_key, 0));

        $current_set = $coupon_sets[$editing_set_key];
        if (!empty($current_set['fields']) && is_array($current_set['fields'])) {
            $selected_form_fields = array_map('sanitize_text_field', (array) $current_set['fields']);
        }
        if (!empty($current_set['custom_field_label'])) {
            $custom_field_label_val = sanitize_text_field($current_set['custom_field_label']);
        }
        if (!empty($current_set['custom_field_key'])) {
            $custom_field_key_val = sanitize_text_field($current_set['custom_field_key']);
        }
        if (!empty($current_set['whatsapp_message'])) {
            $coupon_set_whatsapp_value = ucg_sanitize_whatsapp_message((string) $current_set['whatsapp_message']);
        }

        $privacy_settings = $current_set['privacy'] ?? array();
        if (!empty($privacy_settings['required'])) {
            $privacy_checkbox_checked = true;
        }
        if (isset($privacy_settings['page_id'])) {
            $selected_privacy_page = intval($privacy_settings['page_id']);
        }

        if (isset($current_set['show_whatsapp_opt_in'])) {
            $show_whatsapp_checked = !empty($current_set['show_whatsapp_opt_in']);
        }
        if (isset($current_set['allow_png_download'])) {
            $allow_png_checked = !empty($current_set['allow_png_download']);
        }
        if (isset($current_set['allow_pdf_download'])) {
            $allow_pdf_checked = !empty($current_set['allow_pdf_download']);
        }

        $fidelity_settings = $current_set['fidelity'] ?? array();
        if (!empty($fidelity_settings['enabled'])) {
            $fidelity_checked = true;
        }
        if (isset($fidelity_settings['points_per_euro'])) {
            $points_per_euro_value = intval($fidelity_settings['points_per_euro']);
        }
        if (isset($fidelity_settings['signup_points'])) {
            $signup_points_value = intval($fidelity_settings['signup_points']);
        }

        if (!empty($current_set['status']) && isset($status_options[$current_set['status']])) {
            $set_status_value = $current_set['status'];
        }

        if (!empty($current_set['image_id'])) {
            $set_image_id_value = intval($current_set['image_id']);
        }
        if (!empty($current_set['image_url'])) {
            $set_image_url_value = esc_url_raw($current_set['image_url']);
        }
    }

    $available_fields = array(
        'first_name' => __('Nome', 'unique-coupon-generator'),
        'last_name'  => __('Cognome', 'unique-coupon-generator'),
        'email'      => __('Email', 'unique-coupon-generator'),
        'phone'      => __('Telefono', 'unique-coupon-generator'),
        'birth_date' => __('Data di nascita', 'unique-coupon-generator'),
        'city'       => __('Città', 'unique-coupon-generator'),
    );

    $selected_form_fields = array_values(array_intersect($selected_form_fields, array_keys($available_fields)));
    if (empty($selected_form_fields)) {
        $selected_form_fields = array('first_name', 'last_name', 'email', 'phone');
    }

    if (!empty($notice)) {
        $notice_class = 'notice notice-' . esc_attr($notice['type']);
        echo '<div class="' . $notice_class . '"><p>' . wp_kses_post($notice['message']) . '</p></div>';
    }

    echo '<form method="post" action="" class="ucg-admin-form" data-ucg-loading="true">';
    wp_nonce_field('ucc_save_options_nonce');

    echo '<div class="ucg-grid ucg-grid--two">';

    echo '<section class="ucg-card">';
    echo '<h2><span class="dashicons dashicons-admin-generic" aria-hidden="true"></span> ' . esc_html__('Impostazioni generali', 'unique-coupon-generator') . '</h2>';
    echo '<p class="ucg-card__intro">' . esc_html__('Definisci il comportamento predefinito per tutti i coupon generati dal plugin.', 'unique-coupon-generator') . '</p>';
    echo '<div class="ucg-field">';
    echo '<label for="ucc_coupon_discount_type">' . esc_html__('Tipo di sconto', 'unique-coupon-generator') . '</label>';
    echo '<select id="ucc_coupon_discount_type" name="ucc_coupon_discount_type">';
    echo '<option value="fixed_cart" ' . selected($discount_type, 'fixed_cart', false) . '>' . esc_html__('Sconto fisso', 'unique-coupon-generator') . '</option>';
    echo '<option value="percent" ' . selected($discount_type, 'percent', false) . '>' . esc_html__('Percentuale', 'unique-coupon-generator') . '</option>';
    echo '</select>';
    echo '</div>';
    echo '<div class="ucg-field">';
    echo '<label for="ucc_coupon_amount">' . esc_html__('Valore predefinito', 'unique-coupon-generator') . '</label>';
    echo '<input type="number" step="0.01" min="0" id="ucc_coupon_amount" name="ucc_coupon_amount" value="' . esc_attr($amount) . '" placeholder="10">';
    echo '<p class="description">' . esc_html__('Questo valore può essere personalizzato per singolo coupon in WooCommerce.', 'unique-coupon-generator') . '</p>';
    echo '</div>';
    echo '</section>';

    echo '<section class="ucg-card">';
    echo '<h2><span class="dashicons dashicons-plus-alt2" aria-hidden="true"></span> ' . esc_html__('Nuovo set di coupon', 'unique-coupon-generator') . '</h2>';
    echo '<p class="ucg-card__intro">' . esc_html__('Imposta nome, scadenza e pagine collegate per creare un nuovo set di distribuzione.', 'unique-coupon-generator') . '</p>';
    echo '<div class="ucg-field">';
    echo '<label for="base_coupon_code">' . esc_html__('Codice base set', 'unique-coupon-generator') . '</label>';
    $base_attributes = 'required';
    if ($editing_set_key) {
        $base_attributes .= ' readonly';
    }
    echo '<input type="text" id="base_coupon_code" name="base_coupon_code" value="' . esc_attr($base_coupon_code_value) . '" placeholder="SALDI2024" ' . $base_attributes . '>';
    $base_description = __('Sarà utilizzato per generare i codici univoci e lo shortcode pubblico.', 'unique-coupon-generator');
    if ($editing_set_key) {
        $base_description .= ' ' . __('Per modificare questo valore crea un nuovo set.', 'unique-coupon-generator');
    }
    echo '<p class="description">' . esc_html($base_description) . '</p>';
    echo '</div>';

    echo '<div class="ucg-field">';
    echo '<label for="coupon_set_status">' . esc_html__('Stato del set', 'unique-coupon-generator') . '</label>';
    echo '<select id="coupon_set_status" name="coupon_set_status">';
    foreach ($status_options as $status_key => $status_label) {
        echo '<option value="' . esc_attr($status_key) . '" ' . selected($set_status_value, $status_key, false) . '>' . esc_html($status_label) . '</option>';
    }
    echo '</select>';
    echo '<p class="description">' . esc_html__('Quando un set è chiuso non verranno generati nuovi coupon.', 'unique-coupon-generator') . '</p>';
    echo '</div>';

    echo '<div class="ucg-field">';
    echo '<label>' . esc_html__('Immagine del set', 'unique-coupon-generator') . '</label>';
    echo '<div class="ucg-media-field" data-ucg-media>';
    echo '<div class="ucg-media-preview" data-ucg-media-preview>';
    if (!empty($set_image_url_value)) {
        echo '<img src="' . esc_url($set_image_url_value) . '" alt="" />';
    }
    echo '</div>';
    echo '<div class="ucg-media-actions">';
    echo '<button type="button" class="button" data-ucg-media-add data-ucg-media-title="' . esc_attr__('Seleziona o carica immagine', 'unique-coupon-generator') . '" data-ucg-media-button="' . esc_attr__('Usa questa immagine', 'unique-coupon-generator') . '">' . esc_html__('Scegli immagine', 'unique-coupon-generator') . '</button>';
    $remove_classes = 'button button-link-delete';
    if (!$set_image_id_value && empty($set_image_url_value)) {
        $remove_classes .= ' hidden';
    }
    echo '<button type="button" class="' . esc_attr($remove_classes) . '" data-ucg-media-remove>' . esc_html__('Rimuovi immagine', 'unique-coupon-generator') . '</button>';
    echo '</div>';
    echo '<input type="hidden" name="coupon_set_image" value="' . esc_attr($set_image_id_value) . '" data-ucg-media-input>';
    echo '<input type="hidden" name="coupon_set_image_url" value="' . esc_attr($set_image_url_value) . '" data-ucg-media-url>';
    echo '<p class="description">' . esc_html__("L'immagine verrà mostrata nella pagina pubblica di richiesta coupon.", 'unique-coupon-generator') . '</p>';
    echo '</div>';
    echo '</div>';
    echo '<div class="ucg-field-group">';
    echo '<div class="ucg-field">';
    echo '<label class="ucg-switch">';
    echo '<input type="checkbox" id="use_expiry_days" name="use_expiry_days" value="1" ' . checked($use_expiry_days_checked, true, false) . ' data-ucg-toggle="relative-expiry" data-ucg-exclusive="expiry-mode">';
    echo '<span>' . esc_html__('Usa una validità in giorni', 'unique-coupon-generator') . '</span>';
    echo '</label>';
    $relative_class = $use_expiry_days_checked ? '' : ' hidden';
    echo '<div class="ucg-subcard' . $relative_class . '" data-ucg-target="relative-expiry">';
    echo '<label for="expiry_days">' . esc_html__('Validità (giorni)', 'unique-coupon-generator') . '</label>';
    echo '<input type="number" id="expiry_days" name="expiry_days" min="0" value="' . esc_attr($expiry_days_value) . '">';
    echo '<p class="description">' . esc_html__('Il coupon scadrà dopo il numero di giorni selezionato.', 'unique-coupon-generator') . '</p>';
    echo '</div>';
    echo '</div>';
    echo '<div class="ucg-field">';
    echo '<label class="ucg-switch">';
    echo '<input type="checkbox" id="use_fixed_expiry" name="use_fixed_expiry" value="1" ' . checked($use_fixed_expiry_checked, true, false) . ' data-ucg-toggle="fixed-expiry" data-ucg-exclusive="expiry-mode">';
    echo '<span>' . esc_html__('Usa una data di scadenza fissa', 'unique-coupon-generator') . '</span>';
    echo '</label>';
    $fixed_class = $use_fixed_expiry_checked ? '' : ' hidden';
    echo '<div class="ucg-subcard' . $fixed_class . '" data-ucg-target="fixed-expiry">';
    echo '<label for="fixed_expiry_date">' . esc_html__('Data di scadenza fissa', 'unique-coupon-generator') . '</label>';
    echo '<input type="date" id="fixed_expiry_date" name="fixed_expiry_date" value="' . esc_attr($fixed_expiry_value) . '">';
    echo '<p class="description">' . esc_html__('Il coupon scadrà alla data selezionata.', 'unique-coupon-generator') . '</p>';
    echo '</div>';
    echo '</div>';
    echo '</div>';
    echo '<div class="ucg-field">';
    echo '<label for="thank_you_page">' . esc_html__('Pagina di ringraziamento', 'unique-coupon-generator') . '</label>';
    echo '<select id="thank_you_page" name="thank_you_page">';
    echo '<option value="0">' . esc_html__('— Nessun redirect —', 'unique-coupon-generator') . '</option>';
    foreach ($pages as $page) {
        $selected = selected($selected_thank_you, $page->ID, false);
        echo '<option value="' . esc_attr($page->ID) . '" ' . $selected . '>' . esc_html($page->post_title) . '</option>';
    }
    echo '</select>';
    echo '</div>';
    echo '<div class="ucg-field ucg-field--inline">';
    echo '<label><input type="checkbox" name="create_thank_you_page" value="1" ' . checked($create_thank_checked, true, false) . '> ' . esc_html__('Genera automaticamente la pagina di ringraziamento', 'unique-coupon-generator') . '</label>';
    echo '<label><input type="checkbox" name="create_verify_page" value="1" ' . checked($create_verify_checked, true, false) . '> ' . esc_html__('Genera la pagina di verifica QR', 'unique-coupon-generator') . '</label>';
    echo '</div>';
    echo '</section>';

    echo '</div>';
    echo '<div class="ucg-grid ucg-grid--two">';

    echo '<section class="ucg-card">';
    echo '<h2><span class="dashicons dashicons-forms" aria-hidden="true"></span> ' . esc_html__('Campi del modulo', 'unique-coupon-generator') . '</h2>';
    echo '<p class="ucg-card__intro">' . esc_html__('Scegli quali informazioni chiedere all\'utente e aggiungi eventuali campi personalizzati.', 'unique-coupon-generator') . '</p>';
    echo '<div class="ucg-checkbox-grid">';
    foreach ($available_fields as $field_key => $field_label) {
        $checked = in_array($field_key, $selected_form_fields, true);
        echo '<label class="ucg-checkbox">';
        echo '<input type="checkbox" name="fields_required[]" value="' . esc_attr($field_key) . '" ' . checked($checked, true, false) . '> ' . esc_html($field_label);
        if (in_array($field_key, array('first_name', 'last_name', 'email', 'phone'), true)) {
            echo '<span class="ucg-badge ucg-badge--required">' . esc_html__('Consigliato', 'unique-coupon-generator') . '</span>';
        }
        echo '</label>';
    }
    echo '</div>';
    echo '<div class="ucg-field ucg-field--inline">';
    echo '<label class="ucg-switch">';
    echo '<input type="checkbox" name="show_whatsapp_opt_in" value="1" ' . checked($show_whatsapp_checked, true, false) . ' data-ucg-modal-trigger="whatsapp-reminder">';
    echo '<span>' . esc_html__('Mostra l’opzione “Invio tramite WhatsApp” nel form', 'unique-coupon-generator') . '</span>';
    echo '</label>';
    echo '<p class="description">' . esc_html__('Se attivo, gli utenti potranno richiedere il QR anche via WhatsApp.', 'unique-coupon-generator') . '</p>';
    echo '</div>';
    echo '<div class="ucg-field">';
    echo '<label for="coupon_set_whatsapp_message">' . esc_html__('Messaggio WhatsApp personalizzato', 'unique-coupon-generator') . '</label>';
    echo '<textarea id="coupon_set_whatsapp_message" name="coupon_set_whatsapp_message" rows="5" class="large-text" placeholder="' . esc_attr__('Lascia vuoto per utilizzare il messaggio predefinito.', 'unique-coupon-generator') . '">' . esc_textarea($coupon_set_whatsapp_value) . '</textarea>';
    echo '<p class="description">' . esc_html__('Questo messaggio verrà usato nei link WhatsApp per i coupon di questo set.', 'unique-coupon-generator') . '</p>';
    echo '</div>';

    $whatsapp_placeholders = ucg_get_whatsapp_placeholders();
    if (!empty($whatsapp_placeholders)) {
        echo '<div class="ucg-field ucg-field--help ucg-field--whatsapp">';
        echo '<p><strong>' . esc_html__('Variabili disponibili', 'unique-coupon-generator') . '</strong></p>';
        echo '<ul>';
        foreach ($whatsapp_placeholders as $placeholder => $description) {
            echo '<li><code>' . esc_html($placeholder) . '</code> — ' . esc_html($description) . '</li>';
        }
        echo '</ul>';
        echo '</div>';
    }

    $preview_sample = ucg_get_whatsapp_preview_data();
    $preview_qr     = isset($preview_sample['qr_link']) ? esc_url($preview_sample['qr_link']) : '';
    $preview_code   = isset($preview_sample['coupon_code']) ? $preview_sample['coupon_code'] : '';
    $preview_name   = isset($preview_sample['user_name']) ? $preview_sample['user_name'] : '';

    echo '<div class="ucg-field ucg-field--preview ucg-field--whatsapp">';
    echo '<button type="button" class="button button-secondary ucg-whatsapp-preview-button" data-ucg-whatsapp-preview="#coupon_set_whatsapp_message" data-ucg-whatsapp-default="' . esc_attr(ucg_get_default_whatsapp_message()) . '" data-ucg-preview-qr="' . esc_attr($preview_qr) . '" data-ucg-preview-code="' . esc_attr($preview_code) . '" data-ucg-preview-name="' . esc_attr($preview_name) . '">';
    echo '<span class="dashicons dashicons-visibility" aria-hidden="true"></span> ' . esc_html__('Anteprima messaggio WhatsApp', 'unique-coupon-generator');
    echo '</button>';
    echo '<p class="description">' . esc_html__('Mostra un esempio con le variabili sostituite. Usa {line_break} per andare a capo.', 'unique-coupon-generator') . '</p>';
    echo '<div class="ucg-whatsapp-preview" data-ucg-whatsapp-output aria-live="polite"></div>';
    echo '</div>';
    echo '<div class="ucg-field">';
    echo '<label class="ucg-switch">';
    echo '<input type="checkbox" name="allow_png_download" value="1" ' . checked($allow_png_checked, true, false) . '>';
    echo '<span>' . esc_html__('Permetti il download del QR in PNG', 'unique-coupon-generator') . '</span>';
    echo '</label>';
    echo '<p class="description">' . esc_html__('Abilita nel form la casella per scaricare l’immagine PNG del QR code.', 'unique-coupon-generator') . '</p>';
    echo '</div>';
    echo '<div class="ucg-field">';
    echo '<label class="ucg-switch">';
    echo '<input type="checkbox" name="allow_pdf_download" value="1" ' . checked($allow_pdf_checked, true, false) . '>';
    echo '<span>' . esc_html__('Permetti il download del PDF con i dettagli', 'unique-coupon-generator') . '</span>';
    echo '</label>';
    echo '<p class="description">' . esc_html__('Mostra nel form l’opzione per ottenere un PDF riepilogativo del coupon/evento.', 'unique-coupon-generator') . '</p>';
    echo '</div>';
    echo '<div class="ucg-field-group">';
    echo '<div class="ucg-field">';
    echo '<label for="custom_field_label">' . esc_html__('Etichetta campo personalizzato', 'unique-coupon-generator') . '</label>';
    echo '<input type="text" id="custom_field_label" name="custom_field_label" value="' . esc_attr($custom_field_label_val) . '" placeholder="Es. Codice fiscale">';
    echo '</div>';
    echo '<div class="ucg-field">';
    echo '<label for="custom_field_key">' . esc_html__('Slug campo (minuscole senza spazi)', 'unique-coupon-generator') . '</label>';
    echo '<input type="text" id="custom_field_key" name="custom_field_key" value="' . esc_attr($custom_field_key_val) . '" placeholder="es_codice">';
    echo '</div>';
    echo '</div>';
    echo '</section>';

    echo '<section class="ucg-card">';
    echo '<h2><span class="dashicons dashicons-awards" aria-hidden="true"></span> ' . esc_html__('Programma fidelity', 'unique-coupon-generator') . '</h2>';
    echo '<p class="ucg-card__intro">' . esc_html__('Premia i clienti che richiedono il coupon assegnando punti automatici.', 'unique-coupon-generator') . '</p>';
    echo '<label class="ucg-switch">';
    echo '<input type="checkbox" name="enable_fidelity" value="1" ' . checked($fidelity_checked, true, false) . ' data-ucg-toggle="fidelity-settings">';
    echo '<span>' . esc_html__('Attiva fidelity per questo set', 'unique-coupon-generator') . '</span>';
    echo '</label>';
    $fidelity_class = $fidelity_checked ? '' : ' hidden';
    echo '<div class="ucg-subcard' . $fidelity_class . '" data-ucg-target="fidelity-settings">';
    echo '<div class="ucg-field">';
    echo '<label for="points_per_euro">' . esc_html__('Punti per ogni € speso', 'unique-coupon-generator') . '</label>';
    echo '<input type="number" min="1" id="points_per_euro" name="points_per_euro" value="' . esc_attr($points_per_euro_value) . '">';
    echo '</div>';
    echo '<div class="ucg-field">';
    echo '<label for="signup_points">' . esc_html__('Punti di benvenuto', 'unique-coupon-generator') . '</label>';
    echo '<input type="number" min="0" id="signup_points" name="signup_points" value="' . esc_attr($signup_points_value) . '">';
    echo '</div>';
    echo '<p class="description">' . esc_html__('I punti vengono registrati automaticamente nel terminal fidelity.', 'unique-coupon-generator') . '</p>';
    echo '</div>';
    echo '</section>';

    echo '</div>';

    echo '<section class="ucg-card">';
    echo '<h2><span class="dashicons dashicons-shield-alt" aria-hidden="true"></span> ' . esc_html__('Privacy e pagine collegate', 'unique-coupon-generator') . '</h2>';
    echo '<p class="ucg-card__intro">' . esc_html__('Assicurati di informare gli utenti e collega eventuali pagine di policy.', 'unique-coupon-generator') . '</p>';
    echo '<label class="ucg-switch">';
    echo '<input type="checkbox" name="require_privacy" value="1" ' . checked($privacy_checkbox_checked, true, false) . ' data-ucg-toggle="privacy-settings">';
    echo '<span>' . esc_html__('Richiedi conferma della privacy nel form', 'unique-coupon-generator') . '</span>';
    echo '</label>';
    $privacy_class = $privacy_checkbox_checked ? '' : ' hidden';
    echo '<div class="ucg-subcard' . $privacy_class . '" data-ucg-target="privacy-settings">';
    echo '<div class="ucg-field">';
    echo '<label for="privacy_policy_page">' . esc_html__('Pagina informativa', 'unique-coupon-generator') . '</label>';
    echo '<select id="privacy_policy_page" name="privacy_policy_page">';
    echo '<option value="0">' . esc_html__('— Seleziona pagina —', 'unique-coupon-generator') . '</option>';
    foreach ($pages as $page) {
        $selected = selected($selected_privacy_page, $page->ID, false);
        echo '<option value="' . esc_attr($page->ID) . '" ' . $selected . '>' . esc_html($page->post_title) . '</option>';
    }
    echo '</select>';
    echo '</div>';
    echo '<p class="description">' . esc_html__('Il link verrà mostrato accanto alla checkbox di consenso.', 'unique-coupon-generator') . '</p>';
    echo '</div>';
    echo '</section>';

    echo '<div class="ucg-form-actions">';
    echo '<button type="submit" name="save_options" class="button button-primary ucg-button-spinner">';
    echo '<span class="ucg-button-text">' . esc_html__('Salva set e impostazioni', 'unique-coupon-generator') . '</span>';
    echo '<span class="ucg-button-spinner__indicator" aria-hidden="true"></span>';
    echo '</button>';
    echo '</div>';

    echo '</form>';

    if (!empty($coupon_sets)) {
        echo '<section class="ucg-card ucg-card--table">';
        echo '<h2><span class="dashicons dashicons-index-card" aria-hidden="true"></span> ' . esc_html__('Set configurati', 'unique-coupon-generator') . '</h2>';
        echo '<p class="ucg-card__intro">' . esc_html__('Riepilogo dei set creati con link rapidi alle pagine pubbliche e agli shortcode.', 'unique-coupon-generator') . '</p>';
        echo '<table class="widefat fixed striped">';
        echo '<thead><tr>';
        echo '<th>' . esc_html__('Nome', 'unique-coupon-generator') . '</th>';
        echo '<th>' . esc_html__('Shortcode', 'unique-coupon-generator') . '</th>';
        echo '<th>' . esc_html__('Campi richiesti', 'unique-coupon-generator') . '</th>';
        echo '<th>' . esc_html__('Stato', 'unique-coupon-generator') . '</th>';
        echo '<th>' . esc_html__('Fidelity', 'unique-coupon-generator') . '</th>';
        echo '<th>' . esc_html__('Azioni', 'unique-coupon-generator') . '</th>';
        echo '</tr></thead><tbody>';
        foreach ($coupon_sets as $set_key => $set_data) {
            $fields = !empty($set_data['fields']) ? implode(', ', array_map('ucfirst', $set_data['fields'])) : __('N/D', 'unique-coupon-generator');
            $fidelity_status = !empty($set_data['fidelity']['enabled']) ? __('Attivo', 'unique-coupon-generator') : __('Disattivo', 'unique-coupon-generator');
            $shortcode = $set_data['shortcode'] ?? '[richiedi_coupon base="' . $set_key . '"]';
            $page = plugin_get_page_by_title(sprintf(__('Richiedi %s', 'unique-coupon-generator'), $set_key));
            $status_key = $set_data['status'] ?? 'active';
            $status_label = $status_options[$status_key] ?? ucfirst($status_key);
            $status_class = 'ucg-badge ucg-badge--muted';
            if ('active' === $status_key) {
                $status_class = 'ucg-badge ucg-badge--success';
            } elseif ('closed' === $status_key) {
                $status_class = 'ucg-badge ucg-badge--warning';
            }
            echo '<tr>';
            echo '<td><strong>' . esc_html($set_key) . '</strong></td>';
            echo '<td><code>' . esc_html($shortcode) . '</code></td>';
            echo '<td>' . esc_html($fields) . '</td>';
            echo '<td><span class="' . esc_attr($status_class) . '">' . esc_html($status_label) . '</span></td>';
            echo '<td><span class="ucg-badge ucg-badge--' . (!empty($set_data['fidelity']['enabled']) ? 'success' : 'muted') . '">' . esc_html($fidelity_status) . '</span></td>';
            echo '<td class="ucg-actions">';
            if ($page) {
                echo '<a class="button button-secondary" href="' . esc_url(get_permalink($page)) . '" target="_blank">' . esc_html__('Apri pagina', 'unique-coupon-generator') . '</a> ';
            }
            $edit_url = add_query_arg(
                array(
                    'page'     => 'ucg-admin',
                    'tab'      => 'sets',
                    'edit_set' => $set_key,
                ),
                admin_url('admin.php')
            );
            echo '<a class="button" href="' . esc_url($edit_url) . '">' . esc_html__('Modifica set', 'unique-coupon-generator') . '</a> ';
            echo '<button type="button" class="button ucg-copy" data-ucg-copy="' . esc_attr($shortcode) . '" data-ucg-copy-label="' . esc_attr__('Copiato!', 'unique-coupon-generator') . '">' . esc_html__('Copia shortcode', 'unique-coupon-generator') . '</button>';
            echo '</td>';
            echo '</tr>';
        }
        echo '</tbody></table>';
        echo '</section>';
    }

    if ($modal_markup) {
        echo $modal_markup; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
    }

    if (function_exists('ucg_render_whatsapp_reminder_modal')) {
        echo ucg_render_whatsapp_reminder_modal(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
    }
}

function ucc_display_coupon_management_page() {
    ucg_render_tab_coupon_sets();
}

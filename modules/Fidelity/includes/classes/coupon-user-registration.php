<?php

/**
 * Author: Meteora Web <https://meteoraweb.com>
 */


// Impedisce l'accesso diretto ai file
if (!defined('ABSPATH')) {
    exit;
}

require_once UCG_CLASSES . 'class-coupon-generator.php';
require_once __DIR__ . '/coupon-user-helpers.php';

// Funzione per registrare un utente e richiedere il coupon
function coupon_user_registration_form($base_coupon_code) {
    if (ucg_is_elementor_preview()) {
        return '<p>' . esc_html__('Coupon preview disabled in Elementor', 'unique-coupon-generator') . '</p>';
    }

    if (empty($base_coupon_code)) {
        ucg_log_error('🚨 [UCG] base_coupon_code mancante nella form di registrazione!');
        return '<p>'.esc_html__('Errore: il codice base del coupon non è stato specificato. Verifica lo shortcode: <code>[richiedi_coupon base="ILTUOSET"]</code>.', 'unique-coupon-generator').'</p>';
    }

    $set_settings = function_exists('ucg_get_coupon_set') ? ucg_get_coupon_set($base_coupon_code) : null;
    if (!$set_settings) {
        return '<p>' . esc_html__('Il set di coupon selezionato non è disponibile.', 'unique-coupon-generator') . '</p>';
    }

    $fields = $set_settings['fields'] ?? array();
    $show_whatsapp_opt_in = isset($set_settings['show_whatsapp_opt_in']) ? (bool) $set_settings['show_whatsapp_opt_in'] : true;
    $allow_png_download = isset($set_settings['allow_png_download']) ? (bool) $set_settings['allow_png_download'] : true;
    $allow_pdf_download = isset($set_settings['allow_pdf_download']) ? (bool) $set_settings['allow_pdf_download'] : true;

    $custom_fields = array();
    if (!empty($set_settings['custom_fields']) && is_array($set_settings['custom_fields'])) {
        $custom_fields = $set_settings['custom_fields'];
    } elseif (!empty($set_settings['custom_field_label']) && !empty($set_settings['custom_field_key'])) {
        $custom_fields[] = array(
            'label' => $set_settings['custom_field_label'],
            'key'   => $set_settings['custom_field_key'],
        );
    }

    $set_title = $set_settings['label'] ?? ($set_settings['name'] ?? $base_coupon_code);
    $set_whatsapp_template = isset($set_settings['whatsapp_message']) ? ucg_sanitize_whatsapp_message((string) $set_settings['whatsapp_message']) : '';
    $set_image_url = function_exists('ucg_coupon_set_image_url') ? ucg_coupon_set_image_url($set_settings) : ($set_settings['image_url'] ?? '');

    $is_closed = function_exists('ucg_coupon_set_is_closed') && ucg_coupon_set_is_closed($set_settings);
    $is_active = function_exists('ucg_coupon_set_is_active') ? ucg_coupon_set_is_active($set_settings) : true;

    if ($is_closed || !$is_active) {
        ob_start();
        echo '<div class="ucg-coupon-wrapper">';
        if (!empty($set_image_url)) {
            echo '<div class="ucg-coupon-cover"><img src="' . esc_url($set_image_url) . '" alt="' . esc_attr($set_title) . '"></div>';
        }

        $notice_class = $is_closed ? 'warning' : 'info';
        $notice_message = $is_closed
            ? __('Il QR non può più essere generato perché il set è chiuso.', 'unique-coupon-generator')
            : __('Il coupon non è attualmente disponibile.', 'unique-coupon-generator');

        echo '<div class="ucg-coupon-notice ucg-coupon-notice--' . esc_attr($notice_class) . '">' . esc_html($notice_message) . '</div>';
        echo '</div>';
        return ob_get_clean();
    }

    $privacy_settings = $set_settings['privacy'] ?? array();
    $privacy_required = !empty($privacy_settings['required']);
    $privacy_page_id  = intval($privacy_settings['page_id'] ?? 0);
    $privacy_url      = '';
    if ($privacy_page_id) {
        $privacy_url = get_permalink($privacy_page_id);
    } elseif (function_exists('get_privacy_policy_url')) {
        $privacy_url = get_privacy_policy_url();
    }

    $errors = array();
    $form_values = array(
        'first_name' => '',
        'last_name'  => '',
        'email'      => '',
        'phone'      => '+39',
        'birth_date' => '',
        'city'       => '',
        'whatsapp_opt_in' => false,
        'download_png' => false,
        'download_pdf' => false,
    );

    foreach ($custom_fields as $field) {
        if (isset($field['key'])) {
            $form_values[$field['key']] = '';
        }
    }

    $normalized_phone = ucg_normalize_phone_number('');

    if (isset($_POST['submit_registration'])) {
        if (!isset($_POST['_wpnonce']) || !wp_verify_nonce($_POST['_wpnonce'], 'ucc_user_registration_nonce')) {
            wp_die(esc_html__('Nonce non valido', 'unique-coupon-generator'));
        }

        $form_values['first_name']   = sanitize_text_field(wp_unslash($_POST['first_name'] ?? ''));
        $form_values['last_name']    = sanitize_text_field(wp_unslash($_POST['last_name'] ?? ''));
        $form_values['email']        = sanitize_email(wp_unslash($_POST['email'] ?? ''));
        $phone_input = sanitize_text_field(wp_unslash($_POST['phone'] ?? ''));
        $normalized_phone = ucg_normalize_phone_number($phone_input);
        $form_values['phone']        = $normalized_phone['display'] !== '' ? $normalized_phone['display'] : $phone_input;
        $form_values['birth_date']   = sanitize_text_field(wp_unslash($_POST['birth_date'] ?? ''));
        $form_values['city']         = sanitize_text_field(wp_unslash($_POST['city'] ?? ''));
        $form_values['whatsapp_opt_in'] = $show_whatsapp_opt_in && !empty($_POST['whatsapp_opt_in']);
        $form_values['download_png'] = $allow_png_download && !empty($_POST['download_png']);
        $form_values['download_pdf'] = $allow_pdf_download && !empty($_POST['download_pdf']);

        foreach ($custom_fields as $field) {
            if (!empty($field['key'])) {
                $form_values[$field['key']] = sanitize_text_field(wp_unslash($_POST[$field['key']] ?? ''));
            }
        }

        $delivery_available = (($show_whatsapp_opt_in && in_array('phone', $fields, true)) ? 1 : 0)
            + ($allow_png_download ? 1 : 0)
            + ($allow_pdf_download ? 1 : 0);
        $delivery_selected = (!empty($form_values['whatsapp_opt_in']) ? 1 : 0)
            + (!empty($form_values['download_png']) ? 1 : 0)
            + (!empty($form_values['download_pdf']) ? 1 : 0);

        if ($delivery_available > 0 && $delivery_selected !== 1) {
            $errors[] = esc_html__('Seleziona una sola modalità di ricezione per il QR code.', 'unique-coupon-generator');
        }

        if ($privacy_required && empty($_POST['privacy_accepted'])) {
            $errors[] = esc_html__('Per continuare è necessario accettare la Privacy Policy.', 'unique-coupon-generator');
        }

        if (empty($normalized_phone['is_valid'])) {
            $errors[] = esc_html__('Il numero di telefono deve iniziare con +39 e contenere 10 cifre.', 'unique-coupon-generator');
        }

        if (empty($errors)) {
            $full_phone = $normalized_phone['full'];

            $user_id = email_exists($form_values['email']);
            if (!$user_id && $full_phone) {
                $user_id = phone_exists($full_phone);
            }

            if (!$user_id) {
                $random_password = wp_generate_password(12, false);
                $user_id = wp_create_user($form_values['email'], $random_password, $form_values['email']);

                wp_update_user(array(
                    'ID'         => $user_id,
                    'first_name' => $form_values['first_name'],
                    'last_name'  => $form_values['last_name']
                ));
            }

            if ($full_phone) update_user_meta($user_id, 'billing_phone', $full_phone);
            if ($form_values['city']) update_user_meta($user_id, 'billing_city',  $form_values['city']);
            if ($form_values['birth_date']) update_user_meta($user_id, 'birth_date',    $form_values['birth_date']);

            foreach ($custom_fields as $field) {
                $meta_key = $field['key'] ?? '';
                if ($meta_key && isset($form_values[$meta_key]) && $form_values[$meta_key] !== '') {
                    update_user_meta($user_id, $meta_key, $form_values[$meta_key]);
                }
            }

            if (get_user_meta($user_id, 'coupon_sent_' . $base_coupon_code, true)) {
                $errors[] = esc_html__('Hai ricevuto questo coupon.', 'unique-coupon-generator');
            } elseif (is_admin() && !(function_exists('wp_doing_ajax') && wp_doing_ajax())) {
                $errors[] = esc_html__('Anteprima coupon disabilitata', 'unique-coupon-generator');
            } else {
                $coupon_data = CouponGenerator::genera_coupon_unico_per_utente($user_id, $base_coupon_code);
                if ($coupon_data !== false && is_array($coupon_data)) {
                    $coupon_code = $coupon_data['code'];
                    $qr_code_url = $coupon_data['qr_code_url'];
                    $user_name   = $form_values['first_name'];
                    ucg_log_error("📧 Invio email con set: " . $base_coupon_code);
                    invia_email_coupon($form_values['email'], $coupon_code, $qr_code_url, $base_coupon_code, $user_name);
                    update_user_meta($user_id, 'coupon_sent_' . $base_coupon_code, 1);

                    if ($privacy_required) {
                        update_user_meta($user_id, 'ucg_privacy_acceptance_' . $base_coupon_code, current_time('mysql'));
                    }

                    if (class_exists('UCG_FidelityManager')) {
                        $sets = get_option('mms_coupon_sets', array());
                        $signup = intval($sets[$base_coupon_code]['fidelity']['signup_points'] ?? 0);
                        if ($signup > 0) {
                            UCG_FidelityManager::add_points($user_id, $base_coupon_code, $signup, 'aggiunta', 'iscrizione');
                        }
                    }

                    $full_name = trim($form_values['first_name'] . ' ' . $form_values['last_name']);
                    if ($full_name === '') {
                        $full_name = $form_values['first_name'] ?: $form_values['last_name'];
                    }

                    $whatsapp_key = '';
                    $can_send_whatsapp = $show_whatsapp_opt_in && !empty($form_values['whatsapp_opt_in']) && in_array('phone', $fields, true) && $normalized_phone['full'] !== '';
                    if ($can_send_whatsapp) {
                        $whatsapp_template = $set_whatsapp_template;
                        if (trim($whatsapp_template) === '') {
                            $whatsapp_template = '';
                        }
                        $whatsapp_link = ucg_generate_whatsapp_link(
                            $normalized_phone['full'],
                            array(
                                'qr_link'     => $qr_code_url,
                                'coupon_code' => $coupon_code,
                                'user_name'   => $full_name,
                                'template'    => $whatsapp_template,
                            )
                        );

                        if ($whatsapp_link) {
                            $whatsapp_key = ucg_queue_whatsapp_link($whatsapp_link);
                        }
                    }

                    $thank_you_id = get_option('ucc_coupon_thank_you_page_' . $base_coupon_code, 0);
                    $thank_you_url = $thank_you_id ? get_permalink($thank_you_id) : site_url('/thank-you');
                    $redirect_url = $thank_you_url;

                    if (!empty($whatsapp_key)) {
                        $redirect_url = add_query_arg('ucg_whatsapp', $whatsapp_key, $thank_you_url);
                    } elseif ($allow_png_download && !empty($form_values['download_png']) && !empty($qr_code_url)) {
                        $redirect_url = $qr_code_url;
                    } elseif ($allow_pdf_download && !empty($form_values['download_pdf'])) {
                        $lines = array(
                            sprintf(__('Set coupon: %s', 'unique-coupon-generator'), $set_title),
                            sprintf(__('Codice coupon: %s', 'unique-coupon-generator'), $coupon_code),
                            sprintf(__('Intestatario: %s', 'unique-coupon-generator'), $full_name ?: __('N/D', 'unique-coupon-generator')),
                            sprintf(__('Email: %s', 'unique-coupon-generator'), $form_values['email']),
                        );
                        if ($normalized_phone['full'] !== '') {
                            $lines[] = sprintf(__('Telefono: %s', 'unique-coupon-generator'), $normalized_phone['full']);
                        }
                        $lines[] = sprintf(__('Link QR: %s', 'unique-coupon-generator'), $qr_code_url);

                        $pdf_title = sprintf(__('Dettagli coupon %s', 'unique-coupon-generator'), $coupon_code);
                        $pdf_file = ucg_generate_pdf_file(
                            $pdf_title,
                            $lines,
                            'ucg-coupon-pdf',
                            'coupon-' . sanitize_title($coupon_code),
                            array(
                                'image_url' => $qr_code_url,
                                'image_options' => array(
                                    'width' => 200,
                                    'margin_bottom' => 32,
                                ),
                            )
                        );
                        if ($pdf_file) {
                            $redirect_url = $pdf_file;
                        }
                    }

                    wp_redirect($redirect_url);
                    exit;
                } else {
                    $errors[] = esc_html__('Errore durante la generazione del coupon.', 'unique-coupon-generator');
                }
            }
        }
    }

    ob_start();

    echo '<div class="ucg-coupon-wrapper">';
    if (!empty($set_image_url)) {
        echo '<div class="ucg-coupon-cover"><img src="' . esc_url($set_image_url) . '" alt="' . esc_attr($set_title) . '"></div>';
    }

    if (!empty($errors)) {
        echo '<div class="ucg-error-message">';
        foreach ($errors as $message) {
            echo '<p>' . esc_html($message) . '</p>';
        }
        echo '</div>';
    }
    ?>
    <form method="post" action="" class="custom-coupon-form">
        <?php wp_nonce_field('ucc_user_registration_nonce'); ?>

        <?php if (in_array('first_name', $fields, true)) : ?>
            <label for="first_name"><?php echo esc_html__('Nome:', 'unique-coupon-generator'); ?></label>
            <input type="text" name="first_name" id="first_name" value="<?php echo esc_attr($form_values['first_name']); ?>" required>
        <?php endif; ?>

        <?php if (in_array('last_name', $fields, true)) : ?>
            <label for="last_name"><?php echo esc_html__('Cognome:', 'unique-coupon-generator'); ?></label>
            <input type="text" name="last_name" id="last_name" value="<?php echo esc_attr($form_values['last_name']); ?>" required>
        <?php endif; ?>

        <?php if (in_array('email', $fields, true)) : ?>
            <label for="email"><?php echo esc_html__('Email:', 'unique-coupon-generator'); ?></label>
            <input type="email" name="email" id="email" value="<?php echo esc_attr($form_values['email']); ?>" required>
        <?php endif; ?>

        <?php if (in_array('phone', $fields, true)) : ?>
            <label for="phone"><?php echo esc_html__('Telefono:', 'unique-coupon-generator'); ?></label>
            <input
                type="tel"
                name="phone"
                id="phone"
                value="<?php echo esc_attr($form_values['phone']); ?>"
                required
                inputmode="tel"
                pattern="^\+39\d{10}$"
                title="<?php echo esc_attr__('Inserisci il numero in formato +39 seguito da 10 cifre.', 'unique-coupon-generator'); ?>"
                maxlength="13"
                data-ucg-phone-input
            >
            <p class="ucg-phone-warning" data-ucg-phone-warning hidden aria-live="polite"><?php echo esc_html__('Il numero deve iniziare con +39 e contenere 10 cifre (es. +393451234567).', 'unique-coupon-generator'); ?></p>
        <?php endif; ?>

        <?php
        $delivery_markup = '';
        if ($show_whatsapp_opt_in && in_array('phone', $fields, true)) {
            $delivery_markup .= '<label class="ucg-delivery-option"><input type="checkbox" name="whatsapp_opt_in" value="1" data-ucg-delivery-option' . checked(!empty($form_values['whatsapp_opt_in']), true, false) . '> ' . esc_html__('Voglio ricevere il QR anche su WhatsApp', 'unique-coupon-generator') . '</label>';
        }

        if ($allow_png_download) {
            $delivery_markup .= '<label class="ucg-delivery-option"><input type="checkbox" name="download_png" value="1" data-ucg-delivery-option' . checked(!empty($form_values['download_png']), true, false) . '> ' . esc_html__('Scarica il QR in formato PNG', 'unique-coupon-generator') . '</label>';
        }

        if ($allow_pdf_download) {
            $delivery_markup .= '<label class="ucg-delivery-option"><input type="checkbox" name="download_pdf" value="1" data-ucg-delivery-option' . checked(!empty($form_values['download_pdf']), true, false) . '> ' . esc_html__('Scarica il PDF con i dettagli del coupon', 'unique-coupon-generator') . '</label>';
        }

        if ($delivery_markup !== '') {
            echo '<fieldset class="ucg-delivery-group" data-ucg-delivery-group data-ucg-delivery-required>';
            echo '<legend>' . esc_html__('Come desideri ricevere il QR code?', 'unique-coupon-generator') . '</legend>';
            echo $delivery_markup; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- già sanificato
            echo '<p class="ucg-delivery-warning" data-ucg-delivery-warning hidden aria-live="polite">' . esc_html__('Seleziona una modalità di ricezione per il QR code.', 'unique-coupon-generator') . '</p>';
            echo '</fieldset>';
        }
        ?>

        <?php if (in_array('birth_date', $fields, true)) : ?>
            <label for="birth_date"><?php echo esc_html__('Data di Nascita:', 'unique-coupon-generator'); ?></label>
            <input type="date" name="birth_date" id="birth_date" value="<?php echo esc_attr($form_values['birth_date']); ?>" required>
        <?php endif; ?>

        <?php if (in_array('city', $fields, true)) : ?>
            <label for="city"><?php echo esc_html__('Città:', 'unique-coupon-generator'); ?></label>
            <input type="text" name="city" id="city" value="<?php echo esc_attr($form_values['city']); ?>" required>
        <?php endif; ?>

        <?php foreach ($custom_fields as $field) :
            $field_key = $field['key'] ?? '';
            if (!$field_key) {
                continue;
            }
            ?>
            <label for="<?php echo esc_attr($field_key); ?>"><?php echo esc_html($field['label'] ?? ''); ?>:</label>
            <input type="text" name="<?php echo esc_attr($field_key); ?>" id="<?php echo esc_attr($field_key); ?>" value="<?php echo esc_attr($form_values[$field_key] ?? ''); ?>">
        <?php endforeach; ?>

        <?php if ($privacy_required) :
            $privacy_link_text = esc_html__('Privacy Policy', 'unique-coupon-generator');
            $privacy_label = esc_html__('Acconsento al trattamento dei dati personali così come indicato nella Privacy Policy.', 'unique-coupon-generator');
            if (!empty($privacy_url)) {
                $privacy_label = sprintf(
                    esc_html__('Acconsento al trattamento dei dati personali così come indicato nella %s.', 'unique-coupon-generator'),
                    '<a href="' . esc_url($privacy_url) . '" target="_blank" rel="noopener noreferrer">' . $privacy_link_text . '</a>'
                );
            }
            ?>
            <label class="ucg-privacy-consent">
                <input type="checkbox" name="privacy_accepted" value="1" <?php checked(!empty($_POST['privacy_accepted'])); ?> required>
                <?php echo wp_kses_post($privacy_label); ?>
            </label>
        <?php endif; ?>

        <input type="submit" name="submit_registration" value="<?php echo esc_attr__('Richiedi il qr code', 'unique-coupon-generator'); ?>">
    </form>
    <?php
    echo '</div>';

    return ob_get_clean();
}

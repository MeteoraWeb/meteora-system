<?php

/**
 * Author: Meteora Web <https://meteoraweb.com>
 */

if (!defined('ABSPATH')) {
    exit;
}

function ucg_render_tab_coupon_verify($context = array()) {
    if (!current_user_can('manage_options')) {
        return;
    }

    $defaults = array(
        'page_slug' => 'ucg-admin',
        'tab'       => 'verify',
    );
    $context = wp_parse_args($context, $defaults);

    $action       = '';
    $code         = '';
    $coupon_post  = null;
    $details      = array();
    $notice       = '';
    $notice_class = '';

    if (!empty($_POST['ucg_admin_coupon_action'])) {
        $action = sanitize_text_field(wp_unslash($_POST['ucg_admin_coupon_action']));
        $code   = sanitize_text_field(wp_unslash($_POST['coupon_code'] ?? ''));

        if (empty($_POST['ucg_admin_coupon_nonce']) || !wp_verify_nonce(wp_unslash($_POST['ucg_admin_coupon_nonce']), 'ucg_admin_coupon_verify')) {
            $notice       = __('Operazione non valida. Ricarica la pagina e riprova.', 'unique-coupon-generator');
            $notice_class = 'error';
        } elseif ($code === '') {
            $notice       = __('Per favore, inserisci un codice coupon.', 'unique-coupon-generator');
            $notice_class = 'error';
        } else {
            if (function_exists('ucg_find_coupon_post')) {
                $coupon_post = ucg_find_coupon_post($code);
            }

            if (!$coupon_post) {
                $notice       = __('Il coupon non è valido.', 'unique-coupon-generator');
                $notice_class = 'error';
            } else {
                if (function_exists('ucg_prepare_coupon_details')) {
                    $details = ucg_prepare_coupon_details($coupon_post);
                }

                if (empty($details)) {
                    $notice       = __('Il coupon non è valido.', 'unique-coupon-generator');
                    $notice_class = 'error';
                } else {
                    if ($action === 'mark_used') {
                        if (!empty($details['used'])) {
                            $notice       = __('Il coupon è già stato utilizzato.', 'unique-coupon-generator');
                            $notice_class = 'warning';
                        } else {
                            update_post_meta($coupon_post->ID, 'used', 'yes');

                            if (class_exists('UCG_FidelityManager')) {
                                $email = get_post_meta($coupon_post->ID, 'customer_email', true);
                                $user  = $email ? get_user_by('email', $email) : false;
                                if ($user) {
                                    UCG_FidelityManager::add_points($user->ID, '', 2, 'aggiunta', 'verifica');
                                }
                            }

                            $notice       = __('Il coupon è stato segnato come utilizzato.', 'unique-coupon-generator');
                            $notice_class = 'success';
                            $details      = ucg_prepare_coupon_details($coupon_post);
                        }
                    } else {
                        $notice       = __('Coupon trovato! Controlla i dettagli qui sotto.', 'unique-coupon-generator');
                        $notice_class = 'success';
                    }
                }
            }
        }
    }

    if (!$coupon_post && $code !== '') {
        if (function_exists('ucg_find_coupon_post')) {
            $coupon_post = ucg_find_coupon_post($code);
            if ($coupon_post && function_exists('ucg_prepare_coupon_details')) {
                $details = ucg_prepare_coupon_details($coupon_post);
            }
        }
    }

    $html5_qr_path    = UCG_PLUGIN_DIR . 'assets/js/html5-qrcode.min.js';
    $html5_qr_version = file_exists($html5_qr_path) ? filemtime($html5_qr_path) : UCG_VERSION;
    wp_enqueue_script('html5-qrcode', UCG_PLUGIN_URL . 'assets/js/html5-qrcode.min.js', array(), $html5_qr_version, true);

    echo '<section class="ucg-card">';
    echo '<h2><span class="dashicons dashicons-yes" aria-hidden="true"></span> ' . esc_html__('Verifica coupon generati', 'unique-coupon-generator') . '</h2>';
    echo '<p class="ucg-card__intro">' . esc_html__('Controlla la validità dei coupon emessi e segnali come utilizzati direttamente dal back-end.', 'unique-coupon-generator') . '</p>';

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

    echo '<form method="post" id="ucg-admin-coupon-form" class="ucg-coupon-verify-form">';
    wp_nonce_field('ucg_admin_coupon_verify', 'ucg_admin_coupon_nonce');
    echo '<input type="hidden" name="ucg_admin_coupon_action" value="lookup">';
    echo '<p><label for="ucg-admin-coupon-code" class="screen-reader-text">' . esc_html__('Codice coupon', 'unique-coupon-generator') . '</label>';
    echo '<input type="text" id="ucg-admin-coupon-code" name="coupon_code" value="' . esc_attr($code) . '" class="regular-text" placeholder="' . esc_attr__('Scansiona o inserisci il codice', 'unique-coupon-generator') . '"></p>';
    echo '<p><button type="submit" class="button button-secondary">' . esc_html__('Verifica coupon', 'unique-coupon-generator') . '</button></p>';
    echo '</form>';

    echo '<div id="ucg-admin-coupon-qr" class="ucg-ticket-qr"></div>';

    if ($coupon_post && !empty($details)) {
        $edit_link      = get_edit_post_link($coupon_post->ID, '');
        $amount_output  = $details['amount'] ?? '';
        $amount_label   = '';
        $discount_label = $details['discount_label'] ?? '';
        $expiry_label   = $details['expiry_label'] ?? '';
        $used           = !empty($details['used']);
        $is_expired     = !empty($details['is_expired']);
        $status_label   = $used ? __('Utilizzato', 'unique-coupon-generator') : ($is_expired ? __('Scaduto', 'unique-coupon-generator') : __('Valido', 'unique-coupon-generator'));
        $email          = get_post_meta($coupon_post->ID, 'customer_email', true);
        $download_date  = get_post_meta($coupon_post->ID, 'download_date', true);

        if ($details['discount_type'] === 'percent' && $amount_output !== '') {
            $amount_label = rtrim(rtrim((string) $amount_output, '0'), '.') . '%';
        } elseif ($amount_output !== '') {
            $amount_label = function_exists('wc_price') ? wc_price((float) $amount_output) : $amount_output;
        }

        if ($expiry_label === '') {
            $expiry_label = __('Nessuna data di scadenza', 'unique-coupon-generator');
        }

        echo '<div class="ucg-ticket-summary">';
        echo '<h3>' . esc_html__('Dettagli coupon', 'unique-coupon-generator') . '</h3>';
        echo '<ul>';
        echo '<li><strong>' . esc_html__('Codice:', 'unique-coupon-generator') . '</strong> ' . esc_html($details['code']) . '</li>';
        if ($amount_label !== '') {
            echo '<li><strong>' . esc_html__('Sconto:', 'unique-coupon-generator') . '</strong> ' . wp_kses_post($amount_label) . '</li>';
        }
        if ($discount_label !== '') {
            echo '<li><strong>' . esc_html__('Tipo di sconto:', 'unique-coupon-generator') . '</strong> ' . esc_html($discount_label) . '</li>';
        }
        echo '<li><strong>' . esc_html__('Scadenza:', 'unique-coupon-generator') . '</strong> ' . esc_html($expiry_label) . '</li>';
        echo '<li><strong>' . esc_html__('Stato:', 'unique-coupon-generator') . '</strong> ' . esc_html($status_label) . '</li>';
        if (!empty($email)) {
            echo '<li><strong>' . esc_html__('Assegnato a:', 'unique-coupon-generator') . '</strong> <a href="mailto:' . esc_attr($email) . '">' . esc_html($email) . '</a></li>';
        }
        if (!empty($download_date)) {
            $download_display = mysql2date(get_option('date_format') . ' ' . get_option('time_format'), $download_date);
            echo '<li><strong>' . esc_html__('Data download:', 'unique-coupon-generator') . '</strong> ' . esc_html($download_display) . '</li>';
        }
        echo '</ul>';

        echo '<div class="ucg-ticket-action-buttons">';
        if (!$used) {
            echo '<form method="post" class="ucg-ticket-inline-form">';
            wp_nonce_field('ucg_admin_coupon_verify', 'ucg_admin_coupon_nonce');
            echo '<input type="hidden" name="ucg_admin_coupon_action" value="mark_used">';
            echo '<input type="hidden" name="coupon_code" value="' . esc_attr($details['code']) . '">';
            echo '<button type="submit" class="button button-primary">' . esc_html__('Segna come utilizzato', 'unique-coupon-generator') . '</button>';
            echo '</form>';
        }
        if ($edit_link) {
            echo '<a class="button" href="' . esc_url($edit_link) . '" target="_blank" rel="noopener noreferrer">' . esc_html__('Apri coupon in WooCommerce', 'unique-coupon-generator') . '</a>';
        }
        echo '</div>';
        echo '</div>';
    }

    echo '</section>';

    $inline = "(function(){\n        if (typeof Html5QrcodeScanner === 'undefined') { return; }\n        var container = document.getElementById('ucg-admin-coupon-qr');\n        if (!container) { return; }\n        var scanner = new Html5QrcodeScanner('ucg-admin-coupon-qr', { fps: 10, qrbox: 220 });\n        scanner.render(function(decodedText){\n            var code = decodedText;\n            try {\n                var url = new URL(decodedText);\n                var param = url.searchParams.get('coupon_code');\n                if (param) { code = param; }\n            } catch (e) {}\n            var input = document.getElementById('ucg-admin-coupon-code');\n            if (input) {\n                input.value = code;\n            }\n            var form = document.getElementById('ucg-admin-coupon-form');\n            if (form) {\n                form.submit();\n            }\n        });\n    })();";
    wp_add_inline_script('html5-qrcode', $inline);
}

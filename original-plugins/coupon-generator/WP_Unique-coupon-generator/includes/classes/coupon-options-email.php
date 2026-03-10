<?php

/**
 * Author: Meteora Web <https://meteoraweb.com>
 */


if (!defined('ABSPATH')) {
    exit;
}

/**
 * Render the email options tab.
 *
 * @param array $context Rendering context.
 */
function ucg_render_tab_coupon_emails($context = array()) {
    $coupon_sets    = get_option('ucc_coupon_sets', array());
    $email_settings = get_option('ucc_email_settings', array());
    $notice         = array();

    if (empty($coupon_sets)) {
        echo '<div class="notice notice-warning"><p>' . esc_html__('Crea almeno un set di coupon per configurare le email automatiche.', 'unique-coupon-generator') . '</p></div>';
        echo '<p>' . esc_html__('Vai alla scheda "Set e generazione" per creare il tuo primo set.', 'unique-coupon-generator') . '</p>';
        return;
    }

    $set_keys   = array_keys($coupon_sets);
    $active_set = isset($_GET['set']) ? sanitize_text_field(wp_unslash($_GET['set'])) : reset($set_keys);
    if (!in_array($active_set, $set_keys, true)) {
        $active_set = reset($set_keys);
    }

    if (isset($_POST['save_email_options'])) {
        check_admin_referer('ucc_save_email_options_nonce');
        $current_set = sanitize_text_field(wp_unslash($_POST['current_set'] ?? ''));
        if ($current_set && isset($coupon_sets[$current_set])) {
            $settings = array(
                'email_subject'  => sanitize_text_field(wp_unslash($_POST['email_subject'] ?? '')),
                'email_body'     => wp_kses_post($_POST['email_body'] ?? ''),
                'email_from'     => sanitize_email(wp_unslash($_POST['email_from'] ?? '')),
                'email_sender'   => sanitize_text_field(wp_unslash($_POST['email_sender'] ?? '')),
                'remind_days'    => max(0, intval($_POST['remind_days'] ?? 0)),
                'remind_message' => wp_kses_post($_POST['remind_message'] ?? ''),
            );

            $email_settings[$current_set] = $settings;
            update_option('ucc_email_settings', $email_settings);

            $active_set = $current_set;
            $notice = array(
                'type'    => 'success',
                'message' => __('Opzioni email aggiornate correttamente.', 'unique-coupon-generator'),
            );
        }
    }

    $domain          = parse_url(home_url(), PHP_URL_HOST);
    $settings        = $email_settings[$active_set] ?? array();
    $defaults        = array(
        'email_subject'  => __('Il tuo codice coupon', 'unique-coupon-generator'),
        'email_body'     => __('Ciao {user_name}, ecco il tuo coupon: {coupon_code}<br>{qr_code}', 'unique-coupon-generator'),
        'email_from'     => 'no-reply@' . $domain,
        'email_sender'   => __('Il Team', 'unique-coupon-generator'),
        'remind_days'    => 7,
        'remind_message' => __('Ricorda di utilizzare il tuo coupon!', 'unique-coupon-generator'),
    );
    $settings = wp_parse_args($settings, $defaults);

    $placeholders = array(
        '{user_name}'   => __('Nome e cognome del destinatario', 'unique-coupon-generator'),
        '{coupon_code}' => __('Codice coupon generato', 'unique-coupon-generator'),
        '{qr_code}'     => __('QR code pronto da scansionare', 'unique-coupon-generator'),
        '{expiry_date}' => __('Data di scadenza del coupon', 'unique-coupon-generator'),
    );

    if (!empty($notice)) {
        echo '<div class="notice notice-' . esc_attr($notice['type']) . '"><p>' . esc_html($notice['message']) . '</p></div>';
    }

    echo '<div class="ucg-email-wrapper">';

    echo '<aside class="ucg-email-nav" aria-label="' . esc_attr__('Set di coupon', 'unique-coupon-generator') . '">';
    echo '<h2>' . esc_html__('Set disponibili', 'unique-coupon-generator') . '</h2>';
    echo '<ul class="ucg-pill-nav" role="tablist">';
    foreach ($coupon_sets as $set_key => $set) {
        $is_active = $set_key === $active_set;
        $url = add_query_arg(array(
            'page' => 'ucg-admin',
            'tab'  => 'emails',
            'set'  => rawurlencode($set_key),
        ), admin_url('admin.php'));
        $classes = 'ucg-pill' . ($is_active ? ' is-active' : '');
        echo '<li role="presentation">';
        echo '<a href="' . esc_url($url) . '" class="' . esc_attr($classes) . '" role="tab" aria-selected="' . ($is_active ? 'true' : 'false') . '">';
        echo '<span class="dashicons dashicons-email" aria-hidden="true"></span> ' . esc_html($set['name']);
        echo '</a>';
        echo '</li>';
    }
    echo '</ul>';

    echo '<div class="ucg-tip">';
    echo '<strong>' . esc_html__('Segnaposto rapidi', 'unique-coupon-generator') . '</strong>';
    echo '<ul>';
    foreach ($placeholders as $placeholder => $description) {
        echo '<li><code>' . esc_html($placeholder) . '</code> — ' . esc_html($description) . '</li>';
    }
    echo '</ul>';
    echo '</div>';

    echo '</aside>';

    echo '<section class="ucg-email-form" role="tabpanel">';
    echo '<h2>' . esc_html(sprintf(__('Notifiche per il set "%s"', 'unique-coupon-generator'), $active_set)) . '</h2>';
    echo '<form method="post" action="' . esc_url(add_query_arg(array('set' => $active_set), ucg_admin_page_url('ucg-admin', 'emails'))) . '" class="ucg-admin-form" data-ucg-loading="true">';
    wp_nonce_field('ucc_save_email_options_nonce');
    echo '<input type="hidden" name="current_set" value="' . esc_attr($active_set) . '">';

    echo '<div class="ucg-card">';
    echo '<h3><span class="dashicons dashicons-admin-users" aria-hidden="true"></span> ' . esc_html__('Mittente', 'unique-coupon-generator') . '</h3>';
    echo '<div class="ucg-field">';
    echo '<label for="email_sender">' . esc_html__('Nome mittente', 'unique-coupon-generator') . '</label>';
    echo '<input type="text" id="email_sender" name="email_sender" value="' . esc_attr($settings['email_sender']) . '" placeholder="' . esc_attr__('Il Team', 'unique-coupon-generator') . '">';
    echo '</div>';
    echo '<div class="ucg-field">';
    echo '<label for="email_from">' . esc_html__('Email mittente', 'unique-coupon-generator') . '</label>';
    echo '<input type="email" id="email_from" name="email_from" value="' . esc_attr($settings['email_from']) . '" placeholder="no-reply@' . esc_attr($domain) . '">';
    echo '</div>';
    echo '</div>';

    echo '<div class="ucg-card">';
    echo '<h3><span class="dashicons dashicons-admin-post" aria-hidden="true"></span> ' . esc_html__('Email di consegna', 'unique-coupon-generator') . '</h3>';
    echo '<div class="ucg-field">';
    echo '<label for="email_subject">' . esc_html__('Oggetto email', 'unique-coupon-generator') . '</label>';
    echo '<input type="text" id="email_subject" name="email_subject" value="' . esc_attr($settings['email_subject']) . '" placeholder="' . esc_attr__('Il tuo coupon è pronto', 'unique-coupon-generator') . '">';
    echo '</div>';
    echo '<div class="ucg-field">';
    echo '<label for="email_body">' . esc_html__('Corpo email', 'unique-coupon-generator') . '</label>';
    wp_editor($settings['email_body'], 'email_body', array(
        'textarea_name' => 'email_body',
        'media_buttons' => false,
        'textarea_rows' => 10,
    ));
    echo '</div>';
    echo '</div>';

    echo '<div class="ucg-card">';
    echo '<h3><span class="dashicons dashicons-clock" aria-hidden="true"></span> ' . esc_html__('Promemoria automatico', 'unique-coupon-generator') . '</h3>';
    echo '<div class="ucg-field-group">';
    echo '<div class="ucg-field">';
    echo '<label for="remind_days">' . esc_html__('Giorni dopo l\'invio', 'unique-coupon-generator') . '</label>';
    echo '<input type="number" id="remind_days" name="remind_days" min="0" value="' . esc_attr($settings['remind_days']) . '">';
    echo '<p class="description">' . esc_html__('Imposta 0 per disattivare il promemoria.', 'unique-coupon-generator') . '</p>';
    echo '</div>';
    echo '<div class="ucg-field">';
    echo '<label for="remind_message">' . esc_html__('Messaggio promemoria', 'unique-coupon-generator') . '</label>';
    echo '<textarea id="remind_message" name="remind_message" rows="5" placeholder="' . esc_attr__('Ciao {user_name}, hai già utilizzato il tuo coupon?', 'unique-coupon-generator') . '">' . esc_textarea($settings['remind_message']) . '</textarea>';
    echo '</div>';
    echo '</div>';
    echo '</div>';

    echo '<div class="ucg-form-actions">';
    echo '<button type="submit" name="save_email_options" class="button button-primary ucg-button-spinner">';
    echo '<span class="ucg-button-text">' . esc_html__('Salva impostazioni email', 'unique-coupon-generator') . '</span>';
    echo '<span class="ucg-button-spinner__indicator" aria-hidden="true"></span>';
    echo '</button>';
    echo '</div>';

    echo '</form>';
    echo '</section>';
    echo '</div>';
}

function ucc_display_email_options_page() {
    ucg_render_tab_coupon_emails();
}

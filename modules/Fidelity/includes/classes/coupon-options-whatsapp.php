<?php

/**
 * Author: Meteora Web <https://meteoraweb.com>
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Render the WhatsApp settings tab.
 *
 * @param array $context Rendering context (unused).
 */
function ucg_render_tab_whatsapp_settings($context = array()) {
    if (!current_user_can('manage_options')) {
        return;
    }

    $default_message = ucg_get_default_whatsapp_message();
    $saved_message   = get_option('ucg_whatsapp_message', '');
    if (!is_string($saved_message)) {
        $saved_message = '';
    }

    if ($saved_message !== '') {
        $saved_message = ucg_sanitize_whatsapp_message($saved_message);
    }

    $notice = array();
    if (isset($_POST['ucg_save_whatsapp'])) {
        check_admin_referer('ucg_save_whatsapp_message');
        $raw_message = wp_unslash($_POST['whatsapp_message'] ?? '');
        $new_message = ucg_sanitize_whatsapp_message($raw_message);
        update_option('ucg_whatsapp_message', $new_message);
        $saved_message = $new_message;

        $notice = array(
            'type'    => 'success',
            'message' => __('Impostazioni WhatsApp salvate correttamente.', 'unique-coupon-generator'),
        );
    }

    if (!empty($notice)) {
        echo '<div class="notice notice-' . esc_attr($notice['type']) . '"><p>' . esc_html($notice['message']) . '</p></div>';
    }

    $placeholders = ucg_get_whatsapp_placeholders();
    $preview_data = ucg_get_whatsapp_preview_data();
    $preview_template = $saved_message !== '' ? $saved_message : $default_message;
    $preview_message  = ucg_prepare_whatsapp_message($preview_template, $preview_data);

    echo '<form method="post" class="ucg-admin-form" action="' . esc_url(ucg_admin_page_url('ucg-admin-whatsapp', 'settings')) . '">';
    wp_nonce_field('ucg_save_whatsapp_message');

    echo '<div class="ucg-card">';
    echo '<h3><span class="dashicons dashicons-smartphone" aria-hidden="true"></span> ' . esc_html__('Testo WhatsApp', 'unique-coupon-generator') . '</h3>';
    echo '<div class="ucg-field">';
    echo '<label for="whatsapp_message">' . esc_html__('Messaggio predefinito', 'unique-coupon-generator') . '</label>';
    echo '<textarea id="whatsapp_message" name="whatsapp_message" rows="6" placeholder="' . esc_attr($default_message) . '">' . esc_textarea($saved_message) . '</textarea>';
    echo '<p class="description">' . esc_html__('Lascia vuoto per utilizzare il messaggio predefinito.', 'unique-coupon-generator') . '</p>';
    echo '</div>';

    echo '<div class="ucg-field ucg-field--help">';
    echo '<p><strong>' . esc_html__('Variabili disponibili', 'unique-coupon-generator') . '</strong></p>';
    echo '<ul>';
    foreach ($placeholders as $placeholder => $description) {
        echo '<li><code>' . esc_html($placeholder) . '</code> — ' . esc_html($description) . '</li>';
    }
    echo '</ul>';
    echo '</div>';

    echo '<div class="ucg-field ucg-field--preview">';
    echo '<p><strong>' . esc_html__('Anteprima risultato', 'unique-coupon-generator') . '</strong></p>';
    echo '<p class="description">' . nl2br(esc_html($preview_message)) . '</p>';
    echo '</div>';
    echo '</div>';

    echo '<div class="ucg-form-actions">';
    echo '<button type="submit" name="ucg_save_whatsapp" class="button button-primary ucg-button-spinner">';
    echo '<span class="ucg-button-text">' . esc_html__('Salva impostazioni WhatsApp', 'unique-coupon-generator') . '</span>';
    echo '<span class="ucg-button-spinner__indicator" aria-hidden="true"></span>';
    echo '</button>';
    echo '</div>';

    echo '</form>';
}

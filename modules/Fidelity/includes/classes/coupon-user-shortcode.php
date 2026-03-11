<?php

/**
 * Author: Meteora Web <https://meteoraweb.com>
 */


// Impedisce l'accesso diretto ai file
if (!defined('ABSPATH')) {
    exit;
}

// Shortcode per richiedere un coupon
function richiedi_coupon_shortcode($atts) {
    $denied_message = '<p>' . esc_html__('Licenza non valida.', 'unique-coupon-generator') . '</p>';
    $blocked = ucg_block_when_forbidden('front_shortcode', $denied_message);
    if ($blocked !== null) {
        return $blocked;
    }

    $atts = shortcode_atts(array('base' => ''), $atts, 'richiedi_coupon');

    if (empty($atts['base'])) {
        ucg_log_error('⚠️ shortcode [richiedi_coupon] senza attributo base');
        return '<p>'.esc_html__('Errore: shortcode malformato. Inserire attributo base, es. <code>[richiedi_coupon base="NCaloz"]</code>.', 'unique-coupon-generator').'</p>';
    }

    // Tutti gli utenti, compresi quelli autenticati, devono compilare il form.
    return coupon_user_registration_form($atts['base']);
}

add_shortcode('richiedi_coupon', 'richiedi_coupon_shortcode');

<?php

/**
 * Author: Meteora Web <https://meteoraweb.com>
 */


// Impedisce l'accesso diretto ai file
if (!defined('ABSPATH')) {
    exit;
}

require_once __DIR__ . '/class-coupon-generator.php';
require_once __DIR__ . '/class-coupon-notifier.php';
require_once __DIR__ . '/class-coupon-validator.php';
require_once __DIR__ . '/class-coupon-cleaner.php';
require_once plugin_dir_path(__FILE__) . 'qr-code-functions.php';
require_once plugin_dir_path(__FILE__) . 'email-functions.php';

// Inclusione delle funzioni divise
require_once plugin_dir_path(__FILE__) . 'coupon-user-helpers.php';
require_once plugin_dir_path(__FILE__) . 'coupon-user-registration.php';
require_once plugin_dir_path(__FILE__) . 'coupon-user-shortcode.php';

if (!function_exists('email_exists')) {
    require_once ABSPATH . 'wp-includes/pluggable.php';
}

/**
 * Funzione principale per richiedere il coupon (shortcode)
 */
function richiedi_coupon($base_coupon_code) {
    $denied_message = '<p>' . esc_html__('Licenza non valida.', 'unique-coupon-generator') . '</p>';
    $blocked = ucg_block_when_forbidden('front_request', $denied_message);
    if ($blocked !== null) {
        return $blocked;
    }

    $set = function_exists('ucg_get_coupon_set') ? ucg_get_coupon_set($base_coupon_code) : null;
    if (!$set) {
        return '<p>' . esc_html__('Il set di coupon selezionato non è disponibile.', 'unique-coupon-generator') . '</p>';
    }

    if (function_exists('ucg_coupon_set_is_closed') && ucg_coupon_set_is_closed($set)) {
        return '<p>' . esc_html__('Il QR non può più essere generato perché il set è chiuso.', 'unique-coupon-generator') . '</p>';
    }

    if (function_exists('ucg_coupon_set_is_active') && !ucg_coupon_set_is_active($set)) {
        return '<p>' . esc_html__('Il coupon non è attualmente disponibile.', 'unique-coupon-generator') . '</p>';
    }

    if (ucg_is_elementor_preview()) {
        return '<p>' . esc_html__('Coupon preview disabled in Elementor', 'unique-coupon-generator') . '</p>';
    }

    if (is_user_logged_in()) {
        $user_id = get_current_user_id();

        if (CouponGenerator::utente_ha_scaricato_coupon($user_id, $base_coupon_code)) {
            return '<p>'.esc_html__('Hai già scaricato un coupon di questo set.', 'unique-coupon-generator').'</p>';
        }

        if (get_user_meta($user_id, 'coupon_sent_' . $base_coupon_code, true)) {
            return '<p>'.esc_html__('Hai già ricevuto questo coupon.', 'unique-coupon-generator').'</p>';
        }

        if (is_admin() && !(function_exists('wp_doing_ajax') && wp_doing_ajax())) {
            return '<p>' . esc_html__('Anteprima coupon disabilitata', 'unique-coupon-generator') . '</p>';
        }

        $coupon_data = CouponGenerator::genera_coupon_unico_per_utente($user_id, $base_coupon_code);
        if ($coupon_data !== false && is_array($coupon_data)) {
            $coupon_code = $coupon_data['code'];
            $qr_code_url = $coupon_data['qr_code_url'];
            $user_info = get_userdata($user_id);
            $user_name = $user_info->first_name ?: '';

            invia_email_coupon(
                $user_info->user_email,
                $coupon_code,
                $qr_code_url,
                $base_coupon_code,
                $user_name
            );

            update_user_meta($user_id, 'coupon_sent_' . $base_coupon_code, 1);
            if(class_exists('UCG_FidelityManager')){
                $sets=get_option('ucc_coupon_sets',[]);
                $signup=intval($sets[$base_coupon_code]['fidelity']['signup_points']??0);
                if($signup>0){
                    UCG_FidelityManager::add_points($user_id,$base_coupon_code,$signup,'aggiunta','iscrizione');
                }
            }

            $thank_you_id = get_option('ucc_coupon_thank_you_page_' . $base_coupon_code, 0);
            $thank_you_url = $thank_you_id ? get_permalink($thank_you_id) : site_url('/thank-you');

            wp_redirect($thank_you_url);
            exit;
        } else {
            return '<p>'.esc_html__('Errore durante la generazione del coupon. Riprova più tardi.', 'unique-coupon-generator').'</p>';
        }
    } else {
        return '<p>'.esc_html__('Devi accedere o registrarti per ricevere il tuo coupon.', 'unique-coupon-generator').'</p>';
    }
}

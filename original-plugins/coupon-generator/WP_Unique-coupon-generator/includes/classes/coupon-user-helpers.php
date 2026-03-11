<?php

/**
 * Author: Meteora Web <https://meteoraweb.com>
 */


// Impedisce l'accesso diretto ai file
if (!defined('ABSPATH')) {
    exit;
}

// Funzione per verificare se esiste un utente con un determinato numero di telefono
function phone_exists($phone) {
    global $wpdb;
    return $wpdb->get_var($wpdb->prepare(
        "SELECT user_id FROM $wpdb->usermeta WHERE meta_key = 'billing_phone' AND meta_value = %s",
        $phone
    ));
}

if (!function_exists('utente_ha_scaricato_coupon')) {
    function utente_ha_scaricato_coupon($user_id, $base_coupon_code) {
        return CouponGenerator::utente_ha_scaricato_coupon($user_id, $base_coupon_code);
    }
}

// Controlla se Elementor sta caricando l'anteprima
// More reliable check for Elementor preview/edit mode
// Uses Elementor\Plugin API to avoid false positives from query parameters
if (!function_exists('ucg_is_elementor_preview')) {
    function ucg_is_elementor_preview() {
        if (class_exists('\\Elementor\\Plugin')) {
            $el = \Elementor\Plugin::$instance;
            if (isset($el->editor) && $el->editor->is_edit_mode()) {
                return true;
            }
            if (isset($el->preview) && $el->preview->is_preview_mode()) {
                return true;
            }
        }
        return false;
    }
}

// Rileva se è aperta l'interfaccia di modifica di Elementor nel backend
if (!function_exists('ucg_is_elementor_editor')) {
    function ucg_is_elementor_editor() {
        return is_admin() && isset($_GET['action']) && $_GET['action'] === 'elementor';
    }
}

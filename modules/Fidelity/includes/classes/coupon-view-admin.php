<?php

/**
 * Author: Meteora Web <https://meteoraweb.com>
 */

// Impedisce l'accesso diretto ai file
if (!defined('ABSPATH')) {
    exit;
}

// Aggiungi menu di amministrazione per la visualizzazione dei coupon
add_action('admin_menu', 'ucc_add_coupon_view_menu');

function ucc_add_coupon_view_menu() {
    ucg_safe_add_submenu_page(
        'ucc-gestione-coupon',
        'Visualizza Coupon',
        'Visualizza Coupon',
        'manage_options',
        'ucc-visualizza-coupon',
        'ucc_display_coupon_view_page'
    );
}

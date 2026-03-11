<?php

/**
 * Author: Meteora Web <https://meteoraweb.com>
 */


// Impedisce l'accesso diretto ai file
if (!defined('ABSPATH')) {
    exit;
}

class CouponValidator {

    public static function esiste_coupon($coupon_code) {
        $existing_coupon = plugin_get_page_by_title($coupon_code, OBJECT, 'shop_coupon');
        return $existing_coupon ? true : false;
    }

    public static function verifica_validita_coupon($coupon_code) {
        $coupon = plugin_get_page_by_title($coupon_code, OBJECT, 'shop_coupon');

        if ($coupon) {
            $expiry_date = get_post_meta($coupon->ID, 'expiry_date', true);
            return (!$expiry_date || strtotime($expiry_date) >= time());
        }

        return false;
    }
}

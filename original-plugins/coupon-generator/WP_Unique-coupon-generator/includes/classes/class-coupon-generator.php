<?php

/**
 * Author: Meteora Web <https://meteoraweb.com>
 */


// Impedisce l'accesso diretto ai file
if (!defined('ABSPATH')) {
    exit;
}

require_once UCG_CLASSES . 'qr-code-functions.php';
require_once UCG_CLASSES . 'email-functions.php';

class CouponGenerator {

    protected static function generate_unique_code($base_coupon_code) {
        $base = strtoupper(preg_replace('/[^A-Z0-9]/', '', (string) $base_coupon_code));
        if ($base === '') {
            $base = 'UCG';
        }

        $prefix = substr($base, 0, 4);
        $prefix = $prefix !== '' ? $prefix : 'UCG';

        do {
            $random = strtoupper(wp_generate_password(8, false, false));
            $code    = $prefix . '-' . $random;

            $exists_id = function_exists('wc_get_coupon_id_by_code') ? wc_get_coupon_id_by_code($code) : 0;
            if ($exists_id) {
                $code = '';
                continue;
            }

            $exists_post = plugin_get_page_by_title($code, OBJECT, 'shop_coupon');
            if ($exists_post) {
                $code = '';
            }
        } while (empty($code));

        return $code;
    }

    public static function utente_ha_scaricato_coupon($user_id, $base_coupon_code) {
        $user_info = get_userdata($user_id);
        $existing_coupons = get_posts(array(
            'post_type' => 'shop_coupon',
            'post_status' => 'publish',
            'meta_query' => array(
                array('key' => 'customer_email', 'value' => $user_info->user_email, 'compare' => '='),
                array('key' => 'base_coupon_code', 'value' => $base_coupon_code, 'compare' => '=')
            )
        ));
        return !empty($existing_coupons);
    }

    public static function genera_coupon_unico_per_utente($user_id, $base_coupon_code) {
        if (!ucg_enforce_access_point('coupon_engine')) {
            return false;
        }

        if (function_exists('ucg_get_coupon_set')) {
            $set = ucg_get_coupon_set($base_coupon_code);
            if (!$set || !ucg_coupon_set_is_active($set)) {
                return false;
            }
        }

        $user_info   = get_userdata($user_id);
        $coupon_code = self::generate_unique_code($base_coupon_code);

        $amount = get_option('ucc_coupon_amount', '10');
        $discount_type = get_option('ucc_coupon_discount_type', 'fixed_cart');
        $expiry_days = intval(get_option('ucc_coupon_expiry_days_' . $base_coupon_code, '30'));
        $fixed_expiry = get_option('ucc_coupon_fixed_expiry_' . $base_coupon_code, '');
        $use_relative = get_option('ucc_coupon_use_relative_' . $base_coupon_code, 'yes') === 'yes';
        $use_fixed = get_option('ucc_coupon_use_fixed_' . $base_coupon_code, 'no') === 'yes';

        $expiry_date = '';
        if ($use_fixed && !empty($fixed_expiry)) {
            $expiry_date = $fixed_expiry;
        } elseif ($use_relative && $expiry_days > 0) {
            $expiry_date = date('Y-m-d', strtotime("+$expiry_days days"));
        }

        $coupon = array(
            'post_title' => $coupon_code,
            'post_content' => '',
            'post_status' => 'publish',
            'post_author' => 1,
            'post_type' => 'shop_coupon'
        );

        $new_coupon_id = wp_insert_post($coupon);

        if (is_wp_error($new_coupon_id)) {
            return false;
        }

        update_post_meta($new_coupon_id, 'discount_type', $discount_type);
        update_post_meta($new_coupon_id, 'coupon_amount', $amount);
        update_post_meta($new_coupon_id, 'customer_email', $user_info->user_email);
        update_post_meta($new_coupon_id, 'base_coupon_code', $base_coupon_code);
        update_post_meta($new_coupon_id, 'expiry_date', $expiry_date);

        if (!empty($expiry_date)) {
            $expiry_timestamp = strtotime($expiry_date . ' 23:59:59');
            if ($expiry_timestamp) {
                update_post_meta($new_coupon_id, 'date_expires', $expiry_timestamp);
            } else {
                delete_post_meta($new_coupon_id, 'date_expires');
            }
        } else {
            delete_post_meta($new_coupon_id, 'date_expires');
        }

        $qr_code_url = genera_qr_code($coupon_code);
        update_post_meta($new_coupon_id, 'qr_code_url', $qr_code_url);
        update_post_meta($new_coupon_id, 'download_date', current_time('mysql'));

        return array(
            'code'        => $coupon_code,
            'qr_code_url' => $qr_code_url,
            'id'          => $new_coupon_id,
        );
    }
}

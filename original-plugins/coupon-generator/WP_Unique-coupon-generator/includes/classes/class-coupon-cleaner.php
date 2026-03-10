<?php

/**
 * Author: Meteora Web <https://meteoraweb.com>
 */


// Impedisce l'accesso diretto ai file
if (!defined('ABSPATH')) {
    exit;
}

class CouponCleaner {

    public static function pulizia_coupon_scaduti() {
        $expired_coupons = get_posts(array(
            'post_type' => 'shop_coupon',
            'post_status' => 'publish',
            'meta_query' => array(
                array('key' => 'expiry_date', 'value' => current_time('Y-m-d'), 'compare' => '<')
            )
        ));

        foreach ($expired_coupons as $coupon) {
            wp_delete_post($coupon->ID, true);
        }
    }
}

// Pianificazione evento per pulizia coupon scaduti
if (!wp_next_scheduled('ucc_pulizia_coupon_scaduti_event')) {
    wp_schedule_event(time(), 'daily', 'ucc_pulizia_coupon_scaduti_event');
}
add_action('ucc_pulizia_coupon_scaduti_event', array('CouponCleaner', 'pulizia_coupon_scaduti'));

<?php

/**
 * Author: Meteora Web <https://meteoraweb.com>
 */


// Impedisce l'accesso diretto ai file
if (!defined('ABSPATH')) {
    exit;
}

class CouponNotifier {

    public static function notifica_admin_nuovo_coupon($user_email, $coupon_code) {
        $admin_email = get_option('admin_email');
        $subject = "Nuovo Coupon Generato: $coupon_code";
        $message = "Un nuovo coupon è stato generato per l'utente: $user_email.\nCodice Coupon: $coupon_code";

        wp_mail($admin_email, $subject, $message);
    }
}

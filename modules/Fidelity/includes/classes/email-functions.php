<?php

/**
 * Author: Meteora Web <https://meteoraweb.com>
 */


// Impedisce l'accesso diretto ai file
if (!defined('ABSPATH')) {
    exit;
}

require_once (__DIR__) . '/class-coupon-generator.php';
require_once (__DIR__) . '/class-coupon-notifier.php';
require_once (__DIR__) . '/class-coupon-validator.php';



/**
 * Funzione per inviare l'email con il coupon personalizzata per ogni set
 *
 * @param string $user_email  Email del destinatario
 * @param string $coupon_code Codice coupon generato
 * @param string $qr_code_url URL dell'immagine QR
 * @param string $set_name    Nome del set di coupon (base_coupon_code)
 * @param string $user_name   Nome dell'utente per {user_name}
 */
function invia_email_coupon($user_email, $coupon_code, $qr_code_url, $set_name = '', $user_name = '') {
    if (!ucg_enforce_access_point('mailer_delivery')) {
        return false;
    }

    // Recuperiamo tutte le impostazioni email personalizzate
    $all_settings = get_option('ucc_email_settings', array());

    // **Se il set non è definito nelle impostazioni, blocchiamo l'invio per evitare la doppia email**
    if (empty($set_name) || !isset($all_settings[$set_name])) {
        ucg_log_error(
            sprintf(
                __('❌ Tentativo di inviare email per un set inesistente o non configurato: %s', 'unique-coupon-generator'),
                sanitize_text_field($set_name)
            )
        );
        return false;
    }

    // Recuperiamo le impostazioni del set di coupon
    $settings = $all_settings[$set_name];

    $domain        = parse_url(home_url(), PHP_URL_HOST);
    $subject       = $settings['email_subject'] ?? __('Il tuo codice coupon', 'unique-coupon-generator');
    $body_template = $settings['email_body'] ?? __('Grazie {user_name}! Il tuo codice è {coupon_code}<br>{qr_code}', 'unique-coupon-generator');
    $sender_email  = $settings['email_from'] ?? 'no-reply@' . $domain;
    $sender_name   = $settings['email_sender'] ?? __('Il Team', 'unique-coupon-generator');

    // **Sostituzione delle variabili dinamiche**
    $qr_code_image = '<img src="' . esc_url($qr_code_url) . '" alt="' . esc_attr__('QR Code', 'unique-coupon-generator') . '">';
    $body = str_replace(
        array('{coupon_code}', '{qr_code}', '{user_name}'),
        array(esc_html($coupon_code), $qr_code_image, esc_html($user_name)),
        $body_template ?? ''
    );

    // **Costruzione header personalizzato**
    $from_header = $sender_name . ' <' . sanitize_email($sender_email) . '>';
    $headers = array('Content-Type: text/html; charset=UTF-8', 'From: ' . $from_header);

    // **Invio della mail**
    $mail_sent = wp_mail($user_email, $subject, $body, $headers);

    if (!$mail_sent) {
        ucg_log_error(
            sprintf(
                __('❌ Errore nell\'invio della email per il coupon: %s', 'unique-coupon-generator'),
                sanitize_email($user_email)
            )
        );
        ucg_log_error(
            sprintf(
                __('🧪 Debug wp_mail - Oggetto: %1$s | Headers: %2$s | Body: %3$s', 'unique-coupon-generator'),
                $subject,
                print_r($headers, true),
                wp_strip_all_tags($body)
            )
        );
        return false;
    } else {
        ucg_log_error(
            sprintf(
                __('✅ Email inviata correttamente a: %s', 'unique-coupon-generator'),
                sanitize_email($user_email)
            )
        );
    }

    return true;
}

/**
 * Funzione per inviare email di remind per ogni set di coupon
 */
function invia_email_remind() {
    if (ucg_block_when_forbidden('mailer_reminder', true)) {
        return;
    }

    // Recuperiamo tutte le impostazioni email
    $all_settings = get_option('ucc_email_settings', array());
    // Recuperiamo i set di coupon disponibili
    $coupon_sets = get_option('mms_coupon_sets', array());

    foreach ($coupon_sets as $set_name => $set_info) {
        // **Se il set non ha impostazioni, lo saltiamo**
        if (!isset($all_settings[$set_name])) {
            continue;
        }

        $settings = $all_settings[$set_name];

        $domain         = parse_url(home_url(), PHP_URL_HOST);
        $remind_days    = isset($settings['remind_days']) ? intval($settings['remind_days']) : 7;
        $remind_message = $settings['remind_message'] ?? __('Ricorda di utilizzare il tuo coupon!', 'unique-coupon-generator');
        $sender_email   = $settings['email_from'] ?? 'no-reply@' . $domain;
        $sender_name    = $settings['email_sender'] ?? __('Il Team', 'unique-coupon-generator');

        // **Recupera tutti i coupon non utilizzati inviati da almeno X giorni**
        $args = array(
            'post_type'   => 'shop_coupon',
            'post_status' => 'publish',
            'meta_query'  => array(
                array(
                    'key'     => 'used',
                    'value'   => 'no',
                    'compare' => '='
                ),
            )
        );

        $coupons = get_posts($args);

        foreach ($coupons as $coupon) {
            $coupon_id = $coupon->ID;
            $date_create = get_the_date('Y-m-d', $coupon_id);

            $diff = (time() - strtotime($date_create)) / 86400;
            if ($diff < $remind_days) {
                continue;
            }

            // **Recuperiamo l'email dell'utente associata al coupon**
            $user_email = get_post_meta($coupon_id, 'customer_email', true);
            $user       = get_user_by('email', $user_email);
            $user_name  = $user ? $user->first_name : '';

            $body_remind = sprintf(__('Ciao {user_name}, %s', 'unique-coupon-generator'), $remind_message);
            $body_remind = str_replace('{user_name}', esc_html($user_name), $body_remind ?? '');

            $subject = sprintf(__('Promemoria: usa il tuo coupon %s', 'unique-coupon-generator'), $set_name);

            $from_header = $sender_name . ' <' . sanitize_email($sender_email) . '>';
            $headers = array('Content-Type: text/html; charset=UTF-8', 'From: ' . $from_header);

            wp_mail($user_email, $subject, $body_remind, $headers);
        }
    }
}

// Pianifica il cron job se non esiste già
if (!wp_next_scheduled('ucc_daily_remind_event')) {
    wp_schedule_event(time(), 'daily', 'ucc_daily_remind_event');
}
add_action('ucc_daily_remind_event', 'invia_email_remind');

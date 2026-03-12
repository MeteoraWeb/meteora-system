<?php

/**
 * Author: Meteora Web <https://meteoraweb.com>
 */

// Impedisce l'accesso diretto ai file
if (!defined('ABSPATH')) {
    exit;
}

// Hook sicuro: carica questa logica solo in area admin
add_action('admin_init', function () {

    // 🔁 Elimina un coupon singolo da tabella
    if (isset($_POST['delete_coupon']) && check_admin_referer('ucc_delete_coupon_nonce')) {
        $coupon_id = intval($_POST['delete_coupon']);
        if ($coupon_id) {
            $user_email = get_post_meta($coupon_id, 'customer_email', true);
            if ($user_email) {
                $user = get_user_by('email', $user_email);
                if ($user) {
                    $user_id = $user->ID;

                    global $wpdb;
                    $meta_keys = $wpdb->get_col(
                        $wpdb->prepare(
                            "SELECT meta_key FROM {$wpdb->usermeta} WHERE user_id = %d AND meta_key LIKE 'coupon_sent_%%'",
                            $user_id
                        )
                    );

                    foreach ($meta_keys as $meta_key) {
                        delete_user_meta($user_id, $meta_key);
                    }

                    clean_user_cache($user_id);
                    wp_cache_flush();
                }
            }

            wp_delete_post($coupon_id, true);
            add_action('admin_notices', function () {
                echo '<div class="updated"><p>✅ Coupon eliminato con successo.</p></div>';
            });
        }
    }

    // 🔁 Elimina un set intero di coupon (e pagina)
    if (isset($_GET['delete_coupon_set']) && check_admin_referer('ucc_delete_coupon_set_nonce')) {
        $set_key = sanitize_text_field(wp_unslash($_GET['delete_coupon_set']));
        $coupon_sets = get_option('mms_coupon_sets', array());

        if (isset($coupon_sets[$set_key])) {
            $set_data   = $coupon_sets[$set_key];
            $display_name = $set_data['name'] ?? $set_key;
            $page_title = 'Richiedi ' . $display_name;
            $page = plugin_get_page_by_title($page_title);
            if ($page) {
                wp_delete_post($page->ID, true);
            }

            unset($coupon_sets[$set_key]);
            update_option('mms_coupon_sets', $coupon_sets);

            add_action('admin_notices', function () use ($display_name) {
                echo '<div class="updated"><p>✅ Set di coupon <strong>' . esc_html($display_name) . '</strong> eliminato con successo.</p></div>';
            });
        } else {
            add_action('admin_notices', function () use ($set_key) {
                echo '<div class="error"><p>⚠️ Il set di coupon <strong>' . esc_html($set_key) . '</strong> non esiste.</p></div>';
            });
        }
    }

});

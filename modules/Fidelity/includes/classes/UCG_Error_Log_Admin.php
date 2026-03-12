<?php

/**
 * Author: Meteora Web <https://meteoraweb.com>
 */

if(!defined('ABSPATH')){exit;}

class UCG_Error_Log_Admin{
    public static function init(){
        ucg_safe_add_submenu_page(
            'ucc-gestione-coupon',
            'Log Errori',
            'Log Errori',
            'manage_options',
            'ucg-error-log',
            [__CLASS__,'page']
        );
    }

    public static function page(){
        if(!current_user_can('manage_options')) return;
        global $wpdb;
        $table = $wpdb->prefix.'mms_error_log';
        if(isset($_POST['ucg_clear_errors']) && check_admin_referer('ucg_clear_errors')){
            $wpdb->query("TRUNCATE TABLE $table");
            echo '<div class="updated"><p>Log svuotato.</p></div>';
        }
        $rows = $wpdb->get_results("SELECT * FROM $table ORDER BY timestamp DESC LIMIT 50");
        ?>
        <div class="wrap ucg-settings-wrapper">
            <h1>Log Errori</h1>
            <form method="post">
                <?php wp_nonce_field('ucg_clear_errors'); ?>
                <p><button type="submit" name="ucg_clear_errors" class="button">Svuota Log</button></p>
            </form>
            <table class="wp-list-table widefat fixed striped">
                <thead><tr><th>Data</th><th>Messaggio</th></tr></thead>
                <tbody>
                    <?php foreach($rows as $r): ?>
                        <tr>
                            <td><?php echo esc_html(date('d/m/Y H:i',strtotime($r->timestamp))); ?></td>
                            <td><?php echo esc_html($r->message); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php
    }
}

add_action('admin_menu',[UCG_Error_Log_Admin::class,'init'],21);

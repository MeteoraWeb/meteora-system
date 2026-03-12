<?php
namespace Meteora\Core\Database;

class DatabaseManager {
    public static function createTables() {
        global $wpdb;
        $charset_collate = $wpdb->get_charset_collate();
        require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );

        self::migrateOldTables();

        // Table for Cart Saver
        $table_name_carts = $wpdb->prefix . 'pcs_carts';
        $sql_carts = "CREATE TABLE $table_name_carts (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            email varchar(100) NOT NULL,
            cart_data longtext NOT NULL,
            status varchar(20) DEFAULT 'pending',
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            last_mail_sent datetime DEFAULT NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY email (email)
        ) $charset_collate;";
        dbDelta( $sql_carts );

        // Table for Sales Engine Logs
        $table_name_logs = $wpdb->prefix . 'mms_price_logs';
        $sql_logs = "CREATE TABLE IF NOT EXISTS $table_name_logs (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            batch_id varchar(100) NOT NULL,
            product_id bigint(20) NOT NULL,
            old_regular varchar(50) DEFAULT NULL,
            old_sale varchar(50) DEFAULT NULL,
            date datetime DEFAULT '0000-00-00 00:00:00' NOT NULL,
            PRIMARY KEY  (id)
        ) $charset_collate;";
        dbDelta( $sql_logs );

        // Table for SEO Logs
        $table_name_seo_logs = $wpdb->prefix . 'mms_seo_logs';
        $sql_seo_logs = "CREATE TABLE IF NOT EXISTS $table_name_seo_logs (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            post_id bigint(20) NOT NULL,
            old_title text,
            old_short text,
            old_long longtext,
            old_kw text,
            old_seo_title text,
            old_seo_desc text,
            date datetime DEFAULT '0000-00-00 00:00:00' NOT NULL,
            PRIMARY KEY  (id)
        ) $charset_collate;";
        dbDelta( $sql_seo_logs );

        // Table for SEO Ultra Logs
        $table_name_seo_ultra_logs = $wpdb->prefix . 'mms_seo_ultra_logs';
        $sql_seo_ultra_logs = "CREATE TABLE IF NOT EXISTS $table_name_seo_ultra_logs (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            target_type varchar(50) NOT NULL,
            target_id bigint(20) NOT NULL,
            old_data longtext NOT NULL,
            date datetime DEFAULT '0000-00-00 00:00:00' NOT NULL,
            PRIMARY KEY  (id)
        ) $charset_collate;";
        dbDelta( $sql_seo_ultra_logs );

        // Table for News Logs
        $table_name_news_logs = $wpdb->prefix . 'mms_news_logs';
        $sql_news_logs = "CREATE TABLE IF NOT EXISTS $table_name_news_logs (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            post_id bigint(20) NOT NULL,
            source_type varchar(50) NOT NULL,
            source_links text NOT NULL,
            date datetime DEFAULT '0000-00-00 00:00:00' NOT NULL,
            PRIMARY KEY  (id)
        ) $charset_collate;";
        dbDelta( $sql_news_logs );

        // UCG - Fidelity Tables -> mms_
        $fid_table = $wpdb->prefix . 'mms_fidelity_points';
        $sql_fid = "CREATE TABLE IF NOT EXISTS $fid_table (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            user_id bigint(20) unsigned NOT NULL,
            set_id varchar(100) NOT NULL,
            points int(11) NOT NULL,
            type varchar(20) NOT NULL,
            action varchar(20) NOT NULL,
            amount_spent float NOT NULL DEFAULT 0,
            created_at datetime NOT NULL,
            PRIMARY KEY (id)
        ) $charset_collate";
        dbDelta($sql_fid);

        $ucg_log_table = $wpdb->prefix . 'mms_logs';
        $sql_ucg_log = "CREATE TABLE IF NOT EXISTS $ucg_log_table (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            action varchar(50) NOT NULL,
            user_id bigint(20) unsigned NOT NULL,
            timestamp datetime NOT NULL,
            PRIMARY KEY  (id)
        ) $charset_collate";
        dbDelta($sql_ucg_log);

        $ucg_email_table = $wpdb->prefix . 'mms_email_log';
        $sql_ucg_email = "CREATE TABLE IF NOT EXISTS $ucg_email_table (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            user_id bigint(20) unsigned NOT NULL,
            email varchar(200) NOT NULL,
            subject varchar(255) NOT NULL,
            result varchar(20) NOT NULL,
            attempts int(11) NOT NULL DEFAULT 1,
            sent_at datetime NOT NULL,
            PRIMARY KEY  (id)
        ) $charset_collate";
        dbDelta($sql_ucg_email);

        $ucg_error_table = $wpdb->prefix . 'mms_error_log';
        $sql_ucg_error = "CREATE TABLE IF NOT EXISTS $ucg_error_table (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            module varchar(100) DEFAULT 'system',
            severity varchar(20) DEFAULT 'error',
            message text NOT NULL,
            timestamp datetime NOT NULL,
            PRIMARY KEY (id)
        ) $charset_collate";
        dbDelta($sql_ucg_error);

        // Check columns in $fid_table
        $col = $wpdb->get_results("SHOW COLUMNS FROM $fid_table LIKE 'amount_spent'");
        if(!$col){
            $wpdb->query("ALTER TABLE $fid_table ADD amount_spent float NOT NULL DEFAULT 0 AFTER action");
        }

        $col_module = $wpdb->get_results("SHOW COLUMNS FROM $ucg_error_table LIKE 'module'");
        if(!$col_module) {
            $wpdb->query("ALTER TABLE $ucg_error_table ADD module varchar(100) DEFAULT 'system' AFTER id");
        }

        $col_severity = $wpdb->get_results("SHOW COLUMNS FROM $ucg_error_table LIKE 'severity'");
        if(!$col_severity) {
            $wpdb->query("ALTER TABLE $ucg_error_table ADD severity varchar(20) DEFAULT 'error' AFTER module");
        }

        // Event Tables se ci sono function
        if (function_exists('mms_events_create_tables')) {
            mms_events_create_tables();
        }
    }

    private static function migrateOldTables() {
        global $wpdb;

        $migrations = [
            'mpe_price_logs'      => 'mms_price_logs',
            'mpe_news_logs'       => 'mms_news_logs',
            'mpe_seo_logs'        => 'mms_seo_logs',
            'mpe_seo_ultra_logs'  => 'mms_seo_ultra_logs',
            'ucg_fidelity_points' => 'mms_fidelity_points',
            'ucg_logs'            => 'mms_logs',
            'ucg_email_log'       => 'mms_email_log',
            'ucg_error_log'       => 'mms_error_log',
            'eventi'              => 'mms_events',
            'eventi_tickets'      => 'mms_tickets',
            'eventi_pr'           => 'mms_prs'
        ];

        foreach ($migrations as $old_name => $new_name) {
            $old_table = $wpdb->prefix . $old_name;
            $new_table = $wpdb->prefix . $new_name;

            if ($wpdb->get_var("SHOW TABLES LIKE '{$old_table}'") == $old_table) {
                if ($wpdb->get_var("SHOW TABLES LIKE '{$new_table}'") != $new_table) {
                    $wpdb->query("RENAME TABLE {$old_table} TO {$new_table}");
                }
            }
        }

        // Migrate options
        $old_coupon_sets = get_option('ucc_coupon_sets');
        if ($old_coupon_sets !== false) {
            $new_coupon_sets = get_option('mms_coupon_sets');
            if ($new_coupon_sets === false) {
                update_option('mms_coupon_sets', $old_coupon_sets);
            }
        }
    }
}

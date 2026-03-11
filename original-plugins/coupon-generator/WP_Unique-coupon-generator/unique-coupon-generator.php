<?php
/**
 * Plugin Name: Unique Coupon Generator
 * Plugin URI: https://meteoraweb.com
 * Description: Genera coupon, ticket unici per ogni utente. fidelity points, invio email automatiche, integrazione con WooCommerce e tanto altro ancora.
 * Version: 1.0
 * Author: Meteora Web
 * Author URI: https://meteoraweb.com
 * License: GPL2
 * Text Domain: unique-coupon-generator
 * Domain Path: /languages
 */

if (!defined('ABSPATH')) {
    exit;
}

// ✅ Costanti base
define('UCG_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('UCG_PLUGIN_URL', plugin_dir_url(__FILE__));
define('UCG_VERSION', '1.0.0');
define('UCG_CLASSES', UCG_PLUGIN_DIR . 'includes/classes/');

require_once UCG_CLASSES . 'class-ucg-access-gate.php';

if (!function_exists('ucg_access_gate')) {
    function ucg_access_gate() {
        return UCG_Access_Gate::instance();
    }
}

if (!function_exists('ucg_access_granted')) {
    function ucg_access_granted() {
        return ucg_access_gate()->is_license_valid();
    }
}

// Legacy wrappers preserved for backward compatibility with third-party integrations.
if (!function_exists('ucg_license_manager')) {
    function ucg_license_manager() {
        if (function_exists('_deprecated_function')) {
            _deprecated_function(__FUNCTION__, '1.0.1', 'ucg_access_gate');
        }

        return ucg_access_gate();
    }
}

if (!function_exists('ucg_license_is_valid')) {
    function ucg_license_is_valid() {
        if (function_exists('_deprecated_function')) {
            _deprecated_function(__FUNCTION__, '1.0.1', 'ucg_access_granted');
        }

        return ucg_access_granted();
    }
}

register_activation_hook(__FILE__, array('UCG_Access_Gate', 'activate'));
register_deactivation_hook(__FILE__, array('UCG_Access_Gate', 'deactivate'));
register_uninstall_hook(__FILE__, array('UCG_Access_Gate', 'uninstall'));

ucg_access_gate()->init();

if (!function_exists('plugin_get_page_by_title')) {
    function plugin_get_page_by_title($page_title, $output = OBJECT, $post_type = 'page') {
        $q = new WP_Query(array(
            'post_type'           => (array) $post_type,
            'title'               => $page_title,
            'post_status'         => 'any',
            'posts_per_page'      => 1,
            'no_found_rows'       => true,
            'ignore_sticky_posts' => true,
            'orderby'             => 'ID',
            'order'               => 'ASC',
        ));

        if (empty($q->posts)) {
            return null;
        }

        return get_post($q->posts[0], $output);
    }
}

/**
 * Elementor kill-switch guard. Determines if the current request is
 * running inside the Elementor editor/preview/AJAX contexts.
 */
function myplugin_is_elementor_context() {
    // Elementor editor opened from admin
    if (is_admin() && isset($_GET['action']) && $_GET['action'] === 'elementor') {
        return true;
    }

    // Preview iframe
    if (isset($_GET['elementor-preview']) || isset($_POST['elementor-preview'])) {
        return true;
    }

    // Elementor AJAX operations / heartbeat
    if (wp_doing_ajax()) {
        $ajax_action = isset($_REQUEST['action']) ? sanitize_text_field($_REQUEST['action']) : '';
        if ($ajax_action === 'heartbeat' || strpos($ajax_action ?? '', 'elementor') !== false) {
            return true;
        }
    }

    // REST requests triggered from Elementor
    if (defined('REST_REQUEST') && REST_REQUEST) {
        $referer = isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : '';
        if (strpos($referer ?? '', 'action=elementor') !== false) {
            return true;
        }
    }

    return false;
}

// Allow external forcing of the suspension via filter
if (!defined('MYPLUGIN_SUSPEND_FOR_ELEMENTOR')) {
    define('MYPLUGIN_SUSPEND_FOR_ELEMENTOR', apply_filters('myplugin/suspend_for_elementor', myplugin_is_elementor_context()));
}

if (MYPLUGIN_SUSPEND_FOR_ELEMENTOR) {
    // ⚠️ Only load the files required for shortcodes while in Elementor
    require_once UCG_PLUGIN_DIR . 'includes/functions.php';
    require_once UCG_CLASSES . 'coupon-user-helpers.php';
    require_once UCG_CLASSES . 'coupon-user-functions.php';
    require_once UCG_CLASSES . 'UCG_FidelityManager.php';
    require_once UCG_CLASSES . 'coupon-shortcode.php';
    require_once UCG_CLASSES . 'coupon-user-shortcode.php';
    require_once UCG_PLUGIN_DIR . 'includes/shortcodes/fidelity-terminal.php';
    require_once UCG_PLUGIN_DIR . 'includes/shortcodes/fidelity-points.php';

    // Remove any style/script handles registered by the plugin
    function myplugin_elementor_clean_assets() {
        foreach ([
            'ucc-admin-style',
            'coupon-form-style',
            'ucg-fidelity-style',
            'html5-qrcode',
            'ucg-fidelity-forms',
        ] as $handle) {
            wp_dequeue_script($handle);
            wp_deregister_script($handle);
            wp_dequeue_style($handle);
            wp_deregister_style($handle);
        }
    }
    add_action('wp_enqueue_scripts', 'myplugin_elementor_clean_assets', PHP_INT_MAX);
    add_action('admin_enqueue_scripts', 'myplugin_elementor_clean_assets', PHP_INT_MAX);

    // Stop full plugin bootstrap in Elementor context
    return;
}

// Utility helpers
require_once UCG_CLASSES . 'coupon-user-helpers.php';
require_once UCG_PLUGIN_DIR . 'includes/functions.php';
require_once UCG_CLASSES . 'ucg-welcome.php';

// Carica le funzioni critiche per gli eventi prima degli hook di attivazione
require_once UCG_PLUGIN_DIR . 'includes/events/event-helpers.php';
require_once UCG_PLUGIN_DIR . 'includes/events/event-database.php';
require_once UCG_PLUGIN_DIR . 'includes/events/event-emails.php';
require_once UCG_PLUGIN_DIR . 'includes/events/event-gateway-in-loco.php';

// ✅ Logga errori fatali in shutdown
register_shutdown_function(function () {
    $error = error_get_last();
    if ($error !== null && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
        $log = "[" . date('Y-m-d H:i:s') . "] ❌ UCG ERRORE FATALE:\n" . print_r($error, true) . "\n\n";
        file_put_contents(__DIR__ . '/ucg_error.log', $log, FILE_APPEND);
    }
});

if (defined('WP_DEBUG') && WP_DEBUG) {
    ini_set('display_errors', WP_DEBUG_DISPLAY ? '1' : '0');
    ini_set('display_startup_errors', WP_DEBUG_DISPLAY ? '1' : '0');
    error_reporting(E_ALL);
} else {
    ini_set('display_errors', '0');
    ini_set('display_startup_errors', '0');
    error_reporting(0);
}


// ✅ Funzione per caricare ricorsivamente i file PHP
//    evita di includere cartelle come "lib" e "views" che
//    contengono dipendenze o template da caricare solo su richiesta
function ucg_autoload_recursive($dir) {
    if (!is_dir($dir)) {
        return;
    }

    $files = scandir($dir);
    if ($files === false) {
        return;
    }

    foreach ($files as $file) {
        if ($file === '.' || $file === '..') continue;
        $path = $dir . '/' . $file;
        if (is_dir($path)) {
            $base = basename($path);
            if ($base === 'lib' || $base === 'views') continue; // ❌ salta cartelle lib e views
            ucg_autoload_recursive($path);
        } elseif (substr($file ?? '', -4) === '.php') {
            require_once $path;
        }
    }
}

// Carica gli stili CSS per l'area admin
function ucg_enqueue_admin_styles($hook) {
    if (strpos($hook ?? '', 'ucc-') === false && strpos($hook ?? '', 'ucg-') === false) {
        return;
    }

    $style_path = plugin_dir_path(__FILE__) . 'assets/css/admin-style.css';
    $style_version = file_exists($style_path) ? filemtime($style_path) : UCG_VERSION;

    wp_enqueue_style(
        'ucc-admin-style',
        plugin_dir_url(__FILE__) . 'assets/css/admin-style.css',
        array('wp-components'),
        $style_version
    );

    $script_path = plugin_dir_path(__FILE__) . 'assets/js/admin-ui.js';
    $script_version = file_exists($script_path) ? filemtime($script_path) : UCG_VERSION;

    wp_enqueue_script(
        'ucg-admin-ui',
        plugin_dir_url(__FILE__) . 'assets/js/admin-ui.js',
        array('jquery'),
        $script_version,
        true
    );
}

function ucc_enqueue_coupon_form_styles() {
    if (!is_page()) {
        return;
    }

    $post = get_post();
    if (!$post || !isset($post->post_content)) {
        return;
    }

    $shortcodes = array('richiedi_coupon', 'ucg_coupon_sets', 'ucg_eventi_attivi', 'verifica_coupon');
    foreach ($shortcodes as $shortcode_tag) {
        if (has_shortcode($post->post_content, $shortcode_tag)) {
            wp_enqueue_style('coupon-form-style', plugin_dir_url(__FILE__) . 'assets/css/style-coupon-form.css');

            $phone_js_path = plugin_dir_path(__FILE__) . 'assets/js/ucg-phone-fields.js';
            $phone_js_version = file_exists($phone_js_path) ? filemtime($phone_js_path) : UCG_VERSION;

            wp_enqueue_script(
                'ucg-phone-fields',
                plugin_dir_url(__FILE__) . 'assets/js/ucg-phone-fields.js',
                array(),
                $phone_js_version,
                true
            );

            break;
        }
    }
}

function ucg_enqueue_fidelity_assets(){
    if(is_page()){
        $post = get_post();
        if(!$post || !isset($post->post_content)){
            return;
        }

        $content = $post->post_content;
        if(has_shortcode($content,'ucg_fidelity_terminal') || has_shortcode($content,'ucg_fidelity_points')){
            $style_path = plugin_dir_path(__FILE__).'assets/css/fidelity-forms.css';
            $style_version = file_exists($style_path) ? filemtime($style_path) : UCG_VERSION;

            $html5_qr_path = plugin_dir_path(__FILE__) . 'assets/js/html5-qrcode.min.js';
            $html5_qr_version = file_exists($html5_qr_path) ? filemtime($html5_qr_path) : UCG_VERSION;

            $fidelity_forms_path = plugin_dir_path(__FILE__) . 'assets/js/fidelity-forms.js';
            $fidelity_forms_version = file_exists($fidelity_forms_path) ? filemtime($fidelity_forms_path) : UCG_VERSION;

            wp_enqueue_style('ucg-fidelity-style', plugin_dir_url(__FILE__).'assets/css/fidelity-forms.css', array(), $style_version);
            wp_enqueue_script(
                'html5-qrcode',
                plugins_url('assets/js/html5-qrcode.min.js', __FILE__),
                array(),
                $html5_qr_version,
                true
            );
            wp_enqueue_script(
                'ucg-fidelity-forms',
                plugins_url('assets/js/fidelity-forms.js', __FILE__),
                array('html5-qrcode'),
                $fidelity_forms_version,
                true
            );
        }
    }
}

function ucg_ensure_fidelity_table(){
    global $wpdb;
    $table = $wpdb->prefix.'ucg_fidelity_points';
    if($wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $table)) != $table){
        ucg_create_fidelity_table();
    }
}

function ucg_ensure_fidelity_columns(){
    global $wpdb;
    $table = $wpdb->prefix.'ucg_fidelity_points';
    $col = $wpdb->get_results("SHOW COLUMNS FROM $table LIKE 'amount_spent'");
    if(!$col){
        $wpdb->query("ALTER TABLE $table ADD amount_spent float NOT NULL DEFAULT 0 AFTER action");
    }
}

function ucg_ensure_error_log_table(){
    global $wpdb;
    $table = $wpdb->prefix.'ucg_error_log';
    if($wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $table)) != $table){
        ucg_create_error_log_table();
    }
}


// ✅ Installazione/Disinstallazione placeholder
function ucg_create_fidelity_table(){
    global $wpdb;
    $charset=$wpdb->get_charset_collate();
    $fid_table=$wpdb->prefix.'ucg_fidelity_points';
    $sql="CREATE TABLE IF NOT EXISTS $fid_table (
        id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
        user_id bigint(20) unsigned NOT NULL,
        set_id varchar(100) NOT NULL,
        points int(11) NOT NULL,
        type varchar(20) NOT NULL,
        action varchar(20) NOT NULL,
        amount_spent float NOT NULL DEFAULT 0,
        created_at datetime NOT NULL,
        PRIMARY KEY (id)
    ) $charset";
    require_once ABSPATH.'wp-admin/includes/upgrade.php';
    dbDelta($sql);
}

function ucg_create_error_log_table(){
    global $wpdb;
    $charset=$wpdb->get_charset_collate();
    $table=$wpdb->prefix.'ucg_error_log';
    $sql="CREATE TABLE IF NOT EXISTS $table (
        id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
        message text NOT NULL,
        timestamp datetime NOT NULL,
        PRIMARY KEY (id)
    ) $charset";
    require_once ABSPATH.'wp-admin/includes/upgrade.php';
    dbDelta($sql);
}

function ucg_install() {
    global $wpdb;
    $charset = $wpdb->get_charset_collate();
    $table = $wpdb->prefix . 'ucg_logs';
    $sql = "CREATE TABLE IF NOT EXISTS $table (
        id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
        action varchar(50) NOT NULL,
        user_id bigint(20) unsigned NOT NULL,
        timestamp datetime NOT NULL,
        PRIMARY KEY  (id)
    ) $charset";
    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    dbDelta($sql);

    // Tabella per i log delle email marketing
    $email_table = $wpdb->prefix . 'ucg_email_log';
    $sql2 = "CREATE TABLE IF NOT EXISTS $email_table (
        id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
        user_id bigint(20) unsigned NOT NULL,
        email varchar(200) NOT NULL,
        subject varchar(255) NOT NULL,
        result varchar(20) NOT NULL,
        attempts int(11) NOT NULL DEFAULT 1,
        sent_at datetime NOT NULL,
        PRIMARY KEY  (id)
    ) $charset";
    dbDelta($sql2);

    // Tabella punti fidelity
    ucg_create_fidelity_table();
    // Tabella error log
    ucg_create_error_log_table();

}
register_activation_hook(__FILE__, 'ucg_install');
register_activation_hook(__FILE__, 'ucg_create_fidelity_table');
register_activation_hook(__FILE__, 'ucg_create_error_log_table');
register_activation_hook(__FILE__, 'ucg_events_create_tables');
register_activation_hook(__FILE__, 'ucg_events_schedule_reminders');
register_activation_hook(__FILE__, 'ucg_schedule_welcome_redirect');

function ucg_events_clear_scheduled_reminders() {
    wp_clear_scheduled_hook('ucg_events_daily_reminder');
}
register_deactivation_hook(__FILE__, 'ucg_events_clear_scheduled_reminders');
function ucg_uninstall() {
    // pulizia se necessario
}

// Carica le traduzioni del plugin
function ucg_load_textdomain() {
    load_plugin_textdomain('unique-coupon-generator', false, dirname(plugin_basename(__FILE__)) . '/languages');
}
add_action('plugins_loaded', 'ucg_load_textdomain');

// Hook generale su init per caricare tutto
add_action('init', 'ucg_init_plugin');

function ucg_init_plugin() {

    // Verifica se siamo in contesto Elementor editor/preview/ajax
    $in_elementor_ctx = function_exists('myplugin_is_elementor_context') ? myplugin_is_elementor_context() : false;

    // Composer autoload per eventuali librerie esterne
    $composer_autoload = __DIR__ . '/vendor/autoload.php';
    if (file_exists($composer_autoload)) {
        ob_start();
        require_once $composer_autoload;
        ob_end_clean();
    } else {
        file_put_contents(
            __DIR__ . '/ucg_error.log',
            "[" . date('Y-m-d H:i:s') . "] ❌ Autoload Composer non trovato\n",
            FILE_APPEND
        );
    }

    // --- Caricamento file ---
    // Sempre includere helpers
    ucg_autoload_recursive(__DIR__ . '/includes');

    // Carica parte admin sempre (così il menu rimane)
    if (is_admin()) {
        ucg_autoload_recursive(__DIR__ . '/admin');
    }

    // Carica parte public solo se NON sei nel contesto Elementor
    if (!$in_elementor_ctx) {
        ucg_autoload_recursive(__DIR__ . '/public');
    }

    // --- Assets / Hook ---
    add_action('admin_enqueue_scripts', 'ucg_enqueue_admin_styles');

    if (!$in_elementor_ctx) {
        add_action('wp_enqueue_scripts', 'ucc_enqueue_coupon_form_styles');
        add_action('wp_enqueue_scripts', 'ucg_enqueue_fidelity_assets');
    }

    // --- Tabelle DB ---
    ucg_ensure_fidelity_table();
    ucg_ensure_fidelity_columns();
    ucg_ensure_error_log_table();
    if (function_exists('ucg_events_ensure_tables')) {
        ucg_events_ensure_tables();
    }
}

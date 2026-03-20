<?php
namespace Meteora\Modules\Fidelity;

class Module {
    /**
     * @var Module
     */
    private static $instance = null;

    /**
     * @return Module
     */
    public static function instance() {
        if ( is_null( self::$instance ) ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        $this->init();
    }

    public function init() {
        if (!defined('UCG_PLUGIN_DIR')) {
            define('UCG_PLUGIN_DIR', plugin_dir_path(__FILE__));
        }
        if (!defined('UCG_PLUGIN_URL')) {
            define('UCG_PLUGIN_URL', plugin_dir_url(__FILE__));
        }
        if (!defined('UCG_VERSION')) {
            define('UCG_VERSION', '1.0.0');
        }
        if (!defined('UCG_CLASSES')) {
            define('UCG_CLASSES', UCG_PLUGIN_DIR . 'includes/classes/');
        }

        $in_elementor_ctx = function_exists('myplugin_is_elementor_context') ? myplugin_is_elementor_context() : false;

        // Since classes are now loaded via Composer classmap, we only need to explicitly
        // require procedural files or files that register hooks immediately
        require_once __DIR__ . '/includes/functions.php';

        // Let's manually require the setup files instead of blind recursion
        $setup_files = [
            '/includes/events/event-helpers.php',
            '/includes/events/event-database.php',
            '/includes/events/event-emails.php',
            '/includes/events/event-gateway-in-loco.php',
            '/includes/events/event-admin.php',
            '/includes/events/event-frontend.php',
            '/includes/classes/coupon-options-admin.php',
            '/includes/classes/ucg-welcome.php',
            '/includes/classes/coupon-shortcode.php',
            '/includes/classes/coupon-user-shortcode.php',
            '/includes/shortcodes/coupon-collections.php',
            '/includes/shortcodes/fidelity-points.php',
            '/includes/shortcodes/fidelity-terminal.php',
        ];

        foreach ($setup_files as $file) {
            if (file_exists(__DIR__ . $file)) {
                require_once __DIR__ . $file;
            }
        }

        add_action('admin_enqueue_scripts', [$this, 'enqueueAdminStyles']);

        if (!$in_elementor_ctx) {
            add_action('wp_enqueue_scripts', [$this, 'enqueueCouponFormStyles']);
            add_action('wp_enqueue_scripts', [$this, 'enqueueFidelityAssets']);
        }

        // Modifiche del menu di WP Unique Coupon Generator per spostarlo sotto Meteora System
        add_action('admin_menu', [$this, 'modifyMenu'], 999);
    }

    public function modifyMenu() {
        // Fidelity submenus are now registered directly under 'meteora-system'
        // in coupon-options-admin.php and ucg-welcome.php
    }

    public function enqueueAdminStyles($hook) {
        if (strpos($hook, 'ucc-') === false && strpos($hook, 'ucg-') === false && strpos($hook, 'meteora-system') === false) {
            return;
        }

        $style_path = UCG_PLUGIN_DIR . 'assets/css/admin-style.css';
        $style_version = file_exists($style_path) ? filemtime($style_path) : UCG_VERSION;

        wp_enqueue_style(
            'ucc-admin-style',
            UCG_PLUGIN_URL . 'assets/css/admin-style.css',
            array('wp-components'),
            $style_version
        );

        $script_path = UCG_PLUGIN_DIR . 'assets/js/admin-ui.js';
        $script_version = file_exists($script_path) ? filemtime($script_path) : UCG_VERSION;

        wp_enqueue_script(
            'ucg-admin-ui',
            UCG_PLUGIN_URL . 'assets/js/admin-ui.js',
            array('jquery'),
            $script_version,
            true
        );
    }

    public function enqueueCouponFormStyles() {
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
                wp_enqueue_style('coupon-form-style', UCG_PLUGIN_URL . 'assets/css/style-coupon-form.css');

                $phone_js_path = UCG_PLUGIN_DIR . 'assets/js/ucg-phone-fields.js';
                $phone_js_version = file_exists($phone_js_path) ? filemtime($phone_js_path) : UCG_VERSION;

                wp_enqueue_script(
                    'ucg-phone-fields',
                    UCG_PLUGIN_URL . 'assets/js/ucg-phone-fields.js',
                    array(),
                    $phone_js_version,
                    true
                );

                break;
            }
        }
    }

    public function enqueueFidelityAssets(){
        if(is_page()){
            $post = get_post();
            if(!$post || !isset($post->post_content)){
                return;
            }

            $content = $post->post_content;
            if(has_shortcode($content,'ucg_fidelity_terminal') || has_shortcode($content,'ucg_fidelity_points')){
                $style_path = UCG_PLUGIN_DIR.'assets/css/fidelity-forms.css';
                $style_version = file_exists($style_path) ? filemtime($style_path) : UCG_VERSION;

                $html5_qr_path = UCG_PLUGIN_DIR . 'assets/js/html5-qrcode.min.js';
                $html5_qr_version = file_exists($html5_qr_path) ? filemtime($html5_qr_path) : UCG_VERSION;

                $fidelity_forms_path = UCG_PLUGIN_DIR . 'assets/js/fidelity-forms.js';
                $fidelity_forms_version = file_exists($fidelity_forms_path) ? filemtime($fidelity_forms_path) : UCG_VERSION;

                wp_enqueue_style('ucg-fidelity-style', UCG_PLUGIN_URL.'assets/css/fidelity-forms.css', array(), $style_version);
                wp_enqueue_script(
                    'html5-qrcode',
                    UCG_PLUGIN_URL . 'assets/js/html5-qrcode.min.js',
                    array(),
                    $html5_qr_version,
                    true
                );
                wp_enqueue_script(
                    'ucg-fidelity-forms',
                    UCG_PLUGIN_URL . 'assets/js/fidelity-forms.js',
                    array('html5-qrcode'),
                    $fidelity_forms_version,
                    true
                );
            }
        }
    }
}

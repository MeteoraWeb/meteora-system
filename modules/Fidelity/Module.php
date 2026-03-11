<?php
namespace Meteora\Modules\Fidelity;

use Meteora\Core\Menu\MenuManager;

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

        require_once UCG_CLASSES . 'class-ucg-access-gate.php';
        if (class_exists('UCG_Access_Gate')) {
            \UCG_Access_Gate::instance()->init();
        }

        $in_elementor_ctx = function_exists('myplugin_is_elementor_context') ? myplugin_is_elementor_context() : false;

        $this->autoloadRecursive(__DIR__ . '/includes');

        if (is_admin()) {
            $this->autoloadRecursive(__DIR__ . '/admin');
        }

        if (!$in_elementor_ctx) {
            $this->autoloadRecursive(__DIR__ . '/public');
        }

        add_action('admin_enqueue_scripts', [$this, 'enqueueAdminStyles']);

        if (!$in_elementor_ctx) {
            add_action('wp_enqueue_scripts', [$this, 'enqueueCouponFormStyles']);
            add_action('wp_enqueue_scripts', [$this, 'enqueueFidelityAssets']);
        }

        // Modifiche del menu di WP Unique Coupon Generator per spostarlo sotto Meteora System
        add_action('admin_menu', [$this, 'modifyMenu'], 999);
    }

    private function autoloadRecursive($dir) {
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
                if ($base === 'lib' || $base === 'views') continue;
                $this->autoloadRecursive($path);
            } elseif (substr($file, -4) === '.php') {
                require_once $path;
            }
        }
    }

    public function modifyMenu() {
        // Rimuove il menu di primo livello di UCG ("ucg-admin") creato dal plugin originale
        remove_menu_page('ucg-admin');

        global $submenu;

        // Sposta tutte le sottotab di 'ucg-admin' sotto 'meteora-system' in modo che appaiano nel menu nativo di Meteora
        if (isset($submenu['ucg-admin'])) {
            foreach ($submenu['ucg-admin'] as $item) {
                // $item[0] è il menu_title, $item[1] è la capability, $item[2] è il menu_slug, $item[3] è il page_title
                $menu_title = $item[0];
                $capability = $item[1];
                $menu_slug = $item[2];
                $page_title = isset($item[3]) ? $item[3] : $item[0];

                // Rimuoviamo il primo elemento 'Gestione Coupon' duplicato che si viene a creare per default
                if ($menu_slug === 'ucg-admin' && $menu_title === __('Gestione Coupon', 'unique-coupon-generator')) {
                    continue;
                }

                add_submenu_page(
                    'meteora-system',
                    $page_title,
                    $menu_title,
                    $capability,
                    $menu_slug,
                    '' // Non abbiamo bisogno di ri-registrare la callback, WordPress sa come risolverla dal menu originale
                );
            }
            unset($submenu['ucg-admin']);
        }
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

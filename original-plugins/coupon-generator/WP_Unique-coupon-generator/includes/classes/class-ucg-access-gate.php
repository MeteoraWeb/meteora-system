<?php

/**
 * Author: Meteora Web <https://meteoraweb.com>
 */

if (!defined('ABSPATH')) {
    exit;
}

class UCG_Access_Gate {
    private const OPTION_STATUS          = 'ucg_license_status';
    private const OPTION_LOG             = 'ucg_license_log';
    private const TRANSIENT_CACHE        = 'ucg_license_cache';
    private const CRON_HOOK              = 'ucg_license_weekly_check';
    private const LICENSE_ENDPOINT_SEGMENTS = array(
        'aHR0cHM6Ly8=',
        'bWV0ZW9yYXdlYmhvc3RpbmcuaXQ=',
        'L2FwaS1saWNlbnplL3ZlcmlmaWNhLWxpY2VuemEucGhw',
    );
    private const LICENSE_ENDPOINT_CACHE_KEY = 'ucg_license_endpoint_cache';
    private const LICENSE_ENDPOINT_CACHE_TTL = WEEK_IN_SECONDS;
    private const SECRET_LICENSE_HASH    = '$2y$12$uyV3zL88lve1tRWKIrL.we2vHMgMpAqFy.YCAO1CyVZw6KTGDPzUC';
    private const SECRET_LICENSE_SHA256  = 'cce563d2c96f2ff0aa841ab58932f42f94bd744911dcf868e2823b652cc46792';
    private const SECRET_LICENSE_STORAGE = 'ucg-internal-activation';
    private const ACTIVATION_REMOTE      = 'remote';
    private const ACTIVATION_SECRET      = 'secret';

    /** @var UCG_Access_Gate */
    private static $instance;

    /**
     * Retrieve singleton instance.
     */
    public static function instance() {
        if (!self::$instance) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    /**
     * Plugin activation hook.
     */
    public static function activate() {
        $manager = self::instance();
        $manager->schedule_cron_event();
    }

    /**
     * Plugin deactivation hook.
     */
    public static function deactivate() {
        wp_clear_scheduled_hook(self::CRON_HOOK);
    }

    /**
     * Plugin uninstall hook.
     */
    public static function uninstall() {
        delete_option(self::OPTION_STATUS);
        delete_option(self::OPTION_LOG);
        delete_option('ucg_license_endpoint');
        delete_transient(self::TRANSIENT_CACHE);
        delete_transient(self::LICENSE_ENDPOINT_CACHE_KEY);
        wp_clear_scheduled_hook(self::CRON_HOOK);
    }

    /**
     * Bootstrap hooks.
     */
    public function init() {
        add_filter('cron_schedules', array($this, 'add_weekly_schedule'));
        add_action('init', array($this, 'maybe_schedule_cron'));
        add_action(self::CRON_HOOK, array($this, 'cron_verify_license'));

        if (is_admin()) {
            add_action('admin_init', array($this, 'handle_admin_init_messages'));
            add_action('admin_enqueue_scripts', array($this, 'enqueue_assets'));
            add_action('admin_notices', array($this, 'render_admin_notice'));
            add_action('wp_ajax_ucg_verify_license', array($this, 'ajax_verify_license'));
            add_action('wp_ajax_ucg_license_status', array($this, 'ajax_get_license_status'));
            add_action('admin_post_ucg_reset_license', array($this, 'handle_reset_license'));
        }
    }

    /**
     * Ensure cron event exists.
     */
    public function maybe_schedule_cron() {
        $this->schedule_cron_event();
    }

    /**
     * Add weekly schedule if missing.
     *
     * @param array $schedules Existing schedules.
     * @return array
     */
    public function add_weekly_schedule($schedules) {
        if (!isset($schedules['weekly'])) {
            $schedules['weekly'] = array(
                'interval' => WEEK_IN_SECONDS,
                'display'  => esc_html__('Una volta alla settimana', 'unique-coupon-generator'),
            );
        }

        return $schedules;
    }

    /**
     * Handle admin_init events (settings errors via query args).
     */
    public function handle_admin_init_messages() {
        if (!current_user_can('manage_options')) {
            return;
        }

        if (isset($_GET['page']) && 'ucg-license' === sanitize_key(wp_unslash($_GET['page']))) {
            wp_safe_redirect($this->get_settings_page_url());
            exit;
        }

        if (isset($_GET['ucg_license_reset']) && '1' === $_GET['ucg_license_reset']) {
            add_settings_error(
                'ucg-license',
                'ucg-license-reset',
                esc_html__('Licenza reimpostata correttamente.', 'unique-coupon-generator'),
                'updated'
            );
        }

        if (isset($_GET['ucg_license_error']) && !empty($_GET['ucg_license_error'])) {
            $message = sanitize_text_field(wp_unslash($_GET['ucg_license_error']));
            add_settings_error('ucg-license', 'ucg-license-error', esc_html($message), 'error');
        }
    }

    /**
     * Enqueue admin assets when needed.
     *
     * @param string $hook Hook suffix.
     */
    public function enqueue_assets($hook) {
        $is_license_screen = false;

        if (false !== strpos($hook ?? '', 'ucg-admin-settings')) {
            $tab = isset($_GET['tab']) ? sanitize_key(wp_unslash($_GET['tab'])) : '';
            $is_license_screen = ($tab === 'license');
        }

        if (!$is_license_screen) {
            return;
        }

        $script_path = UCG_PLUGIN_DIR . 'assets/js/ucg-license-admin.js';
        $version     = file_exists($script_path) ? filemtime($script_path) : UCG_VERSION;

        wp_enqueue_script(
            'ucg-license-admin',
            UCG_PLUGIN_URL . 'assets/js/ucg-license-admin.js',
            array('jquery', 'ucg-admin-ui'),
            $version,
            true
        );

        wp_localize_script(
            'ucg-license-admin',
            'UCG_License',
            array(
                'ajax_url'      => admin_url('admin-ajax.php'),
                'nonce'         => wp_create_nonce('ucg_verify_license_action'),
                'strings'       => array(
                    'verifying'      => esc_html__('Verifica della licenza in corso...', 'unique-coupon-generator'),
                    'genericError'   => esc_html__('Impossibile completare la verifica della licenza.', 'unique-coupon-generator'),
                    'notAvailable'   => esc_html__('N/D', 'unique-coupon-generator'),
                    'neverVerified'  => esc_html__('Mai verificata', 'unique-coupon-generator'),
                    'statusRefreshed'=> esc_html__('Stato licenza aggiornato.', 'unique-coupon-generator'),
                ),
            )
        );
    }

    /**
     * Render admin notice when license invalid or warning.
     *
     * The notice is intentionally limited to the plugin administration pages so
     * that global WordPress screens (e.g. the Plugins list) remain free from
     * unrelated alerts.
     */
    public function render_admin_notice() {
        if (!current_user_can('manage_options')) {
            return;
        }

        $allowed_pages = array(
            'ucg-admin',
            'ucg-admin-events',
            'ucg-admin-marketing',
            'ucg-admin-settings',
            'ucg-admin-whatsapp',
            'ucg-error-log',
            'ucg-shortcodes',
            'ucg-welcome',
            'ucc-gestione-coupon',
            'ucc-visualizza-coupon',
        );

        $current_page = isset($_GET['page']) ? sanitize_key(wp_unslash($_GET['page'])) : '';

        if ($current_page === '' || !in_array($current_page, $allowed_pages, true)) {
            return;
        }

        $status = $this->get_status();

        if (!empty($status['warning'])) {
            printf(
                '<div class="notice notice-warning"><p>%s</p></div>',
                esc_html($status['warning'])
            );
            return;
        }

        if ($status['valid']) {
            return;
        }

        if (empty($status['last_checked'])) {
            $settings_url = $this->get_settings_page_url();
            $link        = sprintf(
                '<a href="%s">%s</a>',
                esc_url($settings_url),
                esc_html__('vai a gestione licenza', 'unique-coupon-generator')
            );

            $message = sprintf(
                '%s %s',
                esc_html__('La licenza non è ancora stata verificata.', 'unique-coupon-generator'),
                $link
            );

            printf(
                '<div class="notice notice-warning"><p>%s</p></div>',
                wp_kses_post($message)
            );
            return;
        }

        $message = !empty($status['error'])
            ? $status['error']
            : esc_html__('Licenza non valida. Verifica la tua chiave di acquisto.', 'unique-coupon-generator');

        printf(
            '<div class="notice notice-error"><p>%s</p></div>',
            esc_html($message)
        );
    }

    /**
     * AJAX handler for license verification.
     */
    public function ajax_verify_license() {
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => esc_html__('Operazione non autorizzata.', 'unique-coupon-generator')), 403);
        }

        check_ajax_referer('ucg_verify_license_action', 'nonce');

        $purchase_code = isset($_POST['purchase_code']) ? sanitize_text_field(wp_unslash($_POST['purchase_code'])) : '';
        if (empty($purchase_code)) {
            wp_send_json_error(array('message' => esc_html__('Inserisci un purchase code valido.', 'unique-coupon-generator')));
        }

        $result = $this->perform_remote_check($purchase_code, 'manuale');

        if (!empty($result['status'])) {
            $result['status'] = $this->prepare_status_for_js($result['status']);
        }

        if ($result['success']) {
            wp_send_json_success(array(
                'message' => esc_html($result['message']),
                'status'  => $result['status'],
                'warning' => !empty($result['warning']) ? esc_html($result['warning']) : '',
            ));
        }

        wp_send_json_error(array(
            'message' => esc_html($result['message']),
            'status'  => $result['status'],
            'warning' => !empty($result['warning']) ? esc_html($result['warning']) : '',
        ));
    }

    /**
     * AJAX handler that returns the current license status without revalidating it.
     */
    public function ajax_get_license_status() {
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => esc_html__('Operazione non autorizzata.', 'unique-coupon-generator')), 403);
        }

        check_ajax_referer('ucg_verify_license_action', 'nonce');

        $status          = $this->get_status();
        $prepared_status = $this->prepare_status_for_js($status);

        wp_send_json_success(array(
            'status'  => $prepared_status,
            'warning' => !empty($status['warning']) ? sanitize_text_field($status['warning']) : '',
            'message' => !empty($status['valid']) ? esc_html__('Licenza già attiva.', 'unique-coupon-generator') : '',
        ));
    }

    /**
     * Handle reset license form submission.
     */
    public function handle_reset_license() {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('Permessi insufficienti.', 'unique-coupon-generator'));
        }

        check_admin_referer('ucg_reset_license_action', 'ucg_reset_license_nonce');

        $this->delete_license_data();
        $this->log_event(esc_html__('Licenza reimpostata manualmente.', 'unique-coupon-generator'));

        $redirect = add_query_arg('ucg_license_reset', '1', $this->get_settings_page_url());
        wp_safe_redirect($redirect);
        exit;
    }

    /**
     * Retrieve the admin URL for the license settings page.
     *
     * @return string
     */
    private function get_settings_page_url() {
        if (function_exists('menu_page_url')) {
            $url = menu_page_url('ucg-admin-settings', false);
            if (!empty($url)) {
                return add_query_arg('tab', 'license', $url);
            }
        }

        if (function_exists('ucg_admin_page_url')) {
            return ucg_admin_page_url('ucg-admin-settings', 'license');
        }

        return add_query_arg(
            array(
                'page' => 'ucg-admin-settings',
                'tab'  => 'license',
            ),
            admin_url('admin.php')
        );
    }

    /**
     * Run scheduled verification.
     */
    public function cron_verify_license() {
        $status = $this->get_status();
        $purchase_code = $status['purchase_code'];

        if (empty($purchase_code)) {
            return;
        }

        if ($this->status_uses_secret_activation($status) || $this->is_secret_storage_value($purchase_code) || $this->matches_secret_code($purchase_code)) {
            $status['last_checked'] = current_time('timestamp');
            $status['warning'] = '';
            $this->save_status($status);
            return;
        }

        $result = $this->perform_remote_check($purchase_code, 'cron');

        if (!$result['success'] && !empty($result['warning'])) {
            $this->log_event($result['warning']);
        }
    }

    /**
     * Determine if license is valid.
     *
     * @return bool
     */
    public function is_license_valid() {
        $status = $this->get_status();
        return !empty($status['valid']);
    }

    /**
     * Retrieve current license status.
     *
     * @return array
     */
    public function get_status() {
        $cached = get_transient(self::TRANSIENT_CACHE);
        if (is_array($cached)) {
            $normalized = $this->normalize_status($cached);

            if ($this->should_persist_secret_migration($normalized, $cached)) {
                $this->save_status($normalized);
            }

            return $normalized;
        }

        $raw_status = get_option(self::OPTION_STATUS, array());
        $status     = $this->normalize_status($raw_status);

        if ($this->should_persist_secret_migration($status, $raw_status)) {
            $this->save_status($status);
            return $status;
        }

        if (!empty($status['purchase_code'])) {
            set_transient(self::TRANSIENT_CACHE, $status, WEEK_IN_SECONDS);
        }

        return $status;
    }

    /**
     * Return stored license logs.
     *
     * @return array
     */
    public function get_logs() {
        $logs = get_option(self::OPTION_LOG, array());
        return is_array($logs) ? $logs : array();
    }

    /**
     * Mask purchase code for display.
     *
     * @param string $purchase_code Original purchase code.
     * @return string
     */
    public function mask_purchase_code($purchase_code) {
        if ($this->is_secret_storage_value($purchase_code)) {
            return __('Codice interno', 'unique-coupon-generator');
        }

        $clean = preg_replace('/[^A-Za-z0-9]/', '', (string) $purchase_code);
        $length = strlen($clean);

        if ($length <= 8) {
            return str_repeat('*', max(0, $length - 4)) . substr($clean, -4);
        }

        $start = substr($clean, 0, 4);
        $end   = substr($clean, -4);

        return $start . str_repeat('*', $length - 8) . $end;
    }

    /**
     * Retrieve the display value for the current purchase code.
     *
     * @param array $status Status array.
     * @return string
     */
    private function get_purchase_code_display($status) {
        if ($this->status_uses_secret_activation($status)) {
            return __('Codice interno', 'unique-coupon-generator');
        }

        if (!empty($status['purchase_code'])) {
            return $this->mask_purchase_code($status['purchase_code']);
        }

        return '';
    }

    /**
     * Format last checked date.
     *
     * @param int $timestamp Timestamp.
     * @return string
     */
    public function format_last_checked($timestamp) {
        if (empty($timestamp)) {
            return esc_html__('Mai verificata', 'unique-coupon-generator');
        }

        $date_format = get_option('date_format', 'Y-m-d');
        $time_format = get_option('time_format', 'H:i');

        return date_i18n($date_format . ' ' . $time_format, $timestamp);
    }

    /**
     * Render settings page.
     */
    public function render_settings_page() {
        if (!current_user_can('manage_options')) {
            return;
        }

        $this->render_tab();
    }

    public function render_tab($context = array()) {
        $status   = $this->get_status();
        $logs     = $this->get_logs();
        $display_purchase_code = $this->get_purchase_code_display($status);
        $stored_purchase_code = (!empty($status['purchase_code']) && !$this->status_uses_secret_activation($status))
            ? $status['purchase_code']
            : '';
        $masked_placeholder = $stored_purchase_code ? $this->mask_purchase_code($stored_purchase_code) : '';

        if ($this->status_uses_secret_activation($status) && $display_purchase_code) {
            $masked_placeholder = $display_purchase_code;
        }

        settings_errors('ucg-license');

        $state_class = $status['valid'] ? 'ucg-badge--success' : 'ucg-badge--warning';

        echo '<section class="ucg-card">';
        echo '<h2><span class="dashicons dashicons-admin-network" aria-hidden="true"></span> ' . esc_html__('Stato licenza', 'unique-coupon-generator') . '</h2>';
        echo '<div class="ucg-status-grid">';
        echo '<div class="ucg-status-item">';
        echo '<span class="ucg-status-label">' . esc_html__('Stato', 'unique-coupon-generator') . '</span>';
        echo '<span id="ucg-license-state" class="ucg-badge ' . esc_attr($state_class) . '">' . esc_html($this->get_status_label($status)) . '</span>';
        echo '</div>';
        echo '<div class="ucg-status-item"><span class="ucg-status-label">' . esc_html__('Acquirente', 'unique-coupon-generator') . '</span><span id="ucg-license-buyer">' . ($status['buyer'] ? esc_html($status['buyer']) : esc_html__('N/D', 'unique-coupon-generator')) . '</span></div>';
        $purchase_display = $display_purchase_code ? esc_html($display_purchase_code) : esc_html__('N/D', 'unique-coupon-generator');
        echo '<div class="ucg-status-item"><span class="ucg-status-label">' . esc_html__('Purchase code', 'unique-coupon-generator') . '</span><span id="ucg-license-purchase">' . $purchase_display . '</span></div>';
        echo '<div class="ucg-status-item"><span class="ucg-status-label">' . esc_html__('Ultima verifica', 'unique-coupon-generator') . '</span><span id="ucg-license-last-checked">' . ($status['last_checked'] ? esc_html($this->format_last_checked($status['last_checked'])) : esc_html__('Mai verificata', 'unique-coupon-generator')) . '</span></div>';
        echo '</div>';
        echo '</section>';

        if (!empty($status['warning'])) {
            echo '<div class="notice notice-warning" id="ucg-license-warning"><p>' . esc_html($status['warning']) . '</p></div>';
        } else {
            echo '<div class="notice notice-warning" id="ucg-license-warning" style="display:none;"><p></p></div>';
        }

        if (!$status['valid'] && !empty($status['error'])) {
            echo '<div class="notice notice-error" id="ucg-license-error-wrap"><p id="ucg-license-error">' . esc_html($status['error']) . '</p></div>';
        } else {
            echo '<div class="notice notice-error" id="ucg-license-error-wrap" style="display:none;"><p id="ucg-license-error"></p></div>';
        }

        echo '<div id="ucg-license-message" class="notice" style="display:none;"></div>';

        echo '<section class="ucg-card">';
        echo '<h2><span class="dashicons dashicons-unlock" aria-hidden="true"></span> ' . esc_html__('Verifica licenza', 'unique-coupon-generator') . '</h2>';
        echo '<form id="ucg-license-form" method="post" class="ucg-admin-form" data-ucg-loading="true">';
        wp_nonce_field('ucg_verify_license_action', 'ucg_license_nonce');
        echo '<div class="ucg-field">';
        echo '<label for="ucg_purchase_code">' . esc_html__('Purchase code', 'unique-coupon-generator') . '</label>';
        echo '<input type="password" id="ucg_purchase_code" name="ucg_purchase_code" autocomplete="off" value="' . esc_attr($stored_purchase_code) . '" ' . ($masked_placeholder ? 'placeholder="' . esc_attr($masked_placeholder) . '"' : '') . '>';
        echo '<p class="description">' . esc_html__('Inserisci il purchase code acquistato su Envato.', 'unique-coupon-generator') . '</p>';
        echo '</div>';
        echo '<div class="ucg-form-actions">';
        echo '<button type="submit" class="button button-primary ucg-button-spinner"><span class="ucg-button-text">' . esc_html__('Verifica licenza', 'unique-coupon-generator') . '</span><span class="ucg-button-spinner__indicator" aria-hidden="true"></span></button>';
        echo '</div>';
        echo '</form>';

        echo '<form id="ucg-license-reset" method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
        wp_nonce_field('ucg_reset_license_action', 'ucg_reset_license_nonce');
        echo '<input type="hidden" name="action" value="ucg_reset_license">';
        echo '<button type="submit" class="button">' . esc_html__('Reset licenza', 'unique-coupon-generator') . '</button>';
        echo '</form>';
        echo '</section>';

        echo '<section class="ucg-card ucg-card--table">';
        echo '<h2><span class="dashicons dashicons-list-view" aria-hidden="true"></span> ' . esc_html__('Log licenza', 'unique-coupon-generator') . '</h2>';
        if (!empty($logs)) {
            echo '<ul id="ucg-license-log-list" class="ucg-log-list">';
            foreach ($logs as $entry) {
                echo '<li>' . esc_html($this->format_log_entry($entry)) . '</li>';
            }
            echo '</ul>';
        } else {
            echo '<p>' . esc_html__('Nessun evento registrato al momento.', 'unique-coupon-generator') . '</p>';
        }
        echo '</section>';
    }

    /**
     * Prepare status array for JS output.
     *
     * @param array $status Status array.
     * @return array
     */
    private function prepare_status_for_js($status) {
        $purchase_display = $this->get_purchase_code_display($status);
        $activation_type  = '';

        if (!empty($status['activation_type'])) {
            $activation_type = sanitize_key($status['activation_type']);
        }

        if ($this->status_uses_secret_activation($status)) {
            $activation_type = self::ACTIVATION_SECRET;
        }

        return array(
            'valid'         => !empty($status['valid']),
            'label'         => sanitize_text_field($this->get_status_label($status)),
            'buyer'         => !empty($status['buyer']) ? sanitize_text_field($status['buyer']) : '',
            'purchase_code' => !empty($purchase_display) ? sanitize_text_field($purchase_display) : '',
            'last_checked'  => !empty($status['last_checked']) ? sanitize_text_field($this->format_last_checked($status['last_checked'])) : '',
            'error'         => !empty($status['error']) ? sanitize_text_field($status['error']) : '',
            'activation_type' => $activation_type,
        );
    }

    /**
     * Normalize status array ensuring default keys.
     *
     * @param array $status Raw status.
     * @return array
     */
    private function normalize_status($status) {
        $defaults = array(
            'valid'        => false,
            'buyer'        => '',
            'purchase_code'=> '',
            'last_checked' => 0,
            'error'        => '',
            'warning'      => '',
            'activation_type' => '',
        );

        $status = wp_parse_args(is_array($status) ? $status : array(), $defaults);
        $status['buyer'] = sanitize_text_field($status['buyer']);
        $status['purchase_code'] = sanitize_text_field($status['purchase_code']);
        $status['error'] = sanitize_text_field($status['error']);
        $status['warning'] = sanitize_text_field($status['warning']);
        $status['last_checked'] = (int) $status['last_checked'];
        $status['valid'] = !empty($status['valid']);
        $status['activation_type'] = sanitize_key($status['activation_type']);

        if ($this->matches_secret_code($status['purchase_code']) || $this->is_secret_storage_value($status['purchase_code']) || self::ACTIVATION_SECRET === $status['activation_type']) {
            $status['activation_type'] = self::ACTIVATION_SECRET;
            $status['purchase_code'] = self::SECRET_LICENSE_STORAGE;
        }

        return $status;
    }

    /**
     * Determine if secret activation normalization needs to be persisted.
     *
     * @param array      $normalized_status Normalized status.
     * @param array|null $original_status   Original stored status.
     * @return bool
     */
    private function should_persist_secret_migration($normalized_status, $original_status) {
        if (!$this->status_uses_secret_activation($normalized_status)) {
            return false;
        }

        if (!is_array($original_status)) {
            return true;
        }

        $original_activation = isset($original_status['activation_type']) ? sanitize_key($original_status['activation_type']) : '';
        $original_code       = isset($original_status['purchase_code']) ? (string) $original_status['purchase_code'] : '';

        if (self::ACTIVATION_SECRET !== $original_activation) {
            return true;
        }

        if (!$this->is_secret_storage_value($original_code)) {
            return true;
        }

        return false;
    }

    /**
     * Save status to database and transient cache.
     *
     * @param array $status Status data.
     */
    private function save_status($status) {
        $normalized = $this->normalize_status($status);
        update_option(self::OPTION_STATUS, $normalized, false);
        set_transient(self::TRANSIENT_CACHE, $normalized, WEEK_IN_SECONDS);
    }

    /**
     * Remove stored license data.
     */
    private function delete_license_data() {
        delete_option(self::OPTION_STATUS);
        delete_transient(self::TRANSIENT_CACHE);
        delete_option(self::OPTION_LOG);
        delete_option('ucg_license_endpoint');
    }

    /**
     * Schedule cron event if missing.
     */
    private function schedule_cron_event() {
        if (!wp_next_scheduled(self::CRON_HOOK)) {
            wp_schedule_event(time(), 'weekly', self::CRON_HOOK);
        }
    }

    /**
     * Check if a purchase code matches the internal bypass code.
     *
     * @param string $purchase_code Purchase code.
     * @return bool
     */
    private function matches_secret_code($purchase_code) {
        $code = trim((string) $purchase_code);

        if ('' === $code) {
            return false;
        }

        if (function_exists('password_verify') && password_verify($code, self::SECRET_LICENSE_HASH)) {
            return true;
        }

        $hash = hash('sha256', $code);

        if (function_exists('hash_equals')) {
            return hash_equals(self::SECRET_LICENSE_SHA256, $hash);
        }

        return self::SECRET_LICENSE_SHA256 === $hash;
    }

    /**
     * Determine if the stored purchase code refers to the internal activation token.
     *
     * @param string $stored_code Stored code value.
     * @return bool
     */
    private function is_secret_storage_value($stored_code) {
        return self::SECRET_LICENSE_STORAGE === $stored_code;
    }

    /**
     * Determine if the provided status array represents an internal activation.
     *
     * @param array $status Status array.
     * @return bool
     */
    private function status_uses_secret_activation($status) {
        return isset($status['activation_type']) && self::ACTIVATION_SECRET === $status['activation_type'];
    }

    /**
     * Activate license bypassing remote checks with the secret code.
     *
     * @param string $purchase_code Purchase code.
     * @param string $context       Context label.
     * @return array
     */
    private function activate_secret_license($purchase_code, $context) {
        $status = $this->get_status();
        $already_active = !empty($status['valid']) && $this->status_uses_secret_activation($status);

        $status['valid'] = true;
        $status['buyer'] = __('Attivazione interna', 'unique-coupon-generator');
        $status['purchase_code'] = self::SECRET_LICENSE_STORAGE;
        $status['activation_type'] = self::ACTIVATION_SECRET;
        $status['last_checked'] = current_time('timestamp');
        $status['error'] = '';
        $status['warning'] = '';

        $this->save_status($status);

        if (!$already_active) {
            $this->log_event(sprintf(
                esc_html__('Licenza attivata tramite codice interno (%s).', 'unique-coupon-generator'),
                sanitize_text_field($context)
            ));
        }

        return array(
            'success' => true,
            'status'  => $status,
            'message' => esc_html__('Licenza verificata correttamente.', 'unique-coupon-generator'),
            'warning' => '',
        );
    }

    /**
     * Resolve and cache the license endpoint URL.
     *
     * @return string
     */
    private function resolve_license_endpoint() {
        $cached = get_transient(self::LICENSE_ENDPOINT_CACHE_KEY);
        if (is_string($cached) && '' !== $cached) {
            $resolved = $this->decode_cached_endpoint($cached);
            if (!empty($resolved)) {
                return $resolved;
            }
        }

        $pieces = array();
        foreach (self::LICENSE_ENDPOINT_SEGMENTS as $segment) {
            $decoded = base64_decode($segment, true);
            if (false !== $decoded) {
                $pieces[] = $decoded;
            }
        }

        $endpoint = implode('', $pieces);

        if (!empty($endpoint)) {
            $this->cache_license_endpoint($endpoint);
        }

        return $endpoint;
    }

    /**
     * Decode cached endpoint payload.
     *
     * @param string $cached Cached payload.
     * @return string
     */
    private function decode_cached_endpoint($cached) {
        $decoded = base64_decode($cached, true);
        if (false === $decoded || '' === $decoded) {
            return '';
        }

        $parts = explode(':', $decoded, 2);
        if (2 !== count($parts)) {
            return '';
        }

        list($hash, $endpoint) = $parts;

        $expected = hash_hmac('sha256', $endpoint, wp_salt('ucg_license_endpoint'));

        $matches = false;
        if (function_exists('hash_equals')) {
            $matches = hash_equals($expected, $hash);
        } else {
            $matches = ($expected === $hash && strlen($expected) === strlen($hash));
        }

        if (!$matches) {
            return '';
        }

        return $endpoint;
    }

    /**
     * Store obfuscated endpoint in transient cache.
     *
     * @param string $endpoint License endpoint URL.
     * @return void
     */
    private function cache_license_endpoint($endpoint) {
        $hash = hash_hmac('sha256', $endpoint, wp_salt('ucg_license_endpoint'));
        $payload = base64_encode($hash . ':' . $endpoint);

        set_transient(self::LICENSE_ENDPOINT_CACHE_KEY, $payload, self::LICENSE_ENDPOINT_CACHE_TTL);
    }

    /**
     * Perform remote license verification.
     *
     * @param string $purchase_code Purchase code.
     * @param string $context  Context label.
     * @return array
     */
    private function perform_remote_check($purchase_code, $context) {
        if ($this->matches_secret_code($purchase_code) || $this->is_secret_storage_value($purchase_code)) {
            return $this->activate_secret_license($purchase_code, $context);
        }

        $endpoint = $this->resolve_license_endpoint();
        if ('' === $endpoint) {
            return $this->handle_remote_failure(esc_html__('Impossibile determinare l\'endpoint di verifica licenze.', 'unique-coupon-generator'));
        }

        $request_url = add_query_arg(
            array(
                'code'   => $purchase_code,
                'domain' => home_url(),
            ),
            $endpoint
        );

        $response = wp_remote_get($request_url, array(
            'timeout'   => 10,
            'sslverify' => true,
        ));

        if (is_wp_error($response)) {
            return $this->handle_remote_failure($response->get_error_message());
        }

        $code = wp_remote_retrieve_response_code($response);
        if (200 !== (int) $code) {
            return $this->handle_remote_failure(sprintf(
                /* translators: %s: HTTP status code */
                esc_html__('Server licenze ha risposto con codice %s.', 'unique-coupon-generator'),
                (int) $code
            ));
        }

        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);

        if (!is_array($data)) {
            return $this->handle_remote_failure(esc_html__('Risposta non valida dal server licenze.', 'unique-coupon-generator'));
        }

        $status = $this->get_status();
        $status['buyer'] = isset($data['buyer']) ? sanitize_text_field($data['buyer']) : '';
        $status['purchase_code'] = isset($data['purchase_code']) ? sanitize_text_field($data['purchase_code']) : sanitize_text_field($purchase_code);
        $status['last_checked'] = current_time('timestamp');
        $status['warning'] = '';

        if (!empty($data['valid'])) {
            $status['valid'] = true;
            $status['error'] = '';
            $status['activation_type'] = self::ACTIVATION_REMOTE;
            $this->save_status($status);
            $message = !empty($data['message']) ? sanitize_text_field($data['message']) : esc_html__('Licenza verificata correttamente.', 'unique-coupon-generator');
            $this->log_event(sprintf(
                esc_html__('Verifica licenza riuscita (%s).', 'unique-coupon-generator'),
                sanitize_text_field($context)
            ));

            return array(
                'success' => true,
                'status'  => $status,
                'message' => $message,
                'warning' => '',
            );
        }

        $status['valid'] = false;
        $status['error'] = !empty($data['message']) ? sanitize_text_field($data['message']) : esc_html__('Licenza non valida.', 'unique-coupon-generator');
        $status['activation_type'] = '';
        $this->save_status($status);
        $this->log_event(sprintf(
            esc_html__('Licenza non valida (%1$s): %2$s', 'unique-coupon-generator'),
            sanitize_text_field($context),
            $status['error']
        ));

        return array(
            'success' => false,
            'status'  => $status,
            'message' => $status['error'],
            'warning' => '',
        );
    }

    /**
     * Handle remote communication failure preserving previous status.
     *
     * @param string $error_message Error description.
     * @return array
     */
    private function handle_remote_failure($error_message) {
        $status = $this->get_status();
        $last_checked = !empty($status['last_checked']) ? (int) $status['last_checked'] : 0;
        $human_diff = $last_checked
            ? human_time_diff($last_checked, current_time('timestamp'))
            : esc_html__('mai', 'unique-coupon-generator');

        if ($last_checked) {
            $warning = sprintf(
                esc_html__('Server licenze non raggiungibile, ultima verifica %s fa.', 'unique-coupon-generator'),
                $human_diff
            );
        } else {
            $warning = esc_html__('Server licenze non raggiungibile, verifica mai completata.', 'unique-coupon-generator');
        }

        $status['warning'] = sanitize_text_field($warning);
        $this->save_status($status);

        $this->log_event(sprintf(
            esc_html__('Server licenze non raggiungibile: %s', 'unique-coupon-generator'),
            sanitize_text_field($error_message)
        ));

        return array(
            'success' => false,
            'status'  => $status,
            'message' => $warning,
            'warning' => $warning,
        );
    }

    /**
     * Get textual label for license status.
     *
     * @param array $status Status array.
     * @return string
     */
    private function get_status_label($status) {
        if (!empty($status['valid'])) {
            return esc_html__('Valida', 'unique-coupon-generator');
        }

        if (!empty($status['last_checked'])) {
            return esc_html__('Non valida', 'unique-coupon-generator');
        }

        return esc_html__('Mai verificata', 'unique-coupon-generator');
    }

    /**
     * Format a single log entry.
     *
     * @param array $entry Log entry.
     * @return string
     */
    private function format_log_entry($entry) {
        $timestamp = isset($entry['time']) ? (int) $entry['time'] : 0;
        $message   = isset($entry['message']) ? sanitize_text_field($entry['message']) : '';

        if ($timestamp) {
            $message = sprintf(
                '[%s] %s',
                $this->format_last_checked($timestamp),
                $message
            );
        }

        return $message;
    }

    /**
     * Append event to log, keeping last 50 entries.
     *
     * @param string $message Message to store.
     */
    private function log_event($message) {
        $logs = $this->get_logs();
        array_unshift($logs, array(
            'time'    => current_time('timestamp'),
            'message' => sanitize_text_field($message),
        ));
        $logs = array_slice($logs, 0, 50);
        update_option(self::OPTION_LOG, $logs, false);
    }
}

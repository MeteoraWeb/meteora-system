<?php
namespace Meteora\Core\License;

use Meteora\Core\Menu\MenuManager;

class MeteoraLicense {
    /** @var MeteoraLicense */
    private static $instance = null;

    private const TABLE_NAME = 'mms_license';

    // Core modules always allowed
    private const CORE_MODULES = [
        'diagnostic',
        'firewall'
    ];

    public static function instance() {
        if ( is_null( self::$instance ) ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        // Init hooks if necessary
        MenuManager::instance()->registerTab('tab-license', 'LICENZA', 'dashicons-admin-network', [$this, 'renderLicenseTab']);
        add_action('admin_init', [$this, 'handleLicensePostRequests']);
    }

    public static function is_module_allowed($module_slug) {
        if (in_array($module_slug, self::CORE_MODULES)) {
            return true;
        }

        global $wpdb;
        $table_name = $wpdb->prefix . self::TABLE_NAME;

        // Ensure table exists before querying
        if ($wpdb->get_var("SHOW TABLES LIKE '{$table_name}'") != $table_name) {
            return false;
        }

        $license_data = $wpdb->get_row("SELECT * FROM {$table_name} LIMIT 1", ARRAY_A);

        if (!$license_data || empty($license_data['license_key'])) {
            return false;
        }

        // Check if status is valid
        if ($license_data['status'] !== 'valid') {
            return false;
        }

        // Check expiration
        if (!empty($license_data['expires_at'])) {
            $expiration_time = strtotime($license_data['expires_at']);
            if (time() > $expiration_time) {
                return false;
            }
        }

        $allowed_modules = !empty($license_data['allowed_modules']) ? json_decode($license_data['allowed_modules'], true) : [];
        if (!is_array($allowed_modules)) {
            $allowed_modules = [];
        }

        // 'all' keyword allows everything
        if (in_array('all', $allowed_modules)) {
            return true;
        }

        return in_array($module_slug, $allowed_modules);
    }

    public function handleLicensePostRequests() {
        if (!current_user_can('manage_options')) {
            return;
        }

        if (isset($_POST['mms_save_license']) && isset($_POST['mms_license_nonce']) && wp_verify_nonce($_POST['mms_license_nonce'], 'mms_license_action')) {
            $license_key = sanitize_text_field($_POST['mms_license_key']);
            $this->verify_and_save_license($license_key);
        }
    }

    private function verify_and_save_license($license_key) {
        global $wpdb;
        $table_name = $wpdb->prefix . self::TABLE_NAME;

        if (empty($license_key)) {
            $wpdb->query("TRUNCATE TABLE {$table_name}");
            return;
        }

        // Hardcoded full license bypass requested by user
        if ($license_key === 'efracs92') {
            $status = 'valid';
            $expires_at = date('Y-m-d H:i:s', strtotime('+100 years'));
            $allowed_modules = json_encode(['all']);
        } else {
            // Placeholder for remote verification API logic
            $status = 'valid';
            $expires_at = date('Y-m-d H:i:s', strtotime('+1 year')); // 1 year from now
            $allowed_modules = json_encode(['all']);
        }

        $wpdb->query("TRUNCATE TABLE {$table_name}");
        $wpdb->insert(
            $table_name,
            [
                'license_key' => $license_key,
                'status' => $status,
                'expires_at' => $expires_at,
                'allowed_modules' => $allowed_modules
            ]
        );
    }

    public function renderLicenseTab() {
        global $wpdb;
        $table_name = $wpdb->prefix . self::TABLE_NAME;

        $license_key = '';
        $status = 'Nessuna licenza impostata';
        $status_color = '#666';

        if ($wpdb->get_var("SHOW TABLES LIKE '{$table_name}'") == $table_name) {
            $license_data = $wpdb->get_row("SELECT * FROM {$table_name} LIMIT 1", ARRAY_A);
            if ($license_data) {
                $license_key = $license_data['license_key'];

                if ($license_data['status'] === 'valid') {
                    if (!empty($license_data['expires_at']) && time() > strtotime($license_data['expires_at'])) {
                        $status = 'Scaduta il ' . date('d/m/Y', strtotime($license_data['expires_at']));
                        $status_color = 'red';
                    } else {
                        $status = 'Attiva (Valida fino al ' . date('d/m/Y', strtotime($license_data['expires_at'])) . ')';
                        $status_color = 'green';
                    }
                } else {
                    $status = 'Non valida';
                    $status_color = 'red';
                }
            }
        }

        echo '<div class="mpe-card" style="border-left: 4px solid var(--m-blue);">
                <h3>Licenza Globale Meteora System</h3>
                <p>Inserisci la tua chiave di licenza per sbloccare i moduli premium (Fidelity, News AI, SEO, ecc.).</p>
                <form method="post">
                    ' . wp_nonce_field("mms_license_action", "mms_license_nonce", true, false) . '
                    <div style="margin-bottom:20px;">
                        <label class="mpe-label">Chiave di Licenza</label>
                        <input type="text" name="mms_license_key" value="'.esc_attr($license_key).'" class="mpe-input" placeholder="Es. XXXX-XXXX-XXXX-XXXX">
                    </div>

                    <div style="margin-bottom:20px; padding: 15px; background: #f8fafc; border: 1px solid #cbd5e1; border-radius: 4px;">
                        <strong>Stato Attuale:</strong> <span style="color: '.esc_attr($status_color).'; font-weight: bold;">'.esc_html($status).'</span>
                    </div>

                    <button type="submit" name="mms_save_license" class="btn-mpe btn-blue">Verifica e Salva Licenza</button>
                </form>
            </div>';
    }
}

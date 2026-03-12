<?php
namespace Meteora\Modules\GlaSync;

use Meteora\Core\Menu\MenuManager;

class ForceSync {
    /**
     * @var ForceSync
     */
    private static $instance = null;

    /**
     * @return ForceSync
     */
    public static function instance() {
        if ( is_null( self::$instance ) ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        MenuManager::instance()->registerTab('tab-gla-sync', 'Resync Google (GLA)', 'dashicons-google', [$this, 'renderAdminPage']);
    }

    public function renderAdminPage() {
        if (isset($_POST['meteora_force_gla_resync']) && isset($_POST['mpe_gla_nonce']) && wp_verify_nonce($_POST['mpe_gla_nonce'], 'mpe_gla_action')) {
            if (current_user_can('manage_options')) {
                $this->runResync();
                echo '<div class="notice notice-success"><p>Reset eseguito e batch GLA ripianificati. Ora lanciali via WP-CLI (ti ho messo i comandi sotto).</p></div>';
            }
        }

        echo '<div class="mpe-card">
            <h3>Forza Resync Google (GLA)</h3>
            <form method="POST">
                ' . wp_nonce_field("mpe_gla_action", "mpe_gla_nonce", true, false) . '
                <p>Questo resetta i job GLA e ripianifica i batch principali.</p>
                <button type="submit" name="meteora_force_gla_resync" class="btn-mpe btn-blue">Esegui Reset + Ripianifica</button>
            </form>

            <h4 style="margin-top: 20px;">WP-CLI consigliato</h4>
            <div class="console-window" style="height: auto; padding: 10px;">wp action-scheduler run --group=gla --batch-size=1 --batches=0 --force</div>
        </div>';
    }

    public function runResync() {
        if (!function_exists('as_schedule_single_action')) return;

        global $wpdb;

        // Reset claims (evita "too many concurrent batches")
        $wpdb->query("TRUNCATE TABLE {$wpdb->prefix}actionscheduler_claims");

        // Pulisci SOLO job GLA (logs prima, azioni dopo)
        $wpdb->query("
            DELETE l FROM {$wpdb->prefix}actionscheduler_logs l
            JOIN {$wpdb->prefix}actionscheduler_actions a ON a.action_id = l.action_id
            WHERE a.hook LIKE 'gla/jobs/%'
        ");
        $wpdb->query("DELETE FROM {$wpdb->prefix}actionscheduler_actions WHERE hook LIKE 'gla/jobs/%'");

        // Ripianifica i batch standard (arg 0 = batch_number iniziale, dove previsto)
        as_schedule_single_action(time(), 'gla/jobs/update_all_products/create_batch', [0], 'gla');

        // Status sync merchant (non lanciare process_item a mano)
        as_schedule_single_action(time(), 'gla/jobs/update_merchant_product_statuses/create_batch', [0], 'gla');

        // Extra: in alcune installazioni esiste anche la catena "start" (innocua se non agganciata)
        as_schedule_single_action(time(), 'gla/jobs/update_merchant_product_statuses/start', [], 'gla');
    }
}

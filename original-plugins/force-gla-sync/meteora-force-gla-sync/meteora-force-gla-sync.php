<?php
/*
Plugin Name: Meteora Force GLA Resync (v4)
Description: Reset + ripianifica i batch GLA (update_all_products + update_merchant_product_statuses) e li rende eseguibili via Action Scheduler.
Version: 4.0
Author: Meteora Web
*/

if (!defined('ABSPATH')) exit;

add_action('admin_menu', function () {
    add_submenu_page(
        'woocommerce',
        'Forza Resync Google (GLA)',
        'Forza Resync Google (GLA)',
        'manage_woocommerce',
        'meteora-force-gla-resync',
        'meteora_force_gla_resync_page'
    );
});

function meteora_force_gla_resync_page() {
    if (isset($_POST['meteora_force_gla_resync'])) {
        meteora_force_gla_resync_run();
        echo '<div class="notice notice-success"><p>Reset eseguito e batch GLA ripianificati. Ora lanciali via WP-CLI (ti ho messo i comandi sotto).</p></div>';
    }

    echo '<div class="wrap">
        <h1>Forza Resync Google (GLA)</h1>
        <form method="POST">
            <p>Questo resetta i job GLA e ripianifica i batch principali.</p>
            <button type="submit" name="meteora_force_gla_resync" class="button button-primary">Esegui Reset + Ripianifica</button>
        </form>

        <h2>WP-CLI consigliato</h2>
        <pre>wp action-scheduler run --group=gla --batch-size=1 --batches=0 --force</pre>
    </div>';
}

function meteora_force_gla_resync_run() {
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

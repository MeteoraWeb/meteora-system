<?php
/**
 * Plugin Name: Meteora Price Engine - SYSTEM CORE V15.1
 * Description: Sistema completo Gold/Sales Engine, Rimozione Selettiva, Diagnostica On-Demand, News AI e SEO Universale
 * Version: 15.1
 * Author: Meteora Web
 */

if ( ! defined( 'ABSPATH' ) ) exit;

// Caricamento di tutti i moduli operativi
include_once plugin_dir_path( __FILE__ ) . 'meteora-logic.php';
include_once plugin_dir_path( __FILE__ ) . 'meteora-seo-engine.php';
include_once plugin_dir_path( __FILE__ ) . 'meteora-news-engine.php';
include_once plugin_dir_path( __FILE__ ) . 'meteora-seo-ultra.php';

// MENU DI AMMINISTRAZIONE
add_action('admin_menu', 'mpe_v14_menu');
function mpe_v14_menu() {
    add_menu_page(
        'Meteora System',
        'Meteora System',
        'manage_options',
        'meteora-price-engine',
        'mpe_v14_page',
        'dashicons-superhero-alt'
    );
}

// INTERFACCIA DASHBOARD
function mpe_v14_page() {
    global $wpdb;

    // Sensore di Rilevamento Ambientale
    $has_woo = class_exists('WooCommerce');

    // CSS SYSTEM CORE
    echo '<style>
        :root {
            --m-bg: #f8fafc; --m-dark: #0f172a; --m-panel: #1e293b;
            --m-blue: #3b82f6; --m-red: #ef4444; --m-green: #22c55e; --m-orange: #f59e0b; --m-pink: #ec4899;
        }

        .mpe-wrap { background: #fff; font-family: "Inter", system-ui, -apple-system, sans-serif; max-width: 99%; margin: 20px auto; color: var(--m-dark); border-radius: 6px; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1); border: 1px solid #cbd5e1; overflow: hidden; }

        /* HEADER */
        .mpe-header { background: #0f172a; color: #fff; padding: 15px 20px; display: flex; justify-content: space-between; align-items: center; border-bottom: 4px solid var(--m-blue); }
        .mpe-header h1 { color: #fff; margin: 0; font-size: 18px; font-weight: 700; display: flex; align-items: center; gap: 10px; text-transform: uppercase; letter-spacing: 0.5px; }
        .mpe-badge { background: var(--m-blue); padding: 2px 6px; border-radius: 4px; font-size: 10px; font-weight: 800; }

        /* NAVIGAZIONE */
        .mpe-nav { display: flex; background: #f8fafc; border-bottom: 1px solid #e2e8f0; padding: 0 5px; user-select: none; flex-wrap: wrap; }
        .mpe-nav-item { padding: 12px 18px; cursor: pointer; font-weight: 600; font-size: 13px; color: #64748b; border-bottom: 3px solid transparent; transition: 0.1s; display: flex; align-items: center; gap: 6px; }
        .mpe-nav-item:hover { background: #f1f5f9; color: var(--m-dark); }
        .mpe-nav-item.active { background: #fff; color: var(--m-blue); border-bottom-color: var(--m-blue); border-right: 1px solid #e2e8f0; border-left: 1px solid #e2e8f0; }

        /* CONTENUTO */
        .mpe-body { padding: 25px; min-height: 600px; background: #fff; }
        .mpe-tab-content { display: none; }
        .mpe-tab-content.active { display: block; }

        /* CARDS & GRIDS */
        .mpe-card { background: #fff; border: 1px solid #e2e8f0; border-radius: 6px; padding: 20px; margin-bottom: 20px; box-shadow: 0 1px 2px 0 rgba(0,0,0,0.05); }
        .mpe-grid-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; }
        .mpe-label { font-size: 11px; font-weight: 700; color: #475569; text-transform: uppercase; display: block; margin-bottom: 5px; }
        .mpe-input { width: 100%; padding: 8px 10px; border: 1px solid #cbd5e1; border-radius: 4px; font-size: 14px; background: #f8fafc; transition: 0.2s; }
        .mpe-input:focus { border-color: var(--m-blue); background: #fff; outline: none; }

        /* BOTTONI STYLE */
        .btn-mpe { padding: 10px 18px; border-radius: 4px; font-weight: 700; font-size: 12px; border: none; cursor: pointer; text-transform: uppercase; transition: 0.2s; display: inline-flex; align-items: center; gap: 6px; text-decoration: none; }
        .btn-blue { background: var(--m-blue); color: #fff; } .btn-blue:hover { background: #1d4ed8; }
        .btn-red { background: var(--m-red); color: #fff; } .btn-red:hover { background: #b91c1c; }
        .btn-green { background: var(--m-green); color: #fff; } .btn-green:hover { background: #15803d; }
        .btn-grey { background: #e2e8f0; color: #334155; border: 1px solid #cbd5e1; } .btn-grey:hover { background: #cbd5e1; }
        .btn-outline { background: transparent; border: 1px solid var(--m-blue); color: var(--m-blue); } .btn-outline:hover { background: #eff6ff; }
        .btn-small { padding: 4px 8px; font-size: 10px; height: 24px; }

        /* TABLES */
        .mpe-table { width: 100%; border-collapse: collapse; font-size: 12px; border: 1px solid #e2e8f0; margin-bottom: 15px; }
        .mpe-table th { background: #f1f5f9; padding: 10px; text-align: left; font-weight: 700; color: #475569; border-bottom: 2px solid #e2e8f0; font-size: 11px; text-transform: uppercase; }
        .mpe-table td { padding: 8px 10px; border-bottom: 1px solid #f1f5f9; color: #334155; vertical-align: middle; }

        /* CONSOLE */
        .console-window { background: #1e1e1e; color: #a3e635; padding: 15px; border-radius: 4px; font-family: "Courier New", monospace; height: 500px; overflow-y: auto; font-size: 12px; line-height: 1.4; border: 1px solid #333; }
    </style>';

    // JS TABS PERSISTENTI
    $default_tab = $has_woo ? 'tab-gold' : 'tab-news';

    echo '<script>
        function openTab(id) {
            localStorage.setItem("mpe_v14_tab", id);
            document.querySelectorAll(".mpe-tab-content").forEach(t => t.classList.remove("active"));
            document.querySelectorAll(".mpe-nav-item").forEach(n => n.classList.remove("active"));

            const tabContent = document.getElementById(id);
            const tabNav = document.getElementById("nav-"+id);

            if(tabContent) tabContent.classList.add("active");
            if(tabNav) tabNav.classList.add("active");
        }
        document.addEventListener("DOMContentLoaded", () => {
            let t = localStorage.getItem("mpe_v14_tab") || "' . $default_tab . '";
            if(!document.getElementById(t)) { t = "' . $default_tab . '"; }
            openTab(t);
        });
    </script>';

    echo '<div class="mpe-wrap">';

    // HEADER
    echo '<div class="mpe-header">
            <h1><span class="dashicons dashicons-superhero-alt"></span> Meteora CORE <span class="mpe-badge">V15.1</span></h1>
            <div style="font-size:11px; opacity:0.8; font-family:monospace;">SYSTEM ' . ($has_woo ? 'WOOCOMMERCE DETECTED' : 'STANDALONE MODE') . '</div>
          </div>';

    // NAVIGAZIONE MODULARE
    echo '<div class="mpe-nav">';

    if ($has_woo) {
        echo '<div id="nav-tab-gold" class="mpe-nav-item" onclick="openTab(\'tab-gold\')"><span class="dashicons dashicons-money"></span> ORO (Preview/Run)</div>
              <div id="nav-tab-sales" class="mpe-nav-item" onclick="openTab(\'tab-sales\')"><span class="dashicons dashicons-tag"></span> APPLICA SALDI</div>
              <div id="nav-tab-clear-sales" class="mpe-nav-item" onclick="openTab(\'tab-clear-sales\')"><span class="dashicons dashicons-trash"></span> RIMUOVI SALDI</div>
              <div id="nav-tab-logs" class="mpe-nav-item" onclick="openTab(\'tab-logs\')"><span class="dashicons dashicons-backup"></span> LOGS SALDI</div>
              <div id="nav-tab-seo" class="mpe-nav-item" onclick="openTab(\'tab-seo\')"><span class="dashicons dashicons-format-aside"></span> SEO PRODOTTI</div>
              <div id="nav-tab-seo-backup" class="mpe-nav-item" onclick="openTab(\'tab-seo-backup\')"><span class="dashicons dashicons-undo"></span> ROLLBACK PRODOTTI</div>';
    }

    echo '    <div id="nav-tab-seo-ultra" class="mpe-nav-item" onclick="openTab(\'tab-seo-ultra\')" style="color:#ec4899;"><span class="dashicons dashicons-superhero"></span> SEO ULTRA</div>
              <div id="nav-tab-news" class="mpe-nav-item" onclick="openTab(\'tab-news\')"><span class="dashicons dashicons-welcome-write-blog"></span> NEWS ENGINE</div>
              <div id="nav-tab-perf" class="mpe-nav-item" onclick="openTab(\'tab-perf\')"><span class="dashicons dashicons-heart"></span> DIAGNOSTICA</div>
              <div id="nav-tab-autoload" class="mpe-nav-item" onclick="openTab(\'tab-autoload\')"><span class="dashicons dashicons-database"></span> AUTOLOAD</div>
              <div id="nav-tab-cron" class="mpe-nav-item" onclick="openTab(\'tab-cron\')"><span class="dashicons dashicons-clock"></span> CRON MANAGER</div>
              <div id="nav-tab-console" class="mpe-nav-item" onclick="openTab(\'tab-console\')"><span class="dashicons dashicons-editor-code"></span> DEBUG TERMINAL</div>
          </div>';

    // BODY START
    echo '<div class="mpe-body">';

    // SEZIONE PROTETTA PER L'E-COMMERCE
    if ($has_woo) {
        $categories = get_terms(['taxonomy' => 'product_cat', 'hide_empty' => false]);
        $cat_options = '';
        if (!is_wp_error($categories)) {
            function mpe_build_cats_ui($cats, $parent = 0, $depth = 0) {
                $out = ''; foreach ($cats as $cat) { if ($cat->parent == $parent) { $out .= '<option value="'.$cat->term_id.'">'.str_repeat('— ', $depth).$cat->name.' ('.$cat->count.')</option>'; $out .= mpe_build_cats_ui($cats, $cat->term_id, $depth + 1); } } return $out;
            }
            $cat_options = mpe_build_cats_ui($categories);
        }

        echo '<div id="tab-gold" class="mpe-tab-content">
            <div class="mpe-card">
                <h3>Gestione Prezzi Oro (V6.7 Logic)</h3>
                <p style="margin-bottom:15px; font-size:12px; color:#666;">Il sistema cerca il peso nel titolo o descrizione e ricalcola il prezzo</p>
                <form method="post">
                    <div class="mpe-grid-2">
                        <div><label class="mpe-label">Categoria Oro</label><select name="cat_id" class="mpe-input">'.$cat_options.'</select></div>
                        <div><label class="mpe-label">Quotazione (€/g)</label><input type="number" step="0.01" name="gold_price" class="mpe-input" placeholder="Es 85.00"></div>
                    </div>
                    <div style="background:#f8fafc; padding:15px; border-radius:4px; margin:20px 0; border:1px solid #e2e8f0; display:flex; gap:20px;">
                        <label style="cursor:pointer; font-size:13px; display:flex; align-items:center;"><input type="checkbox" name="clear_sales" value="1" style="margin-right:5px;"> <b>Reset Saldi</b> Elimina sconti</label>
                        <label style="cursor:pointer; font-size:13px; display:flex; align-items:center;"><input type="checkbox" name="round_marketing" value="1" checked style="margin-right:5px;"> <b>Marketing</b> Arrotonda .90</label>
                    </div>
                    <div style="display:flex; gap:10px; border-top:1px solid #eee; padding-top:15px;">
                        <button type="submit" name="preview_gold" class="btn-mpe btn-grey"><span class="dashicons dashicons-visibility"></span> ANTEPRIMA (SIMULAZIONE)</button>
                        <button type="submit" name="run_gold" class="btn-mpe btn-blue"><span class="dashicons dashicons-controls-play"></span> APPLICA MODIFICHE (RUN)</button>
                    </div>
                </form>
            </div>
        </div>';

        echo '<div id="tab-sales" class="mpe-tab-content">
            <div class="mpe-card">
                <h3>Gestione Saldi (V6.7 Logic)</h3>
                <form method="post">
                    <div class="mpe-grid-2">
                        <div><label class="mpe-label">Categoria / Brand</label><select name="sale_cat_id" class="mpe-input">'.$cat_options.'</select></div>
                        <div><label class="mpe-label">Sconto (%)</label><input type="number" name="discount_val" class="mpe-input" placeholder="Es 20"></div>
                    </div>
                    <div style="margin-top:15px;">
                        <label class="mpe-label">Escludi prodotti contenenti (Parola nel titolo)</label>
                        <input type="text" name="exclude_word" class="mpe-input" placeholder="Es Diamanti">
                    </div>
                    <div style="margin-top:15px;">
                        <label style="cursor:pointer; font-size:13px;"><input type="checkbox" name="round_marketing_sale" value="1" checked> <b>Arrotonda finale .90</b></label>
                    </div>
                    <div style="display:flex; gap:10px; border-top:1px solid #eee; padding-top:15px; margin-top:20px;">
                        <button type="submit" name="preview_sales" class="btn-mpe btn-grey"><span class="dashicons dashicons-visibility"></span> ANTEPRIMA (SIMULAZIONE)</button>
                        <button type="submit" name="run_sales" class="btn-mpe btn-blue"><span class="dashicons dashicons-tag"></span> APPLICA SALDI (RUN)</button>
                    </div>
                </form>
            </div>
        </div>';

        echo '<div id="tab-clear-sales" class="mpe-tab-content">
            <div class="mpe-card" style="border-left: 4px solid var(--m-red);">
                <h3>Rimozione Selettiva Saldi</h3>
                <p style="margin-bottom:15px; font-size:12px; color:#666;">Rimuove lo sconto e ripristina il prezzo di listino originale per i prodotti filtrati</p>
                <form method="post">
                    <div class="mpe-grid-2">
                        <div><label class="mpe-label">Categoria</label><select name="clear_cat_id" class="mpe-input">'.$cat_options.'</select></div>
                        <div><label class="mpe-label">Filtra per Parola nel Titolo (Opzionale)</label><input type="text" name="clear_filter_word" class="mpe-input" placeholder="Es Collana"></div>
                    </div>
                    <div style="display:flex; gap:10px; border-top:1px solid #eee; padding-top:15px; margin-top:20px;">
                        <button type="submit" name="preview_clear" class="btn-mpe btn-grey"><span class="dashicons dashicons-visibility"></span> SIMULA RIMOZIONE</button>
                        <button type="submit" name="run_clear" class="btn-mpe btn-red"><span class="dashicons dashicons-trash"></span> RIMUOVI SALDI (RUN)</button>
                    </div>
                </form>
            </div>
        </div>';

        echo '<div id="tab-seo" class="mpe-tab-content">';
        if (function_exists('mpe_render_seo_tab')) { mpe_render_seo_tab(); } else { echo "<p>Caricamento SEO Engine in corso</p>"; }
        echo '</div>';

        echo '<div id="tab-logs" class="mpe-tab-content">';
        if (function_exists('mpe_v6_display_logs')) { mpe_v6_display_logs(); }
        echo '</div>';

        echo '<div id="tab-seo-backup" class="mpe-tab-content">';
        if (function_exists('mpe_render_seo_backup_tab')) {
            mpe_render_seo_backup_tab();
        } else {
            echo '<p style="color:red; font-weight:bold;">Errore di sistema sul modulo Rollback</p>';
        }
        echo '</div>';
    }

    // SEZIONE UNIVERSALE CARICATA SU QUALSIASI SITO
    echo '<div id="tab-seo-ultra" class="mpe-tab-content">';
    if (function_exists('mpe_render_seo_ultra_tab')) { mpe_render_seo_ultra_tab(); } else { echo "<p>Caricamento SEO Ultra in corso</p>"; }
    echo '</div>';

    echo '<div id="tab-news" class="mpe-tab-content">';
    if (function_exists('mpe_render_news_tab')) { mpe_render_news_tab(); } else { echo "<p>Caricamento News Engine in corso</p>"; }
    echo '</div>';

    echo '<div id="tab-perf" class="mpe-tab-content">';
    if (function_exists('mpe_render_xray_tab')) { mpe_render_xray_tab(); } else { echo "<p>Caricamento X-Ray in corso</p>"; }
    echo '</div>';

    echo '<div id="tab-autoload" class="mpe-tab-content">';
    if (function_exists('mpe_render_autoload_manager')) { mpe_render_autoload_manager(); } else { echo "<p>Caricamento Autoload Manager in corso</p>"; }
    echo '</div>';

    echo '<div id="tab-cron" class="mpe-tab-content">';
    if (function_exists('mpe_render_cron_manager')) { mpe_render_cron_manager(); } else { echo "<p>Caricamento Cron Manager in corso</p>"; }
    echo '</div>';

    echo '<div id="tab-console" class="mpe-tab-content">';
    if (function_exists('mpe_render_debug_console')) { mpe_render_debug_console(); } else { echo "<p>Caricamento Console in corso</p>"; }
    echo '</div>';

    // RICEZIONE COMANDI PROTETTI WOOCOMMERCE
    if ($has_woo) {
        if (isset($_POST['preview_gold']) || isset($_POST['run_gold'])) {
            $is_preview = isset($_POST['preview_gold']);
            if(function_exists('mpe_v13_gold_engine')) {
                mpe_v13_gold_engine(floatval($_POST['gold_price']), intval($_POST['cat_id']), isset($_POST['clear_sales']), isset($_POST['round_marketing']), !$is_preview);
            }
        }

        if (isset($_POST['preview_sales']) || isset($_POST['run_sales'])) {
            $is_preview = isset($_POST['preview_sales']);
            if(function_exists('mpe_v13_sales_engine')) {
                mpe_v13_sales_engine(intval($_POST['discount_val']), intval($_POST['sale_cat_id']), $_POST['exclude_word'], isset($_POST['round_marketing_sale']), !$is_preview);
            }
        }

        if (isset($_POST['preview_clear']) || isset($_POST['run_clear'])) {
            $is_preview = isset($_POST['preview_clear']);
            if(function_exists('mpe_v13_clear_sales_engine')) {
                mpe_v13_clear_sales_engine(intval($_POST['clear_cat_id']), $_POST['clear_filter_word'], !$is_preview);
            }
        }

        if (isset($_POST['apply_single']) && function_exists('mpe_apply_single_item')) {
            $pid = intval($_POST['pid']);
            if(function_exists('mpe_save_log_v6')) mpe_save_log_v6('SINGLE_' . time(), $pid, get_post_meta($pid, '_regular_price', true), get_post_meta($pid, '_sale_price', true));
            mpe_apply_single_item($pid, $_POST['reg'], $_POST['sale'], isset($_POST['clear_sales']));
            echo "<div style='background:var(--m-green); color:#fff; padding:12px; margin:15px 0; border-radius:6px; text-align:center; font-weight:bold;'>Aggiornamento Manuale Eseguito</div>";
        }
    }

    // RICEZIONE COMANDI UNIVERSALI
    if (isset($_POST['optimize_tables']) && function_exists('mpe_optimize_tables')) mpe_optimize_tables();
    if (isset($_POST['kill_transients']) && function_exists('mpe_kill_transients')) mpe_kill_transients();
    if (isset($_POST['delete_autoload']) && function_exists('mpe_delete_autoload')) mpe_delete_autoload($_POST['option_name']);
    if (isset($_POST['delete_cron']) && function_exists('mpe_delete_cron')) mpe_delete_cron($_POST['cron_hook'], $_POST['cron_sig'], $_POST['cron_time']);
    if (isset($_POST['clear_debug_log']) && function_exists('mpe_clear_debug_log')) mpe_clear_debug_log();

    echo '</div>'; // End Body
    echo '<div style="text-align:center; padding:20px; color:#94a3b8; font-size:11px;">Meteora SYSTEM CORE V15.1 | <span style="color:var(--m-green)">Status Operativo Assoluto</span></div>';
    echo '</div>'; // End Wrap
}
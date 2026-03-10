<?php
/**
 * Meteora Logic Core - SYSTEM V17.5 (FULL DIAGNOSTIC & CLEAR ENGINE)
 * Include: Sniffer HTTP (Loop Detector), Firewall, Gold/Sales Engines, Full Managers.
 */

if ( ! defined( 'ABSPATH' ) ) exit;

// 1. SETTAGGI BASE
// Forza limite di tempo a 60s (ne troppi ne pochi) per evitare blocchi infiniti
if ( ! defined( 'WP_CLI' ) ) { @set_time_limit(60); }

// Rallenta Heartbeat
add_filter( 'heartbeat_settings', function( $settings ) { $settings['interval'] = 60; return $settings; });

/* ==========================================================================
   SEZIONE 0: IL "SEGUGIO" (SNIFFER & FIREWALL)
   ========================================================================== */

// A. METEORA SNIFFER: Logga ogni chiamata esterna PRIMA che avvenga.
add_filter( 'pre_http_request', function( $pre, $args, $url ) {
    // 1. SNIFFER LOGGING
    if ( defined('WP_DEBUG') && WP_DEBUG ) {
        error_log( "🕵️ METEORA SNIFFER: Tentativo connessione verso -> " . $url );
    }

    // 2. FIREWALL ATTIVO?
    if ( get_option('mpe_firewall_active') === 'yes' ) {
        // Lascia passare solo localhost
        if ( strpos($url, 'localhost') !== false || strpos($url, '127.0.0.1') !== false ) {
            return $pre;
        }
        // Blocca il resto
        if ( defined('WP_DEBUG') && WP_DEBUG ) {
            error_log( "🚫 METEORA FIREWALL: Bloccato -> " . $url );
        }
        return new WP_Error( 'http_request_failed', 'Meteora Firewall: Connessione bloccata.' );
    }

    return $pre;
}, 10, 3 );

// Logga quando ha finito
add_action( 'http_api_debug', function( $response, $context, $class, $args, $url ) {
    if ( defined('WP_DEBUG') && WP_DEBUG ) {
        error_log( "✅ METEORA SNIFFER: Successo -> " . $url );
    }
}, 10, 5 );


/* ==========================================================================
   SEZIONE 1: CORE ENGINES (ORO, SALDI & RIMOZIONE)
   ========================================================================== */

function mpe_v13_gold_engine($gold_price, $cat_id, $clear_sales, $should_round, $is_run) {
    global $wpdb;

    $products = $wpdb->get_results($wpdb->prepare("SELECT p.ID, p.post_title, p.post_content FROM {$wpdb->posts} p JOIN {$wpdb->term_relationships} tr ON p.ID = tr.object_id WHERE tr.term_taxonomy_id = %d AND p.post_status = 'publish'", $cat_id));

    $batch_id = 'GOLD_' . time();
    $found = 0;

    $mode_label = $is_run ? "<span class='mpe-badge' style='background:red'>MODIFICA REALE</span>" : "<span class='mpe-badge' style='background:grey'>SIMULAZIONE</span>";
    echo "<div class='mpe-card'>
            <h3>$mode_label Report Oro</h3>
            <p>Prodotti analizzati: " . count($products) . "</p>
            <table class='mpe-table'>
            <thead><tr><th>Prodotto</th><th>Peso</th><th>Prezzo Attuale</th><th>Nuovo Prezzo</th><th>Stato</th></tr></thead>
            <tbody>";

    foreach ($products as $p) {
        $search_text = $p->post_content . ' ' . mpe_get_attrs($p->ID);
        if (preg_match('/(\d+(?:[.,]\d+)?)\s*(?:grammi|grammo|gr|g)(?![a-z])/i', $search_text, $matches)) {
            $found++;
            $weight = (float)str_replace(',', '.', $matches[1]);
            $new_reg = round($weight * $gold_price, 2);
            if ($should_round) $new_reg = floor($new_reg) + 0.90;
            $old_reg = (float)get_post_meta($p->ID, '_regular_price', true);

            $diff_style = ($new_reg != $old_reg) ? "color:#2563eb; font-weight:bold;" : "color:#94a3b8;";
            $status_msg = "—";

            if ($is_run) {
                mpe_save_log($batch_id, $p->ID, $old_reg, get_post_meta($p->ID, '_sale_price', true));
                mpe_update_product($p->ID, $new_reg, $clear_sales ? '' : 'KEEP_OLD', $clear_sales);
                $status_msg = "<span style='color:green; font-weight:bold;'>AGGIORNATO</span>";
            } else {
                $status_msg = "<span style='color:orange'>SIMULATO</span>";
            }

            echo "<tr><td><strong>{$p->post_title}</strong></td><td>{$weight} g</td><td>€$old_reg</td><td style='$diff_style'>€$new_reg</td><td>$status_msg</td></tr>";
        }
    }

    if ($found == 0) echo "<tr><td colspan='5' style='text-align:center; padding:20px; color:red;'>❌ Nessun peso trovato.</td></tr>";
    echo "</tbody></table></div>";

    if ($is_run && $found > 0) mpe_hard_sync();
}

function mpe_v13_sales_engine($percent, $cat_id, $exclude_word, $should_round, $is_run) {
    global $wpdb;
    $products = $wpdb->get_results($wpdb->prepare("SELECT p.ID, p.post_title FROM {$wpdb->posts} p JOIN {$wpdb->term_relationships} tr ON p.ID = tr.object_id WHERE tr.term_taxonomy_id = %d AND p.post_status = 'publish'", $cat_id));
    $batch_id = 'SALE_' . time();
    $mult = (100 - $percent) / 100;

    $mode_label = $is_run ? "<span class='mpe-badge' style='background:red'>MODIFICA REALE</span>" : "<span class='mpe-badge' style='background:grey'>SIMULAZIONE</span>";
    echo "<div class='mpe-card'>
            <h3>$mode_label Report Saldi ($percent%)</h3>
            <table class='mpe-table'><thead><tr><th>Prodotto</th><th>Listino</th><th>Scontato</th><th>Stato</th></tr></thead><tbody>";

    foreach ($products as $p) {
        if (!empty($exclude_word) && stripos($p->post_title, $exclude_word) !== false) {
            echo "<tr><td>{$p->post_title}</td><td colspan='3' style='color:#999'>Escluso ('$exclude_word')</td></tr>";
            continue;
        }
        $reg = (float)get_post_meta($p->ID, '_regular_price', true);
        $new_sale = round($reg * $mult, 2);
        if ($should_round) $new_sale = floor($new_sale) + 0.90;

        $status_msg = "—";
        if ($is_run && $reg > 0) {
            mpe_save_log($batch_id, $p->ID, $reg, get_post_meta($p->ID, '_sale_price', true));
            mpe_update_product($p->ID, $reg, $new_sale, false);
            $status_msg = "<span style='color:green; font-weight:bold;'>APPLICATO</span>";
        } else {
            $status_msg = "<span style='color:orange'>SIMULATO</span>";
        }

        echo "<tr><td>{$p->post_title}</td><td>€$reg</td><td style='color:#ef4444; font-weight:bold;'>€$new_sale</td><td>$status_msg</td></tr>";
    }
    echo "</tbody></table></div>";
    if ($is_run) mpe_hard_sync();
}

// 3. NUOVO MOTORE: RIMOZIONE SELETTIVA SALDI (CHIRURGO)
function mpe_v13_clear_sales_engine($cat_id, $filter_word, $is_run) {
    global $wpdb;
    $products = $wpdb->get_results($wpdb->prepare("SELECT p.ID, p.post_title FROM {$wpdb->posts} p JOIN {$wpdb->term_relationships} tr ON p.ID = tr.object_id WHERE tr.term_taxonomy_id = %d AND p.post_status = 'publish'", $cat_id));
    $batch_id = 'CLEAR_' . time();
    $count = 0;

    $mode_label = $is_run ? "<span class='mpe-badge' style='background:red'>MODIFICA REALE</span>" : "<span class='mpe-badge' style='background:grey'>SIMULAZIONE</span>";
    echo "<div class='mpe-card'>
            <h3>$mode_label Report Rimozione Saldi</h3>
            <table class='mpe-table'><thead><tr><th>Prodotto</th><th>Listino Corrente</th><th>Sconto Rimosso</th><th>Stato</th></tr></thead><tbody>";

    foreach ($products as $p) {
        if (!empty($filter_word) && stripos($p->post_title, $filter_word) === false) continue;

        $reg = get_post_meta($p->ID, '_regular_price', true);
        $sale = get_post_meta($p->ID, '_sale_price', true);

        if (!empty($sale)) {
            $count++;
            if ($is_run) {
                mpe_save_log($batch_id, $p->ID, $reg, $sale);
                mpe_update_product($p->ID, $reg, '', true);
            }
            echo "<tr><td>{$p->post_title}</td><td>€$reg</td><td><del>€$sale</del></td><td>".($is_run?'RIPRISTINATO':'OK')."</td></tr>";
        }
    }
    if($count == 0) echo "<tr><td colspan='4' style='text-align:center;'>Nessun prodotto con saldo trovato per i filtri selezionati.</td></tr>";
    echo "</tbody></table></div>";

    if ($is_run && $count > 0) mpe_hard_sync();
}

/* ==========================================================================
   SEZIONE 2: DIAGNOSTICA X-RAY & SNIFFER
   ========================================================================== */

function mpe_render_xray_tab() {
    global $wpdb;

    // --- METRICHE ---
    $firewall_status = get_option('mpe_firewall_active') === 'yes';
    $mem = round(memory_get_usage()/1048576, 1);
    $load = function_exists('sys_getloadavg') ? sys_getloadavg() : [0,0,0];
    $cpu = isset($load[0]) ? $load[0] : 0;

    // UI STATS
    echo "<div class='perf-grid' style='display:grid; grid-template-columns:repeat(3,1fr); gap:15px; margin-bottom:20px;'>
        <div class='perf-box' style='background:#fff; padding:15px; text-align:center; border:1px solid #e2e8f0; border-bottom:3px solid #3b82f6;'>
            <div style='font-size:24px; font-weight:800;'>{$mem} MB</div><div style='font-size:10px; color:#666;'>RAM</div>
        </div>
        <div class='perf-box' style='background:#fff; padding:15px; text-align:center; border:1px solid #e2e8f0; border-bottom:3px solid ".($cpu>4?'#ef4444':'#22c55e').";'>
            <div style='font-size:24px; font-weight:800;'>{$cpu}</div><div style='font-size:10px; color:#666;'>CPU LOAD</div>
        </div>
        <div class='perf-box' style='background:#fff; padding:15px; text-align:center; border:1px solid #e2e8f0; border-bottom:3px solid ".($firewall_status?'#ef4444':'#22c55e').";'>
            <div style='font-size:24px; font-weight:800; color:".($firewall_status?'#ef4444':'#22c55e').";'>".($firewall_status?'ON':'OFF')."</div>
            <div style='font-size:10px; color:#666;'>FIREWALL ESTERNO</div>
        </div>
    </div>";

    // --- BOX: COME TROVARE IL LOOP ---
    echo "<div class='mpe-card' style='background:#f0f9ff; border-left:4px solid #0ea5e9;'>
            <h3>🕵️ Come trovare il Loop (Chi blocca la CPU?)</h3>
            <ol style='font-size:13px; line-height:1.6; margin-left:15px;'>
                <li>Vai nel tab <strong>DEBUG TERMINAL</strong>.</li>
                <li>Attiva il Debug se è spento (Tasto ACCENDI).</li>
                <li>Naviga un po' il sito (o aspetta che si rallenti).</li>
                <li>Torna nel DEBUG e cerca le righe <code>🕵️ METEORA SNIFFER: Tentativo connessione...</code></li>
                <li>Se vedi un tentativo verso un sito <strong>SENZA</strong> la riga <code>✅ Successo</code>, <strong>HAI TROVATO IL COLPEVOLE!</strong></li>
            </ol>
          </div>";

    // --- ZONA DI GUERRA ---
    echo "<div class='mpe-card' style='border-left:4px solid #ef4444; background:#fef2f2;'>
            <h3>🔥 Strumenti di Emergenza</h3>
            <div style='display:flex; gap:20px; align-items:center; margin-top:15px;'>
                <form method='post' style='flex:1; border:1px solid #fca5a5; padding:15px; background:#fff; border-radius:6px;'>
                    <strong>1. FIREWALL API:</strong> Blocca tutte le connessioni esterne.<br>
                    <small>Se attivando questo la CPU scende, il problema è esterno.</small><br><br>
                    <input type='hidden' name='toggle_firewall' value='".($firewall_status?'no':'yes')."'>
                    <button class='btn-mpe ".($firewall_status?'btn-green':'btn-red')."' style='width:100%; justify-content:center;'>
                        ".($firewall_status ? 'SPEGNI FIREWALL' : 'ATTIVA FIREWALL')."
                    </button>
                </form>

                <form method='post' style='flex:1; border:1px solid #fcd34d; padding:15px; background:#fff; border-radius:6px;'>
                    <strong>2. PROCESS KILLER:</strong> Pulisce transienti e processi appesi.<br><br><br>
                    <input type='submit' name='kill_transients' class='btn-mpe btn-blue' value='UCCIDI PROCESSI' style='width:100%; justify-content:center;'>
                </form>
            </div>
          </div>";

    // --- SCAN ON DEMAND ---
    if (!isset($_POST['run_deep_scan']) && !isset($_POST['optimize_tables'])) {
        echo "<div class='mpe-card' style='text-align:center; padding:20px;'>
                <h3>🗄️ Scansione Database</h3>
                <form method='post'><input type='submit' name='run_deep_scan' class='btn-mpe btn-grey' value='Avvia Analisi Tabelle'></form>
              </div>";
    } else {
        $tables = $wpdb->get_results("SHOW TABLE STATUS");
        $heavy_tables = []; $total_overhead = 0;
        foreach ($tables as $t) {
            $overhead = (float)$t->Data_free; $total_overhead += $overhead;
            if (($t->Data_length + $t->Index_length) > 5 * 1024 * 1024 || $overhead > 0) $heavy_tables[] = $t;
        }
        usort($heavy_tables, function($a, $b) { return $b->Data_free <=> $a->Data_free; });
        $ov_mb = round($total_overhead/1024/1024, 2);

        echo "<div class='mpe-card' style='border-left:4px solid #22c55e;'>
                <h3>Risultati DB (Overhead: {$ov_mb} MB)</h3>
                <div style='height:300px; overflow-y:auto;'>
                <table class='mpe-table'><thead><tr><th>Tabella</th><th>Dati</th><th>Indici</th><th>Sporco</th></tr></thead><tbody>";
        foreach ($heavy_tables as $t) {
            $d = round($t->Data_length/1024/1024, 2); $i = round($t->Index_length/1024/1024, 2); $f = round($t->Data_free/1024, 2);
            $s = ($f > 0) ? "color:red; font-weight:bold;" : "color:green;";
            echo "<tr><td>{$t->Name}</td><td>{$d}M</td><td>{$i}M</td><td style='$s'>{$f}K</td></tr>";
        }
        echo "</tbody></table></div>
              <div style='margin-top:15px;'><form method='post'><input type='submit' name='optimize_tables' class='btn-mpe btn-blue' value='Ottimizza Tabelle'></form></div>
              </div>";
    }
}

// HANDLER FIREWALL
if (isset($_POST['toggle_firewall'])) {
    update_option('mpe_firewall_active', $_POST['toggle_firewall']);
    echo "<script>window.location.href=window.location.href;</script>";
}

// HANDLER OPTIMIZE
function mpe_optimize_tables() {
    global $wpdb;
    $tables = $wpdb->get_results("SHOW TABLE STATUS WHERE Data_free > 0");
    if (empty($tables)) { echo "<div class='mpe-card'>✅ Nessuna tabella da ottimizzare.</div>"; return; }
    echo "<div class='mpe-card'><h3>Log Ottimizzazione</h3><ul>";
    foreach ($tables as $t) {
        $res = $wpdb->get_row("OPTIMIZE TABLE {$t->Name}");
        $msg = isset($res->Msg_text) ? $res->Msg_text : 'Done';
        if (strpos($msg, 'recreate') !== false || strpos($msg, 'OK') !== false) $msg = "<span style='color:green'>OK (Ricostruita)</span>";
        echo "<li><strong>{$t->Name}</strong>: $msg</li>";
    }
    echo "</ul></div>";
}

/* ==========================================================================
   SEZIONE 3: GESTORI COMPLETI
   ========================================================================== */

function mpe_render_autoload_manager() {
    global $wpdb;
    $total = $wpdb->get_var("SELECT SUM(LENGTH(option_value)) FROM {$wpdb->options} WHERE autoload='yes'");
    $mb = round($total / 1024 / 1024, 2);

    echo "<div class='mpe-card'>
            <h3>🧠 Autoload Memory: $mb MB</h3>
            <table class='mpe-table'><thead><tr><th>Opzione</th><th>KB</th><th>Azione</th></tr></thead><tbody>";

    $opts = $wpdb->get_results("SELECT option_name, LENGTH(option_value) s FROM {$wpdb->options} WHERE autoload='yes' ORDER BY s DESC LIMIT 20");
    foreach($opts as $o) {
        $kb = round($o->s / 1024, 2);
        echo "<tr><td>{$o->option_name}</td><td>{$kb} KB</td>
              <td><form method='post'><input type='hidden' name='option_name' value='{$o->option_name}'><input type='submit' name='delete_autoload' class='btn-mpe btn-small btn-outline' value='ELIMINA' onclick=\"return confirm('Sicuro?')\"></form></td></tr>";
    }
    echo "</tbody></table></div>";
}

function mpe_render_cron_manager() {
    $cron = _get_cron_array();
    echo "<div class='mpe-card'><h3>⏱️ Cron Jobs</h3><div style='height:300px; overflow-y:auto;'><table class='mpe-table'>
          <thead><tr><th>Orario</th><th>Hook</th><th>Elimina</th></tr></thead><tbody>";
    if ($cron) {
        foreach ($cron as $ts => $hooks) {
            foreach ($hooks as $hook => $ev) {
                foreach($ev as $k => $v) {
                    echo "<tr><td>".date('H:i', $ts)."</td><td>$hook</td>
                    <td><form method='post'><input type='hidden' name='cron_hook' value='$hook'><input type='hidden' name='cron_time' value='$ts'><input type='hidden' name='cron_sig' value='$k'><input type='submit' name='delete_cron' class='btn-mpe btn-small btn-outline' value='X'></form></td></tr>";
                }
            }
        }
    }
    echo "</tbody></table></div></div>";
}

function mpe_render_debug_console() {
    if (isset($_POST['toggle_debug'])) {
        $s = $_POST['toggle_debug'] === 'on';
        mpe_write_config('WP_DEBUG', $s); mpe_write_config('WP_DEBUG_LOG', $s); mpe_write_config('WP_DEBUG_DISPLAY', false);
        echo "<script>window.location.href=window.location.href;</script>";
    }
    $f = WP_CONTENT_DIR . '/debug.log';
    $txt = file_exists($f) ? mpe_read_tail($f, 100) : "Log non attivo o vuoto.";

    echo "<div class='mpe-card'>
            <div style='display:flex; justify-content:space-between; margin-bottom:10px;'>
                <strong>Stato Debug: ".(WP_DEBUG?"<span style='color:red'>ATTIVO</span>":"<span style='color:green'>SPENTO</span>")."</strong>
                <form method='post'><input type='hidden' name='toggle_debug' value='".(WP_DEBUG?'off':'on')."'><button class='btn-mpe btn-outline'>".(WP_DEBUG?'SPEGNI':'ACCENDI')."</button></form>
            </div>
            <p style='font-size:11px; background:#e0f2fe; padding:5px; border-left:3px solid #0284c7;'>ℹ️ Cerca qui dentro 'METEORA SNIFFER' per vedere le connessioni esterne.</p>
            <div class='console-window'>$txt</div>
            <form method='post' style='margin-top:10px;'><input type='submit' name='clear_debug_log' class='btn-mpe btn-small btn-grey' value='Svuota Log'></form>
          </div>";
}

/* ==========================================================================
   SEZIONE 4: UTILS & SYNC
   ========================================================================== */

function mpe_update_product($pid, $reg, $sale, $clear) {
    $p = wc_get_product($pid); $ids = array($pid);
    if ($p && $p->is_type('variable')) $ids = array_merge($ids, $p->get_children());
    foreach ($ids as $id) {
        update_post_meta($id, '_regular_price', $reg);
        if ($clear) { delete_post_meta($id, '_sale_price'); update_post_meta($id, '_price', $reg); }
        elseif ($sale === 'KEEP_OLD') { $o = get_post_meta($id, '_sale_price', true); update_post_meta($id, '_price', $o ? $o : $reg); }
        else { update_post_meta($id, '_sale_price', $sale); update_post_meta($id, '_price', $sale); }
    }
}

function mpe_hard_sync() {
    global $wpdb;
    $wpdb->query("UPDATE {$wpdb->postmeta} pm JOIN {$wpdb->posts} p ON pm.post_id=p.ID SET pm.meta_value='instock' WHERE p.post_type='product_variation' AND pm.meta_key='_stock_status' AND p.ID IN (SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key='_price' AND meta_value>0)");
    $wpdb->query("UPDATE {$wpdb->prefix}wc_product_meta_lookup l JOIN (SELECT p.post_parent, MIN(CAST(pm.meta_value AS DECIMAL(10,2))) mn, MAX(CAST(pm.meta_value AS DECIMAL(10,2))) mx FROM {$wpdb->posts} p JOIN {$wpdb->postmeta} pm ON p.ID=pm.post_id WHERE p.post_type='product_variation' AND pm.meta_key='_price' GROUP BY p.post_parent) v ON l.product_id=v.post_parent SET l.min_price=v.mn, l.max_price=v.mx, l.onsale=1, l.stock_status='instock'");
    $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_wc_price_%' OR option_name LIKE '_transient_timeout_wc_price_%'");
    $wpdb->query("UPDATE {$wpdb->postmeta} pm JOIN (SELECT p.post_parent, MIN(CAST(pm.meta_value AS DECIMAL(10,2))) v FROM {$wpdb->posts} p JOIN {$wpdb->postmeta} pm ON p.ID=pm.post_id WHERE p.post_type='product_variation' AND pm.meta_key='_price' GROUP BY p.post_parent) x ON pm.post_id=x.post_parent SET pm.meta_value=x.v WHERE pm.meta_key='_price' OR pm.meta_key='_min_variation_price'");
    $wpdb->query("UPDATE {$wpdb->postmeta} pm JOIN (SELECT p.post_parent, MAX(CAST(pm.meta_value AS DECIMAL(10,2))) v FROM {$wpdb->posts} p JOIN {$wpdb->postmeta} pm ON p.ID=pm.post_id WHERE p.post_type='product_variation' AND pm.meta_key='_price' GROUP BY p.post_parent) x ON pm.post_id=x.post_parent SET pm.meta_value=x.v WHERE pm.meta_key='_max_variation_price'");
}

function mpe_get_attrs($id){ $p=wc_get_product($id); $o=''; if($p)foreach($p->get_attributes() as $a)$o.=implode(' ',(array)$a->get_options()).' '; return $o; }
function mpe_save_log($b,$p,$r,$s){ global $wpdb; $wpdb->insert($wpdb->prefix.'mpe_price_logs',['batch_id'=>$b,'product_id'=>$p,'old_regular'=>$r,'old_sale'=>$s,'date'=>current_time('mysql')]); }
function mpe_v6_display_logs(){
    global $wpdb; $l=$wpdb->get_results("SELECT batch_id,COUNT(*)c,date FROM {$wpdb->prefix}mpe_price_logs GROUP BY batch_id ORDER BY date DESC LIMIT 5");
    echo "<table class='mpe-table'><thead><tr><th>Date</th><th>Items</th><th>Undo</th></tr></thead><tbody>";
    if($l)foreach($l as $r) echo "<tr><td>{$r->date}</td><td>{$r->c}</td><td><form method='post'><input type='hidden' name='rb_id' value='{$r->batch_id}'><input type='submit' name='rollback' class='btn-mpe btn-red btn-small' value='ROLLBACK'></form></td></tr>";
    echo "</tbody></table>";
    if(isset($_POST['rollback'])){
        $i=$wpdb->get_results($wpdb->prepare("SELECT * FROM {$wpdb->prefix}mpe_price_logs WHERE batch_id=%s",$_POST['rb_id']));
        foreach($i as $x)mpe_update_product($x->product_id,$x->old_regular,$x->old_sale,false);
        $wpdb->delete($wpdb->prefix.'mpe_price_logs',['batch_id'=>$_POST['rb_id']]); mpe_hard_sync();
        echo "<script>alert('Rollback Done');</script>";
    }
}
function mpe_read_tail($f,$l){
    if(!is_readable($f)) return "Log vuoto o permessi mancanti.";
    $h=fopen($f,"r"); $c=$l; $p=-2; $t=[];
    while($c>0){ $x=" "; while($x!="\n"){ if(fseek($h,$p,SEEK_END)==-1)break; $x=fgetc($h); $p--; } $c--; if(fseek($h,$p,SEEK_END)==-1)break; $t[]=fgets($h); } fclose($h);
    return htmlspecialchars(implode("",array_reverse($t)));
}
function mpe_write_config($k,$v){ $f=ABSPATH.'wp-config.php'; if(!is_writable($f))return; $c=file_get_contents($f); $s=$v===true?'true':($v===false?'false':"'$v'"); $p="/define\s*\(\s*['\"]".preg_quote($k,'/')."['\"]\s*,.*?\)\s*;/"; $n=preg_match($p,$c)?preg_replace($p,"define('$k',$s);",$c):str_replace("/* That's all","define('$k',$s);\r\n/* That's all",$c); if($n!==$c)file_put_contents($f,$n); }
function mpe_delete_cron($h,$s,$t){ $c=_get_cron_array(); if(isset($c[$t][$h][$s])){ unset($c[$t][$h][$s]); _set_cron_array($c); echo "<script>alert('Deleted');</script>"; } }
function mpe_kill_transients(){ global $wpdb; $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_%'"); delete_transient('doing_cron'); echo "<script>alert('Transients Cleaned');</script>"; }
function mpe_delete_autoload($n){ delete_option($n); echo "<script>alert('Deleted');</script>"; }
function mpe_clear_debug_log(){ $f=WP_CONTENT_DIR.'/debug.log'; if(file_exists($f)) file_put_contents($f,''); }

/* ==========================================================================
   METEORA PERFORMANCE - V25.0 (4GB RAM OPTIMIZED)
   ========================================================================== */

// 1. SILENZIO NUCLEARE (Zittisce IE e Yoast)
set_error_handler(function($errno, $errstr) {
    if (strpos($errstr, 'add_data') !== false || strpos($errstr, 'IE') !== false || strpos($errstr, 'deprecato') !== false) {
        return true;
    }
    return false;
}, E_USER_DEPRECATED | E_DEPRECATED | E_NOTICE);

// 2. RIPRISTINO JETPACK (Ma con cautela)
add_filter( 'jetpack_offline_mode', '__return_false' );
add_filter( 'jetpack_sync_incremental_sync_interval', function() { return 300; } );

// 3. PROTEZIONE DATABASE
add_filter( 'action_scheduler_queue_runner_concurrent_batches', function() { return 1; } );

// 4. FIXER MENU
add_action('admin_menu', function() {
    add_menu_page('Meteora Fix', '🛠️ Meteora Fix', 'manage_options', 'meteora-fixer', 'meteora_fixer_render', 'dashicons-hammer', 99);
});

function meteora_fixer_render() {
    global $wpdb;
    if ( isset($_GET['action']) && $_GET['action'] == 'run_fix' ) {
        // Pulizia Transients, Prezzi e Code
        $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE ('%\_transient\_%')");
        $wpdb->query("DELETE FROM {$wpdb->prefix}actionscheduler_actions WHERE status IN ('complete', 'failed', 'canceled')");
        $wpdb->query("TRUNCATE TABLE {$wpdb->prefix}woocommerce_sessions");
        echo '<div class="notice notice-success"><p>🚀 <strong>SISTEMA RIPRISTINATO!</strong> Jetpack Online e DB pulito.</p></div>';
    }
    ?>
    <div class="wrap">
        <h1>⚡ Meteora Performance Shield (4GB)</h1>
        <div class="card" style="padding:20px;">
            <p>Se il catalogo è lento, clicca per resettare le code.</p>
            <a href="?page=meteora-fixer&action=run_fix" class="button button-primary button-large">LANCIA RIPRISTINO</a>
        </div>
    </div>
    <?php
}
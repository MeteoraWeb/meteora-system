<?php
namespace Meteora\Modules\Diagnostic;

use Meteora\Core\Menu\MenuManager;

class DiagnosticEngine {
    /**
     * @var DiagnosticEngine
     */
    private static $instance = null;

    /**
     * @return DiagnosticEngine
     */
    public static function instance() {
        if ( is_null( self::$instance ) ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        MenuManager::instance()->registerTab('tab-system-tools', 'Strumenti Sistema', 'dashicons-heart', [$this, 'renderSystemToolsTab']);
        add_action('admin_init', [$this, 'handlePostRequests']);
    }

    public function handlePostRequests() {
        if (!current_user_can('manage_options')) return;

        if (isset($_POST['toggle_firewall']) && isset($_POST['mpe_diag_nonce']) && wp_verify_nonce($_POST['mpe_diag_nonce'], 'mpe_diag_action')) {
            update_option('mpe_firewall_active', sanitize_text_field($_POST['toggle_firewall']));
        }
        if (isset($_POST['kill_transients']) && isset($_POST['mpe_diag_nonce']) && wp_verify_nonce($_POST['mpe_diag_nonce'], 'mpe_diag_action')) {
            $this->killTransients();
        }
        if (isset($_POST['optimize_tables']) && isset($_POST['mpe_diag_nonce']) && wp_verify_nonce($_POST['mpe_diag_nonce'], 'mpe_diag_action')) {
            $this->optimizeTables();
        }
        if (isset($_POST['delete_autoload']) && isset($_POST['mpe_diag_nonce']) && wp_verify_nonce($_POST['mpe_diag_nonce'], 'mpe_diag_action')) {
            delete_option(sanitize_text_field($_POST['option_name']));
        }
        if (isset($_POST['delete_cron']) && isset($_POST['mpe_diag_nonce']) && wp_verify_nonce($_POST['mpe_diag_nonce'], 'mpe_diag_action')) {
            $this->deleteCron(sanitize_text_field($_POST['cron_hook']), sanitize_text_field($_POST['cron_sig']), sanitize_text_field($_POST['cron_time']));
        }
        if (isset($_POST['toggle_debug']) && isset($_POST['mpe_diag_nonce']) && wp_verify_nonce($_POST['mpe_diag_nonce'], 'mpe_diag_action')) {
            $this->toggleDebug($_POST['toggle_debug'] === 'on');
        }
        if (isset($_POST['clear_debug_log']) && isset($_POST['mpe_diag_nonce']) && wp_verify_nonce($_POST['mpe_diag_nonce'], 'mpe_diag_action')) {
            $this->clearDebugLog();
        }
    }

    public function renderSystemToolsTab() {
        echo '<div style="max-width: 900px;">';
        $this->renderXray();
        $this->renderAutoloadManager();
        $this->renderCronManager();
        $this->renderDebugConsole();
        echo '</div>';
    }

    private function renderXray() {
        global $wpdb;

        $firewall_status = get_option('mpe_firewall_active') === 'yes';
        $mem = round(memory_get_usage()/1048576, 1);
        $load = function_exists('sys_getloadavg') ? sys_getloadavg() : [0,0,0];
        $cpu = isset($load[0]) ? $load[0] : 0;

        echo "<div class='mpe-grid-2' style='margin-bottom:20px;'>
            <div class='mpe-card' style='text-align:center; border-bottom:4px solid #3b82f6;'>
                <div style='font-size:32px; font-weight:800; color:#3b82f6;'>{$mem} MB</div><div style='font-size:12px; color:#666; font-weight:bold;'>RAM UTILIZZATA</div>
            </div>
            <div class='mpe-card' style='text-align:center; border-bottom:4px solid ".($cpu>4?'#ef4444':'#22c55e').";'>
                <div style='font-size:32px; font-weight:800; color:".($cpu>4?'#ef4444':'#22c55e').";'>{$cpu}</div><div style='font-size:12px; color:#666; font-weight:bold;'>CPU LOAD (1 MIN)</div>
            </div>
        </div>";

        echo "<div class='mpe-card' style='border-left:4px solid #ef4444; background:#fef2f2;'>
                <h3>🔥 Strumenti di Emergenza (X-Ray)</h3>
                <div style='display:flex; gap:20px; align-items:center; margin-top:15px;'>
                    <form method='post' style='flex:1; border:1px solid #fca5a5; padding:15px; background:#fff; border-radius:6px;'>
                        " . wp_nonce_field('mpe_diag_action', 'mpe_diag_nonce', true, false) . "
                        <strong>1. FIREWALL API:</strong> Blocca chiamate esterne (eccetto localhost).<br>
                        <small>Stato attuale: <b>" . ($firewall_status ? "ATTIVO" : "SPENTO") . "</b></small><br><br>
                        <input type='hidden' name='toggle_firewall' value='".($firewall_status?'no':'yes')."'>
                        <button class='btn-mpe ".($firewall_status?'btn-green':'btn-red')."' style='width:100%; justify-content:center;'>
                            ".($firewall_status ? 'SPEGNI FIREWALL' : 'ATTIVA FIREWALL')."
                        </button>
                    </form>

                    <form method='post' style='flex:1; border:1px solid #fcd34d; padding:15px; background:#fff; border-radius:6px;'>
                        " . wp_nonce_field('mpe_diag_action', 'mpe_diag_nonce', true, false) . "
                        <strong>2. PROCESS KILLER:</strong> Pulisce transienti e processi appesi.<br><br><br>
                        <input type='submit' name='kill_transients' class='btn-mpe btn-blue' value='UCCIDI PROCESSI' style='width:100%; justify-content:center;'>
                    </form>
                </div>
              </div>";

        if (!isset($_POST['run_deep_scan']) && !isset($_POST['optimize_tables'])) {
            echo "<div class='mpe-card' style='text-align:center; padding:20px;'>
                    <h3>🗄️ Scansione Database</h3>
                    <form method='post'>
                        " . wp_nonce_field('mpe_diag_action', 'mpe_diag_nonce', true, false) . "
                        <input type='submit' name='run_deep_scan' class='btn-mpe btn-grey' value='Avvia Analisi Tabelle'>
                    </form>
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
                    <h3>Risultati DB (Spazio recuperabile: {$ov_mb} MB)</h3>
                    <div style='height:250px; overflow-y:auto;'>
                    <table class='mpe-table'><thead><tr><th>Tabella</th><th>Dati</th><th>Indici</th><th>Sporco</th></tr></thead><tbody>";
            foreach ($heavy_tables as $t) {
                $d = round($t->Data_length/1024/1024, 2); $i = round($t->Index_length/1024/1024, 2); $f = round($t->Data_free/1024, 2);
                $s = ($f > 0) ? "color:red; font-weight:bold;" : "color:green;";
                echo "<tr><td>{$t->Name}</td><td>{$d}M</td><td>{$i}M</td><td style='$s'>{$f}K</td></tr>";
            }
            echo "</tbody></table></div>
                  <div style='margin-top:15px;'><form method='post'>
                    " . wp_nonce_field('mpe_diag_action', 'mpe_diag_nonce', true, false) . "
                    <input type='submit' name='optimize_tables' class='btn-mpe btn-blue' value='Ottimizza Tabelle'>
                  </form></div>
                  </div>";
        }
    }

    private function renderAutoloadManager() {
        global $wpdb;
        $total = $wpdb->get_var("SELECT SUM(LENGTH(option_value)) FROM {$wpdb->options} WHERE autoload='yes'");
        $mb = round($total / 1024 / 1024, 2);

        echo "<div class='mpe-card'>
                <h3>🧠 Autoload Memory: $mb MB</h3>
                <p style='font-size:12px; color:#666;'>Un autoload superiore a 1 MB può rallentare l'intero sito. Elimina le opzioni non necessarie.</p>
                <table class='mpe-table'><thead><tr><th>Opzione</th><th>KB</th><th>Azione</th></tr></thead><tbody>";

        $opts = $wpdb->get_results("SELECT option_name, LENGTH(option_value) s FROM {$wpdb->options} WHERE autoload='yes' ORDER BY s DESC LIMIT 15");
        foreach($opts as $o) {
            $kb = round($o->s / 1024, 2);
            echo "<tr><td>{$o->option_name}</td><td>{$kb} KB</td>
                  <td><form method='post'>
                    " . wp_nonce_field('mpe_diag_action', 'mpe_diag_nonce', true, false) . "
                    <input type='hidden' name='option_name' value='{$o->option_name}'>
                    <input type='submit' name='delete_autoload' class='btn-mpe btn-small btn-outline' value='ELIMINA' onclick=\"return confirm('Sicuro di voler eliminare questa opzione?')\">
                  </form></td></tr>";
        }
        echo "</tbody></table></div>";
    }

    private function renderCronManager() {
        $cron = _get_cron_array();
        echo "<div class='mpe-card'><h3>⏱️ Cron Jobs Programmati</h3><div style='height:250px; overflow-y:auto;'><table class='mpe-table'>
              <thead><tr><th>Orario</th><th>Hook</th><th>Elimina</th></tr></thead><tbody>";
        if ($cron) {
            foreach ($cron as $ts => $hooks) {
                foreach ($hooks as $hook => $ev) {
                    foreach($ev as $k => $v) {
                        echo "<tr><td>".date('d/m H:i', $ts)."</td><td>$hook</td>
                        <td><form method='post'>
                            " . wp_nonce_field('mpe_diag_action', 'mpe_diag_nonce', true, false) . "
                            <input type='hidden' name='cron_hook' value='$hook'>
                            <input type='hidden' name='cron_time' value='$ts'>
                            <input type='hidden' name='cron_sig' value='$k'>
                            <input type='submit' name='delete_cron' class='btn-mpe btn-small btn-outline' value='X'>
                        </form></td></tr>";
                    }
                }
            }
        }
        echo "</tbody></table></div></div>";
    }

    private function renderDebugConsole() {
        $f = WP_CONTENT_DIR . '/debug.log';
        $txt = file_exists($f) ? $this->readTail($f, 50) : "Log non attivo o vuoto.";

        echo "<div class='mpe-card'>
                <div style='display:flex; justify-content:space-between; align-items:center; margin-bottom:10px;'>
                    <h3 style='margin:0;'>Terminale WP_DEBUG</h3>
                    <div style='display:flex; align-items:center; gap:10px;'>
                        <strong>Stato: ".(WP_DEBUG?"<span style='color:#ef4444'>ATTIVO</span>":"<span style='color:#22c55e'>SPENTO</span>")."</strong>
                        <form method='post'>
                            " . wp_nonce_field('mpe_diag_action', 'mpe_diag_nonce', true, false) . "
                            <input type='hidden' name='toggle_debug' value='".(WP_DEBUG?'off':'on')."'>
                            <button class='btn-mpe btn-outline'>".(WP_DEBUG?'SPEGNI LOG':'ACCENDI LOG')."</button>
                        </form>
                    </div>
                </div>
                <div class='console-window'>$txt</div>
                <form method='post' style='margin-top:10px;'>
                    " . wp_nonce_field('mpe_diag_action', 'mpe_diag_nonce', true, false) . "
                    <input type='submit' name='clear_debug_log' class='btn-mpe btn-small btn-grey' value='Svuota Log'>
                </form>
              </div>";
    }

    private function killTransients() {
        global $wpdb;
        $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_%'");
        delete_transient('doing_cron');
        echo "<div class='updated'><p>Processi e Transients puliti.</p></div>";
    }

    private function optimizeTables() {
        global $wpdb;
        $tables = $wpdb->get_results("SHOW TABLE STATUS WHERE Data_free > 0");
        if (empty($tables)) { echo "<div class='updated'><p>✅ Nessuna tabella da ottimizzare.</p></div>"; return; }
        echo "<div class='updated'><p><strong>Log Ottimizzazione:</strong><br>";
        foreach ($tables as $t) {
            $res = $wpdb->get_row("OPTIMIZE TABLE {$t->Name}");
            echo "{$t->Name} ottimizzata.<br>";
        }
        echo "</p></div>";
    }

    private function deleteCron($h, $s, $t) {
        $c = _get_cron_array();
        if (isset($c[$t][$h][$s])) {
            unset($c[$t][$h][$s]);
            _set_cron_array($c);
        }
    }

    private function readTail($f, $l) {
        if(!is_readable($f)) return "Permessi mancanti per leggere il file debug.log.";
        $h = fopen($f, "r"); $c = $l; $p = -2; $t = [];
        while($c > 0) {
            $x = " ";
            while($x != "\n") {
                if(fseek($h, $p, SEEK_END) == -1) break;
                $x = fgetc($h);
                $p--;
            }
            $c--;
            if(fseek($h, $p, SEEK_END) == -1) break;
            $t[] = fgets($h);
        }
        fclose($h);
        return htmlspecialchars(implode("", array_reverse($t)));
    }

    private function toggleDebug($enable) {
        $f = ABSPATH . 'wp-config.php';
        if (!is_writable($f)) return;
        $c = file_get_contents($f);

        $s = $enable ? 'true' : 'false';
        foreach(['WP_DEBUG', 'WP_DEBUG_LOG'] as $k) {
            $p = "/define\s*\(\s*['\"]".preg_quote($k,'/')."['\"]\s*,.*?\)\s*;/";
            if (preg_match($p, $c)) {
                $c = preg_replace($p, "define('$k', $s);", $c);
            } else {
                $c = str_replace("/* That's all", "define('$k', $s);\r\n/* That's all", $c);
            }
        }

        $p_display = "/define\s*\(\s*['\"]WP_DEBUG_DISPLAY['\"]\s*,.*?\)\s*;/";
        if (preg_match($p_display, $c)) {
            $c = preg_replace($p_display, "define('WP_DEBUG_DISPLAY', false);", $c);
        } else {
            $c = str_replace("/* That's all", "define('WP_DEBUG_DISPLAY', false);\r\n/* That's all", $c);
        }

        file_put_contents($f, $c);
    }

    private function clearDebugLog() {
        $f = WP_CONTENT_DIR . '/debug.log';
        if (file_exists($f)) file_put_contents($f, '');
    }
}

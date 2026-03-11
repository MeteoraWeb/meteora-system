<?php
namespace Meteora\Modules\Saldi;

use Meteora\Core\Menu\MenuManager;

class SalesEngine {
    /**
     * @var SalesEngine
     */
    private static $instance = null;

    /**
     * @return SalesEngine
     */
    public static function instance() {
        if ( is_null( self::$instance ) ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        MenuManager::instance()->registerTab('tab-sales-hub', 'Catalogo e Prezzi', 'dashicons-money', [$this, 'renderSalesHub']);
        add_action('admin_init', [$this, 'handlePostRequests']);
    }

    public function handlePostRequests() {
        // Sensore di Rilevamento Ambientale
        if (!class_exists('WooCommerce')) {
            return;
        }

        if (!current_user_can('manage_options')) {
            return;
        }

        if (isset($_POST['mpe_sales_nonce']) && wp_verify_nonce($_POST['mpe_sales_nonce'], 'mpe_sales_action')) {
            if (isset($_POST['preview_gold']) || isset($_POST['run_gold'])) {
                $is_preview = isset($_POST['preview_gold']);
                $this->goldEngine(floatval($_POST['gold_price']), intval($_POST['cat_id']), isset($_POST['clear_sales']), isset($_POST['round_marketing']), !$is_preview);
            }

            if (isset($_POST['preview_sales']) || isset($_POST['run_sales'])) {
                $is_preview = isset($_POST['preview_sales']);
                $this->salesEngine(intval($_POST['discount_val']), intval($_POST['sale_cat_id']), sanitize_text_field($_POST['exclude_word']), isset($_POST['round_marketing_sale']), !$is_preview);
            }

            if (isset($_POST['preview_clear']) || isset($_POST['run_clear'])) {
                $is_preview = isset($_POST['preview_clear']);
                $this->clearSalesEngine(intval($_POST['clear_cat_id']), sanitize_text_field($_POST['clear_filter_word']), !$is_preview);
            }

            if (isset($_POST['rollback'])) {
                $this->rollbackLogs(sanitize_text_field($_POST['rb_id']));
            }
        }
    }

    private function getCategoryOptions() {
        if (!class_exists('WooCommerce')) {
            return '';
        }
        $categories = get_terms(['taxonomy' => 'product_cat', 'hide_empty' => false]);
        $cat_options = '';
        if (!is_wp_error($categories)) {
            $cat_options = $this->buildCatsUI($categories);
        }
        return $cat_options;
    }

    private function buildCatsUI($cats, $parent = 0, $depth = 0) {
        $out = '';
        foreach ($cats as $cat) {
            if ($cat->parent == $parent) {
                $out .= '<option value="'.$cat->term_id.'">'.str_repeat('— ', $depth).$cat->name.' ('.$cat->count.')</option>';
                $out .= $this->buildCatsUI($cats, $cat->term_id, $depth + 1);
            }
        }
        return $out;
    }

    public function renderSalesHub() {
        if (!class_exists('WooCommerce')) { echo '<p>WooCommerce non rilevato.</p>'; return; }
        $cat_options = $this->getCategoryOptions();

        echo '<div style="max-width: 900px;">';

        // --- GOLD ENGINE ---
        echo '<div class="mpe-card" style="border-left: 4px solid #f59e0b;">
            <h3>Gestione Prezzi Oro</h3>
            <p style="margin-bottom:15px; font-size:12px; color:#666;">Il sistema cerca il peso nel titolo o descrizione e ricalcola il prezzo</p>
            <form method="post">
                ' . wp_nonce_field('mpe_sales_action', 'mpe_sales_nonce', true, false) . '
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
        </div>';

        // --- SALES ENGINE ---
        echo '<div class="mpe-card" style="border-left: 4px solid #3b82f6;">
            <h3>Applica Saldi Massivi</h3>
            <form method="post">
                ' . wp_nonce_field('mpe_sales_action', 'mpe_sales_nonce', true, false) . '
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
        </div>';

        // --- CLEAR SALES ---
        echo '<div class="mpe-card" style="border-left: 4px solid #ef4444;">
            <h3>Rimozione Selettiva Saldi</h3>
            <p style="margin-bottom:15px; font-size:12px; color:#666;">Rimuove lo sconto e ripristina il prezzo di listino originale per i prodotti filtrati</p>
            <form method="post">
                ' . wp_nonce_field('mpe_sales_action', 'mpe_sales_nonce', true, false) . '
                <div class="mpe-grid-2">
                    <div><label class="mpe-label">Categoria</label><select name="clear_cat_id" class="mpe-input">'.$cat_options.'</select></div>
                    <div><label class="mpe-label">Filtra per Parola nel Titolo (Opzionale)</label><input type="text" name="clear_filter_word" class="mpe-input" placeholder="Es Collana"></div>
                </div>
                <div style="display:flex; gap:10px; border-top:1px solid #eee; padding-top:15px; margin-top:20px;">
                    <button type="submit" name="preview_clear" class="btn-mpe btn-grey"><span class="dashicons dashicons-visibility"></span> SIMULA RIMOZIONE</button>
                    <button type="submit" name="run_clear" class="btn-mpe btn-red"><span class="dashicons dashicons-trash"></span> RIMUOVI SALDI (RUN)</button>
                </div>
            </form>
        </div>';

        // --- LOGS ---
        global $wpdb;
        $l = $wpdb->get_results("SELECT batch_id,COUNT(*)c,date FROM {$wpdb->prefix}mpe_price_logs GROUP BY batch_id ORDER BY date DESC LIMIT 5");
        echo "<div class='mpe-card' style='border-left: 4px solid #64748b;'><h3>Log Modifiche Prezzi (Rollback)</h3>";
        if($l) {
            echo "<table class='mpe-table'><thead><tr><th>Date</th><th>Items</th><th>Undo</th></tr></thead><tbody>";
            foreach($l as $r) {
                echo "<tr><td>{$r->date}</td><td>{$r->c}</td><td><form method='post'>";
                echo wp_nonce_field('mpe_sales_action', 'mpe_sales_nonce', true, false);
                echo "<input type='hidden' name='rb_id' value='{$r->batch_id}'><input type='submit' name='rollback' class='btn-mpe btn-red btn-small' value='ROLLBACK'></form></td></tr>";
            }
            echo "</tbody></table>";
        } else {
            echo "<p>Nessun log presente.</p>";
        }
        echo "</div>";

        echo '</div>'; // End container
    }

    private function getAttrs($id){
        if(!function_exists('wc_get_product')) return '';
        $p=wc_get_product($id);
        $o='';
        if($p) {
            foreach($p->get_attributes() as $a) {
                $o.=implode(' ',(array)$a->get_options()).' ';
            }
        }
        return $o;
    }

    private function saveLog($b,$p,$r,$s){
        global $wpdb;
        $wpdb->insert($wpdb->prefix.'mpe_price_logs',['batch_id'=>$b,'product_id'=>$p,'old_regular'=>$r,'old_sale'=>$s,'date'=>current_time('mysql')]);
    }

    public function goldEngine($gold_price, $cat_id, $clear_sales, $should_round, $is_run) {
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
            $search_text = $p->post_content . ' ' . $this->getAttrs($p->ID);
            if (preg_match('/(\d+(?:[.,]\d+)?)\s*(?:grammi|grammo|gr|g)(?![a-z])/i', $search_text, $matches)) {
                $found++;
                $weight = (float)str_replace(',', '.', $matches[1]);
                $new_reg = round($weight * $gold_price, 2);
                if ($should_round) $new_reg = floor($new_reg) + 0.90;
                $old_reg = (float)get_post_meta($p->ID, '_regular_price', true);

                $diff_style = ($new_reg != $old_reg) ? "color:#2563eb; font-weight:bold;" : "color:#94a3b8;";
                $status_msg = "—";

                if ($is_run) {
                    $this->saveLog($batch_id, $p->ID, $old_reg, get_post_meta($p->ID, '_sale_price', true));
                    $this->updateProduct($p->ID, $new_reg, $clear_sales ? '' : 'KEEP_OLD', $clear_sales);
                    $status_msg = "<span style='color:green; font-weight:bold;'>AGGIORNATO</span>";
                } else {
                    $status_msg = "<span style='color:orange'>SIMULATO</span>";
                }

                echo "<tr><td><strong>{$p->post_title}</strong></td><td>{$weight} g</td><td>€$old_reg</td><td style='$diff_style'>€$new_reg</td><td>$status_msg</td></tr>";
            }
        }

        if ($found == 0) echo "<tr><td colspan='5' style='text-align:center; padding:20px; color:red;'>❌ Nessun peso trovato.</td></tr>";
        echo "</tbody></table></div>";

        if ($is_run && $found > 0) $this->hardSync();
    }

    public function salesEngine($percent, $cat_id, $exclude_word, $should_round, $is_run) {
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
                $this->saveLog($batch_id, $p->ID, $reg, get_post_meta($p->ID, '_sale_price', true));
                $this->updateProduct($p->ID, $reg, $new_sale, false);
                $status_msg = "<span style='color:green; font-weight:bold;'>APPLICATO</span>";
            } else {
                $status_msg = "<span style='color:orange'>SIMULATO</span>";
            }

            echo "<tr><td>{$p->post_title}</td><td>€$reg</td><td style='color:#ef4444; font-weight:bold;'>€$new_sale</td><td>$status_msg</td></tr>";
        }
        echo "</tbody></table></div>";
        if ($is_run) $this->hardSync();
    }

    public function clearSalesEngine($cat_id, $filter_word, $is_run) {
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
                    $this->saveLog($batch_id, $p->ID, $reg, $sale);
                    $this->updateProduct($p->ID, $reg, '', true);
                }
                echo "<tr><td>{$p->post_title}</td><td>€$reg</td><td><del>€$sale</del></td><td>".($is_run?'RIPRISTINATO':'OK')."</td></tr>";
            }
        }
        if($count == 0) echo "<tr><td colspan='4' style='text-align:center;'>Nessun prodotto con saldo trovato per i filtri selezionati.</td></tr>";
        echo "</tbody></table></div>";

        if ($is_run && $count > 0) $this->hardSync();
    }

    public function updateProduct($pid, $reg, $sale, $clear) {
        if(!function_exists('wc_get_product')) return;
        $p = wc_get_product($pid); $ids = array($pid);
        if ($p && $p->is_type('variable')) $ids = array_merge($ids, $p->get_children());
        foreach ($ids as $id) {
            update_post_meta($id, '_regular_price', $reg);
            if ($clear) { delete_post_meta($id, '_sale_price'); update_post_meta($id, '_price', $reg); }
            elseif ($sale === 'KEEP_OLD') { $o = get_post_meta($id, '_sale_price', true); update_post_meta($id, '_price', $o ? $o : $reg); }
            else { update_post_meta($id, '_sale_price', $sale); update_post_meta($id, '_price', $sale); }
        }
    }

    public function hardSync() {
        global $wpdb;
        $wpdb->query("UPDATE {$wpdb->postmeta} pm JOIN {$wpdb->posts} p ON pm.post_id=p.ID SET pm.meta_value='instock' WHERE p.post_type='product_variation' AND pm.meta_key='_stock_status' AND p.ID IN (SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key='_price' AND meta_value>0)");
        $wpdb->query("UPDATE {$wpdb->prefix}wc_product_meta_lookup l JOIN (SELECT p.post_parent, MIN(CAST(pm.meta_value AS DECIMAL(10,2))) mn, MAX(CAST(pm.meta_value AS DECIMAL(10,2))) mx FROM {$wpdb->posts} p JOIN {$wpdb->postmeta} pm ON p.ID=pm.post_id WHERE p.post_type='product_variation' AND pm.meta_key='_price' GROUP BY p.post_parent) v ON l.product_id=v.post_parent SET l.min_price=v.mn, l.max_price=v.mx, l.onsale=1, l.stock_status='instock'");
        $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_wc_price_%' OR option_name LIKE '_transient_timeout_wc_price_%'");
        $wpdb->query("UPDATE {$wpdb->postmeta} pm JOIN (SELECT p.post_parent, MIN(CAST(pm.meta_value AS DECIMAL(10,2))) v FROM {$wpdb->posts} p JOIN {$wpdb->postmeta} pm ON p.ID=pm.post_id WHERE p.post_type='product_variation' AND pm.meta_key='_price' GROUP BY p.post_parent) x ON pm.post_id=x.post_parent SET pm.meta_value=x.v WHERE pm.meta_key='_price' OR pm.meta_key='_min_variation_price'");
        $wpdb->query("UPDATE {$wpdb->postmeta} pm JOIN (SELECT p.post_parent, MAX(CAST(pm.meta_value AS DECIMAL(10,2))) v FROM {$wpdb->posts} p JOIN {$wpdb->postmeta} pm ON p.ID=pm.post_id WHERE p.post_type='product_variation' AND pm.meta_key='_price' GROUP BY p.post_parent) x ON pm.post_id=x.post_parent SET pm.meta_value=x.v WHERE pm.meta_key='_max_variation_price'");
    }

    public function rollbackLogs($rb_id) {
        global $wpdb;
        $i=$wpdb->get_results($wpdb->prepare("SELECT * FROM {$wpdb->prefix}mpe_price_logs WHERE batch_id=%s", $rb_id));
        foreach($i as $x) {
            $this->updateProduct($x->product_id,$x->old_regular,$x->old_sale,false);
        }
        $wpdb->delete($wpdb->prefix.'mpe_price_logs',['batch_id'=>$rb_id]);
        $this->hardSync();
        echo "<div class='updated'><p>Rollback eseguito per il batch " . esc_html($rb_id) . ".</p></div>";
    }
}

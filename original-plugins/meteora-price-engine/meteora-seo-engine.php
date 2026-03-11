<?php
/**
 * Meteora SEO Engine - Modulo AI Automatizzato (V2.6 Flash Lite)
 * Inclusi: Filtri Avanzati (Stock, Log, Titolo, All), Brand Dinamico e Mass Rollback
 */

if ( ! defined( 'ABSPATH' ) ) exit;

// ==========================================================================
// 1. SETUP: TABELLA LOGS DI SICUREZZA
// ==========================================================================
add_action('admin_init', 'mpe_seo_setup');
function mpe_seo_setup() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'mpe_seo_logs';
    if($wpdb->get_var("SHOW TABLES LIKE '$table_name'") != $table_name) {
        $charset_collate = $wpdb->get_charset_collate();
        $sql = "CREATE TABLE $table_name (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            post_id bigint(20) NOT NULL,
            old_title text,
            old_short text,
            old_long longtext,
            old_kw text,
            old_seo_title text,
            old_seo_desc text,
            date datetime DEFAULT '0000-00-00 00:00:00' NOT NULL,
            PRIMARY KEY  (id)
        ) $charset_collate;";
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }
}

if (isset($_POST['save_gemini_api'])) {
    update_option('mpe_gemini_api_key', sanitize_text_field($_POST['mpe_gemini_api_key']));
}

// ==========================================================================
// 2. INTERFACCIA UTENTE (UI) CON FILTRI AVANZATI
// ==========================================================================
function mpe_render_seo_tab() {
    global $wpdb;
    $api_key = get_option('mpe_gemini_api_key', '');

    // Menu a tendina categorie con opzione per selezionare l'intero catalogo
    $cat_dropdown = wp_dropdown_categories([
        'taxonomy'         => 'product_cat',
        'hide_empty'       => false,
        'hierarchical'     => true,
        'show_option_all'  => 'Tutte le Categorie (Intero Catalogo)',
        'name'             => 'seo_cat_id',
        'id'               => 'seo_cat_id',
        'class'            => 'mpe-input',
        'show_count'       => true,
        'echo'             => false
    ]);

    echo '<div class="mpe-card" style="border-left: 4px solid #8b5cf6;">
            <h3>🤖 Configurazione Motore AI (Gemini 2.0 Flash Lite)</h3>
            <form method="post" style="display:flex; gap:10px; align-items:flex-end;">
                <div style="flex:1;">
                    <label class="mpe-label">Chiave API Google Gemini</label>
                    <input type="password" name="mpe_gemini_api_key" value="'.esc_attr($api_key).'" class="mpe-input" placeholder="Incolla qui la tua API Key...">
                </div>
                <button type="submit" name="save_gemini_api" class="btn-mpe btn-blue">Salva Chiave</button>
            </form>
          </div>';

    if (empty($api_key)) {
        echo '<div class="mpe-card"><p style="color:red; font-weight:bold;">⚠️ Inserisci la chiave API per sbloccare il SEO Engine.</p></div>';
        return;
    }

    echo '<div class="mpe-card">
            <h3>🚀 Avvia Ottimizzazione Massiva</h3>
            <div class="mpe-grid-2" style="margin-bottom:20px;">
                <div>
                    <label class="mpe-label">Selezione Prodotti</label>
                    '.$cat_dropdown.'
                    <div style="margin-top:15px;">
                        <label class="mpe-label">🔍 Filtro Titolo (Opzionale)</label>
                        <input type="text" id="seo_title_filter" class="mpe-input" placeholder="Es: Collana, Oro, Anello...">
                    </div>
                </div>
                <div>
                    <label class="mpe-label">Filtri e Opzioni IA</label>
                    <div style="background: #f8fafc; padding: 12px; border: 1px solid #e2e8f0; border-radius: 4px;">
                        <label style="display:block; font-size:12px; margin-bottom:8px;">
                            <input type="checkbox" id="seo_exclude_oos" checked> Escludi prodotti non disponibili (Out of Stock)
                        </label>
                        <label style="display:block; font-size:12px; margin-bottom:8px;">
                            <input type="checkbox" id="seo_exclude_modified" checked> Escludi prodotti già ottimizzati (Presenti nei Log)
                        </label>
                        <label style="display:block; font-size:12px; margin-bottom:8px;">
                            <input type="checkbox" id="seo_force_brand" checked> Includi Brand nel SEO Title
                        </label>
                        <label style="display:block; font-size:12px;">
                            <input type="checkbox" id="seo_bullet_points" checked> Usa elenchi <ul> in Desc. Breve
                        </label>
                    </div>
                </div>
            </div>

            <div style="display:flex; gap:10px;">
                <button type="button" id="btn-start-seo" class="btn-mpe btn-green" style="flex:1; justify-content:center; font-size:16px; padding:15px;">▶️ AVVIA AUTO-PILOT SEO</button>
                <button type="button" id="btn-stop-seo" class="btn-mpe btn-red" style="display:none; flex:1; justify-content:center; font-size:16px; padding:15px;">⏹️ FERMA PROCESSO</button>
            </div>

            <div id="seo-progress-area" style="display:none; margin-top:20px; padding:15px; background:#f8fafc; border:1px solid #cbd5e1; border-radius:4px;">
                <div style="display:flex; justify-content:space-between; font-weight:bold; margin-bottom:10px;">
                    <span id="seo-status-text">Analisi prodotti nel database...</span>
                    <span id="seo-counter">0 / 0</span>
                </div>
                <div style="width:100%; background:#e2e8f0; border-radius:10px; height:20px; overflow:hidden;">
                    <div id="seo-progress-bar" style="width:0%; height:100%; background:#10b981; transition:0.3s;"></div>
                </div>
                <div id="seo-log-window" style="margin-top:15px; height:200px; overflow-y:auto; background:#1e1e1e; color:#a3e635; padding:10px; font-family:monospace; font-size:11px; border-radius:4px;">
                    > Sistema Pronto... In attesa di avvio.
                </div>
            </div>
          </div>';

    echo '<script>
    let isSeoRunning = false;

    document.getElementById("btn-start-seo").addEventListener("click", function() {
        const catId = document.getElementById("seo_cat_id").value;
        const forceBrand = document.getElementById("seo_force_brand").checked ? 1 : 0;
        const bulletPoints = document.getElementById("seo_bullet_points").checked ? 1 : 0;
        const excludeOos = document.getElementById("seo_exclude_oos").checked ? 1 : 0;
        const excludeModified = document.getElementById("seo_exclude_modified").checked ? 1 : 0;
        const titleFilter = document.getElementById("seo_title_filter").value;

        isSeoRunning = true;
        document.getElementById("seo-progress-area").style.display = "block";
        this.style.display = "none";
        document.getElementById("btn-stop-seo").style.display = "flex";

        logToConsole("Applicazione filtri e interrogazione database...");

        fetch(ajaxurl, {
            method: "POST",
            headers: {"Content-Type": "application/x-www-form-urlencoded"},
            body: `action=mpe_seo_get_products&cat_id=${catId}&exclude_oos=${excludeOos}&exclude_modified=${excludeModified}&title_filter=${encodeURIComponent(titleFilter)}`
        })
        .then(res => res.json())
        .then(data => {
            if (!isSeoRunning) return;
            if(data.success && data.data.length > 0) {
                document.getElementById("seo-counter").innerText = `0 / ${data.data.length}`;
                logToConsole(`Trovati ${data.data.length} prodotti idonei. Inizio ottimizzazione...`);
                processProduct(data.data, 0, forceBrand, bulletPoints);
            } else {
                logToConsole("Nessun prodotto idoneo trovato con i filtri selezionati.");
                resetButton();
            }
        });
    });

    document.getElementById("btn-stop-seo").addEventListener("click", function() {
        isSeoRunning = false;
        logToConsole("🛑 Stop richiesto. Il sistema si fermerà al termine del prodotto corrente.");
        this.disabled = true;
        this.innerText = "⏳ FERMANDO...";
    });

    function processProduct(ids, index, forceBrand, bulletPoints) {
        if (!isSeoRunning) { resetButton(); return; }
        if (index >= ids.length) { logToConsole("✅ LAVORO COMPLETATO!"); resetButton(); return; }

        const pid = ids[index];
        logToConsole(`Elaborazione [${index + 1}/${ids.length}] - ID Prodotto: ${pid}`);

        fetch(ajaxurl, {
            method: "POST",
            headers: {"Content-Type": "application/x-www-form-urlencoded"},
            body: `action=mpe_seo_process_single&pid=${pid}&force_brand=${forceBrand}&bullets=${bulletPoints}`
        })
        .then(res => res.json())
        .then(data => {
            if (!isSeoRunning) { resetButton(); return; }
            if(data.success) {
                logToConsole(`✨ ID ${pid}: Ottimizzazione riuscita`);
                index++;
                updateProgress(index, ids.length);
                // Pausa 1 secondo (Ottimale se si ha il Livello 1 attivato su Google Cloud)
                setTimeout(() => { processProduct(ids, index, forceBrand, bulletPoints); }, 1000);
            } else if (data.data === "429") {
                logToConsole("⏳ Limite Google (429) raggiunto. Pausa di sicurezza 30s...");
                setTimeout(() => { processProduct(ids, index, forceBrand, bulletPoints); }, 30000);
            } else {
                logToConsole(`❌ ID ${pid}: Errore - ${data.data}`);
                index++;
                setTimeout(() => { processProduct(ids, index, forceBrand, bulletPoints); }, 5000);
            }
        })
        .catch(err => {
            if (!isSeoRunning) return;
            logToConsole(`❌ ID ${pid}: Errore Rete. Riprovo tra 5s...`);
            setTimeout(() => { processProduct(ids, index, forceBrand, bulletPoints); }, 5000);
        });
    }

    function updateProgress(current, total) {
        const percent = (current / total) * 100;
        document.getElementById("seo-progress-bar").style.width = percent + "%";
        document.getElementById("seo-counter").innerText = `${current} / ${total}`;
    }

    function logToConsole(msg) {
        const win = document.getElementById("seo-log-window");
        win.innerHTML += "<br>> " + msg;
        win.scrollTop = win.scrollHeight;
    }

    function resetButton() {
        document.getElementById("btn-start-seo").style.display = "flex";
        document.getElementById("btn-start-seo").disabled = false;
        document.getElementById("btn-stop-seo").style.display = "none";
        document.getElementById("btn-stop-seo").disabled = false;
        document.getElementById("btn-stop-seo").innerText = "⏹️ FERMA PROCESSO";
        isSeoRunning = false;
    }
    </script>';
}

// ==========================================================================
// 3. BACKEND: LOGICA DI FILTRAGGIO SQL E ESTRAZIONE AVANZATA
// ==========================================================================
add_action('wp_ajax_mpe_seo_get_products', 'mpe_seo_get_products_ajax');
function mpe_seo_get_products_ajax() {
    global $wpdb;
    $cat_id = intval($_POST['cat_id']);
    $exclude_oos = intval($_POST['exclude_oos']);
    $exclude_modified = intval($_POST['exclude_modified']);
    $title_filter = sanitize_text_field($_POST['title_filter']);
    $table_logs = $wpdb->prefix . 'mpe_seo_logs';

    // Costruzione dinamica della query in base alla selezione (Tutte le categorie o specifica)
    $sql = "SELECT DISTINCT p.ID FROM {$wpdb->posts} p ";

    if ($cat_id > 0) {
        $sql .= " JOIN {$wpdb->term_relationships} tr ON p.ID = tr.object_id
                  JOIN {$wpdb->term_taxonomy} tt ON tr.term_taxonomy_id = tt.term_taxonomy_id ";
    }

    if ($exclude_oos) {
        $sql .= " JOIN {$wpdb->postmeta} pm_stock ON p.ID = pm_stock.post_id ";
    }

    $sql .= " WHERE p.post_type = 'product' AND p.post_status = 'publish' ";

    if ($cat_id > 0) {
        $term_ids = get_term_children($cat_id, 'product_cat');
        if (is_wp_error($term_ids)) { $term_ids = []; }
        $term_ids[] = $cat_id;
        $in_clause = implode(',', array_map('intval', $term_ids));
        $sql .= " AND tt.term_id IN ($in_clause) AND tt.taxonomy = 'product_cat' ";
    }

    if ($exclude_oos) {
        $sql .= " AND pm_stock.meta_key = '_stock_status' AND pm_stock.meta_value = 'instock' ";
    }

    if (!empty($title_filter)) {
        $sql .= $wpdb->prepare(" AND p.post_title LIKE %s ", '%' . $wpdb->esc_like($title_filter) . '%');
    }

    if ($exclude_modified) {
        $sql .= " AND p.ID NOT IN (SELECT post_id FROM $table_logs) ";
    }

    $sql .= " ORDER BY p.ID DESC";

    $results = $wpdb->get_col($sql);
    wp_send_json_success($results);
}

// ==========================================================================
// 4. BACKEND: ELABORAZIONE IA (VERSIONE METICOLOSA E BLINDATA)
// ==========================================================================
add_action('wp_ajax_mpe_seo_process_single', 'mpe_seo_process_single_ajax');
function mpe_seo_process_single_ajax() {
    global $wpdb;
    $pid = intval($_POST['pid']);
    $api_key = trim(get_option('mpe_gemini_api_key'));

    if (empty($api_key)) wp_send_json_error("Chiave API mancante.");

    $product = get_post($pid);
    if (!$product) wp_send_json_error("Prodotto ID {$pid} non trovato.");

    // ESTRAZIONE DINAMICA BRAND (Tassonomia product_brand)
    $brand_terms = wp_get_post_terms($pid, 'product_brand', array('fields' => 'names'));
    $brand_name = (!is_wp_error($brand_terms) && !empty($brand_terms)) ? $brand_terms[0] : "Gioiello Artigianale";

    // BACKUP E LOG
    $wpdb->insert($wpdb->prefix . 'mpe_seo_logs', [
        'post_id'       => $pid,
        'old_title'     => $product->post_title,
        'old_short'     => $product->post_excerpt,
        'old_long'      => $product->post_content,
        'old_kw'        => get_post_meta($pid, 'rank_math_focus_keyword', true),
        'old_seo_title' => get_post_meta($pid, 'rank_math_title', true),
        'old_seo_desc'  => get_post_meta($pid, 'rank_math_description', true),
        'date'          => current_time('mysql')
    ]);

    // PURIFICAZIONE TESTO (Anti-Crash)
    $clean_text = wp_strip_all_tags(do_shortcode($product->post_content));
    $clean_text = str_replace(['"', "'", "\n", "\r", "[", "]", "{", "}"], ' ', $clean_text);
    $safe_context = wp_trim_words($clean_text, 80);

    // CONFIGURAZIONE PROMPT
    $prompt = "Agisci da Esperto SEO Senior per gioielleria di lusso.
    REGOLE TASSATIVE:
    1. LINGUA: Rispondi esclusivamente in ITALIANO.
    2. FORMATO: Rispondi SOLO con JSON puro. Niente markdown.
    3. BRAND: Il brand identificato è '{$brand_name}'. Usalo nel titolo e nel corpo del testo. NON generare codici shortcode.
    4. STILE: Tecnico e di pregio (oro, pietre, carati). VIETATE frasi su spedizioni o assistenza.

    DATI INPUT:
    Titolo attuale: {$product->post_title}
    Dettagli tecnici: {$safe_context}

    STRUTTURA JSON RICHIESTA:
    {
      \"title\": \"Titolo H1 persuasivo con Brand, max 60 car\",
      \"short\": \"Descrizione breve tecnica in HTML: SOLO <ul><li>\",
      \"long\": \"Descrizione profonda min. 100 parole con <h2> e <p>. Esalta il brand {$brand_name}\",
      \"kw\": \"3-5 focus keyword separate da virgola\",
      \"seo_title\": \"Meta Title ottimizzato, max 60 car\",
      \"seo_desc\": \"Meta Description persuasiva, max 160 car\"
    }";

    // CHIAMATA API
    $url = "https://generativelanguage.googleapis.com/v1beta/models/gemini-2.0-flash-lite:generateContent?key=" . $api_key;
    $body = json_encode([
        "contents" => [["parts" => [["text" => $prompt]]]],
        "generationConfig" => [
            "temperature" => 0.2,
            "response_mime_type" => "application/json" // Forza rigorosamente il JSON
        ]
    ]);

    $response = wp_remote_post($url, [
        'headers' => ['Content-Type' => 'application/json'],
        'body'    => $body,
        'timeout' => 45,
        'sslverify' => false
    ]);

    if (is_wp_error($response)) {
        wp_send_json_error("Errore di Rete: " . $response->get_error_message());
    }

    $http_code = wp_remote_retrieve_response_code($response);
    if ($http_code !== 200) {
        wp_send_json_error("429"); // Triggera la pausa di sicurezza lato JS
    }

    $res_body = json_decode(wp_remote_retrieve_body($response), true);
    $raw_json = $res_body['candidates'][0]['content']['parts'][0]['text'] ?? '';

    // Parsing sicuro via Regex per estrarre solo il JSON
    preg_match('/\{.*\}/s', $raw_json, $matches);
    $data = json_decode($matches[0] ?? '', true);

    if (!$data || !isset($data['title'])) {
        wp_send_json_error("Errore parsing dati ricevuti dall'IA.");
    }

    // AGGIORNAMENTO POST
    wp_update_post([
        'ID'           => $pid,
        'post_title'   => sanitize_text_field($data['title']),
        'post_excerpt' => $data['short'],
        'post_content' => $data['long']
    ]);

    update_post_meta($pid, 'rank_math_focus_keyword', sanitize_text_field($data['kw']));
    update_post_meta($pid, 'rank_math_title', sanitize_text_field($data['seo_title']));
    update_post_meta($pid, 'rank_math_description', sanitize_text_field($data['seo_desc']));

    wp_send_json_success("OK");
}

// ==========================================================================
// 5. ROLLBACK LOGIC CON "MASS ROLLBACK" (RIPRISTINO MASSIVO)
// ==========================================================================
function mpe_render_seo_backup_tab() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'mpe_seo_logs';

    // Contiamo quanti log ci sono in totale
    $total_logs = $wpdb->get_var("SELECT COUNT(*) FROM $table_name");

    // Recuperiamo solo gli ultimi 50 log per la tabella visiva
    $logs = $wpdb->get_results("SELECT * FROM $table_name ORDER BY id DESC LIMIT 50");

    echo '<div class="mpe-card" style="border-left: 4px solid #f59e0b;">
            <div style="display:flex; justify-content:space-between; align-items:center;">
                <h3>⏪ Cronologia Ottimizzazioni (Rollback)</h3>';

    // Se ci sono log, mostriamo il bottone di Ripristino Massivo
    if ($total_logs > 0) {
        echo '<button id="btn-mass-rollback" class="btn-mpe btn-red" style="font-size:13px;">⏪ RIPRISTINA TUTTI I '.$total_logs.' PRODOTTI</button>';
    }

    echo '  </div>
            <p style="font-size:13px; color:#666;">Qui trovi i vecchi titoli e descrizioni salvati PRIMA dell\'intervento dell\'IA.</p>';

    // Area di caricamento per il Ripristino Massivo
    echo '  <div id="mass-rollback-area" style="display:none; margin-top:15px; padding:15px; background:#fef2f2; border:1px solid #fca5a5; border-radius:4px;">
                <div style="display:flex; justify-content:space-between; font-weight:bold; margin-bottom:10px; color:#991b1b;">
                    <span id="rollback-status-text">Recupero dati di backup in corso...</span>
                    <span id="rollback-counter">0 / '.$total_logs.'</span>
                </div>
                <div style="width:100%; background:#fecaca; border-radius:10px; height:20px; overflow:hidden;">
                    <div id="rollback-progress-bar" style="width:0%; height:100%; background:#ef4444; transition:0.3s;"></div>
                </div>
            </div>';

    if (empty($logs)) {
        echo '<div style="padding:15px; background:#f8fafc; text-align:center; color:#94a3b8; margin-top:15px;">Nessun backup presente al momento.</div>';
    } else {
        echo '<table class="mpe-table" id="rollback-table" style="width:100%; text-align:left; border-collapse: collapse; margin-top:15px;">
                <thead>
                    <tr style="border-bottom: 2px solid #e2e8f0;">
                        <th style="padding:10px;">Data</th>
                        <th style="padding:10px;">ID Prodotto</th>
                        <th style="padding:10px;">Titolo Precedente</th>
                        <th style="padding:10px;">Azione Singola</th>
                    </tr>
                </thead>
                <tbody>';
        foreach ($logs as $log) {
            echo '<tr id="log-row-'.$log->id.'" style="border-bottom: 1px solid #e2e8f0;">
                    <td style="padding:10px; font-size:12px; color:#64748b;">'.date('d/m/Y H:i', strtotime($log->date)).'</td>
                    <td style="padding:10px; font-weight:bold;">#'.$log->post_id.'</td>
                    <td style="padding:10px; font-size:13px;">'.esc_html($log->old_title).'</td>
                    <td style="padding:10px;">
                        <button class="btn-mpe" style="background:#475569; padding:5px 10px; font-size:11px;" onclick="mpe_seo_rollback_single('.$log->id.', '.$log->post_id.')">Ripristina</button>
                    </td>
                  </tr>';
        }
        echo '  </tbody>
              </table>';
    }
    echo '</div>';

    echo '<script>
    // --- 1. RIPRISTINO SINGOLO MANUALE ---
    function mpe_seo_rollback_single(logId, postId) {
        if (!confirm("Ripristinare i vecchi dati per il prodotto #" + postId + "?")) return;

        const btn = document.querySelector("#log-row-" + logId + " button");
        btn.innerText = "⏳..."; btn.disabled = true;

        fetch(ajaxurl, {
            method: "POST",
            headers: {"Content-Type": "application/x-www-form-urlencoded"},
            body: `action=mpe_seo_do_rollback&log_id=${logId}&post_id=${postId}`
        })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                document.getElementById("log-row-" + logId).style.backgroundColor = "#dcfce3";
                btn.innerText = "✅ Fatto";
                setTimeout(() => { document.getElementById("log-row-" + logId).remove(); }, 1500);
            } else {
                alert("Errore: " + data.data);
                btn.innerText = "Ripristina"; btn.disabled = false;
            }
        });
    }

    // --- 2. RIPRISTINO MASSIVO (AUTO-PILOT INVERSO) ---
    const btnMass = document.getElementById("btn-mass-rollback");
    if(btnMass) {
        btnMass.addEventListener("click", function() {
            if (!confirm("ATTENZIONE: Stai per ripristinare in massa TUTTI i prodotti attualmente nel registro storico. L\'operazione annullerà tutte le ottimizzazioni di questi file. Procedere?")) return;

            this.style.display = "none";
            document.getElementById("rollback-table").style.opacity = "0.3";
            document.getElementById("mass-rollback-area").style.display = "block";

            // Richiede tutta la coda di log al server
            fetch(ajaxurl, {
                method: "POST",
                headers: {"Content-Type": "application/x-www-form-urlencoded"},
                body: "action=mpe_seo_get_rollback_queue"
            })
            .then(res => res.json())
            .then(data => {
                if(data.success && data.data.length > 0) {
                    processMassRollback(data.data, 0);
                }
            });
        });
    }

    function processMassRollback(queue, index) {
        if (index >= queue.length) {
            document.getElementById("rollback-status-text").innerText = "✅ RIPRISTINO MASSIVO COMPLETATO!";
            setTimeout(() => { location.reload(); }, 2000);
            return;
        }

        const item = queue[index];
        document.getElementById("rollback-status-text").innerText = `Ripristino Prodotto ID ${item.post_id}...`;

        fetch(ajaxurl, {
            method: "POST",
            headers: {"Content-Type": "application/x-www-form-urlencoded"},
            body: `action=mpe_seo_do_rollback&log_id=${item.log_id}&post_id=${item.post_id}`
        })
        .then(res => res.json())
        .then(data => {
            index++;
            // Aggiorna barra
            const percent = (index / queue.length) * 100;
            document.getElementById("rollback-progress-bar").style.width = percent + "%";
            document.getElementById("rollback-counter").innerText = `${index} / ${queue.length}`;

            // Pausa brevissima (300ms) per non strozzare il database locale
            setTimeout(() => { processMassRollback(queue, index); }, 300);
        });
    }
    </script>';
}

// Estrae l'intera lista dei log per il ripristino massivo
add_action('wp_ajax_mpe_seo_get_rollback_queue', 'mpe_seo_get_rollback_queue_ajax');
function mpe_seo_get_rollback_queue_ajax() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'mpe_seo_logs';
    // Estrae gli ID in ordine crescente così partiamo dall'ottimizzazione più vecchia alla più nuova
    $results = $wpdb->get_results("SELECT id as log_id, post_id FROM $table_name ORDER BY id ASC", ARRAY_A);
    wp_send_json_success($results);
}

// Esegue materialmente il ripristino per il singolo ID (usato sia dal click manuale che dal Massivo)
add_action('wp_ajax_mpe_seo_do_rollback', 'mpe_seo_do_rollback_ajax');
function mpe_seo_do_rollback_ajax() {
    global $wpdb;
    $log_id = intval($_POST['log_id']);
    $post_id = intval($_POST['post_id']);
    $table_name = $wpdb->prefix . 'mpe_seo_logs';

    $log = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_name WHERE id = %d AND post_id = %d", $log_id, $post_id));
    if (!$log) {
        wp_send_json_error("Log non trovato nel database.");
    }

    // Ripristina Titolo ed Excerpt/Content originali
    wp_update_post([
        'ID'           => $post_id,
        'post_title'   => $log->old_title,
        'post_excerpt' => $log->old_short,
        'post_content' => $log->old_long
    ]);

    // Ripristina Metadati Rank Math
    if (!empty($log->old_kw)) {
        update_post_meta($post_id, 'rank_math_focus_keyword', $log->old_kw);
    } else {
        delete_post_meta($post_id, 'rank_math_focus_keyword');
    }

    if (!empty($log->old_seo_title)) {
        update_post_meta($post_id, 'rank_math_title', $log->old_seo_title);
    } else {
        delete_post_meta($post_id, 'rank_math_title');
    }

    if (!empty($log->old_seo_desc)) {
        update_post_meta($post_id, 'rank_math_description', $log->old_seo_desc);
    } else {
        delete_post_meta($post_id, 'rank_math_description');
    }

    // Elimina la riga dal log, confermando che il prodotto è tornato allo stato originale
    $wpdb->delete($table_name, ['id' => $log_id]);
    wp_send_json_success("Prodotto ripristinato.");
}
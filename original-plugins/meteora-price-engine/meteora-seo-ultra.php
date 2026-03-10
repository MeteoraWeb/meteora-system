<?php
if ( ! defined( 'ABSPATH' ) ) exit;

add_action('admin_init', 'mpe_seo_ultra_setup');
function mpe_seo_ultra_setup() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'mpe_seo_ultra_logs';
    if($wpdb->get_var("SHOW TABLES LIKE '$table_name'") != $table_name) {
        $charset_collate = $wpdb->get_charset_collate();
        $sql = "CREATE TABLE $table_name (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            object_id bigint(20) NOT NULL,
            object_type varchar(50) NOT NULL,
            old_data longtext NOT NULL,
            date datetime DEFAULT '0000-00-00 00:00:00' NOT NULL,
            PRIMARY KEY  (id)
        ) $charset_collate;";
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }
}

function mpe_render_seo_ultra_tab() {
    global $wpdb;
    $api_key = get_option('mpe_gemini_api_key', '');
    $woo_active = class_exists('WooCommerce');

    echo '<div class="mpe-card" style="border-left: 4px solid #ec4899;">
            <h3>Laboratorio SEO Ultra</h3>
            <p style="font-size: 13px; color: #666; margin-bottom: 15px;">Ottimizzazione universale per Pagine Articoli e riscrittura profonda per i Prodotti</p>';

    if (empty($api_key)) {
        echo '<p style="color: red; font-weight: bold;">Manca la chiave Gemini nel pannello principale</p></div>';
        return;
    }

    echo '
            <div class="mpe-grid-2" style="margin-bottom: 20px;">
                <div>
                    <label class="mpe-label">Area di Intervento</label>
                    <select id="ultra_target_type" class="mpe-input" onchange="mpeToggleUltraFilters(this.value)">
                        <option value="post">Articoli del Blog</option>
                        <option value="page">Pagine del sito</option>
                        <option value="category">Categorie Articoli</option>
                        <option value="post_tag">Tag Articoli</option>';

    if ($woo_active) {
        echo '          <option value="product_advanced">Officina Prodotti (URL e Immagini)</option>
                        <option value="product_cat">Categorie Prodotti (Woo)</option>
                        <option value="product_tag">Tag Prodotti (Woo)</option>
                        <option value="pwb-brand">Brand Prodotti (Woo)</option>';
    }

    echo '          </select>
                </div>
                <div>
                    <label class="mpe-label">Filtri e Operazioni</label>
                    <div style="background: #fdf2f8; padding: 12px; border: 1px solid #fbcfe8; border-radius: 4px;">
                        <div id="ultra_standard_filters">
                            <label style="display:block; font-size:12px; margin-bottom:8px;">
                                <input type="checkbox" id="ultra_exclude_done" checked> Ignora elementi già ottimizzati
                            </label>
                            <label style="display:block; font-size:12px; margin-bottom:8px; color:#be185d; font-weight:bold;">
                                <input type="checkbox" id="ultra_do_term_title"> Consenti modifica dei Titoli per Categorie e Tag
                            </label>
                            <label style="display:block; font-size:12px; margin-bottom:8px;">
                                <input type="number" id="ultra_batch" value="5" style="width:60px; padding:2px;"> Elementi per ciclo
                            </label>
                        </div>';

    if ($woo_active) {
        echo '          <div id="ultra_product_filters" style="display:none; margin-top:10px; border-top:1px solid #f9a8d4; padding-top:10px;">
                            <label style="display:block; font-size:12px; margin-bottom:8px; font-weight:bold; color:#be185d;">
                                <input type="checkbox" id="ultra_do_slug" checked> Riscrivi Slug URL (Genera Redirect 301)
                            </label>
                            <label style="display:block; font-size:12px; margin-bottom:8px; font-weight:bold; color:#be185d;">
                                <input type="checkbox" id="ultra_do_images" checked> Ottimizza Metadati Immagine
                            </label>
                            <label style="display:block; font-size:12px; margin-bottom:8px; color:#475569;">
                                <input type="checkbox" id="ultra_filter_unprocessed" checked> Applica solo ai prodotti MAI ottimizzati
                            </label>
                            <label style="display:block; font-size:12px; color:#475569;">
                                <input type="checkbox" id="ultra_filter_processed"> Forza l applicazione sui prodotti GIA ottimizzati nei log
                            </label>
                        </div>';
    }

    echo '          </div>
                </div>
            </div>

            <div style="display:flex; gap:10px;">
                <button type="button" id="btn-start-ultra" class="btn-mpe" style="background:#db2777; color:white; flex:1; justify-content:center; font-size:16px; padding:15px;">AVVIA MOTORE ULTRA</button>
                <button type="button" id="btn-stop-ultra" class="btn-mpe btn-red" style="display:none; flex:1; justify-content:center; font-size:16px; padding:15px;">FERMA TUTTO</button>
            </div>

            <div id="ultra-progress-area" style="display:none; margin-top:20px; padding:15px; background:#f8fafc; border:1px solid #cbd5e1; border-radius:4px;">
                <div style="display:flex; justify-content:space-between; font-weight:bold; margin-bottom:10px;">
                    <span id="ultra-status-text">Ricerca dati in corso</span>
                    <span id="ultra-counter">0 / 0</span>
                </div>
                <div style="width:100%; background:#e2e8f0; border-radius:10px; height:20px; overflow:hidden;">
                    <div id="ultra-progress-bar" style="width:0%; height:100%; background:#db2777; transition:0.3s;"></div>
                </div>
                <div id="ultra-log-window" style="margin-top:15px; height:200px; overflow-y:auto; background:#1e1e1e; color:#f472b6; padding:10px; font-family:monospace; font-size:11px; border-radius:4px;">
                    > Motore Ultra in attesa
                </div>
            </div>
          </div>';

    mpe_render_ultra_backup_tab();

    echo '<script>
    let isUltraRunning = false;

    function mpeToggleUltraFilters(val) {
        const prodFilters = document.getElementById("ultra_product_filters");
        if(prodFilters) prodFilters.style.display = (val === "product_advanced") ? "block" : "none";
    }

    mpeToggleUltraFilters(document.getElementById("ultra_target_type").value);

    document.getElementById("btn-start-ultra").addEventListener("click", function() {
        const targetType = document.getElementById("ultra_target_type").value;
        const batchCount = document.getElementById("ultra_batch").value;
        const excludeDone = document.getElementById("ultra_exclude_done").checked ? 1 : 0;
        const doTermTitle = document.getElementById("ultra_do_term_title") ? (document.getElementById("ultra_do_term_title").checked ? 1 : 0) : 0;

        let extra = `&do_term_title=${doTermTitle}`;
        if (targetType === "product_advanced") {
            const doSlug = document.getElementById("ultra_do_slug").checked ? 1 : 0;
            const doImg = document.getElementById("ultra_do_images").checked ? 1 : 0;
            const filterUnprocessed = document.getElementById("ultra_filter_unprocessed").checked ? 1 : 0;
            const filterProcessed = document.getElementById("ultra_filter_processed").checked ? 1 : 0;
            extra += `&do_slug=${doSlug}&do_img=${doImg}&filter_unp=${filterUnprocessed}&filter_pro=${filterProcessed}`;
        }

        isUltraRunning = true;
        document.getElementById("ultra-progress-area").style.display = "block";
        this.style.display = "none";
        document.getElementById("btn-stop-ultra").style.display = "flex";

        logUltra("Scansione archivio in corso");

        fetch(ajaxurl, {
            method: "POST",
            headers: {"Content-Type": "application/x-www-form-urlencoded"},
            body: `action=mpe_ultra_get_targets&target=${targetType}&limit=${batchCount}&exclude_done=${excludeDone}${extra}`
        })
        .then(res => res.json())
        .then(data => {
            if (!isUltraRunning) return;
            if(data.success && data.data.length > 0) {
                document.getElementById("ultra-counter").innerText = `0 / ${data.data.length}`;
                logUltra(`Trovati ${data.data.length} elementi inizio i lavori`);
                processUltraTarget(data.data, targetType, extra, 0);
            } else {
                logUltra("Nessun elemento trovato con questi filtri");
                resetUltraButton();
            }
        });
    });

    document.getElementById("btn-stop-ultra").addEventListener("click", function() {
        isUltraRunning = false;
        logUltra("Arresto forzato richiesto");
        this.disabled = true;
        this.innerText = "Attendere";
    });

    function processUltraTarget(items, type, extra, index) {
        if (!isUltraRunning) { resetUltraButton(); return; }
        if (index >= items.length) { logUltra("CICLO COMPLETATO"); resetUltraButton(); return; }

        const item = items[index];
        logUltra(`Modifica in corso su ID ${item.id} Titolo ${item.title.substring(0, 30)}`);

        fetch(ajaxurl, {
            method: "POST",
            headers: {"Content-Type": "application/x-www-form-urlencoded"},
            body: `action=mpe_ultra_process_single&id=${item.id}&type=${type}&title=${encodeURIComponent(item.title)}&content=${encodeURIComponent(item.content)}${extra}`
        })
        .then(res => res.json())
        .then(data => {
            if (!isUltraRunning) { resetUltraButton(); return; }
            if(data.success) {
                logUltra(`ID ${item.id} Lavoro concluso con successo`);
            } else {
                logUltra(`ID ${item.id} Fallito a causa di ${data.data}`);
            }
            index++;
            const percent = (index / items.length) * 100;
            document.getElementById("ultra-progress-bar").style.width = percent + "%";
            document.getElementById("ultra-counter").innerText = `${index} / ${items.length}`;
            setTimeout(() => { processUltraTarget(items, type, extra, index); }, 2000);
        })
        .catch(err => {
            if (!isUltraRunning) return;
            logUltra(`ID ${item.id} Errore server passo al prossimo`);
            index++;
            setTimeout(() => { processUltraTarget(items, type, extra, index); }, 3000);
        });
    }

    function logUltra(msg) {
        const win = document.getElementById("ultra-log-window");
        win.innerHTML += "<br>> " + msg;
        win.scrollTop = win.scrollHeight;
    }

    function resetUltraButton() {
        document.getElementById("btn-start-ultra").style.display = "flex";
        document.getElementById("btn-stop-ultra").style.display = "none";
        document.getElementById("btn-stop-ultra").disabled = false;
        document.getElementById("btn-stop-ultra").innerText = "FERMA TUTTO";
        isUltraRunning = false;
    }
    </script>';
}

add_action('wp_ajax_mpe_ultra_get_targets', 'mpe_ultra_get_targets_ajax');
function mpe_ultra_get_targets_ajax() {
    global $wpdb;
    $target = sanitize_text_field($_POST['target']);
    $limit = intval($_POST['limit']);
    $exclude_done = intval($_POST['exclude_done']);
    $table_logs = $wpdb->prefix . 'mpe_seo_ultra_logs';
    $old_logs = $wpdb->prefix . 'mpe_seo_logs';
    $items = [];

    if ($target === 'product_advanced') {
        $filter_unp = intval($_POST['filter_unp']);
        $filter_pro = intval($_POST['filter_pro']);

        $sql = "SELECT p.ID, p.post_title FROM {$wpdb->posts} p WHERE p.post_type = 'product' AND p.post_status = 'publish' ";

        if ($filter_unp && !$filter_pro) {
            $sql .= " AND p.ID NOT IN (SELECT post_id FROM $old_logs) ";
        } elseif (!$filter_unp && $filter_pro) {
            $sql .= " AND p.ID IN (SELECT post_id FROM $old_logs) ";
        }

        if ($exclude_done) {
            $sql .= " AND p.ID NOT IN (SELECT object_id FROM $table_logs WHERE object_type = 'product_advanced') ";
        }

        $sql .= " ORDER BY p.ID DESC LIMIT $limit";
        $results = $wpdb->get_results($sql);
        foreach ($results as $res) {
            $items[] = array('id' => $res->ID, 'title' => $res->post_title, 'content' => '');
        }

    } elseif ($target === 'post' || $target === 'page') {
        $args = array('post_type' => $target, 'post_status' => 'publish', 'posts_per_page' => $limit);
        if ($exclude_done) {
            $args['meta_query'] = array(array('key' => 'rank_math_focus_keyword', 'compare' => 'NOT EXISTS'));
        }
        $query = new WP_Query($args);
        foreach ($query->posts as $p) {
            $items[] = array('id' => $p->ID, 'title' => $p->post_title, 'content' => wp_trim_words(wp_strip_all_tags(do_shortcode($p->post_content)), 100));
        }
    } else {
        $args = array('taxonomy' => $target, 'hide_empty' => false, 'number' => $limit);
        if ($exclude_done) {
            $args['meta_query'] = array(array('key' => 'rank_math_focus_keyword', 'compare' => 'NOT EXISTS'));
        }
        $terms = get_terms($args);
        if (!is_wp_error($terms)) {
            foreach ($terms as $t) {
                $items[] = array('id' => $t->term_id, 'title' => $t->name, 'content' => wp_trim_words(wp_strip_all_tags($t->description), 100));
            }
        }
    }
    wp_send_json_success($items);
}

add_action('wp_ajax_mpe_ultra_process_single', 'mpe_ultra_process_single_ajax');
function mpe_ultra_process_single_ajax() {
    global $wpdb;
    $api_key = trim(get_option('mpe_gemini_api_key'));
    $id = intval($_POST['id']);
    $type = sanitize_text_field($_POST['type']);
    $title = sanitize_text_field($_POST['title']);
    $content = sanitize_text_field($_POST['content']);
    $table_logs = $wpdb->prefix . 'mpe_seo_ultra_logs';

    if (empty($api_key)) wp_send_json_error("Chiave mancante");

    if ($type === 'product_advanced') {
        $do_slug = intval($_POST['do_slug']);
        $do_img = intval($_POST['do_img']);

        $prompt = "Sei un SEO Tecnico per e-commerce. Analizza questo prodotto.
        REGOLE
        1. Rispondi in JSON puro
        2. Non usare mai i due punti nel testo
        3. Crea uno slug URL testuale pulito
        TITOLO PRODOTTO $title
        JSON
        {
          \"slug\" \"slug-prodotto-pulito\"";

        if ($do_img) {
            $prompt .= ",\n\"img_alt\" \"Testo alternativo profondo per Google Immagini\",\n\"img_desc\" \"Descrizione lunga del prodotto\"";
        }
        $prompt .= "\n}";

        $url = "https://generativelanguage.googleapis.com/v1beta/models/gemini-2.0-flash-lite:generateContent?key=" . $api_key;
        $body = json_encode(["contents" => [["parts" => [["text" => $prompt]]]], "generationConfig" => ["temperature" => 0.1, "response_mime_type" => "application/json"]]);
        $response = wp_remote_post($url, ['headers' => ['Content-Type' => 'application/json'], 'body' => $body, 'timeout' => 45, 'sslverify' => false]);

        if (is_wp_error($response)) wp_send_json_error("Errore Rete");
        $res_body = json_decode(wp_remote_retrieve_body($response), true);
        $raw_json = $res_body['candidates'][0]['content']['parts'][0]['text'] ?? '';
        preg_match('/\{.*\}/s', $raw_json, $matches);
        $data = json_decode($matches[0] ?? '', true);
        if (!$data) wp_send_json_error("Dati incomprensibili");

        $old_data = ['post_name' => get_post_field('post_name', $id)];

        if ($do_slug && !empty($data['slug'])) {
            wp_update_post(['ID' => $id, 'post_name' => sanitize_title($data['slug'])]);
        }

        if ($do_img && !empty($data['img_alt'])) {
            $thumb_id = get_post_thumbnail_id($id);
            if ($thumb_id) {
                $old_data['thumb_id'] = $thumb_id;
                $old_data['old_alt'] = get_post_meta($thumb_id, '_wp_attachment_image_alt', true);
                $old_data['old_title'] = get_post_field('post_title', $thumb_id);
                $old_data['old_excerpt'] = get_post_field('post_excerpt', $thumb_id);
                $old_data['old_content'] = get_post_field('post_content', $thumb_id);

                update_post_meta($thumb_id, '_wp_attachment_image_alt', sanitize_text_field($data['img_alt']));
                wp_update_post([
                    'ID' => $thumb_id,
                    'post_title' => sanitize_text_field($title),
                    'post_excerpt' => sanitize_text_field($data['img_alt']),
                    'post_content' => sanitize_text_field($data['img_desc']),
                    'post_name' => sanitize_title($data['slug'] . '-img')
                ]);
            }
        }

        $wpdb->insert($table_logs, [
            'object_id' => $id,
            'object_type' => $type,
            'old_data' => json_encode($old_data),
            'date' => current_time('mysql')
        ]);

        wp_send_json_success("OK");

    } else {
        $do_term_title = isset($_POST['do_term_title']) ? intval($_POST['do_term_title']) : 0;
        $is_term = ($type !== 'post' && $type !== 'page');

        $prompt = "Agisci da Esperto SEO. Ottimizza questo elemento WordPress $type.
        REGOLE
        1. Rispondi solo in JSON puro
        2. Non usare mai i due punti nei testi descrittivi
        TITOLO $title
        DATI $content
        JSON
        {
          \"kw\" \"Esattamente 5 keyword separate da virgola\",
          \"seo_title\" \"Meta Title max 60 car\",
          \"seo_desc\" \"Meta Desc max 160 car senza usare i due punti\",
          \"new_title\" \"Nuovo nome ottimizzato da usare come titolo\",
          \"desc\" \"Descrizione testuale lunga e persuasiva formattata in HTML\"
        }";

        $url = "https://generativelanguage.googleapis.com/v1beta/models/gemini-2.0-flash-lite:generateContent?key=" . $api_key;
        $body = json_encode(["contents" => [["parts" => [["text" => $prompt]]]], "generationConfig" => ["temperature" => 0.2, "response_mime_type" => "application/json"]]);
        $response = wp_remote_post($url, ['headers' => ['Content-Type' => 'application/json'], 'body' => $body, 'timeout' => 45, 'sslverify' => false]);

        if (is_wp_error($response)) wp_send_json_error("Errore Rete");
        $res_body = json_decode(wp_remote_retrieve_body($response), true);
        $raw_json = $res_body['candidates'][0]['content']['parts'][0]['text'] ?? '';
        preg_match('/\{.*\}/s', $raw_json, $matches);
        $data = json_decode($matches[0] ?? '', true);
        if (!$data) wp_send_json_error("Errore analisi");

        $old_data = [
            'kw' => get_metadata($is_term ? 'term' : 'post', $id, 'rank_math_focus_keyword', true),
            'seo_title' => get_metadata($is_term ? 'term' : 'post', $id, 'rank_math_title', true),
            'seo_desc' => get_metadata($is_term ? 'term' : 'post', $id, 'rank_math_description', true)
        ];

        if ($is_term) {
            $term_obj = get_term($id, $type);
            if (!is_wp_error($term_obj) && $term_obj !== null) {
                $old_data['desc'] = $term_obj->description;
                $old_data['name'] = $term_obj->name;

                $update_args = array('description' => wp_kses_post($data['desc']));
                if ($do_term_title && !empty($data['new_title'])) {
                    $update_args['name'] = sanitize_text_field($data['new_title']);
                }
                wp_update_term($id, $type, $update_args);
            }

            update_term_meta($id, 'rank_math_focus_keyword', sanitize_text_field($data['kw']));
            update_term_meta($id, 'rank_math_title', sanitize_text_field($data['seo_title']));
            update_term_meta($id, 'rank_math_description', sanitize_text_field($data['seo_desc']));
        } else {
            update_post_meta($id, 'rank_math_focus_keyword', sanitize_text_field($data['kw']));
            update_post_meta($id, 'rank_math_title', sanitize_text_field($data['seo_title']));
            update_post_meta($id, 'rank_math_description', sanitize_text_field($data['seo_desc']));
        }

        $wpdb->insert($table_logs, [
            'object_id' => $id,
            'object_type' => $type,
            'old_data' => json_encode($old_data),
            'date' => current_time('mysql')
        ]);

        wp_send_json_success("OK");
    }
}

function mpe_render_ultra_backup_tab() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'mpe_seo_ultra_logs';
    $total_logs = $wpdb->get_var("SELECT COUNT(*) FROM $table_name");

    echo '<div class="mpe-card" style="border-left: 4px solid #f59e0b; margin-top:20px;">
            <div style="display:flex; justify-content:space-between; align-items:center;">
                <h3>Gestione Dati e Ripristino Ultra</h3>';

    if ($total_logs > 0) {
        echo '<button id="btn-clear-ultra-logs" class="btn-mpe btn-red" style="font-size:13px; background:#ef4444;">SVUOTA LOG E ALLEGGERISCI DATABASE</button>';
    }

    echo '  </div>
            <p style="font-size:13px; color:#666;">Hai '.$total_logs.' operazioni memorizzate per questioni di sicurezza</p>
            <div style="display:flex; gap:10px; margin-top:15px;">
                <button id="btn-rollback-ultra" class="btn-mpe" style="background:#f59e0b; color:white; flex:1; justify-content:center;">ANNULLA TUTTE LE MODIFICHE ULTRA</button>
            </div>
          </div>';

    echo '<script>
    document.getElementById("btn-clear-ultra-logs")?.addEventListener("click", function() {
        if (!confirm("Questa operazione cancellerà la memoria storica e non potrai più fare rollback Sei sicuro?")) return;
        fetch(ajaxurl, { method: "POST", headers: {"Content-Type": "application/x-www-form-urlencoded"}, body: "action=mpe_ultra_clear_logs" })
        .then(res => res.json()).then(data => { alert("Database pulito"); location.reload(); });
    });

    document.getElementById("btn-rollback-ultra").addEventListener("click", function() {
        if (!confirm("Ripristino in massa dei dati originali Procediamo?")) return;
        this.disabled = true;
        this.innerText = "LAVORO IN CORSO...";
        fetch(ajaxurl, { method: "POST", headers: {"Content-Type": "application/x-www-form-urlencoded"}, body: "action=mpe_ultra_do_mass_rollback" })
        .then(res => res.json()).then(data => { alert("Ripristino completato"); location.reload(); });
    });
    </script>';
}

add_action('wp_ajax_mpe_ultra_clear_logs', 'mpe_ultra_clear_logs_ajax');
function mpe_ultra_clear_logs_ajax() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'mpe_seo_ultra_logs';
    $wpdb->query("TRUNCATE TABLE $table_name");
    wp_send_json_success("Pulito");
}

add_action('wp_ajax_mpe_ultra_do_mass_rollback', 'mpe_ultra_do_mass_rollback_ajax');
function mpe_ultra_do_mass_rollback_ajax() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'mpe_seo_ultra_logs';
    $logs = $wpdb->get_results("SELECT * FROM $table_name ORDER BY id DESC");

    foreach ($logs as $log) {
        $old = json_decode($log->old_data, true);
        if (!$old) continue;

        if ($log->object_type === 'product_advanced') {
            if (isset($old['post_name'])) {
                wp_update_post(['ID' => $log->object_id, 'post_name' => $old['post_name']]);
            }
            if (isset($old['thumb_id'])) {
                update_post_meta($old['thumb_id'], '_wp_attachment_image_alt', $old['old_alt']);
                wp_update_post([
                    'ID' => $old['thumb_id'],
                    'post_title' => $old['old_title'],
                    'post_excerpt' => $old['old_excerpt'],
                    'post_content' => $old['old_content']
                ]);
            }
        } else {
            $is_term = ($log->object_type !== 'post' && $log->object_type !== 'page');
            $meta_type = $is_term ? 'term' : 'post';

            if (!empty($old['kw'])) update_metadata($meta_type, $log->object_id, 'rank_math_focus_keyword', $old['kw']);
            else delete_metadata($meta_type, $log->object_id, 'rank_math_focus_keyword');

            if (!empty($old['seo_title'])) update_metadata($meta_type, $log->object_id, 'rank_math_title', $old['seo_title']);
            else delete_metadata($meta_type, $log->object_id, 'rank_math_title');

            if (!empty($old['seo_desc'])) update_metadata($meta_type, $log->object_id, 'rank_math_description', $old['seo_desc']);
            else delete_metadata($meta_type, $log->object_id, 'rank_math_description');

            if ($is_term) {
                $restore_args = array();
                if (isset($old['desc'])) $restore_args['description'] = $old['desc'];
                if (isset($old['name'])) $restore_args['name'] = $old['name'];
                if (!empty($restore_args)) {
                    wp_update_term($log->object_id, $log->object_type, $restore_args);
                }
            }
        }
    }
    $wpdb->query("TRUNCATE TABLE $table_name");
    wp_send_json_success("OK");
}
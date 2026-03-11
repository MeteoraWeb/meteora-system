<?php
namespace Meteora\Modules\ArticoliAi;

use Meteora\Core\Menu\MenuManager;
use Meteora\Core\Api\GeminiApi;
use Meteora\Core\Api\DeepSeekApi;

class NewsEngine {
    /**
     * @var NewsEngine
     */
    private static $instance = null;

    /**
     * @return NewsEngine
     */
    public static function instance() {
        if ( is_null( self::$instance ) ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        MenuManager::instance()->registerTab('tab-news', 'NEWS ENGINE', 'dashicons-welcome-write-blog', [$this, 'renderNewsTab']);

        add_action('admin_init', [$this, 'handlePostRequests']);

        add_action('mpe_news_cron_hook_1', [$this, 'runAutomatedNewsFactory']);
        add_action('mpe_news_cron_hook_2', [$this, 'runAutomatedNewsFactory']);
        add_action('mpe_news_cron_hook_3', [$this, 'runAutomatedNewsFactory']);

        add_action('wp_ajax_mpe_news_fetch_rss', [$this, 'fetchRssAjax']);
        add_action('wp_ajax_mpe_news_process_single', [$this, 'processSingleAjax']);
        add_action('wp_ajax_mpe_news_process_custom_links', [$this, 'processCustomLinksAjax']);
        add_action('wp_ajax_mpe_news_process_custom_topic', [$this, 'processCustomTopicAjax']);
    }

    public function handlePostRequests() {
        if (!current_user_can('manage_options')) {
            return;
        }

        if (isset($_POST['save_news_settings']) && isset($_POST['mpe_news_nonce']) && wp_verify_nonce($_POST['mpe_news_nonce'], 'mpe_news_action')) {
            update_option('mpe_pexels_api_key', sanitize_text_field($_POST['mpe_pexels_api_key']));
            update_option('mpe_gemini_api_key', sanitize_text_field($_POST['mpe_gemini_api_key_news']));
            update_option('mpe_deepseek_api_key', sanitize_text_field($_POST['mpe_deepseek_api_key_news']));
            update_option('mpe_news_ai_engine', sanitize_text_field($_POST['mpe_news_ai_engine']));
            update_option('mpe_news_rss_sources', sanitize_textarea_field($_POST['news_rss_urls']));
            update_option('mpe_news_fetch_count', intval($_POST['news_fetch_count']));
            update_option('mpe_news_process_count', intval($_POST['news_process_count']));

            $t1 = sanitize_text_field($_POST['news_cron_1']);
            $t2 = sanitize_text_field($_POST['news_cron_2']);
            $t3 = sanitize_text_field($_POST['news_cron_3']);

            update_option('mpe_news_cron_1', $t1);
            update_option('mpe_news_cron_2', $t2);
            update_option('mpe_news_cron_3', $t3);

            $auto_pilot = isset($_POST['news_auto_pilot']) ? 'yes' : 'no';
            update_option('mpe_news_auto_pilot', $auto_pilot);

            wp_clear_scheduled_hook('mpe_news_cron_hook_1');
            wp_clear_scheduled_hook('mpe_news_cron_hook_2');
            wp_clear_scheduled_hook('mpe_news_cron_hook_3');

            if ($auto_pilot === 'yes') {
                $this->scheduleDailyCron($t1, 'mpe_news_cron_hook_1');
                $this->scheduleDailyCron($t2, 'mpe_news_cron_hook_2');
                $this->scheduleDailyCron($t3, 'mpe_news_cron_hook_3');
            }
        }
    }

    private function scheduleDailyCron($time_string, $hook) {
        if (empty($time_string)) return;
        $tz = wp_timezone();
        $target = new \DateTime($time_string, $tz);
        if ($target->getTimestamp() <= time()) {
            $target->modify('+1 day');
        }
        wp_schedule_event($target->getTimestamp(), 'daily', $hook);
    }

    public function renderNewsTab() {
        global $wpdb;
        $gemini_key = get_option('mpe_gemini_api_key', '');
        $deepseek_key = get_option('mpe_deepseek_api_key', '');
        $ai_engine = get_option('mpe_news_ai_engine', 'gemini');
        $pexels_key = get_option('mpe_pexels_api_key', '');
        $saved_urls = get_option('mpe_news_rss_sources', '');
        $fetch_count = get_option('mpe_news_fetch_count', 2);
        $process_count = get_option('mpe_news_process_count', 2);

        $c1 = get_option('mpe_news_cron_1', '08:20');
        $c2 = get_option('mpe_news_cron_2', '13:20');
        $c3 = get_option('mpe_news_cron_3', '18:20');
        $is_auto = get_option('mpe_news_auto_pilot', 'no') === 'yes' ? 'checked' : '';

        echo '<div class="mpe-card" style="border-left: 4px solid #10b981;">
                <h3>Impostazioni Redazione AI</h3>
                <form method="post">
                    ' . wp_nonce_field("mpe_news_action", "mpe_news_nonce", true, false) . '
                    <div style="display:flex; gap:15px; align-items:flex-end; flex-wrap:wrap; margin-bottom:20px;">
                        <div style="flex:1; min-width:150px;">
                            <label class="mpe-label">Motore AI</label>
                            <select name="mpe_news_ai_engine" class="mpe-input">
                                <option value="gemini" '.selected($ai_engine, 'gemini', false).'>Google Gemini (Flash 2.0)</option>
                                <option value="deepseek" '.selected($ai_engine, 'deepseek', false).'>DeepSeek (Chat)</option>
                            </select>
                        </div>
                        <div style="flex:1; min-width:200px;">
                            <label class="mpe-label">API Key Gemini</label>
                            <input type="password" name="mpe_gemini_api_key_news" value="'.esc_attr($gemini_key).'" class="mpe-input" placeholder="Chiave Gemini...">
                        </div>
                        <div style="flex:1; min-width:200px;">
                            <label class="mpe-label">API Key DeepSeek</label>
                            <input type="password" name="mpe_deepseek_api_key_news" value="'.esc_attr($deepseek_key).'" class="mpe-input" placeholder="Chiave DeepSeek...">
                        </div>
                        <div style="flex:1; min-width:200px;">
                            <label class="mpe-label">API Key Pexels</label>
                            <input type="password" name="mpe_pexels_api_key" value="'.esc_attr($pexels_key).'" class="mpe-input" placeholder="Chiave Pexels...">
                        </div>
                    </div>

                    <div class="mpe-grid-2" style="margin-bottom:20px;">
                        <div>
                            <label class="mpe-label">Feed RSS (un URL per riga)</label>
                            <textarea name="news_rss_urls" id="news_rss_urls" class="mpe-input" style="height:120px; resize:vertical;" placeholder="Inserisci qui i tuoi indirizzi RSS...">'.esc_textarea($saved_urls).'</textarea>
                        </div>
                        <div>
                            <div style="display:flex; gap:10px; margin-bottom:15px;">
                                <div style="flex:1;">
                                    <label class="mpe-label">Notizie da leggere PER FONTE</label>
                                    <input type="number" name="news_fetch_count" id="news_fetch_count" class="mpe-input" value="'.esc_attr($fetch_count).'" min="1" max="20">
                                </div>
                                <div style="flex:1;">
                                    <label class="mpe-label">Articoli DA PUBBLICARE (Totali)</label>
                                    <input type="number" name="news_process_count" id="news_process_count" class="mpe-input" value="'.esc_attr($process_count).'" min="1" max="20">
                                </div>
                            </div>
                            <div style="background:#f8fafc; padding:15px; border:1px solid #cbd5e1; border-radius:4px;">
                                <label class="mpe-label" style="margin-bottom:10px;">Orari di Pubblicazione (Formato HH MM)</label>
                                <div style="display:flex; gap:10px; margin-bottom:10px;">
                                    <input type="time" name="news_cron_1" class="mpe-input" value="'.esc_attr($c1).'">
                                    <input type="time" name="news_cron_2" class="mpe-input" value="'.esc_attr($c2).'">
                                    <input type="time" name="news_cron_3" class="mpe-input" value="'.esc_attr($c3).'">
                                </div>
                                <label style="cursor:pointer; font-weight:bold; color:#0f172a; display:flex; align-items:center;">
                                    <input type="checkbox" name="news_auto_pilot" value="1" '.$is_auto.' style="margin-right:8px;"> ATTIVA PUBBLICAZIONE AUTOMATICA SUI 3 TURNI
                                </label>
                            </div>
                        </div>
                    </div>
                    <button type="submit" name="save_news_settings" class="btn-mpe btn-blue">Salva Impostazioni e Aggiorna Timer</button>
                </form>
            </div>';

        if ( ($ai_engine === 'gemini' && empty($gemini_key)) || ($ai_engine === 'deepseek' && empty($deepseek_key)) || empty($pexels_key)) {
            echo '<div class="mpe-card"><p style="color:red; font-weight:bold;">Inserisci le chiavi API per sbloccare la rotativa (AI e Pexels richiesti)</p></div>';
            return;
        }

        echo '<div class="mpe-grid-2">
                <div class="mpe-card">
                    <h3>Estrazione Automatica Massiva</h3>
                    <p style="font-size:13px; color:#666; margin-bottom:15px;">Il sistema pescherà dai feed RSS e pubblicherà esattamente gli articoli richiesti</p>
                    <button type="button" id="btn-start-news" class="btn-mpe btn-green" style="width:100%; justify-content:center; padding:15px;">ESTRAI E SCRIVI DA RSS</button>
                </div>

                <div class="mpe-card" style="border-left: 4px solid var(--m-blue);">
                    <h3>Scrittura Guidata da Link</h3>
                    <p style="font-size:13px; color:#666; margin-bottom:5px;">Incolla i link (anche più di uno) per fondere le fonti in un unico grande articolo</p>
                    <textarea id="custom_news_links" class="mpe-input" style="height:60px; margin-bottom:10px;" placeholder="https://..."></textarea>
                    <button type="button" id="btn-start-custom-news" class="btn-mpe btn-blue" style="width:100%; justify-content:center; padding:15px;">GENERA DA LINK</button>
                </div>
            </div>
            <div class="mpe-card" style="border-left: 4px solid var(--m-orange); margin-top: 20px;">
                <h3>Scrittura Libera da Argomento</h3>
                <p style="font-size:13px; color:#666; margin-bottom:5px;">Scrivi qui l\'argomento o il titolo e l\'intelligenza artificiale genererà un articolo originale senza fonti esterne.</p>
                <textarea id="custom_news_topic" class="mpe-input" style="height:60px; margin-bottom:10px;" placeholder="Es: Scrivi un articolo sui diamanti rosa e sul loro valore di mercato..."></textarea>
                <button type="button" id="btn-start-custom-topic" class="btn-mpe" style="background:var(--m-orange); color:#fff; width:100%; justify-content:center; padding:15px;">GENERA ARTICOLO DA ARGOMENTO</button>
            </div>';

        echo '<div id="news-progress-area" style="display:none; margin-top:10px; padding:15px; background:#f8fafc; border:1px solid #cbd5e1; border-radius:4px;">
                <div style="display:flex; justify-content:space-between; font-weight:bold; margin-bottom:10px;">
                    <span id="news-status-text">Inizializzazione...</span>
                    <span id="news-counter">0 / 0</span>
                </div>
                <div style="width:100%; background:#e2e8f0; border-radius:10px; height:20px; overflow:hidden;">
                    <div id="news-progress-bar" style="width:0%; height:100%; background:#10b981; transition:0.3s;"></div>
                </div>
                <div id="news-log-window" style="margin-top:15px; height:200px; overflow-y:auto; background:#1e1e1e; color:#a3e635; padding:10px; font-family:monospace; font-size:11px; border-radius:4px;">
                    > Terminale operativo
                </div>
            </div>';

        $table_name = $wpdb->prefix . 'mpe_news_logs';
        if ($wpdb->get_var("SHOW TABLES LIKE '{$table_name}'") == $table_name) {
            $logs = $wpdb->get_results("SELECT * FROM {$table_name} ORDER BY id DESC LIMIT 15");
            if (!empty($logs)) {
                echo '<div class="mpe-card" style="margin-top:20px;">
                        <h3>Ultime Pubblicazioni Effettuate</h3>
                        <table class="mpe-table">
                            <thead><tr><th>Data</th><th>ID Post</th><th>Tipo Fonte</th><th>Link Originali</th></tr></thead>
                            <tbody>';
                foreach ($logs as $log) {
                    echo '<tr>
                            <td style="width:150px;">'.date('d/m/Y H:i', strtotime($log->date)).'</td>
                            <td style="width:80px; font-weight:bold;">#'.$log->post_id.'</td>
                            <td style="width:100px;">'.strtoupper($log->source_type).'</td>
                            <td style="font-size:11px; word-break:break-all;">'.esc_html($log->source_links).'</td>
                        </tr>';
                }
                echo '      </tbody>
                        </table>
                    </div>';
            }
        }

        echo '<script>
        const newsNonce = "'.wp_create_nonce('mpe_news_ajax').'";
        document.getElementById("btn-start-news")?.addEventListener("click", function() {
            const rssUrls = document.getElementById("news_rss_urls").value.trim();
            const fetchCount = document.getElementById("news_fetch_count").value;
            const processCount = document.getElementById("news_process_count").value;

            if(!rssUrls) { alert("Inserisci i feed RSS"); return; }

            startNewsUI("Ricerca fonti inedite nel calderone RSS in corso");

            fetch(ajaxurl, {
                method: "POST",
                headers: {"Content-Type": "application/x-www-form-urlencoded"},
                body: `action=mpe_news_fetch_rss&rss_urls=${encodeURIComponent(rssUrls)}&fetch_count=${fetchCount}&process_count=${processCount}&nonce=${newsNonce}`
            })
            .then(res => res.json())
            .then(data => {
                if(data.success && data.data.length > 0) {
                    document.getElementById("news-counter").innerText = `0 / ${processCount}`;
                    logNews(`Trovate ${data.data.length} notizie esclusive e inedite Inizio stesura di ${processCount} articoli`);
                    processGuaranteedNews(data.data, parseInt(processCount), 0, 0);
                } else {
                    logNews("Tutte le notizie recenti sono già state pubblicate Nessuna fonte inedita trovata");
                    resetNewsUI();
                }
            }).catch(err => {
                logNews("Errore di rete durante il fetch");
                resetNewsUI();
            });
        });

        document.getElementById("btn-start-custom-topic")?.addEventListener("click", function() {
            const customTopic = document.getElementById("custom_news_topic").value.trim();
            if(!customTopic) { alert("Scrivi un argomento"); return; }

            startNewsUI("Creazione articolo originale basato sul tuo input...");
            document.getElementById("news-counter").innerText = `0 / 1`;

            fetch(ajaxurl, {
                method: "POST",
                headers: {"Content-Type": "application/x-www-form-urlencoded"},
                body: `action=mpe_news_process_custom_topic&topic=${encodeURIComponent(customTopic)}&nonce=${newsNonce}`
            })
            .then(res => res.json())
            .then(data => {
                if(data.success) {
                    logNews(`Articolo generato con successo ID Post ${data.data}`);
                    document.getElementById("news-progress-bar").style.width = "100%";
                    document.getElementById("news-counter").innerText = `1 / 1`;
                } else {
                    logNews(`Operazione fallita a causa di ${data.data}`);
                }
                setTimeout(() => { location.reload(); }, 1000);
            }).catch(err => {
                logNews("Errore server durante la generazione");
                resetNewsUI();
            });
        });

        document.getElementById("btn-start-custom-news")?.addEventListener("click", function() {
            const customLinks = document.getElementById("custom_news_links").value.trim();
            if(!customLinks) { alert("Incolla almeno un link"); return; }

            startNewsUI("Lettura approfondita dei link forniti in corso");
            document.getElementById("news-counter").innerText = `0 / 1`;

            fetch(ajaxurl, {
                method: "POST",
                headers: {"Content-Type": "application/x-www-form-urlencoded"},
                body: `action=mpe_news_process_custom_links&links=${encodeURIComponent(customLinks)}&nonce=${newsNonce}`
            })
            .then(res => res.json())
            .then(data => {
                if(data.success) {
                    logNews(`Articolo unico generato con successo ID Post ${data.data}`);
                    document.getElementById("news-progress-bar").style.width = "100%";
                    document.getElementById("news-counter").innerText = `1 / 1`;
                } else {
                    logNews(`Operazione fallita a causa di ${data.data}`);
                }
                setTimeout(() => { location.reload(); }, 1000);
            }).catch(err => {
                logNews("Errore server durante la generazione");
                resetNewsUI();
            });
        });

        function processGuaranteedNews(articles, targetCount, currentIndex, successfulCount) {
            if (successfulCount >= targetCount) {
                logNews(`LAVORO COMPLETATO Hai ottenuto i tuoi ${successfulCount} articoli`);
                setTimeout(() => { location.reload(); }, 1000);
                return;
            }

            if (currentIndex >= articles.length) {
                logNews(`FONTI ESAURITE Pubblicati solo ${successfulCount} articoli`);
                setTimeout(() => { location.reload(); }, 1000);
                return;
            }

            const item = articles[currentIndex];
            logNews(`Elaborazione IA su ${item.title.substring(0, 40)}`);

            fetch(ajaxurl, {
                method: "POST",
                headers: {"Content-Type": "application/x-www-form-urlencoded"},
                body: `action=mpe_news_process_single&title=${encodeURIComponent(item.title)}&content=${encodeURIComponent(item.content)}&link=${encodeURIComponent(item.link)}&nonce=${newsNonce}`
            })
            .then(res => res.json())
            .then(data => {
                if(data.success) {
                    successfulCount++;
                    logNews(`Articolo pubblicato perfettamente ID ${data.data}`);
                    const percent = (successfulCount / targetCount) * 100;
                    document.getElementById("news-progress-bar").style.width = percent + "%";
                    document.getElementById("news-counter").innerText = `${successfulCount} / ${targetCount}`;
                } else {
                    logNews(`Scartata ${data.data} Passo alla notizia di riserva`);
                }
                currentIndex++;
                setTimeout(() => { processGuaranteedNews(articles, targetCount, currentIndex, successfulCount); }, 500);
            }).catch(err => {
                logNews(`Errore rete Recupero notizia di scorta`);
                currentIndex++;
                setTimeout(() => { processGuaranteedNews(articles, targetCount, currentIndex, successfulCount); }, 500);
            });
        }

        function startNewsUI(msg) {
            document.getElementById("btn-start-news").disabled = true;
            document.getElementById("btn-start-custom-news").disabled = true;
            document.getElementById("news-progress-area").style.display = "block";
            logNews(msg);
        }

        function resetNewsUI() {
            document.getElementById("btn-start-news").disabled = false;
            document.getElementById("btn-start-custom-news").disabled = false;
        }

        function logNews(msg) {
            const win = document.getElementById("news-log-window");
            win.innerHTML += "<br>> " + msg;
            win.scrollTop = win.scrollHeight;
        }
        </script>';
    }

    public function runAutomatedNewsFactory() {
        @set_time_limit(300);
        $ai_engine = get_option('mpe_news_ai_engine', 'gemini');
        $api_key = ($ai_engine === 'deepseek') ? trim(get_option('mpe_deepseek_api_key')) : trim(get_option('mpe_gemini_api_key'));
        $pexels_key = trim(get_option('mpe_pexels_api_key'));
        $urls_raw = get_option('mpe_news_rss_sources', '');

        if (empty($urls_raw) || empty($api_key) || empty($pexels_key)) return;

        $urls = array_filter(array_map('trim', explode("\n", $urls_raw)));
        $fetch_count = intval(get_option('mpe_news_fetch_count', 2));
        $process_count = intval(get_option('mpe_news_process_count', 2));

        $articles = $this->fetchAndFilterRss($urls, $fetch_count, $process_count, $api_key, $ai_engine);
        if (empty($articles)) return;

        $success_count = 0;
        foreach ($articles as $art) {
            if ($success_count >= $process_count) break;
            $result = $this->processSingleArticle($art['title'], $art['content'], $art['link'], $api_key, $pexels_key, 'rss', $ai_engine);
            if (is_numeric($result)) {
                $success_count++;
            }
        }
    }

    public function fetchRssAjax() {
        check_ajax_referer('mpe_news_ajax', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Unauthorized');
        }

        $urls_raw = sanitize_textarea_field($_POST['rss_urls']);
        $urls = array_filter(array_map('trim', explode("\n", $urls_raw)));
        $fetch_count = intval($_POST['fetch_count']);
        $process_count = intval($_POST['process_count']);

        $ai_engine = get_option('mpe_news_ai_engine', 'gemini');
        $api_key = ($ai_engine === 'deepseek') ? trim(get_option('mpe_deepseek_api_key')) : trim(get_option('mpe_gemini_api_key'));

        $selected_articles = $this->fetchAndFilterRss($urls, $fetch_count, $process_count, $api_key, $ai_engine);

        if (empty($selected_articles)) {
            wp_send_json_error("Nessun contenuto trovato");
        } else {
            wp_send_json_success($selected_articles);
        }
    }

    public function processSingleAjax() {
        check_ajax_referer('mpe_news_ajax', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Unauthorized');
        }

        $ai_engine = get_option('mpe_news_ai_engine', 'gemini');
        $api_key = ($ai_engine === 'deepseek') ? trim(get_option('mpe_deepseek_api_key')) : trim(get_option('mpe_gemini_api_key'));
        $pexels_key = trim(get_option('mpe_pexels_api_key'));
        $raw_title = sanitize_text_field($_POST['title']);
        $raw_content = sanitize_text_field($_POST['content']);
        $source_link = esc_url_raw($_POST['link']);

        $result = $this->processSingleArticle($raw_title, $raw_content, $source_link, $api_key, $pexels_key, 'rss', $ai_engine);

        if (is_numeric($result)) {
            wp_send_json_success($result);
        } else {
            wp_send_json_error($result);
        }
    }

    public function processCustomTopicAjax() {
        check_ajax_referer('mpe_news_ajax', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Unauthorized');
        }

        $ai_engine = get_option('mpe_news_ai_engine', 'gemini');
        $api_key = ($ai_engine === 'deepseek') ? trim(get_option('mpe_deepseek_api_key')) : trim(get_option('mpe_gemini_api_key'));
        $pexels_key = trim(get_option('mpe_pexels_api_key'));
        $topic = sanitize_textarea_field($_POST['topic']);

        if (empty($topic) || empty($api_key)) wp_send_json_error("Dati mancanti");

        $result = $this->processSingleArticle("Generazione da Argomento", $topic, "Argomento personalizzato", $api_key, $pexels_key, 'custom_topic', $ai_engine);

        if (is_numeric($result)) {
            wp_send_json_success($result);
        } else {
            wp_send_json_error($result);
        }
    }

    public function processCustomLinksAjax() {
        check_ajax_referer('mpe_news_ajax', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Unauthorized');
        }

        $ai_engine = get_option('mpe_news_ai_engine', 'gemini');
        $api_key = ($ai_engine === 'deepseek') ? trim(get_option('mpe_deepseek_api_key')) : trim(get_option('mpe_gemini_api_key'));
        $pexels_key = trim(get_option('mpe_pexels_api_key'));
        $links_raw = sanitize_textarea_field($_POST['links']);
        $links = array_filter(array_map('trim', explode("\n", $links_raw)));

        if (empty($links) || empty($api_key)) wp_send_json_error("Dati mancanti");

        $combined_text = "";
        foreach ($links as $link) {
            $extracted = $this->extractFullContent(esc_url_raw($link));
            $combined_text .= "FONTE $link \n" . $extracted . "\n\n";
        }

        $result = $this->processSingleArticle("Generazione Multi-Link", $combined_text, implode(", ", $links), $api_key, $pexels_key, 'custom_links', $ai_engine);

        if (is_numeric($result)) {
            wp_send_json_success($result);
        } else {
            wp_send_json_error($result);
        }
    }

    private function extractFullContent($url) {
        $html_response = wp_remote_get($url, ['timeout' => 15]);
        if (is_wp_error($html_response) || wp_remote_retrieve_response_code($html_response) != 200) return "";

        $html = wp_remote_retrieve_body($html_response);

        $html = preg_replace('@<script[^>]*?>.*?</script>@si', '', $html);
        $html = preg_replace('@<style[^>]*?>.*?</style>@si', '', $html);
        $html = preg_replace('@<header[^>]*?>.*?</header>@si', '', $html);
        $html = preg_replace('@<footer[^>]*?>.*?</footer>@si', '', $html);
        $html = preg_replace('@<nav[^>]*?>.*?</nav>@si', '', $html);
        $html = preg_replace('@<aside[^>]*?>.*?</aside>@si', '', $html);

        preg_match_all('/<(p|h[1-6]|li)>(.*?)<\/\1>/is', $html, $matches);
        if (!empty($matches[2])) {
            $clean_extracted = wp_strip_all_tags(implode(' ', $matches[2]));
            return wp_trim_words($clean_extracted, 1500);
        }
        return "";
    }

    private function fetchAndFilterRss($urls, $fetch_count, $process_count, $api_key, $ai_engine = 'gemini') {
        global $wpdb;
        include_once(ABSPATH . WPINC . '/feed.php');
        $all_articles = [];
        $index = 0;

        foreach ($urls as $url) {
            if (empty($url)) continue;
            $rss = fetch_feed(esc_url_raw($url));
            if (!is_wp_error($rss)) {
                $maxitems = $rss->get_item_quantity($fetch_count);
                $rss_items = $rss->get_items(0, $maxitems);
                foreach ($rss_items as $item) {
                    $link = esc_url_raw($item->get_permalink());
                    $exists = $wpdb->get_var($wpdb->prepare("SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = '_mpe_source_link' AND meta_value = %s LIMIT 1", $link));

                    if (!$exists) {
                        $all_articles[] = [
                            'id' => $index,
                            'title' => wp_strip_all_tags($item->get_title()),
                            'content' => wp_strip_all_tags($item->get_description()),
                            'link' => $link
                        ];
                        $index++;
                    }
                }
            }
        }

        if (empty($all_articles)) return [];

        $ai_target = $process_count + 6;

        if (empty($api_key) || count($all_articles) <= $process_count) {
            shuffle($all_articles);
            return array_slice($all_articles, 0, $ai_target);
        }

        $all_categories = get_categories(['hide_empty' => false]);
        $cat_names = [];
        foreach ($all_categories as $cat) {
            $cat_names[] = $cat->name;
        }
        $available_categories_string = implode(', ', $cat_names);

        $trends_url = 'https://trends.google.com/trends/trendingsearches/daily/rss?geo=IT';
        $trends_rss = fetch_feed($trends_url);
        $trending_keywords = "";
        if (!is_wp_error($trends_rss)) {
            $max_trends = $trends_rss->get_item_quantity(15);
            $trend_items = $trends_rss->get_items(0, $max_trends);
            $trend_words = [];
            foreach ($trend_items as $t_item) {
                $trend_words[] = wp_strip_all_tags($t_item->get_title());
            }
            $trending_keywords = implode(", ", $trend_words);
        }

        $titles_list = "";
        foreach($all_articles as $a) {
            $titles_list .= "ID " . $a['id'] . " Titolo " . $a['title'] . "\n";
        }

        $prompt = "Agisci da Caporedattore. Le categorie ufficiali del nostro sito sono {$available_categories_string}. In questo preciso momento in Italia le persone stanno cercando su Google questi argomenti bollenti {$trending_keywords}. Ecco un calderone di notizie inedite raccolte da vari feed. Devi valutare la pertinenza di ogni notizia con le nostre categorie e incrociarle il piu possibile con le tendenze di ricerca attuali. Scarta la spazzatura. Tra quelle in target e possibilmente in trend seleziona ESATTAMENTE {$ai_target} notizie eliminando i doppioni. Rispondi solo con un array JSON puro contenente gli ID numerici delle scelte esempio [1, 4, 8]";

        if ($ai_engine === 'deepseek') {
            $raw_json = DeepSeekApi::generateContent($prompt . "\n\nNotizie\n" . $titles_list, $api_key, 0.1);
        } else {
            $raw_json = GeminiApi::generateContent($prompt . "\n\nNotizie\n" . $titles_list, $api_key, 0.1);
        }

        $selected_articles = [];
        if (!is_wp_error($raw_json)) {
            $chosen_ids = json_decode($raw_json, true);

            if (is_array($chosen_ids) && !empty($chosen_ids)) {
                foreach ($all_articles as $art) {
                    if (in_array($art['id'], $chosen_ids)) {
                        $selected_articles[] = $art;
                    }
                }
            }
        }

        if (empty($selected_articles)) {
            shuffle($all_articles);
            $selected_articles = array_slice($all_articles, 0, $ai_target);
        }

        return $selected_articles;
    }

    private function processSingleArticle($raw_title, $raw_content, $source_link, $api_key, $pexels_key, $source_type = 'rss', $ai_engine = 'gemini') {
        global $wpdb;

        if ($source_type === 'rss') {
            $exists = $wpdb->get_var($wpdb->prepare("SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = '_mpe_source_link' AND meta_value = %s LIMIT 1", $source_link));
            if ($exists) return "Notizia già in archivio";

            $extracted_text = $this->extractFullContent($source_link);
            $full_text = !empty($extracted_text) ? $extracted_text : $raw_content;
        } else {
            $full_text = $raw_content;
        }

        $all_categories = get_categories(['hide_empty' => false]);
        $cat_names = [];
        foreach ($all_categories as $cat) {
            $cat_names[] = $cat->name;
        }
        $available_categories_string = implode(' ', $cat_names);

        $prompt = "Sei un giornalista autorevole. Scrivi un articolo inestimabile basato sulle informazioni fornite.

        REGOLE TASSATIVE
        1. LINGUA Traduci ed elabora TUTTO l articolo e i relativi meta dati esclusivamente in lingua ITALIANA. È un obbligo tassativo assoluto.
        2. FORMATO Devi restituire esclusivamente un oggetto JSON puro.
        3. LUNGHEZZA L articolo DEVE superare le 400 parole. È un obbligo tassativo per la SEO.
        4. PUNTEGGIATURA Assolutamente VIETATO usare il carattere dei due punti nel testo e nei meta tag.
        5. STRUTTURA Usa testo discorsivo e tag HTML <p> e <h4> per creare paragrafi e utilizza il grassetto, sottolineato o corsivo per dare importanza alle parti del testo. Assolutamente vietate le liste e gli elenchi puntati.
        6. CATEGORIE Scegli minimo DUE categorie dall elenco fornito e includi sempre NEWS.
        7. KEYWORDS Devi generare e restituire ESATTAMENTE 5 keyword in italiano.

        ELENCO CATEGORIE
        {$available_categories_string}

        DATI
        Titolo Originale {$raw_title}
        Informazioni Raccolte {$full_text}

        STRUTTURA JSON TASSATIVA
        {
        \"title\" \"Titolo H1 persuasivo in lingua italiana\",
        \"content\" \"Testo lunghissimo in HTML in italiano di minimo 400 parole senza usare mai il simbolo dei due punti\",
        \"categories\" [\"Categoria1\", \"Categoria2\"],
        \"tags\" \"Esattamente 3 tag in italiano separati da virgola\",
        \"kw\" \"Esattamente 5 keyword in italiano separate da virgola\",
        \"seo_title\" \"Meta title max 60 caratteri in italiano\",
        \"seo_desc\" \"Meta desc max 160 caratteri in italiano senza mai usare il simbolo dei due punti\",
        \"pexel_keyword\" \"Frase specifica in inglese di 2 o 3 parole per cercare la foto\"
        }";

        if ($ai_engine === 'deepseek') {
            $raw_json = DeepSeekApi::generateContent($prompt, $api_key, 0.4);
        } else {
            $raw_json = GeminiApi::generateContent($prompt, $api_key, 0.4);
        }

        if (is_wp_error($raw_json)) return "Connessione AI fallita: " . $raw_json->get_error_message();

        $data = json_decode($raw_json, true);

        if (!$data || empty($data['title'])) return "Dati illeggibili";

        $thumbnail_id = 0;
        $rand_page = rand(1, 10);
        $search_url = "https://api.pexels.com/v1/search?query=" . urlencode($data['pexel_keyword']) . "&per_page=15&page=" . $rand_page;
        $pex_response = wp_remote_get($search_url, ['headers' => ['Authorization' => $pexels_key], 'timeout' => 15]);

        if (!is_wp_error($pex_response) && wp_remote_retrieve_response_code($pex_response) == 200) {
            $pex_data = json_decode(wp_remote_retrieve_body($pex_response), true);
            if (!empty($pex_data['photos'])) {
                require_once(ABSPATH . 'wp-admin/includes/media.php');
                require_once(ABSPATH . 'wp-admin/includes/file.php');
                require_once(ABSPATH . 'wp-admin/includes/image.php');

                shuffle($pex_data['photos']);

                foreach ($pex_data['photos'] as $photo) {
                    $pex_id = $photo['id'];
                    $already_used = $wpdb->get_var($wpdb->prepare("SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = '_mpe_pexels_id' AND meta_value = %s LIMIT 1", $pex_id));

                    if (!$already_used) {
                        $thumbnail_id = media_sideload_image($photo['src']['large'], 0, $data['title'], 'id');
                        if (!is_wp_error($thumbnail_id)) {
                            update_post_meta($thumbnail_id, '_wp_attachment_image_alt', sanitize_text_field($data['kw']));
                            update_post_meta($thumbnail_id, '_mpe_pexels_id', $pex_id);
                            break;
                        }
                    }
                }
            }
        }

        $post_data = array(
            'post_title'    => sanitize_text_field($data['title']),
            'post_content'  => wp_kses_post($data['content']),
            'post_status'   => 'publish',
            'post_author'   => get_current_user_id(),
            'post_type'     => 'post'
        );

        $new_post_id = wp_insert_post($post_data);

        if ($new_post_id) {
            if (!empty($data['categories']) && is_array($data['categories'])) {
                $cat_ids_to_assign = [];
                foreach ($data['categories'] as $cat_name) {
                    $cat_id = get_cat_ID(sanitize_text_field($cat_name));
                    if ($cat_id) {
                        $cat_ids_to_assign[] = $cat_id;
                    }
                }
                if (!empty($cat_ids_to_assign)) {
                    wp_set_post_categories($new_post_id, $cat_ids_to_assign);
                }
            }

            if (!empty($data['tags'])) {
                wp_set_post_tags($new_post_id, sanitize_text_field($data['tags']));
            }

            if ($thumbnail_id > 0 && !is_wp_error($thumbnail_id)) {
                set_post_thumbnail($new_post_id, $thumbnail_id);
            }

            update_post_meta($new_post_id, 'rank_math_focus_keyword', sanitize_text_field($data['kw']));
            update_post_meta($new_post_id, 'rank_math_title', sanitize_text_field($data['seo_title']));
            update_post_meta($new_post_id, 'rank_math_description', sanitize_text_field($data['seo_desc']));
            update_post_meta($new_post_id, '_mpe_source_link', $source_link);

            $wpdb->insert($wpdb->prefix . 'mpe_news_logs', [
                'post_id'      => $new_post_id,
                'source_type'  => $source_type,
                'source_links' => $source_link,
                'date'         => current_time('mysql')
            ]);

            return $new_post_id;
        } else {
            return "Errore scrittura database";
        }
    }
}

<?php

/**
 * Author: Meteora Web <https://meteoraweb.com>
 */

if(!defined('ABSPATH')){exit;}

class UCG_Email_Marketing {
    public static function init(){
        ucg_safe_add_submenu_page(
            null,
            __('Invio email', 'unique-coupon-generator'),
            __('Invio email', 'unique-coupon-generator'),
            'manage_options',
            'ucg-email',
            [__CLASS__,'page']
        );
    }

    private static function coupon_count($user_id,$set=''){
        $user=get_userdata($user_id);
        if(!$user) return 0;
        $meta=[['key'=>'customer_email','value'=>$user->user_email,'compare'=>'=']];
        if($set){
            $meta[]=['key'=>'base_coupon_code','value'=>$set,'compare'=>'='];
        }
        $q=new WP_Query(['post_type'=>'shop_coupon','posts_per_page'=>-1,'meta_query'=>$meta]);
        return $q->found_posts;
    }

    private static function coupon_status($user_id){
        $user=get_userdata($user_id);if(!$user) return 'never';
        $q=new WP_Query(['post_type'=>'shop_coupon','posts_per_page'=>1,'meta_query'=>[
            ['key'=>'customer_email','value'=>$user->user_email,'compare'=>'='],
            ['key'=>'used','compare'=>'EXISTS']
        ]]);
        if($q->found_posts>0){return 'used';}
        $cnt=self::coupon_count($user_id);
        return $cnt>0?'downloaded':'never';
    }

    public static function render_tab($context = array()){
        if(!current_user_can('manage_options')) return;
        global $wpdb;

        $city = sanitize_text_field($_GET['city'] ?? '');
        $age_from = intval($_GET['age_from'] ?? 0);
        $age_to = intval($_GET['age_to'] ?? 0);
        $set = sanitize_text_field($_GET['coupon_set'] ?? '');
        $status = sanitize_text_field($_GET['status'] ?? '');
        $source = sanitize_text_field($_GET['source'] ?? '');
        $source = in_array($source, array('coupon', 'event'), true) ? $source : '';
        $event_id = isset($_GET['event_id']) ? absint($_GET['event_id']) : 0;

        $events_list = array();
        if(function_exists('ucg_events_table')){
            $events_table = ucg_events_table('events');
            if($events_table && $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $events_table)) === $events_table){
                $events_list = $wpdb->get_results("SELECT id, titolo FROM {$events_table} ORDER BY data_evento DESC, id DESC");
            }
        }

        $rows = [];
        $now = current_time('timestamp');

        if($source !== 'event'){
            $users = get_users();
            foreach($users as $u){
                $count = self::coupon_count($u->ID, $set);
                $points = UCG_FidelityManager::get_user_points($u->ID);
                if(!$count && !$points) continue;
                $city_v = get_user_meta($u->ID, 'billing_city', true);
                if($city && stripos((string) $city_v, $city) === false) continue;
                $bd = get_user_meta($u->ID, 'birth_date', true);
                $age = null;
                if($bd){
                    $timestamp = strtotime($bd);
                    if($timestamp){
                        $age = max(0, floor(($now - $timestamp) / YEAR_IN_SECONDS));
                    }
                }
                if($age_from && ($age === null || $age < $age_from)) continue;
                if($age_to && ($age === null || $age > $age_to)) continue;
                $st = self::coupon_status($u->ID);
                if($status && $st !== $status) continue;
                $rows[] = [
                    'value'        => 'user:' . $u->ID,
                    'name'         => $u->first_name,
                    'last'         => $u->last_name,
                    'email'        => $u->user_email,
                    'city'         => $city_v,
                    'age'          => $age === null ? '' : $age,
                    'count'        => $count,
                    'status'       => $st,
                    'chip_class'   => 'never' === $st ? 'warning' : ('used' === $st ? 'success' : 'info'),
                    'details'      => self::get_status_label($st),
                    'source_label' => __('Coupon', 'unique-coupon-generator'),
                ];
            }
        }

        if($source !== 'coupon' && function_exists('ucg_events_table')){
            $tickets_table = ucg_events_table('tickets');
            $events_table = ucg_events_table('events');
            $tickets_table_exists = $tickets_table && $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $tickets_table)) === $tickets_table;
            $events_table_exists = $events_table && $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $events_table)) === $events_table;
            if($tickets_table_exists){
                $sql = "SELECT t.id, t.evento_id, t.utente_nome, t.utente_email, t.data_creazione";
                if($events_table_exists){
                    $sql .= ", e.titolo";
                }
                $sql .= " FROM {$tickets_table} AS t";
                if($events_table_exists){
                    $sql .= " LEFT JOIN {$events_table} AS e ON t.evento_id = e.id";
                }
                $sql .= " WHERE t.utente_email <> ''";
                $params = array();
                if($event_id){
                    $sql .= " AND t.evento_id = %d";
                    $params[] = $event_id;
                }
                $tickets = $params ? $wpdb->get_results($wpdb->prepare($sql, $params)) : $wpdb->get_results($sql);
                $attendees = array();
                foreach((array) $tickets as $ticket){
                    $email = sanitize_email($ticket->utente_email);
                    if(!$email){
                        continue;
                    }
                    $key = strtolower($email);
                    if(!isset($attendees[$key])){
                        $user = get_user_by('email', $email);
                        $first = $user ? $user->first_name : '';
                        $last = $user ? $user->last_name : '';
                        $city_v = $user ? get_user_meta($user->ID, 'billing_city', true) : '';
                        $age = '';
                        if($user){
                            $bd = get_user_meta($user->ID, 'birth_date', true);
                            if($bd){
                                $timestamp = strtotime($bd);
                                if($timestamp){
                                    $age = max(0, floor(($now - $timestamp) / YEAR_IN_SECONDS));
                                }
                            }
                        }
                        if((!$first || !$last) && !empty($ticket->utente_nome)){
                            $parts = preg_split('/\s+/', trim(($ticket->utente_nome ?? '')));
                            if(!$first && !empty($parts)){
                                $first = array_shift($parts);
                            }
                            if(!$last && !empty($parts)){
                                $last = implode(' ', $parts);
                            }
                        }
                        $attendees[$key] = array(
                            'email'         => $email,
                            'first'         => $first,
                            'last'          => $last,
                            'city'          => $city_v,
                            'age'           => $age,
                            'events'        => array(),
                            'count'         => 0,
                            'user_id'       => $user ? $user->ID : 0,
                            'fallback_name' => trim((string) ($ticket->utente_nome ?? '')),
                        );
                    }
                    $attendees[$key]['count']++;
                    if($events_table_exists && !empty($ticket->titolo)){
                        $attendees[$key]['events'][$ticket->evento_id] = $ticket->titolo;
                    }
                }

                if(!$status){
                    foreach($attendees as $attendee){
                        if($city && (!$attendee['city'] || stripos((string) $attendee['city'], $city) === false)){
                            continue;
                        }
                        if($age_from && ($attendee['age'] === '' || $attendee['age'] < $age_from)){
                            continue;
                        }
                        if($age_to && ($attendee['age'] === '' || $attendee['age'] > $age_to)){
                            continue;
                        }
                        $name_for_payload = $attendee['first'] || $attendee['last']
                            ? trim(($attendee['first'] ?? '') . ' ' . ($attendee['last'] ?? ''))
                            : $attendee['fallback_name'];
                        $payload = array(
                            'email'   => $attendee['email'],
                            'user_id' => $attendee['user_id'],
                            'name'    => $name_for_payload,
                        );
                        $payload_json = wp_json_encode($payload);
                        if(false === $payload_json){
                            continue;
                        }
                        $encoded = rawurlencode(base64_encode($payload_json));
                        $event_names = array_values(array_unique(array_filter(array_map('wp_strip_all_tags', $attendee['events']))));
                        $rows[] = array(
                            'value'        => 'event:' . $encoded,
                            'name'         => $attendee['first'],
                            'last'         => $attendee['last'],
                            'email'        => $attendee['email'],
                            'city'         => $attendee['city'],
                            'age'          => $attendee['age'],
                            'count'        => $attendee['count'],
                            'status'       => 'event',
                            'chip_class'   => 'accent',
                            'details'      => !empty($event_names)
                                ? sprintf(__('Eventi: %s', 'unique-coupon-generator'), implode(', ', $event_names))
                                : __('Ticket evento', 'unique-coupon-generator'),
                            'source_label' => __('Ticket evento', 'unique-coupon-generator'),
                        );
                    }
                }
            }
        }

        $coupon_sets = get_option('ucc_coupon_sets', []);
        $templates = get_option('ucg_email_templates', []);

        echo '<section class="ucg-card ucg-card--filters">';
        echo '<h2><span class="dashicons dashicons-admin-site-alt3" aria-hidden="true"></span> ' . esc_html__('Filtra destinatari', 'unique-coupon-generator') . '</h2>';
        echo '<form method="get" class="ucg-inline-form" data-ucg-loading="true">';
        echo '<input type="hidden" name="page" value="ucg-admin-marketing">';
        echo '<input type="hidden" name="tab" value="campaigns">';
        echo '<input type="text" name="city" placeholder="' . esc_attr__('Città', 'unique-coupon-generator') . '" value="' . esc_attr($city) . '">';
        echo '<input type="number" name="age_from" placeholder="' . esc_attr__('Età da', 'unique-coupon-generator') . '" value="' . esc_attr($age_from) . '">';
        echo '<input type="number" name="age_to" placeholder="' . esc_attr__('Età a', 'unique-coupon-generator') . '" value="' . esc_attr($age_to) . '">';
        echo '<select name="coupon_set">';
        echo '<option value="">' . esc_html__('Tutti i set', 'unique-coupon-generator') . '</option>';
        foreach($coupon_sets as $set_o){
            echo '<option value="' . esc_attr($set_o['name']) . '" ' . selected($set,$set_o['name'],false) . '>' . esc_html($set_o['name']) . '</option>';
        }
        echo '</select>';
        echo '<select name="status">';
        echo '<option value="">' . esc_html__('Stato verifica', 'unique-coupon-generator') . '</option>';
        echo '<option value="downloaded" ' . selected($status,'downloaded',false) . '>' . esc_html__('Scaricato non verificato', 'unique-coupon-generator') . '</option>';
        echo '<option value="used" ' . selected($status,'used',false) . '>' . esc_html__('Verificato', 'unique-coupon-generator') . '</option>';
        echo '<option value="never" ' . selected($status,'never',false) . '>' . esc_html__('Mai scaricato', 'unique-coupon-generator') . '</option>';
        echo '</select>';
        echo '<select name="source">';
        echo '<option value="">' . esc_html__('Tutti i destinatari', 'unique-coupon-generator') . '</option>';
        echo '<option value="coupon" ' . selected($source,'coupon',false) . '>' . esc_html__('Solo coupon', 'unique-coupon-generator') . '</option>';
        echo '<option value="event" ' . selected($source,'event',false) . '>' . esc_html__('Solo eventi', 'unique-coupon-generator') . '</option>';
        echo '</select>';
        if(!empty($events_list)){
            echo '<select name="event_id">';
            echo '<option value="">' . esc_html__('Tutti gli eventi', 'unique-coupon-generator') . '</option>';
            foreach($events_list as $event_row){
                $event_title = !empty($event_row->titolo)
                    ? $event_row->titolo
                    : sprintf(__('Evento #%d', 'unique-coupon-generator'), (int) $event_row->id);
                echo '<option value="' . esc_attr($event_row->id) . '" ' . selected($event_id,(int) $event_row->id,false) . '>' . esc_html($event_title) . '</option>';
            }
            echo '</select>';
        }
        echo '<button class="button button-primary ucg-button-spinner"><span class="ucg-button-text">' . esc_html__('Filtra', 'unique-coupon-generator') . '</span><span class="ucg-button-spinner__indicator" aria-hidden="true"></span></button>';
        echo '<a class="button" href="' . esc_url(ucg_admin_page_url('ucg-admin-marketing','campaigns')) . '">' . esc_html__('Azzera', 'unique-coupon-generator') . '</a>';
        echo '</form>';
        echo '</section>';

        echo '<form id="ucg-send-form" class="ucg-admin-form" data-ucg-loading="true">';
        wp_nonce_field('ucg_send_marketing','_wpnonce_ucg');
        echo '<div class="ucg-card ucg-card--table">';
        echo '<h2><span class="dashicons dashicons-megaphone" aria-hidden="true"></span> ' . esc_html__('Destinatari', 'unique-coupon-generator') . '</h2>';
        echo '<table class="widefat fixed striped">';
        echo '<thead><tr><th><input type="checkbox" id="ucg-check-all"></th><th>' . esc_html__('Nome', 'unique-coupon-generator') . '</th><th>' . esc_html__('Cognome', 'unique-coupon-generator') . '</th><th>' . esc_html__('Email', 'unique-coupon-generator') . '</th><th>' . esc_html__('Città', 'unique-coupon-generator') . '</th><th>' . esc_html__('Età', 'unique-coupon-generator') . '</th><th>' . esc_html__('Origine', 'unique-coupon-generator') . '</th><th>' . esc_html__('Totale coupon/ticket', 'unique-coupon-generator') . '</th><th>' . esc_html__('Dettagli', 'unique-coupon-generator') . '</th></tr></thead><tbody>';
        foreach($rows as $r){
            $chip_class = !empty($r['chip_class']) ? $r['chip_class'] : ('never' === ($r['status'] ?? '') ? 'warning' : ('used' === ($r['status'] ?? '') ? 'success' : 'info'));
            $details = $r['details'] ?? self::get_status_label($r['status'] ?? '');
            $source_label = $r['source_label'] ?? '';
            $city_display = $r['city'] !== '' ? $r['city'] : '—';
            $age_display = ($r['age'] === '' || $r['age'] === null) ? '—' : $r['age'];
            $count_display = isset($r['count']) ? $r['count'] : 0;
            echo '<tr>';
            echo '<td><input type="checkbox" class="ucg-user-check" value="' . esc_attr($r['value']) . '"></td>';
            echo '<td>' . esc_html($r['name']) . '</td>';
            echo '<td>' . esc_html($r['last']) . '</td>';
            echo '<td>' . esc_html($r['email']) . '</td>';
            echo '<td>' . esc_html($city_display) . '</td>';
            echo '<td>' . esc_html($age_display) . '</td>';
            echo '<td>' . esc_html($source_label ?: '—') . '</td>';
            echo '<td>' . esc_html($count_display) . '</td>';
            echo '<td><span class="ucg-chip ucg-chip--' . esc_attr($chip_class) . '">' . esc_html($details) . '</span></td>';
            echo '</tr>';
        }
        if(empty($rows)){
            echo '<tr><td colspan="9">' . esc_html__('Nessun destinatario corrisponde ai filtri scelti.', 'unique-coupon-generator') . '</td></tr>';
        }
        echo '</tbody></table>';
        echo '<p id="ucg-selected-count" class="ucg-selected-count">' . esc_html__('Selezionati: 0', 'unique-coupon-generator') . '</p>';
        echo '</div>';
        echo '<input type="hidden" name="user_ids" id="ucg-user-ids" value="">';

        echo '<div id="ucg-email-editor" class="ucg-card" style="display:none;">';
        echo '<h2><span class="dashicons dashicons-email-alt" aria-hidden="true"></span> ' . esc_html__('Componi email', 'unique-coupon-generator') . '</h2>';
        echo '<p>' . esc_html__('Destinatari selezionati:', 'unique-coupon-generator') . ' <strong id="ucg-count">0</strong></p>';
        echo '<div class="ucg-field">';
        echo '<label for="ucg-sender">' . esc_html__('Mittente', 'unique-coupon-generator') . '</label>';
        echo '<input type="email" id="ucg-sender" name="sender" placeholder="' . esc_attr('no-reply@dominio.it') . '" required>';
        echo '</div>';
        echo '<div class="ucg-field">';
        echo '<label for="ucg-subject">' . esc_html__('Oggetto', 'unique-coupon-generator') . '</label>';
        echo '<input type="text" id="ucg-subject" name="subject" placeholder="' . esc_attr__('Titolo della campagna', 'unique-coupon-generator') . '" required>';
        echo '</div>';
        echo '<div class="ucg-field" style="margin-bottom:15px; border-bottom: 1px solid #eee; padding-bottom: 15px;">';
        echo '<label style="font-weight:bold; margin-right:15px;">' . esc_html__('Modalità Composizione', 'unique-coupon-generator') . '</label>';
        echo '<label style="margin-right:10px;"><input type="radio" name="email_mode" value="visual" checked onchange="toggleEmailMode(this.value)"> Editor Visuale</label>';
        echo '<label><input type="radio" name="email_mode" value="html" onchange="toggleEmailMode(this.value)"> Codice HTML Puro</label>';
        echo '</div>';

        echo '<div class="ucg-field" id="template-selector-wrap">';
        echo '<label for="ucg-template-select">' . esc_html__('Template salvati', 'unique-coupon-generator') . '</label>';
        echo '<select id="ucg-template-select"><option value="">' . esc_html__('Scegli template', 'unique-coupon-generator') . '</option>';
        foreach($templates as $i=>$t){
            echo '<option value="' . esc_attr($i) . '">' . esc_html($t['name']) . '</option>';
        }
        echo '</select>';
        echo '</div>';

        echo '<div id="visual-editor-wrap">';
        wp_editor('', 'ucg_email_content', ['textarea_name'=>'content']);
        echo '</div>';

        echo '<div id="html-editor-wrap" style="display:none;">';
        echo '<p style="font-size:12px; color:#666;">Incolla qui il tuo codice HTML (Verrà inviato esattamente come scritto).</p>';
        echo '<textarea name="content_html" id="ucg_email_content_html" style="width:100%; height:300px; font-family:monospace; background:#1e1e1e; color:#a3e635; padding:15px;" placeholder="<html>..."></textarea>';
        echo '</div>';

        echo '<div class="ucg-form-actions">';
        echo '<button type="submit" id="ucg-send-btn" class="button button-primary" disabled>' . esc_html__('Invia email', 'unique-coupon-generator') . '</button>';
        echo '</div>';
        echo '</div>';
        echo '</form>';
        ?>
        <script>
        function toggleEmailMode(mode) {
            if (mode === 'visual') {
                document.getElementById('visual-editor-wrap').style.display = 'block';
                document.getElementById('template-selector-wrap').style.display = 'block';
                document.getElementById('html-editor-wrap').style.display = 'none';
            } else {
                document.getElementById('visual-editor-wrap').style.display = 'none';
                document.getElementById('template-selector-wrap').style.display = 'none';
                document.getElementById('html-editor-wrap').style.display = 'block';
            }
        }

        (function(){
            var all = document.getElementById('ucg-check-all');
            var checkNodes = document.querySelectorAll('.ucg-user-check');
            var checks = Array.prototype.slice.call(checkNodes || []);
            var ids = document.getElementById('ucg-user-ids');
            var cnt = document.getElementById('ucg-count');
            var editor = document.getElementById('ucg-email-editor');
            var countTxt = document.getElementById('ucg-selected-count');
            var btn = document.getElementById('ucg-send-btn');
            var templateSelect = document.getElementById('ucg-template-select');
            var templates = <?php echo wp_json_encode($templates); ?>;

            function update(){
                if(!ids || !cnt || !countTxt){
                    return;
                }
                var selected = [];
                for(var i = 0; i < checks.length; i++){
                    if(checks[i].checked){
                        selected.push(checks[i].value);
                    }
                }
                ids.value = selected.join(',');
                cnt.textContent = selected.length;
                countTxt.textContent = '<?php echo esc_js(__('Selezionati:', 'unique-coupon-generator')); ?> ' + selected.length;
                if(editor){
                    editor.style.display = selected.length ? 'block' : 'none';
                }
                if(btn){
                    btn.disabled = selected.length === 0;
                }
            }

            if(all){
                all.addEventListener('change', function(){
                    var checked = this.checked;
                    checks.forEach(function(c){
                        c.checked = checked;
                    });
                    update();
                });
            }

            checks.forEach(function(c){
                c.addEventListener('change', update);
            });

            update();

            if(templateSelect){
                templateSelect.addEventListener('change', function(){
                    var sel = this.value;
                    if(templates && templates[sel]){
                        var content = templates[sel].content || '';
                        if(typeof tinyMCE !== 'undefined' && tinyMCE.get('ucg_email_content')){
                            tinyMCE.get('ucg_email_content').setContent(content);
                        }else{
                            var textarea = document.getElementById('ucg_email_content');
                            if(textarea){
                                textarea.value = content;
                            }
                        }
                    }
                });
            }

            var sendForm = document.getElementById('ucg-send-form');
            if(sendForm){
                sendForm.addEventListener('submit', function(e){
                    e.preventDefault();
                    var data = new FormData(sendForm);
                    data.append('action','ucg_send_marketing');
                    var formEl = sendForm;
                    fetch(ajaxurl,{method:'POST',body:data})
                        .then(function(r){ return r.json(); })
                        .then(function(res){
                            var message = (res && typeof res.data !== 'undefined') ? res.data : '<?php echo esc_js(__('Risposta inattesa dal server.', 'unique-coupon-generator')); ?>';
                            alert(message);
                            if(res && res.success){
                                location.reload();
                            }
                        })
                        .catch(function(){
                            alert('<?php echo esc_js(__('Si è verificato un errore durante l\'invio.', 'unique-coupon-generator')); ?>');
                        })
                        .finally(function(){
                            if(window.ucgAdminUI){
                                window.ucgAdminUI.endLoading(formEl);
                            }
                        });
                });
            }
        })();
        </script>
        <?php
    }

    public static function page(){
        self::render_tab();
    }

    public static function send(){
        if(!current_user_can('manage_options')) {
            wp_send_json_error(__('Permessi insufficienti.', 'unique-coupon-generator'));
        }
        check_admin_referer('ucg_send_marketing', '_wpnonce_ucg');
        $raw_ids = array_filter(array_map('trim', explode(',', wp_unslash($_POST['user_ids'] ?? ''))));
        $subject = sanitize_text_field(wp_unslash($_POST['subject'] ?? ''));
        $sender = sanitize_email(wp_unslash($_POST['sender'] ?? ''));

        $mode = isset($_POST['email_mode']) ? sanitize_text_field($_POST['email_mode']) : 'visual';
        if ($mode === 'html') {
            // For HTML mode, we allow full HTML markup since the admin is doing email marketing
            $content = wp_unslash($_POST['content_html'] ?? '');
        } else {
            $content = wp_kses_post(wp_unslash($_POST['content'] ?? ''));
        }

        if(!$raw_ids || !$subject || !$content || !$sender) {
            wp_send_json_error(__('Dati mancanti.', 'unique-coupon-generator'));
        }
        if(!is_email($sender)) {
            wp_send_json_error(__('Mittente email non valido.', 'unique-coupon-generator'));
        }
        $recipients = array();
        foreach($raw_ids as $raw){
            if(strpos($raw ?? '', 'event:') === 0){
                $payload_raw = substr($raw ?? '', 6);
                $decoded = base64_decode(rawurldecode($payload_raw), true);
                if(!$decoded){
                    continue;
                }
                $data = json_decode($decoded, true);
                if(!is_array($data)){
                    continue;
                }
                $email = isset($data['email']) ? sanitize_email($data['email']) : '';
                if(!$email){
                    continue;
                }
                $user_id = isset($data['user_id']) ? intval($data['user_id']) : 0;
                $name = isset($data['name']) ? sanitize_text_field($data['name']) : '';
                if(!isset($recipients[$email])){
                    $recipients[$email] = array(
                        'user_id' => $user_id,
                        'name'    => $name,
                    );
                }else{
                    if(!$recipients[$email]['user_id'] && $user_id){
                        $recipients[$email]['user_id'] = $user_id;
                    }
                    if(!$recipients[$email]['name'] && $name){
                        $recipients[$email]['name'] = $name;
                    }
                }
            }else{
                $user_id = 0;
                if(strpos($raw ?? '', 'user:') === 0){
                    $user_id = intval(substr($raw ?? '', 5));
                }elseif(is_numeric($raw)){
                    $user_id = intval($raw);
                }
                if($user_id <= 0){
                    continue;
                }
                $user = get_userdata($user_id);
                if(!$user){
                    continue;
                }
                $email = sanitize_email($user->user_email);
                if(!$email){
                    continue;
                }
                $name = trim(($user->first_name ?? '') . ' ' . ($user->last_name ?? ''));
                if(!isset($recipients[$email])){
                    $recipients[$email] = array(
                        'user_id' => $user_id,
                        'name'    => $name,
                    );
                }else{
                    if(!$recipients[$email]['user_id']){
                        $recipients[$email]['user_id'] = $user_id;
                    }
                    if(!$recipients[$email]['name'] && $name){
                        $recipients[$email]['name'] = $name;
                    }
                }
            }
        }
        if(empty($recipients)){
            wp_send_json_error(__('Dati mancanti.', 'unique-coupon-generator'));
        }
        $from_name = wp_strip_all_tags(get_option('blogname'), true);
        $from_header = $from_name ? sprintf('%s <%s>', $from_name, $sender) : $sender;
        $headers = [
            'From: ' . $from_header,
            'Reply-To: ' . $sender,
            'Content-Type: text/html; charset=UTF-8',
        ];
        global $wpdb;
        $sent = 0;
        $failed = 0;
        foreach($recipients as $email => $info){
            $ok = wp_mail($email, $subject, $content, $headers);
            if($ok){
                $sent++;
            }else{
                $failed++;
            }
            $wpdb->insert($wpdb->prefix.'ucg_email_log',[
                'user_id'=>!empty($info['user_id']) ? intval($info['user_id']) : 0,
                'email'=>$email,
                'subject'=>$subject,
                'result'=>$ok?'ok':'fail',
                'attempts'=>1,
                'sent_at'=>current_time('mysql')
            ]);
        }
        $wpdb->insert($wpdb->prefix.'ucg_logs',[
            'action'=>'send_email',
            'user_id'=>get_current_user_id(),
            'timestamp'=>current_time('mysql')
        ]);
        if($sent === 0){
            wp_send_json_error(__('Nessuna email è stata inviata. Verifica le impostazioni di consegna.', 'unique-coupon-generator'));
        }
        $message = $failed > 0
            ? sprintf(__('Invio completato con avvisi: %1$d email inviate, %2$d non recapitate.', 'unique-coupon-generator'), $sent, $failed)
            : __('Email inviate correttamente.', 'unique-coupon-generator');
        wp_send_json_success($message);
    }

    private static function get_status_label($status){
        switch($status){
            case 'used':
                return __('Verificato', 'unique-coupon-generator');
            case 'downloaded':
                return __('Scaricato non verificato', 'unique-coupon-generator');
            case 'never':
            default:
                return __('Mai scaricato', 'unique-coupon-generator');
        }
    }
}

add_action('admin_menu',[UCG_Email_Marketing::class,'init'],20);
add_action('wp_ajax_ucg_send_marketing',[UCG_Email_Marketing::class,'send']);

function ucg_render_tab_marketing_email($context = array()){
    UCG_Email_Marketing::render_tab($context);
}

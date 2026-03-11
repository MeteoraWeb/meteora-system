<?php

/**
 * Author: Meteora Web <https://meteoraweb.com>
 */

if (!defined('ABSPATH')) {exit;}

class UCG_User_Profiles {
    public static function init(){
        ucg_safe_add_submenu_page(
            null,
            __('Anagrafiche utenti', 'unique-coupon-generator'),
            __('Anagrafiche utenti', 'unique-coupon-generator'),
            'manage_options',
            'ucg-user-profiles',
            [__CLASS__,'render_page']
        );
        ucg_safe_add_submenu_page(
            null,
            __('Modifica utente', 'unique-coupon-generator'),
            __('Modifica utente', 'unique-coupon-generator'),
            'manage_options',
            'ucg-edit-user',
            [__CLASS__,'render_edit_page']
        );
    }

    protected static function sanitize_filters($source){
        return [
            'name'     => sanitize_text_field($source['name'] ?? ''),
            'city'     => sanitize_text_field($source['city'] ?? ''),
            'age_from' => intval($source['age_from'] ?? 0),
            'age_to'   => intval($source['age_to'] ?? 0),
        ];
    }

    protected static function collect_users(array $filters, $limit = 50){
        $args = [
            'number' => $limit === -1 ? -1 : max(1, intval($limit)),
        ];

        if($filters['name']){
            $args['search'] = '*' . $filters['name'] . '*';
            $args['search_columns'] = ['display_name','user_nicename','user_email'];
        }

        $users = get_users($args);
        $results = [];
        foreach($users as $user){
            $birth_date = get_user_meta($user->ID,'birth_date',true);
            $birth_timestamp = $birth_date ? strtotime($birth_date) : false;
            $city = get_user_meta($user->ID,'billing_city',true);
            $age = $birth_timestamp ? floor((time() - $birth_timestamp) / 31556926) : 0;

            if($filters['city'] && stripos($city, $filters['city']) === false){
                continue;
            }

            if($filters['age_from'] && $age < $filters['age_from']){
                continue;
            }

            if($filters['age_to'] && $age > $filters['age_to']){
                continue;
            }

            $results[] = [
                'id'         => $user->ID,
                'first_name' => $user->first_name,
                'last_name'  => $user->last_name,
                'email'      => $user->user_email,
                'phone'      => get_user_meta($user->ID,'billing_phone',true),
                'city'       => $city,
                'birth_date' => $birth_date,
            ];
        }

        return $results;
    }

    public static function render_tab($context = array()){
        if(!current_user_can('manage_options')){
            return;
        }

        $filters = self::sanitize_filters($_GET);
        $users = self::collect_users($filters, 50);

        echo '<section class="ucg-card ucg-card--filters">';
        echo '<h2><span class="dashicons dashicons-admin-users" aria-hidden="true"></span> ' . esc_html__('Segmenta utenti', 'unique-coupon-generator') . '</h2>';
        echo '<form method="get" class="ucg-inline-form" data-ucg-loading="true">';
        echo '<input type="hidden" name="page" value="ucg-admin-marketing">';
        echo '<input type="hidden" name="tab" value="profiles">';
        echo '<input type="text" name="name" value="' . esc_attr($filters['name']) . '" placeholder="' . esc_attr__('Nome o email', 'unique-coupon-generator') . '">';
        echo '<input type="text" name="city" value="' . esc_attr($filters['city']) . '" placeholder="' . esc_attr__('Città', 'unique-coupon-generator') . '">';
        echo '<input type="number" name="age_from" value="' . esc_attr($filters['age_from']) . '" min="0" placeholder="' . esc_attr__('Età da', 'unique-coupon-generator') . '">';
        echo '<input type="number" name="age_to" value="' . esc_attr($filters['age_to']) . '" min="0" placeholder="' . esc_attr__('Età a', 'unique-coupon-generator') . '">';
        echo '<button type="submit" class="button button-primary ucg-button-spinner"><span class="ucg-button-text">' . esc_html__('Filtra', 'unique-coupon-generator') . '</span><span class="ucg-button-spinner__indicator" aria-hidden="true"></span></button>';
        echo '<a class="button" href="' . esc_url(ucg_admin_page_url('ucg-admin-marketing','profiles')) . '">' . esc_html__('Azzera', 'unique-coupon-generator') . '</a>';
        echo '</form>';
        echo '</section>';

        echo '<div class="ucg-export-actions">';
        echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
        wp_nonce_field('ucg_export_users_csv','_ucg_export_nonce');
        echo '<input type="hidden" name="action" value="ucg_export_users_csv">';
        echo '<input type="hidden" name="name" value="' . esc_attr($filters['name']) . '">';
        echo '<input type="hidden" name="city" value="' . esc_attr($filters['city']) . '">';
        echo '<input type="hidden" name="age_from" value="' . esc_attr($filters['age_from']) . '">';
        echo '<input type="hidden" name="age_to" value="' . esc_attr($filters['age_to']) . '">';
        echo '<button type="submit" class="button">' . esc_html__('Esporta CSV', 'unique-coupon-generator') . '</button>';
        echo '</form>';
        echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
        wp_nonce_field('ucg_export_users_pdf','_ucg_export_nonce');
        echo '<input type="hidden" name="action" value="ucg_export_users_pdf">';
        echo '<input type="hidden" name="name" value="' . esc_attr($filters['name']) . '">';
        echo '<input type="hidden" name="city" value="' . esc_attr($filters['city']) . '">';
        echo '<input type="hidden" name="age_from" value="' . esc_attr($filters['age_from']) . '">';
        echo '<input type="hidden" name="age_to" value="' . esc_attr($filters['age_to']) . '">';
        echo '<button type="submit" class="button">' . esc_html__('Esporta PDF', 'unique-coupon-generator') . '</button>';
        echo '</form>';
        echo '</div>';

        echo '<section class="ucg-card ucg-card--table">';
        echo '<h2><span class="dashicons dashicons-id-alt" aria-hidden="true"></span> ' . esc_html__('Anagrafiche disponibili', 'unique-coupon-generator') . '</h2>';
        echo '<table class="widefat fixed striped">';
        echo '<thead><tr><th>' . esc_html__('Nome', 'unique-coupon-generator') . '</th><th>' . esc_html__('Cognome', 'unique-coupon-generator') . '</th><th>' . esc_html__('Email', 'unique-coupon-generator') . '</th><th>' . esc_html__('Telefono', 'unique-coupon-generator') . '</th><th>' . esc_html__('Città', 'unique-coupon-generator') . '</th><th>' . esc_html__('Data Nascita', 'unique-coupon-generator') . '</th><th></th></tr></thead><tbody>';
        if(empty($users)){
            echo '<tr><td colspan="7">' . esc_html__('Nessun utente trovato con i filtri correnti.', 'unique-coupon-generator') . '</td></tr>';
        }else{
            foreach($users as $user){
                echo '<tr>';
                echo '<td>' . esc_html($user['first_name']) . '</td>';
                echo '<td>' . esc_html($user['last_name']) . '</td>';
                echo '<td>' . esc_html($user['email']) . '</td>';
                echo '<td>' . esc_html($user['phone']) . '</td>';
                echo '<td>' . esc_html($user['city']) . '</td>';
                echo '<td>' . esc_html($user['birth_date']) . '</td>';
                echo '<td><a class="button button-secondary" href="' . esc_url(admin_url('admin.php?page=ucg-edit-user&id=' . intval($user['id']))) . '">' . esc_html__('Modifica', 'unique-coupon-generator') . '</a></td>';
                echo '</tr>';
            }
        }
        echo '</tbody></table>';
        echo '</section>';
    }

    public static function render_page(){
        self::render_tab();
    }

    public static function export_csv(){
        if(!current_user_can('manage_options')){
            wp_die(__('Non hai i permessi per esportare i dati.', 'unique-coupon-generator'));
        }

        check_admin_referer('ucg_export_users_csv','_ucg_export_nonce');

        $filters = self::sanitize_filters($_POST);
        $users = self::collect_users($filters, -1);

        if(ob_get_length()){
            ob_end_clean();
        }

        nocache_headers();
        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="anagrafiche-utenti-' . date('Ymd-His') . '.csv"');

        $output = fopen('php://output','w');
        if($output === false){
            wp_die(__('Impossibile generare il file CSV.', 'unique-coupon-generator'));
        }

        // BOM per una migliore compatibilità con Excel
        echo "\xEF\xBB\xBF";

        fputcsv(
            $output,
            [
                __('Nome', 'unique-coupon-generator'),
                __('Cognome', 'unique-coupon-generator'),
                __('Email', 'unique-coupon-generator'),
                __('Telefono', 'unique-coupon-generator'),
                __('Città', 'unique-coupon-generator'),
                __('Data nascita', 'unique-coupon-generator'),
            ],
            ';'
        );
        foreach($users as $user){
            fputcsv($output,[
                $user['first_name'],
                $user['last_name'],
                $user['email'],
                $user['phone'],
                $user['city'],
                $user['birth_date'],
            ],';');
        }

        fclose($output);
        exit;
    }

    public static function export_pdf(){
        if(!current_user_can('manage_options')){
            wp_die(__('Non hai i permessi per esportare i dati.', 'unique-coupon-generator'));
        }

        check_admin_referer('ucg_export_users_pdf','_ucg_export_nonce');

        $filters = self::sanitize_filters($_POST);
        $users = self::collect_users($filters, -1);

        if(!class_exists('UCG_PDF_Exporter')){
            wp_die(__('Impossibile caricare il generatore PDF.', 'unique-coupon-generator'));
        }

        $pdf = new UCG_PDF_Exporter(__('Anagrafiche utenti', 'unique-coupon-generator'));
        $pdf->setHeaders([
            __('Nome', 'unique-coupon-generator'),
            __('Cognome', 'unique-coupon-generator'),
            __('Email', 'unique-coupon-generator'),
            __('Telefono', 'unique-coupon-generator'),
            __('Città', 'unique-coupon-generator'),
            __('Data nascita', 'unique-coupon-generator'),
        ]);

        if(empty($users)){
            $pdf->addMessage(__('Nessun dato disponibile.', 'unique-coupon-generator'));
        } else {
            foreach($users as $user){
                $pdf->addRow([
                    $user['first_name'],
                    $user['last_name'],
                    $user['email'],
                    $user['phone'],
                    $user['city'],
                    $user['birth_date'],
                ]);
            }
        }

        if(ob_get_length()){
            ob_end_clean();
        }

        $pdf->output('anagrafiche-utenti-' . date('Ymd-His') . '.pdf');
    }

    public static function render_edit_page(){
        if(!current_user_can('manage_options')) return;
        $user_id = intval($_GET['id']);
        $u = get_userdata($user_id);
        if(!$u) {
            echo '<div class="error"><p>' . esc_html__('Utente non trovato.', 'unique-coupon-generator') . '</p></div>';
            return;
        }
        ?>
        <div class="wrap ucg-settings-wrapper">
            <h1><?php esc_html_e('Modifica utente', 'unique-coupon-generator'); ?></h1>
            <form id="ucg-edit-user-form">
                <?php wp_nonce_field('ucg_save_user'); ?>
                <input type="hidden" name="user_id" value="<?php echo $user_id; ?>">
                <table class="form-table ucg-table">
                    <tr>
                        <th><?php esc_html_e('Nome', 'unique-coupon-generator'); ?></th>
                        <td><input type="text" name="first_name" value="<?php echo esc_attr($u->first_name); ?>"></td>
                    </tr>
                    <tr>
                        <th><?php esc_html_e('Cognome', 'unique-coupon-generator'); ?></th>
                        <td><input type="text" name="last_name" value="<?php echo esc_attr($u->last_name); ?>"></td>
                    </tr>
                    <tr>
                        <th><?php esc_html_e('Email', 'unique-coupon-generator'); ?></th>
                        <td><input type="email" name="email" value="<?php echo esc_attr($u->user_email); ?>"></td>
                    </tr>
                    <tr>
                        <th><?php esc_html_e('Telefono', 'unique-coupon-generator'); ?></th>
                        <td><input type="text" name="billing_phone" value="<?php echo esc_attr(get_user_meta($u->ID,'billing_phone',true)); ?>"></td>
                    </tr>
                    <tr>
                        <th><?php esc_html_e('Città', 'unique-coupon-generator'); ?></th>
                        <td><input type="text" name="billing_city" value="<?php echo esc_attr(get_user_meta($u->ID,'billing_city',true)); ?>"></td>
                    </tr>
                    <tr>
                        <th><?php esc_html_e('Data nascita', 'unique-coupon-generator'); ?></th>
                        <td><input type="date" name="birth_date" value="<?php echo esc_attr(get_user_meta($u->ID,'birth_date',true)); ?>"></td>
                    </tr>
                </table>
                <p><button type="submit" class="button button-primary"><?php esc_html_e('Salva', 'unique-coupon-generator'); ?></button></p>
            </form>
            <script>
            document.getElementById('ucg-edit-user-form').addEventListener('submit',function(e){
                e.preventDefault();
                const data = new FormData(this);
                const formEl = this;
                data.append('action','ucg_save_user');
                fetch(ajaxurl,{method:'POST',body:data})
                    .then(r=>r.json())
                    .then(res=>{
                        alert(res.data);
                        if(res.success){
                            location.href='<?php echo esc_url(admin_url('admin.php?page=ucg-admin-marketing&tab=profiles')); ?>';
                        }
                    })
                    .catch(()=>{
                        alert('<?php echo esc_js(__('Si è verificato un errore durante il salvataggio.', 'unique-coupon-generator')); ?>');
                    })
                    .finally(()=>{
                        if(window.ucgAdminUI){
                            window.ucgAdminUI.endLoading(formEl);
                        }
                    });
            });
            </script>
        </div>
        <?php
    }

    public static function save_user(){
        if(!current_user_can('manage_options')) {
            wp_send_json_error(__('Permessi insufficienti.', 'unique-coupon-generator'));
        }
        check_admin_referer('ucg_save_user');
        $user_id=intval($_POST['user_id']);
        wp_update_user(['ID'=>$user_id,'first_name'=>sanitize_text_field($_POST['first_name']),'last_name'=>sanitize_text_field($_POST['last_name']),'user_email'=>sanitize_email($_POST['email'])]);
        update_user_meta($user_id,'billing_phone',sanitize_text_field($_POST['billing_phone']));
        update_user_meta($user_id,'billing_city',sanitize_text_field($_POST['billing_city']));
        update_user_meta($user_id,'birth_date',sanitize_text_field($_POST['birth_date']));
        global $wpdb; $wpdb->insert($wpdb->prefix.'ucg_logs',['action'=>'edit_user','user_id'=>$user_id,'timestamp'=>current_time('mysql')]);
        wp_send_json_success(__('Profilo salvato con successo.', 'unique-coupon-generator'));
    }
}

// Register submenu after parent menu and expose AJAX/export handlers in every context
add_action('admin_menu',[UCG_User_Profiles::class,'init'],20);
add_action('wp_ajax_ucg_save_user',[UCG_User_Profiles::class,'save_user']);
add_action('admin_post_ucg_export_users_csv',[UCG_User_Profiles::class,'export_csv']);
add_action('admin_post_ucg_export_users_pdf',[UCG_User_Profiles::class,'export_pdf']);

function ucg_render_tab_marketing_profiles($context = array()){
    UCG_User_Profiles::render_tab($context);
}

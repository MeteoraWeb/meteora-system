<?php

/**
 * Author: Meteora Web <https://meteoraweb.com>
 */

if(!defined('ABSPATH')){exit;}

class UCG_Fidelity_Admin {
    public static function init(){
        ucg_safe_add_submenu_page(null,'Fidelity','Fidelity','manage_options','ucg-fidelity',[__CLASS__,'page']);
    }

    public static function page(){
        self::render_tab();
    }

    public static function render_tab($context = array()){
        if(!current_user_can('manage_options')){
            return;
        }

        global $wpdb;
        $sets_option = get_option('mms_coupon_sets',[]);
        $notice = array();

        if(isset($_POST['ucg_save_fidelity']) && check_admin_referer('ucg_save_fidelity')){
            foreach($sets_option as $sid=>$set){
                if(empty($set['fidelity']['enabled'])){
                    continue;
                }
                if(isset($_POST['points_per_euro'][$sid])){
                    $sets_option[$sid]['fidelity']['points_per_euro']=max(1,intval($_POST['points_per_euro'][$sid]));
                }
                if(isset($_POST['signup_points'][$sid])){
                    $sets_option[$sid]['fidelity']['signup_points']=max(0,intval($_POST['signup_points'][$sid]));
                }
            }
            update_option('mms_coupon_sets',$sets_option);
            $notice = array(
                'type'    => 'success',
                'message' => __('Impostazioni fidelity salvate con successo.', 'unique-coupon-generator'),
            );
        }

        $identifier = isset($_GET['user']) ? sanitize_text_field(wp_unslash($_GET['user'])) : '';
        $user_id = 0;
        $points = 0;
        $log = [];
        $users_list = [];

        if($identifier){
            $user_id = UCG_FidelityManager::validate_user($identifier);
            if($user_id){
                $points = UCG_FidelityManager::get_user_points($user_id);
                $log = UCG_FidelityManager::get_user_log($user_id,50);
            }
        }

        if(!$identifier){
            $table = $wpdb->prefix.'mms_fidelity_points';
            $users_list = $wpdb->get_results("SELECT user_id, SUM(points) AS balance, SUM(CASE WHEN points>0 THEN points ELSE 0 END) AS earned, SUM(CASE WHEN points<0 THEN -points ELSE 0 END) AS used, SUM(amount_spent) AS spent FROM $table GROUP BY user_id");
        }

        if(!empty($notice)){
            echo '<div class="notice notice-' . esc_attr($notice['type']) . '"><p>' . esc_html($notice['message']) . '</p></div>';
        }

        echo '<div class="ucg-grid ucg-grid--two">';
        echo '<section class="ucg-card">';
        echo '<h2><span class="dashicons dashicons-awards" aria-hidden="true"></span> ' . esc_html__('Parametri fidelity', 'unique-coupon-generator') . '</h2>';
        echo '<p class="ucg-card__intro">' . esc_html__('Gestisci i punti assegnati automaticamente per ciascun set con fidelity attivo.', 'unique-coupon-generator') . '</p>';
        if($sets_option){
            echo '<form method="post" class="ucg-admin-form" data-ucg-loading="true">';
            wp_nonce_field('ucg_save_fidelity');
            echo '<table class="ucg-table">';
            echo '<thead><tr><th>' . esc_html__('Set', 'unique-coupon-generator') . '</th><th>' . esc_html__('Punti per €', 'unique-coupon-generator') . '</th><th>' . esc_html__('Punti iscrizione', 'unique-coupon-generator') . '</th></tr></thead><tbody>';
            foreach($sets_option as $sid=>$set){
                if(empty($set['fidelity']['enabled'])){
                    continue;
                }
                $points_per = isset($set['fidelity']['points_per_euro']) ? $set['fidelity']['points_per_euro'] : 1;
                $signup = isset($set['fidelity']['signup_points']) ? $set['fidelity']['signup_points'] : 0;
                echo '<tr>';
                echo '<td><strong>' . esc_html($set['name']) . '</strong></td>';
                echo '<td><input type="number" name="points_per_euro[' . esc_attr($sid) . ']" min="1" value="' . esc_attr($points_per) . '"></td>';
                echo '<td><input type="number" name="signup_points[' . esc_attr($sid) . ']" min="0" value="' . esc_attr($signup) . '"></td>';
                echo '</tr>';
            }
            echo '</tbody></table>';
            echo '<div class="ucg-form-actions">';
            echo '<button type="submit" name="ucg_save_fidelity" class="button button-primary ucg-button-spinner"><span class="ucg-button-text">' . esc_html__('Salva impostazioni', 'unique-coupon-generator') . '</span><span class="ucg-button-spinner__indicator" aria-hidden="true"></span></button>';
            echo '</div>';
            echo '</form>';
        }else{
            echo '<p>' . esc_html__('Non ci sono set con fidelity attivo al momento.', 'unique-coupon-generator') . '</p>';
        }
        echo '</section>';

        echo '<section class="ucg-card">';
        echo '<h2><span class="dashicons dashicons-search" aria-hidden="true"></span> ' . esc_html__('Consulta punti cliente', 'unique-coupon-generator') . '</h2>';
        echo '<form method="get" class="ucg-inline-form">';
        echo '<input type="hidden" name="page" value="ucg-admin">';
        echo '<input type="hidden" name="tab" value="fidelity">';
        echo '<label class="screen-reader-text" for="ucg-fidelity-search">' . esc_html__('Email o codice QR', 'unique-coupon-generator') . '</label>';
        echo '<input type="text" id="ucg-fidelity-search" name="user" value="' . esc_attr($identifier) . '" placeholder="' . esc_attr__('Email cliente o codice QR', 'unique-coupon-generator') . '">';
        echo '<button type="submit" class="button button-secondary">' . esc_html__('Cerca', 'unique-coupon-generator') . '</button>';
        echo '</form>';

        if($identifier && !$user_id){
            echo '<p class="description">' . esc_html__('Nessun utente trovato con il valore indicato.', 'unique-coupon-generator') . '</p>';
        }

        if($user_id){
            $user = get_userdata($user_id);
            echo '<div class="ucg-subcard">';
            echo '<h3>' . esc_html__('Riepilogo', 'unique-coupon-generator') . '</h3>';
            echo '<p class="ucg-highlight">' . esc_html(sprintf(__('Punti totali: %s', 'unique-coupon-generator'), number_format_i18n($points))) . '</p>';
            if($user){
                echo '<p>' . esc_html($user->display_name) . ' · ' . esc_html($user->user_email) . '</p>';
            }
            echo '</div>';

            echo '<table class="ucg-table">';
            echo '<thead><tr><th>' . esc_html__('Data', 'unique-coupon-generator') . '</th><th>' . esc_html__('Set', 'unique-coupon-generator') . '</th><th>' . esc_html__('Punti', 'unique-coupon-generator') . '</th><th>' . esc_html__('Tipo', 'unique-coupon-generator') . '</th><th>' . esc_html__('Azione', 'unique-coupon-generator') . '</th><th>' . esc_html__('Importo', 'unique-coupon-generator') . '</th></tr></thead><tbody>';
            foreach($log as $row){
                echo '<tr>';
                echo '<td>' . esc_html(date_i18n('d/m/Y H:i',strtotime($row->created_at))) . '</td>';
                echo '<td>' . esc_html($row->set_id) . '</td>';
                echo '<td>' . esc_html($row->points) . '</td>';
                echo '<td><span class="ucg-chip ucg-chip--' . ('add' === $row->type ? 'success' : 'warning') . '">' . esc_html($row->type) . '</span></td>';
                echo '<td>' . esc_html($row->action) . '</td>';
                echo '<td>' . ($row->amount_spent ? esc_html(number_format($row->amount_spent,2)) : '-') . '</td>';
                echo '</tr>';
            }
            echo '</tbody></table>';
        }
        echo '</section>';
        echo '</div>';

        if(!$identifier){
            echo '<section class="ucg-card ucg-card--table">';
            echo '<h2><span class="dashicons dashicons-groups" aria-hidden="true"></span> ' . esc_html__('Clienti fidelizzati', 'unique-coupon-generator') . '</h2>';
            echo '<table class="widefat fixed striped">';
            echo '<thead><tr><th>' . esc_html__('Nome', 'unique-coupon-generator') . '</th><th>' . esc_html__('Cognome', 'unique-coupon-generator') . '</th><th>' . esc_html__('Email', 'unique-coupon-generator') . '</th><th>' . esc_html__('Telefono', 'unique-coupon-generator') . '</th><th>' . esc_html__('Punti', 'unique-coupon-generator') . '</th><th>' . esc_html__('Speso', 'unique-coupon-generator') . '</th><th>' . esc_html__('Utilizzati', 'unique-coupon-generator') . '</th></tr></thead><tbody>';
            foreach($users_list as $row){
                $user = get_userdata($row->user_id);
                if(!$user){
                    continue;
                }
                echo '<tr>';
                echo '<td>' . esc_html($user->first_name) . '</td>';
                echo '<td>' . esc_html($user->last_name) . '</td>';
                echo '<td>' . esc_html($user->user_email) . '</td>';
                echo '<td>' . esc_html(get_user_meta($row->user_id,'billing_phone',true)) . '</td>';
                echo '<td>' . esc_html(intval($row->balance)) . '</td>';
                echo '<td>' . esc_html(number_format($row->spent,2)) . '</td>';
                echo '<td>' . esc_html(intval($row->used)) . '</td>';
                echo '</tr>';
            }
            echo '</tbody></table>';
            echo '</section>';
        }
    }
}

add_action('admin_menu',[UCG_Fidelity_Admin::class,'init'],20);

function ucg_render_tab_coupon_fidelity($context = array()){
    UCG_Fidelity_Admin::render_tab($context);
}

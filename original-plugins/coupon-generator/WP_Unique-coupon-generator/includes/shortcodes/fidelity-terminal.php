<?php

/**
 * Author: Meteora Web <https://meteoraweb.com>
 */

if(!defined('ABSPATH')){exit;}

function ucg_fidelity_terminal_form(){
    $denied_message = '<div class="error"><p>' . esc_html__('Licenza non valida.', 'unique-coupon-generator') . '</p></div>';
    $blocked = ucg_block_when_forbidden('terminal_screen', $denied_message);
    if ($blocked !== null) {
        return $blocked;
    }

    $sets=UCG_FidelityManager::get_fidelity_sets();
    ob_start();
    include UCG_PLUGIN_DIR.'includes/views/fidelity-terminal-form.php';
    return ob_get_clean();
}

function ucg_handle_fidelity_terminal(){
    $denied_message = '<div class="error"><p>' . esc_html__('Licenza non valida.', 'unique-coupon-generator') . '</p></div>';
    $blocked = ucg_block_when_forbidden('terminal_action', $denied_message);
    if ($blocked !== null) {
        return $blocked;
    }

    if($_SERVER['REQUEST_METHOD']!=='POST') return '';
    if(empty($_POST['_wpnonce_ucg_fid']) || !wp_verify_nonce($_POST['_wpnonce_ucg_fid'],'ucg_fidelity_action')){
        return '<div class="error"><p>'.esc_html__('Nonce non valido.', 'unique-coupon-generator').'</p></div>';
    }
    $identifier=sanitize_text_field($_POST['fid_identifier']);
    $user_id=UCG_FidelityManager::validate_user($identifier);
    if(!$user_id){
        ucg_log_error('Fidelity terminal: utente non trovato - '.$identifier);
        return '<div class="error"><p>'.esc_html__('Utente non trovato.', 'unique-coupon-generator').'</p></div>';
    }
    $set=sanitize_text_field($_POST['fid_set']);
    $sets=UCG_FidelityManager::get_fidelity_sets();
    $points_added=0;$points_removed=0;
    if(isset($_POST['fid_amount'])){
        $amount=ucg_parse_float($_POST['fid_amount']);
        if($amount>0){
            $mult=floatval($sets[$set]['fidelity']['points_per_euro']??1);
            $points=round($amount*$mult);
            if($points>0){
                UCG_FidelityManager::add_points($user_id,$set,$points,'aggiunta','spesa',$amount);
                $points_added=$points;
            }
        }
    }
    if(isset($_POST['fid_deduct']) && intval($_POST['fid_deduct'])>0){
        $ded=intval($_POST['fid_deduct']);
        $balance=UCG_FidelityManager::get_user_points($user_id);
        if($balance>=$ded){
            UCG_FidelityManager::add_points($user_id,$set,-$ded,'rimozione','manuale');
            $points_removed=$ded;
        }else{
            ucg_log_error('Tentativo di scalare '.$ded.' punti con saldo '.$balance);
            return '<div class="error"><p>'.esc_html__('Punti insufficienti', 'unique-coupon-generator').'</p></div>';
        }
    }
    $bal=UCG_FidelityManager::get_user_points($user_id);
    $parts = [];
    if($points_added>0){
        $parts[] = sprintf(_n('%d punto aggiunto', '%d punti aggiunti', $points_added, 'unique-coupon-generator'), $points_added);
    }
    if($points_removed>0){
        $parts[] = sprintf(_n('%d punto scalato', '%d punti scalati', $points_removed, 'unique-coupon-generator'), $points_removed);
    }
    if(!$parts){
        $parts[] = esc_html__('Nessuna modifica ai punti', 'unique-coupon-generator');
    }
    $parts[] = sprintf(esc_html__('Saldo attuale: %d', 'unique-coupon-generator'), $bal);
    return '<div id="ucg-fid-message" class="updated"><p>'.esc_html(implode(', ', $parts)).'.</p></div>';
}

function ucg_fidelity_terminal_shortcode(){
    $denied_message = '<div class="error"><p>' . esc_html__('Licenza non valida.', 'unique-coupon-generator') . '</p></div>';
    $blocked = ucg_block_when_forbidden('terminal_shortcode', $denied_message);
    if ($blocked !== null) {
        return $blocked;
    }

    $msg='';
    if($_SERVER['REQUEST_METHOD']==='POST'){
        $msg=ucg_handle_fidelity_terminal();
    }
    return ($msg?:'').ucg_fidelity_terminal_form();
}
add_shortcode('ucg_fidelity_terminal','ucg_fidelity_terminal_shortcode');

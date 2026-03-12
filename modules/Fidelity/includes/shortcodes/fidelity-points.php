<?php

/**
 * Author: Meteora Web <https://meteoraweb.com>
 */

if(!defined('ABSPATH')){exit;}

function mms_fidelity_points_form($points=null,$log=[]){
    $denied_message = '<div id="ucg-fid-message" class="error"><p>' . esc_html__('Licenza non valida.', 'unique-coupon-generator') . '</p></div>';
    $blocked = ucg_block_when_forbidden('points_screen', $denied_message);
    if ($blocked !== null) {
        return $blocked;
    }

    ob_start();
    include UCG_PLUGIN_DIR.'includes/views/fidelity-points-display.php';
    return ob_get_clean();
}

function ucg_handle_fidelity_points(){
    $blocked = ucg_block_when_forbidden('points_action', array('error' => __('Licenza non valida.', 'unique-coupon-generator')));
    if ($blocked !== null) {
        return $blocked;
    }

    if($_SERVER['REQUEST_METHOD']!=='POST') return null;
    if(empty($_POST['_wpnonce_ucg_pts']) || !wp_verify_nonce($_POST['_wpnonce_ucg_pts'],'mms_fidelity_points')){
        return ['error'=>__('Nonce non valido','unique-coupon-generator')];
    }
    $identifier=sanitize_text_field($_POST['fid_identifier']);
    $user_id=UCG_FidelityManager::validate_user($identifier);
    if(!$user_id){
        ucg_log_error('Fidelity points: utente non trovato - '.$identifier);
        return ['error'=>__('Utente non trovato','unique-coupon-generator')];
    }
    $points=UCG_FidelityManager::get_user_points($user_id);
    $log=UCG_FidelityManager::get_user_log($user_id,5);
    return ['points'=>$points,'log'=>$log];
}

function mms_fidelity_points_shortcode(){
    $denied_message = '<div id="ucg-fid-message" class="error"><p>' . esc_html__('Licenza non valida.', 'unique-coupon-generator') . '</p></div>';
    $blocked = ucg_block_when_forbidden('points_shortcode', $denied_message);
    if ($blocked !== null) {
        return $blocked;
    }

    $data=null;
    if($_SERVER['REQUEST_METHOD']==='POST'){
        $data=ucg_handle_fidelity_points();
    }
    if(is_array($data) && isset($data['error'])){
        return '<div id="ucg-fid-message" class="error"><p>'.esc_html($data['error']).'</p></div>'.mms_fidelity_points_form();
    }
    $points=$data['points']??null;
    $log=$data['log']??[];
    return mms_fidelity_points_form($points,$log);
}
add_shortcode('ucg_fidelity_points','mms_fidelity_points_shortcode');

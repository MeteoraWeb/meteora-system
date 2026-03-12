<?php

/**
 * Author: Meteora Web <https://meteoraweb.com>
 */

if(!defined('ABSPATH')){exit;}

class UCG_FidelityManager {
    public static function get_user_points($user_id){
        if (!ucg_enforce_access_point('fidelity_balance')) {
            return 0;
        }

        global $wpdb;
        $table=$wpdb->prefix.'mms_fidelity_points';
        $added=$wpdb->get_var($wpdb->prepare(
            "SELECT COALESCE(SUM(points),0) FROM $table WHERE user_id=%d AND type='aggiunta'",
            $user_id
        ));
        $removed=$wpdb->get_var($wpdb->prepare(
            "SELECT COALESCE(SUM(ABS(points)),0) FROM $table WHERE user_id=%d AND type='rimozione'",
            $user_id
        ));
        return intval($added)-intval($removed);
    }

    public static function add_points($user_id,$set_id,$points,$type,$action,$amount=0){
        if (!ucg_enforce_access_point('fidelity_mutation')) {
            return false;
        }

        global $wpdb;
        $table = $wpdb->prefix.'mms_fidelity_points';

        $amount = ucg_parse_float($amount);

        $wpdb->insert($table,[
            'user_id'     => intval($user_id),
            'set_id'      => sanitize_text_field($set_id),
            'points'      => intval($points),
            'type'        => sanitize_text_field($type),
            'action'      => sanitize_text_field($action),
            'amount_spent'=> $amount,
            'created_at'  => current_time('mysql')
        ],[
            '%d','%s','%d','%s','%s','%f','%s'
        ]);
        if($wpdb->last_error){
            ucg_log_error(
                sprintf(
                    __('Errore durante l\'inserimento dei punti fidelity: %s', 'unique-coupon-generator'),
                    $wpdb->last_error
                )
            );
        }
    }

    public static function get_fidelity_sets(){
        if (!ucg_enforce_access_point('fidelity_catalog')) {
            return [];
        }

        $sets=get_option('mms_coupon_sets',[]);
        $out=[];
        foreach($sets as $id=>$set){
            if(!empty($set['fidelity']['enabled'])){
                if(empty($set['fidelity']['points_per_euro'])){
                    $set['fidelity']['points_per_euro']=1; // default multiplier
                }
                if(!isset($set['fidelity']['signup_points'])){
                    $set['fidelity']['signup_points']=0;
                }
                $out[$id]=$set;
            }
        }
        return $out;
    }

    public static function validate_user($identifier){
        if (!ucg_enforce_access_point('fidelity_probe')) {
            return 0;
        }

        $identifier = trim(($identifier ?? ''));

        // via email
        if(is_email($identifier)){
            $user = get_user_by('email', sanitize_email($identifier));
            if($user){
                return (int) $user->ID;
            }
        }

        // via coupon code (es. da QR)
        $coupon = plugin_get_page_by_title($identifier, OBJECT, 'shop_coupon');
        if($coupon){
            $email = get_post_meta($coupon->ID, 'customer_email', true);
            if($email){
                $user = get_user_by('email', $email);
                if($user){
                    return (int) $user->ID;
                }
            }
        }

        // deprecated: meta qr_code
        $user = get_users(['meta_key' => 'qr_code', 'meta_value' => $identifier, 'number' => 1]);
        if($user){
            return (int) $user[0]->ID;
        }

        return 0;
    }

    public static function get_user_log($user_id,$limit=5){
        if (!ucg_enforce_access_point('fidelity_stream')) {
            return [];
        }

        global $wpdb;
        $table=$wpdb->prefix.'mms_fidelity_points';
        return $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $table WHERE user_id=%d ORDER BY created_at DESC LIMIT %d",
            $user_id,
            $limit
        ));
    }
}

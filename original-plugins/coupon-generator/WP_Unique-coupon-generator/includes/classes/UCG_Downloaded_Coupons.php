<?php

/**
 * Author: Meteora Web <https://meteoraweb.com>
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}


class UCG_Downloaded_Coupons {
    public static function render_tabs() {
        $coupon_sets = get_option('ucc_coupon_sets', array());
        if (empty($coupon_sets)) {
            return;
        }
        ?>
        <div class="wrap ucg-settings-wrapper">
            <h2><?php esc_html_e('Coupon Scaricati per Set', 'unique-coupon-generator'); ?></h2>
            <div class="ucg-tabs">
                <?php foreach ($coupon_sets as $set) :
                    $set_slug = sanitize_title($set['name']); ?>
                    <a href="#" class="ucg-tab" data-tab="<?php echo esc_attr($set_slug); ?>"><?php echo esc_html($set['name']); ?></a>
                <?php endforeach; ?>
            </div>
            <?php foreach ($coupon_sets as $set) :
                $set_slug = sanitize_title($set['name']); ?>
                <div class="ucg-tab-content" id="tab-<?php echo esc_attr($set_slug); ?>" style="display:none;">
                    <?php self::render_table($set['name']); ?>
                </div>
            <?php endforeach; ?>
        </div>
        <script>
            document.addEventListener('DOMContentLoaded', function(){
                const tabs = document.querySelectorAll('.ucg-tab');
                const loadRows = function(table, offset){
                    const set = table.dataset.set;
                    const form = table.previousElementSibling;
                    const params = new URLSearchParams({action:'ucg_get_downloads',set:set,offset:offset});
                    if(form){
                        params.append('name',form.querySelector('[name="name"]').value);
                        params.append('email',form.querySelector('[name="email"]').value);
                        params.append('verified',form.querySelector('[name="verified"]').value);
                        params.append('from',form.querySelector('[name="from"]').value);
                        params.append('to',form.querySelector('[name="to"]').value);
                    }
                    fetch(ajaxurl+'?'+params.toString()).then(r=>r.json()).then(data=>{
                        if(data.success){
                            const tbody = table.querySelector('tbody');
                            data.data.rows.forEach(row=>{
                                const tr=document.createElement('tr');
                                tr.innerHTML='<td>'+row.name+'</td><td>'+row.email+'</td><td>'+row.date+'</td><td>'+row.verified+'</td>';
                                tbody.appendChild(tr);
                            });
                        }
                    });
                };
                document.querySelectorAll('.ucg-load-more').forEach(btn=>{
                    btn.addEventListener('click', function(){
                        const table = this.previousElementSibling;
                        let off = parseInt(this.dataset.offset);
                        loadRows(table, off);
                        this.dataset.offset = off + 20;
                    });
                });
                document.querySelectorAll('.ucg-filter-form').forEach(form=>{
                    form.addEventListener('submit',function(e){
                        e.preventDefault();
                        const table = form.nextElementSibling;
                        table.querySelector('tbody').innerHTML='';
                        form.nextElementSibling.nextElementSibling.dataset.offset = 0;
                        loadRows(table,0);
                    });
                });
                document.querySelectorAll('.ucg-export').forEach(btn=>{
                    btn.addEventListener('click', function(){
                        const table=this.previousElementSibling;
                        const form=table.previousElementSibling;
                        const params=new URLSearchParams({action:'ucg_export_downloads',set:this.dataset.set});
                        if(form){
                            params.append('name',form.querySelector('[name="name"]').value);
                            params.append('email',form.querySelector('[name="email"]').value);
                            params.append('verified',form.querySelector('[name="verified"]').value);
                            params.append('from',form.querySelector('[name="from"]').value);
                            params.append('to',form.querySelector('[name="to"]').value);
                        }
                        window.location = ajaxurl+'?'+params.toString();
                    });
                });
                tabs.forEach(tab => {
                    tab.addEventListener('click', function(e){
                        e.preventDefault();
                        tabs.forEach(t => t.classList.remove('active'));
                        tab.classList.add('active');
                        document.querySelectorAll('.ucg-tab-content').forEach(c => c.style.display='none');
                        const content=document.getElementById('tab-'+tab.dataset.tab);
                        content.style.display='block';
                        const table = content.querySelector('table');
                        if(table && table.querySelector('tbody').children.length===0){
                            loadRows(table,0);
                        }
                    });
                });
                if(tabs[0]){ tabs[0].click(); }
            });
        </script>
        <?php
    }

    public static function render_table($set_name) {
        ?>
        <form class="ucg-filter-form">
            <input type="text" name="name" placeholder="<?php esc_attr_e('Nome', 'unique-coupon-generator'); ?>">
            <input type="email" name="email" placeholder="<?php esc_attr_e('Email', 'unique-coupon-generator'); ?>">
            <select name="verified">
                <option value=""><?php esc_html_e('Verificato?', 'unique-coupon-generator'); ?></option>
                <option value="yes">✔</option>
                <option value="no">✖</option>
            </select>
            <input type="date" name="from">
            <input type="date" name="to">
            <button class="button ucg-filter-button" type="submit"><?php esc_html_e('Filtra', 'unique-coupon-generator'); ?></button>
        </form>
        <table class="wp-list-table widefat fixed striped ucg-download-table ucg-table" data-set="<?php echo esc_attr($set_name); ?>">
            <thead>
                <tr>
                    <th><?php esc_html_e('Nome', 'unique-coupon-generator'); ?></th>
                    <th><?php esc_html_e('Email', 'unique-coupon-generator'); ?></th>
                    <th><?php esc_html_e('Data Scaricamento', 'unique-coupon-generator'); ?></th>
                    <th><?php esc_html_e('Verificato', 'unique-coupon-generator'); ?></th>
                </tr>
            </thead>
            <tbody></tbody>
        </table>
        <button class="button ucg-load-more" data-offset="0" data-set="<?php echo esc_attr($set_name); ?>"><?php esc_html_e('Carica di più', 'unique-coupon-generator'); ?></button>
        <button class="button ucg-export" data-set="<?php echo esc_attr($set_name); ?>"><?php esc_html_e('Esporta CSV', 'unique-coupon-generator'); ?></button>
        <?php
    }
}

add_action('wp_ajax_ucg_get_downloads', 'ucg_ajax_get_downloads');
function ucg_ajax_get_downloads() {
    if (!current_user_can('manage_options')) {
        wp_send_json_error(__('Permessi insufficienti.', 'unique-coupon-generator'));
    }
    $set = sanitize_text_field($_GET['set'] ?? '');
    $offset = intval($_GET['offset'] ?? 0);
    $limit = 20;
    $args = array(
        'post_type' => 'shop_coupon',
        'posts_per_page' => $limit,
        'offset' => $offset,
        'post_status' => 'publish',
        'meta_query' => array(
            array('key' => 'base_coupon_code', 'value' => $set, 'compare' => '=')
        )
    );
    if(!empty($_GET['verified'])){
        $args['meta_query'][] = array('key' => 'used','value' => $_GET['verified']=='yes' ? 'Sì' : '', 'compare' => '=');
    }
    if(!empty($_GET['email'])){
        $args['meta_query'][] = array('key' => 'customer_email','value'=> sanitize_email($_GET['email']), 'compare' => '=');
    }
    if(!empty($_GET['name'])){
        $user_query = new WP_User_Query(array('search' => '*'.esc_attr($_GET['name']).'*', 'search_columns' => array('display_name', 'user_nicename', 'user_email')));
        $user_ids = wp_list_pluck($user_query->get_results(), 'ID');
        if(!empty($user_ids)){
            $args['meta_query'][] = array('key' => 'customer_email', 'value' => array_map(function($id){$u=get_userdata($id);return $u? $u->user_email:'';}, $user_ids),'compare'=>'IN');
        }
    }
    if(!empty($_GET['from']) || !empty($_GET['to'])){
        $from = sanitize_text_field($_GET['from']);
        $to   = sanitize_text_field($_GET['to']);
        $range = array();
        if($from) $range[] = $from;
        if($to)   $range[] = $to;
        $args['meta_query'][] = array('key'=>'download_date','value'=>$range,'compare'=>'BETWEEN','type'=>'DATE');
    }
    $query = new WP_Query($args);
    $rows = array();
    foreach ($query->posts as $coupon) {
        $email = get_post_meta($coupon->ID, 'customer_email', true);
        $user  = get_user_by('email', $email);
        $name  = $user ? $user->display_name : '';
        $date  = get_post_meta($coupon->ID, 'download_date', true);
        $verified = get_post_meta($coupon->ID, 'used', true) ? '✔' : '✖';
        $rows[] = array('name'=>$name,'email'=>$email,'date'=>$date,'verified'=>$verified);
    }
    wp_send_json_success(['rows'=>$rows]);
}

add_action('wp_ajax_ucg_export_downloads','ucg_ajax_export_downloads');
function ucg_ajax_export_downloads(){
    if(!current_user_can('manage_options')){wp_die();}
    $set = sanitize_text_field($_GET['set'] ?? '');
    $args = array(
        'post_type'=>'shop_coupon',
        'post_status'=>'publish',
        'posts_per_page'=>-1,
        'meta_query'=>array(
            array('key'=>'base_coupon_code','value'=>$set,'compare'=>'=')
        )
    );
    if(!empty($_GET['verified'])){
        $args['meta_query'][]=array('key'=>'used','value'=>$_GET['verified']=='yes'?'Sì':'','compare'=>'=');
    }
    if(!empty($_GET['email'])){
        $args['meta_query'][]=array('key'=>'customer_email','value'=>sanitize_email($_GET['email']),'compare'=>'=');
    }
    if(!empty($_GET['name'])){
        $user_query=new WP_User_Query(array('search'=>'*'.esc_attr($_GET['name']).'*','search_columns'=>array('display_name','user_nicename','user_email')));
        $user_ids=wp_list_pluck($user_query->get_results(),'ID');
        if(!empty($user_ids)){
            $args['meta_query'][]=array('key'=>'customer_email','value'=>array_map(function($id){$u=get_userdata($id);return $u?$u->user_email:'';},$user_ids),'compare'=>'IN');
        }
    }
    if(!empty($_GET['from']) || !empty($_GET['to'])){
        $range=array();
        $from=sanitize_text_field($_GET['from']);
        $to=sanitize_text_field($_GET['to']);
        if($from) $range[]=$from;
        if($to) $range[]=$to;
        $args['meta_query'][]=array('key'=>'download_date','value'=>$range,'compare'=>'BETWEEN','type'=>'DATE');
    }
    $query=new WP_Query($args);
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="downloads-'.$set.'.csv"');
    echo sprintf(
        "%s,%s,%s,%s\n",
        __('Nome', 'unique-coupon-generator'),
        __('Email', 'unique-coupon-generator'),
        __('Data', 'unique-coupon-generator'),
        __('Verificato', 'unique-coupon-generator')
    );
    foreach($query->posts as $coupon){
        $email=get_post_meta($coupon->ID,'customer_email',true);
        $user=get_user_by('email',$email);
        $name=$user? $user->display_name : '';
        $date=get_post_meta($coupon->ID,'download_date',true);
        $verified=get_post_meta($coupon->ID,'used',true)?'yes':'no';
        echo sprintf("%s,%s,%s,%s\n", esc_html($name), esc_html($email), esc_html($date), esc_html($verified));
    }
    exit;
}

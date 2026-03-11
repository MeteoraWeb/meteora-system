<?php

/**
 * Author: Meteora Web <https://meteoraweb.com>
 */

if(!defined('ABSPATH')){exit;}

class UCG_Email_Templates{
    public static function init(){
        ucg_safe_add_submenu_page(null,'Template Email','Template Email','manage_options','ucg-email-templates',[__CLASS__,'page']);
    }

    public static function render_tab($context = array()){
        if(!current_user_can('manage_options')) return;
        if(isset($_POST['template_name']) && check_admin_referer('ucg_save_templates')){
            $templates=[];
            if(is_array($_POST['template_name'])){
                foreach($_POST['template_name'] as $i=>$name){
                    $name=sanitize_text_field($name);
                    $content=wp_kses_post($_POST['template_content'][$i]);
                    if($name && $content){
                        $templates[]=['name'=>$name,'content'=>$content];
                    }
                }
            }
            update_option('ucg_email_templates',$templates);
            echo '<div class="notice notice-success"><p>' . esc_html__('Template salvati correttamente.', 'unique-coupon-generator') . '</p></div>';
        }
        $templates=get_option('ucg_email_templates',[]);
        ?>
        <form method="post" class="ucg-admin-form" data-ucg-loading="true">
            <?php wp_nonce_field('ucg_save_templates'); ?>
            <section class="ucg-card ucg-card--table">
                <h2><span class="dashicons dashicons-media-text" aria-hidden="true"></span> <?php esc_html_e('Template salvati', 'unique-coupon-generator'); ?></h2>
                <p class="ucg-card__intro"><?php esc_html_e('Personalizza i messaggi da riutilizzare per le tue campagne email.', 'unique-coupon-generator'); ?></p>
                <div id="ucg-template-rows">
                    <?php if(empty($templates)): ?>
                        <div class="ucg-subcard">
                            <div class="ucg-field">
                                <label><?php esc_html_e('Nome template', 'unique-coupon-generator'); ?></label>
                                <input type="text" name="template_name[]" placeholder="<?php esc_attr_e('Promo estiva', 'unique-coupon-generator'); ?>">
                            </div>
                            <div class="ucg-field">
                                <label><?php esc_html_e('Contenuto', 'unique-coupon-generator'); ?></label>
                                <textarea name="template_content[]" rows="6" placeholder="<?php esc_attr_e('Testo del messaggio', 'unique-coupon-generator'); ?>"></textarea>
                            </div>
                        </div>
                    <?php else: ?>
                        <?php foreach($templates as $i=>$t): ?>
                            <div class="ucg-subcard">
                                <div class="ucg-field">
                                    <label><?php esc_html_e('Nome template', 'unique-coupon-generator'); ?></label>
                                    <input type="text" name="template_name[]" value="<?php echo esc_attr($t['name']); ?>">
                                </div>
                                <div class="ucg-field">
                                    <label><?php esc_html_e('Contenuto', 'unique-coupon-generator'); ?></label>
                                    <?php wp_editor($t['content'],'template_content_'.$i,['textarea_name'=>'template_content[]','textarea_rows'=>8]); ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
                <p><button type="button" class="button" id="ucg-add-template"><?php esc_html_e('Aggiungi template', 'unique-coupon-generator'); ?></button></p>
            </section>
            <div class="ucg-form-actions">
                <button type="submit" class="button button-primary ucg-button-spinner">
                    <span class="ucg-button-text"><?php esc_html_e('Salva template', 'unique-coupon-generator'); ?></span>
                    <span class="ucg-button-spinner__indicator" aria-hidden="true"></span>
                </button>
            </div>
        </form>
        <script>
        document.getElementById('ucg-add-template').addEventListener('click',function(){
            const container=document.getElementById('ucg-template-rows');
            const block=document.createElement('div');
            block.className='ucg-subcard';
            block.innerHTML='<div class="ucg-field"><label><?php echo esc_js(__('Nome template', 'unique-coupon-generator')); ?></label><input type="text" name="template_name[]" placeholder="<?php echo esc_js(__('Nuovo template', 'unique-coupon-generator')); ?>"></div><div class="ucg-field"><label><?php echo esc_js(__('Contenuto', 'unique-coupon-generator')); ?></label><textarea name="template_content[]" rows="6"></textarea></div>';
            container.appendChild(block);
        });
        </script>
        <?php
    }

    public static function page(){
        self::render_tab();
    }
}
add_action('admin_menu',[UCG_Email_Templates::class,'init'],20);

function ucg_render_tab_marketing_templates($context = array()){
    UCG_Email_Templates::render_tab($context);
}

<?php

/**
 * Author: Meteora Web <https://meteoraweb.com>
 */


// Impedisce l'accesso diretto ai file
if (!defined('ABSPATH')) {
    exit;
}

// Funzione per creare pagine per i coupon
function ucc_create_pages_page() {
    if (isset($_POST['create_page'])) {
        $page_type = sanitize_text_field($_POST['page_type']);
        $page_title = sanitize_text_field($_POST['page_title']);
        $page_content = sanitize_textarea_field($_POST['page_content']);
        $page_shortcode = sanitize_text_field($_POST['page_shortcode']);

        $page_id = wp_insert_post(array(
            'post_title'   => $page_title,
            'post_content' => $page_content . ' [' . $page_shortcode . ']',
            'post_status'  => 'publish',
            'post_type'    => 'page'
        ));

        if (is_wp_error($page_id)) {
            echo '<div class="error"><p>' . esc_html__('Errore nella creazione della pagina.', 'unique-coupon-generator') . '</p></div>';
        } else {
            echo '<div class="updated"><p>' . esc_html__('Pagina creata con successo.', 'unique-coupon-generator') . '</p></div>';
        }
    }

    ?>
    <div class="wrap ucg-settings-wrapper">
        <h1><?php esc_html_e('Crea pagine', 'unique-coupon-generator'); ?></h1>
        <form method="post" action="">
            <table class="ucc-form-table ucg-table">
                <tr>
                    <th><label for="page_type"><?php esc_html_e('Tipo di pagina', 'unique-coupon-generator'); ?></label></th>
                    <td>
                        <select name="page_type" id="page_type">
                            <option value="richiedi-coupon"><?php esc_html_e('Richiedi coupon', 'unique-coupon-generator'); ?></option>
                            <option value="thank-you"><?php esc_html_e('Thank You', 'unique-coupon-generator'); ?></option>
                        </select>
                    </td>
                </tr>
                <tr>
                    <th><label for="page_title"><?php esc_html_e('Titolo della pagina', 'unique-coupon-generator'); ?></label></th>
                    <td><input name="page_title" type="text" id="page_title" value="" class="regular-text" required></td>
                </tr>
                <tr>
                    <th><label for="page_content"><?php esc_html_e('Contenuto della pagina', 'unique-coupon-generator'); ?></label></th>
                    <td><textarea name="page_content" id="page_content" rows="10" cols="50" class="large-text"></textarea></td>
                </tr>
                <tr>
                    <th><label for="page_shortcode"><?php esc_html_e('Shortcode', 'unique-coupon-generator'); ?></label></th>
                    <td><input name="page_shortcode" type="text" id="page_shortcode" value="" class="regular-text" required></td>
                </tr>
            </table>
            <?php submit_button(__('Crea pagina', 'unique-coupon-generator'), 'primary', 'create_page'); ?>
        </form>
    </div>
    <?php
}

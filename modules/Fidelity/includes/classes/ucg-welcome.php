<?php

/**
 * Author: Meteora Web <https://meteoraweb.com>
 */

if(!defined('ABSPATH')){exit;}

require_once __DIR__ . '/coupon-user-helpers.php';

function ucg_welcome_page(){
    if (!current_user_can('manage_options')) {
        return;
    }

    if (class_exists('\Meteora\Core\Menu\MenuManager')) {
        \Meteora\Core\Menu\MenuManager::instance()->renderHeader();
    }

    echo '<div class="wrap ucg-admin-app">';

    // Hide old header but keep structure
    echo '<header class="ucg-admin-header" style="display:none;">';
    echo '<h1>' . esc_html__('Benvenuto in Unique Coupon Generator', 'unique-coupon-generator') . '</h1>';
    echo '<p class="ucg-admin-lead">' . esc_html__('Gestisci coupon, programmi fidelity, eventi e comunicazioni marketing da un unico pannello coerente con WordPress.', 'unique-coupon-generator') . '</p>';
    echo '</header>';

    if (function_exists('ucg_render_tab_welcome')) {
        ucg_render_tab_welcome();
    }

    echo '</div>';

    if (class_exists('\Meteora\Core\Menu\MenuManager')) {
        \Meteora\Core\Menu\MenuManager::instance()->renderFooter();
    }
}

function ucg_add_welcome_submenu(){
    ucg_safe_add_submenu_page(
        'meteora-system',
        __('Pagina di benvenuto', 'unique-coupon-generator'),
        __('Fidelity Welcome', 'unique-coupon-generator'),
        'manage_options',
        'ucg-welcome',
        'ucg_welcome_page'
    );
}
add_action('admin_menu','ucg_add_welcome_submenu',20);

function ucg_schedule_welcome_redirect() {
    update_option('ucg_show_welcome', 1);
}

function ucg_redirect_to_welcome() {
    if (!is_admin()) {
        return;
    }

    if (wp_doing_ajax()) {
        return;
    }

    if (defined('REST_REQUEST') && REST_REQUEST) {
        return;
    }

    if (ucg_is_elementor_preview()) {
        return;
    }

    if (!current_user_can('manage_options')) {
        return;
    }

    $page = isset($_GET['page']) ? sanitize_text_field(wp_unslash($_GET['page'])) : '';

    if ($page === 'ucg-welcome') {
        return;
    }

    if ($page === 'ucg-admin') {
        // Option to redirect to welcome if visiting ucg-admin
    }

    $should_redirect = get_option('ucg_show_welcome', false);

    if ($should_redirect) {
        delete_option('ucg_show_welcome');

        if (!isset($_GET['activate-multi'])) {
            wp_safe_redirect(admin_url('admin.php?page=ucg-welcome'));
            exit;
        }
    }
}
add_action('admin_init', 'ucg_redirect_to_welcome', 5);

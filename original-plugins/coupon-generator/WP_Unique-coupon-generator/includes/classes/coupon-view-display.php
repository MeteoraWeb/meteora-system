<?php

/**
 * Author: Meteora Web <https://meteoraweb.com>
 */

// Impedisce l'accesso diretto ai file
if (!defined('ABSPATH')) {
    exit;
}

// Includi le funzioni necessarie
require_once plugin_dir_path(__FILE__) . 'coupon-view-admin.php';
require_once plugin_dir_path(__FILE__) . 'coupon-view-delete.php';
require_once plugin_dir_path(__FILE__) . 'coupon-view-table.php';
require_once plugin_dir_path(__FILE__) . 'coupon-view-scripts.php';

function ucg_sanitize_coupon_filters($source) {
    $filters = array(
        'email' => '',
        'used'  => '',
    );

    if (isset($source['filter_email'])) {
        $filters['email'] = sanitize_email(wp_unslash($source['filter_email']));
    }

    if (isset($source['filter_used'])) {
        $used = sanitize_text_field(wp_unslash($source['filter_used']));
        if (in_array($used, array('yes', 'no'), true)) {
            $filters['used'] = $used;
        }
    }

    return $filters;
}

function ucg_get_coupon_query_args(array $filters = array()) {
    $args = array(
        'post_type'      => 'shop_coupon',
        'posts_per_page' => -1,
        'post_status'    => 'publish',
        'orderby'        => 'date',
        'order'          => 'DESC',
        'meta_query'     => array('relation' => 'AND'),
    );

    if (!empty($filters['email'])) {
        $args['meta_query'][] = array(
            'key'     => 'customer_email',
            'value'   => $filters['email'],
            'compare' => '=',
        );
    }

    if ($filters['used'] === 'yes') {
        $args['meta_query'][] = array(
            'key'     => 'used',
            'value'   => 'yes',
            'compare' => '=',
        );
    } elseif ($filters['used'] === 'no') {
        $args['meta_query'][] = array(
            'relation' => 'OR',
            array(
                'key'     => 'used',
                'compare' => 'NOT EXISTS',
            ),
            array(
                'key'     => 'used',
                'value'   => 'yes',
                'compare' => '!=',
                'type'    => 'CHAR',
            ),
        );
    }

    return $args;
}

function ucg_fetch_coupon_posts(array $filters = array()) {
    $query_args = ucg_get_coupon_query_args($filters);
    return get_posts($query_args);
}

function ucg_build_coupon_row($coupon) {
    $coupon_id  = $coupon->ID;
    $user_email = get_post_meta($coupon_id, 'customer_email', true);
    $user       = $user_email ? get_user_by('email', $user_email) : false;

    $first_name = $user ? $user->first_name : '';
    $last_name  = $user ? $user->last_name : '';
    $phone      = $user ? get_user_meta($user->ID, 'billing_phone', true) : '';
    $city       = $user ? get_user_meta($user->ID, 'billing_city', true) : '';
    $birth_date = $user ? get_user_meta($user->ID, 'birth_date', true) : '';

    return array(
        'id'            => $coupon_id,
        'code'          => $coupon->post_title,
        'user_email'    => $user_email,
        'first_name'    => $first_name,
        'last_name'     => $last_name,
        'phone'         => $phone,
        'city'          => $city,
        'birth_date'    => $birth_date,
        'amount'        => get_post_meta($coupon_id, 'coupon_amount', true),
        'discount_type' => get_post_meta($coupon_id, 'discount_type', true),
        'expiry_date'   => get_post_meta($coupon_id, 'expiry_date', true),
        'download_date' => get_post_meta($coupon_id, 'download_date', true),
        'qr_code_url'   => get_post_meta($coupon_id, 'qr_code_url', true),
        'used'          => get_post_meta($coupon_id, 'used', true) === 'yes',
    );
}

function ucg_get_coupon_rows(array $filters = array()) {
    $posts = ucg_fetch_coupon_posts($filters);
    $rows  = array();

    foreach ($posts as $coupon) {
        $rows[] = ucg_build_coupon_row($coupon);
    }

    return $rows;
}

function ucg_coupon_discount_label($discount_type) {
    return $discount_type === 'percent'
        ? __('Percentuale', 'unique-coupon-generator')
        : __('Sconto fisso', 'unique-coupon-generator');
}

function ucg_coupon_birthdate_label($birth_date) {
    if (empty($birth_date)) {
        return __('N/D', 'unique-coupon-generator');
    }

    $timestamp = strtotime($birth_date);
    if (!$timestamp) {
        return $birth_date;
    }

    return date_i18n('d/m/Y', $timestamp);
}

function ucg_coupon_phone_label($phone) {
    $phone = trim((string) $phone);
    return $phone !== '' ? $phone : __('N/D', 'unique-coupon-generator');
}

function ucg_coupon_expiry_label($expiry_date) {
    if (empty($expiry_date)) {
        return __('Nessuna scadenza', 'unique-coupon-generator');
    }

    $timestamp = strtotime($expiry_date);
    if (!$timestamp) {
        return $expiry_date;
    }

    return date_i18n('d/m/Y', $timestamp);
}

function ucg_coupon_download_label($download_date) {
    if (empty($download_date)) {
        return __('N/D', 'unique-coupon-generator');
    }

    $timestamp = strtotime($download_date);
    if (!$timestamp) {
        return $download_date;
    }

    return date_i18n('d/m/Y H:i', $timestamp);
}

function ucg_coupon_used_label($used) {
    return $used ? __('Sì', 'unique-coupon-generator') : __('No', 'unique-coupon-generator');
}

function ucg_render_tab_coupon_list($context = array()) {
    $filters = ucg_sanitize_coupon_filters($_REQUEST);
    if (!empty($_POST['reset_filters'])) {
        $filters = array('email' => '', 'used' => '');
    }

    $coupon_rows = ucg_get_coupon_rows($filters);
    $filter_email = $filters['email'];
    $filter_used  = $filters['used'];
    $page_url     = ucg_admin_page_url('ucg-admin', 'coupons');

    echo '<div class="ucg-card ucg-card--filters">';
    echo '<h2><span class="dashicons dashicons-filter" aria-hidden="true"></span> ' . esc_html__('Filtra coupon', 'unique-coupon-generator') . '</h2>';
    echo '<form method="get" action="' . esc_url($page_url) . '" class="ucg-inline-form" data-ucg-loading="true">';
    echo '<input type="hidden" name="page" value="ucg-admin">';
    echo '<input type="hidden" name="tab" value="coupons">';
    echo '<label class="screen-reader-text" for="filter_email">' . esc_html__('Filtra per email', 'unique-coupon-generator') . '</label>';
    echo '<input type="email" id="filter_email" name="filter_email" value="' . esc_attr($filter_email) . '" placeholder="' . esc_attr__('Email cliente', 'unique-coupon-generator') . '">';
    echo '<label class="screen-reader-text" for="filter_used">' . esc_html__('Stato utilizzo', 'unique-coupon-generator') . '</label>';
    echo '<select id="filter_used" name="filter_used">';
    echo '<option value="">' . esc_html__('Tutti gli stati', 'unique-coupon-generator') . '</option>';
    echo '<option value="yes" ' . selected($filter_used, 'yes', false) . '>' . esc_html__('Utilizzati', 'unique-coupon-generator') . '</option>';
    echo '<option value="no" ' . selected($filter_used, 'no', false) . '>' . esc_html__('Non utilizzati', 'unique-coupon-generator') . '</option>';
    echo '</select>';
    echo '<button type="submit" class="button button-primary ucg-button-spinner"><span class="ucg-button-text">' . esc_html__('Applica filtri', 'unique-coupon-generator') . '</span><span class="ucg-button-spinner__indicator" aria-hidden="true"></span></button>';
    echo '<a class="button" href="' . esc_url($page_url) . '">' . esc_html__('Azzera', 'unique-coupon-generator') . '</a>';
    echo '</form>';

    if ($filter_email || $filter_used) {
        echo '<div class="ucg-active-filters">';
        echo '<strong>' . esc_html__('Filtri attivi:', 'unique-coupon-generator') . '</strong>';
        if ($filter_email) {
            echo '<span class="ucg-chip">' . esc_html(sprintf(__('Email: %s', 'unique-coupon-generator'), $filter_email)) . '</span>';
        }
        if ($filter_used === 'yes') {
            echo '<span class="ucg-chip ucg-chip--success">' . esc_html__('Solo utilizzati', 'unique-coupon-generator') . '</span>';
        } elseif ($filter_used === 'no') {
            echo '<span class="ucg-chip ucg-chip--info">' . esc_html__('Solo da utilizzare', 'unique-coupon-generator') . '</span>';
        }
        echo '</div>';
    }

    echo '<div class="ucg-export-actions">';
    echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
    wp_nonce_field('ucg_export_coupons_csv', '_ucg_export_coupons_nonce');
    echo '<input type="hidden" name="action" value="ucg_export_coupons_csv">';
    echo '<input type="hidden" name="filter_email" value="' . esc_attr($filter_email) . '">';
    echo '<input type="hidden" name="filter_used" value="' . esc_attr($filter_used) . '">';
    echo '<button type="submit" class="button">' . esc_html__('Esporta CSV', 'unique-coupon-generator') . '</button>';
    echo '</form>';
    echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
    wp_nonce_field('ucg_export_coupons_pdf', '_ucg_export_coupons_pdf_nonce');
    echo '<input type="hidden" name="action" value="ucg_export_coupons_pdf">';
    echo '<input type="hidden" name="filter_email" value="' . esc_attr($filter_email) . '">';
    echo '<input type="hidden" name="filter_used" value="' . esc_attr($filter_used) . '">';
    echo '<button type="submit" class="button">' . esc_html__('Esporta PDF', 'unique-coupon-generator') . '</button>';
    echo '</form>';
    echo '</div>';
    echo '</div>';

    if (empty($coupon_rows)) {
        echo '<div class="notice notice-info"><p>' . esc_html__('Non sono presenti coupon con i filtri selezionati.', 'unique-coupon-generator') . '</p></div>';
    } else {
        echo '<div class="ucg-card ucg-card--table">';
        echo '<h2><span class="dashicons dashicons-list-view" aria-hidden="true"></span> ' . esc_html__('Elenco coupon generati', 'unique-coupon-generator') . '</h2>';
        ucc_display_coupon_table($coupon_rows);
        echo '</div>';
    }

    if (class_exists('UCG_Downloaded_Coupons')) {
        echo '<div class="ucg-card ucg-card--table">';
        UCG_Downloaded_Coupons::render_tabs();
        echo '</div>';
    }
}

function ucc_display_coupon_view_page() {
    ucg_render_tab_coupon_list();
}

function ucg_export_coupons_csv() {
    if (!current_user_can('manage_options')) {
        wp_die(__('Non hai i permessi per esportare i dati.', 'unique-coupon-generator'));
    }

    check_admin_referer('ucg_export_coupons_csv', '_ucg_export_coupons_nonce');

    $filters = ucg_sanitize_coupon_filters($_POST);
    $rows    = ucg_get_coupon_rows($filters);

    if (ob_get_length()) {
        ob_end_clean();
    }

    nocache_headers();
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="coupon-' . date('Ymd-His') . '.csv"');

    $output = fopen('php://output', 'w');
    if ($output === false) {
        wp_die(__('Impossibile generare il file CSV.', 'unique-coupon-generator'));
    }

    echo "\xEF\xBB\xBF";

    fputcsv($output, array(
        __('Codice', 'unique-coupon-generator'),
        __('Nome', 'unique-coupon-generator'),
        __('Cognome', 'unique-coupon-generator'),
        __('Email', 'unique-coupon-generator'),
        __('Telefono', 'unique-coupon-generator'),
        __('Città', 'unique-coupon-generator'),
        __('Data di Nascita', 'unique-coupon-generator'),
        __('Sconto', 'unique-coupon-generator'),
        __('Tipo di Sconto', 'unique-coupon-generator'),
        __('Data di Scadenza', 'unique-coupon-generator'),
        __('Data di Scaricamento', 'unique-coupon-generator'),
        __('Usato', 'unique-coupon-generator'),
    ), ';');

    foreach ($rows as $row) {
        fputcsv($output, array(
            $row['code'],
            $row['first_name'],
            $row['last_name'],
            $row['user_email'],
            ucg_coupon_phone_label($row['phone']),
            $row['city'],
            ucg_coupon_birthdate_label($row['birth_date']),
            $row['amount'],
            ucg_coupon_discount_label($row['discount_type']),
            ucg_coupon_expiry_label($row['expiry_date']),
            ucg_coupon_download_label($row['download_date']),
            ucg_coupon_used_label($row['used']),
        ), ';');
    }

    fclose($output);
    exit;
}

function ucg_export_coupons_pdf() {
    if (!current_user_can('manage_options')) {
        wp_die(__('Non hai i permessi per esportare i dati.', 'unique-coupon-generator'));
    }

    check_admin_referer('ucg_export_coupons_pdf', '_ucg_export_coupons_pdf_nonce');

    $filters = ucg_sanitize_coupon_filters($_POST);
    $rows    = ucg_get_coupon_rows($filters);

    if (!class_exists('UCG_PDF_Exporter')) {
        wp_die(__('Impossibile caricare il generatore PDF.', 'unique-coupon-generator'));
    }

    $pdf = new UCG_PDF_Exporter(__('Elenco Coupon', 'unique-coupon-generator'));
    $pdf->setHeaders(array(
        __('Codice', 'unique-coupon-generator'),
        __('Nome', 'unique-coupon-generator'),
        __('Cognome', 'unique-coupon-generator'),
        __('Email', 'unique-coupon-generator'),
        __('Telefono', 'unique-coupon-generator'),
        __('Città', 'unique-coupon-generator'),
        __('Data di Nascita', 'unique-coupon-generator'),
        __('Sconto', 'unique-coupon-generator'),
        __('Tipo di Sconto', 'unique-coupon-generator'),
        __('Data di Scadenza', 'unique-coupon-generator'),
        __('Data di Scaricamento', 'unique-coupon-generator'),
        __('Usato', 'unique-coupon-generator'),
    ));

    if (empty($rows)) {
        $pdf->addMessage(__('Nessun coupon disponibile per i filtri selezionati.', 'unique-coupon-generator'));
    } else {
        foreach ($rows as $row) {
            $pdf->addRow(array(
                $row['code'],
                $row['first_name'],
                $row['last_name'],
                $row['user_email'],
                ucg_coupon_phone_label($row['phone']),
                $row['city'],
                ucg_coupon_birthdate_label($row['birth_date']),
                $row['amount'],
                ucg_coupon_discount_label($row['discount_type']),
                ucg_coupon_expiry_label($row['expiry_date']),
                ucg_coupon_download_label($row['download_date']),
                ucg_coupon_used_label($row['used']),
            ));
        }
    }

    if (ob_get_length()) {
        ob_end_clean();
    }

    $pdf->output('coupon-' . date('Ymd-His') . '.pdf');
}

add_action('admin_post_ucg_export_coupons_csv', 'ucg_export_coupons_csv');
add_action('admin_post_ucg_export_coupons_pdf', 'ucg_export_coupons_pdf');

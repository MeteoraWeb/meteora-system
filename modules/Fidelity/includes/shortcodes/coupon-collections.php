<?php

/**
 * Author: Meteora Web <https://meteoraweb.com>
 */


if (!defined('ABSPATH')) {
    exit;
}

function mms_coupon_sets_grid_shortcode($atts = array()) {
    $atts = shortcode_atts(
        array(
            'status' => 'active',
        ),
        $atts,
        'mms_coupon_sets'
    );

    $status_filter = array_filter(array_map('trim', explode(',', strtolower((string) $atts['status']))));
    if (empty($status_filter)) {
        $status_filter = array('active');
    }

    $sets_option = ucg_get_coupon_sets();
    if (empty($sets_option)) {
        return '<p class="ucg-collection-empty">' . esc_html__('Nessun set disponibile al momento.', 'unique-coupon-generator') . '</p>';
    }

    $cards = array();
    foreach ($sets_option as $base_code => $raw_set) {
        $set = ucg_get_coupon_set($base_code);
        if (!$set) {
            continue;
        }

        $status = $set['status'] ?? 'active';
        if (!in_array('all', $status_filter, true) && !in_array(strtolower($status), $status_filter, true)) {
            continue;
        }

        if (!ucg_coupon_set_is_active($set) && !in_array('all', $status_filter, true) && !in_array('closed', $status_filter, true) && !in_array('draft', $status_filter, true)) {
            continue;
        }

        $request_url = $set['request_url'] ?? '';
        if (!$request_url) {
            continue;
        }

        $cards[] = array(
            'title' => $set['label'] ?? ($set['name'] ?? $base_code),
            'url'   => $request_url,
            'image' => ucg_coupon_set_image_url($set, 'medium_large'),
        );
    }

    if (empty($cards)) {
        return '<p class="ucg-collection-empty">' . esc_html__('Nessun set disponibile al momento.', 'unique-coupon-generator') . '</p>';
    }

    ob_start();
    echo '<div class="ucg-collection-grid ucg-collection-grid--coupons">';
    foreach ($cards as $card) {
        $title = trim((string) $card['title']);
        if ($title === '') {
            $title = __('Set senza titolo', 'unique-coupon-generator');
        }

        echo '<article class="ucg-collection-card ucg-collection-card--coupon">';
        echo '<a class="ucg-collection-card__image" href="' . esc_url($card['url']) . '">';
        if (!empty($card['image'])) {
            echo '<img src="' . esc_url($card['image']) . '" alt="' . esc_attr($title) . '">';
        } else {
            echo '<span class="ucg-collection-card__placeholder">' . esc_html__('Nessuna immagine', 'unique-coupon-generator') . '</span>';
        }
        echo '</a>';
        echo '<div class="ucg-collection-card__body">';
        echo '<h3 class="ucg-collection-card__title">' . esc_html($title) . '</h3>';
        echo '<a class="ucg-collection-card__link" href="' . esc_url($card['url']) . '">' . esc_html__('Richiedi coupon', 'unique-coupon-generator') . '</a>';
        echo '</div>';
        echo '</article>';
    }
    echo '</div>';

    return ob_get_clean();
}
add_shortcode('ucg_coupon_sets', 'mms_coupon_sets_grid_shortcode');

function ucg_active_events_grid_shortcode($atts = array()) {
    if (!function_exists('mms_events_table')) {
        return '';
    }

    $events_table = mms_events_table('events');
    if (!$events_table) {
        return '';
    }

    global $wpdb;
    $events = $wpdb->get_results("SELECT id, titolo, immagine, page_id, stato FROM {$events_table} WHERE stato = 'pubblicato' ORDER BY data_evento ASC, id DESC");
    if (empty($events)) {
        return '<p class="ucg-collection-empty">' . esc_html__('Nessun evento attivo al momento.', 'unique-coupon-generator') . '</p>';
    }

    $cards = array();
    foreach ($events as $event) {
        $page_id = (int) ($event->page_id ?? 0);
        $request_url = '';
        if ($page_id) {
            $page = get_post($page_id);
            if ($page && 'trash' !== get_post_status($page)) {
                $request_url = get_permalink($page);
            }
        }

        if (!$request_url && function_exists('mms_events_get_event')) {
            $full_event = mms_events_get_event((int) $event->id);
            if ($full_event && !empty($full_event->page_id)) {
                $page = get_post($full_event->page_id);
                if ($page && 'trash' !== get_post_status($page)) {
                    $request_url = get_permalink($page);
                }
            }
        }

        if (!$request_url) {
            continue;
        }

        $cards[] = array(
            'title' => $event->titolo,
            'url'   => $request_url,
            'image' => !empty($event->immagine) ? esc_url_raw($event->immagine) : '',
        );
    }

    if (empty($cards)) {
        return '<p class="ucg-collection-empty">' . esc_html__('Nessun evento attivo al momento.', 'unique-coupon-generator') . '</p>';
    }

    ob_start();
    echo '<div class="ucg-collection-grid ucg-collection-grid--events">';
    foreach ($cards as $card) {
        $title = trim((string) $card['title']);
        if ($title === '') {
            $title = __('Evento senza titolo', 'unique-coupon-generator');
        }

        echo '<article class="ucg-collection-card ucg-collection-card--event">';
        echo '<a class="ucg-collection-card__image" href="' . esc_url($card['url']) . '">';
        if (!empty($card['image'])) {
            echo '<img src="' . esc_url($card['image']) . '" alt="' . esc_attr($title) . '">';
        } else {
            echo '<span class="ucg-collection-card__placeholder">' . esc_html__('Immagine non disponibile', 'unique-coupon-generator') . '</span>';
        }
        echo '</a>';
        echo '<div class="ucg-collection-card__body">';
        echo '<h3 class="ucg-collection-card__title">' . esc_html($title) . '</h3>';
        echo '<a class="ucg-collection-card__link" href="' . esc_url($card['url']) . '">' . esc_html__('Richiedi ticket', 'unique-coupon-generator') . '</a>';
        echo '</div>';
        echo '</article>';
    }
    echo '</div>';

    return ob_get_clean();
}
add_shortcode('ucg_eventi_attivi', 'ucg_active_events_grid_shortcode');

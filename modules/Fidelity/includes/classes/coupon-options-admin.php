<?php

/**
 * Author: Meteora Web <https://meteoraweb.com>
 */


if (!defined('ABSPATH')) {
    exit;
}

add_action('admin_menu', 'ucg_register_admin_hub', 20);

/**
 * Register the unified admin pages for the plugin natively under Meteora System.
 */
function ucg_register_admin_hub() {
    ucg_safe_add_submenu_page(
        'meteora-system',
        __('Fidelity Coupon', 'unique-coupon-generator'),
        __('Fidelity Coupon', 'unique-coupon-generator'),
        'manage_options',
        'ucg-admin',
        'ucg_render_coupon_hub'
    );

    ucg_safe_add_submenu_page(
        'meteora-system',
        __('Fidelity Eventi', 'unique-coupon-generator'),
        __('Fidelity Eventi', 'unique-coupon-generator'),
        'manage_options',
        'ucg-admin-events',
        'ucg_render_events_hub'
    );

    ucg_safe_add_submenu_page(
        'meteora-system',
        __('Fidelity Marketing', 'unique-coupon-generator'),
        __('Fidelity Marketing', 'unique-coupon-generator'),
        'manage_options',
        'ucg-admin-marketing',
        'ucg_render_marketing_hub'
    );

    ucg_safe_add_submenu_page(
        'meteora-system',
        __('Fidelity WhatsApp', 'unique-coupon-generator'),
        __('Fidelity WhatsApp', 'unique-coupon-generator'),
        'manage_options',
        'ucg-admin-whatsapp',
        'ucg_render_whatsapp_hub'
    );

    ucg_safe_add_submenu_page(
        'meteora-system',
        __('Fidelity Settings', 'unique-coupon-generator'),
        __('Fidelity Settings', 'unique-coupon-generator'),
        'manage_options',
        'ucg-admin-settings',
        'ucg_render_settings_hub'
    );
}

/**
 * Render the coupon manager hub.
 */
function ucg_render_coupon_hub() {
    if (!current_user_can('manage_options')) {
        return;
    }

    ucg_admin_render_page(array(
        'slug'        => 'ucg-admin',
        'title'       => __('Gestione coupon & fidelity', 'unique-coupon-generator'),
        'description' => __('Crea, personalizza e monitora i set di coupon e i programmi fidelity con una vista unica.', 'unique-coupon-generator'),
        'tabs'        => array(
            'sets'     => array(
                'label'    => __('Set e generazione', 'unique-coupon-generator'),
                'icon'     => 'dashicons-screenoptions',
                'callback' => 'ucg_render_tab_coupon_sets',
            ),
            'emails'   => array(
                'label'    => __('Opzioni email', 'unique-coupon-generator'),
                'icon'     => 'dashicons-email-alt2',
                'callback' => 'ucg_render_tab_coupon_emails',
            ),
            'coupons'  => array(
                'label'    => __('Coupon generati', 'unique-coupon-generator'),
                'icon'     => 'dashicons-list-view',
                'callback' => 'ucg_render_tab_coupon_list',
            ),
            'fidelity' => array(
                'label'    => __('Fidelity', 'unique-coupon-generator'),
                'icon'     => 'dashicons-awards',
                'callback' => 'ucg_render_tab_coupon_fidelity',
            ),
            'pages'    => array(
                'label'    => __('Pagine & shortcode', 'unique-coupon-generator'),
                'icon'     => 'dashicons-admin-page',
                'callback' => 'ucg_render_tab_coupon_pages',
            ),
            'verify'   => array(
                'label'    => __('Verifica Coupon', 'unique-coupon-generator'),
                'icon'     => 'dashicons-yes',
                'callback' => 'ucg_render_tab_coupon_verify',
            ),
        ),
        'cards'       => 'ucg_admin_coupon_cards',
    ));
}

/**
 * Render the events hub.
 */
function ucg_render_events_hub() {
    if (!current_user_can('manage_options')) {
        return;
    }

    ucg_admin_render_page(array(
        'slug'        => 'ucg-admin-events',
        'title'       => __('Gestione eventi e ticketing', 'unique-coupon-generator'),
        'description' => __('Organizza eventi, controlla i ticket generati e monitora le performance dei PR.', 'unique-coupon-generator'),
        'tabs'        => array(
            'manage'  => array(
                'label'    => __('Crea o modifica eventi', 'unique-coupon-generator'),
                'icon'     => 'dashicons-calendar-alt',
                'callback' => 'ucg_render_tab_events_manage',
            ),
            'tickets' => array(
                'label'    => __('Ticket e check-in', 'unique-coupon-generator'),
                'icon'     => 'dashicons-tickets-alt',
                'callback' => 'ucg_render_tab_events_tickets',
            ),
            'reports' => array(
                'label'    => __('Report PR e vendite', 'unique-coupon-generator'),
                'icon'     => 'dashicons-chart-bar',
                'callback' => 'ucg_render_tab_events_pr',
            ),
            'verify'  => array(
                'label'    => __('Verifica ticket', 'unique-coupon-generator'),
                'icon'     => 'dashicons-yes-alt',
                'callback' => 'ucg_render_tab_events_verify',
            ),
            'pages'   => array(
                'label'    => __('Pagine evento generate', 'unique-coupon-generator'),
                'icon'     => 'dashicons-admin-links',
                'callback' => 'ucg_render_tab_events_pages',
            ),
        ),
        'cards'       => 'ucg_admin_event_cards',
    ));
}

/**
 * Render the marketing hub.
 */
function ucg_render_marketing_hub() {
    if (!current_user_can('manage_options')) {
        return;
    }

    ucg_admin_render_page(array(
        'slug'        => 'ucg-admin-marketing',
        'title'       => __('Marketing e relazioni clienti', 'unique-coupon-generator'),
        'description' => __('Consulta i dati anagrafici, segmenta il pubblico e gestisci template e campagne email.', 'unique-coupon-generator'),
        'tabs'        => array(
            'profiles'  => array(
                'label'    => __('Anagrafiche utenti', 'unique-coupon-generator'),
                'icon'     => 'dashicons-groups',
                'callback' => 'ucg_render_tab_marketing_profiles',
            ),
            'campaigns' => array(
                'label'    => __('Invio email', 'unique-coupon-generator'),
                'icon'     => 'dashicons-email',
                'callback' => 'ucg_render_tab_marketing_email',
            ),
            'templates' => array(
                'label'    => __('Template email', 'unique-coupon-generator'),
                'icon'     => 'dashicons-media-text',
                'callback' => 'ucg_render_tab_marketing_templates',
            ),
        ),
        'cards'       => 'ucg_admin_marketing_cards',
    ));
}

/**
 * Render the WhatsApp settings page.
 */
function ucg_render_whatsapp_hub() {
    if (!current_user_can('manage_options')) {
        return;
    }

    ucg_admin_render_page(array(
        'slug'        => 'ucg-admin-whatsapp',
        'title'       => __('WhatsApp', 'unique-coupon-generator'),
        'description' => __('Personalizza il messaggio da condividere tramite WhatsApp quando un utente richiede un QR code.', 'unique-coupon-generator'),
        'tabs'        => array(
            'settings' => array(
                'label'    => __('Impostazioni', 'unique-coupon-generator'),
                'icon'     => 'dashicons-smartphone',
                'callback' => 'ucg_render_tab_whatsapp_settings',
            ),
        ),
    ));
}

/**
 * Render the settings hub.
 */
function ucg_render_settings_hub() {
    if (!current_user_can('manage_options')) {
        return;
    }

    ucg_admin_render_page(array(
        'slug'        => 'ucg-admin-settings',
        'title'       => __('Impostazioni del plugin', 'unique-coupon-generator'),
        'description' => __('Configura la licenza, consulta i log e recupera gli shortcode principali.', 'unique-coupon-generator'),
        'tabs'        => array(
            'welcome'  => array(
                'label'    => __('Benvenuto', 'unique-coupon-generator'),
                'icon'     => 'dashicons-smiley',
                'callback' => 'ucg_render_tab_welcome',
            ),
            'license'  => array(
                'label'    => __('Gestione licenza', 'unique-coupon-generator'),
                'icon'     => 'dashicons-admin-network',
                'callback' => 'ucg_render_tab_license',
            ),
            'shortcodes' => array(
                'label'    => __('Shortcode', 'unique-coupon-generator'),
                'icon'     => 'dashicons-editor-code',
                'callback' => 'ucg_render_tab_shortcodes',
            ),
            'errors'   => array(
                'label'    => __('Log errori', 'unique-coupon-generator'),
                'icon'     => 'dashicons-shield-alt',
                'callback' => 'ucg_render_tab_error_log',
            ),
        ),
        'cards'       => 'ucg_admin_settings_cards',
    ));
}

/**
 * Determine the currently active tab.
 *
 * @param array  $tabs    Available tabs.
 * @param string $default Default tab key.
 * @return string
 */
function ucg_admin_current_tab(array $tabs, $default) {
    $current = isset($_GET['tab']) ? sanitize_key(wp_unslash($_GET['tab'])) : $default;
    if (!array_key_exists($current, $tabs)) {
        $current = $default;
    }
    return $current;
}

/**
 * Generate an admin page URL for the plugin.
 *
 * @param string      $slug Page slug.
 * @param string|null $tab  Optional tab.
 * @return string
 */
function ucg_admin_page_url($slug, $tab = null) {
    $url = admin_url('admin.php?page=' . $slug);
    if ($tab !== null) {
        $url = add_query_arg('tab', $tab, $url);
    }
    return $url;
}

/**
 * Render the admin page shell with tabs and optional metric cards.
 *
 * @param array $args Rendering arguments.
 */
function ucg_admin_render_page(array $args) {
    $defaults = array(
        'slug'        => '',
        'title'       => '',
        'description' => '',
        'tabs'        => array(),
        'cards'       => array(),
    );

    $args = wp_parse_args($args, $defaults);
    if (empty($args['slug']) || empty($args['tabs'])) {
        return;
    }

    $default_tab = (string) key($args['tabs']);
    $current_tab = ucg_admin_current_tab($args['tabs'], $default_tab);
    $base_url    = ucg_admin_page_url($args['slug']);

    echo '<div class="wrap ucg-admin-app" data-ucg-page="' . esc_attr($args['slug']) . '">';
    echo '<header class="ucg-admin-header">';
    echo '<h1>' . esc_html($args['title']) . '</h1>';
    if (!empty($args['description'])) {
        echo '<p class="ucg-admin-lead">' . esc_html($args['description']) . '</p>';
    }
    echo '</header>';

    $cards = is_callable($args['cards']) ? call_user_func($args['cards'], $current_tab) : $args['cards'];
    if (!empty($cards) && is_array($cards)) {
        ucg_admin_render_cards($cards);
    }

    echo '<nav class="nav-tab-wrapper ucg-admin-tabs" role="tablist">';
    foreach ($args['tabs'] as $tab_key => $tab) {
        $tab_label = isset($tab['label']) ? $tab['label'] : ucfirst($tab_key);
        $icon      = !empty($tab['icon']) ? $tab['icon'] : '';
        $is_active = $tab_key === $current_tab;
        $classes   = 'nav-tab ucg-tab-link' . ($is_active ? ' nav-tab-active is-active' : '');
        $url       = ucg_admin_page_url($args['slug'], $tab_key);

        echo '<a href="' . esc_url($url) . '" class="' . esc_attr($classes) . '" role="tab" aria-selected="' . ($is_active ? 'true' : 'false') . '">';
        if ($icon) {
            echo '<span class="dashicons ' . esc_attr($icon) . '" aria-hidden="true"></span>';
        }
        echo '<span>' . esc_html($tab_label) . '</span>';
        if (!empty($tab['badge'])) {
            echo '<span class="ucg-tab-badge">' . esc_html($tab['badge']) . '</span>';
        }
        echo '</a>';
    }
    echo '</nav>';

    echo '<div class="ucg-admin-content" role="tabpanel">';
    if (!empty($args['tabs'][$current_tab]['callback']) && is_callable($args['tabs'][$current_tab]['callback'])) {
        $context = array(
            'current_tab' => $current_tab,
            'page_slug'   => $args['slug'],
            'base_url'    => $base_url,
        );
        do_action('ucg_before_admin_tab', $args['slug'], $current_tab, $context);
        call_user_func($args['tabs'][$current_tab]['callback'], $context);
        do_action('ucg_after_admin_tab', $args['slug'], $current_tab, $context);
    } else {
        echo '<p>' . esc_html__('Questa sezione non è disponibile.', 'unique-coupon-generator') . '</p>';
    }
    echo '</div>';
    echo '</div>';
}

/**
 * Render compact status cards in the header.
 *
 * @param array $cards Metric cards definition.
 */
function ucg_admin_render_cards(array $cards) {
    $prepared = array();
    foreach ($cards as $card) {
        if (!is_array($card)) {
            continue;
        }
        $prepared[] = array(
            'label'       => isset($card['label']) ? (string) $card['label'] : '',
            'value'       => isset($card['value']) ? ucg_admin_format_metric($card['value']) : '',
            'icon'        => isset($card['icon']) ? (string) $card['icon'] : '',
            'description' => isset($card['description']) ? (string) $card['description'] : '',
            'status'      => isset($card['status']) ? (string) $card['status'] : 'neutral',
        );
    }

    if (empty($prepared)) {
        return;
    }

    echo '<div class="ucg-admin-cards">';
    foreach ($prepared as $card) {
        $status  = 'ucg-mini-card--' . sanitize_html_class($card['status']);
        $classes = 'ucg-mini-card ' . $status;
        echo '<div class="' . esc_attr($classes) . '">';
        if ($card['icon']) {
            echo '<span class="ucg-mini-card__icon dashicons ' . esc_attr($card['icon']) . '" aria-hidden="true"></span>';
        }
        echo '<span class="ucg-mini-card__value">' . esc_html($card['value']) . '</span>';
        echo '<span class="ucg-mini-card__label">' . esc_html($card['label']) . '</span>';
        if ($card['description']) {
            echo '<p class="ucg-mini-card__description">' . esc_html($card['description']) . '</p>';
        }
        echo '</div>';
    }
    echo '</div>';
}

/**
 * Format values for display inside metric cards.
 *
 * @param mixed $value Metric value.
 * @return string
 */
function ucg_admin_format_metric($value) {
    if (is_numeric($value)) {
        return number_format_i18n((float) $value);
    }
    if (is_string($value)) {
        return $value;
    }
    return '';
}

/**
 * Collect overview metrics for the coupon hub.
 *
 * @return array
 */
function ucg_admin_coupon_cards() {
    $sets      = get_option('ucc_coupon_sets', array());
    $set_count = is_array($sets) ? count($sets) : 0;

    global $wpdb;
    $coupon_count = (int) $wpdb->get_var(
        $wpdb->prepare(
            "SELECT COUNT(DISTINCT p.ID) FROM {$wpdb->posts} AS p INNER JOIN {$wpdb->postmeta} AS m ON p.ID = m.post_id WHERE p.post_type = %s AND p.post_status = %s AND m.meta_key = %s",
            'shop_coupon',
            'publish',
            'base_coupon_code'
        )
    );

    $pending_count = (int) $wpdb->get_var(
        $wpdb->prepare(
            "SELECT COUNT(DISTINCT p.ID) FROM {$wpdb->posts} AS p INNER JOIN {$wpdb->postmeta} AS used ON p.ID = used.post_id WHERE p.post_type = %s AND used.meta_key = %s AND (used.meta_value = '' OR used.meta_value IS NULL OR used.meta_value != %s)",
            'shop_coupon',
            'used',
            'yes'
        )
    );

    return array(
        array(
            'label'       => __('Set attivi', 'unique-coupon-generator'),
            'value'       => $set_count,
            'icon'        => 'dashicons-screenoptions',
            'status'      => $set_count ? 'success' : 'warning',
            'description' => __('Totale dei set attualmente configurati.', 'unique-coupon-generator'),
        ),
        array(
            'label'       => __('Coupon generati', 'unique-coupon-generator'),
            'value'       => $coupon_count,
            'icon'        => 'dashicons-tickets-alt',
            'status'      => $coupon_count ? 'info' : 'neutral',
            'description' => __('Codici coupon creati dal plugin.', 'unique-coupon-generator'),
        ),
        array(
            'label'       => __('Coupon da utilizzare', 'unique-coupon-generator'),
            'value'       => $pending_count,
            'icon'        => 'dashicons-clock',
            'status'      => $pending_count ? 'accent' : 'success',
            'description' => __('Coupon ancora non segnati come utilizzati.', 'unique-coupon-generator'),
        ),
    );
}

/**
 * Collect overview metrics for the events hub.
 *
 * @return array
 */
function ucg_admin_event_cards() {
    global $wpdb;
    $events_table = function_exists('ucg_events_table') ? ucg_events_table('events') : '';
    $tickets_table = function_exists('ucg_events_table') ? ucg_events_table('tickets') : '';

    $events_total = 0;
    $tickets_total = 0;
    $tickets_used = 0;

    if ($events_table) {
        $events_total = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$events_table}");
    }

    if ($tickets_table) {
        $tickets_total = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$tickets_table}");

        $status_field = 'stato';
        if (function_exists('ucg_events_get_ticket_status_field')) {
            $status_field = sanitize_key(ucg_events_get_ticket_status_field());
        }

        $tickets_used = (int) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM {$tickets_table} WHERE {$status_field} = %s",
                'usato'
            )
        );
    }

    return array(
        array(
            'label'       => __('Eventi pubblicati', 'unique-coupon-generator'),
            'value'       => $events_total,
            'icon'        => 'dashicons-calendar-alt',
            'status'      => $events_total ? 'info' : 'neutral',
            'description' => __('Eventi creati nel sistema.', 'unique-coupon-generator'),
        ),
        array(
            'label'       => __('Ticket generati', 'unique-coupon-generator'),
            'value'       => $tickets_total,
            'icon'        => 'dashicons-tickets-alt',
            'status'      => $tickets_total ? 'accent' : 'neutral',
            'description' => __('Ticket inviati agli utenti.', 'unique-coupon-generator'),
        ),
        array(
            'label'       => __('Ticket convalidati', 'unique-coupon-generator'),
            'value'       => $tickets_used,
            'icon'        => 'dashicons-yes-alt',
            'status'      => $tickets_used ? 'success' : 'neutral',
            'description' => __('Ticket già segnati come utilizzati.', 'unique-coupon-generator'),
        ),
    );
}

/**
 * Collect overview metrics for the marketing hub.
 *
 * @return array
 */
function ucg_admin_marketing_cards() {
    $user_count = count_users();
    $templates  = get_option('ucg_email_templates', array());
    $templates_total = is_array($templates) ? count($templates) : 0;

    global $wpdb;
    $log_table = $wpdb->prefix . 'ucg_email_log';
    $last_sent = '';
    if ($wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $log_table)) === $log_table) {
        $last_sent = $wpdb->get_var("SELECT MAX(sent_at) FROM {$log_table}");
    }

    return array(
        array(
            'label'       => __('Utenti totali', 'unique-coupon-generator'),
            'value'       => isset($user_count['total_users']) ? (int) $user_count['total_users'] : 0,
            'icon'        => 'dashicons-groups',
            'status'      => 'info',
            'description' => __('Utenti registrati disponibili per le campagne.', 'unique-coupon-generator'),
        ),
        array(
            'label'       => __('Template salvati', 'unique-coupon-generator'),
            'value'       => $templates_total,
            'icon'        => 'dashicons-media-text',
            'status'      => $templates_total ? 'success' : 'neutral',
            'description' => __('Modelli pronti per l\'invio.', 'unique-coupon-generator'),
        ),
        array(
            'label'       => __('Ultimo invio', 'unique-coupon-generator'),
            'value'       => $last_sent ? date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($last_sent)) : __('Nessun invio', 'unique-coupon-generator'),
            'icon'        => 'dashicons-schedule',
            'status'      => $last_sent ? 'accent' : 'neutral',
            'description' => __('Data dell\'ultima campagna registrata.', 'unique-coupon-generator'),
        ),
    );
}

/**
 * Collect overview metrics for the settings hub.
 *
 * @return array
 */
function ucg_admin_settings_cards() {
    $license_status = function_exists('ucg_access_gate') ? ucg_access_gate()->get_status() : array();
    $valid          = !empty($license_status['valid']);
    $status_label   = $valid
        ? __('Licenza attiva', 'unique-coupon-generator')
        : __('Licenza da verificare', 'unique-coupon-generator');

    $last_checked = !empty($license_status['last_checked'])
        ? date_i18n(get_option('date_format'), strtotime($license_status['last_checked']))
        : __('Mai verificata', 'unique-coupon-generator');

    $error_table = $GLOBALS['wpdb']->prefix . 'ucg_error_log';
    $has_errors  = 0;
    if ($GLOBALS['wpdb']->get_var($GLOBALS['wpdb']->prepare("SHOW TABLES LIKE %s", $error_table)) === $error_table) {
        $has_errors = (int) $GLOBALS['wpdb']->get_var("SELECT COUNT(*) FROM {$error_table}");
    }

    return array(
        array(
            'label'       => __('Stato licenza', 'unique-coupon-generator'),
            'value'       => $status_label,
            'icon'        => 'dashicons-admin-network',
            'status'      => $valid ? 'success' : 'warning',
            'description' => sprintf(__('Ultimo controllo: %s', 'unique-coupon-generator'), $last_checked),
        ),
        array(
            'label'       => __('Errori registrati', 'unique-coupon-generator'),
            'value'       => $has_errors,
            'icon'        => 'dashicons-shield-alt',
            'status'      => $has_errors ? 'accent' : 'success',
            'description' => __('Totale errori salvati nel registro del plugin.', 'unique-coupon-generator'),
        ),
    );
}

/**
 * Render the welcome tab with a summary of the plugin capabilities.
 *
 * @param array $context Rendering context.
 */
function ucg_render_tab_welcome($context = array()) {
    if (!current_user_can('manage_options')) {
        return;
    }

    $quick_links = array(
        array(
            'label' => __('Gestione coupon', 'unique-coupon-generator'),
            'url'   => ucg_admin_page_url('ucg-admin', 'sets'),
            'icon'  => 'dashicons-screenoptions',
        ),
        array(
            'label' => __('Gestione eventi', 'unique-coupon-generator'),
            'url'   => ucg_admin_page_url('ucg-admin-events', 'manage'),
            'icon'  => 'dashicons-calendar-alt',
        ),
        array(
            'label' => __('Marketing e email', 'unique-coupon-generator'),
            'url'   => ucg_admin_page_url('ucg-admin-marketing', 'profiles'),
            'icon'  => 'dashicons-email-alt2',
        ),
    );

    echo '<section class="ucg-card">';
    echo '<h2><span class="dashicons dashicons-smiley" aria-hidden="true"></span> ' . esc_html__('Benvenuto a Unique Coupon Generator', 'unique-coupon-generator') . '</h2>';
    echo '<p class="ucg-card__intro">' . esc_html__('Organizza coupon, eventi e campagne di fidelizzazione da un unico pannello progettato in stile WordPress.', 'unique-coupon-generator') . '</p>';
    echo '<ul class="ucg-feature-list">';
    echo '<li>' . esc_html__('Crea set di coupon e landing page dedicate con pochi clic.', 'unique-coupon-generator') . '</li>';
    echo '<li>' . esc_html__('Automatizza email con QR code, promemoria e template riutilizzabili.', 'unique-coupon-generator') . '</li>';
    echo '<li>' . esc_html__('Monitora punti fidelity, ticket evento e report delle attività di marketing.', 'unique-coupon-generator') . '</li>';
    echo '<li>' . esc_html__('Consulta log e statistiche per intervenire rapidamente in caso di problemi.', 'unique-coupon-generator') . '</li>';
    echo '</ul>';
    echo '</section>';

    echo '<section class="ucg-card ucg-card--table">';
    echo '<h2><span class="dashicons dashicons-admin-links" aria-hidden="true"></span> ' . esc_html__('Azioni rapide', 'unique-coupon-generator') . '</h2>';
    echo '<div class="ucg-quick-links">';
    foreach ($quick_links as $link) {
        echo '<a class="button button-secondary ucg-quick-link" href="' . esc_url($link['url']) . '">';
        echo '<span class="dashicons ' . esc_attr($link['icon']) . '" aria-hidden="true"></span>';
        echo '<span>' . esc_html($link['label']) . '</span>';
        echo '</a>';
    }
    echo '</div>';
    echo '</section>';
}

/**
 * Proxy the license manager renderer inside the unified UI.
 *
 * @param array $context Rendering context.
 */
function ucg_render_tab_license($context = array()) {
    if (!function_exists('ucg_access_gate')) {
        echo '<div class="notice notice-error"><p>' . esc_html__('Impossibile inizializzare il gestore della licenza.', 'unique-coupon-generator') . '</p></div>';
        return;
    }

    ucg_access_gate()->render_tab($context);
}

/**
 * Render the error log tab aggregating DB, file and email issues.
 *
 * @param array $context Rendering context.
 */
function ucg_render_tab_error_log($context = array()) {
    if (!current_user_can('manage_options')) {
        return;
    }

    global $wpdb;

    $page_url   = ucg_admin_page_url('ucg-admin-settings', 'errors');
    $error_table = $wpdb->prefix . 'ucg_error_log';
    $email_table = $wpdb->prefix . 'ucg_email_log';

    $has_error_table = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $error_table)) === $error_table;
    $has_email_table = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $email_table)) === $email_table;

    if ($has_error_table && isset($_POST['ucg_clear_error_log'])) {
        check_admin_referer('ucg_clear_error_log');
        $wpdb->query("TRUNCATE TABLE {$error_table}");
        wp_safe_redirect(add_query_arg('ucg_notice', 'cleared-db', $page_url));
        exit;
    }

    if (isset($_POST['ucg_clear_error_file'])) {
        check_admin_referer('ucg_clear_error_file');
        $log_file = UCG_PLUGIN_DIR . 'ucg_error.log';
        if (file_exists($log_file) && is_writable($log_file)) {
            file_put_contents($log_file, '');
        }
        wp_safe_redirect(add_query_arg('ucg_notice', 'cleared-file', $page_url));
        exit;
    }

    $notice_key = isset($_GET['ucg_notice']) ? sanitize_key(wp_unslash($_GET['ucg_notice'])) : '';
    if ('cleared-db' === $notice_key) {
        echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__('Registro errori svuotato.', 'unique-coupon-generator') . '</p></div>';
    } elseif ('cleared-file' === $notice_key) {
        echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__('File di log ripulito con successo.', 'unique-coupon-generator') . '</p></div>';
    }

    $db_logs = array();
    if ($has_error_table) {
        $db_logs = $wpdb->get_results("SELECT message, timestamp FROM {$error_table} ORDER BY timestamp DESC LIMIT 50");
    }

    $email_logs = array();
    if ($has_email_table) {
        $email_logs = $wpdb->get_results("SELECT email, subject, result, sent_at FROM {$email_table} WHERE result != 'ok' ORDER BY sent_at DESC LIMIT 50");
    }

    $log_file_path = UCG_PLUGIN_DIR . 'ucg_error.log';
    $file_entries  = array();
    if (file_exists($log_file_path) && is_readable($log_file_path)) {
        $lines = file($log_file_path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if (is_array($lines)) {
            $file_entries = array_slice($lines, -50);
        }
    }

    echo '<section class="ucg-card ucg-card--table">';
    echo '<h2><span class="dashicons dashicons-shield-alt" aria-hidden="true"></span> ' . esc_html__('Registro errori del plugin', 'unique-coupon-generator') . '</h2>';
    if ($db_logs) {
        echo '<table class="widefat fixed striped">';
        echo '<thead><tr><th>' . esc_html__('Data', 'unique-coupon-generator') . '</th><th>' . esc_html__('Messaggio', 'unique-coupon-generator') . '</th></tr></thead><tbody>';
        foreach ($db_logs as $entry) {
            $timestamp = strtotime($entry->timestamp);
            $formatted = $timestamp ? date_i18n(get_option('date_format') . ' ' . get_option('time_format'), $timestamp) : $entry->timestamp;
            echo '<tr><td>' . esc_html($formatted) . '</td><td>' . esc_html($entry->message) . '</td></tr>';
        }
        echo '</tbody></table>';
    } else {
        echo '<p>' . esc_html__('Nessun errore registrato nel database.', 'unique-coupon-generator') . '</p>';
    }
    if ($has_error_table) {
        echo '<form method="post" action="' . esc_url($page_url) . '" class="ucg-inline-form" data-ucg-loading="true">';
        wp_nonce_field('ucg_clear_error_log');
        echo '<input type="hidden" name="ucg_clear_error_log" value="1">';
        echo '<button type="submit" class="button">' . esc_html__('Svuota registro', 'unique-coupon-generator') . '</button>';
        echo '</form>';
    }
    echo '</section>';

    echo '<section class="ucg-card">';
    echo '<h2><span class="dashicons dashicons-media-text" aria-hidden="true"></span> ' . esc_html__('File di log PHP', 'unique-coupon-generator') . '</h2>';
    if ($file_entries) {
        echo '<div class="ucg-log-file" role="region" aria-live="polite">';
        foreach ($file_entries as $line) {
            echo '<p><code>' . esc_html($line) . '</code></p>';
        }
        echo '</div>';
    } else {
        echo '<p>' . esc_html__('Nessun messaggio disponibile nel file ucg_error.log.', 'unique-coupon-generator') . '</p>';
    }
    if (file_exists($log_file_path) && is_writable($log_file_path)) {
        echo '<form method="post" action="' . esc_url($page_url) . '" class="ucg-inline-form" data-ucg-loading="true">';
        wp_nonce_field('ucg_clear_error_file');
        echo '<input type="hidden" name="ucg_clear_error_file" value="1">';
        echo '<button type="submit" class="button">' . esc_html__('Svuota file', 'unique-coupon-generator') . '</button>';
        echo '</form>';
    }
    echo '</section>';

    echo '<section class="ucg-card ucg-card--table">';
    echo '<h2><span class="dashicons dashicons-email-alt" aria-hidden="true"></span> ' . esc_html__('Errori invio email', 'unique-coupon-generator') . '</h2>';
    if ($email_logs) {
        echo '<table class="widefat fixed striped">';
        echo '<thead><tr><th>' . esc_html__('Data', 'unique-coupon-generator') . '</th><th>' . esc_html__('Destinatario', 'unique-coupon-generator') . '</th><th>' . esc_html__('Oggetto', 'unique-coupon-generator') . '</th><th>' . esc_html__('Stato', 'unique-coupon-generator') . '</th></tr></thead><tbody>';
        foreach ($email_logs as $email_entry) {
            $timestamp = strtotime($email_entry->sent_at);
            $formatted = $timestamp ? date_i18n(get_option('date_format') . ' ' . get_option('time_format'), $timestamp) : $email_entry->sent_at;
            $status_class = 'ucg-chip--warning';
            echo '<tr>';
            echo '<td>' . esc_html($formatted) . '</td>';
            echo '<td>' . esc_html($email_entry->email) . '</td>';
            echo '<td>' . esc_html($email_entry->subject) . '</td>';
            echo '<td><span class="ucg-chip ' . esc_attr($status_class) . '">' . esc_html($email_entry->result) . '</span></td>';
            echo '</tr>';
        }
        echo '</tbody></table>';
    } else {
        echo '<p>' . esc_html__('Non risultano invii email falliti.', 'unique-coupon-generator') . '</p>';
    }
    echo '</section>';
}

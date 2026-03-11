<?php

/**
 * Author: Meteora Web <https://meteoraweb.com>
 */

if (!defined('ABSPATH')) {
    exit;
}

require_once UCG_CLASSES . 'qr-code-functions.php';

/**
 * Return the database table names for the event manager.
 */
function ucg_events_table($type) {
    global $wpdb;

    switch ($type) {
        case 'events':
            return $wpdb->prefix . 'eventi';
        case 'pr':
            return $wpdb->prefix . 'eventi_pr';
        case 'tickets':
            return $wpdb->prefix . 'eventi_tickets';
        default:
            return '';
    }
}

/**
 * Retrieve the cached list of ticket table columns.
 *
 * @param bool $force_refresh Whether to refresh the cached value.
 * @return array
 */
function ucg_events_get_ticket_columns($force_refresh = false) {
    static $columns = null;

    if ($force_refresh) {
        $columns = null;
    }

    if (is_array($columns)) {
        return $columns;
    }

    global $wpdb;
    $columns = array();
    $table   = ucg_events_table('tickets');

    if (empty($table)) {
        return $columns;
    }

    $results = $wpdb->get_results("SHOW COLUMNS FROM {$table}", ARRAY_A);
    if (!empty($results)) {
        foreach ($results as $column) {
            if (!empty($column['Field'])) {
                $columns[] = $column['Field'];
            }
        }
    }

    return $columns;
}

/**
 * Check if a ticket table column exists.
 *
 * @param string $column Column name.
 * @param bool   $force_refresh Force a cache refresh.
 * @return bool
 */
function ucg_events_ticket_column_exists($column, $force_refresh = false) {
    $columns = ucg_events_get_ticket_columns($force_refresh);
    return in_array($column, $columns, true);
}

/**
 * Determine the column name used to store the ticket status.
 *
 * @param bool $force_refresh Force a cache refresh.
 * @return string
 */
function ucg_events_get_ticket_status_field($force_refresh = false) {
    static $field = null;

    if ($force_refresh) {
        $field = null;
    }

    if ($field !== null) {
        if (ucg_events_ticket_column_exists($field)) {
            return $field;
        }

        if (!ucg_events_ticket_column_exists($field, true)) {
            $field = null;
        } else {
            return $field;
        }
    }

    if (ucg_events_ticket_column_exists('stato', $force_refresh)) {
        $field = 'stato';
        return $field;
    }

    if (ucg_events_ticket_column_exists('status', $force_refresh)) {
        $field = 'status';
        return $field;
    }

    $field = 'stato';
    return $field;
}

/**
 * Ensure the ticket table provides a backwards-compatible status column.
 */
function ucg_events_sync_ticket_status_alias() {
    global $wpdb;

    $table = ucg_events_table('tickets');
    if (empty($table)) {
        return;
    }

    $has_stato  = ucg_events_ticket_column_exists('stato');
    $has_status = ucg_events_ticket_column_exists('status');

    $previous_suppression = $wpdb->suppress_errors();
    $wpdb->suppress_errors(true);

    if (!$has_stato && $has_status) {
        $result = $wpdb->query("ALTER TABLE {$table} CHANGE COLUMN status stato varchar(20) NOT NULL DEFAULT 'da pagare'");
        if ($result !== false) {
            ucg_events_get_ticket_columns(true);
            $has_stato  = true;
            $has_status = ucg_events_ticket_column_exists('status');
        } elseif (!empty($wpdb->last_error) && function_exists('ucg_log_error')) {
            ucg_log_error('Ticket status column rename failed', array('error' => $wpdb->last_error));
        }
    }

    if ($has_stato && !$has_status) {
        $result = $wpdb->query("ALTER TABLE {$table} ADD COLUMN status varchar(20) NOT NULL DEFAULT 'da pagare'");
        if ($result !== false) {
            $wpdb->query("UPDATE {$table} SET status = stato");
            ucg_events_get_ticket_columns(true);
        } elseif (!empty($wpdb->last_error) && function_exists('ucg_log_error')) {
            ucg_log_error('Ticket status alias creation failed', array('error' => $wpdb->last_error));
        }
    } elseif ($has_stato && $has_status) {
        $wpdb->query("UPDATE {$table} SET status = stato WHERE status <> stato");
    }

    $wpdb->suppress_errors($previous_suppression);

    ucg_events_get_ticket_status_field(true);
}

/**
 * Normalize incoming status values to the canonical Italian variants.
 *
 * @param string $status Raw status string.
 * @return string
 */
function ucg_events_normalize_ticket_status_value($status) {
    $status = strtolower(trim((string) $status));

    $map = array(
        'used'       => 'usato',
        'validated'  => 'usato',
        'paid'       => 'pagato',
        'completed'  => 'pagato',
        'reserved'   => 'da pagare',
        'pending'    => 'da pagare',
        'unpaid'     => 'da pagare',
        'to pay'     => 'da pagare',
        'waiting'    => 'da pagare',
    );

    if (isset($map[$status])) {
        return $map[$status];
    }

    return $status;
}

/**
 * Determine if the current user can manage PR reports.
 */
function ucg_events_user_can_manage_pr_reports() {
    $can_manage = current_user_can('manage_options');

    if (!$can_manage && current_user_can('manage_woocommerce')) {
        $can_manage = true;
    }

    /**
     * Filter whether the current user can access PR report tools.
     *
     * @param bool $can_manage Current capability state.
     */
    return apply_filters('ucg_events_user_can_manage_pr_reports', $can_manage);
}

/**
 * Normalize a redirect URL so it always points to the current site.
 */
function ucg_events_normalize_redirect_url($url) {
    if (empty($url) || is_array($url)) {
        return '';
    }

    $url = trim((wp_unslash($url) ?? ''));
    if ($url === '') {
        return '';
    }

    if (strpos($url ?? '', 'javascript:') === 0 || strpos($url ?? '', 'data:') === 0) {
        return '';
    }

    if (strpos($url ?? '', '//') === 0) {
        return '';
    }

    if (preg_match('#^https?://#i', $url)) {
        return $url;
    }

    if ($url[0] !== '/') {
        $url = '/' . ltrim($url ?? '', '/');
    }

    if (strpos($url ?? '', '/wp-admin') === 0 || strpos($url ?? '', '/wp-login.php') === 0) {
        return site_url($url);
    }

    return home_url($url);
}

/**
 * Resolve a safe redirect destination for front-end flows.
 */
function ucg_events_get_safe_redirect($preferred = '') {
    $candidates = array();

    if (!empty($preferred)) {
        $candidates[] = $preferred;
    }

    if (isset($_POST['_wp_http_referer'])) {
        $candidates[] = wp_unslash($_POST['_wp_http_referer']);
    }

    if (function_exists('get_queried_object_id')) {
        $object_id = get_queried_object_id();
        if ($object_id) {
            $permalink = get_permalink($object_id);
            if ($permalink) {
                $candidates[] = $permalink;
            }
        }
    }

    $referer = wp_get_referer();
    if ($referer) {
        $candidates[] = $referer;
    }

    if (isset($_SERVER['REQUEST_URI'])) {
        $candidates[] = home_url(wp_unslash($_SERVER['REQUEST_URI']));
    }

    $home_url = home_url('/');
    $candidates[] = $home_url;

    $site_host = wp_parse_url($home_url, PHP_URL_HOST);
    $site_alt = wp_parse_url(site_url('/'), PHP_URL_HOST);
    $allowed_hosts = array_filter(array_unique(array($site_host, $site_alt)));

    foreach ($candidates as $candidate) {
        $normalized = ucg_events_normalize_redirect_url($candidate);
        if (!$normalized) {
            continue;
        }

        $validated = wp_validate_redirect($normalized, false);
        if ($validated) {
            return $validated;
        }

        $host = wp_parse_url($normalized, PHP_URL_HOST);
        if (!$host || in_array($host, $allowed_hosts, true)) {
            return $normalized;
        }
    }

    return $home_url;
}

/**
 * Safely sanitize a URL while handling malformed inputs gracefully.
 *
 * @param mixed $url Potential URL value.
 * @return string Sanitized URL or empty string when invalid.
 */
function ucg_events_safe_url($url) {
    if (empty($url) || is_array($url) || is_object($url)) {
        return '';
    }

    $url = trim((string) $url);
    if ($url === '') {
        return '';
    }

    // Preserve encoded newline markers often used by WhatsApp templates.
    $preserved_tokens = array('%0A', '%0a');
    $placeholders = array();
    foreach ($preserved_tokens as $token) {
        if (strpos($url, $token) !== false) {
            $placeholder = '__UCG_PRESERVE_' . md5($token);
            $placeholders[$placeholder] = $token;
            $url = str_replace($token, $placeholder, $url);
        }
    }

    $sanitized = '';

    try {
        $sanitized = esc_url_raw($url);
    } catch (Throwable $throwable) {
        $sanitized = '';
    }

    if ($sanitized === '' && function_exists('wp_http_validate_url')) {
        try {
            $validated = wp_http_validate_url($url);
        } catch (Throwable $throwable) {
            $validated = '';
        }

        if (is_string($validated) && $validated !== '') {
            $sanitized = $validated;
        }
    }

    if ($sanitized === '' && function_exists('filter_var')) {
        $filtered = filter_var($url, FILTER_SANITIZE_URL);
        if (is_string($filtered) && $filtered !== '') {
            $sanitized = $filtered;
        }
    }

    if ($sanitized === '') {
        return '';
    }

    $sanitized = preg_replace('/[\s\x00-\x1F\x7F]+/', '', $sanitized);
    $scheme = wp_parse_url($sanitized, PHP_URL_SCHEME);
    if ($scheme && !in_array(strtolower($scheme), array('http', 'https'), true)) {
        return '';
    }

    if (!empty($placeholders)) {
        $sanitized = strtr($sanitized, $placeholders);
    }

    return $sanitized;
}

/**
 * Retrieve a single event.
 */
function ucg_events_get_event($event_id) {
    global $wpdb;
    $event_id = absint($event_id);
    if (!$event_id) {
        return null;
    }

    $table = ucg_events_table('events');
    $event = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table} WHERE id = %d", $event_id));
    if ($event) {
        $event->tipi_ticket = ucg_events_decode_ticket_types($event->tipi_ticket);
        if (!isset($event->mostra_whatsapp)) {
            $event->mostra_whatsapp = 1;
        }
        if (!isset($event->mostra_download_png)) {
            $event->mostra_download_png = 0;
        }
        if (!isset($event->mostra_download_pdf)) {
            $event->mostra_download_pdf = 0;
        }
        if (!isset($event->whatsapp_message) || !is_string($event->whatsapp_message)) {
            $event->whatsapp_message = '';
        }

        if (!isset($event->pagamento_wc_gateways)) {
            $event->pagamento_wc_gateways = array();
        } else {
            $event->pagamento_wc_gateways = ucg_events_normalize_gateway_list($event->pagamento_wc_gateways);
        }

        $needs_page = empty($event->page_id) || !get_post($event->page_id);
        if ($needs_page && function_exists('ucg_events_sync_event_page')) {
            $event_data = array(
                'titolo' => $event->titolo,
                'descrizione' => $event->descrizione,
                'immagine' => $event->immagine,
                'data_evento' => $event->data_evento,
                'ora_evento' => $event->ora_evento,
                'luogo' => $event->luogo,
                'mostra_contenuto' => $event->mostra_contenuto,
                'tipi_ticket' => $event->tipi_ticket,
                'page_id' => (int) $event->page_id,
                'stato' => $event->stato,
            );

            $page_id = ucg_events_sync_event_page($event->id, $event_data, $event);
            if ($page_id) {
                $page_id = (int) $page_id;
                if ((int) $event->page_id !== $page_id) {
                    $events_table = ucg_events_table('events');
                    if (!empty($event->id) && $events_table) {
                        $wpdb->update(
                            $events_table,
                            array(
                                'page_id' => $page_id,
                                'updated_at' => current_time('mysql'),
                            ),
                            array('id' => (int) $event->id),
                            array('%d', '%s'),
                            array('%d')
                        );
                    }
                }

                $event->page_id = $page_id;
            }
        }
    }

    return $event;
}

/**
 * Normalize a stored list of WooCommerce gateway identifiers.
 *
 * @param mixed $value Raw value from the database or runtime context.
 * @return array
 */
function ucg_events_normalize_gateway_list($value) {
    if (is_array($value)) {
        $list = $value;
    } elseif (is_object($value)) {
        $list = (array) $value;
    } else {
        $list = array();
        if (is_string($value) && $value !== '') {
            $decoded = json_decode($value, true);
            if (is_array($decoded)) {
                $list = $decoded;
            } else {
                $maybe = maybe_unserialize($value);
                if (is_array($maybe)) {
                    $list = $maybe;
                }
            }
        }
    }

    $sanitized = array();
    foreach ($list as $gateway_id) {
        $gateway_id = sanitize_key($gateway_id);
        if ($gateway_id !== '') {
            $sanitized[$gateway_id] = $gateway_id;
        }
    }

    return array_values($sanitized);
}

/**
 * Retrieve the WhatsApp template configured for a specific event.
 *
 * @param object|null $event Event configuration object.
 * @return string
 */
function ucg_events_get_whatsapp_template($event) {
    if (!$event || !is_object($event)) {
        return '';
    }

    $template = isset($event->whatsapp_message) ? ucg_sanitize_whatsapp_message((string) $event->whatsapp_message) : '';
    if (trim($template) === '') {
        return '';
    }

    return $template;
}

/**
 * Retrieve the list of WooCommerce gateways allowed for the event.
 *
 * @param object|array $event Event configuration.
 * @return array
 */
function ucg_events_get_event_gateways($event) {
    if (is_object($event) && isset($event->pagamento_wc_gateways)) {
        return ucg_events_normalize_gateway_list($event->pagamento_wc_gateways);
    }

    if (is_array($event) && isset($event['pagamento_wc_gateways'])) {
        return ucg_events_normalize_gateway_list($event['pagamento_wc_gateways']);
    }

    return array();
}

/**
 * Filter a WooCommerce gateway map according to the event configuration.
 *
 * @param object       $event     Event configuration.
 * @param array<string,WC_Payment_Gateway> $gateways Available gateways.
 * @return array<string,WC_Payment_Gateway>
 */
function ucg_events_filter_gateway_map_for_event($event, $gateways) {
    if (empty($gateways) || !is_array($gateways)) {
        return array();
    }

    $filtered = $gateways;

    $allowed = ucg_events_get_event_gateways($event);
    if (!empty($allowed)) {
        $filtered = array_intersect_key($filtered, array_flip($allowed));
    }

    $supports_manual_in_loco = false;
    if (is_object($event) && !empty($event->pagamento_in_loco)) {
        $supports_manual_in_loco = true;
    } elseif (is_array($event) && !empty($event['pagamento_in_loco'])) {
        $supports_manual_in_loco = true;
    } elseif (is_array($event) && !empty($event['allow_in_loco'])) {
        $supports_manual_in_loco = true;
    }

    if ($supports_manual_in_loco) {
        foreach ($filtered as $gateway_id => $gateway_obj) {
            if (ucg_events_is_offline_gateway($gateway_id)) {
                unset($filtered[$gateway_id]);
            }
        }
    }

    return $filtered;
}

/**
 * Retrieve the list of offline WooCommerce gateway identifiers.
 *
 * @return array
 */
function ucg_events_get_offline_gateway_ids() {
    $defaults = array('cod', 'bacs', 'cheque', 'ucg_in_loco');
    $gateways = apply_filters('ucg_events_offline_gateways', $defaults);

    if (!is_array($gateways)) {
        $gateways = (array) $gateways;
    }

    $sanitized = array();
    foreach ($gateways as $gateway_id) {
        $key = sanitize_key($gateway_id);
        if ($key !== '') {
            $sanitized[$key] = $key;
        }
    }

    return array_values($sanitized);
}

/**
 * Determine whether the provided gateway represents an offline payment method.
 *
 * @param string|WC_Payment_Gateway $gateway Payment gateway identifier or instance.
 * @return bool
 */
function ucg_events_is_offline_gateway($gateway) {
    if (is_object($gateway) && method_exists($gateway, 'get_id')) {
        $gateway = $gateway->get_id();
    }

    $gateway = sanitize_key((string) $gateway);
    if ($gateway === '') {
        return false;
    }

    return in_array($gateway, ucg_events_get_offline_gateway_ids(), true);
}

/**
 * Retrieve the pending payment message for a specific gateway.
 *
 * @param string $gateway_id Gateway identifier.
 * @return string
 */
function ucg_events_get_pending_gateway_message($gateway_id) {
    $gateway_id = sanitize_key((string) $gateway_id);

    $messages = array(
        'cod'         => __('Ticket prenotato – paga all’ingresso.', 'unique-coupon-generator'),
        'ucg_in_loco' => __('Ticket prenotato – paga all’ingresso.', 'unique-coupon-generator'),
    );

    $default = __('Ordine ricevuto! Segui le istruzioni del metodo di pagamento per completare l’acquisto.', 'unique-coupon-generator');
    $message = $messages[$gateway_id] ?? $default;

    return apply_filters('ucg_events_pending_gateway_message', $message, $gateway_id);
}

/**
 * Create or update the public page that hosts the event form.
 */
function ucg_events_sync_event_page($event_id, $data, $existing_event = null) {
    if (!function_exists('wp_insert_post')) {
        return 0;
    }

    $event_id = absint($event_id);
    if (!$event_id) {
        return 0;
    }

    $option_key = 'ucg_event_page_' . $event_id;

    $defaults = array(
        'titolo' => '',
        'descrizione' => '',
        'immagine' => '',
        'data_evento' => '',
        'ora_evento' => '',
        'luogo' => '',
        'mostra_contenuto' => 1,
        'tipi_ticket' => array(),
        'page_id' => 0,
        'stato' => 'bozza',
    );

    $data = wp_parse_args((array) $data, $defaults);

    $page_id = absint($data['page_id']);
    $page = $page_id ? get_post($page_id) : null;
    if ($page_id && !$page) {
        $page_id = 0;
        $page = null;
    }

    if (!$page_id) {
        $stored_page_id = absint(get_option($option_key));
        if ($stored_page_id) {
            $stored_page = get_post($stored_page_id);
            if ($stored_page && $stored_page->post_type === 'page' && get_post_status($stored_page) !== 'trash') {
                $page_id = $stored_page_id;
                $page = $stored_page;
            } else {
                delete_option($option_key);
            }
        }
    }

    $title = sanitize_text_field($data['titolo']);
    if ($title === '') {
        $title = sprintf(__('Evento %d', 'unique-coupon-generator'), $event_id);
    }

    if (!$page_id) {
        $existing_page = plugin_get_page_by_title($title, OBJECT, 'page');
        if ($existing_page && get_post_status($existing_page) !== 'trash') {
            $managed_event_id = (int) get_post_meta($existing_page->ID, '_ucg_event_id', true);
            if ($managed_event_id === 0 || $managed_event_id === $event_id) {
                $page_id = (int) $existing_page->ID;
                $page = $existing_page;
            }
        }
    }

    $status_map = array(
        'pubblicato' => 'publish',
        'chiuso' => 'publish',
    );
    $post_status = $status_map[strtolower((string) $data['stato'])] ?? 'draft';

    $shortcode = '[richiedi_ticket base="' . $event_id . '"]';
    $content = "<!-- wp:shortcode -->\n" . $shortcode . "\n<!-- /wp:shortcode -->";

    $slug_base = sanitize_title($title);
    if (empty($slug_base)) {
        $slug_base = 'evento-' . $event_id;
    }

    $managed = false;
    if ($page_id) {
        $managed = (int) get_post_meta($page_id, '_ucg_event_id', true) === $event_id;
        if (!$managed) {
            return $page_id;
        }

        $update_args = array(
            'ID' => $page_id,
            'post_title' => $title,
            'post_status' => $post_status,
        );

        $current_content = $page ? $page->post_content : '';
        if ($managed && strpos($current_content ?? '', '[richiedi_ticket') === false) {
            $update_args['post_content'] = $content;
        } elseif ($managed) {
            $update_args['post_content'] = $current_content;
        }

        wp_update_post($update_args);
    } else {
        $insert_args = array(
            'post_type' => 'page',
            'post_status' => $post_status,
            'post_title' => $title,
            'post_name' => $slug_base,
            'post_content' => $content,
        );

        $page_id = wp_insert_post($insert_args, true);
        if (is_wp_error($page_id)) {
            return 0;
        }

        update_post_meta($page_id, '_ucg_event_id', $event_id);
        update_post_meta($page_id, '_ucg_managed_page', 1);
    }

    if ($page_id) {
        if ($managed || get_post_meta($page_id, '_ucg_event_id', true) == $event_id) {
            update_post_meta($page_id, '_ucg_managed_page', 1);
            update_post_meta($page_id, '_ucg_event_id', $event_id);
        }
        update_post_meta($page_id, '_ucg_event_status', $post_status);
        update_option($option_key, (int) $page_id);
    } else {
        delete_option($option_key);
    }

    return (int) $page_id;
}

/**
 * Decode the JSON ticket structure.
 */
function ucg_events_decode_ticket_types($ticket_json) {
    if (empty($ticket_json)) {
        return array();
    }

    if (is_array($ticket_json)) {
        return $ticket_json;
    }

    $data = json_decode($ticket_json, true);
    if (!is_array($data)) {
        return array();
    }

    return array_map(function ($ticket) {
        $ticket['id'] = isset($ticket['id']) ? sanitize_title($ticket['id']) : sanitize_title($ticket['name'] ?? 'ticket');
        $ticket['name'] = sanitize_text_field($ticket['name'] ?? 'Ticket');
        $ticket['price'] = isset($ticket['price']) ? (float) $ticket['price'] : 0;
        $ticket['max'] = isset($ticket['max']) ? (int) $ticket['max'] : 0;
        $ticket['product_id'] = isset($ticket['product_id']) ? (int) $ticket['product_id'] : 0;
        return $ticket;
    }, $data);
}

/**
 * Encode ticket types for storage.
 */
function ucg_events_encode_ticket_types($tickets) {
    if (empty($tickets)) {
        return '';
    }

    return wp_json_encode(array_values($tickets));
}

/**
 * Generate a normalized slug for the ticket type.
 */
function ucg_events_normalize_ticket_slug($name, $index = 0) {
    $slug = sanitize_title($name);
    if (empty($slug)) {
        $slug = 'ticket-' . (int) $index;
    }
    return $slug;
}

/**
 * Retrieve the full configuration array for a ticket type.
 */
function ucg_events_get_ticket_config($event, $ticket_slug) {
    if (!$event || empty($ticket_slug)) {
        return null;
    }

    $ticket_slug = sanitize_title($ticket_slug);
    $tickets = is_array($event->tipi_ticket) ? $event->tipi_ticket : array();

    foreach ($tickets as $ticket) {
        if (!empty($ticket['id']) && sanitize_title($ticket['id']) === $ticket_slug) {
            return $ticket;
        }
    }

    return null;
}

/**
 * Return the human readable label for a ticket type.
 */
function ucg_events_get_ticket_label($event, $ticket_slug) {
    $config = ucg_events_get_ticket_config($event, $ticket_slug);
    if ($config && !empty($config['name'])) {
        return $config['name'];
    }

    return $ticket_slug;
}

/**
 * Return a translated label for a stored ticket status.
 */
function ucg_events_get_ticket_status_label($status) {
    $status = is_string($status) ? strtolower($status) : '';

    switch ($status) {
        case 'pagato':
            return __('Pagato', 'unique-coupon-generator');
        case 'da pagare':
            return __('Da pagare', 'unique-coupon-generator');
        case 'usato':
            return __('Usato', 'unique-coupon-generator');
        default:
            return ucfirst($status);
    }
}

/**
 * Provide the list of supported email placeholders and their meaning.
 */
function ucg_events_get_email_placeholder_descriptions() {
    return array(
        '{customer_name}' => __('Nome completo del partecipante', 'unique-coupon-generator'),
        '{customer_email}' => __('Email del partecipante', 'unique-coupon-generator'),
        '{customer_phone}' => __('Telefono del partecipante', 'unique-coupon-generator'),
        '{event_title}' => __('Titolo dell’evento', 'unique-coupon-generator'),
        '{event_date}' => __('Data dell’evento (formattata secondo le impostazioni di WordPress)', 'unique-coupon-generator'),
        '{event_time}' => __('Ora dell’evento', 'unique-coupon-generator'),
        '{event_location}' => __('Luogo dell’evento', 'unique-coupon-generator'),
        '{event_page_url}' => __('URL della pagina pubblica dell’evento', 'unique-coupon-generator'),
        '{ticket_type}' => __('Nome del ticket selezionato', 'unique-coupon-generator'),
        '{ticket_code}' => __('Codice univoco del ticket', 'unique-coupon-generator'),
        '{ticket_status}' => __('Stato del ticket', 'unique-coupon-generator'),
        '{qr_code}' => __('Immagine del QR code (solo per email HTML)', 'unique-coupon-generator'),
        '{qr_code_url}' => __('URL diretto del QR code', 'unique-coupon-generator'),
    );
}

/**
 * Count generated tickets for an event.
 */
function ucg_events_count_tickets($event_id, $status = array()) {
    global $wpdb;
    $table = ucg_events_table('tickets');
    $event_id = absint($event_id);
    if (!$event_id) {
        return 0;
    }

    $sql = "SELECT COUNT(*) FROM {$table} WHERE evento_id = %d";
    $params = array($event_id);

    if (!empty($status)) {
        $status_field  = sanitize_key(ucg_events_get_ticket_status_field());
        $normalized    = array_map('ucg_events_normalize_ticket_status_value', $status);
        $placeholders  = implode(',', array_fill(0, count($normalized), '%s'));
        $sql          .= " AND {$status_field} IN ({$placeholders})";
        $params        = array_merge(array($event_id), array_map('sanitize_text_field', $normalized));
    }

    return (int) $wpdb->get_var($wpdb->prepare($sql, $params));
}

/**
 * Count tickets by type.
 */
function ucg_events_count_tickets_by_type($event_id, $ticket_slug, $status = array()) {
    global $wpdb;
    $table = ucg_events_table('tickets');
    $event_id = absint($event_id);
    $ticket_slug = sanitize_text_field($ticket_slug);

    if (!$event_id || empty($ticket_slug)) {
        return 0;
    }

    $sql = "SELECT COUNT(*) FROM {$table} WHERE evento_id = %d AND tipo_ticket = %s";
    $params = array($event_id, $ticket_slug);

    if (!empty($status)) {
        $status_field  = sanitize_key(ucg_events_get_ticket_status_field());
        $normalized    = array_map('ucg_events_normalize_ticket_status_value', $status);
        $placeholders  = implode(',', array_fill(0, count($normalized), '%s'));
        $sql          .= " AND {$status_field} IN ({$placeholders})";
        $params        = array_merge($params, array_map('sanitize_text_field', $normalized));
    }

    return (int) $wpdb->get_var($wpdb->prepare($sql, $params));
}

/**
 * Count tickets assigned to a PR.
 */
function ucg_events_count_tickets_by_pr($event_id, $pr_id) {
    global $wpdb;
    $table = ucg_events_table('tickets');
    $event_id = absint($event_id);
    $pr_id = absint($pr_id);

    if (!$event_id || !$pr_id) {
        return 0;
    }

    $sql = "SELECT COUNT(*) FROM {$table} WHERE evento_id = %d AND pr_id = %d";
    return (int) $wpdb->get_var($wpdb->prepare($sql, $event_id, $pr_id));
}

/**
 * Delete a ticket from the database.
 *
 * @param int $ticket_id Ticket identifier.
 * @return bool True if deleted.
 */
function ucg_events_delete_ticket($ticket_id) {
    global $wpdb;
    $ticket_id = absint($ticket_id);
    if (!$ticket_id) {
        return false;
    }

    $table = ucg_events_table('tickets');
    if (!$table) {
        return false;
    }

    $deleted = $wpdb->delete($table, array('id' => $ticket_id), array('%d'));

    return $deleted !== false;
}

/**
 * Delete an event and its related entries.
 *
 * @param int $event_id Event identifier.
 * @return bool True on success.
 */
function ucg_events_delete_event($event_id) {
    global $wpdb;

    $event_id = absint($event_id);
    if (!$event_id) {
        return false;
    }

    $events_table = ucg_events_table('events');
    if (!$events_table) {
        return false;
    }

    $deleted = $wpdb->delete($events_table, array('id' => $event_id), array('%d'));
    if ($deleted === false) {
        return false;
    }

    $tickets_table = ucg_events_table('tickets');
    if ($tickets_table) {
        $wpdb->delete($tickets_table, array('evento_id' => $event_id), array('%d'));
    }

    $pr_table = ucg_events_table('pr');
    if ($pr_table) {
        $wpdb->delete($pr_table, array('evento_id' => $event_id), array('%d'));
    }

    return $deleted !== false;
}

/**
 * Generate a unique ticket code.
 */
function ucg_events_generate_ticket_code($event_id) {
    $event_id = absint($event_id);
    $prefix = 'EV' . str_pad((string) $event_id, 3, '0', STR_PAD_LEFT);

    do {
        $code = $prefix . '-' . wp_generate_password(8, false, false);
        $exists = ucg_events_get_ticket_by_code($code);
    } while ($exists);

    return $code;
}

/**
 * Retrieve a ticket by its code.
 */
function ucg_events_get_ticket_by_code($code) {
    global $wpdb;
    $table = ucg_events_table('tickets');
    $code = sanitize_text_field($code);

    if (empty($code)) {
        return null;
    }

    $query = $wpdb->prepare("SELECT * FROM {$table} WHERE ticket_code = %s LIMIT 1", $code);

    return $wpdb->get_row($query);
}

/**
 * Determine whether a ticket already exists for the provided contact details.
 *
 * @param int    $event_id     Event identifier.
 * @param string $email        Email address to validate.
 * @param string $phone_digits Normalized phone number digits (including prefix).
 * @return bool
 */
function ucg_events_ticket_exists_for_contact($event_id, $email, $phone_digits) {
    global $wpdb;
    $table = ucg_events_table('tickets');

    $event_id = absint($event_id);
    if (!$event_id) {
        return false;
    }

    $email = sanitize_email($email);
    $phone_digits = preg_replace('/\D+/', '', (string) $phone_digits);

    if ($email === '' && $phone_digits === '') {
        return false;
    }

    $clauses = array();
    $params = array($event_id);

    if ($email !== '') {
        $clauses[] = 'utente_email = %s';
        $params[] = $email;
    }

    if ($phone_digits !== '') {
        $clauses[] = "REPLACE(REPLACE(REPLACE(utente_telefono, ' ', ''), '+', ''), '-', '') = %s";
        $params[] = $phone_digits;
    }

    if (empty($clauses)) {
        return false;
    }

    $sql = "SELECT id FROM {$table} WHERE evento_id = %d AND (" . implode(' OR ', $clauses) . ') LIMIT 1';
    $query = $wpdb->prepare($sql, $params);

    return (bool) $wpdb->get_var($query);
}

/**
 * Generate a QR code for a ticket.
 */
function ucg_events_generate_qr_code($ticket_code) {
    if (empty($ticket_code)) {
        return '';
    }

    $ticket_code = sanitize_text_field($ticket_code);

    $verify_url = add_query_arg(array('ticket_code' => rawurlencode($ticket_code)), home_url('/'));

    $qr_url = ucg_generate_qr_code_image($verify_url, array(
        'filename'    => $ticket_code,
        'directory'   => 'ucg-eventi-qrcode',
        'size'        => 10,
        'margin'      => 2,
        'log_context' => array('ticket_code' => $ticket_code, 'type' => 'event_ticket'),
    ));

    if ($qr_url === '') {
        return '';
    }

    return ucg_events_safe_url($qr_url);
}

if (!function_exists('ucg_events_generate_ticket_pdf')) {
    /**
     * Generate a PDF summary for a ticket.
     *
     * @param object $event       Event object.
     * @param string $ticket_code Ticket identifier.
     * @param string $qr_url      QR code URL.
     * @param string $full_name   Ticket holder name.
     * @param string $email       Ticket holder email.
     * @param string $phone       Ticket holder phone.
     * @return string PDF URL or empty string on failure.
     */
    function ucg_events_generate_ticket_pdf($event, $ticket_code, $qr_url, $full_name, $email, $phone) {
        if (empty($ticket_code)) {
            return '';
        }

        $event_title = isset($event->titolo) ? $event->titolo : '';
        $event_date = '';
        if (!empty($event->data_evento)) {
            $event_date = date_i18n(get_option('date_format'), strtotime($event->data_evento));
            if (!empty($event->ora_evento)) {
                $event_date .= ' ' . $event->ora_evento;
            }
        }
        $event_location = isset($event->luogo) ? $event->luogo : '';

        $lines = array();
        if ($event_title !== '') {
            $lines[] = sprintf(__('Evento: %s', 'unique-coupon-generator'), $event_title);
        }
        if ($event_date !== '') {
            $lines[] = sprintf(__('Data: %s', 'unique-coupon-generator'), $event_date);
        }
        if ($event_location !== '') {
            $lines[] = sprintf(__('Luogo: %s', 'unique-coupon-generator'), $event_location);
        }
        $lines[] = sprintf(__('Codice ticket: %s', 'unique-coupon-generator'), $ticket_code);
        if ($full_name !== '') {
            $lines[] = sprintf(__('Intestatario: %s', 'unique-coupon-generator'), $full_name);
        }
        if ($email !== '') {
            $lines[] = sprintf(__('Email: %s', 'unique-coupon-generator'), $email);
        }
        if ($phone !== '') {
            $lines[] = sprintf(__('Telefono: %s', 'unique-coupon-generator'), $phone);
        }
        if ($qr_url !== '') {
            $lines[] = sprintf(__('Link QR: %s', 'unique-coupon-generator'), $qr_url);
        }

        $pdf_title = sprintf(__('Dettagli ticket %s', 'unique-coupon-generator'), $ticket_code);
        $image_options = array(
            'width' => 200,
            'margin_bottom' => 32,
        );

        return ucg_generate_pdf_file(
            $pdf_title,
            $lines,
            'ucg-eventi-pdf',
            'ticket-' . sanitize_title($ticket_code),
            array(
                'image_url' => $qr_url,
                'image_options' => $image_options,
            )
        );
    }
}

/**
 * Get remaining tickets for a type respecting global and specific limits.
 */
function ucg_events_get_global_remaining($event) {
    if (!$event) {
        return 0;
    }

    $global_limit = isset($event->numero_ticket) ? (int) $event->numero_ticket : 0;
    if ($global_limit <= 0) {
        return -1; // Unlimited
    }

    $used = ucg_events_count_tickets($event->id);

    return max(0, $global_limit - $used);
}

function ucg_events_get_ticket_remaining($event, $ticket) {
    if (!$event || empty($ticket['id'])) {
        return 0;
    }

    $global_remaining = ucg_events_get_global_remaining($event);

    $type_limit = isset($ticket['max']) ? (int) $ticket['max'] : 0;
    if ($type_limit <= 0) {
        return $global_remaining;
    }

    $type_remaining = max(0, $type_limit - ucg_events_count_tickets_by_type($event->id, $ticket['id']));

    if ($global_remaining === -1) {
        return $type_remaining;
    }

    return min($global_remaining, $type_remaining);
}

/**
 * Retrieve PR entries for an event.
 */
function ucg_events_get_pr_list($event_id) {
    global $wpdb;
    $table = ucg_events_table('pr');
    $event_id = absint($event_id);
    if (!$event_id) {
        return array();
    }

    $rows = $wpdb->get_results($wpdb->prepare("SELECT * FROM {$table} WHERE evento_id = %d ORDER BY nome_pr ASC", $event_id));
    if (!$rows) {
        return array();
    }

    return $rows;
}

/**
 * Retrieve a single PR entry.
 */
function ucg_events_get_pr($pr_id) {
    global $wpdb;
    $table = ucg_events_table('pr');
    $pr_id = absint($pr_id);
    if (!$pr_id) {
        return null;
    }

    return $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table} WHERE id = %d", $pr_id));
}

/**
 * Retrieve the name of a PR entry if available.
 */
function ucg_events_get_pr_name($pr_id) {
    $pr = ucg_events_get_pr($pr_id);
    if ($pr && !empty($pr->nome_pr)) {
        return $pr->nome_pr;
    }

    return '';
}

/**
 * Calculate remaining tickets for a PR entry.
 */
function ucg_events_get_pr_remaining($event_id, $pr) {
    if (!$pr) {
        return 0;
    }

    $max = isset($pr->max_ticket) ? (int) $pr->max_ticket : 0;
    if ($max === 0) {
        return -1; // Unlimited
    }

    $assigned = ucg_events_count_tickets_by_pr($event_id, $pr->id);
    return max(0, $max - $assigned);
}

/**
 * Helper to get default email headers from coupon settings.
 */
function ucg_events_get_email_headers($event = null) {
    $settings = get_option('ucc_email_settings', array());
    $first = is_array($settings) ? reset($settings) : array();

    $domain = ucg_get_clean_domain();
    $sender_email = !empty($first['email_from']) ? sanitize_email($first['email_from']) : 'no-reply@' . $domain;
    $sender_name = !empty($first['email_sender']) ? sanitize_text_field($first['email_sender']) : get_bloginfo('name');

    if ($event && !empty($event->email_sender)) {
        $custom_email = sanitize_email($event->email_sender);
        if (!empty($custom_email)) {
            $sender_email = $custom_email;
        }
    }

    $headers = array('Content-Type: text/html; charset=UTF-8', 'From: ' . $sender_name . ' <' . $sender_email . '>');

    return array($sender_name, $sender_email, $headers);
}

/**
 * Build the thank you URL for an event.
 */
function ucg_events_get_thankyou_url($event) {
    if (!$event) {
        return home_url('/');
    }

    if (!empty($event->thankyou_page_id)) {
        $url = get_permalink($event->thankyou_page_id);
        if ($url) {
            return $url;
        }
    }

    return add_query_arg(array('ticket_success' => $event->id), get_permalink());
}

/**
 * Refresh WooCommerce stock after ticket generation.
 */
function ucg_events_refresh_wc_stock($event_id) {
    if (!function_exists('wc_get_product')) {
        return;
    }

    $event = ucg_events_get_event($event_id);
    if (!$event || empty($event->tipi_ticket)) {
        return;
    }

    $global_limit = isset($event->numero_ticket) ? (int) $event->numero_ticket : 0;
    $global_remaining = $global_limit > 0
        ? max(0, $global_limit - ucg_events_count_tickets($event->id))
        : 0;

    foreach ($event->tipi_ticket as $ticket) {
        if (empty($ticket['product_id'])) {
            continue;
        }

        $product = wc_get_product($ticket['product_id']);
        if (!$product) {
            continue;
        }

        $ticket_limit = isset($ticket['max']) ? (int) $ticket['max'] : 0;
        $type_remaining = $ticket_limit > 0
            ? max(0, $ticket_limit - ucg_events_count_tickets_by_type($event->id, $ticket['id']))
            : -1;

        if ($global_limit > 0) {
            $stock = ($type_remaining === -1)
                ? $global_remaining
                : min($global_remaining, $type_remaining);
            if ($stock < 0) {
                $stock = 0;
            }

            $product->set_manage_stock(true);
            $product->set_stock_quantity($stock);
            $product->set_stock_status($stock > 0 ? 'instock' : 'outofstock');
        } else {
            if ($type_remaining === -1) {
                $product->set_manage_stock(false);
                $product->set_stock_quantity('');
                $product->set_stock_status('instock');
            } else {
                $stock = max(0, $type_remaining);
                $product->set_manage_stock(true);
                $product->set_stock_quantity($stock);
                $product->set_stock_status($stock > 0 ? 'instock' : 'outofstock');
            }
        }
        $product->save();
    }
}

/**
 * Insert a new ticket in the database.
 */
function ucg_events_insert_ticket($event_id, $data) {
    global $wpdb;
    $table = ucg_events_table('tickets');
    $event_id = absint($event_id);
    if (!$event_id) {
        return false;
    }

    $defaults = array(
        'utente_nome' => '',
        'utente_email' => '',
        'utente_telefono' => '',
        'tipo_ticket' => '',
        'prezzo' => 0,
        'stato' => 'da pagare',
        'qr_code' => '',
        'ticket_code' => '',
        'pr_id' => 0,
        'order_id' => 0,
        'order_item_id' => 0,
    );

    $data = wp_parse_args($data, $defaults);

    $status_value = ucg_events_normalize_ticket_status_value($data['stato']);
    $status_value = sanitize_text_field($status_value);

    $insert = array(
        'evento_id' => $event_id,
        'utente_nome' => sanitize_text_field($data['utente_nome']),
        'utente_email' => sanitize_email($data['utente_email']),
        'utente_telefono' => sanitize_text_field($data['utente_telefono']),
        'tipo_ticket' => sanitize_text_field($data['tipo_ticket']),
        'prezzo' => (float) $data['prezzo'],
        'stato' => $status_value,
        'qr_code' => ucg_events_safe_url($data['qr_code']),
        'ticket_code' => sanitize_text_field($data['ticket_code']),
        'pr_id' => absint($data['pr_id']),
        'order_id' => absint($data['order_id']),
        'order_item_id' => absint($data['order_item_id']),
        'reminder_sent' => 0,
        'data_creazione' => current_time('mysql'),
    );

    $formats = array('%d', '%s', '%s', '%s', '%s', '%f', '%s', '%s', '%s', '%d', '%d', '%d', '%d', '%s');

    if (ucg_events_ticket_column_exists('status')) {
        $insert['status'] = $status_value;
        $formats[]        = '%s';
    }

    $result = $wpdb->insert($table, $insert, $formats);
    if ($result === false) {
        ucg_log_error('Errore inserimento ticket: ' . $wpdb->last_error);
        return false;
    }

    return (int) $wpdb->insert_id;
}

/**
 * Update the status of a ticket.
 */
function ucg_events_update_ticket_status($ticket_id, $status) {
    global $wpdb;
    $table = ucg_events_table('tickets');
    $ticket_id = absint($ticket_id);
    if (!$ticket_id) {
        return false;
    }

    $normalized = sanitize_text_field(ucg_events_normalize_ticket_status_value($status));

    $data    = array('stato' => $normalized);
    $formats = array('%s');

    if (ucg_events_ticket_column_exists('status')) {
        $data['status'] = $normalized;
        $formats[]      = '%s';
    }

    return $wpdb->update(
        $table,
        $data,
        array('id' => $ticket_id),
        $formats,
        array('%d')
    );
}

/**
 * Mark a ticket as having received the reminder email.
 */
function ucg_events_mark_ticket_reminder_sent($ticket_id) {
    global $wpdb;
    $table = ucg_events_table('tickets');
    $ticket_id = absint($ticket_id);
    if (!$ticket_id) {
        return false;
    }

    return $wpdb->update(
        $table,
        array('reminder_sent' => 1),
        array('id' => $ticket_id),
        array('%d'),
        array('%d')
    );
}

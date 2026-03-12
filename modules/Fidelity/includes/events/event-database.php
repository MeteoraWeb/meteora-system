<?php

/**
 * Author: Meteora Web <https://meteoraweb.com>
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Create database tables required for the event manager.
 */
function mms_events_create_tables() {
    global $wpdb;

    $charset = $wpdb->get_charset_collate();
    $events_table = $wpdb->prefix . 'mms_events';
    $pr_table = $wpdb->prefix . 'mms_prs';
    $tickets_table = $wpdb->prefix . 'mms_tickets';

    require_once ABSPATH . 'wp-admin/includes/upgrade.php';

    $sql_events = "CREATE TABLE {$events_table} (
        id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
        titolo varchar(255) NOT NULL,
        descrizione longtext NULL,
        immagine varchar(255) NULL,
        data_evento date NOT NULL,
        ora_evento varchar(20) NOT NULL,
        luogo varchar(255) NULL,
        numero_ticket int(11) NOT NULL DEFAULT 0,
        tipi_ticket longtext NULL,
        pagamento_woocommerce tinyint(1) NOT NULL DEFAULT 0,
        pagamento_wc_gateways longtext NULL,
        pagamento_in_loco tinyint(1) NOT NULL DEFAULT 0,
        privacy_page_id bigint(20) unsigned NULL,
        mostra_privacy tinyint(1) NOT NULL DEFAULT 0,
        mostra_whatsapp tinyint(1) NOT NULL DEFAULT 1,
        mostra_download_png tinyint(1) NOT NULL DEFAULT 0,
        mostra_download_pdf tinyint(1) NOT NULL DEFAULT 0,
        whatsapp_message longtext NULL,
        gestione_pr tinyint(1) NOT NULL DEFAULT 0,
        blocco_ticket datetime NULL,
        email_subject_confirm varchar(255) NULL,
        email_body_confirm longtext NULL,
        email_subject_reminder varchar(255) NULL,
        email_body_reminder longtext NULL,
        email_sender varchar(255) NULL,
        thankyou_page_id bigint(20) unsigned NULL,
        page_id bigint(20) unsigned NULL,
        reminder_days int(11) NOT NULL DEFAULT 3,
        mostra_contenuto tinyint(1) NOT NULL DEFAULT 1,
        stato varchar(20) NOT NULL DEFAULT 'bozza',
        created_at datetime NOT NULL,
        updated_at datetime NOT NULL,
        PRIMARY KEY  (id),
        KEY data_evento (data_evento),
        KEY stato (stato)
    ) {$charset};";

    $sql_pr = "CREATE TABLE {$pr_table} (
        id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
        evento_id bigint(20) unsigned NOT NULL,
        nome_pr varchar(200) NOT NULL,
        max_ticket int(11) NOT NULL DEFAULT 0,
        created_at datetime NOT NULL,
        PRIMARY KEY  (id),
        KEY evento_id (evento_id)
    ) {$charset};";

    $sql_tickets = "CREATE TABLE {$tickets_table} (
        id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
        evento_id bigint(20) unsigned NOT NULL,
        utente_nome varchar(200) NOT NULL,
        utente_email varchar(200) NOT NULL,
        utente_telefono varchar(50) NULL,
        tipo_ticket varchar(200) NOT NULL,
        prezzo decimal(10,2) NOT NULL DEFAULT 0,
        stato varchar(20) NOT NULL DEFAULT 'da pagare',
        qr_code varchar(255) NULL,
        ticket_code varchar(100) NOT NULL,
        pr_id bigint(20) unsigned NULL,
        order_id bigint(20) unsigned NULL,
        order_item_id bigint(20) unsigned NULL,
        reminder_sent tinyint(1) NOT NULL DEFAULT 0,
        data_creazione datetime NOT NULL,
        PRIMARY KEY  (id),
        UNIQUE KEY ticket_code (ticket_code),
        KEY evento_id (evento_id),
        KEY pr_id (pr_id)
    ) {$charset};";

    dbDelta($sql_events);
    dbDelta($sql_pr);
    dbDelta($sql_tickets);
}

/**
 * Ensure the event tables exist at runtime.
 */
function mms_events_ensure_tables() {
    global $wpdb;
    $tables = array(
        $wpdb->prefix . 'mms_events',
        $wpdb->prefix . 'mms_prs',
        $wpdb->prefix . 'mms_tickets',
    );

    foreach ($tables as $table) {
        $exists = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table));
        if ($exists !== $table) {
            mms_events_create_tables();
            break;
        }
    }

    $events_table = $wpdb->prefix . 'mms_events';
    $required_columns = array('email_subject_confirm', 'email_sender', 'page_id', 'mostra_whatsapp', 'mostra_download_png', 'mostra_download_pdf', 'pagamento_wc_gateways', 'whatsapp_message');
    foreach ($required_columns as $column_name) {
        $column = $wpdb->get_var($wpdb->prepare('SHOW COLUMNS FROM ' . $events_table . ' LIKE %s', $column_name));
        if ($column === null) {
            mms_events_create_tables();
            break;
        }
    }

    if (function_exists('mms_events_sync_ticket_status_alias')) {
        mms_events_sync_ticket_status_alias();
    }
}

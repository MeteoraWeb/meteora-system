<?php

/**
 * Author: Meteora Web <https://meteoraweb.com>
 */

if (!defined('ABSPATH')) {
    exit;
}

add_action('mms_events_daily_reminder', 'mms_events_process_reminders');
add_action('init', 'mms_events_schedule_reminders');

/**
 * Schedule the reminder cron if missing.
 */
function mms_events_schedule_reminders() {
    if (!wp_next_scheduled('mms_events_daily_reminder')) {
        wp_schedule_event(time(), 'daily', 'mms_events_daily_reminder');
    }
}

/**
 * Build the placeholder map for email templates.
 */
function mms_events_prepare_email_replacements($event, $ticket, $qr_html) {
    $event_date = !empty($event->data_evento) ? date_i18n(get_option('date_format'), strtotime($event->data_evento)) : '';
    $event_time = !empty($event->ora_evento) ? $event->ora_evento : '';
    $event_location = !empty($event->luogo) ? $event->luogo : '';
    $event_url = !empty($event->page_id) ? mms_events_safe_url(get_permalink($event->page_id)) : '';

    $ticket_label = mms_events_get_ticket_label($event, $ticket->tipo_ticket);
    $status_label = mms_events_get_ticket_status_label($ticket->stato);

    $raw = array(
        '{customer_name}' => $ticket->utente_nome,
        '{customer_email}' => $ticket->utente_email,
        '{customer_phone}' => $ticket->utente_telefono,
        '{event_title}' => $event->titolo,
        '{event_date}' => $event_date,
        '{event_time}' => $event_time,
        '{event_location}' => $event_location,
        '{event_page_url}' => $event_url,
        '{ticket_type}' => $ticket_label,
        '{ticket_code}' => $ticket->ticket_code,
        '{ticket_status}' => $status_label,
        '{qr_code_url}' => $ticket->qr_code,
    );

    $html = array();
    $plain = array();

    foreach ($raw as $placeholder => $value) {
        $value = is_string($value) ? $value : '';

        if ($placeholder === '{event_page_url}' || $placeholder === '{qr_code_url}') {
            $safe_value = mms_events_safe_url($value);
            $html[$placeholder] = $safe_value !== '' ? esc_url($safe_value) : '';
            $plain[$placeholder] = $safe_value;
        } else {
            $html[$placeholder] = esc_html($value);
            $plain[$placeholder] = sanitize_text_field($value);
        }
    }

    $html['{qr_code}'] = $qr_html;
    $plain['{qr_code}'] = isset($raw['{qr_code_url}']) ? mms_events_safe_url($raw['{qr_code_url}']) : '';

    return array($html, $plain);
}

/**
 * Send confirmation or reminder emails for tickets.
 */
function mms_events_send_ticket_email($event, $ticket, $context = 'confirmation') {
    if (!$event || !$ticket) {
        return false;
    }

    list($sender_name, $sender_email, $headers) = mms_events_get_email_headers($event);

    $recipient_name = trim((string) $ticket->utente_nome);
    if ($recipient_name === '' && !empty($ticket->utente_email)) {
        $recipient_name = $ticket->utente_email;
    }

    $event_title = $event->titolo ? wp_strip_all_tags($event->titolo) : '';
    $default_subject = $context === 'reminder'
        ? sprintf(__('Promemoria: %s sta per iniziare', 'unique-coupon-generator'), $event_title)
        : sprintf(__('Il tuo ticket per %s', 'unique-coupon-generator'), $event_title);

    $event_date = !empty($event->data_evento) ? date_i18n(get_option('date_format'), strtotime($event->data_evento)) : '';
    $event_time = !empty($event->ora_evento) ? $event->ora_evento : '';
    $event_location = !empty($event->luogo) ? $event->luogo : '';
    $event_url = !empty($event->page_id) ? mms_events_safe_url(get_permalink($event->page_id)) : '';

    $ticket_label = mms_events_get_ticket_label($event, $ticket->tipo_ticket);
    $status_label = mms_events_get_ticket_status_label($ticket->stato);

    $qr_html = '';
    $qr_src = mms_events_safe_url($ticket->qr_code ?? '');
    if ($qr_src !== '') {
        $qr_html = '<p><img src="' . esc_url($qr_src) . '" alt="' . esc_attr__('QR Code', 'unique-coupon-generator') . '" style="max-width:200px;height:auto;"></p>';
    }

    $body_default = '<p>' . sprintf(__('Ciao %s,', 'unique-coupon-generator'), esc_html($recipient_name)) . '</p>';

    if ($context === 'reminder') {
        $body_default .= '<p>' . esc_html__('Ti ricordiamo il tuo accesso all’evento.', 'unique-coupon-generator') . '</p>';
    } else {
        $body_default .= '<p>' . esc_html__('Grazie per aver richiesto il tuo ticket digitale. Ecco i dettagli:', 'unique-coupon-generator') . '</p>';
    }

    $body_default .= '<ul>';
    $body_default .= '<li><strong>' . esc_html__('Evento:', 'unique-coupon-generator') . '</strong> ' . esc_html($event_title) . '</li>';
    if ($event_date) {
        $body_default .= '<li><strong>' . esc_html__('Data:', 'unique-coupon-generator') . '</strong> ' . esc_html($event_date) . '</li>';
    }
    if ($event_time) {
        $body_default .= '<li><strong>' . esc_html__('Orario:', 'unique-coupon-generator') . '</strong> ' . esc_html($event_time) . '</li>';
    }
    if ($event_location) {
        $body_default .= '<li><strong>' . esc_html__('Luogo:', 'unique-coupon-generator') . '</strong> ' . esc_html($event_location) . '</li>';
    }
    $body_default .= '<li><strong>' . esc_html__('Tipo di ticket:', 'unique-coupon-generator') . '</strong> ' . esc_html($ticket_label) . '</li>';
    $body_default .= '<li><strong>' . esc_html__('Codice ticket:', 'unique-coupon-generator') . '</strong> ' . esc_html($ticket->ticket_code) . '</li>';
    $body_default .= '<li><strong>' . esc_html__('Stato:', 'unique-coupon-generator') . '</strong> ' . esc_html($status_label) . '</li>';
    if ($event_url) {
        $body_default .= '<li><strong>' . esc_html__('Pagina evento:', 'unique-coupon-generator') . '</strong> <a href="' . esc_url($event_url) . '">' . esc_html($event_url) . '</a></li>';
    }
    $body_default .= '</ul>';

    $body_default .= $qr_html;
    $body_default .= '<p>' . esc_html__('Mostra questo codice all’ingresso per completare il check-in.', 'unique-coupon-generator') . '</p>';

    list($replacements_html, $replacements_plain) = mms_events_prepare_email_replacements($event, $ticket, $qr_html);

    $subject_template = $context === 'reminder' ? $event->email_subject_reminder : $event->email_subject_confirm;
    if (!empty($subject_template)) {
        $subject = strtr($subject_template, $replacements_plain);
        $subject = wp_strip_all_tags($subject);
        if ($subject === '') {
            $subject = $default_subject;
        }
    } else {
        $subject = $default_subject;
    }

    $body_template = $context === 'reminder' ? $event->email_body_reminder : $event->email_body_confirm;
    if (!empty($body_template)) {
        $body = strtr($body_template, $replacements_html);
        $body = wp_kses_post($body);
        if (trim($body ?? '') === '') {
            $body = $body_default;
        }
    } else {
        $body = $body_default;
    }

    return wp_mail($ticket->utente_email, $subject, $body, $headers);
}

/**
 * Process scheduled reminders for upcoming events.
 */
function mms_events_process_reminders() {
    global $wpdb;
    $events_table = mms_events_table('events');
    $tickets_table = mms_events_table('tickets');

    $events = $wpdb->get_results("SELECT * FROM {$events_table} WHERE stato = 'pubblicato' AND reminder_days > 0");
    if (!$events) {
        return;
    }

    $now = current_time('timestamp');

    foreach ($events as $event) {
        if (empty($event->data_evento)) {
            continue;
        }

        $event_timestamp = strtotime($event->data_evento . ' ' . ($event->ora_evento ?: '00:00:00'));
        if ($event_timestamp === false || $event_timestamp <= $now) {
            continue;
        }

        $diff_days = floor(($event_timestamp - $now) / DAY_IN_SECONDS);
        if ($diff_days > (int) $event->reminder_days) {
            continue;
        }

        $tickets = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$tickets_table} WHERE evento_id = %d AND reminder_sent = 0",
            $event->id
        ));

        foreach ($tickets as $ticket) {
            $sent = mms_events_send_ticket_email($event, $ticket, 'reminder');
            if ($sent) {
                mms_events_mark_ticket_reminder_sent($ticket->id);
            }
        }
    }
}

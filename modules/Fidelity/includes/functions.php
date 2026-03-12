<?php

/**
 * Author: Meteora Web <https://meteoraweb.com>
 */

// Blocca accessi diretti
if(!defined('ABSPATH')){exit;}

if(!function_exists('ucg_safe_add_submenu_page')){
    function ucg_safe_add_submenu_page($parent_slug, $page_title, $menu_title, $capability, $menu_slug = '', $callback = '', $position = null){
        $original_parent_slug = $parent_slug;
        $hide_from_menu = !is_string($original_parent_slug) || $original_parent_slug === '';

        if(!is_string($parent_slug) || $parent_slug === ''){
            $parent_slug = 'options-general.php';
        }

        if(!is_string($menu_slug) || $menu_slug === ''){
            $menu_slug = 'ucg-default-slug';
        }

        if(empty($callback)){
            $callback = '__return_null';
        }

        if($position === null){
            $hook_suffix = add_submenu_page($parent_slug, $page_title, $menu_title, $capability, $menu_slug, $callback);
        }else{
            $hook_suffix = add_submenu_page($parent_slug, $page_title, $menu_title, $capability, $menu_slug, $callback, $position);
        }

        if($hide_from_menu){
            remove_submenu_page($parent_slug, $menu_slug);
        }

        return $hook_suffix;
    }
}

/**
 * Wraps the global logger for backwards compatibility.
 *
 * @param string       $message Testo dell'errore.
 * @param array|string $context Dati contestuali opzionali per facilitare il debug.
 */
function ucg_log_error($message, $context = array()){
    if (class_exists('\Meteora\Core\Diagnostic\Logger')) {
        \Meteora\Core\Diagnostic\Logger::log($message, 'fidelity', 'error', $context);
    } else {
        error_log(is_string($message) ? $message : wp_json_encode($message));
    }
}

if (!function_exists('ucg_access_guard_profiles')) {
    /**
     * Contextual messages used by the runtime access guards.
     *
     * @return array<string, array<string, string>>
     */
    function ucg_access_guard_profiles(){
        return array(
            'front_shortcode'   => array('log' => __('Blocco shortcode frontend per licenza non valida.', 'unique-coupon-generator')),
            'front_request'     => array('log' => __('Richiesta coupon bloccata: licenza non valida.', 'unique-coupon-generator')),
            'front_verify'      => array('log' => __('Verifica coupon bloccata: licenza non valida.', 'unique-coupon-generator')),
            'terminal_screen'   => array('log' => __('Terminal fidelity non disponibile senza licenza valida.', 'unique-coupon-generator')),
            'terminal_action'   => array('log' => __('Operazione terminal fidelity negata: licenza non valida.', 'unique-coupon-generator')),
            'terminal_shortcode'=> array('log' => __('Shortcode terminal fidelity bloccato: licenza non valida.', 'unique-coupon-generator')),
            'points_screen'     => array('log' => __('Accesso al pannello punti fidelity bloccato.', 'unique-coupon-generator')),
            'points_action'     => array('log' => __('Richiesta punti fidelity non autorizzata.', 'unique-coupon-generator')),
            'points_shortcode'  => array('log' => __('Shortcode punti fidelity bloccato: licenza non valida.', 'unique-coupon-generator')),
            'coupon_engine'     => array('log' => __('Tentativo di generare un coupon senza licenza valida.', 'unique-coupon-generator')),
            'qr_factory'        => array('log' => __('Tentativo di generare un QR code senza licenza valida.', 'unique-coupon-generator')),
            'fidelity_balance'  => array('log' => __('Richiesta saldo fidelity bloccata: licenza non valida.', 'unique-coupon-generator')),
            'fidelity_mutation' => array('log' => __('Tentativo di modifica punti senza licenza valida.', 'unique-coupon-generator')),
            'fidelity_catalog'  => array('log' => __('Catalogo fidelity non disponibile: licenza non valida.', 'unique-coupon-generator')),
            'fidelity_probe'    => array('log' => __('Verifica utente fidelity non autorizzata.', 'unique-coupon-generator')),
            'fidelity_stream'   => array('log' => __('Storico punti fidelity bloccato.', 'unique-coupon-generator')),
            'mailer_delivery'   => array('log' => __('Tentativo di invio email coupon senza licenza valida.', 'unique-coupon-generator')),
            'mailer_reminder'   => array('log' => __('Tentativo di invio remind senza licenza valida.', 'unique-coupon-generator')),
            'cron_dispatch'     => array('log' => __('Esecuzione cron bloccata: licenza non valida.', 'unique-coupon-generator')),
            'ajax_gate'         => array('log' => __('Chiamata AJAX bloccata: licenza non valida.', 'unique-coupon-generator')),
            'default'           => array('log' => __('Accesso bloccato: licenza non valida.', 'unique-coupon-generator')),
        );
    }
}

if (!function_exists('ucg_enforce_access_point')) {
    /**
     * Centralized guard around ucg_access_granted().
     *
     * @param string $context Internal identifier for logging/branching.
     * @return bool True when access is allowed.
     */
    function ucg_enforce_access_point($context){
        $raw_context = (string) ($context ?? '');
        $context_key = sanitize_key($raw_context);
        if ($context_key === '') {
            $context_key = 'default';
        }

        return true;

        $profiles = ucg_access_guard_profiles();
        $profile  = $profiles[$context_key] ?? $profiles['default'];

        ucg_log_error($profile['log'], array(
            'context' => $context_key,
            'guard'   => 'ucg_enforce_access_point',
        ));

        return false;
    }
}

if (!function_exists('ucg_block_when_forbidden')) {
    /**
     * Helper returning a fallback payload when access is denied.
     *
     * @param string               $context  Internal identifier.
     * @param mixed|callable|null  $fallback Value returned when blocked. If callable it receives the context key.
     * @return mixed|null Returns null when access is allowed, otherwise the provided fallback or a safe default.
     */
    function ucg_block_when_forbidden($context, $fallback = null){
        if (ucg_enforce_access_point($context)) {
            return null;
        }

        if (is_callable($fallback)) {
            return call_user_func($fallback, $context);
        }

        if ($fallback !== null) {
            return $fallback;
        }

        $context_key = sanitize_key((string) ($context ?? ''));
        if ($context_key === '') {
            $context_key = 'default';
        }

        $profiles = ucg_access_guard_profiles();
        $profile  = $profiles[$context_key] ?? $profiles['default'];

        return $profile['fallback'] ?? '';
    }
}

if (!function_exists('myplugin_is_elementor_context')) {
    /**
     * Controlla se siamo all'interno dell'editor di Elementor o in un'anteprima.
     *
     * @return bool
     */
    function myplugin_is_elementor_context() {
        if (!class_exists('\Elementor\Plugin')) {
            return false;
        }

        $elementor = \Elementor\Plugin::$instance;

        if (isset($_REQUEST['action']) && in_array($_REQUEST['action'], array('elementor', 'elementor_ajax'))) {
            return true;
        }

        if (isset($_REQUEST['elementor-preview']) && $_REQUEST['elementor-preview']) {
            return true;
        }

        if ($elementor->editor && method_exists($elementor->editor, 'is_edit_mode') && $elementor->editor->is_edit_mode()) {
            return true;
        }

        if ($elementor->preview && method_exists($elementor->preview, 'is_preview_mode') && $elementor->preview->is_preview_mode()) {
            return true;
        }

        return false;
    }
}

/**
 * Restituisce il dominio del sito senza prefissi come "www.".
 *
 * @return string
 */
function ucg_get_clean_domain(){
    $domain = parse_url(home_url(), PHP_URL_HOST);
    if(empty($domain)){
        return 'example.com';
    }

    return preg_replace('/^www\./i', '', $domain);
}

/**
 * Convert a localized numeric string to float.
 * Supports values like "1.234,56" or "1,234.56".
 *
 * @param string $value
 * @return float
 */
function ucg_parse_float($value){
    $value = trim((string) $value);
    $value = str_replace(' ', '', $value ?? '');

    if(strpos($value ?? '', ',') !== false && strpos($value ?? '', '.') !== false){
        // Determine last separator as decimal sign
        if(strrpos($value ?? '', ',') > strrpos($value ?? '', '.')){
            // format 1.234,56
            $value = str_replace('.', '', $value ?? '');
            $value = str_replace(',', '.', $value ?? '');
        }else{
            // format 1,234.56
            $value = str_replace(',', '', $value ?? '');
        }
    }else{
        $value = str_replace(',', '.', $value ?? '');
    }

    return round((float) $value, 2);
}

/**
 * Retrieve all configured coupon sets with a safe default.
 *
 * @return array
 */
function ucg_get_coupon_sets(){
    $sets = get_option('mms_coupon_sets', array());
    if (!is_array($sets)) {
        return array();
    }

    return $sets;
}

/**
 * List of available coupon set statuses.
 *
 * @return array
 */
function ucg_coupon_set_statuses(){
    return array(
        'draft'  => __('Bozza', 'unique-coupon-generator'),
        'active' => __('Attivo', 'unique-coupon-generator'),
        'closed' => __('Chiuso', 'unique-coupon-generator'),
    );
}

/**
 * Normalize the coupon set data for public usage.
 *
 * @param string $base_coupon_code Coupon set identifier.
 * @return array|null
 */
function ucg_get_coupon_set($base_coupon_code){
    $base_coupon_code = sanitize_text_field((string) $base_coupon_code);
    if ($base_coupon_code === '') {
        return null;
    }

    $sets = ucg_get_coupon_sets();
    if (empty($sets[$base_coupon_code]) || !is_array($sets[$base_coupon_code])) {
        return null;
    }

    $defaults = array(
        'name'        => $base_coupon_code,
        'shortcode'   => '[richiedi_coupon base="' . $base_coupon_code . '"]',
        'fields'      => array('first_name', 'last_name', 'email', 'phone'),
        'show_whatsapp_opt_in' => true,
        'allow_png_download'   => true,
        'allow_pdf_download'   => true,
        'custom_field_label' => '',
        'custom_field_key'   => '',
        'custom_fields'      => array(),
        'fidelity'    => array(
            'enabled'         => false,
            'points_per_euro' => 1,
            'signup_points'   => 0,
        ),
        'privacy'     => array(
            'required' => false,
            'page_id'  => 0,
        ),
        'status'      => 'active',
        'image_id'    => 0,
        'image_url'   => '',
        'page_id'     => 0,
        'whatsapp_message' => '',
    );

    $set = wp_parse_args($sets[$base_coupon_code], $defaults);
    $set['show_whatsapp_opt_in'] = !empty($set['show_whatsapp_opt_in']);
    $set['allow_png_download'] = array_key_exists('allow_png_download', $set) ? !empty($set['allow_png_download']) : true;
    $set['allow_pdf_download'] = array_key_exists('allow_pdf_download', $set) ? !empty($set['allow_pdf_download']) : true;

    if (!isset($set['whatsapp_message']) || !is_string($set['whatsapp_message'])) {
        $set['whatsapp_message'] = '';
    } else {
        $set['whatsapp_message'] = (string) $set['whatsapp_message'];
    }

    if (!empty($set['image_id']) && empty($set['image_url']) && function_exists('wp_get_attachment_image_url')) {
        $image_url = wp_get_attachment_image_url((int) $set['image_id'], 'large');
        if ($image_url) {
            $set['image_url'] = $image_url;
        }
    }

    $page_id = (int) ($set['page_id'] ?? 0);
    $page = $page_id ? get_post($page_id) : null;
    if (!$page || 'trash' === get_post_status($page)) {
        $page = null;
    }

    if (!$page && function_exists('plugin_get_page_by_title')) {
        $page = plugin_get_page_by_title(sprintf(__('Richiedi %s', 'unique-coupon-generator'), $set['name']));
        if ($page) {
            $set['page_id'] = (int) $page->ID;
        }
    }

    $set['request_url'] = ($page && !is_wp_error($page)) ? get_permalink($page) : '';

    return $set;
}

/**
 * Determine if a coupon set is marked as active.
 *
 * @param array $set Coupon set data.
 * @return bool
 */
function ucg_coupon_set_is_active($set){
    if (empty($set) || !is_array($set)) {
        return false;
    }

    return ($set['status'] ?? 'active') === 'active';
}

/**
 * Determine if a coupon set is closed.
 *
 * @param array $set Coupon set data.
 * @return bool
 */
function ucg_coupon_set_is_closed($set){
    if (empty($set) || !is_array($set)) {
        return false;
    }

    return ($set['status'] ?? '') === 'closed';
}

/**
 * Retrieve the image URL associated with a coupon set.
 *
 * @param array  $set  Coupon set data.
 * @param string $size Image size to retrieve.
 * @return string
 */
function ucg_coupon_set_image_url($set, $size = 'large'){
    if (empty($set) || !is_array($set)) {
        return '';
    }

    if (!empty($set['image_id']) && function_exists('wp_get_attachment_image_url')) {
        $image_url = wp_get_attachment_image_url((int) $set['image_id'], $size);
        if ($image_url) {
            return $image_url;
        }
    }

    return isset($set['image_url']) ? esc_url_raw((string) $set['image_url']) : '';
}

/**
 * Default WhatsApp message template.
 *
 * @return string
 */
function ucg_get_default_whatsapp_message() {
    return __('Ecco il tuo QR: {qr_link}', 'unique-coupon-generator');
}

/**
 * Return the list of supported placeholders for WhatsApp messages.
 *
 * @return array<string,string> Map of placeholder => description.
 */
function ucg_get_whatsapp_placeholders() {
    return array(
        '{qr_link}'     => __('Link diretto al QR code generato', 'unique-coupon-generator'),
        '{coupon_code}' => __('Codice coupon o ticket generato', 'unique-coupon-generator'),
        '{user_name}'   => __('Nome dell’utente che ha richiesto il QR', 'unique-coupon-generator'),
        '{line_break}'  => __('Inserisce un ritorno a capo nel messaggio', 'unique-coupon-generator'),
    );
}

/**
 * Provide sample data used for WhatsApp previews in the admin UI.
 *
 * @return array<string,string>
 */
function ucg_get_whatsapp_preview_data() {
    return array(
        'qr_link'     => home_url('/qr-demo'),
        'coupon_code' => 'ABC123',
        'user_name'   => 'Mario Rossi',
    );
}

/**
 * Sanitize a WhatsApp message keeping basic formatting tags.
 *
 * @param string $message Raw message provided by the admin.
 * @return string
 */
function ucg_sanitize_whatsapp_message($message) {
    $message = (string) $message;
    if ($message === '') {
        return '';
    }

    $decoded = rawurldecode($message);
    if ($decoded !== '') {
        $message = $decoded;
    }

    // Normalise common HTML newline placeholders before removing any markup.
    $message = preg_replace('/<br\s*\/?\s*>/i', "\n", $message);
    $message = str_replace(array("\r\n", "\r"), "\n", $message);

    // Strip any remaining markup while keeping the line breaks intact.
    if (function_exists('wp_strip_all_tags')) {
        $message = wp_strip_all_tags($message, false);
    } else {
        $message = strip_tags($message);
    }

    // Collapse excessive horizontal whitespace but keep the line breaks as-is.
    $message = preg_replace("/[\t\v\f\x{00A0}]+/u", ' ', $message);
    $message = preg_replace("/ +\n/", "\n", $message);
    $message = preg_replace("/\n +/", "\n", $message);

    // Reduce three or more consecutive new lines to a maximum of two.
    $message = preg_replace("/\n{3,}/", "\n\n", $message);

    return trim($message);
}

/**
 * Retrieve the WhatsApp message configured in the admin or fallback to the default.
 *
 * @return string
 */
function ucg_get_whatsapp_message_template() {
    $template = get_option('ucg_whatsapp_message', '');
    if (!is_string($template)) {
        $template = '';
    }

    $template = ucg_sanitize_whatsapp_message($template);
    if (trim($template) === '') {
        $template = ucg_get_default_whatsapp_message();
    }

    return $template;
}

/**
 * Replace supported placeholders inside a WhatsApp message template.
 *
 * @param string $template Message template.
 * @param array  $data     Placeholder replacements.
 * @return string
 */
function ucg_prepare_whatsapp_message($template, array $data = array()) {
    if ($template === '') {
        $template = ucg_get_default_whatsapp_message();
    }

    $qr_link = isset($data['qr_link']) ? (string) $data['qr_link'] : '';
    if ($qr_link !== '') {
        if (function_exists('mms_events_safe_url')) {
            $qr_link = mms_events_safe_url($qr_link);
        } else {
            try {
                $qr_link = esc_url_raw($qr_link);
            } catch (Throwable $throwable) {
                $qr_link = '';
            }
        }
    }
    $coupon_code = isset($data['coupon_code']) ? sanitize_text_field((string) $data['coupon_code']) : '';
    $user_name = isset($data['user_name']) ? sanitize_text_field((string) $data['user_name']) : '';

    $replacements = array(
        '{qr_link}'     => $qr_link,
        '{coupon_code}' => $coupon_code,
        '{user_name}'   => $user_name,
        '{line_break}'  => "\n",
    );

    return strtr($template, $replacements);
}

/**
 * Encode a WhatsApp message preserving newline placeholders.
 *
 * @param string $message Raw message content.
 * @return string Encoded string ready for the `text` query parameter.
 */
function ucg_encode_whatsapp_message($message) {
    $message = (string) $message;
    if ($message === '') {
        return '';
    }

    $decoded = rawurldecode($message);
    if ($decoded !== '') {
        $message = $decoded;
    }

    $message = preg_replace('/<br\s*\/?\s*>/i', "\n", $message);
    $message = str_replace(array("\r\n", "\r"), "\n", $message);

    $placeholder = '__UCG_LINEBREAK__';
    $message_with_placeholders = str_replace("\n", $placeholder, $message);

    $encoded = rawurlencode($message_with_placeholders);

    return str_replace($placeholder, '%0A', $encoded);
}

/**
 * Normalize a phone number ensuring the prefix is included.
 *
 * @param string $phone Raw phone value provided by the user.
 * @return array{
 *     full: string,
 *     digits: string,
 *     display: string,
 *     has_prefix: bool,
 *     is_valid: bool
 * }
 */
function ucg_normalize_phone_number($phone) {
    $raw = trim((string) $phone);
    $digits = preg_replace('/\D+/', '', $raw);
    $has_prefix = strpos($raw, '+') === 0;

    $display = trim(preg_replace('/[^0-9+\s]/', '', $raw));
    $display = preg_replace('/\s+/', ' ', $display ?? '');
    if ($display === '' || strpos($display, '+39') !== 0) {
        $display_digits = '';
        if ($digits !== '') {
            if (strpos($digits, '39') === 0) {
                $display_digits = substr($digits, 2);
            } else {
                $display_digits = $digits;
            }
            $display_digits = substr($display_digits, 0, 10);
        }

        $display = '+39' . $display_digits;
    }

    $full = '';
    $normalized_digits = '';
    $is_valid = false;

    if ($has_prefix && $digits !== '') {
        $candidate = '+' . $digits;
        if (preg_match('/^\+39\d{10}$/', $candidate)) {
            $full = $candidate;
            $normalized_digits = substr($candidate, 1);
            $display = $candidate;
            $is_valid = true;
        }
    }

    if ($full === '' && $has_prefix) {
        $full = '+' . $digits;
    }

    return array(
        'full'       => $full,
        'digits'     => $normalized_digits,
        'display'    => $display,
        'has_prefix' => $has_prefix,
        'is_valid'   => $is_valid,
    );
}

/**
 * Persist a simple PDF document on disk using plain text lines.
 *
 * @param string $title     Document title.
 * @param array  $lines     Lines of text to include.
 * @param string $subfolder Folder name inside uploads.
 * @param string $filename  File basename without extension.
 * @param array  $options   Optional settings (image_url, image_path, image_options).
 * @return string Generated PDF URL or empty string on failure.
 */
function ucg_generate_pdf_file($title, array $lines, $subfolder, $filename, array $options = array()) {
    if (!class_exists('UCG_PDF_Exporter')) {
        require_once __DIR__ . '/classes/UCG_PDF_Exporter.php';
    }

    if (empty($lines)) {
        return '';
    }

    $upload_dir = wp_upload_dir();
    if (!empty($upload_dir['error'])) {
        ucg_log_error('Impossibile accedere alla cartella upload per generare il PDF: ' . $upload_dir['error']);
        return '';
    }

    $safe_folder = trim($subfolder, '/');
    $base_dir = trailingslashit($upload_dir['basedir']) . $safe_folder . '/';
    if (!wp_mkdir_p($base_dir)) {
        ucg_log_error('Impossibile creare la cartella PDF: ' . $base_dir);
        return '';
    }

    $safe_name = sanitize_file_name($filename);
    if ($safe_name === '') {
        $safe_name = uniqid('ucg-', true);
    }

    $pdf = new UCG_PDF_Exporter($title);
    foreach ($lines as $line) {
        $pdf->addMessage((string) $line);
    }

    $image_path = '';
    if (!empty($options['image_path'])) {
        $image_path = (string) $options['image_path'];
    } elseif (!empty($options['image_url'])) {
        $image_path = ucg_upload_path_from_url($options['image_url']);
    }

    if ($image_path !== '' && method_exists($pdf, 'addImage')) {
        $image_options = isset($options['image_options']) && is_array($options['image_options']) ? $options['image_options'] : array();
        $pdf->addImage($image_path, $image_options);
    }

    if (!method_exists($pdf, 'render')) {
        return '';
    }

    $content = $pdf->render();
    if ($content === '') {
        return '';
    }

    $file_path = $base_dir . $safe_name . '.pdf';
    if (file_put_contents($file_path, $content) === false) {
        ucg_log_error('Impossibile salvare il PDF in ' . $file_path);
        return '';
    }

    $file_url = trailingslashit($upload_dir['baseurl']) . $safe_folder . '/' . $safe_name . '.pdf';

    if (function_exists('mms_events_safe_url')) {
        $file_url = mms_events_safe_url($file_url);
    } else {
        try {
            $file_url = esc_url_raw($file_url);
        } catch (Throwable $throwable) {
            $file_url = '';
        }
    }

    return $file_url;
}

/**
 * Convert an uploads URL into a filesystem path.
 *
 * @param string $url URL pointing inside the uploads directory.
 * @return string Absolute path or empty string if not accessible.
 */
function ucg_upload_path_from_url($url) {
    $url = trim((string) $url);
    if ($url === '' || !function_exists('wp_upload_dir')) {
        return '';
    }

    $upload_dir = wp_upload_dir();
    if (!empty($upload_dir['error'])) {
        return '';
    }

    $base_url = trailingslashit($upload_dir['baseurl']);
    if (strpos($url, $base_url) !== 0) {
        return '';
    }

    $relative = ltrim(substr($url, strlen($base_url)), '/');
    if ($relative === '') {
        return '';
    }

    $relative = str_replace(array('../', '..\\'), '', $relative);
    $path = trailingslashit($upload_dir['basedir']) . $relative;
    if (function_exists('wp_normalize_path')) {
        $path = wp_normalize_path($path);
    }

    if (!file_exists($path)) {
        return '';
    }

    return $path;
}

/**
 * Build a wa.me link ready to be opened in the browser.
 *
 * @param string $phone Full phone number including prefix.
 * @param array  $data  Additional data used for placeholders.
 * @return string
 */
function ucg_generate_whatsapp_link($phone, array $data = array()) {
    $normalized = ucg_normalize_phone_number($phone);
    if (!$normalized['is_valid']) {
        return '';
    }

    $template = isset($data['template']) ? ucg_sanitize_whatsapp_message((string) $data['template']) : '';
    if ($template === '') {
        $template = ucg_get_whatsapp_message_template();
    }

    $message = ucg_prepare_whatsapp_message($template, $data);
    if ($message === '') {
        $message = ucg_get_default_whatsapp_message();
    }

    $encoded_message = ucg_encode_whatsapp_message($message);
    if ($encoded_message === '') {
        return '';
    }

    return 'https://wa.me/' . $normalized['digits'] . '?text=' . $encoded_message;
}

/**
 * Store a WhatsApp link temporarily to be opened after a redirect.
 *
 * @param string $link WhatsApp link to queue.
 * @return string Unique key used to retrieve the link.
 */
function ucg_queue_whatsapp_link($link) {
    $link = (string) $link;
    if ($link !== '') {
        if (function_exists('mms_events_safe_url')) {
            $link = mms_events_safe_url($link);
        } else {
            try {
                $link = esc_url_raw($link);
            } catch (Throwable $throwable) {
                $link = '';
            }
        }
    }

    if ($link === '') {
        return '';
    }

    $key = wp_generate_password(10, false, false);
    set_transient('ucg_whatsapp_link_' . $key, $link, 5 * MINUTE_IN_SECONDS);

    return $key;
}

/**
 * Output a small script that redirects to the queued WhatsApp link.
 */
function ucg_output_queued_whatsapp_link() {
    if (is_admin() || empty($_GET['ucg_whatsapp'])) {
        return;
    }

    $key = sanitize_key(wp_unslash($_GET['ucg_whatsapp']));
    if ($key === '') {
        return;
    }

    $link = get_transient('ucg_whatsapp_link_' . $key);
    if (!$link) {
        return;
    }

    delete_transient('ucg_whatsapp_link_' . $key);

    $safe_link = function_exists('mms_events_safe_url') ? mms_events_safe_url($link) : $link;
    if ($safe_link === '') {
        return;
    }

    $link_js = wp_json_encode($safe_link);
    if (!$link_js) {
        return;
    }

    echo '<script type="text/javascript">(function(){var link=' . $link_js . ';if(!link){return;}function ucgRedirectWhatsapp(){window.location.href=link;}if(document.readyState==="complete"){ucgRedirectWhatsapp();}else{window.addEventListener("load", ucgRedirectWhatsapp);}})();</script>';
}
add_action('wp_footer', 'ucg_output_queued_whatsapp_link', 100);

if (!function_exists('ucg_render_whatsapp_reminder_modal')) {
    /**
     * Render a reusable modal reminding admins to update the WhatsApp message.
     *
     * @return string
     */
    function ucg_render_whatsapp_reminder_modal() {
        static $rendered = false;

        if ($rendered) {
            return '';
        }

        $rendered = true;
        $settings_url = esc_url(admin_url('admin.php?page=ucg-admin-whatsapp'));

        ob_start();
        ?>
        <div class="ucg-modal" data-ucg-modal="whatsapp-reminder" data-ucg-manual>
            <div class="ucg-modal__dialog" role="alertdialog" aria-modal="true">
                <div class="ucg-modal__icon dashicons dashicons-format-chat"></div>
                <h2><?php esc_html_e('Ricorda di aggiornare il testo WhatsApp', 'unique-coupon-generator'); ?></h2>
                <p><?php esc_html_e('Personalizza il messaggio inviato su WhatsApp dalla pagina dedicata prima di attivare questa opzione.', 'unique-coupon-generator'); ?></p>
                <div class="ucg-modal__actions">
                    <a class="button button-primary" href="<?php echo $settings_url; ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e('Apri impostazioni WhatsApp', 'unique-coupon-generator'); ?></a>
                    <button type="button" class="button button-secondary" data-ucg-close-modal><?php esc_html_e('Ho capito', 'unique-coupon-generator'); ?></button>
                </div>
            </div>
        </div>
        <?php

        return ob_get_clean();
    }
}

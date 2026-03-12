<?php
namespace Meteora\Core\Diagnostic;

class Logger {
    /**
     * Records a log entry into the centralized error log table.
     *
     * @param string $message The log message.
     * @param string $module  The module identifier (e.g., 'fidelity', 'news-engine').
     * @param string $severity The severity ('info', 'warning', 'error').
     * @param array  $context Optional context data.
     */
    public static function log($message, $module = 'system', $severity = 'error', $context = array()) {
        global $wpdb;
        $table = $wpdb->prefix . 'mms_error_log';

        if (!is_string($message)) {
            $message = wp_json_encode($message);
        }

        $sanitized_message = sanitize_textarea_field((string) $message);

        if (!empty($context) && is_array($context)) {
            $prepared_context = array();
            $index = 0;

            foreach ($context as $key => $value) {
                $index++;
                $normalized_key = is_string($key) ? sanitize_key($key) : '';
                if ($normalized_key === '') {
                    $normalized_key = 'item_' . $index;
                }

                if (is_scalar($value) || (is_object($value) && method_exists($value, '__toString'))) {
                    $prepared_context[$normalized_key] = sanitize_text_field((string) $value);
                } else {
                    $prepared_context[$normalized_key] = wp_json_encode($value);
                }
            }

            $context_json = wp_json_encode($prepared_context);
            if ($context_json) {
                $sanitized_message .= ' | context: ' . $context_json;
            }

            $sanitized_message = sanitize_textarea_field($sanitized_message);
        }

        // Ensure table exists
        if ($wpdb->get_var("SHOW TABLES LIKE '{$table}'") !== $table) {
            error_log('Meteora System Logger error: Table mms_error_log not found.');
            error_log("[$module] [$severity] " . $sanitized_message);
            return;
        }

        $inserted = $wpdb->insert(
            $table,
            array(
                'module'    => sanitize_text_field($module),
                'severity'  => sanitize_text_field($severity),
                'message'   => $sanitized_message,
                'timestamp' => current_time('mysql'),
            ),
            array('%s', '%s', '%s', '%s')
        );

        if ($inserted === false && method_exists($wpdb, 'print_error')) {
            error_log('Meteora System Logger insert failed: ' . $wpdb->last_error);
        }
    }
}

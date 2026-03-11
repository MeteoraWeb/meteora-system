<?php
// Mock WP functions for testing activation script
define('ABSPATH', __DIR__ . '/');
define('WP_CONTENT_DIR', ABSPATH . 'wp-content');
define('WPINC', 'wp-includes');

function add_action() {}
function register_activation_hook() {}
function add_filter() {}
function wp_next_scheduled() {}
function wp_schedule_event() {}
function plugin_dir_url() { return ''; }
function plugin_dir_path() { return __DIR__ . '/'; }
function get_option() { return ''; }

class mock_wpdb {
    public $prefix = 'wp_';
    public function get_charset_collate() { return ''; }
    public function get_var() { return null; }
    public function get_results() { return null; }
    public function query() { return true; }
    public function prepare() { return ''; }
}
$wpdb = new mock_wpdb();

function dbDelta() {}

// Run plugin file
require_once 'meteora-system.php';

echo "Plugin loads without fatal errors.\n";

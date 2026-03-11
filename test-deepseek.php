<?php
// mock
function wp_remote_post() {
    return new Exception("mock");
}
function is_wp_error($obj) {
    return $obj instanceof Exception;
}

require_once 'core/Api/DeepSeekApi.php';

try {
    \Meteora\Core\Api\DeepSeekApi::generateContent("test", "test");
} catch (\Throwable $e) {
    echo "Caught: " . $e->getMessage();
}

<?php

/**
 * Author: Meteora Web <https://meteoraweb.com>
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Legacy placeholder for the deprecated "Pagamento in loco" WooCommerce gateway.
 *
 * Older versions of the plugin registered a fake WooCommerce gateway so that
 * tickets reserved for on-site payment could pass through the checkout. The new
 * flow bypasses WooCommerce entirely for this scenario: the ticket is generated
 * immediately with status "da pagare" and the customer receives the QR code
 * without hitting the WooCommerce validation flow.
 *
 * The file is kept to avoid fatal errors during updates and to make sure any
 * lingering references to the old gateway identifier are ignored.
 */
add_filter('woocommerce_payment_gateways', 'mms_events_remove_legacy_in_loco_gateway', 99);

/**
 * Remove the legacy gateway from the WooCommerce gateway registry.
 *
 * @param array<int|string,string|object> $methods Registered gateway class names.
 * @return array
 */
function mms_events_remove_legacy_in_loco_gateway($methods) {
    if (!is_array($methods)) {
        return $methods;
    }

    foreach ($methods as $index => $method) {
        if ($method === 'UCG_Payment_In_Loco') {
            unset($methods[$index]);
        }
    }

    return $methods;
}

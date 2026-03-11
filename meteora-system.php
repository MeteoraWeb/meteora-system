<?php
/**
 * Plugin Name: Meteora System
 * Description: La Suite di Marketing e Gestione Definitiva per WordPress. Unifica strumenti isolati in un ecosistema modulare.
 * Version: 1.0.0
 * Author: Meteora Web
 * Text Domain: meteora-system
 */

if ( ! defined( 'ABSPATH' ) ) exit;

// Carica l'autoloader di Composer
if ( file_exists( __DIR__ . '/vendor/autoload.php' ) ) {
    require_once __DIR__ . '/vendor/autoload.php';
}

/**
 * Inizializza il plugin principale
 */
function meteora_system_init() {
    \Meteora\Core\SystemCore::instance();
}
add_action( 'plugins_loaded', 'meteora_system_init' );

/**
 * Attivazione Plugin
 */
register_activation_hook( __FILE__, ['\Meteora\Core\SystemCore', 'activate'] );

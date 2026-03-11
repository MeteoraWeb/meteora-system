<?php
namespace Meteora\Core;

use Meteora\Core\Menu\MenuManager;
use Meteora\Core\Database\DatabaseManager;

class SystemCore {
    /**
     * @var SystemCore
     */
    private static $instance = null;

    /**
     * @return SystemCore
     */
    public static function instance() {
        if ( is_null( self::$instance ) ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        $this->init();
    }

    private function init() {
        // Init core managers
        MenuManager::instance();

        // Init modules
        \Meteora\Modules\CarrelliPersi\CartSaver::instance();
        \Meteora\Modules\GlaSync\ForceSync::instance();
        \Meteora\Modules\Saldi\SalesEngine::instance();
        \Meteora\Modules\Seo\SeoAutomation::instance();
        \Meteora\Modules\Seo\SeoUltra::instance();
        \Meteora\Modules\ArticoliAi\NewsEngine::instance();
        \Meteora\Modules\Fidelity\Module::instance();
        \Meteora\Modules\Diagnostic\DiagnosticEngine::instance();
    }

    public static function activate() {
        DatabaseManager::createTables();
    }
}

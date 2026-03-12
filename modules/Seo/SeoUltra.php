<?php
namespace Meteora\Modules\Seo;

use Meteora\Core\Menu\MenuManager;

class SeoUltra {
    /**
     * @var SeoUltra
     */
    private static $instance = null;

    /**
     * @return SeoUltra
     */
    public static function instance() {
        if ( is_null( self::$instance ) ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        MenuManager::instance()->registerTab('tab-seo-ultra', 'SEO Ultra', 'dashicons-star-filled', [$this, 'renderTab'], 'meteora-system', 'SEO');
    }

    public function renderTab() {
        if (class_exists('\Meteora\Core\License\MeteoraLicense') && !\Meteora\Core\License\MeteoraLicense::is_module_allowed('seo')) {
            echo '<div class="mpe-card"><p style="color:red; font-weight:bold;">Modulo SEO Ultra non abilitato. Inserisci una licenza valida nel pannello Impostazioni per sbloccare questa funzionalità.</p></div>';
            return;
        }

        echo '<div class="mpe-card" style="border-left: 4px solid #ec4899;">';
        echo '<h3>SEO Ultra</h3>';
        echo '<p>Questa è la sezione avanzata per l\'ottimizzazione SEO. (Funzionalità in fase di espansione).</p>';
        echo '</div>';
    }
}

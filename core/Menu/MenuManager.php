<?php
namespace Meteora\Core\Menu;

class MenuManager {
    /**
     * @var MenuManager
     */
    private static $instance = null;

    private $tabs = [];

    /**
     * @return MenuManager
     */
    public static function instance() {
        if ( is_null( self::$instance ) ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        add_action('admin_menu', [$this, 'registerMenu']);
    }

    public function registerMenu() {
        add_menu_page(
            'Meteora System',
            'Meteora System',
            'manage_options',
            'meteora-system',
            [$this, 'renderPage'],
            'dashicons-superhero-alt',
            56
        );
    }

    public function registerTab($id, $title, $icon, $callback) {
        $this->tabs[$id] = [
            'title' => $title,
            'icon' => $icon,
            'callback' => $callback
        ];
    }

    public function renderPage() {
        // Sensore di Rilevamento Ambientale
        $has_woo = class_exists('WooCommerce');

        echo '<style>
            :root {
                --m-bg: #f8fafc; --m-dark: #0f172a; --m-panel: #1e293b;
                --m-blue: #3b82f6; --m-red: #ef4444; --m-green: #22c55e; --m-orange: #f59e0b; --m-pink: #ec4899;
            }

            .mpe-wrap { background: #fff; font-family: "Inter", system-ui, -apple-system, sans-serif; max-width: 99%; margin: 20px auto; color: var(--m-dark); border-radius: 6px; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1); border: 1px solid #cbd5e1; overflow: hidden; }

            /* HEADER */
            .mpe-header { background: #0f172a; color: #fff; padding: 15px 20px; display: flex; justify-content: space-between; align-items: center; border-bottom: 4px solid var(--m-blue); }
            .mpe-header h1 { color: #fff; margin: 0; font-size: 18px; font-weight: 700; display: flex; align-items: center; gap: 10px; text-transform: uppercase; letter-spacing: 0.5px; }
            .mpe-badge { background: var(--m-blue); padding: 2px 6px; border-radius: 4px; font-size: 10px; font-weight: 800; }

            /* NAVIGAZIONE */
            .mpe-nav { display: flex; background: #f8fafc; border-bottom: 1px solid #e2e8f0; padding: 0 5px; user-select: none; flex-wrap: wrap; }
            .mpe-nav-item { padding: 12px 18px; cursor: pointer; font-weight: 600; font-size: 13px; color: #64748b; border-bottom: 3px solid transparent; transition: 0.1s; display: flex; align-items: center; gap: 6px; }
            .mpe-nav-item:hover { background: #f1f5f9; color: var(--m-dark); }
            .mpe-nav-item.active { background: #fff; color: var(--m-blue); border-bottom-color: var(--m-blue); border-right: 1px solid #e2e8f0; border-left: 1px solid #e2e8f0; }

            /* CONTENUTO */
            .mpe-body { padding: 25px; min-height: 600px; background: #fff; }
            .mpe-tab-content { display: none; }
            .mpe-tab-content.active { display: block; }

            /* CARDS & GRIDS */
            .mpe-card { background: #fff; border: 1px solid #e2e8f0; border-radius: 6px; padding: 20px; margin-bottom: 20px; box-shadow: 0 1px 2px 0 rgba(0,0,0,0.05); }
            .mpe-grid-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; }
            .mpe-label { font-size: 11px; font-weight: 700; color: #475569; text-transform: uppercase; display: block; margin-bottom: 5px; }
            .mpe-input { width: 100%; padding: 8px 10px; border: 1px solid #cbd5e1; border-radius: 4px; font-size: 14px; background: #f8fafc; transition: 0.2s; }
            .mpe-input:focus { border-color: var(--m-blue); background: #fff; outline: none; }

            /* BOTTONI STYLE */
            .btn-mpe { padding: 10px 18px; border-radius: 4px; font-weight: 700; font-size: 12px; border: none; cursor: pointer; text-transform: uppercase; transition: 0.2s; display: inline-flex; align-items: center; gap: 6px; text-decoration: none; }
            .btn-blue { background: var(--m-blue); color: #fff; } .btn-blue:hover { background: #1d4ed8; }
            .btn-red { background: var(--m-red); color: #fff; } .btn-red:hover { background: #b91c1c; }
            .btn-green { background: var(--m-green); color: #fff; } .btn-green:hover { background: #15803d; }
            .btn-grey { background: #e2e8f0; color: #334155; border: 1px solid #cbd5e1; } .btn-grey:hover { background: #cbd5e1; }
            .btn-outline { background: transparent; border: 1px solid var(--m-blue); color: var(--m-blue); } .btn-outline:hover { background: #eff6ff; }
            .btn-small { padding: 4px 8px; font-size: 10px; height: 24px; }

            /* TABLES */
            .mpe-table { width: 100%; border-collapse: collapse; font-size: 12px; border: 1px solid #e2e8f0; margin-bottom: 15px; }
            .mpe-table th { background: #f1f5f9; padding: 10px; text-align: left; font-weight: 700; color: #475569; border-bottom: 2px solid #e2e8f0; font-size: 11px; text-transform: uppercase; }
            .mpe-table td { padding: 8px 10px; border-bottom: 1px solid #f1f5f9; color: #334155; vertical-align: middle; }

            /* CONSOLE */
            .console-window { background: #1e1e1e; color: #a3e635; padding: 15px; border-radius: 4px; font-family: "Courier New", monospace; height: 500px; overflow-y: auto; font-size: 12px; line-height: 1.4; border: 1px solid #333; }
        </style>';

        $default_tab = '';
        if (!empty($this->tabs)) {
            $default_tab = array_key_first($this->tabs);
        }

        echo '<script>
            function openTab(id) {
                localStorage.setItem("meteora_system_tab", id);
                document.querySelectorAll(".mpe-tab-content").forEach(t => t.classList.remove("active"));
                document.querySelectorAll(".mpe-nav-item").forEach(n => n.classList.remove("active"));

                const tabContent = document.getElementById(id);
                const tabNav = document.getElementById("nav-"+id);

                if(tabContent) tabContent.classList.add("active");
                if(tabNav) tabNav.classList.add("active");
            }
            document.addEventListener("DOMContentLoaded", () => {
                let t = localStorage.getItem("meteora_system_tab") || "' . $default_tab . '";
                if(!document.getElementById(t)) { t = "' . $default_tab . '"; }
                if(t) openTab(t);
            });
        </script>';

        echo '<div class="mpe-wrap">';

        // HEADER
        echo '<div class="mpe-header">
                <h1><span class="dashicons dashicons-superhero-alt"></span> Meteora System <span class="mpe-badge">V1.0.0</span></h1>
                <div style="font-size:11px; opacity:0.8; font-family:monospace;">SYSTEM ' . ($has_woo ? 'WOOCOMMERCE DETECTED' : 'STANDALONE MODE') . '</div>
              </div>';

        // NAVIGAZIONE MODULARE
        echo '<div class="mpe-nav">';
        foreach ($this->tabs as $id => $tab) {
             echo '<div id="nav-' . esc_attr($id) . '" class="mpe-nav-item" onclick="openTab(\'' . esc_attr($id) . '\')"><span class="dashicons ' . esc_attr($tab['icon']) . '"></span> ' . esc_html($tab['title']) . '</div>';
        }
        echo '</div>';

        // BODY START
        echo '<div class="mpe-body">';

        foreach ($this->tabs as $id => $tab) {
            echo '<div id="' . esc_attr($id) . '" class="mpe-tab-content">';
            if (is_callable($tab['callback'])) {
                call_user_func($tab['callback']);
            }
            echo '</div>';
        }

        echo '</div>'; // End Body
        echo '<div style="text-align:center; padding:20px; color:#94a3b8; font-size:11px;">Meteora SYSTEM | <span style="color:var(--m-green)">Status Operativo Assoluto</span></div>';
        echo '</div>'; // End Wrap
    }
}

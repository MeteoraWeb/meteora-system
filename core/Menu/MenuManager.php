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

    // Groups main tabs into logical sections
    private $grouped_tabs = [];

    public function registerTab($id, $title, $icon, $callback, $slug = 'meteora-system', $group = 'Impostazioni') {
        $this->tabs[$id] = [
            'title' => $title,
            'icon' => $icon,
            'callback' => $callback,
            'slug' => $slug,
            'group' => $group
        ];

        if (!isset($this->grouped_tabs[$group])) {
            $this->grouped_tabs[$group] = [];
        }
        $this->grouped_tabs[$group][$id] = $this->tabs[$id];
    }

    public function renderHeader() {
        $has_woo = class_exists('WooCommerce');
        $current_page = isset($_GET['page']) ? sanitize_text_field($_GET['page']) : 'meteora-system';

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
            .mpe-main-nav { display: flex; background: #0f172a; padding: 0; user-select: none; flex-wrap: wrap; }
            .mpe-main-nav-item { padding: 15px 20px; cursor: pointer; font-weight: 600; font-size: 13px; color: #94a3b8; transition: 0.2s; display: flex; align-items: center; gap: 8px; text-decoration: none; border-top: 3px solid transparent;}
            .mpe-main-nav-item:hover { color: #fff; background: #1e293b; }
            .mpe-main-nav-item.active { color: #fff; background: #1e293b; border-top-color: var(--m-blue); }

            .mpe-sub-nav { display: none; background: #f8fafc; border-bottom: 1px solid #e2e8f0; padding: 0 10px; user-select: none; }
            .mpe-sub-nav.active { display: flex; }
            .mpe-nav-item { padding: 12px 18px; cursor: pointer; font-weight: 600; font-size: 13px; color: #64748b; border-bottom: 3px solid transparent; transition: 0.1s; display: flex; align-items: center; gap: 6px; text-decoration: none;}
            .mpe-nav-item:hover { background: #f1f5f9; color: var(--m-dark); }
            .mpe-nav-item.active { background: #fff; color: var(--m-blue); border-bottom-color: var(--m-blue); border-right: 1px solid #e2e8f0; border-left: 1px solid #e2e8f0; margin-bottom: -1px;}

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

            /* RESET UCG WRAPPERS per integrazione */
            .ucg-admin-app { margin: 0 !important; padding: 0 !important; background: transparent !important; }
            .ucg-admin-header { display: none !important; }
            .ucg-tabs { display: none !important; }
        </style>';

        echo '<div class="mpe-wrap">';

        // HEADER
        echo '<div class="mpe-header">
                <h1><span class="dashicons dashicons-superhero-alt"></span> Meteora System <span class="mpe-badge">V1.0.0</span></h1>
                <div style="font-size:11px; opacity:0.8; font-family:monospace;">SYSTEM ' . ($has_woo ? 'WOOCOMMERCE DETECTED' : 'STANDALONE MODE') . '</div>
              </div>';

        // NAVIGAZIONE MODULARE (Pagine Principali)

        // Definizione fissa dei gruppi principali per forzare l'ordine
        $main_groups = [
            'Dashboard' => 'dashicons-dashboard',
            'Prezzi' => 'dashicons-cart',
            'SEO' => 'dashicons-chart-line',
            'Contenuti' => 'dashicons-welcome-write-blog',
            'Fidelity & Coupon' => 'dashicons-tickets',
            'Marketing' => 'dashicons-megaphone',
            'Diagnostica' => 'dashicons-admin-tools',
            'Impostazioni' => 'dashicons-admin-settings'
        ];

        // Mappatura delle pagine separate (Fidelity)
        $fidelity_pages = [
            'ucg-admin' => ['title' => 'Coupon', 'icon' => 'dashicons-tickets'],
            'ucg-admin-events' => ['title' => 'Eventi', 'icon' => 'dashicons-calendar-alt'],
            'ucg-admin-marketing' => ['title' => 'Marketing & CRM', 'icon' => 'dashicons-email-alt2'],
            'ucg-admin-whatsapp' => ['title' => 'WhatsApp', 'icon' => 'dashicons-whatsapp'],
            'ucg-admin-settings' => ['title' => 'Log Errori', 'icon' => 'dashicons-list-view']
        ];

        // Determina il gruppo attivo
        $active_group = 'Dashboard'; // default
        if (array_key_exists($current_page, $fidelity_pages)) {
            $active_group = 'Fidelity & Coupon';
        }

        // Render Main Navigation
        echo '<div class="mpe-main-nav">';
        foreach ($main_groups as $group_name => $icon) {
            $group_id = sanitize_title($group_name);
            $is_active = ($active_group === $group_name) ? 'active' : '';

            // Fidelity group links directly to its first page, others link to meteora-system with hash
            if ($group_name === 'Fidelity & Coupon') {
                $link = esc_url(admin_url('admin.php?page=ucg-admin'));
                echo '<a href="' . $link . '" class="mpe-main-nav-item ' . $is_active . '" id="main-nav-'.esc_attr($group_id).'"><span class="dashicons ' . esc_attr($icon) . '"></span> ' . esc_html($group_name) . '</a>';
            } else {
                if ($current_page === 'meteora-system') {
                    echo '<div class="mpe-main-nav-item ' . $is_active . '" id="main-nav-'.esc_attr($group_id).'" onclick="openGroup(\'' . esc_attr($group_id) . '\')"><span class="dashicons ' . esc_attr($icon) . '"></span> ' . esc_html($group_name) . '</div>';
                } else {
                    echo '<a href="' . esc_url(admin_url('admin.php?page=meteora-system#group-' . $group_id)) . '" class="mpe-main-nav-item"><span class="dashicons ' . esc_attr($icon) . '"></span> ' . esc_html($group_name) . '</a>';
                }
            }
        }
        echo '</div>';

        // Render Sub Navigations (solo in pagina core, tranne per Fidelity)
        if ($current_page === 'meteora-system') {
            foreach ($main_groups as $group_name => $icon) {
                if ($group_name === 'Fidelity & Coupon') continue;

                $group_id = sanitize_title($group_name);
                $is_active_sub = ($active_group === $group_name) ? 'active' : '';

                echo '<div class="mpe-sub-nav ' . $is_active_sub . '" id="subnav-' . esc_attr($group_id) . '">';
                if (isset($this->grouped_tabs[$group_name])) {
                    foreach ($this->grouped_tabs[$group_name] as $id => $tab) {
                        echo '<div id="nav-' . esc_attr($id) . '" class="mpe-nav-item" onclick="openTab(\'' . esc_attr($id) . '\', \'' . esc_attr($group_id) . '\')"><span class="dashicons ' . esc_attr($tab['icon']) . '"></span> ' . esc_html($tab['title']) . '</div>';
                    }
                }
                echo '</div>';
            }
        } else {
            // Se siamo in una pagina Fidelity, mostriamo solo il subnav Fidelity
            if ($active_group === 'Fidelity & Coupon') {
                echo '<div class="mpe-sub-nav active">';
                foreach ($fidelity_pages as $slug => $tab_data) {
                    $is_active = ($current_page === $slug) ? 'active' : '';
                    echo '<a href="' . esc_url(admin_url('admin.php?page=' . $slug)) . '" class="mpe-nav-item ' . $is_active . '"><span class="dashicons ' . esc_attr($tab_data['icon']) . '"></span> ' . esc_html($tab_data['title']) . '</a>';
                }
                echo '</div>';
            }
        }

        echo '<div class="mpe-body">';
    }

    public function renderFooter() {
        echo '</div>'; // End Body
        echo '<div style="text-align:center; padding:20px; color:#94a3b8; font-size:11px;">Meteora SYSTEM | <span style="color:var(--m-green)">Status Operativo Assoluto</span></div>';
        echo '</div>'; // End Wrap
    }

    public function renderPage() {
        $this->renderHeader();

        $default_tab = '';
        foreach ($this->tabs as $id => $tab) {
            if ($tab['slug'] === 'meteora-system') {
                if (empty($default_tab)) $default_tab = $id;
            }
        }

        echo '<script>
            function openGroup(groupId) {
                localStorage.setItem("meteora_system_group", groupId);

                document.querySelectorAll(".mpe-main-nav-item").forEach(n => n.classList.remove("active"));
                let mainNav = document.getElementById("main-nav-"+groupId);
                if(mainNav) mainNav.classList.add("active");

                document.querySelectorAll(".mpe-sub-nav").forEach(n => n.classList.remove("active"));
                let subNav = document.getElementById("subnav-"+groupId);
                if(subNav) {
                    subNav.classList.add("active");
                    // Auto-apri la prima tab di questo gruppo se non c\'è nulla
                    let firstTab = subNav.querySelector(".mpe-nav-item");
                    if(firstTab) {
                        firstTab.click();
                    }
                }
            }

            function openTab(id, groupId) {
                localStorage.setItem("meteora_system_tab", id);
                if (groupId) {
                    localStorage.setItem("meteora_system_group", groupId);
                }

                document.querySelectorAll(".mpe-tab-content").forEach(t => t.classList.remove("active"));
                document.querySelectorAll(".mpe-nav-item").forEach(n => n.classList.remove("active"));

                const tabContent = document.getElementById(id);
                const tabNav = document.getElementById("nav-"+id);

                if(tabContent) tabContent.classList.add("active");
                if(tabNav) tabNav.classList.add("active");
            }

            document.addEventListener("DOMContentLoaded", () => {
                // Solo se siamo nella pagina main
                if (!document.querySelector(".mpe-tab-content")) return;

                let hash = window.location.hash;
                let g = localStorage.getItem("meteora_system_group") || "dashboard";
                let t = localStorage.getItem("meteora_system_tab") || "' . $default_tab . '";

                if (hash && hash.startsWith("#group-")) {
                    g = hash.replace("#group-", "");
                    t = ""; // reset tab
                }

                openGroup(g);

                if (t && document.getElementById(t)) {
                    openTab(t, g);
                }
            });
        </script>';

        foreach ($this->tabs as $id => $tab) {
            if ($tab['slug'] === 'meteora-system') {
                echo '<div id="' . esc_attr($id) . '" class="mpe-tab-content">';
                if (is_callable($tab['callback'])) {
                    call_user_func($tab['callback']);
                }
                echo '</div>';
            }
        }

        $this->renderFooter();
    }
}

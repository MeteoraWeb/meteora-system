<?php
namespace Meteora\Modules\CarrelliPersi;

use Meteora\Core\Menu\MenuManager;

class CartSaver {
    /**
     * @var CartSaver
     */
    private static $instance = null;

    /**
     * @return CartSaver
     */
    public static function instance() {
        if ( is_null( self::$instance ) ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        add_action('init', [$this, 'recoverCartLogic']);

        add_action('wp_enqueue_scripts', [$this, 'enqueueScripts']);
        add_action('wp_ajax_pcs_save_mail', [$this, 'saveMailCallback']);
        add_action('wp_ajax_nopriv_pcs_save_mail', [$this, 'saveMailCallback']);

        if ( ! wp_next_scheduled( 'pcs_cron_check_event' ) ) {
            wp_schedule_event( time(), 'hourly', 'pcs_cron_check_event' );
        }
        add_action( 'pcs_cron_check_event', [$this, 'dispatchEmails'] );

        add_action( 'woocommerce_order_status_completed', [$this, 'markComplete'] );
        add_action( 'woocommerce_order_status_processing', [$this, 'markComplete'] );

        add_filter( 'wp_mail_from_name', [$this, 'mailFromName'] );
        add_filter( 'wp_mail_from', [$this, 'mailFrom'] );

        // Admin Tab
        MenuManager::instance()->registerTab('tab-carrelli-persi', 'Carrelli Persi', 'dashicons-cart', [$this, 'renderAdminPage']);
    }

    public function enqueueScripts() {
        if (function_exists('is_cart') && is_cart()) {
            wp_enqueue_script('pcs-cart-js', plugins_url('pcs-script.js', __FILE__), array('jquery'), '1.3', true);
            wp_localize_script('pcs-cart-js', 'pcs_ajax', array(
                'url' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('pcs_save_mail_nonce')
            ));
        }
    }

    public function recoverCartLogic() {
        if (isset($_GET['recover_cart']) && !empty($_GET['recover_cart'])) {
            global $wpdb;
            $cart_id = intval($_GET['recover_cart']);
            $table_name = $wpdb->prefix . 'pcs_carts';

            $row = $wpdb->get_row($wpdb->prepare("SELECT cart_data FROM $table_name WHERE id = %d", $cart_id));

            if ($row) {
                $cart_items = json_decode($row->cart_data, true);
                if ( function_exists('WC') && WC()->cart ) {
                    WC()->cart->empty_cart();
                    foreach ($cart_items as $item) {
                        WC()->cart->add_to_cart($item['product_id'], $item['quantity']);
                    }
                    wp_safe_redirect(wc_get_cart_url());
                    die();
                }
            }
        }
    }

    public function renderAdminPage() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'pcs_carts';

        // LOGICA INVIO MANUALE (IL TUO BOTTONE)
        if (isset($_GET['action']) && $_GET['action'] == 'send_now' && isset($_GET['id']) && isset($_GET['_wpnonce']) && wp_verify_nonce($_GET['_wpnonce'], 'pcs_send_mail_' . $_GET['id'])) {
            if (current_user_can('manage_options')) {
                $cart_id = intval($_GET['id']);
                $cart = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_name WHERE id = %d", $cart_id));

                if ($cart) {
                    $this->sendCustomEmail($cart->email, $cart->id);
                    $wpdb->update($table_name,
                        array('status' => 'mail1_sent', 'last_mail_sent' => current_time('mysql')),
                        array('id' => $cart_id)
                    );
                    echo '<div class="updated"><p>Mail di recupero inviata a ' . esc_html($cart->email) . '! Link di ripristino generato.</p></div>';
                }
            }
        }

        $results = $wpdb->get_results("SELECT * FROM $table_name ORDER BY created_at DESC");

        echo '<div class="mpe-card">
                <h3>📦 Carrelli Abbandonati - Patricia Oro</h3>
                <table class="mpe-table widefat fixed striped">
                <thead>
                    <tr>
                        <th style="width:15%">Data</th>
                        <th style="width:20%">Email</th>
                        <th style="width:30%">Prodotti</th>
                        <th style="width:15%">Valore Totale</th>
                        <th style="width:20%">Stato / Azioni</th>
                    </tr>
                </thead>
                <tbody>';

        if ($results) {
            foreach ($results as $row) {
                $cart_items = json_decode($row->cart_data, true);
                $total_value = 0;
                $products_list = [];

                if (is_array($cart_items) && function_exists('wc_get_product')) {
                    foreach ($cart_items as $item) {
                        $product = wc_get_product($item['product_id']);
                        if ($product) {
                            $qty = $item['quantity'];
                            $total_value += (float)$product->get_price() * $qty;
                            $products_list[] = $product->get_name() . " (x$qty)";
                        }
                    }
                }

                echo "<tr>
                        <td>{$row->created_at}</td>
                        <td><strong>{$row->email}</strong></td>
                        <td>" . implode(', ', $products_list) . "</td>
                        <td>" . (function_exists('wc_price') ? wc_price($total_value) : $total_value) . "</td>
                        <td>
                            <mark style='background:#eee;padding:5px;border-radius:4px;display:inline-block;margin-bottom:5px;'>{$row->status}</mark>";

                // BOTTONE SEMPRE VISIBILE PER TEST (O SOLO PENDING SE PREFERISCI)
                $send_url = wp_nonce_url('?page=meteora-system&action=send_now&id=' . $row->id, 'pcs_send_mail_' . $row->id);
                echo "<br><a href='".esc_url($send_url)."' class='button button-primary'>Invia Ora</a>";

                echo "</td></tr>";
            }
        } else {
            echo '<tr><td colspan="5">Nessun carrello intercettato per ora.</td></tr>';
        }
        echo '</tbody></table></div>';
    }

    public function saveMailCallback() {
        check_ajax_referer('pcs_save_mail_nonce', 'nonce');
        global $wpdb;
        $email = sanitize_email($_POST['email']);
        if (is_email($email) && function_exists('WC') && WC()->cart) {
            $table_name = $wpdb->prefix . 'pcs_carts';
            $cart_contents = WC()->cart->get_cart();
            $cart_data = [];
            foreach ($cart_contents as $item) {
                $cart_data[] = ['product_id' => $item['product_id'], 'quantity' => $item['quantity']];
            }
            $wpdb->replace($table_name, array(
                'email' => $email,
                'cart_data' => json_encode($cart_data),
                'status' => 'pending',
                'created_at' => current_time('mysql')
            ));
            wp_send_json_success();
        }
        wp_send_json_error();
    }

    public function dispatchEmails() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'pcs_carts';
        $now = current_time( 'mysql' );
        $current_hour = date('H');

        // Mail 1: dopo 4 ore
        $pending = $wpdb->get_results( "SELECT * FROM $table_name WHERE status = 'pending' AND created_at <= DATE_SUB('$now', INTERVAL 4 HOUR)" );
        foreach ( $pending as $cart ) {
            $this->sendCustomEmail( $cart->email, $cart->id );
            $wpdb->update($table_name, array('status' => 'mail1_sent', 'last_mail_sent' => $now), array('id' => $cart->id));
        }

        // Mail 2: giorno dopo ore 15:00
        if ($current_hour == 15) {
            $follow_up = $wpdb->get_results( "SELECT * FROM $table_name WHERE status = 'mail1_sent' AND last_mail_sent <= DATE_SUB('$now', INTERVAL 18 HOUR)" );
            foreach ($follow_up as $cart) {
                $this->sendCustomEmail($cart->email, $cart->id, true);
                $wpdb->update($table_name, array('status' => 'mail2_sent'), array('id' => $cart->id));
            }
        }
    }

    private function sendCustomEmail( $to, $cart_db_id, $is_second = false ) {
        $subject = $is_second ? "Ultima chiamata: il tuo carrello Patricia Oro ti aspetta!" : "Ti abbiamo tenuto da parte il tuo carrello";
        $headers = array('Content-Type: text/html; charset=UTF-8');

        // LINK MAGICO DI RICOSTRUZIONE
        $recovery_url = add_query_arg('recover_cart', $cart_db_id, home_url());

        $testo_principale = $is_second
            ? "Le nostre creazioni sono limitate e i gioielli che hai scelto potrebbero andare esauriti presto. Non lasciarteli sfuggire!"
            : "Abbiamo notato che hai lasciato dei gioielli preziosi nel carrello. Non lasciarteli scappare!";

        ob_start(); ?>
        <div style="font-family: 'Helvetica', Arial, sans-serif; max-width: 600px; margin: auto; border: 2px solid #D4AF37; padding: 30px; border-radius: 15px; text-align: center;">

            <h1 style="color: #333; text-transform: uppercase; letter-spacing: 3px; margin-bottom: 10px;">Patricia Oro</h1>

            <p style="font-size: 16px; color: #555; line-height: 1.6; margin-bottom: 25px;">
                <?php echo $testo_principale; ?>
            </p>

            <div style="background-color: #fcfcfc; border: 1px solid #eee; padding: 20px; border-radius: 10px; margin-bottom: 30px;">
                <p style="font-size: 15px; font-weight: bold; color: #333; margin-top: 0;">Serve aiuto con l'ordine?</p>

                <a href="https://wa.me/393312532090" style="background-color: #25D366; color: #ffffff !important; padding: 12px 20px; text-decoration: none; border-radius: 8px; font-weight: bold; display: inline-block; margin: 5px; font-size: 14px;">
                    SCRIVICI SU WHATSAPP
                </a>

                <p style="font-size: 14px; color: #777; margin: 10px 0;">Oppure preferisci parlare con noi?</p>

                <a href="tel:+393312532090" style="color: #007AFF; font-weight: bold; text-decoration: none; font-size: 16px;">
                    ☎ CHIAMA ORA: +39 331 253 2090
                </a>
            </div>

            <hr style="border: 0; border-top: 1px solid #eee; margin: 30px 0;">

            <p style="font-size: 14px; color: #777; margin-bottom: 15px;">Clicca qui sotto per completare il tuo acquisto:</p>
            <div style="margin-bottom: 20px;">
                <a href="<?php echo esc_url($recovery_url); ?>" style="background: linear-gradient(135deg, #D4AF37 0%, #B8860B 100%); color: #ffffff !important; padding: 18px 35px; text-decoration: none; border-radius: 50px; font-weight: bold; font-size: 16px; box-shadow: 0 4px 15px rgba(212, 175, 55, 0.4); display: inline-block; text-transform: uppercase;">
                    Completa l'ordine adesso
                </a>
            </div>

            <p style="font-size: 12px; color: #aaa; margin-top: 40px; border-top: 1px solid #eee; padding-top: 20px;">
                © gioielleriapatriciaoro
            </p>
        </div>
        <?php
        $message = ob_get_clean();
        wp_mail( $to, $subject, $message, $headers );
    }

    public function markComplete( $order_id ) {
        global $wpdb;
        if(function_exists('wc_get_order')) {
            $order = wc_get_order( $order_id );
            if($order) {
                $email = $order->get_billing_email();
                $wpdb->update( $wpdb->prefix . 'pcs_carts', array( 'status' => 'completed' ), array( 'email' => $email ) );
            }
        }
    }

    public function mailFromName() { return 'Patricia Oro'; }
    public function mailFrom() { return 'info@gioielleriapatriciaoro.it'; }
}

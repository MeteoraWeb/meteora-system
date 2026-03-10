<?php
/**
 * Plugin Name: Patricia Cart Saver
 * Description: Recupero carrelli abbandonati mobile-first. Cattura la mail, ricostruisce il carrello e invia promemoria automatici e manuali.
 * Version: 1.3
 * Author: Meteora Web
 */

if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * 1. INSTALLAZIONE DATABASE
 */
register_activation_hook( __FILE__, 'pcs_install' );
function pcs_install() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'pcs_carts';
    $charset_collate = $wpdb->get_charset_collate();

    $sql = "CREATE TABLE $table_name (
        id bigint(20) NOT NULL AUTO_INCREMENT,
        email varchar(100) NOT NULL,
        cart_data longtext NOT NULL,
        status varchar(20) DEFAULT 'pending',
        created_at datetime DEFAULT CURRENT_TIMESTAMP,
        last_mail_sent datetime DEFAULT NULL,
        PRIMARY KEY  (id),
        UNIQUE KEY email (email)
    ) $charset_collate;";

    require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
    dbDelta( $sql );
}

/**
 * 2. LOGICA DI RICOSTRUZIONE CARRELLO
 * Ricostruisce il carrello dall'ID salvato nel link della mail.
 */
add_action('init', 'pcs_recover_cart_logic');
function pcs_recover_cart_logic() {
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
                exit;
            }
        }
    }
}

/**
 * 3. DASHBOARD ADMIN
 */
add_action('admin_menu', 'pcs_admin_menu');
function pcs_admin_menu() {
    add_menu_page('Patricia Cart Saver', 'Carrelli Persi', 'manage_options', 'patricia-cart-saver', 'pcs_render_admin_page', 'dashicons-cart', 56);
}

function pcs_render_admin_page() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'pcs_carts';

    // LOGICA INVIO MANUALE (IL TUO BOTTONE)
    if (isset($_GET['action']) && $_GET['action'] == 'send_now' && isset($_GET['id'])) {
        $cart_id = intval($_GET['id']);
        $cart = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_name WHERE id = %d", $cart_id));

        if ($cart) {
            pcs_send_custom_email($cart->email, $cart->id);
            $wpdb->update($table_name,
                array('status' => 'mail1_sent', 'last_mail_sent' => current_time('mysql')),
                array('id' => $cart_id)
            );
            echo '<div class="updated"><p>Mail di recupero inviata a ' . esc_html($cart->email) . '! Link di ripristino generato.</p></div>';
        }
    }

    $results = $wpdb->get_results("SELECT * FROM $table_name ORDER BY created_at DESC");

    echo '<div class="wrap"><h1>📦 Carrelli Abbandonati - Patricia Oro</h1>';
    echo '<table class="wp-list-table widefat fixed striped">
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

            if (is_array($cart_items)) {
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
                    <td>" . wc_price($total_value) . "</td>
                    <td>
                        <mark style='background:#eee;padding:5px;border-radius:4px;display:inline-block;margin-bottom:5px;'>{$row->status}</mark>";

            // BOTTONE SEMPRE VISIBILE PER TEST (O SOLO PENDING SE PREFERISCI)
            echo "<br><a href='?page=patricia-cart-saver&action=send_now&id={$row->id}' class='button button-primary'>Invia Ora</a>";

            echo "</td></tr>";
        }
    } else {
        echo '<tr><td colspan="5">Nessun carrello intercettato per ora.</td></tr>';
    }
    echo '</tbody></table></div>';
}

/**
 * 4. CATTURA AJAX
 */
add_action('wp_enqueue_scripts', 'pcs_enqueue_scripts');
function pcs_enqueue_scripts() {
    if (is_cart()) {
        wp_enqueue_script('pcs-cart-js', plugin_dir_url(__FILE__) . 'pcs-script.js', array('jquery'), '1.3', true);
        wp_localize_script('pcs-cart-js', 'pcs_ajax', array('url' => admin_url('admin-ajax.php')));
    }
}

add_action('wp_ajax_pcs_save_mail', 'pcs_save_mail_callback');
add_action('wp_ajax_nopriv_pcs_save_mail', 'pcs_save_mail_callback');
function pcs_save_mail_callback() {
    global $wpdb;
    $email = sanitize_email($_POST['email']);
    if (is_email($email)) {
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

/**
 * 5. AUTOMAZIONE INVIO (CRON) E TEMPLATE EMAIL (ORO LUSSO)
 */
if ( ! wp_next_scheduled( 'pcs_cron_check_event' ) ) {
    wp_schedule_event( time(), 'hourly', 'pcs_cron_check_event' );
}
add_action( 'pcs_cron_check_event', 'pcs_dispatch_emails' );

function pcs_dispatch_emails() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'pcs_carts';
    $now = current_time( 'mysql' );
    $current_hour = date('H');

    // Mail 1: dopo 4 ore
    $pending = $wpdb->get_results( "SELECT * FROM $table_name WHERE status = 'pending' AND created_at <= DATE_SUB('$now', INTERVAL 4 HOUR)" );
    foreach ( $pending as $cart ) {
        pcs_send_custom_email( $cart->email, $cart->id );
        $wpdb->update($table_name, array('status' => 'mail1_sent', 'last_mail_sent' => $now), array('id' => $cart->id));
    }

    // Mail 2: giorno dopo ore 15:00
    if ($current_hour == 15) {
        $follow_up = $wpdb->get_results( "SELECT * FROM $table_name WHERE status = 'mail1_sent' AND last_mail_sent <= DATE_SUB('$now', INTERVAL 18 HOUR)" );
        foreach ($follow_up as $cart) {
            pcs_send_custom_email($cart->email, $cart->id, true);
            $wpdb->update($table_name, array('status' => 'mail2_sent'), array('id' => $cart->id));
        }
    }
}

function pcs_send_custom_email( $to, $cart_db_id, $is_second = false ) {
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

/**
 * 6. STOP SE ACQUISTA & IMPOSTAZIONI MITTENTE
 */
add_action( 'woocommerce_order_status_completed', 'pcs_mark_complete' );
add_action( 'woocommerce_order_status_processing', 'pcs_mark_complete' );
function pcs_mark_complete( $order_id ) {
    global $wpdb;
    $order = wc_get_order( $order_id );
    if($order) {
        $email = $order->get_billing_email();
        $wpdb->update( $wpdb->prefix . 'pcs_carts', array( 'status' => 'completed' ), array( 'email' => $email ) );
    }
}

add_filter( 'wp_mail_from_name', function() { return 'Patricia Oro'; });
add_filter( 'wp_mail_from', function() { return 'info@gioielleriapatriciaoro.it'; });
<?php

/**
 * Author: Meteora Web <https://meteoraweb.com>
 */


// Impedisce l'accesso diretto ai file
if (!defined('ABSPATH')) {
    exit;
}

function ucg_render_coupon_sets_table() {
    $coupon_sets = get_option('mms_coupon_sets', array());

    ?>
    <h2><?php esc_html_e('Set di Coupon', 'unique-coupon-generator'); ?></h2>
    <table class="wp-list-table widefat fixed striped ucg-table ucg-set-table">
        <thead>
            <tr>
                <th><?php esc_html_e('Nome Set', 'unique-coupon-generator'); ?></th>
                <th><?php esc_html_e('Shortcode', 'unique-coupon-generator'); ?></th>
                <th><?php esc_html_e('Azioni', 'unique-coupon-generator'); ?></th>
            </tr>
        </thead>
        <tbody>
        <?php if (!empty($coupon_sets)) : ?>
            <?php foreach ($coupon_sets as $set_key => $set) :
                $set_name  = $set['name'] ?? $set_key;
                $shortcode = $set['shortcode'] ?? '';
                $set_page  = plugin_get_page_by_title('Richiedi ' . $set_name);
                $set_url   = $set_page ? get_permalink($set_page) : site_url('/richiedi-' . sanitize_title($set_name));
                $delete_url = wp_nonce_url(
                    add_query_arg(
                        array(
                            'page'              => 'ucc-visualizza-coupon',
                            'delete_coupon_set' => $set_key,
                        ),
                        admin_url('admin.php')
                    ),
                    'ucc_delete_coupon_set_nonce'
                );
                ?>
                <tr>
                    <td><?php echo esc_html($set_name); ?></td>
                    <td><code><?php echo esc_html($shortcode); ?></code></td>
                    <td class="ucg-table__actions">
                        <button type="button" class="button ucg-btn ucg-btn-secondary copy-url-button" data-url="<?php echo esc_url($set_url); ?>">
                            <?php esc_html_e('Copia URL', 'unique-coupon-generator'); ?>
                        </button>
                        <a class="button ucg-btn ucg-btn-danger" href="<?php echo esc_url($delete_url); ?>" onclick="return confirm('<?php echo esc_js(__('Sei sicuro di voler eliminare questo set?', 'unique-coupon-generator')); ?>');">
                            <?php esc_html_e('Elimina', 'unique-coupon-generator'); ?>
                        </a>
                    </td>
                </tr>
            <?php endforeach; ?>
        <?php else : ?>
            <tr>
                <td colspan="3"><?php esc_html_e('Nessun set di coupon disponibile.', 'unique-coupon-generator'); ?></td>
            </tr>
        <?php endif; ?>
        </tbody>
    </table>
    <?php
}

function ucc_display_coupon_table(array $coupon_rows) {
    ?>
    <h2><?php esc_html_e('Coupon Esistenti', 'unique-coupon-generator'); ?></h2>
    <table class="wp-list-table widefat fixed striped ucg-table ucg-coupons-table">
        <thead>
            <tr>
                <th><?php esc_html_e('Codice Coupon', 'unique-coupon-generator'); ?></th>
                <th><?php esc_html_e('Nome', 'unique-coupon-generator'); ?></th>
                <th><?php esc_html_e('Cognome', 'unique-coupon-generator'); ?></th>
                <th><?php esc_html_e('Email', 'unique-coupon-generator'); ?></th>
                <th><?php esc_html_e('Telefono', 'unique-coupon-generator'); ?></th>
                <th><?php esc_html_e('Città', 'unique-coupon-generator'); ?></th>
                <th><?php esc_html_e('Data di Nascita', 'unique-coupon-generator'); ?></th>
                <th><?php esc_html_e('Sconto', 'unique-coupon-generator'); ?></th>
                <th><?php esc_html_e('Tipo di Sconto', 'unique-coupon-generator'); ?></th>
                <th><?php esc_html_e('Data di Scadenza', 'unique-coupon-generator'); ?></th>
                <th><?php esc_html_e('Data di Scaricamento', 'unique-coupon-generator'); ?></th>
                <th><?php esc_html_e('Usato', 'unique-coupon-generator'); ?></th>
                <th><?php esc_html_e('QR Code', 'unique-coupon-generator'); ?></th>
                <th><?php esc_html_e('Azioni', 'unique-coupon-generator'); ?></th>
            </tr>
        </thead>
        <tbody>
        <?php if (!empty($coupon_rows)) : ?>
            <?php foreach ($coupon_rows as $row) : ?>
                <tr>
                    <td><?php echo esc_html($row['code']); ?></td>
                    <td><?php echo esc_html($row['first_name'] !== '' ? $row['first_name'] : __('N/D', 'unique-coupon-generator')); ?></td>
                    <td><?php echo esc_html($row['last_name'] !== '' ? $row['last_name'] : __('N/D', 'unique-coupon-generator')); ?></td>
                    <td><?php echo esc_html($row['user_email']); ?></td>
                    <td><?php echo esc_html(ucg_coupon_phone_label($row['phone'])); ?></td>
                    <td><?php echo esc_html($row['city'] !== '' ? $row['city'] : __('N/D', 'unique-coupon-generator')); ?></td>
                    <td><?php echo esc_html(ucg_coupon_birthdate_label($row['birth_date'])); ?></td>
                    <td><?php echo esc_html($row['amount']); ?></td>
                    <td><?php echo esc_html(ucg_coupon_discount_label($row['discount_type'])); ?></td>
                    <td><?php echo esc_html(ucg_coupon_expiry_label($row['expiry_date'])); ?></td>
                    <td><?php echo esc_html(ucg_coupon_download_label($row['download_date'])); ?></td>
                    <td><?php echo esc_html(ucg_coupon_used_label($row['used'])); ?></td>
                    <td>
                        <?php if (!empty($row['qr_code_url'])) : ?>
                            <a href="<?php echo esc_url($row['qr_code_url']); ?>" class="ucg-btn ucg-btn-secondary" download>
                                <?php esc_html_e('Scarica QR', 'unique-coupon-generator'); ?>
                            </a>
                        <?php else : ?>
                            <?php esc_html_e('Nessun QR disponibile', 'unique-coupon-generator'); ?>
                        <?php endif; ?>
                    </td>
                    <td>
                        <form method="post" action="">
                            <?php wp_nonce_field('ucc_delete_coupon_nonce'); ?>
                            <input type="hidden" name="delete_coupon" value="<?php echo esc_attr($row['id']); ?>">
                            <button type="submit" class="ucg-btn ucg-btn-small ucg-btn-danger" onclick="return confirm('<?php echo esc_js(__('Sei sicuro di voler eliminare questo coupon?', 'unique-coupon-generator')); ?>');">
                                <?php esc_html_e('Elimina', 'unique-coupon-generator'); ?>
                            </button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
        <?php else : ?>
            <tr>
                <td colspan="14"><?php esc_html_e('Nessun coupon trovato per i filtri selezionati.', 'unique-coupon-generator'); ?></td>
            </tr>
        <?php endif; ?>
        </tbody>
    </table>
    <?php

    ucg_render_coupon_sets_table();
}

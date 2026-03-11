<?php

/**
 * Author: Meteora Web <https://meteoraweb.com>
 */

// Impedisce l'accesso diretto ai file
if (!defined('ABSPATH')) {
    exit;
}

require_once __DIR__ . '/class-coupon-generator.php';
require_once __DIR__ . '/class-coupon-notifier.php';
require_once __DIR__ . '/class-coupon-validator.php';

if (!function_exists('ucg_find_coupon_post')) {
    function ucg_find_coupon_post($coupon_code) {
        $code = trim((string) $coupon_code);
        if ($code === '') {
            return null;
        }

        $normalized = $code;
        if (function_exists('wc_format_coupon_code')) {
            $normalized = wc_format_coupon_code($code);
        }

        $coupon_id = 0;
        if (function_exists('wc_get_coupon_id_by_code')) {
            $coupon_id = wc_get_coupon_id_by_code($normalized);
        }

        if ($coupon_id) {
            return get_post($coupon_id);
        }

        $coupon = plugin_get_page_by_title($code, OBJECT, 'shop_coupon');
        if ($coupon) {
            return $coupon;
        }

        if ($normalized !== $code) {
            $coupon = plugin_get_page_by_title($normalized, OBJECT, 'shop_coupon');
            if ($coupon) {
                return $coupon;
            }
        }

        return null;
    }
}

if (!function_exists('ucg_prepare_coupon_details')) {
    function ucg_prepare_coupon_details($coupon_post) {
        if (!$coupon_post || empty($coupon_post->ID)) {
            return array();
        }

        $coupon_id = (int) $coupon_post->ID;
        $amount = get_post_meta($coupon_id, 'coupon_amount', true);
        $discount_type = get_post_meta($coupon_id, 'discount_type', true);
        $used = get_post_meta($coupon_id, 'used', true) === 'yes';
        $expiry_meta = get_post_meta($coupon_id, 'expiry_date', true);
        $date_expires_meta = get_post_meta($coupon_id, 'date_expires', true);

        $expiry_timestamp = null;
        if (!empty($expiry_meta)) {
            $maybe_timestamp = strtotime($expiry_meta . ' 23:59:59');
            if ($maybe_timestamp) {
                $expiry_timestamp = $maybe_timestamp;
            }
        } elseif (!empty($date_expires_meta)) {
            $maybe_timestamp = intval($date_expires_meta);
            if ($maybe_timestamp > 0) {
                $expiry_timestamp = $maybe_timestamp;
            }
        }

        $expiry_label = '';
        if ($expiry_timestamp) {
            $expiry_label = date_i18n(get_option('date_format'), $expiry_timestamp);
        }

        $discount_label = $discount_type;
        if (function_exists('wc_get_coupon_types')) {
            $types = wc_get_coupon_types();
            if (isset($types[$discount_type])) {
                $discount_label = $types[$discount_type];
            }
        }

        return array(
            'id'             => $coupon_id,
            'code'           => $coupon_post->post_title,
            'amount'         => $amount,
            'discount_type'  => $discount_type,
            'discount_label' => $discount_label,
            'used'           => $used,
            'expiry_label'   => $expiry_label,
            'expiry_ts'      => $expiry_timestamp,
            'is_expired'     => $expiry_timestamp ? $expiry_timestamp < current_time('timestamp') : false,
        );
    }
}

// Funzione per verificare e segnare il coupon come utilizzato
function verifica_coupon() {
    $denied_message = '<p>' . esc_html__('Licenza non valida.', 'unique-coupon-generator') . '</p>';
    $blocked = ucg_block_when_forbidden('front_verify', $denied_message);
    if ($blocked !== null) {
        return $blocked;
    }

    $notices = array();
    $coupon_summary = null;
    $current_code = '';

    $build_summary = static function ($details) {
        if (empty($details) || empty($details['code'])) {
            return null;
        }

        $raw_amount = $details['amount'];
        $amount_output = '';
        $amount_allows_html = false;

        if ($details['discount_type'] === 'percent' && $raw_amount !== '') {
            $amount_output = rtrim(rtrim((string) $raw_amount, '0'), '.') . '%';
        } elseif ($raw_amount !== '' && is_numeric($raw_amount) && function_exists('wc_price')) {
            $amount_output = wc_price((float) $raw_amount);
            $amount_allows_html = true;
        } elseif ($raw_amount !== '') {
            $amount_output = (string) $raw_amount;
        }

        $status = 'valid';
        if (!empty($details['used'])) {
            $status = 'used';
        } elseif (!empty($details['is_expired'])) {
            $status = 'expired';
        }

        $status_map = array(
            'valid'   => array(
                'label' => esc_html__('Valido', 'unique-coupon-generator'),
                'badge' => 'valid',
            ),
            'used'    => array(
                'label' => esc_html__('Utilizzato', 'unique-coupon-generator'),
                'badge' => 'used',
            ),
            'expired' => array(
                'label' => esc_html__('Scaduto', 'unique-coupon-generator'),
                'badge' => 'expired',
            ),
        );

        $status_meta = isset($status_map[$status]) ? $status_map[$status] : $status_map['valid'];

        $expiry_label = !empty($details['expiry_label'])
            ? $details['expiry_label']
            : esc_html__('Nessuna data di scadenza', 'unique-coupon-generator');

        $discount_label = !empty($details['discount_label'])
            ? $details['discount_label']
            : $details['discount_type'];

        return array(
            'code'                => $details['code'],
            'status'              => $status,
            'status_label'        => $status_meta['label'],
            'status_badge_class'  => $status_meta['badge'],
            'amount_output'       => $amount_output,
            'amount_allows_html'  => $amount_allows_html,
            'discount_label'      => $discount_label,
            'expiry_label'        => $expiry_label,
            'is_used'             => !empty($details['used']),
            'is_expired'          => !empty($details['is_expired']),
            'show_mark_form'      => empty($details['used']) && empty($details['is_expired']),
        );
    };

    if (isset($_POST['mark_as_used'])) {
        $current_code = sanitize_text_field(wp_unslash($_POST['coupon_code'] ?? ''));
        if ($current_code === '') {
            $notices[] = array(
                'type'    => 'error',
                'message' => esc_html__('Per favore, inserisci un codice coupon.', 'unique-coupon-generator'),
            );
        } else {
            $coupon_post = ucg_find_coupon_post($current_code);
            if ($coupon_post && !empty($coupon_post->ID)) {
                update_post_meta($coupon_post->ID, 'used', 'yes');
                if (class_exists('UCG_FidelityManager')) {
                    $email = get_post_meta($coupon_post->ID, 'customer_email', true);
                    $user = $email ? get_user_by('email', $email) : false;
                    if ($user) {
                        UCG_FidelityManager::add_points($user->ID, '', 2, 'aggiunta', 'verifica');
                    }
                }

                $details = ucg_prepare_coupon_details($coupon_post);
                $coupon_summary = $build_summary($details);

                $notices[] = array(
                    'type'    => 'success',
                    'message' => esc_html__('Il coupon è stato segnato come utilizzato.', 'unique-coupon-generator'),
                );
            } else {
                $notices[] = array(
                    'type'    => 'error',
                    'message' => esc_html__('Il coupon non è valido.', 'unique-coupon-generator'),
                );
            }
        }
    }

    if (isset($_POST['verifica_coupon'])) {
        $current_code = sanitize_text_field(wp_unslash($_POST['coupon_code'] ?? ''));
        if ($current_code === '') {
            $notices[] = array(
                'type'    => 'error',
                'message' => esc_html__('Per favore, inserisci un codice coupon.', 'unique-coupon-generator'),
            );
        } else {
            $coupon_post = ucg_find_coupon_post($current_code);
            if ($coupon_post) {
                $details = ucg_prepare_coupon_details($coupon_post);
                $coupon_summary = $build_summary($details);

                if (!$coupon_summary) {
                    $notices[] = array(
                        'type'    => 'error',
                        'message' => esc_html__('Il coupon non è valido.', 'unique-coupon-generator'),
                    );
                } elseif ($coupon_summary['status'] === 'used') {
                    $notices[] = array(
                        'type'    => 'warning',
                        'message' => esc_html__('Il coupon è già stato utilizzato.', 'unique-coupon-generator'),
                    );
                } elseif ($coupon_summary['status'] === 'expired') {
                    $notices[] = array(
                        'type'    => 'error',
                        'message' => esc_html__('Il coupon è scaduto.', 'unique-coupon-generator'),
                    );
                } else {
                    $notices[] = array(
                        'type'    => 'success',
                        'message' => esc_html__('Il coupon è valido.', 'unique-coupon-generator'),
                    );
                }
            } else {
                $notices[] = array(
                    'type'    => 'error',
                    'message' => esc_html__('Il coupon non è valido.', 'unique-coupon-generator'),
                );
            }
        }
    }

    $html5_qr_src = UCG_PLUGIN_URL . 'assets/js/html5-qrcode.min.js';

    ob_start();
    ?>
    <div class="ucg-coupon-verify">
        <div class="ucg-coupon-card">
            <?php foreach ($notices as $notice) :
                $type = isset($notice['type']) ? $notice['type'] : 'info';
                $message = isset($notice['message']) ? $notice['message'] : '';
                if ($message === '') {
                    continue;
                }
                ?>
                <div class="ucg-coupon-notice ucg-coupon-notice--<?php echo esc_attr($type); ?>">
                    <p><?php echo esc_html($message); ?></p>
                </div>
            <?php endforeach; ?>

            <form method="post" action="" id="ucg-coupon-verify-form" class="ucg-coupon-verify-form">
                <label for="ucg-coupon-code"><?php echo esc_html__('Inserisci il codice del coupon', 'unique-coupon-generator'); ?></label>
                <input type="text" name="coupon_code" id="ucg-coupon-code" value="<?php echo esc_attr($current_code); ?>" required>
                <button type="submit" name="verifica_coupon" value="1" class="ucg-submit ucg-submit--primary">
                    <?php echo esc_html__('Verifica coupon', 'unique-coupon-generator'); ?>
                </button>
            </form>

            <div class="ucg-coupon-qr-wrapper">
                <div id="ucg-coupon-qr-reader" class="ucg-coupon-qr-reader"></div>
                <p class="ucg-coupon-qr-hint"><?php echo esc_html__('Inquadra il QR code del coupon per compilare automaticamente il campo.', 'unique-coupon-generator'); ?></p>
            </div>
        </div>

        <?php if ($coupon_summary) : ?>
            <div class="ucg-coupon-summary-card">
                <div class="ucg-coupon-summary-header">
                    <div class="ucg-coupon-summary-code">
                        <span class="ucg-coupon-summary-label"><?php echo esc_html__('Codice coupon', 'unique-coupon-generator'); ?></span>
                        <span class="ucg-coupon-summary-value"><?php echo esc_html($coupon_summary['code']); ?></span>
                    </div>
                    <span class="ucg-coupon-badge ucg-coupon-badge--<?php echo esc_attr($coupon_summary['status_badge_class']); ?>">
                        <?php echo esc_html($coupon_summary['status_label']); ?>
                    </span>
                </div>

                <ul class="ucg-coupon-summary-list">
                    <?php if ($coupon_summary['amount_output'] !== '') : ?>
                        <li>
                            <span class="label"><?php echo esc_html__('Sconto', 'unique-coupon-generator'); ?></span>
                            <span class="value">
                                <?php
                                if ($coupon_summary['amount_allows_html']) {
                                    echo wp_kses_post($coupon_summary['amount_output']);
                                } else {
                                    echo esc_html($coupon_summary['amount_output']);
                                }
                                ?>
                            </span>
                        </li>
                    <?php endif; ?>
                    <li>
                        <span class="label"><?php echo esc_html__('Tipo di sconto', 'unique-coupon-generator'); ?></span>
                        <span class="value"><?php echo esc_html($coupon_summary['discount_label']); ?></span>
                    </li>
                    <li>
                        <span class="label"><?php echo esc_html__('Data di scadenza', 'unique-coupon-generator'); ?></span>
                        <span class="value"><?php echo esc_html($coupon_summary['expiry_label']); ?></span>
                    </li>
                </ul>

                <?php if (!empty($coupon_summary['show_mark_form'])) : ?>
                    <form method="post" class="ucg-coupon-actions">
                        <input type="hidden" name="coupon_code" value="<?php echo esc_attr($coupon_summary['code']); ?>">
                        <button type="submit" name="mark_as_used" value="1" class="ucg-submit ucg-submit--secondary">
                            <?php echo esc_html__('Segna come utilizzato', 'unique-coupon-generator'); ?>
                        </button>
                    </form>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <div id="coupon-verification-results" class="ucg-coupon-live" aria-live="polite"></div>
    </div>

    <script src="<?php echo esc_url($html5_qr_src); ?>"></script>
    <script>
        (function() {
            if (typeof Html5QrcodeScanner === 'undefined') {
                return;
            }

            var qrContainer = document.getElementById('ucg-coupon-qr-reader');
            if (!qrContainer) {
                return;
            }

            var qrWrapper = qrContainer.closest('.ucg-coupon-qr-wrapper');
            var form = document.getElementById('ucg-coupon-verify-form');
            var input = form ? form.querySelector('input[name="coupon_code"]') : null;
            var submitButton = form ? form.querySelector('button[name="verifica_coupon"]') : null;

            function handleScan(decodedText) {
                var value = decodedText;
                try {
                    var parsed = new URL(decodedText);
                    var param = parsed.searchParams.get('coupon_code');
                    if (param) {
                        value = param;
                    }
                } catch (err) {}

                if (input) {
                    input.value = value;
                }

                if (qrWrapper) {
                    qrWrapper.classList.remove('is-active');
                }

                if (submitButton) {
                    submitButton.click();
                } else if (form) {
                    form.submit();
                }
            }

            var scanner = new Html5QrcodeScanner('ucg-coupon-qr-reader', { fps: 10, qrbox: 200 });
            if (qrWrapper) {
                qrWrapper.classList.add('is-active');
            }
            scanner.render(handleScan, function() {});
        })();
    </script>
    <?php

    return ob_get_clean();
}

// Shortcode per verificare un coupon
function verifica_coupon_shortcode() {
    return verifica_coupon();
}
add_shortcode('verifica_coupon', 'verifica_coupon_shortcode');

<?php

/**
 * Author: Meteora Web <https://meteoraweb.com>
 */


// Impedisce l'accesso diretto ai file
if (!defined('ABSPATH')) {
    exit;
}

// Funzione per includere la libreria PHP QR Code
function ucc_include_qr_code_library() {
    if (!class_exists('QRcode')) {
        require_once plugin_dir_path(__FILE__) . '/../lib/phpqrcode/qrlib.php';
    }
}

if (!function_exists('ucg_qr_build_png_chunk')) {
    /**
     * Crea un chunk PNG valido partendo dal tipo e dal payload.
     *
     * @param string $type Tipo di chunk (es. IHDR, IDAT, IEND).
     * @param string $data Dati binari del chunk.
     * @return string      Chunk PNG completo di lunghezza e CRC.
     */
    function ucg_qr_build_png_chunk($type, $data) {
        $length = strlen($data);
        return pack('N', $length) . $type . $data . pack('N', crc32($type . $data));
    }
}

if (!function_exists('ucg_qr_matrix_to_png')) {
    /**
     * Converte la matrice del QR in un'immagine PNG greyscale (8bit) senza usare GD.
     *
     * @param array $matrix         Matrice binaria del QR (valori "1" / "0").
     * @param int   $pixel_per_point Numero di pixel per modulo.
     * @param int   $margin          Margine (in moduli) attorno al codice.
     * @return string                Dati PNG binari.
     */
    function ucg_qr_matrix_to_png(array $matrix, $pixel_per_point, $margin) {
        $pixel_per_point = max(1, (int) $pixel_per_point);
        $margin = max(0, (int) $margin);
        $module_count = count($matrix);

        if ($module_count === 0) {
            return '';
        }

        $image_size = ($module_count + (2 * $margin)) * $pixel_per_point;
        if ($image_size <= 0) {
            return '';
        }

        $white = chr(0xFF);
        $black = chr(0x00);
        $raw_rows = '';

        for ($y = 0; $y < $image_size; $y++) {
            $raw_rows .= "\x00"; // Nessun filtro
            $module_y = intdiv($y, $pixel_per_point) - $margin;

            for ($x = 0; $x < $image_size; $x++) {
                $module_x = intdiv($x, $pixel_per_point) - $margin;
                $is_dark = false;

                if ($module_x >= 0 && $module_x < $module_count && $module_y >= 0 && $module_y < $module_count) {
                    $value = $matrix[$module_y][$module_x] ?? '0';
                    $is_dark = ($value === '1' || $value === 1 || $value === true);
                }

                $raw_rows .= $is_dark ? $black : $white;
            }
        }

        $compressed = gzcompress($raw_rows, 9);
        if ($compressed === false) {
            return '';
        }

        $ihdr = pack('N', $image_size) . pack('N', $image_size) . "\x08\x00\x00\x00\x00";

        return "\x89PNG\r\n\x1a\n"
            . ucg_qr_build_png_chunk('IHDR', $ihdr)
            . ucg_qr_build_png_chunk('IDAT', $compressed)
            . ucg_qr_build_png_chunk('IEND', '');
    }
}

if (!function_exists('ucg_generate_qr_code_image')) {
    /**
     * Genera un QR code e restituisce l'URL del file creato.
     * Utilizza GD se disponibile, altrimenti genera il PNG in pure-PHP.
     *
     * @param string $data Testo/URL da codificare.
     * @param array  $args Argomenti addizionali: filename, directory, size, margin, log_context.
     * @return string      URL del file generato oppure stringa vuota in caso di errore.
     */
    function ucg_generate_qr_code_image($data, $args = array()) {
        $defaults = array(
            'filename'    => '',
            'directory'   => 'qr_codes',
            'size'        => 10,
            'margin'      => 2,
            'log_context' => array(),
        );

        $args = wp_parse_args($args, $defaults);
        $data = (string) $data;
        $filename = sanitize_file_name((string) $args['filename']);
        $directory = trim((string) $args['directory'], '/');

        if ($data === '' || $filename === '') {
            return '';
        }

        if ($directory === '') {
            $directory = 'qr_codes';
        }

        ucc_include_qr_code_library();

        $upload_dir = wp_upload_dir();
        if (!empty($upload_dir['error'])) {
            ucg_log_error(
                'Impossibile accedere alla cartella upload per i QR code.',
                array_merge($args['log_context'], array('error' => $upload_dir['error']))
            );
            return '';
        }

        $folder = trailingslashit($upload_dir['basedir']) . $directory . '/';
        if (!wp_mkdir_p($folder)) {
            ucg_log_error(
                'Impossibile creare la directory per i QR code.',
                array_merge($args['log_context'], array('directory' => $folder))
            );
            return '';
        }

        $file_path = $folder . $filename . '.png';
        $file_url = trailingslashit($upload_dir['baseurl']) . $directory . '/' . $filename . '.png';

        $size = max(1, (int) $args['size']);
        $margin = max(0, (int) $args['margin']);

        $gd_available = extension_loaded('gd') && function_exists('imagecreatetruecolor');

        if (!file_exists($file_path)) {
            try {
                if ($gd_available) {
                    QRcode::png($data, $file_path, QR_ECLEVEL_L, $size, $margin);
                } else {
                    $matrix_raw = QRcode::text($data, false, QR_ECLEVEL_L, 1, 0);
                    if (!is_array($matrix_raw) || empty($matrix_raw)) {
                        throw new RuntimeException('Matrice QR non valida.');
                    }

                    $matrix = array();
                    foreach ($matrix_raw as $row) {
                        if (is_string($row)) {
                            $matrix[] = str_split($row);
                        } elseif (is_array($row)) {
                            $normalized = array();
                            foreach ($row as $cell) {
                                $normalized[] = ($cell === '1' || $cell === 1 || $cell === true) ? '1' : '0';
                            }
                            $matrix[] = $normalized;
                        }
                    }

                    $modules = count($matrix);
                    if ($modules === 0) {
                        throw new RuntimeException('Impossibile calcolare la dimensione della matrice QR.');
                    }

                    $max_size = ($modules + (2 * $margin)) > 0 ? (int) floor(QR_PNG_MAXIMUM_SIZE / ($modules + (2 * $margin))) : 1;
                    $pixel_per_point = min(max(1, $size), max(1, $max_size));

                    $png_data = ucg_qr_matrix_to_png($matrix, $pixel_per_point, $margin);
                    if ($png_data === '') {
                        throw new RuntimeException('Generazione PNG non riuscita.');
                    }

                    if (file_put_contents($file_path, $png_data) === false) {
                        throw new RuntimeException('Impossibile salvare il file PNG generato.');
                    }
                }
            } catch (Throwable $throwable) {
                if (file_exists($file_path)) {
                    wp_delete_file($file_path);
                }

                ucg_log_error(
                    'Errore durante la generazione del QR code: ' . $throwable->getMessage(),
                    array_merge($args['log_context'], array('file_path' => $file_path))
                );

                return '';
            }
        }

        if (!file_exists($file_path)) {
            ucg_log_error(
                'Il QR code non è stato generato correttamente.',
                array_merge($args['log_context'], array('file_path' => $file_path))
            );
            return '';
        }

        return esc_url($file_url);
    }
}


// Funzione per generare il QR code
function genera_qr_code($coupon_code) {
    if (!ucg_enforce_access_point('qr_factory')) {
        return false;
    }

    // Includi la libreria PHP QR Code
    ucc_include_qr_code_library();

    // Valida e sanifica il codice coupon per prevenire vulnerabilità
    $coupon_code = sanitize_text_field($coupon_code);
    if (empty($coupon_code)) {
        ucg_log_error("Errore: codice coupon vuoto o non valido.");
        return false;
    }

    // Crea l'URL con il codice coupon
    $url = esc_url(home_url('/?coupon_code=' . urlencode($coupon_code)));

    $qr_url = ucg_generate_qr_code_image($url, array(
        'filename'    => 'coupon_' . $coupon_code,
        'directory'   => 'qr_codes',
        'size'        => 10,
        'margin'      => 2,
        'log_context' => array('coupon_code' => $coupon_code, 'type' => 'coupon'),
    ));

    if ($qr_url === '') {
        return false;
    }

    return $qr_url;
}

<?php

/**
 * Author: Meteora Web <https://meteoraweb.com>
 */


// Impedisce l'accesso diretto ai file
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Render the inline script that powers the "copy URL" buttons in the admin.
 *
 * The script used to be printed as soon as the file was included, which could
 * pollute AJAX responses (e.g. Elementor JSON payloads). We now hook the
 * output into the admin footer so it only runs when a full admin page is
 * rendered.
 */
function ucg_render_coupon_view_copy_script() {
    if (wp_doing_ajax()) {
        return;
    }
    ?>
    <script>
        document.addEventListener("DOMContentLoaded", function () {
            document.querySelectorAll(".copy-url-button").forEach(function (button) {
                button.addEventListener("click", function () {
                    var url = this.getAttribute("data-url");
                    navigator.clipboard.writeText(url).then(function () {
                        alert("URL copiato: " + url);
                    }).catch(function (error) {
                        console.error("Errore durante la copia dell'URL", error);
                    });
                });
            });
        });
    </script>
    <?php
}
add_action('admin_footer', 'ucg_render_coupon_view_copy_script');
add_action('wp_footer', 'ucg_render_coupon_view_copy_script');

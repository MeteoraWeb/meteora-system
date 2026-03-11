(function ($) {
    'use strict';

    $(function () {
        var form = $('#ucg-license-form');
        var messageBox = $('#ucg-license-message');

        if (!form.length) {
            return;
        }

        form.on('submit', function (event) {
            event.preventDefault();

            var purchaseCode = $('#ucg_purchase_code').val().trim();
            showMessage('info', UCG_License.strings.verifying);

            var formEl = form.get(0);

            $.ajax({
                url: UCG_License.ajax_url,
                method: 'POST',
                dataType: 'json',
                data: {
                    action: 'ucg_verify_license',
                    nonce: UCG_License.nonce,
                    purchase_code: purchaseCode
                }
            }).done(function (response) {
                if (!handleAjaxResponse(response)) {
                    showMessage('error', UCG_License.strings.genericError);
                }
            }).fail(function (jqXHR) {
                if (handleAjaxResponse(jqXHR && jqXHR.responseJSON, jqXHR && jqXHR.responseJSON ? jqXHR.responseJSON.success : undefined)) {
                    return;
                }

                var parsed = parseJsonSafely(jqXHR && jqXHR.responseText);
                if (handleAjaxResponse(parsed, parsed && typeof parsed.success !== 'undefined' ? parsed.success : undefined)) {
                    return;
                }

                refreshStatusFromServer()
                    .done(function (response) {
                        if (!handleAjaxResponse(response, true)) {
                            showMessage('error', UCG_License.strings.genericError);
                        }
                    })
                    .fail(function () {
                        showMessage('error', UCG_License.strings.genericError);
                    });
            }).always(function () {
                if (window.ucgAdminUI) {
                    window.ucgAdminUI.endLoading(formEl);
                }
            });
        });

        function showMessage(type, text) {
            if (!messageBox.length) {
                return;
            }

            messageBox
                .removeClass('notice-success notice-error notice-warning notice-info')
                .addClass('notice-' + type)
                .text(text || '')
                .show();
        }

        function updateStatus(status) {
            if (!status) {
                return;
            }

            $('#ucg-license-state')
                .text(status.label || UCG_License.strings.notAvailable)
                .toggleClass('status-valid', !!status.valid)
                .toggleClass('status-invalid', !status.valid);

            $('#ucg-license-buyer').text(status.buyer || UCG_License.strings.notAvailable);
            $('#ucg-license-purchase').text(status.purchase_code || UCG_License.strings.notAvailable);
            $('#ucg-license-last-checked').text(status.last_checked || UCG_License.strings.neverVerified);

            var errorWrap = $('#ucg-license-error-wrap');
            if (errorWrap.length) {
                if (status.error && !status.valid) {
                    errorWrap.show();
                    $('#ucg-license-error').text(status.error);
                } else {
                    errorWrap.hide();
                    $('#ucg-license-error').text('');
                }
            }
        }

        function handleWarning(warning) {
            var warningBox = $('#ucg-license-warning');
            if (!warningBox.length) {
                return;
            }

            warning = warning || '';

            if (warning) {
                warningBox.show();
                warningBox.find('p').text(warning);
            } else {
                warningBox.hide();
                warningBox.find('p').text('');
            }
        }

        function handleAjaxResponse(response, defaultSuccess) {
            if (!response) {
                return false;
            }

            var success = typeof defaultSuccess === 'boolean' ? defaultSuccess : !!response.success;
            var data = response.data || response;

            if (!data || typeof data !== 'object') {
                return false;
            }

            if (data.status) {
                updateStatus(data.status);
            }

            handleWarning(data.warning);

            if (data.message) {
                var type = data.warning ? 'warning' : (success ? 'success' : 'error');
                showMessage(type, data.message);
                return true;
            }

            if (success && data.status && (data.status.valid || data.status.activation_type === 'secret')) {
                showMessage('success', UCG_License.strings.statusRefreshed);
                return true;
            }

            return false;
        }

        function parseJsonSafely(raw) {
            if (!raw || typeof raw !== 'string') {
                return null;
            }

            var trimmed = raw.replace(/^\uFEFF/, '').trim();

            if (!trimmed) {
                return null;
            }

            try {
                return JSON.parse(trimmed);
            } catch (e) {
                return null;
            }
        }

        function refreshStatusFromServer() {
            return $.ajax({
                url: UCG_License.ajax_url,
                method: 'POST',
                dataType: 'json',
                data: {
                    action: 'ucg_license_status',
                    nonce: UCG_License.nonce
                }
            });
        }
    });
})(jQuery);

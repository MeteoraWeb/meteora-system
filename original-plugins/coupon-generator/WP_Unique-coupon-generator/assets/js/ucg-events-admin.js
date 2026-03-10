(function ($) {
    'use strict';

    function bindRemoveButtons(containerSelector, rowClass) {
        $(document).on('click', containerSelector, function (e) {
            e.preventDefault();
            if ($(this).closest('tbody').find(rowClass).length <= 1) {
                return;
            }
            if (!window.confirm(UCGEventsAdmin.confirmRemove)) {
                return;
            }
            $(this).closest(rowClass).remove();
        });
    }

    $(function () {
        $('#gestione_pr').on('change', function () {
            $('#ucg-pr-wrapper').toggle($(this).is(':checked'));
        });

        $('#ucg-add-ticket').on('click', function (e) {
            e.preventDefault();
            var row = $('<tr class="ucg-ticket-row">' +
                '<td><input type="text" name="ticket_name[]" value="" required>' +
                '<input type="hidden" name="ticket_id[]" value="">' +
                '<input type="hidden" name="ticket_product_id[]" value="0"></td>' +
                '<td><input type="text" name="ticket_price[]" value="" placeholder="0,00"></td>' +
                '<td><input type="number" name="ticket_max[]" value="0" min="0"></td>' +
                '<td><button type="button" class="button link-delete ucg-remove-ticket">' + UCGEventsAdmin.remove + '</button></td>' +
                '</tr>');
            $('#ucg-ticket-rows').append(row);
        });

        $('#ucg-add-pr').on('click', function (e) {
            e.preventDefault();
            var row = $('<tr class="ucg-pr-row">' +
                '<td><input type="text" name="pr_nome[]" value=""></td>' +
                '<td><input type="number" name="pr_max[]" value="0" min="0"></td>' +
                '<td><button type="button" class="button link-delete ucg-remove-pr">' + UCGEventsAdmin.remove + '</button></td>' +
                '</tr>');
            $('#ucg-pr-rows').append(row);
        });

        bindRemoveButtons('.ucg-remove-ticket', '.ucg-ticket-row');
        bindRemoveButtons('.ucg-remove-pr', '.ucg-pr-row');

        var frame;
        $('#ucg-event-image-button').on('click', function (e) {
            e.preventDefault();
            if (frame) {
                frame.open();
                return;
            }

            frame = wp.media({
                title: UCGEventsAdmin.selectImage,
                button: {
                    text: UCGEventsAdmin.selectImage
                },
                multiple: false
            });

            frame.on('select', function () {
                var attachment = frame.state().get('selection').first().toJSON();
                $('#immagine_evento').val(attachment.url);
                $('.ucg-image-preview').remove();
                $('<div class="ucg-image-preview"><img src="' + attachment.url + '" alt=""></div>').insertAfter('#ucg-event-image-button');
            });

            frame.open();
        });

        var $gatewayModal = $('#ucg-wc-gateway-modal');
        var $gatewayControls = $('[data-ucg-gateway-controls]');
        var $gatewayToggle = $('#pagamento_woocommerce');
        var $gatewaySummary = $('[data-ucg-gateway-summary]');
        var gatewayModalLastFocus = null;

        function updateGatewaySummary() {
            if (!$gatewaySummary.length) {
                return;
            }

            var selected = [];
            $gatewayModal.find('input[name="pagamento_wc_gateways[]"]:checked').each(function () {
                var label = $(this).data('label');
                if (label) {
                    selected.push(label);
                }
            });

            if (selected.length) {
                $gatewaySummary.text(selected.join(', '));
            } else {
                $gatewaySummary.text($gatewaySummary.data('default') || '');
            }
        }

        function openGatewayModal(trigger) {
            if (!$gatewayModal.length) {
                return;
            }

            gatewayModalLastFocus = trigger || null;
            $gatewayModal.addClass('is-visible').attr('aria-hidden', 'false');

            var $focusTarget = $gatewayModal.find('input[name="pagamento_wc_gateways[]"]').first();
            if ($focusTarget.length) {
                $focusTarget.trigger('focus');
            } else {
                $gatewayModal.find('.ucg-wc-gateway-dialog').trigger('focus');
            }
        }

        function closeGatewayModal() {
            if (!$gatewayModal.length) {
                return;
            }

            $gatewayModal.removeClass('is-visible').attr('aria-hidden', 'true');
            if (gatewayModalLastFocus) {
                $(gatewayModalLastFocus).trigger('focus');
                gatewayModalLastFocus = null;
            }
        }

        if ($gatewayControls.length) {
            $gatewayControls.toggle($gatewayToggle.is(':checked'));
        }

        updateGatewaySummary();

        $gatewayModal.on('change', 'input[name="pagamento_wc_gateways[]"]', updateGatewaySummary);

        $(document).on('click', '[data-ucg-gateway-open]', function (e) {
            e.preventDefault();
            openGatewayModal(this);
        });

        $(document).on('click', '[data-ucg-gateway-close]', function (e) {
            e.preventDefault();
            closeGatewayModal();
        });

        $(document).on('click', '[data-ucg-gateway-apply]', function (e) {
            e.preventDefault();
            updateGatewaySummary();
            closeGatewayModal();
        });

        $gatewayModal.on('click', function (e) {
            if ($(e.target).is('[data-ucg-gateway-overlay]')) {
                e.preventDefault();
                closeGatewayModal();
            }
        });

        $gatewayToggle.on('change', function () {
            var isChecked = $(this).is(':checked');
            if ($gatewayControls.length) {
                $gatewayControls.toggle(isChecked);
            }
            if (isChecked) {
                openGatewayModal(this);
            } else {
                closeGatewayModal();
            }
        });

        $(document).on('keydown', function (e) {
            if (e.key === 'Escape' && $gatewayModal.hasClass('is-visible')) {
                closeGatewayModal();
            }
        });
    });
})(jQuery);

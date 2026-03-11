(function () {
    const settings = window.ucgTicketWizardSettings || {};
    const ajaxUrl = settings.ajaxUrl || '';
    const nonce = settings.nonce || '';
    const i18n = settings.i18n || {};

    if (!ajaxUrl || !nonce) {
        return;
    }

    const instances = [];

    function showAlert(box, type, message) {
        if (!box) {
            return;
        }
        if (!message) {
            box.hidden = true;
            box.textContent = '';
            box.className = 'ucg-wizard-alert';
            return;
        }
        box.hidden = false;
        box.textContent = message;
        box.className = 'ucg-wizard-alert ucg-wizard-alert-' + type;
    }

    function setLoading(button, isLoading, label) {
        if (!button) {
            return;
        }
        if (isLoading) {
            button.dataset.ucgOriginalText = button.textContent;
            button.disabled = true;
            button.classList.add('ucg-is-loading');
            if (label) {
                button.textContent = label;
            }
        } else {
            button.disabled = false;
            button.classList.remove('ucg-is-loading');
            if (button.dataset.ucgOriginalText) {
                button.textContent = button.dataset.ucgOriginalText;
                delete button.dataset.ucgOriginalText;
            }
        }
    }

    function formDataToObject(formData) {
        const result = {};
        formData.forEach((value, key) => {
            if (Object.prototype.hasOwnProperty.call(result, key)) {
                if (!Array.isArray(result[key])) {
                    result[key] = [result[key]];
                }
                result[key].push(value);
            } else {
                result[key] = value;
            }
        });
        return result;
    }

    function collectInputs(container) {
        if (!container) {
            return {};
        }
        const inputs = container.querySelectorAll('input, select, textarea');
        const data = {};
        inputs.forEach((input) => {
            const name = input.name || input.id;
            if (!name) {
                return;
            }
            if ((input.type === 'checkbox' || input.type === 'radio') && !input.checked) {
                return;
            }
            if (Object.prototype.hasOwnProperty.call(data, name)) {
                if (!Array.isArray(data[name])) {
                    data[name] = [data[name]];
                }
                data[name].push(input.value);
            } else {
                data[name] = input.value;
            }
        });
        return data;
    }

    function renderTickets(summaryBox, payload) {
        if (!summaryBox) {
            return;
        }
        summaryBox.innerHTML = '';
        const message = document.createElement('p');
        message.className = 'ucg-wizard-message';
        message.textContent = payload.message || '';
        summaryBox.appendChild(message);

        const list = document.createElement('div');
        list.className = 'ucg-ticket-downloads';

        const tickets = Array.isArray(payload.tickets) ? payload.tickets : [];
        tickets.forEach((ticket) => {
            const item = document.createElement('div');
            item.className = 'ucg-ticket-download';
            if (ticket.code) {
                const code = document.createElement('p');
                code.className = 'ucg-ticket-code';
                code.textContent = ticket.code;
                item.appendChild(code);
            }
            if (ticket.status_label) {
                const status = document.createElement('p');
                status.className = 'ucg-ticket-status';
                status.textContent = ticket.status_label;
                item.appendChild(status);
            }
            if (ticket.pdf_url) {
                const linkPdf = document.createElement('a');
                linkPdf.href = ticket.pdf_url;
                linkPdf.target = '_blank';
                linkPdf.rel = 'noopener noreferrer';
                linkPdf.className = 'ucg-ticket-button';
                linkPdf.textContent = i18n.downloadPdf || 'Download PDF Ticket';
                item.appendChild(linkPdf);
            }
            if (ticket.png_url) {
                const linkPng = document.createElement('a');
                linkPng.href = ticket.png_url;
                linkPng.target = '_blank';
                linkPng.rel = 'noopener noreferrer';
                linkPng.className = 'ucg-ticket-button';
                linkPng.textContent = i18n.downloadPng || 'Download PNG Ticket';
                item.appendChild(linkPng);
            }
            if (item.children.length) {
                list.appendChild(item);
            }
        });

        if (list.children.length) {
            summaryBox.appendChild(list);
        }

        if (payload.whatsapp_link) {
            const whatsapp = document.createElement('a');
            whatsapp.href = payload.whatsapp_link;
            whatsapp.target = '_blank';
            whatsapp.rel = 'noopener noreferrer';
            whatsapp.className = 'ucg-ticket-button ucg-ticket-button-whatsapp';
            whatsapp.textContent = i18n.whatsapp || 'Receive via WhatsApp';
            summaryBox.appendChild(whatsapp);
        }
    }

    function setupWizard(form) {
        const wrapper = form.closest('.ucg-ticket-wizard');
        if (!wrapper) {
            return;
        }

        const PHONE_PATTERN = /^\+39\d{10}$/;

        let config = {};
        const configRaw = wrapper.getAttribute('data-ucg-config');
        if (configRaw) {
            try {
                config = JSON.parse(configRaw);
            } catch (error) {
                config = {};
            }
        }

        const alertBox = form.querySelector('[data-ucg-alert]');
        const summaryBox = form.querySelector('[data-ucg-summary]');
        const steps = Array.from(form.querySelectorAll('.ucg-wizard-step'));
        const confirmButton = form.querySelector('.ucg-wizard-confirm');
        const state = {
            token: null,
            config,
            paymentMode: null,
        };

        function showStep(targetStep) {
            steps.forEach((step) => {
                const stepIndex = parseInt(step.getAttribute('data-ucg-step'), 10);
                if (stepIndex === targetStep) {
                    step.classList.add('is-active');
                } else {
                    step.classList.remove('is-active');
                }
            });
        }

        function toggleGatewayFields(gateway) {
            const fieldGroups = form.querySelectorAll('.ucg-payment-fields');
            fieldGroups.forEach((group) => {
                const target = group.getAttribute('data-gateway-fields');
                if (target === gateway) {
                    group.removeAttribute('hidden');
                } else {
                    group.setAttribute('hidden', 'hidden');
                }
            });
        }

        function syncConfirmButton() {
            if (!confirmButton) {
                return;
            }
            if (confirmButton.classList.contains('ucg-is-loading')) {
                return;
            }
            const selected = form.querySelector('input[name="payment_method"]:checked');
            const defaultLabel = confirmButton.getAttribute('data-ucg-confirm-default') || confirmButton.textContent;
            confirmButton.textContent = defaultLabel;
            if (state.paymentMode && state.paymentMode !== 'online') {
                confirmButton.disabled = true;
                return;
            }
            confirmButton.disabled = !selected;
        }

        function submitOrder(gateway, options = {}) {
            if (!state.token) {
                const error = new Error(i18n.step1Error || '');
                showAlert(alertBox, 'error', error.message);
                return Promise.reject(error);
            }

            const trigger = options.triggerButton || confirmButton;
            const isManual = options.manualMode === true || !gateway;
            const loadingLabel = options.loadingLabel || (isManual
                ? (i18n.processingReservation || i18n.loading || '')
                : (i18n.processingPayment || i18n.loading || ''));
            const shouldSetLoading = options.skipLoading === true ? false : true;

            if (trigger && shouldSetLoading) {
                setLoading(trigger, true, loadingLabel);
            }

            if (summaryBox && options.preserveSummary !== true) {
                summaryBox.innerHTML = '';
            }

            const payload = new FormData();
            payload.append('action', 'ucg_events_process_ticket_order');
            payload.append('nonce', nonce);
            payload.append('event_id', config.eventId || '');
            payload.append('token', state.token);
            if (gateway) {
                payload.append('payment_method', gateway);
            }
            if (state.paymentMode) {
                payload.append('payment_mode', state.paymentMode);
            }
            payload.append('return_url', config.returnUrl || window.location.href);

            if (!isManual) {
                const gatewayFields = form.querySelector('.ucg-payment-fields[data-gateway-fields="' + gateway + '"]');
                if (gatewayFields) {
                    const gatewayData = collectInputs(gatewayFields);
                    if (Object.keys(gatewayData).length) {
                        payload.append('gateway_data', JSON.stringify(gatewayData));
                    }
                }
            }

            return fetch(ajaxUrl, {
                method: 'POST',
                credentials: 'same-origin',
                body: payload,
            })
                .then((response) => response.json())
                .then((data) => {
                    if (!data) {
                        throw new Error(i18n.genericError || '');
                    }
                    if (!data.success) {
                        const message = data.data && data.data.message ? data.data.message : (i18n.genericError || '');
                        throw new Error(message);
                    }

                    const result = data.data;
                    if (result.status === 'redirect' && result.redirect_url) {
                        state.token = null;
                        state.paymentMode = null;
                        window.location.href = result.redirect_url;
                        return result;
                    }

                    showStep(3);
                    renderTickets(summaryBox, result);
                    const backBtn = form.querySelector('.ucg-wizard-step[data-ucg-step="3"] .ucg-wizard-prev');
                    if (backBtn) {
                        backBtn.hidden = true;
                    }
                    state.token = null;
                    state.paymentMode = null;
                    return result;
                })
                .catch((error) => {
                    if (error && typeof error === 'object') {
                        error.ucgHandled = true;
                    }
                    showAlert(alertBox, 'error', (error && error.message) || i18n.genericError || '');
                    throw error;
                })
                .finally(() => {
                    if (trigger && options.skipResetLoading !== true) {
                        setLoading(trigger, false);
                    }
                    if (options.autoSync !== false) {
                        syncConfirmButton();
                    }
                });
        }

        form.addEventListener('change', (event) => {
            const target = event.target;
            if (target.name === 'payment_method') {
                toggleGatewayFields(target.value);
                syncConfirmButton();
            }
            if (target.name === 'payment_mode') {
                state.paymentMode = target.value;
                if (target.value !== 'online') {
                    const selectedGateway = form.querySelector('input[name="payment_method"]:checked');
                    if (selectedGateway) {
                        selectedGateway.checked = false;
                        toggleGatewayFields('');
                    }
                }
                syncConfirmButton();
            }
        });

        form.querySelectorAll('.ucg-wizard-prev').forEach((button) => {
            button.addEventListener('click', () => {
                const prev = parseInt(button.getAttribute('data-ucg-prev'), 10);
                showStep(prev);
                showAlert(alertBox, 'info', '');
            });
        });

        const stepOneButton = form.querySelector('.ucg-wizard-next[data-ucg-next="2"]');
        if (stepOneButton) {
            stepOneButton.addEventListener('click', () => {
                showAlert(alertBox, 'info', '');
                if (stepOneButton.disabled || stepOneButton.classList.contains('ucg-is-loading')) {
                    return;
                }
                const phoneInput = form.querySelector('[data-ucg-phone-input]');
                if (phoneInput) {
                    const phoneValue = (phoneInput.value || '').trim();
                    if (!PHONE_PATTERN.test(phoneValue)) {
                        let warning = null;
                        const container = phoneInput.closest('.ucg-form-field');
                        if (container) {
                            warning = container.querySelector('[data-ucg-phone-warning]');
                        }
                        if (!warning && phoneInput.parentElement) {
                            warning = phoneInput.parentElement.querySelector('[data-ucg-phone-warning]');
                        }
                        if (warning) {
                            warning.hidden = false;
                            warning.setAttribute('aria-hidden', 'false');
                        }
                        phoneInput.focus();
                        showAlert(alertBox, 'error', i18n.phoneFormatError || 'Il numero di telefono deve iniziare con +39 e contenere 10 cifre.');
                        return;
                    }
                }
                const selectedModeInput = form.querySelector('input[name="payment_mode"]:checked');
                if (!selectedModeInput) {
                    showAlert(alertBox, 'error', i18n.paymentModeError || i18n.step2Error || '');
                    return;
                }

                const selectedMode = selectedModeInput.value;
                state.paymentMode = selectedMode;

                const formData = new FormData(form);
                formData.append('action', 'ucg_events_validate_ticket_step');
                formData.append('nonce', nonce);
                formData.append('event_id', config.eventId || '');
                formData.append('form', JSON.stringify(formDataToObject(new FormData(form))));

                const stepLoadingLabel = (selectedMode === 'loco' || selectedMode === 'manual')
                    ? (i18n.processingReservation || i18n.loading || '')
                    : (i18n.loading || '');

                setLoading(stepOneButton, true, stepLoadingLabel);

                fetch(ajaxUrl, {
                    method: 'POST',
                    credentials: 'same-origin',
                    body: formData,
                })
                    .then((response) => response.json())
                    .then((data) => {
                        if (!data || !data.success) {
                            throw new Error(data && data.data && data.data.message ? data.data.message : i18n.step1Error || '');
                        }
                        const payload = data.data || {};
                        if (payload.status === 'success' && (payload.mode === 'in_loco' || payload.mode === 'manual')) {
                            state.token = null;
                            state.paymentMode = null;
                            showStep(3);
                            renderTickets(summaryBox, payload);
                            const backBtn = form.querySelector('.ucg-wizard-step[data-ucg-step="3"] .ucg-wizard-prev');
                            if (backBtn) {
                                backBtn.hidden = true;
                            }
                            syncConfirmButton();
                            showAlert(alertBox, 'success', '');
                            return null;
                        }

                        state.token = payload.token || null;
                        state.paymentMode = payload.payment_mode || selectedMode;

                        if (!state.token) {
                            throw new Error(i18n.genericError || '');
                        }

                        if (state.paymentMode === 'loco' || state.paymentMode === 'manual') {
                            return submitOrder('', {
                                manualMode: true,
                                triggerButton: stepOneButton,
                                loadingLabel: i18n.processingReservation || i18n.loading || '',
                                skipLoading: true,
                                skipResetLoading: true,
                                autoSync: false,
                            });
                        }

                        showStep(2);
                        syncConfirmButton();
                        showAlert(alertBox, 'success', '');
                        return null;
                    })
                    .catch((error) => {
                        if (error && error.ucgHandled) {
                            return;
                        }
                        showAlert(alertBox, 'error', (error && error.message) || i18n.step1Error || '');
                    })
                    .finally(() => {
                        setLoading(stepOneButton, false);
                    });
            });
        }

        if (confirmButton) {
            confirmButton.addEventListener('click', () => {
                showAlert(alertBox, 'info', '');
                if (!state.token) {
                    showAlert(alertBox, 'error', i18n.step1Error || '');
                    return;
                }
                if (state.paymentMode && state.paymentMode !== 'online') {
                    showAlert(alertBox, 'error', i18n.paymentModeError || i18n.step2Error || '');
                    return;
                }
                const selected = form.querySelector('input[name="payment_method"]:checked');
                if (!selected) {
                    showAlert(alertBox, 'error', i18n.step2Error || '');
                    return;
                }
                const gateway = selected.value;
                submitOrder(gateway).catch(() => {});
            });
        }

        const initialModeInput = form.querySelector('input[name="payment_mode"]:checked');
        if (initialModeInput) {
            state.paymentMode = initialModeInput.value;
        }

        const initialSelection = form.querySelector('input[name="payment_method"]:checked') || form.querySelector('input[name="payment_method"]');
        if (initialSelection) {
            initialSelection.checked = true;
            toggleGatewayFields(initialSelection.value);
            syncConfirmButton();
        } else {
            syncConfirmButton();
        }

        instances.push({
            form,
            wrapper,
            showStep,
            summaryBox,
        });
    }

    function handleReturn() {
        const params = new URLSearchParams(window.location.search);
        const orderKey = params.get('ucg_order_key');
        const wizardToken = params.get('ucg_wizard_token');
        if (!orderKey || !wizardToken) {
            return;
        }
        params.delete('ucg_order_key');
        params.delete('ucg_wizard_token');
        const newQuery = params.toString();
        const newUrl = window.location.pathname + (newQuery ? '?' + newQuery : '') + window.location.hash;
        if (window.history && window.history.replaceState) {
            window.history.replaceState({}, document.title, newUrl);
        }

        const instance = instances[0];
        if (!instance) {
            return;
        }

        const payload = new FormData();
        payload.append('action', 'ucg_events_fetch_order_summary');
        payload.append('nonce', nonce);
        payload.append('order_key', orderKey);
        payload.append('wizard_token', wizardToken);

        const summaryBox = instance.summaryBox;
        if (summaryBox) {
            summaryBox.innerHTML = '<p class="ucg-wizard-message">' + (i18n.polling || '') + '</p>';
        }
        instance.showStep(3);

        fetch(ajaxUrl, {
            method: 'POST',
            credentials: 'same-origin',
            body: payload,
        })
            .then((response) => response.json())
            .then((data) => {
                if (!data || !data.success) {
                    throw new Error(data && data.data && data.data.message ? data.data.message : i18n.genericError || '');
                }
                renderTickets(summaryBox, data.data);
                const backBtn = instance.form.querySelector('.ucg-wizard-step[data-ucg-step="3"] .ucg-wizard-prev');
                if (backBtn) {
                    backBtn.hidden = true;
                }
            })
            .catch((error) => {
                if (summaryBox) {
                    summaryBox.innerHTML = '';
                }
                const alertBox = instance.form.querySelector('[data-ucg-alert]');
                showAlert(alertBox, 'error', error.message || i18n.genericError || '');
            });
    }

    function init() {
        document.querySelectorAll('form[data-ucg-form]').forEach((form) => {
            setupWizard(form);
        });
        handleReturn();
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();

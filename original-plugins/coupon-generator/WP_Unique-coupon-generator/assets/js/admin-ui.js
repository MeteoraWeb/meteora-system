(function (window, document) {
    'use strict';

    function toArray(nodeList) {
        return Array.prototype.slice.call(nodeList || []);
    }

    function escapeRegExp(input) {
        return String(input).replace(/[\\^$.*+?()[\]{}|]/g, '\\$&');
    }

    function dispatchChangeEvent(element) {
        if (!element) {
            return;
        }

        var event;
        if (typeof Event === 'function') {
            event = new Event('change', { bubbles: true });
        } else {
            event = document.createEvent('HTMLEvents');
            event.initEvent('change', true, true);
        }

        element.dispatchEvent(event);
    }

    function setLoadingState(form, isLoading) {
        if (!form) {
            return;
        }

        form.classList.toggle('is-loading', isLoading);
        var button = form.querySelector('.ucg-button-spinner');
        if (button) {
            button.classList.toggle('is-loading', isLoading);
        }
    }

    function setupForms() {
        toArray(document.querySelectorAll('form[data-ucg-loading]')).forEach(function (form) {
            form.addEventListener('submit', function () {
                setLoadingState(form, true);
            });

            form.addEventListener('ucg:loading:done', function () {
                setLoadingState(form, false);
            });
        });
    }

    function setupToggles() {
        toArray(document.querySelectorAll('[data-ucg-toggle]')).forEach(function (toggle) {
            var targetSelector = '[data-ucg-target="' + toggle.getAttribute('data-ucg-toggle') + '"]';
            var target = document.querySelector(targetSelector);
            if (!target) {
                return;
            }

            var update = function () {
                var isVisible = !!toggle.checked;
                target.classList.toggle('hidden', !isVisible);
                target.setAttribute('aria-hidden', isVisible ? 'false' : 'true');
            };

            toggle.addEventListener('change', update);
            update();
        });
    }

    function setupExclusiveCheckboxes() {
        var groups = {};

        toArray(document.querySelectorAll('[data-ucg-exclusive]')).forEach(function (checkbox) {
            if (!checkbox || checkbox.tagName !== 'INPUT') {
                return;
            }

            var group = checkbox.getAttribute('data-ucg-exclusive');
            if (!group) {
                return;
            }

            if (!groups[group]) {
                groups[group] = [];
            }

            groups[group].push(checkbox);
        });

        Object.keys(groups).forEach(function (groupKey) {
            var groupCheckboxes = groups[groupKey];
            groupCheckboxes.forEach(function (checkbox) {
                checkbox.addEventListener('change', function () {
                    if (!checkbox.checked) {
                        return;
                    }

                    groupCheckboxes.forEach(function (other) {
                        if (other !== checkbox && other.checked) {
                            other.checked = false;
                            dispatchChangeEvent(other);
                        }
                    });
                });
            });
        });
    }

    function fallbackCopy(text) {
        var textarea = document.createElement('textarea');
        textarea.value = text;
        textarea.setAttribute('readonly', '');
        textarea.style.position = 'absolute';
        textarea.style.left = '-9999px';
        document.body.appendChild(textarea);
        textarea.select();
        try {
            document.execCommand('copy');
        } catch (error) {
            console.error(error); // eslint-disable-line no-console
        }
        document.body.removeChild(textarea);
    }

    function setupCopyButtons() {
        toArray(document.querySelectorAll('[data-ucg-copy]')).forEach(function (button) {
            button.addEventListener('click', function (event) {
                event.preventDefault();
                var value = button.getAttribute('data-ucg-copy');
                if (!value) {
                    return;
                }

                var original = button.getAttribute('data-ucg-original') || button.textContent;
                button.setAttribute('data-ucg-original', original);

                var successLabel = button.getAttribute('data-ucg-copy-label') || 'Copiato!';

                var copyPromise;
                if (navigator.clipboard && navigator.clipboard.writeText) {
                    copyPromise = navigator.clipboard.writeText(value);
                } else {
                    fallbackCopy(value);
                    copyPromise = Promise.resolve();
                }

                copyPromise.then(function () {
                    button.textContent = successLabel;
                    button.classList.add('is-copied');
                    setTimeout(function () {
                        button.textContent = original;
                        button.classList.remove('is-copied');
                    }, 2000);
                }).catch(function () {
                    button.textContent = original;
                });
            });
        });
    }

    function setupModals() {
        var registry = {};

        toArray(document.querySelectorAll('.ucg-modal')).forEach(function (modal) {
            var requireConfirm = modal.hasAttribute('data-ucg-require-confirm');
            var confirmInput = modal.querySelector('[data-ucg-modal-confirm]');
            var closeButtons = toArray(modal.querySelectorAll('[data-ucg-close-modal]'));
            var modalId = modal.getAttribute('data-ucg-modal') || '';
            var manual = modal.hasAttribute('data-ucg-manual');

            if (requireConfirm && !confirmInput) {
                requireConfirm = false;
            }

            var canClose = function () {
                return !requireConfirm || (confirmInput && confirmInput.checked);
            };

            var focusConfirm = function () {
                if (confirmInput) {
                    confirmInput.focus();
                }
            };

            var updateCloseState = function () {
                var enabled = canClose();
                closeButtons.forEach(function (button) {
                    if (!button) {
                        return;
                    }

                    if (enabled) {
                        button.removeAttribute('disabled');
                    } else {
                        button.setAttribute('disabled', 'disabled');
                    }
                });
            };

            var openModal = function () {
                updateCloseState();
                modal.classList.add('is-visible');
                if (requireConfirm && confirmInput) {
                    setTimeout(focusConfirm, 50);
                }
            };

            modal.ucgOpenModal = openModal;

            if (confirmInput) {
                confirmInput.addEventListener('change', updateCloseState);
            }

            updateCloseState();

            if (!manual) {
                setTimeout(openModal, 50);
            }

            modal.addEventListener('click', function (event) {
                var shouldAttemptClose = event.target === modal || event.target.hasAttribute('data-ucg-close-modal');
                if (!shouldAttemptClose) {
                    return;
                }

                if (!canClose()) {
                    event.preventDefault();
                    focusConfirm();
                    return;
                }

                modal.classList.remove('is-visible');
            });

            document.addEventListener('keydown', function (event) {
                if (event.key !== 'Escape' || !modal.classList.contains('is-visible')) {
                    return;
                }

                if (!canClose()) {
                    event.preventDefault();
                    focusConfirm();
                    return;
                }

                modal.classList.remove('is-visible');
            });

            if (modalId) {
                registry[modalId] = modal;
            }
        });

        window.ucgAdminUI = window.ucgAdminUI || {};
        window.ucgAdminUI.openModal = function (id) {
            if (!id) {
                return;
            }

            var target = registry[id] || document.querySelector('.ucg-modal[data-ucg-modal="' + id + '"]');
            if (!target) {
                return;
            }

            if (typeof target.ucgOpenModal === 'function') {
                target.ucgOpenModal();
            } else {
                target.classList.add('is-visible');
            }
        };
    }

    function setupModalTriggers() {
        toArray(document.querySelectorAll('[data-ucg-modal-trigger]')).forEach(function (control) {
            var targetId = control.getAttribute('data-ucg-modal-trigger');
            if (!targetId) {
                return;
            }

            control.addEventListener('change', function () {
                if (control.type === 'checkbox' && !control.checked) {
                    return;
                }

                if (window.ucgAdminUI && typeof window.ucgAdminUI.openModal === 'function') {
                    window.ucgAdminUI.openModal(targetId);
                }
            });
        });
    }

    function setupMediaFields() {
        if (!window.wp || !wp.media) {
            return;
        }

        toArray(document.querySelectorAll('[data-ucg-media]')).forEach(function (wrapper) {
            var addButton = wrapper.querySelector('[data-ucg-media-add]');
            var removeButton = wrapper.querySelector('[data-ucg-media-remove]');
            var inputId = wrapper.querySelector('[data-ucg-media-input]');
            var inputUrl = wrapper.querySelector('[data-ucg-media-url]');
            var preview = wrapper.querySelector('[data-ucg-media-preview]');

            if (!addButton || !inputId || !preview) {
                return;
            }

            var mediaFrame = null;

            var updatePreview = function (url) {
                if (!preview) {
                    return;
                }

                if (!url) {
                    preview.innerHTML = '';
                    if (removeButton) {
                        removeButton.classList.add('hidden');
                    }
                    return;
                }

                preview.innerHTML = '<img src="' + url + '" alt="" />';
                if (removeButton) {
                    removeButton.classList.remove('hidden');
                }
            };

            addButton.addEventListener('click', function (event) {
                event.preventDefault();

                if (mediaFrame) {
                    mediaFrame.open();
                    return;
                }

                mediaFrame = wp.media({
                    title: addButton.getAttribute('data-ucg-media-title') || addButton.textContent,
                    button: {
                        text: addButton.getAttribute('data-ucg-media-button') || addButton.textContent
                    },
                    multiple: false
                });

                mediaFrame.on('select', function () {
                    var attachment = mediaFrame.state().get('selection').first();
                    if (!attachment) {
                        return;
                    }

                    var data = attachment.toJSON();
                    inputId.value = data.id || '';
                    if (inputUrl) {
                        inputUrl.value = data.url || '';
                    }

                    var displayUrl = data.url || '';
                    if (data.sizes) {
                        if (data.sizes.medium && data.sizes.medium.url) {
                            displayUrl = data.sizes.medium.url;
                        } else if (data.sizes.large && data.sizes.large.url) {
                            displayUrl = data.sizes.large.url;
                        }
                    }

                    updatePreview(displayUrl);
                });

                mediaFrame.open();
            });

            if (removeButton) {
                removeButton.addEventListener('click', function (event) {
                    event.preventDefault();
                    inputId.value = '';
                    if (inputUrl) {
                        inputUrl.value = '';
                    }
                    updatePreview('');
                });
            }

            if (inputUrl && inputUrl.value) {
                updatePreview(inputUrl.value);
            } else if (inputId.value && inputUrl) {
                updatePreview(inputUrl.value);
            }
        });
    }

    function normaliseWhatsappTemplate(template) {
        if (!template) {
            return '';
        }

        var value = String(template);

        try {
            value = decodeURIComponent(value);
        } catch (error) {
            // Ignore malformed sequences.
        }

        value = value.replace(/<br\s*\/?\s*>/gi, '\n');
        value = value.replace(/\r\n?/g, '\n');

        var temp = document.createElement('div');
        temp.innerHTML = value;
        value = temp.textContent || temp.innerText || '';

        value = value.replace(/[\t\v\f\u00a0]+/g, ' ');
        value = value.replace(/ +\n/g, '\n').replace(/\n +/g, '\n');
        value = value.replace(/\n{3,}/g, '\n\n');

        return value.trim();
    }

    function applyWhatsappPlaceholders(template, data) {
        var message = String(template || '');
        if (!message) {
            return '';
        }

        var replacements = {
            '{qr_link}': data && data.qr_link ? data.qr_link : '',
            '{coupon_code}': data && data.coupon_code ? data.coupon_code : '',
            '{user_name}': data && data.user_name ? data.user_name : ''
        };

        Object.keys(replacements).forEach(function (placeholder) {
            var value = replacements[placeholder];
            var regex = new RegExp(escapeRegExp(placeholder), 'g');
            message = message.replace(regex, value);
        });

        var lineBreakRegex = new RegExp(escapeRegExp('{line_break}'), 'g');
        message = message.replace(lineBreakRegex, '\n');

        return message;
    }

    function setupWhatsappPreviews() {
        toArray(document.querySelectorAll('[data-ucg-whatsapp-preview]')).forEach(function (button) {
            if (!button) {
                return;
            }

            var sourceSelector = button.getAttribute('data-ucg-whatsapp-preview');
            if (!sourceSelector) {
                return;
            }

            var output = button.parentNode ? button.parentNode.querySelector('[data-ucg-whatsapp-output]') : null;
            if (!output) {
                var outputSelector = button.getAttribute('data-ucg-whatsapp-output');
                if (outputSelector) {
                    output = document.querySelector(outputSelector);
                }
            }

            var sampleData = {
                qr_link: button.getAttribute('data-ucg-preview-qr') || '',
                coupon_code: button.getAttribute('data-ucg-preview-code') || '',
                user_name: button.getAttribute('data-ucg-preview-name') || ''
            };

            var fallbackTemplate = button.getAttribute('data-ucg-whatsapp-default') || '';

            var renderPreview = function () {
                var source = document.querySelector(sourceSelector);
                var template = '';

                if (source) {
                    template = typeof source.value === 'string' ? source.value : source.textContent || '';
                }

                template = normaliseWhatsappTemplate(template);
                if (!template) {
                    template = normaliseWhatsappTemplate(fallbackTemplate);
                }

                var message = applyWhatsappPlaceholders(template, sampleData);
                message = normaliseWhatsappTemplate(message);

                if (!output) {
                    return;
                }

                if (message) {
                    output.textContent = message;
                    output.classList.add('is-visible');
                } else {
                    output.textContent = '';
                    output.classList.remove('is-visible');
                }
            };

            button.addEventListener('click', function (event) {
                event.preventDefault();
                renderPreview();
            });

            var sourceElement = document.querySelector(sourceSelector);
            if (sourceElement) {
                sourceElement.addEventListener('input', function () {
                    if (output && output.classList.contains('is-visible')) {
                        renderPreview();
                    }
                });
            }
        });
    }

    document.addEventListener('DOMContentLoaded', function () {
        setupForms();
        setupToggles();
        setupExclusiveCheckboxes();
        setupCopyButtons();
        setupModals();
        setupModalTriggers();
        setupMediaFields();
        setupWhatsappPreviews();
    });

    window.ucgAdminUI = window.ucgAdminUI || {};
    window.ucgAdminUI.endLoading = function (form) {
        if (!form) {
            return;
        }

        var node = typeof form === 'string' ? document.querySelector(form) : form;
        if (!node) {
            return;
        }

        var event = new CustomEvent('ucg:loading:done');
        node.dispatchEvent(event);
    };
})(window, document);

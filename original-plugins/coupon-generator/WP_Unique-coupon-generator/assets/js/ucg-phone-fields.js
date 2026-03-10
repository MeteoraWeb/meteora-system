(function (window, document) {
    'use strict';

    function toArray(nodeList) {
        return Array.prototype.slice.call(nodeList || []);
    }

    function findWarningElement(input) {
        var container = input.closest('.ucg-form-field');
        if (container) {
            var warning = container.querySelector('[data-ucg-phone-warning]');
            if (warning) {
                return warning;
            }
        }

        if (input.parentElement) {
            var siblingWarning = input.parentElement.querySelector('[data-ucg-phone-warning]');
            if (siblingWarning) {
                return siblingWarning;
            }
        }

        return null;
    }

    function sanitizePhoneInput(input) {
        var warning = findWarningElement(input);
        var PATTERN_FULL = /^\+39\d{10}$/;

        var hideWarning = function () {
            if (!warning) {
                return;
            }

            warning.hidden = true;
            warning.setAttribute('aria-hidden', 'true');
        };

        var showWarning = function () {
            if (!warning) {
                return;
            }

            warning.hidden = false;
            warning.setAttribute('aria-hidden', 'false');
        };

        var processValue = function (rawValue) {
            var raw = rawValue || '';
            var trimmed = raw.trim();
            var digits = trimmed.replace(/\D/g, '');

            if (digits.indexOf('39') === 0) {
                digits = digits.slice(2);
            } else {
                var prefixIndex = digits.indexOf('39');
                if (prefixIndex > 0) {
                    digits = digits.slice(prefixIndex + 2);
                }
            }

            if (digits.length > 10) {
                digits = digits.slice(0, 10);
            }

            var sanitized = '+39' + digits;

            if (sanitized !== input.value) {
                input.value = sanitized;
            }

            if (sanitized === '+39' || PATTERN_FULL.test(sanitized)) {
                hideWarning();
            }

            return sanitized;
        };

        hideWarning();
        processValue(input.value);

        input.addEventListener('input', function () {
            processValue(input.value);
        });

        input.addEventListener('blur', function () {
            var value = input.value ? input.value.trim() : '';
            if (!value || value === '+39') {
                hideWarning();
                return;
            }

            if (!PATTERN_FULL.test(value)) {
                showWarning();
            } else {
                hideWarning();
            }
        });

        if (input.form) {
            input.form.addEventListener('submit', function (event) {
                var value = input.value ? input.value.trim() : '';

                if (!PATTERN_FULL.test(value)) {
                    event.preventDefault();
                    event.stopImmediatePropagation();
                    showWarning();
                    input.focus();
                }
            });
        }
    }

    function setupDeliveryGroups() {
        var groups = document.querySelectorAll('[data-ucg-delivery-group]');
        if (!groups.length) {
            return;
        }

        toArray(groups).forEach(function (group) {
            var checkboxes = toArray(group.querySelectorAll('input[type="checkbox"][data-ucg-delivery-option]'));
            if (!checkboxes.length) {
                return;
            }

            var warning = group.querySelector('[data-ucg-delivery-warning]');
            var hideWarning = function () {
                if (!warning) {
                    return;
                }

                warning.hidden = true;
                warning.setAttribute('aria-hidden', 'true');
            };
            var showWarning = function () {
                if (!warning) {
                    return;
                }

                warning.hidden = false;
                warning.setAttribute('aria-hidden', 'false');
            };

            hideWarning();

            var enforceSingleSelection = function (current) {
                checkboxes.forEach(function (box) {
                    if (box !== current) {
                        box.checked = false;
                    }
                });
            };

            checkboxes.forEach(function (checkbox) {
                checkbox.addEventListener('change', function () {
                    if (checkbox.checked) {
                        enforceSingleSelection(checkbox);
                        hideWarning();
                    } else {
                        var hasSelection = checkboxes.some(function (box) {
                            return box.checked;
                        });
                        if (!hasSelection && group.hasAttribute('data-ucg-delivery-required')) {
                            showWarning();
                        }
                    }
                });
            });

            var form = group.closest('form');
            if (!form) {
                return;
            }

            form.addEventListener('submit', function (event) {
                if (!group.hasAttribute('data-ucg-delivery-required')) {
                    return;
                }

                var hasChecked = checkboxes.some(function (box) {
                    return box.checked;
                });

                if (!hasChecked) {
                    event.preventDefault();
                    event.stopImmediatePropagation();
                    showWarning();
                    if (checkboxes.length) {
                        checkboxes[0].focus();
                    }
                }
            });
        });
    }

    document.addEventListener('DOMContentLoaded', function () {
        var inputs = document.querySelectorAll('[data-ucg-phone-input]');
        toArray(inputs).forEach(function (input) {
            sanitizePhoneInput(input);
        });

        setupDeliveryGroups();
    });
})(window, document);

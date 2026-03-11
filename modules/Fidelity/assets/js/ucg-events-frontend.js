(function () {
    'use strict';

    function extractValue(raw, param) {
        if (typeof raw !== 'string') {
            return '';
        }

        var value = raw.trim();
        if (!param) {
            return value;
        }

        try {
            var parsed = new URL(value);
            var searchValue = parsed.searchParams.get(param);
            if (searchValue) {
                return searchValue;
            }
        } catch (error) {
            // Not a valid URL, continue with fallback parsing.
        }

        var regex = new RegExp(param + '=([^&]+)', 'i');
        var match = value.match(regex);
        if (match && match[1]) {
            try {
                return decodeURIComponent(match[1]);
            } catch (error) {
                return match[1];
            }
        }

        return value;
    }

    document.addEventListener('DOMContentLoaded', function () {
        if (typeof Html5QrcodeScanner === 'undefined') {
            return;
        }

        var wrappers = document.querySelectorAll('.ucg-qr-wrapper');
        wrappers.forEach(function (wrapper, index) {
            var reader = wrapper.querySelector('.ucg-qr-reader');
            if (!reader) {
                return;
            }

            var readerId = reader.getAttribute('id');
            if (!readerId) {
                readerId = 'ucg-qr-reader-' + (index + 1);
                while (document.getElementById(readerId)) {
                    readerId += '-' + Math.floor(Math.random() * 1000);
                }
                reader.setAttribute('id', readerId);
            }

            var form = wrapper.closest('form');
            var input = null;
            var inputSelector = wrapper.dataset.input;
            if (inputSelector) {
                try {
                    input = form ? form.querySelector(inputSelector) : document.querySelector(inputSelector);
                } catch (error) {
                    input = null;
                }
            }
            if (!input && form) {
                input = form.querySelector('input[type="text"], input[type="search"], input[type="url"]');
            }

            var submit = null;
            var submitSelector = wrapper.dataset.submit;
            if (submitSelector) {
                try {
                    submit = document.querySelector(submitSelector);
                } catch (error) {
                    submit = null;
                }
            }
            if (!submit && form) {
                submit = form.querySelector('[type="submit"]');
            }

            var toggle = wrapper.querySelector('.ucg-qr-toggle');
            var startText = wrapper.dataset.textStart || 'Start scan';
            var stopText = wrapper.dataset.textStop || 'Stop scan';
            var autoStart = wrapper.dataset.autostart === '1';
            var autoSubmit = wrapper.dataset.autosubmit === '1';
            var keepOpen = wrapper.dataset.keepopen === '1';

            var fps = parseInt(wrapper.dataset.fps || '10', 10);
            if (isNaN(fps) || fps <= 0) {
                fps = 10;
            }
            var qrboxValue = parseInt(wrapper.dataset.qrbox || '240', 10);
            if (isNaN(qrboxValue) || qrboxValue <= 0) {
                qrboxValue = null;
            }

            var scanner = null;

            function updateToggle() {
                if (!toggle) {
                    return;
                }
                toggle.textContent = wrapper.classList.contains('ucg-qr-active') ? stopText : startText;
            }

            function finalizeStop() {
                wrapper.classList.remove('ucg-qr-active');
                reader.innerHTML = '';
                updateToggle();
            }

            function stopScanner() {
                if (!scanner) {
                    finalizeStop();
                    return;
                }

                var instance = scanner;
                scanner = null;
                instance
                    .clear()
                    .then(finalizeStop)
                    .catch(finalizeStop);
            }

            function onScanSuccess(decodedText) {
                var value = extractValue(decodedText, wrapper.dataset.param);
                if (input) {
                    input.value = value;
                    input.dispatchEvent(new Event('input', { bubbles: true }));
                    input.focus();
                }

                if (!keepOpen) {
                    stopScanner();
                }

                if (autoSubmit && submit) {
                    submit.click();
                }
            }

            function startScanner() {
                if (scanner) {
                    return;
                }

                var config = { fps: fps };
                if (qrboxValue) {
                    config.qrbox = qrboxValue;
                }

                scanner = new Html5QrcodeScanner(readerId, config, false);
                scanner.render(onScanSuccess, function () {
                    // Ignore scan errors to keep the scanner active.
                });
                wrapper.classList.add('ucg-qr-active');
                updateToggle();
            }

            if (toggle) {
                toggle.addEventListener('click', function (event) {
                    event.preventDefault();
                    if (wrapper.classList.contains('ucg-qr-active')) {
                        stopScanner();
                    } else {
                        startScanner();
                    }
                });
            }

            if (form) {
                form.addEventListener('submit', stopScanner);
            }

            window.addEventListener('pagehide', stopScanner);
            document.addEventListener('visibilitychange', function () {
                if (document.hidden) {
                    stopScanner();
                }
            });

            updateToggle();
            if (autoStart) {
                startScanner();
            }
        });
    });
})();

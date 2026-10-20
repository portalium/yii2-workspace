(function () {
    'use strict';

    var BUSY_CLASS = 'ws-invitation-sending';

    function openDraftModal() {
        var modal = document.getElementById('invitation-modal');
        if (!modal || modal.getAttribute('data-open-on-load') !== '1' || !window.bootstrap) {
            return;
        }
        window.bootstrap.Modal.getOrCreateInstance(modal).show();
    }

    function setBusy(button, busy) {
        var indicator = button.querySelector('.' + BUSY_CLASS);
        if (!busy) {
            button.disabled = false;
            button.removeAttribute('aria-busy');
            if (indicator) {
                indicator.parentNode.removeChild(indicator);
            }
            return;
        }
        button.disabled = true;
        button.setAttribute('aria-busy', 'true');
        if (!indicator) {
            indicator = document.createElement('span');
            indicator.className = BUSY_CLASS;
            indicator.setAttribute('aria-hidden', 'true');
            var dots = document.createElement('span');
            dots.className = 'ws-invitation-dot-typing';
            indicator.appendChild(dots);
            button.appendChild(indicator);
        }
    }

    function hasClientValidation(form) {
        return !!(window.jQuery && window.jQuery(form).data('yiiActiveForm'));
    }

    function guardDoubleSubmit() {
        var form = document.getElementById('invitation-form');
        var button = document.getElementById('send-invitation-form');
        if (!form || !button) {
            return;
        }
        if (window.jQuery) {
            window.jQuery(form).on('beforeSubmit', function () {
                if (button.disabled) {
                    return false;
                }
                setBusy(button, true);
                return true;
            });
        }
        form.addEventListener('submit', function (event) {
            if (hasClientValidation(form)) {
                return;
            }
            if (button.disabled) {
                event.preventDefault();
                return;
            }
            setBusy(button, true);
        });
        window.addEventListener('pageshow', function (event) {
            if (event.persisted) {
                setBusy(button, false);
            }
        });
    }

    /**
     * Davet kabul sayfası: [Katıl] ilk gönderimde pasifleşir, ikinci gönderim durur (çift tıklama iki POST
     * göndermesin). Sunucu da daveti kilitleyerek tek üyelik yazar; bu yalnız gereksiz ikinci isteği önler.
     */
    function guardJoin() {
        var form = document.getElementById('ws-invitation-join');
        var button = form ? form.querySelector('button[type="submit"]') : null;
        if (!form || !button) {
            return;
        }
        form.addEventListener('submit', function (event) {
            if (button.disabled) {
                event.preventDefault();
                return;
            }
            setBusy(button, true);
        });
        window.addEventListener('pageshow', function (event) {
            if (event.persisted) {
                setBusy(button, false);
            }
        });
    }

    function showCopied(button) {
        var label = button.getAttribute('data-label') || button.textContent;
        button.setAttribute('data-label', label);
        button.textContent = button.getAttribute('data-copied') || label;
        window.setTimeout(function () {
            button.textContent = label;
        }, 2000);
    }

    function copyLink(button) {
        var url = button.getAttribute('data-url');
        var askToCopy = function () {
            window.prompt(button.getAttribute('data-prompt') || '', url);
        };
        if (navigator.clipboard && window.isSecureContext) {
            navigator.clipboard.writeText(url).then(function () {
                showCopied(button);
            }, askToCopy);
            return;
        }
        askToCopy();
    }

    function bindCopyButtons() {
        document.addEventListener('click', function (event) {
            var button = event.target && event.target.closest ? event.target.closest('.ws-invitation-copy') : null;
            if (button) {
                event.preventDefault();
                copyLink(button);
            }
        });
    }

    function init() {
        openDraftModal();
        guardDoubleSubmit();
        guardJoin();
        bindCopyButtons();
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();

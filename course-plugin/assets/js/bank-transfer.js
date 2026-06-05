(function () {
    'use strict';

    var i18n = window.courseBankTransfer || {};

    function showToast(root, message) {
        var toast = root.querySelector('.mbt-toast');
        if (!toast) {
            return;
        }
        toast.textContent = message;
        toast.hidden = false;
        toast.classList.add('is-visible');
        window.clearTimeout(toast._mbtTimer);
        toast._mbtTimer = window.setTimeout(function () {
            toast.classList.remove('is-visible');
            toast.hidden = true;
        }, 2200);
    }

    function copyText(text) {
        if (navigator.clipboard && navigator.clipboard.writeText) {
            return navigator.clipboard.writeText(text);
        }

        return new Promise(function (resolve, reject) {
            var area = document.createElement('textarea');
            area.value = text;
            area.setAttribute('readonly', '');
            area.style.position = 'fixed';
            area.style.left = '-9999px';
            document.body.appendChild(area);
            area.select();
            try {
                document.execCommand('copy');
                document.body.removeChild(area);
                resolve();
            } catch (err) {
                document.body.removeChild(area);
                reject(err);
            }
        });
    }

    function formatSum(value) {
        var digits = String(value).replace(/\D/g, '');
        if (!digits) {
            return '';
        }
        return digits.replace(/\B(?=(\d{3})+(?!\d))/g, ' ');
    }

    function parseSum(value) {
        return parseInt(String(value).replace(/\D/g, ''), 10) || 0;
    }

    function initBlock(root) {
        var form = root.querySelector('[data-mbt-form]');
        var sumInput = root.querySelector('#mbt-sum');
        var copyAllBtn = root.querySelector('[data-mbt-copy-all]');

        root.querySelectorAll('[data-mbt-copy]').forEach(function (btn) {
            btn.addEventListener('click', function () {
                var text = btn.getAttribute('data-mbt-copy') || '';
                copyText(text)
                    .then(function () {
                        btn.classList.add('is-copied');
                        showToast(root, i18n.copied || 'Скопировано');
                        window.setTimeout(function () {
                            btn.classList.remove('is-copied');
                        }, 1500);
                    })
                    .catch(function () {
                        showToast(root, i18n.copyFailed || 'Не удалось скопировать');
                    });
            });
        });

        if (copyAllBtn) {
            copyAllBtn.addEventListener('click', function () {
                var lines = [];
                root.querySelectorAll('.mbt-details__row').forEach(function (row) {
                    var label = row.querySelector('.mbt-details__label');
                    var value = row.querySelector('.mbt-details__value');
                    if (label && value) {
                        lines.push(label.textContent.trim() + ': ' + value.textContent.trim());
                    }
                });
                copyText(lines.join('\n'))
                    .then(function () {
                        showToast(root, i18n.copied || 'Скопировано');
                    })
                    .catch(function () {
                        showToast(root, i18n.copyFailed || 'Не удалось скопировать');
                    });
            });
        }

        root.querySelectorAll('[data-mbt-preset]').forEach(function (btn) {
            btn.addEventListener('click', function () {
                var amount = btn.getAttribute('data-mbt-preset') || '';
                if (!sumInput) {
                    return;
                }
                sumInput.value = formatSum(amount);
                root.querySelectorAll('.mbt-preset').forEach(function (pill) {
                    pill.classList.remove('is-active');
                });
                btn.classList.add('is-active');
            });
        });

        if (sumInput) {
            sumInput.addEventListener('input', function () {
                var caretEnd = sumInput.selectionEnd;
                var raw = parseSum(sumInput.value);
                sumInput.value = raw ? formatSum(raw) : '';
                sumInput.setSelectionRange(sumInput.value.length, sumInput.value.length);
                root.querySelectorAll('.mbt-preset').forEach(function (pill) {
                    pill.classList.toggle('is-active', parseSum(pill.getAttribute('data-mbt-preset')) === raw);
                });
            });
        }

        if (form) {
            form.addEventListener('submit', function (event) {
                var valid = true;
                var sumField = form.querySelector('[name="sum"]');
                var nameField = form.querySelector('[name="clientid"]');
                var emailField = form.querySelector('[name="client_email"]');

                [sumField, nameField, emailField].forEach(function (field) {
                    if (field) {
                        field.classList.remove('is-invalid');
                    }
                });

                if (sumField && parseSum(sumField.value) <= 0) {
                    sumField.classList.add('is-invalid');
                    showToast(root, i18n.invalidSum || 'Укажите сумму больше 0');
                    valid = false;
                }

                if (nameField && !nameField.value.trim()) {
                    nameField.classList.add('is-invalid');
                    if (valid) {
                        showToast(root, i18n.invalidName || 'Укажите ФИО');
                    }
                    valid = false;
                }

                if (emailField) {
                    var email = emailField.value.trim();
                    if (!email || !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
                        emailField.classList.add('is-invalid');
                        if (valid) {
                            showToast(root, i18n.invalidEmail || 'Укажите корректный email');
                        }
                        valid = false;
                    }
                }

                if (!valid) {
                    event.preventDefault();
                    return;
                }

                if (sumField) {
                    sumField.value = String(parseSum(sumField.value));
                }
            });
        }
    }

    function boot() {
        document.querySelectorAll('.mbt').forEach(initBlock);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', boot);
    } else {
        boot();
    }
})();

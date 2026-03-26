#!/bin/bash
#
# Настройка Postfix для отправки писем с сервера mbs.russianseminary.org
# Запуск: bash setup_postfix_mail.sh
#
set -e

echo "=== Настройка почтовой системы ==="

# 1. Проверяем/устанавливаем Postfix
if command -v postfix &>/dev/null; then
    echo "Postfix уже установлен."
else
    echo "Устанавливаем Postfix..."
    export DEBIAN_FRONTEND=noninteractive
    apt-get update -qq
    debconf-set-selections <<< "postfix postfix/mailname string mbs.russianseminary.org"
    debconf-set-selections <<< "postfix postfix/main_mailer_type string 'Internet Site'"
    apt-get install -y -qq postfix mailutils
    echo "Postfix установлен."
fi

# 2. Настраиваем Postfix
POSTFIX_MAIN="/etc/postfix/main.cf"
if [ -f "$POSTFIX_MAIN" ]; then
    cp "$POSTFIX_MAIN" "${POSTFIX_MAIN}.bak.$(date +%s)"

    postconf -e "myhostname = mbs.russianseminary.org"
    postconf -e "mydomain = russianseminary.org"
    postconf -e "myorigin = mbs.russianseminary.org"
    postconf -e "inet_interfaces = loopback-only"
    postconf -e "inet_protocols = ipv4"
    postconf -e "mydestination = localhost"
    postconf -e "relayhost ="
    postconf -e "smtp_tls_security_level = may"
    postconf -e "smtp_tls_CAfile = /etc/ssl/certs/ca-certificates.crt"
    postconf -e "smtpd_banner = \$myhostname ESMTP"
    postconf -e "message_size_limit = 10240000"

    echo "Postfix настроен."
else
    echo "ОШИБКА: $POSTFIX_MAIN не найден!"
    exit 1
fi

# 3. Запускаем/перезапускаем Postfix
systemctl enable postfix
systemctl restart postfix
echo "Postfix запущен."

# 4. Проверяем sendmail_path в PHP
echo ""
echo "=== Проверка PHP sendmail_path ==="
PHP_INI=$(php -r "echo php_ini_loaded_file();")
echo "php.ini: $PHP_INI"

SENDMAIL_PATH=$(php -r "echo ini_get('sendmail_path');")
echo "Текущий sendmail_path: ${SENDMAIL_PATH:-(пусто)}"

if [ -z "$SENDMAIL_PATH" ]; then
    echo "sendmail_path пустой! Добавляем..."
    # Ищем все php.ini для FPM
    for ini in /etc/php/*/fpm/php.ini /etc/php/*/cli/php.ini; do
        if [ -f "$ini" ]; then
            if ! grep -q "^sendmail_path" "$ini"; then
                echo 'sendmail_path = /usr/sbin/sendmail -t -i' >> "$ini"
                echo "Добавлено в $ini"
            fi
        fi
    done
fi

# 5. Перезапускаем PHP-FPM
echo ""
echo "=== Перезапуск PHP-FPM ==="
for svc in $(systemctl list-units --type=service --state=running --no-legend | grep 'php.*fpm' | awk '{print $1}'); do
    systemctl restart "$svc"
    echo "Перезапущен: $svc"
done

# 6. Тест отправки через sendmail
echo ""
echo "=== Тест отправки через sendmail ==="
if command -v sendmail &>/dev/null; then
    echo -e "Subject: Test from mbs.russianseminary.org $(date)\nFrom: noreply@mbs.russianseminary.org\nTo: valentink2410@gmail.com\n\nTest email from Postfix setup script.\nTime: $(date)" | sendmail -t
    echo "Письмо отправлено через sendmail. Проверьте valentink2410@gmail.com."
else
    echo "sendmail не найден!"
fi

# 7. Проверяем PTR-запись (rDNS)
echo ""
echo "=== Проверка PTR (rDNS) ==="
SERVER_IP=$(curl -s ifconfig.me 2>/dev/null || hostname -I | awk '{print $1}')
echo "IP сервера: $SERVER_IP"
PTR=$(dig +short -x "$SERVER_IP" 2>/dev/null || echo "(не удалось определить)")
echo "PTR: $PTR"
if [ -z "$PTR" ] || [ "$PTR" = "(не удалось определить)" ]; then
    echo "ВНИМАНИЕ: PTR не настроен. Gmail может отклонять письма."
    echo "Настройте PTR у хостинг-провайдера: $SERVER_IP → mbs.russianseminary.org"
fi

echo ""
echo "=== Проверка SPF ==="
SPF=$(dig +short TXT russianseminary.org 2>/dev/null | grep -i spf || echo "(не найден)")
echo "SPF: $SPF"

echo ""
echo "=== Готово ==="
echo "Теперь запустите: php fix_mail_now.php"
echo "Логи почты: tail -f /var/log/mail.log"

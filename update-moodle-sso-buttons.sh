#!/bin/bash
# Скрипт для обновления и проверки moodle-sso-buttons.php на сервере Moodle

echo "=========================================="
echo "Обновление moodle-sso-buttons.php"
echo "=========================================="

# Переходим в директорию Moodle
cd /var/www/www-root/data/www/class.russianseminary.org || exit 1

echo "1. Обновление файлов из Git..."
git pull

echo ""
echo "2. Проверка обновления файла..."
if grep -q "USER->id:" moodle-sso-buttons.php; then
    echo "✓ Файл обновлен успешно"
    grep -n "USER->id:" moodle-sso-buttons.php | head -1
else
    echo "✗ Файл не обновлен или изменения не найдены"
fi

echo ""
echo "3. Очистка кеша Moodle..."
php admin/cli/purge_caches.php

echo ""
echo "4. Перезапуск PHP-FPM (если доступен)..."
if systemctl is-active --quiet php8.1-fpm; then
    systemctl restart php8.1-fpm
    echo "✓ PHP-FPM перезапущен"
elif systemctl is-active --quiet php8.0-fpm; then
    systemctl restart php8.0-fpm
    echo "✓ PHP-FPM перезапущен"
elif systemctl is-active --quiet php-fpm; then
    systemctl restart php-fpm
    echo "✓ PHP-FPM перезапущен"
else
    echo "⚠ PHP-FPM не найден, пропускаем перезапуск"
fi

echo ""
echo "5. Проверка последних логов Moodle..."
echo "Последние 20 записей из логов Moodle SSO Buttons:"
tail -50 /var/www/www-root/data/www/moodledata/error.log | grep "Moodle SSO Buttons" | tail -20

echo ""
echo "=========================================="
echo "Готово! Теперь перезагрузите страницу в Moodle (Ctrl+F5)"
echo "=========================================="

#!/bin/bash
# Скрипт для обновления настроек SSO в файле Moodle
# Использование: ./update-moodle-sso-config.sh YOUR_MOODLE_SSO_API_KEY

MOODLE_FILE="/var/www/www-root/data/www/class.dekan.pro/moodle-sso-to-wordpress.php"
API_KEY="$1"

if [ -z "$API_KEY" ]; then
    echo "Ошибка: Укажите Moodle SSO API Key"
    echo "Использование: $0 YOUR_MOODLE_SSO_API_KEY"
    exit 1
fi

if [ ! -f "$MOODLE_FILE" ]; then
    echo "Ошибка: Файл не найден: $MOODLE_FILE"
    exit 1
fi

# Создаем резервную копию
cp "$MOODLE_FILE" "${MOODLE_FILE}.backup"

# Заменяем API ключ
sed -i "s/\$moodle_sso_api_key = 'ВАШ_MOODLE_SSO_API_KEY';/\$moodle_sso_api_key = '$API_KEY';/" "$MOODLE_FILE"

# Проверяем результат
if grep -q "ВАШ_MOODLE_SSO_API_KEY" "$MOODLE_FILE"; then
    echo "Ошибка: Ключ не был заменен. Проверьте файл вручную."
    exit 1
fi

echo "✓ Настройки SSO обновлены успешно!"
echo "✓ Резервная копия сохранена: ${MOODLE_FILE}.backup"
echo "✓ Проверьте работу: https://class.dekan.pro/moodle-sso-to-wordpress.php"

#!/bin/bash
# Скрипт для установки SSO в Moodle

MOODLE_PATH="/var/www/www-root/data/www/class.dekan.pro/public"
SSO_FILE="moodle-sso-external.php"

echo "=== Установка SSO для Moodle ==="
echo ""

# Проверка существования исходного файла
if [ ! -f "$SSO_FILE" ]; then
    echo "ОШИБКА: Файл $SSO_FILE не найден!"
    echo "Убедитесь, что вы находитесь в директории с файлами проекта."
    exit 1
fi

# Проверка существования директории Moodle
if [ ! -d "$MOODLE_PATH" ]; then
    echo "ОШИБКА: Директория Moodle не найдена: $MOODLE_PATH"
    exit 1
fi

# Удаление старого файла (если существует)
if [ -f "$MOODLE_PATH/auth/sso/login.php" ]; then
    echo "Удаление старого файла auth/sso/login.php..."
    rm "$MOODLE_PATH/auth/sso/login.php"
    echo "✓ Старый файл удален"
fi

# Удаление пустой директории (если пуста)
if [ -d "$MOODLE_PATH/auth/sso" ] && [ -z "$(ls -A $MOODLE_PATH/auth/sso)" ]; then
    echo "Удаление пустой директории auth/sso..."
    rmdir "$MOODLE_PATH/auth/sso"
    echo "✓ Пустая директория удалена"
fi

# Копирование нового файла
echo "Копирование $SSO_FILE в $MOODLE_PATH/sso-login.php..."
cp "$SSO_FILE" "$MOODLE_PATH/sso-login.php"

if [ $? -eq 0 ]; then
    echo "✓ Файл скопирован успешно"
else
    echo "ОШИБКА: Не удалось скопировать файл!"
    exit 1
fi

# Установка прав доступа
echo "Установка прав доступа..."
chmod 644 "$MOODLE_PATH/sso-login.php"

# Попытка определить владельца веб-сервера
if [ -f "$MOODLE_PATH/config.php" ]; then
    OWNER=$(stat -c '%U' "$MOODLE_PATH/config.php" 2>/dev/null || stat -f '%Su' "$MOODLE_PATH/config.php" 2>/dev/null)
    if [ ! -z "$OWNER" ]; then
        echo "Установка владельца: $OWNER"
        chown "$OWNER:$OWNER" "$MOODLE_PATH/sso-login.php"
    fi
fi

echo ""
echo "=== Установка завершена! ==="
echo ""
echo "Файл установлен: $MOODLE_PATH/sso-login.php"
echo ""
echo "Следующие шаги:"
echo "1. Войдите в админ-панель Moodle"
echo "2. Перейдите: Администрирование → Плагины → Аутентификация → Управление аутентификацией"
echo "3. Убедитесь, что SSO НЕ включен как метод аутентификации"
echo "4. Очистите кеш Moodle: Администрирование → Разработка → Очистить кеш"
echo ""

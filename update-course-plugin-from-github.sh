#!/bin/bash
# Скрипт для обновления course-plugin из GitHub на сервере

SERVER="root@site.dekan.pro"
SERVER_PLUGIN_DIR="/var/www/www-root/data/www/site.dekan.pro/wp-content/plugins/course-plugin"
GITHUB_REPO="https://github.com/ValentinK2410/course_wp.git"
TEMP_DIR="/tmp/course_wp_update_$$"

echo "=== Обновление course-plugin из GitHub ==="
echo ""

# Создаем временную директорию
mkdir -p "$TEMP_DIR"
cd "$TEMP_DIR"

# Клонируем репозиторий
echo "📥 Клонирование репозитория из GitHub..."
git clone "$GITHUB_REPO" . --quiet

if [ $? -ne 0 ]; then
    echo "❌ Ошибка при клонировании репозитория"
    rm -rf "$TEMP_DIR"
    exit 1
fi

echo "✅ Репозиторий успешно клонирован"
echo ""

# Проверяем наличие каталога course-plugin
if [ ! -d "course-plugin" ]; then
    echo "❌ Каталог course-plugin не найден в репозитории"
    rm -rf "$TEMP_DIR"
    exit 1
fi

# Копируем файлы на сервер
echo "📤 Копирование файлов на сервер..."

# Используем rsync для эффективного копирования
rsync -avz --delete \
    --exclude='.git' \
    --exclude='.DS_Store' \
    --exclude='*.swp' \
    --exclude='*.swo' \
    --exclude='*~' \
    "$TEMP_DIR/course-plugin/" "$SERVER:$SERVER_PLUGIN_DIR/"

if [ $? -eq 0 ]; then
    echo "✅ Файлы успешно скопированы на сервер"
else
    echo "❌ Ошибка при копировании файлов"
    rm -rf "$TEMP_DIR"
    exit 1
fi

# Устанавливаем правильные права доступа
echo "🔧 Установка прав доступа..."
ssh "$SERVER" "chown -R www-root:www-root $SERVER_PLUGIN_DIR && chmod -R 755 $SERVER_PLUGIN_DIR"

if [ $? -eq 0 ]; then
    echo "✅ Права доступа установлены"
else
    echo "⚠️  Предупреждение: не удалось установить права доступа"
fi

# Очищаем временную директорию
rm -rf "$TEMP_DIR"

echo ""
echo "=== Готово! ==="
echo ""
echo "Плагин course-plugin успешно обновлен из GitHub."
echo "Проверьте сайт и очистите кеш WordPress, если необходимо."

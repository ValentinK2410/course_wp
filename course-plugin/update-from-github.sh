#!/bin/bash
# Скрипт для обновления course-plugin из GitHub (выполняется на сервере)
# Универсальная версия - автоматически определяет путь к плагину

# Определяем путь к плагину автоматически
# Ищем в стандартных местах WordPress
POSSIBLE_PATHS=(
    "/var/www/www-root/data/www/mbs.russianseminary.org/wp-content/plugins/course-plugin"
    "/var/www/www-root/data/www/site.dekan.pro/wp-content/plugins/course-plugin"
    "$(pwd)"
    "$(dirname "$0")"
)

PLUGIN_DIR=""
for path in "${POSSIBLE_PATHS[@]}"; do
    if [ -f "$path/course-plugin.php" ]; then
        PLUGIN_DIR="$path"
        break
    fi
done

# Если не нашли, пытаемся найти в текущей директории или родительской
if [ -z "$PLUGIN_DIR" ]; then
    if [ -f "course-plugin.php" ]; then
        PLUGIN_DIR="$(pwd)"
    elif [ -f "$(dirname "$0")/course-plugin.php" ]; then
        PLUGIN_DIR="$(dirname "$0")"
    fi
fi

# Если всё ещё не нашли, просим указать вручную
if [ -z "$PLUGIN_DIR" ] || [ ! -f "$PLUGIN_DIR/course-plugin.php" ]; then
    echo "❌ Ошибка: плагин не найден автоматически"
    echo ""
    echo "Пожалуйста, укажите путь к плагину вручную:"
    echo "  PLUGIN_DIR=/путь/к/плагину $0"
    echo ""
    echo "Или запустите скрипт из директории плагина:"
    echo "  cd /путь/к/wp-content/plugins/course-plugin"
    echo "  ./update-from-github.sh"
    exit 1
fi

GITHUB_REPO="https://github.com/ValentinK2410/course_wp.git"
TEMP_DIR="/tmp/course_wp_update_$$"

echo "=== Обновление course-plugin из GitHub ==="
echo "📁 Директория плагина: $PLUGIN_DIR"
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

# Создаем резервную копию (опционально)
BACKUP_DIR="/tmp/course-plugin-backup-$(date +%Y%m%d-%H%M%S)"
echo "💾 Создание резервной копии..."
cp -r "$PLUGIN_DIR" "$BACKUP_DIR" 2>/dev/null
if [ $? -eq 0 ]; then
    echo "✅ Резервная копия создана: $BACKUP_DIR"
else
    echo "⚠️  Не удалось создать резервную копию (продолжаем...)"
fi
echo ""

# Копируем файлы
echo "📤 Копирование файлов..."

# Используем rsync для эффективного копирования
rsync -avz --delete \
    --exclude='.git' \
    --exclude='.DS_Store' \
    --exclude='*.swp' \
    --exclude='*.swo' \
    --exclude='*~' \
    "$TEMP_DIR/course-plugin/" "$PLUGIN_DIR/"

if [ $? -ne 0 ]; then
    echo "❌ Ошибка при копировании файлов"
    echo "💾 Восстановление из резервной копии..."
    if [ -d "$BACKUP_DIR" ]; then
        cp -r "$BACKUP_DIR"/* "$PLUGIN_DIR/"
    fi
    rm -rf "$TEMP_DIR"
    exit 1
fi

echo "✅ Файлы успешно обновлены"

# Устанавливаем правильные права доступа
echo "🔧 Установка прав доступа..."
# Определяем владельца текущей директории плагина
OWNER=$(stat -c '%U:%G' "$PLUGIN_DIR" 2>/dev/null || stat -f '%Su:%Sg' "$PLUGIN_DIR" 2>/dev/null || echo "www-root:www-root")

if command -v chown &> /dev/null; then
    chown -R "$OWNER" "$PLUGIN_DIR" 2>/dev/null || echo "⚠️  Не удалось изменить владельца (возможно, нужны права root)"
fi

chmod -R 755 "$PLUGIN_DIR" 2>/dev/null || echo "⚠️  Не удалось установить права доступа"

# Очищаем временную директорию
rm -rf "$TEMP_DIR"

echo ""
echo "=== Готово! ==="
echo ""
echo "Плагин course-plugin успешно обновлен из GitHub."
echo "Проверьте сайт и очистите кеш WordPress, если необходимо."
echo ""
if [ -d "$BACKUP_DIR" ]; then
    echo "💡 Резервная копия сохранена в: $BACKUP_DIR"
    echo "   (можно удалить через несколько дней)"
fi

#!/bin/bash
# Скрипт для обновления всех важных файлов плагина на сервере

SERVER="root@site.dekan.pro"
SERVER_BASE="/var/www/www-root/data/www/site.dekan.pro/wp-content/plugins/course-plugin"

echo "=== Обновление файлов плагина на сервере ==="
echo ""

# Важные файлы для обновления
FILES=(
    "includes/class-course-anti-bot.php"
    "includes/class-course-anti-bot-admin.php"
    "includes/class-course-moodle-user-sync.php"
    "includes/class-course-registration.php"
)

for file in "${FILES[@]}"; do
    LOCAL_FILE="course-plugin/$file"
    
    if [ ! -f "$LOCAL_FILE" ]; then
        echo "❌ Файл не найден: $LOCAL_FILE"
        continue
    fi
    
    echo "📤 Копирование $file..."
    scp "$LOCAL_FILE" "$SERVER:$SERVER_BASE/$file"
    
    if [ $? -eq 0 ]; then
        echo "✅ $file успешно обновлен"
    else
        echo "❌ Ошибка при копировании $file"
    fi
    echo ""
done

echo "=== Готово! ==="
echo ""
echo "Все файлы обновлены. Проверьте сайт и очистите кеш WordPress."


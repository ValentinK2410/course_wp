#!/bin/bash
# Скрипт для обновления файлов защиты от ботов на сервере

SERVER="root@site.dekan.pro"
SERVER_PATH="/var/www/www-root/data/www/site.dekan.pro/wp-content/plugins/course-plugin/includes"

echo "=== Обновление файлов защиты от ботов на сервере ==="
echo ""

# Файлы для обновления
FILES=(
    "class-course-anti-bot.php"
    "class-course-anti-bot-admin.php"
)

for file in "${FILES[@]}"; do
    LOCAL_FILE="course-plugin/includes/$file"
    
    if [ ! -f "$LOCAL_FILE" ]; then
        echo "❌ Файл не найден: $LOCAL_FILE"
        continue
    fi
    
    echo "📤 Копирование $file..."
    scp "$LOCAL_FILE" "$SERVER:$SERVER_PATH/$file"
    
    if [ $? -eq 0 ]; then
        echo "✅ $file успешно обновлен"
    else
        echo "❌ Ошибка при копировании $file"
    fi
    echo ""
done

echo "=== Готово! ==="
echo ""
echo "Проверьте файлы на сервере и очистите кеш WordPress."


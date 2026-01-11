#!/bin/bash
# Скрипт для проверки правильности настройки course-plugin

PLUGIN_DIR="/var/www/www-root/data/www/site.dekan.pro/wp-content/plugins/course-plugin"
ERRORS=0
WARNINGS=0

echo "=== Проверка настройки course-plugin ==="
echo ""

# Проверка 1: Существует ли директория плагина
echo "1. Проверка существования директории плагина..."
if [ -d "$PLUGIN_DIR" ]; then
    echo "   ✅ Директория существует: $PLUGIN_DIR"
else
    echo "   ❌ Директория не найдена: $PLUGIN_DIR"
    ERRORS=$((ERRORS + 1))
fi
echo ""

# Проверка 2: Есть ли основной файл плагина
echo "2. Проверка основного файла плагина..."
if [ -f "$PLUGIN_DIR/course-plugin.php" ]; then
    echo "   ✅ Файл course-plugin.php найден"
else
    echo "   ❌ Файл course-plugin.php не найден"
    ERRORS=$((ERRORS + 1))
fi
echo ""

# Проверка 3: Отсутствие вложенного репозитория Git
echo "3. Проверка отсутствия вложенного репозитория Git..."
if [ -d "$PLUGIN_DIR/.git" ]; then
    echo "   ❌ Вложенный репозиторий Git найден! Его нужно удалить:"
    echo "      rm -rf $PLUGIN_DIR/.git"
    ERRORS=$((ERRORS + 1))
else
    echo "   ✅ Вложенного репозитория Git нет"
fi
echo ""

# Проверка 4: Отсутствие вложенной директории course-plugin
echo "4. Проверка отсутствия вложенной директории course-plugin..."
if [ -d "$PLUGIN_DIR/course-plugin" ]; then
    echo "   ❌ Вложенная директория course-plugin найдена! Её нужно удалить:"
    echo "      rm -rf $PLUGIN_DIR/course-plugin"
    ERRORS=$((ERRORS + 1))
else
    echo "   ✅ Вложенной директории course-plugin нет"
fi
echo ""

# Проверка 5: Наличие важных директорий
echo "5. Проверка структуры каталогов..."
REQUIRED_DIRS=("includes" "assets" "templates")
for dir in "${REQUIRED_DIRS[@]}"; do
    if [ -d "$PLUGIN_DIR/$dir" ]; then
        echo "   ✅ Директория $dir найдена"
    else
        echo "   ⚠️  Директория $dir не найдена"
        WARNINGS=$((WARNINGS + 1))
    fi
done
echo ""

# Проверка 6: Наличие скрипта обновления
echo "6. Проверка скрипта обновления..."
if [ -f "$PLUGIN_DIR/update-from-github.sh" ]; then
    echo "   ✅ Скрипт update-from-github.sh найден"
    if [ -x "$PLUGIN_DIR/update-from-github.sh" ]; then
        echo "   ✅ Скрипт исполняемый"
    else
        echo "   ⚠️  Скрипт не исполняемый. Выполните: chmod +x $PLUGIN_DIR/update-from-github.sh"
        WARNINGS=$((WARNINGS + 1))
    fi
else
    echo "   ⚠️  Скрипт update-from-github.sh не найден"
    WARNINGS=$((WARNINGS + 1))
fi
echo ""

# Проверка 7: Права доступа
echo "7. Проверка прав доступа..."
OWNER=$(stat -c '%U:%G' "$PLUGIN_DIR" 2>/dev/null || stat -f '%Su:%Sg' "$PLUGIN_DIR" 2>/dev/null)
if [ "$OWNER" = "www-root:www-root" ] || [ "$OWNER" = "www-data:www-data" ] || [ "$OWNER" = "apache:apache" ]; then
    echo "   ✅ Права доступа правильные: $OWNER"
else
    echo "   ⚠️  Права доступа могут быть неправильными: $OWNER"
    echo "      Рекомендуется: chown -R www-root:www-root $PLUGIN_DIR"
    WARNINGS=$((WARNINGS + 1))
fi
echo ""

# Проверка 8: Проверка версии файла (сравнение с GitHub)
echo "8. Проверка актуальности файлов..."
if command -v git &> /dev/null; then
    TEMP_DIR="/tmp/course_wp_check_$$"
    mkdir -p "$TEMP_DIR"
    cd "$TEMP_DIR"
    
    echo "   📥 Клонирование репозитория для проверки..."
    git clone https://github.com/ValentinK2410/course_wp.git . --quiet 2>/dev/null
    
    if [ $? -eq 0 ] && [ -f "$TEMP_DIR/course-plugin/course-plugin.php" ]; then
        LOCAL_VERSION=$(grep -i "Version:" "$PLUGIN_DIR/course-plugin.php" 2>/dev/null | head -1 | sed 's/.*Version:[[:space:]]*\([0-9.]*\).*/\1/')
        GITHUB_VERSION=$(grep -i "Version:" "$TEMP_DIR/course-plugin/course-plugin.php" 2>/dev/null | head -1 | sed 's/.*Version:[[:space:]]*\([0-9.]*\).*/\1/')
        
        if [ -n "$LOCAL_VERSION" ] && [ -n "$GITHUB_VERSION" ]; then
            if [ "$LOCAL_VERSION" = "$GITHUB_VERSION" ]; then
                echo "   ✅ Версия плагина актуальна: $LOCAL_VERSION"
            else
                echo "   ⚠️  Версия плагина отличается:"
                echo "      Локальная: $LOCAL_VERSION"
                echo "      GitHub: $GITHUB_VERSION"
                echo "      Выполните обновление: ./update-from-github.sh"
                WARNINGS=$((WARNINGS + 1))
            fi
        else
            echo "   ⚠️  Не удалось определить версию"
        fi
    else
        echo "   ⚠️  Не удалось проверить версию (проблема с доступом к GitHub)"
    fi
    
    rm -rf "$TEMP_DIR"
else
    echo "   ⚠️  Git не установлен, проверка версии пропущена"
fi
echo ""

# Проверка 9: Проверка структуры файлов
echo "9. Проверка важных файлов..."
IMPORTANT_FILES=(
    "course-plugin.php"
    "includes/class-course-builder.php"
    "assets/js/builder-admin.js"
    "assets/css/builder-admin.css"
)

for file in "${IMPORTANT_FILES[@]}"; do
    if [ -f "$PLUGIN_DIR/$file" ]; then
        SIZE=$(stat -c%s "$PLUGIN_DIR/$file" 2>/dev/null || stat -f%z "$PLUGIN_DIR/$file" 2>/dev/null)
        if [ "$SIZE" -gt 0 ]; then
            echo "   ✅ $file (размер: $SIZE байт)"
        else
            echo "   ⚠️  $file пустой"
            WARNINGS=$((WARNINGS + 1))
        fi
    else
        echo "   ❌ $file не найден"
        ERRORS=$((ERRORS + 1))
    fi
done
echo ""

# Итоговый результат
echo "=== Результаты проверки ==="
echo ""
if [ $ERRORS -eq 0 ] && [ $WARNINGS -eq 0 ]; then
    echo "✅ Все проверки пройдены успешно!"
    echo ""
    echo "Плагин настроен правильно и готов к работе."
elif [ $ERRORS -eq 0 ]; then
    echo "⚠️  Найдено предупреждений: $WARNINGS"
    echo ""
    echo "Плагин должен работать, но рекомендуется исправить предупреждения."
else
    echo "❌ Найдено ошибок: $ERRORS"
    echo "⚠️  Найдено предупреждений: $WARNINGS"
    echo ""
    echo "Необходимо исправить ошибки перед использованием плагина."
fi
echo ""

# Рекомендации
if [ $ERRORS -gt 0 ] || [ $WARNINGS -gt 0 ]; then
    echo "=== Рекомендации ==="
    echo ""
    if [ -d "$PLUGIN_DIR/.git" ]; then
        echo "1. Удалите вложенный репозиторий Git:"
        echo "   rm -rf $PLUGIN_DIR/.git"
        echo ""
    fi
    if [ -d "$PLUGIN_DIR/course-plugin" ]; then
        echo "2. Удалите вложенную директорию:"
        echo "   rm -rf $PLUGIN_DIR/course-plugin"
        echo ""
    fi
    if [ ! -x "$PLUGIN_DIR/update-from-github.sh" ]; then
        echo "3. Сделайте скрипт обновления исполняемым:"
        echo "   chmod +x $PLUGIN_DIR/update-from-github.sh"
        echo ""
    fi
fi

exit $ERRORS

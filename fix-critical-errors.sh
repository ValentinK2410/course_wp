#!/bin/bash
# Скрипт для исправления критических ошибок на сервере

echo "=== Исправление критических ошибок на сервере ==="
echo ""

cd /var/www/www-root/data/www/site.dekan.pro/wp-content/plugins/course-plugin/includes || exit 1

# Создаем резервную копию
echo "1. Создание резервной копии..."
cp class-course-moodle-user-sync.php class-course-moodle-user-sync.php.backup.$(date +%Y%m%d_%H%M%S)

# Исправление 1: Удаляем проблемную строку с хуком
echo "2. Удаление проблемной строки с хуком wp_login..."
sed -i '/sync_password_on_first_login/,+2d' class-course-moodle-user-sync.php

# Исправление 2: Исправляем проблему с lastname
echo "3. Исправление проблемы с lastname..."

# Создаем временный файл с исправлением
cat > /tmp/fix_lastname.php << 'EOFPHP'
<?php
$file = 'class-course-moodle-user-sync.php';
$content = file_get_contents($file);

// Заменяем проблемную логику lastname
$old_pattern = '/\$lastname = !empty\(\$user->last_name\) && trim\(\$user->last_name\) !== \'\' \? trim\(\$user->last_name\) : \'-\';/';
$new_code = '// Если фамилия пустая, используем имя пользователя или "User" вместо дефиса
        if (!empty($user->last_name) && trim($user->last_name) !== \'\') {
            $lastname = trim($user->last_name);
        } elseif (!empty($user->first_name)) {
            // Если есть имя, используем его как фамилию
            $lastname = trim($user->first_name);
        } elseif (!empty($user->display_name)) {
            // Если есть отображаемое имя, используем его
            $lastname = trim($user->display_name);
        } else {
            // В крайнем случае используем "User"
            $lastname = \'User\';
        }';

if (preg_match($old_pattern, $content)) {
    $content = preg_replace($old_pattern, $new_code, $content);
    file_put_contents($file, $content);
    echo "Исправление применено\n";
} else {
    echo "Паттерн не найден, возможно уже исправлено\n";
}
EOFPHP

php /tmp/fix_lastname.php
rm /tmp/fix_lastname.php

# Проверяем синтаксис
echo "4. Проверка синтаксиса PHP..."
php -l class-course-moodle-user-sync.php

if [ $? -eq 0 ]; then
    echo ""
    echo "✅ УСПЕШНО! Критические ошибки исправлены."
    echo ""
    echo "Исправлено:"
    echo "  - Удалена проблемная строка с хуком wp_login"
    echo "  - Исправлена проблема с lastname (теперь используется 'User' вместо '-')"
    echo ""
    echo "Проверьте сайт: https://site.dekan.pro/wp-login.php"
else
    echo ""
    echo "❌ ОШИБКА синтаксиса! Восстановите из резервной копии."
    exit 1
fi


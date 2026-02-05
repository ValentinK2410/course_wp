#!/bin/bash
# Скрипт для копирования moodle-sso-buttons.php на сервер Moodle

echo "Скопируйте этот файл на сервер Moodle:"
echo ""
echo "1. Скачайте файл из репозитория:"
echo "   wget https://raw.githubusercontent.com/ValentinK2410/course_wp/master/course-plugin/moodle-sso-buttons.php -O /var/www/www-root/data/www/class.russianseminary.org/moodle-sso-buttons.php"
echo ""
echo "2. Или скопируйте содержимое файла вручную"
echo ""
echo "3. После копирования выполните:"
echo "   cd /var/www/www-root/data/www/class.russianseminary.org"
echo "   php admin/cli/purge_caches.php"
echo "   systemctl restart php8.1-fpm"
echo "   tail -50 /var/www/www-root/data/www/moodledata/error.log | grep 'Moodle SSO Buttons'"

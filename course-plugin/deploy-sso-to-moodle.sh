#!/bin/bash
# Копирует sso-login.php в корень Moodle
# Использование:
#   ./deploy-sso-to-moodle.sh [путь_к_moodle]
#   MOODLE_ROOT=/path/to/moodle ./deploy-sso-to-moodle.sh
#
# Пример:
#   ./deploy-sso-to-moodle.sh /var/www/www-root/data/www/class.russianseminary.org

set -e
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
SOURCE="$SCRIPT_DIR/sso-login.php"

MOODLE_ROOT="${MOODLE_ROOT:-${1:-}}"
if [ -z "$MOODLE_ROOT" ]; then
    # Пробуем стандартные пути
    for path in \
        "/var/www/www-root/data/www/class.russianseminary.org"
    do
        if [ -f "$SCRIPT_DIR/$path/config.php" ] 2>/dev/null; then
            MOODLE_ROOT="$SCRIPT_DIR/$path"
            break
        elif [ -f "$path/config.php" ] 2>/dev/null; then
            MOODLE_ROOT="$(cd "$SCRIPT_DIR" && cd "$path" && pwd)"
            break
        fi
    done
fi

if [ -z "$MOODLE_ROOT" ]; then
    echo "Ошибка: укажите путь к Moodle:"
    echo "  $0 /путь/к/moodle"
    echo "  MOODLE_ROOT=/путь/к/moodle $0"
    exit 1
fi

if [ ! -f "$MOODLE_ROOT/config.php" ]; then
    echo "Ошибка: config.php не найден в $MOODLE_ROOT"
    exit 1
fi

cp "$SOURCE" "$MOODLE_ROOT/sso-login.php"
echo "Скопировано: $SOURCE -> $MOODLE_ROOT/sso-login.php"

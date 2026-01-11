# Настройка синхронизации course-plugin с GitHub

## Проблема
На сервере в директории `/var/www/www-root/data/www/site.dekan.pro/wp-content/plugins/course-plugin/` был создан вложенный репозиторий Git, что привело к неправильной структуре каталогов.

## Решение

### Вариант 1: Обновление с локального компьютера (рекомендуется)

Используйте скрипт `update-course-plugin-from-github.sh` для обновления файлов на сервере:

```bash
./update-course-plugin-from-github.sh
```

Этот скрипт:
1. Клонирует репозиторий во временную директорию
2. Копирует содержимое `course-plugin/` на сервер
3. Устанавливает правильные права доступа

### Вариант 2: Обновление напрямую на сервере

1. Скопируйте скрипт на сервер:
```bash
scp course-plugin/update-from-github.sh root@site.dekan.pro:/tmp/
```

2. Выполните на сервере:
```bash
ssh root@site.dekan.pro
cd /var/www/www-root/data/www/site.dekan.pro/wp-content/plugins/course-plugin
bash /tmp/update-from-github.sh
```

Или разместите скрипт в директории плагина и запускайте его оттуда.

## Первоначальная настройка на сервере

Перед использованием скриптов необходимо:

1. **Удалить вложенный репозиторий Git** (если он есть):
```bash
cd /var/www/www-root/data/www/site.dekan.pro/wp-content/plugins/course-plugin
rm -rf .git
```

2. **Удалить вложенную директорию course-plugin** (если она есть):
```bash
cd /var/www/www-root/data/www/site.dekan.pro/wp-content/plugins/course-plugin
rm -rf course-plugin/
```

3. **Настроить SSH ключи для доступа к GitHub** (если еще не настроено):
```bash
# На сервере
ssh-keygen -t ed25519 -C "server@site.dekan.pro"
cat ~/.ssh/id_ed25519.pub
# Добавьте публичный ключ в GitHub: Settings -> SSH and GPG keys
```

## Автоматическое обновление (опционально)

Для автоматического обновления можно настроить cron:

```bash
# Редактируем crontab
crontab -e

# Добавляем задачу (обновление каждый день в 3:00)
0 3 * * * /var/www/www-root/data/www/site.dekan.pro/wp-content/plugins/course-plugin/update-from-github.sh >> /var/log/course-plugin-update.log 2>&1
```

## Структура после настройки

После правильной настройки структура должна быть:

```
/var/www/www-root/data/www/site.dekan.pro/wp-content/plugins/course-plugin/
├── course-plugin.php
├── includes/
├── assets/
├── templates/
├── README.md
└── ... (другие файлы плагина)
```

**БЕЗ** вложенной директории `course-plugin/` и **БЕЗ** директории `.git/`.

## Проверка

После обновления проверьте:

1. Структуру каталогов:
```bash
ls -la /var/www/www-root/data/www/site.dekan.pro/wp-content/plugins/course-plugin/
```

2. Работу плагина в WordPress админ-панели

3. Логи ошибок (если есть):
```bash
tail -f /var/www/www-root/data/www/site.dekan.pro/wp-content/debug.log
```

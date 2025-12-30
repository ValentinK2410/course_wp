# Исправление ошибки "SSO не настроен"

## Проблема

При переходе по ссылке `https://class.dekan.pro/moodle-sso-to-wordpress.php` появляется сообщение:
> "SSO не настроен. Обратитесь к администратору."

## Причина

В файле `moodle-sso-to-wordpress.php` на сервере не заполнен Moodle SSO API Key (осталось значение по умолчанию `'ВАШ_MOODLE_SSO_API_KEY'`).

## Решение

### Шаг 1: Получить Moodle SSO API Key из WordPress

1. Войдите в админку WordPress: `https://site.dekan.pro/wp-admin`
2. Перейдите: **Настройки → Moodle Sync**
3. Найдите раздел **"Настройки Single Sign-On (SSO)"**
4. Найдите поле **"Moodle SSO API Key"**
   - Если поле пустое → нажмите **"Сохранить изменения"** (ключ сгенерируется автоматически)
   - **Скопируйте значение ключа**

### Шаг 2: Обновить файл на сервере Moodle

#### Вариант A: Через SSH (быстро)

```bash
cd /var/www/www-root/data/www/class.dekan.pro
sed -i "s/\$moodle_sso_api_key = 'ВАШ_MOODLE_SSO_API_KEY';/\$moodle_sso_api_key = 'YOUR_KEY';/" moodle-sso-to-wordpress.php
```

#### Вариант B: Через FileZilla

1. Откройте файл `moodle-sso-to-wordpress.php` на сервере
2. Найдите строку 30 и замените `'ВАШ_MOODLE_SSO_API_KEY'` на ваш ключ
3. Сохраните файл

#### Вариант C: Использовать скрипт

```bash
./update-moodle-sso-config.sh YOUR_KEY
```

## Проверка

После обновления откройте: `https://class.dekan.pro/moodle-sso-to-wordpress.php`
Должно произойти автоматическое перенаправление в WordPress.

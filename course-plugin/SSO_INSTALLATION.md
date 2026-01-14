# Инструкция по установке SSO файлов на сервер Moodle

## Файл sso-login.php (WordPress → Moodle)

Этот файл позволяет пользователям автоматически входить в Moodle из WordPress без ввода пароля.

### Установка

1. **Скопируйте файл на сервер Moodle:**
   ```bash
   # На сервере Moodle
   cd /var/www/www-root/data/www/class.russianseminary.org
   wget https://raw.githubusercontent.com/ValentinK2410/course_wp/master/course-plugin/sso-login.php
   ```

2. **Настройте файл:**
   Откройте файл `sso-login.php` и укажите правильные значения:
   ```php
   $wordpress_url = 'https://mbs.russianseminary.org'; // URL вашего WordPress сайта
   $sso_api_key = ''; // Ключ из WordPress (если используется)
   ```

3. **Установите права доступа:**
   ```bash
   chmod 644 sso-login.php
   chown www-data:www-data sso-login.php
   ```

4. **Проверьте работу:**
   - Войдите в WordPress
   - Нажмите кнопку "Перейти в Moodle" в верхней панели WordPress
   - Должен произойти автоматический вход в Moodle

## Файл moodle-sso-to-wordpress.php (Moodle → WordPress)

Этот файл позволяет пользователям автоматически входить в WordPress из Moodle без ввода пароля.

### Установка

1. **Скопируйте файл на сервер Moodle:**
   ```bash
   # На сервере Moodle
   cd /var/www/www-root/data/www/class.russianseminary.org
   wget https://raw.githubusercontent.com/ValentinK2410/course_wp/master/moodle-sso-to-wordpress.php
   ```

2. **Настройте файл:**
   Откройте файл `moodle-sso-to-wordpress.php` и укажите правильные значения:
   ```php
   $wordpress_url = 'https://mbs.russianseminary.org'; // URL вашего WordPress сайта
   $moodle_sso_api_key = 'ВАШ_КЛЮЧ'; // Ключ из WordPress: Настройки → Moodle Sync → Moodle SSO API Key
   ```

3. **Получите Moodle SSO API Key:**
   - Войдите в WordPress админку
   - Перейдите в Настройки → Moodle Sync
   - Найдите поле "Moodle SSO API Key"
   - Скопируйте ключ и вставьте в файл `moodle-sso-to-wordpress.php`

4. **Установите права доступа:**
   ```bash
   chmod 644 moodle-sso-to-wordpress.php
   chown www-data:www-data moodle-sso-to-wordpress.php
   ```

5. **Проверьте работу:**
   - Войдите в Moodle
   - Перейдите по ссылке: `https://class.russianseminary.org/moodle-sso-to-wordpress.php`
   - Должен произойти автоматический вход в WordPress

## Важные замечания

1. **Безопасность:**
   - Файлы должны быть доступны только для чтения веб-сервером
   - Не храните API ключи в публичных репозиториях
   - Регулярно обновляйте API ключи

2. **Проверка настроек:**
   - Убедитесь, что `config.php` Moodle находится в правильном месте
   - Проверьте, что пути к файлам корректны
   - Убедитесь, что пользователи синхронизированы между WordPress и Moodle

3. **Отладка:**
   - Проверьте логи Moodle: `/var/www/www-root/data/www/class.russianseminary.org/moodledata/error.log`
   - Проверьте логи WordPress: `/var/www/www-root/data/www/mbs.russianseminary.org/wp-content/debug.log`
   - Включите `WP_DEBUG` в WordPress для подробных логов

## Устранение проблем

### Ошибка 404 "Не найдено"
- Убедитесь, что файл `sso-login.php` находится в корневой директории Moodle
- Проверьте права доступа к файлу
- Проверьте настройки веб-сервера (Apache/Nginx)

### Ошибка "Токен недействителен"
- Проверьте, что токен не истек (действителен 1 час)
- Убедитесь, что SSO API Key настроен правильно
- Проверьте, что пользователь существует в обеих системах

### Ошибка "Пользователь не найден"
- Убедитесь, что пользователь синхронизирован между WordPress и Moodle
- Проверьте, что email пользователя совпадает в обеих системах
- Убедитесь, что пользователь не удален в Moodle

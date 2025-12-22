# Диагностика проблемы с отправкой писем при регистрации

## Проблема
При регистрации нового пользователя не отправляется письмо с подтверждением и паролем.

## Шаги диагностики

### 1. Проверка логов

Проверьте файл `wp-content/course-registration-debug.log` на наличие записей об отправке письма:

```bash
tail -f wp-content/course-registration-debug.log | grep -i "письм\|email\|mail"
```

Ищите записи:
- `========== ОТПРАВКА ПИСЬМА ==========`
- `УСПЕХ:` или `ОШИБКА:`
- `PHPMailer ошибка:`

### 2. Проверка настроек WordPress

Убедитесь, что в WordPress настроен email отправителя:

1. Войдите в админку: `https://site.dekan.pro/wp-admin`
2. Перейдите в **Настройки → Общие**
3. Проверьте поле **"E-mail адрес"** - должен быть указан корректный email

### 3. Проверка функции wp_mail()

Проверьте, работает ли функция `wp_mail()` в WordPress:

```php
// Добавьте в functions.php темы или через WP-CLI
wp_mail('test@example.com', 'Test', 'Test message');
```

Если письмо не приходит, проблема в настройке отправки писем на сервере.

### 4. Настройка SMTP (если письма не отправляются)

Если на сервере не настроена отправка писем через PHP `mail()`, нужно установить SMTP плагин:

**Рекомендуемые плагины:**
- **WP Mail SMTP** (бесплатный)
- **Easy WP SMTP**
- **Post SMTP**

**Настройка WP Mail SMTP:**
1. Установите плагин **WP Mail SMTP**
2. Перейдите в **Настройки → Email**
3. Выберите SMTP провайдера (Gmail, Mail.ru, Yandex, или другой)
4. Заполните настройки SMTP:
   - SMTP Host
   - SMTP Port
   - Encryption (SSL/TLS)
   - SMTP Username
   - SMTP Password
5. Сохраните настройки
6. Отправьте тестовое письмо

### 5. Проверка настройки сервера

Если используется стандартная функция PHP `mail()`, проверьте:

```bash
# Проверьте, установлен ли sendmail/postfix
which sendmail
which postfix

# Проверьте логи почтового сервера
tail -f /var/log/mail.log
# или
tail -f /var/log/maillog
```

### 6. Альтернативное решение - использование внешнего SMTP

Если на сервере нет настроенного почтового сервера, используйте внешний SMTP:

**Пример для Gmail:**
```php
// Добавьте в wp-config.php или через плагин
define('SMTP_HOST', 'smtp.gmail.com');
define('SMTP_PORT', 587);
define('SMTP_USERNAME', 'your-email@gmail.com');
define('SMTP_PASSWORD', 'your-app-password');
define('SMTP_ENCRYPTION', 'tls');
```

### 7. Проверка спам-фильтров

Проверьте папку "Спам" в почтовом ящике пользователя. Письма могут попадать туда.

### 8. Проверка DNS записей

Убедитесь, что для домена настроены SPF и DKIM записи:

```bash
# Проверка SPF записи
dig TXT site.dekan.pro | grep spf

# Проверка DKIM записи
dig TXT default._domainkey.site.dekan.pro
```

### 9. Тестирование отправки письма вручную

Создайте тестовый файл `test-email.php` в корне WordPress:

```php
<?php
require_once('wp-load.php');

$to = 'your-email@example.com';
$subject = 'Test Email';
$message = 'This is a test email from WordPress.';
$headers = array('Content-Type: text/html; charset=UTF-8');

$result = wp_mail($to, $subject, $message, $headers);

if ($result) {
    echo 'Email sent successfully!';
} else {
    echo 'Email failed to send.';
    global $phpmailer;
    if (isset($phpmailer) && isset($phpmailer->ErrorInfo)) {
        echo 'Error: ' . $phpmailer->ErrorInfo;
    }
}
```

Откройте файл в браузере: `https://site.dekan.pro/test-email.php`

**ВАЖНО:** Удалите файл после тестирования!

### 10. Проверка логов WordPress

Проверьте стандартный лог WordPress (если включен):

```bash
tail -f wp-content/debug.log | grep -i mail
```

## Быстрое решение

Если нужно быстро решить проблему, установите плагин **WP Mail SMTP**:

1. Установите плагин через админку WordPress
2. Настройте SMTP (можно использовать Gmail, Mail.ru, Yandex)
3. Отправьте тестовое письмо
4. Попробуйте зарегистрировать нового пользователя

## Что прислать для диагностики

1. Последние 50 строк из `wp-content/course-registration-debug.log`
2. Результат выполнения `wp_mail()` теста
3. Настройки SMTP (если используются)
4. Логи почтового сервера (если доступны)


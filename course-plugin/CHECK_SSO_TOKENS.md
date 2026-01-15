# Проверка генерации токенов из Moodle

## Проблема
Кнопки SSO не появляются в Moodle, потому что токены не генерируются.

## Диагностика

### 1. Проверьте логи WordPress для endpoint get_sso_tokens_from_moodle

```bash
tail -100 /var/www/www-root/data/www/mbs.russianseminary.org/wp-content/debug.log | grep -i "get_sso_tokens_from_moodle\|Запрос токенов из Moodle\|User not found"
```

Должны быть записи:
- `Course SSO: Запрос токенов из Moodle. Email: ...`
- `Course SSO: Пользователь найден по email` или `Course SSO: Пользователь не найден`

### 2. Проверьте логи Moodle

```bash
tail -50 /var/www/www-root/data/www/moodledata/error.log | grep "Moodle SSO Buttons"
```

Должны быть записи:
- `Пользователь Moodle - Email: ...`
- `HTTP код ответа от WordPress: ...`
- `Токены успешно получены` или `Ошибка получения токенов`

### 3. Проверьте, что пользователь существует в WordPress

В WordPress проверьте:
- Пользователи → Все пользователи
- Найдите пользователя с email `sashaspichak@gmail.com`
- Убедитесь, что email точно совпадает (регистр, пробелы)

### 4. Проверьте прямой запрос к WordPress API

Выполните на сервере Moodle (замените EMAIL на реальный email пользователя):

```bash
curl "https://mbs.russianseminary.org/wp-admin/admin-ajax.php?action=get_sso_tokens_from_moodle&email=sashaspichak@gmail.com&moodle_user_id=3"
```

Должен вернуться JSON с токенами или сообщение об ошибке.

## Решение

Если пользователь не найден в WordPress:

1. **Создайте пользователя в WordPress** с тем же email, что и в Moodle
2. **Или синхронизируйте пользователей** из Moodle в WordPress (если есть такая функция)

Если пользователь найден, но токены не генерируются:

1. Проверьте логи WordPress на ошибки генерации токенов
2. Убедитесь, что функция `generate_token()` работает правильно

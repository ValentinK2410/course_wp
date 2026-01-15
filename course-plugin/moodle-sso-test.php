<?php
/**
 * Тестовый файл для проверки работы скриптов в Moodle
 * Этот файл просто выводит alert, чтобы проверить, загружается ли скрипт вообще
 */

header('Content-Type: application/javascript; charset=utf-8');
?>
alert('MOODLE SSO TEST: Скрипт загружен!');
console.log('MOODLE SSO TEST: Скрипт выполнен в ' + new Date().toLocaleString());

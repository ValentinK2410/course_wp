<?php
$_SERVER['HTTP_HOST']    = 'mbs.russianseminary.org';
$_SERVER['REQUEST_URI']  = '/';
$_SERVER['SERVER_NAME']  = 'mbs.russianseminary.org';
$_SERVER['HTTPS']        = 'on';
$_SERVER['REQUEST_METHOD'] = 'GET';

require_once __DIR__ . '/wp-load.php';

echo "WP_DEBUG: " . (defined('WP_DEBUG') && WP_DEBUG ? 'ON' : 'OFF') . "\n";
echo "WP_DEBUG_LOG: " . (defined('WP_DEBUG_LOG') ? var_export(WP_DEBUG_LOG, true) : 'NOT SET') . "\n\n";

$u = get_user_by('login', 'valentink2410');
if (!$u) {
    echo "User valentink2410 NOT FOUND\n";
    exit;
}

echo "user_id: " . $u->ID . "\n";
echo "email: " . $u->user_email . "\n";
echo "moodle_user_id: " . get_user_meta($u->ID, 'moodle_user_id', true) . "\n";
echo "email_confirmed: " . get_user_meta($u->ID, 'email_confirmed', true) . "\n";
echo "Moodle sync enabled: " . get_option('moodle_sync_users_enabled', 'not set') . "\n";
echo "Moodle URL: " . get_option('moodle_sync_url', '') . "\n";
echo "Moodle token set: " . (get_option('moodle_sync_token', '') ? 'YES' : 'NO') . "\n";

echo "\n=== Sync ===\n";
if (class_exists('Course_Moodle_User_Sync')) {
    $sync = Course_Moodle_User_Sync::get_instance();
    $result = $sync->sync_user($u->ID, 'TestPass1-');
    echo "sync_user result: " . var_export($result, true) . "\n";
    echo "moodle_user_id after: " . get_user_meta($u->ID, 'moodle_user_id', true) . "\n";
} else {
    echo "Course_Moodle_User_Sync NOT FOUND\n";
}

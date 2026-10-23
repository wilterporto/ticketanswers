<?php
$_SERVER['REQUEST_URI'] = '/plugins/ticketanswers/ajax/check_all_notifications.php';
$_SERVER['SCRIPT_NAME'] = '/index.php';
$_SERVER['REQUEST_METHOD'] = 'GET';
$_SERVER['HTTP_HOST'] = 'glpi.local';
$_SERVER['DOCUMENT_ROOT'] = 'c:\\wamp64\\www\\glpi11\\public';

ini_set('display_errors', 1);
error_reporting(E_ALL);

try {
    require 'c:\\wamp64\\www\\glpi11\\public\\index.php';
} catch (\Throwable $e) {
    echo "CAUGHT EXCEPTION: " . $e->getMessage() . "\n";
    echo $e->getTraceAsString();
}

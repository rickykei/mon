<?php
 
require_once __DIR__ . '/Monitor.php';

header('Content-Type: application/json; charset=utf-8');

$monitor = new WebsiteMonitor();

try {
    $monitor->log("AJAX 自動檢測觸發");
    $sites = $monitor->checkAll();
    $last  = $monitor->getLastResults();

    echo json_encode(array(
        'success'    => true,
        'checked_at' => $last['checked_at'],
        'sites'      => $sites,
        'logs'       => $monitor->getRecentLogs(20),
    ));
} catch (Exception $e) {
    echo json_encode(array(
        'success' => false,
        'error'   => $e->getMessage(),
    ));
}

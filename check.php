<?php
// 定時檢測腳本（給 cron 任務使用）
// 使用方式：php check.php
// 建議每 5 分鐘執行一次，cron 表達式：分鐘欄位填 */5，後面接 * * * * php /你的路徑/website-monitor/check.php
// 注意：本檔開頭不可使用 /* ... */ 區塊註解，因為 cron 表達式裡的「星號加斜線」會提早結束註解，造成 Parse error。
require_once __DIR__ . '/Monitor.php';

$monitor = new WebsiteMonitor();
$monitor->log("=== 開始定時檢測 ===");
$results = $monitor->checkAll();

$online = 0;
$offline = 0;
foreach ($results as $r) {
    if ($r['online']) $online++;
    else $offline++;
}

$monitor->log("=== 檢測完成：{$online} 個在線，{$offline} 個離線 ===");
echo "檢測完成：{$online} 個在線，{$offline} 個離線\n";

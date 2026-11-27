<?php
/**
 * WhatsApp 通知測試頁
 */
require_once __DIR__ . '/Monitor.php';

$monitor = new WebsiteMonitor();
$cfg = $monitor->config['whatsapp'];
$checks = array();
$sendResult = null;
$sendResponse = '';

// 配置檢查
$checks['enabled']      = array('name' => 'WhatsApp 通知已啟用', 'ok' => !empty($cfg['enabled']), 'detail' => $cfg['enabled'] ? 'enabled=true' : '請將 config.php 中 whatsapp.enabled 設為 true');
$checks['provider']     = array('name' => '服務商已選擇', 'ok' => !empty($cfg['provider']), 'detail' => 'provider=' . $cfg['provider']);
$checks['curl']         = array('name' => 'PHP cURL 擴展可用', 'ok' => function_exists('curl_init'), 'detail' => function_exists('curl_init') ? 'curl 已安裝' : '請聯絡主機商啟用 cURL');
$checks['timezone']     = array('name' => '香港時區已設定', 'ok' => date_default_timezone_get() === 'Asia/Hong_Kong', 'detail' => date_default_timezone_get());

if ($cfg['provider'] === 'twilio') {
    $fromOk = !empty($cfg['from']);
    $toOk   = !empty($cfg['to']);
    $prefixOk = (strpos($cfg['from'], 'whatsapp:') === 0) && (strpos($cfg['to'], 'whatsapp:') === 0);
    $checks['twilio_sid']   = array('name' => 'Twilio Account SID 已填寫', 'ok' => !empty($cfg['account_sid']), 'detail' => $cfg['account_sid'] ? 'SID 已填寫' : '缺少 account_sid');
    $checks['twilio_token'] = array('name' => 'Twilio Auth Token 已填寫', 'ok' => !empty($cfg['auth_token']), 'detail' => $cfg['auth_token'] ? 'Token 已填寫' : '缺少 auth_token');
    $checks['twilio_from']  = array('name' => 'From 號碼格式正確（需加 whatsapp: 前綴）', 'ok' => $fromOk && strpos($cfg['from'], 'whatsapp:') === 0, 'detail' => 'From=' . ($cfg['from'] ?: '（未填）'));
    $checks['twilio_to']    = array('name' => 'To 手機號碼格式正確（需加 whatsapp: 前綴）', 'ok' => $toOk && strpos($cfg['to'], 'whatsapp:') === 0, 'detail' => 'To=' . ($cfg['to'] ?: '（未填）'));
}

if ($cfg['provider'] === 'ultramsg') {
    $checks['ultra_inst'] = array('name' => 'Ultramsg Instance ID 已填寫', 'ok' => !empty($cfg['instance_id']), 'detail' => $cfg['instance_id']);
    $checks['ultra_token']= array('name' => 'Ultramsg Token 已填寫', 'ok' => !empty($cfg['token']), 'detail' => $cfg['token'] ? 'Token 已填寫' : '缺少 token');
    $checks['ultra_to']   = array('name' => 'To 手機號碼已填寫（國際格式 +852...）', 'ok' => !empty($cfg['to']), 'detail' => 'To=' . ($cfg['to'] ?: '（未填）'));
}

if ($cfg['provider'] === 'callmebot') {
    $checks['cmb_phone'] = array('name' => 'CallMeBot 手機號碼已填寫', 'ok' => !empty($cfg['callmebot_phone']), 'detail' => $cfg['callmebot_phone'] ?: '缺少 callmebot_phone，格式 +852...');
    $checks['cmb_api']   = array('name' => 'CallMeBot API Key 已填寫', 'ok' => !empty($cfg['callmebot_api']), 'detail' => $cfg['callmebot_api'] ? 'API Key 已填寫' : '缺少 callmebot_api');
}

if ($cfg['provider'] === 'greenapi') {
    $checks['green_id'] = array('name' => 'Green API Instance ID 已填寫', 'ok' => !empty($cfg['greenapi_id']), 'detail' => $cfg['greenapi_id'] ?: '缺少 greenapi_id');
    $checks['green_token'] = array('name' => 'Green API Token 已填寫', 'ok' => !empty($cfg['greenapi_token']), 'detail' => $cfg['greenapi_token'] ? 'Token 已填寫' : '缺少 greenapi_token');
    $checks['green_to'] = array('name' => '收件人手機號碼已填寫（純數字，例如85254864677）', 'ok' => !empty($cfg['to']), 'detail' => 'To=' . ($cfg['to'] ?: '（未填）'));
}

if ($cfg['provider'] === 'telegram') {
    $checks['tg_token'] = array('name' => 'Telegram Bot Token 已填寫', 'ok' => !empty($cfg['telegram_bot_token']), 'detail' => $cfg['telegram_bot_token'] ?: '缺少 telegram_bot_token');
    $checks['tg_chatid'] = array('name' => 'Telegram Chat ID 已填寫', 'ok' => !empty($cfg['telegram_chat_id']), 'detail' => $cfg['telegram_chat_id'] ?: '缺少 telegram_chat_id');
}

// 發送測試
if (isset($_POST['send_test'])) {
    // 透過反射呼叫 sendWhatsApp
    $ref = new ReflectionMethod('WebsiteMonitor', 'sendWhatsApp');
    $ref->setAccessible(true);

    ob_start();
    $ok = $ref->invoke($monitor, "🧪 這是一條來自網站監控系統的測試訊息\n時間: " . date('Y-m-d H:i:s') . " HKT");
    $sendResponse = ob_get_clean();
    $sendResult = $ok;
}
?>
<!DOCTYPE html>
<html lang="zh-Hant">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>WhatsApp 通知測試</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: linear-gradient(135deg, #1a1a2e 0%, #16213e 100%);
            min-height: 100vh;
            color: #fff;
            padding: 30px 20px;
        }
        .container { max-width: 720px; margin: 0 auto; }
        h1 { font-size: 1.8em; margin-bottom: 8px; text-align: center; background: linear-gradient(90deg,#00d4ff,#00ff88); -webkit-background-clip:text; -webkit-text-fill-color:transparent; }
        .sub { text-align:center; color:#8892b0; margin-bottom:24px; font-size:0.95em; }
        .card { background:rgba(255,255,255,0.05); border:1px solid rgba(255,255,255,0.1); border-radius:12px; padding:22px; margin-bottom:20px; backdrop-filter:blur(10px); }
        .card h2 { font-size:1.15em; margin-bottom:14px; color:#00d4ff; }
        .check-row { display:flex; justify-content:space-between; align-items:center; padding:8px 0; border-bottom:1px solid rgba(255,255,255,0.05); font-size:0.95em; }
        .check-row:last-child { border-bottom:none; }
        .ok { color:#00ff88; font-weight:600; }
        .fail { color:#ff4757; font-weight:600; }
        .detail { color:#8892b0; font-size:0.85em; margin-top:3px; }
        .btn { display:inline-block; padding:12px 28px; border-radius:8px; text-decoration:none; font-weight:600; border:none; cursor:pointer; font-size:1em; color:#fff; background:linear-gradient(90deg,#25d366,#128c7e); margin-top:10px; }
        .btn:hover { transform:translateY(-2px); box-shadow:0 5px 20px rgba(37,211,102,0.3); }
        .btn-back { background:rgba(255,255,255,0.1); margin-left:10px; }
        .result { margin-top:16px; padding:14px; border-radius:8px; font-size:0.95em; }
        .result.success { background:rgba(0,255,136,0.1); border:1px solid #00ff88; color:#00ff88; }
        .result.error { background:rgba(255,71,87,0.1); border:1px solid #ff4757; color:#ff4757; }
        .tips { color:#a8b2d1; font-size:0.9em; line-height:1.7; }
        .tips li { margin-bottom:8px; }
        .tips code { background:rgba(255,255,255,0.08); padding:2px 6px; border-radius:4px; font-size:0.9em; }
        @media (max-width:600px){ h1{font-size:1.4em;} }
    </style>
</head>
<body>
    <div class="container">
        <h1>📱 WhatsApp 通知測試</h1>
        <p class="sub">請先確認所有配置檢查項目都為 ✅ 通過，再發送測試訊息</p>

        <div class="card">
            <h2>🔍 配置檢查</h2>
            <?php $allOk = true; ?>
            <?php foreach ($checks as $c): ?>
                <div class="check-row">
                    <div>
                        <div><?php echo htmlspecialchars($c['name']); ?></div>
                        <div class="detail"><?php echo htmlspecialchars($c['detail']); ?></div>
                    </div>
                    <div class="<?php echo $c['ok'] ? 'ok' : 'fail'; ?>"><?php echo $c['ok'] ? '✅ 通過' : '❌ 失敗'; ?></div>
                </div>
                <?php if (!$c['ok']) $allOk = false; ?>
            <?php endforeach; ?>
        </div>

        <div class="card" style="text-align:center;">
            <h2 style="text-align:left;">📤 發送測試訊息</h2>
            <form method="post">
                <button type="submit" name="send_test" class="btn" <?php echo $allOk ? '' : 'disabled style="opacity:0.5;cursor:not-allowed;"'; ?>>
                    發送測試WhatsApp訊息
                </button>
                <a href="index.php" class="btn btn-back">← 返回儀表板</a>
            </form>
            <?php if ($sendResult !== null): ?>
                <div class="result <?php echo $sendResult ? 'success' : 'error'; ?>">
                    <?php if ($sendResult): ?>
                        ✅ 發送成功！請查看你的WhatsApp是否收到測試訊息。
                    <?php else: ?>
                        ❌ 發送失敗，請查看 logs/monitor.log 取得詳細錯誤原因。
                        <?php if ($sendResponse): ?>
                            <div style="margin-top:10px; font-size:0.85em; color:#ffcccc;">回應：<?php echo htmlspecialchars($sendResponse); ?></div>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>

        <div class="card">
            <h2>💡 常見問題排除</h2>
            <ul class="tips">
                <?php if ($cfg['provider'] === 'twilio'): ?>
                <li>Twilio 免費沙箱：你必須先用手機 WhatsApp 發送 <code>join 你的關鍵字</code> 到 Twilio 的沙箱號碼，才能收到訊息。</li>
                <li>沙箱關鍵字可在 Twilio 控制台 → WhatsApp → Sandbox 頁面找到（例如 <code>join coming-finely</code>）。</li>
                <li>號碼必須加 <code>whatsapp:</code> 前綴：From 例如 <code>whatsapp:+14155238886</code>，To 例如 <code>whatsapp:+85254864677</code>。</li>
                <li>如果提示 ContentSid 錯誤，代表需要升級帳號並建立內容範本。</li>
                <?php endif; ?>
                <?php if ($cfg['provider'] === 'ultramsg'): ?>
                <li>請確認 Ultramsg 後台的 Instance 狀態是「Connected」。</li>
                <li>To 號碼格式為國際格式，例如香港：<code>+85291234567</code>，不要加前面的 0。</li>
                <li>免費版每日有發送額度限制，超過會發送失敗。</li>
                <?php endif; ?>
                <?php if ($cfg['provider'] === 'callmebot'): ?>
                <li>CallMeBot 啟用：先新增聯絡人 +34 644 61 91 98，發送 <code>I allow callmebot to send me messages</code> 取得 API Key。</li>
                <li>如果無法打開聊天窗口，可以點<a href="https://wa.me/34644619198" target="_blank">這裡直接打開WhatsApp</a>。</li>
                <?php endif; ?>
                <?php if ($cfg['provider'] === 'greenapi'): ?>
                <li><strong>設定步驟：</strong>
                    <ol style="margin-left:20px; margin-top:5px;">
                        <li>到 <a href="https://green-api.com/" target="_blank" style="color:#00d4ff;">green-api.com</a> 註冊免費帳號</li>
                        <li>建立 Developer 免費實例，取得 idInstance 和 apiTokenInstance</li>
                        <li>掃描頁面QR code綁定你的WhatsApp（就像綁定WhatsApp Web一樣）</li>
                        <li>填寫config.php中的 greenapi_id 和 greenapi_token，to 欄位填純數字手機號碼例如 85254864677</li>
                    </ol>
                </li>
                <li>免費額度：每天100條訊息，足夠監控使用，不需要加機器人好友。</li>
                <?php endif; ?>
                <?php if ($cfg['provider'] === 'telegram'): ?>
                <li><strong>設定步驟：</strong>
                    <ol style="margin-left:20px; margin-top:5px;">
                        <li>Telegram搜尋 @BotFather，發送 /newbot 建立機器人取得Token</li>
                        <li>搜尋 @getidsbot，發送 /start 取得你的Chat ID</li>
                        <li>填入config即可使用，100%送達，無發送限制。</li>
                    </ol>
                </li>
                <?php endif; ?>
                <li>發送失敗時可查看 <code>logs/monitor.log</code>，最後會記錄 API 回應的錯誤原因。</li>
            </ul>
        </div>
    </div>
</body>
</html>

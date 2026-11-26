<?php
/**
 * 網站監控儀表板
 */
require_once __DIR__ . '/Monitor.php';

$monitor = new WebsiteMonitor();
$message = '';
$messageType = '';

// 手動立即檢測
if (isset($_GET['action']) && $_GET['action'] === 'check') {
    $monitor->log("手動觸發檢測");
    $monitor->checkAll();
    $message = "✅ 已完成所有網站檢測！";
    $messageType = 'success';
}

$data = $monitor->getLastResults();
$sites = $data ? $data['sites'] : array();
$lastCheck = $data ? $data['checked_at'] : '從未檢測';

$total = count($sites);
$onlineCount = 0;
$offlineCount = 0;
foreach ($sites as $s) {
    if ($s['online']) $onlineCount++;
    else $offlineCount++;
}

$logs = $monitor->getRecentLogs(20);
$refreshInterval = $monitor->config['settings']['check_interval'] * 60;
?>
<!DOCTYPE html>
<html lang="zh-Hant">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>網站監控儀表板</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: linear-gradient(135deg, #1a1a2e 0%, #16213e 100%);
            min-height: 100vh;
            color: #fff;
            padding: 20px;
        }
        .container { max-width: 1200px; margin: 0 auto; }
        .header {
            text-align: center;
            margin-bottom: 30px;
            padding: 20px;
        }
        .header h1 {
            font-size: 2.2em;
            margin-bottom: 10px;
            background: linear-gradient(90deg, #00d4ff, #00ff88);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }
        .header p { color: #8892b0; font-size: 0.95em; }

        .stats-bar {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 15px;
            margin-bottom: 25px;
        }
        .stat-card {
            background: rgba(255,255,255,0.05);
            backdrop-filter: blur(10px);
            border-radius: 12px;
            padding: 20px;
            text-align: center;
            border: 1px solid rgba(255,255,255,0.1);
        }
        .stat-value { font-size: 2em; font-weight: bold; margin-bottom: 5px; }
        .stat-label { color: #8892b0; font-size: 0.9em; }
        .stat-online .stat-value { color: #00ff88; }
        .stat-offline .stat-value { color: #ff4757; }
        .stat-total .stat-value { color: #00d4ff; }

        .actions {
            margin-bottom: 25px;
            text-align: center;
        }
        .btn {
            display: inline-block;
            padding: 12px 28px;
            border-radius: 8px;
            text-decoration: none;
            font-weight: 600;
            margin: 0 8px;
            transition: all 0.3s;
            border: none;
            cursor: pointer;
            font-size: 1em;
            color: #fff;
        }
        .btn-primary {
            background: linear-gradient(90deg, #00d4ff, #00a8ff);
        }
        .btn-primary:hover { transform: translateY(-2px); box-shadow: 0 5px 20px rgba(0,212,255,0.3); }
        .btn-primary:disabled { opacity: 0.6; cursor: not-allowed; transform: none; }
        .btn-secondary {
            background: rgba(255,255,255,0.1);
        }
        .btn-secondary:hover { background: rgba(255,255,255,0.2); }

        .message {
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            text-align: center;
            display: none;
        }
        .message.show { display: block; }
        .message.success { background: rgba(0,255,136,0.1); border: 1px solid #00ff88; color: #00ff88; }
        .message.error { background: rgba(255,71,87,0.1); border: 1px solid #ff4757; color: #ff4757; }

        .sites-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(350px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        .site-card {
            background: rgba(255,255,255,0.05);
            backdrop-filter: blur(10px);
            border-radius: 12px;
            padding: 22px;
            border: 1px solid rgba(255,255,255,0.1);
            transition: all 0.3s;
        }
        .site-card:hover { transform: translateY(-3px); border-color: rgba(255,255,255,0.2); }
        .site-card.online { border-left: 4px solid #00ff88; }
        .site-card.offline { border-left: 4px solid #ff4757; background: rgba(255,71,87,0.05); }

        .site-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
        }
        .site-name { font-size: 1.3em; font-weight: 600; }
        .status-badge {
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 0.85em;
            font-weight: 600;
        }
        .status-online { background: rgba(0,255,136,0.15); color: #00ff88; }
        .status-offline { background: rgba(255,71,87,0.15); color: #ff4757; }

        .site-info { margin-bottom: 12px; }
        .info-row {
            display: flex;
            justify-content: space-between;
            padding: 6px 0;
            font-size: 0.9em;
            border-bottom: 1px solid rgba(255,255,255,0.05);
        }
        .info-label { color: #8892b0; }
        .info-value { font-weight: 500; }
        .site-url {
            font-size: 0.85em;
            color: #00d4ff;
            word-break: break-all;
            margin-top: 10px;
        }
        .site-url a { color: #00d4ff; text-decoration: none; }
        .site-url a:hover { text-decoration: underline; }
        .error-msg { color: #ff4757; font-size: 0.85em; margin-top: 8px; }

        .logs-section {
            background: rgba(255,255,255,0.05);
            backdrop-filter: blur(10px);
            border-radius: 12px;
            padding: 22px;
            border: 1px solid rgba(255,255,255,0.1);
        }
        .logs-section h2 { margin-bottom: 15px; font-size: 1.2em; color: #00d4ff; }
        .log-line {
            font-family: 'Courier New', monospace;
            font-size: 0.85em;
            padding: 5px 0;
            border-bottom: 1px solid rgba(255,255,255,0.05);
            color: #a8b2d1;
        }
        .log-line.OK { color: #00ff88; }
        .log-line.DOWN { color: #ff4757; }

        .last-check {
            text-align: center;
            color: #8892b0;
            margin-top: 20px;
            font-size: 0.9em;
        }

        .countdown {
            color: #00d4ff;
            font-weight: 600;
        }
        .checking {
            color: #ffaa00;
            animation: pulse 1s infinite;
        }
        @keyframes pulse {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.5; }
        }

        @media (max-width: 768px) {
            .sites-grid { grid-template-columns: 1fr; }
            .header h1 { font-size: 1.6em; }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>🖥️ 網站監控儀表板</h1>
            <p>即時監控您的所有網站狀態，故障時自動發送WhatsApp通知（通知服務：<?php echo htmlspecialchars($monitor->getProviderName()); ?>）</p>
        </div>

        <div id="message" class="message <?php echo $messageType; ?> <?php echo $message ? 'show' : ''; ?>"><?php echo $message; ?></div>

        <div class="stats-bar">
            <div class="stat-card stat-total">
                <div class="stat-value" id="stat-total"><?php echo $total; ?></div>
                <div class="stat-label">監控網站總數</div>
            </div>
            <div class="stat-card stat-online">
                <div class="stat-value" id="stat-online"><?php echo $onlineCount; ?></div>
                <div class="stat-label">線上正常</div>
            </div>
            <div class="stat-card stat-offline">
                <div class="stat-value" id="stat-offline"><?php echo $offlineCount; ?></div>
                <div class="stat-label">離線故障</div>
            </div>
            <div class="stat-card">
                <div class="stat-value" id="countdown"><?php echo $monitor->config['settings']['check_interval']; ?>:00</div>
                <div class="stat-label">下次自動刷新</div>
            </div>
        </div>

        <div class="actions">
            <button id="btn-check" class="btn btn-primary">🔄 立即檢測所有網站</button>
            <a href="test_whatsapp.php" class="btn btn-secondary">📱 測試WhatsApp通知</a>
        </div>

        <div class="sites-grid" id="sites-grid">
            <?php if (empty($sites)): ?>
                <div style="grid-column: 1/-1; text-align: center; padding: 40px; color: #8892b0;">
                    尚未進行任何檢測，請點擊「立即檢測所有網站」按鈕開始
                </div>
            <?php else: ?>
                <?php foreach ($sites as $site): ?>
                    <div class="site-card <?php echo $site['online'] ? 'online' : 'offline'; ?>">
                        <div class="site-header">
                            <div class="site-name"><?php echo htmlspecialchars($site['name']); ?></div>
                            <span class="status-badge <?php echo $site['online'] ? 'status-online' : 'status-offline'; ?>">
                                <?php echo $site['online'] ? '🟢 在線' : '🔴 離線'; ?>
                            </span>
                        </div>
                        <div class="site-info">
                            <div class="info-row">
                                <span class="info-label">HTTP 狀態碼</span>
                                <span class="info-value"><?php echo $site['http_code'] ?: '無回應'; ?></span>
                            </div>
                            <div class="info-row">
                                <span class="info-label">響應時間</span>
                                <span class="info-value"><?php echo $site['response_ms']; ?> ms</span>
                            </div>
                            <div class="info-row">
                                <span class="info-label">檢測時間</span>
                                <span class="info-value"><?php echo $site['checked_at']; ?> HKT</span>
                            </div>
                        </div>
                        <div class="site-url">
                            🔗 <a href="<?php echo htmlspecialchars($site['url']); ?>" target="_blank"><?php echo htmlspecialchars(isset($site['real_url']) ? $site['real_url'] : $site['url']); ?></a>
                        </div>
                        <?php if (!$site['online'] && isset($site['error']) && $site['error']): ?>
                            <div class="error-msg">⚠️ <?php echo htmlspecialchars($site['error']); ?></div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <div class="logs-section" id="logs-section">
            <h2>📋 最近檢測日誌</h2>
            <div id="logs-list">
            <?php foreach ($logs as $log): ?>
                <?php
                $logClass = '';
                if (strpos($log, 'OK') !== false) $logClass = 'OK';
                if (strpos($log, 'DOWN') !== false) $logClass = 'DOWN';
                ?>
                <div class="log-line <?php echo $logClass; ?>"><?php echo htmlspecialchars(trim($log)); ?></div>
            <?php endforeach; ?>
            </div>
        </div>

        <div class="last-check">
            上次檢測: <strong id="last-check"><?php echo $lastCheck; ?> HKT</strong> | 時區: Asia/Hong_Kong
        </div>
    </div>

    <script>
        const refreshSeconds = <?php echo (int)$refreshInterval; ?>;
        let totalSeconds = refreshSeconds;
        const countdownEl = document.getElementById('countdown');
        const btnCheck = document.getElementById('btn-check');
        const msgEl = document.getElementById('message');

        function showMessage(text, type) {
            msgEl.textContent = text;
            msgEl.className = 'message show ' + type;
        }

        function renderSites(sites) {
            const grid = document.getElementById('sites-grid');
            if (!sites || sites.length === 0) {
                grid.innerHTML = '<div style="grid-column: 1/-1; text-align: center; padding: 40px; color: #8892b0;">尚未有檢測數據</div>';
                return;
            }
            let html = '';
            let online = 0, offline = 0;
            sites.forEach(function(s) {
                if (s.online) online++; else offline++;
                const cls = s.online ? 'online' : 'offline';
                const badgeCls = s.online ? 'status-online' : 'status-offline';
                const badgeText = s.online ? '🟢 在線' : '🔴 離線';
                const target = s.real_url || s.url;
                const errBlock = (!s.online && s.error) ? '<div class="error-msg">⚠️ ' + escapeHtml(s.error) + '</div>' : '';
                html += '<div class="site-card ' + cls + '">'
                    + '<div class="site-header">'
                    +   '<div class="site-name">' + escapeHtml(s.name) + '</div>'
                    +   '<span class="status-badge ' + badgeCls + '">' + badgeText + '</span>'
                    + '</div>'
                    + '<div class="site-info">'
                    +   '<div class="info-row"><span class="info-label">HTTP 狀態碼</span><span class="info-value">' + (s.http_code || '無回應') + '</span></div>'
                    +   '<div class="info-row"><span class="info-label">響應時間</span><span class="info-value">' + s.response_ms + ' ms</span></div>'
                    +   '<div class="info-row"><span class="info-label">檢測時間</span><span class="info-value">' + s.checked_at + ' HKT</span></div>'
                    + '</div>'
                    + '<div class="site-url">🔗 <a href="' + escapeAttr(s.url) + '" target="_blank">' + escapeHtml(target) + '</a></div>'
                    + errBlock
                    + '</div>';
            });
            grid.innerHTML = html;
            document.getElementById('stat-total').textContent = sites.length;
            document.getElementById('stat-online').textContent = online;
            document.getElementById('stat-offline').textContent = offline;
        }

        function renderLogs(logs) {
            const list = document.getElementById('logs-list');
            if (!logs || logs.length === 0) { list.innerHTML = ''; return; }
            let html = '';
            logs.forEach(function(line) {
                let cls = '';
                if (line.indexOf('OK') !== -1) cls = 'OK';
                if (line.indexOf('DOWN') !== -1) cls = 'DOWN';
                html += '<div class="log-line ' + cls + '">' + escapeHtml(line.trim()) + '</div>';
            });
            list.innerHTML = html;
        }

        function escapeHtml(s) {
            var d = document.createElement('div');
            d.textContent = s == null ? '' : String(s);
            return d.innerHTML;
        }
        function escapeAttr(s) {
            return escapeHtml(s).replace(/"/g, '&quot;');
        }

        function doCheck(isAuto) {
            btnCheck.disabled = true;
            countdownEl.className = 'stat-value checking';
            countdownEl.textContent = '檢測中...';
            // 後台執行檢測，避免頁面卡住
            fetch('ajax_check.php', { cache: 'no-store' })
                .then(function(r) { return r.json(); })
                .then(function(data) {
                    if (data.success) {
                        renderSites(data.sites);
                        renderLogs(data.logs);
                        document.getElementById('last-check').textContent = data.checked_at + ' HKT';
                        showMessage('✅ 已完成所有網站檢測！', 'success');
                    } else {
                        showMessage('❌ 檢測失敗：' + (data.error || '未知錯誤'), 'error');
                    }
                })
                .catch(function(e) {
                    showMessage('❌ 連線失敗：' + e.message, 'error');
                })
                .finally(function() {
                    btnCheck.disabled = false;
                    countdownEl.className = 'stat-value countdown';
                    totalSeconds = refreshSeconds;
                    updateCountdown();
                    setTimeout(function() { msgEl.className = 'message'; }, 4000);
                });
        }

        function updateCountdown() {
            const minutes = Math.floor(totalSeconds / 60);
            const seconds = totalSeconds % 60;
            countdownEl.textContent = minutes + ':' + (seconds < 10 ? '0' : '') + seconds;

            if (totalSeconds <= 0) {
                doCheck(true);
                return;
            }
            totalSeconds--;
        }

        btnCheck.addEventListener('click', function() { doCheck(false); });
        setInterval(updateCountdown, 1000);
        updateCountdown();
    </script>
</body>
</html>

<?php
/**
 * 網站監控核心類
 */
class WebsiteMonitor {
    public $config;
    public $cacheFile;
    public $logFile;
    public $notifiedFile;

    public function __construct() {
        // 強制設定香港時區
        date_default_timezone_set('Asia/Hong_Kong');

        $this->config = require __DIR__ . '/config.php';
        $this->cacheFile    = __DIR__ . '/cache/status.json';
        $this->logFile      = __DIR__ . '/logs/monitor.log';
        $this->notifiedFile = __DIR__ . '/cache/notified.json';

        // 確保目錄存在
        if (!is_dir(dirname($this->cacheFile))) {
            mkdir(dirname($this->cacheFile), 0755, true);
        }
        if (!is_dir(dirname($this->logFile))) {
            mkdir(dirname($this->logFile), 0755, true);
        }
    }

    /**
     * 寫入日誌
     */
    public function log($message) {
        $line = sprintf("[%s] %s\n", date('Y-m-d H:i:s'), $message);
        file_put_contents($this->logFile, $line, FILE_APPEND);
    }

    /**
     * 取得服務商顯示名稱
     */
    public function getProviderName() {
        $provider = isset($this->config['whatsapp']['provider']) ? $this->config['whatsapp']['provider'] : '';
        $names = array(
            'twilio'    => 'Twilio',
            'ultramsg'  => 'Ultramsg',
            'callmebot' => 'CallMeBot',
        );
        return isset($names[$provider]) ? $names[$provider] : '未設定';
    }

    /**
     * 讀取最近 N 行日誌
     */
    public function getRecentLogs($lines = 20) {
        if (!file_exists($this->logFile)) {
            return array();
        }
        $all = file($this->logFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if (!$all) return array();
        return array_slice($all, -$lines);
    }

    /**
     * 檢測單個網站
     */
    public function checkSite($site) {
        // 優先使用 real_url 檢測（登入頁等真實地址），否則用 url
        $target = isset($site['real_url']) && $site['real_url'] ? $site['real_url'] : $site['url'];

        $ch = curl_init($target);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, $this->config['settings']['timeout']);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $this->config['settings']['timeout']);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($ch, CURLOPT_USERAGENT, 'Website-Monitor/1.0');

        $startTime = microtime(true);
        $response  = curl_exec($ch);
        $httpCode  = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $endTime   = microtime(true);
        $error     = curl_error($ch);
        curl_close($ch);

        $responseTime = round(($endTime - $startTime) * 1000);

        // 2xx/3xx 狀態碼視為正常
        $online = ($httpCode >= 200 && $httpCode < 400) && empty($error);

        return array(
            'name'         => $site['name'],
            'url'          => $site['url'],
            'real_url'     => $target,
            'online'       => $online,
            'http_code'    => $httpCode,
            'response_ms'  => $responseTime,
            'error'        => $error,
            'checked_at'   => date('Y-m-d H:i:s'),
        );
    }

    /**
     * 檢測所有網站（含重試）
     */
    public function checkAll() {
        $results = array();

        foreach ($this->config['sites'] as $site) {
            $result = null;
            $attempts = $this->config['settings']['retry'] + 1;

            for ($i = 0; $i < $attempts; $i++) {
                $r = $this->checkSite($site);
                if ($r['online']) {
                    $result = $r;
                    break;
                }
                $result = $r;
                if ($i < $attempts - 1) sleep(1);
            }

            $results[] = $result;
            $this->logResult($result);
            $this->handleNotification($result);
        }

        file_put_contents(
            $this->cacheFile,
            json_encode(array(
                'checked_at' => date('Y-m-d H:i:s'),
                'sites'      => $results,
            ))
        );

        return $results;
    }

    /**
     * 讀取上次檢測結果
     */
    public function getLastResults() {
        if (!file_exists($this->cacheFile)) {
            return null;
        }
        $data = json_decode(file_get_contents($this->cacheFile), true);
        return $data;
    }

    /**
     * 記錄單筆檢測日誌
     */
    private function logResult($result) {
        $status = $result['online'] ? 'OK' : 'DOWN';
        $line = sprintf(
            "[%s] %-6s | %-20s | HTTP %d | %dms | %s\n",
            date('Y-m-d H:i:s'),
            $status,
            $result['name'],
            $result['http_code'],
            $result['response_ms'],
            $result['real_url']
        );
        file_put_contents($this->logFile, $line, FILE_APPEND);
    }

    /**
     * 處理通知（故障 + 恢復）
     */
    private function handleNotification($result) {
        $cfg = $this->config['whatsapp'];
        if (empty($cfg['enabled'])) return;

        $cooldown = isset($cfg['cooldown']) ? (int)$cfg['cooldown'] : 1800;

        $notified = array();
        if (file_exists($this->notifiedFile)) {
            $notified = json_decode(file_get_contents($this->notifiedFile), true);
            if (!is_array($notified)) $notified = array();
        }

        $key = md5($result['url']);
        $now = time();

        if (!$result['online']) {
            if (
                !isset($notified[$key]) ||
                ($now - $notified[$key]) > $cooldown
            ) {
                $this->sendWhatsApp(
                    "🔴 網站故障警報\n\n" .
                    "網站: {$result['name']}\n" .
                    "網址: {$result['real_url']}\n" .
                    "HTTP狀態: {$result['http_code']}\n" .
                    "響應時間: {$result['response_ms']}ms\n" .
                    "時間: " . date('Y-m-d H:i:s') . " HKT\n" .
                    ($result['error'] ? "錯誤: {$result['error']}" : "無回應")
                );
                $notified[$key] = $now;
            }
        } else {
            if (isset($notified[$key])) {
                $this->sendWhatsApp(
                    "🟢 網站恢復正常\n\n" .
                    "網站: {$result['name']}\n" .
                    "網址: {$result['real_url']}\n" .
                    "HTTP狀態: {$result['http_code']}\n" .
                    "響應時間: {$result['response_ms']}ms\n" .
                    "時間: " . date('Y-m-d H:i:s') . " HKT"
                );
                unset($notified[$key]);
            }
        }

        file_put_contents($this->notifiedFile, json_encode($notified));
    }

    /**
     * 發送 WhatsApp 訊息
     */
    private function sendWhatsApp($message) {
        $cfg = $this->config['whatsapp'];

        if ($cfg['provider'] === 'twilio') {
            $url = "https://api.twilio.com/2010-04-01/Accounts/{$cfg['account_sid']}/Messages.json";
            $from = $cfg['from'];
            $to   = $cfg['to'];
            if (strpos($from, 'whatsapp:') !== 0) $from = 'whatsapp:' . $from;
            if (strpos($to, 'whatsapp:') !== 0)   $to   = 'whatsapp:' . $to;

            $data = array(
                'From' => $from,
                'To'   => $to,
                'Body' => $message,
            );
            if (!empty($cfg['content_sid'])) {
                $data['ContentSid'] = $cfg['content_sid'];
            }

            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 15);
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
            curl_setopt($ch, CURLOPT_USERPWD, $cfg['account_sid'] . ':' . $cfg['auth_token']);
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curlErr  = curl_error($ch);
            curl_close($ch);

            $ok = ($httpCode >= 200 && $httpCode < 300);
            $this->log(sprintf(
                "[WhatsApp/Twilio] %s | HTTP %d | %s",
                $ok ? 'SUCCESS' : 'FAIL',
                $httpCode,
                $curlErr ? $curlErr : mb_substr(str_replace(array("\r","\n"), ' ', $response), 0, 300)
            ));
            return $ok;
        }

        if ($cfg['provider'] === 'ultramsg') {
            $url = "https://api.ultramsg.com/{$cfg['instance_id']}/messages/chat";
            $to  = $cfg['to'];
            if (strpos($to, '@c.us') === false && strpos($to, '+') !== 0) {
                $to = '+' . ltrim($to, '+');
            }
            $data = array(
                'token' => $cfg['token'],
                'to'    => $to,
                'body'  => $message,
            );

            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 15);
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curlErr  = curl_error($ch);
            curl_close($ch);

            $ok = ($httpCode >= 200 && $httpCode < 300);
            $this->log(sprintf(
                "[WhatsApp/Ultramsg] %s | HTTP %d | %s",
                $ok ? 'SUCCESS' : 'FAIL',
                $httpCode,
                $curlErr ? $curlErr : mb_substr(str_replace(array("\r","\n"), ' ', $response), 0, 300)
            ));
            return $ok;
        }

        if ($cfg['provider'] === 'callmebot') {
            $apiUrl  = "https://api.callmebot.com/whatsapp.php";
            $apiUrl .= "?phone=" . urlencode($cfg['callmebot_phone']);
            $apiUrl .= "&text=" . urlencode($message);
            $apiUrl .= "&apikey=" . urlencode($cfg['callmebot_api']);

            $ctx = stream_context_create(array(
                'http' => array('timeout' => 15)
            ));
            $response = @file_get_contents($apiUrl, false, $ctx);
            $ok = ($response !== false && stripos($response, 'success') !== false);
            $this->log(sprintf("[WhatsApp/CallMeBot] %s | %s", $ok ? 'SUCCESS' : 'FAIL', $response ? mb_substr($response, 0, 200) : 'no response'));
            return $ok;
        }

        return false;
    }
}

# 網站監控系統（PHP + WhatsApp 通知）

監控多個網站是否連線成功，故障與恢復時自動發送 WhatsApp 通知，並提供網頁儀表板。

## 一、本次修正內容

| 問題 | 原因 | 處理 |
|------|------|------|
| cron 執行 check.php 出現 Parse error | 檔頭區塊註解裡含 cron 的「星號加斜線」，提早結束註解 | 改用 `//` 行註解 |
| index.php 出現致命錯誤 | 網頁直接讀取 Monitor 類別的私有屬性 `logFile` | 改為呼叫公開方法 `getRecentLogs()` |
| WhatsApp 一直收不到 | Twilio 現要求 WhatsApp 訊息必須帶 Content 範本（ContentSid）發送，少了會回錯誤 21654；而試用帳號不能建立 Content 範本，回錯誤 20003 | 通知服務商改用 Ultramsg（你的實例已連線，實測可發純文字訊息） |
| Twilio From 號碼錯誤 | config 內填的 `+14155238886` 不屬於此帳號 | 更正為你帳號實際的沙箱號碼 `+17372508034` |

## 二、檔案結構

```
website-monitor/
├── index.php         網頁儀表板（開啟這個頁面即可查看狀態）
├── Monitor.php       監控核心類別（檢測、記錄、通知）
├── check.php         定時檢測腳本（給 cron 使用）
├── test_whatsapp.php WhatsApp 測試與除錯頁
├── config.php        設定檔（網站清單、通知設定）
├── .htaccess         安全設定（禁止直接存取設定與日誌檔）
├── cache/            狀態快取（status.json、notified.json）
├── logs/             日誌（monitor.log）
└── README.md         本說明
```

## 三、設定方式

### 1. 監控的網站
在 `config.php` 的 `sites` 陣列中設定，每個站點可填：
- `name`：顯示名稱
- `url`：對外網址（儀表板上點擊用）
- `real_url`：實際用於檢測的網址（建議填登入頁）。檢測時優先使用 `real_url`，沒有才用 `url`。
- 判定規則：HTTP 狀態碼 2xx／3xx 且無 cURL 錯誤＝在線，其餘＝離線；失敗會依 `retry` 次數重試。

### 2. 通知服務商（`whatsapp.provider`）
| 服務商 | 說明 |
|--------|------|
| `ultramsg` | **目前使用**。你的實例 `instance196947` 狀態為 authenticated／connected，可直接發純文字訊息，不需範本 |
| `twilio` | 需付費帳號並建立 Content 範本。試用帳號會回 21654／20003，無法發送 |
| `callmebot` | 免費備選。先在 WhatsApp 發送允許訊息給 `+34 644 61 91 98` 取得 API Key，填入 `callmebot_api` |

日後 Twilio 帳號升級後，把 `provider` 改回 `'twilio'`，並在 `content_sid` 填入 HX 開頭的範本編號即可。

### 3. 其他參數
- `timeout`：單站超時秒數（預設 10）
- `retry`：失敗重試次數（預設 2）
- `check_interval`：網頁自動刷新間隔，分鐘（預設 5）
- `cooldown`：同一站點通知冷卻秒數（預設 1800，即 30 分鐘）

## 四、定時任務（讓背景持續監控）

在 cPanel 或 Linux 加入 cron 任務，每 5 分鐘執行一次（分鐘欄位填星號斜線 5，其餘為星號）：

```
php /你的路徑/website-monitor/check.php
```

## 五、使用方式

1. 將整個資料夾上傳到支援 PHP + cURL 的虛擬主機。
2. 瀏覽 `index.php`，點「立即檢測所有網站」即可馬上檢查一輪。
3. 有問題時打開 `test_whatsapp.php`，會逐項列出設定檢查結果，並可直接發送測試訊息；失敗會顯示具體原因。
4. 時區固定為 `Asia/Hong_Kong`，所有顯示時間與通知時間均為香港時間（HKT）。

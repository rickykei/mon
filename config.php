<?php
/**
 * 網站監控系統配置文件
 */
return array(
    'sites' => array(
        array(
            'name'     => 'cbuat',
            'url'      => 'https://admin.e-profile.digital',
            'real_url' => 'https://admin.e-profile.digital/user/login',
        ),
        array(
            'name'     => 'cbprd',
            'url'      => 'https://admin.profiles.digital',
            'real_url' => 'https://admin.profiles.digital/user/login',
        ),
        array(
            'name'     => 'beaprd',
            'real_url' => 'https://admin-ebcard.hkbea.digital/user/login',
            'url'      => 'http://18.162.65.202',
        ),
        array(
            'name'     => 'cicprd',
            'real_url' => 'https://admin.namecard.cncbintl.com/user/login',
            'url'      => 'https://18.166.221.71',
        ),
        array(
            'name'     => 'nfctouch',
            'real_url' => 'https://nfctouch.com.hk/',
            'url'      => 'https://nfctouch.com.hk/',
        ),
    ),

    'settings' => array(
        'timeout'        => 10,   // 單個網站超時（秒）
        'retry'          => 2,    // 失敗重試次數
        'check_interval' => 5,    // 網頁自動刷新間隔（分鐘）
    ),

    'whatsapp' => array(
        'enabled'  => true,
        'provider' => 'twilio',   // twilio / ultramsg / callmebot
        'cooldown' => 1800,       // 同一網站 30 分鐘內不重複警報（秒）

        // === Twilio ===
        'account_sid' => 'ACe73453bf589a63bfb6af12099751aa0a',
        'auth_token'  => '50fe6866051f20e0722aa4aacf7f11e',
        'from'        => 'whatsapp:+14155238886',
        'to'          => 'whatsapp:+85254864677',
        'content_sid' => '',       // 升級帳號後建立的 WhatsApp 內容範本 SID，可留空

        // === Ultramsg（備用）===
        'instance_id' => 'instance196947',
        'token'       => 'la0xod6pb4h7wo5x',

        // === CallMeBot（備用，完全免費）===
        'callmebot_phone' => '',  // 例如 +85291234567
        'callmebot_api'   => '',
    ),
);

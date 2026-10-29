<?php
/* CITRIC.UZ — maxfiy sozlamalar namunasi.
   1) Shu faylni nusxalab, nomini config.php qiling (index.php yonida).
   2) Qiymatlarni to'ldiring. config.php hech qachon GitHubga yuklanmasin. */
return [
    // MySQL (hosting). Lokal kompyuterda (php -S) avtomatik SQLite ishlatiladi.
    'db' => [
        'host' => 'localhost',
        'port' => 3306,
        'name' => 'BAZA_NOMI',
        'user' => 'BAZA_FOYDALANUVCHISI',
        'pass' => 'BAZA_PAROLI',
    ],

    // Telegram: @BotFather dan olingan token va operatorlar guruhi ID si
    // (guruh ID bo'sh qolsa — botni guruhga qo'shganingizda o'zi ulanadi)
    'telegram_token'   => '123456789:BOT_TOKEN',
    'telegram_chat_id' => '',
    'webhook_secret'   => 'TASODIFIY_32_BELGILI_SATR', // masalan: bin2hex(random_bytes(16))

    // Google Gemini (aistudio.google.com → API key)
    'gemini_key'      => 'GEMINI_API_KEY',
    'gemini_model'    => 'gemini-3.5-flash',
    'gemini_fallback' => 'gemini-flash-lite-latest',

    // Admin panelga birinchi kirish
    'admin_login'    => 'admin',
    'admin_password' => 'KUCHLI_PAROL',
];

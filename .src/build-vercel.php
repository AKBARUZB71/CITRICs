<?php
// .src/build-vercel.php — Vercel uchun to'liq mustaqil nusxa (hostingsiz):
//   public/index.html  — sayt (rasmlar CDN dan, mahsulotlar sahifa ichida)
//   api/_catalog.json  — AI maslahatchi (api/chat.js) uchun mahsulotlar ro'yxati
// Mahsulotlar manbasi: lokal baza (php -S localhost:5501 + citric-local.sqlite; tahrirlash — lokal admin.php).
// Ishlatish: php .src/build-vercel.php
const LOCAL = 'http://localhost:5501/index.php';
const CDN   = 'https://cdn.jsdelivr.net/gh/AKBARUZB71/CITRIC@main/assets/';
$root = dirname(__DIR__);

$html = @file_get_contents(LOCAL);
if (!$html || strpos($html, 'atob(') === false) exit("Lokal sahifa olinmadi — avval serverni ishga tushiring: php -d extension=pdo_sqlite -S localhost:5501\n");

// Lokal assets/ → CDN
$html = str_replace(['src="assets/', 'href="assets/', 'srcset="assets/', 'content="assets/', 'poster="assets/'],
    ['src="' . CDN, 'href="' . CDN, 'srcset="' . CDN, 'content="' . CDN, 'poster="' . CDN], $html);
$html = str_replace('window.CITRIC_ASSETS = "assets\/";', 'window.CITRIC_ASSETS = ' . json_encode(CDN) . ';', $html);
if (preg_match('~(src|href|srcset)="assets/~', $html)) exit("assets/ havolalari qoldi\n");

// Mahsulotlar va kategoriyalar (sahifadagi ma'lumotdan)
if (!preg_match('~window\.CITRIC_PRODUCTS = (.*?);\n~', $html, $mp) || !preg_match('~window\.CITRIC_CATEGORIES = (.*?);\n~', $html, $mc)) exit("mahsulotlar topilmadi\n");
$products = json_decode($mp[1], true);
$cats = json_decode($mc[1], true);
if (!$products || count($products) < 10) exit("mahsulotlar ro'yxati bo'sh\n");

// Buzilgan kodlash himoyasi: '???' yoki U+FFFD bo'lsa — nusxa yaratilmaydi
if (!mb_check_encoding($html, 'UTF-8') || strpos($html, "\u{FFFD}") !== false || preg_match('~"(?:uz|ru|en)":"[^"]*\?{3,}~', $html)) {
    exit("XATO: mahsulot matnlarida buzilgan belgilar bor — nusxa yaratilmadi\n");
}

// AI maslahatchi katalogi
$catMap = [];
foreach ($cats as $c) $catMap[$c['id']] = ['uz' => $c['uz'], 'ru' => $c['ru'], 'en' => $c['en']];
@mkdir("$root/api", 0775, true);
file_put_contents("$root/api/_catalog.json", json_encode(['categories' => $catMap, 'products' => $products], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT));

// Vercel rejimi: chat va arizalar o'z funksiyalari orqali, tashqi API yo'q
$boot = '<script>window.CITRIC_STATIC=1;window.CITRIC_LEAD_API="/api/lead";window.CITRIC_CHAT_API="/api/chat";</script>';
$p = strpos($html, '<script>(function(d){var s=atob(');
if ($p === false) exit("sayt skripti topilmadi\n");
$html = substr($html, 0, $p) . $boot . substr($html, $p);
if (strpos($html, 'xvest3') !== false) exit("XATO: sahifada eski hosting manzili qoldi\n");

@mkdir("$root/public", 0775, true);
file_put_contents("$root/public/index.html", $html);
echo "public/index.html: ", strlen($html), " bayt | api/_catalog.json: ", count($products), " mahsulot\n";

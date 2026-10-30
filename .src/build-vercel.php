<?php
// .src/build-vercel.php — Vercel uchun statik nusxa: public/index.html
// Sahifa lokal serverdan olinadi (php -S localhost:5501), rasmlar CDN ga, API esa asosiy hostingga yo'naltiriladi.
// Ishlatish: php .src/build-vercel.php
const LOCAL = 'http://localhost:5501/index.php';
const SITE  = 'https://696f540674d5d.xvest3.ru/citric.uz/';   // PHP sayt (API, bot, admin) — domen ulanganda shu yerni o'zgartiring
const CDN   = 'https://cdn.jsdelivr.net/gh/AKBARUZB71/CITRIC@main/assets/';

$html = @file_get_contents(LOCAL);
if (!$html || strpos($html, 'atob(') === false) exit("Lokal sahifa olinmadi — avval serverni ishga tushiring: php -d extension=pdo_sqlite -S localhost:5501\n");

// Lokal assets/ → CDN
$html = str_replace(['src="assets/', 'href="assets/', 'srcset="assets/', 'content="assets/', 'poster="assets/'],
    ['src="' . CDN, 'href="' . CDN, 'srcset="' . CDN, 'content="' . CDN, 'poster="' . CDN], $html);
$html = str_replace('window.CITRIC_ASSETS = "assets\/";', 'window.CITRIC_ASSETS = ' . json_encode(CDN) . ';', $html);
if (preg_match('~(src|href|srcset)="assets/~', $html)) exit("assets/ havolalari qoldi\n");

// Mahsulotlar nusxasi: imkon bo'lsa asosiy saytning haqiqiy bazasidan (lokal test bazasidan emas)
$live = @file_get_contents(SITE . 'index.php?api=products', false, stream_context_create(['http' => ['timeout' => 20]]));
$liveJson = $live ? json_decode($live, true) : null;
if (!empty($liveJson['products'])) {
    $snap = json_encode($liveJson['products'], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP);
    $html = preg_replace('~window\.CITRIC_PRODUCTS = .*?;\n~', 'window.CITRIC_PRODUCTS = ' . addcslashes($snap, '\\$') . ";\n", $html, 1, $cnt);
    echo $cnt ? "mahsulotlar: asosiy saytdan (" . count($liveJson['products']) . " ta)\n" : "OGOHLANTIRISH: CITRIC_PRODUCTS topilmadi\n";
} else {
    echo "mahsulotlar: lokal bazadan (asosiy sayt API javob bermadi)\n";
}
// Buzilgan kodlash himoyasi: '???' yoki U+FFFD bo'lsa — nusxa yaratilmaydi
if (!mb_check_encoding($html, 'UTF-8') || strpos($html, "\u{FFFD}") !== false || preg_match('~"(?:uz|ru|en)":"[^"]*\?{3,}~', $html)) {
    exit("XATO: mahsulot matnlarida buzilgan belgilar bor — nusxa yaratilmadi\n");
}

// API manzili — shifrlangan sayt skriptidan oldin
// Buyurtma va arizalar — Vercel'ning o'z funksiyasi (api/lead.js) orqali to'g'ridan-to'g'ri Telegramga
$boot = '<script>window.CITRIC_API=' . json_encode(SITE . 'index.php') . ';window.CITRIC_SITE=' . json_encode(SITE) . ';window.CITRIC_LEAD_API="/api/lead";</script>';
$p = strpos($html, '<script>(function(d){var s=atob(');
if ($p === false) exit("sayt skripti topilmadi\n");
$html = substr($html, 0, $p) . $boot . substr($html, $p);

@mkdir(dirname(__DIR__) . '/public', 0775, true);
file_put_contents(dirname(__DIR__) . '/public/index.html', $html);
echo "public/index.html: ", strlen($html), " bayt\n";

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

// API manzili — shifrlangan sayt skriptidan oldin
$boot = '<script>window.CITRIC_API=' . json_encode(SITE . 'index.php') . ';window.CITRIC_SITE=' . json_encode(SITE) . ';</script>';
$p = strpos($html, '<script>(function(d){var s=atob(');
if ($p === false) exit("sayt skripti topilmadi\n");
$html = substr($html, 0, $p) . $boot . substr($html, $p);

@mkdir(dirname(__DIR__) . '/public', 0775, true);
file_put_contents(dirname(__DIR__) . '/public/index.html', $html);
echo "public/index.html: ", strlen($html), " bayt\n";

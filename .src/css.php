<?php
// .src/css.php ? .src/product-modal.css ni index.php dagi <style> ichiga joylaydi (qayta ishga tushirsa almashtiradi)
$f = dirname(__DIR__) . '/index.php';
$s = file_get_contents($f);
$css = "/*PM-CSS-START*/" . file_get_contents(__DIR__ . '/product-modal.css') . "/*PM-CSS-END*/\n";
if (strpos($s, '/*PM-CSS-START*/') !== false) {
    $s = preg_replace('~/\*PM-CSS-START\*/.*?/\*PM-CSS-END\*/\n~s', addcslashes($css, '\\$'), $s, 1);
} else {
    $p = strpos($s, '</style>');
    if ($p === false) exit("no </style>\n");
    $s = substr($s, 0, $p) . $css . substr($s, $p);
}
file_put_contents($f, $s);
echo "css ok\n";

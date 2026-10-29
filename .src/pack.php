<?php
// .src/pack.php ? .src/main.js ni index.php ichiga qayta joylaydi
$f = dirname(__DIR__) . '/index.php';
$s = file_get_contents($f);
$js = file_get_contents(__DIR__ . '/main.js');
$n = 0;
$s = preg_replace_callback("~atob\('([A-Za-z0-9+/=]+)'\)~", function () use ($js) { return "atob('" . base64_encode($js) . "')"; }, $s, 1, $n);
if ($n !== 1) exit("packed js not found\n");
file_put_contents($f, $s);
echo "index.php: ", strlen($s), " bytes\n";

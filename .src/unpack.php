<?php
// .src/unpack.php ? index.php ichidagi shifrlangan JS ni .src/main.js ga chiqaradi
$f = dirname(__DIR__) . '/index.php';
$s = file_get_contents($f);
if (!preg_match("~atob\('([A-Za-z0-9+/=]+)'\)~", $s, $m)) exit("packed js not found\n");
file_put_contents(__DIR__ . '/main.js', base64_decode($m[1]));
echo "main.js: ", strlen(base64_decode($m[1])), " bytes\n";

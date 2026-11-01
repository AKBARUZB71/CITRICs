<?php
// .src/hero3d.js -> index.php (/*H3D-START*/ ... /*H3D-END*/ orasiga)
$f = __DIR__ . '/../index.php';
$s = file_get_contents($f);
$js = file_get_contents(__DIR__ . '/hero3d.js');
$a = strpos($s, '/*H3D-START*/'); $b = strpos($s, '/*H3D-END*/');
if ($a === false || $b === false || $b < $a) { fwrite(STDERR, "H3D belgilari topilmadi\n"); exit(1); }
$s = substr($s, 0, $a + 13) . "\n" . $js . substr($s, $b);
file_put_contents($f, $s);
echo "h3d ok\n";

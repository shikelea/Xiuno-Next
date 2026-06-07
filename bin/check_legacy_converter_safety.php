<?php

$root = realpath(__DIR__ . '/..');
if ($root === false) {
    fwrite(STDERR, "Unable to locate project root.\n");
    exit(1);
}

$removedDiscuzConverters = [
    'tool/dx_to_xn4.php',
    'tool/dx32_to_xn4.php',
    'tool/dx34_to_xn4.php',
];

foreach ($removedDiscuzConverters as $relativePath) {
    if (is_file($root . '/' . $relativePath)) {
        fwrite(STDERR, "FAIL: Discuz import converter is out of scope and must not be restored: $relativePath\n");
        exit(1);
    }
}

echo "OK: out-of-scope Discuz import converters are absent\n";

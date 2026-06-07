<?php

$root = realpath(__DIR__ . '/..');
if ($root === false) {
    fwrite(STDERR, "Unable to locate project root.\n");
    exit(1);
}

$discuzConverters = [
    'tool/dx_to_xn4.php',
    'tool/dx32_to_xn4.php',
    'tool/dx34_to_xn4.php',
];

foreach ($discuzConverters as $relativePath) {
    $path = $root . '/' . $relativePath;
    $source = file_get_contents($path);
    if ($source === false) {
        fwrite(STDERR, "Unable to read converter: $relativePath\n");
        exit(1);
    }

    if (strpos($source, "'isfirst'=>(" . '$thread' . "['firstpid'] == " . '$post' . "['pid'] ? 1 : 0)") !== false) {
        fwrite(STDERR, "FAIL: $relativePath uses stale thread state for post isfirst conversion.\n");
        exit(1);
    }

    $required = [
        '$tid = intval($post[\'tid\']);',
        '$pid = intval($post[\'pid\']);',
        '$thread = $dx->sql_find_one("SELECT firstpid FROM {$tablepre}thread WHERE tid=\'$tid\'");',
        "'isfirst'=>(!empty(" . '$thread' . ") && intval(" . '$thread' . "['firstpid']) == " . '$pid' . " ? 1 : 0)",
    ];
    foreach ($required as $needle) {
        if (strpos($source, $needle) === false) {
            fwrite(STDERR, "FAIL: $relativePath is missing converter safety marker: $needle\n");
            exit(1);
        }
    }
}

echo "OK: legacy converter safety checks passed\n";

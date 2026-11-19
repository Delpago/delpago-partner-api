<?php
$f = '/var/www/delpago-app/bootstrap/app.php';
$s = file_get_contents($f); $o = $s;
copy($f, $f.'.bak-partnerapi-'.time());

// a) Register the api route file in withRouting(...). The existing call has web: ...
if (strpos($s, "routes/api.php") === false) {
    // add api: alongside web:
    $s = preg_replace(
        "/(web:\s*__DIR__\s*\.\s*'\/\.\.\/routes\/web\.php',)/",
        "$1\n        api: __DIR__.'/../routes/api.php',\n        apiPrefix: 'api',",
        $s, 1
    );
    echo "OK: api route file registered\n";
} else { echo "SKIP: api route already registered\n"; }

// b) Register the 'partner' middleware alias. Add an ->alias([...]) call inside withMiddleware.
if (strpos($s, "'partner' =>") === false) {
    // insert right after the withMiddleware opening line we saw
    $anchor = '->withMiddleware(function (Middleware $middleware): void {';
    if (strpos($s, $anchor) !== false) {
        $inject = $anchor . "\n        \$middleware->alias(['partner' => \\App\\Http\\Middleware\\AuthenticatePartner::class]);";
        $s = str_replace($anchor, $inject, $s);
        echo "OK: partner middleware alias added\n";
    } else { echo "MISS: withMiddleware anchor not found — add alias manually\n"; }
} else { echo "SKIP: partner alias present\n"; }

if ($s !== $o) file_put_contents($f, $s);
$out=[]; $rc=0; exec('php -l '.escapeshellarg($f).' 2>&1', $out, $rc);
echo ($rc===0?"LINT OK bootstrap\n":"LINT FAIL bootstrap\n".implode("\n",$out)."\n");

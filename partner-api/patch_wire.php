<?php
$log = [];

// 1) Order model: add partner fields to fillable + a partner() relation.
$f = '/var/www/delpago-app/app/Models/Order.php';
$s = file_get_contents($f); $o = $s;
copy($f, $f.'.bak-partnerapi-'.time());
if (strpos($s, "'partner_id'") === false) {
    $s = str_replace("'meta',\n    ];", "'meta',\n        'partner_id', 'sub_merchant_ref', 'partner_fee_bps',\n    ];", $s);
    $log[] = "OK: Order fillable += partner fields";
} else { $log[] = "SKIP: Order fillable"; }
if (strpos($s, "function partner(") === false) {
    $rel = "\n    public function partner(): BelongsTo\n    {\n        return \$this->belongsTo(Partner::class);\n    }\n";
    // insert before the final closing brace
    $s = preg_replace('/\}\s*$/', $rel . "}\n", $s, 1);
    $log[] = "OK: Order->partner() relation";
} else { $log[] = "SKIP: Order->partner()"; }
if ($s !== $o) file_put_contents($f, $s);

// 2) Hook the webhook into OrderService::confirmPaid — fire charge.paid after accrual.
$f2 = '/var/www/delpago-app/app/Services/OrderService.php';
$s2 = file_get_contents($f2); $o2 = $s2;
copy($f2, $f2.'.bak-partnerapi-'.time());
if (strpos($s2, 'PartnerWebhookDispatcher') === false) {
    // after $this->accrueFee($order); in the approved branch, dispatch the event.
    $s2 = str_replace(
        '$this->accrueFee($order);',
        "\$this->accrueFee(\$order);\n                if (\$order->partner_id) {\n                    app(\\App\\Services\\PartnerWebhookDispatcher::class)->dispatchChargeEvent(\$order->fresh(), 'charge.paid');\n                }",
        $s2
    );
    $log[] = "OK: confirmPaid fires charge.paid webhook";
} else { $log[] = "SKIP: webhook hook present"; }
if ($s2 !== $o2) file_put_contents($f2, $s2);

// 3) Detect FeeEntry's order linkage column (order_id vs payment_id) so PartnerController balance works.
$fe = '/var/www/delpago-app/app/Models/FeeEntry.php';
if (is_file($fe)) {
    $fs = file_get_contents($fe);
    $hasOrderId = strpos($fs, 'order_id') !== false;
    $log[] = $hasOrderId ? "INFO: FeeEntry has order_id (balance query OK)" : "WARN: FeeEntry has NO order_id — balance query needs payment_id mapping (see note)";
}

echo implode("\n", $log) . "\n";
foreach (['/var/www/delpago-app/app/Models/Order.php','/var/www/delpago-app/app/Services/OrderService.php'] as $lf) {
  $out=[]; $rc=0; exec('php -l '.escapeshellarg($lf).' 2>&1', $out, $rc);
  echo ($rc===0?"LINT OK: ":"LINT FAIL: ").basename($lf)."\n";
  if ($rc!==0) echo implode("\n",$out)."\n";
}

<?php

namespace App\Services;

use App\Models\Order;
use App\Models\PartnerWebhook;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Sends signed outbound webhooks to a partner's registered URL on charge
 * status changes. Signature scheme matches BorgLayer/DelPago elsewhere:
 *   X-DelPago-Signature: t=<ts>,v1=<hmac_sha256(ts . "." . body, secret)>
 * Synchronous for Phase 1 (no queue worker assumed); auto-disables a URL
 * after sustained failure.
 */
class PartnerWebhookDispatcher
{
    public function dispatchChargeEvent(Order $order, string $type): void
    {
        if (! $order->partner_id) return;
        $hooks = PartnerWebhook::where('partner_id', $order->partner_id)->where('active', true)->get();
        if ($hooks->isEmpty()) return;

        $feeBps = (int) (optional($order->partner)->fee_bps ?? config('billing.fee_percent', 0.9) * 100);
        $payload = [
            'type' => $type,
            'data' => [
                'id'          => $order->reference,
                'reference'   => $order->meta['partner_reference'] ?? null,
                'amount'      => (int) $order->amount_cents,
                'currency'    => $order->currency,
                'status'      => $order->status,
                'fee'         => (int) round($order->amount_cents * $feeBps / 10000),
                'partner_fee' => $order->partner_fee_bps
                    ? (int) round($order->amount_cents * $order->partner_fee_bps / 10000)
                    : 0,
            ],
        ];
        $body = json_encode($payload, JSON_UNESCAPED_SLASHES);

        foreach ($hooks as $hook) {
            $ts  = (string) time();
            $sig = hash_hmac('sha256', $ts . '.' . $body, $hook->secret);
            try {
                $res = Http::timeout(15)
                    ->withHeaders([
                        'Content-Type'       => 'application/json',
                        'X-DelPago-Signature'=> "t={$ts},v1={$sig}",
                        'X-DelPago-Event'    => $type,
                    ])->withBody($body, 'application/json')
                    ->post($hook->url);

                if ($res->successful()) {
                    if ($hook->fail_count > 0) $hook->update(['fail_count' => 0]);
                } else {
                    $this->fail($hook);
                }
            } catch (\Throwable $e) {
                Log::warning('partner webhook failed', ['hook' => $hook->id, 'err' => $e->getMessage()]);
                $this->fail($hook);
            }
        }
    }

    protected function fail(PartnerWebhook $hook): void
    {
        $hook->increment('fail_count');
        if ($hook->fail_count >= 15) {
            $hook->update(['active' => false, 'disabled_at' => now()]);
        }
    }
}

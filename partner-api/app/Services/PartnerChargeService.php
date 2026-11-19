<?php

namespace App\Services;

use App\Models\Merchant;
use App\Models\Order;
use App\Models\Partner;
use Illuminate\Support\Facades\DB;

/**
 * Wraps OrderService::start() for partner charges. Resolves which merchant's
 * connector collects (a named sub-merchant, else the partner's default
 * merchant), creates the charge, and tags it with the partner for the ledger.
 * DelPago never custodies funds — the connector settles to the merchant.
 */
class PartnerChargeService
{
    public function __construct(private OrderService $orders) {}

    /**
     * @return array{ok:bool, order?:Order, redirect_url?:?string, error?:string}
     */
    public function createCharge(Partner $partner, array $data): array
    {
        $merchant = $this->resolveMerchant($partner, $data['sub_merchant'] ?? null);
        if (! $merchant) {
            return ['ok' => false, 'error' => 'no_merchant_for_partner'];
        }

        // Pick the processor: explicit, else the merchant's first active connector.
        $processor = $data['processor'] ?? null;
        if (! $processor) {
            $mc = $merchant->connectors()->where('active', true)->first();
            if (! $mc) return ['ok' => false, 'error' => 'merchant_has_no_active_processor'];
            $processor = $mc->processor;
        }

        $res = $this->orders->start(
            merchant:    $merchant,
            processor:   $processor,
            amountCents: (int) $data['amount'],
            description: (string) ($data['description'] ?? 'Pago'),
            payerEmail:  $data['customer']['email'] ?? null,
            currency:    $data['currency'] ?? null,
        );

        if (! ($res['ok'] ?? false)) {
            return ['ok' => false, 'order' => $res['order'] ?? null, 'error' => $res['error'] ?? 'charge_failed'];
        }

        /** @var Order $order */
        $order = $res['order'];
        // Tag the order with the partner + optional partner markup for the ledger.
        $order->forceFill([
            'partner_id'       => $partner->id,
            'sub_merchant_ref' => $data['sub_merchant'] ?? null,
            'partner_fee_bps'  => isset($data['partner_fee_bps']) ? (int) $data['partner_fee_bps'] : null,
            'meta'             => array_merge((array) ($order->meta ?? []), [
                'partner_reference' => $data['reference'] ?? null,
                'partner_metadata'  => $data['metadata'] ?? null,
                'return_url'        => $data['return_url'] ?? null,
            ]),
        ])->save();

        return ['ok' => true, 'order' => $order, 'redirect_url' => $res['redirect_url'] ?? null];
    }

    /**
     * Sub-merchant resolution: a partner's sub-merchant ref maps to a DelPago
     * Merchant row (slug or a partner-namespaced external id in meta). For
     * Phase 1 we accept a merchant slug or id as the sub_merchant; a dedicated
     * partner_sub_merchants table comes in Phase 2.
     */
    protected function resolveMerchant(Partner $partner, ?string $subMerchant): ?Merchant
    {
        if ($subMerchant) {
            $m = Merchant::where('slug', $subMerchant)->first()
                ?? Merchant::find(is_numeric($subMerchant) ? (int) $subMerchant : 0);
            if ($m) return $m;
        }
        return $partner->defaultMerchant;
    }
}

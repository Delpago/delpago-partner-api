<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\FeeEntry;
use App\Models\Order;
use App\Models\Partner;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PartnerController extends Controller
{
    /** GET /api/v1/balance — DelPago fees accrued for this partner + partner markup owed. */
    public function balance(Request $request): JsonResponse
    {
        /** @var Partner $partner */
        $partner = $request->attributes->get('partner');

        // DelPago's fee on this partner's approved orders (from the fee ledger via the orders).
        $orderIds = Order::where('partner_id', $partner->id)
            ->where('status', Order::STATUS_APPROVED)->pluck('id');

        $delpagoFeeCents = (int) FeeEntry::whereIn('order_id', $orderIds)
            ->where('type', 'accrual')->sum('amount_cents');

        // Partner's own markup across their approved orders.
        $partnerMarkupCents = (int) Order::where('partner_id', $partner->id)
            ->where('status', Order::STATUS_APPROVED)
            ->whereNotNull('partner_fee_bps')
            ->get()
            ->sum(fn ($o) => (int) round($o->amount_cents * $o->partner_fee_bps / 10000));

        return response()->json([
            'partner'                => $partner->name,
            'delpago_fee_owed'       => $delpagoFeeCents,     // minor units — partner owes DelPago
            'partner_markup_accrued' => $partnerMarkupCents,  // minor units — collected on partner's behalf
            'currency_note'          => 'Amounts in minor units; multi-currency partners should sum per-currency.',
        ]);
    }
}

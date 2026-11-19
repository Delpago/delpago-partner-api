<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\IdempotencyRecord;
use App\Models\Order;
use App\Models\Partner;
use App\Services\PartnerChargeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ChargeController extends Controller
{
    public function __construct(private PartnerChargeService $charges) {}

    /** POST /api/v1/charges */
    public function store(Request $request): JsonResponse
    {
        /** @var Partner $partner */
        $partner = $request->attributes->get('partner');

        $data = $request->validate([
            'amount'          => ['required', 'integer', 'min:1'],
            'currency'        => ['sometimes', 'nullable', 'string', 'size:3'],
            'reference'       => ['sometimes', 'nullable', 'string', 'max:120'],
            'description'     => ['sometimes', 'nullable', 'string', 'max:140'],
            'processor'       => ['sometimes', 'nullable', 'string', 'max:40'],
            'sub_merchant'    => ['sometimes', 'nullable', 'string', 'max:120'],
            'partner_fee_bps' => ['sometimes', 'nullable', 'integer', 'min:0', 'max:5000'],
            'customer'        => ['sometimes', 'array'],
            'customer.email'  => ['sometimes', 'nullable', 'email'],
            'return_url'      => ['sometimes', 'nullable', 'url'],
            'metadata'        => ['sometimes', 'nullable', 'array'],
        ]);

        // --- Idempotency ---
        $idemKey  = $request->header('Idempotency-Key');
        $reqHash  = hash('sha256', json_encode($data));
        if ($idemKey) {
            $existing = IdempotencyRecord::where('partner_id', $partner->id)->where('idem_key', $idemKey)->first();
            if ($existing) {
                // same key + same body -> replay stored result; different body -> conflict
                if ($existing->request_hash !== $reqHash) {
                    return $this->err('idempotency_error', 'idempotency_key_reused',
                        'This Idempotency-Key was used with a different request body.', 409);
                }
                return response()->json($existing->response, $existing->status_code);
            }
        }

        $res = $this->charges->createCharge($partner, $data);

        if (! ($res['ok'] ?? false)) {
            $body = ['error' => ['type' => 'charge_error', 'code' => $res['error'] ?? 'charge_failed', 'message' => 'Could not create the charge.']];
            // Do not store failed attempts under the idem key (let them retry).
            return response()->json($body, 422);
        }

        /** @var Order $order */
        $order = $res['order'];
        $body = [
            'id'           => $order->reference,
            'status'       => $order->status,
            'amount'       => (int) $order->amount_cents,
            'currency'     => $order->currency,
            'checkout_url' => $res['redirect_url'] ?? null,
            'reference'    => $data['reference'] ?? null,
            'created_at'   => $order->created_at?->toIso8601String(),
        ];

        if ($idemKey) {
            IdempotencyRecord::create([
                'partner_id'   => $partner->id,
                'idem_key'     => $idemKey,
                'request_hash' => $reqHash,
                'response'     => $body,
                'status_code'  => 201,
            ]);
        }

        return response()->json($body, 201);
    }

    /** GET /api/v1/charges/{id}  (id = the charge reference) */
    public function show(Request $request, string $id): JsonResponse
    {
        /** @var Partner $partner */
        $partner = $request->attributes->get('partner');

        $order = Order::where('reference', $id)->where('partner_id', $partner->id)->first();
        if (! $order) {
            return $this->err('not_found', 'charge_not_found', 'No charge with that id for this partner.', 404);
        }

        return response()->json([
            'id'           => $order->reference,
            'status'       => $order->status,
            'amount'       => (int) $order->amount_cents,
            'currency'     => $order->currency,
            'reference'    => $order->meta['partner_reference'] ?? null,
            'paid_at'      => $order->approved_at?->toIso8601String(),
            'created_at'   => $order->created_at?->toIso8601String(),
        ]);
    }

    protected function err(string $type, string $code, string $message, int $status): JsonResponse
    {
        return response()->json(['error' => compact('type', 'code', 'message')], $status);
    }
}

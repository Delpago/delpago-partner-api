# DelPago Partner API — Phase 1

Stateless, Bearer-key payment API that lets partner platforms (P2P, freelance,
crypto exchange, web3, domain marketplace) charge through DelPago's existing
non-custodial connector engine. DelPago earns a fee per transaction.

## What Phase 1 ships
- `partners`, `partner_api_keys`, `partner_webhooks`, `idempotency_keys` tables
  + `partner_id` / `sub_merchant_ref` / `partner_fee_bps` on `orders`.
- `partner` middleware: `Authorization: Bearer dp_{live|test}_...` → partner + scope.
- `POST /api/v1/charges` (idempotent) — wraps `OrderService::start`, returns a checkout URL.
- `GET  /api/v1/charges/{id}` — status by reference.
- `GET  /api/v1/balance` — DelPago fee owed + partner markup accrued.
- Signed outbound `charge.paid` webhook on settle (HMAC-SHA256, auto-disable on failure).
- `php artisan partner:create` — mint a partner + first key for testing.

## Install (inside the delpago container)
```
bash /root/partner-api/install.sh
```
Idempotent: copies files, registers the middleware alias + api routes in
`bootstrap/app.php`, wires the webhook into `OrderService::confirmPaid`, adds
partner fields to `Order`, and runs migrations. Backs up every file it edits.

## Create a test partner
```
php artisan partner:create --name="Acme P2P" --merchant=<slug|id> --mode=test
```
Prints the API key ONCE. Store it.

## Smoke test (simulate mode)
```
curl -s https://delpago.co/api/v1/charges \
  -H "Authorization: Bearer dp_test_..." \
  -H "Idempotency-Key: test-1" \
  -H "Content-Type: application/json" \
  -d '{"amount":100000,"currency":"COP","reference":"ord_1","description":"Test"}'
```

## Security model
Non-custodial: charges settle to the merchant's own connector; DelPago never
holds principal. Keys hashed (never stored plaintext), live/test separated,
scoped, revocable. Idempotency on writes. Per-key rate limits. Tenant-isolated.

## Notes / Phase 2
- Sub-merchant resolution is slug/id-based in Phase 1; a `partner_sub_merchants`
  table + connect-link onboarding comes in Phase 2.
- The `balance` endpoint joins fees via `orders.partner_id`; if `fee_entries`
  links by `payment_id` only, the installer prints a WARN and that query needs a
  payment→order map (addressed in the deploy step).

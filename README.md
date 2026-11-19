<p align="center">
  <img src=".github/banner.svg" alt="DelPago Partner Payments API" width="640">
</p>

<p align="center">
  <strong>Non-custodial payment infrastructure for platforms.</strong><br>
  One integration gives your marketplace, freelance platform, exchange, web3 app or domain marketplace<br>
  access to 21+ payment gateways, hosted checkout, and direct settlement — DelPago never touches the funds.
</p>

<p align="center">
  <img src="https://img.shields.io/badge/status-Phase%201%20live-2ea043?style=flat-square" alt="status">
  <img src="https://img.shields.io/badge/API-REST%20%C2%B7%20JSON-1766ff?style=flat-square" alt="rest">
  <img src="https://img.shields.io/badge/model-non--custodial-f5b027?style=flat-square" alt="non-custodial">
  <img src="https://img.shields.io/badge/framework-Laravel-ff2d20?style=flat-square" alt="laravel">
</p>

---

## What this is

DelPago is a **checkout engine turned into infrastructure**. It already solves the hard part — a non-custodial, multi-processor charging layer (21+ gateways behind one interface), a fee ledger, and webhook plumbing. The Partner API exposes that engine so other platforms can build payments on top with a single integration, and DelPago earns a fee on every transaction they push through it.

**Who it's for**

| Platform | What they do with it |
|---|---|
| **P2P marketplaces** | Charge the buyer, release to the seller on completion |
| **Freelance platforms** | Collect from the client, split commission + freelancer payout |
| **Crypto exchanges** | Fiat on-ramp / off-ramp |
| **Web3 platforms** | Fiat entry for users without crypto, plus native crypto rails |
| **Domain marketplaces** | Buyer pays, seller receives, platform keeps a cut |

## Why non-custodial matters

DelPago **never receives or holds the funds**. Each charge settles directly to the merchant's own payment-gateway account (Bold, Wompi, Mercado Pago, Stripe, and more). DelPago only records the transaction and bills its fee separately — it is never in the money path. That keeps it a technology provider, not a money transmitter, and keeps cardholder data out of scope (the gateway's hosted checkout handles the card).

## Quick start

```bash
# Create a charge
curl -s https://delpago.co/api/v1/charges \
  -H "Authorization: Bearer dp_live_..." \
  -H "Idempotency-Key: ord_8842" \
  -H "Content-Type: application/json" \
  -d '{
    "amount": 100000,
    "currency": "COP",
    "reference": "ord_8842",
    "description": "Reserva #8842"
  }'
```

```json
{
  "id": "DP-ORD-9-AB12CD34",
  "status": "pending",
  "amount": 100000,
  "currency": "COP",
  "checkout_url": "https://checkout.bold.co/payment/…"
}
```

Redirect your user to `checkout_url`. On payment, DelPago fires a signed `charge.paid` webhook to your registered URL.

## Endpoints (Phase 1)

| Method | Endpoint | Purpose |
|---|---|---|
| `POST` | `/api/v1/charges` | Create a charge → hosted checkout URL (idempotent) |
| `GET`  | `/api/v1/charges/{id}` | Charge status (`pending` · `approved` · `rejected`) |
| `GET`  | `/api/v1/balance` | DelPago fee owed + partner markup accrued |

## Authentication

Bearer API keys, live and test (`dp_live_…` / `dp_test_…`). Keys are hashed (never stored in plaintext), scoped, revocable, and shown once. Pass as a Bearer token:

```
Authorization: Bearer dp_live_xxxxxxxxxxxxxxxx
```

## Webhooks

Status changes POST a signed event to your URL. Verify the signature before trusting it:

```
X-DelPago-Signature: t=<timestamp>,v1=<hmac_sha256(timestamp . "." . body, secret)>
```

Events: `charge.paid`, `charge.failed`, `charge.refunded`.

## Architecture

The API is a thin, stateless layer that composes DelPago's existing services — it owns no money and no new settlement logic:

```
Partner → /api/v1/charges → OrderService → ConnectorManager → gateway checkout
                                   ↓ (on settle)
                            FeeService (tags partner) → signed charge.paid webhook
```

## Install (Laravel app)

```bash
bash install.sh
```

Idempotent: copies the models/controllers/middleware, registers the `partner` middleware + API routes, wires the webhook into `OrderService::confirmPaid`, and runs the migrations. Backs up every file it edits.

```bash
# mint a test partner + key
php artisan partner:create --name="Acme P2P" --merchant=<slug> --mode=test
```

## Roadmap

- **Phase 1 ✅** — charges, status, idempotency, partner auth, fee ledger, `charge.paid` webhook
- **Phase 2** — split model + sub-merchants (per-seller connectors, partner markup)
- **Phase 3** — refunds, disputes, balance
- **Phase 4** — partner dashboard + automated settlement
- **Phase 5** — payouts + native crypto rail (PrimeBorg)

## Access

The API is available to partners. Request keys at **Contact@delpago.co** — docs at **[delpago.co/developers](https://delpago.co/developers.html)**.

---

<p align="center">
  <sub>© 2026 DelPago · Infraestructura tecnológica para pagos descentralizados, modelo no custodial · Colombia y mercados internacionales.</sub>
</p>

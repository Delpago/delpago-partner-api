#!/usr/bin/env bash
# DelPago Partner API — Phase 1 installer (idempotent).
# Run INSIDE the delpago container: php /root/partner-api/install.sh is NOT how —
# this is a bash script; run: bash /root/partner-api/install.sh
set -uo pipefail
APP=/var/www/delpago-app
SRC="$(cd "$(dirname "$0")" && pwd)"
echo "== DelPago Partner API install =="
echo "src: $SRC"

# 1) Copy code into place
cp -v "$SRC"/app/Models/Partner.php            "$APP/app/Models/"
cp -v "$SRC"/app/Models/PartnerApiKey.php      "$APP/app/Models/"
cp -v "$SRC"/app/Models/PartnerWebhook.php     "$APP/app/Models/"
cp -v "$SRC"/app/Models/IdempotencyRecord.php  "$APP/app/Models/"
mkdir -p "$APP/app/Http/Controllers/Api/V1"
cp -v "$SRC"/app/Http/Controllers/Api/V1/ChargeController.php  "$APP/app/Http/Controllers/Api/V1/"
cp -v "$SRC"/app/Http/Controllers/Api/V1/PartnerController.php "$APP/app/Http/Controllers/Api/V1/"
cp -v "$SRC"/app/Http/Middleware/AuthenticatePartner.php      "$APP/app/Http/Middleware/"
cp -v "$SRC"/app/Services/PartnerChargeService.php            "$APP/app/Services/"
cp -v "$SRC"/app/Services/PartnerWebhookDispatcher.php        "$APP/app/Services/"
cp -v "$SRC"/app/Console/Commands/PartnerCreate.php           "$APP/app/Console/Commands/"
cp -v "$SRC"/routes/api.php                                   "$APP/routes/api.php"
cp -v "$SRC"/database/migrations/*.php                        "$APP/database/migrations/"

# 2) Patch bootstrap/app.php — register 'partner' middleware alias + api routes.
php "$SRC/patch_bootstrap.php"

# 3) Patch Order model fillable + relations; hook webhook into OrderService.
php "$SRC/patch_wire.php"

# 4) Migrate
cd "$APP" && php artisan migrate --force
php artisan config:clear >/dev/null 2>&1
php artisan route:clear >/dev/null 2>&1

echo "== route check =="
php artisan route:list 2>/dev/null | grep -E 'v1/charges|v1/balance' || echo "WARN: v1 routes not listed"
echo "== done =="

<?php

namespace App\Console\Commands;

use App\Models\Merchant;
use App\Models\Partner;
use App\Models\PartnerApiKey;
use Illuminate\Console\Command;

/**
 * Create a partner and mint its first API key (for testing before the dashboard).
 *   php artisan partner:create --name="Acme P2P" --merchant=<slug|id> --mode=test --fee-bps=90
 * The plaintext key is printed ONCE — capture it; it is never stored.
 */
class PartnerCreate extends Command
{
    protected $signature = 'partner:create
        {--name= : Partner display name}
        {--merchant= : Default merchant slug or id whose connectors collect}
        {--mode=test : live|test for the first key}
        {--fee-bps=90 : DelPago fee in basis points (90 = 0.9%)}
        {--email= : Contact email}';

    protected $description = 'Create a partner + mint its first API key';

    public function handle(): int
    {
        $name = (string) ($this->option('name') ?: 'Partner');
        $merchantOpt = $this->option('merchant');
        $merchant = null;
        if ($merchantOpt) {
            $merchant = Merchant::where('slug', $merchantOpt)->first()
                ?? Merchant::find(is_numeric($merchantOpt) ? (int) $merchantOpt : 0);
            if (! $merchant) { $this->error("Merchant '{$merchantOpt}' not found."); return 1; }
        }

        $partner = Partner::create([
            'name'                => $name,
            'status'              => 'active',
            'fee_bps'             => (int) $this->option('fee-bps'),
            'default_merchant_id' => $merchant?->id,
            'contact_email'       => $this->option('email'),
        ]);

        [$key, $plain] = PartnerApiKey::mint(
            $partner,
            $this->option('mode') === 'live' ? 'live' : 'test',
            ['*'],
            'first key'
        );

        $this->info("Partner created: #{$partner->id} {$partner->name}");
        $this->info("Default merchant: " . ($merchant ? ($merchant->slug ?? $merchant->id) : '(none — set one before live charges)'));
        $this->info("Fee: {$partner->fee_bps} bps");
        $this->newLine();
        $this->warn("API KEY (shown once, store it now):");
        $this->line($plain);
        return 0;
    }
}

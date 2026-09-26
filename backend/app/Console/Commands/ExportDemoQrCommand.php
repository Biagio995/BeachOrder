<?php

namespace App\Console\Commands;

use App\Models\Location;
use App\Models\Tenant;
use App\Support\TenantContext;
use Endroid\QrCode\Color\Color;
use Endroid\QrCode\Encoding\Encoding;
use Endroid\QrCode\ErrorCorrectionLevel;
use Endroid\QrCode\QrCode;
use Endroid\QrCode\RoundBlockSizeMode;
use Endroid\QrCode\Writer\PngWriter;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class ExportDemoQrCommand extends Command
{
    protected $signature = 'demo:export-qr
        {--tenant= : Tenant slug (default: demo.tenant_slug)}
        {--output= : Output directory (default: demo.qr_output_path)}
        {--size= : PNG size in pixels}
        {--margin= : Quiet-zone margin in pixels}';

    protected $description = 'Generate one QR PNG per table/umbrella for the demo venue';

    public function handle(): int
    {
        $slug = (string) ($this->option('tenant') ?: config('demo.tenant_slug', 'lido-azzurra'));

        $tenant = Tenant::query()->where('slug', $slug)->first();

        if (! $tenant) {
            $this->error("Tenant [{$slug}] not found. Run the demo seeder first: php artisan db:seed --class=DemoSeeder");

            return self::FAILURE;
        }

        TenantContext::set($tenant);

        try {
            $locations = Location::query()
                ->where('is_active', true)
                ->orderBy('zone')
                ->orderBy('name')
                ->get();

            if ($locations->isEmpty()) {
                $this->error("Tenant [{$slug}] has no active locations.");

                return self::FAILURE;
            }

            $output = (string) ($this->option('output') ?: config('demo.qr_output_path'));
            $size = (int) ($this->option('size') ?: config('demo.qr_size', 640));
            $margin = (int) ($this->option('margin') ?: config('demo.qr_margin', 16));
            $base = rtrim((string) config('demo.public_url'), '/');

            File::ensureDirectoryExists($output);

            $writer = new PngWriter;

            foreach ($locations as $location) {
                // Stable QR payload: the printed QR never changes; each scan
                // mints a fresh one-order access token server-side.
                $url = "{$base}/t/{$tenant->slug}/q/{$location->code}";

                $qr = new QrCode(
                    data: $url,
                    encoding: new Encoding('UTF-8'),
                    errorCorrectionLevel: ErrorCorrectionLevel::Medium,
                    size: $size,
                    margin: $margin,
                    foregroundColor: new Color(0, 0, 0),
                    backgroundColor: new Color(255, 255, 255),
                    roundBlockSizeMode: RoundBlockSizeMode::Margin,
                );

                $filename = $location->slug.'.png';
                $writer->write($qr)->saveToFile($output.DIRECTORY_SEPARATOR.$filename);

                $this->line("{$location->name} [{$location->code}] -> {$filename}");
                $this->line("  {$url}");
            }

            $this->info("Wrote {$locations->count()} QR codes to {$output}");

            return self::SUCCESS;
        } finally {
            TenantContext::clear();
        }
    }
}

<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use MaxMind\Db\Reader;
use Throwable;

/**
 * Downloads the local GeoIP country database used by the analytics
 * (default: DB-IP "IP to Country Lite", CC BY 4.0, refreshed monthly by
 * DB-IP). The new file replaces the old one only once it opens and answers
 * a lookup, so a bad download never removes a working database.
 */
class UpdateGeoIpDatabase extends Command
{
    protected $signature = 'analytics:geoip-update {--month= : YYYY-MM to download (default: current month)}';

    protected $description = 'Download/refresh the local GeoIP country database for the analytics';

    public function handle(): int
    {
        $month = $this->option('month') ?: now()->format('Y-m');
        [$year, $mm] = explode('-', $month);
        $url    = str_replace(['{YYYY}', '{MM}'], [$year, $mm], config('analytics.geoip.download_url'));
        $target = config('analytics.geoip.database');
        $tmpGz  = $target . '.download.gz';
        $tmp    = $target . '.download';

        File::ensureDirectoryExists(dirname($target));

        try {
            $response = Http::timeout(120)->sink($tmpGz)->get($url);
            if (! $response->successful()) {
                // Early in the month the new file may not be published yet.
                $this->warn("Download failed ({$response->status()}) for {$url}; keeping the current database.");

                return self::FAILURE;
            }

            $in = gzopen($tmpGz, 'rb');
            $out = fopen($tmp, 'wb');
            while (! gzeof($in)) {
                fwrite($out, gzread($in, 1 << 20));
            }
            gzclose($in);
            fclose($out);

            $reader = new Reader($tmp);
            $reader->get('8.8.8.8');
            $type = $reader->metadata()->databaseType;
            $reader->close();

            rename($tmp, $target);
            $this->info("GeoIP database updated ({$type}, {$month}).");

            return self::SUCCESS;
        } catch (Throwable $e) {
            report($e);
            $this->error('GeoIP update failed: ' . $e->getMessage());

            return self::FAILURE;
        } finally {
            @unlink($tmpGz);
            @unlink($tmp);
        }
    }
}

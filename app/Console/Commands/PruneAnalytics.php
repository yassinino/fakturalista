<?php

namespace App\Console\Commands;

use App\Services\Analytics\AnalyticsPeriod;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Daily privacy housekeeping for the first-party analytics:
 *  - deletes page views and conversions older than analytics.retention_days;
 *  - destroys every visitor-hash salt except today's, which makes past
 *    visitor hashes impossible to recompute or link to anyone.
 */
class PruneAnalytics extends Command
{
    protected $signature = 'analytics:prune';

    protected $description = 'Apply the analytics retention policy and destroy past visitor-hash salts';

    public function handle(): int
    {
        $today  = AnalyticsPeriod::today();
        $cutoff = $today->subDays(max(1, (int) config('analytics.retention_days')))->toDateString();
        $db     = DB::connection('mysql');

        $views = 0;
        do {
            $deleted = $db->table('analytics_pageviews')->where('view_date', '<', $cutoff)->limit(5000)->delete();
            $views += $deleted;
        } while ($deleted > 0);

        $conversions = $db->table('analytics_conversions')->where('conversion_date', '<', $cutoff)->delete();
        $salts       = $db->table('analytics_salts')->where('salt_date', '<', $today->toDateString())->delete();

        $this->info("Deleted {$views} page views and {$conversions} conversions before {$cutoff}; destroyed {$salts} past salt(s).");

        return self::SUCCESS;
    }
}

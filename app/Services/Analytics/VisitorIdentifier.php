<?php

namespace App\Services\Analytics;

use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

/**
 * Anonymous daily visitor identifier (no cookie, nothing stored on the
 * device): sha256(daily random salt | IP | user agent), truncated.
 *
 * The IP address only exists in memory for this computation and is never
 * written anywhere. Each day has its own random salt, kept server-side and
 * deleted by analytics:prune once the day is over - after that, nobody
 * (including us) can recompute or link a hash to a person or across days.
 */
class VisitorIdentifier
{
    /** @var array<string, string> salts already read in this request */
    private array $salts = [];

    public function forRequest(?string $ip, ?string $userAgent, CarbonInterface $localDay): string
    {
        return substr(hash('sha256', $this->salt($localDay) . '|' . $ip . '|' . $userAgent), 0, 32);
    }

    private function salt(CarbonInterface $localDay): string
    {
        $date = $localDay->toDateString();

        if (isset($this->salts[$date])) {
            return $this->salts[$date];
        }

        $table = DB::connection('mysql')->table('analytics_salts');
        // insertOrIgnore: two concurrent first requests of the day agree on one salt.
        $table->insertOrIgnore(['salt_date' => $date, 'salt' => bin2hex(random_bytes(32)), 'created_at' => now()]);

        return $this->salts[$date] = (string) $table->where('salt_date', $date)->value('salt');
    }
}

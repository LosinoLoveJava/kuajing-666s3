<?php

namespace App\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class ShopViewCounter
{
    public const DEFAULT_DAILY_INCREMENT = 1440;

    public function sync(): void
    {
        $now = Carbon::now(config('app.timezone'));
        $dayStart = $now->copy()->startOfDay();
        $minutesInDay = (int) (($dayStart->copy()->addDay()->timestamp - $dayStart->timestamp) / 60);
        // Include the current minute so the full daily quota is reached at 23:59.
        $minute = min($minutesInDay, (int) (($now->timestamp - $dayStart->timestamp) / 60) + 1);

        DB::table('shops')->select('id')->orderBy('id')->chunkById(200, function ($shops) use ($now, $dayStart, $minutesInDay, $minute) {
            foreach ($shops as $shop) {
                DB::transaction(function () use ($shop, $now, $dayStart, $minutesInDay, $minute) {
                    $row = DB::table('shops')->where('id', $shop->id)->lockForUpdate()->first();
                    // An older overlapping invocation must not reset a newer day's values.
                    if (!$row || (int) $row->views_up_time > $now->timestamp) {
                        return;
                    }

                    $newDay = (int) $row->views_up_time < $dayStart->timestamp;
                    $views = $newDay ? max(0, (int) $row->view_base_num) : (int) $row->views;
                    $added = $newDay ? 0 : max(0, (int) $row->view_today_num);
                    $target = intdiv(max(0, (int) $row->view_inc_num) * $minute, $minutesInDay);
                    $delta = max(0, $target - $added);

                    DB::table('shops')->where('id', $row->id)->update([
                        'views' => $views + $delta,
                        'view_today_num' => $added + $delta,
                        'views_up_time' => $now->timestamp,
                    ]);
                }, 3);
            }
        });
    }

    public function configure(int $shopId, int $base, int $dailyIncrement): void
    {
        // One SQL update also serializes configuration changes with the scheduler.
        $updated = DB::table('shops')->where('id', $shopId)->update([
            'view_base_num' => $base,
            'view_inc_num' => $dailyIncrement,
            'views' => $base,
            'view_today_num' => 0,
            'views_up_time' => 0,
        ]);

        if (!$updated && !DB::table('shops')->where('id', $shopId)->exists()) {
            abort(404);
        }
    }
}

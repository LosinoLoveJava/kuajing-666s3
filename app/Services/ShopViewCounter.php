<?php

namespace App\Services;

use Carbon\Carbon;
use App\Models\Shop;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class ShopViewCounter
{
    public const DEFAULT_DAILY_INCREMENT = 0;

    public function recordVisit(Request $request, Shop $shop): bool
    {
        $user = $request->user();
        $agent = (string) $request->userAgent();
        if (!$request->isMethod('GET') || $request->ajax() || $request->expectsJson()
            || ($user && (int) $user->id === (int) $shop->user_id)
            || !$request->hasSession() || $agent === ''
            || preg_match('/bot|crawl|spider|slurp|headless|preview|fetch|scrapy|python|curl|wget|httpclient|http-client|go-http|okhttp|selenium|puppeteer|playwright|phantomjs|lighthouse|pagespeed|facebookexternalhit|whatsapp|telegram|pinterest|monitor|uptime/i', $agent)
            || !preg_match('/Mozilla\/5\.0.*(?:Chrome\/|Firefox\/|Safari\/)/i', $agent)
            || $request->headers->get('Sec-Fetch-Dest', 'document') !== 'document'
            || preg_match('/prefetch|prerender/i', $request->header('Purpose', '').' '.$request->header('Sec-Purpose', ''))) {
            return false;
        }

        $visitor = $user ? 'user:'.$user->id : 'session:'.$request->session()->getId();
        $key = 'shop-visit:'.$shop->id.':'.hash_hmac('sha256', $visitor, (string) config('app.key'));
        $now = Carbon::now(config('app.timezone'));
        try {
            $cache = Cache::store('shop_views');
            // FileStore::add holds an exclusive lock, including across PHP workers.
            // A stable key expires at midnight instead of leaving a new file every day.
            if (!$cache->add($key, true, $now->copy()->addDay()->startOfDay())) {
                return false;
            }
            return $this->syncShop((int) $shop->id, $now, 1);
        } catch (\Throwable $error) {
            // Statistics must not prevent a product page from loading; never count without a guard.
            report($error);
            return false;
        }
    }

    public function sync(): void
    {
        $now = Carbon::now(config('app.timezone'));
        DB::table('shops')->select('id')->orderBy('id')->chunkById(200, function ($shops) use ($now) {
            foreach ($shops as $shop) {
                $this->syncShop((int) $shop->id, $now);
            }
        });
    }

    private function syncShop(int $shopId, Carbon $now, int $visits = 0): bool
    {
        $dayStart = $now->copy()->startOfDay();
        $minutesInDay = (int) (($dayStart->copy()->addDay()->timestamp - $dayStart->timestamp) / 60);
        // Include the current minute so the full daily quota is reached at 23:59.
        $minute = min($minutesInDay, (int) (($now->timestamp - $dayStart->timestamp) / 60) + 1);

        return DB::transaction(function () use ($shopId, $now, $dayStart, $minutesInDay, $minute, $visits) {
            $row = DB::table('shops')->where('id', $shopId)->lockForUpdate()->first();
            // An older overlapping invocation must not reset a newer day's values.
            if (!$row || (int) $row->views_up_time > $now->timestamp) {
                return false;
            }

            $newDay = (int) $row->views_up_time < $dayStart->timestamp;
            $views = $newDay ? max(0, (int) $row->view_base_num) : (int) $row->views;
            $added = $newDay ? 0 : max(0, (int) $row->view_today_num);
            $target = intdiv(max(0, (int) $row->view_inc_num) * $minute, $minutesInDay);
            $delta = max(0, $target - $added);

            DB::table('shops')->where('id', $row->id)->update([
                'views' => $views + $delta + $visits,
                'view_today_num' => $added + $delta,
                'views_up_time' => $now->timestamp,
            ]);
            return true;
        }, 3);
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

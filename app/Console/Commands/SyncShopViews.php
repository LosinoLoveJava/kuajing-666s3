<?php

namespace App\Console\Commands;

use App\Services\ShopViewCounter;
use Illuminate\Console\Command;

class SyncShopViews extends Command
{
    protected $signature = 'shops:sync-views';

    protected $description = 'Reset daily shop views and apply the configured automatic increment';

    public function handle(ShopViewCounter $counter)
    {
        $counter->sync();
        $this->info('Shop views synchronized.');

        return 0;
    }
}

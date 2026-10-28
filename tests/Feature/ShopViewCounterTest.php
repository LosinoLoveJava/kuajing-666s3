<?php

namespace Tests\Feature;

use App\Http\Controllers\HomeController;
use App\Http\Controllers\SellerController;
use App\Models\Shop;
use App\Services\ShopViewCounter;
use Carbon\Carbon;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class ShopViewCounterTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:',
            'cache.default' => 'array', 'session.driver' => 'array', 'app.timezone' => 'Asia/Shanghai']);
        DB::purge('sqlite');
        Carbon::setTestNow(Carbon::parse('2026-10-28 00:00:00', 'Asia/Shanghai'));
        Schema::create('shops', function (Blueprint $t) {
            $t->increments('id');
            $t->integer('views')->default(100);
            $t->integer('view_base_num')->default(100);
            $t->integer('view_inc_num')->default(1440);
            $t->integer('view_today_num')->default(0);
            $t->integer('views_up_time')->default(0);
        });
        Schema::create('translations', function (Blueprint $t) {
            $t->increments('id'); $t->string('lang'); $t->string('lang_key');
            $t->text('lang_value'); $t->timestamps();
        });
        DB::table('shops')->insert(['id' => 1]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function syncAt($time): void
    {
        Carbon::setTestNow(Carbon::parse($time, 'Asia/Shanghai'));
        app(ShopViewCounter::class)->sync();
    }

    public function testNewShopsGrowByDefaultWhileExplicitZeroStaysDisabled()
    {
        // The model default must override the legacy database default of zero.
        Schema::drop('shops');
        Schema::create('shops', function (Blueprint $t) {
            $t->increments('id'); $t->integer('views')->default(0);
            $t->integer('view_base_num')->default(0); $t->integer('view_inc_num')->default(0);
            $t->integer('view_today_num')->default(0); $t->integer('views_up_time')->default(0);
            $t->timestamps();
        });
        Shop::withoutEvents(function () {
            $enabled = new Shop;
            $enabled->save();
            $disabled = new Shop;
            $disabled->view_inc_num = 0;
            $disabled->save();
        });
        $this->syncAt('2026-10-28 00:00:00');
        $this->assertDatabaseHas('shops', ['id' => 1, 'view_inc_num' => 1440, 'views' => 1]);
        $this->assertDatabaseHas('shops', ['id' => 2, 'view_inc_num' => 0, 'views' => 0]);
    }

    public function testUpgradeEnablesExistingZeroAndNullSettingsAndPreservesCustomQuotas()
    {
        Schema::drop('shops');
        Schema::create('shops', function (Blueprint $t) {
            $t->increments('id'); $t->integer('view_inc_num')->nullable();
            $t->integer('views')->default(88); $t->integer('view_base_num')->default(20);
            $t->integer('view_today_num')->default(7); $t->integer('views_up_time')->default(1);
        });
        DB::table('shops')->insert([
            ['id' => 1, 'view_inc_num' => 0], ['id' => 2, 'view_inc_num' => null],
            ['id' => 3, 'view_inc_num' => 200],
        ]);
        require_once database_path('migrations/2026_10_28_100000_enable_shop_view_increment_by_default.php');
        (new \EnableShopViewIncrementByDefault)->up();
        $this->assertDatabaseHas('shops', ['id' => 1, 'view_inc_num' => 1440, 'views' => 88,
            'view_base_num' => 20, 'view_today_num' => 7, 'views_up_time' => 1]);
        $this->assertDatabaseHas('shops', ['id' => 2, 'view_inc_num' => 1440]);
        $this->assertDatabaseHas('shops', ['id' => 3, 'view_inc_num' => 200]);
    }

    public function testMinuteUpdatesAndRepeatedLegacyCallsPreserveRealViews()
    {
        $this->syncAt('2026-10-28 00:00:00');
        $this->assertDatabaseHas('shops', ['id' => 1, 'views' => 101, 'view_today_num' => 1]);
        DB::table('shops')->where('id', 1)->increment('views', 7);
        $response = app(HomeController::class)->viewcron(app(ShopViewCounter::class));
        $this->assertSame('ok', $response->getContent());
        $this->assertDatabaseHas('shops', ['id' => 1, 'views' => 108, 'view_today_num' => 1]);
        $this->syncAt('2026-10-28 00:01:00');
        $this->assertDatabaseHas('shops', ['id' => 1, 'views' => 109, 'view_today_num' => 2]);
    }

    public function testMissedRunsCatchUpOnlyForTodayAndReachTheDailyQuota()
    {
        $this->syncAt('2026-10-28 11:59:00');
        $this->assertDatabaseHas('shops', ['id' => 1, 'views' => 820, 'view_today_num' => 720]);
        $this->syncAt('2026-10-28 23:59:00');
        $this->syncAt('2026-10-28 23:59:59');
        $this->assertDatabaseHas('shops', ['id' => 1, 'views' => 1540, 'view_today_num' => 1440]);
        $this->syncAt('2026-10-30 00:00:00');
        $this->assertDatabaseHas('shops', ['id' => 1, 'views' => 101, 'view_today_num' => 1]);
    }

    public function testDisabledAndDifferentDailyQuotasRemainIndependent()
    {
        DB::table('shops')->where('id', 1)->update(['view_inc_num' => 0]);
        DB::table('shops')->insert([
            ['id' => 2, 'view_inc_num' => 7], ['id' => 3, 'view_inc_num' => 10000],
        ]);
        $this->syncAt('2026-10-28 11:59:00');
        $this->assertDatabaseHas('shops', ['id' => 1, 'views' => 100, 'view_today_num' => 0]);
        $this->assertDatabaseHas('shops', ['id' => 2, 'views' => 103, 'view_today_num' => 3]);
        $this->assertDatabaseHas('shops', ['id' => 3, 'views' => 5100, 'view_today_num' => 5000]);
        DB::table('shops')->where('id', 1)->increment('views', 4);
        $this->syncAt('2026-10-28 23:59:00');
        $this->assertDatabaseHas('shops', ['id' => 1, 'views' => 104, 'view_today_num' => 0]);
        $this->assertDatabaseHas('shops', ['id' => 2, 'views' => 107, 'view_today_num' => 7]);
        $this->assertDatabaseHas('shops', ['id' => 3, 'views' => 10100, 'view_today_num' => 10000]);
        $this->syncAt('2026-10-29 00:00:00');
        $this->assertDatabaseHas('shops', ['id' => 1, 'views' => 100, 'view_today_num' => 0]);
    }

    public function testTimezoneBoundaryAndAnOlderInvocationCannotResetNewerProgress()
    {
        $this->syncAt('2026-10-29 00:00:00'); // Still October 28 in UTC.
        $this->assertDatabaseHas('shops', ['id' => 1, 'views' => 101, 'view_today_num' => 1]);
        $this->syncAt('2026-10-28 23:59:59');
        $this->assertDatabaseHas('shops', ['id' => 1, 'views' => 101, 'view_today_num' => 1]);
    }

    public function testSavingConfigurationClearsOldProgressAndCanDisableGrowth()
    {
        $this->syncAt('2026-10-28 11:59:00');
        $controller = app(SellerController::class);
        $response = $controller->setviews(Request::create('/admin/sellers/setviews', 'POST', [
            'shop_id' => 1, 'base_num' => 200, 'inc_num' => 2880,
        ]));
        $this->assertSame(200, $response->getStatusCode());
        $this->assertDatabaseHas('shops', ['id' => 1, 'views' => 200, 'view_today_num' => 0]);
        app(ShopViewCounter::class)->sync();
        $this->assertDatabaseHas('shops', ['id' => 1, 'views' => 1640, 'view_today_num' => 1440]);
        $controller->setviews(Request::create('/admin/sellers/setviews', 'POST', [
            'shop_id' => 1, 'base_num' => 200, 'inc_num' => 0,
        ]));
        $this->syncAt('2026-10-28 23:59:00');
        $this->assertDatabaseHas('shops', ['id' => 1, 'views' => 200, 'view_today_num' => 0]);
    }

    /** @dataProvider invalidSettings */
    public function testInvalidSettingsDoNotChangeCounters(array $invalid)
    {
        try {
            app(SellerController::class)->setviews(Request::create('/admin/sellers/setviews', 'POST',
                array_merge(['shop_id' => 1, 'base_num' => 100, 'inc_num' => 1440], $invalid)));
            $this->fail('Invalid counter settings were accepted.');
        } catch (ValidationException $e) {
            $this->assertDatabaseHas('shops', ['id' => 1, 'views' => 100, 'view_inc_num' => 1440]);
        }
    }

    public function invalidSettings()
    {
        return [
            [['base_num' => -1]], [['inc_num' => -1]], [['inc_num' => 1.5]],
            [['inc_num' => 'abc']], [['base_num' => null]], [['inc_num' => 1000000001]],
            [['shop_id' => 999]],
        ];
    }

    public function testCommandIsRegisteredAndScheduledEveryMinute()
    {
        $this->artisan('shops:sync-views')->expectsOutput('Shop views synchronized.')->assertExitCode(0);
        $this->assertDatabaseHas('shops', ['id' => 1, 'views' => 101]);
        $events = collect(app(Schedule::class)->events())->filter(function ($event) {
            return strpos((string) $event->command, 'shops:sync-views') !== false;
        });
        $this->assertCount(1, $events);
        $this->assertSame('* * * * *', $events->first()->expression);
        $this->assertTrue($events->first()->withoutOverlapping);
    }
}

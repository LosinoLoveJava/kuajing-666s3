<?php

namespace Tests\Feature;

use App\Http\Controllers\HomeController;
use App\Models\Shop;
use App\Models\User;
use App\Services\ShopViewCounter;
use Carbon\Carbon;
use Illuminate\Cache\Repository;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Http\Request;
use Illuminate\Session\ArraySessionHandler;
use Illuminate\Session\Store;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\Process\Process;
use Tests\TestCase;

class ShopVisitTest extends TestCase
{
    private $directory;
    private const BROWSER = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36';

    protected function setUp(): void
    {
        parent::setUp();
        $this->directory = storage_path('framework/testing/shop-visits-'.bin2hex(random_bytes(8)));
        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:',
            'cache.default' => 'array', 'cache.stores.shop_views.path' => $this->directory,
            'app.timezone' => 'Asia/Shanghai']);
        DB::purge('sqlite');
        Carbon::setTestNow(Carbon::parse('2026-11-27 12:00:00', 'Asia/Shanghai'));
        Schema::create('shops', function (Blueprint $t) {
            $t->increments('id'); $t->integer('views')->default(0);
            $t->integer('view_base_num')->default(0); $t->integer('view_inc_num')->default(0);
            $t->integer('view_today_num')->default(0); $t->integer('views_up_time')->default(0);
        });
        DB::table('shops')->insert([['id' => 1], ['id' => 2]]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        (new Filesystem)->deleteDirectory($this->directory);
        parent::tearDown();
    }

    private function browser($sessionId = 'a', $userId = null): Request
    {
        $request = Request::create('/product/fixture', 'GET', [], [], [], [
            'HTTP_USER_AGENT' => self::BROWSER, 'HTTP_ACCEPT' => 'text/html',
            'HTTP_SEC_FETCH_DEST' => 'document', 'REMOTE_ADDR' => '192.0.2.1',
        ]);
        $session = new Store('visit_test', new ArraySessionHandler(120));
        $session->setId(str_repeat($sessionId, 40));
        $session->start();
        $request->setLaravelSession($session);
        $request->setUserResolver(function () use ($userId) {
            return $userId === null ? null : (new User)->forceFill(['id' => $userId]);
        });
        return $request;
    }

    private function visit(Request $request, $shopId = 1): bool
    {
        $shop = (new Shop)->setRawAttributes(['id' => $shopId, 'user_id' => 10]);
        return app(ShopViewCounter::class)->recordVisit($request, $shop);
    }

    public function testRefreshesAndDifferentProductsInTheSameShopCountOnceAcrossRequests()
    {
        $this->assertTrue($this->visit($this->browser()));
        $request = $this->browser();
        $request->server->set('REQUEST_URI', '/product/another?refresh=123');
        // A new repository uses the same persistent guard, independent of the default cache.
        app('cache')->forgetDriver('shop_views');
        Cache::flush();
        $this->assertFalse($this->visit($request));
        $this->assertTrue($this->visit($this->browser('b')));
        $this->assertTrue($this->visit($this->browser(), 2));
        $this->assertDatabaseHas('shops', ['id' => 1, 'views' => 2]);
        $this->assertDatabaseHas('shops', ['id' => 2, 'views' => 1]);
    }

    public function testActualProductControllerFiltersBotsAndDeduplicatesPhysicalAndDigitalProducts()
    {
        Schema::table('shops', function (Blueprint $t) { $t->integer('user_id')->nullable(); });
        DB::table('shops')->where('id', 1)->update(['user_id' => 10]);
        Schema::create('users', function (Blueprint $t) { $t->increments('id'); });
        Schema::create('brands', function (Blueprint $t) { $t->increments('id'); });
        DB::table('users')->insert(['id' => 10]);
        Schema::create('products', function (Blueprint $t) {
            $t->increments('id'); $t->integer('user_id'); $t->string('slug'); $t->integer('brand_id')->nullable();
            $t->boolean('auction_product')->default(0); $t->boolean('approved')->default(1);
            $t->boolean('published')->default(1); $t->boolean('digital')->default(0);
        });
        DB::table('products')->insert([
            ['user_id' => 10, 'slug' => 'physical', 'digital' => 0],
            ['user_id' => 10, 'slug' => 'digital', 'digital' => 1],
        ]);
        foreach (['product_translations', 'product_taxes', 'product_stocks', 'reviews', 'product_queries'] as $table) {
            Schema::create($table, function (Blueprint $t) {
                $t->increments('id'); $t->integer('product_id');
                $t->integer('customer_id')->nullable(); $t->boolean('status')->default(1);
            });
        }
        $bot = $this->browser(); $bot->headers->set('User-Agent', self::BROWSER.' Googlebot/2.1');
        $this->app->instance('request', $bot);
        app(HomeController::class)->product($bot, 'physical');
        $this->assertDatabaseHas('shops', ['id' => 1, 'views' => 0]);
        foreach (['physical', 'physical', 'digital'] as $slug) {
            $request = $this->browser();
            $this->app->instance('request', $request);
            $view = app(HomeController::class)->product($request, $slug);
            $this->assertSame($slug === 'digital' ? 'frontend.digital_product_details' : 'frontend.product_details', $view->name());
        }
        $this->assertDatabaseHas('shops', ['id' => 1, 'views' => 1]);
    }

    public function testAuthenticatedVisitorIsDeduplicatedAcrossSessionsAndOwnersDoNotCount()
    {
        $this->assertFalse($this->visit($this->browser('a', 10)));
        $this->assertTrue($this->visit($this->browser('a', 20)));
        $this->assertFalse($this->visit($this->browser('b', 20)));
        $this->assertTrue($this->visit($this->browser('a', 21)));
        $this->assertDatabaseHas('shops', ['id' => 1, 'views' => 2]);
    }

    /** @dataProvider ignoredAgents */
    public function testCrawlersAndToolsAreIgnoredBeforeClaimingAVisit($agent)
    {
        $request = $this->browser();
        $request->headers->set('User-Agent', $agent);
        $this->assertFalse($this->visit($request));
        $this->assertDatabaseHas('shops', ['id' => 1, 'views' => 0]);
        $this->assertTrue($this->visit($this->browser()));
    }

    public function ignoredAgents()
    {
        return array_map(function ($agent) { return [$agent]; }, [
            '', 'Googlebot/2.1', self::BROWSER.' bingbot/2.0',
            self::BROWSER.' Bytespider', self::BROWSER.' GPTBot/1.0',
            str_replace('Chrome/', 'HeadlessChrome/', self::BROWSER),
            'facebookexternalhit/1.1', 'python-requests/2.32', 'curl/8.0',
            'Go-http-client/1.1', 'Amazonbot/0.1', 'Mozilla/5.0 (compatible; monitoring/1.0)',
        ]);
    }

    public function testHeadAjaxJsonPrefetchAndMissingSessionsDoNotCount()
    {
        $head = $this->browser(); $head->setMethod('HEAD');
        $ajax = $this->browser(); $ajax->headers->set('X-Requested-With', 'XMLHttpRequest');
        $json = $this->browser(); $json->headers->set('Accept', 'application/json');
        $prefetch = $this->browser(); $prefetch->headers->set('Purpose', 'prefetch');
        $prerender = $this->browser(); $prerender->headers->set('Sec-Purpose', 'prefetch;prerender');
        $image = $this->browser(); $image->headers->set('Sec-Fetch-Dest', 'image');
        $noSession = Request::create('/product/fixture', 'GET', [], [], [], ['HTTP_USER_AGENT' => self::BROWSER]);
        foreach ([$head, $ajax, $json, $prefetch, $prerender, $image, $noSession] as $request) {
            $this->assertFalse($this->visit($request));
        }
        $this->assertDatabaseHas('shops', ['id' => 1, 'views' => 0]);
        $this->assertTrue($this->visit($this->browser()));
    }

    public function testMidnightExpiresTheGuardAndTheSchedulerPreservesTheFirstVisit()
    {
        Carbon::setTestNow(Carbon::parse('2026-11-27 23:59:59', 'Asia/Shanghai'));
        $this->assertTrue($this->visit($this->browser()));
        $this->assertFalse($this->visit($this->browser()));
        Carbon::setTestNow(Carbon::parse('2026-11-28 00:00:00', 'Asia/Shanghai'));
        $this->assertTrue($this->visit($this->browser()));
        app(ShopViewCounter::class)->sync();
        $this->assertFalse($this->visit($this->browser()));
        $this->assertDatabaseHas('shops', ['id' => 1, 'views' => 1, 'view_today_num' => 0]);
    }

    public function testConfiguredDailyIncrementAndVisitsAreNotDoubleCounted()
    {
        app(ShopViewCounter::class)->configure(1, 100, 1440);
        $this->assertTrue($this->visit($this->browser()));
        app(ShopViewCounter::class)->sync();
        $this->assertFalse($this->visit($this->browser()));
        $this->assertDatabaseHas('shops', ['id' => 1, 'views' => 822, 'view_today_num' => 721]);
    }

    public function testFailureToClaimDoesNotCountOrBreakTheProductRequest()
    {
        $cache = \Mockery::mock(Repository::class);
        $cache->shouldReceive('add')->once()->andThrow(new \RuntimeException('Guard unavailable'));
        Cache::shouldReceive('store')->with('shop_views')->andReturn($cache);
        $this->assertFalse($this->visit($this->browser()));
        $this->assertDatabaseHas('shops', ['id' => 1, 'views' => 0]);
    }

    public function testConcurrentPhpWorkersCountTheSameVisitorOnlyOnce()
    {
        mkdir($this->directory, 0700, true);
        $database = $this->directory.'/concurrent.sqlite';
        $db = new \PDO('sqlite:'.$database);
        $db->exec('CREATE TABLE shops (id INTEGER PRIMARY KEY, views INTEGER DEFAULT 0, view_base_num INTEGER DEFAULT 0, view_inc_num INTEGER DEFAULT 0, view_today_num INTEGER DEFAULT 0, views_up_time INTEGER DEFAULT 0)');
        $db->exec('INSERT INTO shops (id) VALUES (1)');
        $worker = $this->directory.'/worker.php';
        file_put_contents($worker, <<<'PHP'
<?php
foreach ([1, 2, 3] as $index) $argv[$index] = base64_decode($argv[$index]);
require $argv[1].'/vendor/autoload.php';
$app = require $argv[1].'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => $argv[2],
    'cache.stores.shop_views.path' => $argv[3].'/guards', 'app.timezone' => 'Asia/Shanghai']);
Illuminate\Support\Facades\DB::purge('sqlite');
Carbon\Carbon::setTestNow(Carbon\Carbon::parse('2026-11-27 12:00:00', 'Asia/Shanghai'));
$request = Illuminate\Http\Request::create('/product/fixture', 'GET', [], [], [], ['HTTP_USER_AGENT' => $argv[4]]);
$session = new Illuminate\Session\Store('test', new Illuminate\Session\ArraySessionHandler(120));
$session->setId(str_repeat('a', 40)); $session->start(); $request->setLaravelSession($session);
$shop = (new App\Models\Shop)->setRawAttributes(['id' => 1, 'user_id' => 10]);
echo $app->make(App\Services\ShopViewCounter::class)->recordVisit($request, $shop) ? 'counted' : 'ignored';
PHP
        );
        $workers = [];
        for ($i = 0; $i < 6; ++$i) {
            // Keep CLI arguments ASCII on Windows; PHP's argv conversion can corrupt CJK paths.
            $process = new Process([PHP_BINARY, 'storage/framework/testing/'.basename($this->directory).'/worker.php',
                base64_encode(base_path()), base64_encode($database), base64_encode($this->directory), self::BROWSER], base_path(), ['APP_ENV' => 'testing']);
            $process->setTimeout(30);
            $process->start();
            $workers[] = $process;
        }
        $counted = 0;
        foreach ($workers as $process) {
            $process->wait();
            $this->assertTrue($process->isSuccessful(), $process->getErrorOutput().$process->getOutput().' Exit '.$process->getExitCode());
            $counted += trim($process->getOutput()) === 'counted' ? 1 : 0;
        }
        $this->assertSame(1, $counted);
        $this->assertSame(1, (int) $db->query('SELECT views FROM shops WHERE id=1')->fetchColumn());
    }
}

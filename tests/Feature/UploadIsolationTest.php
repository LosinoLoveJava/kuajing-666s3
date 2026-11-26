<?php

namespace Tests\Feature;

use App\Http\Controllers\AizUploadController;
use App\Models\Upload;
use App\Models\User;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Session\ArraySessionHandler;
use Illuminate\Session\Store;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class UploadIsolationTest extends TestCase
{
    private $root;

    protected function setUp(): void
    {
        parent::setUp();
        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:',
            'filesystems.default' => 'local']);
        DB::purge('sqlite');
        $this->root = sys_get_temp_dir().'/upload-isolation-'.bin2hex(random_bytes(8));
        mkdir($this->root, 0700);
        $this->app->instance('path.public', $this->root);
        config(['filesystems.disks.local.root' => $this->root]);
        config(['filesystems.disks.private.root' => $this->root.'/private-root']);
        Schema::create('users', function (Blueprint $table) {
            $table->increments('id'); $table->integer('pid')->default(0);
            $table->string('identity_card_front')->default(''); $table->string('identity_card_back')->default('');
        });
        Schema::create('uploads', function (Blueprint $table) {
            $table->increments('id'); $table->string('file_original_name');
            $table->string('file_name')->nullable(); $table->integer('user_id'); $table->string('extension');
            $table->string('type'); $table->integer('file_size'); $table->timestamps();
            $table->string('external_link')->nullable();
        });
        require_once database_path('migrations/2026_11_26_100000_add_guest_upload_ownership.php');
        (new \AddGuestUploadOwnership)->up();
    }

    protected function tearDown(): void
    {
        \Illuminate\Support\Facades\File::deleteDirectory($this->root);
        parent::tearDown();
    }

    private function browser()
    {
        $session = new Store('test-browser', new ArraySessionHandler(120));
        $session->start();
        return $session;
    }

    private function request($session, array $data = [], array $files = [])
    {
        $request = Request::create('/aiz-uploader/upload', 'POST', $data, [], $files);
        $request->setLaravelSession($session);
        $request->setUserResolver(function () { return auth()->user(); });
        return $request;
    }

    private function upload($session)
    {
        $response = app(AizUploadController::class)->upload($this->request($session, [], [
            'aiz_file' => UploadedFile::fake()->createWithContent('private.txt', 'test document'),
        ]));
        $this->assertSame(200, $response->getStatusCode());
        return Upload::latest('id')->firstOrFail();
    }

    public function testSeparateGuestBrowsersCannotListOrPreviewEachOthersUploads()
    {
        $controller = app(AizUploadController::class);
        $first = $this->browser(); $second = $this->browser();
        $one = $this->upload($first); $two = $this->upload($second);
        $legacy = $two->replicate(); $legacy->guest_token = null; $legacy->save();
        $this->assertNotSame($one->guest_token, $two->guest_token);
        foreach ([[$first, $one], [$second, $two]] as [$browser, $own]) {
            $request = $this->request($browser, ['ids' => $one->id.','.$two->id.','.$legacy->id]);
            $this->assertSame([$own->id], $controller->get_uploaded_files($request)->pluck('id')->all());
            $previews = $controller->get_preview_files($request);
            $this->assertSame([$own->id], array_column($previews, 'id'));
            $this->assertArrayNotHasKey('guest_token', $previews[0]->toArray());
        }
        $fresh = $this->request($this->browser(), ['ids' => $one->id.','.$legacy->id]);
        $this->assertSame(0, $controller->get_uploaded_files($fresh)->total());
        $this->assertSame([], $controller->get_preview_files($fresh));
    }

    /** @dataProvider protectedActions */
    public function testForeignUploadIdsCannotBeDownloadedInspectedOrDeleted($action)
    {
        $file = $this->upload($this->browser());
        $request = $this->request($this->browser(), ['id' => $file->id]);
        try {
            app(AizUploadController::class)->$action($request, $file->id);
            $this->fail('Foreign uploads must not be accessible.');
        } catch (ModelNotFoundException $error) {
            $this->assertDatabaseHas('uploads', ['id' => $file->id]);
            $this->assertFileExists(\Storage::disk('private')->path($file->file_name));
        }
    }

    public function protectedActions(): array
    {
        return [['attachment_download'], ['private_file'], ['file_info'], ['destroy']];
    }

    public function testGuestCanDownloadOwnFileWithoutCachingTheDocument()
    {
        $browser = $this->browser();
        $file = $this->upload($browser);
        $response = app(AizUploadController::class)->attachment_download($this->request($browser), $file->id);
        $this->assertSame(realpath(\Storage::disk('private')->path($file->file_name)), $response->getFile()->getRealPath());
        $this->assertTrue($response->headers->hasCacheControlDirective('no-store'));
        $this->assertTrue($response->headers->hasCacheControlDirective('private'));
        $this->assertFalse(is_file(public_path($file->file_name)));
        $this->assertStringContainsString('/aiz-uploader/private/'.$file->id, $file->url);
        $this->assertStringNotContainsString('s3.', $file->url);
    }

    public function testAssignedSellerAndTheirSalesmanCanReadIdentityDocumentsButOtherAccountsCannot()
    {
        $file = $this->upload($this->browser());
        DB::table('users')->insert(['id' => 41, 'pid' => 11, 'identity_card_front' => (string) $file->id]);
        foreach ([[41, 'seller', true], [11, 'salesman', true], [42, 'seller', false], [12, 'salesman', false]] as [$id, $role, $allowed]) {
            $user = new User; $user->id = $id; $user->user_type = $role; $this->actingAs($user);
            $request = $this->request($this->browser());
            $this->assertSame($allowed, Upload::readableBy($request)->whereKey($file->id)->exists());
        }
    }

    public function testAuthenticatedIdentityUploadsStayPrivateEvenWhenS3IsConfigured()
    {
        config(['filesystems.default' => 's3']);
        $disk = \Mockery::mock(); $disk->shouldNotReceive('put'); \Storage::set('s3', $disk);
        $user = new User; $user->id = 41; $user->user_type = 'admin'; $this->actingAs($user);
        $response = app(AizUploadController::class)->upload($this->request($this->browser(), ['purpose' => 'identity'], [
            'aiz_file' => UploadedFile::fake()->createWithContent('certificate.txt', 'private identity'),
        ]));
        $this->assertSame(200, $response->getStatusCode());
        $file = Upload::firstOrFail();
        $this->assertTrue($file->isPrivate());
        $this->assertSame('private identity', \Storage::disk('private')->get($file->file_name));
        $this->assertFalse(is_file(public_path($file->file_name)));
    }

    public function testHistoricalMigrationPreservesDataAndPublicMediaAndCanResume()
    {
        require_once dirname(base_path()).'/deploy/protect-customer-uploads.php';
        $source = 'uploads/all/historical-id.png';
        $base = ['file_original_name' => 'old', 'file_name' => $source, 'user_id' => 0,
            'extension' => 'png', 'type' => 'image', 'file_size' => 8];
        $first = Upload::create($base);
        $duplicate = Upload::create(array_merge($base, ['user_id' => 41]));
        $product = Upload::create(array_merge($base, ['user_id' => 42, 'file_name' => 'uploads/all/product.png']));
        $policy = ['Version' => '2012-10-17', 'Statement' => [['Sid' => 'PublicMedia', 'Effect' => 'Allow',
            'Principal' => '*', 'Action' => 's3:GetObject', 'Resource' => 'arn:aws:s3:::test-bucket/uploads/all/*']]];
        $originalPolicy = $policy;
        $client = \Mockery::mock();
        $client->shouldReceive('getBucketPolicy')->andReturnUsing(function () use (&$policy) { return ['Policy' => json_encode($policy)]; });
        $client->shouldReceive('putBucketPolicy')->twice()->andReturnUsing(function ($args) use (&$policy) { $policy = json_decode($args['Policy'], true); });
        $adapter = \Mockery::mock(); $adapter->shouldReceive('getClient')->andReturn($client);
        $adapter->shouldReceive('getBucket')->andReturn('test-bucket');
        $cloud = \Mockery::mock(); $cloud->shouldReceive('getAdapter')->andReturn($adapter);
        $cloud->shouldReceive('get')->once()->with($source)->andReturn('original');
        $cloud->shouldNotReceive('delete'); \Storage::set('s3', $cloud);
        $manifest = $this->root.'/migration/manifest.json';
        foreach ([1, 2] as $attempt) {
            $result = protect_customer_uploads($manifest);
            $this->assertSame(1, $result['protected_objects']);
            $this->assertSame(2, $result['protected_records']);
            $this->assertSame('original', \Storage::disk('private')->get($first->fresh()->file_name));
            $this->assertSame($first->fresh()->file_name, $duplicate->fresh()->file_name);
            $this->assertSame('uploads/all/product.png', $product->fresh()->file_name);
            $this->assertSame($originalPolicy['Statement'][0], $policy['Statement'][0]);
            $this->assertSame(['arn:aws:s3:::test-bucket/'.$source], $policy['Statement'][1]['Resource']);
            $this->assertSame('Deny', $policy['Statement'][1]['Effect']);
        }
    }

    public function testMigrationReadFailureDoesNotChangeReferencesOrBucketPermissions()
    {
        require_once dirname(base_path()).'/deploy/protect-customer-uploads.php';
        $file = Upload::create(['file_original_name' => 'old', 'file_name' => 'uploads/all/missing.png',
            'user_id' => 0, 'extension' => 'png', 'type' => 'image', 'file_size' => 8]);
        $cloud = \Mockery::mock();
        $cloud->shouldReceive('get')->once()->andThrow(new \RuntimeException('Source unavailable'));
        $cloud->shouldNotReceive('getAdapter'); \Storage::set('s3', $cloud);
        try {
            protect_customer_uploads($this->root.'/migration/manifest.json');
            $this->fail('Missing documents must stop migration before revoking access.');
        } catch (\RuntimeException $error) {
            $this->assertSame('Source unavailable', $error->getMessage());
            $this->assertSame('uploads/all/missing.png', $file->fresh()->file_name);
        }
    }

    public function testPublicMediaCleanupNeverRemovesPrivateDocuments()
    {
        $file = $this->upload($this->browser());
        $this->assertFalse(is_file(public_path($file->file_name)));
        $orphan = Upload::create(['file_original_name' => 'https://example.invalid/orphan', 'file_name' => null,
            'user_id' => 42, 'extension' => 'png', 'type' => 'image', 'file_size' => 0]);
        $this->assertSame(0, \Artisan::call('uploads:clean-broken', ['--force' => true, '--missing-local' => true]));
        $this->assertDatabaseHas('uploads', ['id' => $file->id]);
        $this->assertFileExists(\Storage::disk('private')->path($file->file_name));
        $this->assertDatabaseMissing('uploads', ['id' => $orphan->id]);
    }

    public function testFileLibraryViewsUseAuthorizedUrlsForPrivateThumbnailsAndDownloads()
    {
        Schema::create('translations', function (Blueprint $table) {
            $table->increments('id'); $table->string('lang'); $table->string('lang_key');
            $table->text('lang_value'); $table->timestamps();
        });
        $file = $this->upload($this->browser()); $file->type = 'image'; $file->save();
        foreach (['backend', 'seller'] as $layout) {
            $directory = $this->root.'/views/'.$layout.'/layouts'; mkdir($directory, 0700, true);
            file_put_contents($directory.'/app.blade.php', "@yield('content')@yield('panel_content')");
        }
        app('events')->forget('composing: seller.layouts.app');
        $finder = app('view')->getFinder(); $finder->prependLocation($this->root.'/views'); $finder->flush();
        foreach (['backend.uploaded_files', 'seller.uploads', 'salesman.uploads'] as $library) {
            foreach (['info', 'index'] as $screen) {
                $html = view($library.'.'.$screen, ['file' => $file, 'all_uploads' => Upload::whereKey($file->id)->paginate(60),
                    'search' => null, 'sort_by' => null])->render();
                $dom = new \DOMDocument; @$dom->loadHTML($html); $xpath = new \DOMXPath($dom);
                $links = $xpath->query('//img/@src | //a[@download]/@href | //*[@data-url]/@data-url');
                $this->assertGreaterThan(0, $links->length);
                foreach ($links as $link) {
                    $this->assertStringContainsString('/aiz-uploader/private/'.$file->id, $link->value);
                }
            }
        }
    }

    public function testLoggedInCustomersAndSellersAreLimitedToTheirOwnFiles()
    {
        $guest = $this->upload($this->browser());
        $own = $guest->replicate(); $own->user_id = 41; $own->guest_token = null; $own->save();
        $foreign = $own->replicate(); $foreign->user_id = 42; $foreign->save();
        foreach (['customer', 'seller', 'salesman', 'delivery_boy'] as $role) {
            $user = new User; $user->id = 41; $user->user_type = $role;
            $this->actingAs($user);
            $request = $this->request($this->browser(), ['ids' => $own->id.','.$foreign->id.','.$guest->id]);
            $controller = app(AizUploadController::class);
            $this->assertSame([$own->id], $controller->get_uploaded_files($request)->pluck('id')->all());
            $this->assertSame([$own->id], array_column($controller->get_preview_files($request), 'id'));
        }
        foreach (['admin', 'staff'] as $role) {
            $user = new User; $user->id = 41; $user->user_type = $role; $this->actingAs($user);
            $this->assertSame(3, Upload::accessibleTo($this->request($this->browser()))->count());
        }
    }
}

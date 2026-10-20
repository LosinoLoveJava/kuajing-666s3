<?php

namespace Tests\Feature;

use App\Models\Upload;
use Illuminate\Support\Facades\Storage;
use Mockery;
use Tests\TestCase;

class MediaAssetTest extends TestCase
{
    private $mediaRoot;

    protected function setUp(): void
    {
        parent::setUp();
        $this->mediaRoot = sys_get_temp_dir() . '/media-assets-' . bin2hex(random_bytes(8));
        mkdir($this->mediaRoot . '/uploads', 0700, true);
        file_put_contents($this->mediaRoot . '/uploads/legacy.png', 'legacy-image');
        $this->app->instance('path.public', $this->mediaRoot);
        $this->app['url']->forceRootUrl('https://portal.example');
        $this->app['url']->forceScheme('https');
        config(['filesystems.default' => 's3']);
    }

    protected function tearDown(): void
    {
        if ($this->mediaRoot) {
            unlink($this->mediaRoot . '/uploads/legacy.png');
            rmdir($this->mediaRoot . '/uploads');
            rmdir($this->mediaRoot);
        }
        parent::tearDown();
    }

    public function testExistingLocalMediaDoesNotUseS3(): void
    {
        Storage::shouldReceive('disk')->never();
        $this->assertSame('https://portal.example/public/uploads/legacy.png', my_asset('uploads/legacy.png'));
    }

    public function testCloudOnlyMediaUsesS3UrlWithoutRemoteExistenceCheck(): void
    {
        $disk = Mockery::mock();
        $disk->shouldReceive('url')->once()->with('uploads/cloud.png')->andReturn('https://bucket.example/uploads/cloud.png');
        Storage::shouldReceive('disk')->once()->with('s3')->andReturn($disk);
        $this->assertSame('https://bucket.example/uploads/cloud.png', my_asset('uploads/cloud.png'));
    }

    public function testLocalDriverDoesNotUseS3EvenWhenFileIsMissing(): void
    {
        config(['filesystems.default' => 'local']);
        Storage::shouldReceive('disk')->never();
        $this->assertSame('https://portal.example/public/uploads/missing.png', my_asset('uploads/missing.png'));
    }

    public function testAbsoluteMediaUrlsRemainUnchanged(): void
    {
        Storage::shouldReceive('disk')->never();
        $this->assertSame('https://cdn.example/image.png', my_asset('https://cdn.example/image.png'));
    }

    public function testUploadJsonContainsResolvedLocalUrlAndOriginalPath(): void
    {
        Storage::shouldReceive('disk')->never();
        $upload = new Upload(['file_name' => 'uploads/legacy.png']);
        $data = $upload->toArray();
        $this->assertSame('uploads/legacy.png', $data['file_name']);
        $this->assertSame('https://portal.example/public/uploads/legacy.png', $data['url']);
    }

    public function testUploadJsonPreservesExternalLink(): void
    {
        Storage::shouldReceive('disk')->never();
        $upload = new Upload(['file_name' => 'uploads/legacy.png']);
        $upload->external_link = 'https://external.example/photo.jpg';
        $this->assertSame('https://external.example/photo.jpg', $upload->toArray()['url']);
    }

    public function testStaticAssetsStayLocalWithS3Enabled(): void
    {
        Storage::shouldReceive('disk')->never();
        $this->assertSame('https://portal.example/public/assets/js/aiz-core.js', static_asset('assets/js/aiz-core.js'));
    }
}

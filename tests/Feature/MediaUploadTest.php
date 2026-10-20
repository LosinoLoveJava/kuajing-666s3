<?php

namespace Tests\Feature;

use App\Http\Controllers\AizUploadController;
use Aws\Command;
use Aws\S3\Exception\S3Exception;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Mockery;
use Tests\TestCase;

class MediaUploadTest extends TestCase
{
    private $root;

    protected function setUp(): void
    {
        parent::setUp();
        $this->root = sys_get_temp_dir().'/media-upload-'.bin2hex(random_bytes(8));
        mkdir($this->root, 0700);
        $this->app->instance('path.public', $this->root);
        config(['database.default'=>'sqlite', 'database.connections.sqlite.database'=>':memory:',
            'filesystems.default'=>'s3', 'filesystems.disks.local.root'=>$this->root]);
        DB::purge('sqlite');
        Schema::create('uploads', function (Blueprint $table) {
            $table->increments('id'); $table->string('file_original_name')->nullable();
            $table->string('file_name'); $table->integer('user_id'); $table->string('extension');
            $table->string('type'); $table->integer('file_size'); $table->timestamps();
        });
    }

    protected function tearDown(): void
    {
        if ($this->root) {
            foreach (glob($this->root.'/uploads/all/*') ?: [] as $file) { unlink($file); }
            if (is_dir($this->root.'/uploads/all')) { rmdir($this->root.'/uploads/all'); }
            if (is_dir($this->root.'/uploads')) { rmdir($this->root.'/uploads'); }
            rmdir($this->root);
        }
        parent::tearDown();
    }

    private function upload()
    {
        $request = Request::create('/aiz-uploader/upload', 'POST', [], [], [
            'aiz_file'=>UploadedFile::fake()->createWithContent('sample.txt', 'test upload'),
        ]);
        return app(AizUploadController::class)->upload($request);
    }

    public function testInvalidCredentialsReturnJsonWithoutLeakingSdkDetailsOrSavingAnUpload()
    {
        $disk = Mockery::mock();
        $disk->shouldReceive('put')->once()->andThrow(new S3Exception('sensitive credential and signed URL', new Command('PutObject'), ['code'=>'InvalidAccessKeyId']));
        Storage::set('s3', $disk);
        $response = $this->upload();
        $this->assertSame(503, $response->getStatusCode());
        $this->assertSame('application/json', $response->headers->get('Content-Type'));
        $this->assertSame('storage_upload_failed', $response->getData(true)['code']);
        $this->assertStringContainsString('S3 authentication failed', $response->getData(true)['message']);
        $this->assertStringNotContainsString('sensitive', $response->getContent());
        $this->assertDatabaseCount('uploads', 0);
        $this->assertSame([], glob($this->root.'/uploads/all/*'));
    }

    public function testFalseStorageResultDoesNotCreateABrokenMediaRecord()
    {
        $disk = Mockery::mock(); $disk->shouldReceive('put')->once()->andReturn(false);
        Storage::set('s3', $disk);
        $this->assertSame(503, $this->upload()->getStatusCode());
        $this->assertDatabaseCount('uploads', 0);
        $this->assertSame([], glob($this->root.'/uploads/all/*'));
    }

    public function testS3SuccessSavesMediaRecordAndRemovesLocalCopy()
    {
        $disk = Mockery::mock();
        $disk->shouldReceive('put')->once()->withArgs(function ($path, $contents, $options) {
            return strpos($path, 'uploads/all/') === 0 && $contents === 'test upload'
                && $options['ContentType'] === 'text/plain' && $options['visibility'] === 'public';
        })->andReturn(true);
        Storage::set('s3', $disk);
        $response = $this->upload();
        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('{}', $response->getContent());
        $this->assertDatabaseHas('uploads', ['file_original_name'=>'sample','extension'=>'txt','type'=>'document','file_size'=>11]);
        $this->assertSame([], glob($this->root.'/uploads/all/*'));
    }

    public function testLocalUploadsRemainAvailableWithoutAnS3Call()
    {
        config(['filesystems.default'=>'local']);
        $disk = Mockery::mock(); $disk->shouldNotReceive('put'); Storage::set('s3', $disk);
        $this->assertSame(200, $this->upload()->getStatusCode());
        $this->assertDatabaseCount('uploads', 1);
        $path = DB::table('uploads')->value('file_name');
        $this->assertSame('test upload', file_get_contents($this->root.'/'.$path));
    }

    public function testAclDisabledBucketRetriesWithBucketOwnerFullControl()
    {
        $disk = Mockery::mock();
        $disk->shouldReceive('put')->once()->withArgs(function ($path, $contents, $options) {
            return $options['visibility'] === 'public';
        })->andThrow(new S3Exception('ACLs disabled', new Command('PutObject'), ['code'=>'AccessControlListNotSupported']));
        $disk->shouldReceive('put')->once()->withArgs(function ($path, $contents, $options) {
            return strpos($path, 'uploads/all/') === 0 && $contents === 'test upload'
                && !isset($options['visibility']) && $options['ACL'] === 'bucket-owner-full-control'
                && $options['ContentType'] === 'text/plain';
        })->andReturn(true);
        Storage::set('s3', $disk);
        $this->assertSame(200, $this->upload()->getStatusCode());
        $this->assertDatabaseCount('uploads', 1);
        $this->assertSame([], glob($this->root.'/uploads/all/*'));
    }

    public function testFailedAclRetryDoesNotSaveMediaOrLeakDetails()
    {
        $disk = Mockery::mock();
        $disk->shouldReceive('put')->once()->withArgs(function ($path, $contents, $options) {
            return isset($options['visibility']);
        })->andThrow(new S3Exception('ACLs disabled', new Command('PutObject'), ['code'=>'AccessControlListNotSupported']));
        $disk->shouldReceive('put')->once()->withArgs(function ($path, $contents, $options) {
            return isset($options['ACL']);
        })->andThrow(new S3Exception('private SDK details', new Command('PutObject'), ['code'=>'AccessDenied']));
        Storage::set('s3', $disk);
        $response = $this->upload();
        $this->assertSame(503, $response->getStatusCode());
        $this->assertStringContainsString('upload permissions', $response->getData(true)['message']);
        $this->assertStringNotContainsString('private SDK details', $response->getContent());
        $this->assertDatabaseCount('uploads', 0);
        $this->assertSame([], glob($this->root.'/uploads/all/*'));
    }

    public function testAccessDeniedReturnsAPermissionMessage()
    {
        $disk = Mockery::mock();
        $disk->shouldReceive('put')->once()->andThrow(new S3Exception('private SDK details', new Command('PutObject'), ['code'=>'AccessDenied']));
        Storage::set('s3', $disk);
        $this->assertStringContainsString('upload permissions', $this->upload()->getData(true)['message']);
        $this->assertDatabaseCount('uploads', 0);
    }
}

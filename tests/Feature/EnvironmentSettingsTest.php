<?php

namespace Tests\Feature;

use App\Http\Controllers\BusinessSettingsController;
use App\Http\Controllers\OTPController;
use App\Support\EnvironmentFile;
use Dotenv\Dotenv;
use Tests\TestCase;

class EnvironmentSettingsTest extends TestCase
{
    private $directory;

    protected function setUp(): void
    {
        parent::setUp();
        $this->directory = sys_get_temp_dir() . '/env-settings-' . bin2hex(random_bytes(8));
        mkdir($this->directory, 0700);
        file_put_contents($this->directory . '/.env', "AWS_URL=production-original\n");
        file_put_contents($this->directory . '/.env.local', "AWS_URL=local-old\nFILESYSTEM_DRIVER=local\nDB_DATABASE=kuajing_local\n");
        $this->app->useEnvironmentPath($this->directory)->loadEnvironmentFrom('.env.local');
    }

    protected function tearDown(): void
    {
        if ($this->directory) {
            unlink($this->directory . '/.env');
            unlink($this->directory . '/.env.local');
            rmdir($this->directory);
        }
        parent::tearDown();
    }

    public function testBusinessControllerSavesActiveEnvironmentAndPreservesOtherValues(): void
    {
        (new BusinessSettingsController())->overWriteEnvFile('FILESYSTEM_DRIVER', 's3');
        $settings = Dotenv::parse(file_get_contents($this->directory . '/.env.local'));
        $this->assertSame('s3', $settings['FILESYSTEM_DRIVER']);
        $this->assertSame('kuajing_local', $settings['DB_DATABASE']);
        $this->assertSame("AWS_URL=production-original\n", file_get_contents($this->directory . '/.env'));
    }

    public function testOtpControllerSavesActiveLocalEnvironment(): void
    {
        (new OTPController())->overWriteEnvFile('CHUANX_APPKEY', 'test-only');
        $this->assertSame('test-only', Dotenv::parse(file_get_contents($this->directory . '/.env.local'))['CHUANX_APPKEY']);
        $this->assertSame("AWS_URL=production-original\n", file_get_contents($this->directory . '/.env'));
    }

    public function testEnvironmentWriterRoundTripsSpecialCharactersAndRemovesObsoleteKeys(): void
    {
        $value = 'spaces "quotes" \\ dollar ${APP_URL} hash # single quote ' . "'\nnext\rline\ttab";
        EnvironmentFile::update($this->directory . '/.env.local', ['TEST_SECRET' => $value, 'AWS_URL' => null]);
        $settings = Dotenv::parse(file_get_contents($this->directory . '/.env.local'));
        $this->assertSame($value, $settings['TEST_SECRET']);
        $this->assertArrayNotHasKey('AWS_URL', $settings);
        $this->assertSame('kuajing_local', $settings['DB_DATABASE']);
        $this->assertSame(1, substr_count(file_get_contents($this->directory . '/.env.local'), 'TEST_SECRET='));
    }

    public function testInvalidSettingCannotInjectAnEnvironmentLine(): void
    {
        $before = file_get_contents($this->directory . '/.env.local');
        try {
            EnvironmentFile::update($this->directory . '/.env.local', ["BAD\nDB_HOST" => 'remote']);
            $this->fail('Invalid key was accepted');
        } catch (\RuntimeException $error) {
            $this->assertSame($before, file_get_contents($this->directory . '/.env.local'));
        }
    }
}

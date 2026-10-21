<?php

namespace Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class SellerDocumentTypesTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:', 'cache.default' => 'array']);
        DB::purge('sqlite');
        Schema::create('translations', function (Blueprint $table) {
            $table->increments('id'); $table->string('lang'); $table->string('lang_key');
            $table->text('lang_value'); $table->timestamps();
        });
        require_once database_path('migrations/2026_10_22_120000_add_seller_document_type_translations.php');
    }

    public function testAllTwelveLocalesTranslateAllSellerDocumentTypes()
    {
        (new \AddSellerDocumentTypeTranslations)->up();
        $translations = json_decode(file_get_contents(database_path('data/seller_document_translations.json')), true);
        $this->assertCount(12, $translations);
        foreach ($translations as $locale => $messages) {
            foreach ($messages as $source => $expected) {
                $this->assertSame($expected, translate($source, $locale), $locale.': '.$source);
            }
        }
        $this->assertSame(60, DB::table('translations')->count());
    }

    public function testMigrationIsRepeatableAndPreservesCustomizedLabels()
    {
        DB::table('translations')->insert(['lang'=>'cn', 'lang_key'=>'id_card', 'lang_value'=>'证件（自定义）']);
        Cache::forever('translations-cn', ['id_card'=>'旧缓存']);
        $migration = new \AddSellerDocumentTypeTranslations;
        $migration->up(); $migration->up();
        $this->assertSame('证件（自定义）', translate('ID Card', 'cn'));
        $this->assertSame(60, DB::table('translations')->count());
        $this->assertSame(1, DB::table('translations')->where('lang', 'cn')->where('lang_key', 'id_card')->count());
    }

    public function testPublicAndManagedFormsUseOnlyTheFiveSupportedOptions()
    {
        $public = file_get_contents(resource_path('views/frontend/seller_form.blade.php'));
        $managed = file_get_contents(resource_path('views/backend/sellers/phone_form.blade.php'));
        foreach ([$public, $managed] as $source) {
            foreach (['ID Card', 'Passport', 'Driving License', 'Mobile Phone Bill', 'Water/Electric Bill'] as $label) {
                $this->assertStringContainsString("translate('{$label}')", $source);
            }
            $this->assertStringNotContainsString('Social Security Card', $source);
        }
        $this->assertSame(1, substr_count($public, 'value="idcard"'));
        $this->assertSame(1, substr_count($public, 'value="passport"'));
        $this->assertSame(1, substr_count($public, 'value="driving license"'));
        $this->assertSame(1, substr_count($public, 'value="mobile phone bill"'));
        $this->assertSame(1, substr_count($public, 'value="water/electric bill"'));
    }

    public function testBackendValidationAllowsNewTypesAndRejectsRetiredType()
    {
        $registration = file_get_contents(app_path('Services/SellerRegistration.php'));
        $managed = file_get_contents(app_path('Services/ManagedPhoneAccounts.php'));
        foreach ([$registration, $managed] as $source) {
            foreach (['idcard', 'passport', 'driving license', 'mobile phone bill', 'water/electric bill'] as $value) {
                $this->assertStringContainsString("'{$value}'", $source);
            }
            $this->assertStringNotContainsString('social security card', $source);
        }
    }
}

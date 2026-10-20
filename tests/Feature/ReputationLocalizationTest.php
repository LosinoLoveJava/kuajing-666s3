<?php

namespace Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ReputationLocalizationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['database.default'=>'sqlite', 'database.connections.sqlite.database'=>':memory:', 'cache.default'=>'array']);
        DB::purge('sqlite');
        Schema::create('translations', function (Blueprint $table) {
            $table->increments('id');
            $table->string('lang');
            $table->string('lang_key');
            $table->text('lang_value');
            $table->timestamps();
        });
        require_once database_path('migrations/2026_10_21_100000_add_reputation_translations.php');
    }

    public function testAllTwelveLocalesResolveEveryReputationLabel()
    {
        (new \AddReputationTranslations)->up();
        $translations = json_decode(file_get_contents(database_path('data/reputation_translations.json')), true);
        $this->assertCount(12, $translations);
        foreach ($translations as $locale => $messages) {
            $this->assertSame(array_keys($translations['en']), array_keys($messages));
            foreach ($messages as $source => $expected) {
                $this->assertSame($expected, translate($source, $locale), $locale.': '.$source);
            }
        }
        $this->assertSame(192, DB::table('translations')->count());
    }

    public function testMigrationPreservesCustomTranslationsIsRepeatableAndClearsCaches()
    {
        DB::table('translations')->insert(['lang'=>'cn', 'lang_key'=>'reputation_scores', 'lang_value'=>'Custom reputation label']);
        Cache::forever('translations-cn', ['reputation_scores'=>'Stale label']);
        $migration = new \AddReputationTranslations;
        $migration->up();
        $this->assertSame('Custom reputation label', translate('Reputation scores', 'cn'));
        $migration->up();
        $this->assertSame(192, DB::table('translations')->count());
        $this->assertSame(1, DB::table('translations')->where('lang','cn')->where('lang_key','reputation_scores')->count());
        $migration->down();
        $this->assertSame('Custom reputation label', translate('Reputation scores', 'cn'));
    }

    public function testReputationMenuIsOnlyInsideCustomersSubmenu()
    {
        $source = file_get_contents(resource_path('views/backend/inc/admin_sidenav.blade.php'));
        $this->assertSame(1, substr_count($source, "route('reputation.index')"));
        $document = new \DOMDocument;
        $previous = libxml_use_internal_errors(true);
        $document->loadHTML('<?xml encoding="UTF-8">'.$source);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);
        $xpath = new \DOMXPath($document);
        $links = $xpath->query('//a[contains(@href,"reputation.index")]');
        $this->assertSame(1, $links->length);
        $parent = $xpath->query('ancestor::ul[contains(@class,"level-2")]/parent::li/a', $links->item(0));
        $this->assertSame(1, $parent->length);
        $this->assertStringContainsString("translate('Customers')", $parent->item(0)->textContent);
    }
}

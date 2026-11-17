<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\ProductTranslation;
use App\Support\TurkishCopy;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class TurkishFrontendTest extends TestCase
{
    use \Tests\Concerns\WalletDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->createWalletDatabase();
        app()->setLocale('tr');
    }

    public function testStaticFallbackEmptyRowsAndAdministratorOverrides()
    {
        $this->assertSame('Banka', translate('Bank'));
        DB::table('translations')->insert(['lang'=>'tr','lang_key'=>'bank','lang_value'=>'']);
        Cache::forget('translations-tr');
        $this->assertSame('Banka', translate('Bank'));
        DB::table('translations')->where('lang','tr')->update(['lang_value'=>'Özel banka']);
        Cache::forget('translations-tr');
        $this->assertSame('Özel banka', translate('Bank'));
        $this->assertSame('Bank', translate('Bank','en'));
    }

    public function testOnlyEnabledLanguageQueryChangesTheSession()
    {
        Schema::create('languages',function(Blueprint $t){$t->increments('id');$t->string('code');$t->integer('status');});
        DB::table('languages')->insert([['code'=>'tr','status'=>1],['code'=>'en','status'=>0]]);
        $session=app('session.store');
        $session->start();
        foreach (['tr','en','does-not-exist',['tr']] as $locale) {
            $request=\Illuminate\Http\Request::create('/','GET',['lang'=>$locale]);
            $request->setLaravelSession($session);
            (new \App\Http\Middleware\Language)->handle($request,function(){return response('ok');});
            $this->assertSame('tr',app()->getLocale());
            $this->assertSame('tr',$session->get('locale'));
        }
    }

    public function testHtmlTranslationPreservesMarkupMediaScriptsAndEscapesText()
    {
        $html='Hello <a href="/Hello?q=Hello">World &amp; more</a><img src="Hello.jpg"><script>"Hello"</script><style>.Hello{}</style><!--Hello-->';
        $expected='Merhaba <a href="/Hello?q=Hello">Dünya &amp; fazlası</a><img src="Hello.jpg"><script>"Hello"</script><style>.Hello{}</style><!--Hello-->';
        $this->assertSame($expected, TurkishCopy::html($html,'tr',['Hello'=>'Merhaba','World & more'=>'Dünya & fazlası']));
        $this->assertSame($html, TurkishCopy::html($html,'en',['Hello'=>'Merhaba']));
        $this->assertSame('<b>&lt;script&gt;</b>', TurkishCopy::html('<b>Hello</b>','tr',['Hello'=>'<script>']));
    }

    public function testEmptyProductTranslationFallsBackWithoutChangingStoredChoices()
    {
        $product = new Product(['name'=>'Bank','unit'=>'piece','description'=>'<b>Original</b>','choice_options'=>'[{"values":["Red"]}]']);
        $translation = new ProductTranslation(['lang'=>'tr','name'=>'','unit'=>null,'description'=>'']);
        $product->setRelation('product_translations', collect([$translation]));
        $this->assertSame('Banka', $product->getTranslation('name'));
        $this->assertSame('<b>Original</b>', $product->getTranslation('description'));
        $this->assertSame('[{"values":["Red"]}]', $product->choice_options);
        $this->assertSame('Bank', $product->getTranslation('name','en'));
        $this->assertSame('Banka', frontend_product_meta($product,'meta_title'));
        $this->assertSame('Original', frontend_product_meta($product,'meta_description'));
        $this->assertSame('Durum: Teslim edildi', frontend_order_status('delivered'));
    }

    public function testMenuLabelsAndSettingsAreLocalizedWithoutChangingUrls()
    {
        $this->assertSame(['Banka'], json_decode(TurkishCopy::setting('header_menu_labels','["Bank"]'),true));
        $this->assertSame('["/Bank"]', TurkishCopy::setting('header_menu_links','["/Bank"]'));
        DB::table('business_settings')->insert([
            ['type'=>'widget_one','lang'=>'tr','value'=>''],
            ['type'=>'widget_one','lang'=>'en','value'=>'Bank'],
        ]);
        $this->assertSame('Banka', get_setting('widget_one'));
        $this->assertSame('17 Kasım 2026, 09:30', frontend_datetime('2026-11-17 09:30:00'));
    }

    private function leaves(array $array, $prefix = '')
    {
        $result=[];
        foreach ($array as $key=>$value) {
            if ($key === 'custom') continue;
            $path=$prefix.$key;
            if (is_array($value)) $result += $this->leaves($value,$path.'.');
            else $result[$path]=$value;
        }
        return $result;
    }

    public function testAllLaravelAndPluginKeysPreserveInterpolationTokens()
    {
        $pairs=[];
        foreach (glob(resource_path('lang/en/*.php')) as $path) {
            $name=basename($path);
            $en=$this->leaves(require $path);
            $tr=$this->leaves(require resource_path('lang/tr/'.$name));
            $this->assertSame([],array_values(array_diff(array_keys($en),array_keys($tr))),$name);
            foreach ($en as $key=>$value) $pairs[$name.'.'.$key]=[$value,$tr[$key]];
        }
        $en=$this->leaves(json_decode(file_get_contents(database_path('data/frontend_plugin_sources.json')),true));
        $tr=$this->leaves(json_decode(file_get_contents(database_path('data/turkish_frontend_plugins.json')),true));
        $this->assertSame(array_keys($en),array_keys($tr));
        foreach ($en as $key=>$value) $pairs['plugin.'.$key]=[$value,$tr[$key]];
        foreach ($pairs as $key=>$pair) {
            $this->assertNotSame('',trim($pair[1]),$key);
            preg_match_all('/%\{[^}]+\}|(?<![a-z]):[a-z_]+|%[sd]/',$pair[0],$a);
            preg_match_all('/%\{[^}]+\}|(?<![a-z]):[a-z_]+|%[sd]/',$pair[1],$b);
            sort($a[0]); sort($b[0]);
            $this->assertSame($a[0],$b[0],$key);
        }
    }

    public function testMigrationIsIdempotentAndPreservesCustomTranslations()
    {
        Schema::table('business_settings',function(Blueprint $t){ $t->timestamps(); });
        Schema::create('products',function(Blueprint $t){$t->increments('id');$t->string('name');$t->string('unit');$t->text('description')->nullable();});
        Schema::create('product_translations',function(Blueprint $t){$t->increments('id');$t->integer('product_id');$t->string('lang');$t->string('name')->nullable();$t->string('unit')->nullable();$t->text('description')->nullable();$t->timestamps();});
        foreach (['category','attribute','page'] as $type) {
            Schema::create($type==='category'?'categories':$type.'s',function(Blueprint $t) use($type){$t->increments('id');$t->string($type==='page'?'title':'name');if($type==='page')$t->text('content')->nullable();});
            Schema::create($type.'_translations',function(Blueprint $t) use($type){$t->increments('id');$t->integer($type.'_id');$t->string('lang');$t->string($type==='page'?'title':'name')->nullable();if($type==='page')$t->text('content')->nullable();$t->timestamps();});
        }
        DB::table('products')->insert(['id'=>1,'name'=>'Bank','unit'=>'piece','description'=>'<p>Hello</p>']);
        DB::table('product_translations')->insert(['product_id'=>1,'lang'=>'tr','name'=>'Özel ürün','description'=>'<p>Özel açıklama</p>']);
        DB::table('categories')->insert(['name'=>'Bank']);
        DB::table('attributes')->insert(['name'=>'Bank']);
        DB::table('pages')->insert(['title'=>'Bank','content'=>'<p>Bank</p>']);
        DB::table('pages')->insert(['title'=>'Blank page','content'=>null]);
        DB::table('business_settings')->insert(['type'=>'header_menu_labels','lang'=>'en','value'=>'["Bank"]']);
        require_once database_path('migrations/2026_11_17_150000_complete_turkish_frontend.php');
        (new \CompleteTurkishFrontend)->up();
        $count=DB::table('translations')->count();
        (new \CompleteTurkishFrontend)->up();
        $this->assertSame($count,DB::table('translations')->count());
        $this->assertSame('Özel ürün',DB::table('product_translations')->value('name'));
        $this->assertSame('<p>Özel açıklama</p>',DB::table('product_translations')->value('description'));
        $this->assertSame('Banka',DB::table('category_translations')->value('name'));
        $this->assertSame('<p>Banka</p>',DB::table('page_translations')->value('content'));
        $this->assertSame('',DB::table('page_translations')->where('page_id',2)->value('content'));
        $this->assertSame(['Banka'],json_decode(DB::table('business_settings')->where('lang','tr')->value('value'),true));
    }
}

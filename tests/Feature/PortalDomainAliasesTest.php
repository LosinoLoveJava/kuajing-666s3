<?php

namespace Tests\Feature;

use App\Http\Middleware\PortalEntry;
use App\Services\PortalContext;
use Illuminate\Http\Request;
use Illuminate\Session\ArraySessionHandler;
use Illuminate\Session\Store;
use Tests\TestCase;

class PortalDomainAliasesTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['portals.domain' => 'pddglobalmerchanthub.com',
            'portals.aliases' => ['pddsellercenter.ae', 'pddsellercenter.com']]);
    }

    public function testAllDomainFamiliesResolveAllPortalsAndRejectLookalikeHosts()
    {
        foreach (['pddglobalmerchanthub.com', 'pddsellercenter.ae', 'pddsellercenter.com'] as $domain) {
            foreach (['seller' => 'seller', 'admin' => 'admin', 'sales' => 'sales', 'assig' => 'delivery'] as $prefix => $portal) {
                foreach (['/', '/users/phone', '/login'] as $path) {
                    $this->assertSame($portal, PortalContext::resolve(Request::create('https://'.$prefix.'.'.$domain.$path)));
                }
            }
            foreach ([$domain, 'www.'.$domain] as $host) {
                $this->assertSame('customer', PortalContext::resolve(Request::create('https://'.$host.'/')));
            }
        }
        foreach (['seller.pddsellercenter.ae.attacker.test', 'fakeseller.pddsellercenter.ae',
            'seller.pddsellercenter.com.attacker.test', 'fakeseller.pddsellercenter.com', 'seller.other.test'] as $host) {
            $this->assertNull(PortalContext::host(Request::create('https://'.$host.'/')));
        }
    }

    public function testAliasesUseTheMatchingLoginAndKeepTheIntendedUrlOnTheSameHost()
    {
        foreach (['pddglobalmerchanthub.com', 'pddsellercenter.ae', 'pddsellercenter.com'] as $domain) {
            foreach (['seller' => '/seller/dashboard', 'admin' => '/admin', 'sales' => '/dashboard'] as $portal => $path) {
                $request = Request::create('https://'.$portal.'.'.$domain.'/');
                $request->setLaravelSession(new Store('domain_test', new ArraySessionHandler(120)));
                $request->setUserResolver(function () { return null; });
                $this->app->instance('request', $request);
                app('url')->setRequest($request);
                $response = (new PortalEntry)->handle($request, function () { $this->fail('Portal entry was not recognized.'); });
                $this->assertSame('https://'.$portal.'.'.$domain.'/login?portal='.$portal, $response->getTargetUrl());
                $this->assertSame('https://'.$portal.'.'.$domain.$path, $request->session()->get('link'));
            }
        }
    }

    public function testBuyerHostsPassThroughAndDeliveryKeepsItsUnavailableResponse()
    {
        foreach (['pddsellercenter.ae', 'www.pddsellercenter.ae', 'pddsellercenter.com', 'www.pddsellercenter.com'] as $host) {
            $response = (new PortalEntry)->handle(Request::create('https://'.$host.'/'), function () { return response('buyer'); });
            $this->assertSame('buyer', $response->getContent());
        }
        foreach (['pddsellercenter.ae', 'pddsellercenter.com'] as $domain) {
            $response = (new PortalEntry)->handle(Request::create('https://assig.'.$domain.'/'), function () { $this->fail('Delivery entry was not recognized.'); });
            $this->assertSame(503, $response->getStatusCode());
            $this->assertStringContainsString('配送员模块暂未启用', $response->getContent());
        }
    }
}

<?php

namespace Tests\Feature;

use App\Http\Middleware\PortalEntry;
use Illuminate\Http\Request;
use Tests\TestCase;

class SellerHomeEntryTest extends TestCase
{
    /** @dataProvider entryRequests */
    public function test_seller_home_redirect_is_limited_to_the_intended_entries($environment, $url, $method, $redirects)
    {
        $this->app->instance('env', $environment);
        $request = Request::create($url, $method);
        $response = (new PortalEntry)->handle($request, function () {
            return response('existing page');
        });

        if ($redirects) {
            $this->assertSame(302, $response->getStatusCode());
            $target = $response->headers->get('Location');
            $this->assertSame('/login', parse_url($target, PHP_URL_PATH));
            $this->assertSame('portal=seller', parse_url($target, PHP_URL_QUERY));
        } else {
            $this->assertSame(200, $response->getStatusCode());
            $this->assertSame('existing page', $response->getContent());
        }
    }

    public function entryRequests()
    {
        return [
            'production homepage' => ['production', 'https://www.pddsellercenter.com/', 'GET', true],
            'homepage with query' => ['production', 'https://www.pddsellercenter.com/?utm_source=test', 'GET', true],
            'login must not loop' => ['production', 'https://www.pddsellercenter.com/login?portal=seller', 'GET', false],
            'other pages' => ['production', 'https://www.pddsellercenter.com/product/example', 'GET', false],
            'non-GET' => ['production', 'https://www.pddsellercenter.com/', 'POST', false],
            'other domain' => ['production', 'https://www.example.test/', 'GET', false],
            'local hostname' => ['local', 'http://localhost:8088/', 'GET', true],
            'local IPv4' => ['local', 'http://127.0.0.1:8088/', 'GET', true],
            'local IPv6' => ['local', 'http://[::1]:8088/', 'GET', true],
            'production loopback' => ['production', 'http://localhost/', 'GET', false],
        ];
    }
}

<?php

namespace Tests\Concerns;

use App\Services\PortalContext;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

trait InteractsWithPortalSessions
{
    private function assertPortalAuthenticatedAs($user, $loginResponse)
    {
        $portal = PortalContext::forUser($user);
        $name = config('session.portal_cookie', config('session.cookie')).'_'.$portal;
        $cookies = [];
        foreach ($loginResponse->headers->getCookies() as $cookie) {
            $cookies[$cookie->getName()] = $cookie->getValue();
        }
        $this->assertArrayHasKey($name, $cookies);
        Route::middleware('web')->get('/__test/portal-auth', function () {
            return response()->json(['id' => Auth::id(), 'portal' => PortalContext::current()]);
        });
        Auth::forgetGuards();
        $request = app('request');
        $this->call('GET', $request->getSchemeAndHttpHost().'/__test/portal-auth?portal='.$portal, [], $cookies)
            ->assertOk()->assertJsonPath('id', $user->id)->assertJsonPath('portal', $portal);
        $this->assertAuthenticatedAs($user);
    }
}

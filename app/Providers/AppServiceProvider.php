<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Schema;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\View;

class AppServiceProvider extends ServiceProvider
{
  /**
   * Bootstrap any application services.
   *
   * @return void
   */
  public function boot()
  {
      Auth::extend('portal-session', function ($app, $name, $config) {
          $guard = new \App\Auth\PortalSessionGuard($name, Auth::createUserProvider($config['provider']), $app['session.store']);
          $guard->setCookieJar($app['cookie']);
          $guard->setDispatcher($app['events']);
          $guard->setRequest($app->refresh('request', $guard, 'setRequest'));
          return $guard;
      });
      Schema::defaultStringLength(191);
      Paginator::useBootstrap();

      View::composer('seller.layouts.app', function ($view) {
          // Shared by both navigation partials; LIMIT belongs in SQL, not after get().
          $view->with('sellerUnreadNotifications', Auth::user()->unreadNotifications()->limit(20)->get());
      });
  }

  /**
   * Register any application services.
   *
   * @return void
   */
  public function register()
  {
    //
  }
}

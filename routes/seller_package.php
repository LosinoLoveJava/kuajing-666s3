<?php

/*
|--------------------------------------------------------------------------
| Affiliate Routes
|--------------------------------------------------------------------------
|
| Here is where you can register admin routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/

use App\Http\Controllers\SellerPackageController;
use App\Http\Controllers\SellerSpreadPackageController;

//Admin
Route::group(['prefix' =>'admin', 'middleware' => ['auth', 'admin']], function(){
    Route::resource('seller_packages', SellerPackageController::class);
    Route::resource('seller_spread_packages', SellerSpreadPackageController::class);
    Route::controller(SellerPackageController::class)->group(function () {
        Route::get('/seller_packages/edit/{id}', 'edit')->name('seller_packages.edit');
        Route::get('/seller_packages/destroy/{id}', 'destroy')->name('seller_packages.destroy');
        
        
           Route::get('/seller_packages/set_default/{id}', 'set_default')->name('seller_packages.set_default');
    });
    Route::controller(SellerSpreadPackageController::class)->group(function () {
        Route::get('/seller_spread_packages/edit/{id}', 'edit')->name('seller_spread_packages.edit');
        Route::get('/seller_spread_packages/destroy/{id}', 'destroy')->name('seller_spread_packages.destroy');
    });
});

//FrontEnd
Route::group(['middleware' => ['seller']], function(){
    Route::controller(SellerPackageController::class)->group(function () {
        Route::get('/seller/seller-packages', 'seller_packages_list')->name('seller.seller_packages_list');
        Route::get('/seller/packages-payment-list', 'packages_payment_list')->name('seller.packages_payment_list');
        Route::post('/seller_packages/purchase', 'purchase_package')->name('seller_packages.purchase');
        
       Route::post('/seller_packages/buy_package_by_cash', 'buy_package_by_cash')->name('orders.buy_package_cash');
               
                
        
    });
});
//FrontEnd
Route::group(['middleware' => ['seller']], function(){
    Route::controller(SellerSpreadPackageController::class)->group(function () {
        Route::get('/seller/seller-spread-packages', 'seller_spread_packages_list')->name('seller.seller_spread_packages_list');
        Route::get('/seller/spread-packages-payment-list', 'spread_packages_payment_list')->name('seller.spread_packages_payment_list');
        Route::post('/seller_spread_packages/purchase', 'purchase_spread_package')->name('seller_spread_packages.purchase');
        
        Route::post('/seller_packages/buy_spread_cash', 'buy_spread_cash')->name('orders.buy_spread_cash');   
        
        
    });
});

Route::get('/seller_packages/check_for_invalid', [SellerPackageController::class, 'unpublish_products'])->name('seller_packages.unpublish_products');
Route::get('/seller_spread_packages/check_for_invalid', [SellerSpreadPackageController::class, 'unpublish_products'])->name('seller_spread_packages.unpublish_products');

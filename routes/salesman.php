<?php

use App\Http\Controllers\ConversationController;
use App\Http\Controllers\CustomerController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\SellerWithdrawRequestController;

//Upload
Route::group(['prefix' => 'salesman', 'middleware' => ['auth', 'salesman']], function () {
    // salesman
    Route::controller(CustomerController::class)->group(function () {
        Route::get('customers/index', 'salesman_index')->name('salesman.customers.index');
        Route::get('customers/create', 'salesman_create')->name('salesman.customers.create');
        Route::post('customers/store', 'salesman_store')->name('salesman.customers.store');
        Route::get('customers_ban/{customer}', 'ban')->name('salesman.customers.ban');
        Route::get('/customers/login/{id}', 'login')->name('salesman.customers.login');
        Route::get('/customers/destroy/{id}', 'destroy')->name('salesman.customers.destroy');
        Route::post('/bulk-customer-delete', 'bulk_customer_delete')->name('salesman.bulk-customer-delete');
    });

    //conversation of seller customer
    Route::controller(ConversationController::class)->group(function () {
        Route::post('conversations/store', 'salesman_store')->name('conversations.salesman_store');
    });

    Route::controller(SellerWithdrawRequestController::class)->group(function () {
        Route::get('/withdraw_requests_all', 'salesman_index')->name('salesman.withdraw_requests_all');
        Route::post('/withdraw_request/message_modal', 'message_modal')->name('salesman.withdraw_request.message_modal');
    });

    // Seller Payment
    Route::controller(PaymentController::class)->group(function () {
        Route::get('/seller/payments', 'salesman_payment_histories')->name('salesman.sellers.payment_histories');
        Route::get('/seller/payments/show/{id}', 'show')->name('salesman.sellers.payment_history');
    });
});


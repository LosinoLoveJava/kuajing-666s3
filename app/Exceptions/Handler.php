<?php

namespace App\Exceptions;

use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Throwable;

class Handler extends ExceptionHandler
{
    /**
     * A list of the exception types that are not reported.
     *
     * @var array
     */
    protected $dontReport = [
        //
    ];

    /**
     * A list of the inputs that are never flashed for validation exceptions.
     *
     * @var array
     */
    protected $dontFlash = [
        'current_password',
        'password',
        'password_confirmation',
    ];

    /**
     * Register the exception handling callbacks for the application.
     *
     * @return void
     */
    public function register()
    {
        $this->reportable(function (Throwable $e) {
            //
        });
    }

    public function render($request, Throwable $e)
    {
        if ($e instanceof \Illuminate\Session\TokenMismatchException
            && $request->isMethod('POST') && $request->is('users/phone')
            && $request->input('mode') === 'login' && !$request->expectsJson()) {
            $path = parse_url((string) $request->headers->get('referer'), PHP_URL_PATH);
            $entry = in_array($path, ['/login', '/users/login', '/users/phone'], true)
                ? $path : '/users/login';
            return redirect($entry)
                ->withInput($request->only('phone', 'national_phone', 'country'))
                ->withErrors(['session' => 'Your login page has expired. Please enter your password or verification code again.']);
        }

        return parent::render($request, $e);
    }

    // public function render($request, Throwable $e)
    // {
    //     if($this->isHttpException($e))
    //     {
    //         if ($request->is('customer-products/admin')) {
    //             return redirect()->guest('new-page');
    //         }
    //         switch ($e->getStatusCode()) {
    //             // not found
    //             case 404:
    //                 return parent::render($request, $e);
    //             break;
    //         }
    //     }
    //     else
    //     {
    //             return parent::render($request, $e);
    //     }
    // }
}

<?php

use App\Http\Middleware\RateLimitMiddleware;
use Helper\Response\Response;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Laravel\Passport\Exceptions\AuthenticationException;
use Translation\Message;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__ . '/../routes/web.php',
        api: __DIR__ . '/../routes/api.php',
        commands: __DIR__ . '/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'rate_limit' => RateLimitMiddleware::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->render(function (ValidationException $exception, Request $request) {
            if ($request->is('api/*') || $request->expectsJson()) {
                return Response::_422(null, $exception->errors());
            }
        });

        $exceptions->render(function (AuthenticationException $exception, Request $request) {
            if ($request->is('api/*') || $request->expectsJson()) {
                return Response::_401([
                    'message' => Message::get('unautheticated_login_please_try_again'),
                ]);
            }

            return redirect()->guest(route('login'));
        });

        $exceptions->render(function (Request $request) {
            if ($request->is('api/*') || $request->expectsJson()) {
                return Response::_401([
                    'message' => Message::get('unautheticated'),
                ]);
            }
        });
    })->create();

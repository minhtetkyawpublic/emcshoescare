<?php

use App\Exceptions\ApiException;
use App\Http\Middleware\ApiHeaders;
use App\Http\Middleware\TrustedBrowserRequest;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Session\TokenMismatchException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->alias([
            'api.headers' => ApiHeaders::class,
            'trusted.browser' => TrustedBrowserRequest::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        $exceptions->shouldRenderJsonWhen(fn () => true);
        $exceptions->render(function (ApiException $exception) {
            $error = ['code' => $exception->errorCode, 'message' => $exception->getMessage()];
            if ($exception->fields !== []) {
                $error['fields'] = $exception->fields;
            }

            return response()->json(['success' => false, 'error' => $error], $exception->status);
        });
        $exceptions->render(fn (TokenMismatchException $exception) => response()->json([
            'success' => false,
            'error' => ['code' => 'csrf_failed', 'message' => 'Your secure session changed. Refresh and try again.'],
        ], 419));
        $exceptions->render(fn (NotFoundHttpException $exception) => response()->json([
            'success' => false,
            'error' => ['code' => 'not_found', 'message' => 'API route not found.'],
        ], 404));
    })->create();

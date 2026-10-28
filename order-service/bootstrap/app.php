<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        api: __DIR__ . '/../routes/api.php',
        commands: __DIR__ . '/../routes/console.php',
        apiPrefix: '',
    )
    ->withMiddleware(function (Middleware $middleware) {
        //
    })
    ->withExceptions(function (Exceptions $exceptions) {
        // Semua response error selalu JSON, tidak peduli header Accept.
        $exceptions->shouldRenderJsonWhen(fn(Request $request, Throwable $e) => true);

        $exceptions->render(function (NotFoundHttpException $e, Request $request) {
            return response()->json(['message' => 'Endpoint atau sumber daya tidak ditemukan'], 404);
        });

        $exceptions->render(function (MethodNotAllowedHttpException $e, Request $request) {
            return response()->json(['message' => 'Method tidak diizinkan untuk endpoint ini'], 405);
        });
    })->create();

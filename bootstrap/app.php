<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
    $middleware->alias([
        'log.visit' => \App\Http\Middleware\LogVisit::class,
        'backups.owner' => \App\Http\Middleware\EnsureBackupOwner::class,
    ]);
    // Global, not web-only: a row queued for a request no route matched is still written.
    $middleware->prepend(\App\Http\Middleware\FlushDeferredWrites::class);
    $middleware->web(append: [\App\Http\Middleware\RejectPreviewWrites::class]);
    // Written by calendar.js so the server can render the chosen layers; see CalendarController.
    $middleware->encryptCookies(except: [\App\Http\Controllers\CalendarController::LAYER_COOKIE]);
    $middleware->appendToPriorityList(\Illuminate\Auth\Middleware\Authorize::class, \App\Http\Middleware\RejectPreviewWrites::class);
    // Hides the Backups page with a 404 before a backup id is looked up or Guest gets the 403.
    $middleware->prependToPriorityList(\Illuminate\Routing\Middleware\SubstituteBindings::class, \App\Http\Middleware\EnsureBackupOwner::class);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );

        $exceptions->dontFlash(['key', 'algorithm', 'generate_key']);
    })->create();


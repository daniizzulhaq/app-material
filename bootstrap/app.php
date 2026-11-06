<?php

use App\Exceptions\BisnisException;
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
        // Percayai proxy (ngrok, Cloudflare tunnel, dll) agar header X-Forwarded-Proto/Host
        // dipakai: URL CSS/JS ikut https://domain-ngrok, bukan http://127.0.0.1
        $middleware->trustProxies(at: '*');
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Error aturan bisnis: kembali ke halaman sebelumnya + toast error
        $exceptions->dontReport(BisnisException::class);
        $exceptions->render(function (BisnisException $e, Request $request) {
            if ($request->expectsJson()) {
                return response()->json(['message' => $e->getMessage()], 422);
            }

            return back()->withInput()->with('error', $e->getMessage());
        });
    })->create();

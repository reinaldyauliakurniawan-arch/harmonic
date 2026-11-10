<?php

use App\Http\Middleware\EnsureMember;
use App\Http\Middleware\LazyScheduler;
use App\Http\Middleware\SecurityHeaders;
use App\Http\Middleware\SuperAdmin;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\AuthenticateSession;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->web(append: [SecurityHeaders::class, AuthenticateSession::class]);
        // Sidebar layout preferences are set by the browser and validated against a whitelist where read.
        $middleware->encryptCookies(except: ['nav_rail', 'nav_open']);
        $middleware->alias(['member' => EnsureMember::class, 'scheduler' => LazyScheduler::class, 'super' => SuperAdmin::class]);
        // Tenant must be set before child models bind, so their global scope turns other tenants' ids into 404.
        $middleware->prependToPriorityList(SubstituteBindings::class, EnsureMember::class);
        $middleware->redirectGuestsTo('/login');
        $middleware->redirectUsersTo('/home');
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->render(function (AuthorizationException|AccessDeniedHttpException $e, $request) {
            $msg = "You don't have access to this.";
            if ($request->expectsJson()) {
                return response()->json(['message' => $msg], 403);
            }

            return response()->view('errors.403', ['message' => $msg], 403);
        });
    })->create();

<?php

use Filament\Facades\Filament;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        //
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // A portal link with another association's id (old bookmark, intended URL from a previous login) sends the
        // user to their own association's home instead of a bare 404. Filament still refuses the other tenant.
        $exceptions->render(function (NotFoundHttpException $e, Request $request) {
            $user = $request->user();
            $tenant = $request->route()?->parameter('tenant');
            if ($request->isMethod('GET') && ! $request->expectsJson() && $request->is('portal/*') && $tenant !== null
                && $user?->association_id && (string) $tenant !== (string) $user->association_id) {
                // A plain RedirectResponse: Livewire may have swapped the redirect() helper for its own Redirector.
                return new RedirectResponse(Filament::getPanel('portal')->getUrl($user->association));
            }

            return null;
        });
    })->create();

<?php

use Filament\Facades\Filament;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpKernel\Exception\HttpException;
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

        // Filament answers 403 when a signed-in user may not use this panel: send them where they belong instead.
        $exceptions->render(function (HttpException $e, Request $request) {
            $user = $request->user();
            if ($e->getStatusCode() !== 403 || ! $user || ! $request->isMethod('GET') || $request->expectsJson()
                || ! ($request->is('admin', 'admin/*') || $request->is('portal', 'portal/*'))) {
                return null;
            }
            $assocUser = $user->role->isAssociationRole();
            if (! $user->is_active || ($assocUser && ! $user->association?->is_active)) {
                $panel = $assocUser ? 'portal' : 'admin';
                Auth::guard('web')->logout();
                $request->session()->invalidate();
                $request->session()->regenerateToken();

                return response()->view('errors.deactivated', [
                    'reason' => $user->is_active ? 'Your association has been deactivated, so its users cannot sign in.' : 'An administrator has deactivated your account.',
                    'contact' => $assocUser && $user->is_active ? 'the Sports Entry team' : ($assocUser ? 'your association admin or the Sports Entry team' : 'a Super Admin'),
                    'login' => Filament::getPanel($panel)->getLoginUrl(),
                ], 403);
            }
            if ($assocUser && $request->is('admin', 'admin/*') && $user->association) {
                return new RedirectResponse(Filament::getPanel('portal')->getUrl($user->association));
            }
            if (! $assocUser && $request->is('portal', 'portal/*')) {
                return new RedirectResponse(Filament::getPanel('admin')->getUrl());
            }

            return null;
        });
    })->create();

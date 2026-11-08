<?php

namespace App\Http\Controllers;

use App\Services\InvitationService;
use Filament\Facades\Filament;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rules\Password;

class InvitationController extends Controller
{
    public function __construct(private readonly InvitationService $invitations) {}

    public function show(string $token): Response
    {
        $invitation = $this->invitations->findPending($token);

        return $invitation
            ? response()->view('invitations.accept', ['invitation' => $invitation, 'token' => $token])
            : response()->view('invitations.invalid', status: 410);
    }

    public function store(Request $request, string $token): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'password' => ['required', 'confirmed', Password::min(12)],
        ]);

        $user = $this->invitations->accept($token, $data['name'], $data['password']);
        Auth::login($user);
        $request->session()->regenerate();

        return redirect(Filament::getPanel('portal')->getUrl($user->association));
    }
}

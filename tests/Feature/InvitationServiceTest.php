<?php

use App\Enums\Role;
use App\Mail\InvitationMail;
use App\Models\Association;
use App\Models\Invitation;
use App\Models\User;
use App\Services\InvitationService;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;

beforeEach(function () {
    Mail::fake();
    $this->service = app(InvitationService::class);
    $this->assoc = Association::factory()->create();
});

function tokenFrom(string $url): string
{
    return basename(parse_url($url, PHP_URL_PATH));
}

it('stores only a hash of the token and emails the link', function () {
    $url = $this->service->invite($this->assoc, 'Head@Assoc.bh', Role::AssocAdmin, null);
    $token = tokenFrom($url);
    $inv = Invitation::sole();

    expect($inv->email)->toBe('head@assoc.bh')
        ->and($inv->token_hash)->toBe(hash('sha256', $token))
        ->and($inv->token_hash)->not->toBe($token)
        ->and($inv->expires_at->isSameDay(now()->addDays(7)))->toBeTrue();
    Mail::assertQueued(InvitationMail::class, fn ($m) => $m->hasTo('head@assoc.bh') && $m->url === $url);
});

it('creates the user on accept and is single use', function () {
    $token = tokenFrom($this->service->invite($this->assoc, 'ed@assoc.bh', Role::AssocEditor, null));
    $user = $this->service->accept($token, 'Editor One', 'a-long-password-1');

    expect($user->role)->toBe(Role::AssocEditor)->and($user->association_id)->toBe($this->assoc->id)
        ->and(Invitation::sole()->accepted_at)->not->toBeNull();
    expect(fn () => $this->service->accept($token, 'Again', 'a-long-password-1'))->toThrow(ValidationException::class);
});

it('rejects expired and revoked invitations', function () {
    $token = tokenFrom($this->service->invite($this->assoc, 'x@assoc.bh', Role::AssocEditor, null));
    $this->travel(8)->days();
    expect(fn () => $this->service->accept($token, 'X', 'a-long-password-1'))->toThrow(ValidationException::class);
});

it('caps editors at two, counting active editors and pending invites', function () {
    User::factory()->assocEditor($this->assoc)->create();
    $this->service->invite($this->assoc, 'two@assoc.bh', Role::AssocEditor, null);
    expect(fn () => $this->service->invite($this->assoc, 'three@assoc.bh', Role::AssocEditor, null))
        ->toThrow(ValidationException::class);
});

it('allows only one active association admin', function () {
    User::factory()->assocAdmin($this->assoc)->create();
    expect(fn () => $this->service->invite($this->assoc, 'admin2@assoc.bh', Role::AssocAdmin, null))
        ->toThrow(ValidationException::class);
});

it('refuses emails that already have an account', function () {
    User::factory()->create(['email' => 'taken@assoc.bh']);
    expect(fn () => $this->service->invite($this->assoc, 'taken@assoc.bh', Role::AssocEditor, null))
        ->toThrow(ValidationException::class);
});

it('re-inviting the same email revokes the older invitation', function () {
    $first = tokenFrom($this->service->invite($this->assoc, 'ed@assoc.bh', Role::AssocEditor, null));
    $this->service->invite($this->assoc, 'ed@assoc.bh', Role::AssocEditor, null);
    expect($this->service->findPending($first))->toBeNull()
        ->and(Invitation::pending()->count())->toBe(1);
});

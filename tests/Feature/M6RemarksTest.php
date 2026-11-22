<?php

use App\Enums\PhaseStatus;
use App\Enums\StaPermission;
use App\Filament\Pages\Dashboard;
use App\Filament\Portal\Pages\Home;
use App\Filament\Portal\Pages\Phase1Entry;
use App\Filament\Resources\Games\Pages\GameDashboard;
use App\Filament\Resources\Games\Pages\StaPhase1Edit;
use App\Filament\Resources\Games\Pages\SubmissionView;
use App\Models\Association;
use App\Models\Comment;
use App\Models\Game;
use App\Models\Submission;
use App\Models\User;
use App\Notifications\NewComment;
use Illuminate\Support\Facades\Notification;

use function Pest\Livewire\livewire;

beforeEach(function () {
    $this->assoc = Association::factory()->create(['code' => 'BJF', 'name' => 'Bahrain Judo Federation']);
    $this->game = Game::factory()->create(['name' => 'Asian Indoor Games']);
    $this->game->associations()->attach($this->assoc);
    $this->game->phase(1)->update(['enabled' => true, 'status' => PhaseStatus::Open, 'deadline' => now()->addWeek()]);
    $this->sub = Submission::for($this->game, $this->assoc, 1);
    $this->admin = User::factory()->assocAdmin($this->assoc)->create();
    $this->editor = User::factory()->assocEditor($this->assoc)->create();
    $this->sta = User::factory()->sta()->create(['name' => 'Huda STA']);
    $this->sta->givePermissionTo([StaPermission::ViewSubmissions->value, StaPermission::EditSubmissions->value]);
    $this->viewer = User::factory()->sta()->create();
    $this->viewer->givePermissionTo(StaPermission::ViewSubmissions->value);
    $this->super = User::factory()->superAdmin()->create();
});

it('sends an STA remark from the game dashboard cell to the association by bell and email', function () {
    actingInAdmin($this->sta);
    livewire(GameDashboard::class, ['record' => $this->game->getRouteKey()])
        ->assertSee('Send remark')->assertSee("mountAction('remark', { association: {$this->assoc->id}, phase: 1 })")
        ->callAction('remark', ['body' => 'Please check <b>weights</b>.'], ['association' => $this->assoc->id, 'phase' => 1])
        ->assertHasNoActionErrors();

    $c = Comment::sole();
    expect($c->from_association)->toBeFalse()->and($c->submission_id)->toBe($this->sub->id);
    // Bell: real database rows for both association users, linking to the phase page; escaped.
    foreach ([$this->admin, $this->editor] as $u) {
        $n = $u->notifications()->sole();
        expect($n->data['submission_id'])->toBe($this->sub->id)
            ->and($n->data['title'])->toBe('New STA remark on Phase 1 for Asian Indoor Games')
            ->and($n->data['body'])->toContain('&lt;b&gt;')
            ->and($n->data['actions'][0]['url'])->toContain("/portal/{$this->assoc->id}/games/{$this->game->id}/phase-1");
    }
    expect($this->sta->notifications()->count())->toBe(0);
});

it('emails the remark (mail channel) to the association admin and editors', function () {
    Notification::fake();
    actingInAdmin($this->sta);
    livewire(SubmissionView::class, ['record' => $this->game->getRouteKey(), 'submission' => $this->sub])
        ->callAction('comment', ['body' => 'Upload scans'])->assertHasNoActionErrors();

    Notification::assertSentTo([$this->admin, $this->editor], NewComment::class, fn ($n, $channels) => $channels === ['database', 'mail']);
    Notification::assertNotSentTo([$this->sta, $this->super], NewComment::class);
});

it('creates the submission when a remark is sent to an association that has not started the phase', function () {
    $other = Association::factory()->create();
    $this->game->associations()->attach($other);
    Notification::fake();
    actingInAdmin($this->sta);
    livewire(GameDashboard::class, ['record' => $this->game->getRouteKey()])
        ->callAction('remark', ['body' => 'Hello'], ['association' => $other->id, 'phase' => 1])->assertHasNoActionErrors();

    expect(Comment::sole()->submission->association_id)->toBe($other->id);
});

it('hides the dashboard Remark without edit_submissions', function () {
    actingInAdmin($this->viewer);
    livewire(GameDashboard::class, ['record' => $this->game->getRouteKey()])
        ->assertDontSee('Send remark')->assertActionHidden('remark');
});

it('lets an association editor reply on the phase page; STA with edit_submissions and Super Admins get bell + email', function () {
    Comment::post($this->sub, $this->sta, 'Please upload the passport scans.');
    actingInPortal($this->editor);
    Notification::fake();

    livewire(Phase1Entry::class, ['game' => $this->game->id])
        ->assertSee('Reply to STA')
        ->set('replyBody', 'Done, uploaded <i>today</i>.')->call('reply')->assertHasNoErrors()
        ->assertSee('Done, uploaded <i>today</i>.', escape: true)->assertDontSeeHtml('<i>today</i>');

    $reply = Comment::latest('id')->first();
    expect($reply->from_association)->toBeTrue()->and($reply->user_id)->toBe($this->editor->id);
    Notification::assertSentTo([$this->sta, $this->super], NewComment::class, fn ($n, $channels) => $channels === ['database', 'mail']);
    Notification::assertNotSentTo([$this->viewer, $this->admin, $this->editor], NewComment::class);

    $db = (new NewComment($reply))->toDatabase($this->sta);
    $mail = (new NewComment($reply))->toMail($this->sta);
    expect($db['title'])->toBe('Bahrain Judo Federation replied on Phase 1 for Asian Indoor Games')
        ->and($db['actions'][0]['url'])->toContain("/admin/games/{$this->game->id}/submissions/{$this->sub->id}")
        ->and($mail->subject)->toBe('Bahrain Judo Federation replied on Phase 1 for Asian Indoor Games');
});

it('sends no reply notification to an inactive STA user with edit_submissions', function () {
    $inactive = User::factory()->sta()->inactive()->create();
    $inactive->givePermissionTo([StaPermission::ViewSubmissions->value, StaPermission::EditSubmissions->value]);
    Comment::post($this->sub, $this->sta, 'Remark');
    Notification::fake();
    Comment::post($this->sub, $this->editor, 'Reply');

    Notification::assertSentTo($this->sta, NewComment::class);
    Notification::assertNotSentTo($inactive, NewComment::class);
});

it('refuses a reply when STA has written no remark on the submission', function () {
    actingInPortal($this->editor);
    livewire(Phase1Entry::class, ['game' => $this->game->id])->assertDontSee('Reply to STA')
        ->set('replyBody', 'Unsolicited')->call('reply')->assertForbidden();
    expect(Comment::count())->toBe(0);
});

it('limits replies to 10 per user per 10 minutes', function () {
    Comment::post($this->sub, $this->sta, 'Remark');
    actingInPortal($this->editor);
    Notification::fake();
    $page = livewire(Phase1Entry::class, ['game' => $this->game->id]);
    foreach (range(1, 10) as $i) {
        $page->set('replyBody', "Reply {$i}")->call('reply')->assertHasNoErrors();
    }
    $page->set('replyBody', 'One too many')->call('reply')->assertHasErrors('replyBody')->assertSee('many replies in a short time');
    expect(Comment::where('from_association', true)->count())->toBe(10);
});

it('escapes Markdown in the mailed body so a reply cannot become a link', function () {
    $c = Comment::post($this->sub, $this->editor, 'See [click](https://evil.example) **now** # x');
    $html = (string) (new NewComment($c))->toMail($this->sta)->render();

    expect($html)->not->toContain('href="https://evil.example"')->not->toContain('<strong>')
        ->and($html)->toContain('[click](https://evil.example)');
});

it('requires a reply body', function () {
    Comment::post($this->sub, $this->sta, 'Remark');
    actingInPortal($this->admin);
    livewire(Phase1Entry::class, ['game' => $this->game->id])->set('replyBody', '  ')->call('reply')->assertHasErrors('replyBody');
    expect(Comment::count())->toBe(1);
});

it('keeps another association\'s editor away from the remarks and replies', function () {
    Comment::post($this->sub, $this->sta, 'Secret remark for BJF');
    $otherAssoc = Association::factory()->create();
    $this->game->associations()->attach($otherAssoc);
    $intruder = User::factory()->assocEditor($otherAssoc)->create();
    actingInPortal($intruder);

    // Their own phase page shows only their own (empty) thread; with no STA remark there, a reply is refused.
    livewire(Phase1Entry::class, ['game' => $this->game->id])->assertDontSee('Secret remark for BJF')
        ->set('replyBody', 'x')->call('reply')->assertForbidden();
    expect(Comment::count())->toBe(1);
    // Home card: nothing from another association.
    livewire(Home::class)->assertDontSee('Remarks from STA')->assertDontSee('Secret remark for BJF');
    // The other association's phase page is out of reach by URL.
    $this->get("/portal/{$this->assoc->id}/games/{$this->game->id}/phase-1")->assertRedirect(); // to their own home, never this association's page
});

it('refuses a reply from the STA edit page (portal only)', function () {
    actingInAdmin($this->sta);
    livewire(StaPhase1Edit::class, ['record' => $this->game->getRouteKey(), 'submission' => $this->sub])
        ->set('replyBody', 'x')->call('reply')->assertForbidden();
});

it('shows "Remarks from STA" on the portal Home with unread marks, cleared by opening the phase page', function () {
    Comment::post($this->sub, $this->sta, 'Check the weights.');
    actingInPortal($this->admin);

    livewire(Home::class)->assertSee('Remarks from STA')->assertSee('1 unread')->assertSee('Check the weights.')->assertSee('New')
        ->assertSeeHtml("/portal/{$this->assoc->id}/games/{$this->game->id}/phase-1");

    livewire(Phase1Entry::class, ['game' => $this->game->id]);
    expect($this->admin->unreadNotifications()->count())->toBe(0);
    livewire(Home::class)->assertSee('Latest remarks')->assertDontSee('1 unread');
});

it('counts unread replies under "Needs your attention" on the admin home', function () {
    Comment::post($this->sub, $this->sta, 'Remark');
    Comment::post($this->sub, $this->editor, 'Reply one');
    Comment::post($this->sub, $this->admin, 'Reply two');
    actingInAdmin($this->sta);

    livewire(Dashboard::class)->assertSee('2 replies from associations')->assertSee('Bahrain Judo Federation')
        ->assertSeeHtml("/admin/games/{$this->game->id}/submissions/{$this->sub->id}");

    livewire(SubmissionView::class, ['record' => $this->game->getRouteKey(), 'submission' => $this->sub]);
    livewire(Dashboard::class)->assertDontSee('replies from associations');
});

it('leaves out the replies item when the replies behind the unread bells were deleted', function () {
    Comment::post($this->sub, $this->sta, 'Remark');
    Comment::post($this->sub, $this->editor, 'Reply')->delete();
    actingInAdmin($this->sta);
    expect($this->sta->unreadNotifications()->count())->toBe(1);

    livewire(Dashboard::class)->assertOk()->assertDontSee('from associations');
});

it('sends nothing for a draft game', function () {
    $this->game->update(['published_at' => null]);
    Notification::fake();
    Comment::post($this->sub, $this->sta, 'Draft note');
    Comment::post($this->sub, $this->editor, 'Draft reply');
    Notification::assertNothingSent();
});

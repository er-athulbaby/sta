<?php

use App\Enums\PhaseStatus;
use App\Enums\StaPermission;
use App\Filament\Portal\Pages\Phase1Entry;
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
    Notification::fake();
    $this->assoc = Association::factory()->create(['code' => 'BJF']);
    $this->game = Game::factory()->create(['name' => 'Asian Indoor Games']);
    $this->game->associations()->attach($this->assoc);
    $this->game->phase(1)->update(['enabled' => true, 'status' => PhaseStatus::Open, 'deadline' => now()->addWeek()]);
    $this->sub = Submission::for($this->game, $this->assoc, 1);
    $this->admin = User::factory()->assocAdmin($this->assoc)->create();
    $this->editor = User::factory()->assocEditor($this->assoc)->create();
    $this->inactive = User::factory()->assocEditor($this->assoc)->inactive()->create();
    $this->otherUser = User::factory()->assocAdmin()->create();
    $this->sta = User::factory()->sta()->create(['name' => 'Huda STA']);
    $this->sta->givePermissionTo([StaPermission::ViewSubmissions->value, StaPermission::EditSubmissions->value]);
});

it('lets STA with edit_submissions comment and emails the association\'s active users', function () {
    actingInAdmin($this->sta);
    livewire(SubmissionView::class, ['record' => $this->game->getRouteKey(), 'submission' => $this->sub])
        ->callAction('comment', ['body' => 'Please upload the passport scans.'])->assertHasNoActionErrors()
        ->assertSee('Please upload the passport scans.');

    $c = Comment::sole();
    expect($c->user_id)->toBe($this->sta->id)->and($c->submission_id)->toBe($this->sub->id);
    Notification::assertSentTo([$this->admin, $this->editor], NewComment::class);
    Notification::assertNotSentTo([$this->inactive, $this->otherUser], NewComment::class);
});

it('words the email with the game, phase and comment', function () {
    $c = Comment::create(['submission_id' => $this->sub->id, 'user_id' => $this->sta->id, 'body' => 'Please upload the passport scans.']);
    $mail = (new NewComment($c))->toMail($this->admin);

    expect($mail->subject)->toBe('New STA comment on Phase 1 for Asian Indoor Games')
        ->and($mail->introLines)->toContain('Please upload the passport scans.');
});

it('hides the comment action without edit_submissions', function () {
    $viewer = User::factory()->sta()->create();
    $viewer->givePermissionTo(StaPermission::ViewSubmissions->value);
    actingInAdmin($viewer);
    livewire(SubmissionView::class, ['record' => $this->game->getRouteKey(), 'submission' => $this->sub])->assertActionHidden('comment');
});

it('shows comments read-only on the association\'s phase page, escaped', function () {
    Comment::create(['submission_id' => $this->sub->id, 'user_id' => $this->sta->id, 'body' => 'Please upload the passport scans. <b>x</b>']);
    actingInPortal($this->admin);

    livewire(Phase1Entry::class, ['game' => $this->game->id])
        ->assertSee('Comments from STA')->assertSee('Please upload the passport scans.')->assertSee('Huda STA')->assertDontSee('Add comment')
        ->assertDontSeeHtml('<b>x</b>');
});

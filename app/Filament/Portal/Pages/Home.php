<?php

namespace App\Filament\Portal\Pages;

use App\Models\Comment;
use App\Notifications\NewComment;
use Filament\Facades\Filament;
use Filament\Pages\Dashboard;
use Filament\Support\Components\Attributes\Unexposed;
use Illuminate\Support\Collection;

/** Portal home (canvas menu "Home"): "Remarks from STA" (when there are any), then the same game cards as the Games page. */
class Home extends Dashboard
{
    protected string $view = 'filament.portal.pages.games';

    protected static ?string $title = 'Home';

    protected static ?string $navigationLabel = 'Home';

    public function getPageClasses(): array
    {
        return ['sta-canvas-page'];
    }

    public function getGamesProperty(): Collection
    {
        return Games::forTenant(Filament::getTenant());
    }

    /**
     * The 5 latest STA remarks on this association's published games, unread ones (their bell not yet read) marked.
     *
     * @return Collection<int, array{comment: Comment, unread: bool, url: string}>
     */
    #[Unexposed]
    public function remarks(): Collection
    {
        $unread = auth()->user()->unreadNotifications()->where('type', NewComment::class)->get()->pluck('data.comment_id')->filter()->all();

        return Comment::query()->where('from_association', false)
            // Tenant isolation: only this association's submissions (the submission scope adds the same for association users).
            ->whereHas('submission', fn ($q) => $q->where('association_id', Filament::getTenant()->getKey())
                ->whereHas('game', fn ($g) => $g->whereNotNull('published_at')))
            ->with('submission.game')->latest('id')->limit(5)->get()
            ->map(fn (Comment $c) => ['comment' => $c, 'unread' => in_array($c->id, $unread, true),
                'url' => Games::phasePage($c->submission->phase)::getUrl(['game' => $c->submission->game_id])]);
    }
}

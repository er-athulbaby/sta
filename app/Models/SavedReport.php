<?php

namespace App\Models;

use App\Enums\StaPermission;
use App\Services\Reports\Builder\ReportDefinition;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** A report builder definition (spec 14 Storage). The definition is re-validated on every run. */
class SavedReport extends Model
{
    public const VISIBILITIES = ['private' => 'Only me', 'shared' => 'Everyone with report access'];

    protected $fillable = ['name', 'subject', 'definition', 'visibility', 'created_by', 'updated_by', 'last_run_at'];

    protected function casts(): array
    {
        return ['definition' => 'array', 'last_run_at' => 'datetime'];
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** The stored definition, re-validated for the user running it (unknown fields, draft games... are refused). */
    public function toDefinition(?User $user): ReportDefinition
    {
        return ReportDefinition::fromArray($this->definition, $user);
    }

    /** Own reports and shared ones; the Super Admin sees all; nothing without view_reports. */
    public function scopeVisibleTo(Builder $query, User $user): void
    {
        if ($user->isSuperAdmin()) {
            return;
        }
        if (! $user->can(StaPermission::ViewReports->value)) {
            $query->whereRaw('1 = 0');

            return;
        }
        $query->where(fn (Builder $q) => $q->where('created_by', $user->id)->orWhere('visibility', 'shared'));
    }

    /** "My reports" tab: created by this user. */
    public function scopeMine(Builder $query, User $user): void
    {
        $query->where('created_by', $user->id);
    }

    /** "Shared" tab: visibility shared only (a Super Admin's view of others' private reports is not "shared"). */
    public function scopeShared(Builder $query): void
    {
        $query->where('visibility', 'shared');
    }
}

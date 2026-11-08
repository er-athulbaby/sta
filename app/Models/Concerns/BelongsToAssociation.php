<?php

namespace App\Models\Concerns;

use App\Models\Association;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Defence in depth on top of Filament tenancy: association users can only read and
 * create rows of their own association, in any code path (jobs, exports, controllers).
 */
trait BelongsToAssociation
{
    public static function bootBelongsToAssociation(): void
    {
        static::addGlobalScope('association', function (Builder $query) {
            $user = auth()->user();
            if ($user instanceof User && $user->role->isAssociationRole()) {
                $query->where($query->qualifyColumn('association_id'), $user->association_id);
            }
        });

        static::creating(function (Model $model) {
            $user = auth()->user();
            if ($user instanceof User && $user->role->isAssociationRole()) {
                $model->association_id = $user->association_id;
            }
        });
    }

    public function association(): BelongsTo
    {
        return $this->belongsTo(Association::class);
    }
}

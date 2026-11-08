<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Association extends Model
{
    use HasFactory;

    protected $fillable = ['name', 'email', 'logo_path', 'phone', 'address', 'website'];

    public function invitations(): HasMany
    {
        return $this->hasMany(Invitation::class);
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function people(): HasMany
    {
        return $this->hasMany(Person::class);
    }

    public function sports(): HasMany
    {
        return $this->hasMany(Sport::class);
    }
}

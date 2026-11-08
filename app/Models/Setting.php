<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class Setting extends Model
{
    public const NOC_LOGO = 'noc_logo_path';
    public const DECLARATION = 'pdf_declaration';
    public const DEFAULT_DECLARATION = 'By signing below, the Federation Representative confirms that all information provided in this document is accurate, final, and complies with the regulations of the event.';

    protected $primaryKey = 'key';
    public $incrementing = false;
    protected $keyType = 'string';
    protected $fillable = ['key', 'value'];

    public static function get(string $key, mixed $default = null): mixed
    {
        return Cache::rememberForever("setting.$key", fn () => static::find($key)?->value) ?? $default;
    }

    public static function put(string $key, ?string $value): void
    {
        static::updateOrCreate(['key' => $key], ['value' => $value]);
        Cache::forget("setting.$key");
    }
}

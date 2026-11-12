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

    /** Settings key of one phase's declaration (Phase 1–4 PDFs). */
    public static function phaseDeclarationKey(int $phase): string
    {
        return self::DECLARATION.'_phase_'.$phase;
    }

    /** The phase's own declaration; empty falls back to the single declaration, then to the built-in text. */
    public static function declaration(int $phase): string
    {
        return filled($text = static::get(self::phaseDeclarationKey($phase)))
            ? $text
            : static::get(self::DECLARATION, self::DEFAULT_DECLARATION);
    }

    public static function put(string $key, ?string $value): void
    {
        static::updateOrCreate(['key' => $key], ['value' => $value]);
        Cache::forget("setting.$key");
    }
}

<?php

namespace App\Services\Reports\Builder;

/** The subjects, in picker order. */
final class Registry
{
    public const SUBJECTS = [
        Subjects\People::class,
        Subjects\Entries::class,
        Subjects\Submissions::class,
        Subjects\Phase1Answers::class,
        Subjects\Phase2Numbers::class,
        Subjects\Phase2SportTotals::class,
        Subjects\ClothingSizes::class,
    ];

    /** @return array<string, Subject> */
    public static function all(): array
    {
        $subjects = [];
        foreach (self::SUBJECTS as $class) {
            $subject = new $class;
            $subjects[$subject->key()] = $subject;
        }

        return $subjects;
    }

    public static function get(string $key): ?Subject
    {
        return self::all()[$key] ?? null;
    }
}

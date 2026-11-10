<?php

namespace App\Exports;

use OpenSpout\Common\Entity\Cell;
use OpenSpout\Common\Entity\Cell\StringCell;
use OpenSpout\Common\Entity\Row;

final class LiteralRow
{
    /** Strings are written as literal text: Cell::fromValue would turn a leading "=" into a live formula. */
    public static function from(array $values): Row
    {
        return new Row(array_map(fn ($v) => is_string($v) && $v !== '' ? new StringCell($v, null) : Cell::fromValue($v), $values));
    }
}

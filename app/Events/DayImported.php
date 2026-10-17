<?php

namespace App\Events;

use App\Data\ImportResult;
use Illuminate\Foundation\Events\Dispatchable;

final class DayImported
{
    use Dispatchable;

    /**
     * @param  ImportResult  $result
     */
    public function __construct(public readonly ImportResult $result) {}
}

<?php

namespace App\Repositories\Contracts;

use App\Data\ImportResult;
use Carbon\CarbonImmutable;

interface ImportRunRepositoryInterface
{
    /**
     * @param  CarbonImmutable  $date
     * @return bool
     */
    public function isCompleted(CarbonImmutable $date): bool;

    /**
     * @param  CarbonImmutable  $date
     * @return void
     */
    public function markStarted(CarbonImmutable $date): void;

    /**
     * @param  ImportResult  $result
     * @return void
     */
    public function markCompleted(ImportResult $result): void;

    /**
     * @param  CarbonImmutable  $date
     * @param  string  $error
     * @return void
     */
    public function markFailed(CarbonImmutable $date, string $error): void;

    /**
     * @return CarbonImmutable|null
     */
    public function lastCompletedDate(): ?CarbonImmutable;
}

<?php

namespace App\Data;

use Carbon\CarbonImmutable;
use InvalidArgumentException;

final readonly class DateRange
{
    /**
     * @var CarbonImmutable
     */
    public CarbonImmutable $from;

    /**
     * @var CarbonImmutable
     */
    public CarbonImmutable $to;

    /**
     * @param  CarbonImmutable  $from
     * @param  CarbonImmutable  $to
     *
     * @throws InvalidArgumentException
     */
    public function __construct(CarbonImmutable $from, CarbonImmutable $to)
    {
        if ($from->greaterThan($to)) {
            throw new InvalidArgumentException('The start date must be on or before the end date.');
        }

        $this->from = $from->startOfDay();
        $this->to = $to->startOfDay();
    }

    /**
     * @param  string  $from
     * @param  string  $to
     * @return self
     *
     * @throws InvalidArgumentException
     */
    public static function fromStrings(string $from, string $to): self
    {
        return new self(
            CarbonImmutable::createFromFormat('Y-m-d', $from),
            CarbonImmutable::createFromFormat('Y-m-d', $to),
        );
    }

    /**
     * @param  self  $other
     * @return bool
     */
    public function contains(self $other): bool
    {
        return $other->from->greaterThanOrEqualTo($this->from)
            && $other->to->lessThanOrEqualTo($this->to);
    }

    /**
     * @return int
     */
    public function days(): int
    {
        return (int) $this->from->diffInDays($this->to) + 1;
    }
}

<?php

namespace Tests\Unit\Data;

use App\Data\DateRange;
use Carbon\CarbonImmutable;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class DateRangeTest extends TestCase
{
    /**
     * @return void
     */
    public function test_a_start_after_the_end_is_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);

        DateRange::fromStrings('2026-09-10', '2026-09-01');
    }

    /**
     * @return void
     */
    public function test_days_are_counted_including_both_ends(): void
    {
        $this->assertSame(30, DateRange::fromStrings('2026-09-01', '2026-09-30')->days());
        $this->assertSame(1, DateRange::fromStrings('2026-09-01', '2026-09-01')->days());
    }

    /**
     * @return void
     */
    public function test_the_time_of_day_is_removed(): void
    {
        $range = new DateRange(CarbonImmutable::parse('2026-09-01 18:30'), CarbonImmutable::parse('2026-09-02 07:00'));

        $this->assertSame('2026-09-01 00:00:00', $range->from->toDateTimeString());
        $this->assertSame('2026-09-02 00:00:00', $range->to->toDateTimeString());
    }

    /**
     * @return void
     */
    public function test_contains_includes_the_edges(): void
    {
        $history = DateRange::fromStrings('2026-07-17', '2026-10-16');

        $this->assertTrue($history->contains(DateRange::fromStrings('2026-07-17', '2026-10-16')));
        $this->assertTrue($history->contains(DateRange::fromStrings('2026-08-01', '2026-08-31')));
        $this->assertFalse($history->contains(DateRange::fromStrings('2026-07-16', '2026-08-01')));
        $this->assertFalse($history->contains(DateRange::fromStrings('2026-10-01', '2026-10-17')));
    }
}

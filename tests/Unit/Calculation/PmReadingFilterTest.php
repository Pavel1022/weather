<?php

namespace Tests\Unit\Calculation;

use App\Services\AirQuality\Calculation\PmReadingFilter;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class PmReadingFilterTest extends TestCase
{
    /**
     * @param  float|null  $value
     * @param  bool  $expected
     * @return void
     */
    #[DataProvider('values')]
    public function test_it_validates_pm25_values(?float $value, bool $expected): void
    {
        $filter = new PmReadingFilter(minimum: 0.0, maximum: 500.0);

        $this->assertSame($expected, $filter->isValid($value));
    }

    /**
     * @return array<string, array{float|null, bool}>
     */
    public static function values(): array
    {
        return [
            'normal value' => [12.3, true],
            'sds011 error code' => [999.9, false],
            'at maximum' => [500.0, false],
            'negative' => [-1.0, false],
            'zero' => [0.0, false],
            'missing' => [null, false],
        ];
    }
}

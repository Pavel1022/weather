<?php

namespace Tests\Unit\Calculation;

use App\Data\City;
use App\Services\AirQuality\Calculation\CityResolver;
use App\Services\AirQuality\Calculation\HaversineDistance;
use PHPUnit\Framework\TestCase;

final class DistanceAndCityResolverTest extends TestCase
{
    /**
     * @var float
     */
    private const KM_PER_DEGREE = 111.195;

    /**
     * @return void
     */
    public function test_the_distance_to_the_same_point_is_zero(): void
    {
        $this->assertSame(0.0, (new HaversineDistance)->kilometersBetween(42.6977, 23.3219, 42.6977, 23.3219));
    }

    /**
     * @return void
     */
    public function test_sofia_and_plovdiv_centres_are_about_132_km_apart_in_both_directions(): void
    {
        $distance = new HaversineDistance;

        $this->assertEqualsWithDelta(132.5, $distance->kilometersBetween(42.6977, 23.3219, 42.1354, 24.7453), 0.1);
        $this->assertEqualsWithDelta(
            $distance->kilometersBetween(42.6977, 23.3219, 42.1354, 24.7453),
            $distance->kilometersBetween(42.1354, 24.7453, 42.6977, 23.3219),
            0.0001,
        );
    }

    /**
     * @return void
     */
    public function test_a_point_inside_the_radius_belongs_to_the_city(): void
    {
        $varna = new City('Varna', 43.2141, 27.9147);

        $this->assertSame($varna, $this->resolver()->resolve(43.2141 + 5 / self::KM_PER_DEGREE, 27.9147, [$varna]));
        $this->assertSame($varna, $this->resolver()->resolve(43.2141 + 9.9 / self::KM_PER_DEGREE, 27.9147, [$varna]));
    }

    /**
     * @return void
     */
    public function test_a_point_outside_the_radius_belongs_to_no_city(): void
    {
        $varna = new City('Varna', 43.2141, 27.9147);

        $this->assertNull($this->resolver()->resolve(43.2141 + 10.1 / self::KM_PER_DEGREE, 27.9147, [$varna]));
        $this->assertNull($this->resolver()->resolve(43.2141, 27.9147, []));
    }

    /**
     * @return void
     */
    public function test_the_closest_city_wins_when_two_are_in_range(): void
    {
        $near = new City('Near', 42.0, 23.0);
        $far = new City('Far', 42.0 + 8 / self::KM_PER_DEGREE, 23.0);

        $this->assertSame($near, $this->resolver()->resolve(42.0 + 2 / self::KM_PER_DEGREE, 23.0, [$far, $near]));
    }

    /**
     * @return CityResolver
     */
    private function resolver(): CityResolver
    {
        return new CityResolver(new HaversineDistance, radiusKm: 10.0);
    }
}

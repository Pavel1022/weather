<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Table(keyType: 'int', incrementing: false)]
#[Fillable(['id', 'type', 'city', 'latitude', 'longitude', 'distance_km'])]
class Sensor extends Model
{
    /**
     * @return HasMany<SensorDailyAverage, $this>
     */
    public function dailyAverages(): HasMany
    {
        return $this->hasMany(SensorDailyAverage::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'id' => 'integer',
            'latitude' => 'float',
            'longitude' => 'float',
            'distance_km' => 'float',
        ];
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['sensor_id', 'city', 'date', 'pm25_sum', 'pm25_count', 'pm25_avg'])]
class SensorDailyAverage extends Model
{
    /**
     * @return BelongsTo<Sensor, $this>
     */
    public function sensor(): BelongsTo
    {
        return $this->belongsTo(Sensor::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'sensor_id' => 'integer',
            'date' => 'immutable_date',
            'pm25_sum' => 'float',
            'pm25_count' => 'integer',
            'pm25_avg' => 'float',
        ];
    }
}

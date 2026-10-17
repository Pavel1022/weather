<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['date', 'status', 'files', 'failed_files', 'readings', 'rejected_readings', 'sensor_days', 'error', 'finished_at'])]
class ImportRun extends Model
{
    /**
     * @var string
     */
    public const STATUS_RUNNING = 'running';

    /**
     * @var string
     */
    public const STATUS_COMPLETED = 'completed';

    /**
     * @var string
     */
    public const STATUS_FAILED = 'failed';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'date' => 'immutable_date',
            'files' => 'integer',
            'failed_files' => 'integer',
            'readings' => 'integer',
            'rejected_readings' => 'integer',
            'sensor_days' => 'integer',
            'finished_at' => 'immutable_datetime',
        ];
    }
}

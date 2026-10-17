<?php

return [
    'history_days' => 92,
    'radius_km' => 10.0,
    'min_readings_per_day' => 100,
    'min_valid_pm25' => 0.0,
    'max_valid_pm25' => 500.0,

    'outliers' => [
        'max_ratio_to_median' => 5.0,
        'min_difference' => 25.0,
        'min_sensors' => 3,
    ],

    'cities' => [
        ['name' => 'Sofia', 'latitude' => 42.6977, 'longitude' => 23.3219],
        ['name' => 'Plovdiv', 'latitude' => 42.1354, 'longitude' => 24.7453],
        ['name' => 'Varna', 'latitude' => 43.2141, 'longitude' => 27.9147],
        ['name' => 'Burgas', 'latitude' => 42.5048, 'longitude' => 27.4626],
        ['name' => 'Ruse', 'latitude' => 43.8356, 'longitude' => 25.9657],
    ],

    'sensor_community' => [
        'api_url' => env('SENSOR_COMMUNITY_API_URL', 'https://data.sensor.community'),
        'archive_url' => env('SENSOR_COMMUNITY_ARCHIVE_URL', 'https://archive.sensor.community'),
        'timeout' => 30,
        'retries' => 3,
        'retry_delay_ms' => 500,
        'concurrency' => 10,
    ],
];

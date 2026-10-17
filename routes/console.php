<?php

use Illuminate\Support\Facades\Schedule;

Schedule::command('air:import --days=2')
    ->dailyAt('03:00')
    ->timezone('UTC')
    ->withoutOverlapping();

Schedule::command('air:discover')
    ->weeklyOn(1, '02:00')
    ->timezone('UTC')
    ->withoutOverlapping();

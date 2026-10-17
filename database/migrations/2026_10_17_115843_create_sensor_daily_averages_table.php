<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * @return void
     */
    public function up(): void
    {
        Schema::create('sensor_daily_averages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sensor_id')->constrained('sensors')->cascadeOnDelete();
            $table->string('city', 32);
            $table->date('date');
            $table->double('pm25_sum');
            $table->unsignedInteger('pm25_count');
            $table->double('pm25_avg');
            $table->timestamps();
            $table->unique(['sensor_id', 'date']);
            $table->index(['city', 'date']);
            $table->index('date');
        });
    }

    /**
     * @return void
     */
    public function down(): void
    {
        Schema::dropIfExists('sensor_daily_averages');
    }
};

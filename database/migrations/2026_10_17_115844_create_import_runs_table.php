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
        Schema::create('import_runs', function (Blueprint $table) {
            $table->id();
            $table->date('date')->unique();
            $table->string('status', 16);
            $table->unsignedInteger('files')->default(0);
            $table->unsignedInteger('failed_files')->default(0);
            $table->unsignedInteger('readings')->default(0);
            $table->unsignedInteger('rejected_readings')->default(0);
            $table->unsignedInteger('sensor_days')->default(0);
            $table->text('error')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * @return void
     */
    public function down(): void
    {
        Schema::dropIfExists('import_runs');
    }
};

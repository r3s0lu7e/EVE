<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('locations', function (Blueprint $table) {
            // location_id may be a station id or a player structure (citadel) id.
            $table->unsignedBigInteger('location_id')->primary();
            $table->string('name')->nullable();
            $table->unsignedBigInteger('system_id')->nullable();
            $table->string('system_name')->nullable();
            $table->unsignedBigInteger('region_id')->nullable();
            $table->string('region_name')->nullable();
            $table->timestamps();

            $table->index('region_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('locations');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('eve_types', function (Blueprint $table) {
            // type_id is the EVE inventory type id.
            $table->unsignedBigInteger('type_id')->primary();
            $table->string('name')->nullable();
            $table->unsignedBigInteger('group_id')->nullable();
            $table->string('group_name')->nullable();
            $table->string('category_name')->nullable();
            $table->timestamps();

            $table->index('name');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('eve_types');
    }
};

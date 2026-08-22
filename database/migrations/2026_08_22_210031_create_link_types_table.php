<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('link_types', function (Blueprint $table) {
            $table->id();
            $table->string('name', 60);
            $table->string('slug', 20)->unique();
            $table->string('badge_label', 12);
            $table->string('dash_array', 20)->nullable();
            $table->boolean('is_bidirectional_default')->default(false);
            $table->string('gloss', 255);
            $table->smallInteger('position');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('link_types');
    }
};

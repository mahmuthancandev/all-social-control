<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('projects', function (Blueprint $table) {
            $table->id();
            $table->string('name', 120);
            $table->string('slug', 140)->unique();
            $table->string('type', 20)->default('game'); // game | app
            $table->string('category', 80)->nullable();
            $table->text('description')->nullable();
            $table->string('logo_path')->nullable();
            $table->string('color', 9)->default('#6366f1');
            $table->boolean('is_demo')->default(false)->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('projects');
    }
};

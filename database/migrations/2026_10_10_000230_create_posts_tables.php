<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('posts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('title', 255)->nullable();
            $table->text('body')->nullable();
            $table->json('hashtags')->nullable();
            $table->string('status', 30)->default('draft')->index();
            $table->timestamp('scheduled_at')->nullable()->index();
            $table->boolean('is_demo')->default(false)->index();
            $table->timestamps();
        });

        // Bir medya dosyası birden fazla gönderide kullanılabilir.
        Schema::create('post_media', function (Blueprint $table) {
            $table->id();
            $table->foreignId('post_id')->constrained()->cascadeOnDelete();
            $table->foreignId('media_file_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('position')->default(0);
            $table->timestamps();

            $table->unique(['post_id', 'media_file_id']);
        });

        Schema::create('post_platform_targets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('post_id')->constrained()->cascadeOnDelete();
            $table->foreignId('social_account_id')->constrained()->cascadeOnDelete();
            $table->string('platform', 20)->index();
            $table->string('content_type', 30);
            $table->string('title', 255)->nullable();
            $table->text('caption')->nullable();
            $table->json('hashtags')->nullable();
            $table->foreignId('media_file_id')->nullable()->constrained('media_files')->nullOnDelete();
            $table->foreignId('thumbnail_media_id')->nullable()->constrained('media_files')->nullOnDelete();
            $table->json('settings')->nullable();
            $table->string('publish_mode', 10)->default('manual'); // api | manual
            $table->string('status', 30)->default('draft')->index();
            $table->timestamp('scheduled_at')->nullable()->index();
            $table->timestamp('published_at')->nullable();
            $table->string('external_id', 191)->nullable();
            $table->string('external_url', 1000)->nullable();
            $table->text('error_message')->nullable();
            $table->unsignedSmallInteger('attempts')->default(0);
            // Aynı hedefin iki kez yayınlanmasını engelleyen anahtar.
            $table->string('idempotency_key', 64)->unique();
            $table->boolean('is_demo')->default(false);
            $table->timestamps();

            $table->unique(['post_id', 'social_account_id', 'content_type']);
            $table->index(['status', 'scheduled_at']);
        });

        Schema::create('publication_jobs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('post_platform_target_id')->constrained()->cascadeOnDelete();
            $table->foreignId('triggered_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('mode', 10); // api | manual | demo
            $table->string('status', 20)->index(); // running | succeeded | failed | manual
            $table->unsignedSmallInteger('attempt')->default(1);
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->text('message')->nullable();
            $table->json('response')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('publication_jobs');
        Schema::dropIfExists('post_platform_targets');
        Schema::dropIfExists('post_media');
        Schema::dropIfExists('posts');
    }
};

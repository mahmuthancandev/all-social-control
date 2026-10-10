<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('comments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('social_account_id')->constrained()->cascadeOnDelete();
            $table->foreignId('external_post_id')->nullable()->constrained()->nullOnDelete();
            $table->string('external_id', 191);
            $table->string('parent_external_id', 191)->nullable();
            $table->string('author_name', 191)->nullable();
            $table->string('author_avatar_url', 1000)->nullable();
            $table->text('body');
            $table->unsignedInteger('like_count')->nullable();
            $table->timestamp('commented_at')->nullable()->index();
            $table->timestamp('read_at')->nullable()->index();
            $table->text('reply_body')->nullable();
            $table->timestamp('replied_at')->nullable();
            $table->foreignId('replied_by')->nullable()->constrained('users')->nullOnDelete();
            $table->boolean('is_demo')->default(false)->index();
            $table->timestamps();

            $table->unique(['social_account_id', 'external_id']);
        });

        Schema::create('notification_rules', function (Blueprint $table) {
            $table->id();
            $table->string('type', 40); // views_threshold | followers_threshold | schedule_reminder
            $table->foreignId('social_account_id')->nullable()->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('threshold')->nullable();
            $table->unsignedInteger('minutes_before')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_triggered_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('notifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('type', 50)->index();
            $table->string('level', 10)->default('info'); // info | success | warning | error
            $table->string('title', 255);
            $table->text('body')->nullable();
            $table->string('url', 500)->nullable();
            $table->json('data')->nullable();
            // Aynı olayın aynı kullanıcıya iki kez bildirilmesini engeller.
            $table->string('dedupe_key', 191)->nullable();
            $table->timestamp('read_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'read_at']);
            $table->unique(['user_id', 'dedupe_key']);
        });

        Schema::create('sync_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('social_account_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('type', 30); // profile | metrics | posts | comments | import
            $table->string('status', 20); // running | success | partial | failed | skipped
            $table->unsignedInteger('items_count')->nullable();
            $table->text('message')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();

            $table->index(['social_account_id', 'created_at']);
        });

        Schema::create('api_quota_usages', function (Blueprint $table) {
            $table->id();
            $table->string('platform', 20);
            $table->date('usage_date');
            $table->unsignedInteger('units_used')->default(0);
            $table->timestamps();

            $table->unique(['platform', 'usage_date']);
        });

        Schema::create('settings', function (Blueprint $table) {
            $table->id();
            $table->string('key', 120)->unique();
            $table->text('value')->nullable();
            $table->boolean('is_encrypted')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('settings');
        Schema::dropIfExists('api_quota_usages');
        Schema::dropIfExists('sync_logs');
        Schema::dropIfExists('notifications');
        Schema::dropIfExists('notification_rules');
        Schema::dropIfExists('comments');
    }
};

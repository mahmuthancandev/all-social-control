<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('social_accounts', function (Blueprint $table) {
            $table->id();
            $table->string('platform', 20)->index();
            $table->string('handle', 120);
            $table->string('display_name', 160)->nullable();
            $table->string('external_id', 191)->nullable();
            $table->string('avatar_url', 1000)->nullable();
            $table->string('profile_url', 1000)->nullable();
            $table->string('status', 30)->default('disconnected')->index();
            $table->string('connection_type', 20)->default('manual'); // oauth | api_key | manual | demo
            // Takipçi ve gönderi sayısı: API izni yoksa NULL kalır, asla 0 yazılmaz.
            $table->unsignedBigInteger('followers_count')->nullable();
            $table->unsignedBigInteger('posts_count')->nullable();
            $table->json('granted_scopes')->nullable();
            $table->timestamp('last_synced_at')->nullable();
            $table->text('last_error')->nullable();
            $table->boolean('is_demo')->default(false)->index();
            $table->timestamps();

            $table->unique(['platform', 'external_id']);
            $table->index(['platform', 'handle']);
        });

        Schema::create('account_credentials', function (Blueprint $table) {
            $table->id();
            $table->foreignId('social_account_id')->unique()->constrained()->cascadeOnDelete();
            // Tokenlar Laravel "encrypted" cast ile APP_KEY kullanılarak şifrelenir.
            $table->text('access_token')->nullable();
            $table->text('refresh_token')->nullable();
            $table->string('token_type', 30)->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('refresh_expires_at')->nullable();
            $table->json('scopes')->nullable();
            $table->text('meta')->nullable();
            $table->timestamps();
        });

        Schema::create('project_social_accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->foreignId('social_account_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['project_id', 'social_account_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('project_social_accounts');
        Schema::dropIfExists('account_credentials');
        Schema::dropIfExists('social_accounts');
    }
};

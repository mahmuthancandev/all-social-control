<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // İzni olmayan metrikler NULL saklanır; 0 ile karıştırılmaz.
        Schema::create('account_metric_snapshots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('social_account_id')->constrained()->cascadeOnDelete();
            $table->date('captured_on');
            $table->unsignedBigInteger('followers')->nullable();
            $table->unsignedBigInteger('following')->nullable();
            $table->unsignedBigInteger('posts_count')->nullable();
            $table->unsignedBigInteger('views')->nullable();
            $table->unsignedBigInteger('reach')->nullable();
            $table->unsignedBigInteger('impressions')->nullable();
            $table->unsignedBigInteger('likes')->nullable();
            $table->unsignedBigInteger('comments')->nullable();
            $table->unsignedBigInteger('shares')->nullable();
            $table->string('source', 20)->default('api'); // api | import | manual | demo
            $table->boolean('is_demo')->default(false)->index();
            $table->timestamps();

            $table->unique(['social_account_id', 'captured_on']);
            $table->index('captured_on');
        });

        // Platformdaki gönderiler (panelden ya da dışarıdan paylaşılmış olabilir).
        Schema::create('external_posts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('social_account_id')->constrained()->cascadeOnDelete();
            $table->foreignId('post_platform_target_id')->nullable()->constrained()->nullOnDelete();
            $table->string('external_id', 191);
            $table->string('content_type', 30)->nullable();
            $table->string('title', 500)->nullable();
            $table->string('url', 1000)->nullable();
            $table->string('thumbnail_url', 1000)->nullable();
            $table->timestamp('published_at')->nullable()->index();
            $table->unsignedBigInteger('views')->nullable();
            $table->unsignedBigInteger('likes')->nullable();
            $table->unsignedBigInteger('comments')->nullable();
            $table->unsignedBigInteger('shares')->nullable();
            $table->string('source', 20)->default('api');
            $table->boolean('is_demo')->default(false)->index();
            $table->timestamps();

            $table->unique(['social_account_id', 'external_id']);
        });

        Schema::create('post_metric_snapshots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('external_post_id')->constrained()->cascadeOnDelete();
            $table->date('captured_on');
            $table->unsignedBigInteger('views')->nullable();
            $table->unsignedBigInteger('reach')->nullable();
            $table->unsignedBigInteger('impressions')->nullable();
            $table->unsignedBigInteger('likes')->nullable();
            $table->unsignedBigInteger('comments')->nullable();
            $table->unsignedBigInteger('shares')->nullable();
            $table->unsignedBigInteger('saves')->nullable();
            $table->unsignedBigInteger('watch_time_seconds')->nullable();
            $table->string('source', 20)->default('api');
            $table->boolean('is_demo')->default(false)->index();
            $table->timestamps();

            $table->unique(['external_post_id', 'captured_on']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('post_metric_snapshots');
        Schema::dropIfExists('external_posts');
        Schema::dropIfExists('account_metric_snapshots');
    }
};

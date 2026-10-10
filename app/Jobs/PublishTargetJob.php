<?php

namespace App\Jobs;

use App\Services\Publishing\PublishingService;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

class PublishTargetJob implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 3600;

    public int $uniqueFor = 3600;

    public function __construct(public int $targetId, public ?int $userId = null) {}

    /**
     * Artan bekleme: 1 dk, 5 dk, 15 dk.
     *
     * @return array<int, int>
     */
    public function backoff(): array
    {
        return [60, 300, 900];
    }

    public function uniqueId(): string
    {
        return 'publish-target-'.$this->targetId;
    }

    public function handle(PublishingService $publishing): void
    {
        $publishing->execute($this->targetId, $this->attempts(), $this->userId, $this->attempts() >= $this->tries);
    }

    public function failed(?Throwable $e): void
    {
        app(PublishingService::class)->markFailed($this->targetId, $e?->getMessage() ?? 'Bilinmeyen hata');
    }
}

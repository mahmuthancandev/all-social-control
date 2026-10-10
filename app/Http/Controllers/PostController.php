<?php

namespace App\Http\Controllers;

use App\Enums\ContentType;
use App\Enums\Platform;
use App\Enums\PostStatus;
use App\Enums\TargetStatus;
use App\Models\MediaFile;
use App\Models\Post;
use App\Models\PostPlatformTarget;
use App\Models\Project;
use App\Models\SocialAccount;
use App\Services\Platforms\PlatformManager;
use App\Services\Publishing\PublishingService;
use App\Services\Publishing\TargetValidator;
use App\Support\Audit;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

class PostController extends Controller
{
    public function __construct(
        private PlatformManager $platforms,
        private PublishingService $publishing,
    ) {}

    public function index(Request $request): Response
    {
        $filters = $request->only(['search', 'status', 'project', 'platform']);

        $posts = Post::query()
            ->with(['project:id,name,color', 'targets.socialAccount:id,platform,handle'])
            ->when($filters['search'] ?? null, fn ($q, $s) => $q->where(fn ($q) => $q->where('title', 'like', "%{$s}%")->orWhere('body', 'like', "%{$s}%")))
            ->when($filters['status'] ?? null, fn ($q, $s) => $q->where('status', $s))
            ->when($filters['project'] ?? null, fn ($q, $p) => $q->where('project_id', $p))
            ->when($filters['platform'] ?? null, fn ($q, $p) => $q->whereHas('targets', fn ($t) => $t->where('platform', $p)))
            ->latest('updated_at')
            ->paginate(15)
            ->withQueryString()
            ->through(fn (Post $post) => $this->summary($post));

        return Inertia::render('posts/Index', [
            'posts' => $posts,
            'filters' => $filters,
            'statuses' => PostStatus::options(),
            'projects' => Project::orderBy('name')->get(['id', 'name']),
            'platforms' => Platform::options(),
        ]);
    }

    public function create(Request $request): Response
    {
        return Inertia::render('posts/Composer', $this->composerData() + [
            'post' => null,
            'defaults' => [
                'project_id' => $request->integer('project') ?: null,
                'media_ids' => array_filter([(int) $request->query('media')]),
                'scheduled_at' => $request->query('date'),
            ],
        ]);
    }

    public function edit(Post $post): Response
    {
        $post->load(['targets', 'media']);

        return Inertia::render('posts/Composer', $this->composerData() + [
            'post' => [
                'id' => $post->id,
                'project_id' => $post->project_id,
                'title' => $post->title,
                'body' => $post->body,
                'hashtags' => $post->hashtags ?? [],
                'media_ids' => $post->media->pluck('id'),
                'scheduled_at' => $post->scheduled_at?->toIso8601String(),
                'is_demo' => $post->is_demo,
                'targets' => $post->targets->map(fn (PostPlatformTarget $t) => [
                    'id' => $t->id,
                    'social_account_id' => $t->social_account_id,
                    'content_type' => $t->content_type->value,
                    'title' => $t->title,
                    'caption' => $t->caption,
                    'hashtags' => $t->hashtags ?? [],
                    'media_file_id' => $t->media_file_id,
                    'thumbnail_media_id' => $t->thumbnail_media_id,
                    'settings' => $t->settings ?? (object) [],
                    'status' => $t->status->value,
                    'locked' => $t->status->isPublished() || in_array($t->status, [TargetStatus::Queued, TargetStatus::Publishing], true),
                ]),
            ],
            'defaults' => null,
        ]);
    }

    public function store(Request $request, TargetValidator $validator): RedirectResponse
    {
        $data = $this->validatePayload($request);

        $post = DB::transaction(function () use ($data, $request, $validator) {
            $post = Post::create([
                'project_id' => $data['project_id'] ?? null,
                'created_by' => $request->user()->id,
                'title' => $data['title'] ?? null,
                'body' => $data['body'] ?? null,
                'hashtags' => $data['hashtags'] ?? [],
                'scheduled_at' => $data['scheduled_at'] ?? null,
                'status' => PostStatus::Draft,
            ]);
            $this->syncMedia($post, $data['media_ids'] ?? []);
            $this->syncTargets($post, $data['targets'], $validator);

            return $post;
        });

        Audit::log('post.created', $post, ['targets' => count($data['targets']), 'action' => $data['action']]);

        return $this->applyAction($post->fresh(), $data, $request);
    }

    public function update(Request $request, Post $post, TargetValidator $validator): RedirectResponse
    {
        $data = $this->validatePayload($request);

        DB::transaction(function () use ($post, $data, $validator) {
            $post->update([
                'project_id' => $data['project_id'] ?? null,
                'title' => $data['title'] ?? null,
                'body' => $data['body'] ?? null,
                'hashtags' => $data['hashtags'] ?? [],
                'scheduled_at' => $data['scheduled_at'] ?? null,
            ]);
            $this->syncMedia($post, $data['media_ids'] ?? []);
            $this->syncTargets($post, $data['targets'], $validator);
        });

        Audit::log('post.updated', $post, ['action' => $data['action']]);

        return $this->applyAction($post->fresh(), $data, $request);
    }

    public function show(Post $post): Response
    {
        $post->load(['project:id,name,color', 'media', 'creator:id,name', 'targets.socialAccount', 'targets.mediaFile', 'targets.thumbnail', 'targets.jobs.user:id,name']);

        return Inertia::render('posts/Show', [
            'post' => $this->summary($post) + [
                'body' => $post->body,
                'hashtags' => $post->hashtags ?? [],
                'creator' => $post->creator?->name,
                'media' => $post->media->map->toSummary(),
            ],
            'targets' => $post->targets->map(function (PostPlatformTarget $t) {
                $caps = $this->platforms->capabilities($t->socialAccount);

                return [
                    'id' => $t->id,
                    'platform' => $t->platform->value,
                    'platform_label' => $t->platform->label(),
                    'platform_color' => $t->platform->color(),
                    'content_type' => $t->content_type->value,
                    'content_type_label' => $t->content_type->label(),
                    'account' => $t->socialAccount->toSummary(),
                    'title' => $t->title,
                    'caption' => $t->caption,
                    'hashtags' => $t->hashtags ?? [],
                    'full_caption' => $t->fullCaption(),
                    'status' => $t->status->value,
                    'status_label' => $t->status->label(),
                    'publish_mode' => $t->publish_mode,
                    'can_auto_publish' => $caps->canPublish($t->content_type),
                    'manual_reason' => $caps->canPublish($t->content_type) ? null : $caps->publishReason($t->content_type),
                    'auto_note' => $caps->canPublish($t->content_type) ? $caps->publishReason($t->content_type) : null,
                    'share_url' => $t->platform->manualShareUrl($t->platform === Platform::X ? $t->fullCaption() : null),
                    'scheduled_at' => $t->scheduled_at?->toIso8601String(),
                    'published_at' => $t->published_at?->toIso8601String(),
                    'external_url' => $t->external_url,
                    'error_message' => $t->error_message,
                    'attempts' => $t->attempts,
                    'media' => $t->mediaFile?->toSummary(),
                    'thumbnail' => $t->thumbnail?->toSummary(),
                    'settings' => $t->settings ?? (object) [],
                    'is_demo' => $t->is_demo,
                    'jobs' => $t->jobs->map(fn ($j) => [
                        'id' => $j->id,
                        'mode' => $j->mode,
                        'status' => $j->status,
                        'attempt' => $j->attempt,
                        'message' => $j->message,
                        'user' => $j->user?->name,
                        'started_at' => $j->started_at?->toIso8601String(),
                        'finished_at' => $j->finished_at?->toIso8601String(),
                    ]),
                ];
            }),
        ]);
    }

    public function destroy(Post $post): RedirectResponse
    {
        if ($post->targets()->whereIn('status', [TargetStatus::Queued->value, TargetStatus::Publishing->value])->exists()) {
            return back()->with('error', 'Yayınlanmakta olan bir gönderi silinemez.');
        }

        Audit::log('post.deleted', $post, ['title' => $post->title]);
        $post->delete();

        return redirect()->route('posts.index')->with('success', 'Gönderi silindi. (Platformlarda yayınlanmış içerikler etkilenmez.)');
    }

    public function duplicate(Post $post): RedirectResponse
    {
        $copy = DB::transaction(function () use ($post) {
            $copy = $post->replicate(['status', 'scheduled_at']);
            $copy->fill(['title' => trim(($post->title ?? '').' (kopya)'), 'status' => PostStatus::Draft, 'is_demo' => false, 'created_by' => request()->user()->id])->save();
            $copy->media()->sync($post->media()->pluck('media_files.id')->mapWithKeys(fn ($id, $i) => [$id => ['position' => $i]])->all());
            foreach ($post->targets as $t) {
                $copy->targets()->create($t->only(['social_account_id', 'platform', 'content_type', 'title', 'caption', 'hashtags', 'media_file_id', 'thumbnail_media_id', 'settings']) + [
                    'status' => TargetStatus::Draft,
                    'publish_mode' => 'manual',
                    'is_demo' => $t->is_demo,
                ]);
            }

            return $copy;
        });

        return redirect()->route('posts.edit', $copy)->with('success', 'Gönderi kopyalandı.');
    }

    /**
     * @return array<string, mixed>
     */
    private function composerData(): array
    {
        $accounts = SocialAccount::with('projects:id')->orderBy('platform')->orderBy('handle')->get()->map(function (SocialAccount $a) {
            $caps = $this->platforms->capabilities($a);

            return $a->toSummary() + [
                'project_ids' => $a->projects->pluck('id'),
                'content_types' => array_map(fn (ContentType $t) => [
                    'value' => $t->value,
                    'auto' => $caps->canPublish($t),
                    'reason' => $caps->publishReason($t),
                ], $a->platform->contentTypes()),
            ];
        });

        return [
            'projects' => Project::orderBy('name')->get(['id', 'name', 'color']),
            'accounts' => $accounts,
            'contentTypes' => ContentType::options(),
            'media' => MediaFile::latest()->limit(200)->get()->map->toSummary(),
            'timezone' => config('app.timezone'),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function validatePayload(Request $request): array
    {
        return $request->validate([
            'project_id' => ['nullable', 'integer', 'exists:projects,id'],
            'title' => ['nullable', 'string', 'max:255'],
            'body' => ['nullable', 'string', 'max:10000'],
            'hashtags' => ['nullable', 'array', 'max:60'],
            'hashtags.*' => ['string', 'max:100', 'regex:/^#?[\p{L}\p{N}_]+$/u'],
            'media_ids' => ['nullable', 'array', 'max:20'],
            'media_ids.*' => ['integer', 'exists:media_files,id'],
            'scheduled_at' => ['nullable', 'date', Rule::requiredIf($request->input('action') === 'schedule'), $request->input('action') === 'schedule' ? 'after:now' : 'nullable'],
            'action' => ['required', Rule::in(['draft', 'schedule', 'publish'])],
            'targets' => ['required', 'array', 'min:1', 'max:20'],
            'targets.*.social_account_id' => ['required', 'integer', 'exists:social_accounts,id', 'distinct:strict'],
            'targets.*.content_type' => ['required', Rule::enum(ContentType::class)],
            'targets.*.title' => ['nullable', 'string', 'max:255'],
            'targets.*.caption' => ['nullable', 'string', 'max:10000'],
            'targets.*.hashtags' => ['nullable', 'array', 'max:60'],
            'targets.*.hashtags.*' => ['string', 'max:100', 'regex:/^#?[\p{L}\p{N}_]+$/u'],
            'targets.*.media_file_id' => ['nullable', 'integer', 'exists:media_files,id'],
            'targets.*.thumbnail_media_id' => ['nullable', 'integer', 'exists:media_files,id'],
            'targets.*.settings' => ['nullable', 'array'],
            'targets.*.settings.privacy' => ['nullable', Rule::in(['public', 'unlisted', 'private', 'SELF_ONLY', 'PUBLIC_TO_EVERYONE', 'MUTUAL_FOLLOW_FRIENDS', 'FOLLOWER_OF_CREATOR'])],
            'targets.*.settings.made_for_kids' => ['nullable', 'boolean'],
        ], [
            'targets.required' => 'En az bir hesap seçin.',
            'targets.*.social_account_id.distinct' => 'Aynı hesap birden fazla kez seçilemez.',
            'hashtags.*.regex' => 'Hashtagler yalnızca harf, rakam ve alt çizgi içerebilir.',
            'targets.*.hashtags.*.regex' => 'Hashtagler yalnızca harf, rakam ve alt çizgi içerebilir.',
            'scheduled_at.after' => 'Planlama zamanı gelecekte olmalı.',
            'scheduled_at.required' => 'Planlama için tarih ve saat seçin.',
        ], ['scheduled_at' => 'yayın zamanı', 'targets' => 'hedef hesaplar']);
    }

    /**
     * @param  array<int, int>  $mediaIds
     */
    private function syncMedia(Post $post, array $mediaIds): void
    {
        $post->media()->sync(collect($mediaIds)->values()->mapWithKeys(fn ($id, $i) => [$id => ['position' => $i]])->all());
    }

    /**
     * @param  array<int, array<string, mixed>>  $targets
     */
    private function syncTargets(Post $post, array $targets, TargetValidator $validator): void
    {
        $accounts = SocialAccount::whereIn('id', collect($targets)->pluck('social_account_id'))->get()->keyBy('id');
        $media = MediaFile::whereIn('id', collect($targets)->flatMap(fn ($t) => [$t['media_file_id'] ?? null, $t['thumbnail_media_id'] ?? null])->filter())->get()->keyBy('id');

        $errors = [];
        foreach ($targets as $i => $t) {
            $account = $accounts[$t['social_account_id']];
            foreach ($validator->validate($t, $account, $media[$t['media_file_id'] ?? 0] ?? null, $media[$t['thumbnail_media_id'] ?? 0] ?? null) as $field => $message) {
                $errors["targets.{$i}.{$field}"] = '@'.$account->handle.': '.$message;
            }
        }
        if ($errors) {
            throw ValidationException::withMessages($errors);
        }

        $existing = $post->targets()->get();
        $keep = [];

        foreach ($targets as $t) {
            $account = $accounts[$t['social_account_id']];
            $current = $existing->first(fn ($e) => $e->social_account_id === $account->id);

            $attributes = [
                'platform' => $account->platform,
                'content_type' => $t['content_type'],
                'title' => $t['title'] ?? null,
                'caption' => $t['caption'] ?? null,
                'hashtags' => array_values(array_map(fn ($h) => ltrim($h, '#'), $t['hashtags'] ?? [])),
                'media_file_id' => $t['media_file_id'] ?? null,
                'thumbnail_media_id' => $t['thumbnail_media_id'] ?? null,
                'settings' => $t['settings'] ?? null,
                'is_demo' => $account->is_demo,
            ];

            if ($current) {
                $keep[] = $current->id;
                $locked = $current->status->isPublished() || in_array($current->status, [TargetStatus::Queued, TargetStatus::Publishing], true);
                if (! $locked) {
                    $current->fill($attributes)->save();
                }
            } else {
                $keep[] = $post->targets()->create($attributes + [
                    'social_account_id' => $account->id,
                    'status' => TargetStatus::Draft,
                    'publish_mode' => 'manual',
                ])->id;
            }
        }

        // Listeden çıkarılan ve henüz yayınlanmamış hedefleri sil.
        $existing->whereNotIn('id', $keep)->each(function (PostPlatformTarget $t) {
            if (! $t->status->isPublished() && ! in_array($t->status, [TargetStatus::Queued, TargetStatus::Publishing], true)) {
                $t->delete();
            }
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function applyAction(Post $post, array $data, Request $request): RedirectResponse
    {
        $targets = $post->targets()->with(['socialAccount', 'post'])->get()
            ->filter(fn ($t) => ! $t->status->isPublished() && ! in_array($t->status, [TargetStatus::Queued, TargetStatus::Publishing, TargetStatus::Cancelled], true));

        $messages = [];
        try {
            if ($data['action'] === 'schedule') {
                $at = CarbonImmutable::parse($data['scheduled_at'], config('app.timezone'));
                $targets->each(fn ($t) => $this->publishing->schedule($t, $at));
                $messages[] = $targets->count().' hedef '.$at->format('d.m.Y H:i').' için planlandı.';
                $manual = $targets->filter(fn ($t) => $t->fresh()->publish_mode === 'manual')->count();
                if ($manual) {
                    $messages[] = "{$manual} hedef otomatik yayınlanamaz; zamanı gelince Paylaşım Asistanı için bildirim alacaksınız.";
                }
            } elseif ($data['action'] === 'publish') {
                $api = 0;
                $manual = 0;
                foreach ($targets as $t) {
                    $this->publishing->publishNow($t, $request->user()) === 'api' ? $api++ : $manual++;
                }
                if ($api) {
                    $messages[] = "{$api} hedef yayın kuyruğuna alındı.";
                }
                if ($manual) {
                    $messages[] = "{$manual} hedef için manuel paylaşım gerekiyor (Paylaşım Asistanı).";
                }
            } else {
                $targets->each(fn ($t) => $this->publishing->revertToDraft($t));
                $messages[] = 'Taslak kaydedildi.';
            }
        } catch (Throwable $e) {
            return redirect()->route('posts.show', $post)->with('error', $e->getMessage());
        }

        $post->refreshStatus();

        return redirect()->route($data['action'] === 'draft' ? 'posts.edit' : 'posts.show', $post)->with('success', implode(' ', $messages));
    }

    /**
     * @return array<string, mixed>
     */
    private function summary(Post $post): array
    {
        return [
            'id' => $post->id,
            'title' => $post->title,
            'excerpt' => mb_substr((string) $post->body, 0, 140),
            'status' => $post->status->value,
            'status_label' => $post->status->label(),
            'scheduled_at' => $post->scheduled_at?->toIso8601String(),
            'updated_at' => $post->updated_at?->toIso8601String(),
            'is_demo' => $post->is_demo,
            'project' => $post->project ? ['id' => $post->project->id, 'name' => $post->project->name, 'color' => $post->project->color] : null,
            'targets' => $post->targets->map(fn (PostPlatformTarget $t) => [
                'id' => $t->id,
                'platform' => $t->platform->value,
                'handle' => $t->socialAccount?->handle,
                'status' => $t->status->value,
                'status_label' => $t->status->label(),
                'content_type_label' => $t->content_type->label(),
            ]),
        ];
    }
}

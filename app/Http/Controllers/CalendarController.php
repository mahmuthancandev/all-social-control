<?php

namespace App\Http\Controllers;

use App\Enums\Platform;
use App\Enums\TargetStatus;
use App\Models\Post;
use App\Models\PostPlatformTarget;
use App\Models\Project;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

class CalendarController extends Controller
{
    public function index(Request $request): Response
    {
        $tz = config('app.timezone');
        $view = in_array($request->query('view'), ['month', 'week', 'day'], true) ? $request->query('view') : 'month';
        try {
            $date = CarbonImmutable::parse((string) $request->query('date', 'today'), $tz);
        } catch (Throwable) {
            $date = CarbonImmutable::today($tz);
        }

        [$from, $to] = match ($view) {
            'day' => [$date->startOfDay(), $date->endOfDay()],
            'week' => [$date->startOfWeek(), $date->endOfWeek()],
            default => [$date->startOfMonth()->startOfWeek(), $date->endOfMonth()->endOfWeek()],
        };

        $filters = $request->only(['project', 'platform', 'status']);

        $events = PostPlatformTarget::with(['post.project:id,name,color', 'socialAccount:id,platform,handle'])
            ->when($filters['project'] ?? null, fn ($q, $p) => $q->whereHas('post', fn ($q) => $q->where('project_id', $p)))
            ->when($filters['platform'] ?? null, fn ($q, $p) => $q->where('platform', $p))
            ->when($filters['status'] ?? null, fn ($q, $s) => $q->where('status', $s))
            ->where(function ($q) use ($from, $to) {
                $q->whereBetween('scheduled_at', [$from, $to])->orWhereBetween('published_at', [$from, $to]);
            })
            ->get()
            ->map(function (PostPlatformTarget $t) {
                $at = $t->published_at ?? $t->scheduled_at;

                return [
                    'id' => $t->id,
                    'post_id' => $t->post_id,
                    'title' => $t->title ?: $t->post->title ?: mb_substr((string) $t->caption, 0, 60),
                    'at' => $at?->toIso8601String(),
                    'platform' => $t->platform->value,
                    'platform_label' => $t->platform->label(),
                    'handle' => $t->socialAccount->handle,
                    'content_type_label' => $t->content_type->label(),
                    'status' => $t->status->value,
                    'status_label' => $t->status->label(),
                    'publish_mode' => $t->publish_mode,
                    'project' => $t->post->project ? ['name' => $t->post->project->name, 'color' => $t->post->project->color] : null,
                    'movable' => in_array($t->status, [TargetStatus::Scheduled, TargetStatus::Draft, TargetStatus::Failed, TargetStatus::ManualRequired], true),
                    'is_demo' => $t->is_demo,
                ];
            })
            ->sortBy('at')
            ->values();

        $drafts = Post::with('project:id,name,color')
            ->where('status', 'draft')
            ->when($filters['project'] ?? null, fn ($q, $p) => $q->where('project_id', $p))
            ->latest('updated_at')->limit(15)->get()
            ->map(fn (Post $p) => ['id' => $p->id, 'title' => $p->title ?: 'Başlıksız taslak', 'project' => $p->project?->name, 'is_demo' => $p->is_demo]);

        return Inertia::render('calendar/Index', [
            'view' => $view,
            'date' => $date->toDateString(),
            'from' => $from->toDateString(),
            'to' => $to->toDateString(),
            'events' => $events,
            'drafts' => $drafts,
            'filters' => $filters,
            'projects' => Project::orderBy('name')->get(['id', 'name', 'color']),
            'platforms' => Platform::options(),
            'statuses' => TargetStatus::options(),
            'timezone' => $tz,
        ]);
    }
}

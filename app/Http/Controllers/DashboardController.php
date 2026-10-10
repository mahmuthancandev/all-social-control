<?php

namespace App\Http\Controllers;

use App\Enums\TargetStatus;
use App\Models\AppNotification;
use App\Models\PostPlatformTarget;
use App\Models\Project;
use App\Models\SocialAccount;
use App\Models\SyncLog;
use App\Services\Analytics\AnalyticsService;
use App\Support\AccountScope;
use App\Support\Range;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __invoke(Request $request, AnalyticsService $analytics): Response
    {
        $days = Range::days($request);
        $accounts = AccountScope::query($request)->with('projects:id,name')->get();

        $targetSummary = fn (PostPlatformTarget $t) => [
            'id' => $t->id,
            'post_id' => $t->post_id,
            'title' => $t->title ?: $t->post?->title ?: mb_substr((string) $t->caption, 0, 80),
            'platform' => $t->platform->value,
            'platform_label' => $t->platform->label(),
            'content_type_label' => $t->content_type->label(),
            'account' => '@'.$t->socialAccount->handle,
            'status' => $t->status->value,
            'status_label' => $t->status->label(),
            'publish_mode' => $t->publish_mode,
            'scheduled_at' => $t->scheduled_at?->toIso8601String(),
            'error_message' => $t->error_message,
            'is_demo' => $t->is_demo,
        ];

        $accountIds = $accounts->pluck('id');

        return Inertia::render('Dashboard', [
            'range' => $days,
            'filters' => ['project' => $request->query('project'), 'platform' => $request->query('platform'), 'demo' => $request->query('demo', '1')],
            'projects' => Project::orderBy('name')->get(['id', 'name', 'is_demo']),
            'hasDemo' => SocialAccount::where('is_demo', true)->exists(),
            'hasReal' => SocialAccount::where('is_demo', false)->exists(),
            'accounts' => $accounts->map(fn ($a) => $a->toSummary()),
            'kpis' => $analytics->kpis($accounts, $days),
            'followersSeries' => $analytics->timeSeries($accounts, 'followers', $days),
            'viewsSeries' => $analytics->timeSeries($accounts, 'views', $days),
            'engagementSeries' => $analytics->timeSeries($accounts, 'likes', $days, 'total'),
            'platforms' => $analytics->platformComparison($accounts, $days),
            'topVideos' => $analytics->topPosts($accounts, $days, 5, 'views', 'video'),
            'topPosts' => $analytics->topPosts($accounts, $days, 5, 'engagement'),
            'upcoming' => PostPlatformTarget::with(['post', 'socialAccount'])
                ->whereIn('social_account_id', $accountIds)
                ->where('status', TargetStatus::Scheduled->value)
                ->where('scheduled_at', '>=', now())
                ->orderBy('scheduled_at')->limit(6)->get()->map($targetSummary),
            'attention' => PostPlatformTarget::with(['post', 'socialAccount'])
                ->whereIn('social_account_id', $accountIds)
                ->whereIn('status', [TargetStatus::Failed->value, TargetStatus::ManualRequired->value])
                ->latest('updated_at')->limit(6)->get()->map($targetSummary),
            'notifications' => AppNotification::where('user_id', $request->user()->id)
                ->latest()->limit(5)->get()->map->toSummary(),
            'lastSync' => SyncLog::whereIn('social_account_id', $accountIds)->latest('finished_at')->value('finished_at'),
            'schedulerLastRun' => Cache::get('socialhub:scheduler:last_run'),
        ]);
    }
}

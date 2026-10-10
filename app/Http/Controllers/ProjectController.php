<?php

namespace App\Http\Controllers;

use App\Enums\TargetStatus;
use App\Models\ExternalPost;
use App\Models\PostPlatformTarget;
use App\Models\Project;
use App\Models\SocialAccount;
use App\Services\Analytics\AnalyticsService;
use App\Support\Audit;
use App\Support\Range;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class ProjectController extends Controller
{
    public function index(Request $request, AnalyticsService $analytics): Response
    {
        $projects = Project::with('socialAccounts')->withCount('posts')->orderBy('name')->get()
            ->map(function (Project $project) use ($analytics) {
                $kpis = $analytics->kpis($project->socialAccounts, 30);

                return $project->toSummary() + [
                    'description' => $project->description,
                    'posts_count' => $project->posts_count,
                    'accounts' => $project->socialAccounts->map(fn ($a) => $a->toSummary()),
                    'followers' => $kpis['followers'],
                    'followers_change' => $kpis['followers_change_range'],
                    'views' => $kpis['views'],
                    'engagement_rate' => $kpis['engagement_rate'],
                ];
            });

        return Inertia::render('projects/Index', [
            'projects' => $projects,
            'accounts' => SocialAccount::orderBy('platform')->get()->map->toSummary(),
            'types' => Project::TYPES,
        ]);
    }

    public function show(Request $request, Project $project, AnalyticsService $analytics): Response
    {
        $days = Range::days($request);
        $accounts = $project->socialAccounts()->get();
        $ids = $accounts->pluck('id');

        return Inertia::render('projects/Show', [
            'project' => $project->toSummary() + ['description' => $project->description],
            'range' => $days,
            'accounts' => $accounts->map->toSummary(),
            'allAccounts' => SocialAccount::orderBy('platform')->get()->map->toSummary(),
            'types' => Project::TYPES,
            'kpis' => $analytics->kpis($accounts, $days),
            'followersSeries' => $analytics->timeSeries($accounts, 'followers', $days, 'account'),
            'viewsSeries' => $analytics->timeSeries($accounts, 'views', $days),
            'platforms' => $analytics->platformComparison($accounts, $days),
            'topPosts' => $analytics->topPosts($accounts, $days, 8),
            'publishedCount' => ExternalPost::whereIn('social_account_id', $ids)->where('published_at', '>=', now()->subDays($days))->count(),
            'targets' => PostPlatformTarget::with(['post', 'socialAccount'])
                ->whereHas('post', fn ($q) => $q->where('project_id', $project->id))
                ->whereIn('status', [TargetStatus::Scheduled->value, TargetStatus::ManualRequired->value, TargetStatus::Failed->value, TargetStatus::Draft->value])
                ->orderByRaw('scheduled_at is null, scheduled_at asc')
                ->limit(12)->get()
                ->map(fn ($t) => [
                    'id' => $t->id,
                    'post_id' => $t->post_id,
                    'title' => $t->title ?: $t->post->title,
                    'platform' => $t->platform->value,
                    'platform_label' => $t->platform->label(),
                    'account' => '@'.$t->socialAccount->handle,
                    'status' => $t->status->value,
                    'status_label' => $t->status->label(),
                    'scheduled_at' => $t->scheduled_at?->toIso8601String(),
                    'is_demo' => $t->is_demo,
                ]),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);
        $project = Project::create(collect($data)->except(['logo', 'account_ids'])->all());
        $this->saveLogo($request, $project);
        $project->socialAccounts()->sync($data['account_ids'] ?? []);

        Audit::log('project.created', $project, ['name' => $project->name]);

        return redirect()->route('projects.show', $project)->with('success', 'Proje oluşturuldu.');
    }

    public function update(Request $request, Project $project): RedirectResponse
    {
        $data = $this->validated($request, $project);
        $project->update(collect($data)->except(['logo', 'account_ids', 'remove_logo'])->all());
        if ($request->boolean('remove_logo') && $project->logo_path) {
            Storage::disk('public')->delete($project->logo_path);
            $project->update(['logo_path' => null]);
        }
        $this->saveLogo($request, $project);
        if (array_key_exists('account_ids', $data)) {
            $project->socialAccounts()->sync($data['account_ids'] ?? []);
        }

        Audit::log('project.updated', $project);

        return back()->with('success', 'Proje güncellendi.');
    }

    public function destroy(Project $project): RedirectResponse
    {
        if ($project->logo_path) {
            Storage::disk('public')->delete($project->logo_path);
        }
        Audit::log('project.deleted', $project, ['name' => $project->name]);
        $project->delete();

        return redirect()->route('projects.index')->with('success', 'Proje silindi. Bağlı hesaplar ve medya korunur.');
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request, ?Project $project = null): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'type' => ['required', Rule::in(array_keys(Project::TYPES))],
            'category' => ['nullable', 'string', 'max:80'],
            'description' => ['nullable', 'string', 'max:2000'],
            'color' => ['required', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'logo' => ['nullable', 'image', 'mimes:png,jpg,jpeg,webp', 'max:2048', 'dimensions:max_width=4000,max_height=4000'],
            'remove_logo' => ['boolean'],
            'account_ids' => ['nullable', 'array'],
            'account_ids.*' => ['integer', 'exists:social_accounts,id'],
        ], [], ['name' => 'proje adı', 'type' => 'tür', 'color' => 'renk', 'logo' => 'logo']);
    }

    private function saveLogo(Request $request, Project $project): void
    {
        if (! $request->hasFile('logo')) {
            return;
        }
        if ($project->logo_path) {
            Storage::disk('public')->delete($project->logo_path);
        }
        $path = $request->file('logo')->store('logos', 'public');
        $project->update(['logo_path' => $path]);
    }
}

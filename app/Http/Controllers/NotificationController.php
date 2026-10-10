<?php

namespace App\Http\Controllers;

use App\Models\AppNotification;
use App\Models\NotificationRule;
use App\Models\SocialAccount;
use App\Support\Audit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class NotificationController extends Controller
{
    public function index(Request $request): Response
    {
        $filter = $request->query('filter');

        $notifications = AppNotification::where('user_id', $request->user()->id)
            ->when($filter === 'unread', fn ($q) => $q->whereNull('read_at'))
            ->when(in_array($filter, ['error', 'warning', 'success', 'info'], true), fn ($q) => $q->where('level', $filter))
            ->latest()
            ->paginate(25)
            ->withQueryString()
            ->through(fn (AppNotification $n) => $n->toSummary());

        return Inertia::render('notifications/Index', [
            'notifications' => $notifications,
            'filter' => $filter,
            'rules' => NotificationRule::with('socialAccount')->latest()->get()->map(fn (NotificationRule $r) => [
                'id' => $r->id,
                'type' => $r->type,
                'type_label' => NotificationRule::TYPES[$r->type] ?? $r->type,
                'threshold' => $r->threshold,
                'minutes_before' => $r->minutes_before,
                'is_active' => $r->is_active,
                'account' => $r->socialAccount?->toSummary(),
                'last_triggered_at' => $r->last_triggered_at?->toIso8601String(),
            ]),
            'ruleTypes' => NotificationRule::TYPES,
            'accounts' => SocialAccount::orderBy('platform')->get()->map->toSummary(),
        ]);
    }

    public function open(Request $request, AppNotification $notification): RedirectResponse
    {
        abort_unless($notification->user_id === $request->user()->id, 403);
        $notification->update(['read_at' => $notification->read_at ?? now()]);

        $url = $notification->url;

        return $url && str_starts_with($url, '/') && ! str_starts_with($url, '//') ? redirect($url) : back();
    }

    public function toggleRead(Request $request, AppNotification $notification): RedirectResponse
    {
        abort_unless($notification->user_id === $request->user()->id, 403);
        $notification->update(['read_at' => $notification->read_at ? null : now()]);

        return back();
    }

    public function markAllRead(Request $request): RedirectResponse
    {
        AppNotification::where('user_id', $request->user()->id)->whereNull('read_at')->update(['read_at' => now()]);

        return back()->with('success', 'Tüm bildirimler okundu olarak işaretlendi.');
    }

    public function destroyRead(Request $request): RedirectResponse
    {
        $count = AppNotification::where('user_id', $request->user()->id)->whereNotNull('read_at')->delete();

        return back()->with('success', "{$count} okunmuş bildirim silindi.");
    }

    public function storeRule(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'type' => ['required', Rule::in(array_keys(NotificationRule::TYPES))],
            'social_account_id' => ['nullable', 'integer', 'exists:social_accounts,id', Rule::requiredIf($request->input('type') === 'followers_threshold')],
            'threshold' => ['nullable', 'integer', 'min:1', Rule::requiredIf(in_array($request->input('type'), ['views_threshold', 'followers_threshold'], true))],
            'minutes_before' => ['nullable', 'integer', 'min:5', 'max:1440', Rule::requiredIf($request->input('type') === 'schedule_reminder')],
        ], [], ['threshold' => 'eşik', 'minutes_before' => 'dakika', 'social_account_id' => 'hesap']);

        $rule = NotificationRule::create($data + ['is_active' => true, 'created_by' => $request->user()->id]);
        Audit::log('notification_rule.created', $rule);

        return back()->with('success', 'Bildirim kuralı eklendi.');
    }

    public function toggleRule(NotificationRule $rule): RedirectResponse
    {
        $rule->update(['is_active' => ! $rule->is_active]);

        return back();
    }

    public function destroyRule(NotificationRule $rule): RedirectResponse
    {
        $rule->delete();

        return back()->with('success', 'Kural silindi.');
    }
}

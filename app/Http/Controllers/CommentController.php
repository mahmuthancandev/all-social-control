<?php

namespace App\Http\Controllers;

use App\Enums\Capability;
use App\Enums\Platform;
use App\Models\Comment;
use App\Models\SocialAccount;
use App\Services\Platforms\PlatformManager;
use App\Services\Sync\AccountSyncService;
use App\Support\Audit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

class CommentController extends Controller
{
    public function __construct(private PlatformManager $platforms) {}

    public function index(Request $request): Response
    {
        $filters = $request->only(['account', 'platform', 'status', 'search', 'post']);

        $comments = Comment::with(['socialAccount', 'externalPost:id,title,url,external_id', 'replier:id,name'])
            ->when($filters['account'] ?? null, fn ($q, $a) => $q->where('social_account_id', $a))
            ->when($filters['platform'] ?? null, fn ($q, $p) => $q->whereHas('socialAccount', fn ($q) => $q->where('platform', $p)))
            ->when($filters['post'] ?? null, fn ($q, $p) => $q->where('external_post_id', $p))
            ->when(($filters['status'] ?? null) === 'unread', fn ($q) => $q->whereNull('read_at'))
            ->when(($filters['status'] ?? null) === 'unreplied', fn ($q) => $q->whereNull('replied_at'))
            ->when(($filters['status'] ?? null) === 'replied', fn ($q) => $q->whereNotNull('replied_at'))
            ->when($filters['search'] ?? null, fn ($q, $s) => $q->where(fn ($q) => $q->where('body', 'like', "%{$s}%")->orWhere('author_name', 'like', "%{$s}%")))
            ->latest('commented_at')
            ->paginate(20)
            ->withQueryString()
            ->through(function (Comment $c) {
                $caps = $this->platforms->capabilities($c->socialAccount);

                return [
                    'id' => $c->id,
                    'body' => $c->body,
                    'author_name' => $c->author_name,
                    'author_avatar_url' => $c->author_avatar_url,
                    'like_count' => $c->like_count,
                    'commented_at' => $c->commented_at?->toIso8601String(),
                    'read_at' => $c->read_at?->toIso8601String(),
                    'reply_body' => $c->reply_body,
                    'replied_at' => $c->replied_at?->toIso8601String(),
                    'replier' => $c->replier?->name,
                    'is_demo' => $c->is_demo,
                    'account' => $c->socialAccount->toSummary(),
                    'post' => $c->externalPost ? ['id' => $c->externalPost->id, 'title' => $c->externalPost->title, 'url' => $c->externalPost->url] : null,
                    'can_reply' => $caps->can(Capability::ReplyComments),
                    'reply_reason' => $caps->reason(Capability::ReplyComments),
                ];
            });

        // Yorum API'si olmayan hesaplar için resmi yorum sayfası bağlantıları.
        $unsupported = SocialAccount::orderBy('platform')->get()
            ->filter(fn ($a) => ! $this->platforms->capabilities($a)->can(Capability::ReadComments))
            ->map(fn ($a) => $a->toSummary() + [
                'reason' => $this->platforms->capabilities($a)->reason(Capability::ReadComments),
                'comments_url' => match ($a->platform) {
                    Platform::YouTube => $a->external_id && ! $a->is_demo ? 'https://studio.youtube.com/channel/'.$a->external_id.'/comments/inbox' : 'https://studio.youtube.com/',
                    Platform::Instagram => 'https://www.instagram.com/',
                    Platform::TikTok => 'https://www.tiktok.com/tiktokstudio/comment',
                    Platform::X => 'https://x.com/notifications/mentions',
                },
            ])->values();

        return Inertia::render('comments/Index', [
            'comments' => $comments,
            'filters' => $filters,
            'accounts' => SocialAccount::orderBy('platform')->get()->map->toSummary(),
            'platforms' => Platform::options(),
            'unreadCount' => Comment::whereNull('read_at')->count(),
            'unsupported' => $unsupported,
        ]);
    }

    public function markRead(Comment $comment): RedirectResponse
    {
        $comment->update(['read_at' => $comment->read_at ? null : now()]);

        return back();
    }

    public function markAllRead(Request $request): RedirectResponse
    {
        $count = Comment::whereNull('read_at')
            ->when($request->integer('account'), fn ($q, $a) => $q->where('social_account_id', $a))
            ->update(['read_at' => now()]);

        return back()->with('success', "{$count} yorum okundu olarak işaretlendi.");
    }

    public function reply(Request $request, Comment $comment): RedirectResponse
    {
        $data = $request->validate(['body' => ['required', 'string', 'min:1', 'max:1000']], [], ['body' => 'cevap']);

        if ($comment->replied_at) {
            return back()->with('error', 'Bu yoruma zaten cevap verildi.');
        }

        // Toplu/spam cevap engeli: kullanıcı başına dakikada en fazla 10 cevap.
        $key = 'comment-reply:'.$request->user()->id;
        if (RateLimiter::tooManyAttempts($key, 10)) {
            return back()->with('error', 'Çok hızlı cevap gönderiyorsunuz. Platform kurallarına uymak için lütfen biraz bekleyin.');
        }
        RateLimiter::hit($key, 60);

        $account = $comment->socialAccount()->with('credential')->first();
        $caps = $this->platforms->capabilities($account);
        if (! $caps->can(Capability::ReplyComments)) {
            return back()->with('error', 'Bu hesapta yorumlara cevap verilemiyor: '.$caps->reason(Capability::ReplyComments));
        }

        try {
            $this->platforms->for($account)->replyToComment($account, $comment, $data['body']);
        } catch (Throwable $e) {
            return back()->with('error', 'Cevap gönderilemedi: '.$e->getMessage());
        }

        $comment->update([
            'reply_body' => $data['body'],
            'replied_at' => now(),
            'replied_by' => $request->user()->id,
            'read_at' => $comment->read_at ?? now(),
        ]);
        Audit::log('comment.replied', $comment, ['platform' => $account->platform->value]);

        return back()->with('success', $account->is_demo ? 'DEMO: cevap simüle edildi (platforma gönderilmedi).' : 'Cevap gönderildi.');
    }

    public function sync(AccountSyncService $sync): RedirectResponse
    {
        $total = 0;
        SocialAccount::with('credential')->whereIn('connection_type', ['oauth', 'api_key', 'demo'])->get()
            ->filter(fn ($a) => $this->platforms->capabilities($a)->can(Capability::ReadComments))
            ->each(function ($a) use ($sync, &$total) {
                try {
                    $total += $sync->syncComments($a);
                } catch (Throwable) {
                }
            });

        return back()->with('success', "{$total} yeni yorum alındı.");
    }
}

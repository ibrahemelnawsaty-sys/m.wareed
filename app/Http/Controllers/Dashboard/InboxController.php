<?php

declare(strict_types=1);

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Http\Requests\Dashboard\ReplyMessageRequest;
use App\Models\Conversation;
use App\Models\Message;
use App\Services\Inbox\ConversationRouter;
use App\Services\WhatsApp\WhatsAppClient;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use RuntimeException;

/**
 * WhatsApp-like inbox for the tenant's team (owner + agents). Lets an agent
 * take over a conversation from the bot and reply by hand (§11 handoff).
 *
 * Isolation (§1): every query runs through the TenantScope global scope and
 * route-model binding resolves $conversation through it, so a foreign id 404s
 * — there is no manual `where('tenant_id')` and no cross-tenant read (ADR-02).
 */
class InboxController extends Controller
{
    public function __construct(
        private readonly WhatsAppClient $client,
        private readonly ConversationRouter $router,
    ) {}

    /**
     * List the tenant's conversations with inbox filters. `latestMessage` and
     * `assignedTo` are eager-loaded so the list never fires a query per row
     * (no N+1, §14). The view shows no per-row message count, so none is
     * aggregated — that subquery would be pure wasted work on shared hosting.
     */
    public function index(Request $request): View
    {
        $filter = (string) $request->query('filter', 'all');

        if (! in_array($filter, ['all', 'human', 'mine', 'unassigned'], true)) {
            $filter = 'all';
        }

        $userId = (int) $request->user()->id;

        $query = Conversation::query()
            ->with(['latestMessage', 'assignedTo']);

        match ($filter) {
            'human' => $query->humanMode(),
            'mine' => $query->assignedTo($userId),
            'unassigned' => $query->unassignedHuman(),
            default => $query,
        };

        /** @var LengthAwarePaginator<int, Conversation> $conversations */
        $conversations = $query
            ->orderByDesc('updated_at')
            ->paginate(20)
            ->withQueryString();

        // Tab badge counters (tenant-scoped). Cheap COUNT(*) aggregates.
        $counts = [
            'all' => Conversation::query()->count(),
            'human' => Conversation::query()->humanMode()->count(),
            'mine' => Conversation::query()->assignedTo($userId)->count(),
            'unassigned' => Conversation::query()->unassignedHuman()->count(),
        ];

        return view('dashboard.inbox.index', [
            'conversations' => $conversations,
            'filter' => $filter,
            'counts' => $counts,
        ]);
    }

    /**
     * Show one conversation's thread. Route-model binding resolves through the
     * TenantScope (foreign id → 404). Oldest-first, bounded columns (§14).
     */
    public function show(Conversation $conversation): View
    {
        $messages = $conversation->messages()
            ->with('user:id,name')
            ->orderBy('id')
            ->get(['id', 'direction', 'body', 'status', 'user_id', 'created_at']);

        return view('dashboard.inbox.show', [
            'conversation' => $conversation->load('assignedTo'),
            'messages' => $messages,
        ]);
    }

    /**
     * JSON feed for the Alpine poller: messages with an id greater than ?after.
     * Tenant-scoped via binding; bounded columns; author resolved for display.
     */
    public function messages(Conversation $conversation, Request $request): JsonResponse
    {
        $after = (int) $request->query('after', 0);

        $messages = $conversation->messages()
            ->with('user:id,name')
            ->where('id', '>', $after)
            ->orderBy('id')
            ->get(['id', 'direction', 'body', 'status', 'user_id', 'created_at']);

        return response()->json(
            $messages->map(fn (Message $message): array => [
                'id' => $message->id,
                'direction' => $message->direction,
                'body' => $message->body,
                'status' => $message->status,
                'author' => $this->authorLabel($message),
                'created_at' => $message->created_at?->format('Y-m-d H:i'),
            ])->all()
        );
    }

    /**
     * Send a free-form reply as the current agent.
     *
     * Guards, in order: the 24h window (§11), then authorization — an agent may
     * not answer a conversation assigned to someone else (only the owner can);
     * an unassigned conversation is atomically claimed by the replier first.
     */
    public function reply(Conversation $conversation, ReplyMessageRequest $request): RedirectResponse
    {
        // 24h window guard (§11): no free-form send once it has closed.
        if (! $conversation->isWindowOpen()) {
            return back()->withErrors([
                'reply' => 'انتهت نافذة 24 ساعة؛ لا يمكن إرسال رسالة حرة (يلزم قالب معتمد).',
            ]);
        }

        $user = $request->user();

        // Authorization: a conversation assigned to another agent is off-limits
        // unless the actor is the owner (§13 least privilege).
        if ($conversation->isAssigned() && ! $conversation->isAssignedTo($user) && ! $user->isOwner()) {
            return back()->withErrors([
                'reply' => 'هذه المحادثة مُسندة لموظف آخر.',
            ]);
        }

        // Unassigned: replying takes ownership, so claim it through the router —
        // ONE atomic, capacity-aware, race-safe step (Phase 6c). It enforces the
        // agent's target against the live committed load under a row lock (the
        // OWNER is exempt) AND wins the conversation only while still unassigned,
        // so neither two agents answering the same customer nor one agent
        // overshooting their target is possible under concurrency (§13, §3).
        // Already-assigned-to-me conversations skip this — the agent keeps
        // answering their own thread (and the owner can answer any, via the
        // authorization check above).
        if (! $conversation->isAssigned()) {
            $outcome = $this->router->claimFor($conversation, $user);

            if ($outcome === 'full') {
                return back()->withErrors([
                    'reply' => "بلغت سقف محادثاتك المفتوحة ({$user->conversationQuota()}). أنهِ محادثة أو أعِدها للبوت أولاً.",
                ]);
            }

            if ($outcome === 'taken') {
                return back()->withErrors([
                    'reply' => 'تم استلام هذه المحادثة من موظف آخر للتو.',
                ]);
            }
        }

        $body = (string) $request->validated('body');
        $account = $conversation->whatsappAccount;

        try {
            $sent = $this->client->sendText($account, $conversation->wa_contact_id, $body);
        } catch (RuntimeException $e) {
            report($e);

            return back()->withErrors([
                'reply' => 'تعذّر إرسال الرسالة عبر واتساب. حاول مرة أخرى.',
            ]);
        }

        Message::query()->create([
            'conversation_id' => $conversation->id,
            'user_id' => $user->id,
            'wa_message_id' => $sent['wa_message_id'],
            'direction' => 'out',
            'type' => 'text',
            'body' => $body,
            'status' => 'sent',
        ]);

        $conversation->touch();

        return back()->with('status', 'inbox-reply-sent');
    }

    /**
     * Claim an unassigned conversation for the current agent. If another agent
     * won the race, surface a gentle error rather than silently overwriting.
     *
     * Capacity guard (balanced mode, Phase 6c): an agent already at their open-
     * conversation target cannot take another — they must finish or release one
     * first. The OWNER is exempt (supervisor/overflow) via
     * isAtConversationCapacity().
     */
    public function claim(Conversation $conversation, Request $request): RedirectResponse
    {
        $user = $request->user();

        return match ($this->router->claimFor($conversation, $user)) {
            'claimed' => back()->with('status', 'inbox-claimed'),
            'full' => back()->withErrors([
                'reply' => "بلغت سقف محادثاتك المفتوحة ({$user->conversationQuota()}). أنهِ محادثة أو أعِدها للبوت أولاً.",
            ]),
            default => back()->withErrors([
                'reply' => 'تم استلام هذه المحادثة من موظف آخر.',
            ]),
        };
    }

    /**
     * Return a conversation to the bot. Allowed for the assigned agent or the
     * owner only (§13).
     */
    public function release(Conversation $conversation, Request $request): RedirectResponse
    {
        $user = $request->user();

        if ($conversation->isAssigned() && ! $conversation->isAssignedTo($user) && ! $user->isOwner()) {
            return back()->withErrors([
                'reply' => 'لا يمكنك إرجاع محادثة مُسندة لموظف آخر.',
            ]);
        }

        $conversation->returnToAi();

        return back()->with('status', 'inbox-released');
    }

    /**
     * Display label for a message author: the agent's name, 'البوت' for an
     * outbound message with no agent, or 'العميل' for an inbound one.
     */
    private function authorLabel(Message $message): string
    {
        if ($message->direction === 'in') {
            return 'العميل';
        }

        // Outbound with no agent → the bot; otherwise the agent's name.
        if ($message->user_id === null) {
            return 'البوت';
        }

        return $message->user->name;
    }
}

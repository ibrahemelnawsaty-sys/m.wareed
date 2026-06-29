<?php

declare(strict_types=1);

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\Conversation;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\View\View;

/**
 * Read-only conversation monitoring (§1, §14). Every query runs through the
 * TenantScope global scope, so a tenant only ever sees its own conversations
 * and a foreign id 404s via route-model binding — there is no manual
 * `where('tenant_id')` (ADR-02). Nothing here writes.
 */
class ConversationController extends Controller
{
    /**
     * List the active tenant's conversations, newest activity first.
     *
     * `messages_count` is eager-aggregated (withCount) and the latest message is
     * eager-loaded so the index never fires a query per row (no N+1, §14).
     */
    public function index(): View
    {
        /** @var LengthAwarePaginator<int, Conversation> $conversations */
        $conversations = Conversation::query()
            ->withCount('messages')
            ->with(['latestMessage'])
            ->orderByDesc('updated_at')
            ->paginate(20);

        return view('dashboard.conversations.index', [
            'conversations' => $conversations,
        ]);
    }

    /**
     * Show one conversation's message thread. Route-model binding resolves
     * $conversation through the TenantScope, so a conversation owned by another
     * tenant returns 404 (IDOR, §1/§13) — never a cross-tenant read.
     */
    public function show(Conversation $conversation): View
    {
        // Oldest-first so the thread reads top-to-bottom. Bounded column list,
        // single query, no per-message lazy loads (§14).
        $messages = $conversation->messages()
            ->orderBy('id')
            ->get(['id', 'direction', 'body', 'tokens_in', 'tokens_out', 'status', 'created_at']);

        return view('dashboard.conversations.show', [
            'conversation' => $conversation,
            'messages' => $messages,
        ]);
    }
}

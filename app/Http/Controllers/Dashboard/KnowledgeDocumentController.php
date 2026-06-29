<?php

declare(strict_types=1);

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Http\Requests\Dashboard\StoreKnowledgeDocumentRequest;
use App\Http\Requests\Dashboard\UpdateKnowledgeDocumentRequest;
use App\Models\KnowledgeDocument;
use App\Models\WhatsappAccount;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class KnowledgeDocumentController extends Controller
{
    /**
     * List the active tenant's documents (TenantScope filters every query).
     */
    public function index(): View
    {
        $documents = KnowledgeDocument::query()->latest()->get();

        return view('dashboard.knowledge.index', [
            'documents' => $documents,
        ]);
    }

    public function create(): View
    {
        return view('dashboard.knowledge.create');
    }

    /**
     * Store a document, attaching it to the tenant's WhatsApp account.
     * tenant_id is auto-filled by BelongsToTenant from the active tenant (§1).
     */
    public function store(StoreKnowledgeDocumentRequest $request): RedirectResponse
    {
        $account = WhatsappAccount::query()->firstOrFail();

        KnowledgeDocument::create([
            'whatsapp_account_id' => $account->id,
            'title' => $request->validated('title'),
            'type' => 'text',
            'content' => $request->validated('content'),
        ]);

        return redirect()
            ->route('knowledge.index')
            ->with('status', 'knowledge-created');
    }

    /**
     * Edit a document. Route-model binding resolves $document through the
     * TenantScope, so a document belonging to another tenant 404s (IDOR, §13).
     */
    public function edit(KnowledgeDocument $document): View
    {
        return view('dashboard.knowledge.edit', [
            'document' => $document,
        ]);
    }

    public function update(UpdateKnowledgeDocumentRequest $request, KnowledgeDocument $document): RedirectResponse
    {
        $document->fill($request->safe()->only(['title', 'content']))->save();

        return redirect()
            ->route('knowledge.index')
            ->with('status', 'knowledge-updated');
    }

    public function destroy(KnowledgeDocument $document): RedirectResponse
    {
        $document->delete();

        return redirect()
            ->route('knowledge.index')
            ->with('status', 'knowledge-deleted');
    }
}

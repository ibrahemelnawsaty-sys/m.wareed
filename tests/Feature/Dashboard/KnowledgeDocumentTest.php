<?php

declare(strict_types=1);

use App\Models\KnowledgeDocument;
use App\Models\Tenant;
use App\Models\WhatsappAccount;
use App\Support\Tenancy\TenantContext;

beforeEach(function () {
    app(TenantContext::class)->forget();
});

test('a tenant can list, create, edit and delete its knowledge documents', function () {
    $owner = provisionTenant();

    // Create
    $this->actingAs($owner)->post(route('knowledge.store'), [
        'title' => 'سياسة الإرجاع',
        'content' => 'يمكن إرجاع المنتجات خلال 14 يوماً.',
    ])->assertRedirect(route('knowledge.index'));

    $document = KnowledgeDocument::query()->firstOrFail();
    expect($document->title)->toBe('سياسة الإرجاع');
    expect($document->type)->toBe('text');
    expect($document->whatsapp_account_id)->toBe(WhatsappAccount::query()->firstOrFail()->id);

    // Index
    $this->actingAs($owner)->get(route('knowledge.index'))
        ->assertOk()
        ->assertSee('سياسة الإرجاع');

    // Update
    $this->actingAs($owner)->put(route('knowledge.update', $document), [
        'title' => 'سياسة الإرجاع المحدّثة',
        'content' => 'تم تمديد المدة إلى 30 يوماً.',
    ])->assertRedirect(route('knowledge.index'));

    $document->refresh();
    expect($document->title)->toBe('سياسة الإرجاع المحدّثة');

    // Delete
    $this->actingAs($owner)->delete(route('knowledge.destroy', $document))
        ->assertRedirect(route('knowledge.index'));

    expect(KnowledgeDocument::query()->count())->toBe(0);
});

test('storing a document requires title and content', function () {
    $owner = provisionTenant();

    $this->actingAs($owner)->post(route('knowledge.store'), [
        'title' => '',
        'content' => '',
    ])->assertSessionHasErrors(['title', 'content']);
});

test('a tenant cannot view, edit or delete another tenant document (IDOR)', function () {
    // Tenant B owns a document.
    $tenantB = Tenant::factory()->create();
    $docB = app(TenantContext::class)->run($tenantB->id, function () use ($tenantB) {
        $accountB = WhatsappAccount::factory()->create(['tenant_id' => $tenantB->id]);

        return KnowledgeDocument::factory()->create([
            'tenant_id' => $tenantB->id,
            'whatsapp_account_id' => $accountB->id,
            'title' => 'سر المستأجر ب',
        ]);
    });

    // Tenant A signs in.
    app(TenantContext::class)->forget();
    $ownerA = provisionTenant();

    // A's index must not leak B's document.
    $this->actingAs($ownerA)->get(route('knowledge.index'))
        ->assertOk()
        ->assertDontSee('سر المستأجر ب');

    // Editing B's document by id must 404 (resolved through TenantScope).
    $this->actingAs($ownerA)->get(route('knowledge.edit', $docB->id))->assertNotFound();

    // Updating B's document must 404 and leave it unchanged.
    $this->actingAs($ownerA)->put(route('knowledge.update', $docB->id), [
        'title' => 'اختراق',
        'content' => 'محاولة تعديل عابرة',
    ])->assertNotFound();

    // Deleting B's document must 404.
    $this->actingAs($ownerA)->delete(route('knowledge.destroy', $docB->id))->assertNotFound();

    app(TenantContext::class)->forget();
    $stillThere = KnowledgeDocument::query()->withoutGlobalScopes()->find($docB->id);
    expect($stillThere)->not->toBeNull();
    expect($stillThere->title)->toBe('سر المستأجر ب');
});

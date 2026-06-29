<?php

declare(strict_types=1);

use App\Mail\CustomerNotification;
use App\Models\CustomerMessage;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Tenancy\TenantContext;
use Illuminate\Support\Facades\Mail;

/*
| Phase 4d-1 — the admin emails a customer (their tenant owner) from the console,
| and every send is recorded in the customer_messages audit log (§13). Access is
| admin-only; every cross-tenant read inside the controller uses
| withoutGlobalScopes(). The email body is escaped in the view (no HTML inject).
*/

beforeEach(function () {
    app(TenantContext::class)->forget();
    Mail::fake();
});

test('admin sends an email to the customer owner and records the audit row', function () {
    $admin = makeAdmin();

    $customer = Tenant::factory()->create();
    $owner = User::factory()->create([
        'tenant_id' => $customer->id,
        'role' => 'owner',
        'email' => 'owner@example.test',
    ]);

    $this->actingAs($admin)
        ->post(route('admin.customers.messages.store', $customer->id), [
            'subject' => 'تحديث مهم بخصوص حسابك',
            'body' => 'مرحباً، نود إعلامك بتحديث جديد على المنصة.',
        ])
        ->assertRedirect(route('admin.customers.show', $customer->id))
        ->assertSessionHas('status', 'customer-message-sent');

    // Mailed to the owner's address.
    Mail::assertSent(CustomerNotification::class, function (CustomerNotification $mail) use ($owner) {
        return $mail->hasTo($owner->email)
            && $mail->subjectLine === 'تحديث مهم بخصوص حسابك';
    });

    // Audit row written with the correct attribution.
    $row = CustomerMessage::query()->where('tenant_id', $customer->id)->firstOrFail();
    expect($row->channel)->toBe('email');
    expect($row->subject)->toBe('تحديث مهم بخصوص حسابك');
    expect($row->body)->toBe('مرحباً، نود إعلامك بتحديث جديد على المنصة.');
    expect($row->sent_by_user_id)->toBe($admin->id);
});

test('a non-admin is forbidden from sending a message and no mail is sent', function () {
    $owner = User::factory()->create(); // ordinary owner with a tenant
    expect($owner->isAdmin())->toBeFalse();

    $customer = Tenant::factory()->create();
    User::factory()->create(['tenant_id' => $customer->id, 'role' => 'owner']);

    $this->actingAs($owner)
        ->post(route('admin.customers.messages.store', $customer->id), [
            'subject' => 'محاولة غير مصرّح بها',
            'body' => 'لا ينبغي أن تمر.',
        ])
        ->assertForbidden();

    Mail::assertNothingSent();
    expect(CustomerMessage::query()->count())->toBe(0);
});

test('an empty subject or body fails validation and sends nothing', function () {
    $admin = makeAdmin();

    $customer = Tenant::factory()->create();
    User::factory()->create(['tenant_id' => $customer->id, 'role' => 'owner']);

    $this->actingAs($admin)
        ->post(route('admin.customers.messages.store', $customer->id), [
            'subject' => '',
            'body' => '',
        ])
        ->assertSessionHasErrors(['subject', 'body']);

    Mail::assertNothingSent();
    expect(CustomerMessage::query()->count())->toBe(0);
});

test('a missing owner email surfaces a gentle error, not a 500, and sends nothing', function () {
    $admin = makeAdmin();

    // Tenant with no users at all → no owner email to reach.
    $customer = Tenant::factory()->create();

    $this->actingAs($admin)
        ->post(route('admin.customers.messages.store', $customer->id), [
            'subject' => 'موضوع',
            'body' => 'نص',
        ])
        ->assertRedirect()
        ->assertSessionHasErrors('subject');

    Mail::assertNothingSent();
    expect(CustomerMessage::query()->count())->toBe(0);
});

test('the show page lists this customer messages and not another customer messages', function () {
    $admin = makeAdmin();

    $customer = Tenant::factory()->create();
    User::factory()->create(['tenant_id' => $customer->id, 'role' => 'owner']);

    $other = Tenant::factory()->create();
    User::factory()->create(['tenant_id' => $other->id, 'role' => 'owner']);

    CustomerMessage::create([
        'tenant_id' => $customer->id,
        'sent_by_user_id' => $admin->id,
        'channel' => 'email',
        'subject' => 'رسالة لعميلنا',
        'body' => 'مرحباً بعميلنا.',
    ]);

    CustomerMessage::create([
        'tenant_id' => $other->id,
        'sent_by_user_id' => $admin->id,
        'channel' => 'email',
        'subject' => 'رسالة لعميل آخر',
        'body' => 'مرحباً بالعميل الآخر.',
    ]);

    $response = $this->actingAs($admin)->get(route('admin.customers.show', $customer->id));

    $response->assertOk();
    $response->assertSee('رسالة لعميلنا');
    $response->assertDontSee('رسالة لعميل آخر');
});

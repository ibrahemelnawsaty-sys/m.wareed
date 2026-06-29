<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Tenant;
use App\Models\User;
use App\Models\WhatsappAccount;
use App\Support\Tenancy\TenantContext;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class RegisteredUserController extends Controller
{
    /**
     * Default Arabic system prompt seeded for every new tenant's bot.
     */
    private const DEFAULT_SYSTEM_PROMPT = 'أنت مساعد خدمة عملاء لطيف ومحترف. أجب باللغة العربية بإيجاز ووضوح، واعتمد فقط على المعلومات الموثوقة المتاحة لك. إن لم تعرف الإجابة فاعتذر بلطف واطلب من العميل التواصل مع فريق الدعم.';

    /**
     * Display the registration view.
     */
    public function create(): View
    {
        return view('auth.register');
    }

    /**
     * Handle an incoming registration request: provision a fresh tenant with its
     * owner user and a pending WhatsApp account, all inside one transaction so a
     * half-provisioned tenant can never exist (§1, ADR-02).
     *
     * @throws ValidationException
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'business_name' => ['required', 'string', 'max:255'],
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:'.User::class],
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
        ]);

        $context = app(TenantContext::class);

        $user = DB::transaction(function () use ($validated, $context): User {
            $tenant = Tenant::create([
                'name' => $validated['business_name'],
                'plan' => 'free',
                'status' => 'active',
            ]);

            // Bind the new tenant so BelongsToTenant auto-fills tenant_id on the
            // user and the WhatsApp account created below.
            $context->set($tenant->id);

            $user = User::create([
                'name' => $validated['name'],
                'email' => $validated['email'],
                'password' => Hash::make($validated['password']),
                'tenant_id' => $tenant->id,
                'role' => 'owner',
            ]);

            // MVP: auto-verify the owner so onboarding leads straight to the
            // dashboard without requiring an SMTP setup. Remove this line once
            // mail is configured to enforce real email verification.
            $user->markEmailAsVerified();

            WhatsappAccount::create([
                'tenant_id' => $tenant->id,
                'status' => 'pending',
                'ai_model' => 'gemini-2.5-flash-lite',
                'temperature' => 30,
                'system_prompt' => self::DEFAULT_SYSTEM_PROMPT,
            ]);

            return $user;
        });

        event(new Registered($user));

        Auth::login($user);

        return redirect(route('dashboard', absolute: false));
    }
}

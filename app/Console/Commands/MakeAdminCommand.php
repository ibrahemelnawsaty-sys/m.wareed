<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;

/**
 * Creates a platform super-admin from the server CLI. This is what the owner
 * runs on the host to bootstrap their own admin account — no admin sign-up
 * route exists, and no secret/password is ever committed to the repo (§13).
 *
 * The `is_admin` flag is set with forceFill(), NOT mass assignment, so it can
 * never be reached from a web request even by accident (privilege escalation
 * defence, §13). The admin has tenant_id = null (cross-tenant) and a verified
 * email so they can sign straight in.
 */
class MakeAdminCommand extends Command
{
    protected $signature = 'wareed:make-admin {email} {password}';

    protected $description = 'إنشاء حساب مدير منصة (Super-Admin) من سطر الأوامر';

    public function handle(): int
    {
        /** @var string $email */
        $email = $this->argument('email');
        /** @var string $password */
        $password = $this->argument('password');

        $validator = Validator::make(
            ['email' => $email, 'password' => $password],
            [
                'email' => ['required', 'string', 'lowercase', 'email', 'max:255'],
                'password' => ['required', 'string', Password::min(8)],
            ],
        );

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                $this->error($error);
            }

            return self::FAILURE;
        }

        if (User::query()->withoutGlobalScopes()->where('email', $email)->exists()) {
            $this->error("يوجد مستخدم بالبريد {$email} بالفعل. لم يُنشأ أدمن جديد.");

            return self::FAILURE;
        }

        $user = new User;

        // Fillable attributes via the normal guarded path…
        $user->fill([
            'name' => 'مدير المنصة',
            'email' => $email,
            'password' => Hash::make($password),
            'tenant_id' => null,
            'role' => 'admin',
        ]);

        // …and the privileged flag + verification ONLY via forceFill — never
        // mass-assignable from user input (§13).
        $user->forceFill([
            'is_admin' => true,
            'email_verified_at' => now(),
        ]);

        $user->save();

        $this->info("تم إنشاء حساب الأدمن بنجاح: {$email} (is_admin=true، بلا مستأجر).");

        return self::SUCCESS;
    }
}

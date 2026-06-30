<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\ServiceMenu;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ServiceMenu>
 */
class ServiceMenuFactory extends Factory
{
    protected $model = ServiceMenu::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'enabled' => true,
            'header' => 'مرحباً بك',
            'body' => 'كيف يمكننا خدمتك؟ اختر من القائمة.',
            'button_label' => 'الخدمات',
            'footer' => null,
            'trigger_on_welcome' => true,
        ];
    }

    public function disabled(): static
    {
        return $this->state(fn (): array => ['enabled' => false]);
    }
}

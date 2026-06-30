<?php

declare(strict_types=1);

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Http\Requests\Dashboard\UpdateServiceMenuRequest;
use App\Models\ServiceMenu;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * Owner-only configuration of the interactive service menu (Phase 7b, §11/§13).
 * The route is behind the `owner` middleware, so an agent never reaches here.
 * One menu per tenant; every read/write is filtered by TenantScope (§1).
 */
class ServiceMenuController extends Controller
{
    /**
     * Show the editor. Materialises the single menu for the tenant if it does
     * not exist yet (disabled, with sensible defaults) so the form always has a
     * model to bind. `tenant_id` is set by BelongsToTenant from the bound
     * context, never from input (§13).
     */
    public function edit(): View
    {
        $menu = ServiceMenu::query()->with('rows')->first()
            ?? ServiceMenu::query()->create([
                'enabled' => false,
                'body' => 'كيف يمكننا خدمتك؟ اختر من القائمة.',
                'button_label' => 'الخدمات',
                'trigger_on_welcome' => true,
            ]);

        return view('dashboard.menu.edit', [
            'menu' => $menu,
        ]);
    }

    /**
     * Save the menu and rebuild its rows from the submitted set.
     *
     * Row sync is a full rebuild INSIDE A TRANSACTION (delete-then-insert): the
     * form always submits the COMPLETE desired row set, so there is no partial
     * "empty array means no change" ambiguity here — an empty set legitimately
     * means "no rows / no menu". This differs from the non-destructive rule for
     * forms that omit a field (§3); here the field is always present and
     * authoritative. `row_key` is generated server-side from the row index
     * (never from input) so the list-reply id space stays trusted (§13). The
     * transaction keeps the menu's rows consistent if a write fails midway.
     */
    public function update(UpdateServiceMenuRequest $request): RedirectResponse
    {
        $menu = ServiceMenu::query()->first()
            ?? ServiceMenu::query()->create([
                'body' => 'كيف يمكننا خدمتك؟ اختر من القائمة.',
                'button_label' => 'الخدمات',
            ]);

        /** @var array<int, array<string, mixed>> $rows */
        $rows = $request->validated('rows', []);

        DB::transaction(function () use ($menu, $request, $rows): void {
            $menu->fill([
                'enabled' => $request->boolean('enabled'),
                'trigger_on_welcome' => $request->boolean('trigger_on_welcome'),
                'header' => $request->validated('header'),
                'body' => $request->validated('body'),
                'button_label' => $request->validated('button_label'),
                'footer' => $request->validated('footer'),
            ])->save();

            // Rebuild rows. Scoped delete (TenantScope + the relation) only
            // touches THIS menu's rows.
            $menu->rows()->delete();

            foreach (array_values($rows) as $index => $row) {
                $isReply = ($row['action_type'] ?? null) === 'reply';

                // Mass-assign only the owner-authored fields; `row_key` is NOT
                // fillable (§13), so it is set server-side via forceFill —
                // request input can never choose a list-reply id.
                $menu->rows()->make([
                    'title' => (string) $row['title'],
                    'description' => $row['description'] ?? null,
                    'action_type' => (string) $row['action_type'],
                    // Clear reply_text for a handoff row so a stale value never
                    // ships in a future payload.
                    'reply_text' => $isReply ? ($row['reply_text'] ?? null) : null,
                    'sort_order' => $index,
                ])->forceFill([
                    // Server-generated, stable, unique within the menu.
                    'row_key' => 'row_'.$index,
                ])->save();
            }
        });

        return redirect()
            ->route('menu.edit')
            ->with('status', 'menu-updated');
    }
}

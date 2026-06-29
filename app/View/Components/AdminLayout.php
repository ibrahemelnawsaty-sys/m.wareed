<?php

declare(strict_types=1);

namespace App\View\Components;

use Illuminate\View\Component;
use Illuminate\View\View;

/**
 * Renders the standalone super-admin chrome (§1). Kept separate from
 * {@see AppLayout} so the admin console never shares a layout, nav, or context
 * with tenant-facing pages.
 */
class AdminLayout extends Component
{
    public function render(): View
    {
        return view('admin.layouts.app');
    }
}

<?php

namespace App\Http\Controllers;

use App\Services\DashboardService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __construct(
        private readonly DashboardService $dashboardService,
    ) {
    }

    public function index(Request $request): View
    {
        $summary = $this->dashboardService->summaryForUser($request->user());

        return view('dashboard.index', [
            'summary' => $summary,
            'user' => $request->user(),
        ]);
    }
}


<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ReportRequest;
use App\Services\AdminReportService;
use Inertia\Inertia;
use Inertia\Response;

class ReportController extends Controller
{
    public function __invoke(ReportRequest $request, AdminReportService $reports): Response
    {
        $start = $request->startDate();
        $end = $request->endDate();

        return Inertia::render('admin/Report', [
            'start_date' => $start->toDateString(),
            'end_date' => $end->toDateString(),
            ...$reports->build($start, $end),
        ]);
    }
}

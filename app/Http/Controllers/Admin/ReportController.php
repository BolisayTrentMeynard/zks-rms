<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\ReportService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ReportController extends Controller
{
    public function index(Request $request, ReportService $reports): View
    {
        $years = $reports->years();
        $asked = $request->query('year');

        // A bad or unknown year in the address bar falls back to the current year.
        $year = is_string($asked) && ctype_digit($asked) && in_array((int) $asked, $years, true)
            ? (int) $asked
            : (int) today()->format('Y');

        return view('admin.reports.index', [
            'year' => $year,
            'years' => $years,
            ...$reports->forYear($year),
        ]);
    }
}

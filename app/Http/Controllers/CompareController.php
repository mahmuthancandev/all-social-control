<?php

namespace App\Http\Controllers;

use App\Models\SocialAccount;
use App\Services\Analytics\AnalyticsService;
use App\Support\Range;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CompareController extends Controller
{
    public function index(Request $request, AnalyticsService $analytics): Response
    {
        $days = Range::days($request, 30);
        $includeDemo = $request->query('demo', '1') !== '0';
        $accounts = SocialAccount::with('projects')->when(! $includeDemo, fn ($q) => $q->where('is_demo', false))->get();

        return Inertia::render('compare/Index', [
            'range' => $days,
            'demo' => $includeDemo ? '1' : '0',
            'platforms' => $analytics->platformComparison($accounts, $days),
            'projects' => $analytics->projectComparison($days, $includeDemo),
            'platformFollowers' => $analytics->timeSeries($accounts, 'followers', $days, 'platform'),
            'projectViews' => $analytics->timeSeries($accounts, 'views', $days, 'project'),
            'projectFollowers' => $analytics->timeSeries($accounts, 'followers', $days, 'project'),
        ]);
    }
}

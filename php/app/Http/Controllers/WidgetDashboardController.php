<?php

namespace App\Http\Controllers;

use App\Models\SearchAuditLog;
use App\Models\WidgetClient;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WidgetDashboardController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        /** @var WidgetClient|null $widgetClient */
        $widgetClient = $request->attributes->get('widget_client');
        abort_unless($widgetClient?->dashboard_enabled, 404);

        $validated = $request->validate([
            'period' => ['sometimes', 'integer', 'in:7,30,90'],
        ]);
        $period = (int) ($validated['period'] ?? 30);
        $startDate = now()->subDays($period - 1)->startOfDay();
        $audits = SearchAuditLog::query()->where('created_at', '>=', $startDate);
        $unanswered = (clone $audits)->where(function ($query): void {
            $query->where('response_status', 'unanswered')
                ->orWhere(function ($query): void {
                    $query->whereNull('response_status')->where('result_count', 0);
                });
        });

        $dailyResults = (clone $audits)
            ->selectRaw('DATE(created_at) as date, COUNT(*) as total')
            ->groupByRaw('DATE(created_at)')
            ->pluck('total', 'date');
        $dailyAnswers = (clone $audits)
            ->selectRaw(
                'DATE(created_at) as date, SUM(CASE WHEN response_status = ? THEN 1 ELSE 0 END) as answered, SUM(CASE WHEN response_status = ? OR (response_status IS NULL AND result_count = 0) THEN 1 ELSE 0 END) as unanswered',
                ['answered', 'unanswered'],
            )
            ->groupByRaw('DATE(created_at)')
            ->get()
            ->keyBy('date');

        $daily = collect(range(0, $period - 1))->map(function (int $day) use ($startDate, $dailyResults, $dailyAnswers): array {
            $date = $startDate->copy()->addDays($day);
            $dateKey = $date->toDateString();
            $results = $dailyAnswers->get($dateKey);

            return [
                'date' => $dateKey,
                'label' => $date->translatedFormat('d M'),
                'total' => (int) ($dailyResults[$dateKey] ?? 0),
                'answered' => (int) ($results?->answered ?? 0),
                'unanswered' => (int) ($results?->unanswered ?? 0),
            ];
        });

        return response()->json([
            'period' => $period,
            'stats' => [
                'total' => (clone $audits)->count(),
                'answered' => (clone $audits)->where('response_status', 'answered')->count(),
                'unanswered' => (clone $unanswered)->count(),
            ],
            'daily' => $daily,
        ])->header('Cache-Control', 'no-store');
    }
}

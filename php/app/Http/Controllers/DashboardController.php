<?php

namespace App\Http\Controllers;

use App\Models\FaqEntry;
use App\Models\SearchAuditLog;
use App\Services\Chat\QueryNormalizer;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use ZipArchive;

class DashboardController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $period = (int) $request->integer('period', 30);
        $period = in_array($period, [7, 30, 90], true) ? $period : 30;
        $startDate = now()->subDays($period - 1)->startOfDay();
        $audits = SearchAuditLog::query()->where('created_at', '>=', $startDate);
        $unanswered = (clone $audits)->where(function ($query): void {
            $query->where('response_status', 'unanswered')
                ->orWhere(function ($query): void {
                    $query->whereNull('response_status')->where('result_count', 0);
                });
        });

        $dailyResults = (clone $audits)
            ->selectRaw('DATE(created_at) as date, COUNT(*) as total, SUM(CASE WHEN response_status = ? THEN 1 ELSE 0 END) as answered, SUM(CASE WHEN response_status = ? THEN 1 ELSE 0 END) as unanswered', ['answered', 'unanswered'])
            ->groupByRaw('DATE(created_at)')
            ->pluck('total', 'date');
        $dailyAnswers = (clone $audits)
            ->selectRaw('DATE(created_at) as date, SUM(CASE WHEN response_status = ? THEN 1 ELSE 0 END) as answered, SUM(CASE WHEN response_status = ? THEN 1 ELSE 0 END) as unanswered', ['answered', 'unanswered'])
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
        $contextGroups = (clone $audits)
            ->select(['query', 'response_status'])
            ->latest()
            ->limit(500)
            ->get();
        $contextGroups = $this->paginateCollection($this->automaticContextGroups($contextGroups), $request->integer('context_page', 1), 6);
        $popularQuestions = (clone $audits)
            ->selectRaw('query, COUNT(*) as total, MAX(created_at) as asked_at')
            ->groupBy('query')
            ->orderByDesc('total')
            ->paginate(10, ['*'], 'questions_page')
            ->through(fn (SearchAuditLog $audit): array => [
                'question' => $audit->query,
                'total' => (int) $audit->total,
                'askedAt' => $audit->asked_at ? Carbon::parse($audit->asked_at)->toIso8601String() : null,
            ]);

        return Inertia::render('dashboard', [
            'period' => $period,
            'stats' => [
                'total' => (clone $audits)->count(),
                'answered' => (clone $audits)->where('response_status', 'answered')->count(),
                'unanswered' => (clone $unanswered)->count(),
            ],
            'daily' => $daily,
            'contextGroups' => $contextGroups,
            'popularQuestions' => $popularQuestions,
            'unansweredQuestions' => (clone $unanswered)
                ->latest()
                ->paginate(10, ['id', 'query', 'answer', 'created_at'], 'unanswered_page')
                ->through(fn (SearchAuditLog $audit): array => [
                    'id' => $audit->id,
                    'question' => $audit->query,
                    'answer' => $audit->answer,
                    'askedAt' => $audit->created_at?->toIso8601String(),
                ]),
        ]);
    }

    /**
     * Daftar pertanyaan pada satu hari untuk detail bar chart.
     */
    public function dayQuestions(Request $request): JsonResponse
    {
        $validated = $request->validate(['date' => 'required|date_format:Y-m-d']);

        $questions = SearchAuditLog::query()
            ->whereDate('created_at', $validated['date'])
            ->orderBy('created_at')
            ->get(['id', 'query', 'user_name', 'user_identifier', 'response_status', 'created_at'])
            ->map(fn (SearchAuditLog $audit): array => [
                'id' => $audit->id,
                'question' => $audit->query,
                'user' => $audit->user_name ?: ($audit->user_identifier ?: 'Anonim'),
                'status' => $audit->response_status,
                'askedAt' => $audit->created_at?->toIso8601String(),
            ]);

        return response()->json(['data' => $questions]);
    }

    /**
     * Simpan jawaban manual untuk pertanyaan yang belum terjawab. Jawaban
     * disimpan sebagai FAQ approved (dipakai chatbot untuk pertanyaan yang
     * sama) dan log audit ditandai answered.
     */
    public function answer(Request $request, SearchAuditLog $auditLog): RedirectResponse
    {
        $validated = $request->validate([
            'answer' => 'required|string|max:5000',
        ]);
        $embedding = $this->embedText(QueryNormalizer::normalize($auditLog->query));

        if (empty($embedding)) {
            Inertia::flash('toast', ['type' => 'error', 'message' => 'Layanan embedding tidak tersedia, jawaban belum disimpan. Silakan coba lagi.']);

            return back();
        }

        $vector = '['.implode(',', $embedding).']';
        $answer = trim($validated['answer']);
        $adminName = $request->user()?->name;

        DB::transaction(function () use ($auditLog, $answer, $vector, $adminName): void {
            $faq = FaqEntry::firstOrNew(['search_audit_log_id' => $auditLog->id]);

            if (! $faq->exists) {
                $faq->id = (string) Str::uuid();
            }

            $faq->fill([
                'question' => $auditLog->query,
                'answer' => $answer,
                'status' => 'approved',
                'source' => 'manual',
                'source_count' => 1,
                'approved_at' => now(),
                'approved_by' => $adminName,
            ])->save();

            DB::statement('UPDATE faq_entries SET question_embedding = ?::vector WHERE id = ?', [$vector, $faq->id]);

            $auditLog->update([
                'answer' => $answer,
                'response_status' => 'answered',
                'manual_answered_at' => now(),
                'manual_answered_by' => $adminName,
            ]);
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Jawaban disimpan dan akan dipakai chatbot untuk pertanyaan yang sama.']);

        return back();
    }

    public function destroyAudit(SearchAuditLog $auditLog): RedirectResponse
    {
        FaqEntry::where('search_audit_log_id', $auditLog->id)->delete();
        $auditLog->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Pertanyaan berhasil dihapus.']);

        return back();
    }

    private function embedText(string $text): ?array
    {
        try {
            $response = Http::connectTimeout(3)->timeout(15)->post(
                rtrim(config('services.python_ai.base_url', 'http://localhost:8000'), '/').'/embed/queries',
                ['queries' => [$text]],
            );

            return $response->successful() ? $response->json('embeddings.0') : null;
        } catch (\Throwable $e) {
            Log::warning('Gagal embed jawaban manual', ['message' => $e->getMessage()]);

            return null;
        }
    }

    public function destroy(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'period' => 'required|integer|in:7,15,30,90',
            'questions' => 'nullable|array|min:1',
            'questions.*' => 'string|max:2000',
        ]);
        $audits = SearchAuditLog::query()->where('created_at', '>=', now()->subDays($validated['period'] - 1)->startOfDay());

        if (! empty($validated['questions'])) {
            $audits->whereIn('query', $validated['questions']);
        }

        $deleted = $audits->delete();

        return to_route('dashboard')->with('success', "{$deleted} data monitoring berhasil dihapus.");
    }

    public function export(Request $request): BinaryFileResponse
    {
        $validated = $request->validate([
            'period' => 'required|integer|in:7,15,30,90',
            'fields' => 'required|array|min:1',
            'fields.*' => 'in:asked_at,query,answer,response_status,feedback,result_count',
        ]);
        $columns = [
            'asked_at' => 'Waktu Pertanyaan',
            'query' => 'Pertanyaan',
            'answer' => 'Jawaban Chatbot',
            'response_status' => 'Status Respons',
            'feedback' => 'Feedback Pengguna',
            'result_count' => 'Jumlah Referensi',
        ];
        $audits = SearchAuditLog::query()
            ->where('created_at', '>=', now()->subDays($validated['period'] - 1)->startOfDay())
            ->latest()
            ->get();
        $rows = $audits->map(function (SearchAuditLog $audit) use ($validated): array {
            return collect($validated['fields'])->map(function (string $field) use ($audit): string {
                return match ($field) {
                    'asked_at' => $audit->created_at?->timezone('Asia/Jakarta')->format('d-m-Y H:i') ?? '',
                    'response_status' => match ($audit->response_status) {
                        'answered' => 'Terjawab',
                        'unanswered' => 'Belum terjawab',
                        'error' => 'Gagal',
                        default => 'Tidak diketahui',
                    },
                    'feedback' => match ($audit->feedback) {
                        'positive' => 'Positif',
                        'negative' => 'Negatif',
                        default => 'Belum ada',
                    },
                    default => (string) ($audit->{$field} ?? ''),
                };
            })->all();
        })->all();
        $filePath = tempnam(sys_get_temp_dir(), 'chatbot-monitoring-');
        $zip = new ZipArchive;
        $zip->open($filePath, ZipArchive::CREATE | ZipArchive::OVERWRITE);
        $zip->addFromString('[Content_Types].xml', $this->xlsxContentTypes());
        $zip->addFromString('_rels/.rels', $this->xlsxRelationships());
        $zip->addFromString('xl/workbook.xml', $this->xlsxWorkbook());
        $zip->addFromString('xl/_rels/workbook.xml.rels', $this->xlsxWorkbookRelationships());
        $zip->addFromString('xl/worksheets/sheet1.xml', $this->xlsxWorksheet(
            collect($validated['fields'])->map(fn (string $field): string => $columns[$field])->all(),
            $rows,
        ));
        $zip->close();

        return response()->download($filePath, 'monitoring-chatbot-'.now()->format('Ymd-His').'.xlsx', [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ])->deleteFileAfterSend(true);
    }

    private function automaticContextGroups($audits): array
    {
        $clusters = [];

        foreach ($audits as $audit) {
            $tokens = $this->questionTokens($audit->query);
            $matchingClusters = [];

            foreach ($clusters as $index => $cluster) {
                if (array_intersect($tokens, $cluster['tokens']) !== []) {
                    $matchingClusters[] = $index;
                }
            }

            if ($matchingClusters === []) {
                $clusters[] = [
                    'tokens' => $tokens,
                    'audits' => [$audit],
                ];

                continue;
            }

            $targetIndex = array_shift($matchingClusters);
            $clusters[$targetIndex]['tokens'] = array_values(array_unique([...$clusters[$targetIndex]['tokens'], ...$tokens]));
            $clusters[$targetIndex]['audits'][] = $audit;

            foreach (array_reverse($matchingClusters) as $index) {
                $clusters[$targetIndex]['tokens'] = array_values(array_unique([...$clusters[$targetIndex]['tokens'], ...$clusters[$index]['tokens']]));
                $clusters[$targetIndex]['audits'] = [...$clusters[$targetIndex]['audits'], ...$clusters[$index]['audits']];
                array_splice($clusters, $index, 1);
            }
        }

        return collect($clusters)
            ->map(function (array $cluster): array {
                $questions = collect($cluster['audits'])->groupBy('query')->map(function ($audits, string $question): array {
                    return [
                        'question' => $question,
                        'total' => $audits->count(),
                        'unanswered' => $audits->where('response_status', 'unanswered')->count(),
                        'askedAt' => $audits->max('created_at')?->toIso8601String(),
                    ];
                })->sortByDesc('total')->values();
                $commonTokens = collect($cluster['audits'])
                    ->flatMap(fn (SearchAuditLog $audit) => $this->questionTokens($audit->query))
                    ->countBy()
                    ->sortDesc()
                    ->keys()
                    ->take(3)
                    ->map(fn (string $token): string => mb_convert_case($token, MB_CASE_TITLE, 'UTF-8'))
                    ->implode(', ');

                return [
                    'context' => $commonTokens !== '' ? $commonTokens : 'Pertanyaan umum',
                    'total' => collect($cluster['audits'])->count(),
                    'unanswered' => collect($cluster['audits'])->where('response_status', 'unanswered')->count(),
                    'questions' => $questions,
                ];
            })
            ->sortByDesc('total')
            ->take(8)
            ->values()
            ->all();
    }

    private function paginateCollection(array $items, int $page, int $perPage): array
    {
        $page = max($page, 1);
        $total = count($items);

        return [
            'data' => array_values(array_slice($items, ($page - 1) * $perPage, $perPage)),
            'current_page' => $page,
            'last_page' => max((int) ceil($total / $perPage), 1),
            'total' => $total,
        ];
    }

    private function xlsxWorksheet(array $headers, array $rows): string
    {
        $allRows = [$headers, ...$rows];
        $xmlRows = collect($allRows)->map(function (array $row, int $rowIndex): string {
            $cells = collect($row)->map(function (string $value, int $columnIndex) use ($rowIndex): string {
                $column = chr(65 + $columnIndex);
                $style = $rowIndex === 0 ? ' s="1"' : '';

                return '<c r="'.$column.($rowIndex + 1).'" t="inlineStr"'.$style.'><is><t>'.htmlspecialchars($value, ENT_XML1 | ENT_QUOTES, 'UTF-8').'</t></is></c>';
            })->implode('');

            return '<row r="'.($rowIndex + 1).'">'.$cells.'</row>';
        })->implode('');

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetData>'.$xmlRows.'</sheetData></worksheet>';
    }

    private function xlsxContentTypes(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/><Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/><Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/></Types>';
    }

    private function xlsxRelationships(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/></Relationships>';
    }

    private function xlsxWorkbook(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheets><sheet name="Monitoring Chatbot" sheetId="1" r:id="rId1"/></sheets></workbook>';
    }

    private function xlsxWorkbookRelationships(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/></Relationships>';
    }

    private function questionTokens(string $question): array
    {
        $normalized = mb_strtolower($question);
        $normalized = preg_replace('/[^\p{L}\p{N}\s]/u', ' ', $normalized) ?? '';
        $stopWords = ['ada', 'adalah', 'agar', 'akan', 'atau', 'bagaimana', 'bagi', 'bisa', 'dalam', 'dan', 'dari', 'dengan', 'di', 'ini', 'itu', 'jika', 'ke', 'kapan', 'kenapa', 'mana', 'mohon', 'pada', 'saya', 'sebuah', 'sudah', 'tentang', 'untuk', 'yang'];

        return collect(preg_split('/\s+/u', trim($normalized)) ?: [])
            ->filter(fn (string $token): bool => mb_strlen($token) >= 3 && ! in_array($token, $stopWords, true))
            ->unique()
            ->values()
            ->all();
    }
}

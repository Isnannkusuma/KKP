<?php

namespace App\Http\Controllers;

use App\Models\FaqEntry;
use App\Models\FaqSourceDocument;
use App\Services\Faq\FaqDocumentImporter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class FaqReviewController extends Controller
{
    public function index(Request $request): Response
    {
        $status = $request->string('status', 'draft')->toString();
        $status = in_array($status, ['draft', 'approved', 'rejected'], true) ? $status : 'draft';

        $entries = FaqEntry::query()
            ->with('sourceDocument:id,name')
            ->where('status', $status)
            ->orderByDesc('source_count')
            ->orderByDesc('created_at')
            ->paginate(10)
            ->withQueryString()
            ->through(fn (FaqEntry $entry): array => [
                'id' => $entry->id,
                'question' => $entry->question,
                'answer' => $entry->answer,
                'sourceCount' => $entry->source_count,
                'source' => $entry->source,
                'sourceDocumentName' => $entry->sourceDocument?->name,
                'status' => $entry->status,
                'createdAt' => $entry->created_at?->toIso8601String(),
                'approvedAt' => $entry->approved_at?->toIso8601String(),
                'approvedBy' => $entry->approved_by,
            ]);

        $documents = FaqSourceDocument::query()
            ->whereIn('file_type', ['txt', 'pdf', 'xlsx', 'xls', 'csv'])
            ->latest()
            ->get(['id', 'name', 'file_type', 'status', 'faq_count', 'error_message', 'created_at'])
            ->map(fn (FaqSourceDocument $document): array => [
                'id' => $document->id,
                'name' => $document->name,
                'fileType' => $document->file_type,
                'status' => $document->status,
                'faqCount' => $document->faq_count,
                'errorMessage' => $document->error_message,
                'createdAt' => $document->created_at?->toIso8601String(),
            ]);

        return Inertia::render('faq-review', [
            'status' => $status,
            'documents' => $documents,
            'entries' => $entries,
            'counts' => [
                'draft' => FaqEntry::where('status', 'draft')->count(),
                'approved' => FaqEntry::where('status', 'approved')->count(),
                'rejected' => FaqEntry::where('status', 'rejected')->count(),
            ],
        ]);
    }

    public function upload(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'document' => 'required|file|mimes:txt,pdf,xlsx,xls,csv|max:51200',
        ]);

        $file = $validated['document'];
        $fileName = time().'_'.preg_replace('/[^a-zA-Z0-9._-]/', '_', $file->getClientOriginalName());

        try {
            $path = $file->storeAs('faq-sources', $fileName, 'public');
            $document = FaqSourceDocument::query()->create([
                'id' => (string) Str::uuid(),
                'user_id' => $request->user()?->id,
                'name' => $file->getClientOriginalName(),
                'file_path' => $path,
                'file_type' => strtolower($file->getClientOriginalExtension()),
                'file_size' => $file->getSize(),
                'status' => 'pending',
            ]);

            $stats = $this->extractFaqDocument($document);

            return response()->json([
                'document' => $this->documentPayload($document->fresh()),
                'stats' => $stats,
            ]);
        } catch (\Throwable $exception) {
            if (isset($document)) {
                $document->faqEntries()->delete();
                $document->delete();
            }

            if (isset($path)) {
                Storage::disk('public')->delete($path);
            }

            report($exception);

            return response()->json(['message' => 'Dokumen FAQ gagal diunggah dan diekstrak.'], 500);
        }
    }

    public function extract(FaqSourceDocument $document): JsonResponse
    {
        $stats = $this->extractFaqDocument($document);

        return response()->json([
            'document' => $this->documentPayload($document->fresh()),
            'message' => "Ekstraksi selesai: {$stats['created']} FAQ dibuat, {$stats['duplicates']} duplikat dilewati.",
            'stats' => $stats,
        ]);
    }

    public function destroySource(FaqSourceDocument $document): JsonResponse
    {
        $document->faqEntries()->delete();
        Storage::disk('public')->delete($document->file_path);
        $document->delete();

        return response()->json(['message' => 'Dokumen FAQ dan FAQ hasil ekstraksinya dihapus.']);
    }

    private function extractFaqDocument(FaqSourceDocument $document): array
    {
        $pythonUrl = rtrim((string) config('services.python_microservice.url', 'http://python-ai:8000'), '/');
        $absolutePath = Storage::disk('public')->path($document->file_path);

        if (! Storage::disk('public')->exists($document->file_path)) {
            abort(404, 'File dokumen tidak ditemukan.');
        }

        try {
            $response = Http::timeout(300)
                ->attach('file', fopen($absolutePath, 'r'), $document->name)
                ->post("{$pythonUrl}/extract");
        } catch (\Throwable $exception) {
            report($exception);
            abort(503, 'Layanan ekstraksi tidak dapat dijangkau.');
        }

        if (! $response->successful()) {
            $document->update([
                'status' => 'failed',
                'error_message' => 'Dokumen tidak dapat diekstrak menjadi pasangan FAQ.',
            ]);
            abort(422, 'Dokumen tidak dapat diekstrak menjadi pasangan FAQ.');
        }

        $document->update(['status' => 'extracting', 'error_message' => null]);

        return app(FaqDocumentImporter::class)->import(
            $document,
            $response->json('qa_pairs', []),
            $pythonUrl,
        );
    }

    private function documentPayload(FaqSourceDocument $document): array
    {
        return [
            'id' => $document->id,
            'name' => $document->name,
            'fileType' => $document->file_type,
            'status' => $document->status,
            'faqCount' => $document->faq_count,
            'errorMessage' => $document->error_message,
            'createdAt' => $document->created_at?->toIso8601String(),
        ];
    }

    /**
     * Update isi pertanyaan/jawaban FAQ (dipakai admin untuk mengoreksi
     * hasil generate LLM sebelum di-approve).
     */
    public function update(Request $request, FaqEntry $faqEntry): RedirectResponse
    {
        $validated = $request->validate([
            'question' => 'required|string|max:2000',
            'answer' => 'required|string|max:5000',
        ]);

        $faqEntry->update($validated);

        return back()->with('success', 'FAQ berhasil diperbarui.');
    }

    public function approve(Request $request, FaqEntry $faqEntry): RedirectResponse
    {
        $faqEntry->update([
            'status' => 'approved',
            'approved_at' => now(),
            'approved_by' => $request->user()?->name,
        ]);

        return back()->with('success', 'FAQ disetujui dan siap dipakai chatbot.');
    }

    public function reject(FaqEntry $faqEntry): RedirectResponse
    {
        $faqEntry->update(['status' => 'rejected']);

        return back()->with('success', 'FAQ ditolak.');
    }

    public function destroy(FaqEntry $faqEntry): RedirectResponse
    {
        $faqEntry->delete();

        return back()->with('success', 'FAQ dihapus permanen.');
    }
}

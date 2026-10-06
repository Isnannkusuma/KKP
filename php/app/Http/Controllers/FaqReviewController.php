<?php

namespace App\Http\Controllers;

use App\Models\FaqEntry;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class FaqReviewController extends Controller
{
    public function index(Request $request): Response
    {
        $status = $request->string('status', 'draft')->toString();
        $status = in_array($status, ['draft', 'approved', 'rejected'], true) ? $status : 'draft';

        $entries = FaqEntry::query()
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
                'status' => $entry->status,
                'createdAt' => $entry->created_at?->toIso8601String(),
                'approvedAt' => $entry->approved_at?->toIso8601String(),
                'approvedBy' => $entry->approved_by,
            ]);

        return Inertia::render('faq-review', [
            'status' => $status,
            'entries' => $entries,
            'counts' => [
                'draft' => FaqEntry::where('status', 'draft')->count(),
                'approved' => FaqEntry::where('status', 'approved')->count(),
                'rejected' => FaqEntry::where('status', 'rejected')->count(),
            ],
        ]);
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

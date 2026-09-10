<?php

namespace App\Http\Controllers;

use App\Models\Document;
use App\Models\Category;
use App\Jobs\ProcessDocumentIngest; // ← Import Job baru
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Inertia;

class DocumentController extends Controller
{
    public function index(Request $request)
    {
        $query = Document::with('category');

        if ($request->filled('category_id')) {
            $query->where('category_id', $request->category_id);
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('search')) {
            $query->where('name', 'like', '%' . $request->search . '%');
        }

        $documents = $query->latest()->paginate(10);
        $categories = Category::all();

        $stats = [
            'total' => Document::count(),
            'ready' => Document::where('status', 'ready')->count(),
            'processing' => Document::whereIn('status', ['pending', 'extracting', 'cleaning', 'embedding'])->count(),
            'failed' => Document::where('status', 'failed')->count(),
        ];

        return Inertia::render('document/index', [
            'documents' => $documents,
            'categories' => $categories,
            'stats' => $stats,
            'filters' => $request->only(['status', 'search', 'category_id']),
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'document' => 'required|file|mimes:pdf,docx,xlsx,xls|max:51200',
            'category_id' => 'required|exists:categories,id',
            'access_level' => 'required|in:public,internal,confidential',
        ]);

        try {
            $file = $request->file('document');
            $fileName = time() . '_' . preg_replace('/[^a-zA-Z0-9._-]/', '_', $file->getClientOriginalName());
            $path = $file->storeAs('documents', $fileName, 'public');

            $document = Document::create([
                'id' => (string) Str::uuid(),
                'user_id' => auth()->id(),
                'name' => $file->getClientOriginalName(),
                'file_path' => $path,
                'file_type' => strtolower($file->getClientOriginalExtension()),
                'file_size' => $file->getSize(),
                'category_id' => $request->category_id,
                'access_level' => $request->access_level,
                'status' => 'pending',
                'total_chunks' => 0,
            ]);

            // ✅ Dispatch ke Queue (tidak akan pending lagi!)
            ProcessDocumentIngest::dispatch($document);

            return redirect()->route('dokumen.index')
                ->with('success', 'Dokumen berhasil diunggah dan sedang diproses oleh AI di background.');
        } catch (\Exception $e) {
            if (isset($path)) {
                Storage::disk('public')->delete($path);
            }

            return back()->withErrors([
                'document' => 'Gagal mengunggah file: ' . $e->getMessage()
            ]);
        }
    }

    public function update(Request $request, Document $document)
    {
        $request->validate([
            'document' => 'sometimes|file|mimes:pdf,docx,xlsx,xls|max:51200',
            'category_id' => 'sometimes|exists:categories,id',
            'access_level' => 'sometimes|in:public,internal,confidential',
        ]);

        try {
            $isFileChanged = false;

            if ($request->filled('category_id')) {
                $document->category_id = $request->category_id;
            }
            if ($request->filled('access_level')) {
                $document->access_level = $request->access_level;
            }

            if ($request->hasFile('document')) {
                $file = $request->file('document');

                if (Storage::disk('public')->exists($document->file_path)) {
                    Storage::disk('public')->delete($document->file_path);
                }

                $fileName = time() . '_' . preg_replace('/[^a-zA-Z0-9._-]/', '_', $file->getClientOriginalName());
                $path = $file->storeAs('documents', $fileName, 'public');

                $document->name = $file->getClientOriginalName();
                $document->file_path = $path;
                $document->file_type = strtolower($file->getClientOriginalExtension());
                $document->file_size = $file->getSize();
                $document->status = 'pending';
                $document->total_chunks = 0;
                $document->error_message = null;

                $isFileChanged = true;
            }

            $document->save();

            // ✅ Dispatch ke Queue jika file berubah
            if ($isFileChanged) {
                ProcessDocumentIngest::dispatch($document);
            }

            return redirect()->route('dokumen.index')
                ->with('success', $isFileChanged ? 'Dokumen diperbarui dan sedang diproses ulang.' : 'Metadata dokumen berhasil diperbarui.');
        } catch (\Exception $e) {
            return back()->withErrors([
                'error' => 'Gagal memperbarui dokumen: ' . $e->getMessage()
            ]);
        }
    }

    public function show(Document $document)
    {
        return Inertia::render('document/show', [
            'document' => $document,
        ]);
    }

    public function destroy(Document $document)
    {
        if ($document->user_id && $document->user_id !== auth()->id()) {
            abort(403, 'Anda tidak memiliki akses untuk menghapus dokumen ini.');
        }

        try {
            if (Storage::disk('public')->exists($document->file_path)) {
                Storage::disk('public')->delete($document->file_path);
            }

            $document->delete();

            return redirect()->route('dokumen.index')
                ->with('success', 'Dokumen dan seluruh data vektornya berhasil dihapus.');
        } catch (\Exception $e) {
            return back()->withErrors([
                'error' => 'Gagal menghapus dokumen: ' . $e->getMessage()
            ]);
        }
    }

    public function retry(Document $document)
    {
        if ($document->status !== 'failed') {
            return back()->withErrors([
                'error' => 'Hanya dokumen yang gagal diproses yang bisa di-retry.'
            ]);
        }

        try {
            $document->update([
                'status' => 'pending',
                'error_message' => null,
            ]);

            // ✅ Dispatch ulang ke Queue
            ProcessDocumentIngest::dispatch($document);

            return redirect()->route('dokumen.index')
                ->with('success', 'Dokumen sedang diproses ulang di background.');
        } catch (\Exception $e) {
            return back()->withErrors([
                'error' => 'Gagal memulai ulang proses: ' . $e->getMessage()
            ]);
        }
    }

    public function download(Document $document)
    {
        if (!Storage::disk('public')->exists($document->file_path)) {
            abort(404, 'File tidak ditemukan.');
        }

        return Storage::disk('public')->download(
            $document->file_path,
            $document->name
        );
    }
}

<?php

namespace App\Http\Controllers;

use App\Models\ApiService;
use App\Services\ApiServiceRunner;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

class ApiServiceController extends Controller
{
    public function index(): InertiaResponse
    {
        $services = ApiService::latest()->get()->map(function (ApiService $service): array {
            return [
                'id' => $service->id,
                'name' => $service->name,
                'description' => $service->description,
                'method' => $service->method,
                'url' => $service->url,
                'headers' => collect($service->headers ?? [])->mapWithKeys(
                    fn (string $value, string $key): array => [$key => '[tersimpan]']
                )->all(),
                'query_params' => $service->query_params ?? [],
                'body' => $service->body ?? [],
                'request_schema' => $service->request_schema ?? [],
                'response_fields' => $service->response_fields ?? [],
                'is_active' => $service->is_active,
            ];
        });

        return Inertia::render('api-service/index', ['services' => $services]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $this->validated($request);
        $validated['id'] = (string) Str::uuid();
        ApiService::create($validated);

        return to_route('api-services.index')->with('success', 'Service API berhasil ditambahkan.');
    }

    public function update(Request $request, ApiService $apiService): RedirectResponse
    {
        $validated = $this->validated($request);
        $validated['headers'] = collect($validated['headers'])->mapWithKeys(
            fn (string $value, string $key): array => [
                $key => $value === '[tersimpan]'
                    ? ($apiService->headers[$key] ?? $value)
                    : $value,
            ]
        )->all();
        $apiService->update($validated);

        return to_route('api-services.index')->with('success', 'Service API berhasil diperbarui.');
    }

    public function destroy(ApiService $apiService): RedirectResponse
    {
        $apiService->delete();

        return to_route('api-services.index')->with('success', 'Service API berhasil dihapus.');
    }

    public function test(Request $request, ApiService $apiService, ApiServiceRunner $runner): JsonResponse
    {
        abort_unless($apiService->is_active, 422, 'Service API sedang tidak aktif.');

        $validated = $request->validate([
            'input_values' => 'nullable|array',
        ]);

        try {
            return response()->json($runner->run($apiService, $validated['input_values'] ?? []));
        } catch (\Throwable $exception) {
            report($exception);

            return response()->json([
                'message' => 'Service gagal dipanggil. Periksa URL, input, dan kredensialnya.',
            ], 422);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request): array
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string|max:1000',
            'method' => 'required|in:GET,POST,PUT,PATCH,DELETE',
            'url' => ['required', 'string', 'max:2000', 'regex:/^https?:\/\/\S+$/i'],
            'headers' => 'nullable|array',
            'headers.*' => 'string|max:2000',
            'query_params' => 'nullable|array',
            'body' => 'nullable|array',
            'request_schema' => 'nullable|array',
            'request_schema.*.name' => 'required|string|max:100',
            'request_schema.*.type' => 'required|in:string,number,boolean',
            'request_schema.*.required' => 'boolean',
            'request_schema.*.description' => 'nullable|string|max:500',
            'response_fields' => 'nullable|array',
            'response_fields.*.name' => 'required|string|max:100',
            'response_fields.*.path' => 'required|string|max:500',
            'is_active' => 'boolean',
        ]);

        $validated['headers'] = $validated['headers'] ?? [];
        $validated['query_params'] = $validated['query_params'] ?? [];
        $validated['body'] = $validated['body'] ?? [];
        $validated['request_schema'] = $validated['request_schema'] ?? [];
        $validated['response_fields'] = $validated['response_fields'] ?? [];
        $validated['is_active'] = $validated['is_active'] ?? true;

        return $validated;
    }
}

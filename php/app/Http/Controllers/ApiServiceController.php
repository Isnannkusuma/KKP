<?php

namespace App\Http\Controllers;

use App\Models\ApiService;
use App\Services\ApiServiceRunner;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
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
                'inspect_url' => $service->inspect_url,
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

    public function inspect(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'method' => 'required|in:GET,POST,PUT,PATCH,DELETE',
            'url' => ['required', 'string', 'max:2000', 'regex:/^https?:\/\/\S+$/i'],
            'inspect_url' => ['nullable', 'string', 'max:2000', 'regex:/^https?:\/\/\S+$/i'],
            'headers' => 'nullable|array',
            'headers.*' => 'string|max:2000',
            'query_params' => 'nullable|array',
            'body' => 'nullable|array',
            'sample_values' => 'nullable|array',
        ]);

        $http = Http::connectTimeout(5)
            ->timeout(20)
            ->acceptJson()
            ->withHeaders($validated['headers'] ?? []);

        $inspectUrl = $validated['inspect_url'] ?? $validated['url'];
        $requestUrl = $this->replaceValues($inspectUrl, $validated['sample_values'] ?? []);
        $requestQuery = $this->replaceValues($validated['query_params'] ?? [], $validated['sample_values'] ?? []);
        $requestBody = $this->replaceValues($validated['body'] ?? [], $validated['sample_values'] ?? []);

        try {
            $response = match (strtoupper($validated['method'])) {
                'GET' => $http->get($requestUrl, $requestQuery),
                'POST' => $http->post($requestUrl, $requestBody),
                'PUT' => $http->put($requestUrl, $requestBody),
                'PATCH' => $http->patch($requestUrl, $requestBody),
                'DELETE' => $http->delete($requestUrl, $requestBody),
            };
        } catch (\Throwable $exception) {
            report($exception);

            return response()->json([
                'message' => 'Endpoint gagal diakses. Periksa URL, method, dan autentikasi.',
            ], 422);
        }

        if (! $response->successful()) {
            return response()->json([
                'status' => $response->status(),
                'message' => 'Endpoint mengembalikan status '.$response->status().' dan tidak dapat dipindai.',
                'body' => $response->json() ?? $response->body(),
            ], $response->status() >= 400 ? $response->status() : 422);
        }

        $payload = $response->json();
        $source = $this->normalizeInspectSource($payload);
        $fields = $this->deduplicateFields($this->flattenResponseFields($source, 'data'));

        return response()->json([
            'fields' => $fields,
            'count' => count($fields),
        ]);
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
            'inspect_url' => ['nullable', 'string', 'max:2000', 'regex:/^https?:\/\/\S+$/i'],
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
        $validated['inspect_url'] = $validated['inspect_url'] ?? null;
        $validated['query_params'] = $validated['query_params'] ?? [];
        $validated['body'] = $validated['body'] ?? [];
        $validated['request_schema'] = $validated['request_schema'] ?? [];
        $validated['response_fields'] = $validated['response_fields'] ?? [];
        $validated['is_active'] = $validated['is_active'] ?? true;

        return $validated;
    }

    /**
     * @param array<string, mixed> $data
     * @return array<int, array{name: string, path: string}>
     */
    private function flattenResponseFields(mixed $data, string $prefix = ''): array
    {
        if (! is_array($data)) {
            return [];
        }

        if (array_is_list($data)) {
            if (count($data) === 0) {
                return [];
            }

            if (is_array($data[0]) && ! array_is_list($data[0])) {
                return $this->flattenResponseFields($data[0], $prefix);
            }

            $fields = [];

            foreach ($data as $item) {
                if (is_array($item)) {
                    $fields = [...$fields, ...$this->flattenResponseFields($item, $prefix)];

                    continue;
                }

                $fields[] = [
                    'name' => $prefix === '' ? 'value' : (string) Str::afterLast($prefix, '.'),
                    'path' => $prefix,
                ];
            }

            return $fields;
        }

        $fields = [];

        foreach ($data as $key => $value) {
            $path = $prefix === '' ? (string) $key : $prefix.'.'.(string) $key;

            if (is_array($value) && ! array_is_list($value)) {
                $fields = [...$fields, ...$this->flattenResponseFields($value, $path)];

                continue;
            }

            if (is_array($value) && array_is_list($value)) {
                $fields = [...$fields, ...$this->flattenResponseFields($value, $path)];

                continue;
            }

            $fields[] = [
                'name' => (string) $key,
                'path' => $path,
            ];
        }

        return $fields;
    }

    /**
     * @param array<string, mixed> $payload
     * @return mixed
     */
    private function normalizeInspectSource(mixed $payload): mixed
    {
        if (! is_array($payload)) {
            return $payload;
        }

        $source = array_key_exists('data', $payload) ? $payload['data'] : $payload;

        if (! is_array($source) || ! array_is_list($source) || count($source) === 0 || ! is_array($source[0]) || array_is_list($source[0])) {
            return $source;
        }

        return $source[0];
    }

    /**
     * @param array<int, array{name: string, path: string}> $fields
     * @return array<int, array{name: string, path: string}>
     */
    private function deduplicateFields(array $fields): array
    {
        $unique = [];
        $seen = [];

        foreach ($fields as $field) {
            $path = $field['path'] ?? '';
            $name = $field['name'] ?? $path;
            $key = strtolower($path.'::'.$name);

            if (isset($seen[$key])) {
                continue;
            }

            $seen[$key] = true;
            $unique[] = [
                'name' => $name,
                'path' => $path,
            ];
        }

        return $unique;
    }

    private function replaceValues(mixed $value, array $inputValues): mixed
    {
        if (is_array($value)) {
            return array_map(fn (mixed $item): mixed => $this->replaceValues($item, $inputValues), $value);
        }

        if (! is_string($value)) {
            return $value;
        }

        return preg_replace_callback('/\{\{\s*([\w.-]+)\s*\}\}/', function (array $match) use ($inputValues): string {
            return (string) data_get($inputValues, $match[1], $match[0]);
        }, $value) ?? $value;
    }
}

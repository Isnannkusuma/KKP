<?php

namespace App\Services;

use App\Models\ApiService;
use Illuminate\Http\Client\Pool;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

class ApiServiceRunner
{
    /**
     * @param  array<string, mixed>  $inputValues
     * @return array{status: int, success: bool, data: mixed, selected: array<string, mixed>}
     */
    public function run(ApiService $service, array $inputValues = []): array
    {
        $request = $this->buildHttpRequest($service, $inputValues);
        $response = $this->sendRequest($service, $request);

        return $this->normalizeResult($service, $response);
    }

    /**
     * Jalankan beberapa service API sekaligus untuk pertanyaan kombinasi.
     *
     * @param  iterable<int, ApiService>  $services
     * @param  array<string, mixed>  $inputValues
     * @return array<int, array{status: int, success: bool, data: mixed, selected: array<string, mixed>, service_id: string|null}>
     */
    public function runMany(iterable $services, array $inputValues = []): array
    {
        $serviceList = collect($services)->values();

        if ($serviceList->isEmpty()) {
            return [];
        }

        try {
            $responses = Http::pool(function (Pool $pool) use ($serviceList, $inputValues) {
                return $serviceList->map(function (ApiService $service) use ($pool, $inputValues): mixed {
                    $request = $this->buildHttpRequest($service, $inputValues);

                    return match (strtoupper($service->method)) {
                        'GET' => $pool->connectTimeout(5)
                            ->timeout(20)
                            ->acceptJson()
                            ->withHeaders($request['headers'])
                            ->get($request['url'], $request['query']),
                        'POST' => $pool->connectTimeout(5)
                            ->timeout(20)
                            ->acceptJson()
                            ->withHeaders($request['headers'])
                            ->post($request['url'], $request['body']),
                        'PUT' => $pool->connectTimeout(5)
                            ->timeout(20)
                            ->acceptJson()
                            ->withHeaders($request['headers'])
                            ->put($request['url'], $request['body']),
                        'PATCH' => $pool->connectTimeout(5)
                            ->timeout(20)
                            ->acceptJson()
                            ->withHeaders($request['headers'])
                            ->patch($request['url'], $request['body']),
                        'DELETE' => $pool->connectTimeout(5)
                            ->timeout(20)
                            ->acceptJson()
                            ->withHeaders($request['headers'])
                            ->delete($request['url'], $request['body']),
                        default => throw new \InvalidArgumentException('Method HTTP tidak didukung.'),
                    };
                })->all();
            });
        } catch (\Throwable $exception) {
            report($exception);

            return $serviceList->map(fn (ApiService $service): array => [
                'service_id' => $service->id,
                'status' => 0,
                'success' => false,
                'data' => null,
                'selected' => [],
                'message' => 'Service gagal dipanggil secara paralel.',
            ])->all();
        }

        return $serviceList->map(function (ApiService $service, int $index) use ($responses): array {
            $response = $responses[$index] ?? null;

            return $this->normalizeResult($service, $response instanceof Response ? $response : null, 'Service gagal dipanggil secara paralel.');
        })->all();
    }

    /**
     * Alias semantik untuk skenario pertanyaan kombinasi.
     *
     * @param  iterable<int, ApiService>  $services
     * @param  array<string, mixed>  $inputValues
     * @return array<int, array{status: int, success: bool, data: mixed, selected: array<string, mixed>, service_id: string|null}>
     */
    public function runCombined(iterable $services, array $inputValues = []): array
    {
        return $this->runMany($services, $inputValues);
    }

    /**
     * @return array{url: string, headers: array<string, string>, query: array<string, mixed>, body: array<string, mixed>}
     */
    private function buildHttpRequest(ApiService $service, array $inputValues): array
    {
        $url = $this->replaceValues($service->url, $inputValues);
        $headers = $this->replaceValues($service->headers ?? [], $inputValues);
        $query = $this->replaceValues($service->query_params ?? [], $inputValues);
        $body = $this->replaceValues($service->body ?? [], $inputValues);

        return [
            'url' => $url,
            'headers' => is_array($headers) ? $headers : [],
            'query' => is_array($query) ? $query : [],
            'body' => is_array($body) ? $body : [],
        ];
    }

    private function sendRequest(ApiService $service, array $request): Response
    {
        $http = Http::connectTimeout(5)
            ->timeout(20)
            ->acceptJson()
            ->withHeaders($request['headers']);

        return match (strtoupper($service->method)) {
            'GET' => $http->get($request['url'], $request['query']),
            'POST' => $http->post($request['url'], $request['body']),
            'PUT' => $http->put($request['url'], $request['body']),
            'PATCH' => $http->patch($request['url'], $request['body']),
            'DELETE' => $http->delete($request['url'], $request['body']),
            default => throw new \InvalidArgumentException('Method HTTP tidak didukung.'),
        };
    }

    /**
     * @return array{status: int, success: bool, data: mixed, selected: array<string, mixed>, service_id: string|null, message?: string}
     */
    private function normalizeResult(ApiService $service, ?Response $response, string $fallbackMessage = 'Service gagal dipanggil.'): array
    {
        if (! $response instanceof Response) {
            return [
                'service_id' => $service->id,
                'status' => 0,
                'success' => false,
                'data' => null,
                'selected' => [],
                'message' => $fallbackMessage,
            ];
        }

        $data = $this->decodeResponse($response);

        return [
            'service_id' => $service->id,
            'status' => $response->status(),
            'success' => $response->successful(),
            'data' => $data,
            'selected' => $this->selectFields($data, $service->response_fields ?? []),
        ];
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

    private function decodeResponse(Response $response): mixed
    {
        $json = $response->json();

        return $json ?? $response->body();
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  array<int, array<string, string>>  $fields
     * @return array<string, mixed>
     */
    private function selectFields(mixed $data, array $fields): array
    {
        if (! is_array($data)) {
            return [];
        }

        $selected = [];
        foreach ($fields as $field) {
            $path = $field['path'] ?? null;
            if (is_string($path) && $path !== '') {
                $selected[$field['name'] ?? $path] = data_get($data, $path);
            }
        }

        return $selected;
    }
}

import { Head, router, usePage } from '@inertiajs/react';
import { Pencil, Play, Plus, Trash2, X } from 'lucide-react';
import { useState } from 'react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Textarea } from '@/components/ui/textarea';
import { dashboard } from '@/routes';
import type { ApiServiceItem } from '@/types';

interface Props {
    services: ApiServiceItem[];
}

interface FormState {
    name: string;
    description: string;
    method: string;
    url: string;
    inspect_url: string;
    headers: string;
    query_params: string;
    body: string;
    request_schema: string;
    response_fields: string;
    is_active: boolean;
}

const emptyForm: FormState = {
    name: '',
    description: '',
    method: 'GET',
    url: '',
    inspect_url: '',
    headers: '{}',
    query_params: '{}',
    body: '{}',
    request_schema: '[]',
    response_fields: '[]',
    is_active: true,
};

function formatJson(value: unknown): string {
    return JSON.stringify(value ?? {}, null, 2);
}

function serviceToForm(service: ApiServiceItem): FormState {
    return {
        name: service.name,
        description: service.description ?? '',
        method: service.method,
        url: service.url,
        inspect_url: service.inspect_url ?? '',
        headers: formatJson(service.headers),
        query_params: formatJson(service.query_params),
        body: formatJson(service.body),
        request_schema: formatJson(service.request_schema),
        response_fields: formatJson(service.response_fields),
        is_active: service.is_active,
    };
}

function parseJson(value: string, field: string): unknown {
    try {
        return JSON.parse(value);
    } catch {
        throw new Error(`${field} harus berisi JSON yang valid.`);
    }
}

export default function ApiServiceIndex({ services }: Props) {
    const { flash } = usePage().props as { flash?: { success?: string } };
    const [form, setForm] = useState<FormState>(emptyForm);
    const [editing, setEditing] = useState<ApiServiceItem | null>(null);
    const [error, setError] = useState('');
    const [testResult, setTestResult] = useState<string | null>(null);
    const [testInputs, setTestInputs] = useState('{}');
    const [testing, setTesting] = useState(false);
    const [detectedFields, setDetectedFields] = useState<
        Array<{ name: string; path: string; checked: boolean }>
    >([]);
    const [inspectLoading, setInspectLoading] = useState(false);

    const update = (key: keyof FormState, value: string | boolean) =>
        setForm((current) => ({ ...current, [key]: value }));

    const submit = (event: React.FormEvent) => {
        event.preventDefault();
        setError('');

        try {
            const payload: Record<string, unknown> = {
                ...form,
                inspect_url: form.inspect_url || null,
                headers: parseJson(form.headers, 'Headers'),
                query_params: parseJson(form.query_params, 'Query parameters'),
                body: parseJson(form.body, 'Body'),
                request_schema: parseJson(
                    form.request_schema,
                    'Input yang dibutuhkan',
                ),
                response_fields: parseJson(
                    form.response_fields,
                    'Field response',
                ),
            };
            const url = editing ? `/service-api/${editing.id}` : '/service-api';
            router.post(
                url,
                (editing ? { ...payload, _method: 'PUT' } : payload) as never,
                {
                    preserveScroll: true,
                    onSuccess: () => {
                        setEditing(null);
                        setForm(emptyForm);
                    },
                    onError: (errors) =>
                        setError(
                            Object.values(errors)[0] ?? 'Data belum valid.',
                        ),
                },
            );
        } catch (exception) {
            setError(
                exception instanceof Error
                    ? exception.message
                    : 'Format data tidak valid.',
            );
        }
    };

    const applySelectedFields = (fields: Array<{ name: string; path: string }>) => {
        const payload = fields.map(({ name, path }) => ({ name, path }));
        update('response_fields', JSON.stringify(payload, null, 2));
        setDetectedFields(
            fields.map((field) => ({
                ...field,
                checked: true,
            })),
        );
    };

    const inspectEndpoint = async () => {
        setInspectLoading(true);
        setError('');
        setDetectedFields([]);

        try {
            const headers = parseJson(form.headers, 'Headers');
            const queryParams = parseJson(form.query_params, 'Query parameters');
            const body = parseJson(form.body, 'Body');
            const requestSchema = parseJson(
                form.request_schema,
                'Input yang dibutuhkan',
            );

            if (
                typeof headers !== 'object' ||
                headers === null ||
                Array.isArray(headers)
            ) {
                throw new Error('Headers harus berupa object JSON.');
            }

            const sampleValues: Record<string, string | number | boolean> = {};
            if (Array.isArray(requestSchema)) {
                for (const field of requestSchema) {
                    if (field && typeof field === 'object' && 'name' in field) {
                        const value = field.type === 'number'
                            ? 1
                            : field.type === 'boolean'
                              ? true
                              : `sample_${String(field.name)}`;
                        sampleValues[String(field.name)] = value;
                    }
                }
            }

            if (form.url) {
                try {
                    const parsedUrl = new URL(form.url);
                    parsedUrl.searchParams.forEach((value, key) => {
                        if (!Object.hasOwn(sampleValues, key)) {
                            sampleValues[key] = value || `sample_${key}`;
                        }
                    });
                } catch {
                    // URL bisa berisi placeholder atau parameter tanpa protocol lengkap.
                }
            }

            if (queryParams && typeof queryParams === 'object' && !Array.isArray(queryParams)) {
                Object.entries(queryParams).forEach(([key, value]) => {
                    if (!Object.hasOwn(sampleValues, key) && typeof value === 'string') {
                        sampleValues[key] = value || `sample_${key}`;
                    }
                    if (!Object.hasOwn(sampleValues, key) && typeof value !== 'string') {
                        sampleValues[key] = typeof value === 'number' ? value : String(value ?? `sample_${key}`);
                    }
                });
            }

            const token =
                document
                    .querySelector('meta[name="csrf-token"]')
                    ?.getAttribute('content') ?? '';
            const inspectUrl = form.inspect_url || form.url;
            const response = await fetch('/service-api/inspect', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': token,
                    Accept: 'application/json',
                },
                body: JSON.stringify({
                    method: form.method,
                    url: form.url,
                    inspect_url: inspectUrl,
                    headers,
                    query_params: queryParams,
                    body,
                    sample_values: sampleValues,
                }),
            });

            const result = await response.json();

            if (!response.ok) {
                const detail = result.body && typeof result.body === 'object'
                    ? JSON.stringify(result.body, null, 2)
                    : result.body ?? result.message ?? 'Gagal mengambil field dari endpoint.';
                throw new Error(
                    result.message
                        ? `${result.message}${detail && detail !== 'undefined' ? `\n\nDetail: ${detail}` : ''}`
                        : 'Gagal mengambil field dari endpoint.',
                );
            }

            const fields = Array.isArray(result.fields)
                ? (result.fields as Array<{ name: string; path: string }>).reduce<
                      Array<{ name: string; path: string; checked: boolean }>
                  >(
                      (
                          accumulator: Array<{
                              name: string;
                              path: string;
                              checked: boolean;
                          }>,
                          field: { name: string; path: string },
                      ) => {
                          const normalized: {
                              name: string;
                              path: string;
                              checked: boolean;
                          } = {
                              name: String(field.name),
                              path: String(field.path),
                              checked: true,
                          };

                          const exists = accumulator.some(
                              (item: {
                                  name: string;
                                  path: string;
                                  checked: boolean;
                              }) =>
                                  item.path === normalized.path &&
                                  item.name === normalized.name,
                          );

                          if (!exists) {
                              accumulator.push(normalized);
                          }

                          return accumulator;
                      },
                      [],
                  )
                : [];

            setDetectedFields(fields);
            update(
                'response_fields',
                JSON.stringify(
                    fields.map(({ name, path }) => ({ name, path })),
                    null,
                    2,
                ),
            );
        } catch (exception) {
            setError(
                exception instanceof Error
                    ? exception.message
                    : 'Gagal mengambil field dari endpoint.',
            );
        } finally {
            setInspectLoading(false);
        }
    };

    const toggleDetectedField = (index: number) => {
        setDetectedFields((current) => {
            const next = [...current];
            next[index] = { ...next[index], checked: !next[index].checked };
            const selected = next
                .filter((field) => field.checked)
                .map(({ name, path }) => ({ name, path }));
            update('response_fields', JSON.stringify(selected, null, 2));
            return next;
        });
    };

    const testService = async (service: ApiServiceItem) => {
        setTesting(true);
        setTestResult(null);

        try {
            const inputValues = parseJson(testInputs, 'Nilai input uji');

            if (
                typeof inputValues !== 'object' ||
                inputValues === null ||
                Array.isArray(inputValues)
            ) {
                throw new Error('Nilai input uji harus berupa object JSON.');
            }

            const token =
                document
                    .querySelector('meta[name="csrf-token"]')
                    ?.getAttribute('content') ?? '';
            const response = await fetch(`/service-api/${service.id}/test`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': token,
                    Accept: 'application/json',
                },
                body: JSON.stringify({ input_values: inputValues }),
            });
            setTestResult(JSON.stringify(await response.json(), null, 2));
        } catch (exception) {
            setTestResult(
                JSON.stringify(
                    {
                        message:
                            exception instanceof Error
                                ? exception.message
                                : 'Tidak dapat menjalankan service.',
                    },
                    null,
                    2,
                ),
            );
        } finally {
            setTesting(false);
        }
    };

    return (
        <>
            <Head title="Service API" />
            <div className="flex flex-1 flex-col gap-6 p-4 md:p-8">
                {flash?.success && (
                    <div className="rounded-md border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800">
                        {flash.success}
                    </div>
                )}
                <div>
                    <h1 className="text-2xl font-bold tracking-tight">
                        Service API
                    </h1>
                    <p className="text-muted-foreground">
                        Daftarkan API dari dashboard. Isi input, tentukan
                        request, lalu pilih data response yang ingin dipakai.
                    </p>
                </div>

                <form
                    onSubmit={submit}
                    className="grid gap-4 rounded-xl border bg-card p-5 shadow-sm"
                >
                    <div className="flex items-center justify-between">
                        <div>
                            <h2 className="font-semibold">
                                {editing
                                    ? 'Edit service'
                                    : 'Tambah service baru'}
                            </h2>
                            <p className="text-sm text-muted-foreground">
                                Placeholder seperti {'{{nip}}'} dapat digunakan
                                di URL, header, query, atau body.
                            </p>
                        </div>
                        {editing && (
                            <Button
                                type="button"
                                variant="ghost"
                                onClick={() => {
                                    setEditing(null);
                                    setForm(emptyForm);
                                }}
                            >
                                <X className="mr-2 size-4" /> Batal edit
                            </Button>
                        )}
                    </div>
                    <div className="grid gap-4 md:grid-cols-2">
                        <Input
                            aria-label="Nama service"
                            placeholder="Nama service"
                            value={form.name}
                            onChange={(e) => update('name', e.target.value)}
                        />
                        <div className="flex gap-2">
                            <select
                                className="h-10 rounded-md border bg-background px-3 text-sm"
                                value={form.method}
                                onChange={(e) =>
                                    update('method', e.target.value)
                                }
                            >
                                {['GET', 'POST', 'PUT', 'PATCH', 'DELETE'].map(
                                    (method) => (
                                        <option key={method}>{method}</option>
                                    ),
                                )}
                            </select>
                            <Input
                                className="flex-1"
                                placeholder="https://api.contoh.go.id/pegawai/{{nip}}"
                                value={form.url}
                                onChange={(e) => update('url', e.target.value)}
                            />
                        </div>
                    </div>
                    <div className="grid gap-4 md:grid-cols-2">
                        <div className="flex flex-col gap-2 md:col-span-2">
                            <label className="text-sm font-medium">
                                Endpoint untuk inspect field (opsional)
                            </label>
                            <Input
                                placeholder="https://sianter.kkp.go.id/api/pemetaan/detail?nip={{nip}}"
                                value={form.inspect_url}
                                onChange={(e) => update('inspect_url', e.target.value)}
                            />
                            <span className="text-xs text-muted-foreground">
                                Jika diisi, tombol “Ambil field dari endpoint” akan memakai URL ini untuk menelusuri struktur field. URL utama tetap dipakai saat service dijalankan di runtime.
                            </span>
                        </div>
                    </div>
                    <div className="flex items-center justify-between gap-3">
                        <span className="text-sm text-muted-foreground">
                            Untuk endpoint yang sudah berjalan, bisa ambil field secara otomatis lalu pilih field yang ingin dipakai.
                        </span>
                        <Button
                            type="button"
                            variant="outline"
                            onClick={() => void inspectEndpoint()}
                            disabled={inspectLoading || !form.url}
                        >
                            {inspectLoading ? 'Mengecek...' : 'Ambil field dari endpoint'}
                        </Button>
                    </div>
                    <Textarea
                        placeholder="Deskripsi service (opsional)"
                        value={form.description}
                        onChange={(e) => update('description', e.target.value)}
                        rows={2}
                    />
                    <div className="grid gap-4 lg:grid-cols-2">
                        <JsonEditor
                            label="Headers"
                            value={form.headers}
                            onChange={(value) => update('headers', value)}
                            example={'{"Authorization":"Bearer token"}'}
                        />
                        <JsonEditor
                            label="Query parameters"
                            value={form.query_params}
                            onChange={(value) => update('query_params', value)}
                            example={'{"nip":"{{nip}}"}'}
                        />
                        <JsonEditor
                            label="Body request"
                            value={form.body}
                            onChange={(value) => update('body', value)}
                            example={'{"nip":"{{nip}}"}'}
                        />
                        <JsonEditor
                            label="Input yang dibutuhkan"
                            value={form.request_schema}
                            onChange={(value) =>
                                update('request_schema', value)
                            }
                            example={
                                '[{"name":"nip","type":"string","required":true,"description":"NIP pegawai"}]'
                            }
                        />
                        <JsonEditor
                            label="Field response yang diambil"
                            value={form.response_fields}
                            onChange={(value) =>
                                update('response_fields', value)
                            }
                            example={
                                '[{"name":"nama","path":"data.pegawai.nama"}]'
                            }
                        />
                    </div>
                    {detectedFields.length > 0 && (
                        <div className="rounded-lg border bg-muted/30 p-3">
                            <div className="mb-3 flex items-center justify-between gap-3">
                                <p className="text-sm font-medium">
                                    Pilih field yang akan dipakai
                                </p>
                                <Button
                                    type="button"
                                    variant="ghost"
                                    size="sm"
                                    onClick={() =>
                                        applySelectedFields(
                                            detectedFields.map(
                                                (
                                                    field: {
                                                        name: string;
                                                        path: string;
                                                    },
                                                ) => ({
                                                    name: field.name,
                                                    path: field.path,
                                                }),
                                            ),
                                        )
                                    }
                                >
                                    Pilih semua
                                </Button>
                            </div>
                            <div className="grid gap-2 sm:grid-cols-2">
                                {detectedFields.map((field, index) => (
                                    <label
                                        key={`${field.path}-${index}`}
                                        className="flex cursor-pointer items-start gap-2 rounded-md border bg-background p-2 text-sm"
                                    >
                                        <input
                                            type="checkbox"
                                            checked={field.checked}
                                            onChange={() => toggleDetectedField(index)}
                                            className="mt-1"
                                        />
                                        <span className="min-w-0">
                                            <span className="block font-medium">
                                                {field.name}
                                            </span>
                                            <span className="block truncate text-xs text-muted-foreground">
                                                {field.path}
                                            </span>
                                        </span>
                                    </label>
                                ))}
                            </div>
                        </div>
                    )}
                    <label className="flex items-center gap-2 text-sm">
                        <input
                            type="checkbox"
                            checked={form.is_active}
                            onChange={(e) =>
                                update('is_active', e.target.checked)
                            }
                        />{' '}
                        Service aktif dan dapat diuji
                    </label>
                    {error && <p className="text-sm text-red-600">{error}</p>}
                    <Button type="submit" className="w-fit">
                        <Plus className="mr-2 size-4" />
                        {editing ? 'Simpan perubahan' : 'Simpan service'}
                    </Button>
                </form>

                <div className="grid gap-3">
                    <h2 className="font-semibold">
                        Service terdaftar ({services.length})
                    </h2>
                    <label className="grid max-w-xl gap-2 text-sm font-medium">
                        Nilai input uji (JSON)
                        <Textarea
                            className="min-h-20 font-mono text-xs"
                            value={testInputs}
                            onChange={(e) => setTestInputs(e.target.value)}
                            placeholder={'{"nip":"123456789"}'}
                        />
                        <span className="font-normal text-muted-foreground">
                            Nilai ini menggantikan placeholder seperti{' '}
                            {'{{nip}}'} saat tombol Uji digunakan.
                        </span>
                    </label>
                    {services.map((service) => (
                        <div
                            key={service.id}
                            className="flex flex-col gap-3 rounded-lg border p-4 md:flex-row md:items-center md:justify-between"
                        >
                            <div className="min-w-0">
                                <div className="flex items-center gap-2">
                                    <span className="rounded bg-muted px-2 py-1 font-mono text-xs">
                                        {service.method}
                                    </span>
                                    <strong>{service.name}</strong>
                                    <span
                                        className={
                                            service.is_active
                                                ? 'text-xs text-green-600'
                                                : 'text-xs text-muted-foreground'
                                        }
                                    >
                                        {service.is_active
                                            ? 'Aktif'
                                            : 'Nonaktif'}
                                    </span>
                                </div>
                                <p className="truncate font-mono text-xs text-muted-foreground">
                                    {service.url}
                                </p>
                                <p className="text-sm text-muted-foreground">
                                    Input:{' '}
                                    {service.request_schema
                                        .map((field) => field.name)
                                        .join(', ') || 'tidak ada'}{' '}
                                    · Output:{' '}
                                    {service.response_fields
                                        .map((field) => field.name)
                                        .join(', ') || 'seluruh response'}
                                </p>
                            </div>
                            <div className="flex shrink-0 gap-2">
                                <Button
                                    variant="outline"
                                    size="sm"
                                    onClick={() => void testService(service)}
                                    disabled={testing || !service.is_active}
                                >
                                    <Play className="mr-2 size-4" /> Uji
                                </Button>
                                <Button
                                    variant="outline"
                                    size="sm"
                                    onClick={() => {
                                        setEditing(service);
                                        setForm(serviceToForm(service));
                                    }}
                                >
                                    <Pencil className="mr-2 size-4" /> Edit
                                </Button>
                                <Button
                                    variant="destructive"
                                    size="sm"
                                    onClick={() => {
                                        if (
                                            window.confirm('Hapus service ini?')
                                        ) {
                                            router.delete(
                                                `/service-api/${service.id}`,
                                            );
                                        }
                                    }}
                                >
                                    <Trash2 className="mr-2 size-4" /> Hapus
                                </Button>
                            </div>
                        </div>
                    ))}
                </div>
                {testResult && (
                    <pre className="max-h-80 overflow-auto rounded-lg border bg-muted p-4 text-xs">
                        {testResult}
                    </pre>
                )}
            </div>
        </>
    );
}

function JsonEditor({
    label,
    value,
    onChange,
    example,
}: {
    label: string;
    value: string;
    onChange: (value: string) => void;
    example: string;
}) {
    return (
        <label className="grid gap-2 text-sm font-medium">
            {label}
            <Textarea
                className="min-h-28 font-mono text-xs"
                value={value}
                onChange={(e) => onChange(e.target.value)}
                placeholder={example}
            />
            <span className="font-normal text-muted-foreground">
                Contoh: {example}
            </span>
        </label>
    );
}

ApiServiceIndex.layout = {
    breadcrumbs: [{ title: 'Dashboard', href: dashboard() }],
};

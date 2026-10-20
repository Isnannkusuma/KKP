<?php

namespace App\Http\Controllers;

use App\Models\ChatbotSetting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class ChatbotSettingController extends Controller
{
    public function edit(): Response
    {
        $setting = ChatbotSetting::query()->first() ?? new ChatbotSetting;

        $options = collect(ChatbotSetting::OPTIONS)->map(fn ($def) => [
            'label' => $def['label'],
            'options' => collect($def['options'])->map(fn ($o, $value) => ['value' => $value, 'label' => $o[0]])->values(),
        ]);

        return Inertia::render('chatbot-settings', [
            'settings' => [
                'is_enabled' => $setting->is_enabled ?? true,
                'custom_instructions' => $setting->custom_instructions ?? '',
                'assistant_name' => $setting->assistant_name ?? '',
                'assistant_role' => $setting->assistant_role ?? '',
                'additional_info' => $setting->additional_info ?? '',
                'query_aliases' => collect($setting->query_aliases ?? [])
                    ->map(fn (string $expansion, string $shortcut) => "{$shortcut} = {$expansion}")
                    ->implode("\n"),
                ...collect(array_keys(ChatbotSetting::OPTIONS))
                    ->mapWithKeys(fn ($k) => [$k => $setting->{$k} ?? 'default'])->all(),
            ],
            'options' => $options,
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $rules = [
            'is_enabled' => ['required', 'boolean'],
            'custom_instructions' => ['nullable', 'string', 'max:3000'],
            'assistant_name' => ['nullable', 'string', 'max:100'],
            'assistant_role' => ['nullable', 'string', 'max:255'],
            'additional_info' => ['nullable', 'string', 'max:1000'],
            'query_aliases' => ['nullable', 'string', 'max:3000'],
        ];

        foreach (ChatbotSetting::OPTIONS as $key => $def) {
            $rules[$key] = ['required', Rule::in(array_keys($def['options']))];
        }

        $validator = Validator::make($request->all(), $rules);
        $aliases = [];
        $lines = preg_split('/\R/u', is_string($request->input('query_aliases'))
            ? $request->input('query_aliases')
            : '') ?: [];

        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }

            if (! preg_match('/^([\p{L}][\p{L}\p{N}_-]*)\s*=\s*(.+)$/u', $line, $matches)) {
                $validator->errors()->add('query_aliases', 'Gunakan format satu pasangan per baris: singkatan = kata lengkap.');

                continue;
            }

            $shortcut = mb_strtolower($matches[1]);
            $expansion = trim($matches[2]);

            if (mb_strlen($expansion) > 100 || ! preg_match('/[\p{L}\p{N}]/u', $expansion)) {
                $validator->errors()->add('query_aliases', 'Kata pengganti harus berisi 1–100 karakter.');

                continue;
            }

            if (isset($aliases[$shortcut])) {
                $validator->errors()->add('query_aliases', "Singkatan \"{$matches[1]}\" dicantumkan lebih dari sekali.");

                continue;
            }

            $aliases[$shortcut] = $expansion;
        }

        if (count($aliases) > 50) {
            $validator->errors()->add('query_aliases', 'Maksimal 50 pasangan singkatan dapat disimpan.');
        }

        $data = $validator->validate();
        $data['query_aliases'] = $aliases;

        $setting = ChatbotSetting::query()->first() ?? new ChatbotSetting;
        $setting->fill($data)->save();

        return back()->with('success', 'Personalisasi chatbot berhasil disimpan.');
    }
}

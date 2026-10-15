<?php

namespace App\Http\Controllers;

use App\Models\ChatbotSetting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
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
        ];

        foreach (ChatbotSetting::OPTIONS as $key => $def) {
            $rules[$key] = ['required', Rule::in(array_keys($def['options']))];
        }

        $data = $request->validate($rules);

        $setting = ChatbotSetting::query()->first() ?? new ChatbotSetting;
        $setting->fill($data)->save();

        return back()->with('success', 'Personalisasi chatbot berhasil disimpan.');
    }
}

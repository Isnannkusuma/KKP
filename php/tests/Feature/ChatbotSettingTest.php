<?php

use App\Models\ChatbotSetting;

it('builds no personalization when no settings exist', function () {
    expect(ChatbotSetting::promptSection())->toBe('');
});

it('builds prompt section from settings', function () {
    ChatbotSetting::create(['custom_instructions' => 'Jawab singkat', 'emoji' => 'kurang', 'assistant_name' => 'Kiki']);

    $section = ChatbotSetting::promptSection();

    expect($section)->toContain('Jawab singkat')->toContain('Kiki')->toContain('Jangan menggunakan emoji');
});

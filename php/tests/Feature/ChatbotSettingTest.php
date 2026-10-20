<?php

use App\Models\ChatbotSetting;
use App\Models\User;

it('builds no personalization when no settings exist', function () {
    expect(ChatbotSetting::promptSection())->toBe('');
});

it('builds prompt section from settings', function () {
    ChatbotSetting::create(['custom_instructions' => 'Jawab singkat', 'emoji' => 'kurang', 'assistant_name' => 'Kiki']);

    $section = ChatbotSetting::promptSection();

    expect($section)->toContain('Jawab singkat')->toContain('Kiki')->toContain('Jangan menggunakan emoji');
});

it('saves configured chat shortcuts from the chatbot settings page', function () {
    $admin = User::factory()->create(['role' => 'admin']);

    $this->actingAs($admin)
        ->put(route('chatbot-settings.update'), [
            'is_enabled' => true,
            'custom_instructions' => '',
            'assistant_name' => '',
            'assistant_role' => '',
            'additional_info' => '',
            'tone' => 'default',
            'warmth' => 'default',
            'enthusiasm' => 'default',
            'emoji' => 'default',
            'layout' => 'default',
            'headings_lists' => 'default',
            'query_aliases' => "bsk = besok\nkpn = kapan\nbrp = berapa",
        ])
        ->assertRedirect();

    expect(ChatbotSetting::query()->firstOrFail()->query_aliases)
        ->toBe(['bsk' => 'besok', 'kpn' => 'kapan', 'brp' => 'berapa']);
});

it('rejects duplicate chat shortcut definitions', function () {
    $admin = User::factory()->create(['role' => 'admin']);

    $this->actingAs($admin)
        ->from(route('chatbot-settings.edit'))
        ->put(route('chatbot-settings.update'), [
            'is_enabled' => true,
            'tone' => 'default',
            'warmth' => 'default',
            'enthusiasm' => 'default',
            'emoji' => 'default',
            'layout' => 'default',
            'headings_lists' => 'default',
            'query_aliases' => "bsk = besok\nbsk = lusa",
        ])
        ->assertSessionHasErrors('query_aliases');
});

<?php

use App\Models\SearchAuditLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

test('admin can view chatbot monitoring data', function () {
    $admin = User::factory()->create(['role' => 'admin']);

    SearchAuditLog::create([
        'user_type' => 'guest',
        'user_identifier' => '127.0.0.1',
        'query' => 'Bagaimana prosedur cuti?',
        'answer' => 'Silakan ajukan melalui sistem.',
        'response_status' => 'answered',
        'result_count' => 2,
        'access_level_used' => 'public',
    ]);
    SearchAuditLog::create([
        'user_type' => 'guest',
        'user_identifier' => '127.0.0.2',
        'query' => 'Bagaimana prosedur cuti?',
        'answer' => 'Silakan ajukan melalui sistem.',
        'response_status' => 'answered',
        'result_count' => 1,
        'access_level_used' => 'public',
    ]);
    SearchAuditLog::create([
        'user_type' => 'guest',
        'user_identifier' => '127.0.0.3',
        'query' => 'Kapan jadwal layanan baru?',
        'answer' => 'Maaf, pertanyaan tersebut berada di luar lingkup.',
        'response_status' => 'unanswered',
        'result_count' => 0,
        'access_level_used' => 'public',
    ]);

    $this->actingAs($admin)
        ->get(route('dashboard'))
        ->assertInertia(fn (Assert $page) => $page
            ->component('dashboard')
            ->where('stats.total', 3)
            ->where('stats.answered', 2)
            ->where('stats.unanswered', 1)
            ->where('contextGroups.data.0.context', 'Prosedur, Cuti')
            ->where('contextGroups.data.0.total', 2)
            ->where('contextGroups.data.0.questions.0.question', 'Bagaimana prosedur cuti?')
            ->where('popularQuestions.data.0.question', 'Bagaimana prosedur cuti?')
            ->where('popularQuestions.data.0.total', 2)
            ->where('unansweredQuestions.0.question', 'Kapan jadwal layanan baru?'),
        );
});

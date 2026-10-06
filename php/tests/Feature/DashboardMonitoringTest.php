<?php

use App\Models\SearchAuditLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
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
            ->where('unansweredQuestions.data.0.question', 'Kapan jadwal layanan baru?'),
        );
});

test('admin can answer an unanswered question and delete another', function () {
    Http::fake(['*/embed/queries' => Http::response(['embeddings' => [array_fill(0, 768, 0.1)]])]);
    $admin = User::factory()->create(['role' => 'admin']);
    $audit = SearchAuditLog::create([
        'user_type' => 'guest', 'user_identifier' => '127.0.0.3', 'query' => 'Kapan jadwal layanan baru?',
        'answer' => 'Maaf.', 'response_status' => 'unanswered', 'result_count' => 0, 'access_level_used' => 'public',
    ]);
    $other = SearchAuditLog::create([
        'user_type' => 'guest', 'user_identifier' => '127.0.0.4', 'query' => 'Hapus saya',
        'response_status' => 'unanswered', 'result_count' => 0, 'access_level_used' => 'public',
    ]);

    $this->actingAs($admin)->put(route('dashboard.audits.answer', $audit), ['answer' => 'Jadwal dibuka Senin.'])->assertRedirect();
    $this->actingAs($admin)->delete(route('dashboard.audits.destroy-one', $other))->assertRedirect();

    expect($audit->fresh()->response_status)->toBe('answered')
        ->and($audit->fresh()->answer)->toBe('Jadwal dibuka Senin.');
    $this->assertDatabaseHas('faq_entries', ['search_audit_log_id' => $audit->id, 'status' => 'approved', 'source' => 'manual']);
    $this->assertDatabaseMissing('search_audit_logs', ['id' => $other->id]);
});

test('admin can paginate unanswered questions', function () {
    $admin = User::factory()->create(['role' => 'admin']);

    foreach (range(1, 12) as $number) {
        SearchAuditLog::create([
            'user_type' => 'guest',
            'user_identifier' => "127.0.0.{$number}",
            'query' => "Pertanyaan {$number}",
            'response_status' => 'unanswered',
            'result_count' => 0,
            'access_level_used' => 'public',
        ]);
    }

    $this->actingAs($admin)
        ->get(route('dashboard', ['unanswered_page' => 2]))
        ->assertInertia(fn (Assert $page) => $page
            ->component('dashboard')
            ->where('unansweredQuestions.current_page', 2)
            ->where('unansweredQuestions.last_page', 2)
            ->where('unansweredQuestions.total', 12)
            ->has('unansweredQuestions.data', 2),
        );
});

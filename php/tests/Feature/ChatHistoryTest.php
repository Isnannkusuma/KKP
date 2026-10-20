<?php

use App\Models\ChatConversation;
use App\Models\User;

test('user can see and delete only their own conversations', function (): void {
    $user = User::factory()->create();
    $other = User::factory()->create();

    $mine = ChatConversation::create(['user_id' => $user->id, 'title' => 'Milik saya', 'last_message_at' => now()]);
    $mine->messages()->create(['role' => 'user', 'content' => 'halo']);
    $theirs = ChatConversation::create(['user_id' => $other->id, 'title' => 'Milik orang', 'last_message_at' => now()]);

    $this->actingAs($user)->get('/chat')
        ->assertInertia(fn ($page) => $page->has('conversations', 1)->where('conversations.0.id', $mine->id));

    $this->actingAs($user)->get("/chat/c/{$theirs->id}")->assertNotFound();
    $this->actingAs($user)->deleteJson("/chat/c/{$theirs->id}")->assertNotFound();

    $this->actingAs($user)->get("/chat/c/{$mine->id}")
        ->assertInertia(fn ($page) => $page->has('activeConversation.messages', 1));

    $this->actingAs($user)->deleteJson("/chat/c/{$mine->id}")->assertOk();

    $this->assertDatabaseMissing('chat_conversations', ['id' => $mine->id]);
    $this->assertDatabaseMissing('chat_messages', ['conversation_id' => $mine->id]);
    $this->assertDatabaseHas('chat_conversations', ['id' => $theirs->id]);
});

test('chat page exposes the authenticated role for admin navigation', function (): void {
    $admin = User::factory()->create(['role' => 'admin']);

    $this->actingAs($admin)->get('/chat')
        ->assertInertia(fn ($page) => $page
            ->where('user.role', 'admin'));
});

test('chat history retains complete-list formatting metadata', function (): void {
    $user = User::factory()->create();
    $conversation = ChatConversation::create([
        'user_id' => $user->id,
        'title' => 'Pegawai yang dilantik',
        'last_message_at' => now(),
    ]);
    $conversation->messages()->create([
        'role' => 'assistant',
        'content' => "Pegawai yang dilantik:\n1. Pegawai Satu",
        'meta' => ['complete_list' => true],
    ]);

    $this->actingAs($user)->get("/chat/c/{$conversation->id}")
        ->assertInertia(fn ($page) => $page
            ->where('activeConversation.messages.0.completeList', true));
});

test('guests cannot delete conversations', function (): void {
    $this->deleteJson('/chat/conversations')->assertUnauthorized();
});

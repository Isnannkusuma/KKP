<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class ChatbotSetting extends Model
{
    public const CACHE_KEY = 'chatbot_settings.current';

    /** Opsi dropdown: kunci => [label, instruksi untuk LLM]. 'default' tidak menambah instruksi. */
    public const OPTIONS = [
        'tone' => [
            'label' => 'Gaya dan nada dasar',
            'options' => [
                'default' => ['Default', null],
                'formal' => ['Formal', 'Gunakan gaya bahasa formal dan baku.'],
                'santai' => ['Santai', 'Gunakan gaya bahasa santai namun tetap sopan.'],
                'profesional' => ['Profesional', 'Gunakan gaya bahasa profesional, jelas, dan lugas.'],
                'ringkas' => ['Ringkas', 'Jawab sesingkat mungkin tanpa kehilangan inti informasi.'],
            ],
        ],
        'warmth' => [
            'label' => 'Kehangatan',
            'options' => [
                'default' => ['Default', null],
                'lebih' => ['Lebih hangat', 'Tunjukkan empati dan kehangatan dalam setiap jawaban.'],
                'kurang' => ['Kurang hangat', 'Bersikap netral dan to the point, tanpa basa-basi emosional.'],
            ],
        ],
        'enthusiasm' => [
            'label' => 'Antusiasme',
            'options' => [
                'default' => ['Default', null],
                'lebih' => ['Lebih antusias', 'Tunjukkan antusiasme dan semangat membantu.'],
                'kurang' => ['Kurang antusias', 'Bersikap tenang dan datar, hindari ungkapan yang berlebihan.'],
            ],
        ],
        'emoji' => [
            'label' => 'Emoji',
            'options' => [
                'default' => ['Default', null],
                'lebih' => ['Lebih sering', 'Boleh memakai emoji yang relevan secara moderat.'],
                'kurang' => ['Tanpa emoji', 'Jangan menggunakan emoji sama sekali.'],
            ],
        ],
        'layout' => [
            'label' => 'Tata letak dan visual',
            'options' => [
                'default' => ['Default', null],
                'paragraf' => ['Paragraf', 'Utamakan penyajian dalam paragraf pendek.'],
                'terstruktur' => ['Terstruktur', 'Utamakan penyajian terstruktur dengan jeda baris yang jelas antar bagian.'],
            ],
        ],
        'headings_lists' => [
            'label' => 'Judul dan daftar',
            'options' => [
                'default' => ['Default', null],
                'lebih' => ['Lebih banyak', 'Gunakan daftar bernomor atau poin dan penjudulan sederhana bila membantu keterbacaan.'],
                'kurang' => ['Lebih sedikit', 'Hindari daftar poin dan judul; tulis dalam kalimat mengalir.'],
            ],
        ],
    ];

    protected $fillable = [
        'is_enabled', 'custom_instructions', 'assistant_name', 'assistant_role', 'additional_info',
        'tone', 'warmth', 'enthusiasm', 'emoji', 'layout', 'headings_lists',
    ];

    protected $casts = ['is_enabled' => 'boolean'];

    public static function current(): self
    {
        $attributes = Cache::remember(self::CACHE_KEY, 300, fn () => static::query()->first()?->getAttributes() ?? []);

        return (new static)->forceFill($attributes);
    }

    protected static function booted(): void
    {
        static::saved(fn () => Cache::forget(self::CACHE_KEY));
    }

    /** Perkenalan tetap dari pengaturan admin, null bila nama asisten belum diisi / personalisasi nonaktif. */
    public static function assistantName(): ?string
    {
        try {
            if (! Schema::hasTable('chatbot_settings')) {
                return null;
            }

            $s = static::current();
        } catch (\Throwable) {
            return null;
        }

        return $s->is_enabled && filled($s->assistant_name) ? trim($s->assistant_name) : null;
    }

    public static function assistantIntroduction(?array $greeting = null): ?string
    {
        try {
            if (! Schema::hasTable('chatbot_settings')) {
                return null;
            }

            $s = static::current();
        } catch (\Throwable) {
            return null;
        }

        if (! $s->is_enabled || blank($s->assistant_name)) {
            return null;
        }

        $role = filled($s->assistant_role) ? ", {$s->assistant_role}" : '';

        $recipient = $greeting
            ? trim($greeting['salutation'].' '.($greeting['name'] !== 'pegawai' ? $greeting['name'] : ''))
            : 'Bapak/Ibu';

        return "Halo {$recipient}, saya {$s->assistant_name}{$role}. Ada yang bisa saya bantu?";
    }

    /**
     * Bagian system prompt hasil personalisasi admin. Selalu ditaruh setelah
     * aturan inti sehingga tidak dapat menggantikan lingkup layanan & keamanan.
     */
    public static function promptSection(bool $includeIdentity = true): string
    {
        try {
            if (! Schema::hasTable('chatbot_settings')) {
                return '';
            }

            $s = static::current();
        } catch (\Throwable) {
            return '';
        }

        if (! $s->is_enabled) {
            return '';
        }

        $lines = [];

        if ($includeIdentity && filled($s->assistant_name)) {
            $lines[] = "- NAMA ANDA adalah \"{$s->assistant_name}\". Saat memperkenalkan diri atau ditanya nama/identitas, selalu pakai nama ini dan jangan menyebut nama lain (termasuk nama yang muncul di dokumen).";
        }
        if ($includeIdentity && filled($s->assistant_role)) {
            $lines[] = "- Peran asisten: {$s->assistant_role}.";
        }

        foreach (self::OPTIONS as $key => $def) {
            $instruction = $def['options'][$s->{$key}][1] ?? null;
            if ($instruction) {
                $lines[] = '- '.$instruction;
            }
        }

        if (filled($s->additional_info)) {
            $lines[] = '- Informasi tambahan dari admin: '.trim($s->additional_info);
        }
        if (filled($s->custom_instructions)) {
            $lines[] = "- Instruksi khusus dari admin:\n".trim($s->custom_instructions);
        }

        if ($lines === []) {
            return '';
        }

        return "\n\nPERSONALISASI DARI ADMIN (berlaku untuk semua pengguna; hanya mengatur gaya dan penyajian, tidak boleh melanggar LINGKUP LAYANAN dan KEAMANAN di atas):\n"
            .implode("\n", $lines);
    }

    /**
     * Tulis ulang jawaban FAQ sesuai personalisasi admin tanpa mengubah faktanya.
     * Jika tidak ada pengaturan gaya atau LLM gagal, jawaban asli dikembalikan.
     */
    public static function personalizeAnswer(string $answer): string
    {
        $section = static::promptSection(false);
        $key = config('ai.providers.openai.key');

        if ($section === '' || blank($key) || trim($answer) === '') {
            return $answer;
        }

        $system = "Anda menulis ulang jawaban resmi instansi agar sesuai gaya yang diminta admin.\n"
            ."ATURAN: pertahankan semua fakta, angka, nama, tanggal, dan tautan persis seperti aslinya; jangan menambah atau mengurangi informasi; "
            ."hanya ubah gaya dan penyajian. Keluarkan hanya jawaban akhir.\n"
            .$section;

        try {
            $response = Http::withToken($key)->connectTimeout(5)->timeout(20)
                ->post('https://api.openai.com/v1/chat/completions', [
                    'model' => config('ai.providers.openai.model', 'gpt-6-luna'),
                    'messages' => [
                        ['role' => 'system', 'content' => $system],
                        ['role' => 'user', 'content' => $answer],
                    ],
                    'max_completion_tokens' => 2048,
                ]);

            $text = trim((string) $response->json('choices.0.message.content'));

            return $response->successful() && $text !== '' ? $text : $answer;
        } catch (\Throwable $e) {
            Log::warning('Gagal personalisasi jawaban FAQ', ['message' => $e->getMessage()]);

            return $answer;
        }
    }
}

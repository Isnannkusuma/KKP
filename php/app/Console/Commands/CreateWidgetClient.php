<?php

namespace App\Console\Commands;

use App\Models\WidgetClient;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class CreateWidgetClient extends Command 
{
    protected $signature = 'widget:create
        {name : Nama situs, misal "Portal SDMAO"}
        {origin* : Satu atau lebih domain yang diizinkan, misal https://portal.kkp.go.id}';

    protected $description = 'Buat widget client baru (widget_key + secret_key) untuk situs yang akan pasang widget chatbot';

    public function handle(): int
    {
        $name = $this->argument('name');
        $origins = $this->argument('origin');

        foreach ($origins as $origin) {
            if (! filter_var($origin, FILTER_VALIDATE_URL)) {
                $this->error("Origin tidak valid: {$origin}. Harus URL lengkap, misal https://portal.kkp.go.id");

                return self::FAILURE;
            }
        }

        $widgetKey = 'wk_'.Str::random(24);
        $secretKey = Str::random(48);

        $client = WidgetClient::create([
            'name' => $name,
            'widget_key' => $widgetKey,
            'secret_key' => $secretKey,
            'allowed_origins' => $origins,
            'is_active' => true,
        ]);

        $this->newLine();
        $this->info('✅ Widget client berhasil dibuat!');
        $this->newLine();
        $this->line('<comment>Simpan informasi ini dengan aman - secret_key TIDAK akan ditampilkan lagi.</comment>');
        $this->newLine();
        $this->table(
            ['Field', 'Value'],
            [
                ['Nama', $client->name],
                ['Widget Key (publik, dipasang di embed script)', $client->widget_key],
                ['Secret Key (RAHASIA, untuk sign token identitas di backend situs lain)', $client->secret_key],
                ['Allowed Origins', implode(', ', $client->allowed_origins)],
            ]
        );

        $this->newLine();
        $this->comment('Kode embed untuk dipasang di situs mereka:');
        $this->line("<script src=\"".config('app.url')."/widget/loader.js\" data-widget-key=\"{$client->widget_key}\" async></script>");

        return self::SUCCESS;
    }
}
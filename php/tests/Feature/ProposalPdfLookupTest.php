<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;

function insertProposalRecords(array $records): void
{
    $records = array_map(static function (array $record): array {
        $data = isset($record['raw_data'])
            ? json_decode($record['raw_data'], true, flags: JSON_THROW_ON_ERROR)
            : $record;
        $data['status'] ??= 'approved';
        if (! array_key_exists('verified_at', $data)) {
            $data['verified_at'] = '2026-10-01 00:00:00';
        }

        return $data;
    }, $records);

    DB::connection('proposal_source')->table('projf_proposal')->insert($records);
}

function insertJfRecommendationRecords(array $records): void
{
    DB::connection('proposal_source')->table('projf_manajemen_jf')->insert($records);
}

beforeEach(function () {
    config([
        'services.proposal_pdf.enabled' => true,
        'database.connections.proposal_source' => [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
            'foreign_key_constraints' => true,
        ],
    ]);

    DB::purge('proposal_source');

    Schema::connection('proposal_source')->create('projf_proposal', function ($table) {
        $table->id();
        $table->string('no_surat')->nullable();
        $table->date('tanggal_usulan')->nullable();
        $table->text('hal_usulan')->nullable();
        $table->string('kab_kota')->nullable();
        $table->string('provinsi')->nullable();
        $table->text('link_surat')->nullable();
        $table->string('status')->nullable();
        $table->timestamp('verified_at')->nullable();
    });

    Schema::connection('proposal_source')->create('projf_manajemen_jf', function ($table) {
        $table->id();
        $table->string('kab_kota')->nullable();
        $table->string('provinsi')->nullable();
        $table->string('keterangan')->nullable();
        $table->text('link_surat_rekom')->nullable();
    });
});

test('chatbot returns proposal PDF links for the matching kabupaten without calling RAG', function () {
    insertProposalRecords([
        [
            'raw_data' => json_encode([
                'no_surat' => '001/MAMUJU/2026',
                'tanggal_usulan' => '2026-10-01',
                'hal_usulan' => 'Usulan kegiatan Mamuju',
                'kab_kota' => 'Kabupaten Mamuju',
                'provinsi' => 'Sulawesi Barat',
                'link_surat' => 'https://files.example.test/mamuju.pdf',
            ]),
        ],
        [
            'raw_data' => json_encode([
                'no_surat' => '002/PASANGKAYU/2026',
                'tanggal_usulan' => '2026-10-02',
                'hal_usulan' => 'Usulan kegiatan Pasangkayu',
                'kab_kota' => 'Kabupaten Pasangkayu',
                'provinsi' => 'Sulawesi Barat',
                'link_surat' => 'https://files.example.test/pasangkayu.pdf',
            ]),
        ],
    ]);

    Http::fake();

    $response = $this->post(route('chat.process'), [
        'message' => 'Minta link PDF proposal Kabupaten Mamuju Provinsi Sulawesi Barat',
        'history' => [],
    ]);

    $response->assertOk();
    expect($response->json('answer'))
        ->toContain('https://files.example.test/mamuju.pdf')
        ->not->toContain('https://files.example.test/pasangkayu.pdf');

    Http::assertNothingSent();
});

test('chatbot recognizes pengusulan requests and matches the requested proposal topic', function () {
    insertProposalRecords([
        [
            'raw_data' => json_encode([
                'no_surat' => '001/SBG/2026',
                'tanggal_usulan' => '2026-10-03',
                'hal_usulan' => 'Pengusulan Kebutuhan Jabatan Fungsional',
                'kab_kota' => 'Kota Subang',
                'provinsi' => 'Jawa Barat',
                'link_surat' => 'https://files.example.test/subang-jabatan-fungsional.pdf',
            ]),
        ],
        [
            'raw_data' => json_encode([
                'no_surat' => '002/SBG/2026',
                'tanggal_usulan' => '2026-10-02',
                'hal_usulan' => 'Pengusulan Kebutuhan Pegawai',
                'kab_kota' => 'Kota Subang',
                'provinsi' => 'Jawa Barat',
                'link_surat' => 'https://files.example.test/subang-pegawai.pdf',
            ]),
        ],
    ]);

    Http::fake();

    $response = $this->post(route('chat.process'), [
        'message' => 'berikan saya link untuk file pdf terkait Pengusulan Kebutuhan Jabatan Fungsional kota subang',
        'history' => [],
    ]);

    $response->assertOk();
    expect($response->json('answer'))
        ->toContain('https://files.example.test/subang-jabatan-fungsional.pdf')
        ->not->toContain('https://files.example.test/subang-pegawai.pdf');

    Http::assertNothingSent();
});

test('chatbot recognizes proposal PDF searches for a city without requiring province', function () {
    insertProposalRecords([
        [
            'raw_data' => json_encode([
                'no_surat' => '001/TEGAL/2026',
                'tanggal_usulan' => '2026-10-03',
                'hal_usulan' => 'Pengusulan Kebutuhan Jabatan Fungsional',
                'kab_kota' => 'Kota Tegal',
                'provinsi' => 'Jawa Tengah',
                'link_surat' => 'https://files.example.test/tegal-jf.pdf',
            ]),
        ],
        [
            'raw_data' => json_encode([
                'no_surat' => '002/TEGAL/2026',
                'tanggal_usulan' => '2026-10-02',
                'hal_usulan' => 'Pengadaan sarana prasarana perkantoran',
                'kab_kota' => 'Kota Tegal',
                'provinsi' => 'Jawa Tengah',
                'link_surat' => 'https://files.example.test/tegal-sarana.pdf',
            ]),
        ],
        [
            'raw_data' => json_encode([
                'no_surat' => '003/TEGAL/2026',
                'tanggal_usulan' => '2026-10-01',
                'hal_usulan' => 'Pengusulan Kebutuhan Jabatan Fungsional',
                'kab_kota' => 'Kabupaten Tegal',
                'provinsi' => 'Jawa Tengah',
                'link_surat' => 'https://files.example.test/kabupaten-tegal-jf.pdf',
            ]),
        ],
        [
            'raw_data' => json_encode([
                'no_surat' => '004/TEGAL/2026',
                'tanggal_usulan' => '2026-10-04',
                'hal_usulan' => 'Pengusulan Kebutuhan Jabatan Fungsional',
                'kab_kota' => 'Kota Tegal',
                'provinsi' => 'Jawa Tengah',
                'link_surat' => 'https://files.example.test/tegal-rejected.pdf',
                'status' => 'rejected',
            ]),
        ],
        [
            'raw_data' => json_encode([
                'no_surat' => '005/TEGAL/2026',
                'tanggal_usulan' => '2026-10-05',
                'hal_usulan' => 'Pengusulan Kebutuhan Jabatan Fungsional',
                'kab_kota' => 'Kota Tegal',
                'provinsi' => 'Jawa Tengah',
                'link_surat' => 'https://files.example.test/tegal-unverified.pdf',
                'verified_at' => null,
            ]),
        ],
        [
            'raw_data' => json_encode([
                'no_surat' => '006/TEGAL/2026',
                'tanggal_usulan' => '2026-10-06',
                'hal_usulan' => 'Pengusulan Kebutuhan Jabatan Fungsional',
                'kab_kota' => 'Kota Tegal',
                'provinsi' => 'Jawa Tengah',
                'link_surat' => 'https://files.example.test/tegal-approved.pdf',
                'status' => 'approved',
                'verified_at' => null,
            ]),
        ],
    ]);

    Http::fake();

    $response = $this->post(route('chat.process'), [
        'message' => 'berikan saya link PDF proposal kebutuhan jf Kota Tegal',
        'history' => [],
    ]);

    $response->assertOk()
        ->assertJsonMissingPath('require_login');
    expect($response->json('answer'))
        ->toContain('https://files.example.test/tegal-jf.pdf')
        ->not->toContain('https://files.example.test/tegal-sarana.pdf')
        ->not->toContain('https://files.example.test/tegal-rejected.pdf')
        ->not->toContain('https://files.example.test/tegal-unverified.pdf')
        ->toContain('https://files.example.test/tegal-approved.pdf')
        ->not->toContain('https://files.example.test/kabupaten-tegal-jf.pdf');

    $countyResponse = $this->post(route('chat.process'), [
        'message' => 'berikan saya link PDF proposal kebutuhan jf Kabupaten Tegal',
        'history' => [],
    ]);

    $countyResponse->assertOk();
    expect($countyResponse->json('answer'))
        ->toContain('https://files.example.test/kabupaten-tegal-jf.pdf')
        ->not->toContain('https://files.example.test/tegal-jf.pdf');

    Http::assertNothingSent();
});

test('chatbot recognizes a region with no verified proposals without asking for the region again', function () {
    insertProposalRecords([
        [
            'kab_kota' => 'Kabupaten Wonosobo',
            'provinsi' => 'Jawa Tengah',
            'hal_usulan' => 'Pengusulan Kebutuhan Jabatan Fungsional',
            'link_surat' => 'https://files.example.test/wonosobo-unverified.pdf',
            'verified_at' => null,
        ],
    ]);

    Http::fake();

    $response = $this->post(route('chat.process'), [
        'message' => 'berikan saya link PDF proposal kebutuhan jf di kabupaten wonosobo',
        'history' => [],
    ]);

    $response->assertOk();
    expect($response->json('answer'))
        ->toContain('Belum ditemukan tautan PDF usulan yang sudah diverifikasi atau approved')
        ->not->toContain('Mohon sebutkan nama kota atau kabupaten')
        ->not->toContain('https://files.example.test/wonosobo-unverified.pdf');

    Http::assertNothingSent();
});

test('full jabatan fungsional recommendation search does not require personal-data login', function () {
    insertJfRecommendationRecords([
        [
            'kab_kota' => 'Kota Tegal',
            'provinsi' => 'Jawa Tengah',
            'keterangan' => 'Sudah ada rekomendasi',
            'link_surat_rekom' => 'https://files.example.test/tegal-jf.pdf',
        ],
    ]);

    Http::fake();

    $response = $this->post(route('chat.process'), [
        'message' => 'berikan surat rekomendasi JF dari Kota Tegal',
        'history' => [],
    ]);

    $response->assertOk()
        ->assertJsonMissingPath('require_login');
    expect($response->json('answer'))->toContain('https://files.example.test/tegal-jf.pdf');

    Http::assertNothingSent();
});

test('JF recommendation lookup returns all available letters in a requested province', function () {
    insertJfRecommendationRecords([
        [
            'kab_kota' => 'Kota Surakarta',
            'provinsi' => 'Jawa Tengah',
            'keterangan' => 'Sudah ada rekomendasi',
            'link_surat_rekom' => 'https://files.example.test/surakarta-jf.pdf',
        ],
        [
            'kab_kota' => 'Kabupaten Magelang',
            'provinsi' => 'Jawa Tengah',
            'keterangan' => 'Sudah ada rekomendasi',
            'link_surat_rekom' => 'https://files.example.test/magelang-jf.pdf',
        ],
        [
            'kab_kota' => 'Kota Semarang',
            'provinsi' => 'Jawa Tengah',
            'keterangan' => 'Belum ada rekomendasi',
            'link_surat_rekom' => null,
        ],
        [
            'kab_kota' => 'Kota Bandung',
            'provinsi' => 'Jawa Barat',
            'keterangan' => 'Sudah ada rekomendasi',
            'link_surat_rekom' => 'https://files.example.test/bandung-jf.pdf',
        ],
    ]);

    Http::fake();

    $response = $this->post(route('chat.process'), [
        'message' => 'Tampilkan surat rekomendasi JF Provinsi Jawa Tengah',
        'history' => [],
    ]);

    $response->assertOk()
        ->assertJsonMissingPath('require_login');
    expect($response->json('answer'))
        ->toContain('https://files.example.test/surakarta-jf.pdf')
        ->toContain('https://files.example.test/magelang-jf.pdf')
        ->not->toContain('https://files.example.test/bandung-jf.pdf');

    Http::assertNothingSent();
});

test('JF recommendation lookup returns only the requested city or county', function () {
    insertJfRecommendationRecords([
        [
            'kab_kota' => 'Kota Surakarta',
            'provinsi' => 'Jawa Tengah',
            'keterangan' => 'Sudah ada rekomendasi',
            'link_surat_rekom' => 'https://files.example.test/surakarta-jf.pdf',
        ],
        [
            'kab_kota' => 'Kabupaten Magelang',
            'provinsi' => 'Jawa Tengah',
            'keterangan' => 'Sudah ada rekomendasi',
            'link_surat_rekom' => 'https://files.example.test/magelang-jf.pdf',
        ],
    ]);

    Http::fake();

    $response = $this->post(route('chat.process'), [
        'message' => 'Berikan surat rekomendasi JF dari daerah Surakarta',
        'history' => [],
    ]);

    $response->assertOk()
        ->assertJsonMissingPath('require_login');
    expect($response->json('answer'))
        ->toContain('https://files.example.test/surakarta-jf.pdf')
        ->not->toContain('https://files.example.test/magelang-jf.pdf');

    Http::assertNothingSent();
});

test('JF recommendation lookup groups distinct letters for a city without repeating its name', function () {
    insertJfRecommendationRecords([
        [
            'kab_kota' => 'Kota Bontang',
            'provinsi' => 'KALIMANTAN SELATAN',
            'keterangan' => 'Sudah ada rekomendasi',
            'link_surat_rekom' => 'https://files.example.test/bontang-1.pdf',
        ],
        [
            'kab_kota' => 'Kota Bontang',
            'provinsi' => 'KALIMANTAN SELATAN',
            'keterangan' => 'Sudah ada rekomendasi',
            'link_surat_rekom' => 'https://files.example.test/bontang-2.pdf',
        ],
        [
            'kab_kota' => 'Kota Bontang',
            'provinsi' => 'KALIMANTAN TENGAH',
            'keterangan' => 'Sudah ada rekomendasi',
            'link_surat_rekom' => 'https://files.example.test/bontang-3.pdf',
        ],
        [
            'kab_kota' => 'Kota Bontang',
            'provinsi' => 'KALIMANTAN SELATAN',
            'keterangan' => 'Sudah ada rekomendasi',
            'link_surat_rekom' => 'https://files.example.test/bontang-1.pdf',
        ],
    ]);

    Http::fake();

    $response = $this->post(route('chat.process'), [
        'message' => 'Berikan surat rekomendasi JF dari Kota Bontang',
        'history' => [],
    ]);

    $response->assertOk();
    $answer = $response->json('answer');

    expect(substr_count($answer, 'Kota Bontang'))
        ->toBe(1)
        ->and($answer)
        ->toContain('https://files.example.test/bontang-1.pdf')
        ->toContain('https://files.example.test/bontang-2.pdf')
        ->toContain('https://files.example.test/bontang-3.pdf')
        ->and(substr_count($answer, 'https://files.example.test/bontang-1.pdf'))
        ->toBe(1);

    Http::assertNothingSent();
});

test('JF recommendation lookup confirms when a region has no available recommendation', function () {
    insertJfRecommendationRecords([
        [
            'kab_kota' => 'Kota Surakarta',
            'provinsi' => 'Jawa Tengah',
            'keterangan' => 'Belum ada rekomendasi',
            'link_surat_rekom' => null,
        ],
    ]);

    Http::fake();

    $response = $this->post(route('chat.process'), [
        'message' => 'Berikan surat rekomendasi JF dari daerah Surakarta',
        'history' => [],
    ]);

    $response->assertOk();
    expect($response->json('answer'))
        ->toContain('belum dapat ditampilkan')
        ->toContain('belum ada rekomendasi')
        ->not->toContain('https://');

    Http::assertNothingSent();
});

test('guest can retrieve recommendation letters for a named region without an SSO login', function () {
    insertProposalRecords([
        [
            'raw_data' => json_encode([
                'no_surat' => '001/SBG/2026',
                'tanggal_usulan' => '2026-10-03',
                'hal_usulan' => 'Pengusulan Kebutuhan Jabatan Fungsional',
                'kab_kota' => 'Kota Surakarta',
                'provinsi' => 'Jawa Tengah',
                'link_surat' => 'https://files.example.test/surakarta-jabatan-fungsional.pdf',
            ]),
        ],
        [
            'raw_data' => json_encode([
                'no_surat' => '002/SBG/2026',
                'tanggal_usulan' => '2026-10-02',
                'hal_usulan' => 'Pengusulan Kebutuhan Pegawai',
                'kab_kota' => 'Kota Surakarta',
                'provinsi' => 'Jawa Tengah',
                'link_surat' => 'https://files.example.test/surakarta-pegawai.pdf',
            ]),
        ],
    ]);

    Http::fake();

    $response = $this->post(route('chat.process'), [
        'message' => 'Berikan saya surat rekomendasi dari daerah Surakarta',
        'history' => [],
    ]);

    $response->assertOk()
        ->assertJsonMissingPath('require_login');
    expect($response->json('answer'))
        ->toContain('https://files.example.test/surakarta-jabatan-fungsional.pdf')
        ->toContain('https://files.example.test/surakarta-pegawai.pdf');

    Http::assertNothingSent();
});

test('chatbot returns all proposal PDF links for a matching province', function () {
    insertProposalRecords([
        [
            'raw_data' => json_encode([
                'no_surat' => '001/MAMUJU/2026',
                'tanggal_usulan' => '2026-10-01',
                'hal_usulan' => 'Usulan kegiatan Mamuju',
                'kab_kota' => 'Kabupaten Mamuju',
                'provinsi' => 'Sulawesi Barat',
                'link_surat' => 'https://files.example.test/mamuju.pdf',
            ]),
        ],
        [
            'raw_data' => json_encode([
                'no_surat' => '002/PASANGKAYU/2026',
                'tanggal_usulan' => '2026-10-02',
                'hal_usulan' => 'Usulan kegiatan Pasangkayu',
                'kab_kota' => 'Kabupaten Pasangkayu',
                'provinsi' => 'Sulawesi Barat',
                'link_surat' => 'https://files.example.test/pasangkayu.pdf',
            ]),
        ],
    ]);

    Http::fake();

    $response = $this->post(route('chat.process'), [
        'message' => 'Tampilkan tautan PDF usulan Provinsi Sulawesi Barat',
        'history' => [],
    ]);

    $response->assertOk();
    expect($response->json('answer'))
        ->toContain('https://files.example.test/mamuju.pdf')
        ->toContain('https://files.example.test/pasangkayu.pdf');

    Http::assertNothingSent();
});

test('proposal lookup removes duplicate PDF links for the same region', function () {
    $row = [
        'no_surat' => '800/315',
        'tanggal_usulan' => '2026-03-04',
        'hal_usulan' => 'Permohonan Rekomendasi Persetujuan Formasi Jabatan Fungsional',
        'kab_kota' => 'Kabupaten Aceh Barat',
        'provinsi' => 'Aceh',
        'link_surat' => 'https://drive.google.com/file/d/example/view',
    ];

    insertProposalRecords([
        ['raw_data' => json_encode($row)],
        ['raw_data' => json_encode($row)],
    ]);

    Http::fake();

    $response = $this->post(route('chat.process'), [
        'message' => 'Berikan saya surat rekomendasi dari Kabupaten Aceh Barat Provinsi Aceh',
        'history' => [],
    ]);

    $response->assertOk();
    expect(substr_count($response->json('answer'), 'https://drive.google.com/file/d/example/view'))
        ->toBe(1);

    Http::assertNothingSent();
});

test('chatbot asks for an area when a PDF-link request does not name one', function () {
    Http::fake();

    $response = $this->post(route('chat.process'), [
        'message' => 'Tolong cari link PDF proposal',
        'history' => [],
    ]);

    $response->assertOk();
    expect($response->json('answer'))->toContain('Mohon sebutkan nama kota atau kabupaten');

    Http::assertNothingSent();
});

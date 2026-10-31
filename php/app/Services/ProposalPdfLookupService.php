<?php

namespace App\Services;

use Illuminate\Database\QueryException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ProposalPdfLookupService
{
    public function answerFor(string $message): ?string
    {
        if (! config('services.proposal_pdf.enabled')) {
            return null;
        }

        if ($this->isRegionalJfRecommendationRequest($message)) {
            return $this->regionalJfRecommendationAnswer($message);
        }

        if (! $this->isProposalPdfRequest($message)) {
            return null;
        }

        try {
            $regions = DB::connection('proposal_source')
                ->table('projf_proposal')
                ->select(['kab_kota', 'provinsi'])
                ->distinct()
                ->get();

            $region = $this->findRegion($message, $regions);

            if ($region === null) {
                if (
                    $this->requestedRegionType($message) !== null
                    || preg_match('/\bprovinsi\b/u', mb_strtolower($message)) === 1
                ) {
                    return 'Saya belum menemukan wilayah yang cocok di data proposal. Periksa ejaan nama kota atau kabupaten; provinsi tidak wajib.';
                }

                return 'Mohon sebutkan nama kota atau kabupaten. Provinsi tidak wajib; jika nama kota dan kabupaten sama, sebutkan jenis daerahnya.';
            }

            $proposalsQuery = DB::connection('proposal_source')
                ->table('projf_proposal')
                ->select(['id', 'no_surat', 'tanggal_usulan', 'hal_usulan', 'kab_kota', 'provinsi', 'link_surat'])
                ->where($region['column'], $region['value'])
                ->where(function ($query): void {
                    $query->whereNull('status')->orWhere('status', '<>', 'rejected');
                })
                ->where(function ($query): void {
                    $query->whereNotNull('verified_at')->orWhere('status', 'approved');
                })
                ->whereNotNull('link_surat')
                ->where('link_surat', '<>', '')
                ->orderByDesc('tanggal_usulan');

            $proposals = $proposalsQuery->get();
        } catch (QueryException $exception) {
            Log::error('Proposal PDF source lookup query failed', [
                'connection' => 'proposal_source',
                'error' => $exception->getMessage(),
            ]);

            return 'Maaf, saat ini sumber data surat rekomendasi sedang bermasalah. Silakan coba lagi nanti atau hubungi administrator.';
        }

        $topicTerms = $this->topicTerms($message, $regions);
        $matchingProposals = $proposals
            ->map(fn (object $proposal): array => [
                'proposal' => $proposal,
                'topic_score' => $this->topicMatchScore($topicTerms, (string) $proposal->hal_usulan),
            ])
            ->filter(fn (array $match): bool => $topicTerms === [] || $match['topic_score'] >= 0.6)
            ->sortByDesc('topic_score');

        if ($matchingProposals->isEmpty()) {
            return "Belum ditemukan tautan PDF usulan yang sudah diverifikasi atau approved untuk {$region['value']}"
                .($topicTerms !== [] ? " dengan topik {$this->formatTopic($topicTerms)}." : '.');
        }

        $links = collect();
        $seenUrls = [];

        foreach ($matchingProposals as $match) {
            $proposal = $match['proposal'];

            if (! $this->isHttpUrl((string) $proposal->link_surat)) {
                Log::warning('Proposal PDF lookup skipped an invalid link_surat URL', [
                    'proposal_id' => $proposal->id,
                ]);

                continue;
            }

            $url = trim((string) $proposal->link_surat);
            $deduplicationKey = $url;

            if (isset($seenUrls[$deduplicationKey])) {
                continue;
            }

            $seenUrls[$deduplicationKey] = true;
            $links->push($this->formatProposalLink($proposal, $url));
        }

        if ($links->isEmpty()) {
            return "Belum ditemukan tautan PDF usulan terverifikasi atau approved yang valid untuk {$region['value']}.";
        }

        return "Tautan PDF usulan terverifikasi untuk {$region['value']}:\n\n".implode("\n\n", $links->all());
    }

    private function isRegionalJfRecommendationRequest(string $message): bool
    {
        $message = mb_strtolower($message);

        return preg_match('/\b(?:jf|jabatan\s+fungsional\w*)\b/u', $message) === 1
            && preg_match('/\b(?:rekomend\w*|rekomen\w*|surat)\b/u', $message) === 1;
    }

    private function regionalJfRecommendationAnswer(string $message): string
    {
        try {
            $source = DB::connection('proposal_source');
            $regions = $source
                ->table('projf_manajemen_jf')
                ->select(['kab_kota', 'provinsi'])
                ->distinct()
                ->get();

            $region = $this->findRegion($message, $regions);

            if ($region === null) {
                if (
                    $this->requestedRegionType($message) !== null
                    || preg_match('/\b(?:provinsi|daerah)\b/u', mb_strtolower($message)) === 1
                ) {
                    return 'Surat rekomendasi belum dapat ditampilkan karena untuk saat ini belum ada rekomendasi pada daerah yang diminta.';
                }

                return 'Mohon sebutkan nama Kabupaten/Kota atau Provinsi untuk mencari surat rekomendasi JF.';
            }

            $recommendations = $source
                ->table('projf_manajemen_jf')
                ->select(['kab_kota', 'provinsi', 'keterangan', 'link_surat_rekom'])
                ->where($region['column'], $region['value'])
                ->get();
        } catch (QueryException $exception) {
            Log::error('Regional JF recommendation lookup query failed', [
                'connection' => 'proposal_source',
                'table' => 'projf_manajemen_jf',
                'error' => $exception->getMessage(),
            ]);

            return 'Maaf, saat ini sumber data surat rekomendasi sedang bermasalah. Silakan coba lagi nanti atau hubungi administrator.';
        }

        $availableRecommendations = $recommendations->filter(
            static fn (object $recommendation): bool => mb_strtolower(trim((string) $recommendation->keterangan))
                === 'sudah ada rekomendasi',
        );

        if ($availableRecommendations->isEmpty()) {
            return "Surat rekomendasi untuk {$region['value']} belum dapat ditampilkan karena untuk saat ini belum ada rekomendasi.";
        }

        $recommendationGroups = [];
        $seenUrls = [];

        foreach ($availableRecommendations as $recommendation) {
            $url = trim((string) ($recommendation->link_surat_rekom ?? ''));

            if (! $this->isHttpUrl($url)) {
                Log::warning('Regional JF recommendation lookup skipped an invalid link_surat_rekom URL', [
                    'kab_kota' => $recommendation->kab_kota,
                    'provinsi' => $recommendation->provinsi,
                ]);

                continue;
            }

            if (isset($seenUrls[$url])) {
                continue;
            }

            $seenUrls[$url] = true;
            $area = trim((string) ($recommendation->kab_kota ?? ''));
            $province = trim((string) ($recommendation->provinsi ?? ''));
            $groupKey = $region['column'] === 'kab_kota'
                ? 'requested-area'
                : $this->normalizeRegion($area).'|'.$this->normalizeRegion($province);
            $location = $region['column'] === 'kab_kota'
                ? ''
                : implode(', ', array_filter([$area, $province]));

            $recommendationGroups[$groupKey] ??= [
                'location' => $location,
                'urls' => [],
            ];
            $recommendationGroups[$groupKey]['urls'][] = $url;
        }

        if ($recommendationGroups === []) {
            return "Surat rekomendasi untuk {$region['value']} sudah tersedia, tetapi tautannya belum dapat ditampilkan. Silakan hubungi administrator.";
        }

        $linkGroups = collect($recommendationGroups)
            ->map(function (array $group): string {
                $links = collect($group['urls'])
                    ->values()
                    ->map(fn (string $url, int $index): string => '- Surat rekomendasi '.($index + 1).": {$url}")
                    ->implode("\n");

                return $group['location'] === ''
                    ? $links
                    : "- {$group['location']}\n  ".str_replace("\n", "\n  ", $links);
            })
            ->implode("\n\n");

        return "Surat rekomendasi JF untuk {$region['value']}:\n\n".$linkGroups;
    }

    private function isProposalPdfRequest(string $message): bool
    {
        $message = mb_strtolower($message);
        $requestsDocument = preg_match('/\b(?:link|tautan|url|pdf|file|surat)\b/u', $message) === 1;
        $mentionsProposal = preg_match('/\b(?:proposal|pengusul\w*|usulan|rekomend\w*|rekomen\w*)\b/u', $message) === 1;
        $requestsRecommendationLetter = preg_match('/\bsurat\s+rekomendasi\b/u', $message) === 1;
        $requestsRecommendation = preg_match('/\b(?:berikan|beri|minta|carikan|cari|tampilkan|ingin|mau)\b/u', $message) === 1
            && preg_match('/\b(?:kebutuhan|jabatan\s+fungsional|jf|pengusulan|formasi)\b/u', $message) === 1;
        $isBangkomRequest = preg_match('/\bbangkom\b/u', $message) === 1;

        return ! $isBangkomRequest
            && (($requestsDocument && $mentionsProposal) || $requestsRecommendationLetter || ($mentionsProposal && $requestsRecommendation));
    }

    /**
     * @param  Collection<int, \stdClass>  $regions
     * @return array{column: string, value: string}|null
     */
    private function findRegion(string $message, Collection $regions): ?array
    {
        $normalizedMessage = $this->normalizeRegion($message);
        $requestedType = $this->requestedRegionType($message);
        $matches = [];

        foreach ($regions as $region) {
            foreach (['kab_kota', 'provinsi'] as $column) {
                $value = trim((string) ($region->{$column} ?? ''));
                $normalizedValue = $this->normalizeRegion($value);
                $regionType = $column === 'kab_kota' ? $this->regionType($value) : null;

                if (
                    $normalizedValue !== ''
                    && preg_match(
                        '/(?:^| )'.preg_quote($normalizedValue, '/').'(?:$| )/u',
                        $normalizedMessage,
                    ) === 1
                ) {
                    $matches[$column.':'.$normalizedValue.':'.($regionType ?? 'unspecified')] = [
                        'column' => $column,
                        'value' => $value,
                        'length' => mb_strlen($normalizedValue),
                        'type' => $regionType,
                    ];
                }
            }
        }

        if ($requestedType !== null) {
            $matches = array_filter(
                $matches,
                fn (array $match): bool => $match['column'] === 'kab_kota'
                    && ($match['type'] === null || $match['type'] === $requestedType),
            );
        }

        if ($matches === []) {
            return null;
        }

        $areaMatches = array_filter($matches, static fn (array $match): bool => $match['column'] === 'kab_kota');
        if ($areaMatches !== []) {
            $matches = $areaMatches;
        }

        $distinctAreas = collect($matches)
            ->where('column', 'kab_kota')
            ->unique(fn (array $match): string => $match['type'].':'.$this->normalizeRegion($match['value']));

        if ($requestedType === null && $distinctAreas->count() > 1) {
            return null;
        }

        usort($matches, static function (array $left, array $right): int {
            if ($left['column'] !== $right['column']) {
                return $left['column'] === 'kab_kota' ? -1 : 1;
            }

            if ($left['type'] !== $right['type']) {
                return $left['type'] === null ? 1 : -1;
            }

            return $right['length'] <=> $left['length'];
        });

        return [
            'column' => $matches[0]['column'],
            'value' => $matches[0]['value'],
        ];
    }

    private function requestedRegionType(string $message): ?string
    {
        $normalized = mb_strtolower($message);

        if (preg_match('/\b(?:kabupaten|kab\.?)\b/u', $normalized) === 1) {
            return 'kabupaten';
        }

        if (preg_match('/\bkota\b/u', $normalized) === 1) {
            return 'kota';
        }

        return null;
    }

    private function regionType(string $region): ?string
    {
        $normalized = mb_strtolower(trim($region));

        if (preg_match('/^kabupaten\b|^kab\.?\s/u', $normalized) === 1) {
            return 'kabupaten';
        }

        if (preg_match('/^kota\b/u', $normalized) === 1) {
            return 'kota';
        }

        return null;
    }

    private function normalizeRegion(string $value): string
    {
        $value = mb_strtolower($value);
        $value = preg_replace('/\b(?:kabupaten|kab\.?|kota|provinsi)\b/u', ' ', $value) ?? $value;
        $value = preg_replace('/[^\p{L}\p{N}]+/u', ' ', $value) ?? $value;

        return trim(preg_replace('/\s+/u', ' ', $value) ?? $value);
    }

    /**
     * @param  Collection<int, \stdClass>  $regions
     * @return list<string>
     */
    private function topicTerms(string $message, Collection $regions): array
    {
        $messageTerms = explode(' ', $this->normalizeTopic($message));
        $regionTerms = $regions
            ->flatMap(fn (object $region): array => [
                ...explode(' ', $this->normalizeRegion((string) ($region->kab_kota ?? ''))),
                ...explode(' ', $this->normalizeRegion((string) ($region->provinsi ?? ''))),
            ])
            ->unique()
            ->all();
        $ignoredTerms = [
            'berikan', 'beri', 'saya', 'tolong', 'minta', 'carikan', 'cari', 'tampilkan',
            'link', 'tautan', 'url', 'file', 'pdf', 'surat', 'dokumen', 'terkait', 'mengenai', 'tentang',
            'untuk', 'dari', 'yang', 'di', 'ke', 'pada', 'proposal', 'usulan', 'rekomendasi',
            'rekomenasi', 'rekomen', 'pengusulan', 'kebutuhan',
            'daerah', 'wilayah',
        ];

        $terms = array_values(array_unique(array_filter(
            array_diff($messageTerms, $regionTerms),
            static fn (string $term): bool => $term !== '' && ! in_array($term, $ignoredTerms, true),
        )));

        $terms = array_map(static fn (string $term): string => match ($term) {
            'jf' => 'jabatan_fungsional',
            default => $term,
        }, $terms);

        if ($terms === []) {
            return [];
        }

        return array_values(array_unique(array_merge(
            ...array_map(
                static fn (string $term): array => $term === 'jabatan_fungsional'
                    ? ['jabatan', 'fungsional']
                    : [$term],
                $terms,
            ),
        )));
    }

    /**
     * @param  list<string>  $topicTerms
     */
    private function topicMatchScore(array $topicTerms, string $proposalTitle): float
    {
        if ($topicTerms === []) {
            return 0.0;
        }

        $titleTerms = explode(' ', $this->normalizeTopic($proposalTitle));
        $matchedTerms = array_intersect($topicTerms, $titleTerms);

        return count($matchedTerms) / count($topicTerms);
    }

    private function normalizeTopic(string $value): string
    {
        $value = $this->normalizeRegion($value);
        $value = preg_replace('/\b(?:rekomenasi|rekomen)\b/u', ' rekomendasi ', $value) ?? $value;

        return trim(preg_replace('/\s+/u', ' ', $value) ?? $value);
    }

    /**
     * @param  list<string>  $topicTerms
     */
    private function formatTopic(array $topicTerms): string
    {
        return implode(' ', $topicTerms);
    }

    private function isHttpUrl(string $url): bool
    {
        if (filter_var($url, FILTER_VALIDATE_URL) === false) {
            return false;
        }

        return in_array(parse_url($url, PHP_URL_SCHEME), ['http', 'https'], true);
    }

    private function formatProposalLink(\stdClass $proposal, string $url): string
    {
        $details = collect([
            $proposal->no_surat ? "Nomor: {$proposal->no_surat}" : null,
            $proposal->tanggal_usulan ? "Tanggal: {$proposal->tanggal_usulan}" : null,
            $proposal->hal_usulan ? "Perihal: {$proposal->hal_usulan}" : null,
        ])->filter();

        return '- '.$details->implode("\n  ")."\n  PDF: {$url}";
    }
}

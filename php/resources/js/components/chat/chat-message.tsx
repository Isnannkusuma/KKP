import { Chart, Filler, Legend, LineElement, PointElement, RadarController, RadialLinearScale, Tooltip } from 'chart.js';
import { Bot, FileText, User } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import type { ReactNode } from 'react';
import type { ChatMessage as ChatMessageType } from '@/types';

Chart.register(RadarController, RadialLinearScale, PointElement, LineElement, Filler, Tooltip, Legend);

interface ChatMessageProps {
    message: ChatMessageType;
}

function isBangkomResponse(content: string): boolean {
    const normalized = content.toLowerCase().replace(/[_()-]/g, ' ');

    return normalized.includes('detail bangkom')
        || normalized.includes('detail pengembangan kompetensi bangkom')
        || normalized.includes('rekomendasi bangkom')
        || normalized.includes('rekomendasi bangkom :')
        || (normalized.includes('bangkom') && normalized.includes('rekomendasi'))
}

function parseBangkomData(content: string) {
    const lines = content.split(/\r?\n/).map((line) => line.trim()).filter(Boolean);

    const valueFromLabel = (labels: string[]) => {
        const match = lines.find((line) => labels.some((label) => line.startsWith(label + ':')));

        if (!match) {
            return '';
        }

        return match.split(':').slice(1).join(':').trim();
    };

    const extractSimpleList = (prefix: string) => {
        const startIndex = lines.findIndex((line) => line.startsWith(prefix));

        if (startIndex === -1) {
            return [] as string[];
        }

        const items: string[] = [];

        for (let i = startIndex + 1; i < lines.length; i++) {
            const line = lines[i];

            if (line.startsWith('Integritas dan Moralitas') || line.startsWith('Total Kompetensi') || line.startsWith('Total Sumbu')) {
                break;
            }

            if (line.startsWith('- ') || line.startsWith('  - ') || line.startsWith('  ')) {
                items.push(line.replace(/^-\s*/, '').replace(/^\s{2,}/, ''));
            }
        }

        return items;
    };

    const recommendations: Array<{ aspek: string; nilai: string; gap: string; pelatihan: string[]; kegiatan: string[]; mandiri: string }> = [];
    const recommendationPrefix = 'Rekomendasi Bangkom:';
    const startIndex = lines.findIndex((line) => line.startsWith(recommendationPrefix));

    if (startIndex !== -1) {
        let current: { aspek: string; nilai: string; gap: string; pelatihan: string[]; kegiatan: string[]; mandiri: string } | null = null;

        for (let i = startIndex + 1; i < lines.length; i++) {
            const line = lines[i];

            if (line.startsWith('- ')) {
                const match = line.match(/-\s*(.+?):\s*nilai=(.*?),\s*gap=(.*)$/i);

                if (match) {
                    if (current) {
                        recommendations.push(current);
                    }

                    current = {
                        aspek: match[1].trim(),
                        nilai: match[2]?.trim() ?? '',
                        gap: match[3]?.trim() ?? '',
                        pelatihan: [],
                        kegiatan: [],
                        mandiri: '',
                    };
                    continue;
                }
            }

            if (!current) {
                continue;
            }

            if (line.startsWith('Rekomendasi Pelatihan:')) {
                current.pelatihan = line.replace('Rekomendasi Pelatihan:', '').split(';').map((item) => item.trim()).filter(Boolean);
                continue;
            }

            if (line.startsWith('Penunjang Lainnya:')) {
                current.pelatihan = [...current.pelatihan, ...line.replace('Penunjang Lainnya:', '').split(';').map((item) => item.trim()).filter(Boolean)];
                continue;
            }

            if (line.startsWith('Bentuk Kegiatan Non-Klasikal:')) {
                current.kegiatan = line.replace('Bentuk Kegiatan Non-Klasikal:', '').split(';').map((item) => item.trim()).filter(Boolean);
                continue;
            }

            if (line.startsWith('Mandiri:')) {
                current.mandiri = line.replace('Mandiri:', '').trim();
                continue;
            }

            if (
                current
                && (
                    line.startsWith('Rekomendasi Manajerial')
                    || line.startsWith('Rekomendasi Jabatan')
                    || line.startsWith('Nama:')
                    || line.startsWith('NIP:')
                    || line.startsWith('Pangkat:')
                    || line.startsWith('Jabatan:')
                )
            ) {
                recommendations.push(current);
                current = null;
            }
        }

        if (current) {
            recommendations.push(current);
        }
    }

    return {
        name: valueFromLabel(['Nama']),
        nip: valueFromLabel(['NIP']),
        pangkat: valueFromLabel(['Pangkat']),
        tmtPangkat: valueFromLabel(['TMT Pangkat']),
        jabatan: valueFromLabel(['Jabatan']),
        tmtJabatan: valueFromLabel(['TMT Jabatan']),
        unitOrganisasi: valueFromLabel(['Unit Kerja', 'Unit Organisasi']),
        jenjangJabatan: valueFromLabel(['Jenjang Jabatan']),
        rekomendasiManajerial: valueFromLabel(['Rekomendasi Manajerial', 'Rekomendasi Manajerial Diklat', 'Rekomendasi Manajerial:']),
        rekomendasiFungsional: valueFromLabel(['Rekomendasi Jabatan Fungsional', 'Rekomendasi Jabatan Fungsional:']),
        jpmKompetensi: valueFromLabel(['JPM Kompetensi', 'JPM Kompetensi:']),
        recommendations,
        pelatihanUmum: extractSimpleList('Rekomendasi Pelatihan:'),
    };
}

function renderBangkomFallback(content: string) {
    const sections = content
        .split(/\n\s*(?=\*\*)/)
        .map((section) => section.trim())
        .filter(Boolean);

    const parsedSections = sections.length > 0 ? sections : [content];


    return (
        <div className="w-full max-w-full overflow-hidden rounded-xl border border-slate-700 bg-slate-950 text-slate-100 shadow-2xl">
            <div className="border-b border-slate-700 bg-slate-900 px-3 py-3 sm:px-4">
                <div className="text-sm font-semibold text-slate-100 sm:text-[15px]">Detail Bangkom</div>
            </div>

            <div className="max-h-140 overflow-y-auto px-3 py-3 sm:px-4">
                <div className="space-y-4">
                    {parsedSections.map((section, index) => {
                        const lines = section
                            .split(/\n/)
                            .map((line) => line.trim())
                            .filter(Boolean);

                        const titleLine = lines.find((line) => line.startsWith('**') && line.endsWith('**'));
                        const title = titleLine ? titleLine.replace(/\*\*/g, '').trim() : 'Rekomendasi';

                        const detailLines = lines.filter((line) => {
                            const cleaned = line.replace(/\*\*/g, '').trim();

                            return cleaned !== titleLine && cleaned.length > 0 && !cleaned.startsWith('Berikut rekomendasi');
                        });

                        return (
                            <div key={`${title}-${index}`} className="rounded-lg border border-slate-700 bg-slate-900/80 p-3">
                                <div className="mb-2 text-sm font-semibold text-slate-100 sm:text-[15px]">{title}</div>
                                <div className="space-y-2 text-xs leading-relaxed text-slate-200 sm:text-sm">
                                    {detailLines.map((line, lineIndex) => {
                                        const cleanLine = line.replace(/\*\*/g, '').trim();

                                        if (cleanLine.startsWith('- ') || cleanLine.startsWith('• ')) {
                                            return (
                                                <div key={`${cleanLine}-${lineIndex}`} className="flex gap-2">
                                                    <span className="mt-1 h-1.5 w-1.5 shrink-0 rounded-full bg-emerald-400" />
                                                    <span>{cleanLine.replace(/^[-•]\s*/, '')}</span>
                                                </div>
                                            );
                                        }

                                        return (
                                            <div key={`${cleanLine}-${lineIndex}`}>{cleanLine}</div>
                                        );
                                    })}
                                </div>
                            </div>
                        );
                    })}
                </div>
            </div>
        </div>
    );
}

function renderBangkomMessage(content: string) {
    const data = parseBangkomData(content);
    const headerName = data.name || 'Detail Bangkom';
    const detailTitle = `Detail Bangkom — ${headerName}`;

    const hasStructuredBangkom = data.recommendations.length > 0 || data.pangkat || data.jabatan || data.unitOrganisasi || data.jpmKompetensi;

    if (!hasStructuredBangkom) {
        return renderBangkomFallback(content);
    }

    const cards = [
        { label: 'PANGKAT', value: data.pangkat || '-' },
        { label: 'TMT PANGKAT', value: data.tmtPangkat || '-' },
        { label: 'JABATAN', value: data.jabatan || '-' },
        { label: 'TMT JABATAN', value: data.tmtJabatan || '-' },
        { label: 'UNIT ORGANISASI', value: data.unitOrganisasi || '-' },
        { label: 'JENJANG JABATAN', value: data.jenjangJabatan || '-' },
    ];

    return (
        <div className="w-full max-w-full overflow-hidden rounded-xl border border-slate-700 bg-slate-950 text-slate-100 shadow-2xl">
            <div className="border-b border-slate-700 bg-slate-900 px-3 py-3 sm:px-4">
                <div className="text-sm font-semibold text-slate-100 sm:text-[15px]">{detailTitle}</div>

                <div className="mt-1 text-[10px] text-slate-400 sm:text-[11px]">
                    NIP: {data.nip || '-'} | Status 200 OK | 9 aspek kompetensi
                </div>
            </div>

            <div className="grid gap-3 p-3 sm:grid-cols-2 sm:p-4 xl:grid-cols-5">
                {cards.map((card) => (
                    <div key={card.label} className="min-h-22.5 rounded-lg border border-slate-700 bg-slate-900/80 p-3">
                        <div className="mb-2 text-[10px] font-medium uppercase tracking-wide text-slate-400 sm:text-[11px]">{card.label}</div>
                        <div className="text-sm font-semibold text-slate-100 wrap-break-word sm:text-[15px]">{card.value}</div>
                    </div>
                ))}
            </div>

            <div className="grid gap-3 px-3 pb-3 sm:px-4 sm:pb-4 md:grid-cols-3">
                <div className="rounded-lg border border-slate-700 bg-slate-900/80 p-3">
                    <div className="mb-2 text-[10px] font-medium uppercase tracking-wide text-slate-400 sm:text-[11px]">Rekomendasi Manajerial</div>
                    <div className="inline-flex rounded-full bg-emerald-500/15 px-2 py-1 text-[10px] font-medium text-emerald-300 sm:text-xs">
                        {data.rekomendasiManajerial || 'Tidak Wajib'}
                    </div>
                    <div className="mt-3 text-xs text-slate-300 sm:text-sm">
                        {data.rekomendasiManajerial?.toLowerCase() === 'tidak wajib' ? 'Diklat Penjenjangan' : data.rekomendasiManajerial || 'Diklat Penjenjangan'}
                    </div>
                </div>

                <div className="rounded-lg border border-slate-700 bg-slate-900/80 p-3">
                    <div className="mb-2 text-[10px] font-medium uppercase tracking-wide text-slate-400 sm:text-[11px]">Rekomendasi Jabatan Fungsional</div>

                    <div className="inline-flex rounded-full bg-emerald-500/15 px-2 py-1 text-[10px] font-medium text-emerald-300 sm:text-xs">
                        {data.rekomendasiFungsional || 'Sudah'}
                    </div>
                    <div className="mt-3 text-xs text-slate-300 sm:text-sm">
                        {data.rekomendasiFungsional ? 'Sudah Memiliki Sertifikasi - Diklat Fungsional' : 'Belum tersedia'}
                    </div>
                </div>

                <div className="rounded-lg border border-slate-700 bg-slate-900/80 p-3">
                    <div className="mb-2 text-[10px] font-medium uppercase tracking-wide text-slate-400 sm:text-[11px]">JPM Kompetensi</div>
                    <div className="text-base font-semibold text-slate-100 sm:text-[18px]">{data.jpmKompetensi || '0'} / Target: 0</div>
                </div>
            </div>

            <div className="px-3 pb-3 sm:px-4 sm:pb-4">
                <div className="mb-3 text-sm font-semibold text-slate-200 sm:text-[14px]">Nilai & Gap Kompetensi Per Aspek</div>
                <div className="overflow-x-auto rounded-lg border border-slate-700 bg-slate-900/80">
                    <div className="min-w-175">
                        <div className="grid grid-cols-[1.2fr_0.8fr_0.8fr_2fr_2fr] gap-2 border-b border-slate-700 bg-slate-800/90 px-3 py-2 text-[10px] font-semibold uppercase tracking-wide text-slate-300 sm:text-[11px]">
                            <span>Aspek</span>
                            <span>Nilai</span>
                            <span>Gap</span>
                            <span>Pelatihan Utama</span>
                            <span>Kegiatan Non-Klasikal</span>
                        </div>

                        {(data.recommendations.length > 0 ? data.recommendations : [{ aspek: 'Tidak ada data', nilai: '-', gap: '-', pelatihan: [], kegiatan: [], mandiri: '' }]).map((item, idx) => (
                            <div key={`${item.aspek}-${idx}`} className="grid grid-cols-[1.2fr_0.8fr_0.8fr_2fr_2fr] gap-2 border-b border-slate-700/80 px-3 py-3 text-xs text-slate-200 last:border-b-0 sm:text-sm">
                                <span>{item.aspek}</span>
                                <span>{item.nilai || '-'}</span>
                                <span>{item.gap || '-'}</span>
                                <span>{item.pelatihan.length > 0 ? item.pelatihan.join('; ') : '-'}</span>
                                <span>{item.kegiatan.length > 0 ? item.kegiatan.join('; ') : '-'}</span>
                            </div>
                        ))}
                    </div>
                </div>
            </div>
        </div>
    );
}

function isMappingResponse(content: string): boolean {
    const normalized = content.toLowerCase().replace(/[_()-]/g, ' ');

    if (isBangkomResponse(content)) {
        return false;
    }

    return normalized.includes('hasil pemetaan kinerja dan talenta');
}

function toNumber(value: string | undefined): number {
    if (!value) {
        return 0;
    }

    const numeric = Number.parseFloat(String(value).replace(/[^0-9.,-]/g, '').replace(',', '.'));

    return Number.isFinite(numeric) ? numeric : 0;
}

function parseMappingProfileData(content: string) {
    const lines = content
        .split(/\r?\n/)
        .map((line) => line.trim())
        .filter(Boolean);

    const findValue = (labels: string[]) => {
        for (const label of labels) {
            const normalizedLabel = label.toLowerCase();
            const line = lines.find((entry) => {
                const normalizedEntry = entry.toLowerCase();

                return normalizedEntry.startsWith(normalizedLabel + ':')
                    || normalizedEntry.includes(normalizedLabel + ':')
                    || normalizedEntry.startsWith(normalizedLabel + ' =')
                    || normalizedEntry.includes(normalizedLabel + ' =');
            });

            if (!line) {
                continue;
            }

            const match = line.match(/[:=]\s*(.+)$/);

            if (match) {
                return match[1].trim();
            }

            return line.replace(new RegExp(`^.*?${label}`, 'i'), '').replace(/^[\s:=]+/, '').trim();
        }

        return '';
    };

    const totalSumbu = toNumber(findValue(['Total Sumbu', 'Total Sumbu Y', 'Total Sumbu (smb_y)', 'Total Sumbu Y (smb_y)']));
    const nTalent = toNumber(findValue(['Nilai Talent (n_talent)', 'N Talent', 'Nilai Talent', 'Talent']));
    const sumbuY = toNumber(findValue(['Total Sumbu Y (smb_y)', 'Sumbu Y', 'Total Sumbu Y']));
    const sumbuX = toNumber(findValue(['Total Sumbu X (smb_x)', 'Sumbu X', 'Total Sumbu X']));
    const box = findValue(['Box Kuadran', 'Box Kuadran (box)', 'Box', 'Kuadran']);
    const triwulan = findValue(['Status Triwulan', 'Status Triwulan:', 'Triwulan']);
    const tahunTriwulan = findValue(['Tahun Triwulan', 'Tahun Triwulan:', 'Tahun']);
    const nip = findValue(['NIP']);

    const metrics = [
        { label: 'N KU (Kompetensi Umum)', value: toNumber(findValue(['Total Nilai Kinerja Utama (n_ku)', 'N KU', 'N KU (Kompetensi Umum)', 'Kompetensi Umum'])), max: 60 },
        { label: 'N KP (Kompetensi Pend.)', value: toNumber(findValue(['Total Nilai Kinerja Penguat (n_kp)', 'N KP', 'N KP (Kompetensi Pend.)', 'Kompetensi Pendukung'])), max: 30 },
        { label: 'N Integ Moral', value: toNumber(findValue(['Integritas dan Moralitas (n_integmoral)', 'N Integ Moral', 'Integritas dan Moralitas'])), max: 20 },
        { label: 'N Talent', value: nTalent, max: 100 },
        { label: 'N Pend', value: toNumber(findValue(['Tingkat Pendidikan Formal (n_pend)', 'N Pend', 'Tingkat Pendidikan Formal'])), max: 15 },
        { label: 'N KS Ilmu', value: toNumber(findValue(['Kesesuaian Bidang Ilmu (n_ks_ilmu)', 'N KS Ilmu', 'Kesesuaian Bidang Ilmu'])), max: 15 },
        { label: 'N Lama Jabatan', value: toNumber(findValue(['Pengalaman Jabatan (n_lamajab)', 'N Lama Jabatan', 'Pengalaman Jabatan'])), max: 10 },
        { label: 'Hargaan', value: toNumber(findValue(['Penghargaan (hargaan)', 'Hargaan'])), max: 10 },
        { label: 'Tim', value: toNumber(findValue(['Penugasan dalam Tim (tim)', 'Tim'])), max: 10 },
        { label: 'TGA Drajat', value: toNumber(findValue(['Umpan Balik Kinerja 360 Derajat (tga_drajat)', 'TGA Drajat', '360 Derajat'])), max: 10 },
        { label: 'Komp', value: toNumber(findValue(['Total Kompetensi (komp)', 'Komp', 'Total Kompetensi'])), max: 15 },
        { label: 'N Bangan', value: toNumber(findValue(['Pengembangan Kompetensi (n_bangan)', 'N Bangan', 'Pengembangan Kompetensi'])), max: 5 },
    ];

    const resolvedTotalSumbu = totalSumbu || (sumbuY + sumbuX) || 0;

    return {
        nip: nip || '—',
        box: box || '—',
        triwulan: triwulan || 'TW III',
        tahunTriwulan: tahunTriwulan || '2026',
        totalSumbu: resolvedTotalSumbu,
        nTalent: nTalent || 0,
        sumbuY: sumbuY || 0,
        sumbuX: sumbuX || 0,
        metrics,
    };
}

function RadarChart({ metrics }: { metrics: { label: string; value: number; max: number }[] }) {
    const canvasRef = useRef<HTMLCanvasElement | null>(null);
    const chartRef = useRef<Chart | null>(null);

    useEffect(() => {
        const canvas = canvasRef.current;

        if (!canvas) {
            return;
        }

        const labels = metrics.map((item) => item.label.replace(/^N\s+/, '').replace(/\s*\(.*\)/, '')).slice(0, 10);
        const values = metrics.map((item) => Math.min(Math.max(item.value, 0), item.max)).slice(0, 10);

        const existingChart = Chart.getChart(canvas);

        if (existingChart) {
            existingChart.destroy();
        }

        if (chartRef.current) {
            chartRef.current.destroy();
            chartRef.current = null;
        }

        chartRef.current = new Chart(canvas, {
            type: 'radar',
            data: {
                labels,
                datasets: [
                    {
                        label: 'Kompetensi',
                        data: values,
                        backgroundColor: 'rgba(59, 130, 246, 0.18)',
                        borderColor: '#3b82f6',
                        borderWidth: 2,
                        pointBackgroundColor: '#3b82f6',
                        pointBorderColor: '#fff',
                        pointRadius: 4,
                        pointHoverRadius: 5,
                    },
                ],
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                animation: false,
                plugins: {
                    legend: {
                        display: false,
                    },
                    tooltip: {
                        enabled: true,
                    },
                },
                scales: {
                    r: {
                        beginAtZero: true,
                        min: 0,
                        max: 100,
                        ticks: {
                            stepSize: 25,
                            display: false,
                        },
                        pointLabels: {
                            color: '#475569',
                            font: {
                                size: 10,
                            },
                        },
                        grid: {
                            color: 'rgba(148, 163, 184, 0.35)',
                        },
                        angleLines: {
                            color: 'rgba(148, 163, 184, 0.35)',
                        },
                    },
                },
            },
        });

        return () => {
            if (chartRef.current) {
                chartRef.current.destroy();
                chartRef.current = null;
            }

            const chartFromCanvas = Chart.getChart(canvas);

            if (chartFromCanvas) {
                chartFromCanvas.destroy();
            }
        };
    }, [metrics]);

    return (
        <canvas
            ref={canvasRef}
            width={260}
            height={220}
            role="img"
            aria-label="Radar chart kompetensi pegawai"
            className="block h-55 w-65"
        />
    );
}

function renderMappingProfile(content: string) {
    const profile = parseMappingProfileData(content);
    const colors = ['#3b82f6', '#10b981', '#f59e0b', '#8b5cf6', '#ef4444', '#06b6d4', '#f97316', '#84cc16', '#ec4899', '#14b8a6', '#a855f7', '#fb923c'];
    const metricsToRender = profile.metrics.filter((metric) => metric.value > 0 || metric.label.toLowerCase().includes('n talent'));

    return (
        <div className="w-full max-w-full rounded-xl border border-slate-200 bg-[#f5f5f5] p-3 text-slate-800 shadow-sm">
            <div className="text-[15px] font-bold text-slate-800">Pemetaan Pegawai — Detail</div>
            <div className="mb-3 text-[11px] text-slate-500">NIP: {profile.nip} &nbsp;|&nbsp; Status: 200 OK &nbsp;|&nbsp; 1 record</div>

            <div className="mb-3 flex flex-wrap gap-2">
                <span className="inline-flex rounded-full border border-blue-200 bg-blue-100 px-2 py-1 text-[11px] font-semibold text-blue-700">Box: {profile.box || '—'}</span>
                <span className="inline-flex rounded-full border border-emerald-200 bg-emerald-100 px-2 py-1 text-[11px] font-semibold text-emerald-700">TW {profile.triwulan || '—'}</span>
                <span className="inline-flex rounded-full border border-amber-200 bg-amber-100 px-2 py-1 text-[11px] font-semibold text-amber-700">Tahun {profile.tahunTriwulan || '—'}</span>
            </div>

            <div className="mb-4 grid gap-2 sm:grid-cols-2 lg:grid-cols-4">
                <div className="rounded-lg border border-slate-200 bg-slate-100 px-3 py-2 text-center">
                    <div className="mb-1 text-[10px] uppercase tracking-wide text-slate-500">Total Sumbu</div>
                    <div className="text-[22px] font-bold text-amber-500">{profile.totalSumbu > 0 ? profile.totalSumbu.toFixed(2) : '—'}</div>
                </div>
                <div className="rounded-lg border border-slate-200 bg-slate-100 px-3 py-2 text-center">
                    <div className="mb-1 text-[10px] uppercase tracking-wide text-slate-500">N Talent</div>
                    <div className="text-[22px] font-bold text-blue-500">{profile.nTalent > 0 ? profile.nTalent.toFixed(2) : '—'}</div>
                </div>
                <div className="rounded-lg border border-slate-200 bg-slate-100 px-3 py-2 text-center">
                    <div className="mb-1 text-[10px] uppercase tracking-wide text-slate-500">Sumbu Y</div>
                    <div className="text-[22px] font-bold text-emerald-500">{profile.sumbuY > 0 ? profile.sumbuY.toFixed(0) : '—'}</div>
                </div>
                <div className="rounded-lg border border-slate-200 bg-slate-100 px-3 py-2 text-center">
                    <div className="mb-1 text-[10px] uppercase tracking-wide text-slate-500">Sumbu X</div>
                    <div className="text-[22px] font-bold text-slate-800">{profile.sumbuX > 0 ? profile.sumbuX.toFixed(2) : '—'}</div>
                </div>
            </div>

            <div className="my-3 border-t border-slate-200" />

            <div className="mb-3 text-[12px] font-semibold uppercase tracking-wide text-slate-500">Komponen Nilai</div>
            <div className="space-y-2">
                {metricsToRender.length > 0 ? metricsToRender.map((metric, index) => {
                    const pct = Math.min((metric.value / metric.max) * 100, 100);

                    return (
                        <div key={`${metric.label}-${index}`} className="flex items-center gap-2">
                            <div className="w-32.5 shrink-0 text-right text-[11px] text-slate-500">{metric.label}</div>
                            <div className="h-3.5 flex-1 overflow-hidden rounded bg-slate-200">
                                <div
                                    className="h-full rounded transition-all duration-700"
                                    style={{ width: `${pct}%`, background: colors[index % colors.length] }}
                                />
                            </div>
                            <div className="w-9.5 text-left text-[11px] font-semibold text-slate-700">{metric.value || '—'}</div>
                        </div>
                    );
                }) : (
                    <div className="text-sm text-slate-500">Data komponen nilai belum tersedia.</div>
                )}
            </div>

            <div className="my-3 border-t border-slate-200" />

            <div className="mb-2 text-[12px] font-semibold uppercase tracking-wide text-slate-500">Radar Kompetensi</div>
            <div className="flex justify-center">
                <RadarChart metrics={metricsToRender.length > 0 ? metricsToRender : [{ label: 'Data', value: 0, max: 100 }]} />
            </div>
        </div>
    );
}

function isPersonalProfileResponse(content: string): boolean {
    const normalized = content.toLowerCase().replace(/[_()-]/g, ' ');

    const hasProfileSignals = normalized.includes('nama:')
        && normalized.includes('nip:')
        && (
            normalized.includes('unit kerja:')
            || normalized.includes('jabatan:')
            || normalized.includes('pangkat:')
        );

    const hasDirectPersonalQuery = normalized.includes('data kepegawaian')
        || normalized.includes('data pribadi')
        || normalized.includes('data diri')
        || normalized.includes('data pegawai')
        || normalized.includes('data saya')
        || normalized.includes('profil saya')
        || normalized.includes('informasi terkait saya')
        || normalized.includes('pangkat saya')
        || normalized.includes('jabatan saya')
        || normalized.includes('unit kerja saya');

    return !isMappingResponse(content) && (hasProfileSignals || hasDirectPersonalQuery);
}

function parsePersonalProfileData(content: string) {
    const lines = content
        .split(/\r?\n/)
        .map((line) => line.trim())
        .filter(Boolean);

    const data: Record<string, string> = {};

    for (const line of lines) {
        if (line.startsWith('DATA PRIBADI') || line.startsWith('Sumber data:') || line.startsWith('HASIL PEMETAAN')) {
            continue;
        }

        const index = line.indexOf(':');

        if (index <= 0) {
            continue;
        }

        const label = line.slice(0, index).trim();
        const value = line.slice(index + 1).trim();

        if (label && value) {
            data[label] = value;
        }
    }

    const getDataValue = (...labels: string[]) => {
        for (const label of labels) {
            const direct = data[label] || data[label.toLowerCase()] || data[label.toUpperCase()];

            if (direct) {
                return direct;
            }

            const match = lines.find((line) => {
                const normalized = line.toLowerCase();

                return labels.some((candidate) => normalized.startsWith(candidate.toLowerCase() + ':'));
            });

            if (match) {
                const idx = match.indexOf(':');

                if (idx >= 0) {
                    return match.slice(idx + 1).trim();
                }
            }
        }

        return '—';
    };

    const name = getDataValue('Nama', 'Nama Pegawai') || 'Pegawai';
    const nip = getDataValue('NIP') || '—';
    const jabatan = getDataValue('Jabatan', 'Jabatan Saat Ini', 'Jabatan Pegawai') || '—';
    const pangkat = getDataValue('Pangkat', 'Pangkat / Golongan', 'Golongan') || '—';
    const unitKerja = getDataValue('Unit Kerja', 'Unit Organisasi', 'Unit Kerja Saat Ini') || '—';
    const tempatTanggalLahir = getDataValue('Tempat / Tgl Lahir', 'Tempat, Tanggal Lahir', 'Tempat Tanggal Lahir', 'Tempat Lahir', 'Tanggal Lahir') || '—';
    const jenisKelamin = getDataValue('Jenis Kelamin', 'Kelamin') || '—';
    const agama = getDataValue('Agama') || '—';
    const statusPerkawinan = getDataValue('Status Perkawinan', 'Status Kawin', 'Status Nikah') || '—';
    const tmtCpns = getDataValue('TMT CPNS', 'Tmt CPNS') || '—';
    const tmtPns = getDataValue('TMT PNS', 'Tmt PNS') || '—';
    const masaKerja = getDataValue('Masa Kerja', 'Masa Kerja Golongan') || '—';
    const lamaJabatan = getDataValue('Lama Jabatan', 'Lama Jabatan Saat Ini') || '—';
    const pendidikanTerakhir = getDataValue('Pendidikan Terakhir', 'Pendidikan', 'Ijazah Terakhir') || '—';

    const badges = [
        { label: 'Aktif', tone: 'bg-emerald-100 text-emerald-700 border-emerald-200' },
        { label: 'PNS', tone: 'bg-blue-100 text-blue-700 border-blue-200' },
        { label: pangkat !== '—' ? pangkat : 'Golongan', tone: 'bg-slate-100 text-slate-700 border-slate-200' },
    ];

    const infoCards = [
        { label: 'Tempat / Tgl Lahir', value: tempatTanggalLahir },
        { label: 'Jenis Kelamin', value: jenisKelamin },
        { label: 'Agama', value: agama },
        { label: 'Status Perkawinan', value: statusPerkawinan },
        { label: 'TMT CPNS', value: tmtCpns },
        { label: 'TMT PNS', value: tmtPns },
    ];

    return {
        name,
        nip,
        jabatan,
        pangkat,
        unitKerja,
        lamaJabatan,
        masaKerja,
        pendidikanTerakhir,
        badges,
        infoCards,
    };
}

function PersonalProfileCard({ profile }: { profile: ReturnType<typeof parsePersonalProfileData> }) {
    const tabs = ['Info Umum', 'Riwayat Jabatan', 'Kepangkatan', 'Kinerja & Nilai 360', 'Penghargaan'] as const;
    type ProfileTab = (typeof tabs)[number];

    const [activeTab, setActiveTab] = useState<ProfileTab>('Info Umum');

    const tabContent: Record<ProfileTab, ReactNode> = {
        'Info Umum': (
            <div className="space-y-3 p-4">
                <div className="grid gap-3 md:grid-cols-2">
                    {profile.infoCards.map((card) => (
                        <div key={card.label} className="rounded-lg border border-slate-200 bg-white px-3 py-2">
                            <div className="text-[10px] font-medium uppercase tracking-wide text-slate-500">{card.label}</div>
                            <div className="mt-1 text-sm font-medium text-slate-800">{card.value}</div>
                        </div>
                    ))}
                </div>

                <div className="rounded-lg border border-slate-200 bg-white px-3 py-2">
                    <div className="text-[10px] font-medium uppercase tracking-wide text-slate-500">Unit Kerja</div>
                    <div className="mt-1 text-sm font-medium text-slate-800">{profile.unitKerja}</div>
                </div>

                <div className="grid gap-3 md:grid-cols-3">
                    <div className="rounded-lg border border-slate-200 bg-white px-3 py-2">
                        <div className="text-[10px] font-medium uppercase tracking-wide text-slate-500">Pangkat</div>
                        <div className="mt-1 text-sm font-medium text-slate-800">{profile.pangkat}</div>
                    </div>
                    <div className="rounded-lg border border-slate-200 bg-white px-3 py-2">
                        <div className="text-[10px] font-medium uppercase tracking-wide text-slate-500">Jabatan</div>
                        <div className="mt-1 text-sm font-medium text-slate-800">{profile.jabatan}</div>
                    </div>
                    <div className="rounded-lg border border-slate-200 bg-white px-3 py-2">
                        <div className="text-[10px] font-medium uppercase tracking-wide text-slate-500">Lama Jabatan</div>
                        <div className="mt-1 text-sm font-medium text-slate-800">{profile.lamaJabatan}</div>
                    </div>
                </div>

                <div className="rounded-lg border border-slate-200 bg-white px-3 py-2">
                    <div className="text-[10px] font-medium uppercase tracking-wide text-slate-500">Pendidikan Terakhir</div>
                    <div className="mt-1 text-sm font-medium text-slate-800">{profile.pendidikanTerakhir || '—'}</div>
                </div>
            </div>
        ),
        'Riwayat Jabatan': (
            <div className="space-y-3 p-4">
                <div className="rounded-lg border border-slate-200 bg-white px-3 py-2">
                    <div className="text-[10px] font-medium uppercase tracking-wide text-slate-500">Jabatan Saat Ini</div>
                    <div className="mt-1 text-sm font-medium text-slate-800">{profile.jabatan}</div>
                </div>
                <div className="rounded-lg border border-slate-200 bg-white px-3 py-2">
                    <div className="text-[10px] font-medium uppercase tracking-wide text-slate-500">Unit Kerja</div>
                    <div className="mt-1 text-sm font-medium text-slate-800">{profile.unitKerja}</div>
                </div>
                <div className="rounded-lg border border-slate-200 bg-white px-3 py-2">
                    <div className="text-[10px] font-medium uppercase tracking-wide text-slate-500">Lama Jabatan</div>
                    <div className="mt-1 text-sm font-medium text-slate-800">{profile.lamaJabatan}</div>
                </div>
            </div>
        ),
        'Kepangkatan': (
            <div className="space-y-3 p-4">
                <div className="rounded-lg border border-slate-200 bg-white px-3 py-2">
                    <div className="text-[10px] font-medium uppercase tracking-wide text-slate-500">Pangkat / Golongan</div>
                    <div className="mt-1 text-sm font-medium text-slate-800">{profile.pangkat}</div>
                </div>
                <div className="rounded-lg border border-slate-200 bg-white px-3 py-2">
                    <div className="text-[10px] font-medium uppercase tracking-wide text-slate-500">Masa Kerja</div>
                    <div className="mt-1 text-sm font-medium text-slate-800">{profile.masaKerja || '—'}</div>
                </div>
                <div className="rounded-lg border border-slate-200 bg-white px-3 py-2">
                    <div className="text-[10px] font-medium uppercase tracking-wide text-slate-500">NIP</div>
                    <div className="mt-1 text-sm font-medium text-slate-800">{profile.nip}</div>
                </div>
            </div>
        ),
        'Kinerja & Nilai 360': (
            <div className="space-y-3 p-4">
                <div className="rounded-lg border border-slate-200 bg-white px-3 py-2">
                    <div className="text-[10px] font-medium uppercase tracking-wide text-slate-500">Status Kinerja</div>
                    <div className="mt-1 text-sm font-medium text-slate-800">Aktif / Baik</div>
                </div>
                <div className="rounded-lg border border-slate-200 bg-white px-3 py-2">
                    <div className="text-[10px] font-medium uppercase tracking-wide text-slate-500">Nilai 360</div>
                    <div className="mt-1 text-sm font-medium text-slate-800">Rata-rata evaluasi internal tersedia</div>
                </div>
                <div className="rounded-lg border border-slate-200 bg-white px-3 py-2">
                    <div className="text-[10px] font-medium uppercase tracking-wide text-slate-500">Unit Kerja</div>
                    <div className="mt-1 text-sm font-medium text-slate-800">{profile.unitKerja}</div>
                </div>
            </div>
        ),
        'Penghargaan': (
            <div className="space-y-3 p-4">
                <div className="rounded-lg border border-slate-200 bg-white px-3 py-2">
                    <div className="text-[10px] font-medium uppercase tracking-wide text-slate-500">Penghargaan</div>
                    <div className="mt-1 text-sm font-medium text-slate-800">Belum ada data penghargaan yang tercatat.</div>
                </div>
            </div>
        ),
    };

    return (
        <div className="w-full max-w-full overflow-hidden rounded-xl border border-slate-200 bg-[#f5f5f5] text-slate-800 shadow-sm">
            <div className="flex items-center gap-3 border-b border-slate-200 bg-[#f0f1f2] px-4 py-3">
                <div className="flex h-12 w-12 items-center justify-center rounded-full border border-slate-300 bg-slate-200 text-sm font-bold text-slate-600">
                    {profile.name
                        .split(' ')
                        .slice(0, 2)
                        .map((part) => part[0])
                        .join('')
                        .toUpperCase() || 'P'}
                </div>
                <div className="min-w-0 flex-1">
                    <div className="text-lg font-bold uppercase tracking-tight text-slate-800">{profile.name}</div>
                    <div className="text-[11px] text-slate-600">
                        NIP: {profile.nip}
                    </div>
                    <div className="mt-1 flex flex-wrap gap-2">
                        {profile.badges.map((badge) => (
                            <span
                                key={badge.label}
                                className={`inline-flex rounded-full border px-2 py-0.5 text-[10px] font-semibold ${badge.tone}`}
                            >
                                {badge.label}
                            </span>
                        ))}
                    </div>
                </div>
            </div>

            <div className="flex flex-wrap gap-2 border-b border-slate-200 bg-[#f8f8f8] px-4 py-2">
                {tabs.map((tab) => (
                    <button
                        key={tab}
                        type="button"
                        aria-pressed={activeTab === tab}
                        onClick={() => setActiveTab(tab)}
                        className={`rounded-md border px-2.5 py-1 text-[11px] font-medium transition-colors ${
                            activeTab === tab
                                ? 'border-slate-400 bg-white text-slate-800'
                                : 'border-slate-200 bg-transparent text-slate-600 hover:bg-white'
                        }`}
                    >
                        {tab}
                    </button>
                ))}
            </div>

            {tabContent[activeTab]}
        </div>
    );
}

function renderPersonalProfile(content: string) {
    const profile = parsePersonalProfileData(content);

    return <PersonalProfileCard profile={profile} />;
}

export default function ChatMessage({ message }: ChatMessageProps) {
    const isAi = message.role === 'assistant';

    return (
        <div className={`flex gap-4 mb-6 ${isAi ? '' : 'flex-row-reverse'}`}>

            <div className={`h-8 w-8 shrink-0 rounded-full flex items-center justify-center ${
                isAi ? 'bg-primary text-primary-foreground' : 'bg-muted text-muted-foreground'
            }`}>
                {isAi ? <Bot className="h-5 w-5" /> : <User className="h-5 w-5" />}
            </div>

            <div className={`flex flex-col max-w-[85%] ${isAi ? 'items-start' : 'items-end'}`}>
                <div className={`rounded-2xl px-4 py-3 text-sm shadow-sm ${
                    isAi 
                        ? 'bg-card text-card-foreground border border-border rounded-tl-sm' 
                        : 'bg-primary text-primary-foreground rounded-tr-sm'
                }`}>
                    {isAi && isPersonalProfileResponse(message.content) ? (
                        renderPersonalProfile(message.content)
                    ) : isAi && isBangkomResponse(message.content) ? (
                        renderBangkomMessage(message.content)
                    ) : isAi && isMappingResponse(message.content) ? (
                        renderMappingProfile(message.content)
                    ) : (
                        <p className="whitespace-pre-wrap leading-relaxed wrap-break-words">
                            {message.content}
                        </p>
                    )}

                    {isAi && message.sources && message.sources.length > 0 && (
                        <div className="mt-4 pt-3 border-t border-border/50">
                            <p className="mb-2 gap-1 text-[10px] font-semibold text-muted-foreground uppercase tracking-wider flex items-center">
                                <FileText className="h-3 w-3" />
                                Sumber Referensi
                            </p>
                            
                            <div className="flex-col gap-2 flex">
                                {message.sources.map((source, idx) => {
                                    const safeScore = typeof source.similarity_score === 'number' ? source.similarity_score : 0;
                                    const safeFilename = source.filename && source.filename.trim() !== '' 
                                        ? source.filename 
                                        : 'Dokumen Tidak Diketahui';

                                    return (
                                        <div 
                                            key={idx} 
                                            className="gap-2 p-2 text-xs bg-background/50 rounded-md border border-border/50 flex items-center hover:bg-accent/50 transition-colors"
                                        >
                                            <span className="flex-1 font-medium text-foreground truncate" title={safeFilename}>
                                                {safeFilename}
                                            </span>
                                            
                                            <span className="px-1.5 py-0.5 text-[10px] font-mono text-muted-foreground bg-muted rounded whitespace-nowrap">
                                                 {(safeScore * 100).toFixed(1)}% match
                                            </span>
                                        </div>
                                    );
                                })}
                            </div>
                        </div>
                    )}
                </div>
                
                {/* Timestamp (Opsional) */}
                <span className="mt-1 px-1 text-[10px] text-muted-foreground">
                    {new Date(message.created_at).toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit' })}
                </span>
            </div>
        </div>
    );
}
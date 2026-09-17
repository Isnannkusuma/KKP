import { Head, router } from '@inertiajs/react';
import { CheckCircle2, ChevronDown, ChevronLeft, ChevronRight, CircleHelp, Download, MessageSquareWarning, MessagesSquare, RefreshCw, ThumbsUp, Trash2 } from 'lucide-react';
import { useState } from 'react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle, DialogTrigger } from '@/components/ui/dialog';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { GLASS_BAR, GLASS_CARD, GLASS_PAGE_BG, GLASS_PILL, PRIMARY_BUTTON } from '@/lib/glass-theme';
import { cn } from '@/lib/utils';
import { dashboard } from '@/routes';

interface QuestionSummary {
    question: string;
    total: number;
    askedAt: string | null;
}

interface UnansweredQuestion {
    id: string;
    question: string;
    answer: string | null;
    askedAt: string | null;
}

interface DashboardProps {
    period: number;
    stats: {
        total: number;
        answered: number;
        unanswered: number;
        feedbackCount: number;
        positiveFeedback: number;
        satisfactionRate: number;
    };
    daily: DailyActivity[];
    contextGroups: PaginationData<ContextGroup>;
    popularQuestions: PaginationData<QuestionSummary>;
    unansweredQuestions: UnansweredQuestion[];
}

interface PaginationData<T> {
    data: T[];
    current_page: number;
    last_page: number;
    total: number;
}

interface ContextGroup {
    context: string;
    total: number;
    unanswered: number;
    questions: QuestionSummary[];
}

interface DailyActivity {
    label: string;
    total: number;
    answered: number;
    unanswered: number;
}

const formatDate = (date: string | null): string => {
    if (!date) {
        return '-';
    }

    return new Intl.DateTimeFormat('id-ID', {
        dateStyle: 'medium',
        timeStyle: 'short',
    }).format(new Date(date));
};

export default function Dashboard({ period, stats, daily, contextGroups, popularQuestions, unansweredQuestions }: DashboardProps) {
    const answerRate = stats.total === 0 ? 0 : Math.round((stats.answered / stats.total) * 100);
    const maximumActivity = Math.max(...daily.map((item) => item.total), 1);
    const maximumResolution = Math.max(...daily.map((item) => item.total), 1);
    const changePeriod = (nextPeriod: number) => router.get('/dashboard', { period: nextPeriod }, { preserveScroll: true });
    const deleteAudits = (questions?: string[], deletionPeriod = period) => {
        const target = questions ? 'pertanyaan yang dipilih' : `seluruh data ${deletionPeriod} hari terakhir`;

        if (!window.confirm(`Hapus ${target}? Tindakan ini tidak dapat dibatalkan.`)) {
            return;
        }

        router.delete('/dashboard/audits', { data: { period: deletionPeriod, questions }, preserveScroll: true });
    };

    return (
        <>
            <Head title="Monitoring Chatbot" />
            <div className={cn('flex h-full flex-1 flex-col gap-6 p-4 md:p-8', GLASS_PAGE_BG)}>
                <div className={cn('flex flex-col gap-4 p-5 lg:flex-row lg:items-center lg:justify-between', GLASS_BAR)}>
                    <div>
                        <div className="flex items-center gap-2">
                            <span className="size-2 rounded-full bg-emerald-500" />
                            <p className="text-xs font-medium text-emerald-600">Sistem aktif &middot; Data real-time</p>
                        </div>
                        <h1 className="mt-1 text-2xl font-bold tracking-tight text-slate-900">Monitoring Chatbot</h1>
                        <p className="text-sm text-slate-500">Analisis penggunaan, resolusi jawaban, dan kepuasan pengguna.</p>
                    </div>
                    <div className="flex flex-wrap items-center gap-2">
                        <label className="sr-only" htmlFor="period">Periode</label>
                        <select id="period" value={period} onChange={(event) => changePeriod(Number(event.target.value))} className={cn('h-10 px-4 text-sm text-slate-700 outline-none focus:ring-2 focus:ring-blue-200', GLASS_PILL)}>
                            <option value={7}>7 hari terakhir</option>
                            <option value={30}>30 hari terakhir</option>
                            <option value={90}>90 hari terakhir</option>
                        </select>
                        <button type="button" onClick={() => changePeriod(period)} title="Muat ulang data" aria-label="Muat ulang data" className={cn('grid size-10 place-items-center text-blue-600 transition-colors hover:bg-blue-50', GLASS_PILL)}><RefreshCw className="size-4" /></button>
                        <ExportDialog />
                        <DeleteRangeDialog onDelete={deleteAudits} />
                    </div>
                </div>

                <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                    <MetricCard label="Total pertanyaan" value={stats.total} detail={`Dalam ${period} hari terakhir`} icon={MessagesSquare} />
                    <MetricCard label="Tingkat resolusi" value={`${answerRate}%`} detail={`${stats.answered} pertanyaan terjawab`} icon={CheckCircle2} tone="success" />
                    <MetricCard label="Perlu evaluasi" value={stats.unanswered} detail="Belum ditemukan jawaban relevan" icon={MessageSquareWarning} tone="warning" />
                    <MetricCard label="Kepuasan pengguna" value={stats.feedbackCount ? `${stats.satisfactionRate}%` : '-'} detail={stats.feedbackCount ? `${stats.positiveFeedback} dari ${stats.feedbackCount} feedback positif` : 'Belum ada feedback'} icon={ThumbsUp} tone="info" />
                </div>

                {/* Baris 1 — dua chart sejajar kiri & kanan */}
                <div className="grid items-stretch gap-6 xl:grid-cols-2">
                    <ActivityChart daily={daily} maximum={maximumActivity} period={period} />
                    <ResolutionChart daily={daily} maximum={maximumResolution} />
                </div>

                {/* Baris 2 — konteks pertanyaan & pertanyaan populer sejajar kiri & kanan */}
                <div className="grid items-start gap-6 xl:grid-cols-2">
                    <Card className={GLASS_CARD}>
                        <CardHeader>
                            <CardTitle className="text-slate-800">Kelompok konteks pertanyaan</CardTitle>
                            <CardDescription className="text-slate-500">Dibuat otomatis dari kesamaan kata pada pertanyaan pengguna.</CardDescription>
                        </CardHeader>
                        <CardContent className="space-y-4">
                            {contextGroups.data.length > 0 ? contextGroups.data.map((group) => (
                                <details key={group.context} className="group border-b border-blue-50 pb-4 last:border-0 last:pb-0">
                                    <summary className="flex cursor-pointer list-none items-center justify-between gap-3">
                                        <div className="flex min-w-0 items-center gap-2">
                                            <ChevronDown className="size-4 shrink-0 text-blue-400 transition-transform group-open:rotate-180" />
                                            <p className="truncate text-sm font-semibold text-slate-800">{group.context}</p>
                                        </div>
                                        <div className="flex shrink-0 items-center gap-2">
                                            {group.unanswered > 0 && <Badge variant="outline" className="border-amber-200 bg-amber-50 text-amber-700">{group.unanswered} evaluasi</Badge>}
                                            <Badge variant="secondary" className="border-blue-100 bg-blue-50 text-blue-700">{group.total} pertanyaan</Badge>
                                            <button type="button" title="Hapus konteks" aria-label={`Hapus konteks ${group.context}`} onClick={(event) => {
                                                event.preventDefault();
                                                deleteAudits(group.questions.map((question) => question.question));
                                            }} className="grid size-7 place-items-center rounded-md text-slate-400 hover:bg-red-50 hover:text-red-600"><Trash2 className="size-3.5" /></button>
                                        </div>
                                    </summary>
                                    <div className="mt-2 h-1.5 overflow-hidden rounded-full bg-blue-50"><div className="h-full rounded-full bg-blue-500" style={{ width: `${Math.max((group.total / Math.max(stats.total, 1)) * 100, 4)}%` }} /></div>
                                    <div className="mt-3 space-y-2 border-l-2 border-blue-100 pl-3">
                                        {group.questions.map((question) => <div key={question.question} className="flex items-start justify-between gap-3 text-xs">
                                            <div><p className="leading-5 text-slate-600">{question.question}</p><p className="mt-0.5 text-[11px] text-slate-400">Terakhir diajukan: {formatDate(question.askedAt)}</p></div>
                                            <div className="flex items-center gap-1"><Badge variant="outline" className="shrink-0 border-blue-100 text-blue-700">{question.total}x</Badge><button type="button" title="Hapus pertanyaan" aria-label={`Hapus ${question.question}`} onClick={() => deleteAudits([question.question])} className="grid size-6 place-items-center rounded text-slate-400 hover:bg-red-50 hover:text-red-600"><Trash2 className="size-3" /></button></div>
                                        </div>)}
                                    </div>
                                </details>
                            )) : <EmptyState message="Belum ada konteks pertanyaan untuk dikelompokkan." />}
                            <Pagination pagination={contextGroups} pageKey="context_page" period={period} />
                        </CardContent>
                    </Card>

                    <PopularQuestionsCard popularQuestions={popularQuestions} period={period} onDelete={deleteAudits} />
                </div>

                {/* Baris 3 — pertanyaan belum terjawab, full width ujung ke ujung */}
                <Card className={cn('w-full', GLASS_CARD)}>
                    <CardHeader>
                        <CardTitle className="text-slate-800">Pertanyaan belum dapat dijawab</CardTitle>
                        <CardDescription className="text-slate-500">Gunakan daftar ini untuk menentukan dokumen atau jawaban yang perlu ditambahkan.</CardDescription>
                    </CardHeader>
                    <CardContent>
                        {unansweredQuestions.length > 0 ? (
                            <div className="w-full overflow-x-auto">
                                <Table>
                                    <TableHeader>
                                        <TableRow className="border-blue-50">
                                            <TableHead className="text-slate-700">Pertanyaan</TableHead>
                                            <TableHead className="w-44 text-slate-700">Waktu</TableHead>
                                            <TableHead className="w-32 text-slate-700">Status</TableHead>
                                        </TableRow>
                                    </TableHeader>
                                    <TableBody>
                                        {unansweredQuestions.map((item) => (
                                            <TableRow key={item.id} className="border-blue-50">
                                                <TableCell className="whitespace-normal font-medium text-slate-700">{item.question}</TableCell>
                                                <TableCell className="text-slate-500">{formatDate(item.askedAt)}</TableCell>
                                                <TableCell><Badge variant="destructive">Belum terjawab</Badge></TableCell>
                                            </TableRow>
                                        ))}
                                    </TableBody>
                                </Table>
                            </div>
                        ) : <EmptyState message="Semua pertanyaan yang tercatat telah dapat dijawab." />}
                    </CardContent>
                </Card>
            </div>
        </>
    );
}

function PopularQuestionsCard({ popularQuestions, period, onDelete }: { popularQuestions: PaginationData<QuestionSummary>; period: number; onDelete: (questions?: string[]) => void }) {
    return (
        <Card className={GLASS_CARD}>
            <CardHeader>
                <CardTitle className="text-slate-800">Pertanyaan yang sering muncul</CardTitle>
                <CardDescription className="text-slate-500">Topik yang paling banyak dicari pada periode ini.</CardDescription>
            </CardHeader>
            <CardContent className="space-y-3">
                {popularQuestions.data.length > 0 ? popularQuestions.data.map((item, index) => (
                    <div key={item.question} className="flex items-start gap-3 border-b border-blue-50 pb-3 last:border-0 last:pb-0">
                        <span className="flex size-6 shrink-0 items-center justify-center rounded-md bg-blue-50 text-xs font-semibold text-blue-700">{index + 1}</span>
                        <div className="min-w-0 flex-1"><p className="text-sm leading-5 break-words text-slate-700">{item.question}</p><p className="mt-1 text-xs text-slate-400">Terakhir diajukan: {formatDate(item.askedAt)}</p></div>
                        <div className="flex items-center gap-1"><Badge variant="secondary" className="border-blue-100 bg-blue-50 text-blue-700">{item.total}x</Badge><button type="button" title="Hapus pertanyaan" aria-label={`Hapus ${item.question}`} onClick={() => onDelete([item.question])} className="grid size-7 place-items-center rounded-md text-slate-400 hover:bg-red-50 hover:text-red-600"><Trash2 className="size-3.5" /></button></div>
                    </div>
                )) : <EmptyState message="Belum ada pertanyaan yang tercatat." />}
                <Pagination pagination={popularQuestions} pageKey="questions_page" period={period} />
            </CardContent>
        </Card>
    );
}

function Pagination<T>({ pagination, pageKey, period }: { pagination: PaginationData<T>; pageKey: string; period: number }) {
    if (pagination.last_page <= 1) {
        return null;
    }

    const changePage = (page: number) => router.get('/dashboard', { period, [pageKey]: page }, { preserveScroll: true });

    return <div className="flex items-center justify-between border-t border-blue-50 pt-3 text-xs text-slate-500"><span>Halaman {pagination.current_page} dari {pagination.last_page}</span><div className="flex gap-1"><Button variant="outline" size="icon" className="size-7 border-blue-100 text-blue-600 hover:bg-blue-50" disabled={pagination.current_page === 1} onClick={() => changePage(pagination.current_page - 1)}><ChevronLeft /></Button><Button variant="outline" size="icon" className="size-7 border-blue-100 text-blue-600 hover:bg-blue-50" disabled={pagination.current_page === pagination.last_page} onClick={() => changePage(pagination.current_page + 1)}><ChevronRight /></Button></div></div>;
}

const exportFields = [
    { id: 'asked_at', label: 'Waktu pertanyaan' },
    { id: 'query', label: 'Pertanyaan' },
    { id: 'answer', label: 'Jawaban chatbot' },
    { id: 'response_status', label: 'Status respons' },
    { id: 'feedback', label: 'Feedback pengguna' },
    { id: 'result_count', label: 'Jumlah referensi' },
] as const;

function ExportDialog() {
    const [period, setPeriod] = useState(30);
    const [selectedFields, setSelectedFields] = useState<string[]>(exportFields.map((field) => field.id));

    const toggleField = (field: string, checked: boolean) => {
        setSelectedFields((current) => checked ? [...current, field] : current.filter((item) => item !== field));
    };
    const download = () => {
        if (selectedFields.length === 0) {
            return;
        }

        const parameters = new URLSearchParams({ period: String(period) });
        selectedFields.forEach((field) => parameters.append('fields[]', field));
        window.location.assign(`/dashboard/export?${parameters.toString()}`);
    };

    return <Dialog>
        <DialogTrigger asChild><Button className={cn(PRIMARY_BUTTON, 'rounded-full')}><Download /> Unduh laporan</Button></DialogTrigger>
        <DialogContent className="sm:max-w-md">
            <DialogHeader><DialogTitle className="text-slate-900">Unduh laporan monitoring</DialogTitle><DialogDescription className="text-slate-500">Pilih rentang data dan kolom yang ingin dimasukkan dalam file Excel.</DialogDescription></DialogHeader>
            <div className="space-y-5 py-2">
                <div className="space-y-2"><label className="text-sm font-medium text-slate-700" htmlFor="export-period">Rentang waktu</label><select id="export-period" value={period} onChange={(event) => setPeriod(Number(event.target.value))} className="h-9 w-full rounded-md border bg-white px-3 text-sm text-slate-700"><option value={7}>7 hari terakhir</option><option value={15}>15 hari terakhir</option><option value={30}>30 hari terakhir</option><option value={90}>90 hari terakhir</option></select></div>
                <fieldset className="space-y-3"><legend className="text-sm font-medium text-slate-700">Kolom laporan</legend>{exportFields.map((field) => <label key={field.id} className="flex cursor-pointer items-center gap-3 text-sm text-slate-700"><Checkbox checked={selectedFields.includes(field.id)} onCheckedChange={(checked) => toggleField(field.id, checked === true)} />{field.label}</label>)}</fieldset>
            </div>
            <DialogFooter><Button type="button" className={PRIMARY_BUTTON} onClick={download} disabled={selectedFields.length === 0}><Download /> Unduh Excel</Button></DialogFooter>
        </DialogContent>
    </Dialog>;
}

function DeleteRangeDialog({ onDelete }: { onDelete: (questions?: string[], deletionPeriod?: number) => void }) {
    const [period, setPeriod] = useState(30);

    return <Dialog>
        <DialogTrigger asChild><Button variant="outline" className="rounded-full border-red-200 text-red-600 hover:bg-red-50"><Trash2 /> Hapus data</Button></DialogTrigger>
        <DialogContent className="sm:max-w-md"><DialogHeader><DialogTitle className="text-slate-900">Hapus data monitoring</DialogTitle><DialogDescription className="text-slate-500">Semua catatan chatbot pada rentang waktu terpilih akan dihapus permanen.</DialogDescription></DialogHeader><div className="py-2"><label className="text-sm font-medium text-slate-700" htmlFor="delete-period">Rentang waktu</label><select id="delete-period" value={period} onChange={(event) => setPeriod(Number(event.target.value))} className="mt-2 h-9 w-full rounded-md border bg-white px-3 text-sm text-slate-700"><option value={7}>7 hari terakhir</option><option value={15}>15 hari terakhir</option><option value={30}>30 hari terakhir</option><option value={90}>90 hari terakhir</option></select></div><DialogFooter><Button variant="destructive" type="button" onClick={() => onDelete(undefined, period)}><Trash2 /> Hapus permanen</Button></DialogFooter></DialogContent>
    </Dialog>;
}

function MetricCard({ label, value, detail, icon: Icon, tone }: { label: string; value: number | string; detail: string; icon: typeof MessagesSquare; tone?: 'success' | 'warning' | 'info' }) {
    const toneClass = {
        success: 'bg-emerald-50 text-emerald-600',
        warning: 'bg-amber-50 text-amber-600',
        info: 'bg-blue-50 text-blue-600',
    }[tone ?? 'info'];

    return (
        <Card className={GLASS_CARD}>
            <CardContent className="flex items-start justify-between gap-4 pt-6">
                <div>
                    <p className="text-sm font-medium text-slate-500">{label}</p>
                    <p className="mt-3 text-3xl font-bold tracking-tight text-slate-900">{value}</p>
                    <p className="mt-1 text-xs text-slate-400">{detail}</p>
                </div>
                <div className={`rounded-xl p-2.5 ${toneClass}`}>
                    <Icon className="size-5" />
                </div>
            </CardContent>
        </Card>
    );
}

function ActivityChart({ daily, maximum, period }: { daily: DailyActivity[]; maximum: number; period: number }) {
    const visibleLabels = Math.min(period, 6);

    return <Card className={cn('flex h-full flex-col', GLASS_CARD)}>
        <CardHeader>
            <CardTitle className="text-slate-800">Aktivitas pertanyaan</CardTitle>
            <CardDescription className="text-slate-500">Volume pertanyaan pengguna per hari.</CardDescription>
        </CardHeader>
        <CardContent className="flex flex-1 flex-col justify-end">
            <div className="flex h-52 items-end gap-px border-b border-l border-blue-100 px-3 pt-5 sm:gap-1">
                {daily.map((item) => <div key={item.label} className="group flex h-full min-w-0 flex-1 items-end" title={`${item.label}: ${item.total} pertanyaan`}>
                    <div className="w-full rounded-t-sm bg-blue-500/80 transition-colors group-hover:bg-blue-600" style={{ height: item.total ? `${Math.max((item.total / maximum) * 100, 3)}%` : '0%' }} />
                </div>)}
            </div>
            <div className="mt-2 flex justify-between text-xs text-slate-400">{daily.filter((_, index) => index % Math.ceil(daily.length / visibleLabels) === 0).map((item) => <span key={item.label}>{item.label}</span>)}</div>
        </CardContent>
    </Card>;
}

function ResolutionChart({ daily, maximum }: { daily: DailyActivity[]; maximum: number }) {
    const totalAnswered = daily.reduce((total, item) => total + item.answered, 0);
    const totalUnanswered = daily.reduce((total, item) => total + item.unanswered, 0);

    return <Card className={cn('flex h-full flex-col', GLASS_CARD)}>
        <CardHeader>
            <CardTitle className="text-slate-800">Resolusi per hari</CardTitle>
            <CardDescription className="text-slate-500">Perbandingan jawaban berhasil dan yang memerlukan evaluasi.</CardDescription>
        </CardHeader>
        <CardContent className="flex flex-1 flex-col justify-end">
            <div className="flex h-52 items-end gap-px border-b border-l border-blue-100 px-3 pt-5 sm:gap-1">
                {daily.map((item) => <div key={item.label} className="flex h-full min-w-0 flex-1 items-end gap-px" title={`${item.label}: ${item.answered} terjawab, ${item.unanswered} perlu evaluasi`}>
                    <div className="w-1/2 rounded-t-sm bg-blue-500/85" style={{ height: item.answered ? `${Math.max((item.answered / maximum) * 100, 3)}%` : '0%' }} />
                    <div className="w-1/2 rounded-t-sm bg-amber-500/85" style={{ height: item.unanswered ? `${Math.max((item.unanswered / maximum) * 100, 3)}%` : '0%' }} />
                </div>)}
            </div>
            <div className="mt-3 flex flex-wrap gap-x-5 gap-y-2 text-xs text-slate-500"><span className="flex items-center gap-1.5"><i className="size-2 rounded-full bg-blue-500" />Terjawab: {totalAnswered}</span><span className="flex items-center gap-1.5"><i className="size-2 rounded-full bg-amber-500" />Perlu evaluasi: {totalUnanswered}</span></div>
        </CardContent>
    </Card>;
}

function EmptyState({ message }: { message: string }) {
    return <div className="flex min-h-40 flex-col items-center justify-center gap-2 text-center text-sm text-slate-400"><CircleHelp className="size-5" />{message}</div>;
}

Dashboard.layout = {
    breadcrumbs: [
        {
            title: 'Dashboard',
            href: dashboard(),
        },
    ],
};
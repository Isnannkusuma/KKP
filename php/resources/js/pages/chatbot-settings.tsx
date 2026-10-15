import { Head, router } from '@inertiajs/react';
import { Bot, Save } from 'lucide-react';
import { useState } from 'react';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import { GLASS_BAR, GLASS_CARD, GLASS_PAGE_BG, PRIMARY_BUTTON } from '@/lib/glass-theme';
import { cn } from '@/lib/utils';

type StyleKey = 'tone' | 'warmth' | 'enthusiasm' | 'emoji' | 'layout' | 'headings_lists';

interface Settings {
    is_enabled: boolean;
    custom_instructions: string;
    assistant_name: string;
    assistant_role: string;
    additional_info: string;
    tone: string;
    warmth: string;
    enthusiasm: string;
    emoji: string;
    layout: string;
    headings_lists: string;
}

interface Props {
    settings: Settings;
    options: Record<StyleKey, { label: string; options: { value: string; label: string }[] }>;
}

export default function ChatbotSettings({ settings, options }: Props) {
    const [form, setForm] = useState<Settings>(settings);
    const [saving, setSaving] = useState(false);
    const [errors, setErrors] = useState<Record<string, string>>({});

    const set = <K extends keyof Settings>(key: K, value: Settings[K]) => setForm((prev) => ({ ...prev, [key]: value }));

    const save = () => {
        router.put('/pengaturan-chatbot', { ...form }, {
            preserveScroll: true,
            onStart: () => setSaving(true),
            onFinish: () => setSaving(false),
            onError: (e) => setErrors(e),
            onSuccess: () => setErrors({}),
        });
    };

    return (
        <>
            <Head title="Pengaturan Chatbot" />
            <div className={cn('flex h-full flex-1 flex-col gap-6 p-4 md:p-8' , GLASS_PAGE_BG)}>
                <div className={cn('flex flex-col gap-4 p-5 lg:flex-row lg:items-center lg:justify-between', GLASS_BAR)}>
                    <div>
                        <div className="flex items-center gap-2">
                            <Bot className="size-4 text-blue-500" />
                            <p className="text-xs font-medium text-blue-600">Berlaku untuk seluruh pengguna chatbot</p>
                        </div>
                        <h1 className="mt-1 text-2xl font-bold tracking-tight text-slate-900">Personalisasi Chatbot</h1>
                        <p className="text-sm text-slate-900">Atur gaya, nada, dan instruksi khusus yang dipakai AI saat menjawab.</p>
                    </div>
                    <Button onClick={save} disabled={saving} className={PRIMARY_BUTTON}>
                        <Save className="size-4" /> {saving ? 'Menyimpan...' : 'Simpan'}
                    </Button>
                </div>

                <Card className={cn('text-black', GLASS_CARD)}>
                    <CardHeader>
                        <CardTitle>Aktifkan personalisasi</CardTitle>
                        <CardDescription>Jika dimatikan, chatbot memakai perilaku bawaan tanpa pengaturan di bawah.</CardDescription>
                    </CardHeader>
                    <CardContent>
                        <button
                            type="button"
                            role="switch"
                            aria-checked={form.is_enabled}
                            onClick={() => set('is_enabled', !form.is_enabled)}
                            className={cn('relative h-6 w-11 rounded-full transition-colors', form.is_enabled ? 'bg-blue-600' : 'bg-slate-300')}
                        >
                            <span
                                className={cn(
                                    'absolute top-0.5 left-0.5 size-5 rounded-full bg-white transition-transform',
                                    form.is_enabled && 'translate-x-5',
                                )}
                            />
                        </button>
                    </CardContent>
                </Card>

                <Card className={cn('text-black', GLASS_CARD)}>
                    <CardHeader>
                        <CardTitle>Gaya jawaban</CardTitle>
                    </CardHeader>
                    <CardContent className="divide-y divide-black">
                        {(Object.keys(options) as StyleKey[]).map((key) => (
                            <div key={key} className="flex items-center justify-between gap-4 py-3">
                                <Label>{options[key].label}</Label>
                                <Select value={form[key]} onValueChange={(v) => set(key, v)}>
                                    <SelectTrigger className="w-44">
                                        <SelectValue />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {options[key].options.map((o) => (
                                            <SelectItem key={o.value} value={o.value}>
                                                {o.label}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                            </div>
                        ))}
                    </CardContent>
                </Card>

                <Card className={cn('text-black', GLASS_CARD)}>
                    <CardHeader>
                        <CardTitle>Instruksi khusus</CardTitle>
                        <CardDescription>Berikan petunjuk dan konteks tambahan kepada chatbot untuk semua obrolan.</CardDescription>
                    </CardHeader>
                    <CardContent className="space-y-4">
                        <div className="space-y-2">
                            <Textarea
                                rows={8}
                                maxLength={3000}
                                value={form.custom_instructions}
                                onChange={(e) => set('custom_instructions', e.target.value)}
                                placeholder="Misalnya, jawab dengan singkat"
                            />
                            {errors.custom_instructions && <p className="text-sm text-red-600">{errors.custom_instructions}</p>}
                        </div>
                        <div className="space-y-2">
                            <Label htmlFor="assistant_name">Nama asisten</Label>
                            <Input
                                id="assistant_name"
                                value={form.assistant_name}
                                onChange={(e) => set('assistant_name', e.target.value)}
                                placeholder="Chatbot sebaiknya disebut siapa?"
                            />
                        </div>
                        <div className="space-y-2">
                            <Label htmlFor="assistant_role">Peran asisten</Label>
                            <Input
                                id="assistant_role"
                                value={form.assistant_role}
                                onChange={(e) => set('assistant_role', e.target.value)}
                                placeholder="Contoh: Asisten layanan kepegawaian"
                            />
                        </div>
                        <div className="space-y-2">
                            <Label htmlFor="additional_info">Info lainnya</Label>
                            <Input
                                id="additional_info"
                                value={form.additional_info}
                                onChange={(e) => set('additional_info', e.target.value)}
                                placeholder="Preferensi, nilai, atau pilihan yang perlu diingat"
                            />
                        </div>
                    </CardContent>
                </Card>
            </div>
        </>
    );
}

ChatbotSettings.layout = {
    breadcrumbs: [
        {
            title: 'Pengaturan Chatbot',
            href: { url: '/pengaturan-chatbot', method: 'get' as const },
        },
    ],
};

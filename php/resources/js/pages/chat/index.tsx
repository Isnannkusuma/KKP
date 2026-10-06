import { Head, Link, router } from '@inertiajs/react';
import { Bot, LogIn, LogOut, Search, UserRound, X } from 'lucide-react';
import { useState, useRef, useEffect } from 'react';
import ChatInput from '@/components/chat/chat-input';
import ChatMessage from '@/components/chat/chat-message';
import WelcomeScreen from '@/components/chat/welcome-screen';
import type { ChatSuggestion } from '@/components/chat/welcome-screen';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { login, logout } from '@/routes';
import type { ChatMessage as ChatMessageType } from '@/types';

interface ChatProps {
    user: {
        name: string;
        gender?: string | null;
    } | null;
}

export default function Chat({ user }: ChatProps) {
    const [messages, setMessages] = useState<ChatMessageType[]>([]);
    const [isLoading, setIsLoading] = useState(false);
    const [loginPrompt, setLoginPrompt] = useState<{ open: boolean; message: string; redirectTo: string }>({
        open: false,
        message: 'Untuk pertanyaan terkait data pribadi atau NIP, silakan login terlebih dahulu.',
        redirectTo: '/login',
    });
    const messagesEndRef = useRef<HTMLDivElement>(null);
    const isMale = ['laki', 'laki-laki', 'male'].includes(user?.gender?.toLowerCase() ?? '');
    const userLabel = user ? `${isMale ? 'Bapak' : 'Ibu'} ${user.name}` : 'Bapak/Ibu';

    useEffect(() => {
        messagesEndRef.current?.scrollIntoView({ behavior: 'smooth' });
    }, [messages, isLoading]);

    const handleLogout = () => {
        router.flushAll();
    };

    const handleSuggestionClick = (suggestion: ChatSuggestion) => {
        if (suggestion.requiresLogin && !user) {
            setLoginPrompt({
                open: true,
                message: 'Masuk terlebih dahulu untuk mendapatkan rekomendasi pengembangan kompetensi (Bangkom) yang sesuai dengan data kepegawaian Anda.',
                redirectTo: '/login',
            });

            return;
        }

        void handleSendMessage(suggestion.query);
    };

    const handleSendMessage = async (content: string) => {
        const userMsg: ChatMessageType = {
            id: Date.now().toString(),
            role: 'user',
            content,
            created_at: new Date().toISOString(),
        };

        const historyForRequest = messages.map((msg) => ({
            role: msg.role,
            content: msg.content,
        }));

        setMessages((prev) => [...prev, userMsg]);
        setIsLoading(true);

        try {
            const response = await fetch('/chat/process', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
                },
                body: JSON.stringify({
                    message: content,
                    history: historyForRequest,
                }),
            });

            const data = await response.json();
            const loginReminderText = typeof data?.answer === 'string' ? data.answer : '';
            const requiresLogin = Boolean(
                data?.require_login
                || data?.requireLogin
                || /silakan login|login terlebih dahulu|login diperlukan|log in|untuk pertanyaan terkait data pribadi atau nip|data pribadi atau nip/i.test(loginReminderText)
            );

            if (requiresLogin) {
                setLoginPrompt({
                    open: true,
                    message: loginReminderText || 'Untuk data pribadi dan NIP, silakan login terlebih dahulu.',
                    redirectTo: data?.redirect_to || data?.redirectTo || '/login',
                });
                setMessages((prev) => prev.filter(m => m.id !== userMsg.id));

                return;
            }

            if (data?.error) {
                alert(data.error);
                setMessages((prev) => prev.filter(m => m.id !== userMsg.id));
            } else {
                const aiMsg: ChatMessageType = {
                    id: (Date.now() + 1).toString(),
                    role: 'assistant',
                    content: data.answer,
                    sources: data.sources || [],
                    auditId: data.audit_id || null,
                    created_at: new Date().toISOString(),
                };
                setMessages((prev) => [...prev, aiMsg]);
            }
        } catch (error) {
            console.error('Chat error:', error);
            alert('Terjadi kesalahan jaringan. Silakan coba lagi.');
            setMessages((prev) => prev.filter(m => m.id !== userMsg.id));
        } finally {
            setIsLoading(false);
        }
    };

    const handleFeedback = async (messageId: string, auditId: string, feedback: 'positive' | 'negative') => {
        setMessages((current) => current.map((message) => message.id === messageId ? { ...message, feedback } : message));

        try {
            await fetch(`/chat/audits/${auditId}/feedback`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
                },
                body: JSON.stringify({ feedback }),
            });
        } catch (error) {
            console.error('Feedback error:', error);
        }
    };

    return (
        <>
            <Head title="Asisten AI Instansi" />

            <Dialog open={loginPrompt.open} onOpenChange={(open) => setLoginPrompt((prev) => ({ ...prev, open }))}>
                <DialogContent className="w-[calc(100%-2rem)] max-w-[390px] gap-0 overflow-hidden border border-[#d2e5e6] bg-white p-0 text-[#183746] shadow-2xl shadow-[#123746]/20 [&>button]:hidden">
                    <button
                        type="button"
                        aria-label="Tutup dialog login"
                        onClick={() => setLoginPrompt((prev) => ({ ...prev, open: false }))}
                        className="absolute right-4 top-4 grid h-8 w-8 place-items-center rounded-full border border-[#d2e5e6] bg-[#f2f8f8] text-[#52717b] transition hover:bg-[#e3f2f0] hover:text-[#087f82]"
                    >
                        <X className="h-4 w-4" />
                    </button>
                    <DialogHeader className="items-center text-center">
                        <div className="mt-7 flex items-center gap-2 text-sm font-semibold">
                            <img src="/KKP%20LOGO%203.png" alt="" className="aspect-square h-7 w-7 shrink-0 rounded-full object-contain ring-1 ring-[#9bd9d2]" />
                            <span>Layanan AI KKP</span>
                        </div>
                        <DialogTitle className="mt-5 text-[26px] font-bold tracking-normal text-[#183746]">Masuk ke akun Anda</DialogTitle>
                        <DialogDescription className="mt-1 max-w-[290px] text-sm leading-5 text-[#52717b]">
                            Gunakan NIP dan password untuk mengakses data kepegawaian Anda.
                        </DialogDescription>
                    </DialogHeader>

                    <div className="px-6 pb-7 pt-5">
                        <div className="rounded-xl border border-[#d8e9e8] bg-[#f2f8f8] px-4 py-3 text-center text-xs leading-5 text-[#365765]">
                            {loginPrompt.message}
                        </div>
                        <Button
                            type="button"
                            onClick={() => {
                                setLoginPrompt((prev) => ({ ...prev, open: false }));
                                window.location.href = loginPrompt.redirectTo || '/login';
                            }}
                            className="mt-5 h-11 w-full rounded-xl bg-[#087f82] font-semibold text-white shadow-lg shadow-[#087f82]/20 hover:bg-[#066d70]"
                        >
                            <LogIn className="mr-2 h-4 w-4" /> Masuk
                        </Button>
                    </div>
                </DialogContent>
            </Dialog>

            <div className="h-dvh overflow-hidden bg-[#edf6f6] p-0 font-sans text-[#183746] lg:grid lg:place-items-center lg:p-8">
                <main className="flex h-full min-h-0 w-full max-w-[1440px] overflow-hidden bg-white shadow-[0_24px_56px_rgba(16,57,68,0.12)] lg:rounded-[24px] lg:border lg:border-[#d6e7e8]">
                    <aside className="hidden w-[240px] shrink-0 flex-col border-r border-[#d6e7e8] bg-[#f7fbfb] px-5 py-6 lg:flex">
                        <div className="flex flex-col items-center border-b border-[#d6e7e8] pb-6">
                            <img src="/KKP%20LOGO%203.png" alt="Kementerian Kelautan dan Perikanan" className="aspect-square h-20 w-20 shrink-0 rounded-full object-contain ring-2 ring-[#9bd9d2]" />
                            <span className="mt-3 text-center text-[10px] font-semibold leading-tight text-[#365765]">KEMENTERIAN<br />KELAUTAN DAN PERIKANAN<br />REPUBLIK INDONESIA</span>
                        </div>
                        <div className="relative mt-5">
                            <Search className="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-[#6b8b91]" />
                            <input aria-label="Cari percakapan" placeholder="Cari percakapan" className="h-10 w-full rounded-xl border border-[#d6e7e8] bg-white pl-9 pr-3 text-xs text-[#183746] outline-none placeholder:text-[#789197] focus:border-[#66b8b1] focus:bg-white" />
                        </div>
                        <p className="mt-5 text-[10px] font-semibold tracking-wide text-[#66838a]">PERCAKAPAN</p>
                        <div className="mt-2 flex items-center gap-3 rounded-2xl border border-[#cfe5e3] bg-[#eaf5f3] px-3 py-3 shadow-sm shadow-[#123746]/5">
                            <div className="grid h-9 w-9 place-items-center rounded-full bg-white text-[#078589]"><Bot className="h-4 w-4" /></div>
                            <div className="min-w-0 flex-1"><p className="truncate text-xs font-semibold">Asisten AI</p><p className="truncate text-[10px] text-[#66838a]">Siap membantu Anda</p></div>
                            <span className="h-2 w-2 rounded-full bg-[#17a58f]" />
                        </div>
                        {user && (
                            <div className="mt-auto border-t border-[#d6e7e8] pt-5">
                                <Link
                                    href={logout()}
                                    as="button"
                                    onClick={handleLogout}
                                    className="flex h-10 w-full items-center justify-center gap-2 rounded-xl border border-[#cbdfe1] bg-white text-xs font-semibold text-[#183746] transition hover:bg-[#edf7f6]"
                                >
                                    <LogOut className="h-4 w-4" /> Keluar
                                </Link>
                            </div>
                        )}
                    </aside>

                    <section className="flex min-h-0 min-w-0 flex-1 flex-col bg-[radial-gradient(circle_at_top_right,_#dff3f0_0,_#eff7f7_42%,_#f7faf9_100%)] p-4 sm:p-6 lg:p-5">
                        <header className="flex items-center justify-between px-1 pb-5">
                            <div className="flex items-center gap-2"><img src="/KKP%20LOGO%203.png" alt="" className="aspect-square h-7 w-7 shrink-0 rounded-full object-contain ring-1 ring-[#9bd9d2]" /><h1 className="text-sm font-semibold text-[#183746]">Layanan AI KKP</h1></div>
                            <div className="flex items-center gap-2">
                                <div className="flex items-center gap-2 rounded-full border border-[#d6e7e8] bg-white/80 py-2 pl-3 pr-2 text-[11px] font-medium text-[#365765]"><UserRound className="h-3.5 w-3.5 text-[#078589]" /> {userLabel}</div>
                                {!user && (
                                    <Link
                                        href={login()}
                                        className="flex h-8 items-center gap-1.5 rounded-lg border border-[#cbdfe1] bg-white px-3 text-[11px] font-semibold text-[#087f82] transition hover:bg-[#edf7f6]"
                                    >
                                        <LogIn className="h-3.5 w-3.5" /> Masuk
                                    </Link>
                                )}
                            </div>
                        </header>
                        <div className="flex min-h-0 flex-1 flex-col overflow-hidden rounded-[20px] border border-[#d6e7e8] bg-white/85 shadow-[0_12px_32px_rgba(16,57,68,0.06)]">
                            <div className="flex items-center justify-between px-5 py-4 text-xs"><h2 className="font-semibold text-[#183746]">Percakapan terbaru</h2><span className="text-[#789197]">Saat ini</span></div>
                            <div className="min-h-0 flex-1 overflow-y-auto px-4 pb-4 sm:px-5">
                                <div className="mx-auto max-w-3xl py-2">
                        {messages.length === 0 ? (
                            <WelcomeScreen onSuggestionClick={handleSuggestionClick} />
                        ) : (
                            <>
                                {messages.map((msg) => (
                                    <ChatMessage key={msg.id} message={msg} onFeedback={handleFeedback} />
                                ))}

                                {isLoading && (
                                    <div className="mb-6 flex gap-3">
                                        <div className="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-[#e6f3f1] text-[#078589]">
                                            <div className="gap-1 flex">
                                                <div className="h-2 w-2 rounded-full bg-[#078589]/60 animate-bounce" style={{ animationDelay: '0ms' }}></div>
                                                <div className="h-2 w-2 rounded-full bg-[#078589]/60 animate-bounce" style={{ animationDelay: '150ms' }}></div>
                                                <div className="h-2 w-2 rounded-full bg-[#078589]/60 animate-bounce" style={{ animationDelay: '300ms' }}></div>
                                            </div>
                                        </div>
                                        <div className="rounded-2xl rounded-tl-sm border border-[#d6e7e8] bg-white px-4 py-3 text-sm text-[#183746]">
                                            Sedang mencari informasi dan merangkum jawaban...
                                        </div>
                                    </div>
                                )}
                                <div ref={messagesEndRef} />
                            </>
                        )}
                                </div>
                            </div>
                            <div className="border-t border-[#d6e7e8] bg-[#f7fbfb] p-3 sm:p-4">
                                <div className="mx-auto max-w-3xl"><ChatInput onSend={handleSendMessage} isLoading={isLoading} /></div>
                            </div>
                        </div>
                    </section>
                </main>
            </div>
        </>
    );
}

Chat.layout = (page: any) => page;
import { Head, Link, router } from '@inertiajs/react';
import { Bot, LogIn, LogOut, Search, UserRound, X } from 'lucide-react';
import { useState, useRef, useEffect } from 'react';
import ChatInput from '@/components/chat/chat-input';
import ChatMessage from '@/components/chat/chat-message';
import WelcomeScreen from '@/components/chat/welcome-screen';
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

    return (
        <>
            <Head title="Asisten AI Instansi" />

            <Dialog open={loginPrompt.open} onOpenChange={(open) => setLoginPrompt((prev) => ({ ...prev, open }))}>
                <DialogContent className="w-[calc(100%-2rem)] max-w-[390px] gap-0 overflow-hidden border border-white/20 bg-[#23476d]/95 p-0 text-white shadow-2xl shadow-slate-950/60 backdrop-blur-xl [&>button]:hidden">
                    <button
                        type="button"
                        aria-label="Tutup dialog login"
                        onClick={() => setLoginPrompt((prev) => ({ ...prev, open: false }))}
                        className="absolute right-4 top-4 grid h-8 w-8 place-items-center rounded-full border border-white/15 bg-white/10 text-white/70 transition hover:bg-white/20 hover:text-white"
                    >
                        <X className="h-4 w-4" />
                    </button>
                    <DialogHeader className="items-center text-center">
                        <div className="mt-7 flex items-center gap-2 text-sm font-semibold">
                            <img src="/KKP%20LOGO%203.png" alt="" className="aspect-square h-7 w-7 shrink-0 rounded-full object-contain ring-1 ring-cyan-300/70" />
                            <span>Layanan AI KKP</span>
                        </div>
                        <DialogTitle className="mt-5 text-[26px] font-bold tracking-normal text-white">Masuk ke akun Anda</DialogTitle>
                        <DialogDescription className="mt-1 max-w-[290px] text-sm leading-5 text-blue-100/70">
                            Gunakan NIP dan password untuk mengakses data kepegawaian Anda.
                        </DialogDescription>
                    </DialogHeader>

                    <div className="px-6 pb-7 pt-5">
                        <div className="rounded-xl border border-white/15 bg-white/10 px-4 py-3 text-center text-xs leading-5 text-blue-50/80">
                            {loginPrompt.message}
                        </div>
                        <Button
                            type="button"
                            onClick={() => {
                                setLoginPrompt((prev) => ({ ...prev, open: false }));
                                window.location.href = loginPrompt.redirectTo || '/login';
                            }}
                            className="mt-5 h-11 w-full rounded-xl bg-white font-semibold text-[#1b52c9] shadow-lg shadow-slate-950/20 hover:bg-blue-50"
                        >
                            <LogIn className="mr-2 h-4 w-4" /> Masuk
                        </Button>
                    </div>
                </DialogContent>
            </Dialog>

            <div className="h-dvh overflow-hidden bg-[#06192e] p-0 font-sans text-white lg:grid lg:place-items-center lg:p-8">
                <main className="flex h-full min-h-0 w-full max-w-[1440px] overflow-hidden bg-[#0e4f83]/70 shadow-[0_24px_56px_rgba(0,18,43,0.42)] lg:rounded-[24px] lg:border lg:border-white/20">
                    <aside className="hidden w-[240px] shrink-0 flex-col border-r border-white/10 bg-[#607991]/55 px-5 py-6 backdrop-blur-sm lg:flex">
                        <div className="flex flex-col items-center border-b border-white/10 pb-6">
                            <img src="/KKP%20LOGO%203.png" alt="Kementerian Kelautan dan Perikanan" className="aspect-square h-20 w-20 shrink-0 rounded-full object-contain ring-2 ring-cyan-300/80" />
                            <span className="mt-3 text-center text-[10px] font-semibold leading-tight text-white/80">KEMENTERIAN<br />KELAUTAN DAN PERIKANAN<br />REPUBLIK INDONESIA</span>
                        </div>
                        <div className="relative mt-5">
                            <Search className="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-white/50" />
                            <input aria-label="Cari percakapan" placeholder="Cari percakapan" className="h-10 w-full rounded-xl border border-white/20 bg-white/15 pl-9 pr-3 text-xs text-white outline-none placeholder:text-white/60 focus:border-white/45 focus:bg-white/20" />
                        </div>
                        <p className="mt-5 text-[10px] font-semibold tracking-wide text-white/55">PERCAKAPAN</p>
                        <div className="mt-2 flex items-center gap-3 rounded-2xl border border-white/15 bg-white/15 px-3 py-3 shadow-sm shadow-[#163754]/20">
                            <div className="grid h-9 w-9 place-items-center rounded-full bg-white/15"><Bot className="h-4 w-4" /></div>
                            <div className="min-w-0 flex-1"><p className="truncate text-xs font-semibold">Asisten AI</p><p className="truncate text-[10px] text-white/55">Siap membantu Anda</p></div>
                            <span className="h-2 w-2 rounded-full bg-emerald-400" />
                        </div>
                        {user && (
                            <div className="mt-auto border-t border-white/10 pt-5">
                                <Link
                                    href={logout()}
                                    as="button"
                                    onClick={handleLogout}
                                    className="flex h-10 w-full items-center justify-center gap-2 rounded-xl border border-white/20 bg-white/10 text-xs font-semibold text-white transition hover:bg-white/20"
                                >
                                    <LogOut className="h-4 w-4" /> Keluar
                                </Link>
                            </div>
                        )}
                    </aside>

                    <section className="flex min-h-0 min-w-0 flex-1 flex-col bg-[radial-gradient(circle_at_top_right,_#3b94cb_0,_#2675aa_42%,_#1c5988_100%)] p-4 sm:p-6 lg:p-5">
                        <header className="flex items-center justify-between px-1 pb-5">
                            <div className="flex items-center gap-2"><img src="/KKP%20LOGO%203.png" alt="" className="aspect-square h-7 w-7 shrink-0 rounded-full object-contain ring-1 ring-cyan-200/80" /><h1 className="text-sm font-semibold">Layanan AI KKP</h1></div>
                            <div className="flex items-center gap-2">
                                <div className="flex items-center gap-2 rounded-full bg-white/15 py-2 pl-3 pr-2 text-[11px] font-medium text-white/90"><UserRound className="h-3.5 w-3.5" /> {userLabel}</div>
                                {!user && (
                                    <Link
                                        href={login()}
                                        className="flex h-8 items-center gap-1.5 rounded-lg border border-white/25 bg-white/10 px-3 text-[11px] font-semibold text-white transition hover:bg-white/20"
                                    >
                                        <LogIn className="h-3.5 w-3.5" /> Masuk
                                    </Link>
                                )}
                            </div>
                        </header>
                        <div className="flex min-h-0 flex-1 flex-col overflow-hidden rounded-[20px] border border-white/20 bg-[#326b94]/80 shadow-[inset_0_1px_rgba(255,255,255,0.08)]">
                            <div className="flex items-center justify-between px-5 py-4 text-xs"><h2 className="font-semibold">Percakapan terbaru</h2><span className="text-white/55">Saat ini</span></div>
                            <div className="min-h-0 flex-1 overflow-y-auto px-4 pb-4 sm:px-5">
                                <div className="mx-auto max-w-3xl py-2">
                        {messages.length === 0 ? (
                            <WelcomeScreen onSuggestionClick={handleSendMessage} />
                        ) : (
                            <>
                                {messages.map((msg) => (
                                    <ChatMessage key={msg.id} message={msg} />
                                ))}

                                {isLoading && (
                                    <div className="mb-6 flex gap-3">
                                        <div className="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-white/15">
                                            <div className="gap-1 flex">
                                                <div className="h-2 w-2 bg-foreground/50 rounded-full animate-bounce" style={{ animationDelay: '0ms' }}></div>
                                                <div className="h-2 w-2 bg-foreground/50 rounded-full animate-bounce" style={{ animationDelay: '150ms' }}></div>
                                                <div className="h-2 w-2 bg-foreground/50 rounded-full animate-bounce" style={{ animationDelay: '300ms' }}></div>
                                            </div>
                                        </div>
                                        <div className="rounded-2xl rounded-tl-sm bg-white/90 px-4 py-3 text-sm text-[#1c5bc5]">
                                            Sedang mencari informasi dan merangkum jawaban...
                                        </div>
                                    </div>
                                )}
                                <div ref={messagesEndRef} />
                            </>
                        )}
                                </div>
                            </div>
                            <div className="border-t border-white/10 bg-[#3c7196]/75 p-3 sm:p-4">
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
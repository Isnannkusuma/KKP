import { Head } from '@inertiajs/react';
import { useState, useRef, useEffect } from 'react';
import ChatInput from '@/components/chat/chat-input';
import ChatMessage from '@/components/chat/chat-message';
import WelcomeScreen from '@/components/chat/welcome-screen';
import type { ChatMessage as ChatMessageType } from '@/types';

export default function Chat() {
    const [messages, setMessages] = useState<ChatMessageType[]>([]);
    const [isLoading, setIsLoading] = useState(false);
    const messagesEndRef = useRef<HTMLDivElement>(null);

    useEffect(() => {
        messagesEndRef.current?.scrollIntoView({ behavior: 'smooth' });
    }, [messages, isLoading]);

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
            
            if (data.error) {
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
            <div className="flex-col h-screen bg-background flex">
                <header className="px-6 py-3 border-b justify-between bg-card shrink-0 flex items-center">
                    <div className="gap-3 flex items-center">
                        <div className="h-8 w-8 rounded-full bg-primary justify-center text-primary-foreground font-bold text-sm flex items-center">
                            AI
                        </div>
                        <div>
                            <h1 className="text-sm font-semibold">Asisten Layanan Instansi</h1>
                            <p className="text-xs text-muted-foreground">Didukung oleh RAG & Gemini</p>
                        </div>
                    </div>
                </header>

                <div className="flex-1 px-4 overflow-y-auto min-h-0">
                    <div className="py-6 max-w-3xl mx-auto">
                        {messages.length === 0 ? (
                            <WelcomeScreen onSuggestionClick={handleSendMessage} />
                        ) : (
                            <>
                                {messages.map((msg) => (
                                    <ChatMessage key={msg.id} message={msg} />
                                ))}
                                
                                {isLoading && (
                                    <div className="gap-4 mb-6 flex">
                                        <div className="h-8 w-8 rounded-full bg-muted justify-center flex items-center shrink-0">
                                            <div className="gap-1 flex">
                                                <div className="h-2 w-2 bg-foreground/50 rounded-full animate-bounce" style={{ animationDelay: '0ms' }}></div>
                                                <div className="h-2 w-2 bg-foreground/50 rounded-full animate-bounce" style={{ animationDelay: '150ms' }}></div>
                                                <div className="h-2 w-2 bg-foreground/50 rounded-full animate-bounce" style={{ animationDelay: '300ms' }}></div>
                                            </div>
                        </div>
                                        <div className="px-4 py-3 bg-muted rounded-2xl rounded-tl-sm text-sm text-muted-foreground">
                                            Sedang mencari informasi dan merangkum jawaban...
                                        </div>
                                    </div>
                                )}
                                <div ref={messagesEndRef} />
                            </>
                        )}
                    </div>
                </div>

                <div className="p-4 border-t bg-background shrink-0">
                    <div className="max-w-3xl mx-auto">
                        <ChatInput onSend={handleSendMessage} isLoading={isLoading} />
                        <p className="mt-2 text-center text-[10px] text-muted-foreground">
                            AI dapat membuat kesalahan. Selalu verifikasi informasi penting dengan petugas resmi.
                        </p>
                    </div>
                </div>
            </div>
        </>
    );
}

Chat.layout = (page: any) => page;
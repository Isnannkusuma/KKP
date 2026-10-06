import { Send, Loader2 } from 'lucide-react';
import type { KeyboardEvent } from 'react';
import { useState } from 'react';
import { Button } from '@/components/ui/button';
import { Textarea } from '@/components/ui/textarea';

interface ChatInputProps {
    onSend: (message: string) => void;
    isLoading: boolean;
}

export default function ChatInput({ onSend, isLoading }: ChatInputProps) {
    const [input, setInput] = useState('');

    const handleSend = () => {
        if (input.trim() && !isLoading) {
            onSend(input.trim());
            setInput('');
        }
    };

    const handleKeyDown = (e: KeyboardEvent<HTMLTextAreaElement>) => {
        if (e.key === 'Enter' && !e.shiftKey) {
            e.preventDefault();
            handleSend();
        }
    };

    return (
        <div className="flex items-end gap-2 rounded-[16px] border border-[#cfe1e2] bg-white p-2 shadow-sm shadow-[#123746]/5">
            <Textarea
                placeholder="Tulis pesan untuk asisten..."
                className="min-h-[42px] max-h-[130px] rounded-xl border-0 bg-[#f3f8f8] px-3 py-2 text-sm text-[#183746] placeholder:text-[#789197] focus-visible:ring-0"
                value={input}
                onChange={(e) => setInput(e.target.value)}
                onKeyDown={handleKeyDown}
                disabled={isLoading}
            />
            <Button 
                size="icon" 
                className="h-[42px] w-[42px] shrink-0 rounded-xl bg-[#087f82] text-white shadow-sm hover:bg-[#066d70]"
                onClick={handleSend} 
                disabled={!input.trim() || isLoading}
            >
                {isLoading ? (
                    <Loader2 className="h-5 w-5 animate-spin" />
                ) : (
                    <Send className="h-4 w-4" />
                )}
            </Button>
        </div>
    );
}
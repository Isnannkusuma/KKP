import { SendHorizontal, Loader2 } from 'lucide-react';
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
        <div className="gap-2 flex items-end">
            <Textarea
                placeholder="Ketik pertanyaan Anda mengenai layanan instansi..."
                className="min-h-[50px] max-h-[150px] bg-muted/50 border-border resize-none focus-visible:ring-primary"
                value={input}
                onChange={(e) => setInput(e.target.value)}
                onKeyDown={handleKeyDown}
                disabled={isLoading}
            />
            <Button 
                size="icon" 
                className="h-[50px] w-[50px] shrink-0" 
                onClick={handleSend} 
                disabled={!input.trim() || isLoading}
            >
                {isLoading ? (
                    <Loader2 className="h-5 w-5 animate-spin" />
                ) : (
                    <SendHorizontal className="h-5 w-5" />
                )}
            </Button>
        </div>
    );
}
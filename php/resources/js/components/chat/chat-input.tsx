import { Loader2, Mic, Send, Square } from 'lucide-react';
import type { KeyboardEvent } from 'react';
import { useEffect, useRef, useState } from 'react';
import { Button } from '@/components/ui/button';
import { Textarea } from '@/components/ui/textarea';

interface ChatInputProps {
    onSend: (message: string) => void;
    isLoading: boolean;
}

export default function ChatInput({ onSend, isLoading }: ChatInputProps) {
    const [input, setInput] = useState('');
    const [isRecording, setIsRecording] = useState(false);
    const [isTranscribing, setIsTranscribing] = useState(false);
    const [voiceError, setVoiceError] = useState('');
    const recorderRef = useRef<MediaRecorder | null>(null);
    const streamRef = useRef<MediaStream | null>(null);
    const recordingTimeoutRef = useRef<number | null>(null);

    const handleSend = () => {
        if (input.trim() && !isLoading) {
            onSend(input.trim());
            setInput('');
        }
    };

    const stopRecording = () => {
        if (recordingTimeoutRef.current !== null) {
            window.clearTimeout(recordingTimeoutRef.current);
            recordingTimeoutRef.current = null;
        }

        if (recorderRef.current?.state === 'recording') {
            recorderRef.current.stop();
        }
    };

    const startRecording = async () => {
        setVoiceError('');

        if (!navigator.mediaDevices?.getUserMedia || typeof MediaRecorder === 'undefined') {
            setVoiceError('Browser ini tidak mendukung perekaman suara. Silakan gunakan Chrome, Edge, atau Safari terbaru.');

            return;
        }

        try {
            const stream = await navigator.mediaDevices.getUserMedia({ audio: true });
            streamRef.current = stream;
            const mimeType = [
                'audio/webm;codecs=opus',
                'audio/webm',
                'audio/mp4',
                'audio/ogg;codecs=opus',
            ].find((type) => MediaRecorder.isTypeSupported(type));
            const recorder = new MediaRecorder(stream, mimeType ? { mimeType } : undefined);
            const chunks: BlobPart[] = [];

            recorderRef.current = recorder;
            recorder.ondataavailable = (event) => {
                if (event.data.size > 0) {
                    chunks.push(event.data);
                }
            };
            recorder.onstop = async () => {
                stream.getTracks().forEach((track) => track.stop());
                streamRef.current = null;
                recorderRef.current = null;
                setIsRecording(false);
                setIsTranscribing(true);

                try {
                    const blob = new Blob(chunks, { type: recorder.mimeType || 'audio/webm' });
                    const extension = blob.type.includes('mp4') ? 'm4a' : blob.type.includes('ogg') ? 'ogg' : 'webm';
                    const formData = new FormData();
                    formData.append('audio', blob, `recording.${extension}`);

                    const response = await fetch('/chat/audio/transcribe', {
                        method: 'POST',
                        headers: {
                            Accept: 'application/json',
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
                        },
                        body: formData,
                    });
                    const result = await response.json() as { text?: string; message?: string };

                    if (!response.ok) {
                        throw new Error(result.message || 'Gagal mengenali ucapan. Silakan coba lagi.');
                    }

                    const transcript = result.text?.trim();

                    if (!transcript) {
                        throw new Error('Ucapan tidak terdeteksi. Silakan coba berbicara lebih jelas.');
                    }

                    onSend(transcript);
                } catch (error) {
                    setVoiceError(error instanceof Error ? error.message : 'Gagal mengirim rekaman suara.');
                } finally {
                    setIsTranscribing(false);
                }
            };

            recorder.start();
            setIsRecording(true);
            recordingTimeoutRef.current = window.setTimeout(stopRecording, 60_000);
        } catch (error) {
            streamRef.current?.getTracks().forEach((track) => track.stop());
            streamRef.current = null;
            setVoiceError(error instanceof Error ? error.message : 'Mikrofon tidak dapat diakses.');
        }
    };

    const handleVoiceClick = () => {
        if (isRecording) {
            stopRecording();

            return;
        }

        void startRecording();
    };

    useEffect(() => () => {
        if (recordingTimeoutRef.current !== null) {
            window.clearTimeout(recordingTimeoutRef.current);
        }

        if (recorderRef.current?.state === 'recording') {
            recorderRef.current.stop();
        }

        streamRef.current?.getTracks().forEach((track) => track.stop());
    }, []);

    const handleKeyDown = (e: KeyboardEvent<HTMLTextAreaElement>) => {
        if (e.key === 'Enter' && !e.shiftKey) {
            e.preventDefault();
            handleSend();
        }
    };

    return (
        <div className="flex flex-wrap items-end gap-2 rounded-[16px] border border-[#cfe1e2] bg-white p-2 shadow-sm shadow-[#123746]/5">
            <Textarea
                placeholder={isTranscribing ? 'Mengenali ucapan...' : 'Tulis pesan untuk asisten...'}
                className="min-h-[42px] max-h-[130px] min-w-0 flex-1 basis-0 rounded-xl border-0 bg-[#f3f8f8] px-3 py-2 text-sm text-[#183746] placeholder:text-[#789197] focus-visible:ring-0"
                value={input}
                onChange={(e) => setInput(e.target.value)}
                onKeyDown={handleKeyDown}
                disabled={isLoading || isTranscribing}
            />
            <Button
                type="button"
                size="icon"
                variant="outline"
                aria-label={isRecording ? 'Selesai merekam' : 'Kirim pertanyaan dengan suara'}
                title={isRecording ? 'Selesai merekam' : 'Kirim pertanyaan dengan suara (maksimal 60 detik)'}
                className={`h-[42px] w-[42px] shrink-0 rounded-xl ${isRecording ? 'border-red-300 bg-red-50 text-red-700 hover:bg-red-100' : 'border-[#cfe1e2] text-[#087f82]'}`}
                onClick={handleVoiceClick}
                disabled={isLoading || isTranscribing}
            >
                {isTranscribing ? <Loader2 className="h-4 w-4 animate-spin" /> : isRecording ? <Square className="h-4 w-4 fill-current" /> : <Mic className="h-4 w-4" />}
            </Button>
            <Button 
                size="icon" 
                className="h-[42px] w-[42px] shrink-0 rounded-xl bg-[#087f82] text-white shadow-sm hover:bg-[#066d70]"
                onClick={handleSend} 
                disabled={!input.trim() || isLoading || isTranscribing || isRecording}
            >
                {isLoading ? (
                    <Loader2 className="h-5 w-5 animate-spin" />
                ) : (
                    <Send className="h-4 w-4" />
                )}
            </Button>
            {voiceError && <p className="basis-full px-2 text-xs text-red-700" role="alert">{voiceError}</p>}
        </div>
    );
}
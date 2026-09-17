export interface ChatMessage {
    id: string;
    role: 'user' | 'assistant';
    content: string;
    sources?: {
        document_id: string;
        filename: string;
        similarity_score: number;
    }[];
    auditId?: string | null;
    feedback?: 'positive' | 'negative';
    created_at: string;
}
export interface ChatMessage {
    id: string;
    role: 'user' | 'assistant';
    content: string;
    sources?: {
        document_id: string;
        filename: string;
        similarity_score: number;
    }[];
    graphic?: boolean;
    similarity?: number | null;
    auditId?: string | null;
    feedback?: 'positive' | 'negative';
    created_at: string;
}
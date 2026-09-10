export interface ChatMessage {
    id: string;
    role: 'user' | 'assistant';
    content: string;
    sources?: {
        document_id: string;
        filename: string;
        similarity_score: number;
    }[];
    created_at: string;
}
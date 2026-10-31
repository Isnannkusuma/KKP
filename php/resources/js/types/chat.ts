export interface ConfirmationField {
    name: string;
    label: string;
    type: 'text' | 'radio' | 'checkbox';
    required?: boolean;
    placeholder?: string;
    options?: { label: string; value: string }[];
}

export interface ConfirmationRequest {
    title: string;
    description?: string;
    fields: ConfirmationField[];
    submitLabel?: string;
}

export interface ChatMessage {
    id: string;
    role: 'user' | 'assistant';
    content: string;
    confirmation?: ConfirmationRequest | null;
    sources?: {
        document_id: string;
        filename: string;
        similarity_score: number;
        source_type?: 'api';
    }[];
    graphic?: boolean;
    completeList?: boolean;
    similarity?: number | null;
    auditId?: string | null;
    feedback?: 'positive' | 'negative';
    created_at: string;
}
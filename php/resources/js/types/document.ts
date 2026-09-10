export interface Category {
    id: string;
    name: string;
    slug: string;
}

export interface DocumentItem {
    id: string;
    name: string;
    file_type: string;
    file_size: number;
    file_size_formatted: string;
    status: 'pending' | 'extracting' | 'cleaning' | 'embedding' | 'ready' | 'failed' | 'processing';
    total_chunks: number;
    error_message: string | null;
    created_at: string;
    category?: Category | null;
    access_level: 'public' | 'internal' | 'confidential';
}

export interface DocumentStats {
    total: number;
    ready: number;
    processing: number;
    failed: number;
}

export interface PaginationData {
    data: DocumentItem[];
    current_page: number;
    last_page: number;
    total: number;
    per_page: number;
    from: number | null;
    to: number | null;
}

export interface Filters {
    status?: string;
    search?: string;
    category_id?: string;
}
export interface ApiServiceRequestField {
    name: string;
    type: 'string' | 'number' | 'boolean';
    required: boolean;
    description?: string;
}

export interface ApiServiceResponseField {
    name: string;
    path: string;
}

export interface ApiServiceItem {
    id: string;
    name: string;
    description: string | null;
    method: string;
    url: string;
    inspect_url?: string | null;
    headers: Record<string, string>;
    query_params: Record<string, unknown>;
    body: Record<string, unknown>;
    request_schema: ApiServiceRequestField[];
    response_fields: ApiServiceResponseField[];
    is_active: boolean;
}

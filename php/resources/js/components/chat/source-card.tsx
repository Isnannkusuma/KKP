import { FileText, ChevronDown, ChevronUp } from 'lucide-react';
import { useState } from 'react';
import { Badge } from '@/components/ui/badge';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';

interface SourceItem {
    document_id: string;
    filename: string;
    similarity_score: number;
}

interface SourceCardProps {
    sources: SourceItem[];
}

export default function SourceCard({ sources }: SourceCardProps) {
    const [isExpanded, setIsExpanded] = useState(false);

    if (!sources || sources.length === 0) {
return null;
}

    return (
        <Card className="mt-3 border-[#d6e7e8] bg-white text-[#183746]">
            <CardHeader 
                className="flex-row pb-2 pt-3 px-4 justify-between flex items-center space-y-0 cursor-pointer"
                onClick={() => setIsExpanded(!isExpanded)}
            >
                <div className="gap-2 flex items-center">
                    <FileText className="h-4 w-4 text-[#078589]" />
                    <CardTitle className="text-sm font-medium">
                        Sumber Referensi ({sources.length} Dokumen)
                    </CardTitle>
                </div>
                {isExpanded ? <ChevronUp className="h-4 w-4" /> : <ChevronDown className="h-4 w-4" />}
            </CardHeader>
            
            {isExpanded && (
                <CardContent className="grid pt-0 px-4 pb-4 gap-2">
                    {sources.map((source, index) => (
                        <div key={index} className="flex items-center justify-between rounded border border-[#d6e7e8] bg-[#f7fbfb] p-3 text-xs">
                            <div className="flex-1 gap-2 min-w-0 flex items-center">
                                <FileText className="h-3 w-3 shrink-0 text-[#66838a]" />
                                <span className="truncate font-medium text-[#183746]" title={source.filename}>
                                    {source.filename || 'Dokumen Tidak Diketahui'}
                                </span>
                            </div>
                            <Badge variant="secondary" className="ml-2 shrink-0 bg-[#e6f3f1] text-[10px] text-[#087f82]">
                                {(source.similarity_score * 100).toFixed(1)}% match
                            </Badge>
                        </div>
                    ))}
                </CardContent>
            )}
        </Card>
    );
}
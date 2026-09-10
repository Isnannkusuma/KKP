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
        <Card className="mt-3 bg-muted/50 border-border/50">
            <CardHeader 
                className="flex-row pb-2 pt-3 px-4 justify-between flex items-center space-y-0 cursor-pointer"
                onClick={() => setIsExpanded(!isExpanded)}
            >
                <div className="gap-2 flex items-center">
                    <FileText className="h-4 w-4 text-primary" />
                    <CardTitle className="text-sm font-medium">
                        Sumber Referensi ({sources.length} Dokumen)
                    </CardTitle>
                </div>
                {isExpanded ? <ChevronUp className="h-4 w-4" /> : <ChevronDown className="h-4 w-4" />}
            </CardHeader>
            
            {isExpanded && (
                <CardContent className="grid pt-0 px-4 pb-4 gap-2">
                    {sources.map((source, index) => (
                        <div key={index} className="p-3 text-xs bg-background rounded border border-border/50 justify-between flex items-center">
                            <div className="flex-1 gap-2 min-w-0 flex items-center">
                                <FileText className="h-3 w-3 text-muted-foreground shrink-0" />
                                <span className="font-medium text-foreground truncate" title={source.filename}>
                                    {source.filename || 'Dokumen Tidak Diketahui'}
                                </span>
                            </div>
                            <Badge variant="secondary" className="text-[10px] shrink-0 ml-2">
                                {(source.similarity_score * 100).toFixed(1)}% match
                            </Badge>
                        </div>
                    ))}
                </CardContent>
            )}
        </Card>
    );
}
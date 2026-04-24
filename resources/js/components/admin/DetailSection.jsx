import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { cn } from '@/lib/utils';

/**
 * @param {{ title: string, children: import('react').ReactNode, className?: string }} props
 */
export function DetailSection({ title, children, className }) {
    return (
        <Card className={cn('border-l-4 border-l-primary/80 shadow-sm', className)}>
            <CardHeader className="pb-3">
                <CardTitle className="text-base">{title}</CardTitle>
            </CardHeader>
            <CardContent className="space-y-3 text-sm text-muted-foreground">{children}</CardContent>
        </Card>
    );
}

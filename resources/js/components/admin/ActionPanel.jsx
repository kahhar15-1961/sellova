import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { cn } from '@/lib/utils';

/**
 * @param {{ title?: string, children: import('react').ReactNode, className?: string }} props
 */
export function ActionPanel({ title = 'Actions', children, className }) {
    return (
        <Card className={cn('sticky top-24 shadow-card', className)}>
            <CardHeader className="pb-3">
                <CardTitle className="text-sm font-semibold">{title}</CardTitle>
            </CardHeader>
            <CardContent className="flex flex-col gap-2">{children}</CardContent>
        </Card>
    );
}

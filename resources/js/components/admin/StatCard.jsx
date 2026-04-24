import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { cn } from '@/lib/utils';

/**
 * @param {{ label: string, value: string, hint?: string, className?: string }} props
 */
export function StatCard({ label, value, hint, className }) {
    return (
        <Card className={cn('overflow-hidden', className)}>
            <CardHeader className="pb-2">
                <CardTitle className="text-xs font-medium uppercase tracking-wide text-muted-foreground">{label}</CardTitle>
            </CardHeader>
            <CardContent>
                <p className="text-2xl font-semibold tabular-nums tracking-tight">{value}</p>
                {hint && <p className="mt-2 text-xs text-muted-foreground">{hint}</p>}
            </CardContent>
        </Card>
    );
}

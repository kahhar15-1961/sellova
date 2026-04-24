import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { cn } from '@/lib/utils';

/**
 * @param {{ label: string, value: string | null, hint?: string | null, className?: string, locked?: boolean }} props
 */
export function StatCard({ label, value, hint, className, locked }) {
    const isLocked = locked || value === null || value === undefined;
    const display = isLocked ? '—' : value;
    return (
        <Card className={cn('overflow-hidden', isLocked && 'opacity-70', className)}>
            <CardHeader className="pb-2">
                <CardTitle className="text-xs font-medium uppercase tracking-wide text-muted-foreground">{label}</CardTitle>
            </CardHeader>
            <CardContent>
                <p className="text-2xl font-semibold tabular-nums tracking-tight">{display}</p>
                {hint ? <p className="mt-2 text-xs text-muted-foreground">{hint}</p> : null}
            </CardContent>
        </Card>
    );
}

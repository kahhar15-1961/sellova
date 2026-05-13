import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { cn } from '@/lib/utils';

function normalizeHint(hint) {
    if (!hint) return null;
    const text = String(hint).trim();
    return text.length ? text : null;
}

function isCompactTrend(text) {
    if (!text) return false;
    return text.length <= 14 && /[%+\-]|up|down|gain|loss|growth|drop/i.test(text);
}

/**
 * @param {{ label: string, value: string | null, hint?: string | null, className?: string, locked?: boolean, variant?: 'list' | 'dashboard' }} props
 */
export function StatCard({ label, value, hint, className, locked, variant = 'list' }) {
    const isLocked = locked || value === null || value === undefined;
    const display = isLocked ? '—' : value;
    const meta = normalizeHint(hint);
    const trendText = isLocked ? null : isCompactTrend(meta) ? meta : null;
    const helperText = trendText ? null : meta;

    if (variant === 'dashboard') {
        return (
            <Card className={cn('kpi-card overflow-hidden', isLocked && 'opacity-70', className)}>
                <CardHeader className="space-y-1 pb-2">
                    <CardTitle className="text-[1.05rem] font-semibold tracking-tight text-foreground dark:text-slate-50">{label}</CardTitle>
                    <p className="text-[0.68rem] font-semibold uppercase tracking-[0.2em] text-slate-500 dark:text-slate-400">Performance</p>
                </CardHeader>
                <CardContent className="pt-0">
                    <div className="flex flex-wrap items-center gap-2">
                        <p className="text-[2rem] font-semibold tabular-nums tracking-[-0.04em] text-foreground dark:text-white">{display}</p>
                        {trendText ? (
                            <span className="inline-flex items-center rounded-full bg-emerald-500/14 px-2 py-1 text-xs font-semibold leading-none text-emerald-600 dark:bg-emerald-500/16 dark:text-emerald-400">
                                {trendText}
                            </span>
                        ) : null}
                    </div>
                    {helperText ? <p className="mt-2 text-xs leading-5 text-slate-500 dark:text-slate-400">{helperText}</p> : null}
                </CardContent>
            </Card>
        );
    }

    return (
        <Card className={cn('overflow-hidden rounded-lg border-slate-200 bg-white shadow-[0_1px_2px_rgba(15,23,42,0.04)] dark:border-slate-700 dark:bg-slate-800', isLocked && 'opacity-70', className)}>
            <CardHeader className="pb-1">
                <CardTitle className="text-[10px] font-bold uppercase tracking-normal text-slate-500 dark:text-slate-400">{label}</CardTitle>
            </CardHeader>
            <CardContent className="pt-0">
                <p className="text-[22px] font-bold leading-none tabular-nums text-slate-900 dark:text-white">{display}</p>
                {helperText ? <p className="mt-2 text-xs leading-5 text-slate-500 dark:text-slate-400">{helperText}</p> : null}
            </CardContent>
        </Card>
    );
}

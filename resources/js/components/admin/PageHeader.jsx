import { Link } from '@inertiajs/react';
import { ChevronRight } from 'lucide-react';
import { cn } from '@/lib/utils';

/**
 * @param {{ title: string, description?: string, breadcrumbs?: { label: string, href?: string }[], actions?: import('react').ReactNode, className?: string }} props
 */
export function PageHeader({ title, description, breadcrumbs = [], actions, className }) {
    if (breadcrumbs.length === 0 && !actions) {
        return null;
    }

    return (
        <section className={cn('mb-4 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between', className)}>
            <div className="min-w-0">
                {breadcrumbs.length > 0 && (
                    <nav aria-label={`${title} breadcrumb`} className="flex flex-wrap items-center gap-1.5 text-sm font-medium text-slate-500 dark:text-slate-400">
                            {breadcrumbs.map((crumb, i) => (
                                <span key={i} className="flex items-center gap-2">
                                    {i > 0 && <ChevronRight className="h-4 w-4 shrink-0 opacity-55" />}
                                    {crumb.href ? (
                                        <Link
                                            href={crumb.href}
                                            className="inline-flex items-center transition-colors hover:text-foreground dark:hover:text-slate-200"
                                        >
                                            {crumb.label}
                                        </Link>
                                    ) : (
                                        <span className="inline-flex items-center font-semibold text-foreground dark:text-slate-100">
                                            {crumb.label}
                                        </span>
                                    )}
                                </span>
                            ))}
                    </nav>
                )}
            </div>
            {actions && <div className="flex shrink-0 flex-wrap items-center gap-2">{actions}</div>}
        </section>
    );
}

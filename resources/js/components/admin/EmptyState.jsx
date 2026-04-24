import { Card, CardContent } from '@/components/ui/card';

/**
 * @param {{ title: string, description?: string, icon?: import('react').ReactNode, action?: import('react').ReactNode }} props
 */
export function EmptyState({ title, description, icon, action }) {
    return (
        <Card className="border-dashed">
            <CardContent className="flex flex-col items-center justify-center py-16 text-center">
                {icon && <div className="mb-4 rounded-full bg-muted/80 p-4 text-muted-foreground">{icon}</div>}
                <h3 className="text-sm font-semibold text-foreground">{title}</h3>
                {description && <p className="mt-1 max-w-sm text-sm text-muted-foreground">{description}</p>}
                {action && <div className="mt-6">{action}</div>}
            </CardContent>
        </Card>
    );
}

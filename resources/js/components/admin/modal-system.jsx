import { AlertTriangle, CheckCircle2, Info, Trash2, XCircle } from 'lucide-react';
import { cn } from '@/lib/utils';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter as PrimitiveDialogFooter,
    DialogHeader as PrimitiveDialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';

export { Dialog as Modal };

export function ModalHeader({ icon: Icon = Info, iconTone = 'info', title, description, className, children }) {
    const tones = {
        info: 'bg-[hsl(var(--info-soft))] text-[hsl(var(--info))]',
        success: 'bg-[hsl(var(--success-soft))] text-[hsl(var(--success))]',
        warning: 'bg-[hsl(var(--warning-soft))] text-[hsl(var(--warning))]',
        danger: 'bg-red-100 text-red-600 dark:bg-red-950/40 dark:text-red-200',
    };

    return (
        <PrimitiveDialogHeader className={cn('gap-4', className)}>
            <div className="flex items-start gap-4 pr-8">
                <span className={cn('mt-0.5 flex h-11 w-11 shrink-0 items-center justify-center rounded-full', tones[iconTone] ?? tones.info)}>
                    <Icon className="h-5 w-5" />
                </span>
                <div className="space-y-2">
                    {title ? <DialogTitle className="text-[1.9rem]">{title}</DialogTitle> : null}
                    {description ? <DialogDescription>{description}</DialogDescription> : null}
                    {children}
                </div>
            </div>
        </PrimitiveDialogHeader>
    );
}

export function ModalFooter({ className, ...props }) {
    return <PrimitiveDialogFooter className={cn('pt-2', className)} {...props} />;
}

export function ConfirmationModal({
    open,
    onOpenChange,
    title,
    description,
    confirmLabel = 'Confirm',
    cancelLabel = 'Cancel',
    onConfirm,
    tone = 'info',
    loading = false,
}) {
    const iconByTone = {
        info: Info,
        success: CheckCircle2,
        warning: AlertTriangle,
        danger: Trash2,
    };

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="max-w-[32rem]">
                <ModalHeader icon={iconByTone[tone]} iconTone={tone} title={title} description={description} />
                <ModalFooter>
                    <DialogClose asChild>
                        <Button type="button" variant="outline">
                            {cancelLabel}
                        </Button>
                    </DialogClose>
                    <Button
                        type="button"
                        variant={tone === 'danger' ? 'destructive' : 'default'}
                        disabled={loading}
                        onClick={onConfirm}
                    >
                        {loading ? 'Processing...' : confirmLabel}
                    </Button>
                </ModalFooter>
            </DialogContent>
        </Dialog>
    );
}

export function AlertModal({
    open,
    onOpenChange,
    title,
    description,
    actionLabel = 'Close',
    tone = 'info',
    children,
}) {
    const iconByTone = {
        info: Info,
        success: CheckCircle2,
        warning: AlertTriangle,
        danger: XCircle,
    };

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="max-w-[32rem]">
                <ModalHeader icon={iconByTone[tone]} iconTone={tone} title={title} description={description}>
                    {children}
                </ModalHeader>
                <ModalFooter>
                    <DialogClose asChild>
                        <Button type="button">{actionLabel}</Button>
                    </DialogClose>
                </ModalFooter>
            </DialogContent>
        </Dialog>
    );
}

export function DrawerModal({ open, onOpenChange, title, description, children, footer, className }) {
    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent
                className={cn(
                    'left-auto right-0 top-0 h-screen max-w-[36rem] translate-x-0 translate-y-0 rounded-none rounded-l-[1.75rem] border-y-0 border-r-0 p-0 data-[state=closed]:translate-x-6 data-[state=closed]:translate-y-0 data-[state=open]:translate-x-0 data-[state=open]:translate-y-0',
                    className,
                )}
            >
                <div className="flex h-full flex-col">
                    <div className="border-b border-border/70 px-6 py-5">
                        <ModalHeader icon={Info} iconTone="info" title={title} description={description} className="gap-0" />
                    </div>
                    <div className="admin-scrollbar flex-1 overflow-y-auto px-6 py-6">{children}</div>
                    {footer ? <div className="border-t border-border/70 px-6 py-5">{footer}</div> : null}
                </div>
            </DialogContent>
        </Dialog>
    );
}

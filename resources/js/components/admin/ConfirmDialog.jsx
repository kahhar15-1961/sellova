import { ConfirmationModal } from '@/components/admin/modal-system';

/**
 * @param {{ open: boolean, onOpenChange: (v: boolean) => void, title: string, description?: string, confirmLabel?: string, cancelLabel?: string, onConfirm: () => void, variant?: 'default' | 'destructive' }} props
 */
export function ConfirmDialog({
    open,
    onOpenChange,
    title,
    description,
    confirmLabel = 'Confirm',
    cancelLabel = 'Cancel',
    onConfirm,
    variant = 'default',
}) {
    return (
        <ConfirmationModal
            open={open}
            onOpenChange={onOpenChange}
            title={title}
            description={description}
            confirmLabel={confirmLabel}
            cancelLabel={cancelLabel}
            tone={variant === 'destructive' ? 'danger' : 'info'}
            onConfirm={() => {
                onConfirm();
                onOpenChange(false);
            }}
        />
    );
}

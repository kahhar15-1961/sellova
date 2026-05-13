import { Badge } from '@/components/ui/badge';

const variantMap = {
    active: 'success',
    completed: 'success',
    verified: 'success',
    published: 'success',
    paid: 'success',
    paid_in_escrow: 'warning',
    shipped_or_delivered: 'success',
    requested: 'warning',
    under_review: 'warning',
    submitted: 'warning',
    evidence_collection: 'warning',
    under_dispute: 'danger',
    inactive: 'warning',
    refunded: 'warning',
    escalated: 'danger',
    pending: 'warning',
    processing: 'warning',
    disputed: 'warning',
    suspended: 'danger',
    rejected: 'danger',
    cancelled: 'danger',
    closed: 'muted',
    draft: 'muted',
    needs_attention: 'warning',
    approved: 'success',
    disabled: 'muted',
    default: 'secondary',
};

/**
 * @param {{ status: string, className?: string }} props
 */
export function StatusBadge({ status, className }) {
    const key = String(status || '')
        .toLowerCase()
        .replace(/\s+/g, '_');
    const variant = variantMap[key] ?? variantMap.default;
    return (
        <Badge variant={variant} className={className}>
            {status}
        </Badge>
    );
}

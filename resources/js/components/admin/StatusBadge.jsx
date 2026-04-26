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
    refunded: 'muted',
    escalated: 'danger',
    pending: 'warning',
    processing: 'warning',
    disputed: 'warning',
    suspended: 'danger',
    rejected: 'danger',
    cancelled: 'muted',
    closed: 'muted',
    draft: 'secondary',
    needs_attention: 'warning',
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

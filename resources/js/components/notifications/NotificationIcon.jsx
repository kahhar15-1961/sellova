import {
    AlertCircle,
    BadgeCheck,
    Bell,
    MessageSquareText,
    Package,
    ReceiptText,
    ShieldCheck,
    ShoppingBag,
    Zap,
} from 'lucide-react';
import { cn } from '@/lib/utils';

const iconMap = {
    'alert-circle': AlertCircle,
    'badge-check': BadgeCheck,
    bell: Bell,
    'message-square-text': MessageSquareText,
    package: Package,
    'receipt-text': ReceiptText,
    'shield-check': ShieldCheck,
    'shopping-bag': ShoppingBag,
    zap: Zap,
};

const colorMap = {
    amber: 'bg-slate-50 text-slate-600 ring-slate-200',
    emerald: 'bg-slate-50 text-slate-600 ring-slate-200',
    indigo: 'bg-slate-50 text-slate-600 ring-slate-200',
    orange: 'bg-slate-50 text-slate-600 ring-slate-200',
    rose: 'bg-slate-50 text-slate-600 ring-slate-200',
    sky: 'bg-slate-50 text-slate-600 ring-slate-200',
    slate: 'bg-slate-50 text-slate-600 ring-slate-200',
    teal: 'bg-slate-50 text-slate-600 ring-slate-200',
};

export function NotificationIcon({ icon = 'bell', color = 'slate', className }) {
    const Icon = iconMap[icon] || Bell;
    const palette = colorMap[color] || colorMap.slate;

    return (
        <span className={cn('flex size-10 shrink-0 items-center justify-center rounded-full ring-1', palette, className)}>
            <Icon className="size-4" />
        </span>
    );
}

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
    amber: 'bg-amber-50 text-amber-600 ring-amber-100',
    emerald: 'bg-emerald-50 text-emerald-600 ring-emerald-100',
    indigo: 'bg-indigo-50 text-indigo-600 ring-indigo-100',
    orange: 'bg-orange-50 text-orange-600 ring-orange-100',
    rose: 'bg-rose-50 text-rose-500 ring-rose-100',
    sky: 'bg-sky-50 text-sky-600 ring-sky-100',
    slate: 'bg-slate-100 text-slate-600 ring-slate-200',
    teal: 'bg-teal-50 text-teal-600 ring-teal-100',
};

export function NotificationIcon({ icon = 'bell', color = 'slate', className }) {
    const Icon = iconMap[icon] || Bell;
    const palette = colorMap[color] || colorMap.slate;

    return (
        <span className={cn('flex size-12 shrink-0 items-center justify-center rounded-full ring-1', palette, className)}>
            <Icon className="size-5" />
        </span>
    );
}

import { Bell } from 'lucide-react';

export function NotificationEmptyState() {
    return (
        <div className="flex flex-col items-center justify-center rounded-xl border border-dashed border-slate-200 bg-slate-50/80 px-5 py-8 text-center">
            <span className="flex size-12 items-center justify-center rounded-full bg-white text-slate-400 shadow-sm ring-1 ring-slate-200">
                <Bell className="size-5" />
            </span>
            <h3 className="mt-3 text-sm font-bold text-slate-900">No notifications yet</h3>
            <p className="mt-2 max-w-xs text-sm font-medium leading-6 text-slate-500">Fresh order, payout, message, and compliance updates will appear here in real time.</p>
        </div>
    );
}

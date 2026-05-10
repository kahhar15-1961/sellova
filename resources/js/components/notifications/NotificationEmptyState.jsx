import { Bell } from 'lucide-react';

export function NotificationEmptyState() {
    return (
        <div className="flex flex-col items-center justify-center rounded-[24px] border border-dashed border-slate-200 bg-slate-50/80 px-6 py-10 text-center">
            <span className="flex size-14 items-center justify-center rounded-full bg-white text-slate-400 shadow-sm ring-1 ring-slate-200">
                <Bell className="size-6" />
            </span>
            <h3 className="mt-4 text-base font-extrabold text-slate-900">No notifications yet</h3>
            <p className="mt-2 max-w-xs text-sm font-medium leading-6 text-slate-500">Fresh order, payout, message, and compliance updates will appear here in real time.</p>
        </div>
    );
}

export function NotificationSkeleton() {
    return (
        <div className="grid gap-2.5">
            {[1, 2, 3].map((item) => (
                <div key={item} className="flex animate-pulse gap-3 rounded-xl border border-slate-100 bg-white px-3.5 py-3">
                    <div className="size-10 rounded-full bg-slate-100" />
                    <div className="min-w-0 flex-1">
                        <div className="h-4 w-32 rounded-full bg-slate-100" />
                        <div className="mt-2.5 h-3 w-full rounded-full bg-slate-100" />
                        <div className="mt-2 h-3 w-3/4 rounded-full bg-slate-100" />
                        <div className="mt-3 h-3 w-20 rounded-full bg-slate-100" />
                    </div>
                </div>
            ))}
        </div>
    );
}

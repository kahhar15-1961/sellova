export function NotificationSkeleton() {
    return (
        <div className="grid gap-4">
            {[1, 2, 3].map((item) => (
                <div key={item} className="flex animate-pulse gap-4 rounded-[24px] border border-slate-100 bg-white px-4 py-4">
                    <div className="size-12 rounded-full bg-slate-100" />
                    <div className="min-w-0 flex-1">
                        <div className="h-4 w-40 rounded-full bg-slate-100" />
                        <div className="mt-3 h-3 w-full rounded-full bg-slate-100" />
                        <div className="mt-2 h-3 w-3/4 rounded-full bg-slate-100" />
                        <div className="mt-4 h-3 w-24 rounded-full bg-slate-100" />
                    </div>
                </div>
            ))}
        </div>
    );
}

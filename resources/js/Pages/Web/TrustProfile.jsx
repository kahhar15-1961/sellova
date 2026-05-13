import { Head, Link } from '@inertiajs/react';
import { useMemo, useState } from 'react';
import {
    BadgeCheck,
    CalendarDays,
    CheckCircle2,
    Clock,
    Flag,
    MessageSquareText,
    Package,
    ShieldCheck,
    Star,
    Store,
    ThumbsDown,
    ThumbsUp,
    User,
} from 'lucide-react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { formatMoney } from '@/lib/utils';
import { AppShell, EnterpriseFooter } from './Workspace';

function fmtDate(value) {
    if (!value) return 'Not available';
    try {
        return new Date(value).toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });
    } catch {
        return String(value);
    }
}

function stars(value) {
    const rating = Number(value || 0);
    return (
        <span className="inline-flex items-center gap-0.5 text-amber-500" aria-label={`${rating} out of 5 stars`}>
            {[1, 2, 3, 4, 5].map((item) => (
                <Star key={item} className={`size-4 ${item <= Math.round(rating) ? 'fill-current' : ''}`} />
            ))}
        </span>
    );
}

function scoreTone(label) {
    const normalized = String(label || '').toLowerCase();
    if (normalized.includes('excellent')) return {
        card: 'border-emerald-200 bg-emerald-50 text-emerald-950 shadow-[0_14px_36px_-30px_rgba(16,185,129,0.6)]',
        muted: 'text-emerald-700',
        icon: 'text-emerald-200/70',
    };
    if (normalized.includes('good')) return {
        card: 'border-sky-200 bg-sky-50 text-sky-950 shadow-[0_14px_36px_-30px_rgba(14,165,233,0.6)]',
        muted: 'text-sky-700',
        icon: 'text-sky-200/70',
    };
    if (normalized.includes('risky')) return {
        card: 'border-rose-200 bg-rose-50 text-rose-950 shadow-[0_14px_36px_-30px_rgba(244,63,94,0.6)]',
        muted: 'text-rose-700',
        icon: 'text-rose-200/70',
    };
    if (normalized.includes('new')) return {
        card: 'border-violet-200 bg-violet-50 text-violet-950 shadow-[0_14px_36px_-30px_rgba(139,92,246,0.6)]',
        muted: 'text-violet-700',
        icon: 'text-violet-200/70',
    };
    return {
        card: 'border-amber-200 bg-amber-50 text-amber-950 shadow-[0_14px_36px_-30px_rgba(245,158,11,0.6)]',
        muted: 'text-amber-700',
        icon: 'text-amber-200/70',
    };
}

function statusLabel(value) {
    return String(value || 'not submitted').replace(/_/g, ' ');
}

export default function TrustProfile({ profileData, reviewsEndpoint, canReportReviews = false, initialMarketplace = {} }) {
    const [sort, setSort] = useState('newest');
    const [feedback, setFeedback] = useState('');
    const [rating, setRating] = useState('');
    const reviews = useMemo(() => {
        let rows = [...(profileData.reviews || [])];
        if (feedback) rows = rows.filter((review) => String(review.feedback_type) === feedback);
        if (rating) rows = rows.filter((review) => Number(review.rating) === Number(rating));
        rows.sort((a, b) => {
            if (sort === 'oldest') return new Date(a.created_at || 0) - new Date(b.created_at || 0);
            if (sort === 'highest_rating') return Number(b.rating || 0) - Number(a.rating || 0);
            if (sort === 'lowest_rating') return Number(a.rating || 0) - Number(b.rating || 0);
            if (sort === 'good_feedback') return Number(b.feedback_type === 'good') - Number(a.feedback_type === 'good');
            if (sort === 'bad_feedback') return Number(b.feedback_type === 'bad') - Number(a.feedback_type === 'bad');
            return new Date(b.created_at || 0) - new Date(a.created_at || 0);
        });
        return rows;
    }, [profileData.reviews, sort, feedback, rating]);
    const profile = profileData.profile || {};
    const stats = profileData.stats || {};
    const trust = profileData.trust_score || {};
    const isSeller = profileData.type === 'seller';
    const title = `${profile.name || 'Marketplace profile'} Details`;
    const shell = initialMarketplace || {};
    const cartCount = (shell.cart || []).reduce((sum, item) => sum + Number(item.quantity || 0), 0);
    const trustTone = scoreTone(trust.label);
    const content = (
        <>
            <Head title={title} />
            <section className="pb-2">
                <div className="mx-auto grid max-w-7xl gap-8 lg:grid-cols-[minmax(0,1fr)_384px]">
                    <div className="rounded-[22px] border border-slate-200 bg-white px-8 py-9 shadow-[0_12px_34px_-30px_rgba(15,23,42,0.55)] sm:px-10">
                        <div className="flex flex-col gap-6 sm:flex-row sm:items-center">
                            <div className="flex size-24 shrink-0 items-center justify-center overflow-hidden rounded-[18px] border border-dashed border-slate-300 bg-slate-100 text-4xl font-black text-slate-500 sm:size-28">
                                {profile.avatar ? (
                                    <img src={profile.avatar} alt={profile.name} className="h-full w-full object-cover" />
                                ) : (
                                    <span>{String(profile.name || (isSeller ? 'Seller' : 'Buyer')).split(/\s+/).map((part) => part[0]).join('').slice(0, 2).toUpperCase()}</span>
                                )}
                            </div>
                            <div className="min-w-0">
                                <p className="text-[11px] font-black uppercase tracking-[0.18em] text-slate-400">{isSeller ? 'Seller Details' : 'Buyer Details'}</p>
                                <h1 className="mt-2 text-4xl font-black leading-tight tracking-tight text-slate-950">{profile.name}</h1>
                                <p className="mt-2 max-w-3xl text-base font-medium leading-7 text-slate-500">{profile.description || (isSeller ? 'Public seller trust profile with store performance, ratings, policies, and marketplace activity.' : 'Seller-visible buyer trust profile with marketplace reliability, reviews, and safe activity signals.')}</p>
                                <div className="mt-5 flex flex-wrap items-center gap-x-5 gap-y-3 text-sm font-bold text-slate-400">
                                    <span className="inline-flex items-center gap-2"><CalendarDays className="size-4" />Created {fmtDate(profile.store_created_at || profile.account_created_at)}</span>
                                    <span className="inline-flex items-center gap-2"><Clock className="size-4" />Last active {fmtDate(profile.last_active_at)}</span>
                                </div>
                                <div className="mt-4 flex flex-wrap items-center gap-2">
                                    {profile.verified ? <Badge className="gap-1 bg-emerald-600"><BadgeCheck className="size-3" />Verified</Badge> : <Badge variant="secondary">Unverified</Badge>}
                                    <Badge variant="outline">KYC: {statusLabel(profile.kyc_status)}</Badge>
                                    <Badge variant="outline">Status: {statusLabel(profile.store_status || profile.verification_status || 'active')}</Badge>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div className={`relative overflow-hidden rounded-[22px] border px-8 py-8 ${trustTone.card}`}>
                        <div className={`absolute -right-6 -top-8 ${trustTone.icon}`}>
                            <ShieldCheck className="size-36 stroke-[1.5]" />
                        </div>
                        <div className="relative">
                            <p className={`text-[11px] font-black uppercase tracking-[0.18em] ${trustTone.muted}`}>Trust score</p>
                            <p className="mt-2 text-6xl font-black leading-none tracking-tight">{trust.score ?? 0}</p>
                            <p className="mt-5 text-2xl font-black">{trust.label || 'New user'}</p>
                            <p className={`mt-2 max-w-[280px] text-sm font-semibold leading-6 ${trustTone.muted}`}>Calculated from orders, disputes, refunds, ratings, account age, and KYC completion rate.</p>
                        </div>
                    </div>
                </div>
            </section>

            <section className="mx-auto grid max-w-7xl gap-6 px-0 py-8">
                <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                    {[
                        ['Total orders', stats.total_orders],
                        ['Completed', stats.completed_orders],
                        ['Cancelled', stats.cancelled_orders],
                        ['Disputes', stats.dispute_count],
                        ['Refunds', stats.refund_count],
                        ['Average rating', stats.average_rating],
                        ['Good feedback', stats.good_feedback_count],
                        ['Bad feedback', stats.bad_feedback_count],
                    ].map(([label, value]) => (
                        <div key={label} className="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
                            <p className="text-xs font-black uppercase tracking-[0.14em] text-slate-400">{label}</p>
                            <p className="mt-2 text-2xl font-black">{value ?? 0}</p>
                        </div>
                    ))}
                </div>

                <div className="grid gap-6 lg:grid-cols-[1fr_360px]">
                    <section className="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                        <div className="flex flex-wrap items-center justify-between gap-3">
                            <div>
                                <h2 className="text-xl font-black">Reviews and feedback</h2>
                                <p className="mt-1 text-sm font-semibold text-slate-500">{stars(stats.average_rating)} <span className="ml-2">{stats.average_rating || 0} average rating</span></p>
                            </div>
                            <div className="flex flex-wrap gap-2">
                                <select value={sort} onChange={(e) => setSort(e.target.value)} className="h-10 rounded-lg border border-slate-200 bg-white px-3 text-sm font-bold">
                                    <option value="newest">Newest</option>
                                    <option value="oldest">Oldest</option>
                                    <option value="highest_rating">Highest rating</option>
                                    <option value="lowest_rating">Lowest rating</option>
                                    <option value="good_feedback">Good feedback</option>
                                    <option value="bad_feedback">Bad feedback</option>
                                </select>
                                <select value={rating} onChange={(e) => setRating(e.target.value)} className="h-10 rounded-lg border border-slate-200 bg-white px-3 text-sm font-bold">
                                    <option value="">All ratings</option>
                                    {[5, 4, 3, 2, 1].map((item) => <option key={item} value={item}>{item} stars</option>)}
                                </select>
                                <select value={feedback} onChange={(e) => setFeedback(e.target.value)} className="h-10 rounded-lg border border-slate-200 bg-white px-3 text-sm font-bold">
                                    <option value="">All feedback</option>
                                    <option value="good">Good</option>
                                    <option value="neutral">Neutral</option>
                                    <option value="bad">Bad</option>
                                </select>
                            </div>
                        </div>

                        <div className="mt-5 grid gap-4">
                            {reviews.length ? reviews.map((review) => (
                                <article key={review.id} className="rounded-xl border border-slate-200 p-4">
                                    <div className="flex flex-wrap items-start justify-between gap-3">
                                        <div>
                                            {review.reviewer?.profile_href ? (
                                                <Link href={review.reviewer.profile_href} className="font-black text-indigo-700 hover:text-indigo-900">{review.reviewer?.name || 'Marketplace member'}</Link>
                                            ) : (
                                                <p className="font-black">{review.reviewer?.name || 'Marketplace member'}</p>
                                            )}
                                            <div className="mt-1 flex flex-wrap items-center gap-2 text-xs font-bold text-slate-500">
                                                {stars(review.rating)}
                                                <Badge variant="outline">{review.feedback_type}</Badge>
                                                {review.is_verified_order ? <Badge className="gap-1 bg-emerald-600"><CheckCircle2 className="size-3" />Verified order</Badge> : null}
                                                <span>{fmtDate(review.created_at)}</span>
                                            </div>
                                        </div>
                                        {!canReportReviews || String(review.id).startsWith('seller-review-') || String(review.id).startsWith('buyer-review-') ? null : (
                                            <Button variant="ghost" size="sm" onClick={() => window.fetch(`/api/reviews/${review.id}/report`, { method: 'POST', credentials: 'same-origin', headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '' }, body: JSON.stringify({ reason_code: 'marketplace_review_report' }) })}>
                                                <Flag className="size-4" />Report
                                            </Button>
                                        )}
                                    </div>
                                    {review.title ? <h3 className="mt-4 font-black">{review.title}</h3> : null}
                                    <p className="mt-2 text-sm font-medium leading-6 text-slate-600">{review.comment || 'No written review.'}</p>
                                    {review.tags?.length ? <div className="mt-3 flex flex-wrap gap-2">{review.tags.map((tag) => <span key={tag} className="rounded-md bg-slate-100 px-2 py-1 text-xs font-bold text-slate-600">{tag}</span>)}</div> : null}
                                    {review.seller_reply ? <p className="mt-3 rounded-lg bg-slate-50 p-3 text-sm font-semibold text-slate-600">Seller reply: {review.seller_reply}</p> : null}
                                </article>
                            )) : (
                                <div className="rounded-xl border border-dashed border-slate-300 bg-slate-50 p-8 text-center">
                                    <p className="font-black">No reviews match these filters.</p>
                                    <p className="mt-1 text-sm font-semibold text-slate-500">Verified review history will appear after completed marketplace orders.</p>
                                </div>
                            )}
                        </div>
                    </section>

                    <aside className="grid content-start gap-6">
                        <div className="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                            <h2 className="text-lg font-black">Reliability signals</h2>
                            <div className="mt-4 grid gap-3">
                                {[
                                    ['Completion rate', `${stats.order_completion_rate || stats.delivery_success_rate || 0}%`, Package],
                                    ['Escrow completion', `${stats.escrow_completion_rate || stats.order_completion_rate || 0}%`, ShieldCheck],
                                    ['Communication', stats.communication_rating || 0, MessageSquareText],
                                    [isSeller ? 'Product quality' : 'Payment reliability', stats.product_quality_rating || stats.payment_reliability_indicator || 'Not enough data', isSeller ? ThumbsUp : CheckCircle2],
                                ].map(([label, value, Icon]) => (
                                    <div key={label} className="flex items-center justify-between gap-3 rounded-xl bg-slate-50 p-3">
                                        <span className="inline-flex items-center gap-2 text-sm font-bold text-slate-600"><Icon className="size-4" />{label}</span>
                                        <span className="font-black">{value}</span>
                                    </div>
                                ))}
                            </div>
                            {profileData.actions?.contact_allowed ? <Button className="mt-4 w-full bg-slate-950"><MessageSquareText className="size-4" />Contact</Button> : null}
                        </div>

                        {isSeller ? (
                            <>
                                <div className="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                                    <h2 className="text-lg font-black">Store policies</h2>
                                    <div className="mt-4 grid gap-3">
                                        {(profileData.store_policies || []).map((policy) => (
                                            <div key={policy.label} className="rounded-xl bg-slate-50 p-3">
                                                <p className="text-sm font-black">{policy.label}</p>
                                                <p className="mt-1 text-sm font-semibold text-slate-500">{policy.value}</p>
                                            </div>
                                        ))}
                                    </div>
                                </div>
                                <div className="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                                    <h2 className="text-lg font-black">Featured products</h2>
                                    <div className="mt-4 grid gap-3">
                                        {(profileData.featured_products || []).map((product) => (
                                            <Link key={product.id} href={product.href} className="grid grid-cols-[56px_1fr] gap-3 rounded-xl bg-slate-50 p-2 hover:bg-indigo-50">
                                                <div className="flex size-14 items-center justify-center overflow-hidden rounded-lg bg-white text-slate-300 ring-1 ring-slate-200">
                                                    {product.image ? <img src={product.image} alt={product.title} className="h-full w-full object-cover" /> : <Package className="size-5" />}
                                                </div>
                                                <div className="min-w-0">
                                                    <p className="truncate text-sm font-black">{product.title}</p>
                                                    <p className="mt-1 text-xs font-bold text-slate-500">{formatMoney(product.price, product.currency)}</p>
                                                </div>
                                            </Link>
                                        ))}
                                    </div>
                                </div>
                            </>
                        ) : null}

                        <div className="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                            <h2 className="text-lg font-black">Feedback balance</h2>
                            <div className="mt-4 grid grid-cols-3 gap-2">
                                <div className="rounded-xl bg-emerald-50 p-3 text-emerald-700"><ThumbsUp className="size-4" /><p className="mt-2 text-xl font-black">{stats.good_feedback_count || 0}</p><p className="text-xs font-bold">Good</p></div>
                                <div className="rounded-xl bg-slate-100 p-3 text-slate-700"><Star className="size-4" /><p className="mt-2 text-xl font-black">{stats.neutral_feedback_count || 0}</p><p className="text-xs font-bold">Neutral</p></div>
                                <div className="rounded-xl bg-rose-50 p-3 text-rose-700"><ThumbsDown className="size-4" /><p className="mt-2 text-xl font-black">{stats.bad_feedback_count || 0}</p><p className="text-xs font-bold">Bad</p></div>
                            </div>
                        </div>
                    </aside>
                </div>
            </section>
        </>
    );

    return (
        <AppShell
            mode="buyer"
            view="marketplace"
            user={shell.user}
            cartCount={cartCount}
            wishlistCount={(shell.wishlist || []).length}
            categories={shell.categories || []}
            notifications={shell.buyerOps?.notifications || []}
            unreadNotificationCount={shell.buyerOps?.unreadNotificationCount || 0}
        >
            {content}
            <EnterpriseFooter trustItems={shell.trustItems || []} />
        </AppShell>
    );
}

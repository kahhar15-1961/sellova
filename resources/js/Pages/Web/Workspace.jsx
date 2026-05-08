import { Head, Link } from '@inertiajs/react';
import { useEffect, useMemo, useState } from 'react';
import {
    AlertCircle,
    ArrowRight,
    BadgeCheck,
    BarChart3,
    Bell,
    Boxes,
    BriefcaseBusiness,
    Building2,
    Check,
    ChevronRight,
    ClipboardCheck,
    CreditCard,
    Download,
    Flame,
    Heart,
    Home,
    Headphones,
    LayoutDashboard,
    LockKeyhole,
    Mail,
    Menu,
    MessageSquareText,
    Minus,
    Package,
    PackageCheck,
    PackageSearch,
    Plus,
    ReceiptText,
    Search,
    Settings,
    ShieldCheck,
    ShoppingBag,
    ShoppingCart,
    SlidersHorizontal,
    Sparkles,
    Star,
    Store,
    Tag,
    TrendingUp,
    Truck,
    User,
    WalletCards,
    Zap,
    X,
} from 'lucide-react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { cn } from '@/lib/utils';

const STORAGE_KEY = 'sellova.web.marketplace.v1';

const fallbackCategoryNames = ['All'];
const PAGE_SIZE = 12;

function money(value) {
    return `৳${Number(value || 0).toLocaleString('en-BD')}`;
}

function loadState() {
    if (typeof window === 'undefined') return {};
    try {
        return JSON.parse(window.localStorage.getItem(STORAGE_KEY) || '{}');
    } catch {
        return {};
    }
}

function saveState(state) {
    if (typeof window !== 'undefined') {
        window.localStorage.setItem(STORAGE_KEY, JSON.stringify(state));
    }
}

function csrfToken() {
    if (typeof document === 'undefined') return '';
    return document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '';
}

async function postAction(url, payload) {
    const response = await fetch(url, {
        method: 'POST',
        credentials: 'same-origin',
        headers: {
            'Content-Type': 'application/json',
            'Accept': 'application/json',
            'X-CSRF-TOKEN': csrfToken(),
        },
        body: JSON.stringify(payload),
    });
    const data = await response.json().catch(() => ({}));
    if (!response.ok || data?.ok === false) {
        throw new Error(data?.message || `Request failed with ${response.status}`);
    }
    return data;
}

function asNumber(value, fallback = 0) {
    const number = Number(value);
    return Number.isFinite(number) ? number : fallback;
}

function ProductMedia({ src, alt, className, icon: Icon = PackageSearch }) {
    const [failed, setFailed] = useState(false);
    if (!src || failed) {
        return (
            <div className={cn('flex items-center justify-center bg-slate-100 text-slate-400', className)} role="img" aria-label={alt || 'Product image unavailable'}>
                <Icon className="size-10" />
            </div>
        );
    }

    return <img src={src} alt={alt || ''} loading="lazy" decoding="async" onError={() => setFailed(true)} className={className} />;
}

function isRootCategory(category) {
    const parentId = category?.parent_id;
    return parentId === null || parentId === undefined || parentId === '' || Number(parentId) === 0;
}

function isChildCategory(category) {
    return !isRootCategory(category);
}

function mergeServerState(initialMarketplace) {
    const stored = loadState();
    if (!initialMarketplace) {
        return {};
    }

    const products = Array.isArray(initialMarketplace.products) && initialMarketplace.products.length > 0
        ? initialMarketplace.products
        : [];

    return {
        ...initialMarketplace,
        products,
        cart: Array.isArray(initialMarketplace.cart) ? initialMarketplace.cart : (stored.cart ?? []),
        wishlist: Array.isArray(initialMarketplace.wishlist) ? initialMarketplace.wishlist : [],
        orders: Array.isArray(initialMarketplace.orders) ? initialMarketplace.orders : [],
        chats: Array.isArray(initialMarketplace.chats) ? initialMarketplace.chats : [],
        sellerProducts: Array.isArray(initialMarketplace.sellerProducts) ? initialMarketplace.sellerProducts : [],
        coupons: Array.isArray(initialMarketplace.coupons) ? initialMarketplace.coupons : [],
        payoutRequests: Array.isArray(initialMarketplace.payoutRequests) ? initialMarketplace.payoutRequests : [],
        supportTickets: Array.isArray(initialMarketplace.supportTickets) ? initialMarketplace.supportTickets : [],
        business: initialMarketplace.business ?? { name: '', phone: '', address: '', verification: '' },
        user: initialMarketplace.user ?? stored.user ?? { name: 'Guest buyer', email: '', role: 'buyer', city: '' },
    };
}

function productSnapshot(product) {
    return {
        id: product.id,
        title: product.title,
        category: product.category,
        subcategory: product.subcategory,
        subcategory_id: product.subcategory_id,
        type: product.type,
        productType: product.productType,
        productTypeLabel: product.productTypeLabel,
        fulfillmentHint: product.fulfillmentHint,
        price: product.price,
        oldPrice: product.oldPrice,
        discountPercentage: product.discountPercentage,
        discountLabel: product.discountLabel,
        stock: product.stock,
        availableStock: product.availableStock,
        city: product.city,
        seller: product.seller,
        rating: product.rating,
        verified: product.verified,
        condition: product.condition,
        image: product.image,
        images: product.images,
        attributes: product.attributes,
        attributeRows: product.attributeRows,
        brand: product.brand,
        warrantyStatus: product.warrantyStatus,
        productLocation: product.productLocation,
        variants: product.variants,
        tags: product.tags,
        description: product.description,
    };
}

function useMarketplaceState(initialMarketplace) {
    const [pendingAction, setPendingAction] = useState('');
    const [notice, setNotice] = useState(null);
    const [state, setState] = useState(() => ({
        user: { name: 'Guest buyer', email: '', role: 'buyer', city: '' },
        products: [],
        cart: [],
        wishlist: [],
        orders: [],
        chats: [],
        sellerProducts: [],
        coupons: [],
        payoutRequests: [],
        business: { name: '', phone: '', address: '', verification: '' },
        supportTickets: [],
        categories: [],
        featuredVendor: null,
        hero: null,
        trustItems: [],
        metrics: {},
        ...mergeServerState(initialMarketplace),
    }));

    useEffect(() => saveState(state), [state]);
    useEffect(() => {
        if (!notice) return undefined;
        const timer = window.setTimeout(() => setNotice(null), 4200);
        return () => window.clearTimeout(timer);
    }, [notice]);

    const applyServerPayload = (payload) => {
        if (payload?.marketplace) {
            setState((current) => ({
                ...current,
                ...payload.marketplace,
                products: payload.marketplace.products?.length ? payload.marketplace.products : current.products,
                cart: Array.isArray(payload.marketplace.cart) ? payload.marketplace.cart : current.cart,
                wishlist: Array.isArray(payload.marketplace.wishlist) ? payload.marketplace.wishlist : current.wishlist,
                orders: Array.isArray(payload.marketplace.orders) ? payload.marketplace.orders : current.orders,
                chats: Array.isArray(payload.marketplace.chats) ? payload.marketplace.chats : current.chats,
                sellerProducts: Array.isArray(payload.marketplace.sellerProducts) ? payload.marketplace.sellerProducts : current.sellerProducts,
                coupons: Array.isArray(payload.marketplace.coupons) ? payload.marketplace.coupons : current.coupons,
                payoutRequests: Array.isArray(payload.marketplace.payoutRequests) ? payload.marketplace.payoutRequests : current.payoutRequests,
                supportTickets: Array.isArray(payload.marketplace.supportTickets) ? payload.marketplace.supportTickets : current.supportTickets,
            }));
        }
    };

    const runAction = async (key, url, payload, successMessage) => {
        setPendingAction(key);
        try {
            const response = await postAction(url, payload);
            applyServerPayload(response);
            setNotice({ type: 'success', body: successMessage });
            return response;
        } catch (error) {
            setNotice({ type: 'error', body: error.message || 'Something went wrong. Please try again.' });
            throw error;
        } finally {
            setPendingAction('');
        }
    };

    const addToCart = (product, quantity = 1) => {
        if ((product.availableStock ?? product.stock ?? 0) <= 0) {
            setNotice({ type: 'error', body: 'This listing is out of stock.' });
            return Promise.resolve();
        }
        return runAction('cart:add', '/web/actions/cart/add', { product_id: product.id, quantity, product_snapshot: productSnapshot(product) }, 'Added to cart.');
    };

    const updateCart = (productId, quantity) => {
        const nextQuantity = Math.max(0, quantity);
        return runAction(`cart:${productId}`, '/web/actions/cart/update', { product_id: productId, quantity: nextQuantity }, nextQuantity > 0 ? 'Cart updated.' : 'Removed from cart.');
    };

    const removeCart = (productId) => {
        return updateCart(productId, 0);
    };

    const toggleWishlist = (productId) => {
        return runAction(`wishlist:${productId}`, '/web/actions/wishlist/toggle', { product_id: productId }, 'Wishlist updated.');
    };

    const checkout = (paymentMethod, address) => {
        if (!state.cart.length) {
            setNotice({ type: 'error', body: 'Your cart is empty.' });
            return Promise.resolve();
        }
        if (!String(address || '').trim()) {
            setNotice({ type: 'error', body: 'Add a shipping address before placing the order.' });
            return Promise.resolve();
        }
        return runAction('checkout', '/web/actions/checkout', {
            payment_method: paymentMethod,
            shipping_method: 'standard',
            shipping_address_line: address,
        }, 'Order submitted.');
    };

    const sendMessage = (body) => {
        const trimmed = body.trim();
        if (!trimmed) return;
        return runAction('support', '/web/actions/support/messages', { body: trimmed }, 'Message sent.');
    };

    const addSellerProduct = (payload) => {
        return runAction('seller:product', '/web/actions/seller/products', payload, 'Listing published.');
    };

    const adjustStock = (productId, delta) => {
        return runAction(`stock:${productId}`, '/web/actions/seller/inventory/adjust', { product_id: productId, delta }, 'Inventory updated.');
    };

    const addCoupon = (coupon) => {
        return runAction('seller:coupon', '/web/actions/seller/coupons', { code: coupon.code, value: Number(coupon.value || 0) }, 'Offer created.');
    };

    const requestPayout = (amount) => {
        return runAction('seller:payout', '/web/actions/seller/payouts', { amount: Number(amount || 0) }, 'Payout requested.');
    };

    const saveProfile = (profile) => {
        return runAction('profile', '/web/actions/profile', profile, 'Profile saved.');
    };

    const saveBusiness = (business) => {
        return runAction('business', '/web/actions/business', business, 'Business profile saved.');
    };

    return { state, setState, pendingAction, notice, addToCart, updateCart, removeCart, toggleWishlist, checkout, sendMessage, addSellerProduct, adjustStock, addCoupon, requestPayout, saveProfile, saveBusiness };
}

function Stat({ label, value, hint, icon: Icon }) {
    return (
        <div className="group rounded-lg border border-slate-200/80 bg-white/95 p-5 shadow-[0_18px_50px_-32px_rgba(15,23,42,0.45)] ring-1 ring-white transition duration-300 hover:-translate-y-0.5 hover:border-cyan-200 hover:shadow-[0_22px_60px_-34px_rgba(8,145,178,0.55)]">
            <div className="flex items-center justify-between gap-3">
                <p className="text-xs font-semibold uppercase tracking-wide text-slate-500">{label}</p>
                {Icon ? <span className="flex size-9 items-center justify-center rounded-md bg-cyan-50 text-cyan-700 ring-1 ring-cyan-100"><Icon className="size-4" /></span> : null}
            </div>
            <p className="mt-3 text-xl font-bold tracking-tight text-slate-950">{value}</p>
            <p className="mt-1 text-sm text-slate-500">{hint}</p>
        </div>
    );
}

function PageIntro({ eyebrow, title, description, actionHref, actionLabel }) {
    return (
        <div className="mb-6 flex flex-col gap-4 rounded-lg border border-slate-200/80 bg-white/90 p-5 shadow-[0_18px_50px_-36px_rgba(15,23,42,0.45)] ring-1 ring-white backdrop-blur md:flex-row md:items-end md:justify-between">
            <div>
                <p className="text-xs font-semibold uppercase tracking-wide text-cyan-700">{eyebrow}</p>
                <h1 className="mt-2 text-2xl font-bold tracking-tight text-slate-950">{title}</h1>
                <p className="mt-2 max-w-3xl text-sm leading-6 text-slate-600">{description}</p>
            </div>
            {actionHref ? (
                <Button asChild className="shrink-0">
                    <Link href={actionHref}>{actionLabel}<ArrowRight className="size-4" /></Link>
                </Button>
            ) : null}
        </div>
    );
}

function AppShell({ mode, view, cartCount, wishlistCount, categories = [], notice, children }) {
    const [open, setOpen] = useState(false);
    const [categoryMenuOpen, setCategoryMenuOpen] = useState(false);
    const [activeMegaCategoryId, setActiveMegaCategoryId] = useState(null);
    const [activeMegaLeft, setActiveMegaLeft] = useState(0);
    const [searchTerm, setSearchTerm] = useState(() => {
        if (typeof window === 'undefined') return '';
        return new URLSearchParams(window.location.search).get('q') || '';
    });
    useEffect(() => {
        const closeOnEscape = (event) => {
            if (event.key === 'Escape') {
                setCategoryMenuOpen(false);
                setActiveMegaCategoryId(null);
                setOpen(false);
            }
        };
        window.addEventListener('keydown', closeOnEscape);
        return () => window.removeEventListener('keydown', closeOnEscape);
    }, []);
    const submitSearch = (event) => {
        event.preventDefault();
        const query = searchTerm.trim();
        window.location.href = query ? `/marketplace?q=${encodeURIComponent(query)}` : '/marketplace';
    };
    const buyerLinks = [
        ['/', 'Home', Home],
        ['/marketplace', 'Marketplace', PackageSearch],
        ['/cart', `Cart ${cartCount ? `(${cartCount})` : ''}`, ShoppingCart],
        ['/orders', 'Orders', Truck],
        ['/wishlist', `Wishlist ${wishlistCount ? `(${wishlistCount})` : ''}`, Heart],
        ['/support', 'Support', MessageSquareText],
        ['/profile', 'Profile', User],
    ];
    const sellerLinks = [
        ['/seller/dashboard', 'Dashboard', LayoutDashboard],
        ['/seller/products', 'Products', Package],
        ['/seller/inventory', 'Inventory', Boxes],
        ['/seller/orders', 'Orders', Truck],
        ['/seller/payouts', 'Payouts', WalletCards],
        ['/seller/delivery', 'Delivery', PackageCheck],
        ['/seller/offers', 'Offers', Tag],
        ['/seller/business', 'Business', BriefcaseBusiness],
        ['/seller/support', 'Support', MessageSquareText],
    ];
    const links = mode === 'seller' ? sellerLinks : buyerLinks;
    const rootNavCategories = categories.filter(isRootCategory);
    const navCategories = rootNavCategories.length ? rootNavCategories : categories;
    const categoryLinks = [
        { label: 'All Categories', href: '/marketplace' },
        ...navCategories.map((category) => ({ label: category.name, href: `/marketplace?category=${encodeURIComponent(category.name)}` })),
    ];
    const categoryChildren = (category) => categories.filter((item) => Number(item.parent_id) === Number(category.id));
    const categoryProductCount = (category) => Number(category.products_count ?? category.product_count ?? 0);
    const categoryCountText = (category) => {
        const count = categoryProductCount(category);
        return count > 0 ? ` (${count})` : '';
    };
    const activeMegaCategory = navCategories.find((category) => Number(category.id) === Number(activeMegaCategoryId));
    const activeMegaChildren = activeMegaCategory ? categoryChildren(activeMegaCategory) : [];
    const openCategoryMenu = () => {
        setCategoryMenuOpen((value) => !value);
        setActiveMegaCategoryId(null);
    };
    const openMegaCategory = (category, event = null) => {
        setCategoryMenuOpen(false);
        setActiveMegaCategoryId(category.id);
        if (event?.currentTarget && typeof window !== 'undefined') {
            const rect = event.currentTarget.getBoundingClientRect();
            setActiveMegaLeft(Math.min(Math.max(16, rect.left), Math.max(16, window.innerWidth - 360)));
        }
    };
    const closeMenus = () => {
        setCategoryMenuOpen(false);
        setActiveMegaCategoryId(null);
    };

    return (
        <div className="min-h-screen bg-[#f6f8fb] text-slate-950">
            <Head title="Marketplace" />
            <header className="sticky top-0 z-40 border-b border-slate-200 bg-white shadow-sm">
                <div className="bg-slate-950 text-slate-200">
                    <div className="mx-auto flex h-9 max-w-[1480px] items-center justify-between px-4 text-xs sm:h-10 sm:px-6 sm:text-sm lg:px-8">
                        <div className="flex items-center gap-6">
                            <Link href="/seller/dashboard" className="font-medium hover:text-white">Sell on Sellova</Link>
                            <Link href="/orders" className="font-medium hover:text-white">Track Escrow</Link>
                        </div>
                        <div className="hidden items-center gap-5 sm:flex">
                            <span className="inline-flex items-center gap-2 font-semibold text-emerald-300"><ShieldCheck className="size-4" />100% Escrow Protection</span>
                            <span className="h-4 w-px bg-slate-700" />
                            <span>BDT (৳)</span>
                        </div>
                    </div>
                </div>
                <div className="mx-auto flex min-h-[72px] max-w-[1480px] items-center justify-between gap-4 px-4 sm:px-6 lg:min-h-[96px] lg:gap-5 lg:px-8">
                    <Link href="/" className="flex shrink-0 items-center gap-3">
                        <div className="flex size-10 items-center justify-center rounded-xl bg-indigo-600 text-white shadow-[0_18px_38px_-22px_rgba(79,70,229,0.9)] lg:size-12">
                            <ShoppingBag className="size-6 lg:size-7" />
                        </div>
                        <p className="text-xl font-extrabold tracking-tight text-slate-950 lg:text-2xl">Sellova</p>
                    </Link>

                    <form onSubmit={submitSearch} className="hidden h-14 min-w-0 flex-1 overflow-hidden rounded-lg border border-slate-200 bg-slate-100 shadow-inner lg:flex">
                        <Link href="/marketplace" className="flex items-center gap-2 border-r border-slate-200 bg-slate-200/70 px-5 text-sm font-semibold text-slate-700">
                            All <ChevronRight className="size-4 rotate-90" />
                        </Link>
                        <div className="relative min-w-0 flex-1">
                            <Search className="absolute left-5 top-1/2 size-5 -translate-y-1/2 text-slate-400" />
                            <input value={searchTerm} onChange={(event) => setSearchTerm(event.target.value)} className="h-full w-full border-0 bg-transparent pl-14 pr-4 text-sm font-medium text-slate-700 placeholder:text-slate-400 focus:ring-0" placeholder="Search products, sellers, brands, tags..." aria-label="Search marketplace" />
                        </div>
                        <button type="submit" className="flex w-20 items-center justify-center bg-indigo-600 text-white transition hover:bg-indigo-700" aria-label="Submit search">
                            <Search className="size-6" />
                        </button>
                    </form>

                    <div className="hidden items-center gap-8 xl:flex">
                        <Link href="/profile" className="group">
                            <p className="text-xs font-semibold text-slate-500">Hello, Sign in</p>
                            <p className="flex items-center gap-1 text-sm font-bold text-slate-950 group-hover:text-indigo-600">Account & Vault <ChevronRight className="size-4 rotate-90" /></p>
                        </Link>
                        <Link href="/orders" className="group">
                            <p className="text-xs font-semibold text-slate-500">Returns &</p>
                            <p className="text-sm font-bold text-slate-950 group-hover:text-indigo-600">Escrows</p>
                        </Link>
                        <Link href="/cart" className="relative flex items-center gap-2 text-sm font-bold text-slate-950 hover:text-indigo-600">
                            <span className="relative"><ShoppingCart className="size-8" />{cartCount ? <span className="absolute -right-2 -top-2 flex size-5 items-center justify-center rounded-full bg-indigo-600 text-xs text-white">{cartCount}</span> : null}</span>
                            Cart
                        </Link>
                    </div>

                    <div className="flex items-center gap-2 xl:hidden">
                        <Button asChild variant={mode === 'seller' ? 'outline' : 'default'} size="sm">
                            <Link href={mode === 'seller' ? '/marketplace' : '/seller/dashboard'}>{mode === 'seller' ? 'Buyer site' : 'Seller panel'}</Link>
                        </Button>
                        <Button variant="outline" size="icon" onClick={() => setOpen(true)} aria-label="Open menu">
                            <Menu className="size-4" />
                        </Button>
                    </div>
                </div>
                <div className="border-t border-slate-100 px-4 pb-3 lg:hidden">
                    <div className="relative">
                        <form onSubmit={submitSearch}>
                            <Search className="absolute left-3 top-1/2 size-4 -translate-y-1/2 text-slate-400" />
                            <input value={searchTerm} onChange={(event) => setSearchTerm(event.target.value)} className="h-11 w-full rounded-lg border border-slate-200 bg-slate-100 pl-10 pr-4 text-sm font-medium text-slate-700 placeholder:text-slate-500" placeholder="Search products, services, deals..." aria-label="Search marketplace" />
                        </form>
                    </div>
                    <div className="-mx-4 mt-3 flex gap-2 overflow-x-auto px-4 pb-1">
                        {categoryLinks.slice(0, 6).map((item, index) => (
                            <Link key={item.label} href={item.href} className={cn('shrink-0 rounded-full border px-4 py-2 text-xs font-bold', index === 0 ? 'border-slate-950 bg-slate-950 text-white' : 'border-slate-200 bg-white text-slate-700')}>
                                {item.label.replace('All Categories', 'All')}
                            </Link>
                        ))}
                    </div>
                </div>
                <div className="hidden bg-slate-950 text-white lg:block">
                    <div className="mx-auto flex h-11 max-w-[1480px] items-center gap-7 overflow-x-auto px-4 text-sm font-bold [scrollbar-width:none] sm:px-6 lg:px-8 [&::-webkit-scrollbar]:hidden">
                        {categoryLinks.map((item, index) => (
                            index === 0 ? (
                                <div key={item.label} className="flex h-full shrink-0 items-center">
                                    <button
                                        type="button"
                                        onClick={openCategoryMenu}
                                        className="flex items-center gap-2 whitespace-nowrap text-slate-100 transition hover:text-emerald-300"
                                        aria-expanded={categoryMenuOpen}
                                        aria-controls="desktop-category-menu"
                                    >
                                        <Menu className="size-5" />{item.label}
                                    </button>
                                </div>
                            ) : (
                                <div
                                    key={item.label}
                                    className="flex h-full shrink-0 items-center"
                                    onMouseEnter={(event) => openMegaCategory(navCategories[index - 1], event)}
                                >
                                    <button
                                        type="button"
                                        onClick={(event) => openMegaCategory(navCategories[index - 1], event)}
                                        className={cn(
                                            'flex shrink-0 items-center gap-2 whitespace-nowrap text-slate-100 transition hover:text-emerald-300',
                                            Number(activeMegaCategoryId) === Number(navCategories[index - 1]?.id) && 'text-emerald-300',
                                        )}
                                        aria-expanded={Number(activeMegaCategoryId) === Number(navCategories[index - 1]?.id)}
                                        aria-controls="category-subcategory-menu"
                                    >
                                        {item.label}
                                    </button>
                                </div>
                            )
                        ))}
                    </div>
                </div>
                {categoryMenuOpen ? (
                    <div id="desktop-category-menu" className="absolute left-0 right-0 top-full z-50 hidden border-t border-slate-200 bg-white text-slate-950 shadow-xl lg:block">
                        <div className="mx-auto max-w-[1480px] px-4 py-5 sm:px-6 lg:px-8">
                            <div className="mb-4 flex items-center justify-between gap-4 border-b border-slate-100 pb-4">
                                <div>
                                    <p className="text-xs font-extrabold uppercase tracking-wide text-indigo-600">All Categories</p>
                                    <h2 className="mt-1 text-xl font-extrabold text-slate-950">Shop by department</h2>
                                </div>
                                <Button asChild variant="outline" size="sm" onClick={() => setCategoryMenuOpen(false)}>
                                    <Link href="/marketplace">View all products</Link>
                                </Button>
                            </div>
                            <div className="grid max-h-[520px] gap-x-8 gap-y-6 overflow-y-auto pr-2 md:grid-cols-2 xl:grid-cols-4">
                                {navCategories.map((category) => {
                                    const children = categoryChildren(category);
                                    return (
                                        <div key={category.id || category.name}>
                                            <Link onClick={() => setCategoryMenuOpen(false)} href={`/marketplace?category=${encodeURIComponent(category.name)}`} className="flex items-center justify-between gap-3 text-base font-extrabold text-slate-950 hover:text-indigo-600">
                                                <span>{category.name}{categoryCountText(category)}</span>
                                            </Link>
                                            {children.length ? (
                                                <div className="mt-3 grid gap-2">
                                                    {children.map((child) => (
                                                        <Link
                                                            key={child.id || child.name}
                                                            onClick={() => setCategoryMenuOpen(false)}
                                                            href={`/marketplace?category=${encodeURIComponent(category.name)}&subcategory=${encodeURIComponent(child.name)}`}
                                                            className="text-sm font-semibold text-slate-500 hover:text-indigo-700"
                                                        >
                                                            {child.name}{categoryCountText(child)}
                                                        </Link>
                                                    ))}
                                                </div>
                                            ) : (
                                                <p className="mt-3 text-sm font-medium text-slate-400">No subcategories</p>
                                            )}
                                        </div>
                                    );
                                })}
                            </div>
                        </div>
                    </div>
                ) : null}
                {activeMegaCategory ? (
                    <div
                        id="category-subcategory-menu"
                        className="absolute top-full z-50 hidden w-[340px] rounded-b-lg border border-slate-200 bg-white text-slate-950 shadow-xl lg:block"
                        style={{ left: activeMegaLeft }}
                        onMouseLeave={() => setActiveMegaCategoryId(null)}
                    >
                        <div className="p-4">
                            <div className="mb-3 flex items-center justify-between gap-3 border-b border-slate-100 pb-3">
                                <Link href={`/marketplace?category=${encodeURIComponent(activeMegaCategory.name)}`} onClick={closeMenus} className="font-extrabold text-slate-950 hover:text-indigo-700">
                                    {activeMegaCategory.name}{categoryCountText(activeMegaCategory)}
                                </Link>
                                <ChevronRight className="size-4 text-slate-400" />
                            </div>
                            <div>
                                {activeMegaChildren.length ? (
                                    <div className="grid gap-1">
                                        {activeMegaChildren.map((child) => (
                                            <Link
                                                key={child.id || child.name}
                                                href={`/marketplace?category=${encodeURIComponent(activeMegaCategory.name)}&subcategory=${encodeURIComponent(child.name)}`}
                                                onClick={closeMenus}
                                                className="rounded-md px-2 py-2 text-sm font-semibold text-slate-600 hover:bg-indigo-50 hover:text-indigo-700"
                                            >
                                                {child.name}{categoryCountText(child)}
                                            </Link>
                                        ))}
                                    </div>
                                ) : (
                                    <div className="rounded-lg border border-dashed border-slate-300 bg-slate-50 p-4 text-center">
                                        <div>
                                            <p className="font-extrabold text-slate-950">No subcategories yet</p>
                                            <p className="mt-2 text-sm text-slate-500">Products in this category are still available from the parent category page.</p>
                                            <Button asChild className="mt-4" size="sm" onClick={closeMenus}>
                                                <Link href={`/marketplace?category=${encodeURIComponent(activeMegaCategory.name)}`}>Open {activeMegaCategory.name}</Link>
                                            </Button>
                                        </div>
                                    </div>
                                )}
                            </div>
                        </div>
                    </div>
                ) : null}
            </header>
            {categoryMenuOpen || activeMegaCategory ? <button type="button" className="fixed inset-0 z-30 hidden bg-slate-950/10 lg:block" aria-label="Close category menu" onClick={closeMenus} /> : null}
            {open ? (
                <div className="fixed inset-0 z-50 bg-slate-950/40 lg:hidden">
                    <div className="ml-auto h-full w-[min(88vw,360px)] bg-white p-4 shadow-xl">
                        <div className="mb-4 flex items-center justify-between">
                            <p className="font-semibold">Navigation</p>
                            <Button variant="outline" size="icon" onClick={() => setOpen(false)} aria-label="Close menu"><X className="size-4" /></Button>
                        </div>
                        <div className="grid gap-2">
                            {links.map(([href, label, Icon]) => (
                                <Button key={href} asChild variant="ghost" className="justify-start" onClick={() => setOpen(false)}>
                                    <Link href={href}><Icon className="size-4" />{label}</Link>
                                </Button>
                            ))}
                        </div>
                        {mode !== 'seller' ? (
                            <div className="mt-6 border-t border-slate-200 pt-4">
                                <p className="mb-3 text-xs font-extrabold uppercase tracking-wide text-slate-500">Shop categories</p>
                                <div className="grid gap-3">
                                    {navCategories.map((category) => {
                                        const children = categoryChildren(category);
                                        return (
                                            <details key={category.id || category.name} className="rounded-lg border border-slate-200 bg-slate-50 p-3">
                                                <summary className="cursor-pointer text-sm font-extrabold text-slate-950">{category.name}</summary>
                                                <div className="mt-3 grid gap-2">
                                                    <Link href={`/marketplace?category=${encodeURIComponent(category.name)}`} onClick={() => setOpen(false)} className="text-sm font-semibold text-indigo-700">View all {category.name}</Link>
                                                    {children.map((child) => (
                                                        <Link key={child.id || child.name} href={`/marketplace?category=${encodeURIComponent(category.name)}&subcategory=${encodeURIComponent(child.name)}`} onClick={() => setOpen(false)} className="text-sm font-medium text-slate-600">
                                                            {child.name}{categoryCountText(child)}
                                                        </Link>
                                                    ))}
                                                </div>
                                            </details>
                                        );
                                    })}
                                </div>
                            </div>
                        ) : null}
                    </div>
                </div>
            ) : null}
            <main className="mx-auto max-w-[1480px] px-4 pb-28 pt-5 sm:px-6 sm:pt-8 lg:px-8 lg:pb-8">
                {notice ? (
                    <div className={cn('fixed right-4 top-24 z-50 flex max-w-sm items-start gap-3 rounded-lg border p-4 text-sm font-semibold shadow-xl', notice.type === 'error' ? 'border-rose-200 bg-rose-50 text-rose-800' : 'border-emerald-200 bg-emerald-50 text-emerald-800')}>
                        {notice.type === 'error' ? <AlertCircle className="mt-0.5 size-4 shrink-0" /> : <Check className="mt-0.5 size-4 shrink-0" />}
                        <span>{notice.body}</span>
                    </div>
                ) : null}
                <div className="relative">
                    {children}
                </div>
            </main>
            <nav className="fixed inset-x-0 bottom-0 z-40 border-t border-slate-200 bg-white/95 shadow-[0_-18px_50px_-36px_rgba(15,23,42,0.75)] backdrop-blur lg:hidden">
                <div className="mx-auto grid max-w-md grid-cols-5 px-2 py-2">
                    {(mode === 'seller'
                        ? [
                            ['/seller/dashboard', 'Home', LayoutDashboard],
                            ['/seller/products', 'Products', Package],
                            ['/seller/orders', 'Orders', Truck],
                            ['/seller/payouts', 'Payouts', WalletCards],
                            ['/seller/business', 'Store', BriefcaseBusiness],
                        ]
                        : [
                            ['/', 'Home', Home],
                            ['/marketplace', 'Shop', PackageSearch],
                            ['/cart', 'Cart', ShoppingCart],
                            ['/orders', 'Orders', Truck],
                            ['/profile', 'Profile', User],
                        ]).map(([href, label, Icon]) => (
                        <Link
                            key={href}
                            href={href}
                            className={cn(
                                'flex flex-col items-center gap-1 rounded-xl px-2 py-2 text-[11px] font-bold transition',
                                viewMatches(href, view, mode) ? 'bg-slate-950 text-white' : 'text-slate-500 hover:bg-slate-100 hover:text-slate-950',
                            )}
                        >
                            <span className="relative">
                                <Icon className="size-5" />
                                {href === '/cart' && cartCount ? <span className="absolute -right-2 -top-2 flex size-4 items-center justify-center rounded-full bg-indigo-600 text-[10px] text-white">{cartCount}</span> : null}
                            </span>
                            {label}
                        </Link>
                    ))}
                </div>
            </nav>
        </div>
    );
}

function viewMatches(href, view, mode) {
    if (href === '/' && view === 'home') return true;
    if (href === '/seller/dashboard' && mode === 'seller' && view === 'seller-dashboard') return true;
    return href.replace('/', '').replace('/', '-') === view;
}

function Hero({ state, addToCart }) {
    const featured = state.products[0];
    const hero = state.hero || {};
    const panels = Array.isArray(hero.panels) ? hero.panels : [];
    return (
        <section className="grid gap-4 lg:grid-cols-[2fr_0.98fr]">
            <div className="relative min-h-[340px] overflow-hidden rounded-xl bg-slate-950 p-6 text-white shadow-[0_22px_70px_-42px_rgba(15,23,42,0.85)] sm:min-h-[380px] sm:p-12">
                <ProductMedia src={hero.image || featured?.image} alt={hero.title || featured?.title || 'Featured marketplace product'} className="absolute inset-0 h-full w-full object-cover opacity-55" />
                <div className="absolute inset-0 bg-[linear-gradient(90deg,rgba(15,23,42,0.96),rgba(76,29,149,0.62),rgba(88,28,135,0.72))]" />
                <div className="relative flex h-full max-w-2xl flex-col justify-center">
                    <Badge className="w-fit gap-1 border-0 bg-rose-500 px-4 py-1.5 text-white"><Flame className="size-4" /> {hero.eyebrow || 'Live marketplace'}</Badge>
                    <h1 className="mt-6 text-3xl font-extrabold leading-tight tracking-tight sm:mt-7 sm:text-5xl">{hero.title || 'Discover verified marketplace deals'}</h1>
                    <p className="mt-4 max-w-xl text-sm font-medium leading-7 text-slate-200 sm:mt-6 sm:text-lg sm:leading-8">{hero.description || 'Browse active products, classified ads, seller offers, escrow orders, and delivery tracking from one responsive web workspace.'}</p>
                    <div className="mt-6 flex flex-wrap gap-3 sm:mt-8">
                        <Button asChild size="lg" variant="secondary" className="h-11 rounded-full px-6 text-sm sm:h-12 sm:px-8"><Link href="/marketplace">Shop Now <ArrowRight className="size-5" /></Link></Button>
                        <Button asChild size="lg" className="h-11 rounded-full bg-white/10 px-6 text-sm text-white hover:bg-white/20 sm:h-12 sm:px-8"><Link href="/seller/dashboard">Sell Now</Link></Button>
                    </div>
                </div>
            </div>
            <div className="grid gap-4">
                {(panels.length ? panels : state.products.slice(1, 3).map((product) => ({ eyebrow: product.productTypeLabel || product.type, title: product.title, cta: product.category, image: product.image }))).map(({ eyebrow, title, cta, image }) => (
                    <Link key={title} href="/marketplace" className="group relative min-h-[150px] overflow-hidden rounded-xl bg-slate-950 p-5 text-white shadow-[0_18px_60px_-40px_rgba(15,23,42,0.75)] sm:min-h-[180px] sm:p-7">
                        <ProductMedia src={image} alt={title} className="absolute inset-0 h-full w-full object-cover opacity-45 transition duration-500 group-hover:scale-105" />
                        <div className="absolute inset-0 bg-gradient-to-t from-slate-950 via-slate-950/70 to-slate-900/30" />
                        <div className="relative flex h-full flex-col justify-end">
                            <p className="text-sm font-extrabold uppercase tracking-wide text-amber-300">{eyebrow}</p>
                            <h2 className="mt-2 text-xl font-extrabold sm:text-2xl">{title}</h2>
                            <p className="mt-2 inline-flex items-center gap-1 font-bold">{cta} <ChevronRight className="size-4" /></p>
                        </div>
                    </Link>
                ))}
            </div>
        </section>
    );
}

function productFulfillmentMeta(product) {
    const type = String(product?.productType || '').toLowerCase();
    if (type === 'physical') {
        return null;
    }
    if (type === 'digital' || type === 'instant_delivery') {
        return { label: 'Instant', icon: Zap, variant: 'success', className: 'text-emerald-700' };
    }
    if (type === 'service') {
        return { label: 'Service', icon: BriefcaseBusiness, variant: 'secondary', className: 'text-indigo-700' };
    }
    if (product?.type === 'Classified') {
        return { label: 'Escrow', icon: LockKeyhole, variant: 'outline', className: 'text-indigo-600' };
    }

    return { label: product?.productTypeLabel || 'Marketplace', icon: ShieldCheck, variant: 'secondary', className: 'text-slate-700' };
}

function FulfillmentBadge({ product, compact = false }) {
    const meta = productFulfillmentMeta(product);
    if (!meta) return null;
    const Icon = meta.icon;
    return (
        <Badge variant={meta.variant} className={cn('gap-1 bg-white/95 font-extrabold uppercase tracking-wide shadow-sm', meta.className, compact ? 'px-2 py-1 text-[11px]' : 'px-3 py-1')}>
            <Icon className="size-3" />
            {meta.label}
        </Badge>
    );
}

function ProductCard({ product, addToCart, toggleWishlist, wished }) {
    const gallery = (product.images?.length ? product.images : [product.image]).filter(Boolean);
    const [imageIndex, setImageIndex] = useState(0);
    const activeImage = gallery[imageIndex] || product.image;
    useEffect(() => setImageIndex(0), [product.id]);
    const discount = product.oldPrice > product.price ? Math.max(1, Math.round(((product.oldPrice - product.price) / product.oldPrice) * 100)) : 0;
    const availableStock = asNumber(product.availableStock ?? product.stock);
    const categoryTrail = [product.category, product.subcategory].filter(Boolean).join(' / ');
    return (
        <div className="group overflow-hidden rounded-lg border border-slate-200 bg-white shadow-sm transition duration-300 hover:-translate-y-1 hover:border-rose-300 hover:shadow-[0_22px_60px_-42px_rgba(15,23,42,0.75)]">
            <div className="relative bg-slate-50 p-5">
                {discount ? <div className="absolute right-5 top-5 z-10 rounded-md bg-rose-500 px-3 py-2 text-sm font-extrabold text-white" title={product.discountLabel || ''}>-{discount}%</div> : null}
                <Link href={`/products/${product.id}`} className="block overflow-hidden rounded-md">
                    <ProductMedia src={activeImage} alt={product.title} className="aspect-[16/10] w-full rounded-md object-cover transition duration-500 group-hover:scale-[1.025]" />
                </Link>
                {gallery.length > 1 ? (
                    <div className="mt-3 flex justify-center gap-1.5" aria-label="Product image gallery">
                        {gallery.slice(0, 5).map((image, index) => (
                            <button
                                key={`${product.id}-${image}-${index}`}
                                type="button"
                                onClick={() => setImageIndex(index)}
                                className={cn('size-2 rounded-full transition', imageIndex === index ? 'bg-emerald-600' : 'bg-slate-300 hover:bg-slate-400')}
                                aria-label={`Show product image ${index + 1}`}
                            />
                        ))}
                    </div>
                ) : null}
            </div>
            <div className="border-t border-slate-100 p-5">
                <p className="flex items-center gap-1 text-sm font-bold text-slate-700"><Star className="size-4 fill-amber-400 text-amber-400" />{product.rating}</p>
                <Link href={`/products/${product.id}`} className="mt-3 line-clamp-2 block min-h-12 text-base font-bold leading-6 text-slate-950 hover:text-indigo-600">{product.title}</Link>
                <p className="mt-2 text-sm font-semibold text-slate-500">{product.seller}</p>
                {categoryTrail ? (
                    <p className="mt-2 line-clamp-1 text-xs font-bold uppercase tracking-wide text-indigo-600">{categoryTrail}</p>
                ) : null}
                <p className={cn('mt-2 text-xs font-extrabold uppercase tracking-wide', availableStock > 0 ? 'text-emerald-600' : 'text-rose-600')}>
                    {availableStock > 0 ? `${availableStock} available` : 'Out of stock'}
                </p>
                {discount && product.discountLabel ? <p className="mt-2 line-clamp-1 text-xs font-extrabold uppercase tracking-wide text-rose-600">{product.discountLabel}</p> : null}
                <div className="mt-5 flex items-end justify-between gap-3">
                    <div>
                        <p className="text-xl font-extrabold tabular-nums text-rose-600">{money(product.price)}</p>
                        {product.oldPrice > product.price ? <p className="text-sm font-bold text-slate-400 line-through">{money(product.oldPrice)}</p> : null}
                    </div>
                    {product.verified ? <Badge variant="success" className="gap-1"><ShieldCheck className="size-3" />Safe</Badge> : null}
                </div>
                <div className="mt-4 flex gap-2">
                    <Button disabled={availableStock <= 0} className="flex-1 bg-slate-100 text-slate-950 shadow-none hover:bg-slate-950 hover:text-white disabled:cursor-not-allowed disabled:opacity-60" onClick={() => addToCart(product)}>Add to Cart</Button>
                    <Button variant={wished ? 'default' : 'outline'} size="icon" onClick={() => toggleWishlist(product.id)} aria-label="Toggle wishlist">
                        <Heart className={cn('size-4', wished && 'fill-current')} />
                    </Button>
                </div>
            </div>
        </div>
    );
}

function Marketplace({ state, addToCart, toggleWishlist }) {
    const [query, setQuery] = useState(() => typeof window === 'undefined' ? '' : (new URLSearchParams(window.location.search).get('q') || ''));
    const [category, setCategory] = useState(() => typeof window === 'undefined' ? 'All' : (new URLSearchParams(window.location.search).get('category') || 'All'));
    const [subcategory, setSubcategory] = useState(() => typeof window === 'undefined' ? 'All' : (new URLSearchParams(window.location.search).get('subcategory') || 'All'));
    const [type, setType] = useState('All');
    const [brand, setBrand] = useState('All');
    const [availability, setAvailability] = useState('All');
    const [sort, setSort] = useState('Newest');
    const [minPrice, setMinPrice] = useState('');
    const [maxPrice, setMaxPrice] = useState('');
    const [mobileFiltersOpen, setMobileFiltersOpen] = useState(false);
    const [visibleCount, setVisibleCount] = useState(PAGE_SIZE);
    const rootCategoryRows = (state.categories || []).filter(isRootCategory);
    const rootCategories = rootCategoryRows.length ? rootCategoryRows : (state.categories || []);
    const categoryOptions = ['All', ...rootCategories.map((item) => item.name)].filter(Boolean);
    const selectedCategoryId = rootCategories.find((item) => item.name === category)?.id;
    const subcategoryOptions = ['All', ...(state.categories || [])
        .filter((item) => category === 'All' ? isChildCategory(item) : Number(item.parent_id) === Number(selectedCategoryId))
        .map((item) => item.name)]
        .filter(Boolean);
    const typeOptions = ['All', ...Array.from(new Set(state.products.map((product) => product.productTypeLabel || product.type).filter(Boolean)))];
    const brandOptions = ['All', ...Array.from(new Set(state.products.map((product) => product.brand).filter(Boolean)))];
    const maxCatalogPrice = Math.max(0, ...state.products.map((product) => asNumber(product.price)));
    const products = useMemo(() => {
        const filtered = state.products.filter((product) => {
        const matchesQuery = `${product.title} ${product.seller} ${product.city} ${product.brand || ''} ${product.category || ''} ${product.subcategory || ''} ${(product.tags || []).join(' ')}`.toLowerCase().includes(query.toLowerCase());
        const matchesCategory = category === 'All' || product.category === category;
        const matchesSubcategory = subcategory === 'All' || product.subcategory === subcategory;
        const matchesType = type === 'All' || product.type === type || product.productTypeLabel === type;
        const matchesBrand = brand === 'All' || product.brand === brand;
        const availableStock = asNumber(product.availableStock ?? product.stock);
        const matchesAvailability = availability === 'All' || (availability === 'In stock' ? availableStock > 0 : availableStock <= 0);
        const price = asNumber(product.price);
        const matchesMin = minPrice === '' || price >= asNumber(minPrice);
        const matchesMax = maxPrice === '' || price <= asNumber(maxPrice);
        return matchesQuery && matchesCategory && matchesSubcategory && matchesType && matchesBrand && matchesAvailability && matchesMin && matchesMax;
        });
        return filtered.sort((a, b) => {
            if (sort === 'Price low') return asNumber(a.price) - asNumber(b.price);
            if (sort === 'Price high') return asNumber(b.price) - asNumber(a.price);
            if (sort === 'Rating') return asNumber(b.rating) - asNumber(a.rating);
            if (sort === 'Best selling') return asNumber(b.salesCount) - asNumber(a.salesCount);
            return String(b.publishedAt || b.id).localeCompare(String(a.publishedAt || a.id));
        });
    }, [state.products, query, category, subcategory, type, brand, availability, minPrice, maxPrice, sort]);
    const visibleProducts = products.slice(0, visibleCount);
    const filterControls = (
        <div className="grid gap-3">
            <div className="relative">
                <Search className="absolute left-3 top-1/2 size-4 -translate-y-1/2 text-slate-400" />
                <Input value={query} onChange={(e) => { setQuery(e.target.value); setVisibleCount(PAGE_SIZE); }} placeholder="Search products, sellers, brands, city..." className="h-11 border-slate-200 bg-slate-50 pl-9 focus:bg-white" />
            </div>
            <div className="grid gap-3 sm:grid-cols-2 xl:grid-cols-5">
                <select value={category} onChange={(e) => { setCategory(e.target.value); setSubcategory('All'); setVisibleCount(PAGE_SIZE); }} className="h-11 rounded-md border-slate-200 bg-slate-50 text-sm">
                    {(categoryOptions.length > 1 ? categoryOptions : fallbackCategoryNames).map((item) => <option key={item}>{item}</option>)}
                </select>
                <select value={subcategory} onChange={(e) => { setSubcategory(e.target.value); setVisibleCount(PAGE_SIZE); }} className="h-11 rounded-md border-slate-200 bg-slate-50 text-sm" disabled={subcategoryOptions.length <= 1}>
                    {subcategoryOptions.map((item) => <option key={item}>{item}</option>)}
                </select>
                <select value={type} onChange={(e) => { setType(e.target.value); setVisibleCount(PAGE_SIZE); }} className="h-11 rounded-md border-slate-200 bg-slate-50 text-sm">
                    {typeOptions.map((item) => <option key={item}>{item}</option>)}
                </select>
                <select value={brand} onChange={(e) => { setBrand(e.target.value); setVisibleCount(PAGE_SIZE); }} className="h-11 rounded-md border-slate-200 bg-slate-50 text-sm">
                    {brandOptions.map((item) => <option key={item}>{item}</option>)}
                </select>
                <select value={availability} onChange={(e) => { setAvailability(e.target.value); setVisibleCount(PAGE_SIZE); }} className="h-11 rounded-md border-slate-200 bg-slate-50 text-sm">
                    {['All', 'In stock', 'Out of stock'].map((item) => <option key={item}>{item}</option>)}
                </select>
            </div>
            <div className="grid gap-3 sm:grid-cols-3">
                <Input value={minPrice} onChange={(e) => { setMinPrice(e.target.value); setVisibleCount(PAGE_SIZE); }} inputMode="numeric" placeholder="Min price" className="h-11 bg-slate-50" />
                <Input value={maxPrice} onChange={(e) => { setMaxPrice(e.target.value); setVisibleCount(PAGE_SIZE); }} inputMode="numeric" placeholder={maxCatalogPrice ? `Max ${money(maxCatalogPrice)}` : 'Max price'} className="h-11 bg-slate-50" />
                <select value={sort} onChange={(e) => setSort(e.target.value)} className="h-11 rounded-md border-slate-200 bg-slate-50 text-sm">
                    {['Newest', 'Best selling', 'Rating', 'Price low', 'Price high'].map((item) => <option key={item}>{item}</option>)}
                </select>
            </div>
        </div>
    );

    return (
        <div className="space-y-6">
            <PageIntro
                eyebrow="Buyer marketplace"
                title="Discover verified products, services, and classified deals"
                description="Search by seller, city, category, or listing type. The layout is tuned for serious buying: quick trust signals, stock visibility, price comparison, and fast cart actions."
                actionHref="/cart"
                actionLabel="Review cart"
            />
            <div className="rounded-xl border border-slate-200/80 bg-white/95 p-4 shadow-[0_18px_50px_-36px_rgba(15,23,42,0.45)] ring-1 ring-white">
                <div className="mb-3 flex items-center justify-between gap-3 lg:hidden">
                    <p className="text-sm font-extrabold text-slate-700">{products.length} results</p>
                    <Button variant="outline" size="sm" onClick={() => setMobileFiltersOpen(true)}><SlidersHorizontal className="size-4" />Filters</Button>
                </div>
                <div className="hidden lg:block">{filterControls}</div>
            </div>
            {mobileFiltersOpen ? (
                <div className="fixed inset-0 z-50 bg-slate-950/40 lg:hidden">
                    <div className="ml-auto h-full w-[min(92vw,390px)] overflow-y-auto bg-white p-4 shadow-xl">
                        <div className="mb-4 flex items-center justify-between">
                            <p className="font-extrabold">Filters</p>
                            <Button variant="outline" size="icon" onClick={() => setMobileFiltersOpen(false)} aria-label="Close filters"><X className="size-4" /></Button>
                        </div>
                        {filterControls}
                        <Button className="mt-4 w-full" onClick={() => setMobileFiltersOpen(false)}>Show {products.length} results</Button>
                    </div>
                </div>
            ) : null}
            {products.length ? <div className="grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
                {visibleProducts.map((product) => (
                    <ProductCard key={product.id} product={product} addToCart={addToCart} toggleWishlist={toggleWishlist} wished={state.wishlist.includes(product.id)} />
                ))}
            </div> : <Empty title="No live listings match this view" action="/seller/products" label="Create first listing" />}
            {visibleCount < products.length ? (
                <div className="flex justify-center">
                    <Button variant="outline" onClick={() => setVisibleCount((count) => count + PAGE_SIZE)}>Load more products</Button>
                </div>
            ) : null}
        </div>
    );
}

function SectionTitle({ icon: Icon, title, accent = 'text-indigo-600', action }) {
    return (
        <div className="mb-5 flex items-center justify-between border-b border-slate-200 pb-4">
            <div className="flex items-center gap-3">
                {Icon ? <Icon className={cn('size-7', accent)} /> : null}
                <h2 className="text-2xl font-extrabold tracking-tight text-slate-950">{title}</h2>
            </div>
            {action ? <Link href="/marketplace" className="font-extrabold text-indigo-600 hover:text-indigo-700">{action}</Link> : null}
        </div>
    );
}

function FlashDeals({ state, addToCart, toggleWishlist }) {
    return (
        <section className="mt-10">
            <SectionTitle icon={Flame} title="Flash Deals" accent="text-rose-500" action="View All Deals" />
            <div className="grid gap-5 sm:grid-cols-2 xl:grid-cols-4">
                {state.products.slice(0, 4).map((product) => (
                    <ProductCard key={product.id} product={product} addToCart={addToCart} toggleWishlist={toggleWishlist} wished={state.wishlist.includes(product.id)} />
                ))}
            </div>
        </section>
    );
}

function FeaturedVendor({ vendor }) {
    if (!vendor) return null;

    return (
        <section className="relative mt-12 overflow-hidden rounded-xl bg-indigo-950 p-8 text-white shadow-[0_24px_70px_-42px_rgba(15,23,42,0.9)] sm:p-11">
            <ProductMedia src={vendor.image} alt={vendor.name} icon={Store} className="absolute inset-0 h-full w-full object-cover opacity-35" />
            <div className="absolute inset-0 bg-indigo-950/70" />
            <div className="relative grid gap-8 lg:grid-cols-[1fr_auto] lg:items-center">
                <div>
                    <Badge className="border border-white/20 bg-white/10 text-white"><Star className="mr-1 size-4 fill-white" /> Featured Vendor</Badge>
                    <h2 className="mt-5 text-3xl font-extrabold tracking-tight">{vendor.name}</h2>
                    <p className="mt-5 max-w-2xl text-lg leading-8 text-indigo-100">{vendor.description}</p>
                    <Button asChild className="mt-7 rounded-full bg-white px-7 text-indigo-950 hover:bg-indigo-50"><Link href="/marketplace">Explore Portfolio <ArrowRight className="size-4" /></Link></Button>
                </div>
                <div className="grid grid-cols-2 gap-4">
                    {[
                        [vendor.successRate, 'Trust signal'],
                        [vendor.sales, 'Sales'],
                    ].map(([value, label]) => (
                        <div key={label} className="rounded-lg border border-white/20 bg-white/10 px-8 py-6 text-center backdrop-blur">
                            <p className="text-3xl font-extrabold">{value}</p>
                            <p className="mt-2 text-sm font-extrabold uppercase tracking-wide text-indigo-200">{label}</p>
                        </div>
                    ))}
                </div>
            </div>
        </section>
    );
}

function FulfillmentOverview({ products }) {
    const groups = [
        { key: 'physical', label: 'Physical', icon: Truck, hint: 'Shipping and stock tracked' },
        { key: 'digital', label: 'Digital', icon: Download, hint: 'Delivered after checkout' },
        { key: 'instant_delivery', label: 'Instant', icon: Zap, hint: 'Automatic digital handoff' },
        { key: 'service', label: 'Service', icon: BriefcaseBusiness, hint: 'Seller workflow delivery' },
    ].map((item) => ({
        ...item,
        count: products.filter((product) => product.productType === item.key).length,
    }));

    return (
        <section className="mt-10 grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
            {groups.map(({ key, label, icon: Icon, hint, count }) => (
                <Link key={key} href="/marketplace" className="rounded-lg border border-slate-200 bg-white p-5 shadow-sm transition duration-300 hover:-translate-y-0.5 hover:border-indigo-200 hover:shadow-lg">
                    <div className="flex items-center justify-between gap-3">
                        <span className="flex size-10 items-center justify-center rounded-md bg-slate-100 text-slate-700">
                            <Icon className="size-5" />
                        </span>
                        <span className="text-2xl font-extrabold text-slate-950">{count}</span>
                    </div>
                    <h2 className="mt-4 text-base font-extrabold text-slate-950">{label} listings</h2>
                    <p className="mt-1 text-sm font-medium text-slate-500">{hint}</p>
                </Link>
            ))}
        </section>
    );
}

function BestSellers({ state, addToCart }) {
    return (
        <section className="mt-12 rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
            <SectionTitle icon={TrendingUp} title="Best Sellers" accent="text-amber-500" />
            <div className="grid gap-5 md:grid-cols-2 xl:grid-cols-4">
                {state.products.slice(0, 4).map((product, index) => (
                    <div key={product.id} className="relative rounded-lg border border-slate-200 p-4 transition duration-300 hover:-translate-y-1 hover:shadow-lg">
                        <span className="absolute -left-3 -top-3 z-10 flex size-9 items-center justify-center rounded-full bg-amber-400 text-sm font-extrabold text-slate-950 shadow">#{index + 1}</span>
                        <ProductMedia src={product.image} alt={product.title} className="aspect-[4/3] w-full rounded-md object-cover" />
                        <h3 className="mt-4 line-clamp-1 text-base font-bold">{product.title}</h3>
                        <p className="mt-2 flex items-center gap-1 text-sm font-semibold text-indigo-600">
                            {'★★★★★'} <span className="ml-1 text-slate-500">{product.reviewCount || 0} reviews</span>
                        </p>
                        <p className="mt-3 flex items-center gap-1 text-xs font-extrabold uppercase tracking-wide text-slate-600">
                            {(() => {
                                const meta = productFulfillmentMeta(product);
                                if (!meta) return null;
                                const Icon = meta.icon;
                                return <><Icon className="size-3" />{meta.label} ready</>;
                            })()}
                        </p>
                        <div className="mt-7 flex items-center justify-between">
                            <p className="text-2xl font-extrabold">{money(product.price)}</p>
                            <Button size="icon" className="rounded-full bg-slate-100 text-slate-950 shadow-none hover:bg-slate-950 hover:text-white" onClick={() => addToCart(product)} aria-label="Add to cart"><ShoppingCart className="size-5" /></Button>
                        </div>
                    </div>
                ))}
            </div>
        </section>
    );
}

function JustDropped({ state, addToCart }) {
    return (
        <section className="mt-12">
            <SectionTitle icon={Sparkles} title="Just Dropped" accent="text-indigo-600" />
            <div className="grid gap-5 lg:grid-cols-2">
                {state.products.slice(4, 6).concat(state.products.slice(0, 2)).map((product) => (
                    <div key={`drop-${product.id}`} className="grid gap-4 rounded-lg border border-slate-200 bg-white p-4 shadow-sm transition duration-300 hover:-translate-y-1 hover:shadow-lg sm:grid-cols-[180px_1fr_auto] sm:items-center">
                        <ProductMedia src={product.image} alt={product.title} className="aspect-square w-full rounded-md object-cover sm:size-40" />
                        <div>
                            <p className="text-xs font-extrabold uppercase tracking-wide text-slate-500">{product.seller}</p>
                            <h3 className="mt-2 text-lg font-bold">{product.title}</h3>
                            <p className="mt-2 text-slate-500">{product.description}</p>
                            <p className="mt-4 text-xl font-extrabold">{money(product.price)}</p>
                            <p className="mt-1 flex items-center gap-1 text-xs font-extrabold uppercase tracking-wide text-slate-600">
                                {(() => {
                                    const meta = productFulfillmentMeta(product);
                                    if (!meta) return null;
                                    const Icon = meta.icon;
                                    return <><Icon className="size-3" /> {meta.label}</>;
                                })()}
                            </p>
                        </div>
                        <Button onClick={() => addToCart(product)} className="bg-slate-950 px-7 hover:bg-indigo-600">Add</Button>
                    </div>
                ))}
            </div>
        </section>
    );
}

function Recommended({ state }) {
    return (
        <section className="mt-12">
            <h2 className="mb-6 text-2xl font-extrabold tracking-tight">Recommended For You</h2>
            <div className="grid gap-5 sm:grid-cols-2 lg:grid-cols-3 2xl:grid-cols-6">
                {state.products.map((product) => (
                    <Link key={`rec-${product.id}`} href={`/products/${product.id}`} className="rounded-lg border border-slate-200 bg-white p-3 shadow-sm transition duration-300 hover:-translate-y-1 hover:shadow-lg">
                        <ProductMedia src={product.image} alt={product.title} className="aspect-square w-full rounded-md object-cover" />
                        <h3 className="mt-4 line-clamp-2 min-h-11 font-extrabold">{product.title}</h3>
                        <div className="mt-5 border-t border-slate-100 pt-3">
                            <p className="flex items-center gap-1 text-sm font-semibold text-slate-500">
                                {(() => {
                                    const meta = productFulfillmentMeta(product);
                                    if (!meta) return null;
                                    const Icon = meta.icon;
                                    return <Icon className="size-4 text-indigo-600" />;
                                })()}
                                {product.seller}
                            </p>
                            <p className="mt-2 text-xs font-bold uppercase tracking-wide text-slate-400">{product.productTypeLabel || product.category}</p>
                            <p className="mt-3 text-lg font-extrabold">{money(product.price)}</p>
                        </div>
                    </Link>
                ))}
            </div>
        </section>
    );
}

function ProductDetail({ productId, state, addToCart, toggleWishlist }) {
    const product = state.products.find((item) => item.id === Number(productId)) || state.products[0];
    const [activeImage, setActiveImage] = useState(() => product?.images?.[0] || product?.image || '');
    const [selectedVariantId, setSelectedVariantId] = useState(null);
    const [quantity, setQuantity] = useState(1);
    useEffect(() => {
        setActiveImage(product?.images?.[0] || product?.image || '');
        setSelectedVariantId(product?.variants?.find((variant) => variant.active)?.id || null);
        setQuantity(1);
    }, [product?.id, product?.image]);
    if (!product) {
        return <Empty title="This listing is not available" action="/marketplace" label="Back to marketplace" />;
    }
    const selectedVariant = product.variants?.find((variant) => variant.id === selectedVariantId);
    const displayPrice = asNumber(product.price) + asNumber(selectedVariant?.priceDelta);
    const availableStock = selectedVariant ? asNumber(selectedVariant.stock) : asNumber(product.availableStock ?? product.stock);
    const gallery = product.images?.length ? product.images : [product.image].filter(Boolean);
    const primaryImage = activeImage || gallery[0] || product.image;
    const attributes = product.attributeRows || [];
    const relatedProducts = state.products
        .filter((item) => item.id !== product.id)
        .filter((item) => item.subcategory === product.subcategory || item.category === product.category || (product.brand && item.brand === product.brand))
        .slice(0, 6);
    const sellerProducts = state.products
        .filter((item) => item.id !== product.id)
        .filter((item) => item.seller === product.seller)
        .slice(0, 6);
    const detailTiles = [
        ['Category', product.category || 'Marketplace'],
        ['Condition', product.condition || 'New'],
        ['Location', product.productLocation || product.city || '—'],
        ['Warranty', product.warrantyStatus || 'Ask seller'],
        ['Brand', product.brand || '—'],
        ['Available', `${availableStock} in stock`],
        ['SKU', selectedVariant?.sku || product.uuid || `SKU-${product.id}`],
    ];

    return (
        <section className="space-y-6">
            <div className="grid gap-6 xl:grid-cols-[minmax(320px,0.95fr)_minmax(0,1.05fr)]">
                <div className="overflow-hidden rounded-xl border border-slate-200/80 bg-white shadow-[0_18px_60px_-40px_rgba(15,23,42,0.55)] ring-1 ring-white">
                    <div className="bg-slate-50 p-4">
                        <ProductMedia src={primaryImage} alt={product.title} className="aspect-[4/3] w-full rounded-lg object-cover" />
                    </div>
                    {gallery.length > 1 ? (
                        <div className="grid grid-cols-5 gap-2 border-t border-slate-100 p-4">
                            {gallery.map((image) => (
                                <button
                                    key={image}
                                    type="button"
                                    onClick={() => setActiveImage(image)}
                                    className={cn('overflow-hidden rounded-md border bg-slate-50', primaryImage === image ? 'border-indigo-500 ring-2 ring-indigo-100' : 'border-slate-200')}
                                >
                                    <ProductMedia src={image} alt={`${product.title} thumbnail`} className="aspect-square w-full object-cover" />
                                </button>
                            ))}
                        </div>
                    ) : null}
                </div>

                <aside className="h-fit rounded-xl border border-slate-200/80 bg-white/95 p-6 shadow-[0_18px_60px_-40px_rgba(15,23,42,0.55)] ring-1 ring-white">
                    <div className="flex flex-wrap gap-2">
                        <Badge variant={product.type === 'Classified' ? 'warning' : 'secondary'}>{product.type}</Badge>
                        <Badge variant={product.productType === 'physical' ? 'outline' : 'success'}>{product.productTypeLabel || 'Marketplace item'}</Badge>
                        {product.verified ? <Badge variant="success" className="gap-1"><ShieldCheck className="size-3" />Verified seller</Badge> : null}
                    </div>
                    <h1 className="mt-5 text-3xl font-extrabold leading-tight tracking-tight text-slate-950">{product.title}</h1>
                    <p className="mt-3 text-sm font-semibold text-slate-500">{product.seller}{product.city ? ` - ${product.city}` : ''}</p>
                    <div className="mt-5 flex flex-wrap items-end gap-3">
                        <p className="text-3xl font-extrabold tabular-nums text-rose-600">{money(displayPrice)}</p>
                        {product.oldPrice > product.price ? <p className="pb-1 text-lg font-bold text-slate-400 line-through">{money(product.oldPrice)}</p> : null}
                        {product.discountPercentage > 0 ? <Badge className="mb-1 bg-rose-500 text-white">{product.discountLabel || `${product.discountPercentage}% off`}</Badge> : null}
                    </div>
                    <div className="mt-5 grid gap-3 sm:grid-cols-2">
                        {detailTiles.map(([label, value]) => (
                            <div key={label} className="rounded-lg border border-slate-200 bg-slate-50 px-4 py-3">
                                <p className="text-xs font-extrabold uppercase tracking-wide text-slate-500">{label}</p>
                                <p className="mt-1 font-bold text-slate-950">{value}</p>
                            </div>
                        ))}
                    </div>
                    <div className="mt-5 rounded-lg border border-emerald-100 bg-emerald-50 p-4 text-sm text-emerald-800">
                        <p className="font-bold">Protected transaction</p>
                        <p className="mt-1 leading-6">{product.fulfillmentHint || 'Escrow checkout, verified seller signals, chat, tracking, return and dispute support.'}</p>
                    </div>
                    {product.variants?.length ? (
                        <div className="mt-5">
                            <p className="text-sm font-extrabold uppercase tracking-wide text-slate-500">Variant</p>
                            <div className="mt-3 grid gap-2 sm:grid-cols-2">
                                {product.variants.filter((variant) => variant.active).map((variant) => (
                                    <button key={variant.id} type="button" onClick={() => setSelectedVariantId(variant.id)} className={cn('rounded-lg border p-3 text-left text-sm transition', selectedVariantId === variant.id ? 'border-indigo-600 bg-indigo-50 text-indigo-950' : 'border-slate-200 bg-white hover:bg-slate-50')}>
                                        <span className="font-bold">{variant.title}</span>
                                        <span className="mt-1 block text-xs text-slate-500">{variant.sku || 'No SKU'} · Stock {variant.stock}</span>
                                    </button>
                                ))}
                            </div>
                        </div>
                    ) : null}
                    <div className="mt-5 flex items-center gap-3">
                        <Button size="icon" variant="outline" onClick={() => setQuantity((value) => Math.max(1, value - 1))} aria-label="Decrease quantity"><Minus className="size-4" /></Button>
                        <span className="w-10 text-center font-extrabold">{quantity}</span>
                        <Button size="icon" variant="outline" onClick={() => setQuantity((value) => Math.min(Math.max(availableStock, 1), value + 1))} aria-label="Increase quantity"><Plus className="size-4" /></Button>
                    </div>
                    <div className="mt-5 grid gap-2">
                        <Button disabled={availableStock <= 0} onClick={() => addToCart({ ...product, price: displayPrice, selectedVariant }, quantity)}><ShoppingCart className="size-4" />Add to cart</Button>
                        <Button disabled={availableStock <= 0} onClick={() => addToCart({ ...product, price: displayPrice, selectedVariant }, quantity).then(() => { window.location.href = '/checkout'; })} className="bg-rose-600 hover:bg-rose-700"><Zap className="size-4" />Buy now</Button>
                        <Button variant="outline" onClick={() => toggleWishlist(product.id)}><Heart className="size-4" />Wishlist</Button>
                        <Button asChild variant="outline"><Link href="/support"><MessageSquareText className="size-4" />Chat with seller</Link></Button>
                    </div>
                </aside>
            </div>

            <div className="grid gap-6 xl:grid-cols-[1fr_360px]">
                <div className="rounded-xl border border-slate-200/80 bg-white p-6 shadow-sm">
                    <h2 className="text-xl font-extrabold text-slate-950">Product details</h2>
                    <p className="mt-4 whitespace-pre-wrap leading-8 text-slate-600">{product.description}</p>
                    {attributes.length ? (
                        <div className="mt-6 grid gap-3 sm:grid-cols-2">
                            {attributes.map((item) => (
                                <div key={item.label} className="rounded-lg border border-slate-200 bg-slate-50 px-4 py-3">
                                    <p className="text-xs font-extrabold uppercase tracking-wide text-slate-500">{item.label}</p>
                                    <p className="mt-1 font-semibold text-slate-900">{item.value}</p>
                                </div>
                            ))}
                        </div>
                    ) : null}
                    {product.variants?.length ? (
                        <div className="mt-7">
                            <h3 className="text-base font-extrabold text-slate-950">Available variants</h3>
                            <div className="mt-3 grid gap-3 sm:grid-cols-2">
                                {product.variants.map((variant) => (
                                    <div key={variant.id} className="rounded-lg border border-slate-200 p-4">
                                        <div className="flex items-start justify-between gap-3">
                                            <div>
                                                <p className="font-bold">{variant.title}</p>
                                                <p className="mt-1 text-sm text-slate-500">{variant.sku || 'No SKU'}</p>
                                            </div>
                                            <Badge variant={variant.active ? 'success' : 'secondary'}>{variant.active ? 'Active' : 'Inactive'}</Badge>
                                        </div>
                                        <p className="mt-3 text-sm font-semibold text-slate-600">Stock {variant.stock}</p>
                                    </div>
                                ))}
                            </div>
                        </div>
                    ) : null}
                </div>
                <div className="space-y-4">
                    <div className="rounded-xl border border-slate-200/80 bg-white p-5 shadow-sm">
                        <h2 className="font-extrabold text-slate-950">Seller confidence</h2>
                        <div className="mt-4 space-y-3 text-sm">
                            {[
                                ['Rating', `${product.rating || 0}/5 from ${product.reviewCount || 0} reviews`],
                                ['Sales', `${product.salesCount || 0} order line items`],
                                ['Store status', product.storeStatus || 'Active marketplace seller'],
                                ['Fulfillment', product.productTypeLabel || product.type],
                            ].map(([label, value]) => (
                                <div key={label} className="flex justify-between gap-4 border-b border-slate-100 pb-3 last:border-0 last:pb-0">
                                    <span className="text-slate-500">{label}</span>
                                    <span className="text-right font-bold text-slate-900">{value}</span>
                                </div>
                            ))}
                        </div>
                    </div>
                    <div className="rounded-xl border border-slate-200/80 bg-white p-5 shadow-sm">
                        <h2 className="font-extrabold text-slate-950">Included protections</h2>
                        <div className="mt-4 space-y-3 text-sm font-semibold text-slate-700">
                            {['Escrow checkout', 'Order chat support', 'Delivery tracking', 'Return and dispute workflow'].map((item) => (
                                <p key={item} className="flex items-center gap-2"><Check className="size-4 text-emerald-600" />{item}</p>
                            ))}
                        </div>
                    </div>
                </div>
            </div>
            {relatedProducts.length ? (
                <section className="rounded-xl border border-slate-200/80 bg-white p-5 shadow-sm">
                    <SectionTitle icon={Sparkles} title="You May Also Like" accent="text-rose-500" action="View more" />
                    <div className="grid gap-5 sm:grid-cols-2 xl:grid-cols-3">
                        {relatedProducts.map((item) => (
                            <ProductCard key={`related-${item.id}`} product={item} addToCart={addToCart} toggleWishlist={toggleWishlist} wished={state.wishlist.includes(item.id)} />
                        ))}
                    </div>
                </section>
            ) : null}
            {sellerProducts.length ? (
                <section className="rounded-xl border border-slate-200/80 bg-white p-5 shadow-sm">
                    <SectionTitle icon={Store} title="More From The Seller" accent="text-indigo-600" action="Visit store" />
                    <div className="grid gap-5 sm:grid-cols-2 xl:grid-cols-3">
                        {sellerProducts.map((item) => (
                            <ProductCard key={`seller-more-${item.id}`} product={item} addToCart={addToCart} toggleWishlist={toggleWishlist} wished={state.wishlist.includes(item.id)} />
                        ))}
                    </div>
                </section>
            ) : null}
            <div className="fixed inset-x-0 bottom-[64px] z-30 border-t border-slate-200 bg-white/95 p-3 shadow-[0_-18px_45px_-34px_rgba(15,23,42,0.85)] backdrop-blur lg:hidden">
                <div className="mx-auto flex max-w-md items-center gap-3">
                    <div className="min-w-0 flex-1">
                        <p className="truncate text-sm font-extrabold">{product.title}</p>
                        <p className="text-base font-extrabold text-rose-600">{money(displayPrice)}</p>
                    </div>
                    <Button disabled={availableStock <= 0} onClick={() => addToCart({ ...product, price: displayPrice, selectedVariant }, quantity)}>Add</Button>
                    <Button disabled={availableStock <= 0} onClick={() => addToCart({ ...product, price: displayPrice, selectedVariant }, quantity).then(() => { window.location.href = '/checkout'; })} className="bg-rose-600 hover:bg-rose-700">Buy</Button>
                </div>
            </div>
        </section>
    );
}

function Cart({ state, updateCart, removeCart }) {
    const subtotal = state.cart.reduce((sum, item) => sum + item.price * item.quantity, 0);
    return (
        <section className="grid gap-6 lg:grid-cols-[1fr_360px]">
            <div className="rounded-xl border border-slate-200/80 bg-white/95 shadow-[0_18px_60px_-40px_rgba(15,23,42,0.55)] ring-1 ring-white">
                <div className="border-b border-slate-200 p-4"><h1 className="text-xl font-bold">Cart</h1></div>
                {!state.cart.length ? <Empty title="Your cart is empty" action="/marketplace" label="Browse marketplace" /> : (
                    <div className="divide-y divide-slate-100">
                        {state.cart.map((item) => (
                            <div key={item.id} className="grid gap-4 p-4 sm:grid-cols-[96px_1fr_auto] sm:items-center">
                                <ProductMedia src={item.image} alt={item.title} className="size-24 rounded-md object-cover" />
                                <div>
                                    <p className="font-semibold">{item.title}</p>
                                    <p className="text-sm text-slate-500">{item.seller} · {money(item.price)}</p>
                                    <div className="mt-3 flex items-center gap-2">
                                        <Button size="icon" variant="outline" onClick={() => updateCart(item.id, item.quantity - 1)}><Minus className="size-4" /></Button>
                                        <span className="w-8 text-center text-sm font-semibold">{item.quantity}</span>
                                        <Button size="icon" variant="outline" onClick={() => updateCart(item.id, item.quantity + 1)}><Plus className="size-4" /></Button>
                                    </div>
                                </div>
                                <Button variant="outline" onClick={() => removeCart(item.id)}>Remove</Button>
                            </div>
                        ))}
                    </div>
                )}
            </div>
            <aside className="h-fit rounded-xl border border-slate-200/80 bg-white/95 p-5 shadow-[0_18px_60px_-40px_rgba(15,23,42,0.55)] ring-1 ring-white">
                <h2 className="font-semibold">Order summary</h2>
                <div className="mt-4 space-y-2 text-sm">
                    <div className="flex justify-between"><span>Subtotal</span><span>{money(subtotal)}</span></div>
                    <div className="flex justify-between"><span>Escrow fee</span><span>{money(Math.round(subtotal * 0.015))}</span></div>
                    <div className="flex justify-between"><span>Delivery estimate</span><span>{money(state.cart.length ? 120 : 0)}</span></div>
                </div>
                <div className="mt-4 border-t border-slate-200 pt-4 text-base font-bold">
                    <div className="flex justify-between"><span>Total</span><span>{money(subtotal + Math.round(subtotal * 0.015) + (state.cart.length ? 120 : 0))}</span></div>
                </div>
                <Button asChild className="mt-5 w-full" disabled={!state.cart.length}><Link href="/checkout">Checkout</Link></Button>
            </aside>
        </section>
    );
}

function Checkout({ state, checkout }) {
    const [address, setAddress] = useState(state.user?.city || state.business?.address || '');
    const [payment, setPayment] = useState('wallet');
    return (
        <section className="grid gap-6 lg:grid-cols-[1fr_360px]">
            <div className="space-y-4">
                <Panel title="Shipping address" icon={Truck}>
                    <Input value={address} onChange={(e) => setAddress(e.target.value)} />
                </Panel>
                <Panel title="Payment method" icon={CreditCard}>
                    <div className="grid gap-3 sm:grid-cols-3">
                        {['wallet', 'manual', 'card'].map((item) => (
                            <button key={item} onClick={() => setPayment(item)} className={cn('rounded-md border p-4 text-left text-sm capitalize transition', payment === item ? 'border-slate-950 bg-slate-950 text-white' : 'border-slate-200 bg-white hover:bg-slate-50')}>
                                {item === 'wallet' ? 'Wallet escrow' : item}
                            </button>
                        ))}
                    </div>
                </Panel>
                <Panel title="Compliance" icon={ShieldCheck}>
                    <div className="grid gap-3 sm:grid-cols-3">
                        {['Escrow hold', 'Seller verification', 'Return/dispute eligible'].map((item) => <Badge key={item} variant="success" className="justify-center py-2">{item}</Badge>)}
                    </div>
                </Panel>
            </div>
            <aside className="h-fit rounded-xl border border-slate-200/80 bg-white/95 p-5 shadow-[0_18px_60px_-40px_rgba(15,23,42,0.55)] ring-1 ring-white">
                <h2 className="font-semibold">Checkout</h2>
                <p className="mt-2 text-sm text-slate-500">{state.cart.length} items ready for secure order creation.</p>
                <Button className="mt-5 w-full" onClick={() => checkout(payment, address)} asChild={false}>Place order</Button>
                <Button asChild variant="outline" className="mt-2 w-full"><Link href="/orders">View orders</Link></Button>
            </aside>
        </section>
    );
}

function Orders({ state }) {
    return (
        <Panel title="Order tracking" icon={Truck}>
            <div className="grid gap-4">
                {state.orders.map((order) => (
                    <div key={order.id} className="rounded-lg border border-slate-200 bg-slate-50/70 p-4 transition duration-300 hover:border-cyan-200 hover:bg-white hover:shadow-sm">
                        <div className="flex flex-wrap items-center justify-between gap-3">
                            <div>
                                <p className="font-semibold">{order.id} · {order.product}</p>
                                <p className="text-sm text-slate-500">{order.stage} · ETA {order.eta}</p>
                            </div>
                            <Badge variant={order.status === 'Completed' ? 'success' : 'secondary'}>{order.status}</Badge>
                        </div>
                        <div className="mt-4 h-2 rounded-full bg-slate-200"><div className="h-full rounded-full bg-cyan-600 transition-all" style={{ width: `${order.progress}%` }} /></div>
                        <div className="mt-3 flex justify-between text-sm"><span>{money(order.amount)}</span><Link href="/support" className="font-medium text-cyan-700">Open chat</Link></div>
                    </div>
                ))}
            </div>
        </Panel>
    );
}

function Wishlist({ state, addToCart, toggleWishlist }) {
    const products = state.products.filter((product) => state.wishlist.includes(product.id));
    return <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">{products.map((product) => <ProductCard key={product.id} product={product} addToCart={addToCart} toggleWishlist={toggleWishlist} wished />)}</div>;
}

function Profile({ state, saveProfile }) {
    const [form, setForm] = useState(state.user);
    return (
        <Panel title="Buyer profile and shared auth" icon={User}>
            <div className="grid gap-4 md:grid-cols-2">
                <Input value={form.name} onChange={(e) => setForm({ ...form, name: e.target.value })} placeholder="Name" />
                <Input value={form.email} onChange={(e) => setForm({ ...form, email: e.target.value })} placeholder="Email" />
                <Input value={form.city} onChange={(e) => setForm({ ...form, city: e.target.value })} placeholder="City" />
                <Button onClick={() => saveProfile(form)}><Check className="size-4" />Save profile</Button>
            </div>
            <p className="mt-4 rounded-md bg-cyan-50 p-4 text-sm text-cyan-900">
                The backend already exposes mobile-compatible auth/profile APIs. This web profile is structured to use the same account identity.
            </p>
        </Panel>
    );
}

function Support({ state, sendMessage }) {
    const [body, setBody] = useState('');
    return (
        <section className="grid gap-6 lg:grid-cols-[1fr_360px]">
            <Panel title="Buyer/seller support chat" icon={MessageSquareText}>
                <div className="space-y-3">
                    {state.chats.map((chat, index) => (
                        <div key={index} className={cn('max-w-[82%] rounded-lg p-3 text-sm', chat.from === 'buyer' ? 'ml-auto bg-slate-950 text-white' : 'bg-slate-100 text-slate-800')}>
                            <p>{chat.body}</p><p className="mt-1 text-xs opacity-70">{chat.time}</p>
                        </div>
                    ))}
                </div>
                <div className="mt-4 flex gap-2">
                    <Input value={body} onChange={(e) => setBody(e.target.value)} placeholder="Write a message..." />
                    <Button onClick={() => { sendMessage(body); setBody(''); }}>Send</Button>
                </div>
            </Panel>
            <Panel title="Tickets" icon={ClipboardCheck}>
                {state.supportTickets.length ? state.supportTickets.map((ticket) => <div key={ticket.id} className="rounded-md border border-slate-200 p-3 text-sm"><p className="font-semibold">{ticket.id}</p><p>{ticket.subject}</p><Badge className="mt-2" variant="warning">{ticket.status}</Badge></div>) : <p className="text-sm text-slate-500">No open support tickets.</p>}
            </Panel>
        </section>
    );
}

function SellerDashboard({ state }) {
    const revenue = state.orders.reduce((sum, order) => sum + order.amount, 0);
    return (
        <div className="space-y-6">
            <PageIntro
                eyebrow="Seller command center"
                title="Run catalog, fulfillment, payouts, and customer work from one desk"
                description="A dense operating surface for serious sellers: revenue, stock risk, order stage, verification, delivery proof, offers, and finance activity."
                actionHref="/seller/products"
                actionLabel="Create listing"
            />
            <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                <Stat label="Revenue" value={money(revenue)} hint="All web orders" icon={BarChart3} />
                <Stat label="Products" value={state.sellerProducts.length} hint="Active seller catalog" icon={Package} />
                <Stat label="Low stock" value={state.sellerProducts.filter((p) => p.stock < 10).length} hint="Needs replenishment" icon={Boxes} />
                <Stat label="Payouts" value={state.payoutRequests.length} hint="Requests and ready balances" icon={WalletCards} />
            </div>
            <Panel title="Seller command center" icon={LayoutDashboard}>
                <div className="grid gap-3 md:grid-cols-3">
                    {['KYC verified', 'Escrow enabled', 'Realtime notifications', 'Coupons active', 'Delivery proof required', 'Return queue ready'].map((item) => (
                        <div key={item} className="flex items-center gap-3 rounded-lg border border-slate-200 bg-slate-50 p-3 text-sm font-medium">
                            <span className="flex size-7 items-center justify-center rounded-full bg-emerald-50 text-emerald-700"><Check className="size-4" /></span>
                            {item}
                        </div>
                    ))}
                </div>
            </Panel>
        </div>
    );
}

function SellerProducts({ state, addSellerProduct }) {
    const rootCategoryRows = (state.categories || []).filter(isRootCategory);
    const rootCategories = rootCategoryRows.length ? rootCategoryRows : (state.categories || []);
    const firstCategory = rootCategories[0] || state.categories?.[0];
    const [form, setForm] = useState({ title: '', price: '', stock: '', category_id: firstCategory?.id || '', category: firstCategory?.name || '', subcategory_id: '', type: 'Marketplace', condition: 'New', description: '' });
    const selectedCategoryId = Number(form.category_id || 0);
    const subcategories = (state.categories || []).filter((item) => Number(item.parent_id) === selectedCategoryId);
    const publish = () => {
        const categoryId = Number(form.subcategory_id || form.category_id || 0);
        addSellerProduct({ ...form, category_id: categoryId });
    };
    return (
        <section className="grid gap-6 lg:grid-cols-[380px_1fr]">
            <Panel title="Create listing" icon={Plus}>
                <div className="grid gap-3">
                    <Input className="h-11 bg-slate-50 focus:bg-white" placeholder="Product title" value={form.title} onChange={(e) => setForm({ ...form, title: e.target.value })} />
                    <Input className="h-11 bg-slate-50 focus:bg-white" placeholder="Price" value={form.price} onChange={(e) => setForm({ ...form, price: e.target.value })} />
                    <Input className="h-11 bg-slate-50 focus:bg-white" placeholder="Stock" value={form.stock} onChange={(e) => setForm({ ...form, stock: e.target.value })} />
                    <select value={form.category_id} onChange={(e) => {
                        const category = rootCategories.find((item) => String(item.id) === e.target.value);
                        setForm({ ...form, category_id: e.target.value, category: category?.name || '', subcategory_id: '' });
                    }} className="h-11 rounded-md border-slate-200 bg-slate-50 text-sm">
                        {rootCategories.map((category) => <option key={category.id} value={category.id}>{category.name}</option>)}
                    </select>
                    <select value={form.subcategory_id} onChange={(e) => setForm({ ...form, subcategory_id: e.target.value })} className="h-11 rounded-md border-slate-200 bg-slate-50 text-sm" disabled={!subcategories.length}>
                        <option value="">No subcategory</option>
                        {subcategories.map((category) => <option key={category.id} value={category.id}>{category.name}</option>)}
                    </select>
                    <select value={form.type} onChange={(e) => setForm({ ...form, type: e.target.value })} className="h-11 rounded-md border-slate-200 bg-slate-50 text-sm"><option>Marketplace</option><option>Classified</option></select>
                    <Input className="h-11 bg-slate-50 focus:bg-white" placeholder="Description" value={form.description} onChange={(e) => setForm({ ...form, description: e.target.value })} />
                    <Button onClick={publish}>Publish listing</Button>
                </div>
            </Panel>
            <Panel title="Product catalog" icon={Package}>
                <ProductTable products={state.sellerProducts} />
            </Panel>
        </section>
    );
}

function ProductTable({ products }) {
    return (
        <div className="overflow-x-auto rounded-lg border border-slate-200">
            <table className="w-full text-left text-sm">
                <thead className="border-b bg-slate-50 text-xs uppercase tracking-wide text-slate-500"><tr><th className="px-3 py-3">Product</th><th className="px-3 py-3">Type</th><th className="px-3 py-3">Stock</th><th className="px-3 py-3">Price</th><th className="px-3 py-3">Status</th></tr></thead>
                <tbody className="divide-y divide-slate-100 bg-white">
                    {products.map((product) => <tr key={product.id} className="transition hover:bg-cyan-50/40"><td className="px-3 py-3 font-medium">{product.title}</td><td className="px-3 py-3">{product.productTypeLabel || product.type}</td><td className="px-3 py-3">{product.stock}</td><td className="px-3 py-3">{money(product.price)}</td><td className="px-3 py-3"><Badge variant={product.stock ? 'success' : 'warning'}>{product.stock ? 'Active' : 'Out'}</Badge></td></tr>)}
                </tbody>
            </table>
        </div>
    );
}

function SellerInventory({ state, adjustStock }) {
    return (
        <Panel title="Inventory management" icon={Boxes}>
            <div className="grid gap-3">
                {state.sellerProducts.map((product) => (
                    <div key={product.id} className="flex flex-wrap items-center justify-between gap-3 rounded-lg border border-slate-200 p-4">
                        <div><p className="font-semibold">{product.title}</p><p className="text-sm text-slate-500">Stock {product.stock} · {product.category}</p></div>
                        <div className="flex gap-2">
                            <Button variant="outline" size="icon" onClick={() => adjustStock(product.id, -1)}><Minus className="size-4" /></Button>
                            <Button variant="outline" size="icon" onClick={() => adjustStock(product.id, 1)}><Plus className="size-4" /></Button>
                        </div>
                    </div>
                ))}
            </div>
        </Panel>
    );
}

function SellerOrders({ state }) {
    return <Orders state={state} />;
}

function SellerPayouts({ state, requestPayout }) {
    const [amount, setAmount] = useState('50000');
    return (
        <section className="grid gap-6 lg:grid-cols-[360px_1fr]">
            <Panel title="Request payout" icon={WalletCards}>
                <Input value={amount} onChange={(e) => setAmount(e.target.value)} />
                <Button className="mt-3 w-full" onClick={() => requestPayout(amount)}>Submit payout</Button>
            </Panel>
            <Panel title="Payout history" icon={ReceiptText}>
                <div className="grid gap-3">{state.payoutRequests.map((item) => <div key={item.id} className="flex justify-between rounded-md border border-slate-200 p-3 text-sm"><span>{item.id} · {item.method}</span><strong>{money(item.amount)} · {item.status}</strong></div>)}</div>
            </Panel>
        </section>
    );
}

function SellerOffers({ state, addCoupon }) {
    const [coupon, setCoupon] = useState({ code: '', value: '' });
    return (
        <section className="grid gap-6 lg:grid-cols-[360px_1fr]">
            <Panel title="Create offer" icon={Tag}>
                <Input placeholder="Coupon code" value={coupon.code} onChange={(e) => setCoupon({ ...coupon, code: e.target.value.toUpperCase() })} />
                <Input className="mt-3" placeholder="Discount %" value={coupon.value} onChange={(e) => setCoupon({ ...coupon, value: e.target.value })} />
                <Button className="mt-3 w-full" onClick={() => addCoupon(coupon)}>Create coupon</Button>
            </Panel>
            <Panel title="Coupons and campaigns" icon={Sparkles}>
                <div className="grid gap-3">{state.coupons.map((item) => <div key={item.code} className="flex justify-between rounded-md border border-slate-200 p-3 text-sm"><span>{item.code} · {item.value}% off</span><Badge variant="success">{item.status} · {item.usage}</Badge></div>)}</div>
            </Panel>
        </section>
    );
}

function SellerBusiness({ state, saveBusiness }) {
    const [business, setBusiness] = useState(state.business);
    return (
        <Panel title="Business profile" icon={BriefcaseBusiness}>
            <div className="grid gap-3 md:grid-cols-2">
                <Input value={business.name} onChange={(e) => setBusiness({ ...business, name: e.target.value })} />
                <Input value={business.phone} onChange={(e) => setBusiness({ ...business, phone: e.target.value })} />
                <Input value={business.address} onChange={(e) => setBusiness({ ...business, address: e.target.value })} />
                <Input value={business.verification} onChange={(e) => setBusiness({ ...business, verification: e.target.value })} />
            </div>
            <Button className="mt-4" onClick={() => saveBusiness(business)}>Save business profile</Button>
        </Panel>
    );
}

function Panel({ title, icon: Icon, children }) {
    return (
        <section className="rounded-xl border border-slate-200/80 bg-white/95 p-5 shadow-[0_18px_60px_-42px_rgba(15,23,42,0.5)] ring-1 ring-white backdrop-blur">
            <div className="mb-4 flex items-center gap-3">
                {Icon ? <span className="flex size-10 items-center justify-center rounded-lg bg-cyan-50 text-cyan-700 ring-1 ring-cyan-100"><Icon className="size-5" /></span> : null}
                <h1 className="text-lg font-bold">{title}</h1>
            </div>
            {children}
        </section>
    );
}

function Empty({ title, action, label }) {
    return (
        <div className="p-10 text-center">
            <p className="font-semibold">{title}</p>
            <Button asChild className="mt-4"><Link href={action}>{label}</Link></Button>
        </div>
    );
}

function EnterpriseFooter({ trustItems = [] }) {
    const items = trustItems.length ? trustItems : [
        { title: 'Escrow Protection', body: 'Funds held securely until delivery.' },
        { title: 'Fulfillment Aware', body: 'Physical, digital, instant, and service listings are clearly labeled.' },
        { title: 'Verified Vendors', body: 'Strict seller checks and performance tracking.' },
        { title: 'Support Desk', body: 'Dedicated dispute resolution team.' },
    ];
    const icons = [LockKeyhole, Zap, ShieldCheck, Headphones];

    return (
        <>
            <section className="-mx-4 mt-16 border-y border-slate-200 bg-white sm:-mx-6 lg:-mx-8">
                <div className="mx-auto grid max-w-[1480px] gap-6 px-4 py-10 sm:px-6 md:grid-cols-2 lg:grid-cols-4 lg:px-8">
                    {items.map(({ title, body }, index) => {
                        const Icon = icons[index] || ShieldCheck;
                        return (
                        <div key={title} className="flex items-center gap-5">
                            <Icon className="size-12 text-indigo-600" />
                            <div>
                                <h3 className="text-base font-bold">{title}</h3>
                                <p className="mt-1 text-sm text-slate-500">{body}</p>
                            </div>
                        </div>
                    );})}
                </div>
            </section>
            <footer className="-mx-4 bg-[#020617] text-white sm:-mx-6 lg:-mx-8">
                <div className="mx-auto grid max-w-[1480px] gap-10 px-4 py-16 sm:px-6 lg:grid-cols-[1.35fr_1fr_1fr_1fr] lg:px-8">
                    <div>
                        <div className="flex items-center gap-3">
                            <span className="flex size-11 items-center justify-center rounded-lg bg-indigo-600"><ShoppingBag className="size-7" /></span>
                            <p className="text-2xl font-extrabold">Sellova</p>
                        </div>
                        <p className="mt-8 max-w-md text-sm leading-8 text-slate-400">The secure classified and ecommerce marketplace for high-value digital and physical assets, powered by escrow, seller verification, and instant delivery flows.</p>
                        <div className="mt-8 flex gap-3">
                            {['FB', 'TW', 'IG', 'IN'].map((item) => <span key={item} className="flex size-10 items-center justify-center rounded-full bg-slate-900 text-sm font-extrabold text-slate-300">{item}</span>)}
                        </div>
                    </div>
                    <div>
                        <h3 className="font-extrabold uppercase tracking-wide">Marketplace</h3>
                        <div className="mt-7 grid gap-4 text-slate-400">
                            {['Digital Assets', 'Software & Code', 'Luxury Goods', 'Premium Domains', 'Services & Contracts'].map((item) => <Link key={item} href="/marketplace" className="font-semibold hover:text-white">{item}</Link>)}
                        </div>
                    </div>
                    <div>
                        <h3 className="font-extrabold uppercase tracking-wide">Customer Service</h3>
                        <div className="mt-7 grid gap-4 text-slate-400">
                            {['Help Center', 'How Escrow Works', 'Dispute Resolution', 'Return Policy', 'Report an Issue'].map((item) => <Link key={item} href="/support" className="font-semibold hover:text-white">{item}</Link>)}
                        </div>
                    </div>
                    <div>
                        <h3 className="font-extrabold uppercase tracking-wide">Stay Updated</h3>
                        <p className="mt-7 text-slate-400">Subscribe for premium drops and market insights.</p>
                        <div className="mt-5 flex h-14 items-center gap-3 rounded-md bg-slate-900 px-4 text-slate-400">
                            <Mail className="size-5" />
                            <span>Enter your email</span>
                        </div>
                        <Button className="mt-4 h-14 w-full bg-indigo-600 text-sm hover:bg-indigo-500">Subscribe</Button>
                    </div>
                </div>
            </footer>
        </>
    );
}

function HomePage({ state, addToCart, toggleWishlist }) {
    return (
        <>
            <Hero state={state} addToCart={addToCart} />
            <FulfillmentOverview products={state.products} />
            <FlashDeals state={state} addToCart={addToCart} toggleWishlist={toggleWishlist} />
            <FeaturedVendor vendor={state.featuredVendor} />
            <BestSellers state={state} addToCart={addToCart} />
            <JustDropped state={state} addToCart={addToCart} />
            <Recommended state={state} />
        </>
    );
}

export default function Workspace({ mode = 'buyer', view, productId, initialMarketplace }) {
    const api = useMarketplaceState(initialMarketplace);
    const normalizedMode = mode === 'seller' ? 'seller' : 'buyer';
    const activeView = view || (normalizedMode === 'seller' ? 'seller-dashboard' : 'home');
    const cartCount = api.state.cart.reduce((sum, item) => sum + item.quantity, 0);

    let content;
    if (normalizedMode === 'seller') {
        if (activeView === 'seller-products') content = <SellerProducts state={api.state} addSellerProduct={api.addSellerProduct} />;
        else if (activeView === 'seller-inventory') content = <SellerInventory state={api.state} adjustStock={api.adjustStock} />;
        else if (activeView === 'seller-orders' || activeView === 'seller-delivery') content = <SellerOrders state={api.state} />;
        else if (activeView === 'seller-payouts') content = <SellerPayouts state={api.state} requestPayout={api.requestPayout} />;
        else if (activeView === 'seller-offers') content = <SellerOffers state={api.state} addCoupon={api.addCoupon} />;
        else if (activeView === 'seller-business') content = <SellerBusiness state={api.state} saveBusiness={api.saveBusiness} />;
        else if (activeView === 'seller-support') content = <Support state={api.state} sendMessage={api.sendMessage} />;
        else content = <SellerDashboard state={api.state} />;
    } else if (activeView === 'marketplace') content = <Marketplace state={api.state} addToCart={api.addToCart} toggleWishlist={api.toggleWishlist} />;
    else if (activeView === 'product') content = <ProductDetail productId={productId} state={api.state} addToCart={api.addToCart} toggleWishlist={api.toggleWishlist} />;
    else if (activeView === 'cart') content = <Cart state={api.state} updateCart={api.updateCart} removeCart={api.removeCart} />;
    else if (activeView === 'checkout') content = <Checkout state={api.state} checkout={api.checkout} />;
    else if (activeView === 'orders') content = <Orders state={api.state} />;
    else if (activeView === 'wishlist') content = <Wishlist state={api.state} addToCart={api.addToCart} toggleWishlist={api.toggleWishlist} />;
    else if (activeView === 'profile') content = <Profile state={api.state} saveProfile={api.saveProfile} />;
    else if (activeView === 'support') content = <Support state={api.state} sendMessage={api.sendMessage} />;
    else content = <HomePage state={api.state} addToCart={api.addToCart} toggleWishlist={api.toggleWishlist} />;

    return (
        <AppShell mode={normalizedMode} view={activeView} cartCount={cartCount} wishlistCount={api.state.wishlist.length} categories={api.state.categories} notice={api.notice}>
            {content}
            <EnterpriseFooter trustItems={api.state.trustItems} />
        </AppShell>
    );
}

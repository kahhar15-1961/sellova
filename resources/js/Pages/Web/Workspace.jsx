import { Head, Link } from '@inertiajs/react';
import { useEffect, useLayoutEffect, useMemo, useRef, useState } from 'react';
import { createPortal } from 'react-dom';
import {
    AlertCircle,
    ArrowRight,
    BadgeCheck,
    BarChart3,
    Banknote,
    Bell,
    Boxes,
    BriefcaseBusiness,
    Building2,
    CalendarDays,
    Check,
    ChevronLeft,
    ChevronRight,
    ClipboardCheck,
    Clock,
    CreditCard,
    Download,
    Copy,
    Edit,
    Eye,
    Filter,
    FileText,
    FileDown,
    FileUp,
    Flame,
    Heart,
    Home,
    Headphones,
    LayoutDashboard,
    LockKeyhole,
    Landmark,
    Mail,
    MapPin,
    Menu,
    MessageSquareText,
    Minus,
    Info,
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
    Shield,
    SlidersHorizontal,
    Smartphone,
    Paperclip,
    Send,
    Sparkles,
    Star,
    Store,
    Tag,
    ThumbsUp,
    TrendingUp,
    Truck,
    Trash2,
    Upload,
    User,
    Wallet,
    WalletCards,
    Zap,
    X,
} from 'lucide-react';
import { NotificationBell } from '@/components/notifications/NotificationBell';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { cn } from '@/lib/utils';
import { getEcho } from '@/realtime/echo';

const fallbackCategoryNames = ['All'];
const PAGE_SIZE = 12;

function money(value) {
    return `৳${Number(value || 0).toLocaleString('en-BD')}`;
}

function currentTimeLabel() {
    return new Intl.DateTimeFormat('en-GB', {
        hour: '2-digit',
        minute: '2-digit',
        hour12: false,
    }).format(new Date());
}

function formatDateTime(value) {
    if (!value) return 'Pending';
    return new Date(value).toLocaleString('en-US', {
        month: 'short',
        day: 'numeric',
        year: 'numeric',
        hour: 'numeric',
        minute: '2-digit',
    });
}

function formatTimeOnly(value) {
    if (!value) return 'Pending';
    return new Date(value).toLocaleTimeString('en-US', {
        hour: 'numeric',
        minute: '2-digit',
    });
}

function formatCountdown(seconds) {
    const safe = Math.max(0, Number(seconds || 0));
    const hours = String(Math.floor(safe / 3600)).padStart(2, '0');
    const minutes = String(Math.floor((safe % 3600) / 60)).padStart(2, '0');
    const secs = String(safe % 60).padStart(2, '0');
    return `${hours}:${minutes}:${secs}`;
}

function csrfToken() {
    if (typeof document === 'undefined') return '';
    return document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '';
}

async function postAction(url, payload) {
    return requestJson(url, { method: 'POST', payload });
}

async function requestJson(url, { method = 'GET', payload } = {}) {
    const headers = {
        Accept: 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
    };
    const options = {
        method,
        credentials: 'same-origin',
        headers,
    };

    if (payload !== undefined) {
        headers['Content-Type'] = 'application/json';
        headers['X-CSRF-TOKEN'] = csrfToken();
        options.body = JSON.stringify(payload);
    }

    const response = await fetch(url, options);
    const data = await response.json().catch(() => ({}));
    if (!response.ok || data?.ok === false) {
        throw new Error(data?.message || `Request failed with ${response.status}`);
    }
    return data;
}

async function postMultipartAction(url, formData) {
    const response = await fetch(url, {
        method: 'POST',
        credentials: 'same-origin',
        headers: {
            'Accept': 'application/json',
            'X-CSRF-TOKEN': csrfToken(),
        },
        body: formData,
    });
    const data = await response.json().catch(() => ({}));
    if (!response.ok || data?.ok === false) {
        throw new Error(data?.message || `Request failed with ${response.status}`);
    }
    return data;
}

async function getAction(url) {
    const response = await fetch(url, {
        method: 'GET',
        credentials: 'same-origin',
        headers: {
            'Accept': 'application/json',
            'X-CSRF-TOKEN': csrfToken(),
        },
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
    if (!initialMarketplace) {
        return {};
    }

    const products = Array.isArray(initialMarketplace.products) && initialMarketplace.products.length > 0
        ? initialMarketplace.products
        : [];

    return {
        ...initialMarketplace,
        products,
        cart: Array.isArray(initialMarketplace.cart) ? initialMarketplace.cart : [],
        wishlist: Array.isArray(initialMarketplace.wishlist) ? initialMarketplace.wishlist : [],
        orders: Array.isArray(initialMarketplace.orders) ? initialMarketplace.orders : [],
        buyerOps: initialMarketplace.buyerOps ?? {},
        escrowOrderDetail: initialMarketplace.buyerOps?.selectedEscrowOrder ?? initialMarketplace.sellerOps?.selectedEscrowOrder ?? null,
        chats: Array.isArray(initialMarketplace.chats) ? initialMarketplace.chats : [],
        sellerProducts: Array.isArray(initialMarketplace.sellerProducts) ? initialMarketplace.sellerProducts : [],
        coupons: Array.isArray(initialMarketplace.coupons) ? initialMarketplace.coupons : [],
        payoutRequests: Array.isArray(initialMarketplace.payoutRequests) ? initialMarketplace.payoutRequests : [],
        sellerOps: initialMarketplace.sellerOps ?? {},
        supportTickets: Array.isArray(initialMarketplace.supportTickets) ? initialMarketplace.supportTickets : [],
        business: initialMarketplace.business ?? {
            name: '',
            storeDescription: '',
            storeLogoUrl: '',
            bannerImageUrl: '',
            contactEmail: '',
            phone: '',
            address: '',
            addressLine: '',
            city: '',
            region: '',
            postalCode: '',
            country: '',
            verification: '',
        },
        user: initialMarketplace.user ?? { name: 'Guest buyer', email: '', role: 'buyer', city: '' },
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
        buyerOps: {},
        escrowOrderDetail: initialMarketplace?.buyerOps?.selectedEscrowOrder ?? initialMarketplace?.sellerOps?.selectedEscrowOrder ?? null,
        chats: [],
        sellerProducts: [],
        coupons: [],
        payoutRequests: [],
        sellerOps: {},
        business: {
            name: '',
            storeDescription: '',
            storeLogoUrl: '',
            bannerImageUrl: '',
            contactEmail: '',
            phone: '',
            address: '',
            addressLine: '',
            city: '',
            region: '',
            postalCode: '',
            country: '',
            verification: '',
        },
        supportTickets: [],
        categories: [],
        featuredVendor: null,
        hero: null,
        trustItems: [],
        metrics: {},
        ...mergeServerState(initialMarketplace),
    }));

    useEffect(() => {
        if (!notice) return undefined;
        const timer = window.setTimeout(() => setNotice(null), 4200);
        return () => window.clearTimeout(timer);
    }, [notice]);

    useEffect(() => {
        setState((current) => ({
            ...current,
            ...mergeServerState(initialMarketplace),
        }));
    }, [initialMarketplace]);

    const applyServerPayload = (payload) => {
        if (payload?.marketplace) {
            setState((current) => ({
                ...current,
                ...payload.marketplace,
                products: payload.marketplace.products?.length ? payload.marketplace.products : current.products,
                cart: Array.isArray(payload.marketplace.cart) ? payload.marketplace.cart : current.cart,
                wishlist: Array.isArray(payload.marketplace.wishlist) ? payload.marketplace.wishlist : current.wishlist,
                orders: Array.isArray(payload.marketplace.orders) ? payload.marketplace.orders : current.orders,
                buyerOps: payload.marketplace.buyerOps ?? current.buyerOps,
                escrowOrderDetail: payload.escrow_order_detail
                    ?? payload.marketplace.buyerOps?.selectedEscrowOrder
                    ?? payload.marketplace.sellerOps?.selectedEscrowOrder
                    ?? current.escrowOrderDetail,
                chats: Array.isArray(payload.marketplace.chats) ? payload.marketplace.chats : current.chats,
                sellerProducts: Array.isArray(payload.marketplace.sellerProducts) ? payload.marketplace.sellerProducts : current.sellerProducts,
                coupons: Array.isArray(payload.marketplace.coupons) ? payload.marketplace.coupons : current.coupons,
                payoutRequests: Array.isArray(payload.marketplace.payoutRequests) ? payload.marketplace.payoutRequests : current.payoutRequests,
                sellerOps: payload.marketplace.sellerOps ?? current.sellerOps,
                supportTickets: Array.isArray(payload.marketplace.supportTickets) ? payload.marketplace.supportTickets : current.supportTickets,
            }));
        }
    };

    const runAction = async (key, url, payload, successMessage) => {
        setPendingAction(key);
        try {
            const response = await postAction(url, payload);
            applyServerPayload(response);
            if (successMessage) setNotice({ type: 'success', body: successMessage });
            return response;
        } catch (error) {
            setNotice({ type: 'error', body: error.message || 'Something went wrong. Please try again.' });
            throw error;
        } finally {
            setPendingAction('');
        }
    };

    const runMultipartAction = async (key, url, formData, successMessage) => {
        setPendingAction(key);
        try {
            const response = await postMultipartAction(url, formData);
            applyServerPayload(response);
            if (successMessage) setNotice({ type: 'success', body: successMessage });
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

    const saveBuyerPaymentMethod = (payload, paymentMethodId = null) => {
        const url = paymentMethodId ? `/web/actions/buyer/payment-methods/${paymentMethodId}` : '/web/actions/buyer/payment-methods';
        return runAction(`buyer:payment-method:${paymentMethodId || 'new'}`, url, payload, paymentMethodId ? 'Payment method updated.' : 'Payment method saved.');
    };

    const setDefaultBuyerPaymentMethod = (paymentMethodId) => {
        return runAction(`buyer:payment-method:${paymentMethodId}:default`, `/web/actions/buyer/payment-methods/${paymentMethodId}/default`, {}, 'Default payment method updated.');
    };

    const deleteBuyerPaymentMethod = (paymentMethodId) => {
        return runAction(`buyer:payment-method:${paymentMethodId}:delete`, `/web/actions/buyer/payment-methods/${paymentMethodId}/delete`, {}, 'Payment method removed.');
    };

    const requestBuyerWalletTopUp = (walletId, payload) => {
        return runAction(`buyer:wallet:${walletId}:topup`, `/web/actions/buyer/wallets/${walletId}/top-up`, payload, 'Wallet top-up request submitted.');
    };

    const checkout = (paymentMethod, address, paymentReference = '') => {
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
            payment_reference: paymentReference,
            shipping_method: 'standard',
            shipping_address_line: address,
        }, paymentMethod === 'manual' ? 'Order submitted and manual payment captured.' : 'Order funded from wallet and escrow is active.');
    };

    const sendMessage = (payload) => {
        if (typeof payload === 'string') {
            const trimmed = payload.trim();
            if (!trimmed) return;
            return runAction('support', '/web/actions/support/messages', { body: trimmed }, 'Message sent.');
        }
        const body = String(payload?.body || '').trim();
        const attachmentUrl = String(payload?.attachment_url || '').trim();
        if (!body && !attachmentUrl) return;
        return runAction('support', '/web/actions/support/messages', { ...payload, body }, 'Message sent.');
    };

    const refreshEscrowOrderDetail = async (orderId, quiet = true) => {
        const key = `escrow:${orderId}:refresh`;
        if (!quiet) setPendingAction(key);
        try {
            const response = await getAction(`/web/actions/orders/${orderId}/escrow`);
            if (response?.detail) {
                setState((current) => ({ ...current, escrowOrderDetail: response.detail }));
            }
            return response?.detail ?? null;
        } catch (error) {
            if (!quiet) setNotice({ type: 'error', body: error.message || 'Unable to refresh order details.' });
            throw error;
        } finally {
            if (!quiet) setPendingAction('');
        }
    };

    const releaseEscrowFunds = (orderId) => {
        return runAction(`escrow:${orderId}:release`, `/web/actions/orders/${orderId}/escrow/release`, {}, 'Funds released successfully.');
    };

    const openOrderDispute = (orderId, reasonCode = 'delivery_issue') => {
        return runAction(`escrow:${orderId}:dispute`, `/web/actions/orders/${orderId}/escrow/dispute`, { reason_code: reasonCode }, 'Dispute opened successfully.');
    };

    const submitSellerDelivery = (orderId, payload) => {
        const formData = new FormData();
        formData.append('delivery_message', payload.delivery_message || '');
        formData.append('external_delivery_url', payload.external_delivery_url || '');
        formData.append('delivery_version', payload.delivery_version || '');
        (payload.files || []).forEach((file) => formData.append('files[]', file));

        return runMultipartAction(`escrow:${orderId}:delivery`, `/web/actions/orders/${orderId}/escrow/delivery`, formData, 'Delivery submitted successfully.');
    };

    const sendEscrowMessage = (orderId, payload) => {
        const formData = new FormData();
        formData.append('body', payload.body || '');
        formData.append('artifact_type', payload.artifact_type || '');
        (payload.attachments || []).forEach((file) => formData.append('attachments[]', file));

        return runMultipartAction(`escrow:${orderId}:message`, `/web/actions/orders/${orderId}/escrow/messages`, formData, 'Secure message sent.');
    };

    const markEscrowMessagesRead = (orderId) => {
        return runAction(`escrow:${orderId}:read`, `/web/actions/orders/${orderId}/escrow/messages/read`, {}, '');
    };

    const mergeIncomingEscrowMessage = (threadId, message) => {
        const normalizedThreadId = Number(threadId || 0);
        const messageId = Number(message?.id || 0);
        if (!normalizedThreadId || !messageId) {
            return;
        }

        setState((current) => {
            const detail = current.escrowOrderDetail;
            if (!detail || Number(detail.chat?.thread_id || 0) !== normalizedThreadId) {
                return current;
            }

            const existing = Array.isArray(detail.messages) ? detail.messages : [];
            if (existing.some((item) => Number(item.id) === messageId)) {
                return current;
            }

            const viewerId = Number(current.user?.id || 0);
            const nextMessage = {
                ...message,
                from_me: Number(message?.sender_user_id || 0) === viewerId,
            };

            return {
                ...current,
                escrowOrderDetail: {
                    ...detail,
                    messages: [...existing, nextMessage].sort((left, right) => Number(left.id || 0) - Number(right.id || 0)),
                },
            };
        });
    };

    const addSellerProduct = (payload) => {
        return runAction('seller:product', '/web/actions/seller/products', payload, 'Listing published.');
    };

    const uploadSellerMedia = async (file, purpose = 'product_image') => {
        if (!file) return null;
        setPendingAction(`upload:${purpose}`);
        const formData = new FormData();
        formData.append('file', file);
        formData.append('purpose', purpose);
        try {
            const response = await fetch('/web/actions/seller/media/upload', {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': csrfToken(),
                },
                body: formData,
            });
            const data = await response.json().catch(() => ({}));
            if (!response.ok || data?.ok === false) {
                throw new Error(data?.message || `Upload failed with ${response.status}`);
            }
            setNotice({ type: 'success', body: 'Media uploaded.' });
            return data.media;
        } catch (error) {
            setNotice({ type: 'error', body: error.message || 'Upload failed. Please try again.' });
            throw error;
        } finally {
            setPendingAction('');
        }
    };

    const saveSellerProduct = (payload, productId = null) => {
        const url = productId ? `/web/actions/seller/products/${productId}` : '/web/actions/seller/products';
        return runAction('seller:product', url, payload, productId ? 'Listing updated.' : 'Listing saved.');
    };

    const duplicateSellerProduct = (productId) => {
        return runAction(`seller:duplicate:${productId}`, `/web/actions/seller/products/${productId}/duplicate`, {}, 'Listing duplicated as draft.');
    };

    const bulkSellerProducts = (ids, action) => {
        return runAction('seller:bulk', '/web/actions/seller/products/bulk', { ids, action }, 'Bulk action completed.');
    };

    const deleteSellerProduct = (productId) => {
        return runAction(`seller:product:${productId}:delete`, '/web/actions/seller/products/bulk', { ids: [productId], action: 'delete' }, 'Product deleted successfully.');
    };

    const adjustStock = (productId, delta) => {
        return runAction(`stock:${productId}`, '/web/actions/seller/inventory/adjust', { product_id: productId, delta }, 'Inventory updated.');
    };

    const saveWarehouse = (payload) => {
        return runAction('seller:warehouse', '/web/actions/seller/warehouses', payload, 'Warehouse saved.');
    };

    const deleteWarehouse = (warehouseId) => {
        return runAction(`seller:warehouse:${warehouseId}:delete`, `/web/actions/seller/warehouses/${warehouseId}/delete`, {}, 'Warehouse deleted.');
    };

    const saveShippingSettings = (payload) => {
        return runAction('seller:shipping', '/web/actions/seller/shipping-settings', payload, 'Shipping settings saved.');
    };

    const requestTopUp = (payload) => {
        return runAction('seller:topup', '/web/actions/seller/top-ups', payload, 'Top-up request submitted.');
    };

    const uploadKycDocument = async (file, docType) => {
        if (!file || !docType) return null;
        setPendingAction(`kyc-upload:${docType}`);
        const formData = new FormData();
        formData.append('file', file);
        formData.append('doc_type', docType);
        try {
            const response = await fetch('/web/actions/seller/kyc/documents', {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': csrfToken(),
                },
                body: formData,
            });
            const data = await response.json().catch(() => ({}));
            if (!response.ok || data?.ok === false) {
                throw new Error(data?.message || `Upload failed with ${response.status}`);
            }
            applyServerPayload(data);
            setNotice({ type: 'success', body: 'KYC document uploaded.' });
            return data.document;
        } catch (error) {
            setNotice({ type: 'error', body: error.message || 'Upload failed.' });
            throw error;
        } finally {
            setPendingAction('');
        }
    };

    const saveKycDraft = (payload) => runAction('kyc:save', '/web/actions/seller/kyc/save', payload, 'KYC draft saved.');
    const submitKyc = (payload) => runAction('kyc:submit', '/web/actions/seller/kyc/submit', payload, 'KYC submitted.');

    const saveCoupon = (coupon, couponId = null) => {
        const url = couponId ? `/web/actions/seller/coupons/${couponId}` : '/web/actions/seller/coupons';
        return runAction('seller:coupon', url, coupon, couponId ? 'Offer updated.' : 'Offer created.');
    };

    const toggleCoupon = (couponId) => {
        return runAction(`seller:coupon:${couponId}:toggle`, `/web/actions/seller/coupons/${couponId}/toggle`, {}, 'Offer status updated.');
    };

    const deleteCoupon = (couponId) => {
        return runAction(`seller:coupon:${couponId}:delete`, `/web/actions/seller/coupons/${couponId}/delete`, {}, 'Offer deleted.');
    };

    const requestPayout = (amount) => {
        return runAction('seller:payout', '/web/actions/seller/payouts', { amount: Number(amount || 0) }, 'Payout requested.');
    };

    const savePayoutMethod = (payload) => {
        return runAction('seller:payout-method', '/web/actions/seller/payout-methods', payload, 'Payout method saved.');
    };

    const deletePayoutMethod = (methodId) => {
        return runAction(`seller:payout-method:${methodId}`, `/web/actions/seller/payout-methods/${methodId}/delete`, {}, 'Payout method removed.');
    };

    const saveProfile = (profile) => {
        return runAction('profile', '/web/actions/profile', profile, 'Profile saved.');
    };

    const updateBuyerPassword = (payload) => {
        return runAction('buyer:password', '/web/actions/buyer/password', payload, 'Password updated.');
    };

    const uploadBuyerProfilePhoto = async (file) => {
        if (!file) return null;
        setPendingAction('buyer:profile-photo');
        const formData = new FormData();
        formData.append('file', file);
        try {
            const response = await fetch('/web/actions/buyer/profile-photo', {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    Accept: 'application/json',
                    'X-CSRF-TOKEN': csrfToken(),
                },
                body: formData,
            });
            const data = await response.json().catch(() => ({}));
            if (!response.ok || data?.ok === false) {
                throw new Error(data?.message || `Upload failed with ${response.status}`);
            }
            applyServerPayload(data);
            setNotice({ type: 'success', body: 'Profile photo updated.' });
            return data.media;
        } catch (error) {
            setNotice({ type: 'error', body: error.message || 'Upload failed. Please try again.' });
            throw error;
        } finally {
            setPendingAction('');
        }
    };

    const updateBuyerNotificationPreferences = (payload) => {
        return runAction('buyer:notification-preferences', '/web/actions/buyer/notification-preferences', payload, 'Notification preferences updated.');
    };

    const saveBuyerAddress = (payload, addressId = null) => {
        const url = addressId ? `/web/actions/buyer/addresses/${addressId}` : '/web/actions/buyer/addresses';
        return runAction(`buyer:address:${addressId || 'new'}`, url, payload, addressId ? 'Address updated.' : 'Address saved.');
    };

    const deleteBuyerAddress = (addressId) => {
        return runAction(`buyer:address:${addressId}:delete`, `/web/actions/buyer/addresses/${addressId}/delete`, {}, 'Address removed.');
    };

    const saveBusiness = (business) => {
        return runAction('business', '/web/actions/business', {
            name: business.name,
            store_description: business.storeDescription,
            store_logo_url: business.storeLogoUrl,
            banner_image_url: business.bannerImageUrl,
            contact_email: business.contactEmail,
            phone: business.phone,
            address: business.address,
            address_line: business.addressLine,
            city: business.city,
            region: business.region,
            postal_code: business.postalCode,
            country: business.country,
            verification: business.verification,
        }, 'Business profile saved.');
    };

    const updateNotificationRoleState = (role, updater) => {
        const bucketKey = role === 'seller' ? 'sellerOps' : 'buyerOps';

        setState((current) => ({
            ...current,
            [bucketKey]: updater(current[bucketKey] ?? {}),
        }));
    };

    const applyNotificationCollection = (role, payload) => {
        updateNotificationRoleState(role, (bucket) => ({
            ...bucket,
            notifications: Array.isArray(payload?.notifications) ? payload.notifications : (bucket.notifications || []),
            unreadNotificationCount: Number.isFinite(Number(payload?.unread_count))
                ? Number(payload.unread_count)
                : (bucket.unreadNotificationCount ?? 0),
            notificationsPagination: payload?.pagination ?? bucket.notificationsPagination ?? null,
        }));
    };

    const fetchNotifications = async (role, { page = 1, perPage = 8 } = {}) => {
        const query = new URLSearchParams({
            role,
            page: String(page),
            per_page: String(perPage),
        });
        const response = await requestJson(`/web/actions/notifications?${query.toString()}`);
        applyNotificationCollection(role, response);

        return response;
    };

    const markNotificationRead = async (role, notificationId) => {
        const response = await postAction(`/web/actions/notifications/${notificationId}/read`, { role });

        updateNotificationRoleState(role, (bucket) => ({
            ...bucket,
            notifications: (bucket.notifications || []).map((item) => (
                Number(item.id) === Number(notificationId)
                    ? { ...item, ...(response.notification || {}), read: true, is_read: true }
                    : item
            )),
            unreadNotificationCount: Number.isFinite(Number(response?.unread_count))
                ? Number(response.unread_count)
                : Math.max(0, Number(bucket.unreadNotificationCount || 0) - 1),
        }));

        return response;
    };

    const markAllNotificationsRead = async (role) => {
        const response = await postAction('/web/actions/notifications/mark-all-read', { role });

        updateNotificationRoleState(role, (bucket) => ({
            ...bucket,
            notifications: (bucket.notifications || []).map((item) => ({ ...item, read: true, is_read: true })),
            unreadNotificationCount: Number(response?.unread_count ?? 0),
        }));

        return response;
    };

    const deleteNotification = async (role, notificationId) => {
        const response = await postAction(`/web/actions/notifications/${notificationId}/delete`, { role });

        updateNotificationRoleState(role, (bucket) => ({
            ...bucket,
            notifications: (bucket.notifications || []).filter((item) => Number(item.id) !== Number(notificationId)),
            unreadNotificationCount: Number.isFinite(Number(response?.unread_count))
                ? Number(response.unread_count)
                : (bucket.unreadNotificationCount ?? 0),
        }));

        return response;
    };

    const clearNotifications = async (role) => {
        const response = await postAction('/web/actions/notifications/clear-all', { role });

        updateNotificationRoleState(role, (bucket) => ({
            ...bucket,
            notifications: [],
            unreadNotificationCount: Number(response?.unread_count ?? 0),
        }));

        return response;
    };

    const pushIncomingNotification = (role, notification, unreadCount = null) => {
        if (!notification) {
            return;
        }

        updateNotificationRoleState(role, (bucket) => {
            const notifications = [notification, ...(bucket.notifications || []).filter((item) => Number(item.id) !== Number(notification.id))].slice(0, 20);

            return {
                ...bucket,
                notifications,
                unreadNotificationCount: Number.isFinite(Number(unreadCount))
                    ? Number(unreadCount)
                    : notifications.filter((item) => !(item.is_read ?? item.read)).length,
            };
        });
    };

    const applyNotificationEvent = (payload) => {
        const role = payload?.role === 'seller' ? 'seller' : 'buyer';
        const unreadCount = Number.isFinite(Number(payload?.unread_count)) ? Number(payload.unread_count) : null;

        updateNotificationRoleState(role, (bucket) => {
            const notifications = [...(bucket.notifications || [])];

            if (payload?.action === 'read') {
                const targetId = Number(payload.notification_id || payload.notification?.id);

                return {
                    ...bucket,
                    notifications: notifications.map((item) => Number(item.id) === targetId ? { ...item, ...(payload.notification || {}), read: true, is_read: true } : item),
                    unreadNotificationCount: unreadCount ?? bucket.unreadNotificationCount ?? 0,
                };
            }

            if (payload?.action === 'read_all') {
                return {
                    ...bucket,
                    notifications: notifications.map((item) => ({ ...item, read: true, is_read: true })),
                    unreadNotificationCount: unreadCount ?? 0,
                };
            }

            if (payload?.action === 'deleted') {
                return {
                    ...bucket,
                    notifications: notifications.filter((item) => Number(item.id) !== Number(payload.notification_id)),
                    unreadNotificationCount: unreadCount ?? bucket.unreadNotificationCount ?? 0,
                };
            }

            if (payload?.action === 'cleared') {
                return {
                    ...bucket,
                    notifications: [],
                    unreadNotificationCount: unreadCount ?? 0,
                };
            }

            return bucket;
        });
    };

    return { state, setState, pendingAction, notice, addToCart, updateCart, removeCart, toggleWishlist, saveBuyerPaymentMethod, setDefaultBuyerPaymentMethod, deleteBuyerPaymentMethod, requestBuyerWalletTopUp, checkout, sendMessage, refreshEscrowOrderDetail, releaseEscrowFunds, openOrderDispute, submitSellerDelivery, sendEscrowMessage, markEscrowMessagesRead, mergeIncomingEscrowMessage, addSellerProduct, uploadSellerMedia, saveSellerProduct, duplicateSellerProduct, bulkSellerProducts, deleteSellerProduct, adjustStock, saveWarehouse, deleteWarehouse, saveShippingSettings, requestTopUp, uploadKycDocument, saveKycDraft, submitKyc, saveCoupon, toggleCoupon, deleteCoupon, requestPayout, savePayoutMethod, deletePayoutMethod, saveProfile, updateBuyerPassword, uploadBuyerProfilePhoto, updateBuyerNotificationPreferences, saveBuyerAddress, deleteBuyerAddress, saveBusiness, fetchNotifications, markNotificationRead, markAllNotificationsRead, deleteNotification, clearNotifications, pushIncomingNotification, applyNotificationEvent };
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

function AppShell({
    mode,
    view,
    user,
    cartCount,
    wishlistCount,
    categories = [],
    notice,
    notifications = [],
    unreadNotificationCount = 0,
    onRefreshNotifications,
    onMarkNotificationRead,
    onMarkAllNotificationsRead,
    onDeleteNotification,
    onClearNotifications,
    children,
}) {
    const [open, setOpen] = useState(false);
    const [categoryMenuOpen, setCategoryMenuOpen] = useState(false);
    const [activeMegaCategoryId, setActiveMegaCategoryId] = useState(null);
    const [activeMegaLeft, setActiveMegaLeft] = useState(0);
    const [accountMenuOpen, setAccountMenuOpen] = useState(false);
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
                setAccountMenuOpen(false);
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
        ['/dashboard', 'Dashboard', LayoutDashboard],
        ['/marketplace', 'Marketplace', PackageSearch],
        ['/cart', `Cart ${cartCount ? `(${cartCount})` : ''}`, ShoppingCart],
        ['/orders', 'Orders', Truck],
        ['/wallet', 'Wallet', WalletCards],
        ['/wishlist', `Wishlist ${wishlistCount ? `(${wishlistCount})` : ''}`, Heart],
        ['/notifications', 'Alerts', Bell],
        ['/support', 'Support', MessageSquareText],
        ['/profile', 'Profile', User],
    ];
    const sellerLinks = [
        ['/seller/dashboard', 'Dashboard', LayoutDashboard],
        ['/seller/products', 'Listings', Package],
        ['/seller/inventory', 'Inventory', Boxes],
        ['/seller/warehouses', 'Warehouse', Building2],
        ['/seller/orders', 'Orders', Truck],
        ['/seller/shipping-settings', 'Shipping', PackageCheck],
        ['/seller/payouts', 'Payouts', WalletCards],
        ['/seller/reviews', 'Reviews', Star],
        ['/seller/notifications', 'Alerts', Bell],
        ['/seller/menu', 'More', Menu],
        ['/seller/business', 'Store', BriefcaseBusiness],
        ['/seller/support', 'Support', MessageSquareText],
    ];
    const isAuthenticated = Boolean(user?.isAuthenticated);
    const isSellerAccount = user?.role === 'seller';
    const accountHref = isAuthenticated ? (isSellerAccount ? '/seller/dashboard' : '/profile') : '/login?panel=buyer';
    const accountTitle = isSellerAccount ? 'Seller workspace' : 'Account & Vault';
    const notificationsHref = isSellerAccount ? '/seller/notifications' : '/notifications';
    const accountMenuLinks = isSellerAccount
        ? [
            ['/dashboard', 'Switch to buyer', ShoppingBag],
            ['/seller/dashboard', 'Dashboard', LayoutDashboard],
            ['/seller/business', 'Profile & store', User],
            ['/seller/products', 'Listings', Package],
            ['/seller/warehouses', 'Warehouse', Building2],
            ['/seller/earnings', 'Earnings', BarChart3],
        ]
        : [
            ['/dashboard', 'Dashboard', LayoutDashboard],
            ['/profile', 'Profile', User],
            ['/orders', 'Orders', Truck],
            ['/wallet', 'Wallet', WalletCards],
            ...(user?.hasSellerProfile ? [['/seller/dashboard', 'Switch to seller', Store]] : []),
        ];
    const links = mode === 'seller' ? sellerLinks : buyerLinks;
    const rootNavCategories = categories.filter(isRootCategory);
    const navCategories = rootNavCategories.length ? rootNavCategories : categories;
    const categoryLinks = [
        { label: 'All Categories', href: '/marketplace' },
        ...navCategories.map((category) => ({ label: category.name, href: `/marketplace?category=${encodeURIComponent(category.name)}` })),
    ];
    const productTypeLinks = [
        { label: 'Digital Products', href: '/marketplace?type=Digital%20product', icon: Download },
        { label: 'Instant Delivery', href: '/marketplace?type=Instant%20delivery', icon: Zap },
    ];
    const topCategoryLimit = 6;
    const visibleTopCategories = navCategories.slice(0, topCategoryLimit);
    const overflowCategories = navCategories.slice(topCategoryLimit);
    const categoryChildren = (category) => categories.filter((item) => Number(item.parent_id) === Number(category.id));
    const categoryProductCount = (category) => Number(category.products_count ?? category.product_count ?? 0);
    const categoryCountText = (category) => {
        const count = categoryProductCount(category);
        return count > 0 ? ` (${count})` : '';
    };
    const activeMegaCategory = navCategories.find((category) => Number(category.id) === Number(activeMegaCategoryId));
    const activeMegaChildren = activeMegaCategory ? categoryChildren(activeMegaCategory) : [];
    const toggleAccountMenu = () => {
        setCategoryMenuOpen(false);
        setActiveMegaCategoryId(null);
        setAccountMenuOpen((value) => !value);
    };
    const openCategoryMenu = () => {
        setAccountMenuOpen(false);
        setCategoryMenuOpen((value) => !value);
        setActiveMegaCategoryId(null);
    };
    const showCategoryMenu = () => {
        setAccountMenuOpen(false);
        setCategoryMenuOpen(true);
        setActiveMegaCategoryId(null);
    };
    const openMegaCategory = (category, event = null) => {
        setAccountMenuOpen(false);
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

    useEffect(() => {
        if (!isAuthenticated) {
            setAccountMenuOpen(false);
        }
    }, [isAuthenticated]);

    return (
        <div className="min-h-screen bg-[#f6f8fb] text-slate-950">
            <Head title="Marketplace" />
            <header className="sticky top-0 z-40 border-b border-slate-200 bg-white shadow-sm">
                <div className="bg-slate-950 text-slate-200">
                    <div className="mx-auto flex h-7 max-w-[1480px] items-center justify-between px-4 text-[11px] sm:px-6 lg:px-8">
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
                <div className="mx-auto flex min-h-[58px] max-w-[1480px] items-center justify-between gap-3 px-4 sm:px-6 lg:min-h-[68px] lg:gap-4 lg:px-8">
                    <Link href="/" className="flex shrink-0 items-center gap-2.5">
                        <div className="flex size-9 items-center justify-center rounded-lg bg-indigo-600 text-white shadow-[0_16px_34px_-24px_rgba(79,70,229,0.9)] lg:size-10">
                            <ShoppingBag className="size-5 lg:size-6" />
                        </div>
                        <p className="text-lg font-extrabold tracking-tight text-slate-950 lg:text-xl">Sellova</p>
                    </Link>

                    <form onSubmit={submitSearch} className="hidden h-11 min-w-0 flex-1 overflow-hidden rounded-lg border border-slate-200 bg-slate-50 shadow-[inset_0_1px_0_rgba(255,255,255,0.8)] lg:flex">
                        <Link href="/marketplace" className="flex items-center gap-1.5 border-r border-slate-200 bg-slate-100 px-4 text-xs font-bold text-slate-700 transition hover:bg-slate-200">
                            All <ChevronRight className="size-3.5 rotate-90" />
                        </Link>
                        <div className="relative min-w-0 flex-1">
                            <Search className="absolute left-4 top-1/2 size-4 -translate-y-1/2 text-slate-400" />
                            <input value={searchTerm} onChange={(event) => setSearchTerm(event.target.value)} className="h-full w-full border-0 bg-transparent pl-11 pr-4 text-sm font-medium text-slate-700 placeholder:text-slate-400 focus:ring-0" placeholder="Search products, sellers, brands, tags..." aria-label="Search marketplace" />
                        </div>
                        <button type="submit" className="flex w-14 items-center justify-center bg-indigo-600 text-white transition hover:bg-indigo-700" aria-label="Submit search">
                            <Search className="size-5" />
                        </button>
                    </form>

                    <div className="hidden items-center gap-3 xl:flex">
                        {!isSellerAccount ? (
                            <Link href="/seller/dashboard" className="inline-flex h-9 items-center gap-2 rounded-lg border border-slate-200 px-3 text-xs font-extrabold text-slate-700 transition hover:border-indigo-200 hover:bg-indigo-50 hover:text-indigo-700">
                                <Store className="size-4" /> Sell
                            </Link>
                        ) : null}
                        {isAuthenticated ? (
                            <NotificationBell
                                notifications={notifications}
                                unreadCount={unreadNotificationCount}
                                onRefresh={onRefreshNotifications}
                                onMarkRead={(notification) => onMarkNotificationRead?.(notification?.id)}
                                onMarkAllRead={onMarkAllNotificationsRead}
                                onDelete={(notification) => onDeleteNotification?.(notification?.id)}
                                onClearAll={onClearNotifications}
                                viewAllHref={notificationsHref}
                            />
                        ) : null}
                        {isAuthenticated ? (
                            <div className="relative">
                                <button
                                    type="button"
                                    onClick={toggleAccountMenu}
                                    className="group flex items-center gap-2 rounded-lg px-2 py-1.5 text-left transition hover:bg-slate-50"
                                    aria-expanded={accountMenuOpen}
                                    aria-controls="header-account-menu"
                                >
                                    <User className="size-4 text-slate-400 group-hover:text-indigo-600" />
                                    <span>
                                        <span className="block text-[11px] font-semibold leading-none text-slate-500">Signed in</span>
                                        <span className="mt-1 flex items-center gap-1 text-xs font-extrabold leading-none text-slate-950 group-hover:text-indigo-600">
                                            {user?.name || accountTitle}
                                            <ChevronRight className={cn('size-3 rotate-90 transition', accountMenuOpen && '-rotate-90')} />
                                        </span>
                                    </span>
                                </button>
                                {accountMenuOpen ? (
                                    <div id="header-account-menu" className="absolute right-0 top-[calc(100%+10px)] z-50 w-56 overflow-hidden rounded-xl border border-slate-200 bg-white p-2 shadow-[0_22px_60px_-28px_rgba(15,23,42,0.45)]">
                                        <div className="border-b border-slate-100 px-3 py-2">
                                            <p className="line-clamp-1 text-sm font-extrabold text-slate-950">{user?.name || accountTitle}</p>
                                            <p className="mt-1 line-clamp-1 text-xs font-semibold text-slate-500">{user?.email || (isSellerAccount ? 'Seller account' : 'Buyer account')}</p>
                                        </div>
                                        <div className="py-1">
                                            {accountMenuLinks.map(([href, label, Icon]) => (
                                                <Link
                                                    key={href}
                                                    href={href}
                                                    onClick={() => setAccountMenuOpen(false)}
                                                    className="flex items-center gap-2 rounded-lg px-3 py-2 text-sm font-bold text-slate-700 transition hover:bg-indigo-50 hover:text-indigo-700"
                                                >
                                                    <Icon className="size-4" />{label}
                                                </Link>
                                            ))}
                                        </div>
                                        <Link
                                            href="/logout"
                                            method="post"
                                            as="button"
                                            onClick={() => setAccountMenuOpen(false)}
                                            className="flex w-full items-center gap-2 rounded-lg px-3 py-2 text-left text-sm font-extrabold text-rose-600 transition hover:bg-rose-50"
                                        >
                                            <X className="size-4" />Logout
                                        </Link>
                                    </div>
                                ) : null}
                            </div>
                        ) : (
                            <Link href={accountHref} className="group flex items-center gap-2 rounded-lg px-2 py-1.5 transition hover:bg-slate-50">
                                <User className="size-4 text-slate-400 group-hover:text-indigo-600" />
                                <span>
                                    <span className="block text-[11px] font-semibold leading-none text-slate-500">Hello, Sign in</span>
                                    <span className="mt-1 flex items-center gap-1 text-xs font-extrabold leading-none text-slate-950 group-hover:text-indigo-600">{accountTitle} <ChevronRight className="size-3 rotate-90" /></span>
                                </span>
                            </Link>
                        )}
                        <Link href="/orders" className="group rounded-lg px-2 py-1.5 transition hover:bg-slate-50">
                            <p className="text-[11px] font-semibold leading-none text-slate-500">Returns &</p>
                            <p className="mt-1 text-xs font-extrabold leading-none text-slate-950 group-hover:text-indigo-600">Escrows</p>
                        </Link>
                        <Link href="/cart" className="relative flex items-center gap-2 rounded-lg px-2 py-1.5 text-sm font-extrabold text-slate-950 transition hover:bg-slate-50 hover:text-indigo-600">
                            <span className="relative"><ShoppingCart className="size-6" />{cartCount ? <span className="absolute -right-2 -top-2 flex size-4 items-center justify-center rounded-full bg-indigo-600 text-[10px] text-white">{cartCount}</span> : null}</span>
                            Cart
                        </Link>
                    </div>

                    <div className="flex items-center gap-2 xl:hidden">
                        {isAuthenticated ? (
                            <NotificationBell
                                notifications={notifications}
                                unreadCount={unreadNotificationCount}
                                onRefresh={onRefreshNotifications}
                                onMarkRead={(notification) => onMarkNotificationRead?.(notification?.id)}
                                onMarkAllRead={onMarkAllNotificationsRead}
                                onDelete={(notification) => onDeleteNotification?.(notification?.id)}
                                onClearAll={onClearNotifications}
                                viewAllHref={notificationsHref}
                            />
                        ) : null}
                        <Button asChild variant={mode === 'seller' ? 'outline' : 'default'} size="sm">
                            <Link href={mode === 'seller' ? '/marketplace' : (isSellerAccount ? '/seller/dashboard' : '/login?panel=seller')}>{mode === 'seller' ? 'Buyer site' : 'Seller panel'}</Link>
                        </Button>
                        <Button variant="outline" size="icon" onClick={() => setOpen(true)} aria-label="Open menu">
                            <Menu className="size-4" />
                        </Button>
                    </div>
                </div>
                <div className="border-t border-slate-100 px-4 pb-2 lg:hidden">
                    <div className="relative">
                        <form onSubmit={submitSearch}>
                            <Search className="absolute left-3 top-1/2 size-4 -translate-y-1/2 text-slate-400" />
                            <input value={searchTerm} onChange={(event) => setSearchTerm(event.target.value)} className="h-10 w-full rounded-lg border border-slate-200 bg-slate-50 pl-10 pr-4 text-sm font-medium text-slate-700 placeholder:text-slate-500" placeholder="Search products, services, deals..." aria-label="Search marketplace" />
                        </form>
                    </div>
                    <div className="-mx-4 mt-2 flex gap-2 overflow-x-auto px-4 pb-1">
                        {[categoryLinks[0], ...productTypeLinks, ...categoryLinks.slice(1)].filter(Boolean).slice(0, 8).map((item, index) => (
                            <Link key={item.label} href={item.href} className={cn('shrink-0 rounded-full border px-4 py-2 text-xs font-bold', index === 0 ? 'border-slate-950 bg-slate-950 text-white' : 'border-slate-200 bg-white text-slate-700')}>
                                {item.label.replace('All Categories', 'All')}
                            </Link>
                        ))}
                    </div>
                </div>
                <div className="hidden bg-slate-950 text-white lg:block">
                    <div className="mx-auto flex h-11 max-w-[1480px] items-center gap-7 overflow-x-auto px-4 text-sm font-bold [scrollbar-width:none] sm:px-6 lg:px-8 [&::-webkit-scrollbar]:hidden">
                        {mode === 'seller' ? (
                            <>
                                <Link href="/seller/dashboard" className="flex shrink-0 items-center gap-2 whitespace-nowrap text-emerald-300">
                                    <Store className="size-4" />Seller workspace
                                </Link>
                                {sellerLinks.map(([href, label, Icon]) => (
                                    <Link
                                        key={href}
                                        href={href}
                                        className={cn(
                                            'flex h-full shrink-0 items-center gap-2 whitespace-nowrap border-b-2 transition',
                                            viewMatches(href, view, mode) ? 'border-emerald-300 text-white' : 'border-transparent text-slate-300 hover:text-emerald-300',
                                        )}
                                    >
                                        <Icon className="size-4" />{label}
                                    </Link>
                                ))}
                                <Link href="/marketplace" className="ml-auto flex shrink-0 items-center gap-2 whitespace-nowrap text-slate-300 transition hover:text-white">
                                    Buyer marketplace <ArrowRight className="size-4" />
                                </Link>
                            </>
                        ) : (
                            <>
                                <div className="flex h-full shrink-0 items-center">
                                    <button
                                        type="button"
                                        onClick={openCategoryMenu}
                                        className="flex items-center gap-2 whitespace-nowrap text-slate-100 transition hover:text-emerald-300"
                                        aria-expanded={categoryMenuOpen}
                                        aria-controls="desktop-category-menu"
                                    >
                                        <Menu className="size-5" />All Categories
                                    </button>
                                </div>
                                {productTypeLinks.map(({ label, href, icon: Icon }) => (
                                    <Link key={label} href={href} className="flex shrink-0 items-center gap-2 whitespace-nowrap text-slate-100 transition hover:text-emerald-300">
                                        <Icon className="size-4" />{label}
                                    </Link>
                                ))}
                                {visibleTopCategories.map((category) => (
                                    <div
                                        key={category.id || category.name}
                                        className="flex h-full shrink-0 items-center"
                                        onMouseEnter={(event) => openMegaCategory(category, event)}
                                    >
                                        <button
                                            type="button"
                                            onClick={(event) => openMegaCategory(category, event)}
                                            className={cn(
                                                'flex shrink-0 items-center gap-2 whitespace-nowrap text-slate-100 transition hover:text-emerald-300',
                                                Number(activeMegaCategoryId) === Number(category.id) && 'text-emerald-300',
                                            )}
                                            aria-expanded={Number(activeMegaCategoryId) === Number(category.id)}
                                            aria-controls="category-subcategory-menu"
                                        >
                                            {category.name}
                                        </button>
                                    </div>
                                ))}
                                {overflowCategories.length ? (
                                    <div className="flex h-full shrink-0 items-center" onMouseEnter={showCategoryMenu}>
                                <button
                                    type="button"
                                    onClick={showCategoryMenu}
                                    className="flex items-center gap-1 whitespace-nowrap text-slate-100 transition hover:text-emerald-300"
                                    aria-expanded={categoryMenuOpen}
                                    aria-controls="desktop-category-menu"
                                    title={`${overflowCategories.length} more categories`}
                                >
                                    &gt;&gt;
                                </button>
                                    </div>
                                ) : null}
                            </>
                        )}
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
    const isInstantDelivery = Boolean(product?.isInstantDelivery);
    if (type === 'physical') {
        return null;
    }
    if (type === 'digital' && isInstantDelivery) {
        return { label: 'Instant', icon: Zap, variant: 'success', className: 'text-emerald-700' };
    }
    if (type === 'digital') {
        return { label: 'Digital', icon: Download, variant: 'success', className: 'text-cyan-700' };
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

function RatingSummary({ product, className = '', starClassName = 'size-3' }) {
    const count = asNumber(product?.reviewCount);
    const rating = Math.max(0, Math.min(5, asNumber(product?.rating)));
    const roundedRating = Math.round(rating);
    return (
        <span className={cn('inline-flex items-center gap-1 text-xs font-semibold text-slate-400', className)} aria-label={`${rating.toFixed(1)} rating from ${count} reviews`}>
            <span className="inline-flex items-center gap-0.5 text-amber-400">
                {Array.from({ length: 5 }).map((_, index) => (
                    <Star key={index} className={cn(starClassName, index < roundedRating ? 'fill-amber-400 text-amber-400' : 'fill-slate-200 text-slate-200')} />
                ))}
            </span>
            <span>({count})</span>
        </span>
    );
}

function RatingPill({ product, className = '' }) {
    const rating = asNumber(product?.rating);
    if (rating <= 0) {
        return null;
    }

    return (
        <span className={cn('inline-flex items-center gap-1 text-xs font-extrabold text-slate-700', className)} aria-label={`${rating.toFixed(1)} rating`}>
            <Star className="size-3.5 fill-amber-400 text-amber-400" />
            {rating.toFixed(1)}
        </span>
    );
}

function productTaxonomyLabel(product) {
    return product?.subcategory || product?.category || product?.seller || 'Marketplace';
}

function productCardTrustSlides(product) {
    const type = String(product?.productType || '').toLowerCase();
    const isInstantDelivery = Boolean(product?.isInstantDelivery);
    const slides = [];

    if (type === 'digital' && isInstantDelivery) {
        slides.push({ label: 'Instant Delivery', icon: Zap, className: 'bg-blue-50 text-blue-700' });
    } else if (type === 'digital') {
        slides.push({ label: 'Digital Delivery', icon: Download, className: 'bg-cyan-50 text-cyan-700' });
    } else if (type === 'physical') {
        slides.push({ label: 'Tracked Delivery', icon: Truck, className: 'bg-emerald-50 text-emerald-700' });
    } else if (type === 'service') {
        slides.push({ label: 'Seller Managed', icon: BriefcaseBusiness, className: 'bg-indigo-50 text-indigo-700' });
    }

    slides.push({ label: 'Secure Transfer', icon: ShieldCheck, className: 'bg-purple-50 text-purple-700' });

    if (product?.verified) {
        slides.push({ label: 'Safe', icon: BadgeCheck, className: 'bg-emerald-50 text-emerald-700' });
    } else {
        slides.push({ label: 'Buyer Protection', icon: LockKeyhole, className: 'bg-slate-100 text-slate-700' });
    }

    return slides;
}

function ProductCard({ product, addToCart, toggleWishlist, wished, hideDiscountLabel = false }) {
    const gallery = (product.images?.length ? product.images : [product.image]).filter(Boolean);
    const [imageIndex, setImageIndex] = useState(0);
    const [cardSlide, setCardSlide] = useState(0);
    const [touchStartX, setTouchStartX] = useState(null);
    const activeImage = gallery[imageIndex] || product.image;
    useEffect(() => {
        setImageIndex(0);
        setCardSlide(0);
    }, [product.id]);
    const discount = product.oldPrice > product.price ? Math.max(1, Math.round(((product.oldPrice - product.price) / product.oldPrice) * 100)) : 0;
    const availableStock = asNumber(product.availableStock ?? product.stock);
    const isOutOfStock = availableStock <= 0;
    const lowStockThreshold = 5;
    const showLowStock = !isOutOfStock && availableStock <= lowStockThreshold;
    const soldCount = asNumber(product.salesCount ?? product.stockSold);
    const isPhysicalProduct = product.productType === 'physical';
    const productLocation = product.productLocation || product.city;
    const productCondition = product.condition || 'New';
    const trustSlides = productCardTrustSlides(product);
    const activeTrustSlide = trustSlides[cardSlide] || trustSlides[0];
    const ActiveTrustIcon = activeTrustSlide?.icon || ShieldCheck;
    const handleTrustSwipe = (event) => {
        if (touchStartX === null || trustSlides.length <= 1) return;
        const delta = touchStartX - event.changedTouches[0].clientX;
        if (Math.abs(delta) > 36) {
            setCardSlide((index) => (index + (delta > 0 ? 1 : trustSlides.length - 1)) % trustSlides.length);
        }
        setTouchStartX(null);
    };
    return (
        <article className={cn('group flex h-full flex-col overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm transition duration-300 hover:-translate-y-1 hover:border-slate-300 hover:shadow-[0_24px_70px_-44px_rgba(15,23,42,0.8)]', isOutOfStock && 'border-slate-200 bg-white hover:-translate-y-0 hover:border-slate-200')}>
            <div className="bg-slate-50 p-3">
                <Link href={`/products/${product.id}`} className="relative block overflow-hidden rounded-lg bg-slate-200">
                    <ProductMedia src={activeImage} alt={product.title} className={cn('aspect-[5/3] w-full object-cover transition duration-500 group-hover:scale-[1.025]', isOutOfStock && 'grayscale opacity-35 group-hover:scale-100')} />
                    {discount && !isOutOfStock ? <span className="absolute left-2.5 top-2.5 z-10 rounded-md bg-rose-500 px-2.5 py-1 text-xs font-extrabold text-white shadow-sm" title={product.discountLabel || ''}>-{discount}%</span> : null}
                    {isOutOfStock ? (
                        <span className="absolute left-1/2 top-1/2 z-20 -translate-x-1/2 -translate-y-1/2 -rotate-[-12deg] rounded-lg bg-slate-700 px-5 py-2 text-sm font-black uppercase tracking-[0.22em] text-white shadow-[0_18px_32px_-18px_rgba(15,23,42,0.75)]">
                            Sold Out
                        </span>
                    ) : null}
                    <RatingPill product={product} className={cn('absolute right-2.5 top-2.5 z-10 rounded-md bg-white/95 px-2 py-1 shadow-sm ring-1 ring-slate-200/80', isOutOfStock && 'opacity-50 ring-slate-100')} />
                    <div className={cn('pointer-events-none absolute inset-x-2.5 bottom-2.5 flex items-end justify-between gap-2', isOutOfStock && 'opacity-30')}>
                        <div className="flex min-w-0 flex-wrap gap-1.5">
                            {isPhysicalProduct ? (
                                <>
                                <span className="inline-flex max-w-full items-center gap-1 rounded-md bg-emerald-50 px-2 py-0.5 text-[11px] font-extrabold uppercase tracking-wide text-emerald-700 shadow-sm ring-1 ring-emerald-100">
                                    <Tag className="size-3" />
                                    {productCondition}
                                </span>
                                {productLocation ? (
                                    <span className="inline-flex max-w-full items-center gap-1 rounded-md bg-white/95 px-2 py-0.5 text-[11px] font-extrabold text-slate-700 shadow-sm ring-1 ring-slate-200/80">
                                        <MapPin className="size-3 text-rose-600" />
                                        <span className="max-w-28 truncate">{productLocation}</span>
                                    </span>
                                ) : null}
                                </>
                            ) : product.productType === 'digital' ? (
                                <span className={cn('inline-flex max-w-full items-center gap-1 rounded-md px-2 py-0.5 text-[11px] font-extrabold shadow-sm ring-1', product.isInstantDelivery ? 'bg-blue-50 text-blue-700 ring-blue-100' : 'bg-cyan-50 text-cyan-700 ring-cyan-100')}>
                                    {product.isInstantDelivery ? <Zap className="size-3" /> : <Download className="size-3" />}
                                    {product.isInstantDelivery ? 'Instant Delivery' : 'Digital Product'}
                                </span>
                            ) : (
                                <span className="inline-flex max-w-full items-center gap-1 rounded-md bg-indigo-50 px-2 py-0.5 text-[11px] font-extrabold text-indigo-700 shadow-sm ring-1 ring-indigo-100">
                                    <BriefcaseBusiness className="size-3" />
                                    Service
                                </span>
                            )}
                        </div>
                        {showLowStock ? (
                            <span className="ml-auto inline-flex shrink-0 rounded-md bg-amber-50 px-2 py-0.5 text-[11px] font-extrabold uppercase tracking-wide text-amber-700 shadow-sm ring-1 ring-amber-100">
                                Only {availableStock} left
                            </span>
                        ) : null}
                    </div>
                </Link>
                {gallery.length > 1 ? (
                    <div className="mt-2 flex justify-center gap-1.5" aria-label="Product image gallery">
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
            <div className="flex flex-1 flex-col p-4">
                <p className={cn('line-clamp-1 text-sm font-extrabold text-indigo-600', isOutOfStock && 'text-slate-300')}>{product.seller}</p>
                <Link href={`/products/${product.id}`} className={cn('mt-0.5 line-clamp-2 block min-h-9 text-lg font-extrabold leading-[1.35] tracking-tight text-slate-900 hover:text-indigo-600', isOutOfStock && 'text-slate-400 hover:text-slate-500')}>{product.title}</Link>
                {!hideDiscountLabel && discount && product.discountLabel ? <p className="mt-2 line-clamp-1 text-xs font-extrabold uppercase tracking-wide text-rose-600">{product.discountLabel}</p> : null}
                <div className="mt-auto pt-1">
                    <div>
                        <div className="flex items-end justify-between gap-3">
                            <p className={cn('text-2xl font-extrabold leading-none tracking-tight text-rose-600', isOutOfStock && 'text-slate-300')}><span className="mr-1 text-sm">৳</span>{Number(product.price || 0).toLocaleString('en-BD')}</p>
                            {soldCount > 0 ? <p className={cn('text-right text-sm font-semibold text-slate-500', isOutOfStock && 'text-slate-400')}>{soldCount.toLocaleString('en-BD')} sold</p> : null}
                        </div>
                        <div>
                            {product.oldPrice > product.price ? <p className="mt-1 text-sm font-bold text-slate-400 line-through">{money(product.oldPrice)}</p> : null}
                        </div>
                    </div>
                    <div className="mt-2 flex gap-2">
                        <Button disabled={isOutOfStock} className={cn('h-10 flex-1 rounded-lg text-sm font-bold shadow-none', !isOutOfStock ? 'bg-slate-950 text-white hover:bg-indigo-600' : 'bg-slate-100 text-slate-400 disabled:cursor-not-allowed disabled:opacity-100')} onClick={() => addToCart(product)}>
                            <ShoppingCart className="size-4" />
                            Add to Cart
                        </Button>
                        <Button disabled={isOutOfStock} variant={wished ? 'default' : 'outline'} size="icon" className="size-10 rounded-lg disabled:cursor-not-allowed disabled:border-slate-200 disabled:bg-slate-50 disabled:text-slate-300 disabled:opacity-100" onClick={() => toggleWishlist(product.id)} aria-label="Toggle wishlist">
                            <Heart className={cn('size-4', wished && 'fill-current')} />
                        </Button>
                    </div>
                </div>
            </div>
            {activeTrustSlide ? (
                <div
                    className={cn('border-t border-slate-100 px-4 py-3', activeTrustSlide.className, isOutOfStock && 'bg-slate-50 text-slate-400')}
                    onTouchStart={(event) => setTouchStartX(event.touches[0].clientX)}
                    onTouchEnd={handleTrustSwipe}
                >
                    <div className="flex items-center justify-between gap-3">
                        <button
                            type="button"
                            className="flex min-w-0 items-center gap-2 text-left text-sm font-extrabold disabled:cursor-not-allowed"
                            disabled={isOutOfStock}
                            onClick={() => trustSlides.length > 1 && setCardSlide((index) => (index + 1) % trustSlides.length)}
                            aria-label={`Product assurance: ${activeTrustSlide.label}`}
                        >
                            <ActiveTrustIcon className="size-4 shrink-0" />
                            <span className="truncate">{activeTrustSlide.label}</span>
                        </button>
                        {trustSlides.length > 1 ? (
                            <div className="flex shrink-0 gap-1.5" aria-label="Product assurance slides">
                                {trustSlides.map((slide, index) => (
                                    <button
                                        key={`${product.id}-${slide.label}`}
                                        type="button"
                                        onClick={() => setCardSlide(index)}
                                        className={cn('size-2 rounded-full transition', cardSlide === index ? 'bg-emerald-600' : 'bg-slate-300 hover:bg-slate-400')}
                                        aria-label={`Show ${slide.label}`}
                                    />
                                ))}
                            </div>
                        ) : null}
                    </div>
                </div>
            ) : null}
        </article>
    );
}

function Marketplace({ state, addToCart, toggleWishlist }) {
    const initialDealParam = typeof window === 'undefined' ? '' : (new URLSearchParams(window.location.search).get('deal') || '');
    const initialTypeParam = typeof window === 'undefined' ? '' : (new URLSearchParams(window.location.search).get('type') || '');
    const [query, setQuery] = useState(() => typeof window === 'undefined' ? '' : (new URLSearchParams(window.location.search).get('q') || ''));
    const [dealOnly, setDealOnly] = useState(() => initialDealParam === 'flash');
    const [category, setCategory] = useState(() => typeof window === 'undefined' ? 'All' : (new URLSearchParams(window.location.search).get('category') || 'All'));
    const [subcategory, setSubcategory] = useState(() => typeof window === 'undefined' ? 'All' : (new URLSearchParams(window.location.search).get('subcategory') || 'All'));
    const [type, setType] = useState(() => ['Digital product', 'digital', 'Instant delivery', 'instant_delivery'].includes(initialTypeParam) ? 'All' : (initialTypeParam || 'All'));
    const [fulfillmentFilters, setFulfillmentFilters] = useState(() => ({
        digital: ['Digital product', 'digital'].includes(initialTypeParam),
        instant_delivery: ['Instant delivery', 'instant_delivery'].includes(initialTypeParam),
    }));
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
    const brandOptions = ['All', ...Array.from(new Set(state.products.map((product) => product.brand).filter(Boolean)))];
    const digitalProductCount = state.products.filter((product) => product.productType === 'digital' && !product.isInstantDelivery).length;
    const instantDeliveryCount = state.products.filter((product) => product.productType === 'digital' && product.isInstantDelivery).length;
    const maxCatalogPrice = Math.max(0, ...state.products.map((product) => asNumber(product.price)));
    const products = useMemo(() => {
        const selectedFulfillmentTypes = Object.entries(fulfillmentFilters).filter(([, enabled]) => enabled).map(([key]) => key);
        const filtered = state.products.filter((product) => {
        const campaignText = product.activeCampaign ? `${product.activeCampaign.title || ''} ${product.activeCampaign.badge || ''} ${product.activeCampaign.code || ''}` : '';
        const matchesQuery = `${product.title} ${product.seller} ${product.city} ${product.brand || ''} ${product.category || ''} ${product.subcategory || ''} ${product.discountLabel || ''} ${campaignText} ${(product.tags || []).join(' ')}`.toLowerCase().includes(query.toLowerCase());
        const matchesDeal = !dealOnly || Boolean(product.activeCampaign);
        const matchesCategory = category === 'All' || product.category === category;
        const matchesSubcategory = subcategory === 'All' || product.subcategory === subcategory;
        const matchesType = type === 'All' || product.type === type || product.productTypeLabel === type;
        const matchesFulfillment = selectedFulfillmentTypes.length === 0 || selectedFulfillmentTypes.some((selectedType) => {
            if (selectedType === 'instant_delivery') return product.productType === 'digital' && Boolean(product.isInstantDelivery);
            if (selectedType === 'digital') return product.productType === 'digital' && !Boolean(product.isInstantDelivery);
            return selectedType === product.productType;
        });
        const matchesBrand = brand === 'All' || product.brand === brand;
        const availableStock = asNumber(product.availableStock ?? product.stock);
        const matchesAvailability = availability === 'All' || (availability === 'In stock' ? availableStock > 0 : availableStock <= 0);
        const price = asNumber(product.price);
        const matchesMin = minPrice === '' || price >= asNumber(minPrice);
        const matchesMax = maxPrice === '' || price <= asNumber(maxPrice);
        return matchesQuery && matchesDeal && matchesCategory && matchesSubcategory && matchesType && matchesFulfillment && matchesBrand && matchesAvailability && matchesMin && matchesMax;
        });
        return filtered.sort((a, b) => {
            if (sort === 'Price low') return asNumber(a.price) - asNumber(b.price);
            if (sort === 'Price high') return asNumber(b.price) - asNumber(a.price);
            if (sort === 'Rating') return asNumber(b.rating) - asNumber(a.rating);
            if (sort === 'Best selling') return asNumber(b.salesCount) - asNumber(a.salesCount);
            return String(b.publishedAt || b.id).localeCompare(String(a.publishedAt || a.id));
        });
    }, [state.products, query, dealOnly, category, subcategory, type, fulfillmentFilters, brand, availability, minPrice, maxPrice, sort]);
    const visibleProducts = products.slice(0, visibleCount);
    const toggleFulfillmentFilter = (key) => {
        setFulfillmentFilters((current) => ({ ...current, [key]: !current[key] }));
        setVisibleCount(PAGE_SIZE);
    };
    const clearFilters = () => {
        setQuery('');
        setDealOnly(false);
        setCategory('All');
        setSubcategory('All');
        setType('All');
        setFulfillmentFilters({ digital: false, instant_delivery: false });
        setBrand('All');
        setAvailability('All');
        setMinPrice('');
        setMaxPrice('');
        setSort('Newest');
        setVisibleCount(PAGE_SIZE);
    };
    const filterControls = (
        <div className="grid gap-4">
            <div className="relative">
                <Search className="absolute left-3 top-1/2 size-4 -translate-y-1/2 text-slate-400" />
                <Input id="marketplace-search" aria-label="Search marketplace" value={query} onChange={(e) => { setQuery(e.target.value); setVisibleCount(PAGE_SIZE); }} placeholder="Search products, sellers, brands, city..." className="h-11 border-slate-200 bg-slate-50 pl-9 focus:bg-white" />
            </div>
            <div className="grid gap-3">
                <select id="marketplace-category" aria-label="Category" value={category} onChange={(e) => { setCategory(e.target.value); setSubcategory('All'); setVisibleCount(PAGE_SIZE); }} className="h-11 rounded-md border-slate-200 bg-slate-50 text-sm">
                    {(categoryOptions.length > 1 ? categoryOptions : fallbackCategoryNames).map((item) => <option key={item} value={item}>{item === 'All' ? 'All categories' : item}</option>)}
                </select>
                <select id="marketplace-subcategory" aria-label="Subcategory" value={subcategory} onChange={(e) => { setSubcategory(e.target.value); setVisibleCount(PAGE_SIZE); }} className="h-11 rounded-md border-slate-200 bg-slate-50 text-sm" disabled={subcategoryOptions.length <= 1}>
                    {subcategoryOptions.map((item) => <option key={item} value={item}>{item === 'All' ? 'All subcategories' : item}</option>)}
                </select>
                <div className="rounded-md border border-slate-200 bg-slate-50 p-3">
                    <div className="grid gap-2">
                        <label className="flex cursor-pointer items-center justify-between gap-3 rounded-md bg-white px-3 py-2 text-sm font-semibold text-slate-700 shadow-sm ring-1 ring-slate-100">
                            <span className="flex items-center gap-2">
                                <input type="checkbox" checked={fulfillmentFilters.instant_delivery} onChange={() => toggleFulfillmentFilter('instant_delivery')} className="size-4 rounded border-slate-300 text-slate-950 focus:ring-slate-950/20" />
                                Instant delivery
                            </span>
                            {instantDeliveryCount ? <span className="text-xs font-bold text-slate-400">{instantDeliveryCount}</span> : null}
                        </label>
                        <label className="flex cursor-pointer items-center justify-between gap-3 rounded-md bg-white px-3 py-2 text-sm font-semibold text-slate-700 shadow-sm ring-1 ring-slate-100">
                            <span className="flex items-center gap-2">
                                <input type="checkbox" checked={fulfillmentFilters.digital} onChange={() => toggleFulfillmentFilter('digital')} className="size-4 rounded border-slate-300 text-slate-950 focus:ring-slate-950/20" />
                                Digital product
                            </span>
                            {digitalProductCount ? <span className="text-xs font-bold text-slate-400">{digitalProductCount}</span> : null}
                        </label>
                    </div>
                </div>
                <select id="marketplace-brand" aria-label="Brand" value={brand} onChange={(e) => { setBrand(e.target.value); setVisibleCount(PAGE_SIZE); }} className="h-11 rounded-md border-slate-200 bg-slate-50 text-sm">
                    {brandOptions.map((item) => <option key={item} value={item}>{item === 'All' ? 'All brands' : item}</option>)}
                </select>
                <select id="marketplace-availability" aria-label="Availability" value={availability} onChange={(e) => { setAvailability(e.target.value); setVisibleCount(PAGE_SIZE); }} className="h-11 rounded-md border-slate-200 bg-slate-50 text-sm">
                    {['All', 'In stock', 'Out of stock'].map((item) => <option key={item} value={item}>{item === 'All' ? 'Any availability' : item}</option>)}
                </select>
            </div>
            <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-1">
                <Input id="marketplace-min-price" aria-label="Minimum price" value={minPrice} onChange={(e) => { setMinPrice(e.target.value); setVisibleCount(PAGE_SIZE); }} inputMode="numeric" placeholder="No minimum price" className="h-11 bg-slate-50" />
                <Input id="marketplace-max-price" aria-label="Maximum price" value={maxPrice} onChange={(e) => { setMaxPrice(e.target.value); setVisibleCount(PAGE_SIZE); }} inputMode="numeric" placeholder={maxCatalogPrice ? `Up to ${money(maxCatalogPrice)}` : 'No maximum price'} className="h-11 bg-slate-50" />
                <select id="marketplace-sort" aria-label="Sort products" value={sort} onChange={(e) => setSort(e.target.value)} className="h-11 rounded-md border-slate-200 bg-slate-50 text-sm">
                    {['Newest', 'Best selling', 'Rating', 'Price low', 'Price high'].map((item) => <option key={item}>{item}</option>)}
                </select>
            </div>
            <Button type="button" variant="outline" onClick={clearFilters}>Reset filters</Button>
        </div>
    );

    return (
        <div className="space-y-5">
            <div className="flex items-center justify-between gap-3 lg:hidden">
                <p className="text-sm font-extrabold text-slate-700">{products.length} results</p>
                <Button variant="outline" size="sm" onClick={() => setMobileFiltersOpen(true)}><SlidersHorizontal className="size-4" />Filters</Button>
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
            <div className="grid gap-5 lg:grid-cols-[280px_1fr] xl:grid-cols-[320px_1fr]">
                <aside className="hidden h-fit rounded-xl border border-slate-200 bg-white p-4 shadow-sm lg:sticky lg:top-[164px] lg:block">
                    <div className="mb-4 flex items-center justify-between border-b border-slate-100 pb-3">
                        <h2 className="font-extrabold text-slate-950">Filters</h2>
                        <SlidersHorizontal className="size-4 text-slate-400" />
                    </div>
                    {filterControls}
                </aside>
                <div className="min-w-0 space-y-5">
                    {products.length ? <div className="grid gap-5 sm:grid-cols-2 xl:grid-cols-3">
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
            </div>
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
    const flashDeal = state.flashDeal || {};
    const serverOffset = useMemo(() => {
        const serverTime = flashDeal.serverTime ? new Date(flashDeal.serverTime).getTime() : null;
        return serverTime && Number.isFinite(serverTime) ? Date.now() - serverTime : 0;
    }, [flashDeal.serverTime]);
    const [now, setNow] = useState(() => Date.now());
    useEffect(() => {
        const timer = window.setInterval(() => setNow(Date.now() - serverOffset), 1000);
        setNow(Date.now() - serverOffset);
        return () => window.clearInterval(timer);
    }, [serverOffset]);
    const dealProducts = useMemo(() => {
        const ids = Array.isArray(flashDeal.productIds) ? flashDeal.productIds.map(Number) : [];
        const byId = new Map(state.products.map((product) => [Number(product.id), product]));
        const orderedDeals = ids.map((id) => byId.get(id)).filter(Boolean);
        const discoveredDeals = state.products.filter((product) => product.activeCampaign && !ids.includes(Number(product.id)));
        return [...orderedDeals, ...discoveredDeals].slice(0, 5);
    }, [state.products, flashDeal.productIds]);
    const dealEndsAt = useMemo(() => {
        const explicitEnd = flashDeal.endsAt ? new Date(flashDeal.endsAt).getTime() : null;
        if (explicitEnd && Number.isFinite(explicitEnd)) return explicitEnd;
        const end = new Date();
        end.setHours(23, 59, 59, 999);
        return end.getTime();
    }, [flashDeal.endsAt]);
    const remainingSeconds = Math.max(0, Math.floor((dealEndsAt - now) / 1000));
    const isExpired = remainingSeconds <= 0;
    const timerParts = [
        Math.floor(remainingSeconds / 3600),
        Math.floor((remainingSeconds % 3600) / 60),
        remainingSeconds % 60,
    ].map((value) => String(value).padStart(2, '0'));

    return (
        <section className="mt-10">
            <div className="mb-5 flex flex-wrap items-center justify-between gap-3 border-b border-slate-200 pb-4">
                <div className="flex flex-wrap items-center gap-3">
                    <div className="flex items-center gap-2">
                        <Flame className="size-6 text-rose-500" />
                        <h2 className="text-2xl font-black uppercase tracking-tight text-slate-950">{flashDeal.title || 'Flash Deals'}</h2>
                    </div>
                    <div className="flex items-center gap-2 rounded-md bg-rose-100 px-3 py-2 text-sm font-extrabold text-rose-700">
                        <Clock className="size-4" />
                        <span>Ends in:</span>
                        <span className="rounded bg-white px-2 py-1 tabular-nums text-rose-700">{timerParts[0]}</span>
                        <span>:</span>
                        <span className="rounded bg-white px-2 py-1 tabular-nums text-rose-700">{timerParts[1]}</span>
                        <span>:</span>
                        <span className="rounded bg-white px-2 py-1 tabular-nums text-rose-700">{timerParts[2]}</span>
                    </div>
                </div>
                <Link href="/marketplace?deal=flash" className="text-sm font-extrabold text-indigo-600 transition hover:text-indigo-800">View All Deals</Link>
            </div>
            {dealProducts.length && !isExpired ? (
                <div className="grid gap-5 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-5">
                    {dealProducts.map((product) => (
                        <ProductCard key={product.id} product={product} addToCart={addToCart} toggleWishlist={toggleWishlist} wished={state.wishlist.includes(product.id)} hideDiscountLabel />
                    ))}
                </div>
            ) : (
                <div className="rounded-xl border border-dashed border-slate-300 bg-white p-8 text-center shadow-sm">
                    <p className="text-lg font-extrabold text-slate-950">{isExpired ? 'This flash deal has ended' : 'No flash deals are active'}</p>
                    <p className="mt-2 text-sm font-medium text-slate-500">Active catalog campaigns from the admin panel will appear here automatically.</p>
                </div>
            )}
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
        count: products.filter((product) => {
            if (item.key === 'instant_delivery') return product.productType === 'digital' && product.isInstantDelivery;
            if (item.key === 'digital') return product.productType === 'digital' && !product.isInstantDelivery;
            return product.productType === item.key;
        }).length,
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

function BestSellers({ state, addToCart, toggleWishlist }) {
    const pageSize = 4;
    const rankedProducts = [...state.products].sort((a, b) => asNumber(b.salesCount ?? b.stockSold) - asNumber(a.salesCount ?? a.stockSold));
    const [page, setPage] = useState(0);
    const pageCount = Math.max(1, Math.ceil(rankedProducts.length / pageSize));
    const products = rankedProducts.slice(page * pageSize, page * pageSize + pageSize);
    const movePage = (delta) => setPage((current) => (current + delta + pageCount) % pageCount);

    return (
        <section>
            <div className="mb-5 flex items-center justify-between border-b border-slate-200 pb-4">
                <h2 className="text-xl font-black uppercase tracking-tight text-slate-950">Top Performers</h2>
                <div className="flex items-center gap-2">
                    <Button type="button" variant="outline" size="icon" className="size-9 rounded-none shadow-none" onClick={() => movePage(-1)} aria-label="Previous top performers"><ChevronLeft className="size-4" /></Button>
                    <Button type="button" variant="outline" size="icon" className="size-9 rounded-none shadow-none" onClick={() => movePage(1)} aria-label="Next top performers"><ChevronRight className="size-4" /></Button>
                </div>
            </div>
            <div className="grid gap-3 sm:grid-cols-2">
                {products.map((product, index) => {
                    const availableStock = asNumber(product.availableStock ?? product.stock);
                    const rank = page * pageSize + index + 1;
                    const wished = state.wishlist.includes(product.id);

                    return (
                    <article key={product.id} className="group grid grid-cols-[88px_1fr] gap-3 overflow-hidden rounded-lg border border-slate-200 bg-white p-2.5 shadow-sm transition hover:-translate-y-0.5 hover:border-slate-300 hover:shadow-[0_18px_45px_-38px_rgba(15,23,42,0.7)]">
                        <Link href={`/products/${product.id}`} className="relative block overflow-hidden rounded-md bg-slate-100">
                            <ProductMedia src={product.image} alt={product.title} className={cn('aspect-square w-full object-cover transition duration-500 group-hover:scale-[1.025]', availableStock <= 0 && 'grayscale opacity-45')} />
                            <span className="absolute left-1.5 top-1.5 flex size-6 items-center justify-center rounded-full bg-amber-400 text-[10px] font-black text-slate-950 shadow-sm">#{rank}</span>
                            <button
                                type="button"
                                disabled={availableStock <= 0}
                                onClick={(event) => { event.preventDefault(); toggleWishlist(product.id); }}
                                className={cn('absolute right-1.5 top-1.5 flex size-7 items-center justify-center rounded-full bg-white/95 text-slate-500 shadow-sm ring-1 ring-slate-200 transition hover:text-rose-500 disabled:cursor-not-allowed disabled:text-slate-300 disabled:opacity-80', wished && 'bg-slate-950 text-white hover:text-white')}
                                aria-label="Toggle wishlist"
                            >
                                <Heart className={cn('size-3.5', wished && 'fill-current')} />
                            </button>
                            {availableStock <= 0 ? (
                                <span className="absolute left-1/2 top-1/2 -translate-x-1/2 -translate-y-1/2 -rotate-[-12deg] rounded-md bg-slate-700 px-2.5 py-1 text-[10px] font-black uppercase tracking-[0.12em] text-white shadow-lg">Sold Out</span>
                            ) : null}
                        </Link>
                        <div className="min-w-0 py-0.5">
                            <div className="flex items-center justify-between gap-3">
                                <p className="line-clamp-1 text-[10px] font-extrabold uppercase tracking-[0.12em] text-slate-400">{productTaxonomyLabel(product)}</p>
                                <RatingSummary product={product} starClassName="size-2.5" />
                            </div>
                            <Link href={`/products/${product.id}`} className={cn('mt-1 line-clamp-2 block min-h-8 text-sm font-black leading-4 text-slate-950 hover:text-indigo-600', availableStock <= 0 && 'text-slate-400')}>{product.title}</Link>
                            <div className="mt-2 flex items-center justify-between gap-3">
                                <div>
                                    <p className={cn('text-sm font-black text-rose-600', availableStock <= 0 && 'text-slate-400')}>{money(product.price)}</p>
                                    {asNumber(product.salesCount ?? product.stockSold) > 0 ? <p className="text-[11px] font-bold text-slate-400">{asNumber(product.salesCount ?? product.stockSold).toLocaleString('en-BD')} sold</p> : null}
                                </div>
                                <Button
                                    type="button"
                                    size="icon"
                                    disabled={availableStock <= 0}
                                    className="size-8 rounded-md bg-slate-950 text-white shadow-none hover:bg-indigo-600 disabled:cursor-not-allowed disabled:bg-slate-100 disabled:text-slate-400 disabled:opacity-100"
                                    onClick={() => addToCart(product)}
                                    aria-label="Add top performer to cart"
                                >
                                    <ShoppingCart className="size-3.5" />
                                </Button>
                            </div>
                        </div>
                    </article>
                    );
                })}
            </div>
        </section>
    );
}

function JustDropped({ state, addToCart, toggleWishlist }) {
    const pageSize = 4;
    const droppedProducts = state.products.slice(4).concat(state.products.slice(0, 4));
    const [page, setPage] = useState(0);
    const pageCount = Math.max(1, Math.ceil(droppedProducts.length / pageSize));
    const products = droppedProducts.slice(page * pageSize, page * pageSize + pageSize);
    const movePage = (delta) => setPage((current) => (current + delta + pageCount) % pageCount);

    return (
        <section>
            <div className="mb-5 flex items-center justify-between border-b border-slate-200 pb-4">
                <h2 className="text-xl font-black uppercase tracking-tight text-slate-950">Just Dropped</h2>
                <div className="flex items-center gap-2">
                    <Button type="button" variant="outline" size="icon" className="size-9 rounded-none shadow-none" onClick={() => movePage(-1)} aria-label="Previous just dropped"><ChevronLeft className="size-4" /></Button>
                    <Button type="button" variant="outline" size="icon" className="size-9 rounded-none shadow-none" onClick={() => movePage(1)} aria-label="Next just dropped"><ChevronRight className="size-4" /></Button>
                </div>
            </div>
            <div className="divide-y divide-slate-200">
                {products.map((product) => {
                    const availableStock = asNumber(product.availableStock ?? product.stock);
                    const wished = state.wishlist.includes(product.id);

                    return (
                        <div key={`drop-${product.id}`} className="group grid grid-cols-[64px_1fr_auto] items-center gap-4 py-4">
                            <div className="overflow-hidden bg-slate-100">
                                <Link href={`/products/${product.id}`}>
                                    <ProductMedia src={product.image} alt={product.title} className={cn('size-16 object-cover transition group-hover:scale-105', availableStock <= 0 && 'grayscale opacity-40')} />
                                </Link>
                            </div>
                            <div className="min-w-0">
                                <Link href={`/products/${product.id}`} className={cn('line-clamp-1 text-sm font-extrabold text-slate-950 hover:text-indigo-600', availableStock <= 0 && 'text-slate-400')}>{product.title}</Link>
                                <p className="mt-1 line-clamp-1 text-xs font-medium text-slate-400">{productTaxonomyLabel(product)}</p>
                                <div className="mt-1 flex items-center gap-3">
                                    <RatingSummary product={product} starClassName="size-2.5" />
                                    <button
                                        type="button"
                                        disabled={availableStock <= 0}
                                        onClick={() => toggleWishlist(product.id)}
                                        className={cn('text-slate-400 hover:text-rose-500 disabled:cursor-not-allowed disabled:text-slate-300', wished && 'text-rose-500')}
                                        aria-label="Toggle wishlist"
                                    >
                                        <Heart className={cn('size-4', wished && 'fill-current')} />
                                    </button>
                                </div>
                            </div>
                            <div className="text-right">
                                <p className={cn('text-sm font-black text-slate-950', availableStock <= 0 && 'text-slate-400')}>{money(product.price)}</p>
                                {availableStock <= 0 ? <p className="mt-1 text-[10px] font-black uppercase tracking-wide text-slate-400">Sold out</p> : null}
                                <Button
                                    type="button"
                                    size="icon"
                                    disabled={availableStock <= 0}
                                    className="mt-2 size-8 rounded-md bg-slate-950 text-white shadow-none hover:bg-indigo-600 disabled:cursor-not-allowed disabled:bg-slate-100 disabled:text-slate-400 disabled:opacity-100"
                                    onClick={() => addToCart(product)}
                                    aria-label="Add just dropped product to cart"
                                >
                                    <ShoppingCart className="size-3.5" />
                                </Button>
                            </div>
                        </div>
                    );
                })}
            </div>
        </section>
    );
}

function Recommended({ state, addToCart, toggleWishlist }) {
    const products = state.products.slice(0, 12);

    return (
        <section className="mt-12">
            <SectionTitle icon={PackageSearch} title="Recommended For You" accent="text-emerald-600" action="Explore All" />
            <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 2xl:grid-cols-6">
                {products.map((product) => {
                    const availableStock = asNumber(product.availableStock ?? product.stock);
                    const wished = state.wishlist.includes(product.id);

                    return (
                        <article key={`rec-${product.id}`} className="group overflow-hidden rounded-lg border border-slate-200 bg-white p-2.5 shadow-sm transition hover:-translate-y-0.5 hover:border-slate-300 hover:shadow-[0_16px_42px_-36px_rgba(15,23,42,0.7)]">
                            <Link href={`/products/${product.id}`} className="relative block overflow-hidden rounded-md bg-slate-100">
                                <ProductMedia src={product.image} alt={product.title} className={cn('aspect-[4/3] w-full object-cover transition group-hover:scale-[1.025]', availableStock <= 0 && 'grayscale opacity-40')} />
                                {availableStock <= 0 ? <span className="absolute left-1/2 top-1/2 -translate-x-1/2 -translate-y-1/2 -rotate-[-12deg] rounded-md bg-slate-700 px-3 py-1 text-[10px] font-black uppercase tracking-[0.14em] text-white shadow-lg">Sold Out</span> : null}
                            </Link>
                            <div className="pt-2">
                                <p className="line-clamp-1 text-[11px] font-extrabold text-indigo-600">{product.seller}</p>
                                <Link href={`/products/${product.id}`} className={cn('mt-0.5 line-clamp-2 block min-h-8 text-sm font-black leading-4 text-slate-950 hover:text-indigo-600', availableStock <= 0 && 'text-slate-400')}>{product.title}</Link>
                                <div className="mt-1">
                                    <RatingSummary product={product} starClassName="size-2.5" />
                                </div>
                                <div className="mt-2 flex items-center justify-between gap-2">
                                    <p className={cn('text-sm font-black text-rose-600', availableStock <= 0 && 'text-slate-400')}>{money(product.price)}</p>
                                    <div className="flex gap-1">
                                        <Button type="button" size="icon" disabled={availableStock <= 0} className="size-8 rounded-md bg-slate-950 text-white shadow-none hover:bg-indigo-600 disabled:cursor-not-allowed disabled:bg-slate-100 disabled:text-slate-400 disabled:opacity-100" onClick={() => addToCart(product)} aria-label="Add recommended product to cart">
                                            <ShoppingCart className="size-3.5" />
                                        </Button>
                                        <Button type="button" size="icon" variant={wished ? 'default' : 'outline'} disabled={availableStock <= 0} className="size-8 rounded-md disabled:cursor-not-allowed disabled:border-slate-200 disabled:bg-slate-50 disabled:text-slate-300 disabled:opacity-100" onClick={() => toggleWishlist(product.id)} aria-label="Toggle recommended product wishlist">
                                            <Heart className={cn('size-3.5', wished && 'fill-current')} />
                                        </Button>
                                    </div>
                                </div>
                            </div>
                        </article>
                    );
                })}
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
    const [paymentReference, setPaymentReference] = useState('');
    return (
        <section className="grid gap-6 lg:grid-cols-[1fr_360px]">
            <div className="space-y-4">
                <Panel title="Shipping address" icon={Truck}>
                    <Input value={address} onChange={(e) => setAddress(e.target.value)} />
                </Panel>
                <Panel title="Payment method" icon={CreditCard}>
                    <div className="grid gap-3 sm:grid-cols-2">
                        {['wallet', 'manual'].map((item) => (
                            <button key={item} onClick={() => setPayment(item)} className={cn('rounded-md border p-4 text-left text-sm capitalize transition', payment === item ? 'border-slate-950 bg-slate-950 text-white' : 'border-slate-200 bg-white hover:bg-slate-50')}>
                                {item === 'wallet' ? 'Wallet escrow' : 'Manual payment'}
                            </button>
                        ))}
                    </div>
                    {payment === 'manual' && <Input value={paymentReference} onChange={(e) => setPaymentReference(e.target.value)} placeholder="Payment reference" className="mt-3" />}
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
                <Button className="mt-5 w-full" onClick={() => checkout(payment, address, paymentReference)} asChild={false}>Place order</Button>
                <Button asChild variant="outline" className="mt-2 w-full"><Link href="/orders">View orders</Link></Button>
            </aside>
        </section>
    );
}

const buyerPanelSections = [
    { key: 'dashboard', label: 'Dashboard', href: '/dashboard', icon: LayoutDashboard },
    { key: 'orders', label: 'Orders', href: '/orders', icon: Truck },
    { key: 'wallet', label: 'Wallet', href: '/wallet', icon: WalletCards },
    { key: 'saved', label: 'Saved', href: '/wishlist', icon: Heart },
    { key: 'profile', label: 'Profile', href: '/profile', icon: User },
    { key: 'support', label: 'Inbox', href: '/support', icon: MessageSquareText },
];

function BuyerPanelShell({ activeKey, eyebrow, title, description, children, aside = null }) {
    return (
        <div className="grid gap-6 xl:grid-cols-[260px_minmax(0,1fr)]">
            <aside className="space-y-4 xl:sticky xl:top-24 xl:h-fit">
                <nav className="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
                    {buyerPanelSections.map(({ key, label, href, icon: Icon }) => {
                        const active = activeKey === key;
                        return (
                            <Link
                                key={key}
                                href={href}
                                className={cn(
                                    'flex items-center gap-3 border-b border-slate-100 px-4 py-3 text-sm font-bold transition last:border-b-0',
                                    active ? 'bg-cyan-50 text-cyan-800' : 'text-slate-600 hover:bg-slate-50 hover:text-slate-950',
                                )}
                            >
                                <Icon className="size-4" />
                                {label}
                            </Link>
                        );
                    })}
                </nav>
                {aside}
            </aside>
            <div className="space-y-6">{children}</div>
        </div>
    );
}

function BuyerMetricsGrid({ items }) {
    return <div className="grid gap-3 sm:grid-cols-2 2xl:grid-cols-4">{items.map((item) => <Stat key={item.label} {...item} />)}</div>;
}

function humanizeOrderState(value) {
    return String(value || 'active')
        .replaceAll('_', ' ')
        .replace(/\b\w/g, (char) => char.toUpperCase());
}

function orderStatusPillClasses(item) {
    const status = String(item.status || item.state || '').toLowerCase();
    const escrowState = String(item.escrowState || item.state || '').toLowerCase();

    if (escrowState.includes('hold') || escrowState.includes('funded') || escrowState.includes('active') || status.includes('escrow')) {
        return 'border-indigo-200 bg-indigo-50 text-indigo-700';
    }
    if (status.includes('completed') || status.includes('delivered')) {
        return 'border-emerald-200 bg-emerald-50 text-emerald-700';
    }
    if (status.includes('cancelled') || status.includes('refunded')) {
        return 'border-rose-200 bg-rose-50 text-rose-700';
    }
    if (status.includes('processing') || status.includes('shipped') || status.includes('paid')) {
        return 'border-amber-200 bg-amber-50 text-amber-700';
    }

    return 'border-slate-200 bg-slate-100 text-slate-700';
}

function buyerOrderFilterKey(item) {
    const status = String(item.status || '').toLowerCase();
    const escrowState = String(item.escrowState || '').toLowerCase();

    if (escrowState && !['released', 'not_active', 'inactive', ''].includes(escrowState)) {
        return 'escrow';
    }
    if (status.includes('completed') || status.includes('delivered')) {
        return 'completed';
    }
    if (status.includes('cancelled') || status.includes('refunded')) {
        return 'cancelled';
    }

    return 'all';
}

function BuyerDashboard({ state, addToCart, toggleWishlist }) {
    const buyerOps = state.buyerOps || {};
    const walletSummary = buyerOps.walletSummary || {};
    const detailedOrders = buyerOps.ordersDetailed || [];
    const activeOrders = detailedOrders.filter((order) => !['completed', 'cancelled', 'refunded'].includes(String(order.status || '').toLowerCase()));
    const recentOrders = detailedOrders.slice(0, 5);
    const recentlyViewed = buyerOps.recentlyViewed || [];
    const favoriteStores = buyerOps.favoriteStores || [];
    const recommendations = state.products.filter((product) => !state.wishlist.includes(product.id)).slice(0, 4);
    const notifications = (buyerOps.notifications || []).filter((item) => !(item.is_read ?? item.read));
    const unreadNotificationCount = buyerOps.unreadNotificationCount ?? notifications.length;

    return (
        <BuyerPanelShell
            activeKey="dashboard"
            eyebrow="Buyer workspace"
            title="One command center for shopping, escrow, and account trust"
            description="Shared authentication, role-aware switching, wallet balance, notifications, active escrow, and buyer-side account controls all stay synchronized in one premium buyer panel."
            aside={<div className="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm"><p className="text-xs font-black uppercase tracking-[0.18em] text-slate-500">Role switch</p><div className="mt-4 grid gap-3">{state.user?.hasSellerProfile ? <Button asChild variant="outline" className="justify-start"><Link href="/seller/dashboard"><Store className="size-4" />Seller workspace</Link></Button> : null}<Button asChild className="justify-start bg-slate-950 hover:bg-indigo-600"><Link href="/marketplace"><ShoppingBag className="size-4" />Continue shopping</Link></Button></div></div>}
        >
            <BuyerMetricsGrid items={[
                { label: 'Wallet balance', value: money(walletSummary.available), hint: `${walletSummary.topUps || 0} recent top-ups`, icon: WalletCards },
                { label: 'Escrow hold', value: money(walletSummary.held), hint: 'Protected until fulfillment clears', icon: ShieldCheck },
                { label: 'Active orders', value: activeOrders.length, hint: `${detailedOrders.length} total orders`, icon: Truck },
                { label: 'Unread alerts', value: unreadNotificationCount, hint: `${favoriteStores.length} favorite stores`, icon: Bell },
            ]} />

            <section className="grid gap-5 2xl:grid-cols-[1.3fr_0.9fr]">
                <Panel title="Recent orders" icon={Truck} actions={<Button asChild variant="outline"><Link href="/orders">Open orders</Link></Button>}>
                    <div className="grid gap-3">
                        {recentOrders.length ? recentOrders.map((order) => (
                            <div key={order.id} className="rounded-2xl border border-slate-200 bg-slate-50/70 p-4">
                                <div className="flex flex-wrap items-start justify-between gap-3">
                                    <div>
                                        <p className="font-extrabold text-slate-950">{order.code}</p>
                                        <p className="mt-1 text-sm font-semibold text-slate-500">{order.product}</p>
                                        <p className="mt-2 text-xs font-bold uppercase tracking-wide text-slate-400">{order.seller} {order.paymentMethod ? `· ${order.paymentMethod}` : ''}</p>
                                    </div>
                                    <Badge variant={String(order.status).toLowerCase().includes('completed') ? 'success' : 'secondary'}>{String(order.status || 'processing').replaceAll('_', ' ')}</Badge>
                                </div>
                                <div className="mt-3 flex flex-wrap gap-2 text-xs font-bold text-slate-500">
                                    <span className="rounded-full bg-white px-3 py-1 ring-1 ring-slate-200">{money(order.amount)}</span>
                                    <span className="rounded-full bg-white px-3 py-1 ring-1 ring-slate-200">Escrow: {order.escrowState || 'n/a'}</span>
                                    {order.trackingId ? <span className="rounded-full bg-white px-3 py-1 ring-1 ring-slate-200">Tracking: {order.trackingId}</span> : null}
                                </div>
                            </div>
                        )) : <Empty title="No buyer orders yet" action="/marketplace" label="Start shopping" />}
                    </div>
                </Panel>

                <Panel title="Trust and support" icon={ShieldCheck}>
                    <div className="grid gap-3">
                        {[
                            { label: 'Pending payments', value: detailedOrders.filter((order) => String(order.paymentStatus || '').toLowerCase().includes('pending')).length, hint: 'Orders waiting for final funding' },
                            { label: 'Refund requests', value: (buyerOps.returns || []).filter((item) => String(item.refundStatus || '').toLowerCase().includes('pending')).length, hint: 'Returns currently in review' },
                            { label: 'Support tickets', value: state.supportTickets.length, hint: 'Buyer and seller conversations' },
                            { label: 'Favorite stores', value: favoriteStores.length, hint: 'Most active merchants in your history' },
                        ].map((item) => (
                            <div key={item.label} className="rounded-2xl border border-slate-200 p-4">
                                <p className="text-xs font-black uppercase tracking-[0.16em] text-slate-400">{item.label}</p>
                                <p className="mt-2 text-2xl font-black tracking-tight text-slate-950">{item.value}</p>
                                <p className="mt-1 text-sm font-semibold text-slate-500">{item.hint}</p>
                            </div>
                        ))}
                    </div>
                </Panel>
            </section>

            <section className="grid gap-5 2xl:grid-cols-[1fr_1fr]">
                <Panel title="Recently viewed" icon={Eye} actions={<Button asChild variant="outline"><Link href="/recently-viewed">See all</Link></Button>}>
                    <div className="grid gap-3 sm:grid-cols-2">
                        {recentlyViewed.length ? recentlyViewed.slice(0, 4).map((product) => (
                            <div key={product.id} className="grid grid-cols-[72px_1fr] gap-3 rounded-2xl border border-slate-200 p-3">
                                <ProductMedia src={product.image} alt={product.title} className="size-[72px] rounded-xl object-cover" />
                                <div className="min-w-0">
                                    <Link href={`/products/${product.id}`} className="line-clamp-2 font-bold text-slate-950 hover:text-indigo-600">{product.title}</Link>
                                    <p className="mt-2 text-sm font-extrabold text-rose-600">{money(product.price)}</p>
                                </div>
                            </div>
                        )) : <p className="rounded-2xl bg-slate-50 p-5 text-sm font-semibold text-slate-500">Viewed products will appear here after you browse the catalog.</p>}
                    </div>
                </Panel>
                <Panel title="Recommended for you" icon={Sparkles}>
                    <div className="grid gap-5 sm:grid-cols-2">
                        {recommendations.map((product) => (
                            <ProductCard key={`buyer-rec-${product.id}`} product={product} addToCart={addToCart} toggleWishlist={toggleWishlist} wished={state.wishlist.includes(product.id)} />
                        ))}
                    </div>
                </Panel>
            </section>
        </BuyerPanelShell>
    );
}

function BuyerOrdersCenter({ state, initialTab = 'orders' }) {
    const buyerOps = state.buyerOps || {};
    const orders = buyerOps.ordersDetailed || [];
    const escrows = buyerOps.escrows || [];
    const returns = buyerOps.returns || [];
    const [activeOrderFilter, setActiveOrderFilter] = useState('all');
    const [visibleOrderCount, setVisibleOrderCount] = useState(PAGE_SIZE);
    const activeOrdersCount = orders.filter((item) => !['completed', 'cancelled', 'refunded'].includes(String(item.status || '').toLowerCase())).length;
    const escrowOrdersCount = escrows.length;
    const returnCount = returns.length;
    const orderFilters = [
        { key: 'all', label: 'All', count: orders.length },
        { key: 'escrow', label: 'In Escrow', count: orders.filter((item) => buyerOrderFilterKey(item) === 'escrow').length },
        { key: 'completed', label: 'Completed', count: orders.filter((item) => buyerOrderFilterKey(item) === 'completed').length },
        { key: 'cancelled', label: 'Cancelled', count: orders.filter((item) => buyerOrderFilterKey(item) === 'cancelled').length },
    ];
    const filteredOrders = activeOrderFilter === 'all'
        ? orders
        : orders.filter((item) => buyerOrderFilterKey(item) === activeOrderFilter);
    const displayedOrders = filteredOrders.slice(0, visibleOrderCount);
    const canLoadMoreOrders = initialTab === 'orders' && visibleOrderCount < filteredOrders.length;

    useEffect(() => {
        setVisibleOrderCount(PAGE_SIZE);
    }, [initialTab, orders.length, activeOrderFilter]);

    return (
        <BuyerPanelShell
            activeKey="orders"
            eyebrow="Buyer orders"
            title="Order management, escrow visibility, and post-purchase actions"
            description="Track physical and digital orders, monitor escrow state, inspect timelines, and keep return and refund workflows together."
        >
            <BuyerMetricsGrid items={[
                { label: 'All orders', value: orders.length, hint: 'Buyer-side order records', icon: ReceiptText },
                { label: 'Active orders', value: activeOrdersCount, hint: 'Still moving through fulfillment', icon: Truck },
                { label: 'Escrow cases', value: escrowOrdersCount, hint: 'Protected payment flows', icon: ShieldCheck },
                { label: 'Returns & refunds', value: returnCount, hint: 'Post-purchase requests', icon: FileText },
            ]} />
            <Panel title={initialTab === 'escrow-orders' ? 'Escrow orders' : 'Buyer orders'} icon={ShieldCheck}>
                {initialTab === 'orders' ? (
                    <div className="mb-6 inline-flex flex-wrap gap-2 rounded-[28px] border border-slate-200 bg-slate-100/90 p-2 shadow-[0_14px_32px_-28px_rgba(15,23,42,0.55)]">
                        {orderFilters.map((filter) => (
                            <button
                                key={filter.key}
                                type="button"
                                onClick={() => setActiveOrderFilter(filter.key)}
                                className={cn(
                                    'rounded-2xl px-5 py-3 text-sm font-extrabold transition',
                                    activeOrderFilter === filter.key
                                        ? 'bg-white text-slate-950 shadow-[0_10px_24px_-18px_rgba(15,23,42,0.75)] ring-1 ring-slate-200'
                                        : 'text-slate-500 hover:bg-white/70 hover:text-slate-800',
                                )}
                            >
                                {filter.label}
                                <span className="ml-2 text-xs font-black text-slate-400">{filter.count}</span>
                            </button>
                        ))}
                    </div>
                ) : null}
                <div className={cn('grid gap-4', initialTab === 'orders' ? '2xl:grid-cols-2' : 'xl:grid-cols-2')}>
                    {(initialTab === 'escrow-orders' ? escrows : displayedOrders).length ? (initialTab === 'escrow-orders' ? escrows : displayedOrders).map((item) => (
                        initialTab === 'orders' ? (
                            <article key={item.id} className="rounded-[20px] border border-slate-200 bg-white p-3.5 shadow-[0_12px_28px_-28px_rgba(15,23,42,0.3)] transition hover:border-slate-300 hover:shadow-[0_16px_34px_-28px_rgba(15,23,42,0.36)]">
                                <div className="flex flex-col gap-3 2xl:flex-row 2xl:items-center 2xl:justify-between">
                                    <div className="flex min-w-0 gap-3">
                                        <ProductMedia src={item.image} alt={item.product} className="size-[72px] shrink-0 rounded-lg object-cover ring-1 ring-slate-200" />
                                        <div className="min-w-0">
                                            <div className="flex flex-wrap items-center gap-2">
                                                <Link href={`/order-details?order=${item.id}`} className="text-base font-bold tracking-tight text-slate-950 hover:text-indigo-600">
                                                    {item.code || item.orderNumber}
                                                </Link>
                                                <span className={cn('inline-flex items-center rounded-md border px-2 py-1 text-[11px] font-bold', orderStatusPillClasses(item))}>
                                                    {humanizeOrderState(item.escrowState && !['released', 'not_active', 'inactive'].includes(String(item.escrowState).toLowerCase()) ? 'In Escrow' : item.status)}
                                                </span>
                                            </div>
                                            <p className="mt-1.5 line-clamp-2 text-sm font-semibold leading-5 tracking-tight text-slate-800 md:text-[15px]">
                                                {item.product}
                                            </p>
                                            <p className="mt-1 text-xs font-medium text-slate-500 md:text-sm">
                                                Purchased on {item.placedAt ? new Date(item.placedAt).toLocaleDateString('en-US', { month: 'short', day: 'numeric' }) : 'recently'}
                                                {' • '}
                                                Seller: {item.seller || 'Seller'}
                                            </p>
                                        </div>
                                    </div>
                                    <div className="flex items-end justify-between gap-2.5 border-t border-slate-100 pt-2.5 2xl:min-w-[132px] 2xl:flex-col 2xl:items-end 2xl:justify-center 2xl:border-t-0 2xl:border-l 2xl:border-slate-100 2xl:pl-4 2xl:pt-0">
                                        <div className="text-left 2xl:text-right">
                                            <p className="text-xs font-black uppercase tracking-[0.22em] text-slate-400">Total</p>
                                            <p className="mt-1 text-lg font-bold tracking-tight text-slate-950 md:text-xl">{money(item.amount)}</p>
                                        </div>
                                        <div className="text-right">
                                            <p className="text-[11px] font-semibold text-slate-500 md:text-xs">{humanizeOrderState(item.paymentStatus || 'pending')}</p>
                                            <p className="mt-0.5 text-[11px] font-bold uppercase tracking-[0.14em] text-slate-400">{item.paymentMethod || item.currency || 'Wallet'}</p>
                                        </div>
                                    </div>
                                </div>
                            </article>
                        ) : (
                            <div key={item.id} className="rounded-2xl border border-slate-200 bg-slate-50/70 p-4">
                                <div className="flex flex-wrap items-start justify-between gap-3">
                                    <div>
                                        <Link href={`/order-details?order=${item.id}`} className="font-extrabold text-slate-950 hover:text-indigo-600">{item.code || item.orderNumber}</Link>
                                        <p className="mt-1 text-sm font-semibold text-slate-500">{item.product}</p>
                                    </div>
                                    <Badge variant="secondary">{humanizeOrderState(item.status || item.state || 'active')}</Badge>
                                </div>
                                <div className="mt-3 grid gap-2 text-sm font-semibold text-slate-600 md:grid-cols-2">
                                    <p>Amount: {money(item.amount || item.heldAmount)}</p>
                                    <p>Escrow: {item.escrowState || item.state || 'n/a'}</p>
                                    <p>Payment: {item.paymentMethod || item.currency || 'n/a'}</p>
                                    <p>Tracking: {item.trackingId || item.carrier || 'Not attached yet'}</p>
                                </div>
                                {(item.timeline || []).length ? <div className="mt-4 grid gap-2">{item.timeline.slice(0, 4).map((step) => <div key={step.id} className="rounded-xl bg-white px-3 py-2 text-xs font-bold text-slate-500 ring-1 ring-slate-200">{step.to || step.type} {step.createdAt ? `· ${new Date(step.createdAt).toLocaleString()}` : ''}</div>)}</div> : null}
                            </div>
                        )
                    )) : <p className="rounded-2xl bg-slate-50 p-6 text-sm font-semibold text-slate-500">No matching order records for this section yet.</p>}
                </div>
                {canLoadMoreOrders ? (
                    <div className="mt-5 flex justify-center">
                        <Button variant="outline" className="rounded-full px-6" onClick={() => setVisibleOrderCount((current) => Math.min(current + PAGE_SIZE, filteredOrders.length))}>
                            Load more orders
                        </Button>
                    </div>
                ) : null}
            </Panel>
            {['refund-requests', 'return-requests', 'replacement-requests'].includes(initialTab) ? (
                <Panel title="Return and refund queue" icon={FileText}>
                    <div className="grid gap-3 xl:grid-cols-3">
                        {returns.length ? returns.map((item) => (
                            <div key={item.id} className="rounded-2xl border border-slate-200 p-4">
                                <div className="flex flex-wrap items-center justify-between gap-3">
                                    <p className="font-extrabold text-slate-950">{item.code}</p>
                                    <Badge variant="secondary">{String(item.status || 'pending').replaceAll('_', ' ')}</Badge>
                                </div>
                                <p className="mt-2 text-sm font-semibold text-slate-500">{item.reason || 'Return request'}</p>
                                <p className="mt-2 text-xs font-bold uppercase tracking-wide text-slate-400">Refund: {item.refundStatus || 'Pending'}</p>
                            </div>
                        )) : <p className="rounded-2xl bg-slate-50 p-6 text-sm font-semibold text-slate-500">Return and refund submissions will appear here.</p>}
                    </div>
                </Panel>
            ) : null}
        </BuyerPanelShell>
    );
}

function EscrowDetailTimeline({ timeline = [] }) {
    return (
        <section className="overflow-hidden rounded-[28px] border border-slate-200 bg-white shadow-[0_24px_60px_-44px_rgba(15,23,42,0.28)]">
            <div className="h-1.5 bg-slate-100">
                <div className="h-full w-1/2 rounded-r-full bg-indigo-500" />
            </div>
            <div className="grid gap-6 px-5 py-7 md:grid-cols-4">
                {timeline.map((step) => {
                    const done = step.state === 'completed';
                    const active = step.state === 'active';
                    return (
                        <div key={step.key} className="relative text-center">
                            <div className={cn('mx-auto flex size-12 items-center justify-center rounded-full border text-white shadow-[0_16px_36px_-28px_rgba(79,70,229,0.8)]', done || active ? 'border-indigo-500 bg-indigo-500' : 'border-slate-200 bg-slate-100 text-slate-400')}>
                                <Clock className="size-4.5" />
                            </div>
                            <p className={cn('mt-4 text-sm font-black uppercase tracking-[0.12em]', done || active ? 'text-slate-950' : 'text-slate-400')}>{step.label}</p>
                            <p className="mt-1 text-sm font-semibold text-slate-500">{formatTimeOnly(step.at)}</p>
                        </div>
                    );
                })}
            </div>
        </section>
    );
}

function EscrowSummaryCard({ detail }) {
    const item = detail?.items?.[0];
    return (
        <section className="overflow-hidden rounded-[28px] border border-slate-200 bg-white shadow-[0_24px_60px_-44px_rgba(15,23,42,0.28)]">
            <div className="flex items-center gap-3 border-b border-slate-100 px-6 py-5">
                <ReceiptText className="size-5 text-slate-400" />
                <h2 className="text-xl font-black tracking-tight text-slate-950">Order Summary</h2>
            </div>
            <div className="space-y-5 px-6 py-6">
                <div className="flex gap-4">
                    <ProductMedia src={item?.image_url} alt={item?.title} className="size-20 rounded-2xl object-cover ring-1 ring-slate-200" />
                    <div className="min-w-0 flex-1">
                        <p className="line-clamp-2 text-2xl font-black tracking-tight text-slate-950">{item?.title || 'Digital order'}</p>
                        <p className="mt-1 text-sm font-black uppercase tracking-[0.16em] text-indigo-600">{detail?.seller?.name || 'Seller'}</p>
                        <p className="mt-2 text-sm font-semibold text-slate-500">Qty: {item?.quantity || 1} • {humanizeOrderState(detail?.order?.product_type || 'digital')}</p>
                    </div>
                </div>
                <div className="space-y-3 text-sm font-semibold text-slate-500">
                    <div className="flex items-center justify-between"><span>Subtotal</span><span className="font-black text-slate-950">{money(detail?.order?.subtotal)}</span></div>
                    <div className="flex items-center justify-between"><span>Shipping / Delivery</span><span className="font-black text-slate-950">{asNumber(detail?.order?.delivery_fee) > 0 ? money(detail?.order?.delivery_fee) : 'Digital delivery'}</span></div>
                    <div className="flex items-center justify-between"><span>Escrow Fee</span><span className="font-black text-slate-950">{money(detail?.order?.escrow_fee)}</span></div>
                    <div className="flex items-center justify-between"><span>Discount</span><span className="font-black text-slate-950">{money(detail?.order?.discount)}</span></div>
                    <div className="flex items-center justify-between"><span>Tax</span><span className="font-black text-slate-950">{money(detail?.order?.tax)}</span></div>
                    <div className="border-t border-slate-100 pt-3">
                        <div className="flex items-center justify-between"><span className="text-lg font-black text-slate-950">Total Paid</span><span className="text-3xl font-black tracking-tight text-slate-950">{money(detail?.order?.total_paid)}</span></div>
                    </div>
                </div>
            </div>
        </section>
    );
}

function EscrowChatPanel({ detail, sendEscrowMessage, markEscrowMessagesRead, pendingAction }) {
    const [body, setBody] = useState('');
    const [attachments, setAttachments] = useState([]);
    const orderId = detail?.order?.id;
    const isSending = pendingAction === `escrow:${orderId}:message`;

    useEffect(() => {
        if (orderId) {
            markEscrowMessagesRead(orderId).catch(() => {});
        }
    }, [orderId]);

    const submit = async () => {
        if (!orderId || (!body.trim() && attachments.length === 0)) return;
        await sendEscrowMessage(orderId, { body: body.trim(), attachments });
        setBody('');
        setAttachments([]);
    };

    return (
        <section className="flex min-h-[680px] flex-col overflow-hidden rounded-[28px] border border-slate-200 bg-white shadow-[0_24px_60px_-44px_rgba(15,23,42,0.28)]">
            <div className="flex items-center justify-between border-b border-slate-100 px-6 py-5">
                <div>
                    <div className="flex items-center gap-3">
                        <MessageSquareText className="size-5 text-indigo-500" />
                        <h2 className="text-2xl font-black tracking-tight text-slate-950">Escrow Chat</h2>
                    </div>
                    <p className="mt-1 text-sm font-black uppercase tracking-[0.18em] text-slate-400">Live securely monitored</p>
                </div>
                <div className="inline-flex items-center gap-2 text-sm font-black uppercase tracking-[0.18em] text-emerald-600"><span className="size-2.5 rounded-full bg-emerald-500" />Secure</div>
            </div>
            <div className="flex-1 space-y-4 overflow-y-auto bg-[linear-gradient(180deg,#ffffff_0%,#f8fbff_100%)] px-6 py-6">
                <div className="flex justify-center">
                    <div className="rounded-full border border-slate-200 bg-white px-5 py-2 text-xs font-black uppercase tracking-[0.18em] text-slate-500 shadow-sm">Order funds secured in escrow.</div>
                </div>
                {(detail?.messages || []).map((message) => (
                    <div key={message.id} className={cn('flex', message.from_me ? 'justify-end' : 'justify-start')}>
                        <div className={cn('max-w-[82%] rounded-[24px] px-5 py-4 shadow-[0_14px_32px_-28px_rgba(15,23,42,0.4)]', message.from_me ? 'bg-[linear-gradient(135deg,#4f46e5_0%,#5c4df5_100%)] text-white' : 'border border-slate-200 bg-white text-slate-900')}>
                            <p className="text-[11px] font-black uppercase tracking-[0.18em] opacity-70">{message.sender_role}</p>
                            {message.body ? <p className="mt-2 text-lg font-semibold leading-8">{message.body}</p> : null}
                            {(message.attachments || []).length ? (
                                <div className="mt-3 grid gap-2">
                                    {message.attachments.map((attachment) => (
                                        <a key={attachment.id} href={attachment.download_url} className={cn('inline-flex items-center gap-2 rounded-2xl px-3 py-2 text-sm font-bold', message.from_me ? 'bg-white/10 text-white' : 'bg-slate-50 text-slate-700')}>
                                            <Paperclip className="size-4" />{attachment.name}
                                        </a>
                                    ))}
                                </div>
                            ) : null}
                            <p className={cn('mt-3 text-xs font-bold', message.from_me ? 'text-white/70' : 'text-slate-400')}>{formatTimeOnly(message.created_at)}</p>
                        </div>
                    </div>
                ))}
            </div>
            <div className="border-t border-slate-100 bg-white p-4">
                {attachments.length ? <p className="mb-3 text-xs font-bold text-slate-500">{attachments.length} attachment(s) ready</p> : null}
                <div className="flex gap-3">
                    <label className="flex h-14 w-14 shrink-0 cursor-pointer items-center justify-center rounded-2xl border border-slate-200 bg-slate-50 text-slate-400 transition hover:border-indigo-200 hover:text-indigo-600">
                        <Paperclip className="size-5" />
                        <input type="file" multiple className="sr-only" onChange={(event) => setAttachments(Array.from(event.target.files || []))} />
                    </label>
                    <Input value={body} onChange={(event) => setBody(event.target.value)} placeholder="Type message securely..." className="h-14 rounded-2xl border-slate-200 bg-slate-50 px-5 text-base font-semibold" />
                    <button type="button" disabled={isSending || (!body.trim() && attachments.length === 0)} onClick={submit} className="flex h-14 w-14 shrink-0 items-center justify-center rounded-2xl bg-slate-950 text-white shadow-[0_18px_42px_-28px_rgba(15,23,42,0.7)] transition hover:bg-indigo-600 disabled:cursor-not-allowed disabled:opacity-50">
                        <Send className="size-5" />
                    </button>
                </div>
            </div>
        </section>
    );
}

function EscrowOrderDetails({ state, mode = 'buyer', releaseEscrowFunds, openOrderDispute, sendEscrowMessage, markEscrowMessagesRead, mergeIncomingEscrowMessage, refreshEscrowOrderDetail, submitSellerDelivery, pendingAction }) {
    const detail = state.escrowOrderDetail || state.buyerOps?.selectedEscrowOrder || state.sellerOps?.selectedEscrowOrder || null;
    const orderId = detail?.order?.id;
    const threadId = detail?.chat?.thread_id;
    const isBuyer = detail?.permissions?.is_buyer;
    const isSeller = detail?.permissions?.is_seller;
    const [secondsLeft, setSecondsLeft] = useState(detail?.escrow?.timer?.seconds_remaining ?? null);
    const [deliveryForm, setDeliveryForm] = useState({ delivery_message: '', external_delivery_url: '', delivery_version: 'v1', files: [] });

    useEffect(() => {
        setSecondsLeft(detail?.escrow?.timer?.seconds_remaining ?? null);
        if (detail?.delivery?.version) {
            setDeliveryForm((current) => ({ ...current, delivery_version: detail.delivery.version || current.delivery_version }));
        }
    }, [detail?.escrow?.timer?.server_now, detail?.escrow?.timer?.seconds_remaining, detail?.delivery?.version]);

    useEffect(() => {
        if (secondsLeft === null) return undefined;
        const baseSeconds = Number(detail?.escrow?.timer?.seconds_remaining ?? 0);
        const startedAt = Date.now();
        const timer = window.setInterval(() => {
            const elapsed = Math.floor((Date.now() - startedAt) / 1000);
            setSecondsLeft(Math.max(0, baseSeconds - elapsed));
        }, 1000);
        return () => window.clearInterval(timer);
    }, [detail?.escrow?.timer?.server_now, detail?.escrow?.timer?.seconds_remaining]);

    useEffect(() => {
        if (!orderId) return undefined;
        const interval = window.setInterval(() => {
            refreshEscrowOrderDetail(orderId).catch(() => {});
        }, 15000);
        return () => window.clearInterval(interval);
    }, [orderId]);

    useEffect(() => {
        if (!threadId || !orderId) {
            return undefined;
        }

        const echo = getEcho();
        if (!echo) {
            return undefined;
        }

        const channelName = `chat.thread.${threadId}`;
        const channel = echo.private(channelName);
        let refreshTimer = null;

        const scheduleRefresh = () => {
            if (refreshTimer !== null) {
                window.clearTimeout(refreshTimer);
            }

            refreshTimer = window.setTimeout(() => {
                refreshEscrowOrderDetail(orderId).catch(() => {});
            }, 350);
        };

        const handleCreated = (payload) => {
            if (Number(payload?.thread_id || 0) !== Number(threadId)) {
                return;
            }

            mergeIncomingEscrowMessage?.(threadId, payload?.message || {});
            scheduleRefresh();
        };

        channel.listen('.chat.message.created', handleCreated);

        return () => {
            channel.stopListening('.chat.message.created', handleCreated);
            echo.leave(channelName);
            if (refreshTimer !== null) {
                window.clearTimeout(refreshTimer);
            }
        };
    }, [threadId, orderId, mergeIncomingEscrowMessage, refreshEscrowOrderDetail]);

    if (!detail) {
        return (
            <BuyerPanelShell activeKey="orders" eyebrow="" title="" description="">
                <Panel title="Escrow order details" icon={ReceiptText}>
                    <p className="rounded-2xl bg-slate-50 p-6 text-sm font-semibold text-slate-500">No escrow order is available for this view yet.</p>
                </Panel>
            </BuyerPanelShell>
        );
    }

    const warning = detail?.escrow?.timer?.warning;
    const canRelease = detail?.available_actions?.release_funds;
    const canDispute = detail?.available_actions?.open_dispute;
    const isDeliverySubmitting = pendingAction === `escrow:${orderId}:delivery`;
    const backHref = mode === 'seller' ? '/seller/orders' : '/orders';
    const submitDelivery = async () => {
        if (!orderId) return;
        await submitSellerDelivery(orderId, deliveryForm);
        setDeliveryForm((current) => ({ ...current, delivery_message: '', external_delivery_url: '', files: [] }));
    };

    return (
        <BuyerPanelShell activeKey="orders" eyebrow="" title="" description="">
            <div className="space-y-6 pb-24 md:pb-6">
                <section className="flex flex-col gap-4 xl:flex-row xl:items-start xl:justify-between">
                    <div className="flex gap-3">
                        <Link href={backHref} className="inline-flex size-11 items-center justify-center rounded-2xl border border-slate-200 bg-white text-slate-500 shadow-sm transition hover:text-slate-900">
                            <ChevronLeft className="size-5" />
                        </Link>
                        <div>
                            <h1 className="text-[2.4rem] font-black leading-none tracking-tight text-slate-950">{detail.order.order_number}</h1>
                            <p className="mt-3 text-xl font-semibold text-slate-500">Placed on {formatDateTime(detail.order.placed_at)}</p>
                        </div>
                    </div>
                    <div className="text-left xl:text-right">
                        <p className="text-sm font-black uppercase tracking-[0.24em] text-slate-400">Total Paid</p>
                        <p className="mt-1 text-[3rem] font-black leading-none tracking-tight text-slate-950">{money(detail.order.total_paid)}</p>
                    </div>
                </section>

                <EscrowDetailTimeline timeline={detail.timeline || []} />

                <section className="grid gap-5 xl:grid-cols-[minmax(0,1.35fr)_minmax(360px,0.95fr)]">
                    <div className="space-y-5">
                        <section className="overflow-hidden rounded-[32px] bg-[radial-gradient(circle_at_top_right,#1f2a64_0%,#091225_55%,#07101e_100%)] px-6 py-6 text-white shadow-[0_34px_84px_-56px_rgba(9,18,37,0.88)]">
                            <div className="inline-flex items-center gap-2 rounded-2xl border border-indigo-400/20 bg-indigo-500/15 px-4 py-2 text-xs font-black uppercase tracking-[0.22em] text-indigo-100">
                                <LockKeyhole className="size-4" />Escrow active
                            </div>
                            <h2 className="mt-6 text-[2.15rem] font-black tracking-tight">{isSeller ? 'Awaiting Buyer Review' : 'Awaiting Delivery'}</h2>
                            <div className="mt-6 rounded-[28px] border border-white/10 bg-white/6 p-5 backdrop-blur">
                                <div className="grid gap-4 md:grid-cols-[1fr_minmax(220px,0.8fr)] md:items-center">
                                    <div>
                                        <p className="text-xs font-black uppercase tracking-[0.24em] text-slate-300">Time Remaining</p>
                                        <p className={cn('mt-3 text-[4rem] font-black leading-none tracking-tight', warning ? 'text-amber-300' : 'text-amber-400')}>{secondsLeft === null ? '--:--:--' : formatCountdown(secondsLeft)}</p>
                                    </div>
                                    <p className="text-xl font-semibold leading-8 text-slate-300">Funds are locked. Release them only after verifying the delivered files.</p>
                                </div>
                            </div>
                            {(canRelease || canDispute) ? (
                                <div className="mt-6 grid gap-3 md:grid-cols-2">
                                    <button type="button" disabled={!canRelease || pendingAction === `escrow:${orderId}:release`} onClick={() => { if (window.confirm('Release funds after verifying the delivery?')) releaseEscrowFunds(orderId); }} className="inline-flex h-16 items-center justify-center gap-3 rounded-[20px] bg-emerald-500 px-5 text-xl font-black text-slate-950 shadow-[0_22px_50px_-34px_rgba(16,185,129,0.8)] transition hover:bg-emerald-400 disabled:cursor-not-allowed disabled:opacity-50">
                                        Release Funds <ArrowRight className="size-5" />
                                    </button>
                                    <button type="button" disabled={!canDispute || pendingAction === `escrow:${orderId}:dispute`} onClick={() => { if (window.confirm('Open a dispute for this escrow order?')) openOrderDispute(orderId); }} className="inline-flex h-16 items-center justify-center gap-3 rounded-[20px] border border-white/15 bg-transparent px-5 text-xl font-black text-white transition hover:bg-white/8 disabled:cursor-not-allowed disabled:opacity-50">
                                        <AlertCircle className="size-5" />Open Dispute
                                    </button>
                                </div>
                            ) : null}
                        </section>

                        {isSeller ? (
                            <section className="overflow-hidden rounded-[28px] border border-slate-200 bg-white shadow-[0_24px_60px_-44px_rgba(15,23,42,0.28)]">
                                <div className="flex items-center gap-3 border-b border-slate-100 px-6 py-5">
                                    <FileUp className="size-5 text-slate-400" />
                                    <h2 className="text-xl font-black tracking-tight text-slate-950">Submit Digital Delivery</h2>
                                </div>
                                <div className="grid gap-4 px-6 py-6">
                                    <textarea value={deliveryForm.delivery_message} onChange={(event) => setDeliveryForm((current) => ({ ...current, delivery_message: event.target.value }))} placeholder="Delivery message" className="min-h-32 rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm font-semibold text-slate-800 outline-none ring-0 transition focus:border-indigo-300 focus:bg-white" />
                                    <div className="grid gap-4 md:grid-cols-2">
                                        <Input value={deliveryForm.external_delivery_url} onChange={(event) => setDeliveryForm((current) => ({ ...current, external_delivery_url: event.target.value }))} placeholder="External delivery URL" className="h-12 rounded-2xl border-slate-200 bg-slate-50 font-semibold" />
                                        <Input value={deliveryForm.delivery_version} onChange={(event) => setDeliveryForm((current) => ({ ...current, delivery_version: event.target.value }))} placeholder="Version / revision" className="h-12 rounded-2xl border-slate-200 bg-slate-50 font-semibold" />
                                    </div>
                                    <label className="flex cursor-pointer flex-col items-center justify-center rounded-[24px] border border-dashed border-slate-200 bg-slate-50 px-6 py-8 text-center transition hover:border-indigo-300 hover:bg-indigo-50/60">
                                        <Upload className="size-6 text-indigo-500" />
                                        <p className="mt-3 text-base font-black text-slate-950">Upload delivery files</p>
                                        <p className="mt-1 text-sm font-semibold text-slate-500">{deliveryForm.files.length ? `${deliveryForm.files.length} file(s) selected` : 'PDF, ZIP, images, and docs up to 25MB each'}</p>
                                        <input type="file" multiple className="sr-only" onChange={(event) => setDeliveryForm((current) => ({ ...current, files: Array.from(event.target.files || []) }))} />
                                    </label>
                                    <button type="button" disabled={isDeliverySubmitting} onClick={submitDelivery} className="inline-flex h-14 items-center justify-center gap-3 rounded-2xl bg-slate-950 text-base font-black text-white transition hover:bg-indigo-600 disabled:cursor-not-allowed disabled:opacity-50">
                                        {isDeliverySubmitting ? 'Submitting...' : 'Mark as Delivered'}
                                    </button>
                                </div>
                            </section>
                        ) : null}

                        {(detail.delivery?.files || []).length ? (
                            <section className="overflow-hidden rounded-[28px] border border-slate-200 bg-white shadow-[0_24px_60px_-44px_rgba(15,23,42,0.28)]">
                                <div className="flex items-center gap-3 border-b border-slate-100 px-6 py-5">
                                    <Download className="size-5 text-slate-400" />
                                    <h2 className="text-xl font-black tracking-tight text-slate-950">Delivered Files</h2>
                                </div>
                                <div className="grid gap-3 px-6 py-6">
                                    {detail.delivery.files.map((file) => (
                                        <a key={file.id} href={file.download_url || '#'} className="flex items-center justify-between rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm font-bold text-slate-700 transition hover:border-indigo-200 hover:bg-white">
                                            <span className="flex items-center gap-3"><FileDown className="size-4.5 text-indigo-500" />{file.name}</span>
                                            <span>{file.download_url ? 'Download' : 'Locked'}</span>
                                        </a>
                                    ))}
                                </div>
                            </section>
                        ) : null}

                        <EscrowSummaryCard detail={detail} />
                    </div>

                    <div className="space-y-5">
                        <EscrowChatPanel detail={detail} sendEscrowMessage={sendEscrowMessage} markEscrowMessagesRead={markEscrowMessagesRead} pendingAction={pendingAction} />
                        <section className="overflow-hidden rounded-[28px] border border-slate-200 bg-white shadow-[0_24px_60px_-44px_rgba(15,23,42,0.28)]">
                            <div className="flex items-center gap-3 border-b border-slate-100 px-6 py-5">
                                <ClipboardCheck className="size-5 text-slate-400" />
                                <h2 className="text-xl font-black tracking-tight text-slate-950">Activity Timeline</h2>
                            </div>
                            <div className="space-y-3 px-6 py-6">
                                {(detail.activity_timeline || []).map((entry) => (
                                    <div key={entry.id} className="rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3">
                                        <p className="text-sm font-black uppercase tracking-[0.14em] text-slate-950">{entry.title}</p>
                                        <p className="mt-1 text-sm font-semibold text-slate-500">{entry.body || 'Order activity recorded.'}</p>
                                        <p className="mt-2 text-xs font-bold text-slate-400">{formatDateTime(entry.created_at)}</p>
                                    </div>
                                ))}
                            </div>
                        </section>
                    </div>
                </section>

                {(canRelease || canDispute) ? (
                    <div className="fixed inset-x-0 bottom-0 z-30 border-t border-slate-200 bg-white/95 p-3 backdrop-blur md:hidden">
                        <div className="grid grid-cols-2 gap-3">
                            <button type="button" disabled={!canRelease || pendingAction === `escrow:${orderId}:release`} onClick={() => { if (window.confirm('Release funds after verifying the delivery?')) releaseEscrowFunds(orderId); }} className="h-12 rounded-2xl bg-emerald-500 text-sm font-black text-slate-950 disabled:opacity-50">Release</button>
                            <button type="button" disabled={!canDispute || pendingAction === `escrow:${orderId}:dispute`} onClick={() => { if (window.confirm('Open a dispute for this escrow order?')) openOrderDispute(orderId); }} className="h-12 rounded-2xl border border-slate-300 bg-white text-sm font-black text-slate-950 disabled:opacity-50">Dispute</button>
                        </div>
                    </div>
                ) : null}
            </div>
        </BuyerPanelShell>
    );
}

function BuyerOrderDetails(props) {
    return <EscrowOrderDetails {...props} mode="buyer" />;
}

function SellerOrderDetails(props) {
    return <EscrowOrderDetails {...props} mode="seller" />;
}

function BuyerWalletCenter({ state, initialTab = 'wallet', saveBuyerPaymentMethod, setDefaultBuyerPaymentMethod, deleteBuyerPaymentMethod, requestBuyerWalletTopUp, pendingAction = '' }) {
    const buyerOps = state.buyerOps || {};
    const wallets = buyerOps.wallets || [];
    const summary = buyerOps.walletSummary || {};
    const topUps = wallets.flatMap((wallet) => (wallet.recentTopUps || []).map((item) => ({ ...item, walletType: wallet.type, walletCurrency: wallet.currency })));
    const transactions = wallets.flatMap((wallet) => (wallet.recentEntries || []).map((item) => ({ ...item, walletType: wallet.type, walletCurrency: wallet.currency })));
    const paymentMethods = buyerOps.paymentMethods || [];
    const [editingMethodId, setEditingMethodId] = useState(null);
    const [methodFormOpen, setMethodFormOpen] = useState(false);
    const [fundActionTab, setFundActionTab] = useState('topup');
    const [activityFilter, setActivityFilter] = useState('all');
    const [activityFilterOpen, setActivityFilterOpen] = useState(false);
    const [activityLimit, setActivityLimit] = useState(4);
    const [methodForm, setMethodForm] = useState({
        kind: 'card',
        label: '',
        subtitle: '',
        note: '',
        cardholder_name: '',
        card_brand: '',
        last4: '',
        account_name: '',
        mobile_number: '',
        bank_name: '',
        account_number: '',
        branch: '',
        routing_number: '',
        is_default: paymentMethods.length === 0,
    });
    const [topUpForm, setTopUpForm] = useState(() => {
        const firstWallet = wallets[0] || {};
        return {
            walletId: firstWallet.id || '',
            amount: '',
            payment_method: 'manual',
            payment_reference: '',
        };
    });

    useEffect(() => {
        if (!topUpForm.walletId && wallets.length) {
            setTopUpForm((current) => ({ ...current, walletId: wallets[0].id || '' }));
        }
    }, [wallets.length, topUpForm.walletId]);

    const resetMethodForm = () => {
        setEditingMethodId(null);
        setMethodForm({
            kind: 'card',
            label: '',
            subtitle: '',
            note: '',
            cardholder_name: '',
            card_brand: '',
            last4: '',
            account_name: '',
            mobile_number: '',
            bank_name: '',
            account_number: '',
            branch: '',
            routing_number: '',
            is_default: paymentMethods.length === 0,
        });
        setMethodFormOpen(false);
    };

    const openMethodEditor = (method = null) => {
        if (!method) {
            resetMethodForm();
            setMethodFormOpen(true);
            return;
        }
        const details = method.details || {};
        setEditingMethodId(method.id);
        setMethodForm({
            kind: method.kind || 'card',
            label: method.label || '',
            subtitle: method.subtitle || '',
            note: details.note || '',
            cardholder_name: details.cardholder_name || '',
            card_brand: details.card_brand || '',
            last4: details.last4 || '',
            account_name: details.account_name || '',
            mobile_number: details.mobile_number || '',
            bank_name: details.bank_name || '',
            account_number: details.account_number || '',
            branch: details.branch || '',
            routing_number: details.routing_number || '',
            is_default: Boolean(method.isDefault),
        });
        setMethodFormOpen(true);
    };

    const paymentMethodDetails = () => {
        if (methodForm.kind === 'card') {
            return {
                cardholder_name: methodForm.cardholder_name,
                card_brand: methodForm.card_brand,
                last4: methodForm.last4,
                note: methodForm.note,
            };
        }
        if (['bkash', 'nagad'].includes(methodForm.kind)) {
            return {
                account_name: methodForm.account_name,
                mobile_number: methodForm.mobile_number,
                note: methodForm.note,
            };
        }
        return {
            account_name: methodForm.account_name,
            bank_name: methodForm.bank_name,
            account_number: methodForm.account_number,
            branch: methodForm.branch,
            routing_number: methodForm.routing_number,
            note: methodForm.note,
        };
    };

    const paymentMethodSummary = () => {
        const details = paymentMethodDetails();
        if (methodForm.kind === 'card') {
            return [details.card_brand, details.cardholder_name, details.last4 ? `•••• ${details.last4}` : '']
                .filter(Boolean)
                .join(' · ');
        }
        if (['bkash', 'nagad'].includes(methodForm.kind)) {
            return [details.account_name, details.mobile_number].filter(Boolean).join(' · ');
        }

        return [details.bank_name, details.account_number ? `A/C ${details.account_number}` : ''].filter(Boolean).join(' · ');
    };

    const submitPaymentMethod = async () => {
        const normalizedKind = methodForm.kind === 'bkash'
            ? 'bKash'
            : methodForm.kind === 'nagad'
                ? 'Nagad'
                : methodForm.kind === 'bank'
                    ? 'Bank Transfer'
                    : 'Card';

        await saveBuyerPaymentMethod({
            kind: methodForm.kind,
            label: methodForm.label || normalizedKind,
            subtitle: paymentMethodSummary(),
            details: paymentMethodDetails(),
            is_default: methodForm.is_default,
        }, editingMethodId);
        resetMethodForm();
    };

    const submitTopUp = async () => {
        if (!topUpForm.walletId) return;
        await requestBuyerWalletTopUp(topUpForm.walletId, {
            amount: topUpForm.amount,
            payment_method: topUpForm.payment_method,
            payment_reference: topUpForm.payment_reference,
        });
        setTopUpForm((current) => ({ ...current, amount: '', payment_reference: '' }));
    };

    const moneyFixed = (value) => `৳${asNumber(value).toLocaleString('en-BD', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    })}`;

    const titleCase = (value) => String(value || '')
        .replaceAll('_', ' ')
        .replace(/\b\w/g, (char) => char.toUpperCase());

    const escapePdfText = (value) => String(value || '')
        .replaceAll('\\', '\\\\')
        .replaceAll('(', '\\(')
        .replaceAll(')', '\\)');

    const formatActivityDate = (value) => {
        if (!value) return 'Pending update';
        const date = new Date(value);
        if (Number.isNaN(date.getTime())) return 'Pending update';

        const now = new Date();
        const today = new Date(now.getFullYear(), now.getMonth(), now.getDate());
        const target = new Date(date.getFullYear(), date.getMonth(), date.getDate());
        const diffDays = Math.round((today.getTime() - target.getTime()) / 86400000);
        const timeLabel = new Intl.DateTimeFormat('en-US', {
            hour: 'numeric',
            minute: '2-digit',
            hour12: true,
        }).format(date);

        if (diffDays === 0) return `Today, ${timeLabel}`;
        if (diffDays === 1) return `Yesterday, ${timeLabel}`;

        return new Intl.DateTimeFormat('en-US', {
            month: 'short',
            day: 'numeric',
            year: 'numeric',
        }).format(date);
    };

    const activityItems = [
        ...topUps.map((item) => ({
            id: `topup-${item.id}`,
            kind: 'topup',
            title: 'Top-up',
            subtitle: [formatActivityDate(item.createdAt), item.paymentMethod ? titleCase(item.paymentMethod) : item.walletType].filter(Boolean).join(' • '),
            amount: asNumber(item.amount || item.requested_amount),
            signedAmount: `+${moneyFixed(item.amount || item.requested_amount).replace('৳', '')}`,
            positive: true,
            status: String(item.status || 'pending'),
            statusLabel: titleCase(item.status || 'pending'),
            tone: 'success',
            icon: Download,
            createdAt: item.createdAt || item.reviewedAt,
            walletLabel: `${item.walletType || 'Buyer'} wallet`,
            methodLabel: item.paymentMethod ? titleCase(item.paymentMethod) : 'Wallet top-up',
            referenceLabel: item.paymentReference || `TOPUP-${item.id}`,
            detailLabel: item.paymentReference ? `Reference ${item.paymentReference}` : 'Manual funding request',
        })),
        ...transactions.map((item) => {
            const label = item.entryType?.includes('refund')
                ? 'Refund'
                : item.entryType?.includes('withdraw')
                    ? 'Withdrawal'
                    : item.entryType?.includes('hold') || item.entryType?.includes('escrow')
                        ? 'Payment'
                        : titleCase(item.entryType || 'Wallet transaction');
            const positive = String(item.entrySide || '').toLowerCase() === 'credit';
            const inferredTone = item.entryType?.includes('hold') || item.entryType?.includes('escrow')
                ? 'escrow'
                : item.entryType?.includes('withdraw')
                    ? 'pending'
                    : positive
                        ? 'success'
                        : 'default';

            return {
                id: `entry-${item.id}`,
                kind: 'entry',
                title: label,
                subtitle: [formatActivityDate(item.createdAt), item.description || item.walletType || 'Wallet'].filter(Boolean).join(' • '),
                amount: asNumber(item.amount),
                signedAmount: `${positive ? '+' : '-'}${moneyFixed(item.amount).replace('৳', '')}`,
                positive,
                status: inferredTone,
                statusLabel: inferredTone === 'escrow' ? 'Escrow' : inferredTone === 'pending' ? 'Pending' : 'Completed',
                tone: inferredTone,
                icon: positive ? Download : Upload,
                createdAt: item.createdAt,
                walletLabel: `${item.walletType || 'Buyer'} wallet`,
                methodLabel: titleCase(item.entryType || 'wallet_entry'),
                referenceLabel: `LEDGER-${item.id}`,
                detailLabel: item.description || titleCase(item.entryType || 'wallet transaction'),
            };
        }),
    ]
        .sort((left, right) => new Date(right.createdAt || 0).getTime() - new Date(left.createdAt || 0).getTime());

    const baseActivity = (initialTab === 'top-up-history'
        ? activityItems.filter((item) => item.id.startsWith('topup-'))
        : initialTab === 'transaction-history'
            ? activityItems.filter((item) => item.id.startsWith('entry-'))
            : activityItems
    );

    const filteredActivity = baseActivity.filter((item) => {
        if (activityFilter === 'all') return true;
        if (activityFilter === 'credits') return item.positive;
        if (activityFilter === 'debits') return !item.positive;
        if (activityFilter === 'escrow') return item.tone === 'escrow';
        if (activityFilter === 'pending') return item.tone === 'pending';
        return true;
    });

    const visibleActivity = filteredActivity.slice(0, activityLimit);

    useEffect(() => {
        setActivityLimit(4);
        setActivityFilterOpen(false);
    }, [initialTab, activityFilter]);

    const currentMonth = new Date().getMonth();
    const currentYear = new Date().getFullYear();
    const totalSpentThisMonth = transactions.reduce((total, item) => {
        const createdAt = item.createdAt ? new Date(item.createdAt) : null;
        if (!createdAt || Number.isNaN(createdAt.getTime())) return total;
        if (createdAt.getMonth() !== currentMonth || createdAt.getFullYear() !== currentYear) return total;
        if (String(item.entrySide || '').toLowerCase() !== 'debit') return total;
        return total + asNumber(item.amount);
    }, 0);
    const escrowCount = transactions.filter((item) => {
        const type = String(item.entryType || '').toLowerCase();
        const description = String(item.description || '').toLowerCase();
        return type.includes('hold') || type.includes('escrow') || description.includes('escrow');
    }).length;

    const defaultMethod = paymentMethods.find((method) => method.isDefault) || paymentMethods[0] || null;
    const paymentMethodLabel = (method) => {
        if (!method) return 'Select method';
        const details = method.details || {};
        if (method.kind === 'nagad' || method.kind === 'bkash') {
            return `${method.label}${details.account_name ? ` (${details.account_name})` : ''}`;
        }

        return method.label;
    };

    const paymentMethodSubtitle = (method) => {
        if (!method) return 'No saved methods yet';
        const details = method.details || {};
        if (method.kind === 'nagad' || method.kind === 'bkash') return details.mobile_number || method.subtitle || 'Mobile wallet';
        if (method.kind === 'bank') return method.subtitle || details.bank_name || 'Bank transfer';
        return method.subtitle || details.cardholder_name || 'Saved payment method';
    };

    const methodIcon = (kind) => {
        if (kind === 'bank') return Landmark;
        if (kind === 'nagad' || kind === 'bkash') return Smartphone;
        return CreditCard;
    };

    const statusClasses = {
        success: 'border-emerald-200 bg-emerald-50 text-emerald-700',
        escrow: 'border-indigo-200 bg-indigo-50 text-indigo-600',
        pending: 'border-amber-200 bg-amber-50 text-amber-700',
        default: 'border-slate-200 bg-slate-100 text-slate-700',
    };

    const primaryWallet = wallets[0] || null;
    const activityFilterOptions = [
        ['all', 'All activity'],
        ['credits', 'Credits only'],
        ['debits', 'Debits only'],
        ['escrow', 'Escrow only'],
        ['pending', 'Pending only'],
    ];

    const downloadReceiptPdf = (item) => {
        if (typeof window === 'undefined') return;

        const numericAmount = String(item.signedAmount || '').replace('৳', '').trim();
        const amountLabel = `${item.positive ? 'Credit' : 'Debit'} ${numericAmount}`;
        const createdLabel = item.createdAt ? new Date(item.createdAt).toLocaleString('en-US', {
            year: 'numeric',
            month: 'short',
            day: 'numeric',
            hour: 'numeric',
            minute: '2-digit',
        }) : 'Pending';
        const statusDisplay = item.tone === 'escrow'
            ? 'Escrow Hold'
            : item.statusLabel;
        const rows = [
            ['Wallet', item.walletLabel || 'Buyer wallet'],
            ['Activity Type', item.methodLabel || item.title],
            ['Receipt ID', item.referenceLabel],
            ['Created At', createdLabel],
            ['Details', item.detailLabel || item.subtitle || item.title],
        ];

        const pdfLines = [];
        const push = (line) => pdfLines.push(line);
        const text = (x, y, value, font = 'F1', size = 12, color = '0.10 0.14 0.24') => push(`BT ${color} rg /${font} ${size} Tf 1 0 0 1 ${x} ${y} Tm (${escapePdfText(value)}) Tj ET`);

        push('1 1 1 rg 0 0 595 842 re f');
        push('0.18 0.22 0.40 rg 0 678 595 164 re f');
        push('1 1 1 rg 583 830 22 22 re f');
        push('0.38 0.42 0.93 rg 28 808 28 3 re f');
        push('0.38 0.42 0.93 rg 56 808 28 3 re f');

        text(28, 776, 'SELLOVA WALLET RECEIPT', 'F2', 9, '1 1 1');
        text(28, 736, item.title, 'F2', 28, '1 1 1');
        text(28, 708, `Reference ${item.referenceLabel} | ${createdLabel}`, 'F2', 10, '0.82 0.86 0.93');

        push('1 1 1 rg 28 532 259 76 re f');
        push('1 1 1 rg 309 532 259 76 re f');
        push('0.88 0.91 0.97 RG 1 w 28 532 259 76 re S');
        push('0.88 0.91 0.97 RG 1 w 309 532 259 76 re S');
        text(58, 576, 'AMOUNT', 'F2', 9, '0.55 0.62 0.74');
        text(58, 554, amountLabel, 'F2', 18, '0.07 0.11 0.20');
        text(339, 576, 'STATUS', 'F2', 9, '0.55 0.62 0.74');
        text(339, 554, statusDisplay, 'F2', 18, item.tone === 'success' ? '0.02 0.60 0.38' : item.tone === 'pending' ? '0.72 0.38 0.03' : '0.28 0.27 0.91');

        let rowY = 470;
        rows.forEach(([label, value]) => {
            text(28, rowY, label.toUpperCase(), 'F2', 9, '0.55 0.62 0.74');
            text(208, rowY, value, 'F2', 10, '0.10 0.14 0.24');
            push(`0.92 0.94 0.98 RG 1 w 28 ${rowY - 16} m 568 ${rowY - 16} l S`);
            rowY -= 48;
        });

        push('0.94 0.96 0.99 RG 1 w 28 104 m 568 104 l S');
        text(28, 62, 'This receipt was generated from your Sellova wallet activity.', 'F2', 8, '0.39 0.46 0.60');
        text(510, 62, 'sellova.com', 'F2', 8, '0.07 0.11 0.20');

        const content = pdfLines.join('\n');
        const objects = [
            '1 0 obj << /Type /Catalog /Pages 2 0 R >> endobj',
            '2 0 obj << /Type /Pages /Count 1 /Kids [3 0 R] >> endobj',
            '3 0 obj << /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] /Resources << /Font << /F1 4 0 R /F2 5 0 R >> >> /Contents 6 0 R >> endobj',
            '4 0 obj << /Type /Font /Subtype /Type1 /BaseFont /Helvetica >> endobj',
            '5 0 obj << /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold >> endobj',
            `6 0 obj << /Length ${content.length} >> stream\n${content}\nendstream endobj`,
        ];

        let pdf = '%PDF-1.4\n';
        const offsets = [0];
        objects.forEach((object) => {
            offsets.push(pdf.length);
            pdf += `${object}\n`;
        });
        const xrefStart = pdf.length;
        pdf += `xref\n0 ${objects.length + 1}\n0000000000 65535 f \n`;
        offsets.slice(1).forEach((offset) => {
            pdf += `${String(offset).padStart(10, '0')} 00000 n \n`;
        });
        pdf += `trailer << /Size ${objects.length + 1} /Root 1 0 R >>\nstartxref\n${xrefStart}\n%%EOF`;

        const blob = new Blob([pdf], { type: 'application/pdf' });
        const url = URL.createObjectURL(blob);
        const link = document.createElement('a');
        link.href = url;
        link.download = `${item.referenceLabel || item.id || 'wallet-receipt'}.pdf`;
        document.body.appendChild(link);
        link.click();
        link.remove();
        window.setTimeout(() => URL.revokeObjectURL(url), 1000);
    };

    return (
        <BuyerPanelShell
            activeKey="wallet"
            eyebrow="Buyer wallet"
            title="Balance, top-up history, payment methods, and reward value"
            description="This shared wallet architecture keeps buyer funding, protected escrow debits, transaction history, and reusable payment methods synchronized with mobile flows."
        >
            <section className="space-y-4">
                <div>
                    <h1 className="text-xl font-black tracking-tight text-slate-950">Wallet &amp; Funds</h1>
                    <p className="mt-1 text-sm font-medium text-slate-500">Manage your balances, payment methods, and transaction history.</p>
                </div>

                <section className="grid gap-3 xl:grid-cols-3">
                    <article className="relative overflow-hidden rounded-2xl border border-indigo-200/70 bg-[linear-gradient(135deg,#4f46e5_0%,#5b4ff0_52%,#4f46e5_100%)] px-4 py-4 text-white shadow-[0_14px_28px_-22px_rgba(79,70,229,0.45)]">
                        <div className="absolute inset-y-0 right-0 w-24 bg-[radial-gradient(circle_at_center,rgba(255,255,255,0.16),transparent_72%)]" />
                        <div className="relative flex items-center justify-between gap-4">
                            <div className="flex items-center gap-2.5 text-[11px] font-black uppercase tracking-[0.18em] text-indigo-100">
                                <Wallet className="size-4 text-white/95" />
                                <span>Available Balance</span>
                            </div>
                            <div className="rounded-lg bg-white/12 px-2.5 py-1.5 text-[11px] font-black text-white ring-1 ring-white/10">
                                <span className="text-emerald-300">↗</span> +5.2%
                            </div>
                        </div>
                        <div className="relative mt-6">
                            <p className="text-[2rem] font-black leading-none tracking-[-0.04em] text-white">{moneyFixed(summary.available || 0)}</p>
                            <p className="mt-2.5 text-[12px] font-medium text-indigo-100/90">Ready to use for purchases or withdrawals</p>
                        </div>
                    </article>

                    <article className="rounded-2xl border border-[#e5ebfb] bg-white px-4 py-4 shadow-[0_12px_24px_-24px_rgba(15,23,42,0.26)]">
                        <div className="flex items-start justify-between gap-4">
                            <p className="text-[11px] font-black uppercase tracking-[0.18em] text-slate-400">Funds In Escrow</p>
                            <span className="flex size-9 items-center justify-center rounded-xl bg-indigo-50 text-indigo-500">
                                <Shield className="size-4" />
                            </span>
                        </div>
                        <p className="mt-7 text-[1.6rem] font-black tracking-[-0.03em] text-slate-950">{moneyFixed(summary.held || 0)}</p>
                        <p className="mt-2 text-[12px] font-medium text-slate-500">Securely held for {escrowCount || 0} order{escrowCount === 1 ? '' : 's'}</p>
                    </article>

                    <article className="rounded-2xl border border-[#e5ebfb] bg-white px-4 py-4 shadow-[0_12px_24px_-24px_rgba(15,23,42,0.26)]">
                        <div className="flex items-start justify-between gap-4">
                            <p className="text-[11px] font-black uppercase tracking-[0.18em] text-slate-400">Total Spent (Month)</p>
                            <span className="flex size-9 items-center justify-center rounded-xl bg-emerald-50 text-emerald-600">
                                <Banknote className="size-4" />
                            </span>
                        </div>
                        <p className="mt-7 text-[1.6rem] font-black tracking-[-0.03em] text-slate-950">{moneyFixed(totalSpentThisMonth)}</p>
                        <p className="mt-2 text-[12px] font-medium text-slate-500">Across {transactions.filter((item) => String(item.entrySide || '').toLowerCase() === 'debit').length} completed orders</p>
                    </article>
                </section>

                <section className="grid gap-4 2xl:grid-cols-[minmax(0,1.7fr)_340px]">
                    <section className="overflow-hidden rounded-2xl border border-[#e5ebfb] bg-white shadow-[0_12px_26px_-24px_rgba(15,23,42,0.24)]">
                        <div className="flex items-center justify-between gap-4 border-b border-slate-100 px-4 py-4">
                            <h2 className="text-lg font-black tracking-tight text-slate-950">Recent Wallet Activity</h2>
                            <div className="relative">
                                <button type="button" onClick={() => setActivityFilterOpen((current) => !current)} className="rounded-full p-2 text-slate-400 transition hover:bg-slate-50 hover:text-slate-700" aria-label="Filter wallet activity">
                                    <Filter className="size-4" />
                                </button>
                                {activityFilterOpen ? (
                                    <div className="absolute right-0 top-11 z-20 min-w-[180px] rounded-2xl border border-slate-200 bg-white p-2 shadow-[0_18px_40px_-28px_rgba(15,23,42,0.28)]">
                                        {activityFilterOptions.map(([key, label]) => (
                                            <button
                                                key={key}
                                                type="button"
                                                onClick={() => {
                                                    setActivityFilter(key);
                                                    setActivityFilterOpen(false);
                                                }}
                                                className={cn(
                                                    'flex w-full items-center justify-between rounded-xl px-3 py-2 text-left text-sm font-semibold transition',
                                                    activityFilter === key ? 'bg-indigo-50 text-indigo-600' : 'text-slate-600 hover:bg-slate-50 hover:text-slate-950',
                                                )}
                                            >
                                                <span>{label}</span>
                                                {activityFilter === key ? <Check className="size-4" /> : null}
                                            </button>
                                        ))}
                                    </div>
                                ) : null}
                            </div>
                        </div>

                        <div className="hidden grid-cols-[minmax(220px,1.8fr)_1fr_1fr_56px] gap-5 border-b border-slate-100 px-4 py-3 text-[10px] font-black uppercase tracking-[0.18em] text-slate-400 md:grid">
                            <p>Transaction</p>
                            <p>Amount</p>
                            <p>Status</p>
                            <p className="text-right">Receipt</p>
                        </div>

                        <div className="divide-y divide-slate-100">
                            {visibleActivity.length ? visibleActivity.map((item) => {
                                const RowIcon = item.icon;

                                return (
                                    <div key={item.id} className="grid gap-3 px-4 py-4 md:grid-cols-[minmax(220px,1.8fr)_1fr_1fr_56px] md:items-center md:gap-4">
                                        <div className="flex items-start gap-3">
                                            <span className={cn(
                                                'mt-0.5 flex size-9 items-center justify-center rounded-xl',
                                                item.positive ? 'bg-emerald-50 text-emerald-600' : 'bg-rose-50 text-rose-500',
                                            )}>
                                                <RowIcon className="size-4" />
                                            </span>
                                            <div>
                                                <p className="text-[14px] font-black text-slate-950">{item.title}</p>
                                                <p className="mt-0.5 text-[12px] font-medium text-slate-500">{item.subtitle}</p>
                                            </div>
                                        </div>
                                        <p className={cn(
                                            'text-[14px] font-black',
                                            item.positive ? 'text-emerald-600' : 'text-slate-950',
                                        )}>
                                            {item.signedAmount}
                                        </p>
                                        <div>
                                            <span className={cn('inline-flex rounded-lg border px-2.5 py-1 text-[10px] font-bold', statusClasses[item.tone] || statusClasses.default)}>
                                                {item.statusLabel}
                                            </span>
                                        </div>
                                        <div className="flex justify-start md:justify-end">
                                            <button type="button" onClick={() => downloadReceiptPdf(item)} className="rounded-full p-1.5 text-slate-400 transition hover:bg-slate-50 hover:text-indigo-600" aria-label={`Download receipt for ${item.title}`}>
                                                <Download className="size-4" />
                                            </button>
                                        </div>
                                    </div>
                                );
                            }) : (
                                <div className="px-4 py-10 text-center text-sm font-medium text-slate-500">
                                    Wallet activity will appear here after your first funded order or top-up.
                                </div>
                            )}
                        </div>

                        <div className="border-t border-slate-100 px-4 py-3.5 text-center">
                            {filteredActivity.length > visibleActivity.length ? (
                                <button type="button" onClick={() => setActivityLimit((current) => current + 4)} className="text-sm font-bold text-indigo-600 transition hover:text-indigo-700">Load more transactions</button>
                            ) : null}
                        </div>
                    </section>

                    <div className="space-y-4">
                        <section className="overflow-hidden rounded-2xl border border-[#e5ebfb] bg-white shadow-[0_12px_26px_-24px_rgba(15,23,42,0.24)]">
                            <div className="grid grid-cols-2 border-b border-slate-100">
                                {[
                                    ['topup', 'Top Up'],
                                    ['withdraw', 'Withdraw'],
                                ].map(([key, label]) => (
                                    <button
                                        key={key}
                                        type="button"
                                        onClick={() => setFundActionTab(key)}
                                        className={cn(
                                            'px-4 py-3 text-center text-sm font-black transition',
                                            fundActionTab === key ? 'border-b-[3px] border-indigo-500 text-indigo-600' : 'text-slate-500 hover:bg-slate-50 hover:text-slate-950',
                                        )}
                                    >
                                        {label}
                                    </button>
                                ))}
                            </div>

                            <div className="space-y-3 px-4 py-4">
                                {fundActionTab === 'topup' ? (
                                    <>
                                        {wallets.length > 1 ? (
                                            <select value={topUpForm.walletId} onChange={(event) => setTopUpForm({ ...topUpForm, walletId: event.target.value })} className="h-11 w-full rounded-xl border border-slate-200 bg-slate-50 px-3.5 text-[13px] font-semibold text-slate-600 outline-none transition focus:border-indigo-200 focus:bg-white">
                                                {wallets.map((wallet) => <option key={wallet.id} value={wallet.id}>{wallet.type} wallet • {wallet.currency}</option>)}
                                            </select>
                                        ) : null}
                                        <div className="flex h-11 items-center rounded-xl border border-slate-200 bg-slate-50 px-3.5">
                                            <span className="mr-2.5 text-sm font-bold text-slate-400">৳</span>
                                            <input type="number" min="1" value={topUpForm.amount} onChange={(event) => setTopUpForm({ ...topUpForm, amount: event.target.value })} placeholder="0.00" className="h-full w-full border-0 bg-transparent text-[13px] font-semibold text-slate-700 outline-none placeholder:text-slate-400" />
                                        </div>

                                        <select value={topUpForm.payment_method} onChange={(event) => setTopUpForm({ ...topUpForm, payment_method: event.target.value })} className="h-11 w-full rounded-xl border border-slate-200 bg-slate-50 px-3.5 text-[13px] font-semibold text-slate-700 outline-none transition focus:border-indigo-200 focus:bg-white">
                                            {paymentMethods.length ? paymentMethods.map((method) => (
                                                <option key={method.id} value={method.kind}>{paymentMethodLabel(method)}</option>
                                            )) : (
                                                ['manual', 'card', 'bkash', 'nagad', 'bank'].map((method) => <option key={method} value={method}>{titleCase(method)}</option>)
                                            )}
                                        </select>

                                        <Input value={topUpForm.payment_reference} onChange={(event) => setTopUpForm({ ...topUpForm, payment_reference: event.target.value })} placeholder="TrxID or Reference Code" className="h-11 rounded-xl border-slate-200 bg-slate-50 px-3.5 text-[13px] font-semibold placeholder:text-slate-400" />

                                        <Button onClick={submitTopUp} disabled={pendingAction.startsWith('buyer:wallet:')} className="h-11 w-full rounded-xl bg-[#4f46e5] text-[13px] font-black hover:bg-[#4338ca]">
                                            <Upload className="size-4" />
                                            {pendingAction.startsWith('buyer:wallet:') ? 'Confirming Top-up...' : 'Confirm Top-up'}
                                        </Button>

                                        {primaryWallet ? <p className="text-sm font-semibold text-slate-500">Funds will be requested for your {primaryWallet.type} wallet in {primaryWallet.currency}.</p> : null}
                                    </>
                                ) : (
                                    <div className="rounded-2xl border border-dashed border-slate-200 bg-slate-50 px-5 py-6 text-center">
                                        <p className="text-sm font-black text-slate-950">Withdrawals are not enabled for buyer wallets yet.</p>
                                        <p className="mt-2 text-sm font-medium text-slate-500">This tab is styled to match the design and stays ready for the next workflow.</p>
                                    </div>
                                )}
                            </div>
                        </section>

                        <section className="overflow-hidden rounded-2xl border border-[#e5ebfb] bg-white shadow-[0_12px_26px_-24px_rgba(15,23,42,0.24)]">
                            <div className="flex items-center justify-between gap-4 border-b border-slate-100 px-4 py-4">
                                <h2 className="text-lg font-black tracking-tight text-slate-950">Saved Methods</h2>
                                <button type="button" onClick={() => openMethodEditor()} className="text-2xl font-medium leading-none text-indigo-500 transition hover:text-indigo-600" aria-label="Add payment method">+</button>
                            </div>

                            <div className="space-y-2.5 px-4 py-4">
                                {paymentMethods.length ? paymentMethods.slice(0, 4).map((method) => {
                                    const MethodIcon = methodIcon(method.kind);

                                    return (
                                        <div key={method.id} className="rounded-2xl border border-slate-200 bg-slate-50/60 px-3.5 py-3">
                                            <div className="flex items-center gap-3">
                                                <span className="flex size-9 shrink-0 items-center justify-center rounded-xl border border-slate-200 bg-white text-slate-400">
                                                    <MethodIcon className="size-4" />
                                                </span>
                                                <div className="min-w-0 flex-1">
                                                    <div className="flex flex-wrap items-center gap-3">
                                                        <p className="text-[14px] font-black text-slate-950">{method.label}</p>
                                                        {method.isDefault ? <span className="rounded-xl bg-emerald-50 px-2.5 py-1 text-[10px] font-black uppercase tracking-[0.16em] text-emerald-600">Default</span> : null}
                                                    </div>
                                                    <p className="mt-0.5 truncate text-[12px] font-medium text-slate-500">{paymentMethodSubtitle(method)}</p>
                                                </div>
                                                <button type="button" onClick={() => openMethodEditor(method)} className="rounded-full p-2 text-slate-400 transition hover:bg-white hover:text-slate-700" aria-label={`Edit ${method.label}`}>
                                                    <Edit className="size-4" />
                                                </button>
                                            </div>
                                            <div className="mt-3 flex flex-wrap gap-2">
                                                {!method.isDefault ? <Button variant="outline" size="sm" onClick={() => setDefaultBuyerPaymentMethod(method.id)}>Set default</Button> : null}
                                                <Button variant="outline" size="sm" onClick={() => deleteBuyerPaymentMethod(method.id)} className="text-rose-600 hover:text-rose-700"><Trash2 className="size-4" />Delete</Button>
                                            </div>
                                        </div>
                                    );
                                }) : (
                                    <div className="rounded-3xl border border-dashed border-slate-200 bg-slate-50 px-6 py-10 text-center text-sm font-medium text-slate-500">
                                        No saved methods yet. Add one to match this panel.
                                    </div>
                                )}
                            </div>
                        </section>
                    </div>
                </section>
            </section>
            {methodFormOpen && typeof document !== 'undefined' ? createPortal(
                <div className="fixed inset-0 z-[100000] flex items-center justify-center bg-slate-950/45 p-4">
                    <div className="w-full max-w-3xl rounded-[24px] border border-slate-200 bg-white p-5 shadow-[0_30px_80px_-35px_rgba(15,23,42,0.45)]">
                        <div className="flex items-center justify-between gap-4">
                            <div>
                                <h3 className="text-lg font-black text-slate-950">{editingMethodId ? 'Update payment method' : 'Add payment method'}</h3>
                                <p className="mt-1 text-sm font-medium text-slate-500">Create and edit methods without leaving the wallet screen.</p>
                            </div>
                            <button type="button" onClick={resetMethodForm} className="rounded-lg p-2 text-slate-400 transition hover:bg-slate-100 hover:text-slate-700" aria-label="Close payment method modal">
                                <X className="size-4" />
                            </button>
                        </div>
                        <div className="mt-5 grid gap-4 md:grid-cols-2">
                            <select value={methodForm.kind} onChange={(event) => setMethodForm({ ...methodForm, kind: event.target.value })} className="h-12 rounded-xl border border-slate-200 bg-white px-3 text-sm font-semibold">
                                <option value="card">Card</option>
                                <option value="bkash">bKash</option>
                                <option value="nagad">Nagad</option>
                                <option value="bank">Bank</option>
                            </select>
                            {methodForm.kind === 'card' ? (
                                <>
                                    <Input value={methodForm.cardholder_name} onChange={(event) => setMethodForm({ ...methodForm, cardholder_name: event.target.value })} placeholder="Cardholder name" className="h-12 rounded-xl bg-white font-semibold" />
                                    <Input value={methodForm.card_brand} onChange={(event) => setMethodForm({ ...methodForm, card_brand: event.target.value })} placeholder="Card brand" className="h-12 rounded-xl bg-white font-semibold" />
                                    <Input value={methodForm.last4} onChange={(event) => setMethodForm({ ...methodForm, last4: event.target.value })} placeholder="Last 4 digits" className="h-12 rounded-xl bg-white font-semibold" />
                                </>
                            ) : null}
                            {['bkash', 'nagad'].includes(methodForm.kind) ? (
                                <>
                                    <Input value={methodForm.account_name} onChange={(event) => setMethodForm({ ...methodForm, account_name: event.target.value })} placeholder="Account name" className="h-12 rounded-xl bg-white font-semibold" />
                                    <Input value={methodForm.mobile_number} onChange={(event) => setMethodForm({ ...methodForm, mobile_number: event.target.value })} placeholder="Mobile number" className="h-12 rounded-xl bg-white font-semibold" />
                                    <Input value={methodForm.note} onChange={(event) => setMethodForm({ ...methodForm, note: event.target.value })} placeholder="Internal note" className="h-12 rounded-xl bg-white font-semibold" />
                                </>
                            ) : null}
                            {methodForm.kind === 'bank' ? (
                                <>
                                    <Input value={methodForm.account_name} onChange={(event) => setMethodForm({ ...methodForm, account_name: event.target.value })} placeholder="Account name" className="h-12 rounded-xl bg-white font-semibold" />
                                    <Input value={methodForm.bank_name} onChange={(event) => setMethodForm({ ...methodForm, bank_name: event.target.value })} placeholder="Bank name" className="h-12 rounded-xl bg-white font-semibold" />
                                    <Input value={methodForm.account_number} onChange={(event) => setMethodForm({ ...methodForm, account_number: event.target.value })} placeholder="Account number" className="h-12 rounded-xl bg-white font-semibold" />
                                    <Input value={methodForm.branch} onChange={(event) => setMethodForm({ ...methodForm, branch: event.target.value })} placeholder="Branch" className="h-12 rounded-xl bg-white font-semibold" />
                                    <Input value={methodForm.routing_number} onChange={(event) => setMethodForm({ ...methodForm, routing_number: event.target.value })} placeholder="Routing number" className="h-12 rounded-xl bg-white font-semibold" />
                                </>
                            ) : null}
                            {!['bkash', 'nagad'].includes(methodForm.kind) ? (
                                <Input value={methodForm.note} onChange={(event) => setMethodForm({ ...methodForm, note: event.target.value })} placeholder="Internal note" className="h-12 rounded-xl bg-white font-semibold md:col-span-2" />
                            ) : null}
                            <label className="flex items-center gap-3 text-sm font-bold text-slate-700 md:col-span-2">
                                <input type="checkbox" checked={methodForm.is_default} onChange={(event) => setMethodForm({ ...methodForm, is_default: event.target.checked })} />
                                Set as default payment method
                            </label>
                            <div className="flex flex-wrap justify-end gap-3 md:col-span-2">
                                <Button variant="outline" onClick={resetMethodForm}>Cancel</Button>
                                <Button onClick={submitPaymentMethod} disabled={pendingAction.startsWith('buyer:payment-method:')} className="bg-slate-950 hover:bg-indigo-600">
                                    <Check className="size-4" />{pendingAction.startsWith('buyer:payment-method:') ? 'Saving...' : editingMethodId ? 'Update method' : 'Save method'}
                                </Button>
                            </div>
                        </div>
                    </div>
                </div>,
                document.body,
            ) : null}
        </BuyerPanelShell>
    );
}

function BuyerSavedCenter({ state, addToCart, toggleWishlist, initialTab = 'wishlist' }) {
    const buyerOps = state.buyerOps || {};
    const wishlistProducts = state.products.filter((product) => state.wishlist.includes(product.id));
    const savedItems = buyerOps.savedItems || [];
    const favoriteStores = buyerOps.favoriteStores || [];
    const recentlyViewed = buyerOps.recentlyViewed || [];
    const activeSet = initialTab === 'saved-items' ? savedItems : initialTab === 'favorite-stores' ? favoriteStores : initialTab === 'recently-viewed' ? recentlyViewed : wishlistProducts;

    return (
        <BuyerPanelShell
            activeKey="saved"
            eyebrow="Saved and discovery"
            title="Wishlist, saved items, favorite stores, and recently viewed listings"
            description="Buyer-side intent signals stay grouped so shoppers can resume decisions quickly across web and app."
        >
            <Panel title="Collections" icon={Heart}>
                {initialTab === 'favorite-stores' ? (
                    <div className="grid gap-3 md:grid-cols-2 xl:grid-cols-3">
                        {favoriteStores.length ? favoriteStores.map((store) => (
                            <div key={store.id} className="rounded-2xl border border-slate-200 p-4">
                                <p className="font-extrabold text-slate-950">{store.name}</p>
                                <p className="mt-2 text-sm font-semibold text-slate-500">{store.orders} order{store.orders === 1 ? '' : 's'} {store.active ? '· Active relationship' : ''}</p>
                            </div>
                        )) : <p className="rounded-2xl bg-slate-50 p-6 text-sm font-semibold text-slate-500">Favorite stores will appear after repeat purchases.</p>}
                    </div>
                ) : (
                    <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
                        {activeSet.length ? activeSet.map((product) => (
                            <ProductCard key={product.id} product={product} addToCart={addToCart} toggleWishlist={toggleWishlist} wished={state.wishlist.includes(product.id)} />
                        )) : <p className="rounded-2xl bg-slate-50 p-6 text-sm font-semibold text-slate-500">Nothing has been saved in this section yet.</p>}
                    </div>
                )}
            </Panel>
        </BuyerPanelShell>
    );
}

function BuyerProfileCenter({ state, saveProfile, updateBuyerPassword, uploadBuyerProfilePhoto, updateBuyerNotificationPreferences, saveBuyerAddress, deleteBuyerAddress, pendingAction = '', initialTab = 'profile' }) {
    const [form, setForm] = useState(() => ({
        name: state.user?.name || '',
        email: state.user?.email || '',
        phone: state.user?.phone || '',
        city: state.user?.city || '',
        avatar_url: state.user?.avatarUrl || '',
    }));
    const buyerOps = state.buyerOps || {};
    const devices = buyerOps.devices || [];
    const activity = buyerOps.activity || [];
    const security = buyerOps.security || {};
    const preferences = buyerOps.notificationPreferences || {};
    const addresses = buyerOps.addresses || [];
    const [passwordForm, setPasswordForm] = useState({ current_password: '', new_password: '', new_password_confirmation: '' });
    const [notificationForm, setNotificationForm] = useState({
        order_updates_enabled: Boolean(preferences.orderUpdatesEnabled),
        promotion_enabled: Boolean(preferences.promotionEnabled),
        email_enabled: Boolean(preferences.emailEnabled),
        in_app_enabled: Boolean(preferences.inAppEnabled),
    });
    const [addressModalOpen, setAddressModalOpen] = useState(false);
    const [editingAddressId, setEditingAddressId] = useState(null);
    const [addressForm, setAddressForm] = useState({
        label: '',
        address_type: 'shipping',
        recipient_name: '',
        phone: '',
        address_line: '',
        city: '',
        region: '',
        postal_code: '',
        country: 'Bangladesh',
        is_default: addresses.length === 0,
    });

    useEffect(() => {
        setForm({
            name: state.user?.name || '',
            email: state.user?.email || '',
            phone: state.user?.phone || '',
            city: state.user?.city || '',
            avatar_url: state.user?.avatarUrl || '',
        });
    }, [state.user?.name, state.user?.email, state.user?.phone, state.user?.city, state.user?.avatarUrl]);

    useEffect(() => {
        setNotificationForm({
            order_updates_enabled: Boolean(preferences.orderUpdatesEnabled),
            promotion_enabled: Boolean(preferences.promotionEnabled),
            email_enabled: Boolean(preferences.emailEnabled),
            in_app_enabled: Boolean(preferences.inAppEnabled),
        });
    }, [preferences.orderUpdatesEnabled, preferences.promotionEnabled, preferences.emailEnabled, preferences.inAppEnabled]);

    const resolvedTab = initialTab === 'profile-settings' ? 'profile'
        : initialTab === 'security-settings' ? 'security'
        : initialTab === 'address-book' ? 'address'
        : initialTab === 'notifications' ? 'notifications'
        : initialTab === 'device-management' ? 'security'
        : initialTab;

    const tabItems = [
        { key: 'profile', label: 'Personal Info', href: '/profile-settings', icon: User },
        { key: 'security', label: 'Security', href: '/security-settings', icon: ShieldCheck },
        { key: 'address', label: 'Address Book', href: '/address-book', icon: MapPin },
        { key: 'notifications', label: 'Notifications', href: '/notifications', icon: Bell },
    ];

    const splitName = (value) => {
        const parts = String(value || '').trim().split(/\s+/).filter(Boolean);
        return {
            first: parts[0] || '',
            last: parts.slice(1).join(' '),
        };
    };
    const initials = String(form.name || state.user?.name || 'Buyer')
        .split(/\s+/)
        .map((part) => part[0] || '')
        .join('')
        .slice(0, 2)
        .toUpperCase();
    const nameParts = splitName(form.name);

    const openAddressEditor = (address = null) => {
        if (!address) {
            setEditingAddressId(null);
            setAddressForm({
                label: '',
                address_type: 'shipping',
                recipient_name: form.name || '',
                phone: form.phone || '',
                address_line: '',
                city: form.city || '',
                region: '',
                postal_code: '',
                country: 'Bangladesh',
                is_default: addresses.length === 0,
            });
            setAddressModalOpen(true);
            return;
        }
        setEditingAddressId(address.id);
        setAddressForm({
            label: address.label || '',
            address_type: address.addressType || 'shipping',
            recipient_name: address.recipientName || '',
            phone: address.phone || '',
            address_line: address.addressLine || '',
            city: address.city || '',
            region: address.region || '',
            postal_code: address.postalCode || '',
            country: address.country || 'Bangladesh',
            is_default: Boolean(address.isDefault),
        });
        setAddressModalOpen(true);
    };

    const submitAddress = async () => {
        await saveBuyerAddress(addressForm, editingAddressId);
        openAddressEditor(null);
        setAddressModalOpen(false);
    };

    return (
        <BuyerPanelShell activeKey="profile" eyebrow="" title="" description="">
            <section className="space-y-5">
                <div className="inline-flex flex-wrap gap-2 rounded-[24px] border border-slate-200 bg-slate-100/90 p-2 shadow-[0_12px_28px_-24px_rgba(15,23,42,0.18)]">
                    {tabItems.map(({ key, label, href, icon: Icon }) => {
                        const active = resolvedTab === key;
                        return (
                            <Link
                                key={key}
                                href={href}
                                className={cn(
                                    'inline-flex items-center gap-2 rounded-xl px-4 py-2 text-sm font-semibold transition',
                                    active
                                        ? 'bg-white text-slate-950 shadow-[0_10px_20px_-18px_rgba(15,23,42,0.35)] ring-1 ring-slate-200'
                                        : 'text-slate-500 hover:bg-white/70 hover:text-slate-900',
                                )}
                            >
                                <Icon className="size-4" />
                                {label}
                            </Link>
                        );
                    })}
                </div>

                <div className="space-y-5">
                    {resolvedTab === 'profile' ? (
                        <>
                            <section className="rounded-[20px] border border-slate-200 bg-white p-5 shadow-[0_14px_32px_-30px_rgba(15,23,42,0.22)]">
                                <div className="flex flex-col gap-4 md:flex-row md:items-center">
                                    <div className="relative">
                                        {form.avatar_url ? (
                                            <ProductMedia src={form.avatar_url} alt={form.name || 'Buyer'} icon={User} className="size-16 rounded-full object-cover shadow-[0_16px_28px_-20px_rgba(99,102,241,0.42)] ring-1 ring-slate-200" />
                                        ) : (
                                            <div className="flex size-16 items-center justify-center rounded-full bg-[linear-gradient(145deg,#5b5cf0_0%,#8b4ef5_100%)] text-xl font-bold text-white shadow-[0_16px_28px_-20px_rgba(99,102,241,0.42)]">{initials || 'BU'}</div>
                                        )}
                                        <label className="absolute -bottom-1 -right-1 flex size-7 cursor-pointer items-center justify-center rounded-full bg-slate-950 text-white shadow-md transition hover:bg-slate-800">
                                            <Upload className="size-3.5" />
                                            <input
                                                type="file"
                                                accept="image/*"
                                                className="hidden"
                                                onChange={async (event) => {
                                                    const file = event.target.files?.[0];
                                                    if (!file) return;
                                                    const media = await uploadBuyerProfilePhoto(file);
                                                    setForm((current) => ({ ...current, avatar_url: media?.url || current.avatar_url }));
                                                    event.target.value = '';
                                                }}
                                            />
                                        </label>
                                    </div>
                                    <div>
                                        <p className="text-lg font-bold tracking-tight text-slate-950">{form.name || 'Buyer profile'}</p>
                                        <p className="mt-1 text-sm font-medium text-slate-500">{form.email || 'No email added'}</p>
                                        <span className="mt-3 inline-flex rounded-lg bg-indigo-50 px-3 py-1.5 text-[11px] font-black uppercase tracking-[0.14em] text-indigo-600">
                                            {(security.accountStatus || 'verified buyer').replaceAll('_', ' ')}
                                        </span>
                                    </div>
                                </div>
                            </section>

                            <section className="rounded-[20px] border border-slate-200 bg-white p-5 shadow-[0_14px_32px_-30px_rgba(15,23,42,0.22)]">
                                <div className="flex items-center justify-between gap-4">
                                    <h2 className="text-lg font-bold tracking-tight text-slate-950">Personal Details</h2>
                                </div>
                                <div className="mt-5 grid gap-4 md:grid-cols-2">
                                    <div>
                                        <p className="mb-2 text-xs font-black uppercase tracking-[0.18em] text-slate-400">First Name</p>
                                        <Input value={nameParts.first} onChange={(e) => setForm((current) => ({ ...current, name: `${e.target.value} ${splitName(current.name).last}`.trim() }))} placeholder="First name" className="h-11 rounded-xl text-sm" />
                                    </div>
                                    <div>
                                        <p className="mb-2 text-xs font-black uppercase tracking-[0.18em] text-slate-400">Last Name</p>
                                        <Input value={nameParts.last} onChange={(e) => setForm((current) => ({ ...current, name: `${splitName(current.name).first} ${e.target.value}`.trim() }))} placeholder="Last name" className="h-11 rounded-xl text-sm" />
                                    </div>
                                    <div>
                                        <p className="mb-2 text-xs font-black uppercase tracking-[0.18em] text-slate-400">Email Address</p>
                                        <Input value={form.email} onChange={(e) => setForm({ ...form, email: e.target.value })} placeholder="Email address" className="h-11 rounded-xl text-sm" />
                                    </div>
                                    <div>
                                        <p className="mb-2 text-xs font-black uppercase tracking-[0.18em] text-slate-400">Phone Number</p>
                                        <Input value={form.phone} onChange={(e) => setForm({ ...form, phone: e.target.value })} placeholder="Phone number" className="h-11 rounded-xl text-sm" />
                                    </div>
                                    <div className="md:col-span-2">
                                        <p className="mb-2 text-xs font-black uppercase tracking-[0.18em] text-slate-400">City</p>
                                        <Input value={form.city} onChange={(e) => setForm({ ...form, city: e.target.value })} placeholder="City" className="h-11 rounded-xl text-sm" />
                                    </div>
                                </div>
                                <div className="mt-5 flex justify-end">
                                    <Button onClick={() => saveProfile(form)} className="h-10 rounded-xl bg-slate-950 px-5 text-sm hover:bg-slate-800" disabled={pendingAction === 'profile'}>
                                        Save Changes
                                    </Button>
                                </div>
                            </section>
                        </>
                    ) : null}

                    {resolvedTab === 'security' ? (
                        <>
                            <section className="grid gap-5 xl:grid-cols-12">
                                <div className="rounded-[20px] border border-slate-200 bg-white p-5 shadow-[0_14px_32px_-30px_rgba(15,23,42,0.22)] xl:col-span-8">
                                    <div className="flex items-center gap-3">
                                        <LockKeyhole className="size-4 text-slate-400" />
                                        <h2 className="text-lg font-bold tracking-tight text-slate-950">Change Password</h2>
                                    </div>
                                    <div className="mt-5 grid gap-4">
                                        <div>
                                            <p className="mb-2 text-xs font-black uppercase tracking-[0.18em] text-slate-400">Current Password</p>
                                            <Input type="password" value={passwordForm.current_password} onChange={(e) => setPasswordForm({ ...passwordForm, current_password: e.target.value })} placeholder="Current password" className="h-11 rounded-xl text-sm" />
                                        </div>
                                        <div>
                                            <p className="mb-2 text-xs font-black uppercase tracking-[0.18em] text-slate-400">New Password</p>
                                            <Input type="password" value={passwordForm.new_password} onChange={(e) => setPasswordForm({ ...passwordForm, new_password: e.target.value })} placeholder="New strong password" className="h-11 rounded-xl text-sm" />
                                        </div>
                                        <div>
                                            <p className="mb-2 text-xs font-black uppercase tracking-[0.18em] text-slate-400">Confirm New Password</p>
                                            <Input type="password" value={passwordForm.new_password_confirmation} onChange={(e) => setPasswordForm({ ...passwordForm, new_password_confirmation: e.target.value })} placeholder="Repeat new password" className="h-11 rounded-xl text-sm" />
                                        </div>
                                        <div>
                                            <Button
                                                onClick={async () => {
                                                    await updateBuyerPassword(passwordForm);
                                                    setPasswordForm({ current_password: '', new_password: '', new_password_confirmation: '' });
                                                }}
                                                className="h-10 rounded-xl bg-slate-950 px-5 text-sm hover:bg-slate-800"
                                                disabled={pendingAction === 'buyer:password'}
                                            >
                                                Update Password
                                            </Button>
                                        </div>
                                    </div>
                                </div>

                                <div className="rounded-[20px] border border-slate-200 bg-white p-5 shadow-[0_14px_32px_-30px_rgba(15,23,42,0.22)] xl:col-span-4">
                                    <div className="flex items-center justify-between gap-4">
                                        <div>
                                            <h2 className="text-lg font-bold tracking-tight text-slate-950">Two-Factor Authentication (2FA)</h2>
                                            <p className="mt-1 text-sm font-medium text-slate-500">Add an extra layer of security to your account.</p>
                                        </div>
                                    </div>
                                    <div className="mt-5 grid gap-3">
                                        <InfoTile label="Account Status" value={security.accountStatus || 'active'} />
                                        <InfoTile label="Last Login" value={security.lastLoginAt ? new Date(security.lastLoginAt).toLocaleString() : 'Not captured yet'} />
                                        <InfoTile label="Active Devices" value={String(devices.filter((item) => item.active).length || 0)} />
                                        <Button variant="outline" className="mt-2 h-10 rounded-xl px-4 text-sm text-indigo-600" disabled>
                                            {security.twoFactorEnabled ? '2FA Enabled' : 'Enable 2FA'}
                                        </Button>
                                    </div>
                                </div>
                            </section>

                            <section className="rounded-[20px] border border-slate-200 bg-white p-5 shadow-[0_14px_32px_-30px_rgba(15,23,42,0.22)]">
                                <h2 className="text-lg font-bold tracking-tight text-slate-950">Device Sessions</h2>
                                <div className="mt-5 grid gap-3">
                                    {devices.length ? devices.map((device) => (
                                        <div key={device.id} className="rounded-2xl border border-slate-200 px-4 py-3">
                                            <div className="flex items-center justify-between gap-3">
                                                <p className="font-bold text-slate-950">{device.name}</p>
                                                <Badge variant={device.active ? 'success' : 'secondary'}>{device.active ? 'Active' : 'Inactive'}</Badge>
                                            </div>
                                            <p className="mt-1 text-sm font-medium text-slate-500">{device.platform} · {device.lastSeenAt ? new Date(device.lastSeenAt).toLocaleString() : 'Never seen'}</p>
                                        </div>
                                    )) : <p className="rounded-2xl bg-slate-50 p-5 text-sm font-medium text-slate-500">No push device sessions have been registered yet.</p>}
                                </div>
                            </section>
                        </>
                    ) : null}

                    {resolvedTab === 'address' ? (
                        <section className="rounded-[20px] border border-slate-200 bg-white p-5 shadow-[0_14px_32px_-30px_rgba(15,23,42,0.22)]">
                            <div className="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
                                <div>
                                    <h2 className="text-lg font-bold tracking-tight text-slate-950">Address Book</h2>
                                    <p className="mt-1 text-sm font-medium text-slate-500">Manage your shipping and billing addresses for faster checkout.</p>
                                </div>
                                <Button onClick={() => openAddressEditor(null)} className="h-10 rounded-xl bg-slate-950 px-5 text-sm hover:bg-slate-800">
                                    <Plus className="mr-2 size-4" /> Add New Address
                                </Button>
                            </div>
                            <div className="mt-6 grid gap-3 xl:grid-cols-2">
                                    {addresses.length ? addresses.map((address) => (
                                        <div key={address.id} className="rounded-2xl border border-slate-200 p-4">
                                            <div className="flex items-start justify-between gap-3">
                                                <div>
                                                    <div className="flex items-center gap-2">
                                                        <p className="font-bold text-slate-950">{address.label || address.recipientName}</p>
                                                        {address.isDefault ? <Badge variant="success">Default</Badge> : null}
                                                    </div>
                                                    <p className="mt-1 text-sm font-medium text-slate-500">{humanizeOrderState(address.addressType)} · {address.recipientName}</p>
                                                    <p className="mt-2 text-sm font-medium text-slate-600">{address.addressLine}</p>
                                                    <p className="mt-1 text-sm font-medium text-slate-500">{[address.city, address.region, address.postalCode, address.country].filter(Boolean).join(', ')}</p>
                                                    <p className="mt-1 text-sm font-medium text-slate-500">{address.phone || 'No phone added'}</p>
                                                </div>
                                                <div className="flex gap-2">
                                                    <Button variant="outline" className="h-9 rounded-xl px-3" onClick={() => openAddressEditor(address)}><Edit className="size-4" /></Button>
                                                    <Button variant="outline" className="h-9 rounded-xl px-3 text-rose-600" onClick={() => deleteBuyerAddress(address.id)}><Trash2 className="size-4" /></Button>
                                                </div>
                                            </div>
                                        </div>
                                    )) : <div className="rounded-[24px] border border-slate-200 bg-slate-50/70 p-10 text-center"><MapPin className="mx-auto size-12 text-slate-300" /><p className="mt-4 text-2xl font-bold text-slate-950">No addresses saved</p><p className="mt-2 text-base font-medium text-slate-500">Add your shipping and billing addresses for a faster checkout experience.</p></div>}
                            </div>
                        </section>
                    ) : null}

                    {resolvedTab === 'notifications' ? (
                        <section className="rounded-[20px] border border-slate-200 bg-white p-5 shadow-[0_14px_32px_-30px_rgba(15,23,42,0.22)]">
                            <h2 className="text-lg font-bold tracking-tight text-slate-950">Notification Preferences</h2>
                            <div className="mt-6 divide-y divide-slate-100">
                                {[
                                    ['Order Updates', 'Receive emails about your order status and escrow releases.', 'order_updates_enabled'],
                                    ['Promotions & Offers', 'Get notified about discounts and special campaigns.', 'promotion_enabled'],
                                    ['Security Alerts', 'Important notifications about your account security.', 'in_app_enabled'],
                                    ['Email Notifications', 'Allow account updates and notices by email.', 'email_enabled'],
                                ].map(([label, body, key]) => (
                                    <div key={key} className="flex items-center justify-between gap-4 py-5">
                                        <div>
                                            <p className="text-base font-bold text-slate-950">{label}</p>
                                            <p className="mt-1 text-sm font-medium text-slate-500">{body}</p>
                                        </div>
                                        <button
                                            type="button"
                                            onClick={() => setNotificationForm((current) => ({ ...current, [key]: !current[key] }))}
                                            className={cn(
                                                'relative h-7 w-12 rounded-full transition',
                                                notificationForm[key] ? 'bg-indigo-500' : 'bg-slate-200',
                                            )}
                                        >
                                            <span className={cn('absolute top-1 size-5 rounded-full bg-white transition', notificationForm[key] ? 'left-6' : 'left-1')} />
                                        </button>
                                    </div>
                                ))}
                            </div>
                            <div className="mt-5 flex justify-end">
                                <Button onClick={() => updateBuyerNotificationPreferences(notificationForm)} className="h-10 rounded-xl bg-slate-950 px-5 text-sm hover:bg-slate-800" disabled={pendingAction === 'buyer:notification-preferences'}>
                                    Save Preferences
                                </Button>
                            </div>
                        </section>
                    ) : null}

                    <section className="rounded-[20px] border border-slate-200 bg-white p-5 shadow-[0_14px_32px_-30px_rgba(15,23,42,0.22)]">
                        <h2 className="text-lg font-bold tracking-tight text-slate-950">Activity Log</h2>
                        <div className="mt-5 grid gap-3">
                            {activity.length ? activity.map((item) => <div key={item.id} className="rounded-2xl border border-slate-200 px-4 py-3"><p className="font-bold text-slate-950">{item.action.replaceAll('.', ' / ')}</p><p className="mt-1 text-sm font-medium text-slate-500">{item.reasonCode || item.targetType || 'Marketplace activity'} {item.createdAt ? `· ${new Date(item.createdAt).toLocaleString()}` : ''}</p></div>) : <p className="rounded-2xl bg-slate-50 p-5 text-sm font-medium text-slate-500">Recent account activity will appear here as more buyer actions are audited.</p>}
                        </div>
                    </section>
                </div>
            </section>
            {addressModalOpen && typeof document !== 'undefined' ? createPortal(
                <div className="fixed inset-0 z-[100000] flex items-center justify-center bg-slate-950/45 p-4">
                    <div className="w-full max-w-xl rounded-[20px] border border-slate-200 bg-white p-5 shadow-[0_30px_80px_-35px_rgba(15,23,42,0.45)]">
                        <div className="flex items-center justify-between gap-4">
                            <h3 className="text-base font-bold text-slate-950">{editingAddressId ? 'Update address' : 'Add new address'}</h3>
                            <button type="button" onClick={() => setAddressModalOpen(false)} className="rounded-lg p-2 text-slate-400 transition hover:bg-slate-100 hover:text-slate-700">
                                <X className="size-4" />
                            </button>
                        </div>
                        <div className="mt-4 grid gap-4">
                            <Input value={addressForm.label} onChange={(e) => setAddressForm({ ...addressForm, label: e.target.value })} placeholder="Label e.g. Home / Office" className="h-10 rounded-xl text-sm" />
                            <select value={addressForm.address_type} onChange={(e) => setAddressForm({ ...addressForm, address_type: e.target.value })} className="h-10 rounded-xl border border-slate-200 bg-white px-3 text-sm font-medium text-slate-700">
                                <option value="shipping">Shipping</option>
                                <option value="billing">Billing</option>
                            </select>
                            <Input value={addressForm.recipient_name} onChange={(e) => setAddressForm({ ...addressForm, recipient_name: e.target.value })} placeholder="Recipient name" className="h-10 rounded-xl text-sm" />
                            <Input value={addressForm.phone} onChange={(e) => setAddressForm({ ...addressForm, phone: e.target.value })} placeholder="Phone number" className="h-10 rounded-xl text-sm" />
                            <Input value={addressForm.address_line} onChange={(e) => setAddressForm({ ...addressForm, address_line: e.target.value })} placeholder="Address line" className="h-10 rounded-xl text-sm" />
                            <Input value={addressForm.city} onChange={(e) => setAddressForm({ ...addressForm, city: e.target.value })} placeholder="City" className="h-10 rounded-xl text-sm" />
                            <div className="grid gap-4 md:grid-cols-2">
                                <Input value={addressForm.region} onChange={(e) => setAddressForm({ ...addressForm, region: e.target.value })} placeholder="Region / State" className="h-10 rounded-xl text-sm" />
                                <Input value={addressForm.postal_code} onChange={(e) => setAddressForm({ ...addressForm, postal_code: e.target.value })} placeholder="Postal code" className="h-10 rounded-xl text-sm" />
                            </div>
                            <Input value={addressForm.country} onChange={(e) => setAddressForm({ ...addressForm, country: e.target.value })} placeholder="Country" className="h-10 rounded-xl text-sm" />
                            <label className="flex items-center gap-2 text-sm font-medium text-slate-600">
                                <input type="checkbox" checked={addressForm.is_default} onChange={(e) => setAddressForm({ ...addressForm, is_default: e.target.checked })} />
                                Set as default address
                            </label>
                            <div className="flex justify-end gap-3">
                                <Button type="button" variant="outline" className="h-10 rounded-xl px-4 text-sm" onClick={() => setAddressModalOpen(false)}>Cancel</Button>
                                <Button onClick={submitAddress} className="h-10 rounded-xl bg-slate-950 px-5 text-sm hover:bg-slate-800" disabled={pendingAction.startsWith('buyer:address:')}>
                                    {editingAddressId ? 'Update Address' : 'Save Address'}
                                </Button>
                            </div>
                        </div>
                    </div>
                </div>,
                document.body,
            ) : null}
        </BuyerPanelShell>
    );
}

function BuyerCommsCenter({ state, sendMessage, uploadSellerMedia, initialTab = 'support' }) {
    const buyerOps = state.buyerOps || {};
    const notifications = buyerOps.notifications || [];
    const reviews = buyerOps.reviews || [];

    return (
        <BuyerPanelShell
            activeKey="support"
            eyebrow="Buyer communication"
            title="Tickets, messages, notifications, and review history"
            description="Real support threads, seller chat, account alerts, and review records stay organized under one buyer inbox."
        >
            {initialTab === 'notifications' ? (
                <Panel title="Notifications center" icon={Bell}>
                    <div className="grid gap-3">
                        {notifications.length ? notifications.map((item) => <div key={item.id} className="rounded-2xl border border-slate-200 p-4"><div className="flex items-center justify-between gap-3"><p className="font-extrabold text-slate-950">{item.title}</p><Badge variant={item.read ? 'secondary' : 'success'}>{item.read ? 'Read' : 'Unread'}</Badge></div><p className="mt-2 text-sm font-semibold text-slate-500">{item.body || 'No details attached.'}</p></div>) : <p className="rounded-2xl bg-slate-50 p-6 text-sm font-semibold text-slate-500">No buyer notifications yet.</p>}
                    </div>
                </Panel>
            ) : initialTab === 'product-reviews' || initialTab === 'seller-reviews' ? (
                <Panel title={initialTab === 'seller-reviews' ? 'Seller reviews' : 'Product reviews'} icon={Star}>
                    <div className="grid gap-3">
                        {reviews.length ? reviews.map((review) => <div key={review.id} className="rounded-2xl border border-slate-200 p-4"><div className="flex items-center justify-between gap-3"><p className="font-extrabold text-slate-950">{review.product}</p><Badge variant="secondary">{review.rating}/5</Badge></div><p className="mt-2 text-sm font-semibold text-slate-500">{review.seller}</p><p className="mt-3 text-sm text-slate-700">{review.comment || 'No written review comment.'}</p></div>) : <p className="rounded-2xl bg-slate-50 p-6 text-sm font-semibold text-slate-500">No review activity found yet.</p>}
                    </div>
                </Panel>
            ) : (
                <Support state={state} sendMessage={sendMessage} uploadSellerMedia={uploadSellerMedia} />
            )}
        </BuyerPanelShell>
    );
}

function Support({ state, sendMessage, uploadSellerMedia }) {
    const CHAT_PAGE_SIZE = 20;
    const [body, setBody] = useState('');
    const [searchTerm, setSearchTerm] = useState('');
    const [chatSearchOpen, setChatSearchOpen] = useState(false);
    const [chatSearchTerm, setChatSearchTerm] = useState('');
    const [activeSearchIndex, setActiveSearchIndex] = useState(0);
    const [visibleMessages, setVisibleMessages] = useState(CHAT_PAGE_SIZE);
    const [isLoadingOlder, setIsLoadingOlder] = useState(false);
    const chatScrollRef = useRef(null);
    const bottomRef = useRef(null);
    const fileInputRef = useRef(null);
    const chatSearchInputRef = useRef(null);
    const messageNodeRefs = useRef({});
    const preserveScrollPositionRef = useRef(false);
    const previousScrollHeightRef = useRef(0);
    const shouldStickToBottomRef = useRef(true);
    const tickets = state.supportTickets || [];
    const [activeTicketId, setActiveTicketId] = useState(() => {
        if (typeof window !== 'undefined') {
            const fromUrl = new URLSearchParams(window.location.search).get('ticket');
            if (fromUrl) return fromUrl;
        }
        return tickets[0]?.id || 'SUP-26';
    });
    const [attachment, setAttachment] = useState(null);
    const [optimisticMessages, setOptimisticMessages] = useState({});
    const activeTicket = tickets.find((ticket) => ticket.id === activeTicketId) || tickets[0] || {
        id: 'SUP-26',
        threadId: null,
        subject: 'Buyer/seller support conversation',
        status: 'active',
        messages: [],
    };
    const resolvedThreadId = Number(activeTicket.threadId || String(activeTicket.id || '').replace(/\D+/g, '')) || null;
    const serverMessages = tickets.length
        ? (Array.isArray(activeTicket.messages) ? activeTicket.messages : [])
        : (state.chats || []);
    const messages = [...serverMessages, ...(optimisticMessages[activeTicket.id] || [])];
    const shownMessages = messages.slice(Math.max(0, messages.length - visibleMessages));
    const hasMoreMessages = visibleMessages < messages.length;
    const allOrders = [...(state.buyerOps?.ordersDetailed || []), ...(state.orders || [])];

    const loadOlderMessages = () => {
        const container = chatScrollRef.current;
        if (!container || !hasMoreMessages || isLoadingOlder) return;

        previousScrollHeightRef.current = container.scrollHeight;
        preserveScrollPositionRef.current = true;
        setIsLoadingOlder(true);
        setVisibleMessages((current) => Math.min(messages.length, current + CHAT_PAGE_SIZE));
    };

    const formatTicketMeta = (ticket) => {
        const updatedAt = ticket?.updatedAt || ticket?.lastMessageAt || ticket?.createdAt || null;
        if (!updatedAt) return 'Now';
        const date = new Date(updatedAt);
        if (Number.isNaN(date.getTime())) return 'Now';
        const now = new Date();
        const today = new Date(now.getFullYear(), now.getMonth(), now.getDate());
        const target = new Date(date.getFullYear(), date.getMonth(), date.getDate());
        const diffDays = Math.round((today.getTime() - target.getTime()) / 86400000);
        if (diffDays === 0) {
            return new Intl.DateTimeFormat('en-US', { hour: '2-digit', minute: '2-digit', hour12: false }).format(date);
        }
        if (diffDays === 1) return 'Yesterday';
        return new Intl.DateTimeFormat('en-US', { month: 'short', day: 'numeric' }).format(date);
    };

    const ticketPreview = (ticket) => {
        const latestMessage = Array.isArray(ticket?.messages) && ticket.messages.length ? ticket.messages[ticket.messages.length - 1] : null;
        return latestMessage?.body || ticket?.subject || 'Support conversation';
    };

    const ticketTitle = (ticket) => {
        const subject = String(ticket?.subject || '').trim();
        if (!subject) return 'Support chat';
        return subject.length > 32 ? subject.slice(0, 32).trimEnd() : subject;
    };

    const ticketInitials = (ticket) => {
        const words = ticketTitle(ticket).split(/\s+/).filter(Boolean);
        return (words[0]?.[0] || 'S').toUpperCase();
    };

    const filteredTickets = useMemo(() => {
        const query = searchTerm.trim().toLowerCase();
        if (!query) return tickets;
        return tickets.filter((ticket) => {
            const haystack = [
                ticket.id,
                ticket.subject,
                ticketPreview(ticket),
                ticket.status,
            ].join(' ').toLowerCase();
            return haystack.includes(query);
        });
    }, [tickets, searchTerm]);

    const otherPartyName = useMemo(() => {
        const nonSystem = messages.find((item) => !item.fromMe && item.from !== 'system');
        const subject = String(activeTicket.subject || '').trim();
        if (subject) {
            const cleaned = subject
                .replace(/support conversation/i, '')
                .replace(/buyer\/seller/i, '')
                .replace(/ticket/gi, '')
                .trim();
            if (cleaned) return cleaned;
        }
        if (String(nonSystem?.from || '').trim()) {
            return titleCase(String(nonSystem.from).replaceAll('_', ' '));
        }
        return 'Support Desk';
    }, [activeTicket.subject, messages]);

    const otherPartyRole = useMemo(() => {
        if (String(activeTicket.id || '').startsWith('SUP-')) return 'Seller';
        return 'Support';
    }, [activeTicket.id]);

    const relatedOrder = useMemo(() => {
        const orderCodeMatch = `${activeTicket.subject || ''} ${ticketPreview(activeTicket)}`.match(/ORD-[A-Z0-9-]+/i);
        const orderCode = orderCodeMatch ? orderCodeMatch[0].toUpperCase() : null;
        if (!orderCode) return null;
        return allOrders.find((order) => String(order.code || order.orderNumber || '').toUpperCase() === orderCode) || null;
    }, [activeTicket, allOrders]);

    const chatSearchMatches = useMemo(() => {
        const query = chatSearchTerm.trim().toLowerCase();
        if (!query) return [];
        return shownMessages
            .map((message, index) => ({
                index,
                id: message.id || `msg-${index}`,
                haystack: `${message.body || ''} ${message.attachmentName || ''}`.toLowerCase(),
            }))
            .filter((message) => message.haystack.includes(query));
    }, [shownMessages, chatSearchTerm]);

    const todayMarker = new Intl.DateTimeFormat('en-US', { weekday: 'long' }).format(new Date()).toUpperCase() === 'TODAY' ? 'TODAY' : 'TODAY';

    const submit = async () => {
        const message = body.trim();
        if (!message && !attachment) return;

        let payload = {
            thread_id: resolvedThreadId,
            ticket: activeTicket.id || null,
            body: message,
        };
        if (attachment && uploadSellerMedia) {
            const media = await uploadSellerMedia(attachment, 'support_attachment');
            if (media?.url) {
                payload = {
                    ...payload,
                    attachment_url: media.url,
                    attachment_name: media.original_name,
                    attachment_type: String(media.mime_type || '').startsWith('image/') ? 'image' : 'file',
                    attachment_mime: media.mime_type,
                    attachment_size: media.size,
                };
            }
        }
        const ticketKey = activeTicket.id || 'SUP-26';
        const optimisticMessage = {
            id: `tmp-${Date.now()}`,
            from: 'me',
            fromMe: true,
            body: message,
            time: currentTimeLabel(),
            attachmentUrl: payload.attachment_url || '',
            attachmentName: payload.attachment_name || '',
            attachmentType: payload.attachment_type || '',
            attachmentMime: payload.attachment_mime || '',
            attachmentSize: payload.attachment_size || null,
            isSystem: false,
        };

        setOptimisticMessages((current) => ({
            ...current,
            [ticketKey]: [...(current[ticketKey] || []), optimisticMessage],
        }));

        try {
            const response = await sendMessage(payload);
            const returnedTickets = response?.marketplace?.supportTickets || [];
            if (resolvedThreadId) {
                const matched = returnedTickets.find((ticket) => Number(ticket.threadId) === Number(resolvedThreadId));
                if (matched?.id) {
                    setActiveTicketId(matched.id);
                }
            } else if (returnedTickets[0]?.id) {
                setActiveTicketId(returnedTickets[0].id);
            }
            setOptimisticMessages((current) => ({
                ...current,
                [ticketKey]: [],
            }));
            setBody('');
            setAttachment(null);
            if (fileInputRef.current) fileInputRef.current.value = '';
        } catch (error) {
            setOptimisticMessages((current) => ({
                ...current,
                [ticketKey]: (current[ticketKey] || []).filter((item) => item.id !== optimisticMessage.id),
            }));
            throw error;
        }
    };
    const normalizedStatus = String(activeTicket.status || 'active').toLowerCase();
    const supportScrollClass = '[&::-webkit-scrollbar]:w-2 [&::-webkit-scrollbar-track]:bg-transparent [&::-webkit-scrollbar-thumb]:rounded-full [&::-webkit-scrollbar-thumb]:bg-slate-300 hover:[&::-webkit-scrollbar-thumb]:bg-slate-400';

    useLayoutEffect(() => {
        const container = chatScrollRef.current;
        if (!container) return;

        if (preserveScrollPositionRef.current) {
            const heightDiff = container.scrollHeight - previousScrollHeightRef.current;
            container.scrollTop = Math.max(0, heightDiff + container.scrollTop);
            preserveScrollPositionRef.current = false;
            setIsLoadingOlder(false);
            return;
        }

        if (shouldStickToBottomRef.current) {
            const scroll = () => bottomRef.current?.scrollIntoView({ block: 'end' });
            scroll();
            const frame = requestAnimationFrame(scroll);
            return () => cancelAnimationFrame(frame);
        }

        return undefined;
    }, [shownMessages.length, activeTicketId]);

    useEffect(() => {
        setVisibleMessages(CHAT_PAGE_SIZE);
        setIsLoadingOlder(false);
        setAttachment(null);
        preserveScrollPositionRef.current = false;
        shouldStickToBottomRef.current = true;
        if (fileInputRef.current) fileInputRef.current.value = '';
    }, [activeTicketId]);

    useEffect(() => {
        if (!chatSearchTerm.trim()) {
            setActiveSearchIndex(0);
            return;
        }
        setVisibleMessages(messages.length);
        setActiveSearchIndex(0);
    }, [chatSearchTerm, messages.length]);

    useEffect(() => {
        if (!chatSearchOpen) return;
        const frame = requestAnimationFrame(() => chatSearchInputRef.current?.focus());
        return () => cancelAnimationFrame(frame);
    }, [chatSearchOpen]);

    useEffect(() => {
        if (!chatSearchMatches.length) return;
        const currentMatch = chatSearchMatches[Math.min(activeSearchIndex, chatSearchMatches.length - 1)];
        const node = messageNodeRefs.current[currentMatch.id];
        node?.scrollIntoView({ block: 'center', behavior: 'smooth' });
    }, [activeSearchIndex, chatSearchMatches]);

    useEffect(() => {
        if (!tickets.length) return;
        const stillExists = tickets.some((ticket) => ticket.id === activeTicketId);
        if (!stillExists) {
            setActiveTicketId(tickets[0].id);
        }
    }, [tickets, activeTicketId]);

    useEffect(() => {
        if (typeof window === 'undefined' || !activeTicketId) return;
        const url = new URL(window.location.href);
        url.searchParams.set('ticket', activeTicketId);
        window.history.replaceState({}, '', `${url.pathname}${url.search}`);
    }, [activeTicketId]);

    const handleChatScroll = () => {
        const container = chatScrollRef.current;
        if (!container) return;

        const distanceFromBottom = container.scrollHeight - container.scrollTop - container.clientHeight;
        shouldStickToBottomRef.current = distanceFromBottom < 72;

        if (container.scrollTop <= 36 && hasMoreMessages && !isLoadingOlder) {
            loadOlderMessages();
        }
    };

    const toggleChatSearch = () => {
        setChatSearchOpen((current) => {
            if (current) {
                setChatSearchTerm('');
                setActiveSearchIndex(0);
            }
            return !current;
        });
    };

    const jumpToNextSearchMatch = () => {
        if (!chatSearchMatches.length) return;
        setActiveSearchIndex((current) => (current + 1) % chatSearchMatches.length);
    };

    return (
        <section className="min-h-[calc(100vh-7rem)] bg-[linear-gradient(180deg,#f8fafc_0%,#f3f6fb_100%)] px-1 pb-8 text-slate-950">
            <div className="mx-auto max-w-[1320px] overflow-hidden rounded-[24px] border border-slate-200/80 bg-white shadow-[0_20px_52px_-40px_rgba(15,23,42,0.2)]">
                <div className="grid h-[680px] xl:grid-cols-[310px_minmax(0,1fr)]">
                    <aside className="border-r border-slate-100 bg-[linear-gradient(180deg,#ffffff_0%,#fbfdff_100%)]">
                        <div className="border-b border-slate-100 px-5 py-5">
                            <div className="relative">
                                <Search className="pointer-events-none absolute left-4 top-1/2 size-4 -translate-y-1/2 text-slate-400" />
                                <input
                                    value={searchTerm}
                                    onChange={(event) => setSearchTerm(event.target.value)}
                                    placeholder="Search messages..."
                                    className="h-12 w-full rounded-xl border border-slate-200 bg-slate-50 pl-11 pr-4 text-[13px] font-medium text-slate-700 outline-none transition placeholder:text-slate-400 focus:border-indigo-200 focus:bg-white"
                                />
                            </div>
                        </div>
                        <div className="grid content-start gap-1.5 overflow-y-scroll overflow-x-hidden px-3 py-3 [scrollbar-color:theme(colors.slate.300)_transparent] [scrollbar-width:thin] [&::-webkit-scrollbar]:w-2 [&::-webkit-scrollbar-track]:bg-slate-100/80 [&::-webkit-scrollbar-thumb]:rounded-full [&::-webkit-scrollbar-thumb]:bg-slate-300 hover:[&::-webkit-scrollbar-thumb]:bg-slate-400">
                            {filteredTickets.length ? filteredTickets.map((ticket, index) => {
                                const active = (ticket.id || '') === (activeTicket.id || activeTicketId);
                                const unread = !active && index === 1;
                                return (
                                    <button
                                        key={ticket.id}
                                        type="button"
                                        onClick={() => setActiveTicketId(ticket.id)}
                                        className={cn(
                                            'w-full min-w-0 rounded-[16px] border px-3 py-2.5 text-left transition duration-200',
                                            active
                                                ? 'border-indigo-100 bg-[linear-gradient(180deg,#eef2ff_0%,#e9efff_100%)] shadow-[0_16px_36px_-28px_rgba(79,70,229,0.4)]'
                                                : 'border-transparent bg-white hover:border-slate-200 hover:bg-slate-50',
                                        )}
                                    >
                                        <div className="flex min-w-0 items-start gap-3">
                                            <div className="relative">
                                                <span className={cn(
                                                    'flex size-10 items-center justify-center rounded-full text-base font-black text-white shadow-[0_12px_20px_-18px_rgba(59,130,246,0.55)]',
                                                    active ? 'bg-[linear-gradient(135deg,#5b72ff_0%,#3b82f6_100%)]' : index % 3 === 1 ? 'bg-[linear-gradient(135deg,#ff7a18_0%,#ff4d6d_100%)]' : index % 3 === 2 ? 'bg-[linear-gradient(135deg,#14b8a6_0%,#22c55e_100%)]' : 'bg-[linear-gradient(135deg,#8b5cf6_0%,#ec4899_100%)]',
                                                )}>
                                                    {ticketInitials(ticket)}
                                                </span>
                                                <span className="absolute bottom-0 right-0 size-3.5 rounded-full border-2 border-white bg-emerald-500" />
                                            </div>
                                            <div className="min-w-0 flex-1 overflow-hidden">
                                                <div className="flex items-start justify-between gap-3">
                                                    <p className="truncate text-[13px] font-black text-slate-950">{ticketTitle(ticket)}</p>
                                                    <span className={cn('shrink-0 text-xs font-bold', active ? 'text-indigo-600' : 'text-slate-400')}>{formatTicketMeta(ticket)}</span>
                                                </div>
                                                <p className={cn('mt-0.5 line-clamp-2 text-[11px] leading-4', active ? 'text-slate-600' : 'text-slate-500')}>{ticketPreview(ticket)}</p>
                                            </div>
                                            {unread ? <span className="mt-5 flex size-5 shrink-0 items-center justify-center rounded-full bg-indigo-600 text-[10px] font-black text-white">2</span> : null}
                                        </div>
                                    </button>
                                );
                            }) : (
                                <div className="rounded-2xl border border-dashed border-slate-200 bg-slate-50 p-6 text-center">
                                    <p className="text-sm font-black text-slate-950">No matching tickets</p>
                                    <p className="mt-1 text-sm font-semibold text-slate-500">Try a different search term.</p>
                                </div>
                            )}
                        </div>
                    </aside>

                    <section className="flex min-h-0 flex-col">
                        <div className="flex h-[78px] items-center justify-between border-b border-slate-100 px-6">
                            <div className="flex items-center gap-5">
                                <div className="relative">
                                    <span className="flex size-12 items-center justify-center rounded-full bg-[linear-gradient(135deg,#5b72ff_0%,#3b82f6_100%)] text-xl font-black text-white shadow-[0_16px_28px_-24px_rgba(59,130,246,0.65)]">
                                        {ticketInitials(activeTicket)}
                                    </span>
                                </div>
                                <div>
                                    <h1 className="text-[15px] font-black text-slate-950 md:text-base">{otherPartyName}</h1>
                                    <div className="mt-0.5 flex flex-wrap items-center gap-2 text-[11px] font-bold text-slate-400">
                                        <span className="inline-flex items-center gap-2 text-slate-500"><span className="size-2.5 rounded-full bg-emerald-500" />Online now</span>
                                        <span>•</span>
                                        <span className="uppercase tracking-[0.18em] text-slate-500">{otherPartyRole}</span>
                                        <span>•</span>
                                        <span className={cn(normalizedStatus === 'resolved' ? 'text-emerald-600' : 'text-indigo-600')}>{payoutStatusLabel(activeTicket.status || 'active')}</span>
                                    </div>
                                </div>
                            </div>
                            <div className="flex items-center gap-2">
                                {chatSearchOpen ? (
                                    <div className="mr-2 flex items-center gap-2 rounded-xl border border-slate-200 bg-slate-50 px-2 py-1.5">
                                        <Search className="size-4 text-slate-400" />
                                        <input
                                            ref={chatSearchInputRef}
                                            value={chatSearchTerm}
                                            onChange={(event) => setChatSearchTerm(event.target.value)}
                                            placeholder="Search chat..."
                                            className="w-36 bg-transparent text-xs font-semibold text-slate-700 outline-none placeholder:text-slate-400"
                                        />
                                        <span className="text-[10px] font-bold text-slate-400">{chatSearchMatches.length ? `${Math.min(activeSearchIndex + 1, chatSearchMatches.length)}/${chatSearchMatches.length}` : '0/0'}</span>
                                        <button type="button" onClick={jumpToNextSearchMatch} className="rounded-md px-1.5 py-0.5 text-[10px] font-black text-indigo-600 hover:bg-white" disabled={!chatSearchMatches.length}>Next</button>
                                    </div>
                                ) : null}
                                <Button type="button" onClick={toggleChatSearch} variant="ghost" size="icon" className="rounded-full text-slate-400 hover:bg-slate-50 hover:text-slate-700" aria-label="Search in conversation">
                                    <Search className="size-4" />
                                </Button>
                                {relatedOrder ? (
                                    <Button asChild type="button" variant="ghost" size="icon" className="rounded-full text-slate-400 hover:bg-slate-50 hover:text-slate-700" aria-label="Open order details">
                                        <Link href={`/order-details?order=${relatedOrder.id}`}>
                                            <ReceiptText className="size-4" />
                                        </Link>
                                    </Button>
                                ) : (
                                    <Button type="button" variant="ghost" size="icon" className="rounded-full text-slate-300 hover:bg-slate-50 hover:text-slate-400" aria-label="Order details unavailable" disabled>
                                        <ReceiptText className="size-4" />
                                    </Button>
                                )}
                            </div>
                        </div>

                        <div className="flex min-h-0 flex-1 flex-col overflow-hidden">
                            <div ref={chatScrollRef} onScroll={handleChatScroll} className={cn('flex-1 overflow-y-auto bg-[linear-gradient(180deg,#ffffff_0%,#fcfdff_100%)] px-6 py-6', supportScrollClass)}>
                            {messages.length ? (
                                <div className="space-y-6">
                                    <div className="sticky top-0 z-10 flex justify-center pb-2">
                                        {hasMoreMessages ? (
                                            <div className="flex flex-col items-center gap-2">
                                                <button
                                                    type="button"
                                                    onClick={loadOlderMessages}
                                                    disabled={isLoadingOlder}
                                                    className="rounded-full border border-slate-200 bg-white/95 px-4 py-2 text-xs font-black uppercase tracking-[0.16em] text-slate-500 shadow-sm backdrop-blur transition hover:border-indigo-200 hover:text-indigo-600 disabled:cursor-wait disabled:opacity-70"
                                                >
                                                    {isLoadingOlder ? 'Loading earlier messages...' : 'Load earlier messages'}
                                                </button>
                                                <p className="text-[10px] font-bold uppercase tracking-[0.16em] text-slate-400">Scroll up to load more automatically</p>
                                            </div>
                                        ) : (
                                            <span className="rounded-full border border-slate-200 bg-white px-5 py-2 text-[11px] font-black uppercase tracking-[0.2em] text-slate-400 shadow-sm">{todayMarker}</span>
                                        )}
                                    </div>
                                    {shownMessages.map((chat, index) => {
                                        const outgoing = Boolean(chat.fromMe) || chat.from === 'me';
                                        const system = Boolean(chat.isSystem) || chat.from === 'system' || String(chat.body || '').toLowerCase().includes('escrow');
                                        const messageRefKey = chat.id || `msg-${index}`;
                                        const query = chatSearchTerm.trim().toLowerCase();
                                        const matchesSearch = query && `${chat.body || ''} ${chat.attachmentName || ''}`.toLowerCase().includes(query);
                                        const currentSearchMatch = query && chatSearchMatches[activeSearchIndex]?.id === messageRefKey;
                                        const isImageAttachment = Boolean(chat.attachmentUrl) && (
                                            String(chat.attachmentType || '').toLowerCase() === 'image'
                                            || String(chat.attachmentMime || '').toLowerCase().startsWith('image/')
                                        );
                                        if (system) {
                                            return (
                                                <div key={index} className="flex justify-center">
                                                    <div className="inline-flex items-center gap-3 rounded-full border border-emerald-100 bg-white px-5 py-2.5 text-xs font-black text-slate-600 shadow-[0_10px_24px_-20px_rgba(15,23,42,0.3)]">
                                                        <span className="flex size-6 items-center justify-center rounded-full bg-emerald-50 text-emerald-600">
                                                            <ShieldCheck className="size-3.5" />
                                                        </span>
                                                        <span>{chat.body}</span>
                                                        <span className="text-slate-400">{chat.time || 'Now'}</span>
                                                    </div>
                                                </div>
                                            );
                                        }
                                        return (
                                            <div
                                                key={index}
                                                ref={(node) => {
                                                    if (node) messageNodeRefs.current[messageRefKey] = node;
                                                    else delete messageNodeRefs.current[messageRefKey];
                                                }}
                                                className={cn('flex flex-col', outgoing ? 'items-end' : 'items-start')}
                                            >
                                                {!outgoing ? <p className="mb-2 px-1 text-[11px] font-black uppercase tracking-[0.14em] text-slate-400">{otherPartyName}</p> : null}
                                                <div className={cn(
                                                    'max-w-[76%] px-5 py-4 text-[14px] leading-6 shadow-sm ring-1',
                                                    outgoing
                                                        ? 'rounded-[22px] rounded-br-[10px] bg-[linear-gradient(90deg,#5648f5_0%,#5d46f6_48%,#4f46e5_100%)] font-semibold text-white shadow-[0_20px_36px_-24px_rgba(86,72,245,0.38)] ring-transparent'
                                                        : 'rounded-[18px] rounded-bl-[10px] border border-slate-200 bg-white font-normal text-slate-900 shadow-[0_10px_22px_-22px_rgba(15,23,42,0.22)] ring-transparent',
                                                    matchesSearch && 'ring-amber-300',
                                                    currentSearchMatch && 'ring-2 ring-indigo-400',
                                                )}>
                                                    {chat.attachmentUrl ? (
                                                        <div className={cn(chat.body ? 'mb-2.5' : '')}>
                                                            {isImageAttachment ? (
                                                                <a href={chat.attachmentUrl} target="_blank" rel="noreferrer" className="block">
                                                                    <img
                                                                        src={chat.attachmentUrl}
                                                                        alt={chat.attachmentName || 'Attachment preview'}
                                                                        loading="lazy"
                                                                        className={cn(
                                                                            'max-h-56 w-auto max-w-full rounded-xl border object-cover',
                                                                            outgoing ? 'border-white/15 bg-white/8' : 'border-slate-200',
                                                                        )}
                                                                    />
                                                                </a>
                                                            ) : null}
                                                            <a
                                                                href={chat.attachmentUrl}
                                                                target="_blank"
                                                                rel="noreferrer"
                                                                className={cn(
                                                                    'mt-2 inline-flex max-w-full items-center gap-2 rounded-xl px-3 py-2 text-[13px] font-semibold',
                                                                    outgoing ? 'bg-white/10 text-white/95' : 'bg-slate-50 text-slate-700',
                                                                    !isImageAttachment && 'mt-0',
                                                                )}
                                                            >
                                                                <Paperclip className="size-3.5" />
                                                                <span className="truncate">{chat.attachmentName || 'Attachment'}</span>
                                                            </a>
                                                        </div>
                                                    ) : null}
                                                    {chat.body ? <p className="text-[14px] leading-6">{chat.body}</p> : null}
                                                </div>
                                                <p className={cn('mt-2 px-1 text-[11px] font-extrabold text-slate-400', outgoing && 'pr-1 text-[#8f88ff]')}>
                                                    {chat.time || 'Now'} {outgoing ? <span className="ml-1 text-[#6d43f8]">✓✓</span> : null}
                                                </p>
                                            </div>
                                        );
                                    })}
                                    <div ref={bottomRef} />
                                </div>
                            ) : (
                                <div className="flex h-full items-center justify-center">
                                    <div className="max-w-sm text-center">
                                        <span className="mx-auto flex size-14 items-center justify-center rounded-2xl bg-indigo-50 text-indigo-600">
                                            <MessageSquareText className="size-6" />
                                        </span>
                                        <p className="mt-3 text-base font-black text-slate-950">No messages yet</p>
                                        <p className="mt-1 text-sm font-semibold leading-6 text-slate-500">Write a message to start this support conversation.</p>
                                    </div>
                                </div>
                            )}
                            </div>

                            <div className="border-t border-slate-100 bg-white px-5 py-4">
                            {attachment ? (
                                <div className="mb-3 flex items-center justify-between rounded-xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm">
                                    <span className="truncate font-semibold text-slate-700">{attachment.name}</span>
                                    <Button type="button" variant="ghost" size="icon" className="size-8" onClick={() => {
                                        setAttachment(null);
                                        if (fileInputRef.current) fileInputRef.current.value = '';
                                    }}>
                                        <X className="size-4" />
                                    </Button>
                                </div>
                            ) : null}
                            <div className="rounded-[20px] border border-slate-200 bg-[linear-gradient(180deg,#ffffff_0%,#f8fafc_100%)] p-2 shadow-[0_10px_24px_-24px_rgba(15,23,42,0.22)]">
                                <div className="grid grid-cols-[40px_1fr_108px] gap-2">
                                <Button type="button" variant="ghost" className="h-10 rounded-lg bg-slate-50 text-slate-400 hover:bg-slate-100" aria-label="Attach file" onClick={() => fileInputRef.current?.click()}>
                                    <Paperclip className="size-4" />
                                </Button>
                                <input ref={fileInputRef} type="file" accept="image/*,.pdf,.doc,.docx,.txt,.zip" className="sr-only" onChange={(event) => setAttachment(event.target.files?.[0] || null)} />
                                <Input
                                    value={body}
                                    onChange={(event) => setBody(event.target.value)}
                                    onKeyDown={(event) => {
                                        if (event.key === 'Enter') {
                                            event.preventDefault();
                                            submit();
                                        }
                                    }}
                                    placeholder="Type your message securely..."
                                    className="h-10 rounded-lg border-0 bg-slate-50 px-4 text-sm font-semibold shadow-none placeholder:text-slate-400 focus-visible:ring-0"
                                />
                                <Button type="button" onClick={submit} className="h-10 rounded-lg bg-slate-950 text-sm font-black shadow-[0_14px_28px_-22px_rgba(15,23,42,0.9)]">
                                    Send <Send className="size-4" />
                                </Button>
                                </div>
                            </div>
                        </div>
                    </div>
                    </section>
                </div>
            </div>
        </section>
    );
}
function SellerDashboard({ state }) {
    const revenue = state.orders.reduce((sum, order) => sum + order.amount, 0);
    const lowStock = state.sellerProducts.filter((product) => asNumber(product.stock) > 0 && asNumber(product.stock) < 10);
    const outOfStock = state.sellerProducts.filter((product) => asNumber(product.stock) <= 0);
    const activeOrders = state.orders.filter((order) => order.status !== 'Completed');
    const displayName = state.business?.name || state.user?.name || 'Seller workspace';
    const logoUrl = state.business?.storeLogoUrl || '';
    const bannerUrl = state.business?.bannerImageUrl || '';
    const initials = displayName.split(/\s+/).filter(Boolean).slice(0, 2).map((part) => part[0]).join('').toUpperCase() || 'S';
    const totalUnitsSold = state.sellerProducts.reduce((sum, product) => sum + asNumber(product.soldCount || product.sold || 0), 0);
    const averageOrderValue = state.orders.length ? revenue / state.orders.length : 0;
    const conversionSignal = state.wishlist.length ? Math.min(100, Math.round((state.orders.length / state.wishlist.length) * 100)) : 0;
    const sellerActions = [
        ['/seller/products', 'Create listing', Plus],
        ['/seller/orders', 'Manage orders', Truck],
        ['/seller/warehouses', 'Warehouse', Building2],
        ['/seller/shipping-settings', 'Shipping rules', PackageCheck],
        ['/seller/payouts', 'Request payout', WalletCards],
    ];
    const readiness = [
        ['Store profile', state.business?.name ? 'Configured' : 'Needs setup', Boolean(state.business?.name)],
        ['Catalog', state.sellerProducts.length ? `${state.sellerProducts.length} listing${state.sellerProducts.length === 1 ? '' : 's'}` : 'No listings yet', state.sellerProducts.length > 0],
        ['Warehouse', state.sellerOps?.warehouses?.length ? `${state.sellerOps.warehouses.length} active` : 'Needs setup', (state.sellerOps?.warehouses?.length || 0) > 0],
        ['Fulfillment', activeOrders.length ? `${activeOrders.length} active` : 'Clear', true],
        ['Support', state.supportTickets.length ? `${state.supportTickets.length} open` : 'Clear', state.supportTickets.length === 0],
    ];
    const orderTrend = state.orders.slice(-6).map((order, index) => ({
        label: order.id || `#${index + 1}`,
        value: asNumber(order.amount),
    }));
    const orderTrendSeries = orderTrend.length ? orderTrend : Array.from({ length: 6 }, (_, index) => ({ label: `W${index + 1}`, value: [0, 0, 0, 0, 0, 0][index] }));
    const productTypeCounts = [
        ['Physical', state.sellerProducts.filter((product) => product.productType === 'physical').length, Truck],
        ['Digital', state.sellerProducts.filter((product) => product.productType === 'digital').length, Download],
        ['Instant', state.sellerProducts.filter((product) => product.productType === 'digital' && product.isInstantDelivery).length, Zap],
        ['Service', state.sellerProducts.filter((product) => product.productType === 'service').length, BriefcaseBusiness],
    ];
    const healthBreakdown = [
        { label: 'Healthy', value: state.sellerProducts.filter((product) => asNumber(product.stock) >= 10).length, tone: 'bg-emerald-500' },
        { label: 'Low', value: lowStock.length, tone: 'bg-amber-400' },
        { label: 'Out', value: outOfStock.length, tone: 'bg-rose-500' },
    ];
    const topListings = [...state.sellerProducts]
        .sort((a, b) => asNumber(b.soldCount || b.sold || 0) - asNumber(a.soldCount || a.sold || 0))
        .slice(0, 4);

    return (
        <div className="space-y-5">
            <section className="overflow-hidden rounded-[28px] border border-slate-200 bg-white shadow-[0_28px_80px_-48px_rgba(15,23,42,0.45)]">
                <div className="relative">
                    <div className="h-[250px] bg-[linear-gradient(135deg,#0f172a_0%,#312e81_42%,#5b4cf0_100%)]">
                        {bannerUrl ? <img src={bannerUrl} alt="Seller dashboard banner" className="h-full w-full object-cover" /> : null}
                    </div>
                    <div className="absolute inset-0 bg-[linear-gradient(180deg,rgba(2,6,23,0.12)_0%,rgba(2,6,23,0.75)_72%,rgba(2,6,23,0.92)_100%)]" />
                    <div className="absolute inset-x-0 bottom-0 p-5 sm:p-6">
                        <div className="flex flex-col gap-6 xl:flex-row xl:items-end xl:justify-between">
                            <div className="flex items-center gap-4">
                                <div className="flex size-20 shrink-0 items-center justify-center overflow-hidden rounded-[22px] border-4 border-white/20 bg-white/10 text-xl font-extrabold text-white shadow-[0_22px_50px_-26px_rgba(15,23,42,0.7)] backdrop-blur">
                                    {logoUrl ? <img src={logoUrl} alt="Seller logo" className="h-full w-full object-cover" /> : initials}
                                </div>
                                <div>
                                    <p className="text-xs font-extrabold uppercase tracking-[0.22em] text-violet-200">Seller command center</p>
                                    <h1 className="mt-2 text-3xl font-extrabold tracking-tight text-white sm:text-4xl">{displayName}</h1>
                                    <p className="mt-2 max-w-2xl text-sm font-semibold text-slate-200">{state.business?.storeDescription || `${state.user?.email || 'Seller account'} · ${state.business?.verification || 'unverified'} seller`}</p>
                                    <div className="mt-3 flex flex-wrap items-center gap-2 text-xs font-extrabold uppercase tracking-[0.16em] text-slate-200">
                                        <span className="rounded-full border border-white/15 bg-white/10 px-3 py-1.5">{state.business?.verification || 'unverified'}</span>
                                        <span className="rounded-full border border-white/15 bg-white/10 px-3 py-1.5">{state.sellerProducts.length} listings</span>
                                        <span className="rounded-full border border-white/15 bg-white/10 px-3 py-1.5">{activeOrders.length} active orders</span>
                                    </div>
                                </div>
                            </div>
                            <div className="flex flex-wrap gap-2">
                                <Button asChild className="bg-white text-slate-950 hover:bg-violet-50"><Link href="/seller/products"><Plus className="size-4" />Create listing</Link></Button>
                                <Button asChild variant="outline" className="border-white/20 bg-white/10 text-white hover:bg-white/15 hover:text-white"><Link href="/seller/business"><Settings className="size-4" />Store settings</Link></Button>
                            </div>
                        </div>
                    </div>
                </div>

                <div className="grid gap-3 border-t border-slate-200 bg-slate-50 p-5 sm:grid-cols-2 xl:grid-cols-4">
                    <Stat label="Revenue" value={money(revenue)} hint="All web orders" icon={BarChart3} />
                    <Stat label="Units sold" value={totalUnitsSold} hint="Across seller catalog" icon={Package} />
                    <Stat label="Average order" value={money(averageOrderValue)} hint="Order value trend" icon={WalletCards} />
                    <Stat label="Stock alerts" value={lowStock.length + outOfStock.length} hint={`${outOfStock.length} sold out`} icon={Boxes} />
                </div>
            </section>

            <section className="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                {sellerActions.map(([href, label, Icon]) => (
                    <Link key={href} href={href} className="group flex items-center justify-between rounded-xl border border-slate-200 bg-white p-4 shadow-sm transition hover:-translate-y-0.5 hover:border-indigo-200 hover:shadow-md">
                        <div>
                            <p className="text-sm font-extrabold text-slate-950">{label}</p>
                            <p className="mt-1 text-xs font-semibold text-slate-500">Open workspace</p>
                        </div>
                        <span className="flex size-9 items-center justify-center rounded-lg bg-indigo-50 text-indigo-700 transition group-hover:bg-indigo-600 group-hover:text-white"><Icon className="size-4" /></span>
                    </Link>
                ))}
            </section>

            <section className="grid gap-5 xl:grid-cols-[1.3fr_0.9fr_0.8fr]">
                <Panel title="Revenue pulse" icon={BarChart3}>
                    <div className="grid gap-4 lg:grid-cols-[1fr_220px]">
                        <div>
                            <div className="flex items-end justify-between gap-3">
                                <div>
                                    <p className="text-xs font-extrabold uppercase tracking-[0.16em] text-slate-400">Recent order trend</p>
                                    <p className="mt-2 text-3xl font-extrabold tracking-tight text-slate-950">{money(revenue)}</p>
                                </div>
                                <div className="rounded-2xl border border-indigo-100 bg-indigo-50 px-4 py-3 text-right">
                                    <p className="text-[11px] font-black uppercase tracking-[0.16em] text-indigo-500">Conversion signal</p>
                                    <p className="mt-1 text-lg font-extrabold text-indigo-700">{conversionSignal}%</p>
                                </div>
                            </div>
                            <SellerRevenueBars items={orderTrendSeries} />
                        </div>
                        <div className="grid gap-3">
                            {readiness.map(([label, value, ok]) => (
                                <div key={label} className="rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3">
                                    <p className="text-xs font-black uppercase tracking-[0.16em] text-slate-400">{label}</p>
                                    <p className={cn('mt-2 inline-flex items-center gap-2 text-sm font-extrabold', ok ? 'text-emerald-700' : 'text-amber-700')}>
                                        {ok ? <Check className="size-4" /> : <AlertCircle className="size-4" />}{value}
                                    </p>
                                </div>
                            ))}
                        </div>
                    </div>
                </Panel>

                <Panel title="Catalog mix" icon={Sparkles}>
                    <div className="space-y-4">
                        {productTypeCounts.map(([label, value, Icon]) => (
                            <SellerChartBar key={label} label={label} value={value} max={Math.max(1, ...productTypeCounts.map((entry) => entry[1]))} icon={Icon} />
                        ))}
                    </div>
                </Panel>

                <Panel title="Inventory health" icon={Boxes}>
                    <div className="rounded-2xl border border-slate-200 bg-slate-50 p-4">
                        <div className="flex h-4 overflow-hidden rounded-full bg-slate-200">
                            {healthBreakdown.map((item) => {
                                const total = Math.max(1, state.sellerProducts.length);
                                const width = `${(item.value / total) * 100}%`;
                                return <div key={item.label} className={item.tone} style={{ width }} />;
                            })}
                        </div>
                        <div className="mt-4 grid gap-3">
                            {healthBreakdown.map((item) => (
                                <div key={item.label} className="flex items-center justify-between gap-3 rounded-xl bg-white px-3 py-2.5">
                                    <div className="flex items-center gap-2">
                                        <span className={cn('size-3 rounded-full', item.tone)} />
                                        <span className="text-sm font-bold text-slate-700">{item.label}</span>
                                    </div>
                                    <span className="text-sm font-extrabold text-slate-950">{item.value}</span>
                                </div>
                            ))}
                        </div>
                    </div>
                </Panel>
            </section>

            <section className="grid gap-5 lg:grid-cols-2">
                <Panel title="Stock risks" icon={Boxes}>
                    <div className="grid gap-3">
                        {[...outOfStock, ...lowStock].slice(0, 5).map((product) => (
                            <div key={product.id} className="flex items-center justify-between gap-3 rounded-lg border border-slate-200 p-3">
                                <div className="min-w-0">
                                    <p className="line-clamp-1 font-bold text-slate-950">{product.title}</p>
                                    <p className="mt-1 text-sm text-slate-500">{product.productTypeLabel || product.type} · Stock {product.stock}</p>
                                </div>
                                <Badge variant={asNumber(product.stock) <= 0 ? 'warning' : 'secondary'}>{asNumber(product.stock) <= 0 ? 'Out' : 'Low'}</Badge>
                            </div>
                        ))}
                        {!state.sellerProducts.length ? (
                            <div className="rounded-lg border border-dashed border-slate-300 bg-slate-50 p-6 text-center">
                                <p className="font-extrabold text-slate-950">Your catalog is ready for the first listing.</p>
                                <p className="mt-2 text-sm font-medium text-slate-500">Create physical, digital, or service listings from the same seller workflow.</p>
                                <Button asChild className="mt-4"><Link href="/seller/products"><Plus className="size-4" />Create first listing</Link></Button>
                            </div>
                        ) : !outOfStock.length && !lowStock.length ? <p className="rounded-lg bg-emerald-50 p-4 text-sm font-semibold text-emerald-700">Inventory is healthy.</p> : null}
                    </div>
                </Panel>
                <Panel title="Fulfillment pipeline" icon={PackageCheck}>
                    <div className="grid gap-3">
                        {activeOrders.slice(0, 5).map((order) => (
                            <div key={order.id} className="rounded-lg border border-slate-200 p-3">
                                <div className="flex justify-between gap-3">
                                    <p className="font-bold text-slate-950">{order.id}</p>
                                    <Badge variant="secondary">{order.status}</Badge>
                                </div>
                                <p className="mt-1 line-clamp-1 text-sm text-slate-500">{order.product} · {order.stage}</p>
                                <div className="mt-3 h-2 rounded-full bg-slate-200"><div className="h-full rounded-full bg-cyan-600" style={{ width: `${order.progress}%` }} /></div>
                            </div>
                        ))}
                        {!activeOrders.length ? <p className="rounded-lg bg-slate-50 p-4 text-sm font-medium text-slate-500">No active fulfillment work right now.</p> : null}
                    </div>
                </Panel>
            </section>

            <section className="grid gap-5 xl:grid-cols-[1fr_360px]">
                <Panel title="Top performing listings" icon={TrendingUp}>
                    <div className="grid gap-3 md:grid-cols-2">
                        {topListings.length ? topListings.map((product, index) => (
                            <div key={product.id} className="rounded-2xl border border-slate-200 bg-white p-4 shadow-[0_18px_42px_-34px_rgba(15,23,42,0.2)]">
                                <div className="flex items-start justify-between gap-3">
                                    <div className="min-w-0">
                                        <p className="text-xs font-black uppercase tracking-[0.16em] text-slate-400">Top {index + 1}</p>
                                        <p className="mt-2 line-clamp-1 text-base font-extrabold text-slate-950">{product.title}</p>
                                        <p className="mt-1 text-sm font-semibold text-slate-500">{product.productTypeLabel || product.type}</p>
                                    </div>
                                    <Badge variant="secondary">{asNumber(product.soldCount || product.sold || 0)} sold</Badge>
                                </div>
                                <div className="mt-4 flex items-end justify-between gap-3">
                                    <div>
                                        <p className="text-xs font-black uppercase tracking-[0.16em] text-slate-400">Price</p>
                                        <p className="mt-1 text-xl font-extrabold text-slate-950">{money(product.price)}</p>
                                    </div>
                                    <div className="rounded-xl border border-slate-200 bg-slate-50 px-3 py-2">
                                        <p className="text-[11px] font-black uppercase tracking-[0.16em] text-slate-400">Stock</p>
                                        <p className="mt-1 text-sm font-extrabold text-slate-800">{asNumber(product.stock)}</p>
                                    </div>
                                </div>
                            </div>
                        )) : <p className="rounded-lg bg-slate-50 p-5 text-sm font-semibold text-slate-500 md:col-span-2">Performance insights will appear after your listings start selling.</p>}
                    </div>
                </Panel>

                <Panel title="Priority queue" icon={ClipboardCheck}>
                    <div className="grid gap-3">
                        <Link href="/seller/warehouses" className="rounded-2xl border border-slate-200 bg-slate-50 p-4 transition hover:border-indigo-200 hover:bg-indigo-50">
                            <p className="text-sm font-extrabold text-slate-950">Inventory watch</p>
                            <p className="mt-1 text-sm font-semibold text-slate-500">{lowStock.length + outOfStock.length ? `${lowStock.length + outOfStock.length} listings need stock attention.` : 'Inventory levels look healthy.'}</p>
                        </Link>
                        <Link href="/seller/orders" className="rounded-2xl border border-slate-200 bg-slate-50 p-4 transition hover:border-indigo-200 hover:bg-indigo-50">
                            <p className="text-sm font-extrabold text-slate-950">Fulfillment queue</p>
                            <p className="mt-1 text-sm font-semibold text-slate-500">{activeOrders.length ? `${activeOrders.length} orders are still moving through delivery.` : 'No active fulfillment bottlenecks right now.'}</p>
                        </Link>
                        <Link href="/seller/payouts" className="rounded-2xl border border-slate-200 bg-slate-50 p-4 transition hover:border-indigo-200 hover:bg-indigo-50">
                            <p className="text-sm font-extrabold text-slate-950">Cash flow</p>
                            <p className="mt-1 text-sm font-semibold text-slate-500">{money(averageOrderValue)} average order value and {money(revenue)} booked revenue.</p>
                        </Link>
                    </div>
                </Panel>
            </section>
        </div>
    );
}

function SellerRevenueBars({ items }) {
    const maxValue = Math.max(1, ...items.map((item) => asNumber(item.value)));

    return (
        <div className="mt-5 rounded-2xl border border-slate-200 bg-slate-50 p-4">
            <div className="flex h-44 items-end gap-3">
                {items.map((item) => (
                    <div key={item.label} className="flex flex-1 flex-col items-center justify-end gap-2">
                        <span className="text-[11px] font-black text-slate-400">{asNumber(item.value) > 0 ? money(item.value) : '0'}</span>
                        <div className="flex h-32 w-full items-end rounded-2xl bg-white p-1">
                            <div
                                className="w-full rounded-[18px] bg-[linear-gradient(180deg,#5b4cf0_0%,#312e81_100%)] shadow-[0_18px_30px_-18px_rgba(91,76,240,0.7)]"
                                style={{ height: `${Math.max(10, (asNumber(item.value) / maxValue) * 100)}%` }}
                            />
                        </div>
                        <span className="text-[11px] font-black uppercase tracking-[0.16em] text-slate-400">{item.label}</span>
                    </div>
                ))}
            </div>
        </div>
    );
}

function SellerChartBar({ label, value, max, icon: Icon }) {
    const width = max > 0 ? Math.max(8, (value / max) * 100) : 8;

    return (
        <div className="rounded-2xl border border-slate-200 bg-slate-50 p-4">
            <div className="flex items-center justify-between gap-3">
                <div className="flex items-center gap-3">
                    <span className="flex size-10 items-center justify-center rounded-xl bg-white text-indigo-600 shadow-sm">
                        <Icon className="size-4" />
                    </span>
                    <div>
                        <p className="text-sm font-extrabold text-slate-950">{label}</p>
                        <p className="text-xs font-semibold text-slate-500">Product count</p>
                    </div>
                </div>
                <span className="text-lg font-extrabold text-slate-950">{value}</span>
            </div>
            <div className="mt-4 h-3 overflow-hidden rounded-full bg-white">
                <div className="h-full rounded-full bg-[linear-gradient(90deg,#5b4cf0_0%,#312e81_100%)]" style={{ width: `${width}%` }} />
            </div>
        </div>
    );
}

function productFormFromProduct(product, state) {
    const attrs = product?.attributes || {};
    const categoryId = product?.category_id || state.categories?.find(isRootCategory)?.id || '';
    return {
        title: product?.title || '',
        slug: product?.slug || '',
        sku: product?.sku || '',
        brand: product?.brand || '',
        category_id: categoryId,
        subcategory_id: product?.subcategory_id || '',
        product_type: product?.productType || 'physical',
        short_description: product?.shortDescription || attrs.short_description || '',
        description: product?.description || '',
        image_url: product?.image || '',
        featured_image: product?.featuredImage || product?.image || '',
        gallery_images: product?.images?.length ? product.images : [''],
        video_url: product?.videoUrl || '',
        price: String(product?.regularPrice ?? product?.oldPrice ?? product?.price ?? ''),
        sale_price: product?.salePrice ? String(product.salePrice) : '',
        discount_type: product?.discountType || 'percentage',
        discount_value: product?.discountValue ? String(product.discountValue) : '',
        stock: String(product?.stock ?? 0),
        low_stock_alert: String(product?.lowStockAlert ?? 5),
        warehouse_id: attrs.warehouse_id || state.sellerOps?.warehouses?.[0]?.id || '',
        variants: product?.variants?.length ? product.variants.map((variant) => ({
            title: variant.title || '',
            sku: variant.sku || '',
            price: String(variant.price ?? product.price ?? ''),
            stock: String(variant.stock ?? 0),
            size: variant.attributes?.size || '',
            color: variant.attributes?.color || '',
            weight: variant.attributes?.weight || '',
            attributes: variant.attributes?.custom || '',
        })) : [{ title: '', sku: '', price: '', stock: '', size: '', color: '', weight: '', attributes: '' }],
        shipping_weight: product?.shippingWeight || '',
        shipping_dimensions: product?.shippingDimensions || '',
        tax_class: product?.taxClass || '',
        warranty_information: product?.warrantyStatus || '',
        return_policy: product?.returnPolicy || '',
        seo_title: product?.seoTitle || '',
        seo_description: product?.seoDescription || '',
        status: product?.status || 'pending_review',
        condition: product?.condition || 'New',
        delivery_note: attrs.delivery_note || '',
        digital_product_kind: attrs.digital_product_kind || '',
        access_type: attrs.access_type || '',
        platform: attrs.platform || '',
        license_type: attrs.license_type || '',
        delivery_fulfillment_hours: String(attrs.delivery_fulfillment_hours || '24'),
        is_instant_delivery: Boolean(product?.isInstantDelivery || attrs.is_instant_delivery || product?.productType === 'instant_delivery'),
        instant_delivery_expiration_hours: String(attrs.instant_delivery_expiration_hours || '72'),
        digital_access_validity_hours: String(attrs.digital_access_validity_hours || ''),
    };
}

function SellerProducts({ state, saveSellerProduct, duplicateSellerProduct, bulkSellerProducts, deleteSellerProduct, uploadSellerMedia, mode = 'list' }) {
    const rootCategoryRows = (state.categories || []).filter(isRootCategory);
    const rootCategories = rootCategoryRows.length ? rootCategoryRows : ((state.categories || []).length ? state.categories : [{ id: '', name: 'General Marketplace' }]);
    const firstCategory = rootCategories[0] || state.categories?.[0];
    const editingId = typeof window !== 'undefined' ? Number(window.location.pathname.match(/\/seller\/products\/(\d+)\/edit/)?.[1] || 0) : 0;
    const previewId = typeof window !== 'undefined' ? Number(window.location.pathname.match(/\/seller\/products\/(\d+)\/preview/)?.[1] || 0) : 0;
    const currentProduct = state.sellerProducts.find((item) => item.id === editingId || item.id === previewId);
    const isFormMode = mode === 'create' || Boolean(editingId);
    const isPreviewMode = Boolean(previewId);
    const productTypeOptions = [
        { value: 'physical', label: 'Physical product', hint: 'Inventory, shipping, and delivery tracking', icon: Truck },
        { value: 'digital', label: 'Digital product', hint: 'Files, licenses, accounts, or access handoff', icon: Download },
        { value: 'service', label: 'Service', hint: 'Work delivery, proof, and buyer confirmation', icon: BriefcaseBusiness },
    ];
    const [query, setQuery] = useState('');
    const [statusFilter, setStatusFilter] = useState('all');
    const [selected, setSelected] = useState([]);
    const [tab, setTab] = useState('basics');
    const [errors, setErrors] = useState({});
    const [form, setForm] = useState(() => productFormFromProduct(currentProduct, { ...state, categories: rootCategories.length ? state.categories : [{ id: firstCategory?.id || '', name: firstCategory?.name || 'General Marketplace' }] }));
    useEffect(() => {
        setForm(productFormFromProduct(currentProduct, state));
    }, [currentProduct?.id]);
    const selectedCategoryId = Number(form.category_id || 0);
    const subcategories = (state.categories || []).filter((item) => Number(item.parent_id) === selectedCategoryId);
    const selectedType = productTypeOptions.find((item) => item.value === form.product_type) || productTypeOptions[0];
    const isDigitalLike = form.product_type === 'digital';
    const isInstant = form.product_type === 'digital' && Boolean(form.is_instant_delivery);
    const isService = form.product_type === 'service';
    const filteredProducts = state.sellerProducts.filter((product) => {
        const haystack = `${product.title} ${product.sku} ${product.category} ${product.brand}`.toLowerCase();
        const status = product.status || (asNumber(product.stock) > 0 ? 'published' : 'out_of_stock');
        return haystack.includes(query.toLowerCase()) && (statusFilter === 'all' || status === statusFilter);
    });
    const validate = () => {
        const next = {};
        if (!form.title.trim()) next.title = 'Product title is required.';
        if (!Number.isFinite(Number(form.price)) || Number(form.price) < 0) next.price = 'Regular price must be a valid number.';
        if (Number(form.sale_price || 0) > Number(form.price || 0)) next.sale_price = 'Sale price cannot be higher than regular price.';
        if (!Number.isFinite(Number(form.stock)) || Number(form.stock) < 0) next.stock = 'Stock must be zero or more.';
        setErrors(next);
        return Object.keys(next).length === 0;
    };
    const save = (status = form.status) => {
        if (!validate()) return;
        const categoryId = Number(form.subcategory_id || form.category_id || 0);
        const payload = {
            ...form,
            status,
            category_id: categoryId > 0 ? categoryId : null,
            is_instant_delivery: Boolean(form.is_instant_delivery),
            gallery_images: form.gallery_images.filter(Boolean),
            variants: form.variants.filter((variant) => variant.title || variant.sku),
        };
        saveSellerProduct(payload, editingId || null);
    };
    const setVariant = (index, patch) => setForm({
        ...form,
        variants: form.variants.map((variant, variantIndex) => variantIndex === index ? { ...variant, ...patch } : variant),
    });
    const addVariant = () => setForm({ ...form, variants: [...form.variants, { title: '', sku: '', price: '', stock: '', size: '', color: '', weight: '', attributes: '' }] });
    const toggleSelected = (id) => setSelected((items) => items.includes(id) ? items.filter((item) => item !== id) : [...items, id]);
    const uploadFeaturedImage = async (file) => {
        const media = await uploadSellerMedia(file, 'product_image');
        if (media?.url) {
            setForm((current) => ({
                ...current,
                featured_image: media.url,
                image_url: media.url,
                gallery_images: current.gallery_images.includes(media.url) ? current.gallery_images : [media.url, ...current.gallery_images.filter(Boolean)],
            }));
        }
    };
    const uploadGalleryImages = async (files) => {
        const uploaded = [];
        for (const file of Array.from(files || [])) {
            const media = await uploadSellerMedia(file, 'product_image');
            if (media?.url) uploaded.push(media.url);
        }
        if (uploaded.length) {
            setForm((current) => ({ ...current, gallery_images: [...current.gallery_images.filter(Boolean), ...uploaded] }));
        }
    };
    const uploadProductVideo = async (file) => {
        const media = await uploadSellerMedia(file, 'product_image');
        if (media?.url) setForm((current) => ({ ...current, video_url: media.url }));
    };
    const sectionTabs = [
        ['basics', 'Basics', 'Title, category, product type, descriptions'],
        ['media', 'Media', 'Featured image, gallery, and product video'],
        ['pricing', 'Pricing', 'Regular price, sale logic, tax setup'],
        ['inventory', 'Inventory', 'Stock, warehouse, and variants'],
        ['shipping', 'Shipping', 'Weight, dimensions, warranty, returns'],
        ['seo', 'SEO', 'Search metadata and publish status'],
    ];
    const inputClass = 'h-12 rounded-xl border-slate-200 bg-slate-50 text-[14px] font-semibold shadow-none transition focus:border-indigo-300 focus:bg-white focus:ring-2 focus:ring-indigo-100';
    const selectClass = 'h-12 w-full rounded-xl border border-slate-200 bg-slate-50 px-3 text-[14px] font-semibold text-slate-900 transition focus:border-indigo-300 focus:bg-white focus:outline-none focus:ring-2 focus:ring-indigo-100';
    const textAreaClass = 'min-h-28 rounded-xl border border-slate-200 bg-slate-50 px-3 py-3 text-[14px] font-medium text-slate-900 transition focus:border-indigo-300 focus:bg-white focus:outline-none focus:ring-2 focus:ring-indigo-100';
    const statusOptions = [
        { value: 'draft', label: 'Draft' },
        { value: 'pending_review', label: 'Pending review' },
        { value: 'published', label: 'Published' },
        { value: 'rejected', label: 'Rejected' },
        { value: 'out_of_stock', label: 'Out of stock' },
    ];
    const completionChecks = [
        ['Title', Boolean(form.title.trim())],
        ['Featured image', Boolean(form.featured_image || form.image_url)],
        ['Price', Boolean(String(form.price || '').trim())],
        ['Description', Boolean(form.description.trim())],
        ['Category', Boolean(form.category_id)],
        ['Status', Boolean(form.status)],
    ];
    const completedChecks = completionChecks.filter(([, done]) => done).length;

    if (isPreviewMode) {
        const product = currentProduct;
        if (!product) return <Empty title="Product preview is unavailable" action="/seller/products" label="Back to products" />;
        return (
            <div className="space-y-5">
                <div className="flex flex-wrap items-center justify-between gap-3 rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
                    <div>
                        <p className="text-xs font-extrabold uppercase tracking-wide text-indigo-600">Product preview</p>
                        <h1 className="mt-1 text-xl font-extrabold text-slate-950">{product.title}</h1>
                    </div>
                    <div className="flex gap-2">
                        <Button asChild variant="outline"><Link href={`/seller/products/${product.id}/edit`}><Edit className="size-4" />Edit</Link></Button>
                        <Button asChild><Link href="/seller/products">Catalog</Link></Button>
                    </div>
                </div>
                <ProductDetail productId={product.id} state={{ ...state, products: [product, ...state.products] }} addToCart={() => Promise.resolve()} toggleWishlist={() => Promise.resolve()} />
            </div>
        );
    }

    if (!isFormMode) {
        return (
            <div className="space-y-5">
                <section className="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
                    <div className="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
                        <div>
                            <p className="text-xs font-extrabold uppercase tracking-wide text-indigo-600">Seller catalog</p>
                            <h1 className="mt-1 text-2xl font-extrabold tracking-tight text-slate-950">Products, variants, pricing, SEO, and stock controls</h1>
                        </div>
                        <div className="flex flex-wrap gap-2">
                            <Button variant="outline"><FileUp className="size-4" />Import</Button>
                            <Button variant="outline"><FileDown className="size-4" />Export</Button>
                            <Button asChild className="bg-slate-950 hover:bg-indigo-600"><Link href="/seller/products/create"><Plus className="size-4" />Add product</Link></Button>
                        </div>
                    </div>
                    <div className="mt-5 grid gap-3 md:grid-cols-[1fr_180px_220px]">
                        <div className="relative">
                            <Search className="absolute left-3 top-1/2 size-4 -translate-y-1/2 text-slate-400" />
                            <Input className="pl-9" placeholder="Search title, SKU, brand, category" value={query} onChange={(event) => setQuery(event.target.value)} />
                        </div>
                        <select value={statusFilter} onChange={(event) => setStatusFilter(event.target.value)} className="h-10 rounded-md border-slate-200 bg-white text-sm">
                            {['all', 'draft', 'pending_review', 'published', 'rejected', 'out_of_stock'].map((status) => <option key={status} value={status}>{status.replace('_', ' ')}</option>)}
                        </select>
                        <select value="" onChange={(event) => event.target.value && bulkSellerProducts(selected, event.target.value)} className="h-10 rounded-md border-slate-200 bg-white text-sm" disabled={!selected.length}>
                            <option value="">Bulk actions ({selected.length})</option>
                            <option value="published">Publish</option>
                            <option value="pending_review">Send to review</option>
                            <option value="draft">Move to draft</option>
                            <option value="out_of_stock">Mark out of stock</option>
                            <option value="delete">Delete</option>
                        </select>
                    </div>
                </section>
                <ProductTable products={filteredProducts} selected={selected} toggleSelected={toggleSelected} duplicateSellerProduct={duplicateSellerProduct} deleteSellerProduct={deleteSellerProduct} />
            </div>
        );
    }

    return (
        <section className="grid gap-6 xl:grid-cols-[minmax(0,1fr)_340px]">
                <div className="space-y-6">
                    <section className="rounded-[26px] border border-slate-200 bg-white p-4 shadow-[0_20px_64px_-42px_rgba(15,23,42,0.32)] sm:p-5">
                        <div className="grid gap-3 md:grid-cols-2 xl:grid-cols-3">
                            {sectionTabs.map(([key, label, hint]) => (
                                <button
                                    key={key}
                                    type="button"
                                    onClick={() => setTab(key)}
                                    className={cn(
                                        'rounded-2xl border px-4 py-4 text-left transition',
                                        tab === key
                                            ? 'border-indigo-200 bg-[linear-gradient(135deg,#eef2ff_0%,#f8faff_100%)] shadow-[0_16px_32px_-28px_rgba(79,70,229,0.5)]'
                                            : 'border-slate-200 bg-white hover:border-slate-300 hover:bg-slate-50',
                                    )}
                                >
                                    <p className={cn('text-sm font-black', tab === key ? 'text-indigo-700' : 'text-slate-950')}>{label}</p>
                                    <p className="mt-1 text-xs font-medium leading-5 text-slate-500">{hint}</p>
                                </button>
                            ))}
                        </div>
                    </section>

                    <section className="rounded-[26px] border border-slate-200 bg-white shadow-[0_20px_64px_-42px_rgba(15,23,42,0.32)]">
                        <div className="border-b border-slate-100 px-6 py-5">
                            <h2 className="text-xl font-black tracking-tight text-slate-950">{sectionTabs.find(([key]) => key === tab)?.[1] || 'Product section'}</h2>
                        </div>
                        <div className="grid gap-5 px-6 py-6">
                    {tab === 'basics' ? <>
                        <div className="grid gap-3 xl:grid-cols-2">
                            {productTypeOptions.map(({ value, label, hint, icon: Icon }) => (
                                <button
                                    key={value}
                                    type="button"
                                    onClick={() => setForm({ ...form, product_type: value })}
                                    className={cn(
                                        'rounded-2xl border p-4 text-left transition',
                                        form.product_type === value ? 'border-indigo-200 bg-[linear-gradient(135deg,#eef2ff_0%,#f8faff_100%)] text-indigo-950 shadow-[0_18px_38px_-30px_rgba(79,70,229,0.55)]' : 'border-slate-200 bg-white hover:bg-slate-50',
                                    )}
                                >
                                    <span className="flex items-center gap-3 text-sm font-extrabold"><span className={cn('flex size-10 items-center justify-center rounded-xl', form.product_type === value ? 'bg-white text-indigo-600' : 'bg-slate-100 text-slate-500')}><Icon className="size-4" /></span>{label}</span>
                                    <span className="mt-2 block text-xs font-medium leading-5 text-slate-500">{hint}</span>
                                </button>
                            ))}
                        </div>
                        {form.product_type === 'digital' ? (
                            <label className="flex items-center justify-between rounded-2xl border border-slate-200 bg-slate-50 px-4 py-4">
                                <span>
                                    <span className="block text-sm font-extrabold text-slate-950">Instant delivery?</span>
                                    <span className="mt-1 block text-xs font-medium text-slate-500">Enable automatic digital fulfillment after payment.</span>
                                </span>
                                <input
                                    type="checkbox"
                                    checked={Boolean(form.is_instant_delivery)}
                                    onChange={(e) => setForm({ ...form, is_instant_delivery: e.target.checked })}
                                    className="size-5 rounded border-slate-300 text-indigo-600 focus:ring-indigo-200"
                                />
                            </label>
                        ) : null}
                        <div className="grid gap-5 lg:grid-cols-2">
                            <div className="lg:col-span-2">
                                <label className="mb-2 block text-[11px] font-black uppercase tracking-[0.18em] text-slate-500">Product title</label>
                                <Input className={inputClass} placeholder="Product title" value={form.title} onChange={(e) => setForm({ ...form, title: e.target.value, slug: form.slug || e.target.value.toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/(^-|-$)/g, '') })} />
                            </div>
                            <div>
                                <label className="mb-2 block text-[11px] font-black uppercase tracking-[0.18em] text-slate-500">Slug</label>
                                <Input className={inputClass} placeholder="Slug" value={form.slug} onChange={(e) => setForm({ ...form, slug: e.target.value })} />
                            </div>
                            <div>
                                <label className="mb-2 block text-[11px] font-black uppercase tracking-[0.18em] text-slate-500">SKU</label>
                                <Input className={inputClass} placeholder="SKU" value={form.sku} onChange={(e) => setForm({ ...form, sku: e.target.value })} />
                            </div>
                            <div>
                                <label className="mb-2 block text-[11px] font-black uppercase tracking-[0.18em] text-slate-500">Brand</label>
                                <Input className={inputClass} placeholder="Brand" value={form.brand} onChange={(e) => setForm({ ...form, brand: e.target.value })} />
                            </div>
                            <div>
                                <label className="mb-2 block text-[11px] font-black uppercase tracking-[0.18em] text-slate-500">Condition</label>
                                <Input className={inputClass} placeholder="Condition" value={form.condition} onChange={(e) => setForm({ ...form, condition: e.target.value })} />
                            </div>
                        </div>
                        {errors.title ? <p className="text-sm font-semibold text-rose-600">{errors.title}</p> : null}
                        <div className="grid gap-5 lg:grid-cols-2">
                            <div>
                                <label className="mb-2 block text-[11px] font-black uppercase tracking-[0.18em] text-slate-500">Category</label>
                                <select value={form.category_id} onChange={(e) => {
                                const category = rootCategories.find((item) => String(item.id) === e.target.value);
                                setForm({ ...form, category_id: e.target.value, category: category?.name || '', subcategory_id: '' });
                            }} className={selectClass}>
                                {rootCategories.map((category) => <option key={category.id} value={category.id}>{category.name}</option>)}
                            </select>
                            </div>
                            <div>
                                <label className="mb-2 block text-[11px] font-black uppercase tracking-[0.18em] text-slate-500">Subcategory</label>
                                <select value={form.subcategory_id} onChange={(e) => setForm({ ...form, subcategory_id: e.target.value })} className={selectClass} disabled={!subcategories.length}>
                                <option value="">No subcategory</option>
                                {subcategories.map((category) => <option key={category.id} value={category.id}>{category.name}</option>)}
                            </select>
                            </div>
                        </div>
                        <div className="grid gap-5">
                            <div>
                                <label className="mb-2 block text-[11px] font-black uppercase tracking-[0.18em] text-slate-500">Short description</label>
                                <textarea className="w-full min-h-28 rounded-xl border border-slate-200 bg-slate-50 px-3 py-3 text-[14px] font-medium text-slate-900 transition focus:border-indigo-300 focus:bg-white focus:outline-none focus:ring-2 focus:ring-indigo-100" placeholder="Short description" value={form.short_description} onChange={(e) => setForm({ ...form, short_description: e.target.value })} />
                            </div>
                            <div>
                                <label className="mb-2 block text-[11px] font-black uppercase tracking-[0.18em] text-slate-500">Full description</label>
                                <textarea className="w-full min-h-44 rounded-xl border border-slate-200 bg-slate-50 px-3 py-3 text-[14px] font-medium text-slate-900 transition focus:border-indigo-300 focus:bg-white focus:outline-none focus:ring-2 focus:ring-indigo-100" placeholder="Full description" value={form.description} onChange={(e) => setForm({ ...form, description: e.target.value })} />
                            </div>
                        </div>
                        {isDigitalLike ? <div className="grid gap-5 rounded-2xl border border-slate-200 bg-slate-50/70 p-5 md:grid-cols-2">
                            <div>
                                <label className="mb-2 block text-[11px] font-black uppercase tracking-[0.18em] text-slate-500">Digital product type</label>
                                <Input className={inputClass.replace('bg-slate-50', 'bg-white')} placeholder="Digital product type" value={form.digital_product_kind} onChange={(e) => setForm({ ...form, digital_product_kind: e.target.value })} />
                            </div>
                            <div>
                                <label className="mb-2 block text-[11px] font-black uppercase tracking-[0.18em] text-slate-500">Platform</label>
                                <Input className={inputClass.replace('bg-slate-50', 'bg-white')} placeholder="Platform" value={form.platform} onChange={(e) => setForm({ ...form, platform: e.target.value })} />
                            </div>
                            <div>
                                <label className="mb-2 block text-[11px] font-black uppercase tracking-[0.18em] text-slate-500">Access type</label>
                                <Input className={inputClass.replace('bg-slate-50', 'bg-white')} placeholder="Access type" value={form.access_type} onChange={(e) => setForm({ ...form, access_type: e.target.value })} />
                            </div>
                            <div>
                                <label className="mb-2 block text-[11px] font-black uppercase tracking-[0.18em] text-slate-500">License type</label>
                                <Input className={inputClass.replace('bg-slate-50', 'bg-white')} placeholder="License type" value={form.license_type} onChange={(e) => setForm({ ...form, license_type: e.target.value })} />
                            </div>
                            <div>
                                <label className="mb-2 block text-[11px] font-black uppercase tracking-[0.18em] text-slate-500">{isInstant ? 'Instant access expiry hours' : 'Access validity hours'}</label>
                                <Input className={inputClass.replace('bg-slate-50', 'bg-white')} placeholder={isInstant ? 'Instant access expiry hours' : 'Access validity hours'} value={isInstant ? form.instant_delivery_expiration_hours : form.digital_access_validity_hours} onChange={(e) => setForm(isInstant ? { ...form, instant_delivery_expiration_hours: e.target.value } : { ...form, digital_access_validity_hours: e.target.value })} />
                            </div>
                        </div> : null}
                        {isService ? <div>
                            <label className="mb-2 block text-[11px] font-black uppercase tracking-[0.18em] text-slate-500">Service fulfillment hours</label>
                            <Input className={inputClass} placeholder="Service fulfillment hours" value={form.delivery_fulfillment_hours} onChange={(e) => setForm({ ...form, delivery_fulfillment_hours: e.target.value })} />
                        </div> : null}
                    </> : null}

                    {tab === 'media' ? <>
                        <div className="rounded-2xl border border-slate-200 bg-slate-50/70 p-5">
                            <div className="grid gap-4 md:grid-cols-[180px_1fr]">
                                <ProductMedia src={form.featured_image || form.image_url} alt={form.title} className="aspect-square w-full rounded-2xl object-cover" />
                                <div>
                                    <p className="text-sm font-extrabold text-slate-950">Featured image</p>
                                    <p className="mt-1 text-sm font-medium text-slate-500">Upload a JPG, PNG, WebP, or GIF. This image becomes the product thumbnail and primary preview.</p>
                                    <label className="mt-4 inline-flex h-11 cursor-pointer items-center gap-2 rounded-xl bg-slate-950 px-4 text-sm font-bold text-white hover:bg-indigo-600">
                                        <Upload className="size-4" />Upload featured image
                                        <input type="file" accept="image/*" className="sr-only" onChange={(event) => uploadFeaturedImage(event.target.files?.[0])} />
                                    </label>
                                    {(form.featured_image || form.image_url) ? <Button type="button" variant="outline" className="ml-2 mt-4 rounded-xl" onClick={() => setForm({ ...form, featured_image: '', image_url: '' })}><Trash2 className="size-4" />Remove</Button> : null}
                                </div>
                            </div>
                        </div>
                        <div className="rounded-2xl border border-slate-200 bg-white p-5">
                            <div className="flex flex-wrap items-center justify-between gap-3">
                                <div>
                                    <p className="text-sm font-extrabold text-slate-950">Gallery images</p>
                                    <p className="mt-1 text-sm font-medium text-slate-500">Upload multiple product photos and choose the featured image from the gallery.</p>
                                </div>
                                <label className="inline-flex h-11 cursor-pointer items-center gap-2 rounded-xl border border-slate-200 px-4 text-sm font-bold hover:bg-slate-50">
                                    <Upload className="size-4" />Upload gallery
                                    <input type="file" accept="image/*" multiple className="sr-only" onChange={(event) => uploadGalleryImages(event.target.files)} />
                                </label>
                            </div>
                            <div className="mt-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                                {form.gallery_images.filter(Boolean).map((image, index) => (
                                    <div key={`${image}-${index}`} className="rounded-2xl border border-slate-200 p-2.5">
                                        <ProductMedia src={image} alt={`Gallery ${index + 1}`} className="aspect-square w-full rounded-xl object-cover" />
                                        <div className="mt-2 flex gap-1">
                                            <Button type="button" size="sm" variant={form.featured_image === image ? 'default' : 'outline'} className="h-8 flex-1 rounded-xl text-xs" onClick={() => setForm({ ...form, featured_image: image, image_url: image })}>Feature</Button>
                                            <Button type="button" size="icon" variant="outline" className="size-8 rounded-xl" onClick={() => setForm({ ...form, gallery_images: form.gallery_images.filter((item) => item !== image) })}><Trash2 className="size-3.5" /></Button>
                                        </div>
                                    </div>
                                ))}
                                {!form.gallery_images.filter(Boolean).length ? <div className="rounded-2xl border border-dashed border-slate-300 p-6 text-center text-sm font-semibold text-slate-500 sm:col-span-2 lg:col-span-4">No gallery images uploaded yet.</div> : null}
                            </div>
                        </div>
                        <div className="rounded-2xl border border-slate-200 bg-slate-50/70 p-5">
                            <p className="text-sm font-extrabold text-slate-950">Product video</p>
                            <p className="mt-1 text-sm font-medium text-slate-500">Upload MP4, MOV, or WebM for product demonstration media.</p>
                            <label className="mt-4 inline-flex h-11 cursor-pointer items-center gap-2 rounded-xl border border-slate-200 bg-white px-4 text-sm font-bold hover:bg-slate-50">
                                <Upload className="size-4" />Upload video
                                <input type="file" accept="video/mp4,video/quicktime,video/webm" className="sr-only" onChange={(event) => uploadProductVideo(event.target.files?.[0])} />
                            </label>
                            {form.video_url ? <p className="mt-3 break-all rounded-xl bg-white p-3 text-xs font-semibold text-slate-500">{form.video_url}</p> : null}
                        </div>
                    </> : null}

                    {tab === 'pricing' ? <>
                        <div className="grid gap-5 md:grid-cols-2">
                            <div>
                                <label className="mb-2 block text-[11px] font-black uppercase tracking-[0.18em] text-slate-500">Regular price</label>
                                <Input className={inputClass} placeholder="Regular price" value={form.price} onChange={(e) => setForm({ ...form, price: e.target.value })} />
                            </div>
                            <div>
                                <label className="mb-2 block text-[11px] font-black uppercase tracking-[0.18em] text-slate-500">Sale price</label>
                                <Input className={inputClass} placeholder="Sale price" value={form.sale_price} onChange={(e) => setForm({ ...form, sale_price: e.target.value })} />
                            </div>
                            <div>
                                <label className="mb-2 block text-[11px] font-black uppercase tracking-[0.18em] text-slate-500">Discount type</label>
                                <select value={form.discount_type} onChange={(e) => setForm({ ...form, discount_type: e.target.value })} className={selectClass}><option value="percentage">Percentage</option><option value="fixed">Fixed</option></select>
                            </div>
                            <div>
                                <label className="mb-2 block text-[11px] font-black uppercase tracking-[0.18em] text-slate-500">Discount value</label>
                                <Input className={inputClass} placeholder="Discount value" value={form.discount_value} onChange={(e) => setForm({ ...form, discount_value: e.target.value })} />
                            </div>
                        </div>
                        {errors.price ? <p className="text-sm font-semibold text-rose-600">{errors.price}</p> : null}
                        {errors.sale_price ? <p className="text-sm font-semibold text-rose-600">{errors.sale_price}</p> : null}
                        <div>
                            <label className="mb-2 block text-[11px] font-black uppercase tracking-[0.18em] text-slate-500">Tax class</label>
                            <Input className={inputClass} placeholder="Tax class" value={form.tax_class} onChange={(e) => setForm({ ...form, tax_class: e.target.value })} />
                        </div>
                    </> : null}

                    {tab === 'inventory' ? <>
                        <div className="grid gap-5 md:grid-cols-3">
                            <div>
                                <label className="mb-2 block text-[11px] font-black uppercase tracking-[0.18em] text-slate-500">Stock quantity</label>
                                <Input className={inputClass} placeholder="Stock quantity" value={form.stock} onChange={(e) => setForm({ ...form, stock: e.target.value })} />
                            </div>
                            <div>
                                <label className="mb-2 block text-[11px] font-black uppercase tracking-[0.18em] text-slate-500">Low stock alert</label>
                                <Input className={inputClass} placeholder="Low stock alert" value={form.low_stock_alert} onChange={(e) => setForm({ ...form, low_stock_alert: e.target.value })} />
                            </div>
                            <div>
                                <label className="mb-2 block text-[11px] font-black uppercase tracking-[0.18em] text-slate-500">Warehouse</label>
                                <select value={form.warehouse_id} onChange={(e) => setForm({ ...form, warehouse_id: e.target.value })} className={selectClass}>
                                <option value="">No warehouse</option>
                                {(state.sellerOps?.warehouses || []).map((warehouse) => <option key={warehouse.id} value={warehouse.id}>{warehouse.name}</option>)}
                            </select>
                            </div>
                        </div>
                        {errors.stock ? <p className="text-sm font-semibold text-rose-600">{errors.stock}</p> : null}
                        <div className="rounded-2xl border border-slate-200">
                            <div className="flex items-center justify-between border-b border-slate-200 p-4">
                                <p className="font-extrabold text-slate-950">Variants</p>
                                <Button type="button" size="sm" variant="outline" className="rounded-xl" onClick={addVariant}><Plus className="size-4" />Variant</Button>
                            </div>
                            <div className="grid gap-3 p-4">
                                {form.variants.map((variant, index) => (
                                    <div key={index} className="grid gap-3 rounded-2xl bg-slate-50 p-4 md:grid-cols-4">
                                        <div className="md:col-span-4 flex items-center justify-between">
                                            <p className="text-xs font-black uppercase tracking-[0.16em] text-slate-400">Variant {index + 1}</p>
                                            <Button
                                                type="button"
                                                variant="outline"
                                                size="sm"
                                                className="h-8 rounded-xl border-slate-200 px-3 text-xs font-bold text-slate-600 hover:text-rose-600"
                                                onClick={() => setForm((current) => ({
                                                    ...current,
                                                    variants: current.variants.filter((_, variantIndex) => variantIndex !== index),
                                                }))}
                                            >
                                                <Trash2 className="size-3.5" />
                                                Remove
                                            </Button>
                                        </div>
                                        <Input className={inputClass.replace('bg-slate-50', 'bg-white')} placeholder="Title" value={variant.title} onChange={(e) => setVariant(index, { title: e.target.value })} />
                                        <Input className={inputClass.replace('bg-slate-50', 'bg-white')} placeholder="SKU" value={variant.sku} onChange={(e) => setVariant(index, { sku: e.target.value })} />
                                        <Input className={inputClass.replace('bg-slate-50', 'bg-white')} placeholder="Price" value={variant.price} onChange={(e) => setVariant(index, { price: e.target.value })} />
                                        <Input className={inputClass.replace('bg-slate-50', 'bg-white')} placeholder="Stock" value={variant.stock} onChange={(e) => setVariant(index, { stock: e.target.value })} />
                                        <Input className={inputClass.replace('bg-slate-50', 'bg-white')} placeholder="Size" value={variant.size} onChange={(e) => setVariant(index, { size: e.target.value })} />
                                        <Input className={inputClass.replace('bg-slate-50', 'bg-white')} placeholder="Color" value={variant.color} onChange={(e) => setVariant(index, { color: e.target.value })} />
                                        <Input className={inputClass.replace('bg-slate-50', 'bg-white')} placeholder="Weight" value={variant.weight} onChange={(e) => setVariant(index, { weight: e.target.value })} />
                                        <Input className={inputClass.replace('bg-slate-50', 'bg-white')} placeholder="Custom attributes" value={variant.attributes} onChange={(e) => setVariant(index, { attributes: e.target.value })} />
                                    </div>
                                ))}
                            </div>
                        </div>
                    </> : null}

                    {tab === 'shipping' ? <>
                        <div className="grid gap-5 md:grid-cols-2">
                            <div>
                                <label className="mb-2 block text-[11px] font-black uppercase tracking-[0.18em] text-slate-500">Shipping weight</label>
                                <Input className={inputClass} placeholder="Shipping weight" value={form.shipping_weight} onChange={(e) => setForm({ ...form, shipping_weight: e.target.value })} />
                            </div>
                            <div>
                                <label className="mb-2 block text-[11px] font-black uppercase tracking-[0.18em] text-slate-500">Dimensions</label>
                                <Input className={inputClass} placeholder="Dimensions (L x W x H)" value={form.shipping_dimensions} onChange={(e) => setForm({ ...form, shipping_dimensions: e.target.value })} />
                            </div>
                        </div>
                        <div>
                            <label className="mb-2 block text-[11px] font-black uppercase tracking-[0.18em] text-slate-500">Warranty information</label>
                            <textarea className="w-full min-h-28 rounded-xl border border-slate-200 bg-slate-50 px-3 py-3 text-[14px] font-medium text-slate-900 transition focus:border-indigo-300 focus:bg-white focus:outline-none focus:ring-2 focus:ring-indigo-100" placeholder="Warranty information" value={form.warranty_information} onChange={(e) => setForm({ ...form, warranty_information: e.target.value })} />
                        </div>
                        <div>
                            <label className="mb-2 block text-[11px] font-black uppercase tracking-[0.18em] text-slate-500">Return policy</label>
                            <textarea className="w-full min-h-28 rounded-xl border border-slate-200 bg-slate-50 px-3 py-3 text-[14px] font-medium text-slate-900 transition focus:border-indigo-300 focus:bg-white focus:outline-none focus:ring-2 focus:ring-indigo-100" placeholder="Return policy" value={form.return_policy} onChange={(e) => setForm({ ...form, return_policy: e.target.value })} />
                        </div>
                        <div>
                            <label className="mb-2 block text-[11px] font-black uppercase tracking-[0.18em] text-slate-500">{isInstant ? 'Instant delivery instructions' : 'Delivery note / buyer instructions'}</label>
                            <textarea className="w-full min-h-28 rounded-xl border border-slate-200 bg-slate-50 px-3 py-3 text-[14px] font-medium text-slate-900 transition focus:border-indigo-300 focus:bg-white focus:outline-none focus:ring-2 focus:ring-indigo-100" placeholder={isInstant ? 'Instant delivery instructions' : 'Delivery note / buyer instructions'} value={form.delivery_note} onChange={(e) => setForm({ ...form, delivery_note: e.target.value })} />
                        </div>
                    </> : null}

                    {tab === 'seo' ? <>
                        <div>
                            <label className="mb-2 block text-[11px] font-black uppercase tracking-[0.18em] text-slate-500">SEO title</label>
                            <Input className={inputClass} placeholder="SEO title" value={form.seo_title} onChange={(e) => setForm({ ...form, seo_title: e.target.value })} />
                        </div>
                        <div>
                            <label className="mb-2 block text-[11px] font-black uppercase tracking-[0.18em] text-slate-500">SEO description</label>
                            <textarea className="w-full min-h-28 rounded-xl border border-slate-200 bg-slate-50 px-3 py-3 text-[14px] font-medium text-slate-900 transition focus:border-indigo-300 focus:bg-white focus:outline-none focus:ring-2 focus:ring-indigo-100" placeholder="SEO description" value={form.seo_description} onChange={(e) => setForm({ ...form, seo_description: e.target.value })} />
                        </div>
                        <div>
                            <label className="mb-2 block text-[11px] font-black uppercase tracking-[0.18em] text-slate-500">Product status</label>
                            <select value={form.status} onChange={(e) => setForm({ ...form, status: e.target.value })} className={selectClass}>
                            <option value="draft">Draft</option>
                            <option value="pending_review">Pending review</option>
                            <option value="published">Published</option>
                            <option value="rejected">Rejected</option>
                            <option value="out_of_stock">Out of stock</option>
                        </select>
                        </div>
                    </> : null}

                        </div>
                    </section>
                </div>
                <aside className="space-y-5 xl:sticky xl:top-24 xl:self-start">
                    <section className="overflow-hidden rounded-[26px] border border-slate-200 bg-white shadow-[0_20px_64px_-42px_rgba(15,23,42,0.32)]">
                        <div className="p-5">
                            <ProductMedia src={form.featured_image || form.image_url} alt={form.title} className="aspect-[4/3] w-full rounded-2xl object-cover" />
                            <h3 className="mt-4 text-lg font-extrabold text-slate-950">{form.title || 'Product title'}</h3>
                            <p className="mt-1 text-sm font-medium text-slate-500">{selectedType.label}{isInstant ? ' · Instant delivery' : ''} · {form.brand || 'No brand yet'}</p>
                            <p className="mt-3 text-[28px] font-black tracking-tight text-rose-600">{money(form.sale_price || form.price || 0)}</p>
                            <div className="mt-4 grid grid-cols-2 gap-2 text-xs font-bold">
                                <span className="rounded-xl bg-slate-50 px-3 py-3">Stock {form.stock || 0}</span>
                                <span className="rounded-xl bg-slate-50 px-3 py-3">{(statusOptions.find((item) => item.value === form.status)?.label || 'Draft')}</span>
                                <span className="rounded-xl bg-slate-50 px-3 py-3">Variants {form.variants.filter((item) => item.title || item.sku).length}</span>
                                <span className="rounded-xl bg-slate-50 px-3 py-3">Alert {form.low_stock_alert || 0}</span>
                            </div>
                        </div>
                    </section>

                    <section className="overflow-hidden rounded-[26px] border border-slate-200 bg-white shadow-[0_20px_64px_-42px_rgba(15,23,42,0.32)]">
                        <div className="grid gap-3 p-5">
                            <div className="grid grid-cols-2 gap-2 text-xs font-bold">
                                <span className="rounded-xl bg-slate-50 px-3 py-3">{statusOptions.find((item) => item.value === form.status)?.label || 'Draft'}</span>
                                <span className="rounded-xl bg-slate-50 px-3 py-3">{completedChecks}/{completionChecks.length} fields</span>
                            </div>
                            <Button type="button" variant="outline" className="h-12 rounded-xl font-bold" onClick={() => save('draft')}>Save draft</Button>
                            <Button type="button" className="h-12 rounded-xl bg-slate-950 font-bold hover:bg-indigo-600" onClick={() => save(form.status)}>{editingId ? 'Update product' : 'Create product'}</Button>
                            <Button type="button" className="h-12 rounded-xl bg-indigo-600 font-bold hover:bg-indigo-700" onClick={() => save('pending_review')}>Submit for review</Button>
                        </div>
                    </section>
                </aside>
        </section>
    );
}

function ProductTable({ products, selected = [], toggleSelected = () => {}, duplicateSellerProduct = () => {}, deleteSellerProduct = () => {} }) {
    const [deleteCandidate, setDeleteCandidate] = useState(null);
    const [isDeleting, setIsDeleting] = useState(false);

    const confirmDelete = async () => {
        if (!deleteCandidate?.id || isDeleting) return;
        setIsDeleting(true);
        try {
            await deleteSellerProduct(deleteCandidate.id);
            setDeleteCandidate(null);
        } finally {
            setIsDeleting(false);
        }
    };

    return (
        <>
            <div className="overflow-x-auto rounded-lg border border-slate-200">
                <table className="w-full text-left text-sm">
                    <thead className="border-b bg-slate-50 text-xs uppercase tracking-wide text-slate-500"><tr><th className="px-3 py-3">Select</th><th className="px-3 py-3">Product</th><th className="px-3 py-3">Type</th><th className="px-3 py-3">Stock</th><th className="px-3 py-3">Price</th><th className="px-3 py-3">Status</th><th className="px-3 py-3">Action</th></tr></thead>
                    <tbody className="divide-y divide-slate-100 bg-white">
                        {products.map((product) => {
                            const stock = asNumber(product.stock);
                            return (
                                <tr key={product.id} className="transition hover:bg-cyan-50/40">
                                    <td className="px-3 py-3"><input type="checkbox" checked={selected.includes(product.id)} onChange={() => toggleSelected(product.id)} className="rounded border-slate-300" /></td>
                                    <td className="px-3 py-3">
                                        <div className="flex items-center gap-3">
                                            <ProductMedia src={product.image} alt={product.title} className="size-12 rounded-md object-cover" />
                                            <div className="min-w-0">
                                                <p className="line-clamp-1 font-extrabold text-slate-950">{product.title}</p>
                                                <p className="mt-1 text-xs font-semibold text-slate-500">{product.sku || `SKU-${product.id}`} · {product.category || 'Marketplace'}{product.subcategory ? ` / ${product.subcategory}` : ''}</p>
                                            </div>
                                        </div>
                                    </td>
                                    <td className="px-3 py-3"><Badge variant="secondary">{product.productTypeLabel || product.type}</Badge></td>
                                    <td className="px-3 py-3 font-bold">{stock}</td>
                                    <td className="px-3 py-3 font-extrabold text-rose-600">{money(product.price)}</td>
                                    <td className="px-3 py-3"><Badge variant={product.status === 'published' && stock > 0 ? 'success' : 'warning'}>{product.status || (stock > 0 ? 'active' : 'out of stock')}</Badge></td>
                                    <td className="px-3 py-3">
                                        <div className="flex gap-1">
                                            <Button asChild variant="outline" size="icon" className="size-8"><Link href={`/seller/products/${product.id}/preview`}><Eye className="size-4" /></Link></Button>
                                            <Button asChild variant="outline" size="icon" className="size-8"><Link href={`/seller/products/${product.id}/edit`}><Edit className="size-4" /></Link></Button>
                                            <Button type="button" variant="outline" size="icon" className="size-8" onClick={() => duplicateSellerProduct(product.id)}><Copy className="size-4" /></Button>
                                            <Button type="button" variant="outline" size="icon" className="size-8 text-rose-600 hover:text-rose-700" onClick={() => setDeleteCandidate(product)} aria-label={`Delete ${product.title}`}>
                                                <Trash2 className="size-4" />
                                            </Button>
                                        </div>
                                    </td>
                                </tr>
                            );
                        })}
                        {!products.length ? <tr><td colSpan="7" className="p-8 text-center text-sm font-semibold text-slate-500">No products match your current filters.</td></tr> : null}
                    </tbody>
                </table>
            </div>
            {deleteCandidate ? (
                <div className="fixed inset-0 z-[90] flex items-center justify-center bg-slate-950/55 p-4 backdrop-blur-sm">
                    <div className="w-full max-w-md rounded-[28px] border border-slate-200 bg-white p-6 shadow-[0_36px_90px_-48px_rgba(15,23,42,0.6)]">
                        <div className="flex size-14 items-center justify-center rounded-2xl bg-rose-50 text-rose-600">
                            <Trash2 className="size-6" />
                        </div>
                        <p className="mt-5 text-xs font-black uppercase tracking-[0.18em] text-rose-500">Delete product</p>
                        <h3 className="mt-2 text-2xl font-extrabold tracking-tight text-slate-950">Remove this listing from your catalog?</h3>
                        <p className="mt-3 text-sm font-medium leading-6 text-slate-500">
                            <span className="font-bold text-slate-700">{deleteCandidate.title}</span> will be removed from your seller catalog and hidden from shoppers. This action should only be used when you no longer want to manage this listing.
                        </p>
                        <div className="mt-6 rounded-2xl border border-rose-100 bg-rose-50/80 p-4 text-sm font-medium leading-6 text-rose-700">
                            Please confirm before deleting. If you only want to pause sales, consider changing the product status instead of removing it.
                        </div>
                        <div className="mt-6 flex gap-3">
                            <Button type="button" onClick={confirmDelete} className="flex-1 rounded-xl bg-rose-600 font-bold hover:bg-rose-700" disabled={isDeleting}>
                                {isDeleting ? 'Deleting...' : 'Delete product'}
                            </Button>
                            <Button type="button" variant="outline" onClick={() => setDeleteCandidate(null)} className="flex-1 rounded-xl font-bold" disabled={isDeleting}>
                                Cancel
                            </Button>
                        </div>
                    </div>
                </div>
            ) : null}
        </>
    );
}

function SellerInventory({ state, adjustStock }) {
    const [visibleCount, setVisibleCount] = useState(6);
    const [editorId, setEditorId] = useState(null);
    const [stockDraft, setStockDraft] = useState('');
    const perPage = 6;
    const visibleProducts = useMemo(() => state.sellerProducts.slice(0, visibleCount), [visibleCount, state.sellerProducts]);

    const openEditor = (product) => {
        setEditorId(product.id);
        setStockDraft(String(asNumber(product.stock)));
    };

    const saveStock = async (product) => {
        const target = Math.max(0, asNumber(stockDraft, asNumber(product.stock)));
        const current = asNumber(product.stock);
        const delta = target - current;
        if (delta !== 0) {
            await adjustStock(product.id, delta);
        }
        setEditorId(null);
        setStockDraft('');
    };

    return (
        <div className="space-y-5">
            <Panel title="Inventory management" icon={Boxes}>
                <div className="grid gap-4 xl:grid-cols-3">
                    {visibleProducts.map((product) => {
                        const stock = asNumber(product.stock);
                        return (
                            <div key={product.id} className="rounded-2xl border border-slate-200 bg-white p-4 shadow-[0_18px_42px_-34px_rgba(15,23,42,0.28)]">
                                <div className="flex items-start gap-3">
                                    <ProductMedia src={product.image} alt={product.title} className="size-16 rounded-md object-cover" />
                                    <div className="min-w-0 flex-1">
                                        <p className="line-clamp-1 font-extrabold text-slate-950">{product.title}</p>
                                        <p className="mt-1 text-sm text-slate-500">{product.productTypeLabel || product.type} · {product.category}</p>
                                        <div className="mt-3 flex items-center justify-between gap-3">
                                            <Badge variant={stock > 0 ? (stock < 10 ? 'secondary' : 'success') : 'warning'}>{stock > 0 ? `${stock} units` : 'Out of stock'}</Badge>
                                            <Button type="button" variant="outline" size="icon" onClick={() => openEditor(product)} className="rounded-xl" aria-label={`Update ${product.title} stock`}>
                                                <Edit className="size-4" />
                                            </Button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        );
                    })}
                    {!state.sellerProducts.length ? (
                        <div className="rounded-lg bg-slate-50 p-6 text-center xl:col-span-3">
                            <p className="font-semibold text-slate-600">No seller products yet.</p>
                            <Button asChild className="mt-4"><Link href="/seller/products">Create listing</Link></Button>
                        </div>
                    ) : null}
                </div>
                {state.sellerProducts.length > visibleCount ? <LoadMoreButton onClick={() => setVisibleCount((current) => current + perPage)} className="mt-4" /> : null}
            </Panel>
            {editorId !== null ? (
                <div className="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/45 p-4 backdrop-blur-sm">
                    <div className="w-full max-w-md rounded-[28px] border border-slate-200 bg-white p-6 shadow-[0_32px_90px_-36px_rgba(15,23,42,0.45)]">
                        {(() => {
                            const product = state.sellerProducts.find((item) => item.id === editorId);
                            const currentStock = asNumber(product?.stock);
                            if (!product) return null;
                            return (
                                <>
                                    <div className="flex items-start justify-between gap-4">
                                        <div>
                                            <p className="text-xs font-black uppercase tracking-[0.18em] text-indigo-500">Update stock</p>
                                            <h3 className="mt-2 text-xl font-extrabold tracking-tight text-slate-950">{product.title}</h3>
                                            <p className="mt-1 text-sm font-semibold text-slate-500">Current stock: {currentStock}</p>
                                        </div>
                                        <Button type="button" variant="outline" size="icon" onClick={() => setEditorId(null)} className="rounded-xl">
                                            <X className="size-4" />
                                        </Button>
                                    </div>
                                    <div className="mt-5 rounded-2xl border border-indigo-100 bg-indigo-50/70 p-4">
                                        <p className="text-xs font-black uppercase tracking-[0.18em] text-indigo-500">Target stock</p>
                                        <Input type="number" min="0" value={stockDraft} onChange={(e) => setStockDraft(e.target.value)} className="mt-3 h-12 rounded-xl border-indigo-200 bg-white font-bold" />
                                    </div>
                                    <div className="mt-5 flex gap-3">
                                        <Button type="button" onClick={() => saveStock(product)} className="flex-1 rounded-xl bg-slate-950 font-bold">Save stock</Button>
                                        <Button type="button" variant="outline" onClick={() => setEditorId(null)} className="flex-1 rounded-xl font-bold">Cancel</Button>
                                    </div>
                                </>
                            );
                        })()}
                    </div>
                </div>
            ) : null}
        </div>
    );
}

function SellerOrders({ state }) {
    const orders = state.sellerOps?.ordersDetailed || [];

    return (
        <div className="space-y-5">
            <section className="flex items-end justify-between gap-4">
                <div>
                    <p className="text-sm font-black uppercase tracking-[0.2em] text-slate-400">Seller orders</p>
                    <h1 className="mt-2 text-3xl font-black tracking-tight text-slate-950">Digital delivery and escrow queue</h1>
                </div>
                <Badge variant="secondary">{orders.length} orders</Badge>
            </section>
            <div className="grid gap-4 xl:grid-cols-2">
                {orders.length ? orders.map((order) => (
                    <article key={order.id} className="rounded-[24px] border border-slate-200 bg-white p-5 shadow-[0_18px_42px_-34px_rgba(15,23,42,0.28)]">
                        <div className="flex gap-4">
                            <ProductMedia src={order.image} alt={order.product} className="size-20 rounded-2xl object-cover ring-1 ring-slate-200" />
                            <div className="min-w-0 flex-1">
                                <div className="flex flex-wrap items-center gap-2">
                                    <Link href={`/seller/order-details?order=${order.id}`} className="text-lg font-black tracking-tight text-slate-950 hover:text-indigo-600">{order.code}</Link>
                                    <Badge variant="secondary">{humanizeOrderState(order.status)}</Badge>
                                </div>
                                <p className="mt-2 line-clamp-2 text-base font-semibold text-slate-800">{order.product}</p>
                                <p className="mt-2 text-sm font-semibold text-slate-500">Buyer: {order.buyer}</p>
                                <div className="mt-4 flex flex-wrap gap-4 text-sm font-bold text-slate-500">
                                    <span>Escrow: {humanizeOrderState(order.escrowState || 'held')}</span>
                                    <span>Delivery: {humanizeOrderState(order.deliveryStatus || 'pending')}</span>
                                    <span>Total: {money(order.amount)}</span>
                                </div>
                            </div>
                        </div>
                    </article>
                )) : <p className="rounded-2xl border border-slate-200 bg-slate-50 p-6 text-sm font-semibold text-slate-500">No seller escrow orders are available yet.</p>}
            </div>
        </div>
    );
}

function SellerWallet({ state, requestTopUp, requestPayout, uploadSellerMedia, initialTab = 'wallet', pendingAction = '' }) {
    const wallet = state.sellerOps?.wallet || {};
    const [tab, setTab] = useState(initialTab);
    const initialTopUpState = { amount: '', payment_method: 'bank_transfer', payment_reference: '', payment_proof_url: '' };
    const [topUp, setTopUp] = useState(initialTopUpState);
    const [withdrawAmount, setWithdrawAmount] = useState(String(wallet.minimumWithdraw || 500));
    const submitTopUp = async () => {
        await requestTopUp(topUp);
        setTopUp(initialTopUpState);
    };
    const submitWithdraw = () => requestPayout(withdrawAmount);
    const uploadPaymentProof = async (file) => {
        const media = await uploadSellerMedia(file, 'payment_proof');
        if (media?.url) setTopUp((current) => ({ ...current, payment_proof_url: media.url }));
    };
    const isSubmittingTopUp = pendingAction === 'seller:topup';
    const isUploadingProof = pendingAction === 'upload:payment_proof';
    const tabs = [
        ['wallet', 'Wallet'],
        ['topup', 'Top-up request'],
        ['withdraw', 'Withdraw request'],
        ['transactions', 'Transactions'],
    ];
    const selectAvailableForWithdraw = () => {
        setWithdrawAmount(String(Math.max(0, asNumber(wallet.availableBalance || 0))));
        setTab('withdraw');
    };

    return (
        <div className="min-h-[calc(100vh-7rem)] bg-slate-50 px-1 pb-10 text-slate-950">
            <section className="mb-5 grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                <Stat label="Current balance" value={money(wallet.currentBalance || 0)} hint="Ledger balance" icon={WalletCards} />
                <button type="button" onClick={selectAvailableForWithdraw} className="text-left">
                    <Stat label="Available" value={money(wallet.availableBalance || 0)} hint="Ready to withdraw" icon={CreditCard} />
                </button>
                <Stat label="Pending" value={money(wallet.pendingBalance || 0)} hint="Withdrawals and holds" icon={Clock} />
                <Stat label="Commission" value={money(wallet.commissionDeducted || 0)} hint="Marketplace fees" icon={ReceiptText} />
            </section>

            <section className="inline-flex max-w-full rounded-xl border border-slate-200 bg-white p-2 shadow-sm">
                <div className="grid grid-cols-2 gap-2 sm:flex">
                    {tabs.map(([key, label]) => (
                        <button
                            key={key}
                            type="button"
                            onClick={() => setTab(key)}
                            className={cn(
                                'h-10 rounded-lg px-5 text-sm font-extrabold text-slate-600 transition hover:bg-slate-50 sm:min-w-32',
                                tab === key && 'bg-slate-950 text-white shadow-[0_10px_22px_-16px_rgba(15,23,42,0.9)] hover:bg-slate-950',
                            )}
                        >
                            {label}
                        </button>
                    ))}
                </div>
            </section>

            {tab === 'wallet' ? (
                <section className="mt-8 grid gap-6 lg:grid-cols-[436px_minmax(0,1fr)]">
                    <PayoutCard title="Seller wallet" icon={WalletCards} iconTone="indigo">
                        <div className="mt-6 space-y-4">
                            <div className="rounded-2xl bg-[linear-gradient(135deg,#4f46e5_0%,#5b4cf0_48%,#312e81_100%)] p-5 text-white shadow-[0_24px_60px_-34px_rgba(91,76,240,0.62)]">
                                <p className="text-xs font-black uppercase tracking-[0.22em] text-cyan-100/80">Seller balance</p>
                                <p className="mt-3 text-4xl font-black tracking-tight">{money(wallet.currentBalance || 0)}</p>
                                <div className="mt-5 grid grid-cols-2 gap-3">
                                    <div className="rounded-xl border border-white/15 bg-white/10 p-3 backdrop-blur">
                                        <p className="text-[11px] font-black uppercase tracking-[0.18em] text-cyan-100/75">Available</p>
                                        <p className="mt-1 text-lg font-extrabold">{money(wallet.availableBalance || 0)}</p>
                                    </div>
                                    <div className="rounded-xl border border-white/15 bg-white/10 p-3 backdrop-blur">
                                        <p className="text-[11px] font-black uppercase tracking-[0.18em] text-cyan-100/75">Pending</p>
                                        <p className="mt-1 text-lg font-extrabold">{money(wallet.pendingBalance || 0)}</p>
                                    </div>
                                </div>
                            </div>

                            <div className="grid gap-3 sm:grid-cols-2">
                                <WalletMetric label="Total sales" value={money(wallet.totalSales || 0)} />
                                <WalletMetric label="Total withdrawals" value={money(wallet.totalWithdrawals || 0)} />
                                <WalletMetric label="Commission paid" value={money(wallet.commissionDeducted || 0)} />
                                <WalletMetric label="Minimum withdraw" value={money(wallet.minimumWithdraw || 500)} />
                            </div>
                        </div>
                    </PayoutCard>
                    <PayoutCard title="Recent withdraws" icon={ReceiptText} iconTone="emerald">
                        <WithdrawHistoryCards items={state.payoutRequests} empty="No withdraw requests yet." compact />
                    </PayoutCard>
                </section>
            ) : null}

            {tab === 'topup' ? (
                <section className="mt-8 grid gap-6 lg:grid-cols-[344px_minmax(0,1fr)]">
                    <PayoutCard title="Top-up request" icon={CreditCard} iconTone="indigo">
                        <div className="mt-6 grid gap-4">
                            <Input type="number" min="0" placeholder="Amount" value={topUp.amount} onChange={(e) => setTopUp({ ...topUp, amount: e.target.value })} className="h-12 rounded-xl border-slate-200 bg-slate-50 font-bold" />
                            <KycSearchableSelect label="Payment method" value={topUp.payment_method} options={[
                                { value: 'bank_transfer', label: 'Bank transfer' },
                                { value: 'bkash', label: 'bKash' },
                                { value: 'nagad', label: 'Nagad' },
                                { value: 'card', label: 'Card' },
                            ]} onChange={(value) => setTopUp({ ...topUp, payment_method: value })} />
                            <Input placeholder="Payment reference" value={topUp.payment_reference} onChange={(e) => setTopUp({ ...topUp, payment_reference: e.target.value })} className="h-12 rounded-xl border-slate-200 bg-slate-50 font-bold" />
                            <div className="rounded-xl border border-dashed border-slate-200 bg-slate-50 p-5">
                                <p className="text-sm font-extrabold text-slate-950">Payment proof</p>
                                <label className="mt-3 inline-flex h-10 cursor-pointer items-center gap-2 rounded-lg bg-slate-950 px-4 text-sm font-extrabold text-white shadow-sm hover:bg-indigo-600">
                                    <Upload className="size-4" />{isUploadingProof ? 'Uploading proof...' : 'Upload proof'}
                                    <input type="file" accept="image/*,.pdf" className="sr-only" onChange={(event) => uploadPaymentProof(event.target.files?.[0])} />
                                </label>
                                {topUp.payment_proof_url ? <p className="mt-3 break-all rounded-lg bg-white p-2 text-xs font-semibold text-slate-500">{topUp.payment_proof_url}</p> : null}
                            </div>
                            <Button type="button" disabled={isSubmittingTopUp || isUploadingProof} onClick={submitTopUp} className="h-12 rounded-xl bg-slate-950 font-extrabold shadow-[0_14px_28px_-20px_rgba(15,23,42,0.9)]">
                                {isSubmittingTopUp ? 'Submitting...' : 'Submit top-up'}
                            </Button>
                        </div>
                    </PayoutCard>
                    <PayoutCard title="Top-up history" icon={ReceiptText} iconTone="emerald">
                        <TopUpHistoryCards items={wallet.topUps || []} empty="No top-up requests yet." />
                    </PayoutCard>
                </section>
            ) : null}

            {tab === 'withdraw' ? (
                <section className="mt-8 grid gap-6 lg:grid-cols-[344px_minmax(0,1fr)]">
                    <PayoutCard title="Withdraw request" icon={WalletCards} iconTone="indigo">
                        <div className="mt-6 grid gap-4">
                            <p className="rounded-xl border border-indigo-100 bg-indigo-50 px-4 py-3 text-sm font-extrabold text-indigo-700">Minimum withdraw amount is {money(wallet.minimumWithdraw || 500)}.</p>
                            <Input type="number" min={wallet.minimumWithdraw || 0} value={withdrawAmount} onChange={(e) => setWithdrawAmount(e.target.value)} className="h-12 rounded-xl border-slate-200 bg-slate-50 font-bold" />
                            <Button type="button" className="h-12 rounded-xl bg-slate-950 font-extrabold shadow-[0_14px_28px_-20px_rgba(15,23,42,0.9)]" onClick={submitWithdraw}>Submit withdraw</Button>
                        </div>
                    </PayoutCard>
                    <PayoutCard title="Withdraw history" icon={ReceiptText} iconTone="emerald">
                        <WithdrawHistoryCards items={state.payoutRequests} empty="No withdraw requests yet." />
                    </PayoutCard>
                </section>
            ) : null}

            {tab === 'transactions' ? (
                <section className="mt-8">
                    <PayoutCard title="Transaction history" icon={ReceiptText} iconTone="emerald">
                        <TransactionHistoryCards items={wallet.transactions || []} empty="No wallet transactions yet." />
                    </PayoutCard>
                </section>
            ) : null}
        </div>
    );
}

function PayoutCard({ title, icon: Icon, iconTone = 'indigo', flush = false, children }) {
    const iconClass = iconTone === 'emerald'
        ? 'bg-emerald-50 text-emerald-700'
        : 'bg-indigo-50 text-indigo-600';
    return (
        <section className="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
            <div className="flex items-center gap-4 border-b border-slate-100 px-6 py-6">
                <span className={cn('flex size-10 items-center justify-center rounded-xl', iconClass)}>
                    <Icon className="size-5" />
                </span>
                <h2 className="text-xl font-extrabold tracking-tight text-slate-950">{title}</h2>
            </div>
            <div className={cn(!flush && 'px-6 pb-6')}>
                {children}
            </div>
        </section>
    );
}

function WalletMetric({ label, value }) {
    return (
        <div className="rounded-2xl border border-slate-200 bg-[linear-gradient(180deg,#ffffff_0%,#f8fafc_100%)] px-4 py-4 shadow-[0_16px_38px_-34px_rgba(15,23,42,0.4)]">
            <span className="text-xs font-black uppercase tracking-[0.18em] text-slate-400">{label}</span>
            <strong className="mt-2 block text-xl font-extrabold tracking-tight text-slate-950">{value}</strong>
        </div>
    );
}

function payoutStatusVariant(status) {
    const value = String(status || '').toLowerCase();
    if (['approved', 'paid', 'paid_out', 'completed', 'success'].includes(value)) return 'success';
    if (['rejected', 'failed', 'cancelled'].includes(value)) return 'danger';
    if (['processing', 'pending', 'requested', 'reviewing', 'under_review'].includes(value)) return 'warning';
    return 'secondary';
}

function payoutStatusLabel(status) {
    return String(status || 'Pending').replace(/_/g, ' ').replace(/\b\w/g, (char) => char.toUpperCase());
}

function PayoutRows({ items = [], empty, type }) {
    if (!items.length) {
        return <div className="m-6 rounded-xl border border-slate-100 bg-slate-50 px-5 py-6 text-sm font-extrabold text-slate-500">{empty}</div>;
    }

    return (
        <div className="divide-y divide-slate-100">
            {items.map((item) => {
                const isCredit = item.direction === 'credit' || type === 'topup';
                return (
                    <div key={item.id || item.reference} className="grid gap-3 px-5 py-4 text-sm font-extrabold sm:grid-cols-[minmax(110px,auto)_1fr_auto_auto] sm:items-center">
                        <span className="text-slate-950">{item.id || item.reference}</span>
                        <span className="text-slate-500">{item.method || item.type || item.reference || 'Wallet payout'}</span>
                        <span className={cn('text-lg font-black', isCredit && type === 'transaction' ? 'text-emerald-600' : 'text-slate-950')}>
                            {isCredit && type === 'transaction' ? '+' : ''}{money(item.amount)}
                        </span>
                        {type === 'transaction' ? (
                            <span className="text-right text-sm font-bold text-slate-500">{item.createdAt || ''}</span>
                        ) : (
                            <Badge variant={payoutStatusVariant(item.status)}>{payoutStatusLabel(item.status)}</Badge>
                        )}
                    </div>
                );
            })}
        </div>
    );
}

function TopUpHistoryCards({ items = [], empty }) {
    if (!items.length) {
        return <div className="mt-6 rounded-xl border border-slate-100 bg-slate-50 px-5 py-6 text-sm font-extrabold text-slate-500">{empty}</div>;
    }

    return (
        <div className="mt-6 grid gap-4 md:grid-cols-2 xl:grid-cols-3">
            {items.map((item) => (
                <article key={item.id || item.reference} className="group rounded-2xl border border-slate-200 bg-[linear-gradient(180deg,#ffffff_0%,#f8fafc_100%)] p-5 shadow-[0_18px_42px_-30px_rgba(15,23,42,0.35)] transition hover:-translate-y-0.5 hover:border-emerald-200 hover:shadow-[0_22px_56px_-32px_rgba(16,185,129,0.28)]">
                    <div className="flex items-start justify-between gap-3">
                        <div>
                            <p className="text-xs font-black uppercase tracking-[0.18em] text-slate-400">Top-up request</p>
                            <h3 className="mt-2 text-lg font-extrabold tracking-tight text-slate-950">{item.id || 'Top-up'}</h3>
                        </div>
                        <Badge variant={payoutStatusVariant(item.status)}>{payoutStatusLabel(item.status)}</Badge>
                    </div>

                    <div className="mt-5 flex items-end justify-between gap-4">
                        <div>
                            <p className="text-xs font-bold uppercase tracking-wide text-slate-400">Requested amount</p>
                            <p className="mt-1 text-3xl font-black tracking-tight text-slate-950">{money(item.amount)}</p>
                        </div>
                        <div className="rounded-xl bg-emerald-50 px-3 py-2 text-right">
                            <p className="text-[11px] font-black uppercase tracking-[0.16em] text-emerald-700">Method</p>
                            <p className="mt-1 text-sm font-extrabold text-emerald-900">{payoutMethodLabel(item.method || '')}</p>
                        </div>
                    </div>

                    <div className="mt-5 grid gap-3">
                        <div className="rounded-xl border border-slate-200 bg-white px-4 py-3">
                            <p className="text-xs font-black uppercase tracking-[0.16em] text-slate-400">Reference</p>
                            <p className="mt-1 text-sm font-bold text-slate-700">{item.reference || 'Not provided'}</p>
                        </div>
                        <div className="rounded-xl border border-slate-200 bg-white px-4 py-3">
                            <p className="text-xs font-black uppercase tracking-[0.16em] text-slate-400">Submitted</p>
                            <p className="mt-1 text-sm font-bold text-slate-700">{item.createdAt || 'Pending timestamp'}</p>
                        </div>
                    </div>

                    <div className="mt-5 flex items-center justify-between gap-3">
                        <div className="inline-flex items-center gap-2 text-sm font-bold text-slate-500">
                            <ReceiptText className="size-4 text-slate-400" />
                            Review queue
                        </div>
                        {item.proof ? (
                            <a
                                href={item.proof}
                                target="_blank"
                                rel="noreferrer"
                                className="inline-flex items-center gap-2 rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm font-extrabold text-slate-700 transition hover:border-indigo-200 hover:bg-indigo-50 hover:text-indigo-700"
                            >
                                <Eye className="size-4" />
                                View proof
                            </a>
                        ) : (
                            <span className="text-sm font-bold text-slate-400">No proof attached</span>
                        )}
                    </div>
                </article>
            ))}
        </div>
    );
}

function WithdrawHistoryCards({ items = [], empty, compact = false }) {
    if (!items.length) {
        return <div className="mt-6 rounded-xl border border-slate-100 bg-slate-50 px-5 py-6 text-sm font-extrabold text-slate-500">{empty}</div>;
    }

    return (
        <div className={cn('mt-6 grid gap-4', compact ? 'xl:grid-cols-2' : 'md:grid-cols-2 xl:grid-cols-3')}>
            {items.map((item) => (
                <article key={item.id || item.reference} className="rounded-2xl border border-slate-200 bg-[linear-gradient(180deg,#ffffff_0%,#f8fafc_100%)] p-5 shadow-[0_18px_42px_-30px_rgba(15,23,42,0.35)] transition hover:-translate-y-0.5 hover:border-indigo-200 hover:shadow-[0_24px_60px_-34px_rgba(79,70,229,0.24)]">
                    <div className="flex items-start justify-between gap-3">
                        <div>
                            <p className="text-xs font-black uppercase tracking-[0.18em] text-slate-400">Withdraw request</p>
                            <h3 className="mt-2 text-lg font-extrabold tracking-tight text-slate-950">{item.id || 'Withdraw'}</h3>
                        </div>
                        <Badge variant={payoutStatusVariant(item.status)}>{payoutStatusLabel(item.status)}</Badge>
                    </div>

                    <div className="mt-5 rounded-2xl bg-[linear-gradient(135deg,#4f46e5_0%,#5b4cf0_52%,#312e81_100%)] px-4 py-4 text-white shadow-[0_22px_52px_-34px_rgba(91,76,240,0.6)]">
                        <p className="text-[11px] font-black uppercase tracking-[0.18em] text-slate-300">Requested payout</p>
                        <p className="mt-2 text-3xl font-black tracking-tight">{money(item.amount)}</p>
                    </div>

                    <div className="mt-5 grid gap-3">
                        <div className="rounded-xl border border-slate-200 bg-white px-4 py-3">
                            <p className="text-xs font-black uppercase tracking-[0.16em] text-slate-400">Method</p>
                            <p className="mt-1 text-sm font-bold text-slate-700">{payoutMethodLabel(item.method || '')}</p>
                        </div>
                        <div className="rounded-xl border border-slate-200 bg-white px-4 py-3">
                            <p className="text-xs font-black uppercase tracking-[0.16em] text-slate-400">Created</p>
                            <p className="mt-1 text-sm font-bold text-slate-700">{item.createdAt || 'Pending timestamp'}</p>
                        </div>
                    </div>
                </article>
            ))}
        </div>
    );
}

function TransactionHistoryCards({ items = [], empty }) {
    if (!items.length) {
        return <div className="mt-6 rounded-xl border border-slate-100 bg-slate-50 px-5 py-6 text-sm font-extrabold text-slate-500">{empty}</div>;
    }

    return (
        <div className="mt-6 grid gap-4 md:grid-cols-2 xl:grid-cols-3">
            {items.map((item) => {
                const isCredit = String(item.direction || '').toLowerCase() === 'credit';
                const accentClass = isCredit
                    ? 'from-emerald-500 to-teal-600'
                    : 'from-rose-500 to-orange-500';

                return (
                    <article key={item.id || item.reference} className="rounded-2xl border border-slate-200 bg-[linear-gradient(180deg,#ffffff_0%,#f8fafc_100%)] p-5 shadow-[0_18px_42px_-30px_rgba(15,23,42,0.35)] transition hover:-translate-y-0.5 hover:border-cyan-200 hover:shadow-[0_24px_60px_-34px_rgba(8,145,178,0.24)]">
                        <div className="flex items-start justify-between gap-3">
                            <div>
                                <p className="text-xs font-black uppercase tracking-[0.18em] text-slate-400">Transaction</p>
                                <h3 className="mt-2 text-lg font-extrabold tracking-tight text-slate-950">{item.reference || `Ledger #${item.id}`}</h3>
                            </div>
                            <span className={cn('inline-flex rounded-full bg-gradient-to-r px-3 py-1 text-xs font-black uppercase tracking-[0.16em] text-white', accentClass)}>
                                {isCredit ? 'Credit' : 'Debit'}
                            </span>
                        </div>

                        <div className="mt-5 flex items-end justify-between gap-4">
                            <div>
                                <p className="text-xs font-black uppercase tracking-[0.16em] text-slate-400">Amount</p>
                                <p className={cn('mt-1 text-3xl font-black tracking-tight', isCredit ? 'text-emerald-600' : 'text-rose-600')}>
                                    {isCredit ? '+' : '-'}{money(item.amount)}
                                </p>
                            </div>
                            <div className="rounded-xl border border-slate-200 bg-white px-3 py-2 text-right">
                                <p className="text-[11px] font-black uppercase tracking-[0.16em] text-slate-400">Type</p>
                                <p className="mt-1 text-sm font-extrabold text-slate-800">{payoutStatusLabel(item.type || 'ledger')}</p>
                            </div>
                        </div>

                        <div className="mt-5 grid gap-3">
                            <div className="rounded-xl border border-slate-200 bg-white px-4 py-3">
                                <p className="text-xs font-black uppercase tracking-[0.16em] text-slate-400">Recorded</p>
                                <p className="mt-1 text-sm font-bold text-slate-700">{item.createdAt || 'Pending timestamp'}</p>
                            </div>
                        </div>
                    </article>
                );
            })}
        </div>
    );
}

function HistoryList({ items, empty }) {
    return <div className="grid gap-3">{items.length ? items.map((item) => <div key={item.id} className="flex flex-wrap items-center justify-between gap-3 rounded-lg border border-slate-200 p-3 text-sm"><span className="font-semibold">{item.id || item.reference} · {item.method || item.type || item.direction}</span><strong>{money(item.amount)} · {item.status || item.createdAt || item.direction}</strong></div>) : <p className="rounded-lg bg-slate-50 p-5 text-sm font-semibold text-slate-500">{empty}</p>}</div>;
}

function LoadMoreButton({ onClick, className = '' }) {
    return (
        <div className={cn('flex justify-center', className)}>
            <Button type="button" variant="outline" onClick={onClick} className="rounded-2xl border-slate-300 bg-white px-6 font-extrabold shadow-sm hover:border-indigo-200 hover:bg-indigo-50 hover:text-indigo-700">
                Load more
            </Button>
        </div>
    );
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

function SellerOffers({ state, saveCoupon, toggleCoupon, deleteCoupon, pendingAction }) {
    const couponDefaults = useMemo(() => {
        const start = new Date();
        const end = new Date(start.getTime() + 30 * 24 * 60 * 60 * 1000);
        const format = (value) => {
            const year = value.getFullYear();
            const month = `${value.getMonth() + 1}`.padStart(2, '0');
            const day = `${value.getDate()}`.padStart(2, '0');
            const hour = `${value.getHours()}`.padStart(2, '0');
            const minute = `${value.getMinutes()}`.padStart(2, '0');
            return `${year}-${month}-${day}T${hour}:${minute}`;
        };

        return {
            id: null,
            code: '',
            title: '',
            description: '',
            discount_type: 'percentage',
            discount_value: '',
            min_spend: '',
            max_discount_amount: '',
            usage_limit: '',
            starts_at: format(start),
            ends_at: format(end),
            daily_start_time: '',
            daily_end_time: '',
            is_active: true,
        };
    }, []);
    const [coupon, setCoupon] = useState(couponDefaults);
    const [deleteCandidate, setDeleteCandidate] = useState(null);
    const [campaignModalOpen, setCampaignModalOpen] = useState(false);
    const [filter, setFilter] = useState('all');
    const [query, setQuery] = useState('');
    const [visibleCouponCount, setVisibleCouponCount] = useState(6);

    useEffect(() => {
        setCoupon(couponDefaults);
    }, [couponDefaults]);

    const toInputDateTime = (value) => {
        if (!value) return '';
        const parsed = new Date(value);
        if (Number.isNaN(parsed.getTime())) return '';
        const year = parsed.getFullYear();
        const month = `${parsed.getMonth() + 1}`.padStart(2, '0');
        const day = `${parsed.getDate()}`.padStart(2, '0');
        const hour = `${parsed.getHours()}`.padStart(2, '0');
        const minute = `${parsed.getMinutes()}`.padStart(2, '0');
        return `${year}-${month}-${day}T${hour}:${minute}`;
    };

    const visibleCoupons = useMemo(() => state.coupons.filter((item) => {
        const haystack = `${item.code} ${item.title} ${item.description} ${item.status}`.toLowerCase();
        return haystack.includes(query.toLowerCase()) && (filter === 'all' || String(item.status).toLowerCase() === filter);
    }), [filter, query, state.coupons]);
    const displayedCoupons = useMemo(() => visibleCoupons.slice(0, visibleCouponCount), [visibleCouponCount, visibleCoupons]);

    const activeCoupons = state.coupons.filter((item) => item.status === 'Active').length;
    const scheduledCoupons = state.coupons.filter((item) => item.status === 'Scheduled').length;
    const redeemedCount = state.coupons.reduce((sum, item) => sum + asNumber(item.usage), 0);
    const averageDiscount = state.coupons.length
        ? Math.round(state.coupons.reduce((sum, item) => sum + asNumber(item.value), 0) / state.coupons.length)
        : 0;
    const isSaving = pendingAction === 'seller:coupon';

    const resetCouponForm = () => setCoupon(couponDefaults);

    const openCreateModal = () => {
        resetCouponForm();
        setCampaignModalOpen(true);
    };

    useEffect(() => {
        setVisibleCouponCount(6);
    }, [filter, query]);

    const openEditor = (item) => {
        setCoupon({
            id: item.id,
            code: item.code || '',
            title: item.title || '',
            description: item.description || '',
            discount_type: item.type || 'percentage',
            discount_value: String(item.value ?? ''),
            min_spend: String(item.minSpend ?? ''),
            max_discount_amount: item.maxDiscountAmount != null ? String(item.maxDiscountAmount) : '',
            usage_limit: item.usageLimit != null ? String(item.usageLimit) : '',
            starts_at: toInputDateTime(item.startsAt),
            ends_at: toInputDateTime(item.endsAt),
            daily_start_time: item.dailyStartTime || '',
            daily_end_time: item.dailyEndTime || '',
            is_active: Boolean(item.isActive),
        });
        setCampaignModalOpen(true);
    };

    const submitCoupon = async () => {
        const payload = {
            ...coupon,
            code: coupon.code.trim().toUpperCase(),
            title: coupon.title.trim(),
            description: coupon.description.trim(),
            discount_value: coupon.discount_value === '' ? null : Number(coupon.discount_value),
            min_spend: coupon.min_spend === '' ? 0 : Number(coupon.min_spend),
            max_discount_amount: coupon.max_discount_amount === '' ? null : Number(coupon.max_discount_amount),
            usage_limit: coupon.usage_limit === '' ? null : Number(coupon.usage_limit),
            starts_at: coupon.starts_at || null,
            ends_at: coupon.ends_at || null,
            daily_start_time: coupon.daily_start_time || null,
            daily_end_time: coupon.daily_end_time || null,
            is_active: Boolean(coupon.is_active),
        };
        await saveCoupon(payload, coupon.id);
        resetCouponForm();
        setCampaignModalOpen(false);
    };

    const discountPreview = coupon.discount_type === 'fixed'
        ? `${state.business?.currency || 'BDT'} ${coupon.discount_value || '0'} off`
        : coupon.discount_type === 'shipping'
            ? 'Free standard shipping'
            : `${coupon.discount_value || '0'}% off`;

    return (
        <div className="space-y-5">
            <div className="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                <Stat label="Active offers" value={activeCoupons} hint="Live seller campaigns" icon={Sparkles} />
                <Stat label="Scheduled" value={scheduledCoupons} hint="Queued to launch later" icon={CalendarDays} />
                <Stat label="Redemptions" value={redeemedCount} hint="Total coupon usage" icon={ReceiptText} />
                <Stat label="Average discount" value={`${averageDiscount}%`} hint="Across active offer rules" icon={Tag} />
            </div>

            <section className="grid gap-6">
                <Panel
                    title="Coupons and campaigns"
                    icon={Sparkles}
                    actions={(
                        <div className="grid w-full gap-3 sm:grid-cols-[minmax(0,240px)_180px_auto] xl:w-auto xl:min-w-[560px]">
                            <Input value={query} onChange={(e) => setQuery(e.target.value)} placeholder="Search code or title" />
                            <select value={filter} onChange={(e) => setFilter(e.target.value)} className="h-11 rounded-xl border border-slate-200 bg-slate-50 px-3 text-sm font-semibold text-slate-800 transition focus:border-indigo-300 focus:bg-white focus:outline-none focus:ring-2 focus:ring-indigo-100">
                                <option value="all">All statuses</option>
                                <option value="active">Active</option>
                                <option value="scheduled">Scheduled</option>
                                <option value="paused">Paused</option>
                                <option value="expired">Expired</option>
                            </select>
                            <Button type="button" className="rounded-xl bg-slate-950 font-bold hover:bg-indigo-600" onClick={openCreateModal}>
                                <Plus className="size-4" />Create campaign
                            </Button>
                        </div>
                    )}
                >
                    <div className="mt-5 grid gap-4 xl:grid-cols-2">
                        {displayedCoupons.length ? displayedCoupons.map((item) => {
                            const statusVariant = item.status === 'Active' ? 'success' : item.status === 'Scheduled' ? 'secondary' : 'warning';
                            const usageText = item.usageLimit != null ? `${item.usage}/${item.usageLimit} used` : `${item.usage} used`;
                            const discountText = item.type === 'percentage'
                                ? `${item.value}% off`
                                : item.type === 'fixed'
                                    ? `${item.currency} ${Number(item.value || 0).toLocaleString('en-BD')} off`
                                    : 'Free shipping';

                            return (
                                <article key={item.id} className="rounded-[24px] border border-slate-200 bg-white p-5 shadow-[0_18px_48px_-36px_rgba(15,23,42,0.28)]">
                                    <div className="flex items-start justify-between gap-4">
                                        <div className="min-w-0">
                                            <div className="flex flex-wrap items-center gap-2">
                                                <span className="rounded-full bg-indigo-50 px-3 py-1 text-[11px] font-black uppercase tracking-[0.16em] text-indigo-700">{item.code}</span>
                                                <Badge variant={statusVariant}>{item.status}</Badge>
                                            </div>
                                            <h3 className="mt-3 text-xl font-black tracking-tight text-slate-950">{item.title}</h3>
                                            <p className="mt-2 text-sm font-medium leading-6 text-slate-500">{item.description || 'Seller-managed campaign.'}</p>
                                        </div>
                                        <div className="rounded-2xl bg-slate-950 px-4 py-3 text-right text-white">
                                            <p className="text-[11px] font-black uppercase tracking-[0.16em] text-slate-300">Offer</p>
                                            <p className="mt-1 text-lg font-black">{discountText}</p>
                                        </div>
                                    </div>

                                    <div className="mt-5 grid gap-3 sm:grid-cols-2">
                                        <div className="rounded-2xl bg-slate-50 p-4">
                                            <p className="text-[11px] font-black uppercase tracking-[0.16em] text-slate-400">Eligibility</p>
                                            <p className="mt-2 text-sm font-bold text-slate-700">Min spend {money(item.minSpend || 0)}</p>
                                            <p className="mt-1 text-xs font-semibold text-slate-500">{item.maxDiscountAmount != null ? `Cap ${money(item.maxDiscountAmount)}` : 'No discount cap'}</p>
                                        </div>
                                        <div className="rounded-2xl bg-slate-50 p-4">
                                            <p className="text-[11px] font-black uppercase tracking-[0.16em] text-slate-400">Usage</p>
                                            <p className="mt-2 text-sm font-bold text-slate-700">{usageText}</p>
                                            <p className="mt-1 text-xs font-semibold text-slate-500">{item.remaining != null ? `${item.remaining} remaining` : 'Unlimited redemption window'}</p>
                                        </div>
                                    </div>

                                    <div className="mt-4 grid gap-3 md:grid-cols-2">
                                        <div className="rounded-2xl border border-slate-200 px-4 py-3">
                                            <p className="text-[11px] font-black uppercase tracking-[0.16em] text-slate-400">Campaign window</p>
                                            <p className="mt-2 text-sm font-bold text-slate-700">{item.startsAt ? new Date(item.startsAt).toLocaleString() : 'Starts immediately'}</p>
                                            <p className="mt-1 text-xs font-semibold text-slate-500">{item.endsAt ? `Ends ${new Date(item.endsAt).toLocaleString()}` : 'No end date'}</p>
                                        </div>
                                        <div className="rounded-2xl border border-slate-200 px-4 py-3">
                                            <p className="text-[11px] font-black uppercase tracking-[0.16em] text-slate-400">Daily active hours</p>
                                            <p className="mt-2 text-sm font-bold text-slate-700">{item.dailyStartTime || item.dailyEndTime ? `${item.dailyStartTime || '00:00'} - ${item.dailyEndTime || '23:59'}` : 'All day availability'}</p>
                                            <p className="mt-1 text-xs font-semibold text-slate-500">Channel {item.marketingChannel || 'seller_web'}</p>
                                        </div>
                                    </div>

                                    <div className="mt-5 flex flex-wrap items-center justify-between gap-3 border-t border-slate-100 pt-4">
                                        <p className="text-xs font-semibold text-slate-500">Updated {item.updatedAt || item.createdAt || 'recently'}</p>
                                        <div className="flex flex-wrap gap-2">
                                            <Button type="button" variant="outline" className="rounded-xl font-bold" onClick={() => openEditor(item)}>
                                                <Edit className="size-4" />Edit
                                            </Button>
                                            <Button type="button" variant="outline" className="rounded-xl font-bold" onClick={() => toggleCoupon(item.id)}>
                                                {item.isActive ? 'Pause' : 'Activate'}
                                            </Button>
                                            <Button type="button" variant="outline" className="rounded-xl font-bold text-rose-600 hover:text-rose-700" onClick={() => setDeleteCandidate(item)}>
                                                <Trash2 className="size-4" />Delete
                                            </Button>
                                        </div>
                                    </div>
                                </article>
                            );
                        }) : (
                            <div className="rounded-2xl border border-dashed border-slate-200 bg-slate-50 p-8 text-center xl:col-span-2">
                                <p className="text-lg font-extrabold text-slate-950">No campaigns match this view</p>
                                <p className="mt-2 text-sm font-medium text-slate-500">Create your first seller coupon campaign or adjust the current filters.</p>
                            </div>
                        )}
                    </div>
                    {visibleCoupons.length > visibleCouponCount ? <LoadMoreButton onClick={() => setVisibleCouponCount((current) => current + 6)} className="mt-5" /> : null}
                </Panel>
            </section>

            {deleteCandidate ? (
                <div className="fixed inset-0 z-[90] flex items-center justify-center bg-slate-950/55 p-4 backdrop-blur-sm">
                    <div className="w-full max-w-md rounded-[28px] border border-slate-200 bg-white p-6 shadow-[0_36px_90px_-48px_rgba(15,23,42,0.6)]">
                        <div className="flex size-14 items-center justify-center rounded-2xl bg-rose-50 text-rose-600">
                            <Trash2 className="size-6" />
                        </div>
                        <p className="mt-5 text-xs font-black uppercase tracking-[0.18em] text-rose-500">Delete campaign</p>
                        <h3 className="mt-2 text-2xl font-extrabold tracking-tight text-slate-950">Remove this coupon campaign?</h3>
                        <p className="mt-3 text-sm font-medium leading-6 text-slate-500">
                            <span className="font-bold text-slate-700">{deleteCandidate.title}</span> and code <span className="font-bold text-slate-700">{deleteCandidate.code}</span> will be removed from your seller offers workspace.
                        </p>
                        <div className="mt-6 rounded-2xl border border-rose-100 bg-rose-50/80 p-4 text-sm font-medium leading-6 text-rose-700">
                            Delete this campaign only if you no longer need it. If you may reuse it later, pause it instead to preserve the campaign setup.
                        </div>
                        <div className="mt-6 flex gap-3">
                            <Button type="button" onClick={async () => { await deleteCoupon(deleteCandidate.id); setDeleteCandidate(null); }} className="flex-1 rounded-xl bg-rose-600 font-bold hover:bg-rose-700">
                                Delete campaign
                            </Button>
                            <Button type="button" variant="outline" onClick={() => setDeleteCandidate(null)} className="flex-1 rounded-xl font-bold">
                                Cancel
                            </Button>
                        </div>
                    </div>
                </div>
            ) : null}
            {campaignModalOpen ? (
                <div className="fixed inset-0 z-[90] flex items-center justify-center bg-slate-950/55 p-4 backdrop-blur-sm">
                    <div className="w-full max-w-3xl rounded-[30px] border border-slate-200 bg-white p-6 shadow-[0_36px_90px_-48px_rgba(15,23,42,0.6)]">
                        <div className="flex items-start justify-between gap-4">
                            <div>
                                <p className="text-xs font-black uppercase tracking-[0.18em] text-indigo-500">{coupon.id ? 'Edit campaign' : 'Create campaign'}</p>
                                <h3 className="mt-2 text-2xl font-extrabold tracking-tight text-slate-950">{coupon.id ? 'Update seller offer campaign' : 'Launch a new seller campaign'}</h3>
                                <p className="mt-2 text-sm font-medium text-slate-500">Configure a polished coupon campaign with rules, schedule, and discount controls.</p>
                            </div>
                            <Button type="button" variant="outline" size="icon" className="rounded-xl" onClick={() => setCampaignModalOpen(false)}>
                                <X className="size-4" />
                            </Button>
                        </div>

                        <div className="mt-5 rounded-2xl border border-slate-200 bg-[linear-gradient(135deg,#0f172a_0%,#312e81_100%)] p-5 text-white shadow-[0_22px_60px_-34px_rgba(49,46,129,0.7)]">
                            <p className="text-xs font-black uppercase tracking-[0.18em] text-violet-200">Offer preview</p>
                            <h3 className="mt-3 text-2xl font-black tracking-tight">{coupon.title || 'Campaign title'}</h3>
                            <p className="mt-2 text-sm font-medium text-slate-200">{coupon.description || 'Seller-wide campaign for your storefront and product catalog.'}</p>
                            <div className="mt-5 flex flex-wrap items-center gap-2">
                                <span className="rounded-full bg-white/10 px-3 py-1 text-xs font-black uppercase tracking-[0.16em] text-white">{coupon.code || 'CODE'}</span>
                                <span className="rounded-full bg-emerald-400/20 px-3 py-1 text-xs font-black uppercase tracking-[0.16em] text-emerald-100">{discountPreview}</span>
                            </div>
                        </div>

                        <div className="mt-5 grid gap-4 max-h-[65vh] overflow-y-auto pr-1">
                            <div className="grid gap-4 md:grid-cols-2">
                                <div>
                                    <p className="mb-2 text-sm font-bold text-slate-700">Coupon code</p>
                                    <Input value={coupon.code} onChange={(e) => setCoupon((current) => ({ ...current, code: e.target.value.toUpperCase() }))} placeholder="MEGA10" />
                                </div>
                                <div>
                                    <p className="mb-2 text-sm font-bold text-slate-700">Campaign title</p>
                                    <Input value={coupon.title} onChange={(e) => setCoupon((current) => ({ ...current, title: e.target.value }))} placeholder="Weekend launch offer" />
                                </div>
                            </div>
                            <div>
                                <p className="mb-2 text-sm font-bold text-slate-700">Description</p>
                                <textarea value={coupon.description} onChange={(e) => setCoupon((current) => ({ ...current, description: e.target.value }))} rows={3} className="w-full rounded-xl border border-slate-200 bg-slate-50 px-3 py-3 text-sm font-medium text-slate-800 transition focus:border-indigo-300 focus:bg-white focus:outline-none focus:ring-2 focus:ring-indigo-100" placeholder="Explain where and why this campaign is being used." />
                            </div>
                            <div className="grid gap-4 md:grid-cols-2">
                                <div>
                                    <p className="mb-2 text-sm font-bold text-slate-700">Discount type</p>
                                    <select value={coupon.discount_type} onChange={(e) => setCoupon((current) => ({ ...current, discount_type: e.target.value }))} className="h-11 w-full rounded-xl border border-slate-200 bg-slate-50 px-3 text-sm font-semibold text-slate-800 transition focus:border-indigo-300 focus:bg-white focus:outline-none focus:ring-2 focus:ring-indigo-100">
                                        <option value="percentage">Percentage</option>
                                        <option value="fixed">Fixed amount</option>
                                        <option value="shipping">Shipping</option>
                                    </select>
                                </div>
                                <div>
                                    <p className="mb-2 text-sm font-bold text-slate-700">{coupon.discount_type === 'percentage' ? 'Discount %' : 'Discount value'}</p>
                                    <Input value={coupon.discount_value} onChange={(e) => setCoupon((current) => ({ ...current, discount_value: e.target.value }))} placeholder={coupon.discount_type === 'percentage' ? '10' : '250'} disabled={coupon.discount_type === 'shipping'} />
                                </div>
                            </div>
                            <div className="grid gap-4 md:grid-cols-2">
                                <div>
                                    <p className="mb-2 text-sm font-bold text-slate-700">Minimum spend</p>
                                    <Input value={coupon.min_spend} onChange={(e) => setCoupon((current) => ({ ...current, min_spend: e.target.value }))} placeholder="0" />
                                </div>
                                <div>
                                    <p className="mb-2 text-sm font-bold text-slate-700">Max discount cap</p>
                                    <Input value={coupon.max_discount_amount} onChange={(e) => setCoupon((current) => ({ ...current, max_discount_amount: e.target.value }))} placeholder="Optional" />
                                </div>
                            </div>
                            <div className="grid gap-4 md:grid-cols-2">
                                <div>
                                    <p className="mb-2 text-sm font-bold text-slate-700">Usage limit</p>
                                    <Input value={coupon.usage_limit} onChange={(e) => setCoupon((current) => ({ ...current, usage_limit: e.target.value }))} placeholder="Unlimited if empty" />
                                </div>
                                <label className="flex items-center justify-between rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3">
                                    <span>
                                        <span className="block text-sm font-extrabold text-slate-950">Campaign status</span>
                                        <span className="mt-1 block text-xs font-medium text-slate-500">Pause the code without deleting campaign history.</span>
                                    </span>
                                    <input type="checkbox" checked={Boolean(coupon.is_active)} onChange={(e) => setCoupon((current) => ({ ...current, is_active: e.target.checked }))} className="size-5 rounded border-slate-300 text-indigo-600 focus:ring-indigo-200" />
                                </label>
                            </div>
                            <div className="grid gap-4 md:grid-cols-2">
                                <div>
                                    <p className="mb-2 text-sm font-bold text-slate-700">Start date</p>
                                    <Input type="datetime-local" value={coupon.starts_at} onChange={(e) => setCoupon((current) => ({ ...current, starts_at: e.target.value }))} />
                                </div>
                                <div>
                                    <p className="mb-2 text-sm font-bold text-slate-700">End date</p>
                                    <Input type="datetime-local" value={coupon.ends_at} onChange={(e) => setCoupon((current) => ({ ...current, ends_at: e.target.value }))} />
                                </div>
                            </div>
                            <div className="grid gap-4 md:grid-cols-2">
                                <div>
                                    <p className="mb-2 text-sm font-bold text-slate-700">Daily start time</p>
                                    <Input type="time" value={coupon.daily_start_time} onChange={(e) => setCoupon((current) => ({ ...current, daily_start_time: e.target.value }))} />
                                </div>
                                <div>
                                    <p className="mb-2 text-sm font-bold text-slate-700">Daily end time</p>
                                    <Input type="time" value={coupon.daily_end_time} onChange={(e) => setCoupon((current) => ({ ...current, daily_end_time: e.target.value }))} />
                                </div>
                            </div>
                        </div>

                        <div className="mt-6 flex gap-3 border-t border-slate-100 pt-5">
                            <Button type="button" variant="outline" className="flex-1 rounded-xl font-bold" onClick={resetCouponForm}>Reset</Button>
                            <Button type="button" variant="outline" className="flex-1 rounded-xl font-bold" onClick={() => setCampaignModalOpen(false)}>Cancel</Button>
                            <Button type="button" className="flex-1 rounded-xl bg-slate-950 font-bold hover:bg-indigo-600" onClick={submitCoupon} disabled={isSaving}>
                                {isSaving ? 'Saving...' : coupon.id ? 'Update campaign' : 'Create campaign'}
                            </Button>
                        </div>
                    </div>
                </div>
            ) : null}
        </div>
    );
}

function SellerAnalytics({ state }) {
    const revenue = state.orders.reduce((sum, order) => sum + order.amount, 0);
    const completed = state.orders.filter((order) => order.status === 'Completed').length;
    const active = state.orders.length - completed;
    const sold = state.sellerProducts.reduce((sum, product) => sum + asNumber(product.soldCount || product.sold || 0), 0);
    const [visibleTopProductCount, setVisibleTopProductCount] = useState(6);
    const topProducts = [...state.sellerProducts]
        .sort((a, b) => asNumber(b.soldCount || b.sold || 0) - asNumber(a.soldCount || a.sold || 0))
        .slice(0, 12);
    const visibleTopProducts = useMemo(() => topProducts.slice(0, visibleTopProductCount), [topProducts, visibleTopProductCount]);

    return (
        <div className="space-y-5">
            <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                <Stat label="Revenue" value={money(revenue)} hint="Gross marketplace value" icon={BarChart3} />
                <Stat label="Orders" value={state.orders.length} hint={`${active} active / ${completed} completed`} icon={ReceiptText} />
                <Stat label="Units sold" value={sold} hint="Across seller listings" icon={TrendingUp} />
                <Stat label="Conversion work" value={state.wishlist.length} hint="Buyer saved signals" icon={Heart} />
            </div>
            <section className="grid gap-5 lg:grid-cols-[1fr_360px]">
                <Panel title="Performance by listing" icon={TrendingUp}>
                    <div className="grid gap-4 xl:grid-cols-3">
                        {visibleTopProducts.length ? visibleTopProducts.map((product, index) => {
                            const soldCount = asNumber(product.soldCount || product.sold || 0);
                            const stockCount = asNumber(product.stock);
                            const width = Math.max(8, Math.min(100, soldCount * 12));
                            const performance = soldCount > 0 ? Math.min(100, soldCount * 10) : 6;
                            return (
                                <article key={product.id} className="rounded-[24px] border border-slate-200 bg-white p-4 shadow-[0_18px_42px_-34px_rgba(15,23,42,0.24)]">
                                    <div className="flex items-start justify-between gap-3">
                                        <div className="min-w-0 flex-1">
                                            <p className="text-xs font-extrabold uppercase tracking-wide text-slate-400">#{index + 1} · {product.subcategory || product.category || 'Catalog'}</p>
                                            <p className="mt-1 line-clamp-2 min-h-11 font-extrabold text-slate-950">{product.title}</p>
                                        </div>
                                        <strong className="shrink-0 text-sm font-black text-rose-600">{money(product.price)}</strong>
                                    </div>

                                    <div className="mt-4 grid grid-cols-2 gap-2 text-center text-xs font-bold">
                                        <span className="rounded-xl bg-slate-50 p-3 text-slate-700">
                                            <strong className="block text-lg font-black text-slate-950">{soldCount}</strong>
                                            Units sold
                                        </span>
                                        <span className="rounded-xl bg-slate-50 p-3 text-slate-700">
                                            <strong className="block text-lg font-black text-slate-950">{stockCount}</strong>
                                            Stock left
                                        </span>
                                    </div>

                                    <div className="mt-4">
                                        <div className="flex items-center justify-between gap-3 text-[11px] font-black uppercase tracking-[0.16em] text-slate-400">
                                            <span>Sales momentum</span>
                                            <span>{performance}%</span>
                                        </div>
                                        <div className="mt-2 h-2 overflow-hidden rounded-full bg-slate-100">
                                            <div className="h-full rounded-full bg-indigo-600" style={{ width: `${width}%` }} />
                                        </div>
                                    </div>

                                    <div className="mt-4 flex items-center justify-between gap-3 border-t border-slate-100 pt-4">
                                        <p className="text-xs font-semibold text-slate-500">{soldCount ? `${soldCount} completed sale${soldCount === 1 ? '' : 's'}` : 'No completed sales yet'}</p>
                                        <Badge variant={stockCount > 0 ? 'secondary' : 'warning'}>{stockCount > 0 ? 'In stock' : 'Out of stock'}</Badge>
                                    </div>
                                </article>
                            );
                        }) : <p className="rounded-lg bg-slate-50 p-5 text-sm font-semibold text-slate-500 xl:col-span-3">Publish listings to unlock product-level analytics.</p>}
                    </div>
                    {topProducts.length > visibleTopProductCount ? <LoadMoreButton onClick={() => setVisibleTopProductCount((current) => current + 3)} className="mt-5" /> : null}
                </Panel>
                <Panel title="Next actions" icon={ClipboardCheck}>
                    <div className="grid gap-3">
                        {[
                            ['Add catalog depth', '/seller/products', 'Create a listing with category, subcategory, stock, and delivery rules.'],
                            ['Review fulfillment', '/seller/orders', 'Track paid orders, escrow state, and delivery stages.'],
                            ['Tune offers', '/seller/offers', 'Create coupons and promotion controls for active products.'],
                        ].map(([title, href, body]) => (
                            <Link key={title} href={href} className="rounded-lg border border-slate-200 p-3 transition hover:border-indigo-200 hover:bg-indigo-50">
                                <p className="font-extrabold text-slate-950">{title}</p>
                                <p className="mt-1 text-sm font-medium leading-6 text-slate-500">{body}</p>
                            </Link>
                        ))}
                    </div>
                </Panel>
            </section>
        </div>
    );
}

function SellerBusiness({ state, saveBusiness, uploadSellerMedia, pendingAction }) {
    const [business, setBusiness] = useState(state.business);
    const [uploadingField, setUploadingField] = useState('');
    const countryOptions = ['Bangladesh', 'India', 'Pakistan', 'Sri Lanka', 'Nepal', 'United Arab Emirates', 'United States', 'United Kingdom'];

    useEffect(() => {
        setBusiness(state.business);
    }, [state.business]);

    const updateBusiness = (key, value) => {
        setBusiness((current) => {
            const next = { ...current, [key]: value };
            if (['addressLine', 'city', 'region', 'postalCode', 'country'].includes(key)) {
                next.address = [next.addressLine, next.city, next.region, next.postalCode, next.country]
                    .map((item) => String(item || '').trim())
                    .filter(Boolean)
                    .join(', ');
            }
            return next;
        });
    };

    const handleImageUpload = async (file, field, purpose = 'profile') => {
        if (!file) return;
        setUploadingField(field);
        try {
            const media = await uploadSellerMedia(file, purpose);
            if (media?.url) {
                updateBusiness(field, media.url);
            }
        } finally {
            setUploadingField('');
        }
    };

    const isSaving = pendingAction === 'business';

    return (
        <div className="space-y-5">
            <Panel title="Business profile" icon={BriefcaseBusiness}>
                <div className="grid gap-5 lg:grid-cols-[1.1fr_0.9fr]">
                    <div className="space-y-4">
                        <div className="overflow-hidden rounded-[22px] border border-slate-200 bg-slate-50">
                            <div className="relative h-48 bg-gradient-to-br from-slate-950 via-slate-800 to-cyan-700">
                                {business.bannerImageUrl ? <img src={business.bannerImageUrl} alt="Seller banner" className="h-full w-full object-cover" /> : null}
                                <div className="absolute inset-x-0 bottom-0 flex items-end justify-between gap-4 bg-gradient-to-t from-slate-950/85 via-slate-950/30 to-transparent p-5">
                                    <div className="flex items-center gap-4">
                                        <div className="flex size-20 items-center justify-center overflow-hidden rounded-2xl border-4 border-white bg-white shadow-lg">
                                            {business.storeLogoUrl ? (
                                                <img src={business.storeLogoUrl} alt="Seller logo" className="h-full w-full object-cover" />
                                            ) : (
                                                <Store className="size-9 text-slate-400" />
                                            )}
                                        </div>
                                        <div>
                                            <p className="text-lg font-extrabold text-white">{business.name || 'Seller store'}</p>
                                            <p className="mt-1 max-w-xl text-sm font-medium text-slate-200">{business.storeDescription || 'Add a store description, contact details, and branding that match the mobile seller profile.'}</p>
                                        </div>
                                    </div>
                                    <Badge variant="secondary" className="border-white/20 bg-white/15 text-white">{business.verification || 'unverified'}</Badge>
                                </div>
                            </div>
                        </div>

                        <div className="grid gap-3 md:grid-cols-2">
                            <div className="rounded-xl border border-slate-200 bg-slate-50 p-4">
                                <p className="text-sm font-extrabold text-slate-950">Seller profile image</p>
                                <p className="mt-1 text-sm font-medium text-slate-500">Upload the store logo or seller avatar used across the storefront.</p>
                                <label className="mt-4 inline-flex cursor-pointer items-center gap-2 rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm font-bold text-slate-700 transition hover:border-indigo-200 hover:bg-indigo-50 hover:text-indigo-700">
                                    <Upload className="size-4" />{uploadingField === 'storeLogoUrl' ? 'Uploading...' : 'Upload profile image'}
                                    <input type="file" accept="image/*" className="sr-only" onChange={(event) => handleImageUpload(event.target.files?.[0], 'storeLogoUrl', 'profile')} />
                                </label>
                            </div>
                            <div className="rounded-xl border border-slate-200 bg-slate-50 p-4">
                                <p className="text-sm font-extrabold text-slate-950">Seller banner</p>
                                <p className="mt-1 text-sm font-medium text-slate-500">Upload the wide hero image shown on the public store profile.</p>
                                <label className="mt-4 inline-flex cursor-pointer items-center gap-2 rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm font-bold text-slate-700 transition hover:border-indigo-200 hover:bg-indigo-50 hover:text-indigo-700">
                                    <Upload className="size-4" />{uploadingField === 'bannerImageUrl' ? 'Uploading...' : 'Upload banner'}
                                    <input type="file" accept="image/*" className="sr-only" onChange={(event) => handleImageUpload(event.target.files?.[0], 'bannerImageUrl', 'profile')} />
                                </label>
                            </div>
                        </div>
                    </div>

                    <div className="grid gap-3">
                        <div className="grid gap-3 md:grid-cols-2">
                            <div>
                                <p className="mb-2 text-sm font-bold text-slate-700">Store name</p>
                                <Input value={business.name || ''} onChange={(e) => updateBusiness('name', e.target.value)} placeholder="Seller store name" />
                            </div>
                            <div>
                                <p className="mb-2 text-sm font-bold text-slate-700">Contact phone</p>
                                <Input value={business.phone || ''} onChange={(e) => updateBusiness('phone', e.target.value)} placeholder="+8801XXXXXXXXX" />
                            </div>
                        </div>
                        <div>
                            <p className="mb-2 text-sm font-bold text-slate-700">Store description</p>
                            <textarea value={business.storeDescription || ''} onChange={(e) => updateBusiness('storeDescription', e.target.value)} rows={4} className="w-full rounded-md border border-slate-200 bg-slate-50 px-3 py-2.5 text-sm font-medium text-slate-800 shadow-sm transition focus:border-indigo-300 focus:bg-white focus:outline-none focus:ring-2 focus:ring-indigo-100" placeholder="Tell buyers what the store sells and why they should trust it." />
                        </div>
                        <div>
                            <p className="mb-2 text-sm font-bold text-slate-700">Contact email</p>
                            <Input value={business.contactEmail || ''} onChange={(e) => updateBusiness('contactEmail', e.target.value)} placeholder="seller@example.com" />
                        </div>
                        <div>
                            <p className="mb-2 text-sm font-bold text-slate-700">Address line</p>
                            <Input value={business.addressLine || ''} onChange={(e) => updateBusiness('addressLine', e.target.value)} placeholder="House, road, area" />
                        </div>
                        <div className="grid gap-3 md:grid-cols-2">
                            <div>
                                <p className="mb-2 text-sm font-bold text-slate-700">City</p>
                                <Input value={business.city || ''} onChange={(e) => updateBusiness('city', e.target.value)} placeholder="Dhaka" />
                            </div>
                            <div>
                                <p className="mb-2 text-sm font-bold text-slate-700">Region</p>
                                <Input value={business.region || ''} onChange={(e) => updateBusiness('region', e.target.value)} placeholder="Dhaka Division" />
                            </div>
                        </div>
                        <div className="grid gap-3 md:grid-cols-2">
                            <div>
                                <p className="mb-2 text-sm font-bold text-slate-700">Postal code</p>
                                <Input value={business.postalCode || ''} onChange={(e) => updateBusiness('postalCode', e.target.value)} placeholder="1207" />
                            </div>
                            <div>
                                <p className="mb-2 text-sm font-bold text-slate-700">Country</p>
                                <select
                                    value={business.country || ''}
                                    onChange={(e) => updateBusiness('country', e.target.value)}
                                    className="h-11 w-full rounded-md border border-slate-200 bg-slate-50 px-3 text-sm font-semibold text-slate-800 shadow-sm transition focus:border-indigo-300 focus:bg-white focus:outline-none focus:ring-2 focus:ring-indigo-100"
                                >
                                    <option value="">Select country</option>
                                    {countryOptions.map((country) => (
                                        <option key={country} value={country}>{country}</option>
                                    ))}
                                </select>
                            </div>
                        </div>
                        <div className="rounded-xl border border-slate-200 bg-slate-50 p-4">
                            <p className="text-sm font-extrabold text-slate-950">Verification status</p>
                            <p className="mt-1 text-sm font-medium text-slate-500">Managed by the KYC workflow and shown here for reference.</p>
                            <p className="mt-3 inline-flex rounded-md border border-slate-200 bg-white px-3 py-2 text-sm font-extrabold uppercase tracking-wide text-slate-700">{business.verification || 'not submitted'}</p>
                        </div>
                    </div>
                </div>
                <Button className="mt-5" disabled={isSaving || uploadingField !== ''} onClick={() => saveBusiness(business)}>
                    {isSaving ? 'Saving...' : 'Save business profile'}
                </Button>
            </Panel>
        </div>
    );
}

function SellerMenu({ state }) {
    const ops = state.sellerOps || {};
    const unread = ops.unreadNotificationCount ?? (ops.notifications || []).filter((item) => !(item.is_read ?? item.read)).length;
    const items = [
        ['/seller/store-profile', 'Store Profile', 'Manage public seller identity and contact details.', Store, state.business?.verification || 'Profile'],
        ['/seller/kyc', 'KYC', 'Verification state, submitted documents, and review readiness.', ShieldCheck, ops.kyc?.status || state.business?.verification || 'Not submitted'],
        ['/seller/notifications', 'Notifications', 'Order, payout, review, and policy updates.', Bell, unread ? `${unread} unread` : 'Clear'],
        ['/seller/bank-payment-methods', 'Payout Methods', 'Bank, bKash, Nagad, and default settlement rails.', CreditCard, `${(ops.payoutMethods || []).length} saved`],
        ['/seller/warehouses', 'Warehouse Management', 'Stock locations, reserved units, and low-stock control.', Building2, `${(ops.warehouses || []).length} location`],
        ['/seller/offers', 'Offers & Campaigns', 'Coupon campaigns, discount rules, and promotion controls.', Sparkles, `${state.coupons.length} offer`],
        ['/seller/earnings', 'Earnings', 'Revenue analytics, listing performance, and seller growth insights.', BarChart3, `${state.orders.length} order`],
        ['/seller/withdraw-history', 'Withdraw History', 'Payout requests and settlement status.', ReceiptText, `${state.payoutRequests.length} request`],
        ['/seller/disputes', 'Disputes', 'Buyer disputes, escalations, and evidence workflow.', ShieldCheck, `${(ops.disputes || []).length} open`],
        ['/seller/reviews', 'Reviews', 'Ratings, replies, and product feedback.', Star, `${(ops.reviews || []).length} review`],
        ['/seller/shipping-settings', 'Shipping Settings', 'COD, processing time, and enabled methods.', Truck, ops.shippingSettings?.processingTimeLabel || 'Configure'],
        ['/seller/returns', 'Returns & Refund Queue', 'Seller return decisions and refund visibility.', ClipboardCheck, `${(ops.returns || []).length} return`],
        ['/seller/store-settings', 'Store Settings', 'Storefront branding and operational defaults.', Settings, 'Open'],
        ['/seller/support', 'Help & Support', 'Support desk and active marketplace chats.', Headphones, `${state.supportTickets.length} ticket`],
    ];

    return (
        <div className="space-y-5">
            <section className="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
                <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <p className="text-xs font-extrabold uppercase tracking-wide text-indigo-600">Seller workspace</p>
                        <h1 className="mt-1 text-2xl font-extrabold tracking-tight text-slate-950">{state.business?.name || state.user?.name || 'Seller account'}</h1>
                        <p className="mt-1 text-sm font-semibold text-slate-500">The web panel now mirrors the mobile seller operation menu.</p>
                    </div>
                    <Button asChild className="bg-slate-950 hover:bg-indigo-600"><Link href="/seller/products"><Plus className="size-4" />Create listing</Link></Button>
                </div>
            </section>
            <section className="grid gap-3 md:grid-cols-2 xl:grid-cols-3">
                {items.map(([href, title, body, Icon, meta]) => (
                    <Link key={href} href={href} className="group rounded-xl border border-slate-200 bg-white p-4 shadow-sm transition hover:-translate-y-0.5 hover:border-indigo-200 hover:shadow-md">
                        <div className="flex items-start justify-between gap-4">
                            <span className="flex size-10 items-center justify-center rounded-lg bg-slate-100 text-slate-700 transition group-hover:bg-indigo-600 group-hover:text-white"><Icon className="size-5" /></span>
                            <Badge variant="secondary">{meta}</Badge>
                        </div>
                        <h2 className="mt-4 text-base font-extrabold text-slate-950">{title}</h2>
                        <p className="mt-2 text-sm font-medium leading-6 text-slate-500">{body}</p>
                    </Link>
                ))}
            </section>
        </div>
    );
}

function SellerWarehouse({ state, adjustStock, saveWarehouse, deleteWarehouse }) {
    const ops = state.sellerOps || {};
    const warehouses = ops.warehouses || [];
    const stockMovements = ops.stockMovements || [];
    const emptyWarehouseForm = { id: null, name: '', code: '', address: '', city: '', contact_person: '', phone: '', status: 'active' };
    const [warehouseForm, setWarehouseForm] = useState(emptyWarehouseForm);
    const totalAvailable = warehouses.reduce((sum, item) => sum + asNumber(item.available), 0);
    const sold = warehouses.reduce((sum, item) => sum + asNumber(item.sold), 0);
    const lowStock = state.sellerProducts.filter((product) => asNumber(product.stock) > 0 && asNumber(product.stock) < 10).length;
    const outOfStock = state.sellerProducts.filter((product) => asNumber(product.stock) <= 0).length;
    const [warehouseModalOpen, setWarehouseModalOpen] = useState(false);
    const [deleteCandidate, setDeleteCandidate] = useState(null);
    const [visibleMovementCount, setVisibleMovementCount] = useState(6);
    const movementPerPage = 6;
    const visibleMovements = useMemo(() => stockMovements.slice(0, visibleMovementCount), [stockMovements, visibleMovementCount]);

    const submitWarehouse = async () => {
        await saveWarehouse(warehouseForm);
        setWarehouseForm(emptyWarehouseForm);
        setWarehouseModalOpen(false);
    };

    const openCreateWarehouse = () => {
        setWarehouseForm(emptyWarehouseForm);
        setWarehouseModalOpen(true);
    };

    const openEditWarehouse = (warehouse) => {
        setWarehouseForm({
            id: warehouse.id,
            name: warehouse.name || '',
            code: warehouse.code || '',
            address: warehouse.address || '',
            city: warehouse.city || '',
            contact_person: warehouse.contactPerson || warehouse.contact_person || '',
            phone: warehouse.phone || '',
            status: warehouse.active ? 'active' : 'inactive',
        });
        setWarehouseModalOpen(true);
    };

    const confirmDeleteWarehouse = async () => {
        if (!deleteCandidate?.id) return;
        await deleteWarehouse(deleteCandidate.id);
        setDeleteCandidate(null);
    };

    return (
        <div className="space-y-5">
            <div className="grid gap-3 sm:grid-cols-2 xl:grid-cols-5">
                <Stat label="Warehouses" value={warehouses.length} hint="Active storage locations" icon={Building2} />
                <Stat label="Available stock" value={totalAvailable} hint="Across connected warehouses" icon={Boxes} />
                <Stat label="Sold units" value={sold} hint="Fulfilled inventory" icon={TrendingUp} />
                <Stat label="Low stock" value={lowStock} hint="Below 10 units" icon={AlertCircle} />
                <Stat label="Out of stock" value={outOfStock} hint="Unavailable listings" icon={PackageCheck} />
            </div>
            <section className="grid gap-5 lg:grid-cols-[360px_1fr]">
                <Panel title="Warehouse locations" icon={Building2}>
                    <div className="grid gap-3">
                        <Button type="button" onClick={openCreateWarehouse} className="rounded-2xl bg-slate-950 font-extrabold shadow-[0_16px_38px_-26px_rgba(15,23,42,0.7)]">
                            <Plus className="size-4" />Add new warehouse
                        </Button>
                        {warehouses.length ? warehouses.map((warehouse) => (
                            <div key={warehouse.id} className="rounded-2xl border border-slate-200 bg-white p-4 shadow-[0_18px_42px_-34px_rgba(15,23,42,0.28)]">
                                <div className="flex items-start justify-between gap-3">
                                    <div className="min-w-0">
                                        <p className="font-extrabold text-slate-950">{warehouse.name}</p>
                                        <p className="mt-1 text-sm font-semibold text-slate-500">{warehouse.city || 'No city set'}</p>
                                    </div>
                                    <Badge variant={warehouse.active ? 'success' : 'secondary'}>{warehouse.active ? 'Active' : 'Paused'}</Badge>
                                </div>
                                <div className="mt-3 flex items-center justify-between gap-3">
                                    <span className="rounded-full bg-indigo-50 px-3 py-1 text-[11px] font-black uppercase tracking-[0.16em] text-indigo-700">
                                        {warehouse.code || 'WH'}
                                    </span>
                                    <div className="flex items-center gap-2">
                                        <Button type="button" variant="outline" size="icon" className="rounded-xl" onClick={() => openEditWarehouse(warehouse)}>
                                            <Edit className="size-4" />
                                        </Button>
                                        <Button type="button" variant="outline" size="icon" className="rounded-xl text-rose-600 hover:text-rose-700" onClick={() => setDeleteCandidate(warehouse)}>
                                            <Trash2 className="size-4" />
                                        </Button>
                                    </div>
                                </div>
                                <div className="mt-4 grid gap-3 rounded-2xl bg-slate-50/90 p-3">
                                    <div className="grid grid-cols-2 gap-2 text-center text-xs font-bold">
                                        <span className="rounded-xl bg-white p-3 text-slate-700">
                                            <strong className="block text-lg font-black text-slate-950">{warehouse.available}</strong>
                                            Available units
                                        </span>
                                        <span className="rounded-xl bg-white p-3 text-slate-700">
                                            <strong className="block text-lg font-black text-slate-950">{warehouse.listings}</strong>
                                            Active listings
                                        </span>
                                    </div>
                                    <div className="grid gap-2 text-sm">
                                        <div className="flex items-start gap-2 rounded-xl bg-white px-3 py-2 text-slate-600">
                                            <MapPin className="mt-0.5 size-4 shrink-0 text-slate-400" />
                                            <span className="min-w-0 font-semibold">{warehouse.address || 'Address not added yet'}</span>
                                        </div>
                                        <div className="grid gap-2 sm:grid-cols-2">
                                            <div className="flex items-center gap-2 rounded-xl bg-white px-3 py-2 text-slate-600">
                                                <User className="size-4 shrink-0 text-slate-400" />
                                                <span className="truncate font-semibold">{warehouse.contactPerson || warehouse.contact_person || 'No contact person'}</span>
                                            </div>
                                            <div className="flex items-center gap-2 rounded-xl bg-white px-3 py-2 text-slate-600">
                                                <Smartphone className="size-4 shrink-0 text-slate-400" />
                                                <span className="truncate font-semibold">{warehouse.phone || 'No phone added'}</span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        )) : <p className="rounded-lg bg-slate-50 p-4 text-sm font-semibold text-slate-500">No warehouse stock is connected yet.</p>}
                    </div>
                </Panel>
                <div className="space-y-5">
                    <SellerInventory state={state} adjustStock={adjustStock} />
                    <Panel title="Stock movement history" icon={ReceiptText}>
                        <div className="grid gap-4 xl:grid-cols-3">
                            {stockMovements.length ? visibleMovements.map((item) => (
                                <div key={item.id} className="rounded-2xl border border-slate-200 bg-white p-4 text-sm shadow-[0_18px_42px_-34px_rgba(15,23,42,0.24)]">
                                    <div>
                                        <p className="font-extrabold text-slate-950">{item.product}{item.variant ? ` · ${item.variant}` : ''}</p>
                                        <p className="mt-1 font-semibold text-slate-500">{item.warehouse} · {item.reason || item.type} · {item.createdAt}</p>
                                    </div>
                                    <div className="mt-4 flex items-center justify-between gap-3">
                                        <Badge variant={item.delta >= 0 ? 'success' : 'warning'}>{item.delta >= 0 ? '+' : ''}{item.delta}</Badge>
                                        <span className="text-sm font-extrabold text-slate-700">Stock {item.stockAfter}</span>
                                    </div>
                                </div>
                            )) : <p className="rounded-lg bg-slate-50 p-5 text-sm font-semibold text-slate-500 xl:col-span-3">No stock movements recorded yet.</p>}
                        </div>
                        {stockMovements.length > visibleMovementCount ? <LoadMoreButton onClick={() => setVisibleMovementCount((current) => current + movementPerPage)} className="mt-4" /> : null}
                    </Panel>
                </div>
            </section>
            {warehouseModalOpen ? (
                <div className="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/45 p-4 backdrop-blur-sm">
                    <div className="w-full max-w-2xl rounded-[28px] border border-slate-200 bg-white p-6 shadow-[0_32px_90px_-36px_rgba(15,23,42,0.45)]">
                        <div className="flex items-start justify-between gap-4">
                            <div>
                                <p className="text-xs font-black uppercase tracking-[0.18em] text-indigo-500">{warehouseForm.id ? 'Edit warehouse' : 'Add warehouse'}</p>
                                <h3 className="mt-2 text-2xl font-extrabold tracking-tight text-slate-950">{warehouseForm.id ? 'Update warehouse details' : 'Create a new warehouse'}</h3>
                            </div>
                            <Button type="button" variant="outline" size="icon" onClick={() => setWarehouseModalOpen(false)} className="rounded-xl">
                                <X className="size-4" />
                            </Button>
                        </div>
                        <div className="mt-6 grid gap-3">
                            <Input placeholder="Warehouse name" value={warehouseForm.name} onChange={(e) => setWarehouseForm({ ...warehouseForm, name: e.target.value })} />
                            <Input placeholder="Code" value={warehouseForm.code} onChange={(e) => setWarehouseForm({ ...warehouseForm, code: e.target.value.toUpperCase() })} />
                            <Input placeholder="Address" value={warehouseForm.address} onChange={(e) => setWarehouseForm({ ...warehouseForm, address: e.target.value })} />
                            <div className="grid gap-3 sm:grid-cols-2">
                                <Input placeholder="City" value={warehouseForm.city} onChange={(e) => setWarehouseForm({ ...warehouseForm, city: e.target.value })} />
                                <Input placeholder="Contact person" value={warehouseForm.contact_person} onChange={(e) => setWarehouseForm({ ...warehouseForm, contact_person: e.target.value })} />
                            </div>
                            <div className="grid gap-3 sm:grid-cols-2">
                                <Input placeholder="Phone" value={warehouseForm.phone} onChange={(e) => setWarehouseForm({ ...warehouseForm, phone: e.target.value })} />
                                <select value={warehouseForm.status} onChange={(e) => setWarehouseForm({ ...warehouseForm, status: e.target.value })} className="h-11 rounded-md border border-slate-200 bg-slate-50 px-3 text-sm font-semibold text-slate-800 shadow-sm transition focus:border-indigo-300 focus:bg-white focus:outline-none focus:ring-2 focus:ring-indigo-100">
                                    <option value="active">Active</option>
                                    <option value="inactive">Inactive</option>
                                </select>
                            </div>
                        </div>
                        <div className="mt-6 flex gap-3">
                            <Button type="button" onClick={submitWarehouse} className="flex-1 rounded-xl bg-slate-950 font-bold">{warehouseForm.id ? 'Save changes' : 'Save warehouse'}</Button>
                            <Button type="button" variant="outline" onClick={() => setWarehouseModalOpen(false)} className="flex-1 rounded-xl font-bold">Cancel</Button>
                        </div>
                    </div>
                </div>
            ) : null}
            {deleteCandidate ? (
                <div className="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/45 p-4 backdrop-blur-sm">
                    <div className="w-full max-w-md rounded-[28px] border border-slate-200 bg-white p-6 shadow-[0_32px_90px_-36px_rgba(15,23,42,0.45)]">
                        <p className="text-xs font-black uppercase tracking-[0.18em] text-rose-500">Delete warehouse</p>
                        <h3 className="mt-2 text-2xl font-extrabold tracking-tight text-slate-950">Remove {deleteCandidate.name}?</h3>
                        <p className="mt-2 text-sm font-semibold text-slate-500">This will remove the warehouse from the seller workspace.</p>
                        <div className="mt-6 flex gap-3">
                            <Button type="button" onClick={confirmDeleteWarehouse} className="flex-1 rounded-xl bg-rose-600 font-bold hover:bg-rose-700">Delete warehouse</Button>
                            <Button type="button" variant="outline" onClick={() => setDeleteCandidate(null)} className="flex-1 rounded-xl font-bold">Cancel</Button>
                        </div>
                    </div>
                </div>
            ) : null}
        </div>
    );
}

function SellerShippingSettings({ state, saveShippingSettings, pendingAction }) {
    const settings = state.sellerOps?.shippingSettings;
    const savedMethods = settings?.methods || [];
    const availableMethods = settings?.availableMethods?.length ? settings.availableMethods : savedMethods;
    const processingOptions = settings?.processingTimeOptions?.length ? settings.processingTimeOptions : ['Instant', 'Same day', '1-2 Business Days', '3-5 Business Days', '5-7 Business Days'];
    const [form, setForm] = useState({ cod: false, processingTime: '1-2 Business Days', rows: [] });
    const [addingMethod, setAddingMethod] = useState(false);
    const [customDraft, setCustomDraft] = useState({ methodId: '', name: '', price: '', processingTime: '1-2 Business Days' });
    const [error, setError] = useState('');

    useEffect(() => {
        const rows = savedMethods.map((method, index) => {
            const methodId = Number(method.shippingMethodId || method.shipping_method_id || method.id);
            return {
                clientId: methodId > 0 ? `method-${methodId}` : `custom-${index}`,
                shippingMethodId: methodId,
                code: method.code || '',
                name: method.name || method.method_name || 'Shipping method',
                custom: methodId <= 0,
                suggestedFee: asNumber(method.suggestedFee ?? method.suggested_fee, 0),
                price: String(method.price ?? method.suggestedFee ?? method.suggested_fee ?? 0),
                processingTime: method.processingTime || method.processing_time_label || settings?.processingTimeLabel || '1-2 Business Days',
                enabled: Boolean(method.enabled ?? method.is_enabled ?? true),
                sortOrder: asNumber(method.sortOrder ?? method.sort_order, (index + 1) * 10),
            };
        });
        setForm({
            cod: Boolean(settings?.cashOnDeliveryEnabled),
            processingTime: settings?.processingTimeLabel || '1-2 Business Days',
            rows,
        });
        setError('');
        setAddingMethod(false);
        setCustomDraft({ methodId: '', name: '', price: '', processingTime: settings?.processingTimeLabel || '1-2 Business Days' });
    }, [settings]);

    const selectedMethodIds = new Set(form.rows.map((row) => Number(row.shippingMethodId)).filter((id) => id > 0));
    const addMethodOptions = [
        ...availableMethods
            .filter((method) => !selectedMethodIds.has(Number(method.id || method.shippingMethodId || method.shipping_method_id)))
            .map((method) => ({
                value: String(method.id || method.shippingMethodId || method.shipping_method_id),
                label: method.name || method.method_name || 'Shipping method',
            })),
        { value: '__custom__', label: 'Custom shipping method' },
    ];

    const updateRow = (clientId, patch) => {
        setForm((current) => ({
            ...current,
            rows: current.rows.map((row) => row.clientId === clientId ? { ...row, ...patch } : row),
        }));
    };

    const addCustomMethod = () => {
        if (!customDraft.methodId) {
            setError('Select a shipping method first.');
            return;
        }
        const stamp = Date.now();
        const selectedMethod = availableMethods.find((method) => String(method.id || method.shippingMethodId || method.shipping_method_id) === String(customDraft.methodId));
        const isCustom = customDraft.methodId === '__custom__';
        const name = isCustom ? customDraft.name.trim() : (selectedMethod?.name || selectedMethod?.method_name || '');
        if (!name) {
            setError('Add a shipping method name first.');
            return;
        }
        setForm((current) => ({
            ...current,
            rows: [
                ...current.rows,
                {
                    clientId: isCustom ? `custom-${stamp}` : `method-${customDraft.methodId}`,
                    shippingMethodId: isCustom ? null : Number(customDraft.methodId),
                    code: isCustom ? 'custom' : (selectedMethod?.code || ''),
                    name,
                    custom: isCustom,
                    suggestedFee: isCustom ? 0 : asNumber(selectedMethod?.suggestedFee ?? selectedMethod?.suggested_fee, 0),
                    price: String(customDraft.price || selectedMethod?.suggestedFee || selectedMethod?.suggested_fee || 0),
                    processingTime: customDraft.processingTime || selectedMethod?.processingTime || selectedMethod?.processing_time_label || current.processingTime || '1-2 Business Days',
                    enabled: true,
                    sortOrder: (current.rows.length + 1) * 10,
                },
            ],
        }));
        setAddingMethod(false);
        setCustomDraft({ methodId: '', name: '', price: '', processingTime: form.processingTime || '1-2 Business Days' });
        setError('');
    };

    const submit = async (event) => {
        event.preventDefault();
        const enabledRows = form.rows.filter((row) => row.enabled);
        if (!enabledRows.length) {
            setError('Enable at least one shipping method.');
            return;
        }
        if (enabledRows.some((row) => row.custom && !row.name.trim())) {
            setError('Add a method name for every custom shipping method.');
            return;
        }
        const invalidRow = enabledRows.find((row) => String(row.price).trim() === '' || asNumber(row.price, -1) < 0 || !row.processingTime);
        if (invalidRow) {
            setError('Every enabled method needs a valid fee and processing time.');
            return;
        }
        setError('');
        await saveShippingSettings({
            cash_on_delivery_enabled: form.cod,
            processing_time_label: form.processingTime,
            shipping_methods: form.rows.map((row, index) => ({
                shipping_method_id: row.shippingMethodId,
                method_name: row.name,
                price: asNumber(row.price, 0),
                processing_time_label: row.processingTime,
                is_enabled: row.enabled,
                sort_order: row.sortOrder || ((index + 1) * 10),
            })),
        });
    };

    const isSaving = pendingAction === 'seller:shipping';

    return (
        <form onSubmit={submit} className="grid gap-5">
            <Panel title="Shipping settings" icon={Truck}>
                <div className="grid gap-3 md:grid-cols-2 xl:grid-cols-4">
                    <Stat label={settings?.insideDhakaLabel || 'Primary zone'} value={money(settings?.insideDhakaFee || 0)} hint="First enabled method" icon={MapPin} />
                    <Stat label={settings?.outsideDhakaLabel || 'Secondary zone'} value={money(settings?.outsideDhakaFee || 0)} hint="Second enabled method" icon={Truck} />
                    <Stat label="COD" value={form.cod ? 'Enabled' : 'Disabled'} hint="Cash on delivery" icon={CreditCard} />
                    <Stat label="Processing" value={form.processingTime || 'Not set'} hint="Default handling time" icon={Clock} />
                </div>

                <div className="mt-5 grid gap-5 lg:grid-cols-[1fr_280px]">
                    <div className="rounded-lg border border-slate-200/80 bg-slate-50/60 p-4 shadow-sm">
                        <div className="flex flex-wrap items-center justify-between gap-3">
                            <div>
                                <h2 className="text-base font-extrabold text-slate-950">Delivery methods</h2>
                                <p className="mt-1 text-sm font-medium text-slate-500">Enable the methods sellers can offer and set seller-specific fees.</p>
                            </div>
                            <div className="flex items-center gap-2">
                                <Badge variant={form.rows.filter((row) => row.enabled).length ? 'success' : 'warning'}>{form.rows.filter((row) => row.enabled).length} active</Badge>
                                <Button type="button" variant="outline" size="sm" onClick={() => setAddingMethod(true)}><Plus className="size-4" /> Add more</Button>
                            </div>
                        </div>

                        <div className="mt-4 grid gap-4 xl:grid-cols-2">
                            {form.rows.length ? form.rows.map((method) => (
                                <div key={method.clientId} className={cn('rounded-lg border bg-white p-4 shadow-[0_12px_28px_-24px_rgba(15,23,42,0.45)] transition', method.enabled ? 'border-slate-300 ring-1 ring-cyan-100/80' : 'border-slate-200 opacity-80')}>
                                    <div className="grid min-h-[58px] grid-cols-[1fr_auto] items-start gap-3">
                                        <div>
                                            <p className="font-extrabold text-slate-950">{method.name}</p>
                                            <p className="mt-1 text-xs font-bold uppercase tracking-wide text-slate-400">{method.code || 'shipping'} · Suggested {money(method.suggestedFee)}</p>
                                        </div>
                                        <div className="flex shrink-0 items-center gap-1.5">
                                            <label className="inline-flex h-9 cursor-pointer items-center gap-2 rounded-md border border-slate-200 bg-white px-2.5 text-sm font-bold text-slate-700 shadow-sm">
                                                <input type="checkbox" checked={method.enabled} onChange={(event) => updateRow(method.clientId, { enabled: event.target.checked })} className="size-4 rounded border-slate-300 text-cyan-700 focus:ring-cyan-500" />
                                                {method.enabled ? 'Enabled' : 'Enable'}
                                            </label>
                                            <Button type="button" variant="ghost" size="icon" className="size-9 text-slate-500 hover:text-rose-600" onClick={() => setForm((current) => ({ ...current, rows: current.rows.filter((row) => row.clientId !== method.clientId) }))} aria-label="Remove shipping method">
                                                <Trash2 className="size-4" />
                                            </Button>
                                        </div>
                                    </div>
                                    <div className="mt-4 grid items-end gap-3 sm:grid-cols-[120px_minmax(0,1fr)_88px]">
                                        <label className="grid gap-1 text-sm font-bold text-slate-600">
                                            Fee
                                            <Input type="number" min="0" step="1" value={method.price} onChange={(event) => updateRow(method.clientId, { price: event.target.value })} disabled={!method.enabled} className="h-11 bg-white" />
                                        </label>
                                        <KycSearchableSelect disabled={!method.enabled} label="Delivery time" value={method.processingTime} options={processingOptions} onChange={(value) => updateRow(method.clientId, { processingTime: value })} />
                                        <label className="grid gap-1 text-sm font-bold text-slate-600">
                                            Sort
                                            <Input type="number" min="0" step="1" value={method.sortOrder} onChange={(event) => updateRow(method.clientId, { sortOrder: asNumber(event.target.value, 0) })} disabled={!method.enabled} className="h-11 bg-white" />
                                        </label>
                                    </div>
                                </div>
                            )) : (
                                <div className="rounded-lg border border-dashed border-slate-300 bg-white p-8 text-sm font-semibold text-slate-500 xl:col-span-2">
                                    <div className="mx-auto flex max-w-md flex-col items-center text-center">
                                        <span className="flex size-12 items-center justify-center rounded-xl bg-cyan-50 text-cyan-700 ring-1 ring-cyan-100"><Truck className="size-5" /></span>
                                        <p className="mt-3 text-base font-extrabold text-slate-950">No shipping methods added</p>
                                        <p className="mt-1 text-sm font-semibold leading-6 text-slate-500">Click add shipping, choose a method, then it will appear here for pricing and processing rules.</p>
                                        <Button type="button" variant="outline" size="sm" onClick={() => setAddingMethod(true)} className="mt-4"><Plus className="size-4" /> Add shipping</Button>
                                    </div>
                                </div>
                            )}
                        </div>
                    </div>

                    <div className="grid content-start gap-4">
                        <div className="rounded-lg border border-slate-200/80 bg-white p-4 shadow-sm">
                            <h2 className="text-base font-extrabold text-slate-950">Default rules</h2>
                            <div className="mt-4 grid gap-4">
                                <KycSearchableSelect label="Default processing" value={form.processingTime} options={processingOptions} onChange={(value) => setForm((current) => ({ ...current, processingTime: value }))} />
                                <label className="flex items-center justify-between gap-4 rounded-lg border border-slate-200 p-3">
                                    <span>
                                        <span className="block text-sm font-extrabold text-slate-950">Cash on delivery</span>
                                        <span className="text-xs font-semibold text-slate-500">Allow buyers to pay on delivery.</span>
                                    </span>
                                    <input type="checkbox" checked={form.cod} onChange={(event) => setForm((current) => ({ ...current, cod: event.target.checked }))} className="size-5 rounded border-slate-300 text-cyan-700 focus:ring-cyan-500" />
                                </label>
                            </div>
                        </div>

                        {addingMethod ? (
                            <div className="rounded-lg border border-slate-200/80 bg-white p-4 shadow-sm ring-1 ring-cyan-100/70">
                                <div className="flex items-start justify-between gap-3">
                                    <div>
                                        <h2 className="text-base font-extrabold text-slate-950">Add shipping method</h2>
                                        <p className="mt-1 text-xs font-semibold text-slate-500">Create a seller delivery option.</p>
                                    </div>
                                    <Button type="button" variant="ghost" size="icon" onClick={() => setAddingMethod(false)} aria-label="Close add shipping method">
                                        <X className="size-4" />
                                    </Button>
                                </div>
                                <div className="mt-4 grid gap-3">
                                    <KycSearchableSelect
                                        label="Shipping method"
                                        value={customDraft.methodId}
                                        options={addMethodOptions}
                                        onChange={(value) => {
                                            const selected = availableMethods.find((method) => String(method.id || method.shippingMethodId || method.shipping_method_id) === String(value));
                                            setCustomDraft((current) => ({
                                                ...current,
                                                methodId: value,
                                                name: value === '__custom__' ? current.name : '',
                                                price: value === '__custom__' ? current.price : String(selected?.suggestedFee ?? selected?.suggested_fee ?? ''),
                                                processingTime: selected?.processingTime || selected?.processing_time_label || current.processingTime || form.processingTime,
                                            }));
                                        }}
                                    />
                                    {customDraft.methodId === '__custom__' ? (
                                    <label className="grid gap-1 text-sm font-bold text-slate-600">
                                        Method name
                                        <Input value={customDraft.name} onChange={(event) => setCustomDraft((current) => ({ ...current, name: event.target.value }))} placeholder="Express courier" className="h-11 bg-white" />
                                    </label>
                                    ) : null}
                                    <label className="grid gap-1 text-sm font-bold text-slate-600">
                                        Fee
                                        <Input type="number" min="0" step="1" value={customDraft.price} onChange={(event) => setCustomDraft((current) => ({ ...current, price: event.target.value }))} placeholder="120" className="h-11 bg-white" />
                                    </label>
                                    <KycSearchableSelect label="Processing time" value={customDraft.processingTime} options={processingOptions} onChange={(value) => setCustomDraft((current) => ({ ...current, processingTime: value }))} />
                                    <Button type="button" onClick={addCustomMethod} className="h-10 justify-center"><Plus className="size-4" /> Add method</Button>
                                </div>
                            </div>
                        ) : null}

                        {error ? <div className="rounded-lg border border-rose-200 bg-rose-50 p-3 text-sm font-bold text-rose-700">{error}</div> : null}
                        <Button type="submit" disabled={isSaving || !form.rows.length} className="h-11 justify-center">
                            {isSaving ? 'Saving...' : 'Save shipping settings'}
                        </Button>
                    </div>
                </div>
            </Panel>
        </form>
    );
}

function SellerReviews({ state }) {
    const reviews = state.sellerOps?.reviews || [];
    const [query, setQuery] = useState('');
    const [ratingFilter, setRatingFilter] = useState('all');
    const [visibleCount, setVisibleCount] = useState(5);
    const total = reviews.length;
    const averageNumber = total ? reviews.reduce((sum, item) => sum + asNumber(item.rating), 0) / total : 0;
    const average = averageNumber.toFixed(1);
    const buckets = [5, 4, 3, 2, 1].map((rating) => ({
        rating,
        count: reviews.filter((item) => Math.round(asNumber(item.rating)) === rating).length,
    }));
    const normalizedQuery = query.trim().toLowerCase();
    const filteredReviews = reviews.filter((review) => {
        const matchesQuery = !normalizedQuery || [review.product, review.buyer, review.comment].some((value) => String(value || '').toLowerCase().includes(normalizedQuery));
        const matchesRating = ratingFilter === 'all' || Math.round(asNumber(review.rating)) === Number(ratingFilter);
        return matchesQuery && matchesRating;
    });
    const visibleReviews = filteredReviews.slice(0, visibleCount);
    const filterLabel = ratingFilter === 'all' ? 'Filter' : `${ratingFilter} Star`;

    return (
        <section className="min-h-[calc(100vh-7rem)] bg-slate-50 px-1 pb-8 text-slate-950">
            <div className="mb-5">
                <h1 className="text-3xl font-extrabold leading-none text-slate-950">Vendor Reputation</h1>
                <p className="mt-1.5 text-sm font-semibold text-slate-500">Verified reviews from completed escrow transactions.</p>
            </div>

            <div className="grid items-start gap-6 xl:grid-cols-[346px_minmax(0,1fr)]">
                <aside className="rounded-[20px] border border-slate-200 bg-white p-6 shadow-[0_14px_34px_-30px_rgba(15,23,42,0.5)]">
                    <div className="flex items-center gap-3">
                        <span className="flex size-9 items-center justify-center rounded-xl bg-indigo-50 text-indigo-600">
                            <Star className="size-[18px] fill-current" />
                        </span>
                        <h2 className="text-base font-extrabold text-slate-950">Review Score</h2>
                    </div>

                    <div className="mt-6">
                        <p className="text-[56px] font-black leading-none text-slate-950">{average}</p>
                        <div className="mt-2.5 flex flex-wrap items-center gap-2">
                            <ReviewStars rating={averageNumber} />
                            <span className="text-sm font-bold text-slate-500">Based on {total} review{total === 1 ? '' : 's'}</span>
                        </div>
                    </div>

                    <div className="mt-7 grid gap-3">
                        {buckets.map(({ rating, count }) => {
                            const width = total ? `${Math.round((count / total) * 100)}%` : '0%';
                            return (
                                <div key={rating} className="grid grid-cols-[34px_1fr_18px] items-center gap-3 text-sm font-extrabold text-slate-600">
                                    <span className="inline-flex items-center gap-1">{rating}<Star className="size-3.5 fill-current" /></span>
                                    <span className="h-2 overflow-hidden rounded-full bg-slate-100">
                                        <span className="block h-full rounded-full bg-amber-400" style={{ width }} />
                                    </span>
                                    <span className="text-right text-xs font-bold text-slate-400">{count}</span>
                                </div>
                            );
                        })}
                    </div>
                </aside>

                <div className="overflow-hidden rounded-[20px] border border-slate-200 bg-white shadow-[0_14px_34px_-32px_rgba(15,23,42,0.42)]">
                    <div className="flex flex-col gap-3 border-b border-slate-100 px-6 py-5 md:flex-row md:items-center md:justify-between">
                        <div className="flex items-center gap-3">
                            <span className="flex size-9 items-center justify-center rounded-xl bg-emerald-50 text-emerald-600">
                                <MessageSquareText className="size-[18px]" />
                            </span>
                            <h2 className="text-base font-extrabold text-slate-950">All Reviews</h2>
                        </div>

                        <div className="flex flex-col gap-3 sm:flex-row">
                            <label className="relative block sm:w-64">
                                <Search className="pointer-events-none absolute left-3 top-1/2 size-4 -translate-y-1/2 text-slate-400" />
                                <Input value={query} onChange={(event) => { setQuery(event.target.value); setVisibleCount(5); }} placeholder="Search reviews..." className="h-10 rounded-lg border-slate-200 bg-white pl-9 text-sm font-semibold" />
                            </label>
                            <select value={ratingFilter} onChange={(event) => { setRatingFilter(event.target.value); setVisibleCount(5); }} className="h-10 rounded-lg border border-slate-200 bg-white px-3 text-sm font-extrabold text-slate-700 outline-none focus:border-indigo-300 focus:ring-2 focus:ring-indigo-100">
                                <option value="all">{filterLabel}</option>
                                {[5, 4, 3, 2, 1].map((rating) => <option key={rating} value={rating}>{rating} Star</option>)}
                            </select>
                        </div>
                    </div>

                    <div className="grid gap-0">
                        {visibleReviews.length ? visibleReviews.map((review, index) => (
                            <article key={review.id} className={cn('relative mx-2 rounded-2xl px-6 py-5 transition', index === 3 ? 'bg-slate-50' : 'bg-white', index > 0 && 'border-t border-slate-100')}>
                                <div className="flex items-start justify-between gap-4">
                                    <div className="min-w-0">
                                        <h3 className={cn('truncate text-base font-extrabold', index === 3 ? 'text-indigo-600' : 'text-slate-950')}>{review.product || 'Marketplace listing'}</h3>
                                        <div className="mt-1.5 flex flex-wrap items-center gap-2 text-sm font-semibold text-slate-600">
                                            <span>{review.buyer || 'Buyer'}</span>
                                            <span className="text-slate-300">•</span>
                                            <span>{review.createdAt || 'Recent'}</span>
                                            <span className="text-slate-300">•</span>
                                            <span className="inline-flex items-center gap-1 rounded-full bg-emerald-50 px-2 py-0.5 text-xs font-extrabold text-emerald-600">
                                                <Check className="size-3" /> Verified
                                            </span>
                                        </div>
                                    </div>
                                    <span className="inline-flex shrink-0 items-center gap-1 rounded-lg border border-amber-200 bg-amber-50 px-2.5 py-1.5 text-sm font-extrabold text-amber-600">
                                        <Star className="size-4 fill-current" /> {review.rating}
                                    </span>
                                </div>
                                <p className="mt-4 text-sm font-medium leading-6 text-slate-700">{review.comment || 'No written comment.'}</p>
                                {review.sellerReply ? (
                                    <div className="mt-3 rounded-xl border border-slate-100 bg-slate-50 p-3 text-sm text-slate-600">
                                        <span className="font-extrabold text-slate-800">Seller reply:</span> {review.sellerReply}
                                    </div>
                                ) : null}
                                <div className="mt-4 flex items-center justify-between">
                                    <button type="button" className={cn('inline-flex items-center gap-1.5 rounded-full px-2.5 py-1.5 text-xs font-extrabold', index === 3 ? 'bg-indigo-50 text-indigo-600' : 'bg-slate-100 text-slate-500')}>
                                        <ThumbsUp className="size-3.5" /> Helpful ({asNumber(review.helpfulCount, 0)})
                                    </button>
                                </div>
                            </article>
                        )) : (
                            <div className="m-5 rounded-xl bg-slate-50 p-8 text-center text-sm font-bold text-slate-500">No reviews match the current filters.</div>
                        )}
                    </div>

                    {filteredReviews.length > visibleReviews.length ? (
                        <div className="border-t border-slate-100 p-5 text-center">
                            <button type="button" onClick={() => setVisibleCount((count) => count + 5)} className="text-sm font-extrabold text-indigo-600 hover:text-indigo-700">
                                Load More Reviews
                            </button>
                        </div>
                    ) : null}
                </div>
            </div>
        </section>
    );
}

function ReviewStars({ rating }) {
    return (
        <span className="inline-flex items-center gap-0.5 text-amber-400" aria-label={`${rating.toFixed(1)} out of 5`}>
            {[1, 2, 3, 4, 5].map((star) => (
                <Star key={star} className={cn('size-4', star <= Math.round(rating) ? 'fill-current' : 'fill-slate-200 text-slate-200')} />
            ))}
        </span>
    );
}

function SellerNotifications({ state }) {
    const notifications = state.sellerOps?.notifications || [];
    return (
        <Panel title="Seller notifications" icon={Bell}>
            <div className="grid gap-3">
                {notifications.length ? notifications.map((item) => (
                    <div key={item.id} className={cn('rounded-lg border p-3', item.read ? 'border-slate-200 bg-white' : 'border-indigo-200 bg-indigo-50')}>
                        <div className="flex flex-wrap items-center justify-between gap-3">
                            <p className="font-extrabold text-slate-950">{item.title}</p>
                            <Badge variant={item.read ? 'secondary' : 'success'}>{item.read ? 'Read' : 'Unread'}</Badge>
                        </div>
                        <p className="mt-1 text-sm font-medium leading-6 text-slate-600">{item.body || item.kind}</p>
                        <p className="mt-2 text-xs font-bold uppercase tracking-wide text-slate-400">{item.time || 'Recent'}</p>
                    </div>
                )) : <p className="rounded-lg bg-slate-50 p-5 text-sm font-semibold text-slate-500">No seller notifications yet.</p>}
            </div>
        </Panel>
    );
}

function SellerPayoutMethods({ state, savePayoutMethod, deletePayoutMethod, pendingAction }) {
    const methods = state.sellerOps?.payoutMethods || [];
    const [form, setForm] = useState({
        method_type: 'bkash',
        account_name: '',
        account_number: '',
        bank_name: '',
        branch_name: '',
        routing_number: '',
        account_type_label: 'Personal',
        is_default: methods.length === 0,
    });
    const [error, setError] = useState('');
    const methodOptions = [
        ['bkash', 'bKash', 'Mobile wallet settlement'],
        ['nagad', 'Nagad', 'Mobile wallet settlement'],
        ['bank_transfer', 'Bank', 'Bank transfer settlement'],
    ];
    const isBank = form.method_type === 'bank_transfer';
    const isSaving = pendingAction === 'seller:payout-method';

    const update = (key, value) => {
        setError('');
        setForm((current) => ({ ...current, [key]: value }));
    };

    const submit = async (event) => {
        event.preventDefault();
        const required = [
            ['account_name', 'Account holder name is required.'],
            ['account_number', isBank ? 'Bank account number is required.' : 'Wallet number is required.'],
        ];
        if (isBank) {
            required.push(['bank_name', 'Bank name is required.'], ['branch_name', 'Branch name is required.']);
        }
        const missing = required.find(([key]) => !String(form[key] || '').trim());
        if (missing) {
            setError(missing[1]);
            return;
        }
        await savePayoutMethod(form);
        setForm((current) => ({
            ...current,
            account_name: '',
            account_number: '',
            bank_name: '',
            branch_name: '',
            routing_number: '',
            account_type_label: 'Personal',
            is_default: methods.length === 0,
        }));
    };

    return (
        <section className="grid gap-5 xl:grid-cols-[minmax(0,1fr)_420px]">
            <Panel title="Connected payout methods" icon={CreditCard}>
                <div className="grid gap-4 md:grid-cols-2">
                    {methods.length ? methods.map((method) => {
                        const methodType = method.methodType || method.method_type || method.type;
                        const label = method.label || payoutMethodLabel(methodType);
                        const account = method.accountNumberMasked || method.account_number_masked || method.account || 'Masked account reference';
                        const displayAccount = String(account).replace(/\*/g, '•').replace(/(.{4})/g, '$1 ').trim();
                        const detail = methodType === 'bank_transfer'
                            ? [method.bankName || method.provider, method.branchName].filter(Boolean).join(' · ')
                            : [method.accountTypeLabel, method.provider].filter(Boolean).join(' · ');
                        const MethodIcon = methodType === 'bank_transfer' ? Building2 : Smartphone;

                        return (
                            <div key={method.id} className="rounded-2xl border border-slate-200 bg-white px-5 py-4 shadow-sm">
                                <div className="flex items-center gap-4">
                                    <span className="flex size-12 shrink-0 items-center justify-center rounded-xl bg-slate-50 text-slate-500 ring-1 ring-slate-200">
                                        <MethodIcon className="size-6" />
                                    </span>
                                    <div className="min-w-0">
                                        <div className="flex flex-wrap items-center gap-3">
                                            <p className="text-base font-extrabold text-slate-950">{label}</p>
                                            <span className={cn('rounded-md px-2.5 py-1 text-[10px] font-black uppercase tracking-[0.16em]', method.default || method.isDefault ? 'bg-emerald-50 text-emerald-700 ring-1 ring-emerald-200' : 'bg-slate-100 text-slate-600 ring-1 ring-slate-200')}>
                                                {method.default || method.isDefault ? 'Default' : method.status}
                                            </span>
                                        </div>
                                        <p className="mt-1 font-mono text-sm font-black tracking-[0.28em] text-slate-800">{displayAccount}</p>
                                        <p className="mt-1 truncate text-xs font-bold text-slate-500">
                                            {method.accountName || method.account_name || 'Account holder'}
                                            {detail ? <><span className="px-2 text-slate-300">•</span>{detail}</> : null}
                                        </p>
                                    </div>
                                    <Button type="button" variant="ghost" size="icon" className="ml-auto shrink-0 text-slate-400 hover:text-rose-600" disabled={pendingAction === `seller:payout-method:${method.id}`} onClick={() => deletePayoutMethod(method.id)} aria-label="Remove payout method">
                                        <Trash2 className="size-4" />
                                    </Button>
                                </div>
                            </div>
                        );
                    }) : (
                        <div className="rounded-lg border border-dashed border-slate-300 bg-slate-50 p-6 text-sm font-semibold text-slate-500 md:col-span-2">
                            No payout methods saved yet. Add bKash, Nagad, or a bank account from this page.
                        </div>
                    )}
                </div>
            </Panel>

            <form onSubmit={submit}>
                <Panel title="Add payout method" icon={Plus}>
                    <div className="grid gap-4">
                        <div className="grid grid-cols-3 gap-2">
                            {methodOptions.map(([value, label, hint]) => (
                                <button
                                    key={value}
                                    type="button"
                                    onClick={() => update('method_type', value)}
                                    className={cn('rounded-lg border p-3 text-left transition', form.method_type === value ? 'border-indigo-300 bg-indigo-50 text-indigo-700 ring-2 ring-indigo-100' : 'border-slate-200 bg-white text-slate-700 hover:border-slate-300')}
                                >
                                    <span className="block text-sm font-extrabold">{label}</span>
                                    <span className="mt-1 block text-xs font-semibold text-slate-500">{hint}</span>
                                </button>
                            ))}
                        </div>

                        <label className="grid gap-1.5 text-sm font-bold text-slate-600">
                            Account holder name
                            <Input value={form.account_name} onChange={(event) => update('account_name', event.target.value)} placeholder="Name on account" className="h-11 bg-white" />
                        </label>

                        {isBank ? (
                            <>
                                <label className="grid gap-1.5 text-sm font-bold text-slate-600">
                                    Bank name
                                    <Input value={form.bank_name} onChange={(event) => update('bank_name', event.target.value)} placeholder="Bank name" className="h-11 bg-white" />
                                </label>
                                <label className="grid gap-1.5 text-sm font-bold text-slate-600">
                                    Bank account number
                                    <Input value={form.account_number} onChange={(event) => update('account_number', event.target.value)} placeholder="Account number" className="h-11 bg-white" />
                                </label>
                                <div className="grid gap-3 sm:grid-cols-2">
                                    <label className="grid gap-1.5 text-sm font-bold text-slate-600">
                                        Branch name
                                        <Input value={form.branch_name} onChange={(event) => update('branch_name', event.target.value)} placeholder="Branch" className="h-11 bg-white" />
                                    </label>
                                    <label className="grid gap-1.5 text-sm font-bold text-slate-600">
                                        Routing number
                                        <Input value={form.routing_number} onChange={(event) => update('routing_number', event.target.value)} placeholder="Optional" className="h-11 bg-white" />
                                    </label>
                                </div>
                            </>
                        ) : (
                            <>
                                <label className="grid gap-1.5 text-sm font-bold text-slate-600">
                                    {payoutMethodLabel(form.method_type)} wallet number
                                    <Input value={form.account_number} onChange={(event) => update('account_number', event.target.value)} placeholder="Wallet number" className="h-11 bg-white" />
                                </label>
                                <label className="grid gap-1.5 text-sm font-bold text-slate-600">
                                    Wallet account type
                                    <select value={form.account_type_label} onChange={(event) => update('account_type_label', event.target.value)} className="h-11 rounded-md border border-slate-200 bg-white px-3 text-sm font-semibold text-slate-700 outline-none focus:border-indigo-300 focus:ring-2 focus:ring-indigo-100">
                                        <option>Personal</option>
                                        <option>Agent</option>
                                        <option>Merchant</option>
                                    </select>
                                </label>
                            </>
                        )}

                        <label className="flex items-center justify-between gap-4 rounded-lg border border-slate-200 bg-slate-50 p-3">
                            <span>
                                <span className="block text-sm font-extrabold text-slate-950">Set as default</span>
                                <span className="text-xs font-semibold text-slate-500">Use this destination first for withdrawal requests.</span>
                            </span>
                            <input type="checkbox" checked={form.is_default} onChange={(event) => update('is_default', event.target.checked)} className="size-5 rounded border-slate-300 text-indigo-600 focus:ring-indigo-500" />
                        </label>

                        {error ? <p className="rounded-lg border border-rose-200 bg-rose-50 p-3 text-sm font-bold text-rose-700">{error}</p> : null}

                        <Button type="submit" disabled={isSaving} className="h-11 justify-center">
                            {isSaving ? 'Saving...' : `Save ${payoutMethodLabel(form.method_type)} method`}
                        </Button>
                    </div>
                </Panel>
            </form>
        </section>
    );
}

function payoutMethodLabel(type) {
    if (type === 'bkash') return 'bKash';
    if (type === 'nagad') return 'Nagad';
    return 'Bank Transfer';
}

function SellerReturns({ state }) {
    return <SellerQueue title="Returns & refund queue" icon={ClipboardCheck} items={state.sellerOps?.returns || []} empty="No return requests are waiting for seller action." render={(item) => (
        <>
            <p className="font-extrabold text-slate-950">{item.code}</p>
            <p className="mt-1 text-sm font-semibold text-slate-500">{item.reason || 'Return request'} · SLA {item.dueAt || 'not set'}</p>
            <div className="mt-3 flex gap-2"><Badge variant="secondary">{item.status}</Badge><Badge variant="secondary">{item.refundStatus}</Badge></div>
        </>
    )} />;
}

function SellerDisputes({ state }) {
    const disputes = state.sellerOps?.disputes || [];

    return (
        <section className="overflow-hidden rounded-[20px] border border-slate-200 bg-white shadow-[0_18px_50px_-42px_rgba(15,23,42,0.5)]">
            <div className="flex items-center gap-3 border-b border-slate-100 bg-white px-6 py-5">
                <span className="flex size-9 items-center justify-center rounded-xl bg-rose-50 text-rose-500">
                    <ShieldCheck className="size-[18px]" />
                </span>
                <h1 className="text-lg font-extrabold text-slate-950">Disputes</h1>
            </div>

            <div className="grid gap-4 p-5 md:grid-cols-2 xl:grid-cols-3">
                {disputes.length ? disputes.map((item) => {
                    const status = String(item.status || 'opened');

                    return (
                        <article key={item.id} className="rounded-[14px] border border-slate-200 bg-white px-5 py-5 shadow-[0_10px_30px_-30px_rgba(15,23,42,0.38)]">
                            <h2 className="text-lg font-extrabold tracking-tight text-slate-950">{item.order}</h2>
                            <p className="mt-1.5 flex flex-wrap items-center gap-2 text-sm font-bold text-slate-500">
                                <span>{item.reason || 'Buyer dispute'}</span>
                                <span className="text-slate-300">•</span>
                                <span>{item.openedAt || 'Recent'}</span>
                            </p>
                            <span className={cn('mt-4 inline-flex rounded-md border px-3 py-1.5 text-[10px] font-black uppercase tracking-[0.18em]', disputeStatusClass(status))}>
                                {disputeStatusLabel(status)}
                            </span>
                        </article>
                    );
                }) : (
                    <div className="rounded-lg border border-dashed border-slate-300 bg-slate-50 p-6 text-sm font-semibold text-slate-500 md:col-span-2 xl:col-span-3">
                        No active seller disputes.
                    </div>
                )}
            </div>
        </section>
    );
}

function disputeStatusLabel(status) {
    return String(status || 'opened').replace(/_/g, ' ');
}

function disputeStatusClass(status) {
    const normalized = String(status || '').toLowerCase();
    if (normalized.includes('resolved')) return 'border-emerald-200 bg-emerald-50 text-emerald-700';
    if (normalized.includes('escalated')) return 'border-rose-200 bg-rose-50 text-rose-700';
    if (normalized.includes('review')) return 'border-indigo-200 bg-indigo-50 text-indigo-700';
    return 'border-amber-200 bg-amber-50 text-amber-700';
}

function SellerKyc({ state, uploadKycDocument, saveKycDraft, submitKyc }) {
    const kyc = state.sellerOps?.kyc || { status: 'not_submitted', documents: [], requiredDocuments: [] };
    const [step, setStep] = useState(() => {
        if (kyc.status === 'verified' || kyc.status === 'approved') return 'success';
        if (kyc.status === 'rejected' || kyc.status === 'resubmission_required') return 'resubmit';
        if (kyc.status === 'third_party_pending') return 'third-party';
        return 'personal';
    });
    const [form, setForm] = useState(() => ({
        personal: {
            full_name: kyc.personal?.full_name || state.user?.name || '',
            date_of_birth: kyc.personal?.date_of_birth || '',
            nationality: kyc.personal?.nationality || 'Bangladesh',
            identity_document_type: kyc.personal?.identity_document_type || 'nid',
            id_number: kyc.personal?.id_number || '',
            phone: kyc.personal?.phone || state.business?.phone || '',
        },
        business: {
            legal_name: kyc.business?.legal_name || state.business?.name || '',
            registration_number: kyc.business?.registration_number || '',
            business_type: kyc.business?.business_type || 'Marketplace seller',
            tax_vat_number: kyc.business?.tax_vat_number || '',
            website: kyc.business?.website || '',
        },
        bank: {
            bank_name: kyc.bank?.bank_name || '',
            account_name: kyc.bank?.account_name || '',
            account_number: kyc.bank?.account_number || '',
            routing_number: kyc.bank?.routing_number || '',
            mobile_banking_provider: kyc.bank?.mobile_banking_provider || '',
            mobile_banking_number: kyc.bank?.mobile_banking_number || '',
        },
        address: {
            address_line: kyc.address?.address_line || state.business?.address || '',
            city: kyc.address?.city || state.user?.city || '',
            region: kyc.address?.region || '',
            postal_code: kyc.address?.postal_code || '',
            country: kyc.address?.country || 'Bangladesh',
        },
    }));
    useEffect(() => {
        setForm((current) => ({
            personal: { ...current.personal, ...kyc.personal },
            business: { ...current.business, ...kyc.business },
            bank: { ...current.bank, ...kyc.bank },
            address: { ...current.address, ...kyc.address },
        }));
    }, [kyc.id]);
    const update = (section, key, value) => setForm((current) => ({ ...current, [section]: { ...current[section], [key]: value } }));
    const documents = kyc.documents || [];
    const docByType = Object.fromEntries(documents.map((doc) => [doc.docType, doc]));
    const identityDocumentType = form.personal.identity_document_type || 'nid';
    const identityDocGroups = {
        nid: {
            label: 'National ID (NID)',
            hint: 'Upload front, back, and a selfie holding the NID.',
            docs: [
                ['nid_front', 'NID front side'],
                ['nid_back', 'NID back side'],
                ['nid_selfie', 'Selfie with NID'],
            ],
        },
        driving_license: {
            label: 'Driving License',
            hint: 'Upload front, back, and a selfie holding the license.',
            docs: [
                ['license_front', 'License front side'],
                ['license_back', 'License back side'],
                ['license_selfie', 'Selfie with license'],
            ],
        },
        passport: {
            label: 'Passport',
            hint: 'Upload passport identity page and a selfie holding the passport.',
            docs: [
                ['passport_page', 'Passport identity page'],
                ['passport_selfie', 'Selfie with passport'],
            ],
        },
    };
    const activeIdentityDocs = identityDocGroups[identityDocumentType]?.docs || identityDocGroups.nid.docs;
    const businessDocs = [
        ['trade_license', 'Trade license'],
        ['tax_vat', 'Tax / VAT certificate'],
        ['bank_account_proof', 'Bank account proof'],
        ['address_verification', 'Address verification'],
    ];
    const optionalDocs = [['face_verification', 'Additional face verification']];
    const requiredDocs = [...activeIdentityDocs.map(([type]) => type), ...businessDocs.map(([type]) => type)];
    const missingDocs = requiredDocs.filter((type) => !docByType[type]);
    const steps = [
        ['overview', 'Overview', ShieldCheck],
        ['personal', 'Personal', User],
        ['business', 'Business', Store],
        ['documents', 'Documents', Upload],
        ['bank', 'Bank', CreditCard],
        ['third-party', 'Provider', BadgeCheck],
        ['timeline', 'Timeline', Clock],
    ];
    const locked = ['submitted', 'under_review', 'third_party_pending', 'verified', 'approved'].includes(kyc.status);
    const save = () => saveKycDraft(form);
    const submit = () => submitKyc(form);

    return (
        <div className="space-y-5">
            <section className="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
                <div className="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
                    <div>
                        <p className="text-xs font-extrabold uppercase tracking-wide text-indigo-600">Seller KYC verification</p>
                        <h1 className="mt-1 text-2xl font-extrabold tracking-tight text-slate-950">Identity, business, bank, and document verification</h1>
                        <p className="mt-2 text-sm font-semibold text-slate-500">Status: {kyc.statusLabel || kyc.status || 'Not submitted'}{kyc.expiresAt ? ` · Expires ${kyc.expiresAt}` : ''}</p>
                    </div>
                    <Badge variant={['verified', 'approved'].includes(kyc.status) ? 'success' : kyc.status === 'rejected' || kyc.status === 'resubmission_required' ? 'warning' : 'secondary'}>{kyc.statusLabel || kyc.status}</Badge>
                </div>
                <div className="mt-5 flex flex-wrap gap-2">
                    {steps.map(([key, label, Icon]) => (
                        <button key={key} type="button" onClick={() => setStep(key)} className={cn('inline-flex items-center gap-2 rounded-md px-3 py-2 text-sm font-bold transition', step === key ? 'bg-slate-950 text-white' : 'bg-slate-100 text-slate-600 hover:bg-slate-200')}>
                            <Icon className="size-4" />{label}
                        </button>
                    ))}
                </div>
            </section>

            {step === 'overview' ? (
                <section className="grid gap-5 lg:grid-cols-[1fr_360px]">
                    <Panel title="Verification readiness" icon={ShieldCheck}>
                        <div className="grid gap-3 md:grid-cols-3">
                            <Stat label="Documents" value={`${documents.length}/${requiredDocs.length}`} hint={missingDocs.length ? `${missingDocs.length} missing` : 'Complete'} icon={Upload} />
                            <Stat label="Provider" value={kyc.providerSessionId ? 'Session ready' : 'Not started'} hint="Flexible KYC provider" icon={BadgeCheck} />
                            <Stat label="Risk" value={kyc.riskLevel || 'Pending'} hint="Provider/admin result" icon={AlertCircle} />
                        </div>
                        {kyc.rejectionReason ? <p className="mt-5 rounded-lg border border-rose-200 bg-rose-50 p-4 text-sm font-semibold text-rose-700">{kyc.rejectionReason}</p> : null}
                    </Panel>
                    <Panel title="Business gates" icon={LockKeyhole}>
                        <div className="grid gap-3 text-sm font-semibold text-slate-600">
                            <p className="rounded-lg bg-slate-50 p-3">Withdrawals require verified KYC.</p>
                            <p className="rounded-lg bg-slate-50 p-3">Publishing products requires verified KYC.</p>
                            <p className="rounded-lg bg-slate-50 p-3">Rejected cases can be corrected and resubmitted.</p>
                        </div>
                    </Panel>
                </section>
            ) : null}

            {step === 'personal' ? <KycFormSection title="Personal information" icon={User} locked={locked} fields={[
                { section: 'personal', key: 'full_name', label: 'Full name', type: 'text' },
                { section: 'personal', key: 'date_of_birth', label: 'Date of birth', type: 'date' },
                { section: 'personal', key: 'nationality', label: 'Nationality', type: 'select', options: ['Bangladesh', 'India', 'Pakistan', 'Sri Lanka', 'Nepal', 'United Arab Emirates', 'United States', 'United Kingdom'] },
                { section: 'personal', key: 'identity_document_type', label: 'Identity document type', type: 'select', options: [
                    { value: 'nid', label: 'National ID (NID)' },
                    { value: 'driving_license', label: 'Driving License' },
                    { value: 'passport', label: 'Passport' },
                ] },
                { section: 'personal', key: 'id_number', label: 'National ID / Passport number', type: 'text' },
                { section: 'personal', key: 'phone', label: 'Phone', type: 'tel' },
            ]} form={form} update={update} /> : null}

            {step === 'business' ? <KycFormSection title="Business information" icon={Store} locked={locked} fields={[
                { section: 'business', key: 'legal_name', label: 'Legal business name', type: 'text' },
                { section: 'business', key: 'registration_number', label: 'Registration number', type: 'text' },
                { section: 'business', key: 'business_type', label: 'Business type', type: 'select', options: ['Marketplace seller', 'Sole proprietorship', 'Partnership', 'Private limited company', 'Public limited company', 'NGO / non-profit'] },
                { section: 'business', key: 'tax_vat_number', label: 'Tax / VAT number', type: 'text' },
                { section: 'business', key: 'website', label: 'Website', type: 'url' },
                { section: 'address', key: 'address_line', label: 'Business address', type: 'textarea' },
                { section: 'address', key: 'city', label: 'City', type: 'text' },
                { section: 'address', key: 'region', label: 'Region', type: 'text' },
                { section: 'address', key: 'postal_code', label: 'Postal code', type: 'text' },
                { section: 'address', key: 'country', label: 'Country', type: 'select', options: ['Bangladesh', 'India', 'Pakistan', 'Sri Lanka', 'Nepal', 'United Arab Emirates', 'United States', 'United Kingdom'] },
            ]} form={form} update={update} /> : null}

            {step === 'bank' ? <KycFormSection title="Bank verification" icon={CreditCard} locked={locked} fields={[
                { section: 'bank', key: 'bank_name', label: 'Bank name', type: 'select', options: ['BRAC Bank', 'Dutch-Bangla Bank', 'City Bank', 'Eastern Bank', 'Islami Bank Bangladesh', 'Standard Chartered', 'Prime Bank', 'Other'] },
                { section: 'bank', key: 'account_name', label: 'Account name', type: 'text' },
                { section: 'bank', key: 'account_number', label: 'Account number', type: 'text' },
                { section: 'bank', key: 'routing_number', label: 'Routing / branch code', type: 'text' },
                { section: 'bank', key: 'mobile_banking_provider', label: 'Mobile banking provider', type: 'select', options: ['None', 'bKash', 'Nagad', 'Rocket', 'Upay'] },
                { section: 'bank', key: 'mobile_banking_number', label: 'Mobile banking number', type: 'tel' },
            ]} form={form} update={update} /> : null}

            {step === 'documents' ? (
                <Panel title="Secure document upload" icon={Upload}>
                    <div className="mb-5 rounded-lg border border-slate-200 bg-slate-50 p-4">
                        <div className="grid gap-4 lg:grid-cols-[320px_1fr] lg:items-center">
                            <KycField
                                field={{
                                    section: 'personal',
                                    key: 'identity_document_type',
                                    label: 'Identity document type',
                                    type: 'select',
                                    options: [
                                        { value: 'nid', label: 'National ID (NID)' },
                                        { value: 'driving_license', label: 'Driving License' },
                                        { value: 'passport', label: 'Passport' },
                                    ],
                                }}
                                value={identityDocumentType}
                                locked={locked}
                                onChange={(value) => update('personal', 'identity_document_type', value)}
                            />
                            <div>
                                <p className="text-sm font-extrabold text-slate-950">{identityDocGroups[identityDocumentType]?.label || identityDocGroups.nid.label}</p>
                                <p className="mt-1 text-sm font-semibold leading-6 text-slate-500">{identityDocGroups[identityDocumentType]?.hint || identityDocGroups.nid.hint}</p>
                            </div>
                        </div>
                    </div>

                    <div className="mb-4 flex items-center justify-between gap-3">
                        <div>
                            <p className="text-sm font-extrabold text-slate-950">Identity verification</p>
                            <p className="mt-1 text-sm font-semibold text-slate-500">Only the document slots required for the selected identity type are shown.</p>
                        </div>
                        <Badge variant={activeIdentityDocs.every(([type]) => docByType[type]) ? 'success' : 'warning'}>{activeIdentityDocs.filter(([type]) => docByType[type]).length}/{activeIdentityDocs.length}</Badge>
                    </div>
                    <div className="grid gap-3 md:grid-cols-2 xl:grid-cols-3">
                        {activeIdentityDocs.map(([type, label]) => {
                            const doc = docByType[type];
                            return (
                                <div key={type} className="rounded-lg border border-slate-200 bg-slate-50 p-4">
                                    <div className="flex items-start justify-between gap-3">
                                        <div>
                                            <p className="font-extrabold text-slate-950">{label}</p>
                                            <p className="mt-1 text-xs font-bold uppercase tracking-wide text-slate-400">Required</p>
                                        </div>
                                        <Badge variant={doc ? 'success' : 'warning'}>{doc ? doc.status : 'Missing'}</Badge>
                                    </div>
                                    {doc ? (
                                        <div className="mt-3 rounded-md bg-white p-3 text-sm">
                                            <p className="line-clamp-1 font-bold text-slate-950">{doc.originalName}</p>
                                            <a className="mt-2 inline-flex text-sm font-bold text-indigo-600" href={doc.previewUrl} target="_blank" rel="noreferrer">Preview secure file</a>
                                        </div>
                                    ) : null}
                                    {!locked ? (
                                        <label className="mt-3 inline-flex h-10 cursor-pointer items-center gap-2 rounded-md bg-slate-950 px-4 text-sm font-bold text-white hover:bg-indigo-600">
                                            <Upload className="size-4" />Upload
                                            <input type="file" accept="image/*,.pdf" className="sr-only" onChange={(event) => uploadKycDocument(event.target.files?.[0], type)} />
                                        </label>
                                    ) : null}
                                </div>
                            );
                        })}
                    </div>

                    <div className="mb-4 mt-7 flex items-center justify-between gap-3">
                        <div>
                            <p className="text-sm font-extrabold text-slate-950">Business and account verification</p>
                            <p className="mt-1 text-sm font-semibold text-slate-500">These documents verify that payouts and business ownership match the seller profile.</p>
                        </div>
                        <Badge variant={businessDocs.every(([type]) => docByType[type]) ? 'success' : 'warning'}>{businessDocs.filter(([type]) => docByType[type]).length}/{businessDocs.length}</Badge>
                    </div>
                    <div className="grid gap-3 md:grid-cols-2 xl:grid-cols-4">
                        {[...businessDocs, ...optionalDocs].map(([type, label]) => {
                            const doc = docByType[type];
                            const required = businessDocs.some(([requiredType]) => requiredType === type);
                            return (
                                <div key={type} className="rounded-lg border border-slate-200 bg-white p-4">
                                    <div className="flex items-start justify-between gap-3">
                                        <div>
                                            <p className="font-extrabold text-slate-950">{label}</p>
                                            <p className="mt-1 text-xs font-bold uppercase tracking-wide text-slate-400">{required ? 'Required' : 'Optional'}</p>
                                        </div>
                                        <Badge variant={doc ? 'success' : required ? 'warning' : 'secondary'}>{doc ? doc.status : 'Missing'}</Badge>
                                    </div>
                                    {doc ? (
                                        <div className="mt-3 rounded-md bg-slate-50 p-3 text-sm">
                                            <p className="line-clamp-1 font-bold text-slate-950">{doc.originalName}</p>
                                            <a className="mt-2 inline-flex text-sm font-bold text-indigo-600" href={doc.previewUrl} target="_blank" rel="noreferrer">Preview secure file</a>
                                        </div>
                                    ) : null}
                                    {!locked ? (
                                        <label className="mt-3 inline-flex h-10 cursor-pointer items-center gap-2 rounded-md border border-slate-200 bg-slate-950 px-4 text-sm font-bold text-white hover:bg-indigo-600">
                                            <Upload className="size-4" />Upload
                                            <input type="file" accept="image/*,.pdf" className="sr-only" onChange={(event) => uploadKycDocument(event.target.files?.[0], type)} />
                                        </label>
                                    ) : null}
                                </div>
                            );
                        })}
                    </div>
                </Panel>
            ) : null}

            {step === 'third-party' ? (
                <Panel title="Third-party verification" icon={BadgeCheck}>
                    <div className="rounded-lg border border-slate-200 bg-slate-50 p-5">
                        <p className="font-extrabold text-slate-950">Provider-flexible verification session</p>
                        <p className="mt-2 text-sm font-semibold leading-6 text-slate-500">The backend creates a verification session through the active provider abstraction. Supported provider adapters include Stripe Identity, Sumsub, Onfido, Veriff, Persona, and Shufti Pro. This environment uses the internal provider adapter until live credentials are configured.</p>
                        {kyc.providerSessionUrl ? <Button asChild className="mt-4"><a href={kyc.providerSessionUrl}>Continue verification</a></Button> : <p className="mt-4 text-sm font-semibold text-amber-600">Submit the KYC application to create the provider session.</p>}
                    </div>
                </Panel>
            ) : null}

            {step === 'timeline' ? (
                <Panel title="KYC status timeline" icon={Clock}>
                    <div className="grid gap-3">
                        {(kyc.timeline || []).length ? kyc.timeline.map((item) => (
                            <div key={item.id} className="rounded-lg border border-slate-200 p-3">
                                <p className="font-extrabold text-slate-950">{item.to?.replace('_', ' ')}</p>
                                <p className="mt-1 text-sm font-semibold text-slate-500">{item.reason || 'status_update'} · {item.createdAt}</p>
                                {item.note ? <p className="mt-2 text-sm text-slate-600">{item.note}</p> : null}
                            </div>
                        )) : <p className="rounded-lg bg-slate-50 p-5 text-sm font-semibold text-slate-500">No KYC history yet.</p>}
                    </div>
                </Panel>
            ) : null}

            {step === 'resubmit' ? (
                <Panel title="Resubmission required" icon={AlertCircle}>
                    <p className="rounded-lg border border-rose-200 bg-rose-50 p-4 text-sm font-semibold text-rose-700">{kyc.rejectionReason || 'Admin requested corrections. Update the form and documents, then resubmit.'}</p>
                    <Button className="mt-4" onClick={() => setStep('personal')}>Update application</Button>
                </Panel>
            ) : null}

            {step === 'success' ? (
                <Panel title="Verification success" icon={BadgeCheck}>
                    <p className="rounded-lg bg-emerald-50 p-5 text-sm font-semibold text-emerald-700">Your seller KYC is verified. Product publishing and withdrawal workflows are unlocked.</p>
                </Panel>
            ) : null}

            {!['success'].includes(step) ? (
                <div className="sticky bottom-0 z-20 flex flex-wrap justify-end gap-2 border-t border-slate-200 bg-white/95 p-4 backdrop-blur">
                    <Button type="button" variant="outline" disabled={locked} onClick={save}>Save draft</Button>
                    <Button type="button" disabled={locked || missingDocs.length > 0} onClick={submit}>Submit for review</Button>
                </div>
            ) : null}
        </div>
    );
}

function KycFormSection({ title, icon: Icon, fields, form, update, locked }) {
    return (
        <Panel title={title} icon={Icon}>
            <div className="grid gap-3 md:grid-cols-2">
                {fields.map((field) => <KycField key={`${field.section}.${field.key}`} field={field} value={form[field.section]?.[field.key] || ''} locked={locked} onChange={(value) => update(field.section, field.key, value)} />)}
            </div>
        </Panel>
    );
}

function KycField({ field, value, locked, onChange }) {
    const commonClass = 'h-11 w-full rounded-md border border-slate-200 bg-slate-50 px-3 text-sm font-semibold text-slate-800 shadow-sm transition focus:border-indigo-300 focus:bg-white focus:outline-none focus:ring-2 focus:ring-indigo-100 disabled:cursor-not-allowed disabled:bg-slate-100 disabled:text-slate-400';
    return (
        <label className={cn('grid gap-1.5', field.type === 'textarea' && 'md:col-span-2')}>
            <span className="text-xs font-extrabold uppercase tracking-wide text-slate-500">{field.label}</span>
            {field.type === 'select' ? (
                <KycSearchableSelect disabled={locked} label={field.label} value={value} options={field.options || []} onChange={onChange} />
            ) : field.type === 'date' ? (
                <KycDatePicker disabled={locked} value={value} onChange={onChange} />
            ) : field.type === 'textarea' ? (
                <textarea disabled={locked} value={value} onChange={(event) => onChange(event.target.value)} rows={3} className={cn(commonClass, 'h-auto min-h-24 py-3')} />
            ) : (
                <Input disabled={locked} type={field.type || 'text'} value={value} onChange={(event) => onChange(event.target.value)} className={commonClass} />
            )}
        </label>
    );
}

function normalizeSelectOption(option) {
    return typeof option === 'string' ? { value: option, label: option } : option;
}

function KycSearchableSelect({ label, value, options, onChange, disabled }) {
    const [open, setOpen] = useState(false);
    const [search, setSearch] = useState('');
    const [menuStyle, setMenuStyle] = useState({
        position: 'fixed',
        left: '0px',
        top: '0px',
        width: '0px',
        maxHeight: '0px',
        visibility: 'hidden',
    });
    const rootRef = useRef(null);
    const triggerRef = useRef(null);
    const menuRef = useRef(null);
    const normalized = options.map(normalizeSelectOption);
    const selected = normalized.find((option) => option.value === value);
    const filtered = normalized.filter((option) => option.label.toLowerCase().includes(search.toLowerCase()) || String(option.value).toLowerCase().includes(search.toLowerCase()));

    useLayoutEffect(() => {
        if (!open) return undefined;
        const placeMenu = () => {
            const rect = triggerRef.current?.getBoundingClientRect();
            if (!rect) return;
            const viewportPadding = 12;
            const spaceBelow = window.innerHeight - rect.bottom - viewportPadding;
            const spaceAbove = rect.top - viewportPadding;
            const openUp = spaceBelow < 260 && spaceAbove > spaceBelow;
            const maxHeight = Math.max(180, Math.min(360, openUp ? spaceAbove - 8 : spaceBelow - 8));
            setMenuStyle({
                position: 'fixed',
                left: `${Math.min(Math.max(viewportPadding, rect.left), window.innerWidth - rect.width - viewportPadding)}px`,
                top: openUp ? 'auto' : `${rect.bottom + 8}px`,
                bottom: openUp ? `${window.innerHeight - rect.top + 8}px` : 'auto',
                width: `${rect.width}px`,
                maxHeight: `${maxHeight}px`,
                visibility: 'visible',
            });
        };
        placeMenu();
        requestAnimationFrame(placeMenu);
        const close = (event) => {
            if (
                rootRef.current
                && !rootRef.current.contains(event.target)
                && menuRef.current
                && !menuRef.current.contains(event.target)
            ) {
                setOpen(false);
            }
        };
        window.addEventListener('resize', placeMenu);
        window.addEventListener('scroll', placeMenu, true);
        document.addEventListener('mousedown', close);
        return () => {
            window.removeEventListener('resize', placeMenu);
            window.removeEventListener('scroll', placeMenu, true);
            document.removeEventListener('mousedown', close);
        };
    }, [open]);

    return (
        <div ref={rootRef} className="relative">
            <button
                ref={triggerRef}
                type="button"
                disabled={disabled}
                onClick={() => setOpen((current) => !current)}
                className={cn(
                    'flex h-11 w-full items-center justify-between gap-3 rounded-md border border-slate-200 bg-slate-50 px-3 text-left text-sm font-semibold text-slate-800 shadow-sm transition hover:bg-white focus:border-indigo-300 focus:bg-white focus:outline-none focus:ring-2 focus:ring-indigo-100 disabled:cursor-not-allowed disabled:bg-slate-100 disabled:text-slate-400',
                    open && 'border-indigo-300 bg-white ring-2 ring-indigo-100',
                )}
            >
                <span className={cn('truncate', !selected && 'text-slate-400')}>{selected?.label || `Select ${label.toLowerCase()}`}</span>
                <ChevronRight className={cn('size-4 shrink-0 rotate-90 text-slate-400 transition', open && '-rotate-90')} />
            </button>
            {open && typeof document !== 'undefined' ? createPortal((
                <div ref={menuRef} style={menuStyle} className="z-[99999] overflow-hidden rounded-lg border border-slate-200 bg-white shadow-[0_28px_80px_-28px_rgba(15,23,42,0.65)]">
                    <div className="relative border-b border-slate-100 p-2">
                        <Search className="absolute left-5 top-1/2 size-4 -translate-y-1/2 text-slate-400" />
                        <input
                            value={search}
                            onChange={(event) => setSearch(event.target.value)}
                            autoFocus
                            className="h-10 w-full rounded-md border border-slate-200 bg-slate-50 pl-9 pr-3 text-sm font-semibold outline-none focus:border-indigo-300 focus:bg-white focus:ring-2 focus:ring-indigo-100"
                            placeholder={`Search ${label.toLowerCase()}...`}
                        />
                    </div>
                    <div className="overflow-auto p-1" style={{ maxHeight: menuStyle.maxHeight ? `calc(${menuStyle.maxHeight} - 58px)` : '302px' }}>
                        {filtered.length ? filtered.map((option) => (
                            <button
                                key={option.value}
                                type="button"
                                onClick={() => {
                                    onChange(option.value);
                                    setSearch('');
                                    setOpen(false);
                                }}
                                className={cn('flex w-full items-center justify-between gap-3 rounded-md px-3 py-2.5 text-left text-sm font-semibold transition hover:bg-indigo-50 hover:text-indigo-700', option.value === value && 'bg-indigo-50 text-indigo-700')}
                            >
                                <span>{option.label}</span>
                                {option.value === value ? <Check className="size-4" /> : null}
                            </button>
                        )) : <p className="px-3 py-5 text-center text-sm font-semibold text-slate-500">No options found.</p>}
                    </div>
                </div>
            ), document.body) : null}
        </div>
    );
}

function KycDatePicker({ value, onChange, disabled }) {
    const inputRef = useRef(null);
    return (
        <div className="relative">
            <input
                ref={inputRef}
                type="date"
                disabled={disabled}
                value={value}
                onChange={(event) => onChange(event.target.value)}
                className="h-11 w-full rounded-md border border-slate-200 bg-slate-50 px-3 pr-11 text-sm font-semibold text-slate-800 shadow-sm transition focus:border-indigo-300 focus:bg-white focus:outline-none focus:ring-2 focus:ring-indigo-100 disabled:cursor-not-allowed disabled:bg-slate-100 disabled:text-slate-400"
            />
            <button
                type="button"
                disabled={disabled}
                onClick={() => inputRef.current?.showPicker?.() || inputRef.current?.focus()}
                className="absolute right-1.5 top-1/2 flex size-8 -translate-y-1/2 items-center justify-center rounded-md text-slate-500 transition hover:bg-white hover:text-indigo-600 disabled:cursor-not-allowed disabled:text-slate-300"
                aria-label="Open date picker"
            >
                <CalendarDays className="size-4" />
            </button>
        </div>
    );
}

function SellerEarnings({ state }) {
    return <SellerAnalytics state={state} />;
}

function SellerQueue({ title, icon, items, empty, render }) {
    const Icon = icon;
    return (
        <Panel title={title} icon={Icon}>
            <div className="grid gap-3 md:grid-cols-2">
                {items.length ? items.map((item) => <div key={item.id} className="rounded-lg border border-slate-200 p-4">{render(item)}</div>) : <p className="rounded-lg bg-slate-50 p-5 text-sm font-semibold text-slate-500">{empty}</p>}
            </div>
        </Panel>
    );
}

function Panel({ title, icon: Icon, actions = null, children }) {
    return (
        <section className="rounded-xl border border-slate-200/80 bg-white/95 p-5 shadow-[0_18px_60px_-42px_rgba(15,23,42,0.5)] ring-1 ring-white backdrop-blur">
            <div className="mb-4 flex flex-col gap-4 xl:flex-row xl:items-center xl:justify-between">
                <div className="flex items-center gap-3">
                    {Icon ? <span className="flex size-10 items-center justify-center rounded-lg bg-cyan-50 text-cyan-700 ring-1 ring-cyan-100"><Icon className="size-5" /></span> : null}
                    <h1 className="text-lg font-bold">{title}</h1>
                </div>
                {actions ? <div className="xl:flex xl:justify-end">{actions}</div> : null}
            </div>
            {children}
        </section>
    );
}

function DetailRow({ label, value }) {
    return (
        <div className="rounded-2xl border border-slate-200 p-4">
            <p className="text-xs font-black uppercase tracking-[0.16em] text-slate-400">{label}</p>
            <p className="mt-2 text-sm font-bold leading-6 text-slate-900">{value}</p>
        </div>
    );
}

function InfoTile({ label, value }) {
    return (
        <div className="rounded-2xl bg-slate-50 px-4 py-3 ring-1 ring-slate-200">
            <p className="text-xs font-black uppercase tracking-[0.16em] text-slate-400">{label}</p>
            <p className="mt-2 text-sm font-bold text-slate-900">{value}</p>
        </div>
    );
}

function ActionCard({ href, title, body }) {
    return (
        <Link href={href} className="rounded-2xl border border-slate-200 p-4 transition hover:border-indigo-200 hover:bg-indigo-50">
            <p className="font-extrabold text-slate-950">{title}</p>
            <p className="mt-2 text-sm font-semibold leading-6 text-slate-500">{body}</p>
        </Link>
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
                        <p className="mt-8 max-w-md text-sm leading-8 text-slate-400">The secure classified and ecommerce marketplace for high-value digital and physical assets, powered by escrow, seller verification, and protected delivery flows.</p>
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
            <FlashDeals state={state} addToCart={addToCart} toggleWishlist={toggleWishlist} />
            <FeaturedVendor vendor={state.featuredVendor} />
            <section className="mt-12 grid gap-8 xl:grid-cols-[2fr_1fr]">
                <BestSellers state={state} addToCart={addToCart} toggleWishlist={toggleWishlist} />
                <JustDropped state={state} addToCart={addToCart} toggleWishlist={toggleWishlist} />
            </section>
            <Recommended state={state} addToCart={addToCart} toggleWishlist={toggleWishlist} />
        </>
    );
}

export default function Workspace({ mode = 'buyer', view, productId, initialMarketplace }) {
    const api = useMarketplaceState(initialMarketplace);
    const normalizedMode = mode === 'seller' ? 'seller' : 'buyer';
    const activeView = view || (normalizedMode === 'seller' ? 'seller-dashboard' : 'home');
    const cartCount = api.state.cart.reduce((sum, item) => sum + item.quantity, 0);
    const activeNotifications = normalizedMode === 'seller' ? (api.state.sellerOps?.notifications || []) : (api.state.buyerOps?.notifications || []);
    const activeUnreadCount = normalizedMode === 'seller'
        ? (api.state.sellerOps?.unreadNotificationCount ?? activeNotifications.filter((item) => !(item.is_read ?? item.read)).length)
        : (api.state.buyerOps?.unreadNotificationCount ?? activeNotifications.filter((item) => !(item.is_read ?? item.read)).length);
    const activeEscrowOrderId = Number(api.state.escrowOrderDetail?.order?.id || 0);

    useEffect(() => {
        const userId = Number(api.state.user?.id || 0);
        if (!userId) {
            return undefined;
        }

        let pollTimer = null;
        const refresh = () => api.fetchNotifications(normalizedMode, { perPage: 12 }).catch(() => {});
        const startPolling = () => {
            if (pollTimer !== null) {
                return;
            }

            pollTimer = window.setInterval(refresh, 30000);
        };

        void refresh();

        const echo = getEcho();
        if (!echo) {
            startPolling();

            return () => {
                if (pollTimer !== null) {
                    window.clearInterval(pollTimer);
                }
            };
        }

        const channelName = `App.Models.User.${userId}`;
        const channel = echo.private(channelName);
        const handleCreated = (payload) => {
            const role = payload?.role === 'seller' ? 'seller' : 'buyer';
            if (payload?.role !== 'all' && role !== normalizedMode) {
                return;
            }

            api.pushIncomingNotification(role, payload?.notification, payload?.unread_count);

            const notificationOrderId = Number(
                payload?.notification?.payload?.order_id
                || payload?.notification?.metadata?.order_id
                || 0,
            );
            if (activeEscrowOrderId > 0 && notificationOrderId === activeEscrowOrderId) {
                api.refreshEscrowOrderDetail(activeEscrowOrderId).catch(() => {});
            }
        };
        const handleStateChanged = (payload) => {
            const role = payload?.role === 'seller' ? 'seller' : 'buyer';
            if (payload?.role !== 'all' && role !== normalizedMode) {
                return;
            }

            api.applyNotificationEvent(payload);
        };

        channel.listen('.notification.created', handleCreated);
        channel.listen('.notification.state.changed', handleStateChanged);
        channel.error(() => startPolling());

        return () => {
            channel.stopListening('.notification.created', handleCreated);
            channel.stopListening('.notification.state.changed', handleStateChanged);
            echo.leave(channelName);
            if (pollTimer !== null) {
                window.clearInterval(pollTimer);
            }
        };
    }, [api.state.user?.id, normalizedMode, activeEscrowOrderId]);

    let content;
    if (normalizedMode === 'seller') {
        if (activeView === 'seller-products') content = <SellerProducts state={api.state} saveSellerProduct={api.saveSellerProduct} duplicateSellerProduct={api.duplicateSellerProduct} bulkSellerProducts={api.bulkSellerProducts} deleteSellerProduct={api.deleteSellerProduct} uploadSellerMedia={api.uploadSellerMedia} />;
        else if (activeView === 'seller-products-create') content = <SellerProducts state={api.state} saveSellerProduct={api.saveSellerProduct} duplicateSellerProduct={api.duplicateSellerProduct} bulkSellerProducts={api.bulkSellerProducts} deleteSellerProduct={api.deleteSellerProduct} uploadSellerMedia={api.uploadSellerMedia} mode="create" />;
        else if (activeView === 'seller-products-edit') content = <SellerProducts state={api.state} saveSellerProduct={api.saveSellerProduct} duplicateSellerProduct={api.duplicateSellerProduct} bulkSellerProducts={api.bulkSellerProducts} deleteSellerProduct={api.deleteSellerProduct} uploadSellerMedia={api.uploadSellerMedia} mode="edit" />;
        else if (activeView === 'seller-products-preview') content = <SellerProducts state={api.state} saveSellerProduct={api.saveSellerProduct} duplicateSellerProduct={api.duplicateSellerProduct} bulkSellerProducts={api.bulkSellerProducts} deleteSellerProduct={api.deleteSellerProduct} uploadSellerMedia={api.uploadSellerMedia} mode="preview" />;
        else if (activeView === 'seller-inventory') content = <SellerInventory state={api.state} adjustStock={api.adjustStock} />;
        else if (activeView === 'seller-warehouses' || activeView === 'seller-warehouse-form' || activeView === 'seller-stock-history') content = <SellerWarehouse state={api.state} adjustStock={api.adjustStock} saveWarehouse={api.saveWarehouse} deleteWarehouse={api.deleteWarehouse} />;
        else if (activeView === 'seller-orders' || activeView === 'seller-delivery') content = <SellerOrders state={api.state} />;
        else if (activeView === 'seller-order-details') content = <SellerOrderDetails state={api.state} releaseEscrowFunds={api.releaseEscrowFunds} openOrderDispute={api.openOrderDispute} sendEscrowMessage={api.sendEscrowMessage} markEscrowMessagesRead={api.markEscrowMessagesRead} mergeIncomingEscrowMessage={api.mergeIncomingEscrowMessage} refreshEscrowOrderDetail={api.refreshEscrowOrderDetail} submitSellerDelivery={api.submitSellerDelivery} pendingAction={api.pendingAction} />;
        else if (activeView === 'seller-payouts' || activeView === 'seller-wallet') content = <SellerWallet state={api.state} requestTopUp={api.requestTopUp} requestPayout={api.requestPayout} uploadSellerMedia={api.uploadSellerMedia} initialTab="wallet" pendingAction={api.pendingAction} />;
        else if (activeView === 'seller-top-up') content = <SellerWallet state={api.state} requestTopUp={api.requestTopUp} requestPayout={api.requestPayout} uploadSellerMedia={api.uploadSellerMedia} initialTab="topup" pendingAction={api.pendingAction} />;
        else if (activeView === 'seller-top-up-history') content = <SellerWallet state={api.state} requestTopUp={api.requestTopUp} requestPayout={api.requestPayout} uploadSellerMedia={api.uploadSellerMedia} initialTab="topup" pendingAction={api.pendingAction} />;
        else if (activeView === 'seller-withdraw-request') content = <SellerWallet state={api.state} requestTopUp={api.requestTopUp} requestPayout={api.requestPayout} uploadSellerMedia={api.uploadSellerMedia} initialTab="withdraw" pendingAction={api.pendingAction} />;
        else if (activeView === 'seller-transactions') content = <SellerWallet state={api.state} requestTopUp={api.requestTopUp} requestPayout={api.requestPayout} uploadSellerMedia={api.uploadSellerMedia} initialTab="transactions" />;
        else if (activeView === 'seller-bank-payment-methods') content = <SellerPayoutMethods state={api.state} savePayoutMethod={api.savePayoutMethod} deletePayoutMethod={api.deletePayoutMethod} pendingAction={api.pendingAction} />;
        else if (activeView === 'seller-withdraw-history') content = <SellerWallet state={api.state} requestTopUp={api.requestTopUp} requestPayout={api.requestPayout} uploadSellerMedia={api.uploadSellerMedia} initialTab="withdraw" pendingAction={api.pendingAction} />;
        else if (activeView === 'seller-offers') content = <SellerOffers state={api.state} saveCoupon={api.saveCoupon} toggleCoupon={api.toggleCoupon} deleteCoupon={api.deleteCoupon} pendingAction={api.pendingAction} />;
        else if (activeView === 'seller-analytics' || activeView === 'seller-earnings') content = <SellerEarnings state={api.state} />;
        else if (activeView === 'seller-business' || activeView === 'seller-store-profile' || activeView === 'seller-store-settings') content = <SellerBusiness state={api.state} saveBusiness={api.saveBusiness} uploadSellerMedia={api.uploadSellerMedia} pendingAction={api.pendingAction} />;
        else if (activeView === 'seller-shipping-settings') content = <SellerShippingSettings state={api.state} saveShippingSettings={api.saveShippingSettings} pendingAction={api.pendingAction} />;
        else if (activeView === 'seller-reviews') content = <SellerReviews state={api.state} />;
        else if (activeView === 'seller-notifications') content = <SellerNotifications state={api.state} />;
        else if (activeView === 'seller-kyc') content = <SellerKyc state={api.state} uploadKycDocument={api.uploadKycDocument} saveKycDraft={api.saveKycDraft} submitKyc={api.submitKyc} />;
        else if (activeView === 'seller-returns') content = <SellerReturns state={api.state} />;
        else if (activeView === 'seller-disputes') content = <SellerDisputes state={api.state} />;
        else if (activeView === 'seller-menu') content = <SellerMenu state={api.state} />;
        else if (activeView === 'seller-support') content = <Support state={api.state} sendMessage={api.sendMessage} uploadSellerMedia={api.uploadSellerMedia} />;
        else content = <SellerDashboard state={api.state} />;
    } else if (activeView === 'marketplace') content = <Marketplace state={api.state} addToCart={api.addToCart} toggleWishlist={api.toggleWishlist} />;
    else if (activeView === 'product') content = <ProductDetail productId={productId} state={api.state} addToCart={api.addToCart} toggleWishlist={api.toggleWishlist} />;
    else if (activeView === 'cart') content = <Cart state={api.state} updateCart={api.updateCart} removeCart={api.removeCart} />;
    else if (activeView === 'checkout') content = <Checkout state={api.state} checkout={api.checkout} />;
    else if (activeView === 'dashboard') content = <BuyerDashboard state={api.state} addToCart={api.addToCart} toggleWishlist={api.toggleWishlist} />;
    else if (activeView === 'order-details') content = <BuyerOrderDetails state={api.state} releaseEscrowFunds={api.releaseEscrowFunds} openOrderDispute={api.openOrderDispute} sendEscrowMessage={api.sendEscrowMessage} markEscrowMessagesRead={api.markEscrowMessagesRead} mergeIncomingEscrowMessage={api.mergeIncomingEscrowMessage} refreshEscrowOrderDetail={api.refreshEscrowOrderDetail} submitSellerDelivery={api.submitSellerDelivery} pendingAction={api.pendingAction} />;
    else if (['orders', 'escrow-orders', 'refund-requests', 'return-requests', 'replacement-requests'].includes(activeView)) content = <BuyerOrdersCenter state={api.state} initialTab={activeView} />;
    else if (['wallet', 'top-up-history', 'transaction-history', 'referral-dashboard', 'loyalty-rewards', 'coupons-promotions'].includes(activeView)) content = <BuyerWalletCenter state={api.state} initialTab={activeView} saveBuyerPaymentMethod={api.saveBuyerPaymentMethod} setDefaultBuyerPaymentMethod={api.setDefaultBuyerPaymentMethod} deleteBuyerPaymentMethod={api.deleteBuyerPaymentMethod} requestBuyerWalletTopUp={api.requestBuyerWalletTopUp} pendingAction={api.pendingAction} />;
    else if (['wishlist', 'saved-items', 'favorite-stores', 'recently-viewed'].includes(activeView)) content = <BuyerSavedCenter state={api.state} addToCart={api.addToCart} toggleWishlist={api.toggleWishlist} initialTab={activeView} />;
    else if (['profile', 'profile-settings', 'security-settings', 'address-book', 'notifications', 'kyc-verification', 'device-management'].includes(activeView)) content = <BuyerProfileCenter state={api.state} saveProfile={api.saveProfile} updateBuyerPassword={api.updateBuyerPassword} uploadBuyerProfilePhoto={api.uploadBuyerProfilePhoto} updateBuyerNotificationPreferences={api.updateBuyerNotificationPreferences} saveBuyerAddress={api.saveBuyerAddress} deleteBuyerAddress={api.deleteBuyerAddress} pendingAction={api.pendingAction} initialTab={activeView} />;
    else if (['support', 'support-tickets', 'messages', 'product-reviews', 'seller-reviews'].includes(activeView)) content = <BuyerCommsCenter state={api.state} sendMessage={api.sendMessage} uploadSellerMedia={api.uploadSellerMedia} initialTab={activeView} />;
    else content = <HomePage state={api.state} addToCart={api.addToCart} toggleWishlist={api.toggleWishlist} />;

    return (
        <AppShell
            mode={normalizedMode}
            view={activeView}
            user={api.state.user}
            cartCount={cartCount}
            wishlistCount={api.state.wishlist.length}
            categories={api.state.categories}
            notice={api.notice}
            notifications={activeNotifications}
            unreadNotificationCount={activeUnreadCount}
            onRefreshNotifications={() => api.fetchNotifications(normalizedMode, { perPage: 12 })}
            onMarkNotificationRead={(notificationId) => api.markNotificationRead(normalizedMode, notificationId)}
            onMarkAllNotificationsRead={() => api.markAllNotificationsRead(normalizedMode)}
            onDeleteNotification={(notificationId) => api.deleteNotification(normalizedMode, notificationId)}
            onClearNotifications={() => api.clearNotifications(normalizedMode)}
        >
            {content}
            {normalizedMode === 'seller' ? null : <EnterpriseFooter trustItems={api.state.trustItems} />}
        </AppShell>
    );
}

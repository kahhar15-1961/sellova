# Sellova (Working Name) - Secure Buy & Sell Marketplace

## 🚀 Overview

Sellora is a full-scale, enterprise-grade marketplace platform designed for:

- Buying and selling physical products
- Selling digital products (downloads, licenses)
- Custom/manual delivery products
- Secure transactions using escrow
- Wallet-based payments
- Seller verification (KYC)
- Membership-based seller system

This platform focuses on **trust-first commerce**, ensuring safe transactions for both buyers and sellers.

---

## 🔐 Core Value Proposition

> "Buy & Sell with Confidence — Powered by Escrow Protection"

---

## 🧱 Tech Stack

### Backend
- Laravel (Latest Stable)
- MySQL (Normalized Schema)
- Redis (Queue / Cache)
- REST API Architecture

### Mobile App
- Flutter
- Riverpod (State Management)
- Dio (API Client)

### Admin Panel
- Laravel (Custom Admin)
- Tailwind 
- React (Inertia) 
- Shadcn UI,Component 

---

## ⚙️ Environment & operations

1. Copy **`.env.example`** to **`.env`**, then run **`php artisan key:generate`**.
2. Set **`DB_*`**, run **`php artisan migrate`** (see **`docs/MIGRATIONS.md`** — `composer migrate` runs the same).
3. Front-end: **`npm ci`** then **`npm run build`** (or **`npm run dev`** while developing the admin UI).
4. **`php artisan serve`** (or configure nginx/Apache with `public/` as the web root).

Production checklist: **`docs/PRODUCTION.md`**.

---

## 🧩 Core Modules

- Authentication (Buyer/Seller)
- User Profiles
- Seller Verification
- Product Management
- Categories & Filters
- Cart & Checkout
- Orders
- Escrow Engine
- Wallet Ledger System
- Withdrawals
- Dispute Management
- Membership Packages
- Seller Storefront
- Ratings & Reviews
- Notifications
- Admin Moderation

---

## 💰 Financial System

- Escrow-based transactions
- Ledger-based wallet system
- Commission and fee deduction
- Secure withdrawal system
- Full audit logging

---

## 🎯 Target

- Local + International marketplace
- SaaS-ready product
- CodeCanyon-ready architecture
- Scalable and modular system

---

## ⚠️ Important Rules

- No business logic in frontend
- All financial logic handled in backend only
- Wallet must be ledger-based
- Escrow must be transaction-safe
- Use clean architecture and modular structure

---

## 📌 Status

🚧 Under Development

See:
- PROJECT_PLAN.md
- PHASES.md
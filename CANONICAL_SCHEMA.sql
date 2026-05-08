-- ============================================================================
-- Sellova Canonical Database Schema (MySQL 8.0+, InnoDB, utf8mb4)
-- Source of truth for Laravel migration conversion
-- ============================================================================
-- Execution Plan:
--   Phase 0: Session settings
--   Phase 1: CREATE TABLE (no foreign keys)
--   Phase 2: ALTER TABLE add foreign keys
--   Phase 3: CREATE INDEX (non-unique secondary indexes)
--   Phase 4: Optional partitioning/archive notes
-- ============================================================================

-- ============================================================================
-- Phase 0: Session Settings
-- ============================================================================
SET NAMES utf8mb4;
SET time_zone = '+00:00';
SET sql_mode = 'STRICT_TRANS_TABLES,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION';

-- ============================================================================
-- Phase 1: CREATE TABLES (no FK constraints)
-- ============================================================================

-- ---- Identity & Access ------------------------------------------------------
CREATE TABLE users (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  uuid CHAR(36) NOT NULL,
  email VARCHAR(255) NULL,
  phone VARCHAR(32) NULL,
  password_hash VARCHAR(255) NOT NULL,
  status ENUM('active','suspended','closed') NOT NULL DEFAULT 'active',
  risk_level ENUM('low','medium','high') NOT NULL DEFAULT 'low',
  last_login_at DATETIME(6) NULL,
  created_at DATETIME(6) NOT NULL,
  updated_at DATETIME(6) NOT NULL,
  deleted_at DATETIME(6) NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_users_uuid (uuid),
  UNIQUE KEY uq_users_email (email),
  UNIQUE KEY uq_users_phone (phone)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE roles (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  code VARCHAR(64) NOT NULL,
  name VARCHAR(128) NOT NULL,
  created_at DATETIME(6) NOT NULL,
  updated_at DATETIME(6) NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_roles_code (code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE permissions (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  code VARCHAR(128) NOT NULL,
  name VARCHAR(128) NOT NULL,
  created_at DATETIME(6) NOT NULL,
  updated_at DATETIME(6) NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_permissions_code (code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE user_roles (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id BIGINT UNSIGNED NOT NULL,
  role_id BIGINT UNSIGNED NOT NULL,
  assigned_by BIGINT UNSIGNED NULL,
  created_at DATETIME(6) NOT NULL,
  updated_at DATETIME(6) NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_user_roles_user_role (user_id, role_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE role_permissions (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  role_id BIGINT UNSIGNED NOT NULL,
  permission_id BIGINT UNSIGNED NOT NULL,
  created_at DATETIME(6) NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_role_permissions_role_perm (role_id, permission_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---- Seller Trust -----------------------------------------------------------
CREATE TABLE seller_profiles (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  uuid CHAR(36) NOT NULL,
  user_id BIGINT UNSIGNED NOT NULL,
  display_name VARCHAR(191) NOT NULL,
  legal_name VARCHAR(191) NULL,
  country_code CHAR(2) NOT NULL,
  default_currency CHAR(3) NOT NULL,
  verification_status ENUM('unverified','pending','verified','rejected') NOT NULL DEFAULT 'unverified',
  store_status ENUM('active','paused','banned') NOT NULL DEFAULT 'active',
  store_logo_url VARCHAR(512) NULL,
  banner_image_url VARCHAR(512) NULL,
  contact_email VARCHAR(191) NULL,
  contact_phone VARCHAR(40) NULL,
  address_line VARCHAR(255) NULL,
  city VARCHAR(120) NULL,
  region VARCHAR(120) NULL,
  postal_code VARCHAR(40) NULL,
  country VARCHAR(120) NULL,
  inside_dhaka_label VARCHAR(255) NULL,
  inside_dhaka_fee DECIMAL(10,2) NULL,
  outside_dhaka_label VARCHAR(255) NULL,
  outside_dhaka_fee DECIMAL(10,2) NULL,
  cash_on_delivery_enabled TINYINT(1) NULL,
  processing_time_label VARCHAR(255) NULL,
  created_at DATETIME(6) NOT NULL,
  updated_at DATETIME(6) NOT NULL,
  deleted_at DATETIME(6) NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_seller_profiles_uuid (uuid),
  UNIQUE KEY uq_seller_profiles_user_id (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE kyc_verifications (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  uuid CHAR(36) NOT NULL,
  seller_profile_id BIGINT UNSIGNED NOT NULL,
  status ENUM('submitted','under_review','approved','rejected','expired') NOT NULL,
  provider_ref VARCHAR(128) NULL,
  reviewed_by BIGINT UNSIGNED NULL,
  reviewed_at DATETIME(6) NULL,
  rejection_reason TEXT NULL,
  submitted_at DATETIME(6) NOT NULL,
  created_at DATETIME(6) NOT NULL,
  updated_at DATETIME(6) NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_kyc_verifications_uuid (uuid)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE kyc_documents (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  kyc_verification_id BIGINT UNSIGNED NOT NULL,
  doc_type ENUM('id_front','id_back','selfie','business_license','address_proof') NOT NULL,
  storage_path VARCHAR(512) NOT NULL,
  checksum_sha256 CHAR(64) NOT NULL,
  status ENUM('uploaded','verified','rejected') NOT NULL DEFAULT 'uploaded',
  created_at DATETIME(6) NOT NULL,
  updated_at DATETIME(6) NOT NULL,
  PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---- Storefront & Catalog ---------------------------------------------------
CREATE TABLE storefronts (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  uuid CHAR(36) NOT NULL,
  seller_profile_id BIGINT UNSIGNED NOT NULL,
  slug VARCHAR(191) NOT NULL,
  title VARCHAR(191) NOT NULL,
  description TEXT NULL,
  policy_text TEXT NULL,
  is_public TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME(6) NOT NULL,
  updated_at DATETIME(6) NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_storefronts_uuid (uuid),
  UNIQUE KEY uq_storefronts_seller_profile_id (seller_profile_id),
  UNIQUE KEY uq_storefronts_slug (slug)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE shipping_methods (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  uuid CHAR(36) NOT NULL,
  code VARCHAR(96) NOT NULL,
  name VARCHAR(191) NOT NULL,
  suggested_fee DECIMAL(18,4) NOT NULL DEFAULT 0,
  processing_time_label VARCHAR(80) NOT NULL DEFAULT '1-2 Business Days',
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  sort_order INT NOT NULL DEFAULT 0,
  created_at DATETIME(6) NOT NULL,
  updated_at DATETIME(6) NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_shipping_methods_uuid (uuid),
  UNIQUE KEY uq_shipping_methods_code (code),
  CONSTRAINT chk_shipping_methods_suggested_fee_nonneg CHECK (suggested_fee >= 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE seller_shipping_methods (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  seller_profile_id BIGINT UNSIGNED NOT NULL,
  shipping_method_id BIGINT UNSIGNED NOT NULL,
  price DECIMAL(18,4) NOT NULL DEFAULT 0,
  processing_time_label VARCHAR(80) NOT NULL DEFAULT '1-2 Business Days',
  is_enabled TINYINT(1) NOT NULL DEFAULT 1,
  sort_order INT NOT NULL DEFAULT 0,
  created_at DATETIME(6) NOT NULL,
  updated_at DATETIME(6) NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_seller_shipping_methods_seller_method (seller_profile_id, shipping_method_id),
  CONSTRAINT chk_seller_shipping_methods_price_nonneg CHECK (price >= 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE categories (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  parent_id BIGINT UNSIGNED NULL,
  slug VARCHAR(191) NOT NULL,
  name VARCHAR(191) NOT NULL,
  description TEXT NULL,
  image_url VARCHAR(512) NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  sort_order INT NOT NULL DEFAULT 0,
  created_at DATETIME(6) NOT NULL,
  updated_at DATETIME(6) NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_categories_slug (slug)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE seller_category_requests (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  uuid CHAR(36) NOT NULL,
  seller_profile_id BIGINT UNSIGNED NOT NULL,
  requested_by_user_id BIGINT UNSIGNED NOT NULL,
  parent_id BIGINT UNSIGNED NULL,
  name VARCHAR(191) NOT NULL,
  slug VARCHAR(191) NOT NULL,
  reason TEXT NULL,
  example_product_name VARCHAR(255) NULL,
  status ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',
  resolved_category_id BIGINT UNSIGNED NULL,
  reviewed_by BIGINT UNSIGNED NULL,
  admin_note TEXT NULL,
  reviewed_at DATETIME(6) NULL,
  created_at DATETIME(6) NOT NULL,
  updated_at DATETIME(6) NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_seller_category_requests_uuid (uuid)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE products (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  uuid CHAR(36) NOT NULL,
  seller_profile_id BIGINT UNSIGNED NOT NULL,
  storefront_id BIGINT UNSIGNED NOT NULL,
  category_id BIGINT UNSIGNED NOT NULL,
  product_type ENUM('physical','digital','manual_delivery') NOT NULL,
  title VARCHAR(255) NOT NULL,
  description LONGTEXT NULL,
  base_price DECIMAL(18,4) NOT NULL,
  currency CHAR(3) NOT NULL,
  image_url VARCHAR(512) NULL,
  images_json JSON NULL,
  attributes_json JSON NULL,
  status ENUM('draft','active','inactive','archived','published') NOT NULL DEFAULT 'draft',
  published_at DATETIME(6) NULL,
  created_at DATETIME(6) NOT NULL,
  updated_at DATETIME(6) NOT NULL,
  deleted_at DATETIME(6) NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_products_uuid (uuid),
  CONSTRAINT chk_products_base_price_nonneg CHECK (base_price >= 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE product_variants (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  uuid CHAR(36) NOT NULL,
  product_id BIGINT UNSIGNED NOT NULL,
  sku VARCHAR(128) NOT NULL,
  title VARCHAR(255) NOT NULL,
  price_delta DECIMAL(18,4) NOT NULL DEFAULT 0,
  attributes_json JSON NOT NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME(6) NOT NULL,
  updated_at DATETIME(6) NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_product_variants_uuid (uuid),
  UNIQUE KEY uq_product_variants_sku (sku)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE inventory_records (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  product_id BIGINT UNSIGNED NULL,
  product_variant_id BIGINT UNSIGNED NULL,
  stock_on_hand BIGINT UNSIGNED NOT NULL DEFAULT 0,
  stock_reserved BIGINT UNSIGNED NOT NULL DEFAULT 0,
  stock_sold BIGINT UNSIGNED NOT NULL DEFAULT 0,
  version INT UNSIGNED NOT NULL DEFAULT 1,
  created_at DATETIME(6) NOT NULL,
  updated_at DATETIME(6) NOT NULL,
  PRIMARY KEY (id),
  CONSTRAINT chk_inventory_nonneg CHECK (
    stock_on_hand >= 0 AND stock_reserved >= 0 AND stock_sold >= 0
  ),
  CONSTRAINT chk_inventory_target_xor CHECK (
    (product_id IS NOT NULL AND product_variant_id IS NULL) OR
    (product_id IS NULL AND product_variant_id IS NOT NULL)
  )
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---- Cart & Checkout --------------------------------------------------------
CREATE TABLE carts (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  uuid CHAR(36) NOT NULL,
  buyer_user_id BIGINT UNSIGNED NOT NULL,
  status ENUM('active','checked_out','abandoned') NOT NULL DEFAULT 'active',
  expires_at DATETIME(6) NULL,
  created_at DATETIME(6) NOT NULL,
  updated_at DATETIME(6) NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_carts_uuid (uuid)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE cart_items (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  cart_id BIGINT UNSIGNED NOT NULL,
  product_id BIGINT UNSIGNED NOT NULL,
  product_variant_id BIGINT UNSIGNED NULL,
  seller_profile_id BIGINT UNSIGNED NOT NULL,
  quantity INT UNSIGNED NOT NULL,
  unit_price_snapshot DECIMAL(18,4) NOT NULL,
  currency_snapshot CHAR(3) NOT NULL,
  metadata_snapshot_json JSON NOT NULL,
  created_at DATETIME(6) NOT NULL,
  updated_at DATETIME(6) NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_cart_items_compound (cart_id, product_id, product_variant_id),
  CONSTRAINT chk_cart_items_qty_pos CHECK (quantity > 0),
  CONSTRAINT chk_cart_items_unit_price_nonneg CHECK (unit_price_snapshot >= 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---- Membership & Monetization ---------------------------------------------
CREATE TABLE membership_plans (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  uuid CHAR(36) NOT NULL,
  code VARCHAR(64) NOT NULL,
  name VARCHAR(191) NOT NULL,
  billing_period ENUM('monthly','quarterly','yearly') NOT NULL,
  price DECIMAL(18,4) NOT NULL,
  currency CHAR(3) NOT NULL,
  benefits_json JSON NOT NULL,
  commission_modifier_json JSON NOT NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME(6) NOT NULL,
  updated_at DATETIME(6) NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_membership_plans_uuid (uuid),
  UNIQUE KEY uq_membership_plans_code (code),
  CONSTRAINT chk_membership_plans_price_nonneg CHECK (price >= 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE commission_rules (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  uuid CHAR(36) NOT NULL,
  scope_type ENUM('global','category','seller','membership_plan') NOT NULL,
  scope_id BIGINT UNSIGNED NULL,
  rule_type ENUM('percentage','flat','tiered') NOT NULL,
  rule_json JSON NOT NULL,
  priority INT NOT NULL DEFAULT 0,
  effective_from DATETIME(6) NULL,
  effective_to DATETIME(6) NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME(6) NOT NULL,
  updated_at DATETIME(6) NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_commission_rules_uuid (uuid),
  CONSTRAINT chk_commission_rules_effective_window CHECK (
    effective_to IS NULL OR effective_from IS NULL OR effective_to >= effective_from
  )
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---- Orders -----------------------------------------------------------------
CREATE TABLE orders (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  uuid CHAR(36) NOT NULL,
  order_number VARCHAR(64) NOT NULL,
  buyer_user_id BIGINT UNSIGNED NOT NULL,
  seller_user_id BIGINT UNSIGNED NULL,
  primary_product_id BIGINT UNSIGNED NULL,
  product_type VARCHAR(32) NULL,
  status ENUM('draft','pending_payment','paid','paid_in_escrow','escrow_funded','processing','delivery_submitted','buyer_review','shipped_or_delivered','completed','cancelled','refunded','disputed') NOT NULL,
  fulfillment_state VARCHAR(64) NOT NULL DEFAULT 'not_started',
  currency CHAR(3) NOT NULL,
  gross_amount DECIMAL(18,4) NOT NULL,
  discount_amount DECIMAL(18,4) NOT NULL DEFAULT 0,
  fee_amount DECIMAL(18,4) NOT NULL DEFAULT 0,
  net_amount DECIMAL(18,4) NOT NULL,
  placed_at DATETIME(6) NULL,
  completed_at DATETIME(6) NULL,
  cancelled_at DATETIME(6) NULL,
  seller_deadline_at DATETIME(6) NULL,
  seller_reminder_at DATETIME(6) NULL,
  delivery_submitted_at DATETIME(6) NULL,
  buyer_review_started_at DATETIME(6) NULL,
  buyer_review_expires_at DATETIME(6) NULL,
  reminder_1_at DATETIME(6) NULL,
  reminder_2_at DATETIME(6) NULL,
  escalation_at DATETIME(6) NULL,
  escalation_warning_at DATETIME(6) NULL,
  auto_release_at DATETIME(6) NULL,
  release_eligible_at DATETIME(6) NULL,
  expires_at DATETIME(6) NULL,
  unpaid_reminder_at DATETIME(6) NULL,
  timeout_policy_snapshot_json JSON NULL,
  cancel_reason VARCHAR(500) NULL,
  created_at DATETIME(6) NOT NULL,
  updated_at DATETIME(6) NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_orders_uuid (uuid),
  UNIQUE KEY uq_orders_order_number (order_number),
  CONSTRAINT chk_orders_amounts_nonneg CHECK (
    gross_amount >= 0 AND discount_amount >= 0 AND fee_amount >= 0 AND net_amount >= 0
  )
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE order_items (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  uuid CHAR(36) NOT NULL,
  order_id BIGINT UNSIGNED NOT NULL,
  seller_profile_id BIGINT UNSIGNED NOT NULL,
  product_id BIGINT UNSIGNED NULL,
  product_variant_id BIGINT UNSIGNED NULL,
  product_type_snapshot ENUM('physical','digital','instant_delivery','service','manual_delivery') NOT NULL,
  title_snapshot VARCHAR(255) NOT NULL,
  sku_snapshot VARCHAR(128) NULL,
  quantity INT UNSIGNED NOT NULL,
  unit_price_snapshot DECIMAL(18,4) NOT NULL,
  line_total_snapshot DECIMAL(18,4) NOT NULL,
  commission_rule_snapshot_json JSON NOT NULL,
  delivery_state ENUM('not_started','in_progress','delivered') NOT NULL DEFAULT 'not_started',
  created_at DATETIME(6) NOT NULL,
  updated_at DATETIME(6) NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_order_items_uuid (uuid),
  CONSTRAINT chk_order_items_qty_pos CHECK (quantity > 0),
  CONSTRAINT chk_order_items_price_nonneg CHECK (unit_price_snapshot >= 0 AND line_total_snapshot >= 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE order_state_transitions (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  order_id BIGINT UNSIGNED NOT NULL,
  from_state VARCHAR(64) NOT NULL,
  to_state VARCHAR(64) NOT NULL,
  reason_code VARCHAR(64) NOT NULL,
  actor_user_id BIGINT UNSIGNED NULL,
  correlation_id CHAR(36) NOT NULL,
  created_at DATETIME(6) NOT NULL,
  PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---- Idempotency ------------------------------------------------------------
CREATE TABLE idempotency_keys (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `key` VARCHAR(191) NOT NULL,
  scope VARCHAR(128) NOT NULL,
  request_hash CHAR(64) NOT NULL,
  response_hash CHAR(64) NULL,
  status ENUM('started','succeeded','failed') NOT NULL DEFAULT 'started',
  expires_at DATETIME(6) NOT NULL,
  created_at DATETIME(6) NOT NULL,
  updated_at DATETIME(6) NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_idempotency_keys_key (`key`),
  UNIQUE KEY uq_idempotency_keys_scope_key (scope, `key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---- Payments Integration ---------------------------------------------------
CREATE TABLE payment_intents (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  uuid CHAR(36) NOT NULL,
  order_id BIGINT UNSIGNED NOT NULL,
  provider VARCHAR(64) NOT NULL,
  provider_intent_ref VARCHAR(191) NOT NULL,
  status ENUM('created','authorized','captured','failed','cancelled','refunded_partial','refunded_full') NOT NULL,
  amount DECIMAL(18,4) NOT NULL,
  currency CHAR(3) NOT NULL,
  expires_at DATETIME(6) NULL,
  created_at DATETIME(6) NOT NULL,
  updated_at DATETIME(6) NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_payment_intents_uuid (uuid),
  UNIQUE KEY uq_payment_intents_provider_ref (provider, provider_intent_ref),
  CONSTRAINT chk_payment_intents_amount_nonneg CHECK (amount >= 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE payment_transactions (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  uuid CHAR(36) NOT NULL,
  payment_intent_id BIGINT UNSIGNED NOT NULL,
  order_id BIGINT UNSIGNED NOT NULL,
  provider_txn_ref VARCHAR(191) NOT NULL,
  txn_type ENUM('authorize','capture','refund','void','chargeback') NOT NULL,
  status ENUM('pending','success','failed') NOT NULL,
  amount DECIMAL(18,4) NOT NULL,
  raw_payload_json JSON NOT NULL,
  processed_at DATETIME(6) NULL,
  created_at DATETIME(6) NOT NULL,
  updated_at DATETIME(6) NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_payment_transactions_uuid (uuid),
  UNIQUE KEY uq_payment_transactions_provider_txn_ref (provider_txn_ref),
  CONSTRAINT chk_payment_transactions_amount_nonneg CHECK (amount >= 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE payment_webhook_events (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  provider VARCHAR(64) NOT NULL,
  provider_event_id VARCHAR(191) NOT NULL,
  event_type VARCHAR(128) NOT NULL,
  payload_json JSON NOT NULL,
  received_at DATETIME(6) NOT NULL,
  processed_at DATETIME(6) NULL,
  processing_status ENUM('pending','processed','failed','ignored') NOT NULL DEFAULT 'pending',
  PRIMARY KEY (id),
  UNIQUE KEY uq_payment_webhook_events_provider_event (provider, provider_event_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---- Escrow (TRANSACTION-SENSITIVE / FINANCIAL) -----------------------------
CREATE TABLE escrow_accounts (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  uuid CHAR(36) NOT NULL,
  order_id BIGINT UNSIGNED NOT NULL,
  state ENUM('initiated','held','released','refunded','under_dispute') NOT NULL,
  currency CHAR(3) NOT NULL,
  held_amount DECIMAL(18,4) NOT NULL,
  released_amount DECIMAL(18,4) NOT NULL DEFAULT 0,
  refunded_amount DECIMAL(18,4) NOT NULL DEFAULT 0,
  held_at DATETIME(6) NULL,
  closed_at DATETIME(6) NULL,
  version INT UNSIGNED NOT NULL DEFAULT 1,
  created_at DATETIME(6) NOT NULL,
  updated_at DATETIME(6) NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_escrow_accounts_uuid (uuid),
  UNIQUE KEY uq_escrow_accounts_order_id (order_id),
  CONSTRAINT chk_escrow_accounts_nonneg CHECK (
    held_amount >= 0 AND released_amount >= 0 AND refunded_amount >= 0
  ),
  CONSTRAINT chk_escrow_accounts_release_refund_bounds CHECK (
    released_amount + refunded_amount <= held_amount
  ),
  CONSTRAINT chk_escrow_accounts_terminal_conservation CHECK (
    (state IN ('released','refunded') AND held_amount = released_amount + refunded_amount)
    OR state IN ('initiated','held','under_dispute')
  )
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE escrow_events (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  uuid CHAR(36) NOT NULL,
  escrow_account_id BIGINT UNSIGNED NOT NULL,
  event_type ENUM('initiated','hold','release','refund','dispute_opened','dispute_resolved') NOT NULL,
  amount DECIMAL(18,4) NOT NULL,
  currency CHAR(3) NOT NULL,
  from_state VARCHAR(64) NULL,
  to_state VARCHAR(64) NOT NULL,
  actor_user_id BIGINT UNSIGNED NULL,
  reference_type VARCHAR(64) NOT NULL,
  reference_id BIGINT UNSIGNED NOT NULL,
  idempotency_key_id BIGINT UNSIGNED NULL,
  created_at DATETIME(6) NOT NULL,
  updated_at DATETIME(6) NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_escrow_events_uuid (uuid),
  CONSTRAINT chk_escrow_events_amount_nonneg CHECK (amount >= 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE escrow_timeout_settings (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  unpaid_order_expiration_minutes INT UNSIGNED NOT NULL DEFAULT 30,
  unpaid_order_warning_minutes INT UNSIGNED NOT NULL DEFAULT 10,
  seller_fulfillment_deadline_hours INT UNSIGNED NOT NULL DEFAULT 24,
  seller_fulfillment_warning_hours INT UNSIGNED NOT NULL DEFAULT 2,
  buyer_review_deadline_hours INT UNSIGNED NOT NULL DEFAULT 72,
  buyer_review_reminder_1_hours INT UNSIGNED NOT NULL DEFAULT 24,
  buyer_review_reminder_2_hours INT UNSIGNED NOT NULL DEFAULT 48,
  escalation_warning_minutes INT UNSIGNED NOT NULL DEFAULT 60,
  seller_min_fulfillment_hours INT UNSIGNED NOT NULL DEFAULT 1,
  seller_max_fulfillment_hours INT UNSIGNED NOT NULL DEFAULT 168,
  buyer_min_review_hours INT UNSIGNED NOT NULL DEFAULT 1,
  buyer_max_review_hours INT UNSIGNED NOT NULL DEFAULT 168,
  auto_escalation_after_review_expiry TINYINT(1) NOT NULL DEFAULT 1,
  auto_cancel_unpaid_orders TINYINT(1) NOT NULL DEFAULT 1,
  auto_release_after_buyer_timeout TINYINT(1) NOT NULL DEFAULT 0,
  auto_create_dispute_on_timeout TINYINT(1) NOT NULL DEFAULT 0,
  dispute_review_queue_enabled TINYINT(1) NOT NULL DEFAULT 1,
  updated_by_user_id BIGINT UNSIGNED NULL,
  created_at DATETIME(6) NOT NULL,
  updated_at DATETIME(6) NOT NULL,
  PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE escrow_timeout_events (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  uuid CHAR(36) NOT NULL,
  order_id BIGINT UNSIGNED NOT NULL,
  escrow_account_id BIGINT UNSIGNED NULL,
  event_type VARCHAR(96) NOT NULL,
  status VARCHAR(32) NOT NULL DEFAULT 'processed',
  action_taken VARCHAR(96) NULL,
  metadata_json JSON NULL,
  scheduled_for DATETIME(6) NULL,
  processed_at DATETIME(6) NULL,
  created_at DATETIME(6) NOT NULL,
  updated_at DATETIME(6) NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_escrow_timeout_events_uuid (uuid),
  UNIQUE KEY uq_timeout_events_order_type (order_id, event_type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---- Wallet & Ledger (TRANSACTION-SENSITIVE / FINANCIAL) --------------------
CREATE TABLE wallets (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  uuid CHAR(36) NOT NULL,
  user_id BIGINT UNSIGNED NOT NULL,
  wallet_type ENUM('buyer','seller','platform') NOT NULL,
  currency CHAR(3) NOT NULL,
  status ENUM('active','frozen','closed') NOT NULL DEFAULT 'active',
  version INT UNSIGNED NOT NULL DEFAULT 1,
  created_at DATETIME(6) NOT NULL,
  updated_at DATETIME(6) NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_wallets_uuid (uuid),
  UNIQUE KEY uq_wallets_user_type_currency (user_id, wallet_type, currency)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE wallet_holds (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  uuid CHAR(36) NOT NULL,
  wallet_id BIGINT UNSIGNED NOT NULL,
  hold_type ENUM('escrow','withdrawal','risk_review') NOT NULL,
  reference_type VARCHAR(64) NOT NULL,
  reference_id BIGINT UNSIGNED NOT NULL,
  amount DECIMAL(18,4) NOT NULL,
  currency CHAR(3) NOT NULL,
  status ENUM('active','released','consumed','cancelled') NOT NULL DEFAULT 'active',
  expires_at DATETIME(6) NULL,
  created_at DATETIME(6) NOT NULL,
  updated_at DATETIME(6) NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_wallet_holds_uuid (uuid),
  UNIQUE KEY uq_wallet_holds_type_ref (hold_type, reference_type, reference_id),
  CONSTRAINT chk_wallet_holds_amount_pos CHECK (amount > 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE wallet_ledger_batches (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  uuid CHAR(36) NOT NULL,
  event_name VARCHAR(64) NOT NULL,
  reference_type VARCHAR(64) NOT NULL,
  reference_id BIGINT UNSIGNED NOT NULL,
  idempotency_key_id BIGINT UNSIGNED NOT NULL,
  status ENUM('proposed','posted','reversed') NOT NULL DEFAULT 'proposed',
  posted_at DATETIME(6) NULL,
  reversed_at DATETIME(6) NULL,
  created_by BIGINT UNSIGNED NULL,
  created_at DATETIME(6) NOT NULL,
  updated_at DATETIME(6) NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_wallet_ledger_batches_uuid (uuid),
  UNIQUE KEY uq_wallet_ledger_batches_idem (idempotency_key_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE wallet_ledger_entries (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  uuid CHAR(36) NOT NULL,
  batch_id BIGINT UNSIGNED NOT NULL,
  wallet_id BIGINT UNSIGNED NOT NULL,
  entry_side ENUM('debit','credit') NOT NULL,
  entry_type ENUM(
    'deposit_credit',
    'escrow_hold_debit',
    'escrow_release_credit',
    'platform_fee_credit',
    'refund_credit',
    'withdrawal_hold_debit',
    'withdrawal_settlement_debit',
    'withdrawal_reversal_credit',
    'adjustment_credit',
    'adjustment_debit'
  ) NOT NULL,
  amount DECIMAL(18,4) NOT NULL,
  currency CHAR(3) NOT NULL,
  running_balance_after DECIMAL(18,4) NULL,
  reference_type VARCHAR(64) NOT NULL,
  reference_id BIGINT UNSIGNED NOT NULL,
  counterparty_wallet_id BIGINT UNSIGNED NULL,
  occurred_at DATETIME(6) NOT NULL,
  reversal_of_entry_id BIGINT UNSIGNED NULL,
  is_reversal TINYINT(1) NOT NULL DEFAULT 0,
  description VARCHAR(255) NULL,
  created_at DATETIME(6) NOT NULL,
  updated_at DATETIME(6) NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_wallet_ledger_entries_uuid (uuid),
  UNIQUE KEY uq_wallet_ledger_entries_reversal_of (reversal_of_entry_id),
  CONSTRAINT chk_wallet_ledger_entries_amount_pos CHECK (amount > 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE wallet_balance_snapshots (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  wallet_id BIGINT UNSIGNED NOT NULL,
  as_of DATETIME(6) NOT NULL,
  available_balance DECIMAL(18,4) NOT NULL,
  held_balance DECIMAL(18,4) NOT NULL,
  created_at DATETIME(6) NOT NULL,
  updated_at DATETIME(6) NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_wallet_balance_snapshots_wallet_asof (wallet_id, as_of),
  CONSTRAINT chk_wallet_balance_snapshots_nonneg CHECK (held_balance >= 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---- Withdrawals (TRANSACTION-SENSITIVE / FINANCIAL) ------------------------
CREATE TABLE withdrawal_requests (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  uuid CHAR(36) NOT NULL,
  idempotency_key VARCHAR(191) NOT NULL,
  seller_profile_id BIGINT UNSIGNED NOT NULL,
  wallet_id BIGINT UNSIGNED NOT NULL,
  status ENUM('requested','under_review','approved','processing_payout','paid_out','rejected','failed','cancelled') NOT NULL,
  requested_amount DECIMAL(18,4) NOT NULL,
  fee_amount DECIMAL(18,4) NOT NULL DEFAULT 0,
  net_payout_amount DECIMAL(18,4) NOT NULL,
  currency CHAR(3) NOT NULL,
  hold_id BIGINT UNSIGNED NULL,
  reviewed_by BIGINT UNSIGNED NULL,
  reviewed_at DATETIME(6) NULL,
  reject_reason TEXT NULL,
  created_at DATETIME(6) NOT NULL,
  updated_at DATETIME(6) NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_withdrawal_requests_uuid (uuid),
  UNIQUE KEY uq_withdrawal_requests_idempotency_key (idempotency_key),
  CONSTRAINT chk_withdrawal_requests_amounts CHECK (
    requested_amount >= 0 AND fee_amount >= 0 AND net_payout_amount = requested_amount - fee_amount
  )
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE withdrawal_transactions (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  uuid CHAR(36) NOT NULL,
  withdrawal_request_id BIGINT UNSIGNED NOT NULL,
  provider VARCHAR(64) NOT NULL,
  provider_transfer_ref VARCHAR(191) NOT NULL,
  attempt_no INT UNSIGNED NOT NULL,
  status ENUM('submitted','confirmed','failed') NOT NULL,
  amount DECIMAL(18,4) NOT NULL,
  currency CHAR(3) NOT NULL,
  failure_reason TEXT NULL,
  processed_at DATETIME(6) NULL,
  created_at DATETIME(6) NOT NULL,
  updated_at DATETIME(6) NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_withdrawal_transactions_uuid (uuid),
  UNIQUE KEY uq_withdrawal_transactions_provider_transfer_ref (provider_transfer_ref),
  UNIQUE KEY uq_withdrawal_transactions_req_attempt (withdrawal_request_id, attempt_no),
  CONSTRAINT chk_withdrawal_transactions_amount_nonneg CHECK (amount >= 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE payout_accounts (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  seller_profile_id BIGINT UNSIGNED NOT NULL,
  account_type ENUM('bank','mobile_money','paypal','crypto') NOT NULL,
  provider VARCHAR(64) NOT NULL,
  account_ref_token VARCHAR(255) NOT NULL,
  is_default TINYINT(1) NOT NULL DEFAULT 0,
  status ENUM('active','disabled') NOT NULL DEFAULT 'active',
  created_at DATETIME(6) NOT NULL,
  updated_at DATETIME(6) NOT NULL,
  PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---- Membership Subscriptions ----------------------------------------------
CREATE TABLE membership_subscriptions (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  uuid CHAR(36) NOT NULL,
  seller_profile_id BIGINT UNSIGNED NOT NULL,
  membership_plan_id BIGINT UNSIGNED NOT NULL,
  status ENUM('inactive','active','expired','cancelled','suspended') NOT NULL,
  started_at DATETIME(6) NULL,
  expires_at DATETIME(6) NULL,
  cancelled_at DATETIME(6) NULL,
  suspended_at DATETIME(6) NULL,
  renewal_mode ENUM('auto','manual') NOT NULL DEFAULT 'manual',
  payment_intent_id BIGINT UNSIGNED NULL,
  created_at DATETIME(6) NOT NULL,
  updated_at DATETIME(6) NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_membership_subscriptions_uuid (uuid)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---- Disputes (TRANSACTION-SENSITIVE / FINANCIAL-COUPLED) -------------------
CREATE TABLE dispute_cases (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  uuid CHAR(36) NOT NULL,
  order_id BIGINT UNSIGNED NOT NULL,
  order_item_id BIGINT UNSIGNED NULL,
  opened_by_user_id BIGINT UNSIGNED NOT NULL,
  status ENUM('opened','evidence_collection','under_review','escalated','resolved') NOT NULL,
  resolution_outcome ENUM('buyer_wins','seller_wins','split_decision') NULL,
  opened_at DATETIME(6) NOT NULL,
  resolved_at DATETIME(6) NULL,
  resolution_notes TEXT NULL,
  created_at DATETIME(6) NOT NULL,
  updated_at DATETIME(6) NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_dispute_cases_uuid (uuid)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE dispute_evidences (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  uuid CHAR(36) NOT NULL,
  dispute_case_id BIGINT UNSIGNED NOT NULL,
  order_id BIGINT UNSIGNED NULL,
  submitted_by_user_id BIGINT UNSIGNED NOT NULL,
  message_id BIGINT UNSIGNED NULL,
  file_id VARCHAR(191) NULL,
  note TEXT NULL,
  evidence_type ENUM('text','image','video','document','tracking','chat_message','delivery_proof','screenshot','file') NOT NULL,
  content_text TEXT NULL,
  storage_path VARCHAR(512) NULL,
  checksum_sha256 CHAR(64) NULL,
  submitted_at DATETIME(6) NOT NULL,
  created_at DATETIME(6) NOT NULL,
  updated_at DATETIME(6) NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_dispute_evidences_uuid (uuid)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE dispute_decisions (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  uuid CHAR(36) NOT NULL,
  dispute_case_id BIGINT UNSIGNED NOT NULL,
  decided_by_user_id BIGINT UNSIGNED NOT NULL,
  outcome ENUM('buyer_wins','seller_wins','split_decision') NOT NULL,
  buyer_amount DECIMAL(18,4) NOT NULL,
  seller_amount DECIMAL(18,4) NOT NULL,
  currency CHAR(3) NOT NULL,
  reason_code VARCHAR(64) NOT NULL,
  notes TEXT NOT NULL,
  escrow_event_id BIGINT UNSIGNED NULL,
  ledger_batch_id BIGINT UNSIGNED NULL,
  decided_at DATETIME(6) NOT NULL,
  created_at DATETIME(6) NOT NULL,
  updated_at DATETIME(6) NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_dispute_decisions_uuid (uuid),
  UNIQUE KEY uq_dispute_decisions_case (dispute_case_id),
  CONSTRAINT chk_dispute_decisions_amounts_nonneg CHECK (buyer_amount >= 0 AND seller_amount >= 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---- Reputation / Notifications / Audit ------------------------------------
CREATE TABLE reviews (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  uuid CHAR(36) NOT NULL,
  order_item_id BIGINT UNSIGNED NOT NULL,
  buyer_user_id BIGINT UNSIGNED NOT NULL,
  seller_profile_id BIGINT UNSIGNED NOT NULL,
  product_id BIGINT UNSIGNED NOT NULL,
  rating TINYINT UNSIGNED NOT NULL,
  comment TEXT NULL,
  status ENUM('visible','hidden','flagged') NOT NULL DEFAULT 'visible',
  created_at DATETIME(6) NOT NULL,
  updated_at DATETIME(6) NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_reviews_uuid (uuid),
  UNIQUE KEY uq_reviews_order_item_id (order_item_id),
  CONSTRAINT chk_reviews_rating CHECK (rating BETWEEN 1 AND 5)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE notifications (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  uuid CHAR(36) NOT NULL,
  user_id BIGINT UNSIGNED NOT NULL,
  channel ENUM('in_app','email','sms','push') NOT NULL,
  template_code VARCHAR(128) NOT NULL,
  payload_json JSON NOT NULL,
  status ENUM('queued','sent','failed','read') NOT NULL DEFAULT 'queued',
  sent_at DATETIME(6) NULL,
  read_at DATETIME(6) NULL,
  created_at DATETIME(6) NOT NULL,
  updated_at DATETIME(6) NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_notifications_uuid (uuid)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE audit_logs (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  uuid CHAR(36) NOT NULL,
  actor_user_id BIGINT UNSIGNED NULL,
  action VARCHAR(128) NOT NULL,
  target_type VARCHAR(64) NOT NULL,
  target_id BIGINT UNSIGNED NOT NULL,
  before_json JSON NULL,
  after_json JSON NULL,
  reason_code VARCHAR(64) NULL,
  ip_address VARCHAR(45) NULL,
  user_agent VARCHAR(512) NULL,
  correlation_id CHAR(36) NOT NULL,
  created_at DATETIME(6) NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_audit_logs_uuid (uuid)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE outbox_events (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  uuid CHAR(36) NOT NULL,
  aggregate_type VARCHAR(64) NOT NULL,
  aggregate_id BIGINT UNSIGNED NOT NULL,
  event_type VARCHAR(128) NOT NULL,
  payload_json JSON NOT NULL,
  status ENUM('pending','published','failed') NOT NULL DEFAULT 'pending',
  attempts INT UNSIGNED NOT NULL DEFAULT 0,
  available_at DATETIME(6) NOT NULL,
  published_at DATETIME(6) NULL,
  created_at DATETIME(6) NOT NULL,
  updated_at DATETIME(6) NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_outbox_events_uuid (uuid)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---- API authentication (opaque bearer tokens) -----------------------------
CREATE TABLE user_auth_tokens (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  uuid CHAR(36) NOT NULL,
  user_id BIGINT UNSIGNED NOT NULL,
  token_family CHAR(36) NOT NULL,
  token_hash CHAR(64) NOT NULL,
  kind ENUM('access','refresh') NOT NULL,
  expires_at DATETIME(6) NOT NULL,
  revoked_at DATETIME(6) NULL,
  created_at DATETIME(6) NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_user_auth_tokens_token_hash (token_hash),
  KEY idx_user_auth_tokens_user_family (user_id, token_family),
  KEY idx_user_auth_tokens_expires (expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- Phase 2: Foreign Keys (separate phase)
-- ============================================================================

ALTER TABLE user_roles
  ADD CONSTRAINT fk_user_roles_user FOREIGN KEY (user_id) REFERENCES users(id) ON UPDATE RESTRICT ON DELETE RESTRICT,
  ADD CONSTRAINT fk_user_roles_role FOREIGN KEY (role_id) REFERENCES roles(id) ON UPDATE RESTRICT ON DELETE RESTRICT,
  ADD CONSTRAINT fk_user_roles_assigned_by FOREIGN KEY (assigned_by) REFERENCES users(id) ON UPDATE RESTRICT ON DELETE SET NULL;

ALTER TABLE user_auth_tokens
  ADD CONSTRAINT fk_user_auth_tokens_user FOREIGN KEY (user_id) REFERENCES users(id) ON UPDATE RESTRICT ON DELETE CASCADE;

ALTER TABLE role_permissions
  ADD CONSTRAINT fk_role_permissions_role FOREIGN KEY (role_id) REFERENCES roles(id) ON UPDATE RESTRICT ON DELETE RESTRICT,
  ADD CONSTRAINT fk_role_permissions_permission FOREIGN KEY (permission_id) REFERENCES permissions(id) ON UPDATE RESTRICT ON DELETE RESTRICT;

ALTER TABLE seller_profiles
  ADD CONSTRAINT fk_seller_profiles_user FOREIGN KEY (user_id) REFERENCES users(id) ON UPDATE RESTRICT ON DELETE RESTRICT;

ALTER TABLE kyc_verifications
  ADD CONSTRAINT fk_kyc_verifications_seller_profile FOREIGN KEY (seller_profile_id) REFERENCES seller_profiles(id) ON UPDATE RESTRICT ON DELETE RESTRICT,
  ADD CONSTRAINT fk_kyc_verifications_reviewed_by FOREIGN KEY (reviewed_by) REFERENCES users(id) ON UPDATE RESTRICT ON DELETE SET NULL;

ALTER TABLE kyc_documents
  ADD CONSTRAINT fk_kyc_documents_verification FOREIGN KEY (kyc_verification_id) REFERENCES kyc_verifications(id) ON UPDATE RESTRICT ON DELETE RESTRICT;

ALTER TABLE storefronts
  ADD CONSTRAINT fk_storefronts_seller_profile FOREIGN KEY (seller_profile_id) REFERENCES seller_profiles(id) ON UPDATE RESTRICT ON DELETE RESTRICT;

ALTER TABLE seller_shipping_methods
  ADD CONSTRAINT fk_seller_shipping_methods_seller FOREIGN KEY (seller_profile_id) REFERENCES seller_profiles(id) ON UPDATE RESTRICT ON DELETE CASCADE,
  ADD CONSTRAINT fk_seller_shipping_methods_method FOREIGN KEY (shipping_method_id) REFERENCES shipping_methods(id) ON UPDATE RESTRICT ON DELETE RESTRICT;

ALTER TABLE categories
  ADD CONSTRAINT fk_categories_parent FOREIGN KEY (parent_id) REFERENCES categories(id) ON UPDATE RESTRICT ON DELETE SET NULL;

ALTER TABLE seller_category_requests
  ADD CONSTRAINT fk_seller_category_requests_seller FOREIGN KEY (seller_profile_id) REFERENCES seller_profiles(id) ON UPDATE RESTRICT ON DELETE RESTRICT,
  ADD CONSTRAINT fk_seller_category_requests_user FOREIGN KEY (requested_by_user_id) REFERENCES users(id) ON UPDATE RESTRICT ON DELETE RESTRICT,
  ADD CONSTRAINT fk_seller_category_requests_parent FOREIGN KEY (parent_id) REFERENCES categories(id) ON UPDATE RESTRICT ON DELETE SET NULL,
  ADD CONSTRAINT fk_seller_category_requests_resolved FOREIGN KEY (resolved_category_id) REFERENCES categories(id) ON UPDATE RESTRICT ON DELETE SET NULL,
  ADD CONSTRAINT fk_seller_category_requests_reviewed_by FOREIGN KEY (reviewed_by) REFERENCES users(id) ON UPDATE RESTRICT ON DELETE SET NULL;

ALTER TABLE products
  ADD CONSTRAINT fk_products_seller_profile FOREIGN KEY (seller_profile_id) REFERENCES seller_profiles(id) ON UPDATE RESTRICT ON DELETE RESTRICT,
  ADD CONSTRAINT fk_products_storefront FOREIGN KEY (storefront_id) REFERENCES storefronts(id) ON UPDATE RESTRICT ON DELETE RESTRICT,
  ADD CONSTRAINT fk_products_category FOREIGN KEY (category_id) REFERENCES categories(id) ON UPDATE RESTRICT ON DELETE RESTRICT;

ALTER TABLE product_variants
  ADD CONSTRAINT fk_product_variants_product FOREIGN KEY (product_id) REFERENCES products(id) ON UPDATE RESTRICT ON DELETE RESTRICT;

ALTER TABLE inventory_records
  ADD CONSTRAINT fk_inventory_records_product FOREIGN KEY (product_id) REFERENCES products(id) ON UPDATE RESTRICT ON DELETE RESTRICT,
  ADD CONSTRAINT fk_inventory_records_variant FOREIGN KEY (product_variant_id) REFERENCES product_variants(id) ON UPDATE RESTRICT ON DELETE RESTRICT;

ALTER TABLE carts
  ADD CONSTRAINT fk_carts_buyer_user FOREIGN KEY (buyer_user_id) REFERENCES users(id) ON UPDATE RESTRICT ON DELETE RESTRICT;

ALTER TABLE cart_items
  ADD CONSTRAINT fk_cart_items_cart FOREIGN KEY (cart_id) REFERENCES carts(id) ON UPDATE RESTRICT ON DELETE CASCADE,
  ADD CONSTRAINT fk_cart_items_product FOREIGN KEY (product_id) REFERENCES products(id) ON UPDATE RESTRICT ON DELETE RESTRICT,
  ADD CONSTRAINT fk_cart_items_variant FOREIGN KEY (product_variant_id) REFERENCES product_variants(id) ON UPDATE RESTRICT ON DELETE SET NULL,
  ADD CONSTRAINT fk_cart_items_seller_profile FOREIGN KEY (seller_profile_id) REFERENCES seller_profiles(id) ON UPDATE RESTRICT ON DELETE RESTRICT;

ALTER TABLE orders
  ADD CONSTRAINT fk_orders_buyer_user FOREIGN KEY (buyer_user_id) REFERENCES users(id) ON UPDATE RESTRICT ON DELETE RESTRICT;

ALTER TABLE order_items
  ADD CONSTRAINT fk_order_items_order FOREIGN KEY (order_id) REFERENCES orders(id) ON UPDATE RESTRICT ON DELETE RESTRICT,
  ADD CONSTRAINT fk_order_items_seller_profile FOREIGN KEY (seller_profile_id) REFERENCES seller_profiles(id) ON UPDATE RESTRICT ON DELETE RESTRICT,
  ADD CONSTRAINT fk_order_items_product FOREIGN KEY (product_id) REFERENCES products(id) ON UPDATE RESTRICT ON DELETE SET NULL,
  ADD CONSTRAINT fk_order_items_variant FOREIGN KEY (product_variant_id) REFERENCES product_variants(id) ON UPDATE RESTRICT ON DELETE SET NULL;

ALTER TABLE order_state_transitions
  ADD CONSTRAINT fk_order_state_transitions_order FOREIGN KEY (order_id) REFERENCES orders(id) ON UPDATE RESTRICT ON DELETE RESTRICT,
  ADD CONSTRAINT fk_order_state_transitions_actor FOREIGN KEY (actor_user_id) REFERENCES users(id) ON UPDATE RESTRICT ON DELETE SET NULL;

ALTER TABLE payment_intents
  ADD CONSTRAINT fk_payment_intents_order FOREIGN KEY (order_id) REFERENCES orders(id) ON UPDATE RESTRICT ON DELETE RESTRICT;

ALTER TABLE payment_transactions
  ADD CONSTRAINT fk_payment_transactions_intent FOREIGN KEY (payment_intent_id) REFERENCES payment_intents(id) ON UPDATE RESTRICT ON DELETE RESTRICT,
  ADD CONSTRAINT fk_payment_transactions_order FOREIGN KEY (order_id) REFERENCES orders(id) ON UPDATE RESTRICT ON DELETE RESTRICT;

ALTER TABLE escrow_accounts
  ADD CONSTRAINT fk_escrow_accounts_order FOREIGN KEY (order_id) REFERENCES orders(id) ON UPDATE RESTRICT ON DELETE RESTRICT;

ALTER TABLE escrow_events
  ADD CONSTRAINT fk_escrow_events_account FOREIGN KEY (escrow_account_id) REFERENCES escrow_accounts(id) ON UPDATE RESTRICT ON DELETE RESTRICT,
  ADD CONSTRAINT fk_escrow_events_actor FOREIGN KEY (actor_user_id) REFERENCES users(id) ON UPDATE RESTRICT ON DELETE SET NULL,
  ADD CONSTRAINT fk_escrow_events_idempotency FOREIGN KEY (idempotency_key_id) REFERENCES idempotency_keys(id) ON UPDATE RESTRICT ON DELETE SET NULL;

ALTER TABLE wallets
  ADD CONSTRAINT fk_wallets_user FOREIGN KEY (user_id) REFERENCES users(id) ON UPDATE RESTRICT ON DELETE RESTRICT;

ALTER TABLE wallet_holds
  ADD CONSTRAINT fk_wallet_holds_wallet FOREIGN KEY (wallet_id) REFERENCES wallets(id) ON UPDATE RESTRICT ON DELETE RESTRICT;

ALTER TABLE wallet_ledger_batches
  ADD CONSTRAINT fk_wallet_ledger_batches_idempotency FOREIGN KEY (idempotency_key_id) REFERENCES idempotency_keys(id) ON UPDATE RESTRICT ON DELETE RESTRICT,
  ADD CONSTRAINT fk_wallet_ledger_batches_created_by FOREIGN KEY (created_by) REFERENCES users(id) ON UPDATE RESTRICT ON DELETE SET NULL;

ALTER TABLE wallet_ledger_entries
  ADD CONSTRAINT fk_wallet_ledger_entries_batch FOREIGN KEY (batch_id) REFERENCES wallet_ledger_batches(id) ON UPDATE RESTRICT ON DELETE RESTRICT,
  ADD CONSTRAINT fk_wallet_ledger_entries_wallet FOREIGN KEY (wallet_id) REFERENCES wallets(id) ON UPDATE RESTRICT ON DELETE RESTRICT,
  ADD CONSTRAINT fk_wallet_ledger_entries_counterparty_wallet FOREIGN KEY (counterparty_wallet_id) REFERENCES wallets(id) ON UPDATE RESTRICT ON DELETE SET NULL,
  ADD CONSTRAINT fk_wallet_ledger_entries_reversal_of FOREIGN KEY (reversal_of_entry_id) REFERENCES wallet_ledger_entries(id) ON UPDATE RESTRICT ON DELETE RESTRICT;

ALTER TABLE wallet_balance_snapshots
  ADD CONSTRAINT fk_wallet_balance_snapshots_wallet FOREIGN KEY (wallet_id) REFERENCES wallets(id) ON UPDATE RESTRICT ON DELETE RESTRICT;

ALTER TABLE withdrawal_requests
  ADD CONSTRAINT fk_withdrawal_requests_seller_profile FOREIGN KEY (seller_profile_id) REFERENCES seller_profiles(id) ON UPDATE RESTRICT ON DELETE RESTRICT,
  ADD CONSTRAINT fk_withdrawal_requests_wallet FOREIGN KEY (wallet_id) REFERENCES wallets(id) ON UPDATE RESTRICT ON DELETE RESTRICT,
  ADD CONSTRAINT fk_withdrawal_requests_hold FOREIGN KEY (hold_id) REFERENCES wallet_holds(id) ON UPDATE RESTRICT ON DELETE SET NULL,
  ADD CONSTRAINT fk_withdrawal_requests_reviewed_by FOREIGN KEY (reviewed_by) REFERENCES users(id) ON UPDATE RESTRICT ON DELETE SET NULL;

ALTER TABLE withdrawal_transactions
  ADD CONSTRAINT fk_withdrawal_transactions_request FOREIGN KEY (withdrawal_request_id) REFERENCES withdrawal_requests(id) ON UPDATE RESTRICT ON DELETE RESTRICT;

ALTER TABLE payout_accounts
  ADD CONSTRAINT fk_payout_accounts_seller_profile FOREIGN KEY (seller_profile_id) REFERENCES seller_profiles(id) ON UPDATE RESTRICT ON DELETE RESTRICT;

ALTER TABLE membership_subscriptions
  ADD CONSTRAINT fk_membership_subscriptions_seller_profile FOREIGN KEY (seller_profile_id) REFERENCES seller_profiles(id) ON UPDATE RESTRICT ON DELETE RESTRICT,
  ADD CONSTRAINT fk_membership_subscriptions_plan FOREIGN KEY (membership_plan_id) REFERENCES membership_plans(id) ON UPDATE RESTRICT ON DELETE RESTRICT,
  ADD CONSTRAINT fk_membership_subscriptions_payment_intent FOREIGN KEY (payment_intent_id) REFERENCES payment_intents(id) ON UPDATE RESTRICT ON DELETE SET NULL;

ALTER TABLE dispute_cases
  ADD CONSTRAINT fk_dispute_cases_order FOREIGN KEY (order_id) REFERENCES orders(id) ON UPDATE RESTRICT ON DELETE RESTRICT,
  ADD CONSTRAINT fk_dispute_cases_order_item FOREIGN KEY (order_item_id) REFERENCES order_items(id) ON UPDATE RESTRICT ON DELETE SET NULL,
  ADD CONSTRAINT fk_dispute_cases_opened_by FOREIGN KEY (opened_by_user_id) REFERENCES users(id) ON UPDATE RESTRICT ON DELETE RESTRICT;

ALTER TABLE dispute_evidences
  ADD CONSTRAINT fk_dispute_evidences_case FOREIGN KEY (dispute_case_id) REFERENCES dispute_cases(id) ON UPDATE RESTRICT ON DELETE RESTRICT,
  ADD CONSTRAINT fk_dispute_evidences_submitted_by FOREIGN KEY (submitted_by_user_id) REFERENCES users(id) ON UPDATE RESTRICT ON DELETE RESTRICT;

ALTER TABLE dispute_decisions
  ADD CONSTRAINT fk_dispute_decisions_case FOREIGN KEY (dispute_case_id) REFERENCES dispute_cases(id) ON UPDATE RESTRICT ON DELETE RESTRICT,
  ADD CONSTRAINT fk_dispute_decisions_decided_by FOREIGN KEY (decided_by_user_id) REFERENCES users(id) ON UPDATE RESTRICT ON DELETE RESTRICT,
  ADD CONSTRAINT fk_dispute_decisions_escrow_event FOREIGN KEY (escrow_event_id) REFERENCES escrow_events(id) ON UPDATE RESTRICT ON DELETE SET NULL,
  ADD CONSTRAINT fk_dispute_decisions_ledger_batch FOREIGN KEY (ledger_batch_id) REFERENCES wallet_ledger_batches(id) ON UPDATE RESTRICT ON DELETE SET NULL;

ALTER TABLE reviews
  ADD CONSTRAINT fk_reviews_order_item FOREIGN KEY (order_item_id) REFERENCES order_items(id) ON UPDATE RESTRICT ON DELETE RESTRICT,
  ADD CONSTRAINT fk_reviews_buyer_user FOREIGN KEY (buyer_user_id) REFERENCES users(id) ON UPDATE RESTRICT ON DELETE RESTRICT,
  ADD CONSTRAINT fk_reviews_seller_profile FOREIGN KEY (seller_profile_id) REFERENCES seller_profiles(id) ON UPDATE RESTRICT ON DELETE RESTRICT,
  ADD CONSTRAINT fk_reviews_product FOREIGN KEY (product_id) REFERENCES products(id) ON UPDATE RESTRICT ON DELETE RESTRICT;

ALTER TABLE notifications
  ADD CONSTRAINT fk_notifications_user FOREIGN KEY (user_id) REFERENCES users(id) ON UPDATE RESTRICT ON DELETE RESTRICT;

ALTER TABLE audit_logs
  ADD CONSTRAINT fk_audit_logs_actor FOREIGN KEY (actor_user_id) REFERENCES users(id) ON UPDATE RESTRICT ON DELETE SET NULL;

-- ============================================================================
-- Phase 3: Secondary Indexes
-- ============================================================================

CREATE INDEX idx_kyc_verifications_seller_status ON kyc_verifications (seller_profile_id, status, submitted_at);
CREATE INDEX idx_products_seller_status ON products (seller_profile_id, status, created_at);
CREATE INDEX idx_products_category_status ON products (category_id, status, created_at);
CREATE INDEX idx_seller_category_requests_status_created ON seller_category_requests (status, created_at);
CREATE INDEX idx_seller_category_requests_seller_status ON seller_category_requests (seller_profile_id, status);
CREATE INDEX idx_shipping_methods_active_sort ON shipping_methods (is_active, sort_order, name);
CREATE INDEX idx_seller_shipping_methods_seller_enabled ON seller_shipping_methods (seller_profile_id, is_enabled, sort_order);

CREATE INDEX idx_inventory_records_product ON inventory_records (product_id);
CREATE INDEX idx_inventory_records_variant ON inventory_records (product_variant_id);

CREATE INDEX idx_carts_buyer_status ON carts (buyer_user_id, status, updated_at);
CREATE INDEX idx_cart_items_cart ON cart_items (cart_id);
CREATE INDEX idx_cart_items_product ON cart_items (product_id);

CREATE INDEX idx_orders_status_created ON orders (status, created_at, id);
CREATE INDEX idx_orders_buyer_created ON orders (buyer_user_id, created_at, id);
CREATE INDEX idx_order_items_order ON order_items (order_id, id);
CREATE INDEX idx_order_items_seller ON order_items (seller_profile_id, created_at);
CREATE INDEX idx_order_state_transitions_order_created ON order_state_transitions (order_id, created_at);
CREATE INDEX idx_order_state_transitions_correlation ON order_state_transitions (correlation_id);

CREATE INDEX idx_idempotency_scope_status_expiry ON idempotency_keys (scope, status, expires_at);

CREATE INDEX idx_payment_intents_order ON payment_intents (order_id, status, created_at);
CREATE INDEX idx_payment_transactions_order ON payment_transactions (order_id, status, processed_at);
CREATE INDEX idx_payment_transactions_intent ON payment_transactions (payment_intent_id, created_at);
CREATE INDEX idx_payment_webhook_status_received ON payment_webhook_events (processing_status, received_at);

CREATE INDEX idx_escrow_accounts_state_created ON escrow_accounts (state, created_at);
CREATE INDEX idx_escrow_events_account_created ON escrow_events (escrow_account_id, created_at, id);
CREATE INDEX idx_escrow_events_reference ON escrow_events (reference_type, reference_id);

CREATE INDEX idx_wallets_user ON wallets (user_id, status);
CREATE INDEX idx_wallet_holds_wallet_status ON wallet_holds (wallet_id, status, created_at);
CREATE INDEX idx_wallet_holds_reference ON wallet_holds (reference_type, reference_id);
CREATE INDEX idx_wallet_ledger_batches_reference ON wallet_ledger_batches (reference_type, reference_id, status);
CREATE INDEX idx_wallet_ledger_entries_wallet_time ON wallet_ledger_entries (wallet_id, occurred_at, id);
CREATE INDEX idx_wallet_ledger_entries_reference ON wallet_ledger_entries (reference_type, reference_id);
CREATE INDEX idx_wallet_ledger_entries_entry_type_time ON wallet_ledger_entries (entry_type, occurred_at);

CREATE INDEX idx_withdrawal_requests_seller_status_created ON withdrawal_requests (seller_profile_id, status, created_at);
CREATE INDEX idx_withdrawal_requests_wallet_status ON withdrawal_requests (wallet_id, status, created_at);
CREATE INDEX idx_withdrawal_transactions_request ON withdrawal_transactions (withdrawal_request_id, attempt_no);
CREATE INDEX idx_payout_accounts_seller_default ON payout_accounts (seller_profile_id, is_default, status);

CREATE INDEX idx_membership_subscriptions_seller_status_expiry ON membership_subscriptions (seller_profile_id, status, expires_at);
CREATE INDEX idx_membership_subscriptions_plan_status ON membership_subscriptions (membership_plan_id, status);
CREATE INDEX idx_commission_rules_scope_active_priority ON commission_rules (scope_type, scope_id, is_active, priority);

CREATE INDEX idx_dispute_cases_status_opened ON dispute_cases (status, opened_at);
CREATE INDEX idx_dispute_cases_order_status ON dispute_cases (order_id, status);
CREATE INDEX idx_dispute_evidences_case_submitted ON dispute_evidences (dispute_case_id, submitted_at);

CREATE INDEX idx_notifications_user_status_created ON notifications (user_id, status, created_at);

CREATE INDEX idx_audit_logs_target_created ON audit_logs (target_type, target_id, created_at);
CREATE INDEX idx_audit_logs_actor_created ON audit_logs (actor_user_id, created_at);
CREATE INDEX idx_audit_logs_correlation ON audit_logs (correlation_id);

CREATE INDEX idx_user_auth_tokens_user_kind ON user_auth_tokens (user_id, kind, revoked_at);

CREATE INDEX idx_outbox_events_status_available ON outbox_events (status, available_at, attempts);
CREATE INDEX idx_outbox_events_aggregate ON outbox_events (aggregate_type, aggregate_id, created_at);

-- Optional business guard: one active dispute per order-item scope
-- (MySQL has no partial index; enforce in service layer or trigger)
-- CREATE UNIQUE INDEX uq_dispute_active_guard ON dispute_cases(order_id, order_item_id, status);

-- ============================================================================
-- Phase 4: Archival / Partitioning Notes (operational)
-- ============================================================================
-- Recommended monthly RANGE partitioning (or archive offload) for:
--   - wallet_ledger_entries by occurred_at
--   - audit_logs by created_at
--   - payment_webhook_events by received_at
--   - outbox_events by created_at
-- Optionally:
--   - orders by created_at
--   - order_state_transitions by created_at
--
-- Keep hot data: 12-18 months.
-- Move immutable historical partitions to archive schema/storage.
-- Financial and audit rows are append-only; never hard-delete.

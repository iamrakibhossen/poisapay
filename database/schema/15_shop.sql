-- ============================================================
-- Module: shop
-- GENERATED from Laravel migrations (source of truth) via pg_dump.
-- Do not edit by hand; regenerate with database/schema/regenerate.sh
-- ============================================================

CREATE TABLE public.shop_analytics_events (
    id uuid NOT NULL,
    seller_id uuid NOT NULL,
    sales_page_id uuid,
    order_id uuid,
    type character varying(24) NOT NULL,
    session_id character varying(64),
    ip_hash character varying(64),
    referrer character varying(255),
    utm jsonb,
    occurred_at timestamp(0) without time zone DEFAULT CURRENT_TIMESTAMP NOT NULL
);

ALTER TABLE ONLY public.shop_analytics_events
    ADD CONSTRAINT shop_analytics_events_pkey PRIMARY KEY (id);
CREATE INDEX shop_analytics_events_order_id_index ON public.shop_analytics_events USING btree (order_id);
CREATE INDEX shop_analytics_events_sales_page_id_occurred_at_index ON public.shop_analytics_events USING btree (sales_page_id, occurred_at);
CREATE INDEX shop_analytics_events_seller_id_type_occurred_at_index ON public.shop_analytics_events USING btree (seller_id, type, occurred_at);

CREATE TABLE public.shop_coupons (
    id uuid NOT NULL,
    seller_id uuid NOT NULL,
    product_id uuid,
    code character varying(40) NOT NULL,
    type character varying(10) NOT NULL,
    value integer NOT NULL,
    min_order_amount bigint,
    usage_limit integer,
    used_count integer DEFAULT 0 NOT NULL,
    per_customer_limit integer,
    starts_at timestamp(0) without time zone,
    ends_at timestamp(0) without time zone,
    is_active boolean DEFAULT true NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    deleted_at timestamp(0) without time zone
);

ALTER TABLE ONLY public.shop_coupons
    ADD CONSTRAINT shop_coupons_pkey PRIMARY KEY (id);
ALTER TABLE ONLY public.shop_coupons
    ADD CONSTRAINT shop_coupons_seller_id_code_unique UNIQUE (seller_id, code);
CREATE INDEX shop_coupons_product_id_index ON public.shop_coupons USING btree (product_id);
CREATE INDEX shop_coupons_seller_id_is_active_index ON public.shop_coupons USING btree (seller_id, is_active);

CREATE TABLE public.shop_daily_stats (
    id uuid NOT NULL,
    seller_id uuid NOT NULL,
    sales_page_id uuid,
    day date NOT NULL,
    visitors bigint DEFAULT '0'::bigint NOT NULL,
    checkouts bigint DEFAULT '0'::bigint NOT NULL,
    orders bigint DEFAULT '0'::bigint NOT NULL,
    upsell_accepts bigint DEFAULT '0'::bigint NOT NULL,
    revenue_amount bigint DEFAULT '0'::bigint NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);

ALTER TABLE ONLY public.shop_daily_stats
    ADD CONSTRAINT shop_daily_stats_pkey PRIMARY KEY (id);
ALTER TABLE ONLY public.shop_daily_stats
    ADD CONSTRAINT shop_daily_stats_sales_page_id_day_unique UNIQUE (sales_page_id, day);
CREATE INDEX shop_daily_stats_seller_id_day_index ON public.shop_daily_stats USING btree (seller_id, day);

CREATE TABLE public.shop_domains (
    id uuid NOT NULL,
    seller_id uuid NOT NULL,
    sales_page_id uuid NOT NULL,
    host character varying(253) NOT NULL,
    status character varying(16) DEFAULT 'pending'::character varying NOT NULL,
    verification_token character varying(64),
    ssl_active boolean DEFAULT false NOT NULL,
    verified_at timestamp(0) without time zone,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);

ALTER TABLE ONLY public.shop_domains
    ADD CONSTRAINT shop_domains_host_unique UNIQUE (host);
ALTER TABLE ONLY public.shop_domains
    ADD CONSTRAINT shop_domains_pkey PRIMARY KEY (id);
ALTER TABLE ONLY public.shop_domains
    ADD CONSTRAINT shop_domains_sales_page_id_unique UNIQUE (sales_page_id);
CREATE INDEX shop_domains_seller_id_status_index ON public.shop_domains USING btree (seller_id, status);

CREATE TABLE public.shop_download_events (
    id uuid NOT NULL,
    download_id uuid NOT NULL,
    ip_hash character varying(64),
    user_agent character varying(255),
    created_at timestamp(0) without time zone DEFAULT CURRENT_TIMESTAMP NOT NULL
);

ALTER TABLE ONLY public.shop_download_events
    ADD CONSTRAINT shop_download_events_pkey PRIMARY KEY (id);
CREATE INDEX shop_download_events_download_id_created_at_index ON public.shop_download_events USING btree (download_id, created_at);

CREATE TABLE public.shop_downloads (
    id uuid NOT NULL,
    order_item_id uuid NOT NULL,
    product_file_id uuid NOT NULL,
    buyer_user_id uuid,
    token character varying(64) NOT NULL,
    max_downloads integer DEFAULT 5 NOT NULL,
    download_count integer DEFAULT 0 NOT NULL,
    expires_at timestamp(0) without time zone,
    last_downloaded_at timestamp(0) without time zone,
    revoked boolean DEFAULT false NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);

ALTER TABLE ONLY public.shop_downloads
    ADD CONSTRAINT shop_downloads_pkey PRIMARY KEY (id);
ALTER TABLE ONLY public.shop_downloads
    ADD CONSTRAINT shop_downloads_token_unique UNIQUE (token);
CREATE INDEX shop_downloads_buyer_user_id_index ON public.shop_downloads USING btree (buyer_user_id);
CREATE INDEX shop_downloads_order_item_id_index ON public.shop_downloads USING btree (order_item_id);
CREATE INDEX shop_downloads_product_file_id_index ON public.shop_downloads USING btree (product_file_id);

CREATE TABLE public.shop_funnel_steps (
    id uuid NOT NULL,
    funnel_id uuid NOT NULL,
    offer_product_id uuid NOT NULL,
    kind character varying(16) NOT NULL,
    "position" smallint DEFAULT '0'::smallint NOT NULL,
    parent_step_id uuid,
    price_override_amount bigint,
    config jsonb DEFAULT '{}'::jsonb NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);

ALTER TABLE ONLY public.shop_funnel_steps
    ADD CONSTRAINT shop_funnel_steps_pkey PRIMARY KEY (id);
CREATE INDEX shop_funnel_steps_funnel_id_position_index ON public.shop_funnel_steps USING btree (funnel_id, "position");
CREATE INDEX shop_funnel_steps_offer_product_id_index ON public.shop_funnel_steps USING btree (offer_product_id);
CREATE INDEX shop_funnel_steps_parent_step_id_index ON public.shop_funnel_steps USING btree (parent_step_id);

CREATE TABLE public.shop_funnels (
    id uuid NOT NULL,
    seller_id uuid NOT NULL,
    product_id uuid NOT NULL,
    name character varying(160) NOT NULL,
    is_active boolean DEFAULT true NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    deleted_at timestamp(0) without time zone
);

ALTER TABLE ONLY public.shop_funnels
    ADD CONSTRAINT shop_funnels_pkey PRIMARY KEY (id);
CREATE INDEX shop_funnels_product_id_index ON public.shop_funnels USING btree (product_id);
CREATE INDEX shop_funnels_seller_id_is_active_index ON public.shop_funnels USING btree (seller_id, is_active);

CREATE TABLE public.shop_licenses (
    id uuid NOT NULL,
    product_id uuid NOT NULL,
    order_item_id uuid,
    key_ciphertext text NOT NULL,
    status character varying(12) DEFAULT 'available'::character varying NOT NULL,
    delivered_at timestamp(0) without time zone,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);

ALTER TABLE ONLY public.shop_licenses
    ADD CONSTRAINT shop_licenses_pkey PRIMARY KEY (id);
CREATE INDEX shop_licenses_order_item_id_index ON public.shop_licenses USING btree (order_item_id);
CREATE INDEX shop_licenses_pool ON public.shop_licenses USING btree (product_id) WHERE ((status)::text = 'available'::text);

CREATE TABLE public.shop_message_attachments (
    id uuid NOT NULL,
    message_id uuid NOT NULL,
    disk character varying(32) NOT NULL,
    path character varying(400) NOT NULL,
    original_name character varying(200) NOT NULL,
    size_bytes bigint DEFAULT '0'::bigint NOT NULL,
    mime character varying(100),
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);

ALTER TABLE ONLY public.shop_message_attachments
    ADD CONSTRAINT shop_message_attachments_pkey PRIMARY KEY (id);
CREATE INDEX shop_message_attachments_message_id_index ON public.shop_message_attachments USING btree (message_id);

CREATE TABLE public.shop_messages (
    id uuid NOT NULL,
    order_id uuid NOT NULL,
    author_type character varying(12) NOT NULL,
    author_id uuid,
    body text NOT NULL,
    read_at timestamp(0) without time zone,
    created_at timestamp(0) without time zone DEFAULT CURRENT_TIMESTAMP NOT NULL
);

ALTER TABLE ONLY public.shop_messages
    ADD CONSTRAINT shop_messages_pkey PRIMARY KEY (id);
CREATE INDEX shop_messages_author_id_index ON public.shop_messages USING btree (author_id);
CREATE INDEX shop_messages_order_id_created_at_index ON public.shop_messages USING btree (order_id, created_at);

CREATE TABLE public.shop_order_events (
    id uuid NOT NULL,
    order_id uuid NOT NULL,
    type character varying(40) NOT NULL,
    actor_type character varying(12) DEFAULT 'system'::character varying NOT NULL,
    actor_id uuid,
    data jsonb,
    created_at timestamp(0) without time zone DEFAULT CURRENT_TIMESTAMP NOT NULL
);

ALTER TABLE ONLY public.shop_order_events
    ADD CONSTRAINT shop_order_events_pkey PRIMARY KEY (id);
CREATE INDEX shop_order_events_order_id_created_at_index ON public.shop_order_events USING btree (order_id, created_at);

CREATE TABLE public.shop_order_items (
    id uuid NOT NULL,
    order_id uuid NOT NULL,
    product_id uuid NOT NULL,
    variant_id uuid,
    kind character varying(12) DEFAULT 'main'::character varying NOT NULL,
    name_snapshot character varying(190) NOT NULL,
    unit_amount bigint NOT NULL,
    quantity integer DEFAULT 1 NOT NULL,
    line_total_amount bigint NOT NULL,
    commission_amount bigint DEFAULT '0'::bigint NOT NULL,
    seller_net_amount bigint DEFAULT '0'::bigint NOT NULL,
    fulfilment_status character varying(16) DEFAULT 'pending'::character varying NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);

ALTER TABLE ONLY public.shop_order_items
    ADD CONSTRAINT shop_order_items_pkey PRIMARY KEY (id);
CREATE INDEX shop_order_items_order_id_index ON public.shop_order_items USING btree (order_id);
CREATE INDEX shop_order_items_product_id_created_at_index ON public.shop_order_items USING btree (product_id, created_at);
CREATE INDEX shop_order_items_variant_id_index ON public.shop_order_items USING btree (variant_id);

CREATE TABLE public.shop_orders (
    id uuid NOT NULL,
    number character varying(20) NOT NULL,
    seller_id uuid NOT NULL,
    buyer_user_id uuid NOT NULL,
    sales_page_id uuid,
    funnel_id uuid,
    coupon_id uuid,
    status character varying(24) DEFAULT 'pending'::character varying NOT NULL,
    subtotal_amount bigint DEFAULT '0'::bigint NOT NULL,
    discount_amount bigint DEFAULT '0'::bigint NOT NULL,
    tax_amount bigint DEFAULT '0'::bigint NOT NULL,
    shipping_amount bigint DEFAULT '0'::bigint NOT NULL,
    total_amount bigint DEFAULT '0'::bigint NOT NULL,
    commission_amount bigint DEFAULT '0'::bigint NOT NULL,
    seller_net_amount bigint DEFAULT '0'::bigint NOT NULL,
    asset_id bigint NOT NULL,
    payment_method character varying(24) DEFAULT 'poisapay'::character varying NOT NULL,
    ledger_entry_id uuid,
    refund_window_ends_at timestamp(0) without time zone,
    shipping_address jsonb,
    utm jsonb,
    idempotency_key character varying(190) NOT NULL,
    paid_at timestamp(0) without time zone,
    last_message_at timestamp(0) without time zone,
    seller_unread boolean DEFAULT false NOT NULL,
    buyer_unread boolean DEFAULT false NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    parent_order_id uuid,
    earnings_held boolean DEFAULT false NOT NULL,
    earnings_released_at timestamp(0) without time zone,
    refunded_at timestamp(0) without time zone,
    refunded_amount bigint
);

ALTER TABLE ONLY public.shop_orders
    ADD CONSTRAINT shop_orders_idempotency_key_unique UNIQUE (idempotency_key);
ALTER TABLE ONLY public.shop_orders
    ADD CONSTRAINT shop_orders_number_unique UNIQUE (number);
ALTER TABLE ONLY public.shop_orders
    ADD CONSTRAINT shop_orders_pkey PRIMARY KEY (id);
CREATE INDEX shop_orders_asset_id_index ON public.shop_orders USING btree (asset_id);
CREATE INDEX shop_orders_buyer_user_id_created_at_index ON public.shop_orders USING btree (buyer_user_id, created_at);
CREATE INDEX shop_orders_coupon_id_index ON public.shop_orders USING btree (coupon_id);
CREATE INDEX shop_orders_earnings_release ON public.shop_orders USING btree (refund_window_ends_at) WHERE (earnings_held AND (earnings_released_at IS NULL));
CREATE INDEX shop_orders_funnel_id_index ON public.shop_orders USING btree (funnel_id);
CREATE INDEX shop_orders_inbox ON public.shop_orders USING btree (seller_id, last_message_at DESC) WHERE (last_message_at IS NOT NULL);
CREATE INDEX shop_orders_ledger_entry_id_index ON public.shop_orders USING btree (ledger_entry_id);
CREATE INDEX shop_orders_parent_order_id_index ON public.shop_orders USING btree (parent_order_id);
CREATE INDEX shop_orders_sales_page_id_index ON public.shop_orders USING btree (sales_page_id);
CREATE INDEX shop_orders_seller_id_buyer_user_id_created_at_index ON public.shop_orders USING btree (seller_id, buyer_user_id, created_at);
CREATE INDEX shop_orders_seller_id_created_at_index ON public.shop_orders USING btree (seller_id, created_at);
CREATE INDEX shop_orders_seller_id_status_created_at_index ON public.shop_orders USING btree (seller_id, status, created_at);
CREATE INDEX shop_orders_vesting ON public.shop_orders USING btree (refund_window_ends_at) WHERE (((status)::text = 'paid'::text) AND (refund_window_ends_at IS NOT NULL));

CREATE TABLE public.shop_page_revisions (
    id uuid NOT NULL,
    sales_page_id uuid NOT NULL,
    author_user_id uuid,
    version integer NOT NULL,
    label character varying(80),
    document jsonb NOT NULL,
    created_at timestamp(0) without time zone
);

ALTER TABLE ONLY public.shop_page_revisions
    ADD CONSTRAINT shop_page_revisions_pkey PRIMARY KEY (id);
CREATE INDEX shop_page_revisions_author_user_id_index ON public.shop_page_revisions USING btree (author_user_id);
CREATE INDEX shop_page_revisions_sales_page_id_version_index ON public.shop_page_revisions USING btree (sales_page_id, version);

CREATE TABLE public.shop_product_files (
    id uuid NOT NULL,
    product_id uuid NOT NULL,
    version character varying(32),
    changelog text,
    disk character varying(32) NOT NULL,
    path character varying(400) NOT NULL,
    original_name character varying(200) NOT NULL,
    size_bytes bigint DEFAULT '0'::bigint NOT NULL,
    checksum_sha256 character varying(64),
    scan_status character varying(16) DEFAULT 'pending'::character varying NOT NULL,
    is_current boolean DEFAULT true NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);

ALTER TABLE ONLY public.shop_product_files
    ADD CONSTRAINT shop_product_files_pkey PRIMARY KEY (id);
CREATE INDEX shop_product_files_product_id_is_current_index ON public.shop_product_files USING btree (product_id, is_current);

CREATE TABLE public.shop_product_media (
    id uuid NOT NULL,
    product_id uuid NOT NULL,
    kind character varying(12) DEFAULT 'image'::character varying NOT NULL,
    url character varying(400) NOT NULL,
    "position" smallint DEFAULT '0'::smallint NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);

ALTER TABLE ONLY public.shop_product_media
    ADD CONSTRAINT shop_product_media_pkey PRIMARY KEY (id);
CREATE INDEX shop_product_media_product_id_position_index ON public.shop_product_media USING btree (product_id, "position");

CREATE TABLE public.shop_product_variants (
    id uuid NOT NULL,
    product_id uuid NOT NULL,
    sku character varying(64),
    options jsonb NOT NULL,
    price_amount bigint,
    stock integer,
    weight_grams integer,
    "position" smallint DEFAULT '0'::smallint NOT NULL,
    is_active boolean DEFAULT true NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);

ALTER TABLE ONLY public.shop_product_variants
    ADD CONSTRAINT shop_product_variants_pkey PRIMARY KEY (id);
ALTER TABLE ONLY public.shop_product_variants
    ADD CONSTRAINT shop_product_variants_product_id_sku_unique UNIQUE (product_id, sku);
CREATE INDEX shop_product_variants_product_id_position_index ON public.shop_product_variants USING btree (product_id, "position");

CREATE TABLE public.shop_products (
    id uuid NOT NULL,
    seller_id uuid NOT NULL,
    type character varying(24) DEFAULT 'digital'::character varying NOT NULL,
    name character varying(190) NOT NULL,
    slug character varying(200) NOT NULL,
    summary character varying(300),
    description text,
    status character varying(16) DEFAULT 'draft'::character varying NOT NULL,
    price_amount bigint DEFAULT '0'::bigint NOT NULL,
    price_asset_id bigint NOT NULL,
    compare_price_amount bigint,
    has_variants boolean DEFAULT false NOT NULL,
    requires_shipping boolean DEFAULT false NOT NULL,
    attributes jsonb DEFAULT '{}'::jsonb NOT NULL,
    meta jsonb DEFAULT '{}'::jsonb NOT NULL,
    sales_count bigint DEFAULT '0'::bigint NOT NULL,
    review_count integer DEFAULT 0 NOT NULL,
    rating_bps integer DEFAULT 0 NOT NULL,
    published_at timestamp(0) without time zone,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    deleted_at timestamp(0) without time zone,
    search_vector tsvector GENERATED ALWAYS AS (to_tsvector('simple'::regconfig, (((COALESCE(name, ''::character varying))::text || ' '::text) || (COALESCE(summary, ''::character varying))::text))) STORED
);

ALTER TABLE ONLY public.shop_products
    ADD CONSTRAINT shop_products_pkey PRIMARY KEY (id);
CREATE INDEX shop_products_price_asset_id_index ON public.shop_products USING btree (price_asset_id);
CREATE INDEX shop_products_search_gin ON public.shop_products USING gin (search_vector);
CREATE INDEX shop_products_seller_id_status_created_at_index ON public.shop_products USING btree (seller_id, status, created_at);
CREATE UNIQUE INDEX shop_products_seller_slug_unique ON public.shop_products USING btree (seller_id, slug) WHERE (deleted_at IS NULL);
CREATE INDEX shop_products_type_status_index ON public.shop_products USING btree (type, status);

CREATE TABLE public.shop_refund_requests (
    id uuid NOT NULL,
    order_id uuid NOT NULL,
    seller_id uuid NOT NULL,
    buyer_user_id uuid NOT NULL,
    type character varying(12) DEFAULT 'full'::character varying NOT NULL,
    amount_requested bigint NOT NULL,
    amount_refunded bigint,
    reason text,
    status character varying(16) DEFAULT 'requested'::character varying NOT NULL,
    resolver_type character varying(12),
    resolver_id uuid,
    resolution_note text,
    ledger_entry_id uuid,
    sla_due_at timestamp(0) without time zone,
    escalated_at timestamp(0) without time zone,
    resolved_at timestamp(0) without time zone,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);

ALTER TABLE ONLY public.shop_refund_requests
    ADD CONSTRAINT shop_refund_requests_pkey PRIMARY KEY (id);
CREATE INDEX shop_refund_requests_buyer_user_id_index ON public.shop_refund_requests USING btree (buyer_user_id);
CREATE INDEX shop_refund_requests_ledger_entry_id_index ON public.shop_refund_requests USING btree (ledger_entry_id);
CREATE UNIQUE INDEX shop_refund_requests_one_open ON public.shop_refund_requests USING btree (order_id) WHERE ((status)::text = ANY ((ARRAY['requested'::character varying, 'escalated'::character varying])::text[]));
CREATE INDEX shop_refund_requests_resolver_id_index ON public.shop_refund_requests USING btree (resolver_id);
CREATE INDEX shop_refund_requests_seller_id_status_index ON public.shop_refund_requests USING btree (seller_id, status);
CREATE INDEX shop_refund_requests_sla ON public.shop_refund_requests USING btree (sla_due_at) WHERE ((status)::text = 'requested'::text);

CREATE TABLE public.shop_reviews (
    id uuid NOT NULL,
    seller_id uuid NOT NULL,
    product_id uuid NOT NULL,
    order_id uuid NOT NULL,
    buyer_user_id uuid,
    rating smallint NOT NULL,
    title character varying(160),
    body text,
    media jsonb,
    status character varying(12) DEFAULT 'published'::character varying NOT NULL,
    seller_reply text,
    seller_replied_at timestamp(0) without time zone,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);

ALTER TABLE ONLY public.shop_reviews
    ADD CONSTRAINT shop_reviews_order_id_product_id_unique UNIQUE (order_id, product_id);
ALTER TABLE ONLY public.shop_reviews
    ADD CONSTRAINT shop_reviews_pkey PRIMARY KEY (id);
CREATE INDEX shop_reviews_buyer_user_id_index ON public.shop_reviews USING btree (buyer_user_id);
CREATE INDEX shop_reviews_product_id_status_created_at_index ON public.shop_reviews USING btree (product_id, status, created_at);
CREATE INDEX shop_reviews_seller_id_index ON public.shop_reviews USING btree (seller_id);

CREATE TABLE public.shop_sales_pages (
    id uuid NOT NULL,
    seller_id uuid NOT NULL,
    product_id uuid NOT NULL,
    name character varying(160) NOT NULL,
    slug character varying(200) NOT NULL,
    status character varying(16) DEFAULT 'draft'::character varying NOT NULL,
    sections jsonb DEFAULT '[]'::jsonb NOT NULL,
    theme jsonb DEFAULT '{}'::jsonb NOT NULL,
    seo jsonb DEFAULT '{}'::jsonb NOT NULL,
    tracking jsonb DEFAULT '{}'::jsonb NOT NULL,
    version integer DEFAULT 1 NOT NULL,
    published_at timestamp(0) without time zone,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    deleted_at timestamp(0) without time zone,
    bump_product_id uuid,
    bump_price_amount bigint,
    bump_headline character varying(160),
    bump_description character varying(400),
    upsell_product_id uuid,
    upsell_price_amount bigint,
    upsell_headline character varying(160),
    upsell_description character varying(400),
    draft jsonb
);

ALTER TABLE ONLY public.shop_sales_pages
    ADD CONSTRAINT shop_sales_pages_pkey PRIMARY KEY (id);
CREATE INDEX shop_sales_pages_bump_product_id_index ON public.shop_sales_pages USING btree (bump_product_id);
CREATE INDEX shop_sales_pages_product_id_index ON public.shop_sales_pages USING btree (product_id);
CREATE INDEX shop_sales_pages_published ON public.shop_sales_pages USING btree (seller_id) WHERE (((status)::text = 'published'::text) AND (deleted_at IS NULL));
CREATE INDEX shop_sales_pages_seller_id_status_index ON public.shop_sales_pages USING btree (seller_id, status);
CREATE UNIQUE INDEX shop_sales_pages_slug_unique ON public.shop_sales_pages USING btree (slug) WHERE (deleted_at IS NULL);
CREATE INDEX shop_sales_pages_upsell_product_id_index ON public.shop_sales_pages USING btree (upsell_product_id);

CREATE TABLE public.shop_saved_blocks (
    id uuid NOT NULL,
    seller_id uuid NOT NULL,
    name character varying(120) NOT NULL,
    kind character varying(24) DEFAULT 'section'::character varying NOT NULL,
    node jsonb NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);

ALTER TABLE ONLY public.shop_saved_blocks
    ADD CONSTRAINT shop_saved_blocks_pkey PRIMARY KEY (id);
CREATE INDEX shop_saved_blocks_seller_id_kind_index ON public.shop_saved_blocks USING btree (seller_id, kind);

CREATE TABLE public.shop_seller_applications (
    id uuid NOT NULL,
    seller_id uuid NOT NULL,
    snapshot jsonb NOT NULL,
    status character varying(16) DEFAULT 'pending'::character varying NOT NULL,
    submitted_at timestamp(0) without time zone NOT NULL,
    decided_by uuid,
    decided_at timestamp(0) without time zone,
    notes text,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);

ALTER TABLE ONLY public.shop_seller_applications
    ADD CONSTRAINT shop_seller_applications_pkey PRIMARY KEY (id);
CREATE INDEX shop_seller_applications_decided_by_index ON public.shop_seller_applications USING btree (decided_by);
CREATE INDEX shop_seller_applications_seller_id_submitted_at_index ON public.shop_seller_applications USING btree (seller_id, submitted_at);

CREATE TABLE public.shop_sellers (
    id uuid NOT NULL,
    user_id uuid NOT NULL,
    brand_name character varying(120),
    bio text,
    website character varying(190),
    country character(2),
    categories jsonb DEFAULT '[]'::jsonb NOT NULL,
    status character varying(20) DEFAULT 'draft'::character varying NOT NULL,
    commission_bps integer,
    settlement_asset_id bigint,
    kyc_reference character varying(64),
    plan character varying(24) DEFAULT 'free'::character varying NOT NULL,
    reviewed_by uuid,
    reviewed_at timestamp(0) without time zone,
    approved_at timestamp(0) without time zone,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    deleted_at timestamp(0) without time zone,
    logo_path character varying(255)
);

ALTER TABLE ONLY public.shop_sellers
    ADD CONSTRAINT shop_sellers_pkey PRIMARY KEY (id);
ALTER TABLE ONLY public.shop_sellers
    ADD CONSTRAINT shop_sellers_user_id_unique UNIQUE (user_id);
CREATE INDEX shop_sellers_reviewed_by_index ON public.shop_sellers USING btree (reviewed_by);
CREATE INDEX shop_sellers_settlement_asset_id_index ON public.shop_sellers USING btree (settlement_asset_id);
CREATE INDEX shop_sellers_status_created_at_index ON public.shop_sellers USING btree (status, created_at);

CREATE TABLE public.shop_shipments (
    id uuid NOT NULL,
    order_id uuid NOT NULL,
    carrier character varying(40),
    tracking_number character varying(100),
    status character varying(16) DEFAULT 'pending'::character varying NOT NULL,
    address jsonb,
    shipped_at timestamp(0) without time zone,
    delivered_at timestamp(0) without time zone,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);

ALTER TABLE ONLY public.shop_shipments
    ADD CONSTRAINT shop_shipments_pkey PRIMARY KEY (id);
CREATE INDEX shop_shipments_order_id_status_index ON public.shop_shipments USING btree (order_id, status);


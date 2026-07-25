-- ============================================================
-- Module: p2p
-- GENERATED from Laravel migrations (source of truth) via pg_dump.
-- Do not edit by hand; regenerate with database/schema/regenerate.sh
-- ============================================================

CREATE TABLE public.p2p_ad_payment_methods (
    ad_id uuid NOT NULL,
    payment_method_id uuid NOT NULL
);

ALTER TABLE ONLY public.p2p_ad_payment_methods
    ADD CONSTRAINT p2p_ad_payment_methods_pkey PRIMARY KEY (ad_id, payment_method_id);
CREATE INDEX p2p_ad_payment_methods_payment_method_id_index ON public.p2p_ad_payment_methods USING btree (payment_method_id);

CREATE TABLE public.p2p_ads (
    id uuid NOT NULL,
    user_id uuid NOT NULL,
    side character varying(8) NOT NULL,
    asset_id bigint NOT NULL,
    fiat_currency character(3) DEFAULT 'BDT'::bpchar NOT NULL,
    price_type character varying(10) NOT NULL,
    fixed_price numeric(38,4),
    margin_bps integer,
    min_order numeric(38,2) NOT NULL,
    max_order numeric(38,2) NOT NULL,
    available_amount numeric(78,0) NOT NULL,
    total_amount numeric(78,0) NOT NULL,
    daily_limit numeric(78,0),
    payment_window_min smallint DEFAULT '15'::smallint NOT NULL,
    min_completion_bps integer,
    auto_reply text,
    terms text,
    countries jsonb,
    trade_hours jsonb,
    status character varying(12) DEFAULT 'active'::character varying NOT NULL,
    priority integer DEFAULT 0 NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    deleted_at timestamp(0) without time zone
);

ALTER TABLE ONLY public.p2p_ads
    ADD CONSTRAINT p2p_ads_pkey PRIMARY KEY (id);
CREATE INDEX ix_p2p_ads_book ON public.p2p_ads USING btree (side, status, asset_id);
CREATE INDEX p2p_ads_asset_id_index ON public.p2p_ads USING btree (asset_id);
CREATE INDEX p2p_ads_user_id_status_index ON public.p2p_ads USING btree (user_id, status);

CREATE TABLE public.p2p_blocks (
    id uuid NOT NULL,
    user_id uuid NOT NULL,
    blocked_id uuid NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);

ALTER TABLE ONLY public.p2p_blocks
    ADD CONSTRAINT p2p_blocks_pkey PRIMARY KEY (id);
ALTER TABLE ONLY public.p2p_blocks
    ADD CONSTRAINT uq_p2p_block UNIQUE (user_id, blocked_id);
CREATE INDEX p2p_blocks_blocked_id_index ON public.p2p_blocks USING btree (blocked_id);

CREATE TABLE public.p2p_dispute_evidence (
    id uuid NOT NULL,
    dispute_id uuid NOT NULL,
    uploaded_by uuid NOT NULL,
    uploader_role character varying(8) DEFAULT 'user'::character varying NOT NULL,
    path character varying(255) NOT NULL,
    note character varying(255),
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);

ALTER TABLE ONLY public.p2p_dispute_evidence
    ADD CONSTRAINT p2p_dispute_evidence_pkey PRIMARY KEY (id);
CREATE INDEX p2p_dispute_evidence_dispute_id_index ON public.p2p_dispute_evidence USING btree (dispute_id);

CREATE TABLE public.p2p_dispute_notes (
    id uuid NOT NULL,
    dispute_id uuid NOT NULL,
    admin_id uuid NOT NULL,
    body text NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);

ALTER TABLE ONLY public.p2p_dispute_notes
    ADD CONSTRAINT p2p_dispute_notes_pkey PRIMARY KEY (id);
CREATE INDEX ix_p2p_dispute_note ON public.p2p_dispute_notes USING btree (dispute_id, created_at);
CREATE INDEX p2p_dispute_notes_admin_id_index ON public.p2p_dispute_notes USING btree (admin_id);

CREATE TABLE public.p2p_disputes (
    id uuid NOT NULL,
    order_id uuid NOT NULL,
    opened_by uuid NOT NULL,
    opened_by_role character varying(8) DEFAULT 'user'::character varying NOT NULL,
    reason character varying(64) NOT NULL,
    detail text,
    status character varying(16) DEFAULT 'open'::character varying NOT NULL,
    assigned_admin_id uuid,
    resolution character varying(255),
    resolved_by uuid,
    resolved_at timestamp(0) without time zone,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);

ALTER TABLE ONLY public.p2p_disputes
    ADD CONSTRAINT p2p_disputes_pkey PRIMARY KEY (id);
ALTER TABLE ONLY public.p2p_disputes
    ADD CONSTRAINT uq_p2p_dispute_order UNIQUE (order_id);
CREATE INDEX p2p_disputes_assigned_admin_id_index ON public.p2p_disputes USING btree (assigned_admin_id);
CREATE INDEX p2p_disputes_opened_by_index ON public.p2p_disputes USING btree (opened_by);
CREATE INDEX p2p_disputes_resolved_by_index ON public.p2p_disputes USING btree (resolved_by);

CREATE TABLE public.p2p_escrows (
    id uuid NOT NULL,
    order_id uuid NOT NULL,
    user_id uuid NOT NULL,
    asset_id bigint NOT NULL,
    amount numeric(78,0) NOT NULL,
    status character varying(10) DEFAULT 'locked'::character varying NOT NULL,
    lock_entry_id uuid,
    release_entry_id uuid,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);

ALTER TABLE ONLY public.p2p_escrows
    ADD CONSTRAINT p2p_escrows_pkey PRIMARY KEY (id);
ALTER TABLE ONLY public.p2p_escrows
    ADD CONSTRAINT uq_p2p_escrow_order UNIQUE (order_id);
CREATE INDEX p2p_escrows_asset_id_index ON public.p2p_escrows USING btree (asset_id);
CREATE INDEX p2p_escrows_lock_entry_id_index ON public.p2p_escrows USING btree (lock_entry_id);
CREATE INDEX p2p_escrows_release_entry_id_index ON public.p2p_escrows USING btree (release_entry_id);
CREATE INDEX p2p_escrows_user_id_index ON public.p2p_escrows USING btree (user_id);

CREATE TABLE public.p2p_favorites (
    id uuid NOT NULL,
    user_id uuid NOT NULL,
    merchant_id uuid NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);

ALTER TABLE ONLY public.p2p_favorites
    ADD CONSTRAINT p2p_favorites_pkey PRIMARY KEY (id);
ALTER TABLE ONLY public.p2p_favorites
    ADD CONSTRAINT uq_p2p_fav UNIQUE (user_id, merchant_id);
CREATE INDEX p2p_favorites_merchant_id_index ON public.p2p_favorites USING btree (merchant_id);

CREATE TABLE public.p2p_merchant_profiles (
    id uuid NOT NULL,
    user_id uuid NOT NULL,
    is_online boolean DEFAULT false NOT NULL,
    vacation_mode boolean DEFAULT false NOT NULL,
    level smallint DEFAULT '0'::smallint NOT NULL,
    badges jsonb,
    trade_count integer DEFAULT 0 NOT NULL,
    completed_count integer DEFAULT 0 NOT NULL,
    completion_rate_bps integer DEFAULT 0 NOT NULL,
    avg_release_seconds integer,
    avg_pay_seconds integer,
    total_volume numeric(78,0) DEFAULT '0'::numeric NOT NULL,
    rating numeric(3,2) DEFAULT '0'::numeric NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    review_count integer DEFAULT 0 NOT NULL,
    positive_count integer DEFAULT 0 NOT NULL
);

ALTER TABLE ONLY public.p2p_merchant_profiles
    ADD CONSTRAINT p2p_merchant_profiles_pkey PRIMARY KEY (id);
ALTER TABLE ONLY public.p2p_merchant_profiles
    ADD CONSTRAINT uq_p2p_merchant_user UNIQUE (user_id);

CREATE TABLE public.p2p_order_events (
    id uuid NOT NULL,
    order_id uuid NOT NULL,
    actor_type character varying(8),
    actor_id uuid,
    from_status character varying(20),
    to_status character varying(20) NOT NULL,
    note character varying(255),
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);

ALTER TABLE ONLY public.p2p_order_events
    ADD CONSTRAINT p2p_order_events_pkey PRIMARY KEY (id);
CREATE INDEX ix_p2p_evt_order ON public.p2p_order_events USING btree (order_id, created_at);

CREATE TABLE public.p2p_order_messages (
    id uuid NOT NULL,
    order_id uuid NOT NULL,
    sender_type character varying(8) NOT NULL,
    sender_id uuid,
    type character varying(10) DEFAULT 'text'::character varying NOT NULL,
    body text,
    attachment_path character varying(255),
    read_at timestamp(0) without time zone,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);

ALTER TABLE ONLY public.p2p_order_messages
    ADD CONSTRAINT p2p_order_messages_pkey PRIMARY KEY (id);
CREATE INDEX ix_p2p_msg_order ON public.p2p_order_messages USING btree (order_id, created_at);
CREATE INDEX p2p_order_messages_sender_id_index ON public.p2p_order_messages USING btree (sender_id);

CREATE TABLE public.p2p_orders (
    id uuid NOT NULL,
    ref character varying(20) NOT NULL,
    ad_id uuid NOT NULL,
    buyer_id uuid NOT NULL,
    seller_id uuid NOT NULL,
    asset_id bigint NOT NULL,
    crypto_amount numeric(78,0) NOT NULL,
    fee_amount numeric(78,0) DEFAULT '0'::numeric NOT NULL,
    net_amount numeric(78,0) NOT NULL,
    taker_fee_bps integer DEFAULT 0 NOT NULL,
    fiat_amount numeric(38,2) NOT NULL,
    price numeric(38,4) NOT NULL,
    fiat_currency character(3) DEFAULT 'BDT'::bpchar NOT NULL,
    payment_method_id uuid,
    status character varying(20) DEFAULT 'waiting_payment'::character varying NOT NULL,
    expires_at timestamp(0) without time zone,
    buyer_paid_at timestamp(0) without time zone,
    released_at timestamp(0) without time zone,
    cancelled_at timestamp(0) without time zone,
    cancel_reason character varying(64),
    meta jsonb,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);

ALTER TABLE ONLY public.p2p_orders
    ADD CONSTRAINT p2p_orders_pkey PRIMARY KEY (id);
ALTER TABLE ONLY public.p2p_orders
    ADD CONSTRAINT uq_p2p_order_ref UNIQUE (ref);
CREATE INDEX ix_p2p_orders_ad_day ON public.p2p_orders USING btree (ad_id, created_at);
CREATE INDEX ix_p2p_orders_expiry ON public.p2p_orders USING btree (status, expires_at);
CREATE INDEX p2p_orders_ad_id_index ON public.p2p_orders USING btree (ad_id);
CREATE INDEX p2p_orders_asset_id_index ON public.p2p_orders USING btree (asset_id);
CREATE INDEX p2p_orders_buyer_id_index ON public.p2p_orders USING btree (buyer_id);
CREATE INDEX p2p_orders_payment_method_id_index ON public.p2p_orders USING btree (payment_method_id);
CREATE INDEX p2p_orders_seller_id_index ON public.p2p_orders USING btree (seller_id);

CREATE TABLE public.p2p_payment_methods (
    id uuid NOT NULL,
    key character varying(32) NOT NULL,
    name character varying(64) NOT NULL,
    type character varying(16) NOT NULL,
    country character(2),
    icon character varying(64),
    fields jsonb,
    is_active boolean DEFAULT true NOT NULL,
    sort integer DEFAULT 0 NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);

ALTER TABLE ONLY public.p2p_payment_methods
    ADD CONSTRAINT p2p_payment_methods_pkey PRIMARY KEY (id);
ALTER TABLE ONLY public.p2p_payment_methods
    ADD CONSTRAINT uq_p2p_pm_key UNIQUE (key);

CREATE TABLE public.p2p_reviews (
    id uuid NOT NULL,
    order_id uuid NOT NULL,
    rater_id uuid NOT NULL,
    ratee_id uuid NOT NULL,
    rating smallint NOT NULL,
    is_positive boolean NOT NULL,
    comment character varying(500),
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);

ALTER TABLE ONLY public.p2p_reviews
    ADD CONSTRAINT p2p_reviews_pkey PRIMARY KEY (id);
ALTER TABLE ONLY public.p2p_reviews
    ADD CONSTRAINT uq_p2p_review_order_rater UNIQUE (order_id, rater_id);
CREATE INDEX ix_p2p_review_ratee ON public.p2p_reviews USING btree (ratee_id, created_at);
CREATE INDEX p2p_reviews_rater_id_index ON public.p2p_reviews USING btree (rater_id);

CREATE TABLE public.p2p_user_payment_methods (
    id uuid NOT NULL,
    user_id uuid NOT NULL,
    payment_method_id uuid NOT NULL,
    label character varying(64),
    account text NOT NULL,
    is_active boolean DEFAULT true NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    is_default boolean DEFAULT false NOT NULL
);

ALTER TABLE ONLY public.p2p_user_payment_methods
    ADD CONSTRAINT p2p_user_payment_methods_pkey PRIMARY KEY (id);
CREATE INDEX p2p_user_payment_methods_payment_method_id_index ON public.p2p_user_payment_methods USING btree (payment_method_id);
CREATE INDEX p2p_user_payment_methods_user_id_is_active_index ON public.p2p_user_payment_methods USING btree (user_id, is_active);


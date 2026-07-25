-- ============================================================
-- Module: cards
-- GENERATED from Laravel migrations (source of truth) via pg_dump.
-- Do not edit by hand; regenerate with database/schema/regenerate.sh
-- ============================================================

CREATE TABLE public.cards (
    id uuid NOT NULL,
    user_id uuid NOT NULL,
    program character varying(24) NOT NULL,
    type character varying(8) NOT NULL,
    network character varying(12) NOT NULL,
    issuer_card_ref character varying(128) NOT NULL,
    last4 character(4),
    nickname character varying(48),
    online_enabled boolean DEFAULT true NOT NULL,
    atm_enabled boolean DEFAULT true NOT NULL,
    contactless_enabled boolean DEFAULT true NOT NULL,
    allowed_countries jsonb,
    blocked_mccs jsonb,
    pin_hash text,
    replaced_by uuid,
    closed_at timestamp(0) without time zone,
    status character varying(16) DEFAULT 'inactive'::character varying NOT NULL,
    daily_limit numeric(38,0),
    per_tx_limit numeric(38,0),
    settlement_currency character(3) DEFAULT 'USD'::bpchar NOT NULL,
    frozen_by uuid,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    card_provider_id uuid,
    cardholder_ref character varying(128),
    exp_month smallint,
    exp_year smallint,
    CONSTRAINT ck_no_pan CHECK (((length((issuer_card_ref)::text) > 19) OR (((issuer_card_ref)::text !~~ '4%'::text) AND ((issuer_card_ref)::text !~~ '5%'::text))))
);

ALTER TABLE ONLY public.cards
    ADD CONSTRAINT cards_pkey PRIMARY KEY (id);
ALTER TABLE ONLY public.cards
    ADD CONSTRAINT uq_issuer_card UNIQUE (issuer_card_ref);
CREATE INDEX cards_card_provider_id_index ON public.cards USING btree (card_provider_id);
CREATE INDEX cards_frozen_by_index ON public.cards USING btree (frozen_by);
CREATE INDEX cards_replaced_by_index ON public.cards USING btree (replaced_by);
CREATE INDEX cards_user_id_status_index ON public.cards USING btree (user_id, status);

CREATE TABLE public.card_authorizations (
    id uuid NOT NULL,
    card_id uuid NOT NULL,
    network_auth_id character varying(64) NOT NULL,
    amount numeric(38,0) NOT NULL,
    currency_code character(3) NOT NULL,
    mcc character(4),
    merchant character varying(128),
    funding_asset_id bigint,
    held_amount numeric(78,0),
    quote_id uuid,
    status character varying(16) DEFAULT 'approved'::character varying NOT NULL,
    hold_entry_id uuid,
    settle_entry_id uuid,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);

ALTER TABLE ONLY public.card_authorizations
    ADD CONSTRAINT card_authorizations_pkey PRIMARY KEY (id);
ALTER TABLE ONLY public.card_authorizations
    ADD CONSTRAINT uq_network_auth UNIQUE (network_auth_id);
CREATE INDEX card_authorizations_card_id_created_at_index ON public.card_authorizations USING btree (card_id, created_at);
CREATE INDEX card_authorizations_hold_entry_id_index ON public.card_authorizations USING btree (hold_entry_id);
CREATE INDEX card_authorizations_quote_id_index ON public.card_authorizations USING btree (quote_id);
CREATE INDEX card_authorizations_settle_entry_id_index ON public.card_authorizations USING btree (settle_entry_id);
CREATE INDEX pp_idx_card_authorizations_funding_asset ON public.card_authorizations USING btree (funding_asset_id);
CREATE INDEX pp_idx_card_authorizations_status ON public.card_authorizations USING btree (status);

CREATE TABLE public.card_disputes (
    id uuid NOT NULL,
    authorization_id uuid NOT NULL,
    reason character varying(48) NOT NULL,
    status character varying(16) DEFAULT 'open'::character varying NOT NULL,
    amount numeric(38,0) NOT NULL,
    entry_id uuid,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);

ALTER TABLE ONLY public.card_disputes
    ADD CONSTRAINT card_disputes_pkey PRIMARY KEY (id);
CREATE INDEX card_disputes_authorization_id_index ON public.card_disputes USING btree (authorization_id);
CREATE INDEX card_disputes_entry_id_index ON public.card_disputes USING btree (entry_id);

CREATE TABLE public.card_providers (
    id uuid NOT NULL,
    name character varying(255) NOT NULL,
    slug character varying(255) NOT NULL,
    network character varying(12) DEFAULT 'visa'::character varying NOT NULL,
    bin character varying(8),
    supports_virtual boolean DEFAULT true NOT NULL,
    supports_physical boolean DEFAULT false NOT NULL,
    settlement_currency character(3) DEFAULT 'USD'::bpchar NOT NULL,
    api_base character varying(160),
    is_demo boolean DEFAULT true NOT NULL,
    is_active boolean DEFAULT true NOT NULL,
    sort smallint DEFAULT '0'::smallint NOT NULL,
    config jsonb,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    driver character varying(24) DEFAULT 'mock'::character varying NOT NULL
);

ALTER TABLE ONLY public.card_providers
    ADD CONSTRAINT card_providers_pkey PRIMARY KEY (id);
ALTER TABLE ONLY public.card_providers
    ADD CONSTRAINT card_providers_slug_unique UNIQUE (slug);

CREATE TABLE public.provider_accounts (
    id uuid NOT NULL,
    user_id uuid NOT NULL,
    card_provider_id uuid NOT NULL,
    driver character varying(24) NOT NULL,
    provider_ref character varying(128) NOT NULL,
    status character varying(24),
    metadata jsonb,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);

ALTER TABLE ONLY public.provider_accounts
    ADD CONSTRAINT provider_accounts_pkey PRIMARY KEY (id);
ALTER TABLE ONLY public.provider_accounts
    ADD CONSTRAINT uq_provider_account_token UNIQUE (driver, provider_ref);
ALTER TABLE ONLY public.provider_accounts
    ADD CONSTRAINT uq_provider_account_user_program UNIQUE (user_id, card_provider_id);
CREATE INDEX provider_accounts_card_provider_id_index ON public.provider_accounts USING btree (card_provider_id);

CREATE TABLE public.card_provider_logs (
    id uuid NOT NULL,
    card_provider_id uuid,
    driver character varying(24) NOT NULL,
    card_id uuid,
    direction character varying(8) NOT NULL,
    operation character varying(64) NOT NULL,
    method character varying(8),
    endpoint character varying(200),
    request jsonb,
    response jsonb,
    status_code smallint,
    latency_ms integer,
    success boolean DEFAULT true NOT NULL,
    error character varying(255),
    created_at timestamp(0) without time zone
);

ALTER TABLE ONLY public.card_provider_logs
    ADD CONSTRAINT card_provider_logs_pkey PRIMARY KEY (id);
CREATE INDEX card_provider_logs_card_id_index ON public.card_provider_logs USING btree (card_id);
CREATE INDEX card_provider_logs_card_provider_id_index ON public.card_provider_logs USING btree (card_provider_id);
CREATE INDEX card_provider_logs_driver_created_at_index ON public.card_provider_logs USING btree (driver, created_at);
CREATE INDEX card_provider_logs_operation_index ON public.card_provider_logs USING btree (operation);

CREATE TABLE public.card_metadata (
    id uuid NOT NULL,
    card_id uuid NOT NULL,
    key character varying(64) NOT NULL,
    value text,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);

ALTER TABLE ONLY public.card_metadata
    ADD CONSTRAINT card_metadata_pkey PRIMARY KEY (id);
ALTER TABLE ONLY public.card_metadata
    ADD CONSTRAINT uq_card_metadata_key UNIQUE (card_id, key);

CREATE TABLE public.card_webhooks (
    id uuid NOT NULL,
    driver character varying(24) NOT NULL,
    provider_event_id character varying(128) NOT NULL,
    event_type character varying(40) NOT NULL,
    provider_card_ref character varying(128),
    provider_tx_ref character varying(128),
    payload jsonb,
    signature_valid boolean DEFAULT false NOT NULL,
    status character varying(16) DEFAULT 'pending'::character varying NOT NULL,
    attempts smallint DEFAULT '0'::smallint NOT NULL,
    error character varying(255),
    received_at timestamp(0) without time zone,
    processed_at timestamp(0) without time zone,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);

ALTER TABLE ONLY public.card_webhooks
    ADD CONSTRAINT card_webhooks_pkey PRIMARY KEY (id);
ALTER TABLE ONLY public.card_webhooks
    ADD CONSTRAINT uq_card_webhook_event UNIQUE (driver, provider_event_id);
CREATE INDEX card_webhooks_provider_tx_ref_index ON public.card_webhooks USING btree (provider_tx_ref);
CREATE INDEX card_webhooks_status_created_at_index ON public.card_webhooks USING btree (status, created_at);


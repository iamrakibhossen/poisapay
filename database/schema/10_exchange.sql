-- ============================================================
-- Module: exchange
-- GENERATED from Laravel migrations (source of truth) via pg_dump.
-- Do not edit by hand; regenerate with database/schema/regenerate.sh
-- ============================================================

CREATE TABLE public.fx_quotes (
    id uuid NOT NULL,
    user_id uuid,
    from_asset_id bigint NOT NULL,
    to_asset_id bigint NOT NULL,
    from_amount numeric(78,0) NOT NULL,
    to_amount numeric(78,0) NOT NULL,
    rate numeric(38,18) NOT NULL,
    market_rate numeric(38,18),
    spread_bps integer NOT NULL,
    fee_bps integer DEFAULT 0 NOT NULL,
    source character varying(32) NOT NULL,
    context character varying(16) DEFAULT 'swap'::character varying NOT NULL,
    expires_at timestamp(0) without time zone NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);

ALTER TABLE ONLY public.fx_quotes
    ADD CONSTRAINT fx_quotes_pkey PRIMARY KEY (id);
CREATE INDEX fx_quotes_user_id_created_at_index ON public.fx_quotes USING btree (user_id, created_at);
CREATE INDEX pp_idx_fx_quotes_from_asset ON public.fx_quotes USING btree (from_asset_id);
CREATE INDEX pp_idx_fx_quotes_to_asset ON public.fx_quotes USING btree (to_asset_id);

CREATE TABLE public.conversions (
    id uuid NOT NULL,
    user_id uuid NOT NULL,
    quote_id uuid NOT NULL,
    context character varying(16) NOT NULL,
    entry_id uuid,
    status character varying(16) DEFAULT 'completed'::character varying NOT NULL,
    completed_at timestamp(0) without time zone,
    spread_amount numeric(78,0) DEFAULT '0'::numeric NOT NULL,
    fee_amount numeric(78,0) DEFAULT '0'::numeric NOT NULL,
    gross_amount numeric(78,0) DEFAULT '0'::numeric NOT NULL,
    notional_usd numeric(38,2),
    idempotency_key character varying(160) NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);

ALTER TABLE ONLY public.conversions
    ADD CONSTRAINT conversions_idempotency_key_unique UNIQUE (idempotency_key);
ALTER TABLE ONLY public.conversions
    ADD CONSTRAINT conversions_pkey PRIMARY KEY (id);
CREATE INDEX conversions_entry_id_index ON public.conversions USING btree (entry_id);
CREATE INDEX conversions_user_id_created_at_index ON public.conversions USING btree (user_id, created_at);
CREATE INDEX pp_idx_conversions_quote ON public.conversions USING btree (quote_id);

CREATE TABLE public.trading_pairs (
    id uuid NOT NULL,
    from_asset_id bigint NOT NULL,
    to_asset_id bigint NOT NULL,
    spread_bps integer,
    min_amount numeric(78,0) DEFAULT '0'::numeric NOT NULL,
    max_amount numeric(78,0),
    is_active boolean DEFAULT true NOT NULL,
    sort smallint DEFAULT '0'::smallint NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);

ALTER TABLE ONLY public.trading_pairs
    ADD CONSTRAINT trading_pairs_pkey PRIMARY KEY (id);
ALTER TABLE ONLY public.trading_pairs
    ADD CONSTRAINT uq_trading_pair UNIQUE (from_asset_id, to_asset_id);
CREATE INDEX trading_pairs_is_active_index ON public.trading_pairs USING btree (is_active);
CREATE INDEX trading_pairs_to_asset_id_index ON public.trading_pairs USING btree (to_asset_id);

CREATE TABLE public.ramp_orders (
    id uuid NOT NULL,
    user_id uuid NOT NULL,
    direction character varying(4) NOT NULL,
    rail character varying(24) NOT NULL,
    fiat_asset_id bigint NOT NULL,
    fiat_amount numeric(38,0) NOT NULL,
    provider_ref character varying(128),
    idempotency_key character varying(160),
    beneficiary character varying(160),
    status character varying(24) DEFAULT 'pending'::character varying NOT NULL,
    entry_id uuid,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);

ALTER TABLE ONLY public.ramp_orders
    ADD CONSTRAINT ramp_orders_pkey PRIMARY KEY (id);
ALTER TABLE ONLY public.ramp_orders
    ADD CONSTRAINT uq_ramp_idempotency UNIQUE (idempotency_key);
ALTER TABLE ONLY public.ramp_orders
    ADD CONSTRAINT uq_ramp_provider UNIQUE (rail, provider_ref);
CREATE INDEX ramp_orders_entry_id_index ON public.ramp_orders USING btree (entry_id);
CREATE INDEX ramp_orders_fiat_asset_id_index ON public.ramp_orders USING btree (fiat_asset_id);
CREATE INDEX ramp_orders_user_id_status_index ON public.ramp_orders USING btree (user_id, status);


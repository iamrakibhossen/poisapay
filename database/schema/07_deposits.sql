-- ============================================================
-- Module: deposits
-- GENERATED from Laravel migrations (source of truth) via pg_dump.
-- Do not edit by hand; regenerate with database/schema/regenerate.sh
-- ============================================================

CREATE TABLE public.deposits (
    id uuid NOT NULL,
    user_id uuid NOT NULL,
    deposit_address_id uuid,
    asset_id bigint NOT NULL,
    onchain_tx_id uuid,
    amount numeric(78,0) NOT NULL,
    fee numeric(78,0) DEFAULT '0'::numeric NOT NULL,
    confirmations integer DEFAULT 0 NOT NULL,
    required_confirmations integer DEFAULT 0 NOT NULL,
    status character varying(16) DEFAULT 'detected'::character varying NOT NULL,
    credit_entry_id uuid,
    credited_at timestamp(0) without time zone,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    source character varying(12) DEFAULT 'onchain'::character varying NOT NULL,
    deposit_method_id uuid,
    reference character varying(120)
);

ALTER TABLE ONLY public.deposits
    ADD CONSTRAINT deposits_pkey PRIMARY KEY (id);
ALTER TABLE ONLY public.deposits
    ADD CONSTRAINT uq_deposit_onchain_tx UNIQUE (onchain_tx_id);
CREATE INDEX deposits_credit_entry_id_index ON public.deposits USING btree (credit_entry_id);
CREATE INDEX deposits_deposit_address_id_index ON public.deposits USING btree (deposit_address_id);
CREATE INDEX deposits_deposit_method_id_index ON public.deposits USING btree (deposit_method_id);
CREATE INDEX deposits_user_id_status_index ON public.deposits USING btree (user_id, status);
CREATE INDEX pp_idx_deposits_asset ON public.deposits USING btree (asset_id);
CREATE INDEX pp_idx_deposits_user_created ON public.deposits USING btree (user_id, created_at DESC);

CREATE TABLE public.deposit_methods (
    id uuid NOT NULL,
    asset_id bigint NOT NULL,
    name character varying(80) NOT NULL,
    type character varying(16) NOT NULL,
    details jsonb,
    instructions text,
    min_amount numeric(78,0) DEFAULT '0'::numeric NOT NULL,
    max_amount numeric(78,0),
    fixed_fee numeric(78,0) DEFAULT '0'::numeric NOT NULL,
    percent_fee_bps integer DEFAULT 0 NOT NULL,
    logo character varying(255),
    is_active boolean DEFAULT true NOT NULL,
    sort integer DEFAULT 0 NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);

ALTER TABLE ONLY public.deposit_methods
    ADD CONSTRAINT deposit_methods_pkey PRIMARY KEY (id);
CREATE INDEX deposit_methods_asset_id_is_active_index ON public.deposit_methods USING btree (asset_id, is_active);


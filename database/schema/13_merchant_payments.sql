-- ============================================================
-- Module: merchant_payments
-- GENERATED from Laravel migrations (source of truth) via pg_dump.
-- Do not edit by hand; regenerate with database/schema/regenerate.sh
-- ============================================================

CREATE TABLE public.merchants (
    id uuid NOT NULL,
    user_id uuid NOT NULL,
    business_name character varying(120) NOT NULL,
    slug character varying(140) NOT NULL,
    category character varying(48),
    website character varying(160),
    support_email character varying(160),
    statement_descriptor character varying(22),
    settlement_asset_id bigint,
    fee_bps integer,
    status character varying(16) DEFAULT 'pending'::character varying NOT NULL,
    auto_settle boolean DEFAULT false NOT NULL,
    suspension_reason text,
    approved_at timestamp(0) without time zone,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);

ALTER TABLE ONLY public.merchants
    ADD CONSTRAINT merchants_pkey PRIMARY KEY (id);
ALTER TABLE ONLY public.merchants
    ADD CONSTRAINT merchants_slug_unique UNIQUE (slug);
ALTER TABLE ONLY public.merchants
    ADD CONSTRAINT merchants_user_id_unique UNIQUE (user_id);
CREATE INDEX merchants_settlement_asset_id_index ON public.merchants USING btree (settlement_asset_id);
CREATE INDEX merchants_status_created_at_index ON public.merchants USING btree (status, created_at);

CREATE TABLE public.merchant_invoices (
    id uuid NOT NULL,
    merchant_id uuid NOT NULL,
    asset_id bigint NOT NULL,
    amount numeric(78,0) NOT NULL,
    fee_amount numeric(78,0) DEFAULT '0'::numeric NOT NULL,
    reference character varying(64) NOT NULL,
    memo character varying(160),
    status character varying(16) DEFAULT 'pending'::character varying NOT NULL,
    payer_id uuid,
    entry_id uuid,
    expires_at timestamp(0) without time zone,
    paid_at timestamp(0) without time zone,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);

ALTER TABLE ONLY public.merchant_invoices
    ADD CONSTRAINT merchant_invoices_pkey PRIMARY KEY (id);
ALTER TABLE ONLY public.merchant_invoices
    ADD CONSTRAINT uq_invoice_reference UNIQUE (merchant_id, reference);
CREATE INDEX merchant_invoices_asset_id_index ON public.merchant_invoices USING btree (asset_id);
CREATE INDEX merchant_invoices_entry_id_index ON public.merchant_invoices USING btree (entry_id);
CREATE INDEX merchant_invoices_payer_id_index ON public.merchant_invoices USING btree (payer_id);
CREATE INDEX merchant_invoices_status_created_at_index ON public.merchant_invoices USING btree (status, created_at);


-- ============================================================
-- Module: treasury
-- GENERATED from Laravel migrations (source of truth) via pg_dump.
-- Do not edit by hand; regenerate with database/schema/regenerate.sh
-- ============================================================

CREATE TABLE public.treasury_moves (
    id uuid NOT NULL,
    chain_id bigint NOT NULL,
    asset_id bigint NOT NULL,
    direction character varying(16) DEFAULT 'hot_to_cold'::character varying NOT NULL,
    amount numeric(78,0) DEFAULT '0'::numeric NOT NULL,
    status character varying(16) DEFAULT 'broadcast'::character varying NOT NULL,
    nonce_context character varying(255) NOT NULL,
    onchain_tx_id uuid,
    settle_entry_id uuid,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);

ALTER TABLE ONLY public.treasury_moves
    ADD CONSTRAINT treasury_moves_nonce_context_unique UNIQUE (nonce_context);
ALTER TABLE ONLY public.treasury_moves
    ADD CONSTRAINT treasury_moves_pkey PRIMARY KEY (id);
CREATE INDEX pp_idx_treasury_moves_asset ON public.treasury_moves USING btree (asset_id);
CREATE INDEX pp_idx_treasury_moves_chain ON public.treasury_moves USING btree (chain_id);
CREATE INDEX treasury_moves_onchain_tx_id_index ON public.treasury_moves USING btree (onchain_tx_id);
CREATE INDEX treasury_moves_settle_entry_id_index ON public.treasury_moves USING btree (settle_entry_id);
CREATE INDEX treasury_moves_status_index ON public.treasury_moves USING btree (status);

CREATE TABLE public.cold_refill_requests (
    id uuid NOT NULL,
    chain_id bigint NOT NULL,
    asset_id bigint NOT NULL,
    amount numeric(78,0) DEFAULT '0'::numeric NOT NULL,
    status character varying(16) DEFAULT 'requested'::character varying NOT NULL,
    cold_address character varying(64),
    hot_address character varying(64),
    tx_hash character varying(80),
    approved_by uuid,
    approved_at timestamp(0) without time zone,
    settle_entry_id uuid,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);

ALTER TABLE ONLY public.cold_refill_requests
    ADD CONSTRAINT cold_refill_requests_pkey PRIMARY KEY (id);
CREATE INDEX cold_refill_requests_approved_by_index ON public.cold_refill_requests USING btree (approved_by);
CREATE INDEX cold_refill_requests_asset_id_status_index ON public.cold_refill_requests USING btree (asset_id, status);
CREATE INDEX cold_refill_requests_chain_id_index ON public.cold_refill_requests USING btree (chain_id);
CREATE INDEX cold_refill_requests_settle_entry_id_index ON public.cold_refill_requests USING btree (settle_entry_id);

CREATE TABLE public.reconciliation_runs (
    id uuid NOT NULL,
    asset_id bigint NOT NULL,
    onchain_controlled numeric(78,0) DEFAULT '0'::numeric NOT NULL,
    ledger_treasury numeric(38,0) DEFAULT '0'::numeric NOT NULL,
    ledger_liability numeric(38,0) DEFAULT '0'::numeric NOT NULL,
    drift numeric(78,0) DEFAULT '0'::numeric NOT NULL,
    is_solvent boolean DEFAULT true NOT NULL,
    status character varying(16) DEFAULT 'ok'::character varying NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);

ALTER TABLE ONLY public.reconciliation_runs
    ADD CONSTRAINT reconciliation_runs_pkey PRIMARY KEY (id);
CREATE INDEX reconciliation_runs_asset_id_created_at_index ON public.reconciliation_runs USING btree (asset_id, created_at);

CREATE TABLE public.profit_payouts (
    id uuid NOT NULL,
    asset_id bigint NOT NULL,
    amount numeric(78,0) NOT NULL,
    destination character varying(160),
    network character varying(24),
    destination_address character varying(128),
    status character varying(16) DEFAULT 'completed'::character varying NOT NULL,
    tx_hash character varying(128),
    gas_fee numeric(78,0) DEFAULT '0'::numeric NOT NULL,
    completed_at timestamp(0) without time zone,
    note text,
    entry_id uuid,
    created_by uuid,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);

ALTER TABLE ONLY public.profit_payouts
    ADD CONSTRAINT profit_payouts_pkey PRIMARY KEY (id);
CREATE INDEX profit_payouts_asset_id_created_at_index ON public.profit_payouts USING btree (asset_id, created_at);
CREATE INDEX profit_payouts_created_by_index ON public.profit_payouts USING btree (created_by);
CREATE INDEX profit_payouts_entry_id_index ON public.profit_payouts USING btree (entry_id);

CREATE TABLE public.revenue_withdrawals (
    id uuid NOT NULL,
    asset_id bigint NOT NULL,
    amount numeric(78,0) NOT NULL,
    gas_fee numeric(78,0) DEFAULT '0'::numeric NOT NULL,
    network character varying(24),
    destination_address character varying(128) NOT NULL,
    note text,
    status character varying(16) DEFAULT 'pending'::character varying NOT NULL,
    tx_hash character varying(128),
    failure_reason character varying(255),
    entry_id uuid,
    reversal_entry_id uuid,
    idempotency_key character varying(160) NOT NULL,
    created_by uuid,
    approved_by uuid,
    approved_at timestamp(0) without time zone,
    completed_at timestamp(0) without time zone,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    deleted_at timestamp(0) without time zone
);

ALTER TABLE ONLY public.revenue_withdrawals
    ADD CONSTRAINT revenue_withdrawals_idempotency_key_unique UNIQUE (idempotency_key);
ALTER TABLE ONLY public.revenue_withdrawals
    ADD CONSTRAINT revenue_withdrawals_pkey PRIMARY KEY (id);
CREATE INDEX revenue_withdrawals_approved_by_index ON public.revenue_withdrawals USING btree (approved_by);
CREATE INDEX revenue_withdrawals_asset_id_status_index ON public.revenue_withdrawals USING btree (asset_id, status);
CREATE INDEX revenue_withdrawals_created_by_index ON public.revenue_withdrawals USING btree (created_by);
CREATE INDEX revenue_withdrawals_entry_id_index ON public.revenue_withdrawals USING btree (entry_id);
CREATE INDEX revenue_withdrawals_reversal_entry_id_index ON public.revenue_withdrawals USING btree (reversal_entry_id);
CREATE INDEX revenue_withdrawals_status_created_at_index ON public.revenue_withdrawals USING btree (status, created_at);


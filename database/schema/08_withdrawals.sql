-- ============================================================
-- Module: withdrawals
-- GENERATED from Laravel migrations (source of truth) via pg_dump.
-- Do not edit by hand; regenerate with database/schema/regenerate.sh
-- ============================================================

CREATE TABLE public.withdrawals (
    id uuid NOT NULL,
    user_id uuid NOT NULL,
    asset_id bigint NOT NULL,
    to_address character varying(64) NOT NULL,
    payout_method character varying(16),
    payout_details text,
    amount numeric(78,0) NOT NULL,
    fee numeric(78,0) DEFAULT '0'::numeric NOT NULL,
    status character varying(16) DEFAULT 'pending'::character varying NOT NULL,
    idempotency_key character varying(160) NOT NULL,
    risk_score smallint DEFAULT '0'::smallint NOT NULL,
    risk_level character varying(12) DEFAULT 'low'::character varying NOT NULL,
    requires_review boolean DEFAULT false NOT NULL,
    lock_entry_id uuid,
    settle_entry_id uuid,
    onchain_tx_id uuid,
    broadcast_nonce bigint,
    broadcast_block bigint,
    broadcast_attempts integer DEFAULT 0 NOT NULL,
    approved_by uuid,
    approved_at timestamp(0) without time zone,
    completed_at timestamp(0) without time zone,
    failure_reason text,
    reserve_released_at timestamp(0) without time zone,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);

ALTER TABLE ONLY public.withdrawals
    ADD CONSTRAINT withdrawals_idempotency_key_unique UNIQUE (idempotency_key);
ALTER TABLE ONLY public.withdrawals
    ADD CONSTRAINT withdrawals_pkey PRIMARY KEY (id);
CREATE INDEX pp_idx_withdrawals_asset ON public.withdrawals USING btree (asset_id);
CREATE INDEX pp_idx_withdrawals_user_created ON public.withdrawals USING btree (user_id, created_at DESC);
CREATE INDEX withdrawals_approved_by_index ON public.withdrawals USING btree (approved_by);
CREATE INDEX withdrawals_lock_entry_id_index ON public.withdrawals USING btree (lock_entry_id);
CREATE INDEX withdrawals_onchain_tx_id_index ON public.withdrawals USING btree (onchain_tx_id);
CREATE INDEX withdrawals_settle_entry_id_index ON public.withdrawals USING btree (settle_entry_id);
CREATE INDEX withdrawals_status_created_at_index ON public.withdrawals USING btree (status, created_at);
CREATE INDEX withdrawals_user_id_status_index ON public.withdrawals USING btree (user_id, status);

CREATE TABLE public.withdrawal_methods (
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

ALTER TABLE ONLY public.withdrawal_methods
    ADD CONSTRAINT withdrawal_methods_pkey PRIMARY KEY (id);
CREATE INDEX withdrawal_methods_asset_id_is_active_index ON public.withdrawal_methods USING btree (asset_id, is_active);

CREATE TABLE public.payout_accounts (
    id uuid NOT NULL,
    user_id uuid NOT NULL,
    asset_id bigint NOT NULL,
    withdrawal_method_id uuid,
    label character varying(60),
    account_name character varying(120) NOT NULL,
    account_number character varying(64) NOT NULL,
    bank_name character varying(120),
    is_favorite boolean DEFAULT false NOT NULL,
    last_used_at timestamp(0) without time zone,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);

ALTER TABLE ONLY public.payout_accounts
    ADD CONSTRAINT payout_accounts_pkey PRIMARY KEY (id);
ALTER TABLE ONLY public.payout_accounts
    ADD CONSTRAINT uq_payout_account UNIQUE (user_id, withdrawal_method_id, account_number);
CREATE INDEX payout_accounts_asset_id_index ON public.payout_accounts USING btree (asset_id);
CREATE INDEX payout_accounts_user_id_asset_id_index ON public.payout_accounts USING btree (user_id, asset_id);
CREATE INDEX payout_accounts_withdrawal_method_id_index ON public.payout_accounts USING btree (withdrawal_method_id);

CREATE TABLE public.sweeps (
    id uuid NOT NULL,
    deposit_address_id uuid NOT NULL,
    asset_id bigint NOT NULL,
    amount numeric(78,0) NOT NULL,
    gas_cost numeric(78,0) DEFAULT '0'::numeric NOT NULL,
    status character varying(16) DEFAULT 'pending'::character varying NOT NULL,
    nonce_context character varying(80),
    settle_entry_id uuid,
    onchain_tx_id uuid,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);

ALTER TABLE ONLY public.sweeps
    ADD CONSTRAINT sweeps_pkey PRIMARY KEY (id);
ALTER TABLE ONLY public.sweeps
    ADD CONSTRAINT uq_sweep_nonce_context UNIQUE (nonce_context);
CREATE INDEX pp_idx_sweeps_asset ON public.sweeps USING btree (asset_id);
CREATE INDEX sweeps_deposit_address_id_index ON public.sweeps USING btree (deposit_address_id);
CREATE INDEX sweeps_onchain_tx_id_index ON public.sweeps USING btree (onchain_tx_id);
CREATE INDEX sweeps_settle_entry_id_index ON public.sweeps USING btree (settle_entry_id);
CREATE INDEX sweeps_status_index ON public.sweeps USING btree (status);

CREATE TABLE public.broadcast_attempts (
    id uuid NOT NULL,
    subject_type character varying(32) NOT NULL,
    subject_id uuid NOT NULL,
    tx_hash character varying(80),
    attempt integer DEFAULT 1 NOT NULL,
    outcome character varying(16) DEFAULT 'submitted'::character varying NOT NULL,
    provider_response jsonb,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);

ALTER TABLE ONLY public.broadcast_attempts
    ADD CONSTRAINT broadcast_attempts_pkey PRIMARY KEY (id);
CREATE INDEX broadcast_attempts_subject_type_subject_id_index ON public.broadcast_attempts USING btree (subject_type, subject_id);


-- ============================================================
-- Module: ledger
-- GENERATED from Laravel migrations (source of truth) via pg_dump.
-- Do not edit by hand; regenerate with database/schema/regenerate.sh
-- ============================================================

CREATE TABLE public.journal_entries (
    id uuid NOT NULL,
    type character varying(40) NOT NULL,
    status character varying(16) DEFAULT 'completed'::character varying NOT NULL,
    idempotency_key character varying(160) NOT NULL,
    reverses_entry_id uuid,
    memo character varying(255),
    metadata jsonb,
    posted_at timestamp(0) without time zone,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);

ALTER TABLE ONLY public.journal_entries
    ADD CONSTRAINT journal_entries_idempotency_key_unique UNIQUE (idempotency_key);
ALTER TABLE ONLY public.journal_entries
    ADD CONSTRAINT journal_entries_pkey PRIMARY KEY (id);
CREATE INDEX journal_entries_reverses_entry_id_index ON public.journal_entries USING btree (reverses_entry_id);
CREATE INDEX journal_entries_type_created_at_index ON public.journal_entries USING btree (type, created_at);

CREATE TABLE public.ledger_lines (
    id uuid NOT NULL,
    entry_id uuid NOT NULL,
    account_id uuid NOT NULL,
    asset_id bigint NOT NULL,
    side character varying(6) NOT NULL,
    amount numeric(38,0) NOT NULL,
    created_at timestamp(0) without time zone,
    CONSTRAINT ck_line_amount_positive CHECK ((amount > (0)::numeric))
);

ALTER TABLE ONLY public.ledger_lines
    ADD CONSTRAINT ledger_lines_pkey PRIMARY KEY (id);
CREATE INDEX ledger_lines_account_id_created_at_index ON public.ledger_lines USING btree (account_id, created_at);
CREATE INDEX ledger_lines_entry_id_index ON public.ledger_lines USING btree (entry_id);
CREATE INDEX pp_idx_ledger_lines_asset ON public.ledger_lines USING btree (asset_id);

CREATE TABLE public.ledger_accounts (
    id uuid NOT NULL,
    type character varying(40) NOT NULL,
    user_id uuid,
    asset_id bigint NOT NULL,
    normal_side character varying(6) NOT NULL,
    label character varying(64),
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);

ALTER TABLE ONLY public.ledger_accounts
    ADD CONSTRAINT ledger_accounts_pkey PRIMARY KEY (id);
ALTER TABLE ONLY public.ledger_accounts
    ADD CONSTRAINT uq_account_identity UNIQUE (type, user_id, asset_id);
CREATE INDEX ledger_accounts_asset_id_index ON public.ledger_accounts USING btree (asset_id);
CREATE INDEX ledger_accounts_user_id_asset_id_index ON public.ledger_accounts USING btree (user_id, asset_id);

CREATE TABLE public.account_balances (
    account_id uuid NOT NULL,
    balance numeric(38,0) DEFAULT '0'::numeric NOT NULL,
    version bigint DEFAULT '0'::bigint NOT NULL,
    updated_at timestamp(0) without time zone
);

ALTER TABLE ONLY public.account_balances
    ADD CONSTRAINT account_balances_pkey PRIMARY KEY (account_id);


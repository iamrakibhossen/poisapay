-- ============================================================
-- Module: registry_wallet
-- GENERATED from Laravel migrations (source of truth) via pg_dump.
-- Do not edit by hand; regenerate with database/schema/regenerate.sh
-- ============================================================

CREATE TABLE public.chains (
    id smallint NOT NULL,
    key character varying(16) NOT NULL,
    name character varying(48) NOT NULL,
    native_symbol character varying(12) NOT NULL,
    min_confirmations smallint DEFAULT '12'::smallint NOT NULL,
    is_evm boolean DEFAULT true NOT NULL,
    is_active boolean DEFAULT true NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);

ALTER TABLE ONLY public.chains
    ADD CONSTRAINT chains_key_unique UNIQUE (key);
ALTER TABLE ONLY public.chains
    ADD CONSTRAINT chains_pkey PRIMARY KEY (id);

CREATE TABLE public.assets (
    id smallint NOT NULL,
    symbol character varying(16) NOT NULL,
    name character varying(48) NOT NULL,
    kind character varying(8) DEFAULT 'crypto'::character varying NOT NULL,
    currency_code character(3),
    chain_id bigint,
    contract_address character varying(64),
    decimals smallint NOT NULL,
    min_confirmations smallint,
    withdrawal_min character varying(78) DEFAULT '0'::character varying NOT NULL,
    withdrawal_fee character varying(78) DEFAULT '0'::character varying NOT NULL,
    is_stablecoin boolean DEFAULT false NOT NULL,
    is_active boolean DEFAULT true NOT NULL,
    sort smallint DEFAULT '0'::smallint NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    deposit_enabled boolean DEFAULT true NOT NULL,
    currency_id bigint
);

ALTER TABLE ONLY public.assets
    ADD CONSTRAINT assets_pkey PRIMARY KEY (id);
ALTER TABLE ONLY public.assets
    ADD CONSTRAINT uq_asset_chain_contract UNIQUE (chain_id, contract_address);
CREATE INDEX assets_currency_id_index ON public.assets USING btree (currency_id);
CREATE INDEX assets_kind_is_active_index ON public.assets USING btree (kind, is_active);
CREATE UNIQUE INDEX uq_native_per_chain ON public.assets USING btree (chain_id) WHERE ((contract_address IS NULL) AND (chain_id IS NOT NULL));

CREATE TABLE public.currencies (
    id smallint NOT NULL,
    symbol character varying(16) NOT NULL,
    name character varying(48) NOT NULL,
    kind character varying(8) DEFAULT 'crypto'::character varying NOT NULL,
    is_stablecoin boolean DEFAULT false NOT NULL,
    display_decimals smallint,
    icon character varying(32),
    sort smallint DEFAULT '0'::smallint NOT NULL,
    is_active boolean DEFAULT true NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);

ALTER TABLE ONLY public.currencies
    ADD CONSTRAINT currencies_pkey PRIMARY KEY (id);
ALTER TABLE ONLY public.currencies
    ADD CONSTRAINT currencies_symbol_unique UNIQUE (symbol);

CREATE TABLE public.custody_xpubs (
    id uuid NOT NULL,
    chain_id bigint NOT NULL,
    label character varying(48) NOT NULL,
    xpub text NOT NULL,
    derivation_path character varying(48) NOT NULL,
    next_index bigint DEFAULT '0'::bigint NOT NULL,
    purpose character varying(16) DEFAULT 'deposit'::character varying NOT NULL,
    is_active boolean DEFAULT true NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    CONSTRAINT ck_never_xpriv CHECK (((xpub !~~ 'xprv%'::text) AND (xpub !~~ 'tprv%'::text) AND (xpub !~~ '%priv%'::text)))
);

ALTER TABLE ONLY public.custody_xpubs
    ADD CONSTRAINT custody_xpubs_pkey PRIMARY KEY (id);
CREATE INDEX custody_xpubs_chain_id_index ON public.custody_xpubs USING btree (chain_id);

CREATE TABLE public.deposit_addresses (
    id uuid NOT NULL,
    user_id uuid NOT NULL,
    chain_id bigint NOT NULL,
    xpub_id uuid NOT NULL,
    derivation_index bigint NOT NULL,
    address character varying(64) NOT NULL,
    is_watched boolean DEFAULT true NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);

ALTER TABLE ONLY public.deposit_addresses
    ADD CONSTRAINT deposit_addresses_pkey PRIMARY KEY (id);
ALTER TABLE ONLY public.deposit_addresses
    ADD CONSTRAINT uq_addr_chain_address UNIQUE (chain_id, address);
ALTER TABLE ONLY public.deposit_addresses
    ADD CONSTRAINT uq_addr_xpub_index UNIQUE (xpub_id, derivation_index);
CREATE INDEX deposit_addresses_address_index ON public.deposit_addresses USING btree (address);
CREATE INDEX deposit_addresses_user_id_index ON public.deposit_addresses USING btree (user_id);

CREATE TABLE public.onchain_txs (
    id uuid NOT NULL,
    chain_id bigint NOT NULL,
    tx_hash character varying(80) NOT NULL,
    log_index integer DEFAULT 0 NOT NULL,
    from_address character varying(64),
    to_address character varying(64),
    asset_id bigint,
    amount numeric(78,0) DEFAULT '0'::numeric NOT NULL,
    block_number bigint,
    confirmations integer DEFAULT 0 NOT NULL,
    status character varying(16) DEFAULT 'detected'::character varying NOT NULL,
    direction character varying(8) DEFAULT 'in'::character varying NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);

ALTER TABLE ONLY public.onchain_txs
    ADD CONSTRAINT onchain_txs_pkey PRIMARY KEY (id);
ALTER TABLE ONLY public.onchain_txs
    ADD CONSTRAINT uq_onchain_tx UNIQUE (chain_id, tx_hash, log_index);
CREATE INDEX onchain_txs_asset_id_index ON public.onchain_txs USING btree (asset_id);
CREATE INDEX onchain_txs_status_chain_id_index ON public.onchain_txs USING btree (status, chain_id);

CREATE TABLE public.evm_nonces (
    id uuid NOT NULL,
    chain character varying(16) NOT NULL,
    address character varying(64) NOT NULL,
    next_nonce bigint DEFAULT '0'::bigint NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);

ALTER TABLE ONLY public.evm_nonces
    ADD CONSTRAINT evm_nonces_pkey PRIMARY KEY (id);
ALTER TABLE ONLY public.evm_nonces
    ADD CONSTRAINT uq_evm_nonce UNIQUE (chain, address);

CREATE TABLE public.gas_wallets (
    id uuid NOT NULL,
    chain_id bigint NOT NULL,
    address character varying(64),
    balance numeric(78,0) DEFAULT '0'::numeric NOT NULL,
    min_threshold numeric(78,0) DEFAULT '0'::numeric NOT NULL,
    is_active boolean DEFAULT true NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);

ALTER TABLE ONLY public.gas_wallets
    ADD CONSTRAINT gas_wallets_chain_id_unique UNIQUE (chain_id);
ALTER TABLE ONLY public.gas_wallets
    ADD CONSTRAINT gas_wallets_pkey PRIMARY KEY (id);

CREATE TABLE public.gas_sponsorships (
    id uuid NOT NULL,
    chain_id bigint NOT NULL,
    target_address character varying(64) NOT NULL,
    purpose character varying(24) DEFAULT 'sweep'::character varying NOT NULL,
    status character varying(16) DEFAULT 'pending'::character varying NOT NULL,
    amount_sun numeric(30,0) DEFAULT '0'::numeric NOT NULL,
    tx_hash character varying(80),
    attempts integer DEFAULT 0 NOT NULL,
    last_error character varying(255),
    funded_at timestamp(0) without time zone,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);

ALTER TABLE ONLY public.gas_sponsorships
    ADD CONSTRAINT gas_sponsorships_pkey PRIMARY KEY (id);
ALTER TABLE ONLY public.gas_sponsorships
    ADD CONSTRAINT uq_gas_sponsorship UNIQUE (chain_id, target_address, purpose);
CREATE INDEX gas_sponsorships_status_index ON public.gas_sponsorships USING btree (status);

CREATE TABLE public.rpc_endpoints (
    id uuid NOT NULL,
    chain_id bigint NOT NULL,
    name character varying(64) NOT NULL,
    url character varying(255) NOT NULL,
    priority smallint DEFAULT '1'::smallint NOT NULL,
    weight smallint DEFAULT '1'::smallint NOT NULL,
    is_active boolean DEFAULT true NOT NULL,
    status character varying(12) DEFAULT 'unknown'::character varying NOT NULL,
    last_block bigint,
    latency_ms integer,
    last_checked_at timestamp(0) without time zone,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);

ALTER TABLE ONLY public.rpc_endpoints
    ADD CONSTRAINT rpc_endpoints_pkey PRIMARY KEY (id);
CREATE INDEX rpc_endpoints_chain_id_is_active_priority_index ON public.rpc_endpoints USING btree (chain_id, is_active, priority);

CREATE TABLE public.address_book_entries (
    id uuid NOT NULL,
    user_id uuid NOT NULL,
    label character varying(64) NOT NULL,
    chain_id bigint,
    asset_id bigint,
    address character varying(128) NOT NULL,
    status character varying(16) DEFAULT 'active'::character varying NOT NULL,
    cooldown_until timestamp(0) without time zone,
    whitelisted_at timestamp(0) without time zone,
    blocked_at timestamp(0) without time zone,
    is_favorite boolean DEFAULT false NOT NULL,
    last_used_at timestamp(0) without time zone,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);

ALTER TABLE ONLY public.address_book_entries
    ADD CONSTRAINT address_book_entries_pkey PRIMARY KEY (id);
ALTER TABLE ONLY public.address_book_entries
    ADD CONSTRAINT uq_addressbook_user_address UNIQUE (user_id, address, chain_id);
CREATE INDEX address_book_entries_asset_id_index ON public.address_book_entries USING btree (asset_id);
CREATE INDEX address_book_entries_chain_id_index ON public.address_book_entries USING btree (chain_id);
CREATE INDEX address_book_entries_user_id_is_favorite_index ON public.address_book_entries USING btree (user_id, is_favorite);
CREATE INDEX ix_addressbook_user_status ON public.address_book_entries USING btree (user_id, status);

CREATE TABLE public.user_favorite_assets (
    user_id uuid NOT NULL,
    asset_id bigint NOT NULL,
    "position" smallint DEFAULT '0'::smallint NOT NULL
);

ALTER TABLE ONLY public.user_favorite_assets
    ADD CONSTRAINT user_favorite_assets_pkey PRIMARY KEY (user_id, asset_id);
CREATE INDEX user_favorite_assets_asset_id_index ON public.user_favorite_assets USING btree (asset_id);

CREATE TABLE public.user_spending_priority (
    user_id uuid NOT NULL,
    "position" smallint NOT NULL,
    asset_id bigint NOT NULL
);

ALTER TABLE ONLY public.user_spending_priority
    ADD CONSTRAINT uq_priority_pos UNIQUE (user_id, "position");
ALTER TABLE ONLY public.user_spending_priority
    ADD CONSTRAINT user_spending_priority_pkey PRIMARY KEY (user_id, asset_id);
CREATE INDEX user_spending_priority_asset_id_index ON public.user_spending_priority USING btree (asset_id);


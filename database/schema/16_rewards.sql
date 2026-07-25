-- ============================================================
-- Module: rewards
-- GENERATED from Laravel migrations (source of truth) via pg_dump.
-- Do not edit by hand; regenerate with database/schema/regenerate.sh
-- ============================================================

CREATE TABLE public.reward_grants (
    id uuid NOT NULL,
    user_id uuid NOT NULL,
    type character varying(32) NOT NULL,
    asset_id bigint NOT NULL,
    amount numeric(78,0) NOT NULL,
    idempotency_key character varying(160) NOT NULL,
    entry_id uuid,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);

ALTER TABLE ONLY public.reward_grants
    ADD CONSTRAINT reward_grants_idempotency_key_unique UNIQUE (idempotency_key);
ALTER TABLE ONLY public.reward_grants
    ADD CONSTRAINT reward_grants_pkey PRIMARY KEY (id);
CREATE INDEX reward_grants_asset_id_index ON public.reward_grants USING btree (asset_id);
CREATE INDEX reward_grants_entry_id_index ON public.reward_grants USING btree (entry_id);
CREATE INDEX reward_grants_user_id_index ON public.reward_grants USING btree (user_id);

CREATE TABLE public.reward_campaigns (
    id uuid NOT NULL,
    key character varying(48) NOT NULL,
    name character varying(120) NOT NULL,
    type character varying(24) NOT NULL,
    asset_id bigint,
    amount numeric(78,0),
    rate_bps integer,
    min_spend numeric(78,0),
    max_reward numeric(78,0),
    is_active boolean DEFAULT true NOT NULL,
    starts_at timestamp(0) without time zone,
    ends_at timestamp(0) without time zone,
    meta jsonb,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);

ALTER TABLE ONLY public.reward_campaigns
    ADD CONSTRAINT reward_campaigns_key_unique UNIQUE (key);
ALTER TABLE ONLY public.reward_campaigns
    ADD CONSTRAINT reward_campaigns_pkey PRIMARY KEY (id);
CREATE INDEX reward_campaigns_asset_id_index ON public.reward_campaigns USING btree (asset_id);
CREATE INDEX reward_campaigns_key_is_active_index ON public.reward_campaigns USING btree (key, is_active);

CREATE TABLE public.referrals (
    id uuid NOT NULL,
    referrer_id uuid NOT NULL,
    referee_id uuid NOT NULL,
    code character varying(24) NOT NULL,
    status character varying(16) DEFAULT 'pending'::character varying NOT NULL,
    reward_entry_id uuid,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    CONSTRAINT ck_no_self_referral CHECK ((referrer_id <> referee_id))
);

ALTER TABLE ONLY public.referrals
    ADD CONSTRAINT referrals_pkey PRIMARY KEY (id);
ALTER TABLE ONLY public.referrals
    ADD CONSTRAINT uq_referral_pair UNIQUE (referrer_id, referee_id);
CREATE INDEX referrals_referee_id_index ON public.referrals USING btree (referee_id);
CREATE INDEX referrals_reward_entry_id_index ON public.referrals USING btree (reward_entry_id);


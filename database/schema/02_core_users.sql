-- ============================================================
-- Module: core_users
-- GENERATED from Laravel migrations (source of truth) via pg_dump.
-- Do not edit by hand; regenerate with database/schema/regenerate.sh
-- ============================================================

CREATE TABLE public.users (
    id uuid NOT NULL,
    name character varying(255) NOT NULL,
    email character varying(255) NOT NULL,
    phone character varying(255),
    uid bigint,
    email_verified_at timestamp(0) without time zone,
    phone_verified_at timestamp(0) without time zone,
    password character varying(255) NOT NULL,
    two_factor_secret text,
    two_factor_recovery_codes text,
    two_factor_confirmed_at timestamp(0) without time zone,
    kyc_tier character varying(255) DEFAULT 'unverified'::character varying NOT NULL,
    kyc_status character varying(255) DEFAULT 'none'::character varying NOT NULL,
    referral_code character varying(255),
    referred_by uuid,
    base_currency character varying(3) DEFAULT 'BDT'::character varying NOT NULL,
    locale character varying(8) DEFAULT 'en'::character varying NOT NULL,
    timezone character varying(32) DEFAULT 'Asia/Dhaka'::character varying NOT NULL,
    is_frozen boolean DEFAULT false NOT NULL,
    anti_phishing_code character varying(32),
    image character varying(255),
    remember_token character varying(100),
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    telegram_chat_id character varying(32)
);

ALTER TABLE ONLY public.users
    ADD CONSTRAINT users_email_unique UNIQUE (email);
ALTER TABLE ONLY public.users
    ADD CONSTRAINT users_phone_unique UNIQUE (phone);
ALTER TABLE ONLY public.users
    ADD CONSTRAINT users_pkey PRIMARY KEY (id);
ALTER TABLE ONLY public.users
    ADD CONSTRAINT users_referral_code_unique UNIQUE (referral_code);
ALTER TABLE ONLY public.users
    ADD CONSTRAINT users_uid_unique UNIQUE (uid);
CREATE INDEX users_kyc_status_index ON public.users USING btree (kyc_status);
CREATE INDEX users_kyc_tier_index ON public.users USING btree (kyc_tier);
CREATE INDEX users_referred_by_index ON public.users USING btree (referred_by);

CREATE TABLE public.password_reset_tokens (
    email character varying(255) NOT NULL,
    token character varying(255) NOT NULL,
    created_at timestamp(0) without time zone
);

ALTER TABLE ONLY public.password_reset_tokens
    ADD CONSTRAINT password_reset_tokens_pkey PRIMARY KEY (email);

CREATE TABLE public.sessions (
    id character varying(255) NOT NULL,
    user_id uuid,
    ip_address character varying(45),
    user_agent text,
    payload text NOT NULL,
    last_activity integer NOT NULL
);

ALTER TABLE ONLY public.sessions
    ADD CONSTRAINT sessions_pkey PRIMARY KEY (id);
CREATE INDEX sessions_last_activity_index ON public.sessions USING btree (last_activity);
CREATE INDEX sessions_user_id_index ON public.sessions USING btree (user_id);

CREATE TABLE public.personal_access_tokens (
    id bigint NOT NULL,
    tokenable_type character varying(255) NOT NULL,
    tokenable_id uuid NOT NULL,
    name text NOT NULL,
    token character varying(64) NOT NULL,
    abilities text,
    last_used_at timestamp(0) without time zone,
    expires_at timestamp(0) without time zone,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);

ALTER TABLE ONLY public.personal_access_tokens
    ADD CONSTRAINT personal_access_tokens_pkey PRIMARY KEY (id);
ALTER TABLE ONLY public.personal_access_tokens
    ADD CONSTRAINT personal_access_tokens_token_unique UNIQUE (token);
CREATE INDEX personal_access_tokens_expires_at_index ON public.personal_access_tokens USING btree (expires_at);
CREATE INDEX personal_access_tokens_tokenable_type_tokenable_id_index ON public.personal_access_tokens USING btree (tokenable_type, tokenable_id);

CREATE TABLE public.notifications (
    id uuid NOT NULL,
    type character varying(255) NOT NULL,
    notifiable_type character varying(255) NOT NULL,
    notifiable_id uuid NOT NULL,
    data text NOT NULL,
    read_at timestamp(0) without time zone,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);

ALTER TABLE ONLY public.notifications
    ADD CONSTRAINT notifications_pkey PRIMARY KEY (id);
CREATE INDEX notifications_notifiable_type_notifiable_id_index ON public.notifications USING btree (notifiable_type, notifiable_id);

CREATE TABLE public.user_devices (
    id uuid NOT NULL,
    user_id uuid NOT NULL,
    name character varying(96),
    fingerprint character varying(128) NOT NULL,
    ip_address character varying(45),
    user_agent character varying(255),
    is_trusted boolean DEFAULT false NOT NULL,
    last_used_at timestamp(0) without time zone,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);

ALTER TABLE ONLY public.user_devices
    ADD CONSTRAINT user_devices_pkey PRIMARY KEY (id);
ALTER TABLE ONLY public.user_devices
    ADD CONSTRAINT user_devices_user_id_fingerprint_unique UNIQUE (user_id, fingerprint);

CREATE TABLE public.user_push_tokens (
    id uuid NOT NULL,
    user_id uuid NOT NULL,
    token character varying(255) NOT NULL,
    platform character varying(16) DEFAULT 'web'::character varying NOT NULL,
    last_used_at timestamp(0) without time zone,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);

ALTER TABLE ONLY public.user_push_tokens
    ADD CONSTRAINT uq_push_token UNIQUE (user_id, token);
ALTER TABLE ONLY public.user_push_tokens
    ADD CONSTRAINT user_push_tokens_pkey PRIMARY KEY (id);

CREATE TABLE public.otp_codes (
    id uuid NOT NULL,
    user_id uuid,
    identifier character varying(128) NOT NULL,
    channel character varying(8) NOT NULL,
    purpose character varying(24) NOT NULL,
    code_hash character varying(255) NOT NULL,
    attempts smallint DEFAULT '0'::smallint NOT NULL,
    expires_at timestamp(0) without time zone NOT NULL,
    consumed_at timestamp(0) without time zone,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);

ALTER TABLE ONLY public.otp_codes
    ADD CONSTRAINT otp_codes_pkey PRIMARY KEY (id);
CREATE INDEX otp_codes_identifier_purpose_index ON public.otp_codes USING btree (identifier, purpose);
CREATE INDEX otp_codes_user_id_index ON public.otp_codes USING btree (user_id);

CREATE TABLE public.login_histories (
    id uuid NOT NULL,
    user_id uuid NOT NULL,
    ip_address character varying(45),
    country character varying(2),
    city character varying(64),
    user_agent character varying(255),
    fingerprint character varying(64),
    new_device boolean DEFAULT false NOT NULL,
    risk_score smallint DEFAULT '0'::smallint NOT NULL,
    created_at timestamp(0) without time zone
);

ALTER TABLE ONLY public.login_histories
    ADD CONSTRAINT login_histories_pkey PRIMARY KEY (id);
CREATE INDEX login_histories_user_id_created_at_index ON public.login_histories USING btree (user_id, created_at);


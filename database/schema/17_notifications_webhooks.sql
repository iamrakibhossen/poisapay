-- ============================================================
-- Module: notifications_webhooks
-- GENERATED from Laravel migrations (source of truth) via pg_dump.
-- Do not edit by hand; regenerate with database/schema/regenerate.sh
-- ============================================================

CREATE TABLE public.notification_preferences (
    id uuid NOT NULL,
    user_id uuid NOT NULL,
    category character varying(32) NOT NULL,
    in_app boolean DEFAULT true NOT NULL,
    email boolean DEFAULT true NOT NULL,
    sms boolean DEFAULT false NOT NULL,
    push boolean DEFAULT true NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    whatsapp boolean DEFAULT false NOT NULL,
    telegram boolean DEFAULT false NOT NULL
);

ALTER TABLE ONLY public.notification_preferences
    ADD CONSTRAINT notification_preferences_pkey PRIMARY KEY (id);
ALTER TABLE ONLY public.notification_preferences
    ADD CONSTRAINT uq_pref_user_category UNIQUE (user_id, category);

CREATE TABLE public.notification_templates (
    id uuid NOT NULL,
    key character varying(64) NOT NULL,
    locale character varying(8) DEFAULT 'en'::character varying NOT NULL,
    name character varying(120) NOT NULL,
    category character varying(32) DEFAULT 'product'::character varying NOT NULL,
    channels jsonb NOT NULL,
    subject character varying(160),
    body text NOT NULL,
    is_active boolean DEFAULT true NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);

ALTER TABLE ONLY public.notification_templates
    ADD CONSTRAINT notification_templates_pkey PRIMARY KEY (id);
ALTER TABLE ONLY public.notification_templates
    ADD CONSTRAINT uq_template_key_locale UNIQUE (key, locale);

CREATE TABLE public.announcements (
    id uuid NOT NULL,
    title character varying(160) NOT NULL,
    body text NOT NULL,
    segment character varying(24) DEFAULT 'all'::character varying NOT NULL,
    category character varying(32) DEFAULT 'product'::character varying NOT NULL,
    channels jsonb,
    recipients integer DEFAULT 0 NOT NULL,
    sent_by uuid,
    sent_at timestamp(0) without time zone,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);

ALTER TABLE ONLY public.announcements
    ADD CONSTRAINT announcements_pkey PRIMARY KEY (id);
CREATE INDEX announcements_sent_by_index ON public.announcements USING btree (sent_by);

CREATE TABLE public.webhook_endpoints (
    id uuid NOT NULL,
    user_id uuid NOT NULL,
    url character varying(255) NOT NULL,
    secret character varying(255) NOT NULL,
    events jsonb NOT NULL,
    is_active boolean DEFAULT true NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);

ALTER TABLE ONLY public.webhook_endpoints
    ADD CONSTRAINT webhook_endpoints_pkey PRIMARY KEY (id);
CREATE INDEX webhook_endpoints_user_id_index ON public.webhook_endpoints USING btree (user_id);

CREATE TABLE public.webhook_deliveries (
    id uuid NOT NULL,
    endpoint_id uuid NOT NULL,
    event character varying(48) NOT NULL,
    payload jsonb NOT NULL,
    attempt smallint DEFAULT '1'::smallint NOT NULL,
    response_status smallint,
    status character varying(16) DEFAULT 'pending'::character varying NOT NULL,
    next_retry_at timestamp(0) without time zone,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);

ALTER TABLE ONLY public.webhook_deliveries
    ADD CONSTRAINT webhook_deliveries_pkey PRIMARY KEY (id);
CREATE INDEX webhook_deliveries_endpoint_id_index ON public.webhook_deliveries USING btree (endpoint_id);
CREATE INDEX webhook_deliveries_status_next_retry_at_index ON public.webhook_deliveries USING btree (status, next_retry_at);

CREATE TABLE public.webhook_logs (
    id uuid NOT NULL,
    provider character varying(40),
    method character varying(8) DEFAULT 'POST'::character varying NOT NULL,
    url character varying(512) NOT NULL,
    route character varying(100),
    payload jsonb,
    headers jsonb,
    ip character varying(45),
    hash character varying(32) NOT NULL,
    status smallint DEFAULT '0'::smallint NOT NULL,
    response text,
    retries integer DEFAULT 0 NOT NULL,
    resolved boolean DEFAULT false NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);

ALTER TABLE ONLY public.webhook_logs
    ADD CONSTRAINT webhook_logs_pkey PRIMARY KEY (id);
CREATE INDEX pp_idx_webhook_logs_status_resolved ON public.webhook_logs USING btree (status, resolved);
CREATE INDEX webhook_logs_hash_index ON public.webhook_logs USING btree (hash);
CREATE INDEX webhook_logs_provider_created_at_index ON public.webhook_logs USING btree (provider, created_at);
CREATE INDEX webhook_logs_resolved_created_at_index ON public.webhook_logs USING btree (resolved, created_at);
CREATE INDEX webhook_logs_status_created_at_index ON public.webhook_logs USING btree (status, created_at);


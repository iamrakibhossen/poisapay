-- ============================================================
-- Module: analytics_settings
-- GENERATED from Laravel migrations (source of truth) via pg_dump.
-- Do not edit by hand; regenerate with database/schema/regenerate.sh
-- ============================================================

CREATE TABLE public.analytics_daily_metrics (
    id bigint NOT NULL,
    day date NOT NULL,
    metric character varying(48) NOT NULL,
    value numeric(38,2) DEFAULT '0'::numeric NOT NULL,
    meta jsonb,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);

ALTER TABLE ONLY public.analytics_daily_metrics
    ADD CONSTRAINT analytics_daily_metrics_day_metric_unique UNIQUE (day, metric);
ALTER TABLE ONLY public.analytics_daily_metrics
    ADD CONSTRAINT analytics_daily_metrics_pkey PRIMARY KEY (id);
CREATE INDEX analytics_daily_metrics_metric_index ON public.analytics_daily_metrics USING btree (metric);

CREATE TABLE public.system_settings (
    key character varying(64) NOT NULL,
    value jsonb,
    "group" character varying(32) DEFAULT 'general'::character varying NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);

ALTER TABLE ONLY public.system_settings
    ADD CONSTRAINT system_settings_pkey PRIMARY KEY (key);


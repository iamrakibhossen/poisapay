-- ============================================================
-- Module: security_audit
-- GENERATED from Laravel migrations (source of truth) via pg_dump.
-- Do not edit by hand; regenerate with database/schema/regenerate.sh
-- ============================================================

CREATE TABLE public.security_events (
    id uuid NOT NULL,
    user_id uuid,
    type character varying(40) NOT NULL,
    severity character varying(16) DEFAULT 'info'::character varying NOT NULL,
    ip_address character varying(45),
    country character varying(2),
    city character varying(64),
    user_agent character varying(255),
    fingerprint character varying(64),
    risk_score smallint DEFAULT '0'::smallint NOT NULL,
    metadata jsonb,
    acknowledged_at timestamp(0) without time zone,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);

ALTER TABLE ONLY public.security_events
    ADD CONSTRAINT security_events_pkey PRIMARY KEY (id);
CREATE INDEX security_events_severity_index ON public.security_events USING btree (severity);
CREATE INDEX security_events_type_created_at_index ON public.security_events USING btree (type, created_at);
CREATE INDEX security_events_user_id_created_at_index ON public.security_events USING btree (user_id, created_at);

CREATE SEQUENCE public.audit_logs_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;

CREATE TABLE public.audit_logs (
    id uuid NOT NULL,
    sequence bigint,
    prev_hash character varying(64),
    hash character varying(64),
    user_id uuid,
    actor_type character varying(16) DEFAULT 'user'::character varying NOT NULL,
    actor_id uuid,
    actor_name character varying(255),
    action character varying(64) NOT NULL,
    description character varying(255),
    subject_type character varying(64),
    subject_id character varying(64),
    changes jsonb,
    ip_address character varying(45),
    user_agent character varying(255),
    created_at timestamp(0) without time zone
);

ALTER TABLE ONLY public.audit_logs
    ADD CONSTRAINT audit_logs_pkey PRIMARY KEY (id);
CREATE INDEX audit_logs_action_created_at_index ON public.audit_logs USING btree (action, created_at);
CREATE INDEX audit_logs_actor_type_actor_id_index ON public.audit_logs USING btree (actor_type, actor_id);
CREATE INDEX audit_logs_subject_type_subject_id_index ON public.audit_logs USING btree (subject_type, subject_id);
CREATE INDEX audit_logs_user_id_index ON public.audit_logs USING btree (user_id);
CREATE INDEX ix_audit_sequence ON public.audit_logs USING btree (sequence);


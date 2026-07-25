-- ============================================================
-- Module: support
-- GENERATED from Laravel migrations (source of truth) via pg_dump.
-- Do not edit by hand; regenerate with database/schema/regenerate.sh
-- ============================================================

CREATE TABLE public.support_tickets (
    id uuid NOT NULL,
    user_id uuid NOT NULL,
    subject character varying(160) NOT NULL,
    category character varying(32) DEFAULT 'general'::character varying NOT NULL,
    priority character varying(12) DEFAULT 'normal'::character varying NOT NULL,
    status character varying(16) DEFAULT 'open'::character varying NOT NULL,
    assigned_to uuid,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);

ALTER TABLE ONLY public.support_tickets
    ADD CONSTRAINT support_tickets_pkey PRIMARY KEY (id);
CREATE INDEX support_tickets_assigned_to_index ON public.support_tickets USING btree (assigned_to);
CREATE INDEX support_tickets_status_priority_index ON public.support_tickets USING btree (status, priority);
CREATE INDEX support_tickets_user_id_index ON public.support_tickets USING btree (user_id);

CREATE TABLE public.support_messages (
    id uuid NOT NULL,
    ticket_id uuid NOT NULL,
    author_id uuid,
    author_name character varying(120),
    body text NOT NULL,
    is_staff boolean DEFAULT false NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);

ALTER TABLE ONLY public.support_messages
    ADD CONSTRAINT support_messages_pkey PRIMARY KEY (id);
CREATE INDEX support_messages_author_id_index ON public.support_messages USING btree (author_id);
CREATE INDEX support_messages_ticket_id_index ON public.support_messages USING btree (ticket_id);


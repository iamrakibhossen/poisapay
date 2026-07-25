-- ============================================================
-- Module: cms
-- GENERATED from Laravel migrations (source of truth) via pg_dump.
-- Do not edit by hand; regenerate with database/schema/regenerate.sh
-- ============================================================

CREATE TABLE public.pages (
    id uuid NOT NULL,
    title character varying(255) NOT NULL,
    slug character varying(255) NOT NULL,
    content text,
    status character varying(255) DEFAULT 'published'::character varying NOT NULL,
    meta_description character varying(255),
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);

ALTER TABLE ONLY public.pages
    ADD CONSTRAINT pages_pkey PRIMARY KEY (id);
ALTER TABLE ONLY public.pages
    ADD CONSTRAINT pages_slug_unique UNIQUE (slug);

CREATE TABLE public.faqs (
    id uuid NOT NULL,
    question character varying(255) NOT NULL,
    answer text NOT NULL,
    "group" character varying(255),
    show_on_homepage boolean DEFAULT true NOT NULL,
    sort_order integer DEFAULT 0 NOT NULL,
    status character varying(255) DEFAULT 'published'::character varying NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);

ALTER TABLE ONLY public.faqs
    ADD CONSTRAINT faqs_pkey PRIMARY KEY (id);


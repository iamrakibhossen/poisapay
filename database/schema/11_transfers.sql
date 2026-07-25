-- ============================================================
-- Module: transfers
-- GENERATED from Laravel migrations (source of truth) via pg_dump.
-- Do not edit by hand; regenerate with database/schema/regenerate.sh
-- ============================================================

CREATE TABLE public.transfers (
    id uuid NOT NULL,
    sender_id uuid NOT NULL,
    recipient_id uuid,
    recipient_handle character varying(128),
    asset_id bigint NOT NULL,
    amount numeric(78,0) NOT NULL,
    kind character varying(16) DEFAULT 'internal'::character varying NOT NULL,
    status character varying(16) DEFAULT 'completed'::character varying NOT NULL,
    entry_id uuid,
    idempotency_key character varying(160) NOT NULL,
    memo character varying(140),
    expires_at timestamp(0) without time zone,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);

ALTER TABLE ONLY public.transfers
    ADD CONSTRAINT transfers_idempotency_key_unique UNIQUE (idempotency_key);
ALTER TABLE ONLY public.transfers
    ADD CONSTRAINT transfers_pkey PRIMARY KEY (id);
CREATE INDEX pp_idx_transfers_asset ON public.transfers USING btree (asset_id);
CREATE INDEX transfers_entry_id_index ON public.transfers USING btree (entry_id);
CREATE INDEX transfers_recipient_id_created_at_index ON public.transfers USING btree (recipient_id, created_at);
CREATE INDEX transfers_sender_id_created_at_index ON public.transfers USING btree (sender_id, created_at);


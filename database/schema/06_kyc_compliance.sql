-- ============================================================
-- Module: kyc_compliance
-- GENERATED from Laravel migrations (source of truth) via pg_dump.
-- Do not edit by hand; regenerate with database/schema/regenerate.sh
-- ============================================================

CREATE TABLE public.kyc_profiles (
    id uuid NOT NULL,
    user_id uuid NOT NULL,
    requested_tier character varying(16) NOT NULL,
    status character varying(16) DEFAULT 'pending'::character varying NOT NULL,
    document_type character varying(24),
    document_number character varying(64),
    full_name character varying(128),
    date_of_birth date,
    country character varying(2) DEFAULT 'BD'::character varying NOT NULL,
    address text,
    document_paths jsonb,
    liveness_passed boolean DEFAULT false NOT NULL,
    reviewed_by uuid,
    reviewed_at timestamp(0) without time zone,
    rejection_reason text,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);

ALTER TABLE ONLY public.kyc_profiles
    ADD CONSTRAINT kyc_profiles_pkey PRIMARY KEY (id);
CREATE INDEX kyc_profiles_status_created_at_index ON public.kyc_profiles USING btree (status, created_at);
CREATE INDEX kyc_profiles_user_id_index ON public.kyc_profiles USING btree (user_id);
CREATE INDEX pp_idx_kyc_profiles_reviewed_by ON public.kyc_profiles USING btree (reviewed_by) WHERE (reviewed_by IS NOT NULL);

CREATE TABLE public.screening_results (
    id uuid NOT NULL,
    user_id uuid NOT NULL,
    context character varying(24) NOT NULL,
    subject_id uuid,
    provider character varying(32) DEFAULT 'internal'::character varying NOT NULL,
    result character varying(12) DEFAULT 'clear'::character varying NOT NULL,
    score smallint DEFAULT '0'::smallint NOT NULL,
    matches jsonb,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);

ALTER TABLE ONLY public.screening_results
    ADD CONSTRAINT screening_results_pkey PRIMARY KEY (id);
CREATE INDEX screening_results_user_id_context_index ON public.screening_results USING btree (user_id, context);

CREATE TABLE public.travel_rule_records (
    id uuid NOT NULL,
    withdrawal_id uuid,
    asset_id bigint,
    direction character varying(4) NOT NULL,
    amount numeric(78,0) NOT NULL,
    originator_name character varying(191),
    originator_account character varying(191),
    beneficiary_name character varying(191),
    beneficiary_vasp character varying(191),
    beneficiary_address character varying(191),
    status character varying(16) DEFAULT 'pending'::character varying NOT NULL,
    provider character varying(32),
    provider_ref character varying(128),
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);

ALTER TABLE ONLY public.travel_rule_records
    ADD CONSTRAINT travel_rule_records_pkey PRIMARY KEY (id);
CREATE INDEX travel_rule_records_asset_id_index ON public.travel_rule_records USING btree (asset_id);
CREATE INDEX travel_rule_records_status_index ON public.travel_rule_records USING btree (status);
CREATE INDEX travel_rule_records_withdrawal_id_index ON public.travel_rule_records USING btree (withdrawal_id);

CREATE TABLE public.aml_alerts (
    id uuid NOT NULL,
    user_id uuid NOT NULL,
    type character varying(40) NOT NULL,
    severity character varying(12) DEFAULT 'medium'::character varying NOT NULL,
    context character varying(24) DEFAULT 'withdrawal'::character varying NOT NULL,
    subject_type character varying(48),
    subject_id uuid,
    score smallint DEFAULT '0'::smallint NOT NULL,
    reasons jsonb,
    status character varying(16) DEFAULT 'open'::character varying NOT NULL,
    case_id uuid,
    resolved_by uuid,
    resolved_at timestamp(0) without time zone,
    resolution_note text,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);

ALTER TABLE ONLY public.aml_alerts
    ADD CONSTRAINT aml_alerts_pkey PRIMARY KEY (id);
CREATE INDEX aml_alerts_resolved_by_index ON public.aml_alerts USING btree (resolved_by);
CREATE INDEX aml_alerts_status_severity_created_at_index ON public.aml_alerts USING btree (status, severity, created_at);
CREATE INDEX aml_alerts_user_id_created_at_index ON public.aml_alerts USING btree (user_id, created_at);
CREATE INDEX pp_idx_aml_alerts_case ON public.aml_alerts USING btree (case_id) WHERE (case_id IS NOT NULL);

CREATE TABLE public.compliance_cases (
    id uuid NOT NULL,
    user_id uuid NOT NULL,
    status character varying(16) DEFAULT 'open'::character varying NOT NULL,
    risk_level character varying(12) DEFAULT 'medium'::character varying NOT NULL,
    reason character varying(48) NOT NULL,
    summary text,
    sar_filed boolean DEFAULT false NOT NULL,
    sar_reference character varying(64),
    sar_activity_type character varying(48),
    sar_narrative text,
    sar_amount numeric(38,0),
    sar_filed_at timestamp(0) without time zone,
    assigned_to uuid,
    opened_by uuid,
    closed_at timestamp(0) without time zone,
    resolution text,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);

ALTER TABLE ONLY public.compliance_cases
    ADD CONSTRAINT compliance_cases_pkey PRIMARY KEY (id);
CREATE INDEX compliance_cases_opened_by_index ON public.compliance_cases USING btree (opened_by);
CREATE INDEX compliance_cases_status_risk_level_created_at_index ON public.compliance_cases USING btree (status, risk_level, created_at);
CREATE INDEX compliance_cases_user_id_index ON public.compliance_cases USING btree (user_id);
CREATE INDEX pp_idx_compliance_cases_assigned ON public.compliance_cases USING btree (assigned_to) WHERE (assigned_to IS NOT NULL);

CREATE TABLE public.compliance_list_entries (
    id uuid NOT NULL,
    list character varying(16) NOT NULL,
    kind character varying(16) NOT NULL,
    value character varying(191) NOT NULL,
    reason character varying(255),
    source character varying(64),
    added_by uuid,
    expires_at timestamp(0) without time zone,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);

ALTER TABLE ONLY public.compliance_list_entries
    ADD CONSTRAINT compliance_list_entries_pkey PRIMARY KEY (id);
CREATE INDEX compliance_list_entries_added_by_index ON public.compliance_list_entries USING btree (added_by);
CREATE INDEX compliance_list_entries_list_kind_index ON public.compliance_list_entries USING btree (list, kind);
CREATE INDEX compliance_list_entries_value_index ON public.compliance_list_entries USING btree (value);


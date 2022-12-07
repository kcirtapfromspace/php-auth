\c dcp;
--
-- PostgreSQL database dump
--

-- Dumped from database version 9.4.9
-- Dumped by pg_dump version 9.4.0
-- Started on 2016-11-18 12:28:58

SET statement_timeout = 0;
SET lock_timeout = 0;
SET client_encoding = 'UTF8';
SET standard_conforming_strings = off;
SET check_function_bodies = false;
SET client_min_messages = warning;
SET escape_string_warning = off;

SET search_path = elr, public, pg_catalog;

SET default_tablespace = '';

SET default_with_oids = false;


--
-- Tables
--

CREATE TABLE auth_conditions (
    id        serial NOT NULL,
    role_id   integer,
    condition character varying,
    CONSTRAINT auth_conditions_pkey PRIMARY KEY (id)
)
WITH (
    OIDS=FALSE
);
ALTER TABLE auth_conditions
    OWNER TO droolsuser;
GRANT ALL ON TABLE auth_conditions TO droolsuser;
GRANT SELECT, UPDATE, INSERT, DELETE ON TABLE auth_conditions TO "elr-rw";
GRANT SELECT ON TABLE auth_conditions TO "elr-ro";


CREATE TABLE auth_menus (
    id      serial  NOT NULL,
    role_id integer NOT NULL,
    menu_id integer NOT NULL,
    CONSTRAINT auth_menus_pkey PRIMARY KEY (id)
)
WITH (
    OIDS=FALSE
);
ALTER TABLE auth_menus
    OWNER TO droolsuser;
GRANT ALL ON TABLE auth_menus TO droolsuser;
GRANT SELECT, UPDATE, INSERT, DELETE ON TABLE auth_menus TO "elr-rw";
GRANT SELECT ON TABLE auth_menus TO "elr-ro";


CREATE TABLE auth_role_types (
    id    serial NOT NULL,
    label character varying,
    CONSTRAINT auth_role_types_pkey PRIMARY KEY (id)
)
WITH (
    OIDS=FALSE
);
ALTER TABLE auth_role_types
    OWNER TO droolsuser;
GRANT ALL ON TABLE auth_role_types TO droolsuser;
GRANT SELECT, UPDATE, INSERT, DELETE ON TABLE auth_role_types TO "elr-rw";
GRANT SELECT ON TABLE auth_role_types TO "elr-ro";


CREATE TABLE auth_roles (
    id            serial  NOT NULL,
    name          character varying,
    nedss_role_id integer,
    role_type     integer NOT NULL DEFAULT 3,
    CONSTRAINT auth_roles_pkey PRIMARY KEY (id)
)
WITH (
    OIDS=FALSE
);
ALTER TABLE auth_roles
    OWNER TO droolsuser;
GRANT ALL ON TABLE auth_roles TO droolsuser;
GRANT SELECT, UPDATE, INSERT, DELETE ON TABLE auth_roles TO "elr-rw";
GRANT SELECT ON TABLE auth_roles TO "elr-ro";


CREATE TABLE batch_notification_config (
    id          integer NOT NULL DEFAULT 1,
    udoh_enable boolean NOT NULL DEFAULT TRUE,
    lhd_enable  boolean NOT NULL DEFAULT TRUE,
    udoh_email  character varying,
    CONSTRAINT batch_notification_config_pkey PRIMARY KEY (id)
)
WITH (
    OIDS=FALSE
);
ALTER TABLE batch_notification_config
    OWNER TO droolsuser;
GRANT ALL ON TABLE batch_notification_config TO droolsuser;
GRANT SELECT, UPDATE, INSERT, DELETE ON TABLE batch_notification_config TO "elr-rw";
GRANT SELECT ON TABLE batch_notification_config TO "elr-ro";


CREATE TABLE batch_notification_custom_jurisdictions (
    id          serial  NOT NULL,
    name        character varying(255),
    recipients  character varying,
    link_to_lab boolean NOT NULL DEFAULT FALSE,
    CONSTRAINT batch_notification_custom_jurisdictions_pkey PRIMARY KEY (id)
)
WITH (
    OIDS=FALSE
);
ALTER TABLE batch_notification_custom_jurisdictions
    OWNER TO droolsuser;
GRANT ALL ON TABLE batch_notification_custom_jurisdictions TO droolsuser;
GRANT SELECT, UPDATE, INSERT, DELETE ON TABLE batch_notification_custom_jurisdictions TO "elr-rw";
GRANT SELECT ON TABLE batch_notification_custom_jurisdictions TO "elr-ro";


CREATE TABLE batch_notification_log (
    id               serial                      NOT NULL,
    created          timestamp without time zone NOT NULL DEFAULT now(),
    email            character varying(100)      NOT NULL,
    jurisdiction     integer,
    notification_ids character varying,
    success          boolean                     NOT NULL DEFAULT FALSE,
    custom           boolean                     NOT NULL DEFAULT FALSE,
    CONSTRAINT batch_notifications_log_pkey PRIMARY KEY (id)
)
WITH (
    OIDS=FALSE
);
ALTER TABLE batch_notification_log
    OWNER TO droolsuser;
GRANT ALL ON TABLE batch_notification_log TO droolsuser;
GRANT SELECT, UPDATE, INSERT, DELETE ON TABLE batch_notification_log TO "elr-rw";
GRANT SELECT ON TABLE batch_notification_log TO "elr-ro";

CREATE INDEX batch_notifications_log_idx ON batch_notification_log USING btree (created, email, jurisdiction, success);


CREATE TABLE batch_notification_types (
    id        serial                 NOT NULL,
    label     character varying(100) NOT NULL,
    state_use boolean,
    lhd_use   boolean,
    sort      integer,
    custom    integer,
    CONSTRAINT batch_notification_types_pkey PRIMARY KEY (id)
)
WITH (
    OIDS=FALSE
);
ALTER TABLE batch_notification_types
    OWNER TO droolsuser;
GRANT ALL ON TABLE batch_notification_types TO droolsuser;
GRANT SELECT, UPDATE, INSERT, DELETE ON TABLE batch_notification_types TO "elr-rw";
GRANT SELECT ON TABLE batch_notification_types TO "elr-ro";


CREATE TABLE cda_messages (
    id         serial                      NOT NULL,
    created_at timestamp without time zone NOT NULL DEFAULT LOCALTIMESTAMP,
    connector  character varying,
    cda_xml    character varying,
    hl7_xml    character varying,
    sent       integer                              DEFAULT 0,
    parsed     integer                     NOT NULL DEFAULT 0,
    CONSTRAINT cda_messages_pkey PRIMARY KEY (id)
)
WITH (
    OIDS=FALSE
);
ALTER TABLE cda_messages
    OWNER TO droolsuser;
GRANT ALL ON TABLE cda_messages TO droolsuser;
GRANT SELECT, UPDATE, INSERT, DELETE ON TABLE cda_messages TO "elr-rw";
GRANT SELECT ON TABLE cda_messages TO "elr-ro";


--
-- TOC entry 212 (class 1259 OID 402010)
-- Name: gateway_channels; Type: TABLE; Schema: elr; Owner: droolsuser; Tablespace: 
--

CREATE TABLE gateway_channels (
    id integer NOT NULL,
    user_id integer NOT NULL,
    channel character varying(10) NOT NULL,
    prod boolean DEFAULT false NOT NULL
);


ALTER TABLE gateway_channels OWNER TO droolsuser;

--
-- TOC entry 213 (class 1259 OID 402014)
-- Name: gateway_channels_id_seq; Type: SEQUENCE; Schema: elr; Owner: droolsuser
--

CREATE SEQUENCE gateway_channels_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER TABLE gateway_channels_id_seq OWNER TO droolsuser;

--
-- TOC entry 4791 (class 0 OID 0)
-- Dependencies: 213
-- Name: gateway_channels_id_seq; Type: SEQUENCE OWNED BY; Schema: elr; Owner: droolsuser
--

ALTER SEQUENCE gateway_channels_id_seq OWNED BY gateway_channels.id;


--
-- TOC entry 214 (class 1259 OID 402016)
-- Name: gateway_exceptions; Type: TABLE; Schema: elr; Owner: droolsuser; Tablespace: 
--

CREATE TABLE gateway_exceptions (
    id integer NOT NULL,
    request_id integer,
    created_at timestamp without time zone DEFAULT now() NOT NULL,
    exception character varying
);


ALTER TABLE gateway_exceptions OWNER TO droolsuser;

--
-- TOC entry 215 (class 1259 OID 402023)
-- Name: gateway_exceptions_id_seq; Type: SEQUENCE; Schema: elr; Owner: droolsuser
--

CREATE SEQUENCE gateway_exceptions_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER TABLE gateway_exceptions_id_seq OWNER TO droolsuser;

--
-- TOC entry 4794 (class 0 OID 0)
-- Dependencies: 215
-- Name: gateway_exceptions_id_seq; Type: SEQUENCE OWNED BY; Schema: elr; Owner: droolsuser
--

ALTER SEQUENCE gateway_exceptions_id_seq OWNED BY gateway_exceptions.id;


--
-- TOC entry 216 (class 1259 OID 402025)
-- Name: gateway_requests; Type: TABLE; Schema: elr; Owner: droolsuser; Tablespace: 
--

CREATE TABLE gateway_requests (
    id integer NOT NULL,
    original_message_id integer,
    created_at timestamp without time zone DEFAULT now() NOT NULL,
    sender_ip character varying(20),
    connector character varying,
    message character varying,
    ack character varying(5)
);


ALTER TABLE gateway_requests OWNER TO droolsuser;

--
-- TOC entry 217 (class 1259 OID 402032)
-- Name: gateway_requests_id_seq; Type: SEQUENCE; Schema: elr; Owner: droolsuser
--

CREATE SEQUENCE gateway_requests_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER TABLE gateway_requests_id_seq OWNER TO droolsuser;

--
-- TOC entry 4797 (class 0 OID 0)
-- Dependencies: 217
-- Name: gateway_requests_id_seq; Type: SEQUENCE OWNED BY; Schema: elr; Owner: droolsuser
--

ALTER SEQUENCE gateway_requests_id_seq OWNED BY gateway_requests.id;


--
-- TOC entry 218 (class 1259 OID 402034)
-- Name: gateway_users; Type: TABLE; Schema: elr; Owner: droolsuser; Tablespace: 
--

CREATE TABLE gateway_users (
    id integer NOT NULL,
    username character varying,
    password character varying,
    connector character varying
);


ALTER TABLE gateway_users OWNER TO droolsuser;

--
-- TOC entry 219 (class 1259 OID 402040)
-- Name: gateway_users_id_seq; Type: SEQUENCE; Schema: elr; Owner: droolsuser
--

CREATE SEQUENCE gateway_users_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER TABLE gateway_users_id_seq OWNER TO droolsuser;

--
-- TOC entry 4800 (class 0 OID 0)
-- Dependencies: 219
-- Name: gateway_users_id_seq; Type: SEQUENCE OWNED BY; Schema: elr; Owner: droolsuser
--

ALTER SEQUENCE gateway_users_id_seq OWNED BY gateway_users.id;


--
-- TOC entry 220 (class 1259 OID 402042)
-- Name: gatewaytest_original_messages; Type: TABLE; Schema: elr; Owner: droolsuser; Tablespace: 
--

CREATE TABLE gatewaytest_original_messages (
    id integer NOT NULL,
    message text,
    connector character varying(200),
    sent smallint DEFAULT 0,
    status smallint DEFAULT 0,
    created_at timestamp without time zone DEFAULT now(),
    updated_at timestamp without time zone,
    port character varying(25),
    message_type character varying(50),
    channel integer DEFAULT 0,
    test integer DEFAULT 0
);


ALTER TABLE gatewaytest_original_messages OWNER TO droolsuser;

--
-- TOC entry 221 (class 1259 OID 402053)
-- Name: gatewaytest_original_messages_id_seq; Type: SEQUENCE; Schema: elr; Owner: droolsuser
--

CREATE SEQUENCE gatewaytest_original_messages_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER TABLE gatewaytest_original_messages_id_seq OWNER TO droolsuser;

--
-- TOC entry 4803 (class 0 OID 0)
-- Dependencies: 221
-- Name: gatewaytest_original_messages_id_seq; Type: SEQUENCE OWNED BY; Schema: elr; Owner: droolsuser
--

ALTER SEQUENCE gatewaytest_original_messages_id_seq OWNED BY gatewaytest_original_messages.id;


--
-- TOC entry 222 (class 1259 OID 402055)
-- Name: graylist_request_audits; Type: TABLE; Schema: elr; Owner: droolsuser; Tablespace: 
--

CREATE TABLE graylist_request_audits (
    id integer NOT NULL,
    user_id character varying(100),
    message_action_id integer,
    graylist_request_id integer,
    created_at timestamp without time zone,
    system_status_id integer DEFAULT 0,
    info character varying
);


ALTER TABLE graylist_request_audits OWNER TO droolsuser;

--
-- TOC entry 223 (class 1259 OID 402062)
-- Name: graylist_request_audits_id_seq; Type: SEQUENCE; Schema: elr; Owner: droolsuser
--

CREATE SEQUENCE graylist_request_audits_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER TABLE graylist_request_audits_id_seq OWNER TO droolsuser;

--
-- TOC entry 4806 (class 0 OID 0)
-- Dependencies: 223
-- Name: graylist_request_audits_id_seq; Type: SEQUENCE OWNED BY; Schema: elr; Owner: droolsuser
--

ALTER SEQUENCE graylist_request_audits_id_seq OWNED BY graylist_request_audits.id;


--
-- TOC entry 224 (class 1259 OID 402064)
-- Name: graylist_requests; Type: TABLE; Schema: elr; Owner: droolsuser; Tablespace: 
--

CREATE TABLE graylist_requests (
    id                   integer                                                                        NOT NULL,
    created_at           timestamp WITHOUT TIME ZONE DEFAULT ('now'::text)::timestamp WITHOUT TIME ZONE NOT NULL,
    originating_event_id integer,
    first_name           character varying,
    last_name            character varying,
    middle_name          character varying,
    dob                  timestamp WITHOUT TIME ZONE,
    condition            character varying,
    status               integer,
    event_date           timestamp WITHOUT TIME ZONE
);


ALTER TABLE graylist_requests OWNER TO droolsuser;

--
-- TOC entry 225 (class 1259 OID 402071)
-- Name: graylist_requests_id_seq; Type: SEQUENCE; Schema: elr; Owner: droolsuser
--

CREATE SEQUENCE graylist_requests_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER TABLE graylist_requests_id_seq OWNER TO droolsuser;

--
-- TOC entry 4809 (class 0 OID 0)
-- Dependencies: 225
-- Name: graylist_requests_id_seq; Type: SEQUENCE OWNED BY; Schema: elr; Owner: droolsuser
--

ALTER SEQUENCE graylist_requests_id_seq OWNED BY graylist_requests.id;


--
-- TOC entry 226 (class 1259 OID 402073)
-- Name: graylist_spool; Type: TABLE; Schema: elr; Owner: droolsuser; Tablespace: 
--

CREATE TABLE graylist_spool (
    id integer NOT NULL,
    created_at timestamp without time zone DEFAULT ('now'::text)::timestamp without time zone NOT NULL,
    port character varying,
    sent smallint DEFAULT 0 NOT NULL,
    event_id integer
);


ALTER TABLE graylist_spool OWNER TO droolsuser;

--
-- TOC entry 227 (class 1259 OID 402081)
-- Name: graylist_spool_id_seq; Type: SEQUENCE; Schema: elr; Owner: droolsuser
--

CREATE SEQUENCE graylist_spool_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER TABLE graylist_spool_id_seq OWNER TO droolsuser;

--
-- TOC entry 4812 (class 0 OID 0)
-- Dependencies: 227
-- Name: graylist_spool_id_seq; Type: SEQUENCE OWNED BY; Schema: elr; Owner: droolsuser
--

ALTER SEQUENCE graylist_spool_id_seq OWNED BY graylist_spool.id;


--
-- TOC entry 228 (class 1259 OID 402083)
-- Name: intake_stats_config; Type: TABLE; Schema: elr; Owner: droolsuser; Tablespace: 
--

CREATE TABLE intake_stats_config (
    id integer NOT NULL,
    received_sigma numeric,
    accepted_sigma numeric,
    connectors character varying,
    reporters character varying,
    distribution_list character varying
);


ALTER TABLE intake_stats_config OWNER TO droolsuser;

--
-- TOC entry 229 (class 1259 OID 402089)
-- Name: intake_stats_config_id_seq; Type: SEQUENCE; Schema: elr; Owner: droolsuser
--

CREATE SEQUENCE intake_stats_config_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER TABLE intake_stats_config_id_seq OWNER TO droolsuser;

--
-- TOC entry 4815 (class 0 OID 0)
-- Dependencies: 229
-- Name: intake_stats_config_id_seq; Type: SEQUENCE OWNED BY; Schema: elr; Owner: droolsuser
--

ALTER SEQUENCE intake_stats_config_id_seq OWNED BY intake_stats_config.id;


-- Table: interstate

CREATE TABLE interstate
(
    id serial NOT NULL,
    state text NOT NULL,
    ldap_cn text NOT NULL,
    transmitting boolean NOT NULL DEFAULT FALSE,
    PRIMARY KEY (id)
)
WITH (
    OIDS = FALSE
);

ALTER TABLE interstate
    OWNER to droolsuser;

GRANT ALL ON TABLE interstate TO droolsuser;
GRANT SELECT, UPDATE, DELETE, INSERT ON TABLE interstate TO "elr-rw";


--
-- TOC entry 230 (class 1259 OID 402091)
-- Name: vocab_master2app; Type: TABLE; Schema: elr; Owner: droolsuser; Tablespace: 
--

CREATE TABLE vocab_master2app (
    id integer NOT NULL,
    app_id integer,
    master_id integer,
    concept character varying,
    coded_value character varying,
    last_updated timestamp without time zone
);


ALTER TABLE vocab_master2app OWNER TO droolsuser;

--
-- TOC entry 231 (class 1259 OID 402097)
-- Name: master2app_id_seq; Type: SEQUENCE; Schema: elr; Owner: droolsuser
--

CREATE SEQUENCE master2app_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER TABLE master2app_id_seq OWNER TO droolsuser;

--
-- TOC entry 4818 (class 0 OID 0)
-- Dependencies: 231
-- Name: master2app_id_seq; Type: SEQUENCE OWNED BY; Schema: elr; Owner: droolsuser
--

ALTER SEQUENCE master2app_id_seq OWNED BY vocab_master2app.id;


-- Index: vocab_master2app_app_id_idx

-- DROP INDEX vocab_master2app_app_id_idx;

CREATE INDEX vocab_master2app_app_id_idx
  ON vocab_master2app
  USING btree
  (app_id);

-- Index: vocab_master2app_master_id_idx

-- DROP INDEX vocab_master2app_master_id_idx;

CREATE INDEX vocab_master2app_master_id_idx
  ON vocab_master2app
  USING btree
  (master_id);


--
-- TOC entry 504 (class 1259 OID 19066926)
-- Name: master_xml_flat; Type: TABLE; Schema: elr; Owner: dcpadmin; Tablespace: 
--

CREATE TABLE master_xml_flat (
    id                     integer               NOT NULL,
    system_messages_id     integer,
    report_date            timestamp without time zone,
    report_agency          character varying,
    local_specimen_source  character varying,
    collection_date        timestamp without time zone,
    segment_index          character varying,
    test_status            character varying,
    lab_test_date          timestamp without time zone,
    lab                    character varying,
    local_loinc_code       character varying,
    local_test_name        character varying,
    local_code             character varying,
    local_code_test_name   character varying,
    local_result_value     character varying,
    local_result_value_2   character varying,
    local_result_unit      character varying,
    local_reference_range  character varying,
    abnormal_flag          character varying,
    loinc_code             character varying,
    reference_range        character varying,
    organism               character varying,
    test_result            character varying,
    specimen_source        character varying,
    test_type              character varying,
    diagnostic_name        character varying,
    diagnostic_street_name character varying,
    diagnostic_city        character varying,
    diagnostic_state       character varying,
    diagnostic_zipcode     character varying,
    person_date_of_birth   date,
    person_gender          character varying,
    person_race            character varying,
    person_ethnicity       character varying,
    person_zip             character varying,
    disease                character varying,
    created_at             timestamp without time zone DEFAULT now(),
    updated_at             timestamp without time zone DEFAULT now(),
    pruned                 boolean DEFAULT false NOT NULL,
    lab_id                 integer,
    deleted                integer DEFAULT 0     NOT NULL,
    promoted_from_graylist boolean DEFAULT false NOT NULL
);


ALTER TABLE master_xml_flat OWNER TO dcpadmin;

--
-- TOC entry 503 (class 1259 OID 19066924)
-- Name: master_xml_flat_id_seq; Type: SEQUENCE; Schema: elr; Owner: dcpadmin
--

CREATE SEQUENCE master_xml_flat_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER TABLE master_xml_flat_id_seq OWNER TO dcpadmin;

--
-- TOC entry 4821 (class 0 OID 0)
-- Dependencies: 503
-- Name: master_xml_flat_id_seq; Type: SEQUENCE OWNED BY; Schema: elr; Owner: dcpadmin
--

ALTER SEQUENCE master_xml_flat_id_seq OWNED BY master_xml_flat.id;


--
-- TOC entry 596 (class 1259 OID 28514230)
-- Name: preprocessor_audit_exceptions; Type: TABLE; Schema: elr; Owner: droolsuser; Tablespace: 
--

CREATE TABLE preprocessor_audit_exceptions (
    id integer NOT NULL,
    system_original_messages_id integer NOT NULL,
    exception_message character varying,
    info character varying,
    date_created timestamp without time zone DEFAULT ('now'::text)::timestamp without time zone NOT NULL
);


ALTER TABLE preprocessor_audit_exceptions OWNER TO droolsuser;

--
-- TOC entry 595 (class 1259 OID 28514228)
-- Name: preprocessor_audit_exceptions_id_seq; Type: SEQUENCE; Schema: elr; Owner: droolsuser
--

CREATE SEQUENCE preprocessor_audit_exceptions_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER TABLE preprocessor_audit_exceptions_id_seq OWNER TO droolsuser;

--
-- TOC entry 4826 (class 0 OID 0)
-- Dependencies: 595
-- Name: preprocessor_audit_exceptions_id_seq; Type: SEQUENCE OWNED BY; Schema: elr; Owner: droolsuser
--

ALTER SEQUENCE preprocessor_audit_exceptions_id_seq OWNED BY preprocessor_audit_exceptions.id;


--
-- TOC entry 594 (class 1259 OID 28514213)
-- Name: preprocessor_exceptions; Type: TABLE; Schema: elr; Owner: droolsuser; Tablespace: 
--

CREATE TABLE preprocessor_exceptions (
    id integer NOT NULL,
    system_original_messages_id integer NOT NULL,
    exception_message character varying,
    info character varying,
    date_created timestamp without time zone DEFAULT ('now'::text)::timestamp without time zone NOT NULL
);


ALTER TABLE preprocessor_exceptions OWNER TO droolsuser;

--
-- TOC entry 593 (class 1259 OID 28514211)
-- Name: preprocessor_exceptions_id_seq; Type: SEQUENCE; Schema: elr; Owner: droolsuser
--

CREATE SEQUENCE preprocessor_exceptions_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER TABLE preprocessor_exceptions_id_seq OWNER TO droolsuser;

--
-- TOC entry 4828 (class 0 OID 0)
-- Dependencies: 593
-- Name: preprocessor_exceptions_id_seq; Type: SEQUENCE OWNED BY; Schema: elr; Owner: droolsuser
--

ALTER SEQUENCE preprocessor_exceptions_id_seq OWNED BY preprocessor_exceptions.id;


--
-- TOC entry 573 (class 1259 OID 27833336)
-- Name: ss_batched_messages; Type: TABLE; Schema: elr; Owner: droolsuser; Tablespace: 
--

CREATE TABLE ss_batched_messages (
    id integer NOT NULL,
    message character varying,
    connector character varying,
    application_name character varying,
    facility_name character varying,
    facility_id character varying,
    message_type character varying(3),
    message_trigger_event character varying(3),
    created_at timestamp without time zone DEFAULT ('now'::text)::timestamp without time zone NOT NULL,
    received boolean DEFAULT false NOT NULL,
    valid boolean DEFAULT false NOT NULL,
    locked boolean DEFAULT false NOT NULL,
    sent boolean DEFAULT false NOT NULL
);


ALTER TABLE ss_batched_messages OWNER TO droolsuser;

--
-- TOC entry 572 (class 1259 OID 27833334)
-- Name: ss_batched_messages_id_seq; Type: SEQUENCE; Schema: elr; Owner: droolsuser
--

CREATE SEQUENCE ss_batched_messages_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER TABLE ss_batched_messages_id_seq OWNER TO droolsuser;

--
-- TOC entry 4830 (class 0 OID 0)
-- Dependencies: 572
-- Name: ss_batched_messages_id_seq; Type: SEQUENCE OWNED BY; Schema: elr; Owner: droolsuser
--

ALTER SEQUENCE ss_batched_messages_id_seq OWNED BY ss_batched_messages.id;


--
-- TOC entry 233 (class 1259 OID 402101)
-- Name: ss_biosense; Type: TABLE; Schema: elr; Owner: droolsuser; Tablespace: 
--

CREATE TABLE ss_biosense (
    id integer NOT NULL,
    message text,
    sent integer DEFAULT 0,
    datecreated timestamp without time zone DEFAULT now()
);


ALTER TABLE ss_biosense OWNER TO droolsuser;

--
-- TOC entry 234 (class 1259 OID 402109)
-- Name: ss_biosense_id_seq; Type: SEQUENCE; Schema: elr; Owner: droolsuser
--

CREATE SEQUENCE ss_biosense_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER TABLE ss_biosense_id_seq OWNER TO droolsuser;

--
-- TOC entry 4832 (class 0 OID 0)
-- Dependencies: 234
-- Name: ss_biosense_id_seq; Type: SEQUENCE OWNED BY; Schema: elr; Owner: droolsuser
--

ALTER SEQUENCE ss_biosense_id_seq OWNED BY ss_biosense.id;


--
-- TOC entry 518 (class 1259 OID 27404721)
-- Name: ss_connectors; Type: TABLE; Schema: elr; Owner: droolsuser; Tablespace: 
--

CREATE TABLE ss_connectors (
    id integer NOT NULL,
    connector character varying
);


ALTER TABLE ss_connectors OWNER TO droolsuser;

--
-- TOC entry 517 (class 1259 OID 27404719)
-- Name: ss_connectors_id_seq; Type: SEQUENCE; Schema: elr; Owner: droolsuser
--

CREATE SEQUENCE ss_connectors_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER TABLE ss_connectors_id_seq OWNER TO droolsuser;

--
-- TOC entry 4835 (class 0 OID 0)
-- Dependencies: 517
-- Name: ss_connectors_id_seq; Type: SEQUENCE OWNED BY; Schema: elr; Owner: droolsuser
--

ALTER SEQUENCE ss_connectors_id_seq OWNED BY ss_connectors.id;


--
-- TOC entry 235 (class 1259 OID 402111)
-- Name: ss_facility; Type: TABLE; Schema: elr; Owner: droolsuser; Tablespace: 
--

CREATE TABLE ss_facility (
    facid character varying,
    id integer NOT NULL,
    fac_oid character varying,
    fac_name character varying,
    datecreated timestamp without time zone DEFAULT now()
);


ALTER TABLE ss_facility OWNER TO droolsuser;

--
-- TOC entry 236 (class 1259 OID 402118)
-- Name: ss_facility_id_seq; Type: SEQUENCE; Schema: elr; Owner: droolsuser
--

CREATE SEQUENCE ss_facility_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER TABLE ss_facility_id_seq OWNER TO droolsuser;

--
-- TOC entry 4837 (class 0 OID 0)
-- Dependencies: 236
-- Name: ss_facility_id_seq; Type: SEQUENCE OWNED BY; Schema: elr; Owner: droolsuser
--

ALTER SEQUENCE ss_facility_id_seq OWNED BY ss_facility.id;


--
-- TOC entry 239 (class 1259 OID 402132)
-- Name: structure_category; Type: TABLE; Schema: elr; Owner: droolsuser; Tablespace: 
--

CREATE TABLE structure_category (
    id integer NOT NULL,
    label character varying NOT NULL
);


ALTER TABLE structure_category OWNER TO droolsuser;

--
-- TOC entry 240 (class 1259 OID 402138)
-- Name: structure_category_application; Type: TABLE; Schema: elr; Owner: droolsuser; Tablespace: 
--

CREATE TABLE structure_category_application (
    id integer NOT NULL,
    app_id integer DEFAULT 1 NOT NULL,
    app_table character varying,
    app_category character varying,
    category_id integer NOT NULL
);


ALTER TABLE structure_category_application OWNER TO droolsuser;

--
-- TOC entry 241 (class 1259 OID 402145)
-- Name: structure_category_application_id_seq; Type: SEQUENCE; Schema: elr; Owner: droolsuser
--

CREATE SEQUENCE structure_category_application_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER TABLE structure_category_application_id_seq OWNER TO droolsuser;

--
-- TOC entry 4841 (class 0 OID 0)
-- Dependencies: 241
-- Name: structure_category_application_id_seq; Type: SEQUENCE OWNED BY; Schema: elr; Owner: droolsuser
--

ALTER SEQUENCE structure_category_application_id_seq OWNED BY structure_category_application.id;


--
-- TOC entry 242 (class 1259 OID 402147)
-- Name: structure_category_id_seq; Type: SEQUENCE; Schema: elr; Owner: droolsuser
--

CREATE SEQUENCE structure_category_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER TABLE structure_category_id_seq OWNER TO droolsuser;

--
-- TOC entry 4843 (class 0 OID 0)
-- Dependencies: 242
-- Name: structure_category_id_seq; Type: SEQUENCE OWNED BY; Schema: elr; Owner: droolsuser
--

ALTER SEQUENCE structure_category_id_seq OWNED BY structure_category.id;


--
-- TOC entry 243 (class 1259 OID 402149)
-- Name: structure_data_type; Type: TABLE; Schema: elr; Owner: droolsuser; Tablespace: 
--

CREATE TABLE structure_data_type (
    id integer NOT NULL,
    label character varying(100) NOT NULL
);


ALTER TABLE structure_data_type OWNER TO droolsuser;

--
-- TOC entry 499 (class 1259 OID 1152356)
-- Name: structure_knittable_loincs; Type: TABLE; Schema: elr; Owner: droolsuser; Tablespace: 
--

CREATE TABLE structure_knittable_loincs (
    id integer NOT NULL,
    lab_id integer NOT NULL,
    loinc character varying NOT NULL
);


ALTER TABLE structure_knittable_loincs OWNER TO droolsuser;

--
-- TOC entry 498 (class 1259 OID 1152354)
-- Name: structure_knittable_loincs_id_seq; Type: SEQUENCE; Schema: elr; Owner: droolsuser
--

CREATE SEQUENCE structure_knittable_loincs_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER TABLE structure_knittable_loincs_id_seq OWNER TO droolsuser;

--
-- TOC entry 4847 (class 0 OID 0)
-- Dependencies: 498
-- Name: structure_knittable_loincs_id_seq; Type: SEQUENCE OWNED BY; Schema: elr; Owner: droolsuser
--

ALTER SEQUENCE structure_knittable_loincs_id_seq OWNED BY structure_knittable_loincs.id;


--
-- TOC entry 244 (class 1259 OID 402152)
-- Name: structure_labs; Type: TABLE; Schema: elr; Owner: droolsuser; Tablespace: 
--

CREATE TABLE structure_labs (
    id                      serial  NOT NULL,
    ui_name                 character varying,
    hl7_name                character varying,
    xml_name                character varying,
    alias_for               integer NOT NULL DEFAULT 0,
    default_jurisdiction_id integer,
    visible                 boolean NOT NULL DEFAULT TRUE,
    ecrlab                  boolean NOT NULL DEFAULT FALSE
);


ALTER TABLE structure_labs OWNER TO droolsuser;

--
-- TOC entry 4431 (class 2606 OID 16966)
-- Name: structure_labs_hl7_name_key; Type: CONSTRAINT; Schema: elr; Owner: droolsuser; Tablespace: 
--

ALTER TABLE ONLY structure_labs
    ADD CONSTRAINT structure_labs_hl7_name_key UNIQUE (hl7_name);


--
-- TOC entry 4433 (class 2606 OID 16967)
-- Name: structure_labs_pkey; Type: CONSTRAINT; Schema: elr; Owner: droolsuser; Tablespace: 
--

ALTER TABLE ONLY structure_labs
    ADD CONSTRAINT structure_labs_pkey PRIMARY KEY (id);



-- Table: structure_hl7_valuetype

CREATE TABLE structure_hl7_valuetype
(
    id                serial            NOT NULL,
    lab_id            integer           NOT NULL,
    value_type        character varying NOT NULL,
    interpret_results boolean           NOT NULL DEFAULT FALSE,
    CONSTRAINT structure_hl7_valuetype_pkey PRIMARY KEY (id),
    CONSTRAINT structure_hl7_valuetype_lab_id_fkey FOREIGN KEY (lab_id)
    REFERENCES structure_labs (id) MATCH SIMPLE
    ON UPDATE NO ACTION ON DELETE CASCADE
)
WITH (
OIDS = FALSE
);
ALTER TABLE structure_hl7_valuetype
    OWNER TO droolsuser;
GRANT ALL ON TABLE structure_hl7_valuetype TO droolsuser;
GRANT SELECT, UPDATE, INSERT, DELETE ON TABLE structure_hl7_valuetype TO "elr-rw";

-- Table: structure_hl7_valuetype_defaults

CREATE TABLE structure_hl7_valuetype_defaults
(
    id                        serial  NOT NULL,
    value_type                character varying,
    default_interpret_results boolean NOT NULL DEFAULT FALSE,
    CONSTRAINT structure_hl7_valuetype_defaults_pkey PRIMARY KEY (id)
)
WITH (
OIDS = FALSE
);
ALTER TABLE structure_hl7_valuetype_defaults
    OWNER TO droolsuser;
GRANT ALL ON TABLE structure_hl7_valuetype_defaults TO droolsuser;
GRANT SELECT, UPDATE, INSERT, DELETE ON TABLE structure_hl7_valuetype_defaults TO "elr-rw";


--
-- TOC entry 246 (class 1259 OID 402162)
-- Name: structure_lookup_operator; Type: TABLE; Schema: elr; Owner: droolsuser; Tablespace: 
--

CREATE TABLE structure_lookup_operator (
    id integer NOT NULL,
    label character varying(100) NOT NULL
);


ALTER TABLE structure_lookup_operator OWNER TO droolsuser;

--
-- TOC entry 247 (class 1259 OID 402165)
-- Name: structure_lookup_operator_id_seq; Type: SEQUENCE; Schema: elr; Owner: droolsuser
--

CREATE SEQUENCE structure_lookup_operator_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER TABLE structure_lookup_operator_id_seq OWNER TO droolsuser;

--
-- TOC entry 4853 (class 0 OID 0)
-- Dependencies: 247
-- Name: structure_lookup_operator_id_seq; Type: SEQUENCE OWNED BY; Schema: elr; Owner: droolsuser
--

ALTER SEQUENCE structure_lookup_operator_id_seq OWNED BY structure_lookup_operator.id;


--
-- TOC entry 248 (class 1259 OID 402167)
-- Name: structure_operand_type; Type: TABLE; Schema: elr; Owner: droolsuser; Tablespace: 
--

CREATE TABLE structure_operand_type (
    id integer NOT NULL,
    label character varying(100) NOT NULL
);


ALTER TABLE structure_operand_type OWNER TO droolsuser;

--
-- TOC entry 249 (class 1259 OID 402170)
-- Name: structure_operator; Type: TABLE; Schema: elr; Owner: droolsuser; Tablespace: 
--

CREATE TABLE structure_operator (
    id integer NOT NULL,
    label character varying(100) NOT NULL,
    graphical character varying(20),
    operator_type smallint DEFAULT 1 NOT NULL
);


ALTER TABLE structure_operator OWNER TO droolsuser;

CREATE SEQUENCE structure_path_id
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;

ALTER TABLE structure_path_id OWNER TO droolsuser;


--
-- TOC entry 251 (class 1259 OID 402176)
-- Name: structure_path; Type: TABLE; Schema: elr; Owner: droolsuser; Tablespace: 
--

CREATE TABLE structure_path (
    id integer DEFAULT nextval('structure_path_id'::regclass) NOT NULL,
    xpath text NOT NULL,
    data_type_id integer NOT NULL,
    required boolean DEFAULT false,
    element text,
    category_id integer
);


ALTER TABLE structure_path OWNER TO droolsuser;

ALTER SEQUENCE structure_path_id OWNED BY structure_path.id;

--
-- TOC entry 252 (class 1259 OID 402184)
-- Name: structure_path_application; Type: TABLE; Schema: elr; Owner: droolsuser; Tablespace: 
--

CREATE TABLE structure_path_application (
    id integer NOT NULL,
    xpath character varying NOT NULL,
    required boolean DEFAULT false,
    element character varying,
    app_id integer NOT NULL,
    structure_lookup_operator_id integer DEFAULT 1 NOT NULL,
    structure_path_id integer,
    complex_rule_callback character varying,
    category_application_id integer
);


ALTER TABLE structure_path_application OWNER TO droolsuser;

--
-- TOC entry 253 (class 1259 OID 402192)
-- Name: structure_path_application_id_seq; Type: SEQUENCE; Schema: elr; Owner: droolsuser
--

CREATE SEQUENCE structure_path_application_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER TABLE structure_path_application_id_seq OWNER TO droolsuser;

--
-- TOC entry 4859 (class 0 OID 0)
-- Dependencies: 253
-- Name: structure_path_application_id_seq; Type: SEQUENCE OWNED BY; Schema: elr; Owner: droolsuser
--

ALTER SEQUENCE structure_path_application_id_seq OWNED BY structure_path_application.id;


-- Table: structure_xslt

-- DROP TABLE structure_xslt;

CREATE TABLE structure_xslt
(
    id                serial  NOT NULL,
    structure_labs_id integer NOT NULL,
    message_version   text    NOT NULL,
    xslt              text    NOT NULL,
    CONSTRAINT structure_xslt_pkey PRIMARY KEY (id),
    CONSTRAINT structure_xslt_structure_labs_id_fkey FOREIGN KEY (structure_labs_id)
    REFERENCES structure_labs (id) MATCH SIMPLE
    ON UPDATE NO ACTION ON DELETE CASCADE
)
WITH (
OIDS = FALSE
);
ALTER TABLE structure_xslt
    OWNER TO droolsuser;
GRANT ALL ON TABLE structure_xslt TO droolsuser;
GRANT ALL ON TABLE structure_xslt TO "elr-su";
GRANT SELECT, UPDATE, INSERT, DELETE ON TABLE structure_xslt TO "elr-rw";
GRANT SELECT ON TABLE structure_xslt TO "elr-ro";



--
-- TOC entry 256 (class 1259 OID 402205)
-- Name: structure_path_mirth; Type: TABLE; Schema: elr; Owner: droolsuser; Tablespace: 
--

CREATE TABLE structure_path_mirth (
    id              integer           NOT NULL,
    lab_id          integer           NOT NULL,
    message_version character varying NOT NULL,
    master_path_id  integer,
    glue_string     character varying,
    xpath           character varying NOT NULL,
    sequence        integer DEFAULT 1 NOT NULL
);


ALTER TABLE structure_path_mirth OWNER TO droolsuser;

--
-- TOC entry 257 (class 1259 OID 402212)
-- Name: structure_path_mirth_id_seq; Type: SEQUENCE; Schema: elr; Owner: droolsuser
--

CREATE SEQUENCE structure_path_mirth_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER TABLE structure_path_mirth_id_seq OWNER TO droolsuser;

--
-- TOC entry 4865 (class 0 OID 0)
-- Dependencies: 257
-- Name: structure_path_mirth_id_seq; Type: SEQUENCE OWNED BY; Schema: elr; Owner: droolsuser
--

ALTER SEQUENCE structure_path_mirth_id_seq OWNED BY structure_path_mirth.id;


CREATE SEQUENCE structure_path_rule_id
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;

ALTER TABLE structure_path_rule_id OWNER TO droolsuser;


--
-- TOC entry 259 (class 1259 OID 402216)
-- Name: structure_path_rule; Type: TABLE; Schema: elr; Owner: droolsuser; Tablespace: 
--

CREATE TABLE structure_path_rule (
    id integer DEFAULT nextval('structure_path_rule_id'::regclass) NOT NULL,
    path_id integer NOT NULL,
    operator_id integer NOT NULL,
    operand_type_id integer NOT NULL,
    operand_value text NOT NULL,
    sequence integer DEFAULT 1 NOT NULL,
    and_or_operator_id integer
);


ALTER TABLE structure_path_rule OWNER TO droolsuser;

ALTER SEQUENCE structure_path_rule_id OWNED BY structure_path_rule.id;

--
-- TOC entry 260 (class 1259 OID 402224)
-- Name: system_action_categories; Type: TABLE; Schema: elr; Owner: droolsuser; Tablespace: 
--

CREATE TABLE system_action_categories (
    id integer NOT NULL,
    name character varying(255)
);


ALTER TABLE system_action_categories OWNER TO droolsuser;

--
-- TOC entry 261 (class 1259 OID 402227)
-- Name: system_action_categories_id_seq; Type: SEQUENCE; Schema: elr; Owner: droolsuser
--

CREATE SEQUENCE system_action_categories_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER TABLE system_action_categories_id_seq OWNER TO droolsuser;

--
-- TOC entry 4869 (class 0 OID 0)
-- Dependencies: 261
-- Name: system_action_categories_id_seq; Type: SEQUENCE OWNED BY; Schema: elr; Owner: droolsuser
--

ALTER SEQUENCE system_action_categories_id_seq OWNED BY system_action_categories.id;


--
-- TOC entry 506 (class 1259 OID 22185999)
-- Name: system_alert_types; Type: TABLE; Schema: elr; Owner: droolsuser; Tablespace: 
--

CREATE TABLE system_alert_types (
    id integer NOT NULL,
    name character varying,
    type integer
);


ALTER TABLE system_alert_types OWNER TO droolsuser;

--
-- TOC entry 505 (class 1259 OID 22185997)
-- Name: system_alert_types_id_seq; Type: SEQUENCE; Schema: elr; Owner: droolsuser
--

CREATE SEQUENCE system_alert_types_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER TABLE system_alert_types_id_seq OWNER TO droolsuser;

--
-- TOC entry 4872 (class 0 OID 0)
-- Dependencies: 505
-- Name: system_alert_types_id_seq; Type: SEQUENCE OWNED BY; Schema: elr; Owner: droolsuser
--

ALTER SEQUENCE system_alert_types_id_seq OWNED BY system_alert_types.id;


--
-- TOC entry 508 (class 1259 OID 22186011)
-- Name: system_alerts; Type: TABLE; Schema: elr; Owner: droolsuser; Tablespace: 
--

CREATE TABLE system_alerts (
    id integer NOT NULL,
    created_at timestamp without time zone DEFAULT ('now'::text)::timestamp without time zone NOT NULL,
    alert_type_id integer NOT NULL,
    info character varying,
    alt_info character varying,
    resolved boolean DEFAULT false NOT NULL
);


ALTER TABLE system_alerts OWNER TO droolsuser;

--
-- TOC entry 507 (class 1259 OID 22186009)
-- Name: system_alerts_id_seq; Type: SEQUENCE; Schema: elr; Owner: droolsuser
--

CREATE SEQUENCE system_alerts_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER TABLE system_alerts_id_seq OWNER TO droolsuser;

--
-- TOC entry 4874 (class 0 OID 0)
-- Dependencies: 507
-- Name: system_alerts_id_seq; Type: SEQUENCE OWNED BY; Schema: elr; Owner: droolsuser
--

ALTER SEQUENCE system_alerts_id_seq OWNED BY system_alerts.id;


--
-- TOC entry 262 (class 1259 OID 402229)
-- Name: system_audit_exceptions; Type: TABLE; Schema: elr; Owner: droolsuser; Tablespace: 
--

CREATE TABLE system_audit_exceptions (
    id integer NOT NULL,
    system_messages_audits_id integer NOT NULL,
    system_exceptions_id integer NOT NULL,
    info character varying
);


ALTER TABLE system_audit_exceptions OWNER TO droolsuser;

--
-- TOC entry 263 (class 1259 OID 402235)
-- Name: system_audit_exceptions_id_seq; Type: SEQUENCE; Schema: elr; Owner: droolsuser
--

CREATE SEQUENCE system_audit_exceptions_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER TABLE system_audit_exceptions_id_seq OWNER TO droolsuser;

--
-- TOC entry 4876 (class 0 OID 0)
-- Dependencies: 263
-- Name: system_audit_exceptions_id_seq; Type: SEQUENCE OWNED BY; Schema: elr; Owner: droolsuser
--

ALTER SEQUENCE system_audit_exceptions_id_seq OWNED BY system_audit_exceptions.id;


--
-- TOC entry 264 (class 1259 OID 402237)
-- Name: system_districts; Type: TABLE; Schema: elr; Owner: droolsuser; Tablespace: 
--

CREATE TABLE system_districts (
    id serial NOT NULL,
    district character varying(4) NOT NULL,
    health_district character varying(50) NOT NULL,
    date_created timestamp without time zone DEFAULT now() NOT NULL,
    system_external_id integer,
    close_surveillance boolean DEFAULT true NOT NULL,
    enabled boolean DEFAULT true NOT NULL
);


ALTER TABLE system_districts OWNER TO droolsuser;

--
-- TOC entry 4457 (class 2606 OID 16979)
-- Name: system_districts_pkey; Type: CONSTRAINT; Schema: elr; Owner: droolsuser; Tablespace: 
--

ALTER TABLE ONLY system_districts
    ADD CONSTRAINT system_districts_pkey PRIMARY KEY (id);



--
-- TOC entry 266 (class 1259 OID 402245)
-- Name: system_exceptions; Type: TABLE; Schema: elr; Owner: droolsuser; Tablespace: 
--

CREATE TABLE system_exceptions (
    id                    integer NOT NULL,
    exception_id          integer,
    name                  character varying(100),
    description           text,
    exception_type_id     integer,
    allow_retry           boolean NOT NULL DEFAULT TRUE,
    allow_child_vocab_add boolean NOT NULL DEFAULT FALSE
);


ALTER TABLE system_exceptions OWNER TO droolsuser;

--
-- TOC entry 267 (class 1259 OID 402252)
-- Name: system_exceptions_id_seq; Type: SEQUENCE; Schema: elr; Owner: droolsuser
--

CREATE SEQUENCE system_exceptions_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER TABLE system_exceptions_id_seq OWNER TO droolsuser;

--
-- TOC entry 4882 (class 0 OID 0)
-- Dependencies: 267
-- Name: system_exceptions_id_seq; Type: SEQUENCE OWNED BY; Schema: elr; Owner: droolsuser
--

ALTER SEQUENCE system_exceptions_id_seq OWNED BY system_exceptions.id;


--
-- TOC entry 269 (class 1259 OID 402256)
-- Name: system_menus; Type: TABLE; Schema: elr; Owner: droolsuser; Tablespace: 
--

CREATE TABLE system_menus (
    id integer NOT NULL,
    name character varying(150),
    menu_name character varying(150),
    menu_url character varying(255),
    menu_type smallint DEFAULT 1,
    sorty smallint
);


ALTER TABLE system_menus OWNER TO droolsuser;

--
-- TOC entry 270 (class 1259 OID 402263)
-- Name: system_menus_id_seq; Type: SEQUENCE; Schema: elr; Owner: droolsuser
--

CREATE SEQUENCE system_menus_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER TABLE system_menus_id_seq OWNER TO droolsuser;

--
-- TOC entry 4885 (class 0 OID 0)
-- Dependencies: 270
-- Name: system_menus_id_seq; Type: SEQUENCE OWNED BY; Schema: elr; Owner: droolsuser
--

ALTER SEQUENCE system_menus_id_seq OWNED BY system_menus.id;


--
-- TOC entry 271 (class 1259 OID 402265)
-- Name: system_message_actions; Type: TABLE; Schema: elr; Owner: droolsuser; Tablespace: 
--

CREATE TABLE system_message_actions (
    id integer NOT NULL,
    action_category_id integer,
    message text,
    status smallint DEFAULT 1
);


ALTER TABLE system_message_actions OWNER TO droolsuser;

--
-- TOC entry 272 (class 1259 OID 402272)
-- Name: system_message_actions_id_seq; Type: SEQUENCE; Schema: elr; Owner: droolsuser
--

CREATE SEQUENCE system_message_actions_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER TABLE system_message_actions_id_seq OWNER TO droolsuser;

--
-- TOC entry 4888 (class 0 OID 0)
-- Dependencies: 272
-- Name: system_message_actions_id_seq; Type: SEQUENCE OWNED BY; Schema: elr; Owner: droolsuser
--

ALTER SEQUENCE system_message_actions_id_seq OWNED BY system_message_actions.id;


--
-- TOC entry 273 (class 1259 OID 402274)
-- Name: system_message_comments; Type: TABLE; Schema: elr; Owner: droolsuser; Tablespace: 
--

CREATE TABLE system_message_comments (
    id integer NOT NULL,
    created_at timestamp without time zone DEFAULT now(),
    system_message_id integer,
    comment character varying,
    user_id character varying(100)
);


ALTER TABLE system_message_comments OWNER TO droolsuser;

--
-- TOC entry 274 (class 1259 OID 402281)
-- Name: system_message_comments_id_seq; Type: SEQUENCE; Schema: elr; Owner: droolsuser
--

CREATE SEQUENCE system_message_comments_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER TABLE system_message_comments_id_seq OWNER TO droolsuser;

--
-- TOC entry 4891 (class 0 OID 0)
-- Dependencies: 274
-- Name: system_message_comments_id_seq; Type: SEQUENCE OWNED BY; Schema: elr; Owner: droolsuser
--

ALTER SEQUENCE system_message_comments_id_seq OWNED BY system_message_comments.id;


--
-- TOC entry 275 (class 1259 OID 402283)
-- Name: system_message_exceptions; Type: TABLE; Schema: elr; Owner: droolsuser; Tablespace: 
--

CREATE TABLE system_message_exceptions (
    id integer NOT NULL,
    system_message_id integer,
    exception_id integer,
    info character varying
);


ALTER TABLE system_message_exceptions OWNER TO droolsuser;

--
-- TOC entry 276 (class 1259 OID 402289)
-- Name: system_message_exceptions_id_seq; Type: SEQUENCE; Schema: elr; Owner: droolsuser
--

CREATE SEQUENCE system_message_exceptions_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER TABLE system_message_exceptions_id_seq OWNER TO droolsuser;

--
-- TOC entry 4894 (class 0 OID 0)
-- Dependencies: 276
-- Name: system_message_exceptions_id_seq; Type: SEQUENCE OWNED BY; Schema: elr; Owner: droolsuser
--

ALTER SEQUENCE system_message_exceptions_id_seq OWNED BY system_message_exceptions.id;


--
-- TOC entry 277 (class 1259 OID 402291)
-- Name: system_message_flag_comments; Type: TABLE; Schema: elr; Owner: droolsuser; Tablespace: 
--

CREATE TABLE system_message_flag_comments (
    id integer NOT NULL,
    system_message_id integer NOT NULL,
    system_message_flag_id integer NOT NULL,
    info character varying(255)
);


ALTER TABLE system_message_flag_comments OWNER TO droolsuser;

--
-- TOC entry 278 (class 1259 OID 402294)
-- Name: system_message_flag_comments_id_seq; Type: SEQUENCE; Schema: elr; Owner: droolsuser
--

CREATE SEQUENCE system_message_flag_comments_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER TABLE system_message_flag_comments_id_seq OWNER TO droolsuser;

--
-- TOC entry 4897 (class 0 OID 0)
-- Dependencies: 278
-- Name: system_message_flag_comments_id_seq; Type: SEQUENCE OWNED BY; Schema: elr; Owner: droolsuser
--

ALTER SEQUENCE system_message_flag_comments_id_seq OWNED BY system_message_flag_comments.id;


--
-- TOC entry 279 (class 1259 OID 402296)
-- Name: system_message_flags; Type: TABLE; Schema: elr; Owner: droolsuser; Tablespace: 
--

CREATE TABLE system_message_flags (
    id integer NOT NULL,
    label character varying(100)
);


ALTER TABLE system_message_flags OWNER TO droolsuser;

--
-- TOC entry 280 (class 1259 OID 402299)
-- Name: system_message_flags_id_seq; Type: SEQUENCE; Schema: elr; Owner: droolsuser
--

CREATE SEQUENCE system_message_flags_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER TABLE system_message_flags_id_seq OWNER TO droolsuser;

--
-- TOC entry 4900 (class 0 OID 0)
-- Dependencies: 280
-- Name: system_message_flags_id_seq; Type: SEQUENCE OWNED BY; Schema: elr; Owner: droolsuser
--

ALTER SEQUENCE system_message_flags_id_seq OWNED BY system_message_flags.id;


--
-- TOC entry 281 (class 1259 OID 402301)
-- Name: system_messages; Type: TABLE; Schema: elr; Owner: droolsuser; Tablespace: 
--

CREATE TABLE system_messages (
    id                         serial                                NOT NULL,
    external_system_id         character varying(11),
    system_id                  integer                     DEFAULT (1) :: numeric,
    status                     integer                     DEFAULT 0,
    sent_message               smallint                    DEFAULT 0,
    channel                    smallint                    DEFAULT 0,
    assigned_date              timestamp WITHOUT TIME ZONE,
    master_xml                 text,
    edi_xml                    text,
    transformed_xml            text,
    lab_name                   text,
    loinc_code                 text,
    final_status               integer,
    status_message             text,
    exception_status           integer,
    deleted                    integer                     DEFAULT 0 NOT NULL,
    retry                      integer                     DEFAULT 0,
    original_date              timestamp WITHOUT TIME ZONE,
    event_id                   integer                     DEFAULT 0,
    participation_id           integer                     DEFAULT 0,
    disease                    text,
    fname                      character varying,
    lname                      character varying,
    mname                      character varying,
    jurisdiction_id            INTEGER,
    exception_ids              character varying(50),
    event_record_id            character varying(150),
    address_is_valid           smallint                    DEFAULT 0,
    lab_result_id              integer,
    original_message_id        integer,
    lab_id                     integer,
    created_at                 timestamp WITHOUT TIME ZONE DEFAULT now(),
    message_flags              integer                     DEFAULT 0 NOT NULL,
    clinician                  character varying,
    segment_index              character varying,
    child_loinc                character varying,
    child_test_code            character varying,
    local_result_value         character varying,
    local_result_value_2       character varying,
    reported_at                timestamp WITHOUT TIME ZONE,
    immediate_notify           boolean,
    dob                        date,
    copy_parent_id             integer,
    vocab_app_id               integer                     DEFAULT 1 NOT NULL,
    lab_test_result            character varying,
    susceptibility_test_result character varying,
    o2m_performed              boolean                               NOT NULL DEFAULT FALSE,
    o2m_event_id               integer
);


ALTER TABLE system_messages OWNER TO droolsuser;

--
-- TOC entry 4493 (class 2606 OID 16989)
-- Name: system_messages_pkey; Type: CONSTRAINT; Schema: elr; Owner: droolsuser; Tablespace: 
--

ALTER TABLE ONLY system_messages
    ADD CONSTRAINT system_messages_pkey PRIMARY KEY (id);


--
-- TOC entry 282 (class 1259 OID 402320)
-- Name: system_messages_audits; Type: TABLE; Schema: elr; Owner: droolsuser; Tablespace: 
--

CREATE TABLE system_messages_audits (
    id integer NOT NULL,
    user_id character varying(100),
    message_action_id integer,
    system_message_id integer,
    created_at timestamp without time zone,
    action_category_id integer DEFAULT 0,
    lab_id integer,
    system_exception_id integer,
    fname character varying DEFAULT 100,
    lname character varying(100),
    system_status_id integer DEFAULT 0,
    info character varying,
    original_message_id integer
);


ALTER TABLE system_messages_audits OWNER TO droolsuser;

--
-- TOC entry 283 (class 1259 OID 402329)
-- Name: system_messages_audits_id_seq; Type: SEQUENCE; Schema: elr; Owner: droolsuser
--

CREATE SEQUENCE system_messages_audits_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER TABLE system_messages_audits_id_seq OWNER TO droolsuser;

--
-- TOC entry 4904 (class 0 OID 0)
-- Dependencies: 283
-- Name: system_messages_audits_id_seq; Type: SEQUENCE OWNED BY; Schema: elr; Owner: droolsuser
--

ALTER SEQUENCE system_messages_audits_id_seq OWNED BY system_messages_audits.id;


--
-- TOC entry 285 (class 1259 OID 402333)
-- Name: system_nedss_xml_audits; Type: TABLE; Schema: elr; Owner: droolsuser; Tablespace: 
--

CREATE TABLE system_nedss_xml_audits (
    id integer NOT NULL,
    is_update boolean DEFAULT false NOT NULL,
    created_at timestamp without time zone DEFAULT now() NOT NULL,
    system_messages_audits_id integer,
    previous_xml xml,
    sent_xml xml
);


ALTER TABLE system_nedss_xml_audits OWNER TO droolsuser;

--
-- TOC entry 4908 (class 0 OID 0)
-- Dependencies: 285
-- Name: TABLE system_nedss_xml_audits; Type: COMMENT; Schema: elr; Owner: droolsuser
--

COMMENT ON TABLE system_nedss_xml_audits IS 'Stores NEDSS XML modified by ELR.  Includes NEDSS XML modified to add event to existing person, XML modified to update an existing event, and previous version of NEDSS XML prior to updating.';


--
-- TOC entry 4909 (class 0 OID 0)
-- Dependencies: 285
-- Name: COLUMN system_nedss_xml_audits.is_update; Type: COMMENT; Schema: elr; Owner: droolsuser
--

COMMENT ON COLUMN system_nedss_xml_audits.is_update IS 'If true, XML record is for an updateCmr; if false, for addCmr.';


--
-- TOC entry 286 (class 1259 OID 402341)
-- Name: system_nedss_xml_audits_id_seq; Type: SEQUENCE; Schema: elr; Owner: droolsuser
--

CREATE SEQUENCE system_nedss_xml_audits_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER TABLE system_nedss_xml_audits_id_seq OWNER TO droolsuser;

--
-- TOC entry 4911 (class 0 OID 0)
-- Dependencies: 286
-- Name: system_nedss_xml_audits_id_seq; Type: SEQUENCE OWNED BY; Schema: elr; Owner: droolsuser
--

ALTER SEQUENCE system_nedss_xml_audits_id_seq OWNED BY system_nedss_xml_audits.id;


--
-- TOC entry 287 (class 1259 OID 402343)
-- Name: system_original_messages; Type: TABLE; Schema: elr; Owner: droolsuser; Tablespace: 
--

CREATE TABLE system_original_messages (
    id integer NOT NULL,
    message text,
    connector character varying(200),
    sent smallint DEFAULT 0,
    status smallint DEFAULT 0,
    created_at timestamp without time zone DEFAULT now(),
    updated_at timestamp without time zone,
    port character varying(25),
    message_type character varying(50),
    channel integer DEFAULT 0,
    test integer DEFAULT 0,
    interagency_date_sent timestamp without time zone,
    interagency_recipient text,
    interagency_filename text
);


ALTER TABLE system_original_messages OWNER TO droolsuser;

--
-- TOC entry 288 (class 1259 OID 402354)
-- Name: system_original_messages_id_seq; Type: SEQUENCE; Schema: elr; Owner: droolsuser
--

CREATE SEQUENCE system_original_messages_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER TABLE system_original_messages_id_seq OWNER TO droolsuser;

--
-- TOC entry 4914 (class 0 OID 0)
-- Dependencies: 288
-- Name: system_original_messages_id_seq; Type: SEQUENCE OWNED BY; Schema: elr; Owner: droolsuser
--

ALTER SEQUENCE system_original_messages_id_seq OWNED BY system_original_messages.id;


CREATE TABLE batch_notifications (
    id                serial                      NOT NULL,
    date_created      timestamp without time zone NOT NULL DEFAULT now(),
    notification_type integer,
    event_id          integer                     NOT NULL,
    record_number     character varying(25)       NOT NULL,
    notify_state      boolean                     NOT NULL DEFAULT FALSE,
    notify_lhd        boolean                     NOT NULL DEFAULT FALSE,
    jurisdiction_id   integer,
    condition         character varying(255)      NOT NULL,
    organism          character varying,
    test_type         character varying(255)      NOT NULL,
    date_sent_state   timestamp without time zone,
    date_sent_lhd     timestamp without time zone,
    event_type        character varying(25),
    investigator      character varying(50),
    custom            boolean                     NOT NULL DEFAULT FALSE,
    system_message_id integer,
    test_result       character varying(50),
    CONSTRAINT batch_notifications_pkey PRIMARY KEY (id),
    CONSTRAINT batch_notifications_notification_type_fkey FOREIGN KEY (notification_type)
        REFERENCES batch_notification_types (id) MATCH SIMPLE
        ON UPDATE NO ACTION ON DELETE NO ACTION,
    CONSTRAINT batch_notifications_system_message_id_fkey FOREIGN KEY (system_message_id)
        REFERENCES system_messages (id) MATCH SIMPLE
        ON UPDATE NO ACTION ON DELETE CASCADE
)
WITH (
    OIDS=FALSE
);
ALTER TABLE batch_notifications
    OWNER TO droolsuser;
GRANT ALL ON TABLE batch_notifications TO droolsuser;
GRANT SELECT, UPDATE, INSERT, DELETE ON TABLE batch_notifications TO "elr-rw";
GRANT SELECT ON TABLE batch_notifications TO "elr-ro";

CREATE INDEX batch_notifications_system_message_id_idx ON batch_notifications USING btree (system_message_id);


CREATE TABLE bn_expression_chain (
    id               serial  NOT NULL,
    rule_id          integer NOT NULL,
    parent_chain_id  integer NOT NULL DEFAULT 0,
    left_id          integer NOT NULL DEFAULT 0,
    left_operator_id integer NOT NULL,
    link_type        integer NOT NULL,
    link_id          integer,
    CONSTRAINT bn_expression_chain_pkey PRIMARY KEY (id)
)
WITH (
    OIDS=FALSE
);
ALTER TABLE bn_expression_chain
    OWNER TO droolsuser;
GRANT ALL ON TABLE bn_expression_chain TO droolsuser;
GRANT SELECT, UPDATE, INSERT, DELETE ON TABLE bn_expression_chain TO "elr-rw";
GRANT SELECT ON TABLE bn_expression_chain TO "elr-ro";


CREATE TABLE bn_expression_link (
    id            serial  NOT NULL,
    type_left     integer NOT NULL,
    type_right    integer NOT NULL,
    operand_left  character varying,
    operand_right character varying,
    operator_id   integer NOT NULL,
    CONSTRAINT bn_expression_link_pkey PRIMARY KEY (id)
)
WITH (
    OIDS=FALSE
);
ALTER TABLE bn_expression_link
    OWNER TO droolsuser;
GRANT ALL ON TABLE bn_expression_link TO droolsuser;
GRANT SELECT, UPDATE, INSERT, DELETE ON TABLE bn_expression_link TO "elr-rw";
GRANT SELECT ON TABLE bn_expression_link TO "elr-ro";


CREATE TABLE bn_rule_parameters (
    id      serial NOT NULL,
    varname character varying(100),
    label   character varying(250),
    CONSTRAINT bn_rule_parameters_pkey PRIMARY KEY (id)
)
WITH (
    OIDS=FALSE
);
ALTER TABLE bn_rule_parameters
    OWNER TO droolsuser;
GRANT ALL ON TABLE bn_rule_parameters TO droolsuser;
GRANT SELECT, UPDATE, INSERT, DELETE ON TABLE bn_rule_parameters TO "elr-rw";
GRANT SELECT ON TABLE bn_rule_parameters TO "elr-ro";


CREATE TABLE bn_rules (
    id serial NOT NULL,
    name character varying,
    send_to_state boolean NOT NULL DEFAULT false,
    send_to_lhd boolean NOT NULL DEFAULT false,
    notification_type integer NOT NULL,
    enabled boolean NOT NULL DEFAULT false,
    CONSTRAINT bn_rules_pkey PRIMARY KEY (id)
)
WITH (
    OIDS=FALSE
);
ALTER TABLE bn_rules
    OWNER TO droolsuser;
GRANT ALL ON TABLE bn_rules TO droolsuser;
GRANT SELECT, UPDATE, INSERT, DELETE ON TABLE bn_rules TO "elr-rw";
GRANT SELECT ON TABLE bn_rules TO "elr-ro";


--
-- TOC entry 509 (class 1259 OID 25829649)
-- Name: system_small_areas; Type: TABLE; Schema: elr; Owner: droolsuser; Tablespace: 
--

CREATE TABLE system_small_areas (
    id numeric NOT NULL,
    area_name character varying
);


ALTER TABLE system_small_areas OWNER TO droolsuser;

--
-- TOC entry 289 (class 1259 OID 402384)
-- Name: system_statuses; Type: TABLE; Schema: elr; Owner: droolsuser; Tablespace: 
--

CREATE TABLE system_statuses (
    id integer NOT NULL,
    name character varying(150) NOT NULL,
    parent_id integer,
    sort smallint DEFAULT 0,
    type smallint DEFAULT 0
);


ALTER TABLE system_statuses OWNER TO droolsuser;

--
-- TOC entry 290 (class 1259 OID 402389)
-- Name: system_statuses_id_seq; Type: SEQUENCE; Schema: elr; Owner: droolsuser
--

CREATE SEQUENCE system_statuses_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER TABLE system_statuses_id_seq OWNER TO droolsuser;

--
-- TOC entry 4918 (class 0 OID 0)
-- Dependencies: 290
-- Name: system_statuses_id_seq; Type: SEQUENCE OWNED BY; Schema: elr; Owner: droolsuser
--

ALTER SEQUENCE system_statuses_id_seq OWNED BY system_statuses.id;


--
-- TOC entry 291 (class 1259 OID 402391)
-- Name: system_zip_codes; Type: TABLE; Schema: elr; Owner: droolsuser; Tablespace: 
--

CREATE TABLE system_zip_codes (
    id integer NOT NULL,
    zipcode character varying(20),
    system_district_id integer
);


ALTER TABLE system_zip_codes OWNER TO droolsuser;

--
-- TOC entry 292 (class 1259 OID 402394)
-- Name: system_zip_codes_id_seq; Type: SEQUENCE; Schema: elr; Owner: droolsuser
--

CREATE SEQUENCE system_zip_codes_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER TABLE system_zip_codes_id_seq OWNER TO droolsuser;

--
-- TOC entry 4921 (class 0 OID 0)
-- Dependencies: 292
-- Name: system_zip_codes_id_seq; Type: SEQUENCE OWNED BY; Schema: elr; Owner: droolsuser
--

ALTER SEQUENCE system_zip_codes_id_seq OWNED BY system_zip_codes.id;


--
-- TOC entry 511 (class 1259 OID 25830115)
-- Name: system_zip_to_small_area; Type: TABLE; Schema: elr; Owner: droolsuser; Tablespace: 
--

CREATE TABLE system_zip_to_small_area (
    id integer NOT NULL,
    small_area_id numeric NOT NULL,
    zipcode character varying(5)
);


ALTER TABLE system_zip_to_small_area OWNER TO droolsuser;

--
-- TOC entry 510 (class 1259 OID 25830113)
-- Name: system_zip_to_small_area_id_seq; Type: SEQUENCE; Schema: elr; Owner: droolsuser
--

CREATE SEQUENCE system_zip_to_small_area_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER TABLE system_zip_to_small_area_id_seq OWNER TO droolsuser;

--
-- TOC entry 4924 (class 0 OID 0)
-- Dependencies: 510
-- Name: system_zip_to_small_area_id_seq; Type: SEQUENCE OWNED BY; Schema: elr; Owner: droolsuser
--

ALTER SEQUENCE system_zip_to_small_area_id_seq OWNED BY system_zip_to_small_area.id;


--
-- TOC entry 293 (class 1259 OID 402396)
-- Name: vocab_app; Type: TABLE; Schema: elr; Owner: droolsuser; Tablespace: 
--

CREATE TABLE vocab_app (
    id serial NOT NULL,
    app_name character varying,
    enabled boolean NOT NULL DEFAULT false,
    trigger_notifications boolean NOT NULL DEFAULT false
);


ALTER TABLE vocab_app OWNER TO droolsuser;


--
-- TOC entry 4512 (class 2606 OID 16998)
-- Name: vocab_app_pkey; Type: CONSTRAINT; Schema: elr; Owner: droolsuser; Tablespace: 
--

ALTER TABLE ONLY vocab_app
    ADD CONSTRAINT vocab_app_pkey PRIMARY KEY (id);



CREATE TABLE app_client_hosts
(
    id        serial NOT NULL,
    app_id    integer,
    host_addr character varying,
    CONSTRAINT app_client_hosts_pkey PRIMARY KEY (id),
    CONSTRAINT app_client_hosts_app_id_fkey FOREIGN KEY (app_id)
        REFERENCES vocab_app (id) MATCH SIMPLE
        ON UPDATE NO ACTION ON DELETE CASCADE
)
WITH (
    OIDS=FALSE
);
ALTER TABLE app_client_hosts
    OWNER TO droolsuser;
GRANT ALL ON TABLE app_client_hosts TO droolsuser;
GRANT SELECT, UPDATE, INSERT, DELETE ON TABLE app_client_hosts TO "elr-rw";
GRANT SELECT ON TABLE app_client_hosts TO "elr-ro";


CREATE TABLE app_jurisdictions
(
    id                  serial  NOT NULL,
    system_district_id  integer NOT NULL,
    app_id              integer NOT NULL,
    app_jurisdiction_id integer NOT NULL,
    CONSTRAINT app_jurisdictions_pkey PRIMARY KEY (id),
    CONSTRAINT app_jurisdictions_app_id_fkey FOREIGN KEY (app_id)
        REFERENCES vocab_app (id) MATCH SIMPLE
        ON UPDATE CASCADE ON DELETE CASCADE,
    CONSTRAINT app_jurisdictions_system_district_id_fkey FOREIGN KEY (system_district_id)
        REFERENCES system_districts (id) MATCH SIMPLE
        ON UPDATE CASCADE ON DELETE CASCADE
)
WITH (
    OIDS=FALSE
);
ALTER TABLE app_jurisdictions
    OWNER TO droolsuser;
GRANT ALL ON TABLE app_jurisdictions TO droolsuser;
GRANT SELECT, UPDATE, INSERT, DELETE ON TABLE app_jurisdictions TO "elr-rw";
GRANT SELECT ON TABLE app_jurisdictions TO "elr-ro";


CREATE TABLE auth_app_roles
(
    id           serial  NOT NULL,
    auth_role_id integer NOT NULL,
    app_id       integer NOT NULL,
    app_role_id  integer NOT NULL,
    CONSTRAINT auth_app_roles_pkey PRIMARY KEY (id),
    CONSTRAINT auth_app_roles_app_id_fkey FOREIGN KEY (app_id)
        REFERENCES vocab_app (id) MATCH SIMPLE
        ON UPDATE CASCADE ON DELETE CASCADE,
    CONSTRAINT auth_app_roles_auth_role_id_fkey FOREIGN KEY (auth_role_id)
        REFERENCES auth_roles (id) MATCH SIMPLE
        ON UPDATE CASCADE ON DELETE CASCADE
)
WITH (
    OIDS=FALSE
);
ALTER TABLE auth_app_roles
    OWNER TO droolsuser;
GRANT ALL ON TABLE auth_app_roles TO droolsuser;
GRANT SELECT, UPDATE, INSERT, DELETE ON TABLE auth_app_roles TO "elr-rw";
GRANT SELECT ON TABLE auth_app_roles TO "elr-ro";


--
-- TOC entry 295 (class 1259 OID 402404)
-- Name: vocab_audits; Type: TABLE; Schema: elr; Owner: droolsuser; Tablespace: 
--

CREATE TABLE vocab_audits (
    id integer NOT NULL,
    vocab_id integer NOT NULL,
    tbl integer NOT NULL,
    user_id character varying(100) NOT NULL,
    event_time timestamp without time zone DEFAULT now() NOT NULL,
    action integer NOT NULL,
    old_vals character varying,
    new_vals character varying
);


ALTER TABLE vocab_audits OWNER TO droolsuser;

--
-- TOC entry 296 (class 1259 OID 402411)
-- Name: vocab_audits_id_seq; Type: SEQUENCE; Schema: elr; Owner: droolsuser
--

CREATE SEQUENCE vocab_audits_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER TABLE vocab_audits_id_seq OWNER TO droolsuser;

--
-- TOC entry 4929 (class 0 OID 0)
-- Dependencies: 296
-- Name: vocab_audits_id_seq; Type: SEQUENCE OWNED BY; Schema: elr; Owner: droolsuser
--

ALTER SEQUENCE vocab_audits_id_seq OWNED BY vocab_audits.id;


--
-- TOC entry 297 (class 1259 OID 402413)
-- Name: vocab_c2m_testresult; Type: TABLE; Schema: elr; Owner: droolsuser; Tablespace: 
--

CREATE TABLE vocab_c2m_testresult (
    id integer NOT NULL,
    child_loinc_id integer NOT NULL,
    master_id integer NOT NULL,
    app_id integer DEFAULT 1 NOT NULL,
    results_to_comments character varying,
    conditions_structured character varying,
    conditions_js character varying
);


ALTER TABLE vocab_c2m_testresult OWNER TO droolsuser;

--
-- TOC entry 4931 (class 0 OID 0)
-- Dependencies: 297
-- Name: TABLE vocab_c2m_testresult; Type: COMMENT; Schema: elr; Owner: droolsuser
--

COMMENT ON TABLE vocab_c2m_testresult IS 'Defines interpretive rules for converting child-specific test result values to master test result values for codification to app-specific codes.';


--
-- TOC entry 298 (class 1259 OID 402420)
-- Name: vocab_c2m_testresult_id_seq; Type: SEQUENCE; Schema: elr; Owner: droolsuser
--

CREATE SEQUENCE vocab_c2m_testresult_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER TABLE vocab_c2m_testresult_id_seq OWNER TO droolsuser;

--
-- TOC entry 4933 (class 0 OID 0)
-- Dependencies: 298
-- Name: vocab_c2m_testresult_id_seq; Type: SEQUENCE OWNED BY; Schema: elr; Owner: droolsuser
--

ALTER SEQUENCE vocab_c2m_testresult_id_seq OWNED BY vocab_c2m_testresult.id;


--
-- TOC entry 584 (class 1259 OID 27995417)
-- Name: vocab_child_codeset; Type: TABLE; Schema: elr; Owner: droolsuser; Tablespace: 
--

CREATE TABLE vocab_child_codeset (
    id integer NOT NULL,
    structure_labs_id integer NOT NULL,
    master_codeset_id integer NOT NULL,
    child_codeset_value character varying NOT NULL
);


ALTER TABLE vocab_child_codeset OWNER TO droolsuser;

--
-- TOC entry 583 (class 1259 OID 27995415)
-- Name: vocab_child_codeset_id_seq; Type: SEQUENCE; Schema: elr; Owner: droolsuser
--

CREATE SEQUENCE vocab_child_codeset_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER TABLE vocab_child_codeset_id_seq OWNER TO droolsuser;

--
-- TOC entry 4936 (class 0 OID 0)
-- Dependencies: 583
-- Name: vocab_child_codeset_id_seq; Type: SEQUENCE OWNED BY; Schema: elr; Owner: droolsuser
--

ALTER SEQUENCE vocab_child_codeset_id_seq OWNED BY vocab_child_codeset.id;


--
-- TOC entry 299 (class 1259 OID 402422)
-- Name: vocab_child_loinc; Type: TABLE; Schema: elr; Owner: droolsuser; Tablespace: 
--

CREATE TABLE vocab_child_loinc (
    id                         integer               NOT NULL,
    child_loinc                character varying,
    child_orderable_test_code  character varying,
    child_resultable_test_code character varying,
    child_concept_name         character varying,
    child_alias                character varying,
    master_loinc               integer DEFAULT (-1)  NOT NULL,
    lab_id                     integer,
    units                      character varying,
    refrange                   character varying,
    result_location            integer DEFAULT (-1)  NOT NULL,
    interpret_results          boolean DEFAULT true  NOT NULL,
    hl7_refrange               character varying,
    pregnancy                  boolean DEFAULT false NOT NULL,
    last_updated               timestamp without time zone,
    archived                   boolean DEFAULT false NOT NULL,
    workflow                   integer DEFAULT 17    NOT NULL,
    admin_notes                character varying,
    allow_preprocessing        boolean               NOT NULL DEFAULT FALSE,
    offscale_low_result        integer,
    offscale_high_result       integer,
    interpret_override         boolean
);

COMMENT ON COLUMN vocab_child_loinc.interpret_override IS 'Set whether Child LOINC overrides OBX.2-based Qn interpretation rules.  If NULL, use rules; if TRUE/FALSE, override OBX.2 rule with Qn/Lookup, respectively.';


ALTER TABLE vocab_child_loinc OWNER TO droolsuser;

--
-- TOC entry 300 (class 1259 OID 402436)
-- Name: vocab_child_loinc_id_seq; Type: SEQUENCE; Schema: elr; Owner: droolsuser
--

CREATE SEQUENCE vocab_child_loinc_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER TABLE vocab_child_loinc_id_seq OWNER TO droolsuser;

--
-- TOC entry 4938 (class 0 OID 0)
-- Dependencies: 300
-- Name: vocab_child_loinc_id_seq; Type: SEQUENCE OWNED BY; Schema: elr; Owner: droolsuser
--

ALTER SEQUENCE vocab_child_loinc_id_seq OWNED BY vocab_child_loinc.id;



-- Index: vocab_child_loinc_archived_idx

-- DROP INDEX vocab_child_loinc_archived_idx;

CREATE INDEX vocab_child_loinc_archived_idx
  ON vocab_child_loinc
  USING btree
  (archived);

-- Index: vocab_child_loinc_child_loinc_idx

-- DROP INDEX vocab_child_loinc_child_loinc_idx;

CREATE INDEX vocab_child_loinc_child_loinc_idx
  ON vocab_child_loinc
  USING btree
  (child_loinc COLLATE pg_catalog."default");

-- Index: vocab_child_loinc_lab_id_idx

-- DROP INDEX vocab_child_loinc_lab_id_idx;

CREATE INDEX vocab_child_loinc_lab_id_idx
  ON vocab_child_loinc
  USING btree
  (lab_id);


--
-- TOC entry 301 (class 1259 OID 402438)
-- Name: vocab_child_organism; Type: TABLE; Schema: elr; Owner: droolsuser; Tablespace: 
--

CREATE TABLE vocab_child_organism (
    id              integer              NOT NULL,
    child_code      character varying,
    app_test_result character varying,
    value           character varying,
    test_status     character varying,
    organism        integer DEFAULT (-1) NOT NULL,
    lab_id          integer,
    last_updated    timestamp without time zone,
    test_result_id  integer DEFAULT (-1) NOT NULL,
    result_value    character varying,
    comment         character varying,
    admin_notes     character varying
);


ALTER TABLE vocab_child_organism OWNER TO droolsuser;

--
-- TOC entry 302 (class 1259 OID 402447)
-- Name: vocab_child_organism_id_seq; Type: SEQUENCE; Schema: elr; Owner: droolsuser
--

CREATE SEQUENCE vocab_child_organism_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER TABLE vocab_child_organism_id_seq OWNER TO droolsuser;

--
-- TOC entry 4941 (class 0 OID 0)
-- Dependencies: 302
-- Name: vocab_child_organism_id_seq; Type: SEQUENCE OWNED BY; Schema: elr; Owner: droolsuser
--

ALTER SEQUENCE vocab_child_organism_id_seq OWNED BY vocab_child_organism.id;


--
-- TOC entry 303 (class 1259 OID 402449)
-- Name: vocab_child_vocab; Type: TABLE; Schema: elr; Owner: droolsuser; Tablespace: 
--

CREATE TABLE vocab_child_vocab (
    id integer NOT NULL,
    lab_id integer,
    master_id integer,
    concept character varying,
    comment character varying,
    last_updated timestamp without time zone
);


ALTER TABLE vocab_child_vocab OWNER TO droolsuser;

--
-- TOC entry 304 (class 1259 OID 402455)
-- Name: vocab_child_vocab_id_seq; Type: SEQUENCE; Schema: elr; Owner: droolsuser
--

CREATE SEQUENCE vocab_child_vocab_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER TABLE vocab_child_vocab_id_seq OWNER TO droolsuser;

--
-- TOC entry 4944 (class 0 OID 0)
-- Dependencies: 304
-- Name: vocab_child_vocab_id_seq; Type: SEQUENCE OWNED BY; Schema: elr; Owner: droolsuser
--

ALTER SEQUENCE vocab_child_vocab_id_seq OWNED BY vocab_child_vocab.id;


--
-- TOC entry 582 (class 1259 OID 27995406)
-- Name: vocab_codeset; Type: TABLE; Schema: elr; Owner: droolsuser; Tablespace: 
--

CREATE TABLE vocab_codeset (
    id integer NOT NULL,
    codeset_name character varying NOT NULL
);


ALTER TABLE vocab_codeset OWNER TO droolsuser;

--
-- TOC entry 581 (class 1259 OID 27995404)
-- Name: vocab_codeset_id_seq; Type: SEQUENCE; Schema: elr; Owner: droolsuser
--

CREATE SEQUENCE vocab_codeset_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER TABLE vocab_codeset_id_seq OWNER TO droolsuser;

--
-- TOC entry 4947 (class 0 OID 0)
-- Dependencies: 581
-- Name: vocab_codeset_id_seq; Type: SEQUENCE OWNED BY; Schema: elr; Owner: droolsuser
--

ALTER SEQUENCE vocab_codeset_id_seq OWNED BY vocab_codeset.id;


--
-- TOC entry 586 (class 1259 OID 27995438)
-- Name: vocab_icd; Type: TABLE; Schema: elr; Owner: droolsuser; Tablespace: 
--

CREATE TABLE vocab_icd (
    id integer NOT NULL,
    codeset_id integer NOT NULL,
    code_value character varying NOT NULL,
    code_description character varying,
    master_condition_id integer,
    master_snomed_id integer,
    pregnancy_status boolean DEFAULT false,
    pregnancy_indicator boolean NOT NULL DEFAULT false,
    allow_new_cmr boolean NOT NULL DEFAULT false,
    allow_update_cmr boolean NOT NULL DEFAULT true,
    is_surveillance boolean NOT NULL DEFAULT false
);


ALTER TABLE vocab_icd OWNER TO droolsuser;

--
-- TOC entry 585 (class 1259 OID 27995436)
-- Name: vocab_icd_id_seq; Type: SEQUENCE; Schema: elr; Owner: droolsuser
--

CREATE SEQUENCE vocab_icd_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER TABLE vocab_icd_id_seq OWNER TO droolsuser;

--
-- TOC entry 4949 (class 0 OID 0)
-- Dependencies: 585
-- Name: vocab_icd_id_seq; Type: SEQUENCE OWNED BY; Schema: elr; Owner: droolsuser
--

ALTER SEQUENCE vocab_icd_id_seq OWNED BY vocab_icd.id;


--
-- TOC entry 305 (class 1259 OID 402457)
-- Name: vocab_last_imported; Type: TABLE; Schema: elr; Owner: droolsuser; Tablespace: 
--

CREATE TABLE vocab_last_imported (
    id            integer NOT NULL,
    update_type   character varying(25),
    lab_id        integer,
    app_id        integer,
    last_imported timestamp without time zone
);


ALTER TABLE vocab_last_imported OWNER TO droolsuser;

--
-- TOC entry 306 (class 1259 OID 402460)
-- Name: vocab_last_imported_id_seq; Type: SEQUENCE; Schema: elr; Owner: droolsuser
--

CREATE SEQUENCE vocab_last_imported_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER TABLE vocab_last_imported_id_seq OWNER TO droolsuser;

--
-- TOC entry 4951 (class 0 OID 0)
-- Dependencies: 306
-- Name: vocab_last_imported_id_seq; Type: SEQUENCE OWNED BY; Schema: elr; Owner: droolsuser
--

ALTER SEQUENCE vocab_last_imported_id_seq OWNED BY vocab_last_imported.id;


--
-- TOC entry 307 (class 1259 OID 402462)
-- Name: vocab_master_condition; Type: TABLE; Schema: elr; Owner: droolsuser; Tablespace: 
--

CREATE TABLE vocab_master_condition (
    c_id                         integer NOT NULL,
    immediate_notify             boolean,
    require_specimen             boolean,
    ignore_age_rule              character varying,
    white_rule                   character varying,
    notify_state                 boolean,
    surveillance                 boolean,
    condition                    integer NOT NULL DEFAULT (-1),
    gateway_xref                 character varying,
    valid_specimen               character varying,
    contact_white_rule           character varying,
    disease_category             integer NOT NULL DEFAULT (-1),
    last_updated                 timestamp WITHOUT TIME ZONE,
    check_xref_first             boolean NOT NULL DEFAULT FALSE,
    district_override            integer NOT NULL DEFAULT (-1),
    invalid_specimen             character varying,
    is_initial                   boolean NOT NULL DEFAULT TRUE,
    whitelist_override           boolean NOT NULL DEFAULT FALSE,
    allow_multi_assign           boolean NOT NULL DEFAULT FALSE,
    ast_multi_colony             boolean NOT NULL DEFAULT FALSE,
    o2m_addcmr_exclusions        character varying,
    bypass_oos                   boolean NOT NULL DEFAULT FALSE,
    blacklist_preliminary        boolean NOT NULL DEFAULT FALSE,
    whitelist_ignore_case_status boolean NOT NULL DEFAULT FALSE
);


ALTER TABLE vocab_master_condition OWNER TO droolsuser;

--
-- TOC entry 308 (class 1259 OID 402473)
-- Name: vocab_master_condition_c_id_seq; Type: SEQUENCE; Schema: elr; Owner: droolsuser
--

CREATE SEQUENCE vocab_master_condition_c_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER TABLE vocab_master_condition_c_id_seq OWNER TO droolsuser;

--
-- TOC entry 4954 (class 0 OID 0)
-- Dependencies: 308
-- Name: vocab_master_condition_c_id_seq; Type: SEQUENCE OWNED BY; Schema: elr; Owner: droolsuser
--

ALTER SEQUENCE vocab_master_condition_c_id_seq OWNED BY vocab_master_condition.c_id;


--
-- TOC entry 309 (class 1259 OID 402475)
-- Name: vocab_master_loinc; Type: TABLE; Schema: elr; Owner: droolsuser; Tablespace: 
--

CREATE TABLE vocab_master_loinc (
    l_id                  integer               NOT NULL,
    loinc                 character varying,
    concept_name          character varying,
    check_master_org      boolean,
    trisano_condition     integer DEFAULT (-1)  NOT NULL,
    trisano_organism      integer DEFAULT (-1)  NOT NULL,
    trisano_test_type     integer DEFAULT (-1)  NOT NULL,
    specimen_source       integer DEFAULT (-1)  NOT NULL,
    list                  integer DEFAULT (-1)  NOT NULL,
    last_updated          timestamp without time zone,
    condition_from_result boolean DEFAULT false NOT NULL,
    organism_from_result  boolean DEFAULT false NOT NULL,
    antimicrobial_agent   integer               NOT NULL DEFAULT (-1),
    admin_notes           character varying
);


ALTER TABLE vocab_master_loinc OWNER TO droolsuser;

--
-- TOC entry 310 (class 1259 OID 402488)
-- Name: vocab_master_loinc_l_id_seq; Type: SEQUENCE; Schema: elr; Owner: droolsuser
--

CREATE SEQUENCE vocab_master_loinc_l_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER TABLE vocab_master_loinc_l_id_seq OWNER TO droolsuser;

--
-- TOC entry 4957 (class 0 OID 0)
-- Dependencies: 310
-- Name: vocab_master_loinc_l_id_seq; Type: SEQUENCE OWNED BY; Schema: elr; Owner: droolsuser
--

ALTER SEQUENCE vocab_master_loinc_l_id_seq OWNED BY vocab_master_loinc.l_id;


--
-- TOC entry 311 (class 1259 OID 402490)
-- Name: vocab_master_organism; Type: TABLE; Schema: elr; Owner: droolsuser; Tablespace: 
--
CREATE TABLE vocab_master_organism (
    o_id            integer              NOT NULL,
    snomed          character varying,
    condition       integer DEFAULT (-1) NOT NULL,
    organism        integer DEFAULT (-1) NOT NULL,
    list            integer,
    last_updated    timestamp without time zone,
    test_result     integer DEFAULT (-1) NOT NULL,
    snomed_alt      character varying,
    snomed_category integer DEFAULT (-1) NOT NULL,
    admin_notes     character varying,
    semi_auto_usage boolean
);

COMMENT ON COLUMN vocab_master_organism.semi_auto_usage IS 'If NULL, allow normal semi-automated entry workflow; if FALSE, skip semi-automated entry; if TRUE, require semi-automated entry';


ALTER TABLE vocab_master_organism OWNER TO droolsuser;

--
-- TOC entry 312 (class 1259 OID 402500)
-- Name: vocab_master_organism_o_id_seq; Type: SEQUENCE; Schema: elr; Owner: droolsuser
--

CREATE SEQUENCE vocab_master_organism_o_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER TABLE vocab_master_organism_o_id_seq OWNER TO droolsuser;

--
-- TOC entry 4960 (class 0 OID 0)
-- Dependencies: 312
-- Name: vocab_master_organism_o_id_seq; Type: SEQUENCE OWNED BY; Schema: elr; Owner: droolsuser
--

ALTER SEQUENCE vocab_master_organism_o_id_seq OWNED BY vocab_master_organism.o_id;


--
-- TOC entry 313 (class 1259 OID 402502)
-- Name: vocab_master_vocab; Type: TABLE; Schema: elr; Owner: droolsuser; Tablespace: 
--

CREATE TABLE vocab_master_vocab (
    id integer NOT NULL,
    codeset character varying,
    concept character varying,
    category integer,
    last_updated timestamp without time zone
);


ALTER TABLE vocab_master_vocab OWNER TO droolsuser;

-- Index: vocab_master_vocab_category_idx

-- DROP INDEX vocab_master_vocab_category_idx;

CREATE INDEX vocab_master_vocab_category_idx
  ON vocab_master_vocab
  USING btree
  (category);

--
-- TOC entry 314 (class 1259 OID 402508)
-- Name: vocab_master_vocab_id_seq; Type: SEQUENCE; Schema: elr; Owner: droolsuser
--

CREATE SEQUENCE vocab_master_vocab_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER TABLE vocab_master_vocab_id_seq OWNER TO droolsuser;

--
-- TOC entry 4963 (class 0 OID 0)
-- Dependencies: 314
-- Name: vocab_master_vocab_id_seq; Type: SEQUENCE OWNED BY; Schema: elr; Owner: droolsuser
--

ALTER SEQUENCE vocab_master_vocab_id_seq OWNED BY vocab_master_vocab.id;


--
-- TOC entry 579 (class 1259 OID 27867168)
-- Name: vocab_pfge; Type: TABLE; Schema: elr; Owner: droolsuser; Tablespace: 
--

CREATE TABLE vocab_pfge (
    id integer NOT NULL,
    pattern character varying(3),
    master_snomed_id integer
);


ALTER TABLE vocab_pfge OWNER TO droolsuser;

--
-- TOC entry 578 (class 1259 OID 27867166)
-- Name: vocab_pfge_id_seq; Type: SEQUENCE; Schema: elr; Owner: droolsuser
--

CREATE SEQUENCE vocab_pfge_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER TABLE vocab_pfge_id_seq OWNER TO droolsuser;

--
-- TOC entry 4966 (class 0 OID 0)
-- Dependencies: 578
-- Name: vocab_pfge_id_seq; Type: SEQUENCE OWNED BY; Schema: elr; Owner: droolsuser
--

ALTER SEQUENCE vocab_pfge_id_seq OWNED BY vocab_pfge.id;


--
-- TOC entry 315 (class 1259 OID 402510)
-- Name: vocab_rules_graylist; Type: TABLE; Schema: elr; Owner: droolsuser; Tablespace: 
--

CREATE TABLE vocab_rules_graylist (
    id integer NOT NULL,
    app_id integer DEFAULT 1 NOT NULL,
    master_condition_id integer NOT NULL,
    conditions_structured character varying
);


ALTER TABLE vocab_rules_graylist OWNER TO droolsuser;

--
-- TOC entry 316 (class 1259 OID 402517)
-- Name: vocab_rules_graylist_id_seq; Type: SEQUENCE; Schema: elr; Owner: droolsuser
--

CREATE SEQUENCE vocab_rules_graylist_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER TABLE vocab_rules_graylist_id_seq OWNER TO droolsuser;

--
-- TOC entry 4968 (class 0 OID 0)
-- Dependencies: 316
-- Name: vocab_rules_graylist_id_seq; Type: SEQUENCE OWNED BY; Schema: elr; Owner: droolsuser
--

ALTER SEQUENCE vocab_rules_graylist_id_seq OWNED BY vocab_rules_graylist.id;


--
-- TOC entry 317 (class 1259 OID 402519)
-- Name: vocab_rules_masterloinc; Type: TABLE; Schema: elr; Owner: droolsuser; Tablespace: 
--

CREATE TABLE vocab_rules_masterloinc (
    id integer NOT NULL,
    app_id integer DEFAULT 1 NOT NULL,
    master_loinc_id integer NOT NULL,
    conditions_structured character varying,
    conditions_js character varying,
    allow_new_cmr boolean DEFAULT true NOT NULL,
    state_case_status_master_id integer NOT NULL,
    is_surveillance boolean DEFAULT false NOT NULL,
    allow_update_cmr boolean DEFAULT true NOT NULL
);


ALTER TABLE vocab_rules_masterloinc OWNER TO droolsuser;

--
-- TOC entry 318 (class 1259 OID 402529)
-- Name: vocab_rules_masterloinc_id_seq; Type: SEQUENCE; Schema: elr; Owner: droolsuser
--

CREATE SEQUENCE vocab_rules_masterloinc_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER TABLE vocab_rules_masterloinc_id_seq OWNER TO droolsuser;

--
-- TOC entry 4971 (class 0 OID 0)
-- Dependencies: 318
-- Name: vocab_rules_masterloinc_id_seq; Type: SEQUENCE OWNED BY; Schema: elr; Owner: droolsuser
--

ALTER SEQUENCE vocab_rules_masterloinc_id_seq OWNED BY vocab_rules_masterloinc.id;


--
-- TOC entry 319 (class 1259 OID 402531)
-- Name: vocab_rules_mastersnomed; Type: TABLE; Schema: elr; Owner: droolsuser; Tablespace: 
--

CREATE TABLE vocab_rules_mastersnomed (
    id integer NOT NULL,
    app_id integer DEFAULT 1 NOT NULL,
    master_snomed_id integer NOT NULL,
    conditions_structured character varying,
    conditions_js character varying,
    allow_new_cmr boolean DEFAULT true NOT NULL,
    state_case_status_master_id integer NOT NULL,
    is_surveillance boolean DEFAULT false NOT NULL,
    allow_update_cmr boolean DEFAULT true NOT NULL
);


ALTER TABLE vocab_rules_mastersnomed OWNER TO droolsuser;

--
-- TOC entry 320 (class 1259 OID 402541)
-- Name: vocab_rules_mastersnomed_id_seq; Type: SEQUENCE; Schema: elr; Owner: droolsuser
--

CREATE SEQUENCE vocab_rules_mastersnomed_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER TABLE vocab_rules_mastersnomed_id_seq OWNER TO droolsuser;

--
-- TOC entry 4974 (class 0 OID 0)
-- Dependencies: 320
-- Name: vocab_rules_mastersnomed_id_seq; Type: SEQUENCE OWNED BY; Schema: elr; Owner: droolsuser
--

ALTER SEQUENCE vocab_rules_mastersnomed_id_seq OWNED BY vocab_rules_mastersnomed.id;


-- Index: vocab_rules_mastersnomed_app_id_idx

-- DROP INDEX vocab_rules_mastersnomed_app_id_idx;

CREATE INDEX vocab_rules_mastersnomed_app_id_idx
  ON vocab_rules_mastersnomed
  USING btree
  (app_id);

-- Index: vocab_rules_mastersnomed_master_snomed_id_idx

-- DROP INDEX vocab_rules_mastersnomed_master_snomed_id_idx;

CREATE INDEX vocab_rules_mastersnomed_master_snomed_id_idx
  ON vocab_rules_mastersnomed
  USING btree
  (master_snomed_id);



-- Table: pending_watch_list

-- DROP TABLE pending_watch_list;

CREATE TABLE pending_watch_list
(
    id    serial NOT NULL,
    lname text   NOT NULL,
    CONSTRAINT pending_watch_list_pkey PRIMARY KEY (id)
)
WITH (
OIDS = FALSE
);
ALTER TABLE pending_watch_list
    OWNER TO droolsuser;
GRANT ALL ON TABLE pending_watch_list TO droolsuser;
GRANT SELECT, UPDATE, INSERT, DELETE ON TABLE pending_watch_list TO "elr-rw";
GRANT SELECT ON TABLE pending_watch_list TO "elr-ro";



--
-- Name: nickname; Type: TABLE; Schema: elr; Owner: droolsuser
--

CREATE TABLE nickname (
    name character varying NOT NULL,
    nickname character varying NOT NULL
);
ALTER TABLE nickname OWNER TO droolsuser;
ALTER TABLE ONLY nickname
    ADD CONSTRAINT nickname_pkey PRIMARY KEY (name, nickname);
GRANT ALL ON TABLE nickname TO dcpadmin;
GRANT ALL ON TABLE nickname TO "elr-rw";




--
-- TOC entry 4177 (class 2604 OID 16796)
-- Name: id; Type: DEFAULT; Schema: elr; Owner: droolsuser
--

ALTER TABLE ONLY gateway_channels ALTER COLUMN id SET DEFAULT nextval('gateway_channels_id_seq'::regclass);


--
-- TOC entry 4179 (class 2604 OID 16797)
-- Name: id; Type: DEFAULT; Schema: elr; Owner: droolsuser
--

ALTER TABLE ONLY gateway_exceptions ALTER COLUMN id SET DEFAULT nextval('gateway_exceptions_id_seq'::regclass);


--
-- TOC entry 4181 (class 2604 OID 16798)
-- Name: id; Type: DEFAULT; Schema: elr; Owner: droolsuser
--

ALTER TABLE ONLY gateway_requests ALTER COLUMN id SET DEFAULT nextval('gateway_requests_id_seq'::regclass);


--
-- TOC entry 4182 (class 2604 OID 16799)
-- Name: id; Type: DEFAULT; Schema: elr; Owner: droolsuser
--

ALTER TABLE ONLY gateway_users ALTER COLUMN id SET DEFAULT nextval('gateway_users_id_seq'::regclass);


--
-- TOC entry 4188 (class 2604 OID 16800)
-- Name: id; Type: DEFAULT; Schema: elr; Owner: droolsuser
--

ALTER TABLE ONLY gatewaytest_original_messages ALTER COLUMN id SET DEFAULT nextval('gatewaytest_original_messages_id_seq'::regclass);


--
-- TOC entry 4190 (class 2604 OID 16801)
-- Name: id; Type: DEFAULT; Schema: elr; Owner: droolsuser
--

ALTER TABLE ONLY graylist_request_audits ALTER COLUMN id SET DEFAULT nextval('graylist_request_audits_id_seq'::regclass);


--
-- TOC entry 4192 (class 2604 OID 16802)
-- Name: id; Type: DEFAULT; Schema: elr; Owner: droolsuser
--

ALTER TABLE ONLY graylist_requests ALTER COLUMN id SET DEFAULT nextval('graylist_requests_id_seq'::regclass);


--
-- TOC entry 4195 (class 2604 OID 16803)
-- Name: id; Type: DEFAULT; Schema: elr; Owner: droolsuser
--

ALTER TABLE ONLY graylist_spool ALTER COLUMN id SET DEFAULT nextval('graylist_spool_id_seq'::regclass);


--
-- TOC entry 4196 (class 2604 OID 16804)
-- Name: id; Type: DEFAULT; Schema: elr; Owner: droolsuser
--

ALTER TABLE ONLY intake_stats_config ALTER COLUMN id SET DEFAULT nextval('intake_stats_config_id_seq'::regclass);


--
-- TOC entry 4331 (class 2604 OID 16805)
-- Name: id; Type: DEFAULT; Schema: elr; Owner: dcpadmin
--

ALTER TABLE ONLY master_xml_flat ALTER COLUMN id SET DEFAULT nextval('master_xml_flat_id_seq'::regclass);


--
-- TOC entry 4352 (class 2604 OID 28514233)
-- Name: id; Type: DEFAULT; Schema: elr; Owner: droolsuser
--

ALTER TABLE ONLY preprocessor_audit_exceptions ALTER COLUMN id SET DEFAULT nextval('preprocessor_audit_exceptions_id_seq'::regclass);


--
-- TOC entry 4350 (class 2604 OID 28514216)
-- Name: id; Type: DEFAULT; Schema: elr; Owner: droolsuser
--

ALTER TABLE ONLY preprocessor_exceptions ALTER COLUMN id SET DEFAULT nextval('preprocessor_exceptions_id_seq'::regclass);


--
-- TOC entry 4338 (class 2604 OID 27833339)
-- Name: id; Type: DEFAULT; Schema: elr; Owner: droolsuser
--

ALTER TABLE ONLY ss_batched_messages ALTER COLUMN id SET DEFAULT nextval('ss_batched_messages_id_seq'::regclass);


--
-- TOC entry 4200 (class 2604 OID 16806)
-- Name: id; Type: DEFAULT; Schema: elr; Owner: droolsuser
--

ALTER TABLE ONLY ss_biosense ALTER COLUMN id SET DEFAULT nextval('ss_biosense_id_seq'::regclass);


--
-- TOC entry 4337 (class 2604 OID 27404724)
-- Name: id; Type: DEFAULT; Schema: elr; Owner: droolsuser
--

ALTER TABLE ONLY ss_connectors ALTER COLUMN id SET DEFAULT nextval('ss_connectors_id_seq'::regclass);


--
-- TOC entry 4202 (class 2604 OID 16807)
-- Name: id; Type: DEFAULT; Schema: elr; Owner: droolsuser
--

ALTER TABLE ONLY ss_facility ALTER COLUMN id SET DEFAULT nextval('ss_facility_id_seq'::regclass);


--
-- TOC entry 4203 (class 2604 OID 16809)
-- Name: id; Type: DEFAULT; Schema: elr; Owner: droolsuser
--

ALTER TABLE ONLY structure_category ALTER COLUMN id SET DEFAULT nextval('structure_category_id_seq'::regclass);


--
-- TOC entry 4205 (class 2604 OID 16810)
-- Name: id; Type: DEFAULT; Schema: elr; Owner: droolsuser
--

ALTER TABLE ONLY structure_category_application ALTER COLUMN id SET DEFAULT nextval('structure_category_application_id_seq'::regclass);


--
-- TOC entry 4325 (class 2604 OID 16811)
-- Name: id; Type: DEFAULT; Schema: elr; Owner: droolsuser
--

ALTER TABLE ONLY structure_knittable_loincs ALTER COLUMN id SET DEFAULT nextval('structure_knittable_loincs_id_seq'::regclass);


--
-- TOC entry 4209 (class 2604 OID 16813)
-- Name: id; Type: DEFAULT; Schema: elr; Owner: droolsuser
--

ALTER TABLE ONLY structure_lookup_operator ALTER COLUMN id SET DEFAULT nextval('structure_lookup_operator_id_seq'::regclass);


--
-- TOC entry 4215 (class 2604 OID 16814)
-- Name: id; Type: DEFAULT; Schema: elr; Owner: droolsuser
--

ALTER TABLE ONLY structure_path_application ALTER COLUMN id SET DEFAULT nextval('structure_path_application_id_seq'::regclass);


--
-- TOC entry 4221 (class 2604 OID 16816)
-- Name: id; Type: DEFAULT; Schema: elr; Owner: droolsuser
--

ALTER TABLE ONLY structure_path_mirth ALTER COLUMN id SET DEFAULT nextval('structure_path_mirth_id_seq'::regclass);


--
-- TOC entry 4224 (class 2604 OID 16817)
-- Name: id; Type: DEFAULT; Schema: elr; Owner: droolsuser
--

ALTER TABLE ONLY system_action_categories ALTER COLUMN id SET DEFAULT nextval('system_action_categories_id_seq'::regclass);


--
-- TOC entry 4332 (class 2604 OID 16818)
-- Name: id; Type: DEFAULT; Schema: elr; Owner: droolsuser
--

ALTER TABLE ONLY system_alert_types ALTER COLUMN id SET DEFAULT nextval('system_alert_types_id_seq'::regclass);


--
-- TOC entry 4335 (class 2604 OID 16819)
-- Name: id; Type: DEFAULT; Schema: elr; Owner: droolsuser
--

ALTER TABLE ONLY system_alerts ALTER COLUMN id SET DEFAULT nextval('system_alerts_id_seq'::regclass);


--
-- TOC entry 4225 (class 2604 OID 16820)
-- Name: id; Type: DEFAULT; Schema: elr; Owner: droolsuser
--

ALTER TABLE ONLY system_audit_exceptions ALTER COLUMN id SET DEFAULT nextval('system_audit_exceptions_id_seq'::regclass);


--
-- TOC entry 4231 (class 2604 OID 16822)
-- Name: id; Type: DEFAULT; Schema: elr; Owner: droolsuser
--

ALTER TABLE ONLY system_exceptions ALTER COLUMN id SET DEFAULT nextval('system_exceptions_id_seq'::regclass);


--
-- TOC entry 4233 (class 2604 OID 16823)
-- Name: id; Type: DEFAULT; Schema: elr; Owner: droolsuser
--

ALTER TABLE ONLY system_menus ALTER COLUMN id SET DEFAULT nextval('system_menus_id_seq'::regclass);


--
-- TOC entry 4235 (class 2604 OID 16824)
-- Name: id; Type: DEFAULT; Schema: elr; Owner: droolsuser
--

ALTER TABLE ONLY system_message_actions ALTER COLUMN id SET DEFAULT nextval('system_message_actions_id_seq'::regclass);


--
-- TOC entry 4237 (class 2604 OID 16825)
-- Name: id; Type: DEFAULT; Schema: elr; Owner: droolsuser
--

ALTER TABLE ONLY system_message_comments ALTER COLUMN id SET DEFAULT nextval('system_message_comments_id_seq'::regclass);


--
-- TOC entry 4238 (class 2604 OID 16826)
-- Name: id; Type: DEFAULT; Schema: elr; Owner: droolsuser
--

ALTER TABLE ONLY system_message_exceptions ALTER COLUMN id SET DEFAULT nextval('system_message_exceptions_id_seq'::regclass);


--
-- TOC entry 4239 (class 2604 OID 16827)
-- Name: id; Type: DEFAULT; Schema: elr; Owner: droolsuser
--

ALTER TABLE ONLY system_message_flag_comments ALTER COLUMN id SET DEFAULT nextval('system_message_flag_comments_id_seq'::regclass);


--
-- TOC entry 4240 (class 2604 OID 16828)
-- Name: id; Type: DEFAULT; Schema: elr; Owner: droolsuser
--

ALTER TABLE ONLY system_message_flags ALTER COLUMN id SET DEFAULT nextval('system_message_flags_id_seq'::regclass);


--
-- TOC entry 4259 (class 2604 OID 16830)
-- Name: id; Type: DEFAULT; Schema: elr; Owner: droolsuser
--

ALTER TABLE ONLY system_messages_audits ALTER COLUMN id SET DEFAULT nextval('system_messages_audits_id_seq'::regclass);


--
-- TOC entry 4262 (class 2604 OID 16831)
-- Name: id; Type: DEFAULT; Schema: elr; Owner: droolsuser
--

ALTER TABLE ONLY system_nedss_xml_audits ALTER COLUMN id SET DEFAULT nextval('system_nedss_xml_audits_id_seq'::regclass);


--
-- TOC entry 4268 (class 2604 OID 16832)
-- Name: id; Type: DEFAULT; Schema: elr; Owner: droolsuser
--

ALTER TABLE ONLY system_original_messages ALTER COLUMN id SET DEFAULT nextval('system_original_messages_id_seq'::regclass);


--
-- TOC entry 4271 (class 2604 OID 16833)
-- Name: id; Type: DEFAULT; Schema: elr; Owner: droolsuser
--

ALTER TABLE ONLY system_statuses ALTER COLUMN id SET DEFAULT nextval('system_statuses_id_seq'::regclass);


--
-- TOC entry 4272 (class 2604 OID 16834)
-- Name: id; Type: DEFAULT; Schema: elr; Owner: droolsuser
--

ALTER TABLE ONLY system_zip_codes ALTER COLUMN id SET DEFAULT nextval('system_zip_codes_id_seq'::regclass);


--
-- TOC entry 4336 (class 2604 OID 16835)
-- Name: id; Type: DEFAULT; Schema: elr; Owner: droolsuser
--

ALTER TABLE ONLY system_zip_to_small_area ALTER COLUMN id SET DEFAULT nextval('system_zip_to_small_area_id_seq'::regclass);


--
-- TOC entry 4275 (class 2604 OID 16838)
-- Name: id; Type: DEFAULT; Schema: elr; Owner: droolsuser
--

ALTER TABLE ONLY vocab_audits ALTER COLUMN id SET DEFAULT nextval('vocab_audits_id_seq'::regclass);


--
-- TOC entry 4277 (class 2604 OID 16839)
-- Name: id; Type: DEFAULT; Schema: elr; Owner: droolsuser
--

ALTER TABLE ONLY vocab_c2m_testresult ALTER COLUMN id SET DEFAULT nextval('vocab_c2m_testresult_id_seq'::regclass);


--
-- TOC entry 4346 (class 2604 OID 27995420)
-- Name: id; Type: DEFAULT; Schema: elr; Owner: droolsuser
--

ALTER TABLE ONLY vocab_child_codeset ALTER COLUMN id SET DEFAULT nextval('vocab_child_codeset_id_seq'::regclass);


--
-- TOC entry 4286 (class 2604 OID 16840)
-- Name: id; Type: DEFAULT; Schema: elr; Owner: droolsuser
--

ALTER TABLE ONLY vocab_child_loinc ALTER COLUMN id SET DEFAULT nextval('vocab_child_loinc_id_seq'::regclass);


--
-- TOC entry 4290 (class 2604 OID 16841)
-- Name: id; Type: DEFAULT; Schema: elr; Owner: droolsuser
--

ALTER TABLE ONLY vocab_child_organism ALTER COLUMN id SET DEFAULT nextval('vocab_child_organism_id_seq'::regclass);


--
-- TOC entry 4291 (class 2604 OID 16842)
-- Name: id; Type: DEFAULT; Schema: elr; Owner: droolsuser
--

ALTER TABLE ONLY vocab_child_vocab ALTER COLUMN id SET DEFAULT nextval('vocab_child_vocab_id_seq'::regclass);


--
-- TOC entry 4345 (class 2604 OID 27995409)
-- Name: id; Type: DEFAULT; Schema: elr; Owner: droolsuser
--

ALTER TABLE ONLY vocab_codeset ALTER COLUMN id SET DEFAULT nextval('vocab_codeset_id_seq'::regclass);


--
-- TOC entry 4347 (class 2604 OID 27995441)
-- Name: id; Type: DEFAULT; Schema: elr; Owner: droolsuser
--

ALTER TABLE ONLY vocab_icd ALTER COLUMN id SET DEFAULT nextval('vocab_icd_id_seq'::regclass);


--
-- TOC entry 4292 (class 2604 OID 16843)
-- Name: id; Type: DEFAULT; Schema: elr; Owner: droolsuser
--

ALTER TABLE ONLY vocab_last_imported ALTER COLUMN id SET DEFAULT nextval('vocab_last_imported_id_seq'::regclass);


--
-- TOC entry 4197 (class 2604 OID 16844)
-- Name: id; Type: DEFAULT; Schema: elr; Owner: droolsuser
--

ALTER TABLE ONLY vocab_master2app ALTER COLUMN id SET DEFAULT nextval('master2app_id_seq'::regclass);


--
-- TOC entry 4298 (class 2604 OID 16845)
-- Name: c_id; Type: DEFAULT; Schema: elr; Owner: droolsuser
--

ALTER TABLE ONLY vocab_master_condition ALTER COLUMN c_id SET DEFAULT nextval('vocab_master_condition_c_id_seq'::regclass);


--
-- TOC entry 4306 (class 2604 OID 16846)
-- Name: l_id; Type: DEFAULT; Schema: elr; Owner: droolsuser
--

ALTER TABLE ONLY vocab_master_loinc ALTER COLUMN l_id SET DEFAULT nextval('vocab_master_loinc_l_id_seq'::regclass);


--
-- TOC entry 4311 (class 2604 OID 16847)
-- Name: o_id; Type: DEFAULT; Schema: elr; Owner: droolsuser
--

ALTER TABLE ONLY vocab_master_organism ALTER COLUMN o_id SET DEFAULT nextval('vocab_master_organism_o_id_seq'::regclass);


--
-- TOC entry 4312 (class 2604 OID 16848)
-- Name: id; Type: DEFAULT; Schema: elr; Owner: droolsuser
--

ALTER TABLE ONLY vocab_master_vocab ALTER COLUMN id SET DEFAULT nextval('vocab_master_vocab_id_seq'::regclass);


--
-- TOC entry 4344 (class 2604 OID 27867171)
-- Name: id; Type: DEFAULT; Schema: elr; Owner: droolsuser
--

ALTER TABLE ONLY vocab_pfge ALTER COLUMN id SET DEFAULT nextval('vocab_pfge_id_seq'::regclass);


--
-- TOC entry 4314 (class 2604 OID 16849)
-- Name: id; Type: DEFAULT; Schema: elr; Owner: droolsuser
--

ALTER TABLE ONLY vocab_rules_graylist ALTER COLUMN id SET DEFAULT nextval('vocab_rules_graylist_id_seq'::regclass);


--
-- TOC entry 4319 (class 2604 OID 16850)
-- Name: id; Type: DEFAULT; Schema: elr; Owner: droolsuser
--

ALTER TABLE ONLY vocab_rules_masterloinc ALTER COLUMN id SET DEFAULT nextval('vocab_rules_masterloinc_id_seq'::regclass);


--
-- TOC entry 4324 (class 2604 OID 16851)
-- Name: id; Type: DEFAULT; Schema: elr; Owner: droolsuser
--

ALTER TABLE ONLY vocab_rules_mastersnomed ALTER COLUMN id SET DEFAULT nextval('vocab_rules_mastersnomed_id_seq'::regclass);


--
-- TOC entry 4423 (class 2606 OID 16947)
-- Name: fac_id_pkey; Type: CONSTRAINT; Schema: elr; Owner: droolsuser; Tablespace: 
--

ALTER TABLE ONLY ss_facility
    ADD CONSTRAINT fac_id_pkey PRIMARY KEY (id);


--
-- TOC entry 4392 (class 2606 OID 16948)
-- Name: gateway_channels_pkey; Type: CONSTRAINT; Schema: elr; Owner: droolsuser; Tablespace: 
--

ALTER TABLE ONLY gateway_channels
    ADD CONSTRAINT gateway_channels_pkey PRIMARY KEY (id);


--
-- TOC entry 4394 (class 2606 OID 16949)
-- Name: gateway_exceptions_pkey; Type: CONSTRAINT; Schema: elr; Owner: droolsuser; Tablespace: 
--

ALTER TABLE ONLY gateway_exceptions
    ADD CONSTRAINT gateway_exceptions_pkey PRIMARY KEY (id);


--
-- TOC entry 4396 (class 2606 OID 16950)
-- Name: gateway_requests_pkey; Type: CONSTRAINT; Schema: elr; Owner: droolsuser; Tablespace: 
--

ALTER TABLE ONLY gateway_requests
    ADD CONSTRAINT gateway_requests_pkey PRIMARY KEY (id);


--
-- TOC entry 4398 (class 2606 OID 16951)
-- Name: gateway_users_pkey; Type: CONSTRAINT; Schema: elr; Owner: droolsuser; Tablespace: 
--

ALTER TABLE ONLY gateway_users
    ADD CONSTRAINT gateway_users_pkey PRIMARY KEY (id);


--
-- TOC entry 4403 (class 2606 OID 16952)
-- Name: gatewaytest_original_messages_pkey; Type: CONSTRAINT; Schema: elr; Owner: droolsuser; Tablespace: 
--

ALTER TABLE ONLY gatewaytest_original_messages
    ADD CONSTRAINT gatewaytest_original_messages_pkey PRIMARY KEY (id);


--
-- TOC entry 4405 (class 2606 OID 16953)
-- Name: graylist_request_audits_pkey; Type: CONSTRAINT; Schema: elr; Owner: droolsuser; Tablespace: 
--

ALTER TABLE ONLY graylist_request_audits
    ADD CONSTRAINT graylist_request_audits_pkey PRIMARY KEY (id);


--
-- TOC entry 4413 (class 2606 OID 16954)
-- Name: graylist_requests_pkey; Type: CONSTRAINT; Schema: elr; Owner: droolsuser; Tablespace: 
--

ALTER TABLE ONLY graylist_requests
    ADD CONSTRAINT graylist_requests_pkey PRIMARY KEY (id);


--
-- TOC entry 4415 (class 2606 OID 16955)
-- Name: graylist_spool_pkey; Type: CONSTRAINT; Schema: elr; Owner: droolsuser; Tablespace: 
--

ALTER TABLE ONLY graylist_spool
    ADD CONSTRAINT graylist_spool_pkey PRIMARY KEY (id);


--
-- TOC entry 4417 (class 2606 OID 16956)
-- Name: intake_stats_config_pkey; Type: CONSTRAINT; Schema: elr; Owner: droolsuser; Tablespace: 
--

ALTER TABLE ONLY intake_stats_config
    ADD CONSTRAINT intake_stats_config_pkey PRIMARY KEY (id);


--
-- TOC entry 4419 (class 2606 OID 16957)
-- Name: master2app_pkey; Type: CONSTRAINT; Schema: elr; Owner: droolsuser; Tablespace: 
--

ALTER TABLE ONLY vocab_master2app
    ADD CONSTRAINT master2app_pkey PRIMARY KEY (id);


--
-- TOC entry 4542 (class 2606 OID 16958)
-- Name: master_xml_flat_pkey; Type: CONSTRAINT; Schema: elr; Owner: dcpadmin; Tablespace: 
--

ALTER TABLE ONLY master_xml_flat
    ADD CONSTRAINT master_xml_flat_pkey PRIMARY KEY (id);


--
-- TOC entry 4578 (class 2606 OID 28514239)
-- Name: preprocessor_audit_exceptions_pkey; Type: CONSTRAINT; Schema: elr; Owner: droolsuser; Tablespace: 
--

ALTER TABLE ONLY preprocessor_audit_exceptions
    ADD CONSTRAINT preprocessor_audit_exceptions_pkey PRIMARY KEY (id);


--
-- TOC entry 4576 (class 2606 OID 28514222)
-- Name: preprocessor_exceptions_pkey; Type: CONSTRAINT; Schema: elr; Owner: droolsuser; Tablespace: 
--

ALTER TABLE ONLY preprocessor_exceptions
    ADD CONSTRAINT preprocessor_exceptions_pkey PRIMARY KEY (id);


--
-- TOC entry 4563 (class 2606 OID 27833349)
-- Name: ss_batched_messages_pkey; Type: CONSTRAINT; Schema: elr; Owner: droolsuser; Tablespace: 
--

ALTER TABLE ONLY ss_batched_messages
    ADD CONSTRAINT ss_batched_messages_pkey PRIMARY KEY (id);


--
-- TOC entry 4421 (class 2606 OID 16959)
-- Name: ss_biosense_pkey; Type: CONSTRAINT; Schema: elr; Owner: droolsuser; Tablespace: 
--

ALTER TABLE ONLY ss_biosense
    ADD CONSTRAINT ss_biosense_pkey PRIMARY KEY (id);


--
-- TOC entry 4554 (class 2606 OID 27404729)
-- Name: ss_connectors_pkey; Type: CONSTRAINT; Schema: elr; Owner: droolsuser; Tablespace: 
--

ALTER TABLE ONLY ss_connectors
    ADD CONSTRAINT ss_connectors_pkey PRIMARY KEY (id);


--
-- TOC entry 4427 (class 2606 OID 16961)
-- Name: structure_category_application_pkey; Type: CONSTRAINT; Schema: elr; Owner: droolsuser; Tablespace: 
--

ALTER TABLE ONLY structure_category_application
    ADD CONSTRAINT structure_category_application_pkey PRIMARY KEY (id);


--
-- TOC entry 4425 (class 2606 OID 16962)
-- Name: structure_category_pkey; Type: CONSTRAINT; Schema: elr; Owner: droolsuser; Tablespace: 
--

ALTER TABLE ONLY structure_category
    ADD CONSTRAINT structure_category_pkey PRIMARY KEY (id);


--
-- TOC entry 4429 (class 2606 OID 16964)
-- Name: structure_data_type_pkey; Type: CONSTRAINT; Schema: elr; Owner: droolsuser; Tablespace: 
--

ALTER TABLE ONLY structure_data_type
    ADD CONSTRAINT structure_data_type_pkey PRIMARY KEY (id);


--
-- TOC entry 4540 (class 2606 OID 16965)
-- Name: structure_knittable_loincs_pkey; Type: CONSTRAINT; Schema: elr; Owner: droolsuser; Tablespace: 
--

ALTER TABLE ONLY structure_knittable_loincs
    ADD CONSTRAINT structure_knittable_loincs_pkey PRIMARY KEY (id);


--
-- TOC entry 4435 (class 2606 OID 16968)
-- Name: structure_lookup_operator_pkey; Type: CONSTRAINT; Schema: elr; Owner: droolsuser; Tablespace: 
--

ALTER TABLE ONLY structure_lookup_operator
    ADD CONSTRAINT structure_lookup_operator_pkey PRIMARY KEY (id);


--
-- TOC entry 4437 (class 2606 OID 16969)
-- Name: structure_operand_type_pkey; Type: CONSTRAINT; Schema: elr; Owner: droolsuser; Tablespace: 
--

ALTER TABLE ONLY structure_operand_type
    ADD CONSTRAINT structure_operand_type_pkey PRIMARY KEY (id);


--
-- TOC entry 4439 (class 2606 OID 16970)
-- Name: structure_operator_pkey; Type: CONSTRAINT; Schema: elr; Owner: droolsuser; Tablespace: 
--

ALTER TABLE ONLY structure_operator
    ADD CONSTRAINT structure_operator_pkey PRIMARY KEY (id);


--
-- TOC entry 4443 (class 2606 OID 16971)
-- Name: structure_path_application_pkey; Type: CONSTRAINT; Schema: elr; Owner: droolsuser; Tablespace: 
--

ALTER TABLE ONLY structure_path_application
    ADD CONSTRAINT structure_path_application_pkey PRIMARY KEY (id);


--
-- TOC entry 4448 (class 2606 OID 16972)
-- Name: structure_path_mirth_pkey; Type: CONSTRAINT; Schema: elr; Owner: droolsuser; Tablespace: 
--

ALTER TABLE ONLY structure_path_mirth
    ADD CONSTRAINT structure_path_mirth_pkey PRIMARY KEY (id);


--
-- TOC entry 4441 (class 2606 OID 16973)
-- Name: structure_path_pkey; Type: CONSTRAINT; Schema: elr; Owner: droolsuser; Tablespace: 
--

ALTER TABLE ONLY structure_path
    ADD CONSTRAINT structure_path_pkey PRIMARY KEY (id);


--
-- TOC entry 4450 (class 2606 OID 16974)
-- Name: structure_path_rule_pkey; Type: CONSTRAINT; Schema: elr; Owner: droolsuser; Tablespace: 
--

ALTER TABLE ONLY structure_path_rule
    ADD CONSTRAINT structure_path_rule_pkey PRIMARY KEY (id);


--
-- TOC entry 4452 (class 2606 OID 16975)
-- Name: system_action_category_pkey; Type: CONSTRAINT; Schema: elr; Owner: droolsuser; Tablespace: 
--

ALTER TABLE ONLY system_action_categories
    ADD CONSTRAINT system_action_category_pkey PRIMARY KEY (id);


--
-- TOC entry 4544 (class 2606 OID 16976)
-- Name: system_alert_types_pkey; Type: CONSTRAINT; Schema: elr; Owner: droolsuser; Tablespace: 
--

ALTER TABLE ONLY system_alert_types
    ADD CONSTRAINT system_alert_types_pkey PRIMARY KEY (id);


--
-- TOC entry 4546 (class 2606 OID 16977)
-- Name: system_alerts_pkey; Type: CONSTRAINT; Schema: elr; Owner: droolsuser; Tablespace: 
--

ALTER TABLE ONLY system_alerts
    ADD CONSTRAINT system_alerts_pkey PRIMARY KEY (id);


--
-- TOC entry 4454 (class 2606 OID 16978)
-- Name: system_audit_exceptions_pkey; Type: CONSTRAINT; Schema: elr; Owner: droolsuser; Tablespace: 
--

ALTER TABLE ONLY system_audit_exceptions
    ADD CONSTRAINT system_audit_exceptions_pkey PRIMARY KEY (id);


--
-- TOC entry 4459 (class 2606 OID 16980)
-- Name: system_districts_system_external_id_key; Type: CONSTRAINT; Schema: elr; Owner: droolsuser; Tablespace: 
--

ALTER TABLE ONLY system_districts
    ADD CONSTRAINT system_districts_system_external_id_key UNIQUE (system_external_id);


--
-- TOC entry 4461 (class 2606 OID 16981)
-- Name: system_exceptions_id_pkey; Type: CONSTRAINT; Schema: elr; Owner: droolsuser; Tablespace: 
--

ALTER TABLE ONLY system_exceptions
    ADD CONSTRAINT system_exceptions_id_pkey PRIMARY KEY (id);


--
-- TOC entry 4463 (class 2606 OID 16982)
-- Name: system_menu_pkey; Type: CONSTRAINT; Schema: elr; Owner: droolsuser; Tablespace: 
--

ALTER TABLE ONLY system_menus
    ADD CONSTRAINT system_menu_pkey PRIMARY KEY (id);


--
-- TOC entry 4465 (class 2606 OID 16983)
-- Name: system_message_action_pkey; Type: CONSTRAINT; Schema: elr; Owner: droolsuser; Tablespace: 
--

ALTER TABLE ONLY system_message_actions
    ADD CONSTRAINT system_message_action_pkey PRIMARY KEY (id);


--
-- TOC entry 4468 (class 2606 OID 16984)
-- Name: system_message_comments_pkey; Type: CONSTRAINT; Schema: elr; Owner: droolsuser; Tablespace: 
--

ALTER TABLE ONLY system_message_comments
    ADD CONSTRAINT system_message_comments_pkey PRIMARY KEY (id);


-- Foreign Key: system_message_comments_system_message_id_fkey

ALTER TABLE ONLY system_message_comments
  ADD CONSTRAINT system_message_comments_system_message_id_fkey FOREIGN KEY (system_message_id)
      REFERENCES system_messages (id) MATCH SIMPLE
      ON UPDATE NO ACTION ON DELETE CASCADE;


--
-- TOC entry 4471 (class 2606 OID 16985)
-- Name: system_message_exceptions_pkey; Type: CONSTRAINT; Schema: elr; Owner: droolsuser; Tablespace: 
--

ALTER TABLE ONLY system_message_exceptions
    ADD CONSTRAINT system_message_exceptions_pkey PRIMARY KEY (id);


--
-- TOC entry 4473 (class 2606 OID 16986)
-- Name: system_message_flag_comments_pkey; Type: CONSTRAINT; Schema: elr; Owner: droolsuser; Tablespace: 
--

ALTER TABLE ONLY system_message_flag_comments
    ADD CONSTRAINT system_message_flag_comments_pkey PRIMARY KEY (id);


-- Foreign Key: system_message_flag_comments_system_message_id_fkey
ALTER TABLE ONLY system_message_flag_comments
  ADD CONSTRAINT system_message_flag_comments_system_message_id_fkey FOREIGN KEY (system_message_id)
      REFERENCES system_messages (id) MATCH SIMPLE
      ON UPDATE NO ACTION ON DELETE CASCADE;


--
-- TOC entry 4475 (class 2606 OID 16987)
-- Name: system_message_flags_pkey; Type: CONSTRAINT; Schema: elr; Owner: droolsuser; Tablespace: 
--

ALTER TABLE ONLY system_message_flags
    ADD CONSTRAINT system_message_flags_pkey PRIMARY KEY (id);


--
-- TOC entry 4496 (class 2606 OID 16988)
-- Name: system_messages_audits_pkey; Type: CONSTRAINT; Schema: elr; Owner: droolsuser; Tablespace: 
--

ALTER TABLE ONLY system_messages_audits
    ADD CONSTRAINT system_messages_audits_pkey PRIMARY KEY (id);


-- Foreign Key: system_messages_audits_system_message_id_fkey
ALTER TABLE ONLY system_messages_audits
  ADD CONSTRAINT system_messages_audits_system_message_id_fkey FOREIGN KEY (system_message_id)
      REFERENCES system_messages (id) MATCH SIMPLE
      ON UPDATE NO ACTION ON DELETE CASCADE;


--
-- TOC entry 4499 (class 2606 OID 16990)
-- Name: system_nedss_xml_audits_pkey; Type: CONSTRAINT; Schema: elr; Owner: droolsuser; Tablespace: 
--

ALTER TABLE ONLY system_nedss_xml_audits
    ADD CONSTRAINT system_nedss_xml_audits_pkey PRIMARY KEY (id);


--
-- TOC entry 4504 (class 2606 OID 16991)
-- Name: system_original_messages_pkey; Type: CONSTRAINT; Schema: elr; Owner: droolsuser; Tablespace: 
--

ALTER TABLE ONLY system_original_messages
    ADD CONSTRAINT system_original_messages_pkey PRIMARY KEY (id);


--
-- TOC entry 4548 (class 2606 OID 16992)
-- Name: system_small_areas_pkey; Type: CONSTRAINT; Schema: elr; Owner: droolsuser; Tablespace: 
--

ALTER TABLE ONLY system_small_areas
    ADD CONSTRAINT system_small_areas_pkey PRIMARY KEY (id);


--
-- TOC entry 4507 (class 2606 OID 16993)
-- Name: system_statuses_pkey; Type: CONSTRAINT; Schema: elr; Owner: droolsuser; Tablespace: 
--

ALTER TABLE ONLY system_statuses
    ADD CONSTRAINT system_statuses_pkey PRIMARY KEY (id);


--
-- TOC entry 4510 (class 2606 OID 16994)
-- Name: system_zip_codes_pkey; Type: CONSTRAINT; Schema: elr; Owner: droolsuser; Tablespace: 
--

ALTER TABLE ONLY system_zip_codes
    ADD CONSTRAINT system_zip_codes_pkey PRIMARY KEY (id);


--
-- TOC entry 4550 (class 2606 OID 16995)
-- Name: system_zip_to_small_area_pkey; Type: CONSTRAINT; Schema: elr; Owner: droolsuser; Tablespace: 
--

ALTER TABLE ONLY system_zip_to_small_area
    ADD CONSTRAINT system_zip_to_small_area_pkey PRIMARY KEY (id);


--
-- TOC entry 4552 (class 2606 OID 16996)
-- Name: system_zip_to_small_area_zipcode_key; Type: CONSTRAINT; Schema: elr; Owner: droolsuser; Tablespace: 
--

ALTER TABLE ONLY system_zip_to_small_area
    ADD CONSTRAINT system_zip_to_small_area_zipcode_key UNIQUE (zipcode);


--
-- TOC entry 4514 (class 2606 OID 16999)
-- Name: vocab_audits_pkey; Type: CONSTRAINT; Schema: elr; Owner: droolsuser; Tablespace: 
--

ALTER TABLE ONLY vocab_audits
    ADD CONSTRAINT vocab_audits_pkey PRIMARY KEY (id);


--
-- TOC entry 4516 (class 2606 OID 17000)
-- Name: vocab_c2m_testresult_pkey; Type: CONSTRAINT; Schema: elr; Owner: droolsuser; Tablespace: 
--

ALTER TABLE ONLY vocab_c2m_testresult
    ADD CONSTRAINT vocab_c2m_testresult_pkey PRIMARY KEY (id);


--
-- TOC entry 4572 (class 2606 OID 27995425)
-- Name: vocab_child_codeset_pkey; Type: CONSTRAINT; Schema: elr; Owner: droolsuser; Tablespace: 
--

ALTER TABLE ONLY vocab_child_codeset
    ADD CONSTRAINT vocab_child_codeset_pkey PRIMARY KEY (id);


--
-- TOC entry 4518 (class 2606 OID 17001)
-- Name: vocab_child_loinc_pkey; Type: CONSTRAINT; Schema: elr; Owner: droolsuser; Tablespace: 
--

ALTER TABLE ONLY vocab_child_loinc
    ADD CONSTRAINT vocab_child_loinc_pkey PRIMARY KEY (id);


--
-- TOC entry 4520 (class 2606 OID 17002)
-- Name: vocab_child_organism_pkey; Type: CONSTRAINT; Schema: elr; Owner: droolsuser; Tablespace: 
--

ALTER TABLE ONLY vocab_child_organism
    ADD CONSTRAINT vocab_child_organism_pkey PRIMARY KEY (id);


--
-- TOC entry 4522 (class 2606 OID 17003)
-- Name: vocab_child_vocab_pkey; Type: CONSTRAINT; Schema: elr; Owner: droolsuser; Tablespace: 
--

ALTER TABLE ONLY vocab_child_vocab
    ADD CONSTRAINT vocab_child_vocab_pkey PRIMARY KEY (id);


--
-- TOC entry 4570 (class 2606 OID 27995414)
-- Name: vocab_codeset_pkey; Type: CONSTRAINT; Schema: elr; Owner: droolsuser; Tablespace: 
--

ALTER TABLE ONLY vocab_codeset
    ADD CONSTRAINT vocab_codeset_pkey PRIMARY KEY (id);


--
-- TOC entry 4574 (class 2606 OID 27995447)
-- Name: vocab_icd_pkey; Type: CONSTRAINT; Schema: elr; Owner: droolsuser; Tablespace: 
--

ALTER TABLE ONLY vocab_icd
    ADD CONSTRAINT vocab_icd_pkey PRIMARY KEY (id);


--
-- TOC entry 4524 (class 2606 OID 17004)
-- Name: vocab_last_imported_pkey; Type: CONSTRAINT; Schema: elr; Owner: droolsuser; Tablespace: 
--

ALTER TABLE ONLY vocab_last_imported
    ADD CONSTRAINT vocab_last_imported_pkey PRIMARY KEY (id);


--
-- TOC entry 4526 (class 2606 OID 17005)
-- Name: vocab_master_condition_pk; Type: CONSTRAINT; Schema: elr; Owner: droolsuser; Tablespace: 
--

ALTER TABLE ONLY vocab_master_condition
    ADD CONSTRAINT vocab_master_condition_pk PRIMARY KEY (c_id);


--
-- TOC entry 4528 (class 2606 OID 17006)
-- Name: vocab_master_loinc_pk; Type: CONSTRAINT; Schema: elr; Owner: droolsuser; Tablespace: 
--

ALTER TABLE ONLY vocab_master_loinc
    ADD CONSTRAINT vocab_master_loinc_pk PRIMARY KEY (l_id);


--
-- TOC entry 4530 (class 2606 OID 17007)
-- Name: vocab_master_organism_pk; Type: CONSTRAINT; Schema: elr; Owner: droolsuser; Tablespace: 
--

ALTER TABLE ONLY vocab_master_organism
    ADD CONSTRAINT vocab_master_organism_pk PRIMARY KEY (o_id);


--
-- TOC entry 4532 (class 2606 OID 17008)
-- Name: vocab_master_vocab_pkey; Type: CONSTRAINT; Schema: elr; Owner: droolsuser; Tablespace: 
--

ALTER TABLE ONLY vocab_master_vocab
    ADD CONSTRAINT vocab_master_vocab_pkey PRIMARY KEY (id);


--
-- TOC entry 4568 (class 2606 OID 27867173)
-- Name: vocab_pfge_pkey; Type: CONSTRAINT; Schema: elr; Owner: droolsuser; Tablespace: 
--

ALTER TABLE ONLY vocab_pfge
    ADD CONSTRAINT vocab_pfge_pkey PRIMARY KEY (id);


--
-- TOC entry 4534 (class 2606 OID 17009)
-- Name: vocab_rules_graylist_pkey; Type: CONSTRAINT; Schema: elr; Owner: droolsuser; Tablespace: 
--

ALTER TABLE ONLY vocab_rules_graylist
    ADD CONSTRAINT vocab_rules_graylist_pkey PRIMARY KEY (id);


--
-- TOC entry 4536 (class 2606 OID 17010)
-- Name: vocab_rules_masterloinc_pkey; Type: CONSTRAINT; Schema: elr; Owner: droolsuser; Tablespace: 
--

ALTER TABLE ONLY vocab_rules_masterloinc
    ADD CONSTRAINT vocab_rules_masterloinc_pkey PRIMARY KEY (id);


--
-- TOC entry 4538 (class 2606 OID 17011)
-- Name: vocab_rules_mastersnomed_pkey; Type: CONSTRAINT; Schema: elr; Owner: droolsuser; Tablespace: 
--

ALTER TABLE ONLY vocab_rules_mastersnomed
    ADD CONSTRAINT vocab_rules_mastersnomed_pkey PRIMARY KEY (id);


--
-- TOC entry 4399 (class 1259 OID 625200)
-- Name: gatewaytest_original_messages__created_at__anl__index; Type: INDEX; Schema: elr; Owner: droolsuser; Tablespace: 
--

CREATE INDEX gatewaytest_original_messages__created_at__anl__index ON gatewaytest_original_messages USING btree (created_at);


--
-- TOC entry 4400 (class 1259 OID 625201)
-- Name: gatewaytest_original_messages__created_at__dnl__index; Type: INDEX; Schema: elr; Owner: droolsuser; Tablespace: 
--

CREATE INDEX gatewaytest_original_messages__created_at__dnl__index ON gatewaytest_original_messages USING btree (created_at DESC NULLS LAST);


--
-- TOC entry 4401 (class 1259 OID 625202)
-- Name: gatewaytest_original_messages_idx; Type: INDEX; Schema: elr; Owner: droolsuser; Tablespace: 
--

CREATE INDEX gatewaytest_original_messages_idx ON gatewaytest_original_messages USING btree (created_at, updated_at, sent, status, channel);


--
-- TOC entry 4406 (class 1259 OID 625203)
-- Name: graylist_requests__condition__index; Type: INDEX; Schema: elr; Owner: droolsuser; Tablespace: 
--

CREATE INDEX graylist_requests__condition__index ON graylist_requests USING btree (condition);


--
-- TOC entry 4407 (class 1259 OID 625204)
-- Name: graylist_requests__created_at__index; Type: INDEX; Schema: elr; Owner: droolsuser; Tablespace: 
--

CREATE INDEX graylist_requests__created_at__index ON graylist_requests USING btree (created_at);


--
-- TOC entry 4408 (class 1259 OID 625205)
-- Name: graylist_requests__dob__index; Type: INDEX; Schema: elr; Owner: droolsuser; Tablespace: 
--

CREATE INDEX graylist_requests__dob__index ON graylist_requests USING btree (dob);


--
-- TOC entry 4409 (class 1259 OID 625206)
-- Name: graylist_requests__first_name__index; Type: INDEX; Schema: elr; Owner: droolsuser; Tablespace: 
--

CREATE INDEX graylist_requests__first_name__index ON graylist_requests USING btree (first_name);


--
-- TOC entry 4410 (class 1259 OID 625207)
-- Name: graylist_requests__last_name__index; Type: INDEX; Schema: elr; Owner: droolsuser; Tablespace: 
--

CREATE INDEX graylist_requests__last_name__index ON graylist_requests USING btree (last_name);


-- graylist_requests__middle_name__index
CREATE INDEX graylist_requests__middle_name__index ON graylist_requests USING btree (middle_name);


--
-- TOC entry 4411 (class 1259 OID 625208)
-- Name: graylist_requests__status__index; Type: INDEX; Schema: elr; Owner: droolsuser; Tablespace: 
--

CREATE INDEX graylist_requests__status__index ON graylist_requests USING btree (status);


--
-- TOC entry 4555 (class 1259 OID 27833353)
-- Name: ss_batched_messages_application_name_idx; Type: INDEX; Schema: elr; Owner: droolsuser; Tablespace: 
--

CREATE INDEX ss_batched_messages_application_name_idx ON ss_batched_messages USING btree (application_name);


--
-- TOC entry 4556 (class 1259 OID 27833350)
-- Name: ss_batched_messages_connector_idx; Type: INDEX; Schema: elr; Owner: droolsuser; Tablespace: 
--

CREATE INDEX ss_batched_messages_connector_idx ON ss_batched_messages USING btree (connector);


--
-- TOC entry 4557 (class 1259 OID 27833351)
-- Name: ss_batched_messages_created_at_idx; Type: INDEX; Schema: elr; Owner: droolsuser; Tablespace: 
--

CREATE INDEX ss_batched_messages_created_at_idx ON ss_batched_messages USING btree (created_at DESC NULLS LAST);


--
-- TOC entry 4558 (class 1259 OID 27833352)
-- Name: ss_batched_messages_facility_id_idx; Type: INDEX; Schema: elr; Owner: droolsuser; Tablespace: 
--

CREATE INDEX ss_batched_messages_facility_id_idx ON ss_batched_messages USING btree (facility_id);


--
-- TOC entry 4559 (class 1259 OID 27833354)
-- Name: ss_batched_messages_facility_name_idx; Type: INDEX; Schema: elr; Owner: droolsuser; Tablespace: 
--

CREATE INDEX ss_batched_messages_facility_name_idx ON ss_batched_messages USING btree (facility_name);


--
-- TOC entry 4560 (class 1259 OID 27833355)
-- Name: ss_batched_messages_locked_idx; Type: INDEX; Schema: elr; Owner: droolsuser; Tablespace: 
--

CREATE INDEX ss_batched_messages_locked_idx ON ss_batched_messages USING btree (locked);


--
-- TOC entry 4561 (class 1259 OID 27833356)
-- Name: ss_batched_messages_message_type_idx; Type: INDEX; Schema: elr; Owner: droolsuser; Tablespace: 
--

CREATE INDEX ss_batched_messages_message_type_idx ON ss_batched_messages USING btree (message_type);


--
-- TOC entry 4564 (class 1259 OID 27833357)
-- Name: ss_batched_messages_received_idx; Type: INDEX; Schema: elr; Owner: droolsuser; Tablespace: 
--

CREATE INDEX ss_batched_messages_received_idx ON ss_batched_messages USING btree (received);


--
-- TOC entry 4565 (class 1259 OID 27833358)
-- Name: ss_batched_messages_sent_idx; Type: INDEX; Schema: elr; Owner: droolsuser; Tablespace: 
--

CREATE INDEX ss_batched_messages_sent_idx ON ss_batched_messages USING btree (sent);


--
-- TOC entry 4566 (class 1259 OID 27833359)
-- Name: ss_batched_messages_valid_idx; Type: INDEX; Schema: elr; Owner: droolsuser; Tablespace: 
--

CREATE INDEX ss_batched_messages_valid_idx ON ss_batched_messages USING btree (valid);


--
-- TOC entry 4455 (class 1259 OID 625210)
-- Name: system_districts_idx; Type: INDEX; Schema: elr; Owner: droolsuser; Tablespace: 
--

CREATE INDEX system_districts_idx ON system_districts USING btree (system_external_id);


--
-- TOC entry 4466 (class 1259 OID 625211)
-- Name: system_message_actions_idx; Type: INDEX; Schema: elr; Owner: droolsuser; Tablespace: 
--

CREATE INDEX system_message_actions_idx ON system_message_actions USING btree (action_category_id);


--
-- TOC entry 4469 (class 1259 OID 625212)
-- Name: system_message_exceptions_idx; Type: INDEX; Schema: elr; Owner: droolsuser; Tablespace: 
--

CREATE INDEX system_message_exceptions_idx ON system_message_exceptions USING btree (system_message_id, exception_id);


--
-- TOC entry 4476 (class 1259 OID 625213)
-- Name: system_messages__assigned_date__anl__index; Type: INDEX; Schema: elr; Owner: droolsuser; Tablespace: 
--

CREATE INDEX system_messages__assigned_date__anl__index ON system_messages USING btree (assigned_date);


--
-- TOC entry 4477 (class 1259 OID 625214)
-- Name: system_messages__assigned_date__dnl__index; Type: INDEX; Schema: elr; Owner: droolsuser; Tablespace: 
--

CREATE INDEX system_messages__assigned_date__dnl__index ON system_messages USING btree (assigned_date DESC NULLS LAST);


--
-- TOC entry 4478 (class 1259 OID 625215)
-- Name: system_messages__final_status; Type: INDEX; Schema: elr; Owner: droolsuser; Tablespace: 
--

CREATE INDEX system_messages__final_status ON system_messages USING btree (final_status);


--
-- TOC entry 4479 (class 1259 OID 625216)
-- Name: system_messages__immediate_notify__index; Type: INDEX; Schema: elr; Owner: droolsuser; Tablespace: 
--

CREATE INDEX system_messages__immediate_notify__index ON system_messages USING btree (immediate_notify);


--
-- TOC entry 4480 (class 1259 OID 26060506)
-- Name: system_messages__lab_result_id__anl__index; Type: INDEX; Schema: elr; Owner: droolsuser; Tablespace: 
--

CREATE INDEX system_messages__lab_result_id__anl__index ON system_messages USING btree (lab_result_id);


--
-- TOC entry 4481 (class 1259 OID 625217)
-- Name: system_messages__reported_at__anl__index; Type: INDEX; Schema: elr; Owner: droolsuser; Tablespace: 
--

CREATE INDEX system_messages__reported_at__anl__index ON system_messages USING btree (reported_at);


-- Index: master_xml_flat_system_messages_id_idx

CREATE INDEX master_xml_flat_system_messages_id_idx
  ON master_xml_flat
  USING btree
  (system_messages_id);


-- Index: system_messages_dob

CREATE INDEX system_messages_dob
  ON system_messages
  USING btree
  (dob);


-- Index: system_messages_lname_char

CREATE INDEX system_messages_lname_char
  ON system_messages
  USING btree
  (lower("substring"(lname::text, 1, 1)) COLLATE pg_catalog."default");


-- Index: system_messages_copy_parent_id_idx

CREATE INDEX system_messages_copy_parent_id_idx
  ON system_messages
  USING btree
  (copy_parent_id);

  
-- Index: system_messages_event_id_idx

CREATE INDEX system_messages_event_id_idx
  ON system_messages
  USING btree
  (event_id);


--
-- TOC entry 4494 (class 1259 OID 625218)
-- Name: system_messages_audits_idx; Type: INDEX; Schema: elr; Owner: droolsuser; Tablespace: 
--

CREATE INDEX system_messages_audits_idx ON system_messages_audits USING btree (message_action_id, system_message_id, action_category_id, lab_id, system_exception_id, system_status_id);


--
-- TOC entry 4482 (class 1259 OID 625219)
-- Name: system_messages_ca_index; Type: INDEX; Schema: elr; Owner: droolsuser; Tablespace: 
--

CREATE INDEX system_messages_ca_index ON system_messages USING btree (created_at);


--
-- TOC entry 4483 (class 1259 OID 625220)
-- Name: system_messages_cl_index; Type: INDEX; Schema: elr; Owner: droolsuser; Tablespace: 
--

CREATE INDEX system_messages_cl_index ON system_messages USING btree (child_loinc);


--
-- TOC entry 4484 (class 1259 OID 625221)
-- Name: system_messages_ctc_index; Type: INDEX; Schema: elr; Owner: droolsuser; Tablespace: 
--

CREATE INDEX system_messages_ctc_index ON system_messages USING btree (child_test_code);


--
-- TOC entry 4485 (class 1259 OID 625222)
-- Name: system_messages_del_index; Type: INDEX; Schema: elr; Owner: droolsuser; Tablespace: 
--

CREATE INDEX system_messages_del_index ON system_messages USING btree (deleted);


--
-- TOC entry 4486 (class 1259 OID 625223)
-- Name: system_messages_dis_index; Type: INDEX; Schema: elr; Owner: droolsuser; Tablespace: 
--

CREATE INDEX system_messages_dis_index ON system_messages USING btree (disease);


--
-- TOC entry 4487 (class 1259 OID 625224)
-- Name: system_messages_li_index; Type: INDEX; Schema: elr; Owner: droolsuser; Tablespace: 
--

CREATE INDEX system_messages_li_index ON system_messages USING btree (lab_id);


--
-- TOC entry 4488 (class 1259 OID 625225)
-- Name: system_messages_lrv2_index; Type: INDEX; Schema: elr; Owner: droolsuser; Tablespace: 
--

CREATE INDEX system_messages_lrv2_index ON system_messages USING btree (local_result_value_2);


--
-- TOC entry 4489 (class 1259 OID 625226)
-- Name: system_messages_lrv_index; Type: INDEX; Schema: elr; Owner: droolsuser; Tablespace: 
--

CREATE INDEX system_messages_lrv_index ON system_messages USING btree (local_result_value);


--
-- TOC entry 4490 (class 1259 OID 625227)
-- Name: system_messages_ml_index; Type: INDEX; Schema: elr; Owner: droolsuser; Tablespace: 
--

CREATE INDEX system_messages_ml_index ON system_messages USING btree (loinc_code);


--
-- TOC entry 4491 (class 1259 OID 625228)
-- Name: system_messages_omi_index; Type: INDEX; Schema: elr; Owner: droolsuser; Tablespace: 
--

CREATE INDEX system_messages_omi_index ON system_messages USING btree (original_message_id);


-- Index: system_messages_lab_test_result_idx
CREATE INDEX system_messages_lab_test_result_idx ON system_messages USING btree (lab_test_result);


-- Index: system_messages_susceptibility_test_result_idx
CREATE INDEX system_messages_susceptibility_test_result_idx ON system_messages USING btree (susceptibility_test_result);


-- Index: system_messages_trgm_filtered_fname

-- DROP INDEX system_messages_trgm_filtered_fname;

CREATE INDEX system_messages_trgm_filtered_fname
    ON system_messages USING gist
    (btrim(lower(regexp_replace(translate(fname::text, ',-_''."()`'::text, '   '::text), '\s\s+'::text, ' '::text, 'g'::text))) COLLATE pg_catalog."default" gist_trgm_ops)
    TABLESPACE pg_default;

-- Index: system_messages_trgm_filtered_lname

-- DROP INDEX system_messages_trgm_filtered_lname;

CREATE INDEX system_messages_trgm_filtered_lname
    ON system_messages USING gist
    (btrim(lower(regexp_replace(translate(lname::text, ',-_''."()`'::text, '   '::text), '\s\s+'::text, ' '::text, 'g'::text))) COLLATE pg_catalog."default" gist_trgm_ops)
    TABLESPACE pg_default;

-- Index: system_messages_trgm_filtered_mname

-- DROP INDEX system_messages_trgm_filtered_mname;

CREATE INDEX system_messages_trgm_filtered_mname
    ON system_messages USING gist
    (btrim(lower(regexp_replace(translate(mname::text, ',-_''."()`'::text, '   '::text), '\s\s+'::text, ' '::text, 'g'::text))) COLLATE pg_catalog."default" gist_trgm_ops)
    TABLESPACE pg_default;


-- Index: system_messages_final_status_del_vocab_app_id_index

-- DROP INDEX system_messages_final_status_del_vocab_app_id_index;
CREATE INDEX system_messages_final_status_del_vocab_app_id_index
    ON system_messages USING btree
        (final_status ASC NULLS LAST, deleted ASC NULLS LAST, vocab_app_id ASC NULLS LAST)
    TABLESPACE pg_default;


--
-- TOC entry 4497 (class 1259 OID 625229)
-- Name: system_nedss_xml_audits_idx; Type: INDEX; Schema: elr; Owner: droolsuser; Tablespace: 
--

CREATE INDEX system_nedss_xml_audits_idx ON system_nedss_xml_audits USING btree (created_at, system_messages_audits_id, is_update);


--
-- TOC entry 4500 (class 1259 OID 625230)
-- Name: system_original_messages__created_at__anl__index; Type: INDEX; Schema: elr; Owner: droolsuser; Tablespace: 
--

CREATE INDEX system_original_messages__created_at__anl__index ON system_original_messages USING btree (created_at);


--
-- TOC entry 4501 (class 1259 OID 625231)
-- Name: system_original_messages__created_at__dnl__index; Type: INDEX; Schema: elr; Owner: droolsuser; Tablespace: 
--

CREATE INDEX system_original_messages__created_at__dnl__index ON system_original_messages USING btree (created_at DESC NULLS LAST);


--
-- TOC entry 4502 (class 1259 OID 625232)
-- Name: system_original_messages_idx; Type: INDEX; Schema: elr; Owner: droolsuser; Tablespace: 
--

CREATE INDEX system_original_messages_idx ON system_original_messages USING btree (created_at, updated_at, sent, status, channel);



CREATE INDEX system_original_messages_connector_idx ON system_original_messages USING btree (connector);


--
-- TOC entry 4505 (class 1259 OID 625239)
-- Name: system_statuses_idx; Type: INDEX; Schema: elr; Owner: droolsuser; Tablespace: 
--

CREATE INDEX system_statuses_idx ON system_statuses USING btree (parent_id, sort, type);


--
-- TOC entry 4508 (class 1259 OID 625240)
-- Name: system_zip_codes_idx; Type: INDEX; Schema: elr; Owner: droolsuser; Tablespace: 
--

CREATE INDEX system_zip_codes_idx ON system_zip_codes USING btree (zipcode, system_district_id);


--
-- TOC entry 4625 (class 2606 OID 28514240)
-- Name: preprocessor_audit_exceptions_system_original_messages_id_fkey; Type: FK CONSTRAINT; Schema: elr; Owner: droolsuser
--

ALTER TABLE ONLY preprocessor_audit_exceptions
    ADD CONSTRAINT preprocessor_audit_exceptions_system_original_messages_id_fkey FOREIGN KEY (system_original_messages_id) REFERENCES system_original_messages(id) ON UPDATE CASCADE ON DELETE CASCADE;


--
-- TOC entry 4624 (class 2606 OID 28514223)
-- Name: preprocessor_exceptions_system_original_messages_id_fkey; Type: FK CONSTRAINT; Schema: elr; Owner: droolsuser
--

ALTER TABLE ONLY preprocessor_exceptions
    ADD CONSTRAINT preprocessor_exceptions_system_original_messages_id_fkey FOREIGN KEY (system_original_messages_id) REFERENCES system_original_messages(id) ON UPDATE CASCADE ON DELETE CASCADE;


--
-- TOC entry 4584 (class 2606 OID 17099)
-- Name: structure_category_application_app_id_fkey; Type: FK CONSTRAINT; Schema: elr; Owner: droolsuser
--

ALTER TABLE ONLY structure_category_application
    ADD CONSTRAINT structure_category_application_app_id_fkey FOREIGN KEY (app_id) REFERENCES vocab_app(id);


--
-- TOC entry 4585 (class 2606 OID 17104)
-- Name: structure_category_application_category_id_fkey; Type: FK CONSTRAINT; Schema: elr; Owner: droolsuser
--

ALTER TABLE ONLY structure_category_application
    ADD CONSTRAINT structure_category_application_category_id_fkey FOREIGN KEY (category_id) REFERENCES structure_category(id);


--
-- TOC entry 4586 (class 2606 OID 17114)
-- Name: structure_labs_default_jurisdiction_id_fkey; Type: FK CONSTRAINT; Schema: elr; Owner: droolsuser
--

ALTER TABLE ONLY structure_labs
    ADD CONSTRAINT structure_labs_default_jurisdiction_id_fkey FOREIGN KEY (default_jurisdiction_id) REFERENCES system_districts(id);


--
-- TOC entry 4589 (class 2606 OID 17119)
-- Name: structure_path_application_app_id_fkey; Type: FK CONSTRAINT; Schema: elr; Owner: droolsuser
--

ALTER TABLE ONLY structure_path_application
    ADD CONSTRAINT structure_path_application_app_id_fkey FOREIGN KEY (app_id) REFERENCES vocab_app(id);


--
-- TOC entry 4590 (class 2606 OID 17124)
-- Name: structure_path_application_category_application_id_fkey; Type: FK CONSTRAINT; Schema: elr; Owner: droolsuser
--

ALTER TABLE ONLY structure_path_application
    ADD CONSTRAINT structure_path_application_category_application_id_fkey FOREIGN KEY (category_application_id) REFERENCES structure_category_application(id);


--
-- TOC entry 4591 (class 2606 OID 17129)
-- Name: structure_path_application_structure_lookup_operator_id_fkey; Type: FK CONSTRAINT; Schema: elr; Owner: droolsuser
--

ALTER TABLE ONLY structure_path_application
    ADD CONSTRAINT structure_path_application_structure_lookup_operator_id_fkey FOREIGN KEY (structure_lookup_operator_id) REFERENCES structure_lookup_operator(id);


--
-- TOC entry 4592 (class 2606 OID 17134)
-- Name: structure_path_application_structure_path_id_fkey; Type: FK CONSTRAINT; Schema: elr; Owner: droolsuser
--

ALTER TABLE ONLY structure_path_application
    ADD CONSTRAINT structure_path_application_structure_path_id_fkey FOREIGN KEY (structure_path_id) REFERENCES structure_path(id);


--
-- TOC entry 4587 (class 2606 OID 17139)
-- Name: structure_path_category_id_fkey; Type: FK CONSTRAINT; Schema: elr; Owner: droolsuser
--

ALTER TABLE ONLY structure_path
    ADD CONSTRAINT structure_path_category_id_fkey FOREIGN KEY (category_id) REFERENCES structure_category(id);


--
-- TOC entry 4588 (class 2606 OID 17144)
-- Name: structure_path_data_type_id_fkey; Type: FK CONSTRAINT; Schema: elr; Owner: droolsuser
--

ALTER TABLE ONLY structure_path
    ADD CONSTRAINT structure_path_data_type_id_fkey FOREIGN KEY (data_type_id) REFERENCES structure_data_type(id);


--
-- TOC entry 4594 (class 2606 OID 17149)
-- Name: structure_path_mirth_lab_id_fkey; Type: FK CONSTRAINT; Schema: elr; Owner: droolsuser
--

ALTER TABLE ONLY structure_path_mirth
    ADD CONSTRAINT structure_path_mirth_lab_id_fkey FOREIGN KEY (lab_id) REFERENCES structure_labs(id) ON UPDATE NO ACTION ON DELETE CASCADE;


--
-- TOC entry 4595 (class 2606 OID 17154)
-- Name: structure_path_rule_and_or_operator_id_fkey; Type: FK CONSTRAINT; Schema: elr; Owner: droolsuser
--

ALTER TABLE ONLY structure_path_rule
    ADD CONSTRAINT structure_path_rule_and_or_operator_id_fkey FOREIGN KEY (and_or_operator_id) REFERENCES structure_operator(id);


--
-- TOC entry 4596 (class 2606 OID 17159)
-- Name: structure_path_rule_operand_type_id_fkey; Type: FK CONSTRAINT; Schema: elr; Owner: droolsuser
--

ALTER TABLE ONLY structure_path_rule
    ADD CONSTRAINT structure_path_rule_operand_type_id_fkey FOREIGN KEY (operand_type_id) REFERENCES structure_operand_type(id);


--
-- TOC entry 4597 (class 2606 OID 17164)
-- Name: structure_path_rule_operator_id_fkey; Type: FK CONSTRAINT; Schema: elr; Owner: droolsuser
--

ALTER TABLE ONLY structure_path_rule
    ADD CONSTRAINT structure_path_rule_operator_id_fkey FOREIGN KEY (operator_id) REFERENCES structure_operator(id);


--
-- TOC entry 4598 (class 2606 OID 17169)
-- Name: structure_path_rule_path_id_fkey; Type: FK CONSTRAINT; Schema: elr; Owner: droolsuser
--

ALTER TABLE ONLY structure_path_rule
    ADD CONSTRAINT structure_path_rule_path_id_fkey FOREIGN KEY (path_id) REFERENCES structure_path(id);


--
-- TOC entry 4616 (class 2606 OID 17174)
-- Name: system_alerts_alert_type_id_fkey; Type: FK CONSTRAINT; Schema: elr; Owner: droolsuser
--

ALTER TABLE ONLY system_alerts
    ADD CONSTRAINT system_alerts_alert_type_id_fkey FOREIGN KEY (alert_type_id) REFERENCES system_alert_types(id);


--
-- TOC entry 4599 (class 2606 OID 17179)
-- Name: system_audit_exceptions_system_exceptions_id_fkey; Type: FK CONSTRAINT; Schema: elr; Owner: droolsuser
--

ALTER TABLE ONLY system_audit_exceptions
    ADD CONSTRAINT system_audit_exceptions_system_exceptions_id_fkey FOREIGN KEY (system_exceptions_id) REFERENCES system_exceptions(id);


--
-- TOC entry 4600 (class 2606 OID 17184)
-- Name: system_audit_exceptions_system_messages_audits_id_fkey; Type: FK CONSTRAINT; Schema: elr; Owner: droolsuser
--

ALTER TABLE ONLY system_audit_exceptions
  ADD CONSTRAINT system_audit_exceptions_system_messages_audits_id_fkey FOREIGN KEY (system_messages_audits_id)
      REFERENCES system_messages_audits (id) MATCH SIMPLE
      ON UPDATE NO ACTION ON DELETE CASCADE;


--
-- TOC entry 4601 (class 2606 OID 17189)
-- Name: system_message_actions_action_category_id_fkey; Type: FK CONSTRAINT; Schema: elr; Owner: droolsuser
--

ALTER TABLE ONLY system_message_actions
    ADD CONSTRAINT system_message_actions_action_category_id_fkey FOREIGN KEY (action_category_id) REFERENCES system_action_categories(id);


--
-- TOC entry 4602 (class 2606 OID 17194)
-- Name: system_message_exceptions_exception_id_fkey; Type: FK CONSTRAINT; Schema: elr; Owner: droolsuser
--

ALTER TABLE ONLY system_message_exceptions
    ADD CONSTRAINT system_message_exceptions_exception_id_fkey FOREIGN KEY (exception_id) REFERENCES system_exceptions(id);


-- Foreign Key: system_message_exceptions_system_message_id_fkey

ALTER TABLE ONLY system_message_exceptions
  ADD CONSTRAINT system_message_exceptions_system_message_id_fkey FOREIGN KEY (system_message_id)
      REFERENCES system_messages (id) MATCH SIMPLE
      ON UPDATE NO ACTION ON DELETE CASCADE;


--
-- TOC entry 4604 (class 2606 OID 17204)
-- Name: system_messages_lab_id_fkey; Type: FK CONSTRAINT; Schema: elr; Owner: droolsuser
--

ALTER TABLE ONLY system_messages
    ADD CONSTRAINT system_messages_lab_id_fkey FOREIGN KEY (lab_id) REFERENCES structure_labs(id);


--
-- TOC entry 4605 (class 2606 OID 17209)
-- Name: system_messages_original_message_id_fkey; Type: FK CONSTRAINT; Schema: elr; Owner: droolsuser
--

ALTER TABLE ONLY system_messages
    ADD CONSTRAINT system_messages_original_message_id_fkey FOREIGN KEY (original_message_id) REFERENCES system_original_messages(id);


--
-- TOC entry 4615 (class 2606 OID 17214)
-- Name: system_messages_original_message_id_fkey; Type: FK CONSTRAINT; Schema: elr; Owner: dcpadmin
--

ALTER TABLE ONLY master_xml_flat
    ADD CONSTRAINT system_messages_original_message_id_fkey FOREIGN KEY (system_messages_id) REFERENCES system_messages(id) ON DELETE SET NULL;


--
-- TOC entry 4606 (class 2606 OID 28514263)
-- Name: system_messages_vocab_app_id_fkey; Type: FK CONSTRAINT; Schema: elr; Owner: droolsuser
--

ALTER TABLE ONLY system_messages
    ADD CONSTRAINT system_messages_vocab_app_id_fkey FOREIGN KEY (vocab_app_id) REFERENCES vocab_app(id);


--
-- TOC entry 4607 (class 2606 OID 17219)
-- Name: system_nedss_xml_audits_system_messages_audits_id_fkey; Type: FK CONSTRAINT; Schema: elr; Owner: droolsuser
--

ALTER TABLE ONLY system_nedss_xml_audits
  ADD CONSTRAINT system_nedss_xml_audits_system_messages_audits_id_fkey FOREIGN KEY (system_messages_audits_id)
      REFERENCES system_messages_audits (id) MATCH SIMPLE
      ON UPDATE NO ACTION ON DELETE CASCADE;


--
-- TOC entry 4608 (class 2606 OID 17224)
-- Name: system_zip_codes_system_district_id_fkey; Type: FK CONSTRAINT; Schema: elr; Owner: droolsuser
--

ALTER TABLE ONLY system_zip_codes
    ADD CONSTRAINT system_zip_codes_system_district_id_fkey FOREIGN KEY (system_district_id) REFERENCES system_districts(id);


--
-- TOC entry 4617 (class 2606 OID 17229)
-- Name: system_zip_to_small_area_small_area_id_fkey; Type: FK CONSTRAINT; Schema: elr; Owner: droolsuser
--

ALTER TABLE ONLY system_zip_to_small_area
    ADD CONSTRAINT system_zip_to_small_area_small_area_id_fkey FOREIGN KEY (small_area_id) REFERENCES system_small_areas(id);


--
-- TOC entry 4609 (class 2606 OID 17234)
-- Name: vocab_c2m_testresult_app_id_fkey; Type: FK CONSTRAINT; Schema: elr; Owner: droolsuser
--

ALTER TABLE ONLY vocab_c2m_testresult
    ADD CONSTRAINT vocab_c2m_testresult_app_id_fkey FOREIGN KEY (app_id) REFERENCES vocab_app(id);


--
-- TOC entry 4610 (class 2606 OID 17239)
-- Name: vocab_c2m_testresult_child_loinc_id_fkey; Type: FK CONSTRAINT; Schema: elr; Owner: droolsuser
--

ALTER TABLE ONLY vocab_c2m_testresult
    ADD CONSTRAINT vocab_c2m_testresult_child_loinc_id_fkey FOREIGN KEY (child_loinc_id) REFERENCES vocab_child_loinc(id) ON UPDATE NO ACTION ON DELETE CASCADE;


--
-- TOC entry 4611 (class 2606 OID 17244)
-- Name: vocab_c2m_testresult_master_id_fkey; Type: FK CONSTRAINT; Schema: elr; Owner: droolsuser
--

ALTER TABLE ONLY vocab_c2m_testresult
    ADD CONSTRAINT vocab_c2m_testresult_master_id_fkey FOREIGN KEY (master_id) REFERENCES vocab_master_vocab(id);


--
-- TOC entry 4620 (class 2606 OID 27995426)
-- Name: vocab_child_codeset_master_codeset_id_fkey; Type: FK CONSTRAINT; Schema: elr; Owner: droolsuser
--

ALTER TABLE ONLY vocab_child_codeset
    ADD CONSTRAINT vocab_child_codeset_master_codeset_id_fkey FOREIGN KEY (master_codeset_id) REFERENCES vocab_codeset(id);


--
-- TOC entry 4619 (class 2606 OID 27995431)
-- Name: vocab_child_codeset_structure_labs_id_fkey; Type: FK CONSTRAINT; Schema: elr; Owner: droolsuser
--

ALTER TABLE ONLY vocab_child_codeset
    ADD CONSTRAINT vocab_child_codeset_structure_labs_id_fkey FOREIGN KEY (structure_labs_id) REFERENCES structure_labs(id) ON UPDATE NO ACTION ON DELETE CASCADE;


--
-- TOC entry 4612 (class 2606 OID 17249)
-- Name: vocab_child_vocab_lab_id_fkey; Type: FK CONSTRAINT; Schema: elr; Owner: droolsuser
--

ALTER TABLE ONLY vocab_child_vocab
    ADD CONSTRAINT vocab_child_vocab_lab_id_fkey FOREIGN KEY (lab_id) REFERENCES structure_labs(id) ON UPDATE NO ACTION ON DELETE CASCADE;


--
-- TOC entry 4613 (class 2606 OID 17254)
-- Name: vocab_child_vocab_master_id_fkey; Type: FK CONSTRAINT; Schema: elr; Owner: droolsuser
--

ALTER TABLE ONLY vocab_child_vocab
    ADD CONSTRAINT vocab_child_vocab_master_id_fkey FOREIGN KEY (master_id) REFERENCES vocab_master_vocab(id);


ALTER TABLE ONLY vocab_child_loinc
  ADD CONSTRAINT vocab_child_loinc_offscale_low_result_fkey FOREIGN KEY (offscale_low_result) REFERENCES vocab_master_vocab (id) ON UPDATE NO ACTION ON DELETE NO ACTION;

ALTER TABLE vocab_child_loinc
  ADD CONSTRAINT vocab_child_loinc_offscale_high_result_fkey FOREIGN KEY (offscale_high_result) REFERENCES vocab_master_vocab (id) ON UPDATE NO ACTION ON DELETE NO ACTION;

ALTER TABLE vocab_child_loinc
  ADD FOREIGN KEY (lab_id) REFERENCES structure_labs (id) ON UPDATE NO ACTION ON DELETE CASCADE;

ALTER TABLE vocab_child_organism
  ADD FOREIGN KEY (lab_id) REFERENCES structure_labs (id) ON UPDATE NO ACTION ON DELETE CASCADE;


-- Index: vocab_child_organism_lab_id_idx

-- DROP INDEX vocab_child_organism_lab_id_idx;

CREATE INDEX vocab_child_organism_lab_id_idx
  ON vocab_child_organism
  USING btree
  (lab_id);

-- Index: vocab_child_organism_organism_idx

-- DROP INDEX vocab_child_organism_organism_idx;

CREATE INDEX vocab_child_organism_organism_idx
  ON vocab_child_organism
  USING btree
  (organism);


--
-- TOC entry 4623 (class 2606 OID 27995448)
-- Name: vocab_icd_codeset_id_fkey; Type: FK CONSTRAINT; Schema: elr; Owner: droolsuser
--

ALTER TABLE ONLY vocab_icd
    ADD CONSTRAINT vocab_icd_codeset_id_fkey FOREIGN KEY (codeset_id) REFERENCES vocab_codeset(id);


--
-- TOC entry 4622 (class 2606 OID 27995453)
-- Name: vocab_icd_master_condition_id_fkey; Type: FK CONSTRAINT; Schema: elr; Owner: droolsuser
--

ALTER TABLE ONLY vocab_icd
    ADD CONSTRAINT vocab_icd_master_condition_id_fkey FOREIGN KEY (master_condition_id) REFERENCES vocab_master_condition(c_id);


--
-- TOC entry 4621 (class 2606 OID 27995458)
-- Name: vocab_icd_master_snomed_id_fkey; Type: FK CONSTRAINT; Schema: elr; Owner: droolsuser
--

ALTER TABLE ONLY vocab_icd
    ADD CONSTRAINT vocab_icd_master_snomed_id_fkey FOREIGN KEY (master_snomed_id) REFERENCES vocab_master_organism(o_id);


--
-- TOC entry 4582 (class 2606 OID 17259)
-- Name: vocab_master2app_app_id_fkey; Type: FK CONSTRAINT; Schema: elr; Owner: droolsuser
--

ALTER TABLE ONLY vocab_master2app
    ADD CONSTRAINT vocab_master2app_app_id_fkey FOREIGN KEY (app_id) REFERENCES vocab_app(id);


--
-- TOC entry 4583 (class 2606 OID 17264)
-- Name: vocab_master2app_master_id_fkey; Type: FK CONSTRAINT; Schema: elr; Owner: droolsuser
--

ALTER TABLE ONLY vocab_master2app
    ADD CONSTRAINT vocab_master2app_master_id_fkey FOREIGN KEY (master_id) REFERENCES vocab_master_vocab(id);


--
-- TOC entry 4614 (class 2606 OID 17269)
-- Name: vocab_master_vocab_category_id_fkey; Type: FK CONSTRAINT; Schema: elr; Owner: droolsuser
--

ALTER TABLE ONLY vocab_master_vocab
    ADD CONSTRAINT vocab_master_vocab_category_id_fkey FOREIGN KEY (category) REFERENCES structure_category(id);


--
-- TOC entry 4618 (class 2606 OID 27867174)
-- Name: vocab_pfge_master_snomed_id_fkey; Type: FK CONSTRAINT; Schema: elr; Owner: droolsuser
--

ALTER TABLE ONLY vocab_pfge
    ADD CONSTRAINT vocab_pfge_master_snomed_id_fkey FOREIGN KEY (master_snomed_id) REFERENCES vocab_master_organism(o_id);




--
-- TOC entry 4790 (class 0 OID 0)
-- Dependencies: 212
-- Name: gateway_channels; Type: ACL; Schema: elr; Owner: droolsuser
--

REVOKE ALL ON TABLE gateway_channels FROM PUBLIC;
REVOKE ALL ON TABLE gateway_channels FROM droolsuser;
GRANT ALL ON TABLE gateway_channels TO droolsuser;
GRANT SELECT,INSERT,DELETE,UPDATE ON TABLE gateway_channels TO "elr-rw";
GRANT SELECT ON TABLE gateway_channels TO "elr-ro";


--
-- TOC entry 4792 (class 0 OID 0)
-- Dependencies: 213
-- Name: gateway_channels_id_seq; Type: ACL; Schema: elr; Owner: droolsuser
--

REVOKE ALL ON SEQUENCE gateway_channels_id_seq FROM PUBLIC;
REVOKE ALL ON SEQUENCE gateway_channels_id_seq FROM droolsuser;
GRANT ALL ON SEQUENCE gateway_channels_id_seq TO droolsuser;
GRANT SELECT ON SEQUENCE gateway_channels_id_seq TO "elr-ro";


--
-- TOC entry 4793 (class 0 OID 0)
-- Dependencies: 214
-- Name: gateway_exceptions; Type: ACL; Schema: elr; Owner: droolsuser
--

REVOKE ALL ON TABLE gateway_exceptions FROM PUBLIC;
REVOKE ALL ON TABLE gateway_exceptions FROM droolsuser;
GRANT ALL ON TABLE gateway_exceptions TO droolsuser;
GRANT SELECT,INSERT,DELETE,UPDATE ON TABLE gateway_exceptions TO "elr-rw";
GRANT SELECT ON TABLE gateway_exceptions TO "elr-ro";


--
-- TOC entry 4795 (class 0 OID 0)
-- Dependencies: 215
-- Name: gateway_exceptions_id_seq; Type: ACL; Schema: elr; Owner: droolsuser
--

REVOKE ALL ON SEQUENCE gateway_exceptions_id_seq FROM PUBLIC;
REVOKE ALL ON SEQUENCE gateway_exceptions_id_seq FROM droolsuser;
GRANT ALL ON SEQUENCE gateway_exceptions_id_seq TO droolsuser;
GRANT SELECT ON SEQUENCE gateway_exceptions_id_seq TO "elr-ro";


--
-- TOC entry 4796 (class 0 OID 0)
-- Dependencies: 216
-- Name: gateway_requests; Type: ACL; Schema: elr; Owner: droolsuser
--

REVOKE ALL ON TABLE gateway_requests FROM PUBLIC;
REVOKE ALL ON TABLE gateway_requests FROM droolsuser;
GRANT ALL ON TABLE gateway_requests TO droolsuser;
GRANT SELECT,INSERT,DELETE,UPDATE ON TABLE gateway_requests TO "elr-rw";
GRANT SELECT ON TABLE gateway_requests TO "elr-ro";


--
-- TOC entry 4798 (class 0 OID 0)
-- Dependencies: 217
-- Name: gateway_requests_id_seq; Type: ACL; Schema: elr; Owner: droolsuser
--

REVOKE ALL ON SEQUENCE gateway_requests_id_seq FROM PUBLIC;
REVOKE ALL ON SEQUENCE gateway_requests_id_seq FROM droolsuser;
GRANT ALL ON SEQUENCE gateway_requests_id_seq TO droolsuser;
GRANT SELECT ON SEQUENCE gateway_requests_id_seq TO "elr-ro";


--
-- TOC entry 4799 (class 0 OID 0)
-- Dependencies: 218
-- Name: gateway_users; Type: ACL; Schema: elr; Owner: droolsuser
--

REVOKE ALL ON TABLE gateway_users FROM PUBLIC;
REVOKE ALL ON TABLE gateway_users FROM droolsuser;
GRANT ALL ON TABLE gateway_users TO droolsuser;
GRANT SELECT,INSERT,DELETE,UPDATE ON TABLE gateway_users TO "elr-rw";
GRANT SELECT ON TABLE gateway_users TO "elr-ro";


--
-- TOC entry 4801 (class 0 OID 0)
-- Dependencies: 219
-- Name: gateway_users_id_seq; Type: ACL; Schema: elr; Owner: droolsuser
--

REVOKE ALL ON SEQUENCE gateway_users_id_seq FROM PUBLIC;
REVOKE ALL ON SEQUENCE gateway_users_id_seq FROM droolsuser;
GRANT ALL ON SEQUENCE gateway_users_id_seq TO droolsuser;
GRANT SELECT ON SEQUENCE gateway_users_id_seq TO "elr-ro";


--
-- TOC entry 4802 (class 0 OID 0)
-- Dependencies: 220
-- Name: gatewaytest_original_messages; Type: ACL; Schema: elr; Owner: droolsuser
--

REVOKE ALL ON TABLE gatewaytest_original_messages FROM PUBLIC;
REVOKE ALL ON TABLE gatewaytest_original_messages FROM droolsuser;
GRANT ALL ON TABLE gatewaytest_original_messages TO droolsuser;
GRANT ALL ON TABLE gatewaytest_original_messages TO "elr-rw";
GRANT SELECT ON TABLE gatewaytest_original_messages TO "elr-ro";


--
-- TOC entry 4804 (class 0 OID 0)
-- Dependencies: 221
-- Name: gatewaytest_original_messages_id_seq; Type: ACL; Schema: elr; Owner: droolsuser
--

REVOKE ALL ON SEQUENCE gatewaytest_original_messages_id_seq FROM PUBLIC;
REVOKE ALL ON SEQUENCE gatewaytest_original_messages_id_seq FROM droolsuser;
GRANT ALL ON SEQUENCE gatewaytest_original_messages_id_seq TO droolsuser;
GRANT SELECT ON SEQUENCE gatewaytest_original_messages_id_seq TO "elr-ro";


--
-- TOC entry 4805 (class 0 OID 0)
-- Dependencies: 222
-- Name: graylist_request_audits; Type: ACL; Schema: elr; Owner: droolsuser
--

REVOKE ALL ON TABLE graylist_request_audits FROM PUBLIC;
REVOKE ALL ON TABLE graylist_request_audits FROM droolsuser;
GRANT ALL ON TABLE graylist_request_audits TO droolsuser;
GRANT ALL ON TABLE graylist_request_audits TO "elr-rw";
GRANT SELECT ON TABLE graylist_request_audits TO "elr-ro";


--
-- TOC entry 4807 (class 0 OID 0)
-- Dependencies: 223
-- Name: graylist_request_audits_id_seq; Type: ACL; Schema: elr; Owner: droolsuser
--

REVOKE ALL ON SEQUENCE graylist_request_audits_id_seq FROM PUBLIC;
REVOKE ALL ON SEQUENCE graylist_request_audits_id_seq FROM droolsuser;
GRANT ALL ON SEQUENCE graylist_request_audits_id_seq TO droolsuser;
GRANT SELECT ON SEQUENCE graylist_request_audits_id_seq TO "elr-ro";


--
-- TOC entry 4808 (class 0 OID 0)
-- Dependencies: 224
-- Name: graylist_requests; Type: ACL; Schema: elr; Owner: droolsuser
--

REVOKE ALL ON TABLE graylist_requests FROM PUBLIC;
REVOKE ALL ON TABLE graylist_requests FROM droolsuser;
GRANT ALL ON TABLE graylist_requests TO droolsuser;
GRANT SELECT,INSERT,DELETE,UPDATE ON TABLE graylist_requests TO "elr-rw";
GRANT SELECT ON TABLE graylist_requests TO "elr-ro";


--
-- TOC entry 4810 (class 0 OID 0)
-- Dependencies: 225
-- Name: graylist_requests_id_seq; Type: ACL; Schema: elr; Owner: droolsuser
--

REVOKE ALL ON SEQUENCE graylist_requests_id_seq FROM PUBLIC;
REVOKE ALL ON SEQUENCE graylist_requests_id_seq FROM droolsuser;
GRANT ALL ON SEQUENCE graylist_requests_id_seq TO droolsuser;
GRANT SELECT ON SEQUENCE graylist_requests_id_seq TO "elr-ro";


--
-- TOC entry 4811 (class 0 OID 0)
-- Dependencies: 226
-- Name: graylist_spool; Type: ACL; Schema: elr; Owner: droolsuser
--

REVOKE ALL ON TABLE graylist_spool FROM PUBLIC;
REVOKE ALL ON TABLE graylist_spool FROM droolsuser;
GRANT ALL ON TABLE graylist_spool TO droolsuser;
GRANT SELECT,INSERT,DELETE,UPDATE ON TABLE graylist_spool TO "elr-rw";
GRANT SELECT ON TABLE graylist_spool TO "elr-ro";


--
-- TOC entry 4813 (class 0 OID 0)
-- Dependencies: 227
-- Name: graylist_spool_id_seq; Type: ACL; Schema: elr; Owner: droolsuser
--

REVOKE ALL ON SEQUENCE graylist_spool_id_seq FROM PUBLIC;
REVOKE ALL ON SEQUENCE graylist_spool_id_seq FROM droolsuser;
GRANT ALL ON SEQUENCE graylist_spool_id_seq TO droolsuser;
GRANT SELECT ON SEQUENCE graylist_spool_id_seq TO "elr-ro";


--
-- TOC entry 4814 (class 0 OID 0)
-- Dependencies: 228
-- Name: intake_stats_config; Type: ACL; Schema: elr; Owner: droolsuser
--

REVOKE ALL ON TABLE intake_stats_config FROM PUBLIC;
REVOKE ALL ON TABLE intake_stats_config FROM droolsuser;
GRANT ALL ON TABLE intake_stats_config TO droolsuser;
GRANT SELECT,INSERT,DELETE,UPDATE ON TABLE intake_stats_config TO "elr-rw";
GRANT SELECT ON TABLE intake_stats_config TO "elr-ro";


--
-- TOC entry 4816 (class 0 OID 0)
-- Dependencies: 229
-- Name: intake_stats_config_id_seq; Type: ACL; Schema: elr; Owner: droolsuser
--

REVOKE ALL ON SEQUENCE intake_stats_config_id_seq FROM PUBLIC;
REVOKE ALL ON SEQUENCE intake_stats_config_id_seq FROM droolsuser;
GRANT ALL ON SEQUENCE intake_stats_config_id_seq TO droolsuser;
GRANT SELECT ON SEQUENCE intake_stats_config_id_seq TO "elr-ro";


--
-- TOC entry 4817 (class 0 OID 0)
-- Dependencies: 230
-- Name: vocab_master2app; Type: ACL; Schema: elr; Owner: droolsuser
--

REVOKE ALL ON TABLE vocab_master2app FROM PUBLIC;
REVOKE ALL ON TABLE vocab_master2app FROM droolsuser;
GRANT ALL ON TABLE vocab_master2app TO droolsuser;
GRANT ALL ON TABLE vocab_master2app TO "elr-rw";
GRANT SELECT ON TABLE vocab_master2app TO "elr-ro";


--
-- TOC entry 4819 (class 0 OID 0)
-- Dependencies: 231
-- Name: master2app_id_seq; Type: ACL; Schema: elr; Owner: droolsuser
--

REVOKE ALL ON SEQUENCE master2app_id_seq FROM PUBLIC;
REVOKE ALL ON SEQUENCE master2app_id_seq FROM droolsuser;
GRANT ALL ON SEQUENCE master2app_id_seq TO droolsuser;
GRANT ALL ON SEQUENCE master2app_id_seq TO "elr-rw";
GRANT SELECT ON SEQUENCE master2app_id_seq TO "elr-ro";


--
-- TOC entry 4820 (class 0 OID 0)
-- Dependencies: 504
-- Name: master_xml_flat; Type: ACL; Schema: elr; Owner: dcpadmin
--

REVOKE ALL ON TABLE master_xml_flat FROM PUBLIC;
REVOKE ALL ON TABLE master_xml_flat FROM dcpadmin;
GRANT ALL ON TABLE master_xml_flat TO dcpadmin;
GRANT ALL ON TABLE master_xml_flat TO droolsuser;
GRANT ALL ON TABLE master_xml_flat TO "elr-rw";
GRANT SELECT ON TABLE master_xml_flat TO "elr-ro";


--
-- TOC entry 4822 (class 0 OID 0)
-- Dependencies: 503
-- Name: master_xml_flat_id_seq; Type: ACL; Schema: elr; Owner: dcpadmin
--

REVOKE ALL ON SEQUENCE master_xml_flat_id_seq FROM PUBLIC;
REVOKE ALL ON SEQUENCE master_xml_flat_id_seq FROM dcpadmin;
GRANT ALL ON SEQUENCE master_xml_flat_id_seq TO dcpadmin;
GRANT ALL ON SEQUENCE master_xml_flat_id_seq TO droolsuser;
GRANT ALL ON SEQUENCE master_xml_flat_id_seq TO "elr-rw";
GRANT SELECT ON SEQUENCE master_xml_flat_id_seq TO "elr-ro";


--
-- TOC entry 4825 (class 0 OID 0)
-- Dependencies: 596
-- Name: preprocessor_audit_exceptions; Type: ACL; Schema: elr; Owner: droolsuser
--

REVOKE ALL ON TABLE preprocessor_audit_exceptions FROM PUBLIC;
REVOKE ALL ON TABLE preprocessor_audit_exceptions FROM droolsuser;
GRANT ALL ON TABLE preprocessor_audit_exceptions TO droolsuser;
GRANT SELECT,INSERT,DELETE,UPDATE ON TABLE preprocessor_audit_exceptions TO "elr-rw";
GRANT SELECT ON TABLE preprocessor_audit_exceptions TO "elr-ro";


--
-- TOC entry 4827 (class 0 OID 0)
-- Dependencies: 594
-- Name: preprocessor_exceptions; Type: ACL; Schema: elr; Owner: droolsuser
--

REVOKE ALL ON TABLE preprocessor_exceptions FROM PUBLIC;
REVOKE ALL ON TABLE preprocessor_exceptions FROM droolsuser;
GRANT ALL ON TABLE preprocessor_exceptions TO droolsuser;
GRANT SELECT,INSERT,DELETE,UPDATE ON TABLE preprocessor_exceptions TO "elr-rw";
GRANT SELECT ON TABLE preprocessor_exceptions TO "elr-ro";


--
-- TOC entry 4829 (class 0 OID 0)
-- Dependencies: 573
-- Name: ss_batched_messages; Type: ACL; Schema: elr; Owner: droolsuser
--

REVOKE ALL ON TABLE ss_batched_messages FROM PUBLIC;
REVOKE ALL ON TABLE ss_batched_messages FROM droolsuser;
GRANT ALL ON TABLE ss_batched_messages TO droolsuser;
GRANT SELECT,INSERT,DELETE,UPDATE ON TABLE ss_batched_messages TO "elr-rw";
GRANT SELECT ON TABLE ss_batched_messages TO "elr-ro";


--
-- TOC entry 4831 (class 0 OID 0)
-- Dependencies: 233
-- Name: ss_biosense; Type: ACL; Schema: elr; Owner: droolsuser
--

REVOKE ALL ON TABLE ss_biosense FROM PUBLIC;
REVOKE ALL ON TABLE ss_biosense FROM droolsuser;
GRANT ALL ON TABLE ss_biosense TO droolsuser;
GRANT SELECT ON TABLE ss_biosense TO "elr-ro";


--
-- TOC entry 4833 (class 0 OID 0)
-- Dependencies: 234
-- Name: ss_biosense_id_seq; Type: ACL; Schema: elr; Owner: droolsuser
--

REVOKE ALL ON SEQUENCE ss_biosense_id_seq FROM PUBLIC;
REVOKE ALL ON SEQUENCE ss_biosense_id_seq FROM droolsuser;
GRANT ALL ON SEQUENCE ss_biosense_id_seq TO droolsuser;
GRANT SELECT ON SEQUENCE ss_biosense_id_seq TO "elr-ro";


--
-- TOC entry 4834 (class 0 OID 0)
-- Dependencies: 518
-- Name: ss_connectors; Type: ACL; Schema: elr; Owner: droolsuser
--

REVOKE ALL ON TABLE ss_connectors FROM PUBLIC;
REVOKE ALL ON TABLE ss_connectors FROM droolsuser;
GRANT ALL ON TABLE ss_connectors TO droolsuser;
GRANT SELECT,INSERT,DELETE,UPDATE ON TABLE ss_connectors TO "elr-rw";


--
-- TOC entry 4836 (class 0 OID 0)
-- Dependencies: 235
-- Name: ss_facility; Type: ACL; Schema: elr; Owner: droolsuser
--

REVOKE ALL ON TABLE ss_facility FROM PUBLIC;
REVOKE ALL ON TABLE ss_facility FROM droolsuser;
GRANT ALL ON TABLE ss_facility TO droolsuser;
GRANT SELECT ON TABLE ss_facility TO "elr-ro";


--
-- TOC entry 4838 (class 0 OID 0)
-- Dependencies: 236
-- Name: ss_facility_id_seq; Type: ACL; Schema: elr; Owner: droolsuser
--

REVOKE ALL ON SEQUENCE ss_facility_id_seq FROM PUBLIC;
REVOKE ALL ON SEQUENCE ss_facility_id_seq FROM droolsuser;
GRANT ALL ON SEQUENCE ss_facility_id_seq TO droolsuser;
GRANT SELECT ON SEQUENCE ss_facility_id_seq TO "elr-ro";


--
-- TOC entry 4839 (class 0 OID 0)
-- Dependencies: 239
-- Name: structure_category; Type: ACL; Schema: elr; Owner: droolsuser
--

REVOKE ALL ON TABLE structure_category FROM PUBLIC;
REVOKE ALL ON TABLE structure_category FROM droolsuser;
GRANT ALL ON TABLE structure_category TO droolsuser;
GRANT ALL ON TABLE structure_category TO "elr-rw";
GRANT SELECT ON TABLE structure_category TO "elr-ro";


--
-- TOC entry 4840 (class 0 OID 0)
-- Dependencies: 240
-- Name: structure_category_application; Type: ACL; Schema: elr; Owner: droolsuser
--

REVOKE ALL ON TABLE structure_category_application FROM PUBLIC;
REVOKE ALL ON TABLE structure_category_application FROM droolsuser;
GRANT ALL ON TABLE structure_category_application TO droolsuser;
GRANT ALL ON TABLE structure_category_application TO "elr-rw";
GRANT SELECT ON TABLE structure_category_application TO "elr-ro";


--
-- TOC entry 4842 (class 0 OID 0)
-- Dependencies: 241
-- Name: structure_category_application_id_seq; Type: ACL; Schema: elr; Owner: droolsuser
--

REVOKE ALL ON SEQUENCE structure_category_application_id_seq FROM PUBLIC;
REVOKE ALL ON SEQUENCE structure_category_application_id_seq FROM droolsuser;
GRANT ALL ON SEQUENCE structure_category_application_id_seq TO droolsuser;
GRANT ALL ON SEQUENCE structure_category_application_id_seq TO "elr-rw";
GRANT SELECT ON SEQUENCE structure_category_application_id_seq TO "elr-ro";


--
-- TOC entry 4844 (class 0 OID 0)
-- Dependencies: 242
-- Name: structure_category_id_seq; Type: ACL; Schema: elr; Owner: droolsuser
--

REVOKE ALL ON SEQUENCE structure_category_id_seq FROM PUBLIC;
REVOKE ALL ON SEQUENCE structure_category_id_seq FROM droolsuser;
GRANT ALL ON SEQUENCE structure_category_id_seq TO droolsuser;
GRANT ALL ON SEQUENCE structure_category_id_seq TO "elr-rw";
GRANT SELECT ON SEQUENCE structure_category_id_seq TO "elr-ro";


--
-- TOC entry 4845 (class 0 OID 0)
-- Dependencies: 243
-- Name: structure_data_type; Type: ACL; Schema: elr; Owner: droolsuser
--

REVOKE ALL ON TABLE structure_data_type FROM PUBLIC;
REVOKE ALL ON TABLE structure_data_type FROM droolsuser;
GRANT ALL ON TABLE structure_data_type TO droolsuser;
GRANT ALL ON TABLE structure_data_type TO "elr-rw";
GRANT SELECT ON TABLE structure_data_type TO "elr-ro";


--
-- TOC entry 4846 (class 0 OID 0)
-- Dependencies: 499
-- Name: structure_knittable_loincs; Type: ACL; Schema: elr; Owner: droolsuser
--

REVOKE ALL ON TABLE structure_knittable_loincs FROM PUBLIC;
REVOKE ALL ON TABLE structure_knittable_loincs FROM droolsuser;
GRANT ALL ON TABLE structure_knittable_loincs TO droolsuser;
GRANT SELECT,INSERT,DELETE,UPDATE ON TABLE structure_knittable_loincs TO "elr-rw";
GRANT SELECT ON TABLE structure_knittable_loincs TO "elr-ro";


--
-- TOC entry 4848 (class 0 OID 0)
-- Dependencies: 498
-- Name: structure_knittable_loincs_id_seq; Type: ACL; Schema: elr; Owner: droolsuser
--

REVOKE ALL ON SEQUENCE structure_knittable_loincs_id_seq FROM PUBLIC;
REVOKE ALL ON SEQUENCE structure_knittable_loincs_id_seq FROM droolsuser;
GRANT ALL ON SEQUENCE structure_knittable_loincs_id_seq TO droolsuser;
GRANT SELECT ON SEQUENCE structure_knittable_loincs_id_seq TO "elr-ro";


--
-- TOC entry 4849 (class 0 OID 0)
-- Dependencies: 244
-- Name: structure_labs; Type: ACL; Schema: elr; Owner: droolsuser
--

REVOKE ALL ON TABLE structure_labs FROM PUBLIC;
REVOKE ALL ON TABLE structure_labs FROM droolsuser;
GRANT ALL ON TABLE structure_labs TO droolsuser;
GRANT ALL ON TABLE structure_labs TO "elr-rw";
GRANT SELECT ON TABLE structure_labs TO "elr-ro";


--
-- TOC entry 4852 (class 0 OID 0)
-- Dependencies: 246
-- Name: structure_lookup_operator; Type: ACL; Schema: elr; Owner: droolsuser
--

REVOKE ALL ON TABLE structure_lookup_operator FROM PUBLIC;
REVOKE ALL ON TABLE structure_lookup_operator FROM droolsuser;
GRANT ALL ON TABLE structure_lookup_operator TO droolsuser;
GRANT ALL ON TABLE structure_lookup_operator TO "elr-rw";
GRANT SELECT ON TABLE structure_lookup_operator TO "elr-ro";


--
-- TOC entry 4854 (class 0 OID 0)
-- Dependencies: 247
-- Name: structure_lookup_operator_id_seq; Type: ACL; Schema: elr; Owner: droolsuser
--

REVOKE ALL ON SEQUENCE structure_lookup_operator_id_seq FROM PUBLIC;
REVOKE ALL ON SEQUENCE structure_lookup_operator_id_seq FROM droolsuser;
GRANT ALL ON SEQUENCE structure_lookup_operator_id_seq TO droolsuser;
GRANT ALL ON SEQUENCE structure_lookup_operator_id_seq TO "elr-rw";
GRANT SELECT ON SEQUENCE structure_lookup_operator_id_seq TO "elr-ro";


--
-- TOC entry 4855 (class 0 OID 0)
-- Dependencies: 248
-- Name: structure_operand_type; Type: ACL; Schema: elr; Owner: droolsuser
--

REVOKE ALL ON TABLE structure_operand_type FROM PUBLIC;
REVOKE ALL ON TABLE structure_operand_type FROM droolsuser;
GRANT ALL ON TABLE structure_operand_type TO droolsuser;
GRANT ALL ON TABLE structure_operand_type TO "elr-rw";
GRANT SELECT ON TABLE structure_operand_type TO "elr-ro";


--
-- TOC entry 4856 (class 0 OID 0)
-- Dependencies: 249
-- Name: structure_operator; Type: ACL; Schema: elr; Owner: droolsuser
--

REVOKE ALL ON TABLE structure_operator FROM PUBLIC;
REVOKE ALL ON TABLE structure_operator FROM droolsuser;
GRANT ALL ON TABLE structure_operator TO droolsuser;
GRANT ALL ON TABLE structure_operator TO "elr-rw";
GRANT SELECT ON TABLE structure_operator TO "elr-ro";


--
-- TOC entry 4857 (class 0 OID 0)
-- Dependencies: 251
-- Name: structure_path; Type: ACL; Schema: elr; Owner: droolsuser
--

REVOKE ALL ON TABLE structure_path FROM PUBLIC;
REVOKE ALL ON TABLE structure_path FROM droolsuser;
GRANT ALL ON TABLE structure_path TO droolsuser;
GRANT ALL ON TABLE structure_path TO "elr-rw";
GRANT SELECT ON TABLE structure_path TO "elr-ro";



REVOKE ALL ON SEQUENCE structure_path_id FROM PUBLIC;
REVOKE ALL ON SEQUENCE structure_path_id FROM droolsuser;
GRANT ALL ON SEQUENCE structure_path_id TO droolsuser;
GRANT ALL ON SEQUENCE structure_path_id TO "elr-rw";
GRANT SELECT ON SEQUENCE structure_path_id TO "elr-ro";

REVOKE ALL ON SEQUENCE structure_path_rule_id FROM PUBLIC;
REVOKE ALL ON SEQUENCE structure_path_rule_id FROM droolsuser;
GRANT ALL ON SEQUENCE structure_path_rule_id TO droolsuser;
GRANT ALL ON SEQUENCE structure_path_rule_id TO "elr-rw";
GRANT SELECT ON SEQUENCE structure_path_rule_id TO "elr-ro";

--
-- TOC entry 4858 (class 0 OID 0)
-- Dependencies: 252
-- Name: structure_path_application; Type: ACL; Schema: elr; Owner: droolsuser
--

REVOKE ALL ON TABLE structure_path_application FROM PUBLIC;
REVOKE ALL ON TABLE structure_path_application FROM droolsuser;
GRANT ALL ON TABLE structure_path_application TO droolsuser;
GRANT ALL ON TABLE structure_path_application TO "elr-rw";
GRANT SELECT ON TABLE structure_path_application TO "elr-ro";


--
-- TOC entry 4860 (class 0 OID 0)
-- Dependencies: 253
-- Name: structure_path_application_id_seq; Type: ACL; Schema: elr; Owner: droolsuser
--

REVOKE ALL ON SEQUENCE structure_path_application_id_seq FROM PUBLIC;
REVOKE ALL ON SEQUENCE structure_path_application_id_seq FROM droolsuser;
GRANT ALL ON SEQUENCE structure_path_application_id_seq TO droolsuser;
GRANT ALL ON SEQUENCE structure_path_application_id_seq TO "elr-rw";
GRANT SELECT ON SEQUENCE structure_path_application_id_seq TO "elr-ro";


--
-- TOC entry 4864 (class 0 OID 0)
-- Dependencies: 256
-- Name: structure_path_mirth; Type: ACL; Schema: elr; Owner: droolsuser
--

REVOKE ALL ON TABLE structure_path_mirth FROM PUBLIC;
REVOKE ALL ON TABLE structure_path_mirth FROM droolsuser;
GRANT ALL ON TABLE structure_path_mirth TO droolsuser;
GRANT ALL ON TABLE structure_path_mirth TO "elr-rw";
GRANT SELECT ON TABLE structure_path_mirth TO "elr-ro";


--
-- TOC entry 4866 (class 0 OID 0)
-- Dependencies: 257
-- Name: structure_path_mirth_id_seq; Type: ACL; Schema: elr; Owner: droolsuser
--

REVOKE ALL ON SEQUENCE structure_path_mirth_id_seq FROM PUBLIC;
REVOKE ALL ON SEQUENCE structure_path_mirth_id_seq FROM droolsuser;
GRANT ALL ON SEQUENCE structure_path_mirth_id_seq TO droolsuser;
GRANT ALL ON SEQUENCE structure_path_mirth_id_seq TO "elr-rw";
GRANT SELECT ON SEQUENCE structure_path_mirth_id_seq TO "elr-ro";


--
-- TOC entry 4867 (class 0 OID 0)
-- Dependencies: 259
-- Name: structure_path_rule; Type: ACL; Schema: elr; Owner: droolsuser
--

REVOKE ALL ON TABLE structure_path_rule FROM PUBLIC;
REVOKE ALL ON TABLE structure_path_rule FROM droolsuser;
GRANT ALL ON TABLE structure_path_rule TO droolsuser;
GRANT ALL ON TABLE structure_path_rule TO "elr-rw";
GRANT SELECT ON TABLE structure_path_rule TO "elr-ro";


--
-- TOC entry 4868 (class 0 OID 0)
-- Dependencies: 260
-- Name: system_action_categories; Type: ACL; Schema: elr; Owner: droolsuser
--

REVOKE ALL ON TABLE system_action_categories FROM PUBLIC;
REVOKE ALL ON TABLE system_action_categories FROM droolsuser;
GRANT ALL ON TABLE system_action_categories TO droolsuser;
GRANT ALL ON TABLE system_action_categories TO "elr-rw";
GRANT SELECT ON TABLE system_action_categories TO "elr-ro";


--
-- TOC entry 4870 (class 0 OID 0)
-- Dependencies: 261
-- Name: system_action_categories_id_seq; Type: ACL; Schema: elr; Owner: droolsuser
--

REVOKE ALL ON SEQUENCE system_action_categories_id_seq FROM PUBLIC;
REVOKE ALL ON SEQUENCE system_action_categories_id_seq FROM droolsuser;
GRANT ALL ON SEQUENCE system_action_categories_id_seq TO droolsuser;
GRANT ALL ON SEQUENCE system_action_categories_id_seq TO "elr-rw";
GRANT SELECT ON SEQUENCE system_action_categories_id_seq TO "elr-ro";


--
-- TOC entry 4871 (class 0 OID 0)
-- Dependencies: 506
-- Name: system_alert_types; Type: ACL; Schema: elr; Owner: droolsuser
--

REVOKE ALL ON TABLE system_alert_types FROM PUBLIC;
REVOKE ALL ON TABLE system_alert_types FROM droolsuser;
GRANT ALL ON TABLE system_alert_types TO droolsuser;
GRANT SELECT,INSERT,DELETE,UPDATE ON TABLE system_alert_types TO "elr-rw";


--
-- TOC entry 4873 (class 0 OID 0)
-- Dependencies: 508
-- Name: system_alerts; Type: ACL; Schema: elr; Owner: droolsuser
--

REVOKE ALL ON TABLE system_alerts FROM PUBLIC;
REVOKE ALL ON TABLE system_alerts FROM droolsuser;
GRANT ALL ON TABLE system_alerts TO droolsuser;
GRANT SELECT,INSERT,DELETE,UPDATE ON TABLE system_alerts TO "elr-rw";


--
-- TOC entry 4875 (class 0 OID 0)
-- Dependencies: 262
-- Name: system_audit_exceptions; Type: ACL; Schema: elr; Owner: droolsuser
--

REVOKE ALL ON TABLE system_audit_exceptions FROM PUBLIC;
REVOKE ALL ON TABLE system_audit_exceptions FROM droolsuser;
GRANT ALL ON TABLE system_audit_exceptions TO droolsuser;
GRANT ALL ON TABLE system_audit_exceptions TO "elr-rw";
GRANT SELECT ON TABLE system_audit_exceptions TO "elr-ro";


--
-- TOC entry 4877 (class 0 OID 0)
-- Dependencies: 263
-- Name: system_audit_exceptions_id_seq; Type: ACL; Schema: elr; Owner: droolsuser
--

REVOKE ALL ON SEQUENCE system_audit_exceptions_id_seq FROM PUBLIC;
REVOKE ALL ON SEQUENCE system_audit_exceptions_id_seq FROM droolsuser;
GRANT ALL ON SEQUENCE system_audit_exceptions_id_seq TO droolsuser;
GRANT ALL ON SEQUENCE system_audit_exceptions_id_seq TO "elr-rw";
GRANT SELECT ON SEQUENCE system_audit_exceptions_id_seq TO "elr-ro";


--
-- TOC entry 4878 (class 0 OID 0)
-- Dependencies: 264
-- Name: system_districts; Type: ACL; Schema: elr; Owner: droolsuser
--

REVOKE ALL ON TABLE system_districts FROM PUBLIC;
REVOKE ALL ON TABLE system_districts FROM droolsuser;
GRANT ALL ON TABLE system_districts TO droolsuser;
GRANT ALL ON TABLE system_districts TO "elr-rw";
GRANT SELECT ON TABLE system_districts TO "elr-ro";


--
-- TOC entry 4881 (class 0 OID 0)
-- Dependencies: 266
-- Name: system_exceptions; Type: ACL; Schema: elr; Owner: droolsuser
--

REVOKE ALL ON TABLE system_exceptions FROM PUBLIC;
REVOKE ALL ON TABLE system_exceptions FROM droolsuser;
GRANT ALL ON TABLE system_exceptions TO droolsuser;
GRANT ALL ON TABLE system_exceptions TO "elr-rw";
GRANT SELECT ON TABLE system_exceptions TO "elr-ro";


--
-- TOC entry 4883 (class 0 OID 0)
-- Dependencies: 267
-- Name: system_exceptions_id_seq; Type: ACL; Schema: elr; Owner: droolsuser
--

REVOKE ALL ON SEQUENCE system_exceptions_id_seq FROM PUBLIC;
REVOKE ALL ON SEQUENCE system_exceptions_id_seq FROM droolsuser;
GRANT ALL ON SEQUENCE system_exceptions_id_seq TO droolsuser;
GRANT ALL ON SEQUENCE system_exceptions_id_seq TO "elr-rw";
GRANT SELECT ON SEQUENCE system_exceptions_id_seq TO "elr-ro";


--
-- TOC entry 4884 (class 0 OID 0)
-- Dependencies: 269
-- Name: system_menus; Type: ACL; Schema: elr; Owner: droolsuser
--

REVOKE ALL ON TABLE system_menus FROM PUBLIC;
REVOKE ALL ON TABLE system_menus FROM droolsuser;
GRANT ALL ON TABLE system_menus TO droolsuser;
GRANT ALL ON TABLE system_menus TO "elr-rw";
GRANT ALL ON TABLE system_menus TO droolsuser;
GRANT SELECT ON TABLE system_menus TO "elr-ro";


--
-- TOC entry 4886 (class 0 OID 0)
-- Dependencies: 270
-- Name: system_menus_id_seq; Type: ACL; Schema: elr; Owner: droolsuser
--

REVOKE ALL ON SEQUENCE system_menus_id_seq FROM PUBLIC;
REVOKE ALL ON SEQUENCE system_menus_id_seq FROM droolsuser;
GRANT ALL ON SEQUENCE system_menus_id_seq TO droolsuser;
GRANT ALL ON SEQUENCE system_menus_id_seq TO "elr-rw";
GRANT ALL ON SEQUENCE system_menus_id_seq TO droolsuser;
GRANT SELECT ON SEQUENCE system_menus_id_seq TO "elr-ro";


--
-- TOC entry 4887 (class 0 OID 0)
-- Dependencies: 271
-- Name: system_message_actions; Type: ACL; Schema: elr; Owner: droolsuser
--

REVOKE ALL ON TABLE system_message_actions FROM PUBLIC;
REVOKE ALL ON TABLE system_message_actions FROM droolsuser;
GRANT ALL ON TABLE system_message_actions TO droolsuser;
GRANT ALL ON TABLE system_message_actions TO "elr-rw";
GRANT SELECT ON TABLE system_message_actions TO "elr-ro";


--
-- TOC entry 4889 (class 0 OID 0)
-- Dependencies: 272
-- Name: system_message_actions_id_seq; Type: ACL; Schema: elr; Owner: droolsuser
--

REVOKE ALL ON SEQUENCE system_message_actions_id_seq FROM PUBLIC;
REVOKE ALL ON SEQUENCE system_message_actions_id_seq FROM droolsuser;
GRANT ALL ON SEQUENCE system_message_actions_id_seq TO droolsuser;
GRANT ALL ON SEQUENCE system_message_actions_id_seq TO "elr-rw";
GRANT SELECT ON SEQUENCE system_message_actions_id_seq TO "elr-ro";


--
-- TOC entry 4890 (class 0 OID 0)
-- Dependencies: 273
-- Name: system_message_comments; Type: ACL; Schema: elr; Owner: droolsuser
--

REVOKE ALL ON TABLE system_message_comments FROM PUBLIC;
REVOKE ALL ON TABLE system_message_comments FROM droolsuser;
GRANT ALL ON TABLE system_message_comments TO droolsuser;
GRANT SELECT,INSERT,DELETE,UPDATE ON TABLE system_message_comments TO "elr-rw";
GRANT SELECT ON TABLE system_message_comments TO "elr-ro";


--
-- TOC entry 4892 (class 0 OID 0)
-- Dependencies: 274
-- Name: system_message_comments_id_seq; Type: ACL; Schema: elr; Owner: droolsuser
--

REVOKE ALL ON SEQUENCE system_message_comments_id_seq FROM PUBLIC;
REVOKE ALL ON SEQUENCE system_message_comments_id_seq FROM droolsuser;
GRANT ALL ON SEQUENCE system_message_comments_id_seq TO droolsuser;
GRANT SELECT,UPDATE ON SEQUENCE system_message_comments_id_seq TO "elr-rw";
GRANT SELECT ON SEQUENCE system_message_comments_id_seq TO "elr-ro";


--
-- TOC entry 4893 (class 0 OID 0)
-- Dependencies: 275
-- Name: system_message_exceptions; Type: ACL; Schema: elr; Owner: droolsuser
--

REVOKE ALL ON TABLE system_message_exceptions FROM PUBLIC;
REVOKE ALL ON TABLE system_message_exceptions FROM droolsuser;
GRANT ALL ON TABLE system_message_exceptions TO droolsuser;
GRANT ALL ON TABLE system_message_exceptions TO "elr-rw";
GRANT SELECT ON TABLE system_message_exceptions TO "elr-ro";


--
-- TOC entry 4895 (class 0 OID 0)
-- Dependencies: 276
-- Name: system_message_exceptions_id_seq; Type: ACL; Schema: elr; Owner: droolsuser
--

REVOKE ALL ON SEQUENCE system_message_exceptions_id_seq FROM PUBLIC;
REVOKE ALL ON SEQUENCE system_message_exceptions_id_seq FROM droolsuser;
GRANT ALL ON SEQUENCE system_message_exceptions_id_seq TO droolsuser;
GRANT ALL ON SEQUENCE system_message_exceptions_id_seq TO "elr-rw";
GRANT SELECT ON SEQUENCE system_message_exceptions_id_seq TO "elr-ro";


--
-- TOC entry 4896 (class 0 OID 0)
-- Dependencies: 277
-- Name: system_message_flag_comments; Type: ACL; Schema: elr; Owner: droolsuser
--

REVOKE ALL ON TABLE system_message_flag_comments FROM PUBLIC;
REVOKE ALL ON TABLE system_message_flag_comments FROM droolsuser;
GRANT ALL ON TABLE system_message_flag_comments TO droolsuser;
GRANT SELECT ON TABLE system_message_flag_comments TO "elr-ro";


--
-- TOC entry 4898 (class 0 OID 0)
-- Dependencies: 278
-- Name: system_message_flag_comments_id_seq; Type: ACL; Schema: elr; Owner: droolsuser
--

REVOKE ALL ON SEQUENCE system_message_flag_comments_id_seq FROM PUBLIC;
REVOKE ALL ON SEQUENCE system_message_flag_comments_id_seq FROM droolsuser;
GRANT ALL ON SEQUENCE system_message_flag_comments_id_seq TO droolsuser;
GRANT SELECT ON SEQUENCE system_message_flag_comments_id_seq TO "elr-ro";


--
-- TOC entry 4899 (class 0 OID 0)
-- Dependencies: 279
-- Name: system_message_flags; Type: ACL; Schema: elr; Owner: droolsuser
--

REVOKE ALL ON TABLE system_message_flags FROM PUBLIC;
REVOKE ALL ON TABLE system_message_flags FROM droolsuser;
GRANT ALL ON TABLE system_message_flags TO droolsuser;
GRANT SELECT ON TABLE system_message_flags TO "elr-ro";


--
-- TOC entry 4901 (class 0 OID 0)
-- Dependencies: 280
-- Name: system_message_flags_id_seq; Type: ACL; Schema: elr; Owner: droolsuser
--

REVOKE ALL ON SEQUENCE system_message_flags_id_seq FROM PUBLIC;
REVOKE ALL ON SEQUENCE system_message_flags_id_seq FROM droolsuser;
GRANT ALL ON SEQUENCE system_message_flags_id_seq TO droolsuser;
GRANT SELECT ON SEQUENCE system_message_flags_id_seq TO "elr-ro";


--
-- TOC entry 4902 (class 0 OID 0)
-- Dependencies: 281
-- Name: system_messages; Type: ACL; Schema: elr; Owner: droolsuser
--

REVOKE ALL ON TABLE system_messages FROM PUBLIC;
REVOKE ALL ON TABLE system_messages FROM droolsuser;
GRANT ALL ON TABLE system_messages TO droolsuser;
GRANT ALL ON TABLE system_messages TO "elr-rw";
GRANT SELECT ON TABLE system_messages TO "elr-ro";


--
-- TOC entry 4903 (class 0 OID 0)
-- Dependencies: 282
-- Name: system_messages_audits; Type: ACL; Schema: elr; Owner: droolsuser
--

REVOKE ALL ON TABLE system_messages_audits FROM PUBLIC;
REVOKE ALL ON TABLE system_messages_audits FROM droolsuser;
GRANT ALL ON TABLE system_messages_audits TO droolsuser;
GRANT ALL ON TABLE system_messages_audits TO "elr-rw";
GRANT SELECT ON TABLE system_messages_audits TO "elr-ro";


--
-- TOC entry 4905 (class 0 OID 0)
-- Dependencies: 283
-- Name: system_messages_audits_id_seq; Type: ACL; Schema: elr; Owner: droolsuser
--

REVOKE ALL ON SEQUENCE system_messages_audits_id_seq FROM PUBLIC;
REVOKE ALL ON SEQUENCE system_messages_audits_id_seq FROM droolsuser;
GRANT ALL ON SEQUENCE system_messages_audits_id_seq TO droolsuser;
GRANT ALL ON SEQUENCE system_messages_audits_id_seq TO "elr-rw";
GRANT SELECT ON SEQUENCE system_messages_audits_id_seq TO "elr-ro";


--
-- TOC entry 4910 (class 0 OID 0)
-- Dependencies: 285
-- Name: system_nedss_xml_audits; Type: ACL; Schema: elr; Owner: droolsuser
--

REVOKE ALL ON TABLE system_nedss_xml_audits FROM PUBLIC;
REVOKE ALL ON TABLE system_nedss_xml_audits FROM droolsuser;
GRANT ALL ON TABLE system_nedss_xml_audits TO droolsuser;
GRANT SELECT,INSERT,DELETE,UPDATE ON TABLE system_nedss_xml_audits TO "elr-rw";
GRANT SELECT ON TABLE system_nedss_xml_audits TO "elr-ro";


--
-- TOC entry 4912 (class 0 OID 0)
-- Dependencies: 286
-- Name: system_nedss_xml_audits_id_seq; Type: ACL; Schema: elr; Owner: droolsuser
--

REVOKE ALL ON SEQUENCE system_nedss_xml_audits_id_seq FROM PUBLIC;
REVOKE ALL ON SEQUENCE system_nedss_xml_audits_id_seq FROM droolsuser;
GRANT ALL ON SEQUENCE system_nedss_xml_audits_id_seq TO droolsuser;
GRANT SELECT,UPDATE ON SEQUENCE system_nedss_xml_audits_id_seq TO "elr-rw";
GRANT SELECT ON SEQUENCE system_nedss_xml_audits_id_seq TO "elr-ro";


--
-- TOC entry 4913 (class 0 OID 0)
-- Dependencies: 287
-- Name: system_original_messages; Type: ACL; Schema: elr; Owner: droolsuser
--

REVOKE ALL ON TABLE system_original_messages FROM PUBLIC;
REVOKE ALL ON TABLE system_original_messages FROM droolsuser;
GRANT ALL ON TABLE system_original_messages TO droolsuser;
GRANT ALL ON TABLE system_original_messages TO "elr-rw";
GRANT SELECT ON TABLE system_original_messages TO "elr-ro";


--
-- TOC entry 4915 (class 0 OID 0)
-- Dependencies: 288
-- Name: system_original_messages_id_seq; Type: ACL; Schema: elr; Owner: droolsuser
--

REVOKE ALL ON SEQUENCE system_original_messages_id_seq FROM PUBLIC;
REVOKE ALL ON SEQUENCE system_original_messages_id_seq FROM droolsuser;
GRANT ALL ON SEQUENCE system_original_messages_id_seq TO droolsuser;
GRANT ALL ON SEQUENCE system_original_messages_id_seq TO "elr-rw";
GRANT SELECT ON SEQUENCE system_original_messages_id_seq TO "elr-ro";


--
-- TOC entry 4916 (class 0 OID 0)
-- Dependencies: 509
-- Name: system_small_areas; Type: ACL; Schema: elr; Owner: droolsuser
--

REVOKE ALL ON TABLE system_small_areas FROM PUBLIC;
REVOKE ALL ON TABLE system_small_areas FROM droolsuser;
GRANT ALL ON TABLE system_small_areas TO droolsuser;
GRANT SELECT,INSERT,DELETE,UPDATE ON TABLE system_small_areas TO "elr-rw";


--
-- TOC entry 4917 (class 0 OID 0)
-- Dependencies: 289
-- Name: system_statuses; Type: ACL; Schema: elr; Owner: droolsuser
--

REVOKE ALL ON TABLE system_statuses FROM PUBLIC;
REVOKE ALL ON TABLE system_statuses FROM droolsuser;
GRANT ALL ON TABLE system_statuses TO droolsuser;
GRANT ALL ON TABLE system_statuses TO "elr-rw";
GRANT SELECT ON TABLE system_statuses TO "elr-ro";


--
-- TOC entry 4919 (class 0 OID 0)
-- Dependencies: 290
-- Name: system_statuses_id_seq; Type: ACL; Schema: elr; Owner: droolsuser
--

REVOKE ALL ON SEQUENCE system_statuses_id_seq FROM PUBLIC;
REVOKE ALL ON SEQUENCE system_statuses_id_seq FROM droolsuser;
GRANT ALL ON SEQUENCE system_statuses_id_seq TO droolsuser;
GRANT ALL ON SEQUENCE system_statuses_id_seq TO "elr-rw";
GRANT SELECT ON SEQUENCE system_statuses_id_seq TO "elr-ro";


--
-- TOC entry 4920 (class 0 OID 0)
-- Dependencies: 291
-- Name: system_zip_codes; Type: ACL; Schema: elr; Owner: droolsuser
--

REVOKE ALL ON TABLE system_zip_codes FROM PUBLIC;
REVOKE ALL ON TABLE system_zip_codes FROM droolsuser;
GRANT ALL ON TABLE system_zip_codes TO droolsuser;
GRANT ALL ON TABLE system_zip_codes TO "elr-rw";
GRANT SELECT ON TABLE system_zip_codes TO "elr-ro";


--
-- TOC entry 4922 (class 0 OID 0)
-- Dependencies: 292
-- Name: system_zip_codes_id_seq; Type: ACL; Schema: elr; Owner: droolsuser
--

REVOKE ALL ON SEQUENCE system_zip_codes_id_seq FROM PUBLIC;
REVOKE ALL ON SEQUENCE system_zip_codes_id_seq FROM droolsuser;
GRANT ALL ON SEQUENCE system_zip_codes_id_seq TO droolsuser;
GRANT ALL ON SEQUENCE system_zip_codes_id_seq TO "elr-rw";
GRANT SELECT ON SEQUENCE system_zip_codes_id_seq TO "elr-ro";


--
-- TOC entry 4923 (class 0 OID 0)
-- Dependencies: 511
-- Name: system_zip_to_small_area; Type: ACL; Schema: elr; Owner: droolsuser
--

REVOKE ALL ON TABLE system_zip_to_small_area FROM PUBLIC;
REVOKE ALL ON TABLE system_zip_to_small_area FROM droolsuser;
GRANT ALL ON TABLE system_zip_to_small_area TO droolsuser;
GRANT SELECT,INSERT,DELETE,UPDATE ON TABLE system_zip_to_small_area TO "elr-rw";


--
-- TOC entry 4925 (class 0 OID 0)
-- Dependencies: 293
-- Name: vocab_app; Type: ACL; Schema: elr; Owner: droolsuser
--

REVOKE ALL ON TABLE vocab_app FROM PUBLIC;
REVOKE ALL ON TABLE vocab_app FROM droolsuser;
GRANT ALL ON TABLE vocab_app TO droolsuser;
GRANT ALL ON TABLE vocab_app TO "elr-rw";
GRANT SELECT ON TABLE vocab_app TO "elr-ro";


--
-- TOC entry 4928 (class 0 OID 0)
-- Dependencies: 295
-- Name: vocab_audits; Type: ACL; Schema: elr; Owner: droolsuser
--

REVOKE ALL ON TABLE vocab_audits FROM PUBLIC;
REVOKE ALL ON TABLE vocab_audits FROM droolsuser;
GRANT ALL ON TABLE vocab_audits TO droolsuser;
GRANT SELECT,INSERT,DELETE,UPDATE ON TABLE vocab_audits TO "elr-rw";
GRANT SELECT ON TABLE vocab_audits TO "elr-ro";


--
-- TOC entry 4930 (class 0 OID 0)
-- Dependencies: 296
-- Name: vocab_audits_id_seq; Type: ACL; Schema: elr; Owner: droolsuser
--

REVOKE ALL ON SEQUENCE vocab_audits_id_seq FROM PUBLIC;
REVOKE ALL ON SEQUENCE vocab_audits_id_seq FROM droolsuser;
GRANT ALL ON SEQUENCE vocab_audits_id_seq TO droolsuser;
GRANT SELECT ON SEQUENCE vocab_audits_id_seq TO "elr-ro";


--
-- TOC entry 4932 (class 0 OID 0)
-- Dependencies: 297
-- Name: vocab_c2m_testresult; Type: ACL; Schema: elr; Owner: droolsuser
--

REVOKE ALL ON TABLE vocab_c2m_testresult FROM PUBLIC;
REVOKE ALL ON TABLE vocab_c2m_testresult FROM droolsuser;
GRANT ALL ON TABLE vocab_c2m_testresult TO droolsuser;
GRANT ALL ON TABLE vocab_c2m_testresult TO "elr-rw";
GRANT SELECT ON TABLE vocab_c2m_testresult TO "elr-ro";


--
-- TOC entry 4934 (class 0 OID 0)
-- Dependencies: 298
-- Name: vocab_c2m_testresult_id_seq; Type: ACL; Schema: elr; Owner: droolsuser
--

REVOKE ALL ON SEQUENCE vocab_c2m_testresult_id_seq FROM PUBLIC;
REVOKE ALL ON SEQUENCE vocab_c2m_testresult_id_seq FROM droolsuser;
GRANT ALL ON SEQUENCE vocab_c2m_testresult_id_seq TO droolsuser;
GRANT ALL ON SEQUENCE vocab_c2m_testresult_id_seq TO "elr-rw";
GRANT SELECT ON SEQUENCE vocab_c2m_testresult_id_seq TO "elr-ro";


--
-- TOC entry 4935 (class 0 OID 0)
-- Dependencies: 584
-- Name: vocab_child_codeset; Type: ACL; Schema: elr; Owner: droolsuser
--

REVOKE ALL ON TABLE vocab_child_codeset FROM PUBLIC;
REVOKE ALL ON TABLE vocab_child_codeset FROM droolsuser;
GRANT ALL ON TABLE vocab_child_codeset TO droolsuser;
GRANT SELECT,INSERT,DELETE,UPDATE ON TABLE vocab_child_codeset TO "elr-rw";
GRANT SELECT ON TABLE vocab_child_codeset TO "elr-ro";


--
-- TOC entry 4937 (class 0 OID 0)
-- Dependencies: 299
-- Name: vocab_child_loinc; Type: ACL; Schema: elr; Owner: droolsuser
--

REVOKE ALL ON TABLE vocab_child_loinc FROM PUBLIC;
REVOKE ALL ON TABLE vocab_child_loinc FROM droolsuser;
GRANT ALL ON TABLE vocab_child_loinc TO droolsuser;
GRANT ALL ON TABLE vocab_child_loinc TO "elr-rw";
GRANT SELECT ON TABLE vocab_child_loinc TO "elr-ro";


--
-- TOC entry 4939 (class 0 OID 0)
-- Dependencies: 300
-- Name: vocab_child_loinc_id_seq; Type: ACL; Schema: elr; Owner: droolsuser
--

REVOKE ALL ON SEQUENCE vocab_child_loinc_id_seq FROM PUBLIC;
REVOKE ALL ON SEQUENCE vocab_child_loinc_id_seq FROM droolsuser;
GRANT ALL ON SEQUENCE vocab_child_loinc_id_seq TO droolsuser;
GRANT ALL ON SEQUENCE vocab_child_loinc_id_seq TO "elr-rw";
GRANT SELECT ON SEQUENCE vocab_child_loinc_id_seq TO "elr-ro";


--
-- TOC entry 4940 (class 0 OID 0)
-- Dependencies: 301
-- Name: vocab_child_organism; Type: ACL; Schema: elr; Owner: droolsuser
--

REVOKE ALL ON TABLE vocab_child_organism FROM PUBLIC;
REVOKE ALL ON TABLE vocab_child_organism FROM droolsuser;
GRANT ALL ON TABLE vocab_child_organism TO droolsuser;
GRANT ALL ON TABLE vocab_child_organism TO "elr-rw";
GRANT SELECT ON TABLE vocab_child_organism TO "elr-ro";


--
-- TOC entry 4942 (class 0 OID 0)
-- Dependencies: 302
-- Name: vocab_child_organism_id_seq; Type: ACL; Schema: elr; Owner: droolsuser
--

REVOKE ALL ON SEQUENCE vocab_child_organism_id_seq FROM PUBLIC;
REVOKE ALL ON SEQUENCE vocab_child_organism_id_seq FROM droolsuser;
GRANT ALL ON SEQUENCE vocab_child_organism_id_seq TO droolsuser;
GRANT ALL ON SEQUENCE vocab_child_organism_id_seq TO "elr-rw";
GRANT SELECT ON SEQUENCE vocab_child_organism_id_seq TO "elr-ro";


--
-- TOC entry 4943 (class 0 OID 0)
-- Dependencies: 303
-- Name: vocab_child_vocab; Type: ACL; Schema: elr; Owner: droolsuser
--

REVOKE ALL ON TABLE vocab_child_vocab FROM PUBLIC;
REVOKE ALL ON TABLE vocab_child_vocab FROM droolsuser;
GRANT ALL ON TABLE vocab_child_vocab TO droolsuser;
GRANT ALL ON TABLE vocab_child_vocab TO "elr-rw";
GRANT SELECT ON TABLE vocab_child_vocab TO "elr-ro";


--
-- TOC entry 4945 (class 0 OID 0)
-- Dependencies: 304
-- Name: vocab_child_vocab_id_seq; Type: ACL; Schema: elr; Owner: droolsuser
--

REVOKE ALL ON SEQUENCE vocab_child_vocab_id_seq FROM PUBLIC;
REVOKE ALL ON SEQUENCE vocab_child_vocab_id_seq FROM droolsuser;
GRANT ALL ON SEQUENCE vocab_child_vocab_id_seq TO droolsuser;
GRANT ALL ON SEQUENCE vocab_child_vocab_id_seq TO "elr-rw";
GRANT SELECT ON SEQUENCE vocab_child_vocab_id_seq TO "elr-ro";


--
-- TOC entry 4946 (class 0 OID 0)
-- Dependencies: 582
-- Name: vocab_codeset; Type: ACL; Schema: elr; Owner: droolsuser
--

REVOKE ALL ON TABLE vocab_codeset FROM PUBLIC;
REVOKE ALL ON TABLE vocab_codeset FROM droolsuser;
GRANT ALL ON TABLE vocab_codeset TO droolsuser;
GRANT SELECT,INSERT,DELETE,UPDATE ON TABLE vocab_codeset TO "elr-rw";
GRANT SELECT ON TABLE vocab_codeset TO "elr-ro";


--
-- TOC entry 4948 (class 0 OID 0)
-- Dependencies: 586
-- Name: vocab_icd; Type: ACL; Schema: elr; Owner: droolsuser
--

REVOKE ALL ON TABLE vocab_icd FROM PUBLIC;
REVOKE ALL ON TABLE vocab_icd FROM droolsuser;
GRANT ALL ON TABLE vocab_icd TO droolsuser;
GRANT SELECT,INSERT,DELETE,UPDATE ON TABLE vocab_icd TO "elr-rw";
GRANT SELECT ON TABLE vocab_icd TO "elr-ro";


--
-- TOC entry 4950 (class 0 OID 0)
-- Dependencies: 305
-- Name: vocab_last_imported; Type: ACL; Schema: elr; Owner: droolsuser
--

REVOKE ALL ON TABLE vocab_last_imported FROM PUBLIC;
REVOKE ALL ON TABLE vocab_last_imported FROM droolsuser;
GRANT ALL ON TABLE vocab_last_imported TO droolsuser;
GRANT SELECT ON TABLE vocab_last_imported TO "elr-ro";


--
-- TOC entry 4952 (class 0 OID 0)
-- Dependencies: 306
-- Name: vocab_last_imported_id_seq; Type: ACL; Schema: elr; Owner: droolsuser
--

REVOKE ALL ON SEQUENCE vocab_last_imported_id_seq FROM PUBLIC;
REVOKE ALL ON SEQUENCE vocab_last_imported_id_seq FROM droolsuser;
GRANT ALL ON SEQUENCE vocab_last_imported_id_seq TO droolsuser;
GRANT SELECT ON SEQUENCE vocab_last_imported_id_seq TO "elr-ro";


--
-- TOC entry 4953 (class 0 OID 0)
-- Dependencies: 307
-- Name: vocab_master_condition; Type: ACL; Schema: elr; Owner: droolsuser
--

REVOKE ALL ON TABLE vocab_master_condition FROM PUBLIC;
REVOKE ALL ON TABLE vocab_master_condition FROM droolsuser;
GRANT ALL ON TABLE vocab_master_condition TO droolsuser;
GRANT ALL ON TABLE vocab_master_condition TO "elr-rw";
GRANT SELECT ON TABLE vocab_master_condition TO "elr-ro";


--
-- TOC entry 4955 (class 0 OID 0)
-- Dependencies: 308
-- Name: vocab_master_condition_c_id_seq; Type: ACL; Schema: elr; Owner: droolsuser
--

REVOKE ALL ON SEQUENCE vocab_master_condition_c_id_seq FROM PUBLIC;
REVOKE ALL ON SEQUENCE vocab_master_condition_c_id_seq FROM droolsuser;
GRANT ALL ON SEQUENCE vocab_master_condition_c_id_seq TO droolsuser;
GRANT ALL ON SEQUENCE vocab_master_condition_c_id_seq TO "elr-rw";
GRANT SELECT ON SEQUENCE vocab_master_condition_c_id_seq TO "elr-ro";


--
-- TOC entry 4956 (class 0 OID 0)
-- Dependencies: 309
-- Name: vocab_master_loinc; Type: ACL; Schema: elr; Owner: droolsuser
--

REVOKE ALL ON TABLE vocab_master_loinc FROM PUBLIC;
REVOKE ALL ON TABLE vocab_master_loinc FROM droolsuser;
GRANT ALL ON TABLE vocab_master_loinc TO droolsuser;
GRANT ALL ON TABLE vocab_master_loinc TO "elr-rw";
GRANT SELECT ON TABLE vocab_master_loinc TO "elr-ro";


--
-- TOC entry 4958 (class 0 OID 0)
-- Dependencies: 310
-- Name: vocab_master_loinc_l_id_seq; Type: ACL; Schema: elr; Owner: droolsuser
--

REVOKE ALL ON SEQUENCE vocab_master_loinc_l_id_seq FROM PUBLIC;
REVOKE ALL ON SEQUENCE vocab_master_loinc_l_id_seq FROM droolsuser;
GRANT ALL ON SEQUENCE vocab_master_loinc_l_id_seq TO droolsuser;
GRANT ALL ON SEQUENCE vocab_master_loinc_l_id_seq TO "elr-rw";
GRANT SELECT ON SEQUENCE vocab_master_loinc_l_id_seq TO "elr-ro";


--
-- TOC entry 4959 (class 0 OID 0)
-- Dependencies: 311
-- Name: vocab_master_organism; Type: ACL; Schema: elr; Owner: droolsuser
--

REVOKE ALL ON TABLE vocab_master_organism FROM PUBLIC;
REVOKE ALL ON TABLE vocab_master_organism FROM droolsuser;
GRANT ALL ON TABLE vocab_master_organism TO droolsuser;
GRANT ALL ON TABLE vocab_master_organism TO "elr-rw";
GRANT SELECT ON TABLE vocab_master_organism TO "elr-ro";


--
-- TOC entry 4961 (class 0 OID 0)
-- Dependencies: 312
-- Name: vocab_master_organism_o_id_seq; Type: ACL; Schema: elr; Owner: droolsuser
--

REVOKE ALL ON SEQUENCE vocab_master_organism_o_id_seq FROM PUBLIC;
REVOKE ALL ON SEQUENCE vocab_master_organism_o_id_seq FROM droolsuser;
GRANT ALL ON SEQUENCE vocab_master_organism_o_id_seq TO droolsuser;
GRANT ALL ON SEQUENCE vocab_master_organism_o_id_seq TO "elr-rw";
GRANT SELECT ON SEQUENCE vocab_master_organism_o_id_seq TO "elr-ro";


--
-- TOC entry 4962 (class 0 OID 0)
-- Dependencies: 313
-- Name: vocab_master_vocab; Type: ACL; Schema: elr; Owner: droolsuser
--

REVOKE ALL ON TABLE vocab_master_vocab FROM PUBLIC;
REVOKE ALL ON TABLE vocab_master_vocab FROM droolsuser;
GRANT ALL ON TABLE vocab_master_vocab TO droolsuser;
GRANT ALL ON TABLE vocab_master_vocab TO "elr-rw";
GRANT SELECT ON TABLE vocab_master_vocab TO "elr-ro";


--
-- TOC entry 4964 (class 0 OID 0)
-- Dependencies: 314
-- Name: vocab_master_vocab_id_seq; Type: ACL; Schema: elr; Owner: droolsuser
--

REVOKE ALL ON SEQUENCE vocab_master_vocab_id_seq FROM PUBLIC;
REVOKE ALL ON SEQUENCE vocab_master_vocab_id_seq FROM droolsuser;
GRANT ALL ON SEQUENCE vocab_master_vocab_id_seq TO droolsuser;
GRANT ALL ON SEQUENCE vocab_master_vocab_id_seq TO "elr-rw";
GRANT SELECT ON SEQUENCE vocab_master_vocab_id_seq TO "elr-ro";


--
-- TOC entry 4965 (class 0 OID 0)
-- Dependencies: 579
-- Name: vocab_pfge; Type: ACL; Schema: elr; Owner: droolsuser
--

REVOKE ALL ON TABLE vocab_pfge FROM PUBLIC;
REVOKE ALL ON TABLE vocab_pfge FROM droolsuser;
GRANT ALL ON TABLE vocab_pfge TO droolsuser;
GRANT SELECT,INSERT,DELETE,UPDATE ON TABLE vocab_pfge TO "elr-rw";


--
-- TOC entry 4967 (class 0 OID 0)
-- Dependencies: 315
-- Name: vocab_rules_graylist; Type: ACL; Schema: elr; Owner: droolsuser
--

REVOKE ALL ON TABLE vocab_rules_graylist FROM PUBLIC;
REVOKE ALL ON TABLE vocab_rules_graylist FROM droolsuser;
GRANT ALL ON TABLE vocab_rules_graylist TO droolsuser;
GRANT SELECT,INSERT,DELETE,UPDATE ON TABLE vocab_rules_graylist TO "elr-rw";
GRANT SELECT ON TABLE vocab_rules_graylist TO "elr-ro";


--
-- TOC entry 4969 (class 0 OID 0)
-- Dependencies: 316
-- Name: vocab_rules_graylist_id_seq; Type: ACL; Schema: elr; Owner: droolsuser
--

REVOKE ALL ON SEQUENCE vocab_rules_graylist_id_seq FROM PUBLIC;
REVOKE ALL ON SEQUENCE vocab_rules_graylist_id_seq FROM droolsuser;
GRANT ALL ON SEQUENCE vocab_rules_graylist_id_seq TO droolsuser;
GRANT SELECT ON SEQUENCE vocab_rules_graylist_id_seq TO "elr-ro";


--
-- TOC entry 4970 (class 0 OID 0)
-- Dependencies: 317
-- Name: vocab_rules_masterloinc; Type: ACL; Schema: elr; Owner: droolsuser
--

REVOKE ALL ON TABLE vocab_rules_masterloinc FROM PUBLIC;
REVOKE ALL ON TABLE vocab_rules_masterloinc FROM droolsuser;
GRANT ALL ON TABLE vocab_rules_masterloinc TO droolsuser;
GRANT SELECT,INSERT,DELETE,UPDATE ON TABLE vocab_rules_masterloinc TO "elr-rw";
GRANT SELECT ON TABLE vocab_rules_masterloinc TO "elr-ro";


--
-- TOC entry 4972 (class 0 OID 0)
-- Dependencies: 318
-- Name: vocab_rules_masterloinc_id_seq; Type: ACL; Schema: elr; Owner: droolsuser
--

REVOKE ALL ON SEQUENCE vocab_rules_masterloinc_id_seq FROM PUBLIC;
REVOKE ALL ON SEQUENCE vocab_rules_masterloinc_id_seq FROM droolsuser;
GRANT ALL ON SEQUENCE vocab_rules_masterloinc_id_seq TO droolsuser;
GRANT SELECT,UPDATE ON SEQUENCE vocab_rules_masterloinc_id_seq TO "elr-rw";
GRANT SELECT ON SEQUENCE vocab_rules_masterloinc_id_seq TO "elr-ro";


--
-- TOC entry 4973 (class 0 OID 0)
-- Dependencies: 319
-- Name: vocab_rules_mastersnomed; Type: ACL; Schema: elr; Owner: droolsuser
--

REVOKE ALL ON TABLE vocab_rules_mastersnomed FROM PUBLIC;
REVOKE ALL ON TABLE vocab_rules_mastersnomed FROM droolsuser;
GRANT ALL ON TABLE vocab_rules_mastersnomed TO droolsuser;
GRANT SELECT,INSERT,DELETE,UPDATE ON TABLE vocab_rules_mastersnomed TO "elr-rw";
GRANT SELECT ON TABLE vocab_rules_mastersnomed TO "elr-ro";


--
-- TOC entry 4975 (class 0 OID 0)
-- Dependencies: 320
-- Name: vocab_rules_mastersnomed_id_seq; Type: ACL; Schema: elr; Owner: droolsuser
--

REVOKE ALL ON SEQUENCE vocab_rules_mastersnomed_id_seq FROM PUBLIC;
REVOKE ALL ON SEQUENCE vocab_rules_mastersnomed_id_seq FROM droolsuser;
GRANT ALL ON SEQUENCE vocab_rules_mastersnomed_id_seq TO droolsuser;
GRANT SELECT ON SEQUENCE vocab_rules_mastersnomed_id_seq TO "elr-ro";


-- Completed on 2016-11-18 12:29:00

CREATE INDEX system_messages_audits_system_message_id_ix on elr.system_messages_audits (system_message_id);

--
-- PostgreSQL database dump complete
--


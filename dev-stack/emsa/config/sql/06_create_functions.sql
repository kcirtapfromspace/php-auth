\c dcp;
-- Function: elr.clone_system_message_by_id(integer, integer)

-- DROP FUNCTION elr.clone_system_message_by_id(integer, integer);

CREATE OR REPLACE FUNCTION elr.clone_system_message_by_id(
    system_message_id_in integer,
    destination_status_in integer)
  RETURNS integer AS
$BODY$
	DECLARE
		smr elr.system_messages%rowtype;

	BEGIN
		SELECT INTO smr *
		FROM elr.system_messages
		WHERE id = system_message_id_in;

		smr.id := nextval(pg_get_serial_sequence('elr.system_messages', 'id'));
		smr.final_status := destination_status_in;
		smr.copy_parent_id := system_message_id_in;

		INSERT INTO elr.system_messages VALUES (smr.*);

		RETURN smr.id;
	END;
$BODY$
  LANGUAGE plpgsql VOLATILE
  COST 100;
ALTER FUNCTION elr.clone_system_message_by_id(integer, integer)
  OWNER TO droolsuser;
GRANT EXECUTE ON FUNCTION elr.clone_system_message_by_id(integer, integer) TO droolsuser;
GRANT EXECUTE ON FUNCTION elr.clone_system_message_by_id(integer, integer) TO "elr-rw";
REVOKE ALL ON FUNCTION elr.clone_system_message_by_id(integer, integer) FROM public;


-- Function: elr.get_app_code(character varying, integer, integer, integer)

-- DROP FUNCTION elr.get_app_code(character varying, integer, integer, integer);

CREATE OR REPLACE FUNCTION elr.get_app_code(
    vocab_child_vocab_concept_in character varying,
    structure_path_application_id_in integer,
    lab_id_in integer,
    app_id_in integer)
  RETURNS character varying AS
$BODY$

    DECLARE

       coded_value_found VARCHAR := null;

    BEGIN

    SELECT vma.coded_value FROM elr.vocab_master_vocab mv
    join elr.vocab_master2app vma on (vma.master_id = mv.id AND vma.app_id = app_id_in)
    join elr.vocab_child_vocab cv on (cv.master_id = mv.id AND TRIM(cv.concept) ILIKE TRIM(vocab_child_vocab_concept_in) and cv.lab_id = lab_id_in) 
    join elr.structure_path_application spa on (spa.id = structure_path_application_id_in)
    join elr.structure_path sp on (spa.structure_path_id = sp.id)
    join elr.structure_category sc on (sc.id = sp.category_id and mv.category = sc.id)
    INTO coded_value_found;

    RETURN coded_value_found;

    END;

$BODY$
  LANGUAGE plpgsql VOLATILE
  COST 100;
ALTER FUNCTION elr.get_app_code(character varying, integer, integer, integer)
  OWNER TO postgres;
GRANT EXECUTE ON FUNCTION elr.get_app_code(character varying, integer, integer, integer) TO postgres;
GRANT EXECUTE ON FUNCTION elr.get_app_code(character varying, integer, integer, integer) TO public;


-- Function: elr.get_app_coded_value_from_master_vocab_id(integer, integer)

-- DROP FUNCTION elr.get_app_coded_value_from_master_vocab_id(integer, integer);

CREATE OR REPLACE FUNCTION elr.get_app_coded_value_from_master_vocab_id(
    master_id_in integer,
    app_id_in integer)
  RETURNS character varying AS
$BODY$

    DECLARE

       coded_value_found VARCHAR := null;

    BEGIN

    SELECT coded_value FROM elr.vocab_master2app WHERE master_id=master_id_in AND app_id=app_id_in
    INTO coded_value_found;

    RETURN coded_value_found;

    END;

$BODY$
  LANGUAGE plpgsql VOLATILE
  COST 100;
ALTER FUNCTION elr.get_app_coded_value_from_master_vocab_id(integer, integer)
  OWNER TO postgres;
GRANT EXECUTE ON FUNCTION elr.get_app_coded_value_from_master_vocab_id(integer, integer) TO postgres;
GRANT EXECUTE ON FUNCTION elr.get_app_coded_value_from_master_vocab_id(integer, integer) TO public;


-- Function: elr.get_biosense_aggregated()

-- DROP FUNCTION elr.get_biosense_aggregated();

CREATE OR REPLACE FUNCTION elr.get_biosense_aggregated()
  RETURNS SETOF character varying AS
$BODY$
	DECLARE
		message_count integer := 0;
	BEGIN
		SELECT COUNT(id)
		FROM elr.ss_batched_messages
		WHERE sent IS FALSE
		AND locked IS TRUE
		INTO message_count;

		IF (message_count > 0) THEN
			RETURN QUERY SELECT string_agg(message, E'\n')::varchar
			FROM elr.ss_batched_messages
			WHERE sent IS FALSE
			AND locked IS TRUE;
		END IF;
	END;
$BODY$
  LANGUAGE plpgsql VOLATILE
  COST 100
  ROWS 1000;
ALTER FUNCTION elr.get_biosense_aggregated()
  OWNER TO droolsuser;
GRANT EXECUTE ON FUNCTION elr.get_biosense_aggregated() TO droolsuser;
GRANT EXECUTE ON FUNCTION elr.get_biosense_aggregated() TO "elr-rw";
REVOKE ALL ON FUNCTION elr.get_biosense_aggregated() FROM public;


-- Function: elr.get_condition_name_from_icd(character varying, character varying, character varying, integer)

-- DROP FUNCTION elr.get_condition_name_from_icd(character varying, character varying, character varying, integer);

CREATE OR REPLACE FUNCTION elr.get_condition_name_from_icd(
    icd_code_in character varying,
    local_coding_system_in character varying,
    sending_facility_in character varying,
    app_id_in integer)
  RETURNS character varying AS
$BODY$

		DECLARE
			lab_id INTEGER := NULL;
			coding_system_id INTEGER := NULL;
			condition_id INTEGER := NULL;
			condition_name VARCHAR := NULL;

		BEGIN
			IF ((icd_code_in IS NOT NULL) AND (char_length(trim(icd_code_in)) > 0) AND (local_coding_system_in IS NOT NULL) AND (char_length(trim(local_coding_system_in)) > 0)) THEN
				SELECT elr.get_lab_id_from_lab_name(sending_facility_in) INTO lab_id;

				IF ((lab_id IS NOT NULL) AND (lab_id > 0)) THEN
					SELECT master_codeset_id FROM elr.vocab_child_codeset WHERE structure_labs_id = lab_id AND child_codeset_value ILIKE local_coding_system_in INTO coding_system_id;

					IF ((coding_system_id IS NOT NULL) AND (coding_system_id > 0)) THEN
						SELECT master_condition_id
						FROM elr.vocab_icd
						WHERE codeset_id = coding_system_id
						AND code_value ILIKE icd_code_in
						AND master_condition_id IS NOT NULL
						INTO condition_id;

						IF (condition_id IS NOT NULL) THEN
							SELECT m2a.coded_value
							FROM elr.vocab_master2app m2a
							INNER JOIN elr.vocab_master_condition mc ON (mc.condition = m2a.master_id)
							WHERE m2a.app_id = app_id_in
							AND mc.c_id = condition_id
							INTO condition_name;
						END IF;
					END IF;
				END IF;
			END IF;

			RETURN condition_name;
		END;

	$BODY$
  LANGUAGE plpgsql VOLATILE
  COST 100;
ALTER FUNCTION elr.get_condition_name_from_icd(character varying, character varying, character varying, integer)
  OWNER TO droolsuser;
GRANT EXECUTE ON FUNCTION elr.get_condition_name_from_icd(character varying, character varying, character varying, integer) TO droolsuser;
GRANT EXECUTE ON FUNCTION elr.get_condition_name_from_icd(character varying, character varying, character varying, integer) TO "elr-rw";
REVOKE ALL ON FUNCTION elr.get_condition_name_from_icd(character varying, character varying, character varying, integer) FROM public;
COMMENT ON FUNCTION elr.get_condition_name_from_icd(character varying, character varying, character varying, integer) IS 'Returns VARCHAR condition name (for application specified by app_id_in) based on an ICD code value.  Returns NULL if code not found.';


-- Function: elr.get_description_from_icd(character varying, character varying, character varying)

-- DROP FUNCTION elr.get_description_from_icd(character varying, character varying, character varying);

CREATE OR REPLACE FUNCTION elr.get_description_from_icd(
    icd_code_in character varying,
    local_coding_system_in character varying,
    sending_facility_in character varying)
  RETURNS character varying AS
$BODY$

		DECLARE
			lab_id INTEGER := NULL;
			coding_system_id INTEGER := NULL;
			icd_code_description VARCHAR := NULL;

		BEGIN
			IF ((icd_code_in IS NOT NULL) AND (char_length(trim(icd_code_in)) > 0) AND (local_coding_system_in IS NOT NULL) AND (char_length(trim(local_coding_system_in)) > 0)) THEN
				SELECT elr.get_lab_id_from_lab_name(sending_facility_in) INTO lab_id;

				IF ((lab_id IS NOT NULL) AND (lab_id > 0)) THEN
					SELECT master_codeset_id FROM elr.vocab_child_codeset WHERE structure_labs_id = lab_id AND child_codeset_value ILIKE local_coding_system_in INTO coding_system_id;

					IF ((coding_system_id IS NOT NULL) AND (coding_system_id > 0)) THEN
						SELECT code_description
						FROM elr.vocab_icd
						WHERE codeset_id = coding_system_id
						AND code_value ILIKE icd_code_in
						INTO icd_code_description;
					END IF;
				END IF;
			END IF;

			RETURN icd_code_description;
		END;

	$BODY$
  LANGUAGE plpgsql VOLATILE
  COST 100;
ALTER FUNCTION elr.get_description_from_icd(character varying, character varying, character varying)
  OWNER TO droolsuser;
GRANT EXECUTE ON FUNCTION elr.get_description_from_icd(character varying, character varying, character varying) TO droolsuser;
GRANT EXECUTE ON FUNCTION elr.get_description_from_icd(character varying, character varying, character varying) TO "elr-rw";
REVOKE ALL ON FUNCTION elr.get_description_from_icd(character varying, character varying, character varying) FROM public;
COMMENT ON FUNCTION elr.get_description_from_icd(character varying, character varying, character varying) IS 'Returns VARCHAR description based on an ICD code value.';


-- Function: elr.get_immediately_notifiable_from_condition(character varying)

-- DROP FUNCTION elr.get_immediately_notifiable_from_condition(character varying);

CREATE OR REPLACE FUNCTION elr.get_immediately_notifiable_from_condition(condition_in character varying)
  RETURNS boolean AS
$BODY$
	DECLARE
		immediately_notifiable boolean := FALSE;
	BEGIN
		SELECT mc.immediate_notify
		FROM elr.vocab_master_condition mc
		INNER JOIN elr.vocab_master_vocab mv ON (mv.id = mc.condition)
		WHERE (condition_in = mv.concept) AND (mv.category = elr.vocab_category_id('condition'))
		INTO immediately_notifiable;

		RETURN COALESCE(immediately_notifiable, FALSE);
	END;
$BODY$
  LANGUAGE plpgsql VOLATILE
  COST 100;
ALTER FUNCTION elr.get_immediately_notifiable_from_condition(character varying)
  OWNER TO droolsuser;
GRANT EXECUTE ON FUNCTION elr.get_immediately_notifiable_from_condition(character varying) TO droolsuser;
GRANT EXECUTE ON FUNCTION elr.get_immediately_notifiable_from_condition(character varying) TO "elr-rw";
REVOKE ALL ON FUNCTION elr.get_immediately_notifiable_from_condition(character varying) FROM public;


-- Function: elr.get_interpret_results_for_child_loinc(character varying, integer, character varying)

-- DROP FUNCTION elr.get_interpret_results_for_child_loinc(character varying, integer, character varying);

CREATE OR REPLACE FUNCTION elr.get_interpret_results_for_child_loinc(
    child_loinc_in character varying,
    lab_id_in integer,
    data_type_in character varying)
  RETURNS boolean AS
$BODY$

    DECLARE
        interpret_results_found BOOLEAN := FALSE;
        interp_dt_default BOOLEAN := NULL;
        interp_dt_lab BOOLEAN := NULL;
        interp_child_loinc BOOLEAN := NULL;

    BEGIN
        SELECT default_interpret_results FROM elr.structure_hl7_valuetype_defaults WHERE value_type = data_type_in INTO interp_dt_default;
        SELECT interpret_results FROM elr.structure_hl7_valuetype WHERE value_type = data_type_in AND lab_id = lab_id_in INTO interp_dt_lab;

        SELECT interpret_override FROM elr.vocab_child_loinc vcl
        WHERE vcl.child_loinc = child_loinc_in
        AND vcl.archived is false
        AND vcl.lab_id = lab_id_in
        INTO interp_child_loinc;

        SELECT COALESCE(interp_child_loinc, interp_dt_lab, interp_dt_default, FALSE) INTO interpret_results_found;

        RETURN interpret_results_found;
    END;

$BODY$
  LANGUAGE plpgsql VOLATILE
  COST 100;
ALTER FUNCTION elr.get_interpret_results_for_child_loinc(character varying, integer, character varying)
  OWNER TO postgres;
GRANT EXECUTE ON FUNCTION elr.get_interpret_results_for_child_loinc(character varying, integer, character varying) TO postgres;
GRANT EXECUTE ON FUNCTION elr.get_interpret_results_for_child_loinc(character varying, integer, character varying) TO public;


-- Function: elr.get_interpret_results_for_child_loinc(character varying, integer)

-- DROP FUNCTION elr.get_interpret_results_for_child_loinc(character varying, integer);

CREATE OR REPLACE FUNCTION elr.get_interpret_results_for_child_loinc(
    child_loinc_in character varying,
    lab_id_in integer)
  RETURNS boolean AS
$BODY$

    DECLARE

       interpret_results_found BOOLEAN := false;

    BEGIN

    SELECT interpret_results FROM elr.vocab_child_loinc vcl
    WHERE vcl.child_loinc = child_loinc_in
    AND vcl.archived is false
    AND vcl.lab_id = lab_id_in
    INTO interpret_results_found;

    RETURN interpret_results_found;

    END;

$BODY$
  LANGUAGE plpgsql VOLATILE
  COST 100;
ALTER FUNCTION elr.get_interpret_results_for_child_loinc(character varying, integer)
  OWNER TO postgres;
GRANT EXECUTE ON FUNCTION elr.get_interpret_results_for_child_loinc(character varying, integer) TO postgres;
GRANT EXECUTE ON FUNCTION elr.get_interpret_results_for_child_loinc(character varying, integer) TO public;


-- Function: elr.get_jurisdiction_id(character varying, character varying, character varying, character varying, character varying, integer, integer)

-- DROP FUNCTION elr.get_jurisdiction_id(character varying, character varying, character varying, character varying, character varying, integer, integer);

CREATE OR REPLACE FUNCTION elr.get_jurisdiction_id(
    person_zip_in character varying,
    diagnostic_zip_in character varying,
    person_state_in character varying,
    diagnostic_state_in character varying,
    condition_name_in character varying,
    lab_id_in integer,
    app_id_in integer)
  RETURNS integer AS
$BODY$

DECLARE

  jurisdiction_id         INTEGER := null;
  oos_jurisdiction_id     INTEGER := null;
  condition_group         VARCHAR := null;
  oos_jurisdiction_name   VARCHAR := 'Out of State';
  state_abbr              VARCHAR := 'UT';
  condition_group_match   VARCHAR := 'HAI';

BEGIN

  -- grab jurisdiction ID of OOS jurisdiction for use later
  SELECT aj.app_jurisdiction_id
  FROM elr.app_jurisdictions aj
    INNER JOIN elr.system_districts sd ON (aj.system_district_id = sd.id)
  WHERE aj.app_id = app_id_in
        AND sd.health_district ILIKE oos_jurisdiction_name
  INTO oos_jurisdiction_id;

  -- look up disease category for current condition
  WITH disease_category AS (
      SELECT id, concept
      FROM elr.vocab_master_vocab
      WHERE category = elr.vocab_category_id('disease_category')
  )
  SELECT dc.concept
  FROM elr.vocab_master_condition mc
    INNER JOIN disease_category dc ON (dc.id = mc.disease_category)
    INNER JOIN elr.vocab_master_vocab mv ON (mc.condition = mv.id)
  WHERE mv.category = elr.vocab_category_id('condition')
        AND mv.concept ILIKE condition_name_in
  INTO condition_group;


  IF condition_group ILIKE condition_group_match THEN
    -- condition is a Healthcare-Associated Infection, use HAI lookup specs

    IF diagnostic_zip_in IS NOT NULL AND diagnostic_zip_in <> '' THEN
      -- diagnostic facility address exists...
      IF ((diagnostic_state_in IS NULL) OR (diagnostic_state_in = '') OR (diagnostic_state_in ILIKE state_abbr)) THEN
        -- facility not out-of-state, route by facility zip code
        SELECT COALESCE(elr.get_jurisdiction_id_from_zip(diagnostic_zip_in, app_id_in), -4) INTO jurisdiction_id;
      END IF;
    ELSE
      -- no diagnostic facility address
      -- if we have a patient address, fall back to that
      IF person_zip_in IS NOT NULL AND person_zip_in <> '' THEN
        IF ((person_state_in IS NULL) OR (person_state_in = '') OR (person_state_in ILIKE state_abbr)) THEN
          -- person not out-of-state, route by person's zip code
          SELECT COALESCE(elr.get_jurisdiction_id_from_zip(person_zip_in, app_id_in), -3) INTO jurisdiction_id;
        END IF;
      END IF;
    END IF;

    -- if we still don't have a jurisdiction ID picked (out-of-state facility and/or patient, not a valid zip code for
    -- an in-state facility, missing both facility & person address, etc.), route to 'Utah State' jurisdiction
    IF jurisdiction_id IS NULL OR jurisdiction_id = 0 THEN
      SELECT elr.get_state_jurisdiction_id(app_id_in) INTO jurisdiction_id;
    END IF;
  ELSE
    -- normal lookup (not HAI)

    IF person_zip_in IS NOT NULL AND person_zip_in <> '' THEN
      -- person address exists...
      SELECT COALESCE(elr.get_jurisdiction_id_from_zip(person_zip_in, app_id_in), -1) INTO jurisdiction_id;

      IF jurisdiction_id IS NULL OR jurisdiction_id <= 0 THEN
        -- if no jurisdiction ID found and person is OOS, override jurisdiction to OOS
        IF ((person_state_in IS NOT NULL) AND (person_state_in <> '') AND (person_state_in NOT ILIKE state_abbr)) THEN
          SELECT oos_jurisdiction_id INTO jurisdiction_id;
        END IF;
      END IF;
    ELSE
      -- if diagnostic address exists, use it...
      IF diagnostic_zip_in IS NOT NULL AND diagnostic_zip_in <> '' THEN
        SELECT COALESCE(elr.get_jurisdiction_id_from_zip(diagnostic_zip_in, app_id_in), -2) INTO jurisdiction_id;

        IF jurisdiction_id IS NULL OR jurisdiction_id <= 0 THEN
          -- if no jurisdiction ID found and diagnostic facility is OOS, override jurisdiction to OOS
          IF ((diagnostic_state_in IS NOT NULL) AND (diagnostic_state_in <> '') AND (diagnostic_state_in NOT ILIKE state_abbr)) THEN
            SELECT oos_jurisdiction_id INTO jurisdiction_id;
          END IF;
        END IF;
      END IF;
    END IF;
  END IF;

  RETURN jurisdiction_id;

END;

$BODY$
  LANGUAGE plpgsql STABLE
  COST 100;
ALTER FUNCTION elr.get_jurisdiction_id(character varying, character varying, character varying, character varying, character varying, integer, integer)
  OWNER TO droolsuser;
GRANT EXECUTE ON FUNCTION elr.get_jurisdiction_id(character varying, character varying, character varying, character varying, character varying, integer, integer) TO droolsuser;
GRANT EXECUTE ON FUNCTION elr.get_jurisdiction_id(character varying, character varying, character varying, character varying, character varying, integer, integer) TO postgres;
GRANT EXECUTE ON FUNCTION elr.get_jurisdiction_id(character varying, character varying, character varying, character varying, character varying, integer, integer) TO "elr-rw";
GRANT EXECUTE ON FUNCTION elr.get_jurisdiction_id(character varying, character varying, character varying, character varying, character varying, integer, integer) TO "elr-ro";
REVOKE ALL ON FUNCTION elr.get_jurisdiction_id(character varying, character varying, character varying, character varying, character varying, integer, integer) FROM public;


-- Function: elr.get_jurisdiction_id_from_lab_id(integer)

-- DROP FUNCTION elr.get_jurisdiction_id_from_lab_id(integer);

CREATE OR REPLACE FUNCTION elr.get_jurisdiction_id_from_lab_id(lab_id_in integer)
  RETURNS integer AS
$BODY$

    DECLARE

       jurisdiction_id INTEGER := null;

    BEGIN

        SELECT elr.get_jurisdiction_id_from_lab_id(lab_id_in, 1) INTO jurisdiction_id ;

    RETURN jurisdiction_id;

    END;

$BODY$
  LANGUAGE plpgsql VOLATILE
  COST 100;
ALTER FUNCTION elr.get_jurisdiction_id_from_lab_id(integer)
  OWNER TO postgres;


-- Function: elr.get_jurisdiction_id_from_lab_id(integer, integer)

-- DROP FUNCTION elr.get_jurisdiction_id_from_lab_id(integer, integer);

CREATE OR REPLACE FUNCTION elr.get_jurisdiction_id_from_lab_id(
    lab_id_in integer,
    app_id_in integer)
  RETURNS integer AS
$BODY$

    DECLARE

       jurisdiction_id INTEGER := null;

    BEGIN

        SELECT ad.app_jurisdiction_id
		FROM elr.app_jurisdictions ad
		INNER JOIN elr.system_districts d ON (ad.system_district_id = d.id)
		INNER JOIN elr.structure_labs l ON (d.id = l.default_jurisdiction_id)
		WHERE ad.app_id = app_id_in
		AND l.id = lab_id_in
		INTO jurisdiction_id;

    RETURN jurisdiction_id;

    END;

$BODY$
  LANGUAGE plpgsql VOLATILE
  COST 100;
ALTER FUNCTION elr.get_jurisdiction_id_from_lab_id(integer, integer)
  OWNER TO postgres;
GRANT EXECUTE ON FUNCTION elr.get_jurisdiction_id_from_lab_id(integer, integer) TO public;
GRANT EXECUTE ON FUNCTION elr.get_jurisdiction_id_from_lab_id(integer, integer) TO postgres;


-- Function: elr.get_jurisdiction_id_from_zip(character varying, integer)

-- DROP FUNCTION elr.get_jurisdiction_id_from_zip(character varying, integer);

CREATE OR REPLACE FUNCTION elr.get_jurisdiction_id_from_zip(
    zip_in character varying,
    app_id_in integer)
  RETURNS integer AS
$BODY$

    DECLARE

       jurisdiction_id INTEGER := null;

    BEGIN

        SELECT ad.app_jurisdiction_id
		FROM elr.app_jurisdictions ad
		INNER JOIN elr.system_districts d ON (ad.system_district_id = d.id)
		INNER JOIN elr.system_zip_codes zc ON (d.id = zc.system_district_id)
		WHERE ad.app_id = app_id_in
        AND zc.zipcode = substring(zip_in from 1 for 5)
		INTO jurisdiction_id;

    RETURN jurisdiction_id;

    END;

$BODY$
  LANGUAGE plpgsql VOLATILE
  COST 100;
ALTER FUNCTION elr.get_jurisdiction_id_from_zip(character varying, integer)
  OWNER TO postgres;
GRANT EXECUTE ON FUNCTION elr.get_jurisdiction_id_from_zip(character varying, integer) TO public;
GRANT EXECUTE ON FUNCTION elr.get_jurisdiction_id_from_zip(character varying, integer) TO postgres;


-- Function: elr.get_jurisdiction_id_from_zip(character varying)

-- DROP FUNCTION elr.get_jurisdiction_id_from_zip(character varying);

CREATE OR REPLACE FUNCTION elr.get_jurisdiction_id_from_zip(zip_in character varying)
  RETURNS integer AS
$BODY$

    DECLARE

       jurisdiction_id INTEGER := null;

    BEGIN

        SELECT elr.get_jurisdiction_id_from_zip(zip_in, 1) INTO jurisdiction_id;

    RETURN jurisdiction_id;

    END;

$BODY$
  LANGUAGE plpgsql VOLATILE
  COST 100;
ALTER FUNCTION elr.get_jurisdiction_id_from_zip(character varying)
  OWNER TO postgres;


-- Function: elr.get_lab_id_from_lab_name(character varying)

-- DROP FUNCTION elr.get_lab_id_from_lab_name(character varying);

CREATE OR REPLACE FUNCTION elr.get_lab_id_from_lab_name(lab_name_in character varying)
  RETURNS integer AS
$BODY$

    DECLARE

       lab_id INTEGER := null;
       alias_for_id INTEGER := null;


    BEGIN

        SELECT alias_for FROM elr.structure_labs WHERE hl7_name ilike lab_name_in AND alias_for is not null INTO alias_for_id;

        IF alias_for_id is not null AND alias_for_id > 0 THEN
            lab_id := alias_for_id;
        ELSE
            SELECT id FROM elr.structure_labs WHERE hl7_name ilike lab_name_in AND alias_for = 0 INTO lab_id;

        END IF;

        --SELECT * FROM elr.structure_labs WHERE hl7_name ilike lab_name_in INTO ;


--  if (rs.next()) {
  --                  labId = rs.getInt("id");
    --                Integer aid = rs.getInt("alias_for");
      --              if (aid != null && aid > 0) {
        --                labId = aid;
          --          }
            --    }


    RETURN lab_id;

    END;

$BODY$
  LANGUAGE plpgsql VOLATILE
  COST 100;
ALTER FUNCTION elr.get_lab_id_from_lab_name(character varying)
  OWNER TO postgres;
GRANT EXECUTE ON FUNCTION elr.get_lab_id_from_lab_name(character varying) TO postgres;
GRANT EXECUTE ON FUNCTION elr.get_lab_id_from_lab_name(character varying) TO public;


-- Function: elr.get_lab_id_from_system_message_id(integer)

-- DROP FUNCTION elr.get_lab_id_from_system_message_id(integer);

CREATE OR REPLACE FUNCTION elr.get_lab_id_from_system_message_id(system_message_id_in integer)
  RETURNS integer AS
$BODY$

    DECLARE

       lab_id_found INTEGER := null;

    BEGIN

       SELECT lab_id FROM elr.system_messages WHERE id = system_message_id_in INTO lab_id_found;

    RETURN lab_id_found;

    END;

$BODY$
  LANGUAGE plpgsql VOLATILE
  COST 100;
ALTER FUNCTION elr.get_lab_id_from_system_message_id(integer)
  OWNER TO postgres;
GRANT EXECUTE ON FUNCTION elr.get_lab_id_from_system_message_id(integer) TO postgres;
GRANT EXECUTE ON FUNCTION elr.get_lab_id_from_system_message_id(integer) TO public;


-- Function: elr.get_list(character varying, character varying, integer)

-- DROP FUNCTION elr.get_list(character varying, character varying, integer);

CREATE OR REPLACE FUNCTION elr.get_list(
    child_loinc_in character varying,
    local_result_in character varying,
    lab_id_in integer)
  RETURNS integer AS
$BODY$
	DECLARE
		list_id integer := NULL;
		master_loinc_id integer := NULL;
		derived_organism_id integer := 0;
		derive_master_organism boolean := FALSE;
		quantitative boolean := FALSE;
	BEGIN
		SELECT vcl.interpret_results
		FROM elr.vocab_child_loinc vcl
		WHERE vcl.lab_id = lab_id_in
			AND vcl.child_loinc = child_loinc_in
			AND vcl.archived is false
		INTO quantitative;

		SELECT vml.l_id
		FROM elr.vocab_child_loinc vcl
		INNER JOIN elr.vocab_master_loinc vml ON (vcl.master_loinc = vml.l_id)
		WHERE vcl.lab_id = lab_id_in
			AND vcl.child_loinc = child_loinc_in
			AND vcl.archived is false
		INTO master_loinc_id;

		IF quantitative IS TRUE THEN
			-- For quantitative results, pull list status directly from Master LOINC
			SELECT vml.list FROM elr.vocab_master_loinc vml WHERE vml.l_id = master_loinc_id INTO list_id;
		ELSE
			-- For nominal/ordinal results, if organism is derived from local result value,
			-- get list status from derived organism.
			-- Fall back to Master LOINC if unable to retrieve list status from organism.
			SELECT vml.organism_from_result FROM elr.vocab_master_loinc vml WHERE vml.l_id = master_loinc_id INTO derive_master_organism;

			IF derive_master_organism IS TRUE THEN
				SELECT elr.loinc_organism_id(child_loinc_in, local_result_in, lab_id_in) INTO derived_organism_id;
				IF derived_organism_id > 0 THEN
					SELECT vmo.list FROM elr.vocab_master_organism vmo WHERE o_id = derived_organism_id INTO list_id;
				ELSE
					SELECT vml.list FROM elr.vocab_master_loinc vml WHERE vml.l_id = master_loinc_id INTO list_id;
				END IF;
			ELSE
				SELECT vml.list FROM elr.vocab_master_loinc vml WHERE vml.l_id = master_loinc_id INTO list_id;
			END IF;
		END IF;

		RETURN list_id;
	END;
$BODY$
  LANGUAGE plpgsql VOLATILE
  COST 100;
ALTER FUNCTION elr.get_list(character varying, character varying, integer)
  OWNER TO droolsuser;
GRANT EXECUTE ON FUNCTION elr.get_list(character varying, character varying, integer) TO droolsuser;
GRANT EXECUTE ON FUNCTION elr.get_list(character varying, character varying, integer) TO "elr-rw";
REVOKE ALL ON FUNCTION elr.get_list(character varying, character varying, integer) FROM public;


-- Function: elr.get_list(character varying, character varying, integer, character varying)

-- DROP FUNCTION elr.get_list(character varying, character varying, integer, character varying);

CREATE OR REPLACE FUNCTION elr.get_list(child_loinc_in     character varying,
                                        local_result_in    character varying,
                                        lab_id_in          integer,
                                        lab_test_status_in character varying)
    RETURNS integer AS
$BODY$
DECLARE
    list_id                integer := NULL;
    master_loinc_id        integer := NULL;
    derived_organism_id    integer := 0;
    derived_condition_id   integer := 0;
    derived_test_status    text    := NULL;
    derive_master_organism boolean := FALSE;
    blacklist_preliminary  boolean := FALSE;
    quantitative           boolean := FALSE;
BEGIN
    SELECT elr.loinc_condition_id(child_loinc_in, local_result_in, lab_id_in) INTO derived_condition_id;

    IF ((derived_condition_id IS NOT NULL) AND (derived_condition_id > 0)) THEN
        SELECT mc.blacklist_preliminary
        FROM elr.vocab_master_condition mc
        WHERE mc.c_id = derived_condition_id INTO blacklist_preliminary;
    END IF;

    IF blacklist_preliminary IS TRUE THEN
        SELECT b.concept
        FROM elr.vocab_master_vocab b
             INNER JOIN (
            SELECT master_id,
                concept AS coded_value
            FROM elr.vocab_child_vocab
            WHERE lab_id = lab_id_in
        ) a ON a.master_id = b.id
        WHERE a.coded_value ILIKE lab_test_status_in AND b.category = elr.vocab_category_id('test_status')
            INTO derived_test_status;

        IF derived_test_status ILIKE 'Preliminary result' THEN
            SELECT id FROM elr.system_statuses WHERE type = 2 AND name ILIKE 'Black' INTO list_id;

            IF ((list_id IS NOT NULL) AND (list_id > 0)) THEN
                RETURN list_id;
            END IF;
        END IF;
    END IF;

    SELECT vcl.interpret_results
    FROM elr.vocab_child_loinc vcl
    WHERE vcl.lab_id = lab_id_in AND vcl.child_loinc = child_loinc_in AND vcl.archived IS FALSE
        INTO quantitative;

    SELECT vml.l_id
    FROM elr.vocab_child_loinc vcl
         INNER JOIN elr.vocab_master_loinc vml ON (vcl.master_loinc = vml.l_id)
    WHERE vcl.lab_id = lab_id_in AND vcl.child_loinc = child_loinc_in AND vcl.archived IS FALSE
        INTO master_loinc_id;

    IF quantitative IS TRUE THEN
        -- For quantitative results, pull list status directly from Master LOINC
        SELECT vml.list FROM elr.vocab_master_loinc vml WHERE vml.l_id = master_loinc_id INTO list_id;
    ELSE
        -- For nominal/ordinal results, if organism is derived from local result value,
        -- get list status from derived organism.
        -- Fall back to Master LOINC if unable to retrieve list status from organism.
        SELECT vml.organism_from_result
        FROM elr.vocab_master_loinc vml
        WHERE vml.l_id = master_loinc_id INTO derive_master_organism;

        IF derive_master_organism IS TRUE THEN
            SELECT elr.loinc_organism_id(child_loinc_in, local_result_in, lab_id_in) INTO derived_organism_id;
            IF derived_organism_id > 0 THEN
                SELECT vmo.list FROM elr.vocab_master_organism vmo WHERE o_id = derived_organism_id INTO list_id;
            ELSE
                SELECT vml.list FROM elr.vocab_master_loinc vml WHERE vml.l_id = master_loinc_id INTO list_id;
            END IF;
        ELSE
            SELECT vml.list FROM elr.vocab_master_loinc vml WHERE vml.l_id = master_loinc_id INTO list_id;
        END IF;
    END IF;

    RETURN list_id;
END;
$BODY$
    LANGUAGE plpgsql
    VOLATILE
    COST 100;
ALTER FUNCTION elr.get_list(character varying, character varying, integer, character varying)
    OWNER TO droolsuser;
GRANT EXECUTE ON FUNCTION elr.get_list(character varying, character varying, integer, character varying) TO droolsuser;
GRANT EXECUTE ON FUNCTION elr.get_list(character varying, character varying, integer, character varying) TO "elr-rw";
REVOKE ALL ON FUNCTION elr.get_list(character varying, character varying, integer, character varying) FROM PUBLIC;


-- Function: elr.get_local_result_value(character varying, character varying, character varying, integer)

-- DROP FUNCTION elr.get_local_result_value(character varying, character varying, character varying, integer);

CREATE OR REPLACE FUNCTION elr.get_local_result_value(
    loinc_code_in character varying,
    result_value_1_in character varying,
    result_value_2_in character varying,
    lab_id_in integer)
  RETURNS character varying AS
$BODY$

    DECLARE

        local_result_value VARCHAR := result_value_1_in;
        interpret BOOLEAN := false;
        mapped_id INTEGER := null;

    BEGIN

        IF result_value_2_in is not null AND char_length(trim(result_value_2_in)) > 0 THEN

            SELECT interpret_results
			FROM elr.vocab_child_loinc
			WHERE child_loinc = loinc_code_in
				AND lab_id = lab_id_in
				AND archived is false
			INTO interpret;

            IF interpret THEN
                local_result_value := result_value_2_in;
            ELSE
                --some LOINCs don't set an organism; check for a mapped test result instead of a derived organism
				--SELECT elr.loinc_organism_id(loinc_code_in, result_value_2_in, lab_id_in) INTO mapped_id;
				SELECT elr.get_nomord_testresult(result_value_2_in, lab_id_in) INTO mapped_id;
                IF mapped_id > 0 THEN
                    local_result_value := result_value_2_in;
                END IF;
            END IF;
        END IF;

    RETURN local_result_value;

    END;

$BODY$
  LANGUAGE plpgsql VOLATILE
  COST 100;
ALTER FUNCTION elr.get_local_result_value(character varying, character varying, character varying, integer)
  OWNER TO droolsuser;
GRANT EXECUTE ON FUNCTION elr.get_local_result_value(character varying, character varying, character varying, integer) TO droolsuser;
GRANT EXECUTE ON FUNCTION elr.get_local_result_value(character varying, character varying, character varying, integer) TO "elr-rw";
REVOKE ALL ON FUNCTION elr.get_local_result_value(character varying, character varying, character varying, integer) FROM public;


-- Function: elr.get_master_concept(integer, character varying, integer)

-- DROP FUNCTION elr.get_master_concept(integer, character varying, integer);

CREATE OR REPLACE FUNCTION elr.get_master_concept(
    system_message_id_in integer,
    coded_value_in character varying,
    category_in integer)
  RETURNS character varying AS
$BODY$

    DECLARE

       concept_found VARCHAR := null;

    BEGIN

    SELECT b.concept FROM elr.vocab_master_vocab b
    INNER JOIN (
        (SELECT master_id, coded_value AS coded_value FROM elr.vocab_master2app)
    UNION
        (SELECT master_id, concept AS coded_value FROM elr.vocab_child_vocab WHERE lab_id =
            (SELECT lab_id FROM elr.system_messages WHERE id = system_message_id_in)
        )
    ) a ON a.master_id=b.id
    WHERE a.coded_value ilike coded_value_in
    AND category = category_in
    INTO concept_found;

    RETURN concept_found;

    END;

$BODY$
  LANGUAGE plpgsql VOLATILE
  COST 100;
ALTER FUNCTION elr.get_master_concept(integer, character varying, integer)
  OWNER TO postgres;
GRANT EXECUTE ON FUNCTION elr.get_master_concept(integer, character varying, integer) TO postgres;
GRANT EXECUTE ON FUNCTION elr.get_master_concept(integer, character varying, integer) TO public;


-- Function: elr.get_master_concept_from_master_loinc(character varying)

-- DROP FUNCTION elr.get_master_concept_from_master_loinc(character varying);

CREATE OR REPLACE FUNCTION elr.get_master_concept_from_master_loinc(master_loinc_in character varying)
  RETURNS character varying AS
$BODY$

    DECLARE

       concept_found VARCHAR := null;

    BEGIN

    SELECT vmv.concept
    FROM elr.vocab_master_condition vmc,elr.vocab_master_vocab vmv, elr.vocab_master_loinc vml
    WHERE vml.trisano_condition = vmc.c_id
    AND vmc.condition = vmv.id
    AND vml.loinc ilike master_loinc_in
    INTO concept_found;

    RETURN concept_found;

    END;

$BODY$
  LANGUAGE plpgsql VOLATILE
  COST 100;
ALTER FUNCTION elr.get_master_concept_from_master_loinc(character varying)
  OWNER TO postgres;
GRANT EXECUTE ON FUNCTION elr.get_master_concept_from_master_loinc(character varying) TO postgres;
GRANT EXECUTE ON FUNCTION elr.get_master_concept_from_master_loinc(character varying) TO public;


-- Function: elr.get_master_loinc(character varying, integer)

-- DROP FUNCTION elr.get_master_loinc(character varying, integer);

CREATE OR REPLACE FUNCTION elr.get_master_loinc(
    local_loinc_in character varying,
    lab_id_in integer)
  RETURNS character varying AS
$BODY$

    DECLARE

       master_loinc_found VARCHAR := null;

    BEGIN

    SELECT vml.loinc
    FROM elr.vocab_child_loinc vcl ,elr.vocab_master_loinc vml
    WHERE vcl.master_loinc = vml.l_id
    AND child_loinc ilike local_loinc_in
    AND vcl.archived is false
    AND vcl.lab_id = lab_id_in
    INTO master_loinc_found;

    RETURN master_loinc_found;

    END;

$BODY$
  LANGUAGE plpgsql VOLATILE
  COST 100;
ALTER FUNCTION elr.get_master_loinc(character varying, integer)
  OWNER TO postgres;
GRANT EXECUTE ON FUNCTION elr.get_master_loinc(character varying, integer) TO postgres;
GRANT EXECUTE ON FUNCTION elr.get_master_loinc(character varying, integer) TO public;


-- Function: elr.get_master_snomed_id_by_name(character varying, character varying, integer)

-- DROP FUNCTION elr.get_master_snomed_id_by_name(character varying, character varying, integer);

CREATE OR REPLACE FUNCTION elr.get_master_snomed_id_by_name(
    organism_name_in character varying,
    condition_name_in character varying,
    app_id_in integer)
  RETURNS integer AS
$BODY$

DECLARE
  master_snomed_id_out INTEGER := null;

BEGIN
  -- attempt to get value from Master Vocab concept first
  SELECT MAX(o_id)
  FROM elr.vocab_master_organism
  WHERE organism = (
    SELECT MAX(id)
    FROM elr.vocab_master_vocab
    WHERE
      category = elr.vocab_category_id('organism')
      AND	concept = organism_name_in
  )
        AND condition = (
    SELECT max(c_id)
    FROM elr.vocab_master_condition
    WHERE condition = (
      SELECT id
      FROM elr.vocab_master_vocab
      WHERE category = elr.vocab_category_id('condition')
            AND concept = condition_name_in
    )
  )
  INTO master_snomed_id_out;

  -- fall back to organism name only
  IF (master_snomed_id_out IS NULL OR master_snomed_id_out < 1) THEN
    SELECT elr.get_master_snomed_id_by_name(organism_name_in, app_id_in) INTO master_snomed_id_out;
  END IF;

  RETURN master_snomed_id_out;

END;

$BODY$
  LANGUAGE plpgsql VOLATILE
  COST 100;
ALTER FUNCTION elr.get_master_snomed_id_by_name(character varying, character varying, integer)
  OWNER TO droolsuser;
GRANT EXECUTE ON FUNCTION elr.get_master_snomed_id_by_name(character varying, character varying, integer) TO droolsuser;
GRANT EXECUTE ON FUNCTION elr.get_master_snomed_id_by_name(character varying, character varying, integer) TO "elr-rw";
REVOKE ALL ON FUNCTION elr.get_master_snomed_id_by_name(character varying, character varying, integer) FROM public;


-- Function: elr.get_master_snomed_id_by_name(character varying)

-- DROP FUNCTION elr.get_master_snomed_id_by_name(character varying);

CREATE OR REPLACE FUNCTION elr.get_master_snomed_id_by_name(organism_name_in character varying)
  RETURNS integer AS
$BODY$

	DECLARE
		master_snomed_id_out INTEGER := null;

	BEGIN
		-- deprecated; replacing with elr.get_master_snomed_id_by_name(character varying, integer)
		SELECT elr.get_master_snomed_id_by_name(organism_name_in, 1) INTO master_snomed_id_out;

		RETURN master_snomed_id_out;

	END;

$BODY$
  LANGUAGE plpgsql VOLATILE
  COST 100;
ALTER FUNCTION elr.get_master_snomed_id_by_name(character varying)
  OWNER TO droolsuser;
GRANT EXECUTE ON FUNCTION elr.get_master_snomed_id_by_name(character varying) TO droolsuser;
GRANT EXECUTE ON FUNCTION elr.get_master_snomed_id_by_name(character varying) TO "elr-rw";
REVOKE ALL ON FUNCTION elr.get_master_snomed_id_by_name(character varying) FROM public;


-- Function: elr.get_master_snomed_id_by_name(character varying, integer)

-- DROP FUNCTION elr.get_master_snomed_id_by_name(character varying, integer);

CREATE OR REPLACE FUNCTION elr.get_master_snomed_id_by_name(
    organism_name_in character varying,
    app_id_in integer)
  RETURNS integer AS
$BODY$

	DECLARE
		master_snomed_id_out INTEGER := null;
		id_from_master_concept INTEGER := null;
		id_from_app_concept INTEGER := null;

	BEGIN
		-- attempt to get value from Master Vocab concept first
		SELECT MAX(o_id)
		FROM elr.vocab_master_organism
		WHERE organism = (
			SELECT MAX(id)
			FROM elr.vocab_master_vocab
			WHERE
					category = elr.vocab_category_id('organism')
				AND	concept = organism_name_in
		)
		INTO id_from_master_concept;

		-- next, attempt to get value from NEDSS concept
		SELECT MAX(o_id)
		FROM elr.vocab_master_organism
		WHERE organism = (
			SELECT elr.get_master_vocab_id_from_app_coded_value(organism_name_in, 'organism', app_id_in)
		)
		INTO id_from_app_concept;

		-- coalesce two attempts or return null if both are empty
		SELECT COALESCE(id_from_master_concept, id_from_app_concept) INTO master_snomed_id_out;

		RETURN master_snomed_id_out;

	END;

$BODY$
  LANGUAGE plpgsql VOLATILE
  COST 100;
ALTER FUNCTION elr.get_master_snomed_id_by_name(character varying, integer)
  OWNER TO droolsuser;
GRANT EXECUTE ON FUNCTION elr.get_master_snomed_id_by_name(character varying, integer) TO droolsuser;
GRANT EXECUTE ON FUNCTION elr.get_master_snomed_id_by_name(character varying, integer) TO "elr-rw";
REVOKE ALL ON FUNCTION elr.get_master_snomed_id_by_name(character varying, integer) FROM public;


-- Function: elr.get_master_vocab_coded_value(integer, integer)

-- DROP FUNCTION elr.get_master_vocab_coded_value(integer, integer);

CREATE OR REPLACE FUNCTION elr.get_master_vocab_coded_value(
    vocab_master_vocab_id_in integer,
    app_id_in integer)
  RETURNS character varying AS
$BODY$

    DECLARE

       coded_value_found VARCHAR := null;

    BEGIN

    SELECT m2a.coded_value
    FROM elr.vocab_master_vocab AS vmv, elr.vocab_master2app AS m2a
    WHERE m2a.master_id = vmv.id
    AND m2a.app_id = app_id_in
    AND vmv.id = vocab_master_vocab_id_in
    INTO coded_value_found;

    RETURN coded_value_found;

    END;

$BODY$
  LANGUAGE plpgsql VOLATILE
  COST 100;
ALTER FUNCTION elr.get_master_vocab_coded_value(integer, integer)
  OWNER TO postgres;
GRANT EXECUTE ON FUNCTION elr.get_master_vocab_coded_value(integer, integer) TO postgres;
GRANT EXECUTE ON FUNCTION elr.get_master_vocab_coded_value(integer, integer) TO public;


-- Function: elr.get_master_vocab_id_from_app_coded_value(character varying, character varying, integer)

-- DROP FUNCTION elr.get_master_vocab_id_from_app_coded_value(character varying, character varying, integer);

CREATE OR REPLACE FUNCTION elr.get_master_vocab_id_from_app_coded_value(
    coded_value_in character varying,
    category_in character varying,
    app_id_in integer)
  RETURNS integer AS
$BODY$

	DECLARE
		master_vocab_id_out INTEGER := null;

	BEGIN
		SELECT mv.id
		FROM elr.vocab_master_vocab mv
		INNER JOIN elr.vocab_master2app m2a ON (m2a.master_id = mv.id)
		WHERE
				mv.category = elr.vocab_category_id(category_in)
			AND	m2a.coded_value = coded_value_in
		INTO master_vocab_id_out;

		RETURN master_vocab_id_out;

	END;

$BODY$
  LANGUAGE plpgsql VOLATILE
  COST 100;
ALTER FUNCTION elr.get_master_vocab_id_from_app_coded_value(character varying, character varying, integer)
  OWNER TO droolsuser;
GRANT EXECUTE ON FUNCTION elr.get_master_vocab_id_from_app_coded_value(character varying, character varying, integer) TO droolsuser;
GRANT EXECUTE ON FUNCTION elr.get_master_vocab_id_from_app_coded_value(character varying, character varying, integer) TO "elr-rw";
REVOKE ALL ON FUNCTION elr.get_master_vocab_id_from_app_coded_value(character varying, character varying, integer) FROM public;


-- Function: elr.get_nomord_comments(character varying, integer)

-- DROP FUNCTION elr.get_nomord_comments(character varying, integer);

CREATE OR REPLACE FUNCTION elr.get_nomord_comments(
    local_result_in character varying,
    lab_id_in integer)
  RETURNS character varying AS
$BODY$
	DECLARE
		comment_text varchar := '';
	BEGIN
		SELECT vco.comment
		FROM elr.vocab_child_organism vco
		WHERE vco.lab_id = lab_id_in AND lower(vco.child_code) = lower(local_result_in)
		INTO comment_text;

		RETURN comment_text;
	END;
$BODY$
  LANGUAGE plpgsql VOLATILE
  COST 100;
ALTER FUNCTION elr.get_nomord_comments(character varying, integer)
  OWNER TO droolsuser;
GRANT EXECUTE ON FUNCTION elr.get_nomord_comments(character varying, integer) TO droolsuser;
GRANT EXECUTE ON FUNCTION elr.get_nomord_comments(character varying, integer) TO "elr-rw";
REVOKE ALL ON FUNCTION elr.get_nomord_comments(character varying, integer) FROM public;


-- Function: elr.get_nomord_resultvalue(character varying, integer)

-- DROP FUNCTION elr.get_nomord_resultvalue(character varying, integer);

CREATE OR REPLACE FUNCTION elr.get_nomord_resultvalue(
    local_result_in character varying,
    lab_id_in integer)
  RETURNS character varying AS
$BODY$
	DECLARE
		result_value varchar := '';
	BEGIN
		SELECT vco.result_value
		FROM elr.vocab_child_organism vco
		WHERE vco.lab_id = lab_id_in AND lower(vco.child_code) = lower(local_result_in)
		INTO result_value;

		RETURN result_value;
	END;
$BODY$
  LANGUAGE plpgsql VOLATILE
  COST 100;
ALTER FUNCTION elr.get_nomord_resultvalue(character varying, integer)
  OWNER TO droolsuser;
GRANT EXECUTE ON FUNCTION elr.get_nomord_resultvalue(character varying, integer) TO droolsuser;
GRANT EXECUTE ON FUNCTION elr.get_nomord_resultvalue(character varying, integer) TO "elr-rw";
REVOKE ALL ON FUNCTION elr.get_nomord_resultvalue(character varying, integer) FROM public;


-- Function: elr.get_nomord_testresult(character varying, integer)

-- DROP FUNCTION elr.get_nomord_testresult(character varying, integer);

CREATE OR REPLACE FUNCTION elr.get_nomord_testresult(
    local_result_in character varying,
    lab_id_in integer)
  RETURNS integer AS
$BODY$
	DECLARE
		derived_test_result_id integer := 0;
		derived_organism_id integer := 0;

	BEGIN
		-- Check to see if local result is a child code
		SELECT COALESCE(
			(SELECT
			CASE
				WHEN vco.test_result_id > 0 THEN vco.test_result_id
				WHEN vco.test_result_id < 1 AND vco.organism > 0 THEN vco.organism
				ELSE -1
			END
		FROM elr.vocab_child_organism vco
		WHERE (lower(vco.child_code) = lower(local_result_in)) AND (vco.lab_id = lab_id_in)), -1)
		INTO derived_organism_id;
		/* RAISE NOTICE 'derived_organism_id after vco: %', derived_organism_id; */

		-- If not a child code, check if local result is a master SNOMED code
		IF derived_organism_id < 1 THEN
			SELECT COALESCE(
				(SELECT
				CASE
					WHEN vmo.o_id > 0 THEN vmo.o_id
					ELSE -1
				END
			FROM elr.vocab_master_organism vmo
			WHERE (lower(vmo.snomed) = lower(local_result_in)) OR (lower(vmo.snomed_alt) = lower(local_result_in))), -1)
			INTO derived_organism_id;
		END IF;
		/* RAISE NOTICE 'derived_organism_id after vmo: %', derived_organism_id; */

		IF derived_organism_id > 0 THEN
			SELECT COALESCE((SELECT vmo.test_result FROM elr.vocab_master_organism vmo WHERE o_id = derived_organism_id), -1) INTO derived_test_result_id;
		END IF;

		RETURN derived_test_result_id;
	END;
$BODY$
  LANGUAGE plpgsql VOLATILE
  COST 100;
ALTER FUNCTION elr.get_nomord_testresult(character varying, integer)
  OWNER TO droolsuser;
GRANT EXECUTE ON FUNCTION elr.get_nomord_testresult(character varying, integer) TO droolsuser;
GRANT EXECUTE ON FUNCTION elr.get_nomord_testresult(character varying, integer) TO "elr-rw";
REVOKE ALL ON FUNCTION elr.get_nomord_testresult(character varying, integer) FROM public;


-- Function: elr.get_pregnancy_from_icd(character varying, character varying, character varying)

-- DROP FUNCTION elr.get_pregnancy_from_icd(character varying, character varying, character varying);

CREATE OR REPLACE FUNCTION elr.get_pregnancy_from_icd(
    icd_code_in character varying,
    local_coding_system_in character varying,
    sending_facility_in character varying)
  RETURNS integer AS
$BODY$

		DECLARE
			lab_id INTEGER := NULL;
			coding_system_id INTEGER := NULL;
			pregnancy_flag_temp INTEGER := NULL;
			pregnancy_flag INTEGER := NULL;

		BEGIN
			IF ((icd_code_in IS NOT NULL) AND (char_length(trim(icd_code_in)) > 0) AND (local_coding_system_in IS NOT NULL) AND (char_length(trim(local_coding_system_in)) > 0)) THEN
				SELECT elr.get_lab_id_from_lab_name(sending_facility_in) INTO lab_id;

				IF ((lab_id IS NOT NULL) AND (lab_id > 0)) THEN
					SELECT master_codeset_id FROM elr.vocab_child_codeset WHERE structure_labs_id = lab_id AND child_codeset_value ILIKE local_coding_system_in INTO coding_system_id;

					IF ((coding_system_id IS NOT NULL) AND (coding_system_id > 0)) THEN
						SELECT CASE
							WHEN pregnancy_indicator IS FALSE THEN -1
							WHEN pregnancy_indicator IS TRUE AND pregnancy_status IS TRUE THEN 1
							WHEN pregnancy_indicator IS TRUE AND pregnancy_status IS FALSE THEN 0
							WHEN pregnancy_indicator IS TRUE AND pregnancy_status IS UNKNOWN THEN 2
							END
						FROM elr.vocab_icd
						WHERE codeset_id = coding_system_id
						AND code_value ILIKE icd_code_in
						INTO pregnancy_flag_temp;

						IF (pregnancy_flag_temp IS NOT NULL) THEN
							pregnancy_flag := pregnancy_flag_temp;
						END IF;
					END IF;
				END IF;
			END IF;

			RETURN pregnancy_flag;
		END;

	$BODY$
  LANGUAGE plpgsql VOLATILE
  COST 100;
ALTER FUNCTION elr.get_pregnancy_from_icd(character varying, character varying, character varying)
  OWNER TO droolsuser;
GRANT EXECUTE ON FUNCTION elr.get_pregnancy_from_icd(character varying, character varying, character varying) TO droolsuser;
GRANT EXECUTE ON FUNCTION elr.get_pregnancy_from_icd(character varying, character varying, character varying) TO "elr-rw";
REVOKE ALL ON FUNCTION elr.get_pregnancy_from_icd(character varying, character varying, character varying) FROM public;
COMMENT ON FUNCTION elr.get_pregnancy_from_icd(character varying, character varying, character varying) IS 'Returns INTEGER representing pregnancy status based on an ICD code value.  Returns -1 for "Code not found", 0 for "No", 1 for "Yes", and 2 for "Unknown".';


-- Function: elr.get_pretty_lab_name(integer)

-- DROP FUNCTION elr.get_pretty_lab_name(integer);

CREATE OR REPLACE FUNCTION elr.get_pretty_lab_name(lab_id_in integer)
  RETURNS character varying AS
$BODY$
	DECLARE
		lab_pretty_name varchar;
	BEGIN
		SELECT ui_name FROM elr.structure_labs WHERE id = lab_id_in INTO lab_pretty_name;
		RETURN lab_pretty_name;
	END;
$BODY$
  LANGUAGE plpgsql VOLATILE
  COST 100;
ALTER FUNCTION elr.get_pretty_lab_name(integer)
  OWNER TO droolsuser;
GRANT EXECUTE ON FUNCTION elr.get_pretty_lab_name(integer) TO droolsuser;
GRANT EXECUTE ON FUNCTION elr.get_pretty_lab_name(integer) TO public;
GRANT EXECUTE ON FUNCTION elr.get_pretty_lab_name(integer) TO "elr-rw";


-- Function: elr.get_reference_range(character varying, integer)

-- DROP FUNCTION elr.get_reference_range(character varying, integer);

CREATE OR REPLACE FUNCTION elr.get_reference_range(
    child_loinc_in character varying,
    lab_id_in integer)
  RETURNS character varying AS
$BODY$

    DECLARE

       refrange_found VARCHAR := null;

    BEGIN

    SELECT refrange
    FROM elr.vocab_child_loinc vcl
    WHERE vcl.child_loinc = child_loinc_in
    AND vcl.lab_id = lab_id_in
    AND vcl.archived is false
    INTO refrange_found;

    RETURN refrange_found;

    END;

$BODY$
  LANGUAGE plpgsql VOLATILE
  COST 100;
ALTER FUNCTION elr.get_reference_range(character varying, integer)
  OWNER TO postgres;
GRANT EXECUTE ON FUNCTION elr.get_reference_range(character varying, integer) TO postgres;
GRANT EXECUTE ON FUNCTION elr.get_reference_range(character varying, integer) TO public;


-- Function: elr.get_report_date(integer)

-- DROP FUNCTION elr.get_report_date(integer);

CREATE OR REPLACE FUNCTION elr.get_report_date(system_original_messages_id_in integer)
  RETURNS timestamp without time zone AS
$BODY$

    DECLARE

       report_date_found TIMESTAMP := null;

    BEGIN

    SELECT created_at FROM elr.system_original_messages WHERE id = system_original_messages_id_in
    INTO report_date_found;

    RETURN report_date_found;

    END;

$BODY$
  LANGUAGE plpgsql VOLATILE
  COST 100;
ALTER FUNCTION elr.get_report_date(integer)
  OWNER TO postgres;
GRANT EXECUTE ON FUNCTION elr.get_report_date(integer) TO postgres;
GRANT EXECUTE ON FUNCTION elr.get_report_date(integer) TO public;


-- Function: elr.get_result_value_location(character varying, integer)

-- DROP FUNCTION elr.get_result_value_location(character varying, integer);

CREATE OR REPLACE FUNCTION elr.get_result_value_location(
    child_loinc_in character varying,
    lab_id_in integer)
  RETURNS character varying AS
$BODY$

    DECLARE

       result_value_location_found VARCHAR := null;

    BEGIN

    SELECT vmv.concept
    FROM elr.vocab_child_loinc vcl
    INNER JOIN elr.vocab_master_vocab vmv ON vcl.result_location=vmv.id
    WHERE vcl.child_loinc=child_loinc_in
    AND vcl.lab_id=lab_id_in
    INTO result_value_location_found;

    RETURN result_value_location_found;

    END;

$BODY$
  LANGUAGE plpgsql VOLATILE
  COST 100;
ALTER FUNCTION elr.get_result_value_location(character varying, integer)
  OWNER TO postgres;
GRANT EXECUTE ON FUNCTION elr.get_result_value_location(character varying, integer) TO postgres;
GRANT EXECUTE ON FUNCTION elr.get_result_value_location(character varying, integer) TO public;


-- Function: elr.get_source_required(character varying, character varying, integer)

-- DROP FUNCTION elr.get_source_required(character varying, character varying, integer);

CREATE OR REPLACE FUNCTION elr.get_source_required(
    child_loinc_in character varying,
    result_value_in character varying,
    lab_id_in integer)
  RETURNS boolean AS
$BODY$

    DECLARE

       source_required_found BOOLEAN := false;
	   condition_lookup BOOLEAN := false;

    BEGIN

		SELECT vml.condition_from_result
		FROM elr.vocab_master_loinc vml
		INNER JOIN elr.vocab_child_loinc vcl ON (vcl.master_loinc = vml.l_id)
		WHERE vcl.child_loinc = child_loinc_in
		AND vcl.archived IS FALSE
		AND vcl.lab_id = lab_id_in
		INTO condition_lookup;

		IF (condition_lookup IS TRUE) THEN
			SELECT COALESCE(
				(
					SELECT vmc.require_specimen
					FROM elr.vocab_master_condition vmc
					WHERE vmc.c_id = elr.loinc_condition_id(child_loinc_in, result_value_in, lab_id_in)
				), FALSE
			) INTO source_required_found;

			RETURN source_required_found;
		ELSE
			RETURN FALSE;
		END IF;

    END;

$BODY$
  LANGUAGE plpgsql VOLATILE
  COST 100;
ALTER FUNCTION elr.get_source_required(character varying, character varying, integer)
  OWNER TO droolsuser;
GRANT EXECUTE ON FUNCTION elr.get_source_required(character varying, character varying, integer) TO droolsuser;
GRANT EXECUTE ON FUNCTION elr.get_source_required(character varying, character varying, integer) TO "elr-rw";
REVOKE ALL ON FUNCTION elr.get_source_required(character varying, character varying, integer) FROM public;


-- Function: elr.get_specimen_source(character varying, integer)

-- DROP FUNCTION elr.get_specimen_source(character varying, integer);

CREATE OR REPLACE FUNCTION elr.get_specimen_source(
    master_loinc_in character varying,
    app_id_in integer)
  RETURNS character varying AS
$BODY$

    DECLARE

       coded_value_found VARCHAR := null;

    BEGIN

    SELECT m2a.coded_value
    FROM elr.vocab_master_loinc AS vml, elr.vocab_master2app AS m2a
    WHERE m2a.master_id = vml.specimen_source
    AND m2a.app_id = app_id_in
    AND vml.loinc = master_loinc_in
    INTO coded_value_found;

    RETURN coded_value_found;

    END;

$BODY$
  LANGUAGE plpgsql VOLATILE
  COST 100;
ALTER FUNCTION elr.get_specimen_source(character varying, integer)
  OWNER TO postgres;
GRANT EXECUTE ON FUNCTION elr.get_specimen_source(character varying, integer) TO postgres;
GRANT EXECUTE ON FUNCTION elr.get_specimen_source(character varying, integer) TO public;


-- Function: elr.get_state_case_status_nominal(character varying, character varying, integer, integer)

-- DROP FUNCTION elr.get_state_case_status_nominal(character varying, character varying, integer, integer);

CREATE OR REPLACE FUNCTION elr.get_state_case_status_nominal(
    local_loinc_in character varying,
    local_result_in character varying,
    lab_id_in integer,
    app_id_in integer)
  RETURNS character varying AS
$BODY$
	DECLARE
		organism_from_res Boolean := FALSE;
                found_status VARCHAR := null;
                vocab_master_vocab_id INTEGER := null;
                organism_id INTEGER := null;
	BEGIN


            SELECT organism_from_result
			FROM elr.vocab_master_loinc vml
			INNER JOIN elr.vocab_child_loinc vcl ON (vml.l_id = vcl.master_loinc)
            WHERE vcl.child_loinc = local_loinc_in
				AND vcl.archived is false
			INTO organism_from_res;

           -- RAISE NOTICE 'organism_from_res: %', organism_from_res;

            IF organism_from_res IS TRUE THEN

                SELECT elr.loinc_organism_id(local_loinc_in, local_result_in,lab_id_in) INTO organism_id;

           -- RAISE NOTICE 'organism_id: %', organism_id;

                IF organism_id IS NOT NULL THEN

                    SELECT status FROM elr.vocab_master_organism WHERE o_id = organism_id INTO vocab_master_vocab_id;
                    SELECT elr.get_master_vocab_coded_value(vocab_master_vocab_id,app_id_in) INTO found_status;

                END IF;

            END IF;

	    RETURN found_status;
	END;
$BODY$
  LANGUAGE plpgsql VOLATILE
  COST 100;
ALTER FUNCTION elr.get_state_case_status_nominal(character varying, character varying, integer, integer)
  OWNER TO droolsuser;
GRANT EXECUTE ON FUNCTION elr.get_state_case_status_nominal(character varying, character varying, integer, integer) TO droolsuser;
GRANT EXECUTE ON FUNCTION elr.get_state_case_status_nominal(character varying, character varying, integer, integer) TO "elr-rw";
REVOKE ALL ON FUNCTION elr.get_state_case_status_nominal(character varying, character varying, integer, integer) FROM public;


-- Function: elr.get_state_jurisdiction_id(integer)

-- DROP FUNCTION elr.get_state_jurisdiction_id(integer);

CREATE OR REPLACE FUNCTION elr.get_state_jurisdiction_id(app_id_in integer)
  RETURNS integer AS
$BODY$

DECLARE

  jurisdiction_id         INTEGER := null;
  state_jurisdiction_name VARCHAR := 'Utah State';

BEGIN

  SELECT aj.app_jurisdiction_id
  FROM elr.app_jurisdictions aj
    INNER JOIN elr.system_districts sd ON (aj.system_district_id = sd.id)
  WHERE aj.app_id = app_id_in
  AND sd.health_district ILIKE state_jurisdiction_name
  INTO jurisdiction_id;

  RETURN jurisdiction_id;

END;

$BODY$
  LANGUAGE plpgsql STABLE
  COST 100;
ALTER FUNCTION elr.get_state_jurisdiction_id(integer)
  OWNER TO droolsuser;
GRANT EXECUTE ON FUNCTION elr.get_state_jurisdiction_id(integer) TO droolsuser;
GRANT EXECUTE ON FUNCTION elr.get_state_jurisdiction_id(integer) TO postgres;
GRANT EXECUTE ON FUNCTION elr.get_state_jurisdiction_id(integer) TO "elr-rw";
GRANT EXECUTE ON FUNCTION elr.get_state_jurisdiction_id(integer) TO "elr-ro";
REVOKE ALL ON FUNCTION elr.get_state_jurisdiction_id(integer) FROM public;


-- Function: elr.get_test_type(character varying, integer)

-- DROP FUNCTION elr.get_test_type(character varying, integer);

CREATE OR REPLACE FUNCTION elr.get_test_type(
    master_loinc_in character varying,
    app_id_in integer)
  RETURNS character varying AS
$BODY$

    DECLARE

       coded_value_found VARCHAR := null;

    BEGIN

    SELECT m2a.coded_value
    FROM elr.vocab_master_loinc AS vml, elr.vocab_master2app AS m2a
    WHERE m2a.master_id = vml.trisano_test_type
    AND m2a.app_id = app_id_in
    AND vml.loinc = master_loinc_in
    INTO coded_value_found;

    RETURN coded_value_found;

    END;

$BODY$
  LANGUAGE plpgsql VOLATILE
  COST 100;
ALTER FUNCTION elr.get_test_type(character varying, integer)
  OWNER TO postgres;
GRANT EXECUTE ON FUNCTION elr.get_test_type(character varying, integer) TO postgres;
GRANT EXECUTE ON FUNCTION elr.get_test_type(character varying, integer) TO public;


-- Function: elr.loinc_condition(character varying, character varying, integer)

-- DROP FUNCTION elr.loinc_condition(character varying, character varying, integer);

CREATE OR REPLACE FUNCTION elr.loinc_condition(
    loinc_code_in character varying,
    result_value_in character varying,
    child_lab_id_in integer)
  RETURNS character varying AS
$BODY$
		DECLARE
			result_string CHARACTER VARYING;
		BEGIN
			SELECT elr.loinc_condition(loinc_code_in, result_value_in, child_lab_id_in, 1) INTO result_string;

			RETURN result_string;
		END;
	$BODY$
  LANGUAGE plpgsql VOLATILE STRICT
  COST 100;
ALTER FUNCTION elr.loinc_condition(character varying, character varying, integer)
  OWNER TO droolsuser;
GRANT EXECUTE ON FUNCTION elr.loinc_condition(character varying, character varying, integer) TO droolsuser;
GRANT EXECUTE ON FUNCTION elr.loinc_condition(character varying, character varying, integer) TO "elr-rw";
REVOKE ALL ON FUNCTION elr.loinc_condition(character varying, character varying, integer) FROM public;


-- Function: elr.loinc_condition(character varying, character varying, integer, integer)

-- DROP FUNCTION elr.loinc_condition(character varying, character varying, integer, integer);

CREATE OR REPLACE FUNCTION elr.loinc_condition(
    loinc_code_in character varying,
    result_value_in character varying,
    child_lab_id_in integer,
    app_id_in integer)
  RETURNS character varying AS
$BODY$
		DECLARE
			condition_id INTEGER;
			result_string CHARACTER VARYING;
		BEGIN
			SELECT elr.loinc_condition_id(loinc_code_in, result_value_in, child_lab_id_in) INTO condition_id;

			SELECT
				m2a.coded_value
			FROM elr.vocab_master2app m2a
			INNER JOIN elr.vocab_master_condition mc ON (mc.condition = m2a.master_id)
			WHERE
				m2a.app_id = app_id_in
				AND mc.c_id = condition_id
			INTO result_string;

			RETURN result_string;
		END;
	$BODY$
  LANGUAGE plpgsql VOLATILE STRICT
  COST 100;
ALTER FUNCTION elr.loinc_condition(character varying, character varying, integer, integer)
  OWNER TO droolsuser;
GRANT EXECUTE ON FUNCTION elr.loinc_condition(character varying, character varying, integer, integer) TO droolsuser;
GRANT EXECUTE ON FUNCTION elr.loinc_condition(character varying, character varying, integer, integer) TO "elr-rw";
REVOKE ALL ON FUNCTION elr.loinc_condition(character varying, character varying, integer, integer) FROM public;


-- Function: elr.loinc_condition_id(character varying, character varying, integer)

-- DROP FUNCTION elr.loinc_condition_id(character varying, character varying, integer);

CREATE OR REPLACE FUNCTION elr.loinc_condition_id(
    loinc_code_in character varying,
    result_value_in character varying,
    child_lab_id_in integer)
  RETURNS integer AS
$BODY$
		DECLARE
			result_id INTEGER;
		BEGIN
			SELECT CASE
				WHEN
					ml.condition_from_result IS FALSE
				THEN
					(ml.trisano_condition)
				ELSE
					CASE WHEN
						CASE
							WHEN
								ml.condition_from_result IS TRUE
							THEN (
								COALESCE((SELECT DISTINCT mo.condition
								FROM elr.vocab_master_organism mo
								LEFT JOIN elr.vocab_child_organism co ON (co.organism = mo.o_id)
								WHERE (lower(co.child_code) = lower(result_value_in) AND co.lab_id = child_lab_id_in) OR (lower(mo.snomed) = lower(result_value_in)) OR (lower(mo.snomed_alt) = lower(result_value_in))), -1)
							)
							ELSE ml.trisano_condition
						END = -1
					THEN
						(ml.trisano_condition)
					ELSE (
						SELECT DISTINCT mo.condition
						FROM elr.vocab_master_organism mo
						LEFT JOIN elr.vocab_child_organism co ON (co.organism = mo.o_id)
						WHERE (lower(co.child_code) = lower(result_value_in) AND co.lab_id = child_lab_id_in) OR (lower(mo.snomed) = lower(result_value_in)) OR (lower(mo.snomed_alt) = lower(result_value_in))
					)
				END
			END
			FROM elr.vocab_master_loinc ml
			INNER JOIN elr.vocab_child_loinc cl ON (cl.master_loinc = ml.l_id)
			WHERE cl.child_loinc = loinc_code_in
			AND cl.lab_id = child_lab_id_in
			AND cl.archived is false
			INTO result_id;

			RETURN result_id;
		END;
	$BODY$
  LANGUAGE plpgsql VOLATILE STRICT
  COST 100;
ALTER FUNCTION elr.loinc_condition_id(character varying, character varying, integer)
  OWNER TO droolsuser;
GRANT EXECUTE ON FUNCTION elr.loinc_condition_id(character varying, character varying, integer) TO droolsuser;
GRANT EXECUTE ON FUNCTION elr.loinc_condition_id(character varying, character varying, integer) TO "elr-rw";
REVOKE ALL ON FUNCTION elr.loinc_condition_id(character varying, character varying, integer) FROM public;


-- Function: elr.loinc_organism(character varying, character varying, integer, integer)

-- DROP FUNCTION elr.loinc_organism(character varying, character varying, integer, integer);

CREATE OR REPLACE FUNCTION elr.loinc_organism(
    loinc_code_in character varying,
    result_value_in character varying,
    child_lab_id_in integer,
    app_id_in integer)
  RETURNS character varying AS
$BODY$
		DECLARE
			organism_id INTEGER;
			result_string CHARACTER VARYING;
		BEGIN
			SELECT elr.loinc_organism_id(loinc_code_in, result_value_in, child_lab_id_in) INTO organism_id;

			SELECT
				m2a.coded_value
			FROM elr.vocab_master2app m2a
			INNER JOIN elr.vocab_master_organism mo ON (mo.organism = m2a.master_id)
			WHERE
				m2a.app_id = app_id_in
				AND mo.o_id = organism_id
			INTO result_string;

			RETURN result_string;
		END;
	$BODY$
  LANGUAGE plpgsql VOLATILE STRICT
  COST 100;
ALTER FUNCTION elr.loinc_organism(character varying, character varying, integer, integer)
  OWNER TO droolsuser;
GRANT EXECUTE ON FUNCTION elr.loinc_organism(character varying, character varying, integer, integer) TO droolsuser;
GRANT EXECUTE ON FUNCTION elr.loinc_organism(character varying, character varying, integer, integer) TO "elr-rw";
REVOKE ALL ON FUNCTION elr.loinc_organism(character varying, character varying, integer, integer) FROM public;


-- Function: elr.loinc_organism(character varying, character varying, integer)

-- DROP FUNCTION elr.loinc_organism(character varying, character varying, integer);

CREATE OR REPLACE FUNCTION elr.loinc_organism(
    loinc_code_in character varying,
    result_value_in character varying,
    child_lab_id_in integer)
  RETURNS character varying AS
$BODY$
		DECLARE
			result_string CHARACTER VARYING;
		BEGIN
			SELECT elr.loinc_organism(loinc_code_in, result_value_in, child_lab_id_in, 1) INTO result_string;

			RETURN result_string;
		END;
	$BODY$
  LANGUAGE plpgsql VOLATILE STRICT
  COST 100;
ALTER FUNCTION elr.loinc_organism(character varying, character varying, integer)
  OWNER TO droolsuser;
GRANT EXECUTE ON FUNCTION elr.loinc_organism(character varying, character varying, integer) TO droolsuser;
GRANT EXECUTE ON FUNCTION elr.loinc_organism(character varying, character varying, integer) TO "elr-rw";
REVOKE ALL ON FUNCTION elr.loinc_organism(character varying, character varying, integer) FROM public;


-- Function: elr.loinc_organism_id(character varying, character varying, integer)

-- DROP FUNCTION elr.loinc_organism_id(character varying, character varying, integer);

CREATE OR REPLACE FUNCTION elr.loinc_organism_id(
    loinc_code_in character varying,
    result_value_in character varying,
    child_lab_id_in integer)
  RETURNS integer AS
$BODY$
		DECLARE
			result_id INTEGER;
		BEGIN
			SELECT CASE
				WHEN
					ml.organism_from_result IS FALSE
				THEN
					(ml.trisano_organism)
				ELSE
					CASE WHEN
						CASE
							WHEN
								ml.organism_from_result IS TRUE
							THEN (
								COALESCE(
									(SELECT DISTINCT mo.o_id
									FROM elr.vocab_master_organism mo
									LEFT JOIN elr.vocab_child_organism co ON (co.organism = mo.o_id)
									INNER JOIN elr.vocab_master_vocab mv ON (mo.snomed_category = mv.id)
									WHERE (mv.category = elr.vocab_category_id('snomed_category') AND mv.concept = 'Organism') AND (
									(lower(co.child_code) = lower(result_value_in) AND co.lab_id = child_lab_id_in)
									OR (lower(mo.snomed) = lower(result_value_in))
									OR (lower(mo.snomed_alt) = lower(result_value_in))))
								, -1)
							)
							ELSE ml.trisano_organism
						END = -1
					THEN
						(ml.trisano_organism)
					ELSE (
						SELECT DISTINCT mo.o_id
						FROM elr.vocab_master_organism mo
						LEFT JOIN elr.vocab_child_organism co ON (co.organism = mo.o_id)
						WHERE (lower(co.child_code) = lower(result_value_in) AND co.lab_id = child_lab_id_in) OR (lower(mo.snomed) = lower(result_value_in)) OR (lower(mo.snomed_alt) = lower(result_value_in))
					)
				END
			END
			FROM elr.vocab_master_loinc ml
			INNER JOIN elr.vocab_child_loinc cl ON (cl.master_loinc = ml.l_id)
			WHERE cl.child_loinc = loinc_code_in
			AND cl.lab_id = child_lab_id_in
			AND cl.archived is false
			INTO result_id;

			RETURN result_id;
		END;
	$BODY$
  LANGUAGE plpgsql VOLATILE STRICT
  COST 100;
ALTER FUNCTION elr.loinc_organism_id(character varying, character varying, integer)
  OWNER TO droolsuser;
GRANT EXECUTE ON FUNCTION elr.loinc_organism_id(character varying, character varying, integer) TO droolsuser;
GRANT EXECUTE ON FUNCTION elr.loinc_organism_id(character varying, character varying, integer) TO "elr-rw";
REVOKE ALL ON FUNCTION elr.loinc_organism_id(character varying, character varying, integer) FROM public;


-- Function: elr.o2m_copy_system_message_by_id(integer, integer)

-- DROP FUNCTION elr.o2m_copy_system_message_by_id(integer, integer);

CREATE OR REPLACE FUNCTION elr.o2m_copy_system_message_by_id(
  system_message_id_in integer,
  o2m_event_id_in integer)
  RETURNS integer AS
$BODY$
DECLARE
  smr elr.system_messages%rowtype;

BEGIN
  SELECT INTO smr *
  FROM elr.system_messages
  WHERE id = system_message_id_in;

  smr.id := nextval(pg_get_serial_sequence('elr.system_messages', 'id'));
  smr.copy_parent_id := system_message_id_in;
  smr.o2m_performed := TRUE;
  smr.o2m_event_id := o2m_event_id_in;

  INSERT INTO elr.system_messages VALUES (smr.*);

  RETURN smr.id;
END;
$BODY$
LANGUAGE plpgsql VOLATILE
COST 100;
ALTER FUNCTION elr.o2m_copy_system_message_by_id(integer, integer)
OWNER TO droolsuser;
GRANT EXECUTE ON FUNCTION elr.o2m_copy_system_message_by_id(integer, integer) TO droolsuser;
GRANT EXECUTE ON FUNCTION elr.o2m_copy_system_message_by_id(integer, integer) TO "elr-rw";
REVOKE ALL ON FUNCTION elr.o2m_copy_system_message_by_id(integer, integer) FROM public;


-- Function: elr.o2m_copy_system_message_by_id(integer)

-- DROP FUNCTION elr.o2m_copy_system_message_by_id(integer);

CREATE OR REPLACE FUNCTION elr.o2m_copy_system_message_by_id(
  system_message_id_in integer)
  RETURNS integer AS
$BODY$
DECLARE
  smr elr.system_messages%rowtype;

BEGIN
  SELECT INTO smr *
  FROM elr.system_messages
  WHERE id = system_message_id_in;

  smr.id := nextval(pg_get_serial_sequence('elr.system_messages', 'id'));
  smr.copy_parent_id := system_message_id_in;
  smr.o2m_performed := TRUE;

  INSERT INTO elr.system_messages VALUES (smr.*);

  RETURN smr.id;
END;
$BODY$
LANGUAGE plpgsql VOLATILE
COST 100;
ALTER FUNCTION elr.o2m_copy_system_message_by_id(integer)
OWNER TO droolsuser;
GRANT EXECUTE ON FUNCTION elr.o2m_copy_system_message_by_id(integer) TO droolsuser;
GRANT EXECUTE ON FUNCTION elr.o2m_copy_system_message_by_id(integer) TO "elr-rw";
REVOKE ALL ON FUNCTION elr.o2m_copy_system_message_by_id(integer) FROM public;


-- Function: elr.person_score(character varying, character varying, character varying, character varying, date, date)

-- DROP FUNCTION elr.person_score(character varying, character varying, character varying, character varying, date, date);

CREATE OR REPLACE FUNCTION elr.person_score(
    fn_in character varying,
    fn_match character varying,
    ln_in character varying,
    ln_match character varying,
    bd_in date,
    bd_match date)
  RETURNS integer AS
$BODY$

    DECLARE

        score INTEGER;
        distance INTEGER;
        distance_rev INTEGER;
        distance_fn INTEGER := 0;
        distance_ln INTEGER := 0;
        distance_split INTEGER := 100;
        input_len NUMERIC;
        match_len NUMERIC;
        name_parts_in text[];

    BEGIN

        IF fn_in IS NULL THEN
           fn_in := '';
        END IF;
        IF ln_in IS NULL THEN
           ln_in := '';
        END IF;

        IF fn_match IS NOT NULL THEN
            distance_fn := levenshtein(lower(trim(fn_in)),lower(trim(fn_match)));
            IF distance_fn > 0 THEN
                name_parts_in := regexp_split_to_array(trim(both ' ' from fn_in), '[ ,-]');
                distance_split := 100;
                FOR c IN 1..3 LOOP
                    IF position(lower(name_parts_in[c]) in lower(trim(fn_match))) > 0 THEN
                        distance_split := 4;
                        EXIT;
                    END IF;
                end loop;
                distance_fn := least(distance_split, distance_fn);
            END IF;
        END IF;
        IF fn_match IS NULL THEN
            distance_fn := char_length(trim(fn_in));
        END IF;

        IF ln_match IS NOT NULL THEN
            distance_ln := levenshtein(lower(trim(ln_in)),lower(trim(ln_match)));
            IF distance_ln > 0 THEN
                name_parts_in := regexp_split_to_array(trim(both ' ' from ln_in), '[ ,-]');
                distance_split := 100;
                FOR c IN 1..3 LOOP
                    IF position(lower(name_parts_in[c]) in lower(ln_match)) > 0 THEN
                        distance_split := 4;
                        EXIT;
                    END IF;
                end loop;
                distance_ln := least(distance_split, distance_ln);
            END IF;
        END IF;
        IF ln_match IS NULL THEN
            distance_ln := char_length(trim(ln_in));
        END IF;

        distance := distance_ln + distance_fn;

--Now swap the fn_in and ln_in
        IF fn_match IS NOT NULL THEN
            distance_fn := levenshtein(lower(trim(ln_in)),lower(trim(fn_match)));
            IF distance_fn > 0 THEN
                name_parts_in := regexp_split_to_array(trim(both ' ' from ln_in), '[ ,-]');
                distance_split := 100;
                FOR c IN 1..3 LOOP
                    IF position(lower(name_parts_in[c]) in lower(trim(fn_match))) > 0 THEN
                        distance_split := 4;
                        EXIT;
                    END IF;
                end loop;
                distance_fn := least(distance_split, distance_fn);
            END IF;
        END IF;
        IF fn_match IS NULL THEN
            distance_fn := char_length(trim(ln_in));
        END IF;

        IF ln_match IS NOT NULL THEN
            distance_ln := levenshtein(lower(trim(fn_in)),lower(trim(ln_match)));
            IF distance_ln > 0 THEN
                name_parts_in := regexp_split_to_array(trim(both ' ' from fn_in), '[ ,-]');
                distance_split := 100;
                FOR c IN 1..3 LOOP
                    IF position(lower(name_parts_in[c]) in lower(ln_match)) > 0 THEN
                        distance_split := 4;
                        EXIT;
                    END IF;
                end loop;
                distance_ln := least(distance_split, distance_ln);
            END IF;
        END IF;
        IF ln_match IS NULL THEN
            distance_ln := char_length(trim(fn_in));
        END IF;

        distance_rev := distance_ln + distance_fn;

        input_len := char_length(trim(fn_in)) + char_length(trim(ln_in));

        IF fn_match IS NOT NULL AND ln_match IS NOT NULL THEN
            match_len := char_length(trim(fn_match)) + char_length(trim(ln_match));
        END IF;
        IF fn_match IS NOT NULL AND ln_match IS NULL THEN
            match_len := char_length(fn_match);
        END IF;
        IF ln_match IS NOT NULL AND fn_match IS NULL THEN
            match_len := char_length(ln_match);
        END IF;

--Add a modifier to reduce the penalty for short names.
        input_len := input_len + 6;
        match_len := match_len + 6;

--Calculate the score based on the name part of the match only
        IF distance <= distance_rev THEN
		IF(input_len > match_len) THEN
		    score := round((1 - distance / input_len) * 100);
		END IF;

		IF(input_len <= match_len) THEN
		    score := round((1 - distance / match_len) * 100);
		END IF;
	ELSE
		IF(input_len > match_len) THEN
		    score := round((1 - distance_rev / input_len) * 100);
		END IF;

		IF(input_len <= match_len) THEN
		    score := round((1 - distance_rev / match_len) * 100);
		END IF;
		score := score - 15;
	END IF;

--Now modify the score with weighted birth date criteria
	IF bd_in IS NOT NULL AND bd_match IS NULL  THEN
	    score := score - 10;
	END IF;

        IF bd_in IS NOT NULL AND bd_match IS NOT NULL  THEN
            score := score - levenshtein(to_char(bd_in, 'YYYY-MM-DD'),to_char(bd_match, 'YYYY-MM-DD')) * 10;
            IF (score < 0) THEN
                score := 0;
            END IF;
        END IF;

    RETURN score;

    END;

$BODY$
  LANGUAGE plpgsql STABLE
  COST 100;
ALTER FUNCTION elr.person_score(character varying, character varying, character varying, character varying, date, date)
  OWNER TO droolsuser;
GRANT EXECUTE ON FUNCTION elr.person_score(character varying, character varying, character varying, character varying, date, date) TO droolsuser;
GRANT EXECUTE ON FUNCTION elr.person_score(character varying, character varying, character varying, character varying, date, date) TO "elr-rw";
REVOKE ALL ON FUNCTION elr.person_score(character varying, character varying, character varying, character varying, date, date) FROM public;




CREATE OR REPLACE FUNCTION elr.graylist_score_202102(
	fn_in character varying,
	fn_match character varying,
	ln_in character varying,
	ln_match character varying,
	bd_in date,
	bd_match date,
	mn_in character varying DEFAULT NULL::character varying,
	mn_match character varying DEFAULT NULL::character varying,
	sex_in character varying DEFAULT NULL::character varying,
	sex_match character varying DEFAULT NULL::character varying)
    RETURNS integer
    LANGUAGE 'plpgsql'
    COST 100
    STABLE PARALLEL SAFE
AS $BODY$
DECLARE

        score INTEGER;
		fwfw REAL;
		fpfp REAL;
		lwlw REAL;
		lplp REAL;
		npnp REAL;
		npnp_in REAL;
		npnp_cnt INTEGER;
		ln_match_part_cnt INTEGER;
		mwfw REAL;
		fwmw REAL;
		fwlw REAL;
		lwfw REAL;
		fwnn REAL;
		name_parts RECORD;
		ln_in_part TEXT;
		ln_match_part TEXT;
		year_in text;
		month_in text;
		day_in text;
		year_match text;
		month_match text;
		day_match text;
		year_switch boolean;
		daymonth_switch boolean;
		day_switch boolean;

		meta_fi text;
		meta_fm text;
		dmeta_fi text;
		dmeta_fm text;
		dmetaa_fi text;
		dmetaa_fm text;
		soundex_fi text;
		soundex_fm text;

		meta_li text;
		meta_lm text;
		dmeta_li text;
		dmeta_lm text;
		dmetaa_li text;
		dmetaa_lm text;
		soundex_li text;
		soundex_lm text;

    BEGIN

		fn_in = LEFT(trim(lower(regexp_replace(translate(COALESCE(fn_in,''), ',-_''."()`', '   '), '\s\s+', ' ', 'g'))),255);
		mn_in = LEFT(trim(lower(regexp_replace(translate(COALESCE(mn_in,''), ',-_''."()`', '   '), '\s\s+', ' ', 'g'))),255);
		ln_in = LEFT(trim(lower(regexp_replace(translate(COALESCE(ln_in,''), ',-_''."()`', '   '), '\s\s+', ' ', 'g'))),255);
		fn_match = LEFT(trim(lower(regexp_replace(translate(COALESCE(fn_match,''), ',-_''."()`', '   '), '\s\s+', ' ', 'g'))),255);
		mn_match = LEFT(trim(lower(regexp_replace(translate(COALESCE(mn_match,''), ',-_''."()`', '   '), '\s\s+', ' ', 'g'))),255);
		ln_match = LEFT(trim(lower(regexp_replace(translate(COALESCE(ln_match,''), ',-_''."()`', '   '), '\s\s+', ' ', 'g'))),255);

		meta_fi = NULLIF(metaphone(fn_in,20),'');
		meta_fm = NULLIF(metaphone(fn_match,20),'');
		dmeta_fi = NULLIF(dmetaphone(fn_in),'');
		dmeta_fm = NULLIF(dmetaphone(fn_match),'');
		dmetaa_fi = NULLIF(dmetaphone_alt(fn_in),'');
		dmetaa_fm = NULLIF(dmetaphone_alt(fn_match),'');
		soundex_fi = NULLIF(soundex(fn_in),'');
		soundex_fm = NULLIF(soundex(fn_match),'');

		meta_li = NULLIF(metaphone(ln_in,20),'');
		meta_lm = NULLIF(metaphone(ln_match,20),'');
		dmeta_li = NULLIF(dmetaphone(ln_in),'');
		dmeta_lm = NULLIF(dmetaphone(ln_match),'');
		dmetaa_li = NULLIF(dmetaphone_alt(ln_in),'');
		dmetaa_lm = NULLIF(dmetaphone_alt(ln_match),'');
		soundex_li = NULLIF(soundex(ln_in),'');
		soundex_lm = NULLIF(soundex(ln_match),'');

		fwfw = similarity(fn_in, fn_match);
		CASE
			WHEN fwfw < .9 AND meta_fi = meta_fm THEN fwfw = .9;
			WHEN fwfw < .7 AND (dmeta_fi = dmeta_fm OR  dmetaa_fi = dmetaa_fm OR soundex_fi = soundex_fm OR levenshtein(fn_in, fn_match) = 1) THEN fwfw = .7;
			WHEN fwfw < .5 AND (levenshtein(fn_in, fn_match) = 2 OR (length(meta_fi) > 1 AND length(meta_fm) > 1 AND (position(meta_fi in meta_fm) > 0 OR position(meta_fm in meta_fi) > 0))) THEN fwfw = .5;
			ELSE
		END CASE;
		lwlw = similarity(ln_in, ln_match);
		CASE
			WHEN lwlw < .9 AND meta_li = meta_lm THEN lwlw = .9;
			WHEN lwlw < .8 AND (dmeta_li = dmeta_lm OR  dmetaa_li = dmetaa_lm OR soundex_li = soundex_lm OR levenshtein(ln_in, ln_match) = 1) THEN lwlw = .8;
			WHEN lwlw < .5 AND (levenshtein(ln_in, ln_match) = 2 OR (length(meta_li) > 1 AND length(meta_lm) > 1 AND (position(meta_li in meta_lm) > 0 OR position(meta_lm in meta_li) > 0))) THEN lwlw = .5;
			ELSE
		END CASE;
		mwfw = similarity(mn_in, fn_match);
		CASE
			WHEN mwfw >= .8 THEN mwfw = .6;
			ELSE mwfw = 0;
		END CASE;
		fwmw = similarity(fn_in, mn_match);
		CASE
			WHEN fwmw >= .8 THEN fwmw = .6;
			ELSE fwmw = 0;
		END CASE;
		fwlw = similarity(fn_in, ln_match);
		CASE
			WHEN fwlw < .9 AND meta_fi = meta_lm THEN fwlw = .9;
			WHEN fwlw < .7 AND (dmeta_fi = dmeta_lm OR  dmetaa_fi = dmetaa_lm OR soundex_fi = soundex_lm OR levenshtein(fn_in, ln_match) = 1) THEN fwlw = .7;
			WHEN fwlw < .5 AND levenshtein(fn_in, ln_match) = 2 AND fn_in <> '' AND ln_match <> '' THEN fwlw = .5;
			ELSE
		END CASE;
		lwfw = similarity(ln_in, fn_match);
		CASE
			WHEN lwfw < .9 AND meta_li = meta_fm THEN lwfw = .9;
			WHEN lwfw < .7 AND (dmeta_li = dmeta_fm OR  dmetaa_li = dmetaa_fm OR soundex_li = soundex_fm OR levenshtein(ln_in, fn_match) = 1) THEN lwfw = .7;
			WHEN lwfw < .5 AND levenshtein(ln_in, fn_match) = 2 AND ln_in <> '' AND fn_match <> '' THEN lwfw = .5;
			ELSE
		END CASE;

		CASE
			WHEN EXISTS (SELECT 1 FROM elr.nickname WHERE fn_in = name AND fn_match = nickname) THEN
				fwnn = 1;
			ELSE
				fwnn = 0;
		END CASE;

		fpfp = 0;
		FOR name_parts IN
			SELECT np_in, np_match FROM
			regexp_split_to_table(fn_in, '\s+') np_in CROSS JOIN
			regexp_split_to_table(fn_match, '\s+') np_match WHERE np_in NOT IN ('boy','girl','baby','father','mother','mom','dad','brother','sister','bro','sis','van','jr','sr','de','la','los','st','san','ii','iii') AND
			np_match NOT IN ('boy','girl','baby','father','mother','mom','dad','brother','sister','bro','sis','van','jr','sr','de','la','los','st','san','ii','iii')
			LOOP
				npnp = similarity(name_parts.np_in, name_parts.np_match);

				CASE
					WHEN npnp < .9 AND NULLIF(metaphone(name_parts.np_in,20),'') = NULLIF(metaphone(name_parts.np_match,20),'') THEN npnp = .9;
					WHEN npnp < .7 AND (NULLIF(dmetaphone(name_parts.np_in),'') = NULLIF(dmetaphone(name_parts.np_match),'') OR NULLIF(dmetaphone_alt(name_parts.np_in),'') = NULLIF(dmetaphone_alt(name_parts.np_match),'') OR NULLIF(soundex(name_parts.np_in),'') = NULLIF(soundex(name_parts.np_match),'') OR levenshtein(name_parts.np_in, name_parts.np_match) = 1) THEN npnp = .7;
					WHEN npnp < .5 AND levenshtein(name_parts.np_in, name_parts.np_match) = 2 THEN npnp = .5;
					ELSE
				END CASE;
				fpfp = GREATEST(fpfp, npnp);
		END LOOP;

		lplp = 0;
		npnp_cnt = 0;
		FOR ln_in_part IN
			SELECT np_in FROM
			regexp_split_to_table(ln_in, '\s+') np_in WHERE np_in NOT IN ('boy','girl','baby','father','mother','mom','dad','brother','sister','bro','sis','van','jr','sr','de','la','los','st','san','ii','iii')
			LOOP
				npnp_in = 0;
				ln_match_part_cnt = 0;
				FOR ln_match_part IN
					SELECT np_match FROM
					regexp_split_to_table(ln_match, '\s+') np_match WHERE np_match NOT IN ('boy','girl','baby','father','mother','mom','dad','brother','sister','bro','sis','van','jr','sr','de','la','los','st','san','ii','iii')
					LOOP
						npnp = similarity(ln_in_part, ln_match_part);
						CASE
							WHEN npnp < .9 AND NULLIF(metaphone(ln_in_part,20),'') = NULLIF(metaphone(ln_match_part,20),'') THEN npnp = .9;
							WHEN npnp < .7 AND (NULLIF(dmetaphone(ln_in_part),'') = NULLIF(dmetaphone(ln_match_part),'') OR  NULLIF(dmetaphone_alt(ln_in_part),'') = NULLIF(dmetaphone_alt(ln_match_part),'') OR NULLIF(soundex(ln_in_part),'') = NULLIF(soundex(ln_match_part),'') OR levenshtein(ln_in_part, ln_match_part) = 1) THEN npnp = .7;
							WHEN npnp < .5 AND levenshtein(ln_in_part, ln_match_part) = 2 THEN npnp = .5;
							ELSE
						END CASE;
						npnp_in = GREATEST(npnp_in, npnp);
						ln_match_part_cnt = ln_match_part_cnt + 1;
					END LOOP;
					lplp = lplp + npnp_in;
					npnp_cnt = npnp_cnt + 1;
		END LOOP;
		CASE
			WHEN npnp_cnt = 0 OR ln_match_part_cnt = 1 THEN lplp = LEAST(lplp, 1);
			ELSE lplp = lplp / npnp_cnt;
		END CASE;

		score = GREATEST((fwfw + lwlw) / 2 * 100,
						 (fpfp + lplp) / 2 * 100,
						 (mwfw + lwlw) / 2 * 100,
						 (fwmw + lwlw) / 2 * 100,
						 (fwnn + lwlw) / 2 * 100 - 1,
						 (lwfw + fwlw) / 2 * 100 - 6);
		--RAISE NOTICE 'Score:% fwfw:% lwlw:% fpfp:% lplp:% mwfw:% fwmw:% fwnn:% lwfw:% fwlw:%', score, fwfw, lwlw, fpfp, lplp, mwfw, fwmw, fwnn, lwfw, fwlw;

-- Handle special cases in the names to make sure they do not automatch
		IF soundex_fi = 'M530' AND soundex_li = 'M530' THEN
			score = LEAST(score, 84);
		END IF;

		IF (fn_in ~ '\y(boy|girl|baby)\y' OR fn_match ~ '\y(boy|girl|baby)\y') AND fn_in <> fn_match THEN
			score = LEAST(score, 84);
		END IF;

		IF (ln_in ~ '\d') THEN
			score = score - levenshtein(regexp_replace(ln_in, '[^\d]','','g'),regexp_replace(ln_match, '[^\d]','','g')) * 30;
		END IF;

		IF (fn_in ~ '\d') THEN
			score = score - levenshtein(regexp_replace(fn_in, '[^\d]','','g'),regexp_replace(fn_match, '[^\d]','','g')) * 30;
		END IF;

-- Check the genders and reduce score if they are mismatched
		IF (sex_in = 'M' AND sex_match = 'F') OR (sex_in = 'F' AND sex_match = 'M') THEN
			score = score - 6;
		END IF;

--Now modify the score with weighted birth date criteria
		IF bd_in IS NULL OR bd_match IS NULL  THEN
			score = score - 20;
		END IF;

        IF bd_in IS NOT NULL AND bd_match IS NOT NULL AND bd_in <> bd_match THEN
			year_in = RIGHT(date_part('YEAR', bd_in)::text, 2);
			year_match = RIGHT(date_part('YEAR', bd_match)::text, 2);
			month_in = LPAD(date_part('MONTH', bd_in)::text, 2, '0');
			month_match = LPAD(date_part('MONTH', bd_match)::text, 2, '0');
			day_in = LPAD(date_part('DAY', bd_in)::text, 2, '0');
			day_match = LPAD(date_part('DAY', bd_match)::text, 2, '0');
			year_switch = year_in = REVERSE(year_match) AND year_in <> year_match AND LEFT(to_char(bd_in, 'DDMMYYYY'),6) = LEFT(to_char(bd_match, 'DDMMYYYY'),6);
			daymonth_switch = month_in = day_match AND day_in = month_match AND day_in <> day_match AND month_in <> month_match AND date_part('YEAR', bd_in) = date_part('YEAR', bd_match);
			day_switch = day_in = REVERSE(day_match) AND day_in <> day_match AND to_char(bd_in, 'YYYYMM') = to_char(bd_match, 'YYYYMM');
			IF (year_switch AND NOT daymonth_switch AND NOT day_switch) OR (NOT year_switch AND daymonth_switch AND NOT day_switch) OR (NOT year_switch AND NOT daymonth_switch AND day_switch)
			THEN
			    score = score - levenshtein(to_char(bd_in, 'YYYY-MM-DD'),to_char(bd_match, 'YYYY-MM-DD')) * 3;
			ELSIF  levenshtein(to_char(bd_in, 'YYYY-MM-DD'),to_char(bd_match, 'YYYY-MM-DD')) = 1 THEN
				score = score - 6;
			ELSE
				score = score - levenshtein(to_char(bd_in, 'YYYY-MM-DD'),to_char(bd_match, 'YYYY-MM-DD')) * 16;
			END IF;
        END IF;

		IF (score < 0) THEN
			score = 0;
		END IF;

		RETURN score;
END;
$BODY$;

ALTER FUNCTION elr.graylist_score_202102(character varying, character varying, character varying, character varying, date, date, character varying, character varying, character varying, character varying)
    OWNER TO droolsuser;

COMMENT ON FUNCTION elr.graylist_score_202102(character varying, character varying, character varying, character varying, date, date, character varying, character varying, character varying, character varying)
    IS 'This function is the second part of a process for person searches.
		It performs a series of calculations to determine how closely information from two people match.

		pg_trgm.similarity_threshold and pg_trgm.word_similarity_threshold should be set to ''0.2'' for the user running this function.';

CREATE OR REPLACE FUNCTION elr.graylist_search_202102(
	fn_in character varying,
	ln_in character varying,
	mn_in character varying DEFAULT NULL::character varying)
    RETURNS SETOF integer
    LANGUAGE 'plpgsql'
    COST 100
    VOLATILE PARALLEL RESTRICTED
    ROWS 1000

AS $BODY$
DECLARE

qry_where TEXT;
fname_parts RECORD;
lname_parts RECORD;
fp BOOLEAN;
lp BOOLEAN;
nicknames TEXT;

BEGIN

fn_in = trim(lower(regexp_replace(translate(COALESCE(fn_in,''), ',-_''."()`', '   '), '\s\s+', ' ', 'g')));
mn_in = trim(lower(regexp_replace(translate(COALESCE(mn_in,''), ',-_''."()`', '   '), '\s\s+', ' ', 'g')));
ln_in = trim(lower(regexp_replace(translate(COALESCE(ln_in,''), ',-_''."()`', '   '), '\s\s+', ' ', 'g')));

qry_where = '';
--(fpf OR fpm) AND lpl
IF fn_in = '' THEN --If there is no first name to check against then just do lpl
	IF ln_in <> '' THEN
		qry_where = qry_where || E'(';
		lp = false;
		FOR lname_parts IN
		SELECT lpart FROM regexp_split_to_table(ln_in, '\s+') lpart
		LOOP
			IF lp THEN qry_where = qry_where || 'OR '; END IF;
			qry_where = qry_where || quote_literal(lname_parts.lpart) || E'::text <% trim(lower(regexp_replace(translate(sm.lname::text, \',-_\'\'."()`\', \'   \'), \'\\s\\s+\', \' \', \'g\'))) ';
			lp = true;
		END LOOP;
		qry_where = qry_where || ')';
	END IF;
ELSE
	--fpf,fpm
	fp = false;
	qry_where = qry_where || E'(';
	FOR fname_parts IN
	SELECT fpart FROM regexp_split_to_table(fn_in, '\s+') fpart
	LOOP
		IF fp THEN qry_where = qry_where || ' OR '; END IF;
		qry_where = qry_where || quote_literal(fname_parts.fpart) || E'::text <% trim(lower(regexp_replace(translate(sm.fname::text, \',-_\'\'."()`\', \'   \'), \'\\s\\s+\', \' \', \'g\')))';
		qry_where = qry_where || E' OR ' || quote_literal(fname_parts.fpart) || E'::text <% trim(lower(regexp_replace(translate(sm.mname::text, \',-_\'\'."()`\', \'   \'), \'\\s\\s+\', \' \', \'g\')))';
		fp = true;
	END LOOP;
	qry_where = qry_where || ')';
	--lpl
	IF ln_in <> '' THEN
		qry_where = qry_where || E' AND (';
		lp = false;
		FOR lname_parts IN
		SELECT lpart FROM regexp_split_to_table(ln_in, '\s+') lpart
		LOOP
			IF lp THEN qry_where = qry_where || 'OR '; END IF;
			qry_where = qry_where || quote_literal(lname_parts.lpart) || E'::text <% trim(lower(regexp_replace(translate(sm.lname::text, \',-_\'\'."()`\', \'   \'), \'\\s\\s+\', \' \', \'g\'))) ';
			lp = true;
		END LOOP;
		qry_where = qry_where || ')';
	END IF;
END IF;
--fpl AND lpf
IF fn_in <> '' AND ln_in <> '' THEN
	qry_where = qry_where || ' OR (';
	fp = false;
	FOR fname_parts IN
	SELECT fpart FROM regexp_split_to_table(fn_in, '\s+') fpart
	LOOP
		IF fp THEN qry_where = qry_where || ' OR '; END IF;
		qry_where = qry_where || quote_literal(fname_parts.fpart) || E'::text <% trim(lower(regexp_replace(translate(sm.lname::text, \',-_\'\'."()`\', \'   \'), \'\\s\\s+\', \' \', \'g\')))';
		fp = true;
	END LOOP;
	qry_where = qry_where || E') AND (';
	lp = false;
	FOR lname_parts IN
	SELECT lpart FROM regexp_split_to_table(ln_in, '\s+') lpart
	LOOP
		IF lp THEN qry_where = qry_where || 'OR '; END IF;
		qry_where = qry_where || quote_literal(lname_parts.lpart) || E'::text <% trim(lower(regexp_replace(translate(sm.fname::text, \',-_\'\'."()`\', \'   \'), \'\\s\\s+\', \' \', \'g\'))) ';
		lp = true;
	END LOOP;
	qry_where = qry_where || ')';
END IF;
--(ff OR fn OR mf OR mm) AND ll
IF fn_in <> '' OR mn_in <> '' THEN
	qry_where = qry_where || E' OR (';
	--ff,fn
	IF fn_in <> '' THEN
		qry_where = qry_where || quote_literal(fn_in) || E' % trim(lower(regexp_replace(translate(sm.fname::text, \',-_\'\'."()`\', \'   \'), \'\\s\\s+\', \' \', \'g\')))';
		SELECT INTO nicknames string_agg(nickname.nickname, ''',''') FROM elr.nickname WHERE nickname.name = ANY(regexp_split_to_array(translate(fn_in, ',-_', '   '), '\s+'));
		IF nicknames IS NOT NULL THEN
			qry_where = qry_where || E' OR trim(lower(regexp_replace(translate(sm.fname::text, \',-_\'\'."()`\', \'   \'), \'\\s\\s+\', \' \', \'g\'))) IN (\'' || nicknames || E'\')';
		END IF;
	END IF;
	--mf,mm
	IF mn_in <> '' THEN
		IF fn_in <> '' THEN qry_where = qry_where || E' OR '; END IF;
		qry_where = qry_where || quote_literal(mn_in) || E' % trim(lower(regexp_replace(translate(sm.fname::text, \',-_\'\'."()`\', \'   \'), \'\\s\\s+\', \' \', \'g\')))';
		qry_where = qry_where || E' OR ' || quote_literal(mn_in) || E' % trim(lower(regexp_replace(translate(sm.mname::text, \',-_\'\'."()`\', \'   \'), \'\\s\\s+\', \' \', \'g\')))';
	END IF;
	qry_where = qry_where || E')';
END IF;
IF ln_in <> '' THEN
	IF fn_in <> '' OR mn_in <> '' THEN
		qry_where = qry_where || E' AND ';
	ELSE
		qry_where = qry_where || E' OR ';
	END IF;
	qry_where = qry_where || quote_literal(ln_in) || E' % trim(lower(regexp_replace(translate(sm.lname::text, \',-_\'\'."()`\', \'   \'), \'\\s\\s+\', \' \', \'g\')))';
END IF;

IF qry_where = '' THEN
RAISE NOTICE 'No search criteria was found % % %;', fn_in, ln_in, mn_in;
RETURN;
END IF;

qry_where = 'sm.final_status = 2 AND sm.vocab_app_id = 2 AND (' || qry_where || ')';
--RAISE NOTICE 'SELECT sm.id AS sm_id FROM elr.system_messages sm WHERE %;', qry_where;

RETURN QUERY EXECUTE 'SELECT sm.id AS sm_id FROM elr.system_messages sm WHERE ' || qry_where;

        RETURN;
END;
$BODY$;

ALTER FUNCTION elr.graylist_search_202102(character varying, character varying, character varying)
    OWNER TO droolsuser;

COMMENT ON FUNCTION elr.graylist_search_202102(character varying, character varying, character varying)
    IS 'This function is the start of a process for person searches.
It performs a broad search to identify potential matches that would usually be then scored using the person_score function.

The original logic for this function is this:
fp<%f AND lp<%l OR fp<%m AND lp<%l OR fp<%l AND lp<%f OR f%f AND l%l OR m%f AND l%l OR m%m AND l%l OR f=n AND l%l

The left value is the incoming value and the right is the existing database value to compare against.
fp and lp are the parts of the name split on spaces.

This is the reduced logic to speed up the queries
(fpf OR fpm) AND lpl OR fpl AND lpf OR (ff OR mf OR mm OR fn) AND ll

pg_trgm.similarity_threshold and pg_trgm.word_similarity_threshold should be set to ''0.2'' for the user running this function.';




-- FUNCTION: elr.graylist_search_201906(character varying, character varying, character varying)

-- DROP FUNCTION elr.graylist_search_201906(character varying, character varying, character varying);

CREATE OR REPLACE FUNCTION elr.graylist_search_201906(
	fn_in character varying,
	ln_in character varying,
	mn_in character varying DEFAULT NULL::character varying)
    RETURNS SETOF integer
    LANGUAGE 'plpgsql'

    COST 100
    VOLATILE
    ROWS 1000
AS $BODY$

    DECLARE

	qry_where TEXT;
	fname_parts RECORD;
	lname_parts RECORD;
	fp BOOLEAN;
	lp BOOLEAN;

	BEGIN
		SET LOCAL pg_trgm.similarity_threshold TO '0.2';
		SET LOCAL pg_trgm.word_similarity_threshold TO '0.2';
		fn_in = trim(lower(regexp_replace(translate(COALESCE(fn_in,''), '''.,"()', ''), '\s\s+', ' ', 'g')));
		mn_in = trim(lower(regexp_replace(translate(COALESCE(mn_in,''), '''.,"()', ''), '\s\s+', ' ', 'g')));
		ln_in = trim(lower(regexp_replace(translate(COALESCE(ln_in,''), '''.,"()', ''), '\s\s+', ' ', 'g')));

		qry_where = 'sm.final_status = 2 AND sm.vocab_app_id = 2 AND (';
		--fpf-lpl
		fp = false;
		FOR fname_parts IN
			SELECT fpart FROM regexp_split_to_table(translate(fn_in, ',-_', '   '), '\s+') fpart
		LOOP
			IF fp THEN qry_where = qry_where || ' OR '; END IF;
			qry_where = qry_where || E'(' || quote_literal(fname_parts.fpart) || E'::text <% trim(lower(regexp_replace(translate(sm.fname::text, \'\'\'.,"()\', \'\'), \'\\s\\s+\', \' \', \'g\'))) AND (';
			lp = false;
			FOR lname_parts IN
				SELECT lpart FROM regexp_split_to_table(translate(ln_in, ',-_', '   '), '\s+') lpart
			LOOP
				IF lp THEN qry_where = qry_where || 'OR '; END IF;
				qry_where = qry_where || quote_literal(lname_parts.lpart) || E'::text <% trim(lower(regexp_replace(translate(sm.lname::text, \'\'\'.,"()\', \'\'), \'\\s\\s+\', \' \', \'g\'))) ';
				lp = true;
			END LOOP;
			fp = true;
			qry_where = qry_where || '))';
		END LOOP;
		--fpm-lpl
		qry_where = qry_where || ' OR ';
		fp = false;
		FOR fname_parts IN
			SELECT fpart FROM regexp_split_to_table(translate(fn_in, ',-_', '   '), '\s+') fpart
		LOOP
			IF fp THEN qry_where = qry_where || ' OR '; END IF;
			qry_where = qry_where || E'(' || quote_literal(fname_parts.fpart) || E'::text <% trim(lower(regexp_replace(translate(sm.mname::text, \'\'\'.,"()\', \'\'), \'\\s\\s+\', \' \', \'g\'))) AND (';
			lp = false;
			FOR lname_parts IN
				SELECT lpart FROM regexp_split_to_table(translate(ln_in, ',-_', '   '), '\s+') lpart
			LOOP
				IF lp THEN qry_where = qry_where || 'OR '; END IF;
				qry_where = qry_where || quote_literal(lname_parts.lpart) || E'::text <% trim(lower(regexp_replace(translate(sm.lname::text, \'\'\'.,"()\', \'\'), \'\\s\\s+\', \' \', \'g\'))) ';
				lp = true;
			END LOOP;
			fp = true;
			qry_where = qry_where || '))';
		END LOOP;
		--fpl-lpf
		qry_where = qry_where || ' OR ';
		fp = false;
		FOR fname_parts IN
			SELECT fpart FROM regexp_split_to_table(translate(fn_in, ',-_', '   '), '\s+') fpart
		LOOP
			IF fp THEN qry_where = qry_where || ' OR '; END IF;
			qry_where = qry_where || E'(' || quote_literal(fname_parts.fpart) || E'::text <% trim(lower(regexp_replace(translate(sm.lname::text, \'\'\'.,"()\', \'\'), \'\\s\\s+\', \' \', \'g\'))) AND (';
			lp = false;
			FOR lname_parts IN
				SELECT lpart FROM regexp_split_to_table(translate(ln_in, ',-_', '   '), '\s+') lpart
			LOOP
				IF lp THEN qry_where = qry_where || 'OR '; END IF;
				qry_where = qry_where || quote_literal(lname_parts.lpart) || E'::text <% trim(lower(regexp_replace(translate(sm.fname::text, \'\'\'.,"()\', \'\'), \'\\s\\s+\', \' \', \'g\'))) ';
				lp = true;
			END LOOP;
			fp = true;
			qry_where = qry_where || '))';
		END LOOP;
		qry_where = qry_where || ' OR ';
		--ff-ll
		qry_where = qry_where || E'(' || quote_literal(fn_in) || E' % trim(lower(regexp_replace(translate(sm.fname::text, \'\'\'.,"()\', \'\'), \'\\s\\s+\', \' \', \'g\'))) AND ';
		qry_where = qry_where || E'(' || quote_literal(ln_in) || E' % trim(lower(regexp_replace(translate(sm.lname::text, \'\'\'.,"()\', \'\'), \'\\s\\s+\', \' \', \'g\'))))) OR ';
		--mf-ll
		qry_where = qry_where || E'(' || quote_literal(mn_in) || E' % trim(lower(regexp_replace(translate(sm.fname::text, \'\'\'.,"()\', \'\'), \'\\s\\s+\', \' \', \'g\'))) AND ';
		qry_where = qry_where || E'(' || quote_literal(ln_in) || E' % trim(lower(regexp_replace(translate(sm.lname::text, \'\'\'.,"()\', \'\'), \'\\s\\s+\', \' \', \'g\'))))) OR (';
		--NN match
		qry_where = qry_where || E'trim(lower(regexp_replace(translate(sm.fname::text, \'\'\'.,"()\', \'\'), \'\\s\\s+\', \' \', \'g\'))) IN (';
		qry_where = qry_where || E'SELECT nickname.nickname FROM elr.nickname WHERE nickname.name IN (';
		qry_where = qry_where || E'SELECT regexp_split_to_table(translate(lower(' || quote_literal(fn_in) || E'), \',-_\', \'   \'), \'\\s+\'))';
		qry_where = qry_where || ') AND (' || quote_literal(ln_in) || E' % trim(lower(regexp_replace(translate(sm.lname::text, \'\'\'.,\"()\', \'\'), \'\\s\\s+\', \' \', \'g\')))))';
		--Close the block
		qry_where = qry_where || ')';

		--RAISE NOTICE 'SELECT id AS sm_id FROM elr.system_messages sm WHERE %;', qry_where;

		RETURN QUERY EXECUTE 'SELECT id AS sm_id FROM elr.system_messages sm WHERE ' || qry_where;
END;
$BODY$;

ALTER FUNCTION elr.graylist_search_201906(character varying, character varying, character varying)
    OWNER TO droolsuser;




-- FUNCTION: public.graylist_score_201906(character varying, character varying, character varying, character varying, date, date, character varying, character varying, character varying, character varying)

-- DROP FUNCTION public.graylist_score_201906(character varying, character varying, character varying, character varying, date, date, character varying, character varying, character varying, character varying);

CREATE OR REPLACE FUNCTION elr.graylist_score_201906(
	fn_in character varying,
	fn_match character varying,
	ln_in character varying,
	ln_match character varying,
	bd_in date,
	bd_match date,
	mn_in character varying DEFAULT NULL::character varying,
	mn_match character varying DEFAULT NULL::character varying,
	sex_in character varying DEFAULT NULL::character varying,
	sex_match character varying DEFAULT NULL::character varying)
    RETURNS integer
    LANGUAGE 'plpgsql'
    COST 100
    STABLE PARALLEL SAFE
AS $BODY$
DECLARE

        score INTEGER;
		fwfw REAL;
		fpfp REAL;
		lwlw REAL;
		lplp REAL;
		npnp REAL;
		npnp_in REAL;
		npnp_cnt INTEGER;
		ln_match_part_cnt INTEGER;
		mwfw REAL;
		fwmw REAL;
		fwlw REAL;
		lwfw REAL;
		fwnn REAL;
		name_parts RECORD;
		ln_in_part TEXT;
		ln_match_part TEXT;
		year_in text;
		month_in text;
		day_in text;
		year_match text;
		month_match text;
		day_match text;
		year_switch boolean;
		daymonth_switch boolean;
		day_switch boolean;

		meta_fi text;
		meta_fm text;
		dmeta_fi text;
		dmeta_fm text;
		dmetaa_fi text;
		dmetaa_fm text;
		soundex_fi text;
		soundex_fm text;

		meta_li text;
		meta_lm text;
		dmeta_li text;
		dmeta_lm text;
		dmetaa_li text;
		dmetaa_lm text;
		soundex_li text;
		soundex_lm text;

    BEGIN
		fn_in = LEFT(COALESCE(trim(lower(regexp_replace(translate(COALESCE(fn_in,''), '''.,"()', ''), '\s\s+', ' ', 'g'))),''),255);
		mn_in = LEFT(COALESCE(trim(lower(regexp_replace(translate(COALESCE(mn_in,''), '''.,"()', ''), '\s\s+', ' ', 'g'))),''),255);
		ln_in = LEFT(COALESCE(trim(lower(regexp_replace(translate(COALESCE(ln_in,''), '''.,"()', ''), '\s\s+', ' ', 'g'))),''),255);
		fn_match = LEFT(COALESCE(trim(lower(regexp_replace(translate(COALESCE(fn_match,''), '''.,"()', ''), '\s\s+', ' ', 'g'))),''),255);
		mn_match = LEFT(COALESCE(trim(lower(regexp_replace(translate(COALESCE(mn_match,''), '''.,"()', ''), '\s\s+', ' ', 'g'))),''),255);
		ln_match = LEFT(COALESCE(trim(lower(regexp_replace(translate(COALESCE(ln_match,''), '''.,"()', ''), '\s\s+', ' ', 'g'))),''),255);

		meta_fi = NULLIF(metaphone(fn_in,20),'');
		meta_fm = NULLIF(metaphone(fn_match,20),'');
		dmeta_fi = NULLIF(dmetaphone(fn_in),'');
		dmeta_fm = NULLIF(dmetaphone(fn_match),'');
		dmetaa_fi = NULLIF(dmetaphone_alt(fn_in),'');
		dmetaa_fm = NULLIF(dmetaphone_alt(fn_match),'');
		soundex_fi = NULLIF(soundex(fn_in),'');
		soundex_fm = NULLIF(soundex(fn_match),'');

		meta_li = NULLIF(metaphone(ln_in,20),'');
		meta_lm = NULLIF(metaphone(ln_match,20),'');
		dmeta_li = NULLIF(dmetaphone(ln_in),'');
		dmeta_lm = NULLIF(dmetaphone(ln_match),'');
		dmetaa_li = NULLIF(dmetaphone_alt(ln_in),'');
		dmetaa_lm = NULLIF(dmetaphone_alt(ln_match),'');
		soundex_li = NULLIF(soundex(ln_in),'');
		soundex_lm = NULLIF(soundex(ln_match),'');

		fwfw = similarity(fn_in, fn_match);
		CASE
			WHEN fwfw < .9 AND meta_fi = meta_fm THEN fwfw = .9;
			WHEN fwfw < .7 AND (dmeta_fi = dmeta_fm OR  dmetaa_fi = dmetaa_fm OR soundex_fi = soundex_fm OR levenshtein(fn_in, fn_match) = 1) THEN fwfw = .7;
			WHEN fwfw < .5 AND (levenshtein(fn_in, fn_match) = 2 OR (length(meta_fi) > 1 AND length(meta_fm) > 1 AND (position(meta_fi in meta_fm) > 0 OR position(meta_fm in meta_fi) > 0))) THEN fwfw = .5;
			ELSE
		END CASE;
		lwlw = similarity(ln_in, ln_match);
		CASE
			WHEN lwlw < .9 AND meta_li = meta_lm THEN lwlw = .9;
			WHEN lwlw < .8 AND (dmeta_li = dmeta_lm OR  dmetaa_li = dmetaa_lm OR soundex_li = soundex_lm OR levenshtein(ln_in, ln_match) = 1) THEN lwlw = .8;
			WHEN lwlw < .5 AND (levenshtein(ln_in, ln_match) = 2 OR (length(meta_li) > 1 AND length(meta_lm) > 1 AND (position(meta_li in meta_lm) > 0 OR position(meta_lm in meta_li) > 0))) THEN lwlw = .5;
			ELSE
		END CASE;
		mwfw = similarity(mn_in, fn_match);
		CASE
			WHEN mwfw >= .8 THEN mwfw = .6;
			ELSE mwfw = 0;
		END CASE;
		fwmw = similarity(fn_in, mn_match);
		CASE
			WHEN fwmw >= .8 THEN fwmw = .6;
			ELSE fwmw = 0;
		END CASE;
		fwlw = similarity(fn_in, ln_match);
		CASE
			WHEN fwlw < .9 AND meta_fi = meta_lm THEN fwlw = .9;
			WHEN fwlw < .7 AND (dmeta_fi = dmeta_lm OR  dmetaa_fi = dmetaa_lm OR soundex_fi = soundex_lm OR levenshtein(fn_in, ln_match) = 1) THEN fwlw = .7;
			WHEN fwlw < .5 AND levenshtein(fn_in, ln_match) = 2 AND fn_in <> '' AND ln_match <> '' THEN fwlw = .5;
			ELSE
		END CASE;
		lwfw = similarity(ln_in, fn_match);
		CASE
			WHEN lwfw < .9 AND meta_li = meta_fm THEN lwfw = .9;
			WHEN lwfw < .7 AND (dmeta_li = dmeta_fm OR  dmetaa_li = dmetaa_fm OR soundex_li = soundex_fm OR levenshtein(ln_in, fn_match) = 1) THEN lwfw = .7;
			WHEN lwfw < .5 AND levenshtein(ln_in, fn_match) = 2 AND ln_in <> '' AND fn_match <> '' THEN lwfw = .5;
			ELSE
		END CASE;

		CASE
			WHEN EXISTS (SELECT 1 FROM elr.nickname WHERE fn_in = name AND fn_match = nickname) THEN
				fwnn = 1;
			ELSE
				fwnn = 0;
		END CASE;

		fpfp = 0;
		FOR name_parts IN
			SELECT np_in, np_match FROM
			regexp_split_to_table(translate(fn_in, ',-_', '   '), '\s+') np_in CROSS JOIN
			regexp_split_to_table(translate(fn_match, ',-_', '   '), '\s+') np_match WHERE np_in NOT IN ('boy','girl','baby','father','mother','mom','dad','brother','sister','bro','sis','van','jr','sr','de','la','los','st','san','ii','iii') AND
			np_match NOT IN ('boy','girl','baby','father','mother','mom','dad','brother','sister','bro','sis','van','jr','sr','de','la','los','st','san','ii','iii')
			LOOP
				npnp = similarity(name_parts.np_in, name_parts.np_match);

				CASE
					WHEN npnp < .9 AND NULLIF(metaphone(name_parts.np_in,20),'') = NULLIF(metaphone(name_parts.np_match,20),'') THEN npnp = .9;
					WHEN npnp < .7 AND (NULLIF(dmetaphone(name_parts.np_in),'') = NULLIF(dmetaphone(name_parts.np_match),'') OR NULLIF(dmetaphone_alt(name_parts.np_in),'') = NULLIF(dmetaphone_alt(name_parts.np_match),'') OR NULLIF(soundex(name_parts.np_in),'') = NULLIF(soundex(name_parts.np_match),'') OR levenshtein(name_parts.np_in, name_parts.np_match) = 1) THEN npnp = .7;
					WHEN npnp < .5 AND levenshtein(name_parts.np_in, name_parts.np_match) = 2 THEN npnp = .5;
					ELSE
				END CASE;
				fpfp = GREATEST(fpfp, npnp);
		END LOOP;

		lplp = 0;
		npnp_cnt = 0;
		FOR ln_in_part IN
			SELECT np_in FROM
			regexp_split_to_table(translate(ln_in, ',-_', '   '), '\s+') np_in WHERE np_in NOT IN ('boy','girl','baby','father','mother','mom','dad','brother','sister','bro','sis','van','jr','sr','de','la','los','st','san','ii','iii')
			LOOP
				npnp_in = 0;
				ln_match_part_cnt = 0;
				FOR ln_match_part IN
					SELECT np_match FROM
					regexp_split_to_table(translate(ln_match, ',-_', '   '), '\s+') np_match WHERE np_match NOT IN ('boy','girl','baby','father','mother','mom','dad','brother','sister','bro','sis','van','jr','sr','de','la','los','st','san','ii','iii')
					LOOP
						npnp = similarity(ln_in_part, ln_match_part);
						CASE
							WHEN npnp < .9 AND NULLIF(metaphone(ln_in_part,20),'') = NULLIF(metaphone(ln_match_part,20),'') THEN npnp = .9;
							WHEN npnp < .7 AND (NULLIF(dmetaphone(ln_in_part),'') = NULLIF(dmetaphone(ln_match_part),'') OR  NULLIF(dmetaphone_alt(ln_in_part),'') = NULLIF(dmetaphone_alt(ln_match_part),'') OR NULLIF(soundex(ln_in_part),'') = NULLIF(soundex(ln_match_part),'') OR levenshtein(ln_in_part, ln_match_part) = 1) THEN npnp = .7;
							WHEN npnp < .5 AND levenshtein(ln_in_part, ln_match_part) = 2 THEN npnp = .5;
							ELSE
						END CASE;
						npnp_in = GREATEST(npnp_in, npnp);
						ln_match_part_cnt = ln_match_part_cnt + 1;
					END LOOP;
					lplp = lplp + npnp_in;
					npnp_cnt = npnp_cnt + 1;
		END LOOP;
		CASE
			WHEN npnp_cnt = 0 OR ln_match_part_cnt = 1 THEN lplp = LEAST(lplp, 1);
			ELSE lplp = lplp / npnp_cnt;
		END CASE;

		score = GREATEST((fwfw + lwlw) / 2 * 100,
						 (fpfp + lplp) / 2 * 100,
						 (mwfw + lwlw) / 2 * 100,
						 (fwmw + lwlw) / 2 * 100,
						 (fwnn + lwlw) / 2 * 100 - 1,
						 (lwfw + fwlw) / 2 * 100 - 6);
		--RAISE NOTICE 'Score:% fwfw:% lwlw:% fpfp:% lplp:% mwfw:% fwmw:% fwnn:% lwfw:% fwlw:%', score, fwfw, lwlw, fpfp, lplp, mwfw, fwmw, fwnn, lwfw, fwlw;

-- Handle special cases in the names to make sure they do not automatch
		IF soundex_fi = 'M530' AND soundex_li = 'M530' THEN
			score = LEAST(score, 84);
		END IF;

		IF (fn_in ~ '\y(boy|girl|baby)\y' OR fn_match ~ '\y(boy|girl|baby)\y') AND fn_in <> fn_match THEN
			score = LEAST(score, 84);
		END IF;

		IF (ln_in ~ '\d') THEN
			score = score - levenshtein(regexp_replace(ln_in, '[^\d]','','g'),regexp_replace(ln_match, '[^\d]','','g')) * 30;
		END IF;

		IF (fn_in ~ '\d') THEN
			score = score - levenshtein(regexp_replace(fn_in, '[^\d]','','g'),regexp_replace(fn_match, '[^\d]','','g')) * 30;
		END IF;

-- Check the genders and reduce score if they are mismatched
		IF (sex_in = 'M' AND sex_match = 'F') OR (sex_in = 'F' AND sex_match = 'M') THEN
			score = score - 6;
		END IF;

--Now modify the score with weighted birth date criteria
		IF bd_in IS NULL OR bd_match IS NULL  THEN
			score = score - 20;
		END IF;

        IF bd_in IS NOT NULL AND bd_match IS NOT NULL AND bd_in <> bd_match THEN
			year_in = RIGHT(date_part('YEAR', bd_in)::text, 2);
			year_match = RIGHT(date_part('YEAR', bd_match)::text, 2);
			month_in = LPAD(date_part('MONTH', bd_in)::text, 2, '0');
			month_match = LPAD(date_part('MONTH', bd_match)::text, 2, '0');
			day_in = LPAD(date_part('DAY', bd_in)::text, 2, '0');
			day_match = LPAD(date_part('DAY', bd_match)::text, 2, '0');
			year_switch = year_in = REVERSE(year_match) AND year_in <> year_match AND LEFT(to_char(bd_in, 'DDMMYYYY'),6) = LEFT(to_char(bd_match, 'DDMMYYYY'),6);
			daymonth_switch = month_in = day_match AND day_in = month_match AND day_in <> day_match AND month_in <> month_match AND date_part('YEAR', bd_in) = date_part('YEAR', bd_match);
			day_switch = day_in = REVERSE(day_match) AND day_in <> day_match AND to_char(bd_in, 'YYYYMM') = to_char(bd_match, 'YYYYMM');
			IF (year_switch AND NOT daymonth_switch AND NOT day_switch) OR (NOT year_switch AND daymonth_switch AND NOT day_switch) OR (NOT year_switch AND NOT daymonth_switch AND day_switch)
			THEN
			    score = score - levenshtein(to_char(bd_in, 'YYYY-MM-DD'),to_char(bd_match, 'YYYY-MM-DD')) * 3;
			ELSIF  levenshtein(to_char(bd_in, 'YYYY-MM-DD'),to_char(bd_match, 'YYYY-MM-DD')) = 1 THEN
				score = score - 6;
			ELSE
				score = score - levenshtein(to_char(bd_in, 'YYYY-MM-DD'),to_char(bd_match, 'YYYY-MM-DD')) * 16;
			END IF;
        END IF;

		IF (score < 0) THEN
			score = 0;
		END IF;

		RETURN score;
END;
$BODY$;

ALTER FUNCTION elr.graylist_score_201906(character varying, character varying, character varying, character varying, date, date, character varying, character varying, character varying, character varying)
    OWNER TO droolsuser;




-- Function: elr.safe_varchar_to_date_cast(character varying)

-- DROP FUNCTION elr.safe_varchar_to_date_cast(character varying);

CREATE OR REPLACE FUNCTION elr.safe_varchar_to_date_cast(datestr_in character varying)
  RETURNS date AS
$BODY$
	DECLARE
		datestr_temp DATE DEFAULT NULL;
	BEGIN
		BEGIN
			datestr_temp := datestr_in::timestamp without time zone::date;
		EXCEPTION WHEN OTHERS THEN
			RETURN NULL;
		END;

		RETURN datestr_temp;
	END;
$BODY$
  LANGUAGE plpgsql STABLE
  COST 100;
ALTER FUNCTION elr.safe_varchar_to_date_cast(character varying)
  OWNER TO droolsuser;
GRANT EXECUTE ON FUNCTION elr.safe_varchar_to_date_cast(character varying) TO droolsuser;
GRANT EXECUTE ON FUNCTION elr.safe_varchar_to_date_cast(character varying) TO "elr-rw";
REVOKE ALL ON FUNCTION elr.safe_varchar_to_date_cast(character varying) FROM public;


-- Function: elr.safe_varchar_to_date_cast2(character varying)

-- DROP FUNCTION elr.safe_varchar_to_date_cast2(character varying);

CREATE OR REPLACE FUNCTION elr.safe_varchar_to_date_cast2(datestr_in character varying)
  RETURNS date AS
$BODY$
	DECLARE
		datestr_temp TIMESTAMP DEFAULT NULL;
	BEGIN
		BEGIN
			datestr_temp := datestr_in::timestamp without time zone;
		EXCEPTION WHEN OTHERS THEN
		if CHAR_LENGTH(datestr_in)=12 then
		 datestr_temp := to_timestamp(datestr_in, 'YYYYMMDDhh24mi')::timestamp without time zone;
		 RETURN datestr_temp;
		else
		 RETURN NULL;
		end if;

		END;

		RETURN datestr_temp;
	END;
$BODY$
  LANGUAGE plpgsql STABLE
  COST 100;
ALTER FUNCTION elr.safe_varchar_to_date_cast2(character varying)
  OWNER TO droolsuser;
GRANT EXECUTE ON FUNCTION elr.safe_varchar_to_date_cast2(character varying) TO droolsuser;
GRANT EXECUTE ON FUNCTION elr.safe_varchar_to_date_cast2(character varying) TO public;
GRANT EXECUTE ON FUNCTION elr.safe_varchar_to_date_cast2(character varying) TO "elr-rw";


-- Function: elr.stats_accepted_by_week(integer, integer, integer)

-- DROP FUNCTION elr.stats_accepted_by_week(integer, integer, integer);

CREATE OR REPLACE FUNCTION elr.stats_accepted_by_week(
    lab_id_in integer,
    weeks_offset_in integer,
    weeks_back_in integer)
  RETURNS SETOF record AS
$BODY$
	DECLARE
		curwk_start	TIMESTAMP;
		curwk_end	TIMESTAMP;
		weeks_back	INTEGER		:= ((weeks_offset_in+weeks_back_in)-1);
		cdcgroup_id	INTEGER;
		disease_id	INTEGER;

	BEGIN
		-- get vocab category IDs for 'disease_category' and 'condition' for use later
		SELECT elr.vocab_category_id('disease_category')::INTEGER INTO cdcgroup_id;
		SELECT elr.vocab_category_id('condition')::INTEGER INTO disease_id;

		FOR i IN weeks_offset_in..weeks_back LOOP
			curwk_start	:= ('today'::TIMESTAMP - (((i+1)*7)||' days')::interval);
			curwk_end	:= ('today'::TIMESTAMP - ((i*7)||' days')::interval);
			RETURN QUERY
				SELECT mv_category.concept::VARCHAR, ('week'||i)::VARCHAR, count(sm.id)::INTEGER
				FROM elr.vocab_master_vocab mv_category
				INNER JOIN elr.vocab_master_condition mc ON (mc.disease_category = mv_category.id)
				INNER JOIN elr.vocab_master_vocab mv_condition ON (mv_condition.id = mc.condition)
				LEFT JOIN elr.system_messages sm ON (
					mv_condition.concept = sm.disease
					AND sm.lab_id = lab_id_in
					AND sm.reported_at >= curwk_start
					AND sm.reported_at < curwk_end
				)
				WHERE mv_category.category = cdcgroup_id
				AND mv_condition.category = disease_id
				GROUP BY 1, 2;
-- 			RAISE NOTICE 'i: %', i;
-- 			RAISE NOTICE 'curwk_start: %', curwk_start;
-- 			RAISE NOTICE 'curwk_end: %', curwk_end;
		END LOOP;
	END;
$BODY$
  LANGUAGE plpgsql VOLATILE
  COST 100
  ROWS 1000;
ALTER FUNCTION elr.stats_accepted_by_week(integer, integer, integer)
  OWNER TO droolsuser;
GRANT EXECUTE ON FUNCTION elr.stats_accepted_by_week(integer, integer, integer) TO droolsuser;
GRANT EXECUTE ON FUNCTION elr.stats_accepted_by_week(integer, integer, integer) TO "elr-rw";
REVOKE ALL ON FUNCTION elr.stats_accepted_by_week(integer, integer, integer) FROM public;


-- Function: elr.stats_received_by_week(character varying, integer, integer)

-- DROP FUNCTION elr.stats_received_by_week(character varying, integer, integer);

CREATE OR REPLACE FUNCTION elr.stats_received_by_week(
    connector_in character varying,
    weeks_offset_in integer,
    weeks_back_in integer)
  RETURNS SETOF record AS
$BODY$
	DECLARE
		curwk_start	TIMESTAMP;
		curwk_end	TIMESTAMP;
		weeks_back	INTEGER		:= ((weeks_offset_in+weeks_back_in)-1);

	BEGIN
		FOR i IN weeks_offset_in..weeks_back LOOP
			curwk_start	:= ('today'::TIMESTAMP - (((i+1)*7)||' days')::interval);
			curwk_end	:= ('today'::TIMESTAMP - ((i*7)||' days')::interval);
			RETURN QUERY
				SELECT ('week'||i)::VARCHAR, count(om.id)::INTEGER
				FROM elr.system_original_messages om
				WHERE connector = connector_in
				AND om.created_at >= curwk_start
				AND om.created_at < curwk_end;
-- 			RAISE NOTICE 'i: %', i;
-- 			RAISE NOTICE 'curwk_start: %', curwk_start;
-- 			RAISE NOTICE 'curwk_end: %', curwk_end;
		END LOOP;
	END;
$BODY$
  LANGUAGE plpgsql VOLATILE
  COST 100
  ROWS 1000;
ALTER FUNCTION elr.stats_received_by_week(character varying, integer, integer)
  OWNER TO droolsuser;
GRANT EXECUTE ON FUNCTION elr.stats_received_by_week(character varying, integer, integer) TO droolsuser;
GRANT EXECUTE ON FUNCTION elr.stats_received_by_week(character varying, integer, integer) TO "elr-rw";
REVOKE ALL ON FUNCTION elr.stats_received_by_week(character varying, integer, integer) FROM public;


-- Function: elr.structure_dedupe_by_labid(integer)

-- DROP FUNCTION elr.structure_dedupe_by_labid(integer);

CREATE OR REPLACE FUNCTION elr.structure_dedupe_by_labid(lab_id_in integer)
  RETURNS void AS
$BODY$
DECLARE
	srecord RECORD;
	srecord2 RECORD;
	master_path_mapped VARCHAR[];
	master_path_mapped_dedupe VARCHAR[];
	master_path_unmapped_dedupe VARCHAR[];
	kill_list INTEGER[];
	this_hash VARCHAR;
BEGIN
	-- Step 1:  Iterate through all mapped xpaths, get rid of duplicate mappings
	FOR srecord IN SELECT id, xpath, master_path_id FROM elr.structure_path_mirth WHERE lab_id = lab_id_in AND master_path_id IS NOT NULL LOOP
		this_hash := COALESCE(md5(srecord.xpath),'')||COALESCE(md5(srecord.master_path_id::text),'');
		--RAISE NOTICE 'Comparing % against master_path_mapped', this_hash;
		IF ((master_path_mapped IS NULL) OR ((master_path_mapped @> ARRAY[md5(srecord.xpath)::varchar]) IS FALSE)) THEN
			master_path_mapped := array_append(master_path_mapped, md5(srecord.xpath)::varchar);
		END IF;
		IF ((master_path_mapped_dedupe IS NULL) OR ((master_path_mapped_dedupe @> ARRAY[this_hash]) IS FALSE)) THEN
			master_path_mapped_dedupe := array_append(master_path_mapped_dedupe, this_hash);
			RAISE NOTICE 'Found new distinct row!';
		ELSE
			kill_list := array_append(kill_list, srecord.id);
			RAISE NOTICE 'Adding new ID % to kill list', srecord.id;
		END IF;
	END LOOP;

	-- Step 2:  Iterate through all non-mapped xpaths;
	-- delete any that already had their xpaths mapped, then remove duplicate non-mappings
	FOR srecord2 IN SELECT id, xpath FROM elr.structure_path_mirth WHERE lab_id = lab_id_in AND master_path_id IS NULL LOOP
		IF (master_path_mapped IS NULL) THEN
			master_path_mapped := array_append(master_path_mapped, md5(srecord2.xpath)::varchar);
		ELSEIF ((master_path_mapped @> ARRAY[md5(srecord2.xpath)::varchar]) IS TRUE) THEN
			kill_list := array_append(kill_list, srecord2.id);
			RAISE NOTICE 'Adding new ID % to kill list', srecord2.id;
		ELSEIF ((master_path_unmapped_dedupe IS NULL) OR ((master_path_unmapped_dedupe @> ARRAY[md5(srecord2.xpath)::varchar]) IS FALSE)) THEN
			master_path_unmapped_dedupe := array_append(master_path_unmapped_dedupe, md5(srecord2.xpath)::varchar);
			RAISE NOTICE 'Found new distinct row!';
		ELSE
			kill_list := array_append(kill_list, srecord2.id);
			RAISE NOTICE 'Adding new ID % to kill list', srecord2.id;
		END IF;
	END LOOP;

	--RETURN kill_list;
	DELETE FROM elr.structure_path_mirth WHERE (lab_id = lab_id_in) AND (id = ANY(kill_list));
END;
$BODY$
  LANGUAGE plpgsql VOLATILE
  COST 100;
ALTER FUNCTION elr.structure_dedupe_by_labid(integer)
  OWNER TO droolsuser;
GRANT EXECUTE ON FUNCTION elr.structure_dedupe_by_labid(integer) TO droolsuser;
GRANT EXECUTE ON FUNCTION elr.structure_dedupe_by_labid(integer) TO "elr-rw";
REVOKE ALL ON FUNCTION elr.structure_dedupe_by_labid(integer) FROM public;


-- Function: elr.vocab_category_id(character varying)

-- DROP FUNCTION elr.vocab_category_id(character varying);

CREATE OR REPLACE FUNCTION elr.vocab_category_id(category_name_in character varying)
  RETURNS integer AS
$BODY$
	DECLARE
		category_id integer;
	BEGIN
		SELECT id INTO category_id FROM elr.structure_category WHERE label = category_name_in;
		IF category_id > 0 THEN
			RETURN category_id;
		ELSE
			RETURN -1;
		END IF;
	END;
$BODY$
  LANGUAGE plpgsql STABLE STRICT
  COST 100;
ALTER FUNCTION elr.vocab_category_id(character varying)
  OWNER TO droolsuser;
GRANT EXECUTE ON FUNCTION elr.vocab_category_id(character varying) TO droolsuser;
GRANT EXECUTE ON FUNCTION elr.vocab_category_id(character varying) TO "elr-rw";
GRANT EXECUTE ON FUNCTION elr.vocab_category_id(character varying) TO "elr-ro";
REVOKE ALL ON FUNCTION elr.vocab_category_id(character varying) FROM public;




--
-- Trigger Functions
--
--


-- Function: elr.master_xml_flat_destructor()

-- DROP FUNCTION elr.master_xml_flat_destructor();

CREATE OR REPLACE FUNCTION elr.master_xml_flat_destructor()
  RETURNS trigger AS
$BODY$

BEGIN
  DELETE FROM elr.master_xml_flat WHERE id = NEW.id;
  RETURN OLD;
END;

$BODY$
LANGUAGE plpgsql VOLATILE
COST 100;
ALTER FUNCTION elr.master_xml_flat_destructor()
OWNER TO postgres;
GRANT EXECUTE ON FUNCTION elr.master_xml_flat_destructor() TO postgres;
GRANT EXECUTE ON FUNCTION elr.master_xml_flat_destructor() TO public;
GRANT EXECUTE ON FUNCTION elr.master_xml_flat_destructor() TO droolsuser;
GRANT EXECUTE ON FUNCTION elr.master_xml_flat_destructor() TO "elr-rw";


-- Function: elr.sm_local_values_from_masterxml()

-- DROP FUNCTION elr.sm_local_values_from_masterxml();

CREATE OR REPLACE FUNCTION elr.sm_local_values_from_masterxml()
  RETURNS trigger AS
$BODY$
  BEGIN
    NEW.child_loinc := (xpath('/health/labs/local_loinc_code/text()', NEW.master_xml::xml))[1]::varchar;
	NEW.child_test_code := (xpath('/health/labs/local_code/text()', NEW.master_xml::xml))[1]::varchar;
	NEW.local_result_value := (xpath('/health/labs/local_result_value/text()', NEW.master_xml::xml))[1]::varchar;
	NEW.local_result_value_2 := (xpath('/health/labs/local_result_value_2/text()', NEW.master_xml::xml))[1]::varchar;
	NEW.reported_at := (SELECT created_at FROM elr.system_original_messages WHERE id = NEW.original_message_id);
	NEW.immediate_notify := elr.get_immediately_notifiable_from_condition(NEW.disease);
	IF (NEW.final_status = 2) THEN
		-- If graylisting, set 'assigned_date' to the time the message was graylisted
		-- (for correct sorting in graylist)
		NEW.assigned_date = LOCALTIMESTAMP;
	END IF;
	NEW.dob := elr.safe_varchar_to_date_cast((xpath('/health/person/date_of_birth/text()', NEW.master_xml::xml))[1]::varchar);
    NEW.fname := (xpath('/health/person/first_name/text()', NEW.master_xml::xml))[1]::varchar;
	NEW.lname := (xpath('/health/person/last_name/text()', NEW.master_xml::xml))[1]::varchar;
	NEW.mname := (xpath('/health/person/middle_name/text()', NEW.master_xml::xml))[1]::varchar;
    NEW.loinc_code := (xpath('/health/labs/loinc_code/text()', NEW.master_xml::xml))[1]::varchar;
    NEW.lab_test_result := (xpath('/health/labs/test_result/text()', NEW.master_xml::xml))[1]::varchar;
    NEW.susceptibility_test_result := (xpath('/health/labs/susceptibility/test_result/text()', NEW.master_xml::xml))[1]::varchar;
    RETURN NEW;
  END;
$BODY$
  LANGUAGE plpgsql VOLATILE
  COST 100;
ALTER FUNCTION elr.sm_local_values_from_masterxml()
  OWNER TO droolsuser;
GRANT EXECUTE ON FUNCTION elr.sm_local_values_from_masterxml() TO droolsuser;
GRANT EXECUTE ON FUNCTION elr.sm_local_values_from_masterxml() TO "elr-rw";
REVOKE ALL ON FUNCTION elr.sm_local_values_from_masterxml() FROM public;


-- Function: elr.sm_update_flat()

-- DROP FUNCTION elr.sm_update_flat();

CREATE OR REPLACE FUNCTION elr.sm_update_flat()
    RETURNS trigger AS
$BODY$

    BEGIN
        IF (NEW.vocab_app_id = 2) THEN
            -- only run for messages destined for currently active app
            IF NOT EXISTS(SELECT 1 FROM elr.master_xml_flat WHERE system_messages_id = NEW.id) THEN
                INSERT INTO elr.master_xml_flat (system_messages_id, report_date, report_agency, local_specimen_source, collection_date, segment_index, test_status, lab_test_date, lab, local_loinc_code, local_test_name, local_code, local_code_test_name, local_result_value, local_result_value_2, local_result_unit, local_reference_range, abnormal_flag, loinc_code, reference_range, organism, test_result, specimen_source, test_type, diagnostic_name, diagnostic_street_name, diagnostic_city, diagnostic_state, diagnostic_zipcode, person_date_of_birth, person_gender, person_race, person_ethnicity, person_zip, disease, lab_id, deleted, promoted_from_graylist)
                SELECT NEW.id,
                    elr.safe_varchar_to_date_cast2((xpath('reporting/report_date/text()'::text, NEW.master_xml::xml))[1]::varchar) AS report_date,
                    (xpath('reporting/agency/text()'::text, NEW.master_xml::xml))[1] AS reporting_agency,
                    (xpath('labs/local_specimen_source/text()'::text, NEW.master_xml::xml))[1] AS local_specimen_source,
                    elr.safe_varchar_to_date_cast2((xpath('labs/collection_date/text()'::text, NEW.master_xml::xml))[1]::varchar) AS collection_date,
                    (xpath('labs/segment_index/text()'::text, NEW.master_xml::xml))[1]::varchar AS segment_index,
                    (xpath('labs/test_status/text()'::text, NEW.master_xml::xml))[1] AS test_status,
                    elr.safe_varchar_to_date_cast2((xpath('labs/lab_test_date/text()'::text, NEW.master_xml::xml))[1]::varchar) AS test_date,
                    (xpath('labs/lab/text()'::text, NEW.master_xml::xml))[1] AS lab_name,
                    (xpath('labs/local_loinc_code/text()'::text, NEW.master_xml::xml))[1] AS local_loinc_code,
                    (xpath('labs/local_test_name/text()'::text, NEW.master_xml::xml))[1] AS local_test_name,
                    (xpath('labs/local_code/text()'::text, NEW.master_xml::xml))[1] AS local_code,
                    (xpath('labs/local_code_test_name/text()'::text, NEW.master_xml::xml))[1] AS local_code_test_name,
                    (xpath('labs/local_result_value/text()'::text, NEW.master_xml::xml))[1] AS local_result_value,
                    (xpath('labs/local_result_value_2/text()'::text, NEW.master_xml::xml))[1] AS local_result_value_2,
                    (xpath('labs/local_result_unit/text()'::text, NEW.master_xml::xml))[1] AS local_result_unit,
                    (xpath('labs/local_reference_range/text()'::text, NEW.master_xml::xml))[1] AS local_reference_range,
                    (xpath('labs/abnormal_flag/text()'::text, NEW.master_xml::xml))[1] AS abnormal_flag,
                    (xpath('labs/loinc_code/text()'::text, NEW.master_xml::xml))[1] AS loinc_code,
                    (xpath('labs/reference_range/text()'::text, NEW.master_xml::xml))[1] AS reference_range,
                    (SELECT concept FROM elr.vocab_master_vocab WHERE id = (SELECT elr.get_master_vocab_id_from_app_coded_value((xpath('labs/organism/text()'::text, NEW.master_xml::xml))[1]::varchar, 'organism'::varchar, NEW.vocab_app_id))) AS organism,
                    (xpath('labs/test_result/text()'::text, NEW.master_xml::xml))[1] AS test_result,
                    (xpath('labs/specimen_source/text()'::text, NEW.master_xml::xml))[1] AS specimen_source,
                    (SELECT concept FROM elr.vocab_master_vocab WHERE id = (SELECT elr.get_master_vocab_id_from_app_coded_value((xpath('labs/test_type/text()'::text, NEW.master_xml::xml))[1]::varchar, 'test_type'::varchar, NEW.vocab_app_id))) AS test_type,
                    COALESCE((xpath('person_facilities/facility/name/text()'::text, NEW.master_xml::xml))[1], (xpath('diagnostic/name/text()'::text, NEW.master_xml::xml))[1]) AS facility_name,
                    COALESCE((xpath('person_facilities/facility/street_name/text()'::text, NEW.master_xml::xml))[1], (xpath('diagnostic/street_name/text()'::text, NEW.master_xml::xml))[1]) AS facility_street,
                    COALESCE((xpath('person_facilities/facility/city/text()'::text, NEW.master_xml::xml))[1], (xpath('diagnostic/city/text()'::text, NEW.master_xml::xml))[1]) AS facility_city,
                    COALESCE((xpath('person_facilities/facility/state/text()'::text, NEW.master_xml::xml))[1], (xpath('diagnostic/state/text()'::text, NEW.master_xml::xml))[1]) AS facility_state,
                    COALESCE((xpath('person_facilities/facility/zipcode/text()'::text, NEW.master_xml::xml))[1], (xpath('diagnostic/zipcode/text()'::text, NEW.master_xml::xml))[1]) AS facility_zip,
                    elr.safe_varchar_to_date_cast2((xpath('person/date_of_birth/text()'::text, NEW.master_xml::xml))[1]::varchar) AS person_dob,
                    (xpath('person/gender/text()'::text, NEW.master_xml::xml))[1] AS person_gender,
                    (xpath('person/race/text()'::text, NEW.master_xml::xml))[1] AS person_race,
                    (xpath('person/ethnicity/text()'::text, NEW.master_xml::xml))[1] AS person_ethnicity,
                    (xpath('person/zip/text()'::text, NEW.master_xml::xml))[1] AS person_zip,
                    (xpath('disease/name/text()'::text, NEW.master_xml::xml))[1] AS disease,
                    NEW.lab_id AS lab_id,
                    NEW.deleted AS deleted,
                    CASE WHEN NEW.copy_parent_id IS NULL THEN FALSE ELSE TRUE END AS promoted_from_graylist;
            ELSE
                UPDATE elr.master_xml_flat SET
                    report_date=elr.safe_varchar_to_date_cast2((xpath('reporting/report_date/text()'::text, NEW.master_xml::xml))[1]::varchar),
                    report_agency=(xpath('reporting/agency/text()'::text, NEW.master_xml::xml))[1],
                    local_specimen_source=(xpath('labs/local_specimen_source/text()'::text, NEW.master_xml::xml))[1],
                    collection_date=elr.safe_varchar_to_date_cast2((xpath('labs/collection_date/text()'::text, NEW.master_xml::xml))[1]::varchar),
                    segment_index=(xpath('labs/segment_index/text()'::text, NEW.master_xml::xml))[1]::varchar,
                    test_status=(xpath('labs/test_status/text()'::text, NEW.master_xml::xml))[1],
                    lab_test_date=elr.safe_varchar_to_date_cast2((xpath('labs/lab_test_date/text()'::text, NEW.master_xml::xml))[1]::varchar),
                    lab=(xpath('labs/lab/text()'::text, NEW.master_xml::xml))[1],
                    local_loinc_code=(xpath('labs/local_loinc_code/text()'::text, NEW.master_xml::xml))[1],
                    local_test_name=(xpath('labs/local_test_name/text()'::text, NEW.master_xml::xml))[1],
                    local_code=(xpath('labs/local_code/text()'::text, NEW.master_xml::xml))[1],
                    local_code_test_name=(xpath('labs/local_code_test_name/text()'::text, NEW.master_xml::xml))[1],
                    local_result_value=(xpath('labs/local_result_value/text()'::text, NEW.master_xml::xml))[1],
                    local_result_value_2=(xpath('labs/local_result_value_2/text()'::text, NEW.master_xml::xml))[1],
                    local_result_unit=(xpath('labs/local_result_unit/text()'::text, NEW.master_xml::xml))[1],
                    local_reference_range=(xpath('labs/local_reference_range/text()'::text, NEW.master_xml::xml))[1],
                    abnormal_flag=(xpath('labs/abnormal_flag/text()'::text, NEW.master_xml::xml))[1],
                    loinc_code=(xpath('labs/loinc_code/text()'::text, NEW.master_xml::xml))[1],
                    reference_range=(xpath('labs/reference_range/text()'::text, NEW.master_xml::xml))[1],
                    organism=(SELECT concept FROM elr.vocab_master_vocab WHERE id = (SELECT elr.get_master_vocab_id_from_app_coded_value((xpath('labs/organism/text()'::text, NEW.master_xml::xml))[1]::varchar, 'organism'::varchar, NEW.vocab_app_id))),
                    test_result=(xpath('labs/test_result/text()'::text, NEW.master_xml::xml))[1],
                    specimen_source=(xpath('labs/specimen_source/text()'::text, NEW.master_xml::xml))[1],
                    test_type=(SELECT concept FROM elr.vocab_master_vocab WHERE id = (SELECT elr.get_master_vocab_id_from_app_coded_value((xpath('labs/test_type/text()'::text, NEW.master_xml::xml))[1]::varchar, 'test_type'::varchar, NEW.vocab_app_id))),
                    diagnostic_name=COALESCE((xpath('person_facilities/facility/name/text()'::text, NEW.master_xml::xml))[1], (xpath('diagnostic/name/text()'::text, NEW.master_xml::xml))[1]),
                    diagnostic_street_name=COALESCE((xpath('person_facilities/facility/street_name/text()'::text, NEW.master_xml::xml))[1], (xpath('diagnostic/street_name/text()'::text, NEW.master_xml::xml))[1]),
                    diagnostic_city=COALESCE((xpath('person_facilities/facility/city/text()'::text, NEW.master_xml::xml))[1], (xpath('diagnostic/city/text()'::text, NEW.master_xml::xml))[1]),
                    diagnostic_state=COALESCE((xpath('person_facilities/facility/state/text()'::text, NEW.master_xml::xml))[1], (xpath('diagnostic/state/text()'::text, NEW.master_xml::xml))[1]),
                    diagnostic_zipcode=COALESCE((xpath('person_facilities/facility/zipcode/text()'::text, NEW.master_xml::xml))[1], (xpath('diagnostic/zipcode/text()'::text, NEW.master_xml::xml))[1]),
                    person_date_of_birth=elr.safe_varchar_to_date_cast2((xpath('person/date_of_birth/text()'::text, NEW.master_xml::xml))[1]::varchar),
                    person_gender=(xpath('person/gender/text()'::text, NEW.master_xml::xml))[1],
                    person_race=(xpath('person/race/text()'::text, NEW.master_xml::xml))[1],
                    person_ethnicity=(xpath('person/ethnicity/text()'::text, NEW.master_xml::xml))[1],
                    person_zip=(xpath('person/zip/text()'::text, NEW.master_xml::xml))[1],
                    disease=(xpath('disease/name/text()'::text, NEW.master_xml::xml))[1],
                    lab_id = NEW.lab_id,
                    deleted = NEW.deleted,
                    promoted_from_graylist = CASE WHEN NEW.copy_parent_id IS NULL THEN FALSE ELSE TRUE END
                WHERE elr.master_xml_flat.system_messages_id = NEW.id;
            END IF;
        END IF;

        RETURN NULL;
    END;

$BODY$
  LANGUAGE plpgsql VOLATILE
  COST 100;
ALTER FUNCTION elr.sm_update_flat()
  OWNER TO postgres;
GRANT EXECUTE ON FUNCTION elr.sm_update_flat() TO postgres;
GRANT EXECUTE ON FUNCTION elr.sm_update_flat() TO public;
GRANT EXECUTE ON FUNCTION elr.sm_update_flat() TO droolsuser;
GRANT EXECUTE ON FUNCTION elr.sm_update_flat() TO "elr-rw";


-- Function: elr.ss_distinct_connectors()

-- DROP FUNCTION elr.ss_distinct_connectors();

CREATE OR REPLACE FUNCTION elr.ss_distinct_connectors()
  RETURNS trigger AS
$BODY$
  BEGIN
	IF NOT EXISTS (SELECT connector FROM elr.ss_connectors WHERE connector = NEW.connector) THEN
		INSERT INTO elr.ss_connectors (connector) VALUES (NEW.connector);
	END IF;
	RETURN NULL;
  END;
$BODY$
  LANGUAGE plpgsql VOLATILE
  COST 100;
ALTER FUNCTION elr.ss_distinct_connectors()
  OWNER TO droolsuser;
GRANT EXECUTE ON FUNCTION elr.ss_distinct_connectors() TO droolsuser;
GRANT EXECUTE ON FUNCTION elr.ss_distinct_connectors() TO "elr-rw";
REVOKE ALL ON FUNCTION elr.ss_distinct_connectors() FROM public;


-- Trigger: master_xml_flat_spawn_destructor on elr.master_xml_flat

-- DROP TRIGGER master_xml_flat_spawn_destructor ON elr.master_xml_flat;

CREATE TRIGGER master_xml_flat_spawn_destructor
  AFTER UPDATE OF system_messages_id
  ON elr.master_xml_flat
  FOR EACH ROW
  WHEN (((new.system_messages_id IS NULL) AND (new.pruned IS FALSE)))
  EXECUTE PROCEDURE elr.master_xml_flat_destructor();


-- Trigger: sm_batched_connectors__trigger on elr.ss_batched_messages

-- DROP TRIGGER sm_batched_connectors__trigger ON elr.ss_batched_messages;

CREATE TRIGGER sm_batched_connectors__trigger
  AFTER INSERT
  ON elr.ss_batched_messages
  FOR EACH ROW
  EXECUTE PROCEDURE elr.ss_distinct_connectors();


-- Trigger: sm_local_values__trigger on elr.system_messages

-- DROP TRIGGER sm_local_values__trigger ON elr.system_messages;

CREATE TRIGGER sm_local_values__trigger
  BEFORE INSERT OR UPDATE
  ON elr.system_messages
  FOR EACH ROW
  EXECUTE PROCEDURE elr.sm_local_values_from_masterxml();


-- Trigger: update_flat_trigger on elr.system_messages

-- DROP TRIGGER update_flat_trigger ON elr.system_messages;

CREATE TRIGGER update_flat_trigger
  AFTER INSERT OR UPDATE
  ON elr.system_messages
  FOR EACH ROW
  EXECUTE PROCEDURE elr.sm_update_flat();

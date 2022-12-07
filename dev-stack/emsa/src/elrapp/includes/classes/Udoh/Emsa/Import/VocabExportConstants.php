<?php

namespace Udoh\Emsa\Import;

/**
 * Copyright (c) 2017 Utah Department of Technology Services and Utah Department of Health
 * 
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 * 
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 * 
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 * 
 * In addition, this program is also subject to certain additional terms. You should
 * have received a copy of these additional terms immediately following the terms and
 * conditions of the GNU Affero General Public License which accompanied the program.
 * If not, please request a copy in writing from the Utah Department of Health at
 * the address below.
 * 
 * If you have questions concerning this license or the applicable additional terms,
 * you may contact us in writing at:
 * Utah Department of Health, P.O. Box 141010, Salt Lake City, UT 84114-1010 USA.
 * 
 * @copyright Copyright (c) 2017 Utah Department of Technology Services and Utah Department of Health
 */

/**
 * Queries used during Vocab Exports
 *
 * @author Josh Ridderhoff <jridderhoff@utah.gov>
 * @todo Make truly multi app-compatible (currently mostly hard-coded to EpiTrax)
 */
class VocabExportConstants
{
    
    /** Master Dictionary */
    const EXPORT_QRY_MASTERVOCAB = "SELECT c.label AS category, mv.codeset AS codeset, mv.concept AS concept, mv.last_updated AS last_updated
        FROM vocab_master_vocab mv
        INNER JOIN structure_category c ON (mv.category = c.id)
        ORDER BY c.label, mv.concept;";
    
    /** Application-Specific Dictionary */
    const EXPORT_QRY_APPVALUE = "SELECT a.app_name AS app_name, c.label AS category, m2a.coded_value AS coded_value, mv.concept AS master_concept, m2a.last_updated AS last_updated
        FROM vocab_master2app m2a
        INNER JOIN vocab_app a ON (m2a.app_id = a.id)
        INNER JOIN vocab_master_vocab mv ON (m2a.master_id = mv.id)
        INNER JOIN structure_category c ON (mv.category = c.id)
        ORDER BY a.app_name, c.label, m2a.coded_value;";
    
    /** Master ICD Codes */
    const EXPORT_QRY_ICD = "SELECT vc.codeset_name AS codeset_name, vi.code_value AS code_value, vi.code_description AS code_description, mv_c.concept AS condition, mo.snomed AS organism_snomed, mo.snomed_alt AS organism_snomed_alt, mv_o.concept AS organism, 
        CASE WHEN vi.allow_new_cmr IS TRUE THEN 'Yes' ELSE 'No' END AS allow_new_cmr,
        CASE WHEN vi.allow_update_cmr IS TRUE THEN 'Yes' ELSE 'No' END AS allow_update_cmr,
        CASE WHEN vi.is_surveillance IS TRUE THEN 'Yes' ELSE 'No' END AS is_surveillance,
        CASE WHEN vi.pregnancy_indicator IS TRUE THEN 'Yes' ELSE 'No' END AS pregnancy_indicator,
        CASE WHEN vi.pregnancy_status IS TRUE THEN 'Yes' WHEN vi.pregnancy_status IS FALSE THEN 'No' ELSE 'Unknown' END AS pregnancy_status
        FROM vocab_icd vi
        LEFT JOIN vocab_codeset vc ON (vi.codeset_id = vc.id)
        LEFT JOIN vocab_master_condition mc ON (vi.master_condition_id = mc.c_id) 
        LEFT JOIN vocab_master_vocab mv_c ON (mc.condition = mv_c.id) 
        LEFT JOIN vocab_master_organism mo ON (vi.master_snomed_id = mo.o_id)
        LEFT JOIN vocab_master_vocab mv_o ON (mo.organism = mv_o.id)
        ORDER BY vi.code_value;";
    
    /** Master PFGE Patterns */
    const EXPORT_QRY_PFGE = "SELECT vp.pattern AS pattern, mo.snomed AS organism_snomed, mo.snomed_alt AS organism_snomed_alt, mv_o.concept AS organism
        FROM vocab_pfge vp
        LEFT JOIN vocab_master_organism mo ON (vp.master_snomed_id = mo.o_id)
        LEFT JOIN vocab_master_vocab mv_o ON (mo.organism = mv_o.id)
        ORDER BY vp.pattern;";
    
    /** Master Condition */
    const EXPORT_QRY_ML_CONDITION = "SELECT mv.concept AS condition, mv2.concept AS disease_category, 
            (CASE WHEN mc.is_initial IS TRUE THEN 'Initial' ELSE 'Final' END) AS condition_type, 
            (CASE WHEN mc.immediate_notify IS TRUE THEN 'Yes' ELSE 'No' END) AS immediate_notify, 
            (CASE WHEN mc.require_specimen IS TRUE THEN 'Yes' ELSE 'No' END) AS require_specimen, 
            array_to_string(
                array(
                    SELECT mv_int.concept FROM vocab_master_vocab mv_int WHERE mv_int.id IN (
                        SELECT arr[i]::integer FROM (
                            SELECT generate_series(1, array_upper(arr, 1)) AS i, arr 
                            FROM (SELECT regexp_split_to_array(mc.valid_specimen, E';') AS arr ) t 
                        ) t
                    )
                )
            , '; ') AS valid_specimen, 
            array_to_string(
                array(
                    SELECT mv_int.concept FROM vocab_master_vocab mv_int WHERE mv_int.id IN (
                        SELECT arr[i]::integer FROM (
                            SELECT generate_series(1, array_upper(arr, 1)) AS i, arr 
                            FROM (SELECT regexp_split_to_array(mc.invalid_specimen, E';') AS arr ) t 
                        ) t
                    )
                )
            , '; ') AS invalid_specimen, 
            mc.ignore_age_rule AS ignore_age_rule, mc.white_rule AS white_rule, mc.contact_white_rule AS contact_white_rule,
            (CASE WHEN mc.whitelist_ignore_case_status IS TRUE THEN 'Yes' ELSE 'No' END) AS whitelist_ignore_case_status, 
            mc.gateway_xref AS gateway_xref, mc.o2m_addcmr_exclusions AS o2m_addcmr_exclusions, 
            (CASE WHEN mc.notify_state IS TRUE THEN 'Yes' ELSE 'No' END) AS notify_state, 
            (CASE WHEN mc.check_xref_first IS TRUE THEN 'Yes' ELSE 'No' END) AS check_xref_first, 
            sd.health_district AS district_override, 
            vrg.conditions_structured AS graylist_rule_structured,
            (CASE WHEN mc.whitelist_override IS TRUE THEN 'Yes' ELSE 'No' END) AS whitelist_override, 
            (CASE WHEN mc.allow_multi_assign IS TRUE THEN 'Yes' ELSE 'No' END) AS allow_multi_assign,
            (CASE WHEN mc.ast_multi_colony IS TRUE THEN 'Yes' ELSE 'No' END) AS ast_multi_colony,
            (CASE WHEN mc.bypass_oos IS TRUE THEN 'Yes' ELSE 'No' END) AS bypass_oos,
            (CASE WHEN mc.blacklist_preliminary IS TRUE THEN 'Yes' ELSE 'No' END) AS blacklist_preliminary, 
            mc.last_updated AS last_updated
        FROM vocab_master_condition mc
        LEFT JOIN vocab_rules_graylist vrg ON (mc.c_id = vrg.master_condition_id AND vrg.app_id = 2) 
        INNER JOIN vocab_master_vocab mv ON (mc.condition = mv.id) 
        LEFT JOIN vocab_master_vocab mv2 ON (mc.disease_category = mv2.id) 
        LEFT JOIN system_districts sd ON (mc.district_override = sd.id) 
        ORDER BY mv.concept;";
    
    /** Master LOINC */
    const EXPORT_QRY_ML_LOINC = "SELECT ml.loinc AS loinc, ml.concept_name AS preferred_concept, mv_c.concept AS condition, mv_o.concept AS organism, mv_t.concept AS test_type, mv_s.concept AS status, 
            mv_ss.concept AS specimen_source, mv_aa.concept AS antimicrobial_agent, l.name AS list, 
            (CASE WHEN vrml.allow_new_cmr IS TRUE THEN 'Yes' ELSE 'No' END) AS allow_new_cmr, 
            (CASE WHEN vrml.allow_update_cmr IS TRUE THEN 'Yes' ELSE 'No' END) AS allow_update_cmr, 
            (CASE WHEN vrml.is_surveillance IS TRUE THEN 'Yes' ELSE 'No' END) AS is_surveillance, 
            (CASE WHEN ml.condition_from_result IS TRUE THEN 'Yes' ELSE 'No' END) AS condition_from_result, 
            (CASE WHEN ml.organism_from_result IS TRUE THEN 'Yes' ELSE 'No' END) AS organism_from_result, 
            vrml.conditions_structured AS rule_structured, 
            ml.last_updated AS last_updated, ml.admin_notes AS admin_notes
        FROM vocab_master_loinc ml
        LEFT JOIN vocab_rules_masterloinc vrml ON (ml.l_id = vrml.master_loinc_id AND vrml.app_id = 2) 
        LEFT JOIN vocab_master_condition mc ON (ml.trisano_condition = mc.c_id) 
        LEFT JOIN vocab_master_vocab mv_c ON (mc.condition = mv_c.id) 
        LEFT JOIN vocab_master_organism mo ON (ml.trisano_organism = mo.o_id) 
        LEFT JOIN vocab_master_vocab mv_o ON (mo.organism = mv_o.id) 
        LEFT JOIN vocab_master_vocab mv_t ON (ml.trisano_test_type = mv_t.id) 
        LEFT JOIN vocab_master_vocab mv_s ON (vrml.state_case_status_master_id = mv_s.id) 
        LEFT JOIN vocab_master_vocab mv_ss ON (ml.specimen_source = mv_ss.id) 
        LEFT JOIN vocab_master_vocab mv_aa ON (ml.antimicrobial_agent = mv_aa.id) 
        LEFT JOIN system_statuses l ON (ml.list = l.id) 
        ORDER BY ml.loinc, vrml.id;";
    
    /** Master SNOMED */
    const EXPORT_QRY_ML_ORGANISM = "SELECT mv_sc.concept AS snomed_category, mo.snomed AS snomed, mo.snomed_alt AS snomed_alt, mv_c.concept AS condition, mv_o.concept AS organism, mv_s.concept AS status, 
            l.name AS list, initcap(m2a.coded_value) || CASE WHEN sc_trc.label = 'resist_test_result' THEN ' (AST)' ELSE ' (Labs)' END AS test_result,  
            (CASE WHEN vrmo.allow_new_cmr IS TRUE THEN 'Yes' ELSE 'No' END) AS allow_new_cmr, 
            (CASE WHEN vrmo.allow_update_cmr IS TRUE THEN 'Yes' ELSE 'No' END) AS allow_update_cmr, 
            (CASE WHEN vrmo.is_surveillance IS TRUE THEN 'Yes' ELSE 'No' END) AS is_surveillance, 
            (CASE WHEN mo.semi_auto_usage IS TRUE THEN 'Force Semi-Auto' WHEN mo.semi_auto_usage IS FALSE THEN 'Skip Semi-Auto' ELSE 'Allow Semi-Auto' END) AS semi_auto_usage, 
            vrmo.conditions_structured AS rule_structured, 
            mo.last_updated AS last_updated, mo.admin_notes AS admin_notes
        FROM vocab_master_organism mo
        LEFT JOIN vocab_rules_mastersnomed vrmo ON (mo.o_id = vrmo.master_snomed_id AND vrmo.app_id = 2) 
        LEFT JOIN vocab_master_condition mc ON (mo.condition = mc.c_id) 
        LEFT JOIN vocab_master_vocab mv_c ON (mc.condition = mv_c.id) 
        LEFT JOIN vocab_master_vocab mv_o ON (mo.organism = mv_o.id) 
        LEFT JOIN vocab_master_vocab mv_sc ON (mo.snomed_category = mv_sc.id) 
        LEFT JOIN vocab_master_vocab mv_s ON (vrmo.state_case_status_master_id = mv_s.id) 
        LEFT JOIN vocab_master2app m2a ON (mo.test_result = m2a.master_id AND m2a.app_id = 2) 
        LEFT JOIN vocab_master_vocab mv_trc ON (mo.test_result = mv_trc.id)
        LEFT JOIN structure_category sc_trc ON (mv_trc.category = sc_trc.id)
        LEFT JOIN system_statuses l ON (mo.list = l.id) 
        ORDER BY mv_c.concept, mv_o.concept;";
    
    /** Child Dictionary */
    const EXPORT_QRY_CHILD_VOCAB = "SELECT c.label AS category, cv.concept as child_concept, mv.concept AS master_concept, cv.comment AS comment, cv.last_updated AS last_updated
        FROM vocab_child_vocab cv
        LEFT JOIN vocab_master_vocab mv ON (cv.master_id = mv.id)
        LEFT JOIN structure_category c ON (mv.category = c.id)
        WHERE cv.lab_id = :labId 
        ORDER BY c.label, cv.concept;";
    
    /** Child SNOMED */
    const EXPORT_QRY_CHILD_ORGANISM = "SELECT mv.concept AS organism, mv_t.concept AS test_result_id, co.child_code AS child_code, mo.snomed AS snomed, mo_t.snomed AS test_result_snomed, co.app_test_result AS app_test_result, 
            co.value AS value, co.test_status AS test_status, co.last_updated AS last_updated, co.result_value AS result_value, co.comment AS comment, co.admin_notes AS admin_notes
        FROM vocab_child_organism co
        LEFT JOIN vocab_master_organism mo ON (co.organism = mo.o_id)
        LEFT JOIN vocab_master_organism mo_t ON (co.test_result_id = mo_t.o_id)
        LEFT JOIN vocab_master_vocab mv ON (mo.organism = mv.id)
        LEFT JOIN vocab_master_vocab mv_t ON (mo_t.organism = mv_t.id)
        WHERE co.lab_id = :labId 
        ORDER BY mv.concept;";
    
    /** Child LOINC */
    const EXPORT_QRY_CHILD_LOINC = "SELECT ml.loinc AS master_loinc, ml.concept_name AS preferred_concept_name, cl.child_loinc AS child_loinc, cl.child_concept_name AS child_concept_name, 
            cl.child_orderable_test_code AS cotc, cl.child_resultable_test_code AS crtc, cl.child_alias AS child_alias, 
            mv_rl.concept AS result_location, 
            (CASE WHEN cl.interpret_results IS TRUE THEN 'Yes' ELSE 'No' END) AS interpret_results, 
            (CASE WHEN cl.workflow = " . ENTRY_STATUS . " THEN 'Automated Processing' WHEN cl.workflow = " . SEMI_AUTO_STATUS . " THEN 'Semi-Automated Entry' ELSE 'QA Review' END) AS workflow, 
            cl.units AS units, 
            (CASE WHEN cl.pregnancy IS TRUE THEN 'Yes' ELSE 'No' END) AS pregnancy, 
            cl.refrange AS refrange, cl.hl7_refrange AS hl7_refrange, 
            initcap(m2a.coded_value) || CASE WHEN sc.label = 'resist_test_result' THEN ' (AST)' ELSE ' (Labs)' END AS result, 
            c2m.conditions_structured AS rule_structured, c2m.results_to_comments AS results_to_comments, 
            (CASE WHEN cl.archived IS TRUE THEN 'Yes' ELSE 'No' END) AS archived, 
            cl.last_updated AS last_updated, 
            cl.admin_notes AS admin_notes, 
            (CASE WHEN cl.allow_preprocessing IS TRUE THEN 'Yes' ELSE 'No' END) AS allow_preprocessing, 
            initcap(m2al.coded_value) || CASE WHEN sc_ltrc.label = 'resist_test_result' THEN ' (AST)' ELSE ' (Labs)' END AS offscale_low_result, 
            initcap(m2ah.coded_value) || CASE WHEN sc_htrc.label = 'resist_test_result' THEN ' (AST)' ELSE ' (Labs)' END AS offscale_high_result, 
            (CASE WHEN cl.interpret_override IS TRUE THEN 'Override Quantitative' WHEN cl.interpret_override IS FALSE THEN 'Override Coded Entry' ELSE 'Set by OBX-2' END) AS interpret_override 
        FROM vocab_child_loinc cl
        LEFT JOIN vocab_c2m_testresult c2m ON (cl.id = c2m.child_loinc_id AND c2m.app_id = 2) 
        LEFT JOIN vocab_master_loinc ml ON (cl.master_loinc = ml.l_id) 
        LEFT JOIN vocab_master_vocab mv_rl ON (cl.result_location = mv_rl.id) 
        LEFT JOIN vocab_master2app m2a ON (c2m.master_id = m2a.master_id AND m2a.app_id = 2)
        LEFT JOIN vocab_master_vocab mv ON (c2m.master_id = mv.id)
        LEFT JOIN structure_category sc ON (mv.category = sc.id) 
        LEFT JOIN vocab_master2app m2al ON (cl.offscale_low_result = m2al.master_id AND m2al.app_id = 2)
        LEFT JOIN vocab_master_vocab mv_ltrc ON (cl.offscale_low_result = mv_ltrc.id)
        LEFT JOIN structure_category sc_ltrc ON (mv_ltrc.category = sc_ltrc.id) 
        LEFT JOIN vocab_master2app m2ah ON (cl.offscale_high_result = m2ah.master_id AND m2ah.app_id = 2)
        LEFT JOIN vocab_master_vocab mv_htrc ON (cl.offscale_high_result = mv_htrc.id)
        LEFT JOIN structure_category sc_htrc ON (mv_htrc.category = sc_htrc.id) 
        WHERE cl.lab_id = :labId  
        ORDER BY cl.child_loinc, c2m.id;";
    
}

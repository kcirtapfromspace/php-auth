<?php
/**
 * Copyright (c) 2016 Utah Department of Technology Services and Utah Department of Health
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
 * @copyright Copyright (c) 2016 Utah Department of Technology Services and Utah Department of Health
 */

use Udoh\Emsa\UI\AccessibleMultiselectListbox;
use Udoh\Emsa\UI\Queue\FilterFactory;
use Udoh\Emsa\Utils\DisplayUtils;
use Udoh\Emsa\Utils\VocabUtils;

if (!class_exists('\Udoh\Emsa\Auth\Authenticator') || !\Udoh\Emsa\Auth\Authenticator::userHasPermission(\Udoh\Emsa\Auth\Authenticator::URIGHTS_ADMIN)) {
    ob_end_clean();
    header($_SERVER['SERVER_PROTOCOL'] . " 404 Not Found", TRUE, 404);
    exit;
}

	$va = new VocabAudit($adminDbConn, $authClient);  // set up vocabulary auditer for later

	/**
	 * check for session freshness since last update to session-stored model info
	 * (columns/filters are stored in session data, this hack gives us a way to force
	 * session data to be refreshed without forcing users to clear cookies if the data
	 * is updated mid-session, so that the current columns/filters are used)
	 */
	// set "freshness date" (manual by developer to force client updates)
	// $model_last_updated = strtotime("3:09 PM 9/24/2012");

	/**
	 * Change to use 'filemtime()' to dynamically get last modification date of file
	 * Much less hassle than having to manually set a 'freshness date' each edit
	 */
	$modelLastUpdated = filemtime("manage/vocabulary.php");

	// check "freshness date"...
	if (isset($_SESSION[EXPORT_SERVERNAME]['vocab_model_fresh'])) {
		if ($_SESSION[EXPORT_SERVERNAME]['vocab_model_fresh'] < $modelLastUpdated) {
			// old model data; unset vocab_params & set a new "freshness date"...
			unset($_SESSION[EXPORT_SERVERNAME]["vocab_params"]);
			$_SESSION[EXPORT_SERVERNAME]['vocab_model_fresh'] = time();
		}
	} else {
		// hack for sessions set before "freshness date" implemented
		unset($_SESSION[EXPORT_SERVERNAME]["vocab_params"]);
		$_SESSION[EXPORT_SERVERNAME]['vocab_model_fresh'] = time();
	}


	########## Session Prep ##########
	// switch vocab type based on 'vocab' querystring value
	$_SESSION[EXPORT_SERVERNAME]["vocab_params"]["vocab"] = 1;
	if (isset($navSubcat)) {
		switch (intval($navSubcat)) {
			case 2:
				$_SESSION[EXPORT_SERVERNAME]["vocab_params"]["vocab"] = 2;
				break;
			case 3:
				$_SESSION[EXPORT_SERVERNAME]["vocab_params"]["vocab"] = 3;
				break;
			case 4:
				$_SESSION[EXPORT_SERVERNAME]["vocab_params"]["vocab"] = 4;
				break;
			case 5:
				$_SESSION[EXPORT_SERVERNAME]["vocab_params"]["vocab"] = 5;
				break;
			case 13:
				$_SESSION[EXPORT_SERVERNAME]["vocab_params"]["vocab"] = 13;
				break;
			case 14:
				$_SESSION[EXPORT_SERVERNAME]["vocab_params"]["vocab"] = 14;
				break;
			default:
				$_SESSION[EXPORT_SERVERNAME]["vocab_params"]["vocab"] = 1;
				break;
		}
	}

	/**
	 * Check if adding/editing a child LOINC interpretive result rule...
	 */
	if (isset($_GET['rulemod_action']) && in_array(trim($_GET['rulemod_action']), array("add", "edit", "delete"))) {
		include __DIR__ . '/result_rules.php';
	}

	/**
	 * Check if adding/editing a Master LOINC case management rule...
	 */
	if (isset($_GET['rulemod_cmr_action']) && in_array(trim($_GET['rulemod_cmr_action']), array("add", "edit", "delete"))) {
		include __DIR__ . '/case_management_rules.php';
	}

	/**
	 * Check if adding/editing a Master SNOMED case management rule...
	 */
	if (isset($_GET['rulemod_ms_cmr_action']) && in_array(trim($_GET['rulemod_ms_cmr_action']), array("add", "edit", "delete"))) {
		include __DIR__ . '/case_management_rules_organism.php';
	}

	/**
	 * Check if adding/editing a Graylist rule...
	 */
	if (isset($_GET['rulemod_gray_action']) && in_array(trim($_GET['rulemod_gray_action']), array("add", "edit", "delete"))) {
		include __DIR__ . '/graylist_rules.php';
	}


		if (!isset($_SESSION[EXPORT_SERVERNAME]["vocab_params"][$_SESSION[EXPORT_SERVERNAME]["vocab_params"]["vocab"]])) {
			// if no params exist for selected vocab, load default values for Master LOINC...
            $_SESSION[EXPORT_SERVERNAME]["vocab_params"][$_SESSION[EXPORT_SERVERNAME]["vocab_params"]["vocab"]]["page_rows"] = DEFAULT_ROWS_PER_PAGE;
			$_SESSION[EXPORT_SERVERNAME]["vocab_params"][$_SESSION[EXPORT_SERVERNAME]["vocab_params"]["vocab"]]["sort_order"] = "ASC";
			$_SESSION[EXPORT_SERVERNAME]["vocab_params"][$_SESSION[EXPORT_SERVERNAME]["vocab_params"]["vocab"]]["query_data"]["table_name"] = "vocab_master_loinc";
			$_SESSION[EXPORT_SERVERNAME]["vocab_params"][$_SESSION[EXPORT_SERVERNAME]["vocab_params"]["vocab"]]["query_data"]["id_column"] = "l_id";
			$_SESSION[EXPORT_SERVERNAME]["vocab_params"][$_SESSION[EXPORT_SERVERNAME]["vocab_params"]["vocab"]]["query_data"]["from_tables"] = sprintf("FROM %svocab_master_loinc vml
				LEFT JOIN %ssystem_statuses ss ON (ss.id = vml.list)
				LEFT JOIN %svocab_master_condition vmc ON (vmc.c_id = vml.trisano_condition)
				LEFT JOIN %svocab_master_organism vmo ON (vmo.o_id = vml.trisano_organism)
				LEFT JOIN %svocab_master_vocab mv_c ON (mv_c.id = vmc.condition)
				LEFT JOIN %svocab_master_vocab mv_o ON (mv_o.id = vmo.organism)
				LEFT JOIN %svocab_master_vocab mv_t ON (mv_t.id = vml.trisano_test_type)
                LEFT JOIN %svocab_master_vocab mv_aa ON (mv_aa.id = vml.antimicrobial_agent)
				LEFT JOIN %svocab_master_vocab mv_p ON (mv_p.id = vml.specimen_source)",
				$emsaDbSchemaPrefix, $emsaDbSchemaPrefix, $emsaDbSchemaPrefix, $emsaDbSchemaPrefix, $emsaDbSchemaPrefix, $emsaDbSchemaPrefix, $emsaDbSchemaPrefix, $emsaDbSchemaPrefix, $emsaDbSchemaPrefix);
			$_SESSION[EXPORT_SERVERNAME]["vocab_params"][$_SESSION[EXPORT_SERVERNAME]["vocab_params"]["vocab"]]["sort_col"] = "loinc";
			$_SESSION[EXPORT_SERVERNAME]["vocab_params"][$_SESSION[EXPORT_SERVERNAME]["vocab_params"]["vocab"]]["header_icon"] = 'ui-icon-emsadictionary';
			$_SESSION[EXPORT_SERVERNAME]["vocab_params"][$_SESSION[EXPORT_SERVERNAME]["vocab_params"]["vocab"]]["vocab_verbose"] = "Master LOINC";
			$_SESSION[EXPORT_SERVERNAME]["vocab_params"][$_SESSION[EXPORT_SERVERNAME]["vocab_params"]["vocab"]]["result_cols"] = array(
				"l_id" => array("colname" => "vml.l_id", "label" => "l_id", "rules_placeholder" => FALSE, "display" => FALSE, "filter" => FALSE, "textsearch" => FALSE),
				"loinc" => array("colname" => "vml.loinc", "label" => "LOINC", "rules_placeholder" => FALSE, "display" => TRUE, "filter" => FALSE, "textsearch" => TRUE, "main_concept" => true),
				"concept_name" => array("colname" => "vml.concept_name", "label" => "Preferred Concept Name", "rules_placeholder" => FALSE, "display" => TRUE, "filter" => FALSE, "textsearch" => TRUE, "main_concept" => true),
				"notes" => array("colname" => "vml.admin_notes", "label" => "Admin Notes", "rules_placeholder" => false, "display" => true, "filter" => false, "textsearch" => true),
                "antimicrobial_agent" => array("colname" => "mv_aa.concept", "label" => "Antimicrobial Agent", "rules_placeholder" => FALSE, "display" => TRUE, "filter" => TRUE, "textsearch" => TRUE, "filterlookup" => TRUE, "lookupqry" => sprintf("SELECT vm.id AS value, vm.concept AS label FROM %svocab_master_vocab vm WHERE (vm.category = elr.vocab_category_id('resist_test_agent')) ORDER BY vm.concept;", $emsaDbSchemaPrefix), "filtercolname" => "vml.antimicrobial_agent"),
				"condition_from_result" => array("colname" => "vml.condition_from_result", "label" => "Look Up Condition?", "rules_placeholder" => FALSE, "display" => TRUE, "filter" => TRUE, "textsearch" => FALSE, "filtercolname" => "vml.condition_from_result"),
				"trisano_condition" => array("colname" => "mv_c.concept", "label" => "Master Condition", "rules_placeholder" => FALSE, "display" => TRUE, "filter" => TRUE, "textsearch" => TRUE, "filterlookup" => TRUE, "lookupqry" => sprintf("SELECT vmc.c_id AS value, mv.concept AS label FROM %svocab_master_condition vmc JOIN %svocab_master_vocab mv ON (vmc.condition = mv.id) WHERE vmc.is_initial IS TRUE ORDER BY mv.concept;", $emsaDbSchemaPrefix, $emsaDbSchemaPrefix), "filtercolname" => "vml.trisano_condition"),
				"organism_from_result" => array("colname" => "vml.organism_from_result", "label" => "Look Up Organism?", "rules_placeholder" => FALSE, "display" => TRUE, "filter" => TRUE, "textsearch" => FALSE, "filtercolname" => "vml.organism_from_result"),
				"trisano_organism" => array("colname" => "mv_o.concept", "label" => "Master Organism", "rules_placeholder" => FALSE, "display" => TRUE, "filter" => TRUE, "textsearch" => TRUE, "filterlookup" => TRUE, "lookupqry" => sprintf("SELECT vmo.o_id AS value, vmo.snomed AS snomed, mv.concept AS label FROM %svocab_master_organism vmo LEFT JOIN %svocab_master_vocab mv ON (vmo.organism = mv.id) INNER JOIN %svocab_master_vocab mv2 ON (vmo.snomed_category = mv2.id) WHERE mv2.category = %svocab_category_id('snomed_category') AND mv2.concept = 'Organism' ORDER BY mv.concept, vmo.snomed;", $emsaDbSchemaPrefix, $emsaDbSchemaPrefix, $emsaDbSchemaPrefix, $emsaDbSchemaPrefix), "filtercolname" => "vml.trisano_organism"),
				"trisano_test_type" => array("colname" => "mv_t.concept", "label" => "Test Type", "rules_placeholder" => FALSE, "display" => TRUE, "filter" => TRUE, "textsearch" => TRUE, "filterlookup" => TRUE, "lookupqry" => sprintf("SELECT vm.id AS value, vm.concept AS label FROM %svocab_master_vocab vm WHERE (vm.category = elr.vocab_category_id('test_type')) ORDER BY vm.concept;", $emsaDbSchemaPrefix), "filtercolname" => "vml.trisano_test_type"),
				"specimen_source" => array("colname" => "mv_p.concept", "label" => "Specimen Source", "rules_placeholder" => FALSE, "display" => TRUE, "filter" => TRUE, "textsearch" => TRUE, "filterlookup" => TRUE, "lookupqry" => sprintf("SELECT vm.id AS value, vm.concept AS label FROM %svocab_master_vocab vm WHERE (vm.category = elr.vocab_category_id('specimen')) ORDER BY vm.concept;", $emsaDbSchemaPrefix), "filtercolname" => "vml.specimen_source"),
				"list" => array("colname" => "ss.name", "label" => "List", "rules_placeholder" => FALSE, "display" => TRUE, "filter" => TRUE, "textsearch" => FALSE, "filterlookup" => TRUE, "lookupqry" => sprintf("SELECT id AS value, name AS label FROM %ssystem_statuses WHERE id IN (%s) ORDER BY label;", $emsaDbSchemaPrefix, implode(',', array(WHITE_STATUS, BLACK_STATUS, GRAY_STATUS))), "filtercolname" => "vml.list"),
				"ml_cmr_rules" => array("colname" => "'rules_placeholder'", "label" => "rules_placeholder", "rules_placeholder" => TRUE, "display" => FALSE, "filter" => FALSE, "textsearch" => FALSE));

			switch (intval($_SESSION[EXPORT_SERVERNAME]["vocab_params"]["vocab"])) {
				case 2:
					// Master Condition
					$_SESSION[EXPORT_SERVERNAME]["vocab_params"][$_SESSION[EXPORT_SERVERNAME]["vocab_params"]["vocab"]]["query_data"]["table_name"] = "vocab_master_condition";
					$_SESSION[EXPORT_SERVERNAME]["vocab_params"][$_SESSION[EXPORT_SERVERNAME]["vocab_params"]["vocab"]]["query_data"]["id_column"] = "c_id";
					$_SESSION[EXPORT_SERVERNAME]["vocab_params"][$_SESSION[EXPORT_SERVERNAME]["vocab_params"]["vocab"]]["query_data"]["from_tables"] = sprintf("FROM %svocab_master_condition vmc
						LEFT JOIN %svocab_master_vocab mv_c ON (mv_c.id = vmc.condition)
						LEFT JOIN %svocab_master_vocab mv_dc ON (mv_dc.id = vmc.disease_category)
						LEFT JOIN %ssystem_districts sd ON (sd.id = vmc.district_override)",
						$emsaDbSchemaPrefix, $emsaDbSchemaPrefix, $emsaDbSchemaPrefix, $emsaDbSchemaPrefix);
					$_SESSION[EXPORT_SERVERNAME]["vocab_params"][$_SESSION[EXPORT_SERVERNAME]["vocab_params"]["vocab"]]["header_icon"] = 'ui-icon-emsadictionary';
                    $_SESSION[EXPORT_SERVERNAME]["vocab_params"][$_SESSION[EXPORT_SERVERNAME]["vocab_params"]["vocab"]]["vocab_verbose"] = "Master Condition";
					$_SESSION[EXPORT_SERVERNAME]["vocab_params"][$_SESSION[EXPORT_SERVERNAME]["vocab_params"]["vocab"]]["sort_col"] = "condition";
					$_SESSION[EXPORT_SERVERNAME]["vocab_params"][$_SESSION[EXPORT_SERVERNAME]["vocab_params"]["vocab"]]["result_cols"] = array(
						"c_id" => array("colname" => "vmc.c_id", "label" => "c_id", "rules_placeholder" => FALSE, "display" => FALSE, "filter" => FALSE, "textsearch" => FALSE),
						"condition" => array("colname" => "mv_c.concept", "label" => "Condition", "rules_placeholder" => FALSE, "display" => TRUE, "filter" => TRUE, "textsearch" => TRUE, "filterlookup" => TRUE, "lookupqry" => "SELECT vmc.c_id AS value, mv.concept AS label FROM {$emsaDbSchemaPrefix}vocab_master_condition vmc JOIN {$emsaDbSchemaPrefix}vocab_master_vocab mv ON (vmc.condition = mv.id) ORDER BY mv.concept;", "filtercolname" => "vmc.c_id", "main_concept" => true),
						"ignore_age_rule" => array("colname" => "vmc.ignore_age_rule", "label" => "Ignore Older Than", "rules_placeholder" => FALSE, "display" => TRUE, "filter" => TRUE, "textsearch" => TRUE, "filtercolname" => "vmc.ignore_age_rule"),
						"blacklist_preliminary" => array("colname" => "vmc.blacklist_preliminary", "label" => "Blacklist Prelim Results?", "rules_placeholder" => FALSE, "display" => TRUE, "filter" => TRUE, "textsearch" => FALSE, "filtercolname" => "vmc.blacklist_preliminary"),
                        "white_rule" => array("colname" => "vmc.white_rule", "label" => "Morbidity Whitelist Rules", "rules_placeholder" => FALSE, "display" => TRUE, "filter" => TRUE, "textsearch" => TRUE, "filtercolname" => "vmc.white_rule"),
						"contact_white_rule" => array("colname" => "vmc.contact_white_rule", "label" => "Contact Whitelist Rules", "rules_placeholder" => FALSE, "display" => TRUE, "filter" => TRUE, "textsearch" => TRUE, "filtercolname" => "vmc.contact_white_rule"),
						"whitelist_ignore_case_status" => array("colname" => "vmc.whitelist_ignore_case_status", "label" => "Whitelist Rules Ignore State Case Status?", "rules_placeholder" => FALSE, "display" => TRUE, "filter" => TRUE, "textsearch" => FALSE, "filtercolname" => "vmc.whitelist_ignore_case_status"),
						"gateway_xref" => array("colname" => "vmc.gateway_xref", "label" => "Whitelist Crossrefs", "rules_placeholder" => FALSE, "display" => TRUE, "filter" => FALSE, "textsearch" => FALSE, "filtercolname" => "vmc.gateway_xref"),
						"check_xref_first" => array("colname" => "vmc.check_xref_first", "label" => "Check Crossrefs First?", "rules_placeholder" => FALSE, "display" => TRUE, "filter" => TRUE, "textsearch" => FALSE, "filtercolname" => "vmc.check_xref_first"),
						"whitelist_override" => array("colname" => "vmc.whitelist_override", "label" => "Override Target Whitelist Rules?", "rules_placeholder" => FALSE, "display" => TRUE, "filter" => TRUE, "textsearch" => FALSE, "filtercolname" => "vmc.whitelist_override"),
						"allow_multi_assign" => array("colname" => "vmc.allow_multi_assign", "label" => "Allow One-to-Many?", "rules_placeholder" => FALSE, "display" => TRUE, "filter" => TRUE, "textsearch" => FALSE, "filtercolname" => "vmc.allow_multi_assign"),
                        "o2m_addcmr_exclusions" => array("colname" => "vmc.o2m_addcmr_exclusions", "label" => "O2M Add CMR If Not Found", "rules_placeholder" => FALSE, "display" => TRUE, "filter" => FALSE, "textsearch" => FALSE, "filtercolname" => "vmc.o2m_addcmr_exclusions"),
                        "mc_gray_rules" => array("colname" => "'rules_placeholder'", "label" => "rules_placeholder", "rules_placeholder" => TRUE, "display" => FALSE, "filter" => FALSE, "textsearch" => FALSE),
						"require_specimen" => array("colname" => "vmc.require_specimen", "label" => "Require Specimen From Nominal?", "rules_placeholder" => FALSE, "display" => TRUE, "filter" => TRUE, "textsearch" => FALSE, "filtercolname" => "vmc.require_specimen"),
						"valid_specimen" => array("colname" => "vmc.valid_specimen", "label" => "Valid Specimen Sources", "rules_placeholder" => FALSE, "display" => TRUE, "filter" => FALSE, "textsearch" => TRUE, "filtercolname" => "vmc.valid_specimen"),
						"invalid_specimen" => array("colname" => "vmc.invalid_specimen", "label" => "Invalid Specimen Sources", "rules_placeholder" => FALSE, "display" => TRUE, "filter" => FALSE, "textsearch" => TRUE, "filtercolname" => "vmc.invalid_specimen"),
                        "ast_multi_colony" => array("colname" => "vmc.ast_multi_colony", "label" => "Allow AST Multi-Colony?", "rules_placeholder" => FALSE, "display" => TRUE, "filter" => TRUE, "textsearch" => FALSE, "filtercolname" => "vmc.ast_multi_colony"),
                        "bypass_oos" => array("colname" => "vmc.bypass_oos", "label" => "Bypass OOS Queue?", "rules_placeholder" => FALSE, "display" => TRUE, "filter" => TRUE, "textsearch" => FALSE, "filtercolname" => "vmc.bypass_oos"),
                        "notify_state" => array("colname" => "vmc.notify_state", "label" => "Notify State Upon Receipt?", "rules_placeholder" => FALSE, "display" => TRUE, "filter" => TRUE, "textsearch" => FALSE, "filtercolname" => "vmc.notify_state"),
						"immediate_notify" => array("colname" => "vmc.immediate_notify", "label" => "Immediately Notifiable?", "rules_placeholder" => FALSE, "display" => TRUE, "filter" => TRUE, "textsearch" => FALSE, "filtercolname" => "vmc.immediate_notify"),
						"district_override" => array("colname" => "sd.health_district", "label" => "Jurisdiction Override", "rules_placeholder" => FALSE, "display" => TRUE, "filter" => TRUE, "textsearch" => FALSE, "filterlookup" => TRUE, "lookupqry" => "SELECT sd.id AS value, sd.health_district AS label FROM {$emsaDbSchemaPrefix}system_districts sd WHERE sd.enabled IS TRUE ORDER BY sd.health_district;", "filtercolname" => "vmc.district_override"),
						"disease_category" => array("colname" => "mv_dc.concept", "label" => "CDC Category", "rules_placeholder" => FALSE, "display" => TRUE, "filter" => TRUE, "textsearch" => FALSE, "filterlookup" => TRUE, "lookupqry" => "SELECT vm.id AS value, vm.concept AS label FROM {$emsaDbSchemaPrefix}vocab_master_vocab vm WHERE (vm.category = {$emsaDbSchemaPrefix}vocab_category_id('disease_category')) ORDER BY vm.concept;", "filtercolname" => "vmc.disease_category"),
						"is_initial" => array("colname" => "vmc.is_initial", "label" => "Condition Type", "rules_placeholder" => FALSE, "display" => TRUE, "filter" => TRUE, "textsearch" => FALSE, "filtercolname" => "vmc.is_initial"));
					break;
				case 3:
					// Master Organism
					$_SESSION[EXPORT_SERVERNAME]["vocab_params"][$_SESSION[EXPORT_SERVERNAME]["vocab_params"]["vocab"]]["query_data"]["table_name"] = "vocab_master_organism";
					$_SESSION[EXPORT_SERVERNAME]["vocab_params"][$_SESSION[EXPORT_SERVERNAME]["vocab_params"]["vocab"]]["query_data"]["id_column"] = "o_id";
					$_SESSION[EXPORT_SERVERNAME]["vocab_params"][$_SESSION[EXPORT_SERVERNAME]["vocab_params"]["vocab"]]["query_data"]["from_tables"] = sprintf("FROM %svocab_master_organism vmo
						LEFT JOIN %ssystem_statuses ss ON (ss.id = vmo.list)
						LEFT JOIN %svocab_master_condition vmc ON (vmc.c_id = vmo.condition)
						LEFT JOIN %svocab_master_vocab mv_c ON (mv_c.id = vmc.condition)
						LEFT JOIN %svocab_master_vocab mv_o ON (mv_o.id = vmo.organism)
						LEFT JOIN %svocab_master_vocab mv_sc ON (mv_sc.id = vmo.snomed_category)
						LEFT JOIN %svocab_master_vocab mv_t ON (mv_t.id = vmo.test_result)
						LEFT JOIN %sstructure_category sc_t ON (sc_t.id = mv_t.category)",
						$emsaDbSchemaPrefix, $emsaDbSchemaPrefix, $emsaDbSchemaPrefix, $emsaDbSchemaPrefix, $emsaDbSchemaPrefix, $emsaDbSchemaPrefix, $emsaDbSchemaPrefix, $emsaDbSchemaPrefix);
					$_SESSION[EXPORT_SERVERNAME]["vocab_params"][$_SESSION[EXPORT_SERVERNAME]["vocab_params"]["vocab"]]["header_icon"] = 'ui-icon-emsadictionary';
                    $_SESSION[EXPORT_SERVERNAME]["vocab_params"][$_SESSION[EXPORT_SERVERNAME]["vocab_params"]["vocab"]]["vocab_verbose"] = "Master SNOMED";
					$_SESSION[EXPORT_SERVERNAME]["vocab_params"][$_SESSION[EXPORT_SERVERNAME]["vocab_params"]["vocab"]]["sort_col"] = "condition";
					$_SESSION[EXPORT_SERVERNAME]["vocab_params"][$_SESSION[EXPORT_SERVERNAME]["vocab_params"]["vocab"]]["result_cols"] = array(
						"o_id" => array("colname" => "vmo.o_id", "label" => "o_id", "rules_placeholder" => FALSE, "display" => FALSE, "filter" => FALSE, "textsearch" => FALSE),
						"snomed_category" => array("colname" => "mv_sc.concept", "label" => "SNOMED Type", "rules_placeholder" => FALSE, "display" => TRUE, "filter" => TRUE, "textsearch" => FALSE, "filterlookup" => TRUE, "lookupqry" => sprintf("SELECT vm.id AS value, vm.concept AS label FROM %svocab_master_vocab vm WHERE (vm.category = elr.vocab_category_id('snomed_category')) ORDER BY vm.concept;", $emsaDbSchemaPrefix), "filtercolname" => "vmo.snomed_category"),
						"snomed" => array("colname" => "vmo.snomed", "label" => "SNOMED Code", "rules_placeholder" => FALSE, "display" => TRUE, "filter" => FALSE, "textsearch" => TRUE, "main_concept" => true),
						"snomed_alt" => array("colname" => "vmo.snomed_alt", "label" => "Secondary SNOMED Code", "rules_placeholder" => FALSE, "display" => TRUE, "filter" => FALSE, "textsearch" => TRUE, "main_concept" => true),
						"condition" => array("colname" => "mv_c.concept", "label" => "Master Condition", "rules_placeholder" => FALSE, "display" => TRUE, "filter" => TRUE, "textsearch" => TRUE, "filterlookup" => TRUE, "lookupqry" => sprintf("SELECT vmc.c_id AS value, mv.concept AS label FROM %svocab_master_condition vmc JOIN %svocab_master_vocab mv ON (vmc.condition = mv.id) ORDER BY mv.concept;", $emsaDbSchemaPrefix, $emsaDbSchemaPrefix), "filtercolname" => "vmo.condition"),
						"organism" => array("colname" => "mv_o.concept", "label" => "Type Concept Name", "rules_placeholder" => FALSE, "display" => TRUE, "filter" => TRUE, "textsearch" => TRUE, "filterlookup" => TRUE, "lookupqry" => sprintf("SELECT vmo.o_id AS value, mv.concept AS label FROM %svocab_master_organism vmo JOIN %svocab_master_vocab mv ON (vmo.organism = mv.id) ORDER BY mv.concept;", $emsaDbSchemaPrefix, $emsaDbSchemaPrefix), "filtercolname" => "vmo.o_id", "main_concept" => true),
						"notes" => array("colname" => "vmo.admin_notes", "label" => "Admin Notes", "rules_placeholder" => false, "display" => true, "filter" => false, "textsearch" => true),
                        "list" => array("colname" => "ss.name", "label" => "List", "rules_placeholder" => FALSE, "display" => TRUE, "filter" => TRUE, "textsearch" => FALSE, "filterlookup" => TRUE, "lookupqry" => sprintf("SELECT id AS value, name AS label FROM %ssystem_statuses WHERE id IN (%s) ORDER BY label;", $emsaDbSchemaPrefix, implode(',', array(WHITE_STATUS, GRAY_STATUS, BLACK_STATUS))), "filtercolname" => "vmo.list"),
						"test_result" => array("colname" => "mv_t.concept || CASE WHEN sc_t.label = 'resist_test_result' THEN ' (AST)' WHEN sc_t.label = 'test_result' THEN ' (Labs)' END", "label" => "Test Result", "rules_placeholder" => FALSE, "display" => TRUE, "filter" => TRUE, "textsearch" => FALSE, "filterlookup" => TRUE, "lookupqry" => sprintf("SELECT id AS value, concept || ' (Labs)' as label, 'Labs' as sortkey FROM %svocab_master_vocab WHERE category = %svocab_category_id('test_result') UNION ALL SELECT id AS value, concept || ' (AST)' as label, 'AST' as sortkey FROM %svocab_master_vocab WHERE category = %svocab_category_id('resist_test_result') ORDER BY sortkey DESC, label;", $emsaDbSchemaPrefix, $emsaDbSchemaPrefix, $emsaDbSchemaPrefix, $emsaDbSchemaPrefix), "filtercolname" => "vmo.test_result"),
                        "semi_auto_usage" => array("colname" => "vmo.semi_auto_usage", "label" => "Semi-Auto Usage", "rules_placeholder" => FALSE, "display" => TRUE, "filter" => TRUE, "textsearch" => FALSE, "filtercolname" => "vmo.semi_auto_usage"),
						"mo_cmr_rules" => array("colname" => "'rules_placeholder'", "label" => "rules_placeholder", "rules_placeholder" => TRUE, "display" => FALSE, "filter" => FALSE, "textsearch" => FALSE));
					break;
                case 14:
                    // ICD Code
					$_SESSION[EXPORT_SERVERNAME]["vocab_params"][$_SESSION[EXPORT_SERVERNAME]["vocab_params"]["vocab"]]["query_data"]["table_name"] = "vocab_icd";
					$_SESSION[EXPORT_SERVERNAME]["vocab_params"][$_SESSION[EXPORT_SERVERNAME]["vocab_params"]["vocab"]]["query_data"]["id_column"] = "vi.id";
					$_SESSION[EXPORT_SERVERNAME]["vocab_params"][$_SESSION[EXPORT_SERVERNAME]["vocab_params"]["vocab"]]["query_data"]["from_tables"] = sprintf("FROM %svocab_icd vi
						LEFT JOIN %svocab_codeset vset ON (vset.id = vi.codeset_id)
						LEFT JOIN %svocab_master_condition vmc ON (vmc.c_id = vi.master_condition_id)
						LEFT JOIN %svocab_master_organism vmo ON (vmo.o_id = vi.master_snomed_id)
						LEFT JOIN %svocab_master_vocab mv_c ON (mv_c.id = vmc.condition)
						LEFT JOIN %svocab_master_vocab mv_o ON (mv_o.id = vmo.organism)",
						$emsaDbSchemaPrefix, $emsaDbSchemaPrefix, $emsaDbSchemaPrefix, $emsaDbSchemaPrefix, $emsaDbSchemaPrefix, $emsaDbSchemaPrefix);
					$_SESSION[EXPORT_SERVERNAME]["vocab_params"][$_SESSION[EXPORT_SERVERNAME]["vocab_params"]["vocab"]]["sort_col"] = "icd_code";
					$_SESSION[EXPORT_SERVERNAME]["vocab_params"][$_SESSION[EXPORT_SERVERNAME]["vocab_params"]["vocab"]]["header_icon"] = 'ui-icon-emsadictionary';
                    $_SESSION[EXPORT_SERVERNAME]["vocab_params"][$_SESSION[EXPORT_SERVERNAME]["vocab_params"]["vocab"]]["vocab_verbose"] = "ICD Code";
					$_SESSION[EXPORT_SERVERNAME]["vocab_params"][$_SESSION[EXPORT_SERVERNAME]["vocab_params"]["vocab"]]["result_cols"] = array(
						"id" => array("colname" => "vi.id", "label" => "id", "rules_placeholder" => false, "display" => false, "filter" => false, "textsearch" => false),
						"codeset" => array("colname" => "vset.codeset_name", "label" => "Coding System", "rules_placeholder" => false, "display" => true, "filter" => true, "textsearch" => false, "filterlookup" => true, "lookupqry" => sprintf("SELECT id AS value, codeset_name AS label FROM %svocab_codeset ORDER BY codeset_name;", $emsaDbSchemaPrefix), "filtercolname" => "vi.codeset_id"),
						"icd_code" => array("colname" => "vi.code_value", "label" => "ICD Code", "rules_placeholder" => false, "display" => true, "filter" => false, "textsearch" => true, "main_concept" => true),
						"code_description" => array("colname" => "vi.code_description", "label" => "Code Description", "rules_placeholder" => false, "display" => true, "filter" => false, "textsearch" => true, "main_concept" => true),
						"master_condition" => array("colname" => "mv_c.concept", "label" => "Master Condition", "rules_placeholder" => false, "display" => true, "filter" => true, "textsearch" => true, "filterlookup" => true, "lookupqry" => sprintf("SELECT vmc.c_id AS value, mv.concept AS label FROM %svocab_master_condition vmc JOIN %svocab_master_vocab mv ON (vmc.condition = mv.id) WHERE vmc.is_initial IS TRUE ORDER BY mv.concept;", $emsaDbSchemaPrefix, $emsaDbSchemaPrefix), "filtercolname" => "vi.master_condition_id"),
						"master_snomed" => array("colname" => "mv_o.concept", "label" => "Master Organism", "rules_placeholder" => false, "display" => true, "filter" => true, "textsearch" => true, "filterlookup" => true, "lookupqry" => sprintf("SELECT vmo.o_id AS value, vmo.snomed AS snomed, mv.concept AS label FROM %svocab_master_organism vmo LEFT JOIN %svocab_master_vocab mv ON (vmo.organism = mv.id) INNER JOIN %svocab_master_vocab mv2 ON (vmo.snomed_category = mv2.id) WHERE mv2.category = %svocab_category_id('snomed_category') AND mv2.concept = 'Organism' ORDER BY mv.concept, vmo.snomed;", $emsaDbSchemaPrefix, $emsaDbSchemaPrefix, $emsaDbSchemaPrefix, $emsaDbSchemaPrefix), "filtercolname" => "vi.master_snomed_id"),
                        "allow_new_cmr" => array("colname" => "vi.allow_new_cmr", "label" => "New CMR?", "rules_placeholder" => false, "display" => true, "filter" => true, "textsearch" => false, "filtercolname" => "vi.allow_new_cmr"),
                        "allow_update_cmr" => array("colname" => "vi.allow_update_cmr", "label" => "Update CMR?", "rules_placeholder" => false, "display" => true, "filter" => true, "textsearch" => false, "filtercolname" => "vi.allow_update_cmr"),
                        "is_surveillance" => array("colname" => "vi.is_surveillance", "label" => "Surveillance?", "rules_placeholder" => false, "display" => true, "filter" => true, "textsearch" => false, "filtercolname" => "vi.is_surveillance"),
                        "pregnancy_indicator" => array("colname" => "vi.pregnancy_indicator", "label" => "Determines pregnancy?", "rules_placeholder" => false, "display" => true, "filter" => true, "textsearch" => false, "filtercolname" => "vi.pregnancy_indicator"),
                        "pregnancy_status" => array("colname" => "vi.pregnancy_status", "label" => "Pregnancy Status", "rules_placeholder" => false, "display" => true, "filter" => true, "textsearch" => false, "filtercolname" => "vi.pregnancy_status"));
					break;
                case 13:
					// PFGE to Master SNOMED
					$_SESSION[EXPORT_SERVERNAME]["vocab_params"][$_SESSION[EXPORT_SERVERNAME]["vocab_params"]["vocab"]]["query_data"]["table_name"] = "vocab_pfge";
					$_SESSION[EXPORT_SERVERNAME]["vocab_params"][$_SESSION[EXPORT_SERVERNAME]["vocab_params"]["vocab"]]["query_data"]["id_column"] = "vp.id";
					$_SESSION[EXPORT_SERVERNAME]["vocab_params"][$_SESSION[EXPORT_SERVERNAME]["vocab_params"]["vocab"]]["query_data"]["from_tables"] = sprintf("FROM %svocab_pfge vp
						LEFT JOIN %svocab_master_organism vmo ON (vp.master_snomed_id = vmo.o_id)
						LEFT JOIN %svocab_master_vocab mv_o ON (mv_o.id = vmo.organism)",
						$emsaDbSchemaPrefix, $emsaDbSchemaPrefix, $emsaDbSchemaPrefix);
					$_SESSION[EXPORT_SERVERNAME]["vocab_params"][$_SESSION[EXPORT_SERVERNAME]["vocab_params"]["vocab"]]["header_icon"] = 'ui-icon-emsapfge';
                    $_SESSION[EXPORT_SERVERNAME]["vocab_params"][$_SESSION[EXPORT_SERVERNAME]["vocab_params"]["vocab"]]["vocab_verbose"] = "PulseNet Serotype Code";
					$_SESSION[EXPORT_SERVERNAME]["vocab_params"][$_SESSION[EXPORT_SERVERNAME]["vocab_params"]["vocab"]]["sort_col"] = "pattern";
					$_SESSION[EXPORT_SERVERNAME]["vocab_params"][$_SESSION[EXPORT_SERVERNAME]["vocab_params"]["vocab"]]["result_cols"] = array(
						"id" => array("colname" => "vp.id", "label" => "id", "rules_placeholder" => FALSE, "display" => FALSE, "filter" => FALSE, "textsearch" => FALSE),
						"pattern" => array("colname" => "vp.pattern", "label" => "PulseNet Serotype Code", "rules_placeholder" => FALSE, "display" => TRUE, "filter" => TRUE, "textsearch" => TRUE, "filtercolname" => "vp.pattern", "main_concept" => true),
						"snomed" => array("colname" => "vmo.snomed", "label" => "Master SNOMED", "rules_placeholder" => FALSE, "display" => TRUE, "filter" => FALSE, "textsearch" => TRUE, "linkback" => "3"),
						"organism" => array("colname" => "mv_o.concept", "label" => "Master Organism", "rules_placeholder" => FALSE, "display" => TRUE, "filter" => TRUE, "textsearch" => TRUE, "filterlookup" => TRUE, "lookupqry" => sprintf("SELECT vmo.o_id AS value, vmo.snomed AS snomed, mv.concept AS label FROM %svocab_master_organism vmo JOIN %svocab_master_vocab mv ON (vmo.organism = mv.id) INNER JOIN %svocab_master_vocab mv2 ON (vmo.snomed_category = mv2.id) WHERE mv2.category = %svocab_category_id('snomed_category') AND mv2.concept = 'Organism' ORDER BY mv.concept, vmo.snomed;", $emsaDbSchemaPrefix, $emsaDbSchemaPrefix, $emsaDbSchemaPrefix, $emsaDbSchemaPrefix), "filtercolname" => "vp.master_snomed_id"));
					break;
				case 4:
					// Child Organism
					$_SESSION[EXPORT_SERVERNAME]["vocab_params"][$_SESSION[EXPORT_SERVERNAME]["vocab_params"]["vocab"]]["query_data"]["table_name"] = "vocab_child_organism";
					$_SESSION[EXPORT_SERVERNAME]["vocab_params"][$_SESSION[EXPORT_SERVERNAME]["vocab_params"]["vocab"]]["query_data"]["id_column"] = "vco.id";
					$_SESSION[EXPORT_SERVERNAME]["vocab_params"][$_SESSION[EXPORT_SERVERNAME]["vocab_params"]["vocab"]]["query_data"]["from_tables"] = sprintf("FROM %svocab_child_organism vco
						LEFT JOIN %svocab_master_organism vmo ON (vco.organism = vmo.o_id)
						LEFT JOIN %svocab_master_organism vmo_t ON (vco.test_result_id = vmo_t.o_id)
						LEFT JOIN %sstructure_labs sl ON (vco.lab_id = sl.id)
						LEFT JOIN %svocab_master_vocab mv_o ON (mv_o.id = vmo.organism)
						LEFT JOIN %svocab_master_vocab mv_t ON (mv_t.id = vmo_t.organism)",
						$emsaDbSchemaPrefix, $emsaDbSchemaPrefix, $emsaDbSchemaPrefix, $emsaDbSchemaPrefix, $emsaDbSchemaPrefix, $emsaDbSchemaPrefix);
					$_SESSION[EXPORT_SERVERNAME]["vocab_params"][$_SESSION[EXPORT_SERVERNAME]["vocab_params"]["vocab"]]["header_icon"] = 'ui-icon-emsadictionary';
                    $_SESSION[EXPORT_SERVERNAME]["vocab_params"][$_SESSION[EXPORT_SERVERNAME]["vocab_params"]["vocab"]]["vocab_verbose"] = "Child SNOMED";
					$_SESSION[EXPORT_SERVERNAME]["vocab_params"][$_SESSION[EXPORT_SERVERNAME]["vocab_params"]["vocab"]]["sort_col"] = "organism";
					$_SESSION[EXPORT_SERVERNAME]["vocab_params"][$_SESSION[EXPORT_SERVERNAME]["vocab_params"]["vocab"]]["result_cols"] = array(
						"id" => array("colname" => "vco.id", "label" => "id", "rules_placeholder" => FALSE, "display" => FALSE, "filter" => FALSE, "textsearch" => FALSE),
						"lab" => array("colname" => "sl.ui_name", "label" => "Child Lab", "rules_placeholder" => FALSE, "display" => TRUE, "filter" => TRUE, "textsearch" => FALSE, "filterlookup" => TRUE, "lookupqry" => sprintf("SELECT sl.id AS value, sl.ui_name AS label FROM %sstructure_labs sl WHERE sl.alias_for < 1 ORDER BY sl.ui_name;", $emsaDbSchemaPrefix), "filtercolname" => "vco.lab_id"),
						"child_code" => array("colname" => "vco.child_code", "label" => "Child Code", "rules_placeholder" => FALSE, "display" => TRUE, "filter" => FALSE, "textsearch" => TRUE, "main_concept" => true),
						"notes" => array("colname" => "vco.admin_notes", "label" => "Admin Notes", "rules_placeholder" => false, "display" => true, "filter" => false, "textsearch" => true),
                        "snomed" => array("colname" => "vmo.snomed", "label" => "Master Organism SNOMED", "rules_placeholder" => FALSE, "display" => TRUE, "filter" => FALSE, "textsearch" => TRUE, "linkback" => "3"),
						"organism" => array("colname" => "mv_o.concept", "label" => "Master Organism", "rules_placeholder" => FALSE, "display" => TRUE, "filter" => TRUE, "textsearch" => TRUE, "filterlookup" => TRUE, "lookupqry" => sprintf("SELECT vmo.o_id AS value, vmo.snomed AS snomed, mv.concept AS label FROM %svocab_master_organism vmo JOIN %svocab_master_vocab mv ON (vmo.organism = mv.id) INNER JOIN %svocab_master_vocab mv2 ON (vmo.snomed_category = mv2.id) WHERE mv2.category = %svocab_category_id('snomed_category') AND mv2.concept = 'Organism' ORDER BY mv.concept, vmo.snomed;", $emsaDbSchemaPrefix, $emsaDbSchemaPrefix, $emsaDbSchemaPrefix, $emsaDbSchemaPrefix), "filtercolname" => "vco.organism"),
						"test_result_snomed" => array("colname" => "vmo_t.snomed", "label" => "Master Test Result SNOMED", "rules_placeholder" => FALSE, "display" => TRUE, "filter" => FALSE, "textsearch" => TRUE, "linkback" => "3"),
						"test_result" => array("colname" => "mv_t.concept", "label" => "Master Test Result", "rules_placeholder" => FALSE, "display" => TRUE, "filter" => TRUE, "textsearch" => TRUE, "filterlookup" => TRUE, "lookupqry" => sprintf("SELECT vmo.o_id AS value, vmo.snomed AS snomed, mv.concept AS label FROM %svocab_master_organism vmo JOIN %svocab_master_vocab mv ON (vmo.organism = mv.id) INNER JOIN %svocab_master_vocab mv2 ON (vmo.snomed_category = mv2.id) WHERE mv2.category = %svocab_category_id('snomed_category') AND mv2.concept = 'Test Result' ORDER BY mv.concept, vmo.snomed;", $emsaDbSchemaPrefix, $emsaDbSchemaPrefix, $emsaDbSchemaPrefix, $emsaDbSchemaPrefix), "filtercolname" => "vco.test_result_id"),
						"result_value" => array("colname" => "vco.result_value", "label" => "Result Value", "rules_placeholder" => FALSE, "display" => TRUE, "filter" => FALSE, "textsearch" => TRUE, "filtercolname" => "vco.result_value"),
						"comment" => array("colname" => "vco.comment", "label" => "Comments", "rules_placeholder" => FALSE, "display" => TRUE, "filter" => FALSE, "textsearch" => TRUE, "filtercolname" => "vco.comment"));
					break;
				case 5:
					// Child LOINC
					$_SESSION[EXPORT_SERVERNAME]["vocab_params"][$_SESSION[EXPORT_SERVERNAME]["vocab_params"]["vocab"]]["query_data"]["table_name"] = "vocab_child_loinc";
					$_SESSION[EXPORT_SERVERNAME]["vocab_params"][$_SESSION[EXPORT_SERVERNAME]["vocab_params"]["vocab"]]["query_data"]["id_column"] = "vcl.id";
					$_SESSION[EXPORT_SERVERNAME]["vocab_params"][$_SESSION[EXPORT_SERVERNAME]["vocab_params"]["vocab"]]["query_data"]["from_tables"] = sprintf("FROM %svocab_child_loinc vcl
						LEFT JOIN %svocab_master_loinc vml ON (vcl.master_loinc = vml.l_id)
						LEFT JOIN %svocab_master_vocab mv_l ON (mv_l.id = vcl.result_location)
                        LEFT JOIN %svocab_master_vocab mv_tl ON (mv_tl.id = vcl.offscale_low_result)
                        LEFT JOIN %svocab_master_vocab mv_th ON (mv_th.id = vcl.offscale_high_result)
                        LEFT JOIN %sstructure_category sc_tl ON (sc_tl.id = mv_tl.category)
                        LEFT JOIN %sstructure_category sc_th ON (sc_th.id = mv_th.category)
						LEFT JOIN %sstructure_labs sl ON (vcl.lab_id = sl.id)",
						$emsaDbSchemaPrefix, $emsaDbSchemaPrefix, $emsaDbSchemaPrefix, $emsaDbSchemaPrefix, $emsaDbSchemaPrefix, $emsaDbSchemaPrefix, $emsaDbSchemaPrefix, $emsaDbSchemaPrefix);
					$_SESSION[EXPORT_SERVERNAME]["vocab_params"][$_SESSION[EXPORT_SERVERNAME]["vocab_params"]["vocab"]]["header_icon"] = 'ui-icon-emsadictionary';
                    $_SESSION[EXPORT_SERVERNAME]["vocab_params"][$_SESSION[EXPORT_SERVERNAME]["vocab_params"]["vocab"]]["vocab_verbose"] = "Child LOINC";
					$_SESSION[EXPORT_SERVERNAME]["vocab_params"][$_SESSION[EXPORT_SERVERNAME]["vocab_params"]["vocab"]]["sort_col"] = "child_loinc";
					$_SESSION[EXPORT_SERVERNAME]["vocab_params"][$_SESSION[EXPORT_SERVERNAME]["vocab_params"]["vocab"]]["result_cols"] = array(
						"id" => array("colname" => "vcl.id", "label" => "id", "rules_placeholder" => FALSE, "display" => FALSE, "filter" => FALSE, "textsearch" => FALSE),
						"archived" => array("colname" => "vcl.archived", "label" => "'Archived' Flag", "rules_placeholder" => FALSE, "display" => false, "filter" => TRUE, "textsearch" => FALSE, "filtercolname" => "vcl.archived"),
						"lab" => array("colname" => "sl.ui_name", "label" => "Child Lab", "rules_placeholder" => FALSE, "display" => TRUE, "filter" => TRUE, "textsearch" => FALSE, "filterlookup" => TRUE, "lookupqry" => sprintf("SELECT sl.id AS value, sl.ui_name AS label FROM %sstructure_labs sl WHERE sl.alias_for < 1 ORDER BY sl.ui_name;", $emsaDbSchemaPrefix), "filtercolname" => "vcl.lab_id"),
						"child_loinc" => array("colname" => "vcl.child_loinc", "label" => "Child LOINC", "rules_placeholder" => FALSE, "display" => TRUE, "filter" => FALSE, "textsearch" => TRUE, "main_concept" => true),
						"master_loinc" => array("colname" => "vml.loinc", "label" => "Master LOINC", "rules_placeholder" => FALSE, "display" => TRUE, "filter" => TRUE, "textsearch" => TRUE, "filterlookup" => TRUE, "lookupqry" => sprintf("SELECT vml.l_id AS value, vml.loinc AS label FROM %svocab_master_loinc vml ORDER BY vml.loinc;", $emsaDbSchemaPrefix), "filtercolname" => "vcl.master_loinc", "linkback" => "1"),
						"concept_name" => array("colname" => "vml.concept_name", "label" => "Preferred Concept Name", "rules_placeholder" => FALSE, "display" => TRUE, "filter" => TRUE, "textsearch" => TRUE, "filterlookup" => TRUE, "lookupqry" => sprintf("SELECT vml.l_id AS value, vml.concept_name AS label FROM %svocab_master_loinc vml ORDER BY vml.concept_name;", $emsaDbSchemaPrefix, $emsaDbSchemaPrefix), "filtercolname" => "vcl.master_loinc"),
						"child_concept_name" => array("colname" => "vcl.child_concept_name", "label" => "Child Concept Name", "rules_placeholder" => FALSE, "display" => TRUE, "filter" => FALSE, "textsearch" => TRUE, "main_concept" => true),
						"notes" => array("colname" => "vcl.admin_notes", "label" => "Admin Notes", "rules_placeholder" => false, "display" => true, "filter" => false, "textsearch" => true),
                        "workflow" => array("colname" => "vcl.workflow", "label" => "Message Workflow", "rules_placeholder" => FALSE, "display" => TRUE, "filter" => TRUE, "textsearch" => FALSE, "filtercolname" => "vcl.workflow"),
						"interpret_results" => array("colname" => "vcl.interpret_results", "label" => "Quantitative Result (Deprecated)", "rules_placeholder" => FALSE, "display" => TRUE, "filter" => TRUE, "textsearch" => FALSE, "filtercolname" => "vcl.interpret_results"),
                        "interpret_override" => array("colname" => "vcl.interpret_override", "label" => "Result Interpretation", "rules_placeholder" => FALSE, "display" => TRUE, "filter" => TRUE, "textsearch" => FALSE, "filtercolname" => "vcl.interpret_override"),
                        "allow_preprocessing" => array("colname" => "vcl.allow_preprocessing", "label" => "Preprocessor Concatenation?", "rules_placeholder" => FALSE, "display" => TRUE, "filter" => TRUE, "textsearch" => FALSE, "filtercolname" => "vcl.allow_preprocessing"),
						"result_location" => array("colname" => "mv_l.concept", "label" => "Result Location", "rules_placeholder" => FALSE, "display" => TRUE, "filter" => TRUE, "textsearch" => TRUE, "filterlookup" => TRUE, "lookupqry" => sprintf("SELECT vm.id AS value, vm.concept AS label FROM %svocab_master_vocab vm WHERE (vm.category = elr.vocab_category_id('result_type')) ORDER BY vm.concept;", $emsaDbSchemaPrefix), "filtercolname" => "vcl.result_location"),
						"hl7_refrange" => array("colname" => "vcl.hl7_refrange", "label" => "HL7 Reference Range", "rules_placeholder" => FALSE, "display" => false, "filter" => FALSE, "textsearch" => TRUE),
						"units" => array("colname" => "vcl.units", "label" => "Units", "rules_placeholder" => FALSE, "display" => TRUE, "filter" => FALSE, "textsearch" => TRUE),
						"refrange" => array("colname" => "vcl.refrange", "label" => "Reference Range", "rules_placeholder" => FALSE, "display" => TRUE, "filter" => FALSE, "textsearch" => TRUE),
                        "offscale_low_result" => array("colname" => "mv_tl.concept || CASE WHEN sc_tl.label = 'resist_test_result' THEN ' (AST)' WHEN sc_tl.label = 'test_result' THEN ' (Labs)' END", "label" => "Off-scale Low Test Result", "rules_placeholder" => FALSE, "display" => TRUE, "filter" => TRUE, "textsearch" => FALSE, "filterlookup" => TRUE, "lookupqry" => sprintf("SELECT id AS value, concept || ' (Labs)' as label, 'Labs' as sortkey FROM %svocab_master_vocab WHERE category = %svocab_category_id('test_result') UNION ALL SELECT id AS value, concept || ' (AST)' as label, 'AST' as sortkey FROM %svocab_master_vocab WHERE category = %svocab_category_id('resist_test_result') ORDER BY sortkey DESC, label;", $emsaDbSchemaPrefix, $emsaDbSchemaPrefix, $emsaDbSchemaPrefix, $emsaDbSchemaPrefix), "filtercolname" => "vcl.offscale_low_result"),
                        "offscale_high_result" => array("colname" => "mv_th.concept || CASE WHEN sc_th.label = 'resist_test_result' THEN ' (AST)' WHEN sc_th.label = 'test_result' THEN ' (Labs)' END", "label" => "Off-scale High Test Result", "rules_placeholder" => FALSE, "display" => TRUE, "filter" => TRUE, "textsearch" => FALSE, "filterlookup" => TRUE, "lookupqry" => sprintf("SELECT id AS value, concept || ' (Labs)' as label, 'Labs' as sortkey FROM %svocab_master_vocab WHERE category = %svocab_category_id('test_result') UNION ALL SELECT id AS value, concept || ' (AST)' as label, 'AST' as sortkey FROM %svocab_master_vocab WHERE category = %svocab_category_id('resist_test_result') ORDER BY sortkey DESC, label;", $emsaDbSchemaPrefix, $emsaDbSchemaPrefix, $emsaDbSchemaPrefix, $emsaDbSchemaPrefix), "filtercolname" => "vcl.offscale_high_result"),
                        "cl_interp_rules" => array("colname" => "'rules_placeholder'", "label" => "rules_placeholder", "rules_placeholder" => TRUE, "display" => FALSE, "filter" => FALSE, "textsearch" => FALSE),
						"pregnancy" => array("colname" => "vcl.pregnancy", "label" => "Indicates Pregnancy?", "rules_placeholder" => FALSE, "display" => TRUE, "filter" => TRUE, "textsearch" => FALSE, "filtercolname" => "vcl.pregnancy"));
					break;
				default:
					// Master LOINC fallback
					$_SESSION[EXPORT_SERVERNAME]["vocab_params"][$_SESSION[EXPORT_SERVERNAME]["vocab_params"]["vocab"]]["query_data"]["table_name"] = "vocab_master_loinc";
					$_SESSION[EXPORT_SERVERNAME]["vocab_params"][$_SESSION[EXPORT_SERVERNAME]["vocab_params"]["vocab"]]["query_data"]["id_column"] = "l_id";
					$_SESSION[EXPORT_SERVERNAME]["vocab_params"][$_SESSION[EXPORT_SERVERNAME]["vocab_params"]["vocab"]]["query_data"]["from_tables"] = sprintf("FROM %svocab_master_loinc vml
						LEFT JOIN %ssystem_statuses ss ON (ss.id = vml.list)
						LEFT JOIN %svocab_master_condition vmc ON (vmc.c_id = vml.trisano_condition)
						LEFT JOIN %svocab_master_organism vmo ON (vmo.o_id = vml.trisano_organism)
						LEFT JOIN %svocab_master_vocab mv_c ON (mv_c.id = vmc.condition)
						LEFT JOIN %svocab_master_vocab mv_o ON (mv_o.id = vmo.organism)
						LEFT JOIN %svocab_master_vocab mv_t ON (mv_t.id = vml.trisano_test_type)
                        LEFT JOIN %svocab_master_vocab mv_aa ON (mv_aa.id = vml.antimicrobial_agent)
						LEFT JOIN %svocab_master_vocab mv_p ON (mv_p.id = vml.specimen_source)",
						$emsaDbSchemaPrefix, $emsaDbSchemaPrefix, $emsaDbSchemaPrefix, $emsaDbSchemaPrefix, $emsaDbSchemaPrefix, $emsaDbSchemaPrefix, $emsaDbSchemaPrefix, $emsaDbSchemaPrefix, $emsaDbSchemaPrefix, $emsaDbSchemaPrefix);
					$_SESSION[EXPORT_SERVERNAME]["vocab_params"][$_SESSION[EXPORT_SERVERNAME]["vocab_params"]["vocab"]]["sort_col"] = "loinc";
					$_SESSION[EXPORT_SERVERNAME]["vocab_params"][$_SESSION[EXPORT_SERVERNAME]["vocab_params"]["vocab"]]["header_icon"] = 'ui-icon-emsadictionary';
                    $_SESSION[EXPORT_SERVERNAME]["vocab_params"][$_SESSION[EXPORT_SERVERNAME]["vocab_params"]["vocab"]]["vocab_verbose"] = "Master LOINC";
					$_SESSION[EXPORT_SERVERNAME]["vocab_params"][$_SESSION[EXPORT_SERVERNAME]["vocab_params"]["vocab"]]["result_cols"] = array(
						"l_id" => array("colname" => "vml.l_id", "label" => "l_id", "rules_placeholder" => FALSE, "display" => FALSE, "filter" => FALSE, "textsearch" => FALSE),
						"loinc" => array("colname" => "vml.loinc", "label" => "LOINC", "rules_placeholder" => FALSE, "display" => TRUE, "filter" => FALSE, "textsearch" => TRUE, "main_concept" => true),
						"concept_name" => array("colname" => "vml.concept_name", "label" => "Preferred Concept Name", "rules_placeholder" => FALSE, "display" => TRUE, "filter" => FALSE, "textsearch" => TRUE, "main_concept" => true),
						"notes" => array("colname" => "vml.admin_notes", "label" => "Admin Notes", "rules_placeholder" => false, "display" => true, "filter" => false, "textsearch" => true),
                        "antimicrobial_agent" => array("colname" => "mv_aa.concept", "label" => "Antimicrobial Agent", "rules_placeholder" => FALSE, "display" => TRUE, "filter" => TRUE, "textsearch" => TRUE, "filterlookup" => TRUE, "lookupqry" => sprintf("SELECT vm.id AS value, vm.concept AS label FROM %svocab_master_vocab vm WHERE (vm.category = elr.vocab_category_id('resist_test_agent')) ORDER BY vm.concept;", $emsaDbSchemaPrefix), "filtercolname" => "vml.antimicrobial_agent"),
                        "condition_from_result" => array("colname" => "vml.condition_from_result", "label" => "Look Up Condition?", "rules_placeholder" => FALSE, "display" => TRUE, "filter" => TRUE, "textsearch" => FALSE, "filtercolname" => "vml.condition_from_result"),
						"trisano_condition" => array("colname" => "mv_c.concept", "label" => "Master Condition", "rules_placeholder" => FALSE, "display" => TRUE, "filter" => TRUE, "textsearch" => TRUE, "filterlookup" => TRUE, "lookupqry" => sprintf("SELECT vmc.c_id AS value, mv.concept AS label FROM %svocab_master_condition vmc JOIN %svocab_master_vocab mv ON (vmc.condition = mv.id) WHERE vmc.is_initial IS TRUE ORDER BY mv.concept;", $emsaDbSchemaPrefix, $emsaDbSchemaPrefix), "filtercolname" => "vml.trisano_condition"),
						"organism_from_result" => array("colname" => "vml.organism_from_result", "label" => "Look Up Organism?", "rules_placeholder" => FALSE, "display" => TRUE, "filter" => TRUE, "textsearch" => FALSE, "filtercolname" => "vml.organism_from_result"),
						"trisano_organism" => array("colname" => "mv_o.concept", "label" => "Master Organism", "rules_placeholder" => FALSE, "display" => TRUE, "filter" => TRUE, "textsearch" => TRUE, "filterlookup" => TRUE, "lookupqry" => sprintf("SELECT vmo.o_id AS value, vmo.snomed AS snomed, mv.concept AS label FROM %svocab_master_organism vmo LEFT JOIN %svocab_master_vocab mv ON (vmo.organism = mv.id) INNER JOIN %svocab_master_vocab mv2 ON (vmo.snomed_category = mv2.id) WHERE mv2.category = %svocab_category_id('snomed_category') AND mv2.concept = 'Organism' ORDER BY mv.concept, vmo.snomed;", $emsaDbSchemaPrefix, $emsaDbSchemaPrefix, $emsaDbSchemaPrefix, $emsaDbSchemaPrefix), "filtercolname" => "vml.trisano_organism"),
						"trisano_test_type" => array("colname" => "mv_t.concept", "label" => "Test Type", "rules_placeholder" => FALSE, "display" => TRUE, "filter" => TRUE, "textsearch" => TRUE, "filterlookup" => TRUE, "lookupqry" => sprintf("SELECT vm.id AS value, vm.concept AS label FROM %svocab_master_vocab vm WHERE (vm.category = elr.vocab_category_id('test_type')) ORDER BY vm.concept;", $emsaDbSchemaPrefix), "filtercolname" => "vml.trisano_test_type"),
						"specimen_source" => array("colname" => "mv_p.concept", "label" => "Specimen Source", "rules_placeholder" => FALSE, "display" => TRUE, "filter" => TRUE, "textsearch" => TRUE, "filterlookup" => TRUE, "lookupqry" => sprintf("SELECT vm.id AS value, vm.concept AS label FROM %svocab_master_vocab vm WHERE (vm.category = elr.vocab_category_id('specimen')) ORDER BY vm.concept;", $emsaDbSchemaPrefix), "filtercolname" => "vml.specimen_source"),
						"list" => array("colname" => "ss.name", "label" => "List", "rules_placeholder" => FALSE, "display" => TRUE, "filter" => TRUE, "textsearch" => FALSE, "filterlookup" => TRUE, "lookupqry" => sprintf("SELECT id AS value, name AS label FROM %ssystem_statuses WHERE id IN (%s) ORDER BY label;", $emsaDbSchemaPrefix, implode(',', array(WHITE_STATUS, BLACK_STATUS, GRAY_STATUS))), "filtercolname" => "vml.list"),
						"ml_cmr_rules" => array("colname" => "'rules_placeholder'", "label" => "rules_placeholder", "rules_placeholder" => TRUE, "display" => FALSE, "filter" => FALSE, "textsearch" => FALSE));
					break;
			}
		}

		/**
		 * Search/Filter Prep
		 *
		 * this must happen after setting vocab defaults, otherwise condition can occur where setting query params can
		 * fool the sysetm into thinking default vocab data exists when it doesn't in cases of a linked query
		 */
		// pre-build our vocab-specific search data...
		if (isset($_GET["q"])) {
			if ((trim($_GET["q"]) != "Enter search terms...") && (strlen(trim($_GET["q"])) > 0)) {
				$_SESSION[EXPORT_SERVERNAME]["vocab_params"][$_SESSION[EXPORT_SERVERNAME]["vocab_params"]["vocab"]]["q_display"] = htmlentities(trim($_GET["q"]), ENT_QUOTES, "UTF-8");
				$_SESSION[EXPORT_SERVERNAME]["vocab_params"][$_SESSION[EXPORT_SERVERNAME]["vocab_params"]["vocab"]]["q_sql"] = pg_escape_string(trim($_GET["q"]));
				if (!isset($_GET['f'])) {
					// search query found, but no filters selected
					// if any filters were previously SESSIONized, they've been deselected via the UI, so we'll clear them...
					unset($_SESSION[EXPORT_SERVERNAME]["vocab_params"][$_SESSION[EXPORT_SERVERNAME]["vocab_params"]["vocab"]]["filters"]);
				}
			} else {
				// search field was empty/defaulted, so we'll destroy the saved search params...
				unset($_SESSION[EXPORT_SERVERNAME]["vocab_params"][$_SESSION[EXPORT_SERVERNAME]["vocab_params"]["vocab"]]["q_display"]);
				unset($_SESSION[EXPORT_SERVERNAME]["vocab_params"][$_SESSION[EXPORT_SERVERNAME]["vocab_params"]["vocab"]]["q_sql"]);
				// not only was search blank, but no filters selected, so clear them as well...
				unset($_SESSION[EXPORT_SERVERNAME]["vocab_params"][$_SESSION[EXPORT_SERVERNAME]["vocab_params"]["vocab"]]["filters"]);
			}
		}

		// update SESSIONized filters or destroy them if no filters are selected...
		if (isset($_GET['f'])) {
			if (is_array($_GET['f'])) {
				$_SESSION[EXPORT_SERVERNAME]["vocab_params"][$_SESSION[EXPORT_SERVERNAME]["vocab_params"]["vocab"]]["filters"] = $_GET['f'];
			}
		}

		// if child loinc & no filter for archived bit, default to hide archived loincs
		if (($_SESSION[EXPORT_SERVERNAME]["vocab_params"]["vocab"] == 5) && (!isset($_SESSION[EXPORT_SERVERNAME]["vocab_params"][$_SESSION[EXPORT_SERVERNAME]["vocab_params"]["vocab"]]["filters"]["archived"]))) {
			$_SESSION[EXPORT_SERVERNAME]["vocab_params"][$_SESSION[EXPORT_SERVERNAME]["vocab_params"]["vocab"]]["filters"]["archived"][] = 'f';
		}

		/**
		 * if edit_id passed, include editor
		 *
		 * this must happen after setting the session vocab value & vocab type defaults, else xref links between dependent items will fail
		 */
		if (isset($_GET['edit_id'])){
			include __DIR__ . '/vocabulary_edit.php';
		}

		// sort out our sorting...
		if (isset($_GET["order"])) {
			$_SESSION[EXPORT_SERVERNAME]["vocab_params"][$_SESSION[EXPORT_SERVERNAME]["vocab_params"]["vocab"]]["sort_order"] = ($_GET["order"] == "2") ? "DESC" : "ASC";
		}

		// check that column name is a valid column...
		$valid_cols_qry = "SELECT column_name FROM information_schema.columns WHERE table_name IN ('vocab_master_loinc', 'vocab_master_organism', 'vocab_master_condition', 'vocab_child_loinc', 'vocab_child_organism');";
		$valid_cols_rs = pg_query($host_pa,$valid_cols_qry) or die("Can't Perform Query ".pg_last_error());
		while ($valid_cols_row = pg_fetch_object($valid_cols_rs)) {
			$valid_cols[] = $valid_cols_row->column_name;
		}
		pg_free_result($valid_cols_rs);

		// get a list of all columns that are boolean...
		$bool_cols_qry = "SELECT column_name FROM information_schema.columns WHERE table_name IN ('vocab_master_loinc', 'vocab_master_organism', 'vocab_master_condition', 'vocab_child_loinc', 'vocab_child_organism', 'vocab_icd') AND data_type = 'boolean';";
		$bool_cols_rs = pg_query($host_pa,$bool_cols_qry) or die("Can't Perform Query ".pg_last_error());
		while ($bool_cols_row = pg_fetch_object($bool_cols_rs)) {
			$bool_cols[] = $bool_cols_row->column_name;
		}
		pg_free_result($bool_cols_rs);

		if (isset($_GET["sort"]) && is_array($_SESSION[EXPORT_SERVERNAME]["vocab_params"][$_SESSION[EXPORT_SERVERNAME]["vocab_params"]["vocab"]]["result_cols"])) {
			if (is_array($_SESSION[EXPORT_SERVERNAME]["vocab_params"][$_SESSION[EXPORT_SERVERNAME]["vocab_params"]["vocab"]]["result_cols"][strtolower(trim($_GET["sort"]))])) {
				$_SESSION[EXPORT_SERVERNAME]["vocab_params"][$_SESSION[EXPORT_SERVERNAME]["vocab_params"]["vocab"]]["sort_col"] = strtolower(trim($_GET["sort"]));
			}
		}


		$vocab_fromwhere = sprintf("%s", $_SESSION[EXPORT_SERVERNAME]["vocab_params"][$_SESSION[EXPORT_SERVERNAME]["vocab_params"]["vocab"]]["query_data"]["from_tables"]);

		$where_count = 0;
		// handle any search terms or filters...
		if (isset($_SESSION[EXPORT_SERVERNAME]["vocab_params"][$_SESSION[EXPORT_SERVERNAME]["vocab_params"]["vocab"]]["q_sql"])) {
			// we've got some query params
			$where_count = 1;
			$vocab_fromwhere .= " WHERE (";

			foreach ($_SESSION[EXPORT_SERVERNAME]["vocab_params"][$_SESSION[EXPORT_SERVERNAME]["vocab_params"]["vocab"]]["result_cols"] as $searchcol => $searchcoldata) {
				if ($searchcoldata['textsearch']) {
					if (!in_array($searchcol, $bool_cols)) {
						if ($where_count > 1) {
							$vocab_fromwhere .= " OR ";
						}
						$vocab_fromwhere .= sprintf("%s ILIKE '%%%s%%'", $searchcoldata['colname'], $_SESSION[EXPORT_SERVERNAME]["vocab_params"][$_SESSION[EXPORT_SERVERNAME]["vocab_params"]["vocab"]]["q_sql"]);
						$where_count++;
					}
				}
			}

			$vocab_fromwhere .= ")";
		}

		if (isset($_SESSION[EXPORT_SERVERNAME]["vocab_params"][$_SESSION[EXPORT_SERVERNAME]["vocab_params"]["vocab"]]["filters"])) {
			// need to apply filters
			$filter_count = 0;
			if ($where_count == 0) {
				// not already a WHERE clause for search terms
				$vocab_fromwhere .= " WHERE";
			}

			foreach ($_SESSION[EXPORT_SERVERNAME]["vocab_params"][$_SESSION[EXPORT_SERVERNAME]["vocab_params"]["vocab"]]["filters"] as $sqlfiltercol => $sqlfiltervals) {
				unset($filter_temp);
				$nullfilter = FALSE;
				if (($filter_count > 0) || ($where_count > 1)) {
					$vocab_fromwhere .= " AND (";
				} else {
					$vocab_fromwhere .= " (";
				}
				foreach ($sqlfiltervals as $sqlfilterval) {
					if (is_null($sqlfilterval) || (strlen(trim($sqlfilterval)) == 0)) {
						$nullfilter = TRUE;
					} else {
						$filter_temp[] = "'" . pg_escape_string($sqlfilterval) . "'";
					}
				}

				if ($nullfilter && isset($filter_temp) && is_array($filter_temp)) {
                    if (in_array($sqlfiltercol, $bool_cols)) {
                        $vocab_fromwhere .= $_SESSION[EXPORT_SERVERNAME]["vocab_params"][$_SESSION[EXPORT_SERVERNAME]["vocab_params"]["vocab"]]["result_cols"][$sqlfiltercol]["filtercolname"] . " IN (" . implode(",", $filter_temp) . ") OR " . $_SESSION[EXPORT_SERVERNAME]["vocab_params"][$_SESSION[EXPORT_SERVERNAME]["vocab_params"]["vocab"]]["result_cols"][$sqlfiltercol]["filtercolname"] . " IS NULL";
                    } else {
                        $vocab_fromwhere .= $_SESSION[EXPORT_SERVERNAME]["vocab_params"][$_SESSION[EXPORT_SERVERNAME]["vocab_params"]["vocab"]]["result_cols"][$sqlfiltercol]["filtercolname"] . " IN (" . implode(",", $filter_temp) . ") OR " . $_SESSION[EXPORT_SERVERNAME]["vocab_params"][$_SESSION[EXPORT_SERVERNAME]["vocab_params"]["vocab"]]["result_cols"][$sqlfiltercol]["filtercolname"] . " IS NULL OR " . $_SESSION[EXPORT_SERVERNAME]["vocab_params"][$_SESSION[EXPORT_SERVERNAME]["vocab_params"]["vocab"]]["result_cols"][$sqlfiltercol]["filtercolname"] . " = ''";
                    }
				} elseif (isset($filter_temp) && is_array($filter_temp)) {
					$vocab_fromwhere .= $_SESSION[EXPORT_SERVERNAME]["vocab_params"][$_SESSION[EXPORT_SERVERNAME]["vocab_params"]["vocab"]]["result_cols"][$sqlfiltercol]["filtercolname"] . " IN (" . implode(",", $filter_temp) . ")";
				} elseif ($nullfilter) {
                    if (in_array($sqlfiltercol, $bool_cols)) {
                        $vocab_fromwhere .= $_SESSION[EXPORT_SERVERNAME]["vocab_params"][$_SESSION[EXPORT_SERVERNAME]["vocab_params"]["vocab"]]["result_cols"][$sqlfiltercol]["filtercolname"] . " IS NULL";
                    } else {
                        $vocab_fromwhere .= $_SESSION[EXPORT_SERVERNAME]["vocab_params"][$_SESSION[EXPORT_SERVERNAME]["vocab_params"]["vocab"]]["result_cols"][$sqlfiltercol]["filtercolname"] . " IS NULL OR " . $_SESSION[EXPORT_SERVERNAME]["vocab_params"][$_SESSION[EXPORT_SERVERNAME]["vocab_params"]["vocab"]]["result_cols"][$sqlfiltercol]["filtercolname"] . " = ''";
                    }
				}

                $vocab_fromwhere .= ")";
				$filter_count++;
			}
		}


		// finish up our 'ORDER BY' clause now that we have all of our 'WHERE' stuff figured out...
		// if child vocab, always sort by lab first, then by the specified sort column.
		if ( (intval($_SESSION[EXPORT_SERVERNAME]["vocab_params"]["vocab"]) == 4) || (intval($_SESSION[EXPORT_SERVERNAME]["vocab_params"]["vocab"]) == 5) || (intval($_SESSION[EXPORT_SERVERNAME]["vocab_params"]["vocab"]) == 9) ) {
			$_SESSION[EXPORT_SERVERNAME]["vocab_params"][$_SESSION[EXPORT_SERVERNAME]["vocab_params"]["vocab"]]["query_data"]["order_by"] = sprintf("ORDER BY lab ASC, %s %s", $_SESSION[EXPORT_SERVERNAME]["vocab_params"][$_SESSION[EXPORT_SERVERNAME]["vocab_params"]["vocab"]]["result_cols"][$_SESSION[EXPORT_SERVERNAME]["vocab_params"][$_SESSION[EXPORT_SERVERNAME]["vocab_params"]["vocab"]]["sort_col"]]["colname"], $_SESSION[EXPORT_SERVERNAME]["vocab_params"][$_SESSION[EXPORT_SERVERNAME]["vocab_params"]["vocab"]]["sort_order"]);
		} else {
			$_SESSION[EXPORT_SERVERNAME]["vocab_params"][$_SESSION[EXPORT_SERVERNAME]["vocab_params"]["vocab"]]["query_data"]["order_by"] = sprintf("ORDER BY %s %s", $_SESSION[EXPORT_SERVERNAME]["vocab_params"][$_SESSION[EXPORT_SERVERNAME]["vocab_params"]["vocab"]]["result_cols"][$_SESSION[EXPORT_SERVERNAME]["vocab_params"][$_SESSION[EXPORT_SERVERNAME]["vocab_params"]["vocab"]]["sort_col"]]["colname"], $_SESSION[EXPORT_SERVERNAME]["vocab_params"][$_SESSION[EXPORT_SERVERNAME]["vocab_params"]["vocab"]]["sort_order"]);
		}

?>

<script>
	$(function() {

		<?php
			if ($_SESSION[EXPORT_SERVERNAME]["vocab_params"]["vocab"] == 5) {
				// Child LOINC Quant Interpretive rules
				include_once __DIR__ . '/vocab_rules_js_childloinc.php';
			} elseif ($_SESSION[EXPORT_SERVERNAME]["vocab_params"]["vocab"] == 1) {
				// Master LOINCCMR rules
				include_once __DIR__ . '/vocab_rules_js_masterloinc.php';
			} elseif ($_SESSION[EXPORT_SERVERNAME]["vocab_params"]["vocab"] == 3) {
				// Master SNOMED CMR rules
				include_once __DIR__ . '/vocab_rules_js_mastersnomed.php';
			} elseif ($_SESSION[EXPORT_SERVERNAME]["vocab_params"]["vocab"] == 2) {
				// Master Condition -- Graylist Rules
				include_once __DIR__ . '/vocab_rules_js_graylist.php';
			}
		?>

		$(".vocab_filter_selectall").on("click", function() {
			var thisFilter = $(this).attr("rel");
			$("div.addnew_lookup_checklist[rel='"+thisFilter+"']").find($(":input")).each(function() {
				if (!$(this).is(':checked')) {
					$(this).trigger('click');
				}
			});
		});

		$(".vocab_filter_selectnone").on("click", function() {
			var thisFilter = $(this).attr("rel");
			$("div.addnew_lookup_checklist[rel='"+thisFilter+"']").find($(":input")).each(function() {
				if ($(this).is(':checked')) {
					$(this).trigger('click');
				}
			});
		});

		$("#latestTasks tr").on("click", function() {
			$(this).toggleClass("focus_row");
		});

		$(".colheader_sort_down").button({
			icon: "ui-icon-circle-arrow-s",
            iconPosition: "end"
		});

		$(".colheader_sort_up").button({
			icon: "ui-icon-circle-arrow-n",
            iconPosition: "end"
		});

		$("#latestTasks tbody tr").addClass("all_row");

	<?php
		if (isset($_GET['edit_id'])){
	?>
		$("#vocab_row_<?php echo intval(trim($_GET['edit_id'])); ?>").addClass("focus_row");
		setTimeout(function() {
			//deprecated... var container = (($.browser.msie || $.browser.mozilla) ? $("body,html") : $("body"));
			var container = $("body,html");
			var scrollTo = $("#vocab_row_<?php echo intval(trim($_GET['edit_id'])); ?>");
			container.scrollTop(
				scrollTo.offset().top - container.offset().top + container.scrollTop()
			);
		}, 10);
	<?php
		}

		if (isset($_GET['focus_id'])){
	?>
		$("#vocab_row_<?php echo intval(trim($_GET['focus_id'])); ?>").addClass("focus_row");
		setTimeout(function() {
			//deprecated... var container = (($.browser.msie || $.browser.mozilla) ? $("body,html") : $("body"));
			var container = $("body,html");
			var scrollTo = $("#vocab_row_<?php echo intval(trim($_GET['focus_id'])); ?>");
			container.scrollTop(
				scrollTo.offset().top - container.offset().top + container.scrollTop()
			);
		}, 10);
	<?php
		}
	?>

		$("#toggle_filters").button({
            icon: "ui-icon-triangle-1-n",
            iconPosition: "end"
        }).on("click", function() {
			$(".vocab_filter").toggle("blind");
			var objIcon = $(this).button("option", "icon");
			if (objIcon === "ui-icon-triangle-1-s") {
				$(this).button("option", "icon", "ui-icon-triangle-1-n");
				$(this).button("option", "iconPosition", "end");
				$(this).button("option", "label", "Hide Filters");
				$("#addnew_form").hide();
				$("#addnew_button").show();
			} else {
				$(this).button("option", "icon", "ui-icon-triangle-1-s");
				$(this).button("option", "iconPosition", "end");
				$(this).button("option", "label", "Show Filters");
			}
		});

		$(".vocab_filter").hide();
		$("#toggle_filters").button("option", "icon", "ui-icon-triangle-1-s");
		$("#toggle_filters").button("option", "iconPosition", "end");
		$("#toggle_filters").button("option", "label", "Show Filters");

		$("#clear_filters").button({
            icon: "ui-icon-elrcancel"
        }).on("click", function() {
            let searchForm = $("#search_form");
            let msListBoxes = $("ul.vocab_filter_checklist[role=listbox]");
            $(".pseudo_select").removeAttr("checked");
            $(".pseudo_select_label").removeClass("pseudo_select_on");
            msListBoxes.removeAttr("aria-activedescendant");
            msListBoxes.children().removeClass("multiselect-focused");
            msListBoxes.children().attr("aria-selected", "false");
            msListBoxes.find("input[type=checkbox]").prop("checked", false);
            searchForm[0].reset();
            $("#q").val("").trigger("blur");
            searchForm.trigger("submit");
		});

		$("#q_go").button({
			icon: "ui-icon-elrsearch"
		}).on("click", function(){
			$("#search_form").trigger("submit");
		});

		$("#apply_filters").button({
			icon: "ui-icon-elroptions"
		}).on("click", function(){
			$("#search_form").trigger("submit");
		});

		$("#q").addClass("search_empty").val("Enter search terms...").on("click", function() {
			var search_val = $("#q").val().trim();
			if (search_val === "Enter search terms...") {
				$(this).removeClass("search_empty").val("");
			}
		}).on("blur", function() {
			var search_val_ln = $("#q").val().trim().length;
			if (search_val_ln === 0) {
				$("#q").addClass("search_empty").val("Enter search terms...");
			}
		});

		<?php
			if (isset($_SESSION[EXPORT_SERVERNAME]["vocab_params"][$_SESSION[EXPORT_SERVERNAME]["vocab_params"]["vocab"]]["q_display"])) {
				if ($_SESSION[EXPORT_SERVERNAME]["vocab_params"][$_SESSION[EXPORT_SERVERNAME]["vocab_params"]["vocab"]]["q_display"] != "Enter search terms...") {
		?>
		$("#q").removeClass("search_empty").val("<?php echo $_SESSION[EXPORT_SERVERNAME]["vocab_params"][$_SESSION[EXPORT_SERVERNAME]["vocab_params"]["vocab"]]["q_display"]; ?>");
		<?php
				}
			}
		?>

		$("#user_rows_change").button();

		$("#addnew_button").button({
            icon: "ui-icon-elrplus"
        }).on("click", function() {
			$("#addnew_form").show();
			$(".import_error").hide();
			$("#new_category").trigger("focus");
			$(this).hide();
		});

		$("#addnew_cancel").button({
			icon: "ui-icon-elrcancel"
		}).on("click", function() {
			$("#addnew_form").hide();
			$("#addnew_button").show();
		});

		$("#new_savevocab").button({
            icon: "ui-icon-elrsave"
        });

		$("#new_vocab_form").on("submit", function(e) {
			var incomplete_fields = 0;
			$(":input.required", this).each(function() {
				if ($(this).val() == "" || $(this).val() == -1) {
					alert($('label[for="'+$(this).attr('id')+'"]').text()+' requires a value');
					incomplete_fields++;
				}
			});
			if (incomplete_fields > 0) {
				return false;
			} else {
				return true;
			}
		});

		$(".edit_vocab").button({
				icon: "ui-icon-elrpencil",
				showLabel: false
			}).next().button({
				icon: "ui-icon-elrclose",
				showLabel: false
			}).parent().controlgroup();

		$("#confirm_delete_dialog").dialog({
			autoOpen: false,
			modal: true,
			draggable: false,
			resizable: false,
			width: 400
		});

		$(".delete_vocab").on("click", function(e) {
			e.preventDefault();

			var jsonObj = JSON.parse($(this).val());
			if (jsonObj.current_id) {
				var deleteAction = "<?php echo $webappBaseUrl; ?>?selected_page=6&submenu=3&cat=<?php echo $navCat; ?>&subcat=<?php echo $navSubcat; ?>&delete_id="+jsonObj.current_id+"&focus_id="+jsonObj.previous_id;

				$("#confirm_delete_dialog").dialog('option', 'buttons', {
						"Delete" : function() {
							window.location.href = deleteAction;
							},
						"Cancel" : function() {
							$(this).dialog("close");
							}
						});

				$("#confirm_delete_dialog").dialog("open");
			}

		});

		$(".edit_vocab").on("click", function(e) {
			e.preventDefault();
			var editAction = "<?php echo $webappBaseUrl; ?>?selected_page=6&submenu=3&cat=<?php echo $navCat; ?>&subcat=<?php echo $navSubcat; ?>&edit_id="+$(this).val();
			window.location.href = editAction;
		});

	});
</script>

<h1 class="elrhdg"><span class="ui-icon ui-icon-header <?php echo $_SESSION[EXPORT_SERVERNAME]["vocab_params"][$_SESSION[EXPORT_SERVERNAME]["vocab_params"]["vocab"]]["header_icon"]; ?>"></span><?php echo $_SESSION[EXPORT_SERVERNAME]["vocab_params"][$_SESSION[EXPORT_SERVERNAME]["vocab_params"]["vocab"]]["vocab_verbose"]; ?></h1>

<?php
		if (isset($_GET['delete_id'])) {
			/*
			 * delete existing record
			 */
			if (intval(trim($_GET['delete_id'])) > 0) {
				// make sure passed ID is a valid integer
				unset($delete_sql);
				unset($dependency_sql);
				$delete_id = intval(trim($_GET['delete_id']));

				switch (intval($_SESSION[EXPORT_SERVERNAME]["vocab_params"]["vocab"])) {
					case 5:
						// Child LOINC
						$va_prev_vals = $va->getPreviousVals($delete_id, VocabAudit::TABLE_CHILD_LOINC);
						$va_table = VocabAudit::TABLE_CHILD_LOINC;
						$valid_sql = sprintf("SELECT count(id) AS counter FROM %svocab_child_loinc WHERE id = %d", $emsaDbSchemaPrefix, $delete_id);
						$delete_sql = sprintf("BEGIN;\nDELETE FROM ONLY %svocab_c2m_testresult WHERE child_loinc_id = %d;\nDELETE FROM ONLY %svocab_child_loinc WHERE id = %d;\nCOMMIT;", $emsaDbSchemaPrefix, $delete_id, $emsaDbSchemaPrefix, $delete_id);
						// no dependencies
						break;
					case 4:
						// Child Organism
						$va_prev_vals = $va->getPreviousVals($delete_id, VocabAudit::TABLE_CHILD_SNOMED);
						$va_table = VocabAudit::TABLE_CHILD_SNOMED;
						$valid_sql = sprintf("SELECT count(id) AS counter FROM %svocab_child_organism WHERE id = %d", $emsaDbSchemaPrefix, $delete_id);
						$delete_sql = sprintf("DELETE FROM ONLY %svocab_child_organism WHERE id = %d", $emsaDbSchemaPrefix, $delete_id);
						// no dependencies
						break;
					case 13:
						// Master PFGE to SNOMED
						$va_prev_vals = $va->getPreviousVals($delete_id, VocabAudit::TABLE_MASTER_PFGE);
						$va_table = VocabAudit::TABLE_MASTER_PFGE;
						$valid_sql = sprintf("SELECT count(id) AS counter FROM %svocab_pfge WHERE id = %d", $emsaDbSchemaPrefix, $delete_id);
						$delete_sql = sprintf("DELETE FROM ONLY %svocab_pfge WHERE id = %d", $emsaDbSchemaPrefix, $delete_id);
						// no dependencies
						break;
                    case 14:
                        // Master ICD Code
                        $va_prev_vals = $va->getPreviousVals($delete_id, VocabAudit::TABLE_MASTER_ICD);
						$va_table = VocabAudit::TABLE_MASTER_ICD;
						$valid_sql = sprintf("SELECT count(id) AS counter FROM %svocab_icd WHERE id = %d", $emsaDbSchemaPrefix, $delete_id);
						$delete_sql = sprintf("DELETE FROM ONLY %svocab_icd WHERE id = %d", $emsaDbSchemaPrefix, $delete_id);
						// no dependencies
						break;
					case 3:
						// Master Organism
						$va_prev_vals = $va->getPreviousVals($delete_id, VocabAudit::TABLE_MASTER_SNOMED);
						$va_table = VocabAudit::TABLE_MASTER_SNOMED;
						$valid_sql = sprintf("SELECT count(o_id) AS counter FROM %svocab_master_organism WHERE o_id = %d", $emsaDbSchemaPrefix, $delete_id);
						$delete_sql = sprintf("DELETE FROM ONLY %svocab_master_organism WHERE o_id = %d", $emsaDbSchemaPrefix, $delete_id);
						$dependency_sql = sprintf("SELECT ((SELECT count(l_id) FROM %svocab_master_loinc WHERE trisano_organism = %d) + (SELECT count(id) FROM %svocab_child_organism WHERE organism = %d)) AS dependents;",
							$emsaDbSchemaPrefix, $delete_id,
							$emsaDbSchemaPrefix, $delete_id);
						break;
					case 2:
						// Master Condition
						$va_prev_vals = $va->getPreviousVals($delete_id, VocabAudit::TABLE_MASTER_CONDITION);
						$va_table = VocabAudit::TABLE_MASTER_CONDITION;
						$valid_sql = sprintf("SELECT count(c_id) AS counter FROM %svocab_master_condition WHERE c_id = %d", $emsaDbSchemaPrefix, $delete_id);
						$delete_sql = sprintf("DELETE FROM ONLY %svocab_master_condition WHERE c_id = %d", $emsaDbSchemaPrefix, $delete_id);
						$dependency_sql = sprintf("SELECT ((SELECT count(l_id) FROM %svocab_master_loinc WHERE trisano_condition = %d) + (SELECT count(o_id) FROM %svocab_master_organism WHERE condition = %d)) AS dependents;",
							$emsaDbSchemaPrefix, $delete_id,
							$emsaDbSchemaPrefix, $delete_id);
						break;
					default:
						// Master LOINC
						$va_prev_vals = $va->getPreviousVals($delete_id, VocabAudit::TABLE_MASTER_LOINC);
						$va_table = VocabAudit::TABLE_MASTER_LOINC;
						$valid_sql = sprintf("SELECT count(l_id) AS counter FROM %svocab_master_loinc WHERE l_id = %d", $emsaDbSchemaPrefix, $delete_id);
						$delete_sql = sprintf("DELETE FROM ONLY %svocab_master_loinc WHERE l_id = %d", $emsaDbSchemaPrefix, $delete_id);
						$dependency_sql = sprintf("SELECT count(id) AS dependents FROM %svocab_child_loinc WHERE master_loinc = %d;",
							$emsaDbSchemaPrefix, $delete_id);
						break;
				}

				// check to make sure that the record ID passed actually exists in the correct table...
				$valid_rs = @pg_query($host_pa, $valid_sql);
				$valid_count = @pg_fetch_result($valid_rs, 0, "counter");
				if ($valid_count > 0) {
					// check for dependents
					$dependency_count = 0;
					if (!empty($dependency_sql)) {
						$dependency_rs = @pg_query($host_pa, $dependency_sql);
						$dependency_count = @pg_fetch_result($dependency_rs, 0, "dependents");
					}
					if ($dependency_count > 0) {
						DisplayUtils::drawError(sprintf("Cannot delete vocabulary:  There are %s dependents of this %s record.  Please resolve these dependencies and try again.", intval($dependency_count), DisplayUtils::xSafe($_SESSION[EXPORT_SERVERNAME]["vocab_params"][$_SESSION[EXPORT_SERVERNAME]["vocab_params"]["vocab"]]["vocab_verbose"])));
					} else {
						// commit the delete
						if (@pg_query($host_pa, $delete_sql)) {
							DisplayUtils::drawHighlight(sprintf("%s record was successfully deleted!", $_SESSION[EXPORT_SERVERNAME]["vocab_params"][$_SESSION[EXPORT_SERVERNAME]["vocab_params"]["vocab"]]["vocab_verbose"]), "ui-icon-check");

							$va->resetAudit();
							$va->setOldVals($va_prev_vals);
							$va->auditVocab($delete_id, $va_table, VocabAudit::ACTION_DELETE);
						} else {
							DisplayUtils::drawError("Could not delete this record.");
						}
					}
				} else {
					DisplayUtils::drawError("Cannot delete vocabulary:  Record not found");
				}
			} else {
				DisplayUtils::drawError("Cannot delete vocabulary:  Record not found");
			}

		} elseif (isset($_POST['add_flag'])) {
			/*
			 * add a new record
			 */
			unset($new_fields);
			unset($new_table);

			switch (intval($_SESSION[EXPORT_SERVERNAME]["vocab_params"]["vocab"])) {
				case 5:
					// Child LOINC
					$va_table = VocabAudit::TABLE_CHILD_LOINC;
					$va_id_col = 'id';
					$new_table = sprintf("%svocab_child_loinc", $emsaDbSchemaPrefix);
					$new_fields = array(
							array("get_field" => "new_lab_id", "colname" => "lab_id", "validtype" => "list-required"),
							array("get_field" => "new_archived", "colname" => "archived", "validtype" => "bool"),
							array("get_field" => "new_child_loinc", "colname" => "child_loinc", "validtype" => "text-required"),
							array("get_field" => "new_master_loinc", "colname" => "master_loinc", "validtype" => "list-required"),
							array("get_field" => "new_child_orderable_test_code", "colname" => "child_orderable_test_code", "validtype" => "text"),
							array("get_field" => "new_child_resultable_test_code", "colname" => "child_resultable_test_code", "validtype" => "text"),
							array("get_field" => "new_child_concept_name", "colname" => "child_concept_name", "validtype" => "text"),
							array("get_field" => "new_child_alias", "colname" => "child_alias", "validtype" => "text"),
							array("get_field" => "new_interpret_results", "colname" => "interpret_results", "validtype" => "bool"),
                            array("get_field" => "new_interpret_override", "colname" => "interpret_override", "validtype" => "bool-nullable"),
                            array("get_field" => "new_allow_preprocessing", "colname" => "allow_preprocessing", "validtype" => "bool"),
							array("get_field" => "new_workflow", "colname" => "workflow", "validtype" => "list-required"),
							array("get_field" => "new_location", "colname" => "result_location", "validtype" => "list"),
							array("get_field" => "new_units", "colname" => "units", "validtype" => "text"),
							array("get_field" => "new_pregnancy", "colname" => "pregnancy", "validtype" => "bool"),
							array("get_field" => "new_refrange", "colname" => "refrange", "validtype" => "text"),
							array("get_field" => "new_hl7_refrange", "colname" => "hl7_refrange", "validtype" => "text"),
                            array("get_field" => "new_offscale_low_result", "colname" => "offscale_low_result", "validtype" => "list-required"),
                            array("get_field" => "new_offscale_high_result", "colname" => "offscale_high_result", "validtype" => "list-required")
						);
					break;
				case 4:
					// Child Organism
					$va_table = VocabAudit::TABLE_CHILD_SNOMED;
					$va_id_col = 'id';
					$new_table = sprintf("%svocab_child_organism", $emsaDbSchemaPrefix);
					$new_fields = array(
							array("get_field" => "new_lab_id", "colname" => "lab_id", "validtype" => "list-required"),
							array("get_field" => "new_child_code", "colname" => "child_code", "validtype" => "text-required"),
							array("get_field" => "new_organism", "colname" => "organism", "validtype" => "list"),
							array("get_field" => "new_test_result_id", "colname" => "test_result_id", "validtype" => "list"),
							array("get_field" => "new_result_value", "colname" => "result_value", "validtype" => "text"),
							array("get_field" => "new_comment", "colname" => "comment", "validtype" => "text")
						);
					break;
                case 13:
					// Master PFGE
					$va_table = VocabAudit::TABLE_MASTER_PFGE;
					$va_id_col = 'id';
					$new_table = sprintf("%svocab_pfge", $emsaDbSchemaPrefix);
					$new_fields = array(
							array("get_field" => "new_pattern", "colname" => "pattern", "validtype" => "text-required"),
							array("get_field" => "new_organism", "colname" => "master_snomed_id", "validtype" => "list-required")
						);
					break;
				case 14:
                    // ICD codes
                    $va_table = VocabAudit::TABLE_MASTER_ICD;
					$va_id_col = 'id';
					$new_table = sprintf("%svocab_icd", $emsaDbSchemaPrefix);
					$new_fields = array(
							array("get_field" => "new_codeset", "colname" => "codeset_id", "validtype" => "list-required"),
							array("get_field" => "new_icd_code", "colname" => "code_value", "validtype" => "text-required"),
							array("get_field" => "new_code_description", "colname" => "code_description", "validtype" => "text"),
							array("get_field" => "new_master_condition", "colname" => "master_condition_id", "validtype" => "list-nullable"),
							array("get_field" => "new_master_snomed", "colname" => "master_snomed_id", "validtype" => "list-nullable"),
							array("get_field" => "new_allow_new_cmr", "colname" => "allow_new_cmr", "validtype" => "bool"),
							array("get_field" => "new_allow_update_cmr", "colname" => "allow_update_cmr", "validtype" => "bool"),
							array("get_field" => "new_is_surveillance", "colname" => "is_surveillance", "validtype" => "bool"),
							array("get_field" => "new_pregnancy_indicator", "colname" => "pregnancy_indicator", "validtype" => "bool"),
							array("get_field" => "new_pregnancy_status", "colname" => "pregnancy_status", "validtype" => "bool-nullable")
						);
					break;
				case 3:
					// Master Organism
					$va_table = VocabAudit::TABLE_MASTER_SNOMED;
					$va_id_col = 'o_id';
					$new_table = sprintf("%svocab_master_organism", $emsaDbSchemaPrefix);
					$new_fields = array(
							array("get_field" => "new_snomed_category", "colname" => "snomed_category", "validtype" => "list-required"),
							array("get_field" => "new_condition", "colname" => "condition", "validtype" => "list"),
							array("get_field" => "new_snomed", "colname" => "snomed", "validtype" => "text"),
							array("get_field" => "new_snomed_alt", "colname" => "snomed_alt", "validtype" => "text"),
							array("get_field" => "new_organism", "colname" => "organism", "validtype" => "list"),
							array("get_field" => "new_list", "colname" => "list", "validtype" => "list"),
							array("get_field" => "new_test_result", "colname" => "test_result", "validtype" => "list"),
                            array("get_field" => "new_semi_auto_usage", "colname" => "semi_auto_usage", "validtype" => "bool-nullable")
						);
					break;
				case 2:
					// Master Condition
					$va_table = VocabAudit::TABLE_MASTER_CONDITION;
					$va_id_col = 'c_id';
					$new_table = sprintf("%svocab_master_condition", $emsaDbSchemaPrefix);
					$new_fields = array(
							array("get_field" => "new_condition", "colname" => "condition", "validtype" => "list-required"),
							array("get_field" => "new_is_initial", "colname" => "is_initial", "validtype" => "bool"),
							array("get_field" => "new_disease_category", "colname" => "disease_category", "validtype" => "list-required"),
							array("get_field" => "new_gateways", "colname" => "gateway_xref", "validtype" => "multi"),
							array("get_field" => "new_check_xref_first", "colname" => "check_xref_first", "validtype" => "bool"),
							array("get_field" => "new_whitelist_ignore_case_status", "colname" => "whitelist_ignore_case_status", "validtype" => "bool"),
							array("get_field" => "new_whitelist_override", "colname" => "whitelist_override", "validtype" => "bool"),
							array("get_field" => "new_allow_multi_assign", "colname" => "allow_multi_assign", "validtype" => "bool"),
                            array("get_field" => "new_o2m_addcmr_exclusions", "colname" => "o2m_addcmr_exclusions", "validtype" => "multi"),
                            array("get_field" => "new_valid_specimen", "colname" => "valid_specimen", "validtype" => "multi"),
							array("get_field" => "new_invalid_specimen", "colname" => "invalid_specimen", "validtype" => "multi"),
                            array("get_field" => "new_ignore_age_rule", "colname" => "ignore_age_rule", "validtype" => "text"),
							array("get_field" => "new_white_rule", "colname" => "white_rule", "validtype" => "text"),
							array("get_field" => "new_contact_white_rule", "colname" => "contact_white_rule", "validtype" => "text"),
							array("get_field" => "new_immediate_notify", "colname" => "immediate_notify", "validtype" => "bool"),
							array("get_field" => "new_require_specimen", "colname" => "require_specimen", "validtype" => "bool"),
							array("get_field" => "new_notify_state", "colname" => "notify_state", "validtype" => "bool"),
                            array("get_field" => "new_ast_multi_colony", "colname" => "ast_multi_colony", "validtype" => "bool"),
                            array("get_field" => "new_bypass_oos", "colname" => "bypass_oos", "validtype" => "bool"),
                            array("get_field" => "new_blacklist_preliminary", "colname" => "blacklist_preliminary", "validtype" => "bool"),
                            array("get_field" => "new_district_override", "colname" => "district_override", "validtype" => "list")
						);
					break;
				default:
					// Master LOINC
					$va_table = VocabAudit::TABLE_MASTER_LOINC;
					$va_id_col = 'l_id';
					$new_table = sprintf("%svocab_master_loinc", $emsaDbSchemaPrefix);
					$new_fields = array(
							array("get_field" => "new_loinc", "colname" => "loinc", "validtype" => "text-required"),
							array("get_field" => "new_concept_name", "colname" => "concept_name", "validtype" => "text"),
							array("get_field" => "new_antimicrobial_agent", "colname" => "antimicrobial_agent", "validtype" => "list"),
							array("get_field" => "new_condition_from_result", "colname" => "condition_from_result", "validtype" => "bool"),
							array("get_field" => "new_trisano_condition", "colname" => "trisano_condition", "validtype" => "list"),
							array("get_field" => "new_organism_from_result", "colname" => "organism_from_result", "validtype" => "bool"),
							array("get_field" => "new_trisano_organism", "colname" => "trisano_organism", "validtype" => "list"),
							array("get_field" => "new_trisano_test_type", "colname" => "trisano_test_type", "validtype" => "list-required"),
							array("get_field" => "new_specimen_source", "colname" => "specimen_source", "validtype" => "list"),
							array("get_field" => "new_list", "colname" => "list", "validtype" => "list")
						);
					break;

			}

			// check for pre-existing records in cases where duplicate entries are not allowed
			$duplicateExists = false;
			$dupeVocabParams = [];

			if (intval($_SESSION[EXPORT_SERVERNAME]["vocab_params"]["vocab"]) == 4) {
			    $dupeVocabParams[] = [
			        'key' => ':labId',
                    'value' => (int) filter_input(INPUT_POST, 'new_lab_id', FILTER_SANITIZE_NUMBER_INT)
                ];
			    $dupeVocabParams[] = [
			        'key' => ':childCode',
                    'value' => filter_input(INPUT_POST, 'new_child_code')
                ];

                if (VocabUtils::duplicateVocabExists($adminDbConn, (int)$_SESSION[EXPORT_SERVERNAME]["vocab_params"]["vocab"], ...$dupeVocabParams)) {
                    $duplicateExists = $duplicateExists || true;
                    DisplayUtils::drawError('Cannot add new Child SNOMED code:  Code already exists for this lab!');
                }
            }

			if (!$duplicateExists) {
				if (is_array($new_fields)) {
					$valid_add = TRUE;
					// verify all fields passed are valid types
					foreach ($new_fields as $new_field) {
						switch ($new_field['validtype']) {
							case "list-required":
								if (isset($_POST[$new_field['get_field']]) && is_numeric(trim($_POST[$new_field['get_field']])) && (intval(trim($_POST[$new_field['get_field']])) > 0)):
									$valid_add = $valid_add && TRUE;
								else:
									$valid_add = $valid_add && FALSE;
								endif;
								break;
							case "list":
								if (isset($_POST[$new_field['get_field']]) && is_numeric(trim($_POST[$new_field['get_field']]))):
									$valid_add = $valid_add && TRUE;
								else:
									$valid_add = $valid_add && FALSE;
								endif;
								break;
							case "list-nullable":
                                if (isset($_POST[$new_field['get_field']]) && empty(trim($_POST[$new_field['get_field']]))) {
                                    $valid_add = $valid_add && TRUE;
                                } elseif (isset($_POST[$new_field['get_field']]) && is_numeric(trim($_POST[$new_field['get_field']]))) {
									$valid_add = $valid_add && TRUE;
                                } else {
									$valid_add = $valid_add && FALSE;
                                }
								break;
							case "text":
								if (isset($_POST[$new_field['get_field']])):
									$valid_add = $valid_add && TRUE;
								else:
									$valid_add = $valid_add && FALSE;
								endif;
								break;
							case "text-required":
								if (isset($_POST[$new_field['get_field']]) && ctype_print(trim($_POST[$new_field['get_field']])) && (strlen(trim($_POST[$new_field['get_field']])) > 0)):
									$valid_add = $valid_add && TRUE;
								else:
									$valid_add = $valid_add && FALSE;
								endif;
								break;
							case "multi":
								$valid_add = $valid_add && TRUE;
								break;
							case "bool-nullable":
								if (isset($_POST[$new_field['get_field']]) && ctype_lower(trim($_POST[$new_field['get_field']])) && ((trim($_POST[$new_field['get_field']]) == "t") || (trim($_POST[$new_field['get_field']]) == "f") || (trim($_POST[$new_field['get_field']]) == "u"))):
									$valid_add = $valid_add && TRUE;
								else:
									$valid_add = $valid_add && FALSE;
								endif;
								break;
                            case "bool":
								if (isset($_POST[$new_field['get_field']]) && ctype_lower(trim($_POST[$new_field['get_field']])) && ((trim($_POST[$new_field['get_field']]) == "t") || (trim($_POST[$new_field['get_field']]) == "f"))):
									$valid_add = $valid_add && TRUE;
								else:
									$valid_add = $valid_add && FALSE;
								endif;
								break;
						}
					}
					if ($valid_add) {
						$add_sql = 'INSERT INTO '.$new_table.' (';
						foreach ($new_fields as $new_field) {
							$add_sql .= $new_field['colname'] . ', ';
						}
						$add_sql = substr($add_sql, 0, -2);
						$add_sql .= ') VALUES (';
						foreach ($new_fields as $new_fieldval) {
							switch ($new_fieldval['validtype']) {
								case "list":
								case "list-required":
									//$add_sql .= intval($_POST[$new_fieldval['get_field']]) . ', ';
                                    $add_sql .= filter_input(\INPUT_POST, $new_fieldval['get_field'], \FILTER_SANITIZE_NUMBER_INT) . ', ';
									break;
                                case "list-nullable":
                                    if ((!isset($_POST[$new_fieldval['get_field']])) || (is_null($_POST[$new_fieldval['get_field']])) || (empty(trim($_POST[$new_fieldval['get_field']])))) {
                                        $add_sql .= 'NULL, ';
                                    } else {
                                        //$add_sql .= intval($_POST[$new_fieldval['get_field']]) . ', ';
                                        $add_sql .= filter_input(\INPUT_POST, $new_fieldval['get_field'], \FILTER_SANITIZE_NUMBER_INT) . ', ';
                                    }
                                    break;
                                case "text":
								case "text-required":
									$add_sql .= ((strlen(trim($_POST[$new_fieldval['get_field']])) > 0) ? "'".pg_escape_string(trim($_POST[$new_fieldval['get_field']]))."'" : "NULL") . ', ';
									break;
								case "bool-nullable":
                                    if (isset($_POST[$new_fieldval['get_field']]) && (filter_input(\INPUT_POST, $new_fieldval['get_field'], \FILTER_SANITIZE_STRING) == 't')) {
                                        $add_sql .= "'t', ";
                                    } elseif (isset($_POST[$new_fieldval['get_field']]) && (filter_input(\INPUT_POST, $new_fieldval['get_field'], \FILTER_SANITIZE_STRING) == 'f')) {
                                        $add_sql .= "'f', ";
                                    } else {
                                        $add_sql .= "NULL, ";
                                    }
									break;
								case "bool":
									$add_sql .= ((trim($_POST[$new_fieldval['get_field']]) == "t") ? "'t'" : "'f'") . ', ';
									break;
								case "multi":
									if (isset($_POST[$new_fieldval['get_field']]) && is_array($_POST[$new_fieldval['get_field']]) && (count($_POST[$new_fieldval['get_field']]) > 0)) {
										$multi_string = implode(';', $_POST[$new_fieldval['get_field']]);
									} else {
                                        $multi_string = null;
                                    }
									$add_sql .= ((strlen(trim($multi_string)) > 0) ? "'".pg_escape_string(trim($multi_string))."'" : "NULL") . ', ';
									break;
							}
						}
						$add_sql = substr($add_sql, 0, -2);
						$add_sql .= ') RETURNING '.$va_id_col.';';

						$add_rs = @pg_query($host_pa, $add_sql);

						if ($add_rs !== false) {
							DisplayUtils::drawHighlight(sprintf("%s added successfully!", $_SESSION[EXPORT_SERVERNAME]["vocab_params"][$_SESSION[EXPORT_SERVERNAME]["vocab_params"]["vocab"]]["vocab_verbose"]), "ui-icon-check");

							$add_id = intval(@pg_fetch_result($add_rs, 0, 0));

							$va_new_vals = array();
							foreach ($new_fields as $va_new_field) {
								$va_new_vals[$va_new_field['colname']] = (isset($_POST[$va_new_field['get_field']])) ? $_POST[$va_new_field['get_field']] : null;
							}

							$va_prepared_new_vals = $va->prepareNewValues($va_table, $va_new_vals);

							$va->resetAudit();
							$va->setNewVals($va_prepared_new_vals);
							$va->auditVocab($add_id, $va_table, VocabAudit::ACTION_ADD);
						} else {
							DisplayUtils::drawError(sprintf("Could not add new %s.", $_SESSION[EXPORT_SERVERNAME]["vocab_params"][$_SESSION[EXPORT_SERVERNAME]["vocab_params"]["vocab"]]["vocab_verbose"]));
						}
					} else {
						DisplayUtils::drawError(sprintf("Could not add new %s:  Some values were missing/invalid", $_SESSION[EXPORT_SERVERNAME]["vocab_params"][$_SESSION[EXPORT_SERVERNAME]["vocab_params"]["vocab"]]["vocab_verbose"]));
					}
				}
			}

		}


		#################################################
		############### Result Pagination ###############
		#################################################
		// find out how many results there will be...
		$rowcount_sql = sprintf("SELECT count(%s) AS counter %s;", $_SESSION[EXPORT_SERVERNAME]["vocab_params"][$_SESSION[EXPORT_SERVERNAME]["vocab_params"]["vocab"]]["query_data"]["id_column"], $vocab_fromwhere);
		#debug echo $rowcount_sql."<br><br>";
		$counter=pg_fetch_array(pg_query($host_pa,$rowcount_sql)) or die("Can't Perform Query ".pg_last_error());
		$numrows=$counter['counter'];

		// number of rows to show per page
		$valid_rowsize = array(50, 100, 250, 500, 1000, -1);
		if(isset($_GET['user_rows'])){
			if (in_array(intval(trim($_GET['user_rows'])) , $valid_rowsize)) {
				$_SESSION[EXPORT_SERVERNAME]["vocab_params"][$_SESSION[EXPORT_SERVERNAME]["vocab_params"]["vocab"]]["page_rows"] = intval(trim($_GET['user_rows']));
			}
		}

		// find out total pages
		if ($_SESSION[EXPORT_SERVERNAME]["vocab_params"][$_SESSION[EXPORT_SERVERNAME]["vocab_params"]["vocab"]]["page_rows"] > 0) {
			$totalpages = ceil($numrows / $_SESSION[EXPORT_SERVERNAME]["vocab_params"][$_SESSION[EXPORT_SERVERNAME]["vocab_params"]["vocab"]]["page_rows"]);

			// get the current page or set a default
			if (isset($_GET['currentpage']) && is_numeric($_GET['currentpage'])) {
				$_SESSION[EXPORT_SERVERNAME]["vocab_params"][$_SESSION[EXPORT_SERVERNAME]["vocab_params"]["vocab"]]["current_page"] = intval($_GET['currentpage']);
			}

			if (isset($_SESSION[EXPORT_SERVERNAME]["vocab_params"][$_SESSION[EXPORT_SERVERNAME]["vocab_params"]["vocab"]]["current_page"])) {
				$currentpage = $_SESSION[EXPORT_SERVERNAME]["vocab_params"][$_SESSION[EXPORT_SERVERNAME]["vocab_params"]["vocab"]]["current_page"];
			} else {
				$currentpage = 1;
			}

			// if current page is greater than total pages...
			if ($currentpage > $totalpages) {
				// set current page to last page
				$currentpage = $totalpages;
			}

			// if current page is less than first page...
			if ($currentpage < 1) {
				// set current page to first page
				$currentpage = 1;
			}

			// the offset of the list, based on current page
			$offset = ($currentpage - 1) * $_SESSION[EXPORT_SERVERNAME]["vocab_params"][$_SESSION[EXPORT_SERVERNAME]["vocab_params"]["vocab"]]["page_rows"];
		} else {
            $offset = 0;
			$totalpages = 1;
			$currentpage = 1;
		}

?>

<form name="search_form" id="search_form" method="GET" action="<?php echo $webappBaseUrl; ?>">

<div class="emsa_search_controls ui-widget">
	<label for="q" class="emsa_form_heading" style="margin-right: 10px;">Search:</label><input type="text" name="q" id="q" class="vocab_query ui-corner-all">
	<button name="q_go" id="q_go">Search</button>
	<button type="button" name="clear_filters" id="clear_filters" title="Clear all filters/search terms">Reset</button>
	<button type="button" name="toggle_filters" id="toggle_filters" title="Show/hide filters">Hide Filters</button>
	<button id="addnew_button" type="button" title="Add a new '<?php echo $_SESSION[EXPORT_SERVERNAME]["vocab_params"][$_SESSION[EXPORT_SERVERNAME]["vocab_params"]["vocab"]]["vocab_verbose"]; ?>' record">Add new <?php echo $_SESSION[EXPORT_SERVERNAME]["vocab_params"][$_SESSION[EXPORT_SERVERNAME]["vocab_params"]["vocab"]]["vocab_verbose"]; ?></button>
</div>

<?php
	############### If filters applied, display which ones ###############
	if (isset($_SESSION[EXPORT_SERVERNAME]["vocab_params"][$_SESSION[EXPORT_SERVERNAME]["vocab_params"]["vocab"]]["filters"])) {
?>
<div class="vocab_search ui-widget ui-widget-content ui-state-highlight ui-corner-all" style="padding: 5px;">
	<span class="ui-icon ui-icon-elroptions" style="float: left; margin-right: .3em;"></span><p style="margin-left: 20px;">Active Filters:
<?php
		$active_filters = 0;
		foreach ($_SESSION[EXPORT_SERVERNAME]["vocab_params"][$_SESSION[EXPORT_SERVERNAME]["vocab_params"]["vocab"]]["filters"] as $sqlfiltercol => $sqlfiltervals) {
			if ($active_filters == 0) {
				echo "<strong>" . $_SESSION[EXPORT_SERVERNAME]["vocab_params"][$_SESSION[EXPORT_SERVERNAME]["vocab_params"]["vocab"]]["result_cols"][$sqlfiltercol]['label'] . "</strong>";
			} else {
				echo ", <strong>" . $_SESSION[EXPORT_SERVERNAME]["vocab_params"][$_SESSION[EXPORT_SERVERNAME]["vocab_params"]["vocab"]]["result_cols"][$sqlfiltercol]['label'] . "</strong>";			}
			$active_filters++;
		}
?>
	</p>
</div>
<?php
	}
?>

<div class="vocab_filter ui-widget ui-widget-content ui-corner-all">
	<div style="clear: both;"><span class="emsa_form_heading">Filters:</span></div>
<?php
	############### Draw filter form elements based on 'result_cols' array ###############
	foreach ($_SESSION[EXPORT_SERVERNAME]["vocab_params"][$_SESSION[EXPORT_SERVERNAME]["vocab_params"]["vocab"]]["result_cols"] as $filtercol => $filtercolname) {
		if ($filtercolname['filter']) {
		    if (isset($filtercolname['filterlookup']) && $filtercolname['filterlookup']) {
		        $filterQuery = $filtercolname['lookupqry'];
            } else {
                $filterQuery = sprintf("SELECT DISTINCT %s AS value, %s AS label FROM %s ORDER BY 1 ASC;", $filtercol, $filtercol, $_SESSION[EXPORT_SERVERNAME]["vocab_params"][$_SESSION[EXPORT_SERVERNAME]["vocab_params"]["vocab"]]["query_data"]["table_name"]);
            }

		    (new AccessibleMultiselectListbox(FilterFactory::getListFromQuery($adminDbConn, $filterQuery), $_SESSION[EXPORT_SERVERNAME]["vocab_params"][$_SESSION[EXPORT_SERVERNAME]["vocab_params"]["vocab"]]['filters'][$filtercol] ?? null))
                ->render($filtercolname['label'], 'f[' . $filtercol . ']', false, false, $filtercol, $bool_cols, $filtercolname['filterlookup'] ?? null);
		}
	}
?>
	<br><br><button name="apply_filters" id="apply_filters" style="clear: both; float: left; margin: 5px;">Apply Filters</button>
</div>

<input type="hidden" name="selected_page" value="<?php echo $navSelectedPage; ?>">
<input type="hidden" name="submenu" value="<?php echo $navSubmenu; ?>">
<input type="hidden" name="cat" value="<?php echo (($navCat < 2) ? 1 : $navCat); ?>">
<input type="hidden" name="subcat" value="<?php echo (($navSubcat < 2) ? 1 : $navSubcat); ?>">

</form>

<div id="addnew_form" class="addnew_lab ui-widget ui-widget-content ui-corner-all">
	<div style="clear: both; margin-bottom: 10px;">
		<span class="emsa_form_heading">Add New <?php echo $_SESSION[EXPORT_SERVERNAME]["vocab_params"][$_SESSION[EXPORT_SERVERNAME]["vocab_params"]["vocab"]]["vocab_verbose"]; ?>:</span>
	</div>
	<form id="new_vocab_form" method="POST" action="<?php echo $webappBaseUrl; ?>?selected_page=<?php echo intval($navSelectedPage); ?>&submenu=<?php echo intval($navSubmenu); ?>&cat=<?php echo intval($navCat); ?>&subcat=<?php echo intval($navSubcat); ?>">
	<?php
		if (intval($_SESSION[EXPORT_SERVERNAME]["vocab_params"]["vocab"]) == 5) {
			// Child LOINC
			#lab_id lookup
			echo "<div class=\"addnew_field\"><label class=\"vocab_add_form required\" for=\"new_lab_id\">Child Lab</label><br><select class=\"ui-corner-all required\" name=\"new_lab_id\" id=\"new_lab_id\">\n";
			echo "<option value=\"-1\" selected>--</option>\n";
				$lookup_qry = $_SESSION[EXPORT_SERVERNAME]["vocab_params"][$_SESSION[EXPORT_SERVERNAME]["vocab_params"]["vocab"]]["result_cols"]["lab"]["lookupqry"];
				$lookup_rs = @pg_query($host_pa, $lookup_qry) or DisplayUtils::drawError("Unable to retrieve list of conditions.");
				while ($lookup_row = @pg_fetch_object($lookup_rs)) {
					echo sprintf("<option value=\"%d\">%s</option>\n", $lookup_row->value, htmlentities($lookup_row->label, ENT_QUOTES, "UTF-8"));
				}
				pg_free_result($lookup_rs);
			echo "</select></div>";
			#child_loinc text
			echo "<div class=\"addnew_field\"><label class=\"vocab_add_form required\" for=\"new_child_loinc\">Child Test Concept Code</label><br><input class=\"ui-corner-all required\" type=\"text\" name=\"new_child_loinc\" id=\"new_child_loinc\" /></div>";
			#archived y/n
			echo "<div class=\"addnew_field\"><label class=\"vocab_add_form\" for=\"new_archived\">Archived?</label><br><select class=\"ui-corner-all\" name=\"new_archived\" id=\"new_archived\">\n";
			echo "<option value=\"-1\">--</option>\n";
			echo "<option value=\"t\">Yes</option>\n";
			echo "<option value=\"f\" selected>No</option>\n";
			echo "</select></div>";
			#master_loinc lookup (incl master concept name)
			echo "<div class=\"addnew_field\"><label class=\"vocab_add_form required\" for=\"new_master_loinc\">Master Test Concept Code (LOINC)</label><br><select style=\"width: 100% !important; box-sizing: border-box !important;\" class=\"ui-corner-all required\" name=\"new_master_loinc\" id=\"new_master_loinc\">\n";
			echo "<option value=\"-1\" selected>--</option>\n";
				$lookup_qry = sprintf("SELECT vml.l_id AS value, vml.loinc AS label, vml.concept_name AS concept FROM %svocab_master_loinc vml ORDER BY vml.loinc;", $emsaDbSchemaPrefix);
				$lookup_rs = @pg_query($host_pa, $lookup_qry) or DisplayUtils::drawError("Unable to retrieve list of conditions.");
				while ($lookup_row = @pg_fetch_object($lookup_rs)) {
					echo sprintf("<option value=\"%d\">%s - (%s)</option>\n", $lookup_row->value, htmlentities($lookup_row->label, ENT_QUOTES, "UTF-8"), htmlentities($lookup_row->concept, ENT_QUOTES, "UTF-8"));
				}
				pg_free_result($lookup_rs);
			echo "</select></div>";

			echo "<div class=\"add-form-divider\"></div>";
			#workflow y/n
			echo "<div class=\"addnew_field\"><label class=\"vocab_add_form\" for=\"new_workflow\">Message Workflow</label><br><select class=\"ui-corner-all\" name=\"new_workflow\" id=\"new_workflow\">\n";
			echo "<option value=\"".ENTRY_STATUS."\">Automated Processing</option>\n";
			echo "<option value=\"".SEMI_AUTO_STATUS."\">Semi-Automated Entry</option>\n";
			echo "<option value=\"".QA_STATUS."\" selected>QA Review</option>\n";
			echo "</select></div>";

			echo "<div class=\"add-form-divider\"></div>";
			#interpret_results y/n
			echo "<div class=\"addnew_field\"><label class=\"vocab_add_form\" for=\"new_interpret_results\">Quantitative Result?</label><br><select class=\"ui-corner-all\" name=\"new_interpret_results\" id=\"new_interpret_results\">\n";
			echo "<option value=\"-1\">--</option>\n";
			echo "<option value=\"t\" selected>Yes</option>\n";
			echo "<option value=\"f\">No</option>\n";
			echo "</select></div>";
			#interpret_override y/n/u
			echo "<div class=\"addnew_field\"><label class=\"vocab_add_form\" for=\"new_interpret_override\">Result Interpretation</label><br><select class=\"ui-corner-all\" name=\"new_interpret_override\" id=\"new_interpret_override\">\n";
			echo "<option value=\"u\" selected>Set by OBX-2</option>\n";
			echo "<option value=\"t\">Override Quantitative</option>\n";
			echo "<option value=\"f\">Override Coded Entry</option>\n";
            echo "</select></div>";
			#allow_preprocessing y/n
			echo "<div class=\"addnew_field\"><label class=\"vocab_add_form\" for=\"new_allow_preprocessing\">Preprocessor Concatenation?</label><br><select class=\"ui-corner-all\" name=\"new_allow_preprocessing\" id=\"new_allow_preprocessing\">\n";
			echo "<option value=\"-1\">--</option>\n";
			echo "<option value=\"t\">Yes</option>\n";
			echo "<option value=\"f\" selected>No</option>\n";
			echo "</select></div>";
			#result location lookup
			echo "<div class=\"addnew_field\"><label class=\"vocab_add_form\" for=\"new_location\">Result Location</label><br><select class=\"ui-corner-all\" name=\"new_location\" id=\"new_location\">\n";
			echo "<option value=\"-1\" selected>--</option>\n";
				$lookup_qry = $_SESSION[EXPORT_SERVERNAME]["vocab_params"][$_SESSION[EXPORT_SERVERNAME]["vocab_params"]["vocab"]]["result_cols"]["result_location"]["lookupqry"];
				$lookup_rs = @pg_query($host_pa, $lookup_qry) or DisplayUtils::drawError("Unable to retrieve list of Result Locations.");
				while ($lookup_row = @pg_fetch_object($lookup_rs)) {
					echo sprintf("<option value=\"%d\">%s</option>\n", $lookup_row->value, htmlentities($lookup_row->label, ENT_QUOTES, "UTF-8"));
				}
				pg_free_result($lookup_rs);
			echo "</select></div>";
			#units text
			echo "<div class=\"addnew_field\"><label class=\"vocab_add_form\" for=\"new_units\">Units</label><br><input class=\"ui-corner-all\" type=\"text\" name=\"new_units\" id=\"new_units\" /></div>";
			#refrange text
			echo "<div class=\"addnew_field\"><label class=\"vocab_add_form\" for=\"new_refrange\">Reference Range</label><br><input class=\"ui-corner-all\" type=\"text\" name=\"new_refrange\" id=\"new_refrange\" /></div>";
			#hl7_refrange text
			echo "<div class=\"addnew_field\"><label class=\"vocab_add_form\" for=\"new_hl7_refrange\">HL7 Reference Range</label><br><input class=\"ui-corner-all\" type=\"text\" name=\"new_hl7_refrange\" id=\"new_hl7_refrange\" /></div>";
			#offscale_low_result lookup
			echo "<div class=\"addnew_field\"><label class=\"vocab_add_form required\" for=\"new_offscale_low_result\">Off-scale Low Test Result</label><br><select class=\"ui-corner-all required\" name=\"new_offscale_low_result\" id=\"new_offscale_low_result\">\n";
			echo "<option value=\"-1\" selected>--</option>\n";
                $lookup_qry = $_SESSION[EXPORT_SERVERNAME]["vocab_params"][$_SESSION[EXPORT_SERVERNAME]["vocab_params"]["vocab"]]["result_cols"]["offscale_low_result"]["lookupqry"];
                $lookup_rs = @pg_query($host_pa, $lookup_qry) or DisplayUtils::drawError("Unable to retrieve list of test results.");
                while ($lookup_row = @pg_fetch_object($lookup_rs)) {
                    echo sprintf("<option value=\"%d\">%s</option>\n", $lookup_row->value, htmlentities($lookup_row->label, ENT_QUOTES, "UTF-8"));
                }
                pg_free_result($lookup_rs);
			echo "</select></div>";
            #offscale_high_result lookup
			echo "<div class=\"addnew_field\"><label class=\"vocab_add_form required\" for=\"new_offscale_high_result\">Off-scale High Test Result</label><br><select class=\"ui-corner-all required\" name=\"new_offscale_high_result\" id=\"new_offscale_high_result\">\n";
			echo "<option value=\"-1\" selected>--</option>\n";
                $lookup_qry = $_SESSION[EXPORT_SERVERNAME]["vocab_params"][$_SESSION[EXPORT_SERVERNAME]["vocab_params"]["vocab"]]["result_cols"]["offscale_high_result"]["lookupqry"];
                $lookup_rs = @pg_query($host_pa, $lookup_qry) or DisplayUtils::drawError("Unable to retrieve list of test results.");
                while ($lookup_row = @pg_fetch_object($lookup_rs)) {
                    echo sprintf("<option value=\"%d\">%s</option>\n", $lookup_row->value, htmlentities($lookup_row->label, ENT_QUOTES, "UTF-8"));
                }
                pg_free_result($lookup_rs);
			echo "</select></div>";
            #pregnancy y/n
			echo "<div class=\"addnew_field\"><label class=\"vocab_add_form\" for=\"new_pregnancy\">Indicates Pregnancy?</label><br><select class=\"ui-corner-all\" name=\"new_pregnancy\" id=\"new_pregnancy\">\n";
			echo "<option value=\"t\">Yes</option>\n";
			echo "<option value=\"f\" selected>No</option>\n";
			echo "</select></div>";

			echo "<div class=\"add-form-divider\"></div>";
			#--extended--
			echo "<div style=\"clear: both; margin-bottom: 10px;\"><span class=\"emsa_form_heading\">Extended Child LOINC Fields</span></div>";
			#child_orderable_test_code text
			echo "<div class=\"addnew_field\"><label class=\"vocab_add_form\" for=\"new_child_orderable_test_code\">Child Orderable Test Code</label><br><input class=\"ui-corner-all\" type=\"text\" name=\"new_child_orderable_test_code\" id=\"new_child_orderable_test_code\" /></div>";
			#child_resultable_test_code text
			echo "<div class=\"addnew_field\"><label class=\"vocab_add_form\" for=\"new_child_resultable_test_code\">Child Resultable Test Code</label><br><input class=\"ui-corner-all\" type=\"text\" name=\"new_child_resultable_test_code\" id=\"new_child_resultable_test_code\" /></div>";
			#child_concept_name text
			echo "<div class=\"addnew_field\"><label class=\"vocab_add_form\" for=\"new_child_concept_name\">Child Concept Name</label><br><input class=\"ui-corner-all\" type=\"text\" name=\"new_child_concept_name\" id=\"new_child_concept_name\" /></div>";
			#child_alias text
			echo "<div class=\"addnew_field\"><label class=\"vocab_add_form\" for=\"new_child_alias\">Alias</label><br><input class=\"ui-corner-all\" type=\"text\" name=\"new_child_alias\" id=\"new_child_alias\" /></div>";

		} elseif (intval($_SESSION[EXPORT_SERVERNAME]["vocab_params"]["vocab"]) == 4) {
			// Child Organism
			#lab_id lookup
			echo "<div class=\"addnew_field\"><label class=\"vocab_add_form required\" for=\"new_lab_id\">Child Lab</label><br><select class=\"ui-corner-all required\" name=\"new_lab_id\" id=\"new_lab_id\">\n";
			echo "<option value=\"-1\" selected>--</option>\n";
				$lookup_qry = $_SESSION[EXPORT_SERVERNAME]["vocab_params"][$_SESSION[EXPORT_SERVERNAME]["vocab_params"]["vocab"]]["result_cols"]["lab"]["lookupqry"];
				$lookup_rs = @pg_query($host_pa, $lookup_qry) or DisplayUtils::drawError("Unable to retrieve list of conditions.");
				while ($lookup_row = @pg_fetch_object($lookup_rs)) {
					echo sprintf("<option value=\"%d\">%s</option>\n", $lookup_row->value, htmlentities($lookup_row->label, ENT_QUOTES, "UTF-8"));
				}
				pg_free_result($lookup_rs);
			echo "</select></div>";
			#child_code text
			echo "<div class=\"addnew_field\"><label class=\"vocab_add_form required\" for=\"new_child_code\">Child Code</label><br><input class=\"ui-corner-all required\" type=\"text\" name=\"new_child_code\" id=\"new_child_code\" /></div>";

			echo "<div class=\"add-form-divider\"></div>";
			#organism lookup (incl master snomed)
			echo "<div class=\"addnew_field\"><label class=\"vocab_add_form\" for=\"new_organism\">Master Organism SNOMED</label><br><select class=\"ui-corner-all\" name=\"new_organism\" id=\"new_organism\">\n";
			echo "<option value=\"-1\" selected>--</option>\n";
				$lookup_qry = $_SESSION[EXPORT_SERVERNAME]["vocab_params"][$_SESSION[EXPORT_SERVERNAME]["vocab_params"]["vocab"]]["result_cols"]["organism"]["lookupqry"];
				$lookup_rs = @pg_query($host_pa, $lookup_qry) or DisplayUtils::drawError("Unable to retrieve list of organisms.");
				while ($lookup_row = @pg_fetch_object($lookup_rs)) {
					echo sprintf("<option value=\"%d\">%s [%s]</option>\n", $lookup_row->value, htmlentities($lookup_row->label, ENT_QUOTES, "UTF-8"), htmlentities($lookup_row->snomed, ENT_QUOTES, "UTF-8"));
				}
				pg_free_result($lookup_rs);
			echo "</select></div>";
			echo "<div class=\"add-form-divider\"></div>";

			#test result lookup (incl master snomed)
			echo "<div class=\"addnew_field\"><label class=\"vocab_add_form\" for=\"new_test_result_id\">Master Test Result SNOMED</label><br><select class=\"ui-corner-all\" name=\"new_test_result_id\" id=\"new_test_result_id\">\n";
			echo "<option value=\"-1\" selected>--</option>\n";
				$lookup_qry = $_SESSION[EXPORT_SERVERNAME]["vocab_params"][$_SESSION[EXPORT_SERVERNAME]["vocab_params"]["vocab"]]["result_cols"]["test_result"]["lookupqry"];
				$lookup_rs = @pg_query($host_pa, $lookup_qry) or DisplayUtils::drawError("Unable to retrieve list of organisms.");
				while ($lookup_row = @pg_fetch_object($lookup_rs)) {
					echo sprintf("<option value=\"%d\">%s [%s]</option>\n", $lookup_row->value, htmlentities($lookup_row->label, ENT_QUOTES, "UTF-8"), htmlentities($lookup_row->snomed, ENT_QUOTES, "UTF-8"));
				}
				pg_free_result($lookup_rs);
			echo "</select></div>";
			echo "<div class=\"add-form-divider\"></div>";

			#result_value text
			echo "<div class=\"addnew_field\"><label class=\"vocab_add_form\" for=\"new_result_value\">Result Value</label><br><input class=\"ui-corner-all\" type=\"text\" name=\"new_result_value\" id=\"new_result_value\" /></div>";
			#comment text
			echo "<div class=\"addnew_field\"><label class=\"vocab_add_form\" for=\"new_comment\">Comments</label><br><input class=\"ui-corner-all\" type=\"text\" name=\"new_comment\" id=\"new_comment\" /></div>";

		} elseif (intval($_SESSION[EXPORT_SERVERNAME]["vocab_params"]["vocab"]) == 3) {
			// Master Organism
			#snomed_category lookup
			echo "<div class=\"addnew_field\"><label class=\"vocab_add_form required\" for=\"new_snomed_category\">SNOMED Type</label><br><select class=\"ui-corner-all required\" name=\"new_snomed_category\" id=\"new_snomed_category\">\n";
			echo "<option value=\"-1\" selected>--</option>\n";
				$lookup_qry = sprintf("SELECT id AS value, concept AS label FROM %svocab_master_vocab WHERE category = elr.vocab_category_id('snomed_category') ORDER BY concept;", $emsaDbSchemaPrefix, $emsaDbSchemaPrefix);
				$lookup_rs = @pg_query($host_pa, $lookup_qry) or DisplayUtils::drawError("Unable to retrieve list of SNOMED categories.");
				while ($lookup_row = @pg_fetch_object($lookup_rs)) {
					echo sprintf("<option value=\"%d\">%s</option>\n", $lookup_row->value, htmlentities($lookup_row->label, ENT_QUOTES, "UTF-8"));
				}
				pg_free_result($lookup_rs);
			echo "</select></div>";
			#snomed text
			echo "<div class=\"addnew_field\"><label class=\"vocab_add_form\" for=\"new_snomed\">SNOMED Code</label><br><input class=\"ui-corner-all\" type=\"text\" name=\"new_snomed\" id=\"new_snomed\" /></div>";
			#snomed_alt text
			echo "<div class=\"addnew_field\"><label class=\"vocab_add_form\" for=\"new_snomed_alt\">Secondary SNOMED Code</label><br><input class=\"ui-corner-all\" type=\"text\" name=\"new_snomed_alt\" id=\"new_snomed_alt\" /></div>";
			echo "<div class=\"add-form-divider\"></div>";

			#condition lookup
			echo "<div class=\"addnew_field\"><label class=\"vocab_add_form\" for=\"new_condition\">Master Condition</label><br><select class=\"ui-corner-all\" name=\"new_condition\" id=\"new_condition\">\n";
			echo "<option value=\"-1\" selected>--</option>\n";
				$lookup_qry = $_SESSION[EXPORT_SERVERNAME]["vocab_params"][$_SESSION[EXPORT_SERVERNAME]["vocab_params"]["vocab"]]["result_cols"]["condition"]["lookupqry"];
				$lookup_rs = @pg_query($host_pa, $lookup_qry) or DisplayUtils::drawError("Unable to retrieve list of conditions.");
				while ($lookup_row = @pg_fetch_object($lookup_rs)) {
					echo sprintf("<option value=\"%d\">%s</option>\n", $lookup_row->value, htmlentities($lookup_row->label, ENT_QUOTES, "UTF-8"));
				}
				pg_free_result($lookup_rs);
			echo "</select></div>";
			#organism lookup
			echo "<div class=\"addnew_field\"><label class=\"vocab_add_form\" for=\"new_organism\">Type Concept Name</label><br><select class=\"ui-corner-all\" name=\"new_organism\" id=\"new_organism\">\n";
			echo "<option value=\"-1\" selected>--</option>\n";
				$lookup_qry = sprintf("SELECT id AS value, concept AS label FROM %svocab_master_vocab WHERE category = elr.vocab_category_id('organism') ORDER BY concept;", $emsaDbSchemaPrefix, $emsaDbSchemaPrefix);
				$lookup_rs = @pg_query($host_pa, $lookup_qry) or DisplayUtils::drawError("Unable to retrieve list of organisms.");
				while ($lookup_row = @pg_fetch_object($lookup_rs)) {
					echo sprintf("<option value=\"%d\">%s</option>\n", $lookup_row->value, htmlentities($lookup_row->label, ENT_QUOTES, "UTF-8"));
				}
				pg_free_result($lookup_rs);
			echo "</select></div>";
			echo "<div class=\"add-form-divider\"></div>";

			#list lookup
			echo "<div class=\"addnew_field\"><label class=\"vocab_add_form\" for=\"new_list\">List</label><br><select class=\"ui-corner-all\" name=\"new_list\" id=\"new_list\">\n";
			echo "<option value=\"-1\" selected>--</option>\n";
				$lookup_qry = $_SESSION[EXPORT_SERVERNAME]["vocab_params"][$_SESSION[EXPORT_SERVERNAME]["vocab_params"]["vocab"]]["result_cols"]["list"]["lookupqry"];
				$lookup_rs = @pg_query($host_pa, $lookup_qry) or DisplayUtils::drawError("Unable to retrieve list of lists.");
				while ($lookup_row = @pg_fetch_object($lookup_rs)) {
					echo sprintf("<option value=\"%d\">%s</option>\n", $lookup_row->value, htmlentities($lookup_row->label, ENT_QUOTES, "UTF-8"));
				}
				pg_free_result($lookup_rs);
			echo "</select></div>";
			#test_result lookup
			echo "<div class=\"addnew_field\"><label class=\"vocab_add_form\" for=\"new_test_result\">Test Result</label><br><select class=\"ui-corner-all\" name=\"new_test_result\" id=\"new_test_result\">\n";
			echo "<option value=\"-1\" selected>--</option>\n";
				$lookup_qry = $_SESSION[EXPORT_SERVERNAME]["vocab_params"][$_SESSION[EXPORT_SERVERNAME]["vocab_params"]["vocab"]]["result_cols"]["test_result"]["lookupqry"];
				$lookup_rs = @pg_query($host_pa, $lookup_qry) or DisplayUtils::drawError("Unable to retrieve list of test results.");
				while ($lookup_row = @pg_fetch_object($lookup_rs)) {
					echo sprintf("<option value=\"%d\">%s</option>\n", $lookup_row->value, htmlentities($lookup_row->label, ENT_QUOTES, "UTF-8"));
				}
				pg_free_result($lookup_rs);
			echo "</select></div>";
            #semi_auto_usage y/n/u
			echo "<div class=\"addnew_field\"><label class=\"vocab_add_form\" for=\"new_semi_auto_usage\">Semi-Auto Usage</label><br><select class=\"ui-corner-all\" name=\"new_semi_auto_usage\" id=\"new_semi_auto_usage\">\n";
			echo "<option value=\"u\" selected>Allow Semi-Auto</option>\n";
			echo "<option value=\"t\">Force Semi-Auto</option>\n";
			echo "<option value=\"f\">Skip Semi-Auto</option>\n";
            echo "</select></div>";

		} elseif (intval($_SESSION[EXPORT_SERVERNAME]["vocab_params"]["vocab"]) == 2) {
			// Master Condition
			#disease_category lookup
            echo "<div class=\"addnew_field\"><label class=\"vocab_add_form required\" for=\"new_disease_category\">CDC Category</label><br><select class=\"ui-corner-all required\" name=\"new_disease_category\" id=\"new_disease_category\">\n";
			echo "<option value=\"-1\" selected>--</option>\n";
				$lookup_qry = sprintf("SELECT id AS value, concept AS label FROM %svocab_master_vocab WHERE category = elr.vocab_category_id('disease_category') ORDER BY concept;", $emsaDbSchemaPrefix, $emsaDbSchemaPrefix);
				$lookup_rs = @pg_query($host_pa, $lookup_qry) or DisplayUtils::drawError("Unable to retrieve list of disease categories.");
				while ($lookup_row = @pg_fetch_object($lookup_rs)) {
					echo sprintf("<option value=\"%d\">%s</option>\n", $lookup_row->value, htmlentities($lookup_row->label, ENT_QUOTES, "UTF-8"));
				}
				pg_free_result($lookup_rs);
			echo "</select></div>";
			#condition type initial/final
			echo "<div class=\"addnew_field\"><label class=\"vocab_add_form required\" for=\"new_is_initial\">Condition Type</label><br><select class=\"ui-corner-all required\" name=\"new_is_initial\" id=\"new_is_initial\">\n";
			echo "<option value=\"t\" selected>Initial</option>\n";
			echo "<option value=\"f\">Final</option>\n";
			echo "</select></div>";
			#condition lookup
			echo "<div class=\"addnew_field\"><label class=\"vocab_add_form required\" for=\"new_condition\">Condition</label><br><select class=\"ui-corner-all required\" name=\"new_condition\" id=\"new_condition\">\n";
			echo "<option value=\"-1\" selected>--</option>\n";
				$lookup_qry = sprintf("SELECT id AS value, concept AS label FROM %svocab_master_vocab WHERE category = elr.vocab_category_id('condition') ORDER BY concept;", $emsaDbSchemaPrefix, $emsaDbSchemaPrefix);
				$lookup_rs = @pg_query($host_pa, $lookup_qry) or DisplayUtils::drawError("Unable to retrieve list of conditions.");
				while ($lookup_row = @pg_fetch_object($lookup_rs)) {
					echo sprintf("<option value=\"%d\">%s</option>\n", $lookup_row->value, htmlentities($lookup_row->label, ENT_QUOTES, "UTF-8"));
				}
				pg_free_result($lookup_rs);
			echo "</select></div>";

			echo "<div class=\"add-form-divider\"></div>";
			#valid_specimen multi
			echo "<div class=\"addnew_field\">";
            (new AccessibleMultiselectListbox(FilterFactory::getListFromQuery($adminDbConn, "SELECT id AS value, concept AS label FROM vocab_master_vocab WHERE category = vocab_category_id('specimen') ORDER BY concept;")))
                ->render('Valid Specimen Sources', 'new_valid_specimen', true);
			echo "</div>";
			#invalid_specimen multi
			echo "<div class=\"addnew_field\">";
			(new AccessibleMultiselectListbox(FilterFactory::getListFromQuery($adminDbConn, "SELECT id AS value, concept AS label FROM vocab_master_vocab WHERE category = vocab_category_id('specimen') ORDER BY concept;")))
                ->render('Invalid Specimen Sources', 'new_invalid_specimen', true);
			echo "</div>";

			#ignore_age_rule text
			echo "<div class=\"addnew_field\" style=\"vertical-align: top;\"><label class=\"vocab_add_form\" for=\"new_ignore_age_rule\">Ignore Older Than</label><br><textarea class=\"ui-corner-all\" name=\"new_ignore_age_rule\" id=\"new_ignore_age_rule\"></textarea></div>";
			#white_rule text
			echo "<div class=\"addnew_field\" style=\"vertical-align: top;\"><label class=\"vocab_add_form\" for=\"new_white_rule\">Morbidity Whitelist Rules</label><br><textarea class=\"ui-corner-all\" name=\"new_white_rule\" id=\"new_white_rule\"></textarea></div>";
			#contact_white_rule text
			echo "<div class=\"addnew_field\" style=\"vertical-align: top;\"><label class=\"vocab_add_form\" for=\"new_contact_white_rule\">Contact Whitelist Rules</label><br><textarea class=\"ui-corner-all\" name=\"new_contact_white_rule\" id=\"new_contact_white_rule\"></textarea></div>";
			#gateway_xref text
			echo "<div class=\"addnew_field\">";
			$gateway_xref_qry = 'SELECT mc.c_id AS value, mv.concept AS label
				FROM vocab_master_condition mc
				INNER JOIN vocab_master_vocab mv ON (mc.condition = mv.id)
				ORDER BY mv.concept;';
			(new AccessibleMultiselectListbox(FilterFactory::getListFromQuery($adminDbConn, $gateway_xref_qry)))
                ->render('Whitelist Crossrefs', 'new_gateways');
			echo "</div>";
            #o2m_addcmr_exclusions text
            echo "<div class=\"addnew_field\">";
            $o2m_addcmr_exclusions_qry = 'SELECT mc.c_id AS value, mv.concept AS label
				FROM vocab_master_condition mc
				INNER JOIN vocab_master_vocab mv ON (mc.condition = mv.id)
				ORDER BY mv.concept;';
            (new AccessibleMultiselectListbox(FilterFactory::getListFromQuery($adminDbConn, $o2m_addcmr_exclusions_qry)))
                ->render('(One-to-Many) Add CMR If Not Found', 'new_o2m_addcmr_exclusions');
            echo "</div>";

			echo "<div class=\"add-form-divider\"></div>";
			#check_xref_first y/n
			echo "<div class=\"addnew_field\"><label class=\"vocab_add_form\" for=\"new_check_xref_first\">Check Crossrefs First?</label><br><select class=\"ui-corner-all\" name=\"new_check_xref_first\" id=\"new_check_xref_first\">\n";
			echo "<option value=\"t\">Yes</option>\n";
			echo "<option value=\"f\" selected>No</option>\n";
			echo "</select></div>";
			#whitelist_ignore_case_status y/n
			echo "<div class=\"addnew_field\"><label class=\"vocab_add_form\" for=\"new_whitelist_ignore_case_status\">Whitelist Rules Ignore State Case Status?</label><br><select class=\"ui-corner-all\" name=\"new_whitelist_ignore_case_status\" id=\"new_whitelist_ignore_case_status\">\n";
			echo "<option value=\"t\">Yes</option>\n";
			echo "<option value=\"f\" selected>No</option>\n";
			echo "</select></div>";
			#whitelist_override y/n
			echo "<div class=\"addnew_field\"><label class=\"vocab_add_form\" for=\"new_whitelist_override\">Override Target Whitelist Rules?</label><br><select class=\"ui-corner-all\" name=\"new_whitelist_override\" id=\"new_whitelist_override\">\n";
			echo "<option value=\"t\">Yes</option>\n";
			echo "<option value=\"f\" selected>No</option>\n";
			echo "</select></div>";
			#allow_multi_assign y/n
			echo "<div class=\"addnew_field\"><label class=\"vocab_add_form\" for=\"new_allow_multi_assign\">Allow One-to-Many?</label><br><select class=\"ui-corner-all\" name=\"new_allow_multi_assign\" id=\"new_allow_multi_assign\">\n";
			echo "<option value=\"t\">Yes</option>\n";
			echo "<option value=\"f\" selected>No</option>\n";
			echo "</select></div>";
            #ast_multi_colony y/n
            echo "<div class=\"addnew_field\"><label class=\"vocab_add_form\" for=\"new_ast_multi_colony\">Allow Multi-Colony AST?</label><br><select class=\"ui-corner-all\" name=\"new_ast_multi_colony\" id=\"new_ast_multi_colony\">\n";
            echo "<option value=\"t\">Yes</option>\n";
            echo "<option value=\"f\" selected>No</option>\n";
            echo "</select></div>";
            #bypass_oos y/n
            echo "<div class=\"addnew_field\"><label class=\"vocab_add_form\" for=\"new_bypass_oos\">Bypass OOS Queue?</label><br><select class=\"ui-corner-all\" name=\"new_bypass_oos\" id=\"new_bypass_oos\">\n";
            echo "<option value=\"t\">Yes</option>\n";
            echo "<option value=\"f\" selected>No</option>\n";
            echo "</select></div>";
            #blacklist_preliminary y/n
            echo "<div class=\"addnew_field\"><label class=\"vocab_add_form\" for=\"new_blacklist_preliminary\">Blacklist Preliminary Results?</label><br><select class=\"ui-corner-all\" name=\"new_blacklist_preliminary\" id=\"new_blacklist_preliminary\">\n";
            echo "<option value=\"t\">Yes</option>\n";
            echo "<option value=\"f\" selected>No</option>\n";
            echo "</select></div>";
            #immediate_notify y/n
			echo "<div class=\"addnew_field\"><label class=\"vocab_add_form required\" for=\"new_immediate_notify\">Immediately Notifiable?</label><br><select class=\"ui-corner-all required\" name=\"new_immediate_notify\" id=\"new_immediate_notify\">\n";
			echo "<option value=\"-1\" selected>--</option>\n";
			echo "<option value=\"t\">Yes</option>\n";
			echo "<option value=\"f\">No</option>\n";
			echo "</select></div>";
			#require_specimen y/n
			echo "<div class=\"addnew_field\"><label class=\"vocab_add_form required\" for=\"new_require_specimen\">Require Specimen Source<br>From Nominal Culture?</label><br><select class=\"ui-corner-all required\" name=\"new_require_specimen\" id=\"new_require_specimen\">\n";
			echo "<option value=\"-1\" selected>--</option>\n";
			echo "<option value=\"t\">Yes</option>\n";
			echo "<option value=\"f\">No</option>\n";
			echo "</select></div>";
			#notify_state y/n
			echo "<div class=\"addnew_field\"><label class=\"vocab_add_form required\" for=\"new_notify_state\">Notify State Upon Receipt?</label><br><select class=\"ui-corner-all required\" name=\"new_notify_state\" id=\"new_notify_state\">\n";
			echo "<option value=\"-1\" selected>--</option>\n";
			echo "<option value=\"t\">Yes</option>\n";
			echo "<option value=\"f\">No</option>\n";
			echo "</select></div>";
			#district_override lookup
			echo "<div class=\"addnew_field\"><label class=\"vocab_add_form\" for=\"new_district_override\">Jurisdiction Override</label><br><select class=\"ui-corner-all\" name=\"new_district_override\" id=\"new_district_override\">\n";
			echo "<option value=\"-1\" selected>--</option>\n";
				$lookup_qry = $_SESSION[EXPORT_SERVERNAME]["vocab_params"][$_SESSION[EXPORT_SERVERNAME]["vocab_params"]["vocab"]]["result_cols"]["district_override"]["lookupqry"];
				$lookup_rs = @pg_query($host_pa, $lookup_qry) or DisplayUtils::drawError("Unable to retrieve list of jurisdictions.");
				while ($lookup_row = @pg_fetch_object($lookup_rs)) {
					echo sprintf("<option value=\"%d\">%s</option>\n", $lookup_row->value, htmlentities($lookup_row->label, ENT_QUOTES, "UTF-8"));
				}
				pg_free_result($lookup_rs);
			echo "</select></div>";

        } elseif (intval($_SESSION[EXPORT_SERVERNAME]["vocab_params"]["vocab"]) == 14) {
            // ICD Codes
			#codeset lookup
			echo "<div class=\"addnew_field\" style=\"vertical-align: top;\"><label class=\"vocab_add_form required\" for=\"new_codeset\">Coding System</label><br><select class=\"ui-corner-all required\" name=\"new_codeset\" id=\"new_codeset\">\n";
			echo "<option value=\"-1\" selected>--</option>\n";
				$lookup_qry = sprintf("SELECT id AS value, codeset_name AS label FROM %svocab_codeset ORDER BY codeset_name;", $emsaDbSchemaPrefix);
				$lookup_rs = @pg_query($host_pa, $lookup_qry) or DisplayUtils::drawError("Unable to retrieve list of Coding Systems.");
				while ($lookup_row = @pg_fetch_object($lookup_rs)) {
					echo sprintf("<option value=\"%d\">%s</option>\n", $lookup_row->value, htmlentities($lookup_row->label, ENT_QUOTES, "UTF-8"));
				}
				pg_free_result($lookup_rs);
			echo "</select></div>";

			#icd code text
			echo "<div class=\"addnew_field\" style=\"vertical-align: top;\"><label class=\"vocab_add_form required\" for=\"new_icd_code\">ICD Code</label><br><input class=\"ui-corner-all required\" type=\"text\" name=\"new_icd_code\" id=\"new_icd_code\" /></div>";

			#code description text
			echo "<div class=\"addnew_field\"><label class=\"vocab_add_form\" for=\"new_code_description\">Code Description</label><br><textarea class=\"ui-corner-all\" name=\"new_code_description\" id=\"new_code_description\"></textarea></div>";

			echo "<div class=\"add-form-divider\"></div>";
			#master_condition_id lookup
			echo "<div class=\"addnew_field\"><label class=\"vocab_add_form\" for=\"new_master_condition\">Master Condition</label><br><select class=\"ui-corner-all\" name=\"new_master_condition\" id=\"new_master_condition\">\n";
			echo "<option value=\"\" selected>--</option>\n";
				$lookup_qry = $_SESSION[EXPORT_SERVERNAME]["vocab_params"][$_SESSION[EXPORT_SERVERNAME]["vocab_params"]["vocab"]]["result_cols"]["master_condition"]["lookupqry"];
				$lookup_rs = @pg_query($host_pa, $lookup_qry) or DisplayUtils::drawError("Unable to retrieve list of conditions.");
				while ($lookup_row = @pg_fetch_object($lookup_rs)) {
					echo sprintf("<option value=\"%d\">%s</option>\n", $lookup_row->value, htmlentities($lookup_row->label, ENT_QUOTES, "UTF-8"));
				}
				pg_free_result($lookup_rs);
			echo "</select></div>";

			#master_snomed_id lookup
			echo "<div class=\"addnew_field\"><label class=\"vocab_add_form\" for=\"new_master_snomed\">Master Organism</label><br><select class=\"ui-corner-all\" name=\"new_master_snomed\" id=\"new_master_snomed\">\n";
			echo "<option value=\"\" selected>--</option>\n";
				$lookup_qry = $_SESSION[EXPORT_SERVERNAME]["vocab_params"][$_SESSION[EXPORT_SERVERNAME]["vocab_params"]["vocab"]]["result_cols"]["master_snomed"]["lookupqry"];
				$lookup_rs = @pg_query($host_pa, $lookup_qry) or DisplayUtils::drawError("Unable to retrieve list of organisms.");
				while ($lookup_row = @pg_fetch_object($lookup_rs)) {
					echo sprintf("<option value=\"%d\">%s [%s]</option>\n", $lookup_row->value, htmlentities($lookup_row->label, ENT_QUOTES, "UTF-8"), htmlentities($lookup_row->snomed, ENT_QUOTES, "UTF-8"));
				}
				pg_free_result($lookup_rs);
			echo "</select></div>";

			echo "<div class=\"add-form-divider\"></div>";
            #allow_new_cmr y/n
			echo "<div class=\"addnew_field\"><label class=\"vocab_add_form\" for=\"new_allow_new_cmr\">Create New CMRs?</label><br><select class=\"ui-corner-all\" name=\"new_allow_new_cmr\" id=\"new_allow_new_cmr\">\n";
			echo "<option value=\"t\">Yes</option>\n";
			echo "<option value=\"f\" selected>No</option>\n";
            echo "</select></div>";
			#allow_update_cmr y/n
			echo "<div class=\"addnew_field\"><label class=\"vocab_add_form\" for=\"new_allow_update_cmr\">Update Existing CMRs?</label><br><select class=\"ui-corner-all\" name=\"new_allow_update_cmr\" id=\"new_allow_update_cmr\">\n";
			echo "<option value=\"t\" selected>Yes</option>\n";
			echo "<option value=\"f\">No</option>\n";
            echo "</select></div>";
			#is_surveillance y/n
			echo "<div class=\"addnew_field\"><label class=\"vocab_add_form\" for=\"new_is_surveillance\">New CMRs Are Surveillance Events?</label><br><select class=\"ui-corner-all\" name=\"new_is_surveillance\" id=\"new_is_surveillance\">\n";
			echo "<option value=\"t\">Yes</option>\n";
			echo "<option value=\"f\" selected>No</option>\n";
            echo "</select></div>";

            echo "<div class=\"add-form-divider\"></div>";
            #pregnancy_indicator y/n
			echo "<div class=\"addnew_field\"><label class=\"vocab_add_form\" for=\"new_pregnancy_indicator\">Determines Pregnancy?</label><br><select class=\"ui-corner-all\" name=\"new_pregnancy_indicator\" id=\"new_pregnancy_indicator\">\n";
			echo "<option value=\"t\">Yes</option>\n";
			echo "<option value=\"f\" selected>No</option>\n";
            echo "</select></div>";
			#pregnancy_status y/n/u
			echo "<div class=\"addnew_field\"><label class=\"vocab_add_form\" for=\"new_pregnancy_status\">Pregnancy Status</label><br><select class=\"ui-corner-all\" name=\"new_pregnancy_status\" id=\"new_pregnancy_status\">\n";
			echo "<option value=\"t\">Yes</option>\n";
			echo "<option value=\"f\" selected>No</option>\n";
            echo "<option value=\"u\">Unknown</option>\n";
			echo "</select></div>";

        } elseif (intval($_SESSION[EXPORT_SERVERNAME]["vocab_params"]["vocab"]) == 13) {
            // Master PFGE to SNOMED
			#pattern text
			echo "<div class=\"addnew_field\"><label class=\"vocab_add_form required\" for=\"new_pattern\">PulseNet Serotype Code</label><br><input class=\"ui-corner-all required\" type=\"text\" name=\"new_pattern\" id=\"new_pattern\" /></div>";

			echo "<div class=\"add-form-divider\"></div>";
			#organism lookup (incl master snomed)
			echo "<div class=\"addnew_field\"><label class=\"vocab_add_form required\" for=\"new_organism\">Master Organism SNOMED</label><br><select class=\"ui-corner-all\" name=\"new_organism\" id=\"new_organism\">\n";
			echo "<option value=\"-1\" selected>--</option>\n";
				$lookup_qry = $_SESSION[EXPORT_SERVERNAME]["vocab_params"][$_SESSION[EXPORT_SERVERNAME]["vocab_params"]["vocab"]]["result_cols"]["organism"]["lookupqry"];
				$lookup_rs = @pg_query($host_pa, $lookup_qry) or DisplayUtils::drawError("Unable to retrieve list of organisms.");
				while ($lookup_row = @pg_fetch_object($lookup_rs)) {
					echo sprintf("<option value=\"%d\">%s [%s]</option>\n", $lookup_row->value, htmlentities($lookup_row->label, ENT_QUOTES, "UTF-8"), htmlentities($lookup_row->snomed, ENT_QUOTES, "UTF-8"));
				}
				pg_free_result($lookup_rs);
			echo "</select></div>";
			echo "<div class=\"add-form-divider\"></div>";
        } else {
			// Master LOINC
			#loinc text
			echo "<div class=\"addnew_field\" style=\"vertical-align: top;\"><label class=\"vocab_add_form required\" for=\"new_loinc\">Test Concept Code (LOINC)</label><br><input class=\"ui-corner-all required\" type=\"text\" name=\"new_loinc\" id=\"new_loinc\" /></div>";
			#concept_name text
			echo "<div class=\"addnew_field\"><label class=\"vocab_add_form\" for=\"new_concept_name\">Preferred Concept Name</label><br><textarea class=\"ui-corner-all\" name=\"new_concept_name\" id=\"new_concept_name\"></textarea></div>";

            echo "<div class=\"add-form-divider\"></div>";
            #antimicrobial_agent lookup
			echo "<div class=\"addnew_field\"><label class=\"vocab_add_form\" for=\"new_antimicrobial_agent\">Antimicrobial Agent</label><br><select class=\"ui-corner-all\" name=\"new_antimicrobial_agent\" id=\"new_antimicrobial_agent\">\n";
			echo "<option value=\"-1\" selected>--</option>\n";
				$lookup_qry = $_SESSION[EXPORT_SERVERNAME]["vocab_params"][$_SESSION[EXPORT_SERVERNAME]["vocab_params"]["vocab"]]["result_cols"]["antimicrobial_agent"]["lookupqry"];
				$lookup_rs = @pg_query($host_pa, $lookup_qry) or DisplayUtils::drawError("Unable to retrieve list of Antimicrobial Agents.");
				while ($lookup_row = @pg_fetch_object($lookup_rs)) {
					echo sprintf("<option value=\"%d\">%s</option>\n", $lookup_row->value, htmlentities($lookup_row->label, ENT_QUOTES, "UTF-8"));
				}
				pg_free_result($lookup_rs);
			echo "</select></div>";

			echo "<div class=\"add-form-divider\"></div>";
			#condition_from_result y/n
			echo "<div class=\"addnew_field\"><label class=\"vocab_add_form\" for=\"new_condition_from_result\">Look Up Condition?</label><br><select class=\"ui-corner-all\" name=\"new_condition_from_result\" id=\"new_condition_from_result\">\n";
			echo "<option value=\"t\">Yes</option>\n";
			echo "<option value=\"f\" selected>No</option>\n";
			echo "</select></div>";
			#condition lookup
			echo "<div class=\"addnew_field\"><label class=\"vocab_add_form\" for=\"new_trisano_condition\">Master Condition</label><br><select class=\"ui-corner-all\" name=\"new_trisano_condition\" id=\"new_trisano_condition\">\n";
			echo "<option value=\"-1\" selected>--</option>\n";
				$lookup_qry = $_SESSION[EXPORT_SERVERNAME]["vocab_params"][$_SESSION[EXPORT_SERVERNAME]["vocab_params"]["vocab"]]["result_cols"]["trisano_condition"]["lookupqry"];
				$lookup_rs = @pg_query($host_pa, $lookup_qry) or DisplayUtils::drawError("Unable to retrieve list of conditions.");
				while ($lookup_row = @pg_fetch_object($lookup_rs)) {
					echo sprintf("<option value=\"%d\">%s</option>\n", $lookup_row->value, htmlentities($lookup_row->label, ENT_QUOTES, "UTF-8"));
				}
				pg_free_result($lookup_rs);
			echo "</select></div>";

			echo "<div class=\"add-form-divider\"></div>";
			#organism_from_result y/n
			echo "<div class=\"addnew_field\"><label class=\"vocab_add_form\" for=\"new_organism_from_result\">Look Up Organism?</label><br><select class=\"ui-corner-all\" name=\"new_organism_from_result\" id=\"new_organism_from_result\">\n";
			echo "<option value=\"t\">Yes</option>\n";
			echo "<option value=\"f\" selected>No</option>\n";
			echo "</select></div>";
			#organism lookup
			echo "<div class=\"addnew_field\"><label class=\"vocab_add_form\" for=\"new_trisano_organism\">Master Organism</label><br><select class=\"ui-corner-all\" name=\"new_trisano_organism\" id=\"new_trisano_organism\">\n";
			echo "<option value=\"-1\" selected>--</option>\n";
				$lookup_qry = $_SESSION[EXPORT_SERVERNAME]["vocab_params"][$_SESSION[EXPORT_SERVERNAME]["vocab_params"]["vocab"]]["result_cols"]["trisano_organism"]["lookupqry"];
				$lookup_rs = @pg_query($host_pa, $lookup_qry) or DisplayUtils::drawError("Unable to retrieve list of organisms.");
				while ($lookup_row = @pg_fetch_object($lookup_rs)) {
					echo sprintf("<option value=\"%d\">%s [%s]</option>\n", $lookup_row->value, htmlentities($lookup_row->label, ENT_QUOTES, "UTF-8"), htmlentities($lookup_row->snomed, ENT_QUOTES, "UTF-8"));
				}
				pg_free_result($lookup_rs);
			echo "</select></div>";

			echo "<div class=\"add-form-divider\"></div>";
			#test_type lookup
			echo "<div class=\"addnew_field\"><label class=\"vocab_add_form required\" for=\"new_trisano_test_type\">Test Type</label><br><select class=\"ui-corner-all required\" name=\"new_trisano_test_type\" id=\"new_trisano_test_type\">\n";
			echo "<option value=\"-1\" selected>--</option>\n";
				$lookup_qry = $_SESSION[EXPORT_SERVERNAME]["vocab_params"][$_SESSION[EXPORT_SERVERNAME]["vocab_params"]["vocab"]]["result_cols"]["trisano_test_type"]["lookupqry"];
				$lookup_rs = @pg_query($host_pa, $lookup_qry) or DisplayUtils::drawError("Unable to retrieve list of test types.");
				while ($lookup_row = @pg_fetch_object($lookup_rs)) {
					echo sprintf("<option value=\"%d\">%s</option>\n", $lookup_row->value, htmlentities($lookup_row->label, ENT_QUOTES, "UTF-8"));
				}
				pg_free_result($lookup_rs);
			echo "</select></div>";
			#specimen_source lookup
			echo "<div class=\"addnew_field\"><label class=\"vocab_add_form\" for=\"new_specimen_source\">Specimen Source</label><br><select class=\"ui-corner-all\" name=\"new_specimen_source\" id=\"new_specimen_source\">\n";
			echo "<option value=\"-1\" selected>--</option>\n";
				$lookup_qry = $_SESSION[EXPORT_SERVERNAME]["vocab_params"][$_SESSION[EXPORT_SERVERNAME]["vocab_params"]["vocab"]]["result_cols"]["specimen_source"]["lookupqry"];
				$lookup_rs = @pg_query($host_pa, $lookup_qry) or DisplayUtils::drawError("Unable to retrieve list of specimen sources.");
				while ($lookup_row = @pg_fetch_object($lookup_rs)) {
					echo sprintf("<option value=\"%d\">%s</option>\n", $lookup_row->value, htmlentities($lookup_row->label, ENT_QUOTES, "UTF-8"));
				}
				pg_free_result($lookup_rs);
			echo "</select></div>";
			#list lookup
			echo "<div class=\"addnew_field\"><label class=\"vocab_add_form\" for=\"new_list\">List</label><br><select class=\"ui-corner-all\" name=\"new_list\" id=\"new_list\">\n";
			echo "<option value=\"-1\" selected>--</option>\n";
				$lookup_qry = $_SESSION[EXPORT_SERVERNAME]["vocab_params"][$_SESSION[EXPORT_SERVERNAME]["vocab_params"]["vocab"]]["result_cols"]["list"]["lookupqry"];
				$lookup_rs = @pg_query($host_pa, $lookup_qry) or DisplayUtils::drawError("Unable to retrieve list of lists.");
				while ($lookup_row = @pg_fetch_object($lookup_rs)) {
					echo sprintf("<option value=\"%d\">%s</option>\n", $lookup_row->value, htmlentities($lookup_row->label, ENT_QUOTES, "UTF-8"));
				}
				pg_free_result($lookup_rs);
			echo "</select></div>";

		}
	?>
		<input type="hidden" name="add_flag" value="1" />
		<br><br><button type="submit" name="new_savevocab" id="new_savevocab">Save New <?php echo $_SESSION[EXPORT_SERVERNAME]["vocab_params"][$_SESSION[EXPORT_SERVERNAME]["vocab_params"]["vocab"]]["vocab_verbose"]; ?></button>
		<button type="button" name="addnew_cancel" id="addnew_cancel">Cancel</button>
	</form>
</div>


<div class="vocab_paging_top" style="display: none;">
<?php
	if ($numrows > 0) {
		echo "Page: ";
		/******  build the pagination links ******/
		// range of num links to show
		$range = 3;

		// if not on page 1, don't show back links
		if ($currentpage > 1) {
		   // show << link to go back to page 1
		   printf(" <a href=\"%s?currentpage=%s&selected_page=%s&submenu=%s&cat=%s&subcat=%s\">&lt;&lt;</a> ", $webappBaseUrl, "1", $navSelectedPage, $navSubmenu, (($navCat < 2) ? 1 : $navCat), (($navSubcat < 2) ? 1 : $navSubcat));
		   // get previous page num
		   $prevpage = $currentpage - 1;
		   // show < link to go back to 1 page
		   printf(" <a href=\"%s?currentpage=%s&selected_page=%s&submenu=%s&cat=%s&subcat=%s\">&lt;</a> ", $webappBaseUrl, $prevpage, $navSelectedPage, $navSubmenu, (($navCat < 2) ? 1 : $navCat), (($navSubcat < 2) ? 1 : $navSubcat));
		} // end if

		// loop to show links to range of pages around current page
		for ($x = ($currentpage - $range); $x < (($currentpage + $range) + 1); $x++) {
		   // if it's a valid page number...
		   if (($x > 0) && ($x <= $totalpages)) {
			  // if we're on current page...
			  if ($x == $currentpage) {
				 // 'highlight' it but don't make a link
				 printf(" [<b>%s</b>] ", $x);
			  // if not current page...
			  } else {
				 // make it a link
				 printf(" <a href=\"%s?currentpage=%s&selected_page=%s&submenu=%s&cat=%s&subcat=%s\">%s</a> ", $webappBaseUrl, $x, $navSelectedPage, $navSubmenu, (($navCat < 2) ? 1 : $navCat), (($navSubcat < 2) ? 1 : $navSubcat), $x);
			  } // end else
		   } // end if
		} // end for

		// if not on last page, show forward and last page links
		if ($currentpage != $totalpages) {
		   // get next page
		   $nextpage = $currentpage + 1;
			// echo forward link for next page
		   printf(" <a href=\"%s?currentpage=%s&selected_page=%s&submenu=%s&cat=%s&subcat=%s\">&gt;</a> ", $webappBaseUrl, $nextpage, $navSelectedPage, $navSubmenu, (($navCat < 2) ? 1 : $navCat), (($navSubcat < 2) ? 1 : $navSubcat));
		   // echo forward link for lastpage
		   printf(" <a href=\"%s?currentpage=%s&selected_page=%s&submenu=%s&cat=%s&subcat=%s\">&gt;&gt;</a> ", $webappBaseUrl, $totalpages, $navSelectedPage, $navSubmenu, (($navCat < 2) ? 1 : $navCat), (($navSubcat < 2) ? 1 : $navSubcat));
		} // end if
		/****** end build pagination links ******/
	}
?>

</div>

<table id="latestTasks" class="ui-corner-all">
	<caption>
		<?php
			if ($numrows < 1) {
				echo 'No '.htmlentities($_SESSION[EXPORT_SERVERNAME]["vocab_params"][$_SESSION[EXPORT_SERVERNAME]["vocab_params"]["vocab"]]["vocab_verbose"], ENT_QUOTES, 'UTF-8').' records found!';
			} elseif ($_SESSION[EXPORT_SERVERNAME]["vocab_params"][$_SESSION[EXPORT_SERVERNAME]["vocab_params"]["vocab"]]["page_rows"] > 0) {
				echo intval($offset+1).' - '.((intval($offset+$_SESSION[EXPORT_SERVERNAME]["vocab_params"][$_SESSION[EXPORT_SERVERNAME]["vocab_params"]["vocab"]]["page_rows"])>$numrows) ? intval($numrows) : intval($offset+$_SESSION[EXPORT_SERVERNAME]["vocab_params"][$_SESSION[EXPORT_SERVERNAME]["vocab_params"]["vocab"]]["page_rows"])).' of '.intval($numrows).' records';
			} else {
				echo intval($offset+1).' - '.intval($numrows).' of '.intval($numrows).' records';
			}

			if ($numrows > 0) {
				echo '<div style="border-left: 1px dimgray dotted; display: inline; margin-left: 15px; padding-left: 15px;">';
				echo "Page: ";
				/******  build the pagination links ******/
				// range of num links to show
				$range = 3;

				// if not on page 1, don't show back links
				if ($currentpage > 1) {
				   // show << link to go back to page 1
				   printf(" <a href=\"%s?currentpage=%s&selected_page=%s&submenu=%s&cat=%s&subcat=%s\">&lt;&lt;</a> ", $webappBaseUrl, "1", $navSelectedPage, $navSubmenu, (($navCat < 2) ? 1 : $navCat), (($navSubcat < 2) ? 1 : $navSubcat));
				   // get previous page num
				   $prevpage = $currentpage - 1;
				   // show < link to go back to 1 page
				   printf(" <a href=\"%s?currentpage=%s&selected_page=%s&submenu=%s&cat=%s&subcat=%s\">&lt;</a> ", $webappBaseUrl, $prevpage, $navSelectedPage, $navSubmenu, (($navCat < 2) ? 1 : $navCat), (($navSubcat < 2) ? 1 : $navSubcat));
				} // end if

				// loop to show links to range of pages around current page
				for ($x = ($currentpage - $range); $x < (($currentpage + $range) + 1); $x++) {
				   // if it's a valid page number...
				   if (($x > 0) && ($x <= $totalpages)) {
					  // if we're on current page...
					  if ($x == $currentpage) {
						 // 'highlight' it but don't make a link
						 printf(" [<b>%s</b>] ", $x);
					  // if not current page...
					  } else {
						 // make it a link
						 printf(" <a href=\"%s?currentpage=%s&selected_page=%s&submenu=%s&cat=%s&subcat=%s\">%s</a> ", $webappBaseUrl, $x, $navSelectedPage, $navSubmenu, (($navCat < 2) ? 1 : $navCat), (($navSubcat < 2) ? 1 : $navSubcat), $x);
					  } // end else
				   } // end if
				} // end for

				// if not on last page, show forward and last page links
				if ($currentpage != $totalpages) {
				   // get next page
				   $nextpage = $currentpage + 1;
					// echo forward link for next page
				   printf(" <a href=\"%s?currentpage=%s&selected_page=%s&submenu=%s&cat=%s&subcat=%s\">&gt;</a> ", $webappBaseUrl, $nextpage, $navSelectedPage, $navSubmenu, (($navCat < 2) ? 1 : $navCat), (($navSubcat < 2) ? 1 : $navSubcat));
				   // echo forward link for lastpage
				   printf(" <a href=\"%s?currentpage=%s&selected_page=%s&submenu=%s&cat=%s&subcat=%s\">&gt;&gt;</a> ", $webappBaseUrl, $totalpages, $navSelectedPage, $navSubmenu, (($navCat < 2) ? 1 : $navCat), (($navSubcat < 2) ? 1 : $navSubcat));
				} // end if
				/****** end build pagination links ******/
				echo '</div>';
			}
		?>
		<form name="user_rowselect" id="user_rowselect" method="GET" action="<?php echo $webappBaseUrl; ?>" style="border-left: 1px dimgray dotted; display: inline; margin-left: 15px; padding-left: 15px;">
            <label for="user_rows">Rows per page:</label>
			<select name="user_rows" id="user_rows" class="ui-corner-all">
			<?php
				foreach ($valid_rowsize as $this_rowsize) {
					echo "<option value=\"" . $this_rowsize . "\"" . (($this_rowsize == $_SESSION[EXPORT_SERVERNAME]["vocab_params"][$_SESSION[EXPORT_SERVERNAME]["vocab_params"]["vocab"]]["page_rows"]) ? " selected" : "") . ">" . (($this_rowsize > 0) ? $this_rowsize : "All") . "</option>";
				}
			?>
			</select>
            <button id="user_rows_change" title="Set number of rows per page">Update</button>
			<input type="hidden" name="selected_page" value="<?php echo $navSelectedPage; ?>">
			<input type="hidden" name="submenu" value="<?php echo $navSubmenu; ?>">
			<input type="hidden" name="cat" value="<?php echo (($navCat < 2) ? 1 : $navCat); ?>">
			<input type="hidden" name="subcat" value="<?php echo (($navSubcat < 2) ? 1 : $navSubcat); ?>">
		</form>
	</caption>
	<thead>
		<tr>
			<th>Actions</th>
<?php

		// draw column headers...
		foreach ($_SESSION[EXPORT_SERVERNAME]["vocab_params"][$_SESSION[EXPORT_SERVERNAME]["vocab_params"]["vocab"]]["result_cols"] as $headercol => $headername) {
			if ($headername['display']) {
				$sort_indicator = "";
				$sort_text = sprintf("Sort by '%s' [A-Z]", $headername['label']);
				if ($headercol == $_SESSION[EXPORT_SERVERNAME]["vocab_params"][$_SESSION[EXPORT_SERVERNAME]["vocab_params"]["vocab"]]["sort_col"]) {
					if ($_SESSION[EXPORT_SERVERNAME]["vocab_params"][$_SESSION[EXPORT_SERVERNAME]["vocab_params"]["vocab"]]["sort_order"] == "ASC") {
						$sort_indicator = "colheader_sort_down";
						$sort_text = sprintf("Sort by '%s' [Z-A]", $headername['label']);
					} else {
						$sort_indicator = "colheader_sort_up";
						$sort_text = sprintf("Sort by '%s' [A-Z]", $headername['label']);
					}
				}
				printf("<th><a class=\"colheader %s\" title=\"%s\" href=\"%s?selected_page=%s&submenu=%s&cat=%s&subcat=%s&sort=%s&order=%s&currentpage=1\">%s</a></div></th>", $sort_indicator, $sort_text, $webappBaseUrl, $navSelectedPage, $navSubmenu, (($navCat < 2) ? 1 : $navCat), (($navSubcat < 2) ? 1 : $navSubcat), $headercol, ((($headercol == $_SESSION[EXPORT_SERVERNAME]["vocab_params"][$_SESSION[EXPORT_SERVERNAME]["vocab_params"]["vocab"]]["sort_col"]) && ($_SESSION[EXPORT_SERVERNAME]["vocab_params"][$_SESSION[EXPORT_SERVERNAME]["vocab_params"]["vocab"]]["sort_order"] == "ASC")) ? "2" : "1"), $headername['label']);
			} elseif ($headername['rules_placeholder']) {
				if (intval($_SESSION[EXPORT_SERVERNAME]["vocab_params"]["vocab"]) == 5) {
					// per configured application, draw a 'result rules' column...
					$app_sql = "SELECT id, app_name FROM ".$emsaDbSchemaPrefix."vocab_app WHERE enabled IS TRUE ORDER BY app_name;";
					$app_rs = @pg_query($host_pa, $app_sql);
					if ($app_rs) {
						while ($app_row = @pg_fetch_object($app_rs)) {
							echo "<th>" . filter_var(DisplayUtils::xSafe($app_row->app_name), \FILTER_SANITIZE_STRING) . " Qn Interpretation Rules</th>";
						}
					} else {
						DisplayUtils::drawError("Unable to retrieve list of Applications.");
					}
					@pg_free_result($app_rs);
					unset($app_row);
				}
				if (intval($_SESSION[EXPORT_SERVERNAME]["vocab_params"]["vocab"]) == 1) {
					// per configured application, draw a 'master loinc rules' column...
					$app_sql = "SELECT id, app_name FROM ".$emsaDbSchemaPrefix."vocab_app WHERE enabled IS TRUE ORDER BY app_name;";
					$app_rs = @pg_query($host_pa, $app_sql);
					if ($app_rs) {
						while ($app_row = @pg_fetch_object($app_rs)) {
							echo "<th>" . DisplayUtils::xSafe($app_row->app_name) . " LOINC-Based Case Management Rules</th>";
						}
					} else {
						DisplayUtils::drawError("Unable to retrieve list of Applications.");
					}
					@pg_free_result($app_rs);
					unset($app_row);
				}
				if (intval($_SESSION[EXPORT_SERVERNAME]["vocab_params"]["vocab"]) == 2) {
					// per configured application, draw a 'graylist rules' column...
					$app_sql = "SELECT id, app_name FROM ".$emsaDbSchemaPrefix."vocab_app WHERE enabled IS TRUE ORDER BY app_name;";
					$app_rs = @pg_query($host_pa, $app_sql);
					if ($app_rs) {
						while ($app_row = @pg_fetch_object($app_rs)) {
							echo "<th>" . DisplayUtils::xSafe($app_row->app_name) . " Graylist Rules</th>";
						}
					} else {
						DisplayUtils::drawError("Unable to retrieve list of Applications.");
					}
					@pg_free_result($app_rs);
					unset($app_row);
				}
				if (intval($_SESSION[EXPORT_SERVERNAME]["vocab_params"]["vocab"]) == 3) {
					// per configured application, draw an 'organism cmr rules' column...
					$app_sql = "SELECT id, app_name FROM ".$emsaDbSchemaPrefix."vocab_app WHERE enabled IS TRUE ORDER BY app_name;";
					$app_rs = @pg_query($host_pa, $app_sql);
					if ($app_rs) {
						while ($app_row = @pg_fetch_object($app_rs)) {
							echo "<th>" . DisplayUtils::xSafe($app_row->app_name) . " Organism-Based Case Management Rules</th>";
						}
					} else {
						DisplayUtils::drawError("Unable to retrieve list of Applications.");
					}
					@pg_free_result($app_rs);
					unset($app_row);
				}
			}
		}

		echo "</tr></thead><tbody>";

		$vocab_select = "SELECT ";
		foreach ($_SESSION[EXPORT_SERVERNAME]["vocab_params"][$_SESSION[EXPORT_SERVERNAME]["vocab_params"]["vocab"]]["result_cols"] as $selectcol => $selectcoldata) {
			$vocab_select .= $selectcoldata['colname'] . " AS " . $selectcol . ", ";
		}

		// go grab our data...
		if ($_SESSION[EXPORT_SERVERNAME]["vocab_params"][$_SESSION[EXPORT_SERVERNAME]["vocab_params"]["vocab"]]["page_rows"] > 0) {
			$getvocab_sql = sprintf("%s %s %s LIMIT %d OFFSET %d;", trim($vocab_select, ", "), $vocab_fromwhere, $_SESSION[EXPORT_SERVERNAME]["vocab_params"][$_SESSION[EXPORT_SERVERNAME]["vocab_params"]["vocab"]]["query_data"]["order_by"], $_SESSION[EXPORT_SERVERNAME]["vocab_params"][$_SESSION[EXPORT_SERVERNAME]["vocab_params"]["vocab"]]["page_rows"], $offset);
		} else {
			$getvocab_sql = sprintf("%s %s %s;", trim($vocab_select, ", "), $vocab_fromwhere, $_SESSION[EXPORT_SERVERNAME]["vocab_params"][$_SESSION[EXPORT_SERVERNAME]["vocab_params"]["vocab"]]["query_data"]["order_by"]);
		}
		#debug echo $getvocab_sql;
		$getvocab_rs = pg_query($host_pa,$getvocab_sql) or die("Can't Perform Query ".pg_last_error());

		$previous_row_id = '';
		while ($getvocab_row = pg_fetch_object($getvocab_rs)) {
            unset($this_rowclass);
			$initial_condition = true;
			if (isset($getvocab_row->archived) && ($getvocab_row->archived == 't')) {
				$this_rowstyle = 'font-style: italic; text-decoration: line-through !important; color: darkgray !important; background-color: ghostwhite !important;';
			} elseif (isset($getvocab_row->is_initial) && ($getvocab_row->is_initial == 'f')) {
				$this_rowstyle = 'text-decoration: none; color: #b85c1a !important;';
				$initial_condition = false;
			} else {
				$this_rowstyle = 'text-decoration: none;';
			}
			if ((intval($_SESSION[EXPORT_SERVERNAME]["vocab_params"]["vocab"]) == 4) || (intval($_SESSION[EXPORT_SERVERNAME]["vocab_params"]["vocab"]) == 5) || (intval($_SESSION[EXPORT_SERVERNAME]["vocab_params"]["vocab"]) == 13) || (intval($_SESSION[EXPORT_SERVERNAME]["vocab_params"]["vocab"]) == 14)) {
				echo '<tr id="vocab_row_'.intval($getvocab_row->id).'" style="'.$this_rowstyle.'"><td nowrap>';
			} else {
				echo '<tr id="vocab_row_'.intval($getvocab_row->{$_SESSION[EXPORT_SERVERNAME]["vocab_params"][$_SESSION[EXPORT_SERVERNAME]["vocab_params"]["vocab"]]["query_data"]["id_column"]}).'" style="'.$this_rowstyle.'"><td nowrap>';
			}
			if ((intval($_SESSION[EXPORT_SERVERNAME]["vocab_params"]["vocab"]) == 4) || (intval($_SESSION[EXPORT_SERVERNAME]["vocab_params"]["vocab"]) == 5) || (intval($_SESSION[EXPORT_SERVERNAME]["vocab_params"]["vocab"]) == 13) || (intval($_SESSION[EXPORT_SERVERNAME]["vocab_params"]["vocab"]) == 14)) {
				// hard-code 'id' for child vocabs
				$delete_array = array(
					'current_id' => intval($getvocab_row->id),
					'previous_id' => $previous_row_id
				);
				printf("<button class=\"edit_vocab\" type=\"button\" value=\"%s\" title=\"Edit this vocabulary\">Edit</button>", intval($getvocab_row->id));
				printf("<button class=\"delete_vocab\" type=\"button\" value='%s' title=\"Delete this vocabulary\">Delete</button>", @json_encode($delete_array));
				$previous_row_id = intval($getvocab_row->id);
			} else {
				// use configured 'id_column' for master vocabs
				$delete_array = array(
					'current_id' => intval($getvocab_row->{$_SESSION[EXPORT_SERVERNAME]["vocab_params"][$_SESSION[EXPORT_SERVERNAME]["vocab_params"]["vocab"]]["query_data"]["id_column"]}),
					'previous_id' => $previous_row_id
				);
				printf("<button class=\"edit_vocab\" type=\"button\" value=\"%s\" title=\"Edit this vocabulary\">Edit</button>", intval($getvocab_row->{$_SESSION[EXPORT_SERVERNAME]["vocab_params"][$_SESSION[EXPORT_SERVERNAME]["vocab_params"]["vocab"]]["query_data"]["id_column"]}));
				printf("<button class=\"delete_vocab\" type=\"button\" value='%s' title=\"Delete this vocabulary\">Delete</button>", @json_encode($delete_array));
				$previous_row_id = intval($getvocab_row->{$_SESSION[EXPORT_SERVERNAME]["vocab_params"][$_SESSION[EXPORT_SERVERNAME]["vocab_params"]["vocab"]]["query_data"]["id_column"]});
			}
			echo "</ul></td>";

			foreach ($_SESSION[EXPORT_SERVERNAME]["vocab_params"][$_SESSION[EXPORT_SERVERNAME]["vocab_params"]["vocab"]]["result_cols"] as $colname => $colvalue) {
				if ($colvalue['display']) {
					if (in_array($colname, $bool_cols)) {
						if ($colname == "is_initial") {
							printf("<td class=\"vocab_data_cell\">%s</td>", ((trim($getvocab_row->$colname) == "t") ? "Initial" : "Final"));
                        } elseif ($colname == "pregnancy_status") {
                            if (trim($getvocab_row->$colname) == 't') {
                                echo '<td class="vocab_data_cell">Yes</td>';
                            } elseif (trim($getvocab_row->$colname) == 'f') {
                                echo '<td class="vocab_data_cell">No</td>';
                            } else {
                                echo '<td class="vocab_data_cell">Unknown</td>';
                            }
						} elseif ($colname == "interpret_override") {
                            if (trim($getvocab_row->$colname) == 't') {
                                echo '<td class="vocab_data_cell">Override Quantitative</td>';
                            } elseif (trim($getvocab_row->$colname) == 'f') {
                                echo '<td class="vocab_data_cell">Override Coded Entry</td>';
                            } else {
                                echo '<td class="vocab_data_cell">Set by OBX-2</td>';
                            }
						} elseif ($colname == "semi_auto_usage") {
                            if (trim($getvocab_row->$colname) == 't') {
                                echo '<td class="vocab_data_cell">Force Semi-Auto</td>';
                            } elseif (trim($getvocab_row->$colname) == 'f') {
                                echo '<td class="vocab_data_cell">Skip Semi-Auto</td>';
                            } else {
                                echo '<td class="vocab_data_cell">Allow Semi-Auto</td>';
                            }
						} else {
							if ($initial_condition) {
								printf("<td class=\"vocab_data_cell\">%s</td>", ((trim($getvocab_row->$colname) == "t") ? "Yes" : "No"));
							} else {
								printf("<td class=\"vocab_data_cell\">%s</td>", '');
							}
						}
					} else {
						if ($colname == "gateway_xref" || $colname == "o2m_addcmr_exclusions") {
							printf("<td class=\"vocab_data_cell\">%s</td>", ((strlen(trim($getvocab_row->$colname)) > 0) ? htmlentities(implode(";", VocabUtils::whitelistCrossrefNamesByIdList($adminDbConn, trim($getvocab_row->$colname))), ENT_QUOTES, "UTF-8") : ""));
						} elseif (($colname == "valid_specimen") || ($colname == "invalid_specimen")) {
							if ($initial_condition) {
								printf("<td class=\"vocab_data_cell\">%s</td>", ((strlen(trim($getvocab_row->$colname)) > 0) ? DisplayUtils::xSafe(VocabUtils::specimenIdValues($adminDbConn, trim($getvocab_row->$colname))) : ""));
							} else {
								printf("<td class=\"vocab_data_cell\">%s</td>", '');
							}
						} elseif ($colname == "workflow") {
							if (intval($getvocab_row->$colname) === ENTRY_STATUS) {
								printf("<td class=\"vocab_data_cell\">%s</td>", "Automated Processing");
							} elseif (intval($getvocab_row->$colname) === QA_STATUS) {
								printf("<td class=\"vocab_data_cell\">%s</td>", "QA Review");
							} elseif (intval($getvocab_row->$colname) === SEMI_AUTO_STATUS) {
								printf("<td class=\"vocab_data_cell\">%s</td>", "Semi-Automated Entry");
							} else {
								printf("<td class=\"vocab_data_cell\">%s</td>", "--[Unknown Workflow]--");
							}
						} elseif ($colname == "notes") {
                            printf("<td class=\"vocab_data_cell vocab_admin_notes\">%s</td>", htmlentities(trim($getvocab_row->$colname), ENT_QUOTES, "UTF-8"));
                        } elseif (isset($colvalue['main_concept']) && ($colvalue['main_concept'] === true)) {
                            printf("<td class=\"vocab_data_cell vocab_data_concept\">%s</td>", htmlentities(trim($getvocab_row->$colname), ENT_QUOTES, "UTF-8"));
                        } else {
							if (isset($colvalue['linkback']) && (intval($colvalue['linkback']) > 0)) {
								printf("<td class=\"vocab_data_cell\"><a href=\"%s\">%s</a></td>", $webappBaseUrl."?selected_page=6&submenu=3&cat=1&subcat=".intval($colvalue['linkback'])."&q=".urlencode(trim($getvocab_row->$colname)), htmlentities(trim($getvocab_row->$colname), ENT_QUOTES, "UTF-8"));
							} else {
								printf("<td class=\"vocab_data_cell\">%s</td>", htmlentities(trim($getvocab_row->$colname), ENT_QUOTES, "UTF-8"));
							}
						}
					}
				} elseif ($colvalue['rules_placeholder']) {
					if (intval($_SESSION[EXPORT_SERVERNAME]["vocab_params"]["vocab"]) == 5) {
						/**
						 * Interpretive rule management
						 * display separate column for each configured app...
						 */
						$app_sql = "SELECT id, app_name FROM ".$emsaDbSchemaPrefix."vocab_app WHERE enabled IS TRUE ORDER BY app_name;";
						$app_rs = @pg_query($host_pa, $app_sql);
						if ($app_rs) {
							while ($app_row = @pg_fetch_object($app_rs)) {
								echo "<td>";
								$result_rules_sql = sprintf("SELECT c2m.*, m2a.coded_value || CASE WHEN sc.label = 'test_result' THEN ' (Labs)' ELSE ' (AST)' END AS app_value
                                    FROM %svocab_c2m_testresult c2m
									INNER JOIN %svocab_master2app m2a ON (m2a.master_id = c2m.master_id AND m2a.app_id = c2m.app_id)
                                    INNER JOIN %svocab_master_vocab mv ON (m2a.master_id = mv.id)
                                    INNER JOIN %sstructure_category sc ON (sc.id = mv.category)
									WHERE c2m.child_loinc_id = %d AND c2m.app_id = %d ORDER BY c2m.id", $emsaDbSchemaPrefix, $emsaDbSchemaPrefix, $emsaDbSchemaPrefix, $emsaDbSchemaPrefix, intval($getvocab_row->id), (int) $app_row->id);
								$result_rules_rs = @pg_query($host_pa, $result_rules_sql);
								if ($result_rules_rs) {
									unset($rule_mod_params);
									echo "<table class=\"result_rules\" width=\"100%\"><thead><tr><th>Actions</th><th>Rule</th></tr></thead><tbody>";
									if (pg_num_rows($result_rules_rs) < 1) {
										echo "<tr><td nowrap class=\"action_col\"><span class=\"ui-icon ui-icon-elrerror\" style=\"float: right;\"></span></td><td nowrap><strong style=\"color: #9a2900;\">No Result Rules defined for this LOINC!</strong></td></tr>";
									}
									while ($result_rules_row = @pg_fetch_object($result_rules_rs)) {
										unset($rule_mod_params);
										$rule_mod_params = array(
											"id" => intval($result_rules_row->id),
											"focus_id" => intval($getvocab_row->id),
											"action" => "edit",
											"lab_name" => trim($getvocab_row->lab),
											"child_loinc" => trim($getvocab_row->child_loinc),
											"application" => intval(trim($result_rules_row->app_id)),
											"conditions" => json_decode($result_rules_row->conditions_structured, true),
											"master_result" => intval(trim($result_rules_row->master_id)),
											"comments" => trim($result_rules_row->results_to_comments));
										echo "<tr><td nowrap class=\"action_col\"><button class=\"edit_result_rule\" value='".json_encode($rule_mod_params)."' type=\"button\" title=\"Edit this Result Rule\">Edit</button>";
										unset($rule_mod_params);
										$rule_mod_params = array(
											"id" => intval($result_rules_row->id),
											"focus_id" => intval($getvocab_row->id),
											"action" => "delete",
											"lab_name" => trim($getvocab_row->lab),
											"child_loinc" => trim($getvocab_row->child_loinc));
										echo "<button class=\"delete_result_rule\" value='".json_encode($rule_mod_params)."' type=\"button\" title=\"Delete this Result Rule\">Delete</button></td>";

										echo "<td nowrap style=\"width: 100%;\" title=\"Add Results to Comments: " . DisplayUtils::xSafe($result_rules_row->results_to_comments) . "\">[";
										$this_rule_decoded_conditions = @json_decode($result_rules_row->conditions_structured);
										$this_rule_counter = 1;
										foreach ($this_rule_decoded_conditions as $this_condition) {
											echo "Result <strong style=\"color: green; font-size: 1.1em;\">".EmsaUtils::graphicalOperatorById($adminDbConn, $this_condition->operator)."</strong> &quot;" . htmlentities($this_condition->operand, ENT_QUOTES, "UTF-8") . "&quot;";
											if ($this_rule_counter < sizeof($this_rule_decoded_conditions)) {
												echo " <strong style=\"color: darkred; font-size: 1.1em;\">&</strong> ";
											}
											$this_rule_counter++;
										}
										echo "] <strong style=\"color: blue; font-size: 1.1em;\">&rArr;</strong> <strong>". DisplayUtils::xSafe(trim($result_rules_row->app_value));
										echo "</strong></td></tr>";
									}
									unset($rule_mod_params);
									$rule_mod_params = array(
										"id" => intval($getvocab_row->id),
										"focus_id" => intval($getvocab_row->id),
										"action" => "add",
                                        "application" => (int) $app_row->id,
										"lab_name" => trim($getvocab_row->lab),
										"child_loinc" => trim($getvocab_row->child_loinc));
									echo "<tr><td nowrap class=\"action_col\"><button class=\"add_result_rule\" value='".json_encode($rule_mod_params)."' type=\"button\" title=\"Add new Result Rule for this Child LOINC\">Add New</button></td><td style=\"width: 100%;\" nowrap><em>&lt;Add New Result Rule&gt;</em></td></tr>";
									echo "</tbody></table>";
								} else {
									DisplayUtils::drawError("Unable to retrieve list of " . DisplayUtils::xSafe($app_row->app_name) . " Result Rules for LOINC " . DisplayUtils::xSafe($getvocab_row->child_loinc) . ".");
								}
								@pg_free_result($result_rules_rs);
								echo "</td>";
							}
						} else {
							DisplayUtils::drawError("Unable to retrieve list of Applications.");
						}
						@pg_free_result($app_rs);
						unset($app_row);
					}

					if (intval($_SESSION[EXPORT_SERVERNAME]["vocab_params"]["vocab"]) == 1) {
						/**
						 * Case Management (Master LOINC) rule management
						 * display separate column for each configured app...
						 */
						$app_sql = "SELECT id, app_name FROM ".$emsaDbSchemaPrefix."vocab_app WHERE enabled IS TRUE ORDER BY app_name;";
						$app_rs = @pg_query($host_pa, $app_sql);
						if ($app_rs) {
							while ($app_row = @pg_fetch_object($app_rs)) {
								echo "<td>";
								$result_rules_sql = 'SELECT vrml.*, mv.concept AS master_status_value,
									CASE WHEN vrml.allow_new_cmr IS TRUE THEN \'Yes\' ELSE \'No\' END AS allow_new_cmr_verbose,
									CASE WHEN vrml.allow_update_cmr IS TRUE THEN \'Yes\' ELSE \'No\' END AS allow_update_cmr_verbose,
									CASE WHEN vrml.is_surveillance IS TRUE THEN \'Yes\' ELSE \'No\' END AS is_surveillance_verbose
									FROM '.$emsaDbSchemaPrefix.'vocab_rules_masterloinc vrml
									LEFT JOIN '.$emsaDbSchemaPrefix.'vocab_master_vocab mv ON (mv.id = vrml.state_case_status_master_id)
									WHERE vrml.master_loinc_id = '.intval($getvocab_row->l_id).'
                                    AND vrml.app_id = ' . (int) $app_row->id . '
                                    ORDER BY vrml.id;';
								$result_rules_rs = @pg_query($host_pa, $result_rules_sql);
								if ($result_rules_rs) {
									unset($rule_mod_params);
									echo "<table class=\"result_rules\" width=\"100%\"><thead><tr><th>Actions</th><th nowrap>Test Result</th><th nowrap>New CMR?</th><th nowrap>Update CMRs?</th><th nowrap>Surveillance?</th><th nowrap>State Case Status</th></tr></thead><tbody>";
									if (pg_num_rows($result_rules_rs) < 1) {
										echo "<tr><td nowrap class=\"action_col\"><span class=\"ui-icon ui-icon-elrerror\" style=\"float: right;\"></span></td><td nowrap colspan=\"5\"><strong style=\"color: #9a2900;\">No Case Management Rules defined for this LOINC!</strong></td></tr>";
									}
									while ($result_rules_row = @pg_fetch_object($result_rules_rs)) {
										unset($rule_mod_params);
										$rule_mod_params = array(
											"id" => intval($result_rules_row->id),
											"focus_id" => intval($getvocab_row->l_id),
											"action" => "edit",
											"loinc" => trim($getvocab_row->loinc),
											"application" => intval(trim($result_rules_row->app_id)),
											"conditions" => json_decode($result_rules_row->conditions_structured, true),
											"master_result" => intval(trim($result_rules_row->state_case_status_master_id)),
											"allow_new_cmr" => trim($result_rules_row->allow_new_cmr),
											"allow_update_cmr" => trim($result_rules_row->allow_update_cmr),
											"is_surveillance" => trim($result_rules_row->is_surveillance));
										echo "<tr><td nowrap class=\"action_col\"><button class=\"edit_cmr_rule\" value='".json_encode($rule_mod_params)."' type=\"button\" title=\"Edit this Rule\">Edit</button>";
										unset($rule_mod_params);
										$rule_mod_params = array(
											"id" => intval($result_rules_row->id),
											"focus_id" => intval($getvocab_row->l_id),
											"action" => "delete",
											"loinc" => trim($getvocab_row->loinc));
										echo "<button class=\"delete_cmr_rule\" value='".json_encode($rule_mod_params)."' type=\"button\" title=\"Delete this Rule\">Delete</button></td>";

										echo "<td nowrap style=\"width: 100%;\">";
										$this_rule_decoded_conditions = @json_decode($result_rules_row->conditions_structured);
										$this_rule_counter = 1;
										foreach ($this_rule_decoded_conditions as $this_condition) {
											echo "<strong style=\"color: green; font-size: 1.1em;\">".EmsaUtils::graphicalOperatorById($adminDbConn, $this_condition->operator)."</strong> &quot;" . VocabUtils::appCodedTestResultValueByMasterID($adminDbConn, $this_condition->operand, (int) $app_row->id) . "&quot;";
											if ($this_rule_counter < sizeof($this_rule_decoded_conditions)) {
												echo " <strong style=\"color: darkred; font-size: 1.1em;\">&</strong> ";
											}
											$this_rule_counter++;
										}
										echo "</td>";
										if (trim($result_rules_row->allow_new_cmr_verbose) == 'Yes') {
											echo '<td nowrap style="text-align: center;"><span class="ui-icon ui-icon-elrsuccess" style="margin-left: auto; margin-right: auto;" title="Yes"></span></td>';
										} else {
											echo '<td nowrap style="text-align: center;"><span class="ui-icon ui-icon-elrcancel" style="margin-left: auto; margin-right: auto;" title="No"></span></td>';
										}
										if (trim($result_rules_row->allow_update_cmr_verbose) == 'Yes') {
											echo '<td nowrap style="text-align: center;"><span class="ui-icon ui-icon-elrsuccess" style="margin-left: auto; margin-right: auto;" title="Yes"></span></td>';
										} else {
											echo '<td nowrap style="text-align: center;"><span class="ui-icon ui-icon-elrcancel" style="margin-left: auto; margin-right: auto;" title="No"></span></td>';
										}
										if (trim($result_rules_row->is_surveillance_verbose) == 'Yes') {
											echo '<td nowrap style="text-align: center;"><span class="ui-icon ui-icon-elrsuccess" style="margin-left: auto; margin-right: auto;" title="Yes"></span></td>';
										} else {
											echo '<td nowrap style="text-align: center;"><span class="ui-icon ui-icon-elrcancel" style="margin-left: auto; margin-right: auto;" title="No"></span></td>';
										}
										echo "<td nowrap><strong>".((strlen(trim($result_rules_row->master_status_value)) > 0) ? htmlentities(trim($result_rules_row->master_status_value), ENT_QUOTES, "UTF-8") : '--');
										echo "</strong></td></tr>";
									}
									unset($rule_mod_params);
									$rule_mod_params = array(
										"id" => intval($getvocab_row->l_id),
										"focus_id" => intval($getvocab_row->l_id),
										"action" => "add",
                                        "application" => (int) $app_row->id,
										"loinc" => trim($getvocab_row->loinc));
									echo "<tr><td nowrap class=\"action_col\"><button class=\"add_cmr_rule\" value='".json_encode($rule_mod_params)."' type=\"button\" title=\"Add new Case Management Rule for this Master LOINC\">Add New</button></td><td style=\"width: 100%;\" nowrap colspan=\"5\"><em>&lt;Add New LOINC-based Case Management Rule&gt;</em></td></tr>";
									echo "</tbody></table>";
								} else {
									DisplayUtils::drawError("Unable to retrieve list of " . DisplayUtils::xSafe($app_row->app_name) . " Case Management Rules for Master LOINC " . DisplayUtils::xSafe($getvocab_row->loinc) . ".");
								}
								@pg_free_result($result_rules_rs);
								echo "</td>";
							}
						} else {
							DisplayUtils::drawError("Unable to retrieve list of Applications.");
						}
						@pg_free_result($app_rs);
						unset($app_row);
					}

					if (intval($_SESSION[EXPORT_SERVERNAME]["vocab_params"]["vocab"]) == 3) {
						/**
						 * Case Management (Nominal/Organism-based) rule management
						 * display separate column for each configured app...
						 */
						$app_sql = "SELECT id, app_name FROM ".$emsaDbSchemaPrefix."vocab_app WHERE enabled IS TRUE ORDER BY app_name;";
						$app_rs = @pg_query($host_pa, $app_sql);
						if ($app_rs) {
							while ($app_row = @pg_fetch_object($app_rs)) {
								echo "<td>";
								$result_rules_sql = 'SELECT vrms.*, mv.concept AS master_status_value,
									CASE WHEN vrms.allow_new_cmr IS TRUE THEN \'Yes\' ELSE \'No\' END AS allow_new_cmr_verbose,
									CASE WHEN vrms.allow_update_cmr IS TRUE THEN \'Yes\' ELSE \'No\' END AS allow_update_cmr_verbose,
									CASE WHEN vrms.is_surveillance IS TRUE THEN \'Yes\' ELSE \'No\' END AS is_surveillance_verbose
									FROM '.$emsaDbSchemaPrefix.'vocab_rules_mastersnomed vrms
									LEFT JOIN '.$emsaDbSchemaPrefix.'vocab_master_vocab mv ON (mv.id = vrms.state_case_status_master_id)
									WHERE vrms.master_snomed_id = '.intval($getvocab_row->o_id).'
                                    AND vrms.app_id = ' . (int) $app_row->id . '
                                    ORDER BY vrms.id;';
								$result_rules_rs = @pg_query($host_pa, $result_rules_sql);
								if ($result_rules_rs) {
									unset($rule_mod_params);
									echo "<table class=\"result_rules\" width=\"100%\"><thead><tr><th>Actions</th><th nowrap>Test Result</th><th nowrap>New CMR?</th><th nowrap>Update CMRs?</th><th nowrap>Surveillance?</th><th nowrap>State Case Status</th></tr></thead><tbody>";
									if (pg_num_rows($result_rules_rs) < 1) {
										echo "<tr><td nowrap class=\"action_col\"><span class=\"ui-icon ui-icon-elrerror\" style=\"float: right;\"></span></td><td nowrap colspan=\"5\"><strong style=\"color: #9a2900;\">No Case Management Rules defined for this Organism!</strong></td></tr>";
									}
									while ($result_rules_row = @pg_fetch_object($result_rules_rs)) {
										unset($rule_mod_params);
										$rule_mod_params = array(
											"id" => intval($result_rules_row->id),
											"focus_id" => intval($getvocab_row->o_id),
											"action" => "edit",
											"organism" => trim($getvocab_row->organism),
											"application" => intval(trim($result_rules_row->app_id)),
											"conditions" => json_decode($result_rules_row->conditions_structured, true),
											"master_result" => intval(trim($result_rules_row->state_case_status_master_id)),
											"allow_new_cmr" => trim($result_rules_row->allow_new_cmr),
											"allow_update_cmr" => trim($result_rules_row->allow_update_cmr),
											"is_surveillance" => trim($result_rules_row->is_surveillance));
										echo "<tr><td nowrap class=\"action_col\"><button class=\"edit_ms_cmr_rule\" value='".json_encode($rule_mod_params)."' type=\"button\" title=\"Edit this Rule\">Edit</button>";
										unset($rule_mod_params);
										$rule_mod_params = array(
											"id" => intval($result_rules_row->id),
											"focus_id" => intval($getvocab_row->o_id),
											"action" => "delete",
											"organism" => trim($getvocab_row->organism));
										echo "<button class=\"delete_ms_cmr_rule\" value='".json_encode($rule_mod_params)."' type=\"button\" title=\"Delete this Rule\">Delete</button></td>";

										echo "<td nowrap style=\"width: 100%; line-height: 1.2em;\">";
										$this_rule_decoded_conditions = @json_decode($result_rules_row->conditions_structured);
										$this_rule_counter = 1;
										foreach ($this_rule_decoded_conditions as $this_condition) {
											echo "<strong style=\"color: green; font-size: 1.1em;\">".EmsaUtils::graphicalOperatorById($adminDbConn, $this_condition->operator)."</strong> &quot;" . VocabUtils::appCodedTestResultValueByMasterID($adminDbConn, $this_condition->operand, (int) $app_row->id) . "&quot;";
											if ($this_rule_counter < sizeof($this_rule_decoded_conditions)) {
												echo "<br><strong style=\"color: darkred; font-size: 1.1em;\">&</strong> ";
											}
											$this_rule_counter++;
										}
										echo "</td>";

										if (trim($result_rules_row->allow_new_cmr_verbose) == 'Yes') {
											echo '<td nowrap style="text-align: center;"><span class="ui-icon ui-icon-elrsuccess" style="margin-left: auto; margin-right: auto;" title="Yes"></span></td>';
										} else {
											echo '<td nowrap style="text-align: center;"><span class="ui-icon ui-icon-elrcancel" style="margin-left: auto; margin-right: auto;" title="No"></span></td>';
										}
										if (trim($result_rules_row->allow_update_cmr_verbose) == 'Yes') {
											echo '<td nowrap style="text-align: center;"><span class="ui-icon ui-icon-elrsuccess" style="margin-left: auto; margin-right: auto;" title="Yes"></span></td>';
										} else {
											echo '<td nowrap style="text-align: center;"><span class="ui-icon ui-icon-elrcancel" style="margin-left: auto; margin-right: auto;" title="No"></span></td>';
										}
										if (trim($result_rules_row->is_surveillance_verbose) == 'Yes') {
											echo '<td nowrap style="text-align: center;"><span class="ui-icon ui-icon-elrsuccess" style="margin-left: auto; margin-right: auto;" title="Yes"></span></td>';
										} else {
											echo '<td nowrap style="text-align: center;"><span class="ui-icon ui-icon-elrcancel" style="margin-left: auto; margin-right: auto;" title="No"></span></td>';
										}
										echo "<td nowrap><strong>".((strlen(trim($result_rules_row->master_status_value)) > 0) ? htmlentities(trim($result_rules_row->master_status_value), ENT_QUOTES, "UTF-8") : '--');
										echo "</strong></td></tr>";
									}
									unset($rule_mod_params);
									$rule_mod_params = array(
										"id" => intval($getvocab_row->o_id),
										"focus_id" => intval($getvocab_row->o_id),
										"action" => "add",
                                        "application" => (int) $app_row->id,
										"organism" => trim($getvocab_row->organism));
									echo "<tr><td nowrap class=\"action_col\"><button class=\"add_ms_cmr_rule\" value='".json_encode($rule_mod_params)."' type=\"button\" title=\"Add new Case Management Rule for this Organism\">Add New</button></td><td style=\"width: 100%;\" nowrap colspan=\"5\"><em>&lt;Add New Organism-based Case Management Rule&gt;</em></td></tr>";
									echo "</tbody></table>";
								} else {
									DisplayUtils::drawError("Unable to retrieve list of " . DisplayUtils::xSafe($app_row->app_name) . " Case Management Rules for Organism " . DisplayUtils::xSafe($getvocab_row->loinc) . ".");
								}
								@pg_free_result($result_rules_rs);
								echo "</td>";
							}
						} else {
							DisplayUtils::drawError("Unable to retrieve list of Applications.");
						}
						@pg_free_result($app_rs);
						unset($app_row);
					}

					if (intval($_SESSION[EXPORT_SERVERNAME]["vocab_params"]["vocab"]) == 2) {
						/**
						 * Graylist rule management
						 * display separate column for each configured app...
						 */
						$app_sql = "SELECT id, app_name FROM ".$emsaDbSchemaPrefix."vocab_app WHERE enabled IS TRUE ORDER BY app_name;";
						$app_rs = @pg_query($host_pa, $app_sql);
						if ($app_rs) {
							while ($app_row = @pg_fetch_object($app_rs)) {
								echo "<td>";
								$result_rules_sql = 'SELECT vrg.*
									FROM '.$emsaDbSchemaPrefix.'vocab_rules_graylist vrg
									WHERE vrg.master_condition_id = '.intval($getvocab_row->c_id).'
                                    AND vrg.app_id = ' . (int) $app_row->id . '
                                    ORDER BY vrg.id;';
								$result_rules_rs = @pg_query($host_pa, $result_rules_sql);
								if ($result_rules_rs) {
									unset($rule_mod_params);
									echo "<table class=\"result_rules\" width=\"100%\"><thead><tr><th>Actions</th><th nowrap>Condition</th><th nowrap>Test Type</th><th nowrap>Collected within...</th></tr></thead><tbody>";
									if (pg_num_rows($result_rules_rs) < 1) {
										echo "<tr><td nowrap class=\"action_col\"><span class=\"ui-icon ui-icon-elrerror\" style=\"float: right;\"></span></td><td nowrap colspan=\"3\"><strong style=\"color: #9a2900;\">No Graylist Rules defined for this Condition!</strong></td></tr>";
									}
									while ($result_rules_row = @pg_fetch_object($result_rules_rs)) {
										unset($rule_mod_params);
										$rule_mod_params = array(
											"id" => intval($result_rules_row->id),
											"focus_id" => intval($getvocab_row->c_id),
											"action" => "edit",
											"disease" => trim($getvocab_row->condition),
											"application" => intval(trim($result_rules_row->app_id)),
											"conditions" => json_decode($result_rules_row->conditions_structured, true));
										echo "<tr><td nowrap class=\"action_col\"><button class=\"edit_gray_rule\" value='".json_encode($rule_mod_params)."' type=\"button\" title=\"Edit this Rule\">Edit</button>";
										unset($rule_mod_params);
										$rule_mod_params = array(
											"id" => intval($result_rules_row->id),
											"focus_id" => intval($getvocab_row->c_id),
											"action" => "delete",
											"disease" => trim($getvocab_row->condition));
										echo "<button class=\"delete_gray_rule\" value='".json_encode($rule_mod_params)."' type=\"button\" title=\"Delete this Rule\">Delete</button></td>";

										$this_rule_decoded_conditions = @json_decode($result_rules_row->conditions_structured);
										$this_rule_counter = 1;
										foreach ($this_rule_decoded_conditions as $this_condition) {
											echo "<td style=\"width: 100%; line-height: 1.2em;\">";
											echo "<strong style=\"color: green; font-size: 1.1em;\">".EmsaUtils::graphicalOperatorById($adminDbConn, $this_condition->operator)."</strong> &quot;".((intval($this_condition->operand) < 0) ? "Any" : VocabUtils::appCodedValueByMasterID($adminDbConn, $this_condition->operand, (int) $app_row->id)) . "&quot;";
											echo "</td>";

											echo "<td nowrap style=\"width: 100%; line-height: 1.2em;\">";
											echo "<strong style=\"color: green; font-size: 1.1em;\">".EmsaUtils::graphicalOperatorById($adminDbConn, $this_condition->operator1)."</strong> &quot;" . ((intval($this_condition->operand1) < 0) ? "Any" : VocabUtils::appCodedValueByMasterID($adminDbConn, $this_condition->operand1, (int) $app_row->id)) . "&quot;";
											echo "</td>";

											echo "<td nowrap style=\"width: 100%; line-height: 1.2em;\">";
											echo DisplayUtils::xSafe($this_condition->collect_lbound) . " before Event Date<br>" . DisplayUtils::xSafe($this_condition->collect_ubound) . " after Event Date";
											echo "</td>";
		//									if ($this_rule_counter < sizeof($this_rule_decoded_conditions)) {
		//										echo "<br><strong style=\"color: darkred; font-size: 1.1em;\">&</strong> ";
		//									}
											$this_rule_counter++;
										}
										echo "</tr>";
									}
									unset($rule_mod_params);
									$rule_mod_params = array(
										"id" => intval($getvocab_row->c_id),
										"focus_id" => intval($getvocab_row->c_id),
										"action" => "add",
                                        "application" => (int) $app_row->id,
										"disease" => trim($getvocab_row->condition));
									echo "<tr><td nowrap class=\"action_col\"><button class=\"add_gray_rule\" value='".json_encode($rule_mod_params)."' type=\"button\" title=\"Add new Graylist Rule for this Master Condition\">Add New</button></td><td style=\"width: 100%;\" nowrap colspan=\"3\"><em>&lt;Add New Graylist Rule&gt;</em></td></tr>";
									echo "</tbody></table>";
								} else {
									DisplayUtils::drawError("Unable to retrieve list of " . DisplayUtils::xSafe($app_row->app_name) . " Graylist Rules for Condition " . DisplayUtils::xSafe($getvocab_row->condition) . ".");
								}
								@pg_free_result($result_rules_rs);
								echo "</td>";
							}
						} else {
							DisplayUtils::drawError("Unable to retrieve list of Applications.");
						}
						@pg_free_result($app_rs);
						unset($app_row);
					}
				}
			}

			echo "</tr>";
		}

?>
	</tbody>
</table>

<div class="vocab_paging vocab_paging_bottom">
<?php
	if ($numrows > 0) {
		echo "Page: ";
		/******  build the pagination links ******/
		// range of num links to show
		$range = 3;

		// if not on page 1, don't show back links
		if ($currentpage > 1) {
		   // show << link to go back to page 1
		   printf(" <a href=\"%s?currentpage=%s&selected_page=%s&submenu=%s&cat=%s&subcat=%s\">&lt;&lt;</a> ", $webappBaseUrl, "1", $navSelectedPage, $navSubmenu, (($navCat < 2) ? 1 : $navCat), (($navSubcat < 2) ? 1 : $navSubcat));
		   // get previous page num
		   $prevpage = $currentpage - 1;
		   // show < link to go back to 1 page
		   printf(" <a href=\"%s?currentpage=%s&selected_page=%s&submenu=%s&cat=%s&subcat=%s\">&lt;</a> ", $webappBaseUrl, $prevpage, $navSelectedPage, $navSubmenu, (($navCat < 2) ? 1 : $navCat), (($navSubcat < 2) ? 1 : $navSubcat));
		} // end if

		// loop to show links to range of pages around current page
		for ($x = ($currentpage - $range); $x < (($currentpage + $range) + 1); $x++) {
		   // if it's a valid page number...
		   if (($x > 0) && ($x <= $totalpages)) {
			  // if we're on current page...
			  if ($x == $currentpage) {
				 // 'highlight' it but don't make a link
				 printf(" [<b>%s</b>] ", $x);
			  // if not current page...
			  } else {
				 // make it a link
				 printf(" <a href=\"%s?currentpage=%s&selected_page=%s&submenu=%s&cat=%s&subcat=%s\">%s</a> ", $webappBaseUrl, $x, $navSelectedPage, $navSubmenu, (($navCat < 2) ? 1 : $navCat), (($navSubcat < 2) ? 1 : $navSubcat), $x);
			  } // end else
		   } // end if
		} // end for

		// if not on last page, show forward and last page links
		if ($currentpage != $totalpages) {
		   // get next page
		   $nextpage = $currentpage + 1;
			// echo forward link for next page
		   printf(" <a href=\"%s?currentpage=%s&selected_page=%s&submenu=%s&cat=%s&subcat=%s\">&gt;</a> ", $webappBaseUrl, $nextpage, $navSelectedPage, $navSubmenu, (($navCat < 2) ? 1 : $navCat), (($navSubcat < 2) ? 1 : $navSubcat));
		   // echo forward link for lastpage
		   printf(" <a href=\"%s?currentpage=%s&selected_page=%s&submenu=%s&cat=%s&subcat=%s\">&gt;&gt;</a> ", $webappBaseUrl, $totalpages, $navSelectedPage, $navSubmenu, (($navCat < 2) ? 1 : $navCat), (($navSubcat < 2) ? 1 : $navSubcat));
		} // end if
		/****** end build pagination links ******/
	}
?>
</div>

<div id="confirm_delete_dialog" title="Delete this <?php echo $_SESSION[EXPORT_SERVERNAME]["vocab_params"][$_SESSION[EXPORT_SERVERNAME]["vocab_params"]["vocab"]]["vocab_verbose"]; ?> record?">
	<p><span class="ui-icon ui-icon-elrerror" style="float:left; margin:0 7px 50px 0;"></span>This <?php echo $_SESSION[EXPORT_SERVERNAME]["vocab_params"][$_SESSION[EXPORT_SERVERNAME]["vocab_params"]["vocab"]]["vocab_verbose"]; ?> will be permanently deleted and cannot be recovered. Are you sure?</p>
</div>

<?php

	if ($_SESSION[EXPORT_SERVERNAME]["vocab_params"]["vocab"] == 5) {
		// Child LOINC Quant Interpretive rules
		include_once __DIR__ . '/vocab_rules_div_childloinc.php';
	} elseif ($_SESSION[EXPORT_SERVERNAME]["vocab_params"]["vocab"] == 1) {
		// Master LOINC CMR rules
		include_once __DIR__ . '/vocab_rules_div_masterloinc.php';
	} elseif ($_SESSION[EXPORT_SERVERNAME]["vocab_params"]["vocab"] == 3) {
		// Master SNOMED CMR rules
		include_once __DIR__ . '/vocab_rules_div_mastersnomed.php';
	} elseif ($_SESSION[EXPORT_SERVERNAME]["vocab_params"]["vocab"] == 2) {
		// Master Condition -- Graylist Rules
		include_once __DIR__ . '/vocab_rules_div_graylist.php';
	}

	pg_free_result($getvocab_rs);

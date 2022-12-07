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

if (!class_exists('\Udoh\Emsa\Auth\Authenticator') || !\Udoh\Emsa\Auth\Authenticator::userHasPermission(\Udoh\Emsa\Auth\Authenticator::URIGHTS_ADMIN)) {
    ob_end_clean();
    header($_SERVER['SERVER_PROTOCOL'] . " 404 Not Found", TRUE, 404);
    exit;
}

	/*
	 * graylist_rules.php
	 *
	 * Included in vocabulary.php to process adding, editing, or deleting
	 * Graylist rules (triggered by detecting a valid action specified 
	 * in $_GET['rulemod_gray_action'] (add, edit, or delete).
	 */
	
	// sanitization
	unset($clean_rulemod);
	$clean_rulemod['action'] = trim($_GET['rulemod_gray_action']);     // already sanitized via in_array() on vocabulary.php
	if (isset($_GET['rulemod_gray_id']) && (intval(trim($_GET['rulemod_gray_id'])) > 0)) {
		$clean_rulemod['id'] = intval(trim($_GET['rulemod_gray_id']));
	}
	$clean_rulemod['rulemod_cmr_application'] = ((isset($_GET['rulemod_gray_application']) && (intval(trim($_GET['rulemod_gray_application'])) > 0)) ? intval(trim($_GET['rulemod_gray_application'])) : 2);  // default to EpiTrax if none
	
	// make sure valid ID was passed first, depending on whether we're adding or editing/deleting
	$valid_id = false;
	if (isset($clean_rulemod['id'])) {
		if ($clean_rulemod['action'] == "add") {
			// 'add' passes a master condition id...
			$valid_id_sql = "SELECT count(c_id) AS counter FROM ".$emsaDbSchemaPrefix."vocab_master_condition WHERE c_id = ".intval($clean_rulemod['id']).";";
		} else {
			// 'edit' & 'delete' both pass a rule ID...
			$valid_id_sql = "SELECT count(id) AS counter FROM ".$emsaDbSchemaPrefix."vocab_rules_graylist WHERE id = ".intval($clean_rulemod['id']).";";
		}
		$valid_id_rs = @pg_query($host_pa, $valid_id_sql);
		if ($valid_id_rs) {
			if (@pg_fetch_result($valid_id_rs, 0, 'counter') == 1) {
				$valid_id = true;
			}
		}
	}
	
	if (!$valid_id) {
		\Udoh\Emsa\Utils\DisplayUtils::drawError("Could not modify Graylist Rules.  An invalid ID was specified.", true);
	}
	
	if ((($clean_rulemod['action'] == "add") || ($clean_rulemod['action'] == "edit")) && (!\Udoh\Emsa\Utils\DateTimeUtils::validateDateTimeString($_GET['rulemod_gray_collect_lbound']) || !\Udoh\Emsa\Utils\DateTimeUtils::validateDateTimeString($_GET['rulemod_gray_collect_ubound']))) {
		\Udoh\Emsa\Utils\DisplayUtils::drawError('Could not add/update Graylist Rule:<br><br>Collection Date Range does not contain a valid time interval.');
		$clean_rulemod['action'] = "skip";  // don't add, edit or delete
	}
	
	
	/*
	 * Add a new rule
	 */
	if ($clean_rulemod['action'] == "add") {
		// add
		unset($conditions_arr);
		unset($condition_script);
		
		// check if operator & operand arrays are set and same number of elements in each
		if (
				   isset($_GET['rulemod_gray_operator']) 
				&& isset($_GET['rulemod_gray_operator1']) 
				&& isset($_GET['rulemod_gray_operand_value']) 
				&& isset($_GET['rulemod_gray_operand_value1'])
				&& isset($_GET['rulemod_gray_collect_lbound']) 
				&& isset($_GET['rulemod_gray_collect_ubound']) 
			) {
			
			if (
					   (strlen(trim($_GET['rulemod_gray_operand_value'])) > 0)
					&& (strlen(trim($_GET['rulemod_gray_operand_value1'])) > 0)
					&& (strlen(trim($_GET['rulemod_gray_collect_lbound'])) > 0)
					&& (strlen(trim($_GET['rulemod_gray_collect_ubound'])) > 0)
				) {
				// operator and operand pairs exist, operands are non-empty...
				$conditions_arr[] = array(
					"operator" => intval($_GET['rulemod_gray_operator']), 
					"operand" => filter_var(trim($_GET['rulemod_gray_operand_value']), FILTER_SANITIZE_STRING), 
					"operator1" => intval($_GET['rulemod_gray_operator1']), 
					"operand1" => filter_var(trim($_GET['rulemod_gray_operand_value1']), FILTER_SANITIZE_STRING), 
					"collect_lbound" => filter_var(trim($_GET['rulemod_gray_collect_lbound']), FILTER_SANITIZE_STRING), 
					"collect_ubound" => filter_var(trim($_GET['rulemod_gray_collect_ubound']), FILTER_SANITIZE_STRING), 
				);
			}
			
			if (!isset($conditions_arr) || !is_array($conditions_arr)) {
				\Udoh\Emsa\Utils\DisplayUtils::drawError("Unable to add new Graylist Rule.  No conditions found for specified rule.");
			}
		} else {
			\Udoh\Emsa\Utils\DisplayUtils::drawError("Unable to add new Graylist Rule.  Missing operator or operand for one or more conditions.");
		}
		
		if (isset($conditions_arr)) {
			$rulemod_add_sql = sprintf("INSERT INTO %svocab_rules_graylist 
				(master_condition_id, conditions_structured, app_id) VALUES 
				(%d, '%s', %d);", $emsaDbSchemaPrefix,
				$clean_rulemod['id'], @pg_escape_string(json_encode($conditions_arr)), $clean_rulemod['rulemod_cmr_application']
			);
			$rulemod_add_rs = @pg_query($host_pa, $rulemod_add_sql);
			if ($rulemod_add_rs) {
				\Udoh\Emsa\Utils\DisplayUtils::drawHighlight("New Graylist Rule added successfully!", "ui-icon-elrsuccess");
				
				$va_prepared_new_vals = $va->prepareNewValues(VocabAudit::TABLE_GRAYLIST_RULES, array(
					'app_id' => $clean_rulemod['rulemod_cmr_application'],
					'conditions' => $va->verboseGraylistConditions($conditions_arr)
				));

				$va->resetAudit();
				$va->setNewVals($va_prepared_new_vals);
				$va->auditVocab($clean_rulemod['id'], VocabAudit::TABLE_GRAYLIST_RULES, VocabAudit::ACTION_ADD);
			} else {
                \Udoh\Emsa\Utils\DisplayUtils::drawError("An error occurred while trying to add a new Graylist Rule.");
			}
			@pg_free_result($rulemod_add_rs);
		}
	}
	
	
	/*
	 * Delete an existing rule
	 */
	if ($clean_rulemod['action'] == "delete") {
		$va_prev_vals = $va->getPreviousVals(intval($clean_rulemod['id']), VocabAudit::TABLE_GRAYLIST_RULES);
		$va_cmrrule_parentid = $va->getRuleParentId($clean_rulemod['id'], VocabAudit::TABLE_GRAYLIST_RULES);
		// delete
		$rulemod_delete_sql = sprintf("DELETE FROM ONLY %svocab_rules_graylist WHERE id = %d;", $emsaDbSchemaPrefix, $clean_rulemod['id']);
		$rulemod_delete_rs = @pg_query($host_pa, $rulemod_delete_sql);
		if ($rulemod_delete_rs) {
			\Udoh\Emsa\Utils\DisplayUtils::drawHighlight("Graylist Rule successfully deleted!", "ui-icon-elrsuccess");
			
			$va->resetAudit();
			$va->setOldVals($va_prev_vals);
			$va->auditVocab(intval($va_cmrrule_parentid), VocabAudit::TABLE_GRAYLIST_RULES, VocabAudit::ACTION_DELETE);
		} else {
            \Udoh\Emsa\Utils\DisplayUtils::drawError("An error occurred while trying to delete an existing Master SNOMED Case Management Rule.");
		}
		@pg_free_result($rulemod_delete_rs);
	}
	
	
	/*
	 * Edit an existing rule
	 */
	if ($clean_rulemod['action'] == "edit") {
		$va_prev_vals = $va->getPreviousVals(intval($clean_rulemod['id']), VocabAudit::TABLE_GRAYLIST_RULES);
		$va_cmrrule_parentid = $va->getRuleParentId($clean_rulemod['id'], VocabAudit::TABLE_GRAYLIST_RULES);
		// edit
		unset($conditions_arr);
		
		// check if operator & operand arrays are set and same number of elements in each
		if (
				   isset($_GET['rulemod_gray_operator']) 
				&& isset($_GET['rulemod_gray_operator1']) 
				&& isset($_GET['rulemod_gray_operand_value']) 
				&& isset($_GET['rulemod_gray_operand_value1'])
				&& isset($_GET['rulemod_gray_collect_lbound']) 
				&& isset($_GET['rulemod_gray_collect_ubound']) 
			) {
			
			if (
					   (strlen(trim($_GET['rulemod_gray_operand_value'])) > 0)
					&& (strlen(trim($_GET['rulemod_gray_operand_value1'])) > 0)
					&& (strlen(trim($_GET['rulemod_gray_collect_lbound'])) > 0)
					&& (strlen(trim($_GET['rulemod_gray_collect_ubound'])) > 0)
				) {
				// operator and operand pairs exist, operands are non-empty...
				$conditions_arr[] = array(
					"operator" => intval($_GET['rulemod_gray_operator']), 
					"operand" => filter_var(trim($_GET['rulemod_gray_operand_value']), FILTER_SANITIZE_STRING), 
					"operator1" => intval($_GET['rulemod_gray_operator1']), 
					"operand1" => filter_var(trim($_GET['rulemod_gray_operand_value1']), FILTER_SANITIZE_STRING), 
					"collect_lbound" => filter_var(trim($_GET['rulemod_gray_collect_lbound']), FILTER_SANITIZE_STRING), 
					"collect_ubound" => filter_var(trim($_GET['rulemod_gray_collect_ubound']), FILTER_SANITIZE_STRING), 
				);
			}
			
			if (!is_array($conditions_arr)) {
				\Udoh\Emsa\Utils\DisplayUtils::drawError("Unable to update Graylist Rule.  No conditions found for specified rule.");
			}
		} else {
			\Udoh\Emsa\Utils\DisplayUtils::drawError("Unable to update Graylist Rule.  Missing operator or operand for one or more conditions.");
		}
		
		if (isset($conditions_arr)) {
			$rulemod_edit_sql = sprintf("UPDATE %svocab_rules_graylist SET 
				conditions_structured = '%s', app_id = %d 
				WHERE id = %d;",
				$emsaDbSchemaPrefix,
				@pg_escape_string(json_encode($conditions_arr)),
				$clean_rulemod['rulemod_cmr_application'],
				$clean_rulemod['id']);
			$rulemod_edit_rs = @pg_query($host_pa, $rulemod_edit_sql);
			if ($rulemod_edit_rs) {
				\Udoh\Emsa\Utils\DisplayUtils::drawHighlight("Graylist Rule successfully updated!", "ui-icon-elrsuccess");
				
				$va_prepared_new_vals = $va->prepareNewValues(VocabAudit::TABLE_GRAYLIST_RULES, array(
					'app_id' => $clean_rulemod['rulemod_cmr_application'],
					'conditions' => $va->verboseGraylistConditions($conditions_arr)
				));

				$va->resetAudit();
				$va->setOldVals($va_prev_vals);
				$va->setNewVals($va_prepared_new_vals);
				$va->auditVocab($va_cmrrule_parentid, VocabAudit::TABLE_GRAYLIST_RULES, VocabAudit::ACTION_EDIT);
			} else {
                \Udoh\Emsa\Utils\DisplayUtils::drawError("An error occurred while trying to edit an existing Graylist Rule.");
			}
			@pg_free_result($rulemod_edit_rs);
		}
	}

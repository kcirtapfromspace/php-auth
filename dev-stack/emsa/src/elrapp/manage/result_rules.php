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

use Udoh\Emsa\Utils\CoreUtils;

if (!class_exists('\Udoh\Emsa\Auth\Authenticator') || !\Udoh\Emsa\Auth\Authenticator::userHasPermission(\Udoh\Emsa\Auth\Authenticator::URIGHTS_ADMIN)) {
    ob_end_clean();
    header($_SERVER['SERVER_PROTOCOL'] . " 404 Not Found", TRUE, 404);
    exit;
}

	/*
	 * result_rules.php
	 *
	 * Included in vocabulary.php to process adding, editing, or deleting
	 * Child LOINC result interpretive rules (triggered by detecting a
	 * valid action specified in $_GET['rulemod_action'] (add, edit, or delete).
	 */
	
	// sanitization
	unset($clean_rulemod);
	$clean_rulemod['action'] = trim($_GET['rulemod_action']);     // already sanitized via in_array() on vocabulary.php
	if (isset($_GET['rulemod_id']) && (intval(trim($_GET['rulemod_id'])) > 0)) {
		$clean_rulemod['id'] = intval(trim($_GET['rulemod_id']));
	}
	/*$clean_rulemod['rulemod_operator'] 		= ((isset($_GET['rulemod_operator']) && (intval(trim($_GET['rulemod_operator'])) > 0)) ? intval(trim($_GET['rulemod_operator'])) : -1);
	$clean_rulemod['rulemod_operand_value']	= ((isset($_GET['rulemod_operand_value']) && (strlen(trim($_GET['rulemod_operand_value'])) > 0)) ? "'".pg_escape_string(trim($_GET['rulemod_operand_value']))."'" : "NULL");*/
	$clean_rulemod['rulemod_master_result']	= ((isset($_GET['rulemod_master_result']) && (intval(trim($_GET['rulemod_master_result'])) > 0)) ? intval(trim($_GET['rulemod_master_result'])) : -1);
	$clean_rulemod['rulemod_application']	= ((isset($_GET['rulemod_application']) && (intval(trim($_GET['rulemod_application'])) > 0)) ? intval(trim($_GET['rulemod_application'])) : 2);  // default to EpiTrax if none
	/*$clean_rulemod['rulemod_child2system']	= ((isset($_GET['rulemod_child2system']) && (strlen(trim($_GET['rulemod_child2system'])) > 0)) ? "'".pg_escape_string(trim($_GET['rulemod_child2system']))."'" : "NULL");
	$clean_rulemod['rulemod_units']			= ((isset($_GET['rulemod_units']) && (strlen(trim($_GET['rulemod_units'])) > 0)) ? "'".pg_escape_string(trim($_GET['rulemod_units']))."'" : "NULL");
	$clean_rulemod['rulemod_refrange']		= ((isset($_GET['rulemod_refrange']) && (strlen(trim($_GET['rulemod_refrange'])) > 0)) ? "'".pg_escape_string(trim($_GET['rulemod_refrange']))."'" : "NULL");*/
	$clean_rulemod['rulemod_comments']		= ((isset($_GET['rulemod_comments']) && (strlen(trim($_GET['rulemod_comments'])) > 0)) ? "'".pg_escape_string(trim($_GET['rulemod_comments']))."'" : "NULL");
	/*$clean_rulemod['rulemod_snomed']		= ((isset($_GET['rulemod_snomed']) && (strlen(trim($_GET['rulemod_snomed'])) > 0)) ? "'".pg_escape_string(trim($_GET['rulemod_snomed']))."'" : "NULL");*/
	
	// make sure valid ID was passed first, depending on whether we're adding or editing/deleting
	$valid_id = false;
	if (isset($clean_rulemod['id'])) {
		if ($clean_rulemod['action'] == "add") {
			// 'add' passes a child LOINC id...
			$valid_id_sql = "SELECT count(id) AS counter FROM ".$emsaDbSchemaPrefix."vocab_child_loinc WHERE id = ".intval($clean_rulemod['id']).";";
		} else {
			// 'edit' & 'delete' both pass a rule ID...
			$valid_id_sql = "SELECT count(id) AS counter FROM ".$emsaDbSchemaPrefix."vocab_c2m_testresult WHERE id = ".intval($clean_rulemod['id']).";";
		}
		$valid_id_rs = @pg_query($host_pa, $valid_id_sql);
		if ($valid_id_rs) {
			if (@pg_fetch_result($valid_id_rs, 0, 'counter') == 1) {
				$valid_id = true;
			}
		}
	}
	
	if (!$valid_id) {
		\Udoh\Emsa\Utils\DisplayUtils::drawError("Could not modify Child Result Interpretive Rules.  An invalid ID was specified.", true);
	}
	
	
	/*
	 * Add a new rule
	 */
	if ($clean_rulemod['action'] == "add") {
		// add
		unset($conditions_arr);
		unset($condition_script);
		
		// check if operator & operand arrays are set and same number of elements in each
		if (isset($_GET['rulemod_operator']) && isset($_GET['rulemod_operand_value']) && is_array($_GET['rulemod_operator']) && is_array($_GET['rulemod_operand_value'])) {
			foreach ($_GET['rulemod_operator'] as $operator_key => $operator_value) {
				if (isset($_GET['rulemod_operand_value'][$operator_key]) && (strlen(trim($_GET['rulemod_operand_value'][$operator_key])) > 0)) {
					// operator and operand pair exists, operand is non-empty...
					$conditions_arr[] = array("operator" => intval($operator_value), "operand" => trim($_GET['rulemod_operand_value'][$operator_key]));
				}
			}
			
			if (isset($conditions_arr) && is_array($conditions_arr)) {
				$condition_script = "(";
				foreach ($conditions_arr as $condition_obj) {
					if (CoreUtils::operatorById($condition_obj['operator']) == 'Contains') {
						$condition_script .= "(input.indexOf('".$condition_obj['operand']."') != -1) && ";
					} elseif (CoreUtils::operatorById($condition_obj['operator']) == 'Does Not Contain') {
						$condition_script .= "(input.indexOf('".$condition_obj['operand']."') == -1) && ";
					} else {
						$condition_script .= "(input ".CoreUtils::operatorById($condition_obj['operator']) . " " . ((is_numeric($condition_obj['operand']) || (stripos($condition_obj['operand'], ':') !== false)) ? str_replace('1:', '', $condition_obj['operand']) : "'".$condition_obj['operand']."'" ) . ") && ";
					}
				}
				$condition_script = substr($condition_script, 0, strlen($condition_script)-4);
				$condition_script .= ")";
			} else {
				\Udoh\Emsa\Utils\DisplayUtils::drawError("Unable to add new Child Result Interpretive Rule.  No conditions found for specified rule.");
			}
		} else {
			\Udoh\Emsa\Utils\DisplayUtils::drawError("Unable to add new Child Result Interpretive Rule.  Missing operator or test value for one or more conditions.");
		}
		
		if (isset($conditions_arr) && isset($condition_script)) {
			$rulemod_add_sql = sprintf("INSERT INTO %svocab_c2m_testresult 
				(child_loinc_id, conditions_structured, conditions_js, master_id, app_id, results_to_comments) VALUES 
				(%d, '%s', '%s', %d, %d, %s);", $emsaDbSchemaPrefix,
				$clean_rulemod['id'], @pg_escape_string(json_encode($conditions_arr)), @pg_escape_string($condition_script), $clean_rulemod['rulemod_master_result'], $clean_rulemod['rulemod_application'], $clean_rulemod['rulemod_comments']
			);
			$rulemod_add_rs = @pg_query($host_pa, $rulemod_add_sql);
			if ($rulemod_add_rs) {
				\Udoh\Emsa\Utils\DisplayUtils::drawHighlight("New Child Result Interpretive Rule added successfully!", "ui-icon-elrsuccess");
				
				$va_resultrule_prepared_new_vals = $va->prepareNewValues(VocabAudit::TABLE_CHILD_TESTRESULT, array(
					'app_id' => $_GET['rulemod_application'],
					'conditions' => $va->verboseInterpRuleConditions($condition_script),
					'test_result' => $_GET['rulemod_master_result'],
					'comments' => $_GET['rulemod_comments']
				));
				
				$va->resetAudit();
				$va->setNewVals($va_resultrule_prepared_new_vals);
				$va->auditVocab($clean_rulemod['id'], VocabAudit::TABLE_CHILD_TESTRESULT, VocabAudit::ACTION_ADD);
			} else {
                \Udoh\Emsa\Utils\DisplayUtils::drawError("An error occurred while trying to add a new Child Result Interpretive Rule.");
			}
			@pg_free_result($rulemod_add_rs);
		}
	}
	
	
	/*
	 * Delete an existing rule
	 */
	if ($clean_rulemod['action'] == "delete") {
		$va_resultrule_parentid = $va->getRuleParentId($clean_rulemod['id'], VocabAudit::TABLE_CHILD_TESTRESULT);
		$va_resultrule_prev_vals = $va->getPreviousVals($clean_rulemod['id'], VocabAudit::TABLE_CHILD_TESTRESULT);
		// delete
		$rulemod_delete_sql = sprintf("DELETE FROM ONLY %svocab_c2m_testresult WHERE id = %d;", $emsaDbSchemaPrefix, $clean_rulemod['id']);
		$rulemod_delete_rs = @pg_query($host_pa, $rulemod_delete_sql);
		if ($rulemod_delete_rs) {
			\Udoh\Emsa\Utils\DisplayUtils::drawHighlight("Child Result Interpretive Rule successfully deleted!", "ui-icon-elrsuccess");
			
			$va->resetAudit();
			$va->setOldVals($va_resultrule_prev_vals);
			$va->auditVocab($va_resultrule_parentid, VocabAudit::TABLE_CHILD_TESTRESULT, VocabAudit::ACTION_DELETE);
		} else {
            \Udoh\Emsa\Utils\DisplayUtils::drawError("An error occurred while trying to delete an existing Child Result Interpretive Rule.");
		}
		@pg_free_result($rulemod_delete_rs);
	}
	
	
	/*
	 * Edit an existing rule
	 */
	if ($clean_rulemod['action'] == "edit") {
		$va_resultrule_parentid = $va->getRuleParentId($clean_rulemod['id'], VocabAudit::TABLE_CHILD_TESTRESULT);
		$va_resultrule_prev_vals = $va->getPreviousVals($clean_rulemod['id'], VocabAudit::TABLE_CHILD_TESTRESULT);
		// edit
		unset($conditions_arr);
		unset($condition_script);
		
		// check if operator & operand arrays are set and same number of elements in each
		if (isset($_GET['rulemod_operator']) && isset($_GET['rulemod_operand_value']) && is_array($_GET['rulemod_operator']) && is_array($_GET['rulemod_operand_value'])) {
			foreach ($_GET['rulemod_operator'] as $operator_key => $operator_value) {
				if (isset($_GET['rulemod_operand_value'][$operator_key]) && (strlen(trim($_GET['rulemod_operand_value'][$operator_key])) > 0)) {
					// operator and operand pair exists, operand is non-empty...
					$conditions_arr[] = array("operator" => intval($operator_value), "operand" => trim($_GET['rulemod_operand_value'][$operator_key]));
				}
			}
			
			if (is_array($conditions_arr)) {
				$condition_script = "(";
				foreach ($conditions_arr as $condition_obj) {
					if (CoreUtils::operatorById($condition_obj['operator']) == 'Contains') {
						$condition_script .= "(input.indexOf('".$condition_obj['operand']."') != -1) && ";
					} elseif (CoreUtils::operatorById($condition_obj['operator']) == 'Does Not Contain') {
						$condition_script .= "(input.indexOf('".$condition_obj['operand']."') == -1) && ";
					} else {
						$condition_script .= "(input ".CoreUtils::operatorById($condition_obj['operator']) . " " . ((is_numeric($condition_obj['operand']) || (stripos($condition_obj['operand'], ':') !== false)) ? str_replace('1:', '', $condition_obj['operand']) : "'".$condition_obj['operand']."'" ) . ") && ";
					}
				}
				$condition_script = substr($condition_script, 0, strlen($condition_script)-4);
				$condition_script .= ")";
			} else {
				\Udoh\Emsa\Utils\DisplayUtils::drawError("Unable to add new Child Result Interpretive Rule.  No conditions found for specified rule.");
			}
		} else {
			\Udoh\Emsa\Utils\DisplayUtils::drawError("Unable to add new Child Result Interpretive Rule.  Missing operator or test value for one or more conditions.");
		}
		
		if (isset($conditions_arr) && isset($condition_script)) {
			$rulemod_edit_sql = sprintf("UPDATE %svocab_c2m_testresult SET 
				conditions_structured = '%s', conditions_js = '%s', master_id = %d, app_id = %d, results_to_comments = %s 
				WHERE id = %d;",
				$emsaDbSchemaPrefix,
				@pg_escape_string(json_encode($conditions_arr)),
				@pg_escape_string($condition_script),
				$clean_rulemod['rulemod_master_result'],
				$clean_rulemod['rulemod_application'],
				$clean_rulemod['rulemod_comments'],
				$clean_rulemod['id']);
			$rulemod_edit_rs = @pg_query($host_pa, $rulemod_edit_sql);
			if ($rulemod_edit_rs) {
				\Udoh\Emsa\Utils\DisplayUtils::drawHighlight("Child Result Interpretive Rule successfully updated!", "ui-icon-elrsuccess");
				
				$va_resultrule_prepared_new_vals = $va->prepareNewValues(VocabAudit::TABLE_CHILD_TESTRESULT, array(
					'app_id' => $_GET['rulemod_application'],
					'conditions' => $va->verboseInterpRuleConditions($condition_script),
					'test_result' => $_GET['rulemod_master_result'],
					'comments' => $_GET['rulemod_comments']
				));
				
				$va->resetAudit();
				$va->setOldVals($va_resultrule_prev_vals);
				$va->setNewVals($va_resultrule_prepared_new_vals);
				$va->auditVocab($va_resultrule_parentid, VocabAudit::TABLE_CHILD_TESTRESULT, VocabAudit::ACTION_EDIT);
			} else {
                \Udoh\Emsa\Utils\DisplayUtils::drawError("An error occurred while trying to edit an existing Child Result Interpretive Rule.");
			}
			@pg_free_result($rulemod_edit_rs);
		}
	}
	
?>
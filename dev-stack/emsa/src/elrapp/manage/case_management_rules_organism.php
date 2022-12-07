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
 * case_management_rules_organism.php
 *
 * Included in vocabulary.php to process adding, editing, or deleting
 * Master SNOMED (Organism)-based Case Management rules (triggered by detecting a
 * valid action specified in $_GET['rulemod_ms_cmr_action'] (add, edit, or delete).
 */

// sanitization
unset($clean_rulemod);
$clean_rulemod['action'] = trim($_GET['rulemod_ms_cmr_action']);     // already sanitized via in_array() on vocabulary.php
if (isset($_GET['rulemod_ms_cmr_id']) && (intval(trim($_GET['rulemod_ms_cmr_id'])) > 0)) {
    $clean_rulemod['id'] = intval(trim($_GET['rulemod_ms_cmr_id']));
}
$clean_rulemod['rulemod_cmr_application'] = ((isset($_GET['rulemod_ms_cmr_application']) && (intval(trim($_GET['rulemod_ms_cmr_application'])) > 0)) ? intval(trim($_GET['rulemod_ms_cmr_application'])) : 2);  // default to EpiTrax if none
$clean_rulemod['rulemod_state_case_status'] = ((isset($_GET['rulemod_state_case_status']) && (intval(trim($_GET['rulemod_state_case_status'])) > 0)) ? intval(trim($_GET['rulemod_state_case_status'])) : -1);
$clean_rulemod['rulemod_new_cmr'] = ((isset($_GET['rulemod_new_cmr']) && (strlen(trim($_GET['rulemod_new_cmr'])) > 0) && (trim($_GET['rulemod_new_cmr']) == 't')) ? "'t'" : "'f'");
$clean_rulemod['rulemod_update_cmr'] = ((isset($_GET['rulemod_update_cmr']) && (strlen(trim($_GET['rulemod_update_cmr'])) > 0) && (trim($_GET['rulemod_update_cmr']) == 't')) ? "'t'" : "'f'");
$clean_rulemod['rulemod_is_surveillance'] = ((isset($_GET['rulemod_is_surveillance']) && (strlen(trim($_GET['rulemod_is_surveillance'])) > 0) && (trim($_GET['rulemod_is_surveillance']) == 't')) ? "'t'" : "'f'");

// make sure valid ID was passed first, depending on whether we're adding or editing/deleting
$valid_id = false;
if (isset($clean_rulemod['id'])) {
    if ($clean_rulemod['action'] == "add") {
        // 'add' passes a master SNOMED id...
        $valid_id_sql = "SELECT count(o_id) AS counter FROM " . $emsaDbSchemaPrefix . "vocab_master_organism WHERE o_id = " . intval($clean_rulemod['id']) . ";";
    } else {
        // 'edit' & 'delete' both pass a rule ID...
        $valid_id_sql = "SELECT count(id) AS counter FROM " . $emsaDbSchemaPrefix . "vocab_rules_mastersnomed WHERE id = " . intval($clean_rulemod['id']) . ";";
    }
    $valid_id_rs = @pg_query($host_pa, $valid_id_sql);
    if ($valid_id_rs) {
        if (@pg_fetch_result($valid_id_rs, 0, 'counter') == 1) {
            $valid_id = true;
        }
    }
}

if (!$valid_id) {
    \Udoh\Emsa\Utils\DisplayUtils::drawError("Could not modify Master SNOMED Case Management Rules.  An invalid ID was specified.", true);
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
            isset($_GET['rulemod_ms_cmr_operator']) && isset($_GET['rulemod_ms_cmr_operand_value']) && is_array($_GET['rulemod_ms_cmr_operator']) && is_array($_GET['rulemod_ms_cmr_operand_value'])
    ) {
        foreach ($_GET['rulemod_ms_cmr_operator'] as $operator_key => $operator_value) {
            if (
                    isset($_GET['rulemod_ms_cmr_operand_value'][$operator_key]) && (strlen(trim($_GET['rulemod_ms_cmr_operand_value'][$operator_key])) > 0)
            ) {
                // operator and operand pairs exist, operands are non-empty...
                $conditions_arr[] = array("operator" => intval($operator_value), "operand" => filter_var(trim($_GET['rulemod_ms_cmr_operand_value'][$operator_key]), FILTER_SANITIZE_STRING));
            }
        }

        if (isset($conditions_arr) && is_array($conditions_arr)) {
            $condition_script = "(";
            foreach ($conditions_arr as $condition_obj) {
                $condition_script .= "(input " . CoreUtils::operatorById($condition_obj['operator']) . " " . ((is_numeric($condition_obj['operand']) || (stripos($condition_obj['operand'], ':') !== false)) ? str_replace('1:', '', $condition_obj['operand']) : "'" . $condition_obj['operand'] . "'" ) . ") && ";
            }
            $condition_script = substr($condition_script, 0, strlen($condition_script) - 4);
            $condition_script .= ")";
        } else {
            \Udoh\Emsa\Utils\DisplayUtils::drawError("Unable to add new Master SNOMED Case Management Rule.  No conditions found for specified rule.");
        }
    } else {
        \Udoh\Emsa\Utils\DisplayUtils::drawError("Unable to add new Master SNOMED Case Management Rule.  Missing operator or test result for one or more conditions.");
    }

    if (isset($conditions_arr) && isset($condition_script)) {
        $rulemod_add_sql = sprintf("INSERT INTO %svocab_rules_mastersnomed 
				(master_snomed_id, conditions_structured, conditions_js, state_case_status_master_id, app_id, allow_new_cmr, allow_update_cmr, is_surveillance) VALUES 
				(%d, '%s', '%s', %d, %d, %s, %s, %s);", $emsaDbSchemaPrefix, $clean_rulemod['id'], @pg_escape_string(json_encode($conditions_arr)), @pg_escape_string($condition_script), $clean_rulemod['rulemod_state_case_status'], $clean_rulemod['rulemod_cmr_application'], $clean_rulemod['rulemod_new_cmr'], $clean_rulemod['rulemod_update_cmr'], $clean_rulemod['rulemod_is_surveillance']
        );
        $rulemod_add_rs = @pg_query($host_pa, $rulemod_add_sql);
        if ($rulemod_add_rs) {
            \Udoh\Emsa\Utils\DisplayUtils::drawHighlight("New Master SNOMED Case Management Rule added successfully!", "ui-icon-elrsuccess");

            $va_cmrrule_prepared_new_vals = $va->prepareNewValues(VocabAudit::TABLE_MS_CMR_RULES, array(
                'app_id' => $_GET['rulemod_ms_cmr_application'],
                'conditions' => $va->verboseMsCmrConditions($condition_script),
                'new_cmr' => $_GET['rulemod_new_cmr'],
                'update_cmr' => $_GET['rulemod_update_cmr'],
                'surveillance' => $_GET['rulemod_is_surveillance'],
                'state_case_status_master_id' => $_GET['rulemod_state_case_status']
            ));

            $va->resetAudit();
            $va->setNewVals($va_cmrrule_prepared_new_vals);
            $va->auditVocab($clean_rulemod['id'], VocabAudit::TABLE_MS_CMR_RULES, VocabAudit::ACTION_ADD);
        } else {
            \Udoh\Emsa\Utils\DisplayUtils::drawError("An error occurred while trying to add a new Master SNOMED Case Management Rule.");
        }
        @pg_free_result($rulemod_add_rs);
    }
}


/*
 * Delete an existing rule
 */
if ($clean_rulemod['action'] == "delete") {
    $va_cmrrule_parentid = $va->getRuleParentId($clean_rulemod['id'], VocabAudit::TABLE_MS_CMR_RULES);
    $va_cmrrule_prev_vals = $va->getPreviousVals($clean_rulemod['id'], VocabAudit::TABLE_MS_CMR_RULES);
    // delete
    $rulemod_delete_sql = sprintf("DELETE FROM ONLY %svocab_rules_mastersnomed WHERE id = %d;", $emsaDbSchemaPrefix, $clean_rulemod['id']);
    $rulemod_delete_rs = @pg_query($host_pa, $rulemod_delete_sql);
    if ($rulemod_delete_rs) {
        \Udoh\Emsa\Utils\DisplayUtils::drawHighlight("Master SNOMED Case Management Rule successfully deleted!", "ui-icon-elrsuccess");

        $va->resetAudit();
        $va->setOldVals($va_cmrrule_prev_vals);
        $va->auditVocab($va_cmrrule_parentid, VocabAudit::TABLE_MS_CMR_RULES, VocabAudit::ACTION_DELETE);
    } else {
        \Udoh\Emsa\Utils\DisplayUtils::drawError("An error occurred while trying to delete an existing Master SNOMED Case Management Rule.");
    }
    @pg_free_result($rulemod_delete_rs);
}


/*
 * Edit an existing rule
 */
if ($clean_rulemod['action'] == "edit") {
    $va_cmrrule_parentid = $va->getRuleParentId($clean_rulemod['id'], VocabAudit::TABLE_MS_CMR_RULES);
    $va_cmrrule_prev_vals = $va->getPreviousVals($clean_rulemod['id'], VocabAudit::TABLE_MS_CMR_RULES);
    // edit
    unset($conditions_arr);
    unset($condition_script);

    // check if operator & operand arrays are set and same number of elements in each
    if (
            isset($_GET['rulemod_ms_cmr_operator']) && isset($_GET['rulemod_ms_cmr_operand_value']) && is_array($_GET['rulemod_ms_cmr_operator']) && is_array($_GET['rulemod_ms_cmr_operand_value'])
    ) {
        foreach ($_GET['rulemod_ms_cmr_operator'] as $operator_key => $operator_value) {
            if (
                    isset($_GET['rulemod_ms_cmr_operand_value'][$operator_key]) && (strlen(trim($_GET['rulemod_ms_cmr_operand_value'][$operator_key])) > 0)
            ) {
                // operator and operand pairs exist, operands are non-empty...
                $conditions_arr[] = array("operator" => intval($operator_value), "operand" => filter_var(trim($_GET['rulemod_ms_cmr_operand_value'][$operator_key]), FILTER_SANITIZE_STRING));
            }
        }

        if (is_array($conditions_arr)) {
            $condition_script = "(";
            foreach ($conditions_arr as $condition_obj) {
                $condition_script .= "(input " . CoreUtils::operatorById($condition_obj['operator']) . " " . ((is_numeric($condition_obj['operand']) || (stripos($condition_obj['operand'], ':') !== false)) ? str_replace('1:', '', $condition_obj['operand']) : "'" . $condition_obj['operand'] . "'" ) . ") && ";
            }
            $condition_script = substr($condition_script, 0, strlen($condition_script) - 4);
            $condition_script .= ")";
        } else {
            \Udoh\Emsa\Utils\DisplayUtils::drawError("Unable to add new Master SNOMED Case Management Rule.  No conditions found for specified rule.");
        }
    } else {
        \Udoh\Emsa\Utils\DisplayUtils::drawError("Unable to add new Master SNOMED Case Management Rule.  Missing operator or test result for one or more conditions.");
    }

    if (isset($conditions_arr) && isset($condition_script)) {
        $rulemod_edit_sql = sprintf("UPDATE %svocab_rules_mastersnomed SET 
				conditions_structured = '%s', conditions_js = '%s', state_case_status_master_id = %d, app_id = %d, allow_new_cmr = %s, allow_update_cmr = %s, is_surveillance = %s 
				WHERE id = %d;", $emsaDbSchemaPrefix, @pg_escape_string(json_encode($conditions_arr)), @pg_escape_string($condition_script), $clean_rulemod['rulemod_state_case_status'], $clean_rulemod['rulemod_cmr_application'], $clean_rulemod['rulemod_new_cmr'], $clean_rulemod['rulemod_update_cmr'], $clean_rulemod['rulemod_is_surveillance'], $clean_rulemod['id']);
        $rulemod_edit_rs = @pg_query($host_pa, $rulemod_edit_sql);
        if ($rulemod_edit_rs) {
            \Udoh\Emsa\Utils\DisplayUtils::drawHighlight("Master SNOMED Case Management Rule successfully updated!", "ui-icon-elrsuccess");


            $va_cmrrule_prepared_new_vals = $va->prepareNewValues(VocabAudit::TABLE_MS_CMR_RULES, array(
                'app_id' => (isset($_GET['rulemod_ms_cmr_application'])) ? intval($_GET['rulemod_ms_cmr_application']) : 1,
                'conditions' => $va->verboseMsCmrConditions($condition_script),
                'new_cmr' => (isset($_GET['rulemod_new_cmr']) && trim($_GET['rulemod_new_cmr']) == 't') ? 't' : 'f',
                'update_cmr' => (isset($_GET['rulemod_update_cmr']) && trim($_GET['rulemod_update_cmr']) == 't') ? 't' : 'f',
                'surveillance' => (isset($_GET['rulemod_is_surveillance']) && trim($_GET['rulemod_is_surveillance']) == 't') ? 't' : 'f',
                'state_case_status_master_id' => (isset($_GET['rulemod_state_case_status']) && intval($_GET['rulemod_state_case_status']) > 0) ? intval($_GET['rulemod_state_case_status']) : -1
            ));

            $va->resetAudit();
            $va->setOldVals($va_cmrrule_prev_vals);
            $va->setNewVals($va_cmrrule_prepared_new_vals);
            $va->auditVocab($va_cmrrule_parentid, VocabAudit::TABLE_MS_CMR_RULES, VocabAudit::ACTION_EDIT);
        } else {
            \Udoh\Emsa\Utils\DisplayUtils::drawError("An error occurred while trying to edit an existing Master SNOMED Case Management Rule.");
        }
        @pg_free_result($rulemod_edit_rs);
    }
}
?>
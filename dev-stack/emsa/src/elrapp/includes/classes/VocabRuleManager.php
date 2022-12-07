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

/**
 * Utilities for adding, editing, and deleting vocabulary rules (such as Graylist Rules, Case Management Rules, etc.)
 *
 * @author Josh Ridderhoff <jridderhoff@utah.gov>
 */
class VocabRuleManager
{

    /** @var \PDO */
    protected $dbConn;

    /** @var \VocabAudit */
    protected $va;

    /**
     * Create a new Vocabulary Rules Manager object.
     * 
     * @param \PDO $dbConn PDO connection to the active EMSA database.
     * @param \Udoh\Emsa\Client\AppClientInterface $authClient Application client used for authentication.
     */
    public function __construct(\PDO $dbConn, \Udoh\Emsa\Client\AppClientInterface $authClient)
    {
        $this->dbConn = $dbConn;
        $this->va = new \VocabAudit($this->dbConn, $authClient);
    }

    final public function addLoincCaseManagementRule(array $params)
    {
        $cleanParams = new stdClass();
        $conditionsArr = array();
        $conditionScript = null;

        $editPkg = $params['editPkg'];
        $cleanParams->parentId = intval($editPkg['focus_id']);
        $cleanParams->rulemod_cmr_application = ((isset($editPkg['rulemod_cmr_application']) && (intval(trim($editPkg['rulemod_cmr_application'])) > 0)) ? intval(trim($editPkg['rulemod_cmr_application'])) : 2);  // default to EpiTrax if none
        $cleanParams->rulemod_state_case_status = ((isset($editPkg['rulemod_state_case_status']) && (intval(trim($editPkg['rulemod_state_case_status'])) > 0)) ? intval(trim($editPkg['rulemod_state_case_status'])) : -1);
        $cleanParams->rulemod_new_cmr = ((isset($editPkg['rulemod_new_cmr']) && (strlen(trim($editPkg['rulemod_new_cmr'])) > 0) && (trim($editPkg['rulemod_new_cmr']) == 't')) ? true : false);
        $cleanParams->rulemod_update_cmr = ((isset($editPkg['rulemod_update_cmr']) && (strlen(trim($editPkg['rulemod_update_cmr'])) > 0) && (trim($editPkg['rulemod_update_cmr']) == 't')) ? true : false);
        $cleanParams->rulemod_is_surveillance = ((isset($editPkg['rulemod_is_surveillance']) && (strlen(trim($editPkg['rulemod_is_surveillance'])) > 0) && (trim($editPkg['rulemod_is_surveillance']) == 't')) ? true : false);

        // check if operator & operand arrays are set and same number of elements in each
        if (isset($editPkg['rulemod_cmr_operator']) && isset($editPkg['rulemod_cmr_operand_value']) && is_array($editPkg['rulemod_cmr_operator']) && is_array($editPkg['rulemod_cmr_operand_value'])) {
            foreach ($editPkg['rulemod_cmr_operator'] as $operatorKey => $operatorValue) {
                if (isset($editPkg['rulemod_cmr_operand_value'][$operatorKey]) && (strlen(trim($editPkg['rulemod_cmr_operand_value'][$operatorKey])) > 0)) {
                    // operator and operand pair exists, operand is non-empty...
                    $conditionsArr[] = array("operator" => intval($operatorValue), "operand" => filter_var(trim($editPkg['rulemod_cmr_operand_value'][$operatorKey]), FILTER_SANITIZE_STRING));
                }
            }

            if (is_array($conditionsArr)) {
                $conditionScript = "(";
                foreach ($conditionsArr as $conditionObj) {
                    $conditionScript .= "(input " . CoreUtils::operatorById($conditionObj['operator']) . " " . ((is_numeric($conditionObj['operand']) || (stripos($conditionObj['operand'], ':') !== false)) ? str_replace('1:', '', $conditionObj['operand']) : "'" . $conditionObj['operand'] . "'" ) . ") && ";
                }
                $conditionScript = substr($conditionScript, 0, strlen($conditionScript) - 4);
                $conditionScript .= ")";
            } else {
                throw new Exception("Unable to add new Master LOINC Case Management Rule.  No conditions found for specified rule.");
            }
        } else {
            throw new Exception("Unable to add new Master LOINC Case Management Rule.  Missing operator or test value for one or more conditions.");
        }

        if (isset($conditionsArr) && isset($conditionScript)) {
            $sql = 'INSERT INTO vocab_rules_masterloinc 
						(master_loinc_id, conditions_structured, conditions_js, state_case_status_master_id, app_id, allow_new_cmr, allow_update_cmr, is_surveillance) 
					VALUES 
						(:masterLoincId, :conditionsStructured, :conditionsJs, :statusId, :appId, :allowNewCmr, :allowUpdateCmr, :isSurveillance);';
            $stmt = $this->dbConn->prepare($sql);
            $stmt->bindValue(':masterLoincId', $cleanParams->parentId, PDO::PARAM_INT);
            $stmt->bindValue(':conditionsStructured', json_encode($conditionsArr), PDO::PARAM_STR);
            $stmt->bindValue(':conditionsJs', $conditionScript, PDO::PARAM_STR);
            $stmt->bindValue(':statusId', $cleanParams->rulemod_state_case_status, PDO::PARAM_INT);
            $stmt->bindValue(':appId', $cleanParams->rulemod_cmr_application, PDO::PARAM_INT);
            $stmt->bindValue(':allowNewCmr', $cleanParams->rulemod_new_cmr, PDO::PARAM_BOOL);
            $stmt->bindValue(':allowUpdateCmr', $cleanParams->rulemod_update_cmr, PDO::PARAM_BOOL);
            $stmt->bindValue(':isSurveillance', $cleanParams->rulemod_is_surveillance, PDO::PARAM_BOOL);

            if ($stmt->execute()) {
                $va_cmrrule_prepared_new_vals = $this->va->prepareNewValues(VocabAudit::TABLE_CMR_RULES, array(
                    'app_id' => $editPkg['rulemod_cmr_application'],
                    'conditions' => $this->va->verboseCmrConditions($conditionScript),
                    'new_cmr' => $editPkg['rulemod_new_cmr'],
                    'update_cmr' => $editPkg['rulemod_update_cmr'],
                    'surveillance' => $editPkg['rulemod_is_surveillance'],
                    'state_case_status_master_id' => $editPkg['rulemod_state_case_status']
                ));

                $this->va->resetAudit();
                $this->va->setNewVals($va_cmrrule_prepared_new_vals);
                $this->va->auditVocab($cleanParams->parentId, VocabAudit::TABLE_CMR_RULES, VocabAudit::ACTION_ADD);
            } else {
                throw new Exception("An error occurred while trying to add a new Master LOINC Case Management Rule.", 1);
            }
        }

        return 'New Master LOINC Case Management Rule added successfully!';
    }

    final public function addSnomedCaseManagementRule(array $params)
    {
        $cleanParams = new stdClass();
        $conditionsArr = array();
        $conditionScript = null;

        $editPkg = $params['editPkg'];
        $cleanParams->parentId = intval($editPkg['focus_id']);
        $cleanParams->rulemod_ms_cmr_application = ((isset($editPkg['rulemod_ms_cmr_application']) && (intval(trim($editPkg['rulemod_ms_cmr_application'])) > 0)) ? intval(trim($editPkg['rulemod_ms_cmr_application'])) : 2);  // default to EpiTrax if none
        $cleanParams->rulemod_state_case_status = ((isset($editPkg['rulemod_state_case_status']) && (intval(trim($editPkg['rulemod_state_case_status'])) > 0)) ? intval(trim($editPkg['rulemod_state_case_status'])) : -1);
        $cleanParams->rulemod_new_cmr = ((isset($editPkg['rulemod_new_cmr']) && (strlen(trim($editPkg['rulemod_new_cmr'])) > 0) && (trim($editPkg['rulemod_new_cmr']) == 't')) ? true : false);
        $cleanParams->rulemod_update_cmr = ((isset($editPkg['rulemod_update_cmr']) && (strlen(trim($editPkg['rulemod_update_cmr'])) > 0) && (trim($editPkg['rulemod_update_cmr']) == 't')) ? true : false);
        $cleanParams->rulemod_is_surveillance = ((isset($editPkg['rulemod_is_surveillance']) && (strlen(trim($editPkg['rulemod_is_surveillance'])) > 0) && (trim($editPkg['rulemod_is_surveillance']) == 't')) ? true : false);

        // check if operator & operand arrays are set and same number of elements in each
        if (isset($editPkg['rulemod_ms_cmr_operator']) && isset($editPkg['rulemod_ms_cmr_operand_value']) && is_array($editPkg['rulemod_ms_cmr_operator']) && is_array($editPkg['rulemod_ms_cmr_operand_value'])) {
            foreach ($editPkg['rulemod_ms_cmr_operator'] as $operatorKey => $operatorValue) {
                if (isset($editPkg['rulemod_ms_cmr_operand_value'][$operatorKey]) && (strlen(trim($editPkg['rulemod_ms_cmr_operand_value'][$operatorKey])) > 0)) {
                    // operator and operand pair exists, operand is non-empty...
                    $conditionsArr[] = array("operator" => intval($operatorValue), "operand" => filter_var(trim($editPkg['rulemod_ms_cmr_operand_value'][$operatorKey]), FILTER_SANITIZE_STRING));
                }
            }

            if (is_array($conditionsArr)) {
                $conditionScript = "(";
                foreach ($conditionsArr as $conditionObj) {
                    $conditionScript .= "(input " . CoreUtils::operatorById($conditionObj['operator']) . " " . ((is_numeric($conditionObj['operand']) || (stripos($conditionObj['operand'], ':') !== false)) ? str_replace('1:', '', $conditionObj['operand']) : "'" . $conditionObj['operand'] . "'" ) . ") && ";
                }
                $conditionScript = substr($conditionScript, 0, strlen($conditionScript) - 4);
                $conditionScript .= ")";
            } else {
                throw new Exception("Unable to add new Master SNOMED Case Management Rule.  No conditions found for specified rule.");
            }
        } else {
            throw new Exception("Unable to add new Master SNOMED Case Management Rule.  Missing operator or test value for one or more conditions.");
        }

        if (isset($conditionsArr) && isset($conditionScript)) {
            $sql = 'INSERT INTO vocab_rules_mastersnomed 
						(master_snomed_id, conditions_structured, conditions_js, state_case_status_master_id, app_id, allow_new_cmr, allow_update_cmr, is_surveillance) 
					VALUES 
						(:masterSnomedId, :conditionsStructured, :conditionsJs, :statusId, :appId, :allowNewCmr, :allowUpdateCmr, :isSurveillance);';
            $stmt = $this->dbConn->prepare($sql);
            $stmt->bindValue(':masterSnomedId', $cleanParams->parentId, PDO::PARAM_INT);
            $stmt->bindValue(':conditionsStructured', json_encode($conditionsArr), PDO::PARAM_STR);
            $stmt->bindValue(':conditionsJs', $conditionScript, PDO::PARAM_STR);
            $stmt->bindValue(':statusId', $cleanParams->rulemod_state_case_status, PDO::PARAM_INT);
            $stmt->bindValue(':appId', $cleanParams->rulemod_ms_cmr_application, PDO::PARAM_INT);
            $stmt->bindValue(':allowNewCmr', $cleanParams->rulemod_new_cmr, PDO::PARAM_BOOL);
            $stmt->bindValue(':allowUpdateCmr', $cleanParams->rulemod_update_cmr, PDO::PARAM_BOOL);
            $stmt->bindValue(':isSurveillance', $cleanParams->rulemod_is_surveillance, PDO::PARAM_BOOL);

            if ($stmt->execute()) {
                $va_cmrrule_prepared_new_vals = $this->va->prepareNewValues(VocabAudit::TABLE_MS_CMR_RULES, array(
                    'app_id' => $editPkg['rulemod_ms_cmr_application'],
                    'conditions' => $this->va->verboseMsCmrConditions($conditionScript),
                    'new_cmr' => $editPkg['rulemod_new_cmr'],
                    'update_cmr' => $editPkg['rulemod_update_cmr'],
                    'surveillance' => $editPkg['rulemod_is_surveillance'],
                    'state_case_status_master_id' => $editPkg['rulemod_state_case_status']
                ));

                $this->va->resetAudit();
                $this->va->setNewVals($va_cmrrule_prepared_new_vals);
                $this->va->auditVocab($cleanParams->parentId, VocabAudit::TABLE_MS_CMR_RULES, VocabAudit::ACTION_ADD);
            } else {
                throw new Exception("An error occurred while trying to add a new Master SNOMED Case Management Rule.", 1);
            }
        }

        return 'New Master SNOMED Case Management Rule added successfully!';
    }

    final public function addGraylistRule(array $params)
    {
        $cleanParams = new stdClass();
        $conditionsArr = array();

        $editPkg = $params['editPkg'];
        $cleanParams->parentId = intval($editPkg['focus_id']);
        $cleanParams->rulemod_cmr_application = ((isset($editPkg['rulemod_gray_application']) && (intval(trim($editPkg['rulemod_gray_application'])) > 0)) ? intval(trim($editPkg['rulemod_gray_application'])) : 2);  // default to EpiTrax if none

        if (!\Udoh\Emsa\Utils\DateTimeUtils::validateDateTimeString($editPkg['rulemod_gray_collect_lbound']) || !\Udoh\Emsa\Utils\DateTimeUtils::validateDateTimeString($editPkg['rulemod_gray_collect_ubound'])) {
            throw new Exception('Could not add Graylist Rule:<br><br>Collection Date Range does not contain a valid time interval.');
        }

        // check if operator & operand arrays are set and same number of elements in each
        if (
                isset($editPkg['rulemod_gray_operator']) && isset($editPkg['rulemod_gray_operator1']) && isset($editPkg['rulemod_gray_operand_value']) && isset($editPkg['rulemod_gray_operand_value1']) && isset($editPkg['rulemod_gray_collect_lbound']) && isset($editPkg['rulemod_gray_collect_ubound'])
        ) {

            if (
                    (strlen(trim($editPkg['rulemod_gray_operand_value'])) > 0) && (strlen(trim($editPkg['rulemod_gray_operand_value1'])) > 0) && (strlen(trim($editPkg['rulemod_gray_collect_lbound'])) > 0) && (strlen(trim($editPkg['rulemod_gray_collect_ubound'])) > 0)
            ) {
                // operator and operand pairs exist, operands are non-empty...
                $conditionsArr[] = array(
                    "operator" => intval($editPkg['rulemod_gray_operator']),
                    "operand" => filter_var(trim($editPkg['rulemod_gray_operand_value']), FILTER_SANITIZE_STRING),
                    "operator1" => intval($editPkg['rulemod_gray_operator1']),
                    "operand1" => filter_var(trim($editPkg['rulemod_gray_operand_value1']), FILTER_SANITIZE_STRING),
                    "collect_lbound" => filter_var(trim($editPkg['rulemod_gray_collect_lbound']), FILTER_SANITIZE_STRING),
                    "collect_ubound" => filter_var(trim($editPkg['rulemod_gray_collect_ubound']), FILTER_SANITIZE_STRING),
                );
            }

            if (!is_array($conditionsArr)) {
                throw new Exception("Unable to add new Graylist Rule.  No conditions found for specified rule.");
            }
        } else {
            throw new Exception("Unable to add new Graylist Rule.  Missing operator or operand for one or more conditions.");
        }

        if (isset($conditionsArr)) {
            $sql = 'INSERT INTO vocab_rules_graylist 
						(master_condition_id, conditions_structured, app_id) 
					VALUES 
						(:masterConditionId, :conditionsStructured, :appId);';
            $stmt = $this->dbConn->prepare($sql);
            $stmt->bindValue(':masterConditionId', $cleanParams->parentId, PDO::PARAM_INT);
            $stmt->bindValue(':conditionsStructured', json_encode($conditionsArr), PDO::PARAM_STR);
            $stmt->bindValue(':appId', $cleanParams->rulemod_cmr_application, PDO::PARAM_INT);

            if ($stmt->execute()) {
                $va_prepared_new_vals = $this->va->prepareNewValues(VocabAudit::TABLE_GRAYLIST_RULES, array(
                    'app_id' => $editPkg['rulemod_gray_application'],
                    'conditions' => $this->va->verboseGraylistConditions($conditionsArr)
                ));

                $this->va->resetAudit();
                $this->va->setNewVals($va_prepared_new_vals);
                $this->va->auditVocab($cleanParams->parentId, VocabAudit::TABLE_GRAYLIST_RULES, VocabAudit::ACTION_ADD);
            } else {
                throw new Exception("An error occurred while trying to add a new Graylist Rule.", 1);
            }
        }

        return 'New Graylist Rule added successfully!';
    }

    final public function editLoincCaseManagementRule(array $params)
    {
        $cleanParams = new stdClass();
        $conditionsArr = array();
        $conditionScript = null;

        $editPkg = $params['editPkg'];
        $cleanParams->parentId = intval($editPkg['focus_id']);
        $cleanParams->ruleId = intval($editPkg['rulemod_cmr_id']);
        $cleanParams->rulemod_cmr_application = ((isset($editPkg['rulemod_cmr_application']) && (intval(trim($editPkg['rulemod_cmr_application'])) > 0)) ? intval(trim($editPkg['rulemod_cmr_application'])) : 2);  // default to EpiTrax if none
        $cleanParams->rulemod_state_case_status = ((isset($editPkg['rulemod_state_case_status']) && (intval(trim($editPkg['rulemod_state_case_status'])) > 0)) ? intval(trim($editPkg['rulemod_state_case_status'])) : -1);
        $cleanParams->rulemod_new_cmr = ((isset($editPkg['rulemod_new_cmr']) && (strlen(trim($editPkg['rulemod_new_cmr'])) > 0) && (trim($editPkg['rulemod_new_cmr']) == 't')) ? true : false);
        $cleanParams->rulemod_update_cmr = ((isset($editPkg['rulemod_update_cmr']) && (strlen(trim($editPkg['rulemod_update_cmr'])) > 0) && (trim($editPkg['rulemod_update_cmr']) == 't')) ? true : false);
        $cleanParams->rulemod_is_surveillance = ((isset($editPkg['rulemod_is_surveillance']) && (strlen(trim($editPkg['rulemod_is_surveillance'])) > 0) && (trim($editPkg['rulemod_is_surveillance']) == 't')) ? true : false);

        $va_prev_vals = $this->va->getPreviousVals($cleanParams->ruleId, VocabAudit::TABLE_CMR_RULES);

        // check if operator & operand arrays are set and same number of elements in each
        if (isset($editPkg['rulemod_cmr_operator']) && isset($editPkg['rulemod_cmr_operand_value']) && is_array($editPkg['rulemod_cmr_operator']) && is_array($editPkg['rulemod_cmr_operand_value'])) {
            foreach ($editPkg['rulemod_cmr_operator'] as $operatorKey => $operatorValue) {
                if (isset($editPkg['rulemod_cmr_operand_value'][$operatorKey]) && (strlen(trim($editPkg['rulemod_cmr_operand_value'][$operatorKey])) > 0)) {
                    // operator and operand pair exists, operand is non-empty...
                    $conditionsArr[] = array("operator" => intval($operatorValue), "operand" => filter_var(trim($editPkg['rulemod_cmr_operand_value'][$operatorKey]), FILTER_SANITIZE_STRING));
                }
            }

            if (is_array($conditionsArr)) {
                $conditionScript = "(";
                foreach ($conditionsArr as $conditionObj) {
                    $conditionScript .= "(input " . CoreUtils::operatorById($conditionObj['operator']) . " " . ((is_numeric($conditionObj['operand']) || (stripos($conditionObj['operand'], ':') !== false)) ? str_replace('1:', '', $conditionObj['operand']) : "'" . $conditionObj['operand'] . "'" ) . ") && ";
                }
                $conditionScript = substr($conditionScript, 0, strlen($conditionScript) - 4);
                $conditionScript .= ")";
            } else {
                throw new Exception("Unable to update Master LOINC Case Management Rule.  No conditions found for specified rule.");
            }
        } else {
            throw new Exception("Unable to update Master LOINC Case Management Rule.  Missing operator or test value for one or more conditions.");
        }

        if (isset($conditionsArr) && isset($conditionScript)) {
            $sql = 'UPDATE vocab_rules_masterloinc 
					SET conditions_structured = :conditionsStructured, 
						conditions_js = :conditionsJs, 
						state_case_status_master_id = :statusId, 
						app_id = :appId, 
						allow_new_cmr = :allowNewCmr, 
						allow_update_cmr = :allowUpdateCmr, 
						is_surveillance = :isSurveillance 
					WHERE id = :ruleId;';
            $stmt = $this->dbConn->prepare($sql);
            $stmt->bindValue(':conditionsStructured', json_encode($conditionsArr), PDO::PARAM_STR);
            $stmt->bindValue(':conditionsJs', $conditionScript, PDO::PARAM_STR);
            $stmt->bindValue(':statusId', $cleanParams->rulemod_state_case_status, PDO::PARAM_INT);
            $stmt->bindValue(':appId', $cleanParams->rulemod_cmr_application, PDO::PARAM_INT);
            $stmt->bindValue(':allowNewCmr', $cleanParams->rulemod_new_cmr, PDO::PARAM_BOOL);
            $stmt->bindValue(':allowUpdateCmr', $cleanParams->rulemod_update_cmr, PDO::PARAM_BOOL);
            $stmt->bindValue(':isSurveillance', $cleanParams->rulemod_is_surveillance, PDO::PARAM_BOOL);
            $stmt->bindValue(':ruleId', $cleanParams->ruleId, PDO::PARAM_INT);

            if ($stmt->execute()) {
                $va_prepared_new_vals = $this->va->prepareNewValues(VocabAudit::TABLE_CMR_RULES, array(
                    'app_id' => $editPkg['rulemod_cmr_application'],
                    'conditions' => $this->va->verboseCmrConditions($conditionScript),
                    'new_cmr' => $editPkg['rulemod_new_cmr'],
                    'update_cmr' => $editPkg['rulemod_update_cmr'],
                    'surveillance' => $editPkg['rulemod_is_surveillance'],
                    'state_case_status_master_id' => $editPkg['rulemod_state_case_status']
                ));

                $this->va->resetAudit();
                $this->va->setOldVals($va_prev_vals);
                $this->va->setNewVals($va_prepared_new_vals);
                $this->va->auditVocab($cleanParams->parentId, VocabAudit::TABLE_CMR_RULES, VocabAudit::ACTION_EDIT);
            } else {
                throw new Exception("An error occurred while trying to edit an existing Master LOINC Case Management Rule.", 1);
            }
        }

        return 'Master LOINC Case Management Rule successfully updated!';
    }

    final public function editSnomedCaseManagementRule(array $params)
    {
        $cleanParams = new stdClass();
        $conditionsArr = array();
        $conditionScript = null;

        $editPkg = $params['editPkg'];
        $cleanParams->parentId = intval($editPkg['focus_id']);
        $cleanParams->ruleId = intval($editPkg['rulemod_ms_cmr_id']);
        $cleanParams->rulemod_ms_cmr_application = ((isset($editPkg['rulemod_ms_cmr_application']) && (intval(trim($editPkg['rulemod_ms_cmr_application'])) > 0)) ? intval(trim($editPkg['rulemod_ms_cmr_application'])) : 2);  // default to EpiTrax if none
        $cleanParams->rulemod_state_case_status = ((isset($editPkg['rulemod_state_case_status']) && (intval(trim($editPkg['rulemod_state_case_status'])) > 0)) ? intval(trim($editPkg['rulemod_state_case_status'])) : -1);
        $cleanParams->rulemod_new_cmr = ((isset($editPkg['rulemod_new_cmr']) && (strlen(trim($editPkg['rulemod_new_cmr'])) > 0) && (trim($editPkg['rulemod_new_cmr']) == 't')) ? true : false);
        $cleanParams->rulemod_update_cmr = ((isset($editPkg['rulemod_update_cmr']) && (strlen(trim($editPkg['rulemod_update_cmr'])) > 0) && (trim($editPkg['rulemod_update_cmr']) == 't')) ? true : false);
        $cleanParams->rulemod_is_surveillance = ((isset($editPkg['rulemod_is_surveillance']) && (strlen(trim($editPkg['rulemod_is_surveillance'])) > 0) && (trim($editPkg['rulemod_is_surveillance']) == 't')) ? true : false);

        $va_prev_vals = $this->va->getPreviousVals($cleanParams->ruleId, VocabAudit::TABLE_MS_CMR_RULES);

        // check if operator & operand arrays are set and same number of elements in each
        if (isset($editPkg['rulemod_ms_cmr_operator']) && isset($editPkg['rulemod_ms_cmr_operand_value']) && is_array($editPkg['rulemod_ms_cmr_operator']) && is_array($editPkg['rulemod_ms_cmr_operand_value'])) {
            foreach ($editPkg['rulemod_ms_cmr_operator'] as $operatorKey => $operatorValue) {
                if (isset($editPkg['rulemod_ms_cmr_operand_value'][$operatorKey]) && (strlen(trim($editPkg['rulemod_ms_cmr_operand_value'][$operatorKey])) > 0)) {
                    // operator and operand pair exists, operand is non-empty...
                    $conditionsArr[] = array("operator" => intval($operatorValue), "operand" => filter_var(trim($editPkg['rulemod_ms_cmr_operand_value'][$operatorKey]), FILTER_SANITIZE_STRING));
                }
            }

            if (is_array($conditionsArr)) {
                $conditionScript = "(";
                foreach ($conditionsArr as $conditionObj) {
                    $conditionScript .= "(input " . CoreUtils::operatorById($conditionObj['operator']) . " " . ((is_numeric($conditionObj['operand']) || (stripos($conditionObj['operand'], ':') !== false)) ? str_replace('1:', '', $conditionObj['operand']) : "'" . $conditionObj['operand'] . "'" ) . ") && ";
                }
                $conditionScript = substr($conditionScript, 0, strlen($conditionScript) - 4);
                $conditionScript .= ")";
            } else {
                throw new Exception("Unable to update Master SNOMED Case Management Rule.  No conditions found for specified rule.");
            }
        } else {
            throw new Exception("Unable to update Master SNOMED Case Management Rule.  Missing operator or test value for one or more conditions.");
        }

        if (isset($conditionsArr) && isset($conditionScript)) {
            $sql = 'UPDATE vocab_rules_mastersnomed 
					SET conditions_structured = :conditionsStructured, 
						conditions_js = :conditionsJs, 
						state_case_status_master_id = :statusId, 
						app_id = :appId, 
						allow_new_cmr = :allowNewCmr, 
						allow_update_cmr = :allowUpdateCmr, 
						is_surveillance = :isSurveillance 
					WHERE id = :ruleId;';
            $stmt = $this->dbConn->prepare($sql);
            $stmt->bindValue(':conditionsStructured', json_encode($conditionsArr), PDO::PARAM_STR);
            $stmt->bindValue(':conditionsJs', $conditionScript, PDO::PARAM_STR);
            $stmt->bindValue(':statusId', $cleanParams->rulemod_state_case_status, PDO::PARAM_INT);
            $stmt->bindValue(':appId', $cleanParams->rulemod_ms_cmr_application, PDO::PARAM_INT);
            $stmt->bindValue(':allowNewCmr', $cleanParams->rulemod_new_cmr, PDO::PARAM_BOOL);
            $stmt->bindValue(':allowUpdateCmr', $cleanParams->rulemod_update_cmr, PDO::PARAM_BOOL);
            $stmt->bindValue(':isSurveillance', $cleanParams->rulemod_is_surveillance, PDO::PARAM_BOOL);
            $stmt->bindValue(':ruleId', $cleanParams->ruleId, PDO::PARAM_INT);

            if ($stmt->execute()) {
                $va_prepared_new_vals = $this->va->prepareNewValues(VocabAudit::TABLE_MS_CMR_RULES, array(
                    'app_id' => $editPkg['rulemod_ms_cmr_application'],
                    'conditions' => $this->va->verboseMsCmrConditions($conditionScript),
                    'new_cmr' => $editPkg['rulemod_new_cmr'],
                    'update_cmr' => $editPkg['rulemod_update_cmr'],
                    'surveillance' => $editPkg['rulemod_is_surveillance'],
                    'state_case_status_master_id' => $editPkg['rulemod_state_case_status']
                ));

                $this->va->resetAudit();
                $this->va->setOldVals($va_prev_vals);
                $this->va->setNewVals($va_prepared_new_vals);
                $this->va->auditVocab($cleanParams->parentId, VocabAudit::TABLE_MS_CMR_RULES, VocabAudit::ACTION_EDIT);
            } else {
                throw new Exception("An error occurred while trying to edit an existing Master SNOMED Case Management Rule.", 1);
            }
        }

        return 'Master SNOMED Case Management Rule successfully updated!';
    }

    final public function editGraylistRule(array $params)
    {
        $cleanParams = new stdClass();
        $conditionsArr = array();

        $editPkg = $params['editPkg'];
        $cleanParams->parentId = intval($editPkg['focus_id']);
        $cleanParams->ruleId = intval($editPkg['rulemod_gray_id']);
        $cleanParams->rulemod_gray_application = ((isset($editPkg['rulemod_gray_application']) && (intval(trim($editPkg['rulemod_gray_application'])) > 0)) ? intval(trim($editPkg['rulemod_gray_application'])) : 2);  // default to EpiTrax if none

        $va_prev_vals = $this->va->getPreviousVals($cleanParams->ruleId, VocabAudit::TABLE_GRAYLIST_RULES);

        if (!\Udoh\Emsa\Utils\DateTimeUtils::validateDateTimeString($editPkg['rulemod_gray_collect_lbound']) || !\Udoh\Emsa\Utils\DateTimeUtils::validateDateTimeString($editPkg['rulemod_gray_collect_ubound'])) {
            throw new Exception('Could not update Graylist Rule:<br><br>Collection Date Range does not contain a valid time interval.');
        }

        // check if operator & operand arrays are set and same number of elements in each
        if (
                isset($editPkg['rulemod_gray_operator']) && isset($editPkg['rulemod_gray_operator1']) && isset($editPkg['rulemod_gray_operand_value']) && isset($editPkg['rulemod_gray_operand_value1']) && isset($editPkg['rulemod_gray_collect_lbound']) && isset($editPkg['rulemod_gray_collect_ubound'])
        ) {

            if (
                    (strlen(trim($editPkg['rulemod_gray_operand_value'])) > 0) && (strlen(trim($editPkg['rulemod_gray_operand_value1'])) > 0) && (strlen(trim($editPkg['rulemod_gray_collect_lbound'])) > 0) && (strlen(trim($editPkg['rulemod_gray_collect_ubound'])) > 0)
            ) {
                // operator and operand pairs exist, operands are non-empty...
                $conditionsArr[] = array(
                    "operator" => intval($editPkg['rulemod_gray_operator']),
                    "operand" => filter_var(trim($editPkg['rulemod_gray_operand_value']), FILTER_SANITIZE_STRING),
                    "operator1" => intval($editPkg['rulemod_gray_operator1']),
                    "operand1" => filter_var(trim($editPkg['rulemod_gray_operand_value1']), FILTER_SANITIZE_STRING),
                    "collect_lbound" => filter_var(trim($editPkg['rulemod_gray_collect_lbound']), FILTER_SANITIZE_STRING),
                    "collect_ubound" => filter_var(trim($editPkg['rulemod_gray_collect_ubound']), FILTER_SANITIZE_STRING),
                );
            }

            if (!is_array($conditionsArr)) {
                throw new Exception("Unable to update Graylist Rule.  No conditions found for specified rule.");
            }
        } else {
            throw new Exception("Unable to update Graylist Rule.  Missing operator or operand for one or more conditions.");
        }

        if (isset($conditionsArr)) {
            $sql = 'UPDATE vocab_rules_graylist 
					SET conditions_structured = :conditionsStructured, app_id = :appId 
					WHERE id = :ruleId;';
            $stmt = $this->dbConn->prepare($sql);
            $stmt->bindValue(':conditionsStructured', json_encode($conditionsArr), PDO::PARAM_STR);
            $stmt->bindValue(':appId', $cleanParams->rulemod_gray_application, PDO::PARAM_INT);
            $stmt->bindValue(':ruleId', $cleanParams->ruleId, PDO::PARAM_INT);

            if ($stmt->execute()) {
                $va_prepared_new_vals = $this->va->prepareNewValues(VocabAudit::TABLE_GRAYLIST_RULES, array(
                    'app_id' => $cleanParams->rulemod_gray_application,
                    'conditions' => $this->va->verboseGraylistConditions($conditionsArr)
                ));

                $this->va->resetAudit();
                $this->va->setOldVals($va_prev_vals);
                $this->va->setNewVals($va_prepared_new_vals);
                $this->va->auditVocab($cleanParams->parentId, VocabAudit::TABLE_GRAYLIST_RULES, VocabAudit::ACTION_EDIT);
            } else {
                throw new Exception("An error occurred while trying to update an existing Graylist Rule.", 1);
            }
        }

        return 'Graylist Rule successfully updated!';
    }

    final public function editWhitelistRule(array $params)
    {
        $cleanParams = new stdClass();

        $validCols = array(
            'white_rule',
            'contact_white_rule'
        );

        $editPkg = $params['editPkg'];
        $cleanParams->validColumn = in_array($editPkg['singlefield_col'], $validCols);
        $cleanParams->conditionId = intval($editPkg['singlefield_id']);
        $cleanParams->newVal = ((isset($editPkg['singlefield_val']) && (strlen(trim($editPkg['singlefield_val'])) > 0)) ? trim($editPkg['singlefield_val']) : null);
        $cleanParams->oldVal = ((isset($editPkg['singlefield_old']) && (strlen(trim($editPkg['singlefield_old'])) > 0)) ? trim($editPkg['singlefield_old']) : null);

        $oldVal = array(array(
                'col' => $editPkg['singlefield_col'],
                'val' => $cleanParams->oldVal
        ));
        $newVal = array(array(
                'col' => $editPkg['singlefield_col'],
                'val' => $cleanParams->newVal
        ));

        if ($cleanParams->validColumn) {
            try {
                $sql = 'UPDATE vocab_master_condition
						SET ' . trim($editPkg['singlefield_col']) . ' = :newWhitelistRule
						WHERE c_id = :conditionId;';
                $stmt = $this->dbConn->prepare($sql);
                $stmt->bindValue(':newWhitelistRule', $cleanParams->newVal, PDO::PARAM_STR);
                $stmt->bindValue(':conditionId', $cleanParams->conditionId, PDO::PARAM_INT);

                if ($stmt->execute()) {
                    $this->va->resetAudit();
                    $this->va->auditSingleFieldChange($cleanParams->conditionId, VocabAudit::TABLE_MASTER_CONDITION, VocabAudit::ACTION_EDIT, json_encode($oldVal), json_encode($newVal));
                } else {
                    throw new Exception("An error occurred while trying to update an existing Whitelist Rule.", 1);
                }
            } catch (PDOException $pe) {
                throw new Exception('Unspecified database error (' . trim($pe->getCode()) . ')');
            }
        } else {
            throw new Exception("Invalid column specified");
        }

        return 'Whitelist Rule updated successfully!';
    }

    final public function deleteLoincCaseManagementRule($ruleId, $parentId)
    {
        $va_prev_vals = $this->va->getPreviousVals(intval($ruleId), VocabAudit::TABLE_CMR_RULES);

        try {
            $sql = 'DELETE FROM ONLY vocab_rules_masterloinc
					WHERE id = :ruleId;';
            $stmt = $this->dbConn->prepare($sql);
            $stmt->bindValue(':ruleId', $ruleId, PDO::PARAM_INT);

            if ($stmt->execute()) {
                $this->va->resetAudit();
                $this->va->setOldVals($va_prev_vals);
                $this->va->auditVocab(intval($parentId), VocabAudit::TABLE_CMR_RULES, VocabAudit::ACTION_DELETE);
                return 'Master LOINC Case Management Rule successfully deleted!';
            } else {
                throw new Exception('An error occurred while trying to delete an existing Master LOINC Case Management Rule.');
            }
        } catch (PDOException $pe) {
            throw new Exception('Unspecified database error (' . trim($pe->getCode()) . ')');
        }
    }

    final public function deleteSnomedCaseManagementRule($ruleId, $parentId)
    {
        $va_prev_vals = $this->va->getPreviousVals(intval($ruleId), VocabAudit::TABLE_MS_CMR_RULES);

        try {
            $sql = 'DELETE FROM ONLY vocab_rules_mastersnomed
					WHERE id = :ruleId;';
            $stmt = $this->dbConn->prepare($sql);
            $stmt->bindValue(':ruleId', $ruleId, PDO::PARAM_INT);

            if ($stmt->execute()) {
                $this->va->resetAudit();
                $this->va->setOldVals($va_prev_vals);
                $this->va->auditVocab(intval($parentId), VocabAudit::TABLE_MS_CMR_RULES, VocabAudit::ACTION_DELETE);
                return 'Master SNOMED Case Management Rule successfully deleted!';
            } else {
                throw new Exception('An error occurred while trying to delete an existing Master SNOMED Case Management Rule.');
            }
        } catch (PDOException $pe) {
            throw new Exception('Unspecified database error (' . trim($pe->getCode()) . ')');
        }
    }

    final public function deleteGraylistRule($ruleId, $parentId)
    {
        $va_prev_vals = $this->va->getPreviousVals(intval($ruleId), VocabAudit::TABLE_GRAYLIST_RULES);

        try {
            $sql = 'DELETE FROM ONLY vocab_rules_graylist
					WHERE id = :ruleId;';
            $stmt = $this->dbConn->prepare($sql);
            $stmt->bindValue(':ruleId', $ruleId, PDO::PARAM_INT);

            if ($stmt->execute()) {
                $this->va->resetAudit();
                $this->va->setOldVals($va_prev_vals);
                $this->va->auditVocab(intval($parentId), VocabAudit::TABLE_GRAYLIST_RULES, VocabAudit::ACTION_DELETE);
                return 'Graylist Rule successfully deleted!';
            } else {
                throw new Exception('An error occurred while trying to delete an existing Graylist Rule.');
            }
        } catch (PDOException $pe) {
            throw new Exception('Unspecified database error (' . trim($pe->getCode()) . ')');
        }
    }

}

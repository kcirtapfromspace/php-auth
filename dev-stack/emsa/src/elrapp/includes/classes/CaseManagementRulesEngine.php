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

use Udoh\Emsa\Constants\MessageType;
use Udoh\Emsa\Utils\ExceptionUtils;
use Udoh\Emsa\Utils\VocabUtils;

/**
 * Case Management Rule Engine
 * 
 * Evaluates Case Management Rules for an EMSA Message and determines State Case Status, 
 * whether an event can create a new CMR, whether new events should be Surveillance events, etc.
 *
 * @author Josh Ridderhoff <jridderhoff@utah.gov>
 */
final class CaseManagementRulesEngine
{

    /** @var PDO */
    protected $dbConn;

    /** @var int */
    protected $allowNewCmr;

    /** @var int */
    protected $allowUpdateCmr;

    /** @var int */
    protected $isSurveillance;

    /** @var bool */
    protected $rulesFromSnomed = false;

    const CMR_YES = 1;
    const CMR_NO = 0;
    const CMR_ERR_NO_RULES_DEFINED_LOINC = -99;
    const CMR_ERR_NO_RULES_MATCHED = -98;
    const CMR_ERR_MULT_RULES_MATCHED = -97;
    const CMR_ERR_MULT_ORGS_MATCHED = -96; // prob not used in new OOP implementation
    const CMR_ERR_REQFIELD_LOINC = -95;
    const CMR_ERR_REQFIELD_TESTRESULT = -94;
    const CMR_ERR_NO_RULES_DEFINED_SNOMED = -93;
    const CMR_ERR_REQFIELD_DXCODE = -92;
    const CMR_ERR_NO_RULES_DEFINED_DXCODE = -91;

    /**
     * Create a new Case Management Rules Engine and evaluate rules for the specified message.
     * 
     * @param PDO $dbConn Connection to the EMSA database.
     * @param EmsaMessage $emsaMessage EMSA message being processed.
     */
    public function __construct(PDO $dbConn, EmsaMessage $emsaMessage)
    {
        $this->dbConn = $dbConn;
        $this->evaluateRules($emsaMessage);
    }

    private function getRulesForMasterOrganism($organismId, $appId)
    {
        $rules = array();

        $sql = 'SELECT allow_new_cmr, allow_update_cmr, is_surveillance, conditions_structured
				FROM vocab_rules_mastersnomed
				WHERE master_snomed_id = :orgId
				AND app_id = :appId;';
        $stmt = $this->dbConn->prepare($sql);
        $stmt->bindValue(':orgId', $organismId, PDO::PARAM_INT);
        $stmt->bindValue(':appId', $appId, PDO::PARAM_INT);

        if ($stmt->execute()) {
            while ($row = $stmt->fetchObject()) {
                $rules[] = new CaseManagementRule($row->allow_new_cmr, $row->allow_update_cmr, $row->is_surveillance, $row->conditions_structured);
            }
        }

        return $rules;
    }

    private function getRulesForMasterOrganismName($organismName, $appId, $conditionName = null)
    {
        $organismId = -1;

        $sql = "SELECT COALESCE(get_master_snomed_id_by_name(:orgName, :conditionName, :appId), -1) AS master_organism_id;";
        $stmt = $this->dbConn->prepare($sql);
        $stmt->bindValue(':orgName', $organismName, PDO::PARAM_STR);
        $stmt->bindValue(':conditionName', $conditionName, PDO::PARAM_STR);
        $stmt->bindValue(':appId', $appId, PDO::PARAM_INT);

        if ($stmt->execute()) {
            while ($row = $stmt->fetchObject()) {
                $organismId = intval($row->master_organism_id);
            }
        }

        return $this->getRulesForMasterOrganism($organismId, $appId);
    }

    private function getRulesForMasterLoinc($masterLoinc, $appId)
    {
        $rules = array();

        $sql = 'SELECT r.allow_new_cmr, r.allow_update_cmr, r.is_surveillance, r.conditions_structured
				FROM vocab_rules_masterloinc r 
				INNER JOIN vocab_master_loinc ml ON (r.master_loinc_id = ml.l_id)
				WHERE ml.loinc = :loincCode
				AND r.app_id = :appId;';
        $stmt = $this->dbConn->prepare($sql);
        $stmt->bindValue(':loincCode', $masterLoinc, PDO::PARAM_STR);
        $stmt->bindValue(':appId', $appId, PDO::PARAM_INT);

        if ($stmt->execute()) {
            while ($row = $stmt->fetchObject()) {
                $rules[] = new CaseManagementRule($row->allow_new_cmr, $row->allow_update_cmr, $row->is_surveillance, $row->conditions_structured);
            }
        }

        return $rules;
    }

    private function evaluateRules(EmsaMessage $emsaMessage): void
    {
        if (($emsaMessage->getMessageType() === MessageType::CLINICAL_DOCUMENT) && (empty($emsaMessage->childLoinc))) {
            // Clinical Document with no lab data...
            if (empty($emsaMessage->diagnosticCode) || empty($emsaMessage->diagnosticCodingSystem)) {
                $this->allowNewCmr = self::CMR_ERR_REQFIELD_DXCODE;
                $this->allowUpdateCmr = self::CMR_ERR_REQFIELD_DXCODE;
                $this->isSurveillance = self::CMR_ERR_REQFIELD_DXCODE;
                return;
            }
            
            $this->allowNewCmr = self::CMR_ERR_NO_RULES_DEFINED_DXCODE;
            $this->allowUpdateCmr = self::CMR_ERR_NO_RULES_DEFINED_DXCODE;
            $this->isSurveillance = self::CMR_ERR_NO_RULES_DEFINED_DXCODE;
            
            try {
                $dxCMRSql = "SELECT i.allow_new_cmr, i.allow_update_cmr, i.is_surveillance
                             FROM vocab_icd i
                             INNER JOIN vocab_child_codeset c ON (c.master_codeset_id = i.codeset_id)
                             WHERE i.code_value = :dxCode
                             AND c.child_codeset_value = :dxCodingSystem
                             AND c.structure_labs_id = :labId;";
                $dxCMRStmt = $this->dbConn->prepare($dxCMRSql);
                $dxCMRStmt->bindValue(':dxCode', $emsaMessage->diagnosticCode, PDO::PARAM_STR);
                $dxCMRStmt->bindValue(':dxCodingSystem', $emsaMessage->diagnosticCodingSystem, PDO::PARAM_STR);
                $dxCMRStmt->bindValue(':labId', $emsaMessage->labId, PDO::PARAM_INT);
                
                if ($dxCMRStmt->execute()) {
                    $dxCMRRow = $dxCMRStmt->fetchObject();
                    
                    if (isset($dxCMRRow->allow_new_cmr) && ($dxCMRRow->allow_new_cmr === true)) {
                        $this->allowNewCmr = self::CMR_YES;
                    } elseif (isset($dxCMRRow->allow_new_cmr) && ($dxCMRRow->allow_new_cmr === false)) {
                        $this->allowNewCmr = self::CMR_NO;
                    }
                    
                    if (isset($dxCMRRow->allow_update_cmr) && ($dxCMRRow->allow_update_cmr === true)) {
                        $this->allowUpdateCmr = self::CMR_YES;
                    } elseif (isset($dxCMRRow->allow_update_cmr) && ($dxCMRRow->allow_update_cmr === false)) {
                        $this->allowUpdateCmr = self::CMR_NO;
                    }
                    
                    if (isset($dxCMRRow->is_surveillance) && ($dxCMRRow->is_surveillance === true)) {
                        $this->isSurveillance = self::CMR_YES;
                    } elseif (isset($dxCMRRow->is_surveillance) && ($dxCMRRow->is_surveillance === false)) {
                        $this->isSurveillance = self::CMR_NO;
                    }
                }
                
                return;
            } catch (Throwable $ex) {
                ExceptionUtils::logException($ex);
                return;
            }
        }
        
        $appId = $emsaMessage->getApplicationId();
        $masterLoinc = $emsaMessage->getLoincForCaseManagementRules();
        $masterOrganismName = $emsaMessage->masterOrganism;
        $masterConditionName = $emsaMessage->masterCondition;
        $testResultIds = VocabUtils::appResultMasterVocabIdByName($this->dbConn, $emsaMessage->getTestResultForCaseManagementRules(), $appId);
        
        $deriveOrg = false;
        $orgRules = array();

        if (empty($masterLoinc)) {
            $this->allowNewCmr = self::CMR_ERR_REQFIELD_LOINC;
            $this->allowUpdateCmr = self::CMR_ERR_REQFIELD_LOINC;
            $this->isSurveillance = self::CMR_ERR_REQFIELD_LOINC;
            return;
        }

        if (empty($testResultIds)) {
            $this->allowNewCmr = self::CMR_ERR_REQFIELD_TESTRESULT;
            $this->allowUpdateCmr = self::CMR_ERR_REQFIELD_TESTRESULT;
            $this->isSurveillance = self::CMR_ERR_REQFIELD_TESTRESULT;
            return;
        }

        // check if master LOINC derives an organism
        $deriveOrgSql = 'SELECT l_id
                FROM vocab_master_loinc
                WHERE loinc = :masterLoincCode
                AND organism_from_result IS TRUE;';
        $deriveOrgStmt = $this->dbConn->prepare($deriveOrgSql);
        $deriveOrgStmt->bindValue(':masterLoincCode', $masterLoinc, PDO::PARAM_STR);
        if ($deriveOrgStmt->execute()) {
            if ($deriveOrgStmt->rowCount() === 1) {
                $deriveOrg = true;
            }
        }

        if ($deriveOrg) {
            if (($emsaMessage->getFinalStatus() === SEMI_AUTO_STATUS) || ($emsaMessage->getMessageDestination() === SEMI_AUTO_STATUS) || ($emsaMessage->getFinalStatus() === QA_STATUS) || ($emsaMessage->getMessageDestination() === QA_STATUS)) {
                // for messages using Semi-Automated Entry, look up rules by organism name selected by user 
                // instead of deriving from coded value
                $orgRules = $this->getRulesForMasterOrganismName($masterOrganismName, $appId, $masterConditionName);
            } else {
                // derive Master Organism based on coded value from sender
                $derivedOrgId = $emsaMessage->getMasterSNOMEDId();

                if ($derivedOrgId > 0) {
                    $orgRules = $this->getRulesForMasterOrganism($derivedOrgId, $appId);
                }
            }

            $this->rulesFromSnomed = true;
            $this->evaluateRuleList($orgRules, $testResultIds);
        } else {
            $loincRules = $this->getRulesForMasterLoinc($masterLoinc, $appId);

            $this->rulesFromSnomed = false;
            $this->evaluateRuleList($loincRules, $testResultIds);
        }
    }

    private function evaluateRuleList(array $ruleList, array $testResultIds): void
    {
        $matchedRules = array();

        if (count($ruleList) > 0) {
            // eval rules
            foreach ($ruleList as $rule) {
                foreach ($testResultIds as $testResultId) {
                    if (CaseManagementRule::evaluateRule($rule, $testResultId)) {
                        $matchedRules[] = $rule;
                    }
                }
            }

            if (count($matchedRules) < 1) {
                $this->allowNewCmr = self::CMR_ERR_NO_RULES_MATCHED;
                $this->allowUpdateCmr = self::CMR_ERR_NO_RULES_MATCHED;
                $this->isSurveillance = self::CMR_ERR_NO_RULES_MATCHED;
            } elseif (count($matchedRules) > 1) {
                $this->allowNewCmr = self::CMR_ERR_MULT_RULES_MATCHED;
                $this->allowUpdateCmr = self::CMR_ERR_MULT_RULES_MATCHED;
                $this->isSurveillance = self::CMR_ERR_MULT_RULES_MATCHED;
            } else {
                $matchedRule = reset($matchedRules);
                $this->allowNewCmr = $matchedRule->allowNewCmr ? self::CMR_YES : self::CMR_NO;
                $this->allowUpdateCmr = $matchedRule->allowUpdateCmr ? self::CMR_YES : self::CMR_NO;
                $this->isSurveillance = $matchedRule->isSurveillance ? self::CMR_YES : self::CMR_NO;
            }
        } else {
            if ($this->rulesFromSnomed) {
                $this->allowNewCmr = self::CMR_ERR_NO_RULES_DEFINED_SNOMED;
                $this->allowUpdateCmr = self::CMR_ERR_NO_RULES_DEFINED_SNOMED;
                $this->isSurveillance = self::CMR_ERR_NO_RULES_DEFINED_SNOMED;
            } else {
                $this->allowNewCmr = self::CMR_ERR_NO_RULES_DEFINED_LOINC;
                $this->allowUpdateCmr = self::CMR_ERR_NO_RULES_DEFINED_LOINC;
                $this->isSurveillance = self::CMR_ERR_NO_RULES_DEFINED_LOINC;
            }
        }
    }

    public function getAllowNewCmr()
    {
        return $this->allowNewCmr;
    }

    public function getAllowUpdateCmr()
    {
        return $this->allowUpdateCmr;
    }

    public function getIsSurveillance()
    {
        return $this->isSurveillance;
    }

}

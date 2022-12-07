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

/**
 * Service for processing vocabulary-related AJAX requests, such as editing & displaying single fields.
 *
 * @author Josh Ridderhoff <jridderhoff@utah.gov>
 */
class VocabAjaxService
{

    /** @var \PDO */
    protected $dbConn;

    /** @var \VocabRuleManager */
    protected $vrm;
    
    /** @var \Udoh\Emsa\Client\AppClientInterface */
    protected $appClient;

    const TABLE_MASTER_DICTIONARY = 1;
    const TABLE_CHILD_DICTIONARY = 2;
    const TABLE_MASTER_LOINC = 3;
    const TABLE_MASTER_CONDITION = 4;
    const TABLE_MASTER_SNOMED = 5;
    const TABLE_CHILD_LOINC = 6;
    const TABLE_CHILD_SNOMED = 7;
    const TABLE_RULES_GRAYLIST = 8;
    const TABLE_RULES_CMR_LOINC = 9;
    const TABLE_RULES_CMR_SNOMED = 10;
    const TABLE_RULES_QUANTITATIVE = 11;
    const TABLE_APP_DICTIONARY = 12;

    /**
     * Create a new Vocabulary AJAX Service object.
     * 
     * @param \PDO $dbConn PDO connection to EMSA database
     * @param \Udoh\Emsa\Client\AppClientInterface $authClient Application client used for authentication.
     */
    public function __construct(\PDO $dbConn, \Udoh\Emsa\Client\AppClientInterface $authClient)
    {
        $this->dbConn = $dbConn;
        $this->vrm = new \VocabRuleManager($dbConn, $authClient);
        $this->appClient = $authClient;
    }

    /**
     * Decode the table identifier specified into a table name and ID column.  If an invalid table identifier is specified, returns <b>FALSE</b>
     * @param int $tbl Table identifier
     * @return array|boolean If valid table is specified, an array containing decoded <i>tableName</i> and <i>idColumn</i>.  <b>FALSE</b> if an invalid table is specified.
     */
    private function decodeTable($tbl)
    {
        $tableName = null;
        $idColumn = 'id';

        switch ($tbl) {
            case self::TABLE_MASTER_DICTIONARY:
                $tableName = 'vocab_master_vocab';
                break;
            case self::TABLE_CHILD_DICTIONARY:
                $tableName = 'vocab_child_vocab';
                break;
            case self::TABLE_MASTER_LOINC:
                $tableName = 'vocab_master_loinc';
                $idColumn = 'l_id';
                break;
            case self::TABLE_MASTER_CONDITION:
                $tableName = 'vocab_master_condition';
                $idColumn = 'c_id';
                break;
            case self::TABLE_MASTER_SNOMED:
                $tableName = 'vocab_master_organism';
                $idColumn = 'o_id';
                break;
            case self::TABLE_CHILD_LOINC:
                $tableName = 'vocab_child_loinc';
                break;
            case self::TABLE_CHILD_SNOMED:
                $tableName = 'vocab_child_organism';
                break;
            case self::TABLE_RULES_GRAYLIST:
                $tableName = 'vocab_rules_graylist';
                $idColumn = 'master_condition_id';
                break;
            case self::TABLE_RULES_CMR_LOINC:
                $tableName = 'vocab_rules_masterloinc';
                $idColumn = 'master_loinc_id';
                break;
            case self::TABLE_RULES_CMR_SNOMED:
                $tableName = 'vocab_rules_mastersnomed';
                $idColumn = 'master_snomed_id';
                break;
            case self::TABLE_RULES_QUANTITATIVE:
                $tableName = 'vocab_c2m_testresult';
                break;
            case self::TABLE_APP_DICTIONARY:
                $tableName = 'vocab_app';
                break;
            default:
                $tableName = null;
        }

        if (is_null($tableName)) {
            return false;
        } else {
            return array(
                'tableName' => trim($tableName),
                'idColumn' => trim($idColumn)
            );
        }
    }

    /**
     * Draw a single text field for a specified table, column and record ID
     * @param array $params Array containing <i>tbl</i> (table ID), <i>col</i> (column to retrieve), <i>id</i> (record ID) to retrieve
     * @return string HTML code to be inserted via AJAX into the editor window.
     * @throws Exception if invalid table specified or on database errors.
     */
    public function drawTextField(array $params = array())
    {
        $html = null;
        $fieldVal = null;
        $tableDecoded = $this->decodeTable($params['tbl']);
        if (EmsaUtils::emptyTrim($tableDecoded['tableName'])) {
            throw new Exception('Invalid table specified');
        }

        try {
            $sql = 'SELECT ' . pg_escape_string($params['col']) . '
					FROM ' . pg_escape_string($tableDecoded['tableName']) . '
					WHERE ' . $tableDecoded['idColumn'] . ' = :targetId;';
            $stmt = $this->dbConn->prepare($sql);
            $stmt->bindValue(':targetId', $params['id'], \PDO::PARAM_INT);

            if ($stmt->execute()) {
                $fieldVal = $stmt->fetchColumn(0);
            }
        } catch (PDOException $e) {
            throw new Exception('A database error occurred');
        }

        if (!EmsaUtils::emptyTrim($fieldVal)) {
            $jsonCols = array(
                "callback_handler" => trim($params['handler']),
                "callback_tbl" => intval($params['tbl']),
                "callback_col" => trim($params['col']),
                "id" => intval($params['id']),
                "val" => trim($fieldVal)
            );

            $html = '<button type="button" class="edit_singlefield" value=\'' . json_encode($jsonCols) . '\' title="Edit Value">Edit</button>' . "\n";
            $html .= '<span>' . htmlspecialchars($fieldVal, ENT_QUOTES, 'UTF-8') . "</span>\n";
        }

        return trim($html);
    }

    /**
     * Draw a set of rules for the specified Master Condition
     * @param array $params Array containing <i>tbl</i> (one of VocabAjaxService::TABLE_RULES_GRAYLIST, VocabAjaxService::TABLE_RULES_CMR_LOINC, or VocabAjaxService::TABLE_RULES_CMR_SNOMED) and <i>id</i> (Master Condition ID) to retrieve rules for
     * @return string HTML code to be inserted via AJAX into the editor window.
     * @throws Exception if invalid table specified or on database errors.
     */
    public function drawRulesForCondition(array $params = array())
    {
        $html = '';

        $tableDecoded = $this->decodeTable($params['tbl']);
        if (EmsaUtils::emptyTrim($tableDecoded['tableName'])) {
            throw new Exception('Invalid table specified');
        }

        if ($params['tbl'] == self::TABLE_RULES_GRAYLIST) {
            $html .= '<table class="result_rules"><thead><tr><th>Actions</th><th nowrap>Condition</th><th nowrap>Test Type</th><th nowrap>Collected within...</th></tr></thead><tbody>';
        } elseif ($params['tbl'] == self::TABLE_RULES_CMR_LOINC) {
            $html .= '<table class="result_rules"><thead><tr><th>Actions</th><th nowrap>Test Result</th><th nowrap>New CMR?</th><th nowrap>Update CMRs?</th><th nowrap>Surveillance?</th><th nowrap>State Case Status</th></tr></thead><tbody>';
        } elseif ($params['tbl'] == self::TABLE_RULES_CMR_SNOMED) {
            $html .= '<table class="result_rules"><thead><tr><th>Actions</th><th nowrap>Test Result</th><th nowrap>New CMR?</th><th nowrap>Update CMRs?</th><th nowrap>Surveillance?</th><th nowrap>State Case Status</th></tr></thead><tbody>';
        }

        try {
            $sql = 'SELECT *
					FROM ' . pg_escape_string($tableDecoded['tableName']) . '
					WHERE ' . $tableDecoded['idColumn'] . ' = :targetId
					AND app_id = :appId
					ORDER BY id;';
            $stmt = $this->dbConn->prepare($sql);
            $stmt->bindValue(':targetId', $params['id'], \PDO::PARAM_INT);
            $stmt->bindValue(':appId', $this->appClient->getAppId(), \PDO::PARAM_INT);

            if ($stmt->execute()) {
                if ($stmt->rowCount() < 1) {
                    if (intval($params['tbl']) === self::TABLE_RULES_GRAYLIST) {
                        $html .= '<tr><td nowrap class="action_col"><span class="ui-icon ui-icon-elrerror" style="float: right;"></span></td><td nowrap colspan="3"><strong style="color: #9a2900;">No Graylist Rules defined for this Condition!</strong></td></tr>';
                    } elseif (intval($params['tbl']) === self::TABLE_RULES_CMR_LOINC) {
                        $html .= '<tr><td nowrap class="action_col"><span class="ui-icon ui-icon-elrerror" style="float: right;"></span></td><td nowrap colspan="5"><strong style="color: #9a2900;">No Case Management Rules defined for this LOINC!</strong></td></tr>';
                    } elseif (intval($params['tbl']) === self::TABLE_RULES_CMR_SNOMED) {
                        $html .= '<tr><td nowrap class="action_col"><span class="ui-icon ui-icon-elrerror" style="float: right;"></span></td><td nowrap colspan="5"><strong style="color: #9a2900;">No Case Management Rules defined for this Organism!</strong></td></tr>';
                    }
                } else {
                    while ($row = $stmt->fetchObject()) {
                        if (intval($params['tbl']) === self::TABLE_RULES_GRAYLIST) {
                            $rule_mod_params = array(
                                "callback_handler" => trim($params['handler']),
                                "callback_tbl" => intval($params['tbl']),
                                "callback_col" => trim($params['col']),
                                "callback_noun" => trim($params['condition']),
                                "id" => intval($row->id),
                                "focus_id" => intval($params['id']),
                                "action" => "edit",
                                "disease" => trim($params['condition']),
                                "application" => intval(trim($row->app_id)),
                                "conditions" => json_decode($row->conditions_structured, true));
                            $html .= '<tr><td nowrap class="action_col"><button class="edit_gray_rule" value=\'' . json_encode($rule_mod_params) . '\' type="button" title="Edit this Rule">Edit</button>';
                            $rule_mod_params = array(
                                "callback_handler" => trim($params['handler']),
                                "callback_tbl" => intval($params['tbl']),
                                "callback_col" => trim($params['col']),
                                "callback_noun" => trim($params['condition']),
                                "id" => intval($row->id),
                                "focus_id" => intval($params['id']),
                                "action" => "delete",
                                "disease" => trim($params['condition']));
                            $html .= '<button class="delete_gray_rule" value=\'' . json_encode($rule_mod_params) . '\' type="button" title="Delete this Rule">Delete</button></td>';

                            $this_rule_decoded_conditions = @json_decode($row->conditions_structured);
                            $this_rule_counter = 1;
                            foreach ($this_rule_decoded_conditions as $this_condition) {
                                $html .= '<td style="line-height: 1.2em;">';
                                $html .= '<strong style="color: green; font-size: 1.1em;">' . EmsaUtils::graphicalOperatorById($this->dbConn, $this_condition->operator) . '</strong> &quot;' . ((intval($this_condition->operand) < 0) ? "Any" : \Udoh\Emsa\Utils\VocabUtils::appCodedValueByMasterID($this->dbConn, $this_condition->operand, $this->appClient->getAppId())) . '&quot;';
                                $html .= '</td>';

                                $html .= '<td nowrap style="line-height: 1.2em;">';
                                $html .= '<strong style="color: green; font-size: 1.1em;">' . EmsaUtils::graphicalOperatorById($this->dbConn, $this_condition->operator1) . '</strong> &quot;' . ((intval($this_condition->operand1) < 0) ? "Any" : \Udoh\Emsa\Utils\VocabUtils::appCodedValueByMasterID($this->dbConn, $this_condition->operand1, $this->appClient->getAppId())) . '&quot;';
                                $html .= '</td>';

                                $html .= '<td nowrap style="line-height: 1.2em;">';
                                $html .= $this_condition->collect_lbound . ' before Event Date<br>' . $this_condition->collect_ubound . ' after Event Date';
                                $html .= '</td>';
                                $this_rule_counter++;
                            }
                            $html .= '</tr>';
                        } elseif (intval($params['tbl']) === self::TABLE_RULES_CMR_LOINC) {
                            unset($rule_mod_params);
                            $rule_mod_params = array(
                                "callback_handler" => trim($params['handler']),
                                "callback_tbl" => intval($params['tbl']),
                                "callback_col" => trim($params['col']),
                                "callback_noun" => trim($params['condition']),
                                "id" => intval($row->id),
                                "focus_id" => intval($params['id']),
                                "action" => "edit",
                                "loinc" => trim($params['condition']),
                                "application" => intval(trim($row->app_id)),
                                "conditions" => json_decode($row->conditions_structured, true),
                                "master_result" => intval(trim($row->state_case_status_master_id)),
                                "allow_new_cmr" => ($row->allow_new_cmr) ? 't' : 'f',
                                "allow_update_cmr" => ($row->allow_update_cmr) ? 't' : 'f',
                                "is_surveillance" => ($row->is_surveillance) ? 't' : 'f');
                            $html .= '<tr><td nowrap class="action_col"><button class="edit_cmr_rule" value=\'' . json_encode($rule_mod_params) . '\' type="button" title="Edit this Rule">Edit</button>';
                            unset($rule_mod_params);
                            $rule_mod_params = array(
                                "callback_handler" => trim($params['handler']),
                                "callback_tbl" => intval($params['tbl']),
                                "callback_col" => trim($params['col']),
                                "callback_noun" => trim($params['condition']),
                                "id" => intval($row->id),
                                "focus_id" => intval($params['id']),
                                "action" => "delete",
                                "loinc" => trim($params['condition']));
                            $html .= '<button class="delete_cmr_rule" value=\'' . json_encode($rule_mod_params) . '\' type="button" title="Delete this Rule">Delete</button></td>';

                            $html .= '<td nowrap style="width: 100%;">';
                            $this_rule_decoded_conditions = @json_decode($row->conditions_structured);
                            $this_rule_counter = 1;
                            foreach ($this_rule_decoded_conditions as $this_condition) {
                                $html .= '<strong style="color: green; font-size: 1.1em;">' . EmsaUtils::graphicalOperatorById($this->dbConn, $this_condition->operator) . '</strong> &quot;' . \Udoh\Emsa\Utils\VocabUtils::appCodedTestResultValueByMasterID($this->dbConn, $this_condition->operand, $this->appClient->getAppId()) . '&quot;';
                                if ($this_rule_counter < sizeof($this_rule_decoded_conditions)) {
                                    $html .= ' <strong style="color: darkred; font-size: 1.1em;">&amp;</strong> ';
                                }
                                $this_rule_counter++;
                            }
                            $html .= '</td>';
                            if ($row->allow_new_cmr) {
                                $html .= '<td nowrap style="text-align: center;"><span class="ui-icon ui-icon-elrsuccess" style="margin-left: auto; margin-right: auto;" title="Yes"></span></td>';
                            } else {
                                $html .= '<td nowrap style="text-align: center;"><span class="ui-icon ui-icon-elrcancel" style="margin-left: auto; margin-right: auto;" title="No"></span></td>';
                            }
                            if ($row->allow_update_cmr) {
                                $html .= '<td nowrap style="text-align: center;"><span class="ui-icon ui-icon-elrsuccess" style="margin-left: auto; margin-right: auto;" title="Yes"></span></td>';
                            } else {
                                $html .= '<td nowrap style="text-align: center;"><span class="ui-icon ui-icon-elrcancel" style="margin-left: auto; margin-right: auto;" title="No"></span></td>';
                            }
                            if ($row->is_surveillance) {
                                $html .= '<td nowrap style="text-align: center;"><span class="ui-icon ui-icon-elrsuccess" style="margin-left: auto; margin-right: auto;" title="Yes"></span></td>';
                            } else {
                                $html .= '<td nowrap style="text-align: center;"><span class="ui-icon ui-icon-elrcancel" style="margin-left: auto; margin-right: auto;" title="No"></span></td>';
                            }
                            $html .= '<td nowrap><strong>' . ((intval($row->state_case_status_master_id) > 0) ? htmlentities(\Udoh\Emsa\Utils\VocabUtils::getMasterConceptById($this->dbConn, trim($row->state_case_status_master_id)), ENT_QUOTES, "UTF-8") : '--');
                            $html .= '</strong></td></tr>';
                        } elseif (intval($params['tbl']) === self::TABLE_RULES_CMR_SNOMED) {
                            unset($rule_mod_params);
                            $rule_mod_params = array(
                                "callback_handler" => trim($params['handler']),
                                "callback_tbl" => intval($params['tbl']),
                                "callback_col" => trim($params['col']),
                                "callback_noun" => trim($params['condition']),
                                "id" => intval($row->id),
                                "focus_id" => intval($params['id']),
                                "action" => "edit",
                                "organism" => trim($params['condition']),
                                "application" => intval(trim($row->app_id)),
                                "conditions" => json_decode($row->conditions_structured, true),
                                "master_result" => intval(trim($row->state_case_status_master_id)),
                                "allow_new_cmr" => ($row->allow_new_cmr) ? 't' : 'f',
                                "allow_update_cmr" => ($row->allow_update_cmr) ? 't' : 'f',
                                "is_surveillance" => ($row->is_surveillance) ? 't' : 'f');
                            $html .= '<tr><td nowrap class="action_col"><button class="edit_ms_cmr_rule" value=\'' . json_encode($rule_mod_params) . '\' type="button" title="Edit this Rule">Edit</button>';
                            unset($rule_mod_params);
                            $rule_mod_params = array(
                                "callback_handler" => trim($params['handler']),
                                "callback_tbl" => intval($params['tbl']),
                                "callback_col" => trim($params['col']),
                                "callback_noun" => trim($params['condition']),
                                "id" => intval($row->id),
                                "focus_id" => intval($params['id']),
                                "action" => "delete",
                                "organism" => trim($params['condition']));
                            $html .= '<button class="delete_ms_cmr_rule" value=\'' . json_encode($rule_mod_params) . '\' type="button" title="Delete this Rule">Delete</button></td>';

                            $html .= '<td nowrap style="width: 100%; line-height: 1.2em;">';
                            $this_rule_decoded_conditions = @json_decode($row->conditions_structured);
                            $this_rule_counter = 1;
                            foreach ($this_rule_decoded_conditions as $this_condition) {
                                $html .= '<strong style="color: green; font-size: 1.1em;">' . EmsaUtils::graphicalOperatorById($this->dbConn, $this_condition->operator) . '</strong> &quot;' . \Udoh\Emsa\Utils\VocabUtils::appCodedTestResultValueByMasterID($this->dbConn, $this_condition->operand, $this->appClient->getAppId()) . '&quot;';
                                if ($this_rule_counter < sizeof($this_rule_decoded_conditions)) {
                                    $html .= '<br><strong style="color: darkred; font-size: 1.1em;">&</strong> ';
                                }
                                $this_rule_counter++;
                            }
                            $html .= '</td>';

                            if ($row->allow_new_cmr) {
                                $html .= '<td nowrap style="text-align: center;"><span class="ui-icon ui-icon-elrsuccess" style="margin-left: auto; margin-right: auto;" title="Yes"></span></td>';
                            } else {
                                $html .= '<td nowrap style="text-align: center;"><span class="ui-icon ui-icon-elrcancel" style="margin-left: auto; margin-right: auto;" title="No"></span></td>';
                            }
                            if ($row->allow_update_cmr) {
                                $html .= '<td nowrap style="text-align: center;"><span class="ui-icon ui-icon-elrsuccess" style="margin-left: auto; margin-right: auto;" title="Yes"></span></td>';
                            } else {
                                $html .= '<td nowrap style="text-align: center;"><span class="ui-icon ui-icon-elrcancel" style="margin-left: auto; margin-right: auto;" title="No"></span></td>';
                            }
                            if ($row->is_surveillance) {
                                $html .= '<td nowrap style="text-align: center;"><span class="ui-icon ui-icon-elrsuccess" style="margin-left: auto; margin-right: auto;" title="Yes"></span></td>';
                            } else {
                                $html .= '<td nowrap style="text-align: center;"><span class="ui-icon ui-icon-elrcancel" style="margin-left: auto; margin-right: auto;" title="No"></span></td>';
                            }
                            $html .= '<td nowrap><strong>' . ((intval($row->state_case_status_master_id) > 0) ? htmlentities(\Udoh\Emsa\Utils\VocabUtils::getMasterConceptById($this->dbConn, trim($row->state_case_status_master_id)), ENT_QUOTES, "UTF-8") : '--');
                            $html .= '</strong></td></tr>';
                        }
                    }
                }

                if (intval($params['tbl']) === self::TABLE_RULES_GRAYLIST) {
                    unset($rule_mod_params);
                    $rule_mod_params = array(
                        "callback_handler" => trim($params['handler']),
                        "callback_tbl" => intval($params['tbl']),
                        "callback_col" => trim($params['col']),
                        "callback_noun" => trim($params['condition']),
                        "id" => intval($params['id']),
                        "focus_id" => intval($params['id']),
                        "action" => "add",
                        "disease" => trim($params['condition']));
                    $html .= '<tr><td nowrap class="action_col"><button class="add_gray_rule" value=\'' . json_encode($rule_mod_params) . '\' type="button" title="Add new Graylist Rule for this Master Condition">Add New</button></td><td nowrap colspan="3"><em>&lt;Add New Graylist Rule&gt;</em></td></tr>';
                    $html .= '</tbody></table>';
                    //$html .= '<script type="text/javascript">$.getScript("'.$main_url.'js/vocab_rules_graylist.js");</script>';
                    //$html .= VocabRuleBuilder::drawGraylist($this->dbConn);
                } elseif (intval($params['tbl']) === self::TABLE_RULES_CMR_LOINC) {
                    unset($rule_mod_params);
                    $rule_mod_params = array(
                        "callback_handler" => trim($params['handler']),
                        "callback_tbl" => intval($params['tbl']),
                        "callback_col" => trim($params['col']),
                        "callback_noun" => trim($params['condition']),
                        "id" => intval($params['id']),
                        "focus_id" => intval($params['id']),
                        "action" => "add",
                        "loinc" => trim($params['condition']));
                    $html .= '<tr><td nowrap class="action_col"><button class="add_cmr_rule" value=\'' . json_encode($rule_mod_params) . '\' type="button" title="Add new Case Management Rule for this Master LOINC">Add New</button></td><td style="width: 100%;" nowrap colspan="5"><em>&lt;Add New LOINC-based Case Management Rule&gt;</em></td></tr>';
                    $html .= '</tbody></table>';
                    //$html .= '<script type="text/javascript">$.getScript("'.$main_url.'js/vocab_rules_masterloinc.js");</script>';
                    //$html .= VocabRuleBuilder::drawLoincCmr($this->dbConn);
                } elseif (intval($params['tbl']) === self::TABLE_RULES_CMR_SNOMED) {
                    unset($rule_mod_params);
                    $rule_mod_params = array(
                        "callback_handler" => trim($params['handler']),
                        "callback_tbl" => intval($params['tbl']),
                        "callback_col" => trim($params['col']),
                        "callback_noun" => trim($params['condition']),
                        "id" => intval($params['id']),
                        "focus_id" => intval($params['id']),
                        "action" => "add",
                        "organism" => trim($params['condition']));
                    $html .= '<tr><td nowrap class="action_col"><button class="add_ms_cmr_rule" value=\'' . json_encode($rule_mod_params) . '\' type="button" title="Add new Case Management Rule for this Organism">Add New</button></td><td style="width: 100%;" nowrap colspan="5"><em>&lt;Add New Organism-based Case Management Rule&gt;</em></td></tr>';
                    $html .= '</tbody></table>';
                    //$html .= '<script type="text/javascript">$.getScript("'.$main_url.'js/vocab_rules_mastersnomed.js");</script>';
                    //$html .= VocabRuleBuilder::drawSnomedCmr($this->dbConn);
                }
            } else {
                throw new Exception('Unable to retrieve list of Graylist Rules');
            }
        } catch (PDOException $e) {
            throw new Exception('An unspecified database error occurred');
        }

        return $html;
    }

    public function saveChanges(array $params)
    {
        $tableDecoded = $this->decodeTable($params['tbl']);
        if (EmsaUtils::emptyTrim($tableDecoded['tableName'])) {
            throw new Exception('Invalid table specified');
        }

        if ($tableDecoded['tableName'] == 'vocab_rules_masterloinc') {
            // handle requests for Master LOINC CMR Rules
            if ($params['editPkg']['rulemod_cmr_action'] == 'add') {
                $html = $this->vrm->addLoincCaseManagementRule($params);
            } elseif ($params['editPkg']['rulemod_cmr_action'] == 'edit') {
                $html = $this->vrm->editLoincCaseManagementRule($params);
            } else {
                throw new Exception('Invalid action specified');
            }
        } elseif ($tableDecoded['tableName'] == 'vocab_rules_mastersnomed') {
            // handle requests for Master SNOMED CMR Rules
            if ($params['editPkg']['rulemod_ms_cmr_action'] == 'add') {
                $html = $this->vrm->addSnomedCaseManagementRule($params);
            } elseif ($params['editPkg']['rulemod_ms_cmr_action'] == 'edit') {
                $html = $this->vrm->editSnomedCaseManagementRule($params);
            } else {
                throw new Exception('Invalid action specified');
            }
        } elseif ($tableDecoded['tableName'] == 'vocab_rules_graylist') {
            // handle requests for Graylist Rules
            if ($params['editPkg']['rulemod_gray_action'] == 'add') {
                $html = $this->vrm->addGraylistRule($params);
            } elseif ($params['editPkg']['rulemod_gray_action'] == 'edit') {
                $html = $this->vrm->editGraylistRule($params);
            } else {
                throw new Exception('Invalid action specified');
            }
        } elseif ($tableDecoded['tableName'] == 'vocab_master_condition') {
            // handle edits for Whitelist Rules
            if ($params['editPkg']['action'] == 'edit') {
                $html = $this->vrm->editWhitelistRule($params);
            } else {
                throw new Exception('Invalid action specified');
            }
        } else {
            throw new Exception('Not Implemented');
        }

        return $html;
    }

    public function deleteRule(array $params)
    {
        $tableDecoded = $this->decodeTable($params['tbl']);
        if (EmsaUtils::emptyTrim($tableDecoded['tableName'])) {
            throw new Exception('Invalid table specified');
        }

        if ($tableDecoded['tableName'] == 'vocab_rules_masterloinc') {
            $html = $this->vrm->deleteLoincCaseManagementRule($params['id'], $params['parentId']);
        } elseif ($tableDecoded['tableName'] == 'vocab_rules_mastersnomed') {
            $html = $this->vrm->deleteSnomedCaseManagementRule($params['id'], $params['parentId']);
        } elseif ($tableDecoded['tableName'] == 'vocab_rules_graylist') {
            $html = $this->vrm->deleteGraylistRule($params['id'], $params['parentId']);
        } else {
            throw new Exception('Not Implemented');
        }

        return $html;
    }

}

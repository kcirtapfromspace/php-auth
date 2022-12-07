<?php
namespace Udoh\Emsa\Email;

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

use Exception;
use PDO;
use RuleParameter;
use Throwable;
use Udoh\Emsa\Exceptions\NotificationDatabaseException;
use Udoh\Emsa\Exceptions\NotificationValidationException;
use Udoh\Emsa\Utils\ExceptionUtils;

/**
 * Notification container class
 * 
 * Contains all variables used for determining whether a processed ELR message
 * should generate a notification e-mail, and the properties of the message
 * to be sent.
 * 
 * @author Josh Ridderhoff <jridderhoff@utah.gov>
 */
class Notification
{

    /** @var PDO */
    protected $dbConn;
    protected $system_message_id;
    protected $nedss_event_id;
    protected $nedss_record_number;
    protected $notification_type;
    protected $is_surveillance;
    protected $is_immediate;
    protected $is_state;
    protected $is_pregnancy;
    protected $is_automated;
    protected $is_new_cmr;  // is a new CMR being created or an existing event being updated
    protected $is_event_closed;
    protected $trigger_for_state;
    protected $trigger_for_local;
    protected $condition;
    protected $organism;
    protected $jurisdiction;
    protected $test_type;
    protected $test_result;  // e.g. "POSITIVE" or "EQUIVOCAL"
    protected $result_value;
    protected $investigator;
    protected $master_loinc;
    protected $specimen;
    protected $event_type;

    /**
     * Set up parameters as instances of \RuleParameter class
     * 
     * @param PDO $dbConn Connection to the EMSA database.
     */
    public function __construct(PDO $dbConn)
    {
        $this->dbConn = $dbConn;
        
        $this->system_message_id = new RuleParameter('Integer');
        $this->nedss_event_id = new RuleParameter('Integer');
        $this->nedss_record_number = new RuleParameter('String');
        $this->notification_type = new RuleParameter('Integer');
        $this->is_surveillance = new RuleParameter('Boolean');
        $this->is_immediate = new RuleParameter('Boolean');
        $this->is_state = new RuleParameter('Boolean');
        $this->is_pregnancy = new RuleParameter('Boolean');
        $this->is_automated = new RuleParameter('Boolean');
        $this->is_new_cmr = new RuleParameter('Boolean');
        $this->is_event_closed = new RuleParameter('Boolean');
        $this->trigger_for_state = new RuleParameter('Boolean');
        $this->trigger_for_local = new RuleParameter('Boolean');
        $this->condition = new RuleParameter('String');
        $this->organism = new RuleParameter('String');
        $this->jurisdiction = new RuleParameter('Integer');
        $this->test_type = new RuleParameter('String');
        $this->test_result = new RuleParameter('String');
        $this->result_value = new RuleParameter('String');
        $this->investigator = new RuleParameter('String');
        $this->master_loinc = new RuleParameter('String');
        $this->specimen = new RuleParameter('String');
        $this->event_type = new RuleParameter('String');
    }

    /*
     * Operand Type constants
     */

    const OPTYPE_PARAMETER = 1; // operand type is a reference to a parameter
    const OPTYPE_VALUE = 2;  // operand type is a reference to a user-entered value

    /*
     * Expression Chain Link Type constants
     */
    const LINKTYPE_LINK = 1; // link is a comparison expression to evaluate
    const LINKTYPE_CHAIN = 2; // link is a pointer to another chain

    /*
     * Magic setter & getter to protect \RuleParameter property objects and resolve scope issues 
     * with \RuleParameter::setValue(mixed) & \RuleParameter::getvalue() public methods
     */

    public function __set($param, $value)
    {
        $this->$param->setValue($value);
    }

    public function __get($param)
    {
        return $this->$param->getValue();
    }

    /**
     * Returns a list of all class property names for rule parameter configuration
     * 
     * @return array List of property names
     */
    public function getPropertyList(): array
    {
        $varNames = array();
        foreach (get_object_vars($this) as $varname => $varval) {
            $varNames[] = $varname;
        }
        sort($varNames);
        return $varNames;
    }

    /**
     * Gets the data type for a specified property
     *
     * @param string $param Name of the property
     *
     * @return string Returns NULL on invalid property name, else property data type
     */
    public function getDataType(string $param): ?string
    {
        if (isset($this->$param) && ($this->$param instanceof RuleParameter)) {
            return $this->$param->getDataType();
        } else {
            return null;
        }
    }

    /**
     * Runs notification rules against current notification object to determine
     * whether criteria are met for generating a notification.  If criteria are
     * met, a new notification is generated.
     *
     * @return bool TRUE if criteria are met, FALSE if not.
     *
     * @throws NotificationDatabaseException If an error occurs with the notification generation.
     * @throws NotificationValidationException If required parameters are not complete.
     */
    public function logNotification(): bool
    {
        if ($this->validateRequiredParams()) {
            try {
                $sql = "SELECT id, name, send_to_state, send_to_lhd, notification_type 
                        FROM bn_rules 
                        WHERE enabled IS TRUE 
                        ORDER BY id;";
                $stmt = $this->dbConn->query($sql);  // get list of rules
                
                if ($stmt->rowCount() < 1) {
                    throw new NotificationDatabaseException('Unable to retrieve list of rules to evaluate.');
                } else {
                    while ($row = $stmt->fetchObject()) {
                        if ($this->evaluateRule(intval($row->id), 0)) {  // for each rule, see if conditions match
                            $this->notification_type->setValue($row->notification_type);
                            $this->trigger_for_state->setValue(($row->send_to_state == 't') ? true : false);
                            $this->trigger_for_local->setValue(($row->send_to_lhd == 't') ? true : false);

                            if (!$this->generateNotification()) {  // if rule matches, attempt to generate notification
                                throw new NotificationDatabaseException('Attempted to generate notification for rule "' . $row->name . '", but encountered a database error.');
                            }
                        }
                    }
                }
                
                return true;
            } catch (Throwable $e) {
                ExceptionUtils::logException($e);
                throw new NotificationDatabaseException('Unable to retrieve list of rules to evaluate.', 0, $e);
            }
        } else {
            throw new NotificationValidationException('Required notification parameters not set.');
        }
    }

    /**
     * If notification criteria are met (determined by //), generates a notification record
     * in batch_notifications to be sent at the scheduled time.
     *
     * @return bool TRUE on success, FALSE on failure
     */
    protected function generateNotification(): bool
    {
        // params from old function...
        // $system_message_id = null, $event_id = null, $record_number = null, $notify_type = null, 
        // $condition = null, $test_type = null, $notify_state = false, $notify_lhd = false, 
        // $jurisdiction_id = null, $event_type = 'MorbidityEvent', $investigator = '', $test_result = ''
        //var_dump($this);

        $isCustom = NotificationUtils::isCustomNotificationTypeWithId($this->dbConn, $this->notification_type->getValue());
        
        if ($isCustom === false) {
            $derivedJurisdictionId = $this->jurisdiction->getValue();  // not a custom notification, use jurisdiction ID for this event
        } else {
            $derivedJurisdictionId = $isCustom;  // custom notification, use virtual jurisdiction ID from this notification type
        }
        
        try {
            $sql = "INSERT INTO batch_notifications ( 
					notification_type, 
					system_message_id, 
					event_id, 
					record_number, 
					condition, 
                    organism, 
					test_type, 
					notify_state, 
					notify_lhd, 
					jurisdiction_id, 
					event_type, 
					investigator, 
					custom, 
					test_result 
				) VALUES ( 
					:nType, 
					:msgId, 
					:eventId, 
					:recordNumber, 
					:condition, 
                    :organism, 
					:testType, 
					:stateTrigger, 
					:localTrigger, 
					:jId, 
					:eventType, 
					:investigator, 
					:isCustom, 
					:testResult
				);";
            $stmt = $this->dbConn->prepare($sql);
            
            $cleanInvestigator = ((strlen(trim($this->investigator->getValue())) > 0) ? (string) $this->investigator->getValue() : null);
            $cleanTestResult = ((strlen(trim($this->test_result->getValue())) > 0) ? (string) $this->test_result->getValue() : null);
            $isCustomBool = ($isCustom !== false);
            
            $stmt->bindValue(':nType', (int) $this->notification_type->getValue(), PDO::PARAM_INT);
            $stmt->bindValue(':msgId', (int) $this->system_message_id->getValue(), PDO::PARAM_INT);
            $stmt->bindValue(':eventId', (int) $this->nedss_event_id->getValue(), PDO::PARAM_INT);
            $stmt->bindValue(':recordNumber', (string) $this->nedss_record_number->getValue(), PDO::PARAM_STR);
            $stmt->bindValue(':condition', (string) $this->condition->getValue(), PDO::PARAM_STR);
            $stmt->bindValue(':organism', (string) $this->organism->getValue(), PDO::PARAM_STR);
            $stmt->bindValue(':testType', (string) $this->test_type->getValue(), PDO::PARAM_STR);
            $stmt->bindValue(':stateTrigger', (bool) $this->trigger_for_state->getValue(), PDO::PARAM_BOOL);
            $stmt->bindValue(':localTrigger', (bool) $this->trigger_for_local->getValue(), PDO::PARAM_BOOL);
            $stmt->bindValue(':jId', $derivedJurisdictionId, PDO::PARAM_INT);
            $stmt->bindValue(':eventType', (string) $this->event_type->getValue(), PDO::PARAM_STR);
            $stmt->bindValue(':investigator', $cleanInvestigator, PDO::PARAM_STR);
            $stmt->bindValue(':isCustom', $isCustomBool, PDO::PARAM_BOOL);
            $stmt->bindValue(':testResult', $cleanTestResult, PDO::PARAM_STR);
            
            $stmt->execute();
            return true;
        } catch (Throwable $e) {
            ExceptionUtils::logException($e);
            return false;
        }
    }

    /**
     * Verify all required parameters are set for rule evaluation & notification generation
     * 
     * @return bool
     */
    protected function validateRequiredParams(): bool
    {
        $isValid = true;

        /*
          (!isset($this->system_message_id) || empty($this->system_message_id) || (filter_var($this->system_message_id, FILTER_VALIDATE_INT) === false))
          ? $is_valid = $is_valid && false
          : $this->system_message_id = intval($this->system_message_id);

          (!isset($this->nedss_event_id) || empty($this->nedss_event_id) || (filter_var($this->nedss_event_id, FILTER_VALIDATE_INT) === false))
          ? $is_valid = $is_valid && false
          : $this->nedss_event_id = intval($this->nedss_event_id);

          (!isset($this->nedss_record_number) || empty($this->nedss_record_number) || !(strlen(trim($this->nedss_record_number)) > 0))
          ? $is_valid = $is_valid && false
          : $this->nedss_record_number = filter_var(trim($this->nedss_record_number), FILTER_SANITIZE_STRING);

          (!isset($this->condition) || empty($this->condition) || !(strlen(trim($this->condition)) > 0))
          ? $is_valid = $is_valid && false
          : $this->condition = filter_var(trim($this->condition), FILTER_SANITIZE_STRING);

          (!isset($this->test_type) || empty($this->test_type) || !(strlen(trim($this->test_type)) > 0))
          ? $is_valid = $is_valid && false
          : $this->test_type = filter_var(trim($this->test_type), FILTER_SANITIZE_STRING);

          (!isset($this->jurisdiction) || empty($this->jurisdiction) || (filter_var($this->jurisdiction, FILTER_VALIDATE_INT) === false))
          ? $is_valid = $is_valid && false
          : $this->jurisdiction = intval($this->jurisdiction);
         */

        return $isValid;
    }

    /**
     * Evaluate the specified rule & return whether conditions are met.
     *
     * @param int $ruleId        ID of the rule to be evaluated
     * @param int $parentChainId Parent Chain ID of the expression chain to be evaluated
     *
     * @return bool
     *
     * @throws Exception
     */
    protected function evaluateRule(int $ruleId, ?int $parentChainId = 0): bool
    {
        $parentChainId = $parentChainId ?? 0;

        $result = false;
        $nextChainId = $this->getNextChain($ruleId, $parentChainId, 0);
        $leftId = $nextChainId;

        do {
            $this->evaluateChain($ruleId, $nextChainId, $result);
            $nextChainId = $this->getNextChain($ruleId, $parentChainId, $leftId);
            $leftId = $nextChainId;
        } while ($nextChainId !== false);

        return $result;
    }

    /**
     * Get ID of next chain in parent chain to be evaluated.
     *
     * @param int $ruleId        ID of the rule being evaluated
     * @param int $parentChainId ID of the chain being evaluated
     * @param int $leftId        ID of the last chain that was evaluated
     *
     * @return mixed ID of next chain to be evaluated if found, FALSE if no more chains exist
     */
    public function getNextChain(int $ruleId, int $parentChainId, int $leftId)
    {
        try {
            $sql = "SELECT id FROM bn_expression_chain 
                    WHERE rule_id = :ruleId 
                    AND parent_chain_id = :pcId 
                    AND left_id = :leftId;";
            $stmt = $this->dbConn->prepare($sql);
            $stmt->bindValue(':ruleId', (int) $ruleId, PDO::PARAM_INT);
            $stmt->bindValue(':pcId', (int) $parentChainId, PDO::PARAM_INT);
            $stmt->bindValue(':leftId', (int) $leftId, PDO::PARAM_INT);
            $stmt->execute();
            
            $nextChainId = $stmt->fetchColumn(0);
        } catch (Throwable $e) {
            ExceptionUtils::logException($e);
            $nextChainId = false;
        }

        return $nextChainId;
    }

    /**
     * Evaluates an expression chain and sets whether it is TRUE or FALSE
     *
     * @param int  $ruleId  ID of the rule being evaluated
     * @param int  $chainId ID of the expression chain to evaluate
     * @param bool $result  Reference to result boolean variable
     *
     * @throws Exception If chain_id is not provided, chain_id is not found, or chain is not a valid link type
     */
    protected function evaluateChain(?int $ruleId = null, ?int $chainId = null, bool &$result): void
    {
        if (empty($chainId) || empty($ruleId)) {
            throw new Exception('Unable to evaluate expression chain:  Missing Rule or Chain ID.');
        } else {
            try {
                $sql = "SELECT c.id AS id, c.link_type AS link_type, c.link_id AS link_id, c.left_operator_id AS operator_id, o.label AS operator 
                        FROM bn_expression_chain c 
                        LEFT JOIN structure_operator o ON (c.left_operator_id = o.id) 
                        WHERE c.id = :chainId;";
                $stmt = $this->dbConn->prepare($sql);
                $stmt->bindValue(':chainId', (int) $chainId, PDO::PARAM_INT);
                $stmt->execute();

                if ($stmt->rowCount() !== 1) {
                    throw new Exception('Missing logical expression in rule.');
                } else {
                    $chain = $stmt->fetchObject();
                    if (intval($chain->operator_id) === 0) {
                        // left-most token in the expression chain... set $result to be result of this link's evaluation
                        if (intval($chain->link_type) === self::LINKTYPE_LINK) {
                            $result = $this->evaluateLink(intval($chain->link_id));
                        } elseif (intval($chain->link_type) === self::LINKTYPE_CHAIN) {
                            $result = $this->evaluateRule($ruleId, intval($chain->id));
                        } else {
                            throw new Exception('Invalid logical expression type.');
                        }
                    } else {
                        // evaluate current token & compare against $result using the defined operator
                        if (intval($chain->link_type) === self::LINKTYPE_LINK) {
                            switch (strtolower(trim($chain->operator))) {
                                case 'and':
                                    $result = ($result && $this->evaluateLink(intval($chain->link_id)));
                                    break;
                                case 'or':
                                    $result = ($result || $this->evaluateLink(intval($chain->link_id)));
                                    break;
                                default:
                                    throw new Exception('Invalid operator specified in logical expression.');
                            }
                        } elseif (intval($chain->link_type) === self::LINKTYPE_CHAIN) {
                            switch (strtolower(trim($chain->operator))) {
                                case 'and':
                                    $result = ($result && $this->evaluateRule($ruleId, intval($chain->id)));
                                    break;
                                case 'or':
                                    $result = ($result || $this->evaluateRule($ruleId, intval($chain->id)));
                                    break;
                                default:
                                    throw new Exception('Invalid operator specified in logical expression.');
                            }
                        } else {
                            throw new Exception('Invalid logical expression type.');
                        }
                    }
                }
            } catch (Throwable $e) {
                ExceptionUtils::logException($e);
                throw new Exception('Missing logical expression in rule.');
            }
        }
    }

    /**
     * Evaluates an expression link and returns whether it is TRUE or FALSE
     * 
     * @param int $linkId ID of the expression link to evaluate
     * 
     * @return bool
     * 
     * @throws Exception If link_id is not provided.
     */
    protected function evaluateLink(?int $linkId = null): bool
    {
        if (empty($linkId)) {
            throw new Exception('Unable to evaluate expression link:  No ID provided.');
        } else {
            try {
                $sql = "SELECT l.type_left AS type_left, l.type_right AS type_right, l.operand_left AS operand_left, l.operand_right AS operand_right, o.label AS operator 
                        FROM bn_expression_link l 
                        LEFT JOIN structure_operator o ON (l.operator_id = o.id) 
                        WHERE l.id = :linkId;";
                $stmt = $this->dbConn->prepare($sql);
                $stmt->bindValue(':linkId', (int) $linkId, PDO::PARAM_INT);
                $stmt->execute();

                if ($stmt->rowCount() !== 1) {
                    throw new Exception('Unable to evaluate expression link:  Link not found.');
                } else {
                    $link = $stmt->fetchObject();

                    $operand_left = $link->operand_left;
                    $operand_right = $link->operand_right;

                    if ((intval($link->type_left) === self::OPTYPE_PARAMETER) && (intval($link->type_right) === self::OPTYPE_PARAMETER)) {
                        // parameter to parameter
                        switch (strtolower(trim($link->operator))) {
                            case 'equal':
                                $result = ($this->$operand_left->getValue() == $this->$operand_right->getValue()) ? true : false;
                                break;
                            case 'not equal':
                                $result = ($this->$operand_left->getValue() != $this->$operand_right->getValue()) ? true : false;
                                break;
                            case 'greater than':
                                $result = ($this->$operand_left->getValue() > $this->$operand_right->getValue()) ? true : false;
                                break;
                            case 'less than':
                                $result = ($this->$operand_left->getValue() < $this->$operand_right->getValue()) ? true : false;
                                break;
                            case 'greater than or equal to':
                                $result = ($this->$operand_left->getValue() >= $this->$operand_right->getValue()) ? true : false;
                                break;
                            case 'less than or equal to':
                                $result = ($this->$operand_left->getValue() <= $this->$operand_right->getValue()) ? true : false;
                                break;
                            case 'in':
                                $result = (in_array($this->$operand_left->getValue(), preg_split("/[\s]*[,][\s]*/", $this->$operand_right->getValue()))) ? true : false;
                                break;
                            case 'contains':
                                $result = (stripos($this->$operand_left->getValue(), $this->$operand_right->getValue()) !== false) ? true : false;
                                break;
                            default:
                                throw new Exception('Invalid operator specified in logical expression.');
                        }
                    } elseif ((intval($link->type_left) === self::OPTYPE_PARAMETER) && (intval($link->type_right) === self::OPTYPE_VALUE)) {
                        // parameter to value
                        switch (strtolower(trim($link->operator))) {
                            case 'equal':
                                $result = ($this->$operand_left->getValue() == $operand_right) ? true : false;
                                break;
                            case 'not equal':
                                $result = ($this->$operand_left->getValue() != $operand_right) ? true : false;
                                break;
                            case 'greater than':
                                $result = ($this->$operand_left->getValue() > $operand_right) ? true : false;
                                break;
                            case 'less than':
                                $result = ($this->$operand_left->getValue() < $operand_right) ? true : false;
                                break;
                            case 'greater than or equal to':
                                $result = ($this->$operand_left->getValue() >= $operand_right) ? true : false;
                                break;
                            case 'less than or equal to':
                                $result = ($this->$operand_left->getValue() <= $operand_right) ? true : false;
                                break;
                            case 'in':
                                $result = (in_array($this->$operand_left->getValue(), preg_split("/[\s]*[,][\s]*/", $operand_right))) ? true : false;
                                break;
                            case 'contains':
                                $result = (stripos($this->$operand_left->getValue(), $operand_right) !== false) ? true : false;
                                break;
                            case 'does not contain':
                                $result = (stripos($this->$operand_left->getValue(), $operand_right) === false) ? true : false;
                                break;
                            default:
                                throw new Exception('Invalid operator specified in logical expression.');
                        }
                    } else {
                        // ain't no way, no how...
                        throw new Exception('Unable to evaluate expression link:  Invalid operand types specified.');
                    }
                }
            } catch (Throwable $e) {
                ExceptionUtils::logException($e);
                throw new Exception('Unable to evaluate expression link:  Link not found.');
            }

            return $result;
        }
    }

}

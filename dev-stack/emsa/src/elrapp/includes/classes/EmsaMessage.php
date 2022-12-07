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

use Udoh\Emsa\Client\AppClientList;
use Udoh\Emsa\Constants\MessageType;
use Udoh\Emsa\Constants\SystemMessageActions;
use Udoh\Emsa\Exceptions\EmsaMessageNotFoundException;
use Udoh\Emsa\Exceptions\VocabularyException;
use Udoh\Emsa\MessageProcessing\CompareSourceLab;
use Udoh\Emsa\MessageProcessing\CompareSourceSusceptibility;
use Udoh\Emsa\Model\Address;
use Udoh\Emsa\Model\Person;
use Udoh\Emsa\Model\PersonFacility;
use Udoh\Emsa\Model\Telecom;
use Udoh\Emsa\Rules\ContactWhitelistRule;
use Udoh\Emsa\Rules\MessageFilterRule;
use Udoh\Emsa\Rules\WhitelistRule;
use Udoh\Emsa\Rules\WhitelistRuleSet;
use Udoh\Emsa\Utils\AuditUtils;
use Udoh\Emsa\Utils\AutomationUtils;
use Udoh\Emsa\Utils\CodedDataUtils;
use Udoh\Emsa\Utils\DateTimeUtils;
use Udoh\Emsa\Utils\EmsaMessageUtils;
use Udoh\Emsa\Utils\ExceptionUtils;
use Udoh\Emsa\Utils\VocabUtils;

/**
 * Container for an EMSA message to be used during message assignment
 *
 * @author Josh Ridderhoff <jridderhoff@utah.gov>
 */
class EmsaMessage
{

    /** @var PDO */
    protected $dbConn;

    /** Master LOINC codes, by lab, that should be knitted together
     * @var array */
    protected $knittableLoincs = array();
    /** @var int */
    protected $systemMessageId;
    /** @var int */
    protected $originalMessageId;
    /** @var int */
    protected $finalStatus;
    /** @var int */
    protected $assignedEventId;
    /** @var int */
    protected $systemJurisdictionId;
    /** @var int */
    protected $copyParentId;
    /** @var bool */
    protected $o2mPerformed;
    /** @var int */
    protected $o2mTargetEventId;
    /** @var int */
    protected $messageDestination;
    /** @var int */
    protected $messageType;
    /** @var int */
    protected $messageFlags;
    /** @var bool */
    protected $isDeleted;
    
    /** LOINC code to be used when evaluating Case Management Rules
     * @var string */
    protected $loincForCaseManagementRules;
    /** Test Result to be used when evaluating Case Management Rules
     * @var string */
    protected $testResultForCaseManagementRules;
    /** @var string */
    protected $masterLoincCode;
    /** @var string */
    protected $masterTestResult;
    
    /** @var AppClientList */
    protected $appClientList;
    /** @var int */
    protected $applicationId;
    
    /** @var Person */
    protected $person;
    
    /** @var CompareSourceLab */
    protected $source;

    /** @var SimpleXMLElement */
    protected $masterXML;

    /** @var SimpleXMLElement */
    protected $applicationXML;
    /** @var bool */
    protected $isPregnancyByLoinc = false;
    /** @var bool */
    protected $isPregnancyByMapping = false;
    protected $filterRuleRaw;
    
    /** @var MessageFilterRule */
    protected $filterRule;
    
    /** @var WhitelistRuleSet */
    protected $whitelistRules;
    
    /** @var bool */
    protected $allowOneToMany;

    /** @var bool */
    protected $astMultiColony;

    /** @var bool */
    protected $bypassOOS;
    
    /** @var \DateTime */
    protected $encounterDate;
    
    /** @var \DateTime */
    protected $referenceDate;
    
    /** @var DateTime */
    protected $dateReported;
    
    /** @var DateTime */
    protected $dateAssigned;

    public $masterCondition;
    public $masterOrganism;
    public $masterTestType;
    public $labId;
    public $senderName;
    public $isSenderAssigningECRLabs = false;
    public $districtOverride;
    public $childLocalLoincCode;
    public $childLocalCode;
    public $childLoinc;
    public $localResultValue1;
    public $localResultValue2;
    public $localResultValue;
    public $notifyState = false;
    public $isImmediate = false;
    public $isPregnancy = false;
    public $allowNewCmr;
    public $allowUpdateCmr;
    public $checkXrefFirst = false;
    public $validSpecimen = SPECIMEN_EXCEPTION;
    public $specimenSource;
    public $stateCaseStatus;
    public $isSurveillance;
    public $closeSurveillance = true;
    public $diagnosticCode;
    public $diagnosticCodingSystem;

    /**
     * Create a new EmsaMessage object for a specified System Message ID
     * 
     * @param PDO           $dbConn                  PDO connection to EMSA database
     * @param AppClientList $appClientList           List of configured applications for this installation
     * @param int           $systemMessageId         System Message ID
     * @param bool          $requireValidMasterLoinc [Optional]<br>If <b>TRUE</b>, requires the Master LOINC found in <i>system_messages</i> to be a valid, configured Master LOINC.  Default <b>TRUE</b>.
     * @param bool          $silent                  [Optional; Default <b>FALSE</b>] If <b>TRUE</b>, suppress writing messages to Audit Log where used.
     * @param bool          $showIncomplete          [Optional; Default <b>FALSE</b>] If <b>TRUE</b>, suppress exceptions that would prevent displaying of incomplete messages (i.e. when in Exceptions queue list)
     * 
     * @throws EmsaMessageNotFoundException if <i>systemMessageId</i> is not found
     * @throws VocabularyException if Antimicrobial Susceptibilty Test result exists but unable to translate vocabulary for the target application.
     * @throws PDOException on any database errors
     */
    public function __construct(PDO $dbConn, AppClientList $appClientList, $systemMessageId, $requireValidMasterLoinc = true, $silent = false, $showIncomplete = false)
    {
        $this->dbConn = $dbConn;
        $this->appClientList = $appClientList;
        $this->systemMessageId = (int) filter_var($systemMessageId, \FILTER_SANITIZE_NUMBER_INT);
        $this->messageType = MessageType::ELR_MESSAGE;
        $this->allowOneToMany = false;
        $this->astMultiColony = false;
        $this->bypassOOS = false;

        if (!$this->loadFromDb($requireValidMasterLoinc, $silent, $showIncomplete)) {
            throw new EmsaMessageNotFoundException('Could not process message; message not found.');
        }
        
        $this->setReferenceDate();
    }

    /**
     * Returns the current System Message ID.
     * 
     * @return int
     */
    public function getSystemMessageId()
    {
        if (empty($this->systemMessageId)) {
            return 0;
        } else {
            return (int) $this->systemMessageId;
        }
    }
    
    /**
     * Returns the current message's Original Message ID
     * 
     * @return int
     */
    public function getOriginalMessageId()
    {
        if (empty($this->originalMessageId)) {
            return 0;
        } else {
            return (int) $this->originalMessageId;
        }
    }
    
    /**
     * Returns the Master SNOMED ID for the organism associated with this message.
     * 
     * @return int
     */
    public function getMasterSNOMEDId()
    {
        $masterSNOMEDId = 0;
        
        try {
            $sql = 'SELECT loinc_organism_id(:childLoincCode, :resultValue, :labId);';
            $stmt = $this->dbConn->prepare($sql);
            $stmt->bindValue(':childLoincCode', $this->childLoinc, PDO::PARAM_STR);
            $stmt->bindValue(':resultValue', $this->localResultValue, PDO::PARAM_STR);
            $stmt->bindValue(':labId', $this->labId, PDO::PARAM_INT);
            
            if ($stmt->execute()) {
                $masterSNOMEDId = $stmt->fetchColumn(0);
            }
            
            unset($stmt);
        } catch (Throwable $e) {
            ExceptionUtils::logException($e);
        }

        return $masterSNOMEDId;
    }

    /**
     * Returns the current Queue ID (final_status) of the current message.
     * 
     * @return int
     */
    public function getFinalStatus()
    {
        return (int) $this->finalStatus;
    }
    
    /**
     * Get the LOINC code used for evaluating Case Management Rules.
     * 
     * @return string
     */
    public function getLoincForCaseManagementRules()
    {
        return $this->loincForCaseManagementRules;
    }
    
    /** 
     * Get the Master LOINC Code.
     * 
     * @return string 
     */
    public function getMasterLoincCode()
    {
        return $this->masterLoincCode;
    }
    
    /**
     * Get the Test Result used for evaluating Case Management Rules.
     * 
     * @return string
     */
    public function getTestResultForCaseManagementRules()
    {
        return $this->testResultForCaseManagementRules;
    }

    /** 
     * Get the Master Test Result.
     * 
     * @return string 
     */
    public function getMasterTestResult()
    {
        return $this->masterTestResult;
    }
        
    /**
     * Returns the application record (event) ID this message was assigned to, if assigned.
     * 
     * @return int
     */
    public function getAssignedEventId()
    {
        return $this->assignedEventId;
    }

    /**
     * Get the System Jurisdiction ID for this message
     *
     * @return int
     */
    public function getSystemJurisdictionId()
    {
        return $this->systemJurisdictionId;
    }

    /**
     * Returns the parent system message ID this message was copied from, if it is a copy.
     * 
     * @return int
     */
    public function getCopyParentId()
    {
        return $this->copyParentId;
    }

    /**
     * Indicates whether this message has been through One-to-Many processing.
     *
     * @return bool
     */
    public function wasO2MPerformed()
    {
        return $this->o2mPerformed;
    }

    /**
     * If the message has been through One-to-Many processing, indicates the event ID that this copy of the message
     * was targeted to update.  If this copy was selected to create a new event, returns NULL.
     *
     * @return int
     */
    public function getO2MTargetEventId()
    {
        return $this->o2mTargetEventId;
    }

    /**
     * Set data related to One-to-Many processing.
     *
     * Sets that this message has been processed by One-to-Many.  If <i>targetEventId</i> is provided,
     * also indicates the target event this copy is supposed to update.
     *
     * @param int $targetEventId [Optional]<br>If this message copy is destined to update a specific event, provide the target event ID here.<br>If this message is creating a new event, leave NULL.
     *
     * @return bool
     */
    public function setO2MTarget($targetEventId = null)
    {
        $targetSet = false;

        $this->o2mPerformed = true;

        if (!empty($targetEventId)) {
            $this->o2mTargetEventId = $targetEventId;
        }

        try {
            $sql = "UPDATE system_messages
                    SET o2m_performed = TRUE, o2m_event_id = :eventId
                    WHERE id = :systemMsgId;";
            $stmt = $this->dbConn->prepare($sql);
            $stmt->bindValue(':systemMsgId', $this->systemMessageId, PDO::PARAM_INT);
            $stmt->bindValue(':eventId', $targetEventId, PDO::PARAM_INT);

            if ($stmt->execute()) {
                $targetSet = true;
            }
        } catch (Throwable $e) {
            ExceptionUtils::logException($e);
        } finally {
            $stmt = null;
        }

        return $targetSet;
    }

    /**
     * Returns the Message Flags value for this message.
     * 
     * @return int
     */
    public function getMessageFlags()
    {
        return $this->messageFlags;
    }
    
    /**
     * Indicates whether the message has been flagged as "Deleted" or not.
     * 
     * @return bool
     */
    public function getIsDeleted()
    {
        return $this->isDeleted;
    }

    /**
     * Returns the message type for the current message.
     * 
     * @return int
     */
    public function getMessageType()
    {
        return (int) $this->messageType;
    }

    /**
     * Returns the application ID (target system) for the current message.
     * 
     * @return int
     */
    public function getApplicationId()
    {
        return (int) $this->applicationId;
    }
    
    /**
     * Get the Application Client specific for this message.
     * 
     * @return \Udoh\Emsa\Client\AppClientInterface
     */
    public function getAppClient()
    {
        return $this->appClientList->getClientById($this->applicationId);
    }
    
    /**
     * Ge the Person object for this message.
     * 
     * @return Person
     */
    public function getPerson()
    {
        return $this->person;
    }
    
    /**
     * Get the current message's CompareSourceLab object.
     * 
     * @return CompareSourceLab
     */
    public function getSource()
    {
        return $this->source;
    }

    /**
     * Get the current message's Master XML object.
     * 
     * @return SimpleXMLElement
     */
    public function getMasterXML()
    {
        return $this->masterXML;
    }

    /**
     * Get the current message's Application XML object.
     * 
     * @return SimpleXMLElement
     */
    public function getApplicationXML()
    {
        return $this->applicationXML;
    }

    /**
     * Commits critical changes to Master & Application XML structures to database in event of changes that need to be preserved 
     * (such as algorithmically changing disease outside of normal Master Process mapping).
     * 
     * @return bool
     */
    public function saveXMLChangesToDb()
    {
        $masterXMLStr = trim($this->getMasterXML()->asXML());
        $applicationXMLStr = trim($this->getApplicationXML()->asXML());
        $diseaseNameStr = trim($this->getMasterXML()->disease->name);

        try {
            $sql = "UPDATE system_messages
                    SET master_xml = :masterXML, 
                    transformed_xml = :applicationXML,
                    disease = :diseaseName
                    WHERE id = :systemMessageId;";
            $stmt = $this->dbConn->prepare($sql);
            $stmt->bindValue(':masterXML', $masterXMLStr, PDO::PARAM_STR);
            $stmt->bindValue(':applicationXML', $applicationXMLStr, PDO::PARAM_STR);
            $stmt->bindValue(':diseaseName', $diseaseNameStr, PDO::PARAM_STR);
            $stmt->bindValue(':systemMessageId', $this->getSystemMessageId(), PDO::PARAM_INT);

            $stmt->execute();
            return true;
        } catch (PDOException $e) {
            ExceptionUtils::logException($e);
            return false;
        }
    }

    /**
     * Indicates whether the current message is knittable based on sending facility and Master LOINC.
     * 
     * Lab result knitting allows multiple lab results (e.g. from separate HL7 messages)
     * with the same accession number, collection time, etc. that are mapped to the same 
     * Master LOINC to be knitted together into a single lab result in the target Application.
     * 
     * @return bool
     */
    public function getIsKnittable()
    {
        if ($this->getIsSenderUsingKnitting()) {
            if (in_array($this->masterLoincCode, $this->knittableLoincs[$this->labId])) {
                return true;
            }
        }

        return false;
    }

    /**
     * Indicates whether current sending facility uses lab result knitting.
     * 
     * Lab result knitting allows multiple lab results (e.g. from separate HL7 messages)
     * with the same accession number, collection time, etc. that are mapped to the same 
     * Master LOINC to be knitted together into a single lab result in the target Application.
     * 
     * @return bool
     */
    public function getIsSenderUsingKnitting()
    {
        if (isset($this->knittableLoincs[$this->labId]) && is_array($this->knittableLoincs[$this->labId]) && (count($this->knittableLoincs[$this->labId]) > 0)) {
            return true;
        } else {
            return false;
        }
    }
    
    /**
     * Get the set of Whitelist Rules for the condition associated with this EMSA message
     * 
     * @return WhitelistRuleSet
     */
    public function getWhitelistRules()
    {
        return $this->whitelistRules;
    }
    
    /**
     * Indicates whether this message is allowed to be assigned to multiple existing events (if found).
     * 
     * @return bool
     */
    public function getAllowOneToMany()
    {
        // already performed One-to-Many process; don't allow again
        if ($this->o2mPerformed) {
            return false;
        }

        if ($this->allowOneToMany && empty($this->getCopyParentId())) {
            return true;
        } else {
            return false;
        }
    }

    /**
     * Indicates whether this message shoule allow for multiple AST results for the same organism and antimicrobial agent (i.e. if using Multi-Colony AST panels).
     *
     * @return bool
     */
    public function getAstMultiColony()
    {
        return $this->astMultiColony;
    }

    /**
     * Indicates whether this message should, based upon condition, skip the 'Out of State' queue if it would be otherwise routed there.
     *
     * @return bool
     */
    public function getBypassOOS()
    {
        return $this->bypassOOS;
    }

    /**
     * Get the Message Filter Rule for this message's Master Condition
     * 
     * @return MessageFilterRule
     */
    public function getFilterRule()
    {
        return $this->filterRule;
    }

    /**
     * Sets the encounter date.
     * 
     * @param \DateTime $encounterDate Encounter date.
     */
    public function setEncounterDate(\DateTime $encounterDate = null)
    {
        if (!empty($encounterDate)) {
            $this->encounterDate = $encounterDate;
        }
    }

    /**
     * Get the encounter date.
     * 
     * @param bool $formatted [Optional]<br>Default <b>FALSE</b>.  If <b>TRUE</b>, returns date as a formatted string (specified by <i>formatStr</i>).
     * @param string $formatStr [Optional]<br>Valid PHP date format string/constant to return the date as.  Default <b>DateTime::RFC3339</b>.
     * 
     * @return string|\DateTime|null Formatted string if <i>formatted</i> is <b>TRUE</b>, DateTime object otherwise.<br>Returns <b>NULL</b> if enounter date is not set.
     */
    public function getEncounterDate($formatted = false, $formatStr = DateTime::RFC3339)
    {
        return DateTimeUtils::getDateFormatted($this->encounterDate, $formatted, $formatStr);
    }

    /**
     * Sets the message workflow destination for this message, by Child LOINC
     *
     * @param bool $silent         [Optional; Default <b>FALSE</b>] If <b>TRUE</b>, suppress writing messages to Audit Log.
     * @param bool $showIncomplete [Optional; Default <b>FALSE</b>] If <b>TRUE</b>, suppress exceptions that would prevent displaying of incomplete messages (i.e. when in Exceptions queue list)
     *
     * @throws Exception
     */
    protected function setMessageDestination($silent = false, $showIncomplete = false)
    {
        if (($this->getMessageType() === MessageType::CLINICAL_DOCUMENT) && empty($this->childLoinc)) {
            $this->messageDestination = ENTRY_STATUS;  // short-circuit autoprocess diagnostic-only CCDA messages for now (CCDAs without lab results)
            return;
        }

        $workflow = EXCEPTIONS_STATUS;

        if (empty($this->childLoinc) && !$showIncomplete) {
            throw new Exception('No Child LOINC found in message.');
        }

        // get workflow as defined by Child LOINC
        try {
            $sql = "SELECT workflow
					FROM vocab_child_loinc
					WHERE child_loinc = :childLoinc
					AND lab_id = :labId
					AND archived IS FALSE;";
            $stmt = $this->dbConn->prepare($sql);
            $stmt->bindValue(':childLoinc', trim($this->childLoinc), PDO::PARAM_STR);
            $stmt->bindValue(':labId', trim($this->labId), PDO::PARAM_INT);

            if ($stmt->execute()) {
                $workflow = (int) $stmt->fetchColumn(0);
            }
        } catch (Throwable $e) {
            ExceptionUtils::logException($e);
            throw new Exception('Child LOINC "' . trim($this->childLoinc) . '" not found.');
        }
        
        // check to see if decoded Master SNOMED overrides workflow
        $workflowOverride = AutomationUtils::getSNOMEDWorkflowOverride($this->dbConn, $this);
        if ($workflowOverride === true) {
            $workflow = SEMI_AUTO_STATUS;  // require (force) semi-auto
            if (!$silent) {
                AuditUtils::auditMessage($this->dbConn, $this->getSystemMessageId(), SystemMessageActions::MESSAGE_AUTOPROCESSED, $this->getFinalStatus(), 'Master SNOMED rules require Semi-Automated Entry processing for this result.');
            }
        } elseif (($workflowOverride === false) && ($workflow === SEMI_AUTO_STATUS)) {
            $workflow = ENTRY_STATUS;  // skip semi-auto
            if (!$silent) {
                AuditUtils::auditMessage($this->dbConn, $this->getSystemMessageId(), SystemMessageActions::MESSAGE_AUTOPROCESSED, $this->getFinalStatus(), 'Semi-Automated Entry skipped due to Master SNOMED rules.');
            }
        }

        if ($workflow === EXCEPTIONS_STATUS) {
            throw new Exception('Valid message workflow not found for Child LOINC "' . trim($this->childLoinc) . '".');
        }

        $this->messageDestination = $workflow;
    }

    /**
     * Get this message's workflow destination.
     *
     * @return int EMSA Queue ID that the message should be assigned to
     *
     * @throws Exception
     */
    public function getMessageDestination()
    {
        if (empty($this->messageDestination)) {
            $this->setMessageDestination();
        }
        
        return $this->messageDestination;
    }

    /**
     * Set the Reference Date for this message.
     */
    protected function setReferenceDate()
    {
        $tempReferenceDate = null;

        if (!is_null($this->getSource())) {
            if (!empty($this->getSource()->getDateCollected())) {
                $tempReferenceDate = $this->getSource()->getDateCollected();
            } elseif (!empty($this->getSource()->getDateTested())) {
                // fall back to Lab Test Date if missing Specimen Collection Date
                $tempReferenceDate = $this->getSource()->getDateTested();
            }
        }

        if (empty($tempReferenceDate) && ($this->getMessageType() === MessageType::CLINICAL_DOCUMENT)) {
            $tempReferenceDate = $this->getEncounterDate();
        }

        $this->referenceDate = $tempReferenceDate;
    }

    /**
     * Get the Reference Date for this message for use with filtering and whitelist rules.
     * 
     * @param bool $formatted [Optional]<br>Default <b>FALSE</b>.  If <b>TRUE</b>, returns date as a formatted string (specified by <i>formatStr</i>).
     * @param string $formatStr [Optional]<br>Valid PHP date format string/constant to return the date as.  Default <b>DateTime::RFC3339</b>.
     * 
     * @return string|\DateTime|null Formatted string if <i>formatted</i> is <b>TRUE</b>, DateTime object otherwise.<br>Returns <b>NULL</b> if Reference Date is not set.
     */
    public function getReferenceDate($formatted = false, $formatStr = DateTime::RFC3339)
    {
        return DateTimeUtils::getDateFormatted($this->referenceDate, $formatted, $formatStr);
    }
    
    /**
     * Get the Date Reported for this message.
     * 
     * @param bool $formatted [Optional]<br>Default <b>FALSE</b>.  If <b>TRUE</b>, returns date as a formatted string (specified by <i>formatStr</i>).
     * @param string $formatStr [Optional]<br>Valid PHP date format string/constant to return the date as.  Default <b>DateTime::RFC3339</b>.
     * 
     * @return string|\DateTime|null Formatted string if <i>formatted</i> is <b>TRUE</b>, DateTime object otherwise.<br>Returns <b>NULL</b> if Reported Date is not set.
     */
    public function getDateReported($formatted = false, $formatStr = DateTime::RFC3339)
    {
        return DateTimeUtils::getDateFormatted($this->dateReported, $formatted, $formatStr);
    }
    
    /**
     * Get the Date Assigned for this message.
     * 
     * @param bool $formatted [Optional]<br>Default <b>FALSE</b>.  If <b>TRUE</b>, returns date as a formatted string (specified by <i>formatStr</i>).
     * @param string $formatStr [Optional]<br>Valid PHP date format string/constant to return the date as.  Default <b>DateTime::RFC3339</b>.
     * 
     * @return string|\DateTime|null Formatted string if <i>formatted</i> is <b>TRUE</b>, DateTime object otherwise.<br>Returns <b>NULL</b> if Reported Date is not set.
     */
    public function getDateAssigned($formatted = false, $formatStr = DateTime::RFC3339)
    {
        return DateTimeUtils::getDateFormatted($this->dateAssigned, $formatted, $formatStr);
    }
    
    /**
     * Get the file name to be used if adding an attachment based on this message's lab results to EpiTrax.
     * 
     * @return string File name in the format of "ELR{CollectionDate}{TestType}.pdf"
     */
    public function getAttachmentFilename()
    {
        $filename = 'ELR';
        $filename .= $this->getSource()->getDateCollected(true, "YmdHis");
        $filename .= (string) preg_replace("/[^[:alnum:]]/", '', (string) $this->masterTestType);
        $filename .= '.pdf';
        
        return $filename;
    }
    
    /**
     * In the event a surveillance event is created based on this message, 
     * checks to see whether the surveillance event should be closed or left open, 
     * depending on the target jurisdiction.
     */
    protected function setCloseSurveillance()
    {
        $this->closeSurveillance = true;

        if ($this->getSystemJurisdictionId() > 0) {
            try {
                $sql = "SELECT close_surveillance FROM system_districts
                        WHERE id = :systemDistrictId;";
                $stmt = $this->dbConn->prepare($sql);
                $stmt->bindValue(':systemDistrictId', $this->getSystemJurisdictionId(), PDO::PARAM_INT);
                
                if ($stmt->execute()) {
                    $this->closeSurveillance = $stmt->fetchColumn(0);
                }
            } catch (Throwable $e) {
                ExceptionUtils::logException($e);
            }
        }
    }

    /**
     * Sets the Master XML value to the supplied XML string and set Master XML-derived message properties.
     *
     * @param bool $silent         [Optional; Default <b>FALSE</b>] If <b>TRUE</b>, suppress writing messages to Audit Log where used.
     * @param bool $showIncomplete [Optional; Default <b>FALSE</b>] If <b>TRUE</b>, suppress exceptions that would prevent displaying of incomplete messages (i.e. when in Exceptions queue list)
     *
     * @throws Exception
     */
    protected function setMasterXML($silent = false, $showIncomplete = false)
    {
        if (!empty($this->masterXML)) {
            if (isset($this->masterXML->person->date_of_birth) && !empty($this->masterXML->person->date_of_birth) && (strlen(trim($this->masterXML->person->date_of_birth)) > 5)) {
                try {
                    $tmpDob = DateTimeUtils::createMixed(trim($this->masterXML->person->date_of_birth));
                } catch (Throwable $e) {
                    ExceptionUtils::logException($e);
                    $tmpDob = null;
                }
            } else {
                $tmpDob = null;
            }

            if (isset($this->masterXML->encounter->encounter_date) && !empty($this->masterXML->encounter->encounter_date)) {
                try {
                    $tmpEncounterDate = DateTimeUtils::createMixed(trim($this->masterXML->encounter->encounter_date));
                } catch (Throwable $e) {
                    ExceptionUtils::logException($e);
                    $tmpEncounterDate = null;
                }
                
                $this->setEncounterDate($tmpEncounterDate);
            }
            
            if (!is_null($this->getEncounterDate())) {
                // encounter date only mapped for CCDA messages; set message type flag to CCDA
                $this->messageType = MessageType::CLINICAL_DOCUMENT;
            }
            
            $this->person = new Person();
            $this->person->setFirstName(trim($this->masterXML->person->first_name));
            $this->person->setLastName(trim($this->masterXML->person->last_name));
            $this->person->setMiddleName(trim($this->masterXML->person->middle_name));
            $this->person->setDateOfBirth($tmpDob);

            $personAddress = new Address();
            $personTelephone = new Telecom();

            $personAddress->setStreet((string) $this->masterXML->person->street_name);
            $personAddress->setUnitNumber((string) $this->masterXML->person->unit);
            $personAddress->setCity((string) $this->masterXML->person->city);
            $personAddress->setState((string) $this->masterXML->person->state);
            $personAddress->setPostalCode((string) $this->masterXML->person->zip);
            $personAddress->setCounty((string) $this->masterXML->person->county);

            $personTelephone->setType(Telecom::TYPE_PHONE);
            $personTelephone->setUse(Telecom::USE_UNKNOWN);
            $personTelephone->setAreaCode((string) $this->masterXML->person->area_code);
            $personTelephone->setLocalNumber((string) $this->masterXML->person->phone);
            $personTelephone->setExtension((string) $this->masterXML->person->extension);

            $this->person->addAddress($personAddress);
            $this->person->addTelecom($personTelephone);

            if (!empty($this->masterXML->person->email)) {
                $personEmail = (new Telecom())
                    ->setType(Telecom::TYPE_EMAIL)
                    ->setUse(Telecom::USE_UNKNOWN)
                    ->setEmailAddress(trim($this->masterXML->person->email));
                $this->person->addTelecom($personEmail);
            }
            
            $this->masterTestResult = trim($this->masterXML->labs->test_result);
            $this->masterTestType = CodedDataUtils::getCodeDescriptionFromCodedValue($this->getAppClient(), 'common_test_types', (string) $this->masterXML->labs->test_type);
            $this->masterOrganism = CodedDataUtils::getCodeDescriptionFromCodedValue($this->getAppClient(), 'organisms', (string) $this->masterXML->labs->organism);
            $this->childLocalLoincCode = trim($this->masterXML->labs->local_loinc_code);
            $this->childLocalCode = trim($this->masterXML->labs->local_code);
            $this->childLoinc = ((isset($this->childLocalLoincCode) && !empty($this->childLocalLoincCode)) ? $this->childLocalLoincCode : $this->childLocalCode);
            $this->localResultValue = EmsaMessageUtils::getLocalResultValue($this->dbConn, $this);
            $this->specimenSource = trim($this->masterXML->labs->specimen_source);
            $this->stateCaseStatus = trim($this->masterXML->labs->state_case_status);
            
            $this->setMessageDestination($silent, $showIncomplete);
            
            // set LOINC to be used for Case Management Rules
            if (!empty($this->masterXML->labs->original_loinc_code)) {
                // if original_loinc_code is set, it was the original LOINC that identified the AST+Organism pair
                // before the extractSusceptibilityResults() method overwrote it; use this LOINC code instead of masterLoincCode
                $this->loincForCaseManagementRules = (string) $this->masterXML->labs->original_loinc_code;
            } else {
                $this->loincForCaseManagementRules = (string) $this->masterLoincCode;
            }
            
            // set Test Result to be used for Case Management Rules
            if (!empty($this->masterXML->labs->original_test_result)) {
                // if original_test_result is set, it was the original test result associated with the AST result
                // before the extractSusceptibilityResults() method overwrote it; use this test result code instead of masterTestResult
                $this->testResultForCaseManagementRules = (string) $this->masterXML->labs->original_test_result;
            } else {
                $this->testResultForCaseManagementRules = (string) $this->masterTestResult;
            }

            if ($this->messageType === MessageType::CLINICAL_DOCUMENT) {
                if (empty($this->childLoinc)) {
                    $this->diagnosticCode = trim($this->masterXML->disease->code);
                    $this->diagnosticCodingSystem = trim($this->masterXML->disease->codingSystem);
                    $this->isPregnancyByLoinc = false;
                    $this->validSpecimen = SPECIMEN_VALID;
                } else {
                    $this->diagnosticCode = null;
                    $this->diagnosticCodingSystem = null;
                    $this->isPregnancyByLoinc = VocabUtils::isPregnancyIndicatedByLoinc($this->dbConn, $this->childLoinc, $this->labId);
                    
                    if (empty($this->specimenSource)) {
                        $this->specimenSource = CodedDataUtils::getCodedValueFromDescription($this->getAppClient(), 'specimen', 'Unknown');
                    }
                    
                    $this->validSpecimen = EmsaMessageUtils::isValidSpecimenSource($this->dbConn, $this);
                }
                
                if (!empty($this->masterXML->poison->referral->patient->id)) {
                    // Poison Control Referral
                    $this->allowNewCmr = CaseManagementRulesEngine::CMR_YES;
                    $this->isSurveillance = CaseManagementRulesEngine::CMR_YES;
                    $this->allowUpdateCmr = CaseManagementRulesEngine::CMR_YES;
                } else {
                    $cmrEngine = new CaseManagementRulesEngine($this->dbConn, $this);
                    $this->allowNewCmr = $cmrEngine->getAllowNewCmr();
                    $this->isSurveillance = $cmrEngine->getIsSurveillance();
                    $this->allowUpdateCmr = $cmrEngine->getAllowUpdateCmr();
                }
            } else {
                $cmrEngine = new CaseManagementRulesEngine($this->dbConn, $this);
                $this->allowNewCmr = $cmrEngine->getAllowNewCmr();
                $this->isSurveillance = $cmrEngine->getIsSurveillance();
                $this->allowUpdateCmr = $cmrEngine->getAllowUpdateCmr();

                $this->isPregnancyByLoinc = VocabUtils::isPregnancyIndicatedByLoinc($this->dbConn, $this->childLoinc, $this->labId);
                $this->validSpecimen = EmsaMessageUtils::isValidSpecimenSource($this->dbConn, $this);
            }
        }
    }

    /**
     * Sets the Application XML value to the supplied XML string and set Application XML-derived message properties.
     *
     * @param bool $showIncomplete [Optional; Default <b>FALSE</b>] If <b>TRUE</b>, suppress exceptions that would prevent displaying of incomplete messages (i.e. when in Exceptions queue list)
     *
     * @throws VocabularyException
     */
    protected function setApplicationXML($showIncomplete = false)
    {
        if (!empty($this->applicationXML)) {
            $appRecord = $this->getAppClient()->getNewAppRecord();
            $appRecord->setAppRecordDocument($this->applicationXML->person->personCondition);
            $appRecord->setPersonId($this->person->getPersonId());
            $this->person->addRecord($appRecord);
            
            if (isset($this->applicationXML->person->birthGender->code) && (strlen(trim($this->applicationXML->person->birthGender->code)) > 0)) {
                $this->person->setGender(CodedDataUtils::getCodeDescriptionFromCodedValue($this->getAppClient(), 'gender', (string) $this->applicationXML->person->birthGender->code));
            }
            
            if (isset($this->applicationXML->person->race->code) && (strlen(trim($this->applicationXML->person->race->code)) > 0)) {
                $this->person->addRace(CodedDataUtils::getCodeDescriptionFromCodedValue($this->getAppClient(), 'race', (string) $this->applicationXML->person->race->code));
            }
            
            if (isset($this->applicationXML->person->ethnicity->code) && (strlen(trim($this->applicationXML->person->ethnicity->code)) > 0)) {
                $this->person->setEthnicity(CodedDataUtils::getCodeDescriptionFromCodedValue($this->getAppClient(), 'ethnicity', (string) $this->applicationXML->person->ethnicity->code));
            }
            
            if (isset($this->applicationXML->person->language->code) && (strlen(trim($this->applicationXML->person->language->code)) > 0)) {
                $this->person->setLanguage(CodedDataUtils::getCodeDescriptionFromCodedValue($this->getAppClient(), 'language', (string) $this->applicationXML->person->language->code));
            }

            $this->isPregnancyByMapping = false;
            
            if (!empty($this->applicationXML->person->personPregnantElr)) {
                foreach ($this->applicationXML->person->personPregnantElr as $personPregnantElr) {
                    // todo:  'V22.0' check is a temporary hack to support parallel testing
                    // after EpiTrax launch, refactor to do this better
                    if (!empty($personPregnantElr->pregnant) && (((string) $personPregnantElr->pregnant === 'Yes') || ((string) $personPregnantElr->pregnant === 'V22.0'))) {
                        $this->isPregnancyByMapping = $this->isPregnancyByMapping || true;
                    }
                }
            }

            // add personFacilities
            if (!empty($this->applicationXML->person->personCondition->personFacility)) {
                foreach ($this->applicationXML->person->personCondition->personFacility as $personFacilityXML) {
                    $this->person->addPersonFacility(new PersonFacility($personFacilityXML));
                }
            }

            // override 'Unknown' visit type with Discharge Disposition, if available
            if (!empty($this->applicationXML->person->personCondition->personFacility->facilityVisitType->code)) {
                $hospitalizedStatus = \Udoh\Emsa\Utils\CodedDataUtils::getCodeDescriptionFromCodedValue($this->getAppClient(), 'facility_visit_type', (string) $this->applicationXML->person->personCondition->personFacility->facilityVisitType->code);

                if ($hospitalizedStatus == 'Unknown') {
                    // if "Unknown" visit type (i.e. "Emergency Room") and a Discharge Disposition is present, see if Discharge Disposition overrides
                    if (!empty($this->masterXML->hospital_info->discharge_disposition)) {
                        // try first with old hospital_info mapping...
                        $decodedDischargeDisposition = \Udoh\Emsa\Utils\VocabUtils::getAppCodedValueFromChildCode($this->dbConn, (string)$this->masterXML->hospital_info->discharge_disposition, 'discharge_disposition', $this->labId, $this->getApplicationId());

                        if (empty($decodedDischargeDisposition) && !$showIncomplete) {
                            throw new VocabularyException("Discharge Disposition: {$this->masterXML->hospital_info->discharge_disposition}");
                        }

                        if ($decodedDischargeDisposition == 'inpatient' || $decodedDischargeDisposition == 'outpatient') {
                            $this->applicationXML->person->personCondition->personFacility->facilityVisitType->code = $decodedDischargeDisposition;
                        }
                    } elseif (!empty($this->masterXML->person_facilities->discharge_disposition)) {
                        // then fall back to new person_facilities mapping...
                        $decodedDischargeDisposition = \Udoh\Emsa\Utils\VocabUtils::getAppCodedValueFromChildCode($this->dbConn, (string)$this->masterXML->person_facilities->discharge_disposition, 'discharge_disposition', $this->labId, $this->getApplicationId());

                        if (empty($decodedDischargeDisposition) && !$showIncomplete) {
                            throw new VocabularyException("Discharge Disposition: {$this->masterXML->person_facilities->discharge_disposition}");
                        }

                        if ($decodedDischargeDisposition == 'inpatient' || $decodedDischargeDisposition == 'outpatient') {
                            $this->applicationXML->person->personCondition->personFacility->facilityVisitType->code = $decodedDischargeDisposition;
                        }
                    }
                }
            }

            try {
                if (!empty($this->applicationXML->person->personCondition->lab->collectionDate)) {
                    $collectionDate = DateTimeUtils::createMixed((string) $this->applicationXML->person->personCondition->lab->collectionDate);
                } else {
                    $collectionDate = null;
                }
            } catch (Throwable $e) {
                ExceptionUtils::logException($e);
                $collectionDate = null;
            }

            try {
                if (!empty($this->applicationXML->person->personCondition->lab->labTest->labTestDate)) {
                    $labTestDate = DateTimeUtils::createMixed((string) $this->applicationXML->person->personCondition->lab->labTest->labTestDate);
                } else {
                    $labTestDate = null;
                }
            } catch (Throwable $e) {
                ExceptionUtils::logException($e);
                $labTestDate = null;
            }

            // collect comparison data for use later in whitelist/existing lab rules
            $condition = $appRecord->getConditionName();
            $jurisdiction = CodedDataUtils::getCodeDescriptionFromId($this->getAppClient(), 'agency', (int) $this->applicationXML->person->personCondition->agency->id);
            
            if (!empty($this->applicationXML->person->personCondition->lab->labTest)) {
                if (isset($this->applicationXML->person->personCondition->lab->labTest->labTestResult->organism->code)) {
                    $organism = CodedDataUtils::getCodeDescriptionFromCodedValue($this->getAppClient(), 'organisms', (string) $this->applicationXML->person->personCondition->lab->labTest->labTestResult->organism->code);
                } else {
                    $organism = null;
                }
                
                $accessionNumber = (string) $this->applicationXML->person->personCondition->lab->accessionNo;
                $performingLabName = (string) $this->applicationXML->person->personCondition->lab->labFacility->name;
                $specimenSource = CodedDataUtils::getCodeDescriptionFromCodedValue($this->getAppClient(), 'specimen', (string) $this->applicationXML->person->personCondition->lab->specimenSource->code);
                $testType = CodedDataUtils::getCodeDescriptionFromCodedValue($this->getAppClient(), 'common_test_types', (string) $this->applicationXML->person->personCondition->lab->labTest->testType->code);
                
                if (isset($this->applicationXML->person->personCondition->lab->labTest->labTestResult->testResult->code)) {
                    $testResult = CodedDataUtils::getCodeDescriptionFromCodedValue($this->getAppClient(), 'test_result', (string) $this->applicationXML->person->personCondition->lab->labTest->labTestResult->testResult->code);
                } else {
                    $testResult = null;
                }
                
                $resultValue = (string) $this->applicationXML->person->personCondition->lab->labTest->labTestResult->resultValue;
                $testStatus = CodedDataUtils::getCodeDescriptionFromCodedValue($this->getAppClient(), 'lab_test_status', (string) $this->applicationXML->person->personCondition->lab->labTest->testStatus->code);
                $labComment = (string) $this->applicationXML->person->personCondition->lab->labTest->labTestResult->comment;
                $masterLOINCCode = (string) $this->applicationXML->person->personCondition->lab->labTest->loincCode;
                
                // check for susceptibilities...
                if (!empty($this->applicationXML->person->personCondition->lab->labTest->labTestResult->labResistTest)) {
                    if (!empty($this->applicationXML->person->personCondition->lab->labTest->labTestResult->labResistTest->testAgent->code)) {
                        $resistAgentName = CodedDataUtils::getCodeDescriptionFromCodedValue($this->getAppClient(), 'resist_test_agent', (string) $this->applicationXML->person->personCondition->lab->labTest->labTestResult->labResistTest->testAgent->code);
                        
                        if (empty($resistAgentName) && !$showIncomplete) {
                            throw new VocabularyException("Antimicrobial Agent: {$this->applicationXML->person->personCondition->lab->labTest->labTestResult->labResistTest->testAgent->code}");
                        }
                    } else {
                        $resistAgentName = null;
                    }
                    
                    if (!empty($this->applicationXML->person->personCondition->lab->labTest->labTestResult->labResistTest->testResult->code)) {
                        $resistTestResult = CodedDataUtils::getCodeDescriptionFromCodedValue($this->getAppClient(), 'resist_test_result', (string) $this->applicationXML->person->personCondition->lab->labTest->labTestResult->labResistTest->testResult->code);
                        
                        if (empty($resistTestResult) && !$showIncomplete) {
                            throw new VocabularyException("Susceptibility Test Result: {$this->applicationXML->person->personCondition->lab->labTest->labTestResult->labResistTest->testResult->code}");
                        }
                    } else {
                        $resistTestResult = null;
                    }
                    
                    $resistResultValue = (string) $this->applicationXML->person->personCondition->lab->labTest->labTestResult->labResistTest->resultValue;
                    
                    try {
                        $resistTestDate = DateTimeUtils::createMixed((string) $this->applicationXML->person->personCondition->lab->labTest->labTestResult->labResistTest->testDate);
                    } catch (Throwable $ex) {
                        $ex = null;
                        $resistTestDate = null;
                    }
                } else {
                    $resistAgentName = null;
                    $resistTestResult = null;
                    $resistResultValue = null;
                    $resistTestDate = null;
                }
            } else {
                $resistAgentName = null;
                $resistTestResult = null;
                $resistResultValue = null;
                $resistTestDate = null;
                $organism = null;
                $accessionNumber = null;
                $performingLabName = null;
                $specimenSource = null;
                $testType = null;
                $testResult = null;
                $resultValue = null;
                $testStatus = null;
                $labComment = null;
                $masterLOINCCode = null;
            }
            
            if (!empty($resistAgentName)) {
                $resistTest = new CompareSourceSusceptibility($resistAgentName, $resistTestResult, $resistResultValue, $resistTestDate);
            } else {
                $resistTest = null;
            }
            
            $this->source = new CompareSourceLab(
                $condition, 
                $organism, 
                $collectionDate,
                $labTestDate,
                $accessionNumber, 
                $performingLabName, 
                $specimenSource, 
                $testType, 
                $testResult, 
                $resultValue, 
                $testStatus, 
                $labComment, 
                $masterLOINCCode, 
                $jurisdiction,
                $resistTest
            );
        }
    }
    
    /**
     * Find any mapped AST data, extract it, and update the parent lab test data.
     * 
     * @return bool
     */
    protected function extractSusceptibilityResults()
    {
        $agentMasterId = (int) VocabUtils::getAntimicrobialAgentByLoinc($this->dbConn, $this->masterLoincCode);
        $susceptibilityExists = false;
        
        if (isset($this->masterXML->labs->susceptibility->agent)) {
            $susceptibilityExists = true;
        }
        
        if ($susceptibilityExists && ($this->masterXML->labs->test_result != 'POSITIVE')) {
            // susceptibility exists, but parent result something other than POSITIVE
            // indicates message has been retried or edited; rebuild susceptibility
            $susceptibilityExists = false;
        }
        
        if (($agentMasterId > 0) && !$susceptibilityExists) {
            // agent identified and not yet extracted...
            // store original values first, in case of latter need to retry/reprocess message
            if (!empty($this->masterXML->labs->collection_date)) {
                $this->masterXML->labs->original_collection_date = trim($this->masterXML->labs->collection_date);
            }
            if (!empty($this->masterXML->labs->accession_number)) {
                $this->masterXML->labs->original_accession_number = trim($this->masterXML->labs->accession_number);
            }
            if (!empty($this->masterXML->labs->specimen_source)) {
                $this->masterXML->labs->original_specimen_source = trim($this->masterXML->labs->specimen_source);
            }
            if (!empty($this->masterXML->labs->loinc_code)) {
                $this->masterXML->labs->original_loinc_code = trim($this->masterXML->labs->loinc_code);
            }
            if (!empty($this->masterXML->labs->lab_test_date)) {
                $this->masterXML->labs->original_test_date = trim($this->masterXML->labs->lab_test_date);
            }
            if (!empty($this->masterXML->labs->result_value)) {
                $this->masterXML->labs->original_result_value = trim($this->masterXML->labs->result_value);
            }
            if (!empty($this->masterXML->labs->units)) {
                $this->masterXML->labs->original_units = trim($this->masterXML->labs->units);
            }
            if (!empty($this->masterXML->labs->test_result)) {
                $this->masterXML->labs->original_test_result = trim($this->masterXML->labs->test_result);
            }
            if (!empty($this->masterXML->labs->abnormal_flag)) {
                $this->masterXML->labs->original_abnormal_flag = trim($this->masterXML->labs->abnormal_flag);
            }
            if (!empty($this->masterXML->labs->reference_range)) {
                $this->masterXML->labs->original_reference_range = trim($this->masterXML->labs->reference_range);
            }
            if (!empty($this->masterXML->labs->test_status)) {
                $this->masterXML->labs->original_test_status = trim($this->masterXML->labs->test_status);
            }
            
            // find antimicrobial agent identified by Master LOINC code
            $agentAppCodedValue = VocabUtils::appCodedValueByMasterID($this->dbConn, $agentMasterId, $this->applicationId);
            
            $this->masterXML->labs->susceptibility->agent = trim($agentAppCodedValue);
            if (!empty($this->masterXML->labs->test_result)) {
                $this->masterXML->labs->susceptibility->test_result = trim($this->masterXML->labs->test_result);
            }
            if (!empty($this->masterXML->labs->result_value)) {
                $this->masterXML->labs->susceptibility->result_value = trim($this->masterXML->labs->result_value);
            }
            if (!empty($this->masterXML->labs->lab_test_date)) {
                $this->masterXML->labs->susceptibility->test_date = trim($this->masterXML->labs->lab_test_date);
            }
            
            $this->applicationXML->person->personCondition->lab->labTest->labTestResult->labResistTest->testAgent->code = trim($agentAppCodedValue);
            if (!empty($this->applicationXML->person->personCondition->lab->labTest->labTestResult->testResult->code)) {
                $this->applicationXML->person->personCondition->lab->labTest->labTestResult->labResistTest->testResult->code = trim($this->applicationXML->person->personCondition->lab->labTest->labTestResult->testResult->code);
            }
            if (!empty($this->applicationXML->person->personCondition->lab->labTest->labTestResult->resultValue)) {
                $this->applicationXML->person->personCondition->lab->labTest->labTestResult->labResistTest->resultValue = trim($this->applicationXML->person->personCondition->lab->labTest->labTestResult->resultValue);
            }
            if (!empty($this->applicationXML->person->personCondition->lab->labTest->labTestDate)) {
                $this->applicationXML->person->personCondition->lab->labTest->labTestResult->labResistTest->testDate = trim($this->applicationXML->person->personCondition->lab->labTest->labTestDate);
            }
            
            // set parent culture test result to positive, clear result value
            unset($this->masterXML->labs->result_value);
            unset($this->masterXML->labs->units);
            unset($this->masterXML->labs->reference_range);
            $this->masterXML->labs->test_result = 'POSITIVE';
            
            unset($this->applicationXML->person->personCondition->lab->labTest->labTestResult->resultValue);
            unset($this->applicationXML->person->personCondition->lab->labTest->labTestResult->units);
            unset($this->applicationXML->person->personCondition->lab->labTest->referenceRange);
            $this->applicationXML->person->personCondition->lab->labTest->labTestResult->testResult->code = 'POSITIVE';
            
            // update lab test data with inherited data from parent result...
            // collection date
            if (!empty($this->masterXML->labs->parent_collection_date)) {
                $this->masterXML->labs->collection_date = (string) $this->masterXML->labs->parent_collection_date;
                $this->applicationXML->person->personCondition->lab->collectionDate = (string) $this->masterXML->labs->parent_collection_date;
            }
            
            // test date
            if (!empty($this->masterXML->labs->parent_lab_test_date)) {
                $this->masterXML->labs->lab_test_date = (string) $this->masterXML->labs->parent_lab_test_date;
                $this->applicationXML->person->personCondition->lab->labTest->labTestDate = (string) $this->masterXML->labs->parent_lab_test_date;
            }
            
            // loinc code
            if (!empty($this->masterXML->labs->parent_loinc_code)) {
                $this->masterXML->labs->loinc_code = (string) $this->masterXML->labs->parent_loinc_code;
                $this->applicationXML->person->personCondition->lab->labTest->loincCode = (string) $this->masterXML->labs->parent_loinc_code;
            }
            
            // accession number
            if (!empty($this->masterXML->labs->parent_accession_number)) {
                $this->masterXML->labs->accession_number = (string) $this->masterXML->labs->parent_accession_number;
                $this->applicationXML->person->personCondition->lab->accessionNo = (string) $this->masterXML->labs->parent_accession_number;
            }
            
            // abnormal flag
            if (!empty($this->masterXML->labs->parent_abnormal_flag)) {
                $this->masterXML->labs->abnormal_flag = (string) $this->masterXML->labs->parent_abnormal_flag;
            }
            
            // specimen source
            if (!empty($this->masterXML->labs->parent_specimen_source)) {
                $specimenSourceCoded = VocabUtils::getAppCodedValueFromChildCode($this->dbConn, (string) $this->masterXML->labs->parent_specimen_source, 'specimen', $this->labId, $this->applicationId);
                $this->masterXML->labs->specimen_source = $specimenSourceCoded;
                $this->applicationXML->person->personCondition->lab->specimenSource->code = $specimenSourceCoded;
            }
            
            // test status
            if (!empty($this->masterXML->labs->parent_test_status)) {
                $testStatusCoded = VocabUtils::getAppCodedValueFromChildCode($this->dbConn, (string) $this->masterXML->labs->parent_test_status, 'test_status', $this->labId, $this->applicationId);
                $this->masterXML->labs->test_status = $testStatusCoded;
                $this->applicationXML->person->personCondition->lab->labTest->testStatus->code = $testStatusCoded;
            }
            
            // update db with changes to XML structure
            $this->saveXMLChangesToDb();
        }
        
        return false;
    }

    /**
     * Load the specified EMSA message from the database.
     *
     * @param bool $requireValidMasterLoinc [Optional]<br>If <b>TRUE</b>, requires the Master LOINC found in <i>system_messages</i> to be a valid, configured Master LOINC.  Default <b>TRUE</b>.
     * @param bool $silent                  [Optional; Default <b>FALSE</b>] If <b>TRUE</b>, suppress writing messages to Audit Log where used.
     * @param bool $showIncomplete          [Optional; Default <b>FALSE</b>] If <b>TRUE</b>, suppress exceptions that would prevent displaying of incomplete messages (i.e. when in Exceptions queue list)
     *
     * @throws VocabularyException
     * @throws Exception
     *
     * @return bool
     */
    protected function loadFromDb($requireValidMasterLoinc = true, $silent = false, $showIncomplete = false)
    {
        if ($requireValidMasterLoinc) {
            $sql = 'SELECT 
						sm.original_message_id AS original_message_id, sm.vocab_app_id as vocab_app_id, sm.final_status AS final_status, sm.deleted AS deleted, sm.loinc_code AS loinc_code, sm.local_result_value AS local_result_value, sm.local_result_value_2 AS local_result_value_2, sm.master_xml AS master_xml, sm.transformed_xml AS transformed_xml, sm.lab_id AS lab_id, sm.fname AS fname, sm.lname AS lname, sm.dob AS dob, 
                        sm.reported_at AS reported_at, sm.assigned_date AS assigned_date, sm.event_id AS event_id, sm.copy_parent_id AS copy_parent_id, sm.o2m_performed AS o2m_performed, sm.o2m_event_id AS o2m_event_id, sm.message_flags AS message_flags, 
						mc.ignore_age_rule AS ignore_age_rule, mc.white_rule AS whitelist_rule, mc.contact_white_rule AS contact_whitelist_rule, mc.whitelist_override AS whitelist_override, mc.whitelist_ignore_case_status AS whitelist_ignore_case_status, mc.allow_multi_assign AS allow_multi_assign, mc.notify_state AS notify_state, mc.immediate_notify AS immediate_notify, mc.district_override AS district_override, mc.check_xref_first AS check_xref_first, mc.ast_multi_colony AS ast_multi_colony, mc.bypass_oos AS bypass_oos, 
						mv.concept AS condition, l.ui_name AS lab_name, l.ecrlab AS ecrlab 
					FROM system_messages sm 
					INNER JOIN vocab_master_loinc ml ON (sm.id = :systemMessageId AND sm.loinc_code = ml.loinc) 
					INNER JOIN structure_labs l ON (sm.lab_id = l.id) 
					LEFT JOIN vocab_master_vocab mv ON ((sm.disease = mv.concept) AND (mv.category = elr.vocab_category_id(\'condition\'))) 
					LEFT JOIN vocab_master_condition mc ON (mv.id = mc.condition);';
        } else {
            $sql = 'SELECT 
						sm.original_message_id AS original_message_id, sm.vocab_app_id as vocab_app_id, sm.final_status AS final_status, sm.deleted AS deleted, sm.loinc_code AS loinc_code, sm.local_result_value AS local_result_value, sm.local_result_value_2 AS local_result_value_2, sm.master_xml AS master_xml, sm.transformed_xml AS transformed_xml, sm.lab_id AS lab_id, sm.fname AS fname, sm.lname AS lname, sm.dob AS dob, 
                        sm.reported_at AS reported_at, sm.assigned_date AS assigned_date, sm.event_id AS event_id, sm.copy_parent_id AS copy_parent_id, sm.o2m_performed AS o2m_performed, sm.o2m_event_id AS o2m_event_id, sm.message_flags AS message_flags, 
						mc.ignore_age_rule AS ignore_age_rule, mc.white_rule AS whitelist_rule, mc.contact_white_rule AS contact_whitelist_rule, mc.whitelist_override AS whitelist_override, mc.whitelist_ignore_case_status AS whitelist_ignore_case_status, mc.allow_multi_assign AS allow_multi_assign, mc.notify_state AS notify_state, mc.immediate_notify AS immediate_notify, mc.district_override AS district_override, mc.check_xref_first AS check_xref_first, mc.ast_multi_colony AS ast_multi_colony, mc.bypass_oos AS bypass_oos, 
						mv.concept AS condition, l.ui_name AS lab_name, l.ecrlab AS ecrlab 
					FROM system_messages sm 
					INNER JOIN structure_labs l ON (sm.lab_id = l.id) 
					LEFT JOIN vocab_master_vocab mv ON ((sm.disease = mv.concept) AND (mv.category = elr.vocab_category_id(\'condition\'))) 
					LEFT JOIN vocab_master_condition mc ON (mv.id = mc.condition)
					WHERE sm.id = :systemMessageId;';
        }
        $stmt = $this->dbConn->prepare($sql);
        $stmt->bindValue(':systemMessageId', $this->getSystemMessageId(), PDO::PARAM_INT);

        if ($stmt->execute()) {
            $row = $stmt->fetchObject();
            if ($row !== false) {
                $this->applicationId = (int) $row->vocab_app_id;
                $this->originalMessageId = (int) $row->original_message_id;
                $this->finalStatus = (int) $row->final_status;
                $this->isDeleted = ((int) $row->deleted === 1);
                
                if (!empty($row->reported_at)) {
                    try {
                        $this->dateReported = DateTimeUtils::createMixed($row->reported_at);
                    } catch (Throwable $ex) {
                        $ex = null;
                        $this->dateReported = null;
                    }
                }
                
                if (!empty($row->assigned_date)) {
                    try {
                        $this->dateAssigned = DateTimeUtils::createMixed($row->assigned_date);
                    } catch (Throwable $ex) {
                        $ex = null;
                        $this->dateAssigned = null;
                    }
                }
                
                $this->masterLoincCode = $row->loinc_code;
                $this->localResultValue1 = $row->local_result_value;
                $this->localResultValue2 = $row->local_result_value_2;
                $this->labId = $row->lab_id;
                $this->assignedEventId = $row->event_id;
                $this->copyParentId = $row->copy_parent_id;
                $this->o2mPerformed = $row->o2m_performed;
                $this->o2mTargetEventId = $row->o2m_event_id;
                $this->messageFlags = $row->message_flags;
                $this->senderName = trim($row->lab_name);
                $this->isSenderAssigningECRLabs = (bool) $row->ecrlab;
                $this->masterCondition = $row->condition;
                $this->checkXrefFirst = $row->check_xref_first;
                $this->notifyState = $row->notify_state;
                $this->isImmediate = $row->immediate_notify;
                $this->districtOverride = $row->district_override;
                $this->filterRuleRaw = (string) filter_var($row->ignore_age_rule, FILTER_SANITIZE_STRING);
                $this->filterRule = new MessageFilterRule($this->filterRuleRaw);
                $this->allowOneToMany = $row->allow_multi_assign;
                $this->astMultiColony = $row->ast_multi_colony;
                $this->bypassOOS = $row->bypass_oos;
                
                try {
                    $this->whitelistRules = (new WhitelistRuleSet())
                            ->setMorbidityWhitelistRule(new WhitelistRule((string) $row->whitelist_rule, (bool) $row->whitelist_override, (bool) $row->whitelist_ignore_case_status))
                            ->setContactWhitelistRule(new ContactWhitelistRule((string) $row->contact_whitelist_rule, (bool) $row->whitelist_override, (bool) $row->whitelist_ignore_case_status));
                } catch (Throwable $e) {
                    $e = null;
                    $this->whitelistRules = new WhitelistRuleSet();
                }

                $this->loadKnittableLoincs();
                
                $this->masterXML = simplexml_load_string(trim($row->master_xml));
                $this->applicationXML = simplexml_load_string(trim($row->transformed_xml));
                
                $this->extractSusceptibilityResults();
                
                $this->setMasterXML($silent, $showIncomplete);
                $this->setApplicationXML($showIncomplete);

                if (!empty($this->applicationXML->person->personCondition->agency->id)) {
                    $this->systemJurisdictionId = \Udoh\Emsa\Utils\AppClientUtils::getSystemJurisdictionIdFromApp($this->dbConn, $this->getApplicationId(), (int)$this->applicationXML->person->personCondition->agency->id);
                }

                $this->setCloseSurveillance();

                $this->isPregnancy = ($this->isPregnancyByLoinc || $this->isPregnancyByMapping);
                
                return true;
            }
        }

        return false;
    }
    
    /**
     * Gets a list of knittable Master LOINCs by lab ID
     * 
     * @throws Exception if unable to query list of knittable Master LOINCs
     */
    protected function loadKnittableLoincs()
    {
        $sql = "SELECT lab_id, loinc
                FROM structure_knittable_loincs;";
        $rs = $this->dbConn->query($sql);
        if ($rs !== false) {
            while ($row = $rs->fetchObject()) {
                $this->knittableLoincs[intval($row->lab_id)][] = trim($row->loinc);
            }
        } else {
            throw new Exception('Unable to retrieve list of knittable LOINCs');
        }
    }

}

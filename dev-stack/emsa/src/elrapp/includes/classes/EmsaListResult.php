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

use Udoh\Emsa\Client\AppClientInterface;
use Udoh\Emsa\Client\AppClientList;
use Udoh\Emsa\Constants\VocabTable;
use Udoh\Emsa\Model\Address;
use Udoh\Emsa\Model\PersonFacility;
use Udoh\Emsa\UI\Queue\EmsaListResultSet;
use Udoh\Emsa\Utils\AppClientUtils;
use Udoh\Emsa\Utils\CodedDataUtils;
use Udoh\Emsa\Utils\DateTimeUtils;
use Udoh\Emsa\Utils\DisplayUtils;
use Udoh\Emsa\Utils\ExceptionUtils;
use Udoh\Emsa\Utils\VocabUtils;

/**
 * Individual result of an EMSA Queue List query
 *
 * @author Josh Ridderhoff <jridderhoff@utah.gov>
 */
class EmsaListResult
{

    /** @var HTMLPurifier */
    protected $purifier;
    /**@var AppClientInterface */
    protected $appClient;
    protected $appId;
    /** @var bool */
    protected $isDeleted;
    /** @var int */
    protected $emsaMessageType;
    public $createdAt;
    public $createdAtLong;
    public $assignedAt;
    public $assignedAtLong;
    public $dateReported;
    public $dateCollected;
    public $dateTested;
    public $type;
    public $id;
    public $originalMessageId;
    public $nedssEventId;
    public $copyParentId;
    public $lab;
    public $labId;
    public $firstName;
    public $lastName;
    public $middleName;
    public $fullName;
    public $gender;
    public $race;
    public $ethnicity;
    public $bday;
    public $birthDate;
    public $age;
    public $phone;
    public $email;
    public $street;
    public $unit;
    public $city;
    public $state;
    public $zip;
    public $zipCode;
    public $county;
    public $addressIsValid;
    public $jurisdiction;
    public $condition;
    public $organism;
    public $specimenSource;
    public $referenceRange;
    public $accessionNumber;
    private $hasLabResults;
    public $obrId;
    public $loincCode;
    public $units;
    public $resultValue;
    public $testStatus;
    public $testResult;
    public $testResultClass;
    public $testType;
    public $comment;
    public $abnormalFlag;
    public $localSpecimenSource;
    public $localReferenceRange;
    public $localTestStatus;
    public $localLoincCode;
    public $localTestName;
    public $localCode;
    public $localCodeTestName;
    public $localUnits;
    public $localResultValue;
    public $localResultValue2;
    public $hospitalized;
    public $medicalRecordNumber;
    public $clinicianName;
    public $clinicianPhone;
    public $diagnosticFacility;
    public $diagnosticStreet;
    public $diagnosticUnit;
    public $diagnosticCity;
    public $diagnosticState;
    public $diagnosticZip;
    public $reportingAgency;
    public $stateCaseStatus;
    public $errors;
    /** @var array */
    private $flags = array();

    /** @var PersonFacility[] */
    protected $healthcareFacilities = array();

    /** @var EmsaListResultSet */
    private $panelResults;
    /** @var EmsaListResultSet */
    private $copyPanelResults;

    /** @var array */
    private $clinicalPregnancyDiagnoses = array();
    /** @var array */
    private $clinicalTreatments = array();
    /** @var array */
    private $antimicrobialSusceptibilities = array();

    /** @var DateTime */
    protected $interagencyDateSent;
    /** @var string */
    protected $interagencyFilename;
    /** @var string */
    protected $interagencyRecipient;

    /**
     * Creates a new EMSA List Result item.
     *
     * @param PDO           $dbConn
     * @param EmsaMessage   $emsaMessage
     * @param AppClientList $appClientList
     * @param boolean       $buildPanel [Optional, default <b>TRUE</b>]<br>If <b>TRUE</b>, find associated lab results
     *                                  and add them to the same-panel results
     *
     * @throws Exception
     */
    public function __construct(PDO $dbConn, EmsaMessage $emsaMessage, AppClientList $appClientList, $buildPanel = true)
    {
        $this->isDeleted = false;
        $this->hasLabResults = false;

        $defaultHTMLPurifierConfig = HTMLPurifier_Config::createDefault();
        $this->purifier = new HTMLPurifier($defaultHTMLPurifierConfig);
        
        $this->buildMessage($dbConn, $emsaMessage);
        $this->loadInteragencyData($dbConn, $emsaMessage->getOriginalMessageId());
        
        if ($buildPanel && !empty($emsaMessage->getOriginalMessageId())) {
            $isPaneledQueue = (($this->type == SEMI_AUTO_STATUS) || ($this->type == QA_STATUS) || ($this->type == EXCEPTIONS_STATUS) || ($this->type == NEDSS_EXCEPTION_STATUS));
            
            if (isset($_SESSION[EXPORT_SERVERNAME]['override_user_role']) && (intval($_SESSION[EXPORT_SERVERNAME]['override_user_role']) > 0)) {
                // specific role selected from role override menu; use selected role
                $isAdminUser = (isset($_SESSION[EXPORT_SERVERNAME]['override_is_admin']) && ($_SESSION[EXPORT_SERVERNAME]['override_is_admin'] === true));
                $isQaUser = (isset($_SESSION[EXPORT_SERVERNAME]['override_is_qa']) && ($_SESSION[EXPORT_SERVERNAME]['override_is_qa'] === true));
            } else {
                $isAdminUser = (isset($_SESSION[EXPORT_SERVERNAME]['is_admin']) && ($_SESSION[EXPORT_SERVERNAME]['is_admin'] === true));
                $isQaUser = (isset($_SESSION[EXPORT_SERVERNAME]['is_qa']) && ($_SESSION[EXPORT_SERVERNAME]['is_qa'] === true));
            }

            if ($isPaneledQueue || $isAdminUser || $isQaUser) {
                // only show same-panel results if user is in the Semi-Auto, QA, or Exception queues, 
                // or if the user is designated as an Admin or QA user
                $this->buildPanel($dbConn, $emsaMessage, $appClientList);
                $this->buildCopyPanel($dbConn, $emsaMessage, $appClientList);
            }
        }
    }

    /**
     * Load current message from database.
     * 
     * @param PDO $dbConn
     * @param EmsaMessage $emsaMessage
     * 
     * @throws Exception
     */
    private function buildMessage(PDO $dbConn, EmsaMessage $emsaMessage)
    {
        $this->appId = $emsaMessage->getApplicationId();
        $this->appClient = $emsaMessage->getAppClient();
        $this->isDeleted = $emsaMessage->getIsDeleted();
        $this->emsaMessageType = $emsaMessage->getMessageType();
        
        $tempCreatedAt = $emsaMessage->getDateReported(true, "m/d/Y (g:ia)");
        $tempCreatedAtLong = $emsaMessage->getDateReported(true, DATE_RFC3339);
        
        $tempAssignedAt = $emsaMessage->getDateAssigned(true, "m/d/Y (g:ia)");
        $tempAssignedAtLong = $emsaMessage->getDateAssigned();

        $masterXML = $emsaMessage->getMasterXML();
        $appXML = $emsaMessage->getApplicationXML();

        if (count($masterXML) > 0) {
            $this->hasLabResults = (isset($masterXML->labs) && (count($masterXML->labs) > 0));
            $this->panelResults = new EmsaListResultSet();
            $this->copyPanelResults = new EmsaListResultSet();
            $this->type = $emsaMessage->getFinalStatus();
            $this->id = $emsaMessage->getSystemMessageId();
            $this->originalMessageId = $emsaMessage->getOriginalMessageId();
            $this->nedssEventId = $emsaMessage->getAssignedEventId();
            $this->copyParentId = $emsaMessage->getCopyParentId();
            $this->lab = isset($appXML->person->personCondition->lab->labFacility->name) ? (string) $appXML->person->personCondition->lab->labFacility->name : '';
            $this->labId = $emsaMessage->labId;
            $this->errors = (string) $masterXML->sourceid->exception_status;
            try {
                $this->dateReported = (strlen(trim($masterXML->reporting->report_date)) > 0) ? DateTimeUtils::createMixed(trim($masterXML->reporting->report_date))->format("m/d/Y g:ia") : '';
            } catch (Throwable $ex) {
                $this->dateReported = '';
            }

            $this->setFlags($emsaMessage->getMessageFlags());

            if ((int) $masterXML->administrative->jurisdictionId > 0) {
                $this->jurisdiction = DisplayUtils::xSafe(\EmsaUtils::lhdName($dbConn, AppClientUtils::getSystemJurisdictionIdFromApp($dbConn, $this->appId, (int) $masterXML->administrative->jurisdictionId)));
            } else {
                $this->jurisdiction = '<strong style="color: red;">[Not Set]</strong>';
            }

            $this->firstName = $emsaMessage->getPerson()->getFirstName();
            $this->lastName = $emsaMessage->getPerson()->getLastName();
            $this->middleName = $emsaMessage->getPerson()->getMiddleName();
            $this->fullName = DisplayUtils::formatNameLastFirstMiddle($this->lastName, $this->firstName, $this->middleName);
            $this->gender = ((strlen(trim($masterXML->person->gender)) > 0) ? VocabUtils::getMasterConceptFromChildCode($dbConn, (string) $masterXML->person->gender, 'gender', $this->labId) : '--');
            $this->race = VocabUtils::getMasterConceptFromChildCode($dbConn, (string) $masterXML->person->race, 'race', $this->labId);
            $this->ethnicity = VocabUtils::getMasterConceptFromChildCode($dbConn, (string) $masterXML->person->ethnicity, 'ethnicity', $this->labId);

            if (strlen(trim($masterXML->person->date_of_birth)) > 5) {
                try {
                    $this->bday = DateTimeUtils::createMixed((string) $masterXML->person->date_of_birth);
                    $this->birthDate = DateTimeUtils::createMixed((string) $masterXML->person->date_of_birth)->format("m/d/Y");
                    $this->age = DateTimeUtils::ageFromDob($this->bday);
                } catch (Throwable $e) {
                    ExceptionUtils::logException($e);
                    $this->bday = '';
                    $this->birthDate = '--';
                    $this->age = '--';
                }
            } else {
                $this->bday = '';
                $this->birthDate = '--';
                $this->age = '--';
            }

            $this->phone = ((strlen($masterXML->person->phone) > 0) ? DisplayUtils::formatPhoneNumber($masterXML->person->phone, $masterXML->person->area_code) : "");
            $this->email = (string) $masterXML->person->email;
            $this->street = (string) $masterXML->person->street_name;
            $this->unit = (string) $masterXML->person->unit;
            $this->city = (string) $masterXML->person->city;
            $this->state = (string) $masterXML->person->state;
            $this->zip = (string) $masterXML->person->zip;
            $this->county = (string) $masterXML->person->county;
            
            $this->createdAt = $tempCreatedAt;
            $this->createdAtLong = $tempCreatedAtLong;
            $this->assignedAt = $tempAssignedAt;
            $this->assignedAtLong = $tempAssignedAtLong;
            $this->condition = (string) $masterXML->disease->name;  //diseaseByLoinc($res['loinc_code']);
            $this->loincCode = (string) $emsaMessage->getMasterLoincCode();
            $this->units = (string) $masterXML->labs->units;
            $this->resultValue = (string) $masterXML->labs->result_value;
            $this->specimenSource = (string) CodedDataUtils::getCodeDescriptionFromCodedValue($this->appClient, 'specimen', (string) $masterXML->labs->specimen_source);
            $this->accessionNumber = (string) $masterXML->labs->accession_number;
            $this->obrId = isset($masterXML->labs->obr_id) ? (int) $masterXML->labs->obr_id : 0;
            $this->stateCaseStatus = (string) CodedDataUtils::getCodeDescriptionFromCodedValue($this->appClient, 'case', (string) $masterXML->labs->state_case_status);
            $this->testStatus = VocabUtils::getMasterConceptFromChildCode($dbConn, (string) $masterXML->labs->test_status, 'test_status', $this->labId);
            $this->testType = (string) CodedDataUtils::getCodeDescriptionFromCodedValue($this->appClient, 'common_test_types', (string) $masterXML->labs->test_type);
            $this->organism = (string) $masterXML->labs->organism;
            $this->testResult = (string) CodedDataUtils::getCodeDescriptionFromCodedValue($this->appClient, 'test_result', (string) $masterXML->labs->test_result);
            $this->comment = (string) $masterXML->labs->comment;
            $this->abnormalFlag = VocabUtils::decodeAbnormalFlag($dbConn, (string) $masterXML->labs->abnormal_flag);

            try {
                $this->dateCollected = (strlen(trim($masterXML->labs->collection_date)) > 0) ? DateTimeUtils::createMixed(trim($masterXML->labs->collection_date))->format("m/d/Y g:ia") : '';
            } catch (Throwable $ex) {
                $this->dateCollected = '';
            }

            try {
                $this->dateTested = (strlen(trim($masterXML->labs->lab_test_date)) > 0) ? DateTimeUtils::createMixed(trim($masterXML->labs->lab_test_date))->format("m/d/Y g:ia") : '';
            } catch (Throwable $ex) {
                $this->dateTested = '';
            }

            $this->localSpecimenSource = VocabUtils::getMasterConceptFromChildCode($dbConn, trim($masterXML->labs->local_specimen_source), 'specimen', $this->labId);
            $this->localTestStatus = $this->testStatus;
            $this->localLoincCode = trim($masterXML->labs->local_loinc_code);
            $this->localTestName = trim($masterXML->labs->local_test_name);
            $this->localCode = trim($masterXML->labs->local_code);
            $this->localCodeTestName = trim($masterXML->labs->local_code_test_name);
            $this->localUnits = trim($masterXML->labs->local_result_unit);
            $this->localResultValue = trim($masterXML->labs->local_result_value);
            $this->localResultValue2 = trim($masterXML->labs->local_result_value_2);

            if (strlen(trim($masterXML->labs->reference_range)) > 0) {
                $this->localReferenceRange = trim($masterXML->labs->local_reference_range);
                $this->referenceRange = trim($masterXML->labs->reference_range);
            } elseif (strlen(trim($masterXML->labs->local_reference_range)) > 0) {
                $this->localReferenceRange = trim($masterXML->labs->local_reference_range);
                $this->referenceRange = trim($masterXML->labs->local_reference_range);
            } else {
                $this->localReferenceRange = '';
                $this->referenceRange = '';
            }

            if (strtolower(trim($masterXML->labs->test_result)) == 'positive') {
                $this->testResultClass = 'positive';
            } elseif (strtolower(trim($masterXML->labs->test_result)) == 'negative') {
                $this->testResultClass = 'negative';
            } elseif (strtolower(trim($masterXML->labs->test_result)) == 'susceptible') {
                $this->testResultClass = 'susceptible';
            } elseif (strtolower(trim($masterXML->labs->test_result)) == 'resistant') {
                $this->testResultClass = 'resistant';
            } elseif (strtolower(trim($masterXML->labs->test_result)) == 'intermediate') {
                $this->testResultClass = 'intermediate';
            } else {
                $this->testResultClass = 'other';
            }
            
            // override testResultClass if antimicrobial susceptibility exists in incoming message
            if (!empty($masterXML->labs->susceptibility->test_result)) {
                if (strtolower(trim($masterXML->labs->susceptibility->test_result)) == 'susceptible') {
                    $this->testResultClass = 'susceptible';
                } elseif (strtolower(trim($masterXML->labs->susceptibility->test_result)) == 'resistant') {
                    $this->testResultClass = 'resistant';
                } elseif (strtolower(trim($masterXML->labs->susceptibility->test_result)) == 'intermediate') {
                    $this->testResultClass = 'intermediate';
                } else {
                    $this->testResultClass = 'other';
                }
            }

            if (!empty($appXML->person->personCondition->personFacility->facilityVisitType->code)) {
                $this->hospitalized = CodedDataUtils::getCodeDescriptionFromCodedValue($this->appClient, 'facility_visit_type', (string) $appXML->person->personCondition->personFacility->facilityVisitType->code);
            }

            if (!empty($masterXML->hospital_info->medical_record)) {
                // try old hospital_info mapping first...
                $this->medicalRecordNumber = trim($masterXML->hospital_info->medical_record);
            } elseif (!empty($masterXML->person_facilities->medical_record_number)) {
                // then fall back to new person_facilities mapping...
                $this->medicalRecordNumber = trim($masterXML->person_facilities->medical_record_number);
            } else {
                $this->medicalRecordNumber = null;
            }

            // ordering clinician
            $this->clinicianName = DisplayUtils::formatNameLastFirstMiddle(trim($masterXML->clinicians->last_name), trim($masterXML->clinicians->first_name), trim($masterXML->clinicians->middle_name));
            $this->clinicianPhone = ((strlen($masterXML->clinicians->phone) > 0) ? DisplayUtils::formatPhoneNumber($masterXML->clinicians->phone, $masterXML->clinicians->area_code) : '') . ((strlen(trim($masterXML->clinicians->extension)) > 0) ? 'Ext. ' . $masterXML->clinicians->extension : '');

            // ordering facility
            $this->diagnosticFacility = isset($appXML->person->personCondition->lab->orderingFacility->name) ? (string) $appXML->person->personCondition->lab->orderingFacility->name : '';
            $this->diagnosticStreet = trim($masterXML->diagnostic->street_name);
            $this->diagnosticUnit = trim($masterXML->diagnostic->unit_number);
            $this->diagnosticCity = trim($masterXML->diagnostic->city);
            $this->diagnosticState = trim($masterXML->diagnostic->state);
            $this->diagnosticZip = trim($masterXML->diagnostic->zipcode);

            $this->reportingAgency = isset($appXML->person->personCondition->reportingFacility->name) ? (string) $appXML->person->personCondition->reportingFacility->name : '';

            // associated healthcare facilities

            // [jridderhoff 2019-01-10]
            // refactoring to use $appXML->person->personCondition->personFacility for
            // purpose of having discharge disposition-based hospitalization override reflected in "Full Lab" tab display
            if (isset($appXML->person->personCondition->personFacility) && (count($appXML->person->personCondition->personFacility) > 0)) {
                foreach ($appXML->person->personCondition->personFacility as $personFacility) {
                    if (!empty($personFacility->facilityVisitType->code)) {
                        $this->healthcareFacilities[] = new PersonFacility($personFacility);
                    }
                }
            }
            
            // check for pregnancy diagnoses from Clinical Documents...
            if (isset($masterXML->pregnancy) && (count($masterXML->pregnancy) > 0)) {
                foreach ($masterXML->pregnancy as $pregnancy) {
                    if (isset($pregnancy->pregnancy_diagnosis) && !empty($pregnancy->pregnancy_diagnosis)) {
                        $this->clinicalPregnancyDiagnoses[] = (string)$pregnancy->pregnancy_diagnosis;
                    }
                }
            }
            
            // check for treatments from Clnical Documents...
            if (isset($masterXML->treatments) && (count($masterXML->treatments) > 0)) {
                foreach ($masterXML->treatments as $clinicalTreatment) {
                    try {
                        $startDate = (strlen(trim($clinicalTreatment->date_of_treatment)) > 0) ? DateTimeUtils::createMixed(trim($clinicalTreatment->date_of_treatment))->format("m/d/Y") : '';
                    } catch (Throwable $e) {
                        ExceptionUtils::logException($e);
                        $startDate = '';
                    }
                    
                    try {
                        $endDate = (strlen(trim($clinicalTreatment->treatment_stopped)) > 0) ? DateTimeUtils::createMixed(trim($clinicalTreatment->treatment_stopped))->format("m/d/Y") : '';
                    } catch (Throwable $e) {
                        ExceptionUtils::logException($e);
                        $endDate = '';
                    }
                    
                    $this->clinicalTreatments[] = array(
                        'code' => (string) $clinicalTreatment->code,
                        'code_system' => (string) $clinicalTreatment->code_system,
                        'dose' => (string) $clinicalTreatment->dose_quantity,
                        'name' => (string) $clinicalTreatment->name,
                        'start' => $startDate,
                        'end' => $endDate
                    );
                }
            }
            
            // check for antimicrobial susceptibilities...
            if (isset($masterXML->labs->susceptibility) && (count($masterXML->labs->susceptibility) > 0)) {
                foreach ($masterXML->labs->susceptibility as $susceptibility) {
                    $testDateStr = null;
                    if (!empty($susceptibility->test_date)) {
                        try {
                            $testDateStr = DateTimeUtils::createMixed($susceptibility->test_date)->format("m/d/Y g:ia");
                        } catch (Throwable $ex) {
                            $ex = null;
                            $testDateStr = null;
                        }
                    }
                    
                    $this->antimicrobialSusceptibilities[] = array(
                        'agent' => (string) $susceptibility->agent,
                        'result' => (string) $susceptibility->test_result,
                        'value' => (string) $susceptibility->result_value,
                        'test_date' => (string) $testDateStr
                    );
                }
            }

            // going to be honest:  not sure what this 2nd zip code thing does;
            // need to investigate later...
            if ($masterXML->person->zip) {
                $this->zipCode = trim($masterXML->person->zip);
            } elseif ($masterXML->diagnostic->zipcode) {
                $this->zipCode = trim($masterXML->diagnostic->zipcode);
            } else {
                $this->zipCode = '';
            }
        }
    }

    /**
     * Creates a panel of associated lab results for this message.
     *
     * @param PDO           $dbConn
     * @param EmsaMessage   $emsaMessage
     * @param AppClientList $appClientList
     */
    private function buildPanel(PDO $dbConn, EmsaMessage $emsaMessage, AppClientList $appClientList)
    {
        if (empty($emsaMessage->getCopyParentId())) {
            $sql = "SELECT id
                    FROM system_messages
                    WHERE original_message_id = :originalMessageId
                    AND vocab_app_id = :appId
                    AND id <> :systemMessageId
                    AND copy_parent_id IS NULL;";
            $stmt = $dbConn->prepare($sql);
            $stmt->bindValue(':systemMessageId', $emsaMessage->getSystemMessageId(), PDO::PARAM_INT);
            $stmt->bindValue(':originalMessageId', $emsaMessage->getOriginalMessageId(), PDO::PARAM_INT);
            $stmt->bindValue(':appId', $emsaMessage->getApplicationId(), PDO::PARAM_INT);
        } else {
            $sql = "SELECT id
                    FROM system_messages
                    WHERE original_message_id = :originalMessageId
                    AND vocab_app_id = :appId
                    AND id <> :systemMessageId
                    AND copy_parent_id IS NOT NULL
                    AND copy_parent_id <> :parentId
                    AND event_id = :assignedEventId;";
            $stmt = $dbConn->prepare($sql);
            $stmt->bindValue(':systemMessageId', $emsaMessage->getSystemMessageId(), PDO::PARAM_INT);
            $stmt->bindValue(':originalMessageId', $emsaMessage->getOriginalMessageId(), PDO::PARAM_INT);
            $stmt->bindValue(':appId', $emsaMessage->getApplicationId(), PDO::PARAM_INT);
            $stmt->bindValue(':parentId', $emsaMessage->getCopyParentId(), PDO::PARAM_INT);
            $stmt->bindValue(':assignedEventId', $emsaMessage->getAssignedEventId(), PDO::PARAM_INT);
        }
        if ($stmt->execute()) {
            while ($rsObj = $stmt->fetchObject()) {
                try {
                    $this->panelResults->addResult(new EmsaListResult($dbConn, new EmsaMessage($dbConn, $appClientList, $rsObj->id, false, true, true), $appClientList, false));
                } catch (Throwable $ex) {
                    ExceptionUtils::logException($ex);
                }
            }
        }
        $stmt = null;
    }

    /**
     * Loads any associated data related to interagency message sharing for this original message.
     * 
     * @param PDO $db
     * @param int $originalMsgID
     */
    protected function loadInteragencyData(PDO $db, int $originalMsgID)
    {
        try {
            $sql = "SELECT interagency_date_sent, interagency_recipient, interagency_filename 
                    FROM system_original_messages 
                    WHERE id = :origMsgId;";
            $stmt = $db->prepare($sql);
            $stmt->bindValue(":origMsgId", (int)$originalMsgID, PDO::PARAM_INT);

            if ($stmt->execute()) {
                while ($rsObj = $stmt->fetchObject()) {
                    $this->interagencyFilename = trim($rsObj->interagency_filename);
                    $this->interagencyRecipient = trim($rsObj->interagency_recipient);

                    if (!empty($rsObj->interagency_date_sent)) {
                        $this->interagencyDateSent = DateTimeUtils::createMixed(trim($rsObj->interagency_date_sent));
                    }
                }
            }

        } catch (Throwable $e) {
            ExceptionUtils::logException($e);
        }
    }

    /**
     * Creates a panel of copies of this message.
     *
     * @param PDO           $dbConn
     * @param EmsaMessage   $emsaMessage
     * @param AppClientList $appClientList
     */
    private function buildCopyPanel(PDO $dbConn, EmsaMessage $emsaMessage, AppClientList $appClientList)
    {
        try {
            $sql = "SELECT id
                    FROM system_messages
                    WHERE original_message_id = :originalMessageId
                    AND vocab_app_id = :appId
                    AND copy_parent_id = :systemMessageId
                    UNION 
                    SELECT id
                    FROM system_messages
                    WHERE original_message_id = :originalMessageId
                    AND vocab_app_id = :appId
                    AND copy_parent_id = :copyParentId
                    AND id <> :systemMessageId
                    UNION 
                    SELECT id
                    FROM system_messages
                    WHERE original_message_id = :originalMessageId
                    AND vocab_app_id = :appId
                    AND id = :copyParentId;";
            $stmt = $dbConn->prepare($sql);
            $stmt->bindValue(':systemMessageId', $emsaMessage->getSystemMessageId(), PDO::PARAM_INT);
            $stmt->bindValue(':copyParentId', $emsaMessage->getCopyParentId(), PDO::PARAM_INT);
            $stmt->bindValue(':originalMessageId', $emsaMessage->getOriginalMessageId(), PDO::PARAM_INT);
            $stmt->bindValue(':appId', $emsaMessage->getApplicationId(), PDO::PARAM_INT);

            if ($stmt->execute()) {
                while ($rsObj = $stmt->fetchObject()) {
                    try {
                        $this->copyPanelResults->addResult(new EmsaListResult($dbConn, new EmsaMessage($dbConn, $appClientList, $rsObj->id, false, true, true), $appClientList, false));
                    } catch (Throwable $ex) {
                        ExceptionUtils::logException($ex);
                    }
                }
            }
        } catch (Throwable $e) {
            ExceptionUtils::logException($e);
        } finally {
            $stmt = null;
        }
    }

    private function setFlags($messageFlags)
    {
        if (is_numeric($messageFlags) && (intval($messageFlags) >= 0)) {
            $this->setFlag('investigation_complete', (($messageFlags & EMSA_FLAG_INVESTIGATION_COMPLETE) != 0));
            $this->setFlag('de_error', (($messageFlags & EMSA_FLAG_DE_ERROR) != 0));
            $this->setFlag('fix_duplicate', (($messageFlags & EMSA_FLAG_FIX_DUPLICATE) != 0));
            $this->setFlag('de_other', (($messageFlags & EMSA_FLAG_DE_OTHER) != 0));
            $this->setFlag('clean_data', (($messageFlags & EMSA_FLAG_CLEAN_DATA) != 0));
            $this->setFlag('qa_mandatory_fields', (($messageFlags & EMSA_FLAG_QA_MANDATORY) != 0));
            $this->setFlag('qa_vocab_coding', (($messageFlags & EMSA_FLAG_QA_CODING) != 0));
            $this->setFlag('need_fix', (($messageFlags & EMSA_FLAG_DE_NEEDFIX) != 0));
            $this->setFlag('qa_mqf', (($messageFlags & EMSA_FLAG_QA_MQF) != 0));

            return true;
        } else {
            return false;
        }
    }

    private function setFlag($flagName, $flagValue = false)
    {
        if (EmsaUtils::emptyTrim($flagValue)) {
            $this->flags[strtolower(trim($flagName))] = false;
        } else {
            $this->flags[strtolower(trim($flagName))] = (bool) $flagValue;
        }
    }
    
    /**
     * Whether this message is marked as having been deleted.
     * 
     * @return bool
     */
    public function getIsDeleted()
    {
        return $this->isDeleted;
    }
    
    /**
     * Returns the Udoh\Emsa\Constants\MessageType value for this message.
     * 
     * @return int
     */
    public function getEmsaMessageType()
    {
        return $this->emsaMessageType;
    }

    /**
     * Checks whether the specified flag name is set
     * 
     * @param string $flagName Name of the flag to get
     * 
     * @return boolean
     */
    public function getFlag($flagName)
    {
        if (EmsaUtils::emptyTrim($this->flags[strtolower(trim($flagName))])) {
            return false;
        } else {
            return (bool) $this->flags[strtolower(trim($flagName))];
        }
    }

    /**
     * Get all additional test results from the same order this lab result came from and return them by Accession Number.
     * 
     * @param int $targetObrId Restrict results to a specific OBR panel, specified by OBR index number (OBR-2).
     * 
     * @return array Array of results with the Accession Number as the key and the lab results in an array of name/value pairs.
     */
    public function getPanelResults($targetObrId = 0)
    {
        $foundResults = array();

        foreach ($this->panelResults->getResults() as $panelResult) {
            $foundResults[intval($panelResult->obrId)][trim($panelResult->accessionNumber)][] = array(
                'systemMessageId' => (int) $panelResult->id,
                'isDeleted' => $panelResult->getIsDeleted(),
                'finalStatus' => intval($panelResult->type),
                'childLoinc' => trim($panelResult->localLoincCode),
                'localTestCode' => trim($panelResult->localCode),
                'localTestName' => trim($panelResult->localTestName),
                'localCodeTestName' => trim($panelResult->localCodeTestName),
                'childResultValue' => trim($panelResult->localResultValue),
                'childResultValue2' => trim($panelResult->localResultValue2),
                'masterLoinc' => trim($panelResult->loincCode),
                'organism' => trim($panelResult->organism),
                'testType' => trim($panelResult->testType),
                'testResult' => trim($panelResult->testResult),
                'testResultClass' => trim($panelResult->testResultClass),
                'resultValue' => trim($panelResult->resultValue),
                'dateTested' => trim($panelResult->dateTested),
                'testStatus' => trim($panelResult->testStatus)
            );
        }

        if (isset($foundResults[intval($targetObrId)])) {
            return $foundResults[intval($targetObrId)];
        } else {
            return array();
        }
    }

    /**
     * Get all copies of additional test results from the same order this lab result came from and return them by Accession Number.
     *
     * @param int $targetObrId Restrict results to a specific OBR panel, specified by OBR index number (OBR-2).
     *
     * @return array Array of results with the Accession Number as the key and the lab results in an array of name/value pairs.
     */
    public function getCopyPanelResults($targetObrId = 0)
    {
        $foundResults = array();

        foreach ($this->copyPanelResults->getResults() as $copyPanelResult) {
            $foundResults[intval($copyPanelResult->obrId)][trim($copyPanelResult->accessionNumber)][] = array(
                'systemMessageId' => (int) $copyPanelResult->id,
                'isDeleted' => $copyPanelResult->getIsDeleted(),
                'finalStatus' => intval($copyPanelResult->type),
                'childLoinc' => trim($copyPanelResult->localLoincCode),
                'localTestCode' => trim($copyPanelResult->localCode),
                'localTestName' => trim($copyPanelResult->localTestName),
                'localCodeTestName' => trim($copyPanelResult->localCodeTestName),
                'childResultValue' => trim($copyPanelResult->localResultValue),
                'childResultValue2' => trim($copyPanelResult->localResultValue2),
                'masterLoinc' => trim($copyPanelResult->loincCode),
                'organism' => trim($copyPanelResult->organism),
                'testType' => trim($copyPanelResult->testType),
                'testResult' => trim($copyPanelResult->testResult),
                'testResultClass' => trim($copyPanelResult->testResultClass),
                'resultValue' => trim($copyPanelResult->resultValue),
                'dateTested' => trim($copyPanelResult->dateTested),
                'testStatus' => trim($copyPanelResult->testStatus)
            );
        }

        if (isset($foundResults[intval($targetObrId)])) {
            return $foundResults[intval($targetObrId)];
        } else {
            return array();
        }
    }

    /**
     * Get the Application client for this specific result.
     * 
     * @return AppClientInterface
     */
    public function getAppClient()
    {
        return $this->appClient;
    }

    /**
     * Find QA-related comments for a given EMSA message and return in an HTML table
     * 
     * @param PDO $dbConn PDO connection to the EMSA database
     * 
     * @return string
     */
    protected function getQAComments(PDO $dbConn)
    {
        $html = '<table class="audit_log">
						<thead>
							<tr>
								<th style="width: 15%">Date/Time</th>
								<th style="width: 15%">User</th>
								<th style="width: 70%">Comment</th>
							</tr>
						</thead>
						<tbody>';

        try {
            $sql = "SELECT mc.user_id AS user_id, mc.comment AS comment, mc.created_at AS created_at 
                    FROM system_message_comments mc 
                    WHERE mc.system_message_id = :systemMessageId
                    ORDER BY mc.created_at;";
            $stmt = $dbConn->prepare($sql);
            $stmt->bindValue(':systemMessageId', intval($this->id), PDO::PARAM_INT);

            if ($stmt->execute() && ($stmt->rowCount() > 0)) {
                while ($row = $stmt->fetchObject()) {
                    $html .= '<tr>
								<td>' . DisplayUtils::xSafe(DateTimeUtils::createMixed($row->created_at)->format("m/d/Y H:i:s")) . '</td>
								<td>' . AppClientUtils::userFullNameByUserId($this->appClient, $row->user_id) . '</td>
								<td>' . DisplayUtils::xSafe($row->comment) . '</td>
							</tr>';
                }
            } else {
                $html .= '<tr><td colspan="3"><em>No comments</em></td></tr>';
            }
        } catch (Throwable $e) {
            ExceptionUtils::logException($e);
            $html .= '<tr><td colspan="3"><em>An error occurred while attempting to load QA comments...</em></td></tr>';
        }

        $html .= '</tbody>
				</table>';

        return $html;
    }

    /**
     * Get any current Exceptions for a given EMSA message and return in an HTML table
     * 
     * @param PDO $dbConn PDO connection to the EMSA database
     * 
     * @return string
     */
    public function drawCurrentExceptions(PDO $dbConn)
    {
        $html = '<div class="exception_details">
				<div class="h3">Current Errors</div>
				<table class="audit_log">
					<thead>
						<tr>
							<th>Error Type</th>
							<th>Error Description</th>
							<th>Error Details</th>
						</tr>
					</thead>
					<tbody>';

        try {
            $sql = "SELECT se.description AS description, sme.info AS info, ss.name AS type 
                    FROM system_message_exceptions sme 
                    INNER JOIN system_exceptions se ON (sme.exception_id = se.exception_id) 
                    INNER JOIN system_statuses ss ON (se.exception_type_id = ss.id) 
                    WHERE sme.system_message_id = :systemMessageId 
                    ORDER BY sme.id;";
            $stmt = $dbConn->prepare($sql);
            $stmt->bindValue(':systemMessageId', intval($this->id), PDO::PARAM_INT);

            if ($stmt->execute()) {
                if ($stmt->rowCount() > 0) {
                    while ($row = $stmt->fetchObject()) {
                        $html .= '<tr>
									<td>' . DisplayUtils::xSafe($row->type) . '</td>
									<td>' . DisplayUtils::xSafe($row->description) . '</td>
									<td class="mono_prewrap">' . EmsaUtils::trimNEDSSErrorHTML($dbConn, $row->info) . '</td>
								</tr>';
                    }
                } else {
                    $html .= '<tr><td colspan="3"><em>Message has no current errors</em></td></tr>';
                }
            } else {
                $html .= '<tr><td colspan="3"><em>Unable to retrieve list of errors</em></td></tr>';
            }
        } catch (Throwable $e) {
            ExceptionUtils::logException($e);
            $html .= '<tr><td colspan="3"><em>Unable to retrieve list of errors</em></td></tr>';
        }

        $html .= '</tbody>
					</table>
				</div>';

        return $html;
    }

    /**
     * Get any historical Exceptions for a given EMSA message and return in an HTML table
     * 
     * @param PDO $dbConn PDO connection to the EMSA database
     * 
     * @return string
     */
    public function drawExceptionsHistory(PDO $dbConn)
    {
        $html = '<div class="exception_details">
				<br><br>
				<div class="h3">Error History</div>
				<table class="audit_log">
					<thead>
						<tr>
							<th>Error Type</th>
							<th>Date/Time</th>
							<th>Error Description</th>
							<th>Error Details</th>
						</tr>
					</thead>
					<tbody>';

        try {
            $sql = "SELECT sma.created_at AS created_at, se.description AS description, sae.info AS info, ss.name AS type 
                    FROM system_audit_exceptions sae 
                    INNER JOIN system_exceptions se ON (sae.system_exceptions_id = se.exception_id) 
                    INNER JOIN system_statuses ss ON (se.exception_type_id = ss.id) 
                    INNER JOIN system_messages_audits sma ON (sae.system_messages_audits_id = sma.id) 
                    WHERE sma.system_message_id = :systemMessageId 
                    ORDER BY sma.created_at, ss.name, se.description;";
            $stmt = $dbConn->prepare($sql);
            $stmt->bindValue(':systemMessageId', intval($this->id), PDO::PARAM_INT);

            if ($stmt->execute()) {
                if ($stmt->rowCount() > 0) {
                    while ($row = $stmt->fetchObject()) {
                        $html .= '<tr>
									<td>' . DisplayUtils::xSafe($row->type) . '</td>
									<td style="white-space: nowrap; font-weight: bold;">' . DisplayUtils::xSafe(DateTimeUtils::createMixed($row->created_at)->format("m/d/Y H:i:s.u")) . '</td>
									<td>' . DisplayUtils::xSafe($row->description) . '</td>
									<td class="mono_prewrap">' . EmsaUtils::trimNEDSSErrorHTML($dbConn, $row->info) . '</td>
								</tr>';
                    }
                } else {
                    $html .= '<tr><td colspan="3"><em>Message has no previous errors</em></td></tr>';
                }
            } else {
                $html .= '<tr><td colspan="3"><em>Unable to retrieve error history</em></td></tr>';
            }
        } catch (Throwable $e) {
            ExceptionUtils::logException($e);
            $html .= '<tr><td colspan="3"><em>Unable to retrieve error history</em></td></tr>';
        }

        $html .= '</tbody>
					</table>
				</div>';

        return $html;
    }

    /**
     * Build the 'QA Tracking' tab for the specified EMSA message and return as HTML
     *
     * @param PDO    $dbConn         PDO connection to the EMSA database
     * @param string $navQueryString Current query string for use in navigational elements
     *
     * @return string
     */
    public function drawQaTrackingTab(PDO $dbConn, $navQueryString)
    {
        $formAction = $navQueryString . '&focus=' . intval($this->id);
        $errorMessageFlagComment = \Udoh\Emsa\Utils\EmsaMessageUtils::getMessageFlagComment($dbConn, $this->id, EMSA_FLAG_DE_ERROR);
        $otherMessageFlagComment = \Udoh\Emsa\Utils\EmsaMessageUtils::getMessageFlagComment($dbConn, $this->id, EMSA_FLAG_DE_OTHER);

        $html = '<div class="ui-corner-all emsa_toolbar">';
        $html .= '<form style="display: inline-block;" id="emsa_qa_actions_' . intval($this->id) . '" method="POST" action="' . $formAction . '">
				<input type="hidden" name="id" value="' . intval($this->id) . '" />
				<input type="hidden" name="target" id="qa_target_' . intval($this->id) . '" value="" />
				<input type="hidden" name="emsa_action" id="qa_emsa_action_' . intval($this->id) . '" value="" />
				<input type="hidden" name="info" id="qa_info_' . intval($this->id) . '" value="" />
				<span class="emsa_toolbar_label">QA Flags:</span>';

        if ($this->getFlag('qa_mandatory_fields')) {
            $html .= '<button type="button" class="emsa_btn_flagmandatory_off" id="emsa_btn_flagmandatory_' . intval($this->id) . '" value="' . intval($this->id) . '" title="Clear Flag">Missing Mandatory Fields</button>';
        } else {
            $html .= '<button type="button" class="emsa_btn_flagmandatory" id="emsa_btn_flagmandatory_' . intval($this->id) . '" value="' . intval($this->id) . '" title="Set Flag">Missing Mandatory Fields</button>';
        }

        $html .= '<div class="emsa_toolbar_separator"></div>';
        if ($this->getFlag('qa_vocab_coding')) {
            $html .= '<button type="button" class="emsa_btn_flagvocabcoding_off" id="emsa_btn_flagvocabcoding_' . intval($this->id) . '" value="' . intval($this->id) . '" title="Clear Flag">Coding/Vocabulary Errors</button>';
        } else {
            $html .= '<button type="button" class="emsa_btn_flagvocabcoding" id="emsa_btn_flagvocabcoding_' . intval($this->id) . '" value="' . intval($this->id) . '" title="Set Flag">Coding/Vocabulary Errors</button>';
        }

        $html .= '<div class="emsa_toolbar_separator"></div>';
        if ($this->getFlag('qa_mqf')) {
            $html .= '<button type="button" class="emsa_btn_flagmqf_off" id="emsa_btn_flagmqf_' . intval($this->id) . '" value="' . intval($this->id) . '" title="Clear Flag">MQF Structural Errors</button>';
        } else {
            $html .= '<button type="button" class="emsa_btn_flagmqf" id="emsa_btn_flagmqf_' . intval($this->id) . '" value="' . intval($this->id) . '" title="Set Flag">MQF Structural Errors</button>';
        }

        $html .= '<div class="emsa_toolbar_separator"></div>';
        if ($this->getFlag('fix_duplicate')) {
            $html .= '<button type="button" class="emsa_btn_flagfixduplicate_off" id="emsa_btn_flagfixduplicate_' . intval($this->id) . '" value="' . intval($this->id) . '" title="Clear Flag">Fix Duplicate</button>';
        } else {
            $html .= '<button type="button" class="emsa_btn_flagfixduplicate" id="emsa_btn_flagfixduplicate_' . intval($this->id) . '" value="' . intval($this->id) . '" title="Set Flag">Fix Duplicate</button>';
        }

        $html .= '<div class="emsa_toolbar_separator"></div>';
        if ($this->getFlag('need_fix')) {
            $html .= '<button type="button" class="emsa_btn_flagneedfix_off" id="emsa_btn_flagneedfix_' . intval($this->id) . '" value="' . intval($this->id) . '" title="Clear Flag">Needs Fixing</button>';
        } else {
            $html .= '<button type="button" class="emsa_btn_flagneedfix" id="emsa_btn_flagneedfix_' . intval($this->id) . '" value="' . intval($this->id) . '" title="Set Flag">Needs Fixing</button>';
        }

        $html .= '<div style="display: block; margin-top: 5px; padding-top: 5px; border-top: 1px #aaaaaa dotted;">
				<span class="emsa_toolbar_label">Quality Check:</span>';

        if ($this->getFlag('de_error')) {
            $html .= '<button type="button" class="emsa_btn_flagdeerror_off" id="emsa_btn_flagdeerror_' . intval($this->id) . '" value="' . intval($this->id) . '" title="Clear Flag">Data Entry Error</button>';
        } else {
            $html .= '<button type="button" class="emsa_btn_flagdeerror" id="emsa_btn_flagdeerror_' . intval($this->id) . '" value="' . intval($this->id) . '" title="Set Flag">Data Entry Error</button>';
        }

        $html .= '<label class="sr-only" for="de_error_info_' . $this->id . '">Select type of Data Entry error</label>';
        $html .= '<select class="ui-corner-all" id="de_error_info_' . $this->id . '" name="de_error_info_' . $this->id . '">';
        $html .= '<option selected value="">Select type of Data Entry error...</option>';
        $html .= '<option ' . ((($errorMessageFlagComment == 'Laboratory Error') || ($errorMessageFlagComment == 'Error at Reporting Facility')) ? 'selected ' : '') . 'value="Error at Reporting Facility">Error at Reporting Facility</option>';
        $html .= '<option ' . ((($errorMessageFlagComment == 'TriSano Error') || ($errorMessageFlagComment == 'Error in Surveillance System')) ? 'selected ' : '') . 'value="Error in Surveillance System">Error in Surveillance System</option>';
        $html .= '<option ' . (($errorMessageFlagComment == 'Undetermined Error') ? 'selected ' : '') . 'value="Undetermined Error">Undetermined Error</option>';
        $html .= '<option ' . (($errorMessageFlagComment == 'Alias') ? 'selected ' : '') . 'value="Alias">Alias</option>';
        $html .= '</select>';
        $html .= '<div class="emsa_toolbar_separator"></div>';

        if ($this->getFlag('de_other')) {
            $html .= '<button type="button" class="emsa_btn_flagdeother_off" id="emsa_btn_flagdeother_' . $this->id . '" value="' . $this->id . '" title="Clear Flag">Other</button>';
        } else {
            $html .= '<button type="button" class="emsa_btn_flagdeother" id="emsa_btn_flagdeother_' . $this->id . '" value="' . $this->id . '" title="Set Flag">Other</button>';
        }

        $html .= '<label class="sr-only" for="de_other_info_' . $this->id . '">Explain \'Other\' reason</label>';
        $html .= '<input type="text" class="ui-corner-all" id="de_other_info_' . $this->id . '" name="de_other_info_' . $this->id . '" placeholder="Explain \'Other\' reason..." value="' . DisplayUtils::xSafe($otherMessageFlagComment) . '">';

        $html .= '</div>
					</form>
				</div>

				<div class="ui-corner-all" id="emsa_qa_comments">
					<br><div class="h3">QA Comments</div>
					' . self::getQAComments($dbConn) . '
					<label class="sr-only" for="add_comment_' . $this->id . '">Add new QA Comment</label><br>
					<textarea class="ui-corner-all" style="font-family: \'Open Sans\', Arial, Helvetica, sans-serif; font-weight: 400; width: 50%; height: 50px; margin: 5px;" id="add_comment_' . $this->id . '" name="add_comment_' . $this->id . '" placeholder="Enter new comment here"></textarea><br>
					<button type="button" class="emsa_btn_addcomment" style="margin: 5px;" id="emsa_btn_addcomment_' . $this->id . '" value="' . $this->id . '" title="Add Comment">Add New Comment</button>
				</div>';

        return $html;
    }

    /**
     * Build the 'Manual Override' form and return as HTML
     * 
     * @param string $navQueryString Current query string for use in navigational elements
     * 
     * @return string
     */
    public function drawManualOverrideForm($navQueryString)
    {
        $formAction = $navQueryString . '&focus=' . intval($this->id);

        $html = '<div class="ui-corner-all emsa_toolbar">
					<form method="POST" id="override_cmr_' . intval($this->id) . '" action="' . $formAction . '">
						<input type="hidden" name="id" id="cmr_' . intval($this->id) . '_id" value="' . intval($this->id) . '" />
						<input type="hidden" name="emsa_action" id="override_emsa_cmraction_' . intval($this->id) . '" value="" />
						<input type="hidden" name="emsa_override" id="emsa_override_' . intval($this->id) . '" value="1" />
						<span class="emsa_toolbar_label">Override Actions:</span>
						<button type="button" title="Create a new person and new event with this data" class="override_new_person" value="' . intval($this->id) . '">New Person</button>
						<div class="emsa_toolbar_separator"></div>
						<button type="button" title="Add this data to the specified Event ID" class="override_update_cmr" value="' . intval($this->id) . '">Update Existing Event:</button>
						<label class="sr-only" for="override_event_' . intval($this->id) . '">Event ID Number</label>
						<input type="text" class="ui-corner-all" style="background-color: lightcyan; font-family: Consolas, \'Courier New\', Courier, serif;" name="override_event" id="override_event_' . intval($this->id) . '" placeholder="Event ID#" />
                        <div class="emsa_toolbar_separator"></div>
                        <button type="button" title="Add a new event to the specified person with this data" class="override_new_cmr" value="' . intval($this->id) . '">Add Event to Person:</button>
                        <label class="sr-only" for="override_person_' . intval($this->id) . '">Person ID Number</label>
						<input type="text" class="ui-corner-all" style="background-color: lightcyan; font-family: Consolas, \'Courier New\', Courier, serif;" name="override_person" id="override_person_' . intval($this->id) . '" placeholder="Person ID#" />
					</form>
				</div>';

        return $html;
    }

    /**
     * Generate the 'Full Lab' detail view of the current EMSA message and return as HTML.
     *
     * @param PDO    $dbConn         PDO connection to the EMSA database
     * @param string $navQueryString Current query string for use in navigational elements [Not required if <i>forPDFView</i> is TRUE].
     * @param bool   $forPDFView     [Optional; Default false] Indicates whether being called to draw a PDF view or inline with an EMSA Queue List.
     *
     * @return string
     */
    public function drawFullLabTab(PDO $dbConn, $navQueryString = null, $forPDFView = false)
    {
        $html = null;
        
        if ($forPDFView) {
            $html .= '<div class="ui-corner-all emsa_toolbar" style="background-color: gold; text-align: center;"><label style="vertical-align: middle; color: darkred; font-family: serif; font-size: 1.4em; font-style: italic; font-weight: bold;">';
            $html .= 'This laboratory result information is a UDOH translation of an electronic health message.';
            $html .= '</label></div><br>';
        } else {
            $html .= \EmsaUtils::showMessageMoveReason($dbConn, intval($this->id), intval($this->type));
        }
        
        
        
        $isSemiAutomatedEntry = false;
        if (($this->type == QA_STATUS) || ($this->type == SEMI_AUTO_STATUS)) {
            $isSemiAutomatedEntry = true;  // for now, all msgs in 'Entry' allow this
        }
        
        if (!$forPDFView) {
            $html .= '<div class="ui-corner-all emsa_toolbar">';

            $formAction = $navQueryString . '&focus=' . intval($this->id);

            $html .= '<form style="display: inline-block;" id="emsa_actions_' . intval($this->id) . '" method="POST" action="' . $formAction . '">
                    <input type="hidden" name="id" value="' . intval($this->id) . '" />
                    <input type="hidden" name="target" id="target_' . intval($this->id) . '" value="" />
                    <input type="hidden" name="emsa_action" id="emsa_action_' . intval($this->id) . '" value="" />
                    <input type="hidden" name="info" id="info_' . intval($this->id) . '" value="" />
                    <input type="hidden" name="semi_auto_original" id="semi_auto_original_' . intval($this->id) . '" value="" />
                    <div style="display: block;">
                        <span class="emsa_toolbar_label">Actions:</span>';

            if ($isSemiAutomatedEntry) {
                $html .= '<button type="button" class="emsa_btn_cultureentry" id="emsa_btn_cultureentry_' . intval($this->id) . '" value="' . intval($this->id) . '" title="Manually set complex lab results">Semi-Auto Lab Entry</button>';
                $html .= '<div class="emsa_toolbar_separator"></div>';
            }

            //if ($type == EXCEPTIONS_STATUS || $type == PENDING_STATUS) {
            if ($this->type == EXCEPTIONS_STATUS || $this->type == NEDSS_EXCEPTION_STATUS || $this->type == OOS_STATUS || $this->type == ENTRY_STATUS || $this->type == UNPROCESSED_STATUS || $this->type == LOCKED_STATUS || $this->type == SEMI_AUTO_STATUS || $this->type == QA_STATUS) {
                // buttons for edit & retry
                $html .= '<button type="button" class="emsa_btn_edit" id="emsa_btn_edit_' . intval($this->id) . '" value="' . intval($this->id) . '" title="Edit & revalidate this message">Edit & Retry</button>';
                $html .= '<div class="emsa_toolbar_separator"></div>';
                $html .= '<button type="button" class="emsa_btn_retry" id="emsa_btn_retry_' . intval($this->id) . '" value="' . intval($this->id) . '" title="Revalidate this message as-is">Retry Without Changes</button>';
                if (\Udoh\Emsa\Auth\Authenticator::userHasPermission(\Udoh\Emsa\Auth\Authenticator::URIGHTS_ACTION_MOVE) || (\Udoh\Emsa\Auth\Authenticator::userHasPermission(\Udoh\Emsa\Auth\Authenticator::URIGHTS_ACTION_DELETE) && ($this->type != ASSIGNED_STATUS))) {
                    $html .= '<div class="emsa_toolbar_separator"></div>';
                }
            }

            // action bar buttons
            if (\Udoh\Emsa\Auth\Authenticator::userHasPermission(\Udoh\Emsa\Auth\Authenticator::URIGHTS_ACTION_MOVE)) {
                $html .= '<button type="button" class="emsa_btn_move" id="emsa_btn_move_' . intval($this->id) . '" value="' . intval($this->id) . '" title="Move to selected queue">Move To:</button>' . \EmsaUtils::getQueueNameMenuByTypeAndMsgId($dbConn, $this->type, intval($this->id), false);
                $html .= '<label class="sr-only" for="move_info_' . intval($this->id) . '">Reason for moving?</label>';
                $html .= '<input type="text" class="ui-corner-all" id="move_info_' . intval($this->id) . '" name="move_info_' . intval($this->id) . '" placeholder="Reason for moving?">';
            }
            if (\Udoh\Emsa\Auth\Authenticator::userHasPermission(\Udoh\Emsa\Auth\Authenticator::URIGHTS_ACTION_MOVE) && (\Udoh\Emsa\Auth\Authenticator::userHasPermission(\Udoh\Emsa\Auth\Authenticator::URIGHTS_ACTION_DELETE) && ($this->type != ASSIGNED_STATUS))) {
                $html .= '<div class="emsa_toolbar_separator"></div>';
            }
            if (\Udoh\Emsa\Auth\Authenticator::userHasPermission(\Udoh\Emsa\Auth\Authenticator::URIGHTS_ACTION_DELETE) && ($this->type != ASSIGNED_STATUS)) {
                $html .= '<button type="button" class="emsa_btn_delete" id="emsa_btn_delete_' . intval($this->id) . '" value="' . intval($this->id) . '" title="Delete this message">Delete</button>';
            }
            if ($this->type == ASSIGNED_STATUS) {
                if (\Udoh\Emsa\Auth\Authenticator::userHasPermission(\Udoh\Emsa\Auth\Authenticator::URIGHTS_ACTION_MOVE) || \Udoh\Emsa\Auth\Authenticator::userHasPermission(\Udoh\Emsa\Auth\Authenticator::URIGHTS_ACTION_DELETE)) {
                    $html .= '<div class="emsa_toolbar_separator"></div>';
                }
                try {
                    $html .= '<a target="_blank" href="' . $this->appClient->getAppLinkToRecord(0, null, true, (int) $this->nedssEventId) . '" class="emsa_btn_viewnedss" id="emsa_btn_viewnedss_' . intval($this->id) . '">View in ' . $this->appClient->getAppName() . '</a>';
                } catch (Throwable $e) {
                    $e = null;
                    $html .= '[Missing ' . $this->appClient->getAppName() . ' Record ID]';
                }
            }
            
            $html .= '<div class="emsa_toolbar_separator"></div>';
            $html .= '<a target="_blank" href="' . MAIN_URL . '/emsa/view_pdf.php?id=' . $this->id . '" class="emsa_btn_viewpdf" id="emsa_btn_viewpdf_' . intval($this->id) . '">Download as PDF</a>';

            if ($this->type == OOS_STATUS) {
                $html .= '<div class="emsa_toolbar_separator"></div>';
                if ($this->getFlag('investigation_complete')) {
                    $html .= '<button type="button" class="emsa_btn_flagcomplete_off" id="emsa_btn_flagcomplete_' . intval($this->id) . '" value="' . intval($this->id) . '" title="Clear Flag">Investigation Complete</button>';
                } else {
                    $html .= '<button type="button" class="emsa_btn_flagcomplete" id="emsa_btn_flagcomplete_' . intval($this->id) . '" value="' . intval($this->id) . '" title="Set Flag">Investigation Complete</button>';
                }
            }

            $html .= '</div>';

            //if (($type == PENDING_STATUS || $type == EXCEPTIONS_STATUS) && !\Udoh\Emsa\Auth\Authenticator::userHasPermission(\Udoh\Emsa\Auth\Authenticator::URIGHTS_TAB_QA)) {
            if (($this->type == ENTRY_STATUS || $this->type == UNPROCESSED_STATUS || $this->type == LOCKED_STATUS || $this->type == EXCEPTIONS_STATUS || $this->type == NEDSS_EXCEPTION_STATUS || $this->type == SEMI_AUTO_STATUS) && !\Udoh\Emsa\Auth\Authenticator::userHasPermission(\Udoh\Emsa\Auth\Authenticator::URIGHTS_TAB_QA)) {
                $html .= '<div style="display: block; margin-top: 5px; padding-top: 5px; border-top: 1px #aaaaaa dotted;">
                        <span class="emsa_toolbar_label">Quality Check:</span>';

                if ($this->getFlag('de_error')) {
                    $html .= '<button type="button" class="emsa_btn_flagdeerror_off" id="emsa_btn_flagdeerror_' . intval($this->id) . '" value="' . intval($this->id) . '" title="Clear Flag">Data Entry Error</button>';
                } else {
                    $html .= '<button type="button" class="emsa_btn_flagdeerror" id="emsa_btn_flagdeerror_' . intval($this->id) . '" value="' . intval($this->id) . '" title="Set Flag">Data Entry Error</button>';
                }
                $html .= '<select class="ui-corner-all" id="de_error_info_' . intval($this->id) . '" name="de_error_info_' . intval($this->id) . '">';
                $html .= '<option selected value="">Select type of Data Entry error...</option>';
                $this_message_flag_comment = \Udoh\Emsa\Utils\EmsaMessageUtils::getMessageFlagComment($dbConn, intval($this->id), EMSA_FLAG_DE_ERROR);
                $html .= '<option ' . ((($this_message_flag_comment == 'Laboratory Error') || ($this_message_flag_comment == 'Error at Reporting Facility')) ? 'selected ' : '') . 'value="Error at Reporting Facility">Error at Reporting Facility</option>';
                $html .= '<option ' . ((($this_message_flag_comment == 'TriSano Error') || ($this_message_flag_comment == 'Error in Surveillance System')) ? 'selected ' : '') . 'value="Error in Surveillance System">Error in Surveillance System</option>';
                $html .= '<option ' . (($this_message_flag_comment == 'Undetermined Error') ? 'selected ' : '') . 'value="Undetermined Error">Undetermined Error</option>';
                $html .= '<option ' . (($this_message_flag_comment == 'Alias') ? 'selected ' : '') . 'value="Alias">Alias</option>';
                $html .= '</select>';
                if (1 === 0) {
                    $html .= '<div class="emsa_toolbar_separator"></div>';
                    if ($this->getFlag('fix_duplicate')) {
                        $html .= '<button type="button" class="emsa_btn_flagfixduplicate_off" id="emsa_btn_flagfixduplicate_' . intval($this->id) . '" value="' . intval($this->id) . '" title="Clear Flag">Fix Duplicate</button>';
                    } else {
                        $html .= '<button type="button" class="emsa_btn_flagfixduplicate" id="emsa_btn_flagfixduplicate_' . intval($this->id) . '" value="' . intval($this->id) . '" title="Set Flag">Fix Duplicate</button>';
                    }
                }
                $html .= '<div class="emsa_toolbar_separator"></div>';
                if ($this->getFlag('de_other')) {
                    $html .= '<button type="button" class="emsa_btn_flagdeother_off" id="emsa_btn_flagdeother_' . intval($this->id) . '" value="' . intval($this->id) . '" title="Clear Flag">Other</button>';
                } else {
                    $html .= '<button type="button" class="emsa_btn_flagdeother" id="emsa_btn_flagdeother_' . intval($this->id) . '" value="' . intval($this->id) . '" title="Set Flag">Other</button>';
                }
                $html .= '<input type="text" class="ui-corner-all" id="de_other_info_' . intval($this->id) . '" name="de_other_info_' . intval($this->id) . '" placeholder="Explain \'Other\' reason..." value="' . DisplayUtils::xSafe(\Udoh\Emsa\Utils\EmsaMessageUtils::getMessageFlagComment($dbConn, intval($this->id), EMSA_FLAG_DE_OTHER)) . '">';

                $html .= '</div>';
            }

            $html .= '</form>
                    </div>';
        }

        $html .= '<table class="list"' . (($forPDFView) ? ' cellspacing="6" cellpadding="3"' : '') . '>
				<tr>
					<th width="' . (($forPDFView) ? '30%' : '200px') . '">Patient</th>
					<th width="' . (($forPDFView) ? '30%' : '200px') . '">Condition &amp; Organism</th>
					<th width="' . (($forPDFView) ? '30%' : '345px') . '">Event Data</th>
				</tr>
				<tr>
					<td width="' . (($forPDFView) ? '30%' : '200px') . '" valign="top">
						<table border="0" width="100%"' . (($forPDFView) ? ' cellpadding="2"' : '') . '>
							<tr><td><b>Name:</b></td><td>' . DisplayUtils::xSafe($this->fullName) . '</td></tr>
							<tr><td><b>MRN/Patient ID#:</b></td><td>' . DisplayUtils::xSafe($this->medicalRecordNumber) . '</td></tr>';

        $html .= '</table>
				</td>
				<td class="cultureentry_container_' . intval($this->id) . '" valign="top" width="' . (($forPDFView) ? '30%' : '200px') . '" style="font-size: 1.2em; font-weight: 900; color: purple;">';

        if ($isSemiAutomatedEntry && !$forPDFView) {
            $html .= '<label class="sr-only" for="cultureentry_' . intval($this->id) . '__disease">Set or change condition</label><select class="emsa_cultureentry_' . intval($this->id) . '" disabled style="max-width: 25em;" id="cultureentry_' . intval($this->id) . '__disease" name="cultureentry_' . intval($this->id) . '__disease">
					<option value="">--</option>';
            uasort($_SESSION[EXPORT_SERVERNAME]['codedData'][$this->appClient->getAppName()]['diseases'], array('\Udoh\Emsa\Utils\SortUtils', 'sortExternalCodesByDescriptionAlpha'));
            foreach ($_SESSION[EXPORT_SERVERNAME]['codedData'][$this->appClient->getAppName()]['diseases'] as $appDiseaseData) {
                if (strtolower($this->condition) == strtolower($appDiseaseData['codeDescription'])) {
                    $html .= '<option selected value="' . DisplayUtils::xSafe($appDiseaseData['codeDescription']) . '">' . DisplayUtils::xSafe($appDiseaseData['codeDescription']) . '</option>' . PHP_EOL;
                } else {
                    $html .= '<option value="' . DisplayUtils::xSafe($appDiseaseData['codeDescription']) . '">' . DisplayUtils::xSafe($appDiseaseData['codeDescription']) . '</option>' . PHP_EOL;
                }
            }
            $html .= '</select><br><span style="font-size: 0.9em; font-weight: 400; color: purple;">';
            $html .= '<label class="sr-only" for="cultureentry_' . intval($this->id) . '__organism">Set or change organism</label><select class="emsa_cultureentry_' . intval($this->id) . '" disabled style="max-width: 25em;" id="cultureentry_' . intval($this->id) . '__organism" name="cultureentry_' . intval($this->id) . '__organism">
					<option value="">--</option>';
            $mappedOrganisms = VocabUtils::getOrganismNamesMappedToApp($dbConn, $this->appId);
            uasort($_SESSION[EXPORT_SERVERNAME]['codedData'][$this->appClient->getAppName()]['organisms'], array('\Udoh\Emsa\Utils\SortUtils', 'sortExternalCodesByDescriptionAlpha'));
            foreach ($_SESSION[EXPORT_SERVERNAME]['codedData'][$this->appClient->getAppName()]['organisms'] as $appOrganismData) {
                if (in_array($appOrganismData['codedValue'], $mappedOrganisms)) {
                    if ((string) $this->organism == (string) $appOrganismData['codedValue']) {
                        $html .= '<option selected value="' . DisplayUtils::xSafe((string) $appOrganismData['codedValue']) . '">' . DisplayUtils::xSafe((string) $appOrganismData['codeDescription']) . '</option>' . PHP_EOL;
                    } else {
                        $html .= '<option value="' . DisplayUtils::xSafe((string) $appOrganismData['codedValue']) . '">' . DisplayUtils::xSafe((string) $appOrganismData['codeDescription']) . '</option>' . PHP_EOL;
                    }
                }
            }
            $html .= '</select></span><br>';
        } else {
            $html .= DisplayUtils::xSafe($this->condition) . '<br><em style="font-weight: 600; color: mediumorchid;">' . ((strlen($this->organism) > 0) ? DisplayUtils::xSafe(CodedDataUtils::getCodeDescriptionFromCodedValue($this->appClient, 'organisms', $this->organism)) : '--Organism N/A--') . '</em>';
        }

        $html .= '</td>
				<td valign="top" width="' . (($forPDFView) ? '30%' : '245px') . '">
					<table border="0" width="100%">
						<tr>
							<td class="cultureentry_container_' . intval($this->id) . '"><b>Jurisdiction:</b></td>
							<td class="cultureentry_container_' . intval($this->id) . '">';

        if ($isSemiAutomatedEntry && !$forPDFView) {
            $html .= '<label class="sr-only" for="cultureentry_' . intval($this->id) . '__jurisdiction">Set or change jurisdiction</label><select class="emsa_cultureentry_' . intval($this->id) . '" disabled style="max-width: 25em;" id="cultureentry_' . intval($this->id) . '__jurisdiction" name="cultureentry_' . intval($this->id) . '__jurisdiction">
									<option value="">--</option>';
            foreach ($_SESSION[EXPORT_SERVERNAME]['jurisdictions'][$this->appClient->getAppId()] as $appJurisdictionId => $appJurisdictionShortName) {
                if (strtolower($this->jurisdiction) == strtolower($appJurisdictionShortName)) {
                    $html .= '<option selected value="' . intval($appJurisdictionId) . '">' . DisplayUtils::xSafe($appJurisdictionShortName) . '</option>' . PHP_EOL;
                } else {
                    $html .= '<option value="' . intval($appJurisdictionId) . '">' . DisplayUtils::xSafe($appJurisdictionShortName) . '</option>' . PHP_EOL;
                }
            }
            $html .= '</select>';
        } else {
            $html .= $this->jurisdiction;
        }

        $html .= '</td>
						</tr>
						<tr>
							<td class="cultureentry_container_' . intval($this->id) . '"><b>State Case Status:</b></td>
							<td class="cultureentry_container_' . intval($this->id) . '">';

        if ($isSemiAutomatedEntry && !$forPDFView) {
            $html .= '<label class="sr-only" for="cultureentry_' . intval($this->id) . '__state_case_status">Set or change State Case Status</label><select class="emsa_cultureentry_' . intval($this->id) . '" disabled style="max-width: 25em;" id="cultureentry_' . intval($this->id) . '__state_case_status" name="cultureentry_' . intval($this->id) . '__state_case_status">
									<option value="">--</option>';
            uasort($_SESSION[EXPORT_SERVERNAME]['codedData'][$this->appClient->getAppName()]['case'], array('\Udoh\Emsa\Utils\SortUtils', 'sortExternalCodesByDescriptionAlpha'));
            foreach ($_SESSION[EXPORT_SERVERNAME]['codedData'][$this->appClient->getAppName()]['case'] as $appCaseData) {
                if (strtolower($this->stateCaseStatus) == strtolower($appCaseData['codeDescription'])) {
                    $html .= '<option selected value="' . DisplayUtils::xSafe($appCaseData['codedValue']) . '">' . DisplayUtils::xSafe($appCaseData['codeDescription']) . '</option>' . PHP_EOL;
                } else {
                    $html .= '<option value="' . DisplayUtils::xSafe($appCaseData['codedValue']) . '">' . DisplayUtils::xSafe($appCaseData['codeDescription']) . '</option>' . PHP_EOL;
                }
            }
            $html .= '</select>';
        } else {
            $html .= DisplayUtils::xSafe($this->stateCaseStatus);
        }

        $html .= '</td>
						</tr>';

        if (($this->type == ENTRY_STATUS) || ($this->type == OOS_STATUS) || ($this->type == UNPROCESSED_STATUS) || ($this->type == LOCKED_STATUS) || ($this->type == SEMI_AUTO_STATUS)) {
            $html .= '<tr>
								<td class="cultureentry_container_' . intval($this->id) . '"><b>Surveillance Event?</b></td>
								<td class="cultureentry_container_' . intval($this->id) . '">';

            if ($isSemiAutomatedEntry && !$forPDFView) {
                $html .= '<label class="sr-only" for="cultureentry_' . intval($this->id) . '__surveillance">Set or change surveillance event flag</label><select class="emsa_cultureentry_' . intval($this->id) . '" disabled style="max-width: 25em;" id="cultureentry_' . intval($this->id) . '__surveillance" name="cultureentry_' . intval($this->id) . '__surveillance">
											<option value="0" selected>Set By Rules</option>
											<option value="1">Yes</option>
											<option value="2">No</option>
										</select>';
            } else {
                $html .= 'Set By Rules';
            }

            $html .= '</td>
									</tr>';
        }

        $html .= '</table>
							</td>
						</tr>';

        $html .= '<tr>
							<th width="' . (($forPDFView) ? '30%' : '200px') . '">Demographics</th>
							<th width="' . (($forPDFView) ? '30%' : '200px') . '">Lab-Related Facilities</th>
							<th width="' . (($forPDFView) ? '30%' : '345px') . '">Raw Lab</th>
						</tr>';
        $html .= '<tr>
							<td valign="top" width="' . (($forPDFView) ? '30%' : '200px') . '">
								<table border="0" width="100%"' . (($forPDFView) ? ' cellpadding="2"' : '') . '>
									<tr><td><b>D.O.B. (Age):</b></td><td>' . DisplayUtils::xSafe(DisplayUtils::optionalParentheses($this->birthDate, $this->age)) . '</td></tr>
									<tr><td><b>Gender:</b></td><td>' . DisplayUtils::xSafe($this->gender) . '</td></tr>
									<tr><td><b>Race (Ethnicity):</b></td><td>' . DisplayUtils::xSafe(DisplayUtils::optionalParentheses($this->race, $this->ethnicity)) . '</td></tr>
									<tr>
										<td valign="top"><b>Address:</b></td>
										<td>' . Address::formatAddressMultiLine($this->street, $this->unit, $this->city, $this->state, $this->zip, $this->county) . '</td>
									</tr>
									<tr><td><b>Telephone:</b></td><td>' . DisplayUtils::xSafe($this->phone) . '</td></tr>
									<tr><td><b>Email:</b></td><td>' . htmlspecialchars($this->purifier->purify($this->email)) . '</td></tr>
								</table>
							</td>
							<td valign="top" width="' . (($forPDFView) ? '30%' : '200px') . '">
								<table border="0" width="100%"' . (($forPDFView) ? ' cellpadding="2"' : '') . '>
									<tr><td><b>Reporting Agency:</b></td><td>' . DisplayUtils::xSafe($this->reportingAgency) . '</td></tr>
									<tr><td><b>Date Reported:</b></td><td>' . DisplayUtils::xSafe($this->dateReported) . '</td></tr>
									<tr><td><b>Performing Lab:</b></td><td>' . DisplayUtils::xSafe($this->lab) . '</td></tr>
									<tr><td valign="top"><b>Ordering Provider:</b></td><td>' . DisplayUtils::xSafe(DisplayUtils::optionalParentheses($this->clinicianName, $this->clinicianPhone)) . '</td></tr>
									<tr><td valign="top"><b>Ordering Facility:</b></td><td>' . implode("<br>", array_filter(array(
									    DisplayUtils::xSafe($this->diagnosticFacility),
                                        DisplayUtils::xSafe($this->diagnosticStreet),
                                        DisplayUtils::xSafe(implode(" ", array_filter(array(DisplayUtils::xSafe($this->diagnosticCity), DisplayUtils::xSafe($this->diagnosticState), DisplayUtils::xSafe($this->diagnosticZip)))))
                                    ))) . '</td></tr>
								</table>
							</td>
							<td valign="top" width="' . (($forPDFView) ? '30%' : '345px') . '">
								<table border="0" width="100%"' . (($forPDFView) ? ' cellpadding="2"' : '') . '>';

        // build local result string based on whether l_r_v & l_r_v2 are populated, as well as local_units
        $localResultStr = DisplayUtils::optionalParentheses($this->localResultValue, $this->localResultValue2);
        $localResultStr .= ((strlen(trim($this->localUnits)) > 0) ? ' ' . trim($this->localUnits) : '');

        $html .= '<tr><td><b>Child LOINC:</b></td><td>' . DisplayUtils::optionalParentheses(VocabUtils::getLinkToVocab(VocabTable::CHILD_LOINC, $this->localLoincCode, $this->labId), DisplayUtils::xSafe($this->localTestName)) . '</td></tr>
									<tr><td><b>Local Test Code:</b></td><td>' . DisplayUtils::optionalParentheses(VocabUtils::getLinkToVocab(VocabTable::CHILD_LOINC, $this->localCode, $this->labId), DisplayUtils::xSafe($this->localCodeTestName)) . '</td></tr>
									<tr><td><b>Specimen (Accession #):</b></td><td>' . DisplayUtils::xSafe(DisplayUtils::optionalParentheses($this->localSpecimenSource, $this->accessionNumber)) . '</td></tr>
									<tr><td><b>Local Result Value(s):</b></td><td>' . DisplayUtils::xSafe($localResultStr) . '</td></tr>
									<tr><td><b>Reference Range:</b></td><td style="color: dimgray;">' . DisplayUtils::xSafe($this->localReferenceRange) . '</td></tr>
									<tr><td><b>Abnormal Flag:</b></td><td>' . ((strlen(trim($this->abnormalFlag)) > 0) ? '<b style="color: red;">' . DisplayUtils::xSafe($this->abnormalFlag) . '</b>' : '') . '</td></tr>
								</table>
							</td>
						</tr>';
        
        if (count($this->healthcareFacilities) > 0) {
            $html .= '<tr><th colspan="3">Associated Healthcare Facility Visits</th></tr><tr><td colspan="3" valign="top">';
            $html .= '<table border="0" width="100%"' . (($forPDFView) ? ' cellpadding="2"' : '') . '>';
            $html .= '<tr>';
            $html .= '<td valign="bottom"><b>Visit Type:</b></td>';
            $html .= '<td valign="bottom"><b>Admit/Visit Date:</b></td>';
            $html .= '<td valign="bottom"><b>Discharge Date:</b></td>';
            $html .= '<td valign="bottom"><b>Facility:</b></td>';
            $html .= '<td valign="bottom"><b>Clinician:</b></td>';
            $html .= '</tr>';

            foreach ($this->healthcareFacilities as $healthcareFacility) {
                $healthcareFacilityProvider = null;
                if (!empty($healthcareFacility->getProvider())) {
                    $healthcareFacilityProvider = implode("<br>", array_filter(array(
                        DisplayUtils::xSafe(DisplayUtils::formatNameLastFirstMiddle(trim($healthcareFacility->getProvider()->getLastName()), trim($healthcareFacility->getProvider()->getFirstName()), trim($healthcareFacility->getProvider()->getMiddleName()))),
                        ((strlen($healthcareFacility->getProvider()->getPhone()) > 0) ? DisplayUtils::formatPhoneNumber($healthcareFacility->getProvider()->getPhone(), $healthcareFacility->getProvider()->getAreaCode()) : '') . ((strlen(trim($healthcareFacility->getProvider()->getExtension())) > 0) ? 'Ext. ' . $healthcareFacility->getProvider()->getExtension() : ''),
                        DisplayUtils::xSafe($healthcareFacility->getProvider()->getEmail())
                    )));
                }

                $healthcareFacilityParticipant = null;
                if (!empty($healthcareFacility->getParticipant())) {
                    $healthcareFacilityParticipant = implode("<br>", array_filter(array(
                        DisplayUtils::xSafe($healthcareFacility->getParticipant()->getFacilityName()),
                        DisplayUtils::xSafe($healthcareFacility->getParticipant()->getStreetName()),
                        DisplayUtils::xSafe($healthcareFacility->getParticipant()->getUnitNumber()),
                        DisplayUtils::xSafe(implode(" ", array_filter(array($healthcareFacility->getParticipant()->getCity(), $healthcareFacility->getParticipant()->getState(), $healthcareFacility->getParticipant()->getPostalCode()))))
                    )));
                }

                $html .= '<tr>';
                $html .= '<td valign="top">' . DisplayUtils::xSafe(CodedDataUtils::getCodeDescriptionFromCodedValue($this->appClient, 'facility_visit_type', $healthcareFacility->getVisitType())) . '</td>';
                $html .= '<td valign="top">' . DisplayUtils::xSafe($healthcareFacility->getAdmissionDate(true, "m/d/Y")) . '</td>';
                $html .= '<td valign="top">' . DisplayUtils::xSafe($healthcareFacility->getDischargeDate(true, "m/d/Y")) . '</td>';
                $html .= '<td valign="top">' . $healthcareFacilityParticipant . '</td>';
                $html .= '<td valign="top">' . $healthcareFacilityProvider . '</td>';
                $html .= '</tr>';
            }

            $html .= '</table></td></tr>';
        }

        if ($this->hasLabResults) {
            $html .= '<tr>
							<th colspan="3">Translated Lab</th>
						</tr>
						<tr>
							<td colspan="20">
								<table border="0" width="100%"' . (($forPDFView) ? ' cellpadding="2"' : '') . '>
									<tr>
										<td valign="bottom"><b>Master LOINC:</b></td>
										<td class="cultureentry_container_' . intval($this->id) . '" valign="bottom"><b>Test Type:</b></td>
										<td class="cultureentry_container_' . intval($this->id) . '" valign="bottom"><b>Specimen:</b></td>
										<td class="cultureentry_container_' . intval($this->id) . '" valign="bottom"><b>Test Result:</b></td>
										<td class="cultureentry_container_' . intval($this->id) . '" valign="bottom"><b>Result Value:</b></td>
										<td class="cultureentry_container_' . intval($this->id) . '" valign="bottom"><b>Units:</b></td>
										<td class="cultureentry_container_' . intval($this->id) . '" valign="bottom"><b>Test Status:</b></td>
									</tr>
									<tr>
										<td rowspan="3" valign="top" style="white-space: nowrap;">' . VocabUtils::getLinkToVocab(VocabTable::MASTER_LOINC, $this->loincCode) . '</td>
										<td class="cultureentry_container_' . intval($this->id) . '" valign="top">';

            if ($isSemiAutomatedEntry && !$forPDFView) {
                $html .= '<label class="sr-only" for="cultureentry_' . intval($this->id) . '__test_type">Set or change test type</label><select class="emsa_cultureentry_' . intval($this->id) . '" disabled style="max-width: 14em;" id="cultureentry_' . intval($this->id) . '__test_type" name="cultureentry_' . intval($this->id) . '__test_type">
                                                    <option value="">--</option>';
                uasort($_SESSION[EXPORT_SERVERNAME]['codedData'][$this->appClient->getAppName()]['common_test_types'], array('\Udoh\Emsa\Utils\SortUtils', 'sortExternalCodesByDescriptionAlpha'));
                foreach ($_SESSION[EXPORT_SERVERNAME]['codedData'][$this->appClient->getAppName()]['common_test_types'] as $appTestTypeData) {
                    if (strtolower($this->testType) == strtolower($appTestTypeData['codeDescription'])) {
                        $html .= '<option selected value="' . DisplayUtils::xSafe($appTestTypeData['codedValue']) . '">' . DisplayUtils::xSafe($appTestTypeData['codeDescription']) . '</option>' . PHP_EOL;
                    } else {
                        $html .= '<option value="' . DisplayUtils::xSafe($appTestTypeData['codedValue']) . '">' . DisplayUtils::xSafe($appTestTypeData['codeDescription']) . '</option>' . PHP_EOL;
                    }
                }
                $html .= '</select>';
            } else {
                $html .= DisplayUtils::xSafe($this->testType);
            }

            $html .= '</td><td class="cultureentry_container_' . intval($this->id) . '" valign="top">';

            if ($isSemiAutomatedEntry && !$forPDFView) {
                $html .= '<label class="sr-only" for="cultureentry_' . intval($this->id) . '__specimen">Set or change specimen source</label><select class="emsa_cultureentry_' . intval($this->id) . '" disabled style="max-width: 14em;" id="cultureentry_' . intval($this->id) . '__specimen" name="cultureentry_' . intval($this->id) . '__specimen">
                                                    <option value="">--</option>';

                uasort($_SESSION[EXPORT_SERVERNAME]['codedData'][$this->appClient->getAppName()]['specimen'], array('\Udoh\Emsa\Utils\SortUtils', 'sortExternalCodesByDescriptionAlpha'));
                foreach ($_SESSION[EXPORT_SERVERNAME]['codedData'][$this->appClient->getAppName()]['specimen'] as $appSpecimenSourceData) {
                    if (strtolower($this->specimenSource) == strtolower($appSpecimenSourceData['codeDescription'])) {
                        $html .= '<option selected value="' . DisplayUtils::xSafe($appSpecimenSourceData['codedValue']) . '">' . DisplayUtils::xSafe($appSpecimenSourceData['codeDescription']) . '</option>' . PHP_EOL;
                    } else {
                        $html .= '<option value="' . DisplayUtils::xSafe($appSpecimenSourceData['codedValue']) . '">' . DisplayUtils::xSafe($appSpecimenSourceData['codeDescription']) . '</option>' . PHP_EOL;
                    }
                }

                $html .= '</select>';
            } else {
                $html .= DisplayUtils::xSafe($this->specimenSource);
            }

            $html .= '</td><td class="emsa_result_' . DisplayUtils::xSafe($this->testResultClass) . ' cultureentry_container_' . intval($this->id) . '" valign="top">';

            if ($isSemiAutomatedEntry && !$forPDFView) {
                $html .= '<label class="sr-only" for="cultureentry_' . intval($this->id) . '__testresult">Set or change test result</label><select class="emsa_cultureentry_' . intval($this->id) . '" disabled style="max-width: 14em;" id="cultureentry_' . intval($this->id) . '__testresult" name="cultureentry_' . intval($this->id) . '__testresult">
                                                    <option value="">--</option>';

                uasort($_SESSION[EXPORT_SERVERNAME]['codedData'][$this->appClient->getAppName()]['test_result'], array('\Udoh\Emsa\Utils\SortUtils', 'sortExternalCodesByDescriptionAlpha'));
                foreach ($_SESSION[EXPORT_SERVERNAME]['codedData'][$this->appClient->getAppName()]['test_result'] as $appTestResultData) {
                    if (strtolower($this->testResult) == strtolower($appTestResultData['codeDescription'])) {
                        $html .= '<option selected value="' . DisplayUtils::xSafe($appTestResultData['codedValue']) . '">' . DisplayUtils::xSafe($appTestResultData['codeDescription']) . '</option>' . PHP_EOL;
                    } else {
                        $html .= '<option value="' . DisplayUtils::xSafe($appTestResultData['codedValue']) . '">' . DisplayUtils::xSafe($appTestResultData['codeDescription']) . '</option>' . PHP_EOL;
                    }
                }

                $html .= '</select>';
            } else {
                $html .= DisplayUtils::xSafe($this->testResult);
            }

            $html .= '</td><td class="cultureentry_container_' . intval($this->id) . '" valign="top">';

            if ($isSemiAutomatedEntry && !$forPDFView) {
                $html .= '<label class="sr-only" for="cultureentry_' . intval($this->id) . '__resultvalue">Set or change result value</label><input class="emsa_cultureentry_' . intval($this->id) . '" disabled id="cultureentry_' . intval($this->id) . '__resultvalue" name="cultureentry_' . intval($this->id) . '__resultvalue" value="' . DisplayUtils::xSafe($this->resultValue) . '">';
            } else {
                $html .= DisplayUtils::xSafe($this->resultValue);
            }

            $html .= '</td><td class="cultureentry_container_' . intval($this->id) . '" valign="top">';

            if ($isSemiAutomatedEntry && !$forPDFView) {
                $html .= '<label class="sr-only" for="cultureentry_' . intval($this->id) . '__units">Set or change units</label><input class="emsa_cultureentry_' . intval($this->id) . '" disabled style="max-width: 7em;" id="cultureentry_' . intval($this->id) . '__units" name="cultureentry_' . intval($this->id) . '__units" value="' . DisplayUtils::xSafe($this->units) . '">';
            } else {
                $html .= DisplayUtils::xSafe($this->units);
            }

            $html .= '</td><td class="cultureentry_container_' . intval($this->id) . '" valign="top">';

            if ($isSemiAutomatedEntry && !$forPDFView) {
                $html .= '<label class="sr-only" for="cultureentry_' . intval($this->id) . '__teststatus">Set or change test status</label><select class="emsa_cultureentry_' . intval($this->id) . '" disabled style="max-width: 13em;" id="cultureentry_' . intval($this->id) . '__teststatus" name="cultureentry_' . intval($this->id) . '__teststatus">
                                                    <option value="">--</option>';

                uasort($_SESSION[EXPORT_SERVERNAME]['codedData'][$this->appClient->getAppName()]['lab_test_status'], array('\Udoh\Emsa\Utils\SortUtils', 'sortExternalCodesByDescriptionAlpha'));
                foreach ($_SESSION[EXPORT_SERVERNAME]['codedData'][$this->appClient->getAppName()]['lab_test_status'] as $appTestStatusData) {
                    if (strtolower($this->testStatus) == strtolower($appTestStatusData['codeDescription'])) {
                        $html .= '<option selected value="' . DisplayUtils::xSafe($appTestStatusData['codedValue']) . '">' . DisplayUtils::xSafe($appTestStatusData['codeDescription']) . '</option>' . PHP_EOL;
                    } else {
                        $html .= '<option value="' . DisplayUtils::xSafe($appTestStatusData['codedValue']) . '">' . DisplayUtils::xSafe($appTestStatusData['codeDescription']) . '</option>' . PHP_EOL;
                    }
                }

                $html .= '</select>';
            } else {
                $html .= DisplayUtils::xSafe($this->testStatus);
            }

            $html .= '</td>
                                                </tr>
                                                <tr>
                                                    <td colspan="2" class="cultureentry_container_' . intval($this->id) . '" valign="bottom"><b>Comment:</b></td>
                                                    <td colspan="2" valign="bottom"><b>Reference Range:</b></td>
                                                    <td valign="bottom"><b>Collection Date:</b></td>
                                                    <td valign="bottom"><b>Lab Test Date:</b></td>
                                                </tr>
                                                <tr>
                                                    <td colspan="2" class="cultureentry_container_' . intval($this->id) . '" valign="top">';

            if ($isSemiAutomatedEntry && !$forPDFView) {
                $html .= '<label class="sr-only" for="cultureentry_' . intval($this->id) . '__comment">Set or change lab comments</label><textarea class="emsa_cultureentry_' . intval($this->id) . '" disabled style="width: 95%; height: 3em;" id="cultureentry_' . intval($this->id) . '__comment" name="cultureentry_' . intval($this->id) . '__comment">' . DisplayUtils::xSafe($this->comment) . '</textarea>';
            } else {
                $html .= DisplayUtils::xSafe($this->comment);
            }

            $html .= '</td>
                                                <td colspan="2" valign="top">' . DisplayUtils::xSafe($this->referenceRange) . '</td>
                                                <td valign="top">' . DisplayUtils::xSafe($this->dateCollected) . '</td>
                                                <td valign="top">' . DisplayUtils::xSafe($this->dateTested) . '</td>
                                            </tr>
                                        </table>
                                    </td>
                                </tr>';

        } else {
            $html .= '<tr><th colspan="3">No Lab Testing Reported</th></tr>';
        }
        
        if (count($this->antimicrobialSusceptibilities) > 0) {
            $html .= '<tr><th colspan="3">Antimicrobial Susceptibility Testing</th></tr><tr><td colspan="3" valign="top">';
            $html .= '<table border="0" width="100%"' . (($forPDFView) ? ' cellpadding="2"' : '') . '>';
            $html .= '<tr>';
            $html .= '<td valign="bottom"><b>Antimicrobial Agent:</b></td>';
            $html .= '<td valign="bottom"><b>Result:</b></td>';
            $html .= '<td valign="bottom"><b>Result Value:</b></td>';
            $html .= '<td valign="bottom"><b>Test Date:</b></td>';
            $html .= '</tr>';
            
            foreach ($this->antimicrobialSusceptibilities as $susceptibility) {
                $html .= '<tr>';
                $html .= '<td valign="top">' . DisplayUtils::xSafe($susceptibility['agent']) . '</td>';
                $html .= '<td valign="top" class="emsa_result_' . DisplayUtils::xSafe($this->testResultClass) . '">' . DisplayUtils::xSafe($susceptibility['result']) . '</td>';
                $html .= '<td valign="top">' . DisplayUtils::xSafe($susceptibility['value']) . '</td>';
                $html .= '<td valign="top">' . DisplayUtils::xSafe($susceptibility['test_date']) . '</td>';
                $html .= '</tr>';
            }
            
            $html .= '</table></td></tr>';
        }
        
        if (count($this->clinicalPregnancyDiagnoses) > 0) {
            $html .= '<tr><th colspan="3">Clinical Pregnancy Information</th></tr><tr><td colspan="3" valign="top"><ul>';
            
            foreach ($this->clinicalPregnancyDiagnoses as $clinicalPregnancyDiagnosis) {
                $html .= '<li>' . DisplayUtils::xSafe((string) $clinicalPregnancyDiagnosis) . '</li>';
            }
            
            $html .= '</ul></td></tr>';
        }
        
        if (count($this->clinicalTreatments) > 0) {
            $html .= '<tr><th colspan="3">Treatments</th></tr><tr><td colspan="3" valign="top">';
            $html .= '<table border="0" width="100%"' . (($forPDFView) ? ' cellpadding="2"' : '') . '>';
            $html .= '<tr>';
            $html .= '<td valign="bottom"><b>Treatment Date(s):</b></td>';
            $html .= '<td valign="bottom"><b>Dose:</b></td>';
            $html .= '<td valign="bottom"><b>Treatment Name:</b></td>';
            $html .= '<td valign="bottom"><b>Treatment Code:</b></td>';
            $html .= '</tr>';
            
            foreach ($this->clinicalTreatments as $clinicalTreatment) {
                $html .= '<tr>';
                $html .= '<td valign="top">' . DisplayUtils::xSafe($clinicalTreatment['start']) . '&mdash;' . DisplayUtils::xSafe($clinicalTreatment['end']) . '</td>';
                $html .= '<td valign="top">' . DisplayUtils::xSafe($clinicalTreatment['dose']) . '</td>';
                $html .= '<td valign="top">' . DisplayUtils::xSafe($clinicalTreatment['name']) . '</td>';
                $html .= '<td valign="top">' . DisplayUtils::xSafe($clinicalTreatment['code_system'] . ' ' . $clinicalTreatment['code']) . '</td>';
                $html .= '</tr>';
            }
            
            $html .= '</table></td></tr>';
        }

        $otherPanelResults = $this->getPanelResults($this->obrId);
        if ((count($otherPanelResults) > 0) && !$forPDFView) {
            $html .= '<tr><th colspan="3">Additional Results From Same Test/Panel</th></tr>';
            $html .= '<tr><td colspan="20"><table border="0" width="100%">';
            foreach ($otherPanelResults as $otherPanelResultSet) {
                //$html .= '<tr><td colspan="7" valign="bottom"><b>Accession#: ' . DisplayUtils::xSafe(trim($otherPanelResultAccessionNumber)) . '</b></td></tr>';
                $html .= '<tr>';
                $html .= '<td valign="bottom"><b>Queue:</b></td>';
                $html .= '<td valign="bottom"><b>Child Test Name(s):</b></td>';
                $html .= '<td valign="bottom"><b>Master LOINC (Test Type):</b></td>';
                $html .= '<td valign="bottom"><b>Organism:</b></td>';
                $html .= '<td valign="bottom"><b>Raw Test Result(s):</b></td>';
                $html .= '<td valign="bottom"><b>Test Result (Value):</b></td>';
                $html .= '<td valign="bottom"><b>Test Date (Status):</b></td>';
                $html .= '</tr>';
                foreach ($otherPanelResultSet as $otherPanelResult) {
                    if ($otherPanelResult['isDeleted']) {
                        $html .= '<tr style="text-decoration: line-through;">';
                    } else {
                        $html .= '<tr>';
                    }
                    $html .= '<td valign="top"><a href="' . MAIN_URL . '/?selected_page=6&submenu=6&focus=' . (int) $otherPanelResult['systemMessageId'] . '" target="_blank">' . DisplayUtils::xSafe(\EmsaUtils::getQueueName(intval($otherPanelResult['finalStatus']))) . '</a></td>';
                    $html .= '<td valign="top">' . DisplayUtils::xSafe(DisplayUtils::optionalParentheses($otherPanelResult['localTestName'], $otherPanelResult['localCodeTestName'])) . '</td>';
                    $html .= '<td valign="top">' . DisplayUtils::xSafe(DisplayUtils::optionalParentheses($otherPanelResult['masterLoinc'], $otherPanelResult['testType'])) . '</td>';
                    $html .= '<td valign="top">' . DisplayUtils::xSafe(CodedDataUtils::getCodeDescriptionFromCodedValue($this->appClient, 'organisms', (string) $otherPanelResult['organism'])) . '</td>';
                    $html .= '<td valign="top">' . DisplayUtils::xSafe(DisplayUtils::optionalParentheses($otherPanelResult['childResultValue'], $otherPanelResult['childResultValue2'])) . '</td>';
                    $html .= '<td valign="top" class="emsa_result_' . trim($otherPanelResult['testResultClass']) . '">' . DisplayUtils::xSafe(DisplayUtils::optionalParentheses($otherPanelResult['testResult'], $otherPanelResult['resultValue'])) . '</td>';
                    $html .= '<td valign="top">' . DisplayUtils::xSafe(DisplayUtils::optionalParentheses($otherPanelResult['dateTested'], $otherPanelResult['testStatus'])) . '</td>';
                    $html .= '</tr>';
                }
            }
            $html .= '</table></td></tr>';
        }

        $otherCopyPanelResults = $this->getCopyPanelResults($this->obrId);
        if ((count($otherCopyPanelResults) > 0) && !$forPDFView) {
            $html .= '<tr><th colspan="3">Other Copies of This Message</th></tr>';
            $html .= '<tr><td colspan="20"><table border="0" width="100%">';
            foreach ($otherCopyPanelResults as $otherCopyPanelResultSet) {
                //$html .= '<tr><td colspan="7" valign="bottom"><b>Accession#: ' . DisplayUtils::xSafe(trim($otherCopyPanelResultAccessionNumber)) . '</b></td></tr>';
                $html .= '<tr>';
                $html .= '<td valign="bottom"><b>Queue:</b></td>';
                $html .= '<td valign="bottom"><b>Child Test Name(s):</b></td>';
                $html .= '<td valign="bottom"><b>Master LOINC (Test Type):</b></td>';
                $html .= '<td valign="bottom"><b>Organism:</b></td>';
                $html .= '<td valign="bottom"><b>Raw Test Result(s):</b></td>';
                $html .= '<td valign="bottom"><b>Test Result (Value):</b></td>';
                $html .= '<td valign="bottom"><b>Test Date (Status):</b></td>';
                $html .= '</tr>';
                foreach ($otherCopyPanelResultSet as $otherCopyPanelResult) {
                    if ($otherCopyPanelResult['isDeleted']) {
                        $html .= '<tr style="text-decoration: line-through;">';
                    } else {
                        $html .= '<tr>';
                    }
                    $html .= '<td valign="top"><a href="' . MAIN_URL . '/?selected_page=6&submenu=6&focus=' . (int) $otherCopyPanelResult['systemMessageId'] . '" target="_blank">' . DisplayUtils::xSafe(\EmsaUtils::getQueueName(intval($otherCopyPanelResult['finalStatus']))) . '</a></td>';
                    $html .= '<td valign="top">' . DisplayUtils::xSafe(DisplayUtils::optionalParentheses($otherCopyPanelResult['localTestName'], $otherCopyPanelResult['localCodeTestName'])) . '</td>';
                    $html .= '<td valign="top">' . DisplayUtils::xSafe(DisplayUtils::optionalParentheses($otherCopyPanelResult['masterLoinc'], $otherCopyPanelResult['testType'])) . '</td>';
                    $html .= '<td valign="top">' . DisplayUtils::xSafe(CodedDataUtils::getCodeDescriptionFromCodedValue($this->appClient, 'organisms', (string) $otherCopyPanelResult['organism'])) . '</td>';
                    $html .= '<td valign="top">' . DisplayUtils::xSafe(DisplayUtils::optionalParentheses($otherCopyPanelResult['childResultValue'], $otherCopyPanelResult['childResultValue2'])) . '</td>';
                    $html .= '<td valign="top" class="emsa_result_' . trim($otherCopyPanelResult['testResultClass']) . '">' . DisplayUtils::xSafe(DisplayUtils::optionalParentheses($otherCopyPanelResult['testResult'], $otherCopyPanelResult['resultValue'])) . '</td>';
                    $html .= '<td valign="top">' . DisplayUtils::xSafe(DisplayUtils::optionalParentheses($otherCopyPanelResult['dateTested'], $otherCopyPanelResult['testStatus'])) . '</td>';
                    $html .= '</tr>';
                }
            }
            $html .= '</table></td></tr>';
        }

        $html .= '</table>';

        return $html;
    }

    /**
     * Get the date the message was sent to an interagency partner.
     *
     * @param boolean  $formatted [Optional]<br>Default <b>FALSE</b>.  If <b>TRUE</b>, returns date as a formatted string (specified by <i>formatStr</i>).
     * @param string   $formatStr [Optional]<br>Valid PHP date format string/constant to return the date as.  Default <b>DateTime::RFC3339</b>.
     *
     * @return string|DateTime|null Formatted string if <i>formatted</i> is <b>TRUE</b>, DateTime object otherwise.<br>Returns <b>NULL</b> if <i>dateObj</i> is null or empty.
     */
    public function getInteragencyDateSent(?bool $formatted = false, ?string $formatStr = DateTime::RFC3339)
    {
        return DateTimeUtils::getDateFormatted($this->interagencyDateSent, $formatted, $formatStr);
    }

    /**
     * Get the name of the file sent to the interagency partner containing the original message
     *
     * @return string|null
     */
    public function getInteragencyFilename(): ?string
    {
        return $this->interagencyFilename;
    }

    /**
     * Get the name of the interagency partner the original message was sent to
     *
     * @return string|null
     */
    public function getInteragencyRecipient(): ?string
    {
        return $this->interagencyRecipient;
    }



}

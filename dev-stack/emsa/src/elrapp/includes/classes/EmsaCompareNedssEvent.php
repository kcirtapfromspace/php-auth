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
use Udoh\Emsa\Model\AppLab;
use Udoh\Emsa\Model\AppLabList;
use Udoh\Emsa\Model\AppLabTest;
use Udoh\Emsa\Model\AppLabTestResult;
use Udoh\Emsa\Model\AppResistTest;
use Udoh\Emsa\Model\AppRecord;
use Udoh\Emsa\Rules\ContactWhitelistRule;
use Udoh\Emsa\Rules\WhitelistRule;
use Udoh\Emsa\Rules\WhitelistRuleSet;
use Udoh\Emsa\Utils\CodedDataUtils;
use Udoh\Emsa\Utils\DateTimeUtils;
use Udoh\Emsa\Utils\VocabUtils;

/**
 * Container for storing NEDSS event-related fields.
 * 
 * During EMSA message assignment, used to store event properties and lab result data 
 * for comparison from a NEDSS event identified as a possible target for updating.
 *
 * @author Josh Ridderhoff <jridderhoff@utah.gov>
 */
class EmsaCompareNedssEvent
{

    /** @var SimpleXMLElement */
    protected $appRecordXML;
    
    /** @var AppClientInterface */
    protected $appClient;

    /** @var int */
    protected $personId;

    /** @var int */
    protected $eventId;

    /** @var int */
    protected $eventType;

    /** @var DateTime */
    protected $eventDate;

    /** @var DateTime */
    protected $dateCreated;

    /** @var DateTime */
    protected $dateReported;

    /** @var DateTime */
    protected $dateDeleted;

    /** @var DateTime */
    protected $lastTreatmentDate;

    /** @var DateTime */
    protected $firstPositiveLabCollectionDate;

    /** @var DateTime */
    protected $lastPositiveLabCollectionDate;

    /** @var DateTime */
    protected $lastPositiveLabTestDate;

    /** @var DateTime */
    protected $lastLabCollectionDate;

    /** @var DateTime */
    protected $lastLabTestDate;

    /** @var int */
    protected $diseaseName;

    /** @var string */
    protected $jurisdictionName;

    /** @var string */
    protected $recordNumber;

    /** @var string */
    protected $workflowStatus;

    /** @var string */
    protected $stateCaseStatus;

    /** @var AppLabList */
    protected $appLabs;
    
    /** @var WhitelistRuleSet */
    protected $whitelistRules;

    /**
     * Creates a new EmsaCompareNedssEvent object.
     * 
     * @param PDO $dbConn Connection to the EMSA database
     * @param AppRecord $appRecord Target record for this Application.
     * @param AppClientInterface $appClient Application client.
     */
    public function __construct(PDO $dbConn, AppRecord $appRecord, AppClientInterface $appClient)
    {
        $this->appLabs = new AppLabList();
        $this->appClient = $appClient;
        
        if (!empty($appRecord)) {
            $this->setCoreProperties($dbConn, $appRecord);  // set core properties
            $this->setLabData();  // populate lab data
        }
    }

    /**
     * Set the core properties for this event.
     * 
     * Using the <i>appRecordXML</i>, decode and set the core properties for this Application record 
     * (such as Event ID, Record Number, Treatment Dates, etc.)
     * 
     * @param PDO $dbConn Connection to the EMSA database
     * @param AppRecord $appRecord Target record for this Application.
     */
    protected function setCoreProperties(PDO $dbConn, AppRecord $appRecord)
    {
        $appRecordXML = $appRecord->getAppRecordDocument();
        $this->setAppRecordXML($appRecordXML);
        
        $this->whitelistRules = VocabUtils::getWhitelistRulesByAppCondition($dbConn, $this->appClient->getAppId(), $appRecord->getConditionName());
        
        $thisEventJurisdictionName = null;
        if (!empty($appRecordXML->agency->shortName)) {
            $thisEventJurisdictionName = (string) $appRecordXML->agency->shortName;
        }
        
        $lastTreatmentDate = null;
        if (!empty($appRecordXML->personTreatment)) {
            foreach ($appRecordXML->personTreatment as $personTreatment) {
                if (!empty($personTreatment->treatmentDate)) {
                    $treatmentDates[] = DateTimeUtils::createMixed((string) $personTreatment->treatmentDate);
                }
            }
            
            // get back the most-recent date from the $treatment_dates array...
            if (!empty($treatmentDates)) {
                usort($treatmentDates, array('\Udoh\Emsa\Utils\SortUtils', 'sortDateTimeNewestFirst'));
                $lastTreatmentDate = reset($treatmentDates);
            }
        }
        
        $thisEventDeletedAtDate = null;
        if (!empty($appRecordXML->deletedAt)) {
            $thisEventDeletedAtDate = DateTimeUtils::createMixed((string) $appRecordXML->deletedAt);
        }

        $thisEventCreatedAtDate = null;
        if (!empty($appRecordXML->createdAt)) {
            $thisEventCreatedAtDate = DateTimeUtils::createMixed((string) $appRecordXML->createdAt);
        }

        $thisEventReportedDate = null;
        if (!empty($appRecordXML->firstReportedPhDate)) {
            $thisEventReportedDate = DateTimeUtils::createMixed((string) $appRecordXML->firstReportedPhDate);
        }

        $thisEventWorkflow = null;
        if (!empty($appRecordXML->currentWorkflow->code)) {
            $thisEventWorkflow = (string) $appRecordXML->currentWorkflow->code;
        }

        $thisEventStateCaseStatus = null;
        if (!empty($appRecordXML->stateCaseStatus->code)) {
            $thisEventStateCaseStatus = CodedDataUtils::getCodeDescriptionFromCodedValue($this->appClient, 'case', (string) $appRecordXML->stateCaseStatus->code);
        }
        
        $this->setPersonId($appRecord->getPersonId());
        $this->setEventId($appRecord->getEventId());
        $this->setEventDate($appRecord->getEventDate());
        $this->setLastTreatmentDate($lastTreatmentDate);
        $this->setDateCreated($thisEventCreatedAtDate);
        $this->setDateReported($thisEventReportedDate);
        $this->setEventType($appRecord->getRecordType());
        $this->setRecordNumber($appRecord->getRecordNumber());
        $this->setWorkflowStatus($thisEventWorkflow);
        $this->setJurisdictionName($thisEventJurisdictionName);
        $this->setDiseaseName($appRecord->getConditionName());
        $this->setDateDeleted($thisEventDeletedAtDate);
        $this->setStateCaseStatus($thisEventStateCaseStatus);
    }
    
    /**
     * Create the lab objects for this record and calculate positive lab collection dates.
     */
    protected function setLabData()
    {
        $positiveLabCollectionDates = [];
        $positiveLabTestDates = [];
        $labCollectionDates = [];
        $labTestDates = [];
        
        if (!empty($this->getAppRecordXML()->lab)) {
            foreach ($this->getAppRecordXML()->lab as $foundLab) {
                $newAppLab = new AppLab((int) filter_var($foundLab->id, FILTER_SANITIZE_NUMBER_INT));

                if (!empty($foundLab->accessionNo)) {
                    $newAppLab->setAccessionNumber((string) $foundLab->accessionNo);
                }

                if (!empty($foundLab->labFacility->name)) {
                    $newAppLab->setPerformingLabName((string) $foundLab->labFacility->name);
                }

                if (!empty($foundLab->collectionDate)) {
                    $newAppLab->setCollectionDate(DateTimeUtils::createMixed((string) $foundLab->collectionDate));
                    $labCollectionDates[] = $newAppLab->getCollectionDate();
                }

                if (!empty($foundLab->specimenSource->code)) {
                    $newAppLab->setSpecimenSource(CodedDataUtils::getCodeDescriptionFromCodedValue($this->appClient, 'specimen', (string) $foundLab->specimenSource->code));
                }

                if (!empty($foundLab->labTest)) {
                    foreach ($foundLab->labTest as $foundLabTest) {
                        $newAppLabTest = new AppLabTest((int) filter_var($foundLabTest->id, FILTER_SANITIZE_NUMBER_INT));

                        if (!empty($foundLabTest->loincCode)) {
                            $newAppLabTest->setLoincCode((string) $foundLabTest->loincCode);
                        }

                        if (!empty($foundLabTest->referenceRange)) {
                            $newAppLabTest->setReferenceRange((string) $foundLabTest->referenceRange);
                        }

                        if (!empty($foundLabTest->labTestDate)) {
                            $newAppLabTest->setTestDate(DateTimeUtils::createMixed((string) $foundLabTest->labTestDate));
                            $labTestDates[] = $newAppLabTest->getTestDate();
                        }

                        if (!empty($foundLabTest->testType->code)) {
                            $newAppLabTest->setTestType(CodedDataUtils::getCodeDescriptionFromCodedValue($this->appClient, 'common_test_types', (string) $foundLabTest->testType->code));
                        }

                        if (!empty($foundLabTest->testStatus->code)) {
                            $newAppLabTest->setTestStatus(CodedDataUtils::getCodeDescriptionFromCodedValue($this->appClient, 'lab_test_status', (string) $foundLabTest->testStatus->code));
                        }

                        if (!empty($foundLabTest->labTestResult)) {
                            foreach ($foundLabTest->labTestResult as $foundLabTestResult) {
                                $newAppLabTestResult = new AppLabTestResult((int) filter_var($foundLabTestResult->id, FILTER_SANITIZE_NUMBER_INT));

                                if (!empty($foundLabTestResult->resultValue)) {
                                    $newAppLabTestResult->setResultValue((string) $foundLabTestResult->resultValue);
                                }

                                if (!empty($foundLabTestResult->units)) {
                                    $newAppLabTestResult->setUnits((string) $foundLabTestResult->units);
                                }
                                
                                if (!empty($foundLabTestResult->comment)) {
                                    $newAppLabTestResult->setComment((string) $foundLabTestResult->comment);
                                }

                                if (!empty($foundLabTestResult->organism->code)) {
                                    $newAppLabTestResult->setOrganism(CodedDataUtils::getCodeDescriptionFromCodedValue($this->appClient, 'organisms', (string) $foundLabTestResult->organism->code));
                                }

                                if (!empty($foundLabTestResult->testResult->code)) {
                                    if ((string) $foundLabTestResult->testResult->code == 'POSITIVE') {
                                        $positiveLabCollectionDates[] = $newAppLab->getCollectionDate();
                                        $positiveLabTestDates[] = $newAppLabTest->getTestDate();
                                    }
                                    
                                    $newAppLabTestResult->setTestResult(CodedDataUtils::getCodeDescriptionFromCodedValue($this->appClient, 'test_result', (string) $foundLabTestResult->testResult->code));
                                }
                                
                                if (!empty($foundLabTestResult->labResistTest)) {
                                    foreach ($foundLabTestResult->labResistTest as $foundResistTest) {
                                        $newResistTest = new AppResistTest((int) $foundResistTest->id);
                                        
                                        if (!empty($foundResistTest->testAgent->code)) {
                                            $newResistTest->setAgentName(CodedDataUtils::getCodeDescriptionFromCodedValue($this->appClient, 'resist_test_agent', (string) $foundResistTest->testAgent->code));
                                        }
                                        if (!empty($foundResistTest->testResult->code)) {
                                            $newResistTest->setTestResult(CodedDataUtils::getCodeDescriptionFromCodedValue($this->appClient, 'resist_test_result', (string) $foundResistTest->testResult->code));
                                        }
                                        if (!empty($foundResistTest->resultValue)) {
                                            $newResistTest->setResultValue((string) $foundResistTest->resultValue);
                                        }
                                        if (!empty($foundResistTest->testDate)) {
                                            try {
                                                $newResistTest->setTestDate(DateTimeUtils::createMixed((string) $foundResistTest->testDate));
                                            } catch (Throwable $e) {
                                                $e = null;
                                            }
                                        }
                                        
                                        $newAppLabTestResult->addResistTest($newResistTest);
                                    }
                                }

                                $newAppLabTest->addLabTestResult($newAppLabTestResult);
                            }
                        }

                        $newAppLab->addLabTest($newAppLabTest);
                    }
                }

                $this->appLabs->add($newAppLab);
            }
        }
        
        // calculate positive lab collection dates...
        $newestPositiveLabCollectionDate = null;
        $oldestPositiveLabCollectionDate = null;
        $newestPositiveLabTestDate = null;
        $newestLabCollectionDate = null;
        $newestLabTestDate = null;
        
        if (!empty($positiveLabCollectionDates)) {
            usort($positiveLabCollectionDates, array('\Udoh\Emsa\Utils\SortUtils', 'sortDateTimeNewestFirst'));
            $newestPositiveLabCollectionDate = reset($positiveLabCollectionDates);  // get back the most-recent date from the $positiveLabCollectionDates array...

            usort($positiveLabCollectionDates, array('\Udoh\Emsa\Utils\SortUtils', 'sortDateTimeOldestFirst'));
            $oldestPositiveLabCollectionDate = reset($positiveLabCollectionDates);  // get back the oldest date from the $positiveLabCollectionDates array...
        }

        if (!empty($positiveLabTestDates)) {
            usort($positiveLabTestDates, array('\Udoh\Emsa\Utils\SortUtils', 'sortDateTimeNewestFirst'));
            $newestPositiveLabTestDate = reset($positiveLabTestDates);  // get back the most-recent date from the $positiveLabTestDates array...
        }

        if (!empty($labCollectionDates)) {
            usort($labCollectionDates, array('\Udoh\Emsa\Utils\SortUtils', 'sortDateTimeNewestFirst'));
            $newestLabCollectionDate = reset($labCollectionDates);  // get back the most-recent date from the $positiveLabTestDates array...
        }

        if (!empty($labTestDates)) {
            usort($labTestDates, array('\Udoh\Emsa\Utils\SortUtils', 'sortDateTimeNewestFirst'));
            $newestLabTestDate = reset($labTestDates);  // get back the most-recent date from the $positiveLabTestDates array...
        }
        
        $this->setFirstPositiveLabCollectionDate($oldestPositiveLabCollectionDate);

        $this->setLastPositiveLabCollectionDate($newestPositiveLabCollectionDate);
        $this->setLastPositiveLabTestDate($newestPositiveLabTestDate);
        $this->setLastLabCollectionDate($newestLabCollectionDate);
        $this->setLastLabTestDate($newestLabTestDate);
    }

    /**
     * Get all labs for this record.
     * 
     * @return AppLabList
     */
    public function getAppLabs()
    {
        return $this->appLabs;
    }

    /**
     * Get the XML document for this record in the current Application.
     * 
     * @return SimpleXMLElement
     */
    public function getAppRecordXML()
    {
        return $this->appRecordXML;
    }
    
    /**
     * Get the set of Whitelist Rules based on this record's condition.
     * 
     * @return WhitelistRuleSet
     */
    public function getWhitelistRules()
    {
        return $this->whitelistRules;
    }

    public function getPersonId()
    {
        return $this->personId;
    }

    public function getEventId()
    {
        return $this->eventId;
    }

    public function getEventType()
    {
        return $this->eventType;
    }

    public function getEventDate()
    {
        return $this->eventDate;
    }

    /**
     * Get the Date Created for the event.
     * 
     * @param boolean $formatted [Optional]<br>Default <b>FALSE</b>.  If <b>TRUE</b>, returns date as a formatted string (specified by <i>formatStr</i>).
     * @param string $formatStr [Optional]<br>Valid PHP date format string/constant to return the date as.  Default <b>DateTime::RFC3339</b>.
     * 
     * @return string|DateTime|null Formatted string if <i>formatted</i> is <b>TRUE</b>, DateTime object otherwise.<br>Returns <b>NULL</b> if event has no Date Created is not set.
     */
    public function getDateCreated($formatted = false, $formatStr = DateTime::RFC3339)
    {
        return DateTimeUtils::getDateFormatted($this->dateCreated, $formatted, $formatStr);
    }

    /**
     * Date reported to public health.
     * @return DateTime
     */
    public function getDateReported()
    {
        return $this->dateReported;
    }

    /**
     * Date record deleted.
     * @return DateTime
     */
    public function getDateDeleted()
    {
        return $this->dateDeleted;
    }

    /**
     * Most-recent 'Start Treatment' date.
     * @return DateTime
     */
    public function getLastTreatmentDate()
    {
        return $this->lastTreatmentDate;
    }

    /**
     * Earliest Collection Date associated with a lab with a Positive test result.
     * @return DateTime
     */
    public function getFirstPositiveLabCollectionDate()
    {
        return $this->firstPositiveLabCollectionDate;
    }

    /**
     * Most-recent Collection Date associated with a lab with a Positive test result.
     * @return DateTime
     */
    public function getLastPositiveLabCollectionDate()
    {
        return $this->lastPositiveLabCollectionDate;
    }

    /**
     * Most-recent Lab Test Date associated with a lab with a Positive test result.
     * @return DateTime
     */
    public function getLastPositiveLabTestDate()
    {
        return $this->lastPositiveLabTestDate;
    }

    /**
     * Most-recent Collection Date associated with a lab in this event..
     * @return DateTime
     */
    public function getLastLabCollectionDate()
    {
        return $this->lastLabCollectionDate;
    }

    /**
     * Most-recent Lab Test Date associated with a lab in this event.
     * @return DateTime
     */
    public function getLastLabTestDate()
    {
        return $this->lastLabTestDate;
    }

    public function getDiseaseName()
    {
        return $this->diseaseName;
    }

    public function getJurisdictionName()
    {
        return $this->jurisdictionName;
    }

    public function getRecordNumber()
    {
        return $this->recordNumber;
    }

    public function getWorkflowStatus()
    {
        return $this->workflowStatus;
    }

    public function getStateCaseStatus()
    {
        return $this->stateCaseStatus;
    }

    /**
     * Store the Application XML for this record.
     * 
     * @param SimpleXMLElement $appRecordXML Application XML document
     */
    protected function setAppRecordXML(SimpleXMLElement $appRecordXML = null)
    {
        if (!empty($appRecordXML)) {
            $this->appRecordXML = $appRecordXML;
        }
    }

    /**
     * Set the Person ID for this event.
     * 
     * @param int $personId ID of the Person associated with this NEDSS event
     */
    protected function setPersonId($personId)
    {
        if (!empty($personId)) {
            $this->personId = (int) $personId;
        }
    }

    /**
     * Set the NEDSS event ID.
     * 
     * @param int $eventId NEDSS event ID
     */
    protected function setEventId($eventId)
    {
        if (!empty($eventId)) {
            $this->eventId = (int) $eventId;
        }
    }

    /**
     * Set the NEDSS event type.
     * 
     * @param int $eventType NEDSS event type (one of Udoh\Emsa\Constants\AppRecordType)
     */
    protected function setEventType($eventType)
    {
        if (!empty($eventType)) {
            $this->eventType = $eventType;
        }
    }

    /**
     * Set the Event Date for this NEDSS event.
     * 
     * @param DateTime $eventDate Event Date
     */
    protected function setEventDate(DateTime $eventDate = null)
    {
        if (!empty($eventDate)) {
            $this->eventDate = $eventDate;
        }
    }

    /**
     * Set the Date Created.
     * 
     * @param DateTime $dateCreated Date Created
     */
    protected function setDateCreated(DateTime $dateCreated = null)
    {
        if (!empty($dateCreated)) {
            $this->dateCreated = $dateCreated;
        }
    }

    /**
     * Set the Date First Reported to Public Health.
     * 
     * @param DateTime $dateReported Date First Reported to Public Health
     */
    protected function setDateReported(DateTime $dateReported = null)
    {
        if (!empty($dateReported)) {
            $this->dateReported = $dateReported;
        }
    }

    /**
     * Set the Date Deleted.
     * 
     * @param DateTime $dateDeleted Date Deleted
     */
    protected function setDateDeleted(DateTime $dateDeleted = null)
    {
        if (!empty($dateDeleted)) {
            $this->dateDeleted = $dateDeleted;
        }
    }

    /**
     * Set the most-recent Treatment Date for this event.
     * 
     * @param DateTime $lastTreatmentDate Newest Treatment Date
     */
    protected function setLastTreatmentDate(DateTime $lastTreatmentDate = null)
    {
        if (!empty($lastTreatmentDate)) {
            $this->lastTreatmentDate = $lastTreatmentDate;
        }
    }

    /**
     * Set the Specimen Collection Date from the earliest positive lab found in this event.
     * 
     * @param DateTime $firstPositiveLabCollectionDate Specimen collection date
     */
    protected function setFirstPositiveLabCollectionDate(DateTime $firstPositiveLabCollectionDate = null)
    {
        if (!empty($firstPositiveLabCollectionDate)) {
            $this->firstPositiveLabCollectionDate = $firstPositiveLabCollectionDate;
        }
    }

    /**
     * Set the most-recent Specimen Collection Date associated with a positive lab found in this event.
     * 
     * @param DateTime $lastPositiveLabCollectionDate Specimen Collection Date
     */
    protected function setLastPositiveLabCollectionDate(DateTime $lastPositiveLabCollectionDate = null)
    {
        if (!empty($lastPositiveLabCollectionDate)) {
            $this->lastPositiveLabCollectionDate = $lastPositiveLabCollectionDate;
        }
    }

    /**
     * Set the most-recent Lab Test Date associated with a positive lab found in this event.
     *
     * @param DateTime $lastPositiveLabTestDate Lab Test Date
     */
    protected function setLastPositiveLabTestDate(DateTime $lastPositiveLabTestDate = null)
    {
        if (!empty($lastPositiveLabTestDate)) {
            $this->lastPositiveLabTestDate = $lastPositiveLabTestDate;
        }
    }

    /**
     * Set the most-recent Specimen Collection Date assocaited with a lab found in this event.
     *
     * @param DateTime $lastLabCollectionDate Specimen Collection Date
     */
    protected function setLastLabCollectionDate(DateTime $lastLabCollectionDate = null)
    {
        if (!empty($lastLabCollectionDate)) {
            $this->lastLabCollectionDate = $lastLabCollectionDate;
        }
    }

    /**
     * Set the most-recent Lab Test Date from associated with a lab found in this event.
     *
     * @param DateTime $lastLabTestDate Specimen collection date
     */
    protected function setLastLabTestDate(DateTime $lastLabTestDate = null)
    {
        if (!empty($lastLabTestDate)) {
            $this->lastLabTestDate = $lastLabTestDate;
        }
    }

    /**
     * Set the condition name.
     * 
     * @param string $diseaseName NEDSS condition name
     */
    protected function setDiseaseName($diseaseName)
    {
        if (!empty($diseaseName)) {
            $this->diseaseName = trim($diseaseName);
        }
    }

    /**
     * Set the jurisdiction name.
     * 
     * @param string $jurisdictionName Jurisdiction Name
     */
    protected function setJurisdictionName($jurisdictionName)
    {
        if (!empty($jurisdictionName)) {
            $this->jurisdictionName = (string) $jurisdictionName;
        }
    }

    /**
     * Set the NEDSS record number.
     * 
     * @param string $recordNumber NEDSS record number
     */
    protected function setRecordNumber($recordNumber)
    {
        if (!empty($recordNumber)) {
            $this->recordNumber = trim($recordNumber);
        }
    }

    /**
     * Set the NEDSS workflow status.
     * 
     * @param string $workflowStatus NEDSS workflow status
     */
    protected function setWorkflowStatus($workflowStatus)
    {
        if (!empty($workflowStatus)) {
            $this->workflowStatus = trim($workflowStatus);
        }
    }

    /**
     * Set the State case status.
     * 
     * @param string $stateCaseStatus State case status
     */
    protected function setStateCaseStatus($stateCaseStatus)
    {
        if (!empty($stateCaseStatus)) {
            $this->stateCaseStatus = trim($stateCaseStatus);
        }
    }

}

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
use Udoh\Emsa\Constants\MessageType;
use Udoh\Emsa\Model\AppLab;
use Udoh\Emsa\Model\AppLabTest;
use Udoh\Emsa\Rules\WhitelistRuleTupleSet;

/**
 * Utilities for comparing EMSA messages against NEDSS events
 *
 * @author Josh Ridderhoff <jridderhoff@utah.gov>
 */
class EmsaCompareUtils
{
    
    /**
     * Compares the mapped Lab Test Status of the incoming message in EMSA against a matching Lab Test 
     * in the target Application to see if the Test Status has been upgraded.
     * 
     * @param string $oldTestStatus Name of the Lab Test Status from the existing record.
     * @param string $newTestStatus Name of the Lab Test Status from the incoming EMSA message.
     * 
     * @return boolean <b>TRUE</b> if the new Test Status should override the existing Test Status, or if the existig record's lab does not have a Test Status set yet.  <b>FALSE</b> otherwise.
     */
    protected static function isTestStatusUpgraded($oldTestStatus = null, $newTestStatus = null)
    {
        if (empty($newTestStatus)) {
            return false;
        }
        
        if (empty($oldTestStatus)) {
            return true;
        }
        
        $testStatusUpgraded = false;
        
        $testStatusHierarchy = array(
            'pending'               => 0,
            'preliminary result'    => 1,
            'final'                 => 2,
            'corrected or amended'  => 3,
            'testing canceled'      => 4
        );
        
        if (array_key_exists(strtolower($oldTestStatus), $testStatusHierarchy) && array_key_exists(strtolower($newTestStatus), $testStatusHierarchy)) {
            if ((int) $testStatusHierarchy[strtolower($newTestStatus)] > (int) $testStatusHierarchy[strtolower($oldTestStatus)]) {
                $testStatusUpgraded = true;
            }
        }
        
        return $testStatusUpgraded;
    }

    /**
     * Compare EMSA message lab results against Application record lab results to determine how to update the record.
     * 
     * @param EmsaMessage $emsaMessage EMSA message being assigned
     * @param EmsaCompareNedssEvent $nedssEvent Target Application record to be updated
     * 
     * @return EmsaLabCompareResult Results of lab comparison
     */
    public static function compareLabsForUpdateRecord(EmsaMessage $emsaMessage, EmsaCompareNedssEvent $nedssEvent): EmsaLabCompareResult
    {
        $sourceLab = $emsaMessage->getSource();
        $compareResult = new EmsaLabCompareResult();

        if (($emsaMessage->getMessageType() === MessageType::CLINICAL_DOCUMENT) && !$emsaMessage->isSenderAssigningECRLabs) {
            return $compareResult->setClinicalDocument();
        }

        if (!empty($nedssEvent->getAppLabs())) {
            /* @var $foundLab AppLab */
            foreach ($nedssEvent->getAppLabs() as $foundLab) {
                $foundLabId = $foundLab->getId();
                $foundLabTestId = null;
                $foundLabTestResultId = null;
                
                $matchLabName = (strtolower($sourceLab->getLabName()) === strtolower($foundLab->getPerformingLabName()));
                $matchDateCollected = ($sourceLab->getDateCollected(true, "Y-m-d") === $foundLab->getCollectionDate(true, "Y-m-d"));
                $matchAccessionNumber = ($sourceLab->getAccessionNumber() === $foundLab->getAccessionNumber());
                // not currently comparing Specimen Source, but will save for later... 
                // $matchSpecimenSource = ($sourceLab->getSpecimenSource() === $foundLab->getSpecimenSource());
                
                if (!empty($foundLab->getLabTests())) {
                    /* @var $foundLabTest AppLabTest */
                    foreach ($foundLab->getLabTests() as $foundLabTest) {
                        $foundLabTestId = $foundLabTest->getId();
                        $foundLabTestResultId = null;

                        $matchTestStatus = ($sourceLab->getTestStatus() === $foundLabTest->getTestStatus());
                        $matchMasterLoinc = ($sourceLab->getMasterLoinc() === $foundLabTest->getLoincCode());

                        $upgradeTestStatus = self::isTestStatusUpgraded($foundLabTest->getTestStatus(), $sourceLab->getTestStatus());

                        if (!empty($foundLabTest->getTestResults())) {
                            /* @var $foundLabTestResult Udoh\Emsa\Model\AppLabTestResult */
                            foreach ($foundLabTest->getTestResults() as $foundLabTestResult) {
                                $foundLabTestResultId = $foundLabTestResult->getId();
                                $throwStitchingException = false;
                                $matchTestResult = true;

                                $matchOrganism = ($sourceLab->getOrganism() === $foundLabTestResult->getOrganism());

                                if ($emsaMessage->getIsKnittable()) {
                                    if (empty($sourceLab->getResultValue()) && empty($sourceLab->getComment())) {
                                        // setting test result id only; no way to tell if duplicate, so assume it is not
                                        $matchTestResult = false;
                                    } elseif (empty($sourceLab->getResultValue())) {
                                        // setting comment
                                        if (empty($foundLabTestResult->getComment()) || (stripos($foundLabTestResult->getComment(), $sourceLab->getComment()) === false)) {
                                            $matchTestResult = false;
                                        }
                                    } else {
                                        // setting result value
                                        if (empty($foundLabTestResult->getResultValue())) {
                                            $matchTestResult = false;
                                        } else {
                                            if ($foundLabTestResult->getResultValue() !== $sourceLab->getResultValue()) {
                                                $matchTestResult = false;
                                                $throwStitchingException = true;
                                            }
                                        }
                                    }

                                    // if test status is lower than existing test status, treat as duplicate (do not knit)
                                    if (!$upgradeTestStatus && !$matchTestStatus) {
                                        $matchTestResult = true;
                                    }

                                    // if test result would be changed via knitting, throw exception
                                    if ($foundLabTestResult->getTestResult() != $sourceLab->getTestResult()) {
                                        $throwStitchingException = true;
                                    }
                                } else {
                                    if (empty($sourceLab->getResultValue()) && empty($foundLabTestResult->getResultValue())) {
                                        // result value blank in both incoming lab & existing lab, defer to test result id
                                        if ($foundLabTestResult->getTestResult() != $sourceLab->getTestResult()) {
                                            $matchTestResult = false;
                                        }
                                    } elseif (empty($sourceLab->getResultValue()) || empty($foundLabTestResult->getResultValue())) {
                                        // result value in either ELR message or current lab being evaluated is blank, results are different
                                        $matchTestResult = false;
                                    } else {
                                        // compare result value
                                        if ($foundLabTestResult->getResultValue() != $sourceLab->getResultValue()) {
                                            $matchTestResult = false;
                                            $throwStitchingException = true;
                                        }
                                    }
                                }

                                // also check any susceptibilities...
                                $resistExists = false;
                                $resistMatch = null;  // if null, is a new antimicrobial resistance result
                                $resistTestId = null;
                                if (!empty($sourceLab->getSourceSusceptibility())) {
                                    $resistExists = true;
                                    /* @var $foundResistTest Udoh\Emsa\Model\AppResistTest */
                                    foreach ($foundLabTestResult->getResistTests() as $foundResistTest) {
                                        $matchResistAgentName = ($foundResistTest->getAgentName() == $sourceLab->getSourceSusceptibility()->getAgentName());
                                        $matchResistResult = ($foundResistTest->getTestResult() == $sourceLab->getSourceSusceptibility()->getTestResult());
                                        $matchResistResultValue = ($foundResistTest->getResultValue() == $sourceLab->getSourceSusceptibility()->getResultValue());

                                        if ($matchResistAgentName) {
                                            $resistTestId = $foundResistTest->getId();

                                            if ($matchResistResult && $matchResistResultValue) {
                                                $resistMatch = true;  // duplicate
                                            } else {
                                                if (!$emsaMessage->getAstMultiColony()) {
                                                    $resistMatch = false;  // result changed; exception
                                                }
                                            }
                                        }
                                    }
                                }

                                // evaluate which action to take...
                                if ($matchLabName && $matchDateCollected && $matchAccessionNumber && $matchMasterLoinc && $matchTestResult && $matchOrganism) {
                                    if ($matchTestStatus) {
                                        // duplicate lab test result; discard
                                        $compareResult->addDuplicateLabId($foundLabTestResultId);

                                        if ($resistExists) {
                                            if (is_null($resistMatch)) {
                                                // new resist test; add
                                                // resistance test exists in source; set lab/lab test/lab test result IDs for containing susceptibility
                                                $compareResult->setUpdateLab($foundLabId, $foundLabTestId, $foundLabTestResultId, $foundLabTestResult->getResultValue(), $foundLabTestResult->getUnits(), $foundLabTestResult->getComment(), $foundLabTest->getReferenceRange(), $upgradeTestStatus);
                                                $compareResult->addUpdateResistTest(-1);
                                            } elseif ($resistMatch) {
                                                $compareResult->addDuplicateResistTestId($resistTestId);
                                            } else {
                                                $compareResult->addExceptionResistTestId($resistTestId);
                                            }
                                        }
                                    } else {
                                        // update test status
                                        // check test status priority, discard if same/lower
                                        if ($upgradeTestStatus) {
                                            $compareResult->setUpdateLab($foundLabId, $foundLabTestId, $foundLabTestResultId, $foundLabTestResult->getResultValue(), $foundLabTestResult->getUnits(), $foundLabTestResult->getComment(), $foundLabTest->getReferenceRange(), $upgradeTestStatus);

                                            if ($resistExists) {
                                                if (is_null($resistMatch)) {
                                                    // new resist test; add
                                                    $compareResult->addUpdateResistTest(-1);
                                                } elseif ($resistMatch) {
                                                    $compareResult->addDuplicateResistTestId($resistTestId);
                                                } else {
                                                    $compareResult->addExceptionResistTestId($resistTestId);
                                                }
                                            }
                                        } else {
                                            $compareResult->addDuplicateLabId($foundLabTestResultId);

                                            if ($resistExists) {
                                                if (is_null($resistMatch)) {
                                                    // new resist test; add
                                                    // resistance test exists in source; set lab/lab test/lab test result IDs for containing susceptibility
                                                    $compareResult->setUpdateLab($foundLabId, $foundLabTestId, $foundLabTestResultId, $foundLabTestResult->getResultValue(), $foundLabTestResult->getUnits(), $foundLabTestResult->getComment(), $foundLabTest->getReferenceRange(), $upgradeTestStatus);
                                                    $compareResult->addUpdateResistTest(-1);
                                                } elseif ($resistMatch) {
                                                    $compareResult->addDuplicateResistTestId($resistTestId);
                                                } else {
                                                    $compareResult->addExceptionResistTestId($resistTestId);
                                                }
                                            }
                                        }
                                    }
                                } elseif (!$matchLabName && $matchDateCollected && $matchMasterLoinc && $matchTestResult && $matchOrganism) {
                                    // same test & results, but from different lab... discard
                                    $compareResult->addDuplicateLabId($foundLabTestResultId);

                                    if ($resistExists) {
                                        if (is_null($resistMatch)) {
                                            // new resist test; add
                                            // resistance test exists in source; set lab/lab test/lab test result IDs for containing susceptibility
                                            $compareResult->setUpdateLab($foundLabId, $foundLabTestId, $foundLabTestResultId, $foundLabTestResult->getResultValue(), $foundLabTestResult->getUnits(), $foundLabTestResult->getComment(), $foundLabTest->getReferenceRange(), $upgradeTestStatus);
                                            $compareResult->addUpdateResistTest(-1);
                                        } elseif ($resistMatch) {
                                            $compareResult->addDuplicateResistTestId($resistTestId);
                                        } else {
                                            $compareResult->addExceptionResistTestId($resistTestId);
                                        }
                                    }
                                } elseif ($emsaMessage->getIsKnittable() && $matchLabName && $matchDateCollected && $matchAccessionNumber && $matchMasterLoinc) {
                                    // knit lab results
                                    $compareResult->setUpdateLab($foundLabId, $foundLabTestId, $foundLabTestResultId, $foundLabTestResult->getResultValue(), $foundLabTestResult->getUnits(), $foundLabTestResult->getComment(), $foundLabTest->getReferenceRange(), $upgradeTestStatus);

                                    if ($resistExists) {
                                        if (is_null($resistMatch)) {
                                            // new resist test; add
                                            $compareResult->addUpdateResistTest(-1);
                                        } elseif ($resistMatch) {
                                            $compareResult->addDuplicateResistTestId($resistTestId);
                                        } else {
                                            $compareResult->addExceptionResistTestId($resistTestId);
                                        }
                                    }
                                } else {
                                    // add new lab to event
                                }

                                if ($emsaMessage->getIsSenderUsingKnitting() && $matchMasterLoinc && $matchDateCollected && $matchAccessionNumber && $matchOrganism && !$matchTestResult && $throwStitchingException) {
                                    // if sender uses lab knitting & same collection date but different results for same Master LOINC, move to exception
                                    $compareResult->addExceptionLabId($foundLabTestResultId);
                                }
                            }
                        }
                    }
                }
            }
        }

        return $compareResult->evaluateCompareResults();
    }

    /**
     * Get a set of HTML links for a given WhitelistRuleTupleSet.
     *
     * @param AppClientInterface    $appClient       Application to target the link to
     * @param WhitelistRuleTupleSet $whitelistTupleSet
     * @param int                   $recordType      One of \Udoh\Emsa\Constants\AppRecordType
     * @param int                   $excludedEventId [Optional]<br>If specified, exclude an event with this event ID from the set of links generated.
     *
     * @return string
     */
    public static function nedssLinkByWhitelistRuleTupleSet(AppClientInterface $appClient, WhitelistRuleTupleSet $whitelistTupleSet, $recordType, $excludedEventId = null)
    {
        $eventList = '';
        
        foreach ($whitelistTupleSet as $whitelistTuple) {
            if (empty($excludedEventId) || ($excludedEventId != $whitelistTuple->getNedssEvent()->getEventId())) {
                $eventList .= self::nedssLinkByEventId($appClient, $whitelistTuple->getNedssEvent()->getEventId(), $whitelistTuple->getNedssEvent()->getRecordNumber(), $recordType);
            }
        }
        
        return $eventList;
    }

    /**
     * Get an HTML link to an application record specified by recort ID and record number.
     * 
     * @param AppClientInterface $appClient Application to target the link to
     * @param int $recordId Record ID
     * @param string $recordNumber Record Number
     * @param int $recordType One of \Udoh\Emsa\Constants\AppRecordType
     * 
     * @return string
     */
    public static function nedssLinkByEventId(AppClientInterface $appClient, $recordId, $recordNumber, $recordType)
    {
        $viewLink = $appClient->getAppLinkToRecord($recordType, $recordNumber, true, $recordId);
        return '<a target="_blank" href="' . $viewLink . '" class="emsa_btn_viewnedss" id="emsa_btn_viewnedss_' . $recordId . '" title="View in ' . $appClient->getAppName() . '">Record# ' . $recordNumber . '</a> [Event ID#: ' . $recordId . ']<br>';
    }

    /**
     * Get a set of HTML links for an array of... things.
     * 
     * @param AppClientInterface $appClient Application to target the link to
     * @param array $eventArray
     * 
     * @return string
     */
    public static function nedssLinkByEventArray(AppClientInterface $appClient, $eventArray)
    {
        $eventList = '';
        
        foreach ($eventArray as $eventId => $eventData) {
            $eventList .= self::nedssLinkByEventId($appClient, $eventId, $eventData['record_number'], $eventData['record_type']);
        }
        
        return $eventList;
    }

    /**
     * @param AppClientInterface $appClient Application to target the link to
     * @param int                $personId  ID of the targeted person
     * @param bool|null          $readOnly  [Optional; Default <b>TRUE</b>] If <b>FALSE</b> and Application supports the feature, link points to an editable view of the person.  Otherwise, link points to read-only view.
     *
     * @return string
     */
    public static function appLinkByPersonId(AppClientInterface $appClient, int $personId, ?bool $readOnly = true): string
    {
        if (is_null($readOnly)) {
            $readOnly = true;
        }

        $viewLink = $appClient->getAppLinkToPerson($personId, $readOnly);
        return '<a target="_blank" href="' . $viewLink . '" class="emsa_btn_viewnedss" id="emsa_btn_viewnedss_' . $personId . '" title="View Person in ' . $appClient->getAppName() . '">Person ID# ' . (int) $personId . '</a><br>';
    }

    /**
     * @param AppClientInterface $appClient     Application to target the link to
     * @param array              $personIDArray Array of Person IDs
     * @param bool|null          $readOnly      [Optional; Default <b>TRUE</b>] If <b>FALSE</b> and Application supports the feature, link points to an editable view of the person.  Otherwise, link points to read-only view.
     *
     * @return string
     */
    public static function appLinkByPersonIDArray(AppClientInterface $appClient, array $personIDArray, ?bool $readOnly = true): string
    {
        if (is_null($readOnly)) {
            $readOnly = true;
        }

        $personIDLinkList = '';

        foreach ($personIDArray as $personId) {
            $personIDLinkList .= self::appLinkByPersonId($appClient, $personId, $readOnly);
        }

        return $personIDLinkList;
    }

}

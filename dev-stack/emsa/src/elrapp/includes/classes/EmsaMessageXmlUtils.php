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
use Udoh\Emsa\Constants\SystemMessageActions;
use Udoh\Emsa\Exceptions\AppClientNoValidHosts;
use Udoh\Emsa\Model\Person;
use Udoh\Emsa\Utils\AppClientUtils;
use Udoh\Emsa\Utils\CodedDataUtils;
use Udoh\Emsa\Utils\CoreUtils;
use Udoh\Emsa\Utils\DateTimeUtils;
use Udoh\Emsa\Utils\DisplayUtils;
use Udoh\Emsa\Utils\ExceptionUtils;
use Udoh\Emsa\Utils\VocabUtils;

/**
 * Implementation of XML-modifying functions used during EMSA message assignment
 *
 * @author Josh Ridderhoff <jridderhoff@utah.gov>
 */
class EmsaMessageXmlUtils
{

    /**
     * Appends original HL7 message as a Note when adding a new record from ELR,
     * as well as any demographic data differences in the event of adding a record to an existing Person
     *
     * @param PDO              $dbConn            PDO connection to EMSA database
     * @param EmsaMessage      $emsaMessage       EMSA message being assigned
     * @param SimpleXMLElement $finalAddRecordXML SimpleXMLElement containing the Application XML being prepared to add/update
     * @param Person           $targetPerson      Person the new record is being created for.<br>[Optional; Required if adding a new record to an existing person.]
     * @param bool             $isInterstateTx    [Optional; default FALSE]<br>If TRUE, indicates notes being generated as result of an EMSA message that has been transmitted to another State via Interstate Trasmission
     */
    public static function addRecordHL7ToNotes(PDO $dbConn, EmsaMessage $emsaMessage, SimpleXMLElement $finalAddRecordXML, Person $targetPerson = null, ?bool $isInterstateTx = false)
    {
        $hl7String = EmsaUtils::getRawOriginalMessageFormatted($dbConn, $emsaMessage->getSystemMessageId(), $emsaMessage->getMessageType());
        $demographicDiffs = null;
        $noteText = null;
        
        if (!is_null($targetPerson)) {
            // if adding record to new person, check for differences in demographic data
            $demographicDiffs = self::demographicDataDiff($emsaMessage, $targetPerson, $finalAddRecordXML);
        }
        
        if (!empty(trim($demographicDiffs))) {
            $noteText .= "<strong style='color: orangered;'>Updated Demographic Data:</strong><br><em style='color: dimgray;'>(If listed below, electronically-reported value differs from previously-known value in NEDSS)</em><br>";
            $noteText .= $demographicDiffs;
        }

        if ($isInterstateTx) {
            $noteText .= "<strong style='color: orangered;'>Out of State Message Information:</strong><br>";
            $noteText .= "<span>The ELR message that created this event indicates that the patient lives out-of-state.  This message has already been shared electronically with State the patient resides in.  Further follow-up with external jurisdiction may still be necessary.</span>";
            $noteText .= "<br><br>";
        }
        
        $noteText .= "<strong>Original HL7 Message:</strong><br><span style='font-family: monospace; color: cadetblue;'>" . nl2br(trim($hl7String) . "</span>");
        
        $newNote = $finalAddRecordXML->person->personCondition->addChild('note');
        $newNote->addChild('note');
        $newNote->note = (string) $noteText;
        $newNote->addChild('noteType', 'system');
        $newNote->addChild('user')->addChild('uid', EPITRAX_AUTH_ELR_UID);
    }

    /**
     * For 'addCmr' actions (creating a new CMR for a new person), decodes the coded Abnormal Flag values & appends to comments.
     *
     * @param PDO              $dbConn      PDO connection to EMSA database
     * @param SimpleXMLElement $masterXML   SimpleXMLElement object representing the Master XML
     * @param SimpleXMLElement $appXML      SimpleXMLElement object representing the application XML being prepared for adding a record.
     * @param EmsaMessage      $emsaMessage EMSA Message being assigned
     *
     * @return void
     */
    public static function addRecordSetLabComments(PDO $dbConn, SimpleXMLElement $masterXML, SimpleXMLElement $appXML, EmsaMessage $emsaMessage): void
    {
        $labId = (int) $emsaMessage->labId;
        $commentDate = date(DATE_RSS, time());
        $assigningMessageType = ($emsaMessage->getMessageType() === MessageType::ELR_MESSAGE) ? "ELR" : "eCR";
        $commentHeader = "[===== Lab added by $assigningMessageType $commentDate ==========]";
        $commentFooter = "\n[===== End $assigningMessageType Update ==========]\n\n";

        if (!isset($masterXML->labs)) {
            // if there aren't any lab results in the Master XML (e.g. Clinical Documents), 
            // the rest of this function is pretty pointless.
            return;
        }

        $performingLabName = (string) filter_var($appXML->person->personCondition->lab->labFacility->name, FILTER_SANITIZE_STRING);

        if (!empty($appXML->person->personCondition->lab->labTest->labTestResult->comment)) {
            $mappedComments = (string) $appXML->person->personCondition->lab->labTest->labTestResult->comment;
        } elseif (!empty($masterXML->labs->comment)) {
            // in case of mapped comments that were overlooked due to MP's LabResultsCommentRule.eval(), pull them in
            $mappedComments = (string) $masterXML->labs->comment;
        }

        if (isset($masterXML->labs->abnormal_flag) && !empty($masterXML->labs->abnormal_flag)) {
            $abnormalFlagText = 'Abnormal Flag:  ' . VocabUtils::decodeAbnormalFlag($dbConn, trim($masterXML->labs->abnormal_flag));
        }

        // also append Local test name, if any
        if (!empty($masterXML->labs->local_code_test_name)) {
            $localTestName = $performingLabName . ' Local Code Test Name: ' . htmlspecialchars(trim($masterXML->labs->local_code_test_name));
        } elseif (!empty($masterXML->labs->local_test_name)) {
            $localTestName = $performingLabName . ' Local Test Name: ' . htmlspecialchars(trim($masterXML->labs->local_test_name));
        }

        // get comments from specimen source
        if (!empty($masterXML->labs->parent_specimen_source)) {
            $specimenComments = htmlspecialchars(VocabUtils::getSpecimenComments($dbConn, $labId, trim($masterXML->labs->parent_specimen_source)));
        } elseif (!empty($masterXML->labs->local_specimen_source)) {
            $specimenComments = htmlspecialchars(VocabUtils::getSpecimenComments($dbConn, $labId, trim($masterXML->labs->local_specimen_source)));
        }

        $newComments = $commentHeader;
        if (isset($mappedComments)) {
            $newComments .= "\n$mappedComments";
        }
        if (isset($abnormalFlagText)) {
            $newComments .= "\n$abnormalFlagText";
        }
        if (isset($localTestName)) {
            $newComments .= "\n$localTestName";
        }
        if (!empty($specimenComments)) {
            $newComments .= "\nAdd'l Specimen Details: $specimenComments";
        }
        $newComments .= $commentFooter;

        if (isset($newComments)) {
            if (!isset($appXML->person->personCondition->lab->labTest->labTestResult->comment)) {
                $appXML->person->personCondition->lab->labTest->labTestResult->addChild('comment', $newComments);
            } else {
                // htmlspecialchars_decode() to prevent double-encoding of html entities
                $appXML->person->personCondition->lab->labTest->labTestResult->comment = htmlspecialchars_decode($newComments);
            }
        }
    }

    /**
     * Set the workflow status of an event being assigned.
     * 
     * Changes:
     * - [2013-06-26] Switch auto-close to use 'closed' instead of 'approved_by_lhd' (request by S. Mottice)
     * - [2013-12-12] Change function to be generic and allow any workflow status to be sent (with filtered list).  Renamed from 'closeSurveillanceEvent(SimpleXMLElement)' to 'setWorkflow(SimpleXMLElement, string)'
     *
     * @param SimpleXMLElement $appXML SimpleXMLElement object representing the NEDSS XML
     * @param string $status Workflow status to be set
     * 
     * @static
     */
    public static function setWorkflow(SimpleXMLElement $appXML, $status)
    {
        switch (strtolower(trim($status))) {
            case 'approved_by_lhd':
            case 'closed':
            case 'assigned_to_investigator':
                $cleanStatus = strtolower(trim($status));
                break;
            default:
                $cleanStatus = null;
        }
        if (!empty($cleanStatus)) {
            $appXML->person->personCondition->currentWorkflow->code = $cleanStatus;
        }
    }
    
    /**
     * For any treatments found, set the "Data Source" field to indicate ELR.
     * 
     * @param SimpleXMLElement $appXML SimpleXMLElement object representing the NEDSS XML
     */
    public static function setTreatmentDataSource(SimpleXMLElement $appXML)
    {
        if (!empty($appXML->person->personCondition->personTreatment)) {
            foreach ($appXML->person->personCondition->personTreatment as $foundTreatment) {
                $foundTreatment->dataSource = "eCR";
            }
        }
    }

    /**
     * Prepare record XML for updating an existing Application record
     * 
     * @param SimpleXMLElement $finalUpdateRecordXML Application XML being prepared for updating the record.
     * @param EmsaCompareNedssEvent $existingRecord Target Application record being updated
     * 
     * @static
     */
    public static function prepareRecordXMLForUpdateRecord(SimpleXMLElement $finalUpdateRecordXML, EmsaCompareNedssEvent $existingRecord)
    {
        // set person ID
        $finalUpdateRecordXML->person->addChild('id', (string) $existingRecord->getPersonId());

        // set record ID
        $finalUpdateRecordXML->person->addChild('personCondition')->addChild('id', (string) $existingRecord->getEventId());

        // if existing record does not already have a 'first reported to public health' date set (i.e. legacy event), 
        // set it to be the record's creation date to prevent validation errors
        if (empty($existingRecord->getDateReported())) {
            $finalUpdateRecordXML->person->personCondition->addChild('firstReportedPhDate', (string) $existingRecord->getDateCreated(true, DateTime::RFC3339));
        }
    }

    /**
     * Prepares an EmsaMessage to update an existing Lab in a NEDSS event with new results.
     * 
     * @param EmsaLabCompareUpdateLab $updateLab Participation & results data for the existing lab being updated
     * @param SimpleXMLElement $finalUpdateRecordXML Application XML being prepared for updating the record.
     * 
     * @static
     */
    public static function prepareEmsaMessageLabDataForUpdateCmr(EmsaLabCompareUpdateLab $updateLab, SimpleXMLElement $finalUpdateRecordXML)
    {
        $finalUpdateRecordXML->person->personCondition->addChild('lab')->addChild('id', (string) $updateLab->labId);
        $finalUpdateRecordXML->person->personCondition->lab->addChild('labTest')->addChild('id', (string) $updateLab->labTestId);
        $finalUpdateRecordXML->person->personCondition->lab->labTest->addChild('labTestResult')->addChild('id', (string) $updateLab->labTestResultId);
    }

    /**
     * Manage updates to lab comments section during updateCmr
     * 
     * Handles stitching in comments for lab results and retaining existing lab comments so existing values
     * aren't overwritten, and decoding/appending Abnormal Flags to comments.
     * 
     * @param PDO $dbConn PDO connection to EMSA database
     * @param EmsaMessage $emsaMessage EMSA message being assigned
     * @param SimpleXMLElement $finalUpdateRecordXML Application XML being prepared for updating the record.
     * @param EmsaLabCompareUpdateLab $updateLab [Optional]<br>Participation & results data for the existing lab being updated.  Use <b>NULL</b> to skip if adding a new lab to the NEDSS event.
     * @param bool $isNewLab [Optional]<br>If <b>TRUE</b>, indicates the comments apply to a new lab being added to the NEDSS event.  If <b>FALSE</b>, comments are being applied to an existing lab in the NEDSS event being updated.  Default <b>FALSE</b>.
     * 
     * @static
     */
    public static function prepareLabCommentsForUpdateCmr(PDO $dbConn, EmsaMessage $emsaMessage, SimpleXMLElement $finalUpdateRecordXML, EmsaLabCompareUpdateLab $updateLab = null, $isNewLab = false)
    {
        $commentDate = date(DATE_RSS, time());
        $appXML = $emsaMessage->getApplicationXML();
        $masterXML = $emsaMessage->getMasterXML();

        // skip updates to lab comments if only adding AST results...
        if (empty($finalUpdateRecordXML->person->personCondition->lab->labTest->testStatus->code) && empty($finalUpdateRecordXML->person->personCondition->lab->labTest->labTestResult->testResult->code)) {
            return;
        }
        
        if (!empty($updateLab)) {
            $oldComments = $updateLab->comment;
        } else {
            $oldComments = '';
        }

        if (isset($appXML->person->personCondition->lab->labTest->labTestResult->comment) && !empty($appXML->person->personCondition->lab->labTest->labTestResult->comment)) {
            $newComments = 'New Comments:  ' . trim($appXML->person->personCondition->lab->labTest->labTestResult->comment) . "\n";
        } else {
            $appXML->person->personCondition->lab->labTest->labTestResult->addChild('comment');
            if (!empty($masterXML->labs->comment)) {
                // in case of mapped comments that were overlooked due to MP's LabResultsCommentRule.eval(), pull them in
                $newComments = 'New Comments:  ' . trim($masterXML->labs->comment) . "\n";
            } else {
                $newComments = '';
            }
        }

        if (isset($masterXML->labs->abnormal_flag) && !empty($masterXML->labs->abnormal_flag)) {
            $newComments .= 'Abnormal Flag:  ' . EmsaUtils::decodeAbnormalFlag($dbConn, trim($masterXML->labs->abnormal_flag)) . "\n";
        }

        // also append Local test name, if any
        if (isset($masterXML->labs->local_code_test_name) && !empty($masterXML->labs->local_code_test_name)) {
            $newComments .= trim($appXML->person->personCondition->lab->labFacility->name) . ' Local Code Test Name: ' . DisplayUtils::xSafe(trim($masterXML->labs->local_code_test_name)) . "\n";
        } elseif (isset($masterXML->labs->local_test_name) && !empty($masterXML->labs->local_test_name)) {
            $newComments .= trim($appXML->person->personCondition->lab->labFacility->name) . ' Local Test Name: ' . DisplayUtils::xSafe(trim($masterXML->labs->local_test_name)) . "\n";
        }

        // get comments from specimen source
        if (!empty($masterXML->labs->parent_specimen_source)) {
            $specimenComments = DisplayUtils::xSafe(VocabUtils::getSpecimenComments($dbConn, $emsaMessage->labId, trim($masterXML->labs->parent_specimen_source)));
        } elseif (!empty($masterXML->labs->local_specimen_source)) {
            $specimenComments = DisplayUtils::xSafe(VocabUtils::getSpecimenComments($dbConn, $emsaMessage->labId, trim($masterXML->labs->local_specimen_source)));
        }

        if (!empty($specimenComments)) {
            $newComments .= "Add'l Specimen Details: $specimenComments\n";
        }

        if (!empty($newComments)) {
            $newComments .= "\n";
        }

        $assigningMessageType = ($emsaMessage->getMessageType() === MessageType::ELR_MESSAGE) ? "ELR" : "eCR";

        if ($isNewLab) {
            $commentHeader = "[===== Lab added by $assigningMessageType $commentDate ==========]\n";
        } else {
            $commentHeader = "[===== Lab updated by $assigningMessageType $commentDate ==========]\n";
        }

        $commentFooter = '[===== End ELR Update ==========]' . "\n\n";

        // set the new comment
        if (!isset($finalUpdateRecordXML->person->personCondition->lab->labTest->labTestResult->comment)) {
            $finalUpdateRecordXML->person->personCondition->lab->labTest->labTestResult->addChild('comment', $commentHeader . $newComments . $commentFooter . $oldComments);
        } else {
            // htmlspecialchars_decode() to prevent double-encoding of html entities
            $finalUpdateRecordXML->person->personCondition->lab->labTest->labTestResult->comment = htmlspecialchars_decode($commentHeader . $newComments . $commentFooter . $oldComments);
        }
    }

    /**
     * Stitch incoming lab result data with existing lab result data in NEDSS.
     * 
     * @param EmsaMessage $emsaMessage EMSA message being assigned
     * @param SimpleXMLElement $finalUpdateRecordXML Application XML being prepared for updating the record.
     * 
     * @static
     */
    public static function stitchLabData(EmsaMessage $emsaMessage, SimpleXMLElement $finalUpdateRecordXML)
    {
        $appXML = $emsaMessage->getApplicationXML();
        
        if (!empty($emsaMessage->getSource()->getResultValue())) {
            // update result value
            if (!empty($appXML->person->personCondition->lab->labTest->labTestResult->resultValue)) {
                $finalUpdateRecordXML->person->personCondition->lab->labTest->labTestResult->addChild('resultValue', (string) $appXML->person->personCondition->lab->labTest->labTestResult->resultValue);
            }
            
            // if this is a stitchable LOINC that sets the result value and test has a Reference Range specified, update the Reference Range
            // otherwise preserve Reference Range from NEDSS
            if (!empty($appXML->person->personCondition->lab->labTest->referenceRange)) {
                $finalUpdateRecordXML->person->personCondition->lab->labTest->addChild('referenceRange', (string) $appXML->person->personCondition->lab->labTest->referenceRange);
            }

            // if setting result value and units are specified, update units
            if (!empty($appXML->person->personCondition->lab->labTest->labTestResult->units)) {
                $finalUpdateRecordXML->person->personCondition->lab->labTest->labTestResult->addChild('units', (string) $appXML->person->personCondition->lab->labTest->labTestResult->units);
            }
        }

        // always set test result
        if (!empty($appXML->person->personCondition->lab->labTest->labTestResult->testResult->code)) {
            $finalUpdateRecordXML->person->personCondition->lab->labTest->labTestResult->addChild('testResult')->addChild('code', (string) $appXML->person->personCondition->lab->labTest->labTestResult->testResult->code);
        }

        // if comment is set, update it
        if (!empty($appXML->person->personCondition->lab->labTest->labTestResult->comment)) {
            $finalUpdateRecordXML->person->personCondition->lab->labTest->labTestResult->addChild('comment', (string) $appXML->person->personCondition->lab->labTest->labTestResult->comment);
        }
    }

    /**
     * Sets the upgraded Test Status in an existing record
     * 
     * @param EmsaMessage $emsaMessage EMSA message being assigned
     * @param SimpleXMLElement $finalUpdateRecordXML Application XML being prepared to update the record.
     * 
     * @static
     */
    public static function updateRecordUpgradeTestStatus(EmsaMessage $emsaMessage, SimpleXMLElement $finalUpdateRecordXML)
    {
        $appXML = $emsaMessage->getApplicationXML();

        if (!empty($appXML->person->personCondition->lab->labTest->testStatus->code)) {
            $finalUpdateRecordXML->person->personCondition->lab->labTest->addChild('testStatus')->addChild('code', (string) $appXML->person->personCondition->lab->labTest->testStatus->code);
        }
    }
    
    /**
     * Appends an Antimicrobial Susceptibility Test from the incoming message when updating an existing record.
     * 
     * @param EmsaMessage $emsaMessage EMSA message being assigned
     * @param SimpleXMLElement $finalUpdateRecordXML Application XML being prepared to update the record.
     * 
     * @static
     */
    public static function updateRecordAddResistTest(EmsaMessage $emsaMessage, SimpleXMLElement $finalUpdateRecordXML)
    {
        $appXML = $emsaMessage->getApplicationXML();
        
        self::appendXMLNode($finalUpdateRecordXML->person->personCondition->lab->labTest->labTestResult, $appXML->person->personCondition->lab->labTest->labTestResult->labResistTest);
    }

    /**
     * Miscellaneous cleanup tasks to prepare EMSA message XML for updateCmr
     *
     * @param PDO                     $dbConn               PDO connection to EMSA database
     * @param EmsaMessage             $emsaMessage          EMSA message being assigned
     * @param SimpleXMLElement        $finalUpdateRecordXML Application XML being prepared to update the record.
     * @param EmsaLabCompareUpdateLab $updateLab            [Optional]<br>Participation & results data for the existing lab being updated.  Use <b>NULL</b> to skip if adding a new lab to the NEDSS event.
     * @param bool                    $isNewLab             [Optional]<br>If <b>TRUE</b>, indicates the comments apply to a new lab being added to the NEDSS event.  If <b>FALSE</b>, comments are being applied to an existing lab in the NEDSS event being updated.  Default <b>FALSE</b>.
     * @param bool                    $skipLabAssignment    [Optional]<br>If <b>TRUE</b>, indicates lab data should not be assigned as part of this update.
     *
     * @static
     */
    public static function updateCmrCleanupTasks(PDO $dbConn, EmsaMessage $emsaMessage, SimpleXMLElement $finalUpdateRecordXML, EmsaLabCompareUpdateLab $updateLab = null, $isNewLab = false, $skipLabAssignment = false)
    {
        $appXML = $emsaMessage->getApplicationXML();

        // if not updating an existing lab, copy the entire new lab from the incoming message, if one exists
        if ($isNewLab && !empty($appXML->person->personCondition->lab)) {
            self::appendXMLNode($finalUpdateRecordXML->person->personCondition, $appXML->person->personCondition->lab);
        }
        
        // preserve addressAtDiagnosis (as a personAddress instead) for updates; application responsible for determining whether to update/add/discard
        // (per Workfront Task Ref# 34400)
        if (!empty($appXML->person->personCondition->addressAtDiagnosis)) {
            // geocode address first, to ensure state/county are set properly
            self::geocodeAddressXML($emsaMessage->getAppClient(), $appXML->person->personCondition->addressAtDiagnosis);
            $finalUpdateRecordXML->person->addChild('personAddress');
            
            foreach ($appXML->person->personCondition->addressAtDiagnosis->children() as $addressChildElement) {
                self::appendXMLNode($finalUpdateRecordXML->person->personAddress, $addressChildElement);
            }
        }
        
        // preserve personTelephone for updates; application responsible for determining whether to update/add/discard
        // (per Workfront Task Ref# 34400)
        if (!empty($appXML->person->personTelephone)) {
            self::appendXMLNode($finalUpdateRecordXML->person, $appXML->person->personTelephone);
        }

        // preserve personEmail for updates; application responsible for determining whether to update/add/discard
        // (per Workfront Task Ref# 34400)
        if (!empty($appXML->person->personEmail)) {
            self::appendXMLNode($finalUpdateRecordXML->person, $appXML->person->personEmail);
        }

        self::cleanupPregnancyStatus($emsaMessage, $finalUpdateRecordXML);  // handle pregnancy status

        // preserve any mapped healthcare facilities, unless for non-Clinical document without a visit type code
        if (!empty($appXML->person->personCondition->personFacility)) {
            for ($j = 0; $j < count($appXML->person->personCondition->personFacility); $j++) {
                if (($emsaMessage->getMessageType() === MessageType::CLINICAL_DOCUMENT) || (!empty($appXML->person->personCondition->personFacility[$j]->facilityVisitType->code))) {
                    self::appendXMLNode($finalUpdateRecordXML->person->personCondition, $appXML->person->personCondition->personFacility[$j]);
                }
            }
        }

        if (!$skipLabAssignment && !empty($finalUpdateRecordXML->person->personCondition->lab)) {
            // for messages assigning lab results, process lab comments
            self::prepareLabCommentsForUpdateCmr($dbConn, $emsaMessage, $finalUpdateRecordXML, $updateLab, $isNewLab);
        }

        if ($emsaMessage->getMessageType() == MessageType::CLINICAL_DOCUMENT) {
            // remove lab data, if present (unless sender is flagged to assign lab data from Clinical Documents)
            if (!$emsaMessage->isSenderAssigningECRLabs) {
                unset($finalUpdateRecordXML->person->personCondition->lab);
            }

            // set the data source for any mapped treatments...
            self::setTreatmentDataSource($appXML);

            // copy any mapped treatments
            if (!empty($appXML->person->personCondition->personTreatment)) {
                for ($i = 0; $i < count($appXML->person->personCondition->personTreatment); $i++) {
                    self::appendXMLNode($finalUpdateRecordXML->person->personCondition, $appXML->person->personCondition->personTreatment[$i]);
                }
            }
        }
        
        if ($skipLabAssignment) {
            // skipping lab assignment for this message:  remove lab and attachment from XML
            unset($finalUpdateRecordXML->person->personCondition->lab);
            unset($appXML->person->personCondition->attachment);  // unset attachment from $appXML, since attachment isn't copied to $finalUpdateRecordXML until later in this method if present
        } else {
            if (!$isNewLab && !empty($finalUpdateRecordXML->person->personCondition->lab->labTest->labTestResult->labResistTest)) {
                // if updating lab to add a susceptibility, strip attachment
                unset($appXML->person->personCondition->attachment);
            } else {
                // if adding an attachment for lab results, set the filename and category
                $appXML->person->personCondition->attachment->filename = $emsaMessage->getAttachmentFilename();
                $appXML->person->personCondition->attachment->category = 'lab';
            }
        }
        
        // copy over attachment node if lab test still exists...
        if (!empty($finalUpdateRecordXML->person->personCondition->lab->labTest) && !empty($appXML->person->personCondition->attachment)) {
            self::appendXMLNode($finalUpdateRecordXML->person->personCondition, $appXML->person->personCondition->attachment);
        }
    }

    /**
     * Compared demographic data of a patient between an incoming EMSA message and an existing NEDSS event for differences.
     * 
     * @param EmsaMessage $emsaMessage EMSA message being assigned
     * @param Person $existingPerson Person object representing the person in the target system who the target event belongs to.
     * @param SimpleXMLElement $finalUpdateRecordXML Application XML being prepared to update the record.
     * 
     * @return string
     */
    public static function demographicDataDiff(EmsaMessage $emsaMessage, Person $existingPerson, SimpleXMLElement $finalUpdateRecordXML)
    {
        $appClient = $emsaMessage->getAppClient();
        $newAppXML = $emsaMessage->getApplicationXML();
        
        $diffOutput = '';

        $updates = array();

        // compare patient/demographic info first...
        // first name
        if (!empty($emsaMessage->getPerson()->getFirstName())) {
            // set in incoming message
            if (empty($existingPerson->getFirstName())) {
                // not set in application; update
                $finalUpdateRecordXML->person->firstName = (string) $emsaMessage->getPerson()->getFirstName();
            } elseif (!CoreUtils::mbStrCaseCmp((string) $emsaMessage->getPerson()->getFirstName(), $existingPerson->getFirstName())) {
                // different value, push new value to notes
                $updates[] = array(
                    'label' => 'First Name',
                    'value' => trim($emsaMessage->getPerson()->getFirstName())
                );
            }
        }

        // last name
        if (!empty($emsaMessage->getPerson()->getLastName())) {
            // set in incoming message
            if (empty($existingPerson->getLastName())) {
                // not set in application; update
                $finalUpdateRecordXML->person->lastName = (string) $emsaMessage->getPerson()->getLastName();
            } elseif (!CoreUtils::mbStrCaseCmp((string) $emsaMessage->getPerson()->getLastName(), $existingPerson->getLastName())) {
                // different value, push new value to notes
                $updates[] = array(
                    'label' => 'Last Name',
                    'value' => trim($emsaMessage->getPerson()->getLastName())
                );
            }
        }

        // middle name
        if (!empty($emsaMessage->getPerson()->getMiddleName())) {
            // set in incoming message
            if (empty($existingPerson->getMiddleName())) {
                // not set in application; update
                $finalUpdateRecordXML->person->middleName = (string) $emsaMessage->getPerson()->getMiddleName();
            } elseif (!CoreUtils::mbStrCaseCmp((string) $emsaMessage->getPerson()->getMiddleName(), $existingPerson->getMiddleName())) {
                // different value, push new value to notes
                $updates[] = array(
                    'label' => 'Middle Name',
                    'value' => trim($emsaMessage->getPerson()->getMiddleName())
                );
            }
        }

        // gender
        if (!empty($emsaMessage->getPerson()->getGender())) {
            // has a value
            // $newAppXML->person->birthGender->code
            if (empty($existingPerson->getGender()) || ($existingPerson->getGender() == "Unknown")) {
                // not set in application or set as 'Unknown'; update
                $finalUpdateRecordXML->person->birthGender->code = trim($newAppXML->person->birthGender->code);
            } elseif (!CoreUtils::mbStrCaseCmp($emsaMessage->getPerson()->getGender(), $existingPerson->getGender())) {
                // different value, push new value to notes
                $updates[] = array(
                    'label' => 'Birth Sex',
                    'value' => (string) $emsaMessage->getPerson()->getGender()
                );
            }
        }

        // compare race collections
        $newRaces = $emsaMessage->getPerson()->getRaces();
        $existingRaces = $existingPerson->getRaces();
        
        // should actually only be one incoming race mapped, but easier to process as a collection for forward compatibility in case we get fancy
        if (!empty($newRaces)) {
            if (empty($existingRaces)) {
                // existing person has no races defined; set all mapped
                foreach ($newRaces as $currentRace) {
                    $finalUpdateRecordXML->person->addChild('race')->addChild('code', (string) CodedDataUtils::getCodedValueFromDescription($appClient, 'race', $currentRace));
                }
            } elseif ((count($existingRaces) === 1) && $existingPerson->hasRace('Unknown')) {
                if (!$emsaMessage->getPerson()->hasRace('Unknown')) {
                    // existing person only has 'Unknown' defined and EMSA has something else
                    foreach ($newRaces as $currentRace) {
                        $finalUpdateRecordXML->person->addChild('race')->addChild('code', (string) CodedDataUtils::getCodedValueFromDescription($appClient, 'race', $currentRace));
                    }
                }
            } elseif (count($existingRaces) >= 1) {
                // existing person has one or more races that are not "Unknown"; compare
                foreach ($newRaces as $currentRace) {
                    if (($currentRace != 'Unknown') && !$existingPerson->hasRace($currentRace)) {
                        // EMSA's race is !Unknown and existing person doesn't have it yet
                        $updates[] = array(
                            'label' => 'Race',
                            'value' => $currentRace
                        );
                    }
                }
                
                // remove "Unknown" from existing person (rebuild list without "Unknown")
                if ($existingPerson->hasRace('Unknown')) {
                    foreach ($existingRaces as $existingRace) {
                        if ($existingRace != 'Unknown') {
                            $finalUpdateRecordXML->person->addChild('race')->addChild('code', (string) CodedDataUtils::getCodedValueFromDescription($appClient, 'race', $existingRace));
                        }
                    }
                }
            }
        }

        // ethnicity
        if (!empty($emsaMessage->getPerson()->getEthnicity())) {
            // has a value
            if (empty($existingPerson->getEthnicity()) || ($existingPerson->getEthnicity() == "Unknown")) {
                // not set in application or set as 'Unknown'; update
                $finalUpdateRecordXML->person->ethnicity->code = trim($newAppXML->person->ethnicity->code);
            } elseif (!CoreUtils::mbStrCaseCmp($emsaMessage->getPerson()->getEthnicity(), $existingPerson->getEthnicity())) {
                // different value, push new value to notes
                $updates[] = array(
                    'label' => 'Ethnicity',
                    'value' => (string) $emsaMessage->getPerson()->getEthnicity()
                );
            }
        }

        // language
        if (!empty($emsaMessage->getPerson()->getLanguage())) {
            // has a value
            if (empty($existingPerson->getLanguage()) || ($existingPerson->getLanguage() == "Unknown")) {
                // not set in application or set as 'Unknown'; update
                $finalUpdateRecordXML->person->language->code = trim($newAppXML->person->language->code);
            } elseif (!CoreUtils::mbStrCaseCmp($emsaMessage->getPerson()->getLanguage(), $existingPerson->getLanguage())) {
                // different value, push new value to notes
                $updates[] = array(
                    'label' => 'Primary Language',
                    'value' => (string) $emsaMessage->getPerson()->getLanguage()
                );
            }
        }

        // date of birth
        if (!empty($emsaMessage->getPerson()->getDateOfBirth())) {
            try {
                $newDOBStr = $emsaMessage->getPerson()->getDateOfBirth(true, "Y-m-d");
                $oldDOBStr = $existingPerson->getDateOfBirth(true, "Y-m-d");

                if (empty($existingPerson->getDateOfBirth())) {
                    // not set in application; update
                    $finalUpdateRecordXML->person->birthDate = $emsaMessage->getPerson()->getDateOfBirth(true);
                } elseif (!CoreUtils::mbStrCaseCmp($newDOBStr, $oldDOBStr)) {
                    // different value, push new value to notes
                    $updates[] = array(
                        'label' => 'Date of Birth',
                        'value' => $emsaMessage->getPerson()->getDateOfBirth(true, "F j, Y")
                    );
                }
            } catch (Throwable $ex) {
                ExceptionUtils::logException($ex);
            }
        }

        // date of death
        if (!empty($emsaMessage->getPerson()->getDateOfDeath())) {
            try {
                $newDateOfDeathStr = $emsaMessage->getPerson()->getDateOfDeath(true, "Y-m-d");
                $oldDateOfDeathStr = $existingPerson->getDateOfDeath(true, "Y-m-d");

                if (empty($oldDateOfDeathStr) || !CoreUtils::mbStrCaseCmp($newDateOfDeathStr, $oldDateOfDeathStr)) {
                    // different value, push new value to notes
                    $updates[] = array(
                        'label' => 'Date of Death',
                        'value' => $emsaMessage->getPerson()->getDateOfDeath(true, "F j, Y")
                    );
                }
            } catch (Throwable $ex) {
                ExceptionUtils::logException($ex);
            }
        }


        // prior to comparing addresses, remove personCondition/addressAtDiagnosis if "Earliest Known" is the only field mapped (i.e. empty address)
        if (!empty($newAppXML->person->personCondition->addressAtDiagnosis) && ($newAppXML->person->personCondition->addressAtDiagnosis[0]->count() === 1) && isset($newAppXML->person->personCondition->addressAtDiagnosis->beginning)) {
            unset($newAppXML->person->personCondition->addressAtDiagnosis);
            unset($finalUpdateRecordXML->person->personCondition->addressAtDiagnosis);
            unset($finalUpdateRecordXML->person->personAddress);
        }
        
        if (count($updates) > 0) {
            // one or more fields have updated values, update the notes...
            $diffOutput .= "<ul>\n";
            foreach ($updates as $updateVal) {
                $diffOutput .= '<li><u>' . $updateVal['label'] . ':</u> ' . $updateVal['value'] . "</li>\n";
            }
            $diffOutput .= "</ul>\n";
        }

        return $diffOutput;
    }

    /**
     * Checks Patient, Address & Telephone information for an incoming lab for differences and moves them into 'Notes'.
     *
     * Used when updating an event in NEDSS ('updateCmr') so duplicates aren't added and existing values aren't overwritten.
     *
     * @param PDO              $dbConn               PDO connection to EMSA database
     * @param EmsaMessage      $emsaMessage          EMSA message being assigned
     * @param SimpleXMLElement $finalUpdateRecordXML Application XML being prepared to update the record.
     * @param Person           $targetPerson         Person the target record belongs to.
     * @param int              $assignAction         Action taken during assignment (one of <b>SystemMessageActions</b> constants)
     * @param bool             $skipLabAssignment    [Optional; default FALSE]<br>If TRUE, indicates lab data is not being assigned as part of this update.
     * @param bool             $isInterstateTx       [Optional; default FALSE]<br>If TRUE, indicates notes being generated as result of an EMSA message that has been transmitted to another State via Interstate Trasmission
     *
     * @return bool Returns <b>TRUE</b> when done.
     *
     * @static
     */
    public static function updatedContactInfoToNotes(PDO $dbConn, EmsaMessage $emsaMessage, SimpleXMLElement $finalUpdateRecordXML, Person $targetPerson, $assignAction, $skipLabAssignment = false, $isInterstateTx = false)
    {
        $systemMessageId = $emsaMessage->getSystemMessageId();

        $notes = '';

        // compare demographic data
        $demographicDiffNotes = self::demographicDataDiff($emsaMessage, $targetPerson, $finalUpdateRecordXML);

        if (!empty(trim($demographicDiffNotes))) {
            $notes .= "<strong style='color: orangered;'>Potential updated information from ELR.  Affected fields and their new values are:</strong><br>";
            $notes .= $demographicDiffNotes;
            $notes .= "<br>";
        }

        if ($isInterstateTx) {
            $notes .= "<strong style='color: orangered;'>Out of State Message Information:</strong><br>";
            $notes .= "<span>The ELR message that updated this event indicates that the patient lives out-of-state.  This message has already been shared electronically with State the patient resides in.  Further follow-up with external jurisdiction may still be necessary.</span>";
            $notes .= "<br><br>";
        }

        if (!$skipLabAssignment && !(($assignAction == SystemMessageActions::MESSAGE_ASSIGNED_UPDATED_CMR_UPDATED_LAB) && !empty($finalUpdateRecordXML->person->personCondition->lab->labTest->labTestResult->labResistTest))) {
            // if updating a lab to add AST results or skipping lab results for this message, omit HL7 notes
            $notes .= "<strong>Original HL7 Message:</strong><br><span style='font-family: monospace; color: cadetblue;'>" . nl2br(EmsaUtils::getRawOriginalMessageFormatted($dbConn, $systemMessageId, $emsaMessage->getMessageType()) . "</span>");
        }

        if (!empty($notes)) {
            $finalUpdateRecordXML->person->personCondition->addChild('note');
            $finalUpdateRecordXML->person->personCondition->note->addChild('note');
            $finalUpdateRecordXML->person->personCondition->note->note = (string)$notes;
            $finalUpdateRecordXML->person->personCondition->note->addChild('user')->addChild('uid', EPITRAX_AUTH_ELR_UID);
            $finalUpdateRecordXML->person->personCondition->note->addChild('noteType', 'system');
        }

        return true;
    }

    /**
     * Moves relevant data from a Clinical Document to NEDSS Notes.
     *
     * When updating an event in NEDSS, checks patient demographic information for an incoming message
     * for differences and moves them into 'Notes', as well as adding relevant clinical diagnoses,
     * treatments, and limited lab results.
     *
     * @param PDO              $dbConn               PDO connection to EMSA database
     * @param EmsaMessage      $emsaMessage          EMSA message being assigned
     * @param SimpleXMLElement $finalUpdateRecordXML Application XML being prepared to update the record.
     * @param Person           $targetPerson         Person the target record belongs to.<br>[Optional; Required if updating a record or creating a new record under an existing person.]
     *
     * @return bool Returns <b>TRUE</b> when done.
     *
     * @static
     */
    public static function clinicalDocumentToNotes(PDO $dbConn, EmsaMessage $emsaMessage, SimpleXMLElement $finalUpdateRecordXML, Person $targetPerson = null)
    {
        $masterXML = $emsaMessage->getMasterXML();
        $visitDate = (string) $emsaMessage->getEncounterDate(true, "m/d/Y g:ia");
        $reportingAgency = (string) $masterXML->reporting->agency;
        $noteCount = 0;
        
        if (!empty($masterXML->poison->referral->patient->id)) {
            $poisonReferral = $masterXML->poison->referral;
            $poisonSubstance = $masterXML->poison->substance_info;
            $poisonSubstanceExposure = $masterXML->poison->substance_exposure;
        } else {
            $poisonReferral = null;
            $poisonSubstance = null;
            $poisonSubstanceExposure = null;
        }
        
        if (!empty($poisonReferral)) {
            // Poison Control ED Referral
            $noteCount++;
            $notesFromClinical = "<strong style='color: orangered;'>Poison Control Updates via EMSA</strong><br>";
            $notesFromClinical .= "(Reported by $reportingAgency)<br><br>";
            
            $notesFromClinical .= "<strong>Poison Control ED Referral:</strong><br><ul>";
            $notesFromClinical .= "<li><u>Referral Date:</u> $visitDate</li>";
            $notesFromClinical .= "<li><u>Chief Complaint:</u> " . (string) $poisonReferral->chief_complaint . "</li>";
            $notesFromClinical .= "<li><u>Informant (Relationship):</u> " . DisplayUtils::formatNameLastFirstMiddle((string) $poisonReferral->informant->last_name, (string) $poisonReferral->informant->first_name) . " ( " . (string) $poisonReferral->informant->relationship . ")</li>";
            $notesFromClinical .= "<li><u>Referred Healthcare Facility:</u> " . (string) $poisonReferral->referred_facility . "</li>";
            $notesFromClinical .= "<li><u>Time of Exposure:</u> " . (string) $poisonReferral->time_of_exposure . "</li>";
            $notesFromClinical .= "<li><u>Duration of Exposure:</u> " . (string) $poisonReferral->duration_of_exposure . "</li>";
            $notesFromClinical .= "<li><u>Exposure Route(s):</u> " . (string) $poisonReferral->exposure_routes . "</li>";
            $notesFromClinical .= "<li><u>Subjective Narrative:</u> " . (string) $poisonReferral->subjective_narrative . "</li>";
            
            $notesFromClinical .= "</ul><br>";
            
            $notesFromClinical .= "<strong>Patient Information:</strong><br><ul>";
            $notesFromClinical .= "<li><u>Patient ID:</u> " . (string) $poisonReferral->patient->id . "</li>";
            $notesFromClinical .= "<li><u>Patient Status:</u> " . (string) $poisonReferral->patient->status . "</li>";
            $notesFromClinical .= "<li><u>Previous Medical History:</u> " . (string) $poisonReferral->patient->history . "</li>";
            $notesFromClinical .= "<li><u>Medication:</u> " . (string) $poisonReferral->medication_narrative . "</li>";
            
            $notesFromClinical .= "</ul><br>";
            
            $notesFromClinical .= "<strong>Assessment Information:</strong><br><ul>";
            $notesFromClinical .= "<li><u>Symptoms:</u> " . (string) $poisonReferral->symptoms . "</li>";
            $notesFromClinical .= "<li><u>Clinical Effects:</u> " . (string) $poisonReferral->clinical_effects . "</li>";
            $notesFromClinical .= "<li><u>Assessment Notes:</u> " . (string) $poisonReferral->assessment_notes . "</li>";
            $notesFromClinical .= "<li><u>Recommended Testing &amp; Monitoring:</u> " . (string) $poisonReferral->recommended_testing . "</li>";
            $notesFromClinical .= "<li><u>Recommended Observation Time:</u> " . (string) $poisonReferral->recommended_observation . "</li>";
            $notesFromClinical .= "<li><u>Treatments &amp; Intervention:</u> " . (string) $poisonReferral->treatments_interventions . "</li>";
            
            $notesFromClinical .= "</ul><br>";
            
            $notesFromClinical .= "<strong>Substance Exposure:</strong><br><ul>";
            $notesFromClinical .= "<li><u>Substance Name:</u> " . (string) $poisonSubstanceExposure->description . "</li>";
            $notesFromClinical .= "<li><u>Quantity:</u> " . (string) $poisonSubstanceExposure->quantity . "</li>";
            $notesFromClinical .= "<li><u>Concentration:</u> " . (string) $poisonSubstanceExposure->concentration . "</li>";
            $notesFromClinical .= "<li><u>Certainty:</u> " . (string) $poisonSubstanceExposure->certainty . "</li>";
            $notesFromClinical .= "<li><u>Dose:</u> " . (string) $poisonSubstanceExposure->dose . "</li>";
            $notesFromClinical .= "<li><u>Formulation:</u> " . (string) $poisonSubstanceExposure->formulation . "</li>";
            
            $notesFromClinical .= "</ul><br>";
            
            $notesFromClinical .= "<strong>General Substance Information:</strong><br><ul>";
            $notesFromClinical .= "<li><u>Substance Name:</u> " . (string) $poisonSubstance->description . "</li>";
            $notesFromClinical .= "<li><u>Toxic Dose:</u> " . (string) $poisonSubstance->toxic_dose . "</li>";
            $notesFromClinical .= "<li><u>Common Effects:</u> " . (string) $poisonSubstance->common_effects . "</li>";
            $notesFromClinical .= "<li><u>Time to Peak Concentration:</u> " . (string) $poisonSubstance->peak_concentration . "</li>";
            
            $notesFromClinical .= "</ul>";
        } else {
            // Other eCR
            $notesFromClinical = "<strong style='color: orangered;'>Clinical Updates via EMSA</strong><br>";
            $notesFromClinical .= "(Reported by $reportingAgency)<br><br>";
            
            // condition codes
            if (isset($masterXML->disease->code) && !EmsaUtils::emptyTrim((string) $masterXML->disease->code)) {
                $noteCount++;
                $codeDescription = VocabUtils::getICDCodeDescription($dbConn, $emsaMessage->labId, (string) $masterXML->disease->codingSystem, (string) $masterXML->disease->code);
                $notesFromClinical .= "<br><strong>Clinically-Diagnosed Condition:</strong><br><ul>";
                $notesFromClinical .= "<li>" . (string) $masterXML->disease->name . " [From " . (string) $masterXML->disease->codingSystem . " code " . (string) $masterXML->disease->code . " ($codeDescription)]</li></ul>";
            }

            // pregnancy diagnoses
            if (isset($masterXML->pregnancy) && (count($masterXML->pregnancy) > 0)) {
                $noteCount++;
                $notesFromClinical .= "<br><strong>Pregnancy-Related Information:</strong><br><ul>";

                foreach ($masterXML->pregnancy as $pregnancy) {
                    if (isset($pregnancy->pregnancy_diagnosis) && !empty($pregnancy->pregnancy_diagnosis)) {
                        $notesFromClinical .= "<li>" . (string)$pregnancy->pregnancy_diagnosis . "</li>";
                    }
                }

                $notesFromClinical .= "</ul>";
            }

            // treatments
            /*
             * since treatments are being properly added into the EpiTrax model now, 
             * and we're filtering valid treatments there now, going to stop adding 
             * treatments to notes so we don't inadvertently note an unacceptable treatment.
             * 
            if (isset($masterXML->treatments) && (count($masterXML->treatments) > 0)) {
                $notesFromClinical .= "<br><strong>Treatment Information:</strong><br><ul>";
                foreach ($masterXML->treatments as $treatmentEntry) {
                    if (\EmsaUtils::emptyTrim($treatmentEntry->date_of_treatment)) {
                        $treatmentStartDate = '';
                    } else {
                        try {
                            $treatmentStartDate = DateTimeUtils::createMixed($treatmentEntry->date_of_treatment)->format("m/d/Y");
                        } catch (Throwable $e) {
                            ExceptionUtils::logException($e);
                            $treatmentStartDate = '';
                        }
                    }

                    if (\EmsaUtils::emptyTrim($treatmentEntry->treatment_stopped)) {
                        $treatmentEndDate = '';
                    } else {
                        try {
                            $treatmentEndDate = DateTimeUtils::createMixed($treatmentEntry->treatment_stopped)->format("m/d/Y");
                        } catch (Throwable $e) {
                            ExceptionUtils::logException($e);
                            $treatmentEndDate = '';
                        }
                    }

                    $notesFromClinical .= "<li>($treatmentStartDate - $treatmentEndDate) " . (string) $treatmentEntry->name . " [" . (string) $treatmentEntry->code_system . "# " . (string) $treatmentEntry->code . "], " . (string) $treatmentEntry->dose_quantity . "</li>";
                }

                $notesFromClinical .= "</ul>";
            }
             * 
             */

            // lab results
            if (!$emsaMessage->isSenderAssigningECRLabs) {
                if (isset($masterXML->labs->loinc_code) && !EmsaUtils::emptyTrim($masterXML->labs->loinc_code)) {
                    $noteCount++;
                    $notesFromClinical .= "<br><strong>Lab Results:</strong><br><ul>";

                    if (EmsaUtils::emptyTrim($masterXML->labs->lab_test_date)) {
                        $testDate = '--';
                    } else {
                        try {
                            $testDate = DateTimeUtils::createMixed($masterXML->labs->lab_test_date)->format("m/d/Y g:ia");
                        } catch (Throwable $e) {
                            ExceptionUtils::logException($e);
                            $testDate = '--';
                        }
                    }

                    $testName = (string)$masterXML->labs->local_test_name;
                    $testCode = (string)$masterXML->labs->loinc_code;
                    $testStatus = (string)$masterXML->labs->test_status;
                    $testResult = (string)$masterXML->labs->test_result;
                    $resultValue = (string)$masterXML->labs->local_result_value;
                    $comments = (string)$masterXML->labs->comment;
                    $abnormalFlag = EmsaUtils::decodeAbnormalFlag($dbConn, trim($masterXML->labs->abnormal_flag));

                    $notesFromClinical .= "<li><u>Test Date:</u> $testDate</li>";
                    $notesFromClinical .= "<li><u>Test Description:</u> $testName (LOINC code $testCode)</li>";
                    $notesFromClinical .= "<li><u>Test Result (Value):</u> $testResult ($resultValue)</li>";

                    if (!EmsaUtils::emptyTrim($abnormalFlag)) {
                        $notesFromClinical .= "<li><u>Abnormal Flag:</u> $abnormalFlag</li>";
                    }

                    $notesFromClinical .= "<li><u>Test Status:</u> $testStatus</li>";

                    if (!EmsaUtils::emptyTrim($comments)) {
                        $notesFromClinical .= "<li><u>Lab Comments:</u><br>$comments</li>";
                    }

                    $notesFromClinical .= "</ul>";
                }
            }
        }

        // compare demographic data, if updating an existing person (adding new event to person or updating existing event)
        $demographicDiffNotes = null;
        if (!is_null($targetPerson)) {
            $demographicDiffNotes = self::demographicDataDiff($emsaMessage, $targetPerson, $finalUpdateRecordXML);
        }

        if (!empty(trim($demographicDiffNotes))) {
            $noteCount++;
            $notesFromClinical .= "<strong style='color: orangered;'>Updated Demographic Data:</strong><br><em style='color: dimgray;'>(If listed below, electronically-reported value differs from previously-known value in NEDSS)</em><br>";
            $notesFromClinical .= $demographicDiffNotes;
        }

        // if there are actually any notes generated by this Clinical Document, add them to the final update XML
        if ($noteCount > 0) {
            $finalUpdateRecordXML->person->personCondition->addChild('note');
            $finalUpdateRecordXML->person->personCondition->note->addChild('note');
            $finalUpdateRecordXML->person->personCondition->note->note = (string)$notesFromClinical;
            $finalUpdateRecordXML->person->personCondition->note->addChild('user')->addChild('uid', EPITRAX_AUTH_ELR_UID);
            $finalUpdateRecordXML->person->personCondition->note->addChild('noteType', 'system');
        }

        return true;
    }

    /**
     * Miscellaneous cleanup tasks to prepare EMSA message XML for addCmr
     *
     * @param PDO              $dbConn            PDO connection to EMSA database
     * @param EmsaMessage      $emsaMessage       EMSA message being assigned
     * @param SimpleXMLElement $finalAddRecordXML Application XML being prepared to create the record.
     * @param Person           $targetPerson      Person the new record is being created for.<br>[Optional if creating a new Person to add the record under.]
     * @param bool             $isInterstateTx    [Optional; default FALSE]<br>If TRUE, indicates notes being generated as result of an EMSA message that has been transmitted to another State via Interstate Trasmission
     *
     * @static
     */
    public static function addCmrCleanupTasks(PDO $dbConn, EmsaMessage $emsaMessage, SimpleXMLElement $finalAddRecordXML, Person $targetPerson = null, ?bool $isInterstateTx = false)
    {
        self::addRecordHealthcareFacilityCleanup($emsaMessage, $finalAddRecordXML);

        $appXML = $emsaMessage->getApplicationXML();
        $masterXML = $emsaMessage->getMasterXML();

        self::geocodeAddressXML($emsaMessage->getAppClient(), $finalAddRecordXML->person->personCondition->addressAtDiagnosis);  // geocode patient addresses

        // remove personCondition/addressAtDiagnosis if "Earliest Known" is the only field mapped (i.e. empty address)
        if (!empty($finalAddRecordXML->person->personCondition->addressAtDiagnosis) && ($finalAddRecordXML->person->personCondition->addressAtDiagnosis[0]->count() === 1) && isset($finalAddRecordXML->person->personCondition->addressAtDiagnosis->beginning)) {
            unset($finalAddRecordXML->person->personCondition->addressAtDiagnosis);
        }

        if ($emsaMessage->getMessageType() === MessageType::CLINICAL_DOCUMENT) {
            self::clinicalDocumentToNotes($dbConn, $emsaMessage, $finalAddRecordXML, $targetPerson);
            // remove lab data, if present (unless sender is flagged to assign lab data from Clinical Documents)
            if (!$emsaMessage->isSenderAssigningECRLabs) {
                unset($finalAddRecordXML->person->personCondition->lab);
            }

            // copy encounter date as visit date
            if (!empty($emsaMessage->getEncounterDate()) && !empty($finalAddRecordXML->person->personCondition->personFacility)) {
                $finalAddRecordXML->person->personCondition->personFacility->admissionDate = (string) $emsaMessage->getEncounterDate(true, DateTime::RFC3339);
            }
        } else {
            self::copyManualELRResultValue($emsaMessage, $finalAddRecordXML);  // check for Manual ELR Entry result value
            self::addRecordHL7ToNotes($dbConn, $emsaMessage, $finalAddRecordXML, $targetPerson, $isInterstateTx);  // add original HL7 as a 'Note'
            self::addRecordSetLabComments($dbConn, $masterXML, $finalAddRecordXML, $emsaMessage);  // add decoded Abnormal Flag to comments, if present
        }

        // preserve personTelephone when creating new record for existing person; application responsible for determining whether to update/add/discard
        // (per Workfront Task Ref# 34400)
        if (!is_null($targetPerson) && !empty($appXML->person->personTelephone)) {
            self::appendXMLNode($finalAddRecordXML->person, $appXML->person->personTelephone);
        }

        // preserve personEmail when creating new record for existing person; application responsible for determining whether to update/add/discard
        // (per Workfront Task Ref# 34400)
        if (!is_null($targetPerson) && !empty($appXML->person->personEmail)) {
            self::appendXMLNode($finalAddRecordXML->person, $appXML->person->personEmail);
        }

        self::cleanupPregnancyStatus($emsaMessage, $finalAddRecordXML);  // handle pregnancy status

        // preserve PFGE pattern, if PFGE test
        if ($emsaMessage->masterTestType == 'PFGE') {
            self::pfgePatternToResultValue($emsaMessage, $finalAddRecordXML);
        }

        // close if surveillance event
        if (($emsaMessage->isSurveillance === CaseManagementRulesEngine::CMR_YES) && $emsaMessage->closeSurveillance) {
            self::setWorkflow($finalAddRecordXML, 'closed');
        }

        // add 'SURV' to otherData1 field for surveillance event search/filtering
        if ($emsaMessage->isSurveillance === CaseManagementRulesEngine::CMR_YES) {
            if (isset($finalAddRecordXML->person->personCondition->otherData1)) {
                $finalAddRecordXML->person->personCondition->otherData1 = 'SURV';
            } else {
                $finalAddRecordXML->person->personCondition->addChild('otherData1', 'SURV');
            }
        }

        // check for override to default jurisdiction, based on condition
        if ($emsaMessage->districtOverride > 0) {
            $finalAddRecordXML->person->personCondition->agency->id = AppClientUtils::getAppJurisdictionIdFromSystem($dbConn, $emsaMessage->getApplicationId(), $emsaMessage->districtOverride);
        }

        // if OOS jurisdiction, set State Case Status to OOS as well...
        $finalJurisdiction = strtolower(CodedDataUtils::getCodeDescriptionFromId($emsaMessage->getAppClient(), 'agency', (int) $finalAddRecordXML->person->personCondition->agency->id));
        if ($finalJurisdiction == 'out of state') {
            $finalAddRecordXML->person->personCondition->stateCaseStatus->code = CodedDataUtils::getCodedValueFromDescription($emsaMessage->getAppClient(), 'case', 'Out of State');
        }

        // set the data source for any mapped treatments...
        self::setTreatmentDataSource($finalAddRecordXML);

        // set record type to Morbidity Event in Application XML
        $finalAddRecordXML->person->personCondition->addChild('personConditionType')->addChild('code', 'Morbidity');

        if (empty($finalAddRecordXML->person->personCondition->lab->labTest) && !empty($finalAddRecordXML->person->personCondition->attachment)) {
            // remove PDF attachment if no lab results being added...
            unset($finalAddRecordXML->person->personCondition->attachment);
        } else {
            // if adding an attachment for lab results, set the filename and category
            $finalAddRecordXML->person->personCondition->attachment->filename = $emsaMessage->getAttachmentFilename();
            $finalAddRecordXML->person->personCondition->attachment->category = 'lab';
        }
    }

    /**
     * Adds geocoding to Application XML addresses and attempts to set County based on Zip Code if not present.
     *
     * @param AppClientInterface $appClient
     * @param SimpleXMLElement $addressXML XML node containing the address elements
     */
    public static function geocodeAddressXML(AppClientInterface $appClient, SimpleXMLElement $addressXML = null)
    {
        if (!defined('EPITRAX_GEO_SERVICE_ENDPOINT')) {
            return;
        }

        $latitude = null;
        $longitude = null;
        $streetAddr = null;
        $zipCode = null;
        $countyRaw = null;
        $translatedCountyCode = null;
        $oosCountyCode = CodedDataUtils::getCodedValueFromDescription($appClient, 'county', 'Out-of-state');

        if (!empty($addressXML) && !empty($addressXML->street) && !empty($addressXML->postalCode)) {
            $streetAddr = trim($addressXML->street);
            $zipCode = trim($addressXML->postalCode);

            try {
                $geoCurl = curl_init();
                $geoUrl = $appClient->getServiceURLRoundRobin() . EPITRAX_GEO_SERVICE_ENDPOINT . '?street_name=' . @urlencode($streetAddr) . '&zip=' . @urlencode($zipCode);
                curl_setopt($geoCurl, CURLOPT_HTTPHEADER, array(EPITRAX_AUTH_HEADER . ': ' . EPITRAX_AUTH_ELR_UID));
                curl_setopt($geoCurl, CURLOPT_CONNECTTIMEOUT, 5);
                curl_setopt($geoCurl, CURLOPT_TIMEOUT, 10);
                curl_setopt($geoCurl, CURLOPT_RETURNTRANSFER, true);
                // Warning: only use CURLOPT_VERBOSE for debugging non-production data; can cause addresses to be exposed in error_log
                // curl_setopt($geo_curl, CURLOPT_VERBOSE, true);
                curl_setopt($geoCurl, CURLOPT_URL, $geoUrl);

                $smartyReturn = curl_exec($geoCurl);
                curl_close($geoCurl);

                if ($smartyReturn) {
                    $smartyReturnArr = @json_decode($smartyReturn);
                    if (is_array($smartyReturnArr) && (count($smartyReturnArr) == 1)) {  // validate that exactly one match found
                        // make sure the lat & long are well-formed
                        $latitude = (isset($smartyReturnArr[0]->metadata->latitude) && preg_match('/^(\-?\d+(\.\d+)?)$/', trim($smartyReturnArr[0]->metadata->latitude))) ? trim($smartyReturnArr[0]->metadata->latitude) : null;
                        $longitude = (isset($smartyReturnArr[0]->metadata->longitude) && preg_match('/^(\-?\d+(\.\d+)?)$/', trim($smartyReturnArr[0]->metadata->longitude))) ? trim($smartyReturnArr[0]->metadata->longitude) : null;
                        $countyRaw = (isset($smartyReturnArr[0]->metadata->county_name)) ? trim($smartyReturnArr[0]->metadata->county_name) : null;
                        $stateRaw = (isset($smartyReturnArr[0]->components->state_abbreviation)) ? trim($smartyReturnArr[0]->components->state_abbreviation) : null;
                    }
                }

                if (empty($addressXML->county->code) && !empty($countyRaw)) {
                    // if no county is set by mapping, and we got a county back from Smarty, set it based on the county returned from Smarty...
                    $translatedCountyCode = CodedDataUtils::getCodedValueFromDescription($appClient, 'county', $countyRaw);
                    // if county didn't find a match, set to 'Out-of-state' for non-UT addresses, otherwise leave blank to prevent internal server errors with county ID of 0 (zero)
                    if (!empty($translatedCountyCode)) {
                        $addressXML->county->code = $translatedCountyCode;
                    } else {
                        if (isset($stateRaw) && strtolower($stateRaw) != 'ut' && !empty($oosCountyCode)) {
                            $addressXML->county->code = $oosCountyCode;
                        }
                    }
                }

                if (!empty($latitude) && !empty($longitude)) {
                    $addressXML->addChild('latitude', $latitude);
                    $addressXML->addChild('longitude', $longitude);
                }
            } catch (AppClientNoValidHosts $e) {
                ExceptionUtils::logException($e);

            }
        }
    }

    /**
     * Checks to see if incoming lab result indicates "Influenza activity" and patient hospitalization, 
     * and sets condition to "Influenza-associated hospitalization" if so.
     * 
     * <i>Updated 2016-03-10 per request from R. Boulton:  
     * Instead of setting 'Influenza-associated hospitalization' for 'Yes' and 'Influenza activity' 
     * for all other statuses (incl. blank/unk), now will set 'Influenza activity' for 'No' and 
     * 'Influenza-associated hospitalization' for all others (incl. blank/unk).</i>
     * 
     * @deprecated Now handled via database trigger in EpiTrax.  Do not use.
     * 
     * @param EmsaMessage $emsaMessage The current EmsaMessage being assigned
     */
    public static function setInfluenzaHospitalization(EmsaMessage $emsaMessage)
    {
        $fluActivityConditionName = 'Influenza activity';
        $fluHospitalizationConditionName = 'Influenza-associated hospitalization';
        $outpatientCode = (string) CodedDataUtils::getCodedValueFromDescription($emsaMessage->getAppClient(), 'facility_visit_type', 'Outpatient');
        $fluActivityDiseaseCode = (string) CodedDataUtils::getCodedValueFromDescription($emsaMessage->getAppClient(), 'diseases', $fluActivityConditionName);
        $fluHospitalizationDiseaseCode = (string) CodedDataUtils::getCodedValueFromDescription($emsaMessage->getAppClient(), 'diseases', $fluHospitalizationConditionName);

        $pendingCode = (int) CodedDataUtils::getCodedValueFromDescription($emsaMessage->getAppClient(), 'case', 'Pending');
        $confirmedCode = (int) CodedDataUtils::getCodedValueFromDescription($emsaMessage->getAppClient(), 'case', 'Confirmed');

        $appXML = $emsaMessage->getApplicationXML();
        $masterXML = $emsaMessage->getMasterXML();

        $hospitalizedByFacility = false;
        if (!empty($appXML->person->personCondition->personFacility)) {
            foreach ($appXML->person->personCondition->personFacility as $personFacility) {
                if (!empty($personFacility->facilityVisitType->code) && ((string) $personFacility->facilityVisitType->code != $outpatientCode)) {
                    $hospitalizedByFacility = $hospitalizedByFacility || true;
                }
            }
        }

        $currentDiseaseCode = (string) $appXML->person->personCondition->condition->code;

        if (($currentDiseaseCode === $fluActivityDiseaseCode) && !$hospitalizedByFacility) {
            // patient w/ Influenza activity & hospitalized blank or 'Outpatient', leave as Influenza activity and 
            // set to Confirmed surveillance event
            $emsaMessage->isSurveillance = CaseManagementRulesEngine::CMR_YES;
            $masterXML->labs->state_case_status = $confirmedCode;
            $appXML->person->personCondition->stateCaseStatus->code = $confirmedCode;

            // save updated master & Application XML back to database so changes are reflected in EMSA
            // as well as PDF attachment in Application
            $emsaMessage->saveXMLChangesToDb();
        } elseif (($currentDiseaseCode === $fluActivityDiseaseCode) && $hospitalizedByFacility) {
            // patient w/ Influenza activity & hospitalized is set & other than 'Outpatient', set to Influenza-associated hospitalization and
            // set to Pending investigation event
            unset($appXML->person->personCondition->condition);
            $appXML->person->personCondition->addChild('condition')->addChild('code', $fluHospitalizationDiseaseCode);
            $appXML->person->personCondition->personFacility->hospitalizedCondition->code = $fluHospitalizationDiseaseCode;
            $masterXML->disease->name = $fluHospitalizationConditionName;
            $masterXML->labs->state_case_status = $pendingCode;
            $appXML->person->personCondition->stateCaseStatus->code = $pendingCode;

            // save updated master & Application XML back to database so changes are reflected in EMSA
            // as well as PDF attachment in Application
            $emsaMessage->saveXMLChangesToDb();
        }
    }

    /**
     * Clean up healthcare facility/clinician data when adding a new record.
     * 
     * If patient visit type is not specified, remove any visit-related data (such as 'Admit Date').
     * If a visit is indicated, copy Ordering Facility/Clinician to Healthcare Facility/Clinician.
     * 
     * @param EmsaMessage $emsaMessage The current message being assigned
     * @param SimpleXMLElement $finalAddRecordXML Application XML being prepared to create the record.
     */
    public static function addRecordHealthcareFacilityCleanup(EmsaMessage $emsaMessage, SimpleXMLElement $finalAddRecordXML)
    {
        $appXML = $emsaMessage->getApplicationXML();
        $healthcareFacilityVisit = false;
        
        if (!empty($appXML->person->personCondition->personFacility)) {
            foreach ($appXML->person->personCondition->personFacility as $personFacility) {
                if (!empty($personFacility->facilityVisitType->code)) {
                    $healthcareFacilityVisit = $healthcareFacilityVisit || true;
                }
            }
        }

        if (!$healthcareFacilityVisit && ($emsaMessage->getMessageType() !== MessageType::CLINICAL_DOCUMENT)) {
            // visit does not exist AND is not an eCR message
            if (!empty($finalAddRecordXML->person->personCondition->personFacility)) {
                $emptyFacilities = array();
                
                foreach ($finalAddRecordXML->person->personCondition->personFacility as $personFacility) {
                    // if no visit type is specified at all, remove medicalRecordNumber (probably a lab-only facility)
                    if (empty($personFacility->facilityVisitType->code)) {
                        unset($personFacility->medicalRecordNumber);
                    }

                    // remove admit date
                    if (!empty($personFacility->admissionDate)) {
                        unset($personFacility->admissionDate);
                    }

                    // remove discharge date
                    if (!empty($personFacility->dischargeDate)) {
                        unset($personFacility->dischargeDate);
                    }

                    // remove hospitalized condition
                    if (!empty($personFacility->hospitalizedCondition)) {
                        unset($personFacility->hospitalizedCondition);
                    }
                    
                    if ($personFacility->count() < 1) {
                        $emptyFacilities[] = $personFacility;
                    }
                }
                
                // remove any personFacility nodes with no children
                if (!empty($emptyFacilities)) {
                    foreach ($emptyFacilities as $emptyFacility) {
                        unset($emptyFacility[0]);
                    }
                }
            }
        }
    }

    /**
     * If the lab's test type is "PFGE", copy the PFGE pattern to the NEDSS <i>Result Value</i> field.
     * 
     * @param EmsaMessage $emsaMessage The current EmsaMessage being assigned.
     * @param SimpleXMLElement $finalXML [Optional]<br>Used for special case during addCmr where final XML has already been extracted prior to this point
     */
    public static function pfgePatternToResultValue(EmsaMessage $emsaMessage, SimpleXMLElement $finalXML = null)
    {
        $appXML = $emsaMessage->getApplicationXML();

        if (empty($appXML->person->personCondition->lab->labTest->labTestResult->resultValue)) {
            $appXML->person->personCondition->lab->labTest->labTestResult->resultValue = trim($emsaMessage->localResultValue2);
            
            if (!empty($finalXML)) {
                $finalXML->person->personCondition->lab->labTest->labTestResult->resultValue = trim($emsaMessage->localResultValue2);
            }
        }
    }

    /**
     * In the case of processing messages from Manual ELR Entry, check for and copy Qn result value and units to Application XML.
     * 
     * @param EmsaMessage $emsaMessage The current EmsaMessage being assigned.
     * @param SimpleXMLElement $finalXML [Optional]<br>Used for special case during addCmr where final XML has already been extracted prior to this point
     */
    public static function copyManualELRResultValue(EmsaMessage $emsaMessage, SimpleXMLElement $finalXML = null)
    {
        $masterXML = $emsaMessage->getMasterXML();
        $appXML = $emsaMessage->getApplicationXML();

        if (isset($masterXML->labs->manual_entry) && !empty($masterXML->labs->manual_entry)) {
            // set result in Master XML
            $masterXML->labs->result_value = (string) $masterXML->labs->manual_entry;

            // set result in Application XML
            $appXML->person->personCondition->lab->labTest->labTestResult->resultValue = (string) $masterXML->labs->manual_entry;

            // copy units to Application XML (Master Process skips this if result !Qn
            $appXML->person->personCondition->lab->labTest->labTestResult->units = (string) $masterXML->labs->local_result_unit;
            
            if (!empty($finalXML)) {
                // if final XML is already created, also update values there, too
                $finalXML->person->personCondition->lab->labTest->labTestResult->resultValue = (string) $masterXML->labs->manual_entry;
                $finalXML->person->personCondition->lab->labTest->labTestResult->units = (string) $masterXML->labs->local_result_unit;
            }

            // copy units back to labs->units node in Master XML as well
            $masterXML->labs->units = (string) $masterXML->labs->local_result_unit;

            // save updates to Master/Application XML so they're reflected in EMSA UI
            $emsaMessage->saveXMLChangesToDb();
        }
    }
    
    /**
     * Add electronic pregnancy statuses to the person.
     * 
     * @param EmsaMessage $emsaMessage The current EmsaMessage being assigned.
     * @param SimpleXMLElement $finalUpdateRecordXML [Optional] If updating an existing record, the application XML being prepared to update the record.
     */
    public static function cleanupPregnancyStatus(EmsaMessage $emsaMessage, SimpleXMLElement $finalUpdateRecordXML = null)
    {
        $appXML = $emsaMessage->getApplicationXML();
        $masterXML = $emsaMessage->getMasterXML();

        // clear any mapped statuses, just to be sure...
        unset($appXML->person->personPregnantElr);

        // check for pregnancy by LOINC or mapping
        if ($emsaMessage->isPregnancy) {
            $mappedPregnancyElement = $appXML->person->addChild('personPregnantElr');
            $mappedPregnancyElement->addChild('reportedAt', $emsaMessage->getReferenceDate(true));
            $mappedPregnancyElement->addChild('pregnant', 'Yes');
        }

        // for any pregnancy diagnoses from Clinical Documents, also add them here
        if (isset($masterXML->pregnancy) && (count($masterXML->pregnancy) > 0)) {
            foreach ($masterXML->pregnancy as $pregnancy) {
                if (isset($pregnancy->pregnancy_diagnosis) && !empty($pregnancy->pregnancy_diagnosis)) {
                    $currentClinicalPregnancyElement = $appXML->person->addChild('personPregnantElr');
                    $currentClinicalPregnancyElement->addChild('reportedAt', $emsaMessage->getReferenceDate(true));
                    $currentClinicalPregnancyElement->addChild('pregnant', (string) $pregnancy->pregnancy_diagnosis);
                }
            }
        }
        
        // if $finalUpdateRecordXML exists (updating), copy pregnancy entries to $finalUpdateRecordXML
        if (!empty($finalUpdateRecordXML)) {
            unset($finalUpdateRecordXML->person->personPregnantElr);

            for ($i = 0; $i < count($appXML->person->personPregnantElr); $i++) {
                self::appendXMLNode($finalUpdateRecordXML->person, $appXML->person->personPregnantElr[$i]);
            }
        }
    }

    /**
     * Add <i>newChild</i> SimpleXMLElement object as a child of <i>targetElement</i> SimpleXMLElement object.
     * 
     * @param SimpleXMLElement $targetElement Element to add child to.
     * @param SimpleXMLElement $newChild Child element to add to the target.
     */
    public static function appendXMLNode(SimpleXMLElement $targetElement, SimpleXMLElement $newChild)
    {
        $targetDOM = dom_import_simplexml($targetElement);
        $newChildDOM = dom_import_simplexml($newChild);

        $targetDOM->appendChild(clone $targetDOM->ownerDocument->importNode($newChildDOM, true));
    }

}

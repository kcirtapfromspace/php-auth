<?php

namespace Udoh\Emsa\Utils;

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

use EmsaMessage;
use EmsaUtils;
use Exception;
use const FILTER_SANITIZE_STRING;
use PDO;
use Throwable;
use Udoh\Emsa\Client\AppClientInterface;
use Udoh\Emsa\Constants\AppRecordType;
use Udoh\Emsa\Exceptions\PeopleSearchMissingRequiredFields;
use Udoh\Emsa\Exceptions\PeopleSearchTooManyResults;
use Udoh\Emsa\Model\Address;
use Udoh\Emsa\Model\AppRecord;
use Udoh\Emsa\Model\Person;
use Udoh\Emsa\Model\PersonFacility;
use Udoh\Emsa\Model\Telecom;

/**
 * Utilities for working with Application-specific data.
 * 
 * @package Udoh\Emsa\Utils
 *
 * @author Josh Ridderhoff <jridderhoff@utah.gov>
 */
class AppClientUtils
{

    /**
     * Get People Search results based on the current message's patient information.
     *
     * @param EmsaMessage $emsaMessage          EMSA Message being processed.
     * @param PDO         $dbConn               PDO connection to the EMSA database.
     * @param bool        $limitByCondition     [Optional; default <b>FALSE</b>] Indicates whether to limit search by condition.
     * @param int         $matchScoreThreshhold [Optional; default 60]<br>Only return matches with this score or higher (0-100; 0 = no match, 100 = exact match).
     *
     * @throws Exception Any errors from AppClientInterface
     * @throws PeopleSearchMissingRequiredFields If missing required fields for AppClientInterface->searchPerson()
     * @throws PeopleSearchTooManyResults If People Search has too many matches
     * 
     * @return array Array of matched person results from the target Application.
     */
    public static function getPeopleSearchResults(EmsaMessage $emsaMessage, PDO $dbConn, ?bool $limitByCondition = false, ?int $matchScoreThreshhold = 60): array
    {
        $gatewayConditions = [];
        $eventsByPersonId = [];
        $searchConditions = [];
        $emsaPersonMRNs = [];
        $results = [];

        if ($limitByCondition) {
            $conditionName = $emsaMessage->masterCondition;
        } else {
            $conditionName = null;
        }

        if (empty($emsaMessage->getPerson()->getFirstName()) || empty($emsaMessage->getPerson()->getLastName())) {
            throw new PeopleSearchMissingRequiredFields('Missing required fields for People Search');
        }

        $matchScoreClean = (((filter_var(trim($matchScoreThreshhold), FILTER_VALIDATE_INT) !== false) && (intval($matchScoreThreshhold) >= 0) && (intval($matchScoreThreshhold) <= 100)) ? (int) filter_var($matchScoreThreshhold, FILTER_SANITIZE_NUMBER_INT) : 60);

        if (!is_null($conditionName)) {
            $conditionNameClean = ((strlen(trim($conditionName)) > 0) ? trim($conditionName) : null);
        }
        if (isset($conditionNameClean) && !EmsaUtils::emptyTrim($conditionNameClean)) {
            $searchConditions[] = $conditionNameClean;
            $gatewayConditions = VocabUtils::whitelistCrossrefNamesByInitialConditionName($dbConn, $conditionNameClean);
        }

        if (count($gatewayConditions) > 0) {
            foreach ($gatewayConditions as $gatewayCondition) {
                if (!EmsaUtils::emptyTrim($gatewayCondition)) {
                    $searchConditions[] = $gatewayCondition;
                }
            }
        }

        if (!empty($emsaMessage->getPerson()->getGender())) {
            $codedGender = CodedDataUtils::getCodedValueFromDescription($emsaMessage->getAppClient(), 'gender', $emsaMessage->getPerson()->getGender());
        } else {
            $codedGender = null;
        }

        /* @var $emsaPersonFacility PersonFacility */
        foreach ($emsaMessage->getPerson()->getPersonFacilityList() as $emsaPersonFacility) {
            if (!empty($emsaPersonFacility->getMedicalRecordNumber()) && preg_match('/\d/', $emsaPersonFacility->getMedicalRecordNumber())) {
                $emsaPersonMRNs[] = (string) $emsaPersonFacility->getMedicalRecordNumber();
            }
        }

        $foundPersonList = $emsaMessage->getAppClient()->searchPerson($emsaMessage->getPerson()->getFirstName(), $emsaMessage->getPerson()->getLastName(), $emsaMessage->getPerson()->getMiddleName(), $emsaMessage->getPerson()->getDateOfBirth(), $codedGender, $searchConditions, $matchScoreClean);

        /* @var $foundPerson Person */
        /* @var $foundEvent AppRecord */
        foreach ($foundPersonList as $foundPerson) {
            //$personHasEvents = false;
            $totalEventCount = 0;
            $contactEventCount = 0;
            $hasMinimumDemoFields = false;
            
            foreach ($foundPerson->getRecordList() as $foundEvent) {
                if (($foundEvent->getRecordType() === AppRecordType::MORBIDITY_EVENT) || ($foundEvent->getRecordType() === AppRecordType::CONTACT_EVENT)) {
                    $totalEventCount++;
                    
                    if ($foundEvent->getRecordType() === AppRecordType::CONTACT_EVENT) {
                        $contactEventCount++;
                    }
                    
                    //$personHasEvents = $personHasEvents || true;
                    $eventsByPersonId[$foundPerson->getPersonId()][] = array(
                        'event_type' => $foundEvent->getRecordType(),
                        'event_id' => $foundEvent->getEventId(),
                        'record_number' => $foundEvent->getRecordNumber(),
                        'event_date' => $foundEvent->getEventDate(true, "m/d/Y"),
                        'disease_name' => $foundEvent->getConditionName()
                    );
                }
            }

            $resultAddresses = [];
            $matchData = [];
            $matchData['full_name'] = DisplayUtils::formatNameLastFirstMiddle($foundPerson->getLastName(), $foundPerson->getFirstName(), $foundPerson->getMiddleName());
            $matchData['birth_date'] = $foundPerson->getDateOfBirth(true, "m/d/Y");
            $matchData['sex'] = $foundPerson->getGender();
            $matchData['match_score'] = $foundPerson->getMatchScore();
            $matchData['real_score'] = 0.0;
            
            if (!empty($foundPerson->getDateOfBirth())) {
                $hasMinimumDemoFields = $hasMinimumDemoFields || true;
            }

            /* @var $personAddress Address */
            foreach ($foundPerson->getAddressList() as $personAddress) {
                // for each address present, concat all the elements together & hash it, compare against any previously generated hashes for this person_id
                // only show distinct addresses (case-insensitive)
                $thisAddressCat = trim($personAddress->getStreet()) . trim($personAddress->getUnitNumber()) . trim($personAddress->getCity()) . trim($personAddress->getPostalCode());
                if (strlen($thisAddressCat) > 0) {
                    $thisAddressHash = md5(strtoupper($thisAddressCat));
                    if (!isset($resultAddresses[$foundPerson->getPersonId()][$thisAddressHash])) {
                        $hasMinimumDemoFields = $hasMinimumDemoFields || true;
                        $resultAddresses[$foundPerson->getPersonId()][$thisAddressHash] = true;
                        $matchData['addresses'][] = $personAddress;
                    }
                }

                // also keep list of addresses where street & zip both present for use later in auto-match algorithm comparison against EMSA addresses
                if (( strlen(trim($personAddress->getStreet())) * strlen(trim($personAddress->getPostalCode())) ) > 0) {
                    $matchData['automatch_addresses'][] = trim($personAddress->getStreet()) . '_' . trim($personAddress->getPostalCode());
                }
            }

            $telephoneItemsFound = [];
            $emailItemsFound = [];
            /* @var $personTelecom Telecom */
            foreach ($foundPerson->getTelecomList() as $personTelecom) {
                if ($personTelecom->getType() === Telecom::TYPE_PHONE) {
                    $thisTelephoneCat = $personTelecom->getAreaCode() . $personTelecom->getLocalNumber();
                    if (!in_array($thisTelephoneCat, $telephoneItemsFound) && (strlen($personTelecom->getLocalNumber()) > 0)) {
                        $hasMinimumDemoFields = $hasMinimumDemoFields || true;
                        $telephoneItemsFound[] = $thisTelephoneCat;
                        $matchData['telephones'][] = ((strlen($personTelecom->getLocalNumber()) > 0) ? $personTelecom->getAreaCode() . ((strlen($personTelecom->getAreaCode()) > 0) ? '-' : '') . substr($personTelecom->getLocalNumber(), 0, 3) . "-" . substr($personTelecom->getLocalNumber(), 3) : "");
                    }
                } elseif ($personTelecom->getType() === Telecom::TYPE_EMAIL) {
                    if (!in_array($personTelecom->getEmailAddress(), $emailItemsFound)) {
                        $emailItemsFound[] = $personTelecom->getEmailAddress();
                        $matchData['email_addresses'][] = $personTelecom->getEmailAddress();
                    }
                }
            }
            
            $personOfInterest = true;
            if (($totalEventCount === 0) || ($totalEventCount === $contactEventCount)) {
                // has no events or all events are contacts
                if (!$hasMinimumDemoFields) {
                    // person does not have AT LEAST one of telephones, addresses, or DOB
                    $personOfInterest = false;
                }
            }

            if ($personOfInterest) {
                // get all MRNs for found person
                /* @var $personFacility PersonFacility */
                $matchData['mrn'] = [];
                foreach ($foundPerson->getPersonFacilityList() as $personFacility) {
                    if (!empty($personFacility->getMedicalRecordNumber()) && preg_match('/\d/', $personFacility->getMedicalRecordNumber())) {
                        $matchData['mrn'][] = (string) $personFacility->getMedicalRecordNumber();
                    }
                }

                $results[$foundPerson->getPersonId()] = $matchData;
            }
        }

        $autoMatchResults = [];
        $fuzzyMatchResults = [];
        $noMatchResults = [];

        if (count($results) > 0) {
            foreach ($results as $resultPreproccessPersonId => $resultPreproccessMatchData) {
                $thisMatchScore = null;
                $thisMatchScore = (float) $resultPreproccessMatchData['match_score'];

                $results[$resultPreproccessPersonId]['real_score'] = $thisMatchScore;

                if ($thisMatchScore >= 95.0) {
                    $autoMatchResults[] = $resultPreproccessPersonId;
                } elseif ($thisMatchScore < 70.0) {
                    $noMatchResults[] = $resultPreproccessPersonId;
                } else {
                    // check if fuzzy match with MRN match, promote to auto-match
                    if (!empty($emsaPersonMRNs) && !empty($resultPreproccessMatchData['mrn'])) {
                        if (count(array_intersect($emsaPersonMRNs, $resultPreproccessMatchData['mrn'])) > 0) {
                            $autoMatchResults[] = $resultPreproccessPersonId;
                            continue;
                        }
                    }

                    if ($thisMatchScore >= 86.0) {
                        // phone match?
                        $phoneLength = 0;
                        $emsaPersonTelephones = [];

                        /* @var $emsaPersonTelephone Telecom */
                        foreach ($emsaMessage->getPerson()->getTelecomList() as $emsaPersonTelephone) {
                            if ($emsaPersonTelephone->getType() === Telecom::TYPE_PHONE) {
                                $thisPhoneLength = strlen(trim($emsaPersonTelephone->getLocalNumber()));
                                if ($thisPhoneLength > 0) {
                                    $phoneLength += $thisPhoneLength;
                                    $emsaPersonTelephones[] = ((strlen($emsaPersonTelephone->getLocalNumber()) > 0) ? $emsaPersonTelephone->getAreaCode() . ((strlen($emsaPersonTelephone->getAreaCode()) > 0) ? '-' : '') . substr($emsaPersonTelephone->getLocalNumber(), 0, 3) . "-" . substr($emsaPersonTelephone->getLocalNumber(), 3) : "");
                                }
                            }
                        }
                        $phoneMatch = !empty($resultPreproccessMatchData['telephones']) ? (count(array_intersect($emsaPersonTelephones, $resultPreproccessMatchData['telephones'])) > 0) : false;

                        if ($phoneMatch) {
                            $autoMatchResults[] = $resultPreproccessPersonId;
                            continue;
                        }

                        // email match?
                        $emailLength = 0;
                        $emsaPersonEmails = [];

                        /* @var $emsaPersonEmail Telecom */
                        foreach ($emsaMessage->getPerson()->getTelecomList() as $emsaPersonEmail) {
                            if ($emsaPersonEmail->getType() === Telecom::TYPE_EMAIL) {
                                $thisEmailLength = strlen(trim($emsaPersonEmail->getEmailAddress()));
                                if ($thisEmailLength > 0) {
                                    $emailLength += $thisEmailLength;
                                    $emsaPersonEmails[] = $emsaPersonEmail->getEmailAddress();
                                }
                            }
                        }
                        $emailMatch = !empty($resultPreproccessMatchData['email_addresses']) ? (count(array_intersect($emsaPersonEmails, $resultPreproccessMatchData['email_addresses'])) > 0) : false;

                        if ($emailMatch) {
                            $autoMatchResults[] = $resultPreproccessPersonId;
                            continue;
                        }

                        // address match?
                        $addressLength = 0;
                        $emsaPersonAddresses = [];

                        /* @var $emsaPersonAddress Address */
                        /* @var $originalEMSAPersonAddress Address */
                        foreach ($emsaMessage->getPerson()->getAddressList() as $originalEMSAPersonAddress) {
                            $thisAddressLength = ( strlen(trim($originalEMSAPersonAddress->getStreet())) * strlen(trim($originalEMSAPersonAddress->getPostalCode())) );
                            if ($thisAddressLength > 0) {
                                $addressLength += $thisAddressLength;

                                $emsaPersonAddress = clone $originalEMSAPersonAddress;
                                $emsaPersonAddress->geocodeFull($emsaMessage->getAppClient());
                                $emsaPersonAddresses[] = trim($emsaPersonAddress->getStreet()) . '_' . trim($emsaPersonAddress->getPostalCode());
                            }
                        }
                        $addressMatch = !empty($resultPreproccessMatchData['automatch_addresses']) ? (count(array_intersect($emsaPersonAddresses, $resultPreproccessMatchData['automatch_addresses'])) > 0) : false;

                        if ($addressMatch) {
                            $autoMatchResults[] = $resultPreproccessPersonId;
                            continue;
                        }
                    }

                    if (empty($resultPreproccessMatchData['birth_date'])) {
                        // person in EpiTrax has no DOB...
                        $noMatchResults[] = $resultPreproccessPersonId;
                        continue;
                    }

                    // no other criteria met; defer to Data Entry review ("fuzzy match")
                    $fuzzyMatchResults[] = $resultPreproccessPersonId;
                }
            }

            uasort($results, array('\Udoh\Emsa\Utils\SortUtils', 'peopleSearchSortByRealScore'));  // ensure list is sorted in the correct order, also fall back to name sort if equal scores
        }

        return [
            'results' => $results,
            'events_by_person_id' => $eventsByPersonId,
            'auto_match' => $autoMatchResults,
            'fuzzy_match' => $fuzzyMatchResults,
            'no_match' => $noMatchResults
        ];
    }

    /**
     * Indicates whether the specified EMSA message is for a patient whose last name has potentially changed.
     *
     * <i><b>Note:</b>  This function originally was restricted to female patients to detect possible name changes due
     * to marriage, but was updated to support potential name changes for any gender.</i>
     *
     * @param PDO         $dbConn
     * @param EmsaMessage $emsaMessage EMSA Message being processed.
     *
     * @return array Returns array with two nested arrays:  'personIds' contains list of person IDs found with potential name changes, and 'eventIds' contains arrays representing each event found in candidate people where each key = event ID and the corresponding value contains an array with the record_number and event_type.  If both nested arrays are empty, no potential name changes found
     */
    public static function hasLastNameChanged(PDO $dbConn, EmsaMessage $emsaMessage): array
    {
        $matchSet = [
            'personIds' => [],
            'eventIds'  => []
        ];

        $systemMessageId = $emsaMessage->getSystemMessageId();
        $masterDisease = (string) $emsaMessage->masterCondition;
        $person = $emsaMessage->getPerson();
        $appDiseaseName = $person->getRecordList()->getRecord(0)->getConditionName();

        if (EmsaUtils::emptyTrim($systemMessageId)) {
            return $matchSet;
        }

        $targetDiseases = [
            'HIV',
            'Hepatitis B',
            'Hepatitis C',
            'Tuberculosis',
            'Syphilis'
        ];

        $isTargetDisease = false;
        foreach ($targetDiseases as $checkDisease) {
            if (stripos($masterDisease, $checkDisease) !== false) {
                $isTargetDisease = $isTargetDisease || true;
            }
        }

        if (!$isTargetDisease) {
            return $matchSet;  // only care if HIV, Hep C, Hep B, or TB
        }

        if (empty($person->getFirstName()) || empty($person->getLastName()) || empty($person->getDateOfBirth())) {
            return $matchSet;
        }

        $crossrefConditions = [$appDiseaseName];

        foreach (VocabUtils::whitelistCrossrefNamesByInitialConditionName($dbConn, $masterDisease) as $whitelistCrossref) {
            $crossrefConditions[] = $whitelistCrossref;
        }

        try {
            $searchResults = $emsaMessage->getAppClient()->searchPerson($person->getFirstName(), null, null, $person->getDateOfBirth(), null, null, 49);

            /* @var $foundPerson Person */
            foreach ($searchResults as $foundPerson) {
                // check results found here...
                if (empty($foundPerson->getDateOfBirth()) || empty($foundPerson->getFirstName()) || empty($foundPerson->getLastName()) || ((float) $foundPerson->getMatchScore() < 49.0) || ((float) $foundPerson->getMatchScore() > 50.0)) {
                    continue;
                } else {
                    $sanitizedFoundLastName = preg_replace('/[[:space:]]+/', '', preg_replace('/[[:punct:]]+/', '', strtolower($foundPerson->getLastName())));
                    $sanitizedEMSALastName = preg_replace('/[[:space:]]+/', '', preg_replace('/[[:punct:]]+/', '', strtolower($person->getLastName())));

                    if (($sanitizedFoundLastName !== $sanitizedEMSALastName) && ($foundPerson->getDateOfBirth(true, "Y-m-d") === $person->getDateOfBirth(true, "Y-m-d"))) {
                        if (!in_array((int) $foundPerson->getPersonId(), $matchSet['personIds'])) {
                            $matchSet['personIds'][] = (int) $foundPerson->getPersonId();
                        }
                        if (!is_null($appDiseaseName) && (count($foundPerson->getRecordList()) > 0)) {
                            // if disease specified, check for same-disease events here...
                            /* @var $foundAppRecord AppRecord */
                            foreach ($foundPerson->getRecordList() as $foundAppRecord) {
                                if (in_array($foundAppRecord->getConditionName(), $crossrefConditions)) {
                                    if (!array_key_exists($foundAppRecord->getEventId(), $matchSet['eventIds'])) {
                                        $matchSet['eventIds'][$foundAppRecord->getEventId()] = array('record_number' => $foundAppRecord->getRecordNumber(), 'record_type' => $foundAppRecord->getRecordType());
                                    }
                                }
                            }
                        }
                    }
                }
            }
        } catch (Throwable $e) {
            ExceptionUtils::logException($e);
        }

        return $matchSet;
    }

    /**
     * Gets the user's full name for the specified Application based on user ID.
     * 
     * @param AppClientInterface $appClient Application client used for authentication.
     * @param string $userId User's ID.
     * 
     * @return string
     * @todo [someday] get ELR user ID from environment config, not hard-coded
     * @todo [someday] caching user full names in $_SESSION here really doesn't work, since session_write_close() is called before this is in emsa/index.php.  It works a bit (within each page turn, limiting to one call per user per page), but isn't really "cached" cached.
     */
    public static function userFullNameByUserId(AppClientInterface $appClient, $userId = null)
    {
        $cleanUserId = (string) filter_var($userId, FILTER_SANITIZE_STRING);

        if (!empty($cleanUserId)) {
            if ($cleanUserId === EPITRAX_AUTH_ELR_UID) {
                return '<em>ELR Service</em>';
            }
            
            // see if we've already cached this user's name for this environment
            if (!empty($_SESSION[EXPORT_SERVERNAME]['user_full_names'][$cleanUserId])) {
                return DisplayUtils::xSafe((string) $_SESSION[EXPORT_SERVERNAME]['user_full_names'][$cleanUserId]);
            }

            try {
                $fullNameFromClient = $appClient->getUser(null, $cleanUserId)->getFullName();

                if (empty($fullNameFromClient)) {
                    return '<em title="' . DisplayUtils::xSafe($cleanUserId) . '">Unknown User</em>';
                } else {
                    // cache the results so we don't have to waste a call next time
                    $_SESSION[EXPORT_SERVERNAME]['user_full_names'][$cleanUserId] = $fullNameFromClient;
                    return DisplayUtils::xSafe($fullNameFromClient);
                }
            } catch (Throwable $e) {
                ExceptionUtils::logException($e);
            }
        }

        return '<em title="' . DisplayUtils::xSafe($cleanUserId) . '">Unknown User</em>';
    }

    /**
     * Get the common (system) Jurisdiction ID for a given application-specific Jurisdiction ID.
     * 
     * @param PDO $dbConn PDO connection to EMSA database
     * @param int $appId Application ID
     * @param int $appJurisdictionId Jurisdiction ID from the specified Application
     * 
     * @return int System Jurisdiction ID.  Returns <b>NULL</b> if ID not found.
     */
    public static function getSystemJurisdictionIdFromApp(PDO $dbConn, $appId, $appJurisdictionId)
    {
        $systemJurisdictionId = null;

        try {
            $sql = "SELECT system_district_id
                    FROM app_jurisdictions
                    WHERE app_id = :appId
                    AND app_jurisdiction_id = :appJurisdictionId;";
            $stmt = $dbConn->prepare($sql);
            $stmt->bindValue(':appId', (int) $appId, PDO::PARAM_INT);
            $stmt->bindValue(':appJurisdictionId', (int) $appJurisdictionId, PDO::PARAM_INT);

            if ($stmt->execute()) {
                $systemJurisdictionId = (int) filter_var($stmt->fetchColumn(0), FILTER_SANITIZE_NUMBER_INT);
            }
        } catch (Throwable $e) {
            $systemJurisdictionId = null;
            ExceptionUtils::logException($e);
        }

        return $systemJurisdictionId;
    }

    /**
     * Get the application-specific Jurisdiction ID for a given common (system) Jurisdiction ID.
     * 
     * @param PDO $dbConn PDO connection to EMSA database
     * @param int $appId Application ID
     * @param int $systemJurisdictionId System Jurisdiction ID
     * 
     * @return int Application-specific Jurisdiction ID.  Returns <b>NULL</b> if ID not found.
     */
    public static function getAppJurisdictionIdFromSystem(PDO $dbConn, $appId, $systemJurisdictionId)
    {
        $appJurisdictionId = null;

        try {
            $sql = "SELECT app_jurisdiction_id
                    FROM app_jurisdictions
                    WHERE app_id = :appId
                    AND system_district_id = :systemJurisdictionId;";
            $stmt = $dbConn->prepare($sql);
            $stmt->bindValue(':appId', (int) $appId, PDO::PARAM_INT);
            $stmt->bindValue(':systemJurisdictionId', (int) $systemJurisdictionId, PDO::PARAM_INT);

            if ($stmt->execute()) {
                $appJurisdictionId = (int) filter_var($stmt->fetchColumn(0), FILTER_SANITIZE_NUMBER_INT);
            }
        } catch (Throwable $e) {
            $appJurisdictionId = null;
            ExceptionUtils::logException($e);
        }

        return $appJurisdictionId;
    }

    /**
     * Generate a string listing all of the patient's Medical Record Numbers in a comma-separated list
     *
     * @param array $mrnList
     *
     * @return string|null
     */
    public static function drawMRNList(array $mrnList): ?string
    {
        $mrnStr = null;

        if (!empty($mrnList)) {
            if (is_array($mrnList) && (count($mrnList) > 1)) {
                $mrnStr .= "; MRNs: ";
            } else {
                $mrnStr .= "; MRN: ";
            }

            $mrnStr .= DisplayUtils::xSafe(implode(', ', $mrnList), 'UTF-8', false);
        }

        return $mrnStr;
    }

}

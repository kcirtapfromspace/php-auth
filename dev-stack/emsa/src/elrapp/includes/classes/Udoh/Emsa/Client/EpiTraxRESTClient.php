<?php

namespace Udoh\Emsa\Client;

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

use BadMethodCallException;
use DateTime;
use Exception;
use PDO;
use SimpleXMLElement;
use Throwable;
use Udoh\Emsa\Exceptions\AppClientEmptyResponse;
use Udoh\Emsa\Exceptions\AppClientNoValidHosts;
use Udoh\Emsa\Exceptions\PeopleSearchTooManyResults;
use Udoh\Emsa\Model\AppRecord;
use Udoh\Emsa\Model\AppRoleList;
use Udoh\Emsa\Model\AppUser;
use Udoh\Emsa\Model\AppUserList;
use Udoh\Emsa\Model\ClientResponse;
use Udoh\Emsa\Model\CodedDataResult;
use Udoh\Emsa\Model\EpiTraxRecord;
use Udoh\Emsa\Model\Person;
use Udoh\Emsa\Model\PersonFacility;
use Udoh\Emsa\Model\PersonList;
use Udoh\Emsa\Utils\ExceptionUtils;

/**
 * Client for managing EpiTrax RESTful web service interactions.
 * 
 * @package Udoh\Emsa\Client
 *
 * @author Josh Ridderhoff <jridderhoff@utah.gov>
 */
class EpiTraxRESTClient implements AppClientInterface
{

    /** @var resource cURL session handle */
    protected $session;
    /** @var int */
    protected $appId;
    /** @var array */
    protected $roundRobinHosts;

    /**
     * Create a new application client to interface with the EpiTrax application via REST.
     *
     * @param int   $appId           Application ID for EpiTrax.
     * @param array $roundRobinHosts Array of EpiTrax REST Service hosts available for pseudo-round robin load balancing
     */
    public function __construct(int $appId, array $roundRobinHosts)
    {
        $this->appId = (int) filter_var($appId, \FILTER_SANITIZE_NUMBER_INT);
        $this->session = curl_init();
        $this->roundRobinHosts = $roundRobinHosts;
        
        curl_setopt($this->session, CURLOPT_CONNECTTIMEOUT, 5);
        curl_setopt($this->session, CURLOPT_TIMEOUT, 1800);
        curl_setopt($this->session, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($this->session, CURLOPT_FAILONERROR, true);
        curl_setopt($this->session, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($this->session, CURLOPT_HTTPHEADER, array(EPITRAX_AUTH_HEADER . ': ' . EPITRAX_AUTH_ELR_UID));
    }

    public function getAppName(): string
    {
        return 'EpiTrax';
    }
    
    /**
     * Gets the Application ID corresponding to EpiTrax.
     * 
     * @return int
     */
    public function getAppId(): int
    {
        return $this->appId;
    }
    
    /**
     * Indicates whether this Application is enabled in EMSA.
     * 
     * @param PDO $dbConn Connection to the EMSA database.
     *
     * @return bool
     */
    public function getAppEnabled(PDO $dbConn): bool
    {
        $appEnabled = false;
        
        try {
            $sql = "SELECT COUNT(*) FROM vocab_app WHERE id = :appId AND enabled IS TRUE;";
            $stmt = $dbConn->prepare($sql);
            $stmt->bindValue(':appId', $this->getAppId(), PDO::PARAM_INT);
            
            if ($stmt->execute()) {
                if ((int) $stmt->fetchColumn(0) === 1) {
                    $appEnabled = true;
                }
            }
        } catch (Exception $ex) {
            ExceptionUtils::logException($ex);
        } finally {
            $stmt = null;
        }
        
        return $appEnabled;
    }
    
    /**
     * Indicates whether to trigger notifications for messages assigned to this Application by EMSA.
     * 
     * @param PDO $dbConn Connection to the EMSA database.
     * 
     * @return bool
     */
    public function getTriggerNotifications(PDO $dbConn): bool
    {
        $appTriggerNotifications = false;
        
        try {
            $sql = "SELECT COUNT(*) FROM vocab_app WHERE id = :appId AND trigger_notifications IS TRUE;";
            $stmt = $dbConn->prepare($sql);
            $stmt->bindValue(':appId', $this->getAppId(), PDO::PARAM_INT);
            
            if ($stmt->execute()) {
                if ((int) $stmt->fetchColumn(0) === 1) {
                    $appTriggerNotifications = true;
                }
            }
        } catch (Throwable $ex) {
            ExceptionUtils::logException($ex);
        } finally {
            $stmt = null;
        }
        
        return $appTriggerNotifications;
    }

    /**
     * Generates a new EpiTraxRecord object.
     * 
     * @return EpiTraxRecord
     */
    public function getNewAppRecord(): AppRecord
    {
        return new EpiTraxRecord();
    }
    
    /**
     * Returns a URL pointing to a specified personCondition in EpiTrax.
     *
     * @param int    $recordType   One of \Udoh\Emsa\Constants\AppRecordType
     * @param string $recordNumber [Optional] Record Number of the target record
     * @param bool   $readOnly     [Optional; Default <b>TRUE</b>] If <b>FALSE</b> and Application supports the feature, link points to a modifiable view of the record.  Otherwise, link points to read-only view.
     * @param int    $recordId     [Optional] If needed, the internal ID of the record to retrieve.
     *
     * @return string
     */
    public function getAppLinkToRecord(int $recordType, ?string $recordNumber = null, ?bool $readOnly = true, ?int $recordId = null): string
    {
        $recordType = null;  // not needed for EpiTrax

        $readOnly = $readOnly ?? true;

        if (empty($recordNumber) && empty($recordId)) {
            throw new BadMethodCallException('Missing either a personCondition ID or Record Number');
        }
        
        $recordUrl = BASE_EPITRAX_URL;
        
        $recordUrl .= 'event/';
        
        if ($readOnly === true) {
            $recordUrl .= 'view/';
        } else {
            $recordUrl .= 'edit/';
        }
        
        if (empty($recordNumber) || !empty($recordId)) {
            $recordUrl .= 'id/' . (int) filter_var($recordId, FILTER_SANITIZE_NUMBER_INT);
        } else {
            $recordUrl .= urlencode((string) filter_var($recordNumber, FILTER_SANITIZE_STRING));
        }
        
        return $recordUrl;
    }
    
    /**
     * Returns a URL pointing to a specified person in EpiTrax.
     *
     * @param int  $personId ID of the target person.
     * @param bool $readOnly [Optional; Default <b>TRUE</b>] If <b>FALSE</b> and Application supports the feature, link points to an editable view of the person.  Otherwise, link points to read-only view.
     *
     * @return string
     */
    public function getAppLinkToPerson(int $personId, ?bool $readOnly = true): string
    {
        $personUrl = BASE_EPITRAX_URL;
        
        $personUrl .= 'person/';

        $readOnly = $readOnly ?? true;

        if ($readOnly === true) {
            $personUrl .= 'view/';
        } else {
            $personUrl .= 'edit/';
        }
        
        $personUrl .= (int) filter_var($personId, FILTER_SANITIZE_NUMBER_INT);
        
        return $personUrl;
    }

    /**
     * Add a new personCondition to EpiTrax.
     *
     * @param SimpleXMLElement $addXml New personCondition record in nedssHealth XML format.
     *
     * @return SimpleXMLElement Response from REST API savePerson function.
     *
     * @throws Exception on HTTP error
     */
    public function addRecord(SimpleXMLElement $addXml): SimpleXMLElement
    {
        $serviceURL = $this->getServiceURLRoundRobin() . EPITRAX_REST_SERVICE_URL . 'savePerson';

        curl_setopt($this->session, CURLOPT_URL, $serviceURL);
        curl_setopt($this->session, CURLOPT_POST, true);
        curl_setopt($this->session, CURLOPT_HTTPHEADER, array(EPITRAX_AUTH_HEADER . ': ' . EPITRAX_AUTH_ELR_UID, 'Content-Type: application/xml'));
        curl_setopt($this->session, CURLOPT_POSTFIELDS, $addXml->asXML());

        $savePersonResponse = curl_exec($this->session);

        if ($savePersonResponse === false) {
            throw new Exception('Unable to connect to EpiTrax: ' . curl_error($this->session));
        } else {
            return new SimpleXMLElement($savePersonResponse);
        }
    }

    /**
     * Returns the code/value data for a specific EpiTrax codeset.
     *
     * @param string $codesetName Name of codeset to retrieve. One of:<br>case, county, ethnicity, gender, lab_test_status, race, specimen, state, test_result
     * @param string $badHost     [Optional] If specified, a host that should be marked as "bad" and removed from the pool of hosts to round robin.
     *
     * @return CodedDataResult[] Array of \Udoh\Emsa\Model\CodedDataResult objects for each sub-codeset found in the specified <i>codesetName</i>.
     *
     * @throws Exception on HTTP error
     */
    public function getCodedData(string $codesetName, ?string $badHost = null): array
    {
        $codedDataResultSet = array();
        $cleanCodesetName = (string) filter_var($codesetName, FILTER_SANITIZE_STRING);
        
        try {
            $baseUrl = $this->getServiceURLRoundRobin($badHost) . EPITRAX_REST_SERVICE_URL;
        } catch (AppClientNoValidHosts $nvh) {
            ExceptionUtils::logException($nvh);
            throw new Exception('Unable to connect to EpiTrax: ' . curl_error($this->session));
        }
        
        if ($cleanCodesetName == 'diseases') {
            $serviceURL = $baseUrl . 'getCodedData/condition';
        } elseif ($cleanCodesetName == 'organisms') {
            $serviceURL = $baseUrl . 'getCodedData/organism';
        } elseif ($cleanCodesetName == 'common_test_types') {
            $serviceURL = $baseUrl . 'getCodedData/lab_test_type';
        } elseif ($cleanCodesetName == 'case') {
            $serviceURL = $baseUrl . 'getCodedData/case_status';
        } elseif ($cleanCodesetName == 'specimen') {
            $serviceURL = $baseUrl . 'getCodedData/specimen_source';
        } else {
            $serviceURL = $baseUrl . 'getCodedData/' . urlencode($cleanCodesetName);
        }
        
        curl_setopt($this->session, CURLOPT_HTTPGET, true);
        curl_setopt($this->session, CURLOPT_URL, $serviceURL);

        $getCodedDataResults = curl_exec($this->session);

        if ($getCodedDataResults === false) {
            return $this->getCodedData($codesetName, $baseUrl);
        } else {
            $getCodedDataResultsXml = new SimpleXMLElement($getCodedDataResults);
        }

        $codedResult = new CodedDataResult($cleanCodesetName);
        
        $i = 1;
        foreach ($getCodedDataResultsXml->externalCode as $externalCodeNode) {
            if ((int) filter_var($externalCodeNode->id, FILTER_SANITIZE_NUMBER_INT) > 0) {
                $codedResult->addEntry((int) filter_var($externalCodeNode->id, FILTER_SANITIZE_NUMBER_INT), (string) filter_var($externalCodeNode->description, FILTER_SANITIZE_STRING), (string) filter_var($externalCodeNode->code, FILTER_SANITIZE_STRING));
            } else {
                $codedResult->addEntry($i, (string) $externalCodeNode->description, (string) $externalCodeNode->code);
            }
            $i++;
        }

        $codedDataResultSet[] = clone $codedResult;

        return $codedDataResultSet;
    }

    /**
     * Returns a list of the coded table names that need to be dumped for EpiTrax.
     * 
     * @return array
     */
    public function getCodedDataTables(): array
    {
        $dumpableTables = array(
            'agency',
            'case',
            'county',
            'ethnicity',
            'gender',
            'lab_test_status',
            'language',
            'race',
            'specimen',
            'state',
            'test_result',
            'diseases',
            'organisms',
            'common_test_types',
            'facility_visit_type',
            'resist_test_agent',
            'resist_test_result'
        );

        return $dumpableTables;
    }

    /**
     * Get the jurisdictions configured for EpiTrax.
     *
     * @param PDO    $dbConn  [Not used for EpiTrax]
     * @param string $badHost [Optional] If specified, a host that should be marked as "bad" and removed from the pool
     *                        of hosts to round robin.
     *
     * @return array
     *
     * @throws Exception
     */
    public function getJurisdictions(?PDO $dbConn = null, ?string $badHost = null): array
    {
        $dbConn = null;
        $jurisdictions = array();
        
        try {
            $baseUrl = $this->getServiceURLRoundRobin($badHost) . EPITRAX_REST_SERVICE_URL;
        } catch (AppClientNoValidHosts $nvh) {
            ExceptionUtils::logException($nvh);
            throw new Exception('Unable to connect to EpiTrax: ' . curl_error($this->session));
        }

        $serviceURL = $baseUrl . 'getCodedData/agency';
        curl_setopt($this->session, CURLOPT_HTTPGET, true);
        curl_setopt($this->session, CURLOPT_URL, $serviceURL);

        $getJurisdictionsData = curl_exec($this->session);

        if ($getJurisdictionsData === false) {
            return $this->getJurisdictions($dbConn, $baseUrl);
        } else {
            $getJurisdictionsXml = new SimpleXMLElement($getJurisdictionsData);
            if (isset($getJurisdictionsXml->externalCode) && (count($getJurisdictionsXml->externalCode) > 0)) {
                foreach ($getJurisdictionsXml->externalCode as $agencyItem) {
                    $jurisdictions[(int) filter_var($agencyItem->id, \FILTER_SANITIZE_NUMBER_INT)] = (string) filter_var($agencyItem->description, FILTER_SANITIZE_STRING);
                }
            }
        }
        
        asort($jurisdictions, \SORT_NATURAL);

        return $jurisdictions;
    }

    /**
     * Get a person by ID.
     *
     * @param int    $personId Person ID to retrieve.
     * @param string $badHost  [Optional] If specified, a host that should be marked as "bad" and removed from the pool of hosts to round robin.
     *
     * @return Person
     *
     * @throws Exception on HTTP error
     */
    public function getPerson(int $personId, ?string $badHost = null): Person
    {
        $cleanPersonId = (int) filter_var($personId, \FILTER_SANITIZE_NUMBER_INT);
        
        try {
            $baseUrl = $this->getServiceURLRoundRobin($badHost) . EPITRAX_REST_SERVICE_URL;
        } catch (AppClientNoValidHosts $nvh) {
            ExceptionUtils::logException($nvh);
            throw new Exception('Unable to connect to EpiTrax: ' . curl_error($this->session));
        }
        
        $serviceURL = $baseUrl . 'getPerson/' . urlencode($cleanPersonId);

        curl_setopt($this->session, CURLOPT_HTTPGET, true);
        curl_setopt($this->session, CURLOPT_URL, $serviceURL);

        $getPersonResults = curl_exec($this->session);

        if ($getPersonResults === false) {
            return $this->getPerson($personId, $baseUrl);
        } else {
            $nedssHealthObj = new SimpleXMLElement($getPersonResults);
        }

        $getPersonResponseValidation = $this->validateResponse($nedssHealthObj);

        if (!$getPersonResponseValidation->getStatus()) {
            throw new Exception($getPersonResponseValidation->getErrorString());
        }

        return $this->buildPersonFromXML($nedssHealthObj->person);
    }

    /**
     * Locate an EpiTrax personCondition by ID and return it as an \Udoh\Emsa\Model\EpiTraxRecord object.
     *
     * @param int    $recordId
     * @param string $badHost [Optional] If specified, a host that should be marked as "bad" and removed from the pool of hosts to round robin.
     *
     * @return EpiTraxRecord
     *
     * @throws Exception on HTTP error
     */
    public function getRecord(int $recordId, ?string $badHost = null): AppRecord
    {
        $cleanRecordId = (int) filter_var($recordId, \FILTER_SANITIZE_NUMBER_INT);
        
        try {
            $baseUrl = $this->getServiceURLRoundRobin($badHost) . EPITRAX_REST_SERVICE_URL;
        } catch (AppClientNoValidHosts $nvh) {
            ExceptionUtils::logException($nvh);
            throw new Exception('Unable to connect to EpiTrax: ' . curl_error($this->session));
        }
        
        $serviceURL = $baseUrl . 'getPersonCondition/' . urlencode($cleanRecordId);

        curl_setopt($this->session, CURLOPT_HTTPGET, true);
        curl_setopt($this->session, CURLOPT_URL, $serviceURL);

        $getRecordResults = curl_exec($this->session);

        if ($getRecordResults === false) {
            return $this->getRecord($recordId, $baseUrl);
        } else {
            $getRecordResultsXml = new SimpleXMLElement($getRecordResults);
        }

        $getRecordResponseValidation = $this->validateResponse($getRecordResultsXml);

        if (!$getRecordResponseValidation->getStatus()) {
            throw new Exception($getRecordResponseValidation->getErrorString());
        }
        
        $thisPersonCondition = $getRecordResultsXml->person->personCondition;
        $thisPersonId = (int) $getRecordResultsXml->person->id;
        
        $newRecord = new EpiTraxRecord();
        $newRecord->setAppRecordDocument($thisPersonCondition);
        $newRecord->setPersonId($thisPersonId);

        return $newRecord;
    }

    /**
     * Returns the available user roles from the current application.
     * 
     * @param string $badHost [Optional] If specified, a host that should be marked as "bad" and removed from the pool of hosts to round robin.
     * 
     * @return AppRoleList Set of EpiTrax roles.
     *
     * @throws Exception on HTTP error
     */
    public function getRoles(?string $badHost = null): AppRoleList
    {
        $roleList = new AppRoleList();
        
        try {
            $baseUrl = $this->getServiceURLRoundRobin($badHost) . EPITRAX_REST_SERVICE_URL;
        } catch (AppClientNoValidHosts $nvh) {
            ExceptionUtils::logException($nvh);
            throw new Exception('Unable to connect to EpiTrax: ' . curl_error($this->session));
        }

        $serviceURL = $baseUrl . 'getRoles';
        curl_setopt($this->session, CURLOPT_HTTPGET, true);
        curl_setopt($this->session, CURLOPT_URL, $serviceURL);

        $getRolesData = curl_exec($this->session);

        if ($getRolesData === false) {
            return $this->getRoles($baseUrl);
        } else {
            $getRolesXml = new SimpleXMLElement($getRolesData);
            if (isset($getRolesXml->role) && (count($getRolesXml->role) > 0)) {
                foreach ($getRolesXml->role as $roleItem) {
                    $roleList->add((int) $roleItem->id, (string) $roleItem->roleName);
                }
            }
        }

        return $roleList;
    }

    /**
     * Get EpiTrax user roles for a specific user.
     * 
     * @param string $userId User's EpiTrax User ID.
     * @param string $badHost [Optional] If specified, a host that should be marked as "bad" and removed from the pool of hosts to round robin.
     * 
     * @return AppRoleList Set of EpiTrax roles for this user.
     *
     * @throws Exception on HTTP error
     */
    public function getUserRoles(string $userId, ?string $badHost = null): AppRoleList
    {
        $roleList = new AppRoleList();

        $cleanUserId = (string) filter_var($userId, FILTER_SANITIZE_STRING);
        
        try {
            $baseUrl = $this->getServiceURLRoundRobin($badHost) . EPITRAX_REST_SERVICE_URL;
        } catch (AppClientNoValidHosts $nvh) {
            ExceptionUtils::logException($nvh);
            throw new Exception('Unable to connect to EpiTrax: ' . curl_error($this->session));
        }
        
        $serviceURL = $baseUrl . 'getUserAgencyRoles/' . urlencode($cleanUserId);

        curl_setopt($this->session, CURLOPT_HTTPGET, true);
        curl_setopt($this->session, CURLOPT_URL, $serviceURL);

        $getUserRolesResults = curl_exec($this->session);

        if ($getUserRolesResults === false) {
            return $this->getUserRoles($userId, $baseUrl);
        } else {
            $getUserRolesResultsXml = new SimpleXMLElement($getUserRolesResults);
            if (isset($getUserRolesResultsXml->userAgencyRoles)) {
                foreach ($getUserRolesResultsXml->userAgencyRoles as $userRole) {
                    $roleList->add((int) $userRole->role->id, (string) $userRole->role->roleName);
                }
            }
        }

        return $roleList;
    }

    /**
     * Returns the users defined by EpiTrax for a give role and jurisdiction.
     *
     * @param int    $roleId         Filter users by role ID.
     * @param int    $jurisdictionId Filter users by jurisdiction ID.
     * @param string $badHost        [Optional] If specified, a host that should be marked as "bad" and removed from
     *                               the pool of hosts to round robin.
     *
     * @return AppUserList
     *
     * @throws Exception
     */
    public function getUsers(int $roleId, int $jurisdictionId, ?string $badHost = null): AppUserList
    {
        $userList = new AppUserList();

        $cleanRoleId = (int) filter_var($roleId, \FILTER_SANITIZE_NUMBER_INT);
        $cleanJurisdictionId = (int) filter_var($jurisdictionId, \FILTER_SANITIZE_NUMBER_INT);
        
        try {
            $baseUrl = $this->getServiceURLRoundRobin($badHost) . EPITRAX_REST_SERVICE_URL;
        } catch (AppClientNoValidHosts $nvh) {
            ExceptionUtils::logException($nvh);
            throw new Exception('Unable to connect to EpiTrax: ' . curl_error($this->session));
        }
        
        $serviceURL = $baseUrl . 'getUsers/' . urlencode($cleanRoleId) . '/' . urlencode($cleanJurisdictionId);

        curl_setopt($this->session, CURLOPT_HTTPGET, true);
        curl_setopt($this->session, CURLOPT_URL, $serviceURL);

        $getUsersResults = curl_exec($this->session);

        if ($getUsersResults === false) {
            return $this->getUsers($roleId, $jurisdictionId, $baseUrl);
        } else {
            $getUsersResultsXml = new SimpleXMLElement($getUsersResults);

            foreach ($getUsersResultsXml->user as $foundUser) {
                $userList->addAppUser(self::createUserFromXML($foundUser));
            }
        }

        return $userList;
    }

    /**
     * Get a specific EpiTrax user by user ID.
     *
     * @param int    $id      [Not supported in EpiTrax]
     * @param string $uid     User's EpiTrax user ID ('uid' string value)
     * @param string $badHost [Optional] If specified, a host that should be marked as "bad" and removed from the pool
     *                        of hosts to round robin.
     *
     * @return AppUser
     *
     * @throws Exception
     */
    public function getUser(?int $id = null, string $uid = null, ?string $badHost = null): AppUser
    {
        $id = null;
        
        $cleanUserId = (string) filter_var($uid, FILTER_SANITIZE_STRING);
        
        try {
            $baseUrl = $this->getServiceURLRoundRobin($badHost) . EPITRAX_REST_SERVICE_URL;
        } catch (AppClientNoValidHosts $nvh) {
            ExceptionUtils::logException($nvh);
            throw new Exception('Unable to connect to EpiTrax: ' . curl_error($this->session));
        }
        
        $serviceURL = $baseUrl . 'getUser/' . urlencode($cleanUserId);

        curl_setopt($this->session, CURLOPT_HTTPGET, true);
        curl_setopt($this->session, CURLOPT_URL, $serviceURL);

        $getUserResults = curl_exec($this->session);

        if ($getUserResults === false) {
            return $this->getUser($id, $uid, $baseUrl);
        } else {
            $getUserResultsXml = new SimpleXMLElement($getUserResults);
            return self::createUserFromXML($getUserResultsXml->user);
        }
    }

    /**
     * Search for people in EpiTrax by name and/or date of birth.
     *
     * @param string   $firstName  [Optional] Patient given name
     * @param string   $lastName   [Optional] Patient family name
     * @param string   $middleName [Optional] Patient middle name
     * @param DateTime $birthDate  [Optional] Patient DOB
     * @param string   $birthSex   [Optional] Patient birth sex
     * @param array    $conditions Not used with EpiTrax (search is person-centric, not event-centric)
     * @param int      $matchScore [Optional; Default 60] Limit matched patients to those with this match score or better.
     * @param string   $badHost    [Optional] If specified, a host that should be marked as "bad" and removed from the pool of hosts to round robin.
     *
     * @return PersonList
     *
     * @throws Exception on HTTP error
     * @throws PeopleSearchTooManyResults If too many Persons are matched in EpiTrax
     */
    public function searchPerson(?string $firstName = null, ?string $lastName = null, ?string $middleName = null, ?DateTime $birthDate = null, ?string $birthSex = null, ?array $conditions = null, ?int $matchScore = 60, ?string $badHost = null): PersonList
    {
        $conditions = null;  // Not used with EpiTrax (search is person-centric, not event-centric)
        $searchPersonResults = new PersonList();

        if (empty($matchScore)) {
            $cleanMatchScore = 60;
        } else {
            $tmpMatchScore = (int) filter_var($matchScore, \FILTER_SANITIZE_NUMBER_INT);
            if (($tmpMatchScore >= 0) && ($tmpMatchScore <= 100)) {
                $cleanMatchScore = $tmpMatchScore;
            } else {
                $cleanMatchScore = 60;
            }
        }

        $searchXml = new SimpleXMLElement('<?xml version="1.0" encoding="UTF-8" standalone="yes"?><nedssHealth><person></person></nedssHealth>');

        if (!empty($firstName)) {
            $searchXml->person->addChild('firstName', (string) filter_var($firstName, FILTER_SANITIZE_STRING));
        }
        if (!empty($lastName)) {
            $searchXml->person->addChild('lastName', (string) filter_var($lastName, FILTER_SANITIZE_STRING));
        }
        if (!empty($middleName)) {
            $searchXml->person->addChild('middleName', (string) filter_var($middleName, FILTER_SANITIZE_STRING));
        }
        if (!is_null($birthDate)) {
            $searchXml->person->addChild('birthDate', (string) $birthDate->format(DateTime::RFC3339));
        }
        if (!empty($birthSex)) {
            $searchXml->person->addChild('birthGender')->addChild('code', (string) filter_var($birthSex, FILTER_SANITIZE_STRING));
        }
        
        $searchXml->person->addChild('score', (string) $cleanMatchScore);
        
        try {
            $baseUrl = $this->getServiceURLRoundRobin($badHost) . EPITRAX_REST_SERVICE_URL;
        } catch (AppClientNoValidHosts $nvh) {
            ExceptionUtils::logException($nvh);
            throw new Exception('Unable to connect to EpiTrax: ' . curl_error($this->session));
        }

        $serviceURL = $baseUrl . 'findPeople';

        curl_setopt($this->session, CURLOPT_URL, $serviceURL);
        curl_setopt($this->session, CURLOPT_POST, true);
        curl_setopt($this->session, CURLOPT_HTTPHEADER, array(EPITRAX_AUTH_HEADER . ': ' . EPITRAX_AUTH_ELR_UID, 'Content-Type: application/xml'));
        curl_setopt($this->session, CURLOPT_POSTFIELDS, $searchXml->asXML());

        $findPeopleResponse = curl_exec($this->session);

        if ($findPeopleResponse === false) {
            return $this->searchPerson($firstName, $lastName, $middleName, $birthDate, $birthSex, $conditions, $matchScore, $baseUrl);
        } else {
            $nedssHealthObj = new SimpleXMLElement($findPeopleResponse);

            if (isset($nedssHealthObj->exception)) {
                throw new PeopleSearchTooManyResults((string) $nedssHealthObj->exception);
            }
            
            foreach ($nedssHealthObj->person as $foundPerson) {
                $searchPersonResults->add($this->buildPersonFromXML($foundPerson));
            }
        }

        return $searchPersonResults;
    }

    /**
     * Update a personCondition in EpiTrax.
     *
     * @param SimpleXMLElement $updateXml Updates to personCondition record in nedssHealth XML format.
     *
     * @return SimpleXMLElement Response from REST API savePerson function.
     *
     * @throws Exception on HTTP error
     */
    public function updateRecord(SimpleXMLElement $updateXml): SimpleXMLElement
    {
        $serviceURL = $this->getServiceURLRoundRobin() . EPITRAX_REST_SERVICE_URL . 'savePerson';

        curl_setopt($this->session, CURLOPT_URL, $serviceURL);
        curl_setopt($this->session, CURLOPT_POST, true);
        curl_setopt($this->session, CURLOPT_HTTPHEADER, array(EPITRAX_AUTH_HEADER . ': ' . EPITRAX_AUTH_ELR_UID, 'Content-Type: application/xml'));
        curl_setopt($this->session, CURLOPT_POSTFIELDS, $updateXml->asXML());

        $savePersonResponse = curl_exec($this->session);

        if ($savePersonResponse === false) {
            throw new Exception('Unable to connect to EpiTrax: ' . curl_error($this->session));
        } else {
            return new SimpleXMLElement($savePersonResponse);
        }
    }
    
    /**
     * Create an AppUser object from EpiTrax XML.
     * 
     * @param SimpleXMLElement $appUserXML
     *
     * @return AppUser
     */
    public static function createUserFromXML(SimpleXMLElement $appUserXML = null): AppUser
    {
        $user = new AppUser();
        
        if (isset($appUserXML->givenName) && (strlen($appUserXML->givenName) > 0)) {
            $user->setFullName((string) $appUserXML->givenName);
        } else {
            $tmpFullName = '';

            if (isset($appUserXML->firstName) && (strlen($appUserXML->firstName) > 0)) {
                $tmpFullName .= (string) $appUserXML->firstName;
            }

            $tmpFullName .= ' ';

            if (isset($appUserXML->lastName) && (strlen($appUserXML->lastName) > 0)) {
                $tmpFullName .= (string) $appUserXML->lastName;
            }

            $user->setFullName(trim($tmpFullName));
        }

        if (isset($appUserXML->email) && (strlen($appUserXML->email) > 0)) {
            $user->setEmailAddress((string) $appUserXML->email);
        }
        
        return $user;
    }

    /**
     * Build a \Udoh\Emsa\Model\Person object from nedssHealth/person XML.
     *
     * @param SimpleXMLElement $personObj
     *
     * @return Person
     *
     * @throws Exception
     */
    private function buildPersonFromXML(SimpleXMLElement $personObj): Person
    {
        $thisPerson = new Person();
        $thisPerson->setPersonId((int) $personObj->id);
        $thisPerson->setFirstName((string) $personObj->firstName);
        $thisPerson->setLastName((string) $personObj->lastName);
        $thisPerson->setMiddleName((string) $personObj->middleName);

        if (isset($personObj->birthGender->code) && !empty($personObj->birthGender->code)) {
            $thisPerson->setGender(\Udoh\Emsa\Utils\CodedDataUtils::getCodeDescriptionFromCodedValue($this, 'gender', (string) $personObj->birthGender->code));
        }
        
        if (!empty($personObj->race)) {
            foreach ($personObj->race as $foundRace) {
                if (!empty($foundRace->code)) {
                    $thisPerson->addRace(\Udoh\Emsa\Utils\CodedDataUtils::getCodeDescriptionFromCodedValue($this, 'race', (string) $foundRace->code));
                }
            }
        }
        
        if (isset($personObj->ethnicity->code) && !empty($personObj->ethnicity->code)) {
            $thisPerson->setEthnicity(\Udoh\Emsa\Utils\CodedDataUtils::getCodeDescriptionFromCodedValue($this, 'ethnicity', (string) $personObj->ethnicity->code));
        }

        if (isset($personObj->language->code) && !empty($personObj->language->code)) {
            $thisPerson->setLanguage(\Udoh\Emsa\Utils\CodedDataUtils::getCodeDescriptionFromCodedValue($this, 'language', (string) $personObj->language->code));
        }

        if (isset($personObj->birthDate) && !empty($personObj->birthDate)) {
            $thisPerson->setDateOfBirth(\Udoh\Emsa\Utils\DateTimeUtils::createMixed((string) $personObj->birthDate));
        }
        
        if (isset($personObj->dateOfDeath) && !empty($personObj->dateOfDeath)) {
            $thisPerson->setDateOfDeath(\Udoh\Emsa\Utils\DateTimeUtils::createMixed((string) $personObj->dateOfDeath));
        }

        if (!empty($personObj->personFacility)) {
            foreach ($personObj->personFacility as $personFacilityXML) {
                $thisPerson->addPersonFacility(new PersonFacility($personFacilityXML));
            }
        }
        
        if (!empty($personObj->personAddress)) {
            foreach ($personObj->personAddress as $foundAddress) {
                $thisAddress = new \Udoh\Emsa\Model\Address();
                $thisAddress->setStreet((string) $foundAddress->street);
                $thisAddress->setUnitNumber((string) $foundAddress->unitNumber);
                $thisAddress->setCity((string) $foundAddress->city);
                $thisAddress->setState((string) $foundAddress->state->code);
                $thisAddress->setPostalCode((string) $foundAddress->postalCode);
                $thisAddress->setCounty((string) $foundAddress->county->code);
                $thisPerson->addAddress($thisAddress);
            }
        }
        
        if (!empty($personObj->personTelephone)) {
            foreach ($personObj->personTelephone as $foundTelephone) {
                $thisTelecom = new \Udoh\Emsa\Model\Telecom();
                $thisTelecom->setCountryCode((string)$foundTelephone->countryCode);
                $thisTelecom->setAreaCode((string)$foundTelephone->areaCode);
                $thisTelecom->setLocalNumber((string)$foundTelephone->phoneNumber);
                $thisTelecom->setExtension((string)$foundTelephone->extension);
                $thisTelecom->setType(\Udoh\Emsa\Model\Telecom::TYPE_PHONE);

                if (empty($foundTelephone->telephoneType->code) || ((string)$foundTelephone->telephoneType->code == 'UNK')) {
                    $thisTelecom->setUse(\Udoh\Emsa\Model\Telecom::USE_UNKNOWN);
                } elseif ((string)$foundTelephone->telephoneType->code == 'HT') {
                    $thisTelecom->setUse(\Udoh\Emsa\Model\Telecom::USE_HOME);
                } elseif ((string)$foundTelephone->telephoneType->code == 'MT') {
                    $thisTelecom->setUse(\Udoh\Emsa\Model\Telecom::USE_MOBILE);
                } elseif ((string)$foundTelephone->telephoneType->code == 'WT') {
                    $thisTelecom->setUse(\Udoh\Emsa\Model\Telecom::USE_WORK);
                } else {
                    $thisTelecom->setUse(\Udoh\Emsa\Model\Telecom::USE_UNKNOWN);
                }

                $thisPerson->addTelecom($thisTelecom);
            }
        }
            
        if (!empty($personObj->personEmail)) {
            foreach ($personObj->personEmail as $foundEmailAddress) {
                $thisTelecom = new \Udoh\Emsa\Model\Telecom();
                $thisTelecom->setType(\Udoh\Emsa\Model\Telecom::TYPE_EMAIL);
                $thisTelecom->setUse(\Udoh\Emsa\Model\Telecom::USE_UNKNOWN);
                $thisTelecom->setEmailAddress((string) filter_var($foundEmailAddress->emailAddress, FILTER_SANITIZE_EMAIL));
                $thisPerson->addTelecom($thisTelecom);
            }
        }

        $thisPerson->setMatchScore((float) $personObj->score);

        if (!empty($personObj->personCondition)) {
            foreach ($personObj->personCondition as $foundPersonCondition) {
                if (empty($foundPersonCondition->deletedAt)) {
                    // if personCondition is deleted, don't add...
                    $thisPersonCondition = new EpiTraxRecord();
                    $thisPersonCondition->setAppRecordDocument($foundPersonCondition);
                    $thisPersonCondition->setPersonId($thisPerson->getPersonId());
                    $thisPerson->addRecord($thisPersonCondition);
                }
            }
        }
        
        return $thisPerson;
    }

    /**
     * Gets a blank instance of an EpiTrax nedssHealth XML document.
     * 
     * Probably just used for development & debugging, but hey... vestigial methods are cool, right?
     * 
     * @return SimpleXMLElement
     *
     * @throws Exception on HTTP error
     */
    public function getInstance(): SimpleXMLElement
    {
        $serviceURL = $this->getServiceURLRoundRobin() . EPITRAX_REST_SERVICE_URL . 'getInstance';
        curl_setopt($this->session, CURLOPT_HTTPGET, true);
        curl_setopt($this->session, CURLOPT_URL, $serviceURL);

        $getInstanceData = curl_exec($this->session);

        if ($getInstanceData === false) {
            throw new Exception('Unable to connect to EpiTrax: ' . curl_error($this->session));
        } else {
            $getInstanceXml = new SimpleXMLElement($getInstanceData);
        }

        return $getInstanceXml;
    }

    /**
     * Validates the response from the EpiTrax REST API and returns its status.
     * 
     * @param SimpleXMLElement $responseXML XML response from REST
     * 
     * @return ClientResponse
     * 
     * @throws AppClientEmptyResponse If <i>responseXML</i> is null or empty
     */
    public function validateResponse(SimpleXMLElement $responseXML = null): ClientResponse
    {
        if (empty($responseXML)) {
            throw new AppClientEmptyResponse();
        }
        
        $restResponse = new ClientResponse();
        
        $statusBool = true;
        $lockBool = false;
        
        if (isset($responseXML->exception)) {
            if (is_array($responseXML->exception) && (count($responseXML->exception) > 0)) {
                foreach ($responseXML->exception as $exceptionNode) {
                    if (stripos($exceptionNode, "locked") !== false) {
                        $lockBool = $lockBool || true;
                    }
                    $statusBool = $statusBool && false;
                    $restResponse->addError('REST', (string) 'false', (string) $exceptionNode);
                }
            } else {
                if (stripos($responseXML->exception, "locked") !== false) {
                    $lockBool = $lockBool || true;
                }
                $statusBool = $statusBool && false;
                $restResponse->addError('REST', (string) 'false', (string) $responseXML->exception);
            }
        }
        
        $restResponse->setStatus($statusBool);
        $restResponse->setLock($lockBool);
        
        return $restResponse;
    }
    
    /**
     * Get a URL to the EpiTrax REST service based on pseudo-round robin load balancing, based on number of hosts supplied.
     * 
     * @param string $badHost [Optional] If specified, a host that should be marked as "bad" and removed from the pool of hosts to round robin.
     * 
     * @return string
     *
     * @throws AppClientNoValidHosts
     */
    public function getServiceURLRoundRobin($badHost = null): string
    {
        if (!empty($badHost)) {
            foreach ($this->roundRobinHosts as $badHostKey => $badHostURL) {
                if (stripos($badHost, $badHostURL) !== false) {
                    unset($this->roundRobinHosts[$badHostKey]);
                }
            }
            
            // rebase array keys just in case we unset() any of them
            // (needed for random reference below)
            $this->roundRobinHosts = array_values($this->roundRobinHosts);
        }
        
        if (count($this->roundRobinHosts) === 0) {
            throw new AppClientNoValidHosts('No valid hosts available for EpiTrax REST Client');
        }
        
        $numHosts = (int) count($this->roundRobinHosts) - 1;

        try {
            $roundRobinHostIndex = random_int(0, $numHosts);
        } catch (Throwable $e) {
            $roundRobinHostIndex = mt_rand(0, $numHosts);
        }

        $roundRobinHost = null;
        
        if (!empty($this->roundRobinHosts[$roundRobinHostIndex])) {
            $roundRobinHost = (string) $this->roundRobinHosts[$roundRobinHostIndex];
        }

        return $roundRobinHost;
    }

}

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

use Throwable;

/**
 * Client for managing TriSano SQLA web service requests.
 * 
 * @package Udoh\Emsa\Client
 *
 * @author Josh Ridderhoff <jridderhoff@utah.gov>
 */
class TrisanoSQLAClient implements \Udoh\Emsa\Client\AppClientInterface
{

    /** @var \SoapClient */
    protected $client;

    /** @var int */
    protected $appId;

    /**
     * Create a new client for interacting with TriSano SQLA Web Service
     * 
     * @param int $appId Application ID for TriSano.
     * @throws \Udoh\Emsa\Exceptions\EmsaSoapConnectionFault
     */
    public function __construct($appId)
    {
        $this->appId = (int) filter_var($appId, \FILTER_SANITIZE_NUMBER_INT);

        try {
            $this->client = new \SoapClient(\WSDL_PATH);
        } catch (\SoapFault $e) {
            throw new \Udoh\Emsa\Exceptions\EmsaSoapConnectionFault($e->faultcode, 'Unable to connect to SQLA web service:  ' . $e->getMessage());
        }
    }

    public function getAppName()
    {
        return 'TriSano';
    }

    /**
     * Gets the Application ID corresponding to TriSano.
     * 
     * @return int
     */
    public function getAppId()
    {
        return $this->appId;
    }
    
    /**
     * Indicates whether this Application is enabled in EMSA.
     * 
     * @param \PDO $dbConn Connection to the EMSA database.
     * 
     * @return bool
     */
    public function getAppEnabled(\PDO $dbConn)
    {
        $appEnabled = false;
        
        try {
            $sql = "SELECT COUNT(*) FROM vocab_app WHERE id = :appId AND enabled IS TRUE;";
            $stmt = $dbConn->prepare($sql);
            $stmt->bindValue(':appId', $this->getAppId(), \PDO::PARAM_INT);
            
            if ($stmt->execute()) {
                if ((int) $stmt->fetchColumn(0) === 1) {
                    $appEnabled = true;
                }
            }
        } catch (Throwable $ex) {
            \Udoh\Emsa\Utils\ExceptionUtils::logException($ex);
        }
        
        return $appEnabled;
    }
    
    /**
     * Indicates whether to trigger notifications for messages assigned to this Application by EMSA.
     * 
     * @param \PDO $dbConn Connection to the EMSA database.
     * 
     * @return bool
     */
    public function getTriggerNotifications(\PDO $dbConn)
    {
        $appTriggerNotifications = false;
        
        try {
            $sql = "SELECT COUNT(*) FROM vocab_app WHERE id = :appId AND trigger_notifications IS TRUE;";
            $stmt = $dbConn->prepare($sql);
            $stmt->bindValue(':appId', $this->getAppId(), \PDO::PARAM_INT);
            
            if ($stmt->execute()) {
                if ((int) $stmt->fetchColumn(0) === 1) {
                    $appTriggerNotifications = true;
                }
            }
        } catch (Throwable $ex) {
            \Udoh\Emsa\Utils\ExceptionUtils::logException($ex);
        }
        
        return $appTriggerNotifications;
    }

    /**
     * Generate a new \Udoh\Emsa\Model\TrisanoRecord object.
     * 
     * @return \Udoh\Emsa\Model\TrisanoRecord
     */
    public function getNewAppRecord()
    {
        return new \Udoh\Emsa\Model\TrisanoRecord();
    }

    /**
     * Returns a URL pointing to a specified event in TriSano.
     * 
     * @param int $recordType One of \Udoh\Emsa\Constants\AppRecordType
     * @param string $recordNumber [Optional] Record Number of the target record
     * @param bool $readOnly [Optional; Default <b>TRUE</b>] If <b>FALSE</b>, link points to the 'Edit' view of the event.  Otherwise, link points to 'Show' view.
     * @param int $recordId Event ID of the target record.
     * 
     * @return string
     * @throws \BadMethodCallException if missing <i>recordId</i>
     * @throws \Exception if unexpected event type
     */
    public function getAppLinkToRecord($recordType, $recordNumber = null, $readOnly = true, $recordId = null)
    {
        $recordNumber = null;  // not needed for TriSano

        if (empty($recordId)) {
            throw new \BadMethodCallException('Missing TriSano event ID');
        }

        $recordUrl = BASE_NEDSS_URL;

        if ($recordType === \Udoh\Emsa\Constants\AppRecordType::MORBIDITY_EVENT) {
            $recordUrl .= 'cmrs/';
        } elseif ($recordType === \Udoh\Emsa\Constants\AppRecordType::CONTACT_EVENT) {
            $recordUrl .= 'contact_events/';
        } else {
            throw new \Exception('Unknown TriSano event type.');
        }

        $recordUrl .= (int) filter_var($recordId, FILTER_SANITIZE_NUMBER_INT);
        $recordUrl .= '/';

        if ($readOnly === false) {
            $recordUrl .= 'edit/';
        }

        return $recordUrl;
    }

    /**
     * Locate a Person in TriSano by their Person ID and return the resulting XML
     * 
     * @param int $personId TriSano Person ID
     * 
     * @return \Udoh\Emsa\Model\Person
     * 
     * @throws \Exception if <i>personId</i> is empty or the Person is not found
     * @throws \SoapFault on SOAP errors
     */
    public function getPerson($personId)
    {
        if (empty($personId)) {
            throw new \Exception('No Person ID specified');
        }

        $xml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
				<health_message>
					<system>TRISANO</system>
					<trisano_health>
						<interested_party_attributes>
							<person>
								<id>' . intval($personId) . '</id>
							</person>
						</interested_party_attributes>
					</trisano_health>
				</health_message>';

        $result = $this->client->findPerson(array("healthMessage" => $xml));

        $return = simplexml_load_string($result->return);

        $responseStatus = false;
        
        try {
            $responseStatus = $this->validateResponse($return)->getStatus();
        } catch (\Udoh\Emsa\Exceptions\AppClientEmptyResponse $ace) {
            \Udoh\Emsa\Utils\ExceptionUtils::logException($ace);
            $responseStatus = false;
        }

        if (!$responseStatus) {
            throw new \Exception('Specified person not found');
        }

        return $this->buildPersonFromXML($return->trisano_health);
    }

    /**
     * Search for people in TriSano by name and/or date of birth
     * 
     * @param string $firstName [Optional] Patient given name
     * @param string $lastName [Optional] Patient family name
     * @param \DateTime $birthDate [Optional] Patient DOB
     * @param array $conditions [Optional] To limit matched patients to those containing personConditions for a specific condition or conditions, provide a list of target condition names.
     * @param int $matchScore [Optional; Default 70] Limit matched patients to those with this match score or better.
     * 
     * @return \Udoh\Emsa\Model\PersonList
     * 
     * @throws \SoapFault on SOAP errors
     */
    public function searchPerson($firstName = null, $lastName = null, \DateTime $birthDate = null, array $conditions = array(), $matchScore = 70)
    {
        $searchPersonResults = new \Udoh\Emsa\Model\PersonList();

        $searchXml = new \SimpleXMLElement('<health_message></health_message>');
        $searchXml->addChild("system", "TRISANO");
        $searchXml->addChild("trisano_health")
                ->addChild('interested_party_attributes')
                ->addChild('person');

        if (empty($matchScore)) {
            $cleanMatchScore = 70;
        } else {
            $tmpMatchScore = (int) filter_var($matchScore, \FILTER_SANITIZE_NUMBER_INT);
            if (($tmpMatchScore >= 0) && ($tmpMatchScore <= 100)) {
                $cleanMatchScore = $tmpMatchScore;
            } else {
                $cleanMatchScore = 70;
            }
        }

        if (!empty($firstName)) {
            $searchXml->trisano_health->interested_party_attributes->person->addChild('first_name', (string) filter_var($firstName, \FILTER_SANITIZE_STRING));
        }
        if (!empty($lastName)) {
            $searchXml->trisano_health->interested_party_attributes->person->addChild('last_name', (string) filter_var($lastName, \FILTER_SANITIZE_STRING));
        }
        if (!is_null($birthDate)) {
            $searchXml->trisano_health->interested_party_attributes->person->addChild('birth_date', (string) $birthDate->format("Y-m-d"));
        }

        $searchXml->trisano_health->interested_party_attributes->person->addChild('match_score', $cleanMatchScore);

        if (!is_null($conditions) && is_array($conditions) && (count($conditions) > 0)) {
            foreach ($conditions as $searchCondition) {
                $searchXml->trisano_health->addChild('diseases')->disease_name = (string) $searchCondition;
            }
        }

        $result = $this->client->searchPerson(array("healthMessage" => $searchXml->asXML()));

        $xmlResponse = simplexml_load_string($result->return);

        foreach ($xmlResponse->trisano_health as $trisanoHealthObj) {
            $searchPersonResults->add($this->buildPersonFromXML($trisanoHealthObj));
        }

        return $searchPersonResults;
    }

    /**
     * Locate a TriSano event by ID and return the event.
     * 
     * @param int $recordId TriSano Event ID 
     * 
     * @return \Udoh\Emsa\Model\TrisanoRecord
     * 
     * @throws \Exception if <i>recordId</i> is empty or the event is not found
     * @throws \SoapFault on SOAP errors
     */
    public function getRecord($recordId)
    {
        if (empty($recordId)) {
            throw new \Exception('No Event ID specified');
        }

        $xml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
				<health_message>
					<system>TRISANO</system>
					<trisano_health>
						<events>
							<id>' . intval($recordId) . '</id>
						</events>
					</trisano_health>
				</health_message>';

        $result = $this->client->findEvent(array("healthMessage" => $xml));

        $return = simplexml_load_string($result->return);

        $responseStatus = false;
        
        try {
            $responseStatus = $this->validateResponse($return)->getStatus();
        } catch (\Udoh\Emsa\Exceptions\AppClientEmptyResponse $ace) {
            \Udoh\Emsa\Utils\ExceptionUtils::logException($ace);
            $responseStatus = false;
        }

        if (!$responseStatus) {
            throw new \Exception('Specified event not found');
        }

        $thisPersonId = (int) $return->trisano_health->interested_party_attributes->person->id;

        $newRecord = new \Udoh\Emsa\Model\TrisanoRecord();
        $newRecord->setAppRecordDocument($return);
        $newRecord->setPersonId($thisPersonId);

        return $newRecord;
    }

    /**
     * Updates an existing event in TriSano with new values
     * 
     * @param \SimpleXMLElement $updateXml Desired changes to the event in health_message XML format
     * 
     * @return \SimpleXMLElement TriSano SOAP API results
     * 
     * @throws \Exception if <i>updateXml</i> is empty
     * @throws \SoapFault on SOAP errors
     */
    public function updateRecord(\SimpleXMLElement $updateXml)
    {
        if (empty($updateXml) || \EmsaUtils::emptyTrim($updateXml->asXML())) {
            throw new \Exception('Could not update NEDSS event:  NEDSS XML missing');
        }

        $wrappedXml = self::addHealthMessageWrapper($updateXml);
        $result = $this->client->updateCmr(array("healthMessage" => $wrappedXml));
        $updateCmrReturn = simplexml_load_string($result->return);

        return $updateCmrReturn;
    }

    /**
     * Creates a new CMR in TriSano
     * 
     * @param \SimpleXMLElement $addXml New CMR in health_message XML format
     * 
     * @return \SimpleXMLElement TriSano SOAP API results
     * 
     * @throws \Exception if <i>addXml</i> is empty
     * @throws \SoapFault on SOAP errors
     */
    public function addRecord(\SimpleXMLElement $addXml)
    {
        if (empty($addXml) || \EmsaUtils::emptyTrim($addXml->asXML())) {
            throw new \Exception('Could not create new NEDSS CMR:  NEDSS XML missing');
        }

        $wrappedXml = self::addHealthMessageWrapper($addXml);
        $result = $this->client->addCmr(array("healthMessage" => $wrappedXml));
        $addCmrReturn = simplexml_load_string($result->return);

        return $addCmrReturn;
    }

    /**
     * Returns the users defined by TriSano for a give role and jurisdiction.
     * 
     * @param int $roleId Filter users by role ID.
     * @param int $jurisdictionId Filter users by jurisdiction ID.
     * 
     * @return \Udoh\Emsa\Model\AppUserList
     * 
     * @throws \SoapFault on SOAP errors
     */
    public function getUsers($roleId, $jurisdictionId)
    {
        $userList = new \Udoh\Emsa\Model\AppUserList();

        $xmlObj = new \SimpleXMLElement('<health_message/>');
        $xmlObj->addChild('system', 'TRISANO');
        $xmlObj->addChild('trisano_health')->addChild('role_memberships');
        $xmlObj->trisano_health->role_memberships->addChild('role_id', (int) filter_var($roleId, \FILTER_SANITIZE_NUMBER_INT));
        $xmlObj->trisano_health->role_memberships->addChild('jurisdiction_id', (int) filter_var($jurisdictionId, \FILTER_SANITIZE_NUMBER_INT));

        $result = $this->client->getUsers(array("healthMessage" => $xmlObj->asXML()));
        $return = simplexml_load_string($result->return);

        // full disclosure:  this is a total hack due to TriSano's weird way it returns e-mail addresses.
        // since the only current use for the return from this method is to get a list of e-mail addresses,
        // we're not even bothering with filling in user names, only creating a bunch of bogus users with 
        // e-mail addresses to fill an ApplicationUserList object properly for the interface.
        $foundAddresses = $return->xpath("trisano_health/users_attributes/email_addresses");

        foreach ($foundAddresses as $foundAddress) {
            $userList->add((string) filter_var($foundAddress->email_address, \FILTER_SANITIZE_EMAIL));
        }

        return $userList;
    }

    /**
     * Get a specific TriSano user by user ID.
     * 
     * Only one of <i>id</i> or <i>uid</i> is required.  If both or neither are supplied, and Exception will be thrown.
     * 
     * @param int $id [Optional] User's numeric ID in the current application.
     * @param string $uid [Optional] User's string-based ID in the current application.
     * 
     * @return \Udoh\Emsa\Model\AppUser
     * @throws \InvalidArgumentException
     */
    public function getUser($id = null, $uid = null)
    {
        if ((empty($id) && empty($uid)) || (!empty($id) && !empty($uid))) {
            throw new \InvalidArgumentException;
        }

        $user = new \Udoh\Emsa\Model\AppUser();

        $xmlObj = new \SimpleXMLElement('<health_message/>');
        $xmlObj->addChild('system', 'TRISANO');

        if (!empty($id)) {
            $xmlObj->addChild('trisano_health')->addChild('role_memberships')->addChild('user_id', (int) filter_var($id, \FILTER_SANITIZE_NUMBER_INT));
            $result = $this->client->getUsers(array("healthMessage" => $xmlObj->asXML()));
        } else {
            $xmlObj->addChild('trisano_health')->addChild('users')->addChild('uid', (string) filter_var($uid, \FILTER_SANITIZE_STRING));
            $result = $this->client->getUserRoles(array("healthMessage" => $xmlObj->asXML()));
        }

        $return = simplexml_load_string($result->return);

        if (!empty($id)) {
            if (isset($return->trisano_health->users_attributes->users[1]->givenName) && (strlen($return->trisano_health->users_attributes->users[1]->givenName) > 0)) {
                $user->setFullName((string) $return->trisano_health->users_attributes->users[1]->givenName);
            } else {
                $tmpFullName = '';

                if (isset($return->trisano_health->users_attributes->users[1]->firstName) && (strlen($return->trisano_health->users_attributes->users[1]->firstName) > 0)) {
                    $tmpFullName .= (string) $return->trisano_health->users_attributes->users[1]->firstName;
                }

                $tmpFullName .= ' ';

                if (isset($return->trisano_health->users_attributes->users[1]->lastName) && (strlen($return->trisano_health->users_attributes->users[1]->lastName) > 0)) {
                    $tmpFullName .= (string) $return->trisano_health->users_attributes->users[1]->lastName;
                }

                $user->setFullName(trim($tmpFullName));
            }
        } else {
            if (isset($return->trisano_health->users[1]->givenName) && (strlen($return->trisano_health->users[1]->givenName) > 0)) {
                $user->setFullName((string) $return->trisano_health->users[1]->givenName);
            } else {
                $tmpFullName = '';

                if (isset($return->trisano_health->users[1]->firstName) && (strlen($return->trisano_health->users[1]->firstName) > 0)) {
                    $tmpFullName .= (string) $return->trisano_health->users[1]->firstName;
                }

                $tmpFullName .= ' ';

                if (isset($return->trisano_health->users[1]->lastName) && (strlen($return->trisano_health->users[1]->lastName) > 0)) {
                    $tmpFullName .= (string) $return->trisano_health->users[1]->lastName;
                }

                $user->setFullName(trim($tmpFullName));
            }
        }

        return $user;
    }

    /**
     * Get TriSano user roles
     * 
     * @return \Udoh\Emsa\Model\AppRoleList Set of TriSano roles.
     * 
     * @throws \SoapFault on SOAP errors
     * @throws \Exception on TriSano query agent errors
     */
    public function getRoles()
    {
        $roleList = new \Udoh\Emsa\Model\AppRoleList();

        $xml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
				<health_message>
					<system>TRISANO</system>
					<trisano_health></trisano_health>
				</health_message>';

        $result = $this->client->getRoles(array("healthMessage" => $xml));
        $return = simplexml_load_string($result->return);

        $responseStatus = false;
        
        try {
            $responseStatus = $this->validateResponse($return)->getStatus();
        } catch (\Udoh\Emsa\Exceptions\AppClientEmptyResponse $ace) {
            \Udoh\Emsa\Utils\ExceptionUtils::logException($ace);
            $responseStatus = false;
        }

        if (!$responseStatus) {
            throw new \Exception('Roles not found');
        }

        if (isset($return->trisano_health->roles) && (count($return->trisano_health->roles) > 0)) {
            foreach ($return->trisano_health->roles as $roleItem) {
                $roleList->add((int) $roleItem->id, (string) $roleItem->roleName);
            }
        }

        return $roleList;
    }

    /**
     * Get TriSano user roles for a specific user
     * 
     * @param string $userId User's UMDID.
     * @return \Udoh\Emsa\Model\AppRoleList Set of TriSano roles for this user.
     * 
     * @throws \SoapFault on SOAP errors
     * @throws \Exception on TriSano query agent errors
     */
    public function getUserRoles($userId)
    {
        $roleList = new \Udoh\Emsa\Model\AppRoleList();

        $xml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
				<health_message>
					<system>TRISANO</system>
					<trisano_health>
						<users>
							<uid>' . trim($userId) . '</uid>
						</users>
					</trisano_health>
				</health_message>';

        $result = $this->client->getUserRoles(array("healthMessage" => $xml));
        $return = simplexml_load_string($result->return);

        if (isset($return->trisano_health->role_memberships) && (count($return->trisano_health->role_memberships) > 0)) {
            foreach ($return->trisano_health->role_memberships as $health) {
                $roleList->add((int) $health->role_id, '');
            }
        }

        return $roleList;
    }

    /**
     * Get the jurisdictions configured for TriSano.
     * 
     * Since no SQLA method implements a way to retrieve the jurisdictions via SOAP, we must do a native database call here.
     * 
     * @param \PDO $dbConn Connection to the TriSano database.
     * @return array
     */
    public function getJurisdictions(\PDO $dbConn = null)
    {
        $jurisdictions = array();

        try {
            $sql = 'SELECT p.id AS id, p.short_name AS "shortName"
					FROM places p
					INNER JOIN places_types t ON (p.id = t.place_id)
					INNER JOIN codes c ON (t.type_id = c.id)
					WHERE c.the_code = :code
					AND c.code_name = :codeName
					ORDER BY p.short_name;';
            $stmt = $dbConn->prepare($sql);
            $stmt->bindValue(':code', 'J', \PDO::PARAM_STR);
            $stmt->bindValue(':codeName', 'placetype', \PDO::PARAM_STR);

            if ($stmt->execute()) {
                while ($row = $stmt->fetchObject()) {
                    $jurisdictions[(int) filter_var($row->id, \FILTER_SANITIZE_NUMBER_INT)] = trim(filter_var($row->shortName, \FILTER_SANITIZE_STRING));
                }
            }
        } catch (\PDOException $e) {
            \Udoh\Emsa\Utils\ExceptionUtils::logException($e);
            return $jurisdictions;
        }

        return $jurisdictions;
    }

    /**
     * Returns the code/value data for a specific TriSano code table.
     * 
     * @param string $codesetName Name of table to retrieve.
     * 
     * @return \Udoh\Emsa\Model\CodedDataResult[] Array of \Udoh\Emsa\Model\CodedDataResult objects for each sub-codeset found in the specified <i>codesetName</i>.
     * 
     * @throws \SoapFault on SOAP errors
     * @throws \Exception on TriSano query agent errors
     */
    public function getCodedData($codesetName)
    {
        $codedDataResultSet = array();
        $stupidExternalCodesArrayOfWonders = array();

        $xml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
				<health_message>
					<system>TRISANO</system>
					<trisano_health>
						<dump_table>' . $codesetName . '</dump_table>
					</trisano_health>
				</health_message>';

        $tableDataRaw = $this->client->getTableData(array("healthMessage" => $xml));
        $tableDataXml = simplexml_load_string($tableDataRaw->return);

        if ($codesetName == 'external_codes') {
            // dumping external_codes table, need to iterate through all of the sub-tables & return a \Udoh\Emsa\Model\CodedDataResult for each
            foreach ($tableDataXml->trisano_health->external_codes as $externalCodeNode) {
                if (!isset($stupidExternalCodesArrayOfWonders[(string) $externalCodeNode->code_name]) || !is_array($stupidExternalCodesArrayOfWonders[(string) $externalCodeNode->code_name])) {
                    $stupidExternalCodesArrayOfWonders[(string) $externalCodeNode->code_name] = array();
                }

                if ((trim($externalCodeNode->live) == 'true') && !isset($externalCodeNode->deleted_at)) {
                    $stupidExternalCodesArrayOfWonders[(string) $externalCodeNode->code_name][] = array(
                        'id' => (int) $externalCodeNode->id,
                        'codedValue' => (string) $externalCodeNode->the_code,
                        'codeDescription' => (string) $externalCodeNode->code_description
                    );
                }
            }

            foreach ($stupidExternalCodesArrayOfWonders as $codeName => $codeNameResults) {
                $currentCodedDataResults = new \Udoh\Emsa\Model\CodedDataResult($codeName);

                foreach ($codeNameResults as $codeNameResultItem) {
                    $currentCodedDataResults->addEntry($codeNameResultItem['id'], $codeNameResultItem['codeDescription'], $codeNameResultItem['codedValue']);
                }

                $codedDataResultSet[] = clone $currentCodedDataResults;
                unset($currentCodedDataResults);
            }
        } else {
            // dumping table other than external_codes
            $dumpedArray = explode('|', trim($tableDataXml->trisano_health->dump_table));
            $tempArray = array();

            foreach ($dumpedArray as $dumpItem) {
                $thisTempArray = array();
                $thisTempArray = explode('=', $dumpItem);
                $tempArray[intval(trim($thisTempArray[0]))] = isset($thisTempArray[1]) ? trim($thisTempArray[1]) : '';
            }

            if (isset($tempArray[0])) {
                unset($tempArray[0]);
            }

            if (count($tempArray) > 0) {
                $currentCodedDataResults = new \Udoh\Emsa\Model\CodedDataResult($codesetName);

                foreach ($tempArray as $tempArrayCodeId => $tempArrayCodeDescription) {
                    $currentCodedDataResults->addEntry($tempArrayCodeId, $tempArrayCodeDescription);
                }

                $codedDataResultSet[] = clone $currentCodedDataResults;
                unset($currentCodedDataResults);
            }
        }

        return $codedDataResultSet;
    }

    /**
     * Returns a list of the coded table names that need to be dumped for TriSano.
     * 
     * @return array
     */
    public function getCodedDataTables()
    {
        $dumpableTables = array(
            'external_codes',
            'diseases',
            'organisms',
            'common_test_types'
        );

        return $dumpableTables;
    }

    /**
     * Find TriSano ID(s) for given search strings and return as an health_message XML object
     * 
     * @param \SimpleXMLElement $findIdXml health_message XML object containing search parameters
     * 
     * @return \SimpleXMLElement XML object containing a health_message object
     * 
     * @throws \Exception if <i>findIdXml</i> is empty
     * @throws \SoapFault on SOAP errors
     */
    public function findId(\SimpleXMLElement $findIdXml)
    {
        if (empty($findIdXml) || \EmsaUtils::emptyTrim($findIdXml->asXML())) {
            throw new \Exception('Could not execute search:  findId XML is missing/empty');
        }

        $result = $this->client->findId(array("healthMessage" => $findIdXml->asXML()));

        return simplexml_load_string($result->return);
    }

    /**
     * Build a \Udoh\Emsa\Model\Person object from trisano_health XML.
     * 
     * @param \SimpleXMLElement $trisanoHealthObj
     * 
     * @return \Udoh\Emsa\Model\Person
     */
    private function buildPersonFromXML(\SimpleXMLElement $trisanoHealthObj)
    {
        $thisPerson = new \Udoh\Emsa\Model\Person();
        $thisPerson->setPersonId((int) $trisanoHealthObj->interested_party_attributes->person->id);
        $thisPerson->setLastName((string) $trisanoHealthObj->interested_party_attributes->person->last_name);
        $thisPerson->setFirstName((string) $trisanoHealthObj->interested_party_attributes->person->first_name);
        $thisPerson->setMiddleName((string) $trisanoHealthObj->interested_party_attributes->person->middle_name);

        if (isset($trisanoHealthObj->interested_party_attributes->person->birth_gender_id) && !empty($trisanoHealthObj->interested_party_attributes->person->birth_gender_id) && isset($_SESSION[EXPORT_SERVERNAME]['codedData']['TriSano']['gender'][intval($trisanoHealthObj->interested_party_attributes->person->birth_gender_id)])) {
            $thisPerson->setGender(\Udoh\Emsa\Utils\CodedDataUtils::getCodeDescriptionFromId($this, 'gender', (int) filter_var($trisanoHealthObj->interested_party_attributes->person->birth_gender_id, FILTER_SANITIZE_NUMBER_INT)));
        }

        if (!empty($trisanoHealthObj->interested_party_attributes->people_races)) {
            foreach ($trisanoHealthObj->interested_party_attributes->people_races as $foundRace) {
                if (!empty($foundRace->serial_version_uid->race_id)) {
                    $thisPerson->addRace(\Udoh\Emsa\Utils\CodedDataUtils::getCodeDescriptionFromId($this, 'race', (int) filter_var($foundRace->serial_version_uid->race_id, FILTER_SANITIZE_NUMBER_INT)));
                }
            }
        }

        if (isset($trisanoHealthObj->interested_party_attributes->person->ethnicity_id) && !empty($trisanoHealthObj->interested_party_attributes->person->ethnicity_id) && isset($_SESSION[EXPORT_SERVERNAME]['codedData']['TriSano']['gender'][intval($trisanoHealthObj->interested_party_attributes->person->ethnicity_id)])) {
            $thisPerson->setEthnicity(\Udoh\Emsa\Utils\CodedDataUtils::getCodeDescriptionFromId($this, 'ethnicity', (int) filter_var($trisanoHealthObj->interested_party_attributes->person->ethnicity_id, FILTER_SANITIZE_NUMBER_INT)));
        }

        if (isset($trisanoHealthObj->interested_party_attributes->person->birth_date) && !empty($trisanoHealthObj->interested_party_attributes->person->birth_date)) {
            $thisPerson->setDateOfBirth(\Udoh\Emsa\Utils\DateTimeUtils::createMixed((string) $trisanoHealthObj->interested_party_attributes->person->birth_date));
        }

        if (isset($trisanoHealthObj->interested_party_attributes->person->date_of_death) && !empty($trisanoHealthObj->interested_party_attributes->person->date_of_death)) {
            $thisPerson->setDateOfDeath(\Udoh\Emsa\Utils\DateTimeUtils::createMixed((string) $trisanoHealthObj->interested_party_attributes->person->date_of_death));
        }

        if (!empty($trisanoHealthObj->addresses)) {
            foreach ($trisanoHealthObj->addresses as $foundAddress) {
                $thisAddress = new \Udoh\Emsa\Model\Address();
                $thisAddress->setStreet((string) $foundAddress->street_name);
                $thisAddress->setUnitNumber((string) $foundAddress->unit_number);
                $thisAddress->setCity((string) $foundAddress->city);
                $thisAddress->setState((string) \Udoh\Emsa\Utils\CodedDataUtils::getCodedValueFromId($this, 'state', (int) filter_var($foundAddress->state_id, FILTER_SANITIZE_NUMBER_INT)));
                $thisAddress->setPostalCode((string) $foundAddress->postal_code);
                $thisPerson->addAddress($thisAddress);
            }
        }

        if (!empty($trisanoHealthObj->interested_party_attributes->telephones)) {
            foreach ($trisanoHealthObj->interested_party_attributes->telephones as $foundTelephone) {
                $thisTelecom = new \Udoh\Emsa\Model\Telecom();
                $thisTelecom->setAreaCode((string) $foundTelephone->area_code);
                $thisTelecom->setLocalNumber((string) $foundTelephone->phone_number);
                $thisTelecom->setType(\Udoh\Emsa\Model\Telecom::TYPE_PHONE);
                $thisTelecom->setUse(\Udoh\Emsa\Model\Telecom::USE_UNKNOWN);
                $thisPerson->addTelecom($thisTelecom);
            }
        }

        $thisPerson->setMatchScore((float) $trisanoHealthObj->interested_party_attributes->person->match_score);

        foreach ($trisanoHealthObj->events as $foundEvent) {
            $thisPerson->addRecord($this->getRecord((int) $foundEvent->id));
        }

        return $thisPerson;
    }

    /**
     * Wrap the generated TriSano XML in a healthMessage wrapper & prepare for TriSano SOAP API
     * 
     * @param \SimpleXMLElement $nedssXml TriSano XML being processed
     * 
     * @return string Wrapped XML string ready for consumption by SOAP function
     */
    public static function addHealthMessageWrapper(\SimpleXMLElement $nedssXml)
    {
        $wrappedXmlStr = str_ireplace(
                '<?xml version="1.0" encoding="UTF-8"?>', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>', str_ireplace(
                        '</trisano_health>', '</trisano_health></health_message>', str_ireplace(
                                '<trisano_health>', '<health_message><username>' . EPITRAX_AUTH_ELR_UID . '</username><system>TRISANO</system><trisano_health>', str_ireplace(
                                        "\xC2\xA0", ' ', $nedssXml->asXML()
                                )
                        )
                )
        );

        return $wrappedXmlStr;
    }

    /**
     * Validates the response from SQLA and returns its status.
     * 
     * @param \SimpleXMLElement $responseXML XML response from SQLA
     * 
     * @return \Udoh\Emsa\Model\ClientResponse
     * 
     * @throws \Udoh\Emsa\Exceptions\AppClientEmptyResponse if <i>responseXML</i> is null or empty
     */
    public function validateResponse(\SimpleXMLElement $responseXML = null)
    {
        if (empty($responseXML->status_message)) {
            throw new \Udoh\Emsa\Exceptions\AppClientEmptyResponse();
        }

        $sqlaResponse = new \Udoh\Emsa\Model\ClientResponse();

        $statusBool = true;

        foreach ($responseXML->status_message as $statusObj) {
            if ((int) $statusObj->status === 100) {
                $statusBool = $statusBool && true;
            } else {
                $statusBool = $statusBool && false;
                $sqlaResponse->addError((string) $statusObj->action, (string) $statusObj->status, (string) $statusBool->error_message);
            }
        }

        $sqlaResponse->setStatus($statusBool);

        return $sqlaResponse;
    }

}

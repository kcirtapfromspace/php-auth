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

use DateTime;
use Exception;
use PDO;
use SimpleXMLElement;
use Udoh\Emsa\Exceptions\AppClientEmptyResponse;
use Udoh\Emsa\Exceptions\AppClientNoValidHosts;
use Udoh\Emsa\Exceptions\PeopleSearchTooManyResults;
use Udoh\Emsa\Model\AppRecord;
use Udoh\Emsa\Model\AppRoleList;
use Udoh\Emsa\Model\AppUser;
use Udoh\Emsa\Model\AppUserList;
use Udoh\Emsa\Model\ClientResponse;
use Udoh\Emsa\Model\CodedDataResult;
use Udoh\Emsa\Model\Person;
use Udoh\Emsa\Model\PersonList;

/**
 * Defines methods to be implemented by Application-specific clients.
 * 
 * @author Josh Ridderhoff <jridderhoff@utah.gov>
 */
interface AppClientInterface
{
    
    /**
     * Returns the name of the Application this client is designed to work with.
     * 
     * @return string
     */
    public function getAppName(): string;
    
    /**
     * Returns the ID of the Application this client represents.
     * 
     * @return int
     */
    public function getAppId(): int;
    
    /**
     * Indicates whether this Application is enabled in EMSA.
     * 
     * @param PDO $dbConn Connection to the EMSA database.
     * 
     * @return bool
     */
    public function getAppEnabled(PDO $dbConn): bool;
    
    /**
     * Indicates whether to trigger notifications for messages assigned to this Application by EMSA.
     * 
     * @param PDO $dbConn Connection to the EMSA database.
     * 
     * @return bool
     */
    public function getTriggerNotifications(PDO $dbConn): bool;
    
    /**
     * Generate a new Udoh\Emsa\Model\AppRecord object for this Application.
     * 
     * @return AppRecord
     */
    public function getNewAppRecord(): AppRecord;
    
    /**
     * Returns a URL pointing to a specified record in the current Application.
     *
     * @param int    $recordType   One of \Udoh\Emsa\Constants\AppRecordType
     * @param string $recordNumber [Optional] Record Number of the target record
     * @param bool   $readOnly     [Optional; Default <b>TRUE</b>] If <b>FALSE</b> and Application supports the feature, link points to a modifiable view of the record.  Otherwise, link points to read-only view.
     * @param int    $recordId     [Optional] If needed, the internal ID of the record to retrieve.
     *
     * @return string
     */
    public function getAppLinkToRecord(int $recordType, ?string $recordNumber = null, ?bool $readOnly = true, ?int $recordId = null): string;
    
    /**
     * Returns a URL pointing to a specified person in the current Application.
     *
     * @param int  $personId ID of the target person.
     * @param bool $readOnly [Optional; Default <b>TRUE</b>] If <b>FALSE</b> and Application supports the feature, link points to an editable view of the person.  Otherwise, link points to read-only view.
     *
     * @return string
     */
    public function getAppLinkToPerson(int $personId, ?bool $readOnly = true): string;

    /**
     * Returns a specific person record by ID.
     *
     * @param int $personId
     *
     * @return Person
     */
    public function getPerson(int $personId): Person;

    /**
     * Returns person records matching specified search parameters.
     *
     * @param string   $firstName  [Optional] Patient given name
     * @param string   $lastName   [Optional] Patient family name
     * @param string   $middleName [Optional] Patient middle name
     * @param DateTime $birthDate  [Optional] Patient DOB
     * @param string   $birthSex   [Optional] Patient birth sex
     * @param array    $conditions [Optional] To limit matched patients to those containing personConditions for a specific condition or conditions, provide a list of target condition names.
     * @param int      $matchScore [Optional; Default 60] Limit matched patients to those with this match score or better.
     *
     * @return PersonList
     *
     * @throws PeopleSearchTooManyResults
     * @throws Exception In case of HTTP error
     */
    public function searchPerson(?string $firstName = null, ?string $lastName = null, ?string $middleName = null, ?DateTime $birthDate = null, ?string $birthSex = null, ?array $conditions = array(), ?int $matchScore = 60): PersonList;

    /**
     * Get a specific event, case, or other target record by ID.
     *
     * @param int $recordId
     *
     * @return AppRecord
     */
    public function getRecord(int $recordId): AppRecord;

    /**
     * Updates a specific event, case, or other target record.
     *
     * @param SimpleXMLElement $updateXml
     *
     * @return SimpleXMLElement
     */
    public function updateRecord(SimpleXMLElement $updateXml): SimpleXMLElement;

    /**
     * Adds a new event, case, or other record type.
     *
     * @param SimpleXMLElement $addXml
     *
     * @return SimpleXMLElement
     */
    public function addRecord(SimpleXMLElement $addXml): SimpleXMLElement;
    
    /**
     * Returns a list of users defined in the current application.
     *
     * @param int $roleId         Filter users by role ID
     * @param int $jurisdictionId Filter users by jurisdiction ID
     *
     * @return AppUserList
     */
    public function getUsers(int $roleId, int $jurisdictionId): AppUserList;
    
    /**
     * Get a specific user from the current application.
     * 
     * One of <i>id</i> or <i>uid</i> is required, although the implementing application might not support both.  See specific application documentation
     *
     * @param int    $id  [Optional] User's numeric ID in the current application.
     * @param string $uid [Optional] User's string-based ID in the current application.
     *
     * @return AppUser
     */
    public function getUser(?int $id = null, ?string $uid = null): AppUser;

    /**
     * Returns the available user roles from the current application.
     * 
     * @return AppRoleList Set of user roles for the current application.
     */
    public function getRoles(): AppRoleList;

    /**
     * Returns the user roles assigned to the specified user for the current application.
     *
     * @param string $userId
     *
     * @return AppRoleList Set of user roles assigned to the specified user for the current application.
     */
    public function getUserRoles(string $userId): AppRoleList;
    
    /**
     * Returns the configured jurisdictions for this application.
     *
     * @param PDO $dbConn [Optional] Database connection for the application, if required.
     *
     * @return array
     */
    public function getJurisdictions(?PDO $dbConn = null): array;

    /**
     * Returns the code/value data for a specific codeset from the current application.
     *
     * @param string $codesetName
     *
     * @return CodedDataResult[] Array of Udoh\Emsa\Model\CodedDataResult objects for each sub-codeset found in the specified <i>codesetName</i>.
     */
    public function getCodedData(string $codesetName): array;
    
    /**
     * Returns a list of the coded table names that need to be dumped for this application.
     * 
     * @return array
     */
    public function getCodedDataTables(): array;
    
    /**
     * Create an AppUser object from this specific application's XML representation of the user.
     *
     * @param SimpleXMLElement $appUserXML
     *
     * @return AppUser
     */
    public static function createUserFromXML(SimpleXMLElement $appUserXML = null): AppUser;
    
    /**
     * Validates the response from the current application client and return its status in a \Udoh\Emsa\Model\ClientResponse object.
     * 
     * @param SimpleXMLElement $responseXML XML response from the client.
     * 
     * @return ClientResponse
     * 
     * @throws AppClientEmptyResponse if <i>responseXML</i> is null or empty
     */
    public function validateResponse(SimpleXMLElement $responseXML = null): ClientResponse;

    /**
     * @param string $badHost
     *
     * @return string
     *
     * @throws AppClientNoValidHosts
     */
    public function getServiceURLRoundRobin($badHost = null);
}

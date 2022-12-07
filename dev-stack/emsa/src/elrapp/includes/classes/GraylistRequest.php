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

use Udoh\Emsa\Utils\DateTimeUtils;

/**
 * Functionality associated with querying the EMSA graylist for previously-unprocessable 
 * ELR messages (negative results, informative-only tests, etc.) and updating records in the configured application.
 *
 * @author Josh Ridderhoff <jridderhoff@utah.gov>
 */
class GraylistRequest
{

    /** @var PDO */
    protected $dbConn = null;
    /** @var string */
    protected $firstName;
    /** @var string */
    protected $lastName;
    /** @var string */
    protected $middleName;

    /** @var DateTime */
    protected $dob;

    /** @var DateTime */
    protected $eventDate;
    /** @var string */
    protected $condition;
    /** @var int */
    protected $appRecordId;
    /** @var int */
    protected $requestId = 0;
    /** @var int */
    protected $requestStatus;

    /** List of valid statuses for Graylist requests and messages
     * @var int[] */
    protected $validStatusList = array(
        GRAY_PENDING_STATUS,
        GRAY_PROCESSED_STATUS,
        GRAY_UNPROCESSABLE_STATUS,
        GRAY_EXCEPTION_STATUS
    );

    /**
     * Create a new <b>GraylistRequest</b> object.
     *
     * If <i>requestId</i> is specified, loads existing request from database.
     * Otherwise, attempts to create a new request ID using <i>appRecordId</i>, <i>firstName</i>, <i>lastName</i>, <i>dob</i> and <i>condition</i>.
     *
     * @param PDO         $dbConn      PDO connection to EMSA database
     * @param int         $appRecordId ID of the record that triggered this graylist request
     * @param string      $condition   Original event's condition
     * @param int         $requestId   Existing Request ID
     * @param string      $firstName   Patient's first name
     * @param string      $lastName    Patient's last name
     * @param string|null $middleName  Patient's middle name
     * @param DateTime    $dob         Patient's date of birth
     * @param DateTime    $eventDate   Event date for the existing record
     *
     * @throws Exception if valid database connection information is not specified.
     */
    public function __construct(PDO $dbConn, int $appRecordId, string $condition, ?int $requestId = null, ?string $firstName = null, ?string $lastName = null, ?string $middleName = null, ?DateTime $dob = null, ?DateTime $eventDate = null)
    {
        if (empty($dbConn)) {
            throw new Exception('Missing database connection information.');
        }

        $this->dbConn = $dbConn;

        if (!empty($requestId)) {
            $this->getExistingRequest($requestId);
        } else {
            $this->startNewRequest($appRecordId, $condition, $firstName, $lastName, $middleName, $dob, $eventDate);
        }
    }

    /**
     * Set the ID of the triggering record.
     *
     * @param int $appRecordId Application-specific record ID that triggered the request
     *
     * @return GraylistRequest
     */
    public function setAppRecordId(int $appRecordId): GraylistRequest
    {
        if (!empty($appRecordId) && ((int) $appRecordId > 0)) {
            $this->appRecordId = (int) $appRecordId;
        }

        return $this;
    }

    /**
     * Gets the ID of the triggering record.
     * 
     * @return int
     */
    public function getAppRecordId(): int
    {
        return $this->appRecordId;
    }

    /**
     * Set the patient's first name
     *
     * @param string $firstName Patient's first name
     *
     * @return GraylistRequest
     */
    public function setFirstName(?string $firstName = null): GraylistRequest
    {
        if (!empty($firstName)) {
            $this->firstName = trim($firstName);
        }

        return $this;
    }

    /**
     * Get the patient's first name
     *  
     * @return string|null Patient's first name, or <b>NULL</b> if first name is not set.
     */
    public function getFirstName(): ?string
    {
        if (empty($this->firstName)) {
            return null;
        } else {
            return $this->firstName;
        }
    }

    /**
     * Set the patient's last name
     *
     * @param string|null $lastName Patient's last name
     *
     * @return GraylistRequest
     */
    public function setLastName(?string $lastName = null): GraylistRequest
    {
        if (!empty($lastName)) {
            $this->lastName = trim($lastName);
        }

        return $this;
    }

    /**
     * Get the patient's last name
     *  
     * @return string|null Patient's last name, or <b>NULL</b> if last name is not set.
     */
    public function getLastName(): ?string
    {
        if (empty($this->lastName)) {
            return null;
        } else {
            return $this->lastName;
        }
    }

    /**
     * Set the patient's middle name
     *
     * @param string|null $middleName Patient's middle name
     *
     * @return GraylistRequest
     */
    public function setMiddleName(?string $middleName = null): GraylistRequest
    {
        if (!empty($middleName)) {
            $this->middleName = trim($middleName);
        }

        return $this;
    }

    /**
     * Get the patient's middle name
     *
     * @return string|null Patient's middle name, or <b>NULL</b> if middle name is not set.
     */
    public function getMiddleName(): ?string
    {
        if (empty($this->middleName)) {
            return null;
        } else {
            return $this->middleName;
        }
    }

    /**
     * Set the patient's date of birth
     *
     * @param DateTime $dob Patient's DOB
     *
     * @return GraylistRequest
     */
    public function setDob(?DateTime $dob = null): GraylistRequest
    {
        if (!empty($dob)) {
            $this->dob = $dob;
        }

        return $this;
    }

    /**
     * Gets the patient's date of birth.
     * 
     * @param bool $formatted [Optional]<br>Default <b>FALSE</b>.  If <b>TRUE</b>, returns date as a formatted string (specified by <i>formatStr</i>).
     * @param string $formatStr [Optional]<br>Valid PHP date format string/constant to return the date as.  Default <b>DateTime::RFC3339</b>.
     * 
     * @return string|DateTime|null Formatted string if <i>formatted</i> is <b>TRUE</b>, <b>DateTime</b> object otherwise.<br>Returns <b>NULL</b> if DOB is not set.
     */
    public function getDob(?bool $formatted = false, ?string $formatStr = DateTime::RFC3339)
    {
        return DateTimeUtils::getDateFormatted($this->dob, $formatted, $formatStr);
    }

    /**
     * Set the existing event's Event Date
     *
     * @param DateTime $eventDate Record's Event Date
     *
     * @return GraylistRequest
     */
    public function setEventDate(?DateTime $eventDate = null): GraylistRequest
    {
        if (!empty($eventDate)) {
            $this->eventDate = $eventDate;
        }

        return $this;
    }

    /**
     * Gets the existing event's Event Date
     * 
     * @param bool $formatted [Optional]<br>Default <b>FALSE</b>.  If <b>TRUE</b>, returns date as a formatted string (specified by <i>formatStr</i>).
     * @param string $formatStr [Optional]<br>Valid PHP date format string/constant to return the date as.  Default <b>DateTime::RFC3339</b>.
     * 
     * @return string|DateTime|null Formatted string if <i>formatted</i> is <b>TRUE</b>, <b>DateTime</b> object otherwise.<br>Returns <b>NULL</b> if Event Date is not set.
     */
    public function getEventDate(?bool $formatted = false, ?string $formatStr = DateTime::RFC3339)
    {
        return DateTimeUtils::getDateFormatted($this->eventDate, $formatted, $formatStr);
    }

    /**
     * Set the existing event's condition
     *
     * @param string|null $condition Original event's condition
     *
     * @return GraylistRequest
     */
    public function setCondition(?string $condition = null): GraylistRequest
    {
        if (!empty($condition)) {
            $this->condition = trim($condition);
        }

        return $this;
    }

    /**
     * Get the existing event's condition
     * 
     * @return string|null Original event's condition, or <b>NULL</b> if condition is not set.
     */
    public function getCondition(): ?string
    {
        if (empty($this->condition)) {
            return null;
        } else {
            return $this->condition;
        }
    }

    /**
     * Set the status of the current request
     *
     * @param int $requestStatus Status ID to set.
     *
     * @return GraylistRequest
     */
    protected function setRequestStatus(int $requestStatus): GraylistRequest
    {
        if (!empty($requestStatus) && in_array($requestStatus, $this->validStatusList)) {
            $this->requestStatus = intval($requestStatus);
        }

        return $this;
    }

    /**
     * Change the status of an existing request.
     *
     * @param int $requestStatus Status ID to change to.
     *
     * @return GraylistRequest
     * @throws PDOException on database errors.
     */
    public function updateRequestStatus(int $requestStatus): GraylistRequest
    {
        if (!empty($requestStatus) && in_array($requestStatus, $this->validStatusList)) {
            $this->setRequestStatus($requestStatus);

            $sql = 'UPDATE graylist_requests
						SET status = :status
						WHERE id = :request_id;';
            $stmt = $this->dbConn->prepare($sql);
            $stmt->bindValue(':request_id', $this->requestId, PDO::PARAM_INT);
            $stmt->bindValue(':status', $this->requestStatus, PDO::PARAM_INT);

            $stmt->execute();
        }

        return $this;
    }

    /**
     * Get the Request ID for this graylist request.
     * 
     * @return int Current Request ID.  If valid request does not exist, returns 0.
     */
    public function getRequestId(): int
    {
        return $this->requestId;
    }

    /**
     * Start a new Graylist Request and return the Request ID for it.
     *
     * @param int           $appRecordId ID of the record that triggered this graylist request
     * @param string        $condition   Original event's condition
     * @param string|null   $firstName   Patient's first name
     * @param string|null   $lastName    Patient's last name
     * @param string|null   $middleName  Patient's middle name
     * @param DateTime|null $dob         Patient's DOB (any format supported by <b>strtotime()</b>)
     * @param DateTime|null $eventDate   Original event's Event Date (any format supported by <b>strtotime()</b>)
     *
     * @return int Request ID for this request.
     */
    protected function startNewRequest(int $appRecordId, string $condition, ?string $firstName = null, ?string $lastName = null, ?string $middleName = null, ?DateTime $dob = null, ?DateTime $eventDate = null)
    {
        $this->setAppRecordId($appRecordId);
        $this->setCondition($condition);
        $this->setFirstName($firstName);
        $this->setLastName($lastName);
        $this->setMiddleName($middleName);
        $this->setDob($dob);
        $this->setEventDate($eventDate);
        $this->setRequestStatus(GRAY_PENDING_STATUS);

        return $this->buildNewRequest();
    }

    /**
     * Load an existing Graylist Request for further processing.
     * 
     * @param int $requestId Existing Request ID
     * 
     * @return int <i>requestId</i> if found, 0 if <i>requestId</i> is not a valid ID.
     * 
     * @throws Exception if no <i>requestId</i> is specified
     * @throws PDOException
     */
    protected function getExistingRequest($requestId)
    {
        if (empty($requestId) || !(intval($requestId) > 0)) {
            throw new Exception('Could not get existing request:  No Request ID specified.');
        }

        $sql = 'SELECT * 
					FROM graylist_requests
					WHERE id = :r_id';
        $stmt = $this->dbConn->prepare($sql);
        $stmt->bindValue(':r_id', intval($requestId), PDO::PARAM_INT);

        if ($stmt->execute()) {
            $rs = $stmt->fetchObject();
            if ($rs !== false) {
                $this->setAppRecordId($rs->originating_event_id);
                $this->setCondition($rs->condition);
                $this->setDob(((!EmsaUtils::emptyTrim($rs->dob)) ? DateTimeUtils::createMixed($rs->dob) : null));
                $this->setEventDate(((!EmsaUtils::emptyTrim($rs->event_date)) ? DateTimeUtils::createMixed($rs->event_date) : null));
                $this->setFirstName($rs->first_name);
                $this->setLastName($rs->last_name);
                $this->setMiddleName($rs->middle_name);
                $this->setRequestStatus($rs->status);
                $this->requestId = intval($rs->id);
            }
        }

        return $this->getRequestId();
    }

    /**
     * Create a new Graylist Request in the EMSA database and return the Request ID for it.
     * 
     * @return int Request ID for this request.
     * 
     * @throws BadMethodCallException if missing required fields.
     * @throws PDOException
     */
    protected function buildNewRequest()
    {
        if (
                empty($this->appRecordId) || empty($this->condition) || empty($this->firstName) || empty($this->lastName) || empty($this->eventDate)
        ) {
            throw new BadMethodCallException('Could not start new Graylist Request:  Missing required fields.');
        } else {
            $sql = 'INSERT INTO graylist_requests
						(originating_event_id, first_name, last_name, middle_name, dob, condition, status, event_date)
						VALUES (:o_id, :f_name, :l_name, :m_name, :dob, :condition, :status, :eventDate)
						RETURNING id;';
            $stmt = $this->dbConn->prepare($sql);
            $stmt->bindValue(':o_id', $this->appRecordId, PDO::PARAM_INT);
            $stmt->bindValue(':f_name', $this->firstName, PDO::PARAM_STR);
            $stmt->bindValue(':l_name', $this->lastName, PDO::PARAM_STR);
            $stmt->bindValue(':m_name', $this->middleName, PDO::PARAM_STR);
            $stmt->bindValue(':dob', $this->getDob(true, "Y-m-d H:i:s"), PDO::PARAM_STR);
            $stmt->bindValue(':condition', $this->condition, PDO::PARAM_STR);
            $stmt->bindValue(':status', GRAY_PENDING_STATUS, PDO::PARAM_INT);
            $stmt->bindValue(':eventDate', $this->getEventDate(true, "Y-m-d H:i:s"), PDO::PARAM_STR);

            if ($stmt->execute()) {
                $this->requestId = (int) $stmt->fetchColumn(0);
            }

            return $this->getRequestId();
        }
    }

}

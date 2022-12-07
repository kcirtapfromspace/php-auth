<?php

namespace Udoh\Emsa\UI\Queue;

/**
 * Copyright (c) 2020 Utah Department of Technology Services and Utah Department of Health
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
 * @copyright Copyright (c) 2020 Utah Department of Technology Services and Utah Department of Health
 */

use DateTime;
use EmsaMessage;
use PDO;
use Udoh\Emsa\Client\AppClientList;
use Udoh\Emsa\Utils\DateTimeUtils;
use Udoh\Emsa\Utils\DisplayUtils;

/**
 * Class EmsaQueueListItem
 *
 * @package Udoh\Emsa\UI\Queue
 *
 * @author  Josh Ridderhoff <jridderhoff@utah.gov>
 */
class EmsaQueueListItem
{

    /** @var int */
    protected $id;
    /** @var int */
    protected $originalMessageId;
    /** @var string */
    protected $lastName;
    /** @var string */
    protected $firstName;
    /** @var string */
    protected $middleName;
    /** @var string */
    protected $condition;
    /** @var string */
    protected $labTestResult;
    /** @var string */
    protected $susceptibilityTestResult;
    /** @var DateTime */
    protected $dateOfBirth;
    /** @var int */
    protected $reportingInterfaceId;
    /** @var string */
    protected $reportingInterfaceName;
    /** @var DateTime */
    protected $displayDateTime;
    /** @var DateTime */
    protected $reportedDateTime;

    public function __construct(PDO $db, AppClientList $appClientList)
    {

    }

    /**
     * @return int
     */
    public function getId(): int
    {
        return $this->id;
    }

    /**
     * @param int $id
     *
     * @return EmsaQueueListItem
     */
    public function setId(int $id): EmsaQueueListItem
    {
        $this->id = $id;
        return $this;
    }

    /**
     * @return int
     */
    public function getOriginalMessageId(): int
    {
        return $this->originalMessageId;
    }

    /**
     * @param int $originalMessageId
     *
     * @return EmsaQueueListItem
     */
    public function setOriginalMessageId(int $originalMessageId): EmsaQueueListItem
    {
        $this->originalMessageId = $originalMessageId;
        return $this;
    }

    /**
     * @return string
     */
    public function getLastName(): ?string
    {
        return $this->lastName;
    }

    /**
     * @param string $lastName
     *
     * @return EmsaQueueListItem
     */
    public function setLastName(?string $lastName = null): EmsaQueueListItem
    {
        if (!empty($lastName)) {
            $this->lastName = trim($lastName);
        }
        return $this;
    }

    /**
     * @return string
     */
    public function getFirstName(): ?string
    {
        return $this->firstName;
    }

    /**
     * @param string $firstName
     *
     * @return EmsaQueueListItem
     */
    public function setFirstName(?string $firstName = null): EmsaQueueListItem
    {
        if (!empty($firstName)) {
            $this->firstName = trim($firstName);
        }
        return $this;
    }

    /**
     * @return string
     */
    public function getMiddleName(): ?string
    {
        return $this->middleName;
    }

    /**
     * @param string $middleName
     *
     * @return EmsaQueueListItem
     */
    public function setMiddleName(?string $middleName = null): EmsaQueueListItem
    {
        if (!empty($middleName)) {
            $this->middleName = trim($middleName);
        }
        return $this;
    }

    /**
     * @return string|null
     */
    public function getFullName(): ?string
    {
        return DisplayUtils::formatNameLastFirstMiddle($this->getLastName(), $this->getFirstName(), $this->getMiddleName());
    }

    /**
     * @return string
     */
    public function getCondition(): ?string
    {
        return $this->condition;
    }

    /**
     * @param string $condition
     *
     * @return EmsaQueueListItem
     */
    public function setCondition(?string $condition = null): EmsaQueueListItem
    {
        if (!empty($condition)) {
            $this->condition = trim($condition);
        }
        return $this;
    }

    /**
     * @param bool   $formatted [Optional]<br>Default <b>FALSE</b>.  If <b>TRUE</b>, returns date as a formatted string (specified by <i>formatStr</i>).
     * @param string $formatStr [Optional]<br>Valid PHP date format string/constant to return the date as.  Default <b>DateTime::RFC3339</b>.
     *
     * @return string|DateTime|null Formatted string if <i>formatted</i> is <b>TRUE</b>, DateTime object otherwise.<br>Returns <b>NULL</b> if <i>dateObj</i> is null or empty.
     */
    public function getDateOfBirth(?bool $formatted = false, ?string $formatStr = DateTime::RFC3339)
    {
        return DateTimeUtils::getDateFormatted($this->dateOfBirth, $formatted, $formatStr);
    }

    /**
     * @param DateTime $dateOfBirth
     *
     * @return EmsaQueueListItem
     */
    public function setDateOfBirth(DateTime $dateOfBirth): EmsaQueueListItem
    {
        $this->dateOfBirth = $dateOfBirth;
        return $this;
    }

    /**
     * @return int
     */
    public function getReportingInterfaceId(): int
    {
        return $this->reportingInterfaceId;
    }

    /**
     * @param int $reportingInterfaceId
     *
     * @return EmsaQueueListItem
     */
    public function setReportingInterfaceId(int $reportingInterfaceId): EmsaQueueListItem
    {
        $this->reportingInterfaceId = $reportingInterfaceId;
        return $this;
    }

    /**
     * @return string
     */
    public function getReportingInterfaceName(): string
    {
        return $this->reportingInterfaceName;
    }

    /**
     * @param string $reportingInterfaceName
     *
     * @return EmsaQueueListItem
     */
    public function setReportingInterfaceName(string $reportingInterfaceName): EmsaQueueListItem
    {
        $this->reportingInterfaceName = $reportingInterfaceName;
        return $this;
    }

    /**
     * @param bool   $formatted [Optional]<br>Default <b>FALSE</b>.  If <b>TRUE</b>, returns date as a formatted string (specified by <i>formatStr</i>).
     * @param string $formatStr [Optional]<br>Valid PHP date format string/constant to return the date as.  Default <b>DateTime::RFC3339</b>.
     *
     * @return string|DateTime|null Formatted string if <i>formatted</i> is <b>TRUE</b>, DateTime object otherwise.<br>Returns <b>NULL</b> if <i>dateObj</i> is null or empty.
     */
    public function getDisplayDateTime(?bool $formatted = false, ?string $formatStr = DateTime::RFC3339)
    {
        return DateTimeUtils::getDateFormatted($this->displayDateTime, $formatted, $formatStr);
    }

    /**
     * @param DateTime $displayDateTime
     *
     * @return EmsaQueueListItem
     */
    public function setDisplayDateTime(DateTime $displayDateTime): EmsaQueueListItem
    {
        $this->displayDateTime = $displayDateTime;
        return $this;
    }

    /**
     * @param bool   $formatted [Optional]<br>Default <b>FALSE</b>.  If <b>TRUE</b>, returns date as a formatted string (specified by <i>formatStr</i>).
     * @param string $formatStr [Optional]<br>Valid PHP date format string/constant to return the date as.  Default <b>DateTime::RFC3339</b>.
     *
     * @return string|DateTime|null Formatted string if <i>formatted</i> is <b>TRUE</b>, DateTime object otherwise.<br>Returns <b>NULL</b> if <i>dateObj</i> is null or empty.
     */
    public function getReportedDateTime(?bool $formatted = false, ?string $formatStr = DateTime::RFC3339)
    {
        return DateTimeUtils::getDateFormatted($this->reportedDateTime, $formatted, $formatStr);
    }

    /**
     * @param DateTime $reportedDateTime
     *
     * @return EmsaQueueListItem
     */
    public function setReportedDateTime(DateTime $reportedDateTime): EmsaQueueListItem
    {
        $this->reportedDateTime = $reportedDateTime;
        return $this;
    }

    /**
     * @return string
     */
    public function getLabTestResult(): ?string
    {
        return $this->labTestResult;
    }

    /**
     * @param string $labTestResult
     *
     * @return EmsaQueueListItem
     */
    public function setLabTestResult(?string $labTestResult = null): EmsaQueueListItem
    {
        if (!empty($labTestResult)) {
            $this->labTestResult = $labTestResult;
        }
        return $this;
    }

    /**
     * @return string
     */
    public function getSusceptibilityTestResult(): ?string
    {
        return $this->susceptibilityTestResult;
    }

    /**
     * @param string $susceptibilityTestResult
     *
     * @return EmsaQueueListItem
     */
    public function setSusceptibilityTestResult(?string $susceptibilityTestResult = null): EmsaQueueListItem
    {
        if (!empty($susceptibilityTestResult)) {
            $this->susceptibilityTestResult = $susceptibilityTestResult;
        }
        return $this;
    }
    
    public function getTestResultClass(): string
    {
        $lowerLabTestResult = strtolower($this->getLabTestResult());
        $lowerSusceptibilityTestResult = strtolower($this->getSusceptibilityTestResult());
        
        if ($lowerLabTestResult == 'positive') {
            $testResultClass = 'positive';
        } elseif ($lowerLabTestResult == 'negative') {
            $testResultClass = 'negative';
        } elseif ($lowerLabTestResult == 'susceptible') {
            $testResultClass = 'susceptible';
        } elseif ($lowerLabTestResult == 'resistant') {
            $testResultClass = 'resistant';
        } elseif ($lowerLabTestResult == 'intermediate') {
            $testResultClass = 'intermediate';
        } else {
            $testResultClass = 'other';
        }
        
        // override testResultClass if antimicrobial susceptibility exists in incoming message
        if (!empty($lowerSusceptibilityTestResult)) {
            if ($lowerSusceptibilityTestResult == 'susceptible') {
                $testResultClass = 'susceptible';
            } elseif ($lowerSusceptibilityTestResult == 'resistant') {
                $testResultClass = 'resistant';
            } elseif ($lowerSusceptibilityTestResult == 'intermediate') {
                $testResultClass = 'intermediate';
            } else {
                $testResultClass = 'other';
            }
        }
        
        return $testResultClass;
    }


}
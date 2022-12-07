<?php

namespace Udoh\Emsa\Model;

/**
 * Copyright (c) 2017 Utah Department of Technology Services and Utah Department of Health
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
 * @copyright Copyright (c) 2017 Utah Department of Technology Services and Utah Department of Health
 */

/**
 * Base class to represent the model of a Record object specific to an interfaced Application.
 *
 * @author Josh Ridderhoff <jridderhoff@utah.gov>
 */
abstract class AppRecord
{
    
    protected $appRecordDocument;
    
    /** @var int */
    protected $personId;
    
    /**
     * Populate the AppRecord instance with the Application's representation of the Record document.
     * @param mixed $appRecordDocument Document representing the record for this Application
     * @return \Udoh\Emsa\Model\AppRecord
     */
    public function setAppRecordDocument($appRecordDocument = null)
    {
        if (!empty($appRecordDocument)) {
            $this->appRecordDocument = $appRecordDocument;
        }
        
        return $this;
    }
    
    /**
     * Document representing the record for this Application (e.g. a \SimpleXMLElement object)
     * @return mixed
     */
    public function getAppRecordDocument()
    {
        return $this->appRecordDocument;
    }
    
    /**
     * Set the ID of the person this record belongs to.
     * @param int $personId
     * @return \Udoh\Emsa\Model\AppRecord
     */
    public function setPersonId($personId = null)
    {
        if (!empty($personId)) {
            $this->personId = (int) filter_var($personId, FILTER_SANITIZE_NUMBER_INT);
        }
        
        return $this;
    }
    
    /**
     * Get the ID of the person this record is for
     * @return int
     */
    public function getPersonId()
    {
        return $this->personId;
    }

    /**
     * Get the record's Event ID
     * @return int
     */
    abstract public function getEventId();

    /**
     * Get the record's Record Number
     * @return string
     */
    abstract public function getRecordNumber();

    /**
     * Get the record's Record Type
     * @return int One of \Udoh\Emsa\Constants\AppRecordType
     */
    abstract public function getRecordType();

    /**
     * Get the ID of the condition associated with the record
     * @return int
     */
    abstract public function getConditionId();
    
    /**
     * Get the name of the condition associated with the record
     * @return string
     */
    abstract public function getConditionName();

    /**
     * Get the record's Event Date
     * 
     * @param boolean $formatted [Optional]<br>Default <b>FALSE</b>.  If <b>TRUE</b>, returns date as a formatted string (specified by <i>formatStr</i>).
     * @param string $formatStr [Optional]<br>Valid PHP date format string/constant to return the date as.  Default <b>\DateTime::RFC3339</b>.
     * 
     * @return string|\DateTime|null Formatted string if <i>formatted</i> is <b>TRUE</b>, \DateTime object otherwise.<br>Returns <b>NULL</b> if Date of Birth is not set.
     */
    abstract public function getEventDate($formatted = false, $formatStr = \DateTime::RFC3339);
    
    /**
     * Return the ID of the most-recently added Lab Test Result.
     * @return int;
     */
    abstract public function getLastAddedLabResultId();
    
    /**
     * Get the name of the jurisdiction the record is associated with, if set.  Returns <b>NULL</b> if not set.
     * @return string
     */
    abstract public function getJurisdictionName();
    
    /**
     * Get the user assigned as an investigator for this record.
     * @return \Udoh\Emsa\Model\AppUser
     */
    abstract public function getInvestigator();
}

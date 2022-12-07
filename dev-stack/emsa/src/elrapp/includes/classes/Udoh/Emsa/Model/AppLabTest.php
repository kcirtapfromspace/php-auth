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
 * Lab Test object for the specified application.
 *
 * @author Josh Ridderhoff <jridderhoff@utah.gov>
 */
class AppLabTest
{
    /** @var int */
    protected $id;
    
    /** @var string */
    protected $loincCode;
    
    /** @var string */
    protected $referenceRange;
    
    /** @var \DateTime */
    protected $testDate;
    
    /** @var string */
    protected $testType;
    
    /** @var string */
    protected $testStatus;
    
    /** @var AppLabTestResultList */
    protected $testResults;
    
    /**
     * Create a new AppLabTest object.
     * @param int $id [Optional] Lab Test ID
     */
    public function __construct($id = null)
    {
        if (!empty($id)) {
            $this->setId($id);
        }
        
        $this->testResults = new AppLabTestResultList();
    }
    
    /**
     * Get the Lab Test ID.
     * @return int
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * Get the LOINC Code for this test.
     * @return string
     */
    public function getLoincCode()
    {
        return $this->loincCode;
    }

    /**
     * Get the Reference Range for this test.
     * @return string
     */
    public function getReferenceRange()
    {
        return $this->referenceRange;
    }

    /**
     * Get the Test Date for this test.
     * 
     * @param boolean $formatted [Optional]<br>Default <b>FALSE</b>.  If <b>TRUE</b>, returns date as a formatted string (specified by <i>formatStr</i>).
     * @param string $formatStr [Optional]<br>Valid PHP date format string/constant to return the date as.  Default <b>\DateTime::RFC3339</b>.
     * 
     * @return string|\DateTime|null Formatted string if <i>formatted</i> is <b>TRUE</b>, DateTime object otherwise.<br>Returns <b>NULL</b> if enounter date is not set.
     */
    public function getTestDate($formatted = false, $formatStr = \DateTime::RFC3339)
    {
        return \Udoh\Emsa\Utils\DateTimeUtils::getDateFormatted($this->testDate, $formatted, $formatStr);
    }

    /**
     * Get the Test Type.
     * @return string
     */
    public function getTestType()
    {
        return $this->testType;
    }

    /**
     * Get the Test Status.
     * @return string
     */
    public function getTestStatus()
    {
        return $this->testStatus;
    }

    /**
     * Get the Test Results for this test.
     * @return AppLabTestResultList
     */
    public function getTestResults()
    {
        return $this->testResults;
    }

    /**
     * Set the Lab Test ID.
     * @param int $id
     * @return \Udoh\Emsa\Model\AppLabTest
     */
    public function setId($id = null)
    {
        if (!empty($id)) {
            $this->id = (int) $id;
        }
        
        return $this;
    }

    /**
     * Set the LOINC Code for this test.
     * @param string $loincCode
     * @return \Udoh\Emsa\Model\AppLabTest
     */
    public function setLoincCode($loincCode = null)
    {
        if (!empty($loincCode)) {
            $this->loincCode = (string) $loincCode;
        }
        
        return $this;
    }

    /**
     * Set the Reference Range for this test.
     * @param string $referenceRange
     * @return \Udoh\Emsa\Model\AppLabTest
     */
    public function setReferenceRange($referenceRange = null)
    {
        if (!empty($referenceRange)) {
            $this->referenceRange = (string) $referenceRange;
        }
        
        return $this;
    }

    /**
     * Set the Test Date.
     * @param \DateTime $testDate
     * @return \Udoh\Emsa\Model\AppLabTest
     */
    public function setTestDate(\DateTime $testDate = null)
    {
        if (!empty($testDate)) {
            $this->testDate = $testDate;
        }
        
        return $this;
    }

    /**
     * Set the Test Type for this test.
     * @param string $testType
     * @return \Udoh\Emsa\Model\AppLabTest
     */
    public function setTestType($testType = null)
    {
        if (!empty($testType)) {
            $this->testType = (string) $testType;
        }
        
        return $this;
    }

    /**
     * Set the Test Status for this test.
     * @param string $testStatus
     * @return \Udoh\Emsa\Model\AppLabTest
     */
    public function setTestStatus($testStatus = null)
    {
        if (!empty($testStatus)) {
            $this->testStatus = (string) $testStatus;
        }
        
        return $this;
    }

    /**
     * Add a Lab Test Result to this tet.
     * @param \Udoh\Emsa\Model\AppLabTestResult $labTestResult
     * @return \Udoh\Emsa\Model\AppLabTest
     */
    public function addLabTestResult(AppLabTestResult $labTestResult)
    {
        $this->testResults->add($labTestResult);
        return $this;
    }


}

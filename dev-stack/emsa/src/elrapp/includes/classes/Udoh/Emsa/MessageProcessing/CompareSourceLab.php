<?php

namespace Udoh\Emsa\MessageProcessing;

use DateTime;
use Udoh\Emsa\Utils\DateTimeUtils;

/**
 * Copyright (c) 2018 Utah Department of Technology Services and Utah Department of Health
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
 * @copyright Copyright (c) 2018 Utah Department of Technology Services and Utah Department of Health
 */

/**
 * Container for source lab comparison data used by EMSA message assignment rules.
 *
 * @author Josh Ridderhoff <jridderhoff@utah.gov>
 */
class CompareSourceLab
{

    /** @var string */
    protected $disease;

    /** @var string */
    protected $organism;

    /** @var DateTime */
    protected $dateCollected;

    /** @var DateTime */
    protected $dateTested;

    /** @var string */
    protected $accessionNumber;

    /** @var string */
    protected $labName;

    /** @var string */
    protected $specimenSource;

    /** @var string */
    protected $testType;

    /** @var string */
    protected $testResult;

    /** @var string */
    protected $resultValue;

    /** @var string */
    protected $testStatus;

    /** @var string */
    protected $comment;

    /** @var string */
    protected $masterLoinc;

    /** @var string */
    protected $jurisdiction;
    
    /** @var CompareSourceSusceptibility */
    protected $sourceSusceptibility;

    /**
     * Create a new CompareSourceLab object.
     *
     * @param string                      $conditionName        Condition name
     * @param string                      $organism             Organism name
     * @param DateTime                    $dateCollected        Specimen collection date/time
     * @param DateTime                    $dateTested           Lab test date
     * @param string                      $accessionNumber      Accession number
     * @param string                      $labName              Performing Lab name
     * @param string                      $specimenSource       Specimen source
     * @param string                      $testType             NEDSS Test type
     * @param string                      $testResult           NEDSS Test result
     * @param string                      $resultValue          Result value
     * @param string                      $testStatus           Test status
     * @param string                      $comment              Lab result comments
     * @param string                      $masterLoinc          Master LOINC code
     * @param string                      $jurisdiction         Jurisdiction name
     * @param CompareSourceSusceptibility $sourceSusceptibility Antimicrobial Susceptibility Test associated with this lab
     */
    public function __construct(
            $conditionName = null, 
            $organism = null, 
            DateTime $dateCollected = null,
            DateTime $dateTested = null,
            $accessionNumber = null, 
            $labName = null, 
            $specimenSource = null, 
            $testType = null, 
            $testResult = null, 
            $resultValue = null, 
            $testStatus = null, 
            $comment = null, 
            $masterLoinc = null, 
            $jurisdiction = null,
            $sourceSusceptibility = null
    )
    {
        $this->setDisease($conditionName);
        $this->setOrganism($organism);
        $this->setDateCollected($dateCollected);
        $this->setDateTested($dateTested);
        $this->setAccessionNumber($accessionNumber);
        $this->setLabName($labName);
        $this->setSpecimenSource($specimenSource);
        $this->setTestType($testType);
        $this->setTestResult($testResult);
        $this->setResultValue($resultValue);
        $this->setTestStatus($testStatus);
        $this->setComment($comment);
        $this->setMasterLoinc($masterLoinc);
        $this->setJurisdiction($jurisdiction);
        $this->setSourceSusceptibility($sourceSusceptibility);
    }

    public function getDisease()
    {
        return $this->disease;
    }

    public function getOrganism()
    {
        return $this->organism;
    }

    /**
     * Get the specimen collection date for the incoming lab
     * 
     * @param bool $formatted [Optional]<br>Default <b>FALSE</b>.  If <b>TRUE</b>, returns date as a formatted string (specified by <i>formatStr</i>).
     * @param string $formatStr [Optional]<br>Valid PHP date format string/constant to return the date as.  Default <b>DateTime::RFC3339</b>.
     * 
     * @return mixed Formatted string if <i>formatted</i> is <b>TRUE</b>, DateTime object otherwise.<br>Returns <b>NULL</b> if Specimen Collection Date is not set.
     */
    public function getDateCollected($formatted = false, $formatStr = DateTime::RFC3339)
    {
        return DateTimeUtils::getDateFormatted($this->dateCollected, $formatted, $formatStr);
    }

    /**
     * Get the lab test date for the incoming lab
     *
     * @param bool $formatted [Optional]<br>Default <b>FALSE</b>.  If <b>TRUE</b>, returns date as a formatted string (specified by <i>formatStr</i>).
     * @param string $formatStr [Optional]<br>Valid PHP date format string/constant to return the date as.  Default <b>DateTime::RFC3339</b>.
     *
     * @return mixed Formatted string if <i>formatted</i> is <b>TRUE</b>, DateTime object otherwise.<br>Returns <b>NULL</b> if Lab Test Date is not set.
     */
    public function getDateTested($formatted = false, $formatStr = DateTime::RFC3339)
    {
        return DateTimeUtils::getDateFormatted($this->dateTested, $formatted, $formatStr);
    }

    public function getAccessionNumber()
    {
        return $this->accessionNumber;
    }

    public function getLabName()
    {
        return $this->labName;
    }

    public function getSpecimenSource()
    {
        return $this->specimenSource;
    }

    public function getTestType()
    {
        return $this->testType;
    }

    public function getTestResult()
    {
        return $this->testResult;
    }

    public function getResultValue()
    {
        return $this->resultValue;
    }

    public function getTestStatus()
    {
        return $this->testStatus;
    }

    public function getComment()
    {
        return $this->comment;
    }

    public function getMasterLoinc()
    {
        return $this->masterLoinc;
    }

    public function getJurisdiction()
    {
        return $this->jurisdiction;
    }
    
    /**
     * Get this lab's Antimicrobial Susceptibility Result
     * 
     * @return CompareSourceSusceptibility
     */
    public function getSourceSusceptibility()
    {
        return $this->sourceSusceptibility;
    }

    /**
     * Sets the incoming lab's condition
     * @param string $conditionName Condition name
     */
    protected function setDisease($conditionName)
    {
        if (!empty($conditionName)) {
            $this->disease = (string) $conditionName;
        }
    }

    /**
     * Sets the incoming lab's organism
     * @param string $organism Organism name
     */
    protected function setOrganism($organism)
    {
        if (!empty($organism)) {
            $this->organism = (string) $organism;
        }
    }

    /**
     * Sets the incoming lab's specimen collection date.
     *
     * @param DateTime $dateCollected Specimen collection date/time
     */
    protected function setDateCollected(DateTime $dateCollected = null)
    {
        if (!empty($dateCollected)) {
            $this->dateCollected = $dateCollected;
        }
    }

    /**
     * Sets the incoming lab's lab test date.
     *
     * @param DateTime $dateTested Lab test date
     */
    public function setDateTested(DateTime $dateTested = null)
    {
        if (!empty($dateTested)) {
            $this->dateTested = $dateTested;
        }
    }

    /**
     * Sets the incoming lab's accession number
     * @param string $accessionNumber Accession number
     */
    protected function setAccessionNumber($accessionNumber)
    {
        if (!empty($accessionNumber)) {
            $this->accessionNumber = (string) $accessionNumber;
        }
    }

    /**
     * Sets the lab name associated with the incoming lab
     * @param string $labName Lab name
     */
    protected function setLabName($labName)
    {
        if (!empty($labName)) {
            $this->labName = (string) $labName;
        }
    }

    /**
     * Sets the incoming lab's specimen source
     * @param string $specimenSource Specimen source
     */
    protected function setSpecimenSource($specimenSource)
    {
        if (!empty($specimenSource)) {
            $this->specimenSource = (string) $specimenSource;
        }
    }

    /**
     * Sets the incoming lab's test type
     * @param string $testType Test type
     */
    protected function setTestType($testType)
    {
        if (!empty($testType)) {
            $this->testType = (string) $testType;
        }
    }

    /**
     * Sets the incoming lab's test result
     * @param string $testResult Test result
     */
    protected function setTestResult($testResult)
    {
        if (!empty($testResult)) {
            $this->testResult = (string) $testResult;
        }
    }

    /**
     * Sets the incoming lab's result value
     * @param string $resultValue Result value
     */
    protected function setResultValue($resultValue)
    {
        if (!empty($resultValue)) {
            $this->resultValue = (string) $resultValue;
        }
    }

    /**
     * Sets the incoming lab's test status
     * @param string $testStatus Test status
     */
    protected function setTestStatus($testStatus)
    {
        if (!empty($testStatus)) {
            $this->testStatus = (string) $testStatus;
        }
    }

    /**
     * Sets the incoming lab's existing comments
     * @param string $comment Lab result comments
     */
    protected function setComment($comment)
    {
        if (!empty($comment)) {
            $this->comment = (string) $comment;
        }
    }

    /**
     * Sets the incoming lab's Master LOINC code
     * @param string $masterLoinc Master LOINC code
     */
    protected function setMasterLoinc($masterLoinc)
    {
        if (!empty($masterLoinc)) {
            $this->masterLoinc = (string) $masterLoinc;
        }
    }

    /**
     * Sets the incoming lab's jurisdiction
     * @param string $jurisdiction Jurisdiction name
     */
    protected function setJurisdiction($jurisdiction)
    {
        if (!empty($jurisdiction)) {
            $this->jurisdiction = (string) $jurisdiction;
        }
    }
    
    /**
     * Sets the incoming lab's Antimicrobial Susceptibility Test
     * 
     * @param \Udoh\Emsa\MessageProcessing\CompareSourceSusceptibility $sourceSusceptibility
     * 
     * @return \Udoh\Emsa\MessageProcessing\CompareSourceLab
     */
    public function setSourceSusceptibility(CompareSourceSusceptibility $sourceSusceptibility = null)
    {
        if (!empty($sourceSusceptibility)) {
            $this->sourceSusceptibility = $sourceSusceptibility;
        }
        
        return $this;
    }



}


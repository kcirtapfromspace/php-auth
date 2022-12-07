<?php

namespace Udoh\Emsa\Model;

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
 * Antimicrobial Resistance Test Result for the specified application.
 *
 * @author Josh Ridderhoff <jridderhoff@utah.gov>
 */
class AppResistTest
{
    /** @var int */
    protected $id;
    
    /** @var string Antimicrobial Agent Name */
    protected $agentName;

    /** @var string Antimicrobial Resistance Test Result */
    protected $testResult;

    /** @var string Antimicrobial Resistance Test Result Value */
    protected $resultValue;

    /** @var DateTime Antimicrobial Resistance Test Date */
    protected $testDate;

    /**
     * Create a source Antimicrobial Susceptibility for comparison purposes.
     * 
     * @param int $id Antimicrobial Resistance Test Result ID from the specified application
     * @param string $agentName Antimicrobial Agent Name
     * @param string $testResult Antimicrobial Resistance Test Result
     * @param string $resultValue Antimicrobial Resistance Test Result Value
     * @param DateTime $testDate Antimicrobial Resistance Test Date
     */
    public function __construct($id = null, $agentName = null, $testResult = null, $resultValue = null, DateTime $testDate = null)
    {
        $this->setId($id)
                ->setAgentName($agentName)
                ->setTestResult($testResult)
                ->setResultValue($resultValue)
                ->setTestDate($testDate);
    }

    /**
     * Get the Resistance Test Result ID.
     * 
     * @return int
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * Return the Antimicrobial Agent Name
     * 
     * @return string
     */
    public function getAgentName()
    {
        return $this->agentName;
    }

    /**
     * Return the Antimicrobial Resistance Test Result
     * 
     * @return string
     */
    public function getTestResult()
    {
        return $this->testResult;
    }

    /**
     * Return the Antimicrobial Resistance Test Result Value
     * 
     * @return string
     */
    public function getResultValue()
    {
        return $this->resultValue;
    }

    /**
     * Return the Antimicrobial Resistance Test Date
     * 
     * @param boolean $formatted [Optional]<br>Default <b>FALSE</b>.  If <b>TRUE</b>, returns date as a formatted string (specified by <i>formatStr</i>).
     * @param string $formatStr [Optional]<br>Valid PHP date format string/constant to return the date as.  Default <b>DateTime::RFC3339</b>.
     * 
     * @return mixed Formatted string if <i>formatted</i> is <b>TRUE</b>, DateTime object otherwise.<br>Returns <b>NULL</b> if test date is null or empty.
     */
    public function getTestDate($formatted = false, $formatStr = DateTime::RFC3339)
    {
        return DateTimeUtils::getDateFormatted($this->testDate, $formatted, $formatStr);
    }

    /**
     * Set the Resistance Test Result ID.
     * 
     * @param int $id

     * @return AppResistTest
     */
    public function setId($id = null)
    {
        if (!empty($id)) {
            $this->id = (int) $id;
        }
        
        return $this;
    }

    /**
     * Set Antimicrobial Agent Name
     * 
     * @param string $agentName
     * 
     * @return AppResistTest
     */
    public function setAgentName($agentName = null)
    {
        $this->agentName = $agentName;
        return $this;
    }

    /**
     * Set Antimicrobial Resistance Test Result (e.g. "Resistant" or "Susceptible")
     * 
     * @param string $testResult
     * 
     * @return AppResistTest
     */
    public function setTestResult($testResult = null)
    {
        $this->testResult = $testResult;
        return $this;
    }

    /**
     * Set Antimicrobial Resistance Test Result Value (e.g. MIC Qn value)
     * 
     * @param string $resultValue
     * 
     * @return AppResistTest
     */
    public function setResultValue($resultValue = null)
    {
        $this->resultValue = $resultValue;
        return $this;
    }

    /**
     * Set the Antimicrobial Resistance Test Date 
     * 
     * @param DateTime $testDate
     * 
     * @return AppResistTest
     */
    public function setTestDate(DateTime $testDate = null)
    {
        $this->testDate = $testDate;
        return $this;
    }

}

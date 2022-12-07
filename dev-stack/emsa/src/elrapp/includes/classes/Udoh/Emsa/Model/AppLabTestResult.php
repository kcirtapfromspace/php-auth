<?php

namespace Udoh\Emsa\Model;

use Udoh\Emsa\Model\AppResistTest;
use Udoh\Emsa\Model\AppResistTestList;

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
 * Lab Test Result for the specified application.
 *
 * @author Josh Ridderhoff <jridderhoff@utah.gov>
 */
class AppLabTestResult
{
    /** @var int */
    protected $id;
    
    /** @var string */
    protected $resultValue;
    
    /** @var string */
    protected $units;
    
    /** @var string */
    protected $testResult;
    
    /** @var string */
    protected $organism;
    
    /** @var string */
    protected $comment;
    
    /** @var AppResistTestList */
    protected $resistTests;
    
    /**
     * Create a new AppLabTestResult object.
     * @param int $id [Optional] Lab Test Result ID
     */
    public function __construct($id = null)
    {
        if (!empty($id)) {
            $this->setId($id);
        }
        
        $this->resistTests = new AppResistTestList();
    }
    
    /**
     * Get the Lab Test Result ID.
     * @return int
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * Get the result value.
     * @return string
     */
    public function getResultValue()
    {
        return $this->resultValue;
    }

    /**
     * Get the units.
     * @return string
     */
    public function getUnits()
    {
        return $this->units;
    }

    /**
     * Get the ordinal test result (e.g. Positive, Negative, etc.)
     * @return string
     */
    public function getTestResult()
    {
        return $this->testResult;
    }

    /**
     * Get the name of the organism identified.
     * @return string
     */
    public function getOrganism()
    {
        return $this->organism;
    }
    
    /**
     * Get the lab comments.
     * @return string
     */
    public function getComment()
    {
        return $this->comment;
    }
    
    /**
     * Get the Antimicrobial Susceptibility Test Results associated with this Lab Test Result.
     * 
     * @return AppResistTestList
     */
    public function getResistTests()
    {
        return $this->resistTests;
    }

    /**
     * Set the Lab Test Result ID.
     * @param int $id
     * @return \Udoh\Emsa\Model\AppLabTestResult
     */
    public function setId($id = null)
    {
        if (!empty($id)) {
            $this->id = (int) $id;
        }
        
        return $this;
    }

    /**
     * Set the result value.
     * @param string $resultValue
     * @return \Udoh\Emsa\Model\AppLabTestResult
     */
    public function setResultValue($resultValue = null)
    {
        if (!empty($resultValue)) {
            $this->resultValue = (string) $resultValue;
        }
        
        return $this;
    }

    /**
     * Set the units.
     * @param string $units
     * @return \Udoh\Emsa\Model\AppLabTestResult
     */
    public function setUnits($units = null)
    {
        if (!empty($units)) {
            $this->units = (string) $units;
        }
        
        return $this;
    }

    /**
     * Set the ordinal test result.
     * @param string $testResult
     * @return \Udoh\Emsa\Model\AppLabTestResult
     */
    public function setTestResult($testResult = null)
    {
        if (!empty($testResult)) {
            $this->testResult = (string) $testResult;
        }
        
        return $this;
    }

    /**
     * Set the name of the identified organism.
     * @param string $organism
     * @return \Udoh\Emsa\Model\AppLabTestResult
     */
    public function setOrganism($organism = null)
    {
        if (!empty($organism)) {
            $this->organism = (string) $organism;
        }
        
        return $this;
    }
    
    /**
     * Set the lab comments.
     * @param string $comment
     * @return \Udoh\Emsa\Model\AppLabTestResult
     */
    public function setComment($comment = null)
    {
        if (!empty($comment)) {
            $this->comment = (string) $comment;
        }
        
        return $this;
    }
    
    /**
     * Add an Antimicrobial Susceptibility Test Result to this Lab Test Result.
     * 
     * @param AppResistTest $resistTest
     * 
     * @return \Udoh\Emsa\Model\AppLabTestResult
     */
    public function addResistTest(AppResistTest $resistTest) {
        $this->resistTests->add($resistTest);
        
        return $this;
    }


}

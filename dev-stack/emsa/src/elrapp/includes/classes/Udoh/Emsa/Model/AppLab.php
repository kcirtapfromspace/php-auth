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
 * Lab object for a specified application.
 *
 * @author Josh Ridderhoff <jridderhoff@utah.gov>
 */
class AppLab
{
    /** @var int */
    protected $id;
    
    /** @var string */
    protected $performingLabName;
    
    /** @var \DateTime */
    protected $collectionDate;
    
    /** @var string */
    protected $accessionNumber;
    
    /** @var string */
    protected $specimenSource;
    
    /** @var AppLabTestList */
    protected $labTests;
    
    /**
     * Create a new AppLab object.
     * @param int $id [Optional] Lab ID
     */
    public function __construct($id = null)
    {
        if (!empty($id)) {
            $this->setId($id);
        }
        
        $this->labTests = new AppLabTestList();
    }
    
    /**
     * Get this lab's ID
     * @return int
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * Get the performing lab name for this lab.
     * @return string
     */
    public function getPerformingLabName()
    {
        return $this->performingLabName;
    }

    /**
     * Get the Collection Date for this sample.
     * 
     * @param boolean $formatted [Optional]<br>Default <b>FALSE</b>.  If <b>TRUE</b>, returns date as a formatted string (specified by <i>formatStr</i>).
     * @param string $formatStr [Optional]<br>Valid PHP date format string/constant to return the date as.  Default <b>\DateTime::RFC3339</b>.
     * 
     * @return string|\DateTime|null Formatted string if <i>formatted</i> is <b>TRUE</b>, DateTime object otherwise.<br>Returns <b>NULL</b> if enounter date is not set.
     */
    public function getCollectionDate($formatted = false, $formatStr = \DateTime::RFC3339)
    {
        return \Udoh\Emsa\Utils\DateTimeUtils::getDateFormatted($this->collectionDate, $formatted, $formatStr);
    }

    /**
     * Get the Accession Number for this lab's collected sample.
     * @return string
     */
    public function getAccessionNumber()
    {
        return $this->accessionNumber;
    }

    /**
     * Get the Specimen Source for this lab's collected sample.
     * @return string
     */
    public function getSpecimenSource()
    {
        return $this->specimenSource;
    }

    /**
     * Get the lab tests for this lab.
     * @return AppLabTestList
     */
    public function getLabTests()
    {
        return $this->labTests;
    }

    /**
     * Set this lab's ID.
     * @param int $id
     * @return \Udoh\Emsa\Model\AppLab
     */
    public function setId($id = null)
    {
        if (!empty($id)) {
            $this->id = (int) $id;
        }
        
        return $this;
    }

    /**
     * Set the performing lab name for this lab.
     * @param string $performingLabName
     * @return \Udoh\Emsa\Model\AppLab
     */
    public function setPerformingLabName($performingLabName = null)
    {
        if (!empty($performingLabName)) {
            $this->performingLabName = (string) $performingLabName;
        }
        
        return $this;
    }

    /**
     * Set the Collection Date.
     * @param \DateTime $collectionDate
     * @return \Udoh\Emsa\Model\AppLab
     */
    public function setCollectionDate(\DateTime $collectionDate = null)
    {
        if (!empty($collectionDate)) {
            $this->collectionDate = $collectionDate;
        }
        
        return $this;
    }

    /**
     * Set the Accession Number.
     * @param string $accessionNumber
     * @return \Udoh\Emsa\Model\AppLab
     */
    public function setAccessionNumber($accessionNumber = null)
    {
        if (!empty($accessionNumber)) {
            $this->accessionNumber = $accessionNumber;
        }
        
        return $this;
    }

    /**
     * Set the Specimen Source.
     * @param string $specimenSource
     * @return \Udoh\Emsa\Model\AppLab
     */
    public function setSpecimenSource($specimenSource = null)
    {
        if (!empty($specimenSource)) {
            $this->specimenSource = $specimenSource;
        }
        
        return $this;
    }
    
    /**
     * Add a Lab Test to this Lab.
     * @param \Udoh\Emsa\Model\AppLabTest $labTest
     * @return \Udoh\Emsa\Model\AppLab
     */
    public function addLabTest(AppLabTest $labTest)
    {
        $this->labTests->add($labTest);
        return $this;
    }


}

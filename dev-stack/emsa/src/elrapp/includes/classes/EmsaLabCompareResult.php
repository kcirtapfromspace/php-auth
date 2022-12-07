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

/**
 * Container for results of lab result comparison during updateCmr preparation.
 *
 * @author Josh Ridderhoff <jridderhoff@utah.gov>
 */
class EmsaLabCompareResult
{

    const COMPARE_RESULT_NOT_FOUND = -1;
    const COMPARE_RESULT_EXCEPTION = 1;
    const COMPARE_RESULT_DUPLICATE = 2;
    const COMPARE_RESULT_UPDATE = 3;
    const COMPARE_RESULT_ADD = 4;
    const COMPARE_RESULT_CLINICAL_DOCUMENT = 5;
    
    /** @var int */
    protected $labResultCode;

    /** @var int */
    protected $resistResultCode;

    /** @var EmsaLabCompareUpdateLab */
    protected $updateLab;

    /** @var int[] */
    protected $duplicateLabIds = array();

    /** @var int[] */
    protected $exceptionLabIds = array();
    
    /** @var int[] */
    protected $duplicateResistTestIds = array();

    /** @var int[] */
    protected $exceptionResistTestIds = array();

    public function __construct()
    {
        $this->updateLab = new EmsaLabCompareUpdateLab();
        $this->setLabResultCode(self::COMPARE_RESULT_EXCEPTION);
    }

    public function getLabResultCode()
    {
        return $this->labResultCode;
    }

    public function getResistResultCode()
    {
        return $this->resistResultCode;
    }

    /**
     * Get the IDs & values for the lab result to be updated.
     * @return EmsaLabCompareUpdateLab
     */
    public function getUpdateLab()
    {
        return $this->updateLab;
    }

    /**
     * Get the list of duplicate lab result IDs
     * @return int[] Array of lab result IDs
     */
    public function getDuplicateLabIds()
    {
        return $this->duplicateLabIds;
    }

    /**
     * Get the list of lab result IDs triggering exceptions
     * @return int[] Array of lab result IDs
     */
    public function getExceptionLabIds()
    {
        return $this->exceptionLabIds;
    }

    /**
     * Get the list of duplicate antimicrobial resistance test IDs
     * @return int[] Array of lab result IDs
     */
    public function getDuplicateResistTestIds()
    {
        return $this->duplicateResistTestIds;
    }

    /**
     * Get the list of antimicrobial resistance test IDs triggering exceptions
     * @return int[] Array of lab result IDs
     */
    public function getExceptionResistTestIds()
    {
        return $this->exceptionResistTestIds;
    }

    /**
     * Set the result code for the lab portion of this comparison
     * @param int $labResultCode Result Code (one of <b>EmsaLabCompareResult::COMPARE_RESULT_*</b> constants)
     */
    protected function setLabResultCode($labResultCode)
    {
        $validCodes = array(
            self::COMPARE_RESULT_ADD,
            self::COMPARE_RESULT_DUPLICATE,
            self::COMPARE_RESULT_EXCEPTION,
            self::COMPARE_RESULT_UPDATE,
            self::COMPARE_RESULT_CLINICAL_DOCUMENT
        );

        if (in_array($labResultCode, $validCodes)) {
            $this->labResultCode = $labResultCode;
        }
    }

    /**
     * Set the result code for the antimicrobial resistance test portion comparison
     * @param int $resistResultCode Result Code (one of <b>EmsaLabCompareResult::COMPARE_RESULT_*</b> constants)
     */
    protected function setResistResultCode($resistResultCode)
    {
        $validCodes = array(
            self::COMPARE_RESULT_ADD,
            self::COMPARE_RESULT_DUPLICATE,
            self::COMPARE_RESULT_EXCEPTION,
            self::COMPARE_RESULT_UPDATE,
            self::COMPARE_RESULT_CLINICAL_DOCUMENT,
            self::COMPARE_RESULT_NOT_FOUND
        );

        if (in_array($resistResultCode, $validCodes)) {
            $this->resistResultCode = $resistResultCode;
        }
    }

    /**
     * Add the Lab ID of a duplicate lab result
     * @param int $labId Lab Result ID
     */
    public function addDuplicateLabId($labId)
    {
        if (!empty($labId)) {
            $this->duplicateLabIds[] = intval($labId);
        }
    }

    /**
     * Add the Lab ID of a lab result that is an exception
     * @param int $labId Lab Result ID
     */
    public function addExceptionLabId($labId)
    {
        if (!empty($labId)) {
            $this->exceptionLabIds[] = intval($labId);
        }
    }

    /**
     * Add the ID of a duplicate antimicrobial resistance test
     * @param int $resistTestId Antimicrobial resistance test ID
     */
    public function addDuplicateResistTestId($resistTestId)
    {
        if (!empty($resistTestId)) {
            $this->duplicateResistTestIds[] = intval($resistTestId);
        }
    }

    /**
     * Add the ID of an Antimicrobial resistance test that is an exception
     * @param int $resistTestId Antimicrobial resistance test ID
     */
    public function addExceptionResistTestId($resistTestId)
    {
        if (!empty($resistTestId)) {
            $this->exceptionResistTestIds[] = intval($resistTestId);
        }
    }

    /**
     * Add details about a matching Lab Result to be updated.
     *
     * @param int       $labId                Lab ID
     * @param int       $labTestId            Lab Test ID
     * @param int       $labTestResultId      Lab Test Result ID
     * @param string    $resultValue          [Optional]<br>Result Value from existing lab result
     * @param string    $units                [Optional]<br>Units from existing lab result
     * @param string    $comment              [Optional]<br>Comments from existing lab result
     * @param string    $referenceRange       [Optional]<br>Reference Range from existing lab result
     * @param bool|null $isTestStatusUpgraded [Optional]<br>Indicates whether the test status should be upgraded for this lab
     */
    public function setUpdateLab(int $labId, int $labTestId, int $labTestResultId, ?string $resultValue = null, ?string $units = null, ?string $comment = null, ?string $referenceRange = null, ?bool $isTestStatusUpgraded = false): void
    {
        $this->updateLab->labId = (int) $labId;
        $this->updateLab->labTestId = (int) $labTestId;
        $this->updateLab->labTestResultId = (int) $labTestResultId;
        
        $this->updateLab->resultValue = trim($resultValue);
        $this->updateLab->units = trim($units);
        $this->updateLab->comment = trim($comment);
        $this->updateLab->referenceRange = trim($referenceRange);

        if (!empty($isTestStatusUpgraded) && $isTestStatusUpgraded === true) {
            $this->updateLab->isTestStatusUpgraded = true;
        } else {
            $this->updateLab->isTestStatusUpgraded = false;
        }
    }
    
    /**
     * Add details about a matching Antimicrobial Resistance Test to be updated.
     * 
     * @param int $resistTestId Antimicrobial Resistance Test ID
     */
    public function addUpdateResistTest($resistTestId)
    {
        $this->updateLab->resistTestId = (int) $resistTestId;
    }

    /**
     * Mark this comparison as part of processing a Clinical Document.
     * 
     * @return EmsaLabCompareResult
     */
    public function setClinicalDocument()
    {
        $this->setLabResultCode(self::COMPARE_RESULT_CLINICAL_DOCUMENT);
        return $this;
    }

    /**
     * Evaluates results of lab result comparison between the EMSA message and the target NEDSS event & sets the corresponding Result Code.
     * 
     * @return EmsaLabCompareResult
     */
    public function evaluateCompareResults(): EmsaLabCompareResult
    {
        // lab test-specific...
        $this->setLabResultCode(self::COMPARE_RESULT_ADD);
        
        if (!empty($this->updateLab->labTestResultId)) {
            $this->setLabResultCode(self::COMPARE_RESULT_UPDATE);
        }
        
        if (count($this->duplicateLabIds) > 0) {
            $this->setLabResultCode(self::COMPARE_RESULT_DUPLICATE);
        }

        if (count($this->exceptionLabIds) > 0) {
            $this->setLabResultCode(self::COMPARE_RESULT_EXCEPTION);
        }

        // antimicrobial resistance test-specific...
        $this->setResistResultCode(self::COMPARE_RESULT_NOT_FOUND);
        
        if ($this->updateLab->resistTestId >= 1) {
            // $this->setResistResultCode(self::COMPARE_RESULT_UPDATE);  //eventually maybe we'll update, but for now, send to Exceptions...
            // existing registration found with different data; send to Exceptions
            $this->setResistResultCode(self::COMPARE_RESULT_EXCEPTION);
        } elseif ($this->updateLab->resistTestId < 0) {
            $this->setResistResultCode(self::COMPARE_RESULT_ADD);
        }
        
        if (count($this->duplicateResistTestIds) > 0) {
            $this->setResistResultCode(self::COMPARE_RESULT_DUPLICATE);
        }

        if (count($this->exceptionResistTestIds) > 0) {
            $this->setResistResultCode(self::COMPARE_RESULT_EXCEPTION);
        }

        return $this;
    }

}

<?php

namespace Udoh\Emsa\Model\Vocabulary;

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

use PDO;
use PDOException;
use Udoh\Emsa\API\Utils;
use Udoh\Emsa\Client\AppClientInterface;
use VocabAudit;

/**
 * ICD Code vocabulary object.
 *
 * @package Udoh\Emsa\Model\Vocabulary
 *
 * @author  Josh Ridderhoff <jridderhoff@utah.gov>
 */
class ICDCode
{
    /**
     * @var int
     */
    protected $id;
    /**
     * @var int
     */
    protected $codesetId;
    /**
     * @var string
     */
    protected $codeValue;
    /**
     * @var string
     */
    protected $codeDescription;
    /**
     * @var int
     */
    protected $masterConditionId;
    /**
     * @var int
     */
    protected $masterSNOMEDId;
    /**
     * @var bool
     */
    protected $pregnancyStatus;
    /**
     * @var bool
     */
    protected $pregnancyIndicator;
    /**
     * @var bool
     */
    protected $allowNewCMR;
    /**
     * @var bool
     */
    protected $allowUpdateCMR;
    /**
     * @var bool
     */
    protected $isSurveillance;

    /**
     * Create a new ICDCode object
     */
    public function __construct()
    {
        $this->pregnancyStatus = false;
        $this->pregnancyIndicator = false;
        $this->allowNewCMR = false;
        $this->allowUpdateCMR = true;
        $this->isSurveillance = false;
    }

    /**
     * @return int
     */
    public function getId(): ?int
    {
        return $this->id;
    }

    /**
     * @return int
     */
    public function getCodesetId(): ?int
    {
        return $this->codesetId;
    }

    /**
     * @return string
     */
    public function getCodeValue(): ?string
    {
        return $this->codeValue;
    }

    /**
     * @return string
     */
    public function getCodeDescription(): ?string
    {
        return $this->codeDescription;
    }

    /**
     * @return int
     */
    public function getMasterConditionId(): ?int
    {
        return $this->masterConditionId;
    }

    /**
     * @return int
     */
    public function getMasterSNOMEDId(): ?int
    {
        return $this->masterSNOMEDId;
    }

    /**
     * @return bool
     */
    public function isPregnancyStatus(): bool
    {
        return $this->pregnancyStatus;
    }

    /**
     * @return bool
     */
    public function isPregnancyIndicator(): bool
    {
        return $this->pregnancyIndicator;
    }

    /**
     * @return bool
     */
    public function isAllowNewCMR(): bool
    {
        return $this->allowNewCMR;
    }

    /**
     * @return bool
     */
    public function isAllowUpdateCMR(): bool
    {
        return $this->allowUpdateCMR;
    }

    /**
     * @return bool
     */
    public function isSurveillance(): bool
    {
        return $this->isSurveillance;
    }

    /**
     * Load values from REST POST request.
     */
    protected function loadValuesFromPost(): void
    {
        $this->codesetId = Utils::getInteger("codeset");
        $this->codeValue = filter_input(INPUT_POST, "icd_code");
        $this->codeDescription = filter_input(INPUT_POST, "code_description");

        if (!empty(Utils::getInteger("master_condition"))) {
            $this->masterConditionId = Utils::getInteger("master_condition");
        }
        if (!empty(Utils::getInteger("master_snomed"))) {
            $this->masterSNOMEDId = Utils::getInteger("master_snomed");
        }

        $this->pregnancyStatus = Utils::getBoolean("pregnancy_status");
        $this->pregnancyIndicator = Utils::getBoolean("pregnancy_indicator");
        $this->allowNewCMR = Utils::getBoolean("allow_new_cmr");
        $this->allowUpdateCMR = Utils::getBoolean("allow_update_cmr");
        $this->isSurveillance = Utils::getBoolean("is_surveillance");
    }

    /**
     * Get all properties of this ICD Code (with the excption of id) as an array.
     *
     * @return array
     */
    protected function getPropsAsArray(): array
    {
        $props = [];

        $props["codeset_id"] = $this->getCodesetId();
        $props["code_value"] = $this->getCodeValue();
        $props["code_description"] = $this->getCodeDescription();
        $props["master_condition_id"] = $this->getMasterConditionId();
        $props["master_snomed_id"] = $this->getMasterSNOMEDId();
        $props["allow_new_cmr"] = $this->isAllowNewCMR();
        $props["allow_update_cmr"] = $this->isAllowUpdateCMR();
        $props["is_surveillance"] = $this->isSurveillance();
        $props["pregnancy_indicator"] = $this->isPregnancyIndicator();
        $props["pregnancy_status"] = $this->isPregnancyStatus();

        return $props;
    }

    /**
     * Create a new ICD Code vocabulary object.
     *
     * @param PDO                $dbConn
     * @param AppClientInterface $authClient Application client used for authentication
     *
     * @return ICDCode
     */
    public function create(PDO $dbConn, AppClientInterface $authClient): ICDCode
    {
        $this->loadValuesFromPost();

        try {
            $sql = "INSERT INTO vocab_icd
                        (codeset_id, code_value, code_description, master_condition_id, master_snomed_id, pregnancy_status, pregnancy_indicator, allow_new_cmr, allow_update_cmr, is_surveillance)
                    VALUES (:codeset_id, :code_value, :code_description, :master_condition_id, :master_snomed_id, :pregnancy_status, :pregnancy_indicator, :allow_new_cmr, :allow_update_cmr, :is_surveillance)
                    RETURNING id;";
            $stmt = $dbConn->prepare($sql);

            $stmt->bindValue(":codeset_id", $this->getCodesetId(), PDO::PARAM_INT);
            $stmt->bindValue(":code_value", $this->getCodeValue(), PDO::PARAM_STR);
            $stmt->bindValue(":code_description", $this->getCodeDescription(), PDO::PARAM_STR);
            $stmt->bindValue(":master_condition_id", $this->getMasterConditionId(), PDO::PARAM_INT);
            $stmt->bindValue(":master_snomed_id", $this->getMasterSNOMEDId(), PDO::PARAM_INT);
            $stmt->bindValue(":allow_new_cmr", $this->isAllowNewCMR(), PDO::PARAM_BOOL);
            $stmt->bindValue(":allow_update_cmr", $this->isAllowUpdateCMR(), PDO::PARAM_BOOL);
            $stmt->bindValue(":is_surveillance", $this->isSurveillance(), PDO::PARAM_BOOL);
            $stmt->bindValue(":pregnancy_indicator", $this->isPregnancyIndicator(), PDO::PARAM_BOOL);
            $stmt->bindValue(":pregnancy_status", $this->isPregnancyStatus(), PDO::PARAM_BOOL);

            if ($stmt->execute()) {
                $this->id = (int) $stmt->fetchColumn(0);

                $va = new VocabAudit($dbConn, $authClient);
                $va->resetAudit();
                $va->setNewVals($va->prepareNewValues(VocabAudit::TABLE_MASTER_ICD, $this->getPropsAsArray()));
                $va->auditVocab($this->getId(), VocabAudit::TABLE_MASTER_ICD, VocabAudit::ACTION_ADD);
            }
        } finally {
            $stmt = null;
            $sql = null;
        }

        return $this;
    }

    /**
     * Returns the specified ICD Code vocabulary object.
     *
     * @param PDO $dbConn
     *
     * @return ICDCode
     */
    public function read(PDO $dbConn): ICDCode
    {
        //todo
        return $this;
    }

    /**
     * Update the specified ICD Code vocabulary object.
     *
     * @param PDO $dbConn
     *
     * @return ICDCode
     */
    public function update(PDO $dbConn): ICDCode
    {
        //todo
        return $this;
    }

    /**
     * Deletes the specified ICD Code vocabulary object.
     *
     * @param PDO $dbConn
     *
     * @return bool
     */
    public function delete(PDO $dbConn): bool
    {
        //todo
    }


}
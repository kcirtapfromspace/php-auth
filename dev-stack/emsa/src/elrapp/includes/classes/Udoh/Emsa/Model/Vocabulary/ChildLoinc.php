<?php

namespace Udoh\Emsa\Model\Vocabulary;

use PDO;
use PDOException;
use Udoh\Emsa\API\Utils;
use Udoh\Emsa\Client\AppClientInterface;
use VocabAudit;

/**
 * Copyright (c) 2019 Utah Department of Technology Services and Utah Department of Health
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
 * @copyright Copyright (c) 2019 Utah Department of Technology Services and Utah Department of Health
 */

/**
 * Child LOINC vocabulary object.
 *
 * @package Udoh\Emsa\Model\Vocabulary
 *
 * @author  Josh Ridderhoff <jridderhoff@utah.gov>
 */
class ChildLoinc
{
    /**
     * @var int
     */
    protected $id;
    /**
     * @var string
     */
    protected $childLOINCCode;
    /**
     * @var string
     */
    protected $childOrderableTestCode;
    /**
     * @var string
     */
    protected $childResultableTestCode;
    /**
     * @var string
     */
    protected $childConceptName;
    /**
     * @var string
     */
    protected $childAlias;
    /**
     * @var int
     */
    protected $masterLoinc;
    /**
     * @var int
     */
    protected $labId;
    /**
     * @var string
     */
    protected $units;
    /**
     * @var string
     */
    protected $refrange;
    /**
     * @var int
     */
    protected $resultLocation;
    /**
     * @var bool
     */
    protected $interpretResults;
    /**
     * @var string
     */
    protected $hl7Refrange;
    /**
     * @var bool
     */
    protected $pregnancy;
    /**
     * @var bool
     */
    protected $archived;
    /**
     * @var int
     */
    protected $workflow;
    /**
     * @var string
     */
    protected $adminNotes;
    /**
     * @var bool
     */
    protected $allowPreprocessing;
    /**
     * @var int
     */
    protected $offscaleLowResult;
    /**
     * @var int
     */
    protected $offscaleHighResult;
    /**
     * @var bool
     */
    protected $interpretOverride;

    /**
     * Create a new ChildLoinc object
     */
    public function __construct()
    {
        $this->masterLoinc = -1;
        $this->resultLocation = -1;
        $this->interpretResults = true;
        $this->pregnancy = false;
        $this->archived = false;
        $this->workflow = ENTRY_STATUS;
        $this->allowPreprocessing = false;
    }

    /**
     * @return int
     */
    public function getId(): ?int
    {
        return $this->id;
    }

    /**
     * @return string
     */
    public function getChildLOINCCode(): ?string
    {
        return $this->childLOINCCode;
    }

    /**
     * @return string
     */
    public function getChildOrderableTestCode(): ?string
    {
        return $this->childOrderableTestCode;
    }

    /**
     * @return string
     */
    public function getChildResultableTestCode(): ?string
    {
        return $this->childResultableTestCode;
    }

    /**
     * @return string
     */
    public function getChildConceptName(): ?string
    {
        return $this->childConceptName;
    }

    /**
     * @return string
     */
    public function getChildAlias(): ?string
    {
        return $this->childAlias;
    }

    /**
     * @return int
     */
    public function getMasterLoinc(): ?int
    {
        return $this->masterLoinc;
    }

    /**
     * @return int
     */
    public function getLabId(): ?int
    {
        return $this->labId;
    }

    /**
     * @return string
     */
    public function getUnits(): ?string
    {
        return $this->units;
    }

    /**
     * @return string
     */
    public function getRefrange(): ?string
    {
        return $this->refrange;
    }

    /**
     * @return int
     */
    public function getResultLocation(): ?int
    {
        return $this->resultLocation;
    }

    /**
     * @return bool
     */
    public function isInterpretResults(): ?bool
    {
        return $this->interpretResults;
    }

    /**
     * @return string
     */
    public function getHl7Refrange(): ?string
    {
        return $this->hl7Refrange;
    }

    /**
     * @return bool
     */
    public function isPregnancy(): ?bool
    {
        return $this->pregnancy;
    }

    /**
     * @return bool
     */
    public function isArchived(): ?bool
    {
        return $this->archived;
    }

    /**
     * @return int
     */
    public function getWorkflow(): ?int
    {
        return $this->workflow;
    }

    /**
     * @return string
     */
    public function getAdminNotes(): ?string
    {
        return $this->adminNotes;
    }

    /**
     * @return bool
     */
    public function isAllowPreprocessing(): ?bool
    {
        return $this->allowPreprocessing;
    }

    /**
     * @return int
     */
    public function getOffscaleLowResult(): ?int
    {
        return $this->offscaleLowResult;
    }

    /**
     * @return int
     */
    public function getOffscaleHighResult(): ?int
    {
        return $this->offscaleHighResult;
    }

    /**
     * @return bool
     */
    public function isInterpretOverride(): ?bool
    {
        return $this->interpretOverride;
    }

    /**
     * Load values from REST POST request.
     */
    protected function loadValuesFromPost(): void
    {
        $this->childLOINCCode = filter_input(INPUT_POST, "child_loinc");
        $this->childOrderableTestCode = filter_input(INPUT_POST, "child_orderable_test_code");
        $this->childResultableTestCode = filter_input(INPUT_POST, "child_resultable_test_code");
        $this->childConceptName = filter_input(INPUT_POST, "child_concept_name");
        $this->childAlias = filter_input(INPUT_POST, "child_alias");

        if (!empty(Utils::getInteger("master_loinc"))) {
            $this->masterLoinc = Utils::getInteger("master_loinc");
        }

        $this->labId = Utils::getInteger("lab_id");
        $this->units = filter_input(INPUT_POST, "units");
        $this->refrange = filter_input(INPUT_POST, "refrange");

        if (!empty(Utils::getInteger("result_location"))) {
            $this->resultLocation = Utils::getInteger("result_location");
        }

        if (!empty(Utils::getBoolean("interpret_results"))) {
            $this->interpretResults = Utils::getBoolean("interpret_results");
        }

        $this->hl7Refrange = filter_input(INPUT_POST, "hl7_refrange");

        if (!empty(Utils::getBoolean("pregnancy"))) {
            $this->pregnancy = Utils::getBoolean("pregnancy");
        }

        if (!empty(Utils::getBoolean("archived"))) {
            $this->archived = Utils::getBoolean("archived");
        }

        if (!empty(Utils::getInteger("workflow"))) {
            $this->workflow = Utils::getInteger("workflow");
        }

        $this->adminNotes = filter_input(INPUT_POST, "admin_notes");

        if (!empty(Utils::getBoolean("allow_preprocessing"))) {
            $this->allowPreprocessing = Utils::getBoolean("allow_preprocessing");
        }

        $this->offscaleLowResult = Utils::getInteger("offscale_low_result");
        $this->offscaleHighResult = Utils::getInteger("offscale_high_result");
        $this->interpretOverride = Utils::getBoolean("interpret_override");
    }

    /**
     * Get all properties of this Child LOINC (with the excption of id) as an array.
     *
     * @return array
     */
    protected function getPropsAsArray(): array
    {
        $props = [];

        $props["child_loinc"] = $this->getChildLOINCCode();
        $props["child_orderable_test_code"] = $this->getChildOrderableTestCode();
        $props["child_resultable_test_code"] = $this->getChildResultableTestCode();
        $props["child_concept_name"] = $this->getChildConceptName();
        $props["child_alias"] = $this->getChildAlias();
        $props["master_loinc"] = $this->getMasterLoinc();
        $props["lab_id"] = $this->getLabId();
        $props["units"] = $this->getUnits();
        $props["refrange"] = $this->getRefrange();
        $props["result_location"] = $this->getResultLocation();
        $props["interpret_results"] = $this->isInterpretResults();
        $props["hl7_refrange"] = $this->getHl7Refrange();
        $props["pregnancy"] = $this->isPregnancy();
        $props["archived"] = $this->isArchived();
        $props["workflow"] = $this->getWorkflow();
        $props["admin_notes"] = $this->getAdminNotes();
        $props["allow_preprocessing"] = $this->isAllowPreprocessing();
        $props["offscale_low_result"] = $this->getOffscaleLowResult();
        $props["offscale_high_result"] = $this->getOffscaleHighResult();
        $props["interpret_override"] = $this->isInterpretOverride();

        return $props;
    }

    /**
     * Create a new Child LOINC vocabulary object.
     *
     * @param PDO                $dbConn
     * @param AppClientInterface $authClient Application client used for authentication
     *
     * @return ChildLoinc
     */
    public function create(PDO $dbConn, AppClientInterface $authClient): ChildLoinc
    {
        $this->loadValuesFromPost();

        try {
            $sql = "INSERT INTO vocab_child_loinc
                        (child_loinc, child_orderable_test_code, child_resultable_test_code, child_concept_name, child_alias, master_loinc, lab_id, units, refrange, result_location, interpret_results, hl7_refrange, pregnancy, archived, workflow, admin_notes, allow_preprocessing, offscale_low_result, offscale_high_result, interpret_override)
                    VALUES (:child_loinc, :child_orderable_test_code, :child_resultable_test_code, :child_concept_name, :child_alias, :master_loinc, :lab_id, :units, :refrange, :result_location, :interpret_results, :hl7_refrange, :pregnancy, :archived, :workflow, :admin_notes, :allow_preprocessing, :offscale_low_result, :offscale_high_result, :interpret_override)
                    RETURNING id;";
            $stmt = $dbConn->prepare($sql);

            $stmt->bindValue(":child_loinc", $this->getChildLOINCCode(), PDO::PARAM_STR);
            $stmt->bindValue(":child_orderable_test_code", $this->getChildOrderableTestCode(), PDO::PARAM_STR);
            $stmt->bindValue(":child_resultable_test_code", $this->getChildResultableTestCode(), PDO::PARAM_STR);
            $stmt->bindValue(":child_concept_name", $this->getChildConceptName(), PDO::PARAM_STR);
            $stmt->bindValue(":child_alias", $this->getChildAlias(), PDO::PARAM_STR);
            $stmt->bindValue(":master_loinc", $this->getMasterLoinc(), PDO::PARAM_INT);
            $stmt->bindValue(":lab_id", $this->getLabId(), PDO::PARAM_INT);
            $stmt->bindValue(":units", $this->getUnits(), PDO::PARAM_STR);
            $stmt->bindValue(":refrange", $this->getRefrange(), PDO::PARAM_STR);
            $stmt->bindValue(":result_location", $this->getResultLocation(), PDO::PARAM_INT);
            $stmt->bindValue(":interpret_results", $this->isInterpretResults(), PDO::PARAM_BOOL);
            $stmt->bindValue(":hl7_refrange", $this->getHl7Refrange(), PDO::PARAM_STR);
            $stmt->bindValue(":pregnancy", $this->isPregnancy(), PDO::PARAM_BOOL);
            $stmt->bindValue(":archived", $this->isArchived(), PDO::PARAM_BOOL);
            $stmt->bindValue(":workflow", $this->getWorkflow(), PDO::PARAM_INT);
            $stmt->bindValue(":admin_notes", $this->getAdminNotes(), PDO::PARAM_STR);
            $stmt->bindValue(":allow_preprocessing", $this->isAllowPreprocessing(), PDO::PARAM_BOOL);
            $stmt->bindValue(":offscale_low_result", $this->getOffscaleLowResult(), PDO::PARAM_INT);
            $stmt->bindValue(":offscale_high_result", $this->getOffscaleHighResult(), PDO::PARAM_INT);
            $stmt->bindValue(":interpret_override", $this->isInterpretOverride(), PDO::PARAM_BOOL);

            if ($stmt->execute()) {
                $this->id = (int) $stmt->fetchColumn(0);

                $va = new VocabAudit($dbConn, $authClient);
                $va->resetAudit();
                $va->setNewVals($va->prepareNewValues(VocabAudit::TABLE_CHILD_LOINC, $this->getPropsAsArray()));
                $va->auditVocab($this->getId(), VocabAudit::TABLE_CHILD_LOINC, VocabAudit::ACTION_ADD);
            }
        } finally {
            $stmt = null;
            $sql = null;
        }

        return $this;
    }

    /**
     * Returns the specified Child LOINC vocabulary object.
     *
     * @param PDO $dbConn
     *
     * @return ChildLoinc
     */
    public function read(PDO $dbConn): ChildLoinc
    {
        //todo
        return $this;
    }

    /**
     * Update the specified Child LOINC vocabulary object.
     *
     * @param PDO $dbConn
     *
     * @return ChildLoinc
     */
    public function update(PDO $dbConn): ChildLoinc
    {
        //todo
        return $this;
    }

    /**
     * Deletes the specified Child LOINC vocabulary object.
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
<?php

namespace Udoh\Emsa\Model\Vocabulary;

/**
 * Copyright (c) 2022 Utah Department of Technology Services and Utah Department of Health
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
 * @copyright Copyright (c) 2022 Utah Department of Technology Services and Utah Department of Health
 */
use PDO;
use Udoh\Emsa\API\Utils;
use Udoh\Emsa\Client\AppClientInterface;
use VocabAudit;

/**
 * Child Dictionary vocabulary object.
 *
 * @package Udoh\Emsa\Model\Vocabulary
 *
 * @author  Josh Ridderhoff <jridderhoff@utah.gov>
 */
class ChildDictionary
{
    /** @var int */
    protected $id;
    /** @var int */
    protected $labId;
    /** @var int */
    protected $masterId;
    /** @var string */
    protected $concept;
    /** @var string */
    protected $comment;

    /**
     * ChildDictionary constructor.
     */
    public function __construct()
    {
        $this->labId = -1;
        $this->masterId = -1;
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
    public function getLabId(): int
    {
        return $this->labId;
    }

    /**
     * @return int
     */
    public function getMasterId(): int
    {
        return $this->masterId;
    }

    /**
     * @return string
     */
    public function getConcept(): ?string
    {
        return $this->concept;
    }

    /**
     * @return string
     */
    public function getComment(): ?string
    {
        return $this->comment;
    }

    /**
     * Load values from REST POST request.
     */
    protected function loadValuesFromPost(): void
    {
        if (!empty(Utils::getInteger("master_id"))) {
            $this->masterId = Utils::getInteger("master_id");
        }

        $this->labId = Utils::getInteger("lab_id");
        $this->concept = filter_input(INPUT_POST, "child_concept");
        $this->comment = filter_input(INPUT_POST, "comment");
    }

    /**
     * Get all properties of this Child Dictionary (with the excption of id) as an array.
     *
     * @return array
     */
    protected function getPropsAsArray(): array
    {
        $props = [];

        $props["master_id"] = $this->getMasterId();
        $props["lab_id"] = $this->getLabId();
        $props["child_code"] = $this->getConcept();
        $props["comment"] = $this->getComment();

        return $props;
    }

    /**
     * Create a new Child Dictionary vocabulary object.
     *
     * @param PDO                $dbConn
     * @param AppClientInterface $authClient Application client used for authentication
     *
     * @return ChildDictionary
     */
    public function create(PDO $dbConn, AppClientInterface $authClient): ChildDictionary
    {
        $this->loadValuesFromPost();

        try {
            $sql = "INSERT INTO vocab_child_vocab
                        (concept, master_id, lab_id, comment)
                    VALUES (:child_concept, :master_id, :lab_id, :comment)
                    RETURNING id;";
            $stmt = $dbConn->prepare($sql);

            $stmt->bindValue(":child_concept", $this->getConcept(), PDO::PARAM_STR);
            $stmt->bindValue(":comment", $this->getComment(), PDO::PARAM_STR);
            $stmt->bindValue(":master_id", $this->getMasterId(), PDO::PARAM_INT);
            $stmt->bindValue(":lab_id", $this->getLabId(), PDO::PARAM_INT);

            if ($stmt->execute()) {
                $this->id = (int) $stmt->fetchColumn(0);

                $va = new VocabAudit($dbConn, $authClient);
                $va->resetAudit();
                $va->setNewVals($va->prepareNewValues(VocabAudit::TABLE_CHILD_VOCAB, $this->getPropsAsArray()));
                $va->auditVocab($this->getId(), VocabAudit::TABLE_CHILD_VOCAB, VocabAudit::ACTION_ADD);
            }
        } finally {
            $stmt = null;
            $sql = null;
        }

        return $this;
    }

    /**
     * Returns the specified Child Dictionary vocabulary object.
     *
     * @param PDO $dbConn
     *
     * @return ChildDictionary
     */
    public function read(PDO $dbConn): ChildDictionary
    {
        //todo
        return $this;
    }

    /**
     * Update the specified Child Dictionary vocabulary object.
     *
     * @param PDO $dbConn
     *
     * @return ChildDictionary
     */
    public function update(PDO $dbConn): ChildDictionary
    {
        //todo
        return $this;
    }

    /**
     * Deletes the specified Child Dictionary vocabulary object.
     *
     * @param PDO $dbConn
     *
     * @return bool
     */
    public function delete(PDO $dbConn): bool
    {
        //todo
        return false;
    }


}
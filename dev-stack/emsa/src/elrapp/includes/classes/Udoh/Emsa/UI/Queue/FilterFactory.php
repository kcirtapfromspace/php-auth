<?php

namespace Udoh\Emsa\UI\Queue;

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

use PDO;
use EmsaUtils;
use Throwable;
use Udoh\Emsa\Utils\CoreUtils;
use Udoh\Emsa\Utils\ExceptionUtils;
use Udoh\Emsa\Utils\DisplayUtils;

/**
 * Factory for generating database-driven UI form filter elements
 *
 * @author Josh Ridderhoff <jridderhoff@utah.gov>
 */
class FilterFactory
{

    /** @var PDO */
    private $dbConn;

    public function __construct(PDO $dbConn)
    {
        $this->dbConn = $dbConn;
    }

    /**
     * Get a list of filter values back from a SQL query statement.
     *
     * @param PDO    $db
     * @param string $sql      SQL query to retrieve the filter options
     *
     * @return array
     */
    public static function getListFromQuery(PDO $db, string $sql): array
    {
        $values = [];

        try {
            $stmt = $db->query($sql);

            while ($row = $stmt->fetchObject()) {
                if (!empty($row->snomed)) {
                    $labelModifier = ' [' . trim($row->snomed) . ']';
                } else {
                    $labelModifier = "";
                }

                if (is_bool($row->value)) {
                    // ugly backward-compatible hack to support the way EMSA was coded to deal with pg_query results for boolean columns
                    if ($row->value) {
                        $values["t"] = $row->label . $labelModifier;
                    } else {
                        $values["f"] = $row->label . $labelModifier;
                    }
                } else {
                    $values[$row->value] = $row->label . $labelModifier;
                }
            }
        } catch (Throwable $e) {
            ExceptionUtils::logException($e);
        } finally {
            $row = null;
            $stmt = null;
        }

        return $values;
    }

    /**
     * EMSA 'Show Deleted?'
     * 
     * @return array
     */
    public function getShowDeleted(): array
    {
        return [1 => "Yes"];
    }

    /**
     * Get EMSA Condition List for use with Filter-related functions.
     *
     * @return array
     */
    public function getConditionList(): array
    {
        $values = [];

        try {
            $sql = "SELECT DISTINCT mv.concept 
					FROM vocab_master_vocab mv 
					INNER JOIN vocab_master_condition mc ON (mv.id = mc.condition)
					WHERE mv.category = vocab_category_id('condition') 
					AND mc.is_initial IS TRUE
					ORDER BY mv.concept ASC;";

            $stmt = $this->dbConn->query($sql);

            while ($row = $stmt->fetchObject()) {
                $values[base64_encode($row->concept)] = trim($row->concept);
            }
        } catch (Throwable $e) {
            ExceptionUtils::logException($e);
        } finally {
            $row = null;
            $stmt = null;
            $sql = null;
        }

        return $values;
    }

    /**
     * @return array
     */
    public function getReporterList(): array
    {
        $values = [];

        try {
            $sql = "SELECT DISTINCT id, ui_name 
					FROM structure_labs
					WHERE visible IS TRUE
                    AND alias_for = 0
					ORDER BY ui_name ASC;";

            $stmt = $this->dbConn->query($sql);

            while ($row = $stmt->fetchObject()) {
                $values[(int) $row->id] = trim($row->ui_name);
            }
        } catch (Throwable $e) {
            ExceptionUtils::logException($e);
        } finally {
            $row = null;
            $stmt = null;
            $sql = null;
        }

        return $values;
    }

    /**
     * ELR Connector multi-check
     *
     * @return array
     */
    public function getELRConnectorList(): array
    {
        $connectors = [];
        $connectorList = CoreUtils::getELRConnectorList($this->dbConn);

        if (!empty($connectorList)) {
            foreach ($connectorList as $connector) {
                $connectors[$connector] = $connector;
            }
        }

        return $connectors;
    }

    /**
     * EMSA 'Manual vs Automated' multi-check
     * @return string
     */
    public function getAutomation(): array
    {
        return [
            0 => 'Only Automated Messages',
            1 => 'Only Non-Automated Messages'
        ];
    }

    /**
     * EMSA Message Flags multi-check
     *
     * @return array
     */
    public function getEMSAMessageFlags(): array
    {
        $values = [];

        try {
            $sql = "SELECT id, label AS concept 
                    FROM system_message_flags 
                    ORDER BY label ASC;";

            $stmt = $this->dbConn->query($sql);

            while ($row = $stmt->fetchObject()) {
                $values[(int) pow(2, $row->id)] = trim($row->concept);
            }
        } catch (Throwable $e) {
            ExceptionUtils::logException($e);
        } finally {
            $row = null;
            $stmt = null;
            $sql = null;
        }

        return $values;
    }

    /**
     * EMSA Exception Type multi-check
     *
     * @return array
     */
    public function getExceptionTypeList(): array
    {
        $values = [];

        try {
            $sql = "SELECT exception_id, description 
                    FROM system_exceptions 
                    ORDER BY description ASC;";

            $stmt = $this->dbConn->query($sql);

            while ($row = $stmt->fetchObject()) {
                $values[(int) $row->exception_id] = trim($row->description);
            }
        } catch (Throwable $e) {
            ExceptionUtils::logException($e);
        } finally {
            $row = null;
            $stmt = null;
            $sql = null;
        }

        return $values;
    }

    /**
     * EMSA Clinician Name multi-check
     *
     * @return array
     */
    public function getGraylistClinicianList(): array
    {
        $values = [];

        //todo:  dynamically get appId instead of hard-coding
        try {
            $sql = "SELECT DISTINCT clinician 
                    FROM system_messages 
                    WHERE final_status = 2
                    AND vocab_app_id = 2;";

            $stmt = $this->dbConn->query($sql);

            while ($row = $stmt->fetchObject()) {
                $values[trim($row->clinician)] = trim($row->clinician);
            }
        } catch (Throwable $e) {
            ExceptionUtils::logException($e);
        } finally {
            $row = null;
            $stmt = null;
            $sql = null;
        }

        return $values;
    }

    /**
     * Get list of Test Types for filters.
     *
     * @return array
     */
    public function getTestTypeList(): array
    {
        $values = [];

        try {
            $sql = "SELECT id, concept 
                    FROM vocab_master_vocab 
                    WHERE category = vocab_category_id('test_type') 
                    ORDER BY concept ASC;";

            $stmt = $this->dbConn->query($sql);

            while ($row = $stmt->fetchObject()) {
                $values[(int) $row->id] = trim($row->concept);
            }
        } catch (Throwable $e) {
            ExceptionUtils::logException($e);
        } finally {
            $row = null;
            $stmt = null;
            $sql = null;
        }

        return $values;
    }

    /**
     * @return array
     */
    public function getTestResultList(): array
    {
        $values = [];

        //todo:  dynamically get appId instead of hard-coding
        try {
            $sql = "SELECT m.concept AS concept, a.coded_value AS coded_value 
                    FROM vocab_master_vocab m
                    INNER JOIN vocab_master2app a ON (a.master_id = m.id) 
                    WHERE m.category = vocab_category_id('test_result') 
                    AND a.app_id = 2
                    ORDER BY m.concept ASC;";

            $stmt = $this->dbConn->query($sql);

            while ($row = $stmt->fetchObject()) {
                $values[trim($row->coded_value)] = trim($row->concept);
            }
        } catch (Throwable $e) {
            ExceptionUtils::logException($e);
        } finally {
            $row = null;
            $stmt = null;
            $sql = null;
        }

        return $values;
    }

    /**
     * @return array
     */
    public function getSusceptibilityResultList(): array
    {
        $values = [];

        //todo:  dynamically get appId instead of hard-coding
        try {
            $sql = "SELECT m.concept || ' (AST)' AS concept, a.coded_value AS coded_value, 'AST' as test_result_type 
                    FROM vocab_master_vocab m
                    INNER JOIN vocab_master2app a ON (a.master_id = m.id) 
                    WHERE m.category = vocab_category_id('resist_test_result') 
                    AND a.app_id = 2
                    UNION ALL
                    SELECT m.concept || ' (Labs)' AS concept, a.coded_value AS coded_value, 'Labs' as test_result_type 
                    FROM vocab_master_vocab m
                    INNER JOIN vocab_master2app a ON (a.master_id = m.id) 
                    WHERE m.category = vocab_category_id('test_result') 
                    AND a.app_id = 2
                    ORDER BY test_result_type DESC, concept;";

            $stmt = $this->dbConn->query($sql);

            while ($row = $stmt->fetchObject()) {
                $values[trim($row->coded_value)] = trim($row->concept);
            }
        } catch (Throwable $e) {
            ExceptionUtils::logException($e);
        } finally {
            $row = null;
            $stmt = null;
            $sql = null;
        }

        return $values;
    }

}

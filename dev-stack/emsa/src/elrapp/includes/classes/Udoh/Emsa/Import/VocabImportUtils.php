<?php

namespace Udoh\Emsa\Import;

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

use PDO;
use PDOStatement;
use Throwable;
use Udoh\Emsa\Utils\CoreUtils;
use Udoh\Emsa\Utils\ExceptionUtils;

/**
 * Utilities for working with Vocabulary Import/Export in EMSA
 *
 * @author Josh Ridderhoff <jridderhoff@utah.gov>
 */
class VocabImportUtils extends ImportUtils
{

    /**
     * Decodes JSON-encoded interpretive rules data and converts it into exportable text.
     *
     * @param PDO $dbConn Connection to the EMSA database.
     * @param string $jsonString JSON-encoded object to decode.
     * @param string $targetVar Variable to retrieve from the selected object.
     * @param int $index Zero-based integer representing the index of the decoded object to search for.  [Optional; Defaults to first object if not specified.]
     * @param bool $lookupAppValue Indicates whether the value decoded from the JSON object is a vocabulary ID that should be looked up [Optional; Default FALSE]
     * @param int $appId Specifies the application ID to look up values if $lookupAppValue is TRUE. [Optional; Defaults to EpiTrax]
     * 
     * @return string Value from index->targetVar, if present.  Empty string otherwise.
     */
    public static function decodeJSONForExport(PDO $dbConn, $jsonString = '', $targetVar = '', $index = 0, $lookupAppValue = false, $appId = 2)
    {
        $decodedString = '';
        
        if (empty($jsonString) || empty($targetVar)) {
            return $decodedString;
        }

        $jsonArray = @json_decode($jsonString);
        
        if (isset($jsonArray) && ($jsonArray !== false) && is_array($jsonArray)) {
            if ($lookupAppValue && isset($jsonArray[$index]->$targetVar)) {
                if (intval($jsonArray[$index]->$targetVar) === -1) {
                    $decodedString = 'Any';
                } else {
                    $decodedString = self::getAppValueForDecodedID($dbConn, intval($jsonArray[$index]->$targetVar), intval($appId));
                }
            } elseif (isset($jsonArray[$index]->$targetVar)) {
                $decodedString = trim($jsonArray[$index]->$targetVar);
            }
        }

        if ((stripos($targetVar, 'operator') === 0) && !is_null($decodedString) && !empty($decodedString)) {
            $decodedString = CoreUtils::operatorById(intval($decodedString));
            if ($decodedString == '==') {
                $decodedString = ' =';
            }
            if ($decodedString == '!=') {
                $decodedString = '<>';
            }
        }

        return $decodedString;
    }
    
    /**
     * Run a specified export query and return the results as a PDOStatement
     * 
     * @param PDO $dbConn Connection to the EMSA database.
     * @param string $sql Query to execute.
     * @param int $labId [Optional] Lab ID for child exports.
     * 
     * @return PDOStatement
     */
    public static function getExportRecordSet(PDO $dbConn, $sql, $labId = null)
    {
        try {
            $stmt = $dbConn->prepare($sql);
            
            if (!empty($labId)) {
                $stmt->bindValue(':labId', (int) $labId, PDO::PARAM_INT);
            }
            
            $stmt->execute();
            
            return $stmt;
        } catch (Throwable $e) {
            ExceptionUtils::logException($e);
            return null;
        }
    }
    
    /**
     * Convert string values (such as "Yes", "No", and "Unknown") used during imports to boolean strings usable for database queries.
     * 
     * @param string $input
     * 
     * @return string "TRUE", "FALSE", or "NULL".
     */
    public static function getBooleanExprFromString($input = null)
    {
        $convertedVal = "NULL";
        
        if (!empty($input)) {
            if (strtolower($input) == "yes") {
                $convertedVal = "TRUE";
            } elseif (strtolower($input) == "force semi-auto") {
                $convertedVal = "TRUE";
            } elseif (strtolower($input) == "override quantitative") {
                $convertedVal = "TRUE";
            } elseif (strtolower($input) == "no") {
                $convertedVal = "FALSE";
            } elseif (strtolower($input) == "skip semi-auto") {
                $convertedVal = "TRUE";
            } elseif (strtolower($input) == "override coded entry") {
                $convertedVal = "TRUE";
            }
        }
        
        return $convertedVal;
    }
    
    /**
     * Get the ID of a Master Condition object based on condition name.
     * 
     * @param PDO $dbConn Connection to the EMSA database.
     * @param string $conditionName Condition name to look up
     * 
     * @return int
     */
    public static function getMasterConditionId(PDO $dbConn, $conditionName)
    {
        $masterConditionId = "NULL";
        
        if (empty($conditionName)) {
            return $masterConditionId;
        }
        
        try {
            $sql = "SELECT vmc.c_id AS id
                FROM vocab_master_condition vmc
                INNER JOIN vocab_master_vocab mv ON (vmc.condition = mv.id)
                WHERE mv.concept ILIKE :conditionName
                AND mv.category = vocab_category_id('condition');";
            $stmt = $dbConn->prepare($sql);
            $stmt->bindValue(':conditionName', $conditionName, PDO::PARAM_STR);
            
            if ($stmt->execute()) {
                $masterConditionId = $stmt->fetchColumn(0);
            }
        } catch (Throwable $e) {
            ExceptionUtils::logException($e);
        }
        
        return $masterConditionId;
    }
    
    /**
     * Get the ID of a Master SNOMED object based on lookup criteria.
     * 
     * @param PDO $dbConn Connection to the EMSA database.
     * @param string $snomedCode    [Optional] SNOMED code to look up
     * @param string $snomedCodeAlt [Optional] SNOMED code to look up
     * @param string $orgName       [Optional] SNOMED code to look up
     * 
     * @return int
     */
    public static function getMasterSNOMEDId(PDO $dbConn, $snomedCode = null, $snomedCodeAlt = null, $orgName = null)
    {
        $masterSNOMEDId = "NULL";
        
        if (empty($snomedCode) && empty($snomedCodeAlt) && empty($orgName)) {
            return $masterSNOMEDId;
        }
        
        try {
            $sql = "SELECT vmo.o_id AS id
                FROM vocab_master_organism vmo\n";
            
            $whereClauseItems = array();
            
            if (!empty($orgName)) {
                $sql .= "INNER JOIN vocab_master_vocab mv ON (vmo.organism = mv.id)\n";
                $whereClauseItems[] = "mv.concept ILIKE :orgName";
                $whereClauseItems[] = "mv.category = vocab_category_id('organism')";
            }
            if (!empty($snomedCode)) {
                $whereClauseItems[] = "vmo.snomed = :snomedCode";
            }
            if (!empty($snomedCodeAlt)) {
                $whereClauseItems[] = "vmo.snomed_alt = :snomedCodeAlt";
            }
            
            $whereClause = implode(" AND ", $whereClauseItems);
            $sql .= "WHERE {$whereClause};";
            
            $stmt = $dbConn->prepare($sql);
            
            if (!empty($orgName)) {
                $stmt->bindValue(':orgName', $orgName, PDO::PARAM_STR);
            }
            if (!empty($snomedCode)) {
                $stmt->bindValue(':snomedCode', $snomedCode, PDO::PARAM_STR);
            }
            if (!empty($snomedCodeAlt)) {
                $stmt->bindValue(':snomedCodeAlt', $snomedCodeAlt, PDO::PARAM_STR);
            }
            
            if ($stmt->execute()) {
                $masterSNOMEDId = $stmt->fetchColumn(0);
            }
        } catch (Throwable $e) {
            ExceptionUtils::logException($e);
        }
        
        return $masterSNOMEDId;
    }

}

<?php

namespace Udoh\Emsa\Model;

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

use Throwable;

/**
 * Vocabulary data manipulation functions
 * 
 * @package Udoh\Emsa\Model
 *
 * @author Josh Ridderhoff <jridderhoff@utah.gov>
 */
class Vocabulary
{
    /**
     * Add a new entry to the Master Dictionary table.
     * 
     * @param \PDO $dbConn
     * @param int $category Category ID
     * @param string $masterConcept Master concept name
     * @param string $valueSet [Optional]<br>Value Set
     * 
     * @return int PK ID from the newly-inserted row.
     */
    public static function addMasterDictionary(\PDO $dbConn, $category, $masterConcept, $valueSet = null)
    {
        try {
            $sql = "INSERT INTO vocab_master_vocab (category, codeset, concept)
                    VALUES (:category, :codeset, :concept)
                    RETURNING id;";
            $stmt = $dbConn->prepare($sql);
            $stmt->bindValue(':category', $category, \PDO::PARAM_INT);
            $stmt->bindValue(':concept', $masterConcept, \PDO::PARAM_STR);
            $stmt->bindValue(':codeset', $valueSet, \PDO::PARAM_STR);
            
            $stmt->execute();
            
            $newMasterId = (int) filter_var($stmt->fetchColumn(0), \FILTER_SANITIZE_NUMBER_INT);
        } catch (Throwable $e) {
            \Udoh\Emsa\Utils\ExceptionUtils::logException($e);
            $newMasterId = 0;
        }
        
        return $newMasterId;
    }
    
    /**
     * Add a new entry to the Master-to-Application translation table.
     * 
     * @param \PDO $dbConn
     * @param int $masterVocabId Master Dictionary entry to translate
     * @param int $appId Application ID to translate for
     * @param string $appConcept [Optional]<br>Application-specific translation (concept name)
     * 
     * @return int PK ID from the newly-inserted row.
     */
    public static function addMasterToAppTranslation(\PDO $dbConn, $masterVocabId, $appId, $appConcept = null)
    {
        try {
            $sql = "INSERT INTO vocab_master2app (app_id, master_id, coded_value)
                    VALUES (:appId, :masterId, :codedValue)
                    RETURNING id;";
            $stmt = $dbConn->prepare($sql);
            $stmt->bindValue(':appId', $appId, \PDO::PARAM_INT);
            $stmt->bindValue(':masterId', $masterVocabId, \PDO::PARAM_INT);
            $stmt->bindValue(':codedValue', $appConcept, \PDO::PARAM_STR);
            $stmt->execute();
            
            $newMasterToAppId = (int) filter_var($stmt->fetchColumn(0), \FILTER_SANITIZE_NUMBER_INT);
        } catch (Throwable $e) {
            \Udoh\Emsa\Utils\ExceptionUtils::logException($e);
            $newMasterToAppId = 0;
        }
        
        return $newMasterToAppId;
    }
    
    /**
     * Delete a specified Master Dictionary entry and its associated Application-specific translations
     * 
     * @param \PDO $dbConn
     * @param int $masterVocabId Master Dictionary entry to translate
     * 
     * @return boolean
     */
    public static function deleteMasterDictionary(\PDO $dbConn, $masterVocabId)
    {
        try {
            // wrap in a transaction to make sure that we don't accidentally wipe out app-specific values 
            // and then abort on the master vocab delete
            $dbConn->beginTransaction();
        } catch (Throwable $e) {
            \Udoh\Emsa\Utils\ExceptionUtils::logException($e);
            return false;
        }
        
        try {
            $m2aSql = "DELETE FROM ONLY vocab_master2app WHERE master_id = :masterId;";
            $m2aStmt = $dbConn->prepare($m2aSql);
            $m2aStmt->bindValue(':masterId', (int) $masterVocabId, \PDO::PARAM_INT);
            $m2aStmt->execute();
        } catch (Throwable $e) {
            \Udoh\Emsa\Utils\ExceptionUtils::logException($e);
            $dbConn->rollBack();
            return false;
        }
        
        try {
            $masterSql = "DELETE FROM ONLY vocab_master_vocab WHERE id = :masterId;";
            $masterStmt = $dbConn->prepare($masterSql);
            $masterStmt->bindValue(':masterId', (int) $masterVocabId, \PDO::PARAM_INT);
            $masterStmt->execute();
        } catch (Throwable $e) {
            \Udoh\Emsa\Utils\ExceptionUtils::logException($e);
            $dbConn->rollBack();
            return false;
        }
        
        try {
            $dbConn->commit();
        } catch (Throwable $e) {
            \Udoh\Emsa\Utils\ExceptionUtils::logException($e);
            return false;
        }
        
        return true;
    }
}

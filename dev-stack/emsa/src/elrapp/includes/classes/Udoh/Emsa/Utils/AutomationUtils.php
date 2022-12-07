<?php

namespace Udoh\Emsa\Utils;

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
 * @copyright Copyright (c) 2016-2017 Utah Department of Technology Services and Utah Department of Health
 */

use PDO;
use EmsaMessage;
use Throwable;

/**
 * Utilities used by Automated Message Processing
 *
 * @author Josh Ridderhoff <jridderhoff@utah.gov>
 */
class AutomationUtils
{

    /**
     * Checks for Semi-Auto Usage flag from a Master SNOMED.
     *
     * @param PDO         $dbConn
     * @param EmsaMessage $emsaMessage
     *
     * @return bool <b>TRUE</b> if Semi-Auto required, <b>FALSE</b> if Semi-Auto should be skipped, <b>NULL</b> if Child LOINC setting should be honored.
     */
    public static function getSNOMEDWorkflowOverride(PDO $dbConn, EmsaMessage $emsaMessage): ?bool
    {
        $workflowOverride = null;
        
        $masterSNOMEDId = $emsaMessage->getMasterSNOMEDId();
        
        if ($masterSNOMEDId > 0) {
            try {
                $sql = "SELECT semi_auto_usage 
                        FROM vocab_master_organism
                        WHERE o_id = :snomedId;";
                $stmt = $dbConn->prepare($sql);
                $stmt->bindValue(':snomedId', $masterSNOMEDId, PDO::PARAM_INT);

                if ($stmt->execute()) {
                    $row = $stmt->fetchObject();
                    $workflowOverride = $row->semi_auto_usage;
                    $row = null;
                }
            } catch (Throwable $e) {
                ExceptionUtils::logException($e);
            } finally {
                $stmt = null;
            }
        }
        
        return $workflowOverride;
    }

    /**
     * Checks to see if current message has a derived Condition and negative Test Result
     * Prior to deferring a message to the Semi-Automated Entry queue, checks to see if we already know
     * this is a negative lab result for a known condition (e.g. "No Salmonella Found" from a Culture
     * result), allowing it to skip manual data entry and process automatically.
     *
     * @param EmsaMessage $emsaMessage Current message being processed
     *
     * @return bool
     */
    public static function isKnownNegative(EmsaMessage $emsaMessage): bool
    {
        if ((trim($emsaMessage->getMasterTestResult()) == "NEGATIVE") && (!empty($emsaMessage->masterCondition))) {
            return true;
        } else {
            return false;
        }
    }
    
    /**
     * Checks to see if the patient's name indicates that the message should be shunted to the "Pending" queue for review (such as internal testing).
     *
     * @param PDO    $dbConn    EMSA database connection.
     * @param string $lastName  Patient's last name.
     * @param string $firstName Patient's first name.
     *
     * @return bool
     */
    public static function isPendingByName(PDO $dbConn, ?string $lastName = null, ?string $firstName = null): bool
    {
        $isPendingByName = false;
        
        // if Last Name even has the word 'test' in it, short-circuit to TRUE
        if ((!empty($firstName) && stripos((string) $firstName, 'test')) || (!empty($lastName) && stripos((string) $lastName, 'test'))) {
            $isPendingByName = true;
            return $isPendingByName;
        }
        
        try {
            $sql = "SELECT count(*) AS names_found
                    FROM pending_watch_list
                    WHERE (lower(lname) = lower(:matchLastName))
                    OR (lower(lname) = lower(:matchFirstName));";
            $stmt = $dbConn->prepare($sql);
            $stmt->bindValue(':matchLastName', (string) $lastName, PDO::PARAM_STR);
            $stmt->bindValue(':matchFirstName', (string) $firstName, PDO::PARAM_STR);

            if ($stmt->execute()) {
                if ((int) $stmt->fetchColumn(0) > 0) {
                    $isPendingByName = true;
                }
            }
        } catch (Throwable $ex) {
            ExceptionUtils::logException($ex);
        } finally {
            $stmt = null;
        }
        
        return $isPendingByName;
    }

}

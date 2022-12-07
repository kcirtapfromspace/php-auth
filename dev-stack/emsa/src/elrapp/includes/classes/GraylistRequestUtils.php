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

use Udoh\Emsa\Client\AppClientList;

/**
 * GraylistRequest utility & search functions
 *
 * @author Josh Ridderhoff <jridderhoff@utah.gov>
 */
class GraylistRequestUtils
{

    /**
     * Checks whether the EMSA message identified as a candidate match has previously been assigned to this same record.
     *
     * @static
     *
     * @param PDO         $dbConn      PDO connection to EMSA database
     * @param EmsaMessage $emsaMessage EmsaMessage object for the system message from graylist being processed
     * @param int         $appRecordId ID of the record that triggered this Graylist Request
     *
     * @return bool
     *
     */
    public static function matchAlreadyAssigned(PDO $dbConn, EmsaMessage $emsaMessage, int $appRecordId): bool
    {
        $parentPreviouslyAssigned = self::parentMessageAlreadyAssigned($dbConn, $emsaMessage, $appRecordId);
        $messagePreviouslyAssigned = self::messageAlreadyAssigned($dbConn, $emsaMessage, $appRecordId);

        $previouslyAssigned = $parentPreviouslyAssigned || $messagePreviouslyAssigned;

        return $previouslyAssigned;
    }

    /**
     * Checks whether the parent of the specified EMSA message has previously been assigned to this same record.
     *
     * @static
     *
     * @param PDO         $dbConn      PDO connection to EMSA database
     * @param EmsaMessage $emsaMessage EmsaMessage object for the system message from graylist being processed
     * @param int         $appRecordId ID of the record that triggered this Graylist Request
     *
     * @return bool
     *
     */
    private static function parentMessageAlreadyAssigned(PDO $dbConn, EmsaMessage $emsaMessage, int $appRecordId): bool
    {
        if (empty($appRecordId)) {
            return false;
        }

        $assignedParentIds = array();

        $assignedSql = 'SELECT DISTINCT copy_parent_id
				FROM system_messages
				WHERE copy_parent_id IS NOT NULL
				AND event_id = :nedssEventId
				AND final_status = :statusId
                AND vocab_app_id = :appId;';
        $assignedStmt = $dbConn->prepare($assignedSql);
        $assignedStmt->bindValue(':nedssEventId', intval($appRecordId), PDO::PARAM_INT);
        $assignedStmt->bindValue(':statusId', ASSIGNED_STATUS, PDO::PARAM_INT);
        $assignedStmt->bindValue(':appId', $emsaMessage->getAppClient()->getAppId(), PDO::PARAM_INT);

        if ($assignedStmt->execute()) {
            while ($assignedRow = $assignedStmt->fetchObject()) {
                $assignedParentIds[] = intval($assignedRow->copy_parent_id);
            }
        } else {
            return false;
        }

        if (count($assignedParentIds) === 0) {
            return false;  // short-circuit if no matches found
        }

        if (in_array((int) $emsaMessage->getSystemMessageId(), $assignedParentIds)) {
            return true;
        } else {
            return false;
        }
    }

    /**
     * Checks whether the specified EMSA message has previously been assigned to this same record.
     *
     * @static
     *
     * @param PDO         $dbConn      PDO connection to EMSA database
     * @param EmsaMessage $emsaMessage EmsaMessage object for the system message from graylist being processed
     * @param int         $appRecordId ID of the record that triggered this Graylist Request
     *
     * @return bool
     *
     */
    private static function messageAlreadyAssigned(PDO $dbConn, EmsaMessage $emsaMessage, int $appRecordId): bool
    {
        if (empty($appRecordId)) {
            return false;
        }

        $assignedIds = array();

        $assignedSql = 'SELECT DISTINCT id
				FROM system_messages
				WHERE event_id = :nedssEventId
				AND final_status = :statusId
                AND vocab_app_id = :appId;';
        $assignedStmt = $dbConn->prepare($assignedSql);
        $assignedStmt->bindValue(':nedssEventId', intval($appRecordId), PDO::PARAM_INT);
        $assignedStmt->bindValue(':statusId', ASSIGNED_STATUS, PDO::PARAM_INT);
        $assignedStmt->bindValue(':appId', $emsaMessage->getAppClient()->getAppId(), PDO::PARAM_INT);

        if ($assignedStmt->execute()) {
            while ($assignedRow = $assignedStmt->fetchObject()) {
                $assignedIds[] = intval($assignedRow->id);
            }
        } else {
            return false;
        }

        if (count($assignedIds) === 0) {
            return false;  // short-circuit if no matches found
        }

        if (in_array((int) $emsaMessage->getSystemMessageId(), $assignedIds)) {
            return true;
        } else {
            return false;
        }
    }

    /**
     * Search the Graylist Pool for matches based on the patient and condition info from the triggering application.
     * 
     * @static
     * 
     * @param PDO             $dbConn          PDO connection to EMSA database
     * @param AppClientList   $appClientList   List of configured applications for this installation
     * @param GraylistRequest $graylistRequest Current GraylistRequest object
     * @param int             $minScore        [Optional]<br>Minimum match quality score to return matches for.  Default 70
     * 
     * @return GraylistMatchSet List of matched messages from the Graylist Pool
     * 
     * @throws Exception if missing required fields.
     * @throws PDOException on database errors.
     */
    public static function findGraylistMatches(PDO $dbConn, AppClientList $appClientList, GraylistRequest $graylistRequest, ?int $minScore = 70): GraylistMatchSet
    {
        if (!($graylistRequest->getRequestId())) {
            throw new Exception('Could not search Graylist Pool:  GraylistRequest not initialized.');
        }

        $matchSet = new GraylistMatchSet($appClientList);

        $sql = "SELECT sm.id AS id 
                FROM system_messages sm 
                WHERE sm.id IN (
                    SELECT graylist_search_202102(:fnameIn, :lnameIn, :mnameIn)
                )
                AND graylist_score_202102(:fnameIn, sm.fname, :lnameIn, sm.lname, :dobIn, sm.dob, :mnameIn, sm.mname, NULL, NULL) >= :minScore";

        $stmt = $dbConn->prepare($sql);

        $stmt->bindValue(':fnameIn', $graylistRequest->getFirstName(), PDO::PARAM_STR);
        $stmt->bindValue(':lnameIn', $graylistRequest->getLastName(), PDO::PARAM_STR);
        $stmt->bindValue(':mnameIn', $graylistRequest->getMiddleName(), PDO::PARAM_STR);
        $stmt->bindValue(':dobIn', $graylistRequest->getDob(true, "Y-m-d H:i:s"), PDO::PARAM_STR);

        $stmt->bindValue(':minScore', $minScore, PDO::PARAM_INT);

        if ($stmt->execute()) {
            while ($row = $stmt->fetchObject()) {
                $matchSet->addMatch($dbConn, $row->id);
            }
        }

        return $matchSet;
    }

}

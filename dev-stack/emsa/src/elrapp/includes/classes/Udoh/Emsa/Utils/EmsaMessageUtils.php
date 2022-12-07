<?php

namespace Udoh\Emsa\Utils;

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

use PDO;
use EmsaMessage;
use PDOException;
use Throwable;

/**
 * Utilities for processing data in EMSA Messages
 *
 * @package Udoh\Emsa\Utils
 *
 * @author Josh Ridderhoff <jridderhoff@utah.gov>
 */
class EmsaMessageUtils
{

    /**
     * Get the derived Local Result Value from an EMSA Message
     *
     * @param PDO         $dbConn      PDO connection to the EMSA database
     * @param EmsaMessage $emsaMessage EMSA message being assigned
     *
     * @return string
     *
     * @throws PDOException on database errors
     */
    public static function getLocalResultValue(PDO $dbConn, EmsaMessage $emsaMessage)
    {
        $result = '';

        $sql = 'SELECT get_local_result_value(:childLoincCode, :resultValue1, :resultValue2, :labId);';
        $stmt = $dbConn->prepare($sql);
        $stmt->bindValue(':childLoincCode', $emsaMessage->childLoinc, PDO::PARAM_STR);
        $stmt->bindValue(':resultValue1', $emsaMessage->localResultValue1, PDO::PARAM_STR);
        $stmt->bindValue(':resultValue2', $emsaMessage->localResultValue2, PDO::PARAM_STR);
        $stmt->bindValue(':labId', $emsaMessage->labId, PDO::PARAM_STR);
        if ($stmt->execute()) {
            $result = trim($stmt->fetchColumn(0));
        }

        return $result;
    }

    /**
     * Checks to see if the condition for the message specifies valid specimen sources,
     * and if the specimen source for the incoming message is valid.
     *
     * @param PDO         $dbConn      PDO connection to the EMSA database
     * @param EmsaMessage $emsaMessage EMSA Message object for the current message being processed
     *
     * @return int SPECIMEN_VALID if a valid specimen source, SPECIMEN_INVALID if an invalid specimen source,
     *   or SPECIMEN_EXCEPTION if one of valid or invalid specimen sources defined and provided specimen source is not in the list
     */
    public static function isValidSpecimenSource(PDO $dbConn, EmsaMessage $emsaMessage)
    {
        $isValid = SPECIMEN_EXCEPTION;
        $masterConditionId = 0;
        $specimens = '';
        $iSpecimens = '';
        $validSpecimens = array();
        $invalidSpecimens = array();

        $specimenSource = trim($emsaMessage->specimenSource);

        try {
            if (($emsaMessage->getMessageDestination() === SEMI_AUTO_STATUS) || ($emsaMessage->getMessageDestination() === QA_STATUS) || ($emsaMessage->getFinalStatus() === SEMI_AUTO_STATUS) || ($emsaMessage->getFinalStatus() === QA_STATUS)) {
                try {
                    // for messages that use Semi-Automated Entry, look up Master Condition ID by condition name selected by user
                    // instead of deriving from coded value
                    $semiAutoCondSql = "SELECT MAX(c_id) AS master_condition_id
                                        FROM vocab_master_condition
                                        WHERE condition = (
                                            SELECT MAX(id)
                                            FROM vocab_master_vocab
                                            WHERE concept = :condName
                                            AND category = vocab_category_id('condition')
                                        );";
                    $semiAutoCondStmt = $dbConn->prepare($semiAutoCondSql);
                    $semiAutoCondStmt->bindValue(':condName', trim($emsaMessage->masterCondition), PDO::PARAM_STR);

                    if ($semiAutoCondStmt->execute()) {
                        while ($semiAutoCondRow = $semiAutoCondStmt->fetchObject()) {
                            $masterConditionId = intval($semiAutoCondRow->master_condition_id);
                        }
                    }
                } catch (Throwable $e) {
                    ExceptionUtils::logException($e);
                } finally {
                    $semiAutoCondStmt = null;
                }
            } else {
                try {
                    // derive Master Condition ID based on coded value from sender
                    $deriveCondSql = "SELECT loinc_condition_id(:childLoinc, :resultValue, :labId);";
                    $deriveCondStmt = $dbConn->prepare($deriveCondSql);
                    $deriveCondStmt->bindValue(':childLoinc', trim($emsaMessage->childLoinc), PDO::PARAM_STR);
                    $deriveCondStmt->bindValue(':resultValue', trim($emsaMessage->localResultValue), PDO::PARAM_STR);
                    $deriveCondStmt->bindValue(':labId', intval($emsaMessage->labId), PDO::PARAM_INT);

                    if ($deriveCondStmt->execute()) {
                        $masterConditionId = intval($deriveCondStmt->fetchColumn(0));
                    }
                } catch (Throwable $e) {
                    ExceptionUtils::logException($e);
                } finally {
                    $deriveCondStmt = null;
                }
            }
        } catch (Throwable $e) {
            ExceptionUtils::logException($e);
            return SPECIMEN_EXCEPTION;
        }

        try {
            // get list of specimen sources for identified Master Condition ID
            $qry = "SELECT valid_specimen, invalid_specimen
                FROM vocab_master_condition
                WHERE c_id = :conditionId;";
            $stmt = $dbConn->prepare($qry);
            $stmt->bindValue(':conditionId', intval($masterConditionId), PDO::PARAM_INT);

            if ($stmt->execute()) {
                if ($stmt->rowCount() > 0) {
                    $row = $stmt->fetchObject();
                    if ($row !== false) {
                        $specimens = implode(',', explode(';', trim($row->valid_specimen)));
                        $iSpecimens = implode(',', explode(';', trim($row->invalid_specimen)));
                    }
                }
            }

            if (strlen(trim($specimens)) > 0) {
                $qry2 = "SELECT coded_value
                     FROM vocab_master2app
                     WHERE master_id IN (" . trim($specimens) . ");";
                $stmt2 = $dbConn->query($qry2);

                if ($stmt2 === false || ($stmt2->rowCount() < 1)) {
                    $isValid = SPECIMEN_EXCEPTION;
                } else {
                    while ($row = $stmt2->fetchObject()) {
                        $validSpecimens[] = trim($row->coded_value);
                    }
                    if (!empty($specimenSource) && count($validSpecimens) > 0 && in_array($specimenSource, $validSpecimens)) {
                        $isValid = SPECIMEN_VALID;
                    }
                }
            }

            if (strlen(trim($iSpecimens)) > 0) {
                $qry3 = "SELECT coded_value
                     FROM vocab_master2app
                     WHERE master_id IN (" . trim($iSpecimens) . ");";
                $stmt3 = $dbConn->query($qry3);

                if ($stmt3 === false || ($stmt3->rowCount() < 1)) {
                    $isValid = SPECIMEN_EXCEPTION;
                } else {
                    while ($row = $stmt3->fetchObject()) {
                        $invalidSpecimens[] = trim($row->coded_value);
                    }
                    if (!empty($specimenSource) && count($invalidSpecimens) > 0 && in_array($specimenSource, $invalidSpecimens)) {
                        $isValid = SPECIMEN_INVALID;
                    }
                }
            }

            if ((strlen(trim($specimens)) < 1) && (strlen(trim($iSpecimens)) < 1)) {
                $isValid = SPECIMEN_VALID; // no valid or invalid specimen sources defined, accept all the things!
            }
        } catch (Throwable $e) {
            ExceptionUtils::logException($e);
            return SPECIMEN_EXCEPTION;
        }

        return $isValid;
    }

    /**
     * Gets the comment for a given system message & message flag
     *
     * @param PDO $dbConn          PDO connection to the EMSA database
     * @param int $systemMessageId ELR Message ID
     * @param int $flagId          ID of the flag being set
     *
     * @return string Comments for this message and flag.  Empty string if no comment is set
     */
    public static function getMessageFlagComment(PDO $dbConn, $systemMessageId = null, $flagId = null)
    {
        $comment = '';

        if (empty($systemMessageId) || empty($flagId)) {
            return $comment;
        }

        try {
            $sql = "SELECT info
                    FROM system_message_flag_comments
                    WHERE system_message_id = :msgId
                    AND system_message_flag_id = :flagId;";

            $stmt = $dbConn->prepare($sql);
            $stmt->bindValue(':msgId', $systemMessageId, PDO::PARAM_INT);
            $stmt->bindValue(':flagId', $flagId, PDO::PARAM_INT);

            $stmt->execute();

            $comment = (string) $stmt->fetchColumn(0);
        } catch (Throwable $e) {
            ExceptionUtils::logException($e);
        } finally {
            $stmt = null;
        }

        return trim($comment);
    }

}

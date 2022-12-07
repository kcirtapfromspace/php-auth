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

use Udoh\Emsa\Utils\DisplayUtils;
use Udoh\Emsa\Utils\ExceptionUtils;

/**
 * Miscellaneous utilities for EMSA.
 *
 * @author Josh Ridderhoff <jridderhoff@utah.gov>
 */
class EmsaUtils
{

    /**
     * Expression-safe, internally-trimmed extension of PHP <b>empty()</b> construct.
     * 
     * @param mixed $var Value to be checked
     * 
     * @return bool
     */
    public static function emptyTrim($var = null)
    {
        if (empty($var)) {
            return true;  // short-circuit if truly empty
        }

        if (is_object($var)) {
            return false;  // if $var is a non-null object, return false
        }

        if (is_array($var)) {
            return false;  // if non-empty array, return false
        }

        $trimmedVar = trim($var);
        return empty($trimmedVar);
    }

    /**
     * Builds a well-formed query string to refer to an EMSA page using navigation IDs
     *
     * @param int $selectedPage selected_page ID
     * @param int $submenu      [Optional]<br>submenu ID
     * @param int $cat          [Optional]<br>cat ID
     * @param int $subcat       [Optional]<br>subcat ID
     * @param int $queueId      [Optional]<br>EMSA message queue ID
     *
     * @return string
     */
    public static function queryStringBuilder($selectedPage, $submenu = null, $cat = null, $subcat = null, $queueId = null)
    {
        $queryString = '?selected_page=' . intval($selectedPage);

        if (!self::emptyTrim($submenu) && (intval($submenu) > 0)) {
            $queryString .= '&submenu=' . intval($submenu);
        }

        if (!self::emptyTrim($cat) && (intval($cat) > 0)) {
            $queryString .= '&cat=' . intval($cat);
        }

        if (!self::emptyTrim($subcat) && (intval($subcat) > 0)) {
            $queryString .= '&subcat=' . intval($subcat);
        }

        if (!self::emptyTrim($queueId) && (intval($queueId) > 0)) {
            $queryString .= '&type=' . intval($queueId);
        }

        return $queryString;
    }

    /**
     * Accepts an encoded value for Abnormal Flag from the Master XML & returns the decoded Preferred Concept Name
     *
     * @param PDO    $dbConn           PDO connection to the EMSA database
     * @param string $abnormalFlagCode Coded Abnormal Flag value
     *
     * @return string
     */
    public static function decodeAbnormalFlag(PDO $dbConn, $abnormalFlagCode = null)
    {
        $decodedFlag = '';

        if (empty($abnormalFlagCode)) {
            return $decodedFlag;
        } else {
            try {
                $sql = "SELECT m.concept AS concept
						FROM vocab_master_vocab m
						INNER JOIN vocab_child_vocab c ON (
							c.master_id = m.id AND
							m.category = vocab_category_id('abnormal_flag') AND
							c.concept ILIKE :abnormalFlagCode
						);";
                $stmt = $dbConn->prepare($sql);
                $stmt->bindValue(':abnormalFlagCode', $abnormalFlagCode, PDO::PARAM_STR);

                if ($stmt->execute()) {
                    $decodedFlag = trim($stmt->fetchColumn(0));
                }

                return $decodedFlag;
            } catch (PDOException $e) {
                ExceptionUtils::logException($e);
                return $decodedFlag;
            } finally {
                $stmt = null;
                $sql = null;
            }
        }
    }

    /**
     * Gets the name of an EMSA message QA Flag by ID.
     *
     * @param PDO $dbConn   PDO connection to the EMSA database
     * @param int $qaFlagId Integer representation of the flag's binary ID
     *
     * @return string
     */
    public static function decodeMessageQaFlag(PDO $dbConn, $qaFlagId)
    {
        $decodedFlagName = '';

        if (filter_var($qaFlagId, FILTER_VALIDATE_INT)) {
            $decodedFlagId = intval(log(intval($qaFlagId), 2));

            try {
                $sql = "SELECT label 
                        FROM system_message_flags 
                        WHERE id = :flagId;";
                $stmt = $dbConn->prepare($sql);
                $stmt->bindValue(':flagId', intval($decodedFlagId), PDO::PARAM_INT);

                if ($stmt->execute() && ($stmt->rowCount() > 0)) {
                    $decodedFlagName = trim($stmt->fetchColumn(0));
                }
            } catch (Throwable $e) {
                ExceptionUtils::logException($e);
            } finally {
                $stmt = null;
                $sql = null;
            }
        }
        return $decodedFlagName;
    }

    /**
     * Add (or update) a comment for a given ELR message & QA message flag.
     *
     * @param PDO    $dbConn          PDO connection to the EMSA database
     * @param int    $systemMessageId EMSA message ID
     * @param int    $qaFlagId        ID of the flag being set
     * @param string $comments        Comments to be set for this message & flag
     * 
     * @return bool
     */
    public static function addOrUpdateQaFlagComment(PDO $dbConn, $systemMessageId = null, $qaFlagId = null, $comments = null)
    {
        if (is_null($systemMessageId) || is_null($qaFlagId) || strlen(trim($comments)) < 1) {
            return false;
        }

        try {
            // check whether comment already exists for this message & QA flag
            $commentExistsSql = "SELECT id 
                FROM system_message_flag_comments 
                WHERE system_message_id = :systemMessageId 
                AND system_message_flag_id = :qaFlagId;";
            $commentExistsStmt = $dbConn->prepare($commentExistsSql);
            $commentExistsStmt->bindValue(':systemMessageId', intval($systemMessageId), PDO::PARAM_INT);
            $commentExistsStmt->bindValue(':qaFlagId', intval($qaFlagId), PDO::PARAM_INT);

            if ($commentExistsStmt->execute() && ($commentExistsStmt->rowCount() > 0)) {
                $existingCommentId = intval($commentExistsStmt->fetchColumn(0));
            } else {
                $existingCommentId = 0;
            }

            if ($existingCommentId > 0) {
                $updateCommentSql = "UPDATE system_message_flag_comments 
                    SET info = :comments 
                    WHERE id = :existingCommentId;";
                $updateCommentStmt = $dbConn->prepare($updateCommentSql);
                $updateCommentStmt->bindValue(':comments', trim($comments), PDO::PARAM_STR);
                $updateCommentStmt->bindValue(':existingCommentId', intval($existingCommentId), PDO::PARAM_INT);

                if ($updateCommentStmt->execute()) {
                    return true;
                } else {
                    return false;
                }
            } else {
                $insertCommentSql = "INSERT INTO system_message_flag_comments 
                    (system_message_id, system_message_flag_id, info) 
                    VALUES (:systemMessageId, :qaFlagId, :comments);";
                $insertCommentStmt = $dbConn->prepare($insertCommentSql);
                $insertCommentStmt->bindValue(':systemMessageId', intval($systemMessageId), PDO::PARAM_INT);
                $insertCommentStmt->bindValue(':qaFlagId', intval($qaFlagId), PDO::PARAM_INT);
                $insertCommentStmt->bindValue(':comments', trim($comments), PDO::PARAM_STR);

                if ($insertCommentStmt->execute()) {
                    return true;
                } else {
                    return false;
                }
            }
        } catch (Throwable $e) {
            ExceptionUtils::logException($e);
            return false;
        }
    }

    /**
     * Clears the previously-set QA flag comment for a specified EMSA message.
     *
     * @param PDO $dbConn          PDO connection to the EMSA database
     * @param int $systemMessageId EMSA message ID
     * @param int $qaFlagId        ID of the flag the comment is being cleared from
     *
     * @return boolean
     */
    public static function clearQaFlagComment(PDO $dbConn, $systemMessageId = null, $qaFlagId = null)
    {
        if (is_null($systemMessageId) || is_null($qaFlagId)) {
            return false;
        }

        try {
            $existingCommentSql = "SELECT id 
			FROM system_message_flag_comments 
			WHERE system_message_id = :systemMessageId 
			AND system_message_flag_id = :qaFlagId;";
            $existingCommentStmt = $dbConn->prepare($existingCommentSql);
            $existingCommentStmt->bindValue(':systemMessageId', intval($systemMessageId), PDO::PARAM_INT);
            $existingCommentStmt->bindValue(':qaFlagId', intval($qaFlagId), PDO::PARAM_INT);

            if ($existingCommentStmt->execute() && ($existingCommentStmt->rowCount() > 0)) {
                $existingCommentId = $existingCommentStmt->fetchColumn(0);
            } else {
                $existingCommentId = 0;
            }

            if ($existingCommentId > 0) {
                $sql = "UPDATE system_message_flag_comments 
                    SET info = NULL 
                    WHERE id = :existingCommentId;";
                $stmt = $dbConn->prepare($sql);
                $stmt->bindValue(':existingCommentId', intval($existingCommentId), PDO::PARAM_INT);

                if ($stmt->execute()) {
                    return true;
                } else {
                    return false;
                }
            }
        } catch (Throwable $e) {
            ExceptionUtils::logException($e);
            return false;
        }

        return false;
    }

    /**
     * Removes HTML content from NEDSS-generated error messages.
     *
     * @param PDO    $dbConn EMSA database handle
     * @param string $errorMessage
     *
     * @return string
     */
    public static function trimNEDSSErrorHTML(PDO $dbConn, $errorMessage = null)
    {
        if (EmsaUtils::emptyTrim($errorMessage)) {
            return $errorMessage;
        }

        // look up application_path_id, if found... easier for troubleshooting
        if (preg_match("/application_path_id/ims", $errorMessage) === 1) {
            $xPath = '';
            $pathId = substr($errorMessage, (strripos($errorMessage, "=") + 1));

            try {
                $sql = "SELECT xpath 
							FROM structure_path_application 
							WHERE id = :pathId;";
                $stmt = $dbConn->prepare($sql);
                $stmt->bindValue(":pathId", intval($pathId), PDO::PARAM_INT);
                if ($stmt->execute()) {
                    $xPath = $stmt->fetchColumn(0);
                }
            } catch (PDOException $e) {
                ExceptionUtils::logException($e);
                $xPath = '[Unknown Path]';
            } finally {
                $stmt = null;
                $sql = null;
            }

            $errorMessage = substr($errorMessage, 0, (strripos($errorMessage, "application_path_id") - 1));
            $errorMessage .= " <br>(<strong>NEDSS XML Path:</strong> $xPath)";
        }

        // label child value more clearly
        $errorMessage = preg_replace('/' . preg_quote('child_vocab.concept=', '/') . '/ims', '<strong>Child Value:</strong> ', $errorMessage);

        // strip <meta> tags...
        $errorMessage = preg_replace('/<meta(.*?)>/ims', '', $errorMessage);

        // strip <title> tag...
        $errorMessage = preg_replace('/<title(.*?)\\/title>/ims', '', $errorMessage);

        // strip <style/> tag...
        $errorMessage = preg_replace('/<style(.*?)\\/style>/ims', '', $errorMessage);
        
        // strip csi feedback link...
        $errorMessage = preg_replace('/<a(.*?)wiki\.csinitiative\.com(.*?)\\/a>/ims', '', $errorMessage);
        
        return $errorMessage;
    }

    /**
     * Get the local/child value component from NEDSS-generated exception messages.
     *
     * @param PDO    $dbConn EMSA database handle
     * @param string $errorMessage
     *
     * @return string
     */
    public static function getAppPathExceptionValue(PDO $dbConn, $errorMessage = null)
    {
        if (EmsaUtils::emptyTrim($errorMessage)) {
            return $errorMessage;
        }

        // look up application_path_id, if found... easier for troubleshooting
        if (preg_match("/application_path_id/ims", $errorMessage) === 1) {
            $errorMessage = substr($errorMessage, 0, (strripos($errorMessage, "application_path_id") - 1));
        }

        // strip everything before the child value
        $errorMessage = preg_replace('/' . preg_quote('child_vocab.concept=', '/') . '/ims', '', $errorMessage);

        // strip <meta> tags...
        $errorMessage = preg_replace('/<meta(.*?)>/ims', '', $errorMessage);

        // strip <title> tag...
        $errorMessage = preg_replace('/<title(.*?)\\/title>/ims', '', $errorMessage);

        // strip <style/> tag...
        $errorMessage = preg_replace('/<style(.*?)\\/style>/ims', '', $errorMessage);

        // strip csi feedback link...
        $errorMessage = preg_replace('/<a(.*?)wiki\.csinitiative\.com(.*?)\\/a>/ims', '', $errorMessage);

        return $errorMessage;
    }

    /**
     * Get the Application Path component from NEDSS-generated exception messages.
     *
     * @param PDO    $dbConn EMSA database handle
     * @param string $errorMessage
     *
     * @return string
     */
    public static function getAppPathExceptionPath(PDO $dbConn, $errorMessage = null)
    {
        if (EmsaUtils::emptyTrim($errorMessage)) {
            return $errorMessage;
        }

        // look up application_path_id, if found... easier for troubleshooting
        if (preg_match("/application_path_id/ims", $errorMessage) === 1) {
            $xPath = '';
            $pathId = substr($errorMessage, (strripos($errorMessage, "=") + 1));

            try {
                $sql = "SELECT xpath 
							FROM structure_path_application 
							WHERE id = :pathId;";
                $stmt = $dbConn->prepare($sql);
                $stmt->bindValue(":pathId", intval($pathId), PDO::PARAM_INT);
                if ($stmt->execute()) {
                    $xPath = $stmt->fetchColumn(0);
                }
            } catch (PDOException $e) {
                ExceptionUtils::logException($e);
                $xPath = '[Unknown Path]';
            } finally {
                $stmt = null;
                $sql = null;
            }

            $errorMessage = $xPath;
        }

        // strip everything before the child value
        $errorMessage = preg_replace('/' . preg_quote('child_vocab.concept=', '/') . '/ims', '', $errorMessage);

        // strip <meta> tags...
        $errorMessage = preg_replace('/<meta(.*?)>/ims', '', $errorMessage);

        // strip <title> tag...
        $errorMessage = preg_replace('/<title(.*?)\\/title>/ims', '', $errorMessage);

        // strip <style/> tag...
        $errorMessage = preg_replace('/<style(.*?)\\/style>/ims', '', $errorMessage);

        // strip csi feedback link...
        $errorMessage = preg_replace('/<a(.*?)wiki\.csinitiative\.com(.*?)\\/a>/ims', '', $errorMessage);

        return $errorMessage;
    }

    /**
     * For a given application concept name and category, return the EMSA Master Vocabulary ID
     *
     * @param PDO    $dbConn        Current EMSA database
     * @param string $vocabCategory Master Vocab Category to restrict the search to
     * @param string $appConcept    Concept name to look up
     * @param int    $appId         [Optional]<br>Application that the given <i>appConcept</i> is associated with.  Default 1 (NEDSS)
     *
     * @return int|bool Master Vocab ID if found, <b>FALSE</b> if value cannot be located or database errors occur
     */
    public static function appConceptMasterVocabId(PDO $dbConn, $vocabCategory, $appConcept, $appId = 1)
    {
        try {
            $sql = 'SELECT mv.id
					FROM vocab_master2app m2a
					INNER JOIN vocab_master_vocab mv ON (mv.id = m2a.master_id)
					WHERE m2a.coded_value ILIKE :appCondition
					AND mv.category = vocab_category_id(:mvCategory)
					AND m2a.app_id = :appId;';
            $stmt = $dbConn->prepare($sql);
            $stmt->bindValue(':mvCategory', $vocabCategory, PDO::PARAM_STR);
            $stmt->bindValue(':appCondition', $appConcept, PDO::PARAM_STR);
            $stmt->bindValue(':appId', $appId, PDO::PARAM_INT);

            if ($stmt->execute()) {
                return intval($stmt->fetchColumn(0));
            }
        } catch (Throwable $e) {
            ExceptionUtils::logException($e);
        } finally {
            $stmt = null;
            $sql = null;
        }

        return false;
    }

    /**
     * Decode a logical operator ID into a displayable operator string.
     *
     * @param PDO $dbConn     PDO connection to the EMSA database
     * @param int $operatorId Operator ID to decode
     *
     * @return string
     */
    public static function graphicalOperatorById(PDO $dbConn, $operatorId = null)
    {
        $operator = '';

        if (intval($operatorId) < 1) {
            return $operator;
        }

        $sql = 'SELECT graphical
				FROM structure_operator 
				WHERE id = :operatorId;';
        $stmt = $dbConn->prepare($sql);
        $stmt->bindValue(':operatorId', $operatorId, PDO::PARAM_INT);

        if ($stmt->execute()) {
            $operator = trim($stmt->fetchColumn(0));
        }

        return $operator;
    }

    /**
     * Checks to see if a configured EMSA jurisdiction has any dependencies prior to disabling it
     *
     * @param PDO $dbConn         PDO connection to the EMSA database
     * @param int $emsaDistrictId ID of the system_district in EMSA to check dependencies against
     *
     * @return boolean
     */
    public static function jurisdictionHasDependencies(PDO $dbConn, $emsaDistrictId)
    {
        $sql = "SELECT id AS id FROM structure_labs WHERE default_jurisdiction_id = :districtId
				UNION
				SELECT c_id AS id FROM vocab_master_condition WHERE district_override = :districtId;";
        $stmt = $dbConn->prepare($sql);
        $stmt->bindValue(':districtId', intval($emsaDistrictId), PDO::PARAM_INT);

        if ($stmt->execute() && ($stmt->rowCount() > 0)) {
            return true;
        } else {
            return false;
        }
    }

    /**
     * Checks to see if specified EMSA message was moved out of the Assigned queue in the past.
     *
     * @param PDO $dbConn          PDO connection to the EMSA database
     * @param int $systemMessageId EMSA System Message ID
     *
     * @return boolean
     */
    public static function wasMessageMovedFromAssigned(PDO $dbConn, $systemMessageId)
    {
        $sql = "SELECT id 
                FROM system_messages_audits 
                WHERE system_message_id = :systemMessageId 
                AND message_action_id = :msgMovedAction 
                AND system_status_id = :assignedStatus;";
        $stmt = $dbConn->prepare($sql);
        $stmt->bindValue(':systemMessageId', intval($systemMessageId), PDO::PARAM_INT);
        $stmt->bindValue(':msgMovedAction', \Udoh\Emsa\Constants\SystemMessageActions::MESSAGE_MOVED_BY_USER, PDO::PARAM_INT);
        $stmt->bindValue(':assignedStatus', \ASSIGNED_STATUS, PDO::PARAM_INT);

        if ($stmt->execute() && ($stmt->rowCount() > 0)) {
            return true;
        } else {
            return false;
        }
    }

    /**
     * Calculate whether an ELR message is within 24-hour Service Level for being processed from Entry queue.
     * 
     * Calculation should only include business days (Mon-Fri), but 24 real hours during those days.  For example:
     *   - Received at noon on Monday, due by noon Tuesday
     *   - Recieved at noon on Friday, due by noon Monday
     * If received over the weekend, adjust to start "overdue" countdown Monday at 8:00 a.m.  For example:
     *   - Received on Saturday, due by 8:00 a.m. Tuesday
     *
     * @param int    $finalStatus  ID of the EMSA queue the message is in.
     * @param string $createdAtStr Date the ELR message was received, in format accepted by strtotime().
     *
     * @return bool
     * 
     */
    public static function isElrMessageOverdue($finalStatus, $createdAtStr = null)
    {
        if (!self::emptyTrim($createdAtStr)) {
            $createdTimestamp = strtotime($createdAtStr);

            if ($createdTimestamp !== false) {
                $createdParsed = getdate($createdTimestamp);
                $createdYear = intval($createdParsed['year']);
                $createdWday = intval($createdParsed['wday']);

                if ($createdYear < 1970) {
                    return false;
                }

                if (($createdWday == 0) || ($createdWday == 6)) {
                    // if Sunday or Saturday, adjust timestamp to Monday 8am
                    $adjustedTimeTmp = strtotime("next Monday 8:00 am", $createdTimestamp);
                    $adjustedTime = mktime(
                            date("H", $adjustedTimeTmp), date("i", $adjustedTimeTmp), date("s", $adjustedTimeTmp), date("n", $adjustedTimeTmp), date("j", $adjustedTimeTmp), date("Y", $adjustedTimeTmp));
                } else {
                    // otherwise, use existing timestamp
                    $adjustedTime = $createdTimestamp;
                }

                /**
                 * 24-hour service level
                 * If SLA change is needed, adjust here
                 */
                if ($finalStatus === LOCKED_STATUS) {
                    $overdueTimestamp = strtotime("+2 hours", $adjustedTime);
                } else {
                    $overdueTimestamp = strtotime("+1 day", $adjustedTime);
                }
                $overdueParsed = getdate($overdueTimestamp);
                $overdueWday = intval($overdueParsed['wday']);
                if (($overdueWday == 0) || ($overdueWday == 6)) {
                    // will come due over the weekend, move to be overdue same time next Monday
                    $overdueAdjustedTmp = strtotime("next Monday", $overdueTimestamp);
                    $overdueAdjusted = mktime(
                            date("H", $overdueTimestamp), date("i", $overdueTimestamp), date("s", $overdueTimestamp), date("n", $overdueAdjustedTmp), date("j", $overdueAdjustedTmp), date("Y", $overdueAdjustedTmp));
                } else {
                    $overdueAdjusted = $overdueTimestamp;
                }

                if ($overdueAdjusted < time()) {
                    return true;  // we've passed the due date
                } else {
                    return false;  // still within service level
                }
            } else {
                return false;
            }
        } else {
            return false;
        }
    }

    /**
     * Checks to see if a specified message has been moved and display reason.
     *
     * If message was moved from another queue, returns an HTML element with the name of the
     * previous queue and the comments left by the user who moved the message.  Returns empty string
     * if message has not been moved.
     *
     * @param PDO $dbConn          PDO connection to the EMSA database
     * @param int $systemMessageId ID number of the message in system_messages
     * @param int $finalStatus     ID of the EMSA queue that the message is currently in
     *
     * @return string
     * @todo [someday] Use system constants for action_ids instead of hard-coding
     */
    public static function showMessageMoveReason(PDO $dbConn, $systemMessageId, $finalStatus)
    {
        $html = '';

        if ((intval($systemMessageId) <= 0) || (intval($finalStatus) <= 0)) {
            return $html;
        }

        try {
            $sql = "SELECT au.info AS reason, ss.name AS queue 
                    FROM system_messages_audits au  
                    INNER JOIN system_statuses ss ON (au.system_status_id = ss.id) 
                    WHERE au.message_action_id in (25, 27) AND au.system_message_id = :systemMessageId
                    ORDER BY au.created_at DESC;";
            $stmt = $dbConn->prepare($sql);
            $stmt->bindValue(':systemMessageId', intval($systemMessageId), PDO::PARAM_INT);

            if ($stmt->execute() && ($stmt->rowCount() > 0)) {
                $row = $stmt->fetchObject();
                $html = '<div class="ui-corner-all emsa_toolbar" style="background-color: gold;"><span class="emsa_toolbar_label" style="color: darkred;">';
                $html .= "Message moved from &ldquo;" . DisplayUtils::xSafe(trim($row->queue)) . "&rdquo;</span><br><em>" . DisplayUtils::xSafe(trim($row->reason)) . "</em>";
                $html .= "</div>";
            }
        } catch (Throwable $e) {
            ExceptionUtils::logException($e);
        } finally {
            $row = null;
            $stmt = null;
            $sql = null;
        }

        return $html;
    }

    /**
     * Return the current Queue ID ('final_status') for a specified EMSA message
     *
     * @param PDO $dbConn          PDO connection to the EMSA database
     * @param int $systemMessageId ID number of the message in system_messages
     *
     * @return int Queue ID on success or <b>NULL</b> if no queue specified/message not found.
     */
    public static function getQueueIdByMessageId(PDO $dbConn, $systemMessageId)
    {
        $queueId = null;

        if (intval($systemMessageId) <= 0) {
            return $queueId;
        }

        try {
            $sql = "SELECT final_status 
                FROM system_messages 
                WHERE id = :systemMessageId 
                AND final_status IS NOT NULL;";
            $stmt = $dbConn->prepare($sql);
            $stmt->bindValue(':systemMessageId', intval($systemMessageId), PDO::PARAM_INT);

            if ($stmt->execute() && ($stmt->rowCount() > 0)) {
                $queueId = intval($stmt->fetchColumn(0));
            }
        } catch (Throwable $e) {
            ExceptionUtils::logException($e);
        } finally {
            $stmt = null;
            $sql = null;
        }

        return $queueId;
    }

    /**
     * Get the name of an EMSA message queue ID.
     * 
     * @param int $finalStatus EMSA message queue ID.
     * 
     * @return string Name of the EMSA queue.
     */
    public static function getQueueName($finalStatus)
    {
        switch ($finalStatus) {
            case PENDING_STATUS:
                $decodedName = PENDING_NAME;
                break;
            case ENTRY_STATUS:
                $decodedName = ENTRY_NAME;
                break;
            case OOS_STATUS:
                $decodedName = OOS_NAME;
                break;
            case ASSIGNED_STATUS:
                $decodedName = ASSIGNED_NAME;
                break;
            case GRAY_STATUS:
                $decodedName = GRAY_NAME;
                break;
            case BLACK_STATUS:
                $decodedName = BLACK_NAME;
                break;
            case EXCEPTIONS_STATUS:
                $decodedName = EXCEPTIONS_NAME;
                break;
            case NEDSS_EXCEPTION_STATUS:
                $decodedName = NEDSS_EXCEPTION_NAME;
                break;
            case HOLD_STATUS:
                $decodedName = 'HOLDING STATUS';
                break;
            case QA_STATUS:
                $decodedName = QA_NAME;
                break;
            case SEMI_AUTO_STATUS:
                $decodedName = SEMI_AUTO_NAME;
                break;
            case UNPROCESSED_STATUS:
                $decodedName = UNPROCESSED_NAME;
                break;
            case LOCKED_STATUS:
                $decodedName = LOCKED_NAME;
                break;
            default:
                $decodedName = PENDING_NAME;
                break;
        }

        return $decodedName;
    }

    /**
     * Generate an HTML menu containing valid queues where a specified EMSA message can be moved to, based upon its history and current location.
     *
     * @param PDO  $dbConn          PDO connection to the EMSA database
     * @param int  $finalStatus     EMSA message queue ID of the specified message
     * @param int  $systemMessageId EMSA System Message ID
     * @param bool $isPeopleSearch  [Optional; default <b>FALSE</b>]<br>Indicates whether the generated menu is being included in a People Search result.
     *
     * @return string
     */
    public static function getQueueNameMenuByTypeAndMsgId(PDO $dbConn, $finalStatus = null, $systemMessageId = null, $isPeopleSearch = false)
    {
        if ($isPeopleSearch) {
            $elementIdPrefix = 'cmr_';
        } else {
            $elementIdPrefix = 'system_';
        }

        $html = '<label class="sr-only" for="' . $elementIdPrefix . 'status_id_' . intval($systemMessageId) . '">Choose Queue</label>';
        $html .= '<select class="ui-corner-all" name="system_status_id_' . intval($systemMessageId) . '" id="' . $elementIdPrefix . 'status_id_' . intval($systemMessageId) . '">';

        if (self::emptyTrim($finalStatus) || self::emptyTrim($systemMessageId)) {
            return $html . '</select>';
        }

        $excludedQueuesList = array(
            \GRAY_PENDING_STATUS,
            \GRAY_EXCEPTION_STATUS,
            \GRAY_PROCESSED_STATUS,
            \GRAY_UNPROCESSABLE_STATUS,
            \PENDING_STATUS,
            \BLACK_STATUS,
            \QA_STATUS,
            \UNPROCESSED_STATUS,
            \LOCKED_STATUS,
            intval($finalStatus)
        );

        if (!in_array($finalStatus, array(\EXCEPTIONS_STATUS, \NEDSS_EXCEPTION_STATUS, \PENDING_STATUS)) || (in_array($finalStatus, array(\EXCEPTIONS_STATUS, \NEDSS_EXCEPTION_STATUS, \PENDING_STATUS)) && !self::wasMessageMovedFromAssigned($dbConn, intval($systemMessageId)))) {
            // unless message is currently in the 'Exceptions' or 'Pending' queue 
            // AND was previously in the 'Assigned' queue, exclude the 'Assigned' queue from the list
            $excludedQueuesList[] = \ASSIGNED_STATUS;
        }

        $sql = "SELECT id, name 
                FROM system_statuses 
                WHERE parent_id = 0 
                AND id NOT IN (" . implode(', ', array_map('intval', $excludedQueuesList)) . ");";

        try {
            $stmt = $dbConn->query($sql);

            if (($stmt !== false) && ($stmt->rowCount() > 0)) {
                $html .= '<option value="-1">Choose:</option>';

                while ($row = $stmt->fetchObject()) {
                    if (intval($finalStatus) === intval($row->id)) {
                        $html .= '<option value="' . intval($row->id) . '" selected>' . DisplayUtils::xSafe($row->name) . '</option>';
                    } else {
                        $html .= '<option value="' . intval($row->id) . '">' . DisplayUtils::xSafe($row->name) . '</option>';
                    }
                }
            }
        } catch (Throwable $e) {
            ExceptionUtils::logException($e);
        } finally {
            $row = null;
            $stmt = null;
        }

        return $html . '</select>';
    }

    /**
     * Gets a list of available queues to move a message for use in a common setting where a specific message is not available to check for valid destinations (e.g. bulk message form).
     *
     * @param PDO $dbConn          PDO connection to the EMSA database
     * @param int $fromFinalStatus The final_status (queue ID) of the queue the message(s) will be moved from.
     *
     * @return array List of queues available to move a message to, in the form of <i>id</i> => <i>name</i>
     */
    public static function getMoveToQueueList(PDO $dbConn, $fromFinalStatus)
    {
        $queueList = array();

        try {
            $sql = "SELECT id, name 
                    FROM system_statuses 
                    WHERE parent_id = 0 
                    AND id NOT IN (" . GRAY_PENDING_STATUS . ", " . GRAY_EXCEPTION_STATUS . ", " . GRAY_PROCESSED_STATUS . ", " . GRAY_UNPROCESSABLE_STATUS . ", " . PENDING_STATUS . ", " . BLACK_STATUS . ", " . ASSIGNED_STATUS . ", " . intval($fromFinalStatus) . ")
                    ORDER BY name;";
            $stmt = $dbConn->prepare($sql);
            if ($stmt->execute()) {
                while ($row = $stmt->fetchObject()) {
                    $queueList[] = array(
                        'id' => intval($row->id),
                        'name' => trim($row->name)
                    );
                }
            }
        } catch (Throwable $e) {
            ExceptionUtils::logException($e);
        } finally {
            $row = null;
            $stmt = null;
            $sql = null;
        }

        return $queueList;
    }

    /**
     * Get the Master XML document for a specified EMSA message and return it as a formatted string
     *
     * @param PDO $dbConn          PDO connection to the EMSA database
     * @param int $systemMessageId EMSA System Message ID
     *
     * @return string
     */
    public static function getMasterXmlFormatted(PDO $dbConn, $systemMessageId)
    {
        $formattedStr = '';

        try {
            $sql = "SELECT master_xml
						FROM system_messages
						WHERE id = :systemMessageId;";
            $stmt = $dbConn->prepare($sql);
            $stmt->bindValue(':systemMessageId', intval($systemMessageId), PDO::PARAM_INT);

            if ($stmt->execute()) {
                $formattedStr = DisplayUtils::formatXml(trim($stmt->fetchColumn(0)));
            }

            $stmt = null;
        } catch (Throwable $e) {
            ExceptionUtils::logException($e);
        } finally {
            $stmt = null;
            $sql = null;
        }

        return $formattedStr;
    }

    /**
     * Get the Application XML document for a specified EMSA message and return it as a formatted string
     *
     * @param PDO $dbConn          PDO connection to the EMSA database
     * @param int $systemMessageId EMSA System Message ID
     *
     * @return string
     */
    public static function getApplicationXMLFormatted(PDO $dbConn, $systemMessageId)
    {
        $formattedStr = '';

        try {
            $sql = "SELECT transformed_xml
						FROM system_messages
						WHERE id = :systemMessageId;";
            $stmt = $dbConn->prepare($sql);
            $stmt->bindValue(':systemMessageId', intval($systemMessageId), PDO::PARAM_INT);

            if ($stmt->execute()) {
                $formattedStr = DisplayUtils::formatXml(trim($stmt->fetchColumn(0)));
            }

            $stmt = null;
        } catch (Throwable $e) {
            ExceptionUtils::logException($e);
        } finally {
            $stmt = null;
            $sql = null;
        }

        return $formattedStr;
    }

    /**
     * Get the raw original (HL7/CDA) message for a specified EMSA message and return it as a formatted string
     *
     * @param PDO $dbConn          PDO connection to the EMSA database
     * @param int $systemMessageId EMSA System Message ID
     * @param int $messageType     Type of EMSA message (ELR, CDA, etc.) from Udoh\Emsa\Constants\MessageType
     *
     * @return string
     */
    public static function getRawOriginalMessageFormatted(PDO $dbConn, $systemMessageId, $messageType = Udoh\Emsa\Constants\MessageType::ELR_MESSAGE)
    {
        $formattedStr = '';

        try {
            $sql = "SELECT om.message
						FROM system_original_messages om
						INNER JOIN system_messages sm ON (sm.original_message_id = om.id)
						WHERE sm.id = :systemMessageId;";
            $stmt = $dbConn->prepare($sql);
            $stmt->bindValue(':systemMessageId', intval($systemMessageId), PDO::PARAM_INT);

            if ($stmt->execute()) {
                if ($messageType === Udoh\Emsa\Constants\MessageType::ELR_MESSAGE) {
                    $formattedStr = str_replace("\\015", PHP_EOL, trim($stmt->fetchColumn(0)));
                } else {
                    $formattedStr = DisplayUtils::formatXml(trim($stmt->fetchColumn(0)));
                }
            }

            $stmt = null;
        } catch (Throwable $e) {
            ExceptionUtils::logException($e);
        } finally {
            $stmt = null;
            $sql = null;
        }

        return $formattedStr;
    }

    /**
     * Removes whitespaces from an XPath expression to prevent query issues.
     * 
     * @param string $xPathExpr XPath expression to sanitize
     * 
     * @return string
     */
    public static function sanitizeXPath($xPathExpr)
    {
        if (self::emptyTrim($xPathExpr)) {
            return $xPathExpr;
        } else {
            $sanitizedXPath = preg_replace('/\s+/', '', $xPathExpr);
            return $sanitizedXPath;
        }
    }
    
    /**
     * Sanitize a string to remove filesystem-sensitive characters.
     * 
     * Prior to using an untrusted string as part of a filename, removes all characters except A-Z (case insensitive), 0-9, underscore, and hyphen.
     * 
     * @param string $unsafeStr String to sanitize.
     * 
     * @return string
     */
    public static function sanitizeStringForFilename($unsafeStr)
    {
        return preg_replace('/[^a-z0-9_-]+/i', '', $unsafeStr);
    }
    
    /**
	 * Returns the Local Health Department name for a given Jurisdiction ID.
	 *
     * @param PDO $dbConn               PDO connection to the EMSA database
	 * @param int $systemJurisdictionId System Jurisdiction ID
     * 
	 * @return string
	 */
	public static function lhdName(PDO $dbConn, $systemJurisdictionId = 0) {
		$jId = (int) filter_var($systemJurisdictionId, FILTER_SANITIZE_NUMBER_INT);
        $lhdName = null;
        
		if ($jId > 0) {
            try {
                $sql = "SELECT health_district 
                        FROM system_districts 
                        WHERE id = :jId;";
                $stmt = $dbConn->prepare($sql);
                $stmt->bindValue(':jId', $jId, PDO::PARAM_INT);
                $stmt->execute();
                
                $lhdName = (string) filter_var($stmt->fetchColumn(0), \FILTER_SANITIZE_STRING);
            } catch (Throwable $e) {
                ExceptionUtils::logException($e);
            } finally {
                $stmt = null;
                $sql = null;
            }
		}
        
        return (string) $lhdName;
	}
    
    /**
	 * Returns the "Jurisdiction" name for a given custom Jurisdiction ID (used by E-mail Notification).
	 *
     * @param PDO $dbConn          PDO connection to the EMSA database
	 * @param int $jurisdiction_id Custom Jurisdiction ID
	 *
	 * @return string
	 */
	public static function customLhdName(PDO $dbConn, $jurisdiction_id = 0) {
		$jId = (int) filter_var($jurisdiction_id, FILTER_SANITIZE_NUMBER_INT);
        $customLhdName = '';
        
		if ($jId > 0) {
            try {
                $sql = "SELECT name 
                        FROM batch_notification_custom_jurisdictions 
                        WHERE id = :jId;";
                $stmt = $dbConn->prepare($sql);
                $stmt->bindValue(':jId', $jId, PDO::PARAM_INT);
                $stmt->execute();
                
                $customLhdName = (string) filter_var($stmt->fetchColumn(0), \FILTER_SANITIZE_STRING);
            } catch (Throwable $e) {
                ExceptionUtils::logException($e);
            } finally {
                $stmt = null;
                $sql = null;
            }
		}
        
        return trim($customLhdName);
	}

}

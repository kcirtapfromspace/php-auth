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
use Udoh\Emsa\Client\MasterProcessClient;
use Udoh\Emsa\Constants\AppRecordType;
use Udoh\Emsa\Constants\MessageType;
use Udoh\Emsa\Constants\SystemExceptions;
use Udoh\Emsa\Constants\SystemMessageActions;
use Udoh\Emsa\Email\Notification;
use Udoh\Emsa\Exceptions\EmsaMessageNotFoundException;
use Udoh\Emsa\Exceptions\EmsaSoapConnectionFault;
use Udoh\Emsa\Exceptions\VocabularyException;
use Udoh\Emsa\MessageProcessing\BulkSiblingEventLogTuple;
use Udoh\Emsa\MessageProcessing\BulkSiblingList;
use Udoh\Emsa\MessageProcessing\EventLog;
use Udoh\Emsa\MessageProcessing\EventLogExceptionDetail;
use Udoh\Emsa\MessageProcessing\EventLogExceptionDetailSet;
use Udoh\Emsa\MessageProcessing\EventLogMessage;
use Udoh\Emsa\MessageProcessing\EventLogNote;
use Udoh\Emsa\Model\AppRecord;
use Udoh\Emsa\Model\Person;
use Udoh\Emsa\Model\PersonList;
use Udoh\Emsa\Rules\MessageFilterRulesEngine;
use Udoh\Emsa\Rules\WhitelistRule;
use Udoh\Emsa\Rules\WhitelistRuleEvaluator;
use Udoh\Emsa\Rules\WhitelistRuleTupleSet;
use Udoh\Emsa\Utils\AppClientUtils;
use Udoh\Emsa\Utils\AuditUtils;
use Udoh\Emsa\Utils\CodedDataUtils;
use Udoh\Emsa\Utils\ExceptionUtils;
use Udoh\Emsa\Utils\VocabUtils;

/**
 * Utilities for use in EMSA message processing
 *
 * @author Josh Ridderhoff <jridderhoff@utah.gov>
 */
class MessageProcessingUtils
{

    /** @var PDO */
    protected $dbConn = null;

    /** @var AppClientList */
    protected $appClientList;

    /** List of valid destination statuses for message copying
     *
     * @var int[]
     */
    protected $validCopyDestinations = array(
        ENTRY_STATUS,
        UNPROCESSED_STATUS,
        GRAY_STATUS,
        EXCEPTIONS_STATUS,
        NEDSS_EXCEPTION_STATUS,
        GRAY_PENDING_STATUS
    );

    /** List of valid destination statuses for message moving
     *
     * @var int[]
     */
    protected $validMoveDestinations = array(
        ENTRY_STATUS,
        OOS_STATUS,
        UNPROCESSED_STATUS,
        LOCKED_STATUS,
        GRAY_STATUS,
        ASSIGNED_STATUS,
        EXCEPTIONS_STATUS,
        NEDSS_EXCEPTION_STATUS,
        GRAY_PENDING_STATUS,
        GRAY_EXCEPTION_STATUS,
        QA_STATUS,
        SEMI_AUTO_STATUS
    );

    const SURV_OVERRIDE_RULES = 0;
    const SURV_OVERRIDE_YES = 1;
    const SURV_OVERRIDE_NO = 2;
    const ADDCMR_REASON_ADDNEW = 1;
    const ADDCMR_REASON_NOEVENTS = 2;
    const ADDCMR_REASON_NOWHITELIST = 3;
    const ADDCMR_REASON_NOTACASE = 4;
    const ADDCMR_REASON_ONETOMANY = 5;
    const ASSIGNMENT_CHANNEL_WHITELIST = 0;
    const ASSIGNMENT_CHANNEL_GRAYLIST = 1;

    /**
     * Create a new MessageProcessingUtils object
     *
     * @param PDO           $dbConn        PDO connection to EMSA database
     * @param AppClientList $appClientList List of configured applications for this installation
     */
    public function __construct(PDO $dbConn, AppClientList $appClientList)
    {
        $this->dbConn = $dbConn;
        $this->appClientList = $appClientList;
    }

    /**
     * Check to see if specified EMSA message is still unprocessed in its expected queue.
     *
     * Checks to see if a specified message is still active in the specified queue (not deleted, moved, assigned, etc.)
     * clearing the way for the message to be processed by action handlers (edit, retry, addCmr, updateCmr, etc.).
     * Prevents changes to messages that may have already been processed by another user or process, as well as
     * supresses accidental duplicate message processing due to EMSA page reloads.
     *
     * @param int $systemMessageId EMSA message ID
     * @param int $fromFinalStatus ID of the EMSA queue that the message processing was attempted from
     *
     * @return bool <b>TRUE</b> if message is not deleted and in same queue as specified, <b>FALSE</b> otherwise
     */
    public function isMessageUnprocessed($systemMessageId, $fromFinalStatus)
    {
        if (!filter_var($systemMessageId, FILTER_VALIDATE_INT) || !filter_var($fromFinalStatus, FILTER_VALIDATE_INT)) {
            return false;
        }

        try {
            $sql = "SELECT final_status 
                    FROM system_messages 
                    WHERE (id = :systemMessageId) 
                    AND (final_status = :finalStatus) 
                    AND ((deleted IS NULL) OR (deleted = 0));";
            $stmt = $this->dbConn->prepare($sql);
            $stmt->bindValue(':systemMessageId', (int) $systemMessageId, PDO::PARAM_INT);
            $stmt->bindValue(':finalStatus', (int) $fromFinalStatus, PDO::PARAM_INT);

            if ($stmt->execute()) {
                if ($stmt->rowCount() === 1) {
                    return true;
                }
            }
        } catch (Throwable $e) {
            $e = null;
        } finally {
            $stmt = null;
        }

        return false;
    }

    /**
     * Indicates whether the message specified by ID was sent to an interagency partner.
     *
     * @param int $originalMsgID
     *
     * @return bool
     */
    public function isMessageInteragencyTx(int $originalMsgID): bool
    {
        $wasInteragencyTx = false;

        try {
            $sql = "SELECT interagency_recipient FROM system_original_messages WHERE id = :origMsgId;";
            $stmt = $this->dbConn->prepare($sql);
            $stmt->bindValue(":origMsgId", (int) $originalMsgID, PDO::PARAM_INT);

            if ($stmt->execute()) {
                while ($rsObj = $stmt->fetchObject()) {
                    if (!empty($rsObj->interagency_recipient)) {
                        $wasInteragencyTx = true;
                    }
                }
            }
        } catch (Throwable $e) {
            ExceptionUtils::logException($e);
        }

        return $wasInteragencyTx;
    }

    /**
     * Logs an exception event for a system message being processed by EMSA and moves the message to the specified
     * Exception queue.
     *
     * @param int    $systemMessageId System Message ID
     * @param int    $auditLogId      Audit Log record ID
     * @param int    $exceptionId     System exception ID
     * @param string $exceptionInfo   [Optional]<br>Exception description/details
     * @param int    $exceptionList   [Optional]<br>EMSA queue ID of the desired Exception list to move the message to.
     *                                 Default <b>EXCEPTIONS_STATUS</b><br><br>If <1, does not move message after
     *                                 logging exception.
     *
     * @return boolean
     *
     * @throws PDOException on database errors
     */
    public function logMessageException($systemMessageId, $auditLogId, $exceptionId, $exceptionInfo = null, $exceptionList = EXCEPTIONS_STATUS)
    {
        try {
            $this->dbConn->beginTransaction();

            $aeSql = 'INSERT INTO system_audit_exceptions 
                          (system_messages_audits_id, system_exceptions_id, info) 
                      VALUES 
                          (:smAuditId, :exceptionId, :info);';
            $aeStmt = $this->dbConn->prepare($aeSql);
            $aeStmt->bindValue(':smAuditId', $auditLogId, PDO::PARAM_INT);
            $aeStmt->bindValue(':exceptionId', $exceptionId, PDO::PARAM_INT);
            $aeStmt->bindValue(':info', $exceptionInfo, PDO::PARAM_STR);
            $aeStmt->execute();

            $meSql = 'INSERT INTO system_message_exceptions 
                          (system_message_id, exception_id, info) 
                      VALUES 
                          (:systemMessageId, :exceptionId, :info);';
            $meStmt = $this->dbConn->prepare($meSql);
            $meStmt->bindValue(':systemMessageId', $systemMessageId, PDO::PARAM_INT);
            $meStmt->bindValue(':exceptionId', $exceptionId, PDO::PARAM_INT);
            $meStmt->bindValue(':info', $exceptionInfo, PDO::PARAM_STR);
            $meStmt->execute();

            if ($exceptionList > 0) {
                $statusSql = 'UPDATE system_messages 
                        SET final_status = :finalStatus 
                        WHERE id = :systemMessageId;';
                $statusStmt = $this->dbConn->prepare($statusSql);
                $statusStmt->bindValue(':systemMessageId', $systemMessageId, PDO::PARAM_INT);
                $statusStmt->bindValue(':finalStatus', $exceptionList, PDO::PARAM_INT);
                $statusStmt->execute();
            }

            $this->dbConn->commit();
            return true;
        } catch (Throwable $e) {
            ExceptionUtils::logException($e);
            $this->dbConn->rollBack();
            return false;
        } finally {
            $aeStmt = null;
            $meStmt = null;
            $statusStmt = null;
        }
    }

    /**
     * Handle any exceptions that occur during evaluation of Whitelist Rules.
     *
     * @param EventLogMessage        $eventLogMessage
     * @param EmsaMessage            $emsaMessage
     * @param WhitelistRuleEvaluator $initialResults
     * @param WhitelistRuleEvaluator $crossrefResults
     * @param int                    $finalStatus
     *
     * @return bool
     */
    protected function handleWhitelistExceptions(EventLogMessage $eventLogMessage, EmsaMessage $emsaMessage, WhitelistRuleEvaluator $initialResults, WhitelistRuleEvaluator $crossrefResults, $finalStatus)
    {
        $systemMessageId = $emsaMessage->getSystemMessageId();

        if (empty($emsaMessage->getReferenceDate())) {
            $thisAuditId = AuditUtils::auditMessage($this->dbConn, $systemMessageId, SystemMessageActions::PENDING, $finalStatus);  // exception
            if ($emsaMessage->getMessageType() === MessageType::ELR_MESSAGE) {
                $this->logMessageException(
                    $systemMessageId, $thisAuditId, SystemExceptions::WHITELIST_RULE_EXCEPTION, 'Incoming ELR message does not contain a Specimen Collection Date', NEDSS_EXCEPTION_STATUS
                );
                $eventLogMessage->add(new EventLogNote('This lab could not be processed due to a missing Specimen Collection Date.  Message moved to Pending list.', 'ui-icon-elrerror'));
            } else {
                $this->logMessageException(
                    $systemMessageId, $thisAuditId, SystemExceptions::WHITELIST_RULE_EXCEPTION, 'Incoming Clinical Document does not contain an Encounter Date', NEDSS_EXCEPTION_STATUS
                );
                $eventLogMessage->add(new EventLogNote('This message could not be processed due to a missing Encounter Date.  Message moved to Pending list.', 'ui-icon-elrerror'));
            }

            return false;
        }

        if ($emsaMessage->getAllowOneToMany()) {
            // if any exceptions happened anyhwere in One-to-Many mode, stop assignment and deal with the exceptions immediately
            $thisAuditId = AuditUtils::auditMessage($this->dbConn, $systemMessageId, SystemMessageActions::PENDING, $finalStatus);  // exception
            $mergedWhitelistedEventsSet = new WhitelistRuleTupleSet();
            $mergedWhitelistedEventsSet->merge(
                $initialResults->getMorbidityWhitelistEvents(),
                $crossrefResults->getMorbidityWhitelistEvents(),
                $initialResults->getContactWhitelistEvents(),
                $crossrefResults->getContactWhitelistEvents()
            );

            $this->whitelistExceptionIterator($emsaMessage, $thisAuditId, $initialResults->getMorbidityExceptionEvents(), $mergedWhitelistedEventsSet);
            $this->whitelistExceptionIterator($emsaMessage, $thisAuditId, $initialResults->getContactExceptionEvents(), $mergedWhitelistedEventsSet);
            $this->whitelistExceptionIterator($emsaMessage, $thisAuditId, $crossrefResults->getMorbidityExceptionEvents(), $mergedWhitelistedEventsSet);
            $this->whitelistExceptionIterator($emsaMessage, $thisAuditId, $crossrefResults->getContactExceptionEvents(), $mergedWhitelistedEventsSet);
        } else {
            if ($emsaMessage->checkXrefFirst) {
                if (count($crossrefResults->getMorbidityWhitelistEvents()) > 0) {
                    if (count($crossrefResults->getMorbidityExceptionEvents()) > 0) {
                        $thisAuditId = AuditUtils::auditMessage($this->dbConn, $systemMessageId, SystemMessageActions::PENDING, $finalStatus);  // exception
                        $this->whitelistExceptionIterator($emsaMessage, $thisAuditId, $crossrefResults->getMorbidityExceptionEvents(), $crossrefResults->getMorbidityWhitelistEvents());
                    } else {
                        return true;
                    }
                } elseif (count($crossrefResults->getContactWhitelistEvents()) > 0) {
                    if (count($crossrefResults->getContactExceptionEvents()) > 0) {
                        $thisAuditId = AuditUtils::auditMessage($this->dbConn, $systemMessageId, SystemMessageActions::PENDING, $finalStatus);  // exception
                        $this->whitelistExceptionIterator($emsaMessage, $thisAuditId, $crossrefResults->getContactExceptionEvents(), $crossrefResults->getContactWhitelistEvents());
                    } else {
                        return true;
                    }
                } elseif (count($initialResults->getMorbidityWhitelistEvents()) > 0) {
                    if (count($initialResults->getMorbidityExceptionEvents()) > 0) {
                        $thisAuditId = AuditUtils::auditMessage($this->dbConn, $systemMessageId, SystemMessageActions::PENDING, $finalStatus);  // exception
                        $this->whitelistExceptionIterator($emsaMessage, $thisAuditId, $initialResults->getMorbidityExceptionEvents(), $initialResults->getMorbidityWhitelistEvents());
                    } else {
                        return true;
                    }
                } elseif (count($initialResults->getContactWhitelistEvents()) > 0) {
                    if (count($initialResults->getContactExceptionEvents()) > 0) {
                        $thisAuditId = AuditUtils::auditMessage($this->dbConn, $systemMessageId, SystemMessageActions::PENDING, $finalStatus);  // exception
                        $this->whitelistExceptionIterator($emsaMessage, $thisAuditId, $initialResults->getContactExceptionEvents(), $initialResults->getContactWhitelistEvents());
                    } else {
                        return true;
                    }
                }
            } else {
                $mergedMorbidityWhitelistEvents = (new WhitelistRuleTupleSet())->merge(
                    $initialResults->getMorbidityWhitelistEvents(),
                    $crossrefResults->getMorbidityWhitelistEvents()
                );
                $mergedContactWhitelistEvents = (new WhitelistRuleTupleSet())->merge(
                    $initialResults->getContactWhitelistEvents(),
                    $crossrefResults->getContactWhitelistEvents()
                );
                $mergedMorbidityExceptionEvents = (new WhitelistRuleTupleSet())->merge(
                    $initialResults->getMorbidityExceptionEvents(),
                    $crossrefResults->getMorbidityExceptionEvents()
                );
                $mergedContactExceptionEvents = (new WhitelistRuleTupleSet())->merge(
                    $initialResults->getContactExceptionEvents(),
                    $crossrefResults->getContactExceptionEvents()
                );

                if (count($mergedMorbidityWhitelistEvents) > 0) {
                    if (count($mergedMorbidityExceptionEvents) > 0) {
                        $thisAuditId = AuditUtils::auditMessage($this->dbConn, $systemMessageId, SystemMessageActions::PENDING, $finalStatus);  // exception
                        $this->whitelistExceptionIterator($emsaMessage, $thisAuditId, $mergedMorbidityExceptionEvents, $mergedMorbidityWhitelistEvents);
                    } else {
                        return true;
                    }
                } elseif (count($mergedContactWhitelistEvents) > 0) {
                    if (count($mergedContactExceptionEvents) > 0) {
                        $thisAuditId = AuditUtils::auditMessage($this->dbConn, $systemMessageId, SystemMessageActions::PENDING, $finalStatus);  // exception
                        $this->whitelistExceptionIterator($emsaMessage, $thisAuditId, $mergedContactExceptionEvents, $mergedContactWhitelistEvents);
                    } else {
                        return true;
                    }
                }
            }
        }

        $eventLogMessage->add(new EventLogNote('One or more exceptions occurred while attempting to evaluate Whitelist Rules.  Message moved to Pending list.', 'ui-icon-elrerror'));

        try {
            $this->moveMessageToQueue($systemMessageId, $finalStatus, NEDSS_EXCEPTION_STATUS, SystemMessageActions::MESSAGE_MOVED_BY_WHITELIST_RULE);
        } catch (Throwable $e) {
            ExceptionUtils::logException($e);
            $eventLogMessage->add(new EventLogNote('An unexpected error occurred while attempting to move this message.', 'ui-icon-elrerror', $e->getMessage()));
        } finally {
            return false;
        }
    }

    /**
     * Iterate through all Whitelist Exceptions encountered and log them appropriately.
     *
     * @param EmsaMessage           $emsaMessage
     * @param int                   $auditId
     * @param WhitelistRuleTupleSet $exceptionRuleTupleSet
     * @param WhitelistRuleTupleSet $whitelistRuleTupleSet
     */
    protected function whitelistExceptionIterator(EmsaMessage $emsaMessage, $auditId, WhitelistRuleTupleSet $exceptionRuleTupleSet, WhitelistRuleTupleSet $whitelistRuleTupleSet)
    {
        $systemMessageId = $emsaMessage->getSystemMessageId();
        $appClient = $emsaMessage->getAppClient();

        foreach ($exceptionRuleTupleSet as $ruleTuple) {
            $nedssEvent = $ruleTuple->getNedssEvent();
            $ruleApplied = $ruleTuple->getWhitelistRuleApplied()->getRuleType();
            $conditionApplied = $ruleTuple->getConditionApplied();

            if ($nedssEvent->getEventType() === AppRecordType::MORBIDITY_EVENT) {
                // morbidity events
                if (($ruleApplied === WhitelistRule::WHITELIST_RULETYPE_TIME_TREATMENT) || ($ruleApplied === WhitelistRule::WHITELIST_RULETYPE_STD_MULTI)) {
                    $this->logMessageException(
                        $systemMessageId, $auditId, SystemExceptions::WHITELIST_RULE_EXCEPTION, 'No existing treatments found for treatment date-based rule.<br>' . EmsaCompareUtils::nedssLinkByEventId($appClient, $nedssEvent->getEventId(), $nedssEvent->getRecordNumber(), $nedssEvent->getEventType()) . '<br>Other Matched Events:<br>' . EmsaCompareUtils::nedssLinkByWhitelistRuleTupleSet($appClient, $whitelistRuleTupleSet, AppRecordType::MORBIDITY_EVENT, $nedssEvent->getEventId()), -1
                    );
                } elseif ($ruleApplied === WhitelistRule::WHITELIST_RULETYPE_TIME_LAST_POSITIVE) {
                    $this->logMessageException(
                        $systemMessageId, $auditId, SystemExceptions::WHITELIST_RULE_EXCEPTION, 'No existing positive labs found, Whitelist Rule requires most-recent positive lab collection date.<br>' . EmsaCompareUtils::nedssLinkByEventId($appClient, $nedssEvent->getEventId(), $nedssEvent->getRecordNumber(), $nedssEvent->getEventType()) . '<br>Other Matched Events:<br>' . EmsaCompareUtils::nedssLinkByWhitelistRuleTupleSet($appClient, $whitelistRuleTupleSet, AppRecordType::MORBIDITY_EVENT, $nedssEvent->getEventId()), -1
                    );
                } elseif ($ruleApplied === WhitelistRule::WHITELIST_RULETYPE_EXCEPTION) {
                    $this->logMessageException(
                        $systemMessageId, $auditId, SystemExceptions::WHITELIST_RULE_EXCEPTION, 'Morbidity Whitelist Rules require manual review [' . $conditionApplied . ']', -1
                    );
                } else {
                    $this->logMessageException(
                        $systemMessageId, $auditId, SystemExceptions::WHITELIST_RULE_EXCEPTION, 'No existing positive labs found, Whitelist Rule requires first positive lab collection date.<br>' . EmsaCompareUtils::nedssLinkByEventId($appClient, $nedssEvent->getEventId(), $nedssEvent->getRecordNumber(), $nedssEvent->getEventType()) . '<br>Other Matched Events:<br>' . EmsaCompareUtils::nedssLinkByWhitelistRuleTupleSet($appClient, $whitelistRuleTupleSet, AppRecordType::MORBIDITY_EVENT, $nedssEvent->getEventId()), -1
                    );
                }
            } else {
                // contact events
                $this->logMessageException(
                    $systemMessageId, $auditId, SystemExceptions::WHITELIST_RULE_EXCEPTION, 'Contact Whitelist Rules require manual review [' . $conditionApplied . ']', -1
                );
            }
        }
    }

    /**
     * Clear current Exceptions associated with an EMSA message being assigned.
     *
     * @param int $systemMessageId System Message ID
     *
     * @return boolean
     */
    public function clearCurrentMessageExceptions($systemMessageId)
    {
        try {
            $sql = "DELETE FROM system_message_exceptions
                    WHERE system_message_id = :systemMessageId;";
            $stmt = $this->dbConn->prepare($sql);
            $stmt->bindValue(':systemMessageId', $systemMessageId, PDO::PARAM_INT);
            $stmt->execute();
            return true;
        } catch (Throwable $e) {
            ExceptionUtils::logException($e);
        } finally {
            $stmt = null;
        }

        return false;
    }

    /**
     * Adds a QA comment to the specified EMSA message
     *
     * @param int    $systemMessageId System Message ID
     * @param string $userId          User ID of the user adding the comment
     * @param string $qaComment       Comments to add
     *
     * @return boolean
     */
    public function addQAComment($systemMessageId, $userId, $qaComment = null)
    {
        if (EmsaUtils::emptyTrim($qaComment)) {
            return false;
        }

        try {
            $sql = "INSERT INTO system_message_comments 
                        (system_message_id, user_id, comment) 
                    VALUES 
                        (:systemMessageId, :umdId, :comments);";
            $stmt = $this->dbConn->prepare($sql);
            $stmt->bindValue(':systemMessageId', intval($systemMessageId), PDO::PARAM_INT);
            $stmt->bindValue(':umdId', trim($userId), PDO::PARAM_STR);
            $stmt->bindValue(':comments', trim($qaComment), PDO::PARAM_STR);

            if ($stmt->execute()) {
                return true;
            }
        } catch (Throwable $e) {
            ExceptionUtils::logException($e);
        } finally {
            $stmt = null;
        }

        return false;
    }

    /**
     * Wrapper for bulk setting of QA message flag.
     *
     * @param EventLog $eventLog
     * @param array    $systemMessageIds         Array containing ID numbers of the messages in system_messages to set
     *                                           the flag on
     * @param int      $systemMessageFinalStatus Final Status ID of the messages being flagged
     * @param int      $qaFlagId                 Flag ID to set on each message.
     * @param string   $flagOtherReason          [Optional]<br>If 'Other' flag is selected, the user-entered
     *                                           description.
     * @param string   $flagDeErrorType          [Optional]<br>If 'Data Entry Error' flag is selected, which type of
     *                                           Data Entry Error
     */
    public function bulkQaFlagWrapper(EventLog $eventLog, $systemMessageIds, $systemMessageFinalStatus, $qaFlagId, $flagOtherReason = null, $flagDeErrorType = null)
    {
        $eventLog->add(new EventLogNote((int) count($systemMessageIds) . ' messages found to set flags on...', 'ui-icon-flag'));

        $flagComments = null;
        if ($qaFlagId == EMSA_FLAG_DE_ERROR) {
            $flagComments = $flagDeErrorType;
        } elseif ($qaFlagId == EMSA_FLAG_DE_OTHER) {
            $flagComments = $flagOtherReason;
        }

        foreach ($systemMessageIds as $systemMessageId) {
            $eventLogMessage = new EventLogMessage($systemMessageId);
            if ($this->setQaFlag((int)$systemMessageId, $systemMessageFinalStatus, $qaFlagId, $flagComments)) {
                $eventLogMessage->setProcessedSuccessfully(true);
                $eventLogMessage->add(new EventLogNote('Message flag successfully set!', 'ui-icon-flag'));
            } else {
                $eventLogMessage->setProcessedSuccessfully(false);
                $eventLogMessage->add(new EventLogNote('Error:  Could not set message flag.', 'ui-icon-elrerror'));
            }
            $eventLog->add($eventLogMessage);
        }
    }

    /**
     * Sets the QA flag and, if provided, comments associated with the flag, for an EMSA message.
     *
     * @param int    $systemMessageId          System Message ID
     * @param int    $systemMessageFinalStatus Final Status ID of the message being flagged
     * @param int    $qaFlagId                 Flag ID
     * @param string $flagComments             [Optional]<br>Comments
     *
     * @return boolean
     */
    public function setQaFlag($systemMessageId, $systemMessageFinalStatus, $qaFlagId, $flagComments = null)
    {
        if (empty($systemMessageId) || empty($systemMessageFinalStatus) || empty($qaFlagId)) {
            return false;
        }

        try {
            $sql = "UPDATE system_messages 
                SET message_flags = message_flags | :flagId 
                WHERE id = :systemMessageId;";
            $stmt = $this->dbConn->prepare($sql);
            $stmt->bindValue(':flagId', intval($qaFlagId), PDO::PARAM_INT);
            $stmt->bindValue(':systemMessageId', intval($systemMessageId), PDO::PARAM_INT);

            if ($stmt->execute()) {
                if (!EmsaUtils::emptyTrim($flagComments)) {
                    EmsaUtils::addOrUpdateQaFlagComment($this->dbConn, intval($systemMessageId), intval($qaFlagId), $flagComments);  // comment was passed along with the flag; store in system_message_flag_comments
                }
                AuditUtils::auditMessage($this->dbConn, intval($systemMessageId), SystemMessageActions::MESSAGE_FLAG_SET, intval($systemMessageFinalStatus), EmsaUtils::decodeMessageQaFlag($this->dbConn, intval($qaFlagId)) . ((!is_null($flagComments)) ? ' (' . $flagComments . ')' : ''));
                return true;
            }
        } catch (Throwable $e) {
            ExceptionUtils::logException($e);
        } finally {
            $stmt = null;
        }

        return false;
    }

    /**
     * Un-set a specific QA flag from the specified EMSA message
     *
     * @param int $systemMessageId System Message ID
     * @param int $qaFlag          QA Flag ID to clear
     *
     * @return boolean
     */
    public function unsetQaFlag($systemMessageId, $qaFlag)
    {
        try {
            $sql = "UPDATE system_messages 
                    SET message_flags = message_flags & ~:oldQaFlag::integer
                    WHERE id = :systemMessageId;";
            $stmt = $this->dbConn->prepare($sql);
            $stmt->bindValue(':oldQaFlag', intval($qaFlag), PDO::PARAM_INT);
            $stmt->bindValue(':systemMessageId', intval($systemMessageId), PDO::PARAM_INT);

            if ($stmt->execute()) {
                return true;
            }
        } catch (Throwable $e) {
            ExceptionUtils::logException($e);
        } finally {
            $stmt = null;
        }

        return false;
    }

    /**
     * Creates a copy of an existing EMSA message in another EMSA queue.
     *
     * @param int    $systemMessageId   System Message ID to clone
     * @param int    $finalStatus       [Optional]<br>Destination queue ID to clone the message into.<br>If not
     *                                  specified, copies the message to the "Exception" queue
     * @param string $reasonDescription [Optional]<br>Reason for creating a copy of the message.
     *
     * @return int System Message ID of the newly-copied message
     *
     * @throws Exception if <i>systemMessageId</i> is not valid.
     * @throws PDOException on any database errors.
     */
    public function copyMessageToQueue($systemMessageId, $finalStatus = EXCEPTIONS_STATUS, $reasonDescription = null)
    {
        $newSystemMessageId = 0;

        if (empty($systemMessageId) || (intval($systemMessageId) < 1)) {
            throw new Exception('Could not clone message:  Valid Message ID not provided.');
        }

        if (empty($finalStatus) || !in_array($finalStatus, $this->validCopyDestinations)) {
            throw new Exception('Could not copy message:  Valid destination not provided');
        }

        $copyReason = 'Message copied from MsgID ' . trim(intval($systemMessageId)) . '.';
        if (!empty($reasonDescription)) {
            $copyReason .= ' (' . trim($reasonDescription) . ')';
        }

        $sql = 'SELECT clone_system_message_by_id(:sys_msg_id, :dest_status);';
        $stmt = $this->dbConn->prepare($sql);
        $stmt->bindValue(':sys_msg_id', $systemMessageId, PDO::PARAM_INT);
        $stmt->bindValue(':dest_status', $finalStatus, PDO::PARAM_INT);
        if ($stmt->execute()) {
            $newSystemMessageId = intval($stmt->fetchColumn(0));
        }

        AuditUtils::auditMessage($this->dbConn, $newSystemMessageId, SystemMessageActions::MESSAGE_COPY_CREATED, $finalStatus, $copyReason); // message copy created

        return $newSystemMessageId;
    }

    /**
     * Creates a copy of an existing EMSA message for purposes of One-to-Many assignment.
     *
     * @param int    $systemMessageId   System Message ID to clone
     * @param int    $finalStatus       EMSA message queue where the message resides.
     * @param int    $targetEventId     [Optional]<br>Event ID this copy of the message was targeted to for
     *                                  One-to-Many.  Leave blank if copy is being created to add a new event.
     * @param string $reasonDescription [Optional]<br>Reason for creating a copy of the message.
     *
     * @return int System Message ID of the newly-copied message
     *
     * @throws Exception If <i>systemMessageId</i> is not valid.
     * @throws PDOException On any database errors.
     */
    protected function copyMessageForOneToMany($systemMessageId, $finalStatus, $targetEventId = null, $reasonDescription = null)
    {
        $newSystemMessageId = 0;

        if (empty($systemMessageId) || ((int) $systemMessageId < 1)) {
            throw new Exception('Could not copy message:  Valid Message ID not provided.');
        }

        $copyReason = 'Message copied from MsgID ' . (int) $systemMessageId . ' for One-to-Many assignment.';
        if (!empty($reasonDescription)) {
            $copyReason .= ' (' . trim($reasonDescription) . ')';
        }

        if (empty($targetEventId)) {
            $sql = "SELECT o2m_copy_system_message_by_id(:msgId);";
            $stmt = $this->dbConn->prepare($sql);
            $stmt->bindValue(':msgId', $systemMessageId, PDO::PARAM_INT);
        } else {
            $sql = "SELECT o2m_copy_system_message_by_id(:msgId, :targetEventId);";
            $stmt = $this->dbConn->prepare($sql);
            $stmt->bindValue(':msgId', $systemMessageId, PDO::PARAM_INT);
            $stmt->bindValue(':targetEventId', $targetEventId, PDO::PARAM_INT);
        }

        if ($stmt->execute()) {
            $newSystemMessageId = (int) $stmt->fetchColumn(0);
        }

        AuditUtils::auditMessage($this->dbConn, $newSystemMessageId, SystemMessageActions::MESSAGE_COPY_CREATED, $finalStatus, $copyReason); // message copy created

        return $newSystemMessageId;
    }

    /**
     * Sets One-to-Many properties on a EMSA message.
     *
     * @param int $systemMessageId System Message ID to update.
     * @param int $targetEventId   [Optional]<br>For cases where an event is to be updated via One-to-Many, the Event
     *                             ID this message is targeted to update.  Leave blank if message will add a new event.
     *
     * @throws Exception If <i>systemMessageId</i> is not valid.
     */
    protected function setO2MFlagsForMessage($systemMessageId, $targetEventId = null)
    {
        if (empty($systemMessageId) || ((int) $systemMessageId < 1)) {
            throw new Exception('Could not copy message:  Valid Message ID not provided.');
        }

        try {
            if (empty($targetEventId)) {
                $sql = "UPDATE system_messages SET o2m_performed = TRUE WHERE id = :msgId";
                $stmt = $this->dbConn->prepare($sql);
                $stmt->bindValue(':msgId', $systemMessageId, PDO::PARAM_INT);
                $stmt->execute();
            } else {
                $sql = "UPDATE system_messages SET o2m_performed = TRUE, o2m_event_id = :eventId WHERE id = :msgId";
                $stmt = $this->dbConn->prepare($sql);
                $stmt->bindValue(':msgId', $systemMessageId, PDO::PARAM_INT);
                $stmt->bindValue(':eventId', $targetEventId, PDO::PARAM_INT);
                $stmt->execute();
            }
        } catch (Throwable $e) {
            ExceptionUtils::logException($e);
        } finally {
            $stmt = null;
        }
    }

    /**
     * Moves a message from one EMSA queue to another and logs a record of the move in the message's Audit Log.
     *
     * @param int    $systemMessageId   System Message ID to move
     * @param int    $fromFinalStatus   EMSA queue ID the message is being moved from.
     * @param int    $toFinalStatus     EMSA queue ID the message is being moved to.
     * @param int    $moveType          One of <b>SystemMessageActions::MESSAGE_MOVED_BY_USER</b> or
     *                                  <b>SystemMessageActions::MESSAGE_MOVED_BY_WHITELIST_RULE</b>.<br>Indicates
     *                                  whether the move was user-triggered or rule-triggered.
     * @param string $reasonDescription [Optional]<br>Reason for moving the message.
     *
     * @return boolean
     *
     * @throws Exception When <i>systemMessageId</i> is not valid or to/from queue IDs are missing/invalid.
     */
    public function moveMessageToQueue($systemMessageId, $fromFinalStatus, $toFinalStatus, $moveType, $reasonDescription = null)
    {
        if (empty($systemMessageId) || (intval($systemMessageId) < 1)) {
            throw new Exception('Could not move message:  Valid Message ID not provided.');
        }

        if (empty($fromFinalStatus) || !in_array($fromFinalStatus, $this->validMoveDestinations)) {
            throw new Exception('Could not move message:  Valid origin not provided');
        }

        if (empty($toFinalStatus) || !in_array($toFinalStatus, $this->validMoveDestinations)) {
            throw new Exception('Could not move message:  Valid destination not provided');
        }

        try {
            $sql = "UPDATE system_messages 
                    SET final_status = :dest_status
                    WHERE id = :sys_msg_id;";
            $stmt = $this->dbConn->prepare($sql);
            $stmt->bindValue(':sys_msg_id', $systemMessageId, PDO::PARAM_INT);
            $stmt->bindValue(':dest_status', $toFinalStatus, PDO::PARAM_INT);

            if ($stmt->execute()) {
                AuditUtils::auditMessage($this->dbConn, $systemMessageId, $moveType, $fromFinalStatus, $reasonDescription);
                return true;
            }
        } catch (Throwable $e) {
            ExceptionUtils::logException($e);
        } finally {
            $stmt = null;
        }

        return false;
    }

    /**
     * Graylists an EMSA message during whitelist assignment and logs a record of the move in the message's Audit Log.
     *
     * @param EmsaMessage $emsaMessage       EMSA message being graylisted
     * @param int         $fromFinalStatus   EMSA queue ID the message is being moved from.
     * @param string      $reasonDescription [Optional]<br>Reason for moving the message.
     *
     * @return boolean
     *
     * @throws Exception When <i>systemMessageId</i> is not valid.
     */
    public function graylistMessage($emsaMessage, $fromFinalStatus, $reasonDescription = null)
    {
        $systemMessageId = $emsaMessage->getSystemMessageId();
        $copyParentId = $emsaMessage->getCopyParentId();

        if (empty($systemMessageId) || (intval($systemMessageId) < 1)) {
            throw new Exception('Could not Graylist message:  Valid Message ID not provided.');
        }

        if (empty($fromFinalStatus) || !in_array($fromFinalStatus, $this->validMoveDestinations)) {
            throw new Exception('Could not move message:  Valid origin not provided');
        }

        if (!empty($copyParentId) && ((int) $copyParentId > 0)) {
            // this message is a clone; don't graylist it, just delete it
            return $this->permanentlyDeleteMessage($systemMessageId);
        }

        try {
            $sql = "UPDATE system_messages 
                    SET final_status = :dest_status,
                        assigned_date = LOCALTIMESTAMP,
                        status = 0,
                        external_system_id = NULL,
                        deleted = 0,
                        event_id = 0,
                        lab_result_id = 0
                    WHERE id = :sys_msg_id;";
            $stmt = $this->dbConn->prepare($sql);
            $stmt->bindValue(':sys_msg_id', $systemMessageId, PDO::PARAM_INT);
            $stmt->bindValue(':dest_status', GRAY_STATUS, PDO::PARAM_INT);

            if ($stmt->execute()) {
                AuditUtils::auditMessage($this->dbConn, $systemMessageId, SystemMessageActions::MESSAGE_MOVED_BY_WHITELIST_RULE, $fromFinalStatus, 'Message moved to Gray list: ' . $reasonDescription);
                return true;
            }
        } catch (Throwable $e) {
            ExceptionUtils::logException($e);
        } finally {
            $stmt = null;
        }

        return false;
    }

    /**
     * Discard an EMSA message as a duplicate lab result.
     *
     * @param EmsaMessage $emsaMessage EMSA Message to discard
     * @param int         $appRecordId Record ID in the target Application that the duplicate lab result was found in
     * @param int         $labResultId ID of the duplicate lab results
     * @param int         $discardList [Optional]<br>Final destination to move the discarded message to.  Default
     *                                 <b>ASSIGNED_STATUS</b>.
     *
     * @return boolean
     */
    public function discardDuplicateMessage(EmsaMessage $emsaMessage, $appRecordId = null, $labResultId = null, $discardList = ASSIGNED_STATUS)
    {
        $systemMessageId = $emsaMessage->getSystemMessageId();
        $copyParentId = $emsaMessage->getCopyParentId();

        if (!empty($copyParentId) && ((int) $copyParentId > 0)) {
            // this message is a clone; don't discard it, just delete it
            return $this->permanentlyDeleteMessage($systemMessageId);
        }

        try {
            $sql = 'UPDATE system_messages 
                    SET final_status = :dest_status, 
                    assigned_date = LOCALTIMESTAMP,
                    deleted = 1,
                    status = 0,
                    event_id = :appRecordId,
                    lab_result_id = :labResultId
                    WHERE id = :systemMessageId;';
            $stmt = $this->dbConn->prepare($sql);
            $stmt->bindValue(':systemMessageId', $systemMessageId, PDO::PARAM_INT);
            $stmt->bindValue(':dest_status', $discardList, PDO::PARAM_INT);
            $stmt->bindValue(':appRecordId', $appRecordId, PDO::PARAM_INT);
            $stmt->bindValue(':labResultId', $labResultId, PDO::PARAM_INT);

            if ($stmt->execute()) {
                return true;
            }
        } catch (Throwable $e) {
            ExceptionUtils::logException($e);
        } finally {
            $stmt = null;
        }

        return false;
    }

    /**
     * Mark an EMSA message as deleted
     *
     * @param int    $systemMessageId System Message ID to delete
     * @param int    $fromFinalStatus ID of the queue the messages are being deleted from
     * @param string $comments        [Optional]<br>Reason for deleting the message
     *
     * @return boolean
     */
    public function markMessageDeleted($systemMessageId, $fromFinalStatus, $comments = null)
    {
        try {
            $sql = "UPDATE system_messages 
                    SET deleted = 1 
                    WHERE id = :systemMessageId;";
            $stmt = $this->dbConn->prepare($sql);
            $stmt->bindValue(':systemMessageId', intval($systemMessageId), PDO::PARAM_INT);

            if ($stmt->execute()) {
                AuditUtils::auditMessage($this->dbConn, $systemMessageId, SystemMessageActions::MESSAGE_DELETED, $fromFinalStatus, $comments);
                return true;
            }
        } catch (Throwable $e) {
            ExceptionUtils::logException($e);
        } finally {
            $stmt = null;
        }

        return false;
    }

    /**
     * Permanently deletes an EMSA message
     *
     * <b>WARNING:</b>  This method permanently deletes the row for this message from the database.  All associated
     * exceptions, audit logs, etc. will be irrevocably lost.  Only use when you're sure this message needs to
     * completely disappear!
     *
     * @param int $systemMessageId System Message ID to delete
     *
     * @return bool
     */
    protected function permanentlyDeleteMessage($systemMessageId)
    {
        try {
            $sql = "DELETE FROM system_messages 
                    WHERE id = :systemMessageId;";
            $stmt = $this->dbConn->prepare($sql);
            $stmt->bindValue(':systemMessageId', (int) $systemMessageId, PDO::PARAM_INT);

            if ($stmt->execute()) {
                return true;
            }
        } catch (Throwable $e) {
            ExceptionUtils::logException($e);
        } finally {
            $stmt = null;
        }

        return false;
    }

    /**
     * Wrapper for bulk message retrying.  Requires an array of system_message IDs.
     *
     * @param EventLog $eventLog
     * @param array    $systemMessageIds Array containing ID numbers of the messages in system_messages to retry
     */
    public function bulkRetryWrapper(EventLog $eventLog, array $systemMessageIds)
    {
        $eventLog->add(new EventLogNote((int) count($systemMessageIds) . ' messages found to retry...', 'ui-icon-elrretry'));

        foreach ($systemMessageIds as $retryMsgId) {
            $eventLogMessage = new EventLogMessage($retryMsgId);
            $this->bulkRetryByMessageId($eventLogMessage, (int)$retryMsgId);
            $eventLog->add($eventLogMessage);
        }
    }

    /**
     * Attempts to re-process an excepted message from ELR through the Master Save process in order to revalidate &
     * clear exceptions.  Used from the bulk "E-Task" list.
     *
     * Returns -1 on error, 0 if exception persists after revalidation, or 1 on successful Master Save process.
     *
     * @param EventLogMessage $eventLogMessage
     * @param int             $systemMessageId ID number of the message in system_messages to retry
     *
     * @return void
     */
    public function bulkRetryByMessageId(EventLogMessage $eventLogMessage, $systemMessageId)
    {
        $xml = null;
        $msgType = null;
        $appId = null;

        // ensure a valid message ID was passed
        if (!filter_var($systemMessageId, FILTER_VALIDATE_INT)) {
            $eventLogMessage->setProcessedSuccessfully(false);
            $eventLogMessage->add(new EventLogNote("Invalid message ID supplied; message not found.", "ui-icon-elrerror"));
            return;
        }

        try {
            $sql = "SELECT master_xml, final_status, vocab_app_id  
                    FROM system_messages 
                    WHERE id = :systemMessageId;";
            $stmt = $this->dbConn->prepare($sql);
            $stmt->bindValue(':systemMessageId', intval($systemMessageId), PDO::PARAM_INT);

            if (($stmt->execute() === false) || ($stmt->rowCount() !== 1)) {
                $eventLogMessage->setProcessedSuccessfully(false);
                $eventLogMessage->add(new EventLogNote("Invalid message ID supplied; message not found.", "ui-icon-elrerror"));
                return;
            } else {
                $row = $stmt->fetchObject();
                $xml = trim($row->master_xml);
                $msgType = intval($row->final_status);
                $appId = (int)$row->vocab_app_id;
            }
        } catch (Throwable $e) {
            ExceptionUtils::logException($e);
            $eventLogMessage->setProcessedSuccessfully(false);
            $eventLogMessage->add(new EventLogNote("Database error occurred while attempting to validate message.", "ui-icon-elrerror", $e->getMessage()));
            return;
        }

        if (empty($xml)) {
            $eventLogMessage->setProcessedSuccessfully(false);
            $eventLogMessage->add(new EventLogNote("No Master XML found for specified message.", "ui-icon-elrerror"));
            return;
        } else {
            libxml_disable_entity_loader(true);
            $sxe = simplexml_load_string($xml);
            libxml_disable_entity_loader(false);
            unset($sxe->exceptions);  // no longer used, clean up to avoid confusion -- exceptions stored in db

            $xmlstring = $sxe->asXML();

            // send updated master xml through master save process
            $encoded_masterxml = urlencode(str_replace('<?xml version="1.0" encoding="UTF-8"?>', '', $xmlstring));

            $xml_for_masterservice = new SimpleXMLElement("<health_message></health_message>");
            $xml_for_masterservice->addChild('user_id', EPITRAX_AUTH_ELR_UID);
            $xml_for_masterservice->addChild('system_message_id', filter_var($systemMessageId, FILTER_SANITIZE_NUMBER_INT));
            $xml_for_masterservice->addChild("system", $appId);  // application ID
            $xml_for_masterservice->addChild('health_xml', $encoded_masterxml);

            try {
                $masterProcessClient = new MasterProcessClient();
            } catch (Throwable $e) {
                $eventLogMessage->setProcessedSuccessfully(false);
                $eventLogMessage->add(new EventLogNote("Unexpected Master Process error encountered.", "ui-icon-elrerror", $e->getMessage()));
                return;
            }

            if ($masterProcessClient) {
                try {
                    $saveMasterResponse = $masterProcessClient->saveMaster($xml_for_masterservice);
                    $saveMasterStatus = $masterProcessClient->validateResponse($saveMasterResponse);
                } catch (Throwable $e) {
                    $eventLogMessage->setProcessedSuccessfully(false);
                    $eventLogMessage->add(new EventLogNote("Unexpected Master Process error encountered.", "ui-icon-elrerror", $e->getMessage()));
                    return;
                }

                if ($saveMasterStatus->getStatus()) {
                    AuditUtils::auditMessage($this->dbConn, filter_var($systemMessageId, FILTER_SANITIZE_NUMBER_INT), SystemMessageActions::MESSAGE_RETRIED, $msgType, 'Via Bulk Retry');

                    $currentStatus = $this->getMessageLocationById($systemMessageId);

                    $currentSql = "SELECT se.description AS description, sme.info AS info, ss.name AS type 
                                   FROM system_message_exceptions sme 
                                   INNER JOIN system_exceptions se ON (sme.exception_id = se.exception_id) 
                                   INNER JOIN system_statuses ss ON (se.exception_type_id = ss.id) 
                                   WHERE sme.system_message_id = :systemMessageId
                                   ORDER BY sme.id;";
                    $currentStmt = $this->dbConn->prepare($currentSql);
                    $currentStmt->bindValue(':systemMessageId', intval($systemMessageId), PDO::PARAM_INT);

                    if ($currentStmt->execute()) {
                        if ($currentStmt->rowCount() > 0) {
                            $eventLogExceptionDetails = new EventLogExceptionDetailSet();

                            while ($currentRow = $currentStmt->fetchObject()) {
                                $eventLogExceptionDetails->add(new EventLogExceptionDetail($currentRow->type, $currentRow->description, $currentRow->info));
                            }

                            $eventLogMessage->setProcessedSuccessfully(false);
                            $eventLogMessage->add(new EventLogNote("Message moved to '$currentStatus' queue.", "ui-icon-elrerror", null, $eventLogExceptionDetails));
                        } else {
                            $eventLogMessage->setProcessedSuccessfully(true);
                            $eventLogMessage->add(new EventLogNote("Message validated successfully!  Message moved to '$currentStatus' queue.", "ui-icon-elrretry"));
                        }
                    } else {
                        $eventLogMessage->setProcessedSuccessfully(false);
                        $eventLogMessage->add(new EventLogNote("Database error occurred.", "ui-icon-elrerror", $this->dbConn->errorCode()));
                        return;
                    }
                } else {
                    $eventLogMessage->setProcessedSuccessfully(false);
                    $eventLogMessage->add(new EventLogNote("Unexpected Master Process error encountered.", "ui-icon-elrerror", $saveMasterStatus->getErrorString()));
                    return;
                }
            }
        }
    }

    /**
     * Revalidate & reprocess an EMSA message by System Message ID
     *
     * @param int             $systemMessageId System Message ID of the message to reprocess
     * @param EventLogMessage $eventLogMessage Event Log Message object representing the message being reprocessed.
     * @param int             $finalStatus     [Optional]<br>EMSA message queue ID the message is being assigned from.
     *                                         Default <b>ENTRY_STATUS</b> (Entry queue).
     *
     * @return boolean
     *
     * @throws EmsaMessageNotFoundException
     * @throws VocabularyException
     */
    public function retryMessage($systemMessageId, EventLogMessage $eventLogMessage, $finalStatus = ENTRY_STATUS)
    {
        // get master xml from database
        $emsaMessage = new EmsaMessage($this->dbConn, $this->appClientList, $systemMessageId, false, true);
        $masterXml = $emsaMessage->getMasterXML();

        // check for 'original_*' paths, restore lab data for susceptibilities/reflex tests before sending back to MP
        if (!empty($masterXml->labs->original_collection_date)) {
            $masterXml->labs->collection_date = trim($masterXml->labs->original_collection_date);
        }
        if (!empty($masterXml->labs->original_accession_number)) {
            $masterXml->labs->accession_number = trim($masterXml->labs->original_accession_number);
        }
        if (!empty($masterXml->labs->original_specimen_source)) {
            $masterXml->labs->specimen_source = trim($masterXml->labs->original_specimen_source);
        }
        if (!empty($masterXml->labs->original_loinc_code)) {
            $masterXml->labs->loinc_code = trim($masterXml->labs->original_loinc_code);
        }
        if (!empty($masterXml->labs->original_test_date)) {
            $masterXml->labs->lab_test_date = trim($masterXml->labs->original_test_date);
        }
        if (!empty($masterXml->labs->original_result_value)) {
            $masterXml->labs->result_value = trim($masterXml->labs->original_result_value);
        }
        if (!empty($masterXml->labs->original_units)) {
            $masterXml->labs->units = trim($masterXml->labs->original_units);
        }
        if (!empty($masterXml->labs->original_test_result)) {
            $masterXml->labs->test_result = trim($masterXml->labs->original_test_result);
        }
        if (!empty($masterXml->labs->original_abnormal_flag)) {
            $masterXml->labs->abnormal_flag = trim($masterXml->labs->original_abnormal_flag);
        }
        if (!empty($masterXml->labs->original_reference_range)) {
            $masterXml->labs->reference_range = trim($masterXml->labs->original_reference_range);
        }
        if (!empty($masterXml->labs->original_test_status)) {
            $masterXml->labs->test_status = trim($masterXml->labs->original_test_status);
        }

        $masterXmlString = $masterXml->asXML();

        // send master xml through master save process
        $encodedMasterXml = urlencode(str_replace('<?xml version="1.0" encoding="UTF-8" standalone="yes"?>', '', $masterXmlString));

        $saveMasterXml = new SimpleXMLElement('<?xml version="1.0" encoding="UTF-8" standalone="yes"?><health_message></health_message>');
        $saveMasterXml->addChild("user_id", EPITRAX_AUTH_ELR_UID);
        $saveMasterXml->addChild("system_message_id", (int)$emsaMessage->getSystemMessageId());
        $saveMasterXml->addChild("system", (int)$emsaMessage->getApplicationId());  // application ID
        $saveMasterXml->addChild("health_xml", $encodedMasterXml);

        try {
            $masterProcessClient = new MasterProcessClient();
        } catch (EmsaSoapConnectionFault $e) {
            ExceptionUtils::logException($e);
            $this->logMessageException(
                $systemMessageId, AuditUtils::auditMessage($this->dbConn, $systemMessageId, SystemMessageActions::EXCEPTION, $finalStatus), SystemExceptions::SQLA_ERROR, $e->getMessage()
            );
            $eventLogMessage->setProcessedSuccessfully(false);
            $eventLogMessage->add(new EventLogNote('Message moved to Exception list.', 'ui-icon-elrerror', $e->getMessage()));
            return false;
        } catch (Throwable $e) {
            ExceptionUtils::logException($e);
            $this->logMessageException(
                $systemMessageId, AuditUtils::auditMessage($this->dbConn, $systemMessageId, SystemMessageActions::EXCEPTION, $finalStatus), SystemExceptions::SQLA_ERROR, 'Unexpected SOAP error:  ' . $e->getMessage()
            );
            $eventLogMessage->setProcessedSuccessfully(false);
            $eventLogMessage->add(new EventLogNote('Message moved to Exception list.', 'ui-icon-elrerror', $e->getMessage()));
            return false;
        }

        $eventLogMessage->add(new EventLogNote("Revalidating message...", "ui-icon-elrretry"));

        try {
            $saveMasterReturn = $masterProcessClient->saveMaster($saveMasterXml);
            $saveMasterStatus = $masterProcessClient->validateResponse($saveMasterReturn);
        } catch (Throwable $e) {
            ExceptionUtils::logException($e);
            $eventLogMessage->setProcessedSuccessfully(false);
            $eventLogMessage->add(new EventLogNote('Unable to process message.  The following error occurred:', 'ui-icon-elrerror', $e->getMessage()));
            return false;
        }

        if ($saveMasterStatus->getStatus()) {
            AuditUtils::auditMessage($this->dbConn, $emsaMessage->getSystemMessageId(), SystemMessageActions::MESSAGE_RETRIED, $finalStatus, 'Retry only; no changes made');

            $postValidationMsgStatus = $this->getMessageLocationById($emsaMessage->getSystemMessageId());

            $currentSql = 'SELECT se.description AS description, sme.info AS info, ss.name AS type 
                           FROM system_message_exceptions sme 
                           INNER JOIN system_exceptions se ON (sme.exception_id = se.exception_id) 
                           INNER JOIN system_statuses ss ON (se.exception_type_id = ss.id) 
                           WHERE sme.system_message_id = :systemMessageId 
                           ORDER BY sme.id;';
            $currentStmt = $this->dbConn->prepare($currentSql);
            $currentStmt->bindValue(':systemMessageId', $emsaMessage->getSystemMessageId(), PDO::PARAM_INT);

            if ($currentStmt->execute()) {
                if ($currentStmt->rowCount() > 0) {
                    $eventLogExceptionDetails = new EventLogExceptionDetailSet();

                    while ($currentRow = $currentStmt->fetchObject()) {
                        $eventLogExceptionDetails->add(new EventLogExceptionDetail($currentRow->type, $currentRow->description, $currentRow->info));
                    }
                    $eventLogMessage->setProcessedSuccessfully(false);
                    $eventLogMessage->add(new EventLogNote("Message moved to '" . $postValidationMsgStatus . "' queue.", 'ui-icon-elrerror', null, $eventLogExceptionDetails));
                } else {
                    $eventLogMessage->setProcessedSuccessfully(true);
                    $eventLogMessage->add(new EventLogNote("Message validated with no errors!  Message moved to '" . $postValidationMsgStatus . "' queue.", "ui-icon-elrsuccess"));
                }
            } else {
                $eventLogMessage->setProcessedSuccessfully(false);
                $eventLogMessage->add(new EventLogNote("Could not verify successful message validation due to database errors.  Message moved to '" . $postValidationMsgStatus . "' queue.", 'ui-icon-elrerror'));
                return false;
            }
        } else {
            $eventLogMessage->setProcessedSuccessfully(false);
            $eventLogMessage->add(new EventLogNote('Unable to revalidate message.', 'ui-icon-elrerrorr', $saveMasterStatus->getErrorString()));
            return false;
        }

        return true;
    }

    /**
     * Returns the name of the message queue a specified EMSA message is currently found in.
     *
     * @param int $systemMessageId System Message ID
     *
     * @return string
     */
    public function getMessageLocationById($systemMessageId)
    {
        $queueName = "Unknown";

        try {
            $sql = 'SELECT ss.name 
                    FROM system_statuses ss 
                    INNER JOIN system_messages sm ON (sm.final_status = ss.id AND sm.id = :systemMessageId);';
            $stmt = $this->dbConn->prepare($sql);
            $stmt->bindValue(':systemMessageId', intval($systemMessageId), PDO::PARAM_INT);
            if ($stmt->execute() && ($stmt->rowCount() === 1)) {
                $queueName = trim($stmt->fetchColumn(0));
            }
        } catch (Throwable $e) {
            ExceptionUtils::logException($e);
        }

        return $queueName;
    }

    /**
     * Find all active EMSA messages that are siblings (came from the same original message, in the same queue, and
     * destined for the same application).
     *
     * @param int           $originalMessageId
     * @param int           $finalStatus
     * @param AppClientList $appClientList List of configured applications for this installation
     * @param int           $appId
     *
     * @return BulkSiblingList
     */
    public function getMessageSiblings($originalMessageId, $finalStatus, $appClientList, $appId)
    {
        $siblingMessages = new BulkSiblingList();

        try {
            $sql = "SELECT id
                    FROM system_messages
                    WHERE original_message_id = :origMsgId
                    AND final_status = :queueId
                    AND vocab_app_id = :appId
                    AND deleted = 0;";
            $stmt = $this->dbConn->prepare($sql);
            $stmt->bindValue(':origMsgId', $originalMessageId, PDO::PARAM_INT);
            $stmt->bindValue(':queueId', $finalStatus, PDO::PARAM_INT);
            $stmt->bindValue(':appId', $appId, PDO::PARAM_INT);

            $stmt->execute();

            while ($row = $stmt->fetchObject()) {
                $msgId = (int)$row->id;
                $eventLogMessage = new EventLogMessage($msgId);
                $bulkTuple = new BulkSiblingEventLogTuple();

                try {
                    $emsaMessage = new EmsaMessage($this->dbConn, $appClientList, $msgId, false, true);
                    $bulkTuple->setEmsaMessage($emsaMessage);
                } catch (EmsaMessageNotFoundException $nfe) {
                    // message not found
                    ExceptionUtils::logException($nfe);
                    $this->logMessageException(
                        $msgId,
                        AuditUtils::auditMessage($this->dbConn, $msgId, SystemMessageActions::PENDING, ENTRY_STATUS),
                        SystemExceptions::ENTRY_QUEUE_EXCEPTION,
                        $nfe->getMessage(),
                        NEDSS_EXCEPTION_STATUS
                    );
                    $eventLogMessage->add(new EventLogNote('Message moved to Pending list.', 'ui-icon-elrerror', $nfe->getMessage()));
                } catch (VocabularyException $ave) {
                    ExceptionUtils::logException($ave);
                    $this->logMessageException(
                        $msgId,
                        AuditUtils::auditMessage($this->dbConn, $msgId, SystemMessageActions::EXCEPTION, ENTRY_STATUS),
                        SystemExceptions::UNABLE_TO_FIND_APPLICATION_CODE,
                        $ave->getMessage(),
                        EXCEPTIONS_STATUS
                    );
                    $eventLogMessage->add(new EventLogNote('Message moved to Exceptions list.', 'ui-icon-elrerror', $ave->getMessage()));
                } catch (Throwable $e) {
                    ExceptionUtils::logException($e);
                    $eventLogMessage->add(new EventLogNote('Message could not be processed; an unexpected error occurred:', 'ui-icon-elrerror', $e->getMessage()));
                } finally {
                    $bulkTuple->setEventLogMessage($eventLogMessage);
                    $siblingMessages->add($bulkTuple);
                }
            }
        } catch (Throwable $e) {
            ExceptionUtils::logException($e);
        }

        return $siblingMessages;
    }

    /**
     * Use an EMSA message to create a new record in the target Application.
     *
     * @param EventLogMessage $eventLogMessage
     * @param EmsaMessage     $emsaMessage          Current EMSA message being processed.
     * @param int             $addCmrReason         [Optional]<br>Reason why a new record is being created instead of
     *                                              updating an existing record.  Default
     *                                              <b>MessageProcessingUtils::ADDCMR_REASON_NEW</b>.
     * @param int             $finalStatus          [Optional]<br>EMSA message queue ID the message is being assigned
     *                                              from.  Default <b>ENTRY_STATUS</b> (Entry queue).
     * @param boolean         $isAutomated          [Optional]<br>Indicates whether the message is being assigned
     *                                              manually or via EMSA automation.  Default <b>FALSE</b>.
     * @param boolean         $override             [Optional]<br>Indicates whether message assignment is being done in
     *                                              'override' mode.  Default <b>FALSE</b>.
     * @param int             $surveillanceOverride [Optional]<br>Indicates whether the surveillance status of the new
     *                                              record should be determined by rules
     *                                              (<b>MessageProcessingUtils::SURV_OVERRIDE_RULES</b>) or overridden
     *                                              using semi-automated entry
     *                                              (<b>MessageProcessingUtils::SURV_OVERRIDE_YES</b> or
     *                                              <b>MessageProcessingUtils::SURV_OVERRIDE_NO</b>).<br>Default
     *                                              <b>MessageProcessingUtils::SURV_OVERRIDE_RULES</b>.
     * @param int             $overridePersonId     [Optional]<br>If specified and <i>$override</i> is <b>TRUE</b>,
     *                                              creates new event under existing person.
     *
     * @return int ID of the Person the new record was created under in the target Application.  Returns <b>NULL</b> if
     *             add did not occur.
     * @throws Exception
     */
    final public function addCmrProcess(EventLogMessage $eventLogMessage, EmsaMessage $emsaMessage, $addCmrReason = self::ADDCMR_REASON_ADDNEW, $finalStatus = ENTRY_STATUS, $isAutomated = false, $override = false, $surveillanceOverride = self::SURV_OVERRIDE_RULES, $overridePersonId = null)
    {
        $this->clearCurrentMessageExceptions($emsaMessage->getSystemMessageId());  // clear current pending/exception messages at start of assignment process

        // filter messages that are invalid due to age
        $filterRule = $emsaMessage->getFilterRule();
        $filterRuleResult = MessageFilterRulesEngine::evaluate($filterRule, $emsaMessage->getReferenceDate());

        if (!$override) {
            // skip filtering if in override mode
            if ($filterRuleResult === MessageFilterRulesEngine::RESULT_FILTER) {
                $this->markMessageDeleted($emsaMessage->getSystemMessageId(), $finalStatus, 'Message filtered due to age of incoming encounter or specimen.');
                $eventLogMessage->setProcessedSuccessfully(true);
                $eventLogMessage->add(new EventLogNote('Message filtered due to age of incoming encounter or specimen.', 'ui-icon-elrclose'));
                return null;
            } elseif ($filterRuleResult === MessageFilterRulesEngine::RESULT_EXCEPTION) {
                $this->logMessageException($emsaMessage->getSystemMessageId(), AuditUtils::auditMessage($this->dbConn, $emsaMessage->getSystemMessageId(), SystemMessageActions::EXCEPTION, $finalStatus), Udoh\Emsa\Constants\SystemExceptions::WHITELIST_RULE_EXCEPTION, 'An error occurred while trying to run Message Filter Rules.');
                $eventLogMessage->setProcessedSuccessfully(false);
                $eventLogMessage->add(new EventLogNote('An error occurred while trying to run Message Filter Rules.', 'ui-icon-elrerror'));
                return null;
            } elseif ($filterRuleResult === MessageFilterRulesEngine::MISSING_REQUIRED_FIELDS) {
                if ($emsaMessage->getMessageType() === MessageType::CLINICAL_DOCUMENT) {
                    $this->logMessageException($emsaMessage->getSystemMessageId(), AuditUtils::auditMessage($this->dbConn, $emsaMessage->getSystemMessageId(), SystemMessageActions::EXCEPTION, $finalStatus), Udoh\Emsa\Constants\SystemExceptions::WHITELIST_RULE_EXCEPTION, 'Unable to run Message Filter Rules:  missing Encounter Date.');
                    $eventLogMessage->setProcessedSuccessfully(false);
                    $eventLogMessage->add(new EventLogNote('An error occurred while trying to run Message Filter Rules:  missing Encounter Date.', 'ui-icon-elrerror'));
                } else {
                    $this->logMessageException($emsaMessage->getSystemMessageId(), AuditUtils::auditMessage($this->dbConn, $emsaMessage->getSystemMessageId(), SystemMessageActions::EXCEPTION, $finalStatus), Udoh\Emsa\Constants\SystemExceptions::WHITELIST_RULE_EXCEPTION, 'Unable to run Message Filter Rules:  missing Collection Date.');
                    $eventLogMessage->setProcessedSuccessfully(false);
                    $eventLogMessage->add(new EventLogNote('An error occurred while trying to run Message Filter Rules:  missing Specimen Collection Date and Lab Test Date.', 'ui-icon-elrerror'));
                }

                return null;
            }
        }

        // divert messages with invalid specimen sources (skip if in override mode)
        if (($emsaMessage->validSpecimen === SPECIMEN_EXCEPTION) && !$override) {
            // Specimen source not in valid or invalid specimen sources list; send to exception
            $this->logMessageException($emsaMessage->getSystemMessageId(), AuditUtils::auditMessage($this->dbConn, $emsaMessage->getSystemMessageId(), SystemMessageActions::PENDING, $finalStatus), SystemExceptions::ENTRY_QUEUE_EXCEPTION, 'Unexpected specimen source detected.', NEDSS_EXCEPTION_STATUS);
            $eventLogMessage->setProcessedSuccessfully(false);
            $eventLogMessage->add(new EventLogNote('Could not add new event:  Condition requires specimen source validation, but an unexpected specimen source was provided.  Message moved to ' . NEDSS_EXCEPTION_NAME . ' queue.', 'ui-icon-elrerror'));
            return null;
        } elseif (($emsaMessage->validSpecimen === SPECIMEN_INVALID) && !$override) {
            // Specimen source in invalid specimen sources list; graylilst message
            if ($this->graylistMessage($emsaMessage, $finalStatus, 'Invalid specimen source.')) {
                $eventLogMessage->setProcessedSuccessfully(true);
                $eventLogMessage->add(new EventLogNote('Valid specimen source not provided.  Message Graylisted.', 'ui-icon-elrcancel'));
            } else {
                $eventLogMessage->setProcessedSuccessfully(false);
                $eventLogMessage->add(new EventLogNote('A database error occurred while attempting to graylist incoming message due to an invalid specimen source.  Please contact a system administrator.', 'ui-icon-elrerror'));
            }

            return null;
        }

        if (!empty($overridePersonId)) {
            return $this->addCmr($eventLogMessage, $emsaMessage, array($overridePersonId), $addCmrReason, $finalStatus, $isAutomated, $override, $surveillanceOverride);
        } else {
            return $this->addCmr($eventLogMessage, $emsaMessage, null, $addCmrReason, $finalStatus, $isAutomated, $override, $surveillanceOverride);
        }
    }

    /**
     * Use an EMSA message to create a new record in the target Application.
     *
     * @param EventLogMessage $eventLogMessage
     * @param EmsaMessage     $emsaMessage          EMSA message being assigned
     * @param array           $personIds            [Optional]<br>Array of matched personIds from updateCmr, in case of
     *                                              adding record to existing person.  If omitted, creates new person
     *                                              to add record to.  If multiple personIds are supplied, defers to
     *                                              Pending queue.
     * @param int             $addCmrReason         [Optional]<br>Reason why a new record is being created instead of
     *                                              updating an existing record.  Default
     *                                              <b>MessageProcessingUtils::ADDCMR_REASON_NEW</b>.
     * @param int             $finalStatus          [Optional]<br>EMSA message queue ID the message is being assigned
     *                                              from.  Default <b>ENTRY_STATUS</b> (Entry queue).
     * @param boolean         $isAutomated          [Optional]<br>Indicates whether the message is being assigned
     *                                              manually or via EMSA automation.  Default <b>FALSE</b>.
     * @param boolean         $override             [Optional]<br>Indicates whether message assignment is being done in
     *                                              'override' mode.  Default <b>FALSE</b>.
     * @param int             $surveillanceOverride [Optional]<br>Indicates whether the surveillance status of the new
     *                                              record should be determined by rules
     *                                              (<b>MessageProcessingUtils::SURV_OVERRIDE_RULES</b>) or overridden
     *                                              using semi-automated entry
     *                                              (<b>MessageProcessingUtils::SURV_OVERRIDE_YES</b> or
     *                                              <b>MessageProcessingUtils::SURV_OVERRIDE_NO</b>).<br>Default
     *                                              <b>MessageProcessingUtils::SURV_OVERRIDE_RULES</b>.
     *
     * @return int ID of the Person the new record was created under in the target Application.  Returns <b>NULL</b> if
     *             add did not occur.
     */
    final private function addCmr(EventLogMessage $eventLogMessage, EmsaMessage $emsaMessage, array $personIds = null, $addCmrReason = self::ADDCMR_REASON_ADDNEW, $finalStatus = ENTRY_STATUS, $isAutomated = false, $override = false, $surveillanceOverride = self::SURV_OVERRIDE_RULES)
    {
        switch ($addCmrReason) {
            case self::ADDCMR_REASON_NOEVENTS:
                $auditMsgNamechange = 'No matching events found in selected people, but could not add new event:  Patient last name may have changed.  Message moved to Pending list.';
                $auditMsgValid = 'No matching events found in selected people, adding new event';
                $highlightMsg = 'No matching events found in selected people, adding new event...';
                break;
            case self::ADDCMR_REASON_NOWHITELIST:
                $auditMsgNamechange = 'None of the matched events met whitelist conditions, but could not add new event:  Patient last name may have changed.  Message moved to Pending list.';
                $auditMsgValid = 'None of the matched events met whitelist conditions, adding new event';
                $highlightMsg = 'None of the matched events met whitelist conditions, adding new event...';
                break;
            case self::ADDCMR_REASON_NOTACASE:
                $auditMsgNamechange = 'Matching event marked "Not a Case", but could not add new event:  Patient last name may have changed.  Message moved to Pending list.';
                $auditMsgValid = 'Matching event marked "Not a Case", adding new event';
                $highlightMsg = 'Matching event marked "Not a Case", adding new event...';
                break;
            case self::ADDCMR_REASON_ONETOMANY:
                $auditMsgNamechange = 'One-to-Many rules indicate a new Morbidity event is needed, but could not add new event:  Patient last name may have changed.  Message moved to Pending list.';
                $auditMsgValid = 'One-to-Many rules indicate a new Morbidity event is needed; adding new event...';
                $highlightMsg = 'One-to-Many rules indicate a new Morbidity event is needed; adding new event...';
                break;
            default:
                $auditMsgNamechange = 'Could not add new event:  Patient last name may have changed.  Message moved to Pending list.';
                $auditMsgValid = '[Add New Person & CMR] option selected';
                $highlightMsg = 'Attempting to create a new event...';
                break;
        }

        $systemMessageId = $emsaMessage->getSystemMessageId();
        $eventLogMessage->add(new EventLogNote($highlightMsg, 'ui-icon-elradd'));
        AuditUtils::auditMessage($this->dbConn, $systemMessageId, SystemMessageActions::ATTEMPTED_NEW_CMR, $finalStatus, $auditMsgValid);


        // check for semi-automated entry override of surveillance status
        if ($surveillanceOverride === self::SURV_OVERRIDE_NO) {
            $emsaMessage->isSurveillance = CaseManagementRulesEngine::CMR_NO;
        } elseif ($surveillanceOverride === self::SURV_OVERRIDE_YES) {
            $emsaMessage->isSurveillance = CaseManagementRulesEngine::CMR_YES;
        }

        if ($emsaMessage->isSurveillance === CaseManagementRulesEngine::CMR_ERR_NO_RULES_DEFINED_LOINC) {
            $this->logMessageException(
                $systemMessageId, AuditUtils::auditMessage($this->dbConn, $systemMessageId, SystemMessageActions::EXCEPTION, $finalStatus), SystemExceptions::NO_CASE_MANAGEMENT_RULES_FOUND_MASTER_LOINC, '[isSurveillance] ' . $emsaMessage->getLoincForCaseManagementRules()
            );
            $eventLogMessage->add(new EventLogNote('Could not add new event:  No Case Management rules defined for Master LOINC.  Message moved to Exception list.', 'ui-icon-elrerror'));
        } elseif ($emsaMessage->isSurveillance === CaseManagementRulesEngine::CMR_ERR_NO_RULES_DEFINED_SNOMED) {
            $this->logMessageException(
                $systemMessageId, AuditUtils::auditMessage($this->dbConn, $systemMessageId, SystemMessageActions::EXCEPTION, $finalStatus), SystemExceptions::NO_CASE_MANAGEMENT_RULES_FOUND_MASTER_SNOMED, '[isSurveillance] ' . $emsaMessage->masterOrganism
            );
            $eventLogMessage->add(new EventLogNote('Could not add new event:  No Case Management rules defined for Master Organism.  Message moved to Exception list.', 'ui-icon-elrerror'));
        } elseif ($emsaMessage->isSurveillance === CaseManagementRulesEngine::CMR_ERR_NO_RULES_DEFINED_DXCODE) {
            $this->logMessageException(
                $systemMessageId, AuditUtils::auditMessage($this->dbConn, $systemMessageId, SystemMessageActions::EXCEPTION, $finalStatus), SystemExceptions::NO_CASE_MANAGEMENT_RULES_FOUND_ICD_CODE, '[isSurveillance] ' . $emsaMessage->diagnosticCode . ' (' . $emsaMessage->diagnosticCodingSystem . ')'
            );
            $eventLogMessage->add(new EventLogNote('Could not add new event:  No Case Management rules defined for Diagnostic Code.  Message moved to Exception list.', 'ui-icon-elrerror'));
        } elseif ($emsaMessage->isSurveillance === CaseManagementRulesEngine::CMR_ERR_NO_RULES_MATCHED) {
            $this->logMessageException(
                $systemMessageId, AuditUtils::auditMessage($this->dbConn, $systemMessageId, SystemMessageActions::EXCEPTION, $finalStatus), SystemExceptions::NO_CASE_MANAGEMENT_RULES_TRUE, '[isSurveillance] ' . $emsaMessage->getLoincForCaseManagementRules() . '/' . $emsaMessage->masterOrganism . ' [' . $emsaMessage->getTestResultForCaseManagementRules() . ']'
            );
            $eventLogMessage->add(new EventLogNote('Could not add new event:  No Case Management rules evaluated true.  Message moved to Exception list.', 'ui-icon-elrerror'));
        } elseif ($emsaMessage->isSurveillance === CaseManagementRulesEngine::CMR_ERR_MULT_RULES_MATCHED) {
            $this->logMessageException(
                $systemMessageId, AuditUtils::auditMessage($this->dbConn, $systemMessageId, SystemMessageActions::EXCEPTION, $finalStatus), SystemExceptions::UNABLE_TO_EVALUATE_RULE, '[isSurveillance] Multiple rules evaluated true; ' . $emsaMessage->getLoincForCaseManagementRules() . '/' . $emsaMessage->masterOrganism . ' [' . $emsaMessage->getTestResultForCaseManagementRules() . ']'
            );
            $eventLogMessage->add(new EventLogNote('Could not add new event:  Multiple Case Management rules evaluated true.  Message moved to Exception list.', 'ui-icon-elrerror'));
        } elseif ($emsaMessage->isSurveillance === CaseManagementRulesEngine::CMR_ERR_REQFIELD_LOINC) {
            $this->logMessageException(
                $systemMessageId, AuditUtils::auditMessage($this->dbConn, $systemMessageId, SystemMessageActions::EXCEPTION, $finalStatus), SystemExceptions::UNABLE_TO_EVALUATE_RULE, '[isSurveillance] Missing required field (Master LOINC)'
            );
            $eventLogMessage->add(new EventLogNote('Could not add new event:  Unable to determine surveillance event status due to missing master LOINC code.  Message moved to Exception list.', 'ui-icon-elrerror'));
        } elseif ($emsaMessage->isSurveillance === CaseManagementRulesEngine::CMR_ERR_REQFIELD_DXCODE) {
            $this->logMessageException(
                $systemMessageId, AuditUtils::auditMessage($this->dbConn, $systemMessageId, SystemMessageActions::EXCEPTION, $finalStatus), SystemExceptions::UNABLE_TO_EVALUATE_RULE, '[isSurveillance] Missing required field (Diagnostic Code)'
            );
            $eventLogMessage->add(new EventLogNote('Could not add new event:  Unable to determine surveillance event status due to missing Diagnostic Code.  Message moved to Exception list.', 'ui-icon-elrerror'));
        } elseif ($emsaMessage->isSurveillance === CaseManagementRulesEngine::CMR_ERR_REQFIELD_TESTRESULT) {
            $this->logMessageException(
                $systemMessageId, AuditUtils::auditMessage($this->dbConn, $systemMessageId, SystemMessageActions::EXCEPTION, $finalStatus), SystemExceptions::UNABLE_TO_EVALUATE_RULE, '[isSurveillance] Missing required field (Test Result)'
            );
            $eventLogMessage->add(new EventLogNote('Could not add new event:  Unable to determine surveillance event status due to missing test result.  Message moved to Exception list.', 'ui-icon-elrerror'));
        }

        // determine what type of add we're performing and prepare XML accordingly
        $suppliedPersonIds = 0;
        $targetPerson = null;
        $triggerException = false;

        if (!empty($personIds) && is_array($personIds)) {
            $suppliedPersonIds = count($personIds);
        }

        if ($suppliedPersonIds === 0) {
            // no personId specified, create new person and record
            $finalAddRecordXML = clone $emsaMessage->getApplicationXML();
            $addCmrAction = SystemMessageActions::MESSAGE_ASSIGNED_NEW_CMR_NEW_PERSON;
        } elseif ($suppliedPersonIds === 1) {
            // exactly one personId supplied, add new record to existing person
            $targetPerson = $emsaMessage->getAppClient()->getPerson((int)reset($personIds));
            $finalAddRecordXML = new SimpleXMLElement('<?xml version="1.0" encoding="UTF-8" standalone="yes"?><nedssHealth/>');
            $finalAddRecordXML->addChild('person')->addChild('id', (string)$targetPerson->getPersonId());

            EmsaMessageXmlUtils::appendXMLNode($finalAddRecordXML->person, $emsaMessage->getApplicationXML()->person->personCondition);
            $addCmrAction = SystemMessageActions::MESSAGE_ASSIGNED_NEW_CMR_EXISTING_PERSON;
        } else {
            if (MULTIPERSON_ADDCMR_PENDING_BYPASS === true) {
                // more than one person found... determine which person to add the new record to
                $foundPeople = new PersonList();

                // find events from matched people
                $personIdsStr = implode(', ', $personIds);
                AuditUtils::auditMessage($this->dbConn, $systemMessageId, SystemMessageActions::MESSAGE_AUTOPROCESSED, $finalStatus, "Picking a person to use for new event from matched people");
                $appClientName = $emsaMessage->getAppClient()->getAppName();
                foreach ($personIds as $personId) {
                    try {
                        $foundPeople->add($emsaMessage->getAppClient()->getPerson($personId));
                    } catch (Throwable $e) {
                        ExceptionUtils::logException($e);
                        $this->logMessageException(
                            $systemMessageId, AuditUtils::auditMessage($this->dbConn, $systemMessageId, SystemMessageActions::EXCEPTION, $finalStatus), SystemExceptions::UNABLE_TO_ASSIGN_XML_TARGET_APPLICATION, "[getPerson] Unable to execute $appClientName web service function:  " . $e->getMessage()
                        );
                        $eventLogMessage->add(new EventLogNote('Message moved to Exception list.', 'ui-icon-elrerror', $e->getMessage()));
                        return false;
                    }
                }

                $targetPersonId = null;
                $maxEventCount = 0;
                $targetPersonCount = 0;

                // check for person with the most Morbidity record types
                /** @var Person $foundPerson */
                foreach ($foundPeople as $foundPerson) {
                    if ($foundPerson->getRecordList()->countByRecordType(AppRecordType::MORBIDITY_EVENT) > 0) {
                        if ($foundPerson->getRecordList()->countByRecordType(AppRecordType::MORBIDITY_EVENT) > $maxEventCount) {
                            $maxEventCount = $foundPerson->getRecordList()->countByRecordType(AppRecordType::MORBIDITY_EVENT);
                            $targetPersonId = $foundPerson->getPersonId();
                            $targetPersonCount = 1;
                        } elseif ($foundPerson->getRecordList()->countByRecordType(AppRecordType::MORBIDITY_EVENT) === $maxEventCount) {
                            $targetPersonCount++;
                        }
                    }
                }

                // if no single person found yet, check for person with most overall records of any type
                if ($targetPersonCount !== 1) {
                    $targetPersonId = null;
                    $maxEventCount = 0;
                    $targetPersonCount = 0;

                    /** @var Person $foundPerson2 */
                    foreach ($foundPeople as $foundPerson2) {
                        if (count($foundPerson2->getRecordList()) > 0) {
                            if (count($foundPerson2->getRecordList()) > $maxEventCount) {
                                $maxEventCount = count($foundPerson2->getRecordList());
                                $targetPersonId = $foundPerson2->getPersonId();
                                $targetPersonCount = 1;
                            } elseif (count($foundPerson2->getRecordList()) === $maxEventCount) {
                                $targetPersonCount++;
                            }
                        }
                    }
                }

                // if STILL no single person found, use the newest person (biggest ID) from the list
                if ($targetPersonCount !== 1) {
                    rsort($personIds, SORT_NUMERIC);
                    $targetPersonId = reset($personIds);
                }

                $targetPerson = $foundPeople->get($targetPersonId);
                $finalAddRecordXML = new SimpleXMLElement('<?xml version="1.0" encoding="UTF-8" standalone="yes"?><nedssHealth/>');
                $finalAddRecordXML->addChild('person')->addChild('id', (string)$targetPerson->getPersonId());

                EmsaMessageXmlUtils::appendXMLNode($finalAddRecordXML->person, $emsaMessage->getApplicationXML()->person->personCondition);
                $addCmrAction = SystemMessageActions::MESSAGE_ASSIGNED_NEW_CMR_EXISTING_PERSON;
                AuditUtils::auditMessage($this->dbConn, $systemMessageId, SystemMessageActions::MESSAGE_AUTOPROCESSED, $finalStatus, "Pending automation resolved multiple people selection automatically");
            } else {
                // more than one person found... move to 'Pending' for resolution
                $finalAddRecordXML = null;
                $addCmrAction = null;
                $triggerException = true;
            }
        }

        if (!$triggerException) {
            EmsaMessageXmlUtils::addCmrCleanupTasks($this->dbConn, $emsaMessage, $finalAddRecordXML, $targetPerson, $this->isMessageInteragencyTx($emsaMessage->getOriginalMessageId()));
        }

        // check for last name change; exclude if in 'override' mode
        $lastNameChangedCheck = [];
        if (!$override && ($addCmrAction === SystemMessageActions::MESSAGE_ASSIGNED_NEW_CMR_NEW_PERSON)) {
            $lastNameChangedCheck = AppClientUtils::hasLastNameChanged($this->dbConn, $emsaMessage);
        }

        if ($emsaMessage->allowNewCmr == CaseManagementRulesEngine::CMR_YES) {
            if (($emsaMessage->isSurveillance === CaseManagementRulesEngine::CMR_YES) || ($emsaMessage->isSurveillance === CaseManagementRulesEngine::CMR_NO)) {
                // decided not to do person-with-no-event matches for now; causing too many false-positives due to Contacts
                //if (array_key_exists('personIds', $lastNameChangedCheck) && array_key_exists('eventIds', $lastNameChangedCheck) && ((count($lastNameChangedCheck['personIds']) > 0) || (count($lastNameChangedCheck['eventIds']) > 0))) {
                if (array_key_exists('eventIds', $lastNameChangedCheck) && (count($lastNameChangedCheck['eventIds']) > 0)) {
                    $lastNameChangedAuditMessage = '';
                    /*
                    if (count($lastNameChangedCheck['personIds']) > 0) {
                        $lastNameChangedAuditMessage .= 'Matched People:<br>' . EmsaCompareUtils::appLinkByPersonIDArray($emsaMessage->getAppClient(), $lastNameChangedCheck['personIds']);
                    }
                    */
                    if (count($lastNameChangedCheck['eventIds']) > 0) {
                        $lastNameChangedAuditMessage .= 'Matched Record Numbers:<br>' . EmsaCompareUtils::nedssLinkByEventArray($emsaMessage->getAppClient(), $lastNameChangedCheck['eventIds']);
                    }

                    $this->logMessageException(
                        $systemMessageId, AuditUtils::auditMessage($this->dbConn, $systemMessageId, SystemMessageActions::PENDING, $finalStatus), SystemExceptions::PATIENT_LAST_NAME_CHANGED_MARRIAGE, $lastNameChangedAuditMessage, NEDSS_EXCEPTION_STATUS
                    );

                    $eventLogMessage->add(new EventLogNote($auditMsgNamechange, 'ui-icon-elrerror'));
                } else {
                    // Add incoming ELR message as new record in the target application

                    $isInterstateTx = false;
                    if ($emsaMessage->getMessageType() === MessageType::ELR_MESSAGE && ($emsaMessage->getFinalStatus() !== OOS_STATUS)) {
                        if (!empty($emsaMessage->getMasterXML()->person->state) && (strtoupper(trim($emsaMessage->getMasterXML()->person->state)) != strtoupper(HOME_STATE_ABBR))) {
                            // if ELR, not being assigned -from- the OOS queue, and has an OOS State code, check if State code is participating in Interstate Transmission
                            $isInterstateTx = $this->isMessageInteragencyTx($emsaMessage->getOriginalMessageId());
                        }
                    }

                    if ($isInterstateTx) {
                        // if message has already been transmitted to the appropriate state via Interstate ELR Transmission, but bypasses the OOS queue,
                        // add a note indicating that the message has been transmitted to the state of residence prior to assigning
                        AuditUtils::auditMessage($this->dbConn, $emsaMessage->getSystemMessageId(), SystemMessageActions::MESSAGE_AUTOPROCESSED, $finalStatus, 'Original message is from an Out of State patient.  Original message has also been transmitted to state of residence via Interstate Transmission.');
                        $eventLogMessage->add(new EventLogNote('Original message is from an Out of State patient.  Original message has also been transmitted to state of residence via Interstate Transmission.'));
                    }

                    if (($emsaMessage->getSource()->getJurisdiction() == 'Out of State') && ($emsaMessage->getFinalStatus() !== OOS_STATUS) && ($emsaMessage->getBypassOOS() === false)) {
                        // if message is OOS, not being processed out of the OOS queue, AND this condition doesn't specify to bypass the OOS queue, move it to the OOS queue instead of processing it
                        try {
                            $this->copyMessageToQueue($emsaMessage->getSystemMessageId(), GRAY_STATUS, 'Original message was Out of State.  Graylisting copy of message for possible future use.');

                            if ($isInterstateTx) {
                                // message is ELR & OOS, but has already been transmitted to the appropriate state via Interstate ELR Transmission
                                $this->setQaFlag($emsaMessage->getSystemMessageId(), OOS_STATUS, EMSA_FLAG_INVESTIGATION_COMPLETE);
                                $this->moveMessageToQueue($emsaMessage->getSystemMessageId(), $finalStatus, OOS_STATUS, SystemMessageActions::MESSAGE_MOVED_BY_WHITELIST_RULE, 'Message is from Out of State.  Moved to Out of State queue and marked as Investigation Complete.');
                                $eventLogMessage->setProcessedSuccessfully(true);
                                $eventLogMessage->add(new EventLogNote('Message is from Out of State.  Moved to Out of State queue and marked as Investigation Complete.'));
                            } else {
                                // message is for a state not participating in Interstate Transmission
                                $this->moveMessageToQueue($emsaMessage->getSystemMessageId(), $finalStatus, OOS_STATUS, SystemMessageActions::MESSAGE_MOVED_BY_WHITELIST_RULE, 'Message is from Out of State.  Moved to Out of State queue.');
                                $eventLogMessage->setProcessedSuccessfully(true);
                                $eventLogMessage->add(new EventLogNote('Message is from Out of State.  Moved to Out of State queue.'));
                            }
                        } catch (Throwable $e) {
                            ExceptionUtils::logException($e);
                            $eventLogMessage->add(new EventLogNote("Error occurred while attempting to move message to the 'Out of State' queue", 'ui-icon-elrerror', $e->getMessage()));
                        }

                        return null;
                    }

                    if ($triggerException) {
                        // more than one person found... move to 'Pending' for resolution
                        $this->logMessageException(
                            $systemMessageId, AuditUtils::auditMessage($this->dbConn, $systemMessageId, SystemMessageActions::PENDING, $finalStatus), SystemExceptions::ENTRY_QUEUE_EXCEPTION, 'Attempted to create a new Morbidity Event for an existing person, but multiple people were selected.  Please identify and select a single person and try again.', NEDSS_EXCEPTION_STATUS
                        );
                        $eventLogMessage->add(new EventLogNote('Attempted to create a new Morbidity Event for an existing person after no existing events were found to update, but multiple people were selected.  Message moved to Pending list.', 'ui-icon-elrerror'));
                        return null;
                    }

                    try {
                        $addCmrReturn = $emsaMessage->getAppClient()->addRecord($finalAddRecordXML);
                    } catch (Throwable $e) {
                        ExceptionUtils::logException($e);
                        $auditId = AuditUtils::auditMessage($this->dbConn, $systemMessageId, SystemMessageActions::EXCEPTION, $finalStatus);
                        AuditUtils::auditXML($this->dbConn, null, $finalAddRecordXML, $auditId, false);
                        $this->logMessageException(
                            $systemMessageId, $auditId, SystemExceptions::UNABLE_TO_ASSIGN_XML_TARGET_APPLICATION, '[addCmr] ' . $e->getMessage()
                        );
                        $eventLogMessage->add(new EventLogNote('Message moved to Exception list.', 'ui-icon-elrerror', $e->getMessage()));
                        return null;
                    }

                    return $this->processAssignmentReturn($eventLogMessage, $emsaMessage, $finalAddRecordXML, $addCmrReturn, $addCmrAction, null, null, null, false, $isAutomated, $finalStatus);
                }
            }
        } elseif (($emsaMessage->getMessageType() === MessageType::ELR_MESSAGE) && ($emsaMessage->allowNewCmr == CaseManagementRulesEngine::CMR_NO)) {
            // Test type & result not allowed to create a new CMR; graylist incoming ELR message
            if (array_key_exists('eventIds', $lastNameChangedCheck) && (count($lastNameChangedCheck['eventIds']) > 0)) {
                $lastNameChangedAuditMessage = 'Current test results not allowed to create a new CMR according to Case Management Rules.  Attempted to move message to Gray list, however, a possible patient last name change was detected and was held for review.<br>';
                /*
                if (count($lastNameChangedCheck['personIds']) > 0) {
                    $lastNameChangedAuditMessage .= 'Matched People:<br>' . EmsaCompareUtils::appLinkByPersonIDArray($emsaMessage->getAppClient(), $lastNameChangedCheck['personIds']);
                }
                */
                if (count($lastNameChangedCheck['eventIds']) > 0) {
                    $lastNameChangedAuditMessage .= 'Matched Record Numbers:<br>' . EmsaCompareUtils::nedssLinkByEventArray($emsaMessage->getAppClient(), $lastNameChangedCheck['eventIds']);
                }

                $this->logMessageException(
                    $systemMessageId, AuditUtils::auditMessage($this->dbConn, $systemMessageId, SystemMessageActions::PENDING, $finalStatus), SystemExceptions::PATIENT_LAST_NAME_CHANGED_MARRIAGE, $lastNameChangedAuditMessage, NEDSS_EXCEPTION_STATUS
                );

                $eventLogMessage->add(new EventLogNote($auditMsgNamechange, 'ui-icon-elrerror'));
            } else {
                try {
                    if ($this->graylistMessage($emsaMessage, $finalStatus, 'Case Management Rules prohibit creating a new CMR for this test result.')) {
                        $eventLogMessage->setProcessedSuccessfully(true);
                        $eventLogMessage->add(new EventLogNote('Current test results not allowed to create a new CMR according to Case Management Rules.  Message moved to Gray list...', 'ui-icon-elrcancel'));
                    } else {
                        $eventLogMessage->add(new EventLogNote('A database error occurred while attempting to graylist incoming message.  Please contact a system administrator.', 'ui-icon-elrerror'));
                    }
                } catch (Throwable $e) {
                    ExceptionUtils::logException($e);
                    $eventLogMessage->add(new EventLogNote('Unexpected error occurred while attempting to graylist message.', 'ui-icon-elrerror', $e->getMessage()));
                }
            }
        } elseif (($emsaMessage->getMessageType() === MessageType::CLINICAL_DOCUMENT) && ($emsaMessage->allowNewCmr == CaseManagementRulesEngine::CMR_NO)) {
            // CCDAs not allowed to create new CMRs; graylist incoming message
            if (array_key_exists('eventIds', $lastNameChangedCheck) && (count($lastNameChangedCheck['eventIds']) > 0)) {
                $lastNameChangedAuditMessage = 'Current Clinical Document not allowed to create a new event according to Case Management Rules.  Attempted to move message to Gray list, however, a possible patient last name change was detected and was held for review.<br>';
                /*
                if (count($lastNameChangedCheck['personIds']) > 0) {
                    $lastNameChangedAuditMessage .= 'Matched People:<br>' . EmsaCompareUtils::appLinkByPersonIDArray($emsaMessage->getAppClient(), $lastNameChangedCheck['personIds']);
                }
                */
                if (count($lastNameChangedCheck['eventIds']) > 0) {
                    $lastNameChangedAuditMessage .= 'Matched Record Numbers:<br>' . EmsaCompareUtils::nedssLinkByEventArray($emsaMessage->getAppClient(), $lastNameChangedCheck['eventIds']);
                }

                $this->logMessageException(
                    $systemMessageId, AuditUtils::auditMessage($this->dbConn, $systemMessageId, SystemMessageActions::PENDING, $finalStatus), SystemExceptions::PATIENT_LAST_NAME_CHANGED_MARRIAGE, $lastNameChangedAuditMessage, NEDSS_EXCEPTION_STATUS
                );

                $eventLogMessage->add(new EventLogNote($auditMsgNamechange, 'ui-icon-elrerror'));
            } else {
                try {
                    if ($this->graylistMessage($emsaMessage, $finalStatus, 'Case Management Rules prohibit creating a new event based on this Clinical Document.')) {
                        $eventLogMessage->setProcessedSuccessfully(true);
                        $eventLogMessage->add(new EventLogNote('Current Clinical Document not allowed to create a new event according to Case Management Rules.  Message moved to Gray list...', 'ui-icon-elrcancel'));
                    } else {
                        $eventLogMessage->add(new EventLogNote('A database error occurred while attempting to graylist incoming message.  Please contact a system administrator.', 'ui-icon-elrerror'));
                    }
                } catch (Throwable $e) {
                    ExceptionUtils::logException($e);
                    $eventLogMessage->add(new EventLogNote('Unexpected error occurred while attempting to graylist message.', 'ui-icon-elrerror', $e->getMessage()));
                }
            }
        } elseif ($emsaMessage->allowNewCmr == CaseManagementRulesEngine::CMR_ERR_REQFIELD_LOINC) {
            $this->logMessageException(
                $systemMessageId, AuditUtils::auditMessage($this->dbConn, $systemMessageId, SystemMessageActions::EXCEPTION, $finalStatus), SystemExceptions::UNABLE_TO_EVALUATE_RULE, '[allowNewCMR] Unable to run Case Management rules due to missing Master LOINC Code.'
            );
            $eventLogMessage->add(new EventLogNote('Could not add new event:  Unable to run Case Management rules due to missing Master LOINC Code.  Message moved to Exception list.', 'ui-icon-elrerror'));
        } elseif ($emsaMessage->allowNewCmr == CaseManagementRulesEngine::CMR_ERR_REQFIELD_DXCODE) {
            $this->logMessageException(
                $systemMessageId, AuditUtils::auditMessage($this->dbConn, $systemMessageId, SystemMessageActions::EXCEPTION, $finalStatus), SystemExceptions::UNABLE_TO_EVALUATE_RULE, '[allowNewCMR] Unable to run Case Management rules due to missing Diagnostic Code.'
            );
            $eventLogMessage->add(new EventLogNote('Could not add new event:  Unable to run Case Management rules due to missing Diagnostic Code.  Message moved to Exception list.', 'ui-icon-elrerror'));
        } elseif ($emsaMessage->allowNewCmr == CaseManagementRulesEngine::CMR_ERR_REQFIELD_TESTRESULT) {
            $this->logMessageException(
                $systemMessageId, AuditUtils::auditMessage($this->dbConn, $systemMessageId, SystemMessageActions::EXCEPTION, $finalStatus), SystemExceptions::UNABLE_TO_EVALUATE_RULE, '[allowNewCMR] Unable to run Case Management rules due to missing Test Result.'
            );
            $eventLogMessage->add(new EventLogNote('Could not add new event:  Unable to run Case Management rules due to missing Test Result.  Message moved to Exception list.', 'ui-icon-elrerror'));
        } elseif ($emsaMessage->allowNewCmr == CaseManagementRulesEngine::CMR_ERR_NO_RULES_DEFINED_LOINC) {
            $this->logMessageException(
                $systemMessageId, AuditUtils::auditMessage($this->dbConn, $systemMessageId, SystemMessageActions::EXCEPTION, $finalStatus), SystemExceptions::NO_CASE_MANAGEMENT_RULES_FOUND_MASTER_LOINC, '[allowNewCMR] ' . $emsaMessage->getLoincForCaseManagementRules()
            );
            $eventLogMessage->add(new EventLogNote('Could not add new event:  No Case Management rules defined for Master LOINC.  Message moved to Exception list.', 'ui-icon-elrerror'));
        } elseif ($emsaMessage->allowNewCmr == CaseManagementRulesEngine::CMR_ERR_NO_RULES_DEFINED_SNOMED) {
            $this->logMessageException(
                $systemMessageId, AuditUtils::auditMessage($this->dbConn, $systemMessageId, SystemMessageActions::EXCEPTION, $finalStatus), SystemExceptions::NO_CASE_MANAGEMENT_RULES_FOUND_MASTER_SNOMED, '[allowNewCMR] ' . $emsaMessage->masterOrganism
            );
            $eventLogMessage->add(new EventLogNote('Could not add new event:  No Case Management rules defined for Diagnostic Code.  Message moved to Exception list.', 'ui-icon-elrerror'));
        } elseif ($emsaMessage->allowNewCmr == CaseManagementRulesEngine::CMR_ERR_NO_RULES_DEFINED_DXCODE) {
            $this->logMessageException(
                $systemMessageId, AuditUtils::auditMessage($this->dbConn, $systemMessageId, SystemMessageActions::EXCEPTION, $finalStatus), SystemExceptions::NO_CASE_MANAGEMENT_RULES_FOUND_ICD_CODE, '[allowNewCMR] ' . $emsaMessage->diagnosticCode . ' (' . $emsaMessage->diagnosticCodingSystem . ')'
            );
            $eventLogMessage->add(new EventLogNote('Could not add new event:  No Case Management rules defined for Master Organism.  Message moved to Exception list.', 'ui-icon-elrerror'));
        } elseif ($emsaMessage->allowNewCmr == CaseManagementRulesEngine::CMR_ERR_NO_RULES_MATCHED) {
            $this->logMessageException(
                $systemMessageId, AuditUtils::auditMessage($this->dbConn, $systemMessageId, SystemMessageActions::EXCEPTION, $finalStatus), SystemExceptions::NO_CASE_MANAGEMENT_RULES_TRUE, '[allowNewCMR] ' . $emsaMessage->getLoincForCaseManagementRules() . '/' . $emsaMessage->masterOrganism . ' [' . $emsaMessage->getTestResultForCaseManagementRules() . ']'
            );
            $eventLogMessage->add(new EventLogNote('Could not add new event:  No Case Management rules evaluated true.  Message moved to Exception list.', 'ui-icon-elrerror'));
        } elseif ($emsaMessage->allowNewCmr == CaseManagementRulesEngine::CMR_ERR_MULT_RULES_MATCHED) {
            $this->logMessageException(
                $systemMessageId, AuditUtils::auditMessage($this->dbConn, $systemMessageId, SystemMessageActions::EXCEPTION, $finalStatus), SystemExceptions::UNABLE_TO_EVALUATE_RULE, '[allowNewCMR] Multiple rules evaluated true; ' . $emsaMessage->getLoincForCaseManagementRules() . '/' . $emsaMessage->masterOrganism . ' [' . $emsaMessage->getTestResultForCaseManagementRules() . ']'
            );
            $eventLogMessage->add(new EventLogNote('Could not add new event:  Multiple Case Management rules evaluated true.  Message moved to Exception list.', 'ui-icon-elrerror'));
        }

        return null;
    }

    /**
     * Attempt to update an event in NEDSS with an ELR message from EMSA.
     *
     * Searches NEDSS in an attempt to identify an existing Morbidity or Contact event to append the new ELR results
     * to.
     * If unable to locate an event to update, will create a new CMR.
     *
     * @param EventLogMessage $eventLogMessage
     * @param EmsaMessage     $emsaMessage          Current EMSA message being processed.
     * @param string          $matchedPersonIds     Pipe-delimited list of Person IDs to search within to locate an
     *                                              updatable event.
     * @param int             $finalStatus          [Optional]<br>EMSA message queue ID the message is being assigned
     *                                              from.  Default <b>ENTRY_STATUS</b> (Entry queue).
     * @param boolean         $isAutomated          [Optional]<br>Indicates whether the message is being assigned
     *                                              manually or via EMSA automation.  Default <b>FALSE</b>.
     * @param boolean         $override             [Optional]<br>Indicates whether message assignment is being done in
     *                                              'override' mode.  Default <b>FALSE</b>.
     * @param int             $surveillanceOverride [Optional]<br>Indicates whether the surveillance status of the new
     *                                              event should be determined by rules
     *                                              (<b>MessageProcessingUtils::SURV_OVERRIDE_RULES</b>) or overridden
     *                                              using semi-automated entry
     *                                              (<b>MessageProcessingUtils::SURV_OVERRIDE_YES</b> or
     *                                              <b>MessageProcessingUtils::SURV_OVERRIDE_NO</b>).<br>Default
     *                                              <b>MessageProcessingUtils::SURV_OVERRIDE_RULES</b>.
     * @param int             $overrideEventId      [Optional] (Required if <i>override</i> is
     *                                              <b>TRUE</b>)<br>Specifies the target NEDSS Event ID to force-append
     *                                              this ELR message to.
     *
     * @return boolean Returns <b>TRUE</b> if message successfully processed, <b>FALSE</b> if message cannot be found
     *                 or no matching persons are supplied.
     */
    final public function updateCmrProcess(EventLogMessage $eventLogMessage, EmsaMessage $emsaMessage, $matchedPersonIds = null, $finalStatus = ENTRY_STATUS, $isAutomated = false, $override = false, $surveillanceOverride = self::SURV_OVERRIDE_RULES, $overrideEventId = null)
    {
        $systemMessageId = $emsaMessage->getSystemMessageId();
        $appClient = $emsaMessage->getAppClient();
        $appClientName = $appClient->getAppName();

        $personSearchIds = explode('|', $matchedPersonIds);

        $this->clearCurrentMessageExceptions($systemMessageId);  // clear current pending/exception messages at start of assignment process

        // filter messages that are invalid due to age
        if (!$override) {
            // skip filtering if in override mode
            $filterRule = $emsaMessage->getFilterRule();
            $filterRuleResult = MessageFilterRulesEngine::evaluate($filterRule, $emsaMessage->getReferenceDate());

            if ($filterRuleResult === MessageFilterRulesEngine::RESULT_FILTER) {
                $this->markMessageDeleted($systemMessageId, $finalStatus, 'Message filtered due to age of incoming encounter or specimen.');
                $eventLogMessage->setProcessedSuccessfully(true);
                $eventLogMessage->add(new EventLogNote('Message filtered due to age of incoming encounter or specimen.', 'ui-icon-elrclose'));
                return true;
            } elseif ($filterRuleResult === MessageFilterRulesEngine::RESULT_EXCEPTION) {
                $this->logMessageException($systemMessageId, AuditUtils::auditMessage($this->dbConn, $systemMessageId, SystemMessageActions::EXCEPTION, $finalStatus), SystemExceptions::WHITELIST_RULE_EXCEPTION, 'An error occurred while trying to run Message Filter Rules.');
                $eventLogMessage->add(new EventLogNote('An error occurred while trying to run Message Filter Rules.', 'ui-icon-elrerror'));
                return false;
            } elseif ($filterRuleResult === MessageFilterRulesEngine::MISSING_REQUIRED_FIELDS) {
                if ($emsaMessage->getMessageType() === MessageType::CLINICAL_DOCUMENT) {
                    $this->logMessageException($systemMessageId, AuditUtils::auditMessage($this->dbConn, $systemMessageId, SystemMessageActions::EXCEPTION, $finalStatus), SystemExceptions::WHITELIST_RULE_EXCEPTION, 'Unable to run Message Filter Rules:  missing Encounter Date.');
                    $eventLogMessage->add(new EventLogNote('An error occurred while trying to run Message Filter Rules:  missing Encounter Date.', 'ui-icon-elrerror'));
                } else {
                    $this->logMessageException($systemMessageId, AuditUtils::auditMessage($this->dbConn, $systemMessageId, SystemMessageActions::EXCEPTION, $finalStatus), SystemExceptions::WHITELIST_RULE_EXCEPTION, 'Unable to run Message Filter Rules:  missing Collection Date.');
                    $eventLogMessage->add(new EventLogNote('An error occurred while trying to run Message Filter Rules:  missing Specimen Collection Date and Lab Test Date.', 'ui-icon-elrerror'));
                }

                return false;
            }
        }

        // divert messages with invalid specimen sources
        if (($emsaMessage->validSpecimen === SPECIMEN_EXCEPTION) && !$override) {
            // Specimen source not in valid or invalid specimen sources list; send to exception
            $this->logMessageException(
                $systemMessageId, AuditUtils::auditMessage($this->dbConn, $systemMessageId, SystemMessageActions::PENDING, $finalStatus), SystemExceptions::ENTRY_QUEUE_EXCEPTION, 'Unexpected specimen source detected.', NEDSS_EXCEPTION_STATUS
            );
            $eventLogMessage->add(new EventLogNote('Could not update event:  Condition requires specimen source validation, but an unexpected specimen source was provided.  Message moved to Pending list.', 'ui-icon-elrerror'));
            return false;
        } elseif (($emsaMessage->validSpecimen === SPECIMEN_INVALID) && !$override) {
            // specimen source in invalid specimen sources list; graylilst message
            try {
                if ($this->graylistMessage($emsaMessage, $finalStatus, 'Invalid specimen source.')) {
                    $eventLogMessage->setProcessedSuccessfully(true);
                    $eventLogMessage->add(new EventLogNote('Valid specimen source not provided.  Message moved to Gray list...', 'ui-icon-elrcancel'));
                } else {
                    $eventLogMessage->add(new EventLogNote('A database error occurred while attempting to graylist incoming message.  Please contact a system administrator.', 'ui-icon-elrerror'));
                }
            } catch (Throwable $e) {
                ExceptionUtils::logException($e);
                $eventLogMessage->add(new EventLogNote('An unexpected error occurred while attempting to graylist incoming message.  Please contact a system administrator.', 'ui-icon-elrerror', $e->getMessage()));
            }

            return false;
        }

        if ($override) {
            // in 'override' mode... no need to do people search,
            // just get the Application record by ID & send it along with the EMSA message
            try {
                AuditUtils::auditMessage($this->dbConn, $systemMessageId, SystemMessageActions::MESSAGE_AUTOPROCESSED, $finalStatus, 'Manual Override selected by user...');
                $overrideEvent = new EmsaCompareNedssEvent($this->dbConn, $appClient->getRecord($overrideEventId), $appClient);
                $overridePerson = $appClient->getPerson($overrideEvent->getPersonId());
            } catch (Throwable $e) {
                ExceptionUtils::logException($e);
                $this->logMessageException(
                    $systemMessageId, AuditUtils::auditMessage($this->dbConn, $systemMessageId, SystemMessageActions::EXCEPTION, $finalStatus), SystemExceptions::UNABLE_TO_ASSIGN_XML_TARGET_APPLICATION, "[getRecord] Unable to execute $appClientName web service function:  " . $e->getMessage()
                );
                $eventLogMessage->add(new EventLogNote("An error occurred while attempting to locate the specified record in $appClientName.  Message moved to Exception list.", 'ui-icon-elrerror', $e->getMessage()));
                return false;
            }

            $this->updateCmr($eventLogMessage, $emsaMessage, $overridePerson, $overrideEvent, $finalStatus, $isAutomated, $override);
            return true;
        } elseif (!empty($emsaMessage->getO2MTargetEventId())) {
            // One-to-Many already performed on this message; event already targeted to update
            try {
                AuditUtils::auditMessage($this->dbConn, $systemMessageId, SystemMessageActions::MESSAGE_AUTOPROCESSED, $finalStatus, 'Continuing One-to-Many assignment process...');
                $overrideEvent = new EmsaCompareNedssEvent($this->dbConn, $appClient->getRecord($emsaMessage->getO2MTargetEventId()), $appClient);
                $overridePerson = $appClient->getPerson($overrideEvent->getPersonId());
            } catch (Throwable $e) {
                ExceptionUtils::logException($e);
                $this->logMessageException(
                    $systemMessageId, AuditUtils::auditMessage($this->dbConn, $systemMessageId, SystemMessageActions::EXCEPTION, $finalStatus), SystemExceptions::UNABLE_TO_ASSIGN_XML_TARGET_APPLICATION, "[getRecord] Unable to execute $appClientName web service function:  " . $e->getMessage()
                );
                $eventLogMessage->add(new EventLogNote("An error occurred while attempting to locate the specified record in $appClientName.  Message moved to Exception list.", 'ui-icon-elrerror', $e->getMessage()));
                return false;
            }

            $this->updateCmr($eventLogMessage, $emsaMessage, $overridePerson, $overrideEvent, $finalStatus, $isAutomated, $override);
            return true;
        } elseif ($emsaMessage->wasO2MPerformed() && empty($emsaMessage->getO2MTargetEventId())) {
            // One-to-Many already performed on this message; picked to create new event
            AuditUtils::auditMessage($this->dbConn, $systemMessageId, SystemMessageActions::MESSAGE_AUTOPROCESSED, $finalStatus, 'Continuing One-to-Many assignment process...');
            $this->addCmr($eventLogMessage, $emsaMessage, $personSearchIds, self::ADDCMR_REASON_ONETOMANY, $finalStatus, $isAutomated, $override, $surveillanceOverride);
            return true;
        } else {
            if (empty($personSearchIds)) {
                // make sure at least one person ID was passed
                AuditUtils::auditMessage($this->dbConn, $systemMessageId, SystemMessageActions::ATTEMPTED_UPDATE_CMR, $finalStatus, 'No people selected to check for existing events.');
                $eventLogMessage->add(new EventLogNote('Could not add to existing event:  No person selected.  Please select at least one matching person and try again.', 'ui-icon-elrerror'));
                return false;
            }
        }

        // get whitelist (gateway) crossref conditions...
        $whitelistCrossrefConditionNames = VocabUtils::whitelistCrossrefNamesByInitialConditionName($this->dbConn, $emsaMessage->masterCondition);

        $initialConditionResultEvents = new EmsaCompareNedssEventSet();
        $crossrefConditionResultEvents = new EmsaCompareNedssEventSet();

        $foundPeople = new PersonList();

        // find events from matched people
        $personSearchIdsStr = implode(', ', $personSearchIds);
        AuditUtils::auditMessage($this->dbConn, $systemMessageId, SystemMessageActions::MESSAGE_AUTOPROCESSED, $finalStatus, "Reviewing event history for Person ID(s) $personSearchIdsStr...");
        foreach ($personSearchIds as $personSearchId) {
            try {
                $foundPeople->add($appClient->getPerson($personSearchId));
            } catch (Throwable $e) {
                ExceptionUtils::logException($e);
                $this->logMessageException(
                    $systemMessageId, AuditUtils::auditMessage($this->dbConn, $systemMessageId, SystemMessageActions::EXCEPTION, $finalStatus), SystemExceptions::UNABLE_TO_ASSIGN_XML_TARGET_APPLICATION, "[getPerson] Unable to execute $appClientName web service function:  " . $e->getMessage()
                );
                $eventLogMessage->add(new EventLogNote('Message moved to Exception list.', 'ui-icon-elrerror', $e->getMessage()));
                return false;
            }
        }

        /* @var $foundPerson Person */
        foreach ($foundPeople as $foundPerson) {
            try {
                /* @var $foundRecord AppRecord */
                foreach ($foundPerson->getRecordList() as $foundRecord) {
                    if ($foundRecord->getConditionName() == $emsaMessage->getPerson()->getRecordList()->getRecord(0)->getConditionName()) {
                        try {
                            $initialConditionResultEvents->addNedssEvent(new EmsaCompareNedssEvent($this->dbConn, $foundRecord, $appClient));
                        } catch (Throwable $e) {
                            ExceptionUtils::logException($e);
                            $this->logMessageException(
                                $systemMessageId, AuditUtils::auditMessage($this->dbConn, $systemMessageId, SystemMessageActions::EXCEPTION, $finalStatus), SystemExceptions::UNABLE_TO_ASSIGN_XML_TARGET_APPLICATION, "[updateCmrProcess] Unexpected error occurred while finding records in matched person:  " . $e->getMessage()
                            );
                            $eventLogMessage->add(new EventLogNote('Message moved to Exception list.', 'ui-icon-elrerror', $e->getMessage()));
                            return false;
                        }
                    } elseif (in_array($foundRecord->getConditionName(), $whitelistCrossrefConditionNames)) {
                        try {
                            $crossrefConditionResultEvents->addNedssEvent(new EmsaCompareNedssEvent($this->dbConn, $foundRecord, $appClient));
                        } catch (Throwable $e) {
                            ExceptionUtils::logException($e);
                            $this->logMessageException(
                                $systemMessageId, AuditUtils::auditMessage($this->dbConn, $systemMessageId, SystemMessageActions::EXCEPTION, $finalStatus), SystemExceptions::UNABLE_TO_ASSIGN_XML_TARGET_APPLICATION, "[updateCmrProcess] Unexpected error occurred while finding records in matched person:  " . $e->getMessage()
                            );
                            $eventLogMessage->add(new EventLogNote('Message moved to Exception list.', 'ui-icon-elrerror', $e->getMessage()));
                            return false;
                        }
                    }
                }
            } catch (Throwable $e) {
                ExceptionUtils::logException($e);
                $this->logMessageException(
                    $systemMessageId, AuditUtils::auditMessage($this->dbConn, $systemMessageId, SystemMessageActions::EXCEPTION, $finalStatus), SystemExceptions::UNABLE_TO_ASSIGN_XML_TARGET_APPLICATION, "[getPerson] Unable to execute $appClientName web service function:  " . $e->getMessage()
                );
                $eventLogMessage->add(new EventLogNote('Message moved to Exception list.', 'ui-icon-elrerror', $e->getMessage()));
                return false;
            }
        }

        if ((count($initialConditionResultEvents) < 1) && (count($crossrefConditionResultEvents) < 1)) {
            // none of the people searched have a matching event, short-circuit and create a new CMR for the initial condition
            $this->addCmr($eventLogMessage, $emsaMessage, $personSearchIds, self::ADDCMR_REASON_NOEVENTS, $finalStatus, $isAutomated, $override, $surveillanceOverride);
            return true;
        }

        // make sure Case Management Rules ran before spending resources on Whitelist Rules...
        if ($emsaMessage->allowUpdateCmr === CaseManagementRulesEngine::CMR_ERR_REQFIELD_LOINC) {
            $this->logMessageException(
                $systemMessageId, AuditUtils::auditMessage($this->dbConn, $systemMessageId, SystemMessageActions::EXCEPTION, $finalStatus), SystemExceptions::UNABLE_TO_EVALUATE_RULE, '[allowUpdateCMR] Unable to run Case Management rules due to missing Master LOINC Code.'
            );
            $eventLogMessage->add(new EventLogNote('Unable to run Case Management rules due to missing Master LOINC Code.  Message moved to Exception list.', 'ui-icon-elrerror'));
            return false;
        } elseif ($emsaMessage->allowUpdateCmr === CaseManagementRulesEngine::CMR_ERR_REQFIELD_DXCODE) {
            $this->logMessageException(
                $systemMessageId, AuditUtils::auditMessage($this->dbConn, $systemMessageId, SystemMessageActions::EXCEPTION, $finalStatus), SystemExceptions::UNABLE_TO_EVALUATE_RULE, '[allowUpdateCMR] Unable to run Case Management rules due to missing Diagnostic Code.'
            );
            $eventLogMessage->add(new EventLogNote('Unable to run Case Management rules due to missing Diagnostic Code.  Message moved to Exception list.', 'ui-icon-elrerror'));
            return false;
        } elseif ($emsaMessage->allowUpdateCmr === CaseManagementRulesEngine::CMR_ERR_REQFIELD_TESTRESULT) {
            $this->logMessageException(
                $systemMessageId, AuditUtils::auditMessage($this->dbConn, $systemMessageId, SystemMessageActions::EXCEPTION, $finalStatus), SystemExceptions::UNABLE_TO_EVALUATE_RULE, '[allowUpdateCMR] Unable to run Case Management rules due to missing Test Result.'
            );
            $eventLogMessage->add(new EventLogNote('Unable to run Case Management rules due to missing Test Result.  Message moved to Exception list.', 'ui-icon-elrerror'));
            return false;
        } elseif ($emsaMessage->allowUpdateCmr === CaseManagementRulesEngine::CMR_ERR_NO_RULES_DEFINED_LOINC) {
            $this->logMessageException(
                $systemMessageId, AuditUtils::auditMessage($this->dbConn, $systemMessageId, SystemMessageActions::EXCEPTION, $finalStatus), SystemExceptions::NO_CASE_MANAGEMENT_RULES_FOUND_MASTER_LOINC, '[allowUpdateCMR] ' . $emsaMessage->getLoincForCaseManagementRules()
            );
            $eventLogMessage->add(new EventLogNote('No Case Management rules defined for Master LOINC.  Message moved to Exception list.', 'ui-icon-elrerror'));
            return false;
        } elseif ($emsaMessage->allowUpdateCmr === CaseManagementRulesEngine::CMR_ERR_NO_RULES_DEFINED_SNOMED) {
            $this->logMessageException(
                $systemMessageId, AuditUtils::auditMessage($this->dbConn, $systemMessageId, SystemMessageActions::EXCEPTION, $finalStatus), SystemExceptions::NO_CASE_MANAGEMENT_RULES_FOUND_MASTER_SNOMED, '[allowUpdateCMR] ' . $emsaMessage->masterOrganism
            );
            $eventLogMessage->add(new EventLogNote('No Case Management rules defined for Master Organism.  Message moved to Exception list.', 'ui-icon-elrerror'));
            return false;
        } elseif ($emsaMessage->allowUpdateCmr === CaseManagementRulesEngine::CMR_ERR_NO_RULES_DEFINED_DXCODE) {
            $this->logMessageException(
                $systemMessageId, AuditUtils::auditMessage($this->dbConn, $systemMessageId, SystemMessageActions::EXCEPTION, $finalStatus), SystemExceptions::NO_CASE_MANAGEMENT_RULES_FOUND_ICD_CODE, '[allowUpdateCMR] ' . $emsaMessage->diagnosticCode . ' (' . $emsaMessage->diagnosticCodingSystem . ')'
            );
            $eventLogMessage->add(new EventLogNote('No Case Management rules defined for Diagnostic Code.  Message moved to Exception list.', 'ui-icon-elrerror'));
            return false;
        } elseif ($emsaMessage->allowUpdateCmr === CaseManagementRulesEngine::CMR_ERR_NO_RULES_MATCHED) {
            $this->logMessageException(
                $systemMessageId, AuditUtils::auditMessage($this->dbConn, $systemMessageId, SystemMessageActions::EXCEPTION, $finalStatus), SystemExceptions::NO_CASE_MANAGEMENT_RULES_TRUE, '[allowUpdateCMR] ' . $emsaMessage->getLoincForCaseManagementRules() . '/' . $emsaMessage->masterOrganism . ' [' . $emsaMessage->getTestResultForCaseManagementRules() . ']'
            );
            $eventLogMessage->add(new EventLogNote('No Case Management rules evaluated true.  Message moved to Exception list.', 'ui-icon-elrerror'));
            return false;
        } elseif ($emsaMessage->allowUpdateCmr === CaseManagementRulesEngine::CMR_ERR_MULT_RULES_MATCHED) {
            $this->logMessageException(
                $systemMessageId, AuditUtils::auditMessage($this->dbConn, $systemMessageId, SystemMessageActions::EXCEPTION, $finalStatus), SystemExceptions::UNABLE_TO_EVALUATE_RULE, '[allowUpdateCMR] Multiple rules evaluated true; ' . $emsaMessage->getLoincForCaseManagementRules() . '/' . $emsaMessage->masterOrganism . ' [' . $emsaMessage->getTestResultForCaseManagementRules() . ']'
            );
            $eventLogMessage->add(new EventLogNote('Multiple Case Management rules evaluated true.  Message moved to Exception list.', 'ui-icon-elrerror'));
            return false;
        }

        // prepare to run whitelist rules on matched events
        $eventLogMessage->add(new EventLogNote('Running Whitelist Rules against discovered events...', 'ui-icon-elrrules'));
        AuditUtils::auditMessage($this->dbConn, $systemMessageId, SystemMessageActions::MESSAGE_AUTOPROCESSED, $finalStatus, 'Running Whitelist Rules against discovered events...');
        $initialConditionWhitelistResults = new WhitelistRuleEvaluator();
        $crossrefConditionWhitelistResults = new WhitelistRuleEvaluator();

        if (count($initialConditionResultEvents) > 0) {
            $initialConditionWhitelistResults->evaluateWhitelistRules($initialConditionResultEvents, $emsaMessage);
        }

        if (count($crossrefConditionResultEvents) > 0) {
            $crossrefConditionWhitelistResults->evaluateWhitelistRules($crossrefConditionResultEvents, $emsaMessage);
        }

        $totalExceptions = 0;
        $totalExceptions += count($initialConditionWhitelistResults->getMorbidityExceptionEvents());
        $totalExceptions += count($initialConditionWhitelistResults->getContactExceptionEvents());
        $totalExceptions += count($crossrefConditionWhitelistResults->getMorbidityExceptionEvents());
        $totalExceptions += count($crossrefConditionWhitelistResults->getContactExceptionEvents());

        $totalContactsWhitelisted = 0;
        $totalContactsWhitelisted += count($initialConditionWhitelistResults->getContactWhitelistEvents());
        $totalContactsWhitelisted += count($crossrefConditionWhitelistResults->getContactWhitelistEvents());

        $totalMorbiditiesWhitelisted = 0;
        $totalMorbiditiesWhitelisted += count($initialConditionWhitelistResults->getMorbidityWhitelistEvents());
        $totalMorbiditiesWhitelisted += count($crossrefConditionWhitelistResults->getMorbidityWhitelistEvents());

        if ($totalExceptions > 0) {
            // one or more exceptions happened during Whitelist Rule Evaluation; stop assignment and deal with the exceptions
            if (!$this->handleWhitelistExceptions($eventLogMessage, $emsaMessage, $initialConditionWhitelistResults, $crossrefConditionWhitelistResults, $finalStatus)) {
                return false;
            }
        }

        if (($totalMorbiditiesWhitelisted + $totalContactsWhitelisted) < 1) {
            // none of the found events meet Whitelist Rules; short-circit and create a new CMR for the initial condition
            $this->addCmr($eventLogMessage, $emsaMessage, $personSearchIds, self::ADDCMR_REASON_NOWHITELIST, $finalStatus, $isAutomated, $override, $surveillanceOverride);
            return true;
        }

        $mergedWhitelistedEvents = new WhitelistRuleTupleSet();
        $crossrefTotalCount = count($crossrefConditionWhitelistResults->getMorbidityWhitelistEvents()) + count($crossrefConditionWhitelistResults->getContactWhitelistEvents());
        $initialTotalCount = count($initialConditionWhitelistResults->getMorbidityWhitelistEvents()) + count($initialConditionWhitelistResults->getContactWhitelistEvents());

        if ($emsaMessage->getAllowOneToMany()) {
            // using One-to-Many assignment
            if ($emsaMessage->checkXrefFirst) {
                // using "Check Crossrefs First"
                if ($crossrefTotalCount > 0) {
                    $mergedWhitelistedEvents->merge($crossrefConditionWhitelistResults->getMorbidityWhitelistEvents(), $crossrefConditionWhitelistResults->getContactWhitelistEvents());
                    if ($crossrefConditionWhitelistResults->getMorbidityWhitelistEvents()->hasMultipleEventsPerCondition()) {
                        // crossref matches exist and multiple crossref CMRs for same condition
                        $thisAuditId = AuditUtils::auditMessage($this->dbConn, $systemMessageId, SystemMessageActions::PENDING, $finalStatus);  // exception
                        $this->logMessageException(
                            $systemMessageId, $thisAuditId, SystemExceptions::WHITELIST_RULE_EXCEPTION, "More than one $appClientName Morbidity event for the same condition matched Whitelist Rules.  Matched Record Numbers:<br>" . EmsaCompareUtils::nedssLinkByWhitelistRuleTupleSet($appClient, $mergedWhitelistedEvents, AppRecordType::MORBIDITY_EVENT), NEDSS_EXCEPTION_STATUS
                        );
                        $eventLogMessage->add(new EventLogNote("Could not assign this message to the selected person(s) because multiple $appClientName Morbidity events for the same condition matched Whitelist Rules.  Message moved to Pending list.", 'ui-icon-elrerror'));
                        return false;
                    }
                } elseif ($initialTotalCount > 0) {
                    $mergedWhitelistedEvents->merge($initialConditionWhitelistResults->getMorbidityWhitelistEvents(), $initialConditionWhitelistResults->getContactWhitelistEvents());
                    if ($initialConditionWhitelistResults->getMorbidityWhitelistEvents()->hasMultipleEventsPerCondition()) {
                        // initial condition matches exist and multiple initial CMRs for same condition
                        $thisAuditId = AuditUtils::auditMessage($this->dbConn, $systemMessageId, SystemMessageActions::PENDING, $finalStatus);  // exception
                        $this->logMessageException(
                            $systemMessageId, $thisAuditId, SystemExceptions::WHITELIST_RULE_EXCEPTION, "More than one $appClientName Morbidity event for the same condition matched Whitelist Rules.  Matched Record Numbers:<br>" . EmsaCompareUtils::nedssLinkByWhitelistRuleTupleSet($appClient, $mergedWhitelistedEvents, AppRecordType::MORBIDITY_EVENT), NEDSS_EXCEPTION_STATUS
                        );
                        $eventLogMessage->add(new EventLogNote("Could not assign this message to the selected person(s) because multiple $appClientName Morbidity events for the same condition matched Whitelist Rules.  Message moved to Pending list.", 'ui-icon-elrerror'));
                        return false;
                    }
                }
            } else {
                // not using "Check Crossrefs First"; treat all conditions equally
                $mergedWhitelistedEvents->merge(
                    $crossrefConditionWhitelistResults->getMorbidityWhitelistEvents(),
                    $crossrefConditionWhitelistResults->getContactWhitelistEvents(),
                    $initialConditionWhitelistResults->getMorbidityWhitelistEvents(),
                    $initialConditionWhitelistResults->getContactWhitelistEvents()
                );
                if (($crossrefConditionWhitelistResults->getMorbidityWhitelistEvents()->hasMultipleEventsPerCondition()) || ($initialConditionWhitelistResults->getMorbidityWhitelistEvents()->hasMultipleEventsPerCondition())) {
                    // multiple CMRs exist for same condition somewhere in the result set
                    $thisAuditId = AuditUtils::auditMessage($this->dbConn, $systemMessageId, SystemMessageActions::PENDING, $finalStatus);  // exception
                    $this->logMessageException(
                        $systemMessageId, $thisAuditId, SystemExceptions::WHITELIST_RULE_EXCEPTION, "More than one $appClientName Morbidity event for the same condition matched Whitelist Rules.  Matched Record Numbers:<br>" . EmsaCompareUtils::nedssLinkByWhitelistRuleTupleSet($appClient, $mergedWhitelistedEvents, AppRecordType::MORBIDITY_EVENT), NEDSS_EXCEPTION_STATUS
                    );
                    $eventLogMessage->add(new EventLogNote("Could not assign this message to the selected person(s) because multiple $appClientName Morbidity events for the same condition matched Whitelist Rules.  Message moved to Pending list.", 'ui-icon-elrerror'));
                    return false;
                }
            }
        } else {
            // using standard One-to-One assignment, not One-to-Many
            if ($emsaMessage->checkXrefFirst) {
                // using "Check Crossrefs First"
                if ($crossrefTotalCount > 0) {
                    if (count($crossrefConditionWhitelistResults->getMorbidityWhitelistEvents()) > 0) {
                        $mergedWhitelistedEvents->merge($crossrefConditionWhitelistResults->getMorbidityWhitelistEvents());
                        if (count($crossrefConditionWhitelistResults->getMorbidityWhitelistEvents()) > 1) {
                            // multiple CMRs exist in crossrefs
                            $thisAuditId = AuditUtils::auditMessage($this->dbConn, $systemMessageId, SystemMessageActions::PENDING, $finalStatus);  // exception
                            $this->logMessageException(
                                $systemMessageId, $thisAuditId, SystemExceptions::WHITELIST_RULE_EXCEPTION, "More than one $appClientName Morbidity event matched Whitelist Rules.  Matched Record Numbers:<br>" . EmsaCompareUtils::nedssLinkByWhitelistRuleTupleSet($appClient, $mergedWhitelistedEvents, AppRecordType::MORBIDITY_EVENT), NEDSS_EXCEPTION_STATUS
                            );
                            $eventLogMessage->add(new EventLogNote("Could not assign this message to the selected person(s) because multiple $appClientName Morbidity events matched Whitelist Rules.  Message moved to Pending list.", 'ui-icon-elrerror'));
                            return false;
                        }
                    } elseif (count($crossrefConditionWhitelistResults->getContactWhitelistEvents()) > 0) {
                        $mergedWhitelistedEvents->merge($crossrefConditionWhitelistResults->getContactWhitelistEvents());
                    }
                } elseif ($initialTotalCount > 0) {
                    if (count($initialConditionWhitelistResults->getMorbidityWhitelistEvents()) > 0) {
                        $mergedWhitelistedEvents->merge($initialConditionWhitelistResults->getMorbidityWhitelistEvents());
                        if (count($initialConditionWhitelistResults->getMorbidityWhitelistEvents()) > 1) {
                            // multiple CMRs exist for initial condition
                            $thisAuditId = AuditUtils::auditMessage($this->dbConn, $systemMessageId, SystemMessageActions::PENDING, $finalStatus);  // exception
                            $this->logMessageException(
                                $systemMessageId, $thisAuditId, SystemExceptions::WHITELIST_RULE_EXCEPTION, "More than one $appClientName Morbidity event matched Whitelist Rules.  Matched Record Numbers:<br>" . EmsaCompareUtils::nedssLinkByWhitelistRuleTupleSet($appClient, $mergedWhitelistedEvents, AppRecordType::MORBIDITY_EVENT), NEDSS_EXCEPTION_STATUS
                            );
                            $eventLogMessage->add(new EventLogNote("Could not assign this message to the selected person(s) because multiple $appClientName Morbidity events matched Whitelist Rules.  Message moved to Pending list.", 'ui-icon-elrerror'));
                            return false;
                        }
                    } elseif (count($initialConditionWhitelistResults->getContactWhitelistEvents()) > 0) {
                        $mergedWhitelistedEvents->merge($initialConditionWhitelistResults->getContactWhitelistEvents());
                    }
                }
            } else {
                // not using "Check Crossrefs First"; treat all conditions equally
                if ((count($initialConditionWhitelistResults->getMorbidityWhitelistEvents()) > 0) || (count($crossrefConditionWhitelistResults->getMorbidityWhitelistEvents()) > 0)) {
                    $mergedWhitelistedEvents->merge(
                        $initialConditionWhitelistResults->getMorbidityWhitelistEvents(),
                        $crossrefConditionWhitelistResults->getMorbidityWhitelistEvents()
                    );

                    if ((count($initialConditionWhitelistResults->getMorbidityWhitelistEvents()) === 1) && (count($crossrefConditionWhitelistResults->getMorbidityWhitelistEvents()) === 1)) {
                        // exactly one event found in initial and crossref; possible that 'newest'-based Whitelist Rule cropped to those
                        // if the result sets have been merged, we need to re-newest-ify the merged set and pick the ultimate victor
                        $initialEventRuleType = $initialConditionWhitelistResults->getMorbidityWhitelistEvents()->current()->getWhitelistRuleApplied()->getRuleType();
                        $crossrefEventRuleType = $crossrefConditionWhitelistResults->getMorbidityWhitelistEvents()->current()->getWhitelistRuleApplied()->getRuleType();

                        if ((($initialEventRuleType === WhitelistRule::WHITELIST_RULETYPE_NEWEST) || ($initialEventRuleType === WhitelistRule::WHITELIST_RULETYPE_TB_MULTI)) && (($crossrefEventRuleType === WhitelistRule::WHITELIST_RULETYPE_NEWEST) || ($crossrefEventRuleType === WhitelistRule::WHITELIST_RULETYPE_TB_MULTI))) {
                            $mergedWhitelistedEvents->sortByEventDateNewestFirst();
                            $cropEventId = $mergedWhitelistedEvents->current()->getNedssEvent()->getEventId();
                            $mergedWhitelistedEvents->cropTo($cropEventId);
                        }
                    }

                    //if ((count($initialConditionWhitelistResults->getMorbidityWhitelistEvents()) + count($crossrefConditionWhitelistResults->getMorbidityWhitelistEvents())) > 1) {
                    // if more than one matched event still exists, send to Pending for resolution
                    if (count($mergedWhitelistedEvents) > 1) {
                        // multiple CMRs exist for initial condition
                        $thisAuditId = AuditUtils::auditMessage($this->dbConn, $systemMessageId, SystemMessageActions::PENDING, $finalStatus);  // exception
                        $this->logMessageException(
                            $systemMessageId, $thisAuditId, SystemExceptions::WHITELIST_RULE_EXCEPTION, "More than one $appClientName Morbidity event matched Whitelist Rules.  Matched Record Numbers:<br>" . EmsaCompareUtils::nedssLinkByWhitelistRuleTupleSet($appClient, $mergedWhitelistedEvents, AppRecordType::MORBIDITY_EVENT), NEDSS_EXCEPTION_STATUS
                        );
                        $eventLogMessage->add(new EventLogNote("Could not assign this message to the selected person(s) because multiple $appClientName Morbidity events matched Whitelist Rules.  Message moved to Pending list.", 'ui-icon-elrerror'));
                        return false;
                    }
                } elseif ((count($initialConditionWhitelistResults->getContactWhitelistEvents()) > 0) || (count($crossrefConditionWhitelistResults->getContactWhitelistEvents()) > 0)) {
                    $mergedWhitelistedEvents->merge(
                        $initialConditionWhitelistResults->getContactWhitelistEvents(),
                        $crossrefConditionWhitelistResults->getContactWhitelistEvents()
                    );

                    // just in case there were Contacts identified in both the initial and crossref sets before we merged, prune down to the newest one
                    $mergedWhitelistedEvents->sortByEventDateNewestFirst();
                    $cropEventId = $mergedWhitelistedEvents->current()->getNedssEvent()->getEventId();
                    $mergedWhitelistedEvents->cropTo($cropEventId);
                }
            }
        }

        // check to see if any events we're updating are flagged "Not a Case" and
        // Whitelist Rule used is something other than "Never a New Case"
        $ncAdd = false;
        $ncRemove = [];
        foreach ($mergedWhitelistedEvents as $ncMergedWhitelistTuple) {
            if (
                !$ncMergedWhitelistTuple->getWhitelistRuleApplied()->getIgnoreCaseStatus() &&
                (stripos($ncMergedWhitelistTuple->getNedssEvent()->getStateCaseStatus(), 'not a case') !== false) &&
                (trim($emsaMessage->stateCaseStatus) != 'NC') &&
                ($ncMergedWhitelistTuple->getWhitelistRuleApplied()->getRuleType() !== WhitelistRule::WHITELIST_RULETYPE_NEVER_NEW) &&
                ($ncMergedWhitelistTuple->getWhitelistRuleApplied()->getRuleType() !== WhitelistRule::WHITELIST_RULETYPE_NEWEST) &&
                !$override
            ) {
                $ncAdd = $ncAdd || true;
                $ncRemove[] = $ncMergedWhitelistTuple->getNedssEvent()->getEventId();
            }
        }

        if (count($ncRemove) > 0) {
            foreach ($ncRemove as $ncRemoveId) {
                $mergedWhitelistedEvents->remove($ncRemoveId);
            }
        }

        // discover if we need to create+update in One-to-Many mode
        $o2mAdd = false;
        $o2mInstancesNeeded = 0;
        if ($emsaMessage->getAllowOneToMany()) {
            $o2mAdd = true;
            // get list of conditions that, if not found in the matched events, and using One-to-Many assignment, will allow a new Morbidity event to be created
            $o2mAddCMRExclusionConditions = VocabUtils::o2mCreateIfNotFoundConditionsByInitialConditionName($this->dbConn, $emsaMessage->masterCondition);
            $o2mAddTupleSet = (new WhitelistRuleTupleSet())->merge(
                $crossrefConditionWhitelistResults->getMorbidityWhitelistEvents(),
                $crossrefConditionWhitelistResults->getContactWhitelistEvents(),
                $initialConditionWhitelistResults->getMorbidityWhitelistEvents(),
                $initialConditionWhitelistResults->getContactWhitelistEvents()
            );

            foreach ($o2mAddTupleSet as $o2mAddTuple) {
                if (($o2mAddTuple->getNedssEvent()->getDiseaseName() == $emsaMessage->masterCondition) || in_array($o2mAddTuple->getNedssEvent()->getDiseaseName(), $o2mAddCMRExclusionConditions)) {
                    $o2mAdd = false;
                    break;
                }
            }

            $o2mInstancesNeeded += count($mergedWhitelistedEvents);

            if ($ncAdd || $o2mAdd) {
                // need an extra copy of the message to add a new event
                $o2mInstancesNeeded++;
            }
        }

        if ($emsaMessage->allowUpdateCmr === CaseManagementRulesEngine::CMR_YES) {
            // attempt to update the target event(s)...
            $copiesNeeded = $o2mInstancesNeeded - 1;

            foreach ($mergedWhitelistedEvents as $mergedWhitelistedEventTuple) {
                $eventLogMessage->add(new EventLogNote("Record# {$mergedWhitelistedEventTuple->getNedssEvent()->getRecordNumber()} matches whitelist rules, checking existing labs...", 'ui-icon-elrrules'));
                AuditUtils::auditMessage($this->dbConn, $systemMessageId, SystemMessageActions::MESSAGE_AUTOPROCESSED, $finalStatus, "Record# {$mergedWhitelistedEventTuple->getNedssEvent()->getRecordNumber()} matches whitelist rules, checking existing labs...");

                if ($emsaMessage->getAllowOneToMany()) {
                    if ($copiesNeeded > 0) {
                        try {
                            $targetMessage = new EmsaMessage($this->dbConn, $this->appClientList, $this->copyMessageForOneToMany($systemMessageId, $finalStatus, $mergedWhitelistedEventTuple->getNedssEvent()->getEventId()), false);
                            $targetEventLogMessage = new EventLogMessage($targetMessage->getSystemMessageId());
                            $eventLogMessage->add($targetEventLogMessage);
                            AuditUtils::auditMessage($this->dbConn, $systemMessageId, SystemMessageActions::MESSAGE_COPY_CREATED, $finalStatus, "One-to-Many created copy (System Message ID {$targetMessage->getSystemMessageId()}) to update Record# {$mergedWhitelistedEventTuple->getNedssEvent()->getRecordNumber()}");
                        } catch (Throwable $e) {
                            ExceptionUtils::logException($e);
                            $eventLogMessage->add(new EventLogNote('An unexpected error occurred while attempting to create a copy of this message for One-to-Many assignment.', 'ui-icon-elrerror', $e->getMessage()));
                            return false;
                        }
                        $copiesNeeded--;
                    } else {
                        try {
                            $this->setO2MFlagsForMessage($systemMessageId, $mergedWhitelistedEventTuple->getNedssEvent()->getEventId());
                        } catch (Throwable $e) {
                            ExceptionUtils::logException($e);
                            $eventLogMessage->add(new EventLogNote('An unexpected error occurred while attempting to set One-to-Many parameters for this message.', 'ui-icon-elrerror', $e->getMessage()));
                            return false;
                        }
                        $targetEventLogMessage = $eventLogMessage;
                        $targetMessage = $emsaMessage;
                    }
                } else {
                    $targetEventLogMessage = $eventLogMessage;
                    $targetMessage = $emsaMessage;
                }

                $this->updateCmr($targetEventLogMessage, $targetMessage, $foundPeople->get($mergedWhitelistedEventTuple->getNedssEvent()->getPersonId()), $mergedWhitelistedEventTuple->getNedssEvent(), $finalStatus, $isAutomated, $override);
            }
        } else {
            // $emsaMessage->allowUpdateCmr === CaseManagementRulesEngine::CMR_NO
            try {
                $this->graylistMessage($emsaMessage, $finalStatus, 'Existing event cannot be updated per Case Management Rules.');
                $eventLogMessage->setProcessedSuccessfully(true);
                $eventLogMessage->add(new EventLogNote('Existing event cannot be updated per Case Management Rules.  Message moved to Gray list...', 'ui-icon-elrcancel'));
            } catch (Throwable $e) {
                ExceptionUtils::logException($e);
                $eventLogMessage->add(new EventLogNote('An unexpected error occurred while attempting to Graylist message.', 'ui-icon-elrerror', $e->getMessage()));
                return false;
            }
        }

        // if adding message while in O2M processing; set flags
        if (($emsaMessage->getAllowOneToMany()) && ($ncAdd || $o2mAdd)) {
            try {
                $this->setO2MFlagsForMessage($systemMessageId);
            } catch (Throwable $e) {
                ExceptionUtils::logException($e);
                $eventLogMessage->add(new EventLogNote('An unexpected error occurred while attempting to set One-to-Many parameters for this message.', 'ui-icon-elrerror', $e->getMessage()));
                return false;
            }
        }

        // in O2M, if need for addCmr identified via both Not a Case and One-to-Many rules, treat as if detected via Not a Case rules
        if ($ncAdd) {
            $this->addCmr($eventLogMessage, $emsaMessage, $personSearchIds, self::ADDCMR_REASON_NOTACASE, $finalStatus, $isAutomated, $override, $surveillanceOverride);
        } elseif ($o2mAdd) {
            $this->addCmr($eventLogMessage, $emsaMessage, $personSearchIds, self::ADDCMR_REASON_ONETOMANY, $finalStatus, $isAutomated, $override, $surveillanceOverride);
        }

        return true;
    }

    /**
     * Update an existing NEDSS event with the specified ELR message
     *
     * @param EventLogMessage       $eventLogMessage
     * @param EmsaMessage           $emsaMessage  EMSA message being assigned.
     * @param Person                $targetPerson Person the target record belongs to.
     * @param EmsaCompareNedssEvent $nedssEvent   Target NEDSS record to be updated.
     * @param int                   $finalStatus  [Optional]<br>EMSA message queue ID the message is being assigned
     *                                            from.  Default <b>ENTRY_STATUS</b> (Entry queue).
     * @param boolean               $isAutomated  [Optional]<br>Indicates whether the message is being assigned
     *                                            manually or via EMSA automation.  Default <b>FALSE</b>.
     * @param boolean               $override     [Optional]<br>Indicates whether message assignment is being done in
     *                                            'override' mode.  Default <b>FALSE</b>.
     */
    final private function updateCmr(EventLogMessage $eventLogMessage, EmsaMessage $emsaMessage, Person $targetPerson, EmsaCompareNedssEvent $nedssEvent, $finalStatus = ENTRY_STATUS, $isAutomated = false, $override = false)
    {
        $systemMessageId = $emsaMessage->getSystemMessageId();
        $nedssEventId = $nedssEvent->getEventId();
        $targetLabResultId = null;

        $finalUpdateRecordXML = new SimpleXMLElement('<?xml version="1.0" encoding="UTF-8" standalone="yes"?><nedssHealth/>');
        $finalUpdateRecordXML->addChild('person');

        EmsaMessageXmlUtils::prepareRecordXMLForUpdateRecord($finalUpdateRecordXML, $nedssEvent);

        // check for Manual ELR Entry result value
        EmsaMessageXmlUtils::copyManualELRResultValue($emsaMessage);

        if ($override) {
            $compareResults = new EmsaLabCompareResult();
            $compareResults->evaluateCompareResults();  // force to EmsaLabCompareResult::COMPARE_RESULT_ADD
        } else {
            // compare lab results, check for duplicates/updates/adds
            $compareResults = EmsaCompareUtils::compareLabsForUpdateRecord($emsaMessage, $nedssEvent);
        }

        if ($compareResults->getLabResultCode() === EmsaLabCompareResult::COMPARE_RESULT_EXCEPTION) {
            // lab exceptions found
            $this->logMessageException(
                $systemMessageId, AuditUtils::auditMessage($this->dbConn, $systemMessageId, SystemMessageActions::PENDING, $finalStatus), SystemExceptions::ENTRY_QUEUE_EXCEPTION, 'Test results changed for same sample collection time.  Matched Event:<br>' . EmsaCompareUtils::nedssLinkByEventId($emsaMessage->getAppClient(), $nedssEventId, $nedssEvent->getRecordNumber(), $nedssEvent->getEventType()), NEDSS_EXCEPTION_STATUS
            );
            $eventLogMessage->add(new EventLogNote('An exception occurred while attempting to update this lab.  Message moved to Pending list.', 'ui-icon-elrerror'));
        } elseif ($compareResults->getResistResultCode() === EmsaLabCompareResult::COMPARE_RESULT_EXCEPTION) {
            // susceptibility exceptions found
            $this->logMessageException(
                $systemMessageId, AuditUtils::auditMessage($this->dbConn, $systemMessageId, SystemMessageActions::PENDING, $finalStatus), SystemExceptions::ENTRY_QUEUE_EXCEPTION, 'Antimicrobial resistance results changed for same organism and agent.  Matched Event:<br>' . EmsaCompareUtils::nedssLinkByEventId($emsaMessage->getAppClient(), $nedssEventId, $nedssEvent->getRecordNumber(), $nedssEvent->getEventType()), NEDSS_EXCEPTION_STATUS
            );
            $eventLogMessage->add(new EventLogNote('An exception occurred while attempting to update this lab.  Message moved to Pending list.', 'ui-icon-elrerror'));
        } else {
            // no exceptions encountered with lab result comparison, continue with update
            $assignAction = null;
            $skipLabAssignment = false;

            if ($compareResults->getLabResultCode() === EmsaLabCompareResult::COMPARE_RESULT_DUPLICATE) {
                if (($compareResults->getResistResultCode() === EmsaLabCompareResult::COMPARE_RESULT_NOT_FOUND) || ($compareResults->getResistResultCode() === EmsaLabCompareResult::COMPARE_RESULT_DUPLICATE)) {
                    // lab results are duplicate and should not be assigned
                    $skipLabAssignment = true;
                    AuditUtils::auditMessage($this->dbConn, $systemMessageId, SystemMessageActions::DUPLICATE_LAB_RESULTS, $finalStatus);
                    $eventLogMessage->add(new EventLogNote('Incoming lab results already exist in the target event and will not be included in the update.', 'ui-icon-elrclose'));
                }
            }

            if (!$skipLabAssignment && $emsaMessage->masterTestType == 'PFGE') {
                // preserve PFGE pattern, if PFGE test
                EmsaMessageXmlUtils::pfgePatternToResultValue($emsaMessage);
            }

            $updateLabResult = ($compareResults->getLabResultCode() === EmsaLabCompareResult::COMPARE_RESULT_UPDATE);
            $duplicateLabResult = ($compareResults->getLabResultCode() === EmsaLabCompareResult::COMPARE_RESULT_DUPLICATE);
            $addResistTestResult = ($compareResults->getResistResultCode() === EmsaLabCompareResult::COMPARE_RESULT_ADD);

            if ($updateLabResult || ($duplicateLabResult && $addResistTestResult)) {
                // updating an existing lab result or adding an antimicrobial susceptibility result to an existing lab result
                $updateLab = $compareResults->getUpdateLab();
                $targetLabResultId = (int)$updateLab->labTestResultId;
                EmsaMessageXmlUtils::prepareEmsaMessageLabDataForUpdateCmr($updateLab, $finalUpdateRecordXML);

                if ($addResistTestResult) {
                    EmsaMessageXmlUtils::updateRecordAddResistTest($emsaMessage, $finalUpdateRecordXML);
                }

                if ($updateLabResult) {
                    if ($emsaMessage->getIsKnittable()) {
                        // knit lab results
                        $eventLogMessage->add(new EventLogNote('Stitching lab results...', 'ui-icon-elrpencil'));
                        EmsaMessageXmlUtils::stitchLabData($emsaMessage, $finalUpdateRecordXML);

                        // if test status should be upgraded, do that as well as knitting...
                        if ($updateLab->isTestStatusUpgraded === true) {
                            $eventLogMessage->add(new EventLogNote('Updating test status for existing lab results...', 'ui-icon-elrpencil'));
                            EmsaMessageXmlUtils::updateRecordUpgradeTestStatus($emsaMessage, $finalUpdateRecordXML);
                        }
                    } else {
                        // retain current resultValue, units & refRange; only update test status
                        $eventLogMessage->add(new EventLogNote('Updating test status for existing lab results...', 'ui-icon-elrpencil'));
                        EmsaMessageXmlUtils::updateRecordUpgradeTestStatus($emsaMessage, $finalUpdateRecordXML);
                    }
                }

                EmsaMessageXmlUtils::updateCmrCleanupTasks($this->dbConn, $emsaMessage, $finalUpdateRecordXML, $updateLab, false, $skipLabAssignment);

                $assignAction = SystemMessageActions::MESSAGE_ASSIGNED_UPDATED_CMR_UPDATED_LAB;
            } elseif ($skipLabAssignment || ($compareResults->getLabResultCode() === EmsaLabCompareResult::COMPARE_RESULT_CLINICAL_DOCUMENT)) {
                // updating event with non-lab data (e.g. from Clinical Document or ELR message with duplicate lab results)
                $assignAction = SystemMessageActions::MESSAGE_ASSIGNED_APPENDED_NON_LAB_DATA;
                EmsaMessageXmlUtils::updateCmrCleanupTasks($this->dbConn, $emsaMessage, $finalUpdateRecordXML, null, true, $skipLabAssignment);
                $eventLogMessage->add(new EventLogNote('Updating event with non-laboratory data', 'ui-icon-elrpencil'));
            } else {
                // creating a new lab for the event
                $assignAction = SystemMessageActions::MESSAGE_ASSIGNED_UPDATED_CMR_ADDED_NEW_LAB;
                EmsaMessageXmlUtils::updateCmrCleanupTasks($this->dbConn, $emsaMessage, $finalUpdateRecordXML, null, true, $skipLabAssignment);
                $eventLogMessage->add(new EventLogNote('No matching labs, adding new lab & lab results to event...', 'ui-icon-elrplus'));
            }

            $isInterstateTx = false;
            if (($emsaMessage->getSource()->getJurisdiction() == 'Out of State') && ($emsaMessage->getFinalStatus() !== OOS_STATUS)) {
                if ($emsaMessage->getMessageType() === MessageType::ELR_MESSAGE && !empty($emsaMessage->getMasterXML()->person->state) && $this->isMessageInteragencyTx($emsaMessage->getOriginalMessageId())) {
                    // original message was sent via Interstate
                    $isInterstateTx = true;
                }
            }

            // move updated contact info (name, address, telephone, etc.) to notes
            // check to see if person associated with CMR to update has the same name, address, and DOB; exception if not
            if ($emsaMessage->getMessageType() == MessageType::CLINICAL_DOCUMENT) {
                $notesProcessingOutcome = EmsaMessageXmlUtils::clinicalDocumentToNotes($this->dbConn, $emsaMessage, $finalUpdateRecordXML, $targetPerson);
            } else {
                $notesProcessingOutcome = EmsaMessageXmlUtils::updatedContactInfoToNotes($this->dbConn, $emsaMessage, $finalUpdateRecordXML, $targetPerson, $assignAction, $skipLabAssignment, $isInterstateTx);
            }

            if ($notesProcessingOutcome || $override) {
                // same name/addr/dob
                try {
                    if ($isInterstateTx) {
                        // original message was transmitted via Interstate
                        AuditUtils::auditMessage($this->dbConn, $systemMessageId, SystemMessageActions::MESSAGE_AUTOPROCESSED, $finalStatus, 'Original message is from an Out of State patient.  Original message has also been transmitted to state of residence via Interstate Transmission.');
                        $eventLogMessage->add(new EventLogNote('Original message is from an Out of State patient.  Original message has also been transmitted to state of residence via Interstate Transmission.'));
                    }
                    $updateCmrReturn = $emsaMessage->getAppClient()->updateRecord($finalUpdateRecordXML);
                } catch (Throwable $e) {
                    ExceptionUtils::logException($e);
                    $auditId = AuditUtils::auditMessage($this->dbConn, $systemMessageId, SystemMessageActions::EXCEPTION, $finalStatus);
                    AuditUtils::auditXML($this->dbConn, $nedssEvent->getAppRecordXML()->asXML(), $finalUpdateRecordXML, $auditId, true);
                    $this->logMessageException(
                        $systemMessageId, $auditId, SystemExceptions::UNABLE_TO_ASSIGN_XML_TARGET_APPLICATION, '[updateCmr] ' . $e->getMessage()
                    );
                    $eventLogMessage->add(new EventLogNote('Message moved to Exception list.', 'ui-icon-elrerror', $e->getMessage()));
                    return;
                }

                $this->processAssignmentReturn($eventLogMessage, $emsaMessage, $finalUpdateRecordXML, $updateCmrReturn, $assignAction, $nedssEvent, $nedssEventId, $targetLabResultId, true, $isAutomated, $finalStatus);
            } else {
                // different name/addr/dob
                $this->logMessageException(
                    $systemMessageId, AuditUtils::auditMessage($this->dbConn, $systemMessageId, SystemMessageActions::PENDING, $finalStatus), SystemExceptions::ENTRY_QUEUE_EXCEPTION, 'Patient from incoming message did not match selected person for evemt update.  Matched event:<br>' . EmsaCompareUtils::nedssLinkByEventId($emsaMessage->getAppClient(), $nedssEvent->getEventId(), $nedssEvent->getRecordNumber(), $nedssEvent->getEventType()), NEDSS_EXCEPTION_STATUS
                );
                $eventLogMessage->add(new EventLogNote('Could not update event:  selected person may not match patient from incoming message.  Message moved to Pending list.', 'ui-icon-elrerror'));
            }
        }
    }

    /**
     * Process the XML return from the NEDSS SOAP API message assignment function and log the results.
     *
     * @param EventLogMessage       $eventLogMessage
     * @param EmsaMessage           $emsaMessage     EMSA message being assigned
     * @param SimpleXMLElement      $assignedXML     Final, modified XML that was sent to the Application for
     *                                               assignment
     * @param SimpleXMLElement      $appResponseXML  XML return from the Application client assignment method
     * @param int                   $smAction        Action taken during assignment (one of <b>SystemMessageActions</b>
     *                                               constants)
     * @param EmsaCompareNedssEvent $nedssEvent      NEDSS event being updated (required if <i>isUpdate</i> is
     *                                               <b>TRUE</b>)
     * @param int                   $targetEventId   [Optional]<br>If updating an existing record, the ID of the record
     *                                               targeted for update.
     * @param int                   $targetResultId  [Optional]<br>If updating an existing lab test result, the ID of
     *                                               the lab test result targeted for update.
     * @param boolean               $isUpdate        [Optional]<br><b>TRUE</b> if assignment is updating an existing
     *                                               NEDSS event, <b>FALSE</b> if assignment is adding a new CMR.
     *                                               Default <b>TRUE</b> (updateCmr).
     * @param boolean               $isAutomated     [Optional]<br>For whitelist-flow messages, indicates whether the
     *                                               message is being assigned manually or via EMSA automation.
     *                                               Default <b>FALSE</b>.
     * @param int                   $fromQueueId     [Optional]<br>EMSA message queue ID the message is being assigned
     *                                               from.  Default <b>ENTRY_STATUS</b> (Entry queue).
     * @param int                   $assignToQueueId [Optional]<br>EMSA message queue ID the message should be assigned
     *                                               to.  Default <b>ASSIGNED_STATUS</b> (Assigned queue).
     * @param int                   $assignChannel   [Optional]<br><b>MessageProcessingUtils::ASSIGNMENT_CHANNEL_WHITELIST</b>
     *                                               indicates normal whitelist message assignment (default);
     *                                               <b>MessageProcessingUtils::ASSIGNMENT_CHANNEL_GRAYLIST</b>
     *                                               indicates processing graylist message assignment.
     *
     * @return int ID of the Person the record was assigned to in the target Application.  Returns <b>NULL</b> in case
     *             of unsuccessful assignment.
     */
    public function processAssignmentReturn(
        EventLogMessage $eventLogMessage,
        EmsaMessage $emsaMessage,
        SimpleXMLElement $assignedXML,
        SimpleXMLElement $appResponseXML,
        $smAction,
        EmsaCompareNedssEvent $nedssEvent = null,
        $targetEventId = null,
        $targetResultId = null,
        $isUpdate = false,
        $isAutomated = false,
        $fromQueueId = ENTRY_STATUS,
        $assignToQueueId = ASSIGNED_STATUS,
        $assignChannel = self::ASSIGNMENT_CHANNEL_WHITELIST
    )
    {
        $auditId = null;
        $appClient = $emsaMessage->getAppClient();
        $appClientName = $appClient->getAppName();
        $systemMessageId = $emsaMessage->getSystemMessageId();
        $assignedApplicationXMLStr = $assignedXML->asXML();
        $assignmentActionStr = ($isUpdate) ? 'Update Record' : 'Add Record';
        $affectedPersonId = null;

        if ($isUpdate) {
            $originalApplicationXMLStr = $nedssEvent->getAppRecordXML()->asXML();
        } else {
            $originalApplicationXMLStr = null;
        }

        try {
            $validatedResponse = $appClient->validateResponse($appResponseXML);

            $appResponseRecord = $appClient->getNewAppRecord();
            $appResponseRecord->setAppRecordDocument($appResponseXML->person->personCondition);

            if ($validatedResponse->getLock() === true) {
                // Person we attempted to assign to is locked; queue for retry later
                $auditId = AuditUtils::auditMessage($this->dbConn, $systemMessageId, SystemMessageActions::LOCK_ENCOUNTERED, $fromQueueId);
                AuditUtils::auditXML($this->dbConn, $originalApplicationXMLStr, $assignedApplicationXMLStr, $auditId, $isUpdate);
                $this->logMessageException(
                    $systemMessageId, $auditId, SystemExceptions::UNABLE_TO_ASSIGN_XML_TARGET_APPLICATION, '[' . $assignmentActionStr . '] ' . $validatedResponse->getErrorString(), LOCKED_STATUS
                );
                $eventLogMessage->add(new EventLogNote("A locked record was encountered in $appClientName while attempting to $assignmentActionStr.  Message will be automatically retried later.", 'ui-icon-elrerror'));
            } elseif ($validatedResponse->getStatus() === false) {
                // Application error encountered during web service execution
                $auditId = AuditUtils::auditMessage($this->dbConn, $systemMessageId, SystemMessageActions::EXCEPTION, $fromQueueId);
                AuditUtils::auditXML($this->dbConn, $originalApplicationXMLStr, $assignedApplicationXMLStr, $auditId, $isUpdate);
                $this->logMessageException(
                    $systemMessageId, $auditId, SystemExceptions::UNABLE_TO_ASSIGN_XML_TARGET_APPLICATION, '[' . $assignmentActionStr . '] ' . $validatedResponse->getErrorString()
                );
                $eventLogMessage->add(new EventLogNote("$appClientName error occurred while attempting to $assignmentActionStr.  Message moved to Exception list.", 'ui-icon-elrerror'));
            } else {
                // message assigned successfully
                $auditId = AuditUtils::auditMessage($this->dbConn, $systemMessageId, $smAction, $fromQueueId);
                AuditUtils::auditXML($this->dbConn, $originalApplicationXMLStr, $assignedApplicationXMLStr, $auditId, $isUpdate);

                if ($isUpdate && !empty($targetEventId)) {
                    // if updating, we already know the event ID we're updating
                    $assignedEventId = (int)$targetEventId;
                } else {
                    $assignedEventId = $appResponseRecord->getEventId();
                }

                if ($isUpdate && !empty($targetResultId)) {
                    // if updating an existing lab test result, we already know the lab result ID we're updating
                    $assignedLabId = (int)$targetResultId;
                } else {
                    $assignedLabId = $appResponseRecord->getLastAddedLabResultId();
                }

                $affectedPersonId = (int)filter_var((string)$appResponseXML->person->id, FILTER_SANITIZE_NUMBER_INT);

                // move message to assignment queue
                $sql = 'UPDATE system_messages 
					SET 
						final_status = :assignStatus, 
						status = 0, 
						assigned_date = LOCALTIMESTAMP, 
						event_id = :assignedEventId, 
						lab_result_id = :assignedLabId 
					WHERE id = :systemMessageId;';
                $stmt = $this->dbConn->prepare($sql);
                $stmt->bindValue(':assignStatus', $assignToQueueId, PDO::PARAM_INT);
                $stmt->bindValue(':systemMessageId', $systemMessageId, PDO::PARAM_INT);
                $stmt->bindValue(':assignedEventId', $assignedEventId, PDO::PARAM_INT);
                $stmt->bindValue(':assignedLabId', $assignedLabId, PDO::PARAM_INT);

                if ($stmt->execute()) {
                    if ($isUpdate) {
                        $eventLogMessage->setProcessedSuccessfully(true);
                        $eventLogMessage->add(new EventLogNote('Successfully updated event!', 'ui-icon-elrsuccess'));
                        $eventLogMessage->buildAppRecordLink($appClient, $appResponseRecord->getRecordType(), $appResponseRecord->getRecordNumber(), $assignedEventId, true);
                    } else {
                        $eventLogMessage->setProcessedSuccessfully(true);
                        $eventLogMessage->add(new EventLogNote('New event created!', 'ui-icon-elrsuccess'));
                        $eventLogMessage->buildAppRecordLink($appClient, $appResponseRecord->getRecordType(), $appResponseRecord->getRecordNumber(), $assignedEventId, true);
                    }
                } else {
                    if ($isUpdate) {
                        $eventLogMessage->add(new EventLogNote("Warning:  Successfully updated event, but database errors occurred while updating EMSA.  Please notify a system administrator.  (Event ID $assignedEventId, Lab ID $assignedLabId)", 'ui-icon-elrerror'));
                        $eventLogMessage->buildAppRecordLink($appClient, $appResponseRecord->getRecordType(), $appResponseRecord->getRecordNumber(), $assignedEventId, true);
                    } else {
                        $eventLogMessage->add(new EventLogNote("Warning:  New event successfully created in $appClientName, but database errors occurred while updating EMSA.  Please notify a system administrator.  (Event ID $assignedEventId, Lab ID $assignedLabId)", 'ui-icon-elrerror'));
                        $eventLogMessage->buildAppRecordLink($appClient, $appResponseRecord->getRecordType(), $appResponseRecord->getRecordNumber(), $assignedEventId, true);
                    }
                }

                // if whitelist flow, get notification params & trigger notification,
                // also spool for graylist processing
                if ($assignChannel === self::ASSIGNMENT_CHANNEL_WHITELIST) {
                    $nc = new Notification($this->dbConn);

                    // for notification purposes, get jurisdiction of record that was assigned in the app, 
                    // in case it is different from the EMSA-derived value (e.g. when updating a record)
                    $appJurisdictionId = CodedDataUtils::getCodeIdFromDescription($appClient, 'agency', $appResponseRecord->getJurisdictionName());
                    $assignedSystemJurisdictionId = AppClientUtils::getSystemJurisdictionIdFromApp($this->dbConn, $emsaMessage->getApplicationId(), $appJurisdictionId);
                    $assignedCondition = $appResponseRecord->getConditionName();
                    $recordNumber = $appResponseRecord->getRecordNumber();
                    $investigatorName = ($isUpdate) ? $appResponseRecord->getInvestigator()->getFullName() : null;

                    if ($isUpdate) {
                        if ($nedssEvent->getEventType() === Udoh\Emsa\Constants\AppRecordType::CONTACT_EVENT) {
                            $eventType = 'ContactEvent';
                        } else {
                            $eventType = 'MorbidityEvent';
                        }
                    } else {
                        $eventType = 'MorbidityEvent';
                    }


                    $nc->system_message_id = $systemMessageId;
                    $nc->nedss_event_id = $assignedEventId;
                    $nc->nedss_record_number = $recordNumber;
                    $nc->is_surveillance = $emsaMessage->isSurveillance;
                    $nc->is_immediate = $emsaMessage->isImmediate;
                    $nc->is_state = $emsaMessage->notifyState;
                    $nc->is_pregnancy = $emsaMessage->isPregnancy;
                    $nc->is_automated = $isAutomated;
                    $nc->is_new_cmr = !$isUpdate;
                    $nc->is_event_closed = (($isUpdate && (($nedssEvent->getWorkflowStatus() == 'closed') || ($nedssEvent->getWorkflowStatus() == 'approved_by_lhd'))) ? true : false);
                    $nc->condition = $assignedCondition;
                    $nc->organism = $emsaMessage->masterOrganism;
                    $nc->jurisdiction = $assignedSystemJurisdictionId;
                    $nc->test_type = $emsaMessage->masterTestType;
                    $nc->test_result = $emsaMessage->getMasterTestResult();
                    $nc->result_value = $emsaMessage->getSource()->getResultValue();
                    $nc->investigator = $investigatorName;
                    $nc->master_loinc = $emsaMessage->getMasterLoincCode();
                    $nc->specimen = $emsaMessage->specimenSource;
                    $nc->event_type = $eventType;

                    try {
                        if ($appClient->getTriggerNotifications($this->dbConn)) {
                            $nc->logNotification();  // run rules, generate any appropriate notifications
                        }
                    } catch (\Udoh\Emsa\Exceptions\NotificationDatabaseException $e) {
                        ExceptionUtils::logException($e);
                        $eventLogMessage->add(new EventLogNote('Unexpected exception occurred while trying to generate notifications.', 'ui-icon-elrerror', $e->getMessage()));
                        exit;
                    } catch (Throwable $e) {
                        ExceptionUtils::logException($e);
                        $eventLogMessage->add(new EventLogNote('Unexpected exception occurred while trying to generate notifications.', 'ui-icon-elrerror', $e->getMessage()));
                    }

                    /* if (!$isUpdate) {
                        // only spool request if creating a new CMR
                        // this is now being handled via triggers in the nedss db in EpiTrax
                        // keeping around for legacy purposes just in case
                        GraylistRequestUtils::spoolNewRequest($this->dbConn, $assignedEventId);
                    } */
                }
            }
        } catch (\Udoh\Emsa\Exceptions\AppClientEmptyResponse $ace) {
            // invalid/empty response from Application client
            ExceptionUtils::logException($ace);
            $auditId = AuditUtils::auditMessage($this->dbConn, $systemMessageId, SystemMessageActions::EXCEPTION, $fromQueueId);
            AuditUtils::auditXML($this->dbConn, $originalApplicationXMLStr, $assignedApplicationXMLStr, $auditId, $isUpdate);
            $this->logMessageException(
                $systemMessageId, $auditId, SystemExceptions::SQLA_ERROR, '[' . $assignmentActionStr . '] Invalid/missing response received from ' . $appClientName . ' client.'
            );
            $eventLogMessage->add(new EventLogNote("$appClientName web service error occurred while attempting to $assignmentActionStr.  Message moved to Exception list.", 'ui-icon-elrerror'));
        }

        return $affectedPersonId;
    }

}

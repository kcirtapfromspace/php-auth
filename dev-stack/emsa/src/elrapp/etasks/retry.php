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

use Udoh\Emsa\MessageProcessing\EventLogMessage;
use Udoh\Emsa\MessageProcessing\EventLogNote;

if (!class_exists('\Udoh\Emsa\Auth\Authenticator') || !Udoh\Emsa\Auth\Authenticator::userHasPermission(Udoh\Emsa\Auth\Authenticator::URIGHTS_ADMIN)) {
    ob_end_clean();
    header($_SERVER['SERVER_PROTOCOL'] . " 404 Not Found", TRUE, 404);
    exit;
}

try {
    $retryMsgUtils = new MessageProcessingUtils($adminDbConn, $appClientList);
} catch (Throwable $e) {
    Udoh\Emsa\Utils\ExceptionUtils::logException($e);
    Udoh\Emsa\Utils\DisplayUtils::drawError('Could not complete Bulk Retry operation:  unable to connect to the EMSA database.');
}

$retryMsgs = array();

$retrySql = "SELECT DISTINCT(sm.id) AS id 
             FROM system_messages sm 
             INNER JOIN system_message_exceptions sme ON (sme.system_message_id = sm.id) 
             WHERE (sm.final_status = :exceptionStatus) 
             AND (sm.lab_id = :labId)
             AND (sm.vocab_app_id = 2)
             AND (sme.exception_id = :retryExceptionId) 
             AND ((sm.deleted IS NULL) OR (sm.deleted NOT IN (1, 2)))";

if (!\EmsaUtils::emptyTrim($clean['retry_exception_value'])) {
    $retrySql .= "\nAND (sme.info = :retryExceptionValue)";
}

$retrySql .= ";";

try {
    $retryStmt = $adminDbConn->prepare($retrySql);
    $retryStmt->bindValue(':exceptionStatus', EXCEPTIONS_STATUS, PDO::PARAM_INT);
    $retryStmt->bindValue(':labId', intval($clean['lab_id']), PDO::PARAM_INT);
    $retryStmt->bindValue(':retryExceptionId', intval($clean['retry_exception_id']), PDO::PARAM_INT);
    if (!\EmsaUtils::emptyTrim($clean['retry_exception_value'])) {
        $retryStmt->bindValue(':retryExceptionValue', $clean['retry_exception_value'], PDO::PARAM_STR);
    }

    if ($retryStmt->execute() && ($retryStmt->rowCount() > 0)) {
        while ($retryRow = $retryStmt->fetchObject()) {
            $retryMsgs[] = intval($retryRow->id);
        }
    } else {
        Udoh\Emsa\Utils\DisplayUtils::drawError('Unable to retrieve list of messages to retry.');
    }
} catch (Throwable $e) {
    Udoh\Emsa\Utils\ExceptionUtils::logException($e);
    Udoh\Emsa\Utils\DisplayUtils::drawError('Unable to retrieve list of messages to retry.');
}

if (count($retryMsgs) > 0) {
    $eventLog = new Udoh\Emsa\MessageProcessing\EventLog();

    if (defined("BULK_RETRY_VIA_MIRTH") && (BULK_RETRY_VIA_MIRTH === true)) {
        foreach ($retryMsgs as $retryMsgID) {
            $eventLogMessage = new EventLogMessage($retryMsgID);
            $eventLogMessage->setProcessedSuccessfully(false);
            if ($retryMsgUtils->clearCurrentMessageExceptions($retryMsgID)) {
                try {
                    $markForBulkRetrySQL = "UPDATE system_messages SET deleted = 3 WHERE id = :msgID;";
                    $markForBulkRetryStmt = $adminDbConn->prepare($markForBulkRetrySQL);
                    $markForBulkRetryStmt->bindValue(":msgID", (int)$retryMsgID, PDO::PARAM_INT);
                    if ($markForBulkRetryStmt->execute()) {
                        $eventLogMessage->setProcessedSuccessfully(true);
                        $eventLogMessage->add(new EventLogNote("Message queued for retry.", "ui-icon-elrretry"));
                    }
                } catch (Throwable $e) {
                    Udoh\Emsa\Utils\ExceptionUtils::logException($e);
                }
            }
            $eventLog->add($eventLogMessage);
        }
    } else {
        $retryMsgUtils->bulkRetryWrapper($eventLog, $retryMsgs);
    }

    $eventLog->display();
}

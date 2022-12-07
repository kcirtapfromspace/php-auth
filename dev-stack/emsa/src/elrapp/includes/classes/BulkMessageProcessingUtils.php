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

use Udoh\Emsa\MessageProcessing\EventLog;
use Udoh\Emsa\MessageProcessing\EventLogMessage;
use Udoh\Emsa\MessageProcessing\EventLogNote;

/**
 * Functions for handling bulk message processing
 *
 * @author Josh Ridderhoff <jridderhoff@utah.gov>
 */
class BulkMessageProcessingUtils
{

    /**
     * Wrapper function to bulk-move multiple EMSA messages from one queue to another
     *
     * @param EventLog               $eventLog
     * @param MessageProcessingUtils $msgUtils         MessageProcessingUtils instance
     * @param int[]                  $systemMessageIds Array of EMSA system message IDs to move
     * @param int                    $fromFinalStatus  ID of the queue the messages are being moved from
     * @param int                    $toFinalStatus    ID of the queue the messages are being moved to
     * @param string                 $comments         [Optional]<br>Comments detailing why the message was moved
     */
    public static function moveWrapper(EventLog $eventLog, MessageProcessingUtils $msgUtils, array $systemMessageIds, $fromFinalStatus, $toFinalStatus, $comments = null)
    {
        $eventLog->add(new EventLogNote((int) count($systemMessageIds) . ' messages found to move...', 'ui-icon-elrmove'));

        foreach ($systemMessageIds as $systemMessageId) {
            $eventLogMessage = new EventLogMessage($systemMessageId);
            try {
                if ($msgUtils->moveMessageToQueue((int) $systemMessageId, $fromFinalStatus, $toFinalStatus, \Udoh\Emsa\Constants\SystemMessageActions::MESSAGE_MOVED_BY_USER, $comments)) {
                    $eventLogMessage->setProcessedSuccessfully(true);
                    $eventLogMessage->add(new EventLogNote('Message successfully moved!', 'ui-icon-elrmove'));
                } else {
                    $eventLogMessage->setProcessedSuccessfully(false);
                    $eventLogMessage->add(new EventLogNote('Error:  Could not move message.', 'ui-icon-elrerror'));
                }
            } catch (Throwable $e) {
                \Udoh\Emsa\Utils\ExceptionUtils::logException($e);
                $eventLogMessage->setProcessedSuccessfully(false);
                $eventLogMessage->add(new EventLogNote('Error:  Could not move message.', 'ui-icon-elrerror', $e->getMessage()));
            } finally {
                $eventLog->add($eventLogMessage);
            }
        }
    }

    /**
     * Wrapper function to bulk-delete multiple EMSA messages
     *
     * @param EventLog               $eventLog
     * @param MessageProcessingUtils $msgUtils         MessageProcessingUtils instance
     * @param int[]                  $systemMessageIds Array of EMSA system message IDs to delete
     * @param int                    $fromFinalStatus  ID of the queue the messages are being deleted from
     * @param string                 $comments         [Optional]<br>Comments detailing why the message was moved
     */
    public static function deleteWrapper(EventLog $eventLog, MessageProcessingUtils $msgUtils, array $systemMessageIds, $fromFinalStatus, $comments = null)
    {
        $eventLog->add(new EventLogNote((int) count($systemMessageIds) . ' messages found to delete...', 'ui-icon-elrdelete'));

        foreach ($systemMessageIds as $systemMessageId) {
            $eventLogMessage = new EventLogMessage($systemMessageId);
            if ($msgUtils->markMessageDeleted((int) $systemMessageId, $fromFinalStatus, $comments)) {
                $eventLogMessage->setProcessedSuccessfully(true);
                $eventLogMessage->add(new EventLogNote('Message successfully deleted!', 'ui-icon-elrclose'));
            } else {
                $eventLogMessage->setProcessedSuccessfully(false);
                $eventLogMessage->add(new EventLogNote('Error:  Could not delete message.', 'ui-icon-elrerror'));
            }
            $eventLog->add($eventLogMessage);
        }
    }

}

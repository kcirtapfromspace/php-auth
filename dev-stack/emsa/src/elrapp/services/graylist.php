<?php
/*
  Copyright (c) 2016 Utah Department of Technology Services and Utah Department of Health

  This program is free software: you can redistribute it and/or modify
  it under the terms of the GNU Affero General Public License as published by
  the Free Software Foundation, either version 3 of the License, or
  (at your option) any later version.

  This program is distributed in the hope that it will be useful,
  but WITHOUT ANY WARRANTY; without even the implied warranty of
  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
  GNU Affero General Public License for more details.

  You should have received a copy of the GNU Affero General Public License
  along with this program.  If not, see <http://www.gnu.org/licenses/>.

  In addition, this program is also subject to certain additional terms. You should
  have received a copy of these additional terms immediately following the terms and
  conditions of the GNU Affero General Public License which accompanied the program.
  If not, please request a copy in writing from the Utah Department of Health at
  the address below.

  If you have questions concerning this license or the applicable additional terms,
  you may contact us in writing at:
  Utah Department of Health, P.O. Box 141010, Salt Lake City, UT 84114-1010 USA.
 */

use Udoh\Emsa\MessageProcessing\EventLogMessage;
use Udoh\Emsa\Utils\AuditUtils;
use Udoh\Emsa\Utils\DisplayUtils;
use Udoh\Emsa\Utils\ExceptionUtils;

// prevent caching...
header('Expires: Mon, 26 Jul 1997 05:00:00 GMT');
header('Cache-Control: no-cache');
header('Pragma: no-cache');

include __DIR__ . '/../includes/app_config.php';

session_write_close(); // done writing to session; prevent blocking

$cleanAppRecordId = (int) filter_input(INPUT_GET, 'event_id', FILTER_SANITIZE_NUMBER_INT);

try {
    // graylisting done for primary authenticating application
    $triggerEvent = $authClient->getRecord($cleanAppRecordId);
    $triggerPerson = $authClient->getPerson($triggerEvent->getPersonId());

    $cleanPatientFirstName = $triggerPerson->getFirstName();
    $cleanPatientLastName = $triggerPerson->getLastName();
    $cleanPatientMiddleName = $triggerPerson->getMiddleName();
    $cleanPatientDob = $triggerPerson->getDateOfBirth();
    
    $cleanCondition = $triggerEvent->getConditionName();
    $cleanEventDate = $triggerEvent->getEventDate();
} catch (Throwable $e) {
    error_log("Unable to start Graylist Request from spooler... record ID $cleanAppRecordId not found.");
    exit;
}


// create a new GraylistRequest object
try {
    $dbConn = $emsaDbFactory->getConnection();
    $msgUtils = new MessageProcessingUtils($dbConn, $appClientList);

    $grayRequest = new GraylistRequest(
            $dbConn, $cleanAppRecordId, $cleanCondition, null, $cleanPatientFirstName, $cleanPatientLastName, $cleanPatientMiddleName, $cleanPatientDob, $cleanEventDate);

    $requestId = $grayRequest->getRequestId();

    if ($requestId > 0) {
        GraylistRequestAuditor::auditRequest($dbConn, $requestId, \Udoh\Emsa\Constants\SystemMessageActions::GRAYLIST_REQUEST_STATUS_CHANGE, GRAY_PENDING_STATUS, 'New request created');
    }
} catch (\BadMethodCallException $be) {
    // could not create a GraylistRequest object due to missing required fields; stop here
    ExceptionUtils::logException($be);
    DisplayUtils::drawError($be->getMessage());
    exit;
} catch (Throwable $e) {
    ExceptionUtils::logException($e);
    DisplayUtils::drawError($e->getMessage());
}


// search graylist pool & run graylist rules
try {
    $minScore = 70;
    $matchSet = GraylistRequestUtils::findGraylistMatches($dbConn, $appClientList, $grayRequest, $minScore);
    $matchSet->runGraylistRules($dbConn, $grayRequest);

    // process matches
    if ($matchSet->hasMatches()) {
        foreach ($matchSet->getGraylistMatches() as $graylistMatchMessage) {
            $eventLogMessage = new EventLogMessage($graylistMatchMessage->getEmsaMessage()->getSystemMessageId());

            if (GraylistRequestUtils::matchAlreadyAssigned($dbConn, $graylistMatchMessage->getEmsaMessage(), $grayRequest->getAppRecordId())) {
                GraylistRequestAuditor::auditRequest($dbConn, $requestId, \Udoh\Emsa\Constants\SystemMessageActions::MESSAGE_PROCESSED_BY_GRAYLIST, GRAY_UNPROCESSABLE_STATUS, 'Matched message has been previously appended to this event.'
                );
            } else {
                // clone & attempt to assign any matches from the graylist pool
                unset($currentMessageId);
                $currentMessageId = $msgUtils->copyMessageToQueue($graylistMatchMessage->getEmsaMessage()->getSystemMessageId(), UNPROCESSED_STATUS, 'Graylist Match');
                AuditUtils::auditMessage($dbConn, $currentMessageId, \Udoh\Emsa\Constants\SystemMessageActions::MESSAGE_PROCESSED_BY_GRAYLIST, UNPROCESSED_STATUS, 'Matched Graylist Rules.');
                $msgUtils->retryMessage($currentMessageId, $eventLogMessage, UNPROCESSED_STATUS);
            }
        }

        $grayRequest->updateRequestStatus(GRAY_PROCESSED_STATUS);
        GraylistRequestAuditor::auditRequest($dbConn, $requestId, \Udoh\Emsa\Constants\SystemMessageActions::GRAYLIST_REQUEST_STATUS_CHANGE, GRAY_PROCESSED_STATUS, 'Graylist Pool matches found and processed successfully');
    } else {
        $grayRequest->updateRequestStatus(GRAY_PROCESSED_STATUS);
        GraylistRequestAuditor::auditRequest($dbConn, $requestId, \Udoh\Emsa\Constants\SystemMessageActions::GRAYLIST_REQUEST_STATUS_CHANGE, GRAY_PROCESSED_STATUS, 'No matches found in Graylist Pool');
    }
} catch (Throwable $e) {
    ExceptionUtils::logException($e);
    $grayRequest->updateRequestStatus(GRAY_EXCEPTION_STATUS);

    try {
        GraylistRequestAuditor::auditRequest($dbConn, $requestId, \Udoh\Emsa\Constants\SystemMessageActions::GRAYLIST_REQUEST_STATUS_CHANGE, GRAY_EXCEPTION_STATUS, $e->getMessage());
    } catch (Throwable $eg) {
        ExceptionUtils::logException($eg);
    }

    print_r($e);
}

$dbConn = null;
$emsaDbFactory = null;

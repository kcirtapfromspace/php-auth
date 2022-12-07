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

use Udoh\Emsa\Constants\MessageProcessingActions;
use Udoh\Emsa\Constants\SystemExceptions;
use Udoh\Emsa\Constants\SystemMessageActions;
use Udoh\Emsa\MessageProcessing\EventLogExceptionDetailSet;
use Udoh\Emsa\MessageProcessing\EventLogMessage;
use Udoh\Emsa\MessageProcessing\EventLogNote;
use Udoh\Emsa\Utils\AuditUtils;
use Udoh\Emsa\Utils\DateTimeUtils;

if (!class_exists('Udoh\Emsa\Auth\Authenticator')) {
    ob_end_clean();
    header($_SERVER['SERVER_PROTOCOL'] . " 404 Not Found", TRUE, 404);
    exit;
}

$cleanActions = array();

// create EventLog to record message processing actions for rolled-up display later
$eventLog = new Udoh\Emsa\MessageProcessing\EventLog();

try {
    $dbConn = $emsaDbFactory->getConnection();
    $msgUtils = new MessageProcessingUtils($dbConn, $appClientList);
} catch (Throwable $e) {
    Udoh\Emsa\Utils\ExceptionUtils::logException($e);
    $eventLog->add(new EventLogNote('Message could not be processed; unable to connect to the EMSA database.', 'ui-icon-elrerror'));
}

// Check for bulk message processing
$bulkAction = null;
$bulkMessageIds = array();
$bulkTarget = null;
$bulkQaFlagWhitelisted = null;

switch (filter_input(INPUT_POST, 'bulk_action', FILTER_SANITIZE_STRING)) {
    case "bulk_retry":
        $bulkAction = Udoh\Emsa\Constants\MessageProcessingActions::BULK_ACTION_RETRY;
        break;
    case "bulk_delete":
        $bulkAction = Udoh\Emsa\Constants\MessageProcessingActions::BULK_ACTION_DELETE;
        break;
    case "bulk_move":
        $bulkAction = Udoh\Emsa\Constants\MessageProcessingActions::BULK_ACTION_MOVE;
        break;
    case "bulk_qa_flag":
        $bulkAction = Udoh\Emsa\Constants\MessageProcessingActions::BULK_ACTION_QA_FLAG;
        break;
    default:
        $bulkAction = null;
}

$bulkMessageIds = filter_input(INPUT_POST, 'bulk_ids', FILTER_SANITIZE_NUMBER_INT, FILTER_REQUIRE_ARRAY);
$bulkQaFlag = (int) filter_input(INPUT_POST, 'bulk_qa_flag_id', FILTER_SANITIZE_NUMBER_INT);
$bulkQaFlagOtherReason = (string) filter_input(INPUT_POST, 'bulk_flag_other_reason', FILTER_SANITIZE_STRING);
$bulkQaFlagErrorReason = (string) filter_input(INPUT_POST, 'bulk_flag_de_error_type', FILTER_SANITIZE_STRING);

$bulkValidFlags = array(
    EMSA_FLAG_CLEAN_DATA,
    EMSA_FLAG_DE_ERROR,
    EMSA_FLAG_DE_NEEDFIX,
    EMSA_FLAG_DE_OTHER,
    EMSA_FLAG_FIX_DUPLICATE,
    EMSA_FLAG_INVESTIGATION_COMPLETE,
    EMSA_FLAG_QA_CODING,
    EMSA_FLAG_QA_MANDATORY,
    EMSA_FLAG_QA_MQF
);

if (!empty($bulkAction) && !empty($bulkMessageIds)) {
    if ($bulkAction === Udoh\Emsa\Constants\MessageProcessingActions::BULK_ACTION_MOVE) {
        $bulkTarget = (int) filter_input(INPUT_POST, 'bulk_target', FILTER_SANITIZE_NUMBER_INT);
    }

    if (($bulkAction === Udoh\Emsa\Constants\MessageProcessingActions::BULK_ACTION_QA_FLAG) && !empty($bulkQaFlag) && in_array($bulkQaFlag, $bulkValidFlags)) {
        $bulkQaFlagWhitelisted = $bulkQaFlag;
    }
}

if (!empty($bulkAction) && !empty($bulkMessageIds)) {
    if ($bulkAction === Udoh\Emsa\Constants\MessageProcessingActions::BULK_ACTION_RETRY) {
        $msgUtils->bulkRetryWrapper($eventLog, $bulkMessageIds);  // bulk retry messages
    }

    if (($bulkAction === Udoh\Emsa\Constants\MessageProcessingActions::BULK_ACTION_DELETE) && Udoh\Emsa\Auth\Authenticator::userHasPermission(Udoh\Emsa\Auth\Authenticator::URIGHTS_ACTION_DELETE)) {
        BulkMessageProcessingUtils::deleteWrapper($eventLog, $msgUtils, $bulkMessageIds, $type, 'Deleted by Bulk Delete');  // bulk delete messages
    }

    if (($bulkAction === Udoh\Emsa\Constants\MessageProcessingActions::BULK_ACTION_MOVE) && Udoh\Emsa\Auth\Authenticator::userHasPermission(Udoh\Emsa\Auth\Authenticator::URIGHTS_ACTION_MOVE)) {
        BulkMessageProcessingUtils::moveWrapper($eventLog, $msgUtils, $bulkMessageIds, $type, $bulkTarget, 'Moved by Bulk Move');  // bulk move
    }

    if (($bulkAction === Udoh\Emsa\Constants\MessageProcessingActions::BULK_ACTION_QA_FLAG) && !empty($bulkQaFlagWhitelisted)) {
        $validBulkQAFlag = true;
        if (($bulkQaFlagWhitelisted === EMSA_FLAG_DE_OTHER) && empty($bulkQaFlagOtherReason)) {
            $validBulkQAFlag = false;
        }
        if (($bulkQaFlagWhitelisted === EMSA_FLAG_DE_ERROR) && empty($bulkQaFlagErrorReason)) {
            $validBulkQAFlag = false;
        }

        if ($validBulkQAFlag) {
            $msgUtils->bulkQaFlagWrapper($eventLog, $bulkMessageIds, $type, $bulkQaFlagWhitelisted, $bulkQaFlagOtherReason, $bulkQaFlagErrorReason);
        }
    }
}



// Check for standard, per-message (non-bulk) message processing actions
$ampActionRaw = null;
$ampAction = null;
$ampId = null;
$ampAutomated = false;
$ampOverride = false;
$ampOverrideEventId = null;
$ampOverridePersonId = null;

// there are a few cases we get the ID & action from GET, and a few times we get it from POST
// icky, I know; someday... 
if (isset($_GET['id'])) {
    $ampId = (int) filter_input(INPUT_GET, 'id', FILTER_SANITIZE_NUMBER_INT);
} elseif (isset($_POST['id'])) {
    $ampId = (int) filter_input(INPUT_POST, 'id', FILTER_SANITIZE_NUMBER_INT);
}

if (isset($_GET['emsa_action'])) {
    $ampActionRaw = (string) filter_input(INPUT_GET, 'emsa_action', FILTER_SANITIZE_STRING);
} elseif (isset($_POST['emsa_action'])) {
    $ampActionRaw = (string) filter_input(INPUT_POST, 'emsa_action', FILTER_SANITIZE_STRING);
}

switch ($ampActionRaw) {
    case "elrauto":
        if ($type === ENTRY_STATUS) {
            // elrauto from MP still passes ENTRY_STATUS for the $type value; override to UNPROCESSED_STATUS
            // otherwise, keep as-is (e.g. to allow reprocessing of LOCKED_STATUS messages)
            $type = UNPROCESSED_STATUS;
        }
        $ampAction = Udoh\Emsa\Constants\MessageProcessingActions::ACTION_AUTOMATED_PROCESSING;
        break;
    case "addnew":
        $ampAction = Udoh\Emsa\Constants\MessageProcessingActions::ACTION_ADD_RECORD;
        break;
    case "update":
        $ampAction = Udoh\Emsa\Constants\MessageProcessingActions::ACTION_UPDATE_RECORD;
        break;
    case "bulk_addnew":
        $ampAction = Udoh\Emsa\Constants\MessageProcessingActions::BULK_ACTION_ADD_RECORD;
        break;
    case "bulk_update":
        $ampAction = Udoh\Emsa\Constants\MessageProcessingActions::BULK_ACTION_UPDATE_RECORD;
        break;
    case "edit":
        $ampAction = Udoh\Emsa\Constants\MessageProcessingActions::ACTION_EDIT;
        break;
    case "save":
        $ampAction = Udoh\Emsa\Constants\MessageProcessingActions::ACTION_SAVE;
        break;
    case "move":
        $ampAction = Udoh\Emsa\Constants\MessageProcessingActions::ACTION_MOVE;
        break;
    case "delete":
        $ampAction = Udoh\Emsa\Constants\MessageProcessingActions::ACTION_DELETE;
        break;
    case "retry":
        $ampAction = Udoh\Emsa\Constants\MessageProcessingActions::ACTION_RETRY;
        break;
    case "set_flag":
        $ampAction = Udoh\Emsa\Constants\MessageProcessingActions::ACTION_SET_FLAG;
        break;
    case "unset_flag":
        $ampAction = Udoh\Emsa\Constants\MessageProcessingActions::ACTION_UNSET_FLAG;
        break;
    case "add_qa_comment":
        $ampAction = Udoh\Emsa\Constants\MessageProcessingActions::ACTION_QA_COMMENT;
        break;
    default:
        $ampAction = null;
}

if (!empty($ampAction)) {
    try {
        $emsaMessage = new EmsaMessage($dbConn, $appClientList, $ampId, false);
    } catch (Udoh\Emsa\Exceptions\EmsaMessageNotFoundException $nfe) {
        // message not found
        Udoh\Emsa\Utils\ExceptionUtils::logException($nfe);
        $msgUtils->logMessageException(
                $ampId, 
                Udoh\Emsa\Utils\AuditUtils::auditMessage($dbConn, $ampId, Udoh\Emsa\Constants\SystemMessageActions::PENDING, $type), 
                Udoh\Emsa\Constants\SystemExceptions::ENTRY_QUEUE_EXCEPTION, 
                $nfe->getMessage(), 
                NEDSS_EXCEPTION_STATUS
        );
        $eventLog->add(new EventLogNote('Message moved to Pending list.', 'ui-icon-elrerror', $nfe->getMessage()));
    } catch (Udoh\Emsa\Exceptions\VocabularyException $ave) {
        $msgUtils->logMessageException(
                $ampId, 
                Udoh\Emsa\Utils\AuditUtils::auditMessage($dbConn, $ampId, Udoh\Emsa\Constants\SystemMessageActions::EXCEPTION, $type), 
                Udoh\Emsa\Constants\SystemExceptions::UNABLE_TO_FIND_APPLICATION_CODE,
                $ave->getMessage(), 
                EXCEPTIONS_STATUS
        );
        $eventLog->add(new EventLogNote('Message moved to Exceptions list.', 'ui-icon-elrerror', $ave->getMessage()));
    } catch (Throwable $e) {
        Udoh\Emsa\Utils\ExceptionUtils::logException($e);

        if ($ampId > 0) {
            try {
                $msgUtils->logMessageException(
                    $ampId,
                    Udoh\Emsa\Utils\AuditUtils::auditMessage($dbConn, $ampId, Udoh\Emsa\Constants\SystemMessageActions::EXCEPTION, $type),
                    Udoh\Emsa\Constants\SystemExceptions::ENTRY_QUEUE_EXCEPTION,
                    $e->getMessage(),
                    EXCEPTIONS_STATUS
                );
            } catch (Throwable $e2) {
                Udoh\Emsa\Utils\ExceptionUtils::logException($e2);
            }
        }

        $eventLog->add(new EventLogNote('Message could not be processed; an unexpected error occurred:', 'ui-icon-elrerror', $e->getMessage()));
    }
}


$ampOverrideEventId = (int) filter_input(INPUT_POST, 'override_event', FILTER_SANITIZE_NUMBER_INT);
$ampOverridePersonId = (int) filter_input(INPUT_POST, 'override_person', FILTER_SANITIZE_NUMBER_INT);
$rawOverrideFlag = (int) filter_input(INPUT_POST, 'emsa_override', FILTER_SANITIZE_NUMBER_INT);

if ($rawOverrideFlag === 1) {
    $ampOverride = true;
}

$cleanActions['override_surveillance'] = MessageProcessingUtils::SURV_OVERRIDE_RULES;  // allow rules to determine surveillance event status
if (isset($_REQUEST['override_surveillance'])) {
    if (filter_var(trim($_REQUEST['override_surveillance']), FILTER_SANITIZE_NUMBER_INT) == 1) {
        $cleanActions['override_surveillance'] = MessageProcessingUtils::SURV_OVERRIDE_YES;  // override to make a surveillance event
    }
    if (filter_var(trim($_REQUEST['override_surveillance']), FILTER_SANITIZE_NUMBER_INT) == 2) {
        $cleanActions['override_surveillance'] = MessageProcessingUtils::SURV_OVERRIDE_NO;  // override to investigated event
    }
}

// Automated Message Processing
if ($ampAction === Udoh\Emsa\Constants\MessageProcessingActions::ACTION_AUTOMATED_PROCESSING) {
    // ensure automation is enabled on this environment
    if (AUTOMATION_ENABLED !== true) {
        ob_clean();
        header($_SERVER['SERVER_PROTOCOL'] . " 200 OK", TRUE, 200);

        try {
            $msgUtils->moveMessageToQueue($emsaMessage->getSystemMessageId(), $type, ENTRY_STATUS, Udoh\Emsa\Constants\SystemMessageActions::MESSAGE_MOVED_BY_WHITELIST_RULE, 'Automated Message Processing not enabled for this installation.  Moved to Entry queue.');
        } catch (Throwable $e) {
            Udoh\Emsa\Utils\ExceptionUtils::logException($e);
        }

        exit;
    }

    $ampAutomated = true;

    // check for a valid ID passed
    if (isset($emsaMessage) && ($emsaMessage->getSystemMessageId() > 0)) {
        try {
            // log that autoprocessing has started...
            Udoh\Emsa\Utils\AuditUtils::auditMessage($dbConn, $emsaMessage->getSystemMessageId(), Udoh\Emsa\Constants\SystemMessageActions::MESSAGE_AUTOPROCESSED, $type, 'Message processing started...');

            // if valid specimen source indicated, check for message workflow destination;
            // divert to QA, Pending, or Semi-Automated Entry if specified
            if ($emsaMessage->validSpecimen === SPECIMEN_VALID) {
                $messageWorkflow = $emsaMessage->getMessageDestination();

                if (Udoh\Emsa\Utils\AutomationUtils::isPendingByName($dbConn, $emsaMessage->getPerson()->getLastName(), $emsaMessage->getPerson()->getFirstName())) {
                    // internal test messages from select senders... shunt to 'Pending' queue
                    ob_clean();
                    header($_SERVER['SERVER_PROTOCOL'] . " 200 OK", true, 200);
                    $msgUtils->moveMessageToQueue($emsaMessage->getSystemMessageId(), $type, NEDSS_EXCEPTION_STATUS, Udoh\Emsa\Constants\SystemMessageActions::MESSAGE_MOVED_BY_WHITELIST_RULE, 'Patient name indicates reporter-specific internal process.  Moved to Pending queue.');
                    exit;
                } elseif ($messageWorkflow === QA_STATUS) {
                    ob_clean();
                    header($_SERVER['SERVER_PROTOCOL'] . " 200 OK", true, 200);
                    $msgUtils->moveMessageToQueue($emsaMessage->getSystemMessageId(), $type, $messageWorkflow, Udoh\Emsa\Constants\SystemMessageActions::MESSAGE_MOVED_BY_WHITELIST_RULE, 'Flagged for QA Review by Message Workflow rules.');
                    exit;
                } elseif ($messageWorkflow === SEMI_AUTO_STATUS) {
                    if (Udoh\Emsa\Utils\AutomationUtils::isKnownNegative($emsaMessage)) {
                        Udoh\Emsa\Utils\AuditUtils::auditMessage($dbConn, $emsaMessage->getSystemMessageId(), Udoh\Emsa\Constants\SystemMessageActions::MESSAGE_AUTOPROCESSED, $type, 'Message Workflow rules specify Semi-Automated Entry, but Condition known and Test Result is Negative; continuing to process message.');
                    } else {
                        ob_clean();
                        header($_SERVER['SERVER_PROTOCOL'] . " 200 OK", true, 200);
                        $msgUtils->moveMessageToQueue($emsaMessage->getSystemMessageId(), $type, $messageWorkflow, Udoh\Emsa\Constants\SystemMessageActions::MESSAGE_MOVED_BY_WHITELIST_RULE, 'Flagged for Semi-Automated Entry by Message Workflow rules');
                        exit;
                    }
                }
            }
        } catch (Throwable $e) {
            Udoh\Emsa\Utils\ExceptionUtils::logException($e);
            ob_clean();
            $msgUtils->logMessageException(
                    $emsaMessage->getSystemMessageId(), 
                    Udoh\Emsa\Utils\AuditUtils::auditMessage($dbConn, $emsaMessage->getSystemMessageId(), Udoh\Emsa\Constants\SystemMessageActions::PENDING, $type), 
                    Udoh\Emsa\Constants\SystemExceptions::ENTRY_QUEUE_EXCEPTION, 
                    $e->getMessage(),
                    NEDSS_EXCEPTION_STATUS
            );
            header($_SERVER['SERVER_PROTOCOL'] . " 200 OK", TRUE, 200);
            exit;
        }

        if ($emsaMessage->validSpecimen === SPECIMEN_VALID) {
            try {
                // conduct person search, get back result arrays
                Udoh\Emsa\Utils\AuditUtils::auditMessage($dbConn, $emsaMessage->getSystemMessageId(), Udoh\Emsa\Constants\SystemMessageActions::MESSAGE_AUTOPROCESSED, $type, 'Initiating Person Search...');
                $peopleSearchResults = Udoh\Emsa\Utils\AppClientUtils::getPeopleSearchResults($emsaMessage, $dbConn, true, 70);
            } catch (Udoh\Emsa\Exceptions\PeopleSearchTooManyResults $ptm) {
                // too many search results; leave in Entry
                ob_clean();
                header($_SERVER['SERVER_PROTOCOL'] . " 200 OK", true, 200);

                try {
                    $msgUtils->moveMessageToQueue($emsaMessage->getSystemMessageId(), $type, ENTRY_STATUS, Udoh\Emsa\Constants\SystemMessageActions::MESSAGE_MOVED_BY_WHITELIST_RULE, 'Too many people match search results.  Moved to Entry queue.');
                } catch (Throwable $e) {
                    Udoh\Emsa\Utils\ExceptionUtils::logException($e);
                }

                exit;
            } catch (Udoh\Emsa\Exceptions\PeopleSearchMissingRequiredFields $pe) {
                Udoh\Emsa\Utils\ExceptionUtils::logException($pe);
                ob_clean();
                header($_SERVER['SERVER_PROTOCOL'] . " 200 OK", true, 200);

                try {
                    $msgUtils->moveMessageToQueue($emsaMessage->getSystemMessageId(), $type, ENTRY_STATUS, Udoh\Emsa\Constants\SystemMessageActions::MESSAGE_MOVED_BY_WHITELIST_RULE, 'Unable to retrieve Person Search results (Missing Required Fields for People Search).  Moved to Entry queue.');
                } catch (Throwable $e) {
                    Udoh\Emsa\Utils\ExceptionUtils::logException($e);
                }

                exit;
            } catch (Throwable $e) {
                Udoh\Emsa\Utils\ExceptionUtils::logException($e);
                ob_clean();
                header($_SERVER['SERVER_PROTOCOL'] . " 200 OK", true, 200);

                try {
                    $msgUtils->moveMessageToQueue($emsaMessage->getSystemMessageId(), $type, UNPROCESSED_STATUS, Udoh\Emsa\Constants\SystemMessageActions::MESSAGE_MOVED_BY_WHITELIST_RULE, 'Unable to retrieve Person Search results (reason:  ' . Udoh\Emsa\Utils\DisplayUtils::xSafe($e->getMessage()) . ').  Returned to Unprocessed queue.');
                } catch (Throwable $e) {
                    Udoh\Emsa\Utils\ExceptionUtils::logException($e);
                }

                exit;
            }

            if ((count($peopleSearchResults['auto_match']) > 0) && (count($peopleSearchResults['fuzzy_match']) === 0)) {
                // if all auto-match results (or only auto-match results and no-match results), transmute to 'update' w/ all people IDs from auto-matches
                $ampAction = Udoh\Emsa\Constants\MessageProcessingActions::ACTION_UPDATE_RECORD;
                $_REQUEST['match_persons'] = implode('|', $peopleSearchResults['auto_match']);
                Udoh\Emsa\Utils\AuditUtils::auditMessage($dbConn, $emsaMessage->getSystemMessageId(), Udoh\Emsa\Constants\SystemMessageActions::MESSAGE_AUTOPROCESSED, $type, 'Found exact Person Search matches!');
            } elseif ((count($peopleSearchResults['no_match']) > 0) && (count($peopleSearchResults['auto_match']) === 0) && (count($peopleSearchResults['fuzzy_match']) === 0)) {
                // if all no-match results, add new person & new CMR
                $ampAction = Udoh\Emsa\Constants\MessageProcessingActions::ACTION_ADD_RECORD;
                Udoh\Emsa\Utils\AuditUtils::auditMessage($dbConn, $emsaMessage->getSystemMessageId(), Udoh\Emsa\Constants\SystemMessageActions::MESSAGE_AUTOPROCESSED, $type, 'No Person Search matches with 4+ match quality; creating new CMR');
            } elseif ((count($peopleSearchResults['no_match']) === 0) && (count($peopleSearchResults['auto_match']) === 0) && (count($peopleSearchResults['fuzzy_match']) === 0)) {
                // no results, add new person & new CMR
                $ampAction = Udoh\Emsa\Constants\MessageProcessingActions::ACTION_ADD_RECORD;
                Udoh\Emsa\Utils\AuditUtils::auditMessage($dbConn, $emsaMessage->getSystemMessageId(), Udoh\Emsa\Constants\SystemMessageActions::MESSAGE_AUTOPROCESSED, $type, 'No Person Search matches found; creating new CMR');
            } else {
                // if any fuzzy match results, do nothing & leave in Entry queue
                ob_clean();
                header($_SERVER['SERVER_PROTOCOL'] . " 200 OK", true, 200);

                try {
                    $msgUtils->moveMessageToQueue($emsaMessage->getSystemMessageId(), $type, ENTRY_STATUS, Udoh\Emsa\Constants\SystemMessageActions::MESSAGE_MOVED_BY_WHITELIST_RULE, 'Ambiguous Person Search result.  Moved to Entry queue.');
                } catch (Throwable $e) {
                    Udoh\Emsa\Utils\ExceptionUtils::logException($e);
                }

                exit;
            }
        }
    } else {
        ob_clean();
        header($_SERVER['SERVER_PROTOCOL'] . " 400 Bad Request", TRUE, 400);  // id passed to automated processor not a valid integer
        exit;
    }
} else {
    $ampAutomated = false;
}

if (isset($emsaMessage) && !empty($emsaMessage->getSystemMessageId())) {
    // valid ID & action passed

    if ($msgUtils->isMessageUnprocessed($emsaMessage->getSystemMessageId(), $type)) {
        // message has not been previously processed, continue with specified action

        $cleanActions['target'] = ((isset($_REQUEST['target']) && (strlen(trim($_REQUEST['target'])) > 0)) ? trim($_REQUEST['target']) : "NULL");
        $cleanActions['info'] = ((isset($_REQUEST['info']) && (strlen(trim($_REQUEST['info'])) > 0)) ? trim($_REQUEST['info']) : null);
        $cleanActions['match_persons'] = ((isset($_REQUEST['match_persons']) && (strlen(trim($_REQUEST['match_persons'])) > 0)) ? trim($_REQUEST['match_persons']) : false);

        if ($ampAction === Udoh\Emsa\Constants\MessageProcessingActions::ACTION_EDIT) {
            // draw 'Edit' form
            include __DIR__ . '/edit.php';
        } elseif ($ampAction === Udoh\Emsa\Constants\MessageProcessingActions::ACTION_SAVE) {
            // save 'Edit' changes
            $eventLogMessage = new EventLogMessage($emsaMessage->getSystemMessageId());

            // get master xml from database to make changes
            $editMasterXmlStr = null;
            try {
                $editSql = "SELECT master_xml 
						FROM system_messages 
						WHERE id = :systemMessageId;";
                $editStmt = $dbConn->prepare($editSql);
                $editStmt->bindValue(':systemMessageId', intval($emsaMessage->getSystemMessageId()), PDO::PARAM_INT);
                if ($editStmt->execute()) {
                    $editRow = $editStmt->fetchObject();
                    $editMasterXmlStr = trim($editRow->master_xml);
                }
            } catch (Throwable $e) {
                Udoh\Emsa\Utils\ExceptionUtils::logException($e);
                $eventLogMessage->add(new EventLogNote('Unable to load current values to edit.', 'ui-icon-elrerror'));
            }

            libxml_disable_entity_loader(true);
            $editMasterXml = simplexml_load_string($editMasterXmlStr);
            libxml_disable_entity_loader(false);
            unset($editMasterXml->exceptions);  // no longer used, clean up to avoid confusion -- exceptions stored in db
            // set edited values
            if (isset($_REQUEST['edit_patient_id']) && (strlen(trim($_REQUEST['edit_patient_id'])) > 0)) {
                $editMasterXml->hospital_info->medical_record = trim($_REQUEST['edit_patient_id']);
            } else {
                $editMasterXml->hospital_info->medical_record = '';
            }

            if (isset($_REQUEST['edit_last_name']) && (strlen(trim($_REQUEST['edit_last_name'])) > 0)) {
                $editMasterXml->person->last_name = trim($_REQUEST['edit_last_name']);
            } else {
                $editMasterXml->person->last_name = '';
            }

            if (isset($_REQUEST['edit_first_name']) && (strlen(trim($_REQUEST['edit_first_name'])) > 0)) {
                $editMasterXml->person->first_name = trim($_REQUEST['edit_first_name']);
            } else {
                $editMasterXml->person->first_name = '';
            }

            if (isset($_REQUEST['edit_middle_name']) && (strlen(trim($_REQUEST['edit_middle_name'])) > 0)) {
                $editMasterXml->person->middle_name = trim($_REQUEST['edit_middle_name']);
            } else {
                $editMasterXml->person->middle_name = '';
            }

            if (isset($_REQUEST['edit_gender']) && (strlen(trim($_REQUEST['edit_gender'])) > 0)) {
                $editMasterXml->person->gender = trim($_REQUEST['edit_gender']);
            } else {
                $editMasterXml->person->gender = '';
            }

            if (isset($_REQUEST['edit_race']) && (strlen(trim($_REQUEST['edit_race'])) > 0)) {
                $editMasterXml->person->race = trim($_REQUEST['edit_race']);
            } else {
                $editMasterXml->person->race = '';
            }

            if (isset($_REQUEST['edit_ethnicity']) && (strlen(trim($_REQUEST['edit_ethnicity'])) > 0)) {
                $editMasterXml->person->ethnicity = trim($_REQUEST['edit_ethnicity']);
            } else {
                $editMasterXml->person->ethnicity = '';
            }

            if (isset($_REQUEST['edit_street_name']) && (strlen(trim($_REQUEST['edit_street_name'])) > 0)) {
                $editMasterXml->person->street_name = trim($_REQUEST['edit_street_name']);
            } else {
                $editMasterXml->person->street_name = '';
            }

            if (isset($_REQUEST['edit_unit']) && (strlen(trim($_REQUEST['edit_unit'])) > 0)) {
                $editMasterXml->person->unit = trim($_REQUEST['edit_unit']);
            } else {
                $editMasterXml->person->unit = '';
            }

            if (isset($_REQUEST['edit_city']) && (strlen(trim($_REQUEST['edit_city'])) > 0)) {
                $editMasterXml->person->city = trim($_REQUEST['edit_city']);
            } else {
                $editMasterXml->person->city = '';
            }

            if (isset($_REQUEST['edit_state']) && (strlen(trim($_REQUEST['edit_state'])) > 0)) {
                $editMasterXml->person->state = trim($_REQUEST['edit_state']);
            } else {
                $editMasterXml->person->state = '';
            }

            if (isset($_REQUEST['edit_county']) && (strlen(trim($_REQUEST['edit_county'])) > 0)) {
                $editMasterXml->person->county = trim($_REQUEST['edit_county']);
            } else {
                $editMasterXml->person->county = '';
            }

            if (isset($_REQUEST['edit_zip']) && (strlen(trim($_REQUEST['edit_zip'])) > 0)) {
                $editMasterXml->person->zip = trim($_REQUEST['edit_zip']);
            } else {
                $editMasterXml->person->zip = '';
            }

            if (isset($_REQUEST['edit_country']) && (strlen(trim($_REQUEST['edit_country'])) > 0)) {
                $editMasterXml->person->country = trim($_REQUEST['edit_country']);
            } else {
                $editMasterXml->person->country = '';
            }

            if (isset($_REQUEST['edit_area_code']) && (strlen(trim($_REQUEST['edit_area_code'])) > 0)) {
                $editMasterXml->person->area_code = strtr(trim($_REQUEST['edit_area_code']), array("-" => "", "(" => "", ")" => "", " " => ""));
            } else {
                $editMasterXml->person->area_code = '';
            }

            if (isset($_REQUEST['edit_telephone']) && (strlen(trim($_REQUEST['edit_telephone'])) > 0)) {
                $editMasterXml->person->phone = strtr(trim($_REQUEST['edit_telephone']), array("-" => "", "(" => "", ")" => "", " " => ""));
            } else {
                $editMasterXml->person->phone = '';
            }

            if (isset($_REQUEST['edit_email']) && (strlen(trim($_REQUEST['edit_email'])) > 0)) {
                $editMasterXml->person->email = trim($_REQUEST['edit_email']);
            } else {
                $editMasterXml->person->email = '';
            }

            if (isset($_REQUEST['edit_clinician_last_name']) && (strlen(trim($_REQUEST['edit_clinician_last_name'])) > 0)) {
                $editMasterXml->clinicians->last_name = trim($_REQUEST['edit_clinician_last_name']);
            } else {
                $editMasterXml->clinicians->last_name = '';
            }

            if (isset($_REQUEST['edit_clinician_first_name']) && (strlen(trim($_REQUEST['edit_clinician_first_name'])) > 0)) {
                $editMasterXml->clinicians->first_name = trim($_REQUEST['edit_clinician_first_name']);
            } else {
                $editMasterXml->clinicians->first_name = '';
            }

            if (isset($_REQUEST['edit_clinician_middle_name']) && (strlen(trim($_REQUEST['edit_clinician_middle_name'])) > 0)) {
                $editMasterXml->clinicians->middle_name = trim($_REQUEST['edit_clinician_middle_name']);
            } else {
                $editMasterXml->clinicians->middle_name = '';
            }

            if (isset($_REQUEST['edit_clinician_area_code']) && (strlen(trim($_REQUEST['edit_clinician_area_code'])) > 0)) {
                $editMasterXml->clinicians->area_code = strtr(trim($_REQUEST['edit_clinician_area_code']), array("-" => "", "(" => "", ")" => "", " " => ""));
            } else {
                $editMasterXml->clinicians->area_code = '';
            }

            if (isset($_REQUEST['edit_clinician_telephone']) && (strlen(trim($_REQUEST['edit_clinician_telephone'])) > 0)) {
                $editMasterXml->clinicians->phone = strtr(trim($_REQUEST['edit_clinician_telephone']), array("-" => "", "(" => "", ")" => "", " " => ""));
            } else {
                $editMasterXml->clinicians->phone = '';
            }

            if (isset($_REQUEST['edit_child_loinc']) && (strlen(trim($_REQUEST['edit_child_loinc'])) > 0)) {
                $editMasterXml->labs->local_loinc_code = trim($_REQUEST['edit_child_loinc']);
            } else {
                $editMasterXml->labs->local_loinc_code = '';
            }

            if (isset($_REQUEST['edit_test_name']) && (strlen(trim($_REQUEST['edit_test_name'])) > 0)) {
                $editMasterXml->labs->local_test_name = trim($_REQUEST['edit_test_name']);
            } else {
                $editMasterXml->labs->local_test_name = '';
            }

            if (isset($_REQUEST['edit_local_code']) && (strlen(trim($_REQUEST['edit_local_code'])) > 0)) {
                $editMasterXml->labs->local_code = trim($_REQUEST['edit_local_code']);
            } else {
                $editMasterXml->labs->local_code = '';
            }

            if (isset($_REQUEST['edit_local_code_test_name']) && (strlen(trim($_REQUEST['edit_local_code_test_name'])) > 0)) {
                $editMasterXml->labs->local_code_test_name = trim($_REQUEST['edit_local_code_test_name']);
            } else {
                $editMasterXml->labs->local_code_test_name = '';
            }

            if (isset($_REQUEST['edit_result_value']) && (strlen(trim($_REQUEST['edit_result_value'])) > 0)) {
                $editMasterXml->labs->local_result_value = trim($_REQUEST['edit_result_value']);
            } else {
                $editMasterXml->labs->local_result_value = '';
            }

            if (isset($_REQUEST['edit_abnormal_flag']) && (strlen(trim($_REQUEST['edit_abnormal_flag'])) > 0)) {
                $editMasterXml->labs->abnormal_flag = trim($_REQUEST['edit_abnormal_flag']);
            } else {
                $editMasterXml->labs->abnormal_flag = '';
            }

            if (isset($_REQUEST['edit_specimen_source']) && (strlen(trim($_REQUEST['edit_specimen_source'])) > 0)) {
                $editMasterXml->labs->local_specimen_source = trim($_REQUEST['edit_specimen_source']);
            } else {
                $editMasterXml->labs->local_specimen_source = '';
            }

            if (isset($_REQUEST['edit_accession_number']) && (strlen(trim($_REQUEST['edit_accession_number'])) > 0)) {
                $editMasterXml->labs->accession_number = trim($_REQUEST['edit_accession_number']);
            } else {
                $editMasterXml->labs->accession_number = '';
            }

            if (isset($_REQUEST['edit_performing_lab']) && (strlen(trim($_REQUEST['edit_performing_lab'])) > 0)) {
                $editMasterXml->labs->lab = trim($_REQUEST['edit_performing_lab']);
            } else {
                $editMasterXml->labs->lab = '';
            }

            if (isset($_REQUEST['edit_agency']) && (strlen(trim($_REQUEST['edit_agency'])) > 0)) {
                $editMasterXml->reporting->agency = trim($_REQUEST['edit_agency']);
            } else {
                $editMasterXml->reporting->agency = '';
            }

            $nowDateTime = new DateTime();
            $localTimeZone = $nowDateTime->getTimezone();

            $editDOBRaw = filter_input(INPUT_POST, 'edit_dob', FILTER_SANITIZE_STRING);
            $editDOBFormatted = "";
            if (!empty($editDOBRaw)) {
                try {
                    $editDOBTemp = DateTimeUtils::createMixed($editDOBRaw);

                    if ($editDOBTemp < $nowDateTime) {
                        $editDOBFormatted = $editDOBTemp->setTimezone($localTimeZone)->format(DATE_RFC3339);
                    }
                } catch (Throwable $e) {
                    $e = null;
                }
            }
            $editMasterXml->person->date_of_birth = $editDOBFormatted;


            $editDateReportedRaw = filter_input(INPUT_POST, 'edit_date_reported', FILTER_SANITIZE_STRING);
            $editDateReportedFormatted = "";
            if (!empty($editDateReportedRaw)) {
                try {
                    $editDateReportedTemp = DateTimeUtils::createMixed($editDateReportedRaw);

                    if ($editDateReportedTemp < $nowDateTime) {
                        $editDateReportedFormatted = $editDateReportedTemp->setTimezone($localTimeZone)->format(DATE_RFC3339);
                    }
                } catch (Throwable $e) {
                    $e = null;
                }
            }
            $editMasterXml->reporting->report_date = $editDateReportedFormatted;


            $editDateCollectedRaw = filter_input(INPUT_POST, 'edit_date_collected', FILTER_SANITIZE_STRING);
            $editDateCollectedFormatted = "";
            if (!empty($editDateCollectedRaw)) {
                try {
                    $editDateCollectedTemp = DateTimeUtils::createMixed($editDateCollectedRaw);

                    if ($editDateCollectedTemp < $nowDateTime) {
                        $editDateCollectedFormatted = $editDateCollectedTemp->setTimezone($localTimeZone)->format(DATE_RFC3339);
                    }
                } catch (Throwable $e) {
                    $e = null;
                }
            }
            $editMasterXml->labs->collection_date = $editDateCollectedFormatted;


            $editDateTestedRaw = filter_input(INPUT_POST, 'edit_date_tested', FILTER_SANITIZE_STRING);
            $editDateTestedFormatted = "";
            if (!empty($editDateTestedRaw)) {
                try {
                    $editDateTestedTemp = DateTimeUtils::createMixed($editDateTestedRaw);

                    if ($editDateTestedTemp < $nowDateTime) {
                        $editDateTestedFormatted = $editDateTestedTemp->setTimezone($localTimeZone)->format(DATE_RFC3339);
                    }
                } catch (Throwable $e) {
                    $e = null;
                }
            }
            $editMasterXml->labs->lab_test_date = $editDateTestedFormatted;


            if (isset($_POST['edit_visits']) && is_array($_POST['edit_visits']) && (count($_POST['edit_visits']) > 0)) {
                foreach ($_POST['edit_visits'] as $editVisitId => $editVisitData) {
                    $editMasterXml->person_facilities[$editVisitId]->medical_record_number = trim($editVisitData['mrn']);
                    $editDateAdmittedRaw = filter_var($editVisitData['date_admitted'], FILTER_SANITIZE_STRING);
                    $editDateAdmittedFormatted = "";
                    if (!empty($editDateAdmittedRaw)) {
                        try {
                            $editDateAdmittedTemp = DateTimeUtils::createMixed($editDateAdmittedRaw);

                            if ($editDateAdmittedTemp < $nowDateTime) {
                                $editDateAdmittedFormatted = $editDateAdmittedTemp->setTimezone($localTimeZone)->format(DATE_RFC3339);
                            }
                        } catch (Throwable $e) {
                            $e = null;
                        }
                    }
                    $editMasterXml->person_facilities[$editVisitId]->admission_date = $editDateAdmittedFormatted;

                    $editDateDischargedRaw = filter_var($editVisitData['date_discharged'], FILTER_SANITIZE_STRING);
                    $editDateDischargedFormatted = "";
                    if (!empty($editDateDischargedRaw)) {
                        try {
                            $editDateDischargedTemp = DateTimeUtils::createMixed($editDateDischargedRaw);

                            if ($editDateDischargedTemp < $nowDateTime) {
                                $editDateDischargedFormatted = $editDateDischargedTemp->setTimezone($localTimeZone)->format(DATE_RFC3339);
                            }
                        } catch (Throwable $e) {
                            $e = null;
                        }
                    }
                    $editMasterXml->person_facilities[$editVisitId]->discharge_date = $editDateDischargedFormatted;

                    if (isset($editVisitData['name']) && (strlen(trim($editVisitData['name'])) > 0)) {
                        $editMasterXml->person_facilities[$editVisitId]->facility->name = trim($editVisitData['name']);
                    } else {
                        $editMasterXml->person_facilities[$editVisitId]->facility->name = '';
                    }

                    if (isset($editVisitData['street_name']) && (strlen(trim($editVisitData['street_name'])) > 0)) {
                        $editMasterXml->person_facilities[$editVisitId]->facility->street_name = trim($editVisitData['street_name']);
                    } else {
                        $editMasterXml->person_facilities[$editVisitId]->facility->street_name = '';
                    }

                    if (isset($editVisitData['unit']) && (strlen(trim($editVisitData['unit'])) > 0)) {
                        $editMasterXml->person_facilities[$editVisitId]->facility->unit_number = trim($editVisitData['unit']);
                    } else {
                        $editMasterXml->person_facilities[$editVisitId]->facility->unit_number = '';
                    }

                    if (isset($editVisitData['city']) && (strlen(trim($editVisitData['city'])) > 0)) {
                        $editMasterXml->person_facilities[$editVisitId]->facility->city = trim($editVisitData['city']);
                    } else {
                        $editMasterXml->person_facilities[$editVisitId]->facility->city = '';
                    }

                    if (isset($editVisitData['state']) && (strlen(trim($editVisitData['state'])) > 0)) {
                        $editMasterXml->person_facilities[$editVisitId]->facility->state = trim($editVisitData['state']);
                    } else {
                        $editMasterXml->person_facilities[$editVisitId]->facility->state = '';
                    }

                    if (isset($editVisitData['zip']) && (strlen(trim($editVisitData['zip'])) > 0)) {
                        $editMasterXml->person_facilities[$editVisitId]->facility->zipcode = trim($editVisitData['zip']);
                    } else {
                        $editMasterXml->person_facilities[$editVisitId]->facility->zipcode = '';
                    }
                }
            }


            if (isset($_REQUEST['edit_df_name']) && (strlen(trim($_REQUEST['edit_df_name'])) > 0)) {
                $editMasterXml->diagnostic->name = trim($_REQUEST['edit_df_name']);
            } else {
                $editMasterXml->diagnostic->name = '';
            }

            if (isset($_REQUEST['edit_df_street_name']) && (strlen(trim($_REQUEST['edit_df_street_name'])) > 0)) {
                $editMasterXml->diagnostic->street_name = trim($_REQUEST['edit_df_street_name']);
            } else {
                $editMasterXml->diagnostic->street_name = '';
            }

            if (isset($_REQUEST['edit_df_unit']) && (strlen(trim($_REQUEST['edit_df_unit'])) > 0)) {
                $editMasterXml->diagnostic->unit = trim($_REQUEST['edit_df_unit']);
            } else {
                $editMasterXml->diagnostic->unit = '';
            }

            if (isset($_REQUEST['edit_df_city']) && (strlen(trim($_REQUEST['edit_df_city'])) > 0)) {
                $editMasterXml->diagnostic->city = trim($_REQUEST['edit_df_city']);
            } else {
                $editMasterXml->diagnostic->city = '';
            }

            if (isset($_REQUEST['edit_df_state']) && (strlen(trim($_REQUEST['edit_df_state'])) > 0)) {
                $editMasterXml->diagnostic->state = trim($_REQUEST['edit_df_state']);
            } else {
                $editMasterXml->diagnostic->state = '';
            }

            if (isset($_REQUEST['edit_df_zip']) && (strlen(trim($_REQUEST['edit_df_zip'])) > 0)) {
                $editMasterXml->diagnostic->zipcode = trim($_REQUEST['edit_df_zip']);
            } else {
                $editMasterXml->diagnostic->zipcode = '';
            }


            $edit_xmlstring = $editMasterXml->asXML();

            // send updated master xml through master save process
            $encoded_masterxml = urlencode(str_replace("<?xml version=\"1.0\" encoding=\"UTF-8\"?>", "", $edit_xmlstring));

            $editedXmlForSaveMaster = new SimpleXMLElement("<health_message></health_message>");
            $editedXmlForSaveMaster->addChild("user_id", EPITRAX_AUTH_ELR_UID);
            $editedXmlForSaveMaster->addChild("system_message_id", (int) $emsaMessage->getSystemMessageId());
            $editedXmlForSaveMaster->addChild("system", (int) $emsaMessage->getApplicationId());  // application ID
            $editedXmlForSaveMaster->addChild("health_xml", $encoded_masterxml);

            $eventLogMessage->add(new EventLogNote("Revalidating message...", "ui-icon-elrretry"));

            try {
                $masterProcessClient = new Udoh\Emsa\Client\MasterProcessClient();
            } catch (Throwable $e) {
                Udoh\Emsa\Utils\ExceptionUtils::logException($e);
                $eventLogMessage->setProcessedSuccessfully(false);
                $eventLogMessage->add(new EventLogNote("Unable to process message:  could not connect to Master Process.", "ui-icon-elrerror"));
                $eventLog->add($eventLogMessage);
                $eventLog->display();
                exit;
            }

            try {
                $saveMasterResult = $masterProcessClient->saveMaster($editedXmlForSaveMaster);
                $saveMasterStatus = $masterProcessClient->validateResponse($saveMasterResult);
            } catch (Throwable $e) {
                Udoh\Emsa\Utils\ExceptionUtils::logException($e);
                $eventLogMessage->setProcessedSuccessfully(false);
                $eventLogMessage->add(new EventLogNote("Unable to process message.  The following errors occurred:", "ui-icon-elrerror", $e->getMessage()));
                $eventLog->add($eventLogMessage);
                $eventLog->display();
                exit;
            }

            if ($saveMasterStatus->getStatus()) {
                Udoh\Emsa\Utils\AuditUtils::auditMessage($dbConn, $emsaMessage->getSystemMessageId(), Udoh\Emsa\Constants\SystemMessageActions::MESSAGE_RETRIED, $type, 'Edited and retried');

                $currentStatus = $msgUtils->getMessageLocationById($emsaMessage->getSystemMessageId());

                try {
                    $currentSql = "SELECT se.description AS description, sme.info AS info, ss.name AS type 
							FROM system_message_exceptions sme 
							INNER JOIN system_exceptions se ON (sme.exception_id = se.exception_id) 
							INNER JOIN system_statuses ss ON (se.exception_type_id = ss.id) 
							WHERE sme.system_message_id = :systemMessageId
							ORDER BY sme.id;";
                    $currentStmt = $dbConn->prepare($currentSql);
                    $currentStmt->bindValue(':systemMessageId', intval($emsaMessage->getSystemMessageId()), PDO::PARAM_INT);

                    if ($currentStmt->execute()) {
                        if ($currentStmt->rowCount() > 0) {
                            $eventLogExceptionDetails = new EventLogExceptionDetailSet();

                            while ($currentRow = $currentStmt->fetchObject()) {
                                $eventLogExceptionDetails->add(new Udoh\Emsa\MessageProcessing\EventLogExceptionDetail($currentRow->type, $currentRow->description, $currentRow->info));
                            }

                            $eventLogMessage->setProcessedSuccessfully(false);
                            $eventLogMessage->add(new EventLogNote("Message moved to '$currentStatus' queue.", "ui-icon-elrerror", null, $eventLogExceptionDetails));
                        } else {
                            $eventLogMessage->setProcessedSuccessfully(true);
                            $eventLogMessage->add(new EventLogNote("Changes saved successfully & message validated with no errors!  Message moved to '$currentStatus' queue.", "ui-icon-elrsuccess"));
                        }
                    } else {
                        $eventLogMessage->setProcessedSuccessfully(false);
                        $eventLogMessage->add(new EventLogNote("Changes to message were saved, but could not verify successful message validation due to database errors.  Message moved to '$currentStatus' queue.", "ui-icon-elrerror"));
                    }
                } catch (Throwable $e) {
                    Udoh\Emsa\Utils\ExceptionUtils::logException($e);
                    $eventLogMessage->setProcessedSuccessfully(false);
                    $eventLogMessage->add(new EventLogNote("Changes to message were saved, but could not verify successful message validation due to database errors.  Message moved to '$currentStatus' queue.", "ui-icon-elrerror", $e->getMessage()));
                }
            } else {
                $eventLogMessage->setProcessedSuccessfully(false);
                $eventLogMessage->add(new EventLogNote("Unable to save changes to message.", "ui-icon-elrerror", $saveMasterStatus->getErrorString()));
            }
            $eventLog->add($eventLogMessage);
        } elseif ($ampAction === Udoh\Emsa\Constants\MessageProcessingActions::ACTION_QA_COMMENT) {
            // add a message QA comment
            $eventLogMessage = new EventLogMessage($emsaMessage->getSystemMessageId());
            if ($msgUtils->addQAComment($emsaMessage->getSystemMessageId(), trim($_SESSION[EXPORT_SERVERNAME]['umdid']), trim($cleanActions['info']))) {
                $eventLogMessage->setProcessedSuccessfully(true);
                $eventLogMessage->add(new EventLogNote('QA comment added successfully!', 'ui-icon-elrsuccess'));
            } else {
                $eventLogMessage->setProcessedSuccessfully(false);
                $eventLogMessage->add(new EventLogNote('Error:  Could not add comment.', 'ui-icon-elrerror'));
            }
            $eventLog->add($eventLogMessage);
        } elseif ($ampAction === Udoh\Emsa\Constants\MessageProcessingActions::ACTION_SET_FLAG) {
            // set a message flag
            $eventLogMessage = new EventLogMessage($emsaMessage->getSystemMessageId());
            if ($msgUtils->setQaFlag($emsaMessage->getSystemMessageId(), $type, $cleanActions['target'], $cleanActions['info'])) {
                $eventLogMessage->setProcessedSuccessfully(true);
                $eventLogMessage->add(new EventLogNote('Message flag successfully set!', 'ui-icon-elrsuccess'));
            } else {
                $eventLogMessage->setProcessedSuccessfully(false);
                $eventLogMessage->add(new EventLogNote('Error:  Could not set message flag.', 'ui-icon-elrerror'));
            }
            $eventLog->add($eventLogMessage);
        } elseif ($ampAction === Udoh\Emsa\Constants\MessageProcessingActions::ACTION_UNSET_FLAG) {
            // clear a message flag
            $eventLogMessage = new EventLogMessage($emsaMessage->getSystemMessageId());
            if ($msgUtils->unsetQaFlag($emsaMessage->getSystemMessageId(), intval($cleanActions['target']))) {
                $eventLogMessage->setProcessedSuccessfully(true);
                $eventLogMessage->add(new EventLogNote('Message flag successfully cleared!', 'ui-icon-elrsuccess'));
                Udoh\Emsa\Utils\AuditUtils::auditMessage($dbConn, $emsaMessage->getSystemMessageId(), Udoh\Emsa\Constants\SystemMessageActions::MESSAGE_FLAG_CLEARED, $type, EmsaUtils::decodeMessageQaFlag($dbConn, intval($cleanActions['target']))); // message flag clear
            } else {
                $eventLogMessage->setProcessedSuccessfully(false);
                $eventLogMessage->add(new EventLogNote('Error:  Could not clear message flag.', 'ui-icon-elrerror'));
            }
            
            // un-set comment for this flag in system_message_flag_comments, if one is set
            EmsaUtils::clearQaFlagComment($dbConn, $emsaMessage->getSystemMessageId(), $cleanActions['target']);
            $eventLog->add($eventLogMessage);
        } elseif (($ampAction === Udoh\Emsa\Constants\MessageProcessingActions::ACTION_MOVE) && Udoh\Emsa\Auth\Authenticator::userHasPermission(Udoh\Emsa\Auth\Authenticator::URIGHTS_ACTION_MOVE)) {
            // move selected ELR message to new list
            $eventLogMessage = new EventLogMessage($emsaMessage->getSystemMessageId());
            try {
                if ($msgUtils->moveMessageToQueue($emsaMessage->getSystemMessageId(), $type, $cleanActions['target'], Udoh\Emsa\Constants\SystemMessageActions::MESSAGE_MOVED_BY_USER, $cleanActions['info'])) {
                    $eventLogMessage->setProcessedSuccessfully(true);
                    $eventLogMessage->add(new EventLogNote('Message successfully moved!', 'ui-icon-elrmove'));
                }
            } catch (Throwable $e) {
                Udoh\Emsa\Utils\ExceptionUtils::logException($e);
                $eventLogMessage->setProcessedSuccessfully(false);
                $eventLogMessage->add(new EventLogNote('Error:  Could not move message to new list.', 'ui-icon-elrerror', $e->getMessage()));
            } finally {
                $eventLog->add($eventLogMessage);
            }
        } elseif (($ampAction === Udoh\Emsa\Constants\MessageProcessingActions::ACTION_DELETE) && Udoh\Emsa\Auth\Authenticator::userHasPermission(Udoh\Emsa\Auth\Authenticator::URIGHTS_ACTION_DELETE)) {
            // delete specified ELR message
            $eventLogMessage = new EventLogMessage($emsaMessage->getSystemMessageId());
            if ($msgUtils->markMessageDeleted($emsaMessage->getSystemMessageId(), $type)) {
                $eventLogMessage->setProcessedSuccessfully(true);
                $eventLogMessage->add(new EventLogNote('Message successfully deleted!', 'ui-icon-elrclose'));
            } else {
                $eventLogMessage->setProcessedSuccessfully(false);
                $eventLogMessage->add(new EventLogNote('Error:  Could not delete message.', 'ui-icon-elrerror'));
            }
            $eventLog->add($eventLogMessage);
        } elseif ($ampAction === Udoh\Emsa\Constants\MessageProcessingActions::ACTION_RETRY) {
            // retry processing of this EMSA message
            $eventLogMessage = new EventLogMessage($emsaMessage->getSystemMessageId());
            try {
                $msgUtils->retryMessage($emsaMessage->getSystemMessageId(), $eventLogMessage, $type);
            } catch (Throwable $e) {
                Udoh\Emsa\Utils\ExceptionUtils::logException($e);
                $eventLogMessage->setProcessedSuccessfully(false);
                $eventLogMessage->add(new EventLogNote('Message could not be retried; an unexpected error occurred.', 'ui-icon-elrerror', $e->getMessage()));
            } finally {
                $eventLog->add($eventLogMessage);
            }
        } elseif ($ampAction === Udoh\Emsa\Constants\MessageProcessingActions::BULK_ACTION_ADD_RECORD) {
            // Create a new record in the target Application from the matching selected EMSA messages
            $eventLog->add(new EventLogNote('Starting Bulk Assignment', 'ui-icon-elrcopy'));

            $bulkAddSiblings = $msgUtils->getMessageSiblings($emsaMessage->getOriginalMessageId(), $type, $appClientList, $emsaMessage->getApplicationId());
            $bulkAddedPersonId = null;
            
            // sort list of matched messages to ensure that any messages where 'allowNewCMR = true' are processed first, to minimize needless graylisting
            $bulkAddSiblings->sort();
            
            foreach ($bulkAddSiblings as $bulkAddTuple) {
                try {
                    if (empty($bulkAddedPersonId)) {
                        // new person not yet created... keep trying to add
                        $bulkAddedPersonId = $msgUtils->addCmrProcess(
                                $bulkAddTuple->getEventLogMessage(), $bulkAddTuple->getEmsaMessage(), MessageProcessingUtils::ADDCMR_REASON_ADDNEW, $type, $ampAutomated, $ampOverride, $cleanActions['override_surveillance']
                        );
                    } else {
                        // person already exists, run an update against them instead of an add
                        $msgUtils->updateCmrProcess(
                                $bulkAddTuple->getEventLogMessage(), $bulkAddTuple->getEmsaMessage(), (string) $bulkAddedPersonId, $type, $ampAutomated, $ampOverride, $cleanActions['override_surveillance'], $ampOverrideEventId
                        );
                    }
                } catch (Throwable $e) {
                    Udoh\Emsa\Utils\ExceptionUtils::logException($e);
                    error_log('[addCmrProcess] ' . $e->getMessage());
                    $bulkAddTuple->getEventLogMessage()->add(new EventLogNote('Message could not be processed; an unexpected error occurred:', 'ui-icon-elrerror', $e->getMessage()));
                } finally {
                    $eventLog->add($bulkAddTuple->getEventLogMessage());
                }
            }

            $eventLog->add(new EventLogNote('Bulk Assignment completed!', 'ui-icon-elrsuccess'));
        } elseif ($ampAction === Udoh\Emsa\Constants\MessageProcessingActions::BULK_ACTION_UPDATE_RECORD) {
            // Attempt to update an existing CMR in the target Application from the matching selected EMSA messages
            $eventLog->add(new EventLogNote('Starting Bulk Assignment', 'ui-icon-elrcopy'));

            $bulkUpdateSiblings = $msgUtils->getMessageSiblings($emsaMessage->getOriginalMessageId(), $type, $appClientList, $emsaMessage->getApplicationId());
            
            foreach ($bulkUpdateSiblings as $bulkUpdateTuple) {
                try {
                    $msgUtils->updateCmrProcess(
                            $bulkUpdateTuple->getEventLogMessage(), $bulkUpdateTuple->getEmsaMessage(), $cleanActions['match_persons'], $type, $ampAutomated, $ampOverride, $cleanActions['override_surveillance'], $ampOverrideEventId
                    );
                } catch (Throwable $e) {
                    Udoh\Emsa\Utils\ExceptionUtils::logException($e);
                    error_log('[updateCmrProcess] ' . $e->getMessage());
                    $bulkUpdateTuple->getEventLogMessage()->add(new EventLogNote('Message could not be processed; an unexpected error occurred:', 'ui-icon-elrerror', $e->getMessage()));
                } finally {
                    $eventLog->add($bulkUpdateTuple->getEventLogMessage());
                }
            }

            $eventLog->add(new EventLogNote('Bulk Assignment completed!', 'ui-icon-elrsuccess'));
        } elseif ($ampAction === Udoh\Emsa\Constants\MessageProcessingActions::ACTION_ADD_RECORD) {
            // Create a new record in the target Application from the current EMSA message
            $eventLogMessage = new EventLogMessage($emsaMessage->getSystemMessageId());

            try {
                $msgUtils->addCmrProcess(
                        $eventLogMessage, $emsaMessage, MessageProcessingUtils::ADDCMR_REASON_ADDNEW, $type, $ampAutomated, $ampOverride, $cleanActions['override_surveillance'], $ampOverridePersonId
                );
            } catch (Throwable $e) {
                Udoh\Emsa\Utils\ExceptionUtils::logException($e);
                error_log('[addCmrProcess] ' . $e->getMessage());
                $eventLogMessage->add(new EventLogNote('Message could not be processed; an unexpected error occurred:', 'ui-icon-elrerror', $e->getMessage()));
            } finally {
                $eventLog->add($eventLogMessage);
            }

            if ($ampAutomated) {
                // if elrauto and message is assigned, exit (don't take time displaying EMSA list)
                ob_clean();
                header($_SERVER['SERVER_PROTOCOL'] . " 200 OK", TRUE, 200);
                exit;
            }
        } elseif ($ampAction === Udoh\Emsa\Constants\MessageProcessingActions::ACTION_UPDATE_RECORD) {
            // Attempt to update an existing CMR in the target Application with the current EMSA message
            $eventLogMessage = new EventLogMessage($emsaMessage->getSystemMessageId());

            try {
                $msgUtils->updateCmrProcess(
                        $eventLogMessage, $emsaMessage, $cleanActions['match_persons'], $type, $ampAutomated, $ampOverride, $cleanActions['override_surveillance'], $ampOverrideEventId
                );
            } catch (Throwable $e) {
                Udoh\Emsa\Utils\ExceptionUtils::logException($e);
                error_log('[updateCmrProcess] ' . $e->getMessage());
                $eventLogMessage->add(new EventLogNote('Message could not be processed; an unexpected error occurred:', 'ui-icon-elrerror', $e->getMessage()));
            } finally {
                $eventLog->add($eventLogMessage);
            }

            if ($ampAutomated) {
                // if elrauto and message is assigned, exit (don't take time displaying EMSA list)
                ob_clean();
                header($_SERVER['SERVER_PROTOCOL'] . " 200 OK", TRUE, 200);
                exit;
            }
        } elseif (($ampAction === MessageProcessingActions::ACTION_AUTOMATED_PROCESSING) && ($emsaMessage->validSpecimen === SPECIMEN_EXCEPTION)) {
            // Specimen source not in valid or invalid specimen sources list; send to exception
            $eventLogMessage = new EventLogMessage($emsaMessage->getSystemMessageId());

            $msgUtils->logMessageException($emsaMessage->getSystemMessageId(), AuditUtils::auditMessage($dbConn, $emsaMessage->getSystemMessageId(), SystemMessageActions::PENDING, $type), SystemExceptions::ENTRY_QUEUE_EXCEPTION, 'Unexpected specimen source detected.', NEDSS_EXCEPTION_STATUS);
            $eventLogMessage->setProcessedSuccessfully(false);
            $eventLogMessage->add(new EventLogNote('Could not add new event:  Condition requires specimen source validation, but an unexpected specimen source was provided.  Message moved to ' . NEDSS_EXCEPTION_NAME . ' queue.', 'ui-icon-elrerror'));

            if ($ampAutomated) {
                // if elrauto and message is assigned, exit (don't take time displaying EMSA list)
                ob_clean();
                header($_SERVER['SERVER_PROTOCOL'] . " 200 OK", TRUE, 200);
                exit;
            }
        } elseif (($ampAction === MessageProcessingActions::ACTION_AUTOMATED_PROCESSING) && ($emsaMessage->validSpecimen === SPECIMEN_INVALID)) {
            $eventLogMessage = new EventLogMessage($emsaMessage->getSystemMessageId());

            try {
                if ($msgUtils->graylistMessage($emsaMessage, $type, 'Invalid specimen source.')) {
                    $eventLogMessage->setProcessedSuccessfully(true);
                    $eventLogMessage->add(new EventLogNote('Valid specimen source not provided.  Message Graylisted.', 'ui-icon-elrcancel'));
                } else {
                    $eventLogMessage->setProcessedSuccessfully(false);
                    $eventLogMessage->add(new EventLogNote('A database error occurred while attempting to graylist incoming message due to an invalid specimen source.  Please contact a system administrator.', 'ui-icon-elrerror'));
                }
            } catch (Throwable $e) {
                Udoh\Emsa\Utils\ExceptionUtils::logException($e);
                $eventLogMessage->add(new EventLogNote('Unexpected error occurred while attempting to graylist message.', 'ui-icon-elrerror', $e->getMessage()));
            }

            if ($ampAutomated) {
                // if elrauto and message is assigned, exit (don't take time displaying EMSA list)
                ob_clean();
                header($_SERVER['SERVER_PROTOCOL'] . " 200 OK", TRUE, 200);
                exit;
            }
        }
    } else {
        $eventLogMessage = new EventLogMessage($emsaMessage->getSystemMessageId());
        $eventLogMessage->setProcessedSuccessfully(false);
        $eventLogMessage->add(new EventLogNote('Could not perform selected action on this message:  the selected ELR message has already been processed.', 'ui-icon-elrstop'));
        $eventLog->add($eventLogMessage);
    }
}

$eventLog->display();

$dbConn = null;

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

// prevent caching...
header('Expires: Mon, 26 Jul 1997 05:00:00 GMT');
header('Cache-Control: no-cache');
header('Pragma: no-cache');

include __DIR__ . '/../../includes/app_config.php';

// done writing to session; prevent blocking
session_write_close();

// validate that user has access to Semi-Automated Entry queue...
if (!Udoh\Emsa\Auth\Authenticator::userHasPermission(Udoh\Emsa\Auth\Authenticator::URIGHTS_SEMI_AUTO)) {
    ob_end_clean();
    header($_SERVER['SERVER_PROTOCOL'] . " 400 Bad Request", TRUE, 400);
    exit;
}

try {
    $dbConn = $emsaDbFactory->getConnection();
} catch (Throwable $e) {
    Udoh\Emsa\Utils\ExceptionUtils::logException($e);
    header($_SERVER['SERVER_PROTOCOL'] . " 503 Service unavailable", TRUE, 503);
    exit;
}

$systemMessageId = (int) filter_input(INPUT_POST, 'message', FILTER_SANITIZE_NUMBER_INT);

if (empty($systemMessageId)) {
    ob_end_clean();
    header($_SERVER['SERVER_PROTOCOL'] . " 400 Bad Request", TRUE, 400);  // no message ID specified
    exit;
}

try {
    $emsaMessage = new \EmsaMessage($dbConn, $appClientList, $systemMessageId, true, true);
} catch (Throwable $ex) {
    Udoh\Emsa\Utils\ExceptionUtils::logException($ex);
    header($_SERVER['SERVER_PROTOCOL'] . " 400 Bad Request", TRUE, 400);  // message does not exist
    exit;
}

// check whether message is actually IN a queue that allows for Semi-Automated Entry (Semi-Auto or QA Review)...
$currentQueue = (int) \EmsaUtils::getQueueIdByMessageId($dbConn, $systemMessageId); 
$isSemiAuto = ($currentQueue === SEMI_AUTO_STATUS);
$isQaReview = ($currentQueue === QA_STATUS);
if (!$isSemiAuto && !$isQaReview) {
    ob_end_clean();
    header($_SERVER['SERVER_PROTOCOL'] . " 400 Bad Request", TRUE, 400);
    exit;
}

$clean = array();
$params = array(
    'disease' => 'text',
    'organism' => 'text',
    'jurisdiction' => 'integer',
    'state_case_status' => 'text',
    'test_type' => 'text',
    'specimen' => 'text',
    'testresult' => 'text',
    'resultvalue' => 'text',
    'units' => 'text',
    'comment' => 'text',
    'teststatus' => 'text'
);

// sanitize expected params
foreach ($params as $paramName => $paramType) {
    if ($paramType === 'integer') {
        $clean[$paramName] = (int) filter_input(INPUT_POST, $paramName, FILTER_SANITIZE_NUMBER_INT);
    } else {
        $clean[$paramName] = (string) filter_input(INPUT_POST, $paramName, FILTER_SANITIZE_STRING);
    }
}

// get existing master & NEDSS XML from database for this message
$masterXML = $emsaMessage->getMasterXML();
$appXML = $emsaMessage->getApplicationXML();

// translate & store values if set
if (empty($clean['disease'])) {
    $masterXML->disease->name = '';
    unset($appXML->person->personCondition->condition);
} else {
    $masterXML->disease->name = $clean['disease'];
    unset($appXML->person->personCondition->condition);
    $appXML->person->personCondition->condition->name = $clean['disease'];
}

if (empty($clean['organism'])) {
    $masterXML->labs->organism = '';
    unset($appXML->person->personCondition->lab->labTest->labTestResult->organism);
} else {
    $masterXML->labs->organism = $clean['organism'];
    unset($appXML->person->personCondition->lab->labTest->labTestResult->organism);
    $appXML->person->personCondition->lab->labTest->labTestResult->organism->code = $clean['organism'];
}

if (empty($clean['jurisdiction'])) {
    $masterXML->administrative->jurisdictionId = '';
    unset($appXML->person->personCondition->agency->id);
} else {
    $masterXML->administrative->jurisdictionId = $clean['jurisdiction'];
    $appXML->person->personCondition->agency->id = $clean['jurisdiction'];
}

if (empty($clean['state_case_status'])) {
    $masterXML->labs->state_case_status = '';
    unset($appXML->person->personCondition->stateCaseStatus);
} else {
    $masterXML->labs->state_case_status = $clean['state_case_status'];
    $appXML->person->personCondition->stateCaseStatus->code = $clean['state_case_status'];
}

if (empty($clean['test_type'])) {
    $masterXML->labs->test_type = '';
    unset($appXML->person->personCondition->lab->labTest->testType);
} else {
    $masterXML->labs->test_type = $clean['test_type'];
    $appXML->person->personCondition->lab->labTest->testType->code = $clean['test_type'];
}

if (empty($clean['specimen'])) {
    $masterXML->labs->specimen_source = '';
    unset($appXML->person->personCondition->lab->specimenSource);
} else {
    $masterXML->labs->specimen_source = $clean['specimen'];
    $appXML->person->personCondition->lab->specimenSource->code = $clean['specimen'];
}

if (empty($clean['testresult'])) {
    $masterXML->labs->test_result = '';
    unset($appXML->person->personCondition->lab->labTest->labTestResult->testResult);
} else {
    $masterXML->labs->test_result = $clean['testresult'];
    $appXML->person->personCondition->lab->labTest->labTestResult->testResult->code = $clean['testresult'];
}

if (empty($clean['resultvalue'])) {
    $masterXML->labs->result_value = '';
    unset($appXML->person->personCondition->lab->labTest->labTestResult->resultValue);
} else {
    $masterXML->labs->result_value = $clean['resultvalue'];
    $appXML->person->personCondition->lab->labTest->labTestResult->resultValue = $clean['resultvalue'];
}

if (empty($clean['units'])) {
    $masterXML->labs->units = '';
    unset($appXML->person->personCondition->lab->labTest->labTestResult->units);
} else {
    $masterXML->labs->units = $clean['units'];
    $appXML->person->personCondition->lab->labTest->labTestResult->units = $clean['units'];
}

if (empty($clean['comment'])) {
    $masterXML->labs->comment = '';
    unset($appXML->person->personCondition->lab->labTest->labTestResult->comment);
} else {
    $masterXML->labs->comment = $clean['comment'];
    $appXML->person->personCondition->lab->labTest->labTestResult->comment = $clean['comment'];
}

if (empty($clean['teststatus'])) {
    $masterXML->labs->test_status = '';
    unset($appXML->person->personCondition->lab->labTest->testStatus);
} else {
    $masterXML->labs->test_status = $clean['teststatus'];
    $appXML->person->personCondition->lab->labTest->testStatus->code = $clean['teststatus'];
}

try {
    $sqlSave = "UPDATE system_messages 
                SET master_xml = :masterXmlStr, 
                transformed_xml = :nedssXmlStr, 
                disease = :conditionStr 
                WHERE id = :systemMessageId;";
    
    $stmtSave = $dbConn->prepare($sqlSave);
    $stmtSave->bindValue(':masterXmlStr', $masterXML->asXML(), PDO::PARAM_STR);
    $stmtSave->bindValue(':nedssXmlStr', $appXML->asXML(), PDO::PARAM_STR);
    $stmtSave->bindValue(':conditionStr', $clean['disease'], PDO::PARAM_STR);
    $stmtSave->bindValue(':systemMessageId', $systemMessageId, PDO::PARAM_INT);

    if ($stmtSave->execute()) {
        $stmtSave = null;
        $dbConn = null;
        $emsaDbFactory = null;
        header($_SERVER['SERVER_PROTOCOL'] . " 200 OK", TRUE, 200);
        exit;
    } else {
        $stmtSave = null;
        $dbConn = null;
        $emsaDbFactory = null;
        header($_SERVER['SERVER_PROTOCOL'] . " 500 Internal Server Error", TRUE, 500);
        exit;
    }
} catch (Throwable $e) {
    $dbConn = null;
    $emsaDbFactory = null;
    Udoh\Emsa\Utils\ExceptionUtils::logException($e);
    header($_SERVER['SERVER_PROTOCOL'] . " 500 Internal Server Error", TRUE, 500);
    exit;
}

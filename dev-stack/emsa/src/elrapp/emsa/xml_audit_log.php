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
use Udoh\Emsa\UI\Queue\EmsaQueueList;
use Udoh\Emsa\Utils\ExceptionUtils;

header('Expires: Mon, 26 Jul 1997 05:00:00 GMT');
header('Cache-Control: no-cache');
header('Pragma: no-cache');

include __DIR__ . '/../includes/app_config.php';

// done writing to session; prevent blocking
session_write_close();

// if user doesn't have rights to view XML, don't display
if (!Udoh\Emsa\Auth\Authenticator::userHasPermission(Udoh\Emsa\Auth\Authenticator::URIGHTS_TAB_XML)) {
    ob_end_clean();
    header($_SERVER['SERVER_PROTOCOL'] . " 404 Not Found", TRUE, 404);
    exit;
}

// input sanitization
$rawAuditId = filter_input(INPUT_POST, 'id', FILTER_SANITIZE_NUMBER_INT);

if (is_null($rawAuditId)) {
    // no audit ID passed; probably an attempt to load the page outside of the app
    ob_end_clean();
    header($_SERVER['SERVER_PROTOCOL'] . " 404 Not Found", TRUE, 404);
    exit;
}

$cleanFieldType = 'previous_xml';
$cleanAuditId = (!EmsaUtils::emptyTrim($rawAuditId)) ? intval($rawAuditId) : 0;

switch (filter_input(INPUT_POST, 'type', FILTER_SANITIZE_NUMBER_INT)) {
    case 2:
        $cleanFieldType = 'sent_xml';
        break;
    default:
        $cleanFieldType = 'previous_xml';
        break;
}

// verify that message ID specified actually exists and that 
// logged-in user has permission to view message XML is being requested for...
try {
    $xalDbConn = $emsaDbFactory->getConnection();
    
    try {
        $cleanMsgId = 0;
        $msgIdSql = "SELECT sma.system_message_id
                     FROM system_messages_audits sma
                     INNER JOIN system_nedss_xml_audits nxa ON (sma.id = nxa.system_messages_audits_id)
                     WHERE nxa.id = :auditId;";
        $msgIdStmt = $xalDbConn->prepare($msgIdSql);
        $msgIdStmt->bindValue(':auditId', (int) $cleanAuditId, PDO::PARAM_INT);
        
        if ($msgIdStmt->execute() && ($msgIdStmt->rowCount() > 0)) {
            $cleanMsgId = (int) $msgIdStmt->fetchColumn(0);
        }
        
        $msgIdStmt = null;
    } catch (Throwable $e) {
        throw new Exception('Specified Message not found.', 0, $e);
    }
    
    $safeQueueId = EmsaUtils::getQueueIdByMessageId($xalDbConn, $cleanMsgId);
    
    if (is_null($safeQueueId) || (intval($safeQueueId) <= 0)) {
        throw new Exception('Specified Message not found.');
    }
    
    $safeQueueList = new EmsaQueueList($xalDbConn, $appClientList);
    $safeCount = (int) $safeQueueList->getMessageCount($safeQueueId, null, null, EmsaQueueList::RESTRICT_SHOW_ALL, $cleanMsgId);
    
    if ($safeCount !== 1) {
        throw new Exception('Unauthorized attempt to view message XML');
    }
} catch (Throwable $e) {
    ExceptionUtils::logException($e);
    echo '<h2>Unable to display XML.  Please see a system administrator.</h2>';
    exit;
}


if ($cleanFieldType === 'sent_xml') {
    $viewSql = "SELECT sent_xml AS result 
                FROM system_nedss_xml_audits 
                WHERE id = :auditId;";
} else {
    $viewSql = "SELECT previous_xml AS result 
                FROM system_nedss_xml_audits 
                WHERE id = :auditId;";
}

try {
    $viewStmt = $xalDbConn->prepare($viewSql);
    $viewStmt->bindValue(':auditId', intval($cleanAuditId), PDO::PARAM_INT);
    
    if ($viewStmt->execute() && ($viewStmt->rowCount() === 1)) {
        $cleanXml = trim($viewStmt->fetchColumn(0));
        echo '<textarea style="width: 100%; height: 100%; font-family: Consolas, sans-serif;">' . Udoh\Emsa\Utils\DisplayUtils::xSafe(Udoh\Emsa\Utils\DisplayUtils::formatXml($cleanXml)) . '</textarea>';
    } else {
        echo '<h2>Unable to display XML.  Please see a system administrator.</h2>';
    }
    
    $viewStmt = null;
} catch (Throwable $e) {
    ExceptionUtils::logException($e);
    echo '<h2>Unable to display XML.  Please see a system administrator.</h2>';
    exit;
}

$xalDbConn = null;
$emsaDbFactory = null;

exit;

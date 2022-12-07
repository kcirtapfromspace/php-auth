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

$ignorePermissionFlag = true;
$isValidSender = false;
$validatedLabId = 0;

$checkName = (string) filter_input(INPUT_GET, 'msh4', FILTER_UNSAFE_RAW);
$originalMessageId = (int) filter_input(INPUT_GET, 'id', FILTER_SANITIZE_NUMBER_INT);

include_once __DIR__ . '/../includes/app_config.php';

try {
    $dbConn = $emsaDbFactory->getConnection();
} catch (Throwable $e) {
    Udoh\Emsa\Utils\ExceptionUtils::logException($e);
    ob_clean();
    header($_SERVER['SERVER_PROTOCOL'] . " 500 Internal Server Error - Connection to database failed...  Could not validate Sending Facility", TRUE, 500);
    header('Content-Type: text/plain; charset=UTF-8');
    exit;
}

try {
    $sql = "SELECT get_lab_id_from_lab_name(:sendingFacilityName);";
    $stmt = $dbConn->prepare($sql);
    $stmt->bindValue(':sendingFacilityName', $checkName, \PDO::PARAM_STR);

    if ($stmt->execute()) {
        $validatedLabId = (int) $stmt->fetchColumn(0);
    }
    
    $stmt = null;
} catch (Throwable $e) {
    Udoh\Emsa\Utils\ExceptionUtils::logException($e);
    ob_clean();
    header($_SERVER['SERVER_PROTOCOL'] . " 500 Internal Server Error - Query failed.", TRUE, 500);
    header('Content-Type: text/plain; charset=UTF-8');
    exit;
}

if ($validatedLabId > 0) {
    // valid, recognized sender
    ob_clean();
    header($_SERVER['SERVER_PROTOCOL'] . " 200 OK", TRUE, 200);
    header('Content-Type: text/plain; charset=UTF-8');
    echo 'Valid sender';
    exit;
} else {
    // sender not recognized; log to database
    try {
        $alertSql = "INSERT INTO system_alerts (alert_type_id, info, alt_info)
                     VALUES (:alertType, :sendingFacilityId, :originalMessageId);";
        $alertStmt = $dbConn->prepare($alertSql);
        $alertStmt->bindValue(':alertType', Udoh\Emsa\Constants\SystemAlerts::UNRECOGNIZED_SENDING_FACILITY, \PDO::PARAM_INT);
        $alertStmt->bindValue(':sendingFacilityId', $checkName, \PDO::PARAM_STR);
        $alertStmt->bindValue(':originalMessageId', trim($originalMessageId), \PDO::PARAM_STR);
        $alertStmt->execute();
        
        $alertStmt = null;
    } catch (Throwable $e) {
        Udoh\Emsa\Utils\ExceptionUtils::logException($e);
    }

    ob_clean();
    header($_SERVER['SERVER_PROTOCOL'] . " 200 OK", TRUE, 200);
    header('Content-Type: text/plain; charset=UTF-8');
    echo 'Invalid sender';
    exit;
}

$dbConn = null;
$emsaDbFactory = null;

exit;

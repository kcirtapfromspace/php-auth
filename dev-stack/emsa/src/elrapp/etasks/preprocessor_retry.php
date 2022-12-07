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

if (!class_exists('\Udoh\Emsa\Auth\Authenticator') || !Udoh\Emsa\Auth\Authenticator::userHasPermission(Udoh\Emsa\Auth\Authenticator::URIGHTS_ADMIN)) {
    ob_end_clean();
    header($_SERVER['SERVER_PROTOCOL'] . " 404 Not Found", TRUE, 404);
    exit;
}

$retrySql = "UPDATE system_original_messages SET sent = -1 WHERE id IN (
                SELECT DISTINCT(om.id) AS id 
                FROM system_original_messages om 
                INNER JOIN preprocessor_exceptions pe ON (pe.system_original_messages_id = om.id) 
                WHERE (om.connector = :labId) 
                AND (pe.exception_message = :retryExceptionId)";

if (!EmsaUtils::emptyTrim($clean['retry_exception_value'])) {
    $retrySql .= "\nAND (pe.info = :retryExceptionValue)";
}

$retrySql .= ");";

try {
    $retryStmt = $dbConn->prepare($retrySql);
    $retryStmt->bindValue(':labId', $clean['lab_id'], PDO::PARAM_STR);
    $retryStmt->bindValue(':retryExceptionId', $clean['retry_exception_id'], PDO::PARAM_STR);
    if (!EmsaUtils::emptyTrim($clean['retry_exception_value'])) {
        $retryStmt->bindValue(':retryExceptionValue', $clean['retry_exception_value'], PDO::PARAM_STR);
    }
    
    $retryStmt->execute();
    
    Udoh\Emsa\Utils\DisplayUtils::drawHighlight('Messages queued for retry!', 'ui-icon-elrsuccess');
} catch (Throwable $e) {
    Udoh\Emsa\Utils\ExceptionUtils::logException($e);
    Udoh\Emsa\Utils\DisplayUtils::drawError('Unable to retrieve list of messages to retry.');
}

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

/**
 * Utilities for logging audits for GraylistRequest processing
 *
 * @author Josh Ridderhoff <jridderhoff@utah.gov>
 */
class GraylistRequestAuditor
{

    /**
     * Records a Graylist Request Audit Log entry using the current logged-in user.
     * 
     * @static
     * 
     * @param PDO $dbConn PDO connection to EMSA database
     * @param int $requestId Graylist Request ID
     * @param int $actionId ID corresponding to the logged action (from system_message_actions)
     * @param int $status Message queue ID where the action was generated from
     * @param string $info [Optional]<br>Comments explaining the audited event
     * 
     * @return int Graylist Request Audit Log ID
     * 
     * @throws Exception if missing required fields or if audit log was unsuccessful.
     * @throws PDOException on any database errors.
     */
    public static function auditRequest(PDO $dbConn, $requestId = null, $actionId = null, $status = null, $info = null)
    {
        if (empty($requestId) || empty($actionId) || empty($status)) {
            throw new Exception('Unable to log request audit:  Missing required fields.');
        }

        $auditId = 0;

        $sql = 'INSERT INTO graylist_request_audits
				(user_id, message_action_id, graylist_request_id, created_at, system_status_id, info)
				VALUES (:user_id, :action_id, :request_id, LOCALTIMESTAMP, :status, :info)
				RETURNING id;';
        $stmt = $dbConn->prepare($sql);
        $stmt->bindValue(':user_id', trim($_SESSION[EXPORT_SERVERNAME]['umdid']), PDO::PARAM_STR);
        $stmt->bindValue(':action_id', $actionId, PDO::PARAM_INT);
        $stmt->bindValue(':request_id', $requestId, PDO::PARAM_INT);
        $stmt->bindValue(':status', $status, PDO::PARAM_INT);
        $stmt->bindValue(':info', $info, PDO::PARAM_STR);

        if ($stmt->execute()) {
            $auditId = intval($stmt->fetchColumn(0));
        }

        if (!($auditId > 0)) {
            throw new Exception('Audit log was not successful.');
        }

        return intval($auditId);
    }

}

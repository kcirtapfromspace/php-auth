<?php

namespace Udoh\Emsa\Utils;

/**
 * Copyright (c) 2017 Utah Department of Technology Services and Utah Department of Health
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
 * @copyright Copyright (c) 2017 Utah Department of Technology Services and Utah Department of Health
 */

use PDO;
use Throwable;
use Udoh\Emsa\Client\AppClientInterface;

/**
 * Utilities for dealing with Message Audit Logging
 *
 * @author Josh Ridderhoff <jridderhoff@utah.gov>
 */
class AuditUtils
{

    /**
     * Record an Audit Log event for an ELR message using the current logged-in user
     * Returns an Audit Log ID on success, or FALSE on error.
     *
     * @param PDO    $dbConn                PDO connection to the EMSA database
     * @param int    $systemMessageId       EMSA System Message ID (from system_messages)
     * @param int    $systemMessageActionId ID corresponding to the logged action (from system_message_actions)
     * @param int    $finalStatus           Message queue ID where the action was generated from
     * @param string $auditComment          Optional comments explaining the audited event
     * @param int    $labId                 Lab ID for the specified message (Optional)
     *
     * @return int|bool
     */
    public static function auditMessage(PDO $dbConn, ?int $systemMessageId = null, ?int $systemMessageActionId = null, ?int $finalStatus = null, ?string $auditComment = null, ?int $labId = 1)
    {
        if (is_null($systemMessageId) || is_null($systemMessageActionId) || is_null($finalStatus)) {
            return false;
        }
        
        if (is_null(\EmsaUtils::getQueueIdByMessageId($dbConn, $systemMessageId))) {
            // if message no longer exists, don't try to log an audit for it.
            return false;
        }

        $labId = $labId ?? 1;

        $cleanComment = null; // postgresql NULL string
        if (!is_null($auditComment)) {
            $tempComment = filter_var($auditComment, FILTER_SANITIZE_STRING, FILTER_FLAG_NO_ENCODE_QUOTES);
            if (($tempComment !== false) && (strlen(trim($tempComment)) > 0)) {
                $cleanComment = trim($tempComment);
            }
        }

        try {
            $sql = "INSERT INTO system_messages_audits 
                        (created_at, user_id, message_action_id, system_message_id, lab_id, fname, lname, system_status_id, info) 
                    VALUES (
                        LOCALTIMESTAMP, 
                        :umdId, 
                        :actionId, 
                        :systemMessageId, 
                        :labId, 
                        'EMSA', 
                        'EMSA', 
                        :finalStatus, 
                        :comments
                    ) RETURNING id;";
            $stmt = $dbConn->prepare($sql);
            $stmt->bindValue(':umdId', trim($_SESSION[EXPORT_SERVERNAME]['umdid']), PDO::PARAM_STR);
            $stmt->bindValue(':actionId', intval($systemMessageActionId), PDO::PARAM_INT);
            $stmt->bindValue(':systemMessageId', intval($systemMessageId), PDO::PARAM_INT);
            $stmt->bindValue(':labId', intval($labId), PDO::PARAM_INT);
            $stmt->bindValue(':finalStatus', intval($finalStatus), PDO::PARAM_INT);
            $stmt->bindValue(':comments', $cleanComment, PDO::PARAM_STR);

            if ($stmt->execute()) {
                $row = $stmt->fetchObject();
                $result = intval($row->id);
            } else {
                $result = false;
            }
        } catch (Throwable $e) {
            ExceptionUtils::logException($e);
            $result = false;
        } finally {
            $stmt = null;
        }

        return $result;
    }

    /**
     * Records an audit of actions modifiying Application XML, including previous version of Application XML in the event of a record update.
     *
     * @param PDO    $dbConn         PDO connection to the EMSA database
     * @param string $originalAppXML Previous Application XML (if updating a previous record)
     * @param string $assignedAppXML Modified version of the Application XML being assigned
     * @param int    $auditId        ID from system_messages_audits that this update generated
     * @param bool   $isUpdate       If true, this is for an 'Update Record' event, otherwise for an 'Add Record' event
     *
     * @return bool
     */
    public static function auditXML(PDO $dbConn, ?string $originalAppXML = null, ?string $assignedAppXML = null, ?int $auditId = null, ?bool $isUpdate = false): bool
    {
        $isUpdate = $isUpdate ?? false;

        if (empty($assignedAppXML) || empty($auditId)) {
            return false;
        }

        if (empty($originalAppXML)) {
            $originalAppXML = null;  // ensure null for PDO instead of empty string
        }

        try {
            $sql = "INSERT INTO system_nedss_xml_audits 
                        (is_update, system_messages_audits_id, previous_xml, sent_xml) 
                    VALUES 
                        (:isUpdate, :auditId, :origXML, :newXML);";
            $stmt = $dbConn->prepare($sql);
            $stmt->bindValue(':auditId', $auditId, PDO::PARAM_INT);
            $stmt->bindValue(':origXML', $originalAppXML, PDO::PARAM_STR);
            $stmt->bindValue(':newXML', $assignedAppXML, PDO::PARAM_STR);
            $stmt->bindValue(':isUpdate', $isUpdate, PDO::PARAM_BOOL);

            if ($stmt->execute()) {
                return true;
            }
        } catch (Throwable $ex) {
            ExceptionUtils::logException($ex);
        }

        return false;
    }

    /**
     * Display the Audit Log for a given EMSA message and return it as an HTML table
     *
     * @param AppClientInterface $authClient      Application client used for authentication.
     * @param PDO                $dbConn          PDO connection to the EMSA database.
     * @param int                $systemMessageId EMSA System Message ID
     *
     * @return string
     */
    public static function getAuditLog(AppClientInterface $authClient, PDO $dbConn, int $systemMessageId): string
    {
        $html = '<table class="audit_log"><thead><tr><th>Date/Time</th><th>User</th><th>Event Category</th><th>Event Action</th><th>Event Status</th><th>XML Before Changes</th><th>XML Changes Sent</th></tr></thead><tbody>';

        try {
            $sql = "SELECT au.user_id AS user_id, ac.message AS action, au.info as info, ca.name AS category, au.created_at AS created_at, ss.name AS status, xa.id AS xml_audit_id, xa.previous_xml AS previous_xml, xa.sent_xml AS sent_xml 
                    FROM system_messages_audits au 
                    JOIN system_message_actions ac ON (au.message_action_id = ac.id)
                    JOIN system_action_categories ca ON (ac.action_category_id = ca.id)
                    JOIN system_statuses ss ON (au.system_status_id = ss.id) 
                    LEFT JOIN system_nedss_xml_audits xa ON (xa.system_messages_audits_id = au.id)
                    WHERE au.system_message_id = :systemMessageId
                    ORDER BY au.created_at;";
            $stmt = $dbConn->prepare($sql);
            $stmt->bindValue(':systemMessageId', intval($systemMessageId), PDO::PARAM_INT);
            if ($stmt->execute() && ($stmt->rowCount() > 0)) {
                while ($row = $stmt->fetchObject()) {
                    $html .= '<tr>';
                    $html .= '<td style="white-space: nowrap; font-weight: bold;">' . DisplayUtils::xSafe(DateTimeUtils::createMixed(trim($row->created_at))->format("m/d/Y H:i:s.u")) . '</td>';
                    $html .= '<td style="white-space: nowrap;">' . AppClientUtils::userFullNameByUserId($authClient, trim($row->user_id)) . '</td>';
                    $html .= '<td style="white-space: nowrap;">' . DisplayUtils::xSafe(trim($row->category)) . '</td>';
                    $html .= '<td>' . DisplayUtils::xSafe(trim($row->action)) . ((strlen(trim($row->info)) > 0) ? '<br>Comments: ' . DisplayUtils::xSafe(trim($row->info)) : '') . '</td>';
                    $html .= '<td>' . DisplayUtils::xSafe(trim($row->status)) . '</td>';
                    $html .= '<td>' . ((!empty($row->previous_xml)) ? '<button title="View event XML before changes" class="audit_view_xml" value=\'' . json_encode(array('type' => 1, 'id' => intval($row->xml_audit_id))) . '\'>View</button>' : '--') . '</td>';
                    $html .= '<td>' . ((!empty($row->sent_xml)) ? '<button title="View XML changes sent" class="audit_view_xml" value=\'' . json_encode(array('type' => 2, 'id' => intval($row->xml_audit_id))) . '\'>View</button>' : '--') . '</td>';
                    $html .= '</tr>';
                }
            } else {
                $html .= '<tr><td colspan="5"><em>No events logged</em></td></tr>';
            }
        } catch (Throwable $e) {
            ExceptionUtils::logException($e);
        } finally {
            $stmt = null;
        }

        $html .= '</tbody></table>';

        return $html;
    }

}

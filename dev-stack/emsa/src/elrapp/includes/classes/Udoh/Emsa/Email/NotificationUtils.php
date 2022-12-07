<?php

namespace Udoh\Emsa\Email;

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

use Throwable;

/**
 * Support utilities for processing e-mail notifications.
 * 
 * @package Udoh\Emsa\Email
 *
 * @author Josh Ridderhoff <jridderhoff@utah.gov>
 */
class NotificationUtils
{
    
    /**
     * Record the results of the attempt to send a notification e-mail in the Batch Notification Log.
     * 
     * @param \PDO $dbConn
     * @param string $toAddress E-mail address the notification was sent to.
     * @param integer[] $affectedNotifications Array of notification IDs included in this notification.
     * @param boolean $success [Optional]<br><b>TRUE</b> if notification was successfully sent, <b>FALSE</b> otherwise.
     * @param boolean $custom [Optional]<br><b>TRUE</b> if notification is for a custom (virtual) jurisdiction, <b>FALSE</b> otherwise.
     * @param integer $jurisdictionId [Optional]<br>If notification is for a specific local health department or virtual jurisdiction, specify the jurisdiction ID.  Leave blank for State-level notifications.
     * 
     * @return boolean
     */
    public static function logNotificationResult(\PDO $dbConn, $toAddress, array $affectedNotifications, $success = false, $custom = false, $jurisdictionId = null)
    {
        $cleanAffectectedNotifications = [];
        $cleanJurisdictionId = 0;
        
        if (!empty($jurisdictionId)) {
            $cleanJurisdictionId = (int) filter_var($jurisdictionId, \FILTER_SANITIZE_NUMBER_INT);
        }

        if (is_array($affectedNotifications) && (count($affectedNotifications) > 0)) {
            foreach ($affectedNotifications as $affectedNotification) {
                $cleanAffectectedNotifications[] = (int) filter_var($affectedNotification, \FILTER_SANITIZE_NUMBER_INT);
            }
        }
        
        if (!empty($cleanAffectectedNotifications)) {
            $affectedNotificationStr = (string) implode(',', $cleanAffectectedNotifications);
            
            if (!empty($cleanJurisdictionId)) {
                try {
                    $sql = "INSERT INTO batch_notification_log (email, jurisdiction, notification_ids, success, custom) 
                            VALUES (:toAddress, :jId, :affectedIds, :success, :custom);";
                    $stmt = $dbConn->prepare($sql);
                    $stmt->bindValue(':toAddress', (string) filter_var($toAddress, \FILTER_SANITIZE_STRING), \PDO::PARAM_STR);
                    $stmt->bindValue(':jId', $cleanJurisdictionId, \PDO::PARAM_INT);
                    $stmt->bindValue(':affectedIds', $affectedNotificationStr, \PDO::PARAM_STR);
                    $stmt->bindValue(':success', (bool) $success, \PDO::PARAM_BOOL);
                    $stmt->bindValue(':custom', (bool) $custom, \PDO::PARAM_BOOL);
                    $stmt->execute();
                } catch (Throwable $e) {
                    \Udoh\Emsa\Utils\ExceptionUtils::logException($e);
                    return false;
                }
            } else {
                try {
                    $sql = "INSERT INTO batch_notification_log (email, notification_ids, success, custom) 
                            VALUES (:toAddress, :affectedIds, :success, :custom);";
                    $stmt = $dbConn->prepare($sql);
                    $stmt->bindValue(':toAddress', (string) filter_var($toAddress, \FILTER_SANITIZE_STRING), \PDO::PARAM_STR);
                    $stmt->bindValue(':affectedIds', $affectedNotificationStr, \PDO::PARAM_STR);
                    $stmt->bindValue(':success', (bool) $success, \PDO::PARAM_BOOL);
                    $stmt->bindValue(':custom', (bool) $custom, \PDO::PARAM_BOOL);
                    $stmt->execute();
                } catch (Throwable $e) {
                    \Udoh\Emsa\Utils\ExceptionUtils::logException($e);
                    return false;
                }
            }
        }
        
        return true;
    }

    /**
     * Mark a list of affected notifications as being sent to local health departments.
     * 
     * @param \PDO $dbConn
     * @param integer[] $affectedNotifications Array of notification IDs to mark as sent.
     * 
     * @return boolean
     */
    public static function markNotificationSentLHD(\PDO $dbConn, array $affectedNotifications)
    {
        $cleanAffectectedNotifications = [];

        if (is_array($affectedNotifications) && (count($affectedNotifications) > 0)) {
            foreach ($affectedNotifications as $affectedNotification) {
                $cleanAffectectedNotifications[] = (int) filter_var($affectedNotification, \FILTER_SANITIZE_NUMBER_INT);
            }
        }

        if (!empty($cleanAffectectedNotifications)) {
            try {
                $inPlaceholder = implode(',', array_fill(0, count($cleanAffectectedNotifications), '?'));
                $sql = "UPDATE batch_notifications 
                        SET date_sent_lhd = localtimestamp 
                        WHERE id IN ($inPlaceholder);";
                $stmt = $dbConn->prepare($sql);
                $stmt->execute($cleanAffectectedNotifications);
            } catch (Throwable $e) {
                \Udoh\Emsa\Utils\ExceptionUtils::logException($e);
                return false;
            }
        }
        
        return true;
    }
    
    /**
     * Mark a list of affected notifications as being sent to State recipients.
     * 
     * @param \PDO $dbConn
     * @param integer[] $affectedNotifications Array of notification IDs to mark as sent.
     * 
     * @return boolean
     */
    public static function markNotificationSentState(\PDO $dbConn, array $affectedNotifications)
    {
        $cleanAffectectedNotifications = [];

        if (is_array($affectedNotifications) && (count($affectedNotifications) > 0)) {
            foreach ($affectedNotifications as $affectedNotification) {
                $cleanAffectectedNotifications[] = (int) filter_var($affectedNotification, \FILTER_SANITIZE_NUMBER_INT);
            }
        }

        if (!empty($cleanAffectectedNotifications)) {
            try {
                $inPlaceholder = implode(',', array_fill(0, count($cleanAffectectedNotifications), '?'));
                $sql = "UPDATE batch_notifications 
                        SET date_sent_state = localtimestamp 
                        WHERE id IN ($inPlaceholder);";
                $stmt = $dbConn->prepare($sql);
                $stmt->execute($cleanAffectectedNotifications);
            } catch (Throwable $e) {
                \Udoh\Emsa\Utils\ExceptionUtils::logException($e);
                return false;
            }
        }
        
        return true;
    }
    
    /**
	 * Checks if batch notification type is used for custom jurisdictions or not.
	 *
     * @param \PDO $dbConn PDO connection to the EMSA database.
	 * @param int $notificationTypeId ID corresponding to the batch notification type to check.
     * 
	 * @return boolean|integer Returns virtual jurisdiction ID corresponding to notification type if custom, <b>FALSE</b> otherwise.
	 */
	public static function isCustomNotificationTypeWithId(\PDO $dbConn, $notificationTypeId = null) {
		if (empty($notificationTypeId)) {
			return false;
		}
		
        try {
            $sql = "SELECT custom 
                    FROM batch_notification_types 
                    WHERE id = :nTypeId AND custom IS NOT NULL AND custom > 0;";
            $stmt = $dbConn->prepare($sql);
            $stmt->bindValue(':nTypeId', (int) $notificationTypeId, \PDO::PARAM_INT);
            $stmt->execute();
            
            if ($stmt->rowCount() > 0) {
                return (int) filter_var($stmt->fetchColumn(0), \FILTER_SANITIZE_NUMBER_INT);
            } else {
                return false;
            }
        } catch (Throwable $e) {
            \Udoh\Emsa\Utils\ExceptionUtils::logException($e);
            return false;
        }
	}

}

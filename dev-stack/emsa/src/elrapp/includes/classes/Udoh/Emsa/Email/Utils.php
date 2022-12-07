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
use PHPMailer\PHPMailer\PHPMailer;
use Udoh\Emsa\Utils\ExceptionUtils;

/**
 * Functionality related to sending e-mails, including notifications.
 *
 * @package Udoh\Emsa\Email
 * 
 * @author Josh Ridderhoff <jridderhoff@utah.gov>
 */
class Utils
{

    /**
     * Create a new PHPMailer object pre-configured for EMSA set to send via SMTP.
     *
     * Does not set to/from addresses or add any message subject, body, or attachments.
     *
     * @return null|PHPMailer Returns a <b>PHPMailer</b> object, or <b>NULL</b> in case of errors.
     */
    public static function smtpFactory(): ?PHPMailer
    {
        try {
            $mailObj = new PHPMailer(true);

            $mailObj->isSMTP();
            $mailObj->SMTPAuth = false;
            $mailObj->isHTML(true);

            $mailObj->SMTPOptions = array(
                'ssl' => array(
                    'verify_peer' => false,
                    'verify_peer_name' => false,
                    'allow_self_signed' => true
                )
            );

            $mailObj->Host = \SMTP_HOST;
            $mailObj->Port = \SMTP_PORT;
            $mailObj->Helo = \SMTP_HELO;

            $mailObj->CharSet = 'UTF-8';
            $mailObj->ContentType = 'text/html';
            $mailObj->Priority = 1;
            $mailObj->Encoding = 'quoted-printable';

            return $mailObj;
        } catch (Throwable $e) {
            ExceptionUtils::logException($e);
            return null;
        }
    }

    /**
     * Send an e-mail via SMTP.
     *
     * @param array  $sender      Sender's name & e-mail address, represented by array(string <i>address</i> [, string <i>name</i>]).
     * @param string $subject
     * @param string $body
     * @param array  $toList      Array of recipients for the 'To:' field.  Each recipient represented by array(string <i>address</i> [, string <i>name</i>]).  At least one recipient is required.
     * @param array  $ccList      [Optional] Array of recipients for the 'CC:' field.  Each CC:'ed recipient represented by array(string <i>address</i> [, string <i>name</i>]).
     * @param array  $attachments [Optional] Array of files to attach to the e-mail being sent.  Each attachment represented by array(string <i>path</i> [, string <i>filename</i>]).  (If <i>filename</i> is omitted, actual filename is used.)
     *
     * @return bool
     */
    public static function sendMail(array $sender, string $subject, string $body, array $toList, array $ccList = null, array $attachments = null): bool
    {
        $fromAddrSanitized = null;
        $fromNameSanitized = null;
        
        if (isset($sender['address']) && !empty($sender['address'])) {
            $fromAddrSanitized = (string) filter_var($sender['address'], \FILTER_SANITIZE_EMAIL);
        }

        if (isset($sender['name']) && !empty($sender['name'])) {
            $fromNameSanitized = (string) filter_var($sender['name'], \FILTER_SANITIZE_STRING);
        }

        if ((strlen($fromAddrSanitized) > 0) && (count($toList) > 0)) {
            try {
                $phpMailer = self::smtpFactory();

                $phpMailer->setFrom($fromAddrSanitized, $fromNameSanitized);
                $phpMailer->Subject = $subject;
                $phpMailer->Body = $body;

                // add each 'to' address...
                foreach ($toList as $toAddressPair) {
                    if (isset($toAddressPair['address']) && !\EmsaUtils::emptyTrim((string)filter_var($toAddressPair['address'], \FILTER_VALIDATE_EMAIL))) {
                        if (isset($toAddressPair['name']) && !\EmsaUtils::emptyTrim((string)filter_var($toAddressPair['name'], \FILTER_SANITIZE_STRING))) {
                            $phpMailer->addAddress((string)filter_var($toAddressPair['address'], \FILTER_SANITIZE_EMAIL), (string)filter_var($toAddressPair['name'], \FILTER_SANITIZE_STRING));
                        } else {
                            $phpMailer->addAddress((string)filter_var($toAddressPair['address'], \FILTER_SANITIZE_EMAIL));
                        }
                    }
                }

                // add each 'cc' address...
                if (isset($ccList) && !empty($ccList)) {
                    foreach ($ccList as $ccAddressPair) {
                        if (isset($ccAddressPair['address']) && !\EmsaUtils::emptyTrim((string)filter_var($ccAddressPair['address'], \FILTER_VALIDATE_EMAIL))) {
                            if (isset($ccAddressPair['name']) && !\EmsaUtils::emptyTrim((string)filter_var($ccAddressPair['name'], \FILTER_SANITIZE_STRING))) {
                                $phpMailer->addCC((string)filter_var($ccAddressPair['address'], \FILTER_SANITIZE_EMAIL), (string)filter_var($ccAddressPair['name'], \FILTER_SANITIZE_STRING));
                            } else {
                                $phpMailer->addCC((string)filter_var($ccAddressPair['address'], \FILTER_SANITIZE_EMAIL));
                            }
                        }
                    }
                }

                // add any attachments...
                if (isset($attachments) && !empty($attachments)) {
                    foreach ($attachments as $attachment) {
                        if (isset($attachment['path']) && !empty($attachment['path']) && is_readable($attachment['path'])) {
                            if (isset($attachment['filename']) && !empty($attachment['filename'])) {
                                $phpMailer->addAttachment($attachment['path'], $attachment['filename']);
                            } else {
                                $phpMailer->addAttachment($attachment['path']);
                            }
                        }
                    }
                }

                return $phpMailer->send();
            } catch (Throwable $e) {
                ExceptionUtils::logException($e);
                return false;
            }
        }
        
        return false;
    }
    
    /**
     * Get the ID of the Application role corresponding to ELR Notifications.
     * 
     * @param \Udoh\Emsa\Client\AppClientInterface $appClient
     * @return int
     */
    public static function getNotificationRoleId(\Udoh\Emsa\Client\AppClientInterface $appClient)
    {
        $notificationRoleId = null;
        
        try {
            $appRoles = $appClient->getRoles();
            
            foreach ($appRoles as $appRoleId => $appRoleName) {
                if ($appRoleName == \ELR_NOTIFY_ROLE_NAME) {
                    $notificationRoleId = (int) $appRoleId;
                }
            }
        } catch (Throwable $ex) {
            ExceptionUtils::logException($ex);
        }
        
        return $notificationRoleId;
    }

    /**
     * Get back a list of e-mail addresses for all application users assigned to the "ELR Notification" role.
     * If no users found, or if an error occurs, returns an empty array.
     *
     * @param \Udoh\Emsa\Client\AppClientInterface $appClient Application Client to search.
     * @param int $jurisdictionId Application-specific Jurisdiction ID to retrieve.
     * 
     * @return array
     */
    public static function getNotificationEmailAddressesByJurisdiction(\Udoh\Emsa\Client\AppClientInterface $appClient, $jurisdictionId)
    {
        $cleanJurisdictionId = (int) filter_var($jurisdictionId, \FILTER_SANITIZE_NUMBER_INT);
        $notificationRoleId = self::getNotificationRoleId($appClient);

        if (!empty($cleanJurisdictionId) && !empty($notificationRoleId)) {
            try {
                $emailAddresses = $appClient->getUsers($notificationRoleId, $cleanJurisdictionId)->getEmailAddresses();
            } catch (Throwable $e) {
                ExceptionUtils::logException($e);
                return array();
            }
            
            return $emailAddresses;
        } else {
            return array();
        }
    }

    /**
     * Get back a list of e-mail addresses for a custom notification jurisdiction.
     * If no users found, or if an error occurs, returns empty array.
     *
     * @param \PDO $dbConn Active EMSA database connection.
     * @param int $jurisdictionId Custom Jurisdiction ID to search against.
     * @return array
     */
    public static function getEmailAddressesByCustomJurisdiction(\PDO $dbConn, $jurisdictionId)
    {
        $emailArray = array();

        if (!is_null($jurisdictionId)) {
            $jId = (int) filter_var($jurisdictionId, \FILTER_SANITIZE_NUMBER_INT);

            try {
                $sql = "SELECT recipients 
                    FROM batch_notification_custom_jurisdictions 
                    WHERE id = :jId;";
                $stmt = $dbConn->prepare($sql);
                $stmt->bindValue(':jId', $jId, \PDO::PARAM_INT);
                $stmt->execute();

                $recipientsRaw = (string) filter_var($stmt->fetchColumn(0), \FILTER_SANITIZE_STRING);
                $emailArray = explode(';', trim($recipientsRaw));
            } catch (Throwable $e) {
                ExceptionUtils::logException($e);
            }
        }
        return $emailArray;
    }

    /**
     * Returns a boolean TRUE or FALSE indicating whether the custom notification jurisdiction 
     * (specified by ID) should include a column with a link back to the original ELR message
     * in the Excel spreadsheet
     *
     * @param \PDO $dbConn Active EMSA database connection.
     * @param int $customJurisdictionId Custom Jurisdiction ID
     * @return bool
     */
    public static function useLinkToLab(\PDO $dbConn, $customJurisdictionId)
    {
        $linkToLab = false;

        if (is_null($customJurisdictionId)) {
            return $linkToLab;
        }

        $jId = (int) filter_var($customJurisdictionId, \FILTER_SANITIZE_NUMBER_INT);

        try {
            $sql = "SELECT count(id) FROM batch_notification_custom_jurisdictions 
                    WHERE id = :jId
                    AND link_to_lab IS TRUE;";
            $stmt = $dbConn->prepare($sql);
            $stmt->bindValue(':jId', $jId, \PDO::PARAM_INT);
            $stmt->execute();

            $rowsFound = (int) filter_var($stmt->fetchColumn(0), \FILTER_SANITIZE_NUMBER_INT);

            if ($rowsFound > 0) {
                $linkToLab = true;
            }
        } catch (Throwable $e) {
            ExceptionUtils::logException($e);
        }

        return $linkToLab;
    }

}

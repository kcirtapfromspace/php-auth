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

include_once __DIR__ . '/../includes/app_config.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xls;
use Udoh\Emsa\Email\NotificationUtils;
use Udoh\Emsa\Email\Utils;
use Udoh\Emsa\Utils\AppClientUtils;
use Udoh\Emsa\Utils\DisplayUtils;
use Udoh\Emsa\Utils\ExceptionUtils;

try {
    $dbConn = $emsaDbFactory->getConnection();
} catch (Throwable $e) {
    ExceptionUtils::logException($e);
    DisplayUtils::drawError("Unable to establish connection via PDO.");
}

// explanation to be attached to State Epi notification e-mails
$primerState = <<<EOPS
<font color="darkslategray">
    <hr>
    <h2>Daily ELR Notification for the Bureau of Epidemiology</h2>
    <p>
        ELR generates a spreadsheet daily that is emailed to epidemiologists within the UDOH Bureau of Epidemiology highlighting specific EpiTrax cases that have been created or updated with ELR information in the previous 24 hours. 
    </p>
    <p style="margin-bottom: 2em;">
        This document specifies the six different flags that generate a notification, and how that information may be helpful to case management. Each flag is represented by a different tab in the emailed spreadsheet.
    </p>
            
    <h3 style="display: inline;">Flag #1: Immediately Notifiable Conditions</h3>
    <p style="margin-top: 0.5em; margin-bottom: 2em;">
        <font color="crimson"><em>This is a UDOH-only notification.  Local health departments do not receive this notification.</em></font><br>
        <em>What Gets Flagged?</em><br>
        Cases created by ELR that are associated with immediately notifiable conditions.<br>
        <em>Why is This Flagged?</em><br>
        Immediately notifiable conditions generally require a prompt response, and this notification can help to identify priority cases in queues.<br>
    </p>
            
    <h3 style="display: inline;">Flag #2: Notify State Upon Receipt</h3>
    <p style="margin-top: 0.5em;">
        <font color="crimson"><em>This is a UDOH-only notification.  Local health departments do not receive this notification.</em></font><br>
        <em>What Gets Flagged?</em><br>
        Cases with a lab created by ELR that UDOH epis have asked for notification on.
    </p>
    <ul>
        <li>All immediately notifiable conditions, and:</li>
        <li>Acinetobacter - Carbapenem resistant</li>
        <li>Brucellosis</li>
        <li>Carbapenemase Producing Organism</li>
        <li>Chagas Disease</li>
        <li>Chikungunya virus</li>
        <li>E. coli - Carbapenem resistant</li>
        <li>Enterobacter species - Carbapenem resistant</li>
        <li>Hantavirus infection</li>
        <li>Klebsiella - Ccarbapenem resistant</li>
        <li>Legionellosis</li>
        <li>Listeriosis</li>
        <li>Q Fever, acute and chronic</li>
        <li>Zika virus disease</li>
    </ul>
    <p style="margin-bottom: 2em;">
        <em>Why is This Flagged?</em><br>
        There are a number of conditions that UDOH epidemiologists prefer to know about during the investigation process.<br>
    </p>
            
    <h3 style="display: inline;">Flag #3: Closed Morbidity Event Lab Update</h3>
    <p style="margin-top: 0.5em; margin-bottom: 2em;">
        <em>What Gets Flagged?</em><br>
        Cases in your jurisdiction with a workflow status of &ldquo;closed&rdquo;, that have had a laboratory result added by ELR.<br>
        <em>Why is This Flagged?</em><br>
        Although a task is also generated, if the assigned investigator no longer works for you, no one will receive it.<br>
    </p>
            
    <h3 style="display: inline;">Flag #4: Contact Event Lab Update</h3>
    <p style="margin-top: 0.5em; margin-bottom: 2em;">
        <em>What Gets Flagged?</em><br>
        Contacts in your jurisdiction that have had a laboratory result added by ELR.<br>
        <em>Why is This Flagged?</em><br>
        Contacts typically have a workflow of &ldquo;not participating in workflow&rdquo;, which means no investigator is assigned to receive a task, and therefore there is no active notification that any labs have been added.<br>
    </p>
            
    <h3 style="display: inline;">Flag #5: Surveillance Event</h3>
    <p style="margin-top: 0.5em;">
        <em>What Gets Flagged?</em><br>
        Surveillance events created in your jurisdiction by ELR.<br>
        <em>Why is This Flagged?</em><br>
        Surveillance events do not need to be investigated. Once entered into EpiTrax, they are immediately closed, and bypass investigation (and notification) queues.<br>
        Surveillance events include:
    </p>
    <ul style="margin-bottom: 2em;">
        <li>Influenza activity</li>
        <li>Non-elevated blood lead results</li>
        <li>Clostridium difficile infection</li>
        <li>Invasive streptococcal disease, other (non- Strep pneumo, GAS, or GBS)</li>
        <li>Norovirus</li>
        <li>Atypical Mycobacteria</li>
        <li>TB – IGRA results</li>
    </ul>
            
    <h3 style="display: inline;">Flag #6: Pregnancy Events</h3>
    <p style="margin-top: 0.5em; margin-bottom: 2em;">
        <font color="crimson"><em>This is a UDOH-only notification.  Local health departments do not receive this notification.</em></font><br>
        <em>What Gets Flagged?</em><br>
        Cases with a lab created by ELR and supplemental information that may indicate pregnancy. <br>
        <em>Why is This Flagged?</em><br>
        ELR does not update the pregnancy field in EpiTrax.<br>
        <em>Note:</em><br>
        Cases will trigger if there is information indicating the visit was an encounter for supervision of pregnancy, or if a pregnancy test was conducted. Currently, the only way to identify the trigger is to review the HL7 message that is put into the Notes field. If you do a simple search (<code>CTRL+F</code>) and type in &ldquo;pregnancy&rdquo; you should be able to find it.<br>
        Not available all of the time (ie, if they are pregnant, this won’t always be able to tell you), and we do not update the pregnancy field in EpiTrax.<br>
    </p>
</font>
EOPS;

// explanation to be attached to LHD notification e-mails
$primerLHD = <<<EOPL
<font color="darkslategray">
    <hr>
    <h2>Daily ELR Notification for Local Health Departments</h2>
    <p>
        UDOH creates a spreadsheet daily that is emailed to at least one representative at each local health department highlighting specific EpiTrax cases that have been created or updated with ELR information in the previous 24 hours. 
    </p>
    <p style="margin-bottom: 2em;">
        This document specifies the three different flags that generate a notification, and how that information may be helpful to case management. Each flag is represented by a different tab in the emailed spreadsheet.
    </p>
            
    <h3 style="display: inline;">Flag #1: Closed Morbidity Event Lab Update</h3>
    <p style="margin-top: 0.5em; margin-bottom: 2em;">
        <em>What Gets Flagged?</em><br>
        Cases in your jurisdiction with a workflow status of &ldquo;closed&rdquo;, that have had a laboratory result added by ELR.<br>
        <em>Why is This Flagged?</em><br>
        Although a task is also generated, if the assigned investigator no longer works for you, no one will receive it.<br>
    </p>
            
    <h3 style="display: inline;">Flag #2: Contact Event Lab Update</h3>
    <p style="margin-top: 0.5em; margin-bottom: 2em;">
        <em>What Gets Flagged?</em><br>
        Contacts in your jurisdiction that have had a laboratory result added by ELR.<br>
        <em>Why is This Flagged?</em><br>
        Contacts typically have a workflow of &ldquo;not participating in workflow&rdquo;, which means no investigator is assigned to receive a task, and therefore there is no active notification that any labs have been added.<br>
    </p>
            
    <h3 style="display: inline;">Flag #3: Surveillance Event</h3>
    <p style="margin-top: 0.5em;">
        <em>What Gets Flagged?</em><br>
        Surveillance events created in your jurisdiction by ELR.<br>
        <em>Why is This Flagged?</em><br>
        Surveillance events do not need to be investigated. Once entered into EpiTrax, they are immediately closed, and bypass investigation (and notification) queues.<br>
        Surveillance events include:
    </p>
    <ul style="margin-bottom: 2em;">
        <li>Influenza activity</li>
        <li>Non-elevated blood lead results</li>
        <li>Clostridium difficile infection</li>
        <li>Invasive streptococcal disease, other (non- Strep pneumo, GAS, or GBS)</li>
        <li>Norovirus</li>
        <li>Atypical Mycobacteria</li>
        <li>TB – IGRA results</li>
    </ul>
</font>
EOPL;

// explanation to be attached to Custom Jursdiction notification e-mails
$primerCustom = <<<EOPC
<font color="darkslategray">
    <hr>
    <h2>Daily ELR Notification for Select BOE Groups</h2>
    <p>
        ELR generates a spreadsheet daily that is emailed to select groups within the UDOH Bureau of Epidemiology highlighting certain EpiTrax cases that have been created or updated with ELR information in the previous 24 hours. 
    </p>
    <p style="margin-bottom: 2em;">
        This document specifies the various flags that may generate one of these specialized notifications, and how that information may be helpful to case management. Each flag is represented by a different tab in the emailed spreadsheet, although depending on the specific group each notification is sent to, not all of the following sections will be included in the spreadsheet you receive.
    </p>
            
    <h3 style="display: inline;">Low CD4 Results Added to Event</h3>
    <p style="margin-top: 0.5em; margin-bottom: 2em;">
        <font color="crimson"><em>This is a UDOH-only notification.  Local health departments do not receive this notification.</em></font><br>
        <em>What Gets Flagged?</em><br>
        HIV cases that have had CD4 lab results added by ELR where the CD4 absolute count is <200 or the CD4% is <14%.<br>
    </p>
    
    <h3 style="display: inline;">New HIV Event Created</h3>
    <p style="margin-top: 0.5em; margin-bottom: 2em;">
        <font color="crimson"><em>This is a UDOH-only notification.  Local health departments do not receive this notification.</em></font><br>
        <em>What Gets Flagged?</em><br>
        HIV cases created by ELR.<br>
    </p>
            
    <h3 style="display: inline;">Out-of-State HIV Event Updated</h3>
    <p style="margin-top: 0.5em; margin-bottom: 2em;">
        <font color="crimson"><em>This is a UDOH-only notification.  Local health departments do not receive this notification.</em></font><br>
        <em>What Gets Flagged?</em><br>
        HIV cases assigned to the &ldquo;Out-of-State&rdquo; jurisdiction that are updated by ELR.<br>
    </p>
            
    <h3 style="display: inline;">Hepatitis B Pregnancy Events</h3>
    <p style="margin-top: 0.5em; margin-bottom: 2em;">
        <font color="crimson"><em>This is a UDOH-only notification.  Local health departments do not receive this notification.</em></font><br>
        <em>What Gets Flagged?</em><br>
        Hepatitis B cases with a lab added or updated by ELR and supplemental information that may indicate pregnancy.<br>
        <em>Why is This Flagged?</em><br>
        ELR does not update the pregnancy field in EpiTrax.<br>
        <em>Note:</em><br>
        Cases will trigger if there is information indicating the visit was an encounter for supervision of pregnancy, or if a pregnancy test was conducted. Currently, the only way to identify the trigger is to review the HL7 message that is put into the Notes field. If you do a simple search (<code>CTRL+F</code>) and type in &ldquo;pregnancy&rdquo; you should be able to find it.<br>
    </p>
            
    <h3 style="display: inline;">HIV Case Created by ELR Automation</h3>
    <p style="margin-top: 0.5em; margin-bottom: 2em;">
        <font color="crimson"><em>This is a UDOH-only notification.  Local health departments do not receive this notification.</em></font><br>
        <em>What Gets Flagged?</em><br>
        New HIV cases created automatically by ELR without any human intervention.<br>
    </p>
            
    <h3 style="display: inline;">HIV Case Updated by ELR Automation</h3>
    <p style="margin-top: 0.5em; margin-bottom: 2em;">
        <font color="crimson"><em>This is a UDOH-only notification.  Local health departments do not receive this notification.</em></font><br>
        <em>What Gets Flagged?</em><br>
        Existing HIV cases updated automatically by ELR without any human intervention.<br>
    </p>
            
    <h3 style="display: inline;">TB Event Created by ELR</h3>
    <p style="margin-top: 0.5em; margin-bottom: 2em;">
        <font color="crimson"><em>This is a UDOH-only notification.  Local health departments do not receive this notification.</em></font><br>
        <em>What Gets Flagged?</em><br>
        New TB cases created by ELR.<br>
    </p>
            
    <h3 style="display: inline;">TB Event Updated by ELR</h3>
    <p style="margin-top: 0.5em; margin-bottom: 2em;">
        <font color="crimson"><em>This is a UDOH-only notification.  Local health departments do not receive this notification.</em></font><br>
        <em>What Gets Flagged?</em><br>
        Existing TB cases updated by ELR.<br>
    </p>
</font>
EOPC;

$lhdRecipientList = array();
$notificationTypes = array();
$stateLocalIterator = array('udoh', 'lhd', 'custom');  // used to loop through state-level notifications and then lhd-level notifications
// default config values
$stateEnabled = false;
$lhdEnabled = false;
$stateNotifyAddressesStr = '';
$stateRecipientList = array();
$senderNameAddress = array('address' => 'edx@utah.gov', 'name' => 'Utah DCP Informatics Program');

// get config from db
try {
    $configSql = "SELECT udoh_enable, lhd_enable, udoh_email 
                  FROM batch_notification_config 
                  WHERE id = 1;";
    $configStmt = $dbConn->query($configSql);
    $configRow = $configStmt->fetchObject();
    
    $configStmt = null;
    
    $stateEnabled = (bool) $configRow->udoh_enable;
    $lhdEnabled = (bool) $configRow->lhd_enable;
    $stateNotifyAddressesStr = (string) filter_var($configRow->udoh_email, FILTER_SANITIZE_STRING);
} catch (Throwable $e) {
    ExceptionUtils::logException($e);
}

$stateRecipientList = array_filter(filter_var_array(explode(';', $stateNotifyAddressesStr), FILTER_VALIDATE_EMAIL));


// pre-build unique filename template
$baseFilename = __DIR__ . "/../manage/import/upload/" . date("Ymd", time()) . "_elrnotify_%s.xls";

// get list of jurisdictions found with unsent messages, also get e-mail addresses to send notifications to
try {
    $jQry = "SELECT DISTINCT jurisdiction_id 
             FROM batch_notifications 
             WHERE (custom IS FALSE) AND (date_sent_lhd IS NULL) AND (notify_lhd IS TRUE);";
    $jRs = $dbConn->query($jQry);
    while ($jRow = $jRs->fetchObject()) {
        $lhdRecipientList[intval($jRow->jurisdiction_id)] = Utils::getNotificationEmailAddressesByJurisdiction($authClient, AppClientUtils::getAppJurisdictionIdFromSystem($dbConn, $authClient->getAppId(), $jRow->jurisdiction_id));
    }
    
    $jRs = null;
} catch (Throwable $e) {
    ExceptionUtils::logException($e);
}

// get list of custom notifications found with unsent messages, also get e-mail addresses to send notifications to
try {
    $jcQry = "SELECT DISTINCT jurisdiction_id 
              FROM batch_notifications 
              WHERE (custom IS TRUE) AND (date_sent_lhd IS NULL) AND (notify_lhd IS TRUE);";
    $jcRs = $dbConn->query($jcQry);
    while ($jcRow = $jcRs->fetchObject()) {
        $custom_recipients[intval($jcRow->jurisdiction_id)] = Utils::getEmailAddressesByCustomJurisdiction($dbConn, intval($jcRow->jurisdiction_id));
    }
    
    $jcRs = null;
} catch (Throwable $e) {
    ExceptionUtils::logException($e);
}

// get list of notification types for message grouping
try {
    $nQry = "SELECT id, label, state_use, lhd_use, custom 
             FROM batch_notification_types 
             ORDER BY sort;";
    $nRs = $dbConn->query($nQry);
    while ($nRow = $nRs->fetchObject()) {
        $notificationTypes[intval($nRow->id)] = array(
            'state_use' => (bool) $nRow->state_use,
            'lhd_use' => (bool) $nRow->lhd_use,
            'label' => trim($nRow->label),
            'custom' => ((strlen(trim($nRow->custom)) > 0) ? intval(trim($nRow->custom)) : false)
        );
    }
    
    $nRs = null;
} catch (Throwable $e) {
    ExceptionUtils::logException($e);
    DisplayUtils::drawError("Unable to get list of notification types.  Please see a system administrator.");
}

// text-based intro for sending attachments
$notificationMsgBodyText = "You are receiving this message because you have chosen to receive notifications from UDOH for notifiable lab reports/case updates.  Please find attached all applicable daily ELR notifications for your jurisdiction.";


/**
 * iterate through state & local notification levles
 */
foreach ($stateLocalIterator as $stateOrLocal) {
    if ($stateOrLocal == "lhd" && $lhdEnabled) {
        if (isset($lhdRecipientList) && is_array($lhdRecipientList) && (count($lhdRecipientList) > 0)) {
            // iterate through each jurisdiction & message type to build messages...
            foreach ($lhdRecipientList as $j_id => $j_recipients) {
                unset($affectedNotifications);
                unset($affectedRecipients);

                // attempt to generate xls file for lhd
                unset($this_phe);
                unset($this_pheWriter);
                $attachmentFilename = null;
                $attachmentFilename = sprintf($baseFilename, strtolower(\EmsaUtils::sanitizeStringForFilename(\EmsaUtils::lhdName($dbConn, $j_id))));

                // create a new PhpSpreadsheet document & writer
                try {
                    $this_phe = new Spreadsheet();
                    $this_phe->getProperties()->setCreator("UDOH ELR");
                    $this_phe->getProperties()->setLastModifiedBy("UDOH ELR");
                    $this_sheet_index = 0;

                    foreach ($notificationTypes as $n_id => $type_data) {
                        if ($type_data['lhd_use'] && $type_data['custom'] === false) {
                            unset($type_label);
                            $type_label = $type_data['label'];

                            unset($this_row_cursor);
                            $this_row_cursor = 2;  // skip over header row
                            if ($this_sheet_index > 0) {
                                $this_phe->createSheet();
                            }
                            $this_phe->setActiveSheetIndex($this_sheet_index);
                            $this_phe->getActiveSheet()->setTitle(substr($type_label, 0, 30));  // tab name limited to 31 chars
                            // set column headings
                            $this_phe->getActiveSheet()->setCellValue("A1", "EpiTrax Event (Click to View)");
                            $this_phe->getActiveSheet()->setCellValue("B1", "Investigator");
                            $this_phe->getActiveSheet()->setCellValue("C1", "Date/Time Received");
                            $this_phe->getActiveSheet()->setCellValue("D1", "Condition");
                            $this_phe->getActiveSheet()->setCellValue("E1", "Organism");
                            $this_phe->getActiveSheet()->setCellValue("F1", "Test Type");
                            $this_phe->getActiveSheet()->setCellValue("G1", "Test Result");

                            $this_phe->getActiveSheet()->getStyle("A1:G1")->getFont()->setBold(true);
                            $this_phe->getActiveSheet()->getStyle("A1:G1")->getFill()->setFillType(Fill::FILL_SOLID);
                            $this_phe->getActiveSheet()->getStyle("A1:G1")->getFill()->getStartColor()->setARGB('FFAFEEEE');

                            $qry = null;
                            $stmt = null;
                            $row = null;
                            try {
                                $qry = "SELECT * FROM batch_notifications 
                                    WHERE jurisdiction_id = :jId 
                                    AND notification_type = :nId 
                                    AND date_sent_lhd IS NULL 
                                    AND notify_lhd IS TRUE 
                                    ORDER BY date_created;";
                                $stmt = $dbConn->prepare($qry);
                                $stmt->bindValue(':jId', (int)$j_id, \PDO::PARAM_INT);
                                $stmt->bindValue(':nId', (int)$n_id, \PDO::PARAM_INT);

                                $stmt->execute();

                                while ($row = $stmt->fetchObject()) {
                                    // keep track of which notifications from the database have been affected by this batch
                                    $affectedNotifications[] = intval($row->id);

                                    // set cell contents from db
                                    $this_phe->getActiveSheet()->setCellValue("A" . $this_row_cursor, "Record# " . filter_var($row->record_number, FILTER_SANITIZE_STRING));
                                    /* @var $authClient Udoh\Emsa\Client\AppClientInterface */
                                    $this_phe->getActiveSheet()->getCell("A" . $this_row_cursor)->getHyperlink()->setUrl($authClient->getAppLinkToRecord(0, filter_var($row->record_number, FILTER_SANITIZE_STRING)));
                                    $this_phe->getActiveSheet()->setCellValue("B" . $this_row_cursor, filter_var($row->investigator, FILTER_SANITIZE_STRING));
                                    $this_phe->getActiveSheet()->setCellValue("C" . $this_row_cursor, date("m/d/Y g:i A", strtotime($row->date_created)));
                                    $this_phe->getActiveSheet()->setCellValue("D" . $this_row_cursor, filter_var($row->condition, FILTER_SANITIZE_STRING));
                                    $this_phe->getActiveSheet()->setCellValue("E" . $this_row_cursor, filter_var($row->organism, FILTER_SANITIZE_STRING));
                                    $this_phe->getActiveSheet()->setCellValue("F" . $this_row_cursor, filter_var($row->test_type, FILTER_SANITIZE_STRING));
                                    $this_phe->getActiveSheet()->setCellValue("G" . $this_row_cursor, filter_var($row->test_result, FILTER_SANITIZE_STRING));

                                    $this_row_cursor++;
                                }
                            } catch (Throwable $e) {
                                ExceptionUtils::logException($e);
                            }

                            // auto-size column widths
                            $this_phe->getActiveSheet()->getColumnDimension("A")->setAutoSize(true);
                            $this_phe->getActiveSheet()->getColumnDimension("B")->setAutoSize(true);
                            $this_phe->getActiveSheet()->getColumnDimension("C")->setAutoSize(true);
                            $this_phe->getActiveSheet()->getColumnDimension("D")->setAutoSize(true);
                            $this_phe->getActiveSheet()->getColumnDimension("E")->setAutoSize(true);
                            $this_phe->getActiveSheet()->getColumnDimension("F")->setAutoSize(true);
                            $this_phe->getActiveSheet()->getColumnDimension("G")->setAutoSize(true);

                            // repeat notification type in sheet as well as for tab name
                            $this_phe->getActiveSheet()->insertNewRowBefore(1, 1);  // one new row inserted before current column headings
                            $this_phe->getActiveSheet()->setCellValue("A1", $type_label);
                            $this_phe->getActiveSheet()->mergeCells("A1:G1");
                            $this_phe->getActiveSheet()->getStyle("A1")->getFont()->setBold(true);
                            $this_phe->getActiveSheet()->getStyle("A1")->getFont()->setSize("14");
                            $this_phe->getActiveSheet()->getStyle("A1")->getFill()->setFillType(Fill::FILL_SOLID);
                            $this_phe->getActiveSheet()->getStyle("A1")->getFill()->getStartColor()->setARGB('FFFFD700');

                            // freeze headings
                            $this_phe->getActiveSheet()->freezePane("A3");

                            $this_sheet_index++;
                        }
                    }

                    // move back to first sheet for opening
                    $this_phe->setActiveSheetIndex(0);

                    // save our workbook
                    $this_pheWriter = new Xls($this_phe);
                    $this_pheWriter->save($attachmentFilename);

                    // send the notifications to detected recipients
                    if (isset($j_recipients) && is_array($j_recipients) && (count($j_recipients) > 0)) {
                        foreach ($j_recipients as $j_r_index => $recipient_email) {
                            if (filter_var($recipient_email, FILTER_VALIDATE_EMAIL)) {
                                if (Utils::sendMail($senderNameAddress, "UDOH Daily ELR Notification Summary - " . date("m/d/Y"), $notificationMsgBodyText . $primerLHD, array(array('address' => filter_var($recipient_email, FILTER_SANITIZE_EMAIL))), null, array(array('path' => $attachmentFilename)))) {
                                    $affectedRecipients['success'][] = filter_var($recipient_email, FILTER_SANITIZE_EMAIL);
                                } else {
                                    $affectedRecipients['failure'][] = filter_var($recipient_email, FILTER_SANITIZE_EMAIL);
                                }
                            } else {
                                $affectedRecipients['failure'][] = filter_var($recipient_email, FILTER_SANITIZE_STRING);
                            }
                        }
                        // temp copy to Josh for ldh QA
                        Utils::sendMail($senderNameAddress, "[QA Copy - " . \EmsaUtils::lhdName($dbConn, $j_id) . "] UDOH Daily ELR Notification Summary - " . date("m/d/Y"), $notificationMsgBodyText . $primerLHD, array(array('address' => 'jridderhoff@utah.gov')), null, array(array('path' => $attachmentFilename)));
                    } else {
                        // no e-mail addresses found for jurisdiction, notify edx@utah.gov
                        Utils::sendMail($senderNameAddress, "ELR LHD Notification Failure (" . \EmsaUtils::lhdName($dbConn, $j_id) . ") - " . date("m/d/Y"), 'While attempting to send a "UDOH Daily ELR Notification Summary" e-mail to the "' . \EmsaUtils::lhdName($dbConn, $j_id) . '" jurisdiction, no users with e-mail addresses could be found to send the notification to.', array(array('address' => 'edx@utah.gov', 'name' => 'Utah DCP Informatics Program')), null, array(array('path' => $attachmentFilename)));
                    }

                    if (isset($affectedRecipients['success']) && is_array($affectedRecipients['success']) && (count($affectedRecipients['success']) > 0)) {
                        // at least one notification was sent, update the affected notifications to set a date_sent time
                        NotificationUtils::markNotificationSentLHD($dbConn, $affectedNotifications);

                        // log the successful e-mails
                        foreach ($affectedRecipients['success'] as $successfulEmail) {
                            NotificationUtils::logNotificationResult($dbConn, $successfulEmail, $affectedNotifications, true, false, $j_id);
                        }
                    }

                    if (isset($affectedRecipients['failure']) && is_array($affectedRecipients['failure']) && (count($affectedRecipients['failure']) > 0)) {
                        // log the failed e-mails as well
                        foreach ($affectedRecipients['failure'] as $failedEmail) {
                            NotificationUtils::logNotificationResult($dbConn, $failedEmail, $affectedNotifications, false, false, $j_id);
                        }
                    }
                } catch (Throwable $e) {
                    ExceptionUtils::logException($e);
                } finally {
                    // clear the PhpSpreadsheet objects from memory
                    // important to disconnect worksheets to prevent memory leak
                    $this_phe->disconnectWorksheets();
                    $this_pheWriter = null;
                    $this_phe = null;
                }

                // delete the temporary Excel file saved to disk
                unlink($attachmentFilename);
            }
        }
    } elseif ($stateOrLocal == "custom") {
        if (isset($custom_recipients) && is_array($custom_recipients) && (count($custom_recipients) > 0)) {
            // iterate through each jurisdiction & message type to build messages...
            foreach ($custom_recipients as $jc_id => $jc_recipients) {
                unset($affectedNotifications);
                unset($affectedRecipients);
                unset($link_to_lab);

                $link_to_lab = Utils::useLinkToLab($dbConn, $jc_id);

                // attempt to generate xls file for lhd
                unset($this_phe);
                unset($this_pheWriter);
                $attachmentFilename = null;
                $attachmentFilename = sprintf($baseFilename, strtolower(preg_replace('/\s+/', '', \EmsaUtils::customLhdName($dbConn, $jc_id))));

                // create a new PhpSpreadsheet document & writer
                try {
                    $this_phe = new Spreadsheet();
                    $this_phe->getProperties()->setCreator("UDOH ELR");
                    $this_phe->getProperties()->setLastModifiedBy("UDOH ELR");
                    $this_sheet_index = 0;

                    foreach ($notificationTypes as $n_id => $type_data) {
                        if ($type_data['custom'] == $jc_id) {
                            unset($type_label);
                            $type_label = $type_data['label'];

                            unset($this_row_cursor);
                            $this_row_cursor = 2;  // skip over header row
                            if ($this_sheet_index > 0) {
                                $this_phe->createSheet();
                            }
                            $this_phe->setActiveSheetIndex($this_sheet_index);
                            $this_phe->getActiveSheet()->setTitle(substr($type_label, 0, 30));  // tab name limited to 31 chars
                            // set column headings
                            $this_phe->getActiveSheet()->setCellValue("A1", "EpiTrax Event (Click to View)");
                            $this_phe->getActiveSheet()->setCellValue("B1", "Investigator");
                            $this_phe->getActiveSheet()->setCellValue("C1", "Date/Time Received");
                            $this_phe->getActiveSheet()->setCellValue("D1", "Condition");
                            $this_phe->getActiveSheet()->setCellValue("E1", "Organism");
                            $this_phe->getActiveSheet()->setCellValue("F1", "Test Type");
                            $this_phe->getActiveSheet()->setCellValue("G1", "Test Result");
                            if ($link_to_lab) {
                                $this_phe->getActiveSheet()->setCellValue("H1", "Original Lab (Click to View)");
                                $this_phe->getActiveSheet()->getStyle("A1:H1")->getFont()->setBold(true);
                                $this_phe->getActiveSheet()->getStyle("A1:H1")->getFill()->setFillType(Fill::FILL_SOLID);
                                $this_phe->getActiveSheet()->getStyle("A1:H1")->getFill()->getStartColor()->setARGB('FFAFEEEE');
                            } else {
                                $this_phe->getActiveSheet()->getStyle("A1:G1")->getFont()->setBold(true);
                                $this_phe->getActiveSheet()->getStyle("A1:G1")->getFill()->setFillType(Fill::FILL_SOLID);
                                $this_phe->getActiveSheet()->getStyle("A1:G1")->getFill()->getStartColor()->setARGB('FFAFEEEE');
                            }

                            $qry = null;
                            $stmt = null;
                            $row = null;
                            try {
                                $qry = "SELECT * FROM batch_notifications 
                                    WHERE jurisdiction_id = :jcId 
                                    AND notification_type = :nId 
                                    AND date_sent_lhd IS NULL 
                                    AND notify_lhd IS TRUE 
                                    ORDER BY date_created;";
                                $stmt = $dbConn->prepare($qry);
                                $stmt->bindValue(':jcId', (int)$jc_id, \PDO::PARAM_INT);
                                $stmt->bindValue(':nId', (int)$n_id, \PDO::PARAM_INT);

                                $stmt->execute();

                                while ($row = $stmt->fetchObject()) {
                                    // keep track of which notifications from the database have been affected by this batch
                                    $affectedNotifications[] = intval($row->id);

                                    // set cell contents from db
                                    $this_phe->getActiveSheet()->setCellValue("A" . $this_row_cursor, "Record# " . filter_var($row->record_number, FILTER_SANITIZE_STRING));
                                    $this_phe->getActiveSheet()->getCell("A" . $this_row_cursor)->getHyperlink()->setUrl($authClient->getAppLinkToRecord(0, filter_var($row->record_number, FILTER_SANITIZE_STRING)));
                                    $this_phe->getActiveSheet()->setCellValue("B" . $this_row_cursor, filter_var($row->investigator, FILTER_SANITIZE_STRING));
                                    $this_phe->getActiveSheet()->setCellValue("C" . $this_row_cursor, date("m/d/Y g:i A", strtotime($row->date_created)));
                                    $this_phe->getActiveSheet()->setCellValue("D" . $this_row_cursor, filter_var($row->condition, FILTER_SANITIZE_STRING));
                                    $this_phe->getActiveSheet()->setCellValue("E" . $this_row_cursor, filter_var($row->organism, FILTER_SANITIZE_STRING));
                                    $this_phe->getActiveSheet()->setCellValue("F" . $this_row_cursor, filter_var($row->test_type, FILTER_SANITIZE_STRING));
                                    $this_phe->getActiveSheet()->setCellValue("G" . $this_row_cursor, filter_var($row->test_result, FILTER_SANITIZE_STRING));
                                    if ($link_to_lab) {
                                        $this_phe->getActiveSheet()->setCellValue("H" . $this_row_cursor, "View Original Lab");
                                        $this_phe->getActiveSheet()->getCell("H" . $this_row_cursor)->getHyperlink()->setUrl('https://nedss.health.utah.gov' . MAIN_URL . '/?selected_page=6&submenu=6&focus=' . intval($row->system_message_id));
                                    }

                                    $this_row_cursor++;
                                }

                                $stmt = null;
                            } catch (Throwable $e) {
                                ExceptionUtils::logException($e);
                            }

                            // auto-size column widths
                            $this_phe->getActiveSheet()->getColumnDimension("A")->setAutoSize(true);
                            $this_phe->getActiveSheet()->getColumnDimension("B")->setAutoSize(true);
                            $this_phe->getActiveSheet()->getColumnDimension("C")->setAutoSize(true);
                            $this_phe->getActiveSheet()->getColumnDimension("D")->setAutoSize(true);
                            $this_phe->getActiveSheet()->getColumnDimension("E")->setAutoSize(true);
                            $this_phe->getActiveSheet()->getColumnDimension("F")->setAutoSize(true);
                            $this_phe->getActiveSheet()->getColumnDimension("G")->setAutoSize(true);
                            if ($link_to_lab) {
                                $this_phe->getActiveSheet()->getColumnDimension("H")->setAutoSize(true);
                            }

                            // repeat notification type in sheet as well as for tab name
                            $this_phe->getActiveSheet()->insertNewRowBefore(1, 1);  // one new row inserted before current column headings
                            $this_phe->getActiveSheet()->setCellValue("A1", $type_label);
                            if ($link_to_lab) {
                                $this_phe->getActiveSheet()->mergeCells("A1:H1");
                            } else {
                                $this_phe->getActiveSheet()->mergeCells("A1:G1");
                            }
                            $this_phe->getActiveSheet()->getStyle("A1")->getFont()->setBold(true);
                            $this_phe->getActiveSheet()->getStyle("A1")->getFont()->setSize("14");
                            $this_phe->getActiveSheet()->getStyle("A1")->getFill()->setFillType(Fill::FILL_SOLID);
                            $this_phe->getActiveSheet()->getStyle("A1")->getFill()->getStartColor()->setARGB('FFFFD700');

                            // freeze headings
                            $this_phe->getActiveSheet()->freezePane("A3");

                            $this_sheet_index++;
                        }
                    }

                    // move back to first sheet for opening
                    $this_phe->setActiveSheetIndex(0);

                    // save our workbook
                    $this_pheWriter = new Xls($this_phe);
                    $this_pheWriter->save($attachmentFilename);

                    // send the notifications to detected recipients
                    if (isset($jc_recipients) && is_array($jc_recipients) && (count($jc_recipients) > 0)) {
                        foreach ($jc_recipients as $jc_r_index => $custom_recipient_email) {
                            if (filter_var($custom_recipient_email, FILTER_VALIDATE_EMAIL)) {
                                if (Utils::sendMail($senderNameAddress, "UDOH Daily ELR Notification Summary - " . date("m/d/Y"), $notificationMsgBodyText . $primerCustom, array(array('address' => filter_var($custom_recipient_email, FILTER_SANITIZE_EMAIL))), null, array(array('path' => $attachmentFilename)))) {
                                    $affectedRecipients['success'][] = filter_var($custom_recipient_email, FILTER_SANITIZE_EMAIL);
                                } else {
                                    $affectedRecipients['failure'][] = filter_var($custom_recipient_email, FILTER_SANITIZE_EMAIL);
                                }
                            } else {
                                $affectedRecipients['failure'][] = filter_var($custom_recipient_email, FILTER_SANITIZE_STRING);
                            }
                        }
                        // temp copy to Josh for custom validation
                        Utils::sendMail($senderNameAddress, "[QA Copy - " . \EmsaUtils::customLhdName($dbConn, $jc_id) . "] UDOH Daily ELR Notification Summary - " . date("m/d/Y"), $notificationMsgBodyText . $primerCustom, array(array('address' => 'jridderhoff@utah.gov')), null, array(array('path' => $attachmentFilename)));
                    } else {
                        // no e-mail addresses found for jurisdiction, notify edx@utah.gov
                        Utils::sendMail($senderNameAddress, "ELR LHD Notification Failure (" . \EmsaUtils::customLhdName($dbConn, $jc_id) . ") - " . date("m/d/Y"), 'While attempting to send a "UDOH Daily ELR Notification Summary" e-mail to the "' . \EmsaUtils::customLhdName($dbConn, $jc_id) . '" jurisdiction, no users with e-mail addresses could be found to send the notification to.', array(array('address' => 'edx@utah.gov', 'name' => 'Utah DCP Informatics Program')), null, array(array('path' => $attachmentFilename)));
                    }

                    if (isset($affectedRecipients['success']) && is_array($affectedRecipients['success']) && (count($affectedRecipients['success']) > 0)) {
                        // at least one notification was sent, update the affected notifications to set a date_sent time
                        NotificationUtils::markNotificationSentLHD($dbConn, $affectedNotifications);

                        // log the successful e-mails
                        foreach ($affectedRecipients['success'] as $successfulEmail) {
                            NotificationUtils::logNotificationResult($dbConn, $successfulEmail, $affectedNotifications, true, true, $jc_id);
                        }
                    }

                    if (isset($affectedRecipients['failure']) && is_array($affectedRecipients['failure']) && (count($affectedRecipients['failure']) > 0)) {
                        // log the failed e-mails as well
                        foreach ($affectedRecipients['failure'] as $failedEmail) {
                            NotificationUtils::logNotificationResult($dbConn, $failedEmail, $affectedNotifications, false, true, $jc_id);
                        }
                    }
                } catch (Throwable $e) {
                    ExceptionUtils::logException($e);
                } finally {
                    // clear the PhpSpreadsheet objects from memory
                    // important to disconnect worksheets to prevent memory leak
                    $this_phe->disconnectWorksheets();
                    $this_pheWriter = null;
                    $this_phe = null;
                }
                
                // delete the temporary Excel file saved to disk
                unlink($attachmentFilename);
            }
        }
    } elseif ($stateOrLocal == "udoh" && $stateEnabled) {
        unset($affectedNotifications);
        unset($affectedRecipients);

        // attempt to generate xls file for lhd
        unset($this_phe);
        unset($this_pheWriter);
        $attachmentFilename = null;
        $attachmentFilename = sprintf($baseFilename, "udoh");

        // create a new PhpSpreadsheet document & writer
        try {
            $this_phe = new Spreadsheet();
            $this_phe->getProperties()->setCreator("UDOH ELR");
            $this_phe->getProperties()->setLastModifiedBy("UDOH ELR");
            $this_sheet_index = 0;

            foreach ($notificationTypes as $n_id => $type_data) {
                if ($type_data['state_use'] && $type_data['custom'] === false) {
                    unset($type_label);
                    $type_label = $type_data['label'];

                    unset($this_row_cursor);
                    $this_row_cursor = 2;  // skip over header row
                    if ($this_sheet_index > 0) {
                        $this_phe->createSheet();
                    }
                    $this_phe->setActiveSheetIndex($this_sheet_index);
                    $this_phe->getActiveSheet()->setTitle(substr($type_label, 0, 30));  // tab name limited to 31 chars
                    // set column headings
                    $this_phe->getActiveSheet()->setCellValue("A1", "EpiTrax Event (Click to View)");
                    $this_phe->getActiveSheet()->setCellValue("B1", "Investigator");
                    $this_phe->getActiveSheet()->setCellValue("C1", "Date/Time Received");
                    $this_phe->getActiveSheet()->setCellValue("D1", "Condition");
                    $this_phe->getActiveSheet()->setCellValue("E1", "Organism");
                    $this_phe->getActiveSheet()->setCellValue("F1", "Test Type");
                    $this_phe->getActiveSheet()->setCellValue("G1", "Test Result");

                    $this_phe->getActiveSheet()->getStyle("A1:G1")->getFont()->setBold(true);
                    $this_phe->getActiveSheet()->getStyle("A1:G1")->getFill()->setFillType(Fill::FILL_SOLID);
                    $this_phe->getActiveSheet()->getStyle("A1:G1")->getFill()->getStartColor()->setARGB('FFAFEEEE');

                    $qry = null;
                    $stmt = null;
                    $row = null;
                    try {
                        $qry = "SELECT * FROM batch_notifications 
                            WHERE notification_type = :nId 
                            AND date_sent_state IS NULL 
                            AND notify_state IS TRUE 
                            ORDER BY date_created;";
                        $stmt = $dbConn->prepare($qry);
                        $stmt->bindValue(':nId', (int)$n_id, \PDO::PARAM_INT);

                        $stmt->execute();

                        while ($row = $stmt->fetchObject()) {
                            // keep track of which notifications from the database have been affected by this batch
                            $affectedNotifications[] = intval($row->id);

                            // set cell contents from db
                            $this_phe->getActiveSheet()->setCellValue("A" . $this_row_cursor, "Record# " . filter_var($row->record_number, FILTER_SANITIZE_STRING));
                            $this_phe->getActiveSheet()->getCell("A" . $this_row_cursor)->getHyperlink()->setUrl($authClient->getAppLinkToRecord(0, filter_var($row->record_number, FILTER_SANITIZE_STRING)));
                            $this_phe->getActiveSheet()->setCellValue("B" . $this_row_cursor, filter_var($row->investigator, FILTER_SANITIZE_STRING));
                            $this_phe->getActiveSheet()->setCellValue("C" . $this_row_cursor, date("m/d/Y g:i A", strtotime($row->date_created)));
                            $this_phe->getActiveSheet()->setCellValue("D" . $this_row_cursor, filter_var($row->condition, FILTER_SANITIZE_STRING));
                            $this_phe->getActiveSheet()->setCellValue("E" . $this_row_cursor, filter_var($row->organism, FILTER_SANITIZE_STRING));
                            $this_phe->getActiveSheet()->setCellValue("F" . $this_row_cursor, filter_var($row->test_type, FILTER_SANITIZE_STRING));
                            $this_phe->getActiveSheet()->setCellValue("G" . $this_row_cursor, filter_var($row->test_result, FILTER_SANITIZE_STRING));

                            $this_row_cursor++;
                        }

                        $stmt = null;
                    } catch (Throwable $e) {
                        ExceptionUtils::logException($e);
                    }

                    // auto-size column widths
                    $this_phe->getActiveSheet()->getColumnDimension("A")->setAutoSize(true);
                    $this_phe->getActiveSheet()->getColumnDimension("B")->setAutoSize(true);
                    $this_phe->getActiveSheet()->getColumnDimension("C")->setAutoSize(true);
                    $this_phe->getActiveSheet()->getColumnDimension("D")->setAutoSize(true);
                    $this_phe->getActiveSheet()->getColumnDimension("E")->setAutoSize(true);
                    $this_phe->getActiveSheet()->getColumnDimension("F")->setAutoSize(true);
                    $this_phe->getActiveSheet()->getColumnDimension("G")->setAutoSize(true);

                    // repeat notification type in sheet as well as for tab name
                    $this_phe->getActiveSheet()->insertNewRowBefore(1, 1);  // one new row inserted before current column headings
                    $this_phe->getActiveSheet()->setCellValue("A1", $type_label);
                    $this_phe->getActiveSheet()->mergeCells("A1:G1");
                    $this_phe->getActiveSheet()->getStyle("A1")->getFont()->setBold(true);
                    $this_phe->getActiveSheet()->getStyle("A1")->getFont()->setSize("14");
                    $this_phe->getActiveSheet()->getStyle("A1")->getFill()->setFillType(Fill::FILL_SOLID);
                    $this_phe->getActiveSheet()->getStyle("A1")->getFill()->getStartColor()->setARGB('FFFFD700');

                    // freeze headings
                    $this_phe->getActiveSheet()->freezePane("A3");

                    $this_sheet_index++;
                }
            }

            // move back to first sheet for opening
            $this_phe->setActiveSheetIndex(0);

            // save our workbook
            $this_pheWriter = new Xls($this_phe);
            $this_pheWriter->save($attachmentFilename);

            if (isset($affectedNotifications) && is_array($affectedNotifications) && (count($affectedNotifications) > 0)) {
                foreach ($stateRecipientList as $stateRecipientAddress) {
                    if (Utils::sendMail($senderNameAddress, "UDOH Daily ELR Notification Summary - " . date("m/d/Y"), $notificationMsgBodyText . $primerState, array(array('address' => $stateRecipientAddress)), null, array(array('path' => $attachmentFilename)))) {
                        $affectedRecipients['success'][] = $stateRecipientAddress;
                    } else {
                        $affectedRecipients['failure'][] = $stateRecipientAddress;
                    }
                }
            }

            if (isset($affectedRecipients['success']) && is_array($affectedRecipients['success']) && (count($affectedRecipients['success']) > 0)) {
                // at least one notification was sent, update the affected notifications to set a date_sent time
                NotificationUtils::markNotificationSentState($dbConn, $affectedNotifications);

                // log the successful e-mails
                foreach ($affectedRecipients['success'] as $successfulEmail) {
                    NotificationUtils::logNotificationResult($dbConn, $successfulEmail, $affectedNotifications, true, false);
                }
            }

            if (isset($affectedRecipients['failure']) && is_array($affectedRecipients['failure']) && (count($affectedRecipients['failure']) > 0)) {
                // log the failed e-mails as well
                foreach ($affectedRecipients['failure'] as $failedEmail) {
                    NotificationUtils::logNotificationResult($dbConn, $failedEmail, $affectedNotifications, false, false);
                }
            }
        } catch (Throwable $e) {
            ExceptionUtils::logException($e);
        } finally {
            // clear the PhpSpreadsheet objects from memory
            // important to disconnect worksheets to prevent memory leak
            $this_phe->disconnectWorksheets();
            $this_pheWriter = null;
            $this_phe = null;
        }

        // delete the temporary Excel file saved to disk
        unlink($attachmentFilename);
    }
}

$dbConn = null;
$emsaDbFactory = null;

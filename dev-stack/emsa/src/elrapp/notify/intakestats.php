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
use Udoh\Emsa\Utils\ExceptionUtils;

header('Expires: Mon, 26 Jul 1997 05:00:00 GMT');
header('Cache-Control: no-cache');
header('Pragma: no-cache');

$ignorePermissionFlag = true;

include_once __DIR__ . '/../includes/app_config.php';

try {
    $dbConn = $emsaDbFactory->getConnection();
} catch (Throwable $e) {
    ExceptionUtils::logException($e);
    exit('Unable to establish PDO connection to EMSA database');
}

// default config values
$connectorMonitoringEnabled = false;
$reporterMonitoringEnabled = false;
$connectorThreshold = 2.0;
$reporterThreshold = 2.0;
$notificationEmailAddresses = array();
$senderNameAddress = array('address' => 'edx@utah.gov', 'name' => 'Utah DCP Informatics Program');

// get config from db
try {
    $getConfigSql = "SELECT received_sigma, accepted_sigma, connectors, reporters, distribution_list 
                     FROM intake_stats_config WHERE id = 1;";
    $getConfigStmt = $dbConn->query($getConfigSql);

    if ($getConfigStmt !== false) {
        $getConfigRow = $getConfigStmt->fetchObject();
        $connectorMonitoringEnabled = (floatval($getConfigRow->received_sigma) > 0);
        $reporterMonitoringEnabled = (floatval($getConfigRow->accepted_sigma) > 0);
        $connectorThreshold = ((floatval($getConfigRow->received_sigma) > 0) ? floatval($getConfigRow->received_sigma) : 2.0);
        $reporterThreshold = ((floatval($getConfigRow->accepted_sigma) > 0) ? floatval($getConfigRow->accepted_sigma) : 2.0);
        $notifyEmailImploded = trim($getConfigRow->distribution_list);
    }

    if (!$connectorMonitoringEnabled && !$reporterMonitoringEnabled) {
        exit('Intake stats monitoring not enabled.');  // not sending either, so bail
    }
    
    $getConfigStmt = null;
} catch (Throwable $e) {
    ExceptionUtils::logException($e);
    exit('Unable to retrieve config.');
}

// assemble list of alert recipients
$notifyEmailExploded = preg_split("/[;,]/", $notifyEmailImploded);
if (is_array($notifyEmailExploded) && (count($notifyEmailExploded) > 0)) {
    foreach ($notifyEmailExploded as $notifyEmailToken) {
        if (filter_var(trim($notifyEmailToken), FILTER_VALIDATE_EMAIL)) {
            $notificationEmailAddresses[] = array('address' => (string) filter_var(trim($notifyEmailToken), FILTER_SANITIZE_EMAIL), 'name' => null);
        }
    }
}

if (count($notificationEmailAddresses) < 1) {
    $notificationEmailAddresses[] = array('address' => 'edx@utah.gov', 'name' => 'Utah DCP Informatics Program');  // if no valid addresses provided, fallback to edx@utah.gov address
}




if ($connectorMonitoringEnabled) {
    // process connector-based ("received") monitoring, if enabled
    $connectors = \Udoh\Emsa\Utils\IntakeStatsUtils::getActiveConnectors($dbConn);
    if (is_array($connectors) && (count($connectors) > 0)) {
        $debugConnectors = array();
        foreach ($connectors as $connector) {
            $connectorCurrentWeekCount = null;
            $connectorMean = null;
            $currentConnectorThreshold = null;

            try {
                $connectorSql = "SELECT * 
                    FROM stats_received_by_week(:connectorName, 0, 1) 
                    AS f(week_num varchar, week_count integer);";
                $connectorStmt = $dbConn->prepare($connectorSql);
                $connectorStmt->bindValue(':connectorName', trim($connector), \PDO::PARAM_STR);

                if ($connectorStmt->execute() && ($connectorStmt->rowCount() > 0)) {
                    $connectorRow = $connectorStmt->fetchObject();
                    $connectorCurrentWeekCount = intval($connectorRow->week_count);
                }
                
                $connectorStmt = null;

                $connectorMeanSql = "SELECT avg(f.week_count)::numeric AS mean, stddev_pop(f.week_count)::numeric AS sigma
                    FROM stats_received_by_week(:connectorName, 1, 8) 
                    AS f(week_num varchar, week_count integer);";
                $connectorMeanStmt = $dbConn->prepare($connectorMeanSql);
                $connectorMeanStmt->bindValue(':connectorName', trim($connector), \PDO::PARAM_STR);

                if ($connectorMeanStmt->execute() && ($connectorMeanStmt->rowCount() > 0)) {
                    $connectorMeanRow = $connectorMeanStmt->fetchObject();
                    $connectorMean = floatval($connectorMeanRow->mean);
                    $currentConnectorThreshold = floatval($connectorThreshold * floatval($connectorMeanRow->sigma));
                }
                
                $connectorMeanStmt = null;

                if (!\EmsaUtils::emptyTrim($connectorCurrentWeekCount) && !\EmsaUtils::emptyTrim($connectorMean) && !\EmsaUtils::emptyTrim($currentConnectorThreshold)) {
                    if (($connectorMean > 0) && ($connectorCurrentWeekCount <= ($connectorMean - $currentConnectorThreshold))) {
                        $textBody = "Warning:  HL7 volume from <b>" . htmlspecialchars(trim($connector)) . "</b> for the past 7 days is >" . trim(floatval($connectorThreshold)) . "&sigma; below the 2-month average volume.<br><br>";
                        $textBody .= "<b>Current 7-day Volume:</b>  " . intval($connectorCurrentWeekCount) . "<br>";
                        $textBody .= "<b>2-month Avg Volume:</b>  " . floatval($connectorMean) . "<br>";
                        
                        \Udoh\Emsa\Email\Utils::sendMail($senderNameAddress, "ELR Low Messages Received Alert [" . trim($connector) . "] - " . date("m/d/Y"), $textBody, $notificationEmailAddresses);
                    }
                }
            } catch (Throwable $e) {
                ExceptionUtils::logException($e);
            }
        }
    }
}




$dayOfWeek = date("N", time());
$isBusinessDay = ((($dayOfWeek > 1) && ($dayOfWeek < 7)) ? true : false);  // run on Tue-Sat to check for receips on Mon-Fri
if ($isBusinessDay) {
    // check to make sure each monitored connector has received messages in the past 24 hours... M-F only
    $connectors = \Udoh\Emsa\Utils\IntakeStatsUtils::getActiveConnectors($dbConn);
    if (is_array($connectors) && (count($connectors) > 0)) {
        $debugConnectors = array();
        foreach ($connectors as $connector24hr) {
            $yesterdayReceipts = 0;

            try {
                $connector24hrSql = "SELECT count(id) AS counter 
					FROM system_original_messages 
					WHERE connector = :connector24
					AND created_at::date = :yesterdayDate;";
                $connector24hrStmt = $dbConn->prepare($connector24hrSql);
                $connector24hrStmt->bindValue(':connector24', trim($connector24hr), \PDO::PARAM_STR);
                $connector24hrStmt->bindValue(':yesterdayDate', date("Y-m-d", strtotime("yesterday", time())), \PDO::PARAM_STR);

                if ($connector24hrStmt->execute() && ($connector24hrStmt->rowCount() > 0)) {
                    $connector24hrRow = $connector24hrStmt->fetchObject();
                    $yesterdayReceipts = intval($connector24hrRow->counter);
                }
                
                $connector24hrStmt = null;

                if ($yesterdayReceipts < 1) {
                    $textBody = "***CRITICAL WARNING:  No ELR messages were received from <b>" . htmlspecialchars(trim($connector24hr)) . "</b> on " . date("l, F jS, Y", strtotime("yesterday", time())) . ".***<br><br>";

                    \Udoh\Emsa\Email\Utils::sendMail($senderNameAddress, "CRITICAL:  No ELR Received from " . trim($connector24hr) . " for " . date("m/d/Y", strtotime("yesterday", time())), $textBody, $notificationEmailAddresses);
                }
            } catch (Throwable $e) {
                ExceptionUtils::logException($e);
            }
        }
    }
}




if ($reporterMonitoringEnabled) {
    // process reporter-based ("accepted") monitoring, if enabled
    $reporters = \Udoh\Emsa\Utils\IntakeStatsUtils::getActiveReporters($dbConn);

    if (is_array($reporters) && (count($reporters) > 0)) {
        foreach ($reporters as $reporter) {
            $reporterCurrentWeekCount = array();
            $reporterMean = array();
            $currentReporterThreshold = array();

            $reporterId = intval($reporter['id']);
            $reporterName = trim($reporter['name']);
            
            try {
                $reporterSql = "SELECT * 
                    FROM stats_accepted_by_week(:reporterId, 0, 1) 
                    AS f(category varchar, week_num varchar, week_count integer);";
                $reporterStmt = $dbConn->prepare($reporterSql);
                $reporterStmt->bindValue(':reporterId', intval($reporterId), \PDO::PARAM_INT);

                if ($reporterStmt->execute() && ($reporterStmt->rowCount() > 0)) {
                    while ($reporterRow = $reporterStmt->fetchObject()) {
                        $reporterCurrentWeekCount[trim($reporterRow->category)] = intval($reporterRow->week_count);
                    }
                }
            } catch (Throwable $e) {
                ExceptionUtils::logException($e);
            } finally {
                $reporterStmt = null;
                $reporterSql = null;
            }
            
            try {
                $reporterMeanSql = "SELECT f.category::varchar AS category, avg(f.week_count)::numeric AS mean, stddev_pop(f.week_count)::numeric AS sigma
                    FROM stats_accepted_by_week(:reporterId, 1, 8) 
                    AS f(category varchar, week_num varchar, week_count integer)
                    GROUP BY 1;";
                $reporterMeanStmt = $dbConn->prepare($reporterMeanSql);
                $reporterMeanStmt->bindValue(':reporterId', intval($reporterId), \PDO::PARAM_INT);

                if ($reporterMeanStmt->execute() && ($reporterMeanStmt->rowCount() > 0)) {
                    while ($reporterMeanRow = $reporterMeanStmt->fetchObject()) {
                        $reporterMean[trim($reporterMeanRow->category)] = floatval($reporterMeanRow->mean);
                        $currentReporterThreshold[trim($reporterMeanRow->category)] = floatval($reporterThreshold * floatval($reporterMeanRow->sigma));
                    }
                }
            } catch (Throwable $e) {
                ExceptionUtils::logException($e);
            } finally {
                $reporterMeanStmt = null;
                $reporterMeanSql = null;
            }

            if ((count($reporterCurrentWeekCount) > 0) && (count($reporterMean) > 0) && (count($currentReporterThreshold) > 0)) {
                $currentReporterAlerts = array();

                foreach ($reporterCurrentWeekCount as $this_reporter_category => $this_reporter_current_week_data) {
                    if (($reporterMean[$this_reporter_category] > 0) && ($this_reporter_current_week_data <= ($reporterMean[$this_reporter_category] - $currentReporterThreshold[$this_reporter_category]))) {
                        $currentReporterAlerts[] = array(
                            'category' => trim($this_reporter_category),
                            'current' => intval($this_reporter_current_week_data),
                            'mean' => floatval($reporterMean[$this_reporter_category])
                        );
                    }
                }

                if (count($currentReporterAlerts) > 0) {
                    $textBodyPrefix = "Warning:  Accepted ELR message volume from <b>" . \Udoh\Emsa\Utils\DisplayUtils::xSafe(trim($reporterName)) . "</b> for the past 7 days is >" . trim(floatval($reporterThreshold)) . "&sigma; below the 2-month average volume in the following CDC categories:<br><br>";
                    $textBody = '';
                    foreach ($currentReporterAlerts as $currentReporterAlert) {
                        $textBody .= "<b>Category:</b>  " . \Udoh\Emsa\Utils\DisplayUtils::xSafe(trim($currentReporterAlert['category'])) . "<br>";
                        $textBody .= "<b>Current 7-day Volume:</b>  " . intval($currentReporterAlert['current']) . "<br>";
                        $textBody .= "<b>2-month Avg Volume:</b>  " . floatval($currentReporterAlert['mean']) . "<br><br>";
                    }

                    \Udoh\Emsa\Email\Utils::sendMail($senderNameAddress, "ELR Low Messages Accepted Alert [" . trim($reporterName) . "] - " . date("m/d/Y"), $textBodyPrefix . $textBody, $notificationEmailAddresses);
                }
            }
        }
    }
}

$dbConn = null;
$emsaDbFactory = null;

exit;

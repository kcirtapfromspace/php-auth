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

ob_start();

// prevent caching...
header("Expires: Mon, 26 Jul 1997 05:00:00 GMT");
header("Cache-Control: no-cache");
header("Pragma: no-cache");

include_once __DIR__ . '/includes/app_config.php';

$masterDBStatus = false;
$replicationDBStatus = false;
// $nedssDatabaseIsUp = false;
$masterProcessStatus = false;
$epitraxRESTStatus = false;
$mirthStatus = false;

try {
    $masterDBFactory = new Udoh\Emsa\PDOFactory\PostgreSQL($emsaDbHost, $emsaDbPort, $emsaDbName, $emsaDbUser, $emsaDbPass, $emsaDbSchemaPDO);
    $masterDBStatus = true;
} catch (Throwable $e) {
    Udoh\Emsa\Utils\ExceptionUtils::logException($e);
}

try {
    $replicationDBFactory = new Udoh\Emsa\PDOFactory\PostgreSQL($replicationDbHost, $replicationDbPort, $emsaDbName, $emsaDbUser, $emsaDbPass, $emsaDbSchemaPDO);
    $replicationStatusSQL = <<<REPSQL
SELECT CASE WHEN (pg_is_in_recovery() IS TRUE) AND (pg_last_wal_receive_lsn() = pg_last_wal_replay_lsn()) THEN 0
ELSE EXTRACT(EPOCH FROM (LOCALTIMESTAMP - COALESCE(pg_last_xact_replay_timestamp(), LOCALTIMESTAMP)))
END AS replication_delay_seconds;
REPSQL;
    $replicationDBStatusStmt = $replicationDBFactory->getConnection()->query($replicationStatusSQL);
    $replicationDelay = $replicationDBStatusStmt->fetchColumn(0);

    if (!empty($replicationDelay) && ((float) $replicationDelay >= 21600.0)) {
        $replicationDBStatus = false;
    } else {
        $replicationDBStatus = true;
    }
} catch (Throwable $e) {
    Udoh\Emsa\Utils\ExceptionUtils::logException($e);
} finally {
    $replicationDBStatusStmt = null;
    $replicationStatusSQL = null;
    $replicationDBFactory = null;
}

try {
    $statusMasterClient = new Udoh\Emsa\Client\MasterProcessClient();
    $masterProcessStatus = true;
} catch (Throwable $e) {
    Udoh\Emsa\Utils\ExceptionUtils::logException($e);
}

try {
    $appRoundRobinHosts = Udoh\Emsa\Utils\CoreUtils::getAppRoundRobinHosts($masterDBFactory->getConnection(), 2);
    $statusEpiTraxRESTClient = new Udoh\Emsa\Client\EpiTraxRESTClient(2, $appRoundRobinHosts);
    $statusEpiTraxRESTClient->getJurisdictions();
    $epitraxRESTStatus = true;
} catch (Throwable $e) {
    Udoh\Emsa\Utils\ExceptionUtils::logException($e);
}

try {
    $statusMirthClient = new Udoh\Emsa\Client\MirthServiceClient();
    $mirthStatus = true;
} catch (Throwable $e) {
    Udoh\Emsa\Utils\ExceptionUtils::logException($e);
}

// excluding Smarty Streets from checks at this time...
//$geo_curl = curl_init();
//if ($geo_curl) {
//    $geo_url = 'http://' . SMARTY_URL . '/geo/find?street_name=' . urlencode('288 N 1460 W') . '&zip=' . urlencode('84116');
//    curl_setopt($geo_curl, CURLOPT_CONNECTTIMEOUT, 5);
//    curl_setopt($geo_curl, CURLOPT_TIMEOUT, 10);
//    curl_setopt($geo_curl, CURLOPT_RETURNTRANSFER, true);
//    // Warning: only use CURLOPT_VERBOSE for debugging non-production data; can cause addresses to be exposed in error_log
//    // curl_setopt($geo_curl, CURLOPT_VERBOSE, true);
//    curl_setopt($geo_curl, CURLOPT_URL, $geo_url);
//
//    $smartyIsUp = (curl_exec($geo_curl) !== false) ? true : false;
//    curl_close($geo_curl);
//}

$masterDBFactory = null;
$statusNedssDbFactory = null;

if ($masterDBStatus && $masterProcessStatus && $epitraxRESTStatus && $mirthStatus && $replicationDBStatus) {
    ob_clean();
    header($_SERVER['SERVER_PROTOCOL'] . " 200 OK", TRUE, 200);
    header('Content-Type: text/plain; charset=UTF-8');
    $overallStatus = "EMSA_STATUS_OK";
} else {
    ob_clean();
    header($_SERVER['SERVER_PROTOCOL'] . " 503 Service Unavailable", TRUE, 503);
    header('Content-Type: text/plain; charset=UTF-8');
    $overallStatus = "EMSA_STATUS_BAD";
}

$detailedResults = array(
    'overall_status'        => $overallStatus,
    'master_db_status'      => $masterDBStatus,
    'replication_db_status' => $replicationDBStatus,
    'replication_delay'     => $replicationDelay,
    'master_process'        => $masterProcessStatus,
    'epitrax_rest_service'  => $epitraxRESTStatus,
    'mirth_service'         => $mirthStatus
);

echo json_encode($detailedResults);

// record status check to error_log for auditing
$logString = 'EMSA status check... ' . $overallStatus;

if ($overallStatus != 'EMSA_STATUS_OK') {
    $logString .= ' ' . json_encode($detailedResults);
}

error_log($logString);

ob_end_flush();
exit;

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

header('Content-Type: application/json');

include __DIR__ . '/../includes/app_config.php';

if (!class_exists('Udoh\Emsa\Auth\Authenticator') || !Udoh\Emsa\Auth\Authenticator::userHasPermission(Udoh\Emsa\Auth\Authenticator::URIGHTS_DASHBOARD)) {
    ob_end_clean();
    header($_SERVER['SERVER_PROTOCOL'] . " 404 Not Found", TRUE, 404);
    exit;
}

session_write_close(); // done writing to session; prevent blocking

$valid_callbacks = array(
    'getOrphanedMessageCount' => false,
    'getDashboardSummary' => true,
    'getAvgCMRCreateTime' => true,
    'getDashboardNewCase' => true,
    'getDashboardAppendedCase' => true,
    'getDashboardDiscardedCase' => true,
    'getDashboardBlacklistSummary' => true,
    'getDashboardGraylistSummary' => true,
    'getDashboardMessageQuality' => true,
    'getDashboardLab' => true,
    'getDashboardConditionSummary' => true,
    'getDashboardAutomationFactor' => true,
    'getTrafficArrivalPattern' => true,
    'getTrafficArrivalPatternSelected' => true,
    'getTrafficArrivalPatternSelectedDOW' => true,
    'getSystemAlerts' => true
);

$callback = ((isset($_REQUEST['callback']) && in_array($_REQUEST['callback'], $valid_callbacks)) ? $_REQUEST['callback'] : null);

if (!is_null($callback)) {
    try {
        $dbFactory = new Udoh\Emsa\PDOFactory\PostgreSQL($replicationDbHost, $replicationDbPort, $emsaDbName, $emsaDbUser, $emsaDbPass, $emsaDbSchemaPDO);
        $dbConn = $dbFactory->getConnection();
        $dashboard = new Udoh\Emsa\UI\Dashboard($dbConn);
    } catch (Throwable $e) {
        Udoh\Emsa\Utils\ExceptionUtils::logException($e);
        ob_clean();
        header($_SERVER['SERVER_PROTOCOL'] . " 500 Internal Server Error", TRUE, 500);
        exit;
    }

    if ($valid_callbacks[$callback] === true) {
        $data = call_user_func(array($dashboard, $callback), $_SESSION[EXPORT_SERVERNAME]['dashboard_date_from'], $_SESSION[EXPORT_SERVERNAME]['dashboard_date_to']);
    } else {
        $data = call_user_func(array($dashboard, $callback));
    }
} else {
    $data = array();
}

$dbConn = null;
$dbFactory = null;
$emsaDbFactory = null;

echo @json_encode($data);
exit;

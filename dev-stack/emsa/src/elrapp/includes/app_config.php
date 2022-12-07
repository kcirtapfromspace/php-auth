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

date_default_timezone_set('America/Denver');
/** Two-letter abbreviation for the State this system is running in, for use with Out-of-State message processing */
define("HOME_STATE_ABBR", "CO");

header("Content-Security-Policy: frame-ancestors 'self'");
header("X-Frame-Options: SAMEORIGIN");

// application-wide common definitions

// Server environment
define('ELR_ENV_TEST', 1);
define('ELR_ENV_DEV', 3);
define('ELR_ENV_PROD', 2);

// specimen source validity
define('SPECIMEN_VALID', 1);
define('SPECIMEN_INVALID', 2);
define('SPECIMEN_EXCEPTION', 3);

define("DEFAULT_ROWS_PER_PAGE", 50);

// queues
define("WHITE_STATUS", 1);
define("HOLD_STATUS", 99);
define("BLACK_STATUS", 4);
define("BLACK_NAME", "Black");
define("UNPROCESSED_STATUS", 26);
define("UNPROCESSED_NAME", "Unprocessed");
define("LOCKED_STATUS", 27);
define("LOCKED_NAME", "Locked");
define("ENTRY_STATUS", 17);
define("ENTRY_NAME", "Entry");
define("OOS_STATUS", 28);
define("OOS_NAME", "Out of State");
define("SEMI_AUTO_STATUS", 24);
define("SEMI_AUTO_NAME", "Semi-Automated Entry");
define("PENDING_STATUS", 12);
define("PENDING_NAME", "Pending - Legacy");
define("GRAY_STATUS", 2);
define("GRAY_NAME", "Graylist");
define("ASSIGNED_STATUS", 14);
define("ASSIGNED_NAME", "Assigned");
define("QA_STATUS", 19);
define("QA_NAME", "QA Review");
define("EXCEPTIONS_STATUS", 3);
define("EXCEPTIONS_NAME", "Exceptions");
define("HOLD_NAME", "Hold");
define("NEDSS_EXCEPTION_STATUS", 25);
define("NEDSS_EXCEPTION_NAME", "Pending");

// graylist-specific queues
define("GRAY_PENDING_STATUS", 20);
define("GRAY_PENDING_NAME", "Graylist Pending");
define("GRAY_PROCESSED_STATUS", 21);
define("GRAY_PROCESSED_NAME", "Graylist Processed");
define("GRAY_UNPROCESSABLE_STATUS", 22);
define("GRAY_EXCEPTION_STATUS", 23);
define("GRAY_EXCEPTION_NAME", "Graylist Exception");

// make sure session is started
session_set_cookie_params(0, '/', null, true, true);
if (!isset($_SESSION)) {
    session_start();
}

include_once __DIR__ . '/../../../../../opt/emsa/config.php';  // load environment-specific configuration

// include htmlpurifier
require_once __DIR__ . '/classes/htmlpurifier/library/HTMLPurifier.auto.php';

// register PHPMailer-specific autoloader
spl_autoload_register(function ($className) {
    $prefix = 'PHPMailer\\PHPMailer\\';
    $baseDir = __DIR__ . '/classes/PHPMailer/src/';
    $len = strlen($prefix);

    if (strncmp($prefix, $className, $len) !== 0) {
        // not PHPMailer, move to the next registered autoloader
        return;
    }

    // get the relative class name
    $relativeClass = substr($className, $len);

    // replace the namespace prefix with the base directory, replace namespace
    // separators with directory separators in the relative class name, append
    // with .php
    $filePath = $baseDir . str_replace('\\', '/', $relativeClass) . '.php';

    // if the file exists, require it
    if (is_readable($filePath)) {
        require $filePath;
    }
});

// register generic class autoloader
spl_autoload_register(function ($className) {
    $filePath = __DIR__ . '/classes/' . str_replace('\\', '/', $className) . '.php';
    if (is_readable($filePath)) {
        require $filePath;
    }
});

// set up error reporting
if ($serverEnvironment === ELR_ENV_PROD) {
    ini_set('display_errors', 0);
    ini_set('display_startup_errors', 0);
    ini_set('log_errors', 1);
    error_reporting(E_ALL);
} else {
    ini_set('display_errors', 1);
    ini_set('display_startup_errors', 1);
    ini_set('log_errors', 1);
    error_reporting(-1);
}

// temp workaround for SQLA timeout issue
ini_set('max_execution_time', '1800');
ini_set('default_socket_timeout', '120');

$appRoundRobinHosts = array();

try {
    $emsaDbFactory = new Udoh\Emsa\PDOFactory\PostgreSQL($emsaDbHost, $emsaDbPort, $emsaDbName, $emsaDbUser, $emsaDbPass, $emsaDbSchemaPDO);
    // $appRoundRobinHosts = Udoh\Emsa\Utils\CoreUtils::getAppRoundRobinHosts($emsaDbFactory->getConnection(), 2);
} catch (Throwable $e) {
    Udoh\Emsa\Utils\ExceptionUtils::logException($e);
    ob_end_clean();
    header($_SERVER['SERVER_PROTOCOL'] . " 503 Service Unavailable", TRUE, 503);
    header('Content-Type: text/plain; charset=UTF-8');
    exit;
}

// specify which destination applications are configured for this installation
try {
    $appClientList = new Udoh\Emsa\Client\AppClientList();
    $appClientList->add(new Udoh\Emsa\Client\EpiTraxRESTClient(2, $appRoundRobinHosts));
} catch (Throwable $e) {
    Udoh\Emsa\Utils\ExceptionUtils::logException($e);
    ob_end_clean();
    header($_SERVER['SERVER_PROTOCOL'] . " 503 Service Unavailable", TRUE, 503);
    header('Content-Type: text/plain; charset=UTF-8');
    exit;
}

// initialize htmlpurifier for use later
$defaultHTMLPurifierConfig = HTMLPurifier_Config::createDefault();
$emsaHTMLPurifier = new HTMLPurifier($defaultHTMLPurifierConfig);

include_once __DIR__ . '/security.php';  // handle authentication and permission

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

$_SESSION[EXPORT_SERVERNAME]['umdid'] = getenv('HTTP_UNIQUEID') ?: getenv('HTTP_UID');

try {
    /* @var $appClientList Udoh\Emsa\Client\AppClientList */
    $authClient = $appClientList->getClientById(2);
    $emsaAuthenticator = new Udoh\Emsa\Auth\Authenticator($emsaDbFactory->getConnection(), $authClient, $serverEnvironment);
} catch (Throwable $e) {
    Udoh\Emsa\Utils\ExceptionUtils::logException($e);
    ob_end_clean();
    header($_SERVER['SERVER_PROTOCOL'] . " 503 Service Unavailable", TRUE, 503);
    header('Content-Type: text/plain; charset=UTF-8');
    exit;
}

try {
    if (isset($ignorePermissionFlag) && ($ignorePermissionFlag === true)) {
        $emsaAuthenticator->authenticate($appClientList, true);
    } else {
        $emsaAuthenticator->authenticate($appClientList);
    }
} catch (Throwable $e) {
    Udoh\Emsa\Utils\ExceptionUtils::logException($e);
    ob_end_clean();
    header($_SERVER['SERVER_PROTOCOL'] . " 500 Internal Server Error", TRUE, 500);
    header('Content-Type: text/plain; charset=UTF-8');
    exit;
}

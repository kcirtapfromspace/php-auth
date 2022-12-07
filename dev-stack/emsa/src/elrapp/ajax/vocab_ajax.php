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

include __DIR__ . '/../includes/app_config.php';

if (!class_exists('\Udoh\Emsa\Auth\Authenticator') || !Udoh\Emsa\Auth\Authenticator::userHasPermission(Udoh\Emsa\Auth\Authenticator::URIGHTS_ADMIN)) {
    ob_end_clean();
    header($_SERVER['SERVER_PROTOCOL'] . " 404 Not Found", TRUE, 404);
    exit;
}

session_write_close(); // done writing to session; prevent blocking

$cleanParams = new stdClass();
$cleanParams->params['tbl'] = filter_input(INPUT_POST, 'tbl', FILTER_SANITIZE_NUMBER_INT);
$cleanParams->params['col'] = filter_input(INPUT_POST, 'col', FILTER_SANITIZE_STRING);
$cleanParams->params['id'] = filter_input(INPUT_POST, 'id', FILTER_SANITIZE_NUMBER_INT);
$cleanParams->params['parentId'] = filter_input(INPUT_POST, 'parentId', FILTER_SANITIZE_NUMBER_INT);
$cleanParams->params['condition'] = filter_input(INPUT_POST, 'condition', FILTER_SANITIZE_STRING);
$cleanParams->params['handler'] = filter_input(INPUT_POST, 'handler', FILTER_SANITIZE_STRING);

if (isset($_REQUEST['editPkg'])) {
    parse_str($_REQUEST['editPkg'], $cleanParams->params['editPkg']);
} else {
    $cleanParams->params['editPkg'] = array();
}

$ajaxService = new VocabAjaxService($emsaDbFactory->getConnection(), $authClient);

$data = '';

// ensure a valid handler function exists
if (!EmsaUtils::emptyTrim($cleanParams->params['handler']) && method_exists($ajaxService, $cleanParams->params['handler'])) {
    try {
        $data = call_user_func(array($ajaxService, $cleanParams->params['handler']), $cleanParams->params);
    } catch (Throwable $e) {
        header($_SERVER['SERVER_PROTOCOL'] . " 500 " . trim($e->getMessage()), TRUE, 500);
    }
} else {
    header($_SERVER['SERVER_PROTOCOL'] . " 501 Not Implemented", TRUE, 501);
}

$emsaDbFactory = null;

echo $data;
exit;

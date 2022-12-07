<?php
/**
 * Copyright (c) 2022 Utah Department of Technology Services and Utah Department of Health
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
 * @copyright Copyright (c) 2022 Utah Department of Technology Services and Utah Department of Health
 */

use Udoh\Emsa\Model\Vocabulary\ChildDictionary;
use Udoh\Emsa\Utils\ExceptionUtils;

// prevent caching...
header('Expires: Mon, 26 Jul 1997 05:00:00 GMT');
header('Cache-Control: no-cache');
header('Pragma: no-cache');

include __DIR__ . '/../../includes/app_config.php';

session_write_close(); // done writing to session; prevent blocking

if (!class_exists('\Udoh\Emsa\Auth\Authenticator') || !Udoh\Emsa\Auth\Authenticator::userHasPermission(Udoh\Emsa\Auth\Authenticator::URIGHTS_ADMIN)) {
    ob_end_clean();
    header($_SERVER['SERVER_PROTOCOL'] . " 403 Forbidden", TRUE, 403);
    exit;
}

$httpMethod = filter_input(INPUT_SERVER, 'REQUEST_METHOD');

if ($httpMethod === 'POST') {
    try {
        $childDictionary = new ChildDictionary();
        $childDictionary->create($emsaDbFactory->getConnection(), $authClient);

        ob_clean();
        header($_SERVER['SERVER_PROTOCOL'] . " 200 OK", TRUE, 200);
        exit;
    } catch (Throwable $e) {
        ob_clean();
        ExceptionUtils::logException($e);
        header($_SERVER['SERVER_PROTOCOL'] . " 500 Internal Server Error", TRUE, 500);
        exit;
    }
} else {
    // not yet implemented
    ob_clean();
    header($_SERVER['SERVER_PROTOCOL'] . " 501 Not Implemented", TRUE, 501);
    exit;
}
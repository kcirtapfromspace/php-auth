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

$webappBaseUrl = MAIN_URL . '/';

$navSelectedPage = 1;
$navSubmenu = 0;
$navCat = 0;
$navSubcat = 0;
$processImport = false;

if (isset($_GET['selected_page'])) {
    $navSelectedPage = (int) filter_input(INPUT_GET, 'selected_page', FILTER_SANITIZE_NUMBER_INT);
} elseif (isset($_POST['selected_page'])) {
    $navSelectedPage = (int) filter_input(INPUT_POST, 'selected_page', FILTER_SANITIZE_NUMBER_INT);
}
if (isset($_GET['submenu'])) {
    $navSubmenu = (int) filter_input(INPUT_GET, 'submenu', FILTER_SANITIZE_NUMBER_INT);
} elseif (isset($_POST['submenu'])) {
    $navSubmenu = (int) filter_input(INPUT_POST, 'submenu', FILTER_SANITIZE_NUMBER_INT);
}
if (isset($_GET['cat'])) {
    $navCat = (int) filter_input(INPUT_GET, 'cat', FILTER_SANITIZE_NUMBER_INT);
} elseif (isset($_POST['cat'])) {
    $navCat = (int) filter_input(INPUT_POST, 'cat', FILTER_SANITIZE_NUMBER_INT);
}
if (isset($_GET['subcat'])) {
    $navSubcat = (int) filter_input(INPUT_GET, 'subcat', FILTER_SANITIZE_NUMBER_INT);
} elseif (isset($_POST['subcat'])) {
    $navSubcat = (int) filter_input(INPUT_POST, 'subcat', FILTER_SANITIZE_NUMBER_INT);
}

if (((int) filter_input(INPUT_POST, 'import_flag', FILTER_SANITIZE_NUMBER_INT)) === 1) {
    $processImport = true;
}

if (isset($_POST['override_role']) && !empty($_POST['override_role'])) {
    // if user manually picks from available roles, set the override role in _SESSION
    // so that only that role's diseases are used for filtering/EMSA queues
    $overrideRoleId = (int) filter_input(INPUT_POST, 'override_role', FILTER_SANITIZE_NUMBER_INT);
    $emsaAuthenticator->setOverridePermission($overrideRoleId);
    $emsaAuthenticator->authenticate($appClientList);  // re-authenticate, just in case user lost permission to view the page they were on with their overridden role
}

Udoh\Emsa\Utils\DisplayUtils::drawHeader($emsaDbFactory->getConnection(), $serverEnvironment, $navSelectedPage, $navSubmenu, $navCat, $navSubcat);

switch ($navSelectedPage) {
    case 1:
        include __DIR__ . '/dashboard.php';
        break;
    case 2:
    case 3:
    case 4:
    case 5:
    case 25:
    case 26:
    case 27:
        include __DIR__ . '/emsa/index.php';
        break;
    case 6:
        include __DIR__ . '/admin.php';
        break;
}

Udoh\Emsa\Utils\DisplayUtils::drawFooter();

$emsaDbFactory = null;

ob_end_flush();

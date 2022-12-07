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

if (!class_exists('\Udoh\Emsa\Auth\Authenticator') || !\Udoh\Emsa\Auth\Authenticator::userHasPermission(\Udoh\Emsa\Auth\Authenticator::URIGHTS_ADMIN)) {
    ob_end_clean();
    header($_SERVER['SERVER_PROTOCOL'] . " 404 Not Found", TRUE, 404);
    exit;
}

$selectedReportName = false;
$filteredReportId = (string) filter_input(INPUT_POST, 'report_id', FILTER_SANITIZE_STRING);

switch ($filteredReportId) {
    case 'qa_flags':
        $selectedReportName = 'qa_flags';
        break;
    case 'other_flag_reasons':
        $selectedReportName = 'other_flag_reasons';
        break;
    case 'de_flag_reasons':
        $selectedReportName = 'de_flag_reasons';
        break;
    case 'discarded_messages':
        $selectedReportName = 'discarded_messages';
        break;
    case 'dashboard_tabular':
        $selectedReportName = 'dashboard_tabular';
        break;
    default:
        $selectedReportName = false;
}

if ($selectedReportName !== false) {
    $_SESSION[EXPORT_SERVERNAME]['reporting_current_report'] = $selectedReportName;
    include __DIR__ . '/reports/' . $selectedReportName . '.php';
} else {
    header($_SERVER['SERVER_PROTOCOL'] . " 500 Internal Server Error", TRUE, 500);  // no record found with that id
}

$emsaDbFactory = null;
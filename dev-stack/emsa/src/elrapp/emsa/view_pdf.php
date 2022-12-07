<?php
/**
 * Copyright (c) 2018 Utah Department of Technology Services and Utah Department of Health
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
 * @copyright Copyright (c) 2018 Utah Department of Technology Services and Utah Department of Health
 */

use Udoh\Emsa\Auth\Authenticator;
use Udoh\Emsa\Utils\ExceptionUtils;
use Udoh\Emsa\UI\Queue\EmsaQueueList;

// prevent caching...
header('Expires: Mon, 26 Jul 1997 05:00:00 GMT');
header('Cache-Control: no-cache');
header('Pragma: no-cache');

include __DIR__ . '/../includes/app_config.php';
require_once __DIR__ . '/../includes/classes/TCPDF/tcpdf.php';

session_write_close(); // done writing to session; prevent blocking

try {
    $dbConn = $emsaDbFactory->getConnection();
} catch (Throwable $e) {
    ExceptionUtils::logException($e);
    ob_end_clean();
    header($_SERVER['SERVER_PROTOCOL'] . " 503 Service Unavailable", TRUE, 503);
    $dbConn = null;
    $emsaDbFactory = null;
    exit;
}

$cleanMsgId = (int) filter_input(INPUT_GET, 'id', FILTER_SANITIZE_NUMBER_INT);

try {
    $emsaMessage = new EmsaMessage($dbConn, $appClientList, $cleanMsgId, false, true);
    $systemMessageId = $emsaMessage->getSystemMessageId();
} catch (Throwable $e) {
    ExceptionUtils::logException($e);
    ob_end_clean();
    header($_SERVER['SERVER_PROTOCOL'] . " 400 Bad Request", TRUE, 400);
    $dbConn = null;
    $emsaDbFactory = null;
    exit;
}

// verify that message ID specified actually exists and that 
// logged-in user has permission to view the requested message...
try {
    $safeQueueId = EmsaUtils::getQueueIdByMessageId($dbConn, $systemMessageId);
    
    if (is_null($safeQueueId) || ((int) $safeQueueId <= 0)) {
        throw new Exception('Specified Message not found.');
    }
    
    $safeQueueList = new EmsaQueueList($dbConn, $appClientList);
    $safeResultSet = $safeQueueList->getEmsaListResultSet($safeQueueId, null, null, EmsaQueueList::RESTRICT_SHOW_ALL, $systemMessageId);
    
    if (empty($safeResultSet) || count($safeResultSet) !== 1 || !Authenticator::userHasPermission(Authenticator::URIGHTS_TAB_FULL)) {
        throw new Exception('Unauthorized attempt to view Full Lab PDF');
    }
} catch (Throwable $e) {
    ExceptionUtils::logException($e);
    ob_end_clean();
    header($_SERVER['SERVER_PROTOCOL'] . " 400 Bad Request", TRUE, 400);
    $dbConn = null;
    $emsaDbFactory = null;
    exit;
}

try {
    // create new PDF document
    $pdf = new TCPDF("L", PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8');

    // set document information
    $pdf->SetCreator(PDF_CREATOR);
    $pdf->SetAuthor(ORGANIZATION_NAME_LONG);
    $pdf->SetTitle(ORGANIZATION_NAME_SHORT . ' - Translated Electronic Health Message');

    // set default header data
    $pdf->SetHeaderData(PDF_HEADER_LOGO, PDF_HEADER_LOGO_WIDTH, ORGANIZATION_NAME_SHORT, 'Translated Electronic Health Message', array(200, 32, 0), array(0, 64, 128));
    $pdf->setFooterData(array(0, 64, 0), array(0, 64, 128));

    // set header and footer fonts
    $pdf->setHeaderFont(Array('dejavuserif', 'B', 17));
    $pdf->setFooterFont(Array(PDF_FONT_NAME_DATA, '', PDF_FONT_SIZE_DATA));

    // set default monospaced font
    $pdf->SetDefaultMonospacedFont(PDF_FONT_MONOSPACED);

    // set margins
    $pdf->SetMargins(PDF_MARGIN_LEFT, PDF_MARGIN_TOP, PDF_MARGIN_RIGHT);
    $pdf->SetHeaderMargin(PDF_MARGIN_HEADER);
    $pdf->SetFooterMargin(PDF_MARGIN_FOOTER);

    // set auto page breaks
    $pdf->SetAutoPageBreak(TRUE, 15);

    // set image scale factor
    $pdf->setImageScale(PDF_IMAGE_SCALE_RATIO);
    $pdf->setJPEGQuality(100);

    $tagvs = array('p' => array(0 => array('h' => 0, 'n' => 0), 1 => array('h' => 0, 'n' => 0)));
    $pdf->setHtmlVSpace($tagvs);

    $pdf->SetCellPadding(0);
    $pdf->setCellHeightRatio(1.1);


    // set some language-dependent strings (optional)
    if (@file_exists(dirname(__FILE__) . '/lang/eng.php')) {
        require_once(dirname(__FILE__) . '/lang/eng.php');
        $pdf->setLanguageArray($l);
    }

    // ---------------------------------------------------------
    // set default font subsetting mode
    $pdf->setFontSubsetting(true);

    // Set font
    $pdf->SetFont('helvetica', '', 10);

    // Add a page
    $pdf->AddPage("L");

    $htmlContent = '<style>table td { font-size: 0.95em; } table th { color: olivedrab; background-color: mintcream; padding: 7px; border-bottom: 2px dimgray solid; font-size: 1.3em; font-weight: bold; }</style>';
    /* @var $safeResult EmsaListResult */
    foreach ($safeResultSet as $safeResult) {
        $htmlContent .= $safeResult->drawFullLabTab($dbConn, null, true);
    }
    
    $pdf->writeHTML($htmlContent, true, false, true, false, '');
    $emsaMasterCondition = (string) preg_replace("/[^[:alnum:]]/", '', (string) $emsaMessage->masterCondition);
    $pdf->Output('UDOH_ELR_' . $emsaMasterCondition . '_' . (string) $emsaMessage->getSystemMessageId() . '.pdf', 'I');
    
    $dbConn = null;
    $emsaDbFactory = null;
    exit;
} catch (Throwable $e) {
    ExceptionUtils::logException($e);
    ob_end_clean();
    header($_SERVER['SERVER_PROTOCOL'] . " 500 Internal Server Error", TRUE, 500);
    $dbConn = null;
    $emsaDbFactory = null;
    exit;
}

// fallback, just in case
$dbConn = null;
$emsaDbFactory = null;
exit;

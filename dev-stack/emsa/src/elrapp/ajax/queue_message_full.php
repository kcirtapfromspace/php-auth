<?php
/**
 * Copyright (c) 2020 Utah Department of Technology Services and Utah Department of Health
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
 * @copyright Copyright (c) 2020 Utah Department of Technology Services and Utah Department of Health
 */

use Udoh\Emsa\Auth\Authenticator;
use Udoh\Emsa\Utils\AuditUtils;
use Udoh\Emsa\Utils\DisplayUtils;
use Udoh\Emsa\Utils\ExceptionUtils;
use Udoh\Emsa\UI\Queue\EmsaQueueList;

// prevent caching...
header('Expires: Mon, 26 Jul 1997 05:00:00 GMT');
header('Cache-Control: no-cache');
header('Pragma: no-cache');

include __DIR__ . '/../includes/app_config.php';

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

$cleanMsgId = (int) filter_input(INPUT_POST, 'id', FILTER_SANITIZE_NUMBER_INT);

$cleanNavType = (int) filter_input(INPUT_POST, 'type', FILTER_SANITIZE_NUMBER_INT);
$cleanNavSelectedPage = (int) filter_input(INPUT_POST, 'selected_page', FILTER_SANITIZE_NUMBER_INT);
$cleanNavSubmenu = (int) filter_input(INPUT_POST, 'submenu', FILTER_SANITIZE_NUMBER_INT);
$cleanNavCat = (int) filter_input(INPUT_POST, 'cat', FILTER_SANITIZE_NUMBER_INT);
$cleanNavSubcat = (int) filter_input(INPUT_POST, 'subcat', FILTER_SANITIZE_NUMBER_INT);
$navQueryString = EmsaUtils::queryStringBuilder($cleanNavSelectedPage, $cleanNavSubmenu, $cleanNavCat, $cleanNavSubcat, $cleanNavType);

// verify that message ID specified actually exists and that
// logged-in user has permission to view the requested message...
try {
    $safeQueueId = EmsaUtils::getQueueIdByMessageId($dbConn, $cleanMsgId);

    if (is_null($safeQueueId) || ((int) $safeQueueId <= 0)) {
        throw new Exception('Specified message not found.');
    }

    $safeQueueList = new EmsaQueueList($dbConn, $appClientList);
    $safeResultSet = $safeQueueList->getEmsaListResultSet($safeQueueId, null, null, EmsaQueueList::RESTRICT_SHOW_ALL, $cleanMsgId);

    if (empty($safeResultSet) || count($safeResultSet) !== 1 || !Authenticator::userHasPermission(Authenticator::URIGHTS_TAB_FULL)) {
        throw new Exception('Unauthorized attempt to view full message in queue.');
    }
} catch (Throwable $e) {
    ExceptionUtils::logException($e);
    ob_end_clean();
    header($_SERVER['SERVER_PROTOCOL'] . " 400 Bad Request", TRUE, 400);
    $dbConn = null;
    $emsaDbFactory = null;
    exit;
}

/** @var EmsaListResult $emsaListResult */
foreach ($safeResultSet as $emsaListResult) {
    echo '<ul>';
    echo '<li><a href="#emsa_dupsearch_' . intval($emsaListResult->id) . '_tab1">People Search Results</a></li>' . PHP_EOL;
    if (Authenticator::userHasPermission(Authenticator::URIGHTS_TAB_FULL)) {
        echo '<li><a href="#emsa_dupsearch_' . intval($emsaListResult->id) . '_tab2">Full Lab</a></li>' . PHP_EOL;
    }
    if ($cleanNavType == EXCEPTIONS_STATUS || $cleanNavType == NEDSS_EXCEPTION_STATUS || $cleanNavType == LOCKED_STATUS || $cleanNavType == OOS_STATUS) {
        echo '<li><a href="#emsa_dupsearch_' . intval($emsaListResult->id) . '_tab_override">Manual Override</a></li>' . PHP_EOL;
    }
    if (Authenticator::userHasPermission(Authenticator::URIGHTS_TAB_AUDIT) || ($cleanNavType == PENDING_STATUS)) {
        echo '<li><a href="#emsa_dupsearch_' . intval($emsaListResult->id) . '_tab7">Audit Log</a></li>' . PHP_EOL;
    }
    if (Authenticator::userHasPermission(Authenticator::URIGHTS_TAB_ERROR)) {
        echo '<li><a href="#emsa_dupsearch_' . intval($emsaListResult->id) . '_tab3">Error Flags</a></li>' . PHP_EOL;
    }
    if (Authenticator::userHasPermission(Authenticator::URIGHTS_TAB_HL7)) {
        echo '<li><a href="#emsa_dupsearch_' . intval($emsaListResult->id) . '_tab4">Raw Original Message</a></li>' . PHP_EOL;
    }
    if (Authenticator::userHasPermission(Authenticator::URIGHTS_TAB_XML)) {
        echo '<li><a href="#emsa_dupsearch_' . intval($emsaListResult->id) . '_tab5">Master XML</a></li>' . PHP_EOL;
        echo '<li><a href="#emsa_dupsearch_' . intval($emsaListResult->id) . '_tab6">' . $emsaListResult->getAppClient()->getAppName() . ' XML</a></li>' . PHP_EOL;
    }
    if (Authenticator::userHasPermission(Authenticator::URIGHTS_TAB_QA)) {
        echo '<li><a href="#emsa_dupsearch_' . intval($emsaListResult->id) . '_tab9">QA Tracking</a></li>' . PHP_EOL;
    }
    echo '</ul>';

    echo '<div id="emsa_dupsearch_' . intval($emsaListResult->id) . '_tab1" class="emsa_tabset_content emsa_tabset_content_ps"><img alt="Getting search results" style="vertical-align: bottom;" src="' . MAIN_URL . '/img/ajax-loader.gif" height="16" width="16" border="0" /> Searching...</div>' . PHP_EOL;
    if (Authenticator::userHasPermission(Authenticator::URIGHTS_TAB_FULL)) {
        echo '<div id="emsa_dupsearch_' . intval($emsaListResult->id) . '_tab2" class="emsa_tabset_content_spacing">' . PHP_EOL;
        echo $emsaListResult->drawFullLabTab($dbConn, $navQueryString);
        echo '</div>';
    }
    if ($cleanNavType == EXCEPTIONS_STATUS || $cleanNavType == NEDSS_EXCEPTION_STATUS || $cleanNavType == LOCKED_STATUS || $cleanNavType == OOS_STATUS) {
        echo '<div id="emsa_dupsearch_' . intval($emsaListResult->id) . '_tab_override">' . PHP_EOL;
        echo $emsaListResult->drawManualOverrideForm($navQueryString);
        echo $emsaListResult->drawCurrentExceptions($dbConn);
        echo '</div>';
    }
    if (Authenticator::userHasPermission(Authenticator::URIGHTS_TAB_AUDIT) || ($cleanNavType == PENDING_STATUS)) {
        echo '<div id="emsa_dupsearch_' . intval($emsaListResult->id) . '_tab7">';
        echo '<table class="audit_log"><thead><tr><th>Original Message ID#</th><th>System Message ID#</th></tr></thead>';
        echo '<tbody><tr><td>' . (int) $emsaListResult->originalMessageId . '</td><td>' . (int) $emsaListResult->id . '</td></tr></tbody></table><br>';

        if (!empty($emsaListResult->getInteragencyDateSent())) {
            echo '<table class="audit_log"><thead><tr><th>Inter-agency Message Sharing</th></tr></thead>';
            echo '<tbody><tr><td>The original electronic message that generated this EMSA message was transmitted to ' . DisplayUtils::xSafe($emsaListResult->getInteragencyRecipient(), "UTF-8", false) . ' on ' . $emsaListResult->getInteragencyDateSent(true, "m/d/Y H:i:s") . '<br>(Filename: ' . DisplayUtils::xSafe($emsaListResult->getInteragencyFilename(), "UTF-8", false) . ')</td></tr></tbody></table><br>';
        }

        if (intval($emsaListResult->copyParentId) > 0) {
            echo '<div class="h3">Parent Message Audit Log</div>';
            echo AuditUtils::getAuditLog($emsaListResult->getAppClient(), $dbConn, intval($emsaListResult->copyParentId));
            echo '<br><div class="h3">Current Message Audit Log</div>';
        }
        echo AuditUtils::getAuditLog($emsaListResult->getAppClient(), $dbConn, intval($emsaListResult->id));
        echo '</div>';
    }
    if (Authenticator::userHasPermission(Authenticator::URIGHTS_TAB_ERROR)) {
        echo '<div id="emsa_dupsearch_' . intval($emsaListResult->id) . '_tab3">' . PHP_EOL;
        echo $emsaListResult->drawCurrentExceptions($dbConn);
        echo $emsaListResult->drawExceptionsHistory($dbConn);
        echo '</div>';
    }
    if (Authenticator::userHasPermission(Authenticator::URIGHTS_TAB_HL7)) {
        echo '<div id="emsa_dupsearch_' . intval($emsaListResult->id) . '_tab4" class="emsa_tabset_content" style="overflow: hidden;"><label class="sr-only" for="' . intval($emsaListResult->id) . '_hl7">Raw Original Message Body (Read-only)</label><textarea readonly class="ui-corner-all" style="background-color: aliceblue; font-family: Consolas, \'Courier New\', serif; width: 99%; height: 20em; white-space: pre;" id="' . intval($emsaListResult->id) . '_hl7">' . DisplayUtils::xSafe(EmsaUtils::getRawOriginalMessageFormatted($dbConn, $emsaListResult->id, $emsaListResult->getEmsaMessageType())) . '</textarea></div>' . PHP_EOL;
    }
    if (Authenticator::userHasPermission(Authenticator::URIGHTS_TAB_XML)) {
        echo '<div id="emsa_dupsearch_' . intval($emsaListResult->id) . '_tab5" class="emsa_tabset_content"><pre id="' . intval($emsaListResult->id) . '_mxml">' . DisplayUtils::xSafe(EmsaUtils::getMasterXmlFormatted($dbConn, $emsaListResult->id)) . '</pre></div>' . PHP_EOL;
        echo '<div id="emsa_dupsearch_' . intval($emsaListResult->id) . '_tab6" class="emsa_tabset_content"><pre id="' . intval($emsaListResult->id) . '_nxml">' . DisplayUtils::xSafe(EmsaUtils::getApplicationXMLFormatted($dbConn, $emsaListResult->id)) . '</pre></div>' . PHP_EOL;
    }
    if (Authenticator::userHasPermission(Authenticator::URIGHTS_TAB_QA)) {
        echo '<div id="emsa_dupsearch_' . intval($emsaListResult->id) . '_tab9" class="emsa_tabset_content_spacing">' . PHP_EOL;
        echo $emsaListResult->drawQaTrackingTab($dbConn, $navQueryString);
        echo '</div>';
    }
}

// fallback, just in case
$emsaListResult = null;
$safeResultSet = null;
$safeQueueList = null;
$dbConn = null;
$emsaDbFactory = null;
exit;
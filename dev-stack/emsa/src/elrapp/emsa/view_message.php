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

if (!class_exists('\Udoh\Emsa\Auth\Authenticator')) {
    ob_end_clean();
    header($_SERVER['SERVER_PROTOCOL'] . " 404 Not Found", TRUE, 404);
    exit;
}

// preserve clicked message on page refresh
$emsaListSelectedMsgId = (int) filter_input(INPUT_GET, 'focus', FILTER_SANITIZE_NUMBER_INT);

$type = EmsaUtils::getQueueIdByMessageId($emsaDbFactory->getConnection(), $emsaListSelectedMsgId);

// define pre-selected tab when viewing messages
$selectedTab = "tab2";  // default to Full Lab
if ($type === NEDSS_EXCEPTION_STATUS && \Udoh\Emsa\Auth\Authenticator::userHasPermission(\Udoh\Emsa\Auth\Authenticator::URIGHTS_TAB_ERROR)) {
    $selectedTab = "tab3";  // Error Flags
} elseif ($type === ENTRY_STATUS) {
    $selectedTab = "tab1";  // People Search
} elseif ($type === EXCEPTIONS_STATUS && \Udoh\Emsa\Auth\Authenticator::userHasPermission(\Udoh\Emsa\Auth\Authenticator::URIGHTS_TAB_ERROR)) {
    $selectedTab = "tab3";  // Error Flags
}
?>

<script>
    (function( emsaMessageQueue, $, undefined) {
        emsaMessageQueue.EMSA_FLAG_INVESTIGATION_COMPLETE = <?php echo json_encode(EMSA_FLAG_INVESTIGATION_COMPLETE, JSON_HEX_TAG); ?>;
        emsaMessageQueue.EMSA_FLAG_DE_ERROR = <?php echo json_encode(EMSA_FLAG_DE_ERROR, JSON_HEX_TAG); ?>;
        emsaMessageQueue.EMSA_FLAG_DE_OTHER = <?php echo json_encode(EMSA_FLAG_DE_OTHER, JSON_HEX_TAG); ?>;
        emsaMessageQueue.EMSA_FLAG_FIX_DUPLICATE = <?php echo json_encode(EMSA_FLAG_FIX_DUPLICATE, JSON_HEX_TAG); ?>;
        emsaMessageQueue.EMSA_FLAG_DE_NEEDFIX = <?php echo json_encode(EMSA_FLAG_DE_NEEDFIX, JSON_HEX_TAG); ?>;
        emsaMessageQueue.EMSA_FLAG_QA_MANDATORY = <?php echo json_encode(EMSA_FLAG_QA_MANDATORY, JSON_HEX_TAG); ?>;
        emsaMessageQueue.EMSA_FLAG_QA_CODING = <?php echo json_encode(EMSA_FLAG_QA_CODING, JSON_HEX_TAG); ?>;
        emsaMessageQueue.EMSA_FLAG_QA_MQF = <?php echo json_encode(EMSA_FLAG_QA_MQF, JSON_HEX_TAG); ?>;

        emsaMessageQueue.baseURL = <?php echo json_encode(MAIN_URL . '/', JSON_HEX_TAG); ?>;
        emsaMessageQueue.selectedMessageId = <?php echo json_encode($emsaListSelectedMsgId ?? 0, JSON_HEX_TAG); ?>;
        emsaMessageQueue.type = <?php echo json_encode($type, JSON_HEX_TAG); ?>;
        emsaMessageQueue.navSelectedPage = <?php echo json_encode($navSelectedPage, JSON_HEX_TAG); ?>;
        emsaMessageQueue.navSubmenu = <?php echo json_encode($navSubmenu, JSON_HEX_TAG); ?>;
        emsaMessageQueue.navCat = <?php echo json_encode($navCat, JSON_HEX_TAG); ?>;
        emsaMessageQueue.navSubcat = <?php echo json_encode($navSubcat, JSON_HEX_TAG); ?>;
        emsaMessageQueue.selectedTab = <?php echo json_encode($selectedTab, JSON_HEX_TAG); ?>;
    }(window.emsaMessageQueue = window.emsaMessageQueue || {}, jQuery));
</script>
<script src="<?php echo MAIN_URL . '/js/message-queues.js?v=1.20'; ?>"></script>

<h1 class="elrhdg"><span class="ui-icon ui-icon-header ui-icon-elremsa"></span>View Individual ELR Message</h1>

<?php
// handle any edit/move/retry/delete actions...
include_once __DIR__ . '/emsa_actions.php';

// requery to pick up any changed queue due to assignment or move
$type = EmsaUtils::getQueueIdByMessageId($emsaDbFactory->getConnection(), $emsaListSelectedMsgId);

$emsaFieldset = new Udoh\Emsa\UI\Queue\EmsaListFieldset($emsaDbFactory->getConnection(), $appClientList, $type, $navSelectedPage, $navSubmenu, $navCat, $navSubcat);
$emsaFieldset->getIndividualList($emsaListSelectedMsgId);

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

use Udoh\Emsa\UI\AccessibleMultiselectListbox;
use Udoh\Emsa\Utils\CoreUtils;

if (!class_exists('Udoh\Emsa\Auth\Authenticator')) {
    ob_end_clean();
    header($_SERVER['SERVER_PROTOCOL'] . " 404 Not Found", TRUE, 404);
    exit;
}

$filterFactory = new Udoh\Emsa\UI\Queue\FilterFactory($emsaDbFactory->getConnection());

// check for session freshness since last update to session...
// filters are stored in session data, this hack gives us a way to force
// session data to be refreshed without forcing users to clear cookies if the data
// is updated mid-session, so that the latest filters are used)
$modelLastUpdated = filemtime("emsa/index.php");

// check "freshness date"...
if (isset($_SESSION[EXPORT_SERVERNAME]['emsa_model_fresh'])) {
    if ($_SESSION[EXPORT_SERVERNAME]['emsa_model_fresh'] < $modelLastUpdated) {
        // old model data; unset vocab_params & set a new "freshness date"...
        unset($_SESSION[EXPORT_SERVERNAME]['emsa_params']);
        $_SESSION[EXPORT_SERVERNAME]['emsa_model_fresh'] = time();
    }
} else {
    // hack for sessions set before "freshness date" implemented
    unset($_SESSION[EXPORT_SERVERNAME]['emsa_params']);
    $_SESSION[EXPORT_SERVERNAME]['emsa_model_fresh'] = time();
}


// Session stuff for filters
$_SESSION[EXPORT_SERVERNAME]['emsa_params']['type'] = 1;
if (isset($_GET['type']) && filter_var($_GET['type'], FILTER_VALIDATE_INT)) {
    $_SESSION[EXPORT_SERVERNAME]['emsa_params']['type'] = (int) filter_input(INPUT_GET, 'type', FILTER_SANITIZE_NUMBER_INT);
}

// Sort order, if passed (mainly for Graylist)
if (isset($_REQUEST['sort']) && filter_var($_REQUEST['sort'], FILTER_VALIDATE_INT)) {
    $_SESSION[EXPORT_SERVERNAME]['emsa_params'][$_SESSION[EXPORT_SERVERNAME]['emsa_params']['type']]['sort_order'] = intval(filter_var($_REQUEST['sort'], FILTER_SANITIZE_NUMBER_INT));
}

// if no Sort param passed, default to sort Assigned queue by Date Assigned, Newest First; everything else Date Reported, Oldest First
if (!isset($_SESSION[EXPORT_SERVERNAME]['emsa_params'][$_SESSION[EXPORT_SERVERNAME]['emsa_params']['type']]['sort_order'])) {
    if ($_SESSION[EXPORT_SERVERNAME]['emsa_params']['type'] == ASSIGNED_STATUS) {
        $_SESSION[EXPORT_SERVERNAME]['emsa_params'][$_SESSION[EXPORT_SERVERNAME]['emsa_params']['type']]['sort_order'] = 6;
    } elseif ($_SESSION[EXPORT_SERVERNAME]['emsa_params']['type'] == GRAY_STATUS) {
        $_SESSION[EXPORT_SERVERNAME]['emsa_params'][$_SESSION[EXPORT_SERVERNAME]['emsa_params']['type']]['sort_order'] = 8;
    } else {
        $_SESSION[EXPORT_SERVERNAME]['emsa_params'][$_SESSION[EXPORT_SERVERNAME]['emsa_params']['type']]['sort_order'] = 1;
    }
}

// Get/store current page for selected queue...
if (isset($_GET['currentpage']) && filter_var($_GET['currentpage'], FILTER_VALIDATE_INT)) {
    $_SESSION[EXPORT_SERVERNAME]['emsa_params'][$_SESSION[EXPORT_SERVERNAME]['emsa_params']['type']]['current_page'] = (int) filter_input(INPUT_GET, 'currentpage', FILTER_SANITIZE_NUMBER_INT);
}

// default to pg. 1 if no 'current_page' is saved & no page# specified in querystring
if (!isset($_SESSION[EXPORT_SERVERNAME]['emsa_params'][$_SESSION[EXPORT_SERVERNAME]['emsa_params']['type']]['current_page'])) {
    $_SESSION[EXPORT_SERVERNAME]['emsa_params'][$_SESSION[EXPORT_SERVERNAME]['emsa_params']['type']]['current_page'] = 1;
}

// Search/Filter Prep
// pre-build our vocab-specific search data...
if (isset($_REQUEST['q'])) {
    if (!empty($_REQUEST['q'])) {
        $_SESSION[EXPORT_SERVERNAME]['emsa_params'][$_SESSION[EXPORT_SERVERNAME]['emsa_params']['type']]['q_display'] = htmlentities(trim($_REQUEST['q']), ENT_QUOTES, "UTF-8");
        $_SESSION[EXPORT_SERVERNAME]['emsa_params'][$_SESSION[EXPORT_SERVERNAME]['emsa_params']['type']]['q_sql'] = pg_escape_string(trim($_REQUEST['q']));
        if (!isset($_REQUEST['f'])) {
            // search query found, but no filters selected
            // if any filters were previously SESSIONized, they've been deselected via the UI, so we'll clear them...
            unset($_SESSION[EXPORT_SERVERNAME]['emsa_params'][$_SESSION[EXPORT_SERVERNAME]['emsa_params']['type']]['filters']);
        }
    } else {
        // search field was empty/defaulted, so we'll destroy the saved search params...
        $_SESSION[EXPORT_SERVERNAME]['emsa_params'][$_SESSION[EXPORT_SERVERNAME]['emsa_params']['type']]['q_display'] = null;
        unset($_SESSION[EXPORT_SERVERNAME]['emsa_params'][$_SESSION[EXPORT_SERVERNAME]['emsa_params']['type']]['q_sql']);
        // not only was search blank, but no filters selected, so clear them as well...
        unset($_SESSION[EXPORT_SERVERNAME]['emsa_params'][$_SESSION[EXPORT_SERVERNAME]['emsa_params']['type']]['filters']);
    }
} else {
    if (!isset($_SESSION[EXPORT_SERVERNAME]['emsa_params'][$_SESSION[EXPORT_SERVERNAME]['emsa_params']['type']]['q_display'])) {
        $_SESSION[EXPORT_SERVERNAME]['emsa_params'][$_SESSION[EXPORT_SERVERNAME]['emsa_params']['type']]['q_display'] = null;
    }
}

// update SESSIONized filters or destroy them if no filters are selected...
if (isset($_REQUEST['f'])) {
    if (isset($_REQUEST['f']['evalue']) && empty($_REQUEST['f']['evalue'])) {
        $_REQUEST['f']['evalue'] = null;
    }
    if (is_array($_REQUEST['f']) && count($_REQUEST['f']) > 0) {
        $_SESSION[EXPORT_SERVERNAME]['emsa_params'][$_SESSION[EXPORT_SERVERNAME]['emsa_params']['type']]['filters'] = $_REQUEST['f'];
    }
    if (array_key_exists('evalue', $_SESSION[EXPORT_SERVERNAME]['emsa_params'][$_SESSION[EXPORT_SERVERNAME]['emsa_params']['type']]['filters']) && empty($_SESSION[EXPORT_SERVERNAME]['emsa_params'][$_SESSION[EXPORT_SERVERNAME]['emsa_params']['type']]['filters']['evalue'])) {
        unset($_SESSION[EXPORT_SERVERNAME]['emsa_params'][$_SESSION[EXPORT_SERVERNAME]['emsa_params']['type']]['filters']['evalue']);
    }
    // if 'filters' collection only existed because of an empty 'evalue' (which would have been destroyed above), 
    // destroy the entire 'filters' collection so as not to trigger the 'Active Filters' banner
    if (isset($_SESSION[EXPORT_SERVERNAME]['emsa_params'][$_SESSION[EXPORT_SERVERNAME]['emsa_params']['type']]['filters']) && empty($_SESSION[EXPORT_SERVERNAME]['emsa_params'][$_SESSION[EXPORT_SERVERNAME]['emsa_params']['type']]['filters'])) {
        unset($_SESSION[EXPORT_SERVERNAME]['emsa_params'][$_SESSION[EXPORT_SERVERNAME]['emsa_params']['type']]['filters']);
    }
}

// preserve clicked message on page refresh
if (isset($_GET['focus']) && filter_var($_GET['focus'], FILTER_VALIDATE_INT)) {
    $emsaListSelectedMsgId = (int) filter_input(INPUT_GET, 'focus', FILTER_SANITIZE_NUMBER_INT);
}

// set queue type
if (isset($_GET['type']) && filter_var($_GET['type'], FILTER_VALIDATE_INT)) {
    $type = (int) filter_input(INPUT_GET, 'type', FILTER_SANITIZE_NUMBER_INT);
} else {
    $type = ENTRY_STATUS;
}

session_write_close(); // done writing to session; prevent blocking

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

<h1 class="elrhdg"><span class="ui-icon ui-icon-header ui-icon-emsaqueue"></span><?php echo Udoh\Emsa\Utils\DisplayUtils::xSafe(EmsaUtils::getQueueName($type)); ?></h1>

<form name="search_form" id="search_form" method="POST" action="<?php echo $webappBaseUrl; ?>?selected_page=<?php echo $navSelectedPage; ?><?php echo ((isset($navSubmenu) && filter_var($navSubmenu, FILTER_VALIDATE_INT) && (intval($navSubmenu) > 0)) ? '&submenu=' . $navSubmenu : ''); ?><?php echo ((isset($navCat) && filter_var($navCat, FILTER_VALIDATE_INT) && (intval($navCat) > 0)) ? '&cat=' . $navCat : ''); ?><?php echo ((isset($navSubcat) && filter_var($navSubcat, FILTER_VALIDATE_INT) && (intval($navSubcat) > 0)) ? '&subcat=' . $navSubcat : ''); ?>&type=<?php echo (($type < 2) ? 1 : $type); ?>">

    <div class="emsa_search_controls ui-tabs ui-widget">
        <label for="q" class="emsa_form_heading" style="margin-right: 7px;">Search:</label><input type="text" name="q" id="q" class="vocab_query ui-corner-all" placeholder="Enter search terms..." value="<?php Udoh\Emsa\Utils\DisplayUtils::xEcho((string) $_SESSION[EXPORT_SERVERNAME]['emsa_params'][$_SESSION[EXPORT_SERVERNAME]['emsa_params']['type']]['q_display'], "UTF-8", false) ?>">
        <button name="q_go" id="q_go">Search</button>
        <button type="button" name="clear_filters" id="clear_filters" title="Clear all filters/search terms">Clear</button>
        <button type="button" name="toggle_filters" id="toggle_filters" title="Show/hide filters">Hide Filters</button>
<?php if (Udoh\Emsa\Auth\Authenticator::userHasPermission(Udoh\Emsa\Auth\Authenticator::URIGHTS_TAB_QA)) { ?>
            <button type="button" name="toggle_bulk" id="toggle_bulk" title="Show/hide bulk message actions">Hide Bulk Message Actions</button>
        <?php } ?>
    </div>

<?php
############### If filters applied, display which ones ###############
if (isset($_SESSION[EXPORT_SERVERNAME]['emsa_params'][$_SESSION[EXPORT_SERVERNAME]['emsa_params']['type']]['filters'])) {
    ?>
        <div class="vocab_search ui-widget ui-widget-content ui-state-highlight ui-corner-all" style="padding: 5px;">
            <span class="ui-icon ui-icon-elroptions" style="float: left; margin-right: .3em;"></span><p style="margin-left: 20px;">Filtering by 
    <?php
    $active_filters = 0;
    foreach ($_SESSION[EXPORT_SERVERNAME]['emsa_params'][$_SESSION[EXPORT_SERVERNAME]['emsa_params']['type']]['filters'] as $sqlfiltercol => $sqlfiltervals) {
        $sqlfiltercol_label = "Unknown";
        switch ($sqlfiltercol) {
            case "disease":
                $sqlfiltercol_label = "Disease";
                break;
            case "lab":
                $sqlfiltercol_label = "Lab";
                break;
            case "eflag":
                $sqlfiltercol_label = "Error Flag";
                break;
            case "evalue":
                $sqlfiltercol_label = "Error Details";
                break;
            case "showdeleted";
                $sqlfiltercol_label = "Show Deleted";
                break;
            case "showauto";
                $sqlfiltercol_label = "Manual vs. Automated";
                break;
            case "mflag":
                $sqlfiltercol_label = "Message Flag";
                break;
            case "clinician":
                $sqlfiltercol_label = "Clinician";
                break;
            case "testtype":
                $sqlfiltercol_label = "Test Type";
                break;
            case "testresult":
                $sqlfiltercol_label = "Lab Test Result";
                break;
            case "astresult":
                $sqlfiltercol_label = "Susceptibility Result";
                break;
        }
        if ($active_filters == 0) {
            echo "<strong>" . $sqlfiltercol_label . "</strong>";
        } else {
            echo ", <strong>" . $sqlfiltercol_label . "</strong>";
        }
        $active_filters++;
    }
    ?>
            </p>
        </div>
        <?php
    }
    ?>

    <div class="vocab_filter ui-widget ui-widget-content ui-corner-all">
        <div style="clear: both;"><span class="emsa_form_heading">Filters:</span></div>
        <?php
        // disease filter
        $diseaseFilter = new AccessibleMultiselectListbox($filterFactory->getConditionList(), $_SESSION[EXPORT_SERVERNAME]['emsa_params'][$_SESSION[EXPORT_SERVERNAME]['emsa_params']['type']]['filters']['disease'] ?? null);
        $diseaseFilter->render('Disease', 'f[disease]', true);

        // lab filter
        $labFilter = new AccessibleMultiselectListbox($filterFactory->getReporterList(), $_SESSION[EXPORT_SERVERNAME]['emsa_params'][$_SESSION[EXPORT_SERVERNAME]['emsa_params']['type']]['filters']['lab'] ?? null);
        $labFilter->render('Lab', 'f[lab]', true);

        // show deleted filter
        $showDeletedFilter = new AccessibleMultiselectListbox($filterFactory->getShowDeleted(), $_SESSION[EXPORT_SERVERNAME]['emsa_params'][$_SESSION[EXPORT_SERVERNAME]['emsa_params']['type']]['filters']['showdeleted'] ?? null);
        $showDeletedFilter->render('Show Deleted?', 'f[showdeleted]');

        if ($type == ASSIGNED_STATUS) {
            // assigned 'elr automation' filter
            $automationFilter = new AccessibleMultiselectListbox($filterFactory->getAutomation(), $_SESSION[EXPORT_SERVERNAME]['emsa_params'][$_SESSION[EXPORT_SERVERNAME]['emsa_params']['type']]['filters']['showauto'] ?? null);
            $automationFilter->render('Manual vs. Automated', 'f[showauto]');
        }

        // message flags filter
        $messageFlagFilter = new AccessibleMultiselectListbox($filterFactory->getEMSAMessageFlags(), $_SESSION[EXPORT_SERVERNAME]['emsa_params'][$_SESSION[EXPORT_SERVERNAME]['emsa_params']['type']]['filters']['mflag'] ?? null);
        $messageFlagFilter->render('Message Flags', 'f[mflag]', true);

        if ($type == PENDING_STATUS || $type == EXCEPTIONS_STATUS || $type == NEDSS_EXCEPTION_STATUS) {
            // exception type filter
            $exceptionFlagFilter = new AccessibleMultiselectListbox($filterFactory->getExceptionTypeList(), $_SESSION[EXPORT_SERVERNAME]['emsa_params'][$_SESSION[EXPORT_SERVERNAME]['emsa_params']['type']]['filters']['eflag'] ?? null);
            $exceptionFlagFilter->render('Exceptions', 'f[eflag]', true);

            // exception value filter
            echo "<div class=\"vocab_filter_container\"><label class=\"vocab_search_form2\" for=\"f_evalue\">Error Details</label><br>";
            echo '<textarea class="ui-corner-all" style="background-color: lightcyan; font-family: Consolas, \'Courier New\', sans-serif; border-color: #999; width: 300px; height: 95px;" name="f[evalue]" id="f_evalue">' . ((isset($_SESSION[EXPORT_SERVERNAME]['emsa_params'][$_SESSION[EXPORT_SERVERNAME]['emsa_params']['type']]['filters']['evalue'])) ? Udoh\Emsa\Utils\DisplayUtils::xSafe(CoreUtils::decodeIfBase64Encoded(rawurldecode(trim($_SESSION[EXPORT_SERVERNAME]['emsa_params'][$_SESSION[EXPORT_SERVERNAME]['emsa_params']['type']]['filters']['evalue'])))) : '') . '</textarea>';
            echo "</div>";
        }

        // test type filter
        $testTypeFilter = new AccessibleMultiselectListbox($filterFactory->getTestTypeList(), $_SESSION[EXPORT_SERVERNAME]['emsa_params'][$_SESSION[EXPORT_SERVERNAME]['emsa_params']['type']]['filters']['testtype'] ?? null);
        $testTypeFilter->render('Test Type', 'f[testtype]', true);

        // lab test result filter
        $testResultFilter = new AccessibleMultiselectListbox($filterFactory->getTestResultList(), $_SESSION[EXPORT_SERVERNAME]['emsa_params'][$_SESSION[EXPORT_SERVERNAME]['emsa_params']['type']]['filters']['testresult'] ?? null);
        $testResultFilter->render('Lab Test Result', 'f[testresult]', true);

        // susceptibility test result filter
        $astResultFilter = new AccessibleMultiselectListbox($filterFactory->getSusceptibilityResultList(), $_SESSION[EXPORT_SERVERNAME]['emsa_params'][$_SESSION[EXPORT_SERVERNAME]['emsa_params']['type']]['filters']['astresult'] ?? null);
        $astResultFilter->render('Susceptibility Result', 'f[astresult]', true);

        if (($type == ASSIGNED_STATUS) && (Udoh\Emsa\Auth\Authenticator::userHasPermission(Udoh\Emsa\Auth\Authenticator::URIGHTS_TAB_QA))) {
            // assigned QA sorting
            echo "<div class=\"vocab_filter_container\"><label class=\"vocab_search_form2\" for=\"sort\">Sort By</label><br>";
            echo '<select class="pseudo_select_label" name="sort" id="sort">';
            echo '<option value="6"' . ((isset($_SESSION[EXPORT_SERVERNAME]['emsa_params'][$_SESSION[EXPORT_SERVERNAME]['emsa_params']['type']]['sort_order']) && intval($_SESSION[EXPORT_SERVERNAME]['emsa_params'][$_SESSION[EXPORT_SERVERNAME]['emsa_params']['type']]['sort_order']) == 6) ? ' selected' : '') . '>Date Assigned (Newest First)</option>';
            echo '<option value="5"' . ((isset($_SESSION[EXPORT_SERVERNAME]['emsa_params'][$_SESSION[EXPORT_SERVERNAME]['emsa_params']['type']]['sort_order']) && intval($_SESSION[EXPORT_SERVERNAME]['emsa_params'][$_SESSION[EXPORT_SERVERNAME]['emsa_params']['type']]['sort_order']) == 5) ? ' selected' : '') . '>Date Assigned (Oldest First)</option>';
            echo '<option value="1"' . ((isset($_SESSION[EXPORT_SERVERNAME]['emsa_params'][$_SESSION[EXPORT_SERVERNAME]['emsa_params']['type']]['sort_order']) && intval($_SESSION[EXPORT_SERVERNAME]['emsa_params'][$_SESSION[EXPORT_SERVERNAME]['emsa_params']['type']]['sort_order']) == 1) ? ' selected' : '') . '>Date Reported (Oldest First)</option>';
            echo '<option value="2"' . ((isset($_SESSION[EXPORT_SERVERNAME]['emsa_params'][$_SESSION[EXPORT_SERVERNAME]['emsa_params']['type']]['sort_order']) && intval($_SESSION[EXPORT_SERVERNAME]['emsa_params'][$_SESSION[EXPORT_SERVERNAME]['emsa_params']['type']]['sort_order']) == 2) ? ' selected' : '') . '>Date Reported (Newest First)</option>';
            echo '<option value="3"' . ((isset($_SESSION[EXPORT_SERVERNAME]['emsa_params'][$_SESSION[EXPORT_SERVERNAME]['emsa_params']['type']]['sort_order']) && intval($_SESSION[EXPORT_SERVERNAME]['emsa_params'][$_SESSION[EXPORT_SERVERNAME]['emsa_params']['type']]['sort_order']) == 3) ? ' selected' : '') . '>Patient Name (A-Z)</option>';
            echo '<option value="4"' . ((isset($_SESSION[EXPORT_SERVERNAME]['emsa_params'][$_SESSION[EXPORT_SERVERNAME]['emsa_params']['type']]['sort_order']) && intval($_SESSION[EXPORT_SERVERNAME]['emsa_params'][$_SESSION[EXPORT_SERVERNAME]['emsa_params']['type']]['sort_order']) == 4) ? ' selected' : '') . '>Patient Name (Z-A)</option>';
            echo '</select>';
            echo "</div>";
        }

        if ($type == GRAY_STATUS) {
            // graylist sorting
            echo "<div class=\"vocab_filter_container\"><label class=\"vocab_search_form2\" for=\"sort\">Sort By</label><br>";
            echo '<select class="pseudo_select_label" name="sort" id="sort">';
            echo '<option value="7"' . ((isset($_SESSION[EXPORT_SERVERNAME]['emsa_params'][$_SESSION[EXPORT_SERVERNAME]['emsa_params']['type']]['sort_order']) && intval($_SESSION[EXPORT_SERVERNAME]['emsa_params'][$_SESSION[EXPORT_SERVERNAME]['emsa_params']['type']]['sort_order']) == 7) ? ' selected' : '') . '>Date Graylisted (Oldest First)</option>';
            echo '<option value="8"' . ((isset($_SESSION[EXPORT_SERVERNAME]['emsa_params'][$_SESSION[EXPORT_SERVERNAME]['emsa_params']['type']]['sort_order']) && intval($_SESSION[EXPORT_SERVERNAME]['emsa_params'][$_SESSION[EXPORT_SERVERNAME]['emsa_params']['type']]['sort_order']) == 8) ? ' selected' : '') . '>Date Graylisted (Newest First)</option>';
            echo '<option value="1"' . ((isset($_SESSION[EXPORT_SERVERNAME]['emsa_params'][$_SESSION[EXPORT_SERVERNAME]['emsa_params']['type']]['sort_order']) && intval($_SESSION[EXPORT_SERVERNAME]['emsa_params'][$_SESSION[EXPORT_SERVERNAME]['emsa_params']['type']]['sort_order']) == 1) ? ' selected' : '') . '>Date Reported (Oldest First)</option>';
            echo '<option value="2"' . ((isset($_SESSION[EXPORT_SERVERNAME]['emsa_params'][$_SESSION[EXPORT_SERVERNAME]['emsa_params']['type']]['sort_order']) && intval($_SESSION[EXPORT_SERVERNAME]['emsa_params'][$_SESSION[EXPORT_SERVERNAME]['emsa_params']['type']]['sort_order']) == 2) ? ' selected' : '') . '>Date Reported (Newest First)</option>';
            echo '<option value="3"' . ((isset($_SESSION[EXPORT_SERVERNAME]['emsa_params'][$_SESSION[EXPORT_SERVERNAME]['emsa_params']['type']]['sort_order']) && intval($_SESSION[EXPORT_SERVERNAME]['emsa_params'][$_SESSION[EXPORT_SERVERNAME]['emsa_params']['type']]['sort_order']) == 3) ? ' selected' : '') . '>Patient Name (A-Z)</option>';
            echo '<option value="4"' . ((isset($_SESSION[EXPORT_SERVERNAME]['emsa_params'][$_SESSION[EXPORT_SERVERNAME]['emsa_params']['type']]['sort_order']) && intval($_SESSION[EXPORT_SERVERNAME]['emsa_params'][$_SESSION[EXPORT_SERVERNAME]['emsa_params']['type']]['sort_order']) == 4) ? ' selected' : '') . '>Patient Name (Z-A)</option>';
            echo '</select>';
            echo "</div>";
        }
        ?>
        <br><br><button name="apply_filters" id="apply_filters" style="clear: both; float: left; margin: 5px;">Apply Filters</button>
    </div>

</form>

<form name="bulk_form" id="bulk_form" method="POST" action="<?php echo $webappBaseUrl; ?>index.php?selected_page=<?php echo $navSelectedPage; ?><?php echo ((isset($navSubmenu) && filter_var($navSubmenu, FILTER_VALIDATE_INT) && (intval($navSubmenu) > 0)) ? '&submenu=' . $navSubmenu : ''); ?><?php echo ((isset($navCat) && filter_var($navCat, FILTER_VALIDATE_INT) && (intval($navCat) > 0)) ? '&cat=' . $navCat : ''); ?><?php echo ((isset($navSubcat) && filter_var($navSubcat, FILTER_VALIDATE_INT) && (intval($navSubcat) > 0)) ? '&subcat=' . $navSubcat : ''); ?>&type=<?php echo $type; ?>">
    <div id="bulk_form_container" class="vocab_search ui-tabs ui-widget">
        <input type="hidden" name="bulk_action" id="bulk_action" value="">
        <input type="hidden" name="bulk_target" id="bulk_target" value="">

        <span class="emsa_form_heading" style="margin-right: 7px;">Bulk Actions:</span>
        <button type="button" name="bulk_selectall" id="bulk_selectall" title="Select all messages on this page">Select All</button>
        <button type="button" name="bulk_selectnone" id="bulk_selectnone" title="De-select all messages on this page">Select None</button>

<?php if ($type != ASSIGNED_STATUS) { ?>
            <div id="bulk_form_action_buttons" class="ui-corner-all">
                <button type="button" name="bulk_retry" id="bulk_retry" title="Re-process all selected messages" disabled>Bulk Retry</button>

    <?php if (Udoh\Emsa\Auth\Authenticator::userHasPermission(Udoh\Emsa\Auth\Authenticator::URIGHTS_ACTION_MOVE)) { ?>
                    <div class="emsa_toolbar_separator"></div>
                    <button type="button" name="bulk_move" id="bulk_move" title="Move all selected messages to another queue" disabled>Bulk Move:</button>
                    <label class="sr-only" for="bulk_move_target">Choose queue to move to</label>
                    <select class="ui-corner-all" name="bulk_move_target" id="bulk_move_target">
                        <option value="-1" selected>Move to...</option>
        <?php
        $bulkDestinationList = EmsaUtils::getMoveToQueueList($emsaDbFactory->getConnection(), $type);
        if (!EmsaUtils::emptyTrim($bulkDestinationList)) {
            foreach ($bulkDestinationList as $bulkDestination) {
                echo '<option value="' . intval($bulkDestination['id']) . '">' . htmlentities($bulkDestination['name'], ENT_QUOTES, 'UTF-8') . '</option>' . PHP_EOL;
            }
        }
        ?>
                    </select>
                    <?php } ?>

                <?php if (Udoh\Emsa\Auth\Authenticator::userHasPermission(Udoh\Emsa\Auth\Authenticator::URIGHTS_ACTION_DELETE)) { ?>
                    <div class="emsa_toolbar_separator"></div>
                    <button type="button" name="bulk_delete" id="bulk_delete" title="Delete all selected messages" disabled>Bulk Delete</button>
    <?php } ?>
                <br><br>

                <button type="button" name="bulk_flag" id="bulk_flag" title="Set a QA flag on all selected messages" disabled>Bulk Set QA Flag:</button>
                <label class="sr-only" for="bulk_qa_flag_id">Select QA Flag to set</label>
                <select class="ui-corner-all" name="bulk_qa_flag_id" id="bulk_qa_flag_id">
                    <option value="-1" selected>Select QA Flag to set...</option>
                    <option value="<?php echo intval(EMSA_FLAG_QA_MANDATORY); ?>">Missing Mandatory Fields</option>
                    <option value="<?php echo intval(EMSA_FLAG_QA_CODING); ?>">Coding/Vocabulary Errors</option>
                    <option value="<?php echo intval(EMSA_FLAG_QA_MQF); ?>">MQF Structural Errors</option>
                    <option value="<?php echo intval(EMSA_FLAG_FIX_DUPLICATE); ?>">Fix Duplicate</option>
                    <option value="<?php echo intval(EMSA_FLAG_DE_NEEDFIX); ?>">Needs Fixing</option>
                    <option value="<?php echo intval(EMSA_FLAG_DE_ERROR); ?>">Data Entry Error (Specify type)</option>
                    <option value="<?php echo intval(EMSA_FLAG_DE_OTHER); ?>">Other (Specify reason)</option>
                </select>
                <label class="sr-only" for="bulk_flag_de_error_type">Select type of Data Entry error</label>
                <select class="ui-corner-all" name="bulk_flag_de_error_type" id="bulk_flag_de_error_type">
                    <option value="" selected>Select type of Data Entry error...</option>
                    <option value="Error at Reporting Facility">Error at Reporting Facility</option>
                    <option value="Error in Surveillance System">Error in Surveillance System</option>
                    <option value="Undetermined Error">Undetermined Error</option>
                    <option value="Alias">Alias</option>
                </select>
                <label class="sr-only" for="bulk_flag_other_reason">Explain 'Other' reason</label>
                <input class="ui-corner-all" type="text" name="bulk_flag_other_reason" id="bulk_flag_other_reason" placeholder="Explain 'Other' reason...">

            </div>
<?php } ?>
    </div>
</form>

<?php
// handle any edit/move/retry/delete actions...
include_once __DIR__ . '/emsa_actions.php';

// display lists of messages in EMSA queues
$emsaFieldset = new Udoh\Emsa\UI\Queue\EmsaListFieldset($emsaDbFactory->getConnection(), $appClientList, $type, intval($navSelectedPage), intval($navSubmenu), intval($navCat), intval($navSubcat));

if (($type === ENTRY_STATUS) || ($type === UNPROCESSED_STATUS) || ($type === LOCKED_STATUS) || ($type === EXCEPTIONS_STATUS) || ($type === NEDSS_EXCEPTION_STATUS) || ($type === QA_STATUS) || ($type === SEMI_AUTO_STATUS)) {
    $emsaFieldset->getImmediateList();  // immediately-notifiable conditions
}
$emsaFieldset->getNonImmediateList();  // all other conditions

if (($type === ENTRY_STATUS) || ($type === UNPROCESSED_STATUS) || ($type === LOCKED_STATUS) || ($type === EXCEPTIONS_STATUS) || ($type === NEDSS_EXCEPTION_STATUS) || ($type === QA_STATUS) || ($type === SEMI_AUTO_STATUS)) {
    ?>

    <div id="no_messages" class="import_widget ui-widget import_error ui-state-highlight ui-corner-all" style="display: none; padding: 5px;"><span class="ui-icon ui-icon-info" style="float: left; margin-right: .3em;"></span><p style="margin-left: 20px;">No messages found!</p></div>

    <div id="confirm_addnew_dialog" title="Really Add New Person?" style="display: none;">
        <p><span class="ui-icon ui-icon-elrerror" style="float:left; margin:0 7px 110px 0;"></span><strong>Warning:</strong>  You have selected one or more matching people from the search results.<br><br>Are you sure you want to create a new person instead of adding these results to the people selected below?</p>
    </div>

    <script type="text/javascript">
        $(function() {
            if ($(".emsa-list-nonimmediate").is(":hidden") && $(".emsa-list-immediate").is(":hidden")) {
                $("#no_messages").show();
            }
        });
    </script>
    <?php
}
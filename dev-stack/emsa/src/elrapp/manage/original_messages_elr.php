<?php
/**
 * Copyright (c) 2017 Utah Department of Technology Services and Utah Department of Health
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
 * @copyright Copyright (c) 2017 Utah Department of Technology Services and Utah Department of Health
 */

use Udoh\Emsa\Auth\Authenticator;
use Udoh\Emsa\PDOFactory\PostgreSQL;
use Udoh\Emsa\UI\AccessibleMultiselectListbox;
use Udoh\Emsa\UI\Queue\FilterFactory;
use Udoh\Emsa\Utils\CoreUtils;
use Udoh\Emsa\Utils\DateTimeUtils;
use Udoh\Emsa\Utils\DisplayUtils;
use Udoh\Emsa\Utils\ExceptionUtils;

if (!class_exists('\Udoh\Emsa\Auth\Authenticator') || !Authenticator::userHasPermission(Authenticator::URIGHTS_ADMIN)) {
    ob_end_clean();
    header($_SERVER['SERVER_PROTOCOL'] . " 404 Not Found", TRUE, 404);
    exit;
}

if (!is_array($_SESSION[EXPORT_SERVERNAME]) || !array_key_exists('original_messages_elr', $_SESSION[EXPORT_SERVERNAME]) || !is_array($_SESSION[EXPORT_SERVERNAME]['original_messages_elr'])) {
    $_SESSION[EXPORT_SERVERNAME]['original_messages_elr'] = [];
}

try {
    // get connection to replication DB
    $origELRDbFactory = new PostgreSQL($replicationDbHost, $replicationDbPort, $emsaDbName, $emsaDbUser, $emsaDbPass, $emsaDbSchemaPDO);
    $origELRDbConn = $origELRDbFactory->getConnection();
} catch (Throwable $e) {
    // fall back to main admin DB connection if replication is not available for whatever reason
    $origELRDbConn = $adminDbConn;
}

$validConnectors = CoreUtils::getELRConnectorList($origELRDbConn);
$filterFactory = new FilterFactory($origELRDbConn);

// check for session freshness since last update to session...
// filters are stored in session data, this hack gives us a way to force
// session data to be refreshed without forcing users to clear cookies if the data
// is updated mid-session, so that the latest filters are used)
$modelLastUpdated = filemtime(__DIR__ . "/original_messages_elr.php");

// check "freshness date"...
if (isset($_SESSION[EXPORT_SERVERNAME]['original_messages_elr']['emsa_model_fresh'])) {
    if ($_SESSION[EXPORT_SERVERNAME]['original_messages_elr']['emsa_model_fresh'] < $modelLastUpdated) {
        // old model data; unset vocab_params & set a new "freshness date"...
        $_SESSION[EXPORT_SERVERNAME]['original_messages_elr'] = [];
        $_SESSION[EXPORT_SERVERNAME]['original_messages_elr']['emsa_model_fresh'] = time();
    }
} else {
    // hack for sessions set before "freshness date" implemented
    $_SESSION[EXPORT_SERVERNAME]['original_messages_elr'] = [];
    $_SESSION[EXPORT_SERVERNAME]['original_messages_elr']['emsa_model_fresh'] = time();
}

$cleanMessageIDString = filter_input(INPUT_GET, 'message_ids', FILTER_SANITIZE_STRING);
$cleanMessageIDs = [];

if (!empty($cleanMessageIDString)) {
    $cleanMessageIDs = array_filter(filter_var_array(array_filter(array_filter(explode(",", $cleanMessageIDString)), 'trim'), FILTER_SANITIZE_NUMBER_INT));
}

if (!empty($cleanMessageIDs)) {
    $_SESSION[EXPORT_SERVERNAME]['original_messages_elr']['message_ids'] = $cleanMessageIDs;
}

$cleanLabSelector = filter_input(INPUT_GET, 'connector', FILTER_DEFAULT, FILTER_REQUIRE_ARRAY);

if (!empty($cleanLabSelector) && is_array($cleanLabSelector)) {
    // clear saved list first, then rebuild
    $_SESSION[EXPORT_SERVERNAME]['original_messages_elr']['connector'] = [];

    foreach ($cleanLabSelector as $cleanLab) {
        $candidateLab = filter_var($cleanLab, FILTER_SANITIZE_STRING);
        if (!empty($candidateLab) && in_array($candidateLab, $validConnectors)) {
            $_SESSION[EXPORT_SERVERNAME]['original_messages_elr']['connector'][] = $candidateLab;
        }
    }
}

if (array_key_exists('original_messages_elr', $_SESSION[EXPORT_SERVERNAME]) && !array_key_exists('page_offset', $_SESSION[EXPORT_SERVERNAME]['original_messages_elr'])) {
    $_SESSION[EXPORT_SERVERNAME]['original_messages_elr']['page_offset'] = 0;
}

$_SESSION[EXPORT_SERVERNAME]['original_messages_elr']['rows_per_page'] = 25;

$cleanPageOffset = filter_input(INPUT_GET, 'page', FILTER_SANITIZE_NUMBER_INT);

if (!empty($cleanPageOffset)) {
    $_SESSION[EXPORT_SERVERNAME]['original_messages_elr']['page_offset'] = ((int) $cleanPageOffset) - 1;
}

?>
<script>
	$(function() {
		$("#addnew_form").show();
		
		$("#btn_submit_view").button({
			icon: "ui-icon-elrsearch"
		});

		$("#btn_reset_view").button({
			icon: "ui-icon-elrcancel"
		}).on("click", function (e) {
		    e.preventDefault();
		    let searchForm = $("#raw_msgs_form");
		    let msListBoxes = $("ul.vocab_filter_checklist[role=listbox]");
            searchForm.find(':input').each(function () {
                switch (this.type) {
                    case 'text':
                    case 'textarea':
                    case 'select-one':
                    case 'select-multiple':
                        $(this).val('');
                        break;
                }
            });
            msListBoxes.removeAttr("aria-activedescendant");
            msListBoxes.children().removeClass("multiselect-focused");
            msListBoxes.children().attr("aria-selected", "false");
            msListBoxes.find("input[type=checkbox]").prop("checked", false);
        });
		
		$(".date-range").datepicker();

		$(".scroll-on-focus").on("focus", function () {
            $(this).css("overflow", "auto");
        }).on("blur", function () {
            $(this).css("overflow", "hidden");
        });
		
	});
</script>

<style type="text/css">
	input[type="radio"] { min-width: 0 !important; }
	#raw_msgs_form div:not(.vocab_filter_checklist) { display: inline-block; margin: 7px; padding: 7px; }
    #labResults td { border-bottom-width: 2px; border-bottom-color: darkorange; }
	.audit_log td { border-bottom-width: 1px !important; border-bottom-color: lightgray !important; }
	.vocab_search_form2 { margin: 0 !important; }
    .vocab_filter { margin: 1% 0 !important; padding: 0 !important; width: 100%; border: none !important; }
    .vocab_filter input[type=text] { box-sizing: border-box; width: 100%; }
    .addnew_lab button { margin: 2% 5px 0 0 !important; }
    .vocab_filter_checklist { height: 10em; }
    div.lab_results_container { margin: 10px 0 !important; }

    .vocab_filter fieldset.vocab_filter_container {
		margin-left: 0;
	}

    .scroll-on-focus { overflow: hidden; }
    .raw_message_header { display: table; table-layout: fixed; width: 100%; }
    .raw_message_header_col { display: table-cell; width: 33%; }
    .raw_message_counter { color: crimson; font-weight: bold; }

    table#labResults tr td textarea {
        font-family: monospace !important;
        font-size: 1.2em;
        white-space: pre;
        height: 20em;
        width: 97%;
        color: brown;
        box-shadow: 0 0 3px inset cadetblue;
        margin: 1%;
        padding: 2px 3px;
    }

    table#labResults tr td {
        font-size: 0.9em;
        font-family: Roboto, 'Open Sans', Arial, sans-serif !important;
    }
</style>

<?php

// filter handling shenanigans
if (isset($_GET['text_search'])) {
    if (strlen(trim($_GET['text_search'])) > 0) {
        $_SESSION[EXPORT_SERVERNAME]['original_messages_elr']['keywords'] = filter_var(trim($_GET['text_search']), FILTER_SANITIZE_STRING);
    } else {
        $_SESSION[EXPORT_SERVERNAME]['original_messages_elr']['keywords'] = null;

        if (empty($cleanLabSelector)) {
            // if we got an empty param for text search AND there are no connectors specified, we're in a clear/reset mode; unset all saved connectors and pagination info
            $_SESSION[EXPORT_SERVERNAME]['original_messages_elr']['connector'] = [];
            $_SESSION[EXPORT_SERVERNAME]['original_messages_elr']['page_offset'] = 0;
        }
    }
}

if (isset($_GET['message_ids'])) {
    if (strlen(trim($_GET['message_ids'])) < 1) {
        // empty 'message_ids' sent; clear all saved message IDs
        $_SESSION[EXPORT_SERVERNAME]['original_messages_elr']['message_ids'] = [];
    }
}

if (isset($_GET['date_from'])) {
    if (strlen(trim($_GET['date_from'])) > 0) {
        $_SESSION[EXPORT_SERVERNAME]['original_messages_elr']['date_from'] = filter_var(trim($_GET['date_from']), FILTER_SANITIZE_STRING);
    } else {
        $_SESSION[EXPORT_SERVERNAME]['original_messages_elr']['date_from'] = null;
    }
}

if (isset($_GET['date_to'])) {
    if (strlen(trim($_GET['date_to'])) > 0) {
        $_SESSION[EXPORT_SERVERNAME]['original_messages_elr']['date_to'] = filter_var(trim($_GET['date_to']), FILTER_SANITIZE_STRING);
    } else {
        $_SESSION[EXPORT_SERVERNAME]['original_messages_elr']['date_to'] = null;
    }
}

if (isset($_GET['etype'])) {
    if (strlen(trim($_GET['etype'])) > 0) {
        $_SESSION[EXPORT_SERVERNAME]['original_messages_elr']['etype'] = filter_input(INPUT_GET, 'etype', FILTER_UNSAFE_RAW);
    } else {
        $_SESSION[EXPORT_SERVERNAME]['original_messages_elr']['etype'] = null;
    }
}

if (isset($_GET['evalue'])) {
    if (strlen(trim($_GET['evalue'])) > 0) {
        $_SESSION[EXPORT_SERVERNAME]['original_messages_elr']['evalue'] = CoreUtils::decodeIfBase64Encoded(filter_var(rawurldecode(trim($_GET['evalue'])), FILTER_UNSAFE_RAW));
    } else {
        $_SESSION[EXPORT_SERVERNAME]['original_messages_elr']['evalue'] = null;
    }
}

?>

<h1 class="elrhdg"><span class="ui-icon ui-icon-header ui-icon-elrcategory"></span>Raw Received ELR Messages</h1>

<div id="addnew_form" class="addnew_lab ui-widget ui-widget-content ui-corner-all" style="clear: left; margin: 0; float: left; width: 25%; margin-top: 10px !important;">
	<div style="clear: both;"><span class="emsa_form_heading">Limit Results:</span></div>
	<form id="raw_msgs_form" method="GET">
        <div class="vocab_filter">
            <?php
                $diseaseFilter = new AccessibleMultiselectListbox($filterFactory->getELRConnectorList(), $_SESSION[EXPORT_SERVERNAME]['original_messages_elr']['connector'] ?? null);
                $diseaseFilter->render('Facility', 'connector', true);
            ?>
        </div>
        
		<div class="vocab_filter">
            <label class="vocab_search_form2" for="message_ids">Message ID(s):</label><br>
            <input class="ui-corner-all" type="text" name="message_ids" id="message_ids" placeholder="Separate multiple IDs with commas" value="<?php echo (array_key_exists('message_ids', $_SESSION[EXPORT_SERVERNAME]['original_messages_elr']) && is_array($_SESSION[EXPORT_SERVERNAME]['original_messages_elr']['message_ids'])) ? implode(", ", $_SESSION[EXPORT_SERVERNAME]['original_messages_elr']['message_ids']) : ""; ?>">
        </div>

        <div class="vocab_filter">
            <label class="vocab_search_form2" for="text_search">Search Message Contents:</label><br>
            <input class="ui-corner-all" type="text" name="text_search" id="text_search" value="<?php echo $_SESSION[EXPORT_SERVERNAME]['original_messages_elr']['keywords'] ?? ""; ?>">
        </div>

        <div class="vocab_filter">
            <label class="vocab_search_form2" for="date_from">Start Date:</label><br>
            <input class="date-range ui-corner-all" type="text" name="date_from" id="date_from" value="<?php echo $_SESSION[EXPORT_SERVERNAME]['original_messages_elr']['date_from'] ?? ""; ?>" placeholder="Any Time">
        </div>

        <div class="vocab_filter">
            <label class="vocab_search_form2" for="date_to">End Date</label><br>
            <input class="date-range ui-corner-all" type="text" name="date_to" id="date_to" value="<?php echo $_SESSION[EXPORT_SERVERNAME]['original_messages_elr']['date_to'] ?? ""; ?>" placeholder="Present">
        </div>
        
        <div class="vocab_filter">
            <label class="vocab_search_form2" for="etype">Preprocessor Exception Type:</label><br>
            <input class="ui-corner-all" type="text" name="etype" id="etype" value="<?php echo htmlspecialchars($emsaHTMLPurifier->purify($_SESSION[EXPORT_SERVERNAME]['original_messages_elr']['etype'] ?? "")); ?>">
        </div>

        <div class="vocab_filter">
            <label class="vocab_search_form2" for="evalue">Preprocessor Exception Value:</label><br>
            <input class="ui-corner-all" type="text" name="evalue" id="evalue" value="<?php echo htmlspecialchars($emsaHTMLPurifier->purify($_SESSION[EXPORT_SERVERNAME]['original_messages_elr']['evalue'] ?? "")); ?>">
        </div>
        
        <input type="hidden" name="selected_page" id="selected_page" value="<?php echo (int) $navSelectedPage; ?>">
		<input type="hidden" name="submenu" id="submenu" value="<?php echo (int) $navSubmenu; ?>">
		<input type="hidden" name="cat" id="cat" value="<?php echo (int) $navCat; ?>">
        <br><button type="submit" name="btn_submit_view" id="btn_submit_view">Search</button><button type="reset" name="btn_reset_view" id="btn_reset_view">Clear Filters</button>
	</form>
</div>

<div class="lab_results_container ui-widget ui-corner-all" style="position: absolute; clear: right; float: left; width: 70%; left: 28%;">
    <?php

    try {
        $results = [];
        $params = [];
        $whereItems = [];
        $preWhereItems = [];
        $preParams = [];

        // connectors...
        if (!empty($_SESSION[EXPORT_SERVERNAME]['original_messages_elr']['connector'])) {
            $whereConnectors = [];
            foreach ($_SESSION[EXPORT_SERVERNAME]['original_messages_elr']['connector'] as $whereConnector) {
                $whereConnectors[] = "(connector = ?)";
                $preParams[] = $whereConnector;
                $params[] = $whereConnector;
            }

            $whereConnectorsStr = implode(" OR ", $whereConnectors);
            $whereItems[] = "($whereConnectorsStr)";
            $preWhereItems[] = "($whereConnectorsStr)";
        }

        $preprocessorMessageIDs = null;
        if (!empty($_SESSION[EXPORT_SERVERNAME]['original_messages_elr']['etype']) || !empty($_SESSION[EXPORT_SERVERNAME]['original_messages_elr']['evalue'])) {
            $preprocessorMessageIDs = [];
            try {
                $preSql = "SELECT DISTINCT(om.id) AS id
                            FROM system_original_messages om
                            INNER JOIN preprocessor_exceptions pe ON (pe.system_original_messages_id = om.id)";

                if (!empty($_SESSION[EXPORT_SERVERNAME]['original_messages_elr']['etype'])) {
                    $preWhereItems[] = "(pe.exception_message ILIKE '%' || ? || '%')";
                    $preParams[] = $_SESSION[EXPORT_SERVERNAME]['original_messages_elr']['etype'];
                }

                if (!empty($_SESSION[EXPORT_SERVERNAME]['original_messages_elr']['evalue'])) {
                    $preWhereItems[] = "(pe.info ILIKE '%' || ? || '%')";
                    $preParams[] = $_SESSION[EXPORT_SERVERNAME]['original_messages_elr']['evalue'];
                }

                if (!empty($preWhereItems)) {
                    $preWhereClauseStr = implode(" AND ", $preWhereItems);
                    $preSql .= "\nWHERE $preWhereClauseStr";
                }

                $preSql .= ";";

                $preStmt = $origELRDbConn->prepare($preSql);

                if ($preStmt->execute($preParams)) {
                    while ($preRow = $preStmt->fetchObject()) {
                        $preprocessorMessageIDs[] = (int) $preRow->id;
                    }
                }
            } catch (Throwable $e2) {
                ExceptionUtils::logException($e2);
            } finally {
                $preRow = null;
                $preStmt = null;
            }
        }

        $sql = "WITH mc AS (
                    SELECT id, count(*) over() AS messagecount
                    FROM elr.system_original_messages";

        // start injecting WHERE stuff

        // message IDs
        $whereMessageIDs = [];
        if (!empty($_SESSION[EXPORT_SERVERNAME]['original_messages_elr']['message_ids'])) {
            foreach ($_SESSION[EXPORT_SERVERNAME]['original_messages_elr']['message_ids'] as $whereMessageID) {
                if (!empty($whereMessageID) && !in_array((int)$whereMessageID, $whereMessageIDs)) {
                    $whereMessageIDs[] = (int)$whereMessageID;
                }
            }
        }

        if (is_array($preprocessorMessageIDs)) {
            if (count($preprocessorMessageIDs) > 0) {
                foreach ($preprocessorMessageIDs as $preprocessorMessageID) {
                    if (!empty($preprocessorMessageID) && !in_array((int)$preprocessorMessageID, $whereMessageIDs)) {
                        $whereMessageIDs[] = (int)$preprocessorMessageID;
                    }
                }
            } else {
                // we're looking for preprocessor exceptions, but none were found; ensure that no results are returned
                $whereItems[] = '(TRUE IS FALSE)';
            }
        }

        foreach ($whereMessageIDs as $whereMessageIDItem) {
            $params[] = $whereMessageIDItem;
            $whereMessageIDStr[] = "(id = ?)";
        }
        if (!empty($whereMessageIDStr)) {
            $whereItems[] = '(' . implode(" OR ", $whereMessageIDStr) . ')';
        }

        // message body search
        if (!empty($_SESSION[EXPORT_SERVERNAME]['original_messages_elr']['keywords'])) {
            $whereItems[] = "(message ILIKE '%' || ? || '%')";
            $params[] = $_SESSION[EXPORT_SERVERNAME]['original_messages_elr']['keywords'];
        }

        // start date
        if (!empty($_SESSION[EXPORT_SERVERNAME]['original_messages_elr']['date_from'])) {
            try {
                $startDate = DateTimeUtils::createMixed($_SESSION[EXPORT_SERVERNAME]['original_messages_elr']['date_from']);
                $whereItems[] = "(created_at >= ?)";
                $params[] = $startDate->format("Y-m-d H:i:s");
            } catch (Throwable $e) {
                $e = null;
            }
        }

        // end date
        if (!empty($_SESSION[EXPORT_SERVERNAME]['original_messages_elr']['date_to'])) {
            try {
                $endDate = DateTimeUtils::createMixed($_SESSION[EXPORT_SERVERNAME]['original_messages_elr']['date_to']);
                $endDate->add(DateInterval::createFromDateString('1 day'));
                $whereItems[] = "(created_at < ?)";
                $params[] = $endDate->format("Y-m-d H:i:s");
            } catch (Throwable $e) {
                $e = null;
            }
        }

        if (!empty($whereItems)) {
            $whereClauseStr = implode(" AND ", $whereItems);
            $sql .= "\nWHERE $whereClauseStr";
        }

        $sql .= "\n)
                SELECT o.id AS id, o.created_at AS created_at, o.connector AS connector, o.message, max(mc.messagecount) AS total_messagecount 
                FROM system_original_messages o
                INNER JOIN mc ON (o.id = mc.id)
                GROUP BY 1, 2, 3, 4
                ORDER BY 2 DESC";

        // set limit
        $params[] = $_SESSION[EXPORT_SERVERNAME]['original_messages_elr']['rows_per_page'];
        $sql .= "\nLIMIT ?";

        // set offset, if exists
        if (array_key_exists('page_offset', $_SESSION[EXPORT_SERVERNAME]['original_messages_elr']) && !empty($_SESSION[EXPORT_SERVERNAME]['original_messages_elr']['page_offset'])) {
            $rowOffset = (int) $_SESSION[EXPORT_SERVERNAME]['original_messages_elr']['page_offset'] * (int) $_SESSION[EXPORT_SERVERNAME]['original_messages_elr']['rows_per_page'];
            $params[] = $rowOffset;
            $sql .= "\nOFFSET ?";
        }

        $sql .= ";";
        $stmt = $origELRDbConn->prepare($sql);



        $totalRows = 0;
        $rowsThisPage = 0;

        if ($stmt->execute($params)) {
            while ($row = $stmt->fetchObject()) {
                $rowsThisPage++;
                $totalRows = (int) $row->total_messagecount;

                try {
                    $rowCreatedAt = DateTimeUtils::createMixed((string) $row->created_at)->format("Y-m-d H:i:s");
                } catch (Throwable $edc) {
                    $edc = null;
                    $rowCreatedAt = null;
                }

                $results[] = [
                    'id' => (int) $row->id,
                    'created_at' => $rowCreatedAt,
                    'connector' => (string) $row->connector,
                    'message' => (string) $row->message
                ];
            }
        }
    } catch (Throwable $e) {
        ExceptionUtils::logException($e);
    } finally {
        $row = null;
        $stmt = null;
    }

    if (!empty($results)) {
        if (!empty($rowOffset)) {
            $pageRowStart = $rowOffset + 1;
            $page = (int) $_SESSION[EXPORT_SERVERNAME]['original_messages_elr']['page_offset'] + 1;
        } else {
            $page = 1;
            $pageRowStart = 1;
        }

        if ($totalRows > (int) $_SESSION[EXPORT_SERVERNAME]['original_messages_elr']['rows_per_page']) {
            $pagesFound = (int) ((int) $totalRows / (int) $_SESSION[EXPORT_SERVERNAME]['original_messages_elr']['rows_per_page']);
            $modPagesFound = (int) ((int) $totalRows % (int) $_SESSION[EXPORT_SERVERNAME]['original_messages_elr']['rows_per_page']);

            if ($modPagesFound > 0) {
                $pagesFound++;
            }
        } else {
            $pagesFound = 1;
        }

        $pageRowEnd = ($pageRowStart + $rowsThisPage) - 1;

        // paging stuff...
        echo '<div class="raw_message_counter">';
        echo "Showing messages $pageRowStart &ndash; $pageRowEnd of $totalRows total messages found (page $page of $pagesFound)<br>";

        if ($page > 1) {
            echo '[<a href="' . MAIN_URL . '/index.php?cat=11&selected_page=6&submenu=5&page=1">First Page</a>]';
            echo '[<a href="' . MAIN_URL . '/index.php?cat=11&selected_page=6&submenu=5&page=' . (int) ($page - 1) . '">Previous Page</a>]';
        }

        if ($page < $pagesFound) {
            echo '[<a href="' . MAIN_URL . '/index.php?cat=11&selected_page=6&submenu=5&page=' . (int) ($page + 1) . '">Next Page</a>]';
            echo '[<a href="' . MAIN_URL . '/index.php?cat=11&selected_page=6&submenu=5&page=' . (int) $pagesFound . '">Last Page</a>]';
        }

        echo '</div>';

        echo '<table id="labResults" style="table-layout: fixed;">';

        // iterate over results
        foreach ($results as $result) {
            echo '<tr>';
            echo '<td>';
            echo '<div class="raw_message_header">';
            echo '<div class="raw_message_header_col"><b>Facility:</b> ' . DisplayUtils::xSafe($result['connector'], "UTF-8", false) . '</div>';
            echo '<div class="raw_message_header_col"><b>ID:</b> ' . (int) $result['id'] . ' (<a href="' . MAIN_URL . '/index.php?selected_page=6&submenu=5&cat=5&view_type=4&original_message_id=' . (int) $result['id'] . '" target="_blank">Audit Log</a>)</div>';
            echo '<div class="raw_message_header_col"><b>Received:</b> ' . DisplayUtils::xSafe($result['created_at']) . '</div>';
            echo '</div>';
            echo '<label class="sr-only" for="msg_' . (int) $result['id'] . '">Contents of Message ID ' . (int) $result['id'] . ' (Read-only)</label>';
            echo '<textarea readonly class="ui-corner-all scroll-on-focus" id="msg_' . (int) $result['id'] . '">';

            if (stripos($result['message'], "MSH|") === false) {
                echo DisplayUtils::formatXml(trim($result['message']));
            } else {
                echo str_replace("\\015", PHP_EOL, trim($result['message']));
            }

            echo '</textarea></td>';
            echo '</tr>';
        }

        echo '</table>';

        // paging stuff again...
        echo '<div class="raw_message_counter">';
        echo "Showing messages $pageRowStart &ndash; $pageRowEnd of $totalRows total messages found (page $page of $pagesFound)<br>";

        if ($page > 1) {
            echo '[<a href="' . MAIN_URL . '/index.php?cat=11&selected_page=6&submenu=5&page=1">First Page</a>]';
            echo '[<a href="' . MAIN_URL . '/index.php?cat=11&selected_page=6&submenu=5&page=' . (int) ($page - 1) . '">Previous Page</a>]';
        }

        if ($page < $pagesFound) {
            echo '[<a href="' . MAIN_URL . '/index.php?cat=11&selected_page=6&submenu=5&page=' . (int) ($page + 1) . '">Next Page</a>]';
            echo '[<a href="' . MAIN_URL . '/index.php?cat=11&selected_page=6&submenu=5&page=' . (int) $pagesFound . '">Last Page</a>]';
        }

        echo '<br><br>';
        echo '</div>';
    } else {
        echo '<div class="raw_message_counter">No ELR Messages Found</div>';
    }

    ?>
</div>
<?php
/**
 * Copyright (c) 2019 Utah Department of Technology Services and Utah Department of Health
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
 * @copyright Copyright (c) 2019 Utah Department of Technology Services and Utah Department of Health
 */

use Udoh\Emsa\UI\AccessibleMultiselectListbox;
use Udoh\Emsa\UI\Queue\FilterFactory;
use Udoh\Emsa\Utils\DisplayUtils;

if (!class_exists('\Udoh\Emsa\Auth\Authenticator') || !\Udoh\Emsa\Auth\Authenticator::userHasPermission(\Udoh\Emsa\Auth\Authenticator::URIGHTS_ADMIN)) {
    ob_end_clean();
    header($_SERVER['SERVER_PROTOCOL'] . " 404 Not Found", TRUE, 404);
    exit;
}

	########## Session Prep ##########
	$_SESSION[EXPORT_SERVERNAME]["structure_params"]["structure"] = 9;

	/*
	 * check for session freshness since last update to session-stored filter info
	 * (filters are stored in session data, this hack gives us a way to force
	 * session data to be refreshed without forcing users to clear cookies if the data
	 * is updated mid-session, so that the current filters are used)
	 */
	$modelLastUpdated = filemtime("manage/structure_xslt.php");

	// check "freshness date"...
	if (isset($_SESSION[EXPORT_SERVERNAME]['structure_model_fresh'][$_SESSION[EXPORT_SERVERNAME]["structure_params"]["structure"]])) {
		if ($_SESSION[EXPORT_SERVERNAME]['structure_model_fresh'][$_SESSION[EXPORT_SERVERNAME]["structure_params"]["structure"]] < $modelLastUpdated) {
			// old model data; unset structure_params & set a new "freshness date"...
			unset($_SESSION[EXPORT_SERVERNAME]["structure_params"][$_SESSION[EXPORT_SERVERNAME]["structure_params"]["structure"]]);
			$_SESSION[EXPORT_SERVERNAME]['structure_model_fresh'][$_SESSION[EXPORT_SERVERNAME]["structure_params"]["structure"]] = time();
		}
	} else {
		// hack for sessions set before "freshness date" implemented
		unset($_SESSION[EXPORT_SERVERNAME]["structure_params"][$_SESSION[EXPORT_SERVERNAME]["structure_params"]["structure"]]);
		$_SESSION[EXPORT_SERVERNAME]['structure_model_fresh'][$_SESSION[EXPORT_SERVERNAME]["structure_params"]["structure"]] = time();
	}


	// define filters
	$_SESSION[EXPORT_SERVERNAME]["structure_params"][$_SESSION[EXPORT_SERVERNAME]["structure_params"]["structure"]]['filter_cols'] = array(
		"lab" => array("colname" => "x.structure_labs_id", "label" => "Reporter", "filter" => TRUE, "textsearch" => FALSE, "filterlookup" => TRUE, "has_id" => TRUE, "lookupqry" => sprintf("SELECT sl.id AS value, sl.ui_name AS label FROM %sstructure_labs sl WHERE sl.alias_for < 1 ORDER BY sl.ui_name;", $emsaDbSchemaPrefix), "filtercolname" => "structure_labs_id"),
		"hl7_version" => array("colname" => "x.message_version", "label" => "Message Version", "display" => TRUE, "filter" => TRUE, "textsearch" => FALSE, "filterlookup" => TRUE, "has_id" => FALSE, "lookupqry" => "SELECT DISTINCT message_version AS value, message_version AS label FROM ".$emsaDbSchemaPrefix."structure_xslt ORDER BY message_version;", "filtercolname" => "message_version"),
		"hl7_path" => array("colname" => "x.xslt", "label" => "XSLT Document", "display" => TRUE, "filter" => FALSE, "textsearch" => TRUE)
	);


	/*
	 * Search/Filter Prep
	 *
	 * this must happen after setting structure defaults, otherwise condition can occur where setting query params can
	 * fool the sysetm into thinking default structure data exists when it doesn't in cases of a linked query
	 */
	// pre-build our structure-specific search data...
	if (isset($_GET['q'])) {
		if (!empty($_GET['q'])) {
			$_SESSION[EXPORT_SERVERNAME]["structure_params"][$_SESSION[EXPORT_SERVERNAME]["structure_params"]["structure"]]["q_display"] = trim(filter_input(INPUT_GET, 'q', FILTER_SANITIZE_STRING));
			$_SESSION[EXPORT_SERVERNAME]["structure_params"][$_SESSION[EXPORT_SERVERNAME]["structure_params"]["structure"]]["q_sql"] = trim(filter_input(INPUT_GET, 'q', FILTER_DEFAULT, FILTER_FLAG_STRIP_LOW|FILTER_FLAG_STRIP_HIGH));
			if (!isset($_GET['f'])) {
				// search query found, but no filters selected
				// if any filters were previously SESSIONized, they've been deselected via the UI, so we'll clear them...
				unset($_SESSION[EXPORT_SERVERNAME]["structure_params"][$_SESSION[EXPORT_SERVERNAME]["structure_params"]["structure"]]["filters"]);
			}
		} else {
			// search field was empty/defaulted, so we'll destroy the saved search params...
			$_SESSION[EXPORT_SERVERNAME]["structure_params"][$_SESSION[EXPORT_SERVERNAME]["structure_params"]["structure"]]["q_display"] = null;
			$_SESSION[EXPORT_SERVERNAME]["structure_params"][$_SESSION[EXPORT_SERVERNAME]["structure_params"]["structure"]]["q_sql"] = null;
			// not only was search blank, but no filters selected, so clear them as well...
			unset($_SESSION[EXPORT_SERVERNAME]["structure_params"][$_SESSION[EXPORT_SERVERNAME]["structure_params"]["structure"]]["filters"]);
		}
	} else {
        if (!isset($_SESSION[EXPORT_SERVERNAME]["structure_params"][$_SESSION[EXPORT_SERVERNAME]["structure_params"]["structure"]]["q_display"])) {
            $_SESSION[EXPORT_SERVERNAME]["structure_params"][$_SESSION[EXPORT_SERVERNAME]["structure_params"]["structure"]]["q_display"] = null;
        }
    }

	// update SESSIONized filters or destroy them if no filters are selected...
	if (isset($_GET['f'])) {
		if (is_array($_GET['f'])) {
			$_SESSION[EXPORT_SERVERNAME]["structure_params"][$_SESSION[EXPORT_SERVERNAME]["structure_params"]["structure"]]["filters"] = filter_input(INPUT_GET, 'f', FILTER_DEFAULT, FILTER_FORCE_ARRAY);
		}
	}


	// sanitize input data...
	unset($cleanChildXMLStructureParams);

    $cleanChildXMLStructureParams['delete_id'] = (int) filter_input(INPUT_GET, 'delete_id', FILTER_SANITIZE_NUMBER_INT);
    $cleanChildXMLStructureParams['edit_id'] = (int) filter_input(INPUT_POST, 'edit_id', FILTER_SANITIZE_NUMBER_INT);
    $cleanChildXMLStructureParams['add_flag'] = (int) filter_input(INPUT_POST, 'add_flag', FILTER_SANITIZE_NUMBER_INT);

    $cleanChildXMLStructureParams['new']['lab_id'] = (int) filter_input(INPUT_POST, 'new_lab_id', FILTER_SANITIZE_NUMBER_INT);
    $cleanChildXMLStructureParams['new']['message_version'] = trim((string) filter_input(INPUT_POST, 'new_message_version', FILTER_SANITIZE_STRING));
    $cleanChildXMLStructureParams['new']['xslt'] = (string) filter_input(INPUT_POST, 'new_xslt', FILTER_UNSAFE_RAW);

    $cleanChildXMLStructureParams['edit']['lab_id'] = (int) filter_input(INPUT_POST, 'edit_lab_id', FILTER_SANITIZE_NUMBER_INT);
    $cleanChildXMLStructureParams['edit']['message_version'] = trim((string) filter_input(INPUT_POST, 'edit_message_version', FILTER_SANITIZE_STRING));
    $cleanChildXMLStructureParams['edit']['xslt'] = (string) filter_input(INPUT_POST, 'edit_xslt', FILTER_UNSAFE_RAW);
?>

<script>
	$(function() {
		$("#addnew_button").button({
            icon: "ui-icon-elrplus"
        }).on("click", function() {
			$("#addnew_form").show();
			$(".import_error").hide();
			$("#new_element").trigger("focus");
			$(this).hide();
		});

		$("#addnew_cancel").button({
			icon: "ui-icon-elrcancel"
		}).on("click", function() {
			$("#addnew_form").hide();
			$("#addnew_button").show();
		});

		$("#new_savelab").button({
            icon: "ui-icon-elrsave"
        });

		$(".edit_lab").button({
            icon: "ui-icon-elrpencil",
            showLabel: false
        }).on("click", function(e) {
			e.preventDefault();
			let jsonObj = JSON.parse($(this).val());

			if (jsonObj.element_id) {
				$("#edit_id").val(jsonObj.element_id);
				$("#edit_lab_id").val(jsonObj.lab_id);
				$("#edit_message_version").val(jsonObj.version);
				$("#edit_xslt").val(atob(jsonObj.xslt));

                $("#edit_lab_dialog")
                    .dialog('option', 'buttons', {
                        "Save Changes": function () {
                            $(this).dialog("close");
                            $("#edit_modal_form").trigger("submit");
                        },
                        "Cancel": function () {
                            $(this).dialog("close");
                        }
                    })
                    .dialog("open");
			} else {
				return false;
			}

		}).next().button({
            icon: "ui-icon-elrclose",
            showLabel: false
        }).on("click", function(e) {
			e.preventDefault();
			let deleteAction = "<?php echo $webappBaseUrl; ?>?selected_page=6&submenu=4&cat=2&subcat=9&delete_id="+$(this).val();

            $("#confirm_delete_dialog")
                .dialog('option', 'buttons', {
                    "Delete": function () {
                        window.location.href = deleteAction;
                    },
                    "Cancel": function () {
                        $(this).dialog("close");
                    }
                })
                .dialog("open");

		}).parent().controlgroup();

		$("#confirm_delete_dialog").dialog({
			autoOpen: false,
			modal: true,
			draggable: false,
			resizable: false
		});

		$("#edit_lab_dialog").dialog({
			autoOpen: false,
			width: 800,
			modal: true
		});

		let toggleFilters = $("#toggle_filters");

		toggleFilters.button({
            icon: "ui-icon-triangle-1-n",
            iconPosition: "end"
        }).on("click", function() {
			$(".vocab_filter").toggle("blind");
			let objIcon = $(this).button("option", "icon");
			if (objIcon === "ui-icon-triangle-1-s") {
				$(this).button("option", "icon", "ui-icon-triangle-1-n");
				$(this).button("option", "iconPosition", "end");
				$(this).button("option", "label", "Hide Filters");
				$("#addnew_form").hide();
				$("#addnew_button").show();
			} else {
				$(this).button("option", "icon", "ui-icon-triangle-1-s");
				$(this).button("option", "iconPosition", "end");
				$(this).button("option", "label", "Show Filters");
			}
		});

		$(".vocab_filter").hide();
		toggleFilters.button("option", "icon", "ui-icon-triangle-1-s");
		toggleFilters.button("option", "iconPosition", "end");
		toggleFilters.button("option", "label", "Show Filters");

		$("#clear_filters").button({
            icon: "ui-icon-elrcancel"
        }).on("click", function() {
            let searchForm = $("#search_form");
            let msListBoxes = $("ul.vocab_filter_checklist[role=listbox]");
            $(".pseudo_select").removeAttr("checked");
			$(".pseudo_select_label").removeClass("pseudo_select_on");
			msListBoxes.removeAttr("aria-activedescendant");
            msListBoxes.children().removeClass("multiselect-focused");
            msListBoxes.children().attr("aria-selected", "false");
            msListBoxes.find("input[type=checkbox]").prop("checked", false);
			searchForm[0].reset();
            $("#q").val("").trigger("blur");
            searchForm.trigger("submit");
		});

		$("#q_go").button({
			icon: "ui-icon-elrsearch"
		}).on("click", function(){
			$("#search_form").trigger("submit");
		});

		$("#apply_filters").button({
			icon: "ui-icon-elroptions"
		}).on("click", function(){
			$("#search_form").trigger("submit");
		});

	});
</script>

<?php

	if ($cleanChildXMLStructureParams['edit_id'] > 0) {
		// Edit an existing XSLT entry, specified by ID
		// check to see if passed a valid row id...
		if (!empty($cleanChildXMLStructureParams['edit']['lab_id']) && !empty($cleanChildXMLStructureParams['edit']['message_version']) && !empty($cleanChildXMLStructureParams['edit']['xslt'])) {
		    if (Udoh\Emsa\Management\ReporterStructureUtils::xsltExists($adminDbConn, $cleanChildXMLStructureParams['edit_id'])) {
			    if (Udoh\Emsa\Management\ReporterStructureUtils::updateXSLT($adminDbConn, $cleanChildXMLStructureParams['edit_id'], $cleanChildXMLStructureParams['edit']['lab_id'], $cleanChildXMLStructureParams['edit']['message_version'], $cleanChildXMLStructureParams['edit']['xslt'])) {
					Udoh\Emsa\Utils\DisplayUtils::drawHighlight("XSL Transformation successfully updated!", "ui-icon-elrsuccess");
				} else {
					Udoh\Emsa\Utils\DisplayUtils::drawError("Unable to save changes to XSL Transformation.");
				}
			} else {
                Udoh\Emsa\Utils\DisplayUtils::drawError("Unable to save changes to XSL Transformation -- No XSL Transformation exists for the specified reporter/message version.");
			}
		} else {
			DisplayUtils::drawError("Not all required fields were entered!  Please specify a Reporting Facility, Message Version, and XSL Transformation and try again.");
		}
	} elseif ($cleanChildXMLStructureParams['delete_id'] > 0) {
		// Delete an individual XSLT entry, specified by ID
		// check to see if passed a valid row id...
        if (Udoh\Emsa\Management\ReporterStructureUtils::xsltExists($adminDbConn, $cleanChildXMLStructureParams['delete_id'])) {
			// commit the delete...
            if (Udoh\Emsa\Management\ReporterStructureUtils::deleteXSLT($adminDbConn, $cleanChildXMLStructureParams['delete_id'])) {
				Udoh\Emsa\Utils\DisplayUtils::drawHighlight("XSL Transformation successfully deleted!", "ui-icon-elrsuccess");
			} else {
                Udoh\Emsa\Utils\DisplayUtils::drawError('Unable to delete XSL Transformation.');
			}
		} else {
            Udoh\Emsa\Utils\DisplayUtils::drawError("Unable to delete XSL Transformation:  record not found.");
		}
	} elseif ($cleanChildXMLStructureParams['add_flag'] === 1) {
		// Add a new XSLT version
		if (isset($cleanChildXMLStructureParams['new']['lab_id']) && isset($cleanChildXMLStructureParams['new']['message_version']) && isset($cleanChildXMLStructureParams['new']['xslt'])) {
		    if (Udoh\Emsa\Management\ReporterStructureUtils::addXSLT($adminDbConn, $cleanChildXMLStructureParams['new']['lab_id'], $cleanChildXMLStructureParams['new']['message_version'], $cleanChildXMLStructureParams['new']['xslt'])) {
                Udoh\Emsa\Utils\DisplayUtils::drawHighlight("New XSL Transformation added successfully!", "ui-icon-elrsuccess");
            } else {
                Udoh\Emsa\Utils\DisplayUtils::drawError("Could not add new XSL Transformation.");
            }
		} else {
			Udoh\Emsa\Utils\DisplayUtils::drawError("Not all required fields were entered!  Please specify a Reporting Facility, Message Version, and XSL Transformation and try again.");
		}
	}

?>

<h1 class="elrhdg"><span class="ui-icon ui-icon-header ui-icon-emsaxmldoc"></span>Reporter to Master XSLT-Based Mapping</h1>

<form name="search_form" id="search_form" method="GET" action="<?php echo $webappBaseUrl; ?>">

<div class="emsa_search_controls ui-tabs ui-widget">
    <label for="q" class="emsa_form_heading">Search: </label>
    <input type="text" name="q" id="q" placeholder="Enter search terms..." class="vocab_query ui-corner-all" value="<?php Udoh\Emsa\Utils\DisplayUtils::xEcho((string) $_SESSION[EXPORT_SERVERNAME]["structure_params"][$_SESSION[EXPORT_SERVERNAME]["structure_params"]["structure"]]["q_display"], "UTF-8", false); ?>">
	<button name="q_go" id="q_go">Search</button>
	<button type="button" name="clear_filters" id="clear_filters" title="Clear all search terms/filters">Clear</button>
	<button type="button" name="toggle_filters" id="toggle_filters" title="Show/hide filters">Hide Filters</button>
    <button type="button" id="addnew_button" title="Add a new XSL Transformation for a reporter and/or message version">Add New XSLT</button>
</div>

<?php
	############### If filters applied, display which ones ###############
	if (isset($_SESSION[EXPORT_SERVERNAME]["structure_params"][$_SESSION[EXPORT_SERVERNAME]["structure_params"]["structure"]]["filters"])) {
?>
<div class="vocab_search ui-widget ui-widget-content ui-state-highlight ui-corner-all" style="padding: 5px;">
	<span class="ui-icon ui-icon-elroptions" style="float: left; margin-right: .3em;"></span><p style="margin-left: 20px;">Filtering by
<?php
		$active_filters = 0;
		foreach ($_SESSION[EXPORT_SERVERNAME]["structure_params"][$_SESSION[EXPORT_SERVERNAME]["structure_params"]["structure"]]["filters"] as $sqlfiltercol => $sqlfiltervals) {
			if ($active_filters == 0) {
				echo "<strong>" . $_SESSION[EXPORT_SERVERNAME]["structure_params"][$_SESSION[EXPORT_SERVERNAME]["structure_params"]["structure"]]["filter_cols"][$sqlfiltercol]['label'] . "</strong>";
			} else {
				echo ", <strong>" . $_SESSION[EXPORT_SERVERNAME]["structure_params"][$_SESSION[EXPORT_SERVERNAME]["structure_params"]["structure"]]["filter_cols"][$sqlfiltercol]['label'] . "</strong>";			}
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
	############### Draw filter form elements based on 'filter_cols' array ###############
	foreach ($_SESSION[EXPORT_SERVERNAME]["structure_params"][$_SESSION[EXPORT_SERVERNAME]["structure_params"]["structure"]]["filter_cols"] as $filtercol => $filtercolname) {
		if ($filtercolname['filter']) {
			if ($filtercolname['filterlookup']) {
				$filterQuery =  $filtercolname['lookupqry'];
			} else {
				$filterQuery = sprintf("SELECT DISTINCT %s AS value, %s AS label FROM %s ORDER BY %s ASC;", $filtercol, $filtercol, $_SESSION[EXPORT_SERVERNAME]["structure_params"][$_SESSION[EXPORT_SERVERNAME]["structure_params"]["structure"]]["query_data"]["table_name"], $filtercol);
			}
			
			(new AccessibleMultiselectListbox(FilterFactory::getListFromQuery($adminDbConn, $filterQuery), $_SESSION[EXPORT_SERVERNAME]["structure_params"][$_SESSION[EXPORT_SERVERNAME]["structure_params"]["structure"]]['filters'][$filtercol] ?? null))
                ->render($filtercolname['label'], 'f[' . $filtercol . ']', false, false, $filtercol, null, $filtercolname['filterlookup'] ?? null);
		}
	}
?>
	<br><br><button name="apply_filters" id="apply_filters" style="clear: both; float: left; margin: 5px;">Apply Filters</button>
</div>

<input type="hidden" name="selected_page" value="<?php echo $navSelectedPage; ?>">
<input type="hidden" name="submenu" value="<?php echo $navSubmenu; ?>">
<input type="hidden" name="cat" value="<?php echo $navCat; ?>">
<input type="hidden" name="subcat" value="<?php echo $navSubcat; ?>">

</form>

<div id="addnew_form" class="addnew_lab ui-widget ui-widget-content ui-corner-all">
	<div style="clear: both;"><span class="emsa_form_heading">Add New XSL Transformation:</span><br><br></div>
	<form id="new_lab_form" method="POST" action="<?php echo $webappBaseUrl; ?>?selected_page=<?php echo intval($navSelectedPage); ?>&submenu=<?php echo intval($navSubmenu); ?>&cat=<?php echo intval($navCat); ?>&subcat=<?php echo intval($navSubcat); ?>">
		<label class="vocab_search_form2" for="new_lab_id">Reporting Facility:</label>
			<select class="ui-corner-all" name="new_lab_id" id="new_lab_id">
				<option value="0" selected>--</option>
			<?php
				// get list of labs for menu
				$addnew_sql = sprintf("SELECT DISTINCT id, ui_name FROM %sstructure_labs WHERE alias_for < 1 ORDER BY ui_name;", $emsaDbSchemaPrefix);
				$addnew_rs = @pg_query($host_pa, $addnew_sql) or DisplayUtils::drawError("Unable to retrieve list of labs.", true);
				while ($addnew_row = pg_fetch_object($addnew_rs)) {
					printf("<option value=\"%d\">%s</option>", intval($addnew_row->id), htmlentities($addnew_row->ui_name));
				}
				@pg_free_result($addnew_rs);
			?>
			</select>
		<label class="vocab_search_form2" for="new_message_version">Message Version:</label><input class="ui-corner-all" type="text" name="new_message_version" id="new_message_version" />
        <br><br><label class="vocab_search_form2" for="new_xslt">XSL Transformation:</label><br><textarea style="background-color: lightcyan; white-space: pre; width: 50em; height: 10em; font-family: Consolas, monospace;" class="ui-corner-all" name="new_xslt" id="new_xslt"></textarea>
		<input type="hidden" name="lab_id" value="" />
		<input type="hidden" name="version" value="" />
		<input type="hidden" name="add_flag" value="1" />
		<br><br><button type="submit" name="new_savelab" id="new_savelab">Save New XSL Transformation</button>
		<button type="button" id="addnew_cancel">Cancel</button>
	</form>
</div>

<div class="lab_results_container ui-widget ui-corner-all">
	<table id="labResults">
		<thead>
			<tr>
				<th>Actions</th>
				<th>Reporter</th>
				<th>Message Version</th>
				<th>XSL Transformation</th>
			</tr>
		</thead>
		<tbody>

<?php

	$where_clause = '';

	$where_count = 0;
	// handle any search terms or filters...
	if (!empty($_SESSION[EXPORT_SERVERNAME]["structure_params"][$_SESSION[EXPORT_SERVERNAME]["structure_params"]["structure"]]["q_sql"])) {
		// we've got some query params
		$where_count = 1;
		$where_clause .= " WHERE (";

		foreach ($_SESSION[EXPORT_SERVERNAME]["structure_params"][$_SESSION[EXPORT_SERVERNAME]["structure_params"]["structure"]]["filter_cols"] as $searchcol => $searchcoldata) {
			if ($searchcoldata['textsearch']) {
				if ($where_count > 1) {
					$where_clause .= " OR ";
				}
				$where_clause .= sprintf("%s ILIKE '%%%s%%'", $searchcoldata['colname'], pg_escape_string($_SESSION[EXPORT_SERVERNAME]["structure_params"][$_SESSION[EXPORT_SERVERNAME]["structure_params"]["structure"]]["q_sql"]));
				$where_count++;
			}
		}

		$where_clause .= ")";
	}

	if (isset($_SESSION[EXPORT_SERVERNAME]["structure_params"][$_SESSION[EXPORT_SERVERNAME]["structure_params"]["structure"]]["filters"])) {
		// need to apply filters
		$filter_count = 0;
		if ($where_count == 0) {
			// not already a WHERE clause for search terms
			$where_clause .= " WHERE";
		}

		foreach ($_SESSION[EXPORT_SERVERNAME]["structure_params"][$_SESSION[EXPORT_SERVERNAME]["structure_params"]["structure"]]["filters"] as $sqlfiltercol => $sqlfiltervals) {
			unset($filter_temp);
			$nullfilter = FALSE;
			if (($filter_count > 0) || ($where_count > 1)) {
				$where_clause .= " AND (";
			} else {
				$where_clause .= " (";
			}
			foreach ($sqlfiltervals as $sqlfilterval) {
				if (is_null($sqlfilterval) || (strlen(trim($sqlfilterval)) == 0)) {
					$nullfilter = TRUE;
				} else {
					$filter_temp[] = "'" . pg_escape_string($sqlfilterval) . "'";
				}
			}

			if ($nullfilter && is_array($filter_temp)) {
				$where_clause .= $_SESSION[EXPORT_SERVERNAME]["structure_params"][$_SESSION[EXPORT_SERVERNAME]["structure_params"]["structure"]]["filter_cols"][$sqlfiltercol]["filtercolname"] . " IN (" . implode(",", $filter_temp) . ") OR " . $_SESSION[EXPORT_SERVERNAME]["structure_params"][$_SESSION[EXPORT_SERVERNAME]["structure_params"]["structure"]]["filter_cols"][$sqlfiltercol]["filtercolname"] . " IS NULL OR " . $_SESSION[EXPORT_SERVERNAME]["structure_params"][$_SESSION[EXPORT_SERVERNAME]["structure_params"]["structure"]]["filter_cols"][$sqlfiltercol]["filtercolname"] . " = ''";
			} elseif (is_array($filter_temp)) {
				$where_clause .= $_SESSION[EXPORT_SERVERNAME]["structure_params"][$_SESSION[EXPORT_SERVERNAME]["structure_params"]["structure"]]["filter_cols"][$sqlfiltercol]["filtercolname"] . " IN (" . implode(",", $filter_temp) . ")";
			} elseif ($nullfilter) {
				$where_clause .= $_SESSION[EXPORT_SERVERNAME]["structure_params"][$_SESSION[EXPORT_SERVERNAME]["structure_params"]["structure"]]["filter_cols"][$sqlfiltercol]["filtercolname"] . " IS NULL OR " . $_SESSION[EXPORT_SERVERNAME]["structure_params"][$_SESSION[EXPORT_SERVERNAME]["structure_params"]["structure"]]["filter_cols"][$sqlfiltercol]["filtercolname"] . " = ''";
			}

			$where_clause .= ")";
			$filter_count++;
		}
	}

	$hl7_qry = 'SELECT x.id AS id, x.message_version AS message_version, x.xslt AS xslt, l.id AS lab_id, l.ui_name AS lab_name
		FROM '.$emsaDbSchemaPrefix.'structure_xslt x 
		INNER JOIN '.$emsaDbSchemaPrefix.'structure_labs l ON (x.structure_labs_id = l.id)
		'.$where_clause.' 
		ORDER BY l.ui_name, x.message_version';
	$hl7_rs = @pg_query($host_pa, $hl7_qry) or DisplayUtils::drawError("Could not connect to database:");

	while ($hl7_row = @pg_fetch_object($hl7_rs)) {
		echo '<tr>';
		echo '<td class="action_col" style="vertical-align: top;">';

		unset($edit_lab_params);
		$edit_lab_params = array(
			"element_id" => intval($hl7_row->id),
			"lab_id" => intval($hl7_row->lab_id),
			"version" => htmlentities($hl7_row->message_version, ENT_QUOTES, "UTF-8"),
			"xslt" => base64_encode($hl7_row->xslt)
		);

		printf("<button class=\"edit_lab\" type=\"button\" value='%s' title=\"Edit this record\">Edit</button>", json_encode($edit_lab_params));
		printf("<button class=\"delete_lab\" type=\"button\" value=\"%s\" title=\"Delete this record\">Delete</button>", $hl7_row->id);

		echo '</td>';
		echo '<td style="vertical-align: top;">' . DisplayUtils::xSafe($hl7_row->lab_name, "UTF-8", false) . '</td>';
		echo '<td style="white-space: nowrap; vertical-align: top;">' . DisplayUtils::xSafe($hl7_row->message_version, "UTF-8", false) . '</td>';
		echo '<td style="width: 70%"><label class="sr-only" for="xslt_body_' . (int) $hl7_row->id . '">XSL Transformation for ' . DisplayUtils::xSafe($hl7_row->lab_name, "UTF-8", false) . ' version ' . DisplayUtils::xSafe($hl7_row->message_version, "UTF-8", false) . '</label>';
		echo '<textarea id="xslt_body_' . (int) $hl7_row->id . '" class="ui-corner-all" readonly style="width: 99%; height: 30em; white-space: pre; font-family: Consolas, monospace;">' . DisplayUtils::xSafe(DisplayUtils::formatXml($hl7_row->xslt), "UTF-8", false) . '</textarea></td>';
		echo '</tr>';
	}

	@pg_free_result($hl7_rs);

?>

		</tbody>
	</table>

</div>

<div id="confirm_delete_dialog" title="Delete this XSLT?">
	<p><span class="ui-icon ui-icon-elrerror" style="float:left; margin:0 7px 50px 0;"></span>This XSL Transformation will be permanently deleted and cannot be recovered. Are you sure?</p>
</div>

<div id="edit_lab_dialog" title="Edit XSLT">
	<form id="edit_modal_form" method="POST" action="<?php echo $webappBaseUrl; ?>?selected_page=<?php echo intval($navSelectedPage); ?>&submenu=<?php echo intval($navSubmenu); ?>&cat=<?php echo intval($navCat); ?>&subcat=<?php echo intval($navSubcat); ?>">
		<label for="edit_lab_id">Reporting Facility:</label><br>
		<select class="ui-corner-all" style="margin: 0;" name="edit_lab_id" id="edit_lab_id">
			<option value="0" selected>--</option>
		<?php
			// get list of labs for menu
			$editlabs_sql = sprintf("SELECT DISTINCT id, ui_name FROM %sstructure_labs WHERE alias_for < 1 ORDER BY ui_name;", $emsaDbSchemaPrefix);
			$editlabs_rs = @pg_query($host_pa, $editlabs_sql) or DisplayUtils::drawError("Unable to retrieve list of labs.", true);
			while ($editlabs_row = pg_fetch_object($editlabs_rs)) {
				printf("<option value=\"%d\">%s</option>", intval($editlabs_row->id), htmlentities($editlabs_row->ui_name));
			}
			pg_free_result($editlabs_rs);
		?>
		</select><br><br>
		<label for="edit_message_version">Message Version:</label><br><input class="ui-corner-all" type="text" name="edit_message_version" id="edit_message_version" /><br><br>
        <label for="edit_xslt">XSL Transformation:</label><br><textarea style="white-space: pre; background-color: lightcyan; width: 100%; height: 10em; font-family: Consolas, monospace;" class="ui-corner-all" name="edit_xslt" id="edit_xslt"></textarea><br><br>
		<input type="hidden" name="edit_id" id="edit_id" />
	</form>
</div>
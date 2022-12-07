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
use Udoh\Emsa\UI\Queue\FilterFactory;

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
	$modelLastUpdated = filemtime("manage/structure_loinc_knitting.php");
	
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
		"lab" => array("colname" => "k.lab_id", "label" => "Reporter", "filter" => TRUE, "textsearch" => FALSE, "filterlookup" => TRUE, "has_id" => TRUE, "lookupqry" => sprintf("SELECT sl.id AS value, sl.ui_name AS label FROM %sstructure_labs sl WHERE sl.alias_for < 1 ORDER BY sl.ui_name;", $emsaDbSchemaPrefix), "filtercolname" => "lab_id"),
		"loinc_code" => array("colname" => "k.loinc", "label" => "Knittable Master LOINC Code", "display" => TRUE, "filter" => FALSE, "textsearch" => TRUE)
	);
	
	
	/*
	 * Search/Filter Prep
	 * 
	 * this must happen after setting structure defaults, otherwise condition can occur where setting query params can
	 * fool the sysetm into thinking default structure data exists when it doesn't in cases of a linked query
	 */
	// pre-build our structure-specific search data...
	if (isset($_GET["q"])) {
		if ((trim($_GET["q"]) != "Enter search terms...") && (strlen(trim($_GET["q"])) > 0)) {
			$_SESSION[EXPORT_SERVERNAME]["structure_params"][$_SESSION[EXPORT_SERVERNAME]["structure_params"]["structure"]]["q_display"] = $emsaHTMLPurifier->purify(trim($_GET["q"]));
			$_SESSION[EXPORT_SERVERNAME]["structure_params"][$_SESSION[EXPORT_SERVERNAME]["structure_params"]["structure"]]["q_sql"] = pg_escape_string(trim($_GET["q"]));
			if (!isset($_GET['f'])) {
				// search query found, but no filters selected
				// if any filters were previously SESSIONized, they've been deselected via the UI, so we'll clear them...
				unset($_SESSION[EXPORT_SERVERNAME]["structure_params"][$_SESSION[EXPORT_SERVERNAME]["structure_params"]["structure"]]["filters"]);
			}
		} else {
			// search field was empty/defaulted, so we'll destroy the saved search params...
			unset($_SESSION[EXPORT_SERVERNAME]["structure_params"][$_SESSION[EXPORT_SERVERNAME]["structure_params"]["structure"]]["q_display"]);
			unset($_SESSION[EXPORT_SERVERNAME]["structure_params"][$_SESSION[EXPORT_SERVERNAME]["structure_params"]["structure"]]["q_sql"]);
			// not only was search blank, but no filters selected, so clear them as well...
			unset($_SESSION[EXPORT_SERVERNAME]["structure_params"][$_SESSION[EXPORT_SERVERNAME]["structure_params"]["structure"]]["filters"]);
		}
	}
	
	// update SESSIONized filters or destroy them if no filters are selected...
	if (isset($_GET['f'])) {
		if (is_array($_GET['f'])) {
			$_SESSION[EXPORT_SERVERNAME]["structure_params"][$_SESSION[EXPORT_SERVERNAME]["structure_params"]["structure"]]["filters"] = $_GET['f'];
		}
	}
	
	
	// sanitize input data...
	unset($cleanKnitting);
	if (isset($_REQUEST['add_flag']) && is_numeric($_REQUEST['add_flag']) && (intval(trim($_REQUEST['add_flag'])) > 0)) {
		$cleanKnitting['add_flag'] = 1;
	}
	if (isset($_REQUEST['new_lab_id']) && is_numeric($_REQUEST['new_lab_id']) && (intval(trim($_REQUEST['new_lab_id'])) > 0)) {
		$cleanKnitting['new']['lab_id'] = intval(trim($_REQUEST['new_lab_id']));
	}
	if (isset($_REQUEST['new_loinc_code']) && (strlen(trim($_REQUEST['new_loinc_code'])) > 0)) {
		$cleanKnitting['new']['loinc'] = trim($_REQUEST['new_loinc_code']);
	}
	if (isset($_REQUEST['edit_id']) && is_numeric($_REQUEST['edit_id']) && (intval(trim($_REQUEST['edit_id'])) > 0)) {
		$cleanKnitting['edit_id'] = intval(trim($_REQUEST['edit_id']));
	}
	if (isset($_REQUEST['edit_lab_id']) && is_numeric($_REQUEST['edit_lab_id']) && (intval(trim($_REQUEST['edit_lab_id'])) > 0)) {
		$cleanKnitting['edit']['lab_id'] = intval(trim($_REQUEST['edit_lab_id']));
	}
	if (isset($_REQUEST['edit_loinc_code']) && (strlen(trim($_REQUEST['edit_loinc_code'])) > 0)) {
		$cleanKnitting['edit']['loinc'] = trim($_REQUEST['edit_loinc_code']);
	}
	if (isset($_REQUEST['delete_id']) && is_numeric($_REQUEST['delete_id']) && (intval(trim($_REQUEST['delete_id'])) > 0)) {
		$cleanKnitting['delete_id'] = intval(trim($_REQUEST['delete_id']));
	}
?>

<script>
	$(function() {
		$("#addnew_button").button({
            icon: "ui-icon-elrplus"
        }).on("click", function() {
			$("#addnew_form").show();
			$(".import_error").hide();
			$("#new_lab_id").trigger("focus");
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
            icon: "ui-icon-elrpencil"
        }).next().button({
            icon: "ui-icon-elrclose"
        }).parent().controlgroup();
		
		$("#confirm_delete_dialog").dialog({
			autoOpen: false,
			modal: true,
			draggable: false,
			resizable: false
		});
		
		$(".delete_lab").on("click", function(e) {
			e.preventDefault();
			var deleteAction = "<?php echo $webappBaseUrl; ?>?selected_page=6&submenu=4&cat=9&delete_id="+$(this).val();


			$("#confirm_delete_dialog").dialog('option', 'buttons', {
					"Delete" : function() {
						window.location.href = deleteAction;
						},
					"Cancel" : function() {
						$(this).dialog("close");
						}
					});

			$("#confirm_delete_dialog").dialog("open");

		});
		
		$("#edit_lab_dialog").dialog({
			autoOpen: false,
			width: 800,
			modal: true
		});
		
		$(".edit_lab").on("click", function(e) {
			e.preventDefault();
			var jsonObj = JSON.parse($(this).val());
			
			if (jsonObj.element_id) {
				$("#edit_id").val(jsonObj.element_id);
				$("#edit_lab_id").val(jsonObj.lab_id);
				$("#edit_loinc_code").val(jsonObj.loinc);
				
				$("#edit_lab_dialog").dialog('option', 'buttons', {
						"Save Changes" : function() {
							$(this).dialog("close");
							$("#edit_modal_form").trigger("submit");
							},
						"Cancel" : function() {
							$(this).dialog("close");
							}
						});

				$("#edit_lab_dialog").dialog("open");
			} else {
				return false;
			}
			
		});
		
		$("#toggle_filters").button({
            icon: "ui-icon-triangle-1-n",
            iconPosition: "end"
        }).on("click", function() {
			$(".vocab_filter").toggle("blind");
			var objIcon = $(this).button("option", "icon");
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
		$("#toggle_filters").button("option", "icon", "ui-icon-triangle-1-s");
		$("#toggle_filters").button("option", "iconPosition", "end");
		$("#toggle_filters").button("option", "label", "Show Filters");
		
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
		
		$("#q").addClass("search_empty").val("Enter search terms...").on("click", function() {
			var search_val = $("#q").val().trim();
			if (search_val === "Enter search terms...") {
				$(this).removeClass("search_empty").val("");
			}
		}).on("blur", function() {
			var search_val_ln = $("#q").val().trim().length;
			if (search_val_ln === 0) {
				$("#q").addClass("search_empty").val("Enter search terms...");
			}
		});
		
		<?php
			if (isset($_SESSION[EXPORT_SERVERNAME]["structure_params"][$_SESSION[EXPORT_SERVERNAME]["structure_params"]["structure"]]["q_display"])) {
				if ($_SESSION[EXPORT_SERVERNAME]["structure_params"][$_SESSION[EXPORT_SERVERNAME]["structure_params"]["structure"]]["q_display"] != "Enter search terms...") {
		?>
		$("#q").removeClass("search_empty").val("<?php echo $_SESSION[EXPORT_SERVERNAME]["structure_params"][$_SESSION[EXPORT_SERVERNAME]["structure_params"]["structure"]]["q_display"]; ?>");
		<?php
				}
			}
		?>
	});
</script>

<?php
	
	/* @var $emsaDbFactory \Udoh\Emsa\PDOFactory\PostgreSQL */
	
	if (isset($cleanKnitting['edit_id'])) {
		if (isset($cleanKnitting['edit']['lab_id']) && isset($cleanKnitting['edit']['loinc'])) {
			try {
				$editLoincSql = "UPDATE structure_knittable_loincs
					SET lab_id = :labId, loinc = :loincCode
					WHERE id = :recordId;";
				$editLoincStmt = $emsaDbFactory->getConnection()->prepare($editLoincSql);
				$editLoincStmt->bindValue(':labId', $cleanKnitting['edit']['lab_id'], PDO::PARAM_INT);
				$editLoincStmt->bindValue(':loincCode', $cleanKnitting['edit']['loinc'], PDO::PARAM_STR);
				$editLoincStmt->bindValue(':recordId', $cleanKnitting['edit_id'], PDO::PARAM_INT);
				
				if ($editLoincStmt->execute()) {
					\Udoh\Emsa\Utils\DisplayUtils::drawHighlight('Knittable LOINC updated successfully!');
				} else {
					throw new Exception('Unexpected error occurred');
				}
			} catch (Throwable $e) {
				\Udoh\Emsa\Utils\DisplayUtils::drawError('Could not update existing knittable LOINC:  '.$e->getMessage());
				\Udoh\Emsa\Utils\ExceptionUtils::logException($e);
			}
		} else {
			\Udoh\Emsa\Utils\DisplayUtils::drawError('Could not update existing knittable LOINC:  missing required fields.');
		}
	} elseif (isset($cleanKnitting['delete_id'])) {
		try {
			$deleteLoincSql = "DELETE FROM structure_knittable_loincs
				WHERE id = :recordId;";
			$deleteLoincStmt = $emsaDbFactory->getConnection()->prepare($deleteLoincSql);
			$deleteLoincStmt->bindValue(':recordId', $cleanKnitting['delete_id'], PDO::PARAM_INT);

			if ($deleteLoincStmt->execute()) {
				\Udoh\Emsa\Utils\DisplayUtils::drawHighlight('Knittable LOINC removed!');
			} else {
				throw new Exception('Unexpected error occurred');
			}
		} catch (Throwable $e) {
			\Udoh\Emsa\Utils\DisplayUtils::drawError('Could not delete knittable LOINC:  '.$e->getMessage());
			\Udoh\Emsa\Utils\ExceptionUtils::logException($e);
		}
	} elseif (isset($_REQUEST['add_flag'])) {
		if (isset($cleanKnitting['new']['lab_id']) && isset($cleanKnitting['new']['loinc'])) {
			try {
				$addLoincSql = "INSERT INTO structure_knittable_loincs (lab_id, loinc)
					VALUES (:labId, :loincCode);";
				$addLoincStmt = $emsaDbFactory->getConnection()->prepare($addLoincSql);
				$addLoincStmt->bindValue(':labId', $cleanKnitting['new']['lab_id'], PDO::PARAM_INT);
				$addLoincStmt->bindValue(':loincCode', $cleanKnitting['new']['loinc'], PDO::PARAM_STR);
				
				if ($addLoincStmt->execute()) {
					\Udoh\Emsa\Utils\DisplayUtils::drawHighlight('New knittable LOINC added successfully!');
				} else {
					throw new Exception('Unexpected error occurred');
				}
			} catch (Throwable $e) {
				\Udoh\Emsa\Utils\DisplayUtils::drawError('Could not add new knittable LOINC:  '.$e->getMessage());
				\Udoh\Emsa\Utils\ExceptionUtils::logException($e);
			}
		} else {
			\Udoh\Emsa\Utils\DisplayUtils::drawError('Could not add new knittable LOINC:  missing required fields.');
		}
	}
	
?>

<h1 class="elrhdg"><span class="ui-icon ui-icon-header ui-icon-emsasettings"></span>LOINC-Based Lab Knitting</h1>

<form name="search_form" id="search_form" method="GET" action="<?php echo $webappBaseUrl; ?>">

<div class="emsa_search_controls ui-tabs ui-widget">
	<label for="q" class="emsa_form_heading">Search:</label>
    <input type="text" name="q" id="q" class="vocab_query ui-corner-all">
	<button name="q_go" id="q_go">Search</button>
	<button type="button" name="clear_filters" id="clear_filters" title="Clear all search terms/filters">Reset</button>
	<button type="button" name="toggle_filters" id="toggle_filters" title="Show/hide filters">Hide Filters</button>
	<button type="button" id="addnew_button" title="Manually add a new HL7 message element">Add Knittable LOINC Code</button>
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
				$filterQuery = sprintf("SELECT DISTINCT %s AS label, %s AS value  FROM %s ORDER BY %s ASC;", $filtercol, $filtercol, $_SESSION[EXPORT_SERVERNAME]["structure_params"][$_SESSION[EXPORT_SERVERNAME]["structure_params"]["structure"]]["query_data"]["table_name"], $filtercol);
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

</form>

<div id="addnew_form" class="addnew_lab ui-widget ui-widget-content ui-corner-all">
	<div style="clear: both;"><span class="emsa_form_heading">Add New Knittable LOINC Code:</span><br><br></div>
	<form id="new_lab_form" method="POST" action="<?php echo $webappBaseUrl; ?>?selected_page=<?php echo intval($navSelectedPage); ?>&submenu=<?php echo intval($navSubmenu); ?>&cat=<?php echo intval($navCat); ?>">
		<label class="vocab_search_form2" for="new_lab_id">Reporting Facility:</label>
			<select class="ui-corner-all" name="new_lab_id" id="new_lab_id">
				<option value="0" selected>--</option>
			<?php
				// get list of labs for menu
				$addnew_sql = sprintf("SELECT DISTINCT id, ui_name FROM %sstructure_labs WHERE alias_for < 1 ORDER BY ui_name;", $emsaDbSchemaPrefix);
				$addnew_rs = @pg_query($host_pa, $addnew_sql) or \Udoh\Emsa\Utils\DisplayUtils::drawError("Unable to retrieve list of labs.", true);
				while ($addnew_row = pg_fetch_object($addnew_rs)) {
					printf("<option value=\"%d\">%s</option>", intval($addnew_row->id), htmlentities($addnew_row->ui_name));
				}
				@pg_free_result($addnew_rs);
			?>
			</select>
		<label class="vocab_search_form2" for="new_loinc_code">Master LOINC Code:</label><input class="ui-corner-all" type="text" name="new_loinc_code" id="new_loinc_code" />
		
		<input type="hidden" name="lab_id" value="" />
		<input type="hidden" name="version" value="" />
		<input type="hidden" name="add_flag" value="1" />
		<br><br><button type="submit" name="new_savelab" id="new_savelab">Save New Knittable LOINC Code</button>
		<button type="button" id="addnew_cancel">Cancel</button>
	</form>
</div>

<div class="lab_results_container ui-widget ui-corner-all">
	<table id="labResults">
		<thead>
			<tr>
				<th>Actions</th>
				<th>Reporter</th>
				<th>Knittable Master LOINC Codes</th>
			</tr>
		</thead>
		<tbody>

<?php

	$where_clause = '';
	
	$where_count = 0;
	// handle any search terms or filters...
	if (isset($_SESSION[EXPORT_SERVERNAME]["structure_params"][$_SESSION[EXPORT_SERVERNAME]["structure_params"]["structure"]]["q_sql"])) {
		// we've got some query params
		$where_count = 1;
		$where_clause .= " WHERE (";
		
		foreach ($_SESSION[EXPORT_SERVERNAME]["structure_params"][$_SESSION[EXPORT_SERVERNAME]["structure_params"]["structure"]]["filter_cols"] as $searchcol => $searchcoldata) {
			if ($searchcoldata['textsearch']) {
				if ($where_count > 1) {
					$where_clause .= " OR ";
				}
				$where_clause .= sprintf("%s ILIKE '%%%s%%'", $searchcoldata['colname'], $_SESSION[EXPORT_SERVERNAME]["structure_params"][$_SESSION[EXPORT_SERVERNAME]["structure_params"]["structure"]]["q_sql"]);
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
	
	$hl7_qry = 'SELECT k.id AS id, k.lab_id AS lab_id, k.loinc AS loinc, l.ui_name AS lab_name 
		FROM '.$emsaDbSchemaPrefix.'structure_knittable_loincs k 
		INNER JOIN '.$emsaDbSchemaPrefix.'structure_labs l ON (k.lab_id = l.id) 
		'.$where_clause.' 
		ORDER BY l.ui_name, k.loinc;';
	$loincRs = @pg_query($host_pa, $hl7_qry) or \Udoh\Emsa\Utils\DisplayUtils::drawError("Could not connect to database:");
	
	while ($loincRow = @pg_fetch_object($loincRs)) {
		echo "<tr>";
		printf("<td class=\"action_col\">");
		unset($editLoincParams);
		$editLoincParams = array(
			"element_id" => intval($loincRow->id), 
			"lab_id" => intval($loincRow->lab_id), 
			"loinc" => htmlentities($loincRow->loinc, ENT_QUOTES, "UTF-8")
		);
		printf("<button class=\"edit_lab\" type=\"button\" value='%s' title=\"Edit this record\">Edit</button>", json_encode($editLoincParams));
		printf("<button class=\"delete_lab\" type=\"button\" value=\"%s\" title=\"Delete this record\">Delete</button>", $loincRow->id);
		echo "</td>";
		echo "<td>".htmlentities($loincRow->lab_name, ENT_QUOTES, "UTF-8")."</td>";
		echo "<td>".htmlentities($loincRow->loinc, ENT_QUOTES, "UTF-8")."</td>";
		echo "</tr>";
	}
	
	@pg_free_result($loincRs);

?>

		</tbody>
	</table>
	
</div>

<div id="confirm_delete_dialog" title="Remove this LOINC Code?">
	<p><span class="ui-icon ui-icon-elrerror" style="float:left; margin:0 7px 50px 0;"></span>Really remove this code from list of knittable LOINC codes?</p>
</div>

<div id="edit_lab_dialog" title="Edit LOINC Code">
	<form id="edit_modal_form" method="POST" action="<?php echo $webappBaseUrl; ?>?selected_page=<?php echo intval($navSelectedPage); ?>&submenu=<?php echo intval($navSubmenu); ?>&cat=<?php echo intval($navCat); ?>">
		<label for="edit_lab_id">Reporting Facility:</label><br>
		<select class="ui-corner-all" style="margin: 0px;" name="edit_lab_id" id="edit_lab_id">
			<option value="0" selected>--</option>
		<?php
			// get list of labs for menu
			$editlabs_sql = sprintf("SELECT DISTINCT id, ui_name FROM %sstructure_labs WHERE alias_for < 1 ORDER BY ui_name;", $emsaDbSchemaPrefix);
			$editlabs_rs = @pg_query($host_pa, $editlabs_sql) or \Udoh\Emsa\Utils\DisplayUtils::drawError("Unable to retrieve list of labs.", true);
			while ($editlabs_row = pg_fetch_object($editlabs_rs)) {
				printf("<option value=\"%d\">%s</option>", intval($editlabs_row->id), htmlentities($editlabs_row->ui_name));
			}
			pg_free_result($editlabs_rs);
		?>
		</select><br><br>
		<label for="edit_loinc_code">LOINC Code:</label><br><input class="ui-corner-all" type="text" name="edit_loinc_code" id="edit_loinc_code" /><br><br><input type="hidden" name="edit_id" id="edit_id" />
	</form>
</div>
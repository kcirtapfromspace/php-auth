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
use Udoh\Emsa\Utils\CoreUtils;
use Udoh\Emsa\Utils\DisplayUtils;
use Udoh\Emsa\Utils\ExceptionUtils;

if (!class_exists('\Udoh\Emsa\Auth\Authenticator') || !\Udoh\Emsa\Auth\Authenticator::userHasPermission(\Udoh\Emsa\Auth\Authenticator::URIGHTS_ADMIN)) {
    ob_end_clean();
    header($_SERVER['SERVER_PROTOCOL'] . " 404 Not Found", TRUE, 404);
    exit;
}

	// check for session freshness since last update to session-stored model info
	// (columns/filters are stored in session data, this hack gives us a way to force
	// session data to be refreshed without forcing users to clear cookies if the data
	// is updated mid-session, so that the current columns/filters are used)
    //
	// Use 'filemtime()' to dynamically get last modification date of file
	// Much less hassle than having to manually set a 'freshness date' each edit
	$modelLastUpdated = filemtime("manage/vocab_valueset.php");

	// check "freshness date"...
	if (isset($_SESSION[EXPORT_SERVERNAME]['valueset_model_fresh'])) {
		if ($_SESSION[EXPORT_SERVERNAME]['valueset_model_fresh'] < $modelLastUpdated) {
			// old model data; unset vocab_valueset_params & set a new "freshness date"...
			unset($_SESSION[EXPORT_SERVERNAME]['vocab_valueset_params']);
			$_SESSION[EXPORT_SERVERNAME]['valueset_model_fresh'] = time();
		}
	} else {
		// hack for sessions set before "freshness date" implemented
		unset($_SESSION[EXPORT_SERVERNAME]['vocab_valueset_params']);
		$_SESSION[EXPORT_SERVERNAME]['valueset_model_fresh'] = time();
	}


	// switch vocab type based on 'subcat' variable
	$_SESSION[EXPORT_SERVERNAME]['vocab_valueset_params']['vocab'] = 8;
	if (isset($navSubcat)) {
		switch (intval($navSubcat)) {
			case 9:
				$_SESSION[EXPORT_SERVERNAME]['vocab_valueset_params']['vocab'] = 9;
				break;
			default:
				$_SESSION[EXPORT_SERVERNAME]['vocab_valueset_params']['vocab'] = 8;
				break;
		}
	}


    // get list of apps, if master dictionary
    if ((int) $_SESSION[EXPORT_SERVERNAME]['vocab_valueset_params']['vocab'] === 8) {
        $appList = CoreUtils::getAppList($adminDbConn, true);
        if (count($appList) <= 0) {
            DisplayUtils::drawError('Unable to load list of Applications.');
        }
    }

	// if edit_id passed, include editor...
	// this must happen after setting the session vocab value, else xref links between dependent items will fail
	if (isset($_GET['edit_id'])){
		include __DIR__ . '/vocab_valueset_edit.php';
	}

		// Search/Filter Prep
		// pre-build our vocab-specific search data...
		if (isset($_GET['q'])) {
			if ((trim($_GET['q']) != "Enter search terms...") && (strlen(trim($_GET['q'])) > 0)) {
				$_SESSION[EXPORT_SERVERNAME]['vocab_valueset_params'][$_SESSION[EXPORT_SERVERNAME]['vocab_valueset_params']['vocab']]['q_display'] = $emsaHTMLPurifier->purify(trim($_GET['q']));
				$_SESSION[EXPORT_SERVERNAME]['vocab_valueset_params'][$_SESSION[EXPORT_SERVERNAME]['vocab_valueset_params']['vocab']]['q_sql'] = (string) filter_input(INPUT_GET, 'q', FILTER_UNSAFE_RAW);
				if (!isset($_GET['f'])) {
					// search query found, but no filters selected
					// if any filters were previously SESSIONized, they've been deselected via the UI, so we'll clear them...
					unset($_SESSION[EXPORT_SERVERNAME]['vocab_valueset_params'][$_SESSION[EXPORT_SERVERNAME]['vocab_valueset_params']['vocab']]['filters']);
				}
			} else {
				// search field was empty/defaulted, so we'll destroy the saved search params...
				unset($_SESSION[EXPORT_SERVERNAME]['vocab_valueset_params'][$_SESSION[EXPORT_SERVERNAME]['vocab_valueset_params']['vocab']]['q_display']);
				unset($_SESSION[EXPORT_SERVERNAME]['vocab_valueset_params'][$_SESSION[EXPORT_SERVERNAME]['vocab_valueset_params']['vocab']]['q_sql']);
				// not only was search blank, but no filters selected, so clear them as well...
				unset($_SESSION[EXPORT_SERVERNAME]['vocab_valueset_params'][$_SESSION[EXPORT_SERVERNAME]['vocab_valueset_params']['vocab']]['filters']);
			}
		}

		// update SESSIONized filters or destroy them if no filters are selected...
		if (isset($_GET['f'])) {
			if (is_array($_GET['f'])) {
				$_SESSION[EXPORT_SERVERNAME]['vocab_valueset_params'][$_SESSION[EXPORT_SERVERNAME]['vocab_valueset_params']['vocab']]['filters'] = $_GET['f'];
			}
		}

		// check against result_cols and filter_cols to determine if this needs to be done, since condition can
		// exist where cookies have been cleared in another tab, but sending a search query through sets
		// $_SESSION[EXPORT_SERVERNAME]['vocab_valueset_params'][$_SESSION[EXPORT_SERVERNAME]['vocab_valueset_params']['vocab']]['q_display'] & ...['q_sql'],
		// but not the other default vocab params
		if (
				   !isset($_SESSION[EXPORT_SERVERNAME]['vocab_valueset_params'][$_SESSION[EXPORT_SERVERNAME]['vocab_valueset_params']['vocab']])
                || !isset($_SESSION[EXPORT_SERVERNAME]['vocab_valueset_params'][$_SESSION[EXPORT_SERVERNAME]['vocab_valueset_params']['vocab']]['result_cols'])
                || !isset($_SESSION[EXPORT_SERVERNAME]['vocab_valueset_params'][$_SESSION[EXPORT_SERVERNAME]['vocab_valueset_params']['vocab']]['filter_cols'])
                || EmsaUtils::emptyTrim($_SESSION[EXPORT_SERVERNAME]['vocab_valueset_params'][$_SESSION[EXPORT_SERVERNAME]['vocab_valueset_params']['vocab']]['result_cols'])
				|| EmsaUtils::emptyTrim($_SESSION[EXPORT_SERVERNAME]['vocab_valueset_params'][$_SESSION[EXPORT_SERVERNAME]['vocab_valueset_params']['vocab']]['filter_cols'])
			) {
			// if no params exist for selected vocab, load default values...
			$_SESSION[EXPORT_SERVERNAME]['vocab_valueset_params'][$_SESSION[EXPORT_SERVERNAME]['vocab_valueset_params']['vocab']]['page_rows'] = DEFAULT_ROWS_PER_PAGE;
			$_SESSION[EXPORT_SERVERNAME]['vocab_valueset_params'][$_SESSION[EXPORT_SERVERNAME]['vocab_valueset_params']['vocab']]['sort_order'] = "ASC";
			$_SESSION[EXPORT_SERVERNAME]['vocab_valueset_params'][$_SESSION[EXPORT_SERVERNAME]['vocab_valueset_params']['vocab']]['sort_col'] = "category";
			$_SESSION[EXPORT_SERVERNAME]['vocab_valueset_params'][$_SESSION[EXPORT_SERVERNAME]['vocab_valueset_params']['vocab']]['vocab_verbose'] = "Master Dictionary";
			$_SESSION[EXPORT_SERVERNAME]['vocab_valueset_params'][$_SESSION[EXPORT_SERVERNAME]['vocab_valueset_params']['vocab']]['result_cols'] = array(
				"category" => "Category",
				"codeset" => "Value Set Code",
				"master_concept" => "Master Concept Name");
			$_SESSION[EXPORT_SERVERNAME]['vocab_valueset_params'][$_SESSION[EXPORT_SERVERNAME]['vocab_valueset_params']['vocab']]['column_aliases'] = array(
				"category" => "sc.label",
				"codeset" => "mv.codeset",
				"master_concept" => "mv.concept");
			$_SESSION[EXPORT_SERVERNAME]['vocab_valueset_params'][$_SESSION[EXPORT_SERVERNAME]['vocab_valueset_params']['vocab']]['filter_cols'] = array(
				"category" => array(
					"fieldtable" => "structure_category sc",
					"itemval" => "sc.id",
					"itemlabel" => "sc.label",
					"fieldlabel" => "Category",
                    "constraint" => null),
				"codeset" => array(
					"fieldtable" => "vocab_master_vocab mv",
					"itemval" => "mv.codeset",
					"itemlabel" => null,
					"fieldlabel" => "Value Code Set",
                    "constraint" => null));

			switch (intval($_SESSION[EXPORT_SERVERNAME]['vocab_valueset_params']['vocab'])) {
				case 9:
					// child value set
					$_SESSION[EXPORT_SERVERNAME]['vocab_valueset_params'][$_SESSION[EXPORT_SERVERNAME]['vocab_valueset_params']['vocab']]['page_rows'] = DEFAULT_ROWS_PER_PAGE;
					$_SESSION[EXPORT_SERVERNAME]['vocab_valueset_params'][$_SESSION[EXPORT_SERVERNAME]['vocab_valueset_params']['vocab']]['sort_order'] = "ASC";
					$_SESSION[EXPORT_SERVERNAME]['vocab_valueset_params'][$_SESSION[EXPORT_SERVERNAME]['vocab_valueset_params']['vocab']]['vocab_verbose'] = "Child Dictionary";
					$_SESSION[EXPORT_SERVERNAME]['vocab_valueset_params'][$_SESSION[EXPORT_SERVERNAME]['vocab_valueset_params']['vocab']]['sort_col'] = "category";
					$_SESSION[EXPORT_SERVERNAME]['vocab_valueset_params'][$_SESSION[EXPORT_SERVERNAME]['vocab_valueset_params']['vocab']]['result_cols'] = array(
						"ui_name" => "Child Lab",
						"category" => "Master Category",
						"child_concept" => "Child Code",
						"master_concept" => "Master Concept Name",
                        "comment" => "Append to Comments");
					$_SESSION[EXPORT_SERVERNAME]['vocab_valueset_params'][$_SESSION[EXPORT_SERVERNAME]['vocab_valueset_params']['vocab']]['column_aliases'] = array(
						"ui_name" => "l.ui_name",
						"category" => "sc.label",
						"master_concept" => "mv.concept",
						"child_concept" => "cv.concept",
                        "comment" => "cv.comment");
					$_SESSION[EXPORT_SERVERNAME]['vocab_valueset_params'][$_SESSION[EXPORT_SERVERNAME]['vocab_valueset_params']['vocab']]['filter_cols'] = array(
						"category" => array(
							"fieldtable" => "structure_category sc",
							"itemval" => "sc.id",
							"itemlabel" => "sc.label",
							"fieldlabel" => "Master Category",
                            "constraint" => null),
						"lab" => array(
							"fieldtable" => "structure_labs l",
							"itemval" => "l.id",
							"itemlabel" => "l.ui_name",
							"fieldlabel" => "Child Lab",
                            "constraint" => "WHERE alias_for = 0"));
					break;
				default:
					// master value set
					$_SESSION[EXPORT_SERVERNAME]['vocab_valueset_params'][$_SESSION[EXPORT_SERVERNAME]['vocab_valueset_params']['vocab']]['page_rows'] = DEFAULT_ROWS_PER_PAGE;
					$_SESSION[EXPORT_SERVERNAME]['vocab_valueset_params'][$_SESSION[EXPORT_SERVERNAME]['vocab_valueset_params']['vocab']]['sort_order'] = "ASC";
					$_SESSION[EXPORT_SERVERNAME]['vocab_valueset_params'][$_SESSION[EXPORT_SERVERNAME]['vocab_valueset_params']['vocab']]['vocab_verbose'] = "Master Dictionary";
					$_SESSION[EXPORT_SERVERNAME]['vocab_valueset_params'][$_SESSION[EXPORT_SERVERNAME]['vocab_valueset_params']['vocab']]['sort_col'] = "category";
					$_SESSION[EXPORT_SERVERNAME]['vocab_valueset_params'][$_SESSION[EXPORT_SERVERNAME]['vocab_valueset_params']['vocab']]['result_cols'] = array(
						"category" => "Category",
						"codeset" => "Value Set Code",
						"master_concept" => "Master Concept Name");
					$_SESSION[EXPORT_SERVERNAME]['vocab_valueset_params'][$_SESSION[EXPORT_SERVERNAME]['vocab_valueset_params']['vocab']]['column_aliases'] = array(
						"category" => "sc.label",
						"codeset" => "mv.codeset",
						"master_concept" => "mv.concept");
					$_SESSION[EXPORT_SERVERNAME]['vocab_valueset_params'][$_SESSION[EXPORT_SERVERNAME]['vocab_valueset_params']['vocab']]['filter_cols'] = array(
						"category" => array(
							"fieldtable" => "structure_category sc",
							"itemval" => "sc.id",
							"itemlabel" => "sc.label",
							"fieldlabel" => "Category",
                            "constraint" => null),
						"codeset" => array(
							"fieldtable" => "vocab_master_vocab mv",
							"itemval" => "mv.codeset",
							"itemlabel" => NULL,
							"fieldlabel" => "Value Code Set",
                            "constraint" => null));
					break;
			}
		}


		// check if app & lab are specified via filters... if not, default to 1 for each...
		switch (intval($_SESSION[EXPORT_SERVERNAME]['vocab_valueset_params']['vocab'])) {
			case 9:
				if (!isset($_SESSION[EXPORT_SERVERNAME]['vocab_valueset_params'][$_SESSION[EXPORT_SERVERNAME]['vocab_valueset_params']['vocab']]['filters']['lab'])) {
					//$_SESSION[EXPORT_SERVERNAME]['vocab_valueset_params'][$_SESSION[EXPORT_SERVERNAME]['vocab_valueset_params']['vocab']]['filters']['lab'][] = 1;
				}
				break;
			default:
				if (!isset($_SESSION[EXPORT_SERVERNAME]['vocab_valueset_params'][$_SESSION[EXPORT_SERVERNAME]['vocab_valueset_params']['vocab']]['filters']['app'])) {
					//$_SESSION[EXPORT_SERVERNAME]['vocab_valueset_params'][$_SESSION[EXPORT_SERVERNAME]['vocab_valueset_params']['vocab']]['filters']['app'][] = 1;
				}
				break;

		}


        // set up our SELECT statement and list of approved columns for the selected value set type
        $whereArr = [];
		if ($_SESSION[EXPORT_SERVERNAME]['vocab_valueset_params']['vocab'] == 8) {
			// master value set
			$validCols = array("category","codeset","master_concept");

            foreach ($appList as $appId => $appName) {
                $validCols[] = 'app' . $appId . '_coded_value';
            }

			$pagingSelectStmt = "SELECT count(mv.id) AS counter";

            $selectStmt = "SELECT sc.label AS category, mv.id AS id, mv.codeset AS codeset, mv.concept AS master_concept";
            foreach ($appList as $appId => $appName) {
                $selectStmt .= ', m2a' . $appId . '.coded_value AS app' . $appId . '_coded_value';
            }

			$fromStmt = " FROM vocab_master_vocab mv LEFT JOIN structure_category sc ON (mv.category = sc.id)";
            foreach ($appList as $appId => $appName) {
                $fromStmt .= ' LEFT JOIN vocab_master2app m2a' . $appId . ' ON (m2a' . $appId . '.master_id = mv.id and m2a' . $appId . '.app_id = ?) ';
                $whereArr[] = (int) $appId;
            }
		} elseif ($_SESSION[EXPORT_SERVERNAME]['vocab_valueset_params']['vocab'] == 9) {
			// child value set
			$validCols = array("ui_name","category","master_concept","child_concept","comment");
            $pagingSelectStmt = "SELECT count(cv.id) AS counter";
			$selectStmt = "SELECT cv.id AS id, l.ui_name AS ui_name, sc.label AS category, mv.concept AS master_concept, cv.concept AS child_concept, cv.comment AS comment";
			$fromStmt = "FROM vocab_child_vocab cv
                         JOIN structure_labs l ON (cv.lab_id = l.id)
                         JOIN vocab_master_vocab mv ON (cv.master_id = mv.id)
                         LEFT JOIN structure_category sc ON (mv.category = sc.id)";
		}

		// sort out our sorting...
		if (isset($_GET['order'])) {
			$_SESSION[EXPORT_SERVERNAME]['vocab_valueset_params'][$_SESSION[EXPORT_SERVERNAME]['vocab_valueset_params']['vocab']]['sort_order'] = ($_GET['order'] == "2") ? "DESC" : "ASC";
		}

		// ensure requested sort column is valid
		if (isset($_GET['sort']) && is_array($validCols)) {
			if (in_array(strtolower(trim($_GET['sort'])), $validCols)) {
				$_SESSION[EXPORT_SERVERNAME]['vocab_valueset_params'][$_SESSION[EXPORT_SERVERNAME]['vocab_valueset_params']['vocab']]['sort_col'] = strtolower(trim($_GET['sort']));
			}
		}

		$whereStmt = "WHERE";

		$whereCount = 0;
		// handle any search terms or filters...
		if (isset($_SESSION[EXPORT_SERVERNAME]['vocab_valueset_params'][$_SESSION[EXPORT_SERVERNAME]['vocab_valueset_params']['vocab']]['q_sql'])) {
			// we've got some query params
			$whereCount = 1;
			$whereStmt .= " (";

			foreach ($_SESSION[EXPORT_SERVERNAME]['vocab_valueset_params'][$_SESSION[EXPORT_SERVERNAME]['vocab_valueset_params']['vocab']]['result_cols'] as $searchcol => $searchcolname) {
				if ($whereCount > 1) {
					$whereStmt .= " OR ";
				}
				$whereStmt .= $_SESSION[EXPORT_SERVERNAME]['vocab_valueset_params'][$_SESSION[EXPORT_SERVERNAME]['vocab_valueset_params']['vocab']]['column_aliases'][$searchcol] . " ILIKE ? ";
				$whereArr[] = '%' . $_SESSION[EXPORT_SERVERNAME]['vocab_valueset_params'][$_SESSION[EXPORT_SERVERNAME]['vocab_valueset_params']['vocab']]['q_sql'] . '%';
				$whereCount++;
			}

            if (intval($_SESSION[EXPORT_SERVERNAME]['vocab_valueset_params']['vocab']) === 8) {
                foreach ($appList as $appId => $appName) {
                    $whereStmt .= ' OR m2a' . (int) $appId . ".coded_value ILIKE ?";
                    $whereArr[] = '%' . $_SESSION[EXPORT_SERVERNAME]['vocab_valueset_params'][$_SESSION[EXPORT_SERVERNAME]['vocab_valueset_params']['vocab']]['q_sql'] . '%';
                }
            }

			$whereStmt .= ")";
		}

		if (isset($_SESSION[EXPORT_SERVERNAME]['vocab_valueset_params'][$_SESSION[EXPORT_SERVERNAME]['vocab_valueset_params']['vocab']]['filters'])) {
			// need to apply filters
			$filterCount = 0;
			if ($whereCount == 0) {
				// not already a WHERE clause for search terms
				$whereStmt .= " ";
			}

			foreach ($_SESSION[EXPORT_SERVERNAME]['vocab_valueset_params'][$_SESSION[EXPORT_SERVERNAME]['vocab_valueset_params']['vocab']]['filters'] as $sqlfiltercol => $sqlfiltervals) {
				if ($sqlfiltercol != 'app') {
					$filterTempArr = array();
					$nullfilter = FALSE;
					if (($filterCount > 0) || ($whereCount > 1)) {
						$whereStmt .= " AND (";
					} else {
						$whereStmt .= " (";
					}
					foreach ($sqlfiltervals as $sqlfilterval) {
						if (is_null($sqlfilterval) || (strlen(trim($sqlfilterval)) == 0)) {
							$nullfilter = TRUE;
						} else {
						    $whereArr[] = $sqlfilterval;
							$filterTempArr[] = "(" . $_SESSION[EXPORT_SERVERNAME]['vocab_valueset_params'][$_SESSION[EXPORT_SERVERNAME]['vocab_valueset_params']['vocab']]['filter_cols'][$sqlfiltercol]['itemval'] . " = ?)";
						}
					}

					if ($nullfilter && (count($filterTempArr) > 0)) {
						$whereStmt .= " (" . implode(" OR ", $filterTempArr) . ") OR " . $_SESSION[EXPORT_SERVERNAME]['vocab_valueset_params'][$_SESSION[EXPORT_SERVERNAME]['vocab_valueset_params']['vocab']]['column_aliases'][$sqlfiltercol] . " IS NULL OR " . $_SESSION[EXPORT_SERVERNAME]['vocab_valueset_params'][$_SESSION[EXPORT_SERVERNAME]['vocab_valueset_params']['vocab']]['column_aliases'][$sqlfiltercol] . " = ''";
					} elseif (count($filterTempArr) > 0) {
						$whereStmt .= " (" . implode(" OR ", $filterTempArr) . ")";
					} elseif ($nullfilter) {
						$whereStmt .= $_SESSION[EXPORT_SERVERNAME]['vocab_valueset_params'][$_SESSION[EXPORT_SERVERNAME]['vocab_valueset_params']['vocab']]['filter_cols'][$sqlfiltercol]['itemval'] . " IS NULL OR " . $_SESSION[EXPORT_SERVERNAME]['vocab_valueset_params'][$_SESSION[EXPORT_SERVERNAME]['vocab_valueset_params']['vocab']]['column_aliases'][$sqlfiltercol] . " = ''";
					}

					$whereStmt .= ")";
					$filterCount++;
				}
			}
		}

		if (trim($whereStmt) == 'WHERE') {
			// if no filters got added, clear 'WHERE' statement
			$whereStmt = '';
		}


		// finish up our 'ORDER BY' clause now that we have all of our 'WHERE' stuff figured out...
		switch (intval($_SESSION[EXPORT_SERVERNAME]['vocab_valueset_params']['vocab'])) {
			case 9:
				// child dictionary; sort by lab first, then specified sort column
				$order_stmt = sprintf("ORDER BY ui_name ASC, %s %s", $_SESSION[EXPORT_SERVERNAME]['vocab_valueset_params'][$_SESSION[EXPORT_SERVERNAME]['vocab_valueset_params']['vocab']]['sort_col'], $_SESSION[EXPORT_SERVERNAME]['vocab_valueset_params'][$_SESSION[EXPORT_SERVERNAME]['vocab_valueset_params']['vocab']]['sort_order']);
				//$order_stmt = sprintf("ORDER BY %s %s", $_SESSION[EXPORT_SERVERNAME]['vocab_valueset_params'][$_SESSION[EXPORT_SERVERNAME]['vocab_valueset_params']['vocab']]['sort_col'], $_SESSION[EXPORT_SERVERNAME]['vocab_valueset_params'][$_SESSION[EXPORT_SERVERNAME]['vocab_valueset_params']['vocab']]['sort_order']);
				break;
			default:
				$order_stmt = sprintf("ORDER BY %s %s", $_SESSION[EXPORT_SERVERNAME]['vocab_valueset_params'][$_SESSION[EXPORT_SERVERNAME]['vocab_valueset_params']['vocab']]['sort_col'], $_SESSION[EXPORT_SERVERNAME]['vocab_valueset_params'][$_SESSION[EXPORT_SERVERNAME]['vocab_valueset_params']['vocab']]['sort_order']);
		}

?>

<script>
	$(function() {
	    let toggleFilters = $("#toggle_filters");
	    let searchBox = $("#q");
	    let editVocabBtns = $(".edit_vocab");
	    let confirmDeleteDialog = $("#confirm_delete_dialog");

		$(".colheader_sort_down").button({
			icon: "ui-icon-circle-arrow-s",
            iconPosition: "end"
		});

		$(".colheader_sort_up").button({
			icon: "ui-icon-circle-arrow-n",
            iconPosition: "end"
		});

		$("#latestTasks tbody tr").addClass("all_row");

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

		searchBox.addClass("search_empty").val("Enter search terms...").on("click", function() {
			let search_val = $("#q").val().trim();
			if (search_val === "Enter search terms...") {
				$(this).removeClass("search_empty").val("");
			}
		}).on("blur", function() {
			let search_val_ln = searchBox.val().trim().length;
			if (search_val_ln === 0) {
				$("#q").addClass("search_empty").val("Enter search terms...");
			}
		});

		<?php
			if (isset($_SESSION[EXPORT_SERVERNAME]['vocab_valueset_params'][$_SESSION[EXPORT_SERVERNAME]['vocab_valueset_params']['vocab']]['q_display'])) {
				if ($_SESSION[EXPORT_SERVERNAME]['vocab_valueset_params'][$_SESSION[EXPORT_SERVERNAME]['vocab_valueset_params']['vocab']]['q_display'] != "Enter search terms...") {
		?>
		searchBox.removeClass("search_empty").val(<?php echo json_encode($_SESSION[EXPORT_SERVERNAME]['vocab_valueset_params'][$_SESSION[EXPORT_SERVERNAME]['vocab_valueset_params']['vocab']]['q_display'], JSON_HEX_TAG); ?>);
		<?php
				}
			}
		?>

		$("#user_rows_change").button();

		$("#addnew_button").button({
            icon: "ui-icon-elrplus"
        }).on("click", function() {
			$("#addnew_form").show();
			$(".import_error").hide();
			$("#new_category").trigger("focus");
			$(this).hide();
		});

		$("#addnew_cancel").button({
			icon: "ui-icon-elrcancel"
		}).on("click", function() {
			$("#addnew_form").hide();
			$("#addnew_button").show();
		});

		$("#new_savevocab").button({
            icon: "ui-icon-elrsave"
        });

		editVocabBtns.button({
				icon: "ui-icon-elrpencil"
			}).next().button({
				icon: "ui-icon-elrclose"
			}).parent().controlgroup();

		confirmDeleteDialog.dialog({
			autoOpen: false,
			modal: true,
			draggable: false,
			resizable: false,
			width: 400
		});

		$(".delete_vocab").on("click", function(e) {
			e.preventDefault();
			let deleteAction = "<?php echo $webappBaseUrl; ?>?selected_page=6&submenu=3&cat=<?php echo $navCat; ?>&subcat=<?php echo $navSubcat; ?>&delete_id="+parseInt($(this).val());


			confirmDeleteDialog.dialog('option', 'buttons', {
					"Delete" : function() {
						window.location.href = deleteAction;
						},
					"Cancel" : function() {
						$(this).dialog("close");
						}
					});

			confirmDeleteDialog.dialog("open");

		});

		editVocabBtns.on("click", function(e) {
			e.preventDefault();
			let editAction = "<?php echo $webappBaseUrl; ?>?selected_page=6&submenu=3&cat=<?php echo $navCat; ?>&subcat=<?php echo $navSubcat; ?>&edit_id="+parseInt($(this).val());
			window.location.href = editAction;
		});

	});
</script>

<h1 class="elrhdg"><span class="ui-icon ui-icon-header ui-icon-emsadictionary"></span><?php echo $_SESSION[EXPORT_SERVERNAME]['vocab_valueset_params'][$_SESSION[EXPORT_SERVERNAME]['vocab_valueset_params']['vocab']]['vocab_verbose']; ?></h1>

<?php

        // BEGIN ADD/DELTE

		if (isset($_GET['delete_id'])) {
			// delete if valid-looking ID
			$valid_delete = FALSE;

            $deleteId = (int) filter_input(INPUT_GET, 'delete_id', FILTER_SANITIZE_NUMBER_INT);

			if ($deleteId > 0) {
				// delete code here
				$valid_delete = TRUE;
				$validdelete_sql = sprintf('SELECT count(id) AS id FROM %s%s WHERE id = $1', $emsaDbSchemaPrefix, (($navSubcat != 9) ? "vocab_master_vocab" : "vocab_child_vocab"));
				$validdelete_count = @pg_fetch_result(@pg_query_params($host_pa, $validdelete_sql, array((int) $deleteId)), 0, 'id');
				$valid_delete = ($validdelete_count == 1) ? TRUE : FALSE;

				// dependency check
				if($valid_delete) {
					if ($navSubcat != 9) {
						// master
						// check for child value set dependents first
						$valid_delete = FALSE;
						$dependency_sql = sprintf('SELECT count(id) AS id FROM %svocab_child_vocab WHERE master_id = $1', $emsaDbSchemaPrefix);
						$dependency_count = @pg_fetch_result(@pg_query_params($host_pa, $dependency_sql, array((int) $deleteId)), 0, 'id');
						if ($dependency_count > 0) {
							DisplayUtils::drawError(sprintf("Warning:  %d Child Vocabulary items refer to this Master Vocabulary item.  Please make sure no Child Vocabulary references this Master value and try again.", intval($dependency_count)));
						} else {
							// if no child vocab dependents, check for master vocab that links to this ID
							$dependency_sql = sprintf('SELECT (
								(SELECT count(c_id) FROM %svocab_master_condition WHERE condition = $1) +
								(SELECT count(o_id) FROM %svocab_master_organism WHERE organism = $2) +
								(SELECT count(id) FROM %svocab_rules_mastersnomed WHERE state_case_status_master_id = $3) +
								(SELECT count(id) FROM %svocab_rules_masterloinc WHERE state_case_status_master_id = $4) +
								(SELECT count(l_id) FROM %svocab_master_loinc WHERE trisano_test_type = $5)
								) AS dependents;',
								$emsaDbSchemaPrefix,
								$emsaDbSchemaPrefix,
								$emsaDbSchemaPrefix,
								$emsaDbSchemaPrefix,
								$emsaDbSchemaPrefix
								);
							$dependency_count = @pg_fetch_result(@pg_query_params($host_pa, $dependency_sql, array((int) $deleteId, (int) $deleteId, (int) $deleteId, (int) $deleteId, (int) $deleteId)), 0, 'dependents');
							if ($dependency_count > 0) {
								DisplayUtils::drawError(sprintf("Warning:  %d other vocabulary item(s) are dependent on this Master Dictionary entry.  Please check for the following usages and try again:<ul><li>Master Conditions associated with this Condition name?</li><li>Master SNOMEDs associated with this Organism name?</li><li>Master LOINCs associated with this Test Type?</li><li>Master LOINC/Master SNOMED Case Management Rules associated with this State Case Status?</li></ul>", intval($dependency_count)));
							} else {
								$valid_delete = TRUE;
							}
						}

						if ($valid_delete) {
							$va = new \VocabAudit($adminDbConn, $authClient);

							$deleted_mv_rows = array('id' => $deleteId, 'old' => $va->getPreviousVals($deleteId, \VocabAudit::TABLE_MASTER_VOCAB));
							$deleted_m2a_rows = array();
							$m2a_pre_delete_sql = 'SELECT id, app_id FROM '.$emsaDbSchemaPrefix.'vocab_master2app WHERE master_id = $1;';
							$m2a_pre_delete_rs = @pg_query_params($host_pa, $m2a_pre_delete_sql, array((int) $deleteId));
							if ($m2a_pre_delete_rs !== false) {
								while ($m2a_pre_delete_row = @pg_fetch_object($m2a_pre_delete_rs)) {
									$deleted_m2a_rows[] = array('id' => intval($m2a_pre_delete_row->id), 'old' => $va->getPreviousVals($deleteId, \VocabAudit::TABLE_MASTER_TO_APP, intval($m2a_pre_delete_row->app_id)));
								}
							}
							@pg_free_result($m2a_pre_delete_rs);

							// execute delete
							if (\Udoh\Emsa\Model\Vocabulary::deleteMasterDictionary($adminDbConn, $deleteId)) {
								DisplayUtils::drawHighlight("Vocabulary successfully deleted!", "ui-icon-check");

								$va->resetAudit();
								$va->setOldVals($deleted_mv_rows['old']);
								$va->auditVocab($deleteId, \VocabAudit::TABLE_MASTER_VOCAB, \VocabAudit::ACTION_DELETE);

								foreach ($deleted_m2a_rows as $deleted_m2a_row => $deleted_m2a_row_data) {
									$va->resetAudit();
									$va->setOldVals($deleted_m2a_row_data['old']);
									$va->auditVocab($deleteId, \VocabAudit::TABLE_MASTER_TO_APP, \VocabAudit::ACTION_DELETE);
								}
							} else {
								DisplayUtils::drawError("A database error occurred while attempting to delete this vocabulary item.");
							}
						}
					} else {
						// child
						// check for child value set dependents first
						$valid_delete = FALSE;
						$valid_delete = TRUE;

						if ($valid_delete) {
							$va = new \VocabAudit($adminDbConn, $authClient);

							$prev_vals = $va->getPreviousVals($deleteId, \VocabAudit::TABLE_CHILD_VOCAB);

							// execute delete
							$deletevocab_sql = sprintf('DELETE FROM ONLY %svocab_child_vocab WHERE id = $1;', $emsaDbSchemaPrefix);

							if (@pg_query_params($host_pa, $deletevocab_sql, array((int) $deleteId))) {
								DisplayUtils::drawHighlight("Vocabulary successfully deleted!", "ui-icon-check");

								$va->resetAudit();
								$va->setOldVals($prev_vals);
								$va->auditVocab($deleteId, \VocabAudit::TABLE_CHILD_VOCAB, \VocabAudit::ACTION_DELETE);
							} else {
								DisplayUtils::drawError("A database error occurred while attempting to delete this vocabulary item.");
							}
						}
					}

				} else {
					// valid integer passed, but no matching record found
					DisplayUtils::drawError("Cannot delete vocabulary:  Record not found");
				}
			} else {
				// not a valid integer, don't even try looking for it
				DisplayUtils::drawError("Cannot delete vocabulary:  Record not found");
			}
		} elseif (isset($_GET['add_flag'])) {
			$va = new \VocabAudit($adminDbConn, $authClient);
			if ($navSubcat != 9) {
				// add new master vocab item
				$valid_add = FALSE;
				$valid_valueset = isset($_GET['new_codeset']);
				$valid_concept = ((isset($_GET['new_masterconcept'])) && (ctype_print(trim($_GET['new_masterconcept']))) && (strlen(trim($_GET['new_masterconcept'])) > 0));
				$valid_category = ((isset($_GET['new_category'])) && (is_numeric(trim($_GET['new_category']))) && (intval(trim($_GET['new_category'])) > 0));
				$valid_appid = ((isset($_GET['new_appconcept'])) && (is_array($_GET['new_appconcept'])));
				$valid_add = $valid_valueset && $valid_concept && $valid_category && $valid_appid;
				if ($valid_add) {
					// check to make sure all app values passed correspond to actual configured applications
					$newAppsArr = array();
					foreach ($_GET['new_appconcept'] as $new_appid => $new_appvalue) {
						if (isset($appList[$new_appid])) {
							$newAppsArr[$new_appid] = array("app_name" => trim($appList[$new_appid]), "app_value" => $new_appvalue);
						}
					}
					if (count($newAppsArr) > 0) {
						// insert master values & get new master_id
                        $cleanNewCategory = (int) filter_var($_GET['new_category'], FILTER_SANITIZE_NUMBER_INT);
                        $cleanNewMasterConcept = trim($_GET['new_masterconcept']);
                        $cleanNewValueSet = (strlen(trim($_GET['new_codeset'])) > 0) ? trim($_GET['new_codeset']) : null;

                        $newMasterId = \Udoh\Emsa\Model\Vocabulary::addMasterDictionary($adminDbConn, $cleanNewCategory, $cleanNewMasterConcept, $cleanNewValueSet);

						if ($newMasterId > 0) {
							DisplayUtils::drawHighlight("Master values added!", "ui-icon-check");

							$va->resetAudit();
							$va->setNewVals($va->prepareNewValues(\VocabAudit::TABLE_MASTER_VOCAB, array('category' => $cleanNewCategory, 'valueset' => trim($_GET['new_codeset']), 'masterconcept' => $cleanNewMasterConcept)));
							$va->auditVocab($newMasterId, \VocabAudit::TABLE_MASTER_VOCAB, \VocabAudit::ACTION_ADD);

							foreach ($newAppsArr as $this_app_id => $this_app_data) {
								// insert app-specific values
                                $newMasterToAppId = \Udoh\Emsa\Model\Vocabulary::addMasterToAppTranslation($adminDbConn, $newMasterId, $this_app_id, trim($this_app_data['app_value']));

								if ($newMasterToAppId > 0) {
									DisplayUtils::drawHighlight(sprintf("%s value added!", htmlspecialchars($emsaHTMLPurifier->purify($this_app_data['app_name']))), "ui-icon-check");

									$va->resetAudit();
									$va->setNewVals($va->prepareNewValues(\VocabAudit::TABLE_MASTER_TO_APP, array('app_id' => intval($this_app_id), 'appvalue' => trim($this_app_data['app_value']))));
									$va->auditVocab($newMasterId, \VocabAudit::TABLE_MASTER_TO_APP, \VocabAudit::ACTION_ADD);
								} else {
									DisplayUtils::drawError(sprintf("Could not insert value for %s", htmlspecialchars($emsaHTMLPurifier->purify($this_app_data['app_name']))));
								}
							}
						} else {
							DisplayUtils::drawError("Could not add new Master Vocabulary");
						}
					} else {
						DisplayUtils::drawError("Could not add new Master Vocabulary:  Application(s) not found");
					}
				} else {
					DisplayUtils::drawError("Could not add new Master Vocabulary");
				}
			} else {
				// add new child vocab item
				$valid_add = FALSE;
				$valid_childcode = ((isset($_GET['new_childconcept'])) && (ctype_print(trim($_GET['new_childconcept']))) && (strlen(trim($_GET['new_childconcept'])) > 0));
				$valid_labid = ((isset($_GET['new_child'])) && (ctype_digit(trim($_GET['new_child']))));
				$valid_masterid = ((isset($_GET['new_masterid'])) && (ctype_digit(trim($_GET['new_masterid']))));
				if ($valid_labid) {
					$validlab_sql = sprintf('SELECT count(id) AS id FROM %sstructure_labs WHERE id = $1;', $emsaDbSchemaPrefix);
					$validlab_count = @pg_fetch_result(@pg_query_params($host_pa, $validlab_sql, array(intval(trim($_GET['new_child'])))), 0, 'id');
					$valid_labid = ($validlab_count == 1) ? TRUE : FALSE;
				}
				if ($valid_masterid) {
					$validmid_sql = sprintf('SELECT count(id) AS id FROM %svocab_master_vocab WHERE id = $1;', $emsaDbSchemaPrefix);
					$validmid_count = @pg_fetch_result(@pg_query_params($host_pa, $validmid_sql, array(intval(trim($_GET['new_masterid'])))), 0, 'id');
					$valid_masterid = ($validmid_count == 1) ? TRUE : FALSE;
				}
				$valid_add = $valid_childcode && $valid_labid && $valid_masterid;
				if ($valid_add) {
					// insert code here
					$insertvocab_sql = sprintf('INSERT INTO %svocab_child_vocab (lab_id, master_id, concept, comment) VALUES ($1, $2, $3, $4) RETURNING id;', $emsaDbSchemaPrefix);
					//echo $insertvocab_sql;
					$insertvocab_rs = @pg_query_params($host_pa, $insertvocab_sql, array(intval(trim($_GET['new_child'])), intval(trim($_GET['new_masterid'])), trim($_GET['new_childconcept']), trim($_GET['new_comment'])));
					if ($insertvocab_rs !== false) {
						$insertvocab_id = intval(@pg_fetch_result($insertvocab_rs, 0, 0));
						DisplayUtils::drawHighlight("New Child Vocabulary added successfully!", "ui-icon-check");

						$va->resetAudit();
						$va->setNewVals($va->prepareNewValues(\VocabAudit::TABLE_CHILD_VOCAB, array('lab_id' => intval(trim($_GET['new_child'])), 'master_id' => intval(trim($_GET['new_masterid'])), 'child_code' => trim($_GET['new_childconcept']), 'comment' => trim($_GET['new_comment']))));
						$va->auditVocab($insertvocab_id, \VocabAudit::TABLE_CHILD_VOCAB, \VocabAudit::ACTION_ADD);
					} else {
						DisplayUtils::drawError("Could not add new Child Vocabulary");
					}
				} else {
					DisplayUtils::drawError("Could not add new Child Vocabulary");
				}
			}
		}

        // END ADD/DELETE


		// get result count for pagination
        try {
            $resultCountSql = $pagingSelectStmt . ' ' . $fromStmt . ' ' . $whereStmt;
            $resultCountStmt = $adminDbConn->prepare($resultCountSql);

            $resultCountStmt->execute($whereArr);

            $numrows = (int) filter_var($resultCountStmt->fetchColumn(0), FILTER_SANITIZE_NUMBER_INT);
        } catch (Throwable $e) {
            ExceptionUtils::logException($e);
            DisplayUtils::drawError("Unable to calculate number of rows returned.");
            $numrows = 0;
        }

		// number of rows to show per page
		$valid_rowsize = array(50, 100, 250, 500, 1000, -1);
		if(isset($_GET['user_rows'])){
			if (in_array(intval(trim($_GET['user_rows'])) , $valid_rowsize)) {
				$_SESSION[EXPORT_SERVERNAME]['vocab_valueset_params'][$_SESSION[EXPORT_SERVERNAME]['vocab_valueset_params']['vocab']]['page_rows'] = intval(trim($_GET['user_rows']));
			}
		}

		// find out total pages
		if ($_SESSION[EXPORT_SERVERNAME]['vocab_valueset_params'][$_SESSION[EXPORT_SERVERNAME]['vocab_valueset_params']['vocab']]['page_rows'] > 0) {
			$totalpages = ceil($numrows / $_SESSION[EXPORT_SERVERNAME]['vocab_valueset_params'][$_SESSION[EXPORT_SERVERNAME]['vocab_valueset_params']['vocab']]['page_rows']);

			// get the current page or set a default
			if (isset($_GET['currentpage']) && is_numeric($_GET['currentpage'])) {
				$_SESSION[EXPORT_SERVERNAME]['vocab_valueset_params'][$_SESSION[EXPORT_SERVERNAME]['vocab_valueset_params']['vocab']]['current_page'] = intval($_GET['currentpage']);
			}

			if (isset($_SESSION[EXPORT_SERVERNAME]['vocab_valueset_params'][$_SESSION[EXPORT_SERVERNAME]['vocab_valueset_params']['vocab']]['current_page'])) {
				$currentpage = $_SESSION[EXPORT_SERVERNAME]['vocab_valueset_params'][$_SESSION[EXPORT_SERVERNAME]['vocab_valueset_params']['vocab']]['current_page'];
			} else {
				$currentpage = 1;
			}

			// if current page is greater than total pages...
			if ($currentpage > $totalpages) {
				// set current page to last page
				$currentpage = $totalpages;
			}

			// if current page is less than first page...
			if ($currentpage < 1) {
				// set current page to first page
				$currentpage = 1;
			}

			// the offset of the list, based on current page
			$offset = ($currentpage - 1) * $_SESSION[EXPORT_SERVERNAME]['vocab_valueset_params'][$_SESSION[EXPORT_SERVERNAME]['vocab_valueset_params']['vocab']]['page_rows'];
		} else {
            $offset = 0;
			$totalpages = 1;
			$currentpage = 1;
		}


?>

<form name="search_form" id="search_form" method="GET" action="<?php echo $webappBaseUrl; ?>">

<div class="emsa_search_controls ui-tabs ui-widget">
	<label for="q" class="emsa_form_heading" style="margin-right: 10px;">Search:</label><input type="text" name="q" id="q" class="vocab_query ui-corner-all">
	<button name="q_go" id="q_go">Search</button>
	<button type="button" name="clear_filters" id="clear_filters" title="Clear all filters/search terms">Reset</button>
	<button type="button" name="toggle_filters" id="toggle_filters" title="Show/hide filters">Hide Filters</button>
	<button id="addnew_button" type="button" title="Add a new '<?php echo $_SESSION[EXPORT_SERVERNAME]['vocab_valueset_params'][$_SESSION[EXPORT_SERVERNAME]['vocab_valueset_params']['vocab']]['vocab_verbose']; ?>' record">Add new <?php echo $_SESSION[EXPORT_SERVERNAME]['vocab_valueset_params'][$_SESSION[EXPORT_SERVERNAME]['vocab_valueset_params']['vocab']]['vocab_verbose']; ?></button>

</div>

<?php
	############### If filters applied, display which ones ###############
	if (isset($_SESSION[EXPORT_SERVERNAME]['vocab_valueset_params'][$_SESSION[EXPORT_SERVERNAME]['vocab_valueset_params']['vocab']]['filters'])) {
?>
<div class="vocab_search ui-widget ui-widget-content ui-state-highlight ui-corner-all" style="padding: 5px;">
	<span class="ui-icon ui-icon-elroptions" style="float: left; margin-right: .3em;"></span><p style="margin-left: 20px;">Active Filters:
<?php
		$active_filters = 0;
		foreach ($_SESSION[EXPORT_SERVERNAME]['vocab_valueset_params'][$_SESSION[EXPORT_SERVERNAME]['vocab_valueset_params']['vocab']]['filters'] as $sqlfiltercol => $sqlfiltervals) {
			if ($active_filters == 0) {
				echo '<strong>' . htmlspecialchars($emsaHTMLPurifier->purify($_SESSION[EXPORT_SERVERNAME]['vocab_valueset_params'][$_SESSION[EXPORT_SERVERNAME]['vocab_valueset_params']['vocab']]['filter_cols'][$sqlfiltercol]['fieldlabel'])) . '</strong> <em style="color: darkgray;">(' . htmlspecialchars($emsaHTMLPurifier->purify(\Udoh\Emsa\Utils\VocabUtils::verboseFilterValues($adminDbConn, $_SESSION[EXPORT_SERVERNAME]['vocab_valueset_params'][$_SESSION[EXPORT_SERVERNAME]['vocab_valueset_params']['vocab']]['filter_cols'][$sqlfiltercol], $sqlfiltervals))) . ')</em>';
			} else {
				echo ', <strong>' . htmlspecialchars($emsaHTMLPurifier->purify($_SESSION[EXPORT_SERVERNAME]['vocab_valueset_params'][$_SESSION[EXPORT_SERVERNAME]['vocab_valueset_params']['vocab']]['filter_cols'][$sqlfiltercol]['fieldlabel'])) . '</strong> <em style="color: darkgray;">(' . htmlspecialchars($emsaHTMLPurifier->purify(\Udoh\Emsa\Utils\VocabUtils::verboseFilterValues($adminDbConn, $_SESSION[EXPORT_SERVERNAME]['vocab_valueset_params'][$_SESSION[EXPORT_SERVERNAME]['vocab_valueset_params']['vocab']]['filter_cols'][$sqlfiltercol], $sqlfiltervals))) . ')</em>';
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
	foreach ($_SESSION[EXPORT_SERVERNAME]['vocab_valueset_params'][$_SESSION[EXPORT_SERVERNAME]['vocab_valueset_params']['vocab']]['filter_cols'] as $filtercol => $filtercolname) {
        if (is_null($filtercolname['itemlabel'])) {
			$filterQuery = sprintf("SELECT DISTINCT %s AS value, %s AS label FROM %s %s ORDER BY %s ASC;",
				$filtercolname['itemval'],
				$filtercolname['itemval'],
				$filtercolname['fieldtable'],
                trim($filtercolname['constraint']),
				$filtercolname['itemval']);
		} else {
			$filterQuery = sprintf("SELECT DISTINCT %s AS value, %s AS label FROM %s %s ORDER BY %s ASC;",
				$filtercolname['itemval'],
				$filtercolname['itemlabel'],
				$filtercolname['fieldtable'],
				trim($filtercolname['constraint']),
				$filtercolname['itemlabel']);
		}

		(new AccessibleMultiselectListbox(FilterFactory::getListFromQuery($adminDbConn, $filterQuery), $_SESSION[EXPORT_SERVERNAME]['vocab_valueset_params'][$_SESSION[EXPORT_SERVERNAME]['vocab_valueset_params']['vocab']]['filters'][$filtercol] ?? null))
            ->render($filtercolname['fieldlabel'], 'f[' . $filtercol . ']');
	}
?>
	<br><br><button name="apply_filters" id="apply_filters" style="clear: both; float: left; margin: 5px;">Apply Filters</button>
</div>

<input type="hidden" name="selected_page" value="<?php echo $navSelectedPage; ?>">
<input type="hidden" name="submenu" value="<?php echo $navSubmenu; ?>">
<input type="hidden" name="cat" value="<?php echo (($navCat < 2) ? 1 : $navCat); ?>">
<input type="hidden" name="subcat" value="<?php echo (($navSubcat < 2) ? 1 : $navSubcat); ?>">

</form>


<div id="addnew_form" class="addnew_lab ui-widget ui-widget-content ui-corner-all">
	<div style="clear: both;"><span class="emsa_form_heading">Add New <?php echo $_SESSION[EXPORT_SERVERNAME]['vocab_valueset_params'][$_SESSION[EXPORT_SERVERNAME]['vocab_valueset_params']['vocab']]['vocab_verbose']; ?>:</span><br><br></div>
	<form id="new_vocab_form" method="GET">
	<?php
		if ($navSubcat != 9) {
			// Add New Master Vocab form
	?>
			<div class="addnew_field"><label class="vocab_add_form" for="new_category">Category:</label><br>
				<select class="ui-corner-all" name="new_category" id="new_category">
					<option value="-1" selected>--</option>
				<?php
					// get list of top-level labs for alias menu
					$newcategory_sql = sprintf("SELECT id, label FROM %sstructure_category ORDER BY label;", $emsaDbSchemaPrefix);
					$newcategory_result = @pg_query($host_pa, $newcategory_sql) or DisplayUtils::drawError("Unable to retrieve list of Master Categories.", true);
					while ($newcategory_row = pg_fetch_object($newcategory_result)) {
						printf("<option value=\"%s\">%s</option>", intval($newcategory_row->id), htmlspecialchars($emsaHTMLPurifier->purify($newcategory_row->label)));
					}
					pg_free_result($newcategory_result);
				?>
				</select>
			</div>
			<div class="addnew_field"><label class="vocab_add_form" for="new_codeset">Value Set Code:</label><br><input class="ui-corner-all" type="text" name="new_codeset" id="new_codeset" /></div>
			<div class="addnew_field"><label class="vocab_add_form" for="new_masterconcept">Master Concept Name:</label><br><input class="ui-corner-all" type="text" name="new_masterconcept" id="new_masterconcept" /></div>

		<?php
			// draw app-specific value input for each configured app
			foreach ($appList as $appId => $appName) {
				echo "<div class=\"add-form-divider\"></div>";
				printf("<div class=\"addnew_field\"><label class=\"vocab_add_form\" for=\"new_appconcept_%d\">%s Value:</label><br><input class=\"ui-corner-all\" type=\"text\" name=\"new_appconcept[%d]\" id=\"new_appconcept_%d\" /></div>",
					(int) $appId,
					htmlspecialchars($emsaHTMLPurifier->purify($appName)),
					(int) $appId,
					(int) $appId);
			}

		} else {
			// Add New Child Vocab form
	?>

			<div class="addnew_field"><label class="vocab_add_form" for="new_child">Lab:</label><br>
				<select class="ui-corner-all" name="new_child" id="new_child">
					<option value="-1" selected>--</option>
				<?php
					// get list of top-level labs for alias menu
					$newchild_sql = sprintf("SELECT id, ui_name FROM %sstructure_labs WHERE alias_for < 1 ORDER BY ui_name;", $emsaDbSchemaPrefix);
					$newchild_result = @pg_query($host_pa, $newchild_sql) or DisplayUtils::drawError("Unable to retrieve list of Labs.", true);
					while ($newchild_row = pg_fetch_object($newchild_result)) {
						printf("<option value=\"%d\">%s</option>", intval($newchild_row->id), htmlspecialchars($emsaHTMLPurifier->purify($newchild_row->ui_name)));
					}
					pg_free_result($newchild_result);
				?>
				</select>
			</div>
			<div class="add-form-divider"></div>
			<div class="addnew_field" style="vertical-align: top;"><label class="vocab_add_form" for="new_childconcept">Child Code:</label><br><input class="ui-corner-all" type="text" name="new_childconcept" id="new_childconcept" /></div>
			<div class="addnew_field"><label class="vocab_search_form2" for="new_mastercat">Master Category:</label>
				<select class="ui-corner-all" name="new_mastercat" id="new_mastercat">
					<option value="-1" selected>--</option>
				<?php
					// get unique categories for auto-populating master concept list
					$newmastercat_sql = sprintf("SELECT id, label FROM %sstructure_category ORDER BY label;", $emsaDbSchemaPrefix);
					$newmastercat_result = @pg_query($host_pa, $newmastercat_sql) or DisplayUtils::drawError("Unable to retrieve Master Vocabulary.", true);
					while ($newmastercat_row = pg_fetch_object($newmastercat_result)) {
						printf("<option value=\"%s\">%s</option>", intval($newmastercat_row->id), htmlspecialchars($emsaHTMLPurifier->purify($newmastercat_row->label)));
					}
					pg_free_result($newmastercat_result);
				?>
				</select>
			<br><span class="ui-icon ui-icon-arrowreturnthick-1-e" style="float: left; margin-left: 30px; margin-top: 3px;"></span><label class="vocab_search_form2" for="new_masterid">Master Concept Name:</label>
				<select class="ui-corner-all" name="new_masterid" id="new_masterid" style="width: auto; min-width: 100px;">
					<option value="-1" selected>--</option>
				<?php
					// get list of top-level labs for alias menu
					$newmasterid_sql = sprintf("SELECT id, category, concept FROM %svocab_master_vocab ORDER BY category, concept;", $emsaDbSchemaPrefix);
					$newmasterid_result = @pg_query($host_pa, $newmasterid_sql) or DisplayUtils::drawError("Unable to retrieve Master Vocabulary.", true);
					//echo "<script type=\"text/javascript\">\nvar master_temp = [];\n";
                    $tempMasterConcepts = [];
					while ($newmasterid_row = pg_fetch_object($newmasterid_result)) {
						$tempMasterConcepts[intval($newmasterid_row->category)][intval($newmasterid_row->id)] = htmlspecialchars($emsaHTMLPurifier->purify($newmasterid_row->concept));
					}
					pg_free_result($newmasterid_result);
				?>
				</select>


				<script type="text/javascript">
					var masterList = <?php echo json_encode($tempMasterConcepts); ?>;

					$("#new_mastercat").on("change", function () {
						let selectedCat = $("#new_mastercat").val();
						let newMasterId = $("#new_masterid");

						newMasterId.empty();
						if (selectedCat != -1) {
							$.each(masterList[selectedCat], function(id, label) {
								$("#new_masterid").append($("<option />").val(id).text(label));
							});
						}
						let master_id_list = $("#new_masterid option");

						// major kudos to the fine folks at http://stackoverflow.com/questions/45888/what-is-the-most-efficient-way-to-sort-an-html-selects-options-by-value-while for this elegant option list sorting solution!!!
						// p.s. -- Sortable arrays, but no associative keys.  Associative object properties, but object properties can't be sorted... really, JavaScript?  Thanks for the help.</sarcasm>
						master_id_list.sort(function(a,b) {
							if (a.text > b.text) return 1;
							else if (a.text < b.text) return -1;
							else return 0;
						});
						newMasterId.empty();
						newMasterId.append($("<option />").val(-1).text("--"));
						newMasterId.append(master_id_list);
						newMasterId.val(-1);
					});
				</script>
			</div>
            <div class="addnew_field" style="vertical-align: top;"><label class="vocab_add_form" for="new_comment">Append to Comments:</label><br><input class="ui-corner-all" type="text" name="new_comment" id="new_comment" /></div>
	<?php

		}
	?>
		<input type="hidden" name="selected_page" value="<?php echo intval($navSelectedPage); ?>" />
		<input type="hidden" name="submenu" value="<?php echo intval($navSubmenu); ?>" />
		<input type="hidden" name="cat" value="<?php echo intval($navCat); ?>" />
		<input type="hidden" name="subcat" value="<?php echo intval($navSubcat); ?>" />
		<input type="hidden" name="add_flag" value="1" />
		<br><br><button type="submit" name="new_savevocab" id="new_savevocab">Save New <?php echo $_SESSION[EXPORT_SERVERNAME]['vocab_valueset_params'][$_SESSION[EXPORT_SERVERNAME]['vocab_valueset_params']['vocab']]['vocab_verbose']; ?></button>
		<button type="button" name="addnew_cancel" id="addnew_cancel">Cancel</button>
	</form>
</div>


<div class="vocab_paging_top" style="display: none;">
<?php
	if ($numrows > 0) {
		echo "Page: ";
		/******  build the pagination links ******/
		// range of num links to show
		$range = 3;

		// if not on page 1, don't show back links
		if ($currentpage > 1) {
		   // show << link to go back to page 1
		   printf(" <a href=\"%s?currentpage=%s&selected_page=%s&submenu=%s&cat=%s&subcat=%s\">&lt;&lt;</a> ", $webappBaseUrl, "1", $navSelectedPage, $navSubmenu, (($navCat < 2) ? 1 : $navCat), (($navSubcat < 2) ? 1 : $navSubcat));
		   // get previous page num
		   $prevpage = $currentpage - 1;
		   // show < link to go back to 1 page
		   printf(" <a href=\"%s?currentpage=%s&selected_page=%s&submenu=%s&cat=%s&subcat=%s\">&lt;</a> ", $webappBaseUrl, $prevpage, $navSelectedPage, $navSubmenu, (($navCat < 2) ? 1 : $navCat), (($navSubcat < 2) ? 1 : $navSubcat));
		} // end if

		// loop to show links to range of pages around current page
		for ($x = ($currentpage - $range); $x < (($currentpage + $range) + 1); $x++) {
		   // if it's a valid page number...
		   if (($x > 0) && ($x <= $totalpages)) {
			  // if we're on current page...
			  if ($x == $currentpage) {
				 // 'highlight' it but don't make a link
				 printf(" [<b>%s</b>] ", $x);
			  // if not current page...
			  } else {
				 // make it a link
				 printf(" <a href=\"%s?currentpage=%s&selected_page=%s&submenu=%s&cat=%s&subcat=%s\">%s</a> ", $webappBaseUrl, $x, $navSelectedPage, $navSubmenu, (($navCat < 2) ? 1 : $navCat), (($navSubcat < 2) ? 1 : $navSubcat), $x);
			  } // end else
		   } // end if
		} // end for

		// if not on last page, show forward and last page links
		if ($currentpage != $totalpages) {
		   // get next page
		   $nextpage = $currentpage + 1;
			// echo forward link for next page
		   printf(" <a href=\"%s?currentpage=%s&selected_page=%s&submenu=%s&cat=%s&subcat=%s\">&gt;</a> ", $webappBaseUrl, $nextpage, $navSelectedPage, $navSubmenu, (($navCat < 2) ? 1 : $navCat), (($navSubcat < 2) ? 1 : $navSubcat));
		   // echo forward link for lastpage
		   printf(" <a href=\"%s?currentpage=%s&selected_page=%s&submenu=%s&cat=%s&subcat=%s\">&gt;&gt;</a> ", $webappBaseUrl, $totalpages, $navSelectedPage, $navSubmenu, (($navCat < 2) ? 1 : $navCat), (($navSubcat < 2) ? 1 : $navSubcat));
		} // end if
		/****** end build pagination links ******/
	}
?>

</div>

<table id="latestTasks" class="ui-corner-all">
	<caption>
		<?php
			if ($numrows < 1) {
				echo 'No '.  htmlentities($_SESSION[EXPORT_SERVERNAME]['vocab_valueset_params'][$_SESSION[EXPORT_SERVERNAME]['vocab_valueset_params']['vocab']]['vocab_verbose'], ENT_QUOTES, 'UTF-8').' records found!';
			} elseif ($_SESSION[EXPORT_SERVERNAME]['vocab_valueset_params'][$_SESSION[EXPORT_SERVERNAME]['vocab_valueset_params']['vocab']]['page_rows'] > 0) {
				echo intval($offset+1).' - '.((intval($offset+$_SESSION[EXPORT_SERVERNAME]['vocab_valueset_params'][$_SESSION[EXPORT_SERVERNAME]['vocab_valueset_params']['vocab']]['page_rows'])>intval($numrows)) ? intval($numrows) : intval($offset+$_SESSION[EXPORT_SERVERNAME]['vocab_valueset_params'][$_SESSION[EXPORT_SERVERNAME]['vocab_valueset_params']['vocab']]['page_rows'])).' of '.intval($numrows).' records';
			} else {
				echo intval($offset+1).' - '.intval($numrows).' of '.intval($numrows).' records';
			}

			if ($numrows > 0) {
				echo '<div style="border-left: 1px dimgray dotted; display: inline; margin-left: 15px; padding-left: 15px;">';
				echo "Page: ";
				/******  build the pagination links ******/
				// range of num links to show
				$range = 3;

				// if not on page 1, don't show back links
				if ($currentpage > 1) {
				   // show << link to go back to page 1
				   printf(" <a href=\"%s?currentpage=%s&selected_page=%s&submenu=%s&cat=%s&subcat=%s\">&lt;&lt;</a> ", $webappBaseUrl, "1", $navSelectedPage, $navSubmenu, (($navCat < 2) ? 1 : $navCat), (($navSubcat < 2) ? 1 : $navSubcat));
				   // get previous page num
				   $prevpage = $currentpage - 1;
				   // show < link to go back to 1 page
				   printf(" <a href=\"%s?currentpage=%s&selected_page=%s&submenu=%s&cat=%s&subcat=%s\">&lt;</a> ", $webappBaseUrl, $prevpage, $navSelectedPage, $navSubmenu, (($navCat < 2) ? 1 : $navCat), (($navSubcat < 2) ? 1 : $navSubcat));
				} // end if

				// loop to show links to range of pages around current page
				for ($x = ($currentpage - $range); $x < (($currentpage + $range) + 1); $x++) {
				   // if it's a valid page number...
				   if (($x > 0) && ($x <= $totalpages)) {
					  // if we're on current page...
					  if ($x == $currentpage) {
						 // 'highlight' it but don't make a link
						 printf(" [<b>%s</b>] ", $x);
					  // if not current page...
					  } else {
						 // make it a link
						 printf(" <a href=\"%s?currentpage=%s&selected_page=%s&submenu=%s&cat=%s&subcat=%s\">%s</a> ", $webappBaseUrl, $x, $navSelectedPage, $navSubmenu, (($navCat < 2) ? 1 : $navCat), (($navSubcat < 2) ? 1 : $navSubcat), $x);
					  } // end else
				   } // end if
				} // end for

				// if not on last page, show forward and last page links
				if ($currentpage != $totalpages) {
				   // get next page
				   $nextpage = $currentpage + 1;
					// echo forward link for next page
				   printf(" <a href=\"%s?currentpage=%s&selected_page=%s&submenu=%s&cat=%s&subcat=%s\">&gt;</a> ", $webappBaseUrl, $nextpage, $navSelectedPage, $navSubmenu, (($navCat < 2) ? 1 : $navCat), (($navSubcat < 2) ? 1 : $navSubcat));
				   // echo forward link for lastpage
				   printf(" <a href=\"%s?currentpage=%s&selected_page=%s&submenu=%s&cat=%s&subcat=%s\">&gt;&gt;</a> ", $webappBaseUrl, $totalpages, $navSelectedPage, $navSubmenu, (($navCat < 2) ? 1 : $navCat), (($navSubcat < 2) ? 1 : $navSubcat));
				} // end if
				/****** end build pagination links ******/
				echo '</div>';
			}
		?>
		<form name="user_rowselect" id="user_rowselect" method="GET" action="<?php echo $webappBaseUrl; ?>" style="border-left: 1px dimgray dotted; display: inline; margin-left: 15px; padding-left: 15px;">
            <label for="user_rows">Rows per page:</label>
			<select name="user_rows" id="user_rows" class="ui-corner-all">
			<?php
				foreach ($valid_rowsize as $this_rowsize) {
					echo "<option value=\"" . $this_rowsize . "\"" . (($this_rowsize == $_SESSION[EXPORT_SERVERNAME]['vocab_valueset_params'][$_SESSION[EXPORT_SERVERNAME]['vocab_valueset_params']['vocab']]['page_rows']) ? " selected" : "") . ">" . (($this_rowsize > 0) ? $this_rowsize : "All") . "</option>";
				}
			?>
			</select>
            <button id="user_rows_change" title="Set number of rows per page">Update</button>
			<input type="hidden" name="selected_page" value="<?php echo $navSelectedPage; ?>">
			<input type="hidden" name="submenu" value="<?php echo $navSubmenu; ?>">
			<input type="hidden" name="cat" value="<?php echo (($navCat < 2) ? 1 : $navCat); ?>">
			<input type="hidden" name="subcat" value="<?php echo (($navSubcat < 2) ? 1 : $navSubcat); ?>">
		</form>
	</caption>
	<thead>
		<tr>
			<th>Actions</th>
<?php

		foreach ($_SESSION[EXPORT_SERVERNAME]['vocab_valueset_params'][$_SESSION[EXPORT_SERVERNAME]['vocab_valueset_params']['vocab']]['result_cols'] as $headercol => $headername) {
			$sort_indicator = "";
			$sort_text = sprintf("Sort by '%s' [A-Z]", htmlspecialchars($emsaHTMLPurifier->purify($headername)));
			if ($headercol == $_SESSION[EXPORT_SERVERNAME]['vocab_valueset_params'][$_SESSION[EXPORT_SERVERNAME]['vocab_valueset_params']['vocab']]['sort_col']) {
				if ($_SESSION[EXPORT_SERVERNAME]['vocab_valueset_params'][$_SESSION[EXPORT_SERVERNAME]['vocab_valueset_params']['vocab']]['sort_order'] == "ASC") {
					$sort_indicator = "colheader_sort_down";
					$sort_text = sprintf("Sort by '%s' [Z-A]", htmlspecialchars($emsaHTMLPurifier->purify($headername)));
				} else {
					$sort_indicator = "colheader_sort_up";
					$sort_text = sprintf("Sort by '%s' [A-Z]", htmlspecialchars($emsaHTMLPurifier->purify($headername)));
				}
			}

            printf("<th><a class=\"colheader %s\" title=\"%s\" href=\"%s?selected_page=%s&submenu=%s&cat=%s&subcat=%s&sort=%s&order=%s&currentpage=1\">%s</a></th>", $sort_indicator, $sort_text, $webappBaseUrl, $navSelectedPage, $navSubmenu, (($navCat < 2) ? 1 : $navCat), (($navSubcat < 2) ? 1 : $navSubcat), $headercol, ((($headercol == $_SESSION[EXPORT_SERVERNAME]['vocab_valueset_params'][$_SESSION[EXPORT_SERVERNAME]['vocab_valueset_params']['vocab']]['sort_col']) && ($_SESSION[EXPORT_SERVERNAME]['vocab_valueset_params'][$_SESSION[EXPORT_SERVERNAME]['vocab_valueset_params']['vocab']]['sort_order'] == "ASC")) ? "2" : "1"), $headername);
		}

        if (intval($_SESSION[EXPORT_SERVERNAME]['vocab_valueset_params']['vocab']) === 8) {
            foreach ($appList as $appId => $appName) {
                if (($_SESSION[EXPORT_SERVERNAME]['vocab_valueset_params'][$_SESSION[EXPORT_SERVERNAME]['vocab_valueset_params']['vocab']]['sort_col'] == 'app' . (int) $appId . '_coded_value') && ($_SESSION[EXPORT_SERVERNAME]['vocab_valueset_params'][$_SESSION[EXPORT_SERVERNAME]['vocab_valueset_params']['vocab']]['sort_order'] == "ASC")) {
                    $sort_indicator = "colheader_sort_down";
                    $sort_text = sprintf("Sort by '%s Value' [Z-A]", htmlspecialchars($emsaHTMLPurifier->purify($appName)));
                    echo '<th><a class="colheader ' . $sort_indicator . '" title="' . $sort_text . '" href="' . $webappBaseUrl . '?selected_page=' . (int) $navSelectedPage . '&submenu=' . (int) $navSubmenu . '&cat=' . (($navCat < 2) ? 1 : (int) $navCat) . '&subcat=' . (($navSubcat < 2) ? 1 : (int) $navSubcat) . '&sort=app' . (int) $appId . '_coded_value&order=2&currentpage=1">' . htmlspecialchars($emsaHTMLPurifier->purify($appName)) . ' Value</a></th>';
                } elseif ($_SESSION[EXPORT_SERVERNAME]['vocab_valueset_params'][$_SESSION[EXPORT_SERVERNAME]['vocab_valueset_params']['vocab']]['sort_col'] == 'app' . (int) $appId . '_coded_value') {
                    $sort_indicator = "colheader_sort_up";
                    $sort_text = sprintf("Sort by '%s Value' [A-Z]", htmlspecialchars($emsaHTMLPurifier->purify($appName)));
                    echo '<th><a class="colheader ' . $sort_indicator . '" title="' . $sort_text . '" href="' . $webappBaseUrl . '?selected_page=' . (int) $navSelectedPage . '&submenu=' . (int) $navSubmenu . '&cat=' . (($navCat < 2) ? 1 : (int) $navCat) . '&subcat=' . (($navSubcat < 2) ? 1 : (int) $navSubcat) . '&sort=app' . (int) $appId . '_coded_value&order=1&currentpage=1">' . htmlspecialchars($emsaHTMLPurifier->purify($appName)) . ' Value</a></th>';
                } else {
                    $sort_indicator = "";
                    $sort_text = sprintf("Sort by '%s Value' [A-Z]", htmlspecialchars($emsaHTMLPurifier->purify($appName)));
                    echo '<th><a class="colheader ' . $sort_indicator . '" title="' . $sort_text . '" href="' . $webappBaseUrl . '?selected_page=' . (int) $navSelectedPage . '&submenu=' . (int) $navSubmenu . '&cat=' . (($navCat < 2) ? 1 : (int) $navCat) . '&subcat=' . (($navSubcat < 2) ? 1 : (int) $navSubcat) . '&sort=app' . (int) $appId . '_coded_value&order=1&currentpage=1">' . htmlspecialchars($emsaHTMLPurifier->purify($appName)) . ' Value</a></th>';
                }
            }
        }
		echo "</tr></thead><tbody>";

		// go grab our data...
        try {
            $getVocabSql = $selectStmt . ' ' . $fromStmt . ' ' . $whereStmt . ' ' . $order_stmt;

            if ($_SESSION[EXPORT_SERVERNAME]['vocab_valueset_params'][$_SESSION[EXPORT_SERVERNAME]['vocab_valueset_params']['vocab']]['page_rows'] > 0) {
                $getVocabSql .= " LIMIT " . (int) $_SESSION[EXPORT_SERVERNAME]['vocab_valueset_params'][$_SESSION[EXPORT_SERVERNAME]['vocab_valueset_params']['vocab']]['page_rows'] . " OFFSET " . (int) $offset;
            }

            $getVocabStmt = $adminDbConn->prepare($getVocabSql);

            $getVocabStmt->execute($whereArr);

            while ($getVocabRow = $getVocabStmt->fetchObject()) {
                echo "<tr><td nowrap>";
                printf("<button class=\"edit_vocab\" type=\"button\" value=\"%s\" title=\"Edit this vocabulary\">Edit</button>", intval($getVocabRow->id));
                printf("<button class=\"delete_vocab\" type=\"button\" value=\"%s\" title=\"Delete this vocabulary\">Delete</button>", intval($getVocabRow->id));
                echo "</td>";

                foreach ($_SESSION[EXPORT_SERVERNAME]['vocab_valueset_params'][$_SESSION[EXPORT_SERVERNAME]['vocab_valueset_params']['vocab']]['result_cols'] as $colname => $colvalue) {
                    printf("<td class=\"vocab_data_cell mono_prewrap\">%s</td>", htmlspecialchars($emsaHTMLPurifier->purify(trim($getVocabRow->$colname))));
                }

                if (intval($_SESSION[EXPORT_SERVERNAME]['vocab_valueset_params']['vocab']) === 8) {
                    foreach ($appList as $appId => $appName) {
                        $appColName = 'app' . (int) $appId . '_coded_value';
                        printf("<td class=\"vocab_data_cell mono_prewrap\">%s</td>", htmlspecialchars($emsaHTMLPurifier->purify(trim($getVocabRow->$appColName))));
                    }
                }

                echo "</tr>";
            }
        } catch (Throwable $e) {
            ExceptionUtils::logException($e);
            DisplayUtils::drawError('Could not get vocabulary list.');
        }
?>
	</tbody>
</table>

<div class="vocab_paging vocab_paging_bottom">
<?php
	if ($numrows > 0) {
		echo "Page: ";
		/******  build the pagination links ******/
		// range of num links to show
		$range = 3;

		// if not on page 1, don't show back links
		if ($currentpage > 1) {
		   // show << link to go back to page 1
		   printf(" <a href=\"%s?currentpage=%s&selected_page=%s&submenu=%s&cat=%s&subcat=%s\">&lt;&lt;</a> ", $webappBaseUrl, "1", $navSelectedPage, $navSubmenu, (($navCat < 2) ? 1 : $navCat), (($navSubcat < 2) ? 1 : $navSubcat));
		   // get previous page num
		   $prevpage = $currentpage - 1;
		   // show < link to go back to 1 page
		   printf(" <a href=\"%s?currentpage=%s&selected_page=%s&submenu=%s&cat=%s&subcat=%s\">&lt;</a> ", $webappBaseUrl, $prevpage, $navSelectedPage, $navSubmenu, (($navCat < 2) ? 1 : $navCat), (($navSubcat < 2) ? 1 : $navSubcat));
		} // end if

		// loop to show links to range of pages around current page
		for ($x = ($currentpage - $range); $x < (($currentpage + $range) + 1); $x++) {
		   // if it's a valid page number...
		   if (($x > 0) && ($x <= $totalpages)) {
			  // if we're on current page...
			  if ($x == $currentpage) {
				 // 'highlight' it but don't make a link
				 printf(" [<b>%s</b>] ", $x);
			  // if not current page...
			  } else {
				 // make it a link
				 printf(" <a href=\"%s?currentpage=%s&selected_page=%s&submenu=%s&cat=%s&subcat=%s\">%s</a> ", $webappBaseUrl, $x, $navSelectedPage, $navSubmenu, (($navCat < 2) ? 1 : $navCat), (($navSubcat < 2) ? 1 : $navSubcat), $x);
			  } // end else
		   } // end if
		} // end for

		// if not on last page, show forward and last page links
		if ($currentpage != $totalpages) {
		   // get next page
		   $nextpage = $currentpage + 1;
			// echo forward link for next page
		   printf(" <a href=\"%s?currentpage=%s&selected_page=%s&submenu=%s&cat=%s&subcat=%s\">&gt;</a> ", $webappBaseUrl, $nextpage, $navSelectedPage, $navSubmenu, (($navCat < 2) ? 1 : $navCat), (($navSubcat < 2) ? 1 : $navSubcat));
		   // echo forward link for lastpage
		   printf(" <a href=\"%s?currentpage=%s&selected_page=%s&submenu=%s&cat=%s&subcat=%s\">&gt;&gt;</a> ", $webappBaseUrl, $totalpages, $navSelectedPage, $navSubmenu, (($navCat < 2) ? 1 : $navCat), (($navSubcat < 2) ? 1 : $navSubcat));
		} // end if
		/****** end build pagination links ******/
	}
?>
</div>

<div id="confirm_delete_dialog" title="Delete this <?php echo $_SESSION[EXPORT_SERVERNAME]['vocab_valueset_params'][$_SESSION[EXPORT_SERVERNAME]['vocab_valueset_params']['vocab']]['vocab_verbose']; ?> record?">
	<p><span class="ui-icon ui-icon-elrerror" style="float:left; margin:0 7px 50px 0;"></span>This <?php echo $_SESSION[EXPORT_SERVERNAME]['vocab_valueset_params'][$_SESSION[EXPORT_SERVERNAME]['vocab_valueset_params']['vocab']]['vocab_verbose']; ?> will be permanently deleted and cannot be recovered. Are you sure?</p>
</div>

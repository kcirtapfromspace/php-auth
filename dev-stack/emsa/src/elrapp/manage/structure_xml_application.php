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

if (!class_exists('\Udoh\Emsa\Auth\Authenticator') || !\Udoh\Emsa\Auth\Authenticator::userHasPermission(\Udoh\Emsa\Auth\Authenticator::URIGHTS_ADMIN)) {
    ob_end_clean();
    header($_SERVER['SERVER_PROTOCOL'] . " 404 Not Found", TRUE, 404);
    exit;
}
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
            icon: "ui-icon-elrpencil"
        }).next().button({
            icon: "ui-icon-elrclose"
        }).parent().controlgroup();
		
		$("#confirm_delete_dialog").dialog({
			autoOpen: false,
			modal: true,
			draggable: false,
			resizable: false,
			width: 400
		});
		
		$(".delete_lab").on("click", function(e) {
			e.preventDefault();
			var deleteAction = "<?php echo $webappBaseUrl; ?>?selected_page=6&submenu=4&cat=2&subcat=4&delete_id="+$(this).val();


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
			width: 600,
			modal: true
		});
		
		$(".edit_lab").on("click", function(e) {
			e.preventDefault();
			var jsonObj = JSON.parse($(this).val());
			
			if (jsonObj.id) {
				$("#edit_id").val(jsonObj.id);
				$("#edit_element").val(jsonObj.element);
				$("#edit_xpath").val(jsonObj.xpath);
				$("#edit_application").val(jsonObj.app_id);
				$("#edit_category").val(jsonObj.category);
				$("#edit_lookup_operator").val(jsonObj.operator_id);
				$("#edit_master_xpath").val(jsonObj.master_path_id);
				$("#edit_complex_rule_callback").val(jsonObj.complex_rule_callback);

				if (jsonObj.required == "t") {
					$("#edit_required_yes").trigger("click");
				} else {
					$("#edit_required_no").trigger("click");
				}
			
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
		
	});
</script>
<style type="text/css">
    #new_lab_form legend {
        font-weight: 500;
		font-family: 'Oswald', Arial, sans-serif;
		-moz-user-select: none;
		-khtml-user-select: none;
		-webkit-user-select: none;
		user-select: none;
        margin-left: 15px;
        margin-right: 5px;
    }
</style>

<?php

	if (isset($_GET['edit_id'])) {
		// check to see if passed a valid row id...
		$valid_sql = sprintf("SELECT count(id) AS counter FROM %sstructure_path_application WHERE id = %s;", $emsaDbSchemaPrefix, pg_escape_string(intval($_GET['edit_id'])));
		$valid_result = @pg_query($host_pa, $valid_sql) or \Udoh\Emsa\Utils\DisplayUtils::drawError("Unable to save changes to Application XML element.", true);
		$valid_counter = @pg_fetch_result($valid_result, 0, "counter");
		if ($valid_counter != 1) {
			\Udoh\Emsa\Utils\DisplayUtils::drawError("Unable to save changes to mapping -- Application XML element does not exist.");
		} else {
			$edit_sql = sprintf("UPDATE %sstructure_path_application SET element = %s, xpath = %s, app_id = %d, required = %s, structure_lookup_operator_id = %d, structure_path_id = %d, category_application_id = %s, complex_rule_callback = %s WHERE id = %d;",
				$emsaDbSchemaPrefix,
				((strlen(trim($_GET['edit_element'])) > 0) ? "'".pg_escape_string(trim($_GET['edit_element']))."'" : "NULL"),
				((strlen(trim($_GET['edit_xpath'])) > 0) ? "'".pg_escape_string(trim($_GET['edit_xpath']))."'" : "NULL"),
				((intval(trim($_GET['edit_application'])) > 0) ? intval(trim($_GET['edit_application'])) : 1),
				((trim($_GET['edit_required']) == "true") ? "true" : "false"),
				((intval(trim($_GET['edit_lookup_operator'])) > 0) ? intval(trim($_GET['edit_lookup_operator'])) : 1),
				((intval(trim($_GET['edit_master_xpath'])) > 0) ? intval(trim($_GET['edit_master_xpath'])) : -1),
				((intval(trim($_GET['edit_category'])) > 0) ? intval(trim($_GET['edit_category'])) : "NULL"),
				((strlen(trim($_GET['edit_complex_rule_callback'])) > 0) ? "'".pg_escape_string(trim($_GET['edit_complex_rule_callback']))."'" : "NULL"),
				intval(trim($_GET['edit_id']))
			);
			if (@pg_query($host_pa, $edit_sql)) {
				\Udoh\Emsa\Utils\DisplayUtils::drawHighlight("Application XML element successfully updated!", "ui-icon-elrsuccess");
			} else {
				\Udoh\Emsa\Utils\DisplayUtils::drawError("Unable to save changes to Application XML element.");
			}
		}
	} elseif (isset($_GET['delete_id'])) {
		########## delete lab ##########
		
		// check to see if passed a valid row id...
		$valid_sql = sprintf("SELECT count(id) AS counter FROM %sstructure_path_application WHERE id = %s;", $emsaDbSchemaPrefix, pg_escape_string(intval($_GET['delete_id'])));
		$valid_result = @pg_query($host_pa, $valid_sql) or \Udoh\Emsa\Utils\DisplayUtils::drawError("Unable to delete Application XML element.", true);
		$valid_counter = @pg_fetch_result($valid_result, 0, "counter");
		if ($valid_counter != 1) {
			\Udoh\Emsa\Utils\DisplayUtils::drawError("Unable to delete Application XML element -- record not found.");
		} else {
			// everything checks out, commit the delete...
			$delete_sql = sprintf("DELETE FROM ONLY %sstructure_path_application WHERE id = %d;", $emsaDbSchemaPrefix, intval($_GET['delete_id']));
			if (@pg_query($host_pa, $delete_sql)) {
				\Udoh\Emsa\Utils\DisplayUtils::drawHighlight("Application XML element successfully deleted!", "ui-icon-elrsuccess");
			} else {
				\Udoh\Emsa\Utils\DisplayUtils::drawError("Unable to delete Application XML element.");
			}
		}
	} elseif (isset($_GET['add_flag'])) {
		// add new lab
		if (strlen(trim($_GET['new_element'])) > 0) {
			$addlab_sql = sprintf("INSERT INTO %sstructure_path_application (element, xpath, required, app_id, structure_lookup_operator_id, structure_path_id, category_application_id, complex_rule_callback) VALUES (%s, %s, %s, %d, %d, %d, %s, %s)",
				$emsaDbSchemaPrefix,
				"'".pg_escape_string(trim($_GET['new_element']))."'",
				((strlen(trim($_GET['new_xpath'])) > 0) ? "'".pg_escape_string(trim($_GET['new_xpath']))."'" : "NULL"),
				((trim($_GET['new_required']) == "true") ? "true" : "false"),
				((intval(trim($_GET['new_application'])) > 0) ? intval(trim($_GET['new_application'])) : 1),
				((intval(trim($_GET['new_lookup_operator'])) > 0) ? intval(trim($_GET['new_lookup_operator'])) : 1),
				((intval(trim($_GET['new_master_xpath'])) > 0) ? intval(trim($_GET['new_master_xpath'])) : -1),
				((intval(trim($_GET['new_category'])) > 0) ? intval(trim($_GET['new_category'])) : "NULL"),
                ((strlen(trim($_GET['new_complex_rule_callback'])) > 0) ? "'".pg_escape_string(trim($_GET['new_complex_rule_callback']))."'" : "NULL")
			);
			@pg_query($host_pa, $addlab_sql) or \Udoh\Emsa\Utils\DisplayUtils::drawError("Could not add new Application XML element.");
			\Udoh\Emsa\Utils\DisplayUtils::drawHighlight("New Application XML element \"".htmlentities(trim($_GET['new_element']))."\" added successfully!", "ui-icon-elrsuccess");
		} else {
			\Udoh\Emsa\Utils\DisplayUtils::drawError("No Application XML element name specified!  Enter an element name and try again.");
		}
	}

?>

<h1 class="elrhdg"><span class="ui-icon ui-icon-header ui-icon-elrxml"></span>Application-Specific XML Structure & Mapping</h1>

<div class="emsa_search_controls ui-tabs ui-widget">
<button id="addnew_button" title="Add a new Application XML element">Add New Application XML element</button>
</div>

<div id="addnew_form" class="addnew_lab ui-widget ui-widget-content ui-corner-all">
	<div style="clear: both;"><span class="emsa_form_heading">Add New Application XML element:</span><br><br></div>
	<form id="new_lab_form" method="GET">
		<label class="vocab_search_form2" for="new_application">Application:</label>
			<select class="ui-corner-all" name="new_application" id="new_application">
				<option value="0" selected>--</option>
			<?php
				// get list of data types for menu
				$apps_sql = sprintf("SELECT DISTINCT id, app_name FROM %svocab_app ORDER BY app_name;", $emsaDbSchemaPrefix);
				$apps_rs = @pg_query($host_pa, $apps_sql) or \Udoh\Emsa\Utils\DisplayUtils::drawError("Unable to retrieve list of Applications.", true);
				while ($apps_row = pg_fetch_object($apps_rs)) {
					printf("<option value=\"%d\">%s</option>", intval($apps_row->id), htmlentities($apps_row->app_name));
				}
				pg_free_result($apps_rs);
			?>
			</select>
		<br><br>
		<label class="vocab_search_form2" for="new_master_xpath">Map to Master XPath:</label>
			<select class="ui-corner-all" name="new_master_xpath" id="new_master_xpath">
				<option value="0" selected>--</option>
			<?php
				// get list of master xpaths for menu
				$masterpaths_sql = sprintf("SELECT DISTINCT id, xpath FROM %sstructure_path ORDER BY xpath;", $emsaDbSchemaPrefix);
				$masterpaths_rs = @pg_query($host_pa, $masterpaths_sql) or \Udoh\Emsa\Utils\DisplayUtils::drawError("Unable to retrieve list of Master XPaths.", true);
				while ($masterpaths_row = pg_fetch_object($masterpaths_rs)) {
					printf("<option value=\"%d\">%s</option>", intval($masterpaths_row->id), htmlentities($masterpaths_row->xpath));
				}
				pg_free_result($masterpaths_rs);
			?>
			</select>
		<br><br><label class="vocab_search_form2" for="new_element">Field Name:</label><input class="ui-corner-all" type="text" name="new_element" id="new_element" />
		<label class="vocab_search_form2" for="new_xpath">Element XPath:</label><input class="ui-corner-all" type="text" name="new_xpath" id="new_xpath" />
		<label class="vocab_search_form2" for="new_lookup_operator">Lookup Type:</label>
			<select class="ui-corner-all" name="new_lookup_operator" id="new_lookup_operator">
				<option value="0" selected>--</option>
			<?php
				// get list of lookup types for menu
				$lookup_sql = sprintf("SELECT DISTINCT id, label FROM %sstructure_lookup_operator ORDER BY label;", $emsaDbSchemaPrefix);
				$lookup_rs = @pg_query($host_pa, $lookup_sql) or \Udoh\Emsa\Utils\DisplayUtils::drawError("Unable to retrieve list of Lookup Types.", true);
				while ($lookup_row = pg_fetch_object($lookup_rs)) {
					printf("<option value=\"%d\">%s</option>", intval($lookup_row->id), htmlentities($lookup_row->label));
				}
				pg_free_result($lookup_rs);
			?>
			</select>
		<br><br><label class="vocab_search_form2" for="new_complex_rule_callback">Complex Rule Class:</label><input class="ui-corner-all" type="text" name="new_complex_rule_callback" id="new_complex_rule_callback" />
        <br><br><label class="vocab_search_form2" for="new_category">Vocab Category:</label>
			<select class="ui-corner-all" name="new_category" id="new_category">
				<option value="0" selected>--</option>
			<?php
				// get list of categories for menu
				$category_sql = sprintf("SELECT sca.id AS id, sca.app_table AS app_table, sca.app_category AS app_category, a.app_name AS app_name FROM %sstructure_category_application sca INNER JOIN %svocab_app a ON (sca.app_id = a.id) WHERE sca.app_table <> '' ORDER BY app_name, app_table, app_category;", $emsaDbSchemaPrefix, $emsaDbSchemaPrefix);
				$category_rs = @pg_query($host_pa, $category_sql) or \Udoh\Emsa\Utils\DisplayUtils::drawError("Unable to retrieve list of categories.", true);
				while ($category_row = pg_fetch_object($category_rs)) {
					printf("<option value=\"%d\">[%s] %s\%s</option>", intval($category_row->id), htmlentities($category_row->app_name), htmlentities($category_row->app_table), htmlentities($category_row->app_category));
				}
				pg_free_result($category_rs);
			?>
			</select>
		<br><br>
        <fieldset>
            <legend style="font-size: 11pt; position: relative; float: left;">Required?</legend>
			<label class="vocab_search_form2" for="new_required_yes"><input class="edit_radio ui-corner-all" type="radio" name="new_required" id="new_required_yes" value="true" /> Yes</label>
            <label class="vocab_search_form2" for="new_required_no"><input class="edit_radio ui-corner-all" type="radio" name="new_required" id="new_required_no" value="false" /> No</label>
        </fieldset>

		<input type="hidden" name="selected_page" value="<?php echo intval($navSelectedPage); ?>" />
		<input type="hidden" name="submenu" value="<?php echo intval($navSubmenu); ?>" />
		<input type="hidden" name="cat" value="<?php echo intval($navCat); ?>" />
		<input type="hidden" name="subcat" value="<?php echo intval($navSubcat); ?>" />
		<input type="hidden" name="add_flag" value="1" />
		<br><br><button type="submit" name="new_savelab" id="new_savelab">Save New Application XML element</button>
		<button type="button" id="addnew_cancel">Cancel</button>
	</form>
</div>

<div class="lab_results_container ui-widget ui-corner-all">
	<table id="labResults">
		<thead>
			<tr>
				<th>Actions</th>
				<th>Application</th>
				<th>Field Name</th>
				<th>Master XPath</th>
				<th>Lookup Type</th>
				<th>Vocab Category</th>
                <th>Complex Rule Class</th>
				<th>Application XPath</th>
				<th>Required?</th>
			</tr>
		</thead>
		<tbody>

<?php

    try {
        /* @var $adminDbConn PDO */
        /* @var $authClient \Udoh\Emsa\Client\AppClientInterface */
        $appXmlQry = "SELECT spa.id AS id, spa.element AS element, spa.xpath AS xpath, spa.required AS required, spa.structure_lookup_operator_id AS operator_id, spa.structure_path_id AS master_path_id, spa.category_application_id AS category_id, spa.complex_rule_callback AS complex_rule_callback, sca.app_table AS app_table, sca.app_category AS app_category, sp.xpath AS master_path_text, o.label AS lookup_type, a.id AS app_id, a.app_name AS app_name 
                   FROM structure_path_application spa 
                   INNER JOIN vocab_app a ON (a.id = spa.app_id) 
                   LEFT JOIN structure_category_application sca ON ((sca.app_id = a.id) AND (sca.id = spa.category_application_id)) 
                   LEFT JOIN structure_path sp ON (spa.structure_path_id = sp.id) 
                   INNER JOIN structure_lookup_operator o ON (spa.structure_lookup_operator_id = o.id) 
                   WHERE a.id = :authAppId
                   ORDER BY a.app_name, spa.xpath";
        $appXmlStmt = $adminDbConn->prepare($appXmlQry);
        $appXmlStmt->bindValue(':authAppId', $authClient->getAppId(), PDO::PARAM_INT);
        
        if ($appXmlStmt->execute()) {
            while ($appXmlRow = $appXmlStmt->fetchObject()) {
                echo "<tr>";
                echo "<td nowrap class=\"action_col\">";
                unset($appXMLEditParams);
                $appXMLEditParams = array(
                    "id" => intval($appXmlRow->id), 
                    "element" => htmlentities($appXmlRow->element, ENT_QUOTES, "UTF-8"), 
                    "xpath" => htmlentities($appXmlRow->xpath, ENT_QUOTES, "UTF-8"), 
                    "required" => trim($appXmlRow->required), 
                    "app_id" => intval($appXmlRow->app_id), 
                    "operator_id" => intval($appXmlRow->operator_id), 
                    "master_path_id" => intval($appXmlRow->master_path_id),
                    "category" => intval($appXmlRow->category_id),
                    "complex_rule_callback" => htmlentities($appXmlRow->complex_rule_callback, ENT_QUOTES, "UTF-8")
                );
                printf("<button class=\"edit_lab\" type=\"button\" value='%s' title=\"Edit this record\">Edit</button>", json_encode($appXMLEditParams));
                printf("<button class=\"delete_lab\" type=\"button\" value=\"%s\" title=\"Delete this record\">Delete</button>", intval($appXmlRow->id));
                echo "</td>";
                echo "<td>".htmlentities($appXmlRow->app_name, ENT_QUOTES, "UTF-8")."</td>";
                echo "<td>".htmlentities($appXmlRow->element, ENT_QUOTES, "UTF-8")."</td>";
                echo "<td style='font-family: Consolas !important;'>".str_replace("/", "<wbr>/", htmlentities($appXmlRow->master_path_text, ENT_QUOTES, "UTF-8"))."</td>";
                echo "<td>".htmlentities($appXmlRow->lookup_type, ENT_QUOTES, "UTF-8")."</td>";
                echo "<td>".htmlentities($appXmlRow->app_table, ENT_QUOTES, "UTF-8")."\\".htmlentities($appXmlRow->app_category, ENT_QUOTES, "UTF-8")."</td>";
                echo "<td>".htmlentities($appXmlRow->complex_rule_callback, ENT_QUOTES, "UTF-8")."</td>";
                echo "<td style='font-family: Consolas !important;'>".str_replace("/", "<wbr>/", htmlentities($appXmlRow->xpath, ENT_QUOTES, "UTF-8"))."</td>";
                echo "<td>".((trim($appXmlRow->required) == "t") ? "<span class=\"ui-icon ui-icon-elrsuccess\" title=\"Required\"></span>" : "<span class=\"ui-icon ui-icon-elrcancel\" title=\"Not Required\"></span>")."</td>";
                echo "</tr>";
            }
        }
    } catch (Throwable $ex) {
        \Udoh\Emsa\Utils\ExceptionUtils::logException($ex);
    }
?>

		</tbody>
	</table>
	
</div>

<div id="confirm_delete_dialog" title="Delete this Application XML element?">
	<p><span class="ui-icon ui-icon-elrerror" style="float:left; margin:0 7px 50px 0;"></span>This Application XML element will be permanently deleted and cannot be recovered. Are you sure?</p>
</div>

<div id="edit_lab_dialog" title="Edit Application XML element">
	<form id="edit_modal_form" method="GET">
		<label for="edit_application">Application:</label><br>
		<select class="ui-corner-all" style="margin: 0px;" name="edit_application" id="edit_application">
			<option value="0" selected>--</option>
		<?php
			// get list of data types for menu
			$apps_sql = sprintf("SELECT DISTINCT id, app_name FROM %svocab_app ORDER BY app_name;", $emsaDbSchemaPrefix);
			$apps_rs = @pg_query($host_pa, $apps_sql) or \Udoh\Emsa\Utils\DisplayUtils::drawError("Unable to retrieve list of Applications.", true);
			while ($apps_row = pg_fetch_object($apps_rs)) {
				printf("<option value=\"%d\">%s</option>", intval($apps_row->id), htmlentities($apps_row->app_name));
			}
			pg_free_result($apps_rs);
		?>
		</select><br><br>
		<label for="edit_master_xpath">Map to Master XPath:</label><br>
		<select class="ui-corner-all" style="margin: 0px;" name="edit_master_xpath" id="edit_master_xpath">
			<option value="0" selected>--</option>
		<?php
			// get list of master xpaths for menu
			$masterpaths_sql = sprintf("SELECT DISTINCT id, xpath FROM %sstructure_path ORDER BY xpath;", $emsaDbSchemaPrefix);
			$masterpaths_rs = @pg_query($host_pa, $masterpaths_sql) or \Udoh\Emsa\Utils\DisplayUtils::drawError("Unable to retrieve list of Master XPaths.", true);
			while ($masterpaths_row = pg_fetch_object($masterpaths_rs)) {
				printf("<option value=\"%d\">%s</option>", intval($masterpaths_row->id), htmlentities($masterpaths_row->xpath));
			}
			pg_free_result($masterpaths_rs);
		?>
		</select><br><br>
		<label for="edit_element">Field Name:</label><br><input class="ui-corner-all" type="text" name="edit_element" id="edit_element" /><br><br>
		<label for="edit_xpath">Element XPath:</label><br><input class="ui-corner-all" type="text" name="edit_xpath" id="edit_xpath" /><br><br>
		<label for="edit_lookup_operator">Lookup Type:</label><br>
		<select class="ui-corner-all" style="margin: 0px;" name="edit_lookup_operator" id="edit_lookup_operator">
			<option value="0" selected>--</option>
		<?php
			// get list of lookup types for menu
			$lookup_sql = sprintf("SELECT DISTINCT id, label FROM %sstructure_lookup_operator ORDER BY label;", $emsaDbSchemaPrefix);
			$lookup_rs = @pg_query($host_pa, $lookup_sql) or \Udoh\Emsa\Utils\DisplayUtils::drawError("Unable to retrieve list of Lookup Types.", true);
			while ($lookup_row = pg_fetch_object($lookup_rs)) {
				printf("<option value=\"%d\">%s</option>", intval($lookup_row->id), htmlentities($lookup_row->label));
			}
			pg_free_result($lookup_rs);
		?>
		</select><br><br>
        <label for="edit_complex_rule_callback">Complex Rule Class:</label><br><input class="ui-corner-all" type="text" name="edit_complex_rule_callback" id="edit_complex_rule_callback" /><br><br>
		<label for="edit_category">Vocab Category:</label><br>
		<select class="ui-corner-all" style="margin: 0px;" name="edit_category" id="edit_category">
			<option value="0" selected>--</option>
		<?php
			// get list of categories for menu
			$category_sql = sprintf("SELECT sca.id AS id, sca.app_table AS app_table, sca.app_category AS app_category, a.app_name AS app_name FROM %sstructure_category_application sca INNER JOIN %svocab_app a ON (sca.app_id = a.id) WHERE sca.app_table <> '' ORDER BY app_name, app_table, app_category;", $emsaDbSchemaPrefix, $emsaDbSchemaPrefix);
				$category_rs = @pg_query($host_pa, $category_sql) or \Udoh\Emsa\Utils\DisplayUtils::drawError("Unable to retrieve list of categories.", true);
				while ($category_row = pg_fetch_object($category_rs)) {
					printf("<option value=\"%d\">[%s] %s\%s</option>", intval($category_row->id), htmlentities($category_row->app_name), htmlentities($category_row->app_table), htmlentities($category_row->app_category));
				}
				pg_free_result($category_rs);
		?>
		</select><br><br>
		<fieldset>
            <legend>Required?</legend>
			<label for="edit_required_yes"><input class="edit_radio ui-corner-all" type="radio" name="edit_required" id="edit_required_yes" value="true" /> Yes</label>
            <label for="edit_required_no"><input class="edit_radio ui-corner-all" type="radio" name="edit_required" id="edit_required_no" value="false" /> No</label>
        </fieldset>
		<input type="hidden" name="edit_id" id="edit_id" />
		<input type="hidden" name="selected_page" value="<?php echo intval($navSelectedPage); ?>" />
		<input type="hidden" name="submenu" value="<?php echo intval($navSubmenu); ?>" />
		<input type="hidden" name="cat" value="<?php echo intval($navCat); ?>" />
		<input type="hidden" name="subcat" value="<?php echo intval($navSubcat); ?>" />
	</form>
</div>
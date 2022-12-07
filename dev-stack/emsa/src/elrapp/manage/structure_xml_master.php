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
			var deleteAction = "<?php echo $webappBaseUrl; ?>?selected_page=6&submenu=4&cat=2&subcat=2&delete_id="+$(this).val();


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
				$("#edit_category").val(jsonObj.category);
				$("#edit_datatype").val(jsonObj.datatype);
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
		$valid_sql = sprintf("SELECT count(id) AS counter FROM %sstructure_path WHERE id = %s;", $emsaDbSchemaPrefix, pg_escape_string(intval($_GET['edit_id'])));
		$valid_result = @pg_query($host_pa, $valid_sql) or \Udoh\Emsa\Utils\DisplayUtils::drawError("Unable to save changes to Master XML element.", true);
		$valid_counter = @pg_fetch_result($valid_result, 0, "counter");
		if ($valid_counter != 1) {
			\Udoh\Emsa\Utils\DisplayUtils::drawError("Unable to save changes to mapping -- Master XML element does not exist.");
		} else {
			$edit_sql = sprintf("UPDATE %sstructure_path SET element = %s, xpath = %s, data_type_id = %d, required = %s, category_id = %s WHERE id = %d;",
				$emsaDbSchemaPrefix,
				((strlen(trim($_GET['edit_element'])) > 0) ? "'".pg_escape_string(trim($_GET['edit_element']))."'" : "NULL"),
				((strlen(trim($_GET['edit_xpath'])) > 0) ? "'".pg_escape_string(trim($_GET['edit_xpath']))."'" : "NULL"),
				((intval(trim($_GET['edit_datatype'])) > 0) ? intval(trim($_GET['edit_datatype'])) : -1),
				((trim($_GET['edit_required']) == "true") ? "true" : "false"),
				((intval(trim($_GET['edit_category'])) > 0) ? intval(trim($_GET['edit_category'])) : "NULL"),
				intval(trim($_GET['edit_id']))
			);
			if (@pg_query($host_pa, $edit_sql)) {
				\Udoh\Emsa\Utils\DisplayUtils::drawHighlight("Master XML element successfully updated!", "ui-icon-elrsuccess");
			} else {
				\Udoh\Emsa\Utils\DisplayUtils::drawError("Unable to save changes to Master XML element.");
			}
		}
	} elseif (isset($_GET['delete_id'])) {
		########## delete lab ##########
		
		// check to see if passed a valid row id...
		$valid_sql = sprintf("SELECT count(id) AS counter FROM %sstructure_path WHERE id = %s;", $emsaDbSchemaPrefix, pg_escape_string(intval($_GET['delete_id'])));
		$valid_result = @pg_query($host_pa, $valid_sql) or \Udoh\Emsa\Utils\DisplayUtils::drawError("Unable to delete Master XML element.", true);
		$valid_counter = @pg_fetch_result($valid_result, 0, "counter");
		if ($valid_counter != 1) {
			\Udoh\Emsa\Utils\DisplayUtils::drawError("Unable to delete Master XML element -- record not found.");
		} else {
			// check for alias labs that depend on this row, throw a dependency warning instead of deleting...
			$dependency_sql = sprintf("SELECT (SELECT count(id) FROM %sstructure_path_rule WHERE path_id = %d)+(SELECT count(id) FROM %sstructure_path_application WHERE structure_path_id = %d) AS counter;", $emsaDbSchemaPrefix, pg_escape_string(intval($_GET['delete_id'])), $emsaDbSchemaPrefix, pg_escape_string(intval($_GET['delete_id'])));
			$dependency_result = @pg_query($host_pa, $dependency_sql) or \Udoh\Emsa\Utils\DisplayUtils::drawError("Unable to delete Master XML element.", true);
			$dependency_count = @pg_fetch_result($dependency_result, 0, "counter");
			if ($dependency_count > 0) {
				\Udoh\Emsa\Utils\DisplayUtils::drawError("Unable to delete Master XML Element -- ".intval($dependency_count)." Application XML Structure and/or XML Rule entries use this XML Element Path.  Please resolve these dependency conflicts first and try again.");
			} else {
				// everything checks out, commit the delete...
				$delete_sql = sprintf("DELETE FROM ONLY %sstructure_path WHERE id = %d;", $emsaDbSchemaPrefix, intval($_GET['delete_id']));
				if (@pg_query($host_pa, $delete_sql)) {
					\Udoh\Emsa\Utils\DisplayUtils::drawHighlight("Master XML element successfully deleted!", "ui-icon-elrsuccess");
				} else {
					\Udoh\Emsa\Utils\DisplayUtils::drawError("Unable to delete Master XML element.");
				}
			}
		}
	} elseif (isset($_GET['add_flag'])) {
		// add new lab
		if (strlen(trim($_GET['new_element'])) > 0) {
			$addlab_sql = sprintf("INSERT INTO %sstructure_path (element, xpath, data_type_id, required, category_id) VALUES (%s, %s, %d, %s, %s)",
				$emsaDbSchemaPrefix,
				"'".pg_escape_string(trim($_GET['new_element']))."'",
				((strlen(trim($_GET['new_xpath'])) > 0) ? "'".pg_escape_string(trim($_GET['new_xpath']))."'" : "NULL"),
				((intval(trim($_GET['new_datatype'])) > 0) ? intval(trim($_GET['new_datatype'])) : -1),
				((trim($_GET['new_required']) == "true") ? "true" : "false"),
				((intval(trim($_GET['new_category'])) > 0) ? intval(trim($_GET['new_category'])) : "NULL")
			);
			@pg_query($host_pa, $addlab_sql) or \Udoh\Emsa\Utils\DisplayUtils::drawError("Could not add new Master XML element.");
			\Udoh\Emsa\Utils\DisplayUtils::drawHighlight("New Master XML element \"".htmlentities(trim($_GET['new_element']))."\" added successfully!", "ui-icon-elrsuccess");
		} else {
			\Udoh\Emsa\Utils\DisplayUtils::drawError("No Master XML element name specified!  Enter an element name and try again.");
		}
	}

?>

<h1 class="elrhdg"><span class="ui-icon ui-icon-header ui-icon-elrxml"></span>Master XML Structure</h1>

<div class="emsa_search_controls ui-tabs ui-widget">
<button id="addnew_button" title="Add a new Master XML element">Add New Master XML Element</button>
</div>

<div id="addnew_form" class="addnew_lab ui-widget ui-widget-content ui-corner-all">
	<div style="clear: both;"><span class="emsa_form_heading">Add New Master XML Element:</span><br><br></div>
	<form id="new_lab_form" method="GET">
		<label class="vocab_search_form2" for="new_element">Field Name:</label><input class="ui-corner-all" type="text" name="new_element" id="new_element" />
		<label class="vocab_search_form2" for="new_xpath">Element XPath:</label><input class="ui-corner-all" type="text" name="new_xpath" id="new_xpath" />
		<br><br><label class="vocab_search_form2" for="new_datatype">Data Type:</label>
			<select class="ui-corner-all" name="new_datatype" id="new_datatype">
				<option value="0" selected>--</option>
			<?php
				// get list of data types for menu
				$datatype_sql = sprintf("SELECT DISTINCT id, label FROM %sstructure_data_type ORDER BY label;", $emsaDbSchemaPrefix);
				$datatype_rs = @pg_query($host_pa, $datatype_sql) or \Udoh\Emsa\Utils\DisplayUtils::drawError("Unable to retrieve list of data types.", true);
				while ($datatype_row = pg_fetch_object($datatype_rs)) {
					printf("<option value=\"%d\">%s</option>", intval($datatype_row->id), htmlentities($datatype_row->label));
				}
				pg_free_result($datatype_rs);
			?>
			</select>
		<label class="vocab_search_form2" for="new_category">Vocab Category:</label>
			<select class="ui-corner-all" name="new_category" id="new_category">
				<option value="0" selected>--</option>
			<?php
				// get list of categories for menu
				$category_sql = sprintf("SELECT DISTINCT id, label FROM %sstructure_category ORDER BY label;", $emsaDbSchemaPrefix);
				$category_rs = @pg_query($host_pa, $category_sql) or \Udoh\Emsa\Utils\DisplayUtils::drawError("Unable to retrieve list of categories.", true);
				while ($category_row = pg_fetch_object($category_rs)) {
					printf("<option value=\"%d\">%s</option>", intval($category_row->id), htmlentities($category_row->label));
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
		<br><br><button type="submit" name="new_savelab" id="new_savelab">Save New Master XML Element</button>
		<button type="button" id="addnew_cancel">Cancel</button>
	</form>
</div>

<div class="lab_results_container ui-widget ui-corner-all">
	<table id="labResults">
		<thead>
			<tr>
				<th>Actions</th>
				<th>Field Name</th>
				<th>Element XPath</th>
				<th>Category</th>
				<th>Data Type</th>
				<th>Required?</th>
			</tr>
		</thead>
		<tbody>

<?php
	
	$xml_qry = sprintf("SELECT xm.id AS id, xm.element AS element, xm.xpath AS xpath, xm.category_id AS category_id, xm.required AS required, xt.id AS datatype_id, xt.label AS datatype, xc.label AS category_label FROM %sstructure_path xm LEFT JOIN %sstructure_data_type xt ON (xm.data_type_id = xt.id) LEFT JOIN %sstructure_category xc ON (xm.category_id = xc.id) ORDER BY xm.xpath", $emsaDbSchemaPrefix, $emsaDbSchemaPrefix, $emsaDbSchemaPrefix);
	$xml_rs = @pg_query($host_pa, $xml_qry) or die("Could not connect to database: ".pg_last_error());
	
	while ($xml_row = @pg_fetch_object($xml_rs)) {
		echo "<tr>";
		printf("<td class=\"action_col\">");
		unset($edit_lab_params);
		$edit_lab_params = array(
			"id" => intval($xml_row->id), 
			"element" => htmlentities($xml_row->element, ENT_QUOTES, "UTF-8"), 
			"xpath" => htmlentities($xml_row->xpath, ENT_QUOTES, "UTF-8"), 
			"category" => htmlentities($xml_row->category_id, ENT_QUOTES, "UTF-8"), 
			"datatype" => intval($xml_row->datatype_id), 
			"required" => trim($xml_row->required)
		);
		printf("<button class=\"edit_lab\" type=\"button\" value='%s' title=\"Edit this record\">Edit</button>", json_encode($edit_lab_params));
		printf("<button class=\"delete_lab\" type=\"button\" value=\"%s\" title=\"Delete this record\">Delete</button>", $xml_row->id);
		echo "</td>";
		echo "<td>".htmlentities($xml_row->element)."</td>";
		echo "<td style='font-family: Consolas !important;'>".htmlentities($xml_row->xpath)."</td>";
		echo "<td>".htmlentities($xml_row->category_label)."</td>";
		echo "<td>".htmlentities($xml_row->datatype)."</td>";
		
		echo "<td>".((trim($xml_row->required) == "t") ? "<span class=\"ui-icon ui-icon-elrsuccess\" title=\"Required\"></span>" : "<span class=\"ui-icon ui-icon-elrcancel\" title=\"Not Required\"></span>")."</td>";
		echo "</tr>";
	}
	
	pg_free_result($xml_rs);

?>

		</tbody>
	</table>
	
</div>

<div id="confirm_delete_dialog" title="Delete this Master XML Element?">
	<p><span class="ui-icon ui-icon-elrerror" style="float:left; margin:0 7px 50px 0;"></span>This Master XML element will be permanently deleted and cannot be recovered. Are you sure?</p>
</div>

<div id="edit_lab_dialog" title="Edit Master XML Element">
	<form id="edit_modal_form" method="GET">
		<label for="edit_element">Field Name:</label><br><input class="ui-corner-all" type="text" name="edit_element" id="edit_element" /><br><br>
		<label for="edit_xpath">Element XPath:</label><br><input class="ui-corner-all" type="text" name="edit_xpath" id="edit_xpath" /><br><br>
		<label for="edit_datatype">Data Type:</label><br>
		<select class="ui-corner-all" style="margin: 0px;" name="edit_datatype" id="edit_datatype">
			<option value="0" selected>--</option>
		<?php
			// get list of data types for menu
			$datatype_sql = sprintf("SELECT DISTINCT id, label FROM %sstructure_data_type ORDER BY label;", $emsaDbSchemaPrefix);
			$datatype_rs = @pg_query($host_pa, $datatype_sql) or \Udoh\Emsa\Utils\DisplayUtils::drawError("Unable to retrieve list of data types.", true);
			while ($datatype_row = pg_fetch_object($datatype_rs)) {
				printf("<option value=\"%d\">%s</option>", intval($datatype_row->id), htmlentities($datatype_row->label));
			}
			pg_free_result($datatype_rs);
		?>
		</select><br><br>
		<label for="edit_category">Vocab Category:</label><br>
		<select class="ui-corner-all" style="margin: 0px;" name="edit_category" id="edit_category">
			<option value="0" selected>--</option>
		<?php
			// get list of data types for menu
			$category_sql = sprintf("SELECT DISTINCT id, label FROM %sstructure_category ORDER BY label;", $emsaDbSchemaPrefix);
			$category_rs = @pg_query($host_pa, $category_sql) or \Udoh\Emsa\Utils\DisplayUtils::drawError("Unable to retrieve list of categories.", true);
			while ($category_row = pg_fetch_object($category_rs)) {
				printf("<option value=\"%d\">%s</option>", intval($category_row->id), htmlentities($category_row->label));
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
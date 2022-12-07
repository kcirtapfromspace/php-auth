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
			resizable: false
		});
		
		$(".delete_lab").on("click", function(e) {
			e.preventDefault();
			var deleteAction = "<?php echo $webappBaseUrl; ?>?selected_page=6&submenu=4&cat=6&delete_id="+$(this).val();


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
				var appvalues_class = ".appvalues_"+jsonObj.id;
				
				$("#edit_id").val(jsonObj.id);
				$("#edit_label").val(jsonObj.label);
				
				$(appvalues_class).each(function(i, obj) {
					thisJsonObj = JSON.parse($(obj).val());
					$("#edit_apptable_"+thisJsonObj.app_id).val(thisJsonObj.app_table);
					$("#edit_appcategory_"+thisJsonObj.app_id).val(thisJsonObj.app_category);
				});
				
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

<?php

	if (isset($_GET['edit_id'])) {
		// check to see if passed a valid row id...
		$valid_sql = sprintf("SELECT count(id) AS counter FROM %sstructure_category WHERE id = %s;", $emsaDbSchemaPrefix, pg_escape_string(intval($_GET['edit_id'])));
		$valid_result = @pg_query($host_pa, $valid_sql) or \Udoh\Emsa\Utils\DisplayUtils::drawError("Unable to save changes to Vocabulary Category.", true);
		$valid_counter = @pg_fetch_result($valid_result, 0, "counter");
		if ($valid_counter != 1) {
			\Udoh\Emsa\Utils\DisplayUtils::drawError("Unable to save changes to Vocabulary Category -- record does not exist.");
		} else {
			// save changes to master vocab categories first...
			$edit_sql = sprintf("UPDATE %sstructure_category SET label = %s WHERE id = %d;",
				$emsaDbSchemaPrefix,
				((strlen(trim($_GET['edit_label'])) > 0) ? "'".pg_escape_string(trim($_GET['edit_label']))."'" : "NULL"),
				intval(trim($_GET['edit_id']))
			);
			if (@pg_query($host_pa, $edit_sql)) {
				\Udoh\Emsa\Utils\DisplayUtils::drawHighlight("Master Vocabulary Category changes successfully saved!", "ui-icon-elrsuccess");
			} else {
				\Udoh\Emsa\Utils\DisplayUtils::drawError("Unable to save changes to Master Vocabulary.");
			}
			
			// then, save app-specific info per application...
			if (isset($_GET['apps']) && is_array($_GET['apps'])) {
				foreach ($_GET['apps'] as $app_id => $app_name) {
					$edit_sql = sprintf("UPDATE %sstructure_category_application SET app_table = %s, app_category = %s WHERE app_id = %d AND category_id = %d;",
						$emsaDbSchemaPrefix,
						((strlen(trim($_GET['edit_apptable'][$app_id])) > 0) ? "'".pg_escape_string(trim($_GET['edit_apptable'][$app_id]))."'" : "NULL"),
						((strlen(trim($_GET['edit_appcategory'][$app_id])) > 0) ? "'".pg_escape_string(trim($_GET['edit_appcategory'][$app_id]))."'" : "NULL"),
						intval($app_id),
						intval(trim($_GET['edit_id']))
					);
					if (@pg_query($host_pa, $edit_sql)) {
						\Udoh\Emsa\Utils\DisplayUtils::drawHighlight(\Udoh\Emsa\Utils\DisplayUtils::xSafe($app_name)." Vocabulary Category changes successfully saved!", "ui-icon-elrsuccess");
					} else {
						\Udoh\Emsa\Utils\DisplayUtils::drawError("Unable to save changes to ".\Udoh\Emsa\Utils\DisplayUtils::xSafe($app_name)." Vocabulary.");
					}
				}
			}
		}
	} elseif (isset($_GET['delete_id'])) {
		/**
		 * Delete Category
		 */
		// check to see if passed a valid row id...
		$valid_sql = sprintf("SELECT count(id) AS counter FROM %sstructure_category WHERE id = %s;", $emsaDbSchemaPrefix, pg_escape_string(intval($_GET['delete_id'])));
		$valid_result = @pg_query($host_pa, $valid_sql) or \Udoh\Emsa\Utils\DisplayUtils::drawError("Unable to delete Vocabulary Category.", true);
		$valid_counter = @pg_fetch_result($valid_result, 0, "counter");
		if ($valid_counter != 1) {
			\Udoh\Emsa\Utils\DisplayUtils::drawError("Unable to delete Vocabulary Category -- record not found.");
		} else {
			// everything checks out, commit the delete...
			$delete_sql = sprintf("BEGIN;\nDELETE FROM ONLY %sstructure_category_application WHERE category_id = %d;\nDELETE FROM ONLY %sstructure_category WHERE id = %d;\nCOMMIT;", $emsaDbSchemaPrefix, intval($_GET['delete_id']), $emsaDbSchemaPrefix, intval($_GET['delete_id']));
			if (@pg_query($host_pa, $delete_sql)) {
				\Udoh\Emsa\Utils\DisplayUtils::drawHighlight("Vocabulary Category successfully deleted!", "ui-icon-elrsuccess");
			} else {
				\Udoh\Emsa\Utils\DisplayUtils::drawError("Unable to delete Vocabulary Category.");
			}
		}
	} elseif (isset($_GET['add_flag'])) {
		/**
		 * Add New Category
		 */
		$valid_add = FALSE;
		$valid_label = ((isset($_GET['new_label'])) && (ctype_print(trim($_GET['new_label']))) && (strlen(trim($_GET['new_label'])) > 0));
		$valid_apptable = ((isset($_GET['new_apptable'])) && (is_array($_GET['new_apptable'])));
		$valid_appcategory = ((isset($_GET['new_appcategory'])) && (is_array($_GET['new_appcategory'])));
		$valid_add = $valid_label && $valid_apptable && $valid_appcategory;
		if ($valid_add) {
			// check to make sure all app values passed correspond to actual configured applications
			unset($new_apps);
			foreach ($_GET['new_apptable'] as $new_appid => $new_appvalue) {
				$appvalues_sql = sprintf("SELECT id, app_name FROM %svocab_app WHERE id = %d", $emsaDbSchemaPrefix, intval($new_appid));
				if ($appvalues_row = @pg_fetch_object(@pg_query($host_pa, $appvalues_sql))) {
					$new_apps[$appvalues_row->id] = array("app_name" => \Udoh\Emsa\Utils\DisplayUtils::xSafe($appvalues_row->app_name, 'UTF-8', false), "app_value" => trim($new_appvalue));
				}
			}
			foreach ($_GET['new_appcategory'] as $new_apppathid => $new_apppathvalue) {
				$apppathvalues_sql = sprintf("SELECT id, app_name FROM %svocab_app WHERE id = %d", $emsaDbSchemaPrefix, intval($new_apppathid));
				if ($apppathvalues_row = @pg_fetch_object(@pg_query($host_pa, $apppathvalues_sql))) {
					$new_apps[$apppathvalues_row->id]['app_path'] = trim($new_apppathvalue);
				}
			}
			if (isset($new_apps)) {
				// insert master values & get new master_id
				$insertmaster_sql = sprintf("INSERT INTO %sstructure_category (label) VALUES (%s); 
					SELECT Currval('%sstructure_category_id_seq') AS last_category_id LIMIT 1;", 
					$emsaDbSchemaPrefix,
					((strlen(trim($_GET['new_label'])) > 0) ? "'".pg_escape_string(trim($_GET['new_label']))."'" : "NULL"),
					$emsaDbSchemaPrefix);
				$insertmaster_rs = @pg_query($host_pa, $insertmaster_sql);
				if ($insertmaster_row = @pg_fetch_object($insertmaster_rs)) {
					\Udoh\Emsa\Utils\DisplayUtils::drawHighlight("Vocabulary Category added!", "ui-icon-elrsuccess");
					$this_masterid = $insertmaster_row->last_category_id;
					
					foreach ($new_apps as $this_app_id => $this_app_data) {
						// insert app-specific values
						$insertappvalue_sql = sprintf("INSERT INTO %sstructure_category_application (app_id, category_id, app_table, app_category) VALUES (%d, %d, '%s', '%s');", 
							$emsaDbSchemaPrefix,
							intval($this_app_id),
							intval($this_masterid),
							pg_escape_string(trim($this_app_data['app_value'])),
							pg_escape_string(trim($this_app_data['app_path'])));
						if (@pg_query($host_pa, $insertappvalue_sql)) {
							\Udoh\Emsa\Utils\DisplayUtils::drawHighlight(sprintf("%s value added!", $this_app_data['app_name']), "ui-icon-elrsuccess");
						} else {
							\Udoh\Emsa\Utils\DisplayUtils::drawError(sprintf("Could not insert value for %s", $this_app_data['app_name']));
						}
					}
				} else {
					\Udoh\Emsa\Utils\DisplayUtils::drawError("Could not add new Vocabulary Category");
				}
			} else {
				\Udoh\Emsa\Utils\DisplayUtils::drawError("Could not add new Vocabulary Category:  Application(s) not found");
			}
		} else {
			\Udoh\Emsa\Utils\DisplayUtils::drawError("Could not add new Vocabulary Category");
		}
	}

?>

<h1 class="elrhdg"><span class="ui-icon ui-icon-header ui-icon-elrcategory"></span>Vocabulary Categories</h1>

<div class="emsa_search_controls ui-tabs ui-widget">
<button id="addnew_button" title="Add a new Application XML element">Add New Vocabulary Category</button>
</div>

<div id="addnew_form" class="addnew_lab ui-widget ui-widget-content ui-corner-all">
	<div style="clear: both;"><span class="emsa_form_heading">Add New Category:</span><br><br></div>
	<form id="new_vocab_form" method="GET">
	
		<div class="addnew_field"><label class="vocab_add_form" for="new_label">Category Label:</label><br><input class="ui-corner-all" type="text" name="new_label" id="new_label" /></div>
		
	<?php
		// draw app-specific value input for each configured app
		$newapp_sql = sprintf("SELECT id, app_name FROM %svocab_app ORDER BY app_name;", $emsaDbSchemaPrefix);
		$newapp_result = @pg_query($host_pa, $newapp_sql) or \Udoh\Emsa\Utils\DisplayUtils::drawError("Unable to retrieve list of Applications.", true);
		while ($newapp_row = pg_fetch_object($newapp_result)) {
			echo "<div class=\"add-form-divider\"></div>";
			printf("<div class=\"addnew_field\"><label class=\"vocab_add_form\" for=\"new_apptable_%d\">%s Lookup Table:</label><br><input class=\"ui-corner-all\" type=\"text\" name=\"new_apptable[%d]\" id=\"new_apptable_%d\" /></div>", 
				intval($newapp_row->id),
				\Udoh\Emsa\Utils\DisplayUtils::xSafe(trim($newapp_row->app_name)),
				intval($newapp_row->id),
				intval($newapp_row->id));
			printf("<div class=\"addnew_field\"><label class=\"vocab_add_form\" for=\"new_appcategory_%d\">%s Lookup Category:</label><br><input class=\"ui-corner-all\" type=\"text\" name=\"new_appcategory[%d]\" id=\"new_appcategory_%d\" /></div>", 
				intval($newapp_row->id),
				\Udoh\Emsa\Utils\DisplayUtils::xSafe(trim($newapp_row->app_name)),
				intval($newapp_row->id),
				intval($newapp_row->id));
		}
		pg_free_result($newapp_result);
	?>
		<input type="hidden" name="selected_page" value="<?php echo intval($navSelectedPage); ?>" />
		<input type="hidden" name="submenu" value="<?php echo intval($navSubmenu); ?>" />
		<input type="hidden" name="cat" value="<?php echo intval($navCat); ?>" />
		<input type="hidden" name="add_flag" value="1" />
		<br><br><button type="submit" name="new_savelab" id="new_savelab">Save New Category</button>
		<button type="button" name="addnew_cancel" id="addnew_cancel">Cancel</button>
	</form>
</div>

<div class="lab_results_container ui-widget ui-corner-all">
	<table id="labResults">
		<thead>
			<tr>
				<th>Actions</th>
				<th>Category</th>
			<?php
				// per configured application, draw a 'lookup info' column...
				$app_sql = "SELECT id, app_name FROM ".$emsaDbSchemaPrefix."vocab_app ORDER BY app_name;";
				$app_rs = @pg_query($host_pa, $app_sql);
				if ($app_rs) {
					while ($app_row = @pg_fetch_object($app_rs)) {
						echo "<th>".\Udoh\Emsa\Utils\DisplayUtils::xSafe($app_row->app_name)." Lookup Info</th>";
					}
				} else {
					\Udoh\Emsa\Utils\DisplayUtils::drawError("Unable to retrieve list of Applications.");
				}
				@pg_free_result($app_rs);
				unset($app_row);
			?>
			</tr>
		</thead>
		<tbody>

<?php
	
	$cat_qry = sprintf("SELECT sc.id AS id, sc.label AS label
		FROM %sstructure_category sc 
		ORDER BY sc.label", $emsaDbSchemaPrefix, $emsaDbSchemaPrefix);
	$cat_rs = @pg_query($host_pa, $cat_qry) or die("Could not connect to database: ".pg_last_error());
	
	while ($cat_row = @pg_fetch_object($cat_rs)) {
		echo "<tr>";
		echo "<td nowrap class=\"action_col\">";
		unset($edit_lab_params);
		$edit_lab_params = array(
			"id" => intval($cat_row->id), 
			"label" => \Udoh\Emsa\Utils\DisplayUtils::xSafe($cat_row->label)
		);
		printf("<button class=\"edit_lab\" type=\"button\" value='%s' title=\"Edit this record\">Edit</button>", json_encode($edit_lab_params));
		printf("<button class=\"delete_lab\" type=\"button\" value=\"%s\" title=\"Delete this record\">Delete</button>", intval($cat_row->id));
		echo "</td>";
		echo "<td>".\Udoh\Emsa\Utils\DisplayUtils::xSafe($cat_row->label)."</td>";
		// per configured application, draw a 'lookup info' column...
		$app_sql = "SELECT id, app_name FROM ".$emsaDbSchemaPrefix."vocab_app ORDER BY app_name;";
		$app_rs = @pg_query($host_pa, $app_sql);
		if ($app_rs) {
			while ($app_row = @pg_fetch_object($app_rs)) {
				$category_app_sql = "SELECT app_table, app_category FROM ".$emsaDbSchemaPrefix."structure_category_application WHERE app_id = ".intval($app_row->id)." AND category_id = ".intval($cat_row->id).";";
				$category_app_rs = @pg_query($host_pa, $category_app_sql);
				if ($category_app_rs) {
					echo "<td><strong>Table:</strong> ".\Udoh\Emsa\Utils\DisplayUtils::xSafe(@pg_fetch_result($category_app_rs, 0, "app_table"))."<br><strong>Category:</strong> ".\Udoh\Emsa\Utils\DisplayUtils::xSafe(@pg_fetch_result($category_app_rs, 0, "app_category"));
					echo "<input type=\"hidden\" class=\"appvalues_".intval($cat_row->id)."\" value='" . json_encode(array("app_id" => intval($app_row->id), "app_table" => @pg_fetch_result($category_app_rs, 0, "app_table"), "app_category" => @pg_fetch_result($category_app_rs, 0, "app_category"))) . "'/>";
					echo "</td>";
				} else {
					echo "<td>Could not retrieve data</td>";
				}
				@pg_free_result($category_app_rs);
			}
		} else {
			\Udoh\Emsa\Utils\DisplayUtils::drawError("Unable to retrieve list of Applications.");
		}
		@pg_free_result($app_rs);
		unset($app_row);
		echo "</tr>";
	}
	
	pg_free_result($cat_rs);

?>

		</tbody>
	</table>
	
</div>

<div id="confirm_delete_dialog" title="Delete this Category?">
	<p><span class="ui-icon ui-icon-elrerror" style="float:left; margin:0 7px 50px 0;"></span>This Vocabulary Category will be permanently deleted and cannot be recovered. Are you sure?</p>
</div>

<div id="edit_lab_dialog" title="Edit Application XML element">
	<form id="edit_modal_form" method="GET">
		<label for="edit_label">Category Label:</label><br><input class="ui-corner-all" type="text" name="edit_label" id="edit_label" /><br><br>
		
		<?php
			// per configured application, draw a 'lookup info' column...
			$app_sql = "SELECT id, app_name FROM ".$emsaDbSchemaPrefix."vocab_app ORDER BY app_name;";
			$app_rs = @pg_query($host_pa, $app_sql);
			if ($app_rs) {
				while ($app_row = @pg_fetch_object($app_rs)) {
					echo "<hr>";
					echo "<label for=\"edit_apptable_".intval($app_row->id)."\">".\Udoh\Emsa\Utils\DisplayUtils::xSafe($app_row->app_name)." Lookup Table:</label><br><input class=\"ui-corner-all\" type=\"text\" name=\"edit_apptable[".intval($app_row->id)."]\" id=\"edit_apptable_".intval($app_row->id)."\" /><br><br>";
					echo "<label for=\"edit_appcategory_".intval($app_row->id)."\">".\Udoh\Emsa\Utils\DisplayUtils::xSafe($app_row->app_name)." Lookup Category:</label><br><input class=\"ui-corner-all\" type=\"text\" name=\"edit_appcategory[".intval($app_row->id)."]\" id=\"edit_appcategory_".intval($app_row->id)."\" /><br><br>";
					echo "<input type=\"hidden\" name=\"apps[".intval($app_row->id)."]\" id=\"apps_".intval($app_row->id)."\" value=\"".\Udoh\Emsa\Utils\DisplayUtils::xSafe($app_row->app_name)."\" />";
				}
			} else {
				\Udoh\Emsa\Utils\DisplayUtils::drawError("Unable to retrieve list of Applications.");
			}
			@pg_free_result($app_rs);
			unset($app_row);
		?>
		
		<input type="hidden" name="edit_id" id="edit_id" />
		<input type="hidden" name="selected_page" value="<?php echo intval($navSelectedPage); ?>" />
		<input type="hidden" name="submenu" value="<?php echo intval($navSubmenu); ?>" />
		<input type="hidden" name="cat" value="<?php echo intval($navCat); ?>" />
	</form>
</div>
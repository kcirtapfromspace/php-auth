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
			$("#new_path").trigger("focus");
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
		
		$(".delete_lab").button({
            icon: "ui-icon-elrclose"
        });
		
		$("#confirm_delete_dialog").dialog({
			autoOpen: false,
			modal: true,
			draggable: false,
			resizable: false
		});
		
		$(".delete_lab").on("click", function(e) {
			e.preventDefault();
			var deleteAction = "<?php echo $webappBaseUrl; ?>?selected_page=6&submenu=4&cat=2&subcat=3&delete_id="+$(this).val();


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
		
	});
	
	function edit_lab(rule_id, path, operator, operand_type, operand, sequence, and_or) {
		$("#edit_id").val(rule_id);
		$("#edit_path").val(path);
		$("#edit_operator").val(operator);
		$("#edit_operand_type").val(operand_type);
		$("#edit_operand_value").val(operand);
		$("#edit_sequence").val(sequence);
		$("#edit_and_or_operator").val(and_or);
		
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
	};
</script>

<?php

	if (isset($_GET['edit_id'])) {
		// check to see if passed a valid row id...
		$valid_sql = sprintf("SELECT count(id) AS counter FROM %sstructure_path_rule WHERE id = %s;", $emsaDbSchemaPrefix, pg_escape_string(intval($_GET['edit_id'])));
		$valid_result = @pg_query($host_pa, $valid_sql) or \Udoh\Emsa\Utils\DisplayUtils::drawError("Unable to save changes to XML Rule.", true);
		$valid_counter = @pg_fetch_result($valid_result, 0, "counter");
		if ($valid_counter != 1) {
			\Udoh\Emsa\Utils\DisplayUtils::drawError("Unable to save changes to rules -- XML Rule does not exist.");
		} else {
			$edit_sql = sprintf("UPDATE %sstructure_path_rule SET path_id = %d, operator_id = %d, operand_type_id = %d, operand_value = %s, sequence = %d, and_or_operator_id = %d WHERE id = %d;",
				$emsaDbSchemaPrefix,
				((intval(trim($_GET['edit_path'])) > 0) ? intval(trim($_GET['edit_path'])) : -1),
				((intval(trim($_GET['edit_operator'])) > 0) ? intval(trim($_GET['edit_operator'])) : -1),
				((intval(trim($_GET['edit_operand_type'])) > 0) ? intval(trim($_GET['edit_operand_type'])) : -1),
				((strlen(trim($_GET['edit_operand_value'])) > 0) ? "'".pg_escape_string(trim($_GET['edit_operand_value']))."'" : "NULL"),
				((strlen(trim($_GET['edit_sequence'])) > 0) ? intval(trim($_GET['edit_sequence'])) : "NULL"),
				((intval(trim($_GET['edit_and_or_operator'])) > 0) ? intval(trim($_GET['edit_and_or_operator'])) : -1),
				intval(trim($_GET['edit_id']))
			);
			if (@pg_query($host_pa, $edit_sql)) {
				\Udoh\Emsa\Utils\DisplayUtils::drawHighlight("XML Rule successfully updated!", "ui-icon-elrsuccess");
			} else {
				\Udoh\Emsa\Utils\DisplayUtils::drawError("Unable to save changes to XML Rule.");
			}
		}
	} elseif (isset($_GET['delete_id'])) {
		########## delete lab ##########
		
		// check to see if passed a valid row id...
		$valid_sql = sprintf("SELECT count(id) AS counter FROM %sstructure_path_rule WHERE id = %s;", $emsaDbSchemaPrefix, pg_escape_string(intval($_GET['delete_id'])));
		$valid_result = @pg_query($host_pa, $valid_sql) or \Udoh\Emsa\Utils\DisplayUtils::drawError("Unable to delete XML Rule.", true);
		$valid_counter = @pg_fetch_result($valid_result, 0, "counter");
		if ($valid_counter != 1) {
			\Udoh\Emsa\Utils\DisplayUtils::drawError("Unable to delete XML Rule -- record not found.");
		} else {
			$delete_sql = sprintf("DELETE FROM ONLY %sstructure_path_rule WHERE id = %d;", $emsaDbSchemaPrefix, intval($_GET['delete_id']));
			if (@pg_query($host_pa, $delete_sql)) {
				\Udoh\Emsa\Utils\DisplayUtils::drawHighlight("XML Rule successfully deleted!", "ui-icon-elrsuccess");
			} else {
				\Udoh\Emsa\Utils\DisplayUtils::drawError("Unable to delete XML Rule.");
			}
		}
	} elseif (isset($_GET['add_flag'])) {
		// add new lab
		if (intval(trim($_GET['new_path'])) > 0) {
			$addlab_sql = sprintf("INSERT INTO %sstructure_path_rule (path_id, operator_id, operand_type_id, operand_value, sequence, and_or_operator_id) VALUES (%d, %d, %d, %s, %d, %d)",
				$emsaDbSchemaPrefix,
				((intval(trim($_GET['new_path'])) > 0) ? intval(trim($_GET['new_path'])) : "NULL"),
				((intval(trim($_GET['new_operator'])) > 0) ? intval(trim($_GET['new_operator'])) : 1),
				((intval(trim($_GET['new_operand_type'])) > 0) ? intval(trim($_GET['new_operand_type'])) : 1),
				"'".pg_escape_string(trim($_GET['new_operand_value']))."'",
				((intval(trim($_GET['new_sequence'])) > 0) ? intval(trim($_GET['new_sequence'])) : 1),
				((intval(trim($_GET['new_and_or_operator'])) > 0) ? intval(trim($_GET['new_and_or_operator'])) : 7)
			);
			if (@pg_query($host_pa, $addlab_sql)) {
				\Udoh\Emsa\Utils\DisplayUtils::drawHighlight("New XML Rule added successfully!", "ui-icon-elrsuccess");
			} else {
				\Udoh\Emsa\Utils\DisplayUtils::drawError("Could not add new XML Rule.");
			}
		} else {
			\Udoh\Emsa\Utils\DisplayUtils::drawError("Missing XML Element!  Select a valid XML Element/Path and try again.");
		}
	}

?>

<h1 class="elrhdg"><span class="ui-icon ui-icon-header ui-icon-elrxmlrules"></span>XML Validation Rules Configuration</h1>

<div class="emsa_search_controls ui-tabs ui-widget">
<button id="addnew_button" title="Add a new XML Rule">Add New XML Rule</button>
</div>

<div id="addnew_form" class="addnew_lab ui-widget ui-widget-content ui-corner-all">
	<div style="clear: both;"><span class="emsa_form_heading">Add New XML Path Rule:</span><br><br></div>
	<form id="new_lab_form" method="GET">
		<label class="vocab_search_form2" for="new_path">Master XML Path:</label>
			<select class="ui-corner-all" name="new_path" id="new_path">
				<option value="0" selected>--</option>
			<?php
				// get list of XML paths for menu
				$path_sql = sprintf("SELECT DISTINCT id, element, xpath FROM %sstructure_path ORDER BY element;", $emsaDbSchemaPrefix);
				$path_rs = @pg_query($host_pa, $path_sql) or \Udoh\Emsa\Utils\DisplayUtils::drawError("Unable to retrieve list of XML paths.", true);
				while ($path_row = pg_fetch_object($path_rs)) {
					printf("<option value=\"%d\">%s (%s)</option>", intval($path_row->id), htmlentities($path_row->element, ENT_QUOTES, "UTF-8"), htmlentities($path_row->xpath, ENT_QUOTES, "UTF-8"));
				}
				pg_free_result($path_rs);
			?>
			</select>
		<br><br><label class="vocab_search_form2" for="new_operator">Operator:</label>
			<select class="ui-corner-all" style="margin: 0px;" name="new_operator" id="new_operator">
				<option value="0" selected>--</option>
			<?php
				// get list of XML paths for menu
				$operator_sql = sprintf("SELECT DISTINCT id, label, graphical FROM %sstructure_operator WHERE operator_type = 1 ORDER BY id;", $emsaDbSchemaPrefix);
				$operator_rs = @pg_query($host_pa, $operator_sql) or \Udoh\Emsa\Utils\DisplayUtils::drawError("Unable to retrieve list of Operators.", true);
				while ($operator_row = pg_fetch_object($operator_rs)) {
					printf("<option value=\"%d\">%s (%s)</option>", intval($operator_row->id), htmlentities($operator_row->label, ENT_QUOTES, "UTF-8"), $operator_row->graphical);
				}
				pg_free_result($operator_rs);
			?>
			</select>
		<label class="vocab_search_form2" for="new_operand_type">Operand Type:</label>
			<select class="ui-corner-all" style="margin: 0px;" name="new_operand_type" id="new_operand_type">
				<option value="0" selected>--</option>
			<?php
				// get list of XML paths for menu
				$operandtype_sql = sprintf("SELECT DISTINCT id, label FROM %sstructure_operand_type ORDER BY id;", $emsaDbSchemaPrefix);
				$operandtype_rs = @pg_query($host_pa, $operandtype_sql) or \Udoh\Emsa\Utils\DisplayUtils::drawError("Unable to retrieve list of Operand Types.", true);
				while ($operandtype_row = pg_fetch_object($operandtype_rs)) {
					printf("<option value=\"%d\">%s</option>", intval($operandtype_row->id), htmlentities($operandtype_row->label, ENT_QUOTES, "UTF-8"));
				}
				pg_free_result($operandtype_rs);
			?>
			</select>
		<label class="vocab_search_form2" for="new_operand_value">Operand Value:</label><input class="ui-corner-all" type="text" name="new_operand_value" id="new_operand_value" />
		<br><br><label class="vocab_search_form2" for="new_sequence">Sequence:</label><input class="ui-corner-all" type="text" name="new_sequence" id="new_sequence" />
		<label class="vocab_search_form2" for="new_and_or_operator">AND/OR Operator:</label>
			<select class="ui-corner-all" style="margin: 0px;" name="new_and_or_operator" id="new_and_or_operator">
				<option value="0" selected>--</option>
			<?php
				// get list of XML paths for menu
				$andoroperator_sql = sprintf("SELECT DISTINCT id, label, graphical FROM %sstructure_operator WHERE operator_type = 2 ORDER BY id;", $emsaDbSchemaPrefix);
				$andoroperator_rs = @pg_query($host_pa, $andoroperator_sql) or \Udoh\Emsa\Utils\DisplayUtils::drawError("Unable to retrieve list of AND/OR Operators.", true);
				while ($andoroperator_row = pg_fetch_object($andoroperator_rs)) {
					printf("<option value=\"%d\">%s (%s)</option>", intval($andoroperator_row->id), htmlentities($andoroperator_row->label, ENT_QUOTES, "UTF-8"), $andoroperator_row->graphical);
				}
				pg_free_result($andoroperator_rs);
			?>
			</select>
		<input type="hidden" name="selected_page" value="<?php echo intval($navSelectedPage); ?>" />
		<input type="hidden" name="submenu" value="<?php echo intval($navSubmenu); ?>" />
		<input type="hidden" name="cat" value="<?php echo intval($navCat); ?>" />
		<input type="hidden" name="subcat" value="<?php echo intval($navSubcat); ?>" />
		<input type="hidden" name="add_flag" value="1" />
		<br><br><button type="submit" name="new_savelab" id="new_savelab">Save New XML Path Rule</button>
		<button type="button" id="addnew_cancel">Cancel</button>
	</form>
</div>

<div class="lab_results_container ui-widget ui-corner-all">
	<table id="labResults">
		<thead>
			<tr>
				<th>Actions</th>
				<th>XML Element/Path</th>
				<th>Operator</th>
				<th>Operand (Type)</th>
				<th>AND/OR Operator</th>
				<th>Rule Sequence</th>
			</tr>
		</thead>
		<tbody>

<?php
	
	$rule_qry = sprintf("SELECT pr.id AS id, pr.operand_value AS operand_value, pr.sequence AS sequence, sp.id AS path_id, sp.element AS element, sp.xpath AS xpath, so.id AS operator_id, so.label AS operator, sot.id AS operand_type_id, sot.label AS operand_type, so2.id AS and_or_operator_id, so2.label AS and_or_operator 
		FROM %sstructure_path_rule pr 
		LEFT JOIN %sstructure_path sp ON (pr.path_id = sp.id) 
		LEFT JOIN %sstructure_operator so ON (pr.operator_id = so.id) 
		LEFT JOIN %sstructure_operand_type sot ON (pr.operand_type_id = sot.id) 
		LEFT JOIN %sstructure_operator so2 ON (pr.and_or_operator_id = so2.id) 
		ORDER BY sp.element, pr.sequence", $emsaDbSchemaPrefix, $emsaDbSchemaPrefix, $emsaDbSchemaPrefix, $emsaDbSchemaPrefix, $emsaDbSchemaPrefix);
	$rule_rs = @pg_query($host_pa, $rule_qry) or die("Could not connect to database: ".pg_last_error());
	
	while ($rule_row = @pg_fetch_object($rule_rs)) {
		echo "<tr>";
		printf("<td class=\"action_col\"><button class=\"delete_lab\" type=\"button\" value=\"%s\" title=\"Delete this record\">Delete</button></td>", intval($rule_row->id));
		echo "<td>".htmlentities($rule_row->element, ENT_QUOTES, "UTF-8")." (<kbd style='font-family: Consolas !important;'>".htmlentities($rule_row->xpath, ENT_QUOTES, "UTF-8")."</kbd>)</td>";
		echo "<td>".htmlentities($rule_row->operator, ENT_QUOTES, "UTF-8")."</td>";
		printf("<td><a class=\"edit_thislab\" title=\"Edit this record\" href=\"javascript:edit_lab('%s', '%s', '%s', '%s', '%s', '%s', '%s');\">%s: %s</a></td>", 
			intval($rule_row->id), intval($rule_row->path_id), intval($rule_row->operator_id), intval($rule_row->operand_type_id), htmlentities($rule_row->operand_value, ENT_QUOTES, "UTF-8"), intval($rule_row->sequence), intval($rule_row->and_or_operator_id), 
			htmlentities($rule_row->operand_type, ENT_QUOTES, "UTF-8"), htmlentities($rule_row->operand_value, ENT_QUOTES, "UTF-8")
		);
		echo "<td>".htmlentities($rule_row->and_or_operator, ENT_QUOTES, "UTF-8")."</td>";
		echo "<td>".intval($rule_row->sequence)."</td>";
		echo "</tr>";
	}
	
	pg_free_result($rule_rs);

?>

		</tbody>
	</table>
	
</div>

<div id="confirm_delete_dialog" title="Delete this rule?">
	<p><span class="ui-icon ui-icon-elrerror" style="float:left; margin:0 7px 50px 0;"></span>This XML Rule will be permanently deleted and cannot be recovered. Are you sure?</p>
</div>

<div id="edit_lab_dialog" title="Edit Rule">
	<form id="edit_modal_form" method="GET">
		<label for="edit_path">Master XML Path:</label><br>
		<select class="ui-corner-all" style="margin: 0px;" name="edit_path" id="edit_path">
			<option value="0" selected>--</option>
		<?php
			// get list of XML paths for menu
			$path_sql = sprintf("SELECT DISTINCT id, element, xpath FROM %sstructure_path ORDER BY element;", $emsaDbSchemaPrefix);
			$path_rs = @pg_query($host_pa, $path_sql) or \Udoh\Emsa\Utils\DisplayUtils::drawError("Unable to retrieve list of XML paths.", true);
			while ($path_row = pg_fetch_object($path_rs)) {
				printf("<option value=\"%d\">%s (%s)</option>", intval($path_row->id), htmlentities($path_row->element, ENT_QUOTES, "UTF-8"), htmlentities($path_row->xpath, ENT_QUOTES, "UTF-8"));
			}
			pg_free_result($path_rs);
		?>
		</select><br><br>
		<label for="edit_operator">Operator:</label><br>
		<select class="ui-corner-all" style="margin: 0px;" name="edit_operator" id="edit_operator">
			<option value="0" selected>--</option>
		<?php
			// get list of XML paths for menu
			$operator_sql = sprintf("SELECT DISTINCT id, label, graphical FROM %sstructure_operator WHERE operator_type = 1 ORDER BY id;", $emsaDbSchemaPrefix);
			$operator_rs = @pg_query($host_pa, $operator_sql) or \Udoh\Emsa\Utils\DisplayUtils::drawError("Unable to retrieve list of Operators.", true);
			while ($operator_row = pg_fetch_object($operator_rs)) {
				printf("<option value=\"%d\">%s (%s)</option>", intval($operator_row->id), htmlentities($operator_row->label, ENT_QUOTES, "UTF-8"), $operator_row->graphical);
			}
			pg_free_result($operator_rs);
		?>
		</select><br><br>
		<label for="edit_operand_type">Operand Type:</label><br>
		<select class="ui-corner-all" style="margin: 0px;" name="edit_operand_type" id="edit_operand_type">
			<option value="0" selected>--</option>
		<?php
			// get list of XML paths for menu
			$operandtype_sql = sprintf("SELECT DISTINCT id, label FROM %sstructure_operand_type ORDER BY id;", $emsaDbSchemaPrefix);
			$operandtype_rs = @pg_query($host_pa, $operandtype_sql) or \Udoh\Emsa\Utils\DisplayUtils::drawError("Unable to retrieve list of Operand Types.", true);
			while ($operandtype_row = pg_fetch_object($operandtype_rs)) {
				printf("<option value=\"%d\">%s</option>", intval($operandtype_row->id), htmlentities($operandtype_row->label, ENT_QUOTES, "UTF-8"));
			}
			pg_free_result($operandtype_rs);
		?>
		</select><br><br>
		<label for="edit_operand_value">Operand Value:</label><br><input class="ui-corner-all" type="text" name="edit_operand_value" id="edit_operand_value" /><br><br>
		<label for="edit_sequence">Sequence:</label><br><input class="ui-corner-all" type="text" name="edit_sequence" id="edit_sequence" /><br><br>
		<label for="edit_and_or_operator">AND/OR Operator:</label><br>
		<select class="ui-corner-all" style="margin: 0px;" name="edit_and_or_operator" id="edit_and_or_operator">
			<option value="0" selected>--</option>
		<?php
			// get list of XML paths for menu
			$andoroperator_sql = sprintf("SELECT DISTINCT id, label, graphical FROM %sstructure_operator WHERE operator_type = 2 ORDER BY id;", $emsaDbSchemaPrefix);
			$andoroperator_rs = @pg_query($host_pa, $andoroperator_sql) or \Udoh\Emsa\Utils\DisplayUtils::drawError("Unable to retrieve list of AND/OR Operators.", true);
			while ($andoroperator_row = pg_fetch_object($andoroperator_rs)) {
				printf("<option value=\"%d\">%s (%s)</option>", intval($andoroperator_row->id), htmlentities($andoroperator_row->label, ENT_QUOTES, "UTF-8"), $andoroperator_row->graphical);
			}
			pg_free_result($andoroperator_rs);
		?>
		</select><br><br>
		<input type="hidden" name="edit_id" id="edit_id" />
		<input type="hidden" name="selected_page" value="<?php echo intval($navSelectedPage); ?>" />
		<input type="hidden" name="submenu" value="<?php echo intval($navSubmenu); ?>" />
		<input type="hidden" name="cat" value="<?php echo intval($navCat); ?>" />
		<input type="hidden" name="subcat" value="<?php echo intval($navSubcat); ?>" />
	</form>
</div>
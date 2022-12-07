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

$nc = new \Udoh\Emsa\Email\Notification($adminDbConn);

if (isset($_GET['edit_conditions']) && (intval($_GET['edit_conditions']) == 1)) {
    // handle add/edit functions
    include_once __DIR__ . '/elrnotify_rule_conditions.php';
} else {
?>

<script>
	$(function() {
		$("#addnew_button").button({
            icon: "ui-icon-elrplus"
        }).on("click", function() {
			$("#addnew_form").show();
			$(".import_error").hide();
			$("#new_labname").trigger("focus");
			$(this).hide();
		});
		
		$("#addnew_cancel").button({
			icon: "ui-icon-elrcancel"
		}).on("click", function() {
			$("#addnew_form").hide();
			$("#addnew_button").show();
		});
		
		$("#new_saverule").button({
            icon: "ui-icon-elrsave"
        });
		
		$(".edit_rule").button({
				icon: "ui-icon-elrpencil"
			}).next().button({
				icon: "ui-icon-elrclose"
			}).next().button({
				icon: "ui-icon-elrrules"
			}).parent().controlgroup();
		
		$(".button_disabled").button( "option", "disabled", true );
		
		$("#confirm_delete_dialog").dialog({
			autoOpen: false,
			modal: true,
			draggable: false,
			resizable: false
		});
		
		$(".manage_rule").on("click", function(e) {
			e.preventDefault();
			var manageConditionAction = "<?php echo $webappBaseUrl; ?>?selected_page=6&submenu=9&cat=4&edit_conditions=1&edit_id="+$(this).val();
			window.location.href = manageConditionAction;
		});
		
		$(".delete_rule").on("click", function(e) {
			e.preventDefault();
			var deleteAction = "<?php echo $webappBaseUrl; ?>?selected_page=6&submenu=9&cat=4&delete_id="+$(this).val();


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
			modal: true,
			resizable: false
		});
		
		$(".edit_rule").on("click", function(e) {
			e.preventDefault();
			var jsonObj = JSON.parse($(this).val());
			
			if (jsonObj.id) {
				$("#edit_id").val(jsonObj.id);
				$("#edit_name").val(jsonObj.name);
				$("#edit_notification_type").val(jsonObj.n_type_id);
				
				if (jsonObj.enabled == "t") {
					$("#edit_enabled_yes").trigger("click");
				} else {
					$("#edit_enabled_no").trigger("click");
				}
				
				if (jsonObj.send_to_state == "t") {
					$("#edit_send_to_state_yes").trigger("click");
				} else {
					$("#edit_send_to_state_no").trigger("click");
				}
				
				if (jsonObj.send_to_lhd == "t") {
					$("#edit_send_to_lhd_yes").trigger("click");
				} else {
					$("#edit_send_to_lhd_no").trigger("click");
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
    fieldset { display: inline-block; }
    legend { font-weight: 600; }
    #addnew_form legend {
        margin-left: 15px;
        margin-right: 5px;
        -moz-user-select: none;
		-khtml-user-select: none;
		-webkit-user-select: none;
		user-select: none; }
    #ruleconditions_cancel { float: right; }
	.rule_chain {
		font-family: 'Open Sans', Arial, Helvetica, sans-serif !important;
		display: inline-block;
		margin: 2px 5px 2px 5px;
		padding: 5px 10px;
		border-left: 2px green solid;
		border-right: 2px green solid;
		border-top: 0;
		border-bottom: 0;
		background-color: rgba(102, 205, 170, 0.1);
		box-shadow: 1px 1px 7px dimgray;
		cursor: default;
		vertical-align: top;
	}
	.rule_operator {
		font-family: Consolas, 'Courier New', Courier, serif !important;
		display: inline-block;
		/* border-left: 1px dimgray dotted;
		border-right: 1px dimgray dotted;
		border-top: 0;
		border-bottom: 0;
		background-color: whitesmoke;
		margin: 2px 5px 2px 5px; */
		font-weight: 700;
		padding: 0px 5px;
		color: darkred;
		text-transform: none;
		cursor: default;
		min-width: 25px;
		text-align: center;
		vertical-align: top;
	}
	.rule_link {
		font-family: 'Open Sans', Arial, Helvetica, sans-serif !important;
		display: inline-block;
		border-left: 1px navy dotted;
		border-right: 1px navy dotted;
		border-top: 0;
		border-bottom: 0;
		font-weight: 400;
		margin: 2px 5px 2px 5px;
		padding: 5px 10px;
		color: black;
		background-color: whitesmoke;
		cursor: default;
		vertical-align: middle;
	}
	.rule_actions {
		display: inline-block;
		padding-left: 10px;
		vertical-align: top;
	}
	.link_wrapper {
		margin: 10px;
	}
	#condition_container {
		margin: 15px 0px;
	}
	.ui-dialog-content label, #addnew_form label.vocab_search_form2 {
		font-family: 'Open Sans', Arial, Helvetica, sans-serif !important;
		font-weight: 600;
	}
	.ui-dialog-content select, .ui-dialog-content input, #addnew_form select, #addnew_form input {
		font-family: 'Open Sans', Arial, Helvetica, sans-serif !important;
		font-weight: 400;
		background-color: lightcyan;
	}
	.ui-dialog-content {
		font-family: 'Open Sans', Arial, Helvetica, sans-serif !important;
		font-weight: 400;
	}
	.ui-dialog-title {
		font-family: 'Oswald', serif; font-weight: 500; font-size: 1.5em;
		text-shadow: 1px 1px 6px dimgray;
	}
	.ui-dialog-content h3 {
		font-family: 'Oswald', serif; font-weight: 500; font-size: 1.3em;
		color: firebrick;
	}
	.ui-dialog {
		box-shadow: 4px 4px 15px dimgray;
	}
</style>

<?php

	if (isset($_GET['edit_id'])) {
		// check to see if passed a valid row id...
		$valid_sql = sprintf("SELECT count(id) AS counter FROM %sbn_rules WHERE id = %s;", $emsaDbSchemaPrefix, pg_escape_string(intval(trim($_GET['edit_id']))));
		$valid_result = @pg_query($host_pa, $valid_sql) or \Udoh\Emsa\Utils\DisplayUtils::drawError("Unable to save changes to notification rule.", true);
		$valid_counter = @pg_fetch_result($valid_result, 0, "counter");
		if ($valid_counter != 1) {
			\Udoh\Emsa\Utils\DisplayUtils::drawError("Unable to save changes to notification rule -- record does not exist.");
		} else {
			$edit_sql = sprintf("UPDATE %sbn_rules SET name = %s, send_to_state = %s, send_to_lhd = %s, notification_type = %s, enabled = %s WHERE id = %s;",
				$emsaDbSchemaPrefix,
				((strlen(trim($_GET['edit_name'])) > 0) ? "'".pg_escape_string(trim($_GET['edit_name']))."'" : "NULL"),
				((trim($_GET['edit_send_to_state']) == 'true') ? 'TRUE' : 'FALSE'),
				((trim($_GET['edit_send_to_lhd']) == 'true') ? 'TRUE' : 'FALSE'),
				intval(trim($_GET['edit_notification_type'])),
				((trim($_GET['edit_enabled']) == 'true') ? 'TRUE' : 'FALSE'),
				intval(trim($_GET['edit_id']))
			);
			if (@pg_query($host_pa, $edit_sql)) {
				\Udoh\Emsa\Utils\DisplayUtils::drawHighlight("Notification Rule successfully updated!", "ui-icon-elrsuccess");
			} else {
                \Udoh\Emsa\Utils\DisplayUtils::drawError("Unable to save changes to Notification Rule.");
			}
		}
	} elseif (isset($_GET['delete_id'])) {
		########## delete lab ##########
		
		// check to see if passed a valid row id...
		$valid_sql = sprintf("SELECT count(id) AS counter FROM %sbn_rules WHERE id = %s;", $emsaDbSchemaPrefix, pg_escape_string(intval($_GET['delete_id'])));
		$valid_result = @pg_query($host_pa, $valid_sql) or \Udoh\Emsa\Utils\DisplayUtils::drawError("Unable to delete Notification Rule.", true);
		$valid_counter = @pg_fetch_result($valid_result, 0, "counter");
		if ($valid_counter != 1) {
			\Udoh\Emsa\Utils\DisplayUtils::drawError("Unable to delete Notification Rule -- record not found.");
		} else {
			// everything checks out, commit the delete...
			$delete_sql = 'BEGIN TRANSACTION;'.PHP_EOL;
			$delete_sql .= 'DELETE FROM '.$emsaDbSchemaPrefix.'bn_rules WHERE id = '.intval($_GET['delete_id']).';'.PHP_EOL;
			$delete_sql .= 'DELETE FROM '.$emsaDbSchemaPrefix.'bn_expression_chain WHERE rule_id = '.intval($_GET['delete_id']).';'.PHP_EOL;
			$delete_sql .= 'COMMIT;';
			if (@pg_query($host_pa, $delete_sql)) {
				\Udoh\Emsa\Utils\DisplayUtils::drawHighlight("Notification Rule successfully deleted!", "ui-icon-elrsuccess");
			} else {
                \Udoh\Emsa\Utils\DisplayUtils::drawError("Unable to delete Notification Rule.");
			}
		}
	} elseif (isset($_GET['add_flag'])) {
		// add new lab
		if (strlen(trim($_GET['new_name'])) > 0) {
			if (intval(trim($_GET['new_notification_type'])) > 0) {
				$addlab_sql = sprintf("INSERT INTO %sbn_rules (name, send_to_state, send_to_lhd, notification_type) VALUES (%s, %s, %s, %s);",
					$emsaDbSchemaPrefix,
					"'".pg_escape_string(trim($_GET['new_name']))."'",
					((trim($_GET['new_send_to_state']) == 'true') ? 'TRUE' : 'FALSE'),
					((trim($_GET['new_send_to_lhd']) == 'true') ? 'TRUE' : 'FALSE'),
					intval(trim($_GET['new_notification_type']))
				);
				$addlab_rs = @pg_query($host_pa, $addlab_sql);
				if ($addlab_rs !== false) {
					//$rule_id = @pg_fetch_result($addlab_rs, 0, 'id');
					//$add_foundation_condgroup_sql = 'INSERT INTO '.$my_db_schema.'bn_expression_chain (rule_id, parent_chain_id, left_id, left_operator_id, link_type) VALUES ('.intval($rule_id).', 0, 0, 0, '.\Udoh\Emsa\Email\Notification::LINKTYPE_CHAIN.');';
					//@pg_query($host_pa, $add_foundation_condgroup_sql);
					\Udoh\Emsa\Utils\DisplayUtils::drawHighlight("New Notification Rule \"".\Udoh\Emsa\Utils\DisplayUtils::xSafe(trim($_GET['new_name']))."\" added successfully!");
				} else {
                    \Udoh\Emsa\Utils\DisplayUtils::drawError("Could not add new Notification Rule.");
				}
			} else {
				\Udoh\Emsa\Utils\DisplayUtils::drawError("No Notification Type selected!  Select a notification type and try again.");
			}
		} else {
			\Udoh\Emsa\Utils\DisplayUtils::drawError("No Notification Rule name specified!  Enter a name and try again.");
		}
	}

?>

<h1 class="elrhdg"><span class="ui-icon ui-icon-header ui-icon-elrxmlrules"></span>Notification Rule Management</h1>

<div class="emsa_search_controls ui-tabs ui-widget">
<button id="addnew_button" title="Add a new notification rule">Add New Rule</button>
</div>

<div id="addnew_form" class="addnew_lab ui-widget ui-widget-content ui-corner-all">
    <div style="clear: both;"><span class="emsa_form_heading">Add New Notification Rule</span><br><br></div>
	<form id="new_lab_form" method="GET">
		<label class="vocab_search_form2 required" for="new_name">Rule Name:</label><input style="width: 20em;" class="ui-corner-all" type="text" name="new_name" id="new_name" />
		<br><br>
		<label class="vocab_search_form2 required" for="new_notification_type">Triggers Notification Type:</label>
		<select class="ui-corner-all" id="new_notification_type" name="new_notification_type">
			<option selected value="-1">--</option>
			<?php
				$new_ntypes_qry = 'SELECT id, label FROM '.$emsaDbSchemaPrefix.'batch_notification_types ORDER BY sort;';
				$new_ntypes_rs = @pg_query($host_pa, $new_ntypes_qry);
				if ($new_ntypes_rs !== false) {
					while ($new_ntypes_row = @pg_fetch_object($new_ntypes_rs)) {
						echo '<option value="'.intval($new_ntypes_row->id).'">'.\Udoh\Emsa\Utils\DisplayUtils::xSafe($new_ntypes_row->label).'</option>'.PHP_EOL;
					}
				} else {
                    \Udoh\Emsa\Utils\DisplayUtils::drawError('Unable to get list of notification types');
				}
				@pg_free_result($new_ntypes_rs);
			?>
		</select>
		<br><br>

        <fieldset>
            <legend style="font-size: 11pt; position: relative; float: left;">Send to UDOH?</legend>
            <label class="vocab_search_form2" for="new_send_to_state_yes"><input class="edit_radio ui-corner-all" type="radio" name="new_send_to_state" id="new_send_to_state_yes" value="true" /> Yes</label>
            <label class="vocab_search_form2" for="new_send_to_state_no"><input class="edit_radio ui-corner-all" type="radio" name="new_send_to_state" id="new_send_to_state_no" value="false" /> No</label>
        </fieldset>
		<br><br>

        <fieldset>
            <legend style="font-size: 11pt; position: relative; float: left;">Send to LHD/Virtual Jurisdiction?</legend>
            <label class="vocab_search_form2" for="new_send_to_lhd_yes"><input class="edit_radio ui-corner-all" type="radio" name="new_send_to_lhd" id="new_send_to_lhd_yes" value="true" /> Yes</label>
            <label class="vocab_search_form2" for="new_send_to_lhd_no"><input class="edit_radio ui-corner-all" type="radio" name="new_send_to_lhd" id="new_send_to_lhd_no" value="false" /> No</label>
        </fieldset>
		
		<input type="hidden" name="selected_page" value="<?php echo intval($navSelectedPage); ?>" />
		<input type="hidden" name="submenu" value="<?php echo intval($navSubmenu); ?>" />
		<input type="hidden" name="cat" value="<?php echo intval($navCat); ?>" />
		<input type="hidden" name="add_flag" value="1" />
		<br><br><button type="submit" name="new_saverule" id="new_saverule">Save New Notification Rule</button>
		<button type="button" id="addnew_cancel">Cancel</button>
	</form>
</div>

<div class="lab_results_container ui-widget ui-corner-all">
	<table id="labResults">
		<thead>
			<tr>
				<th>Actions</th>
				<th>Active?</th>
				<th>Rule Name</th>
				<th>Triggers Notification Type</th>
				<th>Send to UDOH?</th>
				<th>Send to LHD/Virtual Jurisdiction?</th>
			</tr>
		</thead>
		<tbody>

<?php
	
	$qry = 'SELECT r.id AS id, n.id AS n_type_id, r.name, r.send_to_state, r.send_to_lhd, n.label, r.enabled 
		FROM '.$emsaDbSchemaPrefix.'bn_rules r 
		LEFT JOIN '.$emsaDbSchemaPrefix.'batch_notification_types n ON (r.notification_type = n.id) 
		ORDER BY r.name';
	$rs = pg_query($host_pa, $qry) or die("Could not connect to database: ".pg_last_error());
	
	while ($row = pg_fetch_object($rs)) {
		echo "<tr>";
		echo "<td style=\"white-space: nowrap;\" class=\"action_col\">";
		unset($edit_lab_params, $num_of_conditions);
		$num_of_conditions = $nc->getNextChain($row->id, 0, 0);
		$edit_lab_params = array(
			"id" => intval($row->id), 
			"n_type_id" => intval($row->n_type_id), 
			"name" => \Udoh\Emsa\Utils\DisplayUtils::xSafe($row->name), 
			"send_to_state" => \Udoh\Emsa\Utils\DisplayUtils::xSafe($row->send_to_state), 
			"send_to_lhd" => \Udoh\Emsa\Utils\DisplayUtils::xSafe($row->send_to_lhd), 
			"enabled" => \Udoh\Emsa\Utils\DisplayUtils::xSafe($row->enabled)
		);
		printf("<button class=\"edit_rule\" type=\"button\" value='%s' title=\"Edit basic settings for this notification rule\">Edit Basics</button>", json_encode($edit_lab_params));
		printf("<button class=\"delete_rule\" type=\"button\" value=\"%s\" title=\"Permanently delete this notification rule\">Delete</button>", intval($row->id));
		printf("<button class=\"manage_rule\" type=\"button\" value=\"%s\" title=\"Manage conditions this notification rule\">Conditions</button>", intval($row->id));
		echo "</td>";
		echo "<td>".((trim($row->enabled) == "t") ? (($num_of_conditions === false) ? "<span style=\"float: right;\" class=\"ui-icon ui-icon-elrerror\" title=\"Rule Enabled, No Conditions Defined!\"></span>" : "<span style=\"float: right;\" class=\"ui-icon ui-icon-elron\" title=\"Rule Valid & Enabled\"></span>") : "<span style=\"float: right;\" class=\"ui-icon ui-icon-elrstop\" title=\"Rule Disabled\"></span>")."</td>";
		echo "<td>".\Udoh\Emsa\Utils\DisplayUtils::xSafe($row->name)."</td>";
		echo "<td>".\Udoh\Emsa\Utils\DisplayUtils::xSafe($row->label)."</td>";
		echo "<td>".((trim($row->send_to_state) == "t") ? "<span class=\"ui-icon ui-icon-elrsuccess\" title=\"Yes\"></span>" : "<span class=\"ui-icon ui-icon-elrcancel\" title=\"No\"></span>")."</td>";
		echo "<td>".((trim($row->send_to_lhd) == "t") ? "<span class=\"ui-icon ui-icon-elrsuccess\" title=\"Yes\"></span>" : "<span class=\"ui-icon ui-icon-elrcancel\" title=\"No\"></span>")."</td>";
		echo "</tr>";
	}
	
	pg_free_result($rs);

?>

		</tbody>
	</table>
	
</div>

<div id="confirm_delete_dialog" title="Delete this Notification Rule?">
	<p><span class="ui-icon ui-icon-elrerror" style="float:left; margin:0 7px 50px 0;"></span> This Notification Rule will be permanently deleted and cannot be recovered. Are you sure?</p>
</div>

<div id="edit_lab_dialog" title="Edit Notification Rule">
	<form id="edit_modal_form" method="GET">
        <fieldset>
            <legend>Enabled?</legend>
            <label for="edit_enabled_yes"><input class="edit_radio ui-corner-all" type="radio" name="edit_enabled" id="edit_enabled_yes" value="true" /> Yes (Enabled)</label>
            <label for="edit_enabled_no"><input class="edit_radio ui-corner-all" type="radio" name="edit_enabled" id="edit_enabled_no" value="false" /> No (Disabled)</label>
        </fieldset>
		<br><br>

		<label class="required" for="edit_name">Rule Name:</label><br><input class="ui-corner-all" type="text" name="edit_name" id="edit_name" /><br><br>
		<label class="required" for="edit_notification_type">Triggers Notification Type:</label><br>
		<select class="ui-corner-all" id="edit_notification_type" name="edit_notification_type">
			<option selected value="-1">--</option>
			<?php
				$edit_ntypes_qry = 'SELECT id, label FROM '.$emsaDbSchemaPrefix.'batch_notification_types ORDER BY sort;';
				$edit_ntypes_rs = @pg_query($host_pa, $edit_ntypes_qry);
				if ($edit_ntypes_rs !== false) {
					while ($edit_ntypes_row = @pg_fetch_object($edit_ntypes_rs)) {
						echo '<option value="'.intval($edit_ntypes_row->id).'">'.\Udoh\Emsa\Utils\DisplayUtils::xSafe($edit_ntypes_row->label).'</option>'.PHP_EOL;
					}
				} else {
                    \Udoh\Emsa\Utils\DisplayUtils::drawError('Unable to get list of notification types');
				}
				@pg_free_result($edit_ntypes_rs);
			?>
		</select><br><br>

        <fieldset>
            <legend>Send to UDOH?</legend>
            <label for="edit_send_to_state_yes"><input class="edit_radio ui-corner-all" type="radio" name="edit_send_to_state" id="edit_send_to_state_yes" value="true" /> Yes</label>
            <label for="edit_send_to_state_no"><input class="edit_radio ui-corner-all" type="radio" name="edit_send_to_state" id="edit_send_to_state_no" value="false" /> No</label>
        </fieldset>
		<br><br>

        <fieldset>
            <legend>Send to LHD/Virtual Jurisdiction?</legend>
            <label for="edit_send_to_lhd_yes"><input class="edit_radio ui-corner-all" type="radio" name="edit_send_to_lhd" id="edit_send_to_lhd_yes" value="true" /> Yes</label>
            <label for="edit_send_to_lhd_no"><input class="edit_radio ui-corner-all" type="radio" name="edit_send_to_lhd" id="edit_send_to_lhd_no" value="false" /> No</label>
        </fieldset>
        
		<input type="hidden" name="edit_id" id="edit_id" />
		<input type="hidden" name="selected_page" value="<?php echo intval($navSelectedPage); ?>" />
		<input type="hidden" name="submenu" value="<?php echo intval($navSubmenu); ?>" />
		<input type="hidden" name="cat" value="<?php echo intval($navCat); ?>" />
	</form>
</div>

<?php
}

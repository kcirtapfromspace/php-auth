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
            $("#new_labname").trigger("focus");
            $(this).hide();
        });

        $("#addnew_cancel").button({
            icon: "ui-icon-elrcancel"
        }).on("click", function() {
            $("#addnew_form").hide();
            $("#addnew_button").show();
        });

        $("#new_saveparam").button({
            icon: "ui-icon-elrsave"
        });

        $(".edit_param").button({
            icon: "ui-icon-elrpencil"
        }).next().button({
            icon: "ui-icon-elrclose"
        }).parent().controlgroup();

        $(".button_disabled").button("option", "disabled", true);

        $("#confirm_delete_dialog").dialog({
            autoOpen: false,
            modal: true,
            draggable: false,
            resizable: false
        });

        $(".delete_param").on("click", function(e) {
            e.preventDefault();
            var deleteAction = "<?php echo $webappBaseUrl; ?>?selected_page=6&submenu=9&cat=7&delete_id=" + $(this).val();


            $("#confirm_delete_dialog").dialog('option', 'buttons', {
                "Delete": function() {
                    window.location.href = deleteAction;
                },
                "Cancel": function() {
                    $(this).dialog("close");
                }
            });

            $("#confirm_delete_dialog").dialog("open");

        });

        $("#edit_lab_dialog").dialog({
            autoOpen: false,
            modal: true
        });

        $(".edit_param").on("click", function(e) {
            e.preventDefault();
            var jsonObj = JSON.parse($(this).val());

            if (jsonObj.id) {
                $("#edit_id").val(jsonObj.id);
                $("#edit_name").val(jsonObj.label);
                $("#edit_varname").val(jsonObj.varname);
                //$("#edit_valuefrom").val(jsonObj.value_from);
                //$("#edit_lookup_table").val(jsonObj.lookup_table);
                //$("#edit_lookup_filter").val(jsonObj.lookup_filter);
                //$("#edit_lookup_value_column").val(jsonObj.lookup_value_column);
                //$("#edit_lookup_label_column").val(jsonObj.lookup_label_column);

                $("#edit_lab_dialog").dialog('option', 'buttons', {
                    "Save Changes": function() {
                        $(this).dialog("close");
                        $("#edit_modal_form").trigger("submit");
                    },
                    "Cancel": function() {
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
    fieldset { padding: 10px; font-family: 'Open Sans', Arial, Helvetica, sans-serif !important; }
    legend { font-family: 'Oswald', serif; margin-left: 10px; color: firebrick; font-weight: 500; font-size: 1.5em; }
    fieldset label { font-weight: 600 !important; }
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
$nc = new \Udoh\Emsa\Email\Notification($adminDbConn);
$nc_proplist = $nc->getPropertyList();

if (isset($_GET['edit_id'])) {
    // check to see if passed a valid row id...
    $valid_sql = sprintf("SELECT count(id) AS counter FROM %sbn_rule_parameters WHERE id = %s;", $emsaDbSchemaPrefix, pg_escape_string(intval(trim($_GET['edit_id']))));
    $valid_result = @pg_query($host_pa, $valid_sql) or \Udoh\Emsa\Utils\DisplayUtils::drawError("Unable to save changes to Rule Parameter.", true);
    $valid_counter = @pg_fetch_result($valid_result, 0, "counter");
    if ($valid_counter != 1) {
        \Udoh\Emsa\Utils\DisplayUtils::drawError("Unable to save changes to Rule Parameter -- record does not exist.");
    } else {
        $edit_sql = sprintf("UPDATE %sbn_rule_parameters SET 
					label = %s, 
					varname = %s 
				WHERE id = %d;", $emsaDbSchemaPrefix, ((strlen(trim($_GET['edit_name'])) > 0) ? "'" . pg_escape_string(trim($_GET['edit_name'])) . "'" : "NULL"), ((strlen(trim($_GET['edit_varname'])) > 0) ? "'" . pg_escape_string(trim($_GET['edit_varname'])) . "'" : "NULL"), intval(trim($_GET['edit_id']))
        );
        if (@pg_query($host_pa, $edit_sql)) {
            \Udoh\Emsa\Utils\DisplayUtils::drawHighlight("Rule Parameter successfully updated!", "ui-icon-check");
        } else {
            \Udoh\Emsa\Utils\DisplayUtils::drawError("Unable to save changes to Rule Parameter.");
        }
    }
} elseif (isset($_GET['delete_id'])) {
    ########## delete lab ##########
    // check to see if passed a valid row id...
    $valid_sql = sprintf("SELECT count(id) AS counter FROM %sbn_rule_parameters WHERE id = %d;", $emsaDbSchemaPrefix, intval(trim($_GET['delete_id'])));
    $valid_result = @pg_query($host_pa, $valid_sql) or \Udoh\Emsa\Utils\DisplayUtils::drawError("Unable to delete Rule Parameter.", true);
    $valid_counter = @pg_fetch_result($valid_result, 0, "counter");
    if ($valid_counter != 1) {
        \Udoh\Emsa\Utils\DisplayUtils::drawError("Unable to delete Rule Parameter -- record not found.");
    } else {
        // check for alias labs that depend on this row, throw a dependency warning instead of deleting...
        //$dependency_sql = sprintf("SELECT count(alias_for) AS counter FROM %sbn_rule_parameters WHERE alias_for = %s;", $my_db_schema, pg_escape_string(intval($_GET['delete_id'])));
        //$dependency_result = @pg_query($host_pa, $dependency_sql) or \Udoh\Emsa\Utils\DisplayUtils::drawError("Unable to delete Rule Parameter.", true);
        //$dependency_count = @pg_fetch_result($dependency_result, 0, "counter");
        $dependency_count = 0;
        if ($dependency_count > 0) {
            \Udoh\Emsa\Utils\DisplayUtils::drawError("Unable to delete Rule Parameter -- " . intval($dependency_count) . " rule" . (($dependency_count > 1) ? "s " : " ") . "use this Rule Parameter.  Please edit/delete any rules using this Rule Parameter first and try again.");
        } else {
            // everything checks out, commit the delete...
            $delete_sql = sprintf("DELETE FROM %sbn_rule_parameters WHERE id = %s;", $emsaDbSchemaPrefix, pg_escape_string(intval($_GET['delete_id'])));
            if (@pg_query($host_pa, $delete_sql)) {
                \Udoh\Emsa\Utils\DisplayUtils::drawHighlight("Rule Parameter successfully deleted!", "ui-icon-check");
            } else {
                \Udoh\Emsa\Utils\DisplayUtils::drawError("Unable to delete Rule Parameter.");
            }
        }
    }
} elseif (isset($_GET['add_flag'])) {
    // add new lab
    if ((strlen(trim($_GET['new_name'])) > 0) && (strlen(trim($_GET['new_varname'])) > 0)) {
        $addlab_sql = sprintf("INSERT INTO %sbn_rule_parameters (
					label, 
					varname 
				) VALUES (
					%s, 
					%s 
				)", $emsaDbSchemaPrefix, "'" . pg_escape_string(trim($_GET['new_name'])) . "'", "'" . pg_escape_string(trim($_GET['new_varname'])) . "'"
        );
        @pg_query($host_pa, $addlab_sql) or \Udoh\Emsa\Utils\DisplayUtils::drawError("Could not add new Rule Parameter.");
        \Udoh\Emsa\Utils\DisplayUtils::drawHighlight("New Rule Parameter \"" . \Udoh\Emsa\Utils\DisplayUtils::xSafe(trim($_GET['new_name'])) . "\" added successfully!");
    } else {
        \Udoh\Emsa\Utils\DisplayUtils::drawError("No Rule Parameter name specified!  Enter a name and try again.");
    }
}
?>

<h1 class="elrhdg"><span class="ui-icon ui-icon-header ui-icon-emsasettings"></span>Manage Rule Parameters</h1>

<div class="emsa_search_controls ui-tabs ui-widget">
    <button id="addnew_button" class="addnew_button_right" title="Add a new parameter for use in building rules">Add New Rule Parameter</button>
    <div style="float: right; width: 50%; font-style: italic; font-family: 'Open Sans', Arial, Helvetica, sans-serif; margin: 5px;">
        These parameters are used to define rule test conditions.  Each parameter is associated with a type of data, and can be given a label that will be displayed in the Rule Builder.
    </div>
</div>

<div id="addnew_form" class="addnew_lab ui-widget ui-widget-content ui-corner-all">
    <div style="clear: both;"><span class="emsa_form_heading">Add New Rule Parameter:</span><br><br></div>
    <form id="new_lab_form" method="GET">
        <label class="vocab_search_form2" for="new_name">Label:</label><input class="ui-corner-all" type="text" name="new_name" id="new_name" />
        <label class="vocab_search_form2" for="new_varname">Variable Name:</label>
        <select class="ui-corner-all" name="new_varname" id="new_varname">
            <option selected value="">--</option>
<?php
foreach ($nc_proplist as $propname) {
    echo '<option value="' . $propname . '">' . $propname . '</option>' . PHP_EOL;
}
?>
        </select>
<?php
/*
 * not using lookup values for now, save for potential later use
  <br><br>
  <label class="vocab_search_form2" for="new_valuefrom">Values From:</label>
  <select class="ui-corner-all" name="new_valuefrom" id="new_valuefrom">
  <option selected value="">--</option>
  <option value="<?php echo RuleParameter::PARAMVALUE_USER; ?>">User entry</option>
  <option value="<?php echo RuleParameter::PARAMVALUE_LOOKUP; ?>">Table Lookup (Specify table parameters below)</option>
  <option value="<?php echo RuleParameter::PARAMVALUE_VOCAB; ?>">Master Vocabulary (Specify vocab category below)</option>
  </select><br><br>
  <label class="vocab_search_form2" for="new_lookup_filter">Vocab Category:</label><input class="ui-corner-all" type="text" name="new_lookup_filter" id="new_lookup_filter" /><br><br>
  <label class="vocab_search_form2" for="new_lookup_table">Lookup Table:</label><input class="ui-corner-all" type="text" name="new_lookup_table" id="new_lookup_table" />
  <label class="vocab_search_form2" for="new_lookup_value_column">Lookup Value Column:</label><input class="ui-corner-all" type="text" name="new_lookup_value_column" id="new_lookup_value_column" />
  <label class="vocab_search_form2" for="new_lookup_label_column">Lookup Label Column:</label><input class="ui-corner-all" type="text" name="new_lookup_label_column" id="new_lookup_label_column" />
 */
?>
        <input type="hidden" name="selected_page" value="<?php echo intval($navSelectedPage); ?>" />
        <input type="hidden" name="submenu" value="<?php echo intval($navSubmenu); ?>" />
        <input type="hidden" name="cat" value="<?php echo intval($navCat); ?>" />
        <input type="hidden" name="add_flag" value="1" />
        <br><br><button type="submit" name="new_saveparam" id="new_saveparam">Save New Rule Parameter</button>
        <button type="button" id="addnew_cancel">Cancel</button>
    </form>
</div>

<div class="lab_results_container ui-widget ui-corner-all">
    <table id="labResults">
        <thead>
            <tr>
                <th>Actions</th>
                <th>Label</th>
                <th>Variable Name</th>
                <th>Variable Type</th>
            </tr>
        </thead>
        <tbody>

<?php
$qry = 'SELECT * from ' . $emsaDbSchemaPrefix . 'bn_rule_parameters ORDER BY label';
$rs = pg_query($host_pa, $qry) or die("Could not connect to database: " . pg_last_error());

while ($row = pg_fetch_object($rs)) {
    echo "<tr>";
    echo "<td style=\"white-space: nowrap;\" class=\"action_col\">";
    unset($edit_var_params);
    $edit_var_params = array(
        "id" => intval($row->id),
        "label" => \Udoh\Emsa\Utils\DisplayUtils::xSafe($row->label),
        "varname" => \Udoh\Emsa\Utils\DisplayUtils::xSafe($row->varname)
    );
    printf("<button class=\"edit_param\" type=\"button\" value='%s' title=\"Edit this Rule Parameter\">Edit</button>", json_encode($edit_var_params));
    printf("<button class=\"delete_param\" type=\"button\" value=\"%s\" title=\"Permanently delete this Rule Parameter\">Delete</button>", intval($row->id));
    echo "</td>";
    echo "<td>" . \Udoh\Emsa\Utils\DisplayUtils::xSafe($row->label) . "</td>";
    echo "<td>" . \Udoh\Emsa\Utils\DisplayUtils::xSafe($row->varname) . "</td>";
    echo "<td>" . \Udoh\Emsa\Utils\DisplayUtils::xSafe($nc->getDataType($row->varname)) . "</td>";
    echo "</tr>";
}

pg_free_result($rs);
?>

        </tbody>
    </table>

</div>

<div id="confirm_delete_dialog" title="Delete this Rule Parameter?">
    <p><span class="ui-icon ui-icon-elrerror" style="float:left; margin:0 7px 50px 0;"></span>This Rule Parameter will be permanently deleted and cannot be recovered. Are you sure?</p>
</div>

<div id="edit_lab_dialog" title="Edit Rule Parameter">
    <form id="edit_modal_form" method="GET">
        <label for="edit_name">Label:</label><br><input class="ui-corner-all" type="text" name="edit_name" id="edit_name" /><br><br>
        <label for="edit_varname">Variable Name:</label>
        <select class="ui-corner-all" name="edit_varname" id="edit_varname">
            <option selected value="">--</option>
<?php
foreach ($nc_proplist as $propname) {
    echo '<option value="' . $propname . '">' . $propname . '</option>' . PHP_EOL;
}
?>
        </select><br><br>
<?php
/*
 * not using lookup values for now, save for potential later use
  <label for="edit_valuefrom">Values From:</label>
  <select class="ui-corner-all" name="edit_valuefrom" id="edit_valuefrom">
  <option selected value="">--</option>
  <option value="<?php echo RuleParameter::PARAMVALUE_USER; ?>">User entry</option>
  <option value="<?php echo RuleParameter::PARAMVALUE_LOOKUP; ?>">Table Lookup (Specify table parameters below)</option>
  <option value="<?php echo RuleParameter::PARAMVALUE_VOCAB; ?>">Master Vocabulary (Specify vocab category below)</option>
  </select><br><br>
  <label for="edit_lookup_filter">Vocab Category:</label><input class="ui-corner-all" type="text" name="edit_lookup_filter" id="edit_lookup_filter" /><br><br>
  <label for="edit_lookup_table">Lookup Table:</label><input class="ui-corner-all" type="text" name="edit_lookup_table" id="edit_lookup_table" /><br><br>
  <label for="edit_lookup_value_column">Lookup Value Column:</label><input class="ui-corner-all" type="text" name="edit_lookup_value_column" id="edit_lookup_value_column" /><br><br>
  <label for="edit_lookup_label_column">Lookup Label Column:</label><input class="ui-corner-all" type="text" name="edit_lookup_label_column" id="edit_lookup_label_column" />
 */
?>
        <input type="hidden" name="edit_id" id="edit_id" />
        <input type="hidden" name="selected_page" value="<?php echo intval($navSelectedPage); ?>" />
        <input type="hidden" name="submenu" value="<?php echo intval($navSubmenu); ?>" />
        <input type="hidden" name="cat" value="<?php echo intval($navCat); ?>" />
    </form>
</div>
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

        $("#new_savetype").button({
            icon: "ui-icon-elrsave"
        });

        $(".edit_type").button({
            icon: "ui-icon-elrpencil"
        }).next().button({
            icon: "ui-icon-elrclose"
        }).next().button({
            icon: "ui-icon-arrowthick-1-n",
            showLabel: false
        }).next().button({
            icon: "ui-icon-arrowthick-1-s",
            showLabel: false
        }).parent().controlgroup();

        $(".button_disabled").button("option", "disabled", true);

        $("#confirm_delete_dialog").dialog({
            autoOpen: false,
            modal: true,
            draggable: false,
            resizable: false
        });

        $(".delete_type").on("click", function(e) {
            e.preventDefault();
            var deleteAction = "<?php echo $webappBaseUrl; ?>?selected_page=6&submenu=9&cat=3&delete_id=" + $(this).val();


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

        $(".promote_type").on("click", function(e) {
            e.preventDefault();
            var promoteAction = "<?php echo $webappBaseUrl; ?>?selected_page=6&submenu=9&cat=3&promote_id=" + $(this).val();
            window.location.href = promoteAction;
        });

        $(".demote_type").on("click", function(e) {
            e.preventDefault();
            var demoteAction = "<?php echo $webappBaseUrl; ?>?selected_page=6&submenu=9&cat=3&demote_id=" + $(this).val();
            window.location.href = demoteAction;
        });

        $("#edit_lab_dialog").dialog({
            autoOpen: false,
            modal: true
        });

        $(".edit_type").on("click", function(e) {
            e.preventDefault();
            var jsonObj = JSON.parse($(this).val());

            if (jsonObj.id) {
                $("#edit_id").val(jsonObj.id);
                $("#edit_label").val(jsonObj.label);
                $("#edit_custom").val(jsonObj.custom);

                if (jsonObj.state_use == "t") {
                    $("#edit_state_use_yes").trigger("click");
                } else {
                    $("#edit_state_use_no").trigger("click");
                }

                if (jsonObj.lhd_use == "t") {
                    $("#edit_lhd_use_yes").trigger("click");
                } else {
                    $("#edit_lhd_use_no").trigger("click");
                }

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
    fieldset { display: inline-block; }
    legend { font-weight: 600; }
    #addnew_form legend {
        margin-left: 15px;
        margin-right: 5px;
        -moz-user-select: none;
		-khtml-user-select: none;
		-webkit-user-select: none;
		user-select: none; }
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
    $valid_sql = sprintf("SELECT count(id) AS counter FROM %sbatch_notification_types WHERE id = %s;", $emsaDbSchemaPrefix, pg_escape_string(intval(trim($_GET['edit_id']))));
    $valid_result = @pg_query($host_pa, $valid_sql) or \Udoh\Emsa\Utils\DisplayUtils::drawError("Unable to save changes to notification type.", true);
    $valid_counter = @pg_fetch_result($valid_result, 0, "counter");
    if ($valid_counter != 1) {
        \Udoh\Emsa\Utils\DisplayUtils::drawError("Unable to save changes to notification type -- record does not exist.");
    } else {
        $edit_sql = sprintf("UPDATE %sbatch_notification_types SET label = %s, state_use = %s, lhd_use = %s, custom = %s WHERE id = %s;", $emsaDbSchemaPrefix, ((strlen(trim($_GET['edit_label'])) > 0) ? "'" . pg_escape_string(trim($_GET['edit_label'])) . "'" : "NULL"), ((trim($_GET['edit_state_use']) == 'true') ? 'TRUE' : 'FALSE'), ((trim($_GET['edit_lhd_use']) == 'true') ? 'TRUE' : 'FALSE'), ((intval(trim($_GET['edit_custom'])) > 0) ? intval(trim($_GET['edit_custom'])) : "NULL"), intval(trim($_GET['edit_id']))
        );
        if (@pg_query($host_pa, $edit_sql)) {
            \Udoh\Emsa\Utils\DisplayUtils::drawHighlight("Notification Type successfully updated!", "ui-icon-check");
        } else {
            \Udoh\Emsa\Utils\DisplayUtils::drawError("Unable to save changes to Notification Type.");
        }
    }
} elseif (isset($_GET['promote_id'])) {
    // check to see if passed a valid row id...
    $valid_sql = sprintf("SELECT sort FROM %sbatch_notification_types WHERE id = %s;", $emsaDbSchemaPrefix, pg_escape_string(intval(trim($_GET['promote_id']))));
    $valid_result = @pg_query($host_pa, $valid_sql) or \Udoh\Emsa\Utils\DisplayUtils::drawError("Unable to save changes to notification type.", true);
    $target_sort = @pg_fetch_result($valid_result, 0, 'sort');
    $victim_sql = sprintf("SELECT id FROM %sbatch_notification_types WHERE sort = %s;", $emsaDbSchemaPrefix, intval(($target_sort - 1)));
    $victim_result = @pg_query($host_pa, $victim_sql) or \Udoh\Emsa\Utils\DisplayUtils::drawError("Unable to save changes to notification type.", true);
    if ($victim_result !== false && @pg_num_rows($victim_result) === 1) {
        $victim_id = @pg_fetch_result($victim_result, 0, 'id');
    }
    if (!isset($victim_id)) {
        \Udoh\Emsa\Utils\DisplayUtils::drawHighlight('Selected Notification Type cannot be promoted any higher!');
    } else {
        // promote selected ID
        $target_promote_sql = sprintf("UPDATE %sbatch_notification_types SET sort = %s WHERE id = %s;", $emsaDbSchemaPrefix, intval(($target_sort - 1)), intval(trim($_GET['promote_id']))
        );
        if (@pg_query($host_pa, $target_promote_sql)) {
            // demote next-highest ID
            $victim_demote_sql = sprintf("UPDATE %sbatch_notification_types SET sort = %s WHERE id = %s;", $emsaDbSchemaPrefix, intval($target_sort), intval($victim_id)
            );
            if (@pg_query($host_pa, $victim_demote_sql)) {
                \Udoh\Emsa\Utils\DisplayUtils::drawHighlight("Notification Type order updated!", "ui-icon-check");
            } else {
                \Udoh\Emsa\Utils\DisplayUtils::drawError("Unable to promote Notification Type.");
            }
        } else {
            \Udoh\Emsa\Utils\DisplayUtils::drawError("Unable to promote Notification Type.");
        }
    }
} elseif (isset($_GET['demote_id'])) {
    $valid_sql = sprintf("SELECT sort FROM %sbatch_notification_types WHERE id = %s;", $emsaDbSchemaPrefix, pg_escape_string(intval(trim($_GET['demote_id']))));
    $valid_result = @pg_query($host_pa, $valid_sql) or \Udoh\Emsa\Utils\DisplayUtils::drawError("Unable to save changes to notification type.", true);
    $target_sort = @pg_fetch_result($valid_result, 0, 'sort');
    $victim_sql = sprintf("SELECT id FROM %sbatch_notification_types WHERE sort = %s;", $emsaDbSchemaPrefix, intval(($target_sort + 1)));
    $victim_result = @pg_query($host_pa, $victim_sql) or \Udoh\Emsa\Utils\DisplayUtils::drawError("Unable to save changes to notification type.", true);
    if ($victim_result !== false && @pg_num_rows($victim_result) === 1) {
        $victim_id = @pg_fetch_result($victim_result, 0, 'id');
    }
    if (!isset($victim_id)) {
        \Udoh\Emsa\Utils\DisplayUtils::drawHighlight('Selected Notification Type cannot be demoted any lower!');
    } else {
        // demote selected ID
        $target_demote_sql = sprintf("UPDATE %sbatch_notification_types SET sort = %s WHERE id = %s;", $emsaDbSchemaPrefix, intval(($target_sort + 1)), intval(trim($_GET['demote_id']))
        );
        if (@pg_query($host_pa, $target_demote_sql)) {
            // promote next-lowest ID
            $victim_promote_sql = sprintf("UPDATE %sbatch_notification_types SET sort = %s WHERE id = %s;", $emsaDbSchemaPrefix, intval($target_sort), intval($victim_id)
            );
            if (@pg_query($host_pa, $victim_promote_sql)) {
                \Udoh\Emsa\Utils\DisplayUtils::drawHighlight("Notification Type order updated!", "ui-icon-check");
            } else {
                \Udoh\Emsa\Utils\DisplayUtils::drawError("Unable to demote Notification Type.");
            }
        } else {
            \Udoh\Emsa\Utils\DisplayUtils::drawError("Unable to demote Notification Type.");
        }
    }
} elseif (isset($_GET['delete_id'])) {
    ########## delete lab ##########
    // check to see if passed a valid row id...
    $valid_sql = sprintf("SELECT count(id) AS counter FROM %sbatch_notification_types WHERE id = %s;", $emsaDbSchemaPrefix, pg_escape_string(intval($_GET['delete_id'])));
    $valid_result = @pg_query($host_pa, $valid_sql) or \Udoh\Emsa\Utils\DisplayUtils::drawError("Unable to delete notification type.", true);
    $valid_counter = @pg_fetch_result($valid_result, 0, "counter");
    if ($valid_counter != 1) {
        \Udoh\Emsa\Utils\DisplayUtils::drawError("Unable to delete notification type -- record not found.");
    } else {
        // check for alias labs that depend on this row, throw a dependency warning instead of deleting...
        //$dependency_sql = sprintf("SELECT count(alias_for) AS counter FROM %sbatch_notification_types WHERE alias_for = %s;", $my_db_schema, pg_escape_string(intval($_GET['delete_id'])));
        //$dependency_result = @pg_query($host_pa, $dependency_sql) or suicide("Unable to delete Notification Type.", 1, 1);
        //$dependency_count = @pg_fetch_result($dependency_result, 0, "counter");
        $dependency_count = 0;
        if ($dependency_count > 0) {
            \Udoh\Emsa\Utils\DisplayUtils::drawError("Unable to delete notification type -- " . intval($dependency_count) . " rule" . (($dependency_count > 1) ? "s " : " ") . "use this notification type.  Please edit/delete any rules using this notification type first and try again.");
        } else {
            // everything checks out, commit the delete...
            // get the sort position of the record about to be deleted
            $delete_sort_sql = sprintf("SELECT sort FROM %sbatch_notification_types WHERE id = %s;", $emsaDbSchemaPrefix, pg_escape_string(intval($_GET['delete_id'])));
            $delete_sort = intval(@pg_fetch_result(@pg_query($host_pa, $delete_sort_sql), 0, 'sort'));
            if ($delete_sort > 0) {
                $delete_sql = sprintf("DELETE FROM %sbatch_notification_types WHERE id = %s;", $emsaDbSchemaPrefix, pg_escape_string(intval($_GET['delete_id'])));
                if (@pg_query($host_pa, $delete_sql)) {
                    $delete_sort_update_sql = sprintf("UPDATE %sbatch_notification_types SET sort = sort - 1 WHERE sort > %s;", $emsaDbSchemaPrefix, intval(($delete_sort))
                    );
                    $delete_sort_update_result = @pg_query($host_pa, $delete_sort_update_sql);
                    if ($delete_sort_update_result !== false) {
                        \Udoh\Emsa\Utils\DisplayUtils::drawHighlight("Notification Type successfully deleted!", "ui-icon-check");
                    } else {
                        \Udoh\Emsa\Utils\DisplayUtils::drawHighlight('Notification Type successfully deleted, but an error occurred while updating sort order for remaining types.', 'ui-icon-elrerror');
                    }
                } else {
                    \Udoh\Emsa\Utils\DisplayUtils::drawError("Unable to delete Notification Type.");
                }
            } else {
                \Udoh\Emsa\Utils\DisplayUtils::drawError("Unable to delete Notification Type.");
            }
        }
    }
} elseif (isset($_GET['add_flag'])) {
    // add new lab
    if (strlen(trim($_GET['new_label'])) > 0) {
        $new_sort = intval(((intval(@pg_fetch_result(@pg_query($host_pa, 'SELECT max(sort) AS sort FROM ' . $emsaDbSchemaPrefix . 'batch_notification_types;'), 0, 'sort'))) + 1));
        $addlab_sql = sprintf("INSERT INTO %sbatch_notification_types (label, state_use, lhd_use, sort, custom) VALUES (%s, %s, %s, %s, %s)", $emsaDbSchemaPrefix, "'" . pg_escape_string(trim($_GET['new_label'])) . "'", ((trim($_GET['new_state_use']) == 'true') ? 'TRUE' : 'FALSE'), ((trim($_GET['new_lhd_use']) == 'true') ? 'TRUE' : 'FALSE'), ((intval($new_sort) > 0) ? intval($new_sort) : "NULL"), ((intval(trim($_GET['new_custom'])) > 0) ? intval(trim($_GET['new_custom'])) : "NULL")
        );
        @pg_query($host_pa, $addlab_sql) or \Udoh\Emsa\Utils\DisplayUtils::drawError("Could not add new notification type.");
        \Udoh\Emsa\Utils\DisplayUtils::drawHighlight("New notification type \"" . \Udoh\Emsa\Utils\DisplayUtils::xSafe(trim($_GET['new_label'])) . "\" added successfully!");
    } else {
        \Udoh\Emsa\Utils\DisplayUtils::drawError("No notification type name specified!  Enter a name and try again.");
    }
}
?>

<h1 class="elrhdg"><span class="ui-icon ui-icon-header ui-icon-elrnotify"></span>Notification Type Configuration</h1>

<div class="emsa_search_controls ui-tabs ui-widget">
    <button id="addnew_button" class="addnew_button_right" title="Add a new notification type">Add New Notification Type</button>
    <div style="float: right; width: 50%; font-style: italic; font-family: 'Open Sans', Arial, Helvetica, sans-serif; margin: 5px;">
        <strong>Tip:</strong>  Each Notification Type will appear as a separate tab in the spreadsheet sent to recipients.  
        Tab order will be the same as shown here.  Use the <strong>&uarr;</strong> and <strong>&darr;</strong> buttons below to adjust tab order.
    </div>
</div>

<div id="addnew_form" class="addnew_lab ui-widget ui-widget-content ui-corner-all">
    <div style="clear: both;"><span class="emsa_form_heading">Add New Notification Type:</span><br><br></div>
    <form id="new_lab_form" method="GET">
        <label class="vocab_search_form2" for="new_label">Notification Type:</label><input class="ui-corner-all" type="text" name="new_label" id="new_label" />
        <br><br>

        <fieldset>
            <legend style="font-size: 11pt; position: relative; float: left;">Show in State-Level Notifications?</legend>
            <label class="vocab_search_form2" for="new_state_use_yes"><input class="edit_radio ui-corner-all" type="radio" name="new_state_use" id="new_state_use_yes" value="true" /> Yes</label>
            <label class="vocab_search_form2" for="new_state_use_no"><input class="edit_radio ui-corner-all" type="radio" name="new_state_use" id="new_state_use_no" value="false" /> No</label>
        </fieldset>
        <br><br>

        <fieldset>
            <legend style="font-size: 11pt; position: relative; float: left;">Show in Jurisdictional Notifications?</legend>
            <label class="vocab_search_form2" for="new_lhd_use_yes"><input class="edit_radio ui-corner-all" type="radio" name="new_lhd_use" id="new_lhd_use_yes" value="true" /> Yes</label>
            <label class="vocab_search_form2" for="new_lhd_use_no"><input class="edit_radio ui-corner-all" type="radio" name="new_lhd_use" id="new_lhd_use_no" value="false" /> No</label>
        </fieldset>

        <br><br><label class="vocab_search_form2" for="new_custom">Included With:</label>
        <select name="new_custom" id="new_custom" class="ui-corner-all" title="If this notification type applies to jurisdictional notifications, indicates which jurisdictions this notification type should appear for.

                'Standard LHDs' will include this notification type to all normal jurisdictions.  Selecting any of the virtual jurisdictions will ensure that the notification type is only included in notifications to that group.">
            <option selected value="0">Standard LHDs</option>
<?php
$custom_qry = 'SELECT id, name FROM ' . $emsaDbSchemaPrefix . 'batch_notification_custom_jurisdictions ORDER BY name;';
$custom_rs = @pg_query($host_pa, $custom_qry);
if ($custom_rs !== false) {
    while ($custom_row = @pg_fetch_object($custom_rs)) {
        echo '<option value="' . intval($custom_row->id) . '">' . \Udoh\Emsa\Utils\DisplayUtils::xSafe($custom_row->name) . '</option>' . PHP_EOL;
    }
} else {
    \Udoh\Emsa\Utils\DisplayUtils::drawError('Unable to get list of custom jurisdictions');
}
@pg_free_result($custom_rs);
?>
        </select>

        <input type="hidden" name="selected_page" value="<?php echo intval($navSelectedPage); ?>" />
        <input type="hidden" name="submenu" value="<?php echo intval($navSubmenu); ?>" />
        <input type="hidden" name="cat" value="<?php echo intval($navCat); ?>" />
        <input type="hidden" name="add_flag" value="1" />
        <br><br><button type="submit" name="new_savetype" id="new_savetype">Save New Notification Type</button>
        <button type="button" id="addnew_cancel">Cancel</button>
    </form>
</div>

<div class="lab_results_container ui-widget ui-corner-all">
    <table id="labResults">
        <thead>
            <tr>
                <th>Actions</th>
                <th>Notification Type</th>
                <th>Show in State-Level Notifications?</th>
                <th>Show in Jurisdictional Notifications?</th>
                <th title="If this notification type applies to jurisdictional notifications, indicates which jurisdictions this notification type should appear for.

                    'Standard LHDs' will include this notification type to all normal jurisdictions.  Selecting any of the virtual jurisdictions will ensure that the notification type is only included in notifications to that group.">Included With</th>
            </tr>
        </thead>
        <tbody>

            <?php
            $qry = 'SELECT t.*, CASE WHEN t.custom IS NULL THEN \'Standard LHDs\' ELSE cj.name END AS custom_name 
		FROM ' . $emsaDbSchemaPrefix . 'batch_notification_types t
		LEFT JOIN ' . $emsaDbSchemaPrefix . 'batch_notification_custom_jurisdictions cj ON (t.custom = cj.id)
		ORDER BY sort';
            $rs = pg_query($host_pa, $qry) or die("Could not connect to database: " . pg_last_error());

            while ($row = pg_fetch_object($rs)) {
                echo "<tr>";
                echo "<td style=\"white-space: nowrap;\" class=\"action_col\">";
                unset($edit_lab_params);
                $edit_lab_params = array(
                    "id" => intval($row->id),
                    "label" => \Udoh\Emsa\Utils\DisplayUtils::xSafe($row->label),
                    "state_use" => \Udoh\Emsa\Utils\DisplayUtils::xSafe(trim($row->state_use)),
                    "lhd_use" => \Udoh\Emsa\Utils\DisplayUtils::xSafe(trim($row->lhd_use)),
                    "custom" => intval($row->custom)
                );
                printf("<button class=\"edit_type\" type=\"button\" value='%s' title=\"Edit this notification type\">Edit</button>", json_encode($edit_lab_params));
                printf("<button class=\"delete_type\" type=\"button\" value=\"%s\" title=\"Permanently delete this notification type\">Delete</button>", intval($row->id));
                printf("<button class=\"promote_type\" type=\"button\" value=\"%s\" title=\"Move this notification type up\">Promote</button>", intval($row->id));
                printf("<button class=\"demote_type\" type=\"button\" value=\"%s\" title=\"Move this notification type down\">Demote</button>", intval($row->id));
                echo "</td>";
                echo "<td>" . \Udoh\Emsa\Utils\DisplayUtils::xSafe($row->label) . "</td>";
                echo "<td>" . ((trim($row->state_use) == "t") ? "<span class=\"ui-icon ui-icon-elrsuccess\" title=\"Yes\"></span>" : "<span class=\"ui-icon ui-icon-elrcancel\" title=\"No\"></span>") . "</td>";
                echo "<td>" . ((trim($row->lhd_use) == "t") ? "<span class=\"ui-icon ui-icon-elrsuccess\" title=\"Yes\"></span>" : "<span class=\"ui-icon ui-icon-elrcancel\" title=\"No\"></span>") . "</td>";
                echo "<td>" . \Udoh\Emsa\Utils\DisplayUtils::xSafe($row->custom_name) . "</td>";
                echo "</tr>";
            }

            pg_free_result($rs);
            ?>

        </tbody>
    </table>
    <br><br>

</div>

<div id="confirm_delete_dialog" title="Delete this Notification Type?">
    <p><span class="ui-icon ui-icon-elrerror" style="float:left; margin:0 7px 50px 0;"></span>This notification type will be permanently deleted and cannot be recovered. Are you sure?</p>
</div>

<div id="edit_lab_dialog" title="Edit Notification Type">
    <form id="edit_modal_form" method="GET">
        <label for="edit_label">Notification Type:</label><br><input class="ui-corner-all" type="text" name="edit_label" id="edit_label" /><br><br>

        <fieldset>
            <legend>Show in State-Level Notifications?</legend>
            <label for="edit_state_use_yes"><input class="edit_radio ui-corner-all" type="radio" name="edit_state_use" id="edit_state_use_yes" value="true" /> Yes</label>
            <label for="edit_state_use_no"><input class="edit_radio ui-corner-all" type="radio" name="edit_state_use" id="edit_state_use_no" value="false" /> No</label>
        </fieldset>
        <br><br>

        <fieldset>
            <legend>Show in Jurisdictional Notifications?</legend>
            <label for="edit_lhd_use_yes"><input class="edit_radio ui-corner-all" type="radio" name="edit_lhd_use" id="edit_lhd_use_yes" value="true" /> Yes</label>
            <label for="edit_lhd_use_no"><input class="edit_radio ui-corner-all" type="radio" name="edit_lhd_use" id="edit_lhd_use_no" value="false" /> No</label>
        </fieldset>

        <br><br><label for="edit_custom">Included With:</label>
        <select name="edit_custom" id="edit_custom" class="ui-corner-all" title="If this notification type applies to jurisdictional notifications, indicates which jurisdictions this notification type should appear for.

                'Standard LHDs' will include this notification type to all normal jurisdictions.  Selecting any of the virtual jurisdictions will ensure that the notification type is only included in notifications to that group.">
            <option selected value="0">Standard LHDs</option>
            <?php
            $custom_qry = 'SELECT id, name FROM ' . $emsaDbSchemaPrefix . 'batch_notification_custom_jurisdictions ORDER BY name;';
            $custom_rs = @pg_query($host_pa, $custom_qry);
            if ($custom_rs !== false) {
                while ($custom_row = @pg_fetch_object($custom_rs)) {
                    echo '<option value="' . intval($custom_row->id) . '">' . \Udoh\Emsa\Utils\DisplayUtils::xSafe($custom_row->name) . '</option>' . PHP_EOL;
                }
            } else {
                \Udoh\Emsa\Utils\DisplayUtils::drawError('Unable to get list of custom jurisdictions');
            }
            @pg_free_result($custom_rs);
            ?>
        </select>

        <input type="hidden" name="edit_id" id="edit_id" />
        <input type="hidden" name="selected_page" value="<?php echo intval($navSelectedPage); ?>" />
        <input type="hidden" name="submenu" value="<?php echo intval($navSubmenu); ?>" />
        <input type="hidden" name="cat" value="<?php echo intval($navCat); ?>" />
    </form>
</div>
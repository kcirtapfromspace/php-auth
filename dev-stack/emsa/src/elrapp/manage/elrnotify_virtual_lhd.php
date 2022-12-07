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

        $("#new_savevirtual").button({
            icon: "ui-icon-elrsave"
        });

        $(".edit_jurisdiction").button({
            icon: "ui-icon-elrpencil"
        }).next().button({
            icon: "ui-icon-elrclose"
        }).parent().controlgroup();

        $(".button_disabled").button("option", "disabled", true);

        $("#confirm_delete_dialog").dialog({
            autoOpen: false,
            modal: true,
            draggable: false,
            resizable: false,
            width: 350
        });

        $(".delete_jurisdiction").on("click", function(e) {
            e.preventDefault();
            var deleteAction = "<?php echo $webappBaseUrl; ?>?selected_page=6&submenu=9&cat=2&delete_id=" + $(this).val();


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

        $(".edit_jurisdiction").on("click", function(e) {
            e.preventDefault();
            var jsonObj = JSON.parse($(this).val());

            if (jsonObj.id) {
                $("#edit_id").val(jsonObj.id);
                $("#edit_name").val(jsonObj.name);
                $("#edit_email").val(jsonObj.email);

                if (jsonObj.link_to_lab == "t") {
                    $("#edit_link_to_lab_yes").trigger("click");
                } else {
                    $("#edit_link_to_lab_no").trigger("click");
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
    $valid_sql = sprintf("SELECT count(id) AS counter FROM %sbatch_notification_custom_jurisdictions WHERE id = %s;", $emsaDbSchemaPrefix, pg_escape_string(intval(trim($_GET['edit_id']))));
    $valid_result = @pg_query($host_pa, $valid_sql) or \Udoh\Emsa\Utils\DisplayUtils::drawError("Unable to save changes to virtual jurisdiction.", true);
    $valid_counter = @pg_fetch_result($valid_result, 0, "counter");
    if ($valid_counter != 1) {
        \Udoh\Emsa\Utils\DisplayUtils::drawError("Unable to save changes to virtual jurisdiction -- record does not exist.");
    } else {
        unset($email_unsafe);
        unset($email_sanitized);
        $email_unsafe_arr = preg_split("/[;,]/", trim($_GET['edit_email']));
        foreach ($email_unsafe_arr as $email_unsafe) {
            if (filter_var(trim($email_unsafe), FILTER_VALIDATE_EMAIL)) {
                $email_sanitized[] = filter_var(trim($email_unsafe), FILTER_SANITIZE_EMAIL);
            }
        }
        $edit_sql = sprintf("UPDATE %sbatch_notification_custom_jurisdictions SET name = %s, recipients = %s, link_to_lab = %s WHERE id = %s;", $emsaDbSchemaPrefix, ((strlen(trim($_GET['edit_name'])) > 0) ? "'" . pg_escape_string(trim($_GET['edit_name'])) . "'" : "NULL"), ((isset($email_sanitized) && (count($email_sanitized) > 0)) ? "'" . pg_escape_string(implode(';', $email_sanitized)) . "'" : "NULL"), ((trim($_GET['edit_link_to_lab']) == 'true') ? 'TRUE' : 'FALSE'), intval(trim($_GET['edit_id']))
        );
        if (@pg_query($host_pa, $edit_sql)) {
            \Udoh\Emsa\Utils\DisplayUtils::drawHighlight("Virtual Jurisdiction successfully updated!", "ui-icon-check");
        } else {
            \Udoh\Emsa\Utils\DisplayUtils::drawError("Unable to save changes to Virtual Jurisdiction.");
        }
    }
} elseif (isset($_GET['delete_id'])) {
    ########## delete lab ##########
    // check to see if passed a valid row id...
    $valid_sql = sprintf("SELECT count(id) AS counter FROM %sbatch_notification_custom_jurisdictions WHERE id = %s;", $emsaDbSchemaPrefix, pg_escape_string(intval($_GET['delete_id'])));
    $valid_result = @pg_query($host_pa, $valid_sql) or \Udoh\Emsa\Utils\DisplayUtils::drawError("Unable to delete Virtual Jurisdiction.", true);
    $valid_counter = @pg_fetch_result($valid_result, 0, "counter");
    if ($valid_counter != 1) {
        \Udoh\Emsa\Utils\DisplayUtils::drawError("Unable to delete Virtual Jurisdiction -- record not found.");
    } else {
        // check for alias labs that depend on this row, throw a dependency warning instead of deleting...
        //$dependency_sql = sprintf("SELECT count(alias_for) AS counter FROM %sbatch_notification_custom_jurisdictions WHERE alias_for = %s;", $my_db_schema, pg_escape_string(intval($_GET['delete_id'])));
        //$dependency_result = @pg_query($host_pa, $dependency_sql) or suicide("Unable to delete Virtual Jurisdiction.", 1, 1);
        //$dependency_count = @pg_fetch_result($dependency_result, 0, "counter");
        $dependency_count = 0;
        if ($dependency_count > 0) {
            \Udoh\Emsa\Utils\DisplayUtils::drawError("Unable to delete Virtual Jurisdiction -- " . intval($dependency_count) . " rule" . (($dependency_count > 1) ? "s " : " ") . "use this Virtual Jurisdiction.  Please edit/delete any rules using this Virtual Jurisdiction first and try again.");
        } else {
            // everything checks out, commit the delete...
            $delete_sql = sprintf("DELETE FROM %sbatch_notification_custom_jurisdictions WHERE id = %s;", $emsaDbSchemaPrefix, pg_escape_string(intval($_GET['delete_id'])));
            if (@pg_query($host_pa, $delete_sql)) {
                \Udoh\Emsa\Utils\DisplayUtils::drawHighlight("Virtual Jurisdiction successfully deleted!", "ui-icon-check");
            } else {
                \Udoh\Emsa\Utils\DisplayUtils::drawError("Unable to delete Virtual Jurisdiction.");
            }
        }
    }
} elseif (isset($_GET['add_flag'])) {
    // add new lab
    if (strlen(trim($_GET['new_name'])) > 0) {
        unset($email_unsafe);
        unset($email_sanitized);
        $email_unsafe_arr = preg_split("/[;,]/", trim($_GET['new_email']));
        foreach ($email_unsafe_arr as $email_unsafe) {
            if (filter_var(trim($email_unsafe), FILTER_VALIDATE_EMAIL)) {
                $email_sanitized[] = filter_var(trim($email_unsafe), FILTER_SANITIZE_EMAIL);
            }
        }
        $addlab_sql = sprintf("INSERT INTO %sbatch_notification_custom_jurisdictions (name, recipients, link_to_lab) VALUES (%s, %s, %s)", $emsaDbSchemaPrefix, "'" . pg_escape_string(trim($_GET['new_name'])) . "'", ((isset($email_sanitized) && (count($email_sanitized) > 0)) ? "'" . pg_escape_string(implode(';', $email_sanitized)) . "'" : "NULL"), ((trim($_GET['new_link_to_lab']) == 'true') ? 'TRUE' : 'FALSE')
        );
        @pg_query($host_pa, $addlab_sql) or \Udoh\Emsa\Utils\DisplayUtils::drawError("Could not add new Virtual Jurisdiction.");
        \Udoh\Emsa\Utils\DisplayUtils::drawHighlight("New Virtual Jurisdiction \"" . \Udoh\Emsa\Utils\DisplayUtils::xSafe(trim($_GET['new_name'])) . "\" added successfully!");
    } else {
        \Udoh\Emsa\Utils\DisplayUtils::drawError("No Virtual Jurisdiction name specified!  Enter a name and try again.");
    }
}
?>

<h1 class="elrhdg"><span class="ui-icon ui-icon-header ui-icon-elrlab"></span>Virtual Jurisdiction Management</h1>

<div class="emsa_search_controls ui-tabs ui-widget">
    <button id="addnew_button" class="addnew_button_right" title="Add a new 'virtual' jurisdiction for notifications">Add New Virtual Jurisdiction</button>
    <div style="float: right; width: 50%; font-style: italic; font-family: 'Open Sans', Arial, Helvetica, sans-serif; margin: 5px;">
        "Virtual Jurisdictions" allow custom notifications to be sent to a specific distribution list that may be different than the State-level or Jurisdiction-level recipients.  For example, a set of notifications for a specific condition that need to be sent directly to specific Epidemiologists.
    </div>
</div>

<div id="addnew_form" class="addnew_lab ui-widget ui-widget-content ui-corner-all">
    <div style="clear: both;"><span class="emsa_form_heading">Add New Virtual Jurisdiction:</span><br><br></div>
    <form id="new_lab_form" method="GET">
        <label class="vocab_search_form2" for="new_name">Name:</label><input class="ui-corner-all" type="text" name="new_name" id="new_name" />
        <label class="vocab_search_form2" for="new_email">E-mail Address(es):</label><input style="width: 20em;" class="ui-corner-all" type="text" name="new_email" id="new_email" title="Separate multiple addresses with commas or semicolons" />
        <br><br>

        <fieldset>
            <legend style="font-size: 11pt; position: relative; float: left;">Include Link to ELR Message in E-mails?</legend>
            <label class="vocab_search_form2" for="new_link_to_lab_yes"><input class="edit_radio ui-corner-all" type="radio" name="new_link_to_lab" id="new_link_to_lab_yes" value="true" /> Yes</label>
            <label class="vocab_search_form2" for="new_link_to_lab_no"><input class="edit_radio ui-corner-all" type="radio" name="new_link_to_lab" id="new_link_to_lab_no" value="false" /> No</label>
        </fieldset>

        <input type="hidden" name="selected_page" value="<?php echo intval($navSelectedPage); ?>" />
        <input type="hidden" name="submenu" value="<?php echo intval($navSubmenu); ?>" />
        <input type="hidden" name="cat" value="<?php echo intval($navCat); ?>" />
        <input type="hidden" name="add_flag" value="1" />
        <br><br><button type="submit" name="new_savevirtual" id="new_savevirtual">Save New Virtual Jurisdiction</button>
        <button type="button" id="addnew_cancel">Cancel</button>
    </form>
</div>

<div class="lab_results_container ui-widget ui-corner-all">
    <table id="labResults">
        <thead>
            <tr>
                <th>Actions</th>
                <th>Virtual Jurisdiction Name</th>
                <th>E-mail Addresses</th>
                <th>Include Link to ELR Message in E-mails?</th>
            </tr>
        </thead>
        <tbody>

<?php
$qry = 'SELECT * from ' . $emsaDbSchemaPrefix . 'batch_notification_custom_jurisdictions ORDER BY name';
$rs = pg_query($host_pa, $qry) or die("Could not connect to database: " . pg_last_error());

while ($row = pg_fetch_object($rs)) {
    unset($recipient_arr);
    foreach (explode(';', $row->recipients) as $email_address) {
        if (filter_var(trim($email_address), FILTER_VALIDATE_EMAIL)) {
            $recipient_arr[] = filter_var(trim($email_address), FILTER_SANITIZE_EMAIL);
        }
    }

    echo "<tr>";
    echo "<td style=\"white-space: nowrap;\" class=\"action_col\">";
    unset($edit_lab_params);
    $edit_lab_params = array(
        "id" => intval($row->id),
        "name" => \Udoh\Emsa\Utils\DisplayUtils::xSafe($row->name),
        "email" => \Udoh\Emsa\Utils\DisplayUtils::xSafe($row->recipients),
        "link_to_lab" => \Udoh\Emsa\Utils\DisplayUtils::xSafe(trim($row->link_to_lab))
    );
    printf("<button class=\"edit_jurisdiction\" type=\"button\" value='%s' title=\"Edit this virtual jurisdiction\">Edit</button>", json_encode($edit_lab_params));
    printf("<button class=\"delete_jurisdiction\" type=\"button\" value=\"%s\" title=\"Permanently delete this virtual jurisdiction\">Delete</button>", intval($row->id));
    echo "</td>";
    echo "<td>" . \Udoh\Emsa\Utils\DisplayUtils::xSafe($row->name) . "</td>";
    echo "<td>" . ((count($recipient_arr) > 0) ? implode("<br>", $recipient_arr) : '<em style="color: lightgray;">&mdash;No Recipients&mdash;</em>') . "</td>";
    echo "<td>" . ((trim($row->link_to_lab) == "t") ? "<span class=\"ui-icon ui-icon-elrsuccess\" title=\"Yes\"></span>" : "<span class=\"ui-icon ui-icon-elrcancel\" title=\"No\"></span>") . "</td>";
    echo "</tr>";
}

pg_free_result($rs);
?>

        </tbody>
    </table>

</div>

<div id="confirm_delete_dialog" title="Delete this Virtual Jurisdiction?">
    <p><span class="ui-icon ui-icon-elrerror" style="float:left; margin:0 7px 50px 0;"></span>This Virtual Jurisdiction will be permanently deleted and cannot be recovered. Are you sure?</p>
</div>

<div id="edit_lab_dialog" title="Edit Virtual Jurisdiction">
    <form id="edit_modal_form" method="GET">
        <label for="edit_name">Name:</label><br><input class="ui-corner-all" type="text" name="edit_name" id="edit_name" /><br><br>
        <label for="edit_email">E-mail Address(es):</label><br><textarea style="background-color: lightcyan; font-family: Consolas, 'Courier New'; font-weight: 400; font-size: 10pt; line-height: 14pt; width: 100%; height: 10em;" class="ui-corner-all" name="edit_email" id="edit_email"></textarea><br><br>

        <fieldset>
            <legend>Include Link to ELR Message in E-mails?</legend>
            <label for="edit_link_to_lab_yes"><input class="edit_radio ui-corner-all" type="radio" name="edit_link_to_lab" id="edit_link_to_lab_yes" value="true" /> Yes</label>
            <label for="edit_link_to_lab_no"><input class="edit_radio ui-corner-all" type="radio" name="edit_link_to_lab" id="edit_link_to_lab_no" value="false" /> No</label>
        </fieldset>

        <input type="hidden" name="edit_id" id="edit_id" />
        <input type="hidden" name="selected_page" value="<?php echo intval($navSelectedPage); ?>" />
        <input type="hidden" name="submenu" value="<?php echo intval($navSubmenu); ?>" />
        <input type="hidden" name="cat" value="<?php echo intval($navCat); ?>" />
    </form>
</div>
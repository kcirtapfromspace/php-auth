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
            $("#clone_form").hide();
            $(".import_error").hide();
            $("#clone_button").hide();
            $("#new_labname").trigger("focus");
            $(this).hide();
        });

        $("#addnew_cancel").button({
            icon: "ui-icon-elrcancel"
        }).on("click", function() {
            $("#addnew_form").hide();
            $("#addnew_button").show();
            $("#clone_button").show();
        });

        $("#new_savelab").button({
            icon: "ui-icon-elrsave"
        });

        $("#clone_button").button({
            icon: "ui-icon-elrcopy"
        }).on("click", function() {
            $("#clone_form").show();
            $("#addnew_form").hide();
            $("#addnew_button").hide();
            $(".import_error").hide();
            $("#clone_labname").trigger("focus");
            $(this).hide();
        });

        $("#clone_cancel").button({
            icon: "ui-icon-elrcancel"
        }).on("click", function() {
            $("#addnew_form").hide();
            $("#clone_form").hide();
            $("#addnew_button").show();
            $("#clone_button").show();
        });

        $("#clone_savelab").button({
            icon: "ui-icon-elrsave"
        });

        $(".edit_lab").button({
            icon: "ui-icon-elrpencil",
            showLabel: false
        }).on("click", function(e) {
            e.preventDefault();
            let jsonObj = JSON.parse($(this).val());

            if (jsonObj.lab_id) {
                $("#edit_id").val(jsonObj.lab_id);
                $("#edit_labname").val(jsonObj.lab_name);
                $("#edit_hl7name").val(jsonObj.hl7_name);
                $("#edit_alias").val(jsonObj.alias);
                $("#edit_visible").val(jsonObj.visible);
                $("#edit_ecrlab").val(jsonObj.ecrlab);
                $("#edit_district").val(jsonObj.district);

                $("#edit_lab_dialog")
                    .dialog('option', 'buttons', {
                        "Save Changes": function () {
                            $(this).dialog("close");
                            $("#edit_modal_form").trigger("submit");
                        },
                        "Cancel": function () {
                            $(this).dialog("close");
                        }
                    }).dialog("open");
            } else {
                return false;
            }
        });

        $(".delete_lab").button({
            icon: "ui-icon-elrclose",
            showLabel: false
        }).on("click", function(e) {
            e.preventDefault();
            let deleteAction = "<?php echo $webappBaseUrl; ?>?selected_page=6&submenu=4&cat=1&delete_id=" + $(this).val();


            $("#confirm_delete_dialog")
                .dialog('option', 'buttons', {
                    "Delete": function () {
                        window.location.href = deleteAction;
                    },
                    "Cancel": function () {
                        $(this).dialog("close");
                    }
                }).dialog("open");

        });

        $(".edit_hl7").button({
            icon: "ui-icon-elrxml-small"
        }).on("click", function() {
            window.location.href = "<?php echo $webappBaseUrl; ?>?selected_page=6&submenu=4&cat=2&subcat=5&f[lab][]=" + $(this).val();
        });

        $(".edit_xslt").button({
            icon: "ui-icon-emsaxmldoc16"
        }).on("click", function() {
            window.location.href = "<?php echo $webappBaseUrl; ?>?selected_page=6&submenu=4&cat=2&subcat=9&f[lab][]=" + $(this).val();
        });

        $(".button_disabled").button("option", "disabled", true);

        $("#confirm_delete_dialog").dialog({
            autoOpen: false,
            modal: true,
            draggable: false,
            resizable: false
        });

        $("#edit_lab_dialog").dialog({
            autoOpen: false,
            modal: true
        });

    });
</script>

<?php
if (isset($_GET['edit_id'])) {
    // check to see if passed a valid row id...
    $valid_sql = sprintf("SELECT count(id) AS counter FROM %sstructure_labs WHERE id = %s;", $emsaDbSchemaPrefix, pg_escape_string(intval(trim($_GET['edit_id']))));
    $valid_result = @pg_query($host_pa, $valid_sql) or \Udoh\Emsa\Utils\DisplayUtils::drawError("Unable to save changes to lab.", true);
    $valid_counter = @pg_fetch_result($valid_result, 0, "counter");
    if ($valid_counter != 1) {
        \Udoh\Emsa\Utils\DisplayUtils::drawError("Unable to save changes to lab -- lab does not exist.");
    } else {
        $edit_sql = sprintf("UPDATE %sstructure_labs SET ui_name = %s, hl7_name = %s, alias_for = %s, visible = %s, ecrlab = %s, default_jurisdiction_id = %s WHERE id = %s;", $emsaDbSchemaPrefix, ((strlen(trim($_GET['edit_labname'])) > 0) ? "'" . pg_escape_string(trim($_GET['edit_labname'])) . "'" : "NULL"), ((strlen(trim($_GET['edit_hl7name'])) > 0) ? "'" . pg_escape_string(trim($_GET['edit_hl7name'])) . "'" : "NULL"), ((intval(trim($_GET['edit_alias'])) > 0) ? intval(trim($_GET['edit_alias'])) : 0), ((trim($_GET['edit_visible']) == "t") ? "true" : "false"), ((trim($_GET['edit_ecrlab']) == "t") ? "true" : "false"), ((intval(trim($_GET['edit_district'])) > 0) ? intval(trim($_GET['edit_district'])) : "NULL"), intval(trim($_GET['edit_id']))
        );
        if (@pg_query($host_pa, $edit_sql)) {
            \Udoh\Emsa\Utils\DisplayUtils::drawHighlight("Lab successfully updated!", "ui-icon-check");
        } else {
            \Udoh\Emsa\Utils\DisplayUtils::drawError("Unable to save changes to lab.");
        }
    }
} elseif (isset($_GET['delete_id'])) {
    ########## delete lab ##########
    // check to see if passed a valid row id...
    $valid_sql = sprintf("SELECT count(id) AS counter FROM %sstructure_labs WHERE id = %s;", $emsaDbSchemaPrefix, pg_escape_string(intval($_GET['delete_id'])));
    $valid_result = @pg_query($host_pa, $valid_sql) or \Udoh\Emsa\Utils\DisplayUtils::drawError("Unable to delete lab.", true);
    $valid_counter = @pg_fetch_result($valid_result, 0, "counter");
    if ($valid_counter != 1) {
        \Udoh\Emsa\Utils\DisplayUtils::drawError("Unable to delete lab -- record not found.");
    } else {
        // check for alias labs that depend on this row, throw a dependency warning instead of deleting...
        $dependency_sql = sprintf("SELECT count(alias_for) AS counter FROM %sstructure_labs WHERE alias_for = %s;", $emsaDbSchemaPrefix, pg_escape_string(intval($_GET['delete_id'])));
        $dependency_result = @pg_query($host_pa, $dependency_sql) or \Udoh\Emsa\Utils\DisplayUtils::drawError("Unable to delete lab.", true);
        $dependency_count = @pg_fetch_result($dependency_result, 0, "counter");
        if ($dependency_count > 0) {
            \Udoh\Emsa\Utils\DisplayUtils::drawError("Unable to delete lab -- " . intval($dependency_count) . " alias" . (($dependency_count > 1) ? "es of this lab exist" : " of this lab exists") . ".  Please delete any aliases for this lab first and try again.");
        } else {
            // everything checks out, commit the delete...
            $delete_sql = sprintf("DELETE FROM %sstructure_labs WHERE id = %s;", $emsaDbSchemaPrefix, pg_escape_string(intval($_GET['delete_id'])));
            if (@pg_query($host_pa, $delete_sql)) {
                \Udoh\Emsa\Utils\DisplayUtils::drawHighlight("Lab successfully deleted!", "ui-icon-check");
            } else {
                \Udoh\Emsa\Utils\DisplayUtils::drawError("Unable to delete lab.");
            }
        }
    }
} elseif (isset($_GET['add_flag'])) {
    // add new lab
    if (strlen(trim($_GET['new_labname'])) > 0) {
        $addlab_sql = sprintf("INSERT INTO %sstructure_labs (ui_name, hl7_name, alias_for, visible, default_jurisdiction_id, ecrlab) VALUES (%s, %s, %d, %s, %s, %s)", $emsaDbSchemaPrefix, "'" . pg_escape_string(trim($_GET['new_labname'])) . "'", ((strlen(trim($_GET['new_hl7name'])) > 0) ? "'" . pg_escape_string(trim($_GET['new_hl7name'])) . "'" : "NULL"), ((intval(trim($_GET['new_alias'])) > 0) ? intval(trim($_GET['new_alias'])) : 0), ((trim($_GET['new_visible']) == "t") ? "true" : "false"), ((intval(trim($_GET['new_district'])) > 0) ? intval(trim($_GET['new_district'])) : "NULL"), ((trim($_GET['new_ecrlab']) == "t") ? "true" : "false") );
        @pg_query($host_pa, $addlab_sql) or \Udoh\Emsa\Utils\DisplayUtils::drawError("Could not add new lab.");
        \Udoh\Emsa\Utils\DisplayUtils::drawHighlight("New lab \"" . \Udoh\Emsa\Utils\DisplayUtils::xSafe(trim($_GET['new_labname'])) . "\" added successfully!");
    } else {
        \Udoh\Emsa\Utils\DisplayUtils::drawError("No lab name specified!  Enter a lab name and try again.");
    }
} elseif (isset($_GET['clone_flag'])) {
    // copy an existing lab to a new lab
    if ((intval($_GET['clone_source_id']) > 0) && (strlen(trim($_GET['clone_labname'])) > 0)) {
        //todo:  [still need?] bugger!  need to write a lookup routine that can store IDs of child SNOMEDs inserted to link up the child_c2m_testresult rows... blech.
        
//        $cloneReporterAddFacilitySql = "INSERT INTO structure_labs
//            (ui_name, hl7_name, alias_for, visible, default_jurisdiction_id) 
//              VALUES 
//            (:uiName, :hl7Name, :aliasFor, :visible, :defaultJurisdictionId);";
//        $cloneReporterCopyStructureSql = "INSERT INTO ;";
//        $cloneReporterCopyDictionarySql = "INSERT INTO ;";
//        $cloneReporterCopyLoincSql = "INSERT INTO ;";
//        $cloneReporterCopySnomedSql = "INSERT INTO ;";
//        
//        $adminDbConn->beginTransaction();
//        
//        \Udoh\Emsa\Utils\DisplayUtils::drawHighlight("New lab \"" . \Udoh\Emsa\Utils\DisplayUtils::xSafe(trim($_GET['new_labname'])) . "\" added successfully!");
    } else {
        \Udoh\Emsa\Utils\DisplayUtils::drawError("No lab name specified!  Enter a lab name and try again.");
    }
}
?>

<h1 class="elrhdg"><span class="ui-icon ui-icon-header ui-icon-elrlab"></span>Reporting Facility Management</h1>

<div class="emsa_search_controls ui-tabs ui-widget">
    <button id="addnew_button" title="Add a new Reporting Facility">Add New Reporter</button>
<!--    <button id="clone_button" title="Copy an existing Reporting Facility to create a new Reporting Facility, including structure mapping and vocabulary">Clone Existing Reporter</button>-->
    <br>
</div>

<div id="addnew_form" class="addnew_lab ui-widget ui-widget-content ui-corner-all">
    <div style="clear: both;"><span class="emsa_form_heading">Add New Reporter:</span><br><br></div>
    <form id="new_lab_form" method="GET">
        <label class="vocab_search_form2" for="new_labname">Reporting Facility Name:</label><input class="ui-corner-all" type="text" name="new_labname" id="new_labname" />
        <label class="vocab_search_form2" for="new_hl7name">Coded Facility Identifier (e.g. from MSH-4.1):</label><input class="ui-corner-all" type="text" name="new_hl7name" id="new_hl7name" />
        <label class="vocab_search_form2" for="new_district">Default Jurisdiction:</label>
        <select class="ui-corner-all" name="new_district" id="new_district">
            <option value="0" selected>--</option>
<?php
// get list of top-level labs for alias menu
$newdistrict_sql = sprintf("SELECT id, health_district FROM %ssystem_districts WHERE enabled IS TRUE ORDER BY health_district;", $emsaDbSchemaPrefix);
$newdistrict_result = @pg_query($host_pa, $newdistrict_sql) or \Udoh\Emsa\Utils\DisplayUtils::drawError("Unable to retrieve list of Jurisdictions.", true);
while ($newdistrict_row = pg_fetch_object($newdistrict_result)) {
    printf("<option value=\"%d\">%s</option>", intval($newdistrict_row->id), \Udoh\Emsa\Utils\DisplayUtils::xSafe($newdistrict_row->health_district));
}
pg_free_result($newdistrict_result);
?>
        </select><br><br>
        <label class="vocab_search_form2" for="new_ecrlab">eCRs Update Lab Data?</label>
        <select class="ui-corner-all" name="new_ecrlab" id="new_ecrlab">
            <option value="f" selected>No</option>
            <option value="t">Yes</option>
        </select>
        <label class="vocab_search_form2" for="new_visible">Show in UI?</label>
        <select class="ui-corner-all" name="new_visible" id="new_visible">
            <option value="f" selected>No</option>
            <option value="t">Yes</option>
        </select>
        <label class="vocab_search_form2" for="new_alias">Alias For:</label>
        <select class="ui-corner-all" name="new_alias" id="new_alias">
            <option value="0" selected>--</option>
            <?php
            // get list of top-level labs for alias menu
            $newalias_sql = sprintf("SELECT DISTINCT id, ui_name FROM %sstructure_labs WHERE alias_for < 1 ORDER BY ui_name;", $emsaDbSchemaPrefix);
            $newalias_result = @pg_query($host_pa, $newalias_sql) or \Udoh\Emsa\Utils\DisplayUtils::drawError("Unable to retrieve list of aliases.", true);
            while ($newalias_row = pg_fetch_object($newalias_result)) {
                printf("<option value=\"%d\">%s</option>", intval($newalias_row->id), \Udoh\Emsa\Utils\DisplayUtils::xSafe($newalias_row->ui_name));
            }
            pg_free_result($newalias_result);
            ?>
        </select>
        <input type="hidden" name="selected_page" value="<?php echo intval($navSelectedPage); ?>" />
        <input type="hidden" name="submenu" value="<?php echo intval($navSubmenu); ?>" />
        <input type="hidden" name="cat" value="<?php echo intval($navCat); ?>" />
        <input type="hidden" name="add_flag" value="1" />
        <br><br><button type="submit" name="new_savelab" id="new_savelab">Save New Reporter</button>
        <button type="button" id="addnew_cancel">Cancel</button>
    </form>
</div>

<div id="clone_form" class="addnew_lab ui-widget ui-widget-content ui-corner-all">
    <div style="clear: both;"><span class="emsa_form_heading">Clone Existing Reporter:</span><br><br></div>
    <form id="clone_lab_form" method="GET">
        <label class="vocab_search_form2" for="clone_source_id">Clone From:</label>
        <select class="ui-corner-all" name="clone_source_id" id="clone_source_id">
            <option value="0" selected>--</option>
<?php
// get list of top-level labs for alias menu
$clonedistrict_sql = sprintf("SELECT id, health_district FROM %ssystem_districts WHERE enabled IS TRUE ORDER BY health_district;", $emsaDbSchemaPrefix);
$clonedistrict_result = @pg_query($host_pa, $clonedistrict_sql) or \Udoh\Emsa\Utils\DisplayUtils::drawError("Unable to retrieve list of Jurisdictions.", true);
while ($clonedistrict_row = pg_fetch_object($clonedistrict_result)) {
    printf("<option value=\"%d\">%s</option>", intval($clonedistrict_row->id), \Udoh\Emsa\Utils\DisplayUtils::xSafe($clonedistrict_row->health_district));
}
pg_free_result($clonedistrict_result);
?>
        </select><br><br>
        
        <label class="vocab_search_form2" for="clone_labname">Reporting Facility Name:</label><input class="ui-corner-all" type="text" name="clone_labname" id="clone_labname" />
        <label class="vocab_search_form2" for="clone_hl7name">Coded Facility Identifier (e.g. from MSH-4.1):</label><input class="ui-corner-all" type="text" name="clone_hl7name" id="clone_hl7name" />
        <label class="vocab_search_form2" for="clone_district">Default Jurisdiction:</label>
        <select class="ui-corner-all" name="clone_district" id="clone_district">
            <option value="0" selected>--</option>
<?php
// get list of top-level labs for alias menu
$clonedistrict_sql = sprintf("SELECT id, health_district FROM %ssystem_districts WHERE enabled IS TRUE ORDER BY health_district;", $emsaDbSchemaPrefix);
$clonedistrict_result = @pg_query($host_pa, $clonedistrict_sql) or \Udoh\Emsa\Utils\DisplayUtils::drawError("Unable to retrieve list of Jurisdictions.", true);
while ($clonedistrict_row = pg_fetch_object($clonedistrict_result)) {
    printf("<option value=\"%d\">%s</option>", intval($clonedistrict_row->id), \Udoh\Emsa\Utils\DisplayUtils::xSafe($clonedistrict_row->health_district));
}
pg_free_result($clonedistrict_result);
?>
        </select><br><br>
        <label class="vocab_search_form2" for="clone_ecrlab">eCRs Update Lab Data?</label>
        <select class="ui-corner-all" name="clone_ecrlab" id="clone_ecrlab">
            <option value="f" selected>No</option>
            <option value="t">Yes</option>
        </select>
        <label class="vocab_search_form2" for="clone_visible">Show in UI?</label>
        <select class="ui-corner-all" name="clone_visible" id="clone_visible">
            <option value="f" selected>No</option>
            <option value="t">Yes</option>
        </select>
        <label class="vocab_search_form2" for="clone_alias">Alias For:</label>
        <select class="ui-corner-all" name="clone_alias" id="clone_alias">
            <option value="0" selected>--</option>
            <?php
            // get list of top-level labs for alias menu
            $clonealias_sql = sprintf("SELECT DISTINCT id, ui_name FROM %sstructure_labs WHERE alias_for < 1 ORDER BY ui_name;", $emsaDbSchemaPrefix);
            $clonealias_result = @pg_query($host_pa, $clonealias_sql) or \Udoh\Emsa\Utils\DisplayUtils::drawError("Unable to retrieve list of aliases.", true);
            while ($clonealias_row = pg_fetch_object($clonealias_result)) {
                printf("<option value=\"%d\">%s</option>", intval($clonealias_row->id), \Udoh\Emsa\Utils\DisplayUtils::xSafe($clonealias_row->ui_name));
            }
            pg_free_result($clonealias_result);
            ?>
        </select>
        <input type="hidden" name="selected_page" value="<?php echo intval($navSelectedPage); ?>" />
        <input type="hidden" name="submenu" value="<?php echo intval($navSubmenu); ?>" />
        <input type="hidden" name="cat" value="<?php echo intval($navCat); ?>" />
        <input type="hidden" name="clone_flag" value="1" />
        <br><br><button type="submit" name="clone_savelab" id="clone_savelab">Save New Reporter</button>
        <button type="button" id="clone_cancel">Cancel</button>
    </form>
</div>

<div class="lab_results_container ui-widget ui-corner-all">
    <table id="labResults">
        <thead>
            <tr>
                <th>Actions</th>
                <th>XML Mapping</th>
                <th>Reporting Facility Name</th>
                <th>Coded Facility Identifier</th>
                <th>Alias For</th>
                <th>eCRs Update Lab Data?</th>
                <th>Show in UI?</th>
                <th>Default Jurisdiction</th>
            </tr>
        </thead>
        <tbody>

<?php
$labQry = "SELECT l.*, j.health_district, l2.ui_name AS alias_for_name, 
           CASE WHEN l.alias_for > 0 THEN l2.ui_name::text || ' - ' || l.ui_name::text WHEN l.alias_for = 0 THEN l.ui_name::text END AS pretty_ui_name 
           FROM structure_labs l 
           LEFT JOIN system_districts j ON (l.default_jurisdiction_id = j.id) 
           LEFT JOIN structure_labs l2 ON (l.alias_for = l2.id)
           ORDER BY pretty_ui_name;";
$labStmt = $adminDbConn->query($labQry);
if ($labStmt !== false) {
    while ($labRow = $labStmt->fetchObject()) {
        $isAlias = intval($labRow->alias_for) > 0;

        if ($isAlias) {
            echo '<tr style="background-color: lavender; font-style: italic;">';
        } else {
            echo '<tr>';
        }

        echo "<td style=\"white-space: nowrap;\" class=\"action_col\">";
        unset($edit_lab_params);
        $edit_lab_params = array(
            "lab_id" => intval($labRow->id),
            "lab_name" => \Udoh\Emsa\Utils\DisplayUtils::xSafe($labRow->ui_name),
            "hl7_name" => \Udoh\Emsa\Utils\DisplayUtils::xSafe($labRow->hl7_name),
            "alias" => intval($labRow->alias_for),
            "visible" => (($labRow->visible) ? 't' : 'f'),
            "ecrlab" => (($labRow->ecrlab) ? 't' : 'f'),
            "district" => intval($labRow->default_jurisdiction_id)
        );
        printf("<button class=\"edit_lab\" type=\"button\" value='%s' title=\"Edit this reporter\">Edit</button>", json_encode($edit_lab_params));
        printf("<button class=\"delete_lab\" type=\"button\" value=\"%s\" title=\"Permanently delete this reporter\">Delete</button>", intval($labRow->id));
        echo "</td>";
        echo "<td style=\"white-space: nowrap;\" class=\"action_col\">";
        if (!$isAlias) {
            printf("<button class=\"edit_hl7\" type=\"button\" value=\"%s\" title=\"%s\">XPath</button>", intval($labRow->id), "Manage XPath-based mapping for this reporter");
            printf("<button class=\"edit_xslt\" type=\"button\" value=\"%s\" title=\"%s\">XSLT</button>", intval($labRow->id), "Manage XSL Transformer-based mapping for this reporter");
        } else {
            echo "&nbsp;";
        }
        echo "</td>";
        echo '<td>' . \Udoh\Emsa\Utils\DisplayUtils::xSafe($labRow->ui_name) . '</td>';
        echo "<td class='mono_prewrap'>" . \Udoh\Emsa\Utils\DisplayUtils::xSafe($labRow->hl7_name) . "</td>";

        echo "<td>";
        if (intval($labRow->alias_for) > 0) {
            \Udoh\Emsa\Utils\DisplayUtils::xEcho($labRow->alias_for_name);
        }
        echo "</td>";
        echo "<td>";
        echo (($isAlias) ? '' : '<span class="ui-icon ' . (($labRow->ecrlab) ? 'ui-icon-elrsuccess' : 'ui-icon-elrcancel') . '"></span>');
        echo "</td>";
        echo "<td>";
        echo (($isAlias) ? '' : '<span class="ui-icon ' . (($labRow->visible) ? 'ui-icon-elrsuccess' : 'ui-icon-elrcancel') . '"></span>');
        echo "</td>";
        echo "<td>" . (($isAlias) ? '' : \Udoh\Emsa\Utils\DisplayUtils::xSafe($labRow->health_district)) . "</td>";
        echo "</tr>";
    }
}

$labStmt = null;
?>

        </tbody>
    </table>

</div>

<div id="confirm_delete_dialog" title="Delete this reporter?">
    <p><span class="ui-icon ui-icon-elrerror" style="float:left; margin:0 7px 50px 0;"></span>This reporter and any associated XML mappings will be permanently deleted and cannot be recovered. Are you sure?</p>
</div>

<div id="edit_lab_dialog" title="Edit Reporting Facility">
    <form id="edit_modal_form" method="GET">
        <label for="edit_labname">Reporting Facility Name:</label><br><input class="ui-corner-all" type="text" name="edit_labname" id="edit_labname" /><br><br>
        <label for="edit_hl7name">Coded Facility Identifier (e.g. from MSH-4.1):</label><br><input class="ui-corner-all" type="text" name="edit_hl7name" id="edit_hl7name" /><br><br>
        <label for="edit_district">Default Jurisdiction:</label><br>
        <select class="ui-corner-all" style="margin: 0px;" name="edit_district" id="edit_district">
            <option value="0" selected>--</option>
            <?php
            // get list of top-level labs for alias menu
            $newdistrict_sql = sprintf("SELECT id, health_district FROM %ssystem_districts WHERE enabled IS TRUE ORDER BY health_district;", $emsaDbSchemaPrefix);
            $newdistrict_result = @pg_query($host_pa, $newdistrict_sql) or \Udoh\Emsa\Utils\DisplayUtils::drawError("Unable to retrieve list of Jurisdictions.", true);
            while ($newdistrict_row = pg_fetch_object($newdistrict_result)) {
                printf("<option value=\"%d\">%s</option>", intval($newdistrict_row->id), \Udoh\Emsa\Utils\DisplayUtils::xSafe($newdistrict_row->health_district));
            }
            pg_free_result($newdistrict_result);
            ?>
        </select><br><br>
        <label for="edit_ecrlab">eCRs Update Lab Data?</label>
        <select class="ui-corner-all" name="edit_ecrlab" id="edit_ecrlab">
            <option value="f" selected>No</option>
            <option value="t">Yes</option>
        </select><br><br>
        <label for="edit_visible">Show in UI?</label>
        <select class="ui-corner-all" name="edit_visible" id="edit_visible">
            <option value="f" selected>No</option>
            <option value="t">Yes</option>
        </select><br><br>
        <label for="edit_alias">Alias For:</label><br>
        <select class="ui-corner-all" style="margin: 0px;" name="edit_alias" id="edit_alias">
            <option value="0" selected>--</option>
            <?php
            // get list of top-level labs for alias menu
            $newalias_sql = sprintf("SELECT DISTINCT id, ui_name FROM %sstructure_labs WHERE alias_for < 1 ORDER BY ui_name;", $emsaDbSchemaPrefix);
            $newalias_result = @pg_query($host_pa, $newalias_sql) or \Udoh\Emsa\Utils\DisplayUtils::drawError("Unable to retrieve list of aliases.", true);
            while ($newalias_row = pg_fetch_object($newalias_result)) {
                printf("<option value=\"%d\">%s</option>", intval($newalias_row->id), \Udoh\Emsa\Utils\DisplayUtils::xSafe($newalias_row->ui_name));
            }
            pg_free_result($newalias_result);
            ?>
        </select>
        <input type="hidden" name="edit_id" id="edit_id" />
        <input type="hidden" name="selected_page" value="<?php echo intval($navSelectedPage); ?>" />
        <input type="hidden" name="submenu" value="<?php echo intval($navSubmenu); ?>" />
        <input type="hidden" name="cat" value="<?php echo intval($navCat); ?>" />
    </form>
</div>
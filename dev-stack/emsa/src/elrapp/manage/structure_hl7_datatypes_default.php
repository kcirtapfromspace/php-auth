<?php
/**
 * Copyright (c) 2017 Utah Department of Technology Services and Utah Department of Health
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
 * @copyright Copyright (c) 2017 Utah Department of Technology Services and Utah Department of Health
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
            $("#new_name").trigger("focus");
            $(this).hide();
        });

        $("#addnew_cancel").button({
            icon: "ui-icon-elrcancel"
        }).on("click", function() {
            $("#addnew_form").hide();
            $("#addnew_button").show();
        });

        $("#new_savedatatype").button({
            icon: "ui-icon-elrsave"
        });

        $(".edit_datatype").button({
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

        $(".delete_datatype").on("click", function(e) {
            e.preventDefault();
            var deleteAction = "<?php echo $webappBaseUrl; ?>?selected_page=6&submenu=4&cat=10&subcat=1&delete_id=" + $(this).val();


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

        $("#edit_datatype_dialog").dialog({
            autoOpen: false,
            modal: true
        });

        $(".edit_datatype").on("click", function(e) {
            e.preventDefault();
            var jsonObj = JSON.parse($(this).val());

            if (jsonObj.id) {
                $("#edit_id").val(jsonObj.id);
                $("#edit_name").val(jsonObj.name);
                
                if (jsonObj.interpret_results === "t") {
                    $("#edit_interpret_results_yes").trigger("click");
                } else {
                    $("#edit_interpret_results_no").trigger("click");
                }
                
                $("#edit_datatype_dialog").dialog('option', 'buttons', {
                    "Save Changes": function() {
                        $(this).dialog("close");
                        $("#edit_modal_form").trigger("submit");
                    },
                    "Cancel": function() {
                        $(this).dialog("close");
                    }
                });

                $("#edit_datatype_dialog").dialog("open");
            } else {
                return false;
            }
        });
        
		$("th > .ui-icon").tooltip();
	});
</script>
<style type="text/css">
    legend { font-weight: 600; }
    #new_lab_form legend { margin-left: 15px; margin-right: 5px; }
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

<h1 class="elrhdg"><span class="ui-icon ui-icon-header ui-icon-emsasettings"></span>Default HL7 Data Type Configuration</h1>

<div id="date_filter" style="position: absolute; z-index: 999; top: 43px; right: 15px;">
	<button id="addnew_button" title="Set up a new default HL7 data type">Add Default HL7 Data Type</button>
</div>

<?php

    /* @var $adminDbConn \PDO */
	
	// process jurisdiction actions
    $datatypeEditId = (int) filter_input(INPUT_GET, 'edit_id', FILTER_SANITIZE_NUMBER_INT);
    $datatypeDeleteId = (int) filter_input(INPUT_GET, 'delete_id', FILTER_SANITIZE_NUMBER_INT);
    $datatypeAddFlag = (int) filter_input(INPUT_GET, 'add_flag', FILTER_SANITIZE_NUMBER_INT);
    
    $datatypeActionTypeName = (string) filter_input(INPUT_GET, 'name', FILTER_SANITIZE_STRING, FILTER_FLAG_NO_ENCODE_QUOTES);
    $datatypeActionInterpretResults = ((string) filter_input(INPUT_GET, 'interpret_results', FILTER_SANITIZE_STRING) == 'true') ? true : false;
    
    if ($datatypeAddFlag === 1) {
        try {
            $addSql = "INSERT INTO structure_hl7_valuetype_defaults (value_type, default_interpret_results)
                       VALUES (:valueType, :defaultInterp);";
            $addStmt = $adminDbConn->prepare($addSql);
            $addStmt->bindValue(':valueType', $datatypeActionTypeName, PDO::PARAM_STR);
            $addStmt->bindValue(':defaultInterp', $datatypeActionInterpretResults, PDO::PARAM_BOOL);
            
            $addStmt->execute();
            
            Udoh\Emsa\Utils\DisplayUtils::drawHighlight('New default data type added successfully!', 'ui-icon-elrsuccess');
        } catch (Throwable $e) {
            \Udoh\Emsa\Utils\ExceptionUtils::logException($e);
            Udoh\Emsa\Utils\DisplayUtils::drawError('An error occurred while attempting to add a new default data type.');
        }
    } elseif ($datatypeDeleteId > 0) {
        try {
            $deleteSql = "DELETE FROM structure_hl7_valuetype_defaults 
                          WHERE id = :deleteId;";
            $deleteStmt = $adminDbConn->prepare($deleteSql);
            $deleteStmt->bindValue(':deleteId', $datatypeDeleteId, PDO::PARAM_INT);
            
            $deleteStmt->execute();
            
            Udoh\Emsa\Utils\DisplayUtils::drawHighlight('Default data type successfully deleted!', 'ui-icon-elrsuccess');
        } catch (Throwable $e) {
            \Udoh\Emsa\Utils\ExceptionUtils::logException($e);
            Udoh\Emsa\Utils\DisplayUtils::drawError('An error occurred while attempting to delete this default data type.');
        }
    } elseif ($datatypeEditId > 0) {
        try {
            $updateSql = "UPDATE structure_hl7_valuetype_defaults
                          SET value_type = :valueType, default_interpret_results = :defaultInterp
                          WHERE id = :editId;";
            $updateStmt = $adminDbConn->prepare($updateSql);
            $updateStmt->bindValue(':valueType', $datatypeActionTypeName, PDO::PARAM_STR);
            $updateStmt->bindValue(':defaultInterp', $datatypeActionInterpretResults, PDO::PARAM_BOOL);
            $updateStmt->bindValue(':editId', $datatypeEditId, PDO::PARAM_INT);
            
            $updateStmt->execute();
            
            Udoh\Emsa\Utils\DisplayUtils::drawHighlight('Default data type successfully updated!', 'ui-icon-elrsuccess');
        } catch (Throwable $e) {
            \Udoh\Emsa\Utils\ExceptionUtils::logException($e);
            Udoh\Emsa\Utils\DisplayUtils::drawError('An error occurred while attempting to update this default data type.');
        }
    }

?>

<div id="addnew_form" class="addnew_lab ui-widget ui-widget-content ui-corner-all">
    <div style="clear: both;"><span class="emsa_form_heading">Add New Default Data Type:</span><br><br></div>
    <form id="new_lab_form" method="GET">
        <label class="vocab_search_form2" for="new_name">Data Type:</label><input class="ui-corner-all" type="text" name="name" id="new_name" />
        <br><br>
        
        <fieldset>
            <legend style="font-size: 11pt; position: relative; float: left;">Qn Interpretation?</legend>
            <label class="vocab_search_form2" for="new_interpret_results_yes"><input checked="checked" class="edit_radio ui-corner-all" type="radio" name="interpret_results" id="new_interpret_results_yes" value="true" /> Yes</label>
            <label class="vocab_search_form2" for="new_interpret_results_no"><input class="edit_radio ui-corner-all" type="radio" name="interpret_results" id="new_interpret_results_no" value="false" /> No</label>
        </fieldset>
        
        <input type="hidden" name="selected_page" value="<?php echo intval($navSelectedPage); ?>" />
        <input type="hidden" name="submenu" value="<?php echo intval($navSubmenu); ?>" />
        <input type="hidden" name="cat" value="<?php echo intval($navCat); ?>" />
        <input type="hidden" name="subcat" value="<?php echo intval($navSubcat); ?>" />
        <input type="hidden" name="add_flag" value="1" />
        <br><br><button type="submit" name="new_savedatatype" id="new_savedatatype">Save New Default Data Type</button>
        <button type="button" id="addnew_cancel">Cancel</button>
    </form>
</div>



<div class="lab_results_container ui-widget ui-corner-all">
	<table id="labResults">
		<thead>
			<tr>
				<th style="text-align: center;">Actions</th>
				<th style="width: 40%;">Data Type</th>
                <th>Qn Interpretation?</th>
			</tr>
		</thead>
		<tbody>

<?php
	
	$currentSql = 'SELECT id, value_type, default_interpret_results
		FROM structure_hl7_valuetype_defaults
        ORDER BY value_type;';
	$currentRs = $adminDbConn->query($currentSql);
	
	while ($row = $currentRs->fetchObject()) {
        echo "<tr>";
		echo '<td style="text-align: center; white-space: nowrap;" class="action_col">';
		
        $editLhdParams = array(
            "id" => (int) $row->id,
            "name" => \Udoh\Emsa\Utils\DisplayUtils::xSafe((string) $row->value_type),
            "interpret_results" => ((bool) $row->default_interpret_results === true) ? 't' : 'f'
        );
        
        echo "<button class='edit_datatype' type='button' value='" . json_encode($editLhdParams) . "' title='Edit this Default Data Type'>Edit</button>";
        echo "<button class='delete_datatype' type='button' value='" . (int) $row->id . "' title='Permanently delete this Default Data Type'>Delete</button>";
        
		echo "</td>";
		echo "<td>" . \Udoh\Emsa\Utils\DisplayUtils::xSafe((string) $row->value_type) . "</td>";
        
        if ((bool) $row->default_interpret_results) {
            echo '<td><span class="ui-icon ui-icon-elrsuccess" title="Yes"></span></td>';
        } else {
            echo '<td><span class="ui-icon ui-icon-elrcancel" title="No"></span></td>';
        }
        
        echo "</tr>";
	}

?>

		</tbody>
	</table>
	
</div>

<div id="confirm_delete_dialog" title="Delete this Default Data Type?">
    <p><span class="ui-icon ui-icon-elrerror" style="float:left; margin:0 7px 50px 0;"></span>This record will be permanently deleted and cannot be recovered. Are you sure?</p>
</div>

<div id="edit_datatype_dialog" title="Edit Default Data Type">
    <form id="edit_modal_form" method="GET">
        <label for="edit_name">Data Type</label><br><input class="ui-corner-all" type="text" name="name" id="edit_name" />
        <br><br>
        
        <fieldset>
            <legend>Qn Interpretation?</legend>
            <label class="vocab_search_form2" for="edit_interpret_results_yes"><input class="edit_radio ui-corner-all" type="radio" name="interpret_results" id="edit_interpret_results_yes" value="true" /> Yes</label>
            <label class="vocab_search_form2" for="edit_interpret_results_no"><input class="edit_radio ui-corner-all" type="radio" name="interpret_results" id="edit_interpret_results_no" value="false" /> No</label>
        </fieldset>
        
        <input type="hidden" name="edit_id" id="edit_id" />
        <input type="hidden" name="selected_page" value="<?php echo intval($navSelectedPage); ?>" />
        <input type="hidden" name="submenu" value="<?php echo intval($navSubmenu); ?>" />
        <input type="hidden" name="cat" value="<?php echo intval($navCat); ?>" />
        <input type="hidden" name="subcat" value="<?php echo intval($navSubcat); ?>" />
    </form>
</div>
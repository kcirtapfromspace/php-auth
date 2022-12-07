<?php
/**
 * Copyright (c) 2019 Utah Department of Technology Services and Utah Department of Health
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
 * @copyright Copyright (c) 2019 Utah Department of Technology Services and Utah Department of Health
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
        
        let editDatatypeBtn = $(".edit_datatype");

        editDatatypeBtn.button({
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
            let deleteAction = "<?php echo $webappBaseUrl; ?>?selected_page=6&submenu=30&cat=14&delete_id=" + $(this).val();


            $("#confirm_delete_dialog")
                .dialog('option', 'buttons', {
                    "Delete": function () {
                        window.location.href = deleteAction;
                    },
                    "Cancel": function () {
                        $(this).dialog("close");
                    }
                })
                .dialog("open");

        });

        $("#edit_datatype_dialog").dialog({
            autoOpen: false,
            modal: true
        });

        editDatatypeBtn.on("click", function(e) {
            e.preventDefault();
            let jsonObj = JSON.parse($(this).val());

            if (jsonObj.id) {
                $("#edit_id").val(jsonObj.id);
                $("#edit_name").val(jsonObj.name);
                
                if (jsonObj.interpret_results === "t") {
                    $("#edit_interpret_results_yes").trigger("click");
                } else {
                    $("#edit_interpret_results_no").trigger("click");
                }

                $("#edit_datatype_dialog")
                    .dialog('option', 'buttons', {
                        "Save Changes": function () {
                            $(this).dialog("close");
                            $("#edit_modal_form").trigger("submit");
                        },
                        "Cancel": function () {
                            $(this).dialog("close");
                        }
                    })
                    .dialog("open");
            } else {
                return false;
            }
        });
        
		$("th > .ui-icon").tooltip();
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

<h1 class="elrhdg"><span class="ui-icon ui-icon-header ui-icon-elrcategory"></span>Name-Based "Pending" Queue Watchlist</h1>

<div id="date_filter" style="position: absolute; z-index: 999; top: 43px; right: 15px;">
	<button id="addnew_button" title="Add a new name to Watchlist">Add Name</button>
</div>

<?php

    /* @var $adminDbConn \PDO */
	
	// process actions
    $watchlistEditId = (int) filter_input(INPUT_GET, 'edit_id', FILTER_SANITIZE_NUMBER_INT);
    $watchlistDeleteId = (int) filter_input(INPUT_GET, 'delete_id', FILTER_SANITIZE_NUMBER_INT);
    $watchlistAddFlag = (int) filter_input(INPUT_GET, 'add_flag', FILTER_SANITIZE_NUMBER_INT);
    
    $watchlistActionName = (string) filter_input(INPUT_GET, 'name', FILTER_SANITIZE_STRING, FILTER_FLAG_NO_ENCODE_QUOTES);
    
    if ($watchlistAddFlag === 1) {
        try {
            $addSql = "INSERT INTO pending_watch_list (lname)
                       VALUES (:lname);";
            $addStmt = $adminDbConn->prepare($addSql);
            $addStmt->bindValue(':lname', $watchlistActionName, PDO::PARAM_STR);
            
            $addStmt->execute();
            
            Udoh\Emsa\Utils\DisplayUtils::drawHighlight('New name added successfully!', 'ui-icon-elrsuccess');
        } catch (Throwable $e) {
            \Udoh\Emsa\Utils\ExceptionUtils::logException($e);
            Udoh\Emsa\Utils\DisplayUtils::drawError('An error occurred while attempting to add the new name.');
        }
    } elseif ($watchlistDeleteId > 0) {
        try {
            $deleteSql = "DELETE FROM pending_watch_list 
                          WHERE id = :deleteId;";
            $deleteStmt = $adminDbConn->prepare($deleteSql);
            $deleteStmt->bindValue(':deleteId', $watchlistDeleteId, PDO::PARAM_INT);
            
            $deleteStmt->execute();
            
            Udoh\Emsa\Utils\DisplayUtils::drawHighlight('Name successfully deleted!', 'ui-icon-elrsuccess');
        } catch (Throwable $e) {
            \Udoh\Emsa\Utils\ExceptionUtils::logException($e);
            Udoh\Emsa\Utils\DisplayUtils::drawError('An error occurred while attempting to delete this name.');
        }
    } elseif ($watchlistEditId > 0) {
        try {
            $updateSql = "UPDATE pending_watch_list
                          SET lname = :lname
                          WHERE id = :editId;";
            $updateStmt = $adminDbConn->prepare($updateSql);
            $updateStmt->bindValue(':lname', $watchlistActionName, PDO::PARAM_STR);
            $updateStmt->bindValue(':editId', $watchlistEditId, PDO::PARAM_INT);
            
            $updateStmt->execute();
            
            Udoh\Emsa\Utils\DisplayUtils::drawHighlight('Name successfully updated!', 'ui-icon-elrsuccess');
        } catch (Throwable $e) {
            \Udoh\Emsa\Utils\ExceptionUtils::logException($e);
            Udoh\Emsa\Utils\DisplayUtils::drawError('An error occurred while attempting to update this name.');
        }
    }

?>

<div id="addnew_form" class="addnew_lab ui-widget ui-widget-content ui-corner-all">
    <div style="clear: both;"><span class="emsa_form_heading">Add New Pending Watchlist Name:</span><br><br></div>
    <form id="new_lab_form" method="GET">
        <label class="vocab_search_form2" for="new_name">Name:</label><input class="ui-corner-all" type="text" name="name" id="new_name" />
        <br><br>
        
        <input type="hidden" name="selected_page" value="<?php echo intval($navSelectedPage); ?>" />
        <input type="hidden" name="submenu" value="<?php echo intval($navSubmenu); ?>" />
        <input type="hidden" name="cat" value="<?php echo intval($navCat); ?>" />
        <input type="hidden" name="subcat" value="<?php echo intval($navSubcat); ?>" />
        <input type="hidden" name="add_flag" value="1" />
        <br><br><button type="submit" name="new_savedatatype" id="new_savedatatype">Save New Name</button>
        <button type="button" id="addnew_cancel">Cancel</button>
    </form>
</div>



<div class="lab_results_container ui-widget ui-corner-all">
	<table id="labResults">
		<thead>
			<tr>
				<th style="text-align: center;">Actions</th>
				<th>Name</th>
			</tr>
		</thead>
		<tbody>

<?php
	
	$currentSql = 'SELECT id, lname
		FROM pending_watch_list
        ORDER BY lname;';
	$currentRs = $adminDbConn->query($currentSql);
	
	while ($row = $currentRs->fetchObject()) {
        echo "<tr>";
		echo '<td style="text-align: center; white-space: nowrap;" class="action_col">';
		
        $editLhdParams = array(
            "id" => (int) $row->id,
            "name" => \Udoh\Emsa\Utils\DisplayUtils::xSafe((string) $row->lname)
        );
        
        echo "<button class='edit_datatype' type='button' value='" . json_encode($editLhdParams) . "' title='Edit this Name'>Edit</button>";
        echo "<button class='delete_datatype' type='button' value='" . (int) $row->id . "' title='Permanently delete this Name'>Delete</button>";
        
		echo "</td>";
		echo "<td>" . \Udoh\Emsa\Utils\DisplayUtils::xSafe((string) $row->lname) . "</td>";
        
        echo "</tr>";
	}

?>

		</tbody>
	</table>
	
</div>

<div id="confirm_delete_dialog" title="Delete this Watchlist Name?">
    <p><span class="ui-icon ui-icon-elrerror" style="float:left; margin:0 7px 50px 0;"></span>This name will be permanently deleted from the Pending Watchlist and cannot be recovered. Are you sure?</p>
</div>

<div id="edit_datatype_dialog" title="Edit Watchlist Name">
    <form id="edit_modal_form" method="GET">
        <label for="edit_name">Name</label><br><input class="ui-corner-all" type="text" name="name" id="edit_name" />
        <br><br>
        
        <input type="hidden" name="edit_id" id="edit_id" />
        <input type="hidden" name="selected_page" value="<?php echo intval($navSelectedPage); ?>" />
        <input type="hidden" name="submenu" value="<?php echo intval($navSubmenu); ?>" />
        <input type="hidden" name="cat" value="<?php echo intval($navCat); ?>" />
        <input type="hidden" name="subcat" value="<?php echo intval($navSubcat); ?>" />
    </form>
</div>
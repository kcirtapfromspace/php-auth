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

use Udoh\Emsa\Utils\DisplayUtils;

if (!class_exists('\Udoh\Emsa\Auth\Authenticator') || !Udoh\Emsa\Auth\Authenticator::userHasPermission(Udoh\Emsa\Auth\Authenticator::URIGHTS_ADMIN)) {
    ob_end_clean();
    header($_SERVER['SERVER_PROTOCOL'] . " 404 Not Found", TRUE, 404);
    exit;
}
?>
<script>
	$(function() {
	    let editInterstate = $(".edit_interstate_state");
	    
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

        $("#new_savejurisdiction").button({
            icon: "ui-icon-elrsave"
        });

        editInterstate.button({
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

        $(".delete_interstate_state").on("click", function(e) {
            e.preventDefault();
            let deleteAction = "<?php echo $webappBaseUrl; ?>?selected_page=6&submenu=30&cat=15&delete_id=" + $(this).val();
            let confirmDeleteDialog = $("#confirm_delete_dialog");

            confirmDeleteDialog.dialog('option', 'buttons', {
                "Delete": function() {
                    window.location.href = deleteAction;
                },
                "Cancel": function() {
                    $(this).dialog("close");
                }
            });

            confirmDeleteDialog.dialog("open");

        });

        $("#edit_lab_dialog").dialog({
            autoOpen: false,
            modal: true
        });

        editInterstate.on("click", function(e) {
            e.preventDefault();
            let jsonObj = JSON.parse($(this).val());
            let editLabDialog = $("#edit_lab_dialog");

            if (jsonObj.id) {
                $("#edit_id").val(jsonObj.id);
                $("#edit_name").val(jsonObj.name);
                $("#edit_ldap_cn").val(jsonObj.ldap_cn);
                
                if (jsonObj.is_transmitting === "t") {
                    $("#edit_is_transmitting_yes").trigger("click");
                } else {
                    $("#edit_is_transmitting_no").trigger("click");
                }
                
                editLabDialog.dialog('option', 'buttons', {
                    "Save Changes": function() {
                        $(this).dialog("close");
                        $("#edit_modal_form").trigger("submit");
                    },
                    "Cancel": function() {
                        $(this).dialog("close");
                    }
                });

                editLabDialog.dialog("open");
            } else {
                return false;
            }
        });
        
		$("th > .ui-icon").tooltip();
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

<h1 class="elrhdg"><span class="ui-icon ui-icon-header ui-icon-emsainterstate"></span>Manage Interstate Transmission</h1>

<div id="date_filter" style="position: absolute; z-index: 999; top: 43px; right: 15px;">
	<button id="addnew_button" title="Set up a new State for Interstate transmission">Add New State</button>
</div>

<?php

    /* @var $adminDbConn PDO */
	
	$appJurisdictions = array();
    /* @var $appClient Udoh\Emsa\Client\AppClientInterface */
    foreach ($appClientList->getClients() as $appClient) {
        $appJurisdictions[$appClient->getAppId()] = $appClient->getJurisdictions();
    }
    
    
    // process jurisdiction actions
    $stateEditID = (int) filter_input(INPUT_GET, 'edit_id', FILTER_SANITIZE_NUMBER_INT);
    $stateDeleteID = (int) filter_input(INPUT_GET, 'delete_id', FILTER_SANITIZE_NUMBER_INT);
    $stateAddFlag = (int) filter_input(INPUT_GET, 'add_flag', FILTER_SANITIZE_NUMBER_INT);
    
    $stateActionName = (string) filter_input(INPUT_GET, 'name', FILTER_SANITIZE_STRING, FILTER_FLAG_NO_ENCODE_QUOTES);
    $stateActionIsTransmitting = ((string) filter_input(INPUT_GET, 'is_transmitting', FILTER_SANITIZE_STRING) == 'true') ? true : false;
    $stateActionCN = (string) filter_input(INPUT_GET, 'ldap_cn', FILTER_SANITIZE_STRING, FILTER_FLAG_NO_ENCODE_QUOTES);
    
    if ($stateAddFlag === 1) {
        try {
            $addInterstateSql = "INSERT INTO interstate (state, ldap_cn, transmitting)
                               VALUES (:stateName, :ldapCN, :isTransmitting) RETURNING id;";
            $addInterstateStmt = $adminDbConn->prepare($addInterstateSql);
            $addInterstateStmt->bindValue(':stateName', $stateActionName, PDO::PARAM_STR);
            $addInterstateStmt->bindValue(':ldapCN', $stateActionCN, PDO::PARAM_STR);
            $addInterstateStmt->bindValue(':isTransmitting', $stateActionIsTransmitting, PDO::PARAM_BOOL);
            
            $addInterstateStmt->execute();
            
            Udoh\Emsa\Utils\DisplayUtils::drawHighlight('New jurisdiction added successfully!', 'ui-icon-elrsuccess');
        } catch (Throwable $e) {
            Udoh\Emsa\Utils\ExceptionUtils::logException($e);
            Udoh\Emsa\Utils\DisplayUtils::drawError('An error occurred while attempting to add new State.');
        }
    } elseif ($stateDeleteID > 0) {
        try {
            $deleteInterstateSql = "DELETE FROM interstate WHERE id = :stateId;";
            $deleteInterstateStmt = $adminDbConn->prepare($deleteInterstateSql);
            $deleteInterstateStmt->bindValue(':stateId', $stateDeleteID, PDO::PARAM_INT);
            $deleteInterstateStmt->execute();
            Udoh\Emsa\Utils\DisplayUtils::drawHighlight('Jurisdiction successfully deleted!', 'ui-icon-elrsuccess');
        } catch (Throwable $e) {
            Udoh\Emsa\Utils\ExceptionUtils::logException($e);
            Udoh\Emsa\Utils\DisplayUtils::drawError('An error occurred while attempting to delete this State.');
        }
    } elseif ($stateEditID > 0) {
        try {
            $updateInterstateSql = "UPDATE interstate
                                  SET state = :stateName, ldap_cn = :ldapCN, transmitting = :isTransmitting
                                  WHERE id = :stateId;";
            $updateInterstateStmt = $adminDbConn->prepare($updateInterstateSql);
            $updateInterstateStmt->bindValue(':stateName', $stateActionName, PDO::PARAM_STR);
            $updateInterstateStmt->bindValue(':ldapCN', $stateActionCN, PDO::PARAM_STR);
            $updateInterstateStmt->bindValue(':isTransmitting', $stateActionIsTransmitting, PDO::PARAM_BOOL);
            $updateInterstateStmt->bindValue(':stateId', $stateEditID, PDO::PARAM_INT);
            $updateInterstateStmt->execute();
            
            Udoh\Emsa\Utils\DisplayUtils::drawHighlight('State successfully updated!', 'ui-icon-elrsuccess');
        } catch (Throwable $e) {
            Udoh\Emsa\Utils\ExceptionUtils::logException($e);
            Udoh\Emsa\Utils\DisplayUtils::drawError('An error occurred while attempting to update this State.');
        }
    }

?>

<div id="addnew_form" class="addnew_lab ui-widget ui-widget-content ui-corner-all">
    <div style="clear: both;"><span class="emsa_form_heading">Add New State:</span><br><br></div>
    <form id="new_lab_form" method="GET">
        <label class="vocab_search_form2" for="new_name">State Code (e.g. "UT"):</label><input class="ui-corner-all" type="text" name="name" id="new_name" />
        <br><br>

        <label class="vocab_search_form2" for="new_ldap_cn">LDAP CN for Encryption:</label><input class="ui-corner-all" type="text" name="ldap_cn" id="new_ldap_cn" />
        <br><br>

        <fieldset>
            <legend style="font-size: 11pt; position: relative; float: left;">Send Interstate Messages?</legend>
            <label class="vocab_search_form2" for="new_is_transmitting_yes"><input checked="checked" class="edit_radio ui-corner-all" type="radio" name="is_transmitting" id="new_is_transmitting_yes" value="true" /> Yes</label>
            <label class="vocab_search_form2" for="new_is_transmitting_no"><input class="edit_radio ui-corner-all" type="radio" name="is_transmitting" id="new_is_transmitting_no" value="false" /> No</label>
        </fieldset>

        <input type="hidden" name="selected_page" value="<?php echo intval($navSelectedPage); ?>" />
        <input type="hidden" name="submenu" value="<?php echo intval($navSubmenu); ?>" />
        <input type="hidden" name="cat" value="<?php echo intval($navCat); ?>" />
        <input type="hidden" name="add_flag" value="1" />
        <br><br><button type="submit" name="new_savejurisdiction" id="new_savejurisdiction">Save New Jurisdiction</button>
        <button type="button" id="addnew_cancel">Cancel</button>
    </form>
</div>



<div class="lab_results_container ui-widget ui-corner-all">
	<table id="labResults">
		<thead>
			<tr>
				<th style="text-align: center;">Actions</th>
				<th style="width: 10%;">State</th>
                <th>Send Interstate Messages?</th>
                <th>LDAP CN for Encryption</th>
			</tr>
		</thead>
		<tbody>

<?php
	
	$currentSql = 'SELECT id, state, ldap_cn, transmitting
		FROM interstate
        ORDER BY state;';
	$currentRs = $adminDbConn->query($currentSql);
	
	$interstateStatesWithAppData = array();
    while ($row = $currentRs->fetchObject()) {
        $interstateStatesWithAppData[(int) $row->id]['stateName'] = (string) filter_var(trim($row->state), FILTER_SANITIZE_STRING, FILTER_FLAG_NO_ENCODE_QUOTES);
        $interstateStatesWithAppData[(int) $row->id]['ldapCN'] = (string) filter_var(trim($row->ldap_cn), FILTER_SANITIZE_STRING, FILTER_FLAG_NO_ENCODE_QUOTES);
        $interstateStatesWithAppData[(int) $row->id]['isTransmitting'] = (bool) $row->transmitting;
	}
	
	foreach ($interstateStatesWithAppData as $interstateStateId => $interstateData) {
		echo "<tr>";
		echo '<td style="text-align: center; white-space: nowrap;" class="action_col">';
		
        $editInterstateParams = array(
            "id" => (int) $interstateStateId,
            "name" => Udoh\Emsa\Utils\DisplayUtils::xSafe($interstateData['stateName']),
            "ldap_cn" => Udoh\Emsa\Utils\DisplayUtils::xSafe($interstateData['ldapCN']),
            "is_transmitting" => ($interstateData['isTransmitting'] === true) ? 't' : 'f'
        );
        
        echo "<button class='edit_interstate_state' type='button' value='" . json_encode($editInterstateParams) . "' title='Edit this State'>Edit</button>";
        echo "<button class='delete_interstate_state' type='button' value='" . (int) $interstateStateId . "' title='Permanently delete this State'>Delete</button>";
        
		echo "</td>";
		echo "<td>" . Udoh\Emsa\Utils\DisplayUtils::xSafe($interstateData['stateName']) . "</td>";
        
        if ($interstateData['isTransmitting']) {
            echo '<td><span class="ui-icon ui-icon-elrsuccess" title="Yes"></span></td>';
        } else {
            echo '<td><span class="ui-icon ui-icon-elrcancel" title="No"></span></td>';
        }

        echo "<td>" . Udoh\Emsa\Utils\DisplayUtils::xSafe($interstateData['ldapCN']) . "</td>";
		echo "</tr>";
	}

?>

		</tbody>
	</table>
	
</div>

<div id="confirm_delete_dialog" title="Delete this State?">
    <p><span class="ui-icon ui-icon-elrerror" style="float:left; margin:0 7px 50px 0;"></span>This State will be permanently removed from Interstate Transmission configuration and cannot be recovered. Are you sure?</p>
</div>

<div id="edit_lab_dialog" title="Edit State">
    <form id="edit_modal_form" method="GET">
        <label for="edit_name">State Code</label><br><input class="ui-corner-all" type="text" name="name" id="edit_name" />
        <br><br>

        <label for="edit_ldap_cn">LDAP CN for Encryption</label><br><input class="ui-corner-all" type="text" name="ldap_cn" id="edit_ldap_cn" />
        <br><br>

        <fieldset>
            <legend>Send Interstate Messages?</legend>
            <label for="edit_is_transmitting_yes"><input class="edit_radio ui-corner-all" type="radio" name="is_transmitting" id="edit_is_transmitting_yes" value="true" /> Yes</label>
            <label for="edit_is_transmitting_no"><input class="edit_radio ui-corner-all" type="radio" name="is_transmitting" id="edit_is_transmitting_no" value="false" /> No</label>
        </fieldset>
        
        <input type="hidden" name="edit_id" id="edit_id" />
        <input type="hidden" name="selected_page" value="<?php echo intval($navSelectedPage); ?>" />
        <input type="hidden" name="submenu" value="<?php echo intval($navSubmenu); ?>" />
        <input type="hidden" name="cat" value="<?php echo intval($navCat); ?>" />
    </form>
</div>
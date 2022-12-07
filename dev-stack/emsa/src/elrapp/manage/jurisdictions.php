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
            var deleteAction = "<?php echo $webappBaseUrl; ?>?selected_page=6&submenu=30&cat=12&delete_id=" + $(this).val();


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
                
                if (jsonObj.close_surveillance === "t") {
                    $("#edit_close_surveillance_yes").trigger("click");
                } else {
                    $("#edit_close_surveillance_no").trigger("click");
                }
                
                for (var key in jsonObj) {
                    if (jsonObj.hasOwnProperty(key) && (key.indexOf('edit_app_') >= 0)) {
                        $("#" + key).val(jsonObj[key]);
                    }
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

<h1 class="elrhdg"><span class="ui-icon ui-icon-header ui-icon-emsamap"></span>Manage Jurisdictions</h1>

<div id="date_filter" style="position: absolute; z-index: 999; top: 43px; right: 15px;">
	<button id="addnew_button" title="Add a new Jurisdiction">Add New Jurisdiction</button>
</div>

<?php

    /* @var $adminDbConn \PDO */
	
	$appJurisdictions = array();
    /* @var $appClient \Udoh\Emsa\Client\AppClientInterface */
    foreach ($appClientList->getClients() as $appClient) {
        $appJurisdictions[$appClient->getAppId()] = $appClient->getJurisdictions();
    }
    
    
    // process jurisdiction actions
    $jurisdictionEditID = (int) filter_input(INPUT_GET, 'edit_id', FILTER_SANITIZE_NUMBER_INT);
    $jurisdictionDeleteID = (int) filter_input(INPUT_GET, 'delete_id', FILTER_SANITIZE_NUMBER_INT);
    $jurisdictionAddFlag = (int) filter_input(INPUT_GET, 'add_flag', FILTER_SANITIZE_NUMBER_INT);
    
    $jurisdictionActionName = (string) filter_input(INPUT_GET, 'name', FILTER_SANITIZE_STRING, FILTER_FLAG_NO_ENCODE_QUOTES);
    $jurisdictionActionCloseSurveillance = ((string) filter_input(INPUT_GET, 'close_surveillance', FILTER_SANITIZE_STRING) == 'true') ? true : false;
    $jurisdictionAddAppIDs = array();
    $jurisdictionEditAppIDs = array();
    
    foreach ($appClientList->getClients() as $appClient) {
        $jurisdictionAddAppIDs[$appClient->getAppId()] = (int) filter_input(INPUT_GET, 'new_app_' . $appClient->getAppId(), FILTER_SANITIZE_NUMBER_INT);
        $jurisdictionEditAppIDs[$appClient->getAppId()] = (int) filter_input(INPUT_GET, 'edit_app_' . $appClient->getAppId(), FILTER_SANITIZE_NUMBER_INT);
    }
    
    if ($jurisdictionAddFlag === 1) {
        try {
            $addDistrictSql = "INSERT INTO system_districts (district, health_district, close_surveillance)
                               VALUES ('XX', :districtName, :closeSurveillance) RETURNING id;";
            $addDistrictStmt = $adminDbConn->prepare($addDistrictSql);
            $addDistrictStmt->bindValue(':districtName', $jurisdictionActionName, \PDO::PARAM_STR);
            $addDistrictStmt->bindValue(':closeSurveillance', $jurisdictionActionCloseSurveillance, \PDO::PARAM_BOOL);
            
            if ($addDistrictStmt->execute()) {
                $addedDistrictID = (int) $addDistrictStmt->fetchColumn(0);
                
                if ($addedDistrictID > 0) {
                    $addAppDistrictSql = "INSERT INTO app_jurisdictions (system_district_id, app_id, app_jurisdiction_id) 
                                          VALUES (?, ?, ?);";
                    $addAppDistrictStmt = $adminDbConn->prepare($addAppDistrictSql);
                    
                    foreach ($jurisdictionAddAppIDs as $jurisdictionAddAppID => $jurisdictionAddAppDistrictID) {
                        if ($jurisdictionAddAppDistrictID > 0) {
                            $addAppDistrictStmt->execute(array($addedDistrictID, $jurisdictionAddAppID, $jurisdictionAddAppDistrictID));
                        }
                    }
                }
            }
            
            Udoh\Emsa\Utils\DisplayUtils::drawHighlight('New jurisdiction added successfully!', 'ui-icon-elrsuccess');
        } catch (Throwable $e) {
            \Udoh\Emsa\Utils\ExceptionUtils::logException($e);
            Udoh\Emsa\Utils\DisplayUtils::drawError('An error occurred while attempting to add new jurisdiction.');
        }
    } elseif ($jurisdictionDeleteID > 0) {
        try {
            $deleteDistrictSql = "DELETE FROM system_districts WHERE id = :districtId;";
            $deleteDistrictStmt = $adminDbConn->prepare($deleteDistrictSql);
            $deleteDistrictStmt->bindValue(':districtId', $jurisdictionDeleteID, \PDO::PARAM_INT);
            $deleteDistrictStmt->execute();
            Udoh\Emsa\Utils\DisplayUtils::drawHighlight('Jurisdiction successfully deleted!', 'ui-icon-elrsuccess');
        } catch (Throwable $e) {
            \Udoh\Emsa\Utils\ExceptionUtils::logException($e);
            Udoh\Emsa\Utils\DisplayUtils::drawError('An error occurred while attempting to delete this jurisdiction.');
        }
    } elseif ($jurisdictionEditID > 0) {
        try {
            $updateDistrictSql = "UPDATE system_districts
                                  SET health_district = :districtName, close_surveillance = :closeSurveillance
                                  WHERE id = :districtId;";
            $updateDistrictStmt = $adminDbConn->prepare($updateDistrictSql);
            $updateDistrictStmt->bindValue(':districtName', $jurisdictionActionName, \PDO::PARAM_STR);
            $updateDistrictStmt->bindValue(':closeSurveillance', $jurisdictionActionCloseSurveillance, \PDO::PARAM_BOOL);
            $updateDistrictStmt->bindValue(':districtId', $jurisdictionEditID, \PDO::PARAM_INT);
            $updateDistrictStmt->execute();
            
            $appDistrictCheckSql = "SELECT id FROM app_jurisdictions
                                    WHERE system_district_id = ?
                                    AND app_id = ?;";
            $appDistrictCheckStmt = $adminDbConn->prepare($appDistrictCheckSql);
            
            $updateAppDistrictSql = "UPDATE app_jurisdictions 
                                     SET app_jurisdiction_id = ?
                                     WHERE system_district_id = ?
                                     AND app_id = ?;";
            $updateAppDistrictStmt = $adminDbConn->prepare($updateAppDistrictSql);
            
            $addAppDistrictSql = "INSERT INTO app_jurisdictions (system_district_id, app_id, app_jurisdiction_id) 
                                  VALUES (?, ?, ?);";
            $addAppDistrictStmt = $adminDbConn->prepare($addAppDistrictSql);
            
            $deleteAppDistrictSql = "DELETE FROM app_jurisdictions WHERE id = :pkId;";
            $deleteAppDistrictStmt = $adminDbConn->prepare($deleteAppDistrictSql);
            
            foreach ($jurisdictionEditAppIDs as $jurisdictionEditAppID => $jurisdictionEditAppDistrictID) {
                // check if mapping already exists for this app & system district
                $appDistrictCheckStmt->execute(array($jurisdictionEditID, $jurisdictionEditAppID));
                $updateAppDistrictId = (int) $appDistrictCheckStmt->fetchColumn(0);
                
                if (($updateAppDistrictId > 0) && ($jurisdictionEditAppDistrictID <= 0)) {
                    // existing mapping exists, but new mapping is to un-set; delete old mapping
                    $deleteAppDistrictStmt->execute(array($updateAppDistrictId));
                } elseif (($updateAppDistrictId <= 0) && ($jurisdictionEditAppDistrictID > 0)) {
                    // existing mapping doesn't exist, but new mapping set; add new mapping
                    $addAppDistrictStmt->execute(array($jurisdictionEditID, $jurisdictionEditAppID, $jurisdictionEditAppDistrictID));
                } elseif (($updateAppDistrictId > 0) && ($jurisdictionEditAppDistrictID > 0)) {
                    // existing mapping exists, and new mapping set; update existing mapping
                    $updateAppDistrictStmt->execute(array($jurisdictionEditAppDistrictID, $jurisdictionEditID, $jurisdictionEditAppID));
                }
            }
            
            Udoh\Emsa\Utils\DisplayUtils::drawHighlight('Jurisdiction successfully updated!', 'ui-icon-elrsuccess');
        } catch (Throwable $e) {
            \Udoh\Emsa\Utils\ExceptionUtils::logException($e);
            Udoh\Emsa\Utils\DisplayUtils::drawError('An error occurred while attempting to update this jurisdiction.');
        }
    }

?>

<div id="addnew_form" class="addnew_lab ui-widget ui-widget-content ui-corner-all">
    <div style="clear: both;"><span class="emsa_form_heading">Add New Jurisdiction:</span><br><br></div>
    <form id="new_lab_form" method="GET">
        <label class="vocab_search_form2" for="new_name">Jurisdiction Name:</label><input class="ui-corner-all" type="text" name="name" id="new_name" />
        <br><br>

        <fieldset>
            <legend style="font-size: 11pt; position: relative; float: left;">Close surveillance events?</legend>
            <label class="vocab_search_form2" for="new_close_surveillance_yes"><input checked="checked" class="edit_radio ui-corner-all" type="radio" name="close_surveillance" id="new_close_surveillance_yes" value="true" /> Yes</label>
            <label class="vocab_search_form2" for="new_close_surveillance_no"><input class="edit_radio ui-corner-all" type="radio" name="close_surveillance" id="new_close_surveillance_no" value="false" /> No</label>
        </fieldset>

        <?php
        foreach ($appClientList->getClients() as $appClient) {
            echo '<br><br>';
            echo '<label class="vocab_search_form2" for="new_app_' . (int) $appClient->getAppId() . '">' . Udoh\Emsa\Utils\DisplayUtils::xSafe($appClient->getAppName(), 'UTF-8') . ' Jurisdiction:</label>';
            echo '<select class="ui-corner-all" name="new_app_' . (int) $appClient->getAppId() . '" id="new_app_' . (int) $appClient->getAppId() . '">';
            echo '<option value="-1" selected>--</option>';
            
            foreach ($appJurisdictions[$appClient->getAppId()] as $appJurisdictionId => $appJurisdictionName) {
                echo '<option value="' . (int) $appJurisdictionId . '">' . Udoh\Emsa\Utils\DisplayUtils::xSafe($appJurisdictionName, 'UTF-8') . '</option>';
            }
            
            echo '</select>';
        }
        ?>
        
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
				<th style="width: 40%;">Jurisdiction Name</th>
                <th>Close Surveillance?</th>
                <?php
                foreach ($appClientList->getClients() as $appClient) {
                    echo '<th>' . $appClient->getAppName() . ' Jurisdiction</th>';
                }
                ?>
			</tr>
		</thead>
		<tbody>

<?php
	
	$currentSql = 'SELECT d.id AS id, d.health_district AS health_district, d.close_surveillance AS close_surveillance, a.id AS app_id, j.app_jurisdiction_id AS app_jurisdiction_id
		FROM system_districts d
        CROSS JOIN vocab_app a
        LEFT JOIN app_jurisdictions j ON (d.id = j.system_district_id) AND (j.app_id = a.id)
		ORDER BY d.health_district;';
	$currentRs = $adminDbConn->query($currentSql);
	
	$systemJurisdictionsWithAppData = array();
    while ($row = $currentRs->fetchObject()) {
        $systemJurisdictionsWithAppData[(int) $row->id]['districtName'] = (string) filter_var(trim($row->health_district), FILTER_SANITIZE_STRING, FILTER_FLAG_NO_ENCODE_QUOTES);
        $systemJurisdictionsWithAppData[(int) $row->id]['closeSurveillance'] = (bool) $row->close_surveillance;
        $systemJurisdictionsWithAppData[(int) $row->id]['appIds'][(int) filter_var($row->app_id, \FILTER_SANITIZE_NUMBER_INT)] = (int) filter_var($row->app_jurisdiction_id, \FILTER_SANITIZE_NUMBER_INT);
	}
	
	foreach ($systemJurisdictionsWithAppData as $jurisdictionId => $jurisdictionData) {
		echo "<tr>";
		echo '<td style="text-align: center; white-space: nowrap;" class="action_col">';
		
        $editLhdParams = array(
            "id" => (int) $jurisdictionId,
            "name" => \Udoh\Emsa\Utils\DisplayUtils::xSafe($jurisdictionData['districtName']),
            "close_surveillance" => ($jurisdictionData['closeSurveillance'] === true) ? 't' : 'f'
        );
        
        foreach ($appClientList->getClients() as $appClient) {
            $editLhdParams['edit_app_' . (int) $appClient->getAppId()] = (int) $jurisdictionData['appIds'][$appClient->getAppId()];
        }
        
        echo "<button class='edit_jurisdiction' type='button' value='" . json_encode($editLhdParams) . "' title='Edit this Jurisdiction'>Edit</button>";
        echo "<button class='delete_jurisdiction' type='button' value='" . (int) $jurisdictionId . "' title='Permanently delete this Jurisdiction'>Delete</button>";
        
		echo "</td>";
		echo "<td>" . \Udoh\Emsa\Utils\DisplayUtils::xSafe($jurisdictionData['districtName']) . "</td>";
        
        if ($jurisdictionData['closeSurveillance']) {
            echo '<td><span class="ui-icon ui-icon-elrsuccess" title="Yes"></span></td>';
        } else {
            echo '<td><span class="ui-icon ui-icon-elrcancel" title="No"></span></td>';
        }
        
        foreach ($appClientList->getClients() as $appClient) {
            echo '<td>';
            if (isset($appJurisdictions[$appClient->getAppId()][$jurisdictionData['appIds'][$appClient->getAppId()]])) {
                Udoh\Emsa\Utils\DisplayUtils::xEcho((string) filter_var($appJurisdictions[$appClient->getAppId()][$jurisdictionData['appIds'][$appClient->getAppId()]], FILTER_SANITIZE_STRING), 'UTF-8');
            } else {
                echo '--';
            }
            echo "</td>";
        }
		echo "</tr>";
	}

?>

		</tbody>
	</table>
	
</div>

<div id="confirm_delete_dialog" title="Delete this Jurisdiction?">
    <p><span class="ui-icon ui-icon-elrerror" style="float:left; margin:0 7px 50px 0;"></span>This jurisdiction will be permanently deleted and cannot be recovered. Are you sure?</p>
</div>

<div id="edit_lab_dialog" title="Edit Jurisdiction">
    <form id="edit_modal_form" method="GET">
        <label for="edit_name">Jurisdiction Name</label><br><input class="ui-corner-all" type="text" name="name" id="edit_name" />
        <br><br>

        <fieldset>
            <legend>Close surveillance events?</legend>
            <label for="edit_close_surveillance_yes"><input class="edit_radio ui-corner-all" type="radio" name="close_surveillance" id="edit_close_surveillance_yes" value="true" /> Yes</label>
            <label for="edit_close_surveillance_no"><input class="edit_radio ui-corner-all" type="radio" name="close_surveillance" id="edit_close_surveillance_no" value="false" /> No</label>
        </fieldset>
        
        <?php
        foreach ($appClientList->getClients() as $appClient) {
            echo '<br><br>';
            echo '<label for="edit_app_' . (int) $appClient->getAppId() . '">' . Udoh\Emsa\Utils\DisplayUtils::xSafe($appClient->getAppName(), 'UTF-8') . ' Jurisdiction:</label>';
            echo '<select class="ui-corner-all" name="edit_app_' . (int) $appClient->getAppId() . '" id="edit_app_' . (int) $appClient->getAppId() . '">';
            echo '<option value="-1" selected>--</option>';
            
            foreach ($appJurisdictions[$appClient->getAppId()] as $appJurisdictionId => $appJurisdictionName) {
                echo '<option value="' . (int) $appJurisdictionId . '">' . Udoh\Emsa\Utils\DisplayUtils::xSafe($appJurisdictionName, 'UTF-8') . '</option>';
            }
            
            echo '</select>';
        }
        ?>
        
        <input type="hidden" name="edit_id" id="edit_id" />
        <input type="hidden" name="selected_page" value="<?php echo intval($navSelectedPage); ?>" />
        <input type="hidden" name="submenu" value="<?php echo intval($navSubmenu); ?>" />
        <input type="hidden" name="cat" value="<?php echo intval($navCat); ?>" />
    </form>
</div>
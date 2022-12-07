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

use Udoh\Emsa\Management\PreprocessorExceptionValue;
use Udoh\Emsa\Utils\CoreUtils;
use Udoh\Emsa\Utils\DisplayUtils;

if (!class_exists('\Udoh\Emsa\Auth\Authenticator') || !Udoh\Emsa\Auth\Authenticator::userHasPermission(Udoh\Emsa\Auth\Authenticator::URIGHTS_ADMIN)) {
    ob_end_clean();
    header($_SERVER['SERVER_PROTOCOL'] . " 404 Not Found", TRUE, 404);
    exit;
}

// sanitization & filtering
$clean['retry_exception_id'] = (string) filter_input(INPUT_GET, 'e_id', FILTER_UNSAFE_RAW);
$clean['delete_exception_id'] = (string) filter_input(INPUT_GET, 'd_id', FILTER_UNSAFE_RAW);
$clean['lab_id'] = (string) filter_input(INPUT_GET, 'l_id', FILTER_UNSAFE_RAW);

if (isset($_GET['e_value']) && (strlen(trim($_GET['e_value'])) > 0)) {
    $clean['retry_exception_value'] = CoreUtils::decodeIfBase64Encoded(filter_var(rawurldecode(trim($_GET['e_value'])), FILTER_SANITIZE_STRING));
} else {
    $clean['retry_exception_value'] = null;
}

if (isset($_GET['d_value']) && (strlen(trim($_GET['d_value'])) > 0)) {
    $clean['delete_exception_value'] = CoreUtils::decodeIfBase64Encoded(filter_var(rawurldecode(trim($_GET['d_value'])), FILTER_SANITIZE_STRING));
} else {
    $clean['delete_exception_value'] = null;
}

try {
    $dbConn = $emsaDbFactory->getConnection();
} catch (Throwable $e) {
    Udoh\Emsa\Utils\ExceptionUtils::logException($e);
    Udoh\Emsa\Utils\DisplayUtils::drawError('Unable to connect to the EMSA database.');
    exit;
}
?>

<script>
    $(function () {
        $(".retry_btn").button({
            icon: "ui-icon-elrretry",
            showLabel: false
        }).on("click", function () {
            $(this).prop('disabled', true);
            let jsonObj = JSON.parse($(this).val());
            let retryAction = "";
            if (jsonObj.e_id) {
                if (jsonObj.e_value) {
                    retryAction = "<?php echo $webappBaseUrl; ?>?selected_page=6&submenu=31&cat=31&l_id=" + jsonObj.l_id + "&e_id=" + jsonObj.e_id + "&e_value=" + encodeURIComponent(jsonObj.e_value);
                } else {
                    retryAction = "<?php echo $webappBaseUrl; ?>?selected_page=6&submenu=31&cat=31&l_id=" + jsonObj.l_id + "&e_id=" + jsonObj.e_id;
                }
                window.location.href = retryAction;
            } else {
                alert("An error occurred.\n\nUnable to retry messages.  Please contact a system administrator.");
            }
        });

        $(".delete_btn").button({
            icon: "ui-icon-elrclose",
            showLabel: false
        }).on("click", function () {
            if (confirm("Are you sure you want to permanently delete the messages from this lab associated with this preprocessor exception?\n\nWarning!  This action cannot be undone.")) {
                $(this).prop('disabled', true);
                let jsonObj = JSON.parse($(this).val());
                let deleteAction = "";
                if (jsonObj.e_id) {
                    if (jsonObj.e_value) {
                        deleteAction = "<?php echo $webappBaseUrl; ?>?selected_page=6&submenu=31&cat=31&l_id=" + jsonObj.l_id + "&d_id=" + jsonObj.e_id + "&d_value=" + encodeURIComponent(jsonObj.e_value);
                    } else {
                        deleteAction = "<?php echo $webappBaseUrl; ?>?selected_page=6&submenu=31&cat=31&l_id=" + jsonObj.l_id + "&d_id=" + jsonObj.e_id;
                    }
                    window.location.href = deleteAction;
                } else {
                    alert("An error occurred.\n\nUnable to delete messages.  Please contact a system administrator.");
                }
            } else {
                return false;
            }
        });

        let addICDDialog;
        let addICDForm = $("#add_icd_form");

        function addICDCode() {
            let incompleteFields = 0;
			$(":input.required", addICDForm).each(function() {
				if ($(this).val() == "" || $(this).val() == -1) {
					alert($('label[for="'+$(this).attr('id')+'"]').text()+' requires a value');
					incompleteFields++;
				}
			});

			if (incompleteFields > 0) {
			    return false;
            }

            $.ajax({
                type: 'POST',
                url: 'api/vocabulary/icd.php',
                data: addICDForm.serialize()
            }).done(function() {
                alert("ICD Code added successfully!");
                addICDDialog.dialog("close");
                return true;
            }).fail(function() {
                alert("ICD Code was not added successfully.");
                return false;
            });
        }

        addICDDialog = $("#add_icd_dialog").dialog({
			autoOpen: false,
			modal: true,
            height: 550,
			width: 750,
            buttons: {
			    "Add ICD Code": addICDCode,
                "Cancel": function() {
                    addICDDialog.dialog("close");
                }
            }
		});

        $(".add_icd_btn").button({
            icon: "ui-icon-elrplus",
            showLabel: false
        }).on("click", function() {
            let jsonObj = JSON.parse($(this).val());
            let icdCode = jsonObj.code;
            let codeSystem = jsonObj.codeSystem;
            let apiDescription = jsonObj.apiDescription;
            let displayName = jsonObj.displayName;

            let icdCodeDescription;
            if ((apiDescription !== null) && (apiDescription.length > 0)) {
                icdCodeDescription = apiDescription.toString();
            } else {
                icdCodeDescription = displayName.toString();
            }

            document.getElementById('add_icd_form').reset();

            // codeset
            $.ajax({
                type: 'GET',
                url: 'ajax/codeset.php',
                dataType: 'json'
            }).done(function (data) {
                let codesetOptions = [];
                let codesetObj = $("#codeset");
                codesetOptions[codesetOptions.length] = "<option value='-1' selected>--</option>";
                if (data.length) {
                    for (codeset in data) {
                        html = "<option value='" + parseInt(data[codeset].id) + "'>" + escapeHtml(data[codeset].concept) + "</option>";
                        codesetOptions[codesetOptions.length] = html;
                    }
                    codesetObj.empty().append(codesetOptions.join(''));
                }

                $("#codeset option").filter(function () {
                    return $(this).text() === codeSystem;
                }).prop("selected", true);
            });

            // master condition
            $.ajax({
                type: 'GET',
                url: 'ajax/master_condition.php',
                dataType: 'json'
            }).done(function (data) {
                let masterConditionOptions = [];
                let masterConditionObj = $("#master_condition");
                masterConditionOptions[masterConditionOptions.length] = "<option value='' selected>--</option>";
                if (data.length) {
                    for (masterCondition in data) {
                        html = "<option value='" + parseInt(data[masterCondition].id) + "'>" + escapeHtml(data[masterCondition].concept) + "</option>";
                        masterConditionOptions[masterConditionOptions.length] = html;
                    }
                    masterConditionObj.empty().append(masterConditionOptions.join(''));
                }
            });

            // master SNOMED
            $.ajax({
                type: 'GET',
                url: 'ajax/master_snomed.php',
                dataType: 'json'
            }).done(function (data) {
                let masterSNOMEDOptions = [];
                let masterSNOMEDObj = $("#master_snomed");
                masterSNOMEDOptions[masterSNOMEDOptions.length] = "<option value='' selected>--</option>";
                if (data.length) {
                    for (masterSNOMED in data) {
                        html = "<option value='" + parseInt(data[masterSNOMED].id) + "'>" + escapeHtml(data[masterSNOMED].concept) + " [" + escapeHtml(data[masterSNOMED].snomed) + "]</option>";
                        masterSNOMEDOptions[masterSNOMEDOptions.length] = html;
                    }
                    masterSNOMEDObj.empty().append(masterSNOMEDOptions.join(''));
                }
            });

            $("#icd_code").val(icdCode);
            $("#code_description").val(icdCodeDescription);

            addICDDialog.dialog("open");
        });

        $("#tabs").tabs().addClass("ui-tabs-vertical ui-helper-clearfix");
        $("#tabs li").removeClass("ui-corner-top").addClass("ui-corner-left");
        $(".ui-tabs-panel").addClass("ui-corner-all");
    });
</script>

<style type="text/css">
    .bulk-exception-counter {
        font-family: Roboto, 'Open Sans', Arial, sans-serif;
        margin: 0;
        margin-left: 7px;
        font-size: 0.8em;
        line-height: 2em;
        display: inline;
        padding: 3px;
        background-color: red;
        color: white;
    }
    input[type="radio"] { min-width: 0 !important; }
    #new_lab_form div { display: inline-block; margin: 7px; padding: 10px; }
    #labResults td { border-bottom-width: 2px; border-bottom-color: darkorange; }
    .audit_log td { border-bottom-width: 1px !important; border-bottom-color: lightgray !important; }
    .audit_log tr:hover > td { background-color: lightcyan !important; }
    #labResults a:link, #labResults a:visited, #labResults a:active { color: #005D9C; font-weight: 600; }
    #labResults a:hover { color: black; }
    .retry_btn, .delete_btn, .add_icd_btn { vertical-align: middle; height: 24px; }
    .ui-tabs-vertical { width: 100%; }
    .ui-tabs-vertical .ui-tabs-nav { text-align: right; padding: .2em .1em .2em .2em; float: left; width: 20%; }
    .ui-tabs-vertical .ui-tabs-nav .ui-tabs-anchor { float: right; }
    .ui-tabs-vertical .ui-tabs-nav li.ui-tabs-active a { color: #333333; }
    .ui-tabs-vertical .ui-tabs-nav li { white-space: normal; text-align: right; clear: left; width: 100%; border-bottom-width: 1px !important; border-right-width: 0 !important; margin: 0 -1px .2em 0; padding-top: 2px; }
    .ui-tabs-vertical .ui-tabs-nav li a { display: block; }
    .ui-tabs-vertical .ui-tabs-nav li.ui-tabs-active {
        margin: 0 -1px .2em 0; 
        padding-bottom: 0; 
        padding-right: .1em; 
        border-right-width: 1px; 
        background: rgba(241,231,103,1);
        background: -moz-linear-gradient(left, rgba(241,231,103,1) 0%, rgba(254,182,69,1) 100%);
        background: -webkit-gradient(left top, right top, color-stop(0%, rgba(241,231,103,1)), color-stop(100%, rgba(254,182,69,1)));
        background: -webkit-linear-gradient(left, rgba(241,231,103,1) 0%, rgba(254,182,69,1) 100%);
        background: -o-linear-gradient(left, rgba(241,231,103,1) 0%, rgba(254,182,69,1) 100%);
        background: -ms-linear-gradient(left, rgba(241,231,103,1) 0%, rgba(254,182,69,1) 100%);
        background: linear-gradient(to right, rgba(241,231,103,1) 0%, rgba(254,182,69,1) 100%);
        filter: progid:DXImageTransform.Microsoft.gradient( startColorstr='#f1e767', endColorstr='#feb645', GradientType=1 );
    }
    .ui-tabs-vertical .ui-tabs-panel { padding: 1em; float: right; width: 77%; height: 500px; overflow-y: auto; background-color: lightcyan; border: 1px darkgray solid; }
</style>

<h1 class="elrhdg"><span class="ui-icon ui-icon-header ui-icon-elrerrorbig"></span>Preprocessor Exceptions by Connector</h1>

<?php
if ((strlen($clean['retry_exception_id']) > 0) && (strlen($clean['lab_id']) > 0)) {
    // attempt a retry
    include_once __DIR__ . '/preprocessor_retry.php';
} elseif ((strlen($clean['delete_exception_id']) > 0) && (strlen($clean['lab_id']) > 0)) {
    // attempt a retry
    include_once __DIR__ . '/preprocessor_delete.php';
}
?>

<div class="lab_results_container ui-widget ui-corner-all">
    <?php
    $bulkExceptionAggregateFacilityData = array();
    $bulkExceptionAggregateData = array();
    $etaskArr = array();
    $etaskLookup = array();
    $labIdArr = array();

    try {
        $sql = "SELECT om.connector AS connector, pe.exception_message AS description, pe.info AS info, count(pe.exception_message) AS exceptions 
                FROM preprocessor_exceptions pe
                INNER JOIN system_original_messages om ON (pe.system_original_messages_id = om.id)
                WHERE om.sent <> -1
                GROUP BY 1, 2, 3
                ORDER BY 1, 2, 4 DESC, 3;";
        $stmt = $dbConn->prepare($sql);

        $stmt->execute();

        while ($row = $stmt->fetchObject()) {
            $etaskArr[trim($row->connector)][$row->description][$row->info] = intval($row->exceptions);
        }
        
        $stmt = null;
    } catch (Throwable $e) {
        Udoh\Emsa\Utils\ExceptionUtils::logException($e);
        Udoh\Emsa\Utils\DisplayUtils::drawError('Unable to query Preprocessor Exceptions list:  ' . Udoh\Emsa\Utils\DisplayUtils::xSafe($e->getMessage()));
    }
    
    $dbConn = null;

    if (isset($etaskArr) && is_array($etaskArr) && (count($etaskArr) > 0)) {

        echo '<div id="tabs"><ul>';

        foreach ($etaskArr as $bulkPreExceptionFacilityName => $bulkPreExceptionFacilityData) {
            foreach ($bulkPreExceptionFacilityData as $bulkPreExceptionMessage => $bulkExceptionValues) {
                foreach ($bulkExceptionValues as $bulkPreExceptionInfo => $bulkPreExceptionInfoCount) {
                    if (isset($bulkExceptionAggregateFacilityData[$bulkPreExceptionFacilityName])) {
                        $bulkExceptionAggregateFacilityData[$bulkPreExceptionFacilityName] += $bulkPreExceptionInfoCount;
                    } else {
                        $bulkExceptionAggregateFacilityData[$bulkPreExceptionFacilityName] = $bulkPreExceptionInfoCount;
                    }

                    if (isset($bulkExceptionAggregateData[$bulkPreExceptionFacilityName][$bulkPreExceptionMessage])) {
                        $bulkExceptionAggregateData[$bulkPreExceptionFacilityName][$bulkPreExceptionMessage] += $bulkPreExceptionInfoCount;
                    } else {
                        $bulkExceptionAggregateData[$bulkPreExceptionFacilityName][$bulkPreExceptionMessage] = $bulkPreExceptionInfoCount;
                    }
                }
            }
            echo '<li><a href="#tabs-' . md5($bulkPreExceptionFacilityName) . '">' . Udoh\Emsa\Utils\DisplayUtils::xSafe($bulkPreExceptionFacilityName) . ' <div class="bulk-exception-counter ui-corner-all">&nbsp;' . intval($bulkExceptionAggregateFacilityData[$bulkPreExceptionFacilityName]) . '&nbsp;</div></a></li>';
        }

        echo '</ul>';

        foreach ($bulkExceptionAggregateFacilityData as $etask_lab_arr_connector => $etask_lab_arr_value) {
            echo '<div id="tabs-' . md5($etask_lab_arr_connector) . '">';
            ?>
            <table id="labResults">
                <thead>
                    <tr>
                        <th style="width: 250px; min-width: 250px; max-width: 250px;">Exception Type</th>
                        <th style="width: auto">Exception Values</th>
                        <th style="width: 150px; min-width: 150px; max-width: 150px;"># of Exceptions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    foreach ($bulkExceptionAggregateData[$etask_lab_arr_connector] as $etask_lab_etype_arr_key => $etask_lab_etype_arr_value) {
                        echo '<tr>';
                        echo '<td style="vertical-align: top;">';
                        echo '<span>';
                        echo '<strong style="font-size: 1.05em;">' . Udoh\Emsa\Utils\DisplayUtils::xSafe($etask_lab_etype_arr_key) . '</strong><br>';
                        echo '<button class="retry_btn" type="button" title="Reprocess messages with this preprocessor exception" value=\'' . json_encode(array('l_id' => DisplayUtils::xSafe($etask_lab_arr_connector, "UTF-8", false), 'e_id' => DisplayUtils::xSafe($etask_lab_etype_arr_key, "UTF-8", false), 'e_value' => null)) . '\'>Retry</button>';
                        echo '<button class="delete_btn" type="button" title="Delete messages with this preprocessor exception" value=\'' . json_encode(array('l_id' => DisplayUtils::xSafe($etask_lab_arr_connector, "UTF-8", false), 'e_id' => DisplayUtils::xSafe($etask_lab_etype_arr_key, "UTF-8", false), 'e_value' => null)) . '\'>Delete</button>';
                        echo '<div class="emsa_toolbar_separator"></div>';
                        echo '<a href="' . MAIN_URL . '/?selected_page=6&submenu=5&cat=11&connector[]=' . urlencode($etask_lab_arr_connector) . '&etype=' . urlencode($etask_lab_etype_arr_key) . '" target="_blank">' . intval($etask_lab_etype_arr_value) . ' error' . ((intval($etask_lab_etype_arr_value) > 1) ? 's' : '') . '</a>';
                        echo '</span>';
                        echo '</td>';
                        echo '<td colspan="2">';
                        echo '<table class="audit_log" style="width: 100%">';
                        foreach ($etaskArr[$etask_lab_arr_connector][$etask_lab_etype_arr_key] as $etask_info => $etask_count) {
                            $exceptionValue = new PreprocessorExceptionValue($etask_info);
                            echo '<tr>';
                            echo '<td class="mono_prewrap" style="width: auto;">' . $exceptionValue->getExceptionValue(true, "UTF-8", false) . '</td>';
                            echo '<td style="width: 145px; min-width: 145px; white-space: nowrap;">';
                            echo '<span>';
                            if (!empty($exceptionValue->getDecodedProperty("code"))) {
                                echo '<button class="add_icd_btn" type="button" title="Add ICD Code" value=\'' . json_encode(array("code" => DisplayUtils::xSafe($exceptionValue->getDecodedProperty("code"), "UTF-8", false), "codeSystem" => DisplayUtils::xSafe($exceptionValue->getDecodedProperty("codeSystem"), "UTF-8", false), "apiDescription" => DisplayUtils::xSafe($exceptionValue->getDecodedProperty("apiDescription"), "UTF-8", false), "displayName" => DisplayUtils::xSafe($exceptionValue->getDecodedProperty("displayName"), "UTF-8", false))) . '\'>Add</button>';
                            }
                            echo '<button class="retry_btn" type="button" title="Reprocess messages with this preprocessor exception value" value=\'' . json_encode(array('l_id' => DisplayUtils::xSafe($etask_lab_arr_connector, "UTF-8", false), 'e_id' => DisplayUtils::xSafe($etask_lab_etype_arr_key, "UTF-8", false), 'e_value' => base64_encode($etask_info))) . '\'>Retry</button>';
                            echo '<button class="delete_btn" type="button" title="Delete messages with this preprocessor exception value" value=\'' . json_encode(array('l_id' => DisplayUtils::xSafe($etask_lab_arr_connector, "UTF-8", false), 'e_id' => DisplayUtils::xSafe($etask_lab_etype_arr_key, "UTF-8", false), 'e_value' => base64_encode($etask_info))) . '\'>Delete</button>';
                            echo '<div class="emsa_toolbar_separator"></div>';
                            echo '<a href="' . MAIN_URL . '/?selected_page=6&submenu=5&cat=11&connector[]=' . urlencode($etask_lab_arr_connector) . '&etype=' . urlencode($etask_lab_etype_arr_key) . '&evalue=' . urlencode(base64_encode($etask_info)) . '" target="_blank">' . intval($etask_count) . ' error' . ((intval($etask_count) > 1) ? 's' : '') . '</a>';
                            echo '</span>';
                            echo '</td>';
                            echo '</tr>';
                        }
                        echo "</table>";
                        echo "</td>";
                        echo "</tr>";
                    }

                    echo "</tbody></table>";
                    echo '</div>';
                }

                echo '</div>';
            } else {
                Udoh\Emsa\Utils\DisplayUtils::drawHighlight("No Preprocessor Exceptions Found!");
            }
            ?>
            </div>

<div id="add_icd_dialog" title="Add New ICD Code">
    <form id="add_icd_form">
        <div class="addnew_field"><label class="vocab_add_form required" for="codeset">Coding System:</label><br><select class="ui-corner-all required" name="codeset" id="codeset"></select></div>
        <div class="addnew_field"><label class="vocab_add_form required " for="icd_code">ICD Code:</label><br><input class="ui-corner-all required" type="text" name="icd_code" id="icd_code"></div>
        <div class="addnew_field">
            <label class="vocab_add_form" for="code_description">Code Description:</label><br>
            <textarea name="code_description" id="code_description" class="ui-corner-all"></textarea>
        </div>

        <div class="add-form-divider"></div>
        <div class="addnew_field"><label class="vocab_add_form" for="master_condition">Condition:</label><br><select class="ui-corner-all" style="width: 100%;" name="master_condition" id="master_condition"></select></div>

        <div class="add-form-divider"></div>
            <div class="addnew_field"><label class="vocab_add_form" for="master_snomed">Organism:</label><br><select class="ui-corner-all" style="width: 100%;" name="master_snomed" id="master_snomed"></select></div>

        <div class="add-form-divider"></div>
        <div class="addnew_field">
            <label class="vocab_add_form" for="allow_new_cmr">Create New CMRs?</label><br>
            <select class="ui-corner-all" name="allow_new_cmr" id="allow_new_cmr">
                <option value="t">Yes</option>
                <option value="f" selected>No</option>
            </select>
        </div>
        <div class="addnew_field">
            <label class="vocab_add_form" for="allow_update_cmr">Update Existing CMRs?</label><br>
            <select class="ui-corner-all" name="allow_update_cmr" id="allow_update_cmr">
                <option value="t" selected>Yes</option>
                <option value="f">No</option>
            </select>
        </div>
        <div class="addnew_field">
            <label class="vocab_add_form" for="is_surveillance">New CMRs Are Surveillance Events?</label><br>
            <select class="ui-corner-all" name="is_surveillance" id="is_surveillance">
                <option value="t">Yes</option>
                <option value="f" selected>No</option>
            </select>
        </div>

        <div class="add-form-divider"></div>
        <div class="addnew_field">
            <label class="vocab_add_form" for="pregnancy_indicator"">Determines Pregnancy?</label><br>
            <select class="ui-corner-all" name="pregnancy_indicator"" id="pregnancy_indicator"">
                <option value="t">Yes</option>
                <option value="f" selected>No</option>
            </select>
        </div>
        <div class="addnew_field">
            <label class="vocab_add_form" for="pregnancy_status">Pregnancy Status</label><br>
            <select class="ui-corner-all" name="pregnancy_status" id="pregnancy_status">
                <option value="t">Pregnant</option>
                <option value="f" selected>Not Pregnant</option>
                <option value="u">Unknown</option>
            </select>
        </div>
    </form>
</div>
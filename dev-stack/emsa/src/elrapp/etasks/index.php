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

use Udoh\Emsa\Constants\SystemExceptions;
use Udoh\Emsa\Utils\CoreUtils;
use Udoh\Emsa\Utils\DisplayUtils;

if (!class_exists('\Udoh\Emsa\Auth\Authenticator') || !Udoh\Emsa\Auth\Authenticator::userHasPermission(Udoh\Emsa\Auth\Authenticator::URIGHTS_ADMIN)) {
    ob_end_clean();
    header($_SERVER['SERVER_PROTOCOL'] . " 404 Not Found", TRUE, 404);
    exit;
}

// sanitization & filtering
if (isset($_GET['e_id']) && filter_var(trim($_GET['e_id']), FILTER_VALIDATE_INT)) {
    $clean['retry_exception_id'] = filter_var(trim($_GET['e_id']), FILTER_SANITIZE_NUMBER_INT);
}

if (isset($_GET['e_value']) && (strlen(trim($_GET['e_value'])) > 0)) {
    $clean['retry_exception_value'] = CoreUtils::decodeIfBase64Encoded(filter_var(rawurldecode(trim($_GET['e_value'])), FILTER_SANITIZE_STRING));
} else {
    $clean['retry_exception_value'] = null;
}

if (isset($_GET['l_id']) && filter_var(trim($_GET['l_id']), FILTER_VALIDATE_INT)) {
    $clean['lab_id'] = filter_var(trim($_GET['l_id']), FILTER_SANITIZE_NUMBER_INT);
}
?>

<script>
    $(function() {
        let mplogToggle = $("#mplog-toggle");
        mplogToggle.button({
            icon: "ui-icon-triangle-1-n",
            iconPosition: "end"
        }).on("click", function() {
            $(".mplog-hideable").toggle();
            let objIcon = $(this).button("option", "icon");
            if (objIcon === "ui-icon-triangle-1-s") {
                $(this).button("option", "icon", "ui-icon-triangle-1-n");
                $(this).button("option", "iconPosition", "end");
                $(this).button("option", "label", "Hide More Details");
            } else {
                $(this).button("option", "icon", "ui-icon-triangle-1-s");
                $(this).button("option", "iconPosition", "end");
                $(this).button("option", "label", "Show More Details");
            }
        });

        $(".mplog-hideable").hide();
        mplogToggle.button("option", "icon", "ui-icon-triangle-1-s");
        mplogToggle.button("option", "iconPosition", "end");
        mplogToggle.button("option", "label", "Show More Details");

        $(".retry_btn").button({
            icon: "ui-icon-elrretry",
            showLabel: false
        }).on("click", function() {
            $(this).prop('disabled', true);
            let jsonObj = JSON.parse($(this).val());
            let retryAction = "";
            if (jsonObj.e_id) {
                if (jsonObj.e_value) {
                    retryAction = "<?php echo $webappBaseUrl; ?>?selected_page=6&submenu=31&cat=2&l_id=" + jsonObj.l_id + "&e_id=" + jsonObj.e_id + "&e_value=" + encodeURIComponent(jsonObj.e_value);
                } else {
                    retryAction = "<?php echo $webappBaseUrl; ?>?selected_page=6&submenu=31&cat=2&l_id=" + jsonObj.l_id + "&e_id=" + jsonObj.e_id;
                }
                window.location.href = retryAction;
            } else {
                alert("An error occurred.\n\nUnable to retry messages.  Please contact a system administrator.");
            }
        });

        $("#view_detailed_results").button({
            icon: "ui-icon-circle-triangle-s",
            iconPosition: "end"
        }).on("click", function() {
            $("#detailed_results").toggle();
        });

        $(".emsa_btn_viewnedss").button({
            icon: "ui-icon-elrview"
        });

        let addChildDialog;

        function addChildLOINC() {
            $.ajax({
                type: 'POST',
                url: 'api/vocabulary/child_loinc.php',
                data: $("#add_child_loinc_form").serialize()
            }).done(function() {
                alert("Child LOINC added successfully!");
                addChildDialog.dialog("close");
                return true;
            }).fail(function() {
                alert("Child LOINC was not added successfully.");
                return false;
            });
        }

        function blacklistChildLOINC() {
            $.ajax({
                type: 'POST',
                url: 'api/vocabulary/child_loinc.php',
                data: $("#add_child_loinc_form").serialize()
            }).done(function() {
                alert("Child LOINC successfully blacklisted!");
                addChildDialog.dialog("close");
                return true;
            }).fail(function() {
                alert("Error:\n\nChild LOINC was not blacklisted.");
                return false;
            });
        }

        addChildDialog = $("#add_child_loinc_dialog").dialog({
			autoOpen: false,
			modal: true,
            height: 530,
			width: 750,
            buttons: {
			    "Add Child LOINC": addChildLOINC,
                "Cancel": function() {
                    addChildDialog.dialog("close");
                }
            }
		});

        $(".add_child_loinc_btn").button({
            icon: "ui-icon-elrplus",
            showLabel: false
        }).on("click", function() {
            let jsonObj = JSON.parse($(this).val());
            let labId = jsonObj.l_id;
            let childLOINC = window.atob(jsonObj.e_value);

            document.getElementById('add_child_loinc_form').reset();

            // reporters
            $.ajax({
                type: 'GET',
                url: 'ajax/reporting_facility.php',
                dataType: 'json'
            }).done(function (data) {
                let reporterOptions = [];
                let labIDObj = $("#lab_id");
                reporterOptions[reporterOptions.length] = "<option value='-1' selected>--</option>";
                if (data.length) {
                    for (reporter in data) {
                        html = "<option value='" + parseInt(data[reporter].id) + "'>" + escapeHtml(data[reporter].ui_name) + "</option>";
                        reporterOptions[reporterOptions.length] = html;
                    }
                    labIDObj.empty().append(reporterOptions.join(''));
                }

                labIDObj.val(labId);
            });

            // master LOINC
            $.ajax({
                type: 'GET',
                url: 'ajax/master_loinc.php',
                dataType: 'json'
            }).done(function (data) {
                let masterLOINCOptions = [];
                let masterLOINCObj = $("#master_loinc");
                masterLOINCOptions[masterLOINCOptions.length] = "<option value='-1' selected>--</option>";
                if (data.length) {
                    for (masterLOINC in data) {
                        html = "<option value='" + parseInt(data[masterLOINC].id) + "'>" + escapeHtml(data[masterLOINC].loinc) + "(" + data[masterLOINC].concept_name + ")</option>";
                        masterLOINCOptions[masterLOINCOptions.length] = html;
                    }
                    masterLOINCObj.empty().append(masterLOINCOptions.join(''));
                }

                masterLOINCObj.val(-1);
            });

            // offscale test result
            $.ajax({
                type: 'GET',
                url: 'ajax/test_result_combined.php',
                dataType: 'json'
            }).done(function (data) {
                let testResultOptions = [];
                testResultOptions[testResultOptions.length] = "<option value='0' selected>--</option>";
                if (data.length) {
                    for (testResult in data) {
                        html = "<option value='" + parseInt(data[testResult].id) + "'>" + escapeHtml(data[testResult].concept) + "</option>";
                        testResultOptions[testResultOptions.length] = html;
                    }
                    $("#offscale_low_result").empty().append(testResultOptions.join(''));
                    $("#offscale_high_result").empty().append(testResultOptions.join(''));
                }
            });

            $("#child_loinc").val(childLOINC);

            addChildDialog.dialog("open");
        });

        $(".blacklist_child_loinc_btn").button({
            icon: "ui-icon-emsa-noentryblack16",
            showLabel: false
        }).on("click", function() {
            let jsonObj = JSON.parse($(this).val());
            let labId = jsonObj.l_id;
            let childLOINC = window.atob(jsonObj.e_value);

            document.getElementById('add_child_loinc_form').reset();

            $("#lab_id").empty().append("<option value='" + labId + "' selected>--</option>");

            // master LOINC
            $.ajax({
                type: 'GET',
                url: 'ajax/master_loinc.php',
                dataType: 'json',
                data: { "loinc": "L999-9" }
            }).done(function (data) {
                let masterLOINCOptions = [];
                let masterLOINCObj = $("#master_loinc");
                if (data.length) {
                    for (masterLOINC in data) {
                        html = "<option value='" + parseInt(data[masterLOINC].id) + "' selected>" + escapeHtml(data[masterLOINC].loinc) + "(" + data[masterLOINC].concept_name + ")</option>";
                        masterLOINCOptions[masterLOINCOptions.length] = html;
                    }
                    masterLOINCObj.empty().append(masterLOINCOptions.join(''));
                }

                $("#child_loinc").val(childLOINC);
                $("#child_concept_name").val("Blacklisted from Bulk Exceptions");

                blacklistChildLOINC();
            });
        });

        let addChildSpecimenDialog;

        function addChildSpecimen() {
            $.ajax({
                type: 'POST',
                url: 'api/vocabulary/child_dictionary.php',
                data: $("#add_child_specimen_form").serialize()
            }).done(function() {
                alert("Child Specimen Source added successfully!");
                addChildSpecimenDialog.dialog("close");
                return true;
            }).fail(function() {
                alert("Child Specimen Source was not added successfully.");
                return false;
            });
        }

        addChildSpecimenDialog = $("#add_child_specimen_dialog").dialog({
			autoOpen: false,
			modal: true,
            height: 350,
			width: 750,
            buttons: {
			    "Add Child Specimen": addChildSpecimen,
                "Cancel": function() {
                    addChildSpecimenDialog.dialog("close");
                }
            }
		});

        $(".add_child_specimen_btn").button({
            icon: "ui-icon-elrplus",
            showLabel: false
        }).on("click", function() {
            let jsonObj = JSON.parse($(this).val());
            let labId = jsonObj.l_id;
            let childSpecimenValue = window.atob(jsonObj.e_value);

            document.getElementById('add_child_specimen_form').reset();

            // reporters
            $.ajax({
                type: 'GET',
                url: 'ajax/reporting_facility.php',
                dataType: 'json'
            }).done(function (data) {
                let reporterOptions = [];
                let labIDObj = $("#spm_lab_id");
                reporterOptions[reporterOptions.length] = "<option value='-1' selected>--</option>";
                if (data.length) {
                    for (reporter in data) {
                        html = "<option value='" + parseInt(data[reporter].id) + "'>" + escapeHtml(data[reporter].ui_name) + "</option>";
                        reporterOptions[reporterOptions.length] = html;
                    }
                    labIDObj.empty().append(reporterOptions.join(''));
                }

                labIDObj.val(labId);
            });

            // Master Specimen
            $.ajax({
                type: 'GET',
                url: 'ajax/master_dictionary.php',
                dataType: 'json',
                data: { "category": "specimen" }
            }).done(function (data) {
                let masterSpecimenOptions = [];
                let masterSpecimenObj = $("#master_specimen");
                masterSpecimenOptions[masterSpecimenOptions.length] = "<option value='-1' selected>--</option>";
                if (data.specimen.length) {
                    for (masterSpecimen in data.specimen) {
                        html = "<option value='" + parseInt(data['specimen'][masterSpecimen].id) + "'>" + escapeHtml(data['specimen'][masterSpecimen].concept) + "</option>";
                        masterSpecimenOptions[masterSpecimenOptions.length] = html;
                    }
                    masterSpecimenObj.empty().append(masterSpecimenOptions.join(''));
                }

                masterSpecimenObj.val(-1);
            });

            $("#child_specimen").val(childSpecimenValue);

            addChildSpecimenDialog.dialog("open");
        });

        let addChildCodedValueDialog;

        function addChildCodedValue() {
            $.ajax({
                type: 'POST',
                url: 'api/vocabulary/child_dictionary.php',
                data: $("#add_child_coded_value_form").serialize()
            }).done(function() {
                alert("Child Dictionary item added successfully!");
                addChildCodedValueDialog.dialog("close");
                return true;
            }).fail(function() {
                alert("Child Dictionary item was not added successfully.");
                return false;
            });
        }

        addChildCodedValueDialog = $("#add_child_coded_value_dialog").dialog({
			autoOpen: false,
			modal: true,
            height: 400,
			width: 950,
            buttons: {
			    "Add Child Dictionary Item": addChildCodedValue,
                "Cancel": function() {
                    addChildCodedValueDialog.dialog("close");
                }
            }
		});

        $(".add_child_coded_value_btn").button({
            icon: "ui-icon-elrplus",
            showLabel: false
        }).on("click", function() {
            let jsonObj = JSON.parse($(this).val());
            let labId = jsonObj.l_id;
            let childCodedValue = jsonObj.child_value;
            let appPath = jsonObj.app_path;

            document.getElementById('add_child_coded_value_form').reset();

            // reporters
            $.ajax({
                type: 'GET',
                url: 'ajax/reporting_facility.php',
                dataType: 'json'
            }).done(function (data) {
                let reporterOptions = [];
                let labIDObj = $("#coded_value_lab_id");
                reporterOptions[reporterOptions.length] = "<option value='-1' selected>--</option>";
                if (data.length) {
                    for (reporter in data) {
                        html = "<option value='" + parseInt(data[reporter].id) + "'>" + escapeHtml(data[reporter].ui_name) + "</option>";
                        reporterOptions[reporterOptions.length] = html;
                    }
                    labIDObj.empty().append(reporterOptions.join(''));
                }

                labIDObj.val(labId);
            });

            function onMasterCategoryChange() {
                let masterCodedValueValue = $("#master_coded_value_value");
                let masterCodedValueCategory = $("#master_coded_value_category").val();

                let masterCodedValueValueOptions = [];

                if (masterCodedValueCategory === "-1") {
                    masterCodedValueValueOptions[masterCodedValueValueOptions.length] = "<option value='-1' selected>--</option>";
                    masterCodedValueValue.empty().append(masterCodedValueValueOptions.join(''));
                    masterCodedValueValue.val(-1);
                } else {
                    $.ajax({
                        type: 'GET',
                        url: 'ajax/master_dictionary.php',
                        dataType: 'json',
                        data: { "category": masterCodedValueCategory }
                    }).done(function (data) {
                        masterCodedValueValueOptions[masterCodedValueValueOptions.length] = "<option value='-1' selected>--</option>";
                        if (data[masterCodedValueCategory].length) {
                            for (masterCodedValue in data[masterCodedValueCategory]) {
                                html = "<option value='" + parseInt(data[masterCodedValueCategory][masterCodedValue].id) + "'>" + escapeHtml(data[masterCodedValueCategory][masterCodedValue].concept) + "</option>";
                                masterCodedValueValueOptions[masterCodedValueValueOptions.length] = html;
                            }
                            masterCodedValueValue.empty().append(masterCodedValueValueOptions.join(''));
                        }

                        masterCodedValueValue.val(-1);
                    });
                }
            }

            // Master Vocab Hierarchy
            $.ajax({
                type: 'GET',
                url: 'ajax/master_dictionary.php',
                dataType: 'json'
            }).done(function (data) {
                let masterCodedValueOptions = [];
                let masterCodedValueCategoryObj = $("#master_coded_value_category");

                masterCodedValueOptions[masterCodedValueOptions.length] = "<option value='-1' selected>--</option>";
                if (data !== undefined) {
                    for (let [masterCategory, masterCategoryVals] of Object.entries(data)) {
                        html = "<option value='" + escapeHtml(masterCategory) + "'>" + escapeHtml(masterCategory) + "</option>";
                        masterCodedValueOptions[masterCodedValueOptions.length] = html;
                    }
                    masterCodedValueCategoryObj.empty().append(masterCodedValueOptions.join(''));
                }

                masterCodedValueCategoryObj.val(-1);
            });

            $("#child_coded_value").val(childCodedValue);
            $("#child_dictionary_path").text(appPath);

            $("body").off("change", "#master_coded_value_category");

            addChildCodedValueDialog.dialog("open");

            $("body").on("change", "#master_coded_value_category", function() {
                onMasterCategoryChange();
            });
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
    .blacklist_child_loinc_btn, .add_child_loinc_btn, .add_child_specimen_btn, .add_child_coded_value_btn, .retry_btn { vertical-align: middle; height: 24px; }
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

<h1 class="elrhdg"><span class="ui-icon ui-icon-header ui-icon-elrerrorbig"></span>Bulk Exceptions by Reporter</h1>

<?php
if ((isset($clean['retry_exception_id'])) && (isset($clean['lab_id']))) {
    // attempt a retry
    include_once __DIR__ . '/retry.php';
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
        $sql = "SELECT l.ui_name AS lab, l.id AS lab_id, se.description AS description, se.id AS e_id, se.allow_retry AS allow_retry, se.allow_child_vocab_add AS allow_child_vocab_add, sme.info AS info, count(sme.exception_id) AS exceptions 
                FROM system_message_exceptions sme
                LEFT JOIN system_messages sm ON (sme.system_message_id = sm.id)
                LEFT JOIN structure_labs l ON (sm.lab_id = l.id)
                INNER JOIN system_exceptions se ON (sme.exception_id = se.exception_id)
                WHERE (sm.deleted != 1 OR sm.deleted IS NULL) 
                AND sm.final_status = :exceptionStatus
                AND sm.vocab_app_id = 2
                GROUP BY l.ui_name, l.id, se.id, se.description, se.allow_retry, se.allow_child_vocab_add, sme.info
                ORDER BY lab, description, exceptions DESC, info;";
        $stmt = $adminDbConn->prepare($sql);
        $stmt->bindValue(':exceptionStatus', EXCEPTIONS_STATUS, PDO::PARAM_INT);

        $stmt->execute();

        while ($row = $stmt->fetchObject()) {
            if (!isset($etaskLookup[intval($row->e_id)])) {
                $etaskLookup[intval($row->e_id)] = array(
                    'description' => trim($row->description),
                    'allow_retry' => ((trim($row->allow_retry == 't')) ? true : false),
                    'allow_child_vocab_add' => ((trim($row->allow_child_vocab_add == 't')) ? true : false)
                );
            }
            if (!isset($labIdArr[intval($row->lab_id)])) {
                $labIdArr[intval($row->lab_id)] = trim($row->lab);
            }
            $etaskArr[trim($row->lab_id)][intval($row->e_id)][$row->info] = intval($row->exceptions);
        }
    } catch (Throwable $e) {
        Udoh\Emsa\Utils\ExceptionUtils::logException($e);
        Udoh\Emsa\Utils\DisplayUtils::drawError('Unable to query Exceptions list:  ' . Udoh\Emsa\Utils\DisplayUtils::xSafe($e->getMessage()));
    }

    if (isset($etaskArr) && is_array($etaskArr) && (count($etaskArr) > 0)) {

        echo '<div id="tabs"><ul>';

        foreach ($etaskArr as $bulkExceptionFacilityId => $bulkExceptionFacilityData) {
            foreach ($bulkExceptionFacilityData as $bulkExceptionId => $bulkExceptionValues) {
                foreach ($bulkExceptionValues as $bulkExceptionDescription => $bulkExceptionDescriptionCount) {
                    if (isset($bulkExceptionAggregateFacilityData[$bulkExceptionFacilityId])) {
                        $bulkExceptionAggregateFacilityData[$bulkExceptionFacilityId] += $bulkExceptionDescriptionCount;
                    } else {
                        $bulkExceptionAggregateFacilityData[$bulkExceptionFacilityId] = $bulkExceptionDescriptionCount;
                    }
                    
                    if (isset($bulkExceptionAggregateData[$bulkExceptionFacilityId][$bulkExceptionId])) {
                        $bulkExceptionAggregateData[$bulkExceptionFacilityId][$bulkExceptionId] += $bulkExceptionDescriptionCount;
                    } else {
                        $bulkExceptionAggregateData[$bulkExceptionFacilityId][$bulkExceptionId] = $bulkExceptionDescriptionCount;
                    }
                }
            }
            echo '<li><a href="#tabs-' . md5($labIdArr[$bulkExceptionFacilityId]) . '">' . Udoh\Emsa\Utils\DisplayUtils::xSafe($labIdArr[$bulkExceptionFacilityId]) . ' <div class="bulk-exception-counter ui-corner-all">&nbsp;' . intval($bulkExceptionAggregateFacilityData[$bulkExceptionFacilityId]) . '&nbsp;</div></a></li>';
        }

        echo '</ul>';

        foreach ($bulkExceptionAggregateFacilityData as $etask_lab_arr_key => $etask_lab_arr_value) {
            echo '<div id="tabs-' . md5($labIdArr[$etask_lab_arr_key]) . '">';
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
                    foreach ($bulkExceptionAggregateData[$etask_lab_arr_key] as $etask_lab_etype_arr_key => $etask_lab_etype_arr_value) {
                        echo '<tr><td style="vertical-align: top;"><span><strong style="font-size: 1.05em;">' . Udoh\Emsa\Utils\DisplayUtils::xSafe($etaskLookup[$etask_lab_etype_arr_key]['description']) . '</strong><br>' . (($etaskLookup[$etask_lab_etype_arr_key]['allow_retry']) ? ' <button class="retry_btn" type="button" title="Retry messages with this exception type" value=\'' . json_encode(array('l_id' => (int) $etask_lab_arr_key, 'e_id' => (int) $etask_lab_etype_arr_key, 'e_value' => null)) . '\'>Retry</button><div class="emsa_toolbar_separator"></div>' : '') . '<span style="display: inline-block; vertical-align: middle; padding-bottom: 3px;" class="ui-icon ui-icon-newwin"></span><a href="?selected_page=6&submenu=31&cat=1&type=3&f[lab][]=' . intval($etask_lab_arr_key) . '&f[eflag][]=' . intval($etask_lab_etype_arr_key) . '" target="_blank" title="View all messages with \'' . Udoh\Emsa\Utils\DisplayUtils::xSafe($etaskLookup[$etask_lab_etype_arr_key]['description']) . '\' errors...">' . intval($etask_lab_etype_arr_value) . ' error' . ((intval($etask_lab_etype_arr_value) > 1) ? 's' : '') . '</a></span></td><td colspan="2"><table class="audit_log" style="width: 100%">';
                        foreach ($etaskArr[$etask_lab_arr_key][$etask_lab_etype_arr_key] as $etask_info => $etask_count) {
                            echo '<tr><td class="mono_prewrap" style="width: auto;">' . EmsaUtils::trimNEDSSErrorHTML($emsaDbFactory->getConnection(), $etask_info) . '</td><td style="width: 145px; min-width: 145px; white-space: nowrap;"><span>';
                            if ($etaskLookup[$etask_lab_etype_arr_key]['allow_child_vocab_add']) {
                                echo ' <button class="blacklist_child_loinc_btn" type="button" title="Blacklist Child LOINC" value=\'' . json_encode(array('l_id' => (int) $etask_lab_arr_key, 'e_id' => (int) $etask_lab_etype_arr_key, 'e_value' => base64_encode($etask_info))) . '\'>Blacklist</button><div class="emsa_toolbar_separator"></div><button class="add_child_loinc_btn" type="button" title="Add Child LOINC" value=\'' . json_encode(array('l_id' => (int) $etask_lab_arr_key, 'e_id' => (int) $etask_lab_etype_arr_key, 'e_value' => base64_encode($etask_info))) . '\'>Add</button><div class="emsa_toolbar_separator"></div>';
                            }

                            if ((int) $etask_lab_etype_arr_key == SystemExceptions::SPECIMEN_NOT_MAPPED) {
                                echo ' <button class="add_child_specimen_btn" type="button" title="Add Child Specimen Source mapping" value=\'' . json_encode(array('l_id' => (int) $etask_lab_arr_key, 'e_id' => (int) $etask_lab_etype_arr_key, 'e_value' => base64_encode($etask_info))) . '\'>Add</button><div class="emsa_toolbar_separator"></div>';
                            }

                            if ((int) $etask_lab_etype_arr_key == SystemExceptions::UNABLE_TO_FIND_APPLICATION_CODE) {
                                echo ' <button class="add_child_coded_value_btn" type="button" title="Add Child Dictionary item" value=\'' . json_encode(array('l_id' => (int) $etask_lab_arr_key, 'e_id' => (int) $etask_lab_etype_arr_key, 'child_value' => EmsaUtils::getAppPathExceptionValue($emsaDbFactory->getConnection(), $etask_info), 'app_path' => EmsaUtils::getAppPathExceptionPath($emsaDbFactory->getConnection(), $etask_info))) . '\'>Add</button><div class="emsa_toolbar_separator"></div>';
                            }

                            if ($etaskLookup[$etask_lab_etype_arr_key]['allow_retry']) {
                                echo ' <button class="retry_btn" type="button" title="Retry messages with this exception value" value=\'' . json_encode(array('l_id' => (int) $etask_lab_arr_key, 'e_id' => (int) $etask_lab_etype_arr_key, 'e_value' => base64_encode($etask_info))) . '\'>Retry</button><div class="emsa_toolbar_separator"></div>';
                            }

                            echo '<span style="display: inline-block; vertical-align: middle; padding-bottom: 3px;" class="ui-icon ui-icon-newwin"></span><a href="?selected_page=6&submenu=31&cat=1&type=3&f[lab][]=' . intval($etask_lab_arr_key) . '&f[eflag][]=' . intval($etask_lab_etype_arr_key) . '&f[evalue]=' . urlencode(base64_encode($etask_info)) . '" target="_blank" title="View all messages with \'' . Udoh\Emsa\Utils\DisplayUtils::xSafe($etaskLookup[$etask_lab_etype_arr_key]['description']) . '\' errors containing a value of \'' . Udoh\Emsa\Utils\DisplayUtils::xSafe($etask_info) . '\'...">' . intval($etask_count) . ' error' . ((intval($etask_count) > 1) ? 's' : '') . '</a></span></td></tr>';
                        }
                        echo "</table></td></tr>";
                    }

                    echo "</tbody></table>";
                    echo '</div>';
                }

                echo '</div>';
            } else {
                Udoh\Emsa\Utils\DisplayUtils::drawHighlight("No Exceptions Found!");
            }
            ?>
            </div>

<div id="add_child_loinc_dialog" title="Add New Child LOINC">
    <form id="add_child_loinc_form">
        <div class="addnew_field"><label class="vocab_add_form" for="lab_id">Reporter:</label><br><select class="ui-corner-all" name="lab_id" id="lab_id"></select></div>
        <div class="addnew_field"><label class="vocab_add_form" for="child_loinc">Child LOINC:</label><br><input class="ui-corner-all" type="text" name="child_loinc" id="child_loinc"></div>
        <div class="addnew_field"><label class="vocab_add_form" for="child_concept_name">Child Concept Name:</label><br><input class="ui-corner-all" type="text" name="child_concept_name" id="child_concept_name"></div>

        <div class="add-form-divider"></div>
        <div class="addnew_field"><label class="vocab_add_form" for="master_loinc">Master LOINC:</label><br><select class="ui-corner-all" style="width: 100%;" name="master_loinc" id="master_loinc"></select></div>

        <div class="add-form-divider"></div>
        <div class="addnew_field">
            <label class="vocab_add_form" for="workflow">Message Workflow</label><br>
            <select class="ui-corner-all" name="workflow" id="workflow">
                <option value="<?php echo (int) ENTRY_STATUS; ?>" selected>Automated Processing</option>
                <option value="<?php echo (int) SEMI_AUTO_STATUS; ?>">Semi-Automated Entry</option>
                <option value="<?php echo (int) QA_STATUS; ?>">QA Review</option>
            </select>
        </div>
        <div class="addnew_field">
            <label class="vocab_add_form" for="interpret_override">Result Interpretation</label><br>
            <select class="ui-corner-all" name="interpret_override" id="interpret_override">
                <option value="u" selected>Set by OBX-2</option>
                <option value="t">Override Quantitative</option>
                <option value="f">Override Coded Entry</option>
            </select>
        </div>
        <div class="addnew_field"><label class="vocab_add_form" for="offscale_low_result">Off-scale Low Test Result:</label><br><select class="ui-corner-all" name="offscale_low_result" id="offscale_low_result"></select></div>
        <div class="addnew_field"><label class="vocab_add_form" for="offscale_high_result">Off-scale High Test Result:</label><br><select class="ui-corner-all" name="offscale_high_result" id="offscale_high_result"></select></div>

        <div class="add-form-divider"></div>
        <div class="addnew_field">
            <label class="vocab_add_form" for="admin_notes">Admin Notes:</label><br>
            <textarea name="admin_notes" id="admin_notes" class="ui-corner-all">Added from 'Bulk Exceptions' UI</textarea>
        </div>
    </form>
</div>

<div id="add_child_specimen_dialog" title="Add New Child Specimen Source">
    <form id="add_child_specimen_form">
        <div class="addnew_field"><label class="vocab_add_form" for="spm_lab_id">Reporter:</label><br><select class="ui-corner-all" name="lab_id" id="spm_lab_id"></select></div>
        <div class="addnew_field"><label class="vocab_add_form" for="child_specimen">Child Specimen Source:</label><br><input class="ui-corner-all" type="text" name="child_concept" id="child_specimen"></div>

        <div class="add-form-divider"></div>
        <div class="addnew_field"><label class="vocab_add_form" for="master_specimen">Master Specimen Source:</label><br><select class="ui-corner-all" style="width: 100%;" name="master_id" id="master_specimen"></select></div>

        <div class="add-form-divider"></div>
        <div class="addnew_field"><label class="vocab_add_form" for="spm_comment">Append to Comments:</label><br><input class="ui-corner-all" type="text" name="comment" id="spm_comment"></div>
    </form>
</div>

<div id="add_child_coded_value_dialog" title="Add New Child Dictionary Item">
    <form id="add_child_coded_value_form">
        <div class="addnew_field"><strong>For App XML Path:</strong> <div id="child_dictionary_path" style="font-family: Consolas, monospace"></div></div>

        <div class="add-form-divider"></div>
        <div class="addnew_field"><label class="vocab_add_form" for="coded_value_lab_id">Reporter:</label><br><select class="ui-corner-all" name="lab_id" id="coded_value_lab_id"></select></div>
        <div class="addnew_field"><label class="vocab_add_form" for="child_coded_value">Child Value:</label><br><input class="ui-corner-all" style="min-width: 300px" type="text" name="child_concept" id="child_coded_value"></div>

        <div class="add-form-divider"></div>
        <div class="addnew_field">
            <label class="vocab_add_form" for="master_coded_value_category">Master Category:</label><select class="ui-corner-all" name="master_category_id" id="master_coded_value_category"></select><br>
            <span class="ui-icon ui-icon-arrowreturnthick-1-e" style="float: left; margin-left: 30px; margin-top: 3px;"></span>
            <label class="vocab_add_form" for="master_coded_value_value">Master Concept Name:</label><select class="ui-corner-all" name="master_id" id="master_coded_value_value"><option value="-1">--</option> </select>
        </div>

        <div class="add-form-divider"></div>
        <div class="addnew_field"><label class="vocab_add_form" for="cd_comment">Append to Comments:</label><br><input class="ui-corner-all" type="text" name="comment" id="cd_comment"></div>
    </form>
</div>
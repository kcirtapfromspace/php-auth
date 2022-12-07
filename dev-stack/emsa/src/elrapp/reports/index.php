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

use Udoh\Emsa\Auth\Authenticator;
use Udoh\Emsa\UI\AccessiblePopupMultiselect;
use Udoh\Emsa\Utils\CoreUtils;
use Udoh\Emsa\Utils\DateTimeUtils;
use Udoh\Emsa\Utils\DisplayUtils;

if (!class_exists('\Udoh\Emsa\Auth\Authenticator') || !Authenticator::userHasPermission(Authenticator::URIGHTS_ADMIN)) {
    ob_end_clean();
    header($_SERVER['SERVER_PROTOCOL'] . " 404 Not Found", TRUE, 404);
    exit;
}
if (isset($_POST['from_date']) && isset($_POST['to_date']) && !EmsaUtils::emptyTrim($_POST['from_date']) && !EmsaUtils::emptyTrim($_POST['to_date'])) {
    // update sessionized date params if updated by user
    DateTimeUtils::setDashboardDates(trim($_POST['from_date']), trim($_POST['to_date']));
}

if (isset($_POST['lab_filter']) && is_array($_POST['lab_filter'])) {
    $_SESSION[EXPORT_SERVERNAME]['dashboard_lab_filter'] = array();
    foreach ($_POST['lab_filter'] as $labFilterArray) {
        $_SESSION[EXPORT_SERVERNAME]['dashboard_lab_filter'][] = intval($labFilterArray);
    }
}

if (isset($_POST['report_action']) && (trim($_POST['report_action']) == 'reset')) {
    // reset view to default
    unset($_SESSION[EXPORT_SERVERNAME]['dashboard_date_from'], $_SESSION[EXPORT_SERVERNAME]['dashboard_date_to'], $_SESSION[EXPORT_SERVERNAME]['dashboard_lab_filter']);
}

DateTimeUtils::initializeDashboardDates();

if (!isset($_SESSION[EXPORT_SERVERNAME]['dashboard_lab_filter'])) {
    $_SESSION[EXPORT_SERVERNAME]['dashboard_lab_filter'] = array();
}

session_write_close(); // done writing to session; prevent blocking
//$dashboard_summary = getDashboardSummary($_SESSION[EXPORT_SERVERNAME]['dashboard_date_from'], $_SESSION[EXPORT_SERVERNAME]['dashboard_date_to']);
?>

<script type="text/javascript" src="https://www.gstatic.com/charts/loader.js"></script>
<script type="text/javascript">
    google.charts.load('current', {packages: ['corechart', 'table']});

    $(function() {
        $(".date_range").datepicker({
            changeMonth: true,
            changeYear: true,
            showButtonPanel: true
        });

        $("#apply_date").button({
            icon: "ui-icon-elrretry"
        }).on("click", function() {
            $("#reporting_area").html('<img alt="Generating report" style="vertical-align: bottom;" src="<?php echo $webappBaseUrl; ?>img/ajax-loader.gif" height="16" width="16" border="0" /> <em>Generating report...</em>');
            $("#content-wrapper-1").addClass('busy');
            return true;
        });

        $("#reset_date").button({
            icon: "ui-icon-elrcancel"
        }).on("click", function() {
            $("#report_action").val("reset");
            $("#apply_date").trigger("click");
        });

        $("#report_list li").on("click", function() {
            $("#reporting_area").html('<img alt="Generating report" style="vertical-align: bottom;" src="<?php echo $webappBaseUrl; ?>img/ajax-loader.gif" height="16" width="16" border="0" /> <em>Generating report...</em>');
            $("#content-wrapper-1").addClass('busy');
            var reportId = $(this).attr("report");
            $.post("reports/viewer.php", {report_id: reportId}, function(reportData) {
                $("#reporting_area").html(reportData);
                $("#content-wrapper-1").removeClass('busy');
                drawChart();
            }).error(function(vaErrXhr, vaErrText, vaErrThrown) {
                $("#reporting_area").html('');
                $("#content-wrapper-1").removeClass('busy');
                alert("Could not load report.\n\nError Details:\n" + vaErrText + " (" + vaErrThrown + ")");
            });
        });

<?php
if (isset($_REQUEST['report_id']) && !EmsaUtils::emptyTrim($_REQUEST['report_id'])) {
    echo 'setTimeout(function() { $("#report_list li[report=\'' . addslashes(DisplayUtils::xSafe(trim($_REQUEST['report_id']))) . '\']").trigger("click"); }, 200);';
} elseif (isset($_SESSION[EXPORT_SERVERNAME]['reporting_current_report']) && !empty($_SESSION[EXPORT_SERVERNAME]['reporting_current_report'])) {
    echo 'setTimeout(function() { $("#report_list li[report=\'' . $_SESSION[EXPORT_SERVERNAME]['reporting_current_report'] . '\']").trigger("click"); }, 200);';
}
?>
    });
</script>	

<style type="text/css" media="all">
    strong.big_strong { font-size: 17pt; margin: 0; color: darkolivegreen; }
    strong.medium_strong { margin-top: 1.5em; display: block; font-size: 14pt; color: firebrick; font-family: 'Oswald', sans-serif; border-bottom: 1px lightgray solid; font-weight: 500; }
    em { font-size: 10pt; color: dimgray; font-style: italic; }
    #reporting_filters label, .reporting_filter_label { font-family: 'Open Sans', Arial, sans-serif; font-weight: 700; color: black; }
    #reporting_filters fieldset.emsa-multiselect label { font-weight: 400 !important; }
    .reporting_area { cursor: default; display: inline-block; position: relative; float: left; width: 70%; height: auto; margin: 10px; top: -5px; }
    .reporting_filters { background-color: ghostwhite; display: block; padding: 10px; position: relative; width: 25%; height: 100%; top: -5px; float: left; border-right: 1px darkgray solid; border-top: 1px darkgray solid; }
    #report_list { margin-top: 10px; border-top: 1px darkgray dotted; font-weight: 600 !important; color: midnightblue; }
    #report_list li { padding: 2px; border-bottom: 1px darkgray dotted; cursor: pointer; }
    #report_list li:hover { background-color: cornflowerblue; color: white; text-decoration: underline; }
    .busy { cursor: progress !important; }
</style>

<h1 class="elrhdg"><span class="ui-icon ui-icon-header ui-icon-emsareporting"></span>Reporting</h1>

<div class="reporting_filters">
    <form id="reporting_filters" method="POST" action="<?php echo $webappBaseUrl; ?>?selected_page=6&submenu=7">
        <label for="from_date">Start Date:</label><br>
        <input class="ui-corner-all date_range" type="text" name="from_date" size="10" id="from_date" value="<?php echo $_SESSION[EXPORT_SERVERNAME]['dashboard_date_from']; ?>"><br><br>
        <label for="to_date">End Date:</label><br>
        <input class="ui-corner-all date_range" type="text" name="to_date" size="10" id="to_date" value="<?php echo $_SESSION[EXPORT_SERVERNAME]['dashboard_date_to']; ?>"><br><br>
        <?php
            $reporterList = CoreUtils::getReporterList($emsaDbFactory->getConnection());

            $reporterFilter = new AccessiblePopupMultiselect($reporterList, $_SESSION[EXPORT_SERVERNAME]['dashboard_lab_filter']);
            $reporterFilter->render("Include", "lab_filter");
        ?>
        <br><br>
        <input type="hidden" name="report_action" id="report_action" value="">
        <button id="apply_date" type="submit" title="Update/refresh using these settings">Update</button>
        <button id="reset_date" type="button" title="Reset to default settings">Defaults</button>
        <br><br>
        <span class="reporting_filter_label">Available Reports:</span>
        <ul id="report_list">
            <li report="qa_flags"><span class="ui-icon ui-icon-elrview" style="display: inline-block; vertical-align: top; margin-right: 3px;"></span>QA Flag Stats</li>
            <li report="other_flag_reasons"><span class="ui-icon ui-icon-elrview" style="display: inline-block; vertical-align: top; margin-right: 3px;"></span>'Other' QA Flag Reasons</li>
            <li report="de_flag_reasons"><span class="ui-icon ui-icon-elrview" style="display: inline-block; vertical-align: top; margin-right: 3px;"></span>'Data Entry Error' QA Flag Types</li>
            <li report="discarded_messages"><span class="ui-icon ui-icon-elrview" style="display: inline-block; vertical-align: top; margin-right: 3px;"></span>Discarded Duplicate ELR Messages</li>
            <li report="dashboard_tabular"><span class="ui-icon ui-icon-elrview" style="display: inline-block; vertical-align: top; margin-right: 3px;"></span>Tabular Dashboard Data</li>
        </ul>
    </form>
</div>

<div id="reporting_area" class="reporting_area ui-corner-all">
    <em>No report loaded.</em>
</div>
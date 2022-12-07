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

if (!class_exists('Udoh\Emsa\Auth\Authenticator') || !Udoh\Emsa\Auth\Authenticator::userHasPermission(Udoh\Emsa\Auth\Authenticator::URIGHTS_ADMIN)) {
    ob_end_clean();
    header($_SERVER['SERVER_PROTOCOL'] . " 404 Not Found", TRUE, 404);
    exit;
}
	
	try {
		$dbFactory = new Udoh\Emsa\PDOFactory\PostgreSQL($replicationDbHost, $replicationDbPort, $emsaDbName, $emsaDbUser, $emsaDbPass, $emsaDbSchemaPDO);
		$dbConn = $dbFactory->getConnection();
		$dashboard = new Udoh\Emsa\UI\Dashboard($dbConn);
	} catch (Throwable $e) {
		Udoh\Emsa\Utils\ExceptionUtils::logException($e);
		Udoh\Emsa\Utils\DisplayUtils::drawError('Unable to load report:  '.$e->getMessage(), true);
	}
	
	echo '<strong class="big_strong">EMSA Dashboard &mdash; Tabular Data</strong>';
	echo '<br><em>(For HL7 messages reported between '.$_SESSION[EXPORT_SERVERNAME]['dashboard_date_from'].' to '.$_SESSION[EXPORT_SERVERNAME]['dashboard_date_to'].')</em>';
	
	$dashboard_summary = $dashboard->getDashboardSummary($_SESSION[EXPORT_SERVERNAME]['dashboard_date_from'], $_SESSION[EXPORT_SERVERNAME]['dashboard_date_to']);
	$dashboard_avgnewcmr = $dashboard->getAvgCMRCreateTime($_SESSION[EXPORT_SERVERNAME]['dashboard_date_from'], $_SESSION[EXPORT_SERVERNAME]['dashboard_date_to']);
	$dashboard_newcase = $dashboard->getDashboardNewCase($_SESSION[EXPORT_SERVERNAME]['dashboard_date_from'], $_SESSION[EXPORT_SERVERNAME]['dashboard_date_to']);
	$dashboard_appendcase = $dashboard->getDashboardAppendedCase($_SESSION[EXPORT_SERVERNAME]['dashboard_date_from'], $_SESSION[EXPORT_SERVERNAME]['dashboard_date_to']);
	$dashboard_discardcase = $dashboard->getDashboardDiscardedCase($_SESSION[EXPORT_SERVERNAME]['dashboard_date_from'], $_SESSION[EXPORT_SERVERNAME]['dashboard_date_to']);
	$dashboard_blacklist_summary = $dashboard->getDashboardBlacklistSummary($_SESSION[EXPORT_SERVERNAME]['dashboard_date_from'], $_SESSION[EXPORT_SERVERNAME]['dashboard_date_to']);
	$dashboard_graylist_summary = $dashboard->getDashboardGraylistSummary($_SESSION[EXPORT_SERVERNAME]['dashboard_date_from'], $_SESSION[EXPORT_SERVERNAME]['dashboard_date_to']);
	$dashboard_mqf = $dashboard->getDashboardMessageQuality($_SESSION[EXPORT_SERVERNAME]['dashboard_date_from'], $_SESSION[EXPORT_SERVERNAME]['dashboard_date_to']);
	$dashboard_lab = $dashboard->getDashboardLab($_SESSION[EXPORT_SERVERNAME]['dashboard_date_from'], $_SESSION[EXPORT_SERVERNAME]['dashboard_date_to']);
	$condition_summary = $dashboard->getDashboardConditionSummary($_SESSION[EXPORT_SERVERNAME]['dashboard_date_from'], $_SESSION[EXPORT_SERVERNAME]['dashboard_date_to']);
	$dashboard_automation_factor = $dashboard->getDashboardAutomationFactor($_SESSION[EXPORT_SERVERNAME]['dashboard_date_from'], $_SESSION[EXPORT_SERVERNAME]['dashboard_date_to']);
    
    // consolidate new/updated assigned counts into a single list for canonical count
	foreach ($dashboard_newcase as $dashboard_newcase_category => $dashboard_newcase_count) {
		$dashboard_appendcase[$dashboard_newcase_category]['new_cmr'] = $dashboard_newcase_count;
	}
	
	// calculate automation percentage
	$automation_denominator = array_sum($dashboard_automation_factor);
	foreach ($dashboard_automation_factor as $auto_method => $auto_method_count) {
		$dashboard_automation_factor2[$auto_method] = array(
			'count'		=> $auto_method_count, 
			'percent'	=> (($automation_denominator > 0) ? trim(round((floatval(($auto_method_count)/($automation_denominator))*100), 1)).'%' : '--')
		);
	}
	
	// Add totals to applicable data
	$assigned_totals = array(
        'new_cmr'       => array(),
        'labs_updated'  => array()
    );
	foreach($dashboard_appendcase as $appendcase_totals_category => $appendcase_totals_data) {
		$assigned_totals['new_cmr'][]			= $appendcase_totals_data['new_cmr'];
		$assigned_totals['labs_updated'][]		= $appendcase_totals_data['labs_updated'];
		//$assigned_totals['contact_counter'][]	= $appendcase_totals_data['contact_counter'];
	}
	$assigned_newcmr_total = array_sum($assigned_totals['new_cmr']);
	$assigned_cmrcounter_total = array_sum($assigned_totals['labs_updated']);
	//$assigned_contactcounter_total = array_sum($assigned_totals['contact_counter']);
	
	$dashboard_appendcase['[Totals]'] = array(
		'new_cmr' 			=> $assigned_newcmr_total,
		'labs_updated'		=> $assigned_cmrcounter_total
	);
	
	$discard_total = array_sum($dashboard_discardcase);
	$graylist_total = array_sum($dashboard_graylist_summary);
	$blacklist_total = array_sum($dashboard_blacklist_summary);
	$mqf_total = array_sum($dashboard_mqf);
	
	$dashboard_discardcase['[Total]'] = intval($discard_total);
	$dashboard_graylist_summary['[Total]'] = intval($graylist_total);
	$dashboard_blacklist_summary['[Total]'] = intval($blacklist_total);
	$dashboard_mqf['[Total]'] = intval($mqf_total);
    
    $dbConn = null;
    $dbFactory = null;
	
?>

<strong class="medium_strong">EMSA Queue Overview</strong><em><?php echo nl2br(htmlentities(Udoh\Emsa\UI\Dashboard::TOOLTIP_SUMMARY, ENT_QUOTES, 'UTF-8')); ?></em>
<div id="summary_chart" style="margin-top: 15px; height: 100%;"></div>

<strong class="medium_strong">Disease Summary</strong><em><?php echo nl2br(htmlentities(Udoh\Emsa\UI\Dashboard::TOOLTIP_CONDITION_SUMMARY, ENT_QUOTES, 'UTF-8')); ?></em>
<div id="condition_chart" style="margin-top: 15px; height: 100%;"></div>

<strong class="medium_strong">Assigned Labs by CDC Category</strong><em><strong>For 'New CMRs Generated by ELR' column:</strong><br><?php echo nl2br(htmlentities(Udoh\Emsa\UI\Dashboard::TOOLTIP_NEW_CMR, ENT_QUOTES, 'UTF-8')); ?>
    <br><br><strong>For 'Existing Labs Updated by ELR' column:</strong><br><?php echo nl2br(htmlentities(Udoh\Emsa\UI\Dashboard::TOOLTIP_UPDATED_LAB, ENT_QUOTES, 'UTF-8')); ?></em>
<div id="assigned_chart" style="margin-top: 15px; height: 100%;"></div>

<strong class="medium_strong">Messages Blacklisted by Reporting Facility</strong><em><?php echo nl2br(htmlentities(Udoh\Emsa\UI\Dashboard::TOOLTIP_BLACKLIST, ENT_QUOTES, 'UTF-8')); ?></em>
<div id="blacklist_summary_chart" style="margin-top: 15px; height: 100%;"></div>

<strong class="medium_strong">Duplicate Messages Discarded by CDC Category</strong><em><?php echo nl2br(htmlentities(Udoh\Emsa\UI\Dashboard::TOOLTIP_DISCARD, ENT_QUOTES, 'UTF-8')); ?></em>
<div id="discardcase_chart" style="margin-top: 15px; height: 100%;"></div>

<strong class="medium_strong">Messages Graylisted by CDC Category</strong><em><?php echo nl2br(htmlentities(Udoh\Emsa\UI\Dashboard::TOOLTIP_GRAYLIST, ENT_QUOTES, 'UTF-8')); ?></em>
<div id="graylist_summary_chart" style="margin-top: 15px; height: 100%;"></div>

<strong class="medium_strong">Avg. Time from Specimen Collection to LHD Routing</strong><em><?php echo nl2br(htmlentities(Udoh\Emsa\UI\Dashboard::TOOLTIP_AVGTIME, ENT_QUOTES, 'UTF-8')); ?></em>
<div id="avgnew_chart" style="margin-top: 15px; height: 100%;"></div>

<strong class="medium_strong">Messages with QA Errors</strong><em><?php echo nl2br(htmlentities(Udoh\Emsa\UI\Dashboard::TOOLTIP_MQF, ENT_QUOTES, 'UTF-8')); ?></em>
<div id="mqf_chart" style="margin-top: 15px; height: 100%;"></div>

<strong class="medium_strong">Automation Percentage</strong><em><?php echo nl2br(htmlentities(Udoh\Emsa\UI\Dashboard::TOOLTIP_AUTOFACTOR, ENT_QUOTES, 'UTF-8')); ?></em>
<div id="autofactor_chart" style="margin-top: 15px; height: 100%;"></div>

<script type='text/javascript'>
	function drawChart() {
		
		var summaryData = new google.visualization.DataTable();
		var conditionData = new google.visualization.DataTable();
		var assignedLabData = new google.visualization.DataTable();
		var discardCaseData = new google.visualization.DataTable();
		var graylistData = new google.visualization.DataTable();
		var blacklistData = new google.visualization.DataTable();
		var mqfData = new google.visualization.DataTable();
		var autoFactorData = new google.visualization.DataTable();
		var avgNewCmrData = new google.visualization.DataTable();
		summaryData.addColumn('string', 'Message Queue');
		summaryData.addColumn('number', 'HL7 Messages Received');
		conditionData.addColumn('string', 'Condition');
		conditionData.addColumn('number', 'HL7 Messages Received');
		conditionData.addColumn('number', 'Assigned Labs');
		assignedLabData.addColumn('string', 'Disease Category');
		assignedLabData.addColumn('number', 'New CMRs Generated by ELR');
		assignedLabData.addColumn('number', 'Existing Labs Updated by ELR');
		assignedLabData.addColumn('number', 'Total Assigned');
		discardCaseData.addColumn('string', 'Disease Category');
		discardCaseData.addColumn('number', 'HL7 Messages Discarded');
		graylistData.addColumn('string', 'Disease Category');
		graylistData.addColumn('number', 'HL7 Messages Graylisted');
		blacklistData.addColumn('string', 'Reporting Facility');
		blacklistData.addColumn('number', 'HL7 Messages Blacklisted');
		mqfData.addColumn('string', 'Quality Issue');
		mqfData.addColumn('number', 'Messages with Issue');
		autoFactorData.addColumn('string', 'Method Processed');
		autoFactorData.addColumn('number', 'Number of Messages');
		autoFactorData.addColumn('string', 'Percentage');
		avgNewCmrData.addColumn('string', 'Disease Category');
		avgNewCmrData.addColumn('string', 'Average Time Elapsed');
	<?php
		foreach ($dashboard_summary as $summary_slice => $summary_slice_count) {
			echo "summaryData.addRow(['".$summary_slice."', ".intval($summary_slice_count)."]);".PHP_EOL;
		}
		foreach ($condition_summary as $condition_summary_slice => $condition_summary_slice_data) {
			echo "conditionData.addRow(['".$condition_summary_slice."', ".intval($condition_summary_slice_data['total_received']).", ".intval($condition_summary_slice_data['total_labs'])."]);".PHP_EOL;
		}
		foreach ($dashboard_appendcase as $appendcase_slice => $appendcase_slice_count) {
			echo "assignedLabData.addRow(['".$appendcase_slice."', ".intval($appendcase_slice_count['new_cmr']).", ".intval($appendcase_slice_count['labs_updated']).", ".((intval($appendcase_slice_count['new_cmr']))+(intval($appendcase_slice_count['labs_updated'])))."]);".PHP_EOL;
		}
		foreach ($dashboard_discardcase as $discardcase_slice => $discardcase_slice_count) {
			echo "discardCaseData.addRow(['".$discardcase_slice."', ".intval($discardcase_slice_count)."]);".PHP_EOL;
		}
		foreach ($dashboard_graylist_summary as $graylist_summary_slice => $graylist_summary_slice_count) {
			echo "graylistData.addRow(['".$graylist_summary_slice."', ".intval($graylist_summary_slice_count)."]);".PHP_EOL;
		}
		foreach ($dashboard_blacklist_summary as $blacklist_summary_slice => $blacklist_summary_slice_count) {
			echo "blacklistData.addRow(['".$blacklist_summary_slice."', ".intval($blacklist_summary_slice_count)."]);".PHP_EOL;
		}
		foreach ($dashboard_avgnewcmr as $avg_slice => $avg_time) {
			echo "avgNewCmrData.addRow(['".$avg_slice."', '".$avg_time."']);".PHP_EOL;
		}
		foreach ($dashboard_mqf as $mqf_slice => $mqf_count) {
			echo "mqfData.addRow(['".$mqf_slice."', ".intval($mqf_count)."]);".PHP_EOL;
		}
		foreach ($dashboard_automation_factor2 as $auto_factor_slice => $auto_factor_slice_data) {
			echo "autoFactorData.addRow(['".$auto_factor_slice."', ".intval($auto_factor_slice_data['count']).", '".$auto_factor_slice_data['percent']."']);".PHP_EOL;
		}
	?>
		var summaryOptions = {
			fontName: 'Open Sans',
			fontSize: 10,
			legend: { position: 'top', maxLines: 5 }, 
			tooltip: { showColorCode: true }, 
			sort: 'disable'
		};
		var conditionOptions = {
			fontName: 'Open Sans',
			fontSize: 10,
			legend: { position: 'top', maxLines: 5 }, 
			tooltip: { showColorCode: true }, 
			sortColumn: 1, 
			sortAscending: false
		};
		var assignedOptions = {
			fontName: 'Open Sans',
			fontSize: 10,
			legend: { position: 'top', maxLines: 5 }, 
			tooltip: { showColorCode: true }, 
			sort: 'disable'
		};
		var discardCaseOptions = {
			fontName: 'Open Sans',
			fontSize: 10,
			legend: { position: 'top', maxLines: 5 }, 
			tooltip: { showColorCode: true }, 
			sort: 'disable'
		};
		var blacklistSummaryOptions = {
			fontName: 'Open Sans',
			fontSize: 10,
			legend: { position: 'top', maxLines: 5 }, 
			tooltip: { showColorCode: true }, 
			sort: 'disable'
		};
		var graylistSummaryOptions = {
			fontName: 'Open Sans',
			fontSize: 10,
			legend: { position: 'top', maxLines: 5 }, 
			tooltip: { showColorCode: true }, 
			sort: 'disable'
		};
		var mqfOptions = {
			fontName: 'Open Sans',
			fontSize: 10,
			legend: { position: 'top', maxLines: 5 }, 
			tooltip: { showColorCode: true }, 
			sort: 'disable'
		};
		var autoFactorOptions = {
			fontName: 'Open Sans',
			fontSize: 10,
			legend: { position: 'top', maxLines: 5 }, 
			tooltip: { showColorCode: true }, 
			sort: 'disable'
		};
		var avgNewCmrOptions = {
			fontName: 'Open Sans',
			fontSize: 12,
			showRowNumber: false,
			sort: 'disable'
		};
		
		var summaryChart = new google.visualization.Table(document.getElementById('summary_chart'));
		var conditionChart = new google.visualization.Table(document.getElementById('condition_chart'));
		var assignedChart = new google.visualization.Table(document.getElementById('assigned_chart'));
		var discardCaseChart = new google.visualization.Table(document.getElementById('discardcase_chart'));
		var blacklistSummaryChart = new google.visualization.Table(document.getElementById('blacklist_summary_chart'));
		var graylistSummaryChart = new google.visualization.Table(document.getElementById('graylist_summary_chart'));
		var mqfChart = new google.visualization.Table(document.getElementById('mqf_chart'));
		var autoFactorChart = new google.visualization.Table(document.getElementById('autofactor_chart'));
		var avgNewChart = new google.visualization.Table(document.getElementById('avgnew_chart'));
		summaryChart.draw(summaryData, summaryOptions);
		conditionChart.draw(conditionData, conditionOptions);
		assignedChart.draw(assignedLabData, assignedOptions);
		discardCaseChart.draw(discardCaseData, discardCaseOptions);
		blacklistSummaryChart.draw(blacklistData, blacklistSummaryOptions);
		graylistSummaryChart.draw(graylistData, graylistSummaryOptions);
		mqfChart.draw(mqfData, mqfOptions);
		autoFactorChart.draw(autoFactorData, autoFactorOptions);
		avgNewChart.draw(avgNewCmrData, avgNewCmrOptions);
	};
</script>
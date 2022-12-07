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
	
	echo '<strong class="big_strong">Discarded ELR Messages</strong>';
	echo '<br><em>(Messages in EMSA discarded as a duplicate lab; for messages received between '.$_SESSION[EXPORT_SERVERNAME]['dashboard_date_from'].' and '.$_SESSION[EXPORT_SERVERNAME]['dashboard_date_to'].')</em>';
	
	$results_array = array();
	if (!EmsaUtils::emptyTrim($_SESSION[EXPORT_SERVERNAME]['dashboard_lab_filter'])) {
		$lab_sql = 'AND (sm.lab_id IN ('.implode(",", array_map("intval", $_SESSION[EXPORT_SERVERNAME]['dashboard_lab_filter'])).'))';
	} else {
		$lab_sql = '';
	}
	
	$sql = 'SELECT sm.id, sm.event_id, l.ui_name AS lab_name, sm.loinc_code, sm.disease, s.name AS queue_name, sm.created_at, sm.assigned_date, 
				(xpath(\'//labs/organism/text()\', sm.master_xml::xml))[1] as organism, 
				(xpath(\'//labs/test_result/text()\', sm.master_xml::xml))[1] as test_result 
			FROM '.$emsaDbSchemaPrefix.'system_messages sm 
			INNER JOIN '.$emsaDbSchemaPrefix.'structure_labs l ON (sm.lab_id = l.id)
			INNER JOIN '.$emsaDbSchemaPrefix.'system_statuses s ON (sm.final_status = s.id)
			WHERE sm.id IN (
				SELECT DISTINCT(sma.system_message_id) FROM '.$emsaDbSchemaPrefix.'system_messages_audits sma 
				WHERE message_action_id = 7
			)
			'.$lab_sql.' 
			AND sm.created_at::date >= \''.$_SESSION[EXPORT_SERVERNAME]['dashboard_date_from'].'\' 
			AND sm.created_at::date <= \''.$_SESSION[EXPORT_SERVERNAME]['dashboard_date_to'].'\' 
            AND sm.vocab_app_id = 2
			ORDER BY sm.lab_id, sm.created_at, sm.loinc_code;';

	$rs = @pg_query($host_pa, $sql);
	if ($rs !== false) {
		while ($row = @pg_fetch_object($rs)) {
			//print_r($row);
			$results_array[] = array(
				'Message ID'	=> intval($row->id), 
				'NEDSS ID'		=> intval($row->event_id), 
				'Lab'			=> trim($row->lab_name), 
				'Master LOINC'	=> trim($row->loinc_code), 
				'Condition'		=> trim($row->disease), 
				'Organism'		=> trim($row->organism), 
				'Test Result'	=> trim($row->test_result), 
				'Date Created'	=> trim(date("Y-m-d H:i:s A", strtotime($row->created_at))), 
				'Date Assigned'	=> ((strlen(trim($row->assigned_date)) > 0) ? trim(date("Y-m-d H:i:s A", strtotime($row->assigned_date))) : '--')
			);
		}
		@pg_free_result($rs);
	}
	
?>

<div id="total_container" style="font-family: 'Open Sans', Arial, sans-serif; margin-top: 15px; font-size: 11pt; font-weight: 600;"></div>
<div id="summary_chart" style="margin-top: 15px; height: 100%;"></div>

<script type='text/javascript'>
	function drawChart() {
		var summaryData = new google.visualization.DataTable();
		summaryData.addColumn('string', 'Message ID');
		summaryData.addColumn('string', 'NEDSS Event');
		summaryData.addColumn('string', 'Lab');
		summaryData.addColumn('string', 'Master LOINC');
		summaryData.addColumn('string', 'Condition [Organism]');
		summaryData.addColumn('string', 'Test Result');
		summaryData.addColumn('string', 'Date Reported');
		summaryData.addColumn('string', 'Date Discarded');
	<?php
		foreach ($results_array as $user_key => $user_stats) {
			echo "summaryData.addRow(['<a title=\"Click to view message in EMSA\" href=\"".MAIN_URL."/?selected_page=6&submenu=6&f[showdeleted][]=1&focus=".intval($user_stats['Message ID'])."\" target=\"_blank\">".intval($user_stats['Message ID'])."</a>'
				, '<a title=\"Click to view event in Surveillance System\" href=\"".MAIN_URL."/emsa/nedss_link.php?event_id=".intval($user_stats['NEDSS ID'])."\" target=\"_blank\">".intval($user_stats['NEDSS ID'])."</a>'
				, '".htmlentities($user_stats['Lab'], ENT_QUOTES, 'UTF-8')."'
				, '".htmlentities($user_stats['Master LOINC'], ENT_QUOTES, 'UTF-8')."'
				, '".htmlentities($user_stats['Condition'], ENT_QUOTES, 'UTF-8')." [".htmlentities($user_stats['Organism'], ENT_QUOTES, 'UTF-8')."]'
				, '".htmlentities($user_stats['Test Result'], ENT_QUOTES, 'UTF-8')."'
				, '".htmlentities($user_stats['Date Created'], ENT_QUOTES, 'UTF-8')."'
				, '".htmlentities($user_stats['Date Assigned'], ENT_QUOTES, 'UTF-8')."'
			]);".PHP_EOL;
		}
	?>
		var summaryOptions = {
			fontName: 'Open Sans',
			fontSize: 10,
			legend: { position: 'top', maxLines: 5 }, 
			tooltip: { showColorCode: true }, 
			allowHtml: true
		};
		
		var summaryChart = new google.visualization.Table(document.getElementById('summary_chart'));
		summaryChart.draw(summaryData, summaryOptions);
		$("#total_container").text('Total Discarded Messages: '+summaryData.getNumberOfRows());
	};
</script>
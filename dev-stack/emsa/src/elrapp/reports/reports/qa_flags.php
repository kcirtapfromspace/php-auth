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

echo '<strong class="big_strong">QA Flag Statistics</strong>';
echo '<br><em>(For user activity from ' . $_SESSION[EXPORT_SERVERNAME]['dashboard_date_from'] . ' to ' . $_SESSION[EXPORT_SERVERNAME]['dashboard_date_to'] . ')</em>';

$results_array = array();

$sql = 'SELECT DISTINCT user_id, 
				SUM(CASE WHEN message_action_id IN (23, 24) THEN 1 ELSE 0 END) AS assign_new, 
				SUM(CASE WHEN message_action_id IN (22, 28, 29, 39) THEN 1 ELSE 0 END) AS assign_update, 
				SUM(CASE WHEN message_action_id = 7 THEN 1 ELSE 0 END) AS duplicate, 
				SUM(CASE WHEN message_action_id = 8 THEN 1 ELSE 0 END) AS delete, 
				SUM(CASE WHEN message_action_id = 13 THEN 1 ELSE 0 END) AS exception, 
				SUM(CASE WHEN message_action_id = 25 THEN 1 ELSE 0 END) AS move_manual, 
				SUM(CASE WHEN message_action_id = 27 THEN 1 ELSE 0 END) AS move_whitelist, 
				SUM(CASE WHEN message_action_id = 32 THEN 1 ELSE 0 END) AS set_flag, 
				SUM(CASE WHEN message_action_id = 33 THEN 1 ELSE 0 END) AS clear_flag
			FROM elr.system_messages_audits 
			WHERE user_id <> \'' . EPITRAX_AUTH_ELR_UID . '\'
			AND message_action_id IN (7, 8, 13, 23, 24, 25, 27, 22, 28, 29, 32, 33, 39)
			AND created_at::date >= \'' . $_SESSION[EXPORT_SERVERNAME]['dashboard_date_from'] . '\'
			AND created_at::date <= \'' . $_SESSION[EXPORT_SERVERNAME]['dashboard_date_to'] . '\'
			GROUP BY user_id 
			ORDER BY user_id;';


$rs = @pg_query($host_pa, $sql);
if ($rs !== false) {
    while ($row = @pg_fetch_object($rs)) {
        //print_r($row);
        $results_array[\Udoh\Emsa\Utils\AppClientUtils::userFullNameByUserId($authClient, $row->user_id)] = array(
            'New CMRs' => intval($row->assign_new),
            'Cases Updated' => intval($row->assign_update),
            'Duplicate Messages' => intval($row->duplicate),
            'Messages Deleted' => intval($row->delete),
            'Exceptions' => intval($row->exception),
            'Messages Moved' => intval($row->move_manual),
            'Messages Graylisted' => intval($row->move_whitelist),
            'QA Flags Set' => intval($row->set_flag),
            'QA Flags Cleared' => intval($row->clear_flag)
        );
    }
    @pg_free_result($rs);
}
?>

<div id="summary_chart" style="margin-top: 15px; height: 100%;"></div>

<script type='text/javascript'>
    function drawChart() {
        var summaryData = new google.visualization.DataTable();
        summaryData.addColumn('string', 'User');
        summaryData.addColumn('number', 'New CMRs');
        summaryData.addColumn('number', 'Cases Updated');
        summaryData.addColumn('number', 'Duplicate Messages');
        summaryData.addColumn('number', 'Messages Deleted');
        summaryData.addColumn('number', 'Exceptions');
        summaryData.addColumn('number', 'Messages Moved');
        summaryData.addColumn('number', 'Messages Graylisted');
        summaryData.addColumn('number', 'QA Flags Set');
        summaryData.addColumn('number', 'QA Flags Cleared');
<?php
foreach ($results_array as $user => $user_stats) {
    echo "summaryData.addRow(['" . $user . "', " . intval($user_stats['New CMRs']) . ", " . intval($user_stats['Cases Updated']) . ", " . intval($user_stats['Duplicate Messages']) . ", " . intval($user_stats['Messages Deleted']) . ", " . intval($user_stats['Exceptions']) . ", " . intval($user_stats['Messages Moved']) . ", " . intval($user_stats['Messages Graylisted']) . ", " . intval($user_stats['QA Flags Set']) . ", " . intval($user_stats['QA Flags Cleared']) . "]);" . PHP_EOL;
}
?>
        var summaryOptions = {
            fontName: 'Open Sans',
            fontSize: 10,
            legend: {position: 'top', maxLines: 5},
            tooltip: {showColorCode: true},
            sortColumn: 0
        };

        var summaryChart = new google.visualization.Table(document.getElementById('summary_chart'));
        summaryChart.draw(summaryData, summaryOptions);
    }
    ;
</script>
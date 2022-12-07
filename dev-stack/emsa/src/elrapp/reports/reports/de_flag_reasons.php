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

echo '<strong class="big_strong">&ldquo;Data Entry Error&rdquo; QA Flag Types</strong>';
echo '<br><em>(For messages received ' . $_SESSION[EXPORT_SERVERNAME]['dashboard_date_from'] . ' to ' . $_SESSION[EXPORT_SERVERNAME]['dashboard_date_to'] . ')</em>';

$results_array = array();

if (!EmsaUtils::emptyTrim($_SESSION[EXPORT_SERVERNAME]['dashboard_lab_filter'])) {
    $lab_sql = 'AND (sm.lab_id IN (' . implode(",", array_map("intval", $_SESSION[EXPORT_SERVERNAME]['dashboard_lab_filter'])) . '))';
} else {
    $lab_sql = '';
}

$sql = 'SELECT DISTINCT fc.info AS info, COUNT(fc.info) AS counter 
			FROM ' . $emsaDbSchemaPrefix . 'system_message_flag_comments fc 
			WHERE fc.system_message_id IN ( 
				SELECT sm.id FROM ' . $emsaDbSchemaPrefix . 'system_messages sm 
				WHERE sm.message_flags & ' . EMSA_FLAG_DE_ERROR . ' != 0 
				' . $lab_sql . ' 
				AND sm.created_at::date >= \'' . $_SESSION[EXPORT_SERVERNAME]['dashboard_date_from'] . '\' 
				AND sm.created_at::date <= \'' . $_SESSION[EXPORT_SERVERNAME]['dashboard_date_to'] . '\') 
                AND sm.vocab_app_id = 2
				AND fc.system_message_flag_id = ' . EMSA_FLAG_DE_ERROR . ' 
			GROUP BY fc.info 
			ORDER BY 2 DESC, 1;';

$rs = @pg_query($host_pa, $sql);
if ($rs !== false) {
    while ($row = @pg_fetch_object($rs)) {
        //print_r($row);
        $results_array[] = array(
            'Number of Occurrences' => intval($row->counter),
            'Type' => trim($row->info)
        );
    }
    @pg_free_result($rs);
}
?>

<div id="summary_chart" style="margin-top: 15px; height: 100%;"></div>

<script type='text/javascript'>
    function drawChart() {
        var summaryData = new google.visualization.DataTable();
        summaryData.addColumn('number', '# of Occurrences');
        summaryData.addColumn('string', 'Type');
<?php
foreach ($results_array as $user_key => $user_stats) {
    if (intval($user_stats['Number of Occurrences']) > 0) {
        echo "summaryData.addRow([" . intval($user_stats['Number of Occurrences']) . ", '" . htmlentities($user_stats['Type'], ENT_QUOTES, 'UTF-8') . "']);" . PHP_EOL;
    }
}
?>
        var summaryOptions = {
            fontName: 'Open Sans',
            fontSize: 10,
            legend: {position: 'top', maxLines: 5},
            tooltip: {showColorCode: true},
            sortColumn: 0,
            sortAscending: false
        };

        var summaryChart = new google.visualization.Table(document.getElementById('summary_chart'));
        summaryChart.draw(summaryData, summaryOptions);
    }
    ;
</script>
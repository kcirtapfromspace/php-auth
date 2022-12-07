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
		$("#addnew_form").show();
		
		$("#new_savelab").button({
			icon: "ui-icon-elrsearch"
		});
		
		$(".view_type_container").on("click", function() {
			$("input[type=radio]", this).first().prop("checked", true);
		});
		
		$(".date-range").datepicker();
		
	});
</script>

<style type="text/css">
	input[type="radio"] { min-width: 0 !important; }
	#new_lab_form div { display: inline-block; margin: 7px; padding: 10px; }
	#labResults td { border-bottom-width: 2px; border-bottom-color: darkorange; }
	.audit_log td { border-bottom-width: 1px !important; border-bottom-color: lightgray !important; }
	.view_type_container, .view_type_container label { font-family: 'Open Sans', Arial, Helvetica, sans-serif !important; font-weight: 600; }
</style>

<?php

	if (isset($_GET['text_search']) && strlen(trim($_GET['text_search'])) > 0) {
		$clean['keywords'] = filter_var(trim($_GET['text_search']), FILTER_SANITIZE_STRING);
	}
	
	if (isset($_GET['message_id']) && filter_var(trim($_GET['message_id']), FILTER_VALIDATE_INT)) {
		$clean['message_id'] = filter_var(trim($_GET['message_id']), FILTER_SANITIZE_NUMBER_INT);
	}
	
	if (isset($_GET['view_type']) && filter_var($_GET['view_type'], FILTER_VALIDATE_INT) && in_array(intval(trim($_GET['view_type'])), array(1, 2, 3))) {
		$clean['view_type'] = filter_var(trim($_GET['view_type']), FILTER_SANITIZE_NUMBER_INT);
	}
	
	if (isset($_GET['date_from']) && (strlen(trim($_GET['date_from'])) > 0)) {
		$clean['date_from'] = filter_var(trim($_GET['date_from']), FILTER_SANITIZE_STRING);
	}

	if (isset($_GET['date_to']) && (strlen(trim($_GET['date_to'])) > 0)) {
		$clean['date_to'] = filter_var(trim($_GET['date_to']), FILTER_SANITIZE_STRING);
	}

?>

<h1 class="elrhdg"><span class="ui-icon ui-icon-header ui-icon-emsalog"></span>Vocabulary Audit Log Viewer</h1>

<div id="addnew_form" class="addnew_lab ui-widget ui-widget-content ui-corner-all">
	<div style="clear: both;"></div>
	<form id="new_lab_form" method="GET">
        <fieldset>
            <legend class="emsa_form_heading">View Vocabulary Audit Logs By:</legend>
            <div class="view_type_container ui-widget-content ui-corner-all">
                <input type="radio" name="view_type" id="view_type_recent" value="3"<?php echo ((isset($clean['view_type']) && ($clean['view_type'] == 3)) ? " checked" : ""); ?>/>
                <label class="vocab_search_form2" for="view_type_recent">View Most-Recent Changes</label>
            </div>
            <div class="view_type_container ui-widget-content ui-corner-all">
                <input type="radio" name="view_type" id="view_type_date" value="2"<?php echo ((isset($clean['view_type']) && ($clean['view_type'] == 2)) ? " checked" : ""); ?>/>
                <label class="vocab_search_form2" for="view_type_date">View by Date Range</label>
                <label class="vocab_search_form2" for="date_from">From Date:</label>
                <input class="date-range ui-corner-all" type="text" name="date_from" id="date_from" value="<?php echo ((isset($clean['date_from'])) ? $clean['date_from'] : ""); ?>" placeholder="Any Time">

                <label class="vocab_search_form2" for="date_to">To Date:</label>
                <input class="date-range ui-corner-all" type="text" name="date_to" id="date_to" value="<?php echo ((isset($clean['date_to'])) ? $clean['date_to'] : ""); ?>" placeholder="Present"></div>
            <div class="view_type_container ui-widget-content ui-corner-all"><label class="vocab_search_form2" for="text_search">Keyword Search:</label> <input class="ui-corner-all" type="text" name="text_search" id="text_search" value="<?php echo ((isset($clean['keywords'])) ? $clean['keywords'] : ''); ?>"></div>
        </fieldset>
		<hr>
		<input type="hidden" name="selected_page" id="selected_page" value="<?php echo intval($navSelectedPage); ?>">
		<input type="hidden" name="submenu" id="submenu" value="<?php echo intval($navSubmenu); ?>">
		<input type="hidden" name="cat" id="cat" value="<?php echo intval($navCat); ?>">
		<input type="hidden" name="subcat" id="subcat" value="<?php echo intval($navSubcat); ?>">
		<br><button type="submit" name="new_savelab" id="new_savelab">View Selected Audit Logs</button>
	</form>
</div>

<?php

	// check for view type
	if (isset($clean['view_type'])) {
		// valid 'view_type' flag
		
?>
<div class="lab_results_container ui-widget ui-corner-all">
	<div class="h3">Vocabulary Audits...</div>
	<table id="labResults">
		<thead>
			<tr>
				<th>Date/Time</th>
				<th>User</th>
				<th>Action</th>
				<th>Old Values</th>
				<th>New Values</th>
			</tr>
		</thead>
		<tbody>
<?php
	
		$va = new VocabAudit($adminDbConn, $authClient);

		if ($clean['view_type'] == 1) {
			// view by message id
			$outer_qry = "SELECT DISTINCT a.system_message_id, m.original_message_id, m.deleted, s.name, s.id AS queue_id, o.created_at 
				FROM ".$emsaDbSchemaPrefix."system_messages_audits a 
				INNER JOIN ".$emsaDbSchemaPrefix."system_messages m ON (a.system_message_id = m.id) 
				INNER JOIN ".$emsaDbSchemaPrefix."system_original_messages o ON (m.original_message_id = o.id)
				LEFT JOIN ".$emsaDbSchemaPrefix."system_statuses s ON (m.final_status = s.id) 
				WHERE (a.system_message_id = ".$clean['message_id'].") OR (m.original_message_id = ".$clean['message_id'].") 
				GROUP BY a.system_message_id, m.original_message_id, m.deleted, s.name, o.created_at, s.id
				ORDER BY o.created_at DESC;";
			
		} elseif ($clean['view_type'] == 2) {
			// view by date range
			unset($date_range_clause);
			unset($keyword_clause);
			if (isset($clean['date_from']) && isset($clean['date_to'])) {
				// between
				$date_range_clause = "WHERE va.event_time BETWEEN '" . date(DATE_RFC3339, strtotime($clean['date_from'])) . "' AND '" . date(DATE_RFC3339, strtotime($clean['date_to'])) . "' ";
			} elseif (isset($clean['date_from'])) {
				// start to infinity
				$date_range_clause = "WHERE va.event_time > '" . date(DATE_RFC3339, strtotime($clean['date_from'])) . "' ";
			} elseif (isset($clean['date_to'])) {
				// infinity to end
				$date_range_clause = "WHERE va.event_time < '" . date(DATE_RFC3339, strtotime($clean['date_to'])) . "' ";
			} else {
				$date_range_clause = "";
			}
			if (isset($clean['keywords'])) {
				if (isset($clean['date_from']) || isset($clean['date_to'])) {
					$keyword_clause = ' AND ((va.old_vals ILIKE \'%'.pg_escape_string($clean['keywords']).'%\') OR (va.new_vals ILIKE \'%'.pg_escape_string($clean['keywords']).'%\')) ';
				} else {
					$keyword_clause = 'WHERE ((va.old_vals ILIKE \'%'.pg_escape_string($clean['keywords']).'%\') OR (va.new_vals ILIKE \'%'.pg_escape_string($clean['keywords']).'%\')) ';
				}
			} else {
				$keyword_clause = '';
			}
			$outer_qry = 'SELECT * 
				FROM '.$emsaDbSchemaPrefix.'vocab_audits va 
				'.$date_range_clause.$keyword_clause.'
				ORDER BY va.event_time DESC;';
			
		} else {
			// view by most-recent
			if (isset($clean['keywords'])) {
				$keyword_clause = 'WHERE ((va.old_vals ILIKE \'%'.pg_escape_string($clean['keywords']).'%\') OR (va.new_vals ILIKE \'%'.pg_escape_string($clean['keywords']).'%\')) ';
			} else {
				$keyword_clause = '';
			}
			$outer_qry = 'SELECT * 
				FROM '.$emsaDbSchemaPrefix.'vocab_audits va 
				'.$keyword_clause.' 
				ORDER BY va.event_time DESC 
				LIMIT 50;';
			
		}
		
		$outer_rs = @pg_query($host_pa, $outer_qry);
		if ($outer_rs) {
			if (@pg_num_rows($outer_rs) > 0) {
				// draw results
				while ($row = @pg_fetch_object($outer_rs)) {
					unset($this_action);
					switch (intval($row->action)) {
						case VocabAudit::ACTION_ADD:
							$this_action = 'Added new';
							break;
						case VocabAudit::ACTION_EDIT:
							$this_action = 'Edited';
							break;
						case VocabAudit::ACTION_DELETE:
							$this_action = 'Deleted';
							break;
					}
					echo '<tr>
							<td>'.date("m/d/Y [g:ia]", strtotime($row->event_time)).'</td>
							<td>' . \Udoh\Emsa\Utils\AppClientUtils::userFullNameByUserId($authClient, $row->user_id) . '</td>
							<td><strong>'.$this_action.'</strong> '.$va->tableName(intval($row->tbl)).'</td>
							<td>'.$va->displayJsonVals(trim($row->old_vals)).'</td>
							<td>'.$va->displayJsonVals(trim($row->new_vals)).'</td>
						</tr>';
				}
			} else {
				// draw 'no results'
				echo "<tr><td colspan=\"5\"><em>No actions found</em></td></tr>";
				
			}
		} else {
			\Udoh\Emsa\Utils\DisplayUtils::drawError("Could not connect to Vocabulary Audit Log database.");
		
		}
		@pg_free_result($outer_rs);

?>

		</tbody>
	</table>
	
<?php

	}
	
?>
	
</div>
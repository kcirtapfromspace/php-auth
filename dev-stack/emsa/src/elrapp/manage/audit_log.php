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
use Udoh\Emsa\Utils\AppClientUtils;
use Udoh\Emsa\Utils\DisplayUtils;

if (!class_exists('\Udoh\Emsa\Auth\Authenticator') || !Authenticator::userHasPermission(Authenticator::URIGHTS_ADMIN)) {
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
	
	if (isset($_GET['original_message_id']) && filter_var(trim($_GET['original_message_id']), FILTER_VALIDATE_INT)) {
		$clean['original_message_id'] = filter_var(trim($_GET['original_message_id']), FILTER_SANITIZE_NUMBER_INT);
	}
	
	if (isset($_GET['event_id']) && filter_var(trim($_GET['event_id']), FILTER_VALIDATE_INT)) {
		$clean['event_id'] = filter_var(trim($_GET['event_id']), FILTER_SANITIZE_NUMBER_INT);
	}
	
	if (isset($_GET['view_type']) && filter_var($_GET['view_type'], FILTER_VALIDATE_INT) && in_array(intval(trim($_GET['view_type'])), array(1, 2, 3, 4, 5))) {
		$clean['view_type'] = filter_var(trim($_GET['view_type']), FILTER_SANITIZE_NUMBER_INT);
	}
	
	if (isset($_GET['date_from']) && (strlen(trim($_GET['date_from'])) > 0)) {
		$clean['date_from'] = filter_var(trim($_GET['date_from']), FILTER_SANITIZE_STRING);
	}

	if (isset($_GET['date_to']) && (strlen(trim($_GET['date_to'])) > 0)) {
		$clean['date_to'] = filter_var(trim($_GET['date_to']), FILTER_SANITIZE_STRING);
	}

?>

<h1 class="elrhdg"><span class="ui-icon ui-icon-header ui-icon-emsalog"></span>Audit Log Viewer</h1>

<div id="addnew_form" class="addnew_lab ui-widget ui-widget-content ui-corner-all">
	<form id="new_lab_form" method="GET">
        <fieldset>
            <legend class="emsa_form_heading">View Audit Logs By:</legend>
            <div class="view_type_container ui-widget-content ui-corner-all"><label class="vocab_search_form2" for="text_search">Original Message Keyword Search:</label> <input class="ui-corner-all" type="text" name="text_search" id="text_search" value="<?php echo ((isset($clean['keywords'])) ? $clean['keywords'] : ''); ?>"></div>
            <hr>
            <div class="view_type_container ui-widget-content ui-corner-all"><input type="radio" name="view_type" id="view_type_recent" value="3"<?php echo ((isset($clean['view_type']) && ($clean['view_type'] == 3)) ? " checked" : ""); ?>/><label class="vocab_search_form2" for="view_type_recent">View Most-Recent Messages</label></div>
            <div class="view_type_container ui-widget-content ui-corner-all">
                <input type="radio" name="view_type" id="view_type_date" value="2"<?php echo ((isset($clean['view_type']) && ($clean['view_type'] == 2)) ? " checked" : ""); ?>/>
                <label class="vocab_search_form2" for="view_type_date">View by Date Range:</label>

                <label class="vocab_search_form2" for="date_from">From Date:</label>
                <input class="date-range ui-corner-all" type="text" name="date_from" id="date_from" value="<?php echo ((isset($clean['date_from'])) ? $clean['date_from'] : ""); ?>" placeholder="Any Time">

                <label class="vocab_search_form2" for="date_to">To Date:</label>
                <input class="date-range ui-corner-all" type="text" name="date_to" id="date_to" value="<?php echo ((isset($clean['date_to'])) ? $clean['date_to'] : ""); ?>" placeholder="Present">
            </div>
            <hr>
            <div class="view_type_container ui-widget-content ui-corner-all">
                <input type="radio" name="view_type" id="view_type_original_id" value="4"<?php echo ((isset($clean['view_type']) && ($clean['view_type'] == 4)) ? " checked" : ""); ?>/>
                <label class="vocab_search_form2" for="view_type_original_id">View by <u>Original</u> Message ID#:</label>
                <label class="sr-only" for="original_message_id">Original Message ID</label>
                <input class="ui-corner-all" type="text" name="original_message_id" id="original_message_id" value="<?php echo ((isset($clean['original_message_id'])) ? $clean['original_message_id'] : ""); ?>">
            </div>
            <div class="view_type_container ui-widget-content ui-corner-all">
                <input type="radio" name="view_type" id="view_type_id" value="1"<?php echo ((isset($clean['view_type']) && ($clean['view_type'] == 1)) ? " checked" : ""); ?>/>
                <label class="vocab_search_form2" for="view_type_id">View by <u>System</u> Message ID#:</label>
                <label class="sr-only" for="message_id">System Message ID</label>
                <input class="ui-corner-all" type="text" name="message_id" id="message_id" value="<?php echo ((isset($clean['message_id'])) ? $clean['message_id'] : ""); ?>">
            </div>
            <div class="view_type_container ui-widget-content ui-corner-all">
                <input type="radio" name="view_type" id="view_type_event_id" value="5"<?php echo ((isset($clean['view_type']) && ($clean['view_type'] == 5)) ? " checked" : ""); ?>/>
                <label class="vocab_search_form2" for="view_type_event_id">View by NEDSS Event ID#:</label>
                <label class="sr-only" for="event_id">NEDSS Event ID</label>
                <input class="ui-corner-all" type="text" name="event_id" id="event_id" value="<?php echo ((isset($clean['event_id'])) ? $clean['event_id'] : ""); ?>"></div>
            <hr>
        </fieldset>
		<input type="hidden" name="selected_page" id="selected_page" value="<?php echo intval($navSelectedPage); ?>">
		<input type="hidden" name="submenu" id="submenu" value="<?php echo intval($navSubmenu); ?>">
		<input type="hidden" name="cat" id="cat" value="<?php echo intval($navCat); ?>">
		<br><button type="submit" name="new_savelab" id="new_savelab">View Selected Audit Logs</button>
	</form>
</div>

<?php

	// check for view type
	if (isset($clean['view_type'])) {
		// valid 'view_type' flag
		
?>
<div class="lab_results_container ui-widget ui-corner-all">
	<div class="h3">Message Audits...</div>
	<table id="labResults">
		<thead>
			<tr>
				<th style="width: 25%;">Message Details</th>
				<th style="width: 14%;">Event Date/Time</th>
				<th style="width: 12%;">User</th>
				<th style="width: 8%;">Category</th>
				<th style="width: 35%;">Action</th>
				<th style="width: 6%;">Status</th>
			</tr>
		</thead>
		<tbody>
<?php

		if ($clean['view_type'] == 1) {
			// view by system message id
			$outer_qry = "SELECT DISTINCT a.system_message_id, m.original_message_id, m.deleted, s.name, s.id AS queue_id, o.created_at 
				FROM ".$emsaDbSchemaPrefix."system_messages_audits a 
				INNER JOIN ".$emsaDbSchemaPrefix."system_messages m ON (a.system_message_id = m.id) 
				INNER JOIN ".$emsaDbSchemaPrefix."system_original_messages o ON (m.original_message_id = o.id)
				LEFT JOIN ".$emsaDbSchemaPrefix."system_statuses s ON (m.final_status = s.id) 
				WHERE m.id = ".$clean['message_id']." 
                AND m.vocab_app_id = 2
				GROUP BY a.system_message_id, m.original_message_id, m.deleted, s.name, o.created_at, s.id
				ORDER BY o.created_at DESC;";
			
		} elseif ($clean['view_type'] == 2) {
			// view by date range
			unset($date_range_clause);
			unset($keyword_clause);
			if (isset($clean['date_from']) && isset($clean['date_to'])) {
				// between
				$date_range_clause = "WHERE o.created_at BETWEEN '" . date(DATE_RFC3339, strtotime($clean['date_from'])) . "' AND '" . date(DATE_RFC3339, strtotime($clean['date_to'])) . "' ";
			} elseif (isset($clean['date_from'])) {
				// start to infinity
				$date_range_clause = "WHERE o.created_at > '" . date(DATE_RFC3339, strtotime($clean['date_from'])) . "' ";
			} elseif (isset($clean['date_to'])) {
				// infinity to end
				$date_range_clause = "WHERE o.created_at < '" . date(DATE_RFC3339, strtotime($clean['date_to'])) . "' ";
			} else {
				$date_range_clause = "";
			}
			if (isset($clean['keywords'])) {
				if (isset($clean['date_from']) || isset($clean['date_to'])) {
					$keyword_clause = ' AND o.message ILIKE \'%'.pg_escape_string($clean['keywords']).'%\' AND m.vocab_app_id = 2 ';
				} else {
					$keyword_clause = 'WHERE o.message ILIKE \'%'.pg_escape_string($clean['keywords']).'%\' AND m.vocab_app_id = 2 ';
				}
			} else {
				$keyword_clause = 'WHERE m.vocab_app_id = 2 ';
			}
			$outer_qry = "SELECT DISTINCT a.system_message_id, m.original_message_id, m.deleted, s.name, s.id AS queue_id, o.created_at 
				FROM ".$emsaDbSchemaPrefix."system_messages_audits a 
				INNER JOIN ".$emsaDbSchemaPrefix."system_messages m ON (a.system_message_id = m.id) 
				INNER JOIN ".$emsaDbSchemaPrefix."system_original_messages o ON (m.original_message_id = o.id)
				LEFT JOIN ".$emsaDbSchemaPrefix."system_statuses s ON (m.final_status = s.id) ".$date_range_clause.$keyword_clause."
				GROUP BY a.system_message_id, m.original_message_id, m.deleted, s.name, o.created_at, s.id
				ORDER BY o.created_at DESC;";
			
		} elseif ($clean['view_type'] == 4) {
			// view by original message id
			$outer_qry = "SELECT DISTINCT a.system_message_id, m.original_message_id, m.deleted, s.name, s.id AS queue_id, o.created_at 
				FROM ".$emsaDbSchemaPrefix."system_messages_audits a 
				INNER JOIN ".$emsaDbSchemaPrefix."system_messages m ON (a.system_message_id = m.id) 
				INNER JOIN ".$emsaDbSchemaPrefix."system_original_messages o ON (m.original_message_id = o.id)
				LEFT JOIN ".$emsaDbSchemaPrefix."system_statuses s ON (m.final_status = s.id) 
				WHERE m.original_message_id = ".$clean['original_message_id']." 
                AND m.vocab_app_id = 2
				GROUP BY a.system_message_id, m.original_message_id, m.deleted, s.name, o.created_at, s.id
				ORDER BY o.created_at DESC;";
			
		} elseif ($clean['view_type'] == 5) {
			// view by NEDSS event id
			$outer_qry = "SELECT DISTINCT a.system_message_id, m.original_message_id, m.deleted, s.name, s.id AS queue_id, o.created_at 
				FROM ".$emsaDbSchemaPrefix."system_messages_audits a 
				INNER JOIN ".$emsaDbSchemaPrefix."system_messages m ON (a.system_message_id = m.id) 
				INNER JOIN ".$emsaDbSchemaPrefix."system_original_messages o ON (m.original_message_id = o.id)
				LEFT JOIN ".$emsaDbSchemaPrefix."system_statuses s ON (m.final_status = s.id) 
				WHERE m.event_id = ".$clean['event_id']." 
                AND m.vocab_app_id = 2
				GROUP BY a.system_message_id, m.original_message_id, m.deleted, s.name, o.created_at, s.id
				ORDER BY o.created_at DESC;";
			
		} else {
			// view by most-recent
			if (isset($clean['keywords'])) {
				$keyword_clause = 'WHERE o.message ILIKE \'%'.pg_escape_string($clean['keywords']).'%\' AND m.vocab_app_id = 2 ';
			} else {
				$keyword_clause = 'WHERE m.vocab_app_id = 2 ';
			}
			$outer_qry = "SELECT DISTINCT a.system_message_id, m.original_message_id, m.deleted, s.name, s.id AS queue_id, o.created_at 
				FROM ".$emsaDbSchemaPrefix."system_messages_audits a 
				INNER JOIN ".$emsaDbSchemaPrefix."system_messages m ON (a.system_message_id = m.id) 
				INNER JOIN ".$emsaDbSchemaPrefix."system_original_messages o ON (m.original_message_id = o.id)
				LEFT JOIN ".$emsaDbSchemaPrefix."system_statuses s ON (m.final_status = s.id) ".$keyword_clause." 
				GROUP BY a.system_message_id, m.original_message_id, m.deleted, s.name, o.created_at, s.id
				ORDER BY o.created_at DESC 
				LIMIT 50;";
			
		}
		
		$outer_rs = @pg_query($host_pa, $outer_qry);
		if ($outer_rs) {
			if (pg_num_rows($outer_rs) > 0) {
				// draw results
				while ($outer_row = pg_fetch_object($outer_rs)) {
					$message_link = '?selected_page=6&submenu=6&focus='.intval($outer_row->system_message_id); // view individual msg page
					echo '<tr>';
					echo '<td style="text-align: left; vertical-align: top;"><strong>Original Msg ID [System Msg ID]:</strong><br>'.intval($outer_row->original_message_id).' ['.intval($outer_row->system_message_id).']
						<br><br><strong>Received:</strong><br>'.date("Y-m-d H:i:s", strtotime($outer_row->created_at));
					if (intval($outer_row->deleted) > 0) {
						echo '<br><br><strong>Current Queue:</strong><br><a style="font-weight: 700; text-decoration: line-through;" href="' . MAIN_URL . '/' . $message_link . '" target="_blank">'.htmlspecialchars($emsaHTMLPurifier->purify($outer_row->name)).'</a> (<span class="ui-icon ui-icon-elrdelete" style="display: inline-block; margin-bottom: -5px;"></span> Deleted)</td>';
					} else {
						echo '<br><br><strong>Current Queue:</strong><br><a style="font-weight: 700;" href="' . MAIN_URL . '/' . $message_link . '" target="_blank">'.htmlspecialchars($emsaHTMLPurifier->purify($outer_row->name)).'</a></td>';
					}
					echo '<td colspan="5" style="text-align: left; vertical-align: top;"><table class="audit_log">';
					echo '<tbody>';
					
					// get individual audits for this message
					unset($inner_qry);
					unset($inner_rs);
					unset($inner_row);
					$inner_qry = sprintf("SELECT au.user_id AS user_id, ac.message AS action, au.info AS info, ca.name AS category, au.created_at AS created_at, ss.name AS status 
						FROM %ssystem_messages_audits au 
						JOIN %ssystem_message_actions ac ON (au.message_action_id = ac.id)
						JOIN %ssystem_action_categories ca ON (ac.action_category_id = ca.id)
						JOIN %ssystem_statuses ss ON (au.system_status_id = ss.id)
						WHERE au.system_message_id = %s
						ORDER BY au.created_at DESC;",
					$emsaDbSchemaPrefix, $emsaDbSchemaPrefix, $emsaDbSchemaPrefix, $emsaDbSchemaPrefix, intval($outer_row->system_message_id));
					$inner_rs = @pg_query($host_pa, $inner_qry);
					while($inner_row = pg_fetch_object($inner_rs)) {
						echo "<tr>";
						echo "<td style=\"width: 14%;\">".date("m/d/Y H:i:s", strtotime($inner_row->created_at))."</td>";
						echo "<td style=\"width: 12%;\">" . AppClientUtils::userFullNameByUserId($authClient, trim($inner_row->user_id)) . "</td>";
						echo "<td style=\"width: 8%;\">".htmlspecialchars($emsaHTMLPurifier->purify($inner_row->category))."</td>";
						echo "<td style=\"width: 35%;\">".htmlspecialchars($emsaHTMLPurifier->purify($inner_row->action)).((strlen(trim($inner_row->info)) > 0) ? '<br>Comments: '.htmlspecialchars($emsaHTMLPurifier->purify($inner_row->info)) : '')."</td>";
						echo "<td style=\"width: 6%;\">".htmlspecialchars($emsaHTMLPurifier->purify($inner_row->status))."</td>";
						echo "</tr>";
					}
					@pg_free_result($inner_rs);
					
					echo "</tbody></table></td>";
					echo "</tr>";
				}
			} else {
				// draw 'no results'
				echo "<tr><td colspan=\"6\"><em>No messages found that match your criteria</em></td></tr>";
				
			}
		} else {
			DisplayUtils::drawError("Could not connect to Audit Log database.");
		
		}
		@pg_free_result($outer_rs);

?>

		</tbody>
	</table>
	
<?php

	}
	
?>
	
</div>
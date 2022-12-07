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

use Udoh\Emsa\UI\AccessibleMultiselectListbox;
use Udoh\Emsa\UI\Queue\FilterFactory;

if (!class_exists('\Udoh\Emsa\Auth\Authenticator') || !\Udoh\Emsa\Auth\Authenticator::userHasPermission(\Udoh\Emsa\Auth\Authenticator::URIGHTS_ADMIN)) {
    ob_end_clean();
    header($_SERVER['SERVER_PROTOCOL'] . " 404 Not Found", TRUE, 404);
    exit;
}
	
	define("BLANK_PLACEHOLDER", "(Blank)");
	
	$clean = (object) array(
		'show_results' => false, 
		'date_reported_start' => '', 
		'date_reported_end' => '',
		'reporting_agencies' => array(),
		'reporting_agencies_sql' => array(),
		'conditions' => array(),
		'conditions_sql' => array(),
		'test_results' => array(),
		'test_results_sql' => array()
	);
	
	if (isset($_POST['show_results']) && (intval(trim($_POST['show_results'])) === 1)) {
		$clean->show_results = true;
	}
	
	if (isset($_POST['date_reported_start']) && (strlen(trim($_POST['date_reported_start'])) > 0)) {
		$clean->date_reported_start = filter_var(trim($_POST['date_reported_start']), FILTER_SANITIZE_STRING);
	}

	if (isset($_POST['date_reported_end']) && (strlen(trim($_POST['date_reported_end'])) > 0)) {
		$clean->date_reported_end = filter_var(trim($_POST['date_reported_end']), FILTER_SANITIZE_STRING);
	}
	
	if (isset($_POST['reporting_agencies']) && is_array($_POST['reporting_agencies']) && (count($_POST['reporting_agencies']) > 0)) {
		foreach ($_POST['reporting_agencies'] as $selectedReporter) {
			$clean->reporting_agencies[] = filter_var(trim($selectedReporter), FILTER_SANITIZE_STRING);
			if ($selectedReporter != BLANK_PLACEHOLDER) {
				$clean->reporting_agencies_sql[] = $adminDbConn->quote(filter_var(trim($selectedReporter), FILTER_SANITIZE_STRING));
			}
		}
	}
	
	if (isset($_POST['conditions']) && is_array($_POST['conditions']) && (count($_POST['conditions']) > 0)) {
		foreach ($_POST['conditions'] as $selectedCondition) {
			$clean->conditions[] = filter_var(trim($selectedCondition), FILTER_SANITIZE_STRING);
			if ($selectedCondition != BLANK_PLACEHOLDER) {
				$clean->conditions_sql[] = $adminDbConn->quote(filter_var(trim($selectedCondition), FILTER_SANITIZE_STRING));
			}
		}
	}
	
	if (isset($_POST['test_results']) && is_array($_POST['test_results']) && (count($_POST['test_results']) > 0)) {
		foreach ($_POST['test_results'] as $selectedTestResult) {
			$clean->test_results[] = filter_var(trim($selectedTestResult), FILTER_SANITIZE_STRING);
			if ($selectedTestResult != BLANK_PLACEHOLDER) {
				$clean->test_results_sql[] = $adminDbConn->quote(filter_var(trim($selectedTestResult), FILTER_SANITIZE_STRING));
			}
		}
	}
	
	$whereClause = '';
	$filterCount = 0;
	
	if (!\EmsaUtils::emptyTrim($clean->date_reported_start)) {
		$dateTimeReportedStart = \Udoh\Emsa\Utils\DateTimeUtils::createMixed($clean->date_reported_start);
		if ($filterCount  === 0) {
			$whereClause = 'WHERE ';
		} else {
			$whereClause .= PHP_EOL.'AND ';
		}
		$whereClause .= "(report_date::date >= '".$dateTimeReportedStart->format("Y-m-d")."')";
		$filterCount++;
	}
	
	if (!\EmsaUtils::emptyTrim($clean->date_reported_end)) {
		$dateTimeReportedEnd = \Udoh\Emsa\Utils\DateTimeUtils::createMixed($clean->date_reported_end);
		if ($filterCount  === 0) {
			$whereClause = 'WHERE ';
		} else {
			$whereClause .= PHP_EOL.'AND ';
		}
		$whereClause .= "(report_date::date <= '".$dateTimeReportedEnd->format("Y-m-d")."')";
		$filterCount++;
	}
	
	if (count($clean->reporting_agencies) > 0) {
		if ($filterCount  === 0) {
			$whereClause = 'WHERE ';
		} else {
			$whereClause .= PHP_EOL.'AND ';
		}
		$reportingAgencyImploded = implode(",", $clean->reporting_agencies_sql);
		if ((count($clean->reporting_agencies_sql) > 0) && in_array(BLANK_PLACEHOLDER, $clean->reporting_agencies)) {
			$whereClause .= "((report_agency IN (".$reportingAgencyImploded.")) OR (report_agency IS NULL))";
		} elseif ((count($clean->reporting_agencies_sql) <= 0) && in_array(BLANK_PLACEHOLDER, $clean->reporting_agencies)) {
			$whereClause .= "(report_agency IS NULL)";
		} else {
			$whereClause .= "(report_agency IN (".$reportingAgencyImploded."))";
		}
		$filterCount++;
	}
	
	if (count($clean->conditions) > 0) {
		if ($filterCount  === 0) {
			$whereClause = 'WHERE ';
		} else {
			$whereClause .= PHP_EOL.'AND ';
		}
		$conditionsImploded = implode(",", $clean->conditions_sql);
		if ((count($clean->conditions_sql) > 0) && in_array(BLANK_PLACEHOLDER, $clean->conditions)) {
			$whereClause .= "((disease IN (".$conditionsImploded.")) OR (disease IS NULL))";
		} elseif ((count($clean->conditions_sql) <= 0) && in_array(BLANK_PLACEHOLDER, $clean->conditions)) {
			$whereClause .= "(disease IS NULL)";
		} else {
			$whereClause .= "(disease IN (".$conditionsImploded."))";
		}
		$filterCount++;
	}
	
	if (count($clean->test_results) > 0) {
		if ($filterCount  === 0) {
			$whereClause = 'WHERE ';
		} else {
			$whereClause .= PHP_EOL.'AND ';
		}
		$testResultsImploded = implode(",", $clean->test_results_sql);
		if ((count($clean->test_results_sql) > 0) && in_array(BLANK_PLACEHOLDER, $clean->test_results)) {
			$whereClause .= "((test_result IN (".$testResultsImploded.")) OR (test_result IS NULL))";
		} elseif ((count($clean->test_results_sql) <= 0) && in_array(BLANK_PLACEHOLDER, $clean->test_results)) {
			$whereClause .= "(test_result IS NULL)";
		} else {
			$whereClause .= "(test_result IN (".$testResultsImploded."))";
		}
		$filterCount++;
	}
	
?>
<script>
	$(function() {
		$(".tooltip").tooltip();
		
		$("#addnew_form").show();
		
		$("#count_submit").button();
		
		$(".date-range").datepicker();
	});
</script>
<style type="text/css">
	.ui-dialog-content label {
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
	
	.vocab_filter_container {
		margin: 0px;
	}
	
	.vocab_filter_checklist {
		min-width: 15em;
		height: 10em;
	}
	
	.vocab_filter {
		padding: 10px;
	}
	
	.vocab_search_form2 {
		margin-left: 0px !important;
	}
	
	.pseudo_select_label {
		white-space: nowrap;
	}
</style>

<h1 class="elrhdg"><span class="ui-icon ui-icon-header ui-icon-emsareporting"></span>Message Receipt Counter</h1>

<?php
	
	if ($clean->show_results) {
		if ($filterCount > 0) {
			try {
				$countSql = "SELECT count(id)
					FROM master_xml_flat
					".$whereClause.";";
				$rs = $adminDbConn->query($countSql);
				if ($rs !== false) {
					$receiptCount = intval($rs->fetchColumn(0));
					\Udoh\Emsa\Utils\DisplayUtils::drawHighlight('Done!<br><strong>'.htmlentities(trim($receiptCount)).'</strong> message receipts found for the selected criteria.', 'ui-icon-elrsuccess');
				} else {
					\Udoh\Emsa\Utils\DisplayUtils::drawError('Could not count message receipts:  An unexpected error occurred.');
				}
			} catch (Throwable $e) {
				\Udoh\Emsa\Utils\ExceptionUtils::logException($e);
				\Udoh\Emsa\Utils\DisplayUtils::drawError('Could not count message receipts: '.htmlentities($e->getMessage(), ENT_QUOTES, 'UTF-8'));
			}
		} else {
			\Udoh\Emsa\Utils\DisplayUtils::drawHighlight('No report criteria selected!  Select at least one criteria to count and try again.');
		}
	}
	
?>

<div id="addnew_form" class="addnew_lab ui-widget ui-widget-content ui-corner-all">
	<div style="clear: both;"><span class="emsa_form_heading">Count Criteria:</span><br><br></div>
	<form id="search_form" method="POST" action="<?php echo $webappBaseUrl; ?>?selected_page=<?php echo intval($navSelectedPage); ?>&submenu=<?php echo intval($navSubmenu); ?>&cat=<?php echo intval($navCat); ?>">
		
		<div class="vocab_filter ui-widget ui-widget-content ui-corner-all" style="float: left;">
			<label class="vocab_search_form2" for="date_reported_start">Earliest Date Reported:</label>
            <input class="date-range ui-corner-all" type="text" name="date_reported_start" id="date_reported_start" value="<?php echo htmlentities($clean->date_reported_start); ?>" placeholder="Any Time">
             to
            <label class="vocab_search_form2" for="date_reported_end">Latest Date Reported: </label>
            <input class="date-range ui-corner-all" type="text" name="date_reported_end" id="date_reported_end" value="<?php echo htmlentities($clean->date_reported_end); ?>" placeholder="Present">
		</div>
		
		<div style="clear: both;"></div>
		
		<div class="vocab_filter ui-widget ui-widget-content ui-corner-all">
		<div class="vocab_filter_container">
			<?php
                (new AccessibleMultiselectListbox(FilterFactory::getListFromQuery($adminDbConn, "SELECT DISTINCT report_agency AS value, report_agency AS label FROM master_xml_flat ORDER BY 1;"), $clean->reporting_agencies ?? null))
                    ->render("Reporter", "reporting_agencies", true);
			?>
		</div>
		</div>
		
		<div class="vocab_filter ui-widget ui-widget-content ui-corner-all">
		<div class="vocab_filter_container">
			<?php
                (new AccessibleMultiselectListbox(FilterFactory::getListFromQuery($adminDbConn, "SELECT DISTINCT disease AS value, disease AS label FROM master_xml_flat ORDER BY 1;"), $clean->conditions ?? null))
                    ->render("Condition", "conditions", true);
			?>
		</div>
		</div>
		
		<div class="vocab_filter ui-widget ui-widget-content ui-corner-all">
		<div class="vocab_filter_container">
			<?php
                (new AccessibleMultiselectListbox(FilterFactory::getListFromQuery($adminDbConn, "SELECT DISTINCT test_result AS value, test_result AS label FROM master_xml_flat ORDER BY 1;"), $clean->test_results ?? null))
                    ->render("Test Result", "test_results", true);
			?>
		</div>
		</div>
		
		<br>
		<input type="hidden" name="show_results" value="1">
		<button type="submit" name="count_submit" id="count_submit">Count Message Receipts</button>
	</form>
</div>
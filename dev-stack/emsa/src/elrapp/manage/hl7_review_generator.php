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

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Writer\Xls;
use Udoh\Emsa\UI\AccessibleMultiselectListbox;
use Udoh\Emsa\Utils\ExceptionUtils;

if (!class_exists('\Udoh\Emsa\Auth\Authenticator') || !\Udoh\Emsa\Auth\Authenticator::userHasPermission(\Udoh\Emsa\Auth\Authenticator::URIGHTS_ADMIN)) {
    ob_end_clean();
    header($_SERVER['SERVER_PROTOCOL'] . " 404 Not Found", TRUE, 404);
    exit;
}
?>
<script type="text/javascript">
	$(function() {
		$("#addhl7form").show();
		$("#btn_save").button({
            icon: "ui-icon-elrsave"
        });
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
		margin: 0;
	}
	
	.vocab_filter_checklist {
		min-width: 20em;
		height: 15em;
	}
	
	.vocab_filter {
		padding: 10px;
	}
	
	.vocab_search_form2 {
		margin-left: 0 !important;
	}
	
	.pseudo_select_label {
		white-space: nowrap;
	}
</style>

<?php

	function elapsed_time($start_time) {
		return round((microtime(true) - $start_time), 3)."s";
	}
    
    $validConnectors = array();
    
    $c_sql = 'SELECT DISTINCT connector FROM '.$emsaDbSchemaPrefix.'system_original_messages WHERE connector <> \'elr_WorkerQ_in\' AND connector <> \'2.16.840.1.114222.4.3.2.2.1.232.1\' AND connector NOT ILIKE \'ehars\' AND connector NOT ILIKE \'MANUAL%\' ORDER BY connector;';
    $c_rs = @pg_query($host_pa, $c_sql);
    if ($c_rs !== false) {
        while ($c_row = @pg_fetch_object($c_rs)) {
            $validConnectors[] = filter_var(trim($c_row->connector), FILTER_SANITIZE_STRING);
        }
    }
	
	if (isset($_POST['mode']) && (strlen(trim($_POST['mode'])) > 0)) {
		switch (filter_var(trim($_POST['mode']), FILTER_SANITIZE_NUMBER_INT)) {
			case 2:
				$clean['mode'] = 2; // messages (csv)
				break;
			default:
				$clean['mode'] = 1; // codes & results (xlsx)
				break;
		}
	} else {
		$clean['mode'] = 1;
	}

	$selectedConnectors = array();
    if (isset($_POST['connectors']) && is_array($_POST['connectors']) && (count($_POST['connectors']) > 0)) {
        foreach ($_POST['connectors'] as $unsafeConnector) {
            if (in_array(trim($unsafeConnector), $validConnectors)) {
                $selectedConnectors[] = filter_var(trim($unsafeConnector), FILTER_SANITIZE_STRING);
            }
        }
	}

	if (isset($_POST['date_from']) && (strlen(trim($_POST['date_from'])) > 0)) {
		$clean['date_from'] = filter_var(trim($_POST['date_from']), FILTER_SANITIZE_STRING);
	}

	if (isset($_POST['date_to']) && (strlen(trim($_POST['date_to'])) > 0)) {
		$clean['date_to'] = filter_var(trim($_POST['date_to']), FILTER_SANITIZE_STRING);
	}

	if (isset($_POST['generate_flag']) && filter_var($_POST['generate_flag'], FILTER_VALIDATE_INT) && (intval($_POST['generate_flag']) == 1)) {
		$old_max_time = ini_get('max_execution_time');
		//ini_set('max_execution_time', 10800);
		ini_set('memory_limit', '5120M');
		$time_start = microtime(true); // elapsed time debugging
		
		unset($messages);
		unset($columns);
		unset($codes);
		unset($obr_codes);
		unset($connector_clause);
		unset($date_range_clause);
		
		if ($clean['mode'] == 2) {
			$messages = array();
			$columns = array();
		} else {
			$codes = array();
			$obr_codes = array();
			$result_codes = array();
		}
		
		if (empty($selectedConnectors)) {
			\Udoh\Emsa\Utils\DisplayUtils::drawError('No connector specified!  Unable to generate report', true);
		} else {
            $escapedConnectors = array_map('pg_escape_string', $selectedConnectors);
            $quotedConnectors = array_map(function($toQuote) { return "'".$toQuote."'"; }, $escapedConnectors);
            $connector_clause = 'WHERE connector IN (' . implode(",", $quotedConnectors) . ')';
		}
		
		if (isset($clean['date_from']) && isset($clean['date_to'])) {
			// between
			$date_range_clause = " AND (created_at BETWEEN '" . date(DATE_RFC3339, strtotime($clean['date_from'])) . "' AND '" . date(DATE_RFC3339, strtotime($clean['date_to'])) . "')";
		} elseif (isset($clean['date_from'])) {
			// start to infinity
			$date_range_clause = " AND (created_at > '" . date(DATE_RFC3339, strtotime($clean['date_from'])) . "')";
		} elseif (isset($clean['date_to'])) {
			// infinity to end
			$date_range_clause = " AND (created_at < '" . date(DATE_RFC3339, strtotime($clean['date_to'])) . "')";
		} else {
			$date_range_clause = "";
		}
		
		if (isset($connector_clause) && isset($date_range_clause)) {
			$sql = 'SELECT id, message FROM '.$emsaDbSchemaPrefix.'system_original_messages '.$connector_clause.$date_range_clause.' ORDER BY created_at;';
			$rs = @pg_query($host_pa, $sql);
			
			if ($rs !== false) {
				while ($row = @pg_fetch_object($rs)) {
					unset($this_message_segments);
					$messages[$row->id] = array();
					foreach (preg_split("/((\r?\n)|(\r\n?))/", str_replace('\015', "\r", $row->message)) as $line) {
						unset($line_segments);
						unset($this_segment_type);
						$line_segments = explode('|', $line);
						$this_segment_type = trim($line_segments[0]);
						
						// inject a pipe char as MSH-1, per HL7 standards
						if ($this_segment_type == 'MSH') {
							array_splice($line_segments, 1, 0, array('|'));
						}
						
						if (isset($this_message_segments[$this_segment_type])) {
							$this_message_segments[$this_segment_type] = $this_message_segments[$this_segment_type] + 1;
						} else {
							$this_message_segments[$this_segment_type] = 1;
						}
						
						if ($clean['mode'] == 2) {
							foreach ($line_segments as $segment_id => $segment_value) {
								if ($segment_id > 0) {
									unset ($long_segment_id);
									$long_segment_id = (($this_message_segments[$this_segment_type] == 1) ? $this_segment_type.'-'.trim($segment_id) : $this_segment_type.'_'.trim($this_message_segments[$this_segment_type]).'-'.trim($segment_id));
									// add segment type+id to $columns
									if (!in_array($long_segment_id, $columns)) {
										$columns[] = $long_segment_id;
									}
									// add segment value to $messages for this message ID
									$messages[$row->id][$long_segment_id] = trim($segment_value);
								}
							}
						} else {
							// if OBX, add distinct codes & results to $codes
							if ($this_segment_type == 'OBX') {
								if (!isset($codes[$line_segments[3]])) {
									$codes[$line_segments[3]] = array();
								}
								if (!in_array(trim($line_segments[5]), $codes[$line_segments[3]])) {
									$codes[trim($line_segments[3])][] = trim($line_segments[5]);
								}
								if (isset($result_codes[trim($line_segments[3])][trim($line_segments[5])])) {
									$result_codes[trim($line_segments[3])][trim($line_segments[5])]++;
								} else {
									$result_codes[trim($line_segments[3])][trim($line_segments[5])] = 1;
								}
							}
							
							// if OBR, add distinct OBR LOINC codes $obr_codes
							if ($this_segment_type == 'OBR') {
								if (!isset($obr_codes[$line_segments[4]])) {
									$obr_codes[$line_segments[4]] = array();
								}
							}
						}
					}
				}
			} else {
				\Udoh\Emsa\Utils\DisplayUtils::drawError('An error occurred while querying HL7 messages.', true);
			}
			pg_free_result($rs);
			
			if ($clean['mode'] == 2) {
				usort($columns, array('\Udoh\Emsa\Utils\SortUtils', 'hl7ColumnSort')); // group by segment & order by segment element number
				
				// save our messages CSV output
				ob_clean();
				$hl7ReviewOutputFilename = 'hl7_review_messages_'.date("YmdHis", time()).'.csv';
				header('Content-Type: text/csv');
				header('Content-Disposition: attachment; filename='.$hl7ReviewOutputFilename);
				header('Expires: 0');
				header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
				header('Pragma: public');
				
				$output_file = fopen('php://output', 'w');
				
				// set column headings
				fwrite($output_file, 'MsgID,');
				fputcsv($output_file, $columns, ',', '"');
				
				// process data rows
				foreach ($messages as $message_id => $message_data) {
					fwrite($output_file, intval($message_id).',');
					unset($this_line);
					$this_line = array();
					foreach ($columns as $column_key => $column_name) {
						$this_line[] = (isset($message_data[$column_name])) ? trim($message_data[$column_name]) : '';
					}
					fputcsv($output_file, $this_line, ',', '"');
				}
				
				unset($messages);
				unset($columns);
				
				fclose($output_file);
				$hl7ReviewOutputFilename = null;
				exit;
			} else {
				// Initialize PhpSpreadsheet stuff
				ksort($codes);
				ksort($obr_codes);
				ksort($result_codes);
				arsort($result_codes);
				
				$tabs_array = array('Test Codes', 'Codes-Results', 'OBR LOINC Codes');
			
				try {
                    // create a new PhpSpreadsheed document & writer
                    $hl7ReviewSpreadsheet = new Spreadsheet();

                    for ($hl7ReviewSheetIndex = 0; $hl7ReviewSheetIndex < count($tabs_array); $hl7ReviewSheetIndex++) {
                        $hl7ReviewRowCursor = 2;  // skip over header row
                        if ($hl7ReviewSheetIndex > 0) {
                            $hl7ReviewSpreadsheet->createSheet();
                        }
                        // set the current sheet & change the name
                        $hl7ReviewSpreadsheet->setActiveSheetIndex($hl7ReviewSheetIndex);
                        $hl7ReviewCurrentSheet = $hl7ReviewSpreadsheet->getActiveSheet();
                        $hl7ReviewCurrentSheet->setTitle(substr($tabs_array[$hl7ReviewSheetIndex], 0, 30));

                        if ($hl7ReviewSheetIndex == 0) {
                            // set column headings
                            $hl7ReviewCurrentSheet->setCellValue('A1', 'Value');
                            $hl7ReviewCurrentSheet->setCellValue('B1', 'Notes');
                            $hl7ReviewCurrentSheet->setCellValue('C1', 'Yellow');
                            $hl7ReviewCurrentSheet->setCellValue('D1', 'Red');
                            $hl7ReviewCurrentSheet->setCellValue('E1', 'Green');

                            $hl7ReviewCurrentSheet->getStyle('A1:E1')->getFont()->setBold(true);
                            $hl7ReviewCurrentSheet->getStyle('A1:E1')->getFill()->setFillType(Fill::FILL_SOLID);
                            $hl7ReviewCurrentSheet->getStyle('A1:B1')->getFill()->getStartColor()->setARGB('FFCCCCCC');
                            $hl7ReviewCurrentSheet->getStyle('C1')->getFill()->getStartColor()->setARGB('FFFFFF00');
                            $hl7ReviewCurrentSheet->getStyle('D1')->getFill()->getStartColor()->setARGB('FFFF0000');
                            $hl7ReviewCurrentSheet->getStyle('E1')->getFill()->getStartColor()->setARGB('FF008000');

                            // process data rows
                            foreach ($codes as $test_code => $result_values) {
                                $hl7ReviewCurrentSheet->setCellValue('A' . trim($hl7ReviewRowCursor), trim($test_code));
                                $hl7ReviewRowCursor++;
                            }
                        } elseif ($hl7ReviewSheetIndex == 1) {
                            // set column headings
                            $hl7ReviewCurrentSheet->setCellValue('A1', 'Test Codes');
                            $hl7ReviewCurrentSheet->setCellValue('B1', 'Result Values');
                            $hl7ReviewCurrentSheet->setCellValue('C1', 'Result Value Counts');

                            $hl7ReviewCurrentSheet->getStyle('A1:C1')->getFont()->setBold(true);
                            $hl7ReviewCurrentSheet->getStyle('A1:C1')->getFill()->setFillType(Fill::FILL_SOLID);
                            $hl7ReviewCurrentSheet->getStyle('A1:C1')->getFill()->getStartColor()->setARGB('FFCCCCCC');

                            // process data rows
                            foreach ($codes as $test_code => $result_values) {
                                sort($result_values);
                                foreach ($result_values as $result_value) {
                                    $hl7ReviewCurrentSheet->setCellValue('A' . trim($hl7ReviewRowCursor), trim($test_code));
                                    $hl7ReviewCurrentSheet->setCellValueExplicit('B' . trim($hl7ReviewRowCursor), trim($result_value), DataType::TYPE_STRING);
                                    $hl7ReviewCurrentSheet->setCellValue('C' . trim($hl7ReviewRowCursor), trim($result_codes[trim($test_code)][trim($result_value)]));
                                    $hl7ReviewRowCursor++;
                                }
                            }
                        } elseif ($hl7ReviewSheetIndex == 2) {
                            // set column headings
                            $hl7ReviewCurrentSheet->setCellValue('A1', 'OBR LOINC Code');
                            $hl7ReviewCurrentSheet->setCellValue('B1', 'OBR Test Name');
                            $hl7ReviewCurrentSheet->setCellValue('C1', 'OBR Local Code');
                            $hl7ReviewCurrentSheet->setCellValue('D1', 'OBR Local Test Name');

                            $hl7ReviewCurrentSheet->getStyle('A1:D1')->getFont()->setBold(true);
                            $hl7ReviewCurrentSheet->getStyle('A1:D1')->getFill()->setFillType(Fill::FILL_SOLID);
                            $hl7ReviewCurrentSheet->getStyle('A1:D1')->getFill()->getStartColor()->setARGB('FFCCCCCC');

                            // process data rows
                            foreach ($obr_codes as $obr_test_code => $obr_result_values) {
                                unset($this_obr_code_arr);
                                $this_obr_code_arr = explode('^', $obr_test_code);
                                $hl7ReviewCurrentSheet->setCellValue('A' . trim($hl7ReviewRowCursor), trim($this_obr_code_arr[0]));
                                $hl7ReviewCurrentSheet->setCellValueExplicit('B' . trim($hl7ReviewRowCursor), trim($this_obr_code_arr[1]), DataType::TYPE_STRING);
                                $hl7ReviewCurrentSheet->setCellValue('C' . trim($hl7ReviewRowCursor), trim($this_obr_code_arr[3]));
                                $hl7ReviewCurrentSheet->setCellValue('D' . trim($hl7ReviewRowCursor), trim($this_obr_code_arr[4]));
                                $hl7ReviewRowCursor++;
                            }
                        } elseif ($hl7ReviewSheetIndex == 3) {
                            $hl7ReviewCurrentSheet->setCellValue('A1', 'Number of Uses');
                            $hl7ReviewCurrentSheet->setCellValue('B1', 'Result Code');

                            $hl7ReviewCurrentSheet->getStyle('A1:B1')->getFont()->setBold(true);
                            $hl7ReviewCurrentSheet->getStyle('A1:B1')->getFill()->setFillType(Fill::FILL_SOLID);
                            $hl7ReviewCurrentSheet->getStyle('A1:B1')->getFill()->getStartColor()->setARGB('FFCCCCCC');

                            // process data rows
                            foreach ($result_codes as $result_code => $result_code_count) {
                                $hl7ReviewCurrentSheet->setCellValue('A' . trim($hl7ReviewRowCursor), trim($result_code_count));
                                $hl7ReviewCurrentSheet->getStyle('A' . trim($hl7ReviewRowCursor))->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
                                $hl7ReviewCurrentSheet->setCellValueExplicit('B' . trim($hl7ReviewRowCursor), trim($result_code), DataType::TYPE_STRING);
                                $hl7ReviewRowCursor++;
                            }


                            $hl7ReviewCurrentSheet->getColumnDimension("A")->setAutoSize(true);
                            $hl7ReviewCurrentSheet->getColumnDimension("B")->setAutoSize(true);
                        }
                    }
                    unset($codes);

                    // save our workbook
                    ob_clean();
                    $hl7ReviewOutputFilename = 'hl7_review_' . date("YmdHis", time()) . '.xls';
                    header('Content-Type: application/vnd.ms-excel');
                    header('Content-Disposition: attachment; filename=' . $hl7ReviewOutputFilename);
                    header('Content-Transfer-Encoding: binary');
                    header('Expires: 0');
                    header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
                    header('Pragma: public');

                    // move back to first sheet for opening
                    $hl7ReviewSpreadsheet->setActiveSheetIndex(0);
                    
                    $hl7ReviewXlsWriter = new Xls($hl7ReviewSpreadsheet);
                    $hl7ReviewXlsWriter->save('php://output');
                } catch (Throwable $e) {
				    ExceptionUtils::logException($e);
                } finally {
				    // important to disconnect worksheets to prevent memory leak
                    $hl7ReviewSpreadsheet->disconnectWorksheets();
                    $hl7ReviewXlsWriter = null;
                    $hl7ReviewSpreadsheet = null;
                    $hl7ReviewOutputFilename = null;
                    exit;
                }
			}
			
			#debug echo 'mem used: '.memory_get_peak_usage();
			#debug echo '<br>time: '.elapsed_time($time_start);
			
			/* DEBUG
			echo '<pre>';
			print_r($columns);
			echo '<hr style="background-color: purple; width: 100%; height: 20px;" noshade>';
			print_r($messages);
			echo '<hr style="background-color: purple; width: 100%; height: 20px;" noshade>';
			print_r($codes);
			echo '</pre>';
			*/
			
		} else {
			\Udoh\Emsa\Utils\DisplayUtils::drawError('Missing connector or date range.', true);
		}
		//ini_set('max_execution_time', $old_max_time);
	} else {
?>

<h1 class="elrhdg"><span class="ui-icon ui-icon-header ui-icon-elrxml"></span>Generate HL7 Message Review Spreadsheet</h1>

<div id="addhl7form" class="addnew_lab ui-widget ui-widget-content ui-corner-all">
	<div style="clear: both;"><span class="emsa_form_heading">Generate HL7 Message Review</span><br><br></div>
	<form id="search_form" method="POST" target="_blank" action="<?php echo $webappBaseUrl; ?>?selected_page=<?php echo intval($navSelectedPage); ?>&submenu=<?php echo intval($navSubmenu); ?>&cat=<?php echo intval($navCat); ?>">
		<div class="vocab_filter ui-widget ui-widget-content ui-corner-all">
        <div class="vocab_filter_container">
            <label class="vocab_search_form2" for="mode">Generate: </label><select class="ui-corner-all" name="mode" id="mode">
                <option value="1" selected>Test Codes/Results (.xls)</option>
                <option value="2">Messages (.csv)</option>
            </select>
        </div>
        </div>
        
        <div class="vocab_filter ui-widget ui-widget-content ui-corner-all">
        <div class="vocab_filter_container">
            <label class="vocab_search_form2">From Date: <input class="date-range ui-corner-all" type="text" name="date_from" id="date_from" value="<?php echo ((isset($clean['date_from'])) ? $clean['date_from'] : ""); ?>" placeholder="Any Time"></label>
            <label class="vocab_search_form2">To Date: <input class="date-range ui-corner-all" type="text" name="date_to" id="date_to" value="<?php echo ((isset($clean['date_to'])) ? $clean['date_to'] : ""); ?>" placeholder="Present"></label>
        </div>
        </div>
		
        <div style="clear: both;"></div>
        
        <div class="vocab_filter ui-widget ui-widget-content ui-corner-all">
        <div class="vocab_filter_container">
            <?php
                $validConnectorsForListbox = [];
                foreach ($validConnectors as $validConnector) {
                    $validConnectorsForListbox[$validConnector] = $validConnector;
                }

                $connectorListbox = new AccessibleMultiselectListbox($validConnectorsForListbox);
                $connectorListbox->render("Reporting Facilities", "connectors", true);
            ?>
        </div>
        </div>
        
        <input type="hidden" name="generate_flag" value="1" />
		<br><br><button type="submit" name="btn_save" id="btn_save">Generate</button>
	</form>
</div>

<?php
	}
?>
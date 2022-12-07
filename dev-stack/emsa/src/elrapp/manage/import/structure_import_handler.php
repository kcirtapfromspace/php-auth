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

use PhpOffice\PhpSpreadsheet\Reader\Xls;
use Udoh\Emsa\Import\ImportUtils;
use Udoh\Emsa\Utils\ExceptionUtils;

if (!class_exists('\Udoh\Emsa\Auth\Authenticator') || !\Udoh\Emsa\Auth\Authenticator::userHasPermission(\Udoh\Emsa\Auth\Authenticator::URIGHTS_ADMIN)) {
    ob_end_clean();
    header($_SERVER['SERVER_PROTOCOL'] . " 404 Not Found", TRUE, 404);
    exit;
}
?>
<script>
	$(function() {
		$("#upload_button").button({
			icon: "ui-icon-elrsave"
		}).on("click", function(){
			$("#import_uploader").trigger("submit");
		});
		
		$("#addanother_button").button({
			icon: "ui-icon-arrowreturnthick-1-w"
		}).on("click", function(){
			window.location = '?selected_page=6&submenu=4&cat=2&subcat=7';
		});
	});
</script>

<h1 class="elrhdg"><span class="ui-icon ui-icon-header ui-icon-elrimport"></span>Import New Structure</h1>

<?php

	// check for valid import type/lab
	$lab_qry = "SELECT id FROM ".$emsaDbSchemaPrefix."structure_labs;";
	$lab_rs = pg_query($host_pa, $lab_qry);
	while ($lab_row = pg_fetch_object($lab_rs)) {
		$valid_labs[] = intval($lab_row->id);
	}
	pg_free_result($lab_rs);
	
	if (isset($_POST['vocab_type'])) {
		switch (strtolower(trim($_POST['vocab_type']))) {
			case "childmirth":
				if (isset($_POST['vocab_child_lab'])) {
					if (in_array(intval(trim($_POST['vocab_child_lab'])), $valid_labs)) {
						$clean['lab_id'] = intval(trim($_POST['vocab_child_lab']));
						$clean['vocab_type'] = "childmirth";
						$required_tab_list = array(
							"Sheet1"
						);
					} else {
						die ("<div class=\"import_widget ui-widget import_error ui-state-error ui-corner-all\" style=\"padding: 5px;\"><span class=\"ui-icon ui-icon-alert\" style=\"float: left; margin-right: .3em;\"></span><p style=\"margin-left: 20px;\"><strong>Could not complete structure input:</strong>  Child lab not selected.</p></div>");
					}
				} else {
					die ("<div class=\"import_widget ui-widget import_error ui-state-error ui-corner-all\" style=\"padding: 5px;\"><span class=\"ui-icon ui-icon-alert\" style=\"float: left; margin-right: .3em;\"></span><p style=\"margin-left: 20px;\"><strong>Could not complete structure input:</strong>  Child lab not selected.</p></div>");
				}
				break;
			default:
				die ("<div class=\"import_widget ui-widget import_error ui-state-error ui-corner-all\" style=\"padding: 5px;\"><span class=\"ui-icon ui-icon-alert\" style=\"float: left; margin-right: .3em;\"></span><p style=\"margin-left: 20px;\"><strong>Could not complete structure input:</strong>  Structure type not specified.</p></div>");
		}
	} else {
		die ("<div class=\"import_widget ui-widget import_error ui-state-error ui-corner-all\" style=\"padding: 5px;\"><span class=\"ui-icon ui-icon-alert\" style=\"float: left; margin-right: .3em;\"></span><p style=\"margin-left: 20px;\"><strong>Could not complete structure input:</strong>  Structure type not specified.</p></div>");
	}

	$validUpload = false;
    $upload['upload_dir'] = __DIR__ . "/upload/";
    $upload['safe_path'] = $upload['upload_dir'] . 'structure-import-' . ImportUtils::generateRandomFilenameToken() . '.xls';

    if (isset($_FILES['vocab_source'])) {
        $errorsOccurred = false;

        if (is_array($_FILES['vocab_source']['error']) && (count($_FILES['vocab_source']['error']) > 0)) {
            $errorsOccurred = true;
        } elseif ((int) $_FILES['vocab_source']['error'] > 0) {
            $errorsOccurred = true;
        }

        if (ImportUtils::getFileMIMEType($_FILES['vocab_source']['tmp_name']) != 'application/vnd.ms-excel') {
            // only accept if MS Excel (.xls) file type
            $errorsOccurred = true;
        }

        if (!$errorsOccurred && move_uploaded_file($_FILES['vocab_source']['tmp_name'], $upload['safe_path'])) {
            $validUpload = true;
        }
    }

    if (!$validUpload) {
		// file did not upload successfully or no file specified...
		die("<div class=\"import_widget ui-widget import_error ui-state-error ui-corner-all\" style=\"padding: 5px;\"><span class=\"ui-icon ui-icon-alert\" style=\"float: left; margin-right: .3em;\"></span><p style=\"margin-left: 20px;\"><strong>Could not complete structure input:</strong>  No file was selected/file was not successfully uploaded.</p></div>");
	}
	
	// Create a new PhpSpreadsheet Xls Reader
	$objReader = new Xls();

	// Load uploaded file to a Spreadsheet object
	try {
		$pxls = $objReader->load($upload['safe_path']);
	} catch (Throwable $pxls_e) {
        ExceptionUtils::logException($pxls_e);
		die ("<div class=\"import_widget ui-widget import_error ui-state-error ui-corner-all\" style=\"padding: 5px;\"><span class=\"ui-icon ui-icon-alert\" style=\"float: left; margin-right: .3em;\"></span><p style=\"margin-left: 20px;\"><strong>Could not complete structure input:</strong>  ".$pxls_e->getMessage()."</p></div>");
	}
	
	echo "<div class=\"import_widget ui-widget import_error ui-state-highlight ui-corner-all\" style=\"padding: 5px;\"><span class=\"ui-icon ui-icon-info\" style=\"float: left; margin-right: .3em;\"></span><p style=\"margin-left: 20px;\"><em style=\"color: dimgray;\">Reading new structure data...</em></p></div>";
	
	// make sure required master/child tabs are included in workbook
	// tab names compared with strtoupper() due to case-sensitivity of in_array()
	$found_tabs = 0;
	
	function array_caps($srcArray) {
		return strtoupper($srcArray);
	}
	
	$uploaded_sheets = array_map("array_caps", $pxls->getSheetNames());
	
	if (is_array($uploaded_sheets)) {
		foreach ($required_tab_list as $req_tab_name) {
			if (in_array(strtoupper($req_tab_name), $uploaded_sheets)) {
				$found_tabs++;
			}
		}
	}
	
	if ($found_tabs !== sizeof($required_tab_list)) {
		// required tabs not found...
		die("<div class=\"import_widget ui-widget import_error ui-state-error ui-corner-all\" style=\"padding: 5px;\"><span class=\"ui-icon ui-icon-alert\" style=\"float: left; margin-right: .3em;\"></span><p style=\"margin-left: 20px;\"><strong>Could not complete structure input:</strong>  One or more worksheets may be missing.</p></div>");
	}
	
	

	// start import
	unset($vocab_insert_sql);
	unset($insert_rs);
	unset($insert_error_rs);
	unset($delete_rs);
	unset($delete_error_rs);
	foreach ($required_tab_list as $import_tab) {
	    try {
            $pxls->setActiveSheetIndexByName($import_tab);
            $this_sheet = $pxls->getActiveSheet()->toArray(null, true, false, true);
        } catch (Throwable $e) {
            $this_sheet = null;
            ExceptionUtils::logException($e);
        }
		
		if (($clean['vocab_type'] == "childmirth") && ($import_tab == "Sheet1")) {
			$sm_inserts = 0;
			foreach ($this_sheet as $this_key => $this_row) {
				// don't insert column headings or any rows with a null/empty child lab name, message version, and HL7 XPath
				if (($this_key > 1) && (strlen(trim($this_row['A'])) > 1) && (strlen(trim($this_row['B'])) > 1) && (strlen(trim($this_row['C'])) > 1)) {
					if (strlen(trim($this_row['A'])) > 0) {
						$getlabid_sql = sprintf("SELECT id AS id FROM %sstructure_labs WHERE ui_name ILIKE '%s';", $emsaDbSchemaPrefix, pg_escape_string(trim($this_row['A'])));
						$getlabid_row = @pg_fetch_object(@pg_query($host_pa, $getlabid_sql));
						$this_labid = intval($getlabid_row->id);
						if ($this_labid < 1) {
							$this_labid = -1;
							$missing_vocab['Child HL7 Structure']['Lab Name'][] = trim($this_row['A']);
						}
					} else {
						$this_labid = -1;
					}
					
					// decode result location
					if (strlen(trim($this_row['D'])) > 0) {
						$getmasterpath_sql = sprintf("SELECT id AS id FROM %sstructure_path WHERE xpath = '%s'", $emsaDbSchemaPrefix, pg_escape_string(trim($this_row['D'])));
						$getmasterpath_row = @pg_fetch_object(@pg_query($host_pa, $getmasterpath_sql));
						$this_masterpath = intval($getmasterpath_row->id);
						if ($this_masterpath < 1) {
							$this_masterpath = null;
							$missing_vocab['Child HL7 Structure']['Master XPath'][] = trim($this_row['D']);
						}
					} else {
						$this_masterpath = null;
					}
					
					$sm_inserts++;
					$vocab_insert_sql[] = sprintf(
						"INSERT INTO %sstructure_path_mirth (
							lab_id, 
							message_version,
							master_path_id,
							glue_string,
							xpath,
							sequence) 
						VALUES 
							(%d, %s, %d, %s, %s, %d);", $emsaDbSchemaPrefix, 
							intval($this_labid),
							((strlen(trim($this_row['B'])) > 0) ? "'".pg_escape_string(trim($this_row['B']))."'" : "NULL"),
							((is_null($this_masterpath)) ? 'NULL' : intval($this_masterpath)),
							((strlen(trim($this_row['E'])) > 0) ? "'".pg_escape_string(trim($this_row['E']))."'" : "NULL"),
							((strlen(trim($this_row['C'])) > 0) ? "'".pg_escape_string(trim($this_row['C']))."'" : "NULL"),
							((intval(trim($this_row['F'])) > 0) ? intval(trim($this_row['F'])) : 1)
						);
				}
			}
		}
	}


	// Structure source file has now been scanned & INSERTs prepared... time to clear out the old data
	// Because queries are done in a transaction, errors must be collected along the way & dumped after the ROLLBACK
	
	// Before deleting, prepare the INSERT transaction to verify no errors (don't want to dump data if we're not going
	// to be able to insert it later!)
	
	$insert_rs[] = @pg_query($host_pa, "BEGIN");
		$insert_error_rs[] = pg_last_error();
		
	foreach ($vocab_insert_sql as $vocab_insert_exec) {
		$insert_rs[] = @pg_query($host_pa, $vocab_insert_exec);
			$insert_error_rs[] = pg_last_error();
	}
	
	if (in_array(FALSE, $insert_rs)) {
		// bad things happened preparing the INSERTs, run away
		@pg_query("ROLLBACK");
		
		// list-ify all of the errors
		$insert_errors = "";
		foreach ($insert_error_rs as $insert_error_item) {
			if (strlen($insert_error_item) > 0) {
				$insert_errors .= "<li>".$insert_error_item."</li>";
			}
		}
		die ("<div class=\"import_widget ui-widget import_error ui-state-error ui-corner-all\" style=\"padding: 5px;\"><span class=\"ui-icon ui-icon-alert\" style=\"float: left; margin-right: .3em;\"></span><p style=\"margin-left: 20px;\"><strong>Could not complete structure input:</strong>  Unable to prepare to insert new structure.  Errors encountered:</p><ul>" . $insert_errors . "</ul></div>");
	} else {
		// name the prepared transaction so we can COMMIT it after successful purging of old data...
		$insert_prepare_token = sprintf("insert_vocab_%s", uniqid('', true));
		$insert_prepare_stmt = sprintf("PREPARE TRANSACTION '%s';", $insert_prepare_token);
		$commit_insert_stmt = sprintf("COMMIT PREPARED '%s'", $insert_prepare_token);
		$rollback_insert_stmt = sprintf("ROLLBACK PREPARED '%s'", $insert_prepare_token);
		
		@pg_query($insert_prepare_stmt);
		
		if ($clean['vocab_type'] == "childmirth") {
			$found_stats = sprintf("%s Child HL7 XPath records found to import...", $sm_inserts);
		}
		echo "<div class=\"import_widget ui-widget import_error ui-state-highlight ui-corner-all\" style=\"padding: 5px;\"><span class=\"ui-icon ui-icon-info\" style=\"float: left; margin-right: .3em;\"></span><p style=\"margin-left: 20px;\"><em style=\"color: dimgray;\">Preparing new structure dataset.  ".$found_stats."</em></div>";
	
	
		// INSERT prepared transaction looks good, let's DELETE!
		$delete_rs[] = @pg_query($host_pa, "BEGIN");
			$delete_error_rs[] = pg_last_error();
			
		if ($clean['vocab_type'] == "childmirth") {
			$delete_rs[] = @pg_query($host_pa, "DELETE FROM {$emsaDbSchemaPrefix}structure_path_mirth WHERE lab_id = {$clean['lab_id']};");
				$delete_error_rs[] = pg_last_error();
		}
		
		if (in_array(FALSE, $delete_rs)) {
			// bad things happened with the delete, run away
			@pg_query("ROLLBACK");
			
			// rollback the prepared INSERT as well
			@pg_query($rollback_insert_stmt);
			
			// list-ify all of the errors
			$delete_errors = "";
			foreach ($delete_error_rs as $error_item) {
				if (strlen($error_item) > 0) {
					$delete_errors .= "<li>".$error_item."</li>";
				}
			}
			die ("<div class=\"import_widget ui-widget import_error ui-state-error ui-corner-all\" style=\"padding: 5px;\"><span class=\"ui-icon ui-icon-alert\" style=\"float: left; margin-right: .3em;\"></span><p style=\"margin-left: 20px;\"><strong>Could not complete structure input:</strong>  Unable to purge old structure data.  Errors encountered:</p><ul>" . $delete_errors . "</ul></div>");
		} else {
			// commit the DELETE
			@pg_query("COMMIT");
			echo "<div class=\"import_widget ui-widget import_error ui-state-highlight ui-corner-all\" style=\"padding: 5px;\"><span class=\"ui-icon ui-icon-info\" style=\"float: left; margin-right: .3em;\"></span><p style=\"margin-left: 20px;\"><em style=\"color: dimgray;\">Old structure data successfully purged...</em></p></div>";
			
			// commit the previously-prepared INSERT
			@pg_query($commit_insert_stmt);
			
			echo "<div class=\"import_widget ui-widget import_error ui-state-highlight ui-corner-all\" style=\"padding: 5px;\"><span class=\"ui-icon ui-icon-elrsuccess\" style=\"float: left; margin-right: .3em;\"></span><p style=\"margin-left: 20px;\"><strong>New structure successfully imported!</strong></p><p><button id=\"addanother_button\">Import Another Structure List?</button></p></div>";
			
			if (isset($missing_vocab) && is_array($missing_vocab)) {
				echo "<div class=\"import_widget ui-widget import_error ui-state-error ui-corner-all\" style=\"padding: 5px;\"><span class=\"ui-icon ui-icon-info\" style=\"float: left; margin-right: .3em;\"></span><p style=\"margin-left: 20px;\"><strong>Warning:</strong>  No parent vocabulary match was found for the following items, but the records were imported with blank values in the respective fields:";
				echo "<pre>";
				//print_r ($missing_vocab);
				echo "</pre>";
				foreach ($missing_vocab as $missing_vocab_tab => $missing_vocab_items) {
					echo "<br><strong><em>From " . $missing_vocab_tab . "...<ul>";
					foreach ($missing_vocab_items as $missing_item_type => $missing_item_values) {
						foreach ($missing_item_values as $missing_item_value) {
							echo "<li>" . htmlentities($missing_item_type) . " =&gt; " . htmlentities($missing_item_value) . "</li>";
						}
					}
					echo "</ul>";
				}
				echo "</p></div>";
			}
		}
		
	}
	
?>

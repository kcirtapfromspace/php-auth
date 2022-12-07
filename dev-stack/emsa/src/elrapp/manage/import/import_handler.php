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
use Udoh\Emsa\Utils\CoreUtils;
use Udoh\Emsa\Utils\ExceptionUtils;
use Udoh\Emsa\Utils\VocabUtils;

if (!class_exists('\Udoh\Emsa\Auth\Authenticator') || !\Udoh\Emsa\Auth\Authenticator::userHasPermission(\Udoh\Emsa\Auth\Authenticator::URIGHTS_ADMIN)) {
    ob_end_clean();
    header($_SERVER['SERVER_PROTOCOL'] . " 404 Not Found", TRUE, 404);
    exit;
}

$labList = CoreUtils::getReporterList($adminDbConn);

$cleanVocabType = (string) filter_input(INPUT_POST, 'vocab_type', FILTER_SANITIZE_STRING);
$cleanLabId = (int) filter_input(INPUT_POST, 'vocab_child_lab', FILTER_SANITIZE_NUMBER_INT);

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
			window.location = '?selected_page=6&submenu=3&cat=3&subcat=6';
		});
	});
</script>

<h1 class="elrhdg"><span class="ui-icon ui-icon-header ui-icon-elrimport"></span>Import New Vocabulary</h1>

<?php

// check for valid import type/lab
if (!empty($cleanVocabType)) {
    switch ($cleanVocabType) {
        case "master":
            $clean['vocab_type'] = "master";
            $requiredTabList = array(
                "Master Condition",
                "Master Organism",
                "Master LOINC to Condition"
            );
            break;
        case "mastervocab":
            $clean['vocab_type'] = "mastervocab";
            $requiredTabList = array(
                "Sheet1"
            );
            break;
        case "master-icd":
            $clean['vocab_type'] = "master-icd";
            $requiredTabList = array(
                "Sheet1"
            );
            break;
        case "master-pfge":
            $clean['vocab_type'] = "master-pfge";
            $requiredTabList = array(
                "Sheet1"
            );
            break;
        case "trisano":
            $clean['vocab_type'] = "trisano";
            $requiredTabList = array(
                "Sheet1"
            );
            break;
        case "childvocab":
            if (!empty($cleanLabId)) {
                if (array_key_exists($cleanLabId, $labList)) {
                    $clean['lab_id'] = $cleanLabId;
                    $clean['vocab_type'] = "childvocab";
                    $requiredTabList = array(
                        "Child Vocab"
                    );
                } else {
                    die ("<div class=\"import_widget ui-widget import_error ui-state-error ui-corner-all\" style=\"padding: 5px;\"><span class=\"ui-icon ui-icon-alert\" style=\"float: left; margin-right: .3em;\"></span><p style=\"margin-left: 20px;\"><strong>Could not complete vocabulary input:</strong>  Child lab not selected.</p></div>");
                }
            } else {
                die ("<div class=\"import_widget ui-widget import_error ui-state-error ui-corner-all\" style=\"padding: 5px;\"><span class=\"ui-icon ui-icon-alert\" style=\"float: left; margin-right: .3em;\"></span><p style=\"margin-left: 20px;\"><strong>Could not complete vocabulary input:</strong>  Child lab not selected.</p></div>");
            }
            break;
        case "child":
            if (!empty($cleanLabId)) {
                if (array_key_exists($cleanLabId, $labList)) {
                    $clean['lab_id'] = $cleanLabId;
                    $clean['vocab_type'] = "child";
                    $requiredTabList = array(
                        "Child LOINC",
                        "Child Organism"
                    );
                } else {
                    die ("<div class=\"import_widget ui-widget import_error ui-state-error ui-corner-all\" style=\"padding: 5px;\"><span class=\"ui-icon ui-icon-alert\" style=\"float: left; margin-right: .3em;\"></span><p style=\"margin-left: 20px;\"><strong>Could not complete vocabulary input:</strong>  Child lab not selected.</p></div>");
                }
            } else {
                die ("<div class=\"import_widget ui-widget import_error ui-state-error ui-corner-all\" style=\"padding: 5px;\"><span class=\"ui-icon ui-icon-alert\" style=\"float: left; margin-right: .3em;\"></span><p style=\"margin-left: 20px;\"><strong>Could not complete vocabulary input:</strong>  Child lab not selected.</p></div>");
            }
            break;
        default:
            die ("<div class=\"import_widget ui-widget import_error ui-state-error ui-corner-all\" style=\"padding: 5px;\"><span class=\"ui-icon ui-icon-alert\" style=\"float: left; margin-right: .3em;\"></span><p style=\"margin-left: 20px;\"><strong>Could not complete vocabulary input:</strong>  Vocuabulary type not specified.</p></div>");
    }
} else {
    die ("<div class=\"import_widget ui-widget import_error ui-state-error ui-corner-all\" style=\"padding: 5px;\"><span class=\"ui-icon ui-icon-alert\" style=\"float: left; margin-right: .3em;\"></span><p style=\"margin-left: 20px;\"><strong>Could not complete vocabulary input:</strong>  Vocuabulary type not specified.</p></div>");
}

$validUpload = false;
$upload['upload_dir'] = __DIR__ . "/upload/";
$upload['safe_path'] = $upload['upload_dir'] . 'vocab-import-' . ImportUtils::generateRandomFilenameToken() . '.xls';
	
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
    die("<div class=\"import_widget ui-widget import_error ui-state-error ui-corner-all\" style=\"padding: 5px;\"><span class=\"ui-icon ui-icon-alert\" style=\"float: left; margin-right: .3em;\"></span><p style=\"margin-left: 20px;\"><strong>Could not complete vocabulary input:</strong>  No file was selected/file was not successfully uploaded.</p></div>");
}

// Create a new PhpSpreadsheet Xls Reader
$objReader = new Xls();

// Load uploaded file to a Spreadsheet object
try {
    $pxls = $objReader->load($upload['safe_path']);
} catch (Throwable $pxls_e) {
    ExceptionUtils::logException($pxls_e);
    die ("<div class=\"import_widget ui-widget import_error ui-state-error ui-corner-all\" style=\"padding: 5px;\"><span class=\"ui-icon ui-icon-alert\" style=\"float: left; margin-right: .3em;\"></span><p style=\"margin-left: 20px;\"><strong>Could not complete vocabulary input:</strong>  ".$pxls_e->getMessage()."</p></div>");
}

echo "<div class=\"import_widget ui-widget import_error ui-state-highlight ui-corner-all\" style=\"padding: 5px;\"><span class=\"ui-icon ui-icon-info\" style=\"float: left; margin-right: .3em;\"></span><p style=\"margin-left: 20px;\"><em style=\"color: dimgray;\">Reading new vocabulary data...</em></p></div>";

// make sure required master/child tabs are included in workbook
// tab names compared with strtoupper() due to case-sensitivity of in_array()
$foundTabs = 0;

function array_caps($srcArray) {
    return strtoupper($srcArray);
}

$uploadedSheets = array_map("array_caps", $pxls->getSheetNames());

if (is_array($uploadedSheets)) {
    foreach ($requiredTabList as $reqTabName) {
        if (in_array(strtoupper($reqTabName), $uploadedSheets)) {
            $foundTabs++;
        }
    }
}

if ($foundTabs !== sizeof($requiredTabList)) {
    // required tabs not found...
    die("<div class=\"import_widget ui-widget import_error ui-state-error ui-corner-all\" style=\"padding: 5px;\"><span class=\"ui-icon ui-icon-alert\" style=\"float: left; margin-right: .3em;\"></span><p style=\"margin-left: 20px;\"><strong>Could not complete vocabulary input:</strong>  One or more worksheets may be missing.</p></div>");
}



// Master LOINC/Condition/Organism handling
if ($clean['vocab_type'] == "master") {
    // Process condition first, then organism, then LOINCs for ID lookups to succeed...

    ###############################################
    # Master Condition ############################
        unset($vocab_insert_sql);
        unset($insert_rs);
        unset($insert_error_rs);
        unset($delete_rs);
        unset($delete_error_rs);

        try {
            $pxls->setActiveSheetIndexByName("Master Condition");
            $thisSheet = $pxls->getActiveSheet()->toArray(null, true, false, true);
        } catch (Throwable $e) {
            $thisSheet = null;
            ExceptionUtils::logException($e);
        }

        $vmcInserts = 0;
        $vmcInsertedConditions = array();
        $vmcGrayRules = array();
        $vmcWhitelistCrossrefs = array();
        $vmcO2MExclusions = array();
        foreach ($thisSheet as $thisKey => $thisRow) {
            unset($thisConditionArr);
            // don't insert column headings or any rows with a null/empty condition
            if (($thisKey > 1) && (strlen(trim($thisRow['A'])) > 0)) {
                /*
                 * Graylist Rule pre-processing
                 */
                if (strlen(trim($thisRow['D'])) > 0) {
                    // don't even try if there's not at least a single graylist rule defined for this condition
                    if ((strlen(trim($thisRow['D'])) > 0) && (strlen(trim($thisRow['E']) && (strlen(trim($thisRow['F'])) > 0) && (strlen(trim($thisRow['G'])) > 0)) > 0)) {
                        $thisConditionArr[] = array(
                            "operator" => CoreUtils::operatorBySymbol(trim($thisRow['D'])),
                            "operand" => intval(EmsaUtils::appConceptMasterVocabId($emsaDbFactory->getConnection(), 'condition', trim($thisRow['E']))), 
                            "operator1" => CoreUtils::operatorBySymbol(trim($thisRow['F'])),
                            "operand1" => ((stripos(trim($thisRow['G']), 'any') !== false) ? -1 : intval(EmsaUtils::appConceptMasterVocabId($emsaDbFactory->getConnection(), 'condition', trim($thisRow['G'])))),
                            "collect_lbound" => trim($thisRow['H']),
                            "collect_ubound" => trim($thisRow['I'])
                        );
                    }

                    if (isset($thisConditionArr)) {
                        // prepare to insert interpretive rule into $vcl_interp_rules array for later processing
                        $vmcGrayRules[trim($thisRow['A'])][] = array("structured" => json_encode($thisConditionArr));
                    }
                }

                if (!in_array(md5(trim($thisRow['A'])), $vmcInsertedConditions)) {
                    $vmcInsertedConditions[] = md5(trim($thisRow['A']));

                    // codify 'gateway aliases' column
                    $whitelistCrossrefsRaw = array();
                    $whitelistCrossrefsRaw = explode("|", trim($thisRow['J']));
                    if (is_array($whitelistCrossrefsRaw) && (count($whitelistCrossrefsRaw) > 0)) {
                        $vmcWhitelistCrossrefs[trim($thisRow['A'])] = $whitelistCrossrefsRaw;
                    }

                    // codify 'o2m_addcmr_exclusions' column
                    $o2mExlusionsRaw = array();
                    $o2mExlusionsRaw = explode("|", trim($thisRow['Z']));
                    if (is_array($o2mExlusionsRaw) && (count($o2mExlusionsRaw) > 0)) {
                        $vmcO2MExclusions[trim($thisRow['A'])] = $o2mExlusionsRaw;
                    }

                    // codify 'valid specimen sources' column
                    $this_specimen_string = null;
                    $this_specimen_aliases = array();
                    $this_specimen_aliases_raw = array();
                    $this_specimen_aliases_raw = explode(";", trim($thisRow['M']));
                    if (is_array($this_specimen_aliases_raw) && (count($this_specimen_aliases_raw) > 0)) {
                        foreach ($this_specimen_aliases_raw as $this_specimen_alias_item) {
                            // look up id for matching specimen source from Master Value Set...
                            $getspecimen_sql = sprintf("SELECT MAX(id) AS id FROM %svocab_master_vocab WHERE category = elr.vocab_category_id('specimen') and concept ILIKE '%s'", $emsaDbSchemaPrefix, pg_escape_string(trim($this_specimen_alias_item)));
                            $getspecimen_row = @pg_fetch_object(@pg_query($host_pa, $getspecimen_sql));
                            if (intval($getspecimen_row->id) > 0) {
                                $this_specimen_aliases[] = intval($getspecimen_row->id);
                            }
                        }
                    }
                    if (is_array($this_specimen_aliases) && (count($this_specimen_aliases) > 0)) {
                        $this_specimen_string = "'".implode(";", $this_specimen_aliases)."'";
                    } else {
                        $this_specimen_string = "NULL";
                    }

                    // codify 'invalid specimen sources' column
                    $this_invalid_specimen_string = null;
                    $this_invalid_specimen_aliases = array();
                    $this_invalid_specimen_aliases_raw = array();
                    $this_invalid_specimen_aliases_raw = explode(";", trim($thisRow['N']));
                    if (is_array($this_invalid_specimen_aliases_raw) && (count($this_invalid_specimen_aliases_raw) > 0)) {
                        foreach ($this_invalid_specimen_aliases_raw as $this_invalid_specimen_alias_item) {
                            // look up id for matching specimen source from Master Value Set...
                            $getinvalidspecimen_sql = sprintf("SELECT MAX(id) AS id FROM %svocab_master_vocab WHERE category = elr.vocab_category_id('specimen') and concept ILIKE '%s'", $emsaDbSchemaPrefix, pg_escape_string(trim($this_invalid_specimen_alias_item)));
                            $getinvalidspecimen_row = @pg_fetch_object(@pg_query($host_pa, $getinvalidspecimen_sql));
                            if (intval($getinvalidspecimen_row->id) > 0) {
                                $this_invalid_specimen_aliases[] = intval($getinvalidspecimen_row->id);
                            }
                        }
                    }
                    if (is_array($this_invalid_specimen_aliases) && (count($this_invalid_specimen_aliases) > 0)) {
                        $this_invalid_specimen_string = "'".implode(";", $this_invalid_specimen_aliases)."'";
                    } else {
                        $this_invalid_specimen_string = "NULL";
                    }

                    // look up id for matching condition from Master Value Set...
                    $getmaster_sql = sprintf("SELECT MAX(id) AS id FROM %svocab_master_vocab WHERE category = elr.vocab_category_id('condition') and concept ILIKE '%s'", $emsaDbSchemaPrefix, pg_escape_string(trim($thisRow['A'])));
                    //echo $getmaster_sql."<br>";
                    $getmaster_row = @pg_fetch_object(@pg_query($host_pa, $getmaster_sql));

                    // look up id for matching Disease Category from Master Value Set...
                    $getcategory_sql = sprintf("SELECT MAX(id) AS id FROM %svocab_master_vocab WHERE category = elr.vocab_category_id('disease_category') and concept ILIKE '%s'", $emsaDbSchemaPrefix, pg_escape_string(trim($thisRow['R'])));
                    //echo $getmaster_sql."<br>";
                    $getcategory_row = @pg_fetch_object(@pg_query($host_pa, $getcategory_sql));

                    // look up id for matching Jurisdiction Override from system_districts...
                    unset($getdistrictoverride_id);
                    $getdistrictoverride_sql = sprintf("SELECT MAX(id) AS id FROM %ssystem_districts WHERE health_district ILIKE '%s'", $emsaDbSchemaPrefix, pg_escape_string(trim($thisRow['Q'])));
                    //echo $getmaster_sql."<br>";
                    $getdistrictoverride_row = @pg_fetch_object(@pg_query($host_pa, $getdistrictoverride_sql));
                    if (intval($getdistrictoverride_row->id) > 0) {
                        $getdistrictoverride_id = intval($getdistrictoverride_row->id);
                    } else {
                        $getdistrictoverride_id = -1;
                    }

                    if (intval($getmaster_row->id) > 0) {
                        //print_r ($this_row);
                        $vmcInserts++;
                        $vocab_insert_sql[] = sprintf(
                            "INSERT INTO %svocab_master_condition (
                                condition, 
                                check_xref_first, 
                                immediate_notify, 
                                require_specimen, 
                                valid_specimen, 
                                white_rule, 
                                contact_white_rule, 
                                notify_state, 
                                disease_category, 
                                district_override,
                                invalid_specimen,
                                is_initial,
                                ignore_age_rule,
                                whitelist_override,
                                allow_multi_assign,
                                ast_multi_colony,
                                bypass_oos,
                                blacklist_preliminary,
                                whitelist_ignore_case_status) 
                            VALUES 
                                (%d, %s, %s, %s, %s, %s, %s, %s, %d, %d, %s, %s, %s, %s, %s, %s, %s, %s, %s);", $emsaDbSchemaPrefix,
                                intval($getmaster_row->id),
                                ((trim($thisRow['K']) == "Yes") ? "'t'" : "'f'"),
                                ((trim($thisRow['P']) == "Yes") ? "'t'" : "'f'"),
                                ((trim($thisRow['L']) == "Yes") ? "'t'" : "'f'"),
                                $this_specimen_string,
                                ((strlen(trim($thisRow['B'])) > 0) ? "'".pg_escape_string(trim($thisRow['B']))."'" : "NULL"),
                                ((strlen(trim($thisRow['C'])) > 0) ? "'".pg_escape_string(trim($thisRow['C']))."'" : "NULL"),
                                ((trim($thisRow['O']) == "Yes") ? "'t'" : "'f'"),
                                intval($getcategory_row->id), 
                                intval($getdistrictoverride_id), 
                                $this_invalid_specimen_string,
                                ((trim($thisRow['S']) == "Initial") ? "'t'" : "'f'"),
                                ((strlen(trim($thisRow['U'])) > 0) ? "'".pg_escape_string(trim($thisRow['U']))."'" : "NULL"),
                                ((trim($thisRow['V']) == "Yes") ? "'t'" : "'f'"),
                                ((trim($thisRow['W']) == "Yes") ? "'t'" : "'f'"),
                                ((trim($thisRow['X']) == "Yes") ? "'t'" : "'f'"),
                                ((trim($thisRow['Y']) == "Yes") ? "'t'" : "'f'"),
                                ((trim($thisRow['AA']) == "Yes") ? "'t'" : "'f'"),
                                ((trim($thisRow['AB']) == "Yes") ? "'t'" : "'f'")
                                );
                    } else {
                        die ("<div class=\"import_widget ui-widget import_error ui-state-error ui-corner-all\" style=\"padding: 5px;\"><span class=\"ui-icon ui-icon-alert\" style=\"float: left; margin-right: .3em;\"></span><p style=\"margin-left: 20px;\"><strong>Could not complete vocabulary input:</strong>  Unable to prepare to insert new vocabulary.  Could not find matching Master Vocabulary entry for Master Condition item.</p></div>");
                    }
                }
            }
        }

        // Vocabulary source file has now been scanned & INSERTs prepared... time to clear out the old data
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
            die ("<div class=\"import_widget ui-widget import_error ui-state-error ui-corner-all\" style=\"padding: 5px;\"><span class=\"ui-icon ui-icon-alert\" style=\"float: left; margin-right: .3em;\"></span><p style=\"margin-left: 20px;\"><strong>Could not complete vocabulary input:</strong>  Unable to prepare to insert new vocabulary.  Errors encountered:</p><ul>" . $insert_errors . "</ul></div>");
        } else {
            // name the prepared transaction so we can COMMIT it after successful purging of old data...
            $insert_prepare_token = sprintf("insert_vocab_%s", uniqid('', true));
            $insert_prepare_stmt = sprintf("PREPARE TRANSACTION '%s';", $insert_prepare_token);
            $commit_insert_stmt = sprintf("COMMIT PREPARED '%s'", $insert_prepare_token);
            $rollback_insert_stmt = sprintf("ROLLBACK PREPARED '%s'", $insert_prepare_token);

            @pg_query($insert_prepare_stmt);

            //$found_stats = sprintf("Found %s new Master Condition records</p>", $vmc_inserts);
            //echo "<div class=\"import_widget ui-widget import_error ui-state-highlight ui-corner-all\" style=\"padding: 5px;\"><span class=\"ui-icon ui-icon-info\" style=\"float: left; margin-right: .3em;\"></span><p style=\"margin-left: 20px;\"><em style=\"color: dimgray;\">Preparing new vocabulary dataset.  ".$found_stats."</em></div>";


            // INSERT prepared transaction looks good, let's DELETE!
            $delete_rs[] = @pg_query($host_pa, "BEGIN");
                $delete_error_rs[] = pg_last_error();

            $delete_rs[] = @pg_query($host_pa, "DELETE FROM {$emsaDbSchemaPrefix}vocab_master_condition;");
                $delete_error_rs[] = pg_last_error();
            $delete_rs[] = @pg_query($host_pa, "DELETE FROM {$emsaDbSchemaPrefix}vocab_master_loinc;");
                $delete_error_rs[] = pg_last_error();
            $delete_rs[] = @pg_query($host_pa, "DELETE FROM {$emsaDbSchemaPrefix}vocab_master_organism;");
                $delete_error_rs[] = pg_last_error();

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
                die ("<div class=\"import_widget ui-widget import_error ui-state-error ui-corner-all\" style=\"padding: 5px;\"><span class=\"ui-icon ui-icon-alert\" style=\"float: left; margin-right: .3em;\"></span><p style=\"margin-left: 20px;\"><strong>Could not complete vocabulary input:</strong>  Unable to purge old Master Condition vocabulary data.  Errors encountered:</p><ul>" . $delete_errors . "</ul></div>");
            } else {
                // commit the DELETE
                @pg_query("COMMIT");
                //echo "<div class=\"import_widget ui-widget import_error ui-state-highlight ui-corner-all\" style=\"padding: 5px;\"><span class=\"ui-icon ui-icon-info\" style=\"float: left; margin-right: .3em;\"></span><p style=\"margin-left: 20px;\"><em style=\"color: dimgray;\">Old Master Condition vocabulary data successfully purged...</em></p></div>";

                // commit the previously-prepared INSERT
                @pg_query($commit_insert_stmt);
                echo "<div class=\"import_widget ui-widget import_error ui-state-highlight ui-corner-all\" style=\"padding: 5px;\"><span class=\"ui-icon ui-icon-check\" style=\"float: left; margin-right: .3em;\"></span><p style=\"margin-left: 20px;\"><strong>" . $vmcInserts . " new Master Condition vocabulary records successfully imported!</strong></p></div>";
            }

        }

    # End Master Condition ########################
    ###############################################


    ###############################################
    # Master Organism #############################
        unset($vocab_insert_sql);
        unset($insert_rs);
        unset($insert_error_rs);
        unset($delete_rs);
        unset($delete_error_rs);

        try {
            $pxls->setActiveSheetIndexByName("Master Organism");
            $thisSheet = $pxls->getActiveSheet()->toArray(null, true, false, true);
        } catch (Throwable $e) {
            $thisSheet = null;
            ExceptionUtils::logException($e);
        }

        $vmo_inserts = 0;
        $vmo_inserted_snomeds = array();
        unset($vmo_cmr_rules);
        foreach ($thisSheet as $thisKey => $thisRow) {
            unset($org_masterid_lookup);
            unset($rule_status_lookup);
            unset($thisConditionArr);
            unset($this_condition_script);
            unset($this_condition_obj);
            // don't insert column headings or any rows with a null/empty SNOMED code OR a null/empty Organism
            if (($thisKey > 1) && ((strlen(trim($thisRow['B'])) > 0) || (strlen(trim($thisRow['E'])) > 0))) {
                /*
                 * Case Management Rule pre-processing
                 */
                if (strlen(trim($thisRow['L'])) > 0) {
                    // don't even try if there's not a value defined for 'create new cmr?' in the import file for this Master LOINC
                    if ((strlen(trim($thisRow['H'])) > 0) && (strlen(trim($thisRow['I'])) > 0)) {
                        // get primary interp rule condition, if it exists
                        $thisConditionArr[] = array("operator" => CoreUtils::operatorBySymbol(trim($thisRow['H'])), "operand" => (int) VocabUtils::appResultMasterVocabIdByName($adminDbConn, trim($thisRow['I']), $authClient->getAppId(), VocabUtils::isTestResultAST(trim($thisRow['I'])))[0]);
                    }

                    if ((strlen(trim($thisRow['J'])) > 0) && (strlen(trim($thisRow['K'])) > 0)) {
                        // secondary condition (i.e. between) exists
                        $thisConditionArr[] = array("operator" => CoreUtils::operatorBySymbol(trim($thisRow['J'])), "operand" => (int) VocabUtils::appResultMasterVocabIdByName($adminDbConn, trim($thisRow['K']), $authClient->getAppId(), VocabUtils::isTestResultAST(trim($thisRow['K'])))[0]);
                    }

                    if (isset($thisConditionArr)) {
                        $this_condition_script = "(";
                        foreach ($thisConditionArr as $this_condition_obj) {
                            $this_condition_script .= "(input ".CoreUtils::operatorById($this_condition_obj['operator']) . " " . ((is_numeric($this_condition_obj['operand'])) ? $this_condition_obj['operand'] : "'".$this_condition_obj['operand']."'" ) . ") && ";
                        }
                        $this_condition_script = substr($this_condition_script, 0, strlen($this_condition_script)-4);
                        $this_condition_script .= ")";

                        if (strlen(trim($thisRow['O'])) > 0) {
                            $getstatus_sql = sprintf("SELECT MAX(id) AS id FROM %svocab_master_vocab WHERE category = elr.vocab_category_id('case') and concept ILIKE '%s'", $emsaDbSchemaPrefix, pg_escape_string(trim($thisRow['O'])));
                            //echo $getmasterorg_sql;
                            $getstatus_row = @pg_fetch_object(@pg_query($host_pa, $getstatus_sql));
                            if (intval($getstatus_row->id) > 0) {
                                $rule_status_lookup = intval($getstatus_row->id);
                            } else {
                                $rule_status_lookup = -1;
                                //$missing_vocab['Master LOINC']['Rule Status'][] = trim($this_row['O']);
                                //die ("<div class=\"import_widget ui-widget import_error ui-state-error ui-corner-all\" style=\"padding: 5px;\"><span class=\"ui-icon ui-icon-alert\" style=\"float: left; margin-right: .3em;\"></span><p style=\"margin-left: 20px;\"><strong>Could not complete vocabulary input:</strong>  Unable to prepare to insert new vocabulary.  Could not find matching Master Vocabulary entry for Master Organism-&gt;Organism item.</p></div>");
                            }
                        } else {
                            $rule_status_lookup = -1;
                        }



                        // prepare to insert interpretive rule into $vcl_interp_rules array for later processing
                        $vmo_cmr_rules[md5(trim($thisRow['B']).trim($thisRow['E']))][] = array(
                            "snomed" => trim($thisRow['B']),
                            "concept_name" => trim($thisRow['E']),
                            "allow_new_cmr" => ((trim($thisRow['L']) == "Yes") ? "'t'" : "'f'"),
                            "allow_update_cmr" => ((trim($thisRow['M']) == "Yes") ? "'t'" : "'f'"),
                            "is_surveillance" => ((trim($thisRow['N']) == "Yes") ? "'t'" : "'f'"),
                            "state_case_status_master_id" => intval($rule_status_lookup),
                            "structured" => json_encode($thisConditionArr),
                            "js" => $this_condition_script);
                    }
                }

                if (!in_array(md5(trim($thisRow['B']).trim($thisRow['E'])), $vmo_inserted_snomeds)) {
                    $vmo_inserted_snomeds[] = md5(trim($thisRow['B']).trim($thisRow['E']));

                    if (strlen(trim($thisRow['A'])) > 0) {
                        $getmastersntype_sql = sprintf("SELECT MAX(id) AS id FROM %svocab_master_vocab WHERE category = elr.vocab_category_id('snomed_category') and concept ILIKE '%s'", $emsaDbSchemaPrefix, pg_escape_string(trim($thisRow['A'])));
                        //echo $getmastercond_sql;
                        $getmastersntype_row = @pg_fetch_object(@pg_query($host_pa, $getmastersntype_sql));
                        if (intval($getmastersntype_row->id) > 0) {
                            $org_masterid_lookup['snomed_category'] = intval($getmastersntype_row->id);
                        } else {
                            $org_masterid_lookup['snomed_category'] = -1;
                            $missing_vocab['Master Organism']['SNOMED Type'][] = trim($thisRow['A']);
                            //die ("<div class=\"import_widget ui-widget import_error ui-state-error ui-corner-all\" style=\"padding: 5px;\"><span class=\"ui-icon ui-icon-alert\" style=\"float: left; margin-right: .3em;\"></span><p style=\"margin-left: 20px;\"><strong>Could not complete vocabulary input:</strong>  Unable to prepare to insert new vocabulary.  Could not find matching Master Vocabulary entry for Master Organism-&gt;Condition item.</p></div>");
                        }
                    } else {
                        $org_masterid_lookup['snomed_category'] = -1;
                    }

                    if (strlen(trim($thisRow['D'])) > 0) {
                        $getmastercond_sql = sprintf("SELECT MAX(mc.c_id) AS id FROM %svocab_master_vocab mv JOIN %svocab_master_condition mc ON mc.condition = mv.id WHERE mv.category = elr.vocab_category_id('condition') and mv.concept ILIKE '%s'", $emsaDbSchemaPrefix, $emsaDbSchemaPrefix, pg_escape_string(trim($thisRow['D'])));
                        //echo $getmastercond_sql;
                        $getmastercond_row = @pg_fetch_object(@pg_query($host_pa, $getmastercond_sql));
                        if (intval($getmastercond_row->id) > 0) {
                            $org_masterid_lookup['condition'] = intval($getmastercond_row->id);
                        } else {
                            $org_masterid_lookup['condition'] = -1;
                            $missing_vocab['Master Organism']['Condition'][] = trim($thisRow['D']);
                            //die ("<div class=\"import_widget ui-widget import_error ui-state-error ui-corner-all\" style=\"padding: 5px;\"><span class=\"ui-icon ui-icon-alert\" style=\"float: left; margin-right: .3em;\"></span><p style=\"margin-left: 20px;\"><strong>Could not complete vocabulary input:</strong>  Unable to prepare to insert new vocabulary.  Could not find matching Master Vocabulary entry for Master Organism-&gt;Condition item.</p></div>");
                        }
                    } else {
                        $org_masterid_lookup['condition'] = -1;
                    }

                    if (strlen(trim($thisRow['E'])) > 0) {
                        $getmasterorg_sql = sprintf("SELECT MAX(id) AS id FROM %svocab_master_vocab WHERE category = elr.vocab_category_id('organism') and concept ILIKE '%s'", $emsaDbSchemaPrefix, pg_escape_string(trim($thisRow['E'])));
                        //echo $getmasterorg_sql;
                        $getmasterorg_row = @pg_fetch_object(@pg_query($host_pa, $getmasterorg_sql));
                        if (intval($getmasterorg_row->id) > 0) {
                            $org_masterid_lookup['organism'] = intval($getmasterorg_row->id);
                        } else {
                            $org_masterid_lookup['organism'] = -1;
                            $missing_vocab['Master Organism']['Organism'][] = trim($thisRow['E']);
                            //die ("<div class=\"import_widget ui-widget import_error ui-state-error ui-corner-all\" style=\"padding: 5px;\"><span class=\"ui-icon ui-icon-alert\" style=\"float: left; margin-right: .3em;\"></span><p style=\"margin-left: 20px;\"><strong>Could not complete vocabulary input:</strong>  Unable to prepare to insert new vocabulary.  Could not find matching Master Vocabulary entry for Master Organism-&gt;Organism item.</p></div>");
                        }
                    } else {
                        $org_masterid_lookup['organism'] = -1;
                    }

                    if (strlen(trim($thisRow['F'])) > 0) {
                        $getlist_sql = sprintf("SELECT id FROM %ssystem_statuses WHERE type = 2 and name ILIKE '%s'", $emsaDbSchemaPrefix, pg_escape_string(trim($thisRow['F'])));
                        //echo $getmasterorg_sql;
                        $getlist_row = @pg_fetch_object(@pg_query($host_pa, $getlist_sql));
                        if (intval($getlist_row->id) > 0) {
                            $org_masterid_lookup['list'] = intval($getlist_row->id);
                        } else {
                            $org_masterid_lookup['list'] = -1;
                            $missing_vocab['Master Organism']['List'][] = trim($thisRow['F']);
                            //die ("<div class=\"import_widget ui-widget import_error ui-state-error ui-corner-all\" style=\"padding: 5px;\"><span class=\"ui-icon ui-icon-alert\" style=\"float: left; margin-right: .3em;\"></span><p style=\"margin-left: 20px;\"><strong>Could not complete vocabulary input:</strong>  Unable to prepare to insert new vocabulary.  Could not find matching Master Vocabulary entry for Master Organism-&gt;Organism item.</p></div>");
                        }
                    } else {
                        $org_masterid_lookup['list'] = -1;
                    }

                    if (strlen(trim($thisRow['G'])) > 0) {
                        $getmasterresult = (int) VocabUtils::appResultMasterVocabIdByName($adminDbConn, trim($thisRow['G']), $authClient->getAppId(), VocabUtils::isTestResultAST(trim($thisRow['G'])))[0];
                        if (intval($getmasterresult) > 0) {
                            $org_masterid_lookup['result'] = intval($getmasterresult);
                        } else {
                            $org_masterid_lookup['result'] = -1;
                            $missing_vocab['Master Organism']['Test Result'][] = trim($thisRow['G']);
                            //die ("<div class=\"import_widget ui-widget import_error ui-state-error ui-corner-all\" style=\"padding: 5px;\"><span class=\"ui-icon ui-icon-alert\" style=\"float: left; margin-right: .3em;\"></span><p style=\"margin-left: 20px;\"><strong>Could not complete vocabulary input:</strong>  Unable to prepare to insert new vocabulary.  Could not find matching Master Vocabulary entry for Master Organism-&gt;Case Status item.</p></div>");
                        }
                    } else {
                        $org_masterid_lookup['result'] = -1;
                    }

                    if (strlen(trim($thisRow['O'])) > 0) {
                        $getmasterstatus_sql = sprintf("SELECT MAX(id) AS id FROM %svocab_master_vocab WHERE category = elr.vocab_category_id('case') and concept ILIKE '%s'", $emsaDbSchemaPrefix, pg_escape_string(trim($thisRow['O'])));
                        //echo $getmasterstatus_sql;
                        $getmasterstatus_row = @pg_fetch_object(@pg_query($host_pa, $getmasterstatus_sql));
                        if (intval($getmasterstatus_row->id) > 0) {
                            $org_masterid_lookup['status'] = intval($getmasterstatus_row->id);
                        } else {
                            $org_masterid_lookup['status'] = -1;
                            $missing_vocab['Master Organism']['Case Status'][] = trim($thisRow['O']);
                            //die ("<div class=\"import_widget ui-widget import_error ui-state-error ui-corner-all\" style=\"padding: 5px;\"><span class=\"ui-icon ui-icon-alert\" style=\"float: left; margin-right: .3em;\"></span><p style=\"margin-left: 20px;\"><strong>Could not complete vocabulary input:</strong>  Unable to prepare to insert new vocabulary.  Could not find matching Master Vocabulary entry for Master Organism-&gt;Case Status item.</p></div>");
                        }
                    } else {
                        $org_masterid_lookup['status'] = -1;
                    }
                    
                    if (strlen(trim($thisRow['Q'])) > 0) {
                        if (strtolower(trim($thisRow['Q'])) == 'force semi-auto') {
                            $org_masterid_lookup['semi_auto_usage'] = "TRUE";
                        } elseif (strtolower(trim($thisRow['Q'])) == 'skip semi-auto') {
                            $org_masterid_lookup['semi_auto_usage'] = "FALSE";
                        } else {
                            $org_masterid_lookup['semi_auto_usage'] = "NULL";
                        }
                    } else {
                        $org_masterid_lookup['semi_auto_usage'] = "NULL";
                    }

                    //print_r ($this_row);
                    $vmo_inserts++;
                    $vocab_insert_sql[] = sprintf(
                        "INSERT INTO %svocab_master_organism (
                            snomed_category, 
                            condition, 
                            snomed, 
                            snomed_alt, 
                            organism, 
                            list, 
                            test_result,
                            semi_auto_usage,
                            admin_notes) 
                        VALUES 
                            (%s, %s, %s, %s, %s, %s, %s, %s, %s);", $emsaDbSchemaPrefix,
                            $org_masterid_lookup['snomed_category'],
                            $org_masterid_lookup['condition'],
                            ((strlen(trim($thisRow['B'])) > 0) ? "'".pg_escape_string(trim($thisRow['B']))."'" : "NULL"),
                            ((strlen(trim($thisRow['C'])) > 0) ? "'".pg_escape_string(trim($thisRow['C']))."'" : "NULL"),
                            $org_masterid_lookup['organism'],
                            $org_masterid_lookup['list'],
                            $org_masterid_lookup['result'],
                            $org_masterid_lookup['semi_auto_usage'],
                            ((strlen(trim($thisRow['R'])) > 0) ? "'".pg_escape_string(trim($thisRow['R']))."'" : "NULL")
                    );
                }
            }
        }

        // Vocabulary source file has now been scanned & INSERTs prepared... time to clear out the old data
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
            die ("<div class=\"import_widget ui-widget import_error ui-state-error ui-corner-all\" style=\"padding: 5px;\"><span class=\"ui-icon ui-icon-alert\" style=\"float: left; margin-right: .3em;\"></span><p style=\"margin-left: 20px;\"><strong>Could not complete vocabulary input:</strong>  Unable to prepare to insert new vocabulary.  Errors encountered:</p><ul>" . $insert_errors . "</ul></div>");
        } else {
            // name the prepared transaction so we can COMMIT it after successful purging of old data...
            $insert_prepare_token = sprintf("insert_vocab_%s", uniqid('', true));
            $insert_prepare_stmt = sprintf("PREPARE TRANSACTION '%s';", $insert_prepare_token);
            $commit_insert_stmt = sprintf("COMMIT PREPARED '%s'", $insert_prepare_token);
            $rollback_insert_stmt = sprintf("ROLLBACK PREPARED '%s'", $insert_prepare_token);

            @pg_query($insert_prepare_stmt);

            //$found_stats = sprintf("Found %s new Master Condition records</p>", $vmo_inserts);
            //echo "<div class=\"import_widget ui-widget import_error ui-state-highlight ui-corner-all\" style=\"padding: 5px;\"><span class=\"ui-icon ui-icon-info\" style=\"float: left; margin-right: .3em;\"></span><p style=\"margin-left: 20px;\"><em style=\"color: dimgray;\">Preparing new vocabulary dataset.  ".$found_stats."</em></div>";


            // INSERT prepared transaction looks good, let's DELETE!
            $delete_rs[] = @pg_query($host_pa, "BEGIN");
                $delete_error_rs[] = pg_last_error();
            $delete_rs[] = @pg_query($host_pa, "DELETE FROM {$emsaDbSchemaPrefix}vocab_master_organism;");
                $delete_error_rs[] = pg_last_error();

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
                die ("<div class=\"import_widget ui-widget import_error ui-state-error ui-corner-all\" style=\"padding: 5px;\"><span class=\"ui-icon ui-icon-alert\" style=\"float: left; margin-right: .3em;\"></span><p style=\"margin-left: 20px;\"><strong>Could not complete vocabulary input:</strong>  Unable to purge old Master Organism vocabulary data.  Errors encountered:</p><ul>" . $delete_errors . "</ul></div>");
            } else {
                // commit the DELETE
                @pg_query("COMMIT");
                //echo "<div class=\"import_widget ui-widget import_error ui-state-highlight ui-corner-all\" style=\"padding: 5px;\"><span class=\"ui-icon ui-icon-info\" style=\"float: left; margin-right: .3em;\"></span><p style=\"margin-left: 20px;\"><em style=\"color: dimgray;\">Old Master Organism vocabulary data successfully purged...</em></p></div>";

                // commit the previously-prepared INSERT
                @pg_query($commit_insert_stmt);
                echo "<div class=\"import_widget ui-widget import_error ui-state-highlight ui-corner-all\" style=\"padding: 5px;\"><span class=\"ui-icon ui-icon-check\" style=\"float: left; margin-right: .3em;\"></span><p style=\"margin-left: 20px;\"><strong>" . $vmo_inserts . " new Master Organism vocabulary records successfully imported!</strong></p></div>";
            }

        }

    # End Master Organism #########################
    ###############################################


    ###############################################
    # Master LOINC ################################
        unset($vocab_insert_sql);
        unset($insert_rs);
        unset($insert_error_rs);
        unset($delete_rs);
        unset($delete_error_rs);

        try {
            $pxls->setActiveSheetIndexByName("Master LOINC to Condition");
            $thisSheet = $pxls->getActiveSheet()->toArray(null, true, false, true);
        } catch (Throwable $e) {
            $thisSheet = null;
            ExceptionUtils::logException($e);
        }

        $vml_inserts = 0;
        $vml_inserted_loincs = array();
        unset($vml_cmr_rules);
        foreach ($thisSheet as $thisKey => $thisRow) {
            unset($loinc_masterid_lookup);
            unset($rule_status_lookup);
            unset($thisConditionArr);
            unset($this_condition_script);
            unset($this_condition_obj);
            // don't insert column headings or any rows with a null/empty loinc
            if (($thisKey > 1) && (strlen(trim($thisRow['A'])) > 0)) {
                /*
                 * Case Management Rule pre-processing
                 */
                if (strlen(trim($thisRow['N'])) > 0) {
                    // don't even try if there's not a value defined for 'create new cmr?' in the import file for this Master LOINC
                    if ((strlen(trim($thisRow['J'])) > 0) && (strlen(trim($thisRow['K'])) > 0)) {
                        // get primary interp rule condition, if it exists
                        $thisConditionArr[] = array("operator" => CoreUtils::operatorBySymbol(trim($thisRow['J'])), "operand" => (int) VocabUtils::appResultMasterVocabIdByName($adminDbConn, trim($thisRow['K']), $authClient->getAppId(), VocabUtils::isTestResultAST(trim($thisRow['K'])))[0]);
                    }

                    if ((strlen(trim($thisRow['L'])) > 0) && (strlen(trim($thisRow['M'])) > 0)) {
                        // secondary condition (i.e. between) exists
                        $thisConditionArr[] = array("operator" => CoreUtils::operatorBySymbol(trim($thisRow['L'])), "operand" => (int) VocabUtils::appResultMasterVocabIdByName($adminDbConn, trim($thisRow['M']), $authClient->getAppId(), VocabUtils::isTestResultAST(trim($thisRow['M'])))[0]);
                    }

                    if (isset($thisConditionArr)) {
                        $this_condition_script = "(";
                        foreach ($thisConditionArr as $this_condition_obj) {
                            $this_condition_script .= "(input ".CoreUtils::operatorById($this_condition_obj['operator']) . " " . ((is_numeric($this_condition_obj['operand'])) ? $this_condition_obj['operand'] : "'".$this_condition_obj['operand']."'" ) . ") && ";
                        }
                        $this_condition_script = substr($this_condition_script, 0, strlen($this_condition_script)-4);
                        $this_condition_script .= ")";

                        if (strlen(trim($thisRow['Q'])) > 0) {
                            $getstatus_sql = sprintf("SELECT MAX(id) AS id FROM %svocab_master_vocab WHERE category = elr.vocab_category_id('case') and concept ILIKE '%s'", $emsaDbSchemaPrefix, pg_escape_string(trim($thisRow['Q'])));
                            //echo $getmasterorg_sql;
                            $getstatus_row = @pg_fetch_object(@pg_query($host_pa, $getstatus_sql));
                            if (intval($getstatus_row->id) > 0) {
                                $rule_status_lookup = intval($getstatus_row->id);
                            } else {
                                $rule_status_lookup = -1;
                                //$missing_vocab['Master LOINC']['Rule Status'][] = trim($this_row['Q']);
                                //die ("<div class=\"import_widget ui-widget import_error ui-state-error ui-corner-all\" style=\"padding: 5px;\"><span class=\"ui-icon ui-icon-alert\" style=\"float: left; margin-right: .3em;\"></span><p style=\"margin-left: 20px;\"><strong>Could not complete vocabulary input:</strong>  Unable to prepare to insert new vocabulary.  Could not find matching Master Vocabulary entry for Master Organism-&gt;Organism item.</p></div>");
                            }
                        } else {
                            $rule_status_lookup = -1;
                        }



                        // prepare to insert interpretive rule into $vcl_interp_rules array for later processing
                        $vml_cmr_rules[trim($thisRow['A'])][] = array(
                            "allow_new_cmr" => ((trim($thisRow['N']) == "Yes") ? "'t'" : "'f'"),
                            "allow_update_cmr" => ((trim($thisRow['O']) == "Yes") ? "'t'" : "'f'"),
                            "is_surveillance" => ((trim($thisRow['P']) == "Yes") ? "'t'" : "'f'"),
                            "state_case_status_master_id" => intval($rule_status_lookup),
                            "structured" => json_encode($thisConditionArr),
                            "js" => $this_condition_script);
                    }
                }

                // don't insert master LOINC again if already have inserted this master LOINC
                if (!in_array(trim($thisRow['A']), $vml_inserted_loincs)) {
                    $vml_inserted_loincs[] = trim($thisRow['A']);
                    if (strlen(trim($thisRow['D'])) > 0) {
                        $getmastercond_sql = sprintf("SELECT MAX(mc.c_id) AS id FROM %svocab_master_vocab mv JOIN %svocab_master_condition mc ON mc.condition = mv.id WHERE mv.category = elr.vocab_category_id('condition') and mv.concept ILIKE '%s'", $emsaDbSchemaPrefix, $emsaDbSchemaPrefix, pg_escape_string(trim($thisRow['D'])));
                        //echo $getmastercond_sql;
                        $getmastercond_row = @pg_fetch_object(@pg_query($host_pa, $getmastercond_sql));
                        if (intval($getmastercond_row->id) > 0) {
                            $loinc_masterid_lookup['condition'] = intval($getmastercond_row->id);
                        } else {
                            $loinc_masterid_lookup['condition'] = -1;
                            $missing_vocab['Master LOINC']['Condition'][] = trim($thisRow['D']);
                            //die ("<div class=\"import_widget ui-widget import_error ui-state-error ui-corner-all\" style=\"padding: 5px;\"><span class=\"ui-icon ui-icon-alert\" style=\"float: left; margin-right: .3em;\"></span><p style=\"margin-left: 20px;\"><strong>Could not complete vocabulary input:</strong>  Unable to prepare to insert new vocabulary.  Could not find matching Master Vocabulary entry for Master LOINC-&gt;Condition item.</p></div>");
                        }
                    } else {
                        $loinc_masterid_lookup['condition'] = -1;
                    }

                    if (strlen(trim($thisRow['F'])) > 0) {
                        $getmasterorg_sql = sprintf("SELECT MAX(mo.o_id) AS id FROM %svocab_master_vocab mv JOIN %svocab_master_organism mo ON mo.organism = mv.id WHERE mv.category = elr.vocab_category_id('organism') and mv.concept ILIKE '%s'", $emsaDbSchemaPrefix, $emsaDbSchemaPrefix, pg_escape_string(trim($thisRow['F'])));
                        //echo $getmasterorg_sql;
                        $getmasterorg_row = @pg_fetch_object(@pg_query($host_pa, $getmasterorg_sql));
                        if (intval($getmasterorg_row->id) > 0) {
                            $loinc_masterid_lookup['organism'] = intval($getmasterorg_row->id);
                        } else {
                            $loinc_masterid_lookup['organism'] = -1;
                            $missing_vocab['Master LOINC']['Organism'][] = trim($thisRow['F']);
                            //die ("<div class=\"import_widget ui-widget import_error ui-state-error ui-corner-all\" style=\"padding: 5px;\"><span class=\"ui-icon ui-icon-alert\" style=\"float: left; margin-right: .3em;\"></span><p style=\"margin-left: 20px;\"><strong>Could not complete vocabulary input:</strong>  Unable to prepare to insert new vocabulary.  Could not find matching Master Vocabulary entry for Master LOINC-&gt;Organism item.</p></div>");
                        }
                    } else {
                        $loinc_masterid_lookup['organism'] = -1;
                    }

                    if (strlen(trim($thisRow['G'])) > 0) {
                        $getmastertest_sql = sprintf("SELECT MAX(id) AS id FROM %svocab_master_vocab WHERE category = elr.vocab_category_id('test_type') and concept ILIKE '%s'", $emsaDbSchemaPrefix, pg_escape_string(trim($thisRow['G'])));
                        //echo $getmastertest_sql;
                        $getmastertest_row = @pg_fetch_object(@pg_query($host_pa, $getmastertest_sql));
                        if (intval($getmastertest_row->id) > 0) {
                            $loinc_masterid_lookup['test_type'] = intval($getmastertest_row->id);
                        } else {
                            $loinc_masterid_lookup['test_type'] = -1;
                            $missing_vocab['Master LOINC']['Test Type'][] = trim($thisRow['G']);
                            //die ("<div class=\"import_widget ui-widget import_error ui-state-error ui-corner-all\" style=\"padding: 5px;\"><span class=\"ui-icon ui-icon-alert\" style=\"float: left; margin-right: .3em;\"></span><p style=\"margin-left: 20px;\"><strong>Could not complete vocabulary input:</strong>  Unable to prepare to insert new vocabulary.  Could not find matching Master Vocabulary entry for Master LOINC-&gt;Test Type item.</p></div>");
                        }
                    } else {
                        $loinc_masterid_lookup['test_type'] = -1;
                    }

                    if (strlen(trim($thisRow['H'])) > 0) {
                        $getmasterspecimen_sql = sprintf("SELECT MAX(id) AS id FROM %svocab_master_vocab WHERE category = elr.vocab_category_id('specimen') AND concept ILIKE '%s';", $emsaDbSchemaPrefix, pg_escape_string(trim($thisRow['H'])));
                        //echo $getmasterorg_sql;
                        $getmasterspecimen_row = @pg_fetch_object(@pg_query($host_pa, $getmasterspecimen_sql));
                        if (intval($getmasterspecimen_row->id) > 0) {
                            $loinc_masterid_lookup['specimen_source'] = intval($getmasterspecimen_row->id);
                        } else {
                            $loinc_masterid_lookup['specimen_source'] = -1;
                            $missing_vocab['Master LOINC']['Specimen Source'][] = trim($thisRow['H']);
                            //die ("<div class=\"import_widget ui-widget import_error ui-state-error ui-corner-all\" style=\"padding: 5px;\"><span class=\"ui-icon ui-icon-alert\" style=\"float: left; margin-right: .3em;\"></span><p style=\"margin-left: 20px;\"><strong>Could not complete vocabulary input:</strong>  Unable to prepare to insert new vocabulary.  Could not find matching Master Vocabulary entry for Master Organism-&gt;Organism item.</p></div>");
                        }
                    } else {
                        $loinc_masterid_lookup['specimen_source'] = -1;
                    }

                    if (strlen(trim($thisRow['I'])) > 0) {
                        $getlist_sql = sprintf("SELECT id FROM %ssystem_statuses WHERE type = 2 and name ILIKE '%s'", $emsaDbSchemaPrefix, pg_escape_string(trim($thisRow['I'])));
                        //echo $getmasterorg_sql;
                        $getlist_row = @pg_fetch_object(@pg_query($host_pa, $getlist_sql));
                        if (intval($getlist_row->id) > 0) {
                            $loinc_masterid_lookup['list'] = intval($getlist_row->id);
                        } else {
                            $loinc_masterid_lookup['list'] = -1;
                            $missing_vocab['Master LOINC']['List'][] = trim($thisRow['I']);
                            //die ("<div class=\"import_widget ui-widget import_error ui-state-error ui-corner-all\" style=\"padding: 5px;\"><span class=\"ui-icon ui-icon-alert\" style=\"float: left; margin-right: .3em;\"></span><p style=\"margin-left: 20px;\"><strong>Could not complete vocabulary input:</strong>  Unable to prepare to insert new vocabulary.  Could not find matching Master Vocabulary entry for Master Organism-&gt;Organism item.</p></div>");
                        }
                    } else {
                        $loinc_masterid_lookup['list'] = -1;
                    }

                    if (strlen(trim($thisRow['R'])) > 0) {
                        $getmastertest_sql = sprintf("SELECT MAX(id) AS id FROM %svocab_master_vocab WHERE category = elr.vocab_category_id('resist_test_agent') and concept ILIKE '%s'", $emsaDbSchemaPrefix, pg_escape_string(trim($thisRow['R'])));
                        //echo $getmastertest_sql;
                        $getmastertest_row = @pg_fetch_object(@pg_query($host_pa, $getmastertest_sql));
                        if (intval($getmastertest_row->id) > 0) {
                            $loinc_masterid_lookup['antimicrobial_agent'] = intval($getmastertest_row->id);
                        } else {
                            $loinc_masterid_lookup['antimicrobial_agent'] = -1;
                            $missing_vocab['Master LOINC']['Antimicrobial Agent'][] = trim($thisRow['R']);
                            //die ("<div class=\"import_widget ui-widget import_error ui-state-error ui-corner-all\" style=\"padding: 5px;\"><span class=\"ui-icon ui-icon-alert\" style=\"float: left; margin-right: .3em;\"></span><p style=\"margin-left: 20px;\"><strong>Could not complete vocabulary input:</strong>  Unable to prepare to insert new vocabulary.  Could not find matching Master Vocabulary entry for Master LOINC-&gt;Antimicrobial Agent item.</p></div>");
                        }
                    } else {
                        $loinc_masterid_lookup['antimicrobial_agent'] = -1;
                    }

                    //print_r ($this_row);
                    $vml_inserts++;
                    $vocab_insert_sql[] = sprintf(
                        "INSERT INTO %svocab_master_loinc (
                            loinc, 
                            concept_name, 
                            trisano_condition, 
                            trisano_organism, 
                            trisano_test_type, 
                            specimen_source, 
                            list, 
                            condition_from_result, 
                            organism_from_result,
                            antimicrobial_agent,
                            admin_notes) 
                        VALUES 
                            (%s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s);", $emsaDbSchemaPrefix,
                            ((strlen(trim($thisRow['A'])) > 0) ? "'".pg_escape_string(trim($thisRow['A']))."'" : "NULL"),
                            ((strlen(trim($thisRow['B'])) > 0) ? "'".pg_escape_string(trim($thisRow['B']))."'" : "NULL"),
                            $loinc_masterid_lookup['condition'],
                            $loinc_masterid_lookup['organism'],
                            $loinc_masterid_lookup['test_type'],
                            $loinc_masterid_lookup['specimen_source'],
                            $loinc_masterid_lookup['list'],
                            ((trim($thisRow['C']) == "Yes") ? "'t'" : "'f'"),
                            ((trim($thisRow['E']) == "Yes") ? "'t'" : "'f'"),
                            $loinc_masterid_lookup['antimicrobial_agent'],
                            ((strlen(trim($thisRow['S'])) > 0) ? "'".pg_escape_string(trim($thisRow['S']))."'" : "NULL")
                            );
                }
            }
        }

        // Vocabulary source file has now been scanned & INSERTs prepared... time to clear out the old data
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
            die ("<div class=\"import_widget ui-widget import_error ui-state-error ui-corner-all\" style=\"padding: 5px;\"><span class=\"ui-icon ui-icon-alert\" style=\"float: left; margin-right: .3em;\"></span><p style=\"margin-left: 20px;\"><strong>Could not complete vocabulary input:</strong>  Unable to prepare to insert new vocabulary.  Errors encountered:</p><ul>" . $insert_errors . "</ul></div>");
        } else {
            // name the prepared transaction so we can COMMIT it after successful purging of old data...
            $insert_prepare_token = sprintf("insert_vocab_%s", uniqid('', true));
            $insert_prepare_stmt = sprintf("PREPARE TRANSACTION '%s';", $insert_prepare_token);
            $commit_insert_stmt = sprintf("COMMIT PREPARED '%s'", $insert_prepare_token);
            $rollback_insert_stmt = sprintf("ROLLBACK PREPARED '%s'", $insert_prepare_token);

            @pg_query($insert_prepare_stmt);

            //$found_stats = sprintf("Found %s new Master LOINC records</p>", $vml_inserts);
            //echo "<div class=\"import_widget ui-widget import_error ui-state-highlight ui-corner-all\" style=\"padding: 5px;\"><span class=\"ui-icon ui-icon-info\" style=\"float: left; margin-right: .3em;\"></span><p style=\"margin-left: 20px;\"><em style=\"color: dimgray;\">Preparing new vocabulary dataset.  ".$found_stats."</em></div>";


            // INSERT prepared transaction looks good, let's DELETE!
            $delete_rs[] = @pg_query($host_pa, "BEGIN");
                $delete_error_rs[] = pg_last_error();
            $delete_rs[] = @pg_query($host_pa, "DELETE FROM {$emsaDbSchemaPrefix}vocab_master_loinc;");
                $delete_error_rs[] = pg_last_error();

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
                die ("<div class=\"import_widget ui-widget import_error ui-state-error ui-corner-all\" style=\"padding: 5px;\"><span class=\"ui-icon ui-icon-alert\" style=\"float: left; margin-right: .3em;\"></span><p style=\"margin-left: 20px;\"><strong>Could not complete vocabulary input:</strong>  Unable to purge old Master LOINC vocabulary data.  Errors encountered:</p><ul>" . $delete_errors . "</ul></div>");
            } else {
                // commit the DELETE
                @pg_query("COMMIT");
                //echo "<div class=\"import_widget ui-widget import_error ui-state-highlight ui-corner-all\" style=\"padding: 5px;\"><span class=\"ui-icon ui-icon-info\" style=\"float: left; margin-right: .3em;\"></span><p style=\"margin-left: 20px;\"><em style=\"color: dimgray;\">Old Master LOINC vocabulary data successfully purged...</em></p></div>";

                // commit the previously-prepared INSERT
                @pg_query($commit_insert_stmt);
                echo "<div class=\"import_widget ui-widget import_error ui-state-highlight ui-corner-all\" style=\"padding: 5px;\"><span class=\"ui-icon ui-icon-check\" style=\"float: left; margin-right: .3em;\"></span><p style=\"margin-left: 20px;\"><strong>" . $vml_inserts . " new Master LOINC vocuabulary records successfully imported!</strong></p></div>";

                if (isset($missing_vocab) && is_array($missing_vocab)) {
                    echo "<div class=\"import_widget ui-widget import_error ui-state-error ui-corner-all\" style=\"padding: 5px;\"><span class=\"ui-icon ui-icon-info\" style=\"float: left; margin-right: .3em;\"></span><p style=\"margin-left: 20px;\"><strong>Warning:</strong>  The following vocabulary items could not be found in the Master Value Set:";
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

    # End Master LOINC ############################
    ###############################################

    // if Master LOINC, now that we've added them, go back & build Case Management Rules
    if (isset($vml_cmr_rules) && is_array($vml_cmr_rules) && (count($vml_cmr_rules) > 0)) {
        unset($cmr_sql);
        $cmr_sql = "BEGIN;".PHP_EOL;
        foreach ($vml_cmr_rules as $cmr_loinc => $cmr_conditions) {
            foreach ($cmr_conditions as $cmr_condition) {
                $cmr_sql .= "INSERT INTO ".$emsaDbSchemaPrefix."vocab_rules_masterloinc (master_loinc_id, allow_new_cmr, allow_update_cmr, is_surveillance, app_id, state_case_status_master_id, conditions_structured, conditions_js) 
                    VALUES (
                        (SELECT MAX(l_id) FROM ".$emsaDbSchemaPrefix."vocab_master_loinc WHERE loinc = '".pg_escape_string(trim($cmr_loinc))."'), 
                        ".trim($cmr_condition['allow_new_cmr']).", 
                        ".trim($cmr_condition['allow_update_cmr']).", 
                        ".trim($cmr_condition['is_surveillance']).", 
                        2, 
                        ".((isset($cmr_condition['state_case_status_master_id']) && !is_null($cmr_condition['state_case_status_master_id']) && (strlen($cmr_condition['state_case_status_master_id']) > 0)) ? intval($cmr_condition['state_case_status_master_id']) : "NULL").", 
                        '".pg_escape_string($cmr_condition['structured'])."', 
                        '".pg_escape_string($cmr_condition['js'])."'
                    );".PHP_EOL;
                //$cmr_sql .= "SELECT MAX(id) FROM ".$my_db_schema."vocab_child_loinc WHERE child_loinc = '".pg_escape_string(trim($interp_loinc))."' AND lab_id = ".$clean['lab_id'].";".PHP_EOL;
            }
        }
        //$cmr_sql .= "ROLLBACK;".PHP_EOL;
        $cmr_sql .= "COMMIT;".PHP_EOL;
        pg_query($cmr_sql);
        echo "<div class=\"import_widget ui-widget import_error ui-state-highlight ui-corner-all\" style=\"padding: 5px;\"><span class=\"ui-icon ui-icon-info\" style=\"float: left; margin-right: .3em;\"></span><p style=\"margin-left: 20px;\"><em style=\"color: dimgray;\">New Master LOINC Case Management Rules loaded...</em></p></div>";

        /* debug
        echo "<pre>";
        print_r($cmr_sql);
        echo "</pre>";
        */
    }

    // if Master SNOMED, now that we've added them, go back & build Case Management Rules
    if (isset($vmo_cmr_rules) && is_array($vmo_cmr_rules) && (count($vmo_cmr_rules) > 0)) {
        unset($ocmr_sql);
        $ocmr_sql = "BEGIN;".PHP_EOL;
        foreach ($vmo_cmr_rules as $cmr_snomed => $ocmr_conditions) {
            foreach ($ocmr_conditions as $ocmr_condition) {
                if (!EmsaUtils::emptyTrim($ocmr_condition['snomed'])) {
                    // if SNOMED present, always look up ID based on SNOMED column
                    $ocmr_sql .= "INSERT INTO ".$emsaDbSchemaPrefix."vocab_rules_mastersnomed (master_snomed_id, allow_new_cmr, allow_update_cmr, is_surveillance, app_id, state_case_status_master_id, conditions_structured, conditions_js) 
                        VALUES (
                            (SELECT MAX(o_id) FROM ".$emsaDbSchemaPrefix."vocab_master_organism 
                                WHERE snomed = '".pg_escape_string(trim($ocmr_condition['snomed']))."'), 
                            ".trim($ocmr_condition['allow_new_cmr']).", 
                            ".trim($ocmr_condition['allow_update_cmr']).", 
                            ".trim($ocmr_condition['is_surveillance']).", 
                            2, 
                            ".((isset($ocmr_condition['state_case_status_master_id']) && !is_null($ocmr_condition['state_case_status_master_id']) && (strlen($ocmr_condition['state_case_status_master_id']) > 0)) ? intval($ocmr_condition['state_case_status_master_id']) : "NULL").", 
                            '".pg_escape_string($ocmr_condition['structured'])."', 
                            '".pg_escape_string($ocmr_condition['js'])."'
                        );".PHP_EOL;
                } else {
                    $ocmr_sql .= "INSERT INTO ".$emsaDbSchemaPrefix."vocab_rules_mastersnomed (master_snomed_id, allow_new_cmr, allow_update_cmr, is_surveillance, app_id, state_case_status_master_id, conditions_structured, conditions_js) 
                        VALUES (
                            (SELECT MAX(o.o_id) FROM ".$emsaDbSchemaPrefix."vocab_master_organism o 
                                INNER JOIN ".$emsaDbSchemaPrefix."vocab_master_vocab m ON (m.id = o.organism)
                                WHERE m.category = ".$emsaDbSchemaPrefix."vocab_category_id('organism')
                                AND m.concept = '".pg_escape_string(trim($ocmr_condition['concept_name']))."'
                                AND o.snomed IS NULL), 
                            ".trim($ocmr_condition['allow_new_cmr']).", 
                            ".trim($ocmr_condition['allow_update_cmr']).", 
                            ".trim($ocmr_condition['is_surveillance']).", 
                            2, 
                            ".((isset($ocmr_condition['state_case_status_master_id']) && !is_null($ocmr_condition['state_case_status_master_id']) && (strlen($ocmr_condition['state_case_status_master_id']) > 0)) ? intval($ocmr_condition['state_case_status_master_id']) : "NULL").", 
                            '".pg_escape_string($ocmr_condition['structured'])."', 
                            '".pg_escape_string($ocmr_condition['js'])."'
                        );".PHP_EOL;
                }
            }
        }
        //$cmr_sql .= "ROLLBACK;".PHP_EOL;
        $ocmr_sql .= "COMMIT;".PHP_EOL;
        pg_query($ocmr_sql);
        echo "<div class=\"import_widget ui-widget import_error ui-state-highlight ui-corner-all\" style=\"padding: 5px;\"><span class=\"ui-icon ui-icon-info\" style=\"float: left; margin-right: .3em;\"></span><p style=\"margin-left: 20px;\"><em style=\"color: dimgray;\">New Master SNOMED Case Management Rules loaded...</em></p></div>";

        /* debug
        echo "<pre>";
        print_r($cmr_sql);
        echo "</pre>";
        */
    }
    
    // if Master Condition, go back and add Whitelist Crossrefs...
    if (!empty($vmcWhitelistCrossrefs)) {
        $whitelistSql = "BEGIN;\n";
        
        foreach ($vmcWhitelistCrossrefs as $vmcWhitelistCrossrefCondition => $vmcWhitelistCrossrefData) {
            $whitelistCrossrefIDs = array();
            $this_gateway_string = null;
            
            foreach ($vmcWhitelistCrossrefData as $rawWhitelistCrossrefName) {
                // look up id for matching gateway_xref from Master Value Set...
                $getalias_sql = sprintf("SELECT MAX(c_id) AS id FROM %svocab_master_condition WHERE condition = (SELECT MAX(id) AS id FROM %svocab_master_vocab WHERE category = %svocab_category_id('condition') and concept ILIKE '%s')", $emsaDbSchemaPrefix, $emsaDbSchemaPrefix, $emsaDbSchemaPrefix, pg_escape_string(trim($rawWhitelistCrossrefName)));
                $getalias_row = @pg_fetch_object(@pg_query($host_pa, $getalias_sql));
                
                if ((int) $getalias_row->id > 0) {
                    $whitelistCrossrefIDs[] = (int) $getalias_row->id;
                }

                if (is_array($whitelistCrossrefIDs) && (count($whitelistCrossrefIDs) > 0)) {
                    $this_gateway_string = "'" . pg_escape_string(implode(";", $whitelistCrossrefIDs)) . "'";
                } else {
                    $this_gateway_string = "NULL";
                }
                
                $getalias_row = null;
            }
            
            if (!empty($this_gateway_string)) {
                $whitelistSql .= "UPDATE {$emsaDbSchemaPrefix}vocab_master_condition 
                    SET gateway_xref = {$this_gateway_string} 
                    WHERE c_id = (
                        SELECT MAX(vmc.c_id) FROM {$emsaDbSchemaPrefix}vocab_master_condition vmc 
                            INNER JOIN {$emsaDbSchemaPrefix}vocab_master_vocab mv ON (mv.id = vmc.condition) 
                            WHERE mv.concept = '" . pg_escape_string(trim($vmcWhitelistCrossrefCondition)) . "'
                    );\n";
            }
        }
        
        $whitelistSql .= "COMMIT;";
        pg_query($whitelistSql);
        echo "<div class=\"import_widget ui-widget import_error ui-state-highlight ui-corner-all\" style=\"padding: 5px;\"><span class=\"ui-icon ui-icon-info\" style=\"float: left; margin-right: .3em;\"></span><p style=\"margin-left: 20px;\"><em style=\"color: dimgray;\">Whitelist Crossrefs reloaded...</em></p></div>";
    }

    // if Master Condition, go back and add O2M Create If Not Found Exclusions...
    if (!empty($vmcO2MExclusions)) {
        $o2mSql = "BEGIN;\n";

        foreach ($vmcO2MExclusions as $vmcO2MExclusionCondition => $vmcO2MExclusionData) {
            $o2mExclusionIDs = array();
            $this_o2m_string = null;

            foreach ($vmcO2MExclusionData as $rawO2MExclusionName) {
                // look up id for matching o2m_addcmr_exclusions from Master Value Set...
                $geto2m_sql = sprintf("SELECT MAX(c_id) AS id FROM %svocab_master_condition WHERE condition = (SELECT MAX(id) AS id FROM %svocab_master_vocab WHERE category = %svocab_category_id('condition') and concept ILIKE '%s')", $emsaDbSchemaPrefix, $emsaDbSchemaPrefix, $emsaDbSchemaPrefix, pg_escape_string(trim($rawO2MExclusionName)));
                $geto2m_row = @pg_fetch_object(@pg_query($host_pa, $geto2m_sql));

                if ((int) $geto2m_row->id > 0) {
                    $o2mExclusionIDs[] = (int) $geto2m_row->id;
                }

                if (is_array($o2mExclusionIDs) && (count($o2mExclusionIDs) > 0)) {
                    $this_o2m_string = "'" . pg_escape_string(implode(";", $o2mExclusionIDs)) . "'";
                } else {
                    $this_o2m_string = "NULL";
                }

                $geto2m_row = null;
            }

            if (!empty($this_o2m_string)) {
                $o2mSql .= "UPDATE {$emsaDbSchemaPrefix}vocab_master_condition 
                    SET o2m_addcmr_exclusions = {$this_o2m_string} 
                    WHERE c_id = (
                        SELECT MAX(vmc.c_id) FROM {$emsaDbSchemaPrefix}vocab_master_condition vmc 
                            INNER JOIN {$emsaDbSchemaPrefix}vocab_master_vocab mv ON (mv.id = vmc.condition) 
                            WHERE mv.concept = '" . pg_escape_string(trim($vmcO2MExclusionCondition)) . "'
                    );\n";
            }
        }

        $o2mSql .= "COMMIT;";
        pg_query($o2mSql);
        echo "<div class=\"import_widget ui-widget import_error ui-state-highlight ui-corner-all\" style=\"padding: 5px;\"><span class=\"ui-icon ui-icon-info\" style=\"float: left; margin-right: .3em;\"></span><p style=\"margin-left: 20px;\"><em style=\"color: dimgray;\">'O2M Create If Not Found' reloaded...</em></p></div>";
    }

    // if Master Condition, now that we've added them, go back & build Graylist Rules
    if (isset($vmcGrayRules) && is_array($vmcGrayRules) && (count($vmcGrayRules) > 0)) {
        unset($gray_sql);
        $gray_sql = "BEGIN;".PHP_EOL;
        foreach ($vmcGrayRules as $gray_rule_disease => $gray_conditions) {
            foreach ($gray_conditions as $gray_condition) {
                $gray_sql .= "INSERT INTO ".$emsaDbSchemaPrefix."vocab_rules_graylist (app_id, master_condition_id, conditions_structured) 
                    VALUES (
                        2, 
                        (SELECT MAX(vmc.c_id) FROM ".$emsaDbSchemaPrefix."vocab_master_condition vmc 
                            INNER JOIN ".$emsaDbSchemaPrefix."vocab_master_vocab mv ON (mv.id = vmc.condition) 
                            WHERE mv.concept = '".pg_escape_string(trim($gray_rule_disease))."'), 
                        '".pg_escape_string($gray_condition['structured'])."'
                    );".PHP_EOL;
                //$cmr_sql .= "SELECT MAX(id) FROM ".$my_db_schema."vocab_child_loinc WHERE child_loinc = '".pg_escape_string(trim($interp_loinc))."' AND lab_id = ".$clean['lab_id'].";".PHP_EOL;
            }
        }
        //$cmr_sql .= "ROLLBACK;".PHP_EOL;
        $gray_sql .= "COMMIT;".PHP_EOL;
        pg_query($gray_sql);
        echo "<div class=\"import_widget ui-widget import_error ui-state-highlight ui-corner-all\" style=\"padding: 5px;\"><span class=\"ui-icon ui-icon-info\" style=\"float: left; margin-right: .3em;\"></span><p style=\"margin-left: 20px;\"><em style=\"color: dimgray;\">New Graylist Rules loaded...</em></p></div>";

        /* debug
        echo "<pre>";
        print_r($cmr_sql);
        echo "</pre>";
        */
    }

    echo "<div class=\"import_widget ui-widget ui-corner-all\"><p><button id=\"addanother_button\">Import Another Vocabulary List?</button></p></div>";

} else {
    // everything else except Master Condition/Organism/LOINC
    unset($vocab_insert_sql);
    unset($insert_rs);
    unset($insert_error_rs);
    unset($delete_rs);
    unset($delete_error_rs);
    foreach ($requiredTabList as $import_tab) {
        try {
            $pxls->setActiveSheetIndexByName($import_tab);
            $thisSheet = $pxls->getActiveSheet()->toArray(null, true, false, true);
        } catch (Throwable $e) {
            $thisSheet = null;
            ExceptionUtils::logException($e);
        }

        if (($clean['vocab_type'] == "mastervocab") && ($import_tab == "Sheet1")) {
            $vm_inserts = 0;
            foreach ($thisSheet as $thisKey => $thisRow) {
                // don't insert column headings or any rows with a null/empty master code
                if (($thisKey > 1) && (strlen(trim($thisRow['C'])) > 0)) {
                    // look up category id from structure_category...
                    unset($getstructurecategory_sql);
                    unset($getstructurecategory_row);
                    $getstructurecategory_sql = sprintf("SELECT MAX(id) AS id FROM %sstructure_category WHERE label ILIKE '%s'", $emsaDbSchemaPrefix, pg_escape_string(trim($thisRow['A'])));
                    //echo $getmaster_sql."<br>";
                    $getstructurecategory_row = @pg_fetch_object(@pg_query($host_pa, $getstructurecategory_sql));

                    //print_r ($this_row);
                    $vm_inserts++;
                    $vocab_insert_sql[] = sprintf(
                        "INSERT INTO %svocab_master_vocab (
                            category, 
                            codeset,
                            concept) 
                        VALUES 
                            (%s, %s, %s);", $emsaDbSchemaPrefix, 
                            intval($getstructurecategory_row->id),
                            ((strlen(trim($thisRow['B'])) > 0) ? "'".pg_escape_string(trim($thisRow['B']))."'" : "NULL"),
                            ((strlen(trim($thisRow['C'])) > 0) ? "'".pg_escape_string(trim($thisRow['C']))."'" : "NULL")
                            );
                }
            }

        } elseif (($clean['vocab_type'] == "trisano") && ($import_tab == "Sheet1")) {
            $vt_inserts = 0;
            foreach ($thisSheet as $thisKey => $thisRow) {
                // don't insert column headings or any rows with a null/empty master code
                if (($thisKey > 1) && (strlen(trim($thisRow['C'])) > 0)) {
                    $getmaster_sql = sprintf("SELECT MAX(id) AS id FROM %svocab_master_vocab WHERE category = (SELECT MAX(id) FROM %sstructure_category WHERE label ILIKE '%s') and concept = '%s'", $emsaDbSchemaPrefix, $emsaDbSchemaPrefix, pg_escape_string(trim($thisRow['B'])), pg_escape_string(trim($thisRow['C'])));
                    //echo $getmaster_sql;
                    $getmaster_row = @pg_fetch_object(@pg_query($host_pa, $getmaster_sql));
                    if (intval($getmaster_row->id) > 0) {
                        //print_r ($getmaster_row);
                        $vt_inserts++;
                        $vocab_insert_sql[] = sprintf(
                            "INSERT INTO %svocab_master2app (
                                app_id, 
                                master_id,
                                coded_value) 
                            VALUES 
                                ((SELECT id FROM %svocab_app WHERE app_name ILIKE '%s'), %s, %s);", $emsaDbSchemaPrefix, $emsaDbSchemaPrefix, 
                                trim($thisRow['A']),
                                intval($getmaster_row->id),
                                ((strlen(trim($thisRow['D'])) > 0) ? "'".pg_escape_string(trim($thisRow['D']))."'" : "NULL")
                                );
                    } else {
                        die ("<div class=\"import_widget ui-widget import_error ui-state-error ui-corner-all\" style=\"padding: 5px;\"><span class=\"ui-icon ui-icon-alert\" style=\"float: left; margin-right: .3em;\"></span><p style=\"margin-left: 20px;\"><strong>Could not complete vocabulary input:</strong>  Unable to prepare to insert new vocabulary.  Could not find matching Master Vocabulary entry for EpiTrax item.</p></div>");
                    }
                }
            }
            
        } elseif (($clean['vocab_type'] == "master-icd") && ($import_tab == "Sheet1")) {
            $icdInserts = 0;
            
            foreach ($thisSheet as $thisKey => $thisRow) {
                // don't insert column headings or any rows with a null/empty ICD code
                if (($thisKey > 1) && (strlen(trim($thisRow['B'])) > 0)) {
                    $icdInserts++;
                    $vocab_insert_sql[] = "INSERT INTO {$emsaDbSchemaPrefix}vocab_icd
                        (
                            codeset_id,
                            code_value,
                            code_description,
                            master_condition_id,
                            master_snomed_id,
                            pregnancy_status,
                            pregnancy_indicator,
                            allow_new_cmr,
                            allow_update_cmr,
                            is_surveillance
                        )
                        VALUES (
                            (SELECT id FROM {$emsaDbSchemaPrefix}vocab_codeset WHERE codeset_name = '" . pg_escape_string(trim($thisRow['A'])) . "'), 
                            '" . pg_escape_string(trim($thisRow['B'])) . "',
                            '" . pg_escape_string(trim($thisRow['C'])) . "',
                            " . Udoh\Emsa\Import\VocabImportUtils::getMasterConditionId($adminDbConn, trim($thisRow['D'])) . ",
                            " . Udoh\Emsa\Import\VocabImportUtils::getMasterSNOMEDId($adminDbConn, trim($thisRow['E']), trim($thisRow['F']), trim($thisRow['G'])) . ",
                            " . Udoh\Emsa\Import\VocabImportUtils::getBooleanExprFromString(trim($thisRow['L'])) . ",
                            " . Udoh\Emsa\Import\VocabImportUtils::getBooleanExprFromString(trim($thisRow['K'])) . ",
                            " . Udoh\Emsa\Import\VocabImportUtils::getBooleanExprFromString(trim($thisRow['H'])) . ",
                            " . Udoh\Emsa\Import\VocabImportUtils::getBooleanExprFromString(trim($thisRow['I'])) . ",
                            " . Udoh\Emsa\Import\VocabImportUtils::getBooleanExprFromString(trim($thisRow['J'])) . "
                        );";
                }
            }
            
        } elseif (($clean['vocab_type'] == "master-pfge") && ($import_tab == "Sheet1")) {
            $pfgeInserts = 0;
            
            foreach ($thisSheet as $thisKey => $thisRow) {
                // don't insert column headings or any rows with a null/empty PFGE pattern
                if (($thisKey > 1) && (strlen(trim($thisRow['A'])) > 0)) {
                    $pfgeInserts++;
                    $vocab_insert_sql[] = "INSERT INTO {$emsaDbSchemaPrefix}vocab_pfge
                        (
                            pattern,
                            master_snomed_id
                        )
                        VALUES (
                            '" . pg_escape_string(trim($thisRow['A'])) . "',
                            " . Udoh\Emsa\Import\VocabImportUtils::getMasterSNOMEDId($adminDbConn, trim($thisRow['B']), trim($thisRow['C']), trim($thisRow['D'])) . "
                        );";
                }
            }
            
        } elseif (($clean['vocab_type'] == "child") && ($import_tab == "Child LOINC")) {
            $vcl_inserts = 0;
            $vcl_inserted_loincs = array();
            unset($vcl_interp_rules);
            foreach ($thisSheet as $thisKey => $thisRow) {
                unset($thisConditionArr);
                unset($this_condition_script);
                unset($this_condition_obj);
                // don't insert column headings or any rows with a null/empty child LOINC code
                if (($thisKey > 2) && (strlen(trim($thisRow['B'])) > 0)) {
                    /*
                     * Interpretive Result Rule pre-processing
                     */
                    if (strlen(trim($thisRow['T'])) > 0) {
                        // don't even try if there's not result value defined in the import file for this Child LOINC
                        if ((strlen(trim($thisRow['P'])) > 0) && (strlen(trim($thisRow['Q'])) > 0)) {
                            // get primary interp rule condition, if it exists
                            $thisConditionArr[] = array("operator" => CoreUtils::operatorBySymbol(trim($thisRow['P'])), "operand" => trim($thisRow['Q']));
                        }

                        if ((strlen(trim($thisRow['R'])) > 0) && (strlen(trim($thisRow['S'])) > 0)) {
                            // secondary condition (i.e. between) exists
                            $thisConditionArr[] = array("operator" => CoreUtils::operatorBySymbol(trim($thisRow['R'])), "operand" => trim($thisRow['S']));
                        }

                        if (isset($thisConditionArr)) {
                            $this_condition_script = "(";
                            foreach ($thisConditionArr as $this_condition_obj) {
                                if (CoreUtils::operatorById($this_condition_obj['operator']) == 'Contains') {
                                    $this_condition_script .= "(input.indexOf('".$this_condition_obj['operand']."') != -1) && ";
                                } elseif (CoreUtils::operatorById($this_condition_obj['operator']) == 'Does Not Contain') {
                                    $this_condition_script .= "(input.indexOf('".$this_condition_obj['operand']."') == -1) && ";
                                } else {
                                    $this_condition_script .= "(input ".CoreUtils::operatorById($this_condition_obj['operator']) . " " . ((is_numeric($this_condition_obj['operand'])) ? $this_condition_obj['operand'] : "'".$this_condition_obj['operand']."'" ) . ") && ";
                                }
                            }
                            $this_condition_script = substr($this_condition_script, 0, strlen($this_condition_script)-4);
                            $this_condition_script .= ")";

                            // prepare to insert interpretive rule into $vcl_interp_rules array for later processing
                            $vcl_interp_rules[trim($thisRow['B'])][trim($thisRow['A'])][] = array(
                                "result_master_id" => (int) VocabUtils::appResultMasterVocabIdByName($adminDbConn, trim($thisRow['T']), $authClient->getAppId(), VocabUtils::isTestResultAST(trim($thisRow['T'])))[0],
                                "results_to_comments" => trim($thisRow['U']),
                                "structured" => json_encode($thisConditionArr),
                                "js" => $this_condition_script);
                        }
                    }

                    // don't insert child LOINC again if already have inserted this child LOINC
                    if (!in_array(trim($thisRow['B']).'_'.trim($thisRow['C']).'_'.trim($thisRow['A']), $vcl_inserted_loincs)) {
                        $vcl_inserted_loincs[] = trim($thisRow['B']).'_'.trim($thisRow['C']).'_'.trim($thisRow['A']);
                        if (strlen(trim($thisRow['C'])) > 0) {
                            $getmaster_sql = sprintf("SELECT MAX(l_id) AS id FROM %svocab_master_loinc WHERE loinc ILIKE '%s';", $emsaDbSchemaPrefix, pg_escape_string(trim($thisRow['C'])));
                            //echo $getmaster_sql;
                            $getmaster_row = @pg_fetch_object(@pg_query($host_pa, $getmaster_sql));
                            $this_masterloinc = intval($getmaster_row->id);
                            if ($this_masterloinc < 1) {
                                $this_masterloinc = -1;
                                $missing_vocab['Child LOINC']['Master LOINC'][] = trim($thisRow['C']);
                                //die ("<div class=\"import_widget ui-widget import_error ui-state-error ui-corner-all\" style=\"padding: 5px;\"><span class=\"ui-icon ui-icon-alert\" style=\"float: left; margin-right: .3em;\"></span><p style=\"margin-left: 20px;\"><strong>Could not complete vocabulary input:</strong>  Unable to prepare to insert new vocabulary.  Could not find matching Master LOINC entry for Child LOINC item.</p></div>");
                            }
                        } else {
                            $this_masterloinc = -1;
                        }

                        // decode result location
                        if (strlen(trim($thisRow['I'])) > 0) {
                            $getresultlocation_sql = sprintf("SELECT MAX(id) AS id FROM %svocab_master_vocab WHERE category = elr.vocab_category_id('result_type') and concept ILIKE '%s'", $emsaDbSchemaPrefix, pg_escape_string(trim($thisRow['I'])));
                            //echo $getmaster_sql;
                            $getresultlocation_row = @pg_fetch_object(@pg_query($host_pa, $getresultlocation_sql));
                            $this_resultlocation = intval($getresultlocation_row->id);
                            if ($this_resultlocation < 1) {
                                $this_resultlocation = -1;
                                $missing_vocab['Child LOINC']['Result Location'][] = trim($thisRow['I']);
                                //die ("<div class=\"import_widget ui-widget import_error ui-state-error ui-corner-all\" style=\"padding: 5px;\"><span class=\"ui-icon ui-icon-alert\" style=\"float: left; margin-right: .3em;\"></span><p style=\"margin-left: 20px;\"><strong>Could not complete vocabulary input:</strong>  Unable to prepare to insert new vocabulary.  Could not find matching Master LOINC entry for Child LOINC item.</p></div>");
                            }
                        } else {
                            $this_resultlocation = -1;
                        }

                        $this_workflow = QA_STATUS;
                        if (strlen(trim($thisRow['K'])) > 0) {
                            switch (strtolower(trim($thisRow['K']))) {
                                case "automated processing":
                                    $this_workflow = ENTRY_STATUS;
                                    break;
                                case "semi-automated entry":
                                    $this_workflow = SEMI_AUTO_STATUS;
                                    break;
                                default:
                                    $this_workflow = QA_STATUS;
                                    break;
                            }
                        }
                        
                        $thisInterpOverride = "NULL";
                        if (strlen(trim($thisRow['AA'])) > 0) {
                            if (strtolower(trim($thisRow['AA'])) == 'override quantitative') {
                                $thisInterpOverride = "TRUE";
                            } elseif (strtolower(trim($thisRow['AA'])) == 'override coded entry') {
                                $thisInterpOverride = "FALSE";
                            }
                        }

                        //print_r ($getmaster_row);
                        $vcl_inserts++;
                        $vocab_insert_sql[] = sprintf(
                            "INSERT INTO %svocab_child_loinc (
                                lab_id, 
                                master_loinc,
                                child_loinc,
                                child_orderable_test_code,
                                child_resultable_test_code,
                                child_concept_name,
                                child_alias,
                                units,
                                refrange, 
                                result_location, 
                                interpret_results, 
                                workflow, 
                                hl7_refrange, 
                                pregnancy, 
                                archived,
                                admin_notes,
                                allow_preprocessing,
                                offscale_low_result,
                                offscale_high_result,
                                interpret_override) 
                            VALUES 
                                (%d, %d, %s, %s, %s, %s, %s, %s, %s, %d, %s, %d, %s, %s, %s, %s, %s, %s, %s, %s);", $emsaDbSchemaPrefix, 
                                intval($clean['lab_id']),
                                intval($this_masterloinc),
                                ((strlen(trim($thisRow['B'])) > 0) ? "'".pg_escape_string(trim($thisRow['B']))."'" : "NULL"),
                                ((strlen(trim($thisRow['E'])) > 0) ? "'".pg_escape_string(trim($thisRow['E']))."'" : "NULL"),
                                ((strlen(trim($thisRow['F'])) > 0) ? "'".pg_escape_string(trim($thisRow['F']))."'" : "NULL"),
                                ((strlen(trim($thisRow['G'])) > 0) ? "'".pg_escape_string(trim($thisRow['G']))."'" : "NULL"),
                                ((strlen(trim($thisRow['H'])) > 0) ? "'".pg_escape_string(trim($thisRow['H']))."'" : "NULL"),
                                ((strlen(trim($thisRow['L'])) > 0) ? "'".pg_escape_string(trim($thisRow['L']))."'" : "NULL"),
                                ((strlen(trim($thisRow['N'])) > 0) ? "'".pg_escape_string(trim($thisRow['N']))."'" : "NULL"), 
                                intval($this_resultlocation),
                                ((trim($thisRow['J']) == "Yes") ? "'t'" : "'f'"), 
                                intval($this_workflow), 
                                ((strlen(trim($thisRow['O'])) > 0) ? "'".pg_escape_string(trim($thisRow['O']))."'" : "NULL"),
                                ((trim($thisRow['M']) == "Yes") ? "'t'" : "'f'"), 
                                ((trim($thisRow['A']) == "Yes") ? "'t'" : "'f'"),
                                ((strlen(trim($thisRow['W'])) > 0) ? "'".pg_escape_string(trim($thisRow['W']))."'" : "NULL"),
                                ((trim($thisRow['X']) == "Yes") ? "'t'" : "'f'"), 
                                ((strlen(trim($thisRow['Y'])) > 0) ? (int) VocabUtils::appResultMasterVocabIdByName($adminDbConn, trim($thisRow['Y']), $authClient->getAppId(), VocabUtils::isTestResultAST(trim($thisRow['Y'])))[0] : "NULL"),
                                ((strlen(trim($thisRow['Z'])) > 0) ? (int) VocabUtils::appResultMasterVocabIdByName($adminDbConn, trim($thisRow['Z']), $authClient->getAppId(), VocabUtils::isTestResultAST(trim($thisRow['Z'])))[0] : "NULL"),
                                $thisInterpOverride
                                );
                    }
                }
            }

        } elseif (($clean['vocab_type'] == "child") && ($import_tab == "Child Organism")) {
            $vco_inserts = 0;
            foreach ($thisSheet as $thisKey => $thisRow) {
                // don't insert column headings or any rows with a null/empty child code
                if (($thisKey > 2) && (strlen(trim($thisRow['A'])) > 0)) {
                    if (strlen(trim($thisRow['C'])) > 0) {
                        $getmaster_sql = sprintf("SELECT MAX(mo.o_id) AS id FROM %svocab_master_organism mo JOIN %svocab_master_vocab mv ON (mo.organism = mv.id AND mv.concept ILIKE '%s');", $emsaDbSchemaPrefix, $emsaDbSchemaPrefix, pg_escape_string(trim($thisRow['C'])));
                        //echo $getmaster_sql."<br><br>";
                        $getmaster_row = @pg_fetch_object(@pg_query($host_pa, $getmaster_sql));
                        $this_masterorganism = intval($getmaster_row->id);
                        if ($this_masterorganism < 1) {
                            $this_masterorganism = -1;
                            $missing_vocab['Child Organism']['Master Organism Name'][] = trim($thisRow['C']);
                            //die ("<div class=\"import_widget ui-widget import_error ui-state-error ui-corner-all\" style=\"padding: 5px;\"><span class=\"ui-icon ui-icon-alert\" style=\"float: left; margin-right: .3em;\"></span><p style=\"margin-left: 20px;\"><strong>Could not complete vocabulary input:</strong>  Unable to prepare to insert new vocabulary.  Could not find matching Master Organism entry for Child Organism.</p></div>");
                        }
                    } else {
                        $this_masterorganism = -1;
                    }

                    if (strlen(trim($thisRow['E'])) > 0) {
                        $getmaster_sql = sprintf("SELECT MAX(mo.o_id) AS id FROM %svocab_master_organism mo JOIN %svocab_master_vocab mv ON (mo.organism = mv.id AND mv.concept ILIKE '%s');", $emsaDbSchemaPrefix, $emsaDbSchemaPrefix, pg_escape_string(trim($thisRow['E'])));
                        //echo $getmaster_sql."<br><br>";
                        $getmaster_row = @pg_fetch_object(@pg_query($host_pa, $getmaster_sql));
                        $this_mastertestresult = intval($getmaster_row->id);
                        if ($this_mastertestresult < 1) {
                            $this_mastertestresult = -1;
                            $missing_vocab['Child Organism']['Master Test Result Name'][] = trim($thisRow['E']);
                            //die ("<div class=\"import_widget ui-widget import_error ui-state-error ui-corner-all\" style=\"padding: 5px;\"><span class=\"ui-icon ui-icon-alert\" style=\"float: left; margin-right: .3em;\"></span><p style=\"margin-left: 20px;\"><strong>Could not complete vocabulary input:</strong>  Unable to prepare to insert new vocabulary.  Could not find matching Master Organism entry for Child Organism.</p></div>");
                        }
                    } else {
                        $this_mastertestresult = -1;
                    }

                    //print_r ($getmaster_row);
                    $vco_inserts++;
                    $vocab_insert_sql[] = sprintf(
                        "INSERT INTO %svocab_child_organism (
                            lab_id, 
                            organism, 
                            test_result_id, 
                            child_code,
                            result_value, 
                            comment,
                            admin_notes) 
                        VALUES 
                            (%d, %d, %d, %s, %s, %s, %s);", $emsaDbSchemaPrefix,
                            intval($clean['lab_id']),
                            intval($this_masterorganism),
                            intval($this_mastertestresult),
                            ((strlen(trim($thisRow['A'])) > 0) ? "'".pg_escape_string(trim($thisRow['A']))."'" : "NULL"),
                            ((strlen(trim($thisRow['F'])) > 0) ? "'".pg_escape_string(trim($thisRow['F']))."'" : "NULL"),
                            ((strlen(trim($thisRow['G'])) > 0) ? "'".pg_escape_string(trim($thisRow['G']))."'" : "NULL"),
                            ((strlen(trim($thisRow['I'])) > 0) ? "'".pg_escape_string(trim($thisRow['I']))."'" : "NULL")
                            );
                }
            }

        } elseif ($import_tab == "Child Vocab") {
            $vc_inserts = 0;
            $missing_mastervalue = array();
            foreach ($thisSheet as $thisKey => $thisRow) {
                // don't insert column headings or any rows with a null/empty master code
                if (($thisKey > 2) && (strlen(trim($thisRow['C'])) > 0)) {
                    $getmaster_sql = sprintf("SELECT MAX(id) AS id FROM %svocab_master_vocab WHERE category = (SELECT MAX(id) FROM %sstructure_category WHERE label ILIKE '%s') and concept = '%s'", 
                        $emsaDbSchemaPrefix, $emsaDbSchemaPrefix, 
                        pg_escape_string(trim($thisRow['A'])), 
                        pg_escape_string(trim($thisRow['C']))
                    );
                    //echo $getmaster_sql."<br>";
                    $getmaster_row = @pg_fetch_object(@pg_query($host_pa, $getmaster_sql));
                    if (intval($getmaster_row->id) > 0) {
                        //print_r ($getmaster_row);
                        $vc_inserts++;
                        $vocab_insert_sql[] = sprintf(
                            "INSERT INTO %svocab_child_vocab (
                                lab_id,
                                master_id,
                                concept,
                                comment) 
                            VALUES 
                                (%s, %s, %s, %s);",
                                $emsaDbSchemaPrefix, 
                                $clean['lab_id'],
                                intval($getmaster_row->id),
                                ((strlen(trim($thisRow['B'])) > 0) ? "'".pg_escape_string(trim($thisRow['B']))."'" : "NULL"),
                                ((strlen(trim($thisRow['D'])) > 0) ? "'".pg_escape_string(trim($thisRow['D']))."'" : "NULL")
                                );
                    } else {
                        $missing_mastervalue[] = array(
                            "cat" => trim($thisRow['A']),
                            "needle" => trim($thisRow['C'])
                        );
                    }
                }
            }

            if (count($missing_mastervalue) > 0) {
                $missing_string = '';
                foreach ($missing_mastervalue as $missing_value) {
                    $missing_string .= $missing_value["cat"] . " =&gt; " . $missing_value["needle"] . "<br>";
                }
                die ("<div class=\"import_widget ui-widget import_error ui-state-error ui-corner-all\" style=\"padding: 5px;\"><span class=\"ui-icon ui-icon-alert\" style=\"float: left; margin-right: .3em;\"></span><p style=\"margin-left: 20px;\"><strong>Could not complete vocabulary input:</strong>  Could not find matching Master Vocabulary entries for all Child items.<br><br><em><strong>Missing items:</strong><br>" . $missing_string . "</em></p></div>");
            }
        }
    }


    // Vocabulary source file has now been scanned & INSERTs prepared... time to clear out the old data
    // Because queries are done in a transaction, errors must be collected along the way & dumped after the ROLLBACK

    // Before deleting, prepare the INSERT transaction to verify no errors (don't want to dump data if we're not going
    // to be able to insert it later!)

    $insert_rs[] = @pg_query($host_pa, "BEGIN");
    $insert_error_rs[] = pg_last_error();

    if (isset($vocab_insert_sql) && is_array($vocab_insert_sql) && (count($vocab_insert_sql) > 0)) {
        foreach ($vocab_insert_sql as $vocab_insert_exec) {
            $insert_rs[] = @pg_query($host_pa, $vocab_insert_exec);
            $insert_error_rs[] = pg_last_error();
        }
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
        die ("<div class=\"import_widget ui-widget import_error ui-state-error ui-corner-all\" style=\"padding: 5px;\"><span class=\"ui-icon ui-icon-alert\" style=\"float: left; margin-right: .3em;\"></span><p style=\"margin-left: 20px;\"><strong>Could not complete vocabulary input:</strong>  Unable to prepare to insert new vocabulary.  Errors encountered:</p><ul>" . $insert_errors . "</ul></div>");
    } else {
        // name the prepared transaction so we can COMMIT it after successful purging of old data...
        $insert_prepare_token = sprintf("insert_vocab_%s", uniqid('', true));
        $insert_prepare_stmt = sprintf("PREPARE TRANSACTION '%s';", $insert_prepare_token);
        $commit_insert_stmt = sprintf("COMMIT PREPARED '%s'", $insert_prepare_token);
        $rollback_insert_stmt = sprintf("ROLLBACK PREPARED '%s'", $insert_prepare_token);

        @pg_query($insert_prepare_stmt);

        if ($clean['vocab_type'] == "child") {
            $found_stats = sprintf("%s new Child LOINC records and %s new Child Organism records found...", $vcl_inserts, $vco_inserts);
        } elseif ($clean['vocab_type'] == "mastervocab") {
            $found_stats = sprintf("%s new Master Vocab records found...", $vm_inserts);
        } elseif ($clean['vocab_type'] == "master-icd") {
            $found_stats = sprintf("%s new ICD code records found...", $icdInserts);
        } elseif ($clean['vocab_type'] == "master-pfge") {
            $found_stats = sprintf("%s new PFGE pattern records found...", $pfgeInserts);
        } elseif ($clean['vocab_type'] == "childvocab") {
            $found_stats = sprintf("%s new Child Vocab records found...", $vc_inserts);
        } elseif ($clean['vocab_type'] == "trisano") {
            $found_stats = sprintf("%s new EpiTrax Vocab records found...", $vt_inserts);
        }
        echo "<div class=\"import_widget ui-widget import_error ui-state-highlight ui-corner-all\" style=\"padding: 5px;\"><span class=\"ui-icon ui-icon-info\" style=\"float: left; margin-right: .3em;\"></span><p style=\"margin-left: 20px;\"><em style=\"color: dimgray;\">Preparing new vocabulary dataset.  ".$found_stats."</em></div>";


        // INSERT prepared transaction looks good, let's DELETE!
        $delete_rs[] = @pg_query($host_pa, "BEGIN");
            $delete_error_rs[] = pg_last_error();

        if ($clean['vocab_type'] == "child") {
            $delete_rs[] = @pg_query($host_pa, "DELETE FROM {$emsaDbSchemaPrefix}vocab_child_organism WHERE lab_id = {$clean['lab_id']};");
                $delete_error_rs[] = pg_last_error();
            $delete_rs[] = @pg_query($host_pa, "DELETE FROM {$emsaDbSchemaPrefix}vocab_c2m_testresult WHERE child_loinc_id IN (SELECT id FROM {$emsaDbSchemaPrefix}vocab_child_loinc WHERE lab_id = {$clean['lab_id']}) AND app_id = 2;");
                $delete_error_rs[] = pg_last_error();
            $delete_rs[] = @pg_query($host_pa, "DELETE FROM {$emsaDbSchemaPrefix}vocab_child_loinc WHERE lab_id = {$clean['lab_id']};");
                $delete_error_rs[] = pg_last_error();
        } elseif ($clean['vocab_type'] == "childvocab") {
            $delete_rs[] = @pg_query($host_pa, "DELETE FROM {$emsaDbSchemaPrefix}vocab_child_vocab WHERE lab_id = {$clean['lab_id']};");
                $delete_error_rs[] = pg_last_error();
        } elseif ($clean['vocab_type'] == "master-icd") {
            $delete_rs[] = @pg_query($host_pa, "DELETE FROM {$emsaDbSchemaPrefix}vocab_icd;");
                $delete_error_rs[] = pg_last_error();
        } elseif ($clean['vocab_type'] == "master-pfge") {
            $delete_rs[] = @pg_query($host_pa, "DELETE FROM {$emsaDbSchemaPrefix}vocab_pfge;");
                $delete_error_rs[] = pg_last_error();
        } elseif ($clean['vocab_type'] == "mastervocab") {
            $delete_rs[] = @pg_query($host_pa, "DELETE FROM {$emsaDbSchemaPrefix}vocab_master2app;");
                $delete_error_rs[] = pg_last_error();
            $delete_rs[] = @pg_query($host_pa, "DELETE FROM {$emsaDbSchemaPrefix}vocab_child_vocab;");
                $delete_error_rs[] = pg_last_error();
            $delete_rs[] = @pg_query($host_pa, "DELETE FROM {$emsaDbSchemaPrefix}vocab_master_vocab;");
                $delete_error_rs[] = pg_last_error();
        } elseif ($clean['vocab_type'] == "trisano") {
            $delete_rs[] = @pg_query($host_pa, "DELETE FROM {$emsaDbSchemaPrefix}vocab_master2app WHERE app_id = 2;");
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
            die ("<div class=\"import_widget ui-widget import_error ui-state-error ui-corner-all\" style=\"padding: 5px;\"><span class=\"ui-icon ui-icon-alert\" style=\"float: left; margin-right: .3em;\"></span><p style=\"margin-left: 20px;\"><strong>Could not complete vocabulary input:</strong>  Unable to purge old vocabulary data.  Errors encountered:</p><ul>" . $delete_errors . "</ul></div>");
        } else {
            // commit the DELETE
            @pg_query("COMMIT");
            echo "<div class=\"import_widget ui-widget import_error ui-state-highlight ui-corner-all\" style=\"padding: 5px;\"><span class=\"ui-icon ui-icon-info\" style=\"float: left; margin-right: .3em;\"></span><p style=\"margin-left: 20px;\"><em style=\"color: dimgray;\">Old vocabulary data successfully purged...</em></p></div>";

            // commit the previously-prepared INSERT
            @pg_query($commit_insert_stmt);

            // if child LOINC, now that we've added them, go back & build Interpretive Rules
            if (isset($vcl_interp_rules) && is_array($vcl_interp_rules) && (count($vcl_interp_rules) > 0)) {
                unset($interp_sql);
                $interp_sql = "BEGIN;".PHP_EOL;
                foreach ($vcl_interp_rules as $interp_loinc => $interp_archive_yn) {
                    foreach ($interp_archive_yn as $interp_archive_yn_key => $interp_conditions) {
                        foreach ($interp_conditions as $interp_condition) {
                            $interp_sql .= "INSERT INTO ".$emsaDbSchemaPrefix."vocab_c2m_testresult (child_loinc_id, master_id, app_id, results_to_comments, conditions_structured, conditions_js) 
                                VALUES (
                                    (SELECT MAX(id) FROM ".$emsaDbSchemaPrefix."vocab_child_loinc WHERE (archived ".((trim($interp_archive_yn_key) == 'Yes') ? 'IS TRUE' : 'IS FALSE').") AND (child_loinc = '".pg_escape_string(trim($interp_loinc))."') AND (lab_id = ".$clean['lab_id'].")), 
                                    ".intval($interp_condition['result_master_id']).", 
                                    2, 
                                    ".((isset($interp_condition['results_to_comments']) && !is_null($interp_condition['results_to_comments']) && (strlen($interp_condition['results_to_comments']) > 0)) ? "'".pg_escape_string($interp_condition['results_to_comments'])."'" : "NULL").", 
                                    '".pg_escape_string($interp_condition['structured'])."', 
                                    '".pg_escape_string($interp_condition['js'])."'
                                );".PHP_EOL;
                            //$interp_sql .= "SELECT MAX(id) FROM ".$my_db_schema."vocab_child_loinc WHERE child_loinc = '".pg_escape_string(trim($interp_loinc))."' AND lab_id = ".$clean['lab_id'].";".PHP_EOL;
                        }
                    }
                }
                //$interp_sql .= "ROLLBACK;".PHP_EOL;
                $interp_sql .= "COMMIT;".PHP_EOL;
                @pg_query($interp_sql);
                echo "<div class=\"import_widget ui-widget import_error ui-state-highlight ui-corner-all\" style=\"padding: 5px;\"><span class=\"ui-icon ui-icon-info\" style=\"float: left; margin-right: .3em;\"></span><p style=\"margin-left: 20px;\"><em style=\"color: dimgray;\">New Result Interpretive Rules loaded...</em></p></div>";

                /* debug
                echo "<pre>";
                print_r($interp_sql);
                echo "</pre>";
                */
            }

            echo "<div class=\"import_widget ui-widget import_error ui-state-highlight ui-corner-all\" style=\"padding: 5px;\"><span class=\"ui-icon ui-icon-elrsuccess\" style=\"float: left; margin-right: .3em;\"></span><p style=\"margin-left: 20px;\"><strong>New vocabulary successfully imported!</strong></p><p><button id=\"addanother_button\">Import Another Vocabulary List?</button></p></div>";

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
}

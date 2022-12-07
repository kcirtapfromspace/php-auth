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
/* @var $adminDbConn PDO */
/* @var $appList array */

$va = new \VocabAudit($adminDbConn, $authClient);

// verify we've got a valid ID to edit
$editVocabId = 0;
$editVocabIdRaw = (int) filter_input(\INPUT_GET, 'edit_id', \FILTER_SANITIZE_NUMBER_INT);
if (is_numeric($_GET['edit_id']) && (intval(trim($_GET['edit_id'])) > 0)) {
    try {
        $validEditSql = "SELECT id 
                         FROM " . ((intval($navSubcat) !== 9) ? "vocab_master_vocab" : "vocab_child_vocab") . "
                         WHERE id = :editId;";
        $validEditStmt = $adminDbConn->prepare($validEditSql);
        $validEditStmt->bindValue(':editId', $editVocabIdRaw, \PDO::PARAM_INT);

        if (($validEditStmt->execute()) && ($validEditStmt->rowCount() === 1)) {
            $editVocabId = $editVocabIdRaw;
        }
    } catch (Throwable $e) {
        \Udoh\Emsa\Utils\ExceptionUtils::logException($e);
    }
}

if ($editVocabId <= 0) {
    \Udoh\Emsa\Utils\DisplayUtils::drawError("Cannot edit vocabulary:  Record not found", true);
}

if (isset($_GET['save_flag'])) {
    // save changes
    $changesSaved = false;

    if (intval($navSubcat) !== 9) {
        // master values
        
        // make sure rows exist in vocab_master2app for all apps and this master_id
        // in some cases, when there isn't a corresponding app value at the time the master value is created, 
        // editing won't update the row for that app since it doesn't exist yet
        \Udoh\Emsa\Utils\VocabUtils::createMissingMasterToAppVocab($adminDbConn, $editVocabId);

        $validEdit = false;
        $validValueset = isset($_GET['edit_codeset']);
        $validConcept = ((isset($_GET['edit_masterconcept'])) && (ctype_print(trim($_GET['edit_masterconcept']))) && (strlen(trim($_GET['edit_masterconcept'])) > 0));
        $validCategory = ((isset($_GET['edit_category'])) && (ctype_print(trim($_GET['edit_category']))) && (strlen(trim($_GET['edit_category'])) > 0) && (trim($_GET['edit_category']) != "-1"));
        $validAppId = ((isset($_GET['edit_appconcept'])) && (is_array($_GET['edit_appconcept'])));
        $validEdit = $validValueset && $validConcept && $validCategory && $validAppId;
        
        if ($validEdit) {
            $mvOldVals = $va->getPreviousVals($editVocabId, \VocabAudit::TABLE_MASTER_VOCAB);
            //$m2aOldVals = $va->getPreviousVals($editVocabId, \VocabAudit::TABLE_MASTER_TO_APP);
            $m2aOldVals = array();
            $m2aNewVals = array();
            
            // check to make sure all app values passed correspond to actual configured applications
            $editApps = array();
            foreach ($_GET['edit_appconcept'] as $editAppId => $editAppValue) {
                if (isset($appList[intval($editAppId)])) {
                    $editApps[intval($editAppId)] = array("app_name" => $appList[intval($editAppId)], "app_value" => $editAppValue);
                }
            }
            if (count($editApps) > 0) {
                try {
                    // transact-ify the updates to make sure stuff doesn't get out of sync between master & master2app table
                    $adminDbConn->beginTransaction();
                    
                    $mvCategory = (intval(trim($_GET['edit_category'])) > 0) ? intval(trim($_GET['edit_category'])) : null;
                    $mvCodeset = (strlen(trim($_GET['edit_codeset'])) > 0) ? trim($_GET['edit_codeset']) : null;
                    $mvConcept = trim($_GET['edit_masterconcept']);
                    $m2aCodedValue = null;
                    $currentAppId = null;
                    
                    $updateMVSql = "UPDATE vocab_master_vocab 
                                    SET category = :mvCategory, codeset = :mvCodeset, concept = :mvConcept 
                                    WHERE id = :mvId;";
                    $updateMVStmt = $adminDbConn->prepare($updateMVSql);
                    $updateMVStmt->bindValue(':mvCategory', $mvCategory, \PDO::PARAM_INT);
                    $updateMVStmt->bindValue(':mvCodeset', $mvCodeset, \PDO::PARAM_STR);
                    $updateMVStmt->bindValue(':mvConcept', $mvConcept, \PDO::PARAM_STR);
                    $updateMVStmt->bindValue(':mvId', $editVocabId, \PDO::PARAM_INT);
                    $updateMVStmt->execute();
                    
                    $updateM2ASql = "UPDATE vocab_master2app 
                                     SET coded_value = :m2aCodedValue 
                                     WHERE master_id = :mvId 
                                     AND app_id = :appId;";
                    $updateM2AStmt = $adminDbConn->prepare($updateM2ASql);
                    $updateM2AStmt->bindValue(':mvId', $editVocabId, \PDO::PARAM_INT);
                    $updateM2AStmt->bindParam(':m2aCodedValue', $m2aCodedValue, \PDO::PARAM_STR);
                    $updateM2AStmt->bindParam(':appId', $currentAppId, \PDO::PARAM_STR);
                    
                    foreach ($editApps as $thisAppId => $thisAppData) {
                        // insert app-specific values
                        $m2aCodedValue = (strlen(trim($thisAppData['app_value'])) > 0) ? trim($thisAppData['app_value']) : null;
                        $currentAppId = intval($thisAppId);
                        $updateM2AStmt->execute();
                        $m2aOldVals[intval($thisAppId)] = $va->getPreviousVals($editVocabId, \VocabAudit::TABLE_MASTER_TO_APP, intval($thisAppId));
                        $m2aNewVals[intval($thisAppId)] = $va->prepareNewValues(\VocabAudit::TABLE_MASTER_TO_APP, array('app_id' => intval($thisAppId), 'appvalue' => trim($thisAppData['app_value'])));
                    }

                    if ($adminDbConn->commit()) {
                        $changesSaved = true;

                        $va->resetAudit();
                        $va->setOldVals($mvOldVals);
                        $va->setNewVals($va->prepareNewValues(VocabAudit::TABLE_MASTER_VOCAB, array('category' => intval(trim($_GET['edit_category'])), 'valueset' => trim($_GET['edit_codeset']), 'masterconcept' => trim($_GET['edit_masterconcept']))));
                        $va->auditVocab($editVocabId, VocabAudit::TABLE_MASTER_VOCAB, VocabAudit::ACTION_EDIT);

                        foreach ($appList as $appListId => $appListName) {
                            $va->resetAudit();
                            $va->setOldVals($m2aOldVals[$appListId]);
                            $va->setNewVals($m2aNewVals[$appListId]);
                            $va->auditVocab($editVocabId, VocabAudit::TABLE_MASTER_TO_APP, VocabAudit::ACTION_EDIT);
                        }
                    } else {
                        \Udoh\Emsa\Utils\DisplayUtils::drawError("Unable to save changes to vocabulary");
                    }
                } catch (Throwable $e) {
                    \Udoh\Emsa\Utils\ExceptionUtils::logException($e);
                    $adminDbConn->rollBack();
                    \Udoh\Emsa\Utils\DisplayUtils::drawError("Unable to save changes to vocabulary");
                }
            } else {
                \Udoh\Emsa\Utils\DisplayUtils::drawError("Could not save changes:  Application(s) not found");
            }
        } else {
            \Udoh\Emsa\Utils\DisplayUtils::drawError("Could not save changes:  Not all required fields had the correct values entered.");
        }
    } else {
        // child values
        $cleanLabId = (isset($_GET['edit_child']) && ctype_digit($_GET['edit_child']) && (intval(trim($_GET['edit_child'])) > 0)) ? intval(trim($_GET['edit_child'])) : -1;
        $cleanMasterId = (isset($_GET['edit_masterid']) && ctype_digit($_GET['edit_masterid']) && (intval(trim($_GET['edit_masterid'])) > 0)) ? intval(trim($_GET['edit_masterid'])) : -1;
        $cleanChildConcept = (strlen(trim($_GET['edit_childconcept'])) > 0) ? trim($_GET['edit_childconcept']) : null;
        $cleanComment = (strlen(trim($_GET['edit_comment'])) > 0) ? trim($_GET['edit_comment']) : null;
        
        // make sure master_id & lab_id are valid values
        $validIds = false;
        $validIdsSql = "SELECT (
                            (SELECT count(id) FROM structure_labs WHERE id = :labId) * 
                            (SELECT count(id) FROM vocab_master_vocab WHERE id = :mvId)
                        ) AS validcount;";
        $validIdsStmt = $adminDbConn->prepare($validIdsSql);
        $validIdsStmt->bindValue(':labId', $cleanLabId, \PDO::PARAM_INT);
        $validIdsStmt->bindValue(':mvId', $cleanMasterId, \PDO::PARAM_INT);
        
        if ($validIdsStmt->execute() && ($validIdsStmt->rowCount() > 0)) {
            if (intval($validIdsStmt->fetchColumn(0)) > 0) {
                $validIds = true;
            }
        } else {
            \Udoh\Emsa\Utils\DisplayUtils::drawError("Could not validate Lab or Master ID", true);
        }

        if (!$validIds) {
            \Udoh\Emsa\Utils\DisplayUtils::drawError("Changes not saved:  Invalid Lab and/or Master Value selected.");
        } else {
            // update the database
            try {
                $va->resetAudit();
                $va->setOldVals($va->getPreviousVals($editVocabId, \VocabAudit::TABLE_CHILD_VOCAB));
                $va->setNewVals($va->prepareNewValues(\VocabAudit::TABLE_CHILD_VOCAB, array('lab_id' => $cleanLabId, 'master_id' => $cleanMasterId, 'child_code' => $cleanChildConcept, 'comment' => $cleanComment)));
                
                $updateSql = "UPDATE vocab_child_vocab 
                              SET lab_id = :labId, master_id = :mvId, concept = :childConcept, comment = :comment 
                              WHERE id = :cvId;";
                $updateStmt = $adminDbConn->prepare($updateSql);
                $updateStmt->bindValue(':labId', $cleanLabId, \PDO::PARAM_INT);
                $updateStmt->bindValue(':mvId', $cleanMasterId, \PDO::PARAM_INT);
                $updateStmt->bindValue(':childConcept', $cleanChildConcept, \PDO::PARAM_STR);
                $updateStmt->bindValue(':comment', $cleanComment, \PDO::PARAM_STR);
                $updateStmt->bindValue(':cvId', $editVocabId, \PDO::PARAM_INT);
                
                if ($updateStmt->execute()) {
                    $changesSaved = true;
                    $va->auditVocab($editVocabId, \VocabAudit::TABLE_CHILD_VOCAB, \VocabAudit::ACTION_EDIT);
                } else {
                    \Udoh\Emsa\Utils\DisplayUtils::drawError("Unable to save changes to vocabulary");
                }
            } catch (Throwable $e) {
                \Udoh\Emsa\Utils\ExceptionUtils::logException($e);
                \Udoh\Emsa\Utils\DisplayUtils::drawError("Unable to save changes to vocabulary");
            }
        }
    }

    if ($changesSaved) {
        \Udoh\Emsa\Utils\DisplayUtils::drawHighlight("Changes to vocabulary successfully saved!", "ui-icon-elrsuccess");
    }
} else {
    // draw 'edit' form
    ?>
    <script>
        $(function() {
            $("#edit_cancel").button({
                icon: "ui-icon-elrcancel"
            }).on("click", function(e) {
                e.preventDefault();
                var cancelAction = "<?php echo $webappBaseUrl; ?>?selected_page=6&submenu=3&cat=<?php echo $navCat; ?>&subcat=<?php echo $navSubcat; ?>";
                            window.location.href = cancelAction;
                        });

                        $("#edit_savevocab").button({
                            icon: "ui-icon-elrsave"
                        });

    <?php
    if ($navSubcat != 9) {
        // load master data to form
        try {
            $editvalsSql = "SELECT category, codeset, concept 
                             FROM vocab_master_vocab 
                             WHERE id = :vocabId;";
            $editvalsStmt = $adminDbConn->prepare($editvalsSql);
            $editvalsStmt->bindValue(':vocabId', $editVocabId, \PDO::PARAM_INT);
            if ($editvalsStmt->execute()) {
                $editvalsRow = $editvalsStmt->fetchObject();
                echo '$("#edit_category").val(' . json_encode(intval($editvalsRow->category)) . ');'."\n";
                echo '$("#edit_codeset").val(' . json_encode($editvalsRow->codeset, JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_TAG) . ');'."\n";
                echo '$("#edit_masterconcept").val(' . json_encode($editvalsRow->concept, JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_TAG) . ');'."\n";
            }
        } catch (Throwable $e) {
            \Udoh\Emsa\Utils\ExceptionUtils::logException($e);
        }
        
        foreach ($appList as $configuredAppId => $configuredAppName) {
            try {
                $editAppValueSql = "SELECT coded_value 
                                    FROM vocab_master2app 
                                    WHERE app_id = :appId 
                                    AND master_id = :vocabId;";
                $editAppValueStmt = $adminDbConn->prepare($editAppValueSql);
                $editAppValueStmt->bindValue(':appId', $configuredAppId, \PDO::PARAM_INT);
                $editAppValueStmt->bindValue(':vocabId', $editVocabId, \PDO::PARAM_INT);
                $editAppValueStmt->execute();
                if ($editAppValueStmt->rowCount() > 0) {
                    $editAppValueRow = $editAppValueStmt->fetchObject();
                    echo '$("#edit_appconcept_' . intval($configuredAppId) .'").val(' . json_encode($editAppValueRow->coded_value, JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_TAG) . ');'."\n";
                }
            } catch (Throwable $e) {
                \Udoh\Emsa\Utils\ExceptionUtils::logException($e);
            }
        }
    } else {
        // load child data to form
        try {
            $editvalsSql = "SELECT cv.lab_id AS lab_id, cv.concept AS concept, cv.master_id AS master_id, mv.category AS category, cv.comment AS comment 
                            FROM vocab_child_vocab cv 
                            JOIN vocab_master_vocab mv ON (cv.master_id = mv.id) 
                            WHERE cv.id = :vocabId;";
            $editvalsStmt = $adminDbConn->prepare($editvalsSql);
            $editvalsStmt->bindValue(':vocabId', $editVocabId, \PDO::PARAM_INT);
            if ($editvalsStmt->execute()) {
                $editvalsRow = $editvalsStmt->fetchObject();
                echo '$("#edit_child").val(' . json_encode(intval($editvalsRow->lab_id)) . ');'."\n";
                echo '$("#edit_childconcept").val(' . json_encode($editvalsRow->concept, JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_TAG) . ');'."\n";
                echo '$("#edit_comment").val(' . json_encode($editvalsRow->comment, JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_TAG) . ');'."\n";
                echo '$("#edit_mastercat").val(' . json_encode(intval($editvalsRow->category)) . ');'."\n";
                echo '$("#edit_mastercat").trigger(\'change\');'."\n";
                echo '$("#edit_masterid").val(' . json_encode(intval($editvalsRow->master_id)) . ');'."\n";
            }
        } catch (Throwable $e) {
            \Udoh\Emsa\Utils\ExceptionUtils::logException($e);
        }
    }
    ?>
                    });
    </script>

    <h1 class="elrhdg"><span class="ui-icon ui-icon-header ui-icon-emsadictionary"></span><?php echo $_SESSION[EXPORT_SERVERNAME]["vocab_valueset_params"][$_SESSION[EXPORT_SERVERNAME]["vocab_valueset_params"]["vocab"]]["vocab_verbose"]; ?> Editor</h1>

    <div id="edit_form" class="edit_vocab_form ui-widget ui-widget-content ui-corner-all">
        <div style="clear: both;"><span class="emsa_form_heading">Edit <?php echo $_SESSION[EXPORT_SERVERNAME]["vocab_valueset_params"][$_SESSION[EXPORT_SERVERNAME]["vocab_valueset_params"]["vocab"]]["vocab_verbose"]; ?>:</span><br><br></div>
        <form id="edit_vocab_form" method="GET">
            <?php
            if ($navSubcat != 9) {
                // Add New Master Vocab form
                ?>
                <div class="addnew_field_vs"><label class="vocab_add_form" for="edit_category">Category:</label><br>
                    <select class="ui-corner-all" name="edit_category" id="edit_category">
                        <option value="-1" selected>--</option>
                        <?php
                        // get list of vocab categories
                        try {
                            $newCategoryStmt = $adminDbConn->query("SELECT id, label FROM structure_category ORDER BY label;");
                            while ($newCategoryRow = $newCategoryStmt->fetchObject()) {
                                echo '<option value="' . intval($newCategoryRow->id) . '">' . \Udoh\Emsa\Utils\DisplayUtils::xSafe($newCategoryRow->label) . '</option>'."\n";
                            }
                        } catch (Throwable $e) {
                            \Udoh\Emsa\Utils\ExceptionUtils::logException($e);
                            \Udoh\Emsa\Utils\DisplayUtils::drawError("Unable to retrieve list of Master Categories.", true);
                        }
                        ?>
                    </select>
                </div>
                <div class="addnew_field_vs"><label class="vocab_add_form" for="edit_codeset">Value Set Code:</label><br><input class="ui-corner-all" type="text" name="edit_codeset" id="edit_codeset" style="width: 90%;" /></div>
                <div class="addnew_field_vs"><label class="vocab_add_form" for="edit_masterconcept">Master Concept Name:</label><br><input class="ui-corner-all" type="text" name="edit_masterconcept" id="edit_masterconcept" style="width: 90%;" /></div>

                <?php
                // draw app-specific value input for each configured app
                foreach ($appList as $configuredAppId => $configuredAppName) {
                    echo '<div class="addnew_field_vs"><label class="vocab_add_form" for="edit_appconcept_' . intval($configuredAppId) . '">' . \Udoh\Emsa\Utils\DisplayUtils::xSafe($configuredAppName) . ' Value:</label><br><input class="ui-corner-all" type="text" name="edit_appconcept[' . intval($configuredAppId) . ']" id="edit_appconcept_' . intval($configuredAppId) . '" style="width: 90%;" /></div>';
                }
            } else {
                // Add New Child Vocab form
                ?>

                <div class="addnew_field_vs"><label class="vocab_add_form" for="edit_child">Lab:</label><br>
                    <select class="ui-corner-all" name="edit_child" id="edit_child">
                        <option value="-1" selected>--</option>
                        <?php
                        // get list of top-level labs for alias menu
                        try {
                            $newChildStmt = $adminDbConn->query("SELECT id, ui_name FROM structure_labs WHERE alias_for < 1 ORDER BY ui_name;");
                            while ($newChildRow = $newChildStmt->fetchObject()) {
                                echo '<option value="' . intval($newChildRow->id) . '">' . \Udoh\Emsa\Utils\DisplayUtils::xSafe($newChildRow->ui_name) . '</option>'."\n";
                            }
                        } catch (Throwable $e) {
                            \Udoh\Emsa\Utils\ExceptionUtils::logException($e);
                            \Udoh\Emsa\Utils\DisplayUtils::drawError("Unable to retrieve list of Labs.", true);
                        }
                        ?>
                    </select>
                </div>
                <div class="addnew_field_vs"><label class="vocab_add_form" for="edit_childconcept">Child Code:</label><br><input class="ui-corner-all" type="text" name="edit_childconcept" id="edit_childconcept" /></div>
                <div class="addnew_field_vs"><label class="vocab_add_form" for="edit_mastercat">Master Category:</label>
                    <select class="ui-corner-all" name="edit_mastercat" id="edit_mastercat">
                        <option value="-1" selected>--</option>
                        <?php
                        // get list of vocab categories
                        try {
                            $newCategoryStmt = $adminDbConn->query("SELECT id, label FROM structure_category ORDER BY label;");
                            while ($newCategoryRow = $newCategoryStmt->fetchObject()) {
                                echo '<option value="' . intval($newCategoryRow->id) . '">' . \Udoh\Emsa\Utils\DisplayUtils::xSafe($newCategoryRow->label) . '</option>'."\n";
                            }
                        } catch (Throwable $e) {
                            \Udoh\Emsa\Utils\ExceptionUtils::logException($e);
                            \Udoh\Emsa\Utils\DisplayUtils::drawError("Unable to retrieve list of Master Categories.", true);
                        }
                        ?>
                    </select>
                    <br><span class="ui-icon ui-icon-arrowreturnthick-1-e" style="float: left; margin-left: 30px; margin-top: 3px;"></span><label class="vocab_search_form2" for="edit_masterid">Master Concept Name:</label>
                    <select class="ui-corner-all" name="edit_masterid" id="edit_masterid" style="width: auto; max-width: 60%; min-width: 10%;">
                        <option value="-1" selected>--</option>
                        <?php
                        // get list of master vocab items by category
                        try {
                            $newMasterValuesStmt = $adminDbConn->query("SELECT id, category, concept FROM vocab_master_vocab ORDER BY category, concept;");
                            while ($newMasterValuesRow = $newMasterValuesStmt->fetchObject()) {
                                $temp_master[intval($newMasterValuesRow->category)][intval($newMasterValuesRow->id)] = htmlspecialchars($newMasterValuesRow->concept);
                            }
                        } catch (Throwable $e) {
                            \Udoh\Emsa\Utils\ExceptionUtils::logException($e);
                            \Udoh\Emsa\Utils\DisplayUtils::drawError("Unable to retrieve Master Vocabulary.", true);
                        }
                        ?>
                    </select>

                    <script type="text/javascript">
                        var masterList = <?php echo json_encode($temp_master); ?>;

                        $("#edit_mastercat").on("change", function() {
                            var selectedCat = $("#edit_mastercat").val();

                            $("#edit_masterid").empty();
                            if (selectedCat != -1) {
                                $.each(masterList[selectedCat], function(id, label) {
                                    $("#edit_masterid").append($("<option />").val(id).text(label));
                                });
                            }
                            var master_id_list = $("#edit_masterid option");

                            // major kudos to the fine folks at http://stackoverflow.com/questions/45888/what-is-the-most-efficient-way-to-sort-an-html-selects-options-by-value-while for this elegant option list sorting solution!!!
                            // p.s. -- Sortable arrays, but no associative keys.  Associative object properties, but object properties can't be sorted... really, JavaScript?  Thanks for the help.</sarcasm>
                            master_id_list.sort(function(a, b) {
                                if (a.text > b.text)
                                    return 1;
                                else if (a.text < b.text)
                                    return -1;
                                else
                                    return 0;
                            });
                            $("#edit_masterid").empty();
                            $("#edit_masterid").append($("<option />").val(-1).text("--"));
                            $("#edit_masterid").append(master_id_list);
                            $("#edit_masterid").val(-1);
                        });
                    </script>
                </div>
                <div class="addnew_field_vs"><label class="vocab_add_form" for="edit_comment">Append to Comments:</label><br><input class="ui-corner-all" type="text" name="edit_comment" id="edit_comment" /></div>
                <?php
            }
            ?>
            <input type="hidden" name="selected_page" value="<?php echo intval($navSelectedPage); ?>" />
            <input type="hidden" name="submenu" value="<?php echo intval($navSubmenu); ?>" />
            <input type="hidden" name="cat" value="<?php echo intval($navCat); ?>" />
            <input type="hidden" name="subcat" value="<?php echo intval($navSubcat); ?>" />
            <input type="hidden" name="edit_id" value="<?php echo intval($editVocabId); ?>" />
            <input type="hidden" name="save_flag" value="1" />
            <br><br><button type="submit" name="edit_savevocab" id="edit_savevocab">Save Changes</button>
            <button type="button" name="edit_cancel" id="edit_cancel">Cancel</button>
        </form>
    </div>

    <div id="vocab_log" class="edit_vocab_form ui-widget ui-state-highlight ui-widget-content ui-corner-all">
        <div style="clear: both;"><span class="emsa_form_heading">Audit Log</span><br><br></div>
            <?php
            if (intval($navSubcat) !== 9) {
                // Master Dictionary
                echo $va->displayVocabAuditById(intval($editVocabId), array(VocabAudit::TABLE_MASTER_VOCAB, VocabAudit::TABLE_MASTER_TO_APP));
            } else {
                // Child Dictionary
                echo $va->displayVocabAuditById(intval($editVocabId), array(VocabAudit::TABLE_CHILD_VOCAB));
            }
            ?>
    </div>

    <?php
    exit();
    // don't show the rest of the Value Set page
}
?>
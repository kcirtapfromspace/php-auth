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

	########## Session Prep ##########
	$_SESSION[EXPORT_SERVERNAME]["structure_params"]["structure"] = 5;
	
	/*
	 * check for session freshness since last update to session-stored filter info
	 * (filters are stored in session data, this hack gives us a way to force
	 * session data to be refreshed without forcing users to clear cookies if the data
	 * is updated mid-session, so that the current filters are used)
	 */
	$modelLastUpdated = filemtime("manage/structure_hl7.php");
	
	// check "freshness date"...
	if (isset($_SESSION[EXPORT_SERVERNAME]['structure_model_fresh'][$_SESSION[EXPORT_SERVERNAME]["structure_params"]["structure"]])) {
		if ($_SESSION[EXPORT_SERVERNAME]['structure_model_fresh'][$_SESSION[EXPORT_SERVERNAME]["structure_params"]["structure"]] < $modelLastUpdated) {
			// old model data; unset structure_params & set a new "freshness date"...
			unset($_SESSION[EXPORT_SERVERNAME]["structure_params"][$_SESSION[EXPORT_SERVERNAME]["structure_params"]["structure"]]);
			$_SESSION[EXPORT_SERVERNAME]['structure_model_fresh'][$_SESSION[EXPORT_SERVERNAME]["structure_params"]["structure"]] = time();
		}
	} else {
		// hack for sessions set before "freshness date" implemented
		unset($_SESSION[EXPORT_SERVERNAME]["structure_params"][$_SESSION[EXPORT_SERVERNAME]["structure_params"]["structure"]]);
		$_SESSION[EXPORT_SERVERNAME]['structure_model_fresh'][$_SESSION[EXPORT_SERVERNAME]["structure_params"]["structure"]] = time();
	}
	
	
	// define filters
	$_SESSION[EXPORT_SERVERNAME]["structure_params"][$_SESSION[EXPORT_SERVERNAME]["structure_params"]["structure"]]['filter_cols'] = array(
		"lab" => array("colname" => "h.lab_id", "label" => "Reporter", "filter" => TRUE, "textsearch" => FALSE, "filterlookup" => TRUE, "has_id" => TRUE, "lookupqry" => sprintf("SELECT sl.id AS value, sl.ui_name AS label FROM %sstructure_labs sl WHERE sl.alias_for < 1 ORDER BY sl.ui_name;", $emsaDbSchemaPrefix), "filtercolname" => "lab_id"), 
		"hl7_version" => array("colname" => "h.message_version", "label" => "Message Version", "display" => TRUE, "filter" => TRUE, "textsearch" => FALSE, "filterlookup" => TRUE, "has_id" => FALSE, "lookupqry" => "SELECT DISTINCT message_version AS value, message_version AS label FROM ".$emsaDbSchemaPrefix."structure_path_mirth ORDER BY message_version;", "filtercolname" => "message_version"),
		"master_path" => array("colname" => "p.xpath", "label" => "Master XML Path", "display" => TRUE, "filter" => TRUE, "textsearch" => TRUE, "filterlookup" => TRUE, "has_id" => TRUE, "lookupqry" => sprintf("SELECT DISTINCT id AS value, element||' ('||xpath||')' AS label FROM %sstructure_path ORDER BY 2;", $emsaDbSchemaPrefix), "filtercolname" => "master_path_id"), 
		"hl7_path" => array("colname" => "h.xpath", "label" => "HL7 XML Path", "display" => TRUE, "filter" => FALSE, "textsearch" => TRUE)
	);
	
	
	/*
	 * Search/Filter Prep
	 * 
	 * this must happen after setting structure defaults, otherwise condition can occur where setting query params can
	 * fool the sysetm into thinking default structure data exists when it doesn't in cases of a linked query
	 */
	// pre-build our structure-specific search data...
	if (isset($_GET['q'])) {
		if (!empty($_GET['q'])) {
			$_SESSION[EXPORT_SERVERNAME]["structure_params"][$_SESSION[EXPORT_SERVERNAME]["structure_params"]["structure"]]["q_display"] = trim(filter_input(INPUT_GET, 'q', FILTER_SANITIZE_STRING));
			$_SESSION[EXPORT_SERVERNAME]["structure_params"][$_SESSION[EXPORT_SERVERNAME]["structure_params"]["structure"]]["q_sql"] = trim(filter_input(INPUT_GET, 'q', FILTER_DEFAULT, FILTER_FLAG_STRIP_LOW|FILTER_FLAG_STRIP_HIGH));
			if (!isset($_GET['f'])) {
				// search query found, but no filters selected
				// if any filters were previously SESSIONized, they've been deselected via the UI, so we'll clear them...
				unset($_SESSION[EXPORT_SERVERNAME]["structure_params"][$_SESSION[EXPORT_SERVERNAME]["structure_params"]["structure"]]["filters"]);
			}
		} else {
			// search field was empty/defaulted, so we'll destroy the saved search params...
			$_SESSION[EXPORT_SERVERNAME]["structure_params"][$_SESSION[EXPORT_SERVERNAME]["structure_params"]["structure"]]["q_display"] = null;
			$_SESSION[EXPORT_SERVERNAME]["structure_params"][$_SESSION[EXPORT_SERVERNAME]["structure_params"]["structure"]]["q_sql"] = null;
			// not only was search blank, but no filters selected, so clear them as well...
			unset($_SESSION[EXPORT_SERVERNAME]["structure_params"][$_SESSION[EXPORT_SERVERNAME]["structure_params"]["structure"]]["filters"]);
		}
	} else {
        if (!isset($_SESSION[EXPORT_SERVERNAME]["structure_params"][$_SESSION[EXPORT_SERVERNAME]["structure_params"]["structure"]]["q_display"])) {
            $_SESSION[EXPORT_SERVERNAME]["structure_params"][$_SESSION[EXPORT_SERVERNAME]["structure_params"]["structure"]]["q_display"] = null;
        }
    }
	
	// update SESSIONized filters or destroy them if no filters are selected...
	if (isset($_GET['f'])) {
		if (is_array($_GET['f'])) {
			$_SESSION[EXPORT_SERVERNAME]["structure_params"][$_SESSION[EXPORT_SERVERNAME]["structure_params"]["structure"]]["filters"] = filter_input(INPUT_GET, 'f', FILTER_DEFAULT, FILTER_FORCE_ARRAY);
		}
	}
	
	
	// sanitize input data...
	unset($cleanChildXMLStructureParams);
	
    $cleanChildXMLStructureParams['clone_from_lab_id'] = (int) filter_input(INPUT_POST, 'clone_from_lab_id', FILTER_SANITIZE_NUMBER_INT);
    $cleanChildXMLStructureParams['clone_to_lab_id'] = (int) filter_input(INPUT_POST, 'clone_to_lab_id', FILTER_SANITIZE_NUMBER_INT);
    $cleanChildXMLStructureParams['delete_id'] = (int) filter_input(INPUT_GET, 'delete_id', FILTER_SANITIZE_NUMBER_INT);
    $cleanChildXMLStructureParams['edit_id'] = (int) filter_input(INPUT_POST, 'edit_id', FILTER_SANITIZE_NUMBER_INT);
    
    $cleanChildXMLStructureParams['add_flag'] = (int) filter_input(INPUT_POST, 'add_flag', FILTER_SANITIZE_NUMBER_INT);
    
    $cleanChildXMLStructureParams['onboard_preview'] = (int) filter_input(INPUT_POST, 'onboard_preview', FILTER_SANITIZE_NUMBER_INT);
    $cleanChildXMLStructureParams['onboard_pre_flag'] = (int) filter_input(INPUT_POST, 'onboard_pre_flag', FILTER_SANITIZE_NUMBER_INT);
    $cleanChildXMLStructureParams['onboard_flag'] = (int) filter_input(INPUT_POST, 'onboard_flag', FILTER_SANITIZE_NUMBER_INT);
    $cleanChildXMLStructureParams['onboard_lab_id'] = (int) filter_input(INPUT_POST, 'onboard_lab_id', FILTER_SANITIZE_NUMBER_INT);
    $cleanChildXMLStructureParams['onboard_message_version'] = trim((string) filter_input(INPUT_POST, 'onboard_message_version', FILTER_SANITIZE_STRING));
    $cleanChildXMLStructureParams['onboard_path_arr'] = filter_input(INPUT_POST, 'onboard_paths', FILTER_DEFAULT, FILTER_FORCE_ARRAY);
    
    $onboardMessageArr = filter_input(INPUT_POST, 'onboard_message', FILTER_DEFAULT, FILTER_FORCE_ARRAY);
    
    if (is_array($onboardMessageArr) && (count($onboardMessageArr) > 0)) {
		foreach ($onboardMessageArr as $onboardMessage) {
			if (strlen(trim($onboardMessage)) > 0) {
				$cleanChildXMLStructureParams['onboard_message'][] = str_replace('^~\\\\&', '^~\\&', trim($onboardMessage));
			}
		}
	}
    
	$cleanChildXMLStructureParams['new']['lab_id'] = (int) filter_input(INPUT_POST, 'new_lab_id', FILTER_SANITIZE_NUMBER_INT);
    $cleanChildXMLStructureParams['new']['master_xpath'] = (int) filter_input(INPUT_POST, 'new_master_xpath', FILTER_SANITIZE_NUMBER_INT);
    $cleanChildXMLStructureParams['new']['sequence'] = (int) filter_input(INPUT_POST, 'new_sequence', FILTER_SANITIZE_NUMBER_INT);
    $cleanChildXMLStructureParams['new']['xpath'] = trim((string) filter_input(INPUT_POST, 'new_xpath', FILTER_DEFAULT, FILTER_FLAG_STRIP_LOW|FILTER_FLAG_STRIP_HIGH));
    $cleanChildXMLStructureParams['new']['message_version'] = trim((string) filter_input(INPUT_POST, 'new_message_version', FILTER_SANITIZE_STRING));
    $cleanChildXMLStructureParams['new']['glue_string'] = (string) filter_input(INPUT_POST, 'new_glue', FILTER_DEFAULT, FILTER_FLAG_STRIP_LOW|FILTER_FLAG_STRIP_HIGH);  // don't trim glue string; allows for separators such as a blank space
    
    $cleanChildXMLStructureParams['edit']['lab_id'] = (int) filter_input(INPUT_POST, 'edit_lab_id', FILTER_SANITIZE_NUMBER_INT);
    $cleanChildXMLStructureParams['edit']['master_xpath'] = (int) filter_input(INPUT_POST, 'edit_master_xpath', FILTER_SANITIZE_NUMBER_INT);
    $cleanChildXMLStructureParams['edit']['sequence'] = (int) filter_input(INPUT_POST, 'edit_sequence', FILTER_SANITIZE_NUMBER_INT);
    $cleanChildXMLStructureParams['edit']['xpath'] = trim((string) filter_input(INPUT_POST, 'edit_xpath', FILTER_DEFAULT, FILTER_FLAG_STRIP_LOW|FILTER_FLAG_STRIP_HIGH));
    $cleanChildXMLStructureParams['edit']['message_version'] = trim((string) filter_input(INPUT_POST, 'edit_message_version', FILTER_SANITIZE_STRING));
    $cleanChildXMLStructureParams['edit']['glue_string'] = (string) filter_input(INPUT_POST, 'edit_glue', FILTER_DEFAULT, FILTER_FLAG_STRIP_LOW|FILTER_FLAG_STRIP_HIGH);  // don't trim glue string; allows for separators such as a blank space
?>

<script>
	$(function() {
		$("#clone_button").button({
            icon: "ui-icon-elrcopy"
        }).on("click", function() {
			$("#clone_form").show();
			$("#addnew_form").hide();
			$("#addnew_button").show();
			$("#onboard_form").hide();
            $("#onboard_cda_form").hide();
			$("#onboard_button").show();
            $("#onboard_cda_button").show();
			$(".import_error").hide();
			$("#new_element").trigger("focus");
			$(this).hide();
		});
		
		$("#addnew_button").button({
            icon: "ui-icon-elrplus"
        }).on("click", function() {
			$("#addnew_form").show();
			$("#clone_form").hide();
			$("#clone_button").show();
			$("#onboard_form").hide();
            $("#onboard_cda_form").hide();
			$("#onboard_button").show();
            $("#onboard_cda_button").show();
			$(".import_error").hide();
			$("#new_element").trigger("focus");
			$(this).hide();
		});
		
		$("#onboard_button").button({
            icon: "ui-icon-elrimport-small"
        }).on("click", function() {
			$("#onboard_form").show();
            $("#onboard_cda_form").hide();
			$("#clone_form").hide();
			$("#clone_button").show();
            $("#onboard_cda_button").show();
			$("#addnew_form").hide();
			$("#addnew_button").show();
			$(".import_error").hide();
			$("#new_element").trigger("focus");
			$(this).hide();
			//var onboardAction = "<?php echo $webappBaseUrl; ?>?selected_page=6&submenu=4&cat=2&subcat=5&onboard=1&lab_id="+$(this).val();
			//window.location.href = onboardAction;
		});
		
		$("#onboard_cda_button").button({
            icon: "ui-icon-elrimport-small"
        }).on("click", function() {
			$("#onboard_cda_form").show();
            $("#onboard_form").hide();
			$("#clone_form").hide();
			$("#clone_button").show();
            $("#onboard_button").show();
			$("#addnew_form").hide();
			$("#addnew_button").show();
			$(".import_error").hide();
			$("#new_element").trigger("focus");
			$(this).hide();
			//var onboardAction = "<?php echo $webappBaseUrl; ?>?selected_page=6&submenu=4&cat=2&subcat=5&onboard=1&lab_id="+$(this).val();
			//window.location.href = onboardAction;
		});
		
		$("#onboard_cancel").button({
			icon: "ui-icon-elrcancel"
		}).on("click", function() {
			$("#onboard_form").hide();
			$("#onboard_button").show();
		});
		
		$("#onboard_cda_cancel").button({
			icon: "ui-icon-elrcancel"
		}).on("click", function() {
			$("#onboard_cda_form").hide();
			$("#onboard_cda_button").show();
		});
		
		$("#onboard_save").button({
            icon: "ui-icon-elrsave"
        });
		
		$("#onboard_cda_save").button({
            icon: "ui-icon-elrsave"
        });
		
		$("#addnew_cancel").button({
			icon: "ui-icon-elrcancel"
		}).on("click", function() {
			$("#addnew_form").hide();
			$("#addnew_button").show();
		});
		
		$("#new_savelab").button({
            icon: "ui-icon-elrsave"
        });
		
		$("#clone_cancel").button({
			icon: "ui-icon-elrcancel"
		}).on("click", function() {
			$("#clone_form").hide();
			$("#clone_button").show();
		});
		
		$("#clone_savelab").button({
            icon: "ui-icon-elrsave"
        });
		
		$(".edit_lab").button({
            icon: "ui-icon-elrpencil",
            showLabel: false
        }).next().button({
            icon: "ui-icon-elrclose",
            showLabel: false
        }).parent().controlgroup();
		
		$("#confirm_delete_dialog").dialog({
			autoOpen: false,
			modal: true,
			draggable: false,
			resizable: false
		});
		
		$(".delete_lab").on("click", function(e) {
			e.preventDefault();
			var deleteAction = "<?php echo $webappBaseUrl; ?>?selected_page=6&submenu=4&cat=2&subcat=5&delete_id="+$(this).val();


			$("#confirm_delete_dialog").dialog('option', 'buttons', {
					"Delete" : function() {
						window.location.href = deleteAction;
						},
					"Cancel" : function() {
						$(this).dialog("close");
						}
					});

			$("#confirm_delete_dialog").dialog("open");

		});
		
		$("#edit_lab_dialog").dialog({
			autoOpen: false,
			width: 800,
			modal: true
		});
		
		$(".edit_lab").on("click", function(e) {
			e.preventDefault();
			var jsonObj = JSON.parse($(this).val());
			
			if (jsonObj.element_id) {
				$("#edit_id").val(jsonObj.element_id);
				$("#edit_lab_id").val(jsonObj.lab_id);
				$("#edit_message_version").val(jsonObj.version);
				$("#edit_xpath").val(jsonObj.xpath);
				$("#edit_master_xpath").val(jsonObj.master_xpath);
				$("#edit_glue").val(jsonObj.glue);
				$("#edit_sequence").val(jsonObj.sequence);
				
				$("#edit_lab_dialog").dialog('option', 'buttons', {
						"Save Changes" : function() {
							$(this).dialog("close");
							$("#edit_modal_form").trigger("submit");
							},
						"Cancel" : function() {
							$(this).dialog("close");
							}
						});

				$("#edit_lab_dialog").dialog("open");
			} else {
				return false;
			}
			
		});
		
		$("#onboard_finish").button({
            icon: "ui-icon-elrsave"
        });
		
		$("#toggle_filters").button({
            icon: "ui-icon-triangle-1-n",
            iconPosition: "end"
        }).on("click", function() {
			$(".vocab_filter").toggle("blind");
			var objIcon = $(this).button("option", "icon");
			if (objIcon === "ui-icon-triangle-1-s") {
				$(this).button("option", "icon", "ui-icon-triangle-1-n");
				$(this).button("option", "iconPosition", "end");
				$(this).button("option", "label", "Hide Filters");
				$("#addnew_form").hide();
				$("#addnew_button").show();
			} else {
				$(this).button("option", "icon", "ui-icon-triangle-1-s");
				$(this).button("option", "iconPosition", "end");
				$(this).button("option", "label", "Show Filters");
			}
		});
		
		$(".vocab_filter").hide();
		$("#toggle_filters").button("option", "icon", "ui-icon-triangle-1-s");
		$("#toggle_filters").button("option", "iconPosition", "end");
		$("#toggle_filters").button("option", "label", "Show Filters");
		
		$("#clear_filters").button({
            icon: "ui-icon-elrcancel"
        }).on("click", function() {
            let searchForm = $("#search_form");
            let msListBoxes = $("ul.vocab_filter_checklist[role=listbox]");
            $(".pseudo_select").removeAttr("checked");
			$(".pseudo_select_label").removeClass("pseudo_select_on");
			msListBoxes.removeAttr("aria-activedescendant");
            msListBoxes.children().removeClass("multiselect-focused");
            msListBoxes.children().attr("aria-selected", "false");
            msListBoxes.find("input[type=checkbox]").prop("checked", false);
			searchForm[0].reset();
            $("#q").val("").trigger("blur");
            searchForm.trigger("submit");
		});
		
		$("#q_go").button({
			icon: "ui-icon-elrsearch"
		}).on("click", function(){
			$("#search_form").trigger("submit");
		});
		
		$("#apply_filters").button({
			icon: "ui-icon-elroptions"
		}).on("click", function(){
			$("#search_form").trigger("submit");
		});
		
	});
</script>

<?php

	/**
	 * Process Step 1 of onboarding a new message type...
	 * Display all found paths, and give the user the option to pre-enter data, then pass to step 2.
	 * Requires lab ID and Hl7 message
	 */
	if (($cleanChildXMLStructureParams['onboard_lab_id'] > 0) && ($cleanChildXMLStructureParams['onboard_pre_flag'] > 0) && isset($cleanChildXMLStructureParams['onboard_message']) && !empty($cleanChildXMLStructureParams['onboard_message_version'])) {
		$onboard = array();
        
        if ($cleanChildXMLStructureParams['onboard_pre_flag'] === 1) {
            $mirthClient = null;
            // valid lab ID, message version & message, onboard flag is set... time to parse the message & onboard the new structure

            // get back Mirth XML from HL7 message
            try {
                $mirthClient = new \Udoh\Emsa\Client\MirthServiceClient();
                
                foreach ($cleanChildXMLStructureParams['onboard_message'] as $clean_onboard_message_key => $clean_onboard_message) {
                    $acceptMessageXml = null;
                    $acceptMessageXml = $mirthClient->acceptMessage($clean_onboard_message);
                    
                    if (!is_null($acceptMessageXml)) {
                        $onboard['xml'][] = $acceptMessageXml;
                    } else {
                        \Udoh\Emsa\Utils\DisplayUtils::drawError("No Mirth XML returned from Web Service.  Please check to ensure that you have provided a well-formed HL7 message and try again.", true);
                    }
                }
            } catch (Throwable $e) {
                \Udoh\Emsa\Utils\ExceptionUtils::logException($e);
                \Udoh\Emsa\Utils\DisplayUtils::drawError($e->getMessage());
            }
        } elseif ($cleanChildXMLStructureParams['onboard_pre_flag'] === 2) {
            foreach ($cleanChildXMLStructureParams['onboard_message'] as $clean_onboard_message_key => $clean_onboard_message) {
                $onboard['xml'][] = simplexml_load_string(str_replace('xmlns="urn:hl7-org:v3"', '', $clean_onboard_message));
            }
        }
		
		echo "<h1 class=\"elrhdg\"><span class=\"ui-icon ui-icon-header ui-icon-elrxml\"></span>HL7 Structure</h1>";
		
		if ($cleanChildXMLStructureParams['onboard_preview'] === 1) {
			echo "<div class='h3'>XML Preview</div>";
			foreach ($onboard['xml'] as $preview_xml) {
				echo "<textarea class=\"ui-corner-all\" style=\"padding: 5px; font-family: 'Consolas'; font-weight: bold; width: 90%; height: 30em; color: darkred;\">".htmlentities(\Udoh\Emsa\Utils\DisplayUtils::formatXml(trim($preview_xml->asXML())))."</textarea><br><br>";
			}
			exit;
		} else {
			$sortableHtmlRows = array();
			echo '<div class="h3">Onboarding - Step 2:  Discovered XML Paths</div>';
			\Udoh\Emsa\Utils\DisplayUtils::drawHighlight('<strong style="font-weight: 700 !important; font-size: 1.1em;">Important!</strong><br>Completing this step will immediately replace all prior mappings for this Reporting Facility & Message Version with those specified below!<br>Only HL7 paths mapped to a selected Master XML Path will be included.  All unmapped paths will be discarded.', 'ui-icon-elrerror');
			echo '<form id="new_lab_form" method="POST" action="' . $webappBaseUrl . '?selected_page=' . (int) $navSelectedPage . '&submenu=' . (int) $navSubmenu . '&cat=' . (int) $navCat . '&subcat=' . (int) $navSubcat . '">';
            echo '<input type="hidden" name="onboard_message_version" value="' . Udoh\Emsa\Utils\DisplayUtils::xSafe($cleanChildXMLStructureParams['onboard_message_version']) . '">';
            echo '<input type="hidden" name="onboard_lab_id" value="' . (int) $cleanChildXMLStructureParams['onboard_lab_id'] . '">';
            echo '<input type="hidden" name="onboard_flag" value="1">';
			echo '<table id="labResults"><thead><tr><th>Source XML Path</th><th>Example Values</th><th>Master XML Path</th><th>Concat String</th><th>Sequence</th></tr></thead><tbody>';
			
			
			if (isset($onboard['xml']) && is_array($onboard['xml']) && (count($onboard['xml']) > 0)) {
				unset($path_counter, $onboard['version'], $onboard['paths'], $onboard['found_paths']);
				// set message version
				$onboard['version'] = $cleanChildXMLStructureParams['onboard_message_version'];
				$path_counter = 1;
				
				// parse through XML document & get all the XPaths for end-nodes
				foreach ($onboard['xml'] as $onboard_xml_key => $onboard_xml) {
					unset($dxml);
					$dxml = dom_import_simplexml($onboard_xml);
					foreach ($dxml->getElementsByTagName("*") as $node) {
						unset($this_nodepath);
						$node_has_children = false;
						// loop through child nodes of given element and see if any of them contain more XML element nodes
						foreach($node->childNodes as $childNode) {
							if (intval($childNode->nodeType) == 1) { 
								$node_has_children = $node_has_children || true;
							}
						}
                        
						if (($cleanChildXMLStructureParams['onboard_pre_flag'] === 2) || ($node_has_children === false)) {
                            // include all paths for CDA documents for attribute access
                            //$onboard['paths'][] = $node->getNodePath();
							$this_nodepath = $node->getNodePath();
							$this_nodepath = preg_replace('/\[\w+\]/', '', $this_nodepath);  // remove repeating element indices from xpaths to only get unique xpaths
                            
                            if (($cleanChildXMLStructureParams['onboard_pre_flag'] === 2) && $node->hasAttributes()) {
                                //$this_nodepath .= '{has attrib}';
                            } else {
                                $this_nodepath .= '/text()';
                            }
                            
                            // excluded anything in SFT, TQ1, or ZLR segments; not used, cuts down on number of paths to manage
                            $thisNodeExcluded = false;
							if ((stripos($this_nodepath, '/SFT/') !== false) || (stripos($this_nodepath, '/ZLR/') !== false) || (stripos($this_nodepath, '/TQ1/') !== false)) {
                                $thisNodeExcluded = true;
                            }
                            
                            if (!$thisNodeExcluded) {
                                if ($node_has_children) {
                                    // if node has nested nodes, don't store nodeValue
                                    if (isset($onboard['found_paths'][$this_nodepath]) && is_array($onboard['found_paths'][$this_nodepath])) {
                                        // value already set, do nothing
                                    } else {
                                        $onboard['found_paths'][$this_nodepath][] = null;
                                    }
                                } else {
                                    if (isset($onboard['found_paths'][$this_nodepath]) && is_array($onboard['found_paths'][$this_nodepath]) && in_array(trim($node->nodeValue), $onboard['found_paths'][$this_nodepath])) {
                                        // value already set, do nothing
                                    } else {
                                        $onboard['found_paths'][$this_nodepath][] = trim($node->nodeValue);
                                    }
                                }
                            }
						}
					}
				}
					
				if (isset($onboard['found_paths']) && is_array($onboard['found_paths'])) {
					//ksort($onboard['found_paths']);
					
					// get previously-mapped vals, if any, and try to preserve them (or allow for editing)
					$previous_map_sql = 'SELECT xpath, master_path_id, glue_string, sequence 
						FROM '.$emsaDbSchemaPrefix.'structure_path_mirth 
						WHERE lab_id = '.intval($cleanChildXMLStructureParams['onboard_lab_id']).' 
						AND message_version = \''.@pg_escape_string($cleanChildXMLStructureParams['onboard_message_version']).'\';';
					$previous_map_rs = @pg_query($host_pa, $previous_map_sql);
					$previousMap = array();
					if ($previous_map_rs !== false) {
						while ($previous_map_row = @pg_fetch_object($previous_map_rs)) {
							if (isset($previousMap[trim($previous_map_row->xpath)][trim(intval($previous_map_row->master_path_id))])) {
								// if this xpath already exists and is mapped to the same master path (i.e. duplicate paths), don't overwrite
							} else {
								$previousMap[trim($previous_map_row->xpath)][trim(intval($previous_map_row->master_path_id))] = array(
									'glue_string' => trim($previous_map_row->glue_string), 
									'sequence' => intval($previous_map_row->sequence), 
									'master_path_id' => intval($previous_map_row->master_path_id)
								);
							}
						}
					}
					
					foreach ($onboard['found_paths'] as $hl7_nodepath => $hl7_nodepath_vals) {
						$thisHtmlString = '';
						$thisHtmlString .= "<tr><td style='font-family: Consolas !important;' valign=\"top\">" . str_replace("/", "<wbr>/", \Udoh\Emsa\Utils\DisplayUtils::xSafe($hl7_nodepath)) . "<input type=\"hidden\" name=\"onboard_paths[".$path_counter."][hl7path]\" value=\"".$hl7_nodepath."\"></td>";
						$thisHtmlString .= "<td valign=\"top\" ><ul style=\"margin-left: 20px;\">";
						foreach ($hl7_nodepath_vals as $node_value) {
							$thisHtmlString .= '<li style="list-style-type: square; margin-bottom: 0.5em;">'.htmlentities($node_value, ENT_QUOTES, "UTF-8").'</li>';
						}
						$thisHtmlString .= "</ul></td>";
						$thisHtmlString .= "<td valign=\"top\" >";
						// master xml path drop-down...
						$thisHtmlString .= "<select class=\"ui-corner-all\" name=\"onboard_paths[".$path_counter."][masterpath]\" id=\"onboard_paths_".$path_counter."_masterpath\"><option value=\"0\" selected>--</option>";
						
						// get list of master xpaths for menu
						$masterpaths_sql = sprintf("SELECT DISTINCT id, xpath FROM %sstructure_path ORDER BY xpath;", $emsaDbSchemaPrefix);
						$masterpaths_rs = @pg_query($host_pa, $masterpaths_sql) or \Udoh\Emsa\Utils\DisplayUtils::drawError("Unable to retrieve list of Master XPaths.", true);
						$thisMasterPathId = -1;
						while ($masterpaths_row = pg_fetch_object($masterpaths_rs)) {
							if (($thisMasterPathId < 1) && isset($previousMap[trim($hl7_nodepath)][trim(intval($masterpaths_row->id))])) {
								$thisHtmlString .= '<option value="'.intval($masterpaths_row->id).'" selected>'.htmlentities($masterpaths_row->xpath).'</option>';
								$thisMasterPathId = intval($masterpaths_row->id);
							} else {
								$thisHtmlString .= '<option value="'.intval($masterpaths_row->id).'">'.htmlentities($masterpaths_row->xpath).'</option>';
							}
						}
						pg_free_result($masterpaths_rs);
						
						$thisHtmlString .= '</select>';
						$thisHtmlString .= '</td>';
						$thisHtmlString .= '<td valign="top" ><input class="ui-corner-all" type="text" name="onboard_paths['.$path_counter.'][concat]" id="onboard_paths_'.$path_counter.'_concat" value="'.((isset($previousMap[trim($hl7_nodepath)][trim($thisMasterPathId)])) ? htmlspecialchars(trim($previousMap[trim($hl7_nodepath)][trim($thisMasterPathId)]['glue_string'])) : '').'"/></td>';
						$thisHtmlString .= '<td valign="top" ><input class="ui-corner-all" type="text" name="onboard_paths['.$path_counter.'][sequence]" id="onboard_paths_'.$path_counter.'_sequence" placeholder="1" value="'.((isset($previousMap[trim($hl7_nodepath)][trim($thisMasterPathId)])) ? intval($previousMap[trim($hl7_nodepath)][trim($thisMasterPathId)]['sequence']) : '').'"/></td>';
						$thisHtmlString .= '</tr>';
						
						$sortableHtmlRows[$hl7_nodepath][] = $thisHtmlString;
						
						if ($thisMasterPathId > 0) {
							unset($previousMap[trim($hl7_nodepath)][trim($thisMasterPathId)]);
						}
						
						if (isset($previousMap[trim($hl7_nodepath)]) && (count($previousMap[trim($hl7_nodepath)]) < 1)) {
							unset($previousMap[trim($hl7_nodepath)]);  // if nothing else previously mapped for this xpath, remove from list
						}
						
						$path_counter++;
					}
				}
				
				if (isset($previousMap) && is_array($previousMap) && (count($previousMap) > 0)) {
					foreach ($previousMap as $preExistXPath => $preExistMapping) {
						foreach ($preExistMapping as $preExistMasterPathId => $preExistMappingData) {
							if (intval($preExistMasterPathId) > 0) {
								$thisHtmlString = '';
								$thisHtmlString .= "<tr><td style='font-family: Consolas !important;' valign=\"top\">" . str_replace("/", "<wbr>/", \Udoh\Emsa\Utils\DisplayUtils::xSafe($preExistXPath)) . "<input type=\"hidden\" name=\"onboard_paths[".$path_counter."][hl7path]\" value=\"".$preExistXPath."\"></td>";
								if (isset($onboard['found_paths'][$preExistXPath])) {
									$thisHtmlString .= "<td valign=\"top\" ><ul style=\"margin-left: 20px;\">";
									foreach ($onboard['found_paths'][$preExistXPath] as $node_value) {
										$thisHtmlString .= '<li style="list-style-type: square; margin-bottom: 0.5em;">'.htmlentities($node_value, ENT_QUOTES, "UTF-8").'</li>';
									}
									$thisHtmlString .= "</ul></td>";
								} else {
									$thisHtmlString .= '<td valign="top" style="font-style: italic; color: dodgerblue;">Path previously mapped, but not found in this sample HL7 message.  To remove, un-set the Master XML Path.</td>';
								}
								$thisHtmlString .= "<td valign=\"top\" >";
								// master xml path drop-down...
								$thisHtmlString .= "<select class=\"ui-corner-all\" name=\"onboard_paths[".$path_counter."][masterpath]\" id=\"onboard_paths_".$path_counter."_masterpath\"><option value=\"0\" selected>--</option>";

								// get list of master xpaths for menu
								$masterpaths_sql = sprintf("SELECT DISTINCT id, xpath FROM %sstructure_path ORDER BY xpath;", $emsaDbSchemaPrefix);
								$masterpaths_rs = @pg_query($host_pa, $masterpaths_sql) or \Udoh\Emsa\Utils\DisplayUtils::drawError("Unable to retrieve list of Master XPaths.", true);
								$thisMasterPathId = -1;
								while ($masterpaths_row = pg_fetch_object($masterpaths_rs)) {
									if (($thisMasterPathId < 1) && isset($previousMap[trim($preExistXPath)][trim(intval($masterpaths_row->id))])) {
										$thisHtmlString .= '<option value="'.intval($masterpaths_row->id).'" selected>'.htmlentities($masterpaths_row->xpath).'</option>';
										$thisMasterPathId = intval($masterpaths_row->id);
									} else {
										$thisHtmlString .= '<option value="'.intval($masterpaths_row->id).'">'.htmlentities($masterpaths_row->xpath).'</option>';
									}
								}
								pg_free_result($masterpaths_rs);

								$thisHtmlString .= '</select>';
								$thisHtmlString .= '</td>';
								$thisHtmlString .= '<td valign="top" ><input class="ui-corner-all" type="text" name="onboard_paths['.$path_counter.'][concat]" id="onboard_paths_'.$path_counter.'_concat" value="'.((isset($previousMap[trim($preExistXPath)][trim($thisMasterPathId)])) ? htmlspecialchars(trim($previousMap[trim($preExistXPath)][trim($thisMasterPathId)]['glue_string'])) : '').'"/></td>';
								$thisHtmlString .= '<td valign="top" ><input class="ui-corner-all" type="text" name="onboard_paths['.$path_counter.'][sequence]" id="onboard_paths_'.$path_counter.'_sequence" placeholder="1" value="'.((isset($previousMap[trim($preExistXPath)][trim($thisMasterPathId)])) ? intval($previousMap[trim($preExistXPath)][trim($thisMasterPathId)]['sequence']) : '').'"/></td>';
								$thisHtmlString .= '</tr>';
								
								$sortableHtmlRows[$preExistXPath][] = $thisHtmlString;
								
								$path_counter++;
							}
						}
					}
				
				}
				
				//ksort($sortableHtmlRows, SORT_NATURAL);
				
				foreach ($sortableHtmlRows as $sortableHtmlRow) {
					foreach ($sortableHtmlRow as $htmlRow) {
						echo $htmlRow.PHP_EOL;
					}
				}
				
				echo "</tbody></table>";
				
				echo "<br><button type=\"submit\" id=\"onboard_finish\">Save New HL7 Structure Mapping</button><br><br>";
				echo "</form>";
				exit;
			
			} else {
				\Udoh\Emsa\Utils\DisplayUtils::drawError("Unable to receive Mirth XML from web service.");
			}
		}
	}
	
	/*
	 * Process Step 2 of onboarding a new message type...
	 * Requires lab ID, message version and onboard_paths array
	 */
	if (($cleanChildXMLStructureParams['onboard_lab_id'] > 0) && ($cleanChildXMLStructureParams['onboard_flag'] === 1) && !empty($cleanChildXMLStructureParams['onboard_path_arr']) && !empty($cleanChildXMLStructureParams['onboard_message_version'])) {
        // make sure we got at least one XPath back
		if (is_array($cleanChildXMLStructureParams['onboard_path_arr']) && (count($cleanChildXMLStructureParams['onboard_path_arr']) > 0)) {
			// remove old structure for this lab & version if it exists, then insert new structure
			$onboard_insert_sql = "BEGIN;\n";
			$onboard_insert_sql .= sprintf("DELETE FROM ONLY %sstructure_path_mirth WHERE lab_id = %d AND message_version = '%s';\n", $emsaDbSchemaPrefix, $cleanChildXMLStructureParams['onboard_lab_id'], pg_escape_string(trim($cleanChildXMLStructureParams['onboard_message_version'])));
			foreach ($cleanChildXMLStructureParams['onboard_path_arr'] as $op_key => $op_vals) {
				if (isset($op_vals['masterpath']) && !is_null($op_vals['masterpath']) && filter_var($op_vals['masterpath'], FILTER_VALIDATE_INT) && (intval($op_vals['masterpath']) > 0)) {
					// only insert new HL7 xpaths if mapped to master path
					$onboard_insert_sql .= sprintf("INSERT INTO %sstructure_path_mirth (lab_id, message_version, master_path_id, glue_string, xpath, sequence) VALUES (%d, '%s', %s, %s, '%s', %s);\n", 
						$emsaDbSchemaPrefix,
						$cleanChildXMLStructureParams['onboard_lab_id'],
						pg_escape_string(trim($cleanChildXMLStructureParams['onboard_message_version'])),
						((isset($op_vals['masterpath']) && !is_null($op_vals['masterpath']) && filter_var($op_vals['masterpath'], FILTER_VALIDATE_INT) && (intval($op_vals['masterpath']) > 0)) ? intval($op_vals['masterpath']) : "NULL"),
						((isset($op_vals['concat']) && !is_null($op_vals['concat']) && (strlen(trim($op_vals['concat'])) > 0)) ? "'".pg_escape_string(trim($op_vals['concat']))."'" : "NULL"),
						pg_escape_string(trim($op_vals['hl7path'])),
						((isset($op_vals['sequence']) && !is_null($op_vals['sequence']) && filter_var($op_vals['sequence'], FILTER_VALIDATE_INT) && (intval($op_vals['sequence']) > 0)) ? intval($op_vals['sequence']) : 1)
					);
				}
			}
			//$onboard_insert_sql .= "ROLLBACK;\n";
			$onboard_insert_sql .= "COMMIT;\n";
			$onboard_insert_rs = @pg_query($host_pa, $onboard_insert_sql);
			if ($onboard_insert_rs) {
				/* debug
				echo "<pre>";
				echo $onboard_insert_sql;
				echo "</pre>";
				*/
				\Udoh\Emsa\Utils\DisplayUtils::drawHighlight("New message structure successfully onboarded!", "ui-icon-elrsuccess");
			} else {
                \Udoh\Emsa\Utils\DisplayUtils::drawError("Unable to onboard new message structure.");
			}
			@pg_free_result($onboard_insert_rs);
		} else {
			\Udoh\Emsa\Utils\DisplayUtils::drawError("No valid XPaths found in generated Mirth XML.");
		}
		
	}

	if ($cleanChildXMLStructureParams['edit_id'] > 0) {
		// Edit an existing HL7 element record, specified by ID
		// check to see if passed a valid row id...
		if (!empty($cleanChildXMLStructureParams['edit']['lab_id']) && !empty($cleanChildXMLStructureParams['edit']['message_version']) && !empty($cleanChildXMLStructureParams['edit']['xpath'])) {
			if (Udoh\Emsa\Management\ReporterStructureUtils::pathExists($adminDbConn, $cleanChildXMLStructureParams['edit_id'])) {
				if (Udoh\Emsa\Management\ReporterStructureUtils::updateSinglePath($adminDbConn, $cleanChildXMLStructureParams['edit_id'], $cleanChildXMLStructureParams['edit']['lab_id'], $cleanChildXMLStructureParams['edit']['master_xpath'], $cleanChildXMLStructureParams['edit']['sequence'], $cleanChildXMLStructureParams['edit']['message_version'], $cleanChildXMLStructureParams['edit']['xpath'], $cleanChildXMLStructureParams['edit']['glue_string'])) {
					Udoh\Emsa\Utils\DisplayUtils::drawHighlight("Reporter XML element successfully updated!", "ui-icon-elrsuccess");
				} else {
					Udoh\Emsa\Utils\DisplayUtils::drawError("Unable to save changes to Reporter XML element.");
				}
			} else {
                Udoh\Emsa\Utils\DisplayUtils::drawError("Unable to save changes to mapping -- Reporter XML element does not exist.");
			}
		} else {
			\Udoh\Emsa\Utils\DisplayUtils::drawError("Not all required fields were entered!  Please specify at least a Reporting Facility, Message Version, and Reporter XML Element Path and try again.");
		}
	} elseif ($cleanChildXMLStructureParams['delete_id'] > 0) {
		// Delete an individual HL7 element, specified by ID
		// check to see if passed a valid row id...
		if (Udoh\Emsa\Management\ReporterStructureUtils::pathExists($adminDbConn, $cleanChildXMLStructureParams['delete_id'])) {
			// commit the delete...
			if (Udoh\Emsa\Management\ReporterStructureUtils::deleteSinglePath($adminDbConn, $cleanChildXMLStructureParams['delete_id'])) {
				Udoh\Emsa\Utils\DisplayUtils::drawHighlight("Reporter XML element successfully deleted!", "ui-icon-elrsuccess");
			} else {
                Udoh\Emsa\Utils\DisplayUtils::drawError('Unable to delete Reporter XML element.');
			}
		} else {
            Udoh\Emsa\Utils\DisplayUtils::drawError("Unable to delete Reporter XML element:  record not found.");
		}
	} elseif ($cleanChildXMLStructureParams['add_flag'] === 1) {
		/*
		 * Add a new individual HL7 element
		 */
		if (isset($cleanChildXMLStructureParams['new']['lab_id']) && isset($cleanChildXMLStructureParams['new']['message_version']) && isset($cleanChildXMLStructureParams['new']['xpath'])) {
            if (Udoh\Emsa\Management\ReporterStructureUtils::addSinglePath($adminDbConn, $cleanChildXMLStructureParams['new']['lab_id'], $cleanChildXMLStructureParams['new']['master_xpath'], $cleanChildXMLStructureParams['new']['sequence'], $cleanChildXMLStructureParams['new']['message_version'], $cleanChildXMLStructureParams['new']['xpath'], $cleanChildXMLStructureParams['new']['glue_string'])) {
                Udoh\Emsa\Utils\DisplayUtils::drawHighlight("New HL7 element added successfully!", "ui-icon-elrsuccess");
            } else {
                Udoh\Emsa\Utils\DisplayUtils::drawError("Could not add new HL7 element.");
            }
		} else {
			Udoh\Emsa\Utils\DisplayUtils::drawError("Not all required fields were entered!  Please specify at least a Reporting Facility, Message Version, and HL7 Element Path and try again.");
		}
	} elseif ((int) filter_input(INPUT_POST, 'clone_flag', FILTER_SANITIZE_NUMBER_INT) === 1) {
		// clone existing mapping to a new reporting facility's mapping
		if (($cleanChildXMLStructureParams['clone_from_lab_id'] > 0) && ($cleanChildXMLStructureParams['clone_to_lab_id'] > 0)) {
            try {
                Udoh\Emsa\Management\ReporterStructureUtils::cloneReporterXMLMapping($adminDbConn, $cleanChildXMLStructureParams['clone_from_lab_id'], $cleanChildXMLStructureParams['clone_to_lab_id']);
                Udoh\Emsa\Utils\DisplayUtils::drawHighlight("Reporter XML Mappings successfully cloned!", "ui-icon-elrsuccess");
            } catch (Throwable $e) {
                Udoh\Emsa\Utils\ExceptionUtils::logException($e);
                Udoh\Emsa\Utils\DisplayUtils::drawError('Could not clone Reporter XML mappings.');
            }
		} else {
			\Udoh\Emsa\Utils\DisplayUtils::drawError('Unable to clone Reporter XML mappings:  "TO" and "FROM" Reporting Facilities both required.');
		}
	}

?>

<h1 class="elrhdg"><span class="ui-icon ui-icon-header ui-icon-elrxml"></span>Reporter to Master XPath-Based Mapping</h1>

<form name="search_form" id="search_form" method="GET" action="<?php echo $webappBaseUrl; ?>">

<div class="emsa_search_controls ui-tabs ui-widget">
    <label for="q" class="emsa_form_heading">Search: </label>
    <input type="text" name="q" id="q" placeholder="Enter search terms..." class="vocab_query ui-corner-all" value="<?php Udoh\Emsa\Utils\DisplayUtils::xEcho((string) $_SESSION[EXPORT_SERVERNAME]["structure_params"][$_SESSION[EXPORT_SERVERNAME]["structure_params"]["structure"]]["q_display"], "UTF-8", false); ?>">
	<button name="q_go" id="q_go">Search</button>
	<button type="button" name="clear_filters" id="clear_filters" title="Clear all search terms/filters">Clear</button>
	<button type="button" name="toggle_filters" id="toggle_filters" title="Show/hide filters">Hide Filters</button>
</div>

<?php
	############### If filters applied, display which ones ###############
	if (isset($_SESSION[EXPORT_SERVERNAME]["structure_params"][$_SESSION[EXPORT_SERVERNAME]["structure_params"]["structure"]]["filters"])) {
?>
<div class="vocab_search ui-widget ui-widget-content ui-state-highlight ui-corner-all" style="padding: 5px;">
	<span class="ui-icon ui-icon-elroptions" style="float: left; margin-right: .3em;"></span><p style="margin-left: 20px;">Filtering by 
<?php
		$active_filters = 0;
		foreach ($_SESSION[EXPORT_SERVERNAME]["structure_params"][$_SESSION[EXPORT_SERVERNAME]["structure_params"]["structure"]]["filters"] as $sqlfiltercol => $sqlfiltervals) {
			if ($active_filters == 0) {
				echo "<strong>" . $_SESSION[EXPORT_SERVERNAME]["structure_params"][$_SESSION[EXPORT_SERVERNAME]["structure_params"]["structure"]]["filter_cols"][$sqlfiltercol]['label'] . "</strong>";
			} else {
				echo ", <strong>" . $_SESSION[EXPORT_SERVERNAME]["structure_params"][$_SESSION[EXPORT_SERVERNAME]["structure_params"]["structure"]]["filter_cols"][$sqlfiltercol]['label'] . "</strong>";			}
			$active_filters++;
		}
?>
	</p>
</div>
<?php
	}
?>

<div class="vocab_filter ui-widget ui-widget-content ui-corner-all">
	<div style="clear: both;"><span class="emsa_form_heading">Filters:</span></div>
<?php
	############### Draw filter form elements based on 'filter_cols' array ###############
	foreach ($_SESSION[EXPORT_SERVERNAME]["structure_params"][$_SESSION[EXPORT_SERVERNAME]["structure_params"]["structure"]]["filter_cols"] as $filtercol => $filtercolname) {
		if ($filtercolname['filter']) {
			if (isset($filtercolname['filterlookup']) && $filtercolname['filterlookup']) {
				$filterQuery =  $filtercolname['lookupqry'];
			} else {
				$filterQuery = sprintf("SELECT DISTINCT %s AS value, %s AS label FROM %s ORDER BY %s ASC;", $filtercol, $filtercol, $_SESSION[EXPORT_SERVERNAME]["structure_params"][$_SESSION[EXPORT_SERVERNAME]["structure_params"]["structure"]]["query_data"]["table_name"], $filtercol);
			}
			
			(new AccessibleMultiselectListbox(FilterFactory::getListFromQuery($adminDbConn, $filterQuery), $_SESSION[EXPORT_SERVERNAME]["structure_params"][$_SESSION[EXPORT_SERVERNAME]["structure_params"]["structure"]]['filters'][$filtercol] ?? null))
                ->render($filtercolname['label'], 'f[' . $filtercol . ']', false, false, $filtercol, null, $filtercolname['filterlookup'] ?? null);
		}
	}
?>
	<br><br><button name="apply_filters" id="apply_filters" style="clear: both; float: left; margin: 5px;">Apply Filters</button>
</div>
    
<div style="clear: both;" class="vocab_search ui-tabs ui-widget">
    <button type="button" id="addnew_button" class="addnew_button_right" title="Manually add a new HL7 message element">Add Single Path</button>
	<button type="button" id="onboard_button" title="Onboard a new Reporting Facility by supplying sample HL7 messages">Onboard HL7</button>
    <button type="button" id="onboard_cda_button" title="Onboard a new Reporting Facility by supplying sample CDA messages">Onboard CDA</button>
	<button type="button" id="clone_button" title="Clone mapping from one Reporting Facility to another">Clone Facility</button>
</div>
<br>

<input type="hidden" name="selected_page" value="<?php echo $navSelectedPage; ?>">
<input type="hidden" name="submenu" value="<?php echo $navSubmenu; ?>">
<input type="hidden" name="cat" value="<?php echo $navCat; ?>">
<input type="hidden" name="subcat" value="<?php echo $navSubcat; ?>">

</form>

<div id="clone_form" class="addnew_lab ui-widget ui-widget-content ui-corner-all">
	<div style="clear: both;"><span class="emsa_form_heading">Clone Reporting Facility:</span><br><br></div>
	<form id="clone_lab_form" method="POST" action="<?php echo $webappBaseUrl; ?>?selected_page=<?php echo intval($navSelectedPage); ?>&submenu=<?php echo intval($navSubmenu); ?>&cat=<?php echo intval($navCat); ?>&subcat=<?php echo intval($navSubcat); ?>">
		<label class="vocab_search_form2" for="clone_from_lab_id">Copy mapping FROM:</label>
			<select class="ui-corner-all" name="clone_from_lab_id" id="clone_from_lab_id">
				<option value="0" selected>--</option>
			<?php
				// get list of labs for menu
				$addnew_sql = 'SELECT DISTINCT l.id AS id, l.ui_name AS ui_name 
					FROM '.$emsaDbSchemaPrefix.'structure_labs l
					INNER JOIN '.$emsaDbSchemaPrefix.'structure_path_mirth m ON (m.lab_id = l.id)
					WHERE l.alias_for < 1
					AND l.visible IS TRUE
					ORDER BY l.ui_name;';
				$addnew_rs = @pg_query($host_pa, $addnew_sql) or \Udoh\Emsa\Utils\DisplayUtils::drawError("Unable to retrieve list of labs.", true);
				while ($addnew_row = pg_fetch_object($addnew_rs)) {
					printf("<option value=\"%d\">%s</option>", intval($addnew_row->id), htmlentities($addnew_row->ui_name));
				}
				@pg_free_result($addnew_rs);
			?>
			</select>
		<label class="vocab_search_form2" for="clone_to_lab_id">Create mapping FOR:</label>
			<select class="ui-corner-all" name="clone_to_lab_id" id="clone_to_lab_id">
				<option value="0" selected>--</option>
			<?php
				// get list of labs for menu
				$addnew_sql = 'SELECT DISTINCT id, ui_name 
					FROM '.$emsaDbSchemaPrefix.'structure_labs 
					WHERE alias_for < 1 
					AND visible IS TRUE
					AND id NOT IN (SELECT DISTINCT lab_id FROM '.$emsaDbSchemaPrefix.'structure_path_mirth)
					ORDER BY ui_name;';
				$addnew_rs = @pg_query($host_pa, $addnew_sql) or \Udoh\Emsa\Utils\DisplayUtils::drawError("Unable to retrieve list of labs.", true);
				while ($addnew_row = pg_fetch_object($addnew_rs)) {
					printf("<option value=\"%d\">%s</option>", intval($addnew_row->id), htmlentities($addnew_row->ui_name));
				}
				@pg_free_result($addnew_rs);
			?>
			</select>
		
		<input type="hidden" name="clone_flag" value="1" />
		<br><br><button type="submit" name="clone_savelab" id="clone_savelab">Clone Mapping</button>
		<button type="button" id="clone_cancel">Cancel</button>
	</form>
</div>

<div id="addnew_form" class="addnew_lab ui-widget ui-widget-content ui-corner-all">
	<div style="clear: both;"><span class="emsa_form_heading">Add New Reporter XML Element:</span><br><br></div>
	<form id="new_lab_form" method="POST" action="<?php echo $webappBaseUrl; ?>?selected_page=<?php echo intval($navSelectedPage); ?>&submenu=<?php echo intval($navSubmenu); ?>&cat=<?php echo intval($navCat); ?>&subcat=<?php echo intval($navSubcat); ?>">
		<label class="vocab_search_form2" for="new_lab_id">Reporting Facility:</label>
			<select class="ui-corner-all" name="new_lab_id" id="new_lab_id">
				<option value="0" selected>--</option>
			<?php
				// get list of labs for menu
				$addnew_sql = sprintf("SELECT DISTINCT id, ui_name FROM %sstructure_labs WHERE alias_for < 1 ORDER BY ui_name;", $emsaDbSchemaPrefix);
				$addnew_rs = @pg_query($host_pa, $addnew_sql) or \Udoh\Emsa\Utils\DisplayUtils::drawError("Unable to retrieve list of labs.", true);
				while ($addnew_row = pg_fetch_object($addnew_rs)) {
					printf("<option value=\"%d\">%s</option>", intval($addnew_row->id), htmlentities($addnew_row->ui_name));
				}
				@pg_free_result($addnew_rs);
			?>
			</select>
		<label class="vocab_search_form2" for="new_message_version">Message Version:</label><input class="ui-corner-all" type="text" name="new_message_version" id="new_message_version" />
		<br><br><label class="vocab_search_form2" for="new_xpath">Reporter XML XPath:</label><input class="ui-corner-all" type="text" name="new_xpath" id="new_xpath" />
		<label class="vocab_search_form2" for="new_master_xpath">Master XML Path:</label>
			<select class="ui-corner-all" name="new_master_xpath" id="new_master_xpath">
				<option value="0" selected>--</option>
			<?php
				// get list of XML paths for menu
				$path_sql = sprintf("SELECT DISTINCT id, element, xpath FROM %sstructure_path ORDER BY element;", $emsaDbSchemaPrefix);
				$path_rs = @pg_query($host_pa, $path_sql) or \Udoh\Emsa\Utils\DisplayUtils::drawError("Unable to retrieve list of XML paths.", true);
				while ($path_row = pg_fetch_object($path_rs)) {
					printf("<option value=\"%d\">%s (%s)</option>", intval($path_row->id), htmlentities($path_row->element, ENT_QUOTES, "UTF-8"), htmlentities($path_row->xpath, ENT_QUOTES, "UTF-8"));
				}
				pg_free_result($path_rs);
			?>
			</select>
		<br><br><label class="vocab_search_form2" for="new_glue">Concatenation String:</label><input class="ui-corner-all" type="text" name="new_glue" id="new_glue" />
		<label class="vocab_search_form2" for="new_sequence">Sequence:</label><input class="ui-corner-all" type="text" name="new_sequence" id="new_sequence" />
		<input type="hidden" name="lab_id" value="" />
		<input type="hidden" name="version" value="" />
		<input type="hidden" name="add_flag" value="1" />
		<br><br><button type="submit" name="new_savelab" id="new_savelab">Save New Reporter XML Element</button>
		<button type="button" id="addnew_cancel">Cancel</button>
	</form>
</div>

<div id="onboard_form" class="addnew_lab ui-widget ui-widget-content ui-corner-all">
	<div style="clear: both;"><span class="emsa_form_heading">Onboard New HL7 Message Structure - Step 1:</span><br><br></div>
	<form id="new_onboard_form" method="POST" action="<?php echo $webappBaseUrl; ?>?selected_page=<?php echo intval($navSelectedPage); ?>&submenu=<?php echo intval($navSubmenu); ?>&cat=<?php echo intval($navCat); ?>&subcat=<?php echo intval($navSubcat); ?>">
		<label class="vocab_search_form2" for="onboard_preview">Preview Full XML?</label>
			<select class="ui-corner-all" name="onboard_preview" id="onboard_preview">
				<option value="1">Yes, view full Mirth XML document</option>
				<option value="0" selected>No, onboard/view paths</option>
			</select>
		<br><br><label class="vocab_search_form2" for="onboard_lab_id">Reporting Facility:</label>
			<select class="ui-corner-all" name="onboard_lab_id" id="onboard_lab_id">
				<option value="0" selected>--</option>
			<?php
				// get list of labs for menu
				$onboard_sql = sprintf("SELECT DISTINCT id, ui_name FROM %sstructure_labs WHERE alias_for < 1 ORDER BY ui_name;", $emsaDbSchemaPrefix);
				$onboard_rs = @pg_query($host_pa, $onboard_sql) or \Udoh\Emsa\Utils\DisplayUtils::drawError("Unable to retrieve list of labs.", true);
				while ($onboard_row = pg_fetch_object($onboard_rs)) {
					printf("<option value=\"%d\">%s</option>", intval($onboard_row->id), htmlentities($onboard_row->ui_name));
				}
				@pg_free_result($onboard_rs);
			?>
			</select>
		<label class="vocab_search_form2" for="onboard_message_version">Message Version:</label><input class="ui-corner-all" type="text" name="onboard_message_version" id="onboard_message_version" />
		<br><br><label class="vocab_search_form2" for="onboard_message_1">HL7 Message #1:</label><br>
		<textarea class="ui-corner-all" name="onboard_message[]" id="onboard_message_1" style="width: 70%; height: 12em;"></textarea>
		<br><br><label class="vocab_search_form2" for="onboard_message_2">HL7 Message #2 (Optional):</label><br>
		<textarea class="ui-corner-all" name="onboard_message[]" id="onboard_message_2" style="width: 70%; height: 12em;"></textarea>
		<br><br><label class="vocab_search_form2" for="onboard_message_3">HL7 Message #3 (Optional):</label><br>
		<textarea class="ui-corner-all" name="onboard_message[]" id="onboard_message_3" style="width: 70%; height: 12em;"></textarea>
		<br><br><label class="vocab_search_form2" for="onboard_message_4">HL7 Message #4 (Optional):</label><br>
		<textarea class="ui-corner-all" name="onboard_message[]" id="onboard_message_4" style="width: 70%; height: 12em;"></textarea>
		<br><br><label class="vocab_search_form2" for="onboard_message_5">HL7 Message #5 (Optional):</label><br>
		<textarea class="ui-corner-all" name="onboard_message[]" id="onboard_message_5" style="width: 70%; height: 12em;"></textarea>
		
		<input type="hidden" name="lab_id" value="" />
		<input type="hidden" name="version" value="" />
		<input type="hidden" name="onboard_pre_flag" value="1" />
		<br><br><button type="submit" name="onboard_save" id="onboard_save">Parse & Generate Structure</button>
		<button type="button" id="onboard_cancel">Cancel</button>
	</form>
</div>

<div id="onboard_cda_form" class="addnew_lab ui-widget ui-widget-content ui-corner-all">
	<div style="clear: both;"><span class="emsa_form_heading">Onboard New CDA Message Structure - Step 1:</span><br><br></div>
	<form id="new_cda_onboard_form" method="POST" action="<?php echo $webappBaseUrl; ?>?selected_page=<?php echo intval($navSelectedPage); ?>&submenu=<?php echo intval($navSubmenu); ?>&cat=<?php echo intval($navCat); ?>&subcat=<?php echo intval($navSubcat); ?>">
		<label class="vocab_search_form2" for="onboard_cda_lab_id">Reporting Facility:</label>
			<select class="ui-corner-all" name="onboard_lab_id" id="onboard_cda_lab_id">
				<option value="0" selected>--</option>
			<?php
				// get list of labs for menu
				$onboard_sql = sprintf("SELECT DISTINCT id, ui_name FROM %sstructure_labs WHERE alias_for < 1 ORDER BY ui_name;", $emsaDbSchemaPrefix);
				$onboard_rs = @pg_query($host_pa, $onboard_sql) or \Udoh\Emsa\Utils\DisplayUtils::drawError("Unable to retrieve list of labs.", true);
				while ($onboard_row = pg_fetch_object($onboard_rs)) {
					printf("<option value=\"%d\">%s</option>", intval($onboard_row->id), htmlentities($onboard_row->ui_name));
				}
				@pg_free_result($onboard_rs);
			?>
			</select>
		<label class="vocab_search_form2" for="onboard_cda_message_version">Document Code:</label><input class="ui-corner-all" type="text" name="onboard_message_version" id="onboard_cda_message_version" />
		<br><br><label class="vocab_search_form2" for="onboard_cda_message_1">CDA Message #1:</label><br>
		<textarea class="ui-corner-all" name="onboard_message[]" id="onboard_cda_message_1" style="width: 70%; height: 12em;"></textarea>
		<br><br><label class="vocab_search_form2" for="onboard_cda_message_2">CDA Message #2 (Optional):</label><br>
		<textarea class="ui-corner-all" name="onboard_message[]" id="onboard_cda_message_2" style="width: 70%; height: 12em;"></textarea>
		<br><br><label class="vocab_search_form2" for="onboard_cda_message_3">CDA Message #3 (Optional):</label><br>
		<textarea class="ui-corner-all" name="onboard_message[]" id="onboard_cda_message_3" style="width: 70%; height: 12em;"></textarea>
		<br><br><label class="vocab_search_form2" for="onboard_cda_message_4">CDA Message #4 (Optional):</label><br>
		<textarea class="ui-corner-all" name="onboard_message[]" id="onboard_cda_message_4" style="width: 70%; height: 12em;"></textarea>
		<br><br><label class="vocab_search_form2" for="onboard_cda_message_5">CDA Message #5 (Optional):</label><br>
		<textarea class="ui-corner-all" name="onboard_message[]" id="onboard_cda_message_5" style="width: 70%; height: 12em;"></textarea>
		
		<input type="hidden" name="lab_id" value="" />
		<input type="hidden" name="version" value="" />
		<input type="hidden" name="onboard_pre_flag" value="2" />
		<br><br><button type="submit" name="onboard_save" id="onboard_cda_save">Parse & Generate Structure</button>
		<button type="button" id="onboard_cda_cancel">Cancel</button>
	</form>
</div>

<div class="lab_results_container ui-widget ui-corner-all">
	<table id="labResults">
		<thead>
			<tr>
				<th>Actions</th>
				<th>Reporter</th>
				<th>Message Version</th>
				<th>Reporter XML Path</th>
				<th>Master XML Path</th>
				<th>Concat String</th>
				<th>Sequence</th>
			</tr>
		</thead>
		<tbody>

<?php

	$where_clause = '';
	
	$where_count = 0;
	// handle any search terms or filters...
	if (!empty($_SESSION[EXPORT_SERVERNAME]["structure_params"][$_SESSION[EXPORT_SERVERNAME]["structure_params"]["structure"]]["q_sql"])) {
		// we've got some query params
		$where_count = 1;
		$where_clause .= " WHERE (";
		
		foreach ($_SESSION[EXPORT_SERVERNAME]["structure_params"][$_SESSION[EXPORT_SERVERNAME]["structure_params"]["structure"]]["filter_cols"] as $searchcol => $searchcoldata) {
			if ($searchcoldata['textsearch']) {
				if ($where_count > 1) {
					$where_clause .= " OR ";
				}
				$where_clause .= sprintf("%s ILIKE '%%%s%%'", $searchcoldata['colname'], pg_escape_string($_SESSION[EXPORT_SERVERNAME]["structure_params"][$_SESSION[EXPORT_SERVERNAME]["structure_params"]["structure"]]["q_sql"]));
				$where_count++;
			}
		}
		
		$where_clause .= ")";
	}
	
	if (isset($_SESSION[EXPORT_SERVERNAME]["structure_params"][$_SESSION[EXPORT_SERVERNAME]["structure_params"]["structure"]]["filters"])) {
		// need to apply filters
		$filter_count = 0;
		if ($where_count == 0) {
			// not already a WHERE clause for search terms
			$where_clause .= " WHERE";
		}
		
		foreach ($_SESSION[EXPORT_SERVERNAME]["structure_params"][$_SESSION[EXPORT_SERVERNAME]["structure_params"]["structure"]]["filters"] as $sqlfiltercol => $sqlfiltervals) {
			unset($filter_temp);
			$nullfilter = FALSE;
			if (($filter_count > 0) || ($where_count > 1)) {
				$where_clause .= " AND (";
			} else {
				$where_clause .= " (";
			}
			foreach ($sqlfiltervals as $sqlfilterval) {
				if (is_null($sqlfilterval) || (strlen(trim($sqlfilterval)) == 0)) {
					$nullfilter = TRUE;
				} else {
					$filter_temp[] = "'" . pg_escape_string($sqlfilterval) . "'";
				}
			}
			
			if ($nullfilter && is_array($filter_temp)) {
				$where_clause .= $_SESSION[EXPORT_SERVERNAME]["structure_params"][$_SESSION[EXPORT_SERVERNAME]["structure_params"]["structure"]]["filter_cols"][$sqlfiltercol]["filtercolname"] . " IN (" . implode(",", $filter_temp) . ") OR " . $_SESSION[EXPORT_SERVERNAME]["structure_params"][$_SESSION[EXPORT_SERVERNAME]["structure_params"]["structure"]]["filter_cols"][$sqlfiltercol]["filtercolname"] . " IS NULL OR " . $_SESSION[EXPORT_SERVERNAME]["structure_params"][$_SESSION[EXPORT_SERVERNAME]["structure_params"]["structure"]]["filter_cols"][$sqlfiltercol]["filtercolname"] . " = ''";
			} elseif (is_array($filter_temp)) {
				$where_clause .= $_SESSION[EXPORT_SERVERNAME]["structure_params"][$_SESSION[EXPORT_SERVERNAME]["structure_params"]["structure"]]["filter_cols"][$sqlfiltercol]["filtercolname"] . " IN (" . implode(",", $filter_temp) . ")";
			} elseif ($nullfilter) {
				$where_clause .= $_SESSION[EXPORT_SERVERNAME]["structure_params"][$_SESSION[EXPORT_SERVERNAME]["structure_params"]["structure"]]["filter_cols"][$sqlfiltercol]["filtercolname"] . " IS NULL OR " . $_SESSION[EXPORT_SERVERNAME]["structure_params"][$_SESSION[EXPORT_SERVERNAME]["structure_params"]["structure"]]["filter_cols"][$sqlfiltercol]["filtercolname"] . " = ''";
			}
			
			$where_clause .= ")";
			$filter_count++;
		}
	}
	
	$hl7_qry = 'SELECT h.id AS id, h.message_version AS message_version, h.xpath AS xpath, h.master_path_id AS master_path_id, h.sequence AS sequence, h.glue_string AS glue_string, l.id AS lab_id, l.ui_name AS lab_name, p.xpath AS master_xpath, p.element AS master_element
		FROM '.$emsaDbSchemaPrefix.'structure_path_mirth h 
		INNER JOIN '.$emsaDbSchemaPrefix.'structure_labs l ON (h.lab_id = l.id) 
		LEFT JOIN '.$emsaDbSchemaPrefix.'structure_path p ON (h.master_path_id = p.id) 
		'.$where_clause.' 
		ORDER BY h.lab_id, h.message_version, h.master_path_id, h.sequence, h.xpath';
	$hl7_rs = @pg_query($host_pa, $hl7_qry) or \Udoh\Emsa\Utils\DisplayUtils::drawError("Could not connect to database:");
	
	while ($hl7_row = @pg_fetch_object($hl7_rs)) {
		echo "<tr>";
		printf("<td class=\"action_col\">");
		unset($edit_lab_params);
		$edit_lab_params = array(
			"element_id" => intval($hl7_row->id), 
			"lab_id" => intval($hl7_row->lab_id), 
			"version" => htmlentities($hl7_row->message_version, ENT_QUOTES, "UTF-8"), 
			"xpath" => htmlentities($hl7_row->xpath, ENT_QUOTES, "UTF-8"), 
			"master_xpath" => intval($hl7_row->master_path_id), 
			"glue" => htmlentities($hl7_row->glue_string, ENT_QUOTES, "UTF-8"), 
			"sequence" => intval($hl7_row->sequence)
		);
		printf("<button class=\"edit_lab\" type=\"button\" value='%s' title=\"Edit this record\">Edit</button>", json_encode($edit_lab_params));
		printf("<button class=\"delete_lab\" type=\"button\" value=\"%s\" title=\"Delete this record\">Delete</button>", $hl7_row->id);
		echo "</td>";
		echo "<td>".htmlentities($hl7_row->lab_name, ENT_QUOTES, "UTF-8")."</td>";
		echo "<td>".htmlentities($hl7_row->message_version, ENT_QUOTES, "UTF-8")."</td>";
		echo "<td style='font-family: Consolas !important;'>".str_replace("/", "<wbr>/", htmlentities($hl7_row->xpath, ENT_QUOTES, "UTF-8"))."</td>";
		echo "<td>".htmlentities($hl7_row->master_element, ENT_QUOTES, "UTF-8")." (<kbd style='font-family: Consolas;'>".str_replace("/", "<wbr>/", htmlentities($hl7_row->master_xpath, ENT_QUOTES, "UTF-8"))."</kbd>)</td>";
		echo "<td>".htmlentities($hl7_row->glue_string, ENT_QUOTES, "UTF-8")."</td>";
		echo "<td>".intval($hl7_row->sequence)."</td>";
		echo "</tr>";
	}
	
	@pg_free_result($hl7_rs);

?>

		</tbody>
	</table>
	
</div>

<div id="confirm_delete_dialog" title="Delete this XML Element?">
	<p><span class="ui-icon ui-icon-elrerror" style="float:left; margin:0 7px 50px 0;"></span>This Reporter XML element will be permanently deleted and cannot be recovered. Are you sure?</p>
</div>

<div id="edit_lab_dialog" title="Edit XML Element">
	<form id="edit_modal_form" method="POST" action="<?php echo $webappBaseUrl; ?>?selected_page=<?php echo intval($navSelectedPage); ?>&submenu=<?php echo intval($navSubmenu); ?>&cat=<?php echo intval($navCat); ?>&subcat=<?php echo intval($navSubcat); ?>">
		<label for="edit_lab_id">Reporting Facility:</label><br>
		<select class="ui-corner-all" style="margin: 0px;" name="edit_lab_id" id="edit_lab_id">
			<option value="0" selected>--</option>
		<?php
			// get list of labs for menu
			$editlabs_sql = sprintf("SELECT DISTINCT id, ui_name FROM %sstructure_labs WHERE alias_for < 1 ORDER BY ui_name;", $emsaDbSchemaPrefix);
			$editlabs_rs = @pg_query($host_pa, $editlabs_sql) or \Udoh\Emsa\Utils\DisplayUtils::drawError("Unable to retrieve list of labs.", true);
			while ($editlabs_row = pg_fetch_object($editlabs_rs)) {
				printf("<option value=\"%d\">%s</option>", intval($editlabs_row->id), htmlentities($editlabs_row->ui_name));
			}
			pg_free_result($editlabs_rs);
		?>
		</select><br><br>
		<label for="edit_message_version">Message Version:</label><br><input class="ui-corner-all" type="text" name="edit_message_version" id="edit_message_version" /><br><br>
		<label for="edit_xpath">Reporter XML XPath:</label><br><input class="ui-corner-all" type="text" name="edit_xpath" id="edit_xpath" /><br><br>
		<label for="edit_master_xpath">Master XML XPath:</label><br>
		<select class="ui-corner-all" style="margin: 0px;" name="edit_master_xpath" id="edit_master_xpath">
			<option value="0" selected>--</option>
		<?php
			// get list of XML paths for menu
			$path_sql = sprintf("SELECT DISTINCT id, element, xpath FROM %sstructure_path ORDER BY element;", $emsaDbSchemaPrefix);
			$path_rs = @pg_query($host_pa, $path_sql) or \Udoh\Emsa\Utils\DisplayUtils::drawError("Unable to retrieve list of XML paths.", true);
			while ($path_row = pg_fetch_object($path_rs)) {
				printf("<option value=\"%d\">%s (%s)</option>", intval($path_row->id), htmlentities($path_row->element, ENT_QUOTES, "UTF-8"), htmlentities($path_row->xpath, ENT_QUOTES, "UTF-8"));
			}
			pg_free_result($path_rs);
		?>
		</select><br><br>
		<label for="edit_glue">Concatenation String:</label><br><input class="ui-corner-all" type="text" name="edit_glue" id="edit_glue" /><br><br>
		<label for="edit_sequence">Sequence:</label><br><input class="ui-corner-all" type="text" name="edit_sequence" id="edit_sequence" /><br><br>
		<input type="hidden" name="edit_id" id="edit_id" />
	</form>
</div>
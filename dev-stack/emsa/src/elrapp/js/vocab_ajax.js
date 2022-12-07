/*
  Copyright (c) 2016 Utah Department of Technology Services and Utah Department of Health

  This program is free software: you can redistribute it and/or modify
  it under the terms of the GNU Affero General Public License as published by
  the Free Software Foundation, either version 3 of the License, or
  (at your option) any later version.

  This program is distributed in the hope that it will be useful,
  but WITHOUT ANY WARRANTY; without even the implied warranty of
  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
  GNU Affero General Public License for more details.

  You should have received a copy of the GNU Affero General Public License
  along with this program.  If not, see <http://www.gnu.org/licenses/>.

  In addition, this program is also subject to certain additional terms. You should
  have received a copy of these additional terms immediately following the terms and
  conditions of the GNU Affero General Public License which accompanied the program.
  If not, please request a copy in writing from the Utah Department of Health at
  the address below.

  If you have questions concerning this license or the applicable additional terms,
  you may contact us in writing at:
  Utah Department of Health, P.O. Box 141010, Salt Lake City, UT 84114-1010 USA.
 */

$(function() {
	$("#ajax_results_success_dialog").dialog({
		autoOpen: false,
		modal: true,
		draggable: true,
		resizable: true,
		width: 500
	});
	
	$("#ajax_results_error_dialog").dialog({
		autoOpen: false,
		modal: true,
		draggable: true,
		resizable: true,
		width: 500
	});
	
	$(".rbc_row").off("click").on("click", function(e) {
		$(".rbc_row").not($(this)).removeClass("rbc_row_highlight");
		if (e.ctrlKey) {
			$(this).toggleClass("rbc_row_highlight");
		} else {
			$(this).addClass("rbc_row_highlight");
		}
	});
});

function drawTextField(vocabTable, getColumn, vocabId, targetDiv) {
	$('#'+targetDiv+'_status').show();
	$.ajax({
		type: 'POST',
		data: { handler: 'drawTextField', tbl: vocabTable, col: getColumn, id: vocabId },
		url: 'ajax/vocab_ajax.php',
		dataType: 'html',
		async: false
	}).done(function(data) {
		$('#'+targetDiv+'_status').hide();
		$('#'+targetDiv+'_ajax').html(data);
	});
}

function drawRulesForCondition(vocabTable, vocabId, conditionName, targetDiv) {
	$('#'+targetDiv+'_status').show();
	$.ajax({
		type: 'POST',
		data: { handler: 'drawRulesForCondition', tbl: vocabTable, id: vocabId, condition: conditionName },
		url: 'ajax/vocab_ajax.php',
		dataType: 'html',
		async: false
	}).done(function(data) {
		$('#'+targetDiv+'_status').hide();
		$('#'+targetDiv+'_ajax').html(data);
	});
}

function ruleBuilderJQueryUI() {
	$.getScript("js/vocab_rules_graylist.js");
	$.getScript("js/vocab_rules_masterloinc.js");
	$.getScript("js/vocab_rules_mastersnomed.js");
	$.getScript("js/vocab_rules_singlefield.js");
}

function handleEditSubmit(form, updateTarget, callbackHandler, callbackTbl, callbackId, callbackNoun, callbackCol) {
	var updateTargetBase = updateTarget.replace("_ajax", "");
	var formData = $(form).serialize();
	$.ajax({
		type: 'POST',
		data: { handler: 'saveChanges', tbl: callbackTbl, editPkg: formData },
		url: 'ajax/vocab_ajax.php',
		dataType: 'html',
		async: false
	}).done(function(data) {
		var doneStr = "<p><span class=\"ui-icon ui-icon-elrsuccess\" style=\"float:left; margin:0 7px 50px 0;\"></span>"+data+"</p>";
		$("#ajax_results_success_dialog").html(doneStr);
		$("#ajax_results_success_dialog").dialog('option', 'buttons', {
				"OK" : function() {
					$(this).dialog("close");
					}
				});

		$("#ajax_results_success_dialog").dialog("open");
	}).error(function(jqXHR, textStatus, errorThrown) {
		var errStr = "<p><span class=\"ui-icon ui-icon-elrerror\" style=\"float:left; margin:0 7px 50px 0;\"></span>An error occurred while attempting to update the specified vocabulary entry:<br><br>"+errorThrown+"</p>";
		$("#ajax_results_error_dialog").html(errStr);
		$("#ajax_results_error_dialog").dialog('option', 'buttons', {
				"OK" : function() {
					$(this).dialog("close");
					}
				});

		$("#ajax_results_error_dialog").dialog("open");
	}).always(function() {
		if (callbackHandler === 'drawTextField') {
			drawTextField(callbackTbl, callbackCol, callbackId, updateTargetBase);
		} else if (callbackHandler === 'drawRulesForCondition') {
			drawRulesForCondition(callbackTbl, callbackId, callbackNoun, updateTargetBase);
		}
		ruleBuilderJQueryUI();
	});
}

function handleRuleDeleteSubmit(updateTarget, callbackHandler, callbackTbl, callbackRuleId, callbackVocabId, callbackNoun) {
	var updateTargetBase = updateTarget.replace("_ajax", "");
	$.ajax({
		type: 'POST',
		data: { handler: 'deleteRule', tbl: callbackTbl, id: callbackRuleId, parentId: callbackVocabId },
		url: 'ajax/vocab_ajax.php',
		dataType: 'html',
		async: false
	}).done(function(data) {
		var doneStr = "<p><span class=\"ui-icon ui-icon-elrsuccess\" style=\"float:left; margin:0 7px 50px 0;\"></span>"+data+"</p>";
		$("#ajax_results_success_dialog").html(doneStr);
		$("#ajax_results_success_dialog").dialog('option', 'buttons', {
				"OK" : function() {
					$(this).dialog("close");
					}
				});

		$("#ajax_results_success_dialog").dialog("open");
	}).error(function(jqXHR, textStatus, errorThrown) {
		var errStr = "<p><span class=\"ui-icon ui-icon-elrerror\" style=\"float:left; margin:0 7px 50px 0;\"></span>An error occurred while attempting to delete the specified rule:<br><br>"+errorThrown+"</p>";
		$("#ajax_results_error_dialog").html(errStr);
		$("#ajax_results_error_dialog").dialog('option', 'buttons', {
				"OK" : function() {
					$(this).dialog("close");
					}
				});

		$("#ajax_results_error_dialog").dialog("open");
	}).always(function() {
		if (callbackHandler === 'drawRulesForCondition') {
			drawRulesForCondition(callbackTbl, callbackVocabId, callbackNoun, updateTargetBase);
		}
		ruleBuilderJQueryUI();
	});
}
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

// jQueryUI functions for Master LOINC-based CMR Rules Builder functionality

$("#rule_mod_ms_cmr_dialog").dialog({
	autoOpen: false,
	width: 725,
	modal: true
});

$("#confirm_deleterule_ms_cmr_dialog").dialog({
	autoOpen: false,
	modal: true,
	draggable: false,
	resizable: false,
	width: 400
});

$(".edit_ms_cmr_rule").button({
	icon: "ui-icon-elrpencil",
	showLabel: false
}).off("click").on("click", function(e) {
	e.preventDefault();
	var jsonObj = JSON.parse($(this).val());
	var targetDiv = $(e.target).closest("div").attr('id');
	var numConditions = parseInt($("#rulemod_ms_cmr_condition_counter").val());
	
	if (jsonObj.id && jsonObj.action) {
		$("#rulemod_ms_cmr_condition_container").empty();
		$("#rulemod_ms_cmr_condition_counter").val((numConditions+jsonObj.conditions.length));

		for (var i = 0; i < jsonObj.conditions.length; i++) {
			$("#rulemod_ms_cmr_condition_container").append("<div class=\"rulemod_cmr_condition\" id=\"rulemod_ms_cmr_condition_"+(i)+"\"></div>");
			$("#rulemod_ms_cmr_condition_"+(i)).html("<button type=\"button\" class=\"rulemod_cmr_delete_condition\" value=\""+(i)+"\" title=\"Delete This Condition\">Delete Condition</button><label for=\"rulemod_ms_cmr_operator_"+(i)+"\">Test Result </label><select class=\"ui-corner-all\" style=\"margin: 0px; max-width: 11em;\" name=\"rulemod_ms_cmr_operator["+(i)+"]\" id=\"rulemod_ms_cmr_operator_"+(i)+"\" title=\"Choose a comparison type\"><option value=\"0\" selected>--</option></select> <select class=\"ui-corner-all\" style=\"margin: 0px;\" name=\"rulemod_ms_cmr_operand_value["+(i)+"]\" id=\"rulemod_ms_cmr_operand_value_"+(i)+"\"><option value=\"0\" selected>--</option></select>");
			
			// get operators
			$.ajax({
				type: 'POST',
				data: { type: 1 },
				url: 'ajax/operator.php',
				dataType: 'json',
				async: false
			}).done(function(data) {
				$.each(data, function(idx, val) {
					$("#rulemod_ms_cmr_operator_"+(i)).append("<option value='"+val.id+"'>"+val.label+" ("+val.graphical+")</option>");
				});
			});
			
			// get master test results
			$.ajax({
				type: 'GET',
				url: 'ajax/test_result_combined.php',
				dataType: 'json',
				async: false
			}).done(function(data) {
				$.each(data, function(idx, val) {
					$("#rulemod_ms_cmr_operand_value_"+(i)).append("<option value='"+val.id+"'>"+val.concept+"</option>");
				});
			});
			
			$("#rulemod_ms_cmr_operator_"+(i)).val(jsonObj.conditions[i].operator);
			$("#rulemod_ms_cmr_operand_value_"+(i)).val(jsonObj.conditions[i].operand);
		}

		$(".rulemod_cmr_delete_condition").button({
			icon: "ui-icon-elrclose",
			showLabel: false
		}).off("click").on("click", function(e) {
			var deleteCondition = $(this).val();
			$("#rulemod_ms_cmr_condition_"+deleteCondition).remove();
		});

		$("#rulemod_ms_cmr_loinc").html("Edit rule for Master LOINC '"+jsonObj.loinc+"':");
		$("#rulemod_ms_cmr_id").val(jsonObj.id);
		$("#rulemod_ms_cmr_focus_id").val(jsonObj.focus_id);
		$("#rulemod_ms_cmr_action").val(jsonObj.action);
		$("#rulemod_ms_cmr_application").val(jsonObj.application);
		$("#rulemod_ms_state_case_status").val(jsonObj.master_result);
		$("#rulemod_ms_new_cmr").val(jsonObj.allow_new_cmr);
		$("#rulemod_ms_update_cmr").val(jsonObj.allow_update_cmr);
		$("#rulemod_ms_is_surveillance").val(jsonObj.is_surveillance);

		$("#rule_mod_ms_cmr_dialog").dialog('option', 'buttons', {
				"Save Changes" : function() {
					$(this).dialog("close");
					handleEditSubmit($("#rule_mod_ms_cmr_form"), targetDiv, jsonObj.callback_handler, jsonObj.callback_tbl, jsonObj.focus_id, jsonObj.callback_noun, jsonObj.callback_col);
					},
				"Cancel" : function() {
					$(this).dialog("close");
					}
				});

		$("#rule_mod_ms_cmr_dialog").dialog("open");
	} else {
		return false;
	}
});

$(".add_ms_cmr_rule").button({
	icon: "ui-icon-elrplus",
	showLabel: false
}).off("click").on("click", function(e) {
	e.preventDefault();
	var jsonObj = JSON.parse($(this).val());
	var targetDiv = $(e.target).closest("div").attr('id');

	if (jsonObj.id && jsonObj.action) {
		$("#rulemod_ms_cmr_condition_container").empty();
		$("#rulemod_ms_cmr_condition_counter").val(1);
		$("#rulemod_ms_cmr_condition_container").append("<div class=\"rulemod_cmr_condition\" id=\"rulemod_ms_cmr_condition_1\"></div>");
		$("#rulemod_ms_cmr_condition_1").html("<button type=\"button\" class=\"rulemod_cmr_delete_condition\" value=\"1\" title=\"Delete This Condition\">Delete Condition</button><label for=\"rulemod_ms_cmr_operator_1\">Test Result </label><select class=\"ui-corner-all\" style=\"margin: 0px; max-width: 11em;\" name=\"rulemod_ms_cmr_operator[1]\" id=\"rulemod_ms_cmr_operator_1\" title=\"Choose a comparison type\"><option value=\"0\" selected>--</option></select> <select class=\"ui-corner-all\" style=\"margin: 0px;\" name=\"rulemod_ms_cmr_operand_value[1]\" id=\"rulemod_ms_cmr_operand_value_1\"><option value=\"0\" selected>--</option></select>");
		
		// get operators
		$.ajax({
			type: 'POST',
			data: { type: 1 },
			url: 'ajax/operator.php',
			dataType: 'json',
			async: false
		}).done(function(data) {
			$.each(data, function(idx, val) {
				$("#rulemod_ms_cmr_operator_1").append("<option value='"+val.id+"'>"+val.label+" ("+val.graphical+")</option>");
			});
		});
		
		// get master test results
		$.ajax({
			type: 'GET',
			url: 'ajax/test_result_combined.php',
			dataType: 'json',
			async: false
		}).done(function(data) {
			$.each(data, function(idx, val) {
				$("#rulemod_ms_cmr_operand_value_1").append("<option value='"+val.id+"'>"+val.concept+"</option>");
			});
		});
		
		$("#rulemod_ms_cmr_operator_1").val(0);
		$("#rulemod_ms_cmr_operand_value_1").val(0);

		$(".rulemod_cmr_delete_condition").button({
			icon: "ui-icon-elrclose",
			showLabel: false
		}).off("click").on("click", function(e) {
			var deleteCondition = $(this).val();
			$("#rulemod_ms_cmr_condition_"+deleteCondition).remove();
		});

		$("#rulemod_ms_cmr_organism").html("Add rule for &ldquo;"+jsonObj.organism+"&rdquo;:");
		$("#rulemod_ms_cmr_id").val(jsonObj.id);
		$("#rulemod_ms_cmr_focus_id").val(jsonObj.focus_id);
		$("#rulemod_ms_cmr_action").val(jsonObj.action);
		$("#rulemod_ms_cmr_application").val(jsonObj.application);
		$("#rulemod_ms_state_case_status").val(-1);

		$("#rule_mod_ms_cmr_dialog").dialog('option', 'buttons', {
				"Save Changes" : function() {
					$(this).dialog("close");
					handleEditSubmit($("#rule_mod_ms_cmr_form"), targetDiv, jsonObj.callback_handler, jsonObj.callback_tbl, jsonObj.focus_id, jsonObj.callback_noun, jsonObj.callback_col);
					},
				"Cancel" : function() {
					$(this).dialog("close");
					}
				});

		$("#rule_mod_ms_cmr_dialog").dialog("open");
	} else {
		return false;
	}
});

$(".delete_ms_cmr_rule").button({
	icon: "ui-icon-elrclose",
	showLabel: false
}).off("click").on("click", function(e) {
	e.preventDefault();
	var jsonObj = JSON.parse($(this).val());
	var targetDiv = $(e.target).closest("div").attr('id');

	if (jsonObj.id) {
		$("#confirm_deleterule_ms_cmr_dialog").dialog('option', 'buttons', {
				"Delete" : function() {
					$(this).dialog("close");
					handleRuleDeleteSubmit(targetDiv, jsonObj.callback_handler, jsonObj.callback_tbl, jsonObj.id, jsonObj.focus_id, jsonObj.callback_noun);
					},
				"Cancel" : function() {
					$(this).dialog("close");
					}
				});

		$("#confirm_deleterule_ms_cmr_dialog").dialog("open");
	} else {
		return false;
	}
});

$("#rulemod_ms_cmr_add_condition").button({
	icon: "ui-icon-elrplus"
}).off("click").on("click", function(e) {
	var numConditions = parseInt($("#rulemod_ms_cmr_condition_counter").val());
	$("#rulemod_ms_cmr_condition_counter").val((numConditions+1));
	$("#rulemod_ms_cmr_condition_container").append("<div class=\"rulemod_cmr_condition\" id=\"rulemod_ms_cmr_condition_"+(numConditions+1)+"\"></div>");
	$("#rulemod_ms_cmr_condition_"+(numConditions+1)).html("<button type=\"button\" class=\"rulemod_cmr_delete_condition\" value=\""+(numConditions+1)+"\" title=\"Delete This Condition\">Delete Condition</button><label for=\"rulemod_ms_cmr_operator_"+(numConditions+1)+"\">Test Result </label><select class=\"ui-corner-all\" style=\"margin: 0px; max-width: 11em;\" name=\"rulemod_ms_cmr_operator["+(numConditions+1)+"]\" id=\"rulemod_ms_cmr_operator_"+(numConditions+1)+"\" title=\"Choose a comparison type\"><option value=\"0\" selected>--</option></select> <select class=\"ui-corner-all\" style=\"margin: 0px;\" name=\"rulemod_ms_cmr_operand_value["+(numConditions+1)+"]\" id=\"rulemod_ms_cmr_operand_value_"+(numConditions+1)+"\"><option value=\"0\" selected>--</option></select>");
	
	// get operators
	$.ajax({
		type: 'POST',
		data: { type: 1 },
		url: 'ajax/operator.php',
		dataType: 'json',
		async: false
	}).done(function(data) {
		$.each(data, function(idx, val) {
			$("#rulemod_ms_cmr_operator_"+(numConditions+1)).append("<option value='"+val.id+"'>"+val.label+" ("+val.graphical+")</option>");
		});
	});
	
	// get master test results
	$.ajax({
		type: 'GET',
		url: 'ajax/test_result_combined.php',
		dataType: 'json',
		async: false
	}).done(function(data) {
		$.each(data, function(idx, val) {
			$("#rulemod_ms_cmr_operand_value_"+(numConditions+1)).append("<option value='"+val.id+"'>"+val.concept+"</option>");
		});
	});
	
	$(".rulemod_cmr_delete_condition").button({
		icon: "ui-icon-elrclose",
		showLabel: false
	}).off("click").on("click", function(e) {
		var deleteCondition = $(this).val();
		$("#rulemod_ms_cmr_condition_"+deleteCondition).remove();
	});
});


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

// jQueryUI functions for Graylist Rules Builder functionality

$("#rule_mod_gray_dialog").dialog({
	autoOpen: false,
	width: 700,
	modal: true
});

$("#confirm_deleterule_gray_dialog").dialog({
	autoOpen: false,
	modal: true,
	draggable: false,
	resizable: false,
	width: 400
});

$(".edit_gray_rule").button({
	icon: "ui-icon-elrpencil",
	showLabel: false
}).off("click").on("click", function(e) {
	e.preventDefault();
	var jsonObj = JSON.parse($(this).val());
	var targetDiv = $(e.target).closest("div").attr('id');
	var numConditions = parseInt($("#rulemod_gray_condition_counter").val());

	if (jsonObj.id && jsonObj.action) {
		$("#rulemod_gray_condition_container").empty();
		$("#rulemod_gray_condition_container").append("<div class=\"rulemod_cmr_condition\" id=\"rulemod_gray_condition\"></div>");
		$("#rulemod_gray_condition").html(" \
			<label for=\"rulemod_gray_operator\">Condition </label><select class=\"ui-corner-all\" style=\"margin: 0px; max-width: 11em;\" name=\"rulemod_gray_operator\" id=\"rulemod_gray_operator\" title=\"Choose a comparison type\"></select> <select class=\"ui-corner-all\" style=\"margin: 0px; max-width: 30em;\" name=\"rulemod_gray_operand_value\" id=\"rulemod_gray_operand_value\"><option value=\"-1\" selected>Any</option></select><br><br> \
			<label for=\"rulemod_gray_operator1\"><strong>AND</strong> Test Type </label><select class=\"ui-corner-all\" style=\"margin: 0px; max-width: 11em;\" name=\"rulemod_gray_operator1\" id=\"rulemod_gray_operator1\" title=\"Choose a comparison type\"></select> <select class=\"ui-corner-all\" style=\"margin: 0px;\" name=\"rulemod_gray_operand_value1\" id=\"rulemod_gray_operand_value1\"><option value=\"-1\" selected>Any</option></select><br><br> \
			<label for=\"rulemod_gray_collect_lbound\"><strong>AND</strong> Specimen collected </label><input type=\"text\" class=\"ui-corner-all\" style=\"margin: 0px; max-width: 11em;\" name=\"rulemod_gray_collect_lbound\" id=\"rulemod_gray_collect_lbound\"> before, or <input type=\"text\" class=\"ui-corner-all\" style=\"margin: 0px; max-width: 11em;\" name=\"rulemod_gray_collect_ubound\" id=\"rulemod_gray_collect_ubound\"><label for=\"rulemod_gray_collect_lbound\"> after,</label> Event Date");
		
		// get operators
		$.ajax({
			type: 'POST',
			data: { type: 1 },
			url: 'ajax/operator.php',
			dataType: 'json',
			async: false
		}).done(function(data) {
			$.each(data, function(idx, val) {
				$("#rulemod_gray_operator").append("<option value='"+val.id+"'>"+val.label+" ("+val.graphical+")</option>");
				$("#rulemod_gray_operator1").append("<option value='"+val.id+"'>"+val.label+" ("+val.graphical+")</option>");
			});
		});
		
		// get master test types
		$.ajax({
			type: 'GET',
			url: 'ajax/test_type.php',
			dataType: 'json',
			async: false
		}).done(function(data) {
			$.each(data, function(idx, val) {
				$("#rulemod_gray_operand_value1").append("<option value="+val.id+">"+val.concept+"</option>");
			});
		});
		
		// get master conditions
		$.ajax({
			type: 'GET',
			url: 'ajax/condition.php',
			dataType: 'json',
			async: false
		}).done(function(data) {
			$.each(data, function(idx, val) {
				$("#rulemod_gray_operand_value").append("<option value='"+val.id+"'>"+val.concept+"</option>");
			});
		});
		
		$("#rulemod_gray_operator").val(jsonObj.conditions[0].operator);
		$("#rulemod_gray_operator1").val(jsonObj.conditions[0].operator1);
		$("#rulemod_gray_operand_value").val(jsonObj.conditions[0].operand);
		$("#rulemod_gray_operand_value1").val(jsonObj.conditions[0].operand1);
		$("#rulemod_gray_collect_lbound").val(jsonObj.conditions[0].collect_lbound);
		$("#rulemod_gray_collect_ubound").val(jsonObj.conditions[0].collect_ubound);

		$("#rulemod_gray_organism").html("Edit Graylist Rule for &ldquo;"+jsonObj.disease+"&rdquo;:");
		$("#rulemod_gray_id").val(jsonObj.id);
		$("#rulemod_gray_focus_id").val(jsonObj.focus_id);
		$("#rulemod_gray_action").val(jsonObj.action);
		$("#rulemod_gray_application").val(jsonObj.application);

		$("#rule_mod_gray_dialog").dialog('option', 'buttons', {
				"Save Changes" : function() {
					$(this).dialog("close");
					handleEditSubmit($("#rule_mod_gray_form"), targetDiv, jsonObj.callback_handler, jsonObj.callback_tbl, jsonObj.focus_id, jsonObj.callback_noun, jsonObj.callback_col);
					},
				"Cancel" : function() {
					$(this).dialog("close");
					}
				});

		$("#rule_mod_gray_dialog").dialog("open");
	} else {
		return false;
	}
});



$(".add_gray_rule").button({
	icon: "ui-icon-elrplus",
	showLabel: false
}).off("click").on("click", function(e) {
	e.preventDefault();
	var jsonObj = JSON.parse($(this).val());
	var targetDiv = $(e.target).closest("div").attr('id');
	
	if (jsonObj.id && jsonObj.action) {
		$("#rulemod_gray_condition_container").empty();
		$("#rulemod_gray_condition_container").append("<div class=\"rulemod_cmr_condition\" id=\"rulemod_gray_condition\"></div>");
		$("#rulemod_gray_condition").html(" \
			<label for=\"rulemod_gray_operator\">Condition </label><select class=\"ui-corner-all\" style=\"margin: 0px; max-width: 11em;\" name=\"rulemod_gray_operator\" id=\"rulemod_gray_operator\" title=\"Choose a comparison type\"></select> <select class=\"ui-corner-all\" style=\"margin: 0px; max-width: 30em;\" name=\"rulemod_gray_operand_value\" id=\"rulemod_gray_operand_value\"><option value=\"-1\" selected>Any</option></select><br><br> \
			<label for=\"rulemod_gray_operator1\"><strong>AND</strong> Test Type </label><select class=\"ui-corner-all\" style=\"margin: 0px; max-width: 11em;\" name=\"rulemod_gray_operator1\" id=\"rulemod_gray_operator1\" title=\"Choose a comparison type\"></select> <select class=\"ui-corner-all\" style=\"margin: 0px;\" name=\"rulemod_gray_operand_value1\" id=\"rulemod_gray_operand_value1\"><option value=\"-1\" selected>Any</option></select><br><br> \
			<label for=\"rulemod_gray_collect_lbound\"><strong>AND</strong> Specimen collected </label><input type=\"text\" class=\"ui-corner-all\" style=\"margin: 0px; max-width: 11em;\" name=\"rulemod_gray_collect_lbound\" id=\"rulemod_gray_collect_lbound\"> before, or <input type=\"text\" class=\"ui-corner-all\" style=\"margin: 0px; max-width: 11em;\" name=\"rulemod_gray_collect_ubound\" id=\"rulemod_gray_collect_ubound\"><label for=\"rulemod_gray_collect_lbound\"> after,</label> Event Date");
		
		$.ajax({
			type: 'POST',
			data: { type: 1 },
			url: 'ajax/operator.php',
			dataType: 'json',
			async: false
		}).done(function(data) {
			$.each(data, function(idx, val) {
				$("#rulemod_gray_operator").append("<option value='"+val.id+"'>"+val.label+" ("+val.graphical+")</option>");
				$("#rulemod_gray_operator1").append("<option value='"+val.id+"'>"+val.label+" ("+val.graphical+")</option>");
			});
		});
		
		// get master test types
		$.ajax({
			type: 'GET',
			url: 'ajax/test_type.php',
			dataType: 'json',
			async: false
		}).done(function(data) {
			$.each(data, function(idx, val) {
				$("#rulemod_gray_operand_value1").append("<option value='"+val.id+"'>"+val.concept+"</option>");
			});
		});
		
		// get master conditions
		$.ajax({
			type: 'GET',
			url: 'ajax/condition.php',
			dataType: 'json',
			async: false
		}).done(function(data) {
			$.each(data, function(idx, val) {
				$("#rulemod_gray_operand_value").append("<option value='"+val.id+"'>"+val.concept+"</option>");
			});
		});
		
		$("#rulemod_gray_operator").val(1);
		$("#rulemod_gray_operator1").val(1);
		$("#rulemod_gray_collect_lbound").val('30 days');
		$("#rulemod_gray_collect_ubound").val('7 days');
		
		$("#rulemod_gray_organism").html("Add Graylist Rule for &ldquo;"+jsonObj.disease+"&rdquo;:");
		$("#rulemod_gray_id").val(jsonObj.id);
		$("#rulemod_gray_focus_id").val(jsonObj.focus_id);
		$("#rulemod_gray_action").val(jsonObj.action);
		$("#rulemod_gray_application").val(jsonObj.application);

		$("#rule_mod_gray_dialog").dialog('option', 'buttons', {
				"Save Changes" : function() {
					$(this).dialog("close");
					handleEditSubmit($("#rule_mod_gray_form"), targetDiv, jsonObj.callback_handler, jsonObj.callback_tbl, jsonObj.focus_id, jsonObj.callback_noun, jsonObj.callback_col);
					},
				"Cancel" : function() {
					$(this).dialog("close");
					}
				});

		$("#rule_mod_gray_dialog").dialog("open");
	} else {
		return false;
	}
});



$(".delete_gray_rule").button({
	icon: "ui-icon-elrclose",
	showLabel: false
}).off("click").on("click", function(e) {
	e.preventDefault();
	var jsonObj = JSON.parse($(this).val());
	var targetDiv = $(e.target).closest("div").attr('id');

	if (jsonObj.id) {
		$("#confirm_deleterule_gray_dialog").dialog('option', 'buttons', {
				"Delete" : function() {
					$(this).dialog("close");
					handleRuleDeleteSubmit(targetDiv, jsonObj.callback_handler, jsonObj.callback_tbl, jsonObj.id, jsonObj.focus_id, jsonObj.callback_noun);
					},
				"Cancel" : function() {
					$(this).dialog("close");
					}
				});

		$("#confirm_deleterule_gray_dialog").dialog("open");
	} else {
		return false;
	}
});
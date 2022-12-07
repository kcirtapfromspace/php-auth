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
?>
$("#rule_mod_dialog").dialog({
	autoOpen: false,
	width: 700,
	modal: true
});

$("#confirm_deleterule_dialog").dialog({
	autoOpen: false,
	modal: true,
	draggable: false,
	resizable: false,
	width: 400
});

$("#rulemod_add_condition").button({
	icon: "ui-icon-elrplus"
}).on("click", function(e) {
	var numConditions = parseInt($("#rulemod_condition_counter").val());
	$("#rulemod_condition_counter").val((numConditions+1));
	$("#rulemod_condition_container").append("<div class=\"rulemod_condition\" id=\"rulemod_condition_"+(numConditions+1)+"\"></div>");
	$("#rulemod_condition_"+(numConditions+1)).html("<button type=\"button\" class=\"rulemod_delete_condition\" value=\""+(numConditions+1)+"\" title=\"Delete This Condition\">Delete Condition</button><label for=\"rulemod_operator_"+(numConditions+1)+"\">Result </label><select class=\"ui-corner-all\" style=\"margin: 0; max-width: 20em;\" name=\"rulemod_operator["+(numConditions+1)+"]\" id=\"rulemod_operator_"+(numConditions+1)+"\" title=\"Choose a comparison type\"></select> <label for=\"rulemod_operand_value_"+(numConditions+1)+"\"> this value:</label> <input class=\"ui-corner-all\" type=\"text\" name=\"rulemod_operand_value["+(numConditions+1)+"]\" id=\"rulemod_operand_value_"+(numConditions+1)+"\" title=\"Compare message result with this value\" />");

    $.ajax({
        type: 'GET',
        url: 'ajax/operator.php',
        dataType: 'json'
    }).done(function(data) {
        let operatorOptions = [];
        operatorOptions[operatorOptions.length] = "<option value='0' selected>--</option>";
        if (data.length) {
            for (operator in data) {
                html = "<option value='" + parseInt(data[operator].id) + "'>" + escapeHtml(data[operator].label) + "(" + data[operator].graphical + ")</option>";
                operatorOptions[operatorOptions.length] = html;
            }
            $("#rulemod_operator_"+(numConditions+1)).empty().append(operatorOptions.join(''));
        }
    });

	$(".rulemod_delete_condition").button({
		icon: "ui-icon-elrclose",
		showLabel: false
	}).on("click", function(e) {
		var deleteCondition = $(this).val();
		$("#rulemod_condition_"+deleteCondition).remove();
	});
});

$(".add_result_rule").button({
	icon: "ui-icon-elrplus",
	showLabel: false
}).on("click", function(e) {
	e.preventDefault();
	var jsonObj = JSON.parse($(this).val());

	if (jsonObj.id && jsonObj.action) {
		$("#rulemod_condition_container").empty();
		$("#rulemod_condition_counter").val(1);
		$("#rulemod_condition_container").append("<div class=\"rulemod_condition\" id=\"rulemod_condition_1\"></div>");
		$("#rulemod_condition_1").html("<button type=\"button\" class=\"rulemod_delete_condition\" value=\"1\" title=\"Delete This Condition\">Delete Condition</button><label for=\"rulemod_operator_1\">Result </label><select class=\"ui-corner-all\" style=\"margin: 0; max-width: 20em;\" name=\"rulemod_operator[1]\" id=\"rulemod_operator_1\" title=\"Choose a comparison type\"> <label for=\"rulemod_operand_value_1\"> this value:</label> <input class=\"ui-corner-all\" type=\"text\" name=\"rulemod_operand_value[1]\" id=\"rulemod_operand_value_1\" title=\"Compare message result with this value\" />");

        $.ajax({
            type: 'GET',
            url: 'ajax/operator.php',
            dataType: 'json'
        }).done(function(data) {
            let operatorOptions = [];
            operatorOptions[operatorOptions.length] = "<option value='0' selected>--</option>";
            if (data.length) {
                for (operator in data) {
                    html = "<option value='" + parseInt(data[operator].id) + "'>" + escapeHtml(data[operator].label) + "(" + data[operator].graphical + ")</option>";
                    operatorOptions[operatorOptions.length] = html;
                }
                $("#rulemod_operator_1").empty().append(operatorOptions.join(''));
            }
        });

		$("#rulemod_operator_1").val(0);
		$("#rulemod_operand_value_1").val("");

		$(".rulemod_delete_condition").button({
			icon: "ui-icon-elrclose",
			showLabel: false
		}).on("click", function(e) {
			var deleteCondition = $(this).val();
			$("#rulemod_condition_"+deleteCondition).remove();
		});

		$("#rulemod_child_loinc").html("Add rule for "+jsonObj.lab_name+" LOINC &ldquo;"+jsonObj.child_loinc+"&rdquo;:");
		$("#rulemod_id").val(jsonObj.id);
		$("#rulemod_focus_id").val(jsonObj.focus_id);
		$("#rulemod_action").val(jsonObj.action);
		$("#rulemod_application").val(jsonObj.application);
		$("#rulemod_comments").val("");

        $.ajax({
            type: 'GET',
            url: 'ajax/test_result_combined.php',
            dataType: 'json'
        }).done(function(data) {
            let testResultOptions = [];
            testResultOptions[testResultOptions.length] = "<option value='0' selected>--</option>";
            if (data.length) {
                for (testResult in data) {
                    html = "<option value='" + parseInt(data[testResult].id) + "'>" + escapeHtml(data[testResult].concept) + "</option>";
                    testResultOptions[testResultOptions.length] = html;
                }
                $("#rulemod_master_result").empty().append(testResultOptions.join(''));
            }
        });

        $("#rulemod_master_result").val(0);

		$("#rule_mod_dialog").dialog('option', 'buttons', {
				"Save Changes" : function() {
					$(this).dialog("close");
					$("#rule_mod_form").trigger("submit");
					},
				"Cancel" : function() {
					$(this).dialog("close");
					}
				});

		$("#rule_mod_dialog").dialog("open");
	} else {
		return false;
	}
});

$(".edit_result_rule").button({
	icon: "ui-icon-elrpencil",
	showLabel: false
}).on("click", function(e) {
	e.preventDefault();
	var jsonObj = JSON.parse($(this).val());
	var numConditions = parseInt($("#rulemod_condition_counter").val());

	if (jsonObj.id && jsonObj.action) {
		$("#rulemod_condition_container").empty();
		$("#rulemod_condition_counter").val((numConditions+jsonObj.conditions.length));

		for (var i = 0; i < jsonObj.conditions.length; i++) {
			$("#rulemod_condition_container").append("<div class=\"rulemod_condition\" id=\"rulemod_condition_"+(i)+"\"></div>");
			$("#rulemod_condition_"+(i)).html("<button type=\"button\" class=\"rulemod_delete_condition\" value=\""+(i)+"\" title=\"Delete This Condition\">Delete Condition</button><label for=\"rulemod_operator_"+(i)+"\">Result </label><select class=\"ui-corner-all\" style=\"margin: 0; max-width: 20em;\" name=\"rulemod_operator["+(i)+"]\" id=\"rulemod_operator_"+(i)+"\" title=\"Choose a comparison type\"></select> <label for=\"rulemod_operand_value_"+(i)+"\"> this value:</label> <input class=\"ui-corner-all\" type=\"text\" name=\"rulemod_operand_value["+(i)+"]\" id=\"rulemod_operand_value_"+(i)+"\" title=\"Compare message result with this value\" />");

            (function(idx) {
                $.ajax({
                    type: 'GET',
                    url: 'ajax/operator.php',
                    dataType: 'json'
                }).done(function(data) {
                    let operatorOptions = [];
                    operatorOptions[operatorOptions.length] = "<option value='0' selected>--</option>";
                    if (data.length) {
                        for (operator in data) {
                            html = "<option value='" + parseInt(data[operator].id) + "'>" + escapeHtml(data[operator].label) + "(" + data[operator].graphical + ")</option>";
                            operatorOptions[operatorOptions.length] = html;
                        }
                        $("#rulemod_operator_"+(idx)).empty().append(operatorOptions.join(''));
                    }
                    $("#rulemod_operator_"+(idx)).val(jsonObj.conditions[idx].operator);
                    $("#rulemod_operand_value_"+(idx)).val(jsonObj.conditions[idx].operand);
                });
            })(i);
		}

		$(".rulemod_delete_condition").button({
			icon: "ui-icon-elrclose",
			showLabel: false
		}).on("click", function(e) {
			var deleteCondition = $(this).val();
			$("#rulemod_condition_"+deleteCondition).remove();
		});

		$("#rulemod_child_loinc").html("Edit rule for "+jsonObj.lab_name+" LOINC &ldquo;"+jsonObj.child_loinc+"&rdquo;:");
		$("#rulemod_id").val(jsonObj.id);
		$("#rulemod_focus_id").val(jsonObj.focus_id);
		$("#rulemod_action").val(jsonObj.action);
		$("#rulemod_application").val(jsonObj.application);
		$("#rulemod_comments").val(jsonObj.comments);

        $.ajax({
            type: 'GET',
            url: 'ajax/test_result_combined.php',
            dataType: 'json'
        }).done(function(data) {
            let testResultOptions = [];
            testResultOptions[testResultOptions.length] = "<option value='0' selected>--</option>";
            if (data.length) {
                for (testResult in data) {
                    html = "<option value='" + parseInt(data[testResult].id) + "'>" + escapeHtml(data[testResult].concept) + "</option>";
                    testResultOptions[testResultOptions.length] = html;
                }
                $("#rulemod_master_result").empty().append(testResultOptions.join(''));
            }
            $("#rulemod_master_result").val(jsonObj.master_result);
        });

		$("#rule_mod_dialog").dialog('option', 'buttons', {
				"Save Changes" : function() {
					$(this).dialog("close");
					$("#rule_mod_form").trigger("submit");
					},
				"Cancel" : function() {
					$(this).dialog("close");
					}
				});

		$("#rule_mod_dialog").dialog("open");
	} else {
		return false;
	}
});

$(".delete_result_rule").button({
	icon: "ui-icon-elrclose",
	showLabel: false
}).on("click", function(e) {
	e.preventDefault();
	var jsonObj = JSON.parse($(this).val());

	if (jsonObj.id) {
		var deleteRuleAction = "<?php echo $webappBaseUrl; ?>?selected_page=6&submenu=3&cat=<?php echo $navCat; ?>&subcat=<?php echo $navSubcat; ?>&rulemod_action=delete&rulemod_id="+jsonObj.id+"&focus_id="+jsonObj.focus_id;

		$("#confirm_deleterule_dialog").dialog('option', 'buttons', {
				"Delete" : function() {
					window.location.href = deleteRuleAction;
					},
				"Cancel" : function() {
					$(this).dialog("close");
					}
				});

		$("#confirm_deleterule_dialog").dialog("open");
	} else {
		return false;
	}
});


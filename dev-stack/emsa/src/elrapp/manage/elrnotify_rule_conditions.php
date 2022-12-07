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

include_once __DIR__ . '/../includes/rule_condition_management_functions.php';

$valid_actions = array(
    'add_group',
    'add_link',
    'edit_link',
    'delete_group',
    'delete_link'
);

$clean_params = (object) array(
            'rule_id' => ((isset($_REQUEST['cmod__rule_id']) && (filter_var($_REQUEST['cmod__rule_id'], FILTER_VALIDATE_INT) !== false)) ? intval(trim($_REQUEST['cmod__rule_id'])) : null),
            'link_id' => ((isset($_REQUEST['cmod__link_id']) && (filter_var($_REQUEST['cmod__link_id'], FILTER_VALIDATE_INT) !== false)) ? intval(trim($_REQUEST['cmod__link_id'])) : null),
            'left_id' => ((isset($_REQUEST['cmod__left_id']) && (filter_var($_REQUEST['cmod__left_id'], FILTER_VALIDATE_INT) !== false)) ? intval(trim($_REQUEST['cmod__left_id'])) : 0),
            'parent_chain_id' => ((isset($_REQUEST['cmod__parent_chain_id']) && (filter_var($_REQUEST['cmod__parent_chain_id'], FILTER_VALIDATE_INT) !== false)) ? intval(trim($_REQUEST['cmod__parent_chain_id'])) : 0),
            'type_left' => ((isset($_REQUEST['cmod__type_left']) && (filter_var($_REQUEST['cmod__type_left'], FILTER_VALIDATE_INT) !== false)) ? intval(trim($_REQUEST['cmod__type_left'])) : null),
            'type_right' => ((isset($_REQUEST['cmod__type_right']) && (filter_var($_REQUEST['cmod__type_right'], FILTER_VALIDATE_INT) !== false)) ? intval(trim($_REQUEST['cmod__type_right'])) : null),
            'operator_id' => ((isset($_REQUEST['cmod__operator_id']) && (filter_var($_REQUEST['cmod__operator_id'], FILTER_VALIDATE_INT) !== false)) ? intval(trim($_REQUEST['cmod__operator_id'])) : 0),
            'link_operator_id' => ((isset($_REQUEST['cmod__link_operator_id']) && (filter_var($_REQUEST['cmod__link_operator_id'], FILTER_VALIDATE_INT) !== false)) ? intval(trim($_REQUEST['cmod__link_operator_id'])) : 0),
            'operand_left' => ((isset($_REQUEST['cmod__operand_left']) && (strlen(trim($_REQUEST['cmod__operand_left'])) > 0)) ? trim($_REQUEST['cmod__operand_left']) : null),
            'operand_right' => ((isset($_REQUEST['cmod__operand_right']) && (strlen(trim($_REQUEST['cmod__operand_right'])) > 0)) ? trim($_REQUEST['cmod__operand_right']) : null),
            'link_type' => ((isset($_REQUEST['cmod__link_type']) && (filter_var($_REQUEST['cmod__link_type'], FILTER_VALIDATE_INT) !== false)) ? intval(trim($_REQUEST['cmod__link_type'])) : null)
);

if (isset($_REQUEST['cmod__action']) && in_array(trim($_REQUEST['cmod__action']), $valid_actions)) {
    if (trim($_REQUEST['cmod__action']) == 'add_group') {
        // add a new chain to a rule
        if (!is_null($clean_params->rule_id) && !is_null($clean_params->parent_chain_id) && !is_null($clean_params->left_id)) {
            $sql = 'INSERT INTO ' . $emsaDbSchemaPrefix . 'bn_expression_chain (
                        rule_id, 
                        parent_chain_id, 
                        left_id, 
                        left_operator_id, 
                        link_type, 
                        link_id
                    ) VALUES (
                        ' . $clean_params->rule_id . ', 
                        ' . $clean_params->parent_chain_id . ', 
                        ' . $clean_params->left_id . ', 
                        ' . $clean_params->operator_id . ', 
                        ' . \Udoh\Emsa\Email\Notification::LINKTYPE_CHAIN . ', 
                        ' . ((!is_null($clean_params->link_id)) ? $clean_params->link_id : 'NULL') . ' 
                    ) RETURNING id;';
            $rs = @pg_query($host_pa, $sql);
            if ($rs === false) {
                \Udoh\Emsa\Utils\DisplayUtils::drawError('Unable to add New Condition group, the following errors were encountered:');
            } else {
                $new_group_id = intval(@pg_fetch_result($rs, 0, 'id'));
                demoteChain($clean_params->rule_id, $clean_params->parent_chain_id, $clean_params->left_id, $new_group_id);  // insert the new item into the chain, fix order of existing items
                \Udoh\Emsa\Utils\DisplayUtils::drawHighlight('New Condition Group added!', 'ui-icon-elrsuccess');
            }
        } else {
            \Udoh\Emsa\Utils\DisplayUtils::drawError('Unable to add new Condition Group:  Missing required fields.');
        }
    }

    if (trim($_REQUEST['cmod__action']) == 'add_link') {
        // add a new epression link to a chain
        if (
                !is_null($clean_params->rule_id) && !is_null($clean_params->parent_chain_id) && !is_null($clean_params->left_id) && !is_null($clean_params->operator_id) && !is_null($clean_params->type_left) && !is_null($clean_params->type_right) && !is_null($clean_params->operand_left) && !is_null($clean_params->operand_right) && !is_null($clean_params->link_operator_id)
        ) {
            // insert new row into link table, return id from that
            $insert_link_sql = 'INSERT INTO ' . $emsaDbSchemaPrefix . 'bn_expression_link (
                                    type_left, 
                                    type_right, 
                                    operand_left, 
                                    operand_right, 
                                    operator_id
                                ) VALUES (
                                    ' . intval($clean_params->type_left) . ',
                                    ' . intval($clean_params->type_right) . ',
                                    \'' . pg_escape_string(trim($clean_params->operand_left)) . '\',
                                    \'' . pg_escape_string(trim($clean_params->operand_right)) . '\',
                                    ' . intval($clean_params->link_operator_id) . '
                                ) 
                                RETURNING id;';
            $insert_link_rs = @pg_query($host_pa, $insert_link_sql);
            if ($insert_link_rs !== false) {
                // insert new row into chian table with id from new link
                $new_link_id = intval(@pg_fetch_result($insert_link_rs, 0, 'id'));
                $insert_linkchain_sql = 'INSERT INTO ' . $emsaDbSchemaPrefix . 'bn_expression_chain (
                                             rule_id, 
                                             parent_chain_id, 
                                             left_id, 
                                             left_operator_id, 
                                             link_type, 
                                             link_id
                                         ) VALUES (
                                             ' . $clean_params->rule_id . ', 
                                             ' . $clean_params->parent_chain_id . ', 
                                             ' . $clean_params->left_id . ', 
                                             ' . $clean_params->operator_id . ', 
                                             ' . \Udoh\Emsa\Email\Notification::LINKTYPE_LINK . ', 
                                             ' . $new_link_id . ' 
                                         ) RETURNING id;';
                $insert_linkchain_rs = @pg_query($host_pa, $insert_linkchain_sql);
                if ($insert_linkchain_rs === false) {
                    \Udoh\Emsa\Utils\DisplayUtils::drawError('Unable to insert new Condition, the following errors were encountered:');
                } else {
                    $new_linkchain_id = intval(@pg_fetch_result($insert_linkchain_rs, 0, 'id'));
                    demoteChain($clean_params->rule_id, $clean_params->parent_chain_id, $clean_params->left_id, $new_linkchain_id);  // insert the new item into the chain, fix order of existing items
                    \Udoh\Emsa\Utils\DisplayUtils::drawHighlight('New Condition added!', 'ui-icon-elrsuccess');
                }
            } else {
                \Udoh\Emsa\Utils\DisplayUtils::drawError('Unable to insert new Condition, the following errors were encountered:');
            }
        } else {
            \Udoh\Emsa\Utils\DisplayUtils::drawError('Unable to add new Condition; Missing required fields.');
        }
    }

    if (trim($_REQUEST['cmod__action']) == 'edit_link') {
        // modify an expression link
        if (
                !is_null($clean_params->rule_id) && !is_null($clean_params->parent_chain_id) && !is_null($clean_params->left_id) && !is_null($clean_params->operator_id) && !is_null($clean_params->link_id) && !is_null($clean_params->type_left) && !is_null($clean_params->type_right) && !is_null($clean_params->link_operator_id) && !is_null($clean_params->operand_left) && !is_null($clean_params->operand_right)
        ) {
            // save changes to link first
            $save_link_sql = 'UPDATE ' . $emsaDbSchemaPrefix . 'bn_expression_link SET 
						type_left = ' . $clean_params->type_left . ', 
						type_right = ' . $clean_params->type_right . ', 
						operator_id = ' . $clean_params->link_operator_id . ', 
						operand_left = \'' . @pg_escape_string($clean_params->operand_left) . '\', 
						operand_right = \'' . @pg_escape_string($clean_params->operand_right) . '\' 
					WHERE id = ' . $clean_params->link_id . ';';
            $save_link_rs = @pg_query($host_pa, $save_link_sql);
            if ($save_link_rs === false) {
                \Udoh\Emsa\Utils\DisplayUtils::drawError('Unable to save changes to Condition, the following errors were encountered:');
            } else {
                // then update the operator on the chain
                $save_chain_sql = 'UPDATE ' . $emsaDbSchemaPrefix . 'bn_expression_chain SET 
							left_operator_id = ' . $clean_params->operator_id . ' 
						WHERE rule_id = ' . $clean_params->rule_id . ' 
						AND parent_chain_id = ' . $clean_params->parent_chain_id . ' 
						AND link_id = ' . $clean_params->link_id . ';';
                $save_chain_rs = @pg_query($host_pa, $save_chain_sql);
                if ($save_chain_rs === false) {
                    \Udoh\Emsa\Utils\DisplayUtils::drawError('Unable to save changes to Condition, the following errors were encountered:');
                } else {
                    \Udoh\Emsa\Utils\DisplayUtils::drawHighlight('Condition updated!', 'ui-icon-elrsuccess');
                }
            }
        } else {
            \Udoh\Emsa\Utils\DisplayUtils::drawError('Unable to save changes to Condition; Missing required fields.');
        }
    }

    if (trim($_REQUEST['cmod__action']) == 'delete_group') {
        // remove an entire chain & all descendents
        if (!is_null($clean_params->rule_id) && !is_null($clean_params->parent_chain_id) && !is_null($clean_params->left_id)) {
            // find all descendent links & chains
            if (deleteChain($clean_params->rule_id, $clean_params->parent_chain_id)) {
                promoteChain($clean_params->rule_id, $clean_params->parent_chain_id, $clean_params->left_id);  // reset the order of the items in the chain
                \Udoh\Emsa\Utils\DisplayUtils::drawHighlight('Condition Group deleted successfully!', 'ui-icon-elrsuccess');
            } else {
                \Udoh\Emsa\Utils\DisplayUtils::drawError('Unable to delete Condition Group, the following errors were encountered:');
            }
        } else {
            \Udoh\Emsa\Utils\DisplayUtils::drawError('Unable to delete Condition Group; No ID specified!');
        }
    }

    if (trim($_REQUEST['cmod__action']) == 'delete_link') {
        // remove an expression link from a chain
        if (!is_null($clean_params->rule_id) && !is_null($clean_params->link_id) && !is_null($clean_params->left_id)) {
            // find all descendent links & chains
            if (deleteLink($clean_params->rule_id, $clean_params->link_id)) {
                promoteChain($clean_params->rule_id, $clean_params->link_id, $clean_params->left_id);  // reset the order of the items in the chain
                \Udoh\Emsa\Utils\DisplayUtils::drawHighlight('Condition deleted successfully!', 'ui-icon-elrsuccess');
            } else {
                \Udoh\Emsa\Utils\DisplayUtils::drawError('Unable to delete Condition, the following errors were encountered:');
            }
        } else {
            \Udoh\Emsa\Utils\DisplayUtils::drawError('Unable to delete Condition; No ID specified!');
        }
    }
}
?>

<script>
    $(function() {
        $("#confirm_delete_dialog").dialog({
            autoOpen: false,
            modal: true,
            draggable: false,
            resizable: false
        });

        $("#modal__add_group").dialog({
            autoOpen: false,
            modal: true,
            draggable: true,
            resizable: false
        });

        $("#modal__add_link").dialog({
            autoOpen: false,
            modal: true,
            draggable: true,
            resizable: false,
            width: 400
        });

        $("#modal__edit_link").dialog({
            autoOpen: false,
            modal: true,
            draggable: true,
            resizable: false,
            width: 400
        });

        $("#modal__add_link__link_left_param").on("change", function() {
            var paramDataType = $('option:selected', this).attr('rel');

            // clear operator & value fields to prevent weird spillover 
            // between boolean & non-boolean options
            $("#modal__add_link__link_right_operand").val('');
            $("#modal__add_link__link_operator_id").val(0);

            if (paramDataType == 'Boolean') {
                $("#modal__add_link__boolean_param").show();
                $("#modal__add_link__nonboolean_param").hide();
            } else {
                $("#modal__add_link__boolean_param").hide();
                $("#modal__add_link__nonboolean_param").show();
            }
        });

        $("#modal__add_link__link_boolean_truefalse").on("change", function() {
            if ($(this).val() == 1) {
                // true
                $("#modal__add_link__link_type_right").val(<?php echo \Udoh\Emsa\Email\Notification::OPTYPE_VALUE; ?>);
                $("#modal__add_link__link_operator_id option").filter(function() {
                    return $(this).text() === "Equal";
                }).prop('selected', 'selected');
                $("#modal__add_link__link_right_operand").val(1);
            } else {
                // false
                $("#modal__add_link__link_type_right").val(<?php echo \Udoh\Emsa\Email\Notification::OPTYPE_VALUE; ?>);
                $("#modal__add_link__link_operator_id option").filter(function() {
                    return $(this).text() === "Equal";
                }).prop('selected', 'selected');
                $("#modal__add_link__link_right_operand").val(0);
            }
        });

        $("#modal__edit_link__link_left_param").on("change", function() {
            var paramDataType = $('option:selected', this).attr('rel');

            // clear operator & value fields to prevent weird spillover 
            // between boolean & non-boolean options
            $("#modal__edit_link__link_right_operand").val('');
            $("#modal__edit_link__link_operator_id").val(0);

            if (paramDataType == 'Boolean') {
                $("#modal__edit_link__boolean_param").show();
                $("#modal__edit_link__nonboolean_param").hide();
            } else {
                $("#modal__edit_link__boolean_param").hide();
                $("#modal__edit_link__nonboolean_param").show();
            }
        });

        $("#modal__edit_link__link_boolean_truefalse").on("change", function() {
            if ($(this).val() == 1) {
                // true
                $("#modal__edit_link__link_type_right").val(<?php echo \Udoh\Emsa\Email\Notification::OPTYPE_VALUE; ?>);
                $("#modal__edit_link__link_operator_id option").filter(function() {
                    return $(this).text() === "Equal";
                }).prop('selected', 'selected');
                $("#modal__edit_link__link_right_operand").val(1);
            } else {
                // false
                $("#modal__edit_link__link_type_right").val(<?php echo \Udoh\Emsa\Email\Notification::OPTYPE_VALUE; ?>);
                $("#modal__edit_link__link_operator_id option").filter(function() {
                    return $(this).text() === "Equal";
                }).prop('selected', 'selected');
                $("#modal__edit_link__link_right_operand").val(0);
            }
        });

        /*
         * future use
         $("#modal__add_link__link_type_right").on("change", function() {
         if ($(this).val() == < ?php echo \Udoh\Emsa\Email\Notification::OPTYPE_PARAMETER; ? >) {
         $("#modal__add_link__link_right_operand").prop('disabled', 'disabled');
         $("#modal__add_link__link_right_param").prop('disabled', false);
         $("#modal__add_link__typeof_param").show();
         $("#modal__add_link__typeof_value").hide();
         } else {
         $("#modal__add_link__link_right_param").prop('disabled', 'disabled');
         $("#modal__add_link__link_right_operand").prop('disabled', false);
         $("#modal__add_link__typeof_value").show();
         $("#modal__add_link__typeof_param").hide();
         }
         });
         */

        $("#save_placeholder").button({
            icon: "ui-icon-elrsave"
        });

        $("#ruleconditions_cancel").button({
            icon: "ui-icon-elrback"
        }).on("click", function() {
            var cancelAction = "<?php echo $webappBaseUrl; ?>?selected_page=6&submenu=9&cat=4";
            window.location.href = cancelAction;
        });

        $(".chain_action_add_expression").button({
            icon: "ui-icon-elrplus",
            showLabel: false
        }).on("click", function(e) {
            // add new link
            e.preventDefault();
            var jsonObj = JSON.parse($(this).val());

            if (jsonObj.rule_id) {
                $("#edit_link__rule_id").val(jsonObj.rule_id);
                $("#edit_link__parent_chain_id").val(jsonObj.parent_chain_id);
                $("#edit_link__left_id").val(jsonObj.left_id);
                $("#edit_link__action").val('add_link');
                $("#edit_link__link_type").val(<?php echo \Udoh\Emsa\Email\Notification::LINKTYPE_LINK; ?>);

                $("#modal__add_link").dialog('option', 'buttons', {
                    "Add New Rule Condition": function() {
                        $("#edit_link__operator_id").val($("#modal__add_link__operator_id").val());
                        $("#edit_link__link_operator_id").val($("#modal__add_link__link_operator_id").val());
                        $("#edit_link__operand_left").val($("#modal__add_link__link_left_param").val());
                        $("#edit_link__operand_right").val($("#modal__add_link__link_right_operand").val());
                        $("#edit_link__type_left").val($("#modal__add_link__link_type_left").val());
                        $("#edit_link__type_right").val($("#modal__add_link__link_type_right").val());
                        $("#form__edit_link").trigger("submit");
                    },
                    "Cancel": function() {
                        $(this).dialog("close");
                    }
                });

                if (parseInt($("#edit_link__left_id").val()) == 0) {
                    $("#modal__add_link__operator_id").prop('disabled', 'disabled');
                } else {
                    $("#modal__add_link__operator_id").prop('disabled', false);
                }
                $("#modal__add_link").dialog("open");
            }
        })
                .next().button({
            icon: "ui-icon-elraddfolder",
            showLabel: false
        }).on("click", function(e) {
            // add new chain
            e.preventDefault();
            var jsonObj = JSON.parse($(this).val());

            if (jsonObj.rule_id) {
                $("#edit_group__rule_id").val(jsonObj.rule_id);
                $("#edit_group__parent_chain_id").val(jsonObj.parent_chain_id);
                $("#edit_group__left_id").val(jsonObj.left_id);
                $("#edit_group__action").val('add_group');
                $("#edit_group__link_type").val(<?php echo \Udoh\Emsa\Email\Notification::LINKTYPE_CHAIN; ?>);

                $("#modal__add_group").dialog('option', 'buttons', {
                    "Add New Group": function() {
                        $("#edit_group__operator_id").val($("#modal__add_group__operator_id").val());
                        $("#form__edit_group").trigger("submit");
                    },
                    "Cancel": function() {
                        $(this).dialog("close");
                    }
                });

                if (parseInt($("#edit_group__left_id").val()) == 0) {
                    $("#modal__add_group__operator_id").prop('disabled', 'disabled');
                } else {
                    $("#modal__add_group__operator_id").prop('disabled', false);
                }
                $("#modal__add_group").dialog("open");
            }
        })
                .next().button({
            icon: "ui-icon-elrpencil",
            showLabel: false
        }).on("click", function(e) {
            // edit link
            e.preventDefault();
            var jsonObj = JSON.parse($(this).val());

            if (jsonObj.rule_id) {
                $("#edit_link__rule_id").val(jsonObj.rule_id);
                $("#edit_link__parent_chain_id").val(jsonObj.parent_chain_id);
                $("#edit_link__link_id").val(jsonObj.link_id);
                $("#edit_link__action").val('edit_link');

                $("#modal__edit_link").dialog('option', 'buttons', {
                    "Save Changes": function() {
                        $("#edit_link__operator_id").val($("#modal__edit_link__operator_id").val());
                        $("#edit_link__link_operator_id").val($("#modal__edit_link__link_operator_id").val());
                        $("#edit_link__operand_left").val($("#modal__edit_link__link_left_param").val());
                        $("#edit_link__operand_right").val($("#modal__edit_link__link_right_operand").val());
                        $("#edit_link__type_left").val($("#modal__edit_link__link_type_left").val());
                        $("#edit_link__type_right").val($("#modal__edit_link__link_type_right").val());
                        $("#form__edit_link").trigger("submit");
                    },
                    "Cancel": function() {
                        $(this).dialog("close");
                    }
                });

                if (parseInt(jsonObj.left_id) == 0) {
                    $("#modal__edit_link__operator_id").prop('disabled', 'disabled');
                    $("#modal__edit_link__operator_id").val(0);
                } else {
                    $("#modal__edit_link__operator_id").prop('disabled', false);
                    $("#modal__edit_link__operator_id").val(jsonObj.operator_id);
                }
                $("#modal__edit_link__link_type_left").val(jsonObj.type_left);
                $("#modal__edit_link__link_type_right").val(jsonObj.type_right);
                $("#modal__edit_link__link_left_param").val(jsonObj.operand_left).trigger('change');
                var selectedParamDataType = $('option:selected', '#modal__edit_link__link_left_param').attr('rel');
                if (selectedParamDataType == 'Boolean') {
                    if (jsonObj.operand_right == 1) {
                        $("#modal__edit_link__link_boolean_truefalse").val(1);
                    } else {
                        $("#modal__edit_link__link_boolean_truefalse").val(0);
                    }
                    $("#modal__edit_link__link_boolean_truefalse").trigger('change');
                } else {
                    $("#modal__edit_link__link_operator_id").val(jsonObj.link_operator_id);
                    $("#modal__edit_link__link_right_operand").val(jsonObj.operand_right);
                }

                $("#modal__edit_link").dialog("open");
            }
        })
                .next().button({
            icon: "ui-icon-elrclose",
            showLabel: false
        }).on("click", function(e) {
            e.preventDefault();
            var jsonObj = JSON.parse($(this).val());
            var targetForm;

            if (jsonObj.type) {
                if (jsonObj.type == 'chain') {
                    targetForm = $("#form__delete_group");
                    $("#delete_group__rule_id").val(jsonObj.rule_id);
                    $("#delete_group__parent_chain_id").val(jsonObj.parent_chain_id);
                    $("#delete_group__left_id").val(jsonObj.left_id);
                    $("#delete_group__action").val('delete_group');
                } else {
                    targetForm = $("#form__delete_link");
                    $("#delete_link__rule_id").val(jsonObj.rule_id);
                    $("#delete_link__link_id").val(jsonObj.link_id);
                    $("#delete_link__left_id").val(jsonObj.left_id);
                    $("#delete_link__action").val('delete_link');
                }

                $("#confirm_delete_dialog").dialog('option', 'buttons', {
                    "Delete": function() {
                        targetForm.trigger("submit");
                    },
                    "Cancel": function() {
                        $(this).dialog("close");
                    }
                });

                $("#confirm_delete_dialog").dialog("open");
            }
        })
                .parent()
                .controlgroup();
    });
</script>
<style type="text/css">
    fieldset { padding: 10px; font-family: 'Open Sans', Arial, Helvetica, sans-serif !important; }
    legend { font-family: 'Oswald', serif; margin-left: 10px; color: firebrick; font-weight: 500; font-size: 1.5em; }
    fieldset label { font-weight: 600 !important; }
    #ruleconditions_cancel { float: right; }
    .rule_chain {
        font-family: 'Open Sans', Arial, Helvetica, sans-serif !important;
        display: inline-block;
        margin: 2px 5px 2px 5px;
        padding: 5px 10px;
        border-left: 2px green solid;
        border-right: 2px green solid;
        border-top: 0;
        border-bottom: 0;
        background-color: #e0ebe7; /* fallback */
        background-color: rgba(102, 205, 170, 0.1);
        box-shadow: 1px 1px 7px dimgray;
        cursor: default;
        vertical-align: top;
    }
    .rule_operator {
        font-family: Consolas, 'Courier New', Courier, serif !important;
        display: inline-block;
        /* border-left: 1px dimgray dotted;
        border-right: 1px dimgray dotted;
        border-top: 0;
        border-bottom: 0;
        background-color: whitesmoke;
        margin: 2px 5px 2px 5px; */
        font-weight: 700;
        padding: 0px 5px;
        color: darkred;
        text-transform: none;
        cursor: default;
        min-width: 25px;
        text-align: center;
        vertical-align: top;
    }
    .rule_link {
        font-family: 'Open Sans', Arial, Helvetica, sans-serif !important;
        display: inline-block;
        border-left: 1px navy dotted;
        border-right: 1px navy dotted;
        border-top: 0;
        border-bottom: 0;
        font-weight: 400;
        margin: 2px 5px 2px 5px;
        padding: 5px 10px;
        color: black;
        background-color: whitesmoke;
        cursor: default;
        vertical-align: middle;
    }
    .rule_link strong { font-weight: 600 !important;}
    .rule_actions {
        display: inline-block;
        padding-left: 10px;
        vertical-align: top;
    }
    .link_wrapper {
        margin: 10px;
    }
    #condition_container {
        margin: 15px 0px;
    }
    .ui-dialog-content label {
        font-family: 'Open Sans', Arial, Helvetica, sans-serif !important;
        font-weight: 600;
    }
    .ui-dialog-content select, .ui-dialog-content input {
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
</style>

<?php
$rule_sql = 'SELECT r.id AS rule_id, r.name AS rule_name, r.send_to_state, r.send_to_lhd, n.label AS notification_type, r.enabled AS enabled 
		FROM ' . $emsaDbSchemaPrefix . 'bn_rules r 
		LEFT JOIN ' . $emsaDbSchemaPrefix . 'batch_notification_types n ON (r.notification_type = n.id) 
		WHERE r.id = ' . intval(trim($_GET['edit_id'])) . ' 
		ORDER BY r.name';
$rule_rs = @pg_query($host_pa, $rule_sql);
if ($rule_rs !== false) {
    $this_rule = @pg_fetch_object($rule_rs);
} else {
    \Udoh\Emsa\Utils\DisplayUtils::drawError('Unable to manage conditions -- Rule not found.');
}
@pg_free_result($rule_rs);
?>

<h1 class="elrhdg"><span class="ui-icon ui-icon-header ui-icon-emsasettings"></span>Rule Condition Manager</h1>

<div class="lab_results_container ui-widget ui-corner-all">

    <fieldset class="ui-widget ui-widget-content ui-state-highlight ui-corner-all">
        <legend>&ldquo;<?php echo \Udoh\Emsa\Utils\DisplayUtils::xSafe($this_rule->rule_name); ?>&rdquo; Summary</legend>
        <div style="float: left;">
            <strong>Enabled?</strong> <?php echo ((trim($this_rule->enabled) == "t") ? "<span style=\"display: inline-block; vertical-align: bottom;\" class=\"ui-icon ui-icon-elron\" title=\"Rule Enabled\"></span>" : "<span style=\"display: inline-block; vertical-align: bottom;\" class=\"ui-icon ui-icon-elrstop\" title=\"Rule Disabled\"></span>"); ?><br>
            <strong>Triggers:</strong> <?php echo \Udoh\Emsa\Utils\DisplayUtils::xSafe($this_rule->notification_type); ?><br>
            <strong>Notify UDOH?:</strong> <?php echo ((trim($this_rule->send_to_state) == "t") ? "<span style=\"display: inline-block; vertical-align: bottom;\" class=\"ui-icon ui-icon-elrsuccess\" title=\"Yes\"></span>" : "<span style=\"display: inline-block; vertical-align: bottom;\" class=\"ui-icon ui-icon-elrcancel\" title=\"No\"></span>"); ?><br>
            <strong>Notify LHD/Virtual Jurisdiction?:</strong> <?php echo ((trim($this_rule->send_to_lhd) == "t") ? "<span style=\"display: inline-block; vertical-align: bottom;\" class=\"ui-icon ui-icon-elrsuccess\" title=\"Yes\"></span>" : "<span style=\"display: inline-block; vertical-align: bottom;\" class=\"ui-icon ui-icon-elrcancel\" title=\"No\"></span>"); ?><br>
        </div>
        <button id="ruleconditions_cancel" title="Go back to the list of rules & make no changes">Back to Rule List</button>
    </fieldset>

    <fieldset id="condition_container" class="ui-widget ui-widget-content ui-corner-all">
        <legend>Edit Rule Conditions for &ldquo;<?php echo \Udoh\Emsa\Utils\DisplayUtils::xSafe($this_rule->rule_name); ?>&rdquo;</legend>


<?php
displayRule(intval(trim($_GET['edit_id'])), 0);
?>

    </fieldset>

</div>

<div id="confirm_delete_dialog" title="Delete this item?">
    <p><span class="ui-icon ui-icon-elrerror" style="float:left; margin:0 7px 50px 0;"></span>This item will be permanently deleted and cannot be recovered. Are you sure?</p>
</div>

<div id="modal__add_group" title="Add New Rule Condition Group">
    <div class="h3">Logical Operator</div>
    <label for="modal__add_group__operator_id">How should this Rule Condition Group be combined with the previous Condition or Group?</label><br>
    <select class="ui-corner-all" name="modal__add_group__operator_id" id="modal__add_group__operator_id" title="Leave blank if no previous Rule Conditions or Groups">
        <option value="0">--</option>
<?php
$modal_operator_sql = 'SELECT id, label FROM ' . $emsaDbSchemaPrefix . 'structure_operator WHERE operator_type = 2 ORDER BY label;';
$modal_operator_rs = @pg_query($host_pa, $modal_operator_sql);
if ($modal_operator_rs !== false) {
    while ($modal_operator_row = @pg_fetch_object($modal_operator_rs)) {
        echo '<option value="' . intval($modal_operator_row->id) . '">' . \Udoh\Emsa\Utils\DisplayUtils::xSafe($modal_operator_row->label) . '</option>' . PHP_EOL;
    }
} else {
    \Udoh\Emsa\Utils\DisplayUtils::drawError('Unable to retrieve list of operators');
}
@pg_free_result($modal_operator_rs);
?>
    </select>
</div>

<div id="modal__add_link" title="Add New Rule Condition">
    <div class="h3">Logical Operator</div>
    <label for="modal__add_link__operator_id">How should this Rule Condition be combined with the previous Condition or Group?</label><br>
    <select class="ui-corner-all" name="modal__add_link__operator_id" id="modal__add_link__operator_id" title="Leave blank if no previous Rule Conditions or Groups">
        <option value="0">--</option>
<?php
$modal_operator_sql = 'SELECT id, label FROM ' . $emsaDbSchemaPrefix . 'structure_operator WHERE operator_type = 2 ORDER BY label;';
$modal_operator_rs = @pg_query($host_pa, $modal_operator_sql);
if ($modal_operator_rs !== false) {
    while ($modal_operator_row = @pg_fetch_object($modal_operator_rs)) {
        echo '<option value="' . intval($modal_operator_row->id) . '">' . \Udoh\Emsa\Utils\DisplayUtils::xSafe($modal_operator_row->label) . '</option>' . PHP_EOL;
    }
} else {
    \Udoh\Emsa\Utils\DisplayUtils::drawError('Unable to retrieve list of operators');
}
@pg_free_result($modal_operator_rs);
?>
    </select>
    <br><br>

    <div class="h3">Condition:</div>

    <label for="modal__add_link__link_left_param">Parameter:</label><br>
    <select class="ui-corner-all" name="modal__add_link__link_left_param" id="modal__add_link__link_left_param">
        <option value="0" rel="NULL">--</option>
<?php
$modal_parameter_sql = 'SELECT id, varname, label FROM ' . $emsaDbSchemaPrefix . 'bn_rule_parameters ORDER BY label;';
$modal_parameter_rs = @pg_query($host_pa, $modal_parameter_sql);
if ($modal_parameter_rs !== false) {
    while ($modal_parameter_row = @pg_fetch_object($modal_parameter_rs)) {
        echo '<option value="' . \Udoh\Emsa\Utils\DisplayUtils::xSafe(trim($modal_parameter_row->varname)) . '" rel="' . \Udoh\Emsa\Utils\DisplayUtils::xSafe($nc->getDataType($modal_parameter_row->varname)) . '">' . \Udoh\Emsa\Utils\DisplayUtils::xSafe($modal_parameter_row->label) . '</option>' . PHP_EOL;
    }
} else {
    \Udoh\Emsa\Utils\DisplayUtils::drawError('Unable to retrieve list of parameters');
}
@pg_free_result($modal_parameter_rs);
?>
    </select><br>

    <div id="modal__add_link__boolean_param" style="display: none;">
        <label for="modal__add_link__link_boolean_truefalse">Parameter Value:</label><br>
        <select class="ui-corner-all" name="modal__add_link__link_boolean_truefalse" id="modal__add_link__link_boolean_truefalse">
            <option value="-1">--</option>
            <option value="1">Is True</option>
            <option value="0">Is False</option>
        </select>
    </div>

    <div id="modal__add_link__nonboolean_param" style="display: none;">
        <label for="modal__add_link__link_operator_id">Comparison:</label><br>
        <select class="ui-corner-all" name="modal__add_link__link_operator_id" id="modal__add_link__link_operator_id">
            <option value="0">--</option>
        <?php
        $modal_operator_sql = 'SELECT id, graphical, label FROM ' . $emsaDbSchemaPrefix . 'structure_operator WHERE operator_type = 1 ORDER BY label;';
        $modal_operator_rs = @pg_query($host_pa, $modal_operator_sql);
        if ($modal_operator_rs !== false) {
            while ($modal_operator_row = @pg_fetch_object($modal_operator_rs)) {
                echo '<option value="' . intval($modal_operator_row->id) . '">' . \Udoh\Emsa\Utils\DisplayUtils::xSafe($modal_operator_row->label) . '</option>' . PHP_EOL;
            }
        } else {
            \Udoh\Emsa\Utils\DisplayUtils::drawError('Unable to retrieve list of operators');
        }
        @pg_free_result($modal_operator_rs);
        ?>
        </select><br>

        <input type="hidden" name="modal__add_link__link_type_left" id="modal__add_link__link_type_left" value="<?php echo \Udoh\Emsa\Email\Notification::OPTYPE_PARAMETER; ?>">
        <input type="hidden" name="modal__add_link__link_type_right" id="modal__add_link__link_type_right" value="<?php echo \Udoh\Emsa\Email\Notification::OPTYPE_VALUE; ?>">

        <div id="modal__add_link__typeof_param" style="display: none;">
            <label for="modal__add_link__link_right_param">Parameter:</label><br>
            <select class="ui-corner-all" name="modal__add_link__link_right_param" id="modal__add_link__link_right_param">
                <option value="0" rel="NULL">--</option>
<?php
$modal_parameter_sql = 'SELECT id, varname, label FROM ' . $emsaDbSchemaPrefix . 'bn_rule_parameters ORDER BY label;';
$modal_parameter_rs = @pg_query($host_pa, $modal_parameter_sql);
if ($modal_parameter_rs !== false) {
    while ($modal_parameter_row = @pg_fetch_object($modal_parameter_rs)) {
        echo '<option value="' . intval($modal_parameter_row->id) . '" rel="' . $nc->getDataType($modal_parameter_row->varname) . '">' . \Udoh\Emsa\Utils\DisplayUtils::xSafe($modal_parameter_row->label) . '</option>' . PHP_EOL;
    }
} else {
    \Udoh\Emsa\Utils\DisplayUtils::drawError('Unable to retrieve list of parameters');
}
@pg_free_result($modal_parameter_rs);
?>
            </select><br>
        </div>

        <div id="modal__add_link__typeof_value">
            <label for="modal__add_link__link_right_operand">Value:</label><br>
            <input type="text" class="ui-corner-all" id="modal__add_link__link_right_operand" name="modal__add_link__link_right_operand"><br>
        </div>
    </div>

</div>

<div id="modal__edit_link" title="Edit Rule Condition">
    <div class="h3">Logical Operator</div>
    <label for="modal__edit_link__operator_id">How should this Rule Condition be combined with the previous Condition or Group?</label><br>
    <select class="ui-corner-all" name="modal__edit_link__operator_id" id="modal__edit_link__operator_id" title="Leave blank if no previous Rule Conditions or Groups">
        <option value="0">--</option>
                <?php
                $modal_operator_sql = 'SELECT id, label FROM ' . $emsaDbSchemaPrefix . 'structure_operator WHERE operator_type = 2 ORDER BY label;';
                $modal_operator_rs = @pg_query($host_pa, $modal_operator_sql);
                if ($modal_operator_rs !== false) {
                    while ($modal_operator_row = @pg_fetch_object($modal_operator_rs)) {
                        echo '<option value="' . intval($modal_operator_row->id) . '">' . \Udoh\Emsa\Utils\DisplayUtils::xSafe($modal_operator_row->label) . '</option>' . PHP_EOL;
                    }
                } else {
                    \Udoh\Emsa\Utils\DisplayUtils::drawError('Unable to retrieve list of operators');
                }
                @pg_free_result($modal_operator_rs);
                ?>
    </select>
    <br><br>

    <div class="h3">Condition:</div>

    <label for="modal__edit_link__link_left_param">Parameter:</label><br>
    <select class="ui-corner-all" name="modal__edit_link__link_left_param" id="modal__edit_link__link_left_param">
        <option value="0" rel="NULL">--</option>
<?php
$modal_parameter_sql = 'SELECT id, varname, label FROM ' . $emsaDbSchemaPrefix . 'bn_rule_parameters ORDER BY label;';
$modal_parameter_rs = @pg_query($host_pa, $modal_parameter_sql);
if ($modal_parameter_rs !== false) {
    while ($modal_parameter_row = @pg_fetch_object($modal_parameter_rs)) {
        echo '<option value="' . \Udoh\Emsa\Utils\DisplayUtils::xSafe(trim($modal_parameter_row->varname)) . '" rel="' . \Udoh\Emsa\Utils\DisplayUtils::xSafe($nc->getDataType($modal_parameter_row->varname)) . '">' . \Udoh\Emsa\Utils\DisplayUtils::xSafe($modal_parameter_row->label) . '</option>' . PHP_EOL;
    }
} else {
    \Udoh\Emsa\Utils\DisplayUtils::drawError('Unable to retrieve list of parameters');
}
@pg_free_result($modal_parameter_rs);
?>
    </select><br>

    <div id="modal__edit_link__boolean_param" style="display: none;">
        <label for="modal__edit_link__link_boolean_truefalse">Parameter Value:</label><br>
        <select class="ui-corner-all" name="modal__edit_link__link_boolean_truefalse" id="modal__edit_link__link_boolean_truefalse">
            <option value="-1">--</option>
            <option value="1">Is True</option>
            <option value="0">Is False</option>
        </select>
    </div>

    <div id="modal__edit_link__nonboolean_param" style="display: none;">
        <label for="modal__edit_link__link_operator_id">Comparison:</label><br>
        <select class="ui-corner-all" name="modal__edit_link__link_operator_id" id="modal__edit_link__link_operator_id">
            <option value="0">--</option>
        <?php
        $modal_operator_sql = 'SELECT id, graphical, label FROM ' . $emsaDbSchemaPrefix . 'structure_operator WHERE operator_type = 1 ORDER BY label;';
        $modal_operator_rs = @pg_query($host_pa, $modal_operator_sql);
        if ($modal_operator_rs !== false) {
            while ($modal_operator_row = @pg_fetch_object($modal_operator_rs)) {
                echo '<option value="' . intval($modal_operator_row->id) . '">' . \Udoh\Emsa\Utils\DisplayUtils::xSafe($modal_operator_row->label) . '</option>' . PHP_EOL;
            }
        } else {
            \Udoh\Emsa\Utils\DisplayUtils::drawError('Unable to retrieve list of operators');
        }
        @pg_free_result($modal_operator_rs);
        ?>
        </select><br>

        <input type="hidden" name="modal__edit_link__link_type_left" id="modal__edit_link__link_type_left" value="<?php echo \Udoh\Emsa\Email\Notification::OPTYPE_PARAMETER; ?>">
        <input type="hidden" name="modal__edit_link__link_type_right" id="modal__edit_link__link_type_right" value="<?php echo \Udoh\Emsa\Email\Notification::OPTYPE_VALUE; ?>">

        <div id="modal__edit_link__typeof_param" style="display: none;">
            <label for="modal__edit_link__link_right_param">Parameter:</label><br>
            <select class="ui-corner-all" name="modal__edit_link__link_right_param" id="modal__edit_link__link_right_param">
                <option value="0" rel="NULL">--</option>
<?php
$modal_parameter_sql = 'SELECT id, varname, label FROM ' . $emsaDbSchemaPrefix . 'bn_rule_parameters ORDER BY label;';
$modal_parameter_rs = @pg_query($host_pa, $modal_parameter_sql);
if ($modal_parameter_rs !== false) {
    while ($modal_parameter_row = @pg_fetch_object($modal_parameter_rs)) {
        echo '<option value="' . intval($modal_parameter_row->id) . '" rel="' . $nc->getDataType($modal_parameter_row->varname) . '">' . \Udoh\Emsa\Utils\DisplayUtils::xSafe($modal_parameter_row->label) . '</option>' . PHP_EOL;
    }
} else {
    \Udoh\Emsa\Utils\DisplayUtils::drawError('Unable to retrieve list of parameters');
}
@pg_free_result($modal_parameter_rs);
?>
            </select><br>
        </div>

        <div id="modal__edit_link__typeof_value">
            <label for="modal__edit_link__link_right_operand">Value:</label><br>
            <input type="text" class="ui-corner-all" id="modal__edit_link__link_right_operand" name="modal__edit_link__link_right_operand"><br>
        </div>
    </div>

</div>

<form method="POST" id="form__delete_link">
    <input type="hidden" id="delete_link__rule_id" name="cmod__rule_id">
    <input type="hidden" id="delete_link__link_id" name="cmod__link_id">
    <input type="hidden" id="delete_link__left_id" name="cmod__left_id">
    <input type="hidden" id="delete_link__action" name="cmod__action">
</form>

<form method="POST" id="form__delete_group">
    <input type="hidden" id="delete_group__rule_id" name="cmod__rule_id">
    <input type="hidden" id="delete_group__parent_chain_id" name="cmod__parent_chain_id">
    <input type="hidden" id="delete_group__left_id" name="cmod__left_id">
    <input type="hidden" id="delete_group__action" name="cmod__action">
</form>

<form method="POST" id="form__edit_group">
    <input type="hidden" id="edit_group__rule_id" name="cmod__rule_id">
    <input type="hidden" id="edit_group__parent_chain_id" name="cmod__parent_chain_id">
    <input type="hidden" id="edit_group__left_id" name="cmod__left_id">
    <input type="hidden" id="edit_group__operator_id" name="cmod__operator_id">
    <input type="hidden" id="edit_group__link_type" name="cmod__link_type">
    <input type="hidden" id="edit_group__link_id" name="cmod__link_id">
    <input type="hidden" id="edit_group__action" name="cmod__action">
</form>

<form method="POST" id="form__edit_link">
    <input type="hidden" id="edit_link__rule_id" name="cmod__rule_id">
    <input type="hidden" id="edit_link__parent_chain_id" name="cmod__parent_chain_id">
    <input type="hidden" id="edit_link__left_id" name="cmod__left_id">
    <input type="hidden" id="edit_link__operator_id" name="cmod__operator_id">
    <input type="hidden" id="edit_link__link_id" name="cmod__link_id">
    <input type="hidden" id="edit_link__type_left" name="cmod__type_left">
    <input type="hidden" id="edit_link__type_right" name="cmod__type_right">
    <input type="hidden" id="edit_link__link_operator_id" name="cmod__link_operator_id">
    <input type="hidden" id="edit_link__operand_left" name="cmod__operand_left">
    <input type="hidden" id="edit_link__operand_right" name="cmod__operand_right">
    <input type="hidden" id="edit_link__action" name="cmod__action">
</form>
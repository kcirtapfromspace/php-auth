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
 * @copyright Copyright (c) 2019 Utah Department of Technology Services and Utah Department of Health
 */
function semiAutomatedEntry(thisEvent, cmrMode) {
    let semiAutoOriginal = {};
    try {
        semiAutoOriginal = JSON.parse($("#semi_auto_original_"+thisEvent).val());
    } catch (err) {
        semiAutoOriginal = {};
    }

    if (!jQuery.isEmptyObject(semiAutoOriginal)) {
        let changedFields = 0;
        let semiAutoEdits = {};
            semiAutoEdits.message			= thisEvent;
            semiAutoEdits.disease			= $("#cultureentry_"+thisEvent+"__disease").val();
            semiAutoEdits.organism			= $("#cultureentry_"+thisEvent+"__organism").val();
            semiAutoEdits.jurisdiction		= $("#cultureentry_"+thisEvent+"__jurisdiction").val();
            semiAutoEdits.state_case_status	= $("#cultureentry_"+thisEvent+"__state_case_status").val();
            semiAutoEdits.test_type			= $("#cultureentry_"+thisEvent+"__test_type").val();
            semiAutoEdits.specimen			= $("#cultureentry_"+thisEvent+"__specimen").val();
            semiAutoEdits.testresult		= $("#cultureentry_"+thisEvent+"__testresult").val();
            semiAutoEdits.resultvalue		= $("#cultureentry_"+thisEvent+"__resultvalue").val();
            semiAutoEdits.units				= $("#cultureentry_"+thisEvent+"__units").val();
            semiAutoEdits.comment			= $("#cultureentry_"+thisEvent+"__comment").val();
            semiAutoEdits.teststatus		= $("#cultureentry_"+thisEvent+"__teststatus").val();


        $("#cmroverride_surveillance_"+thisEvent).val($("#cultureentry_"+thisEvent+"__surveillance").val());

        for (let prop in semiAutoEdits) {
            if (semiAutoEdits.hasOwnProperty(prop)) {
                if (semiAutoEdits[prop] !== semiAutoOriginal[prop]) {
                    changedFields++;
                }
            }
        }

        if (!(semiAutoEdits.disease.length > 0)) {
            alert('No Condition specified!\n\nPlease select a Condition on the "Full Lab" tab & try again.');
            return false;
        }

        if (changedFields > 0) {
            // make ajax request to update master/nedss xml
            $.post("emsa/lib/ajax_semiauto_entry.php", semiAutoEdits)
            .done(function() {
                // changes saved, continue assigning message
                if (cmrMode === 'add_new_cmr') {
                    assignMessageAddNewCMR(thisEvent);
                } else {
                    assignMessageUpdateCMR(thisEvent);
                }
            }).fail(function() {
                alert('An error occurred while attempting to save the semi-automated lab entry changes.\n\nMessage cannot be processed at this time.');
                return false;
            });
        } else {
            // no changes made, continue assigning message
            if (cmrMode === 'add_new_cmr') {
                assignMessageAddNewCMR(thisEvent);
            } else {
                assignMessageUpdateCMR(thisEvent);
            }
        }
    } else {
        // this message not using semi-automated entry, continue assigning message
        if (cmrMode === 'add_new_cmr') {
            assignMessageAddNewCMR(thisEvent);
        } else if (cmrMode === 'add_bulk_new_cmr') {
            assignMessageBulkAddNewCMR(thisEvent);
        } else if (cmrMode === 'update_bulk_cmr') {
            assignMessageBulkUpdateCMR(thisEvent);
        } else {
            assignMessageUpdateCMR(thisEvent);
        }
    }
}

function assignMessageAddNewCMR(thisEvent) {
    let thisPersonsArray = [];

    $.each($("input[name='use_person["+thisEvent+"][]']:checked"), function() {
        thisPersonsArray.push($(this).val());
    });

    if (thisPersonsArray.length > 0) {
        let confirmAddnewDialog = $("#confirm_addnew_dialog");

        confirmAddnewDialog.dialog('option', 'buttons', {
                "Yes, add results to a new person" : function() {
                    // multi-click protection...
                    $(".emsa_btn_edit").button("disable");
                    $(".emsa_btn_retry").button("disable");
                    $(".emsa_btn_move").button("disable");
                    $(".emsa_btn_delete").button("disable");
                    $(".emsa_btn_viewpdf").button("disable");
                    $(".override_new_person").button("disable");
                    $(".override_update_cmr").button("disable");
                    $(".override_new_cmr").button("disable");
                    $(".add_new_cmr").button("disable");
                    $(".update_cmr").button("disable");
                    $(".add_bulk_new_cmr").button("disable");
                    $(".update_bulk_cmr").button("disable");
                    $("*").css("cursor", "wait");

                    $("#emsa_cmraction_"+thisEvent).val("addnew");
                    $("#cmr_"+thisEvent).trigger("submit");
                },
                "No, add results to the selected persons" : function() {
                    // multi-click protection...
                    $(".emsa_btn_edit").button("disable");
                    $(".emsa_btn_retry").button("disable");
                    $(".emsa_btn_move").button("disable");
                    $(".emsa_btn_delete").button("disable");
                    $(".emsa_btn_viewpdf").button("disable");
                    $(".override_new_person").button("disable");
                    $(".override_update_cmr").button("disable");
                    $(".override_new_cmr").button("disable");
                    $(".add_new_cmr").button("disable");
                    $(".update_cmr").button("disable");
                    $(".add_bulk_new_cmr").button("disable");
                    $(".update_bulk_cmr").button("disable");
                    $("*").css("cursor", "wait");

                    $("#emsa_cmraction_"+thisEvent).val("update");
                    $("#match_persons_"+thisEvent).val(thisPersonsArray.join("|"));
                    $("#cmr_"+thisEvent).trigger("submit");
                },
                "Do nothing, I'll pick new options" : function() {
                    $(this).dialog("close");
                }
        });

        confirmAddnewDialog.dialog("open");
    } else {
        $("#emsa_cmraction_"+thisEvent).val("addnew");
        $("#cmr_"+thisEvent).trigger("submit");
    }
}

function assignMessageUpdateCMR(thisEvent) {
    let thisPersonsArray = [];

    $.each($("input[name='use_person["+thisEvent+"][]']:checked"), function() {
        thisPersonsArray.push($(this).val());
    });

    if (thisPersonsArray.length < 1) {
        alert("No person(s) selected to update!\n\nPlease choose at least one person to add this event to.");
        return false;
    } else {
        // multi-click protection...
        $(".emsa_btn_edit").button("disable");
        $(".emsa_btn_retry").button("disable");
        $(".emsa_btn_move").button("disable");
        $(".emsa_btn_delete").button("disable");
        $(".emsa_btn_viewpdf").button("disable");
        $(".override_new_person").button("disable");
        $(".override_update_cmr").button("disable");
        $(".override_new_cmr").button("disable");
        $(".add_new_cmr").button("disable");
        $(".update_cmr").button("disable");
        $(".add_bulk_new_cmr").button("disable");
        $(".update_bulk_cmr").button("disable");
        $("*").css("cursor", "wait");

        $("#emsa_cmraction_"+thisEvent).val("update");
        $("#match_persons_"+thisEvent).val(thisPersonsArray.join("|"));
        $("#cmr_"+thisEvent).trigger("submit");
    }
}

function assignMessageBulkAddNewCMR(thisEvent) {
    let thisPersonsArray = [];

    $.each($("input[name='use_person["+thisEvent+"][]']:checked"), function() {
        thisPersonsArray.push($(this).val());
    });

    if (thisPersonsArray.length > 0) {
        let confirmAddnewDialog = $("#confirm_addnew_dialog");

        confirmAddnewDialog.dialog('option', 'buttons', {
                "Yes, add results to a new person" : function() {
                    // multi-click protection...
                    $(".emsa_btn_edit").button("disable");
                    $(".emsa_btn_retry").button("disable");
                    $(".emsa_btn_move").button("disable");
                    $(".emsa_btn_delete").button("disable");
                    $(".emsa_btn_viewpdf").button("disable");
                    $(".override_new_person").button("disable");
                    $(".override_update_cmr").button("disable");
                    $(".override_new_cmr").button("disable");
                    $(".add_new_cmr").button("disable");
                    $(".update_cmr").button("disable");
                    $(".add_bulk_new_cmr").button("disable");
                    $(".update_bulk_cmr").button("disable");
                    $("*").css("cursor", "wait");

                    $("#emsa_cmraction_"+thisEvent).val("bulk_addnew");
                    $("#cmr_"+thisEvent).trigger("submit");
                },
                "No, add results to the selected persons" : function() {
                    // multi-click protection...
                    $(".emsa_btn_edit").button("disable");
                    $(".emsa_btn_retry").button("disable");
                    $(".emsa_btn_move").button("disable");
                    $(".emsa_btn_delete").button("disable");
                    $(".emsa_btn_viewpdf").button("disable");
                    $(".override_new_person").button("disable");
                    $(".override_update_cmr").button("disable");
                    $(".override_new_cmr").button("disable");
                    $(".add_new_cmr").button("disable");
                    $(".update_cmr").button("disable");
                    $(".add_bulk_new_cmr").button("disable");
                    $(".update_bulk_cmr").button("disable");
                    $("*").css("cursor", "wait");

                    $("#emsa_cmraction_"+thisEvent).val("bulk_update");
                    $("#match_persons_"+thisEvent).val(thisPersonsArray.join("|"));
                    $("#cmr_"+thisEvent).trigger("submit");
                },
                "Do nothing, I'll pick new options" : function() {
                    $(this).dialog("close");
                }
        });

        confirmAddnewDialog.dialog("open");
    } else {
        $("#emsa_cmraction_"+thisEvent).val("bulk_addnew");
        $("#cmr_"+thisEvent).trigger("submit");
    }
}

function assignMessageBulkUpdateCMR(thisEvent) {
    let thisPersonsArray = [];

    $.each($("input[name='use_person["+thisEvent+"][]']:checked"), function() {
        thisPersonsArray.push($(this).val());
    });

    if (thisPersonsArray.length < 1) {
        alert("No person(s) selected to update!\n\nPlease choose at least one person to add this event to.");
        return false;
    } else {
        // multi-click protection...
        $(".emsa_btn_edit").button("disable");
        $(".emsa_btn_retry").button("disable");
        $(".emsa_btn_move").button("disable");
        $(".emsa_btn_delete").button("disable");
        $(".emsa_btn_viewpdf").button("disable");
        $(".override_new_person").button("disable");
        $(".override_update_cmr").button("disable");
        $(".override_new_cmr").button("disable");
        $(".add_new_cmr").button("disable");
        $(".update_cmr").button("disable");
        $(".add_bulk_new_cmr").button("disable");
        $(".update_bulk_cmr").button("disable");
        $("*").css("cursor", "wait");

        $("#emsa_cmraction_"+thisEvent).val("bulk_update");
        $("#match_persons_"+thisEvent).val(thisPersonsArray.join("|"));
        $("#cmr_"+thisEvent).trigger("submit");
    }
}

$(function() {
    $(".vocab_filter_selectall").on("click", function() {
        let thisFilter = $(this).attr("rel");

        $("div.vocab_filter_checklist[rel='"+thisFilter+"']").find($(":input")).each(function() {
            if (!$(this).is(':checked')) {
                $(this).trigger('click');
            }
        });
    });

    $(".vocab_filter_selectnone").on("click", function() {
        let thisFilter = $(this).attr("rel");

        $("div.vocab_filter_checklist[rel='"+thisFilter+"']").find($(":input")).each(function() {
            if ($(this).is(':checked')) {
                $(this).trigger('click');
            }
        });
    });

    $("#confirm_addnew_dialog").dialog({
        autoOpen: false,
        modal: true,
        draggable: false,
        resizable: false
    });

    $(".paging_link").button();
    $(".paging_link_current").button({
        disabled: true
    });
    $(".paging_link_first").button({
        icon: 
            "ui-icon-arrowthickstop-1-w"
        ,
        showLabel: false
    });
    $(".paging_link_previous").button({
        icon: 
            "ui-icon-arrowthick-1-w"
        ,
        showLabel: false
    });
    $(".paging_link_next").button({
        icon: 
            "ui-icon-arrowthick-1-e"
        ,
        showLabel: false
    });
    $(".paging_link_last").button({
        icon: 
            "ui-icon-arrowthickstop-1-e"
        ,
        showLabel: false
    });

    if ((parseInt(emsaMessageQueue.selectedMessageId) > 0) && $("#"+emsaMessageQueue.selectedMessageId).length) {
        setTimeout(function() {
            let selectedMessage = $("#"+emsaMessageQueue.selectedMessageId);
            selectedMessage.trigger('click');
            //$("#emsa_dupsearch_"+emsaMessageQueue.selectedMessageId+"_tabset").tabs("option", "selected", 2);
            //let container = $("body,html");
            //container.scrollTop(
//                selectedMessage.offset().top - container.offset().top + container.scrollTop()
  //          );
        }, 10);
    }

    let toggleFilters = $("#toggle_filters");
    toggleFilters.button({
        icon: "ui-icon-triangle-1-n",
        iconPosition: "end"
    }).on("click", function() {
        $(".vocab_filter").toggle();
        let objIcon = $(this).button("option", "icon");
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
    toggleFilters.button("option", "icon", "ui-icon-triangle-1-s");
    toggleFilters.button("option", "iconPosition", "end");
    toggleFilters.button("option", "label", "Show Filters");

    let toggleBulk = $("#toggle_bulk");
    toggleBulk.button({
        icon: "ui-icon-triangle-1-n",
        iconPosition: "end"
    }).on("click", function() {
        $("#bulk_form").toggle();
        let objIcon = $(this).button("option", "icon");
        if (objIcon === "ui-icon-triangle-1-s") {
            $(this).button("option", "icon", "ui-icon-triangle-1-n");
            $(this).button("option", "iconPosition", "end");
            $(this).button("option", "label", "Hide Bulk Message Actions");
            $("#addnew_form").hide();
            $("#addnew_button").show();
        } else {
            $(this).button("option", "icon", "ui-icon-triangle-1-s");
            $(this).button("option", "iconPosition", "end");
            $(this).button("option", "label", "Show Bulk Message Actions");
        }
    });

    $("#bulk_form").hide();
    toggleBulk.button("option", "icon", "ui-icon-triangle-1-s");
    toggleBulk.button("option", "iconPosition", "end");
    toggleBulk.button("option", "label", "Show Bulk Message Actions");

    let mplogToggle = $("#mplog-toggle");
    mplogToggle.button({
        icon: "ui-icon-triangle-1-n",
        iconPosition: "end"
    }).on("click", function() {
        $(".mplog-hideable").toggle();
        let objIcon = $(this).button("option", "icon");
        if (objIcon === "ui-icon-triangle-1-s") {
            $(this).button("option", "icon", "ui-icon-triangle-1-n");
            $(this).button("option", "iconPosition", "end");
            $(this).button("option", "label", "Hide More Details");
        } else {
            $(this).button("option", "icon", "ui-icon-triangle-1-s");
            $(this).button("option", "iconPosition", "end");
            $(this).button("option", "label", "Show More Details");
        }
    });

    $(".mplog-hideable").hide();
    mplogToggle.button("option", "icon", "ui-icon-triangle-1-s");
    mplogToggle.button("option", "iconPosition", "end");
    mplogToggle.button("option", "label", "Show More Details");

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
        $("#f_evalue").val("").trigger("blur");
        $("#sort").val("").trigger("blur");
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

    $(".emsa_close").button({
        icon: "ui-icon-arrowreturnthick-1-n",
        iconPosition: "end"
    }).on("click", function(e) {
        e.preventDefault();
    });

    $("#bulk_retry").button({
        icon: "ui-icon-elrretry"
    }).one("click", function() {
        // multi-click protection...
        $("#bulk_retry").button("disable");
        $("#bulk_move").button("disable");
        $("#bulk_delete").button("disable");
        $("#bulk_flag").button("disable");
        $("*").css("cursor", "wait");

        $("#bulk_action").val("bulk_retry");
        $("#bulk_target").val("");
        $("#bulk_form").trigger("submit");
    });

    $("#bulk_move").button({
        icon: "ui-icon-elrmove"
    }).on("click", function() {
        let bulkTargetId = $("#bulk_move_target").val();

        if (bulkTargetId < 1) {
            alert('Please select a destination queue to move selected message(s) to.');
            return false;
        }

        // multi-click protection...
        $("#bulk_retry").button("disable");
        $("#bulk_move").button("disable");
        $("#bulk_delete").button("disable");
        $("#bulk_flag").button("disable");
        $("*").css("cursor", "wait");

        $("#bulk_action").val("bulk_move");
        $("#bulk_target").val(bulkTargetId);
        $("#bulk_form").trigger("submit");
    });

    $("#bulk_delete").button({
        icon: "ui-icon-elrdelete"
    }).on("click", function() {
        if (confirm('Are you really sure you want to delete these messages?')) {
            // multi-click protection...
            $("#bulk_retry").button("disable");
            $("#bulk_move").button("disable");
            $("#bulk_delete").button("disable");
            $("#bulk_flag").button("disable");
            $("*").css("cursor", "wait");

            $("#bulk_action").val("bulk_delete");
            $("#bulk_target").val("");
            $("#bulk_form").trigger("submit");
        } else {
            return false;
        }
    });

    $("#bulk_flag").button({
        icon: "ui-icon-flag"
    }).on("click", function() {
        let bulkFlagId = $("#bulk_qa_flag_id").val();
        let bulkDeErrorReason = $("#bulk_flag_de_error_type").val().trim();
        let bulkDeOtherReason = $("#bulk_flag_other_reason").val().trim();

        if (bulkFlagId < 1) {
            alert('Please select a QA Flag to set.');
            return false;
        }
        if (bulkFlagId === emsaMessageQueue.EMSA_FLAG_DE_ERROR) {
            if (bulkDeErrorReason.length === 0) {
                alert('Cannot set QA flag for the selected messages:  Please select a type of Data Entry Error and try again.');
                return false;
            }
        }
        if (bulkFlagId === emsaMessageQueue.EMSA_FLAG_DE_OTHER) {
            if (bulkDeOtherReason.length === 0) {
                alert('Cannot set QA flag for the selected messages:  Please specify an \'Other\' reason and try again.');
                return false;
            }
        }

        // multi-click protection...
        $("#bulk_retry").button("disable");
        $("#bulk_move").button("disable");
        $("#bulk_delete").button("disable");
        $("#bulk_flag").button("disable");
        $("*").css("cursor", "wait");

        $("#bulk_action").val("bulk_qa_flag");
        $("#bulk_target").val("");
        $("#bulk_form").trigger("submit");
    });

    $("#bulk_selectall").button({
        icon: "ui-icon-elrsuccess"
    }).on("click", function() {
        let e = jQuery.Event("click");
        e.ctrlKey = true;
        $(".emsa_dup").each(function() {
            if (!$(this).hasClass("ctrlclicked")) {
                $(this).trigger(e);
            }
        });
    });

    $("#bulk_selectnone").button({
        icon: "ui-icon-radio-on"
    }).on("click", function() {
        let e = jQuery.Event("click");
        e.ctrlKey = true;
        $(".emsa_dup").each(function() {
            if ($(this).hasClass("ctrlclicked")) {
                $(this).trigger(e);
            }
        });
    });

    $("#view_detailed_results").button({
        icon:  "ui-icon-circle-triangle-s",
        iconPosition: "end"
    }).on("click", function() {
        $("#detailed_results").toggle();
    });

    $(".emsa_dup").on("click", function(e) {
        if (e.ctrlKey) {
            // Bulk Message selection functionality
            let container = $("#bulk_form_container");
            let thisId = $("#"+this.id);

            thisId.toggleClass("ctrlclicked");
            $("#"+this.id+" > td").toggleClass("ctrlclicked");

            if (thisId.hasClass("ctrlclicked")) {
                $("<input>", {type: "hidden", name: "bulk_ids[]", id: "bulk_ids_"+this.id, value: this.id}).appendTo(container);
            } else {
                $("#bulk_ids_"+this.id).remove();
            }

            if ($("input[name='bulk_ids[]']").length > 0) {
                $("#bulk_retry").button("enable");
                $("#bulk_delete").button("enable");
                $("#bulk_move").button("enable");
                $("#bulk_flag").button("enable");
            } else {
                $("#bulk_retry").button("disable");
                $("#bulk_delete").button("disable");
                $("#bulk_move").button("disable");
                $("#bulk_flag").button("disable");
            }
        } else {
            // Message assignment functionality
            let systemMessageId = this.id;
            let msgBodyRowId = "dupsearch_"+systemMessageId;
            let emsaDupsearchTabset = $("#emsa_dupsearch_"+systemMessageId+"_tabset");
            let msgBodyRow = $("#"+msgBodyRowId);
            let msgHeaderRow = $("#"+this.id);

            let ajaxParams = {
                id: systemMessageId,
                type: emsaMessageQueue.type,
                selected_page: emsaMessageQueue.navSelectedPage,
                submenu: emsaMessageQueue.navSubmenu,
                cat: emsaMessageQueue.navCat,
                subcat: emsaMessageQueue.navSubcat
            };

            $(".emsa_dupsearch:not(#"+msgBodyRowId+")").hide(); // hide all msgBodyRows that aren't this one
            $(".emsa_dupsearch_tabset:not(#emsa_dupsearch_"+systemMessageId+"_tabset)").each(function () {
                // destroy tabset from any other message rows
                if ($(this).tabs("instance")) {
                    $(this).tabs("destroy");
                }
                $(this).empty();
            });
            $(".emsa_dup:not(#"+systemMessageId+") > td").removeClass("emsa_results_search_parent");

            if (msgBodyRow.is(':hidden')) {
                $(".emsa_dup:not(#" + systemMessageId + ")").addClass("emsa_dup_dimmed");
                $(".emsa_close:not(#emsa_close_" + systemMessageId + ")").hide();
                msgHeaderRow.removeClass("emsa_dup_dimmed");
                $("#" + systemMessageId + " > td").addClass("emsa_results_search_parent");
                $("#emsa_close_" + systemMessageId).show();

                emsaDupsearchTabset.html('<img alt="Retrieving message details" style="vertical-align: bottom;" src="' + emsaMessageQueue.baseURL + 'img/ajax-loader.gif" height="16" width="16" /> Loading message...');
                msgBodyRow.show();

                setTimeout(function () {
                    let container = $("body,html");
                    let scrollTo = $("#" + systemMessageId);

                    container.scrollTop(container.offset().top); // reset scroll to top of page
                    container.scrollTop(
                        scrollTo.offset().top - container.offset().top + container.scrollTop() - 35
                    ); // scroll to position of clicked row
                    // 2017-08-02: adjusted scrollTo position down by 35px to account for fixed header
                }, 10);

                emsaDupsearchTabset.load("ajax/queue_message_full.php", ajaxParams, function (response, status, xhr) {
                    emsaDupsearchTabset.tabs();
                    emsaDupsearchTabset.tabs("option", "active", ($("#emsa_dupsearch_"+systemMessageId+"_"+emsaMessageQueue.selectedTab).index()-1));

                    //todo:  move jQueryUI button, etc. stuff for dupsearchTabset to here after load
                    $(".override_new_person").button({
                        icon: "ui-icon-elraddcmr"
                    }).on("click", function(e) {
                        e.preventDefault();
                        let thisEvent = $(this).val();

                        if (confirm("Manual Override Confirmation\n\nThis will...\n    - Create a new person in EpiTrax\n    - Add a new Morbidity Event to the new person\n\nReally add new person and event?")) {
                            // multi-click protection...
                            $(".emsa_btn_edit").button("disable");
                            $(".emsa_btn_retry").button("disable");
                            $(".emsa_btn_move").button("disable");
                            $(".emsa_btn_delete").button("disable");
                            $(".emsa_btn_viewpdf").button("disable");
                            $(".override_new_person").button("disable");
                            $(".override_update_cmr").button("disable");
                            $(".override_new_cmr").button("disable");
                            $(".add_new_cmr").button("disable");
                            $(".update_cmr").button("disable");
                            $(".add_bulk_new_cmr").button("disable");
                            $(".update_bulk_cmr").button("disable");
                            $("*").css("cursor", "wait");

                            $("#override_emsa_cmraction_"+thisEvent).val("addnew");
                            $("#override_cmr_"+thisEvent).trigger("submit");
                        } else {
                            return false;
                        }
                    });

                    $(".override_new_cmr").button({
                        icon: "ui-icon-elrupdatecmr"
                    }).on("click", function(e) {
                        e.preventDefault();
                        let thisEvent = $(this).val();
                        let overridePersonId = $("#override_person_"+thisEvent).val();
                        if (overridePersonId === "") {
                            alert('Please enter a Person ID and try again.');
                            return false;
                        } else {
                            if (confirm("Manual Override Confirmation\n\nThis will...\n    - Add a new Morbidity Event to Person ID# " + parseInt(overridePersonId) + "\n\nReally add new event to this person?")) {
                                // multi-click protection...
                                $(".emsa_btn_edit").button("disable");
                                $(".emsa_btn_retry").button("disable");
                                $(".emsa_btn_move").button("disable");
                                $(".emsa_btn_delete").button("disable");
                                $(".emsa_btn_viewpdf").button("disable");
                                $(".override_new_person").button("disable");
                                $(".override_update_cmr").button("disable");
                                $(".override_new_cmr").button("disable");
                                $(".add_new_cmr").button("disable");
                                $(".update_cmr").button("disable");
                                $(".add_bulk_new_cmr").button("disable");
                                $(".update_bulk_cmr").button("disable");
                                $("*").css("cursor", "wait");

                                $("#override_emsa_cmraction_"+thisEvent).val("addnew");
                                $("#override_cmr_"+thisEvent).trigger("submit");
                            } else {
                                return false;
                            }
                        }
                    });

                    $(".override_update_cmr").button({
                        icon: "ui-icon-elrupdatecmr"
                    }).on("click", function(e) {
                        e.preventDefault();
                        let thisEvent = $(this).val();
                        let overrideId = $("#override_event_"+thisEvent).val();
                        if (overrideId === "") {
                            alert('Please enter an Event ID and try again.');
                            return false;
                        } else {
                            if (confirm("Manual Override Confirmation\n\nThis will...\n    - Update Morbidity Event ID# " + parseInt(overrideId) + "\n\nReally update this event?")) {
                                // multi-click protection...
                                $(".emsa_btn_edit").button("disable");
                                $(".emsa_btn_retry").button("disable");
                                $(".emsa_btn_move").button("disable");
                                $(".emsa_btn_delete").button("disable");
                                $(".emsa_btn_viewpdf").button("disable");
                                $(".override_new_person").button("disable");
                                $(".override_update_cmr").button("disable");
                                $(".override_new_cmr").button("disable");
                                $(".add_new_cmr").button("disable");
                                $(".update_cmr").button("disable");
                                $(".add_bulk_new_cmr").button("disable");
                                $(".update_bulk_cmr").button("disable");
                                $("*").css("cursor", "wait");

                                $("#override_emsa_cmraction_"+thisEvent).val("update");
                                $("#override_cmr_"+thisEvent).trigger("submit");
                            } else {
                                return false;
                            }
                        }
                    });

                    $(".emsa_btn_cultureentry").button({
                        icon: "ui-icon-elrbacteria"
                    }).on("click", function() {
                        let editId = $(this).val();
                        let originalObj = {};
                            originalObj.message				= editId;
                            originalObj.disease				= $("#cultureentry_"+editId+"__disease").val();
                            originalObj.organism			= $("#cultureentry_"+editId+"__organism").val();
                            originalObj.jurisdiction		= $("#cultureentry_"+editId+"__jurisdiction").val();
                            originalObj.state_case_status	= $("#cultureentry_"+editId+"__state_case_status").val();
                            originalObj.test_type			= $("#cultureentry_"+editId+"__test_type").val();
                            originalObj.specimen			= $("#cultureentry_"+editId+"__specimen").val();
                            originalObj.testresult			= $("#cultureentry_"+editId+"__testresult").val();
                            originalObj.resultvalue			= $("#cultureentry_"+editId+"__resultvalue").val();
                            originalObj.units				= $("#cultureentry_"+editId+"__units").val();
                            originalObj.comment				= $("#cultureentry_"+editId+"__comment").val();
                            originalObj.teststatus			= $("#cultureentry_"+editId+"__teststatus").val();
                        let originalJson = JSON.stringify(originalObj);

                        $("#semi_auto_original_"+editId).val(originalJson);
                        $("#emsa_btn_edit_"+editId).button({ disabled: true });
                        $("#emsa_btn_retry_"+editId).button({ disabled: true });
                        $(".emsa_cultureentry_"+editId).prop("disabled", false);
                        $(".cultureentry_container_"+editId+" input, .cultureentry_container_"+editId+" select, .cultureentry_container_"+editId+" textarea").addClass("cultureentry_active").addClass("ui-corner-all");
                    });

                    $(".emsa_btn_edit").button({
                        icon: "ui-icon-elrpencil"
                    }).one("click", function() {
                        let editId = $(this).val();

                        // multi-click protection...
                        $(".emsa_btn_edit").button("disable");
                        $(".emsa_btn_retry").button("disable");
                        $(".emsa_btn_move").button("disable");
                        $(".emsa_btn_delete").button("disable");
                        $(".emsa_btn_viewpdf").button("disable");
                        $(".override_new_person").button("disable");
                        $(".override_update_cmr").button("disable");
                        $(".override_new_cmr").button("disable");
                        $(".add_new_cmr").button("disable");
                        $(".update_cmr").button("disable");
                        $(".add_bulk_new_cmr").button("disable");
                        $(".update_bulk_cmr").button("disable");
                        $("*").css("cursor", "wait");

                        $("#emsa_action_"+editId).val("edit");
                        $("#emsa_actions_"+editId).trigger("submit");
                    });

                    $(".emsa_btn_retry").button({
                        icon: "ui-icon-elrretry"
                    }).on("click", function() {
                        let retryId = $(this).val();
                        if (confirm("Are you sure you want to retry sending this event?")) {
                            // multi-click protection...
                            $(".emsa_btn_edit").button("disable");
                            $(".emsa_btn_retry").button("disable");
                            $(".emsa_btn_move").button("disable");
                            $(".emsa_btn_delete").button("disable");
                            $(".emsa_btn_viewpdf").button("disable");
                            $(".override_new_person").button("disable");
                            $(".override_update_cmr").button("disable");
                            $(".override_new_cmr").button("disable");
                            $(".add_new_cmr").button("disable");
                            $(".update_cmr").button("disable");
                            $(".add_bulk_new_cmr").button("disable");
                            $(".update_bulk_cmr").button("disable");
                            $("*").css("cursor", "wait");

                            $("#emsa_action_"+retryId).val("retry");
                            $("#emsa_actions_"+retryId).trigger("submit");
                        } else {
                            return false;
                        }
                    });

                    $(".emsa_btn_move").button({
                        icon: "ui-icon-elrmove"
                    }).on("click", function() {
                        let moveId = $(this).val();
                        let destList = $("#system_status_id_"+moveId);
                        let moveComments = $("#move_info_"+moveId);
                        let destListText = $("#system_status_id_"+moveId+" :selected").text();

                        if (destList.val() < 1) {
                            alert("No list selected!\n\nPlease choose a list to move this event to and try again.");
                            return false;
                        } else {
                            if (confirm("Are you sure you want to move this event to the '"+destListText+"' list?")) {
                                $("#target_"+moveId).val(destList.val());
                                $("#info_"+moveId).val(moveComments.val());

                                // multi-click protection...
                                $(".emsa_btn_edit").button("disable");
                                $(".emsa_btn_retry").button("disable");
                                $(".emsa_btn_move").button("disable");
                                $(".emsa_btn_delete").button("disable");
                                $(".emsa_btn_viewpdf").button("disable");
                                $(".override_new_person").button("disable");
                                $(".override_update_cmr").button("disable");
                                $(".override_new_cmr").button("disable");
                                $(".add_new_cmr").button("disable");
                                $(".update_cmr").button("disable");
                                $(".add_bulk_new_cmr").button("disable");
                                $(".update_bulk_cmr").button("disable");
                                $("*").css("cursor", "wait");

                                $("#emsa_action_"+moveId).val("move");
                                $("#emsa_actions_"+moveId).trigger("submit");
                            } else {
                                return false;
                            }
                        }
                    });

                    $(".emsa_btn_delete").button({
                        icon: "ui-icon-elrclose"
                    }).on("click", function() {
                        let delId = $(this).val();

                        if (confirm("Are you sure you want to delete this event?")) {
                            // multi-click protection...
                            $(".emsa_btn_edit").button("disable");
                            $(".emsa_btn_retry").button("disable");
                            $(".emsa_btn_move").button("disable");
                            $(".emsa_btn_delete").button("disable");
                            $(".emsa_btn_viewpdf").button("disable");
                            $(".override_new_person").button("disable");
                            $(".override_update_cmr").button("disable");
                            $(".override_new_cmr").button("disable");
                            $(".add_new_cmr").button("disable");
                            $(".update_cmr").button("disable");
                            $(".add_bulk_new_cmr").button("disable");
                            $(".update_bulk_cmr").button("disable");
                            $("*").css("cursor", "wait");

                            $("#emsa_action_"+delId).val("delete");
                            $("#emsa_actions_"+delId).trigger("submit");
                        } else {
                            return false;
                        }
                    });

                    $(".emsa_btn_flagcomplete").button().on("click", function() {
                            let moveId = $(this).val();

                            if (confirm("Are you sure you want to flag this event as 'Investigation Completed'?")) {
                                $("#target_"+moveId).val(emsaMessageQueue.EMSA_FLAG_INVESTIGATION_COMPLETE);
                                $("#emsa_action_"+moveId).val("set_flag");
                                $("#emsa_actions_"+moveId).trigger("submit");
                            } else {
                                return false;
                            }
                        });

                    $(".emsa_btn_flagcomplete_off").button({
                        icon: "ui-icon-elrsuccess"
                    }).on("click", function() {
                        let moveId = $(this).val();

                        if (confirm("Are you sure you want to clear the 'Investigation Completed' flag for this event?")) {
                            $("#target_"+moveId).val(emsaMessageQueue.EMSA_FLAG_INVESTIGATION_COMPLETE);
                            $("#emsa_action_"+moveId).val("unset_flag");
                            $("#emsa_actions_"+moveId).trigger("submit");
                        } else {
                            return false;
                        }
                    });


                    $(".emsa_btn_flagdeerror").button().on("click", function() {
                        let moveId = $(this).val();
                        let deReason = $("#de_error_info_"+moveId).val().trim();

                        if (deReason.length === 0) {
                            alert("Please select a type of 'Data Entry Error' and try again.");
                            return false;
                        } else {
                            if (confirm("Are you sure you want to set the Quality Check reason for this message as 'Data Entry Error'?")) {
                                $("#target_"+moveId).val(emsaMessageQueue.EMSA_FLAG_DE_ERROR);
                                $("#emsa_action_"+moveId).val("set_flag");
                                $("#info_"+moveId).val(deReason);
                                $("#emsa_actions_"+moveId).trigger("submit");
                            } else {
                                return false;
                            }
                        }
                    });

                    $(".emsa_btn_flagdeerror_off").button({
                        icon: "ui-icon-elrsuccess"
                    }).on("click", function() {
                        let moveId = $(this).val();
                        let deReason = $("#de_error_info_"+moveId).val().trim();

                        if (confirm("Are you sure you want to un-set 'Data Entry Error' as the Quality Check reason for this message?")) {
                            $("#target_"+moveId).val(emsaMessageQueue.EMSA_FLAG_DE_ERROR);
                            $("#emsa_action_"+moveId).val("unset_flag");
                            $("#info_"+moveId).val(deReason);
                            $("#emsa_actions_"+moveId).trigger("submit");
                        } else {
                            return false;
                        }
                    });

                    $(".emsa_btn_flagdeother").button().on("click", function() {
                        let moveId = $(this).val();
                        let otherReason = $("#de_other_info_"+moveId).val().trim();

                        if (otherReason.length === 0) {
                            alert("Please enter a reason for choosing 'Other' and try again.");
                            return false;
                        } else {
                            if (confirm("Are you sure you want to set the Quality Check reason for this message as 'Other'?")) {
                                $("#target_"+moveId).val(emsaMessageQueue.EMSA_FLAG_DE_OTHER);
                                $("#emsa_action_"+moveId).val("set_flag");
                                $("#info_"+moveId).val(otherReason);
                                $("#emsa_actions_"+moveId).trigger("submit");
                            } else {
                                return false;
                            }
                        }
                    });

                    $(".emsa_btn_flagdeother_off").button({
                        icon: "ui-icon-elrsuccess"
                    }).on("click", function() {
                        let moveId = $(this).val();
                        let otherReason = $("#de_other_info_"+moveId).val().trim();

                        if (confirm("Are you sure you want to un-set 'Other' as the Quality Check reason for this message?")) {
                            $("#target_"+moveId).val(emsaMessageQueue.EMSA_FLAG_DE_OTHER);
                            $("#emsa_action_"+moveId).val("unset_flag");
                            $("#info_"+moveId).val(otherReason);
                            $("#emsa_actions_"+moveId).trigger("submit");
                        } else {
                            return false;
                        }
                    });

                    $(".emsa_btn_flagfixduplicate").button().on("click", function() {
                        let moveId = $(this).val();

                        if (confirm("Are you sure you want to flag this event as 'Fix Duplicate'?")) {
                            $("#target_"+moveId).val(emsaMessageQueue.EMSA_FLAG_FIX_DUPLICATE);
                            $("#emsa_action_"+moveId).val("set_flag");
                            $("#emsa_actions_"+moveId).trigger("submit");
                        } else {
                            return false;
                        }
                    });

                    $(".emsa_btn_flagfixduplicate_off").button({
                        icon: "ui-icon-elrsuccess"
                    }).on("click", function() {
                        let moveId = $(this).val();

                        if (confirm("Are you sure you want to clear the 'Fix Duplicate' flag for this event?")) {
                            $("#target_"+moveId).val(emsaMessageQueue.EMSA_FLAG_FIX_DUPLICATE);
                            $("#emsa_action_"+moveId).val("unset_flag");
                            $("#emsa_actions_"+moveId).trigger("submit");
                        } else {
                            return false;
                        }
                    });

                    $(".emsa_btn_flagneedfix").button().on("click", function() {
                        let moveId = $(this).val();

                        if (confirm("Are you sure you want to flag this event as 'Needs Fixing'?")) {
                            $("#target_"+moveId).val(emsaMessageQueue.EMSA_FLAG_DE_NEEDFIX);
                            $("#emsa_action_"+moveId).val("set_flag");
                            $("#emsa_actions_"+moveId).trigger("submit");
                        } else {
                            return false;
                        }
                    });

                    $(".emsa_btn_flagneedfix_off").button({
                        icon: "ui-icon-elrsuccess"
                    }).on("click", function() {
                        let moveId = $(this).val();

                        if (confirm("Are you sure you want to clear the 'Needs Fixing' flag for this event?")) {
                            $("#target_"+moveId).val(emsaMessageQueue.EMSA_FLAG_DE_NEEDFIX);
                            $("#emsa_action_"+moveId).val("unset_flag");
                            $("#emsa_actions_"+moveId).trigger("submit");
                        } else {
                            return false;
                        }
                    });

                    $(".emsa_btn_flagmandatory").button().on("click", function() {
                        let moveId = $(this).val();

                        if (confirm("Are you sure you want to flag this event as 'Missing Mandatory Fields'?")) {
                            $("#qa_target_"+moveId).val(emsaMessageQueue.EMSA_FLAG_QA_MANDATORY);
                            $("#qa_emsa_action_"+moveId).val("set_flag");
                            $("#emsa_qa_actions_"+moveId).trigger("submit");
                        } else {
                            return false;
                        }
                    });

                    $(".emsa_btn_flagmandatory_off").button({
                        icon: "ui-icon-elrsuccess"
                    }).on("click", function() {
                        let moveId = $(this).val();

                        if (confirm("Are you sure you want to clear the 'Missing Mandatory Fields' flag for this event?")) {
                            $("#qa_target_"+moveId).val(emsaMessageQueue.EMSA_FLAG_QA_MANDATORY);
                            $("#qa_emsa_action_"+moveId).val("unset_flag");
                            $("#emsa_qa_actions_"+moveId).trigger("submit");
                        } else {
                            return false;
                        }
                    });

                    $(".emsa_btn_flagvocabcoding").button().on("click", function() {
                        let moveId = $(this).val();

                        if (confirm("Are you sure you want to flag this event as 'Vocab/Coding Errors'?")) {
                            $("#qa_target_"+moveId).val(emsaMessageQueue.EMSA_FLAG_QA_CODING);
                            $("#qa_emsa_action_"+moveId).val("set_flag");
                            $("#emsa_qa_actions_"+moveId).trigger("submit");
                        } else {
                            return false;
                        }
                    });

                    $(".emsa_btn_flagvocabcoding_off").button({
                        icon: "ui-icon-elrsuccess"
                    }).on("click", function() {
                        let moveId = $(this).val();

                        if (confirm("Are you sure you want to clear the 'Vocab/Coding Errors' flag for this event?")) {
                            $("#qa_target_"+moveId).val(emsaMessageQueue.EMSA_FLAG_QA_CODING);
                            $("#qa_emsa_action_"+moveId).val("unset_flag");
                            $("#emsa_qa_actions_"+moveId).trigger("submit");
                        } else {
                            return false;
                        }
                    });

                    $(".emsa_btn_flagmqf").button().on("click", function() {
                        let moveId = $(this).val();

                        if (confirm("Are you sure you want to flag this event as 'MQF Structural Errors'?")) {
                            $("#qa_target_"+moveId).val(emsaMessageQueue.EMSA_FLAG_QA_MQF);
                            $("#qa_emsa_action_"+moveId).val("set_flag");
                            $("#emsa_qa_actions_"+moveId).trigger("submit");
                        } else {
                            return false;
                        }
                    });

                    $(".emsa_btn_flagmqf_off").button({
                        icon: "ui-icon-elrsuccess"
                    }).on("click", function() {
                        let moveId = $(this).val();

                        if (confirm("Are you sure you want to clear the 'MQF Structural Errors' flag for this event?")) {
                            $("#qa_target_"+moveId).val(emsaMessageQueue.EMSA_FLAG_QA_MQF);
                            $("#qa_emsa_action_"+moveId).val("unset_flag");
                            $("#emsa_qa_actions_"+moveId).trigger("submit");
                        } else {
                            return false;
                        }
                    });

                    $(".emsa_btn_addcomment").button({
                        icon: "ui-icon-elraddcomment"
                    }).on("click", function() {
                        let moveId = $(this).val();
                        let moveComments = $("#add_comment_"+moveId);

                        $("#qa_target_"+moveId).val('');
                        $("#qa_emsa_action_"+moveId).val("add_qa_comment");
                        $("#qa_info_"+moveId).val(moveComments.val());
                        $("#emsa_qa_actions_"+moveId).trigger("submit");
                    });

                    $(".emsa_btn_viewnedss").button({
                        icon: "ui-icon-elrview"
                    });

                    $(".emsa_btn_viewpdf").button({
                        icon: "ui-fileicon-pdf"
                    });

                    // audit log xml viewer
                    $(".audit_view_xml").button({
                        icon: "ui-icon-elrxml-small"
                    }).on("click", function() {
                        // loads xml viewer dialog
                        let jsonObj = JSON.parse($(this).val());

                        $("#view_xml_dlg").remove();
                        let viewXmlModal = $("<div id='view_xml_dlg' title='View XML'></div>");

                        $.post("emsa/xml_audit_log.php", { id: jsonObj.id, type: jsonObj.type })
                        .done(function(xmlData) {
                            viewXmlModal.html(xmlData).dialog({
                                autoOpen: false,
                                height: 400,
                                width: 500,
                                modal: true,
                                buttons: {
                                    "OK": function() {
                                        $(this).dialog("close");
                                    }
                                }
                            }).dialog("open");
                        }).fail(function(vxErrXhr, vxErrText, vxErrThrown) {
                            alert("Could not view XML.\n\nError Details:\n"+vxErrText+" ("+vxErrThrown+")");
                        })
                    });





                    let personSearchResultsPanelId = "emsa_dupsearch_" + systemMessageId + "_tab1";
                    let personSearchResultsPanel = $("#" + personSearchResultsPanelId);

                    // people search
                    $.post("ajax/people_search.php", ajaxParams)
                        .done(function (thisData) {
                            if (thisData.indexOf("AUTOSUBMIT") > 0) {
                                // intercept siteminder login prompt within People Search Results container
                                // to prevent a badly broken UI
                                window.location.href = emsaMessageQueue.baseURL;
                            } else {
                                personSearchResultsPanel.empty();
                                personSearchResultsPanel.html(thisData);
                                $(".add_new_cmr").button({
                                    icon: "ui-icon-elraddcmr"
                                }).on("click", function (e) {
                                    e.preventDefault();
                                    let thisEvent = $(this).val();

                                    // check for semi-automated lab entry, save changes if necessary
                                    semiAutomatedEntry(thisEvent, 'add_new_cmr');
                                });
                                $(".add_bulk_new_cmr").button({
                                    icon: "ui-icon-elraddcmr"
                                }).on("click", function (e) {
                                    e.preventDefault();
                                    let thisEvent = $(this).val();

                                    // check for semi-automated lab entry, save changes if necessary
                                    semiAutomatedEntry(thisEvent, 'add_bulk_new_cmr');
                                });
                                $(".update_cmr").button({
                                    icon: "ui-icon-elrupdatecmr"
                                }).on("click", function (e) {
                                    e.preventDefault();
                                    let thisEvent = $(this).val();

                                    // check for semi-automated lab entry, save changes if necessary
                                    semiAutomatedEntry(thisEvent, 'update_cmr');
                                });
                                $(".update_bulk_cmr").button({
                                    icon: "ui-icon-elrupdatecmr"
                                }).on("click", function (e) {
                                    e.preventDefault();
                                    let thisEvent = $(this).val();

                                    // check for semi-automated lab entry, save changes if necessary
                                    semiAutomatedEntry(thisEvent, 'update_bulk_cmr');
                                });
                                $(".emsa_cmrbtn_move").button({
                                    icon: "ui-icon-elrmove"
                                }).on("click", function () {
                                    let moveId = $(this).val();
                                    let destList = $("#cmr_status_id_" + moveId);
                                    let moveComments = $("#cmr_move_info_" + moveId);
                                    let destListText = $("#cmr_status_id_" + moveId + " :selected").text();

                                    if (destList.val() < 1) {
                                        alert("No list selected!\n\nPlease choose a list to move this event to and try again.");
                                        return false;
                                    } else {
                                        if (confirm("Are you sure you want to move this event to the '" + destListText + "' list?")) {
                                            // multi-click protection...
                                            $(".emsa_btn_edit").button("disable");
                                            $(".emsa_btn_retry").button("disable");
                                            $(".emsa_btn_move").button("disable");
                                            $(".emsa_btn_delete").button("disable");
                                            $(".emsa_btn_viewpdf").button("disable");
                                            $(".override_new_person").button("disable");
                                            $(".override_update_cmr").button("disable");
                                            $(".override_new_cmr").button("disable");
                                            $(".add_new_cmr").button("disable");
                                            $(".update_cmr").button("disable");
                                            $(".add_bulk_new_cmr").button("disable");
                                            $(".update_bulk_cmr").button("disable");
                                            $("*").css("cursor", "wait");

                                            $("#cmrtarget_" + moveId).val(destList.val());
                                            $("#cmrinfo_" + moveId).val(moveComments.val());
                                            $("#emsa_cmraction_" + moveId).val("move");
                                            $("#cmr_" + moveId).trigger("submit");
                                        } else {
                                            return false;
                                        }
                                    }
                                });
                                $(".emsa_search_results tr").on("click", function (event) {
                                    if (event.target.nodeName !== "INPUT") {
                                        let thisSearchId = this.id.substring((this.id.search("__") + 2));
                                        let thisPersonId = msgBodyRowId.substring(10);
                                        let thisCheckbox = $("#use_person_" + thisPersonId + "_" + thisSearchId);

                                        thisCheckbox.prop("checked", !thisCheckbox.prop("checked"));
                                    }
                                });
                                $(".person_match_found td input:checkbox").each(function () {
                                    this.checked = true;
                                });  // auto-check all exact matches
                            }
                        });
                });
            } else {
                $(".emsa_dup:not(#"+systemMessageId+")").removeClass("emsa_dup_dimmed");
                $("#"+systemMessageId+" > td").removeClass("emsa_results_search_parent");
                $("#emsa_close_"+systemMessageId).hide();
                emsaDupsearchTabset.tabs("destroy");
                emsaDupsearchTabset.empty();
                msgBodyRow.hide();
            }
        }
    });
});
/*
  Copyright (c) 2019 Utah Department of Technology Services and Utah Department of Health

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
$(function () {
    $("body").on("focus", "ul.vocab_filter_checklist[role=listbox]", function (e) {
        if (!$(this).find(".multiselect-focused").length) {
            var activeItem = $(this).find("[role=option]:first");
            $(this).attr("aria-activedescendant", activeItem.attr("id"));
            activeItem.addClass("multiselect-focused");
        }
    }).on("keydown", "ul.vocab_filter_checklist[role=listbox]", function (e) {
        var currentItem = $(this).find(".multiselect-focused");
        var firstItem = $(this).find("[role=option]:first");
        switch (e.keyCode) {
            case 38:  // Up arrow
                if (currentItem.prev().length) {
                    $(this).attr("aria-activedescendant", currentItem.prev().attr("id"));
                    currentItem.removeClass("multiselect-focused");
                    currentItem.prev().addClass("multiselect-focused");

                    currentListboxScrollTop = $(this).scrollTop();
                    currentListboxOffset = $(this).offset().top;
                    targetItemOffset = currentItem.prev().offset().top;
                    targetItemHeight = currentItem.prev().height();
                    currentListboxGap = firstItem.offset().top - currentListboxOffset + currentListboxScrollTop;
                    //currentListboxGap = 4;

                    if ((currentListboxOffset + currentListboxGap) >= (targetItemOffset + targetItemHeight)) {
                        scrollTarget = currentListboxScrollTop - currentListboxOffset + targetItemOffset - currentListboxGap;
                        $(this).scrollTop(scrollTarget);
                    }
                }
                e.preventDefault();
                break;
            case 40:  // Down arrow
                if (currentItem.next().length) {
                    $(this).attr("aria-activedescendant", currentItem.next().attr("id"));
                    currentItem.removeClass("multiselect-focused");
                    currentItem.next().addClass("multiselect-focused");

                    targetItemOffset = currentItem.next().offset().top;
                    targetItemHeight = currentItem.next().height();
                    currentListboxOffset = $(this).offset().top;
                    //currentListboxGap = 4;
                    currentListboxHeight = $(this).height();
                    currentListboxScrollTop = $(this).scrollTop();
                    currentListboxGap = firstItem.offset().top - currentListboxOffset + currentListboxScrollTop;

                    if ((currentListboxOffset + currentListboxGap + currentListboxHeight) < (targetItemOffset + targetItemHeight)) {
                        scrollTarget = targetItemOffset - (currentListboxOffset + currentListboxGap) - currentListboxHeight + targetItemHeight + currentListboxScrollTop;
                        $(this).scrollTop(scrollTarget);
                    }
                }
                e.preventDefault();
                break;
            case 36:  // Home
            case 33:  // PgUp
                targetItem = $(this).find("[role=option]:first");
                currentItem.removeClass("multiselect-focused");
                targetItem.addClass("multiselect-focused");
                $(this).attr("aria-activedescendant", targetItem.attr("id"));

                currentListboxScrollTop = $(this).scrollTop();
                currentListboxOffset = $(this).offset().top;
                //currentListboxGap = 4;
                targetItemOffset = targetItem.offset().top;
                targetItemHeight = targetItem.height();
                currentListboxGap = firstItem.offset().top - currentListboxOffset + currentListboxScrollTop;

                scrollTarget = currentListboxScrollTop - currentListboxOffset + targetItemOffset - currentListboxGap;
                $(this).scrollTop(scrollTarget);

                e.preventDefault();
                break;
            case 35:  // End
            case 34:  // PgDown
                targetItem = $(this).find("[role=option]:last");
                currentItem.removeClass("multiselect-focused");
                targetItem.addClass("multiselect-focused");
                $(this).attr("aria-activedescendant", targetItem.attr("id"));

                targetItemOffset = targetItem.offset().top;
                targetItemHeight = targetItem.height();
                currentListboxOffset = $(this).offset().top;
                //currentListboxGap = 4;
                currentListboxHeight = $(this).height();
                currentListboxScrollTop = $(this).scrollTop();
                currentListboxGap = firstItem.offset().top - currentListboxOffset + currentListboxScrollTop;

                scrollTarget = targetItemOffset - (currentListboxOffset + currentListboxGap) - currentListboxHeight + targetItemHeight + currentListboxScrollTop;
                $(this).scrollTop(scrollTarget);

                e.preventDefault();
                break;
            case 32:  // Space
                if (currentItem.closest("[role=option]").attr("aria-selected") === "true") {
                    //currentItem.closest("[role=option]").attr("aria-selected", "false");
                    currentItem.find("input[type=checkbox]").prop("checked", false).trigger("change");
                } else {
                    //currentItem.closest("[role=option]").attr("aria-selected", "true");
                    currentItem.find("input[type=checkbox]").prop("checked", true).trigger("change");
                }
                e.preventDefault();
                break;
            case 13:
                e.preventDefault();
                break;
        }
    }).on("mouseover", "ul.vocab_filter_checklist[role=listbox]", function (e) {
        $(this).removeAttr("aria-activedescendant");
        $(this).children().removeClass("multiselect-focused");
    }).on("mousedown", "ul.vocab_filter_checklist[role=listbox] li[role=option] input[type=checkbox]", function (e) {
        e.preventDefault();
    }).on("mousedown", "ul.vocab_filter_checklist[role=listbox] li[role=option] label", function (e) {
        e.preventDefault();
    }).on("click", "ul.vocab_filter_checklist[role=listbox] li[role=option] label", function (e) {
        if (this === e.target) {
            e.preventDefault();
            this.control.click();
        }
    }).on("change", "ul.vocab_filter_checklist[role=listbox] li[role=option] input[type=checkbox]", function () {
        allOptions = $(this).closest("[role=listbox]").children().not(".multi_list_selectall");

        if ($(this).closest("[role=option]").hasClass("multi_list_selectall")) {
            allOptions = $(this).closest("[role=listbox]").children().not($(this).closest("[role=option]"));

            if ($(this).prop("checked")) {
                $(this).closest("[role=option]").attr("aria-selected", "true");
                allOptions.attr("aria-selected", "true").find("input[type=checkbox]").prop("checked", true);
            } else {
                $(this).closest("[role=option]").attr("aria-selected", "false");
                allOptions.attr("aria-selected", "false").find("input[type=checkbox]").prop("checked", false);
            }
        } else {
            if ($(this).prop("checked")) {
                $(this).closest("[role=option]").attr("aria-selected", "true");
            } else {
                $(this).closest("[role=option]").attr("aria-selected", "false");
            }

            selectedOptions = $(this).closest("[role=listbox]").children("[aria-selected=true]").not(".multi_list_selectall");
            selectAllToggle = $(this).closest("[role=listbox]").find(".multi_list_selectall");

            if (allOptions.length === selectedOptions.length) {
                // all options now selected, mark 'Select All' as selected
                selectAllToggle.find("input[type=checkbox]").prop("checked", true);
                selectAllToggle.attr("aria-selected", "true");
            } else {
                selectAllToggle.find("input[type=checkbox]").prop("checked", false);
                selectAllToggle.attr("aria-selected", "false");
            }
        }
    });
});
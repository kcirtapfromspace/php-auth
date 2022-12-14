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
(function ($) {
    'use strict';

    const DataStatePropertyName = 'multiselect';
    const EventNamespace = '.multiselect';
    const PluginName = 'MultiSelect';

    var old = $.fn[PluginName];
    $.fn[PluginName] = plugin;
    $.fn[PluginName].Constructor = MultiSelect;
    $.fn[PluginName].noConflict = function () {
        $.fn[PluginName] = old;
        return this;
    };

    // Defaults
    $.fn[PluginName].defaults = {};

    // Static members
    $.fn[PluginName].EventNamespace = function () {
        return EventNamespace.replace(/^\./ig, '');
    };
    $.fn[PluginName].GetNamespacedEvents = function (eventsArray) {
        return getNamespacedEvents(eventsArray);
    };

    function getNamespacedEvents(eventsArray) {
        var event;
        var namespacedEvents = "";
        while (event = eventsArray.shift()) {
            namespacedEvents += event + EventNamespace + " ";
        }
        return namespacedEvents.replace(/\s+$/g, '');
    }

    function plugin(option) {
        this.each(function () {
            var $target = $(this);
            var multiSelect = $target.data(DataStatePropertyName);
            var options = (typeof option === typeof {} && option) || {};

            if (!multiSelect) {
                $target.data(DataStatePropertyName, multiSelect = new MultiSelect(this, options));
            }

            if (typeof option === typeof "") {
                if (!(option in multiSelect)) {
                    throw "MultiSelect does not contain a method named '" + option + "'";
                }
                return multiSelect[option]();
            }
        });
    }

    function MultiSelect(element, options) {
        this.$element = $(element);
        this.options = $.extend({}, $.fn[PluginName].defaults, options);
        this.destroyFns = [];

        this.$toggle = this.$element.children('.toggle');
        this.$toggle.attr('id', this.$element.attr('id') + 'multi-select-label');
        this.$backdrop = null;
        this.$allToggle = null;

        init.apply(this);
    }

    MultiSelect.prototype.open = open;
    MultiSelect.prototype.close = close;

    function init() {
        this.$element
            .addClass('multi-select')
            .attr('tabindex', 0);

        initAria.apply(this);
        initEvents.apply(this);
        injectToggleAll.apply(this);

        var that = this;
        var checkboxes = that.$element
            .find(":checkbox")
            .not(that.$allToggle.find(":checkbox"))
            .filter(":checked");
        
        that.$allToggle.find(":checkbox").prop("checked", checkboxes.length === checkboxes.end().length);

        updateLabel.apply(this);

        this.destroyFns.push(function () {
            return '|'
        });
    }

    function injectToggleAll() {
        if (this.$allToggle && !this.$allToggle.parent()) {
            this.$allToggle = null;
        }

        this.$allToggle = $("<li><label><input type='checkbox'/> &lt;Select All&gt;</label><li>");

        this.$element
            .children('ul:first')
            .prepend(this.$allToggle);
    }

    function initAria() {
        this.$element
            .attr('aria-multiselect', true)
            .attr('aria-expanded', false)
            .attr('aria-haspopup', 'listbox')
            .attr('aria-labeledby', this.$element.attr("aria-labeledby") + " " + this.$toggle.attr('id'));

        this.$toggle
            .attr('aria-label', '');
    }

    function initEvents() {
        var that = this;
        this.$element
            .on(getNamespacedEvents(['click']), function ($event) {
                if ($event.target !== that.$toggle[0] && !that.$toggle.has($event.target).length) {
                    return;
                }

                if ($(this).hasClass('in')) {
                    that.close();
                } else {
                    that.open();
                }
            })
            .on(getNamespacedEvents(['keydown']), function ($event) {
                var next = false;
                switch ($event.keyCode) {
                    case 13:
                        $event.preventDefault();
                        if ($(this).hasClass('in')) {
                            that.close();
                        } else {
                            that.open();
                        }
                        break;
                    case 9:
                        if ($event.target !== that.$element[0]) {
                            $event.preventDefault();
                        }
                    case 27:
                        that.close();
                        break;
                    case 40:
                        next = true;
                    case 38:
                        $event.preventDefault();
                        var $items = $(this)
                            .children("ul:first")
                            .find(":input, button, a");

                        var foundAt = $.inArray(document.activeElement, $items);
                        if (next && ++foundAt === $items.length) {
                            foundAt = 0;
                        } else if (!next && --foundAt < 0) {
                            foundAt = $items.length - 1;
                        }

                        $($items[foundAt])
                            .trigger('focus');
                }
            })
            .on(getNamespacedEvents(['focus']), 'a, button, :input', function () {
                $(this)
                    .parents('li:last')
                    .addClass('focused');
            })
            .on(getNamespacedEvents(['blur']), 'a, button, :input', function () {
                $(this)
                    .parents('li:last')
                    .removeClass('focused');
            })
            .on(getNamespacedEvents(['change']), ':checkbox', function () {
                if (that.$allToggle && $(this).is(that.$allToggle.find(':checkbox'))) {
                    var allChecked = that.$allToggle
                        .find(':checkbox')
                        .prop("checked");

                    that.$element
                        .find(':checkbox')
                        .not(that.$allToggle.find(":checkbox"))
                        .each(function () {
                            $(this).prop("checked", allChecked);
                            $(this)
                                .parents('li:last')
                                .toggleClass('selected', $(this).prop('checked'));
                        });

                    updateLabel.apply(that);
                    return;
                }

                $(this)
                    .parents('li:last')
                    .toggleClass('selected', $(this).prop('checked'));

                var checkboxes = that.$element
                    .find(":checkbox")
                    .not(that.$allToggle.find(":checkbox"))
                    .filter(":checked");

                that.$allToggle.find(":checkbox").prop("checked", checkboxes.length === checkboxes.end().length);

                updateLabel.apply(that);
            })
            .on(getNamespacedEvents(['mouseover']), 'ul', function () {
                $(this)
                    .children(".focused")
                    .removeClass("focused");
            });
    }

    function updateLabel() {
        var pluralize = function (wordSingular, count) {
            if (count !== 1) {
                if (/y$/.test(wordSingular)) {
                    wordSingular = wordSingular.replace(/y$/, "ies");
                } else {
                    wordSingular = wordSingular + "s";
                }
            }
            return wordSingular;
        };

        var $checkboxes = this.$element
            .find('ul :checkbox');

        var allCount = $checkboxes.length - 1;
        var checkedCount = $checkboxes.filter(":checked").length;
        var label = checkedCount + " of " + allCount + " " + pluralize("Reporter", allCount);

        // account for 'Select All' being checked and over-counting checked checkboxes
        if (checkedCount > allCount) {
            checkedCount--;
        }

        this.$toggle
            .children("span:first")
            .text(checkedCount ? (checkedCount === allCount ? 'All Reporters' : label) : 'No Reporters');

        this.$element
            .children('ul')
            .attr("aria-label", checkedCount ? (checkedCount === allCount ? 'All Reporters' : label) : 'No Reporters');
    }

    function ensureFocus() {
        this.$element
            .children("ul:first")
            .find(":input, button, a")
            .first()
            .trigger('focus')
            .end()
            .end()
            .find(":checked")
            .first()
            .trigger('focus');
    }

    function addBackdrop() {
        if (this.$backdrop) {
            return;
        }

        var that = this;
        this.$backdrop = $("<div class='multi-select-backdrop'/>");
        this.$element.append(this.$backdrop);

        this.$backdrop
            .on('click', function () {
                $(this)
                    .off('click')
                    .remove();

                that.$backdrop = null;
                that.close();
            });
    }

    function open() {
        if (this.$element.hasClass('in')) {
            return;
        }

        this.$element
            .addClass('in');

        this.$element
            .attr('aria-expanded', true);

        addBackdrop.apply(this);
        ensureFocus.apply(this);
    }

    function close() {
        this.$element
            .removeClass('in')
            .trigger('focus');

        this.$element
            .attr('aria-expanded', false);

        if (this.$backdrop) {
            this.$backdrop.trigger('click');
        }
    }
})(jQuery);
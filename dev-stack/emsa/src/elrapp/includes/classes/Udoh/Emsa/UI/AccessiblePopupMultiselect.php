<?php

namespace Udoh\Emsa\UI;

/**
 * Copyright (c) 2019 Utah Department of Technology Services and Utah Department of Health
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

use Udoh\Emsa\Utils\DisplayUtils;

/**
 * Multi-Select pop-up menu that supports accessible technologies, including keyboard navigation.
 *
 * @package Udoh\Emsa\UI
 *
 * @author  Josh Ridderhoff <jridderhoff@utah.gov>
 */
class AccessiblePopupMultiselect
{
    /** @var array */
    protected $options = [];
    /** @var array */
    protected $selectedOptions = [];

    /**
     * AccessibleMultiselect constructor.
     *
     * @param array      $options         Array of choices to show up as options.  Key will be the value used in
     *                                    checkboxes, value will be the displayed text for the choices.
     * @param array|null $selectedOptions [Optional] An array of key values that should be pre-selected when rendered.
     */
    public function __construct(array $options, ?array $selectedOptions = null)
    {
        $this->options = $options;

        if (!empty($selectedOptions)) {
            $this->selectedOptions = $selectedOptions;
        }
    }

    /**
     * Render the Multiselect widget.
     *
     * @param string    $legend       String to be displayed in the legend.
     * @param string    $elementName  Name to be used to identify the set of checkboxes.
     * @param bool|null $legendOnLeft If true, legend will be rendered to the left of the fieldset.  Otherwise, will
     *                                render on top.
     */
    public function render(string $legend, string $elementName, ?bool $legendOnLeft = false): void
    {
        $cleanLegend = DisplayUtils::xSafe($legend, "UTF-8");
        $cleanElementName = addslashes($elementName);

        $multiSelectCode = "<fieldset class=\"emsa-multiselect\">\n";

        if ($legendOnLeft) {
            $multiSelectCode .= "\t<legend class=\"emsa-ms-legend-left\" id=\"ms-legend-$cleanElementName\">$cleanLegend:</legend>&nbsp;\n";
        } else {
            $multiSelectCode .= "\t<legend id=\"ms-legend-$cleanElementName\">$cleanLegend:</legend>&nbsp;\n";
        }

        $multiSelectCode .= "\t<div id=\"multi-select-plugin-$cleanElementName\">\n";
        $multiSelectCode .= "\t\t<span class=\"toggle ui-corner-all\">\n";
        $multiSelectCode .= "\t\t\t<span>Select a value</span>\n\t\t\t<span class=\"chevron\">&lt;</span>\n";
        $multiSelectCode .= "\t\t</span>\n";
        $multiSelectCode .= "\t\t<ul role=\"listbox\" class=\"ui-corner-all\">\n";

        if (!empty($this->options)) {
            foreach ($this->options as $optionValue => $optionText) {
                $cleanOptionValue = addslashes($optionValue);
                $cleanOptionText = DisplayUtils::xSafe($optionText, "UTF-8");

                if (empty($this->selectedOptions) || in_array($optionValue, $this->selectedOptions)) {
                    // choice is pre-selected or no options are selected (pick all)
                    $multiSelectCode .= "\t\t\t<li role=\"option\" class=\"selected\">\n";
                    $multiSelectCode .= "\t\t\t\t<label>\n";
                    $multiSelectCode .= "\t\t\t\t\t<input tabindex=\"-1\" type=\"checkbox\" name=\"" . $cleanElementName . "[]\" value=\"$cleanOptionValue\" checked=\"checked\"/> $cleanOptionText\n";
                    $multiSelectCode .= "\t\t\t\t</label>\n";
                    $multiSelectCode .= "\t\t\t</li>\n";
                } else {
                    $multiSelectCode .= "\t\t\t<li role=\"option\">\n";
                    $multiSelectCode .= "\t\t\t\t<label>\n";
                    $multiSelectCode .= "\t\t\t\t\t<input tabindex=\"-1\" type=\"checkbox\" name=\"" . $cleanElementName . "[]\" value=\"$cleanOptionValue\"/> $cleanOptionText\n";
                    $multiSelectCode .= "\t\t\t\t</label>\n";
                    $multiSelectCode .= "\t\t\t</li>\n";
                }
            }
        }

        $multiSelectCode .= "\t\t</ul>\n";
        $multiSelectCode .= "\t</div>\n";
        $multiSelectCode .= "</fieldset>\n\n";

        $multiSelectCode .= "<script>\n";
        $multiSelectCode .= "\t" . '$(function() {' . "\n";
        $multiSelectCode .= "\t\t" . '$("#multi-select-plugin-' . $cleanElementName . '").MultiSelect();' . "\n";
        $multiSelectCode .= "\t" . '});' . "\n";
        $multiSelectCode .= "</script>\n\n";

        echo $multiSelectCode;
    }
}
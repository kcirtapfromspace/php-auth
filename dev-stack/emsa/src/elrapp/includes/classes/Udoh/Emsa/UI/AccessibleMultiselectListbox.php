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

use HTMLPurifier;
use HTMLPurifier_Config;

/**
 * Multi-Select list box that supports accessible technologies, including keyboard navigation.
 *
 * @package Udoh\Emsa\UI
 *
 * @author  Josh Ridderhoff <jridderhoff@utah.gov>
 */
class AccessibleMultiselectListbox
{
    /** @var array */
    protected $options = [];
    /** @var array */
    protected $selectedOptions = [];
    /** @var HTMLPurifier */
    protected $purifier;

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

        $defaultHTMLPurifierConfig = HTMLPurifier_Config::createDefault();
        $this->purifier = new HTMLPurifier($defaultHTMLPurifierConfig);
    }

    /**
     * Render the Multiselect widget.
     *
     * @param string      $legend        String to be displayed in the legend.
     * @param string      $elementName   Name to be used to identify the set of checkboxes.
     * @param bool|null   $hasSelectAll  [Optional; Default false]<br>If true, includes a 'Select All' checkbox in the list of options.
     * @param bool|null   $legendOnLeft  [Optional; Default false]<br>If true, legend will be rendered to the left of the fieldset.  Otherwise, will render on top.
     * @param string|null $filterColumn  [Optional; Default null]<br>Used to specify filter column for Vocab filters generated dynamically from an array.  If specified, <i>booleanFields</i> and <i>isLookupField</i> are required.
     * @param array|null  $booleanFields [Optional; Default empty array]<br>Lists the field names that are boolean field types.
     * @param bool|null   $isLookupField [Optional; Default false]<br>If true, indicates that the options in this list are looked up from a list of possible values.
     */
    public function render(string $legend, string $elementName, ?bool $hasSelectAll = false, ?bool $legendOnLeft = false, ?string $filterColumn = null, ?array $booleanFields = [], ?bool $isLookupField = false): void
    {
        $cleanLegend = htmlspecialchars($this->purifier->purify($legend));
        $cleanElementName = preg_replace('/[^a-zA-Z0-9\[\]\_\s]/', '', strip_tags(html_entity_decode($elementName)));

        if ($isLookupField) {
            // if it's a lookup field, add a "<Blank>" choice at the end of the list
            $this->addBlankOption();
        }

        $multiSelectCode = "<fieldset class=\"vocab_filter_container\">\n";

        if ($legendOnLeft) {
            $multiSelectCode .= "\t<legend class=\"emsa-ms-legend-left\" id=\"ms-legend-$cleanElementName\">$cleanLegend</legend>&nbsp;\n";
        } else {
            $multiSelectCode .= "\t<legend id=\"ms-legend-$cleanElementName\">$cleanLegend</legend>\n";
        }

        $multiSelectCode .= "\t<ul role=\"listbox\" class=\"vocab_filter_checklist ui-corner-all\" tabindex=\"0\">\n";

        if (!empty($this->options)) {
            $optionCount = 0;
            $selectedOptionCount = 0;
            $multiSelectOptions = "";

            foreach ($this->options as $optionValue => $optionText) {
                $cleanOptionValue = htmlspecialchars($this->purifier->purify($optionValue));

                if (empty($optionText)) {
                    $cleanOptionText = '&lt;Blank&gt;';
                } else {
                    $cleanOptionText = htmlspecialchars($this->purifier->purify($optionText));
                }

                if (!empty($booleanFields) && in_array($filterColumn, $booleanFields)) {
                    // a boolean data field; labels of options change depending on which field we're dealing with
                    if ($filterColumn === "is_initial") {
                        $cleanOptionText = ($cleanOptionValue === "t") ? "Initial" : "Final";
                    } elseif ($filterColumn === "pregnancy_status") {
                        if ($cleanOptionValue === "t") {
                            $cleanOptionText = 'Yes';
                        } elseif ($cleanOptionValue === "f") {
                            $cleanOptionText = 'No';
                        } else {
                            $cleanOptionText = 'Unknown';
                        }
                    } elseif ($filterColumn === "interpret_override") {
                        if ($cleanOptionValue === "t") {
                            $cleanOptionText = 'Override Quantitative';
                        } elseif ($cleanOptionValue === "f") {
                            $cleanOptionText = 'Override Coded Entry';
                        } else {
                            $cleanOptionText = 'Set by OBX-2';
                        }
                    } elseif ($filterColumn === "semi_auto_usage") {
                        if ($cleanOptionValue === "t") {
                            $cleanOptionText = 'Force Semi-Auto';
                        } elseif ($cleanOptionValue === "f") {
                            $cleanOptionText = 'Skip Semi-Auto';
                        } else {
                            $cleanOptionText = 'Allow Semi-Auto';
                        }
                    } else {
                        $cleanOptionText = ($cleanOptionValue === "t") ? "Yes" : "No";
                    }
                } else {
                    if ($filterColumn === "workflow") {
                        switch ((int) $optionValue) {
							case ENTRY_STATUS:
								$cleanOptionText = 'Automated Processing';
								break;
							case QA_STATUS:
								$cleanOptionText = 'QA Review';
								break;
							case SEMI_AUTO_STATUS:
								$cleanOptionText = 'Semi-Automated Entry';
								break;
							default:
								$cleanOptionText = '[Unknown]';
								break;
						}
                    }
                }

                if (!empty($this->selectedOptions) && in_array($optionValue, $this->selectedOptions)) {
                    // choice is pre-selected
                    $selectedOptionCount++;
                    $multiSelectOptions .= "\t\t<li id=\"" . $cleanElementName . "_" . $optionCount . "\" role=\"option\" aria-selected=\"true\">\n";
                    $multiSelectOptions .= "\t\t\t<label class=\"pseudo_select_label\">\n";
                    $multiSelectOptions .= "\t\t\t\t<input class=\"pseudo_select\" tabindex=\"-1\" type=\"checkbox\" name=\"" . $cleanElementName . "[]\" value=\"$cleanOptionValue\" checked=\"checked\"/> $cleanOptionText\n";
                    $multiSelectOptions .= "\t\t\t</label>\n";
                    $multiSelectOptions .= "\t\t</li>\n";
                } else {
                    $multiSelectOptions .= "\t\t<li id=\"" . $cleanElementName . "_" . $optionCount . "\" role=\"option\" aria-selected=\"false\">\n";
                    $multiSelectOptions .= "\t\t\t<label class=\"pseudo_select_label\">\n";
                    $multiSelectOptions .= "\t\t\t\t<input class=\"pseudo_select\" tabindex=\"-1\" type=\"checkbox\" name=\"" . $cleanElementName . "[]\" value=\"$cleanOptionValue\"/> $cleanOptionText\n";
                    $multiSelectOptions .= "\t\t\t</label>\n";
                    $multiSelectOptions .= "\t\t</li>\n";
                }

                $optionCount++;
            }

            if ($hasSelectAll) {
                if ($selectedOptionCount === $optionCount) {
                    // pre-select 'Select All' checkbox
                    $multiSelectCode .= "\t\t<li class=\"multi_list_selectall\" id=\"" . $cleanElementName . "_selectall\" role=\"option\" aria-selected=\"true\">\n";
                    $multiSelectCode .= "\t\t\t<label class=\"pseudo_select_label\">\n";
                    $multiSelectCode .= "\t\t\t\t<input class=\"pseudo_select\" tabindex=\"-1\" type=\"checkbox\" name=\"" . $cleanElementName . "_selectall\" value=\"0\" checked=\"checked\"/> &lt;Select All&gt;\n";
                    $multiSelectCode .= "\t\t\t</label>\n";
                    $multiSelectCode .= "\t\t</li>\n";
                } else {
                    $multiSelectCode .= "\t\t<li class=\"multi_list_selectall\" id=\"" . $cleanElementName . "_selectall\" role=\"option\" aria-selected=\"false\">\n";
                    $multiSelectCode .= "\t\t\t<label class=\"pseudo_select_label\">\n";
                    $multiSelectCode .= "\t\t\t\t<input class=\"pseudo_select\" tabindex=\"-1\" type=\"checkbox\" name=\"" . $cleanElementName . "_selectall\" value=\"0\"/> &lt;Select All&gt;\n";
                    $multiSelectCode .= "\t\t\t</label>\n";
                    $multiSelectCode .= "\t\t</li>\n";
                }
            }

            $multiSelectCode .= $multiSelectOptions;
        }

        $multiSelectCode .= "\t</ul>\n";
        $multiSelectCode .= "</fieldset>\n\n";

        echo $multiSelectCode;
    }

    /**
     * Adds a '<Blank>' choice to the list of filter options
     */
    protected function addBlankOption(): void
    {
         $this->options["-1"] = "<Blank>";
    }
}
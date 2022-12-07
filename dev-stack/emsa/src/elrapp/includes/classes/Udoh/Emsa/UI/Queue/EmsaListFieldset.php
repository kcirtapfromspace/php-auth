<?php

namespace Udoh\Emsa\UI\Queue;

/**
 * Copyright (c) 2018 Utah Department of Technology Services and Utah Department of Health
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
 * @copyright Copyright (c) 2018 Utah Department of Technology Services and Utah Department of Health
 */

use DateTime;
use PDO;
use EmsaUtils;
use Udoh\Emsa\Client\AppClientList;
use Udoh\Emsa\Utils\CoreUtils;
use Udoh\Emsa\Utils\DisplayUtils;

/**
 * Functionality for displaying EMSA List Results in paged fieldsets
 *
 * @author Josh Ridderhoff <jridderhoff@utah.gov>
 */
class EmsaListFieldset
{

    /** @var PDO */
    protected $dbConn;

    /** @var EmsaQueueList */
    protected $queueList;

    /** @var int */
    protected $queueId;

    /** @var int */
    protected $pageSize;

    /** @var int */
    protected $totalPages;

    /** @var int */
    protected $currentPage;

    /** @var int */
    protected $offset;

    /** @var int */
    protected $navSelectedPage;

    /** @var int */
    protected $navSubmenu;

    /** @var int */
    protected $navCat;

    /** @var int */
    protected $navSubcat;

    /** @var string */
    protected $navQueryString;

    /** @var int */
    protected $pagingNavSpread = 5;

    /**
     * Returns an instance of EmsaListFieldset
     * 
     * @param PDO $dbConn PDO connection to the EMSA database
     * @param AppClientList $appClientList List of configured applications for this installation.
     * @param int $queueId EMSA Queue ID
     * @param int $selectedPage Nav selected_page ID
     * @param int $submenu [Optional]<br>Nav submenu ID
     * @param int $cat [Optional]<br>Nav cat ID
     * @param int $subcat [Optional]<br>Nav subcat ID
     * @param int $pageSize Number of EMSA messages to display per-page
     */
    public function __construct(PDO $dbConn, AppClientList $appClientList, $queueId, $selectedPage, $submenu = null, $cat = null, $subcat = null, $pageSize = 25)
    {
        $this->dbConn = $dbConn;
        $this->queueList = new EmsaQueueList($this->dbConn, $appClientList);
        $this->queueId = intval($queueId);
        $this->pageSize = intval($pageSize);
        $this->navSelectedPage = intval($selectedPage);
        $this->navSubmenu = intval($submenu);
        $this->navCat = intval($cat);
        $this->navSubcat = intval($subcat);
        $this->navQueryString = EmsaUtils::queryStringBuilder($this->navSelectedPage, $this->navSubmenu, $this->navCat, $this->navSubcat, $this->queueId);
    }

    /**
     * Calculate pagination stats for the current set of results
     * 
     * @param int $rowCount
     */
    protected function calculatePagination($rowCount)
    {
        // find out total pages
        $this->totalPages = intval(ceil($rowCount / $this->pageSize));

        // get the current page from session
        $this->currentPage = intval($_SESSION[EXPORT_SERVERNAME]["emsa_params"][$_SESSION[EXPORT_SERVERNAME]["emsa_params"]["type"]]["current_page"]);

        // if current page is greater than total pages...
        if ($this->currentPage > $this->totalPages) {
            // set current page to last page
            $this->currentPage = intval($this->totalPages);
        }

        // if current page is less than first page...
        if ($this->currentPage < 1) {
            // set current page to first page
            $this->currentPage = 1;
        }

        // the offset of the list, based on current page 
        $this->offset = intval(($this->currentPage - 1) * $this->pageSize);
    }

    /**
     * Draw pagination elements under the list of EMSA message results
     *
     * @param string|null $listClass String to append to currentpage ID to differentiate when multiple lists present
     */
    protected function drawPagination(?string $listClass = null): void
    {
        if ($this->totalPages > 1) {
            // if not on page 1, show back links
            if ($this->currentPage != 1) {
                // show << link to go back to page 1
                echo '<a class="paging_link_first" title="First Page" href="' . $this->getNavQueryString() . '&currentpage=1">&lt;&lt;</a>';

                // get previous page num
                $previousPage = $this->currentPage - 1;

                // show < link to go back to 1 page
                echo '<a class="paging_link_previous" title="Previous Page" href="' . $this->getNavQueryString() . '&currentpage=' . intval($previousPage) . '">&lt;</a>';
            }

            // loop to show links to range of pages around current page
            for ($pageNumInRange = ($this->currentPage - $this->pagingNavSpread); $pageNumInRange < (($this->currentPage + $this->pagingNavSpread) + 1); $pageNumInRange++) {
                // if it's a valid page number...
                if (($pageNumInRange > 0) && ($pageNumInRange <= $this->totalPages)) {
                    // if we're on current page...
                    if ($pageNumInRange == $this->currentPage) {
                        // 'highlight' it but don't make a link
                        echo '<form style="display: inline; margin: 0;" method="GET">';
                        echo '<input type="hidden" name="selected_page" value="' . intval($this->navSelectedPage) . '">';
                        if (!EmsaUtils::emptyTrim($this->navSubmenu)) {
                            echo '<input type="hidden" name="submenu" value="' . intval($this->navSubmenu) . '">';
                        }
                        echo '<input type="hidden" name="type" value="' . intval($this->queueId) . '">';
                        echo '<label class="sr-only" for="currentpage_' . addslashes($listClass) . '">Go to page number</label>';
                        echo '<input type="text" name="currentpage" id="currentpage_' . addslashes($listClass) . '" style="font-family: \'Open Sans\' !important; font-weight: 600; background-color: lightcyan; text-align: center; width: 2em; padding: .4em;" class="ui-corner-all" value="' . intval($pageNumInRange) . '">';
                        echo '</form>';
                    } else {
                        // if not current page... make it a link
                        echo '<a class="paging_link" title="Page ' . intval($pageNumInRange) . '" href="' . $this->getNavQueryString() . '&currentpage=' . intval($pageNumInRange) . '">' . intval($pageNumInRange) . '</a>';
                    }
                }
            }

            // if not on last page, show forward and last page links
            if ($this->currentPage != $this->totalPages) {
                // get next page
                $nextPage = $this->currentPage + 1;

                // echo forward link for next page
                echo '<a class="paging_link_next" title="Next Page" href="' . $this->getNavQueryString() . '&currentpage=' . intval($nextPage) . '">&gt;</a>';

                // echo forward link for lastpage
                echo '<a class="paging_link_last" title="Last Page" href="' . $this->getNavQueryString() . '&currentpage=' . intval($this->totalPages) . '">&gt;&gt;</a>';
            }
        }
    }

    /**
     * Populate the rows within an EMSA List Fieldset with actual EMSA messages
     *
     * @param EmsaQueueListItemSet $queueListItemSet
     * @param string|null          $listClass String to append to currentpage ID to differentiate when multiple lists present
     */
    protected function fillFieldset(EmsaQueueListItemSet $queueListItemSet, ?string $listClass = null): void
    {
        /* @var $queueListItem EmsaQueueListItem */
        echo '<div class="emsa_results_container">
				<table class="emsa_results">
					<thead>
						<tr>
                            <td style="width: 1%;"></td>
							<th style="width: 24%;">Name</th>
							<th style="width: 9%;">D.O.B.</th>
							<th style="width: 36%;">Condition</th>
							<th style="width: 19%;">Reporter</th>';
        if ($this->queueId == ASSIGNED_STATUS) {
            echo '<th style="width: 11%;">Date Assigned</th>';
        } elseif ($this->queueId == GRAY_STATUS) {
            echo '<th style="width: 11%;">Date Graylisted</th>';
        } else {
            echo '<th style="width: 11%;">Date Reported</th>';
        }
        echo '</tr>
					</thead>
					<tbody>';

        if (count($queueListItemSet) < 1) {
            echo '<tr><td colspan="6"><em>No events found</em></td></tr>';
        } else {
            foreach ($queueListItemSet as $queueListItem) {
                if ($queueListItem->getTestResultClass() == 'positive') {
                    $listRowResultClass = ' emsa_list_positive';
                } elseif ($queueListItem->getTestResultClass() == 'negative') {
                    $listRowResultClass = ' emsa_list_negative';
                } elseif ($queueListItem->getTestResultClass() == 'susceptible') {
                    $listRowResultClass = ' emsa_list_susceptible';
                } elseif ($queueListItem->getTestResultClass() == 'resistant') {
                    $listRowResultClass = ' emsa_list_resistant';
                } elseif ($queueListItem->getTestResultClass() == 'intermediate') {
                    $listRowResultClass = ' emsa_list_intermediate';
                } else {
                    $listRowResultClass = ' emsa_list_other';
                }

                if ((($this->queueId == ENTRY_STATUS) || ($this->queueId == OOS_STATUS) || ($this->queueId == UNPROCESSED_STATUS) || ($this->queueId == LOCKED_STATUS) || ($this->queueId == SEMI_AUTO_STATUS) || ($this->queueId == QA_STATUS) || ($this->queueId == EXCEPTIONS_STATUS) || ($this->queueId == NEDSS_EXCEPTION_STATUS)) && EmsaUtils::isElrMessageOverdue($this->queueId, $queueListItem->getReportedDateTime(true, DateTime::RFC3339))) {
                    $listRowOverdueClass = ' emsa_overdue';
                } else {
                    $listRowOverdueClass = '';
                }

                $listRowClasses = htmlentities($listRowOverdueClass . $listRowResultClass, ENT_QUOTES, 'UTF-8');

                echo '<tr class="emsa_dup' . $listRowClasses . '" id="' . (int) $queueListItem->getId() . '">' . PHP_EOL;
                echo "<td style='background-color: " . CoreUtils::randomRGBHex($queueListItem->getOriginalMessageId()) . " !important;'> </td>\n";
                echo "<td>\n";
                echo '<button type="button" class="emsa_close" id="emsa_close_' . (int) $queueListItem->getId() . '" style="display: none; margin-right: 5px;">Close</button>' . PHP_EOL;
                echo DisplayUtils::xSafe($queueListItem->getFullName(), "UTF-8", false) . PHP_EOL;
                echo "</td>\n";
                echo '<td>' . DisplayUtils::xSafe($queueListItem->getDateOfBirth(true, "m/d/Y") ?? "--", "UTF-8", false) . "</td>\n";
                echo '<td>' . DisplayUtils::xSafe($queueListItem->getCondition(), "UTF-8", false) . "</td>\n";
                echo '<td>' . DisplayUtils::xSafe($queueListItem->getReportingInterfaceName(), "UTF-8", false) . "</td>\n";
                echo '<td style="white-space: nowrap;">' . DisplayUtils::xSafe($queueListItem->getDisplayDateTime(true, "m/d/Y (g:ia)"), "UTF-8", false) . "</td>\n";
                echo "</tr>\n";

                echo '<tr class="emsa_dupsearch" id="dupsearch_' . (int) $queueListItem->getId() . '">' . PHP_EOL;
                echo "<td style='background-color: " . CoreUtils::randomRGBHex($queueListItem->getOriginalMessageId()) . " !important;'> </td>\n";
                echo '<td colspan="5">' . PHP_EOL;
                echo '<div class="emsa_dupsearch_tabset" id="emsa_dupsearch_' . (int) $queueListItem->getId() . '_tabset"></div>' . PHP_EOL;
                echo "</td>\n";
                echo "</tr>\n";
            }
        }

        echo "</tbody>\n";
        echo "</table>\n";

        echo '<div class="emsa_paging">';
        if (count($queueListItemSet) > 0) {
            echo '<p><strong>Page ' . intval($this->currentPage) . ' of ' . intval($this->totalPages) . '</strong></p>';
            $this->drawPagination($listClass);
        } else {
            echo '<p><strong><em>No labs found</em></strong></p>';
        }
        echo '</div>';

        echo '</div>';
    }

    /**
     * Draw the HTML Fieldset element to display the current page of results
     *
     * @param string               $legendTitle   Title to display in the legend of the fieldset
     * @param int                  $rowCount      Total number of records returned
     * @param EmsaQueueListItemSet $queueListItemSet
     * @param string               $fieldsetClass [Optional]<br>Additional class names to apply to the fieldset element
     */
    protected function drawFieldset(string $legendTitle, int $rowCount, EmsaQueueListItemSet $queueListItemSet, ?string $fieldsetClass = null): void
    {
        echo '<fieldset class="emsa-list ui-widget ui-widget-content ui-corner-all ' . DisplayUtils::xSafe($fieldsetClass) . '">';
        echo '<legend class="emsa-list-legend ui-widget-content ui-corner-all">' . DisplayUtils::xSafe($legendTitle) . '&nbsp;&nbsp;&nbsp;[ ' . (int) $rowCount . ' ]</legend>';
        $this->fillFieldset($queueListItemSet, $fieldsetClass);
        echo '</fieldset>';
    }

    public function getImmediateList()
    {
        $rowCount = $this->queueList->getMessageCount($this->queueId, 0, 0, EmsaQueueList::RESTRICT_IMMEDIATE);
        $this->calculatePagination($rowCount);
        $queueListItemSet = $this->queueList->getEmsaQueueListItemSet($this->queueId, $this->offset, $this->pageSize, EmsaQueueList::RESTRICT_IMMEDIATE);
        $this->drawFieldset('Immediate', $rowCount, $queueListItemSet, 'emsa-list-immediate');

        if ($rowCount < 1) {
            // if no rows in 'Immediate' list, hide the entire fieldset for UI real estate usability
            echo '<script type="text/javascript">
						$(function() {
							$(".emsa-list-immediate").hide();
						});
					</script>';
        }
    }

    public function getNonImmediateList()
    {
        switch ($this->queueId) {
            case ENTRY_STATUS:
            case UNPROCESSED_STATUS:
            case LOCKED_STATUS:
            case EXCEPTIONS_STATUS:
            case NEDSS_EXCEPTION_STATUS:
            case SEMI_AUTO_STATUS:
            case QA_STATUS:
                $viewAll = EmsaQueueList::RESTRICT_NON_IMMEDIATE;
                break;
            default :
                $viewAll = EmsaQueueList::RESTRICT_SHOW_ALL;
                break;
        }

        $legendTitle = ($viewAll == EmsaQueueList::RESTRICT_SHOW_ALL) ? DisplayUtils::xSafe(EmsaUtils::getQueueName($this->queueId)) : 'Non-Immediate';

        $rowCount = $this->queueList->getMessageCount($this->queueId, 0, 0, $viewAll);
        $this->calculatePagination($rowCount);
        $queueListItemSet = $this->queueList->getEmsaQueueListItemSet($this->queueId, $this->offset, $this->pageSize, $viewAll);
        $this->drawFieldset($legendTitle, $rowCount, $queueListItemSet, 'emsa-list-nonimmediate');

        if (($viewAll != EmsaQueueList::RESTRICT_SHOW_ALL) && ($rowCount < 1)) {
            // if no rows in non-immediate list, hide the entire fieldset for UI real estate usability
            echo '<script type="text/javascript">
						$(function() {
							$(".emsa-list-nonimmediate").hide();
						});
					</script>';
        }
    }

    public function getIndividualList(int $systemMessageId): void
    {
        if (EmsaUtils::emptyTrim($systemMessageId) || ((int) $systemMessageId < 1)) {
            return;
        }

        $legendTitle = 'Message ID# ' . (int) $systemMessageId . ' [' . DisplayUtils::xSafe(EmsaUtils::getQueueName($this->queueId)) . ']';

        // hard-code pagination... only one result/page
        $rowCount = 1;
        $this->totalPages = 1;
        $this->currentPage = 1;
        $this->offset = 0;

        $queueListItemSet = $this->queueList->getEmsaQueueListItemSet($this->queueId, 0, $this->pageSize, EmsaQueueList::RESTRICT_SHOW_ALL, (int) $systemMessageId);
        $this->drawFieldset($legendTitle, $rowCount, $queueListItemSet, 'emsa-list-nonimmediate');
    }

    /**
     * Get the current query string for use in navigational elements
     * 
     * @return string
     */
    public function getNavQueryString()
    {
        return $this->navQueryString;
    }

}

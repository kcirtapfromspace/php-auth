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

use Udoh\Emsa\Client\AppClientList;
use Udoh\Emsa\Utils\ExceptionUtils;

/**
 * Container and utilities for processing matches from the Graylist Pool within the Graylist process
 *
 * @author Josh Ridderhoff <jridderhoff@utah.gov>
 */
class GraylistMatchSet
{

    /** @var GraylistMatch[] */
    protected $graylistMatches;

    /** @var AppClientList */
    protected $appClientList;

    /**
     * Create a new GraylistMatchSet object.
     *
     * @param AppClientList $appClientList List of configured applications for this installation
     */
    public function __construct(AppClientList $appClientList)
    {
        $this->graylistMatches = [];
        $this->appClientList = $appClientList;
    }

    /**
     * Indicates whether the specified EMSA message has already been identified as a Graylist Match
     * 
     * @param int $systemMessageId System Message ID of the matching Graylist Pool message
     * 
     * @return bool
     */
    private function idAlreadyMatched(int $systemMessageId): bool
    {
        if (!empty($this->graylistMatches)) {
            foreach ($this->graylistMatches as $graylistMatch) {
                if ($graylistMatch->getEmsaMessage()->getSystemMessageId() == $systemMessageId) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Indicates whether there are any 4- or 5-star matches for this Graylist Pool search.
     * 
     * @return bool
     */
    public function hasMatches(): bool
    {
        if (!empty($this->graylistMatches)) {
            return true;
        } else {
            return false;
        }
    }

    /**
     * Get all Graylist Matches as an array.
     * 
     * @return GraylistMatch[] Array of <b>\GraylistMatch</b> objects.
     */
    public function getGraylistMatches(): array
    {
        return $this->graylistMatches;
    }

    /**
     * Adds a match from the Graylist Pool to the current request's match set.
     *
     * @param PDO $dbConn          PDO connection to EMSA database
     * @param int $systemMessageId System Message ID of the matching Graylist Pool message
     *
     * @return bool
     */
    public function addMatch(PDO $dbConn, int $systemMessageId): bool
    {
        if ($this->idAlreadyMatched($systemMessageId)) {
            return false;
        }

        try {
            $this->graylistMatches[] = new GraylistMatch($dbConn, $this->appClientList, $systemMessageId);
            return true;
        } catch (Throwable $e) {
            ExceptionUtils::logException($e);
            return false;
        }
    }

    /**
     * Remove matches from the Match Set that do not meet Graylist Rules
     * 
     * @param PDO             $dbConn      PDO connection to EMSA database
     * @param GraylistRequest $grayRequest Current Graylist request
     */
    public function runGraylistRules(PDO $dbConn, GraylistRequest $grayRequest): void
    {
        // get graylist rules for this request's condition
        $rulesEngine = new GraylistRulesEngine($dbConn, $grayRequest);

        // for each matched message, run graylist rules; remove if no match
        if (!empty($this->graylistMatches)) {
            foreach ($this->graylistMatches as $graylistMatchKey => $graylistMatch) {
                if (!$rulesEngine->evaluateRules($graylistMatch->getEmsaMessage())) {
                    unset($this->graylistMatches[$graylistMatchKey]);
                }
            }
        }
    }

}

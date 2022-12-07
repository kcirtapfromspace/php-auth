<?php
namespace Udoh\Emsa\Rules;

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

use EmsaCompareNedssEvent;

/**
 * Container representing a matched EmsaCompareNedssEvent object and the Whitelist Rule used for evaluating it.
 *
 * @author  Josh Ridderhoff <jridderhoff@utah.gov>
 */
class WhitelistRuleTuple
{
    /** @var EmsaCompareNedssEvent */
    protected $nedssEvent;
    /** @var WhitelistRule */
    protected $whitelistRuleApplied;
    /** @var string */
    protected $conditionApplied;

    /**
     * Create a new WhitelistRuleTuple object.
     *
     * @param EmsaCompareNedssEvent $nedssEvent
     * @param WhitelistRule         $whitelistRuleApplied
     * @param string                $conditionApplied
     */
    public function __construct(EmsaCompareNedssEvent $nedssEvent, WhitelistRule $whitelistRuleApplied, $conditionApplied)
    {
        $this->nedssEvent = $nedssEvent;
        $this->whitelistRuleApplied = $whitelistRuleApplied;
        $this->conditionApplied = $conditionApplied;
    }

    /**
     * Gets the EmsaCompareNedssEvent object representing the matched record in the target application.
     *
     * @return EmsaCompareNedssEvent
     */
    public function getNedssEvent()
    {
        return $this->nedssEvent;
    }

    /**
     * Gets the Whitelist Rule used during evaluation for this record.
     *
     * @return WhitelistRule
     */
    public function getWhitelistRuleApplied()
    {
        return $this->whitelistRuleApplied;
    }

    /**
     * Gets the Condition Name used to determine which Whitelist Rule to run.
     *
     * @return string
     */
    public function getConditionApplied()
    {
        return $this->conditionApplied;
    }


}
<?php

namespace Udoh\Emsa\Rules;

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

use EmsaUtils;

/**
 * Contact Whitelist Rule container & constants
 *
 * @author Josh Ridderhoff <jridderhoff@utah.gov>
 */
class ContactWhitelistRule extends WhitelistRule
{

    /**
     * Create a new Contact Whitelist Rule object & decode the Contact Whitelist Rules
     *
     * @param string $whitelistRuleRaw Contact Whitelist Rule text for this lab's Master Condition
     * @param bool   $isOverride       [Optional; Default <b>FALSE</b>]  Indicates whether this Whitelist Rule has the
     *                                 "Priority Override" flag set
     * @param bool   $ignoreCaseStatus [Optional; Default <b>FALSE</b>]  Indicates whether State Case Status, e.g. "Not
     *                                 a Case", should be ignored when this Whitelist Rule is in use.
     */
    public function __construct(string $whitelistRuleRaw, ?bool $isOverride = false, ?bool $ignoreCaseStatus = false)
    {
        if ($isOverride === true) {
            $this->isOverride = true;
        } else {
            $this->isOverride = false;
        }

        if ($ignoreCaseStatus === true) {
            $this->ignoreCaseStatus = true;
        } else {
            $this->ignoreCaseStatus = false;
        }
        
        if (EmsaUtils::emptyTrim($whitelistRuleRaw)) {
            $this->ruleType = self::WHITELIST_RULETYPE_EXCEPTION;
            $this->ruleValue = null;
        } elseif (stripos($whitelistRuleRaw, 'diagnostic') !== false) {
            // deprecated for Contacts following the Always/Never New rename, but still here just in case
            $this->ruleType = self::WHITELIST_RULETYPE_ALWAYS_NEW_IF_DIAGNOSTIC;
            $this->ruleValue = null;
        } elseif (stripos($whitelistRuleRaw, 'never') !== false) {
            // used to be specified as "Always New" in the UI, changed to "Never Added to Contact" to reduce confusion
            // ...ironically
            $this->ruleType = self::WHITELIST_RULETYPE_ALWAYS_NEW;
            $this->ruleValue = null;
        } elseif (stripos($whitelistRuleRaw, 'always') !== false) {
            // used to be specified as "Never New" in the UI, changed to "Always Added to Contact" to reduce confusion
            $this->ruleType = self::WHITELIST_RULETYPE_NEVER_NEW;
            $this->ruleValue = null;
        } elseif (stripos($whitelistRuleRaw, 'std multi') !== false) {
            $this->ruleType = self::WHITELIST_RULETYPE_STD_MULTI;
            $this->ruleValue = null;
        } elseif (stripos($whitelistRuleRaw, 'tb multi') !== false) {
            $this->ruleType = self::WHITELIST_RULETYPE_TB_MULTI;
            $this->ruleValue = null;
        } else {
            $ruleTmpValue = trim($whitelistRuleRaw);
            $this->ruleType = self::WHITELIST_RULETYPE_TIME_ONSET;
            $this->ruleValue = $ruleTmpValue;
        }
    }

}

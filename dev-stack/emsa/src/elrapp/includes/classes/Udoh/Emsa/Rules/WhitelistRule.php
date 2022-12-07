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
 * Morbidity Whitelist Rule container & constants
 *
 * @author Josh Ridderhoff <jridderhoff@utah.gov>
 */
class WhitelistRule
{

    /** @var int */
    protected $ruleType;

    /** @var string */
    protected $ruleValue;
    
    /** @var bool Indicates whether this Whitelist Rule should be used, overriding other Whitelist Rules identified. */
    protected $isOverride;

    /** @var bool Indicates whether State Case Status, e.g. "Not a Case", should be ignored when this Whitelist Rule is in use. */
    protected $ignoreCaseStatus;

    const WHITELIST_RULETYPE_EXCEPTION = 0;
    const WHITELIST_RULETYPE_NEWEST = 1;
    const WHITELIST_RULETYPE_NEVER_NEW = 2;
    const WHITELIST_RULETYPE_STD_MULTI = 3;
    const WHITELIST_RULETYPE_TIME_TREATMENT = 4;
    const WHITELIST_RULETYPE_TIME_LAST_POSITIVE = 5;
    const WHITELIST_RULETYPE_TIME_ONSET = 6;
    const WHITELIST_RULETYPE_ALWAYS_NEW = 7;
    const WHITELIST_RULETYPE_ALWAYS_NEW_IF_DIAGNOSTIC = 8;
    const WHITELIST_RULETYPE_TB_MULTI = 9;
    const WHITELIST_RULETYPE_TIME_ONSET_BEFOREAFTER = 10;

    /**
     * Create a new Morbidity Whitelist Rule object & decode the Morbidity Whitelist Rules
     *
     * @param string $whitelistRuleRaw Morbidity Whitelist Rule text for this lab's Master Condition
     * @param bool   $isOverride       [Optional; Default <b>FALSE</b>]  Indicates whether this Whitelist Rule has the
     *                                 "Priority Override" flag set
     * @param bool   $ignoreCaseStatus [Optional; Default <b>FALSE</b>]  Indicates whether State Case Status, e.g. "Not
     *                                 a Case", should be ignored when this Whitelist Rule is in use.
     */
    public function __construct(string $whitelistRuleRaw, ?bool $isOverride = false, ?bool $ignoreCaseStatus = false)
    {
        /*
         * decode morbidity whitelist rule...
         * valid whitelist rule types:
         *   + time_onset (inc. specimen collection date vs. existing event onset date)
         *   + time_treatment (inc. specimen collection date vs. existing event last treatment date)
         *   + time_last_positive (inc. specimen collection date vs. existing event last positive lab received date)
         *   + never_new (always append inc. lab to existing event if exactly one matching event is found)
         *   + exception (require epidemiologist intervention to decide add/update)
         *   + std_multi (multi-step timeframe-based rule for STDs)
         *   + newest (get back only the newest event, if any found)
         */
        
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
        } elseif (stripos($whitelistRuleRaw, 'exception') !== false) {
            $this->ruleType = self::WHITELIST_RULETYPE_EXCEPTION;
            $this->ruleValue = null;
        } elseif (stripos($whitelistRuleRaw, 'newest') !== false) {
            $this->ruleType = self::WHITELIST_RULETYPE_NEWEST;
            $this->ruleValue = null;
        } elseif (stripos($whitelistRuleRaw, 'if case') !== false) {
            // temporarily treating 'logic' options as exception-type rules
            $this->ruleType = self::WHITELIST_RULETYPE_EXCEPTION;
            $this->ruleValue = null;
        } elseif (stripos($whitelistRuleRaw, 'never') !== false) {
            $this->ruleType = self::WHITELIST_RULETYPE_NEVER_NEW;
            $this->ruleValue = null;
        } elseif (stripos($whitelistRuleRaw, 'std multi') !== false) {
            $this->ruleType = self::WHITELIST_RULETYPE_STD_MULTI;
            $this->ruleValue = null;
        } elseif (stripos($whitelistRuleRaw, 'tb multi') !== false) {
            $this->ruleType = self::WHITELIST_RULETYPE_TB_MULTI;
            $this->ruleValue = null;
        } elseif (stripos($whitelistRuleRaw, 'treatment') !== false) {
            $ruleTmpValue = trim(str_ireplace('after date of treatment', '', str_ireplace('after last treatment date', '', $whitelistRuleRaw)));
            $this->ruleType = self::WHITELIST_RULETYPE_TIME_TREATMENT;
            $this->ruleValue = $ruleTmpValue;
        } elseif (stripos($whitelistRuleRaw, 'positive') !== false) {
            $ruleTmpValue = trim(str_ireplace('after date of last positive lab', '', $whitelistRuleRaw));
            $this->ruleType = self::WHITELIST_RULETYPE_TIME_LAST_POSITIVE;
            $this->ruleValue = $ruleTmpValue;
        } elseif (stripos($whitelistRuleRaw, ';') !== false) {
            $ruleTmpValue = trim($whitelistRuleRaw);
            $this->ruleType = self::WHITELIST_RULETYPE_TIME_ONSET_BEFOREAFTER;
            $this->ruleValue = $ruleTmpValue;
        } else {
            $ruleTmpValue = trim($whitelistRuleRaw);
            $this->ruleType = self::WHITELIST_RULETYPE_TIME_ONSET;
            $this->ruleValue = $ruleTmpValue;
        }
    }

    /**
     * Indicates this Whitelist Rule's type (one of <b>WhitelistRule::WHITELIST_RULETYPE_*</b>).
     * 
     * @return int
     */
    public function getRuleType(): int
    {
        return $this->ruleType;
    }

    /**
     * Gets this Whitelist Rule's value.
     * 
     * @return string
     */
    public function getRuleValue(): ?string
    {
        return $this->ruleValue;
    }
    
    /**
     * Indicates whether this Whitelist Rule has the "Priority Override" flag set.
     * 
     * @return bool
     */
    public function getIsOverride(): bool
    {
        return $this->isOverride;
    }

    /**
     * Indicates whether State Case Status, e.g. "Not a Case", should be ignored when this Whitelist Rule is in use.
     *
     * @return bool
     */
    public function getIgnoreCaseStatus(): bool
    {
        return $this->ignoreCaseStatus;
    }

}

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

use DateInterval;
use EmsaCompareNedssEventSet;
use EmsaMessage;
use Udoh\Emsa\Constants\AppRecordType;
use Udoh\Emsa\Utils\ExceptionUtils;

/**
 * Utilities for evaluating and returning results of Whitelist Rules against a set of NEDSS events.
 *
 * @author Josh Ridderhoff <jridderhoff@utah.gov>
 */
class WhitelistRuleEvaluator
{

    /** @var WhitelistRuleTupleSet */
    protected $morbidityWhitelistEvents;

    /** @var WhitelistRuleTupleSet */
    protected $contactWhitelistEvents;

    /** @var WhitelistRuleTupleSet */
    protected $morbidityExceptionEvents;

    /** @var WhitelistRuleTupleSet */
    protected $contactExceptionEvents;

    /**
     * Create a new WhitelistRuleEvaluator object
     */
    public function __construct()
    {
        $this->morbidityWhitelistEvents = new WhitelistRuleTupleSet();
        $this->morbidityExceptionEvents = new WhitelistRuleTupleSet();
        $this->contactWhitelistEvents = new WhitelistRuleTupleSet();
        $this->contactExceptionEvents = new WhitelistRuleTupleSet();
    }

    /**
     * Run Whitelist Rules against matched NEDSS events
     *
     * @param EmsaCompareNedssEventSet $candidateEventSet Set of matched NEDSS events.
     * @param EmsaMessage              $emsaMessage       Message being processed for assignment.
     *
     * @return WhitelistRuleEvaluator
     */
    public function evaluateWhitelistRules(EmsaCompareNedssEventSet $candidateEventSet, EmsaMessage $emsaMessage)
    {
        $referenceDate = $emsaMessage->getReferenceDate();
        
        foreach ($candidateEventSet->getNedssEvents() as $nedssEvent) {
            // evaluate whitelist rules for each existing matched event that was found
            $isWhitelisted = false;
            
            // get whitelist rules
            // 
            // if initial condition's whitelist override flag is true and target condition's isn't, use initial condition's rules
            // otherwise, use rules based on condition of target event
            if ($emsaMessage->getWhitelistRules()->getMorbidityWhitelistRule()->getIsOverride() && !$nedssEvent->getWhitelistRules()->getMorbidityWhitelistRule()->getIsOverride()) {
                $morbidityWhitelistRule = $emsaMessage->getWhitelistRules()->getMorbidityWhitelistRule();
                $whitelistCondition = $emsaMessage->masterCondition;
            } else {
                $morbidityWhitelistRule = $nedssEvent->getWhitelistRules()->getMorbidityWhitelistRule();
                $whitelistCondition = $nedssEvent->getDiseaseName();
            }
            
            if ($emsaMessage->getWhitelistRules()->getContactWhitelistRule()->getIsOverride() && !$nedssEvent->getWhitelistRules()->getContactWhitelistRule()->getIsOverride()) {
                $contactWhitelistRule = $emsaMessage->getWhitelistRules()->getContactWhitelistRule();
            } else {
                $contactWhitelistRule = $nedssEvent->getWhitelistRules()->getContactWhitelistRule();
            }

            if (is_null($nedssEvent->getDateDeleted()) && (stripos($nedssEvent->getStateCaseStatus(), 'discarded') === false)) {
                // skip deleted & discarded events
                if ($nedssEvent->getEventType() === AppRecordType::MORBIDITY_EVENT) {
                    // morbidity event, use $morbidityWhitelistRule
                    if (is_null($referenceDate)) {
                        $isWhitelisted = true;
                        $this->morbidityExceptionEvents->add(new WhitelistRuleTuple($nedssEvent, $morbidityWhitelistRule, $whitelistCondition));  // no lab collection date; flag as exception
                    } else {
                        if ($morbidityWhitelistRule->getRuleType() === WhitelistRule::WHITELIST_RULETYPE_NEVER_NEW) {
                            $isWhitelisted = true;
                        } elseif ($morbidityWhitelistRule->getRuleType() === WhitelistRule::WHITELIST_RULETYPE_NEWEST) {
                            $isWhitelisted = true;
                        } elseif ($morbidityWhitelistRule->getRuleType() === WhitelistRule::WHITELIST_RULETYPE_EXCEPTION) {
                            $isWhitelisted = true;
                            $this->morbidityExceptionEvents->add(new WhitelistRuleTuple($nedssEvent, $morbidityWhitelistRule, $whitelistCondition));
                        } elseif ($morbidityWhitelistRule->getRuleType() === WhitelistRule::WHITELIST_RULETYPE_STD_MULTI) {
                            // multi-step whitelist rule for STDs
                            if (is_null($nedssEvent->getLastTreatmentDate())) {
                                // check against event onset date
                                $morbidityWhitelistOnsetStartDate = clone $nedssEvent->getEventDate();
                                $morbidityWhitelistOnsetStartDate->add(DateInterval::createFromDateString('+90 days'));
                                if ($morbidityWhitelistOnsetStartDate >= $referenceDate) {
                                    // collected within 90 days of onset, update CMR
                                    $isWhitelisted = true;
                                }
                            } else {
                                // check against last treatment date
                                $morbidityWhitelistTreatStartDate = clone $nedssEvent->getLastTreatmentDate();
                                $morbidityWhitelistTreatStartDate->add(DateInterval::createFromDateString('+30 days'));
                                if ($morbidityWhitelistTreatStartDate >= $referenceDate) {
                                    // collected within 30 days of treatment
                                    $isWhitelisted = true;
                                }
                            }
                        } elseif ($morbidityWhitelistRule->getRuleType() === WhitelistRule::WHITELIST_RULETYPE_TB_MULTI) {
                            // multi-step whitelist rule for TB family
                            if (is_null($nedssEvent->getLastTreatmentDate())) {
                                // check against event onset date
                                $morbidityWhitelistOnsetStartDate = clone $nedssEvent->getEventDate();
                                $morbidityWhitelistOnsetStartDate->add(DateInterval::createFromDateString('+1 year'));
                                if ($morbidityWhitelistOnsetStartDate >= $referenceDate) {
                                    // collected within 1 year of onset, update CMR
                                    $isWhitelisted = true;
                                }
                            } else {
                                // check against last treatment date
                                $morbidityWhitelistTreatStartDate = clone $nedssEvent->getLastTreatmentDate();
                                $morbidityWhitelistTreatStartDate->add(DateInterval::createFromDateString('+1 year'));
                                if ($morbidityWhitelistTreatStartDate >= $referenceDate) {
                                    // collected within 1 year of start of most-recent treatment
                                    $isWhitelisted = true;
                                }
                            }
                        } elseif ($morbidityWhitelistRule->getRuleType() === WhitelistRule::WHITELIST_RULETYPE_TIME_ONSET) {
                            $morbidityWhitelistStartStr = '+' . $morbidityWhitelistRule->getRuleValue();
                            // first, check whitelist rules against event date...
                            if (is_null($nedssEvent->getEventDate())) {
                                $isWhitelisted = false;
                            } else {
                                $morbidityWhitelistStartDate = clone $nedssEvent->getEventDate();
                                $morbidityWhitelistStartDate->add(DateInterval::createFromDateString($morbidityWhitelistStartStr));
                                if ($morbidityWhitelistStartDate > $referenceDate) {
                                    $isWhitelisted = true;
                                }
                            }
                        } elseif ($morbidityWhitelistRule->getRuleType() === WhitelistRule::WHITELIST_RULETYPE_TIME_ONSET_BEFOREAFTER) {
                            // first, check whitelist rules against event date...
                            if (is_null($nedssEvent->getEventDate())) {
                                $isWhitelisted = false;
                            } else {
                                $rawWhitelistRuleValue = $morbidityWhitelistRule->getRuleValue();
                                $ruleValueParts = explode(";", $rawWhitelistRuleValue);

                                foreach ($ruleValueParts as $ruleValuePart) {
                                    if (stripos($ruleValuePart, "before") !== false) {
                                        $ruleValueBefore = trim(str_ireplace('before event date', '', $ruleValuePart));
                                    } elseif (stripos($ruleValuePart, "after")) {
                                        $ruleValueAfter = trim(str_ireplace('after event date', '', $ruleValuePart));
                                    }
                                }

                                if (!empty($ruleValueBefore) && !empty($ruleValueAfter)) {
                                    $ruleStrBefore = $ruleValueBefore;
                                    $ruleStrAfter = $ruleValueAfter;

                                    $eventDateLBound = clone $nedssEvent->getEventDate();
                                    $eventDateLBound->sub(DateInterval::createFromDateString($ruleStrBefore));

                                    $eventDateUBound = clone $nedssEvent->getEventDate();
                                    $eventDateUBound->add(DateInterval::createFromDateString($ruleStrAfter));

                                    if (($referenceDate > $eventDateLBound) && ($referenceDate < $eventDateUBound)) {
                                        $isWhitelisted = true;
                                    }
                                } else {
                                    $isWhitelisted = true;
                                    $this->morbidityExceptionEvents->add(new WhitelistRuleTuple($nedssEvent, $morbidityWhitelistRule, $whitelistCondition));
                                }
                            }
                        } elseif ($morbidityWhitelistRule->getRuleType() === WhitelistRule::WHITELIST_RULETYPE_TIME_TREATMENT) {
                            $morbidityWhitelistStartStr = '+' . $morbidityWhitelistRule->getRuleValue();
                            if (is_null($nedssEvent->getLastTreatmentDate())) {
                                // if no treatment date, flag as an exception
                                $isWhitelisted = true;
                                $this->morbidityExceptionEvents->add(new WhitelistRuleTuple($nedssEvent, $morbidityWhitelistRule, $whitelistCondition));
                            } else {
                                $morbidityWhitelistStartDate = clone $nedssEvent->getLastTreatmentDate();
                                $morbidityWhitelistStartDate->add(DateInterval::createFromDateString($morbidityWhitelistStartStr));
                                if ($morbidityWhitelistStartDate > $referenceDate) {
                                    $isWhitelisted = true;
                                }
                            }
                        } elseif ($morbidityWhitelistRule->getRuleType() === WhitelistRule::WHITELIST_RULETYPE_TIME_LAST_POSITIVE) {
                            $morbidityWhitelistStartStr = '+' . $morbidityWhitelistRule->getRuleValue();
                            if (!is_null($nedssEvent->getLastPositiveLabCollectionDate())) {
                                // per https://pm.health.utah.gov/project/admin-dcp-ip-emsaother/us/917, if missing positive lab collection date,
                                // fall back to positive lab test date, then overall lab collection date, then overall lab test date, and finally event date
                                // no longer flagging as exception.
                                //$isWhitelisted = true;
                                //$this->morbidityExceptionEvents->add(new WhitelistRuleTuple($nedssEvent, $morbidityWhitelistRule, $whitelistCondition));
                                $morbidityWhitelistStartDate = clone $nedssEvent->getLastPositiveLabCollectionDate();
                            } elseif (!is_null($nedssEvent->getLastPositiveLabTestDate())) {
                                $morbidityWhitelistStartDate = clone $nedssEvent->getLastPositiveLabTestDate();
                            } elseif (!is_null($nedssEvent->getLastLabCollectionDate())) {
                                $morbidityWhitelistStartDate = clone $nedssEvent->getLastLabCollectionDate();
                            } elseif (!is_null($nedssEvent->getLastLabTestDate())) {
                                $morbidityWhitelistStartDate = clone $nedssEvent->getLastLabTestDate();
                            } else {
                                $morbidityWhitelistStartDate = clone $nedssEvent->getEventDate();
                            }

                            $morbidityWhitelistStartDate->add(DateInterval::createFromDateString($morbidityWhitelistStartStr));
                            if ($morbidityWhitelistStartDate > $referenceDate) {
                                $isWhitelisted = true;
                            }
                        }
                    }

                    if ($isWhitelisted) {
                        // morbidity whitelist rule matches
                        $this->morbidityWhitelistEvents->add(new WhitelistRuleTuple($nedssEvent, $morbidityWhitelistRule, $whitelistCondition));
                    }
                } else {
                    // contact event, use $contactWhitelistRule
                    if (is_null($referenceDate)) {
                        $isWhitelisted = true;
                        $this->contactExceptionEvents->add(new WhitelistRuleTuple($nedssEvent, $contactWhitelistRule, $whitelistCondition));  // no lab collection date; flag as exception
                    } else {
                        if ($contactWhitelistRule->getRuleType() === WhitelistRule::WHITELIST_RULETYPE_EXCEPTION) {
                            $isWhitelisted = true;
                            $this->contactExceptionEvents->add(new WhitelistRuleTuple($nedssEvent, $contactWhitelistRule, $whitelistCondition));
                        } elseif ($contactWhitelistRule->getRuleType() === WhitelistRule::WHITELIST_RULETYPE_ALWAYS_NEW_IF_DIAGNOSTIC) {
                            $isWhitelisted = false; // allowNewCmr should check for 'if diagnostic' later, assume new CMR here though
                        } elseif ($contactWhitelistRule->getRuleType() === WhitelistRule::WHITELIST_RULETYPE_ALWAYS_NEW) {
                            $isWhitelisted = false;
                        } elseif ($contactWhitelistRule->getRuleType() === WhitelistRule::WHITELIST_RULETYPE_NEVER_NEW) {
                            $isWhitelisted = true;
                        } elseif ($contactWhitelistRule->getRuleType() === WhitelistRule::WHITELIST_RULETYPE_STD_MULTI) {
                            // multi-step whitelist rule for STDs
                            if (is_null($nedssEvent->getLastTreatmentDate())) {
                                // check against event onset date
                                $contactWhitelistOnsetStartDate = clone $nedssEvent->getEventDate();
                                $contactWhitelistOnsetStartDate->add(DateInterval::createFromDateString('+90 days'));
                                if ($contactWhitelistOnsetStartDate >= $referenceDate) {
                                    // collected within 90 days of onset
                                    $isWhitelisted = true;
                                }
                            } else {
                                // check against last treatment date
                                $contactWhitelistTreatStartDate = clone $nedssEvent->getLastTreatmentDate();
                                $contactWhitelistTreatStartDate->add(DateInterval::createFromDateString('+30 days'));
                                if ($contactWhitelistTreatStartDate >= $referenceDate) {
                                    // collected within 30 days of treatment
                                    $isWhitelisted = true;
                                }
                            }
                        } elseif ($contactWhitelistRule->getRuleType() === WhitelistRule::WHITELIST_RULETYPE_TB_MULTI) {
                            // multi-step whitelist rule for TB family
                            if (is_null($nedssEvent->getLastTreatmentDate())) {
                                // check against event onset date
                                $contactWhitelistOnsetStartDate = clone $nedssEvent->getEventDate();
                                $contactWhitelistOnsetStartDate->add(DateInterval::createFromDateString('+1 year'));
                                if ($contactWhitelistOnsetStartDate >= $referenceDate) {
                                    // collected within 1 year of onset
                                    $isWhitelisted = true;
                                }
                            } else {
                                // check against last treatment date
                                $contactWhitelistTreatStartDate = clone $nedssEvent->getLastTreatmentDate();
                                $contactWhitelistTreatStartDate->add(DateInterval::createFromDateString('+1 year'));
                                if ($contactWhitelistTreatStartDate >= $referenceDate) {
                                    // collected within 1 year of start of most-recent treatment
                                    $isWhitelisted = true;
                                }
                            }
                        } elseif ($contactWhitelistRule->getRuleType() === WhitelistRule::WHITELIST_RULETYPE_TIME_ONSET) {
                            $contactWhitelistStartStr = '+' . $contactWhitelistRule->getRuleValue();
                            $contactWhitelistStartDate = clone $nedssEvent->getEventDate();
                            $contactWhitelistStartDate->add(DateInterval::createFromDateString($contactWhitelistStartStr));
                            if ($contactWhitelistStartDate > $referenceDate) {
                                $isWhitelisted = true;
                            }
                        } elseif ($contactWhitelistRule->getRuleType() === WhitelistRule::WHITELIST_RULETYPE_TIME_ONSET_BEFOREAFTER) {
                            // first, check whitelist rules against event date...
                            if (is_null($nedssEvent->getEventDate())) {
                                $isWhitelisted = false;
                            } else {
                                $rawWhitelistRuleValue = $contactWhitelistRule->getRuleValue();
                                $ruleValueParts = explode(";", $rawWhitelistRuleValue);

                                foreach ($ruleValueParts as $ruleValuePart) {
                                    if (stripos($ruleValuePart, "before") !== false) {
                                        $ruleValueBefore = trim(str_ireplace('before event date', '', $ruleValuePart));
                                    } elseif (stripos($ruleValuePart, "after")) {
                                        $ruleValueAfter = trim(str_ireplace('after event date', '', $ruleValuePart));
                                    }
                                }

                                if (!empty($ruleValueBefore) && !empty($ruleValueAfter)) {
                                    $ruleStrBefore = $ruleValueBefore;
                                    $ruleStrAfter = $ruleValueAfter;

                                    $eventDateLBound = clone $nedssEvent->getEventDate();
                                    $eventDateLBound->sub(DateInterval::createFromDateString($ruleStrBefore));

                                    $eventDateUBound = clone $nedssEvent->getEventDate();
                                    $eventDateUBound->add(DateInterval::createFromDateString($ruleStrAfter));

                                    if (($referenceDate > $eventDateLBound) && ($referenceDate < $eventDateUBound)) {
                                        $isWhitelisted = true;
                                    }
                                } else {
                                    $isWhitelisted = true;
                                    $this->contactExceptionEvents->add(new WhitelistRuleTuple($nedssEvent, $contactWhitelistRule, $whitelistCondition));
                                }
                            }
                        }
                    }
                    if ($isWhitelisted) {
                        // contact whitelist rule matches
                        $this->contactWhitelistEvents->add(new WhitelistRuleTuple($nedssEvent, $contactWhitelistRule, $whitelistCondition));
                    }
                }
            }
        }

        if ((count($this->morbidityWhitelistEvents) > 1) && ($morbidityWhitelistRule->getRuleType() === WhitelistRule::WHITELIST_RULETYPE_TB_MULTI)) {
            // for 'TB MultiStep' whitelist rules, if more than one CMR, trim results to newest event
            $this->morbidityWhitelistEvents->sortByEventDateNewestFirst();
            $cropEventId = $this->morbidityWhitelistEvents->current()->getNedssEvent()->getEventId();
            $this->morbidityWhitelistEvents->cropTo($cropEventId);  // remove all of the items from morbidityWhitelistEvents except the one we want
        }

        if ((count($this->morbidityWhitelistEvents) > 1) && ($morbidityWhitelistRule->getRuleType() === WhitelistRule::WHITELIST_RULETYPE_NEWEST)) {
            // for 'Newest' whitelist rules, if more than one CMR, trim results to newest event
            $this->morbidityWhitelistEvents->sortByEventDateNewestFirst();
            $cropEventId = $this->morbidityWhitelistEvents->current()->getNedssEvent()->getEventId();
            $this->morbidityWhitelistEvents->cropTo($cropEventId);  // remove all of the items from morbidityWhitelistEvents except the one we want
        }

        if ((count($this->contactWhitelistEvents) > 1) && !$emsaMessage->getAllowOneToMany()) {
            // more than one Contact matched whitelist rules and not in One-to-Many mode, get newest Contact
            $this->contactWhitelistEvents->sortByEventDateNewestFirst();
            $cropEventId = $this->contactWhitelistEvents->current()->getNedssEvent()->getEventId();
            $this->contactWhitelistEvents->cropTo($cropEventId);  // remove all of the items from contactWhitelistEvents except the one we want
        }

        if (count($this->morbidityWhitelistEvents) > 1) {
            // if more than one whitelist event, loop through list & prune out any "not a case" events,
            // unless all of the events are marked "not a case"
            $notacasePrune = array(
                'case' => array(),
                'notacase' => array()
            );

            foreach ($this->morbidityWhitelistEvents as $morbidityPruneTuple) {
                // If whitelist rule applied for this event has the "Ignore State Case Status" flagged, treat it as a normal case, regardless;
                // otherwise, if State Case Status for the matched event is "Not a Case", count it as a "Not a Case" match.
                //
                // Note that because whitelist rules are set based on the condition of the event matched, not the incoming ELR message,
                // this may result in situations where some cases marked "Not a Case" will be included in the whitelist match set, while
                // others are removed (assuming that some matched conditions have the "Ignore State Case Status" flag set vs others that don't).
                if (!$morbidityPruneTuple->getWhitelistRuleApplied()->getIgnoreCaseStatus() && (stripos($morbidityPruneTuple->getNedssEvent()->getStateCaseStatus(), 'not a case') !== false)) {
                    $notacasePrune['notacase'][] = $morbidityPruneTuple->getNedssEvent()->getEventId();
                } else {
                    $notacasePrune['case'][] = $morbidityPruneTuple->getNedssEvent()->getEventId();
                }
            }

            if ((count($notacasePrune['notacase']) > 0) && (count($notacasePrune['notacase']) < count($this->morbidityWhitelistEvents))) {
                foreach ($notacasePrune['notacase'] as $notacasePruneEventId) {
                    $this->morbidityWhitelistEvents->remove($notacasePruneEventId);
                }
            }
        }

        if ((count($this->morbidityWhitelistEvents) > 1) && ($this->morbidityWhitelistEvents->hasOnlyBeforeAfterWhitelistRules()) && (count($this->morbidityWhitelistEvents->getConditionsMatched()) === 1)) {
            // for 'Before/After Event Date' whitelist rules, if more than one CMR that all use 'Before/After' rules
            // and are appropriately whitelisted apart from each other, trim results to earliest event

            $this->morbidityWhitelistEvents->sortByEventDateOldestFirst();
            $cropEventId = $this->morbidityWhitelistEvents->current()->getNedssEvent()->getEventId();

            $lastEventDate = null;
            $whitelistSeparationStr = null;
            $validWhitelistSeparation = true;

            foreach ($this->morbidityWhitelistEvents as $morbidityPruneTuple) {
                if (empty($lastEventDate)) {
                    $lastEventDate = clone $morbidityPruneTuple->getNedssEvent()->getEventDate();
                    $rawWhitelistSeparationStr = $morbidityPruneTuple->getWhitelistRuleApplied()->getRuleValue();
                    $whitelistSeparationStrParts = explode(";", $rawWhitelistSeparationStr);

                    foreach ($whitelistSeparationStrParts as $whitelistSeparationStrPart) {
                        if (stripos($whitelistSeparationStrPart, "after")) {
                            $whitelistSeparationStr = trim(str_ireplace('after event date', '', $whitelistSeparationStrPart));
                        }
                    }
                } else {
                    $currentEventDate = clone $morbidityPruneTuple->getNedssEvent()->getEventDate();
                    $currentEventDate->sub(DateInterval::createFromDateString($whitelistSeparationStr));

                    if ($currentEventDate < $lastEventDate) {
                        $validWhitelistSeparation = $validWhitelistSeparation && false;
                    }

                    $lastEventDate = clone $morbidityPruneTuple->getNedssEvent()->getEventDate();
                }
            }

            if ($validWhitelistSeparation) {
                // remove all of the items from morbidityWhitelistEvents except the one we want
                $this->morbidityWhitelistEvents->cropTo($cropEventId);
            }
        }

        return $this;
    }

    /**
     * @return WhitelistRuleTupleSet
     */
    public function getMorbidityWhitelistEvents()
    {
        return $this->morbidityWhitelistEvents;
    }

    /**
     * @return WhitelistRuleTupleSet
     */
    public function getContactWhitelistEvents()
    {
        return $this->contactWhitelistEvents;
    }

    /**
     * @return WhitelistRuleTupleSet
     */
    public function getMorbidityExceptionEvents()
    {
        return $this->morbidityExceptionEvents;
    }

    /**
     * @return WhitelistRuleTupleSet
     */
    public function getContactExceptionEvents()
    {
        return $this->contactExceptionEvents;
    }

}

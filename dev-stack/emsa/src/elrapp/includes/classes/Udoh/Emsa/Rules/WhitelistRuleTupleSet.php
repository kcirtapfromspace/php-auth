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

use Countable;
use Iterator;

/**
 * Collection of WhitelistRuleTuple objects.
 *
 * @author  Josh Ridderhoff <jridderhoff@utah.gov>
 */
class WhitelistRuleTupleSet implements Iterator, Countable
{
    /** @var WhitelistRuleTuple[] */
    private $whitelistRuleTuples = [];

    public function count()
    {
        return count($this->whitelistRuleTuples);
    }

    /**
     * @return WhitelistRuleTuple
     */
    public function current()
    {
        return current($this->whitelistRuleTuples);
    }

    public function next()
    {
        next($this->whitelistRuleTuples);
    }

    public function key()
    {
        return key($this->whitelistRuleTuples);
    }

    public function valid()
    {
        return !is_null(key($this->whitelistRuleTuples));
    }

    public function rewind()
    {
        reset($this->whitelistRuleTuples);
    }


    /**
     * Add a WhitelistRuleTuple to the collection.
     *
     * @param WhitelistRuleTuple $whitelistRuleTuple
     *
     * @return WhitelistRuleTupleSet
     */
    public function add(WhitelistRuleTuple $whitelistRuleTuple)
    {
        $eventId = $whitelistRuleTuple->getNedssEvent()->getEventId();
        $this->whitelistRuleTuples[$eventId] = $whitelistRuleTuple;
        return $this;
    }

    /**
     * Merge one or more WhitelistRuleTupleSet objects into a single collection.
     *
     * @param WhitelistRuleTupleSet ...$tupleSets
     *
     * @return WhitelistRuleTupleSet
     */
    public function merge(WhitelistRuleTupleSet ...$tupleSets)
    {
        if (!empty($tupleSets)) {
            foreach ($tupleSets as $tupleSet) {
                foreach ($tupleSet as $tuple) {
                    $eventId = $tuple->getNedssEvent()->getEventId();

                    if (!array_key_exists($eventId, $this->whitelistRuleTuples)) {
                        $this->whitelistRuleTuples[$eventId] = $tuple;
                    }
                }

                // ensure pointer moved back to the beginning after we've iterated over it
                $tupleSet->rewind();
            }
        }

        $this->rewind();
        return $this;
    }

    /**
     * Get the set of WhitelistRuleTuples.
     *
     * @return WhitelistRuleTuple[] Array of WhitelistRuleTuple objects
     */
    public function getAll()
    {
        return $this->whitelistRuleTuples;
    }

    /**
     * Get an individual WhitelistRuleTuple specified by event ID.
     *
     * @param int $eventId Event ID of the targeted NEDSS event
     *
     * @return null|WhitelistRuleTuple
     */
    public function get($eventId)
    {
        if (!empty($this->whitelistRuleTuples) && key_exists($eventId, $this->whitelistRuleTuples)) {
            return $this->whitelistRuleTuples[$eventId];
        } else {
            return null;
        }
    }

    /**
     * Remove an individual WhitelistRuleTuple specified by event ID.
     *
     * @param int $eventId Event ID of the targeted NEDSS event
     *
     * @return bool
     */
    public function remove($eventId)
    {
        if (!empty($this->whitelistRuleTuples) && key_exists($eventId, $this->whitelistRuleTuples)) {
            unset($this->whitelistRuleTuples[$eventId]);
            return true;
        } else {
            return false;
        }
    }

    /**
     * Remove all WhitelistRuleTuples except the one specified by event ID.
     *
     * @param int $eventId Event ID of the targeted NEDSS event to keep
     *
     * @return bool
     */
    public function cropTo($eventId)
    {
        if (!empty($this->whitelistRuleTuples) && key_exists($eventId, $this->whitelistRuleTuples)) {
            $filterTuples = array((string) $eventId);
            $tempTuples = array_intersect_key($this->whitelistRuleTuples, array_flip($filterTuples));
            $this->whitelistRuleTuples = $tempTuples;
            return true;
        } else {
            return false;
        }
    }

    /**
     * Sorts the WhitelistRuleTuples in this Set by the underlying Event Date, in order from newest events to oldest.
     *
     * @return bool
     */
    public function sortByEventDateNewestFirst()
    {
        $this->rewind();
        return uasort($this->whitelistRuleTuples, array('\Udoh\Emsa\Utils\SortUtils', 'sortWhitelistRuleTupleEventDateNewestFirst'));
    }

    /**
     * Sorts the WhitelistRuleTuples in this Set by the underlying Event Date, in order from oldest events to newest.
     *
     * @return bool
     */
    public function sortByEventDateOldestFirst()
    {
        $this->rewind();
        return uasort($this->whitelistRuleTuples, array('\Udoh\Emsa\Utils\SortUtils', 'sortWhitelistRuleTupleEventDateOldestFirst'));
    }

    /**
     * Indicates whether there exists >1 event for any condition in the set of Whitelist Rule matches.
     *
     * @return bool
     */
    public function hasMultipleEventsPerCondition()
    {
        $eventsByCondition = [];

        foreach ($this->whitelistRuleTuples as $whitelistRuleTuple) {
            $currentCondition = preg_replace('/\s/', '', $whitelistRuleTuple->getNedssEvent()->getDiseaseName());
            if (array_key_exists($currentCondition, $eventsByCondition)) {
                return true;
            } else {
                $eventsByCondition[$currentCondition] = 0;
            }
        }

        return false;
    }

    /**
     * Indicates whether all WhitelistRuleTuples in this set use the WhitelistRule::WHITELIST_RULETYPE_TIME_ONSET_BEFOREAFTER rule type.
     *
     * @return bool
     */
    public function hasOnlyBeforeAfterWhitelistRules(): bool
    {
        $result = true;

        if ($this->count() < 1) {
            // just in case this set has no Tuples, short-circuit to false
            return false;
        }

        foreach ($this->whitelistRuleTuples as $whitelistRuleTuple) {
            if ($whitelistRuleTuple->getWhitelistRuleApplied()->getRuleType() === WhitelistRule::WHITELIST_RULETYPE_TIME_ONSET_BEFOREAFTER) {
                $result = $result && true;
            } else {
                // found at least one non-BEFOREAFTER; no sense going any further
                return false;
            }
        }

        return $result;
    }

    /**
     * Get a list of all of the conditions found within this set of Whitelist Rule matches.
     *
     * @return array
     */
    public function getConditionsMatched()
    {
        $conditionsMatched = [];

        foreach ($this->whitelistRuleTuples as $whitelistRuleTuple) {
            $conditionsMatched[] = $whitelistRuleTuple->getNedssEvent()->getDiseaseName();
        }

        return array_unique($conditionsMatched);
    }


}
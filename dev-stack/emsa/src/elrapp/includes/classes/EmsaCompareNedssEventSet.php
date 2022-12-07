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

/**
 * Collection of matched NEDSS events.
 *
 * @author Josh Ridderhoff <jridderhoff@utah.gov>
 */
class EmsaCompareNedssEventSet implements Countable, Iterator
{

    /** @var EmsaCompareNedssEvent[] */
    private $nedssEvents;

    public function __construct()
    {
        $this->nedssEvents = [];
    }

    /* Countable implementation */

    public function count()
    {
        return count($this->nedssEvents);
    }

    /* Iterator implementation */

    public function valid()
    {
        return !is_null(key($this->nedssEvents));
    }

    public function rewind()
    {
        reset($this->nedssEvents);
    }

    public function current()
    {
        return current($this->nedssEvents);
    }

    public function key()
    {
        return key($this->nedssEvents);
    }

    public function next()
    {
        next($this->nedssEvents);
    }

    /**
     * Add a NEDSS event to the set of matches.
     *
     * @param EmsaCompareNedssEvent $nedssEvent
     */
    public function addNedssEvent(EmsaCompareNedssEvent $nedssEvent): void
    {
        $eventKey = $nedssEvent->getEventId();
        $this->nedssEvents[$eventKey] = $nedssEvent;
    }

    /**
     * Gets a specific NEDSS event from the set of matches.
     *
     * @param int $eventId Event ID of the targeted NEDSS event
     *
     * @return EmsaCompareNedssEvent|null
     */
    public function getNedssEvent(int $eventId): ?EmsaCompareNedssEvent
    {
        if (!empty($this->nedssEvents[$eventId])) {
            return $this->nedssEvents[$eventId];
        } else {
            return null;
        }
    }

    /**
     * Get all NEDSS events for this set of matches.
     *
     * @return EmsaCompareNedssEvent[] Array of NEDSS events
     */
    public function getNedssEvents(): array
    {
        return $this->nedssEvents;
    }

    /**
     * Get the Event IDs for all NEDSS events in this set of matches
     *
     * @return int[] Array of NEDSS event IDs
     */
    public function getNedssEventIds(): array
    {
        $eventIds = [];

        foreach ($this->getNedssEvents() as $nedssEvent) {
            $eventIds[] = (int) $nedssEvent->getEventId();
        }

        return $eventIds;
    }

    /**
     * Remove a specific NEDSS event from the set of matches.
     *
     * @param int $eventId Event ID of the targeted NEDSS event
     *
     * @return bool
     */
    public function removeNedssEvent(int $eventId): bool
    {
        if (isset($this->nedssEvents[$eventId])) {
            unset($this->nedssEvents[$eventId]);
            return true;
        } else {
            return false;
        }
    }

    /**
     * Remove all NEDSS events from this Event Set except the one specified by <i>eventId</i>
     *
     * @param int $eventId Event ID to preserve.
     *
     * @return bool Returns <b>FALSE</b> if specified <i>eventId</i> does not exist in the set, <b>TRUE</b> otherwise.
     */
    public function cropToNedssEvent(int $eventId): bool
    {
        if (EmsaUtils::emptyTrim($this->getNedssEvent($eventId))) {
            return false;
        } else {
            foreach ($this->nedssEvents as $targetNedssEvent) {
                if ($targetNedssEvent->getEventId() != $eventId) {
                    unset($this->nedssEvents[$targetNedssEvent->getEventId()]);
                }
            }
            return true;
        }
    }

    /**
     * Sorts the NEDSS events in this Event Set by the Event Date, in order from newest events to oldest
     *
     * @return bool
     */
    public function sortByEventDateNewestFirst(): bool
    {
        $this->rewind();
        return uasort($this->nedssEvents, array('\Udoh\Emsa\Utils\SortUtils', 'sortNedssEventDateNewestFirst'));
    }

    /**
     * Sorts the NEDSS events in this Event Set by the Event Date, in order from oldest events to newest
     *
     * @return bool
     */
    public function sortByEventDateOldestFirst(): bool
    {
        $this->rewind();
        return uasort($this->nedssEvents, array('\Udoh\Emsa\Utils\SortUtils', 'sortNedssEventDateOldestFirst'));
    }

}

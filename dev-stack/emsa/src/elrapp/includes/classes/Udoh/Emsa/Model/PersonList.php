<?php

namespace Udoh\Emsa\Model;

/**
 * Copyright (c) 2017 Utah Department of Technology Services and Utah Department of Health
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
 * @copyright Copyright (c) 2017 Utah Department of Technology Services and Utah Department of Health
 */

/**
 * Collection of \Udoh\Emsa\Model\Person objects.
 *
 * @author Josh Ridderhoff <jridderhoff@utah.gov>
 */
class PersonList implements \Iterator, \Countable
{
    /** @var array */
    private $personList;

    public function __construct()
    {
        $this->personList = array();
    }

    public function count()
    {
        return count($this->personList);
    }

    public function current()
    {
        return current($this->personList);
    }

    public function key()
    {
        return key($this->personList);
    }

    public function next()
    {
        next($this->personList);
    }

    public function rewind()
    {
        reset($this->personList);
    }

    public function valid()
    {
        return !is_null(key($this->personList));
    }
    
    /**
     * Add a Person to the list.
     * @param \Udoh\Emsa\Model\Person $person
     * @return \Udoh\Emsa\Model\PersonList
     */
    public function add(Person $person)
    {
        $this->personList[(int) $person->getPersonId()] = $person;
        return $this;
    }
    
    /**
     * Get a Person by Person ID
     * @param int $personId Person ID to retrieve.
     * @return Person Returns <b>NULL</b> if the specified Person ID does not exist in the list.
     */
    public function get($personId)
    {
        if (!empty($personId) && (array_key_exists((int) $personId, $this->personList))) {
            return $this->personList[(int) $personId];
        } else {
            return null;
        }
    }
    
    /**
     * Sort the list of Persons, ranked in descending Match Score values
     * @return \Udoh\Emsa\Model\PersonList
     */
    public function sortByMatchScore()
    {
        usort($this->personList, array($this, 'callableMatchScoreSort'));
        
        return $this;
    }

    /**
     * Callable Match Score-based sorting algorithm.
     * @param \Udoh\Emsa\Model\Person $a
     * @param \Udoh\Emsa\Model\Person $b
     */
    protected function callableMatchScoreSort(Person $a, Person $b)
    {
        $matchScoreA = (float) $a->getMatchScore();
        $matchScoreB = (float) $b->getMatchScore();
        
        if ($matchScoreA == $matchScoreB) {
            return 0;
        }
        
        return ($matchScoreA < $matchScoreB) ? -1 : 1;
    }
}

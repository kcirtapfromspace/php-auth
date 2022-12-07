<?php

namespace Udoh\Emsa\Model;

use Countable;
use Iterator;

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

/**
 * Collection of \Udoh\Emsa\Model\PersonFacility objects.
 *
 * @author Josh Ridderhoff <jridderhoff@utah.gov>
 */
class PersonFacilityList implements Iterator, Countable
{

    /** @var array */
    private $personFacilityList;

    public function __construct()
    {
        $this->personFacilityList = array();
    }

    public function count()
    {
        return count($this->personFacilityList);
    }

    public function current()
    {
        return current($this->personFacilityList);
    }

    public function key()
    {
        return key($this->personFacilityList);
    }

    public function next()
    {
        next($this->personFacilityList);
    }

    public function rewind()
    {
        reset($this->personFacilityList);
    }

    public function valid()
    {
        return !is_null(key($this->personFacilityList));
    }

    /**
     * Add a Person Facility to the list.
     *
     * @param PersonFacility $personFacility
     *
     * @return PersonFacilityList
     */
    public function add(PersonFacility $personFacility): PersonFacilityList
    {
        $this->personFacilityList[] = $personFacility;
        return $this;
    }

    /**
     * Get a specific Person Facility by index number.
     *
     * @param int $index
     *
     * @return PersonFacility|null
     */
    public function get(int $index = 0): ?PersonFacility
    {
        $cleanIndex = (int) filter_var($index, FILTER_SANITIZE_NUMBER_INT);

        if (($cleanIndex >= 0) && array_key_exists($cleanIndex, $this->personFacilityList)) {
            return $this->personFacilityList[$cleanIndex];
        } else {
            return null;
        }
    }

}

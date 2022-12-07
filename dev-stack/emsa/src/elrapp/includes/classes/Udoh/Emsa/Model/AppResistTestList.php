<?php

namespace Udoh\Emsa\Model;

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

/**
 * Collection of AppResistTest objects.
 *
 * @author Josh Ridderhoff <jridderhoff@utah.gov>
 */
class AppResistTestList implements \Countable, \Iterator
{
    /** @var array */
    private $list;

    public function __construct()
    {
        $this->list = array();
    }

    public function count()
    {
        return count($this->list);
    }

    public function current()
    {
        return current($this->list);
    }

    public function key()
    {
        return key($this->list);
    }

    public function next()
    {
        next($this->list);
    }

    public function rewind()
    {
        reset($this->list);
    }

    public function valid()
    {
        return !is_null(key($this->list));
    }
    
    /**
     * Add a new Resistance Test Result to the list.
     * 
     * @param AppResistTest $resistTest
     * 
     * @return AppResistTestList
     */
    public function add(AppResistTest $resistTest)
    {
        $this->list[(int) $resistTest->getId()] = $resistTest;
        return $this;
    }
    
    /**
     * Get an individual Resistance Test Result by ID.
     * 
     * @param int $id Resistance Test Result ID to retrieve.
     * 
     * @return AppResistTest Returns <b>NULL</b> if the specified Resistance Test Result ID does not exist.
     */
    public function get($id)
    {
        if (!empty($id) && array_key_exists((int) $id, $this->list)) {
            return $this->list[(int) $id];
        } else {
            return null;
        }
    }
}

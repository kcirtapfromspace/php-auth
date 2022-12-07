<?php
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

namespace Udoh\Emsa\UI\Queue;

use Countable;
use Iterator;
use EmsaListResult;

/**
 * Container for a set of EmsaListResult objects
 *
 * @author Josh Ridderhoff <jridderhoff@utah.gov>
 */
class EmsaListResultSet implements Countable, Iterator
{

    /** @var EmsaListResult[] */
    private $listResults = array();

    /* Countable implementation */

    public function count()
    {
        return count($this->listResults);
    }

    /* Iterator implementation */

    public function valid()
    {
        return !is_null(key($this->listResults));
    }

    public function rewind()
    {
        reset($this->listResults);
    }

    public function current()
    {
        return current($this->listResults);
    }

    public function key()
    {
        return key($this->listResults);
    }

    public function next()
    {
        next($this->listResults);
    }

    /**
     * Add an EMSA Queue List query result to the set of results
     * @param EmsaListResult $result Result from an EMSA Queue List query to add to the set of results
     */
    public function addResult(EmsaListResult $result)
    {
        $this->listResults[] = $result;
    }

    /**
     * Gets the set of results from an EMSA Queue List query
     * @return EmsaListResult[]
     */
    public function getResults()
    {
        return $this->listResults;
    }

}

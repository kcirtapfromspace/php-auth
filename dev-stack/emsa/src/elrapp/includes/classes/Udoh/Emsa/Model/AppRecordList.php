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
 * Collection of \Udoh\Emsa\Model\AppRecord objects.
 *
 * @author Josh Ridderhoff <jridderhoff@utah.gov>
 */
class AppRecordList implements \Iterator, \Countable
{

    /** @var array */
    private $recordList;

    public function __construct()
    {
        $this->recordList = array();
    }

    public function count()
    {
        return count($this->recordList);
    }

    public function current()
    {
        return current($this->recordList);
    }

    public function key()
    {
        return key($this->recordList);
    }

    public function next()
    {
        next($this->recordList);
    }

    public function rewind()
    {
        reset($this->recordList);
    }

    public function valid()
    {
        return !is_null(key($this->recordList));
    }
    
    /**
     * Add an Application Record to the list.
     * @param \Udoh\Emsa\Model\AppRecord $appRecord
     * @return \Udoh\Emsa\Model\AppRecordList
     */
    public function add(AppRecord $appRecord)
    {
        $this->recordList[] = $appRecord;
        return $this;
    }
    
    /**
     * Get a specific Application Record by index number.
     * @param int $index Record index number.
     * @return \Udoh\Emsa\Model\AppRecord;
     */
    public function getRecord($index = 0)
    {
        $cleanIndex = (int) filter_var($index, FILTER_SANITIZE_NUMBER_INT);
        
        if (($cleanIndex >= 0) && array_key_exists($cleanIndex, $this->recordList)) {
            return $this->recordList[$cleanIndex];
        } else {
            return null;
        }
    }

    /**
     * Get the count of a specific type of records this AppRecordList contains.
     *
     * @param int $appRecordType Record type to check (one of Udoh\Emsa\Constants\AppRecordType)
     *
     * @return int
     */
    public function countByRecordType(int $appRecordType): int
    {
        $recordTypeCount = 0;

        /** @var AppRecord $appRecord */
        foreach ($this->recordList as $appRecord) {
            if ($appRecord->getRecordType() === $appRecordType) {
                $recordTypeCount++;
            }
        }

        return $recordTypeCount;
    }

}

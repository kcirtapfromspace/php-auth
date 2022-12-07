<?php

namespace Udoh\Emsa\MessageProcessing;

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
 * Class EventLogExceptionDetail
 *
 * @package Udoh\Emsa\MessageProcessing
 *
 * @author  Josh Ridderhoff <jridderhoff@utah.gov>
 */
class EventLogExceptionDetail
{
    /** @var string */
    protected $type;
    /** @var string */
    protected $description;
    /** @var string */
    protected $details;

    /**
     * EventLogExceptionDetail constructor.
     *
     * @param string $type
     * @param string $description
     * @param string $details
     */
    public function __construct($type = null, $description = null, $details = null)
    {
        $this->type = $type;
        $this->description = $description;
        $this->details = $details;
    }


    /**
     * @return string
     */
    public function getType()
    {
        return $this->type;
    }

    /**
     * @param string $type
     *
     * @return EventLogExceptionDetail
     */
    public function setType($type = null)
    {
        $this->type = $type;
        return $this;
    }

    /**
     * @return string
     */
    public function getDescription()
    {
        return $this->description;
    }

    /**
     * @param string $description
     *
     * @return EventLogExceptionDetail
     */
    public function setDescription($description = null)
    {
        $this->description = $description;
        return $this;
    }

    /**
     * @return string
     */
    public function getDetails()
    {
        return $this->details;
    }

    /**
     * @param string $details
     *
     * @return EventLogExceptionDetail
     */
    public function setDetails($details = null)
    {
        $this->details = $details;
        return $this;
    }

}

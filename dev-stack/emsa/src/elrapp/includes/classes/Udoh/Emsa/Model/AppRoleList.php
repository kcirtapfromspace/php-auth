<?php

namespace Udoh\Emsa\Model;

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
 * Set of Roles from an Application Client.
 * 
 * @package Udoh\Emsa\Model
 *
 * @author Josh Ridderhoff <jridderhoff@utah.gov>
 */
class AppRoleList implements \Iterator, \Countable
{

    /** @var array */
    private $roleList;

    public function __construct()
    {
        $this->roleList = array();
    }

    public function count()
    {
        return count($this->roleList);
    }

    public function current()
    {
        return current($this->roleList);
    }

    public function key()
    {
        return key($this->roleList);
    }

    public function next()
    {
        next($this->roleList);
    }

    public function rewind()
    {
        reset($this->roleList);
    }

    public function valid()
    {
        return !is_null(key($this->roleList));
    }

    /**
     * Add a new role to the collection.
     * 
     * @param int $roleId
     * @param string $roleName
     * 
     * @return \Udoh\Emsa\Model\AppRoleList
     */
    public function add($roleId, $roleName)
    {
        $this->roleList[(int) $roleId] = (string) $roleName;
        return $this;
    }

    /**
     * Returns the name of the role ID specified.
     * 
     * @param int $roleId Application Role ID
     * @return string
     */
    public function getRoleNameById($roleId)
    {
        $roleName = null;

        $cleanRoleId = (int) filter_var($roleId, \FILTER_SANITIZE_NUMBER_INT);

        if (($cleanRoleId > 0) && (array_key_exists($cleanRoleId, $this->roleList))) {
            $roleName = (string) $this->roleList[$cleanRoleId];
        }

        return $roleName;
    }

}

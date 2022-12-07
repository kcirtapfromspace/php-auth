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
 * Container for response from a client action
 *
 * @author Josh Ridderhoff <jridderhoff@utah.gov>
 */
class ClientResponse
{
    /** @var bool */
    protected $status;

    /** @var bool */
    protected $lock;
    
    /** @var array */
    protected $errorList;
    
    /**
     * Create a new ClientResponse object.
     */
    public function __construct()
    {
        $this->status = false;
        $this->errorList = array();
    }
    
    /**
     * Get the response status from the client operation.
     * 
     * @return bool
     */
    public function getStatus()
    {
        return $this->status;
    }

    /**
     * Get whether the response from the client indicated a record lock.
     *
     * @return bool
     */
    public function getLock()
    {
        return $this->lock;
    }
    
    /**
     * Get the collection of errors reported from the client operation.
     * 
     * @return array
     */
    public function getErrorList()
    {
        return $this->errorList;
    }
    
    /**
     * Get a string representation of all errors reported by the client operation.
     * 
     * @return string
     */
    public function getErrorString()
    {
        $errorString = null;
        
        foreach ($this->errorList as $errorItem) {
            $errorString .= "[" . (string) $errorItem['action'] . "] Error " . (string) $errorItem['status'] . ": " . (string) $errorItem['message'] . "\n";
        }
        
        return $errorString;
    }
    
    /**
     * Set the status of the client operation.
     * 
     * @param bool $status
     * 
     * @return ClientResponse
     */
    public function setStatus($status)
    {
        if ($status === true) {
            $this->status = true;
        } else {
            $this->status = false;
        }
        
        return $this;
    }

    /**
     * Set whether the client response indicated a record lock.
     *
     * @param bool $lock
     *
     * @return ClientResponse
     */
    public function setLock($lock)
    {
        if ($lock === true) {
            $this->lock = true;
        } else {
            $this->lock = false;
        }

        return $this;
    }
    
    /**
     * Add an error returned by the client operation.
     *
     * @param string $action  Client operation that reported the error.
     * @param string $status  Error status code reported by the client.
     * @param string $message Error message reported by the client.
     *
     * @return ClientResponse
     */
    public function addError($action, $status, $message)
    {
        $this->errorList[] = array(
            'action' => (string) $action,
            'status' => (string) $status,
            'message' => (string) $message
        );
        
        return $this;
    }
}

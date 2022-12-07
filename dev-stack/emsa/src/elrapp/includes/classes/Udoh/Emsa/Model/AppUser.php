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
 * Object representation of a user from a client Application.
 * 
 * @package Udoh\Emsa\Model
 *
 * @author Josh Ridderhoff <jridderhoff@utah.gov>
 */
class AppUser
{

    protected $emailAddress;
    protected $fullName;

    /**
     * Create a new Application User.
     * 
     * @param string $emailAddress [Optional] User's e-mail address.
     * @param string $fullName [Optional] User's full name.
     */
    public function __construct($emailAddress = null, $fullName = null)
    {
        $this->setEmailAddress($emailAddress);
        $this->setFullName($fullName);
    }

    /**
     * Get the user's e-mail address.
     * 
     * @return string
     */
    public function getEmailAddress()
    {
        return $this->emailAddress;
    }

    /**
     * Get the user's full name.
     * 
     * @return string
     */
    public function getFullName()
    {
        return $this->fullName;
    }

    /**
     * Set the user's email address.
     * 
     * @param string $emailAddress
     * @return \Udoh\Emsa\Model\AppUser
     */
    public function setEmailAddress($emailAddress = null)
    {
        if (is_null($emailAddress)) {
            $this->emailAddress = null;
        } else {
            $this->emailAddress = (string) filter_var($emailAddress, \FILTER_SANITIZE_EMAIL);
        }

        return $this;
    }

    /**
     * Set the user's full name.
     * 
     * @param string $fullName
     * @return \Udoh\Emsa\Model\AppUser
     */
    public function setFullName($fullName = null)
    {
        if (is_null($fullName)) {
            $this->fullName = null;
        } else {
            $this->fullName = (string) filter_var($fullName, \FILTER_SANITIZE_STRING);
        }

        return $this;
    }

}

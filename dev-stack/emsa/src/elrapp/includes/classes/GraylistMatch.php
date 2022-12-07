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

use Udoh\Emsa\Client\AppClientList;
use Udoh\Emsa\Exceptions\EmsaMessageNotFoundException;
use Udoh\Emsa\Exceptions\VocabularyException;

/**
 * Container for a single match from the Graylist Pool
 *
 * @author Josh Ridderhoff <jridderhoff@utah.gov>
 */
class GraylistMatch
{

    /** @var EmsaMessage */
    private $emsaMessage;

    /**
     * Create a new Graylist Pool match.
     *
     * @param PDO           $dbConn          PDO connection to EMSA database
     * @param AppClientList $appClientList   List of configured applications for this installation
     * @param int           $systemMessageId System Message ID of the matching EMSA message
     *
     * @throws EmsaMessageNotFoundException
     * @throws VocabularyException
     */
    public function __construct(PDO $dbConn, AppClientList $appClientList, $systemMessageId)
    {
        $this->emsaMessage = new EmsaMessage($dbConn, $appClientList, $systemMessageId, false);
    }

    /**
     * Get this match's EmsaMessage object
     * 
     * @return EmsaMessage
     */
    public function getEmsaMessage()
    {
        return $this->emsaMessage;
    }

}

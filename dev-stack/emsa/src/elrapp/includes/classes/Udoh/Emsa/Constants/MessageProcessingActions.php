<?php

namespace Udoh\Emsa\Constants;

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
 * Constants used during main EMSA Message Processing
 *
 * @author Josh Ridderhoff <jridderhoff@utah.gov>
 */
class MessageProcessingActions
{

    // bulk message processing
    const BULK_ACTION_RETRY = 1;
    const BULK_ACTION_DELETE = 2;
    const BULK_ACTION_MOVE = 3;
    const BULK_ACTION_QA_FLAG = 4;
    const BULK_ACTION_ADD_RECORD = 16;
    const BULK_ACTION_UPDATE_RECORD = 17;
    
    // standard single-message processing
    const ACTION_AUTOMATED_PROCESSING = 5;
    const ACTION_ADD_RECORD = 6;
    const ACTION_UPDATE_RECORD = 7;
    const ACTION_EDIT = 8;
    const ACTION_SAVE = 9;
    const ACTION_MOVE = 10;
    const ACTION_DELETE = 11;
    const ACTION_RETRY = 12;
    const ACTION_SET_FLAG = 13;
    const ACTION_UNSET_FLAG = 14;
    const ACTION_QA_COMMENT = 15;

}

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

if (!class_exists('\Udoh\Emsa\Auth\Authenticator') || !\Udoh\Emsa\Auth\Authenticator::userHasPermission(\Udoh\Emsa\Auth\Authenticator::URIGHTS_ADMIN)) {
    ob_end_clean();
    header($_SERVER['SERVER_PROTOCOL'] . " 404 Not Found", TRUE, 404);
    exit;
}

/* Child Vocab > Child LOINC */
define('EXPORT_QRY_CHILD_HL7XML', 'SELECT sl.ui_name AS lab_name, spm.message_version AS message_version, spm.xpath AS hl7_xpath, sp.xpath AS master_xpath, 
			spm.glue_string AS glue_string, spm.sequence AS sequence 
		FROM ' . $emsaDbSchemaPrefix . 'structure_path_mirth spm
		INNER JOIN ' . $emsaDbSchemaPrefix . 'structure_labs sl ON (spm.lab_id = sl.id) 
		LEFT JOIN ' . $emsaDbSchemaPrefix . 'structure_path sp ON (spm.master_path_id = sp.id) 
		WHERE spm.lab_id = %d  
		ORDER BY sl.ui_name, spm.message_version, sp.xpath, spm.sequence, spm.xpath;');

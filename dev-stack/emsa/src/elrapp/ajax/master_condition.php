<?php
/**
 * Copyright (c) 2020 Utah Department of Technology Services and Utah Department of Health
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
 * @copyright Copyright (c) 2020 Utah Department of Technology Services and Utah Department of Health
 */

use Udoh\Emsa\Utils\DisplayUtils;

// prevent caching...
header('Expires: Mon, 26 Jul 1997 05:00:00 GMT');
header('Cache-Control: no-cache');
header('Pragma: no-cache');

header('Content-Type: application/json');

include __DIR__ . '/../includes/app_config.php';

session_write_close(); // done writing to session; prevent blocking

$conditions = array();

$sql = "SELECT vmc.c_id AS id, mv.concept AS concept 
        FROM vocab_master_condition vmc 
        JOIN vocab_master_vocab mv ON (vmc.condition = mv.id) 
        WHERE vmc.is_initial IS TRUE 
        ORDER BY mv.concept;";
$stmt = $emsaDbFactory->getConnection()->prepare($sql);

if ($stmt->execute()) {
    while ($row = $stmt->fetchObject()) {
        $conditions[] = array(
            'id' => (int) $row->id,
            'concept' => DisplayUtils::xSafe(trim($row->concept), 'UTF-8', false)
        );
    }
}

$stmt = null;
$emsaDbFactory = null;

echo json_encode($conditions, JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_TAG);
exit;

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
?>
<h1 class="elrhdg"><span class="ui-icon ui-icon-header ui-icon-elrnotify"></span>Pending ELR E-mail Notifications</h1>

<div class="lab_results_container ui-widget ui-corner-all">

    <table id="labResults">
        <thead>
            <tr>
                <th>Notification Type</th>
                <th>Record Number</th>
                <th>Investigator</th>
                <th>Notify State?</th>
                <th>Notify Jurisdiction?</th>
                <th>Jurisdiction</th>
                <th>Condition (Organism) [<em>Test Type; Result</em>]</th>
                <th>Generated</th>
            </tr>
        </thead>
        <tbody>

            <?php
            try {
                $sql = "SELECT n.*, t.label AS label 
                        FROM batch_notifications n 
                        INNER JOIN batch_notification_types t ON (n.notification_type = t.id) 
                        WHERE (n.date_sent_lhd IS NULL AND n.notify_lhd IS TRUE) 
                        OR (n.date_sent_state IS NULL AND n.notify_state IS TRUE) 
                        ORDER BY n.date_created;";
                $stmt = $adminDbConn->query($sql);
                
                while ($row = $stmt->fetchObject()) {
                    echo "<tr>";
                    echo "<td>" . \Udoh\Emsa\Utils\DisplayUtils::xSafe($row->label) . "</td>";
                    echo "<td>" . \Udoh\Emsa\Utils\DisplayUtils::xSafe($row->record_number) . "</td>";
                    echo "<td>" . \Udoh\Emsa\Utils\DisplayUtils::xSafe($row->investigator) . "</td>";
                    echo "<td>" . (($row->notify_state === true) ? "<span class=\"ui-icon ui-icon-elrsuccess\" title=\"Yes\"></span>" : "<span class=\"ui-icon ui-icon-elrcancel\" title=\"No\"></span>") . "</td>";
                    echo "<td>" . (($row->notify_lhd === true) ? "<span class=\"ui-icon ui-icon-elrsuccess\" title=\"Yes\"></span>" : "<span class=\"ui-icon ui-icon-elrcancel\" title=\"No\"></span>") . "</td>";
                    echo "<td>" . (($row->custom === true) ? '<span style="color: #7a1dd2;">' . \Udoh\Emsa\Utils\DisplayUtils::xSafe(\EmsaUtils::customLhdName($adminDbConn, intval($row->jurisdiction_id))) . '</span>' : '<strong>' . \Udoh\Emsa\Utils\DisplayUtils::xSafe(\EmsaUtils::lhdName($adminDbConn, intval($row->jurisdiction_id))) . '</strong>') . "</td>";
                    echo "<td>" . \Udoh\Emsa\Utils\DisplayUtils::xSafe($row->condition) . " (" . \Udoh\Emsa\Utils\DisplayUtils::xSafe($row->organism) . ") [<em>" . \Udoh\Emsa\Utils\DisplayUtils::xSafe($row->test_type) . "; " . \Udoh\Emsa\Utils\DisplayUtils::xSafe($row->test_result) . "</em>]</td>";
                    echo "<td>" . \Udoh\Emsa\Utils\DisplayUtils::xSafe(date("m/d/Y H:i:s", strtotime($row->date_created))) . "</td>";
                    echo "</tr>";
                }
            } catch (Throwable $e) {
                \Udoh\Emsa\Utils\ExceptionUtils::logException($e);
                \Udoh\Emsa\Utils\DisplayUtils::drawError('Could not connect to ELR notification database.', true);
            } finally {
                $stmt = null;
            }
            ?>

        </tbody>
    </table>
    <br><br>

</div>
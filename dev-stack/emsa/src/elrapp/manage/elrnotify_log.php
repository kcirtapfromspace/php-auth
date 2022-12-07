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
<h1 class="elrhdg"><span class="ui-icon ui-icon-header ui-icon-elrnotify"></span>ELR E-mail Notification Log</h1>

<div class="vocab_search ui-tabs ui-widget">
    <div style="float: left; width: 50%; font-style: italic; font-family: 'Open Sans', Arial, Helvetica, sans-serif; margin: 5px;">
        E-mail Notifications sent by EMSA within the last 24 hours.
    </div>
</div>

<div class="lab_results_container ui-widget ui-corner-all">

    <table id="labResults">
        <thead>
            <tr>
                <th>Date/Time Sent</th>
                <th>E-mail Address</th>
                <th>State/LHD?</th>
                <th>Successful?</th>
            </tr>
        </thead>
        <tbody>

            <?php
            try {
                $sql = "SELECT * 
                        FROM batch_notification_log 
                        WHERE created > (CURRENT_TIMESTAMP - interval '24 hours') 
                        ORDER BY date_trunc('hour', created) DESC, jurisdiction;";
                $stmt = $adminDbConn->query($sql);
            
                while ($row = $stmt->fetchObject()) {
                    echo "<tr>";
                    echo "<td>" . \Udoh\Emsa\Utils\DisplayUtils::xSafe(date("d M Y, g:i a", strtotime($row->created))) . "</td>";
                    echo "<td>" . \Udoh\Emsa\Utils\DisplayUtils::xSafe($row->email) . "</td>";
                    echo "<td>" . ((is_null($row->jurisdiction) || (strlen(trim($row->jurisdiction)) < 1)) ? '<strong style="color: darkslategray;" title="State-Level Notification">State</strong>' : (($row->custom === true) ? '<span style="color: darkgray;" title="Virtual Jurisdiction">' . \Udoh\Emsa\Utils\DisplayUtils::xSafe(\EmsaUtils::customLhdName($adminDbConn, intval($row->jurisdiction))) . '</span>' : '<strong title="Local Health Department">' . \Udoh\Emsa\Utils\DisplayUtils::xSafe(\EmsaUtils::lhdName($adminDbConn, intval($row->jurisdiction))) . '</strong>') ) . "</td>";
                    echo "<td>" . (($row->success === true) ? "<span class=\"ui-icon ui-icon-elrsuccess\" title=\"Yes\"></span>" : "<span class=\"ui-icon ui-icon-elrcancel\" title=\"No\"></span>") . "</td>";
                    echo "</tr>";
                }
            } catch (Throwable $e) {
                \Udoh\Emsa\Utils\ExceptionUtils::logException($e);
                \Udoh\Emsa\Utils\DisplayUtils::drawError('Could not connect to ELR notification log database.', true);
            } finally {
                $stmt = null;
            }
            ?>

        </tbody>
    </table>
    <br><br>

</div>
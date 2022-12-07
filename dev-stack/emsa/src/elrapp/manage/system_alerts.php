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

$alertAction = filter_input(INPUT_GET, 'alert_action', FILTER_SANITIZE_STRING);
$alertId = filter_input(INPUT_GET, 'alert_id', FILTER_SANITIZE_NUMBER_INT);

if ($alertAction && $alertId) {
    try {
        $resolveSql = "UPDATE system_alerts SET resolved = TRUE WHERE id = :alertId;";
        $resolveStmt = $adminDbConn->prepare($resolveSql);
        $resolveStmt->bindValue(':alertId', intval($alertId), \PDO::PARAM_INT);

        if ($resolveStmt->execute()) {
            \Udoh\Emsa\Utils\DisplayUtils::drawHighlight('Alert successfully cleared!', 'ui-icon-elrsuccess');
        } else {
            \Udoh\Emsa\Utils\DisplayUtils::drawError('Unable to clear alert.');
        }
    } catch (Throwable $e) {
        \Udoh\Emsa\Utils\ExceptionUtils::logException($e);
        \Udoh\Emsa\Utils\DisplayUtils::drawError('Unable to clear alert.  An unexpected error occurred.');
    }
}
?>

<script>
    $(function() {
        $(".btn_resolve_alert").button({
            icon: "ui-icon-elrclose"
        }).on("click", function() {
            $("#alert_id").val($(this).val());
            $("#alert_action").val('resolve');
            $("#resolve_item").trigger("submit");
        });
    });
</script>

<h1 class="elrhdg"><span class="ui-icon ui-icon-header ui-icon-elrerrorbig"></span>EMSA System Alerts</h1>

<div class="vocab_search ui-tabs ui-widget">
    <div style="float: left; width: 50%; font-style: italic; font-family: 'Open Sans', Arial, Helvetica, sans-serif; margin: 5px;">
        Active alerts generated by EMSA that are not message-specific.
    </div>
</div>

<div class="lab_results_container ui-widget ui-corner-all">

    <table id="labResults">
        <thead>
            <tr>
                <th>Actions</th>
                <th>Date/Time</th>
                <th>Alert Description</th>
                <th>Details</th>
            </tr>
        </thead>
        <tbody>

            <?php
            try {
                $sql = "SELECT sa.id AS alert_id, sa.created_at AS created_at, sat.name AS name, sa.alert_type_id AS alert_type_id, sa.info AS info, sa.alt_info AS alt_info, om.connector AS connector
                        FROM system_alerts sa
                        INNER JOIN system_alert_types sat ON (sa.alert_type_id = sat.id)
                        LEFT JOIN system_original_messages om ON (sa.alt_info::integer = om.id)
                        WHERE resolved IS FALSE
                        ORDER BY created_at DESC;";
                $stmt = $adminDbConn->query($sql);

                if ($stmt->rowCount() > 0) {
                    while ($row = $stmt->fetchObject()) {
                        echo '<tr>';
                        echo '<td>';
                        echo '<button type="button" class="btn_resolve_alert" value="' . intval($row->alert_id) . '">Clear Alert</button>';
                        echo '</td>';
                        echo '<td>' . \Udoh\Emsa\Utils\DisplayUtils::xSafe(\Udoh\Emsa\Utils\DateTimeUtils::createMixed($row->created_at)->format("d M Y, g:i a")) . '</td>';
                        echo '<td>' . \Udoh\Emsa\Utils\DisplayUtils::xSafe($row->name) . '</td>';
                        if (intval($row->alert_type_id === \Udoh\Emsa\Constants\SystemAlerts::UNRECOGNIZED_SENDING_FACILITY)) {
                            echo '<td>';
                            echo '<strong>Sending Facility ID:</strong> ' . \Udoh\Emsa\Utils\DisplayUtils::xSafe($row->info);
                            echo '<br><strong>Connector:</strong>' . \Udoh\Emsa\Utils\DisplayUtils::xSafe($row->connector);
                            echo '<br><strong>Original Message ID:</strong> ' . intval($row->alt_info);
                            echo '</td>';
                        }
                        echo '</tr>';
                    }
                } else {
                    echo '<tr><td colspan="5" style="text-align: center;"><em>&mdash; No Active Alerts! &mdash;</em></td></tr>';
                }
            } catch (Throwable $e) {
                \Udoh\Emsa\Utils\ExceptionUtils::logException($e);
                \Udoh\Emsa\Utils\DisplayUtils::drawError('Could not retrieve active System Alerts.');
            }
            ?>

        </tbody>
    </table>
    <br><br>

    <form id="resolve_item" method="GET">
        <input type="hidden" id="alert_id" name="alert_id">
        <input type="hidden" id="alert_action" name="alert_action">
        <input type="hidden" name="selected_page" value="<?php echo intval($navSelectedPage); ?>" />
        <input type="hidden" name="submenu" value="<?php echo intval($navSubmenu); ?>" />
        <input type="hidden" name="cat" value="<?php echo intval($navCat); ?>" />
    </form>

</div>
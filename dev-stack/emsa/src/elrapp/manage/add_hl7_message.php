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

use Udoh\Emsa\PDOFactory\PostgreSQL;
use Udoh\Emsa\Utils\DisplayUtils;
use Udoh\Emsa\Utils\ExceptionUtils;

if (!class_exists('\Udoh\Emsa\Auth\Authenticator') || !\Udoh\Emsa\Auth\Authenticator::userHasPermission(\Udoh\Emsa\Auth\Authenticator::URIGHTS_ADMIN)) {
    ob_end_clean();
    header($_SERVER['SERVER_PROTOCOL'] . " 404 Not Found", TRUE, 404);
    exit;
}
?>
<script type="text/javascript">
    $(function() {
        $.ajax({
            type: 'GET',
            url: 'ajax/recent_connectors.php',
            dataType: 'json'
        }).done(function (data) {
            if (data.length) {
                $("#lab_selector").autocomplete({
                   source: data
                });
            }
        });

        $("#addhl7form").show();
        $("#hl7message").trigger("focus");

        $("#btn_save").button({
            icon: "ui-icon-elrsave"
        });
    });
</script>

<?php
$cleanLabSelector = (string) filter_input(INPUT_POST, 'lab_selector', FILTER_SANITIZE_STRING);
$cleanDebugSelector = (int) filter_input(INPUT_POST, 'debug_selector', FILTER_SANITIZE_NUMBER_INT);

if ($serverEnvironment === ELR_ENV_TEST) {
    switch ($cleanDebugSelector) {
        case 1:
            $debugSelector = true;
            $msgChannel = 7;
            break;
        case 2:
            $debugSelector = false;
            $msgChannel = 6;
            break;
        default:
            $debugSelector = false;
            $msgChannel = 6;
            break;
    }
} else {
    $msgChannel = 6;
}

if (((int) filter_input(INPUT_POST, 'add_flag', FILTER_SANITIZE_NUMBER_INT) === 1) && !empty($cleanLabSelector)) {
    $generatedOriginalMessageId = 0;
    if (strlen(trim($_POST['hl7message'])) > 0) {
        try {
            $addHl7DbFactory = new PostgreSQL($emsaDbHost, $emsaDbPort, $emsaDbName, $emsaDbUser, $emsaDbPass, $emsaDbSchemaPDO);
            $addHl7DbConn = $addHl7DbFactory->getConnection();

            $sql = "INSERT INTO system_original_messages 
                        (message, connector, port, message_type, channel) 
                    VALUES 
                        (:hl7Message, :connector, :port, :messageType, :channel)
                    RETURNING id;";
            $stmt = $addHl7DbConn->prepare($sql);
            $stmt->bindValue(':hl7Message', $_POST['hl7message'], PDO::PARAM_STR);
            $stmt->bindValue(':connector', $cleanLabSelector, PDO::PARAM_STR);
            $stmt->bindValue(':port', 'ELR', PDO::PARAM_STR);
            $stmt->bindValue(':messageType', 'HL7', PDO::PARAM_STR);
            $stmt->bindValue(':channel', $msgChannel, PDO::PARAM_INT);

            if ($stmt->execute()) {
                $generatedOriginalMessageId = intval($stmt->fetchColumn(0));
            }

            if (intval($generatedOriginalMessageId) > 0) {
                DisplayUtils::drawHighlight("HL7 Message added and will be processed.  The Original Message ID: <span style=\"display: inline-block;\" class=\"ui-icon ui-icon-newwin\"></span><a title=\"View Audit Log\" style=\"color: royalblue; font-weight: bold; text-decoration: underline;\" href=\"" . $webappBaseUrl . "?view_type=4&original_message_id=" . $generatedOriginalMessageId . "&selected_page=6&submenu=5&cat=5\" target=\"_blank\">" . intval($generatedOriginalMessageId) . "</a> can be used to track the message.</p>", "ui-icon-elrsuccess");
            } else {
                throw new Exception('No Message ID generated');
            }
        } catch (Throwable $e) {
            ExceptionUtils::logException($e);
            DisplayUtils::drawError("An error occurred while attempting to stage the new HL7 Message:<br><br>" . DisplayUtils::xSafe($e->getMessage()));
        } finally {
            $stmt = null;
            $addHl7DbConn = null;
            $addHl7DbFactory = null;
        }
    } else {
        DisplayUtils::drawError("No HL7 message detected.  Please try again.");
    }
}
?>

<h1 class="elrhdg"><span class="ui-icon ui-icon-header ui-icon-elrxml"></span>Manually Add Electronic Message</h1>

<div id="addhl7form" class="addnew_lab ui-widget ui-widget-content ui-corner-all">
    <div style="clear: both;"><span class="emsa_form_heading">Add New Electronic Message:</span><br><br></div>
    <form id="new_onboard_form" method="POST" action="<?php echo $webappBaseUrl; ?>?selected_page=<?php echo intval($navSelectedPage); ?>&submenu=<?php echo intval($navSubmenu); ?>&cat=<?php echo intval($navCat); ?>">
        <label style="font-weight: 600;" for="lab_selector">Original Message Connector (Choose from auto-complete list or enter your own):</label><br>
        <input class="ui-corner-all" type="text" id="lab_selector" name="lab_selector" value="<?php DisplayUtils::xEcho($cleanLabSelector, "UTF-8", false); ?>"><br><br>
<?php if ($serverEnvironment === ELR_ENV_TEST) { ?>
            <label style="font-weight: 600;" for="debug_selector">Choose System:</label><br>
            <select class="ui-corner-all" id="debug_selector" name="debug_selector">
                <option <?php
    if ($debugSelector === false) {
        echo 'selected';
    }
    ?> value="0">Normal</option>
                <option <?php
    if ($debugSelector === true) {
        echo 'selected';
    }
    ?> value="1">Debugging</option>
            </select><br><br>
<?php } ?>
        <label style="font-weight: 600;" for="hl7message">Enter Message Contents:</label><br>
        <textarea class="ui-corner-all" name="hl7message" id="hl7message" style="width: 70%; height: 12em;"></textarea>
        <input type="hidden" name="add_flag" value="1" />
        <br><br><button type="submit" name="btn_save" id="btn_save">Submit</button>
    </form>
</div>

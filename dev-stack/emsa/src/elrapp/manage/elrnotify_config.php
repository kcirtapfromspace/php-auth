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
<script>
    $(function () {
        $("#addnew_form").show();

        $("#new_savelab").button({
            icon: "ui-icon-elrsave"
        });

        $("#udoh_enable").button({
            icon: "ui-icon-elroff"
        });
        $("#lhd_enable").button({
            icon: "ui-icon-elroff"
        });

        $(".notify_checkbox").checkboxradio();

    });
</script>
<style type="text/css">
    fieldset { padding: 10px; font-family: 'Open Sans', Arial, Helvetica, sans-serif !important; }
    legend { font-family: 'Oswald', serif; margin-left: 10px; color: firebrick; font-weight: 500; font-size: 1.5em; }
    fieldset label { font-weight: 600 !important; }
    .ui-dialog-content label, #addnew_form label.vocab_search_form2 {
        font-family: 'Open Sans', Arial, Helvetica, sans-serif !important;
        font-weight: 600;
    }
    .ui-dialog-content select, .ui-dialog-content input, #addnew_form select, #addnew_form input {
        font-family: 'Open Sans', Arial, Helvetica, sans-serif !important;
        font-weight: 400;
        background-color: lightcyan;
    }
    .ui-dialog-content {
        font-family: 'Open Sans', Arial, Helvetica, sans-serif !important;
        font-weight: 400;
    }
    .ui-dialog-title {
        font-family: 'Oswald', serif; font-weight: 500; font-size: 1.5em;
        text-shadow: 1px 1px 6px dimgray;
    }
    .ui-dialog-content h3 {
        font-family: 'Oswald', serif; font-weight: 500; font-size: 1.3em;
        color: firebrick;
    }
    .ui-dialog {
        box-shadow: 4px 4px 15px dimgray;
    }
</style>

<?php
$editFlagClean = (int) filter_input(INPUT_POST, 'edit_flag', FILTER_SANITIZE_NUMBER_INT);

if ($editFlagClean === 1) {
    $cleanUdohEnable = ((int) filter_input(INPUT_POST, 'udoh_enable', FILTER_SANITIZE_NUMBER_INT) === 1);
    $cleanLHDEnable = ((int) filter_input(INPUT_POST, 'lhd_enable', FILTER_SANITIZE_NUMBER_INT) === 1);
    $cleanStateEmailListStr = (string) filter_input(INPUT_POST, 'udoh_email', FILTER_SANITIZE_STRING);
    $sanitizedStateEmailList = array();
    
    $cleanStateEmailList = preg_split("/[;,]/", $cleanStateEmailListStr);
    
    foreach ($cleanStateEmailList as $cleanStateEmailAddress) {
        $sanitizedStateEmailList[] = (string) filter_var($cleanStateEmailAddress, FILTER_SANITIZE_EMAIL);
    }
    
    try {
        $editSql = "UPDATE batch_notification_config
                    SET udoh_enable = :boolStateEnable, lhd_enable = :boolLHDEnable, udoh_email = :stateEmailList
                    WHERE id = 1;";
        $editStmt = $adminDbConn->prepare($editSql);
        $editStmt->bindValue(':boolStateEnable', $cleanUdohEnable, PDO::PARAM_BOOL);
        $editStmt->bindValue(':boolLHDEnable', $cleanLHDEnable, PDO::PARAM_BOOL);
        $editStmt->bindValue(':stateEmailList', implode(';', $sanitizedStateEmailList), PDO::PARAM_STR);
        $editStmt->execute();

        \Udoh\Emsa\Utils\DisplayUtils::drawHighlight("Settings successfully updated!", "ui-icon-elrsuccess");
    } catch (Throwable $e) {
        \Udoh\Emsa\Utils\ExceptionUtils::logException($e);
        \Udoh\Emsa\Utils\DisplayUtils::drawError('Unable to save settings.');
    } finally {
        $editStmt = null;
    }
}

// get current state of config options...
$currentLHDEnable = false;
$currentStateEnable = false;
$currentStateEmail = false;

try {
    $currentSql = "SELECT * FROM batch_notification_config WHERE id = 1;";
    $currentRow = $adminDbConn->query($currentSql)->fetchObject();
    
    $currentStateEnable = $currentRow->udoh_enable;
    $currentLHDEnable = $currentRow->lhd_enable;
    $currentStateEmail = $currentRow->udoh_email;
} catch (Throwable $e) {
    \Udoh\Emsa\Utils\ExceptionUtils::logException($e);
    \Udoh\Emsa\Utils\DisplayUtils::drawError('Unable to retrieve current ELR Notification configuration.', true);
}

?>

<h1 class="elrhdg"><span class="ui-icon ui-icon-header ui-icon-elrnotify"></span>ELR Notification Configuration</h1>

<div id="addnew_form" class="addnew_lab ui-widget ui-widget-content ui-corner-all">
    <div style="clear: both;"><span class="emsa_form_heading">Edit Notification Settings:</span><br><br></div>
    <form id="new_lab_form" method="POST" action="<?php echo $webappBaseUrl; ?>?selected_page=<?php echo intval($navSelectedPage); ?>&submenu=<?php echo intval($navSubmenu); ?>&cat=<?php echo intval($navCat); ?>">
        <label class="vocab_search_form2" for="udoh_email">State-level Notification E-mail Address(es) <em>[separate multiple addresses with semicolons or commas]</em>:</label><br><textarea style="background-color: lightcyan; font-family: Consolas, 'Courier New'; font-weight: 400; font-size: 10pt; line-height: 14pt; margin-left: 15px; width: 50%; height: 5em;" class="ui-corner-all" name="udoh_email" id="udoh_email"><?php echo \Udoh\Emsa\Utils\DisplayUtils::xSafe(filter_var($currentStateEmail, FILTER_SANITIZE_STRING), 'UTF-8', false); ?></textarea><br><br>
        <input type="checkbox" class="notify_checkbox" name="udoh_enable" id="udoh_enable" value="1"<?php echo (($currentStateEnable) ? " checked" : ""); ?> /><label class="vocab_search_form2" for="udoh_enable">Send State-level Notifications</label>
        <input type="checkbox" class="notify_checkbox" name="lhd_enable" id="lhd_enable" value="1"<?php echo (($currentLHDEnable) ? " checked" : ""); ?> /><label class="vocab_search_form2" for="lhd_enable">Send LHD Notifications</label>
        <input type="hidden" name="edit_flag" value="1" />
        <br><br><button type="submit" name="new_savelab" id="new_savelab">Save Changes</button>
    </form>
</div>

<div class="lab_results_container ui-widget ui-corner-all">
    <div class="h3">Configured LHD Recipients...</div>
    <table id="labResults">
        <thead>
            <tr>
                <th>Jurisdiction</th>
                <th>E-mail Addresses</th>
            </tr>
        </thead>
        <tbody>
<?php
/* @var $authClient Udoh\Emsa\Client\AppClientInterface */
$notificationJurisdictions = $authClient->getJurisdictions();
foreach ($notificationJurisdictions as $notificationJurisdictionId => $notificationJurisdictionName) {
    $thisRecipients = array();
    $thisRecipientsTmp = array();
    $thisRecipients = \Udoh\Emsa\Email\Utils::getNotificationEmailAddressesByJurisdiction($authClient, $notificationJurisdictionId);
    foreach ($thisRecipients as $thisEmail) {
        if (filter_var(trim($thisEmail), FILTER_VALIDATE_EMAIL)) {
            $thisRecipientsTmp[] = (string) filter_var(trim($thisEmail), FILTER_SANITIZE_EMAIL);
        }
    }

    echo '<tr>';
    echo '<td>' . Udoh\Emsa\Utils\DisplayUtils::xSafe((string) filter_var($notificationJurisdictionName, FILTER_SANITIZE_STRING), 'UTF-8', false) . '</td>';
    echo '<td>' . ( (count($thisRecipientsTmp) > 0) ? implode('<br>', $thisRecipientsTmp) : '<em style="color: #696969;">--</em>') . '</td>';
    echo '</tr>';
}
?>
        </tbody>
    </table>
    <br><br>

</div>
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
    $(function() {
        $(".add_jurisdiction_zip").button({
            icon: "ui-icon-elrplus"
        }).on("click", function() {
            var this_jID = $(this).val();
            var this_zipVal = $("#newzip_" + this_jID).val();
            if (this_zipVal === "") {
                alert("No Zip Code specified!\n\nPlease enter a valid Zip Code & try again.");
            } else {
                $("#new_zip").val(this_zipVal);
                $("#j_id").val(this_jID);
                $("#add_zip_form").trigger("submit");

            }
        });

        $(".edit_jurisdiction").button({
            icon: "ui-icon-elrpencil"
        }).on("click", function() {
            var this_mID = $(this).val();
            window.location.href = "<?php echo $webappBaseUrl; ?>?selected_page=6&submenu=30&cat=4&manage_id=" + this_mID;
        });

        $(".newzip").on("keyup", function(e) {
            if (e.keyCode === 27) {
                $(this).val("");
                $(this).trigger("blur");
            } else if (e.keyCode === 13) {
                $(this).siblings(".add_jurisdiction_zip").trigger("click");
            }

        });

        $(".edit_zip").checkboxradio();

        $("#delete_zips").button({
            icon: "ui-icon-elrclose"
        });

        $("#insert_zips").button({
            icon: "ui-icon-elrplus"
        });

    });
</script>

<?php
try {
    /* @var $emsaDbFactory \Udoh\Emsa\PDOFactory\PostgreSQL */
    $zipcodeDbConn = $emsaDbFactory->getConnection();
} catch (Throwable $e) {
    \Udoh\Emsa\Utils\ExceptionUtils::logException($e);
    \Udoh\Emsa\Utils\DisplayUtils::drawError('Unable to connect to database.', true);
}

if (isset($_POST['add_zip']) && filter_var($_POST['add_zip'], FILTER_VALIDATE_INT) && (intval($_POST['add_zip']) == 1)) {
    // manually add one-off zip code from main view
    if ((isset($_POST['new_zip']) && (strlen(trim($_POST['new_zip'])) > 0)) && (isset($_POST['j_id']) && filter_var($_POST['j_id'], FILTER_VALIDATE_INT) && (intval($_POST['j_id']) > 0))) {
        unset($add_dup_count);
        $add_dup_sql = 'SELECT count(id) AS counter FROM ' . $emsaDbSchemaPrefix . 'system_zip_codes WHERE system_district_id = $1 AND zipcode ILIKE $2;';
        $add_dup_rs = @pg_query_params($host_pa, $add_dup_sql, array(intval($_POST['j_id']), trim($_POST['new_zip'])));
        if ($add_dup_rs) {
            $add_dup_count = intval(@pg_fetch_result($add_dup_rs, 0, "counter"));
        }
        @pg_free_result($add_dup_rs);
        if (isset($add_dup_count)) {
            if ($add_dup_count == 0) {
                $add_sql = 'INSERT INTO ' . $emsaDbSchemaPrefix . 'system_zip_codes (zipcode, system_district_id) VALUES ($1, $2);';
                #debug echo $add_sql;
                $add_rs = @pg_query_params($host_pa, $add_sql, array(trim($_POST['new_zip']), intval($_POST['j_id'])));
                if ($add_rs) {
                    \Udoh\Emsa\Utils\DisplayUtils::drawHighlight("Zip Code '" . htmlspecialchars($emsaHTMLPurifier->purify(trim($_POST['new_zip']))) . "' successfully added!", "ui-icon-elrsuccess");
                } else {
                    \Udoh\Emsa\Utils\DisplayUtils::drawError("Could not add Zip Code to selected jurisdiction.");
                }
                @pg_free_result($add_rs);
            } else {
                \Udoh\Emsa\Utils\DisplayUtils::drawError("Could not add Zip Code.  Zip Code '" . htmlspecialchars($emsaHTMLPurifier->purify(trim($_POST['new_zip']))) . "' already exists for selected jurisdiction.");
            }
        } else {
            \Udoh\Emsa\Utils\DisplayUtils::drawError("Could not add Zip Code.  Unable to check for duplicates.");
        }
    } else {
        \Udoh\Emsa\Utils\DisplayUtils::drawError("Could not add Zip Code.  Missing/invald Zip Code or Jurisdiction specified.");
    }
} elseif (isset($_POST['edit_zip']) && is_array($_POST['edit_zip']) && (count($_POST['edit_zip']) > 0)) {
    // delete one-to-many zip codes for a selected jurisdiction
    if (isset($_POST['prune_jurisdiction']) && (intval($_POST['prune_jurisdiction']) > 0)) {
        // valid jurisdiction ID
        try {
            // seriously, PDO needs an array-to-IN() mapper...
            $pruneSql = "DELETE FROM ONLY system_zip_codes
                         WHERE system_district_id = ?
                         AND (";
            $pruneSqlTmp = '';
            for ($pruneBuilder = 0; $pruneBuilder < count($_POST['edit_zip']); $pruneBuilder++) {
                $pruneSqlTmp .= "(id = ?) OR ";
            }
            $pruneSql .= substr($pruneSqlTmp, 0, -4);
            $pruneSql .= ');';
            
            $pruneStmt = $zipcodeDbConn->prepare($pruneSql);
            $pruneStmt->bindValue(1, intval($_POST['prune_jurisdiction']), \PDO::PARAM_INT);
            $pruneBuilder = 1;
            foreach ($_POST['edit_zip'] as $pruneZipId) {
                $pruneBuilder++;
                $pruneStmt->bindValue($pruneBuilder, intval($pruneZipId), \PDO::PARAM_INT);
            }
            
            if ($pruneStmt->execute()) {
                \Udoh\Emsa\Utils\DisplayUtils::drawHighlight(intval($pruneStmt->rowCount()) . " Zip Codes deleted!", "ui-icon-elrsuccess");
            } else {
                \Udoh\Emsa\Utils\DisplayUtils::drawError("Unable to delete Zip Codes.");
            }
            
            $pruneStmt = null;
        } catch (Throwable $e) {
            \Udoh\Emsa\Utils\ExceptionUtils::logException($e);
            \Udoh\Emsa\Utils\DisplayUtils::drawError("Unable to delete Zip Codes.");
        }
    } else {
        \Udoh\Emsa\Utils\DisplayUtils::drawError("Unable to delete Zip Codes.  Invalid/missing Jurisdiction ID");
    }
} elseif (isset($_POST['grow_zips']) && (strlen(trim($_POST['grow_zips'])) > 0)) {
    if (isset($_POST['grow_jurisdiction']) && (intval($_POST['grow_jurisdiction']) > 0)) {
        // valid jurisdiction ID
        $grow_zip_arr = preg_split("/[\s]*[,][\s]*/", filter_var(trim($_POST['grow_zips']), FILTER_SANITIZE_STRING));
        $grow_sql = 'INSERT INTO ' . $emsaDbSchemaPrefix . 'system_zip_codes (zipcode, system_district_id) VALUES ';
        $growSqlArr = [];
        $growSqlTmp = [];
        foreach ($grow_zip_arr as $grow_zip) {
            $growSqlTmp[] = '($' . (count($growSqlArr)+1) . ', $' . (count($growSqlArr)+2) . ')';
            $growSqlArr[] = trim($grow_zip);
            $growSqlArr[] = intval($_POST['grow_jurisdiction']);
        }
        $grow_sql .= implode(', ', $growSqlTmp);
        $grow_sql .= ';';
        if (@pg_query_params($host_pa, $grow_sql, $growSqlArr)) {
            \Udoh\Emsa\Utils\DisplayUtils::drawHighlight("Zip Codes successfully added!", "ui-icon-elrsuccess");
        } else {
            \Udoh\Emsa\Utils\DisplayUtils::drawError("Unable to add Zip Codes.");
        }
    } else {
        \Udoh\Emsa\Utils\DisplayUtils::drawError("Unable to add Zip Codes.  Invalid/missing Jurisdiction ID");
    }
}

$zipcodeDbConn = null;
?>

<h1 class="elrhdg"><span class="ui-icon ui-icon-header ui-icon-emsamap"></span>Manage Zip Codes per Jurisdiction</h1>

<?php
if (isset($_GET['manage_id']) && filter_var($_GET['manage_id'], FILTER_VALIDATE_INT) && (intval($_GET['manage_id']) > 0)) {

    $manage_j_qry = 'SELECT health_district FROM ' . $emsaDbSchemaPrefix . 'system_districts WHERE id = $1;';
    $manage_district_name = @pg_fetch_result(@pg_query_params($host_pa, $manage_j_qry, array(intval($_GET['manage_id']))), 0, "health_district");
    ?>

    <style type="text/css">
        .ui-button { display: inline-block; margin: 5px; font-family: Consolas, 'Courier New', serif; font-size: 11pt !important; }
    </style>

    <div class="h3">Remove <?php echo htmlspecialchars($emsaHTMLPurifier->purify(trim($manage_district_name))); ?> Zip Codes</div>

    <div class="lab_results_container ui-widget ui-corner-all">
        <p>To remove Zip Codes from this jurisdiction, check the desired Zip Codes & click <em>Delete Selected Zip Codes</em>.</p>
        <form id="prune_jurisdiction_form" method="POST" action="<?php echo $webappBaseUrl; ?>?selected_page=6&submenu=30&cat=4">
    <?php
    // get selected zip codes for specified jurisdiction
    $manage_z_qry = 'SELECT id, zipcode FROM ' . $emsaDbSchemaPrefix . 'system_zip_codes WHERE system_district_id = $1 ORDER BY zipcode;';
    $manage_z_rs = @pg_query_params($host_pa, $manage_z_qry, array(intval($_GET['manage_id'])));
    if ($manage_z_rs) {
        echo "<fieldset><legend class='sr-only'>Current Zip Codes</legend>\n";
        while ($manage_z_row = pg_fetch_object($manage_z_rs)) {
            echo "<input type=\"checkbox\" class=\"edit_zip\" name=\"edit_zip[]\" id=\"edit_zip_" . intval($manage_z_row->id) . "\" value=\"" . intval($manage_z_row->id) . "\"><label for=\"edit_zip_" . intval($manage_z_row->id) . "\">" . htmlspecialchars($emsaHTMLPurifier->purify(trim($manage_z_row->zipcode))) . "</label>";
        }
        echo "</fieldset>\n";
        echo "<br><br><button id=\"delete_zips\" type=\"submit\">Delete Selected Zip Codes</button>";
    } else {
        \Udoh\Emsa\Utils\DisplayUtils::drawError("Unable to retrieve list of Zip Codes for " . htmlspecialchars($emsaHTMLPurifier->purify(trim($manage_district_name))) . ".");
    }
    ?>
            <input type="hidden" name="prune_jurisdiction" id="prune_jurisdiction" value="<?php echo intval($_GET['manage_id']); ?>">
        </form>
    </div>

    <div class="h3">Add New <?php echo htmlspecialchars($emsaHTMLPurifier->purify(trim($manage_district_name))); ?> Zip Codes</div>

    <div class="lab_results_container ui-widget ui-corner-all">
        <p>To add Zip Codes to this jurisdiction, enter Zip Codes (separate multiple Zip Codes with a comma) & click <em>Add New Zip Codes</em>.</p>
        <form id="grow_jurisdiction_form" method="POST" action="<?php echo $webappBaseUrl; ?>?selected_page=6&submenu=30&cat=4">
            <label class="sr-only" for="grow_zips">New Zip Codes</label>
            <textarea class="ui-corner-all" style="background-color: lightcyan; font-family: Consolas, 'Courier New'; width: 50%; height: 7em;" name="grow_zips" id="grow_zips"></textarea>
            <input type="hidden" name="grow_jurisdiction" id="grow_jurisdiction" value="<?php echo intval($_GET['manage_id']); ?>">
            <br><button id="insert_zips" type="submit">Add New Zip Codes</button>
        </form>

<?php } else { ?>

        <div class="lab_results_container ui-widget ui-corner-all">
            <table id="labResults">
                <thead>
                    <tr>
                        <th>Actions</th>
                        <th>Jurisdiction</th>
                        <th>Zip Codes</th>
                    </tr>
                </thead>
                <tbody>

    <?php
    $district_qry = sprintf("SELECT d.id, d.health_district, count(z.id) AS zip_count from %ssystem_districts d LEFT JOIN %ssystem_zip_codes z ON (d.id = z.system_district_id) GROUP BY d.id, d.health_district ORDER BY d.health_district;", $emsaDbSchemaPrefix, $emsaDbSchemaPrefix);
    $district_rs = @pg_query($host_pa, $district_qry);
    if ($district_rs) {
        while ($district_row = pg_fetch_object($district_rs)) {
            echo "<tr>";
            echo "<td style=\"white-space: nowrap;\" class=\"action_col\">";
            printf("<label class='sr-only' for='newzip_%s'>New Zip Code</label><input style=\"background-color: lightcyan; font-family: Consolas, 'Courier New', monospace; line-height: 1.9em; padding-left: 10px; width: 7em;\" type=\"text\" class=\"newzip ui-corner-all\" name=\"newzip_%s\" id=\"newzip_%s\" placeholder=\"New Zip Code\" maxlength=\"5\" />", intval($district_row->id), intval($district_row->id), intval($district_row->id));
            printf("<button class=\"add_jurisdiction_zip\" type=\"button\" value=\"%s\" title=\"Add a Zip Code to this jurisdiction\">Add Zip Code</button>", intval($district_row->id));
            printf("<button class=\"edit_jurisdiction\" type=\"button\" value=\"%s\" title=\"Manage Zip Codes for this juristiction\">Manage Zip Codes</button>", intval($district_row->id));
            echo "</td>";
            echo "<td>" . htmlspecialchars($emsaHTMLPurifier->purify($district_row->health_district)) . "</td>";
            echo "<td>" . htmlspecialchars($emsaHTMLPurifier->purify(intval($district_row->zip_count))) . "</td>";
            echo "</tr>";
        }
    } else {
        \Udoh\Emsa\Utils\DisplayUtils::drawError("Could not retrieve list of jurisdictions.");
    }

    pg_free_result($district_rs);
    ?>

                </tbody>
            </table>

        </div>

        <form id="add_zip_form" method="POST" action="<?php echo $webappBaseUrl; ?>?selected_page=6&submenu=30&cat=4">
            <input type="hidden" name="new_zip" id="new_zip">
            <input type="hidden" name="j_id" id="j_id">
            <input type="hidden" name="add_zip" id="add_zip" value="1">
        </form>

<?php }

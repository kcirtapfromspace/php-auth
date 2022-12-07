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

try {
    $adminDbFactory = new \Udoh\Emsa\PDOFactory\PostgreSQL($emsaDbHost, $emsaDbPort, $emsaDbName, $emsaDbUser, $emsaDbPass, $emsaDbSchemaPDO);
    $adminDbConn = $adminDbFactory->getConnection();
} catch (Throwable $ex) {
    Udoh\Emsa\Utils\ExceptionUtils::logException($ex);
    Udoh\Emsa\Utils\DisplayUtils::drawError('Unable to connect to the EMSA database.');
}

if (!class_exists('\Udoh\Emsa\Auth\Authenticator') || !\Udoh\Emsa\Auth\Authenticator::userHasPermission(\Udoh\Emsa\Auth\Authenticator::URIGHTS_ADMIN)) {
    ob_end_clean();
    header($_SERVER['SERVER_PROTOCOL'] . " 404 Not Found", TRUE, 404);
    exit;
}

include_once __DIR__ . '/includes/db_connect.php';  // creates PostgreSQL connection resource $host_pa for legacy pg_connect uses within Admin section

if ($navSubmenu == 31) {
    // errors & exceptions
    if (($navCat < 2) || ($navCat == 32) || ($navCat == 33)) {
        include __DIR__ . '/emsa/index.php';  // exceptions, unprocessed, locked, QA queues
    } elseif ($navCat == 2) {
        include __DIR__ . '/etasks/index.php';  // exception dashboard
    } elseif ($navCat == 31) {
        include __DIR__ . '/etasks/preprocessor_index.php';  // preprocessor exceptions
    } elseif ($navCat == 11) {
        include __DIR__ . '/manage/system_alerts.php';  // system alert log
    }
} elseif ($navSubmenu == 8 || $navSubmenu == 19) {
    include __DIR__ . '/emsa/index.php';  // QA Review queue
} elseif ($navSubmenu == 3) {
    if ($navCat == 1) {
        // master vocab
        if ($navSubcat == 8) {
            include __DIR__ . '/manage/vocab_valueset.php';  // value set management
        } elseif ($navSubcat == 10) {
            include __DIR__ . '/manage/rules_by_condition.php';  // rules by condition viewer
        } else {
            include __DIR__ . '/manage/vocabulary.php';  // LOINC/Condition/SNOMED management
        }
    } elseif ($navCat == 2) {
        // child vocab
        if ($navSubcat == 9) {
            include __DIR__ . '/manage/vocab_valueset.php';  // value set management
        } else {
            include __DIR__ . '/manage/vocabulary.php';  // LOINC/Condition/SNOMED management
        }
    } else {
        // tools
        if ($navSubcat == 6 && $processImport) {
            include __DIR__ . '/manage/import/import_handler.php';
        } elseif ($navSubcat == 6) {
            include __DIR__ . '/manage/import/import.php';
        } elseif ($navSubcat == 7) {
            include __DIR__ . '/manage/import/export.php';
        } else {
            include __DIR__ . '/manage/vocabulary_audit_log.php';  // vocabulary manager audit log
        }
    }
} elseif ($navSubmenu == 4) {
    if ($navCat == 2) {
        // data mapping
        if ($navSubcat == 2) {
            include __DIR__ . '/manage/structure_xml_master.php';
        } elseif ($navSubcat == 3) {
            include __DIR__ . '/manage/structure_rules_xml.php';
        } elseif ($navSubcat == 4) {
            include __DIR__ . '/manage/structure_xml_application.php';
        } elseif ($navSubcat == 5) {
            include __DIR__ . '/manage/structure_hl7.php';
        } elseif ($navSubcat == 7 && $processImport) {
            include __DIR__ . '/manage/import/structure_import_handler.php';
        } elseif ($navSubcat == 7) {
            include __DIR__ . '/manage/import/structure_import.php';
        } elseif ($navSubcat == 8) {
            include __DIR__ . '/manage/import/structure_export.php';
        } elseif ($navSubcat == 9) {
            include __DIR__ . '/manage/structure_xslt.php';
        }
    } elseif ($navCat == 6) {
        // vocab categories
        include __DIR__ . '/manage/structure_vocab_category.php';
    } elseif ($navCat == 9) {
        // vocab categories
        include __DIR__ . '/manage/structure_loinc_knitting.php';
    } elseif ($navCat == 10) {
        // hl7 data type definitions
        if ($navSubcat == 2) {
            include __DIR__ . '/manage/structure_hl7_datatypes.php';
        } else {
            include __DIR__ . '/manage/structure_hl7_datatypes_default.php';
        }
    } else {
        // reporter management
        include __DIR__ . '/manage/lab_manager.php';
    }
} elseif ($navSubmenu == 5) {
    // 'tools' submenu
    if ($navCat == 2) {
        include __DIR__ . '/manage/add_hl7_message.php';  // manually push an HL7 message into the ELR process
    } elseif ($navCat == 5) {
        include __DIR__ . '/manage/audit_log.php';  // EMSA audit log
    } elseif ($navCat == 6) {
        include __DIR__ . '/manage/pretty_xml.php';  // xml make-pretty-izer
    } elseif ($navCat == 7) {
        include __DIR__ . '/manage/hl7_review_generator.php';  // generate HL7 message review spreadsheet for onboarding
    } elseif ($navCat == 10) {
        include __DIR__ . '/manage/receipt_counter.php';  // denominator count query tool
    } elseif ($navCat == 11) {
        include __DIR__ . '/manage/original_messages_elr.php';  // raw ELR message list
    } elseif ($navCat == 12) {
        //include __DIR__ . '/manage/original_messages_sys.php';  // raw SyS message list
    }
} elseif ($navSubmenu == 6) {
    include __DIR__ . '/emsa/view_message.php';  // single-message viewer
} elseif ($navSubmenu == 7) {
    include __DIR__ . '/reports/index.php';  // reporting ui
} elseif ($navSubmenu == 9) {
    if ($navCat == 2) {
        include __DIR__ . '/manage/elrnotify_virtual_lhd.php';  // configure 'virtual' jurisdictions
    } elseif ($navCat == 3) {
        include __DIR__ . '/manage/elrnotify_types.php';  // configure notification types
    } elseif ($navCat == 7) {
        include __DIR__ . '/manage/elrnotify_rule_params.php';  // configure notification rule parameters
    } elseif ($navCat == 4) {
        include __DIR__ . '/manage/elrnotify_rules.php';  // configure notification rules
    } elseif ($navCat == 5) {
        include __DIR__ . '/manage/elrnotify_pending.php';  // view pending (unsent) notifications
    } elseif ($navCat == 6) {
        include __DIR__ . '/manage/elrnotify_log.php';  // view recently-sent notifications
    } else {
        include __DIR__ . '/manage/elrnotify_config.php';  // configure ELR notification settings
    }
} elseif ($navSubmenu == 30) {
    // 'settings' submenu
    if ($navCat == 4) {
        include __DIR__ . '/manage/zipcodes.php';  // manage jurisdiction zip codes
    } elseif ($navCat == 10) {
        include __DIR__ . '/manage/intakestats_config.php';  // configure intake monitoring notification
    } elseif ($navCat == 11) {
        include __DIR__ . '/roles/index2.php';  // new & improved roles
    } elseif ($navCat == 12) {
        include __DIR__ . '/manage/jurisdictions.php';  // manage jurisdictions
    } elseif ($navCat == 13) {
        // need to figure out what happened with this... seems to have gotten lost in all the svn->git migration & reorg
        // ...or maybe I just meant to write this & never did?  hmm... anyway, can't find that file anymore, so REMmed out for now
        // include __DIR__ . '/manage/risk_tree.php';  // manage risk assessment decision trees
    } elseif ($navCat == 14) {
        include __DIR__ . '/manage/pending_watchlist.php';  // manage jurisdictions
    } elseif ($navCat == 15) {
        include __DIR__ . '/manage/interstate.php';  // manage interstate reporting
    } else {
        include __DIR__ . '/roles/index2.php';  // fallback to new & improved roles
    }
} else {
    echo '<h1>Admin Menu</h1>';
}

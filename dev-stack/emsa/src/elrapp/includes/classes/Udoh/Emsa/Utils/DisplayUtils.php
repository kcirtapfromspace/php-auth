<?php

namespace Udoh\Emsa\Utils;

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

use DOMDocument;
use EmsaUtils;
use NavigationHeader;
use NavigationItem;
use PDO;
use Udoh\Emsa\Auth\Authenticator;

/**
 * Utilities for displaying and formatting data.
 * 
 * @package Udoh\Emsa\Utils
 *
 * @author Josh Ridderhoff <jridderhoff@utah.gov>
 */
class DisplayUtils
{

    /**
     * Error display widget.
     *
     * @param mixed $message Error message to display.
     * @param bool  $isFatal [Optional]<br>If <b>TRUE</b>, stop execution of the page.  Default <b>FALSE</b>.
     */
    public static function drawError($message, $isFatal = false)
    {
        echo '<div class="import_widget ui-widget import_error ui-state-error ui-corner-all" style="padding: 5px;">';
        echo '<span class="ui-icon ui-icon-elrerror" style="float: left; margin-right: .3em;"></span>';
        echo '<p style="margin-left: 20px;"><strong>' . $message . '</strong></p>';
        echo '</div>';

        if ($isFatal === true) {
            exit;
        }
    }

    /**
     * Highlighted message/info display widget.
     *
     * @param mixed  $message Message to display.
     * @param string $uiIcon  [Optional]<br>Class name of the jQueryUI icon to display.  Default "ui-icon-info".
     */
    public static function drawHighlight($message, $uiIcon = 'ui-icon-info')
    {
        echo '<div class="import_widget ui-widget import_error ui-state-highlight ui-corner-all" style="padding: 5px;">';
        echo '<span class="ui-icon ' . self::xSafe($uiIcon) . '" style="float: left; margin-right: .3em;"></span>';
        echo '<p style="margin-left: 20px;">' . $message . '</p>';
        echo '</div>';
    }

    /**
     * Get the names and IDs of all assigned EMSA roles for the current authenticated user.
     *
     * @param PDO   $dbConn      PDO connection to the EMSA database
     * @param array $emsaRoleIds User's assigned EMSA Role IDs (typically from
     *                           $_SESSION[EXPORT_SERVERNAME]['user_system_roles'])
     *
     * @return array Array containing IDs (as key) and names (as value) of EMSA roles for the current authenticated
     *               user.
     */
    public static function emsaRoleNames(PDO $dbConn, ?array $emsaRoleIds = [])
    {
        $roleNames = [];

        if (empty($emsaRoleIds)) {
            return $roleNames;
        }

        $sql = "SELECT id, name
                FROM auth_roles
                WHERE id IN (" . implode(',', array_map('intval', $emsaRoleIds)) . ")
                ORDER BY name;";

        foreach ($dbConn->query($sql, PDO::FETCH_OBJ) as $emsaRole) {
            $roleNames[(int)$emsaRole->id] = (string)$emsaRole->name;
        }

        return $roleNames;
    }

    /**
     * Escape text for XSS mitigation.
     *
     * @param string $rawData      Data to escape
     * @param string $encoding     [Optional]<br>Default UTF-8.
     * @param bool   $doubleEncode [Optional]<br>Default <b>TRUE</b>.
     *
     * @return string
     */
    public static function xSafe($rawData, $encoding = 'UTF-8', $doubleEncode = true)
    {
        if (EmsaUtils::emptyTrim($encoding)) {
            $encoding = 'UTF-8';
        }

        return htmlspecialchars($rawData, ENT_QUOTES | ENT_XHTML | ENT_SUBSTITUTE, $encoding, $doubleEncode);
    }

    /**
     * Escape text for XSS mitigation and echo it
     *
     * @param string $rawData      Data to escape and echo
     * @param string $encoding     [Optional]<br>Default UTF-8.
     * @param bool   $doubleEncode [Optional]<br>Default <b>TRUE</b>.
     */
    public static function xEcho($rawData, $encoding = 'UTF-8', $doubleEncode = true)
    {
        echo self::xSafe($rawData, $encoding, $doubleEncode);
    }

    /**
     * Escape text for use in an XML string.
     *
     * @param string $rawData  Data to escape
     * @param string $encoding [Optional]<br>Default UTF-8.
     *
     * @return string
     */
    public static function xmlSafe($rawData, $encoding = 'UTF-8')
    {
        if (EmsaUtils::emptyTrim($encoding)) {
            $encoding = 'UTF-8';
        }

        return htmlspecialchars($rawData, ENT_QUOTES | ENT_XML1 | ENT_SUBSTITUTE, $encoding);
    }

    /**
     * Accepts optional Last, First, and Middle names and returns them as a formatted string
     *
     * @param string $lastName   Last name
     * @param string $firstName  First name
     * @param string $middleName Middle name
     *
     * @return string Name in the format Last|--, First|--[, Middle].<br>If First, Last, and Middle names are all empty, an empty string is returned.
     */
    public static function formatNameLastFirstMiddle($lastName = null, $firstName = null, $middleName = null)
    {
        if (empty($lastName) && empty($firstName) && empty($middleName)) {
            return '';
        }
        
        $nameStrArr = array();
        
        if (!empty($lastName)) {
            $nameStrArr[] = trim($lastName);
        } else {
            $nameStrArr[] = '--';
        }
        
        if (!empty($firstName)) {
            $nameStrArr[] = trim($firstName);
        } else {
            $nameStrArr[] = '--';
        }
        
        if (!empty($middleName)) {
            $nameStrArr[] = trim($middleName);
        }
        
        return implode(', ', $nameStrArr);
    }

    /**
     * Takes up to two inputs and, if both options are present,
     * returns them as string with the second value in parentheses
     *
     * @param mixed $firstField  First value to display
     * @param mixed $secondField Second value to display
     *
     * @return string String in the format "[<i>$firstField</i>][ (<i>$secondField</i>)]".<br>Returns empty string if both fields are empty.
     */
    public static function optionalParentheses($firstField = null, $secondField = null)
    {
        $outputData = '';

        if (!EmsaUtils::emptyTrim($firstField)) {
            $outputData .= trim($firstField);
        }

        if (!EmsaUtils::emptyTrim($secondField)) {
            $outputData .= ' (' . trim($secondField) . ')';
        }

        return trim($outputData);
    }

    public static function formatPhoneNumber($phoneNumber = null, $areaCode = null)
    {
        $phoneNumberCombined = (string) $areaCode . (string) $phoneNumber;
        $cleanser = array(
            'tel:' => '',
            'fax:' => '',
            '(' => '',
            ')' => '',
            '-' => '',
            '.' => '',
            '+1' => '',
            ' ' => ''
        );

        // remove any formatting chars, including CDA prefixes
        $phoneNumberCleansed = (string) strtr($phoneNumberCombined, $cleanser);

        if (strlen($phoneNumberCleansed) === 7) {
            $cleanAreaCode = null;
            $cleanExchange = substr($phoneNumberCleansed, 0, 3);
            $cleanNumber = substr($phoneNumberCleansed, 3, 4);
        } elseif (strlen($phoneNumberCleansed) === 10) {
            $cleanAreaCode = substr($phoneNumberCleansed, 0, 3);
            $cleanExchange = substr($phoneNumberCleansed, 3, 3);
            $cleanNumber = substr($phoneNumberCleansed, 6, 4);
        } else {
            return $phoneNumberCleansed;
        }

        return (string) implode('-', array_filter(array($cleanAreaCode, $cleanExchange, $cleanNumber)));
    }

    /**
     * Formats an XML string to properly include line breaks & nesting of nodes for display.
     * 
     * @param string $xmlStr XML string to be formatted
     * 
     * @return string Formatted XML string, unescaped.  If <i>xmlStr</i> is empty or invalid, returns an empty string.
     */
    public static function formatXml($xmlStr = null)
    {
        if (EmsaUtils::emptyTrim($xmlStr)) {
            return '';
        }

        libxml_disable_entity_loader(true);

        $dom = new DOMDocument("1.0");
        $dom->preserveWhiteSpace = false;
        $dom->formatOutput = true;
        $loadSuccess = $dom->loadXML($xmlStr);

        libxml_disable_entity_loader(false);

        if ($loadSuccess) {
            return $dom->saveXML();
        } else {
            return '';
        }
    }

    /**
     * Draws the EMSA HTML header.
     *
     * @param PDO $dbConn            PDO connection to the EMSA database.
     * @param int $serverEnvironment Flag indicating current server environment.
     * @param int $selectedPage      [Optional]<br>Current 'selected page'.
     * @param int $subMenu           [Optional]<br>Current 'submenu'.
     * @param int $cat               [Optional]<br>Current 'category'.
     * @param int $subCat            [Optional]<br>Current 'sub-category'.
     */
    public static function drawHeader(PDO $dbConn, $serverEnvironment, $selectedPage = null, $subMenu = null, $cat = null, $subCat = null)
    {
        $pageTitle = null;

        switch ($selectedPage) {
            case 1:
                $pageTitle = 'Dashboard';
                break;
            case 2:
                $pageTitle = null;  // deprecated page?
                break;
            case 3:
                $pageTitle = ENTRY_NAME;
                break;
            case 4:
                $pageTitle = ASSIGNED_NAME;
                break;
            case 5:
                $pageTitle = GRAY_NAME;
                break;
            case 25:
                $pageTitle = SEMI_AUTO_NAME;
                break;
            case 26:
                $pageTitle = NEDSS_EXCEPTION_NAME;
                break;
            case 27:
                $pageTitle = OOS_NAME;
                break;
            case 6:
                $pageTitle = 'Admin';
                break;
            case 8:
                $pageTitle = 'Non-ELR Data';
                break;
        }

        if ($selectedPage == 6) {
            switch ($subMenu) {
                case 19:
                    $pageTitle = QA_NAME;
                    break;
                case 3:
                    $pageTitle = 'Vocabulary Management';
                    break;
                case 4:
                    $pageTitle = 'Structure Management';
                    break;
                case 7:
                    $pageTitle = 'Reporting';
                    break;
                case 9:
                    $pageTitle = 'E-mail Notification Settings';
                    break;
                case 30:
                    $pageTitle = 'Settings';
                    break;
                case 5:
                    $pageTitle = 'Administrative Tools';
                    break;
                case 31:
                    $pageTitle = 'Errors & Exceptions';
                    break;
                default:
                    $pageTitle = 'Administration';
                    break;
            }

            if ($subMenu == 3) {
                $pageTitle = 'Vocab - ';
                if ($cat == 1) {
                    if ($subCat == 8) {
                        $pageTitle .= 'Master Dictionary';
                    } elseif ($subCat == 2) {
                        $pageTitle .= 'Master Condition';
                    } elseif ($subCat == 3) {
                        $pageTitle .= 'Master SNOMED';
                    } elseif ($subCat == 13) {
                        $pageTitle .= 'PFGE Patterns';
                    } elseif ($subCat == 14) {
                        $pageTitle .= 'ICD Codes';
                    } elseif ($subCat == 10) {
                        $pageTitle .= 'Rules by Condition';
                    } else {
                        $pageTitle .= 'Master LOINC';
                    }
                } elseif ($cat == 2) {
                    if ($subCat == 9) {
                        $pageTitle .= 'Child Dictionary';
                    } elseif ($subCat == 4) {
                        $pageTitle .= 'Child SNOMED';
                    } else  {
                        $pageTitle .= 'Child LOINC';
                    }
                } elseif ($cat == 3) {
                    $pageTitle .= 'Tools';
                }
            }

            if ($subMenu == 4) {
                $pageTitle = 'Config - ';
                if ($cat == 1) {
                    $pageTitle .= 'Reporters';
                } elseif ($cat == 2) {
                    $pageTitle .= 'XML Mapping';
                } elseif ($cat == 9) {
                    $pageTitle .= 'LOINC-Based Knitting';
                } elseif ($cat == 6) {
                    $pageTitle .= 'Vocab Categories';
                } elseif ($cat == 10) {
                    $pageTitle .= 'HL7 Data Types';
                }
            }

            if ($subMenu == 31) {
                switch ($cat) {
                    case 32:
                        $pageTitle = 'Unprocessed Messages';
                        break;
                    case 33:
                        $pageTitle = 'Locked Messages';
                        break;
                    case 1:
                        $pageTitle = 'Exceptions';
                        break;
                    case 2:
                        $pageTitle = 'Bulk Exceptions';
                        break;
                    case 31:
                        $pageTitle = 'Preprocessor Exceptions';
                        break;
                    case 11:
                        $pageTitle = 'System Alerts';
                        break;
                    default:
                        $pageTitle = 'Errors & Exceptions';
                        break;
                }
            }
        }

        $headerTitle = implode(' - ', array('EMSA', $pageTitle));

        $appUrl = MAIN_URL . '/';

        echo "<!DOCTYPE html>\n";
        echo "<html lang='en'>\n";
        echo "<head>\n";
        echo "\t<meta http-equiv='Content-Type' content='text/html; charset=utf-8' />\n";
        echo "\t<title>$headerTitle</title>\n";
        echo "\t<link rel='shortcut icon' type='image/gif' href='{$appUrl}img/favicon.ico' />\n";
        echo "\t<link rel='stylesheet' type='text/css' href='{$appUrl}css/jquery-ui-1.13.0-ui-lightness.css' media='screen' />\n";
        echo "\t<link rel='stylesheet' type='text/css' href='https://fonts.googleapis.com/css?family=Oswald:500|Roboto:400,700|Open+Sans:400,600,700|Caveat' />\n";
        echo "\t<link rel='stylesheet' type='text/css' href='{$appUrl}css/emsa.css?v=5.03' media='all' />\n";
        echo "\t<link rel='stylesheet' type='text/css' href='{$appUrl}css/nav-layout.css?v=2.07' media='all' />\n";

        if (($selectedPage == 1) || (($selectedPage == 6) && (($subMenu == 7) || (($subMenu == 5) && ($cat == 10))))) {
            echo "\t<link rel='stylesheet' type='text/css' href='{$appUrl}css/reporting_common.css?v=1.4' />\n";
            echo "\t<link rel='stylesheet' type='text/css' href='{$appUrl}css/accessible-multiselect.css?v=1.32' />\n";
        }

        if (($selectedPage == 6) && ($subMenu == 3) && ($cat == 1) && ($subCat == 10)) {
            echo "\t<link rel='stylesheet' type='text/css' href='{$appUrl}css/rules_by_condition.css?v=1.5' />\n";
        }

        echo "\t<script type='text/javascript' src='{$appUrl}js/jquery-3.6.0.min.js'></script>\n";
        echo "\t<script type='text/javascript' src='{$appUrl}js/jquery-ui-1.13.0.min.js'></script>\n";
        echo "\t<script type='text/javascript' src='{$appUrl}js/elr.nav.js?v=2.22'></script>\n";
        echo "\t<script type='text/javascript' src='{$appUrl}js/accessible-listbox.min.js?v=1.98'></script>\n";
        echo "\t<script type='text/javascript' src='{$appUrl}js/w3c-menubar/MenubarLinks.js?v=1.55'></script>\n";
        echo "\t<script type='text/javascript' src='{$appUrl}js/w3c-menubar/MenubarItemLinks.js?v=1.55'></script>\n";
        echo "\t<script type='text/javascript' src='{$appUrl}js/w3c-menubar/PopupMenuLinks.js?v=1.55'></script>\n";
        echo "\t<script type='text/javascript' src='{$appUrl}js/w3c-menubar/PopupMenuItemLinks.js?v=1.55'></script>\n";


        if (($selectedPage == 1) || (($selectedPage == 6) && (($subMenu == 7) || (($subMenu == 5) && ($cat == 10))))) {
            echo "\t<script type='text/javascript' src='{$appUrl}js/accessible-multiselect.min.js?v=1.24'></script>\n";
        }

        // anti-clickjacking for legacy browsers
        // (handled for modern browsers via Content-Security-Policy and X-Frame-Options in app_config.php
        echo "\t<style id=\"antiClickjack\">body{display:none !important;}</style>\n";
        echo "\t<script type=\"text/javascript\">\n";
        echo "\t\tif (self === top) {\n";
        echo "\t\t\tvar antiClickjack = document.getElementById(\"antiClickjack\");\n";
        echo "\t\t\tantiClickjack.parentNode.removeChild(antiClickjack);\n";
        echo "\t\t} else {\n";
        echo "\t\t\ttop.location = self.location;\n";
        echo "\t\t}\n";
        echo "\t</script>\n";

        echo "</head>\n\n";

        echo "<body class='common'>\n";
        echo "\t<div id='container'>\n";
        echo "\t\t<header>\n";

        if (!EmsaUtils::emptyTrim($selectedPage)) {
            $masterVocabNav = new NavigationItem('Master Vocabulary', null, 6, 3, 1, 1, null);
            $masterVocabNav
                    ->addChild(new NavigationItem('Dictionary', null, 6, 3, 1, 8, null))
                    ->addChild(new NavigationItem('Condition', null, 6, 3, 1, 2, null))
                    ->addChild(new NavigationItem('LOINC', null, 6, 3, 1, 1, null))
                    ->addChild(new NavigationItem('SNOMED', null, 6, 3, 1, 3, null))
                    ->addChild(new NavigationItem('PFGE', null, 6, 3, 1, 13, null))
                    ->addChild(new NavigationItem('ICD', null, 6, 3, 1, 14, null));

            $masterVocabNav->addChild(new NavigationItem('Rules by Condition', null, 6, 3, 1, 10, null));

            $childVocabNav = new NavigationItem('Child Vocabulary', null, 6, 3, 2, 5, null);
            $childVocabNav
                    ->addChild(new NavigationItem('Dictionary', null, 6, 3, 2, 9, null))
                    ->addChild(new NavigationItem('LOINC', null, 6, 3, 2, 5, null))
                    ->addChild(new NavigationItem('SNOMED', null, 6, 3, 2, 4, null));

            $toolsVocabNav = new NavigationItem('Tools', null, 6, 3, 3, 6, null);
            $toolsVocabNav
                    ->addChild(new NavigationItem('Import', null, 6, 3, 3, 6, null))
                    ->addChild(new NavigationItem('Export', null, 6, 3, 3, 7, null))
                    ->addChild(new NavigationItem('Vocab Audit Log', null, 6, 3, 3, 9, null));

            $vocabNav = new NavigationItem('Vocabulary', null, 6, 3, 1, 1, null);
            $vocabNav
                    ->addChild($masterVocabNav)
                    ->addChild($childVocabNav)
                    ->addChild($toolsVocabNav);
            
            $hl7DataNav = new NavigationItem('HL7 Data Types', null, 6, 4, 10, 1, null);
            $hl7DataNav
                    ->addChild(new NavigationItem('Default Settings', null, 6, 4, 10, 1, null))
                    ->addChild(new NavigationItem('Reporter-Specific Settings', null, 6, 4, 10, 2, null));

            $mappingNav = new NavigationItem('XML Data Mapping', null, 6, 4, 2, 5, null);
            $mappingNav
                    ->addChild(new NavigationItem('Reporter (XPath)', null, 6, 4, 2, 5, null))
                    ->addChild(new NavigationItem('Reporter (XSLT)', null, 6, 4, 2, 9, null))
                    ->addChild(new NavigationItem('Master XML', null, 6, 4, 2, 2, null))
                    ->addChild(new NavigationItem('App XML', null, 6, 4, 2, 4, null))
                    ->addChild(new NavigationItem('Validation Rules', null, 6, 4, 2, 3, null))
                    ->addChild(new NavigationItem('Import', null, 6, 4, 2, 7, null))
                    ->addChild(new NavigationItem('Export', null, 6, 4, 2, 8, null));

            $structureNav = new NavigationItem('Structure', null, 6, 4, 1, null, null);
            $structureNav
                    ->addChild(new NavigationItem('Reporters', null, 6, 4, 1, null, null))
                    ->addChild($hl7DataNav)
                    ->addChild($mappingNav)
                    ->addChild(new NavigationItem('LOINC-based Knitting', null, 6, 4, 9, null, null))
                    ->addChild(new NavigationItem('Vocab Categories', null, 6, 4, 6, null, null));

            $settingsNav = new NavigationItem('Settings', null, 6, 30, 11, null, null);
            $settingsNav
                    ->addChild(new NavigationItem('User Roles', null, 6, 30, 11, null, null))
                    ->addChild(new NavigationItem('Jurisdictions', null, 6, 30, 12, null, null))
                    ->addChild(new NavigationItem('Zip Codes', null, 6, 30, 4, null, null))
                    ->addChild(new NavigationItem('Interstate Transmission', null, 6, 30, 15, null, null))
                    ->addChild(new NavigationItem('Pending Watchlist', null, 6, 30, 14, null, null))
                    ->addChild(new NavigationItem('Intake Monitoring Config', null, 6, 30, 10, null, null));

            $toolsNav = new NavigationItem('Tools', null, 6, 5, 5, null, null);

            $toolsNav
                    ->addChild(new NavigationItem('Audit Log', null, 6, 5, 5, null, null))
                    ->addChild(new NavigationItem('Raw ELR Messages', null, 6, 5, 11, null, null))
                    ->addChild(new NavigationItem('Raw SyS Messages', null, 6, 5, 12, null, null))
                    ->addChild(new NavigationItem('Message Receipt Counter', null, 6, 5, 10, null, null));

            if ($serverEnvironment === ELR_ENV_DEV) {
                $toolsNav
                        ->addChild(new NavigationItem('Manual HL7', null, 6, 5, 2, null, null))
                        ->addChild(new NavigationItem('Generate HL7', null, 6, 5, 8, null, null));
            } else {
                $toolsNav
                        ->addChild(new NavigationItem('Manually Add Message', null, 6, 5, 2, null, null));
            }

            $toolsNav
                    ->addChild(new NavigationItem('XML Formatter', null, 6, 5, 6, null, null))
                    ->addChild(new NavigationItem('HL7 Review Generator', null, 6, 5, 7, null, null));

            $notifyNav = new NavigationItem('E-mail Notification', null, 6, 9, 1, null, null);
            $notifyNav
                    ->addChild(new NavigationItem('Basic Settings', null, 6, 9, 1, null, null))
                    ->addChild(new NavigationItem('Virtual Jurisdictions', null, 6, 9, 2, null, null))
                    ->addChild(new NavigationItem('Notification Types', null, 6, 9, 3, null, null))
                    ->addChild(new NavigationItem('Rule Parameters', null, 6, 9, 7, null, null))
                    ->addChild(new NavigationItem('Rules', null, 6, 9, 4, null, null))
                    ->addChild(new NavigationItem('Pending Notifications', null, 6, 9, 5, null, null))
                    ->addChild(new NavigationItem('Log', null, 6, 9, 6, null, null));
            
            $navItems = [];
            $navItems[] = new NavigationItem('Dashboard', null, null, null, null, null, null);
            $navItems[] = (new NavigationItem('Queues', null, null, null, null, null, null))
                ->addChild(new NavigationItem('Entry', Authenticator::URIGHTS_ENTRY, 3, null, null, null, 17))
                ->addChild(new NavigationItem('Out of State', Authenticator::URIGHTS_OOS, 27, null, null, null, 28))
                ->addChild(new NavigationItem('Pending', Authenticator::URIGHTS_NEDSS_EXCEPTION, 26, null, null, null, 25))
                ->addChild(new NavigationItem('Semi-Automated Entry', Authenticator::URIGHTS_SEMI_AUTO, 25, null, null, null, 24))
                ->addChild(new NavigationItem('Assigned', Authenticator::URIGHTS_ASSIGNED, 4, null, null, null, 14))
                ->addChild(new NavigationItem('Graylist', Authenticator::URIGHTS_GRAY, 5, null, null, null, 2))
                ->addChild(new NavigationItem('QA Review', Authenticator::URIGHTS_ADMIN, 6, 19, null, null, 19));
            $navItems[] = (new NavigationItem('Errors & Exceptions', Authenticator::URIGHTS_ADMIN, 6, 31, 2, null, 3))
                ->addChild(new NavigationItem('Bulk Exceptions', Authenticator::URIGHTS_ADMIN, 6, 31, 2, null, null))
                ->addChild(new NavigationItem('Preprocessor Exceptions', Authenticator::URIGHTS_ADMIN, 6, 31, 31, null, null))
                ->addChild(new NavigationItem('Exceptions', Authenticator::URIGHTS_ADMIN, 6, 31, 1, null, 3))
                ->addChild(new NavigationItem('Unprocessed', Authenticator::URIGHTS_ADMIN, 6, 31, 32, null, 26))
                ->addChild(new NavigationItem('Locked', Authenticator::URIGHTS_ADMIN, 6, 31, 33, null, 27))
                ->addChild(new NavigationItem('System Alerts', Authenticator::URIGHTS_ADMIN, 6, 31, 11, null, null));
            $navItems[] = (new NavigationItem('Configuration', Authenticator::URIGHTS_ADMIN, 6, 1, 1, 1, 3))
                ->addChild($vocabNav)
                ->addChild($structureNav)
                ->addChild($toolsNav)
                ->addChild($settingsNav)
                ->addChild($notifyNav);
            $navItems[] = new NavigationItem('Reporting', Authenticator::URIGHTS_ADMIN, 6, 7, null, null, null);

            echo NavigationHeader::generateNav($navItems, MAIN_URL, $selectedPage, $subMenu, $cat, $subCat);
        }

        echo "\t\t\t<span id='logo'>EMSA2</span>\n";

        $headerRoles = DisplayUtils::emsaRoleNames($dbConn, $_SESSION[EXPORT_SERVERNAME]['user_system_roles']);

        if (is_array($headerRoles) && count($headerRoles) > 1) {
            // only draw role override selector if user has more than one role to begin with
            echo "\t\t\t<form name='change_elr_view' id='change_elr_view' method='POST'>\n";
            echo "\t\t\t\t<label for='override_role'>Switch user role:</label>\n";
            echo "\t\t\t\t<select id='override_role' name='override_role' class='ui-corner-all'>\n";
            echo "\t\t\t\t\t<option value='-1' selected>All assigned roles</option>\n";

            foreach ($headerRoles as $headerRoleId => $headerRoleName) {
                echo "\t\t\t\t\t" . '<option value="' . intval($headerRoleId) . '"' . ((isset($_SESSION[EXPORT_SERVERNAME]['override_user_role']) && (intval($_SESSION[EXPORT_SERVERNAME]['override_user_role']) == $headerRoleId)) ? ' selected' : '') . '>' . self::xSafe(trim($headerRoleName)) . '</option>' . "\n";
            }

            echo "\t\t\t\t</select>\n";
            echo "\t\t\t\t<button id='override_role_submit' title='Switch to selected user role' type='submit'>Switch</button>";
            echo "\t\t\t</form>\n";
        }

        echo "\t\t</header>\n";

        echo "\t\t<main id='content-wrapper-1' role='main'>\n";
    }

    /**
     * Draws the common EMSA footer.
     */
    public static function drawFooter()
    {
        $orgName = ORGANIZATION_NAME_SHORT;

        echo "\t\t</main>\n";
        echo "\t\t<footer role='contentinfo'>\n";
        echo "\t\t\t<p>EMSA &mdash; $orgName | <a href='?updateperm=1'>Reload Cached Data / Reset Permissions</a></p>\n";
        echo "\t\t</footer>\n";
        echo "\t</div>\n";
        echo "</body>\n";
        echo "</html>\n";
    }

}

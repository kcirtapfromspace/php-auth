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

use Udoh\Emsa\Constants\AppRecordType;
use Udoh\Emsa\Exceptions\PeopleSearchTooManyResults;
use Udoh\Emsa\Model\Address;
use Udoh\Emsa\UI\Queue\EmsaQueueList;
use Udoh\Emsa\Utils\AppClientUtils;
use Udoh\Emsa\Utils\CodedDataUtils;
use Udoh\Emsa\Utils\DisplayUtils;
use Udoh\Emsa\Utils\ExceptionUtils;

// prevent caching...
header('Expires: Mon, 26 Jul 1997 05:00:00 GMT');
header('Cache-Control: no-cache');
header('Pragma: no-cache');

include __DIR__ . '/../includes/app_config.php';

session_write_close(); // done writing to session; prevent blocking

try {
    $dbConn = $emsaDbFactory->getConnection();
} catch (Throwable $e) {
    ExceptionUtils::logException($e);
    DisplayUtils::drawError('Unable to connect to the EMSA database.', true);
}

$cleanMsgId = (int) filter_input(INPUT_POST, 'id', FILTER_SANITIZE_NUMBER_INT);

try {
    $emsaMessage = new EmsaMessage($dbConn, $appClientList, $cleanMsgId, false, true);
    $systemMessageId = $emsaMessage->getSystemMessageId();
    $cleanFirstName = $emsaMessage->getPerson()->getFirstName();
    $cleanLastName = $emsaMessage->getPerson()->getLastName();
    $cleanCondition = $emsaMessage->masterCondition;
    $cleanDateOfBirth = $emsaMessage->getPerson()->getDateOfBirth(true, "Y-m-d");
} catch (Throwable $e) {
    ExceptionUtils::logException($e);
    ob_end_clean();
    header($_SERVER['SERVER_PROTOCOL'] . " 400 Bad Request", TRUE, 400);
    exit;
}

$clean['type'] = (int) filter_input(INPUT_POST, 'type', FILTER_SANITIZE_NUMBER_INT);
$clean['selected_page'] = (int) filter_input(INPUT_POST, 'selected_page', FILTER_SANITIZE_NUMBER_INT);
$clean['submenu'] = (int) filter_input(INPUT_POST, 'submenu', FILTER_SANITIZE_NUMBER_INT);

// verify that message ID specified actually exists and that 
// logged-in user has permission to view message search is being conducted for...
try {
    $safeQueueId = EmsaUtils::getQueueIdByMessageId($dbConn, $systemMessageId);
    
    if (is_null($safeQueueId) || ((int) $safeQueueId <= 0)) {
        throw new Exception('Specified Message not found.');
    }
    
    $safeQueueList = new EmsaQueueList($dbConn, $appClientList);
    $safeCount = (int) $safeQueueList->getMessageCount($safeQueueId, null, null, EmsaQueueList::RESTRICT_SHOW_ALL, $systemMessageId);
    
    if ($safeCount !== 1) {
        throw new Exception('Unauthorized attempt to view People Search results');
    }
} catch (Throwable $e) {
    ExceptionUtils::logException($e);
    ob_end_clean();
    header($_SERVER['SERVER_PROTOCOL'] . " 400 Bad Request", TRUE, 400);
    exit;
}

if (($systemMessageId > 0) && (!empty($cleanFirstName) || !empty($cleanLastName) || !empty($cleanDateOfBirth))) {

    try {
        $resultsArray = AppClientUtils::getPeopleSearchResults($emsaMessage, $dbConn);
        $results = $resultsArray['results'];
        $autoMatches = $resultsArray['auto_match'];
        $fuzzyMatches = $resultsArray['fuzzy_match'];
        $noMatches = $resultsArray['no_match'];
        $eventsByPersonId = $resultsArray['events_by_person_id'];

        if (is_array($results)) {
            if (($clean['type'] === ENTRY_STATUS) || ($clean['type'] === OOS_STATUS) || ($clean['type'] === SEMI_AUTO_STATUS) || ($clean['type'] === QA_STATUS) || ($clean['type'] === NEDSS_EXCEPTION_STATUS) || ($clean['type'] === LOCKED_STATUS)) {
                // show "Assign Message" toolbar
                ?>
                <div class="ui-corner-all emsa_toolbar">
                    <?php
                    $form_action = MAIN_URL . '/index.php?selected_page=' . $clean['selected_page'];
                    if ($clean['submenu'] > 0) {
                        $form_action .= '&submenu=' . (int)$clean['submenu'];
                    }
                    $form_action .= '&type=' . (int)$clean['type'];
                    $form_action .= '&focus=' . (int)$systemMessageId;
                    ?>
                    <form method="POST" id="cmr_<?php echo $systemMessageId; ?>" action="<?php echo $form_action; ?>">
                        <input type="hidden" name="id" id="cmr_<?php echo $systemMessageId; ?>_id"
                               value="<?php echo $systemMessageId; ?>"/>
                        <input type="hidden" name="match_persons" id="match_persons_<?php echo $systemMessageId; ?>"
                               value=""/>
                        <input type="hidden" name="target" id="cmrtarget_<?php echo $systemMessageId; ?>" value=""/>
                        <input type="hidden" name="emsa_action" id="emsa_cmraction_<?php echo $systemMessageId; ?>"
                               value=""/>
                        <input type="hidden" name="info" id="cmrinfo_<?php echo $systemMessageId; ?>" value=""/>
                        <input type="hidden" name="override_surveillance"
                               id="cmroverride_surveillance_<?php echo $systemMessageId; ?>" value=""/>

                        <span class="emsa_toolbar_label">Assign Current Message
                            to <?php DisplayUtils::xEcho((string)$emsaMessage->getAppClient()->getAppName()); ?>
                            :</span>
                        <button type="button"
                                title="Create a new person with this information (no matching persons found)"
                                class="add_new_cmr" value="<?php echo $systemMessageId; ?>">Create New Person
                        </button>
                        <div class="emsa_toolbar_separator"></div>
                        <button type="button" title="Add this information to one of the selected person(s) below"
                                class="update_cmr" value="<?php echo $systemMessageId; ?>">Use Selected Person(s)
                        </button>
                        <?php if (($clean['type'] === ENTRY_STATUS) || ($clean['type'] === OOS_STATUS) || ($clean['type'] === QA_STATUS) || ($clean['type'] === NEDSS_EXCEPTION_STATUS) || ($clean['type'] === LOCKED_STATUS)) { ?>
                            <div style="display: block; margin-top: 5px; padding-top: 5px; border-top: 1px #aaaaaa dotted;">
                                <span class="emsa_toolbar_label">Assign Current &amp; Related Messages
                                    to <?php DisplayUtils::xEcho((string)$emsaMessage->getAppClient()->getAppName()); ?>
                                    :</span>
                                <button type="button"
                                        title="Create a new person with the information from this and other related messages (no matching persons found)"
                                        class="add_bulk_new_cmr" value="<?php echo $systemMessageId; ?>">Create New
                                    Person
                                </button>
                                <div class="emsa_toolbar_separator"></div>
                                <button type="button"
                                        title="Add information from this and other related messages to one of the selected person(s) below"
                                        class="update_bulk_cmr" value="<?php echo $systemMessageId; ?>">Use Selected
                                    Person(s)
                                </button>
                            </div>
                        <?php } ?>
                    </form>
                </div>
            <?php } ?>
            <div class="h3">Current Person:</div>
            <table class="emsa_search_results_header">
                <tr>
                    <th style="width: 24%;">Name</th>
                    <th style="width: 6%;">Gender</th>
                    <th style="width: 9%;">D.O.B.</th>
                    <th style="width: 37%;">Address</th>
                    <th style="width: 24%;">Contact Info</th>
                </tr>
                <tr>
                    <td class="inactive"><?php echo DisplayUtils::xSafe(DisplayUtils::formatNameLastFirstMiddle($emsaMessage->getPerson()->getLastName(), $emsaMessage->getPerson()->getFirstName(), $emsaMessage->getPerson()->getMiddleName()), "UTF-8", false); ?></td>
                    <td class="inactive"><?php echo DisplayUtils::xSafe($emsaMessage->getPerson()->getGender(), "UTF-8", false); ?></td>
                    <td class="inactive"><?php echo DisplayUtils::xSafe($emsaMessage->getPerson()->getDateOfBirth(true, "m/d/Y"), "UTF-8", false); ?></td>
                    <td class="inactive"><?php
                        echo Address::formatAddressSingleLine(
                                $emsaMessage->getMasterXML()->person->street_name ?? null,
                                $emsaMessage->getMasterXML()->person->unit ?? null,
                                $emsaMessage->getMasterXML()->person->city ?? null,
                                $emsaMessage->getMasterXML()->person->state ?? null,
                                $emsaMessage->getMasterXML()->person->zip ?? null,
                                $emsaMessage->getMasterXML()->person->county ?? null
                        );
                    ?></td>
                    <td class="inactive">
                        <?php
                        echo htmlspecialchars($emsaHTMLPurifier->purify(((strlen($emsaMessage->getMasterXML()->person->phone) > 0) ? DisplayUtils::formatPhoneNumber($emsaMessage->getMasterXML()->person->phone, $emsaMessage->getMasterXML()->person->area_code) : "")));
                        if (!empty($emsaMessage->getMasterXML()->person->email)) {
                            echo "<br>";
                            echo htmlspecialchars($emsaHTMLPurifier->purify($emsaMessage->getMasterXML()->person->email));
                        }
                        ?>
                    </td>
                </tr>
            </table>

            <br><br>
            <div class="h3">People from Search Results:</div>
            <table class="emsa_search_results_header">
                <thead>
                <tr>
                    <th style="width: 24%;">Name</th>
                    <th style="width: 6%;">Gender</th>
                    <th style="width: 9%;">D.O.B.</th>
                    <th style="width: 37%;">Addresses</th>
                    <th style="width: 13%;">Contact Info</th>
                    <th style="width: 11%;">Match Quality</th>
                </tr>
                </thead>
            </table>
            <div class="emsa_sr_scroller">
                <table class="emsa_search_results">
                    <tbody>
                    <?php
                    if (is_array($results) && (count($results) > 0)) {
                        if (is_array($autoMatches) && (count($autoMatches) > 0)) {
                            foreach ($autoMatches as $autoMatchPersonID) {
                                $resultItem = $results[$autoMatchPersonID];
                                if (is_array($resultItem) && array_key_exists('mrn', $resultItem)) {
                                    $mrnList = $resultItem['mrn'];
                                } else {
                                    $mrnList = [];
                                }
                                ?>
                                <tr class="event_match_person person_match_found"
                                    id="person_match_<?php echo $systemMessageId; ?>__<?php echo (int)$autoMatchPersonID; ?>">
                                    <td style="width: 24%;">
                                        <input aria-label="Select this person for assignment" type="checkbox"
                                               name="use_person[<?php echo $systemMessageId; ?>][]"
                                               id="use_person_<?php echo $systemMessageId; ?>_<?php echo (int)$autoMatchPersonID; ?>"
                                               value="<?php echo (int)$autoMatchPersonID; ?>"/>
                                        <?php echo DisplayUtils::xSafe($resultItem['full_name']); ?>
                                    </td>
                                    <td style="width: 6%;"><?php echo DisplayUtils::xSafe($resultItem['sex']); ?></td>
                                    <td style="width: 9%;"><?php echo DisplayUtils::xSafe(trim($resultItem['birth_date'])); ?></td>
                                    <td style="width: 37%;">
                                        <?php
                                        if (isset($resultItem['addresses']) && is_array($resultItem['addresses'])) {
                                            /* @var $address_item Address */
                                            foreach ($resultItem['addresses'] as $address_item) {
                                                echo Address::formatAddressSingleLine($address_item->getStreet(), $address_item->getUnitNumber(), $address_item->getCity(), $address_item->getState(), $address_item->getPostalCode(), CodedDataUtils::getCodeDescriptionFromCodedValue($emsaMessage->getAppClient(), 'county', $address_item->getCounty()));
                                                echo '<br>';
                                            }
                                        }
                                        ?>
                                    </td>
                                    <td style="width: 13%;">
                                        <?php
                                        if (isset($resultItem['telephones']) && is_array($resultItem['telephones'])) {
                                            foreach ($resultItem['telephones'] as $telephoneItem) {
                                                ?>
                                                <?php echo DisplayUtils::xSafe($telephoneItem); ?><br>
                                                <?php
                                            }
                                        }
                                        if (isset($resultItem['email_addresses']) && is_array($resultItem['email_addresses'])) {
                                            foreach ($resultItem['email_addresses'] as $emailItem) {
                                                ?>
                                                <?php echo DisplayUtils::xSafe($emailItem); ?><br>
                                                <?php
                                            }
                                        }
                                        ?>
                                    </td>
                                    <td style="width: 11%;">
                                        <?php
                                        if ($resultItem['real_score'] == 100.0) {
                                            $starImg = MAIN_URL . "/img/rating-5star.png";
                                            $starAlt = "5 Stars";
                                        } elseif ($resultItem['real_score'] >= 90.0) {
                                            $starImg = MAIN_URL . "/img/rating-4point5star.png";
                                            $starAlt = "4.5 Stars";
                                        } elseif ($resultItem['real_score'] >= 80.0) {
                                            $starImg = MAIN_URL . "/img/rating-4star.png";
                                            $starAlt = "4 Stars";
                                        } elseif ($resultItem['real_score'] >= 70.0) {
                                            $starImg = MAIN_URL . "/img/rating-3point5star.png";
                                            $starAlt = "3.5 Stars";
                                        } elseif ($resultItem['real_score'] >= 60.0) {
                                            $starImg = MAIN_URL . "/img/rating-3star.png";
                                            $starAlt = "3 Stars";
                                        } elseif ($resultItem['real_score'] >= 50.0) {
                                            $starImg = MAIN_URL . "/img/rating-2point5star.png";
                                            $starAlt = "2.5 Stars";
                                        } elseif ($resultItem['real_score'] >= 40.0) {
                                            $starImg = MAIN_URL . "/img/rating-2star.png";
                                            $starAlt = "2 Stars";
                                        } elseif ($resultItem['real_score'] >= 30.0) {
                                            $starImg = MAIN_URL . "/img/rating-1point5star.png";
                                            $starAlt = "1.5 Stars";
                                        } elseif ($resultItem['real_score'] >= 20.0) {
                                            $starImg = MAIN_URL . "/img/rating-1star.png";
                                            $starAlt = "1 Star";
                                        } elseif ($resultItem['real_score'] >= 10.0) {
                                            $starImg = MAIN_URL . "/img/rating-0point5star.png";
                                            $starAlt = "0.5 Stars";
                                        } else {
                                            $starImg = MAIN_URL . "/img/rating-0star.png";
                                            $starAlt = "0 Stars";
                                        }

                                        echo '<div title="Automatic Match! (Actual Score: ' . (int)$resultItem['real_score'] . '%)" style="display: inline;">';
                                        echo "<img src='$starImg' alt='$starAlt' width='80' height='16'>";
                                        echo '</div>';
                                        ?>
                                    </td>
                                </tr>
                                <tr class="event_match_events first_event_match event_match_events_<?php echo $systemMessageId; ?>__<?php echo (int)$autoMatchPersonID; ?>"
                                    id="event_match_events_<?php echo $systemMessageId; ?>__<?php echo (int)$autoMatchPersonID; ?>__editperson">
                                    <td colspan="6" style="width: 100%;">
                                        &bull; <a style="font-weight: bold; color: navy;"
                                                  href="<?php echo $emsaMessage->getAppClient()->getAppLinkToPerson($autoMatchPersonID, true); ?>"
                                                  target="_blank">View Person</a> (Person ID# <?php echo (int)$autoMatchPersonID . AppClientUtils::drawMRNList($mrnList); ?>)
                                    </td>
                                </tr>
                                <?php
                                unset($first_event);
                                $first_event = true;
                                if (!empty($eventsByPersonId) && array_key_exists((int)$autoMatchPersonID, $eventsByPersonId)) {
                                    foreach ($eventsByPersonId[(int)$autoMatchPersonID] as $event_by_person_key => $event_by_person_data) {
                                        ?>
                                        <tr class="event_match_events event_match_events_<?php echo $systemMessageId; ?>__<?php echo (int)$autoMatchPersonID; ?>"
                                            id="event_match_events_<?php echo $systemMessageId; ?>__<?php echo (int)$autoMatchPersonID; ?>__<?php echo (int)$event_by_person_key; ?>">
                                            <td colspan="6" style="width: 100%;">
                                                &bull; <a style="font-weight: bold; color: navy;"
                                                          href="<?php echo $emsaMessage->getAppClient()->getAppLinkToRecord($event_by_person_data['event_type'], $event_by_person_data['record_number'], true, $event_by_person_data['event_id']); ?>"
                                                          target="_blank">Record# <?php echo $event_by_person_data['record_number']; ?></a>
                                                (Event ID# <?php echo (int)$event_by_person_data['event_id']; ?>) <?php echo DisplayUtils::xSafe((string)$event_by_person_data['disease_name'], "UTF-8") . ' ' . (($event_by_person_data['event_type'] === AppRecordType::MORBIDITY_EVENT) ? 'Morbidity Event' : 'Contact Event') . ' (' . $event_by_person_data['event_date'] . ') '; ?>
                                            </td>
                                        </tr>
                                        <?php
                                        $first_event = false;
                                    }
                                }
                            }
                        }

                        if (is_array($fuzzyMatches) && (count($fuzzyMatches) > 0)) {
                            foreach ($fuzzyMatches as $fuzzyMatchPersonID) {
                                $resultItem = $results[$fuzzyMatchPersonID];
                                if (is_array($resultItem) && array_key_exists('mrn', $resultItem)) {
                                    $mrnList = $resultItem['mrn'];
                                } else {
                                    $mrnList = [];
                                }
                                ?>
                                <tr class="event_match_person"
                                    id="person_match_<?php echo $systemMessageId; ?>__<?php echo (int)$fuzzyMatchPersonID; ?>">
                                    <td style="width: 24%;">
                                        <input aria-label="Select this person for assignment" type="checkbox"
                                               name="use_person[<?php echo $systemMessageId; ?>][]"
                                               id="use_person_<?php echo $systemMessageId; ?>_<?php echo (int)$fuzzyMatchPersonID; ?>"
                                               value="<?php echo (int)$fuzzyMatchPersonID; ?>"/>
                                        <?php echo DisplayUtils::xSafe($resultItem['full_name']); ?>
                                    </td>
                                    <td style="width: 6%;"><?php echo DisplayUtils::xSafe($resultItem['sex']); ?></td>
                                    <td style="width: 9%;"><?php echo DisplayUtils::xSafe(trim($resultItem['birth_date'])); ?></td>
                                    <td style="width: 37%;">
                                        <?php
                                        if (isset($resultItem['addresses']) && is_array($resultItem['addresses'])) {
                                            /* @var $address_item Address */
                                            foreach ($resultItem['addresses'] as $address_item) {
                                                echo Address::formatAddressSingleLine($address_item->getStreet(), $address_item->getUnitNumber(), $address_item->getCity(), $address_item->getState(), $address_item->getPostalCode(), CodedDataUtils::getCodeDescriptionFromCodedValue($emsaMessage->getAppClient(), 'county', $address_item->getCounty()));
                                                echo '<br>';
                                            }
                                        }
                                        ?>
                                    </td>
                                    <td style="width: 13%;">
                                        <?php
                                        if (isset($resultItem['telephones']) && is_array($resultItem['telephones'])) {
                                            foreach ($resultItem['telephones'] as $telephoneItem) {
                                                ?>
                                                <?php echo DisplayUtils::xSafe($telephoneItem); ?><br>
                                                <?php
                                            }
                                        }
                                        if (isset($resultItem['email_addresses']) && is_array($resultItem['email_addresses'])) {
                                            foreach ($resultItem['email_addresses'] as $emailItem) {
                                                ?>
                                                <?php echo DisplayUtils::xSafe($emailItem); ?><br>
                                                <?php
                                            }
                                        }
                                        ?>
                                    </td>
                                    <td style="width: 11%;">
                                        <?php
                                        if ($resultItem['real_score'] == 100.0) {
                                            $starImg = MAIN_URL . "/img/rating-5star.png";
                                            $starAlt = "5 Stars";
                                        } elseif ($resultItem['real_score'] >= 90.0) {
                                            $starImg = MAIN_URL . "/img/rating-4point5star.png";
                                            $starAlt = "4.5 Stars";
                                        } elseif ($resultItem['real_score'] >= 80.0) {
                                            $starImg = MAIN_URL . "/img/rating-4star.png";
                                            $starAlt = "4 Stars";
                                        } elseif ($resultItem['real_score'] >= 70.0) {
                                            $starImg = MAIN_URL . "/img/rating-3point5star.png";
                                            $starAlt = "3.5 Stars";
                                        } elseif ($resultItem['real_score'] >= 60.0) {
                                            $starImg = MAIN_URL . "/img/rating-3star.png";
                                            $starAlt = "3 Stars";
                                        } elseif ($resultItem['real_score'] >= 50.0) {
                                            $starImg = MAIN_URL . "/img/rating-2point5star.png";
                                            $starAlt = "2.5 Stars";
                                        } elseif ($resultItem['real_score'] >= 40.0) {
                                            $starImg = MAIN_URL . "/img/rating-2star.png";
                                            $starAlt = "2 Stars";
                                        } elseif ($resultItem['real_score'] >= 30.0) {
                                            $starImg = MAIN_URL . "/img/rating-1point5star.png";
                                            $starAlt = "1.5 Stars";
                                        } elseif ($resultItem['real_score'] >= 20.0) {
                                            $starImg = MAIN_URL . "/img/rating-1star.png";
                                            $starAlt = "1 Star";
                                        } elseif ($resultItem['real_score'] >= 10.0) {
                                            $starImg = MAIN_URL . "/img/rating-0point5star.png";
                                            $starAlt = "0.5 Stars";
                                        } else {
                                            $starImg = MAIN_URL . "/img/rating-0star.png";
                                            $starAlt = "0 Stars";
                                        }

                                        echo '<div title="Match Score: ' . (int)$resultItem['real_score'] . '%" style="display: inline;">';
                                        echo "<img src='$starImg' alt='$starAlt' width='80' height='16'>";
                                        echo '</div>';
                                        ?>
                                    </td>
                                </tr>
                                <tr class="event_match_events first_event_match event_match_events_<?php echo $systemMessageId; ?>__<?php echo (int)$fuzzyMatchPersonID; ?>"
                                    id="event_match_events_<?php echo $systemMessageId; ?>__<?php echo (int)$fuzzyMatchPersonID; ?>__editperson">
                                    <td colspan="6" style="width: 100%;">
                                        &bull; <a style="font-weight: bold; color: navy;"
                                                  href="<?php echo $emsaMessage->getAppClient()->getAppLinkToPerson($fuzzyMatchPersonID, true); ?>"
                                                  target="_blank">View Person</a> (Person ID# <?php echo (int)$fuzzyMatchPersonID . AppClientUtils::drawMRNList($mrnList); ?>)
                                    </td>
                                </tr>
                                <?php
                                unset($first_event);
                                $first_event = true;
                                if (!empty($eventsByPersonId) && array_key_exists((int)$fuzzyMatchPersonID, $eventsByPersonId)) {
                                    foreach ($eventsByPersonId[(int)$fuzzyMatchPersonID] as $event_by_person_key => $event_by_person_data) {
                                        ?>
                                        <tr class="event_match_events event_match_events_<?php echo $systemMessageId; ?>__<?php echo (int)$fuzzyMatchPersonID; ?>"
                                            id="event_match_events_<?php echo $systemMessageId; ?>__<?php echo (int)$fuzzyMatchPersonID; ?>__<?php echo (int)$event_by_person_key; ?>">
                                            <td colspan="6" style="width: 100%;">
                                                &bull; <a style="font-weight: bold; color: navy;"
                                                          href="<?php echo $emsaMessage->getAppClient()->getAppLinkToRecord($event_by_person_data['event_type'], $event_by_person_data['record_number'], true, $event_by_person_data['event_id']); ?>"
                                                          target="_blank">Record# <?php echo $event_by_person_data['record_number']; ?></a>
                                                (Event ID# <?php echo (int)$event_by_person_data['event_id']; ?>) <?php echo DisplayUtils::xSafe((string)$event_by_person_data['disease_name'], "UTF-8") . ' ' . (($event_by_person_data['event_type'] === AppRecordType::MORBIDITY_EVENT) ? 'Morbidity Event' : 'Contact Event') . ' (' . $event_by_person_data['event_date'] . ') '; ?>
                                            </td>
                                        </tr>
                                        <?php
                                        $first_event = false;
                                    }
                                }
                            }
                        }

                        if (is_array($noMatches) && (count($noMatches) > 0)) {
                            foreach ($noMatches as $noMatchPersonID) {
                                $resultItem = $results[$noMatchPersonID];
                                if (is_array($resultItem) && array_key_exists('mrn', $resultItem)) {
                                    $mrnList = $resultItem['mrn'];
                                } else {
                                    $mrnList = [];
                                }
                                ?>
                                <tr class="event_match_person person_nomatch"
                                    id="person_match_<?php echo $systemMessageId; ?>__<?php echo (int)$noMatchPersonID; ?>">
                                    <td style="width: 24%;">
                                        <input aria-label="Select this person for assignment" type="checkbox"
                                               name="use_person[<?php echo $systemMessageId; ?>][]"
                                               id="use_person_<?php echo $systemMessageId; ?>_<?php echo (int)$noMatchPersonID; ?>"
                                               value="<?php echo (int)$noMatchPersonID; ?>"/>
                                        <?php echo DisplayUtils::xSafe($resultItem['full_name']); ?>
                                    </td>
                                    <td style="width: 6%;"><?php echo DisplayUtils::xSafe($resultItem['sex']); ?></td>
                                    <td style="width: 9%;"><?php echo DisplayUtils::xSafe(trim($resultItem['birth_date'])); ?></td>
                                    <td style="width: 37%;">
                                        <?php
                                        if (isset($resultItem['addresses']) && is_array($resultItem['addresses'])) {
                                            /* @var $address_item Address */
                                            foreach ($resultItem['addresses'] as $address_item) {
                                                echo Address::formatAddressSingleLine($address_item->getStreet(), $address_item->getUnitNumber(), $address_item->getCity(), $address_item->getState(), $address_item->getPostalCode(), CodedDataUtils::getCodeDescriptionFromCodedValue($emsaMessage->getAppClient(), 'county', $address_item->getCounty()));
                                                echo '<br>';
                                            }
                                        }
                                        ?>
                                    </td>
                                    <td style="width: 13%;">
                                        <?php
                                        if (isset($resultItem['telephones']) && is_array($resultItem['telephones'])) {
                                            foreach ($resultItem['telephones'] as $telephoneItem) {
                                                ?>
                                                <?php echo DisplayUtils::xSafe($telephoneItem); ?><br>
                                                <?php
                                            }
                                        }
                                        if (isset($resultItem['email_addresses']) && is_array($resultItem['email_addresses'])) {
                                            foreach ($resultItem['email_addresses'] as $emailItem) {
                                                ?>
                                                <?php echo DisplayUtils::xSafe($emailItem); ?><br>
                                                <?php
                                            }
                                        }
                                        ?>
                                    </td>
                                    <td style="width: 11%;">
                                        <?php
                                        if ($resultItem['real_score'] == 100.0) {
                                            $starImg = MAIN_URL . "/img/rating-5star.png";
                                            $starAlt = "5 Stars";
                                        } elseif ($resultItem['real_score'] >= 90.0) {
                                            $starImg = MAIN_URL . "/img/rating-4point5star.png";
                                            $starAlt = "4.5 Stars";
                                        } elseif ($resultItem['real_score'] >= 80.0) {
                                            $starImg = MAIN_URL . "/img/rating-4star.png";
                                            $starAlt = "4 Stars";
                                        } elseif ($resultItem['real_score'] >= 70.0) {
                                            $starImg = MAIN_URL . "/img/rating-3point5star.png";
                                            $starAlt = "3.5 Stars";
                                        } elseif ($resultItem['real_score'] >= 60.0) {
                                            $starImg = MAIN_URL . "/img/rating-3star.png";
                                            $starAlt = "3 Stars";
                                        } elseif ($resultItem['real_score'] >= 50.0) {
                                            $starImg = MAIN_URL . "/img/rating-2point5star.png";
                                            $starAlt = "2.5 Stars";
                                        } elseif ($resultItem['real_score'] >= 40.0) {
                                            $starImg = MAIN_URL . "/img/rating-2star.png";
                                            $starAlt = "2 Stars";
                                        } elseif ($resultItem['real_score'] >= 30.0) {
                                            $starImg = MAIN_URL . "/img/rating-1point5star.png";
                                            $starAlt = "1.5 Stars";
                                        } elseif ($resultItem['real_score'] >= 20.0) {
                                            $starImg = MAIN_URL . "/img/rating-1star.png";
                                            $starAlt = "1 Star";
                                        } elseif ($resultItem['real_score'] >= 10.0) {
                                            $starImg = MAIN_URL . "/img/rating-0point5star.png";
                                            $starAlt = "0.5 Stars";
                                        } else {
                                            $starImg = MAIN_URL . "/img/rating-0star.png";
                                            $starAlt = "0 Stars";
                                        }

                                        echo '<div title="Not a Match (Actual Score: ' . (int)$resultItem['real_score'] . '%)" style="display: inline;">';
                                        echo "<img src='$starImg' alt='$starAlt' width='80' height='16'>";
                                        echo '</div>';
                                        ?>
                                    </td>
                                </tr>
                                <tr class="event_match_events first_event_match event_match_events_<?php echo $systemMessageId; ?>__<?php echo (int)$noMatchPersonID; ?>"
                                    id="event_match_events_<?php echo $systemMessageId; ?>__<?php echo (int)$noMatchPersonID; ?>__editperson">
                                    <td colspan="6" style="width: 100%;">
                                        &bull; <a style="font-weight: bold; color: navy;"
                                                  href="<?php echo $emsaMessage->getAppClient()->getAppLinkToPerson($noMatchPersonID, true); ?>"
                                                  target="_blank">View Person</a> (Person ID# <?php echo (int)$noMatchPersonID . AppClientUtils::drawMRNList($mrnList); ?>)
                                    </td>
                                </tr>
                                <?php
                                unset($first_event);
                                $first_event = true;
                                if (!empty($eventsByPersonId) && array_key_exists((int)$noMatchPersonID, $eventsByPersonId)) {
                                    foreach ($eventsByPersonId[(int)$noMatchPersonID] as $event_by_person_key => $event_by_person_data) {
                                        ?>
                                        <tr class="event_match_events event_match_events_<?php echo $systemMessageId; ?>__<?php echo (int)$noMatchPersonID; ?>"
                                            id="event_match_events_<?php echo $systemMessageId; ?>__<?php echo (int)$noMatchPersonID; ?>__<?php echo (int)$event_by_person_key; ?>">
                                            <td colspan="6" style="width: 100%;">
                                                &bull; <a style="font-weight: bold; color: navy;"
                                                          href="<?php echo $emsaMessage->getAppClient()->getAppLinkToRecord($event_by_person_data['event_type'], $event_by_person_data['record_number'], true, $event_by_person_data['event_id']); ?>"
                                                          target="_blank">Record# <?php echo $event_by_person_data['record_number']; ?></a>
                                                (Event ID# <?php echo (int)$event_by_person_data['event_id']; ?>) <?php echo DisplayUtils::xSafe((string)$event_by_person_data['disease_name'], "UTF-8") . ' ' . (($event_by_person_data['event_type'] === AppRecordType::MORBIDITY_EVENT) ? 'Morbidity Event' : 'Contact Event') . ' (' . $event_by_person_data['event_date'] . ') '; ?>
                                            </td>
                                        </tr>
                                        <?php
                                        $first_event = false;
                                    }
                                }
                            }
                        }
                    } else {
                        // no results found
                        echo '<tr class="no_person_search_results" id="person_match_' . $systemMessageId . '__notfound"><td>No matches found!</td></tr>';
                    }
                    ?>
                    </tbody>
                </table>
            </div>
            <?php
        }
    } catch (PeopleSearchTooManyResults $ptm) {
        DisplayUtils::drawError(DisplayUtils::xSafe($ptm->getMessage()) . '.');
        $results = false;
    } catch (Throwable $e) {
        ExceptionUtils::logException($e);
        DisplayUtils::drawError('Unable to retrieve list of matches:<br><br>' . DisplayUtils::xSafe($e->getMessage()) . '.');
        $results = false;
    }

    unset($client);
} else {
    echo 'Unable to perform search.  Missing name/birthdate fields.';
}

$dbConn = null;
$emsaDbFactory = null;

<?php

namespace Udoh\Emsa\Import;

use Throwable;
use Udoh\Emsa\Utils\ExceptionUtils;
use ZipArchive;
use PDO;

/**
 * Copyright (c) 2017 Utah Department of Technology Services and Utah Department of Health
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
 * @copyright Copyright (c) 2017 Utah Department of Technology Services and Utah Department of Health
 */

/**
 * Utilities for working with Import/Export functionality in EMSA.
 *
 * @author Josh Ridderhoff <jridderhoff@utah.gov>
 */
abstract class ImportUtils
{

    /**
     * Creates a compressed Zip archive and unlinks original files
     * (Original source from David Walsh -- http://davidwalsh.name/create-zip-php)
     * 
     * @param array $files Array of filenames to zip together
     * @param string $destination Target filename for Zip archive
     * @param bool $overwrite If TRUE and target file already exists, file will be overwritten.  If FALSE and target file exists, createZip will return FALSE.
     * @return bool TRUE if Zip archive is created successfully, FALSE if file already exists or file could not be created.
     */
    public static function createZip($files = array(), $destination = '', $overwrite = false)
    {
        //if the zip file already exists and overwrite is false, return false
        if (file_exists($destination) && !$overwrite) {
            return false;
        }
        //vars
        $validFiles = array();
        //if files were passed in...
        if (is_array($files)) {
            //cycle through each file
            foreach ($files as $file) {
                //make sure the file exists
                if (file_exists($file)) {
                    $validFiles[] = $file;
                }
            }
        }
        //if we have good files...
        if (count($validFiles)) {
            //create the archive
            $zip = new ZipArchive();
            if ($zip->open($destination, $overwrite ? ZIPARCHIVE::OVERWRITE : ZIPARCHIVE::CREATE) !== true) {
                return false;
            }
            //add the files
            foreach ($validFiles as $file) {
                $zip->addFile($file, basename($file));
            }
            //debug
            //echo 'The zip archive contains ',$zip->numFiles,' files with a status of ',$zip->status;
            //close the zip -- done!
            $zip->close();

            // get rid of the files we zipped
            foreach ($validFiles as $killFile) {
                unlink($killFile);
            }

            // stream to download
            if (file_exists($destination)) {
                ob_clean();
                header('Pragma: public');
                header('Expires: 0');
                header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
                header('Cache-Control: public');
                header('Content-Description: File Transfer');
                header('Content-Type: application/octet-stream');
                header('Content-Disposition: attachment; filename=' . basename($destination));
                header('Content-Transfer-Encoding: binary');
                header('Content-Length: ' . filesize($destination));
                flush();
                readfile($destination);
                unlink($destination);
                exit;
            } else {
                echo 'Error happened';
                return false;
            }
        } else {
            return false;
        }
    }

    /**
     * Returns the application-specific vocabulary value for a given ID decoded from JSON-formatted rules.
     * 
     * @param PDO $dbConn Connection to the EMSA database.
     * @param int $masterId Master vocabulary ID decoded from JSON.
     * @param int $appId Application ID [Optional, default EpiTrax]
     * 
     * @return string
     */
    public static function getAppValueForDecodedID(PDO $dbConn, $masterId = null, $appId = 2)
    {
        $appValue = '';

        if (!empty($masterId)) {
            try {
                $sql = "SELECT initcap(m2a.coded_value) || CASE WHEN sc.label = 'resist_test_result' THEN ' (AST)' WHEN sc.label = 'test_result' THEN ' (Labs)' ELSE '' END AS coded_value 
                        FROM vocab_master2app m2a
                        LEFT JOIN vocab_master_vocab mv ON (mv.id = m2a.master_id)
                        LEFT JOIN structure_category sc ON (mv.category = sc.id)
                        WHERE m2a.app_id = :appId
                        AND m2a.master_id = :masterId;";
                $stmt = $dbConn->prepare($sql);
                $stmt->bindValue(':appId', $appId, PDO::PARAM_INT);
                $stmt->bindValue(':masterId', $masterId, PDO::PARAM_INT);

                if ($stmt->execute()) {
                    $appValue = (string) $stmt->fetchColumn(0);
                }
            } catch (Throwable $e) {
                ExceptionUtils::logException($e);
            } finally {
                $stmt = null;
            }
        }

        return $appValue;
    }

    /**
     * Generate a random token for use in an imported file's name.
     *
     * @param int|null $length Number of bytes to return from random_bytes()
     *
     * @return string
     */
    public static function generateRandomFilenameToken(?int $length = 16): string
    {
        if (empty($length)) {
            $length = 16;
        }

        try {
            return bin2hex(random_bytes($length));
        } catch (Throwable $e) {
            // fallback in case no source of entropy in random_bytes()
            // just needs to be reasonably random, crypto secure not critical if not possible
            return uniqid();
        }
    }

    /**
     * Get the MIME type for the specified file.
     *
     * @param string $filename
     *
     * @return string|null MIME type of file specified by filename, or null on errors.
     */
    public static function getFileMIMEType(string $filename): ?string
    {
        $mimeType = null;

        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = finfo_file($finfo, $filename);
        finfo_close($finfo);

        if ($mimeType !== false) {
            return $mimeType;
        } else {
            return null;
        }

    }

    /**
     * Returns Excel-like column labels back for a give zero-based integer (e.g. 0 = 'A', 27 = 'AA', etc.)<br><br>
     * [Courtesy http://stackoverflow.com/users/338665/ircmaxell from
     * http://stackoverflow.com/questions/3302857/algorithm-to-get-the-excel-like-column-name-of-a-number]
     *
     * @param int $num Integer representing the column index to return (starts at 0 = 'A')
     *
     * @return string
     */
    public static function getExcelColumnLabel(int $num): string
    {
        $numeric = $num % 26;
        $letter = chr(65 + $numeric);
        $num2 = intval($num / 26);
        if ($num2 > 0) {
            return self::getExcelColumnLabel($num2 - 1) . $letter;
        } else {
            return $letter;
        }
    }

}

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

use Udoh\Emsa\Utils\CoreUtils;

/**
     * Creates a compressed Zip archive and unlinks original files
     * (Original source from David Walsh -- http://davidwalsh.name/create-zip-php)
     *
     * @param array  $files       Array of filenames to zip together
     * @param string $destination Target filename for Zip archive
     * @param bool   $overwrite   If TRUE and target file already exists, file will be overwritten.  If FALSE and target file exists, createZip will return FALSE.
     *
     * @return bool TRUE if Zip archive is created successfully, FALSE if file already exists or file could not be created.
	 */
	function createZip($files = array(), $destination = '', $overwrite = false): bool {
		//if the zip file already exists and overwrite is false, return false
		if(file_exists($destination) && !$overwrite) { return false; }
		//vars
		$validFiles = array();
		//if files were passed in...
		if(is_array($files)) {
			//cycle through each file
			foreach($files as $file) {
				//make sure the file exists
				if(file_exists($file)) {
					$validFiles[] = $file;
				}
			}
		}
		//if we have good files...
		if(count($validFiles)) {
			//create the archive
			$zip = new ZipArchive();
			if($zip->open($destination,$overwrite ? ZIPARCHIVE::OVERWRITE : ZIPARCHIVE::CREATE) !== true) {
				return false;
			}
			//add the files
			foreach($validFiles as $file) {
				$zip->addFile($file, basename($file));
			}

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
				header('Content-Disposition: attachment; filename='.basename($destination));
				header('Content-Transfer-Encoding: binary');
				header('Content-Length: '.filesize($destination));
				flush();
				readfile($destination);
				unlink($destination);
				exit;
			} else {
				echo 'Error happened';
				return false;
			}
		}
		else
		{
			return false;
		}
	}
	
	
	
	
	/**
     * Decodes JSON-encoded interpretive rules data and converts it into exportable text.
     *
     * @param string $jsonString     JSON-encoded object to decode.
     * @param string $targetVar      Variable to retrieve from the selected object.
     * @param int    $index          Zero-based integer representing the index of the decoded object to search for.  [Optional; Defaults to first object if not specified.]
     * @param bool   $lookupAppValue Indicates whether the value decoded from the JSON object is a vocabulary ID that should be looked up [Optional; Default FALSE]
     * @param int    $appId          Specifies the application ID to look up values if $lookupAppValue is TRUE. [Optional; Defaults to 2 (EpiTrax)]
     *
     * @return string Value from index->targetVar, if present.  Empty string otherwise.
	 */
	function decodeJSONForExport(?string $jsonString = '', ?string $targetVar = '', ?int $index = 0, ?bool $lookupAppValue = false, ?int $appId = 2): string {
		$decodedString = '';

		$index = $index ?? 0;
		$lookupAppValue = $lookupAppValue ?? false;
		$appId = $appId ?? 2;

		if (empty($jsonString) || empty($targetVar)) {
			return $decodedString;
		}
		
		$jsonArray = @json_decode($jsonString);
		if (isset($jsonArray) && ($jsonArray !== false) && is_array($jsonArray)) {
			if ($lookupAppValue && isset($jsonArray[$index]->$targetVar)) {
				$decodedString = getAppValueForDecodedID(intval($jsonArray[$index]->$targetVar), intval($appId));
			} elseif (isset($jsonArray[$index]->$targetVar)) {
				$decodedString = trim($jsonArray[$index]->$targetVar);
			}
		}
		
		if ($targetVar == 'operator' && !is_null($decodedString) && !empty($decodedString)) {
			$decodedString = CoreUtils::operatorById(intval($decodedString));
			if ($decodedString == '==') {
				$decodedString = ' =';
			}
			if ($decodedString == '!=') {
				$decodedString = '<>';
			}
		}
		
		return $decodedString;
	}
	
	
	
	
	/**
     * Returns the application-specific vocabulary value for a given ID decoded from JSON-formatted rules.
     *
     * @param int $masterId Master vocabulary ID decoded from JSON.
     * @param int $appId    Application ID [Optional, default 2 (EpiTrax)]
     *
     * @return string
	 */
	function getAppValueForDecodedID(?int $masterId = null, ?int $appId = 2): string {
	    global $host_pa, $emsaDbSchemaPrefix;

	    $appId = $appId ?? 2;
		
		if (is_null($masterId) || empty($masterId)) {
			return '';
		}
		
		$sql = 'SELECT initcap(coded_value) AS coded_value FROM '.$emsaDbSchemaPrefix.'vocab_master2app WHERE app_id = '.intval($appId).' AND master_id = '.intval($masterId).';';
		$result = @pg_fetch_result(@pg_query($host_pa, $sql), 0, 'coded_value');
		
		return trim($result);
	}

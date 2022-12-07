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

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use PhpOffice\PhpSpreadsheet\Writer\Xls;
use Udoh\Emsa\Import\ImportUtils;
use Udoh\Emsa\Utils\ExceptionUtils;

if (!class_exists('\Udoh\Emsa\Auth\Authenticator') || !\Udoh\Emsa\Auth\Authenticator::userHasPermission(\Udoh\Emsa\Auth\Authenticator::URIGHTS_ADMIN)) {
    ob_end_clean();
    header($_SERVER['SERVER_PROTOCOL'] . " 404 Not Found", TRUE, 404);
    exit;
}

	require_once __DIR__ . '/structure_import_functions.php';
	require_once __DIR__ . '/structure_import_constants.php';
	
	//set_time_limit(120);
	
	define('EXPORT_TIMESTAMP', date("YmdHis", time()));
	
	$export_filename = __DIR__ . '/upload/elr_structure_export__'.strtolower(EXPORT_SERVERNAME).'__'.EXPORT_TIMESTAMP.'.zip';
	
	$finished_files = array();  // placeholder for filenames to be zipped
	$export_files = array();
	
	/* 
	 * loop through all children with vocab & add to export_files list
	 */
	$child_sql = 'SELECT id, ui_name FROM '.$emsaDbSchemaPrefix.'structure_labs WHERE ui_name <> \'eHARS\' AND alias_for = 0 ORDER BY id;';
	$child_rs = @pg_query($host_pa, $child_sql);
	if ($child_rs !== false) {
		while ($child_row = @pg_fetch_object($child_rs)) {
			$export_files[] = array(
				'filename' => __DIR__ . '/upload/'.trim(preg_replace('/[\\/:"*?<>|]+/', '', $child_row->ui_name)).'_HL7_Structure__'.EXPORT_SERVERNAME.'Export__'.EXPORT_TIMESTAMP.'.xls', 
				'lab_name' => trim($child_row->ui_name), 
				'sheets' => array(
					'Sheet1' => array(
						'query' => sprintf(EXPORT_QRY_CHILD_HL7XML, intval($child_row->id)), 
						'columns' => array(
							array(
								'width' => '25', 
								'label' => 'Lab Name', 
								'column' => 'lab_name'
							), 
							array(
								'width' => '15', 
								'label' => 'Message Version', 
								'column' => 'message_version'
							), 
							array(
								'width' => '50', 
								'label' => 'HL7 XPath', 
								'column' => 'hl7_xpath'
							), 
							array(
								'width' => '30', 
								'label' => 'Master XPath', 
								'column' => 'master_xpath'
							), 
							array(
								'width' => '15', 
								'label' => 'Concat String', 
								'column' => 'glue_string'
							), 
							array(
								'width' => '15', 
								'label' => 'Sequence', 
								'column' => 'sequence'
							)
						)
					)
				), 
				'object' => new Spreadsheet()
			);
		}
	}
	
	foreach ($export_files as $export_file) {  // create all files expected for export
        /** @var Spreadsheet $current_export_file */
        $current_export_file = $export_file['object'];

        try {
            $current_export_file->getProperties()->setCreator("UDOH ELR Structure Exporter");
            $current_export_file->getProperties()->setLastModifiedBy("UDOH ELR Structure Exporter");

            $sheet_counter = 0;
            foreach ($export_file['sheets'] as $sheet_name => $sheet_data) {  // create all sheets for this file
                if ($sheet_counter > 0) {
                    $current_export_file->createSheet();
                }
                $sheet_last_column = (count($sheet_data['columns']) - 1);
                $header_range = 'A1:' . ImportUtils::getExcelColumnLabel($sheet_last_column) . '1';

                $current_export_file->setActiveSheetIndex($sheet_counter);
                $current_export_file->getActiveSheet()->setTitle(substr($sheet_name, 0, 30));  // tab name limited to 31 chars

                if (isset($sheet_data['columns']) && is_array($sheet_data['columns']) && (count($sheet_data['columns']) > 0)) {
                    // write out column headers
                    foreach ($sheet_data['columns'] as $column_index => $column_data) {
                        $current_export_file->getActiveSheet()->setCellValue(ImportUtils::getExcelColumnLabel($column_index) . '1', $column_data['label']);
                    }

                    // get data back for this sheet's columns
                    unset($sheet_rs);
                    unset($sheet_row);
                    if (isset($sheet_data['query']) && !is_null($sheet_data['query'])) {
                        $sheet_rs = @pg_query($host_pa, $sheet_data['query']);
                    }

                    if (isset($sheet_rs) && ($sheet_rs !== false) && (@pg_num_rows($sheet_rs) > 0)) {
                        $sheet_row_index = 2;
                        while ($sheet_row = @pg_fetch_object($sheet_rs)) {
                            foreach ($sheet_data['columns'] as $column_index_fetch => $column_data_fetch) {
                                if (isset($column_data_fetch['decode']) && !is_null($column_data_fetch['decode']) && isset($column_data_fetch['get_app_value']) && $column_data_fetch['get_app_value']) {
                                    $current_export_file->getActiveSheet()->setCellValueExplicit(
                                        ImportUtils::getExcelColumnLabel($column_index_fetch) . intval($sheet_row_index),
                                        decodeJSONForExport($sheet_row->{$column_data_fetch['column']}, $column_data_fetch['decode'], $column_data_fetch['segment'], true, 2) . ' ',
                                        DataType::TYPE_STRING
                                    );
                                } elseif (isset($column_data_fetch['decode']) && !is_null($column_data_fetch['decode'])) {
                                    $current_export_file->getActiveSheet()->setCellValueExplicit(
                                        ImportUtils::getExcelColumnLabel($column_index_fetch) . intval($sheet_row_index),
                                        decodeJSONForExport($sheet_row->{$column_data_fetch['column']}, $column_data_fetch['decode'], $column_data_fetch['segment'], false) . ' ',
                                        DataType::TYPE_STRING
                                    );
                                } else {
                                    $current_export_file->getActiveSheet()->setCellValueExplicit(
                                        ImportUtils::getExcelColumnLabel($column_index_fetch) . intval($sheet_row_index),
                                        $sheet_row->{$column_data_fetch['column']} . ' ',
                                        DataType::TYPE_STRING
                                    );
                                }
                                $current_export_file->getActiveSheet()->getStyle(ImportUtils::getExcelColumnLabel($column_index_fetch) . intval($sheet_row_index))->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_TEXT);
                            }
                            $sheet_row_index++;
                        }
                    }

                    // size columns based on defined widths
                    foreach ($sheet_data['columns'] as $column_index_sizing => $column_data_trash) {
                        $current_export_file->getActiveSheet()->getColumnDimension(ImportUtils::getExcelColumnLabel($column_index_sizing))->setWidth($column_data_trash['width']);
                    }

                    // formatting & beautification
                    $current_export_file->getActiveSheet()->getStyle($header_range)->getFont()->setBold(true);
                    $current_export_file->getActiveSheet()->getStyle($header_range)->getFont()->setSize('12');
                    $current_export_file->getActiveSheet()->getStyle($header_range)->getFill()->setFillType(Fill::FILL_SOLID);
                    $current_export_file->getActiveSheet()->getStyle($header_range)->getFill()->getStartColor()->setARGB('FFB0C4DE');

                    if (stripos($export_file['filename'], 'child_values') !== false) {
                        // add lab name header to file if child vocab
                        $current_export_file->getActiveSheet()->insertNewRowBefore(1, 1);  // one new row inserted before current column headings
                        $current_export_file->getActiveSheet()->setCellValue('A1', $export_file['lab_name']);
                        $current_export_file->getActiveSheet()->getStyle($header_range)->getFont()->setBold(true);
                        $current_export_file->getActiveSheet()->getStyle($header_range)->getFont()->setSize('16');
                        $current_export_file->getActiveSheet()->getStyle($header_range)->getFill()->setFillType(Fill::FILL_SOLID);
                        $current_export_file->getActiveSheet()->getStyle($header_range)->getFill()->getStartColor()->setARGB('FFA4D246');
                        $current_export_file->getActiveSheet()->mergeCells($header_range);
                        $current_export_file->getActiveSheet()->freezePane('A3');
                    } else {
                        $current_export_file->getActiveSheet()->freezePane('A2');
                    }

                    // apply text wrapping to entire range of cells
                    $current_export_file->getActiveSheet()->getStyle('A1:' . ImportUtils::getExcelColumnLabel($sheet_last_column) . trim($sheet_row_index))->getAlignment()->setWrapText(true);
                }

                @pg_free_result($sheet_rs);
                $sheet_counter++;
            }

            $current_export_file->setActiveSheetIndex(0);  // set focus back to first sheet in workbook

            $current_writer = new Xls($current_export_file);
            $current_writer->save($export_file['filename']);
        } catch (Throwable $e) {
            ExceptionUtils::logException($e);
        } finally {
            $current_export_file->disconnectWorksheets();
            $current_writer = null;
            $current_export_file = null;
        }
		
		$finished_files[] = $export_file['filename'];
	}
	
	// package all files together, remove originals
	createZip($finished_files, $export_filename);
	
	echo 'Done!';

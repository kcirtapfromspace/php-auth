<?php
include_once 'includes/dashboard_functions.php';
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

use Udoh\Emsa\UI\AccessiblePopupMultiselect;
use Udoh\Emsa\Utils\CoreUtils;

if (!class_exists('Udoh\Emsa\Auth\Authenticator') || !Udoh\Emsa\Auth\Authenticator::userHasPermission(Udoh\Emsa\Auth\Authenticator::URIGHTS_DASHBOARD)) {
    ob_end_clean();
    header($_SERVER['SERVER_PROTOCOL'] . " 404 Not Found", TRUE, 404);
    exit;
}

if (isset($_POST['from_date']) && isset($_POST['to_date'])) {
    // update sessionized date params if updated by user
    Udoh\Emsa\Utils\DateTimeUtils::setDashboardDates(trim($_POST['from_date']), trim($_POST['to_date']));
}

if (isset($_POST['lab_filter']) && is_array($_POST['lab_filter'])) {
    $_SESSION[EXPORT_SERVERNAME]['dashboard_lab_filter'] = array();
    foreach ($_POST['lab_filter'] as $labFilterArray) {
        $_SESSION[EXPORT_SERVERNAME]['dashboard_lab_filter'][] = (int) $labFilterArray;
    }
}

if (isset($_POST['dashboard_action']) && (trim($_POST['dashboard_action']) == 'reset')) {
    // reset view to default
    unset($_SESSION[EXPORT_SERVERNAME]['dashboard_date_from'], $_SESSION[EXPORT_SERVERNAME]['dashboard_date_to'], $_SESSION[EXPORT_SERVERNAME]['dashboard_lab_filter']);
}

Udoh\Emsa\Utils\DateTimeUtils::initializeDashboardDates();

if (!isset($_SESSION[EXPORT_SERVERNAME]['dashboard_lab_filter'])) {
    $_SESSION[EXPORT_SERVERNAME]['dashboard_lab_filter'] = array();
}

session_write_close(); // done writing to session; prevent blocking
?>

<script type="text/javascript" src="https://www.gstatic.com/charts/loader.js"></script>
<script type='text/javascript'>
    google.charts.load('current', {packages: ['corechart', 'table']});
    google.charts.setOnLoadCallback(drawSummaryChart);
    // moved to tabular only -- google.charts.setOnLoadCallback(drawAvgNewCmrChart);
    google.charts.setOnLoadCallback(drawNewCaseChart);
    google.charts.setOnLoadCallback(drawAppendCaseChart);
    google.charts.setOnLoadCallback(drawDiscardCaseChart);
    google.charts.setOnLoadCallback(drawBlacklistChart);
    google.charts.setOnLoadCallback(drawGraylistChart);
    google.charts.setOnLoadCallback(drawMQFChart);
    google.charts.setOnLoadCallback(drawConditionChart);
    google.charts.setOnLoadCallback(drawAutomationFactorChart);
    google.charts.setOnLoadCallback(drawLabDataChart);
    google.charts.setOnLoadCallback(drawAlertDataChart);
    google.charts.setOnLoadCallback(drawTapChart);
    google.charts.setOnLoadCallback(drawTapSelectedChart);
    google.charts.setOnLoadCallback(drawTapSelectedDOWChart);

    var summaryData,
            summaryOptions,
            summaryChart,
            summaryView,
            summarySeriesColor,
            tapData,
            tapSelectedData,
            tapSelectedDOWData,
            tapOptions,
            tapChart,
            tapView,
            tapSelectedView,
            tapSelectedDOWView,
            newCaseData,
            newCaseOptions,
            newCaseChart,
            newCaseView,
            appendCaseData,
            appendCaseOptions,
            appendCaseChart,
            appendCaseView,
            discardCaseData,
            discardCaseOptions,
            discardCaseChart,
            discardCaseView,
            blacklistData,
            blacklistSummaryOptions,
            blacklistSummaryChart,
            blacklistView,
            graylistData,
            graylistSummaryOptions,
            graylistSummaryChart,
            graylistView,
            mqfData,
            mqfOptions,
            mqfChart,
            mqfView,
            conditionData,
            conditionOptions,
            conditionChart,
            conditionView,
            autoFactorData,
            autoFactorOptions,
            autoFactorChart,
            labData,
            labOptions,
            labChart,
            alertData,
            alertOptions,
            alertChart;

    function drawTapChart() {
        $.ajax({
            type: 'POST',
            data: {callback: 'getTrafficArrivalPattern'},
            url: 'ajax/dashboard_ajax.php',
            dataType: 'json',
            async: true
        }).done(function(data) {
            jsonData = data;

            tapData = new google.visualization.DataTable();
            tapData.addColumn('string', 'Interval');
            tapData.addColumn('number', 'Messages Received');
            $.each(jsonData, function(series, val) {
                tapData.addRow([series, ((typeof (val) != 'object') ? parseInt(val) : 0)]);
            });
            
            tapView = new google.visualization.DataView(tapData);
            tapView.setColumns([0, 1]);

            $('#tap_chart_status').hide();

            tapSummaryOptions = {
                height: 285,
                fontName: 'Open Sans',
                fontSize: 12,
                chartArea: {left: "5%", top: 10, height: "83%", width: "90%"},
                colors: ['yellowgreen'],
                legend: {position: 'none'},
                annotations: { alwaysOutside: true, textStyle: { fontSize: 9 } },
                hAxis: { viewWindow: { min: 0 }, slantedText: true, slantedTextAngle: 90, textStyle: {fontSize: 9} },
                bar: { groupWidth: "90%" }
            };

            tapSummaryChart = new google.visualization.ColumnChart(document.getElementById('tap_chart_ajax'));
            tapSummaryChart.draw(tapView, tapSummaryOptions);
        });
    }

    function drawTapSelectedChart() {
        $.ajax({
            type: 'POST',
            data: {callback: 'getTrafficArrivalPatternSelected'},
            url: 'ajax/dashboard_ajax.php',
            dataType: 'json',
            async: true
        }).done(function(data) {
            jsonData = data;

            tapSelectedData = new google.visualization.DataTable();
            tapSelectedData.addColumn('string', 'Interval');
            tapSelectedData.addColumn('number', 'Messages Received');
            $.each(jsonData, function(series, val) {
                tapSelectedData.addRow([series, ((typeof (val) != 'object') ? parseInt(val) : 0)]);
            });

            tapSelectedView = new google.visualization.DataView(tapSelectedData);
            tapSelectedView.setColumns([0, 1]);

            $('#tap2_chart_status').hide();

            tapSelectedSummaryOptions = {
                height: 285,
                fontName: 'Open Sans',
                fontSize: 12,
                chartArea: {left: "5%", top: 10, height: "83%", width: "90%"},
                colors: ['thistle'],
                legend: {position: 'none'},
                annotations: { alwaysOutside: true, textStyle: { fontSize: 9 } },
                hAxis: { viewWindow: { min: 0 }, textStyle: {fontSize: 10} },
                bar: { groupWidth: "40%" }
            };

            tapSelectedSummaryChart = new google.visualization.ColumnChart(document.getElementById('tap2_chart_ajax'));
            tapSelectedSummaryChart.draw(tapSelectedView, tapSelectedSummaryOptions);
        });
    }

    function drawTapSelectedDOWChart() {
        $.ajax({
            type: 'POST',
            data: {callback: 'getTrafficArrivalPatternSelectedDOW'},
            url: 'ajax/dashboard_ajax.php',
            dataType: 'json',
            async: true
        }).done(function(data) {
            jsonData = data;

            tapSelectedDOWData = new google.visualization.DataTable();
            tapSelectedDOWData.addColumn('string', 'Interval');
            tapSelectedDOWData.addColumn('number', 'Messages Received');
            $.each(jsonData, function(series, val) {
                tapSelectedDOWData.addRow([series, ((typeof (val) != 'object') ? parseInt(val) : 0)]);
            });

            tapSelectedDOWView = new google.visualization.DataView(tapSelectedDOWData);
            tapSelectedDOWView.setColumns([0, 1]);

            $('#tap3_chart_status').hide();

            tapSelectedDOWSummaryOptions = {
                height: 285,
                fontName: 'Open Sans',
                fontSize: 12,
                chartArea: {left: "5%", top: 10, height: "50%", width: "90%"},
                colors: ['plum'],
                legend: {position: 'none'},
                annotations: { alwaysOutside: true, textStyle: { fontSize: 9 } },
                hAxis: { viewWindow: { min: 0 }, slantedText: true, slantedTextAngle: 90, textStyle: {fontSize: 9} },
                bar: { groupWidth: "80%" }
            };

            tapSelectedDOWSummaryChart = new google.visualization.ColumnChart(document.getElementById('tap3_chart_ajax'));
            tapSelectedDOWSummaryChart.draw(tapSelectedDOWView, tapSelectedDOWSummaryOptions);
        });
    }

    function drawSummaryChart() {
        $.ajax({
            type: 'POST',
            data: {callback: 'getDashboardSummary'},
            url: 'ajax/dashboard_ajax.php',
            dataType: 'json',
            async: true
        }).done(function(data) {
            jsonData = data;
            
            summarySeriesColors = {
                "Total": "royalblue",
                "Entry": "darkorange",
                "Out of State": "yellowgreen",
                "Pending": "tomato",
                "Semi-Auto": "gold",
                "Assigned": "forestgreen",
                "Graylist": "lightgray",
                "Exception": "crimson",
                "QA Review": "gold",
                "Deleted": "dimgray",
                "Unprocessed": "lavender",
                "Locked": "deeppink",
                "Unknown": "black"
            };

            summaryData = new google.visualization.DataTable();
            summaryData.addColumn('string', 'Message Queue');
            summaryData.addColumn('number', '# of HL7 Messages');
            summaryData.addColumn({type: 'string', role: 'style'});
            $.each(jsonData, function(series, val) {
                summaryData.addRow([series, parseInt(val), 'color: ' + summarySeriesColors[series].toString()]);
                //summaryData.addRow([series + ' [' + ((typeof (val) != 'object') ? val : '0') + ']', parseInt(val)]);
            });
            
            summaryView = new google.visualization.DataView(summaryData);
            summaryView.setColumns([0, 1, 2, {
                    calc: "stringify",
                    sourceColumn: 1,
                    type: "string",
                    role: "annotation"
            }]);

            $('#summary_chart_status').hide();

            summaryOptions = {
                height: 305,
                fontName: 'Open Sans',
                fontSize: 12,
                chartArea: {left: "18%", top: 10, height: "90%", width: "76%"},
                colors: ['darkorange'],
                legend: {position: 'none'},
                annotations: { alwaysOutside: true, textStyle: { fontSize: 11 } }
            };

            summaryChart = new google.visualization.BarChart(document.getElementById('summary_chart_ajax'));
            summaryChart.draw(summaryView, summaryOptions);
        });
    }

    function drawAvgNewCmrChart() {
        $.ajax({
            type: 'POST',
            data: {callback: 'getAvgCMRCreateTime'},
            url: 'ajax/dashboard_ajax.php',
            dataType: 'json',
            async: true
        }).done(function(data) {
            jsonData = data;

            avgNewCmrData = new google.visualization.DataTable();
            avgNewCmrData.addColumn('string', 'Disease Category');
            avgNewCmrData.addColumn('string', 'Average Time Elapsed');
            $.each(jsonData, function(series, val) {
                avgNewCmrData.addRow([series, val]);
            });

            $('#avgnew_chart_status').hide();

            avgNewCmrOptions = {
                fontName: 'Open Sans',
                fontSize: 12,
                showRowNumber: false,
                sort: 'disable'
            };

            avgNewChart = new google.visualization.Table(document.getElementById('avgnew_chart_ajax'));
            avgNewChart.draw(avgNewCmrData, avgNewCmrOptions);
        });
    }

    function drawNewCaseChart() {
        $.ajax({
            type: 'POST',
            data: {callback: 'getDashboardNewCase'},
            url: 'ajax/dashboard_ajax.php',
            dataType: 'json',
            async: true
        }).done(function(data) {
            jsonData = data;

            newCaseData = new google.visualization.DataTable();
            newCaseData.addColumn('string', 'Disease Category');
            newCaseData.addColumn('number', '# of New CMRs Generated by ELR');
            $.each(jsonData, function(series, val) {
                newCaseData.addRow([series, ((typeof (val) != 'object') ? parseInt(val) : 0)]);
                //newCaseData.addRow([series + ' [' + ((typeof (val) != 'object') ? val : '0') + ']', parseInt(val)]);
            });
            
            newCaseView = new google.visualization.DataView(newCaseData);
            newCaseView.setColumns([0, 1, {
                    calc: "stringify",
                    sourceColumn: 1,
                    type: "string",
                    role: "annotation"
            }]);

            $('#newcase_chart_status').hide();

            newCaseOptions = {
                height: 285,
                fontName: 'Open Sans',
                fontSize: 12,
                chartArea: {left: "30%", top: 10, height: "90%", width: "65%"},
                colors: ['forestgreen'],
                legend: {position: 'none'},
                annotations: { alwaysOutside: true, textStyle: { fontSize: 11 } },
                hAxis: { viewWindow: { min: 0 } }
            };

            newCaseChart = new google.visualization.BarChart(document.getElementById('newcase_chart_ajax'));
            newCaseChart.draw(newCaseView, newCaseOptions);
        });
    }

    function drawAppendCaseChart() {
        $.ajax({
            type: 'POST',
            data: {callback: 'getDashboardAppendedCase'},
            url: 'ajax/dashboard_ajax.php',
            dataType: 'json',
            async: true
        }).done(function(data) {
            jsonData = data;

            appendCaseData = new google.visualization.DataTable();
            appendCaseData.addColumn('string', 'Disease Category');
            appendCaseData.addColumn('number', '# of Existing Lab Results Updated by ELR');
            $.each(jsonData, function(series, val) {
                appendCaseData.addRow([series, ((typeof (val['labs_updated']) != 'undefined') ? parseInt(val['labs_updated']) : 0)]);
                //appendCaseData.addRow([series + ' [' + ((typeof (val['labs_updated']) != 'undefined') ? val['labs_updated'] : 0) + ']', parseInt(val['labs_updated'])]);
            });
            
            appendCaseView = new google.visualization.DataView(appendCaseData);
            appendCaseView.setColumns([0, 1, {
                    calc: "stringify",
                    sourceColumn: 1,
                    type: "string",
                    role: "annotation"
            }]);

            $('#appendcase_chart_status').hide();

            appendCaseOptions = {
                height: 285,
                fontName: 'Open Sans',
                fontSize: 12,
                chartArea: {left: "30%", top: 10, height: "90%", width: "65%"},
                colors: ['royalblue'],
                legend: {position: 'none'},
                annotations: { alwaysOutside: true, textStyle: { fontSize: 11 } },
                hAxis: { viewWindow: { min: 0 } }
            };

            appendCaseChart = new google.visualization.BarChart(document.getElementById('appendcase_chart_ajax'));
            appendCaseChart.draw(appendCaseView, appendCaseOptions);
        });
    }

    function drawDiscardCaseChart() {
        $.ajax({
            type: 'POST',
            data: {callback: 'getDashboardDiscardedCase'},
            url: 'ajax/dashboard_ajax.php',
            dataType: 'json',
            async: true
        }).done(function(data) {
            jsonData = data;

            discardCaseData = new google.visualization.DataTable();
            discardCaseData.addColumn('string', 'Disease Category');
            discardCaseData.addColumn('number', 'HL7 Messages Discarded');
            $.each(jsonData, function(series, val) {
                discardCaseData.addRow([series, ((typeof (val) != 'object') ? parseInt(val) : 0)]);
            });
            
            discardCaseView = new google.visualization.DataView(discardCaseData);
            discardCaseView.setColumns([0, 1, {
                    calc: "stringify",
                    sourceColumn: 1,
                    type: "string",
                    role: "annotation"
            }]);

            $('#discardcase_chart_status').hide();

            discardCaseOptions = {
                height: 285,
                fontName: 'Open Sans',
                fontSize: 12,
                chartArea: {left: "30%", top: 10, height: "90%", width: "65%"},
                colors: ['firebrick'],
                legend: {position: 'none'},
                annotations: { alwaysOutside: true, textStyle: { fontSize: 11 } },
                hAxis: { viewWindow: { min: 0 } }
            };

            discardCaseChart = new google.visualization.BarChart(document.getElementById('discardcase_chart_ajax'));
            discardCaseChart.draw(discardCaseView, discardCaseOptions);
        });
    }

    function drawBlacklistChart() {
        $.ajax({
            type: 'POST',
            data: {callback: 'getDashboardBlacklistSummary'},
            url: 'ajax/dashboard_ajax.php',
            dataType: 'json',
            async: true
        }).done(function(data) {
            jsonData = data;

            blacklistData = new google.visualization.DataTable();
            blacklistData.addColumn('string', 'Reporting Facility');
            blacklistData.addColumn('number', 'HL7 Messages Blacklisted');
            $.each(jsonData, function(series, val) {
                blacklistData.addRow([series, ((typeof (val) != 'object') ? parseInt(val) : 0)]);
            });
            
            blacklistView = new google.visualization.DataView(blacklistData);
            blacklistView.setColumns([0, 1, {
                    calc: "stringify",
                    sourceColumn: 1,
                    type: "string",
                    role: "annotation"
            }]);

            $('#blacklist_summary_chart_status').hide();

            blacklistSummaryOptions = {
                height: 285,
                fontName: 'Open Sans',
                fontSize: 12,
                chartArea: {left: "50%", top: 10, height: "90%", width: "45%"},
                colors: ['#333333'],
                legend: {position: 'none'},
                bar: {groupWidth: 20},
                annotations: { alwaysOutside: true, textStyle: { fontSize: 11 } },
                hAxis: { viewWindow: { min: 0 } }
            };

            blacklistSummaryChart = new google.visualization.BarChart(document.getElementById('blacklist_summary_chart_ajax'));
            blacklistSummaryChart.draw(blacklistView, blacklistSummaryOptions);
        });
    }

    function drawGraylistChart() {
        $.ajax({
            type: 'POST',
            data: {callback: 'getDashboardGraylistSummary'},
            url: 'ajax/dashboard_ajax.php',
            dataType: 'json',
            async: true
        }).done(function(data) {
            jsonData = data;

            graylistData = new google.visualization.DataTable();
            graylistData.addColumn('string', 'Disease Category');
            graylistData.addColumn('number', 'HL7 Messages Graylisted');
            $.each(jsonData, function(series, val) {
                graylistData.addRow([series, ((typeof (val) != 'object') ? parseInt(val) : 0)]);
                //graylistData.addRow([series + ' [' + ((typeof (val) != 'object') ? val : '0') + ']', parseInt(val)]);
            });
            
            graylistView = new google.visualization.DataView(graylistData);
            graylistView.setColumns([0, 1, {
                    calc: "stringify",
                    sourceColumn: 1,
                    type: "string",
                    role: "annotation"
            }]);

            $('#graylist_summary_chart_status').hide();

            graylistSummaryOptions = {
                height: 285,
                fontName: 'Open Sans',
                fontSize: 12,
                chartArea: {left: "30%", top: 10, height: "90%", width: "65%"},
                colors: ['dimgray'],
                legend: {position: 'none'},
                annotations: { alwaysOutside: true, textStyle: { fontSize: 11 } },
                hAxis: { viewWindow: { min: 0 } }
            };

            graylistSummaryChart = new google.visualization.BarChart(document.getElementById('graylist_summary_chart_ajax'));
            graylistSummaryChart.draw(graylistView, graylistSummaryOptions);
        });
    }

    function drawMQFChart() {
        $.ajax({
            type: 'POST',
            data: {callback: 'getDashboardMessageQuality'},
            url: 'ajax/dashboard_ajax.php',
            dataType: 'json',
            async: true
        }).done(function(data) {
            jsonData = data;

            mqfData = new google.visualization.DataTable();
            mqfData.addColumn('string', 'Quality Issue');
            mqfData.addColumn('number', 'Messages with Issue');
            $.each(jsonData, function(series, val) {
                mqfData.addRow([series, parseInt(val)]);
            });
            
            mqfView = new google.visualization.DataView(mqfData);
            mqfView.setColumns([0, 1, {
                    calc: "stringify",
                    sourceColumn: 1,
                    type: "string",
                    role: "annotation"
            }]);

            $('#mqf_chart_status').hide();

            mqfOptions = {
                height: 285,
                fontName: 'Open Sans',
                fontSize: 12,
                chartArea: {left: "30%", top: 10, height: "90%", width: "65%"},
                colors: ['salmon'],
                legend: {position: 'none'},
                bar: {groupWidth: 20},
                annotations: { alwaysOutside: true, textStyle: { fontSize: 11 } },
                hAxis: { viewWindow: { min: 0 } }
            };

            mqfChart = new google.visualization.BarChart(document.getElementById('mqf_chart_ajax'));
            mqfChart.draw(mqfView, mqfOptions);
        });
    }

    function drawConditionChart() {
        $.ajax({
            type: 'POST',
            data: {callback: 'getDashboardConditionSummary'},
            url: 'ajax/dashboard_ajax.php',
            dataType: 'json',
            async: true
        }).done(function(data) {
            jsonData = data;

            conditionData = new google.visualization.DataTable();
            conditionData.addColumn('string', 'Condition');
            conditionData.addColumn('number', 'HL7 Messages Received');
            conditionData.addColumn('number', 'Assigned Labs');
            $.each(jsonData, function(series, val) {
                var conditionSeriesString = "['" + series + "'";
                $.each(val, function(col_key, col_val) {
                    conditionSeriesString += ', ' + parseInt(col_val);
                });
                conditionSeriesString += "]";
                conditionData.addRow(eval(conditionSeriesString));
            });
            
            conditionView = new google.visualization.DataView(conditionData);
            conditionView.setColumns([0, 1, {
                    calc: "stringify",
                    sourceColumn: 1,
                    type: "string",
                    role: "annotation"
            }, 2, {
                    calc: "stringify",
                    sourceColumn: 2,
                    type: "string",
                    role: "annotation"
            }]);

            $('#condition_chart_status').hide();

            var numRows = conditionData.getNumberOfRows();
            var realHeight = (numRows * 35);

            conditionOptions = {
                height: realHeight,
                bar: {groupWidth: 20},
                fontName: 'Open Sans',
                fontSize: 12,
                chartArea: {left: "25%", top: 25, height: "95%", width: "70%"},
                vAxis: {textPosition: 'out'},
                legend: {position: 'top'},
                annotations: { alwaysOutside: true, textStyle: { fontSize: 10 } },
            };

            conditionChart = new google.visualization.BarChart(document.getElementById('condition_chart_ajax'));
            conditionChart.draw(conditionView, conditionOptions);
        });
    }

    function drawAutomationFactorChart() {
        $.ajax({
            type: 'POST',
            data: {callback: 'getDashboardAutomationFactor'},
            url: 'ajax/dashboard_ajax.php',
            dataType: 'json',
            async: true
        }).done(function(data) {
            jsonData = data;

            autoFactorData = new google.visualization.DataTable();
            autoFactorData.addColumn('string', 'Auto or Manual?');
            autoFactorData.addColumn('number', 'Number of Messages');
            $.each(jsonData, function(series, val) {
                autoFactorData.addRow([series, parseInt(val)]);
            });

            $('#autofactor_chart_status').hide();

            autoFactorOptions = {
                height: 285,
                fontName: 'Open Sans',
                fontSize: 12,
                is3D: true,
                slices: { 1: { offset: 0.2 } }
            };

            autoFactorChart = new google.visualization.PieChart(document.getElementById('autofactor_chart_ajax'));
            autoFactorChart.draw(autoFactorData, autoFactorOptions);
        });
    }

    function drawLabDataChart() {
        $.ajax({
            type: 'POST',
            data: {callback: 'getDashboardLab'},
            url: 'ajax/dashboard_ajax.php',
            dataType: 'json',
            async: true
        }).done(function(data) {
            jsonData = data;

            labData = new google.visualization.DataTable();
            $.each(jsonData, function(series, val) {
                if (series == "headers") {
                    $.each(val, function(header_key, header_val) {
                        if (header_val == "Day") {
                            labData.addColumn('string', 'Day');
                        } else if (header_val == "Certainty") {
                            //labData.addColumn({type: 'boolean', role: 'certainty'});
                        } else {
                            labData.addColumn('number', header_val);
                        }
                    });
                } else {
                    var series_string = "['" + series + "'";
                    $.each(val, function(lab_key, lab_val) {
                        if (lab_key == "Certainty") {
                            series_string += ', ' + lab_val;
                        } else {
                            series_string += ', ' + parseInt(lab_val);
                        }
                    });
                    series_string += "]";
                    labData.addRow(eval(series_string));
                }
            });

            $('#lab_chart_status').hide();

            labOptions = {
                height: 285,
                fontName: 'Open Sans',
                fontSize: 12,
                chartArea: {left: "5%", top: 10, height: "90%", width: "70%"},
                hAxis: {slantedText: false, textStyle: {fontSize: 9}},
                pointSize: 3,
                legend: {position: 'right', textStyle: {fontSize: 11}},
                series: {0: {lineWidth: 1, pointSize: 5, pointShape: "star", lineDashStyle: [4, 5, 1, 5]}}
            };

            labChart = new google.visualization.LineChart(document.getElementById('lab_chart_ajax'));
            labChart.draw(labData, labOptions);
        });
    }
    
    function drawAlertDataChart() {
        $.ajax({
            type: 'POST',
            data: {callback: 'getSystemAlerts'},
            url: 'ajax/dashboard_ajax.php',
            dataType: 'json',
            async: true
        }).done(function(data) {
            jsonData = data;

            alertData = new google.visualization.DataTable();
            alertData.addColumn('string', 'Alert Type');
            alertData.addColumn('number', '# of Alerts');
            $.each(jsonData, function(series, val) {
                alertData.addRow([series, val]);
            });

            $('#alert_chart_status').hide();

            alertOptions = {
                fontName: 'Open Sans',
                fontSize: 12,
                showRowNumber: false,
                sort: 'disable',
                width: "100%"
            };

            alertChart = new google.visualization.Table(document.getElementById('alert_chart_ajax'));
            alertChart.draw(alertData, alertOptions);
        });
    }
</script>
<script type="text/javascript">
    $(function() {
        var xhrPool = [];
        var abortXHR = function() {
            $.each(xhrPool, function(idx, jqXHR) {
                jqXHR.abort();
            });
        };

        var oldbeforeunload = window.onbeforeunload;
        window.onbeforeunload = function() {
            var r = oldbeforeunload ? oldbeforeunload() : undefined;
            if (r == undefined) {
                abortXHR();
            }
            return r
        }

        $(document).ajaxSend(function(e, jqXHR, options) {
            xhrPool.push(jqXHR);
        });

        $(document).ajaxComplete(function(e, jqXHR, options) {
            xhrPool = $.grep(xhrPool, function(x) {
                return x != jqXHR
            });
        });

        $(".date_range").datepicker({
            changeMonth: true,
            changeYear: true,
            showButtonPanel: true
        });

        $("#apply_date").button({
            icon: "ui-icon-elrretry",
            showLabel: false
        });

        $("#reset_date").button({
            icon: "ui-icon-elrcancel",
            showLabel: false
        }).on("click", function() {
            $("#dashboard_action").val("reset");
            $("#date_filter").trigger("submit");
        });

        $("#view_tabular").button({
            icon: "ui-icon-elrviewdocument"
        }).on("click", function() {
            window.location.href = "<?php echo $webappBaseUrl; ?>?selected_page=6&submenu=7&report_id=dashboard_tabular";
        });

        $("#view_orphaned").button({
            icon: "ui-icon-elrviewdocument"
        }).on("click", function() {
            window.location.href = "<?php echo $webappBaseUrl; ?>?selected_page=6&submenu=7&report_id=orphaned_messages";
        });
    });
</script>

<h1 class="elrhdg"><span class="ui-icon ui-icon-header ui-icon-emsareporting"></span>Dashboard</h1>

<form id="date_filter" method="POST">
    <label for="from_date">Start Date:</label>
    <input class="ui-corner-all date_range" type="text" name="from_date" size="10" id="from_date" value="<?php echo $_SESSION[EXPORT_SERVERNAME]['dashboard_date_from']; ?>"> 
    <label for="to_date">End Date</label>
    <input class="ui-corner-all date_range" type="text" name="to_date" size="10" id="to_date" value="<?php echo $_SESSION[EXPORT_SERVERNAME]['dashboard_date_to']; ?>">
    <div class="emsa_toolbar_separator"></div>
    <?php
        $reporterList = CoreUtils::getReporterList($emsaDbFactory->getConnection());

        $reporterFilter = new AccessiblePopupMultiselect($reporterList, $_SESSION[EXPORT_SERVERNAME]['dashboard_lab_filter']);
        $reporterFilter->render("Filter Reporters", "lab_filter", true);
    ?>
    <input type="hidden" name="dashboard_action" id="dashboard_action" value="">
    <div class="emsa_toolbar_separator"></div>
    <button id="apply_date" type="submit" title="Update/refresh using these settings">Apply Changes</button>
    <button id="reset_date" type="button" title="Reset to default settings">Reset to Default Values</button>
    <button id="view_tabular" type="button" title="View report data in textual format">Tabular</button>
</form>


<div class="report_widget report_widget_tall ui-corner-all">
    <strong class="big_strong">EMSA Queue Overview</strong> 
    <span style="display: inline-block; cursor: help; margin-bottom: -3px;" class="ui-icon ui-icon-info" title="<?php Udoh\Emsa\Utils\DisplayUtils::xEcho(Udoh\Emsa\UI\Dashboard::TOOLTIP_SUMMARY); ?>"></span>
    <div id="summary_chart_status" style="display: inline-block;"><img style="vertical-align: bottom;" src="img/ajax-loader.gif" height="16" width="16" border="0" alt="Retrieving Data" /> Updating...</div>
    <div id="summary_chart_ajax"></div>
</div>

<div class="report_widget report_widget_tall ui-corner-all">
    <strong class="big_strong">System Alerts &amp; Preprocessor Exceptions</strong>
    <span style="display: inline-block; cursor: help; margin-bottom: -3px;" class="ui-icon ui-icon-info" title="<?php Udoh\Emsa\Utils\DisplayUtils::xEcho(Udoh\Emsa\UI\Dashboard::TOOLTIP_ALERTS); ?>"></span>
    <div id="alert_chart_status" style="display: inline-block;"><img style="vertical-align: bottom;" src="img/ajax-loader.gif" height="16" width="16" border="0" alt="Retrieving Data" /> Updating...</div>
    <div id="alert_chart_ajax"></div>
</div>

<div class="report_widget ui-corner-all" style="width: 1032px; height: auto;">
    <strong class="big_strong">Messages Received per Reporting Facility</strong> 
    <span style="display: inline-block; cursor: help; margin-bottom: -3px;" class="ui-icon ui-icon-info" title="<?php Udoh\Emsa\Utils\DisplayUtils::xEcho(Udoh\Emsa\UI\Dashboard::TOOLTIP_LAB_SUMMARY); ?>"></span>
    <div id="lab_chart_status" style="display: inline-block;"><img style="vertical-align: bottom;" src="img/ajax-loader.gif" height="16" width="16" border="0" alt="Retrieving Data" /> Updating...</div>
    <div id="lab_chart_ajax"></div>
</div>

<div class="report_widget ui-corner-all" style="width: 1032px; height: auto;">
    <strong class="big_strong">ELR Volume for Selected Sender(s) by 4-Hour Interval</strong>
    <span style="display: inline-block; cursor: help; margin-bottom: -3px;" class="ui-icon ui-icon-info" title="<?php Udoh\Emsa\Utils\DisplayUtils::xEcho(Udoh\Emsa\UI\Dashboard::TOOLTIP_TAP_SELECTED); ?>"></span>
    <div id="tap2_chart_status" style="display: inline-block;"><img style="vertical-align: bottom;" src="img/ajax-loader.gif" height="16" width="16" border="0" alt="Retrieving Data" /> Updating...</div>
    <div id="tap2_chart_ajax"></div>
</div>

<div class="report_widget ui-corner-all" style="width: 1032px; height: auto;">
    <strong class="big_strong">ELR Volume for Selected Sender(s) by Day of Week & 4-Hour Interval</strong>
    <span style="display: inline-block; cursor: help; margin-bottom: -3px;" class="ui-icon ui-icon-info" title="<?php Udoh\Emsa\Utils\DisplayUtils::xEcho(Udoh\Emsa\UI\Dashboard::TOOLTIP_TAP_SELECTED_DOW); ?>"></span>
    <div id="tap3_chart_status" style="display: inline-block;"><img style="vertical-align: bottom;" src="img/ajax-loader.gif" height="16" width="16" border="0" alt="Retrieving Data" /> Updating...</div>
    <div id="tap3_chart_ajax"></div>
</div>

<div class="report_widget ui-corner-all" style="width: 1032px; height: auto;">
    <strong class="big_strong">Total ELR Message Volume by Time of Day (Past 14 Days)</strong> 
    <span style="display: inline-block; cursor: help; margin-bottom: -3px;" class="ui-icon ui-icon-info" title="<?php Udoh\Emsa\Utils\DisplayUtils::xEcho(Udoh\Emsa\UI\Dashboard::TOOLTIP_TAP); ?>"></span>
    <div id="tap_chart_status" style="display: inline-block;"><img style="vertical-align: bottom;" src="img/ajax-loader.gif" height="16" width="16" border="0" alt="Retrieving Data" /> Updating...</div>
    <div id="tap_chart_ajax"></div>
</div>

<div class="report_widget ui-corner-all" style="width: 1032px; height: auto;">
    <strong class="big_strong">Disease Summary</strong>
    <span style="display: inline-block; cursor: help; margin-bottom: -3px;" class="ui-icon ui-icon-info" title="<?php Udoh\Emsa\Utils\DisplayUtils::xEcho(Udoh\Emsa\UI\Dashboard::TOOLTIP_CONDITION_SUMMARY); ?>"></span>
    <div id="condition_chart_status" style="display: inline-block;"><img style="vertical-align: bottom;" src="img/ajax-loader.gif" height="16" width="16" border="0" alt="Retrieving Data" /> Updating...</div>
    <div id="condition_chart_ajax"></div>
</div>

<div class="report_widget ui-corner-all">
    <strong class="big_strong">New CMRs Generated by ELR</strong>
    <span style="display: inline-block; cursor: help; margin-bottom: -3px;" class="ui-icon ui-icon-info" title="<?php Udoh\Emsa\Utils\DisplayUtils::xEcho(Udoh\Emsa\UI\Dashboard::TOOLTIP_NEW_CMR); ?>"></span>
    <div id="newcase_chart_status" style="display: inline-block;"><img style="vertical-align: bottom;" src="img/ajax-loader.gif" height="16" width="16" border="0" alt="Retrieving Data" /> Updating...</div>
    <div id="newcase_chart_ajax"></div>
</div>

<div class="report_widget ui-corner-all">
    <strong class="big_strong">Existing Labs Updated by ELR</strong>
    <span style="display: inline-block; cursor: help; margin-bottom: -3px;" class="ui-icon ui-icon-info" title="<?php Udoh\Emsa\Utils\DisplayUtils::xEcho(Udoh\Emsa\UI\Dashboard::TOOLTIP_UPDATED_LAB); ?>"></span>
    <div id="appendcase_chart_status" style="display: inline-block;"><img style="vertical-align: bottom;" src="img/ajax-loader.gif" height="16" width="16" border="0" alt="Retrieving Data" /> Updating...</div>
    <div id="appendcase_chart_ajax"></div>
</div>

<div class="report_widget ui-corner-all">
    <strong class="big_strong">Duplicate Messages Discarded</strong>
    <span style="display: inline-block; cursor: help; margin-bottom: -3px;" class="ui-icon ui-icon-info" title="<?php Udoh\Emsa\Utils\DisplayUtils::xEcho(Udoh\Emsa\UI\Dashboard::TOOLTIP_DISCARD); ?>"></span>
    <div id="discardcase_chart_status" style="display: inline-block;"><img style="vertical-align: bottom;" src="img/ajax-loader.gif" height="16" width="16" border="0" alt="Retrieving Data" /> Updating...</div>
    <div id="discardcase_chart_ajax"></div>
</div>

<div class="report_widget ui-corner-all">
    <strong class="big_strong">Messages Blacklisted</strong>
    <span style="display: inline-block; cursor: help; margin-bottom: -3px;" class="ui-icon ui-icon-info" title="<?php Udoh\Emsa\Utils\DisplayUtils::xEcho(Udoh\Emsa\UI\Dashboard::TOOLTIP_BLACKLIST); ?>"></span>
    <div id="blacklist_summary_chart_status" style="display: inline-block;"><img style="vertical-align: bottom;" src="img/ajax-loader.gif" height="16" width="16" border="0" alt="Retrieving Data" /> Updating...</div>
    <div id="blacklist_summary_chart_ajax"></div>
</div>

<div class="report_widget ui-corner-all">
    <strong class="big_strong">Messages Graylisted</strong>
    <span style="display: inline-block; cursor: help; margin-bottom: -3px;" class="ui-icon ui-icon-info" title="<?php Udoh\Emsa\Utils\DisplayUtils::xEcho(Udoh\Emsa\UI\Dashboard::TOOLTIP_GRAYLIST); ?>"></span>
    <div id="graylist_summary_chart_status" style="display: inline-block;"><img style="vertical-align: bottom;" src="img/ajax-loader.gif" height="16" width="16" border="0" alt="Retrieving Data" /> Updating...</div>
    <div id="graylist_summary_chart_ajax"></div>
</div>

<div class="report_widget ui-corner-all">
    <strong class="big_strong">Percent Automated Messages vs. Manual</strong>
    <span style="display: inline-block; cursor: help; margin-bottom: -3px;" class="ui-icon ui-icon-info" title="<?php Udoh\Emsa\Utils\DisplayUtils::xEcho(Udoh\Emsa\UI\Dashboard::TOOLTIP_AUTOFACTOR); ?>"></span>
    <div id="autofactor_chart_status" style="display: inline-block;"><img style="vertical-align: bottom;" src="img/ajax-loader.gif" height="16" width="16" border="0" alt="Retrieving Data" /> Updating...</div>
    <div id="autofactor_chart_ajax"></div>
</div>

<div class="report_widget ui-corner-all">
    <strong class="big_strong">Messages with QA Errors</strong>
    <span style="display: inline-block; cursor: help; margin-bottom: -3px;" class="ui-icon ui-icon-info" title="<?php Udoh\Emsa\Utils\DisplayUtils::xEcho(Udoh\Emsa\UI\Dashboard::TOOLTIP_MQF); ?>"></span>
    <div id="mqf_chart_status" style="display: inline-block;"><img style="vertical-align: bottom;" src="img/ajax-loader.gif" height="16" width="16" border="0" alt="Retrieving Data" /> Updating...</div>
    <div id="mqf_chart_ajax"></div>
</div>
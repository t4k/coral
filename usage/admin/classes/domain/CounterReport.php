<?php

/*
**************************************************************************************************************************
** CORAL Usage Statistics Module
**
** Copyright (c) 2010 University of Notre Dame
**
** This file is part of CORAL.
**
** CORAL is free software: you can redistribute it and/or modify it under the terms of the GNU General Public License as published by the Free Software Foundation, either version 3 of the License, or (at your option) any later version.
**
** CORAL is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the GNU General Public License for more details.
**
** You should have received a copy of the GNU General Public License along with CORAL.  If not, see <http://www.gnu.org/licenses/>.
**
**************************************************************************************************************************
*/

class CounterReport {

  protected $layouts;
  protected $layout;


  public function __construct($data, $version, $reportLayout) {
    $this->config = new Configuration;
    $this->checkForError();
  }

}

/*
   * Report Parsing
   */

  private function parseSushi($fName, $reportLayout, $release, $overwritePlatform) {
    $serviceProvider = $this->getServiceProvider();
    $fileName = BASE_DIR . $fName;

    //read layouts ini file to get the available layouts
    $layoutsArray = parse_ini_file(BASE_DIR . "layouts.ini", true);

    // check file exists
    if (!file_exists($fileName)) {
      $this->logStatus("Failed trying to open XML File: " . $fileName . ".  This could be due to not having write access to the /sushistore/ directory.");
      $this->saveLogAndExit($reportLayout);
    }

    $contents = $string = file_get_contents($fName);
  }

	private function parseXML($contents, $reportLayout, $overwritePlatform){
		//////////////////////////////////////
		//PARSE XML!!
		//////////////////////////////////////
    /// With Release 5, this parse XML functions converts the XML into Release 5 json format

    $metrics = parse_ini_file(BASE_DIR . "metrics.ini");


    // Gets rid of all namespace references
    $clean_xml = $this->stripNamespaces($contents);
    $xml = simplexml_load_string($clean_xml);

    $report = array()

        $txtOut = "";
        $startDateArr = explode("-", $this->startDate);
        $endDateArr = explode("-", $this->endDate);
        $startYear = $startDateArr[0];
        $startMonth = $startDateArr[1];
        $endYear = $endDateArr[0];
        $endMonth = $endDateArr[1];
        $numMonths = 0;
        if ($startMonth > $endMonth)
            $numMonths = (13 - ($startMonth - $endMonth));
        else if ($endMonth > $startMonth)
            $numMonths = ($endMonth - $startMonth);
        else
            $numMonths = 1;

		//First - get report information
        $report = $xml->Body->ReportResponse->Report->Report;
        $reportTypeName = $report->attributes()->Name;
        $version = $report->attributes()->Version;
        $layoutCode = $reportTypeName;
        if (($version == "3") || ($version =="4")){
            $version = "R" . $version;
        }

        if ($version != ''){
            $layoutCode .= "_" . $version;
        } else {
            $layoutCode .= "_R" . $this->releaseNumber;
        }

		//At this point, determine the format of the report to port to csv from the layouts.ini file
        $layoutKey = $layoutsArray['ReportTypes'][$layoutCode];
        $layoutColumns = $layoutsArray[$layoutKey]['columns'];
        //if this way of determining layout was unsuccessful, just use the layout sent in
        if (count($layoutColumns) == "0") {
            $layoutCode = $reportLayout . "_R" . $this->releaseNumber;

            $layoutKey = $layoutsArray['ReportTypes'][$layoutCode];
            $layoutColumns = $layoutsArray[$layoutKey]['columns'];
        }

        if (count($layoutColumns) == 0 || $layoutCode == ''){
            $this->logStatus("Failed determining layout:  Reached report items before establishing layout.  Please make sure this layout is set up in layouts.ini");
            $this->saveLogAndExit($reportLayout);
        }
        ///////////////////////////////////////////////////////
        // Create header for SUSHI file
        ///////////////////////////////////////////////////////
        $header = $layoutColumns;
        $startMonthArray = array('jan' => 1, 'feb' => 2, 'mar' => 3, 'apr' => 4, 'may' => 5, 'jun' => 6, 'jul' => 7, 'aug' => 8, 'sep' => 9, 'oct' => 10, 'nov' => 11, 'dec' => 12);
        for ($i = 0; $i < sizeof($header); $i++) {
            foreach ($startMonthArray as $monthName => $monthNumber) {
                if($header[$i] == $monthName && $monthNumber >= $startMonth) {
                    $header[$i] .= "-$startYear";
                    break;
                }
                else if ($header[$i] == $monthName && $monthNumber < $startMonth){
                    $header[$i] .= "-$endYear";
                    break;
                }
            }
        }
        for ($i = 12; $i > 0; $i--) {
            if ($startMonth > $endMonth && $i < $startMonth && $i > $endMonth)
                $header[(count($header) - 13)+$i] .= "-x";
            else if ($endMonth > $startMonth && ($i < $startMonth || $i > $endMonth))
                $header[(count($header) - 13)+$i] .= "-x";
            else if ($endMonth == $startMonth && $i < $startMonth && $i > $endMonth)
                $header[(count($header) - 13)+$i] .= "-x";
        }
        $txtOut .= implode($header, "\t") . "\n";
        $this->log("Layout validated successfully against layouts.ini : " . $layoutCode);

        foreach($report->Customer->ReportItems as $resource) {
            //reset variables
            /**
             * Each $reportArray is slightly different
             * JR1: Need aggregated count columns of ytd, ytdPDF, ytdHTML
             * BR1: Need aggregated count columns of ytd
             * DB1: Need separate rows based on activity type, but aggregated counts for those activity types
             */
            $identifierArray=array();
            $reportArray = array();
            $baseStats = array('ytd' => 0, 'ytdPDF' => 0, 'ytdHTML' => 0);
            $statRows = array();

            if ($overwritePlatform){
                $reportArray['platform'] = $serviceProvider;
            }else{
                $reportArray['platform'] = $resource->ItemPlatform[0];
            }

            $reportArray['publisher'] = $resource->ItemPublisher;
            $reportArray['title'] = $resource->ItemName;
            foreach($resource->ItemIdentifier as $identifier) {
                $idType = strtoupper($identifier->Type);
                $identifierArray[$idType] = $identifier->Value;
            }

            foreach($resource->ItemPerformance as $monthlyStat) {
                $date = new DateTime($monthlyStat->Period->Begin);
                $m = strtolower($date->format('M'));
                if ($reportTypeName == 'DB1') {
                    // If this is a DB1 report, we need 1 row of stats for each possible metric type
                    foreach($monthlyStat->Instance as $metricStat) {
                        $type = $metricStat->MetricType->__toString();
                        if(empty($statRows[$type])){
                            $statRows[$type] = $baseStats;
                        }
                        $count = intval($metricStat->Count);
                        $statRows[$type][$m] = $count;
                        $statRows[$type]['activityType'] = empty($metrics[$type]) ? $type : $metrics[$type];
                        $statRows[$type]['ytd'] += $count;
                    }
                } else {
                    // Else, there will only be 1 row of stats with YTD values
                    if (empty($statRows)) {
                        $statRows[0] = $baseStats;
                    }
                    $monthlyTotal = 0;
                    $pdfTotal = 0;
                    $htmlTotal = 0;
                    foreach ($monthlyStat->Instance as $metricStat) {
                        $count = intval($metricStat->Count);
                        if ($metricStat->MetricType == 'ft_total') {
                            $monthlyTotal = $count;
                        }
                        if (stripos($metricStat->MetricType, 'pdf')) {
                            $pdfTotal = $count;
                        }
                        if (stripos($metricStat->MetricType, 'html')) {
                            $htmlTotal = $count;
                        }
                    }
                    $monthlyTotal = $monthlyTotal == 0 ? $pdfTotal + $htmlTotal : $monthlyTotal;
                    $statRows[0][$m] = $monthlyTotal;
                    $statRows[0]['ytd'] += $monthlyTotal;
                    $statRows[0]['ytdPDF'] += $pdfTotal;
                    $statRows[0]['ytdHTML'] += $htmlTotal;
                }
            }

            foreach($identifierArray as $key => $value){
                if (!(strrpos($key,'PRINT') === false) && !(strrpos($key,'ISSN') === false)){
                    $reportArray['issn'] = $value;
                }else if (!(strrpos($key,'ONLINE') === false) && !(strrpos($key,'ISSN') === false)){
                    $reportArray['eissn'] = $value;
                }else if (!(strpos($key,'PRINT') === false) && !(strpos($key,'ISBN') === false)){
                    $reportArray['isbn'] = $value;
                }else if (!(strpos($key,'ONLINE') === false) && !(strpos($key,'ISBN') === false)){
                    $reportArray['eisbn'] = $value;
                }else if (!(strpos($key,'DOI') === false)){
                    $reportArray['doi'] = $value;
                }else if (!(strpos($key,'PROPRIETARY') === false)){
                    $reportArray['pi']=$value;
                }
            }

            // For each stat row, add in the resource metadata
            foreach($statRows as $row) {
                $reportRow = array_merge($reportArray, $row);
                $finalArray=array();
                //Now look at the report's layoutcode's columns to order them properly
                foreach($layoutColumns as $colName){
                    if (isset($reportRow[$colName]))
                        $finalArray[] = $reportRow[$colName];
                    else
                        $finalArray[] = null;
                }
                $txtOut .= implode($finalArray,"\t") . "\n";
            }
        }

		if (($layoutKey == "") || (count($layoutColumns) == '0') || ($txtOut == "")){
			if (file_exists($xmlFileName)) {
				$this->logStatus("Failed XML parsing or no data was found.");

				$this->log("LayoutKey: $layoutKey\n");
                $this->log("LayoutColumns Count: ".count($layoutColumns)."\n");

				$xml = simplexml_load_file($xmlFileName);
				$this->log("The following is the XML response:");

				$this->log(htmlentities(file_get_contents($xmlFileName)));

			}else{
				$this->log("Failed loading XML file.  Please verify you have write permissions on /sushistore/ directory.");
			}

			$this->saveLogAndExit($layoutCode);
		}

		#Save final text delimited "file" and log output on server
		$txtFile =  strtotime("now") . '.txt';
		$fp = fopen(BASE_DIR . 'archive/' . $txtFile, 'w');
		fwrite($fp, $txtOut);
		fclose($fp);


		$this->log("");
		$this->log("-- Sushi XML parsing completed --");

		$this->log("Archive/Text File Name: " . Utility::getPageURL() . 'archive/' . $txtFile);

		$this->saveLogAndExit($layoutCode, $txtFile, true);
	}

  private function stripNamespaces($string)
  {
    $sxe = new SimpleXMLElement($string);
    $doc = new DOMDocument();
    $doc->loadXML($string);
    foreach ($sxe->getNamespaces(true) as $name => $uri) {
      if (!empty($name)) {
        $finder = new DOMXPath($doc);
        $nodes = $finder->query("//*[namespace::{$name} and not(../namespace::{$name})]");
        foreach ($nodes as $n) {
          $ns_uri = $n->lookupNamespaceURI($name);
          $n->removeAttributeNS($ns_uri, $name);
        }
      }
    }
    return $doc->saveXML(null, LIBXML_NOEMPTYTAG);
  }

}


//for soap headers
class clsWSSEAuth{
	private $username;
	private $password;
	function __construct($username, $password){
		$this->username=$username;
		$this->password=$password;
	}
}
class clsWSSEToken{
	private $usernameToken;
	function __construct ($innerVal){
		$this->usernameToken = $innerVal;
	}
}

?>

<?php

/**
 * Copyright 2014 Michael van Vliet (Leiden University), Thomas Hankeijer 
 * (Leiden University)
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 * 
 *      http://www.apache.org/licenses/LICENSE-2.0
 * 
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 **/

    header('Cache-Control: no-cache, must-revalidate');
    header('Expires: Mon, 26 Jul 1997 05:00:00 GMT');
    header('Content-type: application/json');

    // convert feed http://www.metabolomicsworkbench.org/data/DRCCStudySummary.php?Mode=StudySummary&OutputMode=File&OutputDataMode=MetabolomeXchange&OutputType=JSON
    $jsonResponse = "";
    $datasets = array();

    $feedUrl = 'http://www.metabolomicsworkbench.org/data/DRCCStudySummary.php?Mode=StudySummary&OutputMode=File&OutputDataMode=MetabolomeXchange&OutputType=JSON';
    $feedJSON = file_get_contents($feedUrl);

    $feed = json_decode($feedJSON);
    
    foreach ($feed as $dataRecord) {

        $dataRecord = (array) $dataRecord;

        if ($dataRecord['Study Status'] != '0'){
            
            $accession = $dataRecord['Study ID'];
            $datasets[$accession] = array();
            $datasets[$accession]['accession'] = $accession;
            $datasets[$accession]['title'] = $dataRecord['Study Title'];
            $datasets[$accession]['description'] = $dataRecord['Study Summary'];
            $datasets[$accession]['url'] = "http://www.metabolomicsworkbench.org/data/DRCCMetadata.php?Mode=Study&StudyID=" . $accession;
            $datasets[$accession]['date'] = $dataRecord['Submitted'];
            $datasets[$accession]['submitter'] = $dataRecord['First Name'] . ' ' . $dataRecord['Last Name'];
            
            $datasets[$accession]['meta'] = array();
            $datasets[$accession]['meta']['species'] = $dataRecord['Species'];
            $datasets[$accession]['meta']['institute'] = $dataRecord['Institute'];
            $datasets[$accession]['meta']['department'] = $dataRecord['Department'];
            $datasets[$accession]['meta']['laboratory'] = $dataRecord['Laboratory'];
            $datasets[$accession]['meta']['analysis'] = $dataRecord['Analysis'];
        }
    }
    echo json_encode(array_values($datasets));
?>
<?php

/**
 * Copyright 2014 Michael van Vliet (Leiden University), Thomas Hankemeier 
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
    header("Access-Control-Allow-Origin: *"); // required for all clients to connect  

    set_time_limit(15*60); // 15 minutes

    $feedReloadKey = 'myfeedreloadkey';      

    // convert feed http://www.metabolomicsworkbench.org/data/DRCCStudySummary.php?Mode=StudySummary&OutputMode=File&OutputDataMode=MetabolomeXchange&OutputType=JSON
    $feedUrl = 'http://www.metabolomicsworkbench.org/data/DRCCStudySummary.php?Mode=StudySummary&OutputMode=File&OutputDataMode=MetabolomeXchange&OutputType=JSON';    
    $jsonResponse = "";

    // set/determine use of cache
    $cacheFile = md5($feedUrl) . '.cache';
    if (!file_exists($cacheFile) || (isset($_GET['rl']) && $_GET['rl'] == $feedReloadKey) || (file_exists($cacheFile) && (time() - filemtime($cacheFile)) > 24*60*60 ) ) {
        
        $datasets = array();

        // add JSON-LD context
        $datasets['@context'] = 'http://'.$_SERVER['HTTP_HOST'].'/contexts/datacatalog.jsonld';

        $datasets['name'] = 'Metabolomics Workbench';
        $datasets['url'] = 'http://www.metabolomicsworkbench.org/';
        $datasets['description'] = 'The Metabolomics Workbench is a scalable and extensible informatics infrastructure which will serve as a national metabolomics resource. This is a companion to RCMRCs and is a part of the Common Fund Initiative in metabolomics. The Metabolomics Workbench will coordinate data activities of national and international metabolomics centers and initiatives, serve as a national data repository and develop a Workbench that will have data, query and analysis interfaces, and tools for interactive analysis and integration of metabolomics data.';

        $datasets['datasets'] = array();        

        $ctx = stream_context_create(array('http'=>array('timeout' => 15*60,)));
        $feedJSON = file_get_contents($feedUrl, false, $ctx);

        $feed = json_decode($feedJSON);
        
        foreach ($feed as $dataRecord) {

            $dataRecord = (array) $dataRecord;

            if ($dataRecord['Study Status'] != '0'){

                $dataset = array();

                // add JSON-LD context
                $dataset['@context'] = 'http://'.$_SERVER['HTTP_HOST'].'/contexts/dataset.jsonld';                                  
                
                $dataset['accession'] = $dataRecord['Study ID'];
                $dataset['title'] = $dataRecord['Study Title'];
                $dataset['description'] = $dataRecord['Study Summary'];
                $dataset['url'] = "http://www.metabolomicsworkbench.org/data/DRCCMetadata.php?Mode=Study&StudyID=" . $dataset['accession'];
                $dataset['date'] = $dataRecord['Submitted'];
                
                $dataset['submitter'] = array();
                $dataset['submitter'][] = $dataRecord['First Name'] . ' ' . $dataRecord['Last Name'];

                $timestamp = ''; // convert date to timestamp
                if (isset($dataset['date']) && $dataset['date'] != ''){
                    list($year, $month, $day) = explode('-', $dataset['date']);
                    $timestamp = mktime(0, 0, 0, $month, $day, $year);
                }
                $dataset['timestamp'] = $timestamp;                
                
                $metadata = array();
            
                // add metadata JSON-LD
                $metadata['@context'] = 'http://'.$_SERVER['HTTP_HOST'].'/contexts/metadata.jsonld';                

                $metadata['species'] = $dataRecord['Species'];
                $metadata['institute'] = $dataRecord['Institute'];
                $metadata['department'] = $dataRecord['Department'];
                $metadata['laboratory'] = $dataRecord['Laboratory'];
                $metadata['analysis'] = $dataRecord['Analysis'];
            
                $dataset['meta'] = $metadata;
                $datasets['datasets'][] = $dataset;
            }
        }

        if (count($datasets['datasets']) >= 1){
            // convert to JSON and write file to cache
            $jsonResponse = json_encode($datasets);
            $fp = fopen($cacheFile, 'w');
            fwrite($fp, $jsonResponse);
            fclose($fp);        
        } else {
            // in case the feed doesn't return any results we return the cached version
            $jsonResponse = file_get_contents($cacheFile);
        } 
    } else {
        $jsonResponse = file_get_contents($cacheFile);                          
    }

    echo $jsonResponse;
?>
<?php

/**
 * Copyright 2017 Michael van Vliet (Leiden University), Thomas Hankemeier
 * (Leiden University)
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 * 		http://www.apache.org/licenses/LICENSE-2.0
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
	date_default_timezone_set('Europe/London');

	$feedReloadKey = 'myfeedreloadkey';

	if (!isset($_SERVER['HTTP_HOST'])){
        $_SERVER['HTTP_HOST'] = 'feeds.metabolomexchange.org';
    }

	# get datasets:     http://metabolonote.kazusa.or.jp/mnapi.php?action=getTopPageIds
    # get dataset:      http://metabolonote.kazusa.or.jp/mnapi.php?action=getDataByMetadataId&id=SE1_MS1&output=json
	# get create data:  http://metabolonote.kazusa.or.jp/mnapi.php?action=getCreateDateOfMetadata&id=SE10_S01_M01_D02
    #                   http://metabolonote.kazusa.or.jp/mnapi.php?action=getCreateDateOfMetadata&id=SE10

	// convert feed http://metabolonote.kazusa.or.jp/mnapi.php
	$feedUrl = 'http://metabolonote.kazusa.or.jp/mnapi.php?action=getAllPageIds';
	$jsonResponse = "";

	// set/determine use of cache
	$cacheFile = md5($feedUrl) . '.cache';
    if (!file_exists($cacheFile) || (isset($_GET['rl']) && $_GET['rl'] == $feedReloadKey) || (file_exists($cacheFile) && (time() - filemtime($cacheFile)) > 24*60*60 ) ) {

		$datasets = array();

		// add JSON-LD context
		$datasets['@context'] = 'http://'.$_SERVER['HTTP_HOST'].'/contexts/datacatalog.jsonld';

		$datasets['name'] = 'Metabolonote';
		$datasets['url'] = 'http://metabolonote.kazusa.or.jp';
		$datasets['description'] = 'Metabolonote is a database/management system that manages "metadata" for experimental data obtained through the metabolomics studies.';

		$datasets['datasets'] = array();

        $ctx = stream_context_create(array('http'=>array('timeout' => 15*60,)));
        $feedJSON = file_get_contents($feedUrl, false, $ctx);

        $feed = json_decode($feedJSON);

        $arrDS = array();
        foreach ($feed as $idx => $dset) {
            $dsParts = explode("_", $dset);

            # make sure it exists
            if (!isset($arrDS[$dsParts[0]])){
                $arrDS[$dsParts[0]] = array();
                $arrDS[$dsParts[0]]['platforms'] = array();
            }

            # accession
            $arrDS[$dsParts[0]]['accession'] = $dsParts[0];

            # platform
            if (isset($dsParts[1])) {
                if (strtolower(substr($dsParts[1], 0, 2)) == 'ms') {
                    $arrDS[$dsParts[0]]['platforms'][$dsParts[1]] = $dsParts[1];
                }
            }
        }

        // $arrDS = array_slice($arrDS, 8, 2); // limit datasets for testing

        foreach ($arrDS as $idx => $ds){

            // Find data created
            $datasetCreateUrl = 'http://metabolonote.kazusa.or.jp/mnapi.php?action=getCreateDateOfMetadata&id=' . $ds['accession'] . '&output=json';
            $datasetCreateJSON = file_get_contents($datasetCreateUrl, false, $ctx);
            $dataCreateRecord = json_decode($datasetCreateJSON);
            $dataCreateRecord = (array) $dataCreateRecord;
            $dataCreated = current($dataCreateRecord);

            $datasetUrl = 'http://metabolonote.kazusa.or.jp/mnapi.php?action=getDataByMetadataId&id=' . $ds['accession'] . '&output=json';
            $datasetJSON = file_get_contents($datasetUrl, false, $ctx);
            $dataRecord = json_decode($datasetJSON);

            $dataRecord = (array) $dataRecord;
            $dataRecord = (array) $dataRecord['sample_set'];

            $dataset = array();
            $dataset['description'] = array();

            // add JSON-LD context
            $dataset['@context'] = 'http://'.$_SERVER['HTTP_HOST'].'/contexts/dataset.jsonld';

            $dataset['accession'] = $ds['accession'];
            $dataset['title'] = $dataRecord['title'];
            $dataset['description'][] = $dataRecord['description'];
            $dataset['url'] = "http://metabolonote.kazusa.or.jp/" . $dataset['accession'] . ':/';
            $dataset['timestamp'] = $dataCreated;

            $date = new DateTime("@$dataCreated");
            $dataset['date'] = $date->format('Y-m-d');

            $dataset['submitter'] = array();

            $arrSubmitters = explode(",", $dataRecord['authors']);

            if (strpos($dataRecord['authors'], ':') !== false) {

                foreach ($arrSubmitters as $sIdx => $submitter){

                    $submitter = trim($submitter);
                    $submitterParts = explode(" ", $submitter);
                    $firstElement = trim(current($submitterParts));
                    $lastElement = trim(end($submitterParts));

                    if (strpos($firstElement, ':') !== false) {
                        $dataset['submitter'][] = implode(" ", array_slice($submitterParts, 1, count($submitterParts)));
                    } elseif ($lastElement == (int) $lastElement){
                        $dataset['submitter'][] = implode(" ", array_slice($submitterParts, 0, count($submitterParts) - 1));
                    } else {
                        $dataset['submitter'][] = implode(" ", $submitterParts);
                    }
                }

            } else {
                foreach (array_slice($arrSubmitters, 0, count($arrSubmitters) - 1) as $aIdx => $author){
                    $dataset['submitter'][] = trim($author);
                }
            }

            $metadata = array();

            // add metadata JSON-LD
            $metadata['@context'] = 'http://'.$_SERVER['HTTP_HOST'].'/contexts/metadata.jsonld';

            // add platform information
            $platforms = array();
            foreach ($ds['platforms'] as $pIdx => $platform){

                $platformUrl = 'http://metabolonote.kazusa.or.jp/mnapi.php?action=getDataByMetadataId&id='.$ds['accession'].'_'.$platform.'&output=json';
                $platformJSON = file_get_contents($platformUrl, false, $ctx);
                $platformRecord = json_decode($platformJSON);
                $platformRecord = (array) $platformRecord;
                $platformRecord = (array) $platformRecord['analytical_method_details'];

                $platforms[$platformRecord['instrument']] = $platformRecord['instrument'];
                if ($platformRecord['description']) {
                    $dataset['description'][] = $platform . ": " . $platformRecord['description'];
                }
            }

            $metadata['platform'] = implode(", ", $platforms);

            $dataset['meta'] = $metadata;

            if (count($dataset['submitter'])) {
                $datasets['datasets'][$dataset['accession']] = $dataset;
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
            try {
                $jsonResponse = file_get_contents($cacheFile);
            } catch (Exception $e) {
                print('Caught exception: ' . $e->getMessage() . "\n");
            }
		}

	} else {
		$jsonResponse = file_get_contents($cacheFile);
	}

	echo $jsonResponse;
?>
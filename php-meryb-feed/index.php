<?php

/**
 * Copyright 2014 Michael van Vliet (Leiden University), Thomas Hankemeier 
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

	// convert feed http://services.cbib.u-bordeaux2.fr/MERYB/mx_feed.xml
	$feedUrl = 'http://services.cbib.u-bordeaux2.fr/MERYB/mx_feed.xml';	
	$jsonResponse = "";

	// set/determine use of cache
	$cacheFile = md5($feedUrl) . '.cache';
	if ( file_exists($cacheFile) && ( (time() - filemtime($cacheFile)) <= 9000 ) ) {
		$jsonResponse = file_get_contents($cacheFile);
	} else {


		$datasets = array();

		// add JSON-LD context
		$datasets['@context'] = 'http://'.$_SERVER['HTTP_HOST'].'/contexts/datacatalog.jsonld';		

		$datasets['name'] = 'Metabolomic Repository Bordeaux';
		$datasets['shortname'] = 'meryb';
		$datasets['url'] = 'http://services.cbib.u-bordeaux2.fr/MERYB/home/home.php';
		$datasets['description'] = 'MeRy-B is a plant metabolomics platform allowing the storage and visualisation of Nuclear Magnetic Resonance (NMR) metabolic profiles from plants. It contains plant metabolites and unknown compounds lists with information about experimental conditions and metabolite concentrations from several plant species compiled from a thousand of curated annotated NMR profiles on various organs or tissues.';

		$datasets['datasets'] = array();		

		// fetch data
		$feedXML = file_get_contents($feedUrl);
		$feed = simplexml_load_string($feedXML);
		
		foreach ($feed->entries->entry as $idx => $dataRecord) {
				
			$dataset = array();

			// add JSON-LD context
			$dataset['@context'] = 'http://'.$_SERVER['HTTP_HOST'].'/contexts/dataset.jsonld';					

			$accession	= trim((string)$dataRecord->Attributes());
			
			$dataset['accession']	= (string) $accession;
			$dataset['url']			= 'http://www.cbib.u-bordeaux2.fr/MERYB/res/project/' . (string) $accession;
			$dataset['title']		= (string) $dataRecord->name;
			$dataset['description']	= (string) $dataRecord->description;

			// dates
			$dataset['date']		= '';
			foreach ($dataRecord->dates->date as $date){
				if ((string) $date->Attributes()->type == 'creation'){ $dataset['date'] = (string) $date->Attributes()->value; }				
			}

			$timestamp = ''; // convert date to timestamp
			if (isset($dataset['date']) && $dataset['date'] != ''){
				list($year, $month, $day) = explode('-', $dataset['date']);
				$timestamp = mktime(0, 0, 0, $month, $day, $year);
			}
			$dataset['timestamp'] = $timestamp;

			// additional fields
			$organisms 		= array();
			$metabolites	= array();

			$dataset['submitter']	= 'Daniel Jacob';

			// metadata
			$metadata = array();				
			
			// add metadata JSON-LD
			$metadata['@context'] = 'http://'.$_SERVER['HTTP_HOST'].'/contexts/metadata.jsonld';				

			foreach ($dataRecord->additional_fields->field as $field){

				$fieldName = (string) $field->Attributes()->name;
				$fieldValue = (string) $field;

				if ($fieldName == 'submitter'){ $dataset['submitter'] = $fieldValue; }
				if ($fieldName == 'technology_type'){ $metadata['analysis'] = $fieldValue; }
				if ($fieldName == 'platform'){ $metadata['platform'] = $fieldValue; }
				if ($fieldName == 'organism'){ $organisms[] = $fieldValue; }
				if ($fieldName == 'metabolite_name'){ $metabolites[] = $fieldValue; }

			}			

			// organism
			if (count($organisms) > 1){
				$metadata['organism'] = array();
				foreach ($organisms as $organism){ $metadata['organism'][] = $organism; }
			} else if (count($organisms) == 1){ $metadata['organism'] = $organisms[0]; }	
			
			// metabolites
			if (count($metabolites) > 1){
				$metadata['metabolites'] = array();
				foreach ($metabolites as $metabolite){ $metadata['metabolites'][] = $metabolite; }
			} else if (count($metabolites) == 1){ $metadata['metabolites'] = $metabolites[0]; }	

			$dataset['meta'] = $metadata;
			$datasets['datasets'][] = $dataset;
		}

		// convert to JSON and write file to cache
		$jsonResponse = json_encode($datasets);
		$fp = fopen($cacheFile, 'w');
		fwrite($fp, $jsonResponse);
		fclose($fp);		
	}

	echo $jsonResponse;
?>
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

	set_time_limit(15*60); // 15 minutes
	date_default_timezone_set('Europe/London');

	$feedReloadKey = 'myfeedreloadkey';

	$feedUrl = "ftp://ftp.ebi.ac.uk/pub/databases/metabolights/eb-eye/thomsonreuters_metabolights_studies.xml";
	$jsonResponse = "";

	// set/determine use of cache
	$cacheFile = md5($feedUrl) . '.cache';
    if (!file_exists($cacheFile) || (isset($_GET['rl']) && $_GET['rl'] == $feedReloadKey) || (file_exists($cacheFile) && (time() - filemtime($cacheFile)) > 24*60*60 ) ) {
		
		$datasets = array();

		$datasets['name'] = 'MetaboLights';
		$datasets['url'] = 'http://www.ebi.ac.uk/metabolights/';
		$datasets['description'] = 'MetaboLights is a database for Metabolomics experiments and derived information. The database is cross-species, cross-technique and covers metabolite structures and their reference spectra as well as their biological roles, locations and concentrations, and experimental data from metabolic experiments.';

		// add JSON-LD context
		$datasets['@context'] = 'http://'.$_SERVER['HTTP_HOST'].'/contexts/datacatalog.jsonld';		

		$z = new XMLReader;
		$z->open($feedUrl);

		$doc = new DOMDocument;

		// move to the first <entry /> node
		while ($z->read() && $z->name !== 'entry');
		
		// foreach ($feed->entries->entry as $idx => $dataRecord) {
		while ($z->name === 'entry'){

			//$dataRecord = new SimpleXMLElement($z->readOuterXML());
			$dataRecord = simplexml_import_dom($doc->importNode($z->expand(), true));

			$id_prefix = substr((string) $dataRecord->Attributes(),0,5);

			if ($id_prefix == 'MTBLS' && $dataRecord->name != 'PRIVATE STUDY'){
				
				$dataset = array();
				$metadata = array();	
				$dataset['description'] = array();				

				// add metadata JSON-LD
				$metadata['@context'] = 'http://'.$_SERVER['HTTP_HOST'].'/contexts/metadata.jsonld';				

				// add JSON-LD context
				$dataset['@context'] = 'http://'.$_SERVER['HTTP_HOST'].'/contexts/dataset.jsonld';		

				$accession	= trim((string)$dataRecord->Attributes());
				
				$dataset['accession']	= (string) $accession;
				$dataset['url']			= 'http://www.ebi.ac.uk/metabolights/' . (string) $accession;
				$dataset['title']		= (string) $dataRecord->name;
				$dataset['description'][]	= (string) $dataRecord->description;

				// dates
				$dataset['date']		= '';
				foreach ($dataRecord->dates->date as $date){
					if ((string) $date->Attributes()->type == 'publication'){ $dataset['date'] = (string) $date->Attributes()->value; }				
				}

				$timestamp = ''; // convert date to timestamp
				if (isset($dataset['date']) && $dataset['date'] != ''){
					list($year, $month, $day) = explode('-', $dataset['date']);
					$timestamp = @mktime(0, 0, 0, $month, $day, $year);
				}
				$dataset['timestamp'] = $timestamp;

				// additional fields
				$organisms 		= array();
				$organism_parts	= array();
				$metabolites	= array();

				// authors/submitters
				$dataset['submitter'] = array();
				foreach ($dataRecord->authorship->author as $author){
					$dataset['submitter'][] = trim((string) $author->name);
				}

				// publictations
				$dataset['publications'] = array();
				foreach ($dataRecord->publications->publication as $publication){
					$dataset['publications'][] = array(
							'title'=>trim((string) $publication->title),
							'doi'=>trim((string) $publication->doi),
							'pubmed'=>trim((string) $publication->pubmed)
						);
				}				

				foreach ($dataRecord->additional_fields->field as $field){

					$fieldName = strtolower((string) $field->Attributes()->name);
					$fieldValue = trim((string) $field);

					if ($fieldValue != ''){

						// remove html
						$fieldValue = str_replace("<p>", "", $fieldValue);
						$fieldValue = str_replace(array("</p>", "</br>"), " ", $fieldValue);
						$fieldValue = str_replace("  ", " ", $fieldValue);
						
						// see if we can add the protocols to the description
						if (strpos($fieldName, '_protocol') >= 1){
							$dataset['description'][] = trim(ucfirst(str_replace(array("protocol","_"), " ", $fieldName))) . ': ' . $fieldValue;
						}

						if ($fieldName == 'technology_type'){ $metadata['analysis'] = $fieldValue; }
						if ($fieldName == 'instrument_platform'){ $metadata['platform'] = $fieldValue; }
						if ($fieldName == 'organism'){ $organisms[] = $fieldValue; }
						if ($fieldName == 'organism part'){ $organism_parts[] = $fieldValue; }
						if ($fieldName == 'metabolite_name'){ $metabolites[] = $fieldValue; }

					}
				}			

				// organism
				$organisms = array_unique($organisms);
				if (count($organisms) > 1){
					$metadata['organism'] = array();
					foreach ($organisms as $organism){ $metadata['organism'][] = $organism; }
				} else if (count($organisms) == 1){ $metadata['organism'] = $organisms[0]; }	

				// organism part
				$organism_parts = array_unique($organism_parts);
				if (count($organism_parts) > 1){
					$metadata['organism_parts'] = array();
					foreach ($organism_parts as $organism_part){ $metadata['organism_parts'][] = $organism_part; }
				} else if (count($organism_parts) == 1){ $metadata['organism_parts'] = $organism_parts[0]; }					
				
				// metabolites
				$metabolites = array_unique($metabolites);
				if (count($metabolites) > 1){
					$dataset['meta']['metabolites'] = array();
					foreach ($metabolites as $metabolite){ $metadata['metabolites'][] = $metabolite; }
				} else if (count($metabolites) == 1){ $metadata['metabolites'] = $metabolites[0]; }	

				$dataset['meta'] = $metadata;
				$datasets['datasets'][$dataset['accession']] = $dataset;				
			}

		    $z->next('entry');
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
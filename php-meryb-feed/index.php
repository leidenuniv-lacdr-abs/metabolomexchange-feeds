<?php

/**
 * Copyright 2014 Michael van Vliet (Leiden University), Thomas Hankeijer 
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

	// convert feed http://services.cbib.u-bordeaux2.fr/MERYB/mx_feed.xml
	$jsonResponse = "";
	$datasets = array();

	$feedUrl = 'http://services.cbib.u-bordeaux2.fr/MERYB/mx_feed.xml';
	$feedXML = file_get_contents($feedUrl);

	$feed = simplexml_load_string($feedXML);
	
	foreach ($feed->entries->entry as $idx => $dataRecord) {

		//print_r($dataRecord);
			
		$dataset = array();

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

		// additional fields
		$organisms 		= array();
		$metabolites	= array();

		$dataset['submitter']	= 'Daniel Jacob';
		$dataset['meta'] 		= array();			
		foreach ($dataRecord->additional_fields->field as $field){

			$fieldName = (string) $field->Attributes()->name;
			$fieldValue = (string) $field;

			if ($fieldName == 'submitter'){ $dataset['submitter'] = $fieldValue; }
			if ($fieldName == 'technology_type'){ $dataset['meta']['analysis'] = $fieldValue; }
			if ($fieldName == 'platform'){ $dataset['meta']['platform'] = $fieldValue; }
			if ($fieldName == 'organism'){ $organisms[] = $fieldValue; }
			if ($fieldName == 'metabolite_name'){ $metabolites[] = $fieldValue; }

		}			

		// organism
		if (count($organisms) > 1){
			$dataset['meta']['organism'] = array();
			foreach ($organisms as $organism){ $dataset['meta']['organism'][] = $organism; }
		} else if (count($organisms) == 1){ $dataset['meta']['organism'] = $organisms[0]; }	
		
		// metabolites
		if (count($metabolites) > 1){
			$dataset['meta']['metabolites'] = array();
			foreach ($metabolites as $metabolite){ $dataset['meta']['metabolites'][] = $metabolite; }
		} else if (count($metabolites) == 1){ $dataset['meta']['metabolites'] = $metabolites[0]; }	

		$datasets[$accession] = $dataset;
	}

	echo json_encode(array_values($datasets));
?>
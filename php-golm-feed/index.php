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

	// convert feed http://gmd.mpimp-golm.mpg.de/webservices/dciProfile.ashx
	$jsonResponse = "";
	$datasets = array();

	$feedUrl = 'http://gmd.mpimp-golm.mpg.de/webservices/dciProfile.ashx';
	$feedXML = file_get_contents($feedUrl);

	$feed = simplexml_load_string($feedXML);
	
	foreach ($feed as $idx => $dataRecord) {
		
		$dataset = array();

		$accession	= trim($dataRecord->Header->RecordIdentifier, '{}');
		
		$dataset['accession']	= (string) $accession;
		$dataset['date']		= (string) substr($dataRecord->BibliographicData->Source->PublicationYear,0,10);
		$dataset['url']			= (string) $dataRecord->BibliographicData->Source->SourceURL;
		$dataset['description']	= (string) str_replace("\n"," ", $dataRecord->Abstract);		
		
		// submitter
		$dataset['submitter']	= '';
		foreach ($dataRecord->BibliographicData->AuthorList->Author as $authorIdx => $author){
			$authorAttribs = $author->Attributes();
			if ($authorAttribs['seq'] == 1){
				$dataset['submitter'] = (string) $author->AuthorName;
			}
		}
		
		// title
		$dataset['title']	= '';
		foreach ($dataRecord->BibliographicData->TitleList->ItemTitle as $titleIdx => $title){
			$titleAttribs = $title->Attributes();			
			if ($titleAttribs['TitleType'] == 'English title'){
				$dataset['title'] = (string) $title;
			}
		}		

		// metadata
		$metadata = array();
		$metadata['institute']	= (string) $dataRecord->Header->Owner;
		$metadata['datatype']		= (string) $dataRecord->DescriptorsData->DataType;
		
		// principal_investigator
		$metadata['principal_investigator']	= '';
		foreach ($dataRecord->BibliographicData->AuthorList->Author as $authorIdx => $author){
			$authorAttribs = $author->Attributes();			
			if ($authorAttribs['AuthorRole'] == 'principal investigator'){
				$metadata['principal_investigator'] = (string) $author->AuthorName;
			}
		}
		
		// organism
		$organisms = $dataRecord->DescriptorsData->OrganismList->OrganismName;
		if (count($organisms) > 1){
			$metadata['organism'] = array();
			foreach ($organisms as $organism){ $metadata['organism'][] = (string) $organism; }
		} else if (count($organisms) == 1){ $metadata['organism'] = (string) $organisms; }	
		
		$dataset['meta'] = $metadata;
		$datasets[$accession] = $dataset;
	}

	echo json_encode(array_values($datasets));
?>
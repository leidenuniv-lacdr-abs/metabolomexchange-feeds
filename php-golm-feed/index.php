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

	$feedReloadKey = 'myfeedreloadkey';

	// convert feed http://gmd.mpimp-golm.mpg.de/webservices/dciProfile.ashx
	$feedUrl = 'http://gmd.mpimp-golm.mpg.de/webservices/dciProfile.ashx';	
	$jsonResponse = "";

	// set/determine use of cache
	$cacheFile = md5($feedUrl) . '.cache';
    if (!file_exists($cacheFile) || (isset($_GET['rl']) && $_GET['rl'] == $feedReloadKey) || (file_exists($cacheFile) && (time() - filemtime($cacheFile)) > 24*60*60 ) ) {
		
		$datasets = array();

		// add JSON-LD context
		$datasets['@context'] = 'http://'.$_SERVER['HTTP_HOST'].'/contexts/datacatalog.jsonld';

		$datasets['name'] = 'Golm Metabolome Database';
		$datasets['shortname'] = 'golm';
		$datasets['url'] = 'http://gmd.mpimp-golm.mpg.de';
		$datasets['description'] = 'The Golm Metabolome Database (GMD) facilitates the search for and dissemination of reference mass spectra from biologically active metabolites quantified using gas chromatography (GC) coupled to mass spectrometry (MS). GC/MS profiling studies aiming at the identification of compounds from complex biological mixtures depend on the comparison of observed mass spectra and retention times with reference libraries such as the GMD. The GMD comprises mass spectra and retention time indices of pure reference substances and frequently observed mass spectral tags (MST: mass spectrum linked to chromatographic retention) of yet unidentified metabolites.';

		$datasets['datasets'] = array();

		$feedXML = file_get_contents($feedUrl);

		$feed = simplexml_load_string($feedXML);
		
		foreach ($feed as $idx => $dataRecord) {
			
			$dataset = array();

			// add JSON-LD context
			$dataset['@context'] = 'http://'.$_SERVER['HTTP_HOST'].'/contexts/dataset.jsonld';		

			$accession	= trim($dataRecord->Header->RecordIdentifier, '{}');

			$dataset['accession']	= (string) $accession;
			$dataset['date']		= (string) substr($dataRecord->BibliographicData->Source->PublicationYear,0,10);
			$dataset['url']			= (string) $dataRecord->BibliographicData->Source->SourceURL;
			$dataset['description']	= (string) str_replace("\n"," ", $dataRecord->Abstract);	

			$timestamp = ''; // convert date to timestamp
			if (isset($dataset['date']) && $dataset['date'] != ''){
				list($year, $month, $day) = explode('-', $dataset['date']);
				$timestamp = mktime(0, 0, 0, $month, $day, $year);
			}
			$dataset['timestamp'] = $timestamp;
			
			// submitter
			$dataset['submitter']	= array();
			foreach ($dataRecord->BibliographicData->AuthorList->Author as $authorIdx => $author){
				$authorAttribs = $author->Attributes();
				if ($authorAttribs['seq'] == 1){
					$dataset['submitter'][] = (string) $author->AuthorName;
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

			// add metadata JSON-LD
			$metadata['@context'] = 'http://'.$_SERVER['HTTP_HOST'].'/contexts/metadata.jsonld';				
			
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
			$datasets['datasets'][] = $dataset;
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
	} else { // use cached data
		$jsonResponse = file_get_contents($cacheFile);
	}

	echo $jsonResponse;
?>
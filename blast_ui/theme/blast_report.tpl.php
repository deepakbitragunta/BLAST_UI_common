 <script>
     window.onload = function() {
    if(!window.location.hash) {
        window.location = window.location + '#loaded';
        window.location.reload();
    }
}
 
</script>
<?php

/**
 * Display the results of a BLAST job execution
 *
 * Variables Available in this template:
 *   $xml_filename: The full path & filename of XML file containing the BLAST results
 *		@deepaksomanadh: $job_data = meta data related to the current job
 */

// Set ourselves up to do link-out if our blast database is configured to do so.
$linkout = FALSE;

if ($blastdb->linkout->none === FALSE && $blastdb->linkout->regex_type == 'custom') {
  $linkout = TRUE;
  $linkout_regex = $blastdb->linkout->regex;
  /* if (isset($blastdb->linkout->db_id->urlprefix) AND !empty($blastdb->linkout->db_id->urlprefix)) {
    $linkout_urlprefix = $blastdb->linkout->db_id->urlprefix;
  }
  else {
    $linkout = FALSE;
  } */
}


// Handle no hits. This following array will hold the names of all query
// sequences which didn't have any hits.
$query_with_no_hits = array();

// Furthermore, if no query sequences have hits we don't want to bother listing
// them all but just want to give a single, all-include "No Results" message.
$no_hits = TRUE;

?>

<!-- JQuery controlling display of the alignment information (hidden by default) -->
<script type="text/javascript">
  $(document).ready(function(){

    // Hide the alignment rows in the table
    // (ie: all rows not labelled with the class "result-summary" which contains the tabular
    // summary of the hit)
    $("#blast_report tr:not(.result-summary)").hide();
    $("#blast_report tr:first-child").show();

    // When a results summary row is clicked then show the next row in the table
    // which should be corresponding the alignment information
    $("#blast_report tr.result-summary").click(function(){
      $(this).next("tr").toggle();
      $(this).find(".arrow").toggleClass("up");
    });
  });
</script>

<style>
.no-hits-message {
  color: red;
  font-style: italic;
}
</style>

<p><strong>Download</strong>:
  <a href="<?php print '../../' . $html_filename; ?>">Alignment</a>,
  <a href="<?php print '../../' . $tsv_filename; ?>">Tab-Delimited</a>,
  <a href="<?php print '../../' . $xml_filename; ?>">XML</a>
</p>
<!--	@deepaksomanadh: For displaying BLAST command details -->

<strong style='margin-left:5em'>Input query sequence(s) </strong> 
<strong style='text-align:center;margin-left:15em'> Target Database selected</strong> 
<?php 
	// get input sequences from job_data variable

	$query_def = $job_id_data['query_def'];
	echo "<ol>";
	$count = 1;
	foreach($query_def as $row) {
		echo "<li>";
		if($count == 1) {
			// get database selected
				$row .= "<span style='text-align:center;margin-left:12em'>" . 	$job_id_data['db_name'] . "</span>";
		}
		$count++;
		echo  $row . "</li>";
	}
	echo "</ol>";
	
 ?> 

<strong> BLAST command executed:</strong> &nbsp;

<?php 
	//display the BLAST command without revealing the internal path
	$blast_cmd = $job_id_data['program'];
	foreach($job_id_data['options'] as $key => $value) {
			$blast_cmd .= ' -' . $key. ' ' . $value ;
	}
	print $blast_cmd;	
 ?>
<br>
<br>
<p>The following table summarizes the results of your BLAST. To see additional information
about each hit including the alignment, click on that row in the table to expand it.</p>

<?php

// Load the XML file
$xml = simplexml_load_file($xml_filename);

/**
 * We are using the drupal table theme functionality to create this listing
 * @see theme_table() for additional documentation
 */

if ($xml) {
  // Specify the header of the table
  $header = array(
    'number' =>  array('data' => '#', 'class' => array('number')),
    'query' =>  array('data' => 'Query Name', 'class' => array('query')),
    'hit' =>  array('data' => 'Hit Name', 'class' => array('hit')),
    'evalue' =>  array('data' => 'E-Value', 'class' => array('evalue')),
    'arrow-col' =>  array('data' => '', 'class' => array('arrow-col'))
  );

  $rows = array();
  $count = 0;

  // Parse the BLAST XML to generate the rows of the table
  // where each hit results in two rows in the table: 1) A summary of the query/hit and
  // significance and 2) additional information including the alignment
  foreach($xml->{'BlastOutput_iterations'}->children() as $iteration) {
    $children_count = $iteration->{'Iteration_hits'}->children()->count();
    if($children_count != 0) {
      foreach($iteration->{'Iteration_hits'}->children() as $hit) {
        if (is_object($hit)) {
          $count +=1;
          $zebra_class = ($count % 2 == 0) ? 'even' : 'odd';
          $no_hits = FALSE;
					$rounded_evalue = '';
						
							$score = $hit->{'Hit_hsps'}->{'Hsp'}->{'Hsp_score'};
							$evalue = $hit->{'Hit_hsps'}->{'Hsp'}->{'Hsp_evalue'};
					if (strpos($evalue,'e') != false) {
					 $evalue_split = explode('e', $evalue);
					 $rounded_evalue = round($evalue_split[0], 2, PHP_ROUND_HALF_EVEN);				    
						 $rounded_evalue .= 'e' . $evalue_split[1];
					}
					else { 
							$rounded_evalue = $evalue;
					}				
				
				  // ALIGNMENT ROW (collapsed by default)
					// Process HSPs
			
					$HSPs = array();
					$track_start = INF;
					$track_end = -1;
					$hsps_range = '';
							
					foreach ($hit->{'Hit_hsps'}->children() as $hsp_xml) {
						$HSPs[] = (array) $hsp_xml;
						$hsps_range .= $hsp_xml->{'Hsp_hit-from'} . '..' . $hsp_xml->{'Hsp_hit-to'} . ',' ;
					
					
						if($track_start > $hsp_xml->{'Hsp_hit-from'}) {
							$track_start = $hsp_xml->{'Hsp_hit-from'} . "";
						}
						if($track_end < $hsp_xml->{'Hsp_hit-to'}) {
							$track_end = $hsp_xml->{'Hsp_hit-to'} . "";
						}
					}
					$range_start = (int) $track_start - 20000;
					$range_end = (int) $track_end + 20000;
				
					if($range_start < 1) 
						 $range_start = 1;	
						 
					// SUMMARY ROW
					// If the id is of the form gnl|BL_ORD_ID|### then the parseids flag
					// to makeblastdb did a really poor job. In this case we want to use
					// the def to provide the original FASTA header.
					//$hit_name = (preg_match('/BL_ORD_ID/', $hit->{'Hit_id'})) ? $hit->{'Hit_def'} : $hit->{'Hit_id'};
					$hit_name = $hit->{'Hit_def'};
					$query_name = $iteration->{'Iteration_query-def'};
					
					// ***** Future modification ***** The gbrowse_url can be extracted from Tripal Database table			
					
					// $hit_name_url = l($linkout_urlprefix . $linkout_match[1],
					// array('attributes' => array('target' => '_blank'))
					//  );
					
					// Link out functionality to GBrowse
					// Link out is possible for this hit
					
					// Check if our BLAST DB is configured to handle link-outs then use the
					// regex & URL prefix provided to create one. 
					// Then, check if the db is configured to handle linkouts
					// For alias targets
					
					if ($linkout) {
						// For CDS/protein alias targets	
						if(preg_match('/.*(aradu).*/i', $hit_name) == 1) {
							$gbrowse_url =   'http://peanutbase.org/gbrowse_aradu1.0';
						}
						else if(preg_match('/.*(araip).*/i', $hit_name) == 1) {
							$gbrowse_url =  'http://peanutbase.org/gbrowse_araip1.0';
						}
						else if(preg_match('/.*(cicar).*/i', $hit_name) == 1) {
							$gbrowse_url =  'gbrowse_cicar1.0';
						}
						else if(preg_match('/.*(glyma).*/i', $hit_name) == 1) {
							$gbrowse_url =  'http://soybase.org/gb2/gbrowse/gmax2.0/';
						}
						else if(preg_match('/.*(lotja).*/i', $hit_name) == 1) {
							$gbrowse_url =  'gbrowse_lotja2.5';
						}
						else if(preg_match('/.*(medtr).*/i', $hit_name) == 1) {
							$gbrowse_url =  'gbrowse_medtr4.0';
						}
						else if(preg_match('/.*(cajca).*/i', $hit_name) == 1) {
							$gbrowse_url =  'gbrowse_cajca1.0';
						}
						else if(preg_match('/.*(phavu).*/i', $hit_name) == 1) {
							$gbrowse_url =  'gbrowse_phavu1.0';
						}
						else if(preg_match('/.*(phytozome).*/i', $hit_name) == 1) {
							$gbrowse_url =  'chado_phylotree';
						}	
						else {
							$gbrowse_url = null;
						}	
						
						if(preg_match('/.*(phytozome).*/i', $hit_name) == 1) {
								$hit_url =  	$GLOBALS['base_url'] . '/' . $gbrowse_url . '/' . $hit_name;
								$hit_name_url = l($hit_name, $hit_url, array('attributes' => array('target' => '_blank')));
							}	
						else if ((preg_match($linkout_regex, $hit_name, $linkout_match) == 1) && $gbrowse_url != null) {
							// matches found 
							if(preg_match("/http:/",  $gbrowse_url) == 1) {
								$hit_url = 	$gbrowse_url . '?' . 'query=q=';
								$hit_name =  $linkout_match[1];
								if(preg_match("/soybase.org/",  $gbrowse_url) == 1) {
									$hit_url = 	$gbrowse_url . '?' . 'q=';
									$hit_names = explode('.', $linkout_match[1]);
									$hit_name = $hit_names[0] . '.' . $hit_names[1];  
								} 
								$hit_url .= $hit_name . ';h_feat=' . $iteration->{'Iteration_query-ID'};
							}	
							else {
								$hit_url = 	$GLOBALS['base_url'] . '/' . $gbrowse_url . '?' . 'query=q=';
								$hit_name = $linkout_match[1];
								$hit_url .= $hit_name . ';h_feat=' . $iteration->{'Iteration_query-ID'};
							}											
							$hit_name_url = l($hit_name, $hit_url, array('attributes' => array('target' => '_blank')));

						} 
						else {
							// No matches for regex. Hence, linkouts not possible
							$hit_name_url = $hit_name;								
						}
					}		
					else {
						// For Genome targets
					
						if(preg_match('/aradu/i', $hit_name) == 1) {
							$gbrowse_url =   'http://peanutbase.org/gbrowse_aradu1.0';
						}
						else if(preg_match('/araip/i', $hit_name) == 1) {
							$gbrowse_url =  'http://peanutbase.org/gbrowse_araip1.0';
						}
						else if(preg_match('/Ca\d/i', $hit_name) == 1) {
							$gbrowse_url =  'gbrowse_cicar1.0';
							$hit_name_parts = explode(' ', $hit_name);
							$hit_name = $hit_name_parts[0];
						}
						else if(preg_match('/Gm/i', $hit_name) == 1) {
							$gbrowse_url =  'http://soybase.org/gb2/gbrowse/gmax2.0/';
						}
						else if(preg_match('/Lj/i', $hit_name) == 1) {
							$gbrowse_url =  'gbrowse_lotja2.5';
						}
						else if(preg_match('/Mt/i', $hit_name) == 1) {
							$gbrowse_url =  'gbrowse_medtr4.0';
						}
						else if(preg_match('/Cc/i', $hit_name) == 1) {
							$gbrowse_url =  'gbrowse_cajca1.0';
						}
						else if(preg_match('/Pv/i', $hit_name) == 1) {
							$gbrowse_url =  'gbrowse_phavu1.0';
						}
						else if(preg_match('/scaffold/i', $hit_name) == 1){
							$gbrowse_url = null;
						}	
						else {
							$gbrowse_url = null;
						}
			
						if($gbrowse_url == null) {
							$hit_name_url = $hit_name;
						}
						else {
							if(preg_match("/http:/",  $gbrowse_url) == 1) {
								$hit_url = 	$gbrowse_url;
							}
							else {
								$hit_url = 	$GLOBALS['base_url'] . '/' .  $gbrowse_url ;
							}
							$hit_url = $hit_url . '?' . 'query=' . 'start=' . $range_start . ';' . 'stop=' . $range_end . ';' .
															'ref=' . $hit_name . ';' . 'add=' . $hit_name . '+'	. 'BLAST+' . $iteration->{'Iteration_query-ID'} .
															'+' . $hsps_range . ';h_feat=' . $iteration->{'Iteration_query-ID'} ; 
															
							$hit_name_url = l($hit_name, $hit_url, array('attributes' => array('target' => '_blank')));
						}
					}
			
          $row = array(
            'data' => array(
              'number' => array('data' => $count, 'class' => array('number')),
              'query' => array('data' => $query_name, 'class' => array('query')),
              'hit' => array('data' => $hit_name_url, 'class' => array('hit')),
              'evalue' => array('data' => $rounded_evalue, 'class' => array('evalue')),
              'arrow-col' => array('data' => '<div class="arrow"></div>', 'class' => array('arrow-col'))
            ),
            'class' => array('result-summary')
          );
          $rows[] = $row;
					

          $row = array(
            'data' => array(
              'number' => '',
              'query' => array(
                'data' => theme('blast_report_alignment_row', array('HSPs' => $HSPs)),
                'colspan' => 4,
              )
            ),
            'class' => array('alignment-row', $zebra_class),
            'no_striping' => TRUE
          );
          $rows[] = $row;

        }// end of if - checks $hit
      } //end of foreach - iteration_hits
    }	// end of if - check for iteration_hits
    else {

      // Currently where the "no results" is added.
      $query_name = $iteration->{'Iteration_query-def'};
      $query_with_no_hits[] = $query_name;

		} // end of else
  }

  if ($no_hits) {
    print '<p class="no-hits-message">No results found.</p>';
  }
  else {
    // We want to warn the user if some of their query sequences had no hits.
    if (!empty($query_with_no_hits)) {
      print '<p class="no-hits-message">Some of your query sequences did not '
      . 'match to the database/template. They are: '
      . implode(', ', $query_with_no_hits) . '.</p>';
    }

    // Actually print the table.
    if (!empty($rows)) {
      print theme('table', array(
        'header' => $header,
        'rows' => $rows,
        'attributes' => array('id' => 'blast_report'),
      ));
    }
  }
}
else {
  drupal_set_title('BLAST: Error Encountered');
  print '<p>We encountered an error and are unable to load your BLAST results.</p>';
}

?>
<p> <!--	@deepaksomanadh: Building the edit and resubmit URL --> 
	 <a style ="align:center" href="<?php print '../../'. $job_id_data['job_url'] . '?jid=' . base64_encode($job_id) ?>">Edit this query and re-submit</a>	
</p>
<strong> Recent Jobs </strong>
	<ol>
	<?php
			$sid = session_id();	
			$jobs = $_SESSION['all_jobs'][$sid];
	
			foreach ( $jobs as $job) {
				echo "<li>";
				echo "<a href='" . "../../" . $job['job_output_url'] ."' >"  
								. $job['query_defs'][0] ."->". $job['program'] . "</a>";
				echo "</li>";
			}
	?>
	</ol>
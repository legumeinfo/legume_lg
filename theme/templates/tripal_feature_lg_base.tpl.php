<?php
  $feature  = $variables['node']->feature;  
  $feature_id = $feature->feature_id;
//echo "<pre>";var_dump($feature);echo "</pre>";

  // Always want to expand joins as arrays regardless of how many matches
  //   there are
  $table_options = array('return_array' => true);
  
  // Organism
  $organism = $feature->organism_id->genus 
            . " " . $feature->organism_id->species 
            ." (" . $feature->organism_id->common_name .")";
  if (property_exists($feature->organism_id, 'nid')) {
    $text = "<i>" . $feature->organism_id->genus . " " 
           . $feature->organism_id->species 
           . "</i> (" . $feature->organism_id->common_name .")";
    $url = "node/".$feature->organism_id->nid;
    $organism = l($text, $url, array('html' => TRUE));
  } 

  // Map
  $map = 'unknown';
  $sql = "
    SELECT fm.name, cfm.nid FROM {featuremap} fm
      INNER JOIN chado_featuremap cfm ON cfm.featuremap_id=fm.featuremap_id
    WHERE fm.featuremap_id=
      (SELECT DISTINCT featuremap_id FROM {featurepos}
       WHERE feature_id=$feature_id
      )";
  if ($res=chado_query($sql)) {
    while ($row=$res->fetchObject()) {
      $url = '/node/' . $row->nid;
      $map = "<a href=\"$url\">" . $row->name . "</a>";
    }
  }
  
  // Publications
  $pubs = array();
  $sql = "
    SELECT p.uniquename, cp.nid FROM {pub} p
      INNER JOIN chado_pub cp ON cp.pub_id=p.pub_id
    WHERE p.pub_id IN
      (SELECT pub_id FROM {featuremap_pub} fmp 
       WHERE fmp.featuremap_id = 
         (SELECT DISTINCT featuremap_id FROM {featurepos} fp 
          WHERE feature_id=$feature_id
         )
      )";
  if ($res=chado_query($sql)) {
    while ($row=$res->fetchObject()) {
      $url = '/node/' . $row->nid;  // NOTE: assumes all maps are sync-ed!
      $pub_html = l($row->uniquename, $url);
      $pubs[] = $pub_html;
    }//each pub row
  }

  // Size
  $feature = chado_expand_var($feature, 'table', 'featurepos', $table_options);
  $featurepos = $feature->featurepos;
  foreach ($featurepos as $pos) {
    $sql = "
      SELECT fp.*, c.name AS type FROM {featureposprop} fp
        INNER JOIN {cvterm} c ON c.cvterm_id=fp.type_id
      WHERE featurepos_id = " . $pos[0]->featurepos_id;
    $res = chado_query($sql, array());
    while ($row=$res->fetchObject()) {
      if ($row->type == 'start') {
        $start = $pos[0]->mappos;
      }
      else {
        $stop = $pos[0]->mappos;
      }
    }
  }

  // Marker count
  $marker_count = 'unknown';
  $sql = "
    SELECT COUNT(*) FROM {featurepos}
    WHERE map_feature_id=" . $feature->feature_id;
  $res = chado_query($sql, array());
  while ($row=$res->fetchObject()) {
    $marker_count = $row->count . "<br>";
  }
  
  // Get properties
  $properties = array();
  $feature = chado_expand_var($feature, 'table', 'featureprop', $table_options);
  $props = $feature->featureprop;
  foreach ($props as $prop){
    $prop = chado_expand_var($prop, 'field', 'featureprop.value');
    $properties[$prop->type_id->name] = $prop->value;
  }
?>

<div class="tripal_feature-data-block-desc tripal-data-block-desc"></div> <?php
 
  // the $headers array is an array of fields to use as the colum headers. 
  // additional documentation can be found here 
  // https://api.drupal.org/api/drupal/includes%21theme.inc/function/theme_table/7
  // This table for the analysis has a vertical header (down the first column)
  // so we do not provide headers here, but specify them in the $rows array below.
  $headers = array();
  
  // the $rows array contains an array of rows where each row is an array
  // of values for each column of the table in that row.  Additional documentation
  // can be found here:
  // https://api.drupal.org/api/drupal/includes%21theme.inc/function/theme_table/7 
  $rows = array();
  
  // Full name
  $rows[] = array(
    array(
      'data' => 'Full name',
      'header' => TRUE,
      'width' => '20%',
    ),
    $feature->name
  );

  // Short (common) name
  $rows[] = array(
    array(
      'data' => 'Short Linkage Group Name',
      'header' => TRUE,
      'width' => '20%',
    ),
    $properties['Assigned Linkage Group'],
  );

  // Synonyms, if any
  if (($marker->synonyms && $marker->synonyms != '{}')
        || ($marker->markers && $marker->markers != '{}')) {
    $synonyms = array_unique(
                  array_merge(
                    explode(',', preg_replace('/[\{\}]/', '', $marker->synonyms)),
                    explode(',', preg_replace('/[\{\}]/', '', $marker->markers)))
    );
    // messy, but might be blank synonyms
    for ($i=0;$i<count($synonyms); $i++) {
      if ($synonyms[$i] == '') {
        unset($synonyms[$i]);
      }
    }
    $rows[] = array(
      array(
        'data' => 'Synonyms',
        'header' => TRUE
      ),
      implode(', ', $synonyms),
    );
  }

  // CMap linkout (if exits)
  $feature = chado_expand_var($feature, 'table', 'feature_dbxref', $table_options);
  $dbxref = $feature->feature_dbxref[0]->dbxref_id;
  if ($dbxref && $dbxref->db_id->name == 'LIS:cmap') {
    $url = $dbxref->db_id->urlprefix . $dbxref->accession;
    $acc_text = "<a href=\"$url\">CMap</a>";
  }
  else {
    $acc_text = "n/a";
  }
  $rows[] = array(
    array(
      'data' => 'View Linkage Group',
      'header' => TRUE,
      'width' => '20%',
    ),
    $acc_text,
  );

  // Organism row
  $rows[] = array(
    array(
      'data' => 'Organism',
      'header' => TRUE,
    ),
    $organism
  );

  $rows[] = array(
    array(
      'data' => 'Coordinates',
      'header' => TRUE
    ),
    "$start - $stop cM",
  );

  // Marker count
  $rows[] = array(
    array(
      'data' => 'Number of Markers',
      'header' => TRUE
    ),
    $marker_count,
  );

  // Map
  $rows[] = array(
    array(
      'data' => 'Map Set',
      'header' => TRUE
    ),
    $map,
  );

  // Publications
  $rows[] = array(
    array(
      'data' => 'Publication(s)',
      'header' => TRUE
    ),
    implode($pubs, '<br>'),
  );

  // Comments
  if (array_key_exists('comment', $properties)) {
    $rows[] = array(
      array(
        'data' => 'Comments',
        'header' => TRUE,
      ),
      $properties['comment'],
    );
  }

  /////// SEPARATOR /////////
  $rows[] = array(
    array(
      'data' => '',
      'header' => TRUE,
      'height' => 6,
      'style' => 'background-color:white',
    ),
    array(
      'data' => '',
      'style' => 'background-color:white',
    ),
  );


  // allow site admins to see the feature ID
  if (user_access('view ids')) { 
    // Feature ID
    $rows[] = array(
      array(
        'data' => 'Feature ID',
        'header' => TRUE,
        'class' => 'tripal-site-admin-only-table-row',
      ),
      array(
        'data' => $feature->feature_id,
        'class' => 'tripal-site-admin-only-table-row',
      ),
    );
  }
  
  // the $table array contains the headers and rows array as well as other
  // options for controlling the display of the table.  Additional
  // documentation can be found here:
  // https://api.drupal.org/api/drupal/includes%21theme.inc/function/theme_table/7
  $table = array(
    'header' => $headers,
    'rows' => $rows,
    'attributes' => array(
      'id' => 'tripal_feature-table-base',
      'class' => 'tripal-data-table'
    ),
    'sticky' => FALSE,
    'caption' => '',
    'colgroups' => array(),
    'empty' => '',
  );
  
  // once we have our table array structure defined, we call Drupal's theme_table()
  // function to generate the table.
  print theme_table($table); 


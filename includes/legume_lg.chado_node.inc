<?php
/**
 * @file
 * Implementation of hooks to create a feature content type
 */



/**
 * Implements hook_node_view().
 * Acts on all content types.
 *
 * @ingroup legume_lg
 */
function legume_lg_node_view($node, $view_mode, $langcode) {
  if ($node->type != 'chado_feature') { return; }

  $path = drupal_get_path('module', 'legume_lg');
  
  switch ($view_mode) {
    case 'full':
      if ($node->feature->type_id->name == 'linkage_group') {
        // we want to override the base feature theme
        $node->content['tripal_feature_base'] = array(
          '#markup' => theme("tripal_feature_lg_base",
                             array('node' => $node)),
          '#tripal_toc_id'    => 'base',
          '#tripal_toc_title' => 'Linkage Group Overview',
          '#weight' => -100,
        );
        $node->content['tripal_feature_lg_base'] = array(
          '#markup' => theme("tripal_feature_lg_base",
                             array('node' => $node)),
          '#tripal_toc_id'    => 'lg_base',
          '#tripal_toc_title' => 'Linkage Group Overview',
          '#weight' => -100,
        );

      }
      break;
      
    case 'teaser':
      break;
      
    default:
      break;
  }
}



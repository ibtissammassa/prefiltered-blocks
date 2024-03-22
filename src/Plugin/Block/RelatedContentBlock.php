<?php

/**
 * @file
 * Creates a block that displays the related content
 */

 namespace Drupal\prefiltered_blocks\Plugin\Block;

 use Drupal\Core\Block\BlockBase;
 use Drupal\Core\Block\Attribute\Block;
 use Drupal\Core\StringTranslation\TranslatableMarkup;
 use Drupal\Core\Session\AccountInterface;
 use Drupal\Core\Access\AccessResult;
 use Drupal\taxonomy\Entity\Vocabulary;

 /**
 * Provides the related content block.
 */
#[Block(
    id: "related_content_block",
    admin_label: new TranslatableMarkup("Related content Block")
  )]

  class RelatedContentBlock extends BlockBase {
    /**
   * Get the taxonomy field name associated with a content type.
   * @param string $content_type
   * @return string|null
   * 
   */
    protected function getTaxonomyFieldName($node) {
      $field_defs = $node->getFieldDefinitions();
      foreach($field_defs as $field) {
        $field_settings = $field->getSettings();
        if(isset($field_settings) && isset($field_settings['target_type'])){
          $field_type = $field_settings['target_type'];
          if($field_type=='taxonomy_term'){
            $field_name = $field->getName();
            return $field_name;
          }
        }
      }
    }

    /**
     * {@inheritdoc}
     */
    public function build() {
        $node = \Drupal::routeMatch()->getParameter('node');
        $related_content = [];
        $content_type = $node->getType();

        // Get the taxonomy field name associated with the content type
        $taxonomy_field_name = $this->getTaxonomyFieldName($node);

        if (empty($taxonomy_field_name)) {
            return ['#markup' => $this->t('No related content found.')];
        }

        // Get the term IDs associated with the current node
        $term_ids = [];
        foreach ($node->get($taxonomy_field_name) as $term_reference) {
          $term_ids[] = $term_reference->target_id;
        }
  
        // Query related nodes
        $query = \Drupal::entityQuery('node')
          ->accessCheck(TRUE)
          ->condition('type', $content_type)
          ->condition('status', 1) // Published
          ->condition('nid', $node->id(), '<>') // Exclude the current node
          ->condition($taxonomy_field_name, $term_ids, 'IN')
          ->range(0, 3) // three related nodes
          ->sort('created', 'DESC'); // Sort by creation date
  
        $related_node_nids = $query->execute();
  
        // Load the related nodes
        $related_content = \Drupal\node\Entity\Node::loadMultiple($related_node_nids);
        // Render
        $build = [];
        if (!empty($related_content)) {
          $entity_type_manager = \Drupal::entityTypeManager();
          $node_view_builder = $entity_type_manager->getViewBuilder('node');
          $view_mode = 'teaser';
          $content = $node_view_builder->viewMultiple($related_content, $view_mode);
          // Render the nodes using a custom Twig template
          $build = [
            '#theme' => 'related',
            '#nodes' => $content,
          ];
          return $build;	
        
          }
          return ['#markup' => $this->t('No related content found.')];
    }

    /**
     * {@inheritDoc}
     */
    public function blockAccess(AccountInterface $account){
        // If viewing a node
        $node = \Drupal::routeMatch()->getParameter('node');

        if (!(is_null($node))) {
            return AccessResult::allowedIfHasPermission($account,'view related content');
        }
        return AccessResult::forbidden();                       
    }
  
  }
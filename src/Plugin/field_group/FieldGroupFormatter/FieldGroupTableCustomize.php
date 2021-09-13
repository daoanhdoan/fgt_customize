<?php

namespace Drupal\fgt_customize\Plugin\field_group\FieldGroupFormatter;

use Drupal\Component\Utility\Html;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Render\Element;
use Drupal\field_group_table\Plugin\field_group\FieldGroupFormatter\FieldGroupTable;

/**
 * Plugin implementation of the 'field_group_table' formatter.
 *
 * @FieldGroupFormatter(
 *   id = "fgt_customize",
 *   label = @Translation("Table(Customize)"),
 *   description = @Translation("This fieldgroup renders fields in a 2-column table with the label in the left column, and the value in the right column."),
 *   supported_contexts = {
 *     "form",
 *     "view",
 *   }
 * )
 */
class FieldGroupTableCustomize extends FieldGroupTable implements ContainerFactoryPluginInterface
{
  /**
   * {@inheritdoc}
   */
  public function settingsSummary()
  {
    $summary = parent::settingsSummary();
    return $summary;
  }

  /**
   * {@inheritdoc}
   */
  public function preRender(&$element, $rendering_object)
  {
    $element['#mode'] = $this->context;
    if (!empty($this->group->children)) {
      foreach($this->group->children as $children) {
        if (!isset($element[$children]) && isset($rendering_object[$children])) {
          $element[$children] = $rendering_object[$children];
        }
      }
    }
    parent::preRender($element, $rendering_object);

    $element['table']['#attached'] = ['library' => ['fgt_customize/fgt_customize']];
    $element['table']['#attributes']['class'][] = 'fgt-customize-table';
  }

  /**
   * Build table row for requested element.
   *
   * @param array $element
   *   Rendering array of an element.
   * @param string $field_name
   *   The name of currently handling field.
   *
   * @return array
   *   Table row definition on success or an empty array otherwise.
   */
  public function buildRow(array $element, $field_name)
  {
    $build = parent::buildRow($element, $field_name);
    $build['#attributes']['class'][] = 'table-row';
    $build['#attributes']['no_striping'] = !$this->getSetting('table_row_striping');
    return $build;
  }

  /**
   * Build table row for a "view" context.
   *
   * @param array $element
   *   Rendering array of an element.
   *
   * @return array
   *   Table row for a "view" context.
   */
  protected function buildRowView(array $element)
  {
    $label_display = isset($element['#label_display']) ? $element['#label_display'] : '';
    $title_data = $this->getElementTitleData($element);
    $build = [];

    // Display the label in the first column,
    // if 'always show field label' is set.
    if ($this->getSetting('always_show_field_label')) {
      $build = [
        [
          'title' => ['#type' => 'label', '#title' => $title_data['title'], '#title_display' => $label_display],
          '#wrapper_attributes' => ['class' => ['table-row-label']]
        ],
        [
          'data' => $element,
        ],
      ];
    }

    // Display the label in the first column,
    // if it's set to "above" and the title isn't empty.
    elseif ($title_data['title'] && $label_display === 'above') {
      $this->hideElementTitle($element);
      $build = [
        [
          'title' => ['#type' => 'label', '#title' => $title_data['title'], '#title_display' => $label_display],
          '#wrapper_attributes' => ['class' => ['table-row-label']],
        ],
        [
          'data' => $element,
        ],
      ];
    }

    // Display an empty cell if we won't display the title and
    // 'empty label behavior' is set to keep empty label cells.
    elseif ($this->getSetting('empty_label_behavior') == self::EMPTY_LABEL_KEEP) {
      $build = [
        [
          'title' => ['#title' => '', '#title_display' => $label_display],
          '#wrapper_attributes' => ['class' => ['table-row-label']]
        ],
        [
          'data' => $element,
        ],
      ];
    } // Otherwise we merge the cells.
    else {
      $build['data'] = [
        [
          'data' => [$element],
          'colspan' => 2,
        ],
      ];
    }

    if (isset($element['#field_name'])) {
      $build['#attributes']['class'][] = Html::cleanCssIdentifier($element['#field_name']);
    }
    if (isset($element['#field_type'])) {
      $build['#attributes']['class'][] = 'type-' . Html::cleanCssIdentifier($element['#field_type']);
    }

    return $build;
  }

  /**
   * Build table row for a "form" context.
   *
   * @param array $element
   *   Rendering array of an element.
   *
   * @return array
   *   Table row for a "form" context.
   */
  protected function buildRowForm(array $element)
  {
    $title_data = $this->getElementTitleData($element);
    if ($title_data['title'] || $this->getSetting('empty_label_behavior') == self::EMPTY_LABEL_KEEP) {
      if (!$this->getSetting('always_show_field_label')) {
        $this->hideElementTitle($element);
      }
      $build = [
        [
          'title' => [
            '#type' => 'label',
            '#title' => $title_data['title'],
            '#required' => $title_data['required'],
          ],
          '#wrapper_attributes' => ['class' => ['table-row-label']],
        ],
        [
          'data' => $element['widget'],
          '#wrapper_attributes' => ['class' => ['table-row-data']],
        ],
      ];
    } else {
      $build = [
        [
          'data' => [$element['widget']],
          '#wrapper_attributes' => ['colspan' => 2, 'class' => ['table-row-data']],
        ],
      ];
    }
    if (isset($element['widget'], $element['widget']['#field_name'])) {
      $build['#attributes']['class'][] = Html::cleanCssIdentifier($element['widget']['#field_name']);
    }

    return $build;
  }

}

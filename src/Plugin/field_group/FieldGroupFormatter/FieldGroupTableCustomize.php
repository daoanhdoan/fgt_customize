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
    $element['#mode'] = $this->context;
    // Allow modules to alter the rows, useful for removing empty rows.
    $children = Element::children($element, TRUE);
    $this->moduleHandler->alter('field_group_table_rows', $element, $children);

    if ($this->getSetting('hide_table_if_empty')) {
      field_group_remove_empty_display_groups($element, []);
      if ($element == []) {
        return;
      }
    }

    $element['#type'] = 'container';
    $element['#attributes']['class'][] = 'field-group-table';
    $element['#attributes']['class'][] = $this->group->group_name;

    $element['header'] = $this->buildAdditionalContent(self::ADD_CONTENT_HEADER);

    $element['table'] = [
      '#type' => 'table',
      '#caption' => $this->getSetting('label_visibility') == self::DISPLAY_CAPTION ? $this->group->label : NULL,
      '#header' => $this->getTableHeader(),
      '#attributes' => [
        'class' => array_merge(
          $this->getTableCssClasses($element),
          explode(' ', $this->getSetting('classes'))
        ),
      ]
    ];

    $element['footer'] = $this->buildAdditionalContent(self::ADD_CONTENT_FOOTER);

    foreach ($children as $key => $field_name) {
      if ($row = $this->buildRow($element, $field_name)) {
        $element['table'][$field_name] = $row;
      }
      unset($element[$field_name]);
    }

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
    $item = $this->getRowItem($element, $field_name);
    $build = [];

    if (!$item) {
      return $build;
    }

    switch ($this->context) {

      case 'view':
        $build = $this->buildRowView($item);
        break;

      case 'form':
        $build = $this->buildRowForm($item);
        break;
    }
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
      $field_name = $element['widget']['#field_name'];
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
          $field_name => $element['widget'],
          '#wrapper_attributes' => ['class' => array_merge($element['#attributes']['class'], ['table-row-data'])],
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

<?php
/**
 * @var stdClass      $options
 * @var boolean       $isNetworkClone
 * @var boolean       $showAll
 * @var array|boolean $selected
 *
 * @see \WPStaging\Backend\Administrator::ajaxPushScan
 * @see \WPStaging\Backend\Administrator::ajaxPushTables
 */

if (isset($options->tables)) {
    foreach ($options->tables as $table) :
        $attributes = '';
        // Unselect tables if they are not included in the previous push selection
        if (in_array($table->name, $options->tablePushSelection)) {
            $attributes = 'selected';
        }

        $show = $attributes === 'selected';
        if (strpos($table->name, $options->prefix) === 0) {
            $show = true;
        }

        if ($selected !== false && is_array($selected)) {
            if (in_array($table->name, $selected)) {
                $attributes = 'selected';
            } else {
                $attributes = '';
            }
        }

        if ($showAll || $show) :
            ?>
            <option class="wpstg-db-table" value="<?php echo $table->name ?>" name="<?php echo esc_attr($table->name) ?>" <?php echo esc_attr($attributes) ?>>
                <?php echo esc_html($table->name) ?> - <?php echo esc_html(size_format($table->size, 2)) ?>
            </option>
            <?php
        endif;
    endforeach;
}
?>

<?php
require 'views/page_header.php';

$controller->render_messages();
?>

<?php $view['unit_copy_form']->begin_form(); ?>
    <legend>Unit Copy</legend>
    <div class="form-group">
        <label class="col-md-offset-4 col-md-2 control-label">Unit</label>
        <div class="col-md-2"><?php $view['unit_copy_form']->render_field('unit_id') ?></div>
    </div>
    <div class="form-group">
        <label class="col-md-offset-4 col-md-2 control-label">Copy to package</label>
        <div class="col-md-2"><?php $view['unit_copy_form']->render_field('package_id') ?></div>
    </div>
    <div class="form-group">
        <div class="col-md-offset-5 col-md-2"><?php $view['unit_copy_form']->submit_button("Copy"); ?></div>
    </div>
<?php $view['unit_copy_form']->end_form(); ?>

<hr>

<?php $view['node_move_form']->begin_form(); ?>
    <legend>Unit Move</legend>
    <div class="form-group">
        <label class="col-md-offset-4 col-md-2 control-label">Unit</label>
        <div class="col-md-2"><?php $view['node_move_form']->render_field('unit_id') ?></div>
    </div>
    <div class="form-group">
        <label class="col-md-offset-4 col-md-2 control-label">Move to package</label>
        <div class="col-md-2"><?php $view['node_move_form']->render_field('package_id') ?></div>
    </div>
    <div class="form-group">
        <div class="col-md-offset-5 col-md-2"><?php $view['node_move_form']->submit_button("Move"); ?></div>
    </div>
<?php $view['node_move_form']->end_form(); ?>


<?php
require 'views/page_footer.php';

<?php
/** @var \candb\controller\EnumTypeController $controller */
/** @var array $view */

$editing = $view['editing'];
$form = $view['form'];
$pkg = $view['pkg'];
$unit = $view['unit'];
$enum_type = $view['enum_type'];

// FIXME: hack s $editing
require 'views/page_header.php';

if ($enum_type) {
    ?>
    <div class="toolbar-container">
    <div class="container">
    <ul class="toolbar">
        <li><?php $form->render_edit_toggle($enum_type); ?></li>

        <?php if ($controller->user_is_admin()) { ?>
        <li><a href="<?= $enum_type->url_delete(); ?>" onclick="return confirm('Delete enum type? Please make sure it is not referenced by anything.')"><strong class="text-danger"><span class="glyphicon glyphicon-trash"></span>&ensp;Delete</strong></a></li>
        <?php } ?>
    </ul>
    </div>
    </div>
    <?php
}

$controller->render_messages();
?>

<?php $form->begin_form(); ?>
    <legend>Basic information</legend>
    <div class="form-group">
        <label class="col-md-2 control-label">Name</label>
        <div class="col-md-6"><?php $form->render_field('name') ?></div>
    </div>
    <div class="form-group">
        <label class="col-md-2 control-label">Description</label>
        <div class="col-md-10"><?php $form->render_field('description') ?></div>
    </div>
    <legend>Items</legend>
<?php $form->render_field('items'); ?>

    <div class="form-group">
        <div class="col-md-offset-5 col-md-2"><?php $form->submit_button("<span class='glyphicon glyphicon-save'></span>&ensp;Save"); ?></div>
    </div>
<?php $form->end_form(); ?>

    <script>
        $(function() {
            <?php $form->init_js(); ?>
        });
    </script>

<?php
require 'views/page_footer.php';

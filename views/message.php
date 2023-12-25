<?php
/** @var \candb\controller\MessageController $controller */

$editing = $view['editing'];
$form = $view['form'];
$pkg = $view['pkg'];
/** @var \candb\model\Message $message */
$message = $view['message']; // $message is always non-null, but $message->id will be null if inserting new message
$unit = $view['unit'];
$unit_id = $view['unit_id'];
$sent_by = $view['sent_by'];
$received_by = $view['received_by'];

$drc_num_errors = $view['drc_num_errors'];
$drc_num_warnings = $view['drc_num_warnings'];

// FIXME: hack s $editing
require 'views/page_header.php';

if ($message->id !== null) {
    ?>
    <div class="toolbar-container">
    <div class="container">
    <ul class="toolbar">
        <li><?php $form->render_edit_toggle($message); ?></li>

        <?php \candb\ui\GUICommon::drc_button($message, $drc_num_errors, $drc_num_warnings); ?>
        <?php if ($controller->user_is_admin()) { ?>
        <li><a href="<?= $message->url_delete(); ?>" onclick="return confirm('Delete message? Please make sure it is not referenced by anything.')"><strong class="text-danger"><span class="glyphicon glyphicon-trash"></span>&ensp;Delete</strong></a></li>
        <?php } ?>
    </ul>
    </div>
    </div>
    <?php
}

$controller->render_messages();
?>

<?php $form->begin_form(); ?>

<fieldset>
<legend>Basic information</legend>
<div class="form-group">
    <label class="col-md-2 control-label">Name</label>
    <div class="col-md-4"><?php $form->render_field('name') ?></div>
    <label class="col-md-2 control-label">Message ID</label>
    <div class="col-md-4"><?php $form->render_field('can_id') ?></div>
</div>
<div class="form-group">
    <label class="col-md-offset-6 col-md-2 control-label">Primary bus</label>
    <div class="col-md-4"><?php $form->render_field('bus_id') ?></div>
</div>
<div class="form-group">
    <label class="col-md-offset-6 col-md-2 control-label">
        Additional buses
        <a tabindex="0" role="button" data-container="body" data-toggle="popover" data-trigger="focus" title="" data-content="Multiple additional buses can now be added, but this feature is not yet fully supported across the ecosystem:<br>&#x2705;&ensp;Bus statistics<br>&#x274C;&ensp;Code export<br>&#x274C;&ensp;DBC export<br>&#x274C;&ensp;JSON export" data-placement="bottom" data-html="true"><span class="glyphicon glyphicon-question-sign"></span></a>
    </label>
    <div class="col-md-4"><?php $form->render_field('buses') ?></div>
</div>
<div class="form-group">
    <label class="col-md-2 control-label">Description</label>
    <div class="col-md-10"><?php $form->render_field('description'); ?></div>
</div>
<div class="row">
    <div class="col-md-6">
        <div class="form-group">
            <label class="col-md-4 control-label">Sending period</label>
            <div class="col-md-8"><?php $form->render_field('tx_period') ?></div>
        </div>
    </div>
    <div class="col-md-6">
        <div class="form-group">
            <label class="col-md-4 control-label">Timeout</label>
            <div class="col-md-8"><?php $form->render_field('timeout') ?></div>
        </div>
    </div>
</div>
</fieldset>

<fieldset>
<legend>Message fields</legend>
<?php $form->render_field('fields'); ?>
</fieldset>

<?php
if (!$editing) {
    ?>
    <fieldset>
    <legend>Message layout</legend>

    <p class="text-right">
        <select id="message-layout-display-select">
            <option value="lsb">LSB first</option>
            <option value="msb">MSB first</option>
        </select>
    </p>

    <table class="table table-bordered message-layout-table">
        <?php
        if ($message->fields) {
            echo "<tr><th></th>";
            for ($i = 0; $i < 8; $i++) echo "<th>$i</th>";
            echo "</tr>";

            $message->print_nice_layout_lsb_first();
        }
        else {
            echo "<tr><td class='text-center text-muted'><i>No data.</i></td></tr>";
        }
        ?>
    </table>

    <table class="table table-bordered message-layout-table hidden">
        <?php
        if ($message->fields) {
            echo "<tr><th></th>";
            for ($i = 7; $i >= 0; $i--) echo "<th>$i</th>";
            echo "</tr>";

            $message->print_nice_layout_msb_first();
        }
        else {
            echo "<tr><td class='text-center text-muted'><i>No data.</i></td></tr>";
        }
        ?>
    </table>
    </fieldset>

<div class="row">
    <fieldset class="col-md-6">
        <legend>Sent by</legend>
        <ul class="list-unstyled">
        <?php
        $controller->render_list($sent_by, function($unit) {
            echo '<li><a href="' . htmlentities($unit->url(), ENT_QUOTES) . '">' . htmlentities($unit->package_name, ENT_QUOTES)
                . " / " . htmlentities($unit->name, ENT_QUOTES) . '</a></li>';
        });
        ?>
        </ul>
    </fieldset>
    <fieldset class="col-md-6">
        <legend>Received by</legend>
        <ul class="list-unstyled">
        <?php
        $controller->render_list($received_by, function($unit) {
            echo '<li><a href="' . htmlentities($unit->url(), ENT_QUOTES) . '">' . htmlentities($unit->package_name, ENT_QUOTES)
                . " / " . htmlentities($unit->name, ENT_QUOTES) . '</a></li>';
        });
        ?>
        </ul>
    </fieldset>
</div>
    <?php
}
?>

<div class="form-group">
    <div class="col-md-offset-5 col-md-2"><?php $form->submit_button("<span class='glyphicon glyphicon-save'></span>&ensp;Save"); ?></div>
</div>

<?php $form->end_form(); ?>

<?php
if ($message && $message->who_changed && !$editing)
    echo '<div class="text-center text-muted">Last changed by ' . $message->who_changed . ' on ' . $message->when_changed . '.</div>';
?>

<script>
    function validate() {
        var tableId = <?php echo json_encode($form->name . $form->fields['fields']->name); ?>;
        var table = $(document.getElementById(tableId));

        var rows = table.children('tbody').children();

        // Hacky but eh...
        for (var i = 0; i < rows.length - 1; i++) {
            var arrayLength = $($(rows[i]).children()[9]).children('select');
            var value = parseInt(arrayLength.val());

            if (isNaN(value) || value < 1) {
                alert("Array length must be set to 1!");
                arrayLength.focus();
                return false;
            }
        }

        return true;
    }

    $(function() {
        <?php $form->init_js(); ?>

        var formId = <?php echo json_encode($form->html_id); ?>;
        $(document.getElementById(formId)).submit(function(e) {
            if (!validate())
                e.preventDefault();
        });

        $('#message-layout-display-select').change(function() {
            $('.message-layout-table').toggleClass('hidden');
        });
    });
</script>

<?php
require 'views/page_footer.php';
?>

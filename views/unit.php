<?php
$editing = $view['editing'];
$form = $view['form'];
$pkg = $view['pkg'];
$unit = $view['unit'];
$package_id = $unit->package_id;
$sent_messages = $view['sent_messages'];
$received_messages = $view['received_messages'];
/** @var \candb\model\Bus[] $buses */ $buses = $view['buses'];

$drc_num_errors = $view['drc_num_errors'];
$drc_num_warnings = $view['drc_num_warnings'];

// FIXME: hack s $editing
require 'views/page_header.php';

if ($unit->id) {
    ?>
    <div class="toolbar-container">
    <div class="container">
    <ul class="toolbar">
        <li><?php $form->render_edit_toggle($unit); ?></li>
        <li><a href="<?= \candb\model\Message::s_new_url($unit->id) ?>"><span class="glyphicon glyphicon-plus"></span>&ensp;New message</a></li>
        <li><a href="<?= \candb\model\EnumType::s_new_url($unit->id) ?>"><span class="glyphicon glyphicon-plus"></span>&ensp;New enum</a></li>
        <li><a href="<?= $unit->url_export("json"); ?>"><span class="glyphicon glyphicon-download-alt"></span>&ensp;Download JSON</a></li>
        <li><a href="<?= $unit->url_export("dbc") ?>"><span class="glyphicon glyphicon-piggy-bank"></span>&ensp;Export to DBC</a></li>
        <li><a href="<?= $unit->url_export("tx"); ?>"><span class="glyphicon glyphicon-wrench"></span>&ensp;Generate code</a></li>

        <?php \candb\ui\GUICommon::drc_button($unit, $drc_num_errors, $drc_num_warnings); ?>
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
        <div class="col-md-6"><?php $form->render_field('name') ?></div>
    </div>
    <div class="form-group">
        <label class="col-md-2 control-label">Description</label>
        <div class="col-md-10"><?php $form->render_field('description') ?></div>
    </div>
    <div class="form-group">
        <label class="col-md-2 control-label">Hardware author(s)</label>
        <div class="col-md-4"><?php $form->render_field('authors_hw') ?></div>
        <label class="col-md-2 control-label">Firmware author(s)</label>
        <div class="col-md-4"><?php $form->render_field('authors_sw') ?></div>
    </div>
    <div class="form-group">
        <label class="col-md-2 control-label">Code model version</label>
        <div class="col-md-4"><?php $form->render_field('code_model_version') ?></div>
    </div>
</fieldset>

<fieldset>
    <legend>Buses</legend>
<?php $form->render_field('bus_links'); ?>
</fieldset>

    <div class="row">
        <?php
        if ($editing) {
            echo "<fieldset class='col-sm-6'>";
            echo "<legend>Sent Messages</legend>";
            $form->render_field('sent_messages');
            echo '</fieldset>';
            echo "<fieldset class='col-sm-6'>";
            echo "<legend>Received Messages</legend>";
            $form->render_field('received_messages');
            echo '</fieldset>';
        }
        else {
            echo "<fieldset class='col-sm-12'>";
            echo "<legend>Own Messages</legend>";

            echo '<div class="row">';

            $controller->render_list($unit->messages, function(\candb\model\Message $message) use($buses) {
                $has_can_id = ($message->get_can_id_type() !== \candb\model\Message::CAN_ID_TYPE_UNDEF);
                $color_index = $has_can_id ? ($message->get_can_id() % 16) : '';

                echo "<div class='col-md-4' style='margin-bottom: 30px'>";
                echo "<a class='tile -col-md-3' href='" . htmlentities($message->url(), ENT_QUOTES) . "' style='height: 160px'>";
                echo "<div class='bg-".$color_index."' style='height: 3px; position: relative; top: -8px; left: -15px'></div>";
                if ($has_can_id || $message->bus_id !== null) {
                    echo "<div class='pull-right tile-right bg-".$color_index."'>";
                    echo '<div>' . $message->id_to_hex_string() . '</div>';
                    if ($message->bus_id !== null) {
                        echo '<div style="font-size: 12px"><i class="fas fa-network-wired"></i>&ensp;' . htmlentities($buses[$message->bus_id]->name, ENT_QUOTES) . '</div>';
                    }
                    echo "</div>";
                }
                echo "<h4 style='word-break: break-all'>";
                echo " " . htmlentities($message->name) . "&ensp;";
                echo "</h4>";
                echo "<span class='text text-muted'>" . htmlentities($message->description) . "</span>";
                echo '</a>';
                echo '</div>';
            });
            echo '</div>';

            echo '</fieldset>';

            echo '</div><div class="row">';

            function print_nice_link(\candb\model\Message $message)
            {
                echo '<tr>';
                echo '<td class="text-muted">';
                echo $message->package_name;
                echo '</td>';
                echo '<td class="text-muted">' . htmlentities($message->owner_name, ENT_QUOTES);
                echo '<td><a href="' . htmlentities($message->url(), ENT_QUOTES) . '" style="display: block">';
                echo  '<strong>' . htmlentities($message->name) . '</strong>';
                echo "</a></td>";
                echo '<td><a href="' . htmlentities($message->url(), ENT_QUOTES) . '" style="display: block">';
                echo $message->id_to_hex_string() ?? '';
                echo "</a></td>";
                echo '<td><a href="' . htmlentities($message->url(), ENT_QUOTES) . '" style="display: block">';
                echo $message->format_tx_period();
                echo "</a></td>";
                echo "</tr>";
                echo '</a>';
            }

            function render_messages_by_package(array $map)
            {
                if ($map) {
                    echo '<table class="table table-condensed">';
                    echo '<tr class="no-border-top"><th>Package</th><th>Unit</th><th>Message</th><th>Message ID</th><th>Period</th></tr>';

                    foreach ($map as $package_id => $messages) {
                        foreach ($messages as $message) {
                            print_nice_link($message);
                        }

                        echo '<tr><td colspan="5"></td></tr>';
                    }

                    echo '</table>';
                }
                else {
                    echo '<p class="text-center text-muted">No data.</p><br>';
                }
            }

            echo "<fieldset class='col-sm-6'>";
            echo "<legend>Sent Messages</legend>";

            render_messages_by_package($sent_messages);

            echo "</fieldset><fieldset class='col-sm-6'>";
            echo "<legend>Received Messages</legend>";

            render_messages_by_package($received_messages);

            echo "</fieldset></div><div class='row'><fieldset class='col-sm-6'>";
            echo "<legend>Enums</legend>";
            echo "<div class='row'>";
            foreach ($unit->enum_types as $id => $enum_type) {
                $enum_type->package_id = $package_id;
                $enum_type->node_id = $unit->id;
                echo '<div class="col-md-4" style="margin-bottom: 30px">';
                echo "<a class='tile' href='".htmlentities($enum_type->url(), ENT_QUOTES)."'>";
                echo "<b>".htmlentities($enum_type->name)."</b>   ";
                echo "</a>";
                echo '</div>';
            }
            echo "</div>";
            echo '</fieldset>';
        }
        ?>
    </div>

    <div class="form-group">
        <div class="col-md-offset-5 col-md-2"><?php $form->submit_button("<span class='glyphicon glyphicon-save'></span>&ensp;Save"); ?></div>
    </div>

<?php $form->end_form(); ?>

<?php
if ($unit && $unit->who_changed && !$editing)
    echo '<div class="text-center text-muted">Last changed by ' . $unit->who_changed . ' on ' . $unit->when_changed . '.</div>';
?>

    <script>
        $(function() {
            <?php $form->init_js(); ?>
        });
    </script>

<?php
require 'views/page_footer.php';

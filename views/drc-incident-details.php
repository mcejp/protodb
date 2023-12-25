<?php
$incidents = $view['incidents'];

require 'views/page_header.php';

$controller->render_messages();
?>

<?php
foreach ($incidents as $incident) {

    switch ($incident->severity) {
        case \candb\model\DrcIncident::INFO: $glyphicon_class = 'text-info glyphicon glyphicon-info-sign'; $bg_class = 'bg-info'; $title = 'Information'; break;
        case \candb\model\DrcIncident::WARNING: $glyphicon_class = 'text-warning glyphicon glyphicon-alert'; $bg_class = 'bg-warning'; $title = 'Warning'; break;
        case \candb\model\DrcIncident::ERROR: $glyphicon_class = 'text-danger glyphicon glyphicon-remove'; $bg_class = 'bg-danger'; $title = 'Error'; break;
        case \candb\model\DrcIncident::CRITICAL: $glyphicon_class = 'text-danger glyphicon glyphicon-remove'; $bg_class = 'bg-danger'; $title = 'Critical Issue'; break;
        default: $glyphicon_class = ''; $bg_class = ''; $title = '';
    }

    if (!$incident->valid)
        $bg_class = 'bg-default';

    ?>
<h3 class="<?= $bg_class ?>" style="padding: 10px 15px">
    <span class="<?= $glyphicon_class ?>" title="<?= $title ?>"></span>&ensp;Incident #<?= $incident->id ?>
    <?= $incident->valid ? "(active)" : "(inactive)" ?>
</h3>

<table class="table">
    <tr>
        <th>Incident ID</th>
        <td><?= $incident->id ?></td>
    </tr>
    <tr>
        <th>Package</th>
        <td><?= $controller->build_package_link($incident) ?></td>
    </tr>
        <th>Bus</th>
        <td><?= $incident->bus_id ?></td>
    </tr>
    <tr>
        <th>Unit</th>
        <td><?= $controller->build_unit_link($incident) ?></td>
    </tr>
    <tr>
        <th>Message</th>
        <td><?= $controller->build_message_link($incident) ?></td>
    </tr>
    <tr>
        <th>Message field</th>
        <td><?= $incident->message_field_id ? htmlentities($incident->message_field_name, ENT_QUOTES) : '' ?></td>
    </tr>
    <tr>
        <th>Issue Description</th>
        <td><?= $incident->description ?></td>
    </tr>
    <tr>
        <th>Timestamp</th>
        <td><?= $incident->when_updated ?></td>
    </tr>
    <tr>
        <th>Parameters</th>
        <td><code><?= htmlentities($incident->get_params(), ENT_QUOTES) ?></code></td>
    </tr>
</table>

<?php
}
?>

<?php
require 'views/page_footer.php';
?>

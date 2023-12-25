<?php
$incidents = $view['incidents'];
$run_url = $view['run_url'];

require 'views/page_header.php';
?>

<?php
if ($run_url) {
    ?>
    <div class="toolbar-container">
    <div class="container">
    <ul class="toolbar">
        <li><a href="<?= $run_url ?>"><span class="glyphicon glyphicon-refresh"></span>&ensp;Re-run QC</a></li>
    </ul>
    </div>
    </div>
    <?php
}

$controller->render_messages();
?>

<legend>Data Quality Check</legend>

<table class="table">
    <tr>
        <th>Incident ID</th>
        <th>Package</th>
        <th>Bus</th>
        <th>Unit</th>
        <th>Message</th>
        <th>Message field</th>
        <th>Issue Description</th>
        <th>Timestamp</th>
    </tr>
    <?php
    foreach ($incidents as $incident) {
        switch ($incident->severity) {
            case \candb\model\DrcIncident::INFO: $glyphicon_class = 'text-info glyphicon glyphicon-info-sign'; $bg_class = 'bg-info'; $title = 'Information'; break;
            case \candb\model\DrcIncident::WARNING: $glyphicon_class = 'text-warning glyphicon glyphicon-alert'; $bg_class = 'bg-warning'; $title = 'Warning'; break;
            case \candb\model\DrcIncident::ERROR: $glyphicon_class = 'text-danger glyphicon glyphicon-remove'; $bg_class = 'bg-danger'; $title = 'Error'; break;
            case \candb\model\DrcIncident::CRITICAL: $glyphicon_class = 'text-danger glyphicon glyphicon-remove'; $bg_class = 'bg-danger'; $title = 'Critical Issue'; break;
            default: $glyphicon_class = ''; $bg_class = ''; $title = '';
        }

        $incident_url = \candb\model\DrcIncident::s_url($incident->id);
    ?>
        <tr class="<?= $bg_class ?>">
            <td><span class="<?= $glyphicon_class ?>" title="<?= $title ?>"></span>&ensp;<a href="<?= htmlentities($incident_url, ENT_QUOTES) ?>"><?= $incident->id ?></a></td>
            <td><?= $controller->build_package_link($incident) ?></td>
            <td><?= $incident->bus_id ?></td>
            <td><?= $controller->build_unit_link($incident) ?></td>
            <td><?= $controller->build_message_link($incident) ?></td>
            <td><?= $incident->message_field_id ? htmlentities($incident->message_field_name, ENT_QUOTES) : '' ?></td>
            <td><?= $incident->description ?></td>
            <td><abbr class="small" title="<?= htmlentities($incident->get_params(), ENT_QUOTES) ?>"><?= $incident->when_updated ?></abbr></td>
        </tr>
    <?php
    }
    ?>
</table>

<?php
require 'views/page_footer.php';
?>

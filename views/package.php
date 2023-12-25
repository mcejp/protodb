<?php
$package_id = $view['package_id'];
/** @var \candb\model\Package $pkg */ $pkg = $view['pkg'];

$drc_num_errors = $view['drc_num_errors'];
$drc_num_warnings = $view['drc_num_warnings'];

require 'views/page_header.php';

if ($package_id) {
    ?>
    <div class="toolbar-container">
    <div class="container">
    <ul class="toolbar">
        <li><a href="<?= \candb\model\Unit::s_new_url($package_id) ?>"><span class="glyphicon glyphicon-plus"></span>&ensp;New unit</a></li>
        <li><a href="<?= $pkg->url_export('json2') ?>"><span class="glyphicon glyphicon-download-alt"></span>&ensp;Download JSON 2.0</a></li>
        <!--<li><a href="<?= $pkg->url_export('json') ?>"><span class="glyphicon glyphicon-download-alt"></span>&ensp;Download JSON</a></li>-->
        <li><a href="<?= $pkg->url_export('dbc') ?>"><span class="glyphicon glyphicon-piggy-bank"></span>&ensp;Export to DBC</a></li>

        <li><a href="<?= $pkg->urlCommunicationMatrix() ?>"><span class="glyphicon glyphicon-th"></span>&ensp;Matrix</a></li>

        <?php \candb\ui\GUICommon::drc_button($pkg, $drc_num_errors, $drc_num_warnings); ?>
    </ul>
    </div>
    </div>
    <?php
}

$controller->render_messages();
?>

    <h3>Buses</h3>
    <div class="row">
        <?php
        foreach ($pkg->buses as $bus) {
            echo '<div class="col-md-3" style="margin-bottom: 30px">';
            echo "<a class='tile' href='".htmlentities(\candb\controller\BusController::url($bus), ENT_QUOTES)."'>";
            echo "<b>";
            if ($bus->dbc_id !== null) {
                echo '<span class="text-muted">'.$bus->dbc_id.'</span>&ensp;';
            }
            echo htmlentities($bus->name);
            echo "</b><div>".htmlentities($bus->format_bitrate())."</div>   ";
            echo "</a>";
            echo '</div>';
        }
        ?>
    </div>

    <h3>Units</h3>
    <div class="row">
<?php
foreach ($pkg->units as $unit_id => $unit) {
    echo '<div class="col-md-3" style="margin-bottom: 30px">';
    echo "<a class='tile' href='" . htmlentities($unit->url(), ENT_QUOTES) . "'>";
    echo "<h4>{$unit->name}</h4>";
    echo "<div class='text text-muted'>{$unit->description}</div>";
    echo "<span class='text-muted small'>{$unit->num_messages} messages</span><br>";
    echo '</a>';
    echo '</div>';
}
echo '</div>';

require 'views/page_footer.php';

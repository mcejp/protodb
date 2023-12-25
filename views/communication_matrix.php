<?php
$pkg = $view['pkg'];
$units = $view['units'];
$rows = $view['rows'];

require 'views/page_header.php';

$controller->render_messages();
?>

    <h3>Communication matrix</h3>
    <table class="table table-bordered">
        <thead>
        <tr>
            <th class="text-right"><span class="glyphicon glyphicon-share-alt"></span></th>
            <?php
            foreach ($units as $unit)
                echo '<th><a href="' . htmlentities($unit->url(), ENT_QUOTES) . '">' . htmlentities($unit->name, ENT_QUOTES) . '</a></th>';
            ?>
        </tr>
        </thead>
        <tbody>
        <?php
        foreach ($rows as $i => $row) {
            ?>
            <tr>
                <?php
                echo '<th><a href="' . htmlentities($units[$i]->url(), ENT_QUOTES) . '">' . htmlentities($units[$i]->name, ENT_QUOTES) . '</a></th>';

                foreach ($row as $j => $matches) {
                    if ($i != $j)
                        echo '<td>';
                    else
                        echo '<td class="bg-warning">';

                    /** @var \candb\model\Message $msg */
                    foreach ($matches as $msg) {
                        if ($msg->get_can_id() !== null)
                            $class_ = 'text-warning';
                        else
                            $class_ = 'text-success';

                        echo '<div><a href="' . htmlentities($msg->url(), ENT_QUOTES) . '" title="' . $msg->get_can_id() . '" class="' . $class_ . '">';
                        echo htmlentities($msg->name, ENT_QUOTES);
                        echo '</a></div>';
                    }

                    echo '</td>';
                }
                ?>
            </tr>
            <?php
        }
        ?>
        </tbody>
    </table>

<?php

require 'views/page_footer.php';
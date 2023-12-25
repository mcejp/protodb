<?php
// avoidable?
global $app_version;
global $is_devel;
global $rendering_start;
?>

<hr>
<div class="small text-center text-muted">
    <p class="pull-right"><a href="https://github.com/mcejp/protodb">ProtoDB</a></p>
    <p class="pull-left">
        Rendered in <?php printf("%.1f ms", (microtime(true) - $rendering_start) * 1000) ?>.

    <?php
    if ($is_devel) {
        echo 'Logged in as ' . (isset($_SESSION['username']) ? '<code>'. $_SESSION['username'] . '</code>' : '(null)') . '.';
    }
    ?>
    </p>
</div>

</div>
</body>
</html>

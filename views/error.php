<?php
require 'page_header.php';

echo "<div class='alert alert-danger'>";
echo '<p><strong>'.htmlentities($view['heading'], ENT_QUOTES).'</strong></p>';
echo '<p>'.htmlentities($view['body'], ENT_QUOTES).'</p>';
echo "</div>";

require 'page_footer.php';

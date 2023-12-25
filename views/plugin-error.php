<?php
require 'page_header.php';

echo "<div class='alert alert-danger'>${view['message']}</div>";
echo "<pre>".htmlentities($view['stderr'])."</pre>";

require 'page_footer.php';

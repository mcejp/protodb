<?php
$changelog_html = $view['changelog_html'];

require 'views/page_header.php';

$controller->render_messages();

echo $changelog_html;

require 'page_footer.php';

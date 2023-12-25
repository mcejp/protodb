<!DOCTYPE html>
<html>
<head>
    <title><?= $controller->build_page_title($view); ?></title>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <link href="<?= $GLOBALS['base_path'] ?>static/css/bootstrap.min.css" rel="stylesheet">
    <link href='<?= $GLOBALS['base_path'] ?>static/css/style.css' rel='stylesheet' type='text/css'>

    <link rel="stylesheet" href="https://use.fontawesome.com/releases/v5.14.0/css/all.css">

    <script src="https://ajax.googleapis.com/ajax/libs/jquery/1.11.3/jquery.min.js"></script>
    <script src="<?= $GLOBALS['base_path'] ?>static/js/bootstrap.min.js"></script>
    <script src="<?= $GLOBALS['base_path'] ?>static/js/validator.min.js"></script>
    <script src="<?= $GLOBALS['base_path'] ?>static/js/script.js"></script>
</head>
<body class="<?php if (isset($editing) && $editing) echo 'editing'; ?>">
<div class="container">

<?php
if (array_key_exists('modelpath', $view)) {
    $controller->render_breadcrumb($view['modelpath']);
}
?>

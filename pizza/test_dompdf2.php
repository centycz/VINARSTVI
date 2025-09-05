<?php
require __DIR__.'/vendor/autoload.php';

use Dompdf\Dompdf;
use Dompdf\Options;

$options = new Options();
$options->set('defaultFont', 'DejaVu Sans');
$options->set('isRemoteEnabled', true);        // kdyby ses rozhodl načítat vzdálené fonty/logo
$options->set('isHtml5ParserEnabled', true);   // lepší parser
$dompdf = new Dompdf($options);

$html = '<!DOCTYPE html>
<html lang="cs">
<head>
<meta charset="UTF-8">
<style>
body { font-family: "DejaVu Sans", sans-serif; }
h1 { font-size:24px; }
p  { font-size:14px; }
</style>
</head>
<body>
<h1>TEST PDF – ŽLUŤOUČKÝ KŮŇ '.date('Y-m-d H:i:s').'</h1>
<p>Řetězec pro kontrolu: Příliš žluťoučký kůň úpěl ďábelské ódy. ěščřžýáíéúů ĚŠČŘŽÝÁÍÉÚŮ</p>
</body></html>';

$dompdf->loadHtml($html, 'UTF-8');
$dompdf->setPaper('A4','portrait');
$dompdf->render();
$dompdf->stream('test2.pdf', ['Attachment'=>false]);

<?php
require __DIR__.'/vendor/autoload.php';
use Dompdf\Dompdf;
$d = new Dompdf();
$html = '<html><meta charset="UTF-8"><body><h1>TEST PDF – ŽLUŤOUČKÝ KŮŇ '.date('Y-m-d H:i:s').'</h1></body></html>';
$d->loadHtml($html,'UTF-8');
$d->setPaper('A4','portrait');
$d->render();
$d->stream('test.pdf');

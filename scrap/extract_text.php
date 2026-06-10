<?php
$z = new ZipArchive();
$z->open('IR.docx');
$xml = $z->getFromName('word/document.xml');
$z->close();
preg_match_all('/<w:t[^>]*>([^<]+)<\/w:t>/', $xml, $m);
$texts = array_filter($m[1], function($t) { return trim($t) !== ''; });
foreach($texts as $i=>$t) echo $i.': '.$t.PHP_EOL;

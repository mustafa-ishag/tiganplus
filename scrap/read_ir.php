<?php
// قراءة محتوى IR.docx XML لفهم التركيبة
$zip = new ZipArchive();
if ($zip->open('IR.docx') === TRUE) {
    $xml = $zip->getFromName('word/document.xml');
    $zip->close();
    // تنظيف XML لسهولة القراءة
    $dom = new DOMDocument();
    $dom->loadXML($xml);
    $dom->formatOutput = true;
    file_put_contents('ir_structure.xml', $dom->saveXML());
    echo "Saved ir_structure.xml\n";
    
    // استخراج headers أيضاً
    $zip2 = new ZipArchive();
    $zip2->open('IR.docx');
    for ($i = 1; $i <= 3; $i++) {
        $h = $zip2->getFromName("word/header{$i}.xml");
        if ($h) {
            $dom2 = new DOMDocument();
            $dom2->loadXML($h);
            $dom2->formatOutput = true;
            file_put_contents("ir_header{$i}.xml", $dom2->saveXML());
            echo "Saved ir_header{$i}.xml\n";
        }
    }
    $zip2->close();
} else {
    echo "Failed to open IR.docx\n";
}

<?php
require 'vendor/autoload.php';
$spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load('Status of EPR Targets for Producers in Lithium_________________________.xlsx');
$sheet = $spreadsheet->getActiveSheet();
$data = $sheet->toArray();
for($i=0; $i<10; $i++) {
    print_r($data[$i]);
}
?>

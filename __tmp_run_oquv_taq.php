<?php
$_POST = [
  'show_all' => 0,
  'kafedra_id' => '',
  'semestr' => '',
  'oquv_yil_start' => ''
];
chdir(__DIR__ . '/legacy/dashboard/get');
include 'oquv_taqsimoti_table.php';

<?php

require_once "inc/init.php";

$seasons = range(2010, 2016);

$games = [];
$file = "data.csv";
$fp = fopen($file, 'w');

foreach ($seasons as $season) {
    $table = new Table('bl1', $season);
    foreach ($table->getRawGameData() as $game) {
        fputcsv($fp, $game);
    }
}


fclose($fp);


$table  = new Table('bl1', '2017');
$player = new Player('bl1', '2017');
$curr   = $table->getCurrentMatchday();
$md     = $table->getMatchDay($curr);
$currTable = $table->get($curr - 1);

$games = [];


$file = "validation.csv";
$fp = fopen($file, 'w');
foreach ($table->getRawGameData() as $game) {
    fputcsv($fp, $game);
}

foreach ($md as $match) {
   fputcsv($fp, $table->getMatchRawData($currTable, $curr, $match['home_id'], $match['away_id'], $player->getMaxStreakTopScorers($curr - 1)));
}

fclose($fp);

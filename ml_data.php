<?php

require_once "inc/init.php";

$seasons = range(2010, 2016);

$games = [];
/*
foreach ($seasons as $season) {
    $table = new Table('bl1', $season);
    $games = array_merge($games, $table->getRawGameData());
}

$file = "data.csv";
$fp = fopen($file, 'w');
foreach ($games as $game) {
    fputcsv($fp, $game);
}

fclose($fp);
*/

$table = new Table('bl1', '2017');
$curr  = $table->getCurrentMatchday();
$md    = $table->getMatchDay($curr);
$currTable = $table->get($curr - 1);

$games = [];
foreach ($md as $match) {
    $games[] = $table->getMatchRawData($currTable, $curr, $match['home_id'], $match['away_id']);
}

$file = "validation.csv";
$fp = fopen($file, 'w');
foreach ($games as $game) {
    fputcsv($fp, array_values($game));
}

fclose($fp);

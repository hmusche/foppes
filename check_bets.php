<?php

require_once "inc/init.php";

$betFile = 'results.csv';
$fp = fopen($betFile, 'r');

$table = new Table('bl1', '2017');

$bets = [];

while (($row = fgetcsv($fp, 10, ',')) !== false) {
    $bets[] = [
        'home' => $row[0],
        'away' => $row[1]
    ];
}

$table = new Table('bl1', '2017');
$curr  = $table->getCurrentMatchday();
$md    = $table->getMatchDay($curr);
$currTable = $table->get($curr - 1);


foreach ($md as $key => $match) {
    $md[$key]['bet_home'] = $bets[$key]['home'];
    $md[$key]['bet_away'] = $bets[$key]['away'];
}

Render::table($md);

exit;
$index = 0;
$points = 0;

foreach ($table->getResults() as $matchday => $results) {
    foreach ($results as $result) {
        $points += $table->getMatchBetpoints($result, $bets[$index]);
        $index++;
    }
}

var_dump($points);

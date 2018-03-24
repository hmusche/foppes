<?php

require_once "inc/init.php";

$betFile = file_get_contents('results.txt');

$bets = [];

foreach (explode(',', $betFile) as $result) {
    $bets[] = [
        'home' => $result > 0 ? $result : 0,
        'away' => $result < 0 ? abs($result) : 0
    ];
}

$table = new Table('bl1', '2017');
$curr  = $table->getCurrentMatchday();
/*

$md    = $table->getMatchDay($curr);
$currTable = $table->get($curr - 1);

foreach ($md as $key => $match) {
    $md[$key]['bet_home'] = $bets[$key]['home'];
    $md[$key]['bet_away'] = $bets[$key]['away'];
}

Render::table($md);

exit;
*/
$index = 0;
$points = 0;

foreach ($table->getResults() as $matchday => $results) {
    if ($matchday > $curr) {
        break;
    }

    foreach ($results as $result) {
        if ($matchday > 11) {
            //var_dump($result, $bets[$index], $table->getMatchBetpoints($result, $bets[$index]));exit;
        }

        $points += $table->getMatchBetpoints($result, $bets[$index]);
        unset($bets[$index]);
        $index++;
    }
}

var_dump($points);


$md    = $table->getMatchDay($curr);
$currTable = $table->get($curr - 1);
$bets = array_values($bets);

foreach ($md as $key => $match) {
    $md[$key]['bet_home'] = $bets[$key]['home'];
    $md[$key]['bet_away'] = $bets[$key]['away'];
}

Render::table($md);

<?php

require_once "inc/init.php";

//$betFile = file_get_contents('results.txt');

$bets = [];

$betFile = 'results.csv';
$fp = fopen($betFile, 'r');

while (($row = fgetcsv($fp, 10, ',')) !== false) {
    $bets[] = [
        'home' => ($row[0] > 0 ? $row[0] : 0) + $row[1],
        'away' => ($row[0] < 0 ? abs($row[0]) : 0) + $row[1]
    ];
}

fclose($fp);

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
$twoonepoints = 0;
$matches = [];

foreach ($table->getResults() as $matchday => $results) {
    if ($matchday > $curr) {
        break;
    }

    $md = $table->getMatchDay($matchday);


    foreach ($results as $key => $result) {

        $md[$key]['bet']    = $bets[$index]['home'] . ' - ' . $bets[$index]['away'];
        $md[$key]['result'] = $result['home_score'] . ' - ' . $result['away_score'];
        $md[$key]['points'] = $table->getMatchBetpoints($result, $bets[$index]);

        $md[$key]['21points'] = $table->getMatchBetpoints($result, ['home' => 2, 'away' => 1]);

        unset($md[$key]['home_id']);
        unset($md[$key]['away_id']);

        $matches[] = $md[$key];

        $points += $md[$key]['points'];
        $twoonepoints += $md[$key]['21points'];
        unset($bets[$index]);
        $index++;
    }


}
    Render::table($matches);


$md    = $table->getMatchDay($curr);
$currTable = $table->get($curr - 1);
$bets = array_values($bets);

foreach ($md as $key => $match) {
    $md[$key]['bet'] = $bets[$key]['home'] . ' - ' . $bets[$key]['away'];
}

Render::table($md);

var_dump($points, $twoonepoints);

<?php

require_once "inc/init.php";

$seasons = range(2010, 2016);

$games = [];
$file = "data.csv";
$fp = fopen($file, 'w');

$teamPositions = [];
$getTeamPosition = function(&$game, &$teamPositions) {
    foreach ($game['points'] as $teamId => $pos) {
        if (!isset($teamPositions[$teamId])) {
            $teamPositions[$teamId] = [];
        }

        $teamPositions[$teamId][] = $pos;

        if (count($teamPositions[$teamId]) > (33)) {
            unset($teamPositions[$teamId][0]);
            $teamPositions[$teamId] = array_values($teamPositions[$teamId]);
        }

        $game['data'][$teamId . '_otp'] = array_sum($teamPositions[$teamId]) / count($teamPositions[$teamId]);
    }
};

foreach ($seasons as $season) {
    foreach (['bl1', 'bl2'] as $league) {
        $table = new Table($league, $season);

        foreach ($table->getRawGameData() as $game) {
            $getTeamPosition($game, $teamPositions);
            fputcsv($fp, array_values($game['data']));
        }
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
    $getTeamPosition($game, $teamPositions);
    fputcsv($fp, array_values($game['data']));
}

foreach ($md as $match) {
    $game = $table->getMatchRawData($currTable, $curr, $match['home_id'], $match['away_id'], $player->getMaxStreakTopScorers($curr - 1));
    $getTeamPosition($game, $teamPositions);
    fputcsv($fp, array_values($game['data']));
}

fclose($fp);

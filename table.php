<?php

require_once "inc/init.php";

$options = getopt('s:l:m:t:d:');

$seasonCode    = isset($options['s']) ? $options['s'] : '2017';
$leagueCode    = isset($options['l']) ? $options['l'] : 'bl1';
$maxmatchDay   = isset($options['m']) ? $options['m'] : null;
$tablePosDif   = isset($options['t']) ? $options['t'] : 3;
$tableDistance = isset($options['d']) ? $options['d'] : 5;

$table  = new Table($leagueCode, $seasonCode);
$player = new Player($leagueCode, $seasonCode);

//var_dump($table->getRawGameData());

/**
 * Default Table Current Matchday
 */
Render::table($table->get());

foreach ($player->getMaxStreakTopScorers($maxmatchDay) as $players) {
    Render::table($players);
}

/**
 * points
 */
exit;
$points = [];

foreach ($table->getResults() as $matchday => $results) {
    $points[$matchday] = 0;

    foreach ($results as $result) {
        if ($matchday == 1) {
            $bet = [
                'home' => 2,
                'away' => 1
            ];
        } else {
            $bet = $table->getBet($matchday, $result['home_team_id'], $result['away_team_id'], $tablePosDif, $tableDistance);
        }

        $points[$matchday] += $table->getMatchBetpoints($result, $bet);
    }
}

$nextMatches = $table->getMatchday(++$matchday);
$matchdayTable = [];

foreach ($nextMatches as $key => $match) {
    $bet = $table->getBet($matchday, $match['home_id'], $match['away_id'], $tablePosDif, $tableDistance);
    $matchdayTable[] = [
        'date' => date('d.m.Y H:i', $match['date']),
        'home' => $match['home'],
        'away' => $match['away'],
        'expected_result' => $bet['home'] . ':' . $bet['away']
    ];
}

echo "Points made so far: " . array_sum($points) . "\n\n";
Render::table($matchdayTable);

exit;

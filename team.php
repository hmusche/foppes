<?php

require_once "inc/init.php";

$options = getopt('s:t:l:');

$leagueCode = isset($options['l']) ? $options['l'] : 'bl1';
$seasonCode = isset($options['s']) ? $options['s'] : '2017';

if (!isset($options['t'])) {
    echo "Please provide TeamId\n";
    exit;
}

$teamId = $options['t'];
$teams  = new Teams($api, $db);

$sql = "
SELECT * FROM `match_result` AS mr
    JOIN `match` AS m ON m.id = mr.match_id
    JOIN `matchday` AS md ON md.id = m.matchday_id
    JOIN `league` AS l ON l.id = md.league_id
    JOIN `season` AS s ON s.id = md.season_id

    WHERE s.shortname = ?
    AND l.shortname = ?
    AND mr.type = ?
    AND (m.home_team_id = ? OR m.away_team_id = ?)
";


$stmt = $db->prepare($sql, [
    $seasonCode,
    $leagueCode,
    'fulltime',
    $teamId,
    $teamId
]);


$matches = [];
$max     = [
    'home' => 0,
    'away' => 0
];

foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
    $code = '';

    if ($row['home_team_id'] == $teamId) {
        $team = 'home';
        $oppo = 'away';
    } else {
        $team = 'away';
        $oppo = 'home';
    }

    if ($row[$team . '_score'] > $row[$oppo . '_score']) {
        $code = 's';
    } elseif ($row[$team . '_score'] < $row[$oppo . '_score']) {
        $code = 'l';
    } else {
        $code = 'd';
    }

    if ($row['home_team_id'] == $teamId) {
        $code = strtoupper($code);
    }

    $matches[] = [
        'code'  => $code,
        'home'  => $teams->getName($row[$team . '_team_id']),
        'score' => $row[$team . '_score'] . ':' . $row[$oppo . '_score'],
        'away'  => $teams->getName($row[$oppo . '_team_id'])
    ];

    foreach ($max as $key => $m) {
        if (strlen($teams->getName($row[$key . '_team_id'])) > $m) {
            $max[$key] = strlen($teams->getName($row[$key . '_team_id']));
        }
    }
}

foreach ($matches as $match) {
    echo $match['code'] . ' - ';
    echo str_pad($match['home'], $max['home'], ' ', STR_PAD_LEFT);
    echo " " . $match['score'] . " ";
    echo str_pad($match['away'], $max['away']);
    echo "\n";
}

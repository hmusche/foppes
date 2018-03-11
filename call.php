<?php

require 'inc/init.php';

$options = getopt('e:tms:l:');

$leagueCode = isset($options['l']) ? $options['l'] : 'bl1';
$seasonCode = isset($options['s']) ? $options['s'] : '2017';

$endpoints = [
    'teams' => "getavailableteams/$leagueCode/$seasonCode/",
    'match' => "getmatchdata/$leagueCode/$seasonCode/"
];

switch (true) {
    case isset($options['t']):
        $teams = $api->get($endpoints['teams']);

        foreach ($teams as $team) {
            $data = [
                'name'      => $team['TeamName'],
                'shortname' => $team['ShortName']
            ];

            $where = ['ext_id' => $team['TeamId']];

            $db->upsert('team', $data, $where);
        }

        echo "Updated " . count($teams) . " Teams...\n";
        break;
    case isset($options['m']):
        $day    = 0;
        $league = $db->fetch('league', ['shortname' => $leagueCode]);
        $season = $db->fetch('season', ['shortname' => $seasonCode]);
        $teams  = new Teams($db);
        $extMap = $teams->getExtMap();

        do {
            $day++;
            $matchday = [
                'count'     => $day,
                'league_id' => $league['id'],
                'season_id' => $season['id'],
                'start'     => 0,
                'end'       => 0
            ];

            $matches = $api->get($endpoints['match'] . $day);

            if (!$matches) {
                break;
            }

            $matchdayId = $db->upsert('matchday', $matchday, [
                'count'     => $day,
                'league_id' => $league['id'],
                'season_id' => $season['id']
            ]);

            $matchday = [
                'start' => PHP_INT_MAX,
                'end'   => 0
            ];

            $matchdayStatus = 'not_started';

            foreach ($matches as $matchData) {
                $date  = strtotime($matchData['MatchDateTime']);

                $status = $date > time() ? 'not_started' : 'finished';

                if ($status == 'finished' && !$matchData['MatchIsFinished']) {
                    $status = 'started';
                }

                if ($date < $matchday['start']) {
                    $matchday['start'] = $date;
                }

                if ($date > $matchday['end']) {
                    $matchday['end'] = $date;
                }

                $match = [
                    'ext_id'       => $matchData['MatchID'],
                    'matchday_id'  => $matchdayId,
                    'home_team_id' => $extMap[$matchData['Team1']['TeamId']],
                    'away_team_id' => $extMap[$matchData['Team2']['TeamId']],
                    'date'         => $date,
                    'status'       => $status
                ];

                $matchId = $db->upsert('match', $match, ['ext_id' => $matchData['MatchID']]);

                $results = [
                    'halftime' => [
                        'home_score' => 0,
                        'away_score' => 0
                    ],
                    'fulltime' => [
                        'home_score' => 0,
                        'away_score' => 0
                    ]
                ];

                foreach ($matchData['MatchResults'] as $result) {
                    if ($result['ResultTypeID'] == 1) {
                        $results['halftime']['home_score'] = $result['PointsTeam1'];
                        $results['halftime']['away_score'] = $result['PointsTeam2'];

                    } elseif ($result['ResultTypeID'] == 2) {
                        $results['fulltime']['home_score'] = $result['PointsTeam1'];
                        $results['fulltime']['away_score'] = $result['PointsTeam2'];
                    }
                }

                foreach ($results as $type => $result) {
                    $db->upsert('match_result', $result, [
                        'match_id' => $matchId,
                        'type'     => $type
                    ]);
                }

                if ($status == 'finished' && $matchdayStatus == 'not_started') {
                    $matchdayStatus = 'finished';
                }

                if ($status == 'not_started' && $matchdayStatus == 'finished') {
                    $matchdayStatus = 'started';
                }
            }

            $matchday['status'] = $matchdayStatus;

            $db->update('matchday', $matchday, ['id' => $matchdayId]);

            echo "Added Matchday $day...\n";
        } while ($matches);
        break;
    default:
        $result = $api->get($options['e']);

        var_dump($result);
        break;
}

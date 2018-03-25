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

        preg_match('/([a-z]+)([0-9]+)/', $leagueCode, $match);

        if (isset($match[1])) {
            switch ($match[1]) {
                case 'bl':
                    $leagueName = 'Bundesliga';
                    break;
                default:
                    throw new Exception('unknown league id');
            }

            if (isset($match[2])) {
                $leagueName = $match[2] . '. ' . $leagueName;
            }
        } else {
            throw new Exception('unknown league code');
        }

        $leagueId = $db->upsert('league', [
            'shortname' => $leagueCode,
            'name'      => $leagueName
        ], ['shortname' => $leagueCode]);

        $seasonName = $seasonCode . '/' . ($seasonCode + 1);

        $seasonId = $db->upsert('season', [
            'shortname' => $seasonCode,
            'name'      => $seasonName
        ], ['shortname' => $seasonCode]);

        $teams  = new Teams($db);
        $extMap = $teams->getExtMap();

        do {
            $day++;
            $matchday = [
                'count'     => $day,
                'league_id' => $leagueId,
                'season_id' => $seasonId,
                'start'     => 0,
                'end'       => 0
            ];

            $matches = $api->get($endpoints['match'] . $day);

            if (!$matches) {
                break;
            }

            $matchdayId = $db->upsert('matchday', $matchday, [
                'count'     => $day,
                'league_id' => $leagueId,
                'season_id' => $seasonId
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
                    if ($result['ResultTypeID'] == 1 || $result['ResultName'] == "Halbzeitergebnis") {
                        $results['halftime']['home_score'] = $result['PointsTeam1'];
                        $results['halftime']['away_score'] = $result['PointsTeam2'];

                    } elseif ($result['ResultTypeID'] == 2 || $result['ResultName'] == "Endergebnis") {
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

                $goals = [
                    'home' => 0,
                    'away' => 0
                ];

                foreach ($matchData['Goals'] as $goal) {
                    if ($goal['ScoreTeam1'] > $goals['home']) {
                        $scoreTeamId  = $match['home_team_id'];
                        $playerTeamId = $goal['IsOwnGoal'] ? $match['away_team_id'] : $match['home_team_id'];
                        $goals['home']++;
                    } else {
                        $scoreTeamId  = $match['away_team_id'];
                        $playerTeamId = $goal['IsOwnGoal'] ? $match['home_team_id'] : $match['away_team_id'];
                        $goals['away']++;
                    }

                    $playerName = explode(' ', str_replace(['.', ','], ['. ', ', '], trim($goal['GoalGetterName'])));

                    if (count($playerName) == 1) {
                        $firstname = '';
                        $lastname  = trim($playerName[0]);
                    } else {
                        if (strpos($playerName[0], ',') === false) {
                            $firstname = substr(trim($playerName[0]), 0, 1);
                            unset($playerName[0]);

                            $lastname  = trim(implode(' ', $playerName));
                        } else {
                            $firstname = substr(trim($playerName[1]), 0, 1);
                            $lastname  = str_replace(',', '', trim($playerName[0]));
                        }
                    }

                    $playerId = $db->upsert('player', [
                        'lastname'  => ucfirst($lastname),
                        'firstname' => ucfirst($firstname)
                    ], [
                        'lastname' => $lastname
                    ]);

                    $teamPlayer = [
                        'player_id' => $playerId,
                        'team_id'   => $playerTeamId,
                        'league_id' => $leagueId,
                        'season_id' => $seasonId
                    ];

                    $teamPlayerId = $db->upsert('team_player', $teamPlayer, $teamPlayer);

                    $goal = [
                        'match_id'       => $matchId,
                        'team_player_id' => $teamPlayerId,
                        'minute'         => $goal['MatchMinute'],
                        'team_id'        => $scoreTeamId,
                        'own_goal'       => $goal['IsOwnGoal'] ? 1 : 0,
                        'penalty'        => $goal['IsPenalty'] ? 1 : 0
                    ];

                    $goalId = $db->upsert('goal', $goal, $goal);
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

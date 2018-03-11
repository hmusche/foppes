<?php

class Table {
    protected $_db;

    protected $_leagueCode;
    protected $_seasonCode;

    protected $_teams;
    protected $_results;

    protected $_table;

    public function __construct($leagueCode, $seasonCode) {
        $this->_db = Db::getInstance();
        $this->_leagueCode = $leagueCode;
        $this->_seasonCode = $seasonCode;

        $this->_teams   = $this->getTeams();
        $this->_results = $this->_getMatchResults();
    }

    public function getRawGameData() {
        $matchData = [];

        foreach ($this->getResults() as $matchday => $results) {
            $table = $this->get($matchday - 1);

            foreach ($results as $result) {
                $data = $this->getMatchRawData($table, $matchday, $result['home_team_id'], $result['away_team_id']);

                $matchData[] = array_merge(array_values($data),
                [
                    (int)$result['home_score'],
                    (int)$result['away_score']
                ]);
            }
        }

        return $matchData;
    }

    public function getMatchRawData($table, $matchday, $homeId, $awayId) {
        $data     = [];
        $position = false;

        foreach ([$homeId, $awayId] as $teamId) {
            $position = $position === false
                      ? (int)$this->getTeamPosition($teamId, $table)
                      : $position - (int)$this->getTeamPosition($teamId, $table);

            $teamData = $table[$teamId];
            $data[$teamId . '_mgs'] = (int)round($teamData['games'] ? $teamData['goals_scored'] / $teamData['games'] : 0);
            $data[$teamId . '_mgt'] = (int)round($teamData['games'] ? $teamData['goals_taken'] / $teamData['games'] : 0);
            $data[$teamId . '_str'] = (int)$this->getWonGames($teamId, $matchday - 1, 5);
        }

        $data['pos_diff'] = $position;
        $data['matchday'] = $matchday;

        return $data;
    }

    public function getTeamPosition($teamId, $table) {
        $table = array_keys($table);
        return array_search($teamId, $table) + 1;
    }

    public function render($maxmatchday = null) {
        $table   = $this->get($maxmatchday);
        Render::table($table);
    }

    public function get($maxmatchday = null) {
        $this->_fillTable();

        foreach ($this->_results as $matchday => $results) {
            if (!is_null($maxmatchday) && $maxmatchday < $matchday) {
                continue;
            }

            foreach ($results as $result) {
                foreach (['home', 'away'] as $team) {
                    $oppo = $team == 'away' ? 'home' : 'away';

                    $this->_table[$result[$team . '_team_id']]['goals_scored'] += $result[$team . '_score'];
                    $this->_table[$result[$team . '_team_id']]['goals_taken']  += $result[$oppo . '_score'];
                    $this->_table[$result[$team . '_team_id']]['games']++;

                    if ($result[$team . '_score'] > $result[$oppo . '_score']) {
                        $this->_table[$result[$team . '_team_id']]['won']++;
                        $this->_table[$result[$team . '_team_id']]['points'] += 3;
                    } elseif ($result[$team . '_score'] < $result[$oppo . '_score']) {
                        $this->_table[$result[$team . '_team_id']]['lost']++;
                    } else {
                        $this->_table[$result[$team . '_team_id']]['draw']++;
                        $this->_table[$result[$team . '_team_id']]['points'] += 1;
                    }
                }
            }
        }

        uasort($this->_table, [$this, '_sortTable']);

        return $this->_table;
    }

    public function getMatchday($matchday) {
        $sql = "
        SELECT m.date, home.name AS home, home.id AS home_id, away.name AS away, away.id AS away_id
        FROM `match` AS m
            JOIN `team` AS home ON m.home_team_id = home.id
            JOIN `team` AS away ON m.away_team_id = away.id
            JOIN `matchday` AS md ON md.id = m.matchday_id
            JOIN `season` AS s ON s.id = md.season_id
            JOIN `league` AS l ON l.id = md.league_id

            WHERE s.shortname = ?
            AND l.shortname = ?
            AND md.count = ?
        ";

        return $this->_db->prepare($sql, [
            $this->_seasonCode,
            $this->_leagueCode,
            $matchday
        ])->fetchAll();
    }

    protected function getWonGames($teamId, $matchday, $lastNoGames = 5) {
        $sql = "
        SELECT SUM(IF(m.home_team_id = $teamId,
            IF(mr.home_score > mr.away_score,1,0),
            IF(mr.away_score > mr.home_score,1,0)
        )) as won
        FROM `match_result` AS mr
            JOIN `match` AS m ON mr.match_id = m.id
            JOIN `team` AS home ON m.home_team_id = home.id
            JOIN `team` AS away ON m.away_team_id = away.id
            JOIN `matchday` AS md ON md.id = m.matchday_id
            JOIN `season` AS s ON s.id = md.season_id
            JOIN `league` AS l ON l.id = md.league_id

            WHERE s.shortname = ?
            AND l.shortname = ?
            AND md.count <= ?
            AND md.count >= ?
            AND mr.type = ?
            AND (m.home_team_id = ? OR m.away_team_id = ?)
        ";

        $data = $this->_db->prepare($sql, [
            $this->_seasonCode,
            $this->_leagueCode,
            $matchday,
            $matchday - $lastNoGames,
            'fulltime',
            $teamId,
            $teamId
        ])->fetch();

        return isset($data['won']) ? $data['won'] : 0;
    }

    public function getMatchBetpoints($result, $bet) {
        $betpoints = 0;

        if ($bet['home'] == $result['home_score'] && $bet['away'] == $result['away_score']) {
            $betpoints += 4;
        } elseif (($bet['home'] - $bet['away']) == ($result['home_score'] - $result['away_score'])) {
            $betpoints += 3;
        } elseif ($bet['home'] - $bet['away'] !== 0) {
            if (($bet['home'] - $bet['away']) > 0 && ($result['home_score'] - $result['away_score']) > 0) {
                $betpoints += 2;
            } elseif (($bet['home'] - $bet['away']) < 0 && ($result['home_score'] - $result['away_score']) < 0) {
                $betpoints += 2;
            }
        }

        return $betpoints;
    }

    public function getBet($matchday, $homeId, $awayId, $tablePosDif, $tableDistance) {
        $matchdayTable = array_keys($this->get($matchday - 1));

        $posHome = array_search($homeId, $matchdayTable);
        $posAway = array_search($awayId, $matchdayTable);

        $diff = abs($posHome - $posAway);

        if ($posHome - $tablePosDif < $posAway) {
            $bet  = [
                'home' => $diff > $tableDistance ? 3 : 2,
                'away' => $diff > $tableDistance ? 0 : 1
            ];
        } elseif ($posHome > $posAway + $tablePosDif) {
            $bet = [
                'home' => $diff > $tableDistance ? 1 : 0,
                'away' => $diff > $tableDistance ? 3 : 1
            ];
        } else {
            $bet = [
                'home' => 1,
                'away' => 1
            ];
        }

        return $bet;
    }

    public function getResults() {
        return $this->_results;
    }

    protected function _getMatchResults() {
        $sql = "
        SELECT * FROM `match_result` AS mr
            JOIN `match` AS m ON m.id = mr.match_id
            JOIN `matchday` AS md ON md.id = m.matchday_id
            JOIN `season` AS s ON s.id = md.season_id
            JOIN `league` AS l ON l.id = md.league_id

            WHERE s.shortname = ?
            AND l.shortname = ?
            AND mr.type = ?
            AND m.status = ?
        ";

        $data = $this->_db->prepare($sql, [
            $this->_seasonCode,
            $this->_leagueCode,
            'fulltime',
            'finished'
        ])->fetchAll();

        $matchResults = [];

        foreach ($data as $row) {
            if (!isset($matchResults[$row['count']])) {
                $matchResults[$row['count']] = [];
            }

            $matchResults[$row['count']][] = $row;
        }

        return $matchResults;
    }

    protected function _fillTable() {
        $this->_table = [];

        foreach ($this->getTeams() as $teamId => $name) {
            $this->_table[$teamId] = [
                'name' => $name,
                'games' => 0,
                'won' => 0,
                'draw' => 0,
                'lost' => 0,
                'goals_scored' => 0,
                'goals_taken' => 0,
                'points' => 0,
            ];
        }
    }

    public function getTeams() {
        if (!$this->_teams) {
            $sql = "
            SELECT m.home_team_id AS id, t.name
                FROM `match` AS m
                JOIN `team` AS t ON t.id = m.home_team_id
                JOIN `matchday` AS md ON md.id = m.matchday_id
                JOIN `season` AS s ON s.id = md.season_id
                JOIN `league` AS l ON l.id = md.league_id

                WHERE s.shortname = ?
                AND l.shortname = ?
                GROUP BY m.home_team_id
            ";

            $teams = $this->_db->prepare($sql, [
                $this->_seasonCode,
                $this->_leagueCode
            ])->fetchAll();

            return array_column($teams, 'name', 'id');
        }

        return $this->_teams;
    }

    public function getCurrentMatchday() {
        $sql = "
        SELECT md.count AS matchday
            FROM `matchday` AS md
            JOIN `season` AS s ON s.id = md.season_id
            JOIN `league` AS l ON l.id = md.league_id

            WHERE s.shortname = ?
            AND l.shortname = ?
            AND md.end > ?
            ORDER BY md.end ASC
        ";

        $row = $this->_db->prepare($sql, [
            $this->_seasonCode,
            $this->_leagueCode,
            time()
        ])->fetch();

        return $row['matchday'];
    }

    protected function _sortTable($a, $b) {
        if ($a['points'] < $b['points']) {
            return 1;
        } elseif ($a['points'] > $b['points']) {
            return -1;
        } else {
            return ($a['goals_scored'] < $b['goals_scored']) ? 1 : -1;
        }
    }
}

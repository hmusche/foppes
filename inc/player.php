<?php

class Player {
    protected $_db;

    public function __construct($leagueCode, $seasonCode) {
        $this->_db = Db::getInstance();
        $this->_leagueCode = $leagueCode;
        $this->_seasonCode = $seasonCode;

    }

    public function getMaxStreakTopScorers($matchday = null, $maxScorers = 1, $lastXGames = 5) {
        $players = $this->getTopScorers($matchday, $lastXGames);
        $teams   = [];

        foreach ($players as $player) {
            if (!isset($teams[$player['team_id']])) {
                $teams[$player['team_id']] = [];
            }

            if (count($teams[$player['team_id']]) == $maxScorers) {
                continue;
            }

            $teams[$player['team_id']][] = $player;
        }

        return $teams;
    }

    public function getTopScorers($matchday = null, $lastXGames = 5, $teamId = null) {
        if ($matchday === null) {
            $this->_table = new Table($this->_leagueCode, $this->_seasonCode);
            $matchday     = $this->_table->getCurrentMatchday();
        }

        $streakCount = $matchday - $lastXGames;

        $teamWhere = $teamId ? 'AND tp.team_id = ?' : '';

        $sql = "
            SELECT
                p.id AS player_id,
                t.id AS team_id,
                p.lastname,
                t.name AS team,
                COUNT(*) AS goals,
                SUM(g.own_goal) AS own_goals,
                SUM(g.penalty) AS penalties,
                SUM(IF(md.count >= ?, 1, 0)) AS streak
            FROM player p
            JOIN team_player tp ON tp.player_id = p.id
            JOIN team t ON t.id = tp.team_id
            JOIN goal g ON g.team_player_id = tp.id
            JOIN `match` m ON m.id = g.match_id
            JOIN matchday md ON md.id = m.matchday_id
            JOIN season s ON s.id = md.season_id
            JOIN league l ON l.id = md.league_id

            WHERE s.shortname = ?
            AND l.shortname = ?
            AND md.count <= ?
            {$teamWhere}
            GROUP BY tp.id
            ORDER BY t.id,goals DESC
        ";

        $data = [
            $streakCount,
            $this->_seasonCode,
            $this->_leagueCode,
            $matchday
        ];

        if ($teamId) {
            $data[] = $teamId;
        }

        return $this->_db->prepare($sql, $data)->fetchAll();
    }
}

<?php

class Teams {
    protected $_teams;
    protected $_api;
    protected $_db;

    public function __construct($db, $teamIds = []) {
        $this->_db  = $db;

        $this->_setTeams();
    }

    protected function _setTeams($teamIds = []) {
        if (!$this->_teams) {
            if ($teamIds) {
                $this->_teams = $this->_db->fetchAll('team', ['id' => $teamIds]);
            } else {
                $this->_teams = $this->_db->fetchAll('team');
            }

            $this->_teams = array_combine(array_column($this->_teams, 'id'), $this->_teams);
        }
    }

    public function getTeams() {
        return array_combine(array_column($this->_teams, 'id'), $this->_teams);
    }

    public function getExtMap() {
        return array_column($this->_teams, 'id', 'ext_id');
    }

    public function getName($id) {
        return isset($this->_teams[$id]) ? $this->_teams[$id]['name'] : '';
    }
}

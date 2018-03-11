<?php

class Api {
    protected $_ch;

    protected $_baseUrl = 'https://www.openligadb.de/api/';

    public function __construct() {
        $this->_ch = curl_init();
    }

    public function __destruct() {
        curl_close($this->_ch);
    }

    public function get($endpoint) {
        curl_setopt($this->_ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($this->_ch, CURLOPT_URL, $this->_baseUrl . $endpoint);

        $return = curl_exec($this->_ch);

        if ($return) {
            return json_decode($return, true);
        }

        return false;
    }
}

<?php

class Render {
    static public function table($table) {
        $lengths = [];

        foreach ($table as $row) {
            foreach ($row as $key => $value) {
                if (!isset($lengths[$key])) {
                    $lengths[$key] = mb_strlen($key);
                }

                if (mb_strlen($value) > $lengths[$key]) {
                    $lengths[$key] = mb_strlen($value);
                }
            }
        }

        $head = false;

        foreach ($table as $row) {
            if (!$head) {
                foreach ($row as $key => $value) {
                    $key = $key . str_repeat(" ", $lengths[$key] - mb_strlen($key));

                    echo "$key\t";
                }
                echo "\n";
                $head = true;
            }

            foreach ($row as $key => $value) {
                $value = $value . str_repeat(" ", $lengths[$key] - mb_strlen($value));

                echo "$value\t";
            }
            echo "\n";
        }
    }
}

<?php

class Charts {
  function getSerie($id, $from, $to) {
    return $this->__random($id, $from, $to);
  }


  function getSeries($ids, $from, $to) {
    foreach($ids as $id){
      $result[] = $this->__random($id, $from, $to);
    }
    return $result;
  }

  function __random($id, $from, $to) {
    $serie["id"] = $id;
    $serie["name"] = "sgnaps ".($id % 10);
    $serie["units"] = "meters";
    $serie["x"] = array();
    $serie["y"] = array();
    for($t = ceil($from); $t <= $to; $t += 3600 * 1000) {
      $serie["x"][] = $t;
      $serie["y"][] = rand(1,10) + $id;
    }
    return $serie;
  }

  function workspacesList() {
    $result[] = array("id" => 100303, "name" => "Workspace 1");
    $result[] = array("id" => 220202, "name" => "Workspace 2");
    return $result;
  }

  function workspace($id) {
    for($i = 1; $i < ($id % 10) + 2; $i++) {
      $graphs[] = array("id" => $d * 100000 + $i,
        "name" => "Chart " . $i + 1,
        "series" => array($id + 21 * $i, $id + 32 * $i)
      );
    }

    return array("graphs" => $graphs);
  }

  function searchMeasure($text) {
    for($i=0; $i < 20; $i++) {
      $result []= array("id" => 34443 + $i, "name" => "Temperatura condotta alta ".$i." ".$text);
      $result []= array("id" => 1232 + $i, "name" => "Lilvello diga Brugneto ".$i." ".$text);
    }
    return $result;
  }
}

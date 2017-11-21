<?php
require_once '../../../config/config.php';

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
      $graphs[] = array("id" => $id * 100000 + $i,
        "name" => "Chart " . ($id % 100 * 100 + $i),
        "series" => array($id + 21 * $i, $id + 32 * $i)
      );
    }

    return array("graphs" => $graphs);
  }

  function searchMeasure($text) {
    $db = GCApp::getDB();
    $sql = "SELECT ma.id as id, ma.nome as nome from ".DB_SCHEMA.".misure_anagrafica ma
      where ma.nome ILIKE :q OR ma.nome ILIKE :q2";
    $stmt = $db->prepare($sql);
    $stmt->execute(array('q' => $text.'%', 'q2' => '% '.$text.'%'));

    $result = array();
      while($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $result []= array("name" => $row['nome'], "id" => $row['id']);
      }

    return $result;
  }

  function getSeriesForFeature($featureId) {
    $catalog = "genova_acqua";
    $table = "rainvaso_v";
    $id = 4;

    $db = GCApp::getDB();
    $sql = "SELECT ma.id as id, ma.nome as nome from ".DB_SCHEMA.".misure_feature mf
      JOIN ".DB_SCHEMA.".misure_anagrafica ma on ma.id = mf.id_misura
      where catalogo = :catalogo AND nome_tabella = :nome_tabella AND id_feature = :id_feature";
    $stmt = $db->prepare($sql);
    $stmt->execute(array('catalogo' => $catalog, 'nome_tabella' => $table, "id_feature" => $id));

    $result = array();
    while($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
      $result []= array("name" => $row['nome'], "id" => $row['id']);
    }

    return $result;
  }
}

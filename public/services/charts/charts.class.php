<?php
require_once '../../../config/config.php';
require_once '../../../lib/repository.class.php';

function coalesce(&$value, $default = null)
{
    return isset($value) ? $value : $default;
}

class WorkspaceRepository extends Repository {
  function __construct($db, $table) {
    parent::__construct($db, $table,
      array('name', 'data', 'user_id', 'is_public')
    );
  }

  function ofUser($user) {
    return $this->where(
      "user_id = :user_id OR is_public = TRUE",
      array("user_id" => $user), array("order" => 'name'));
  }
}

function createCatalogDb($db, $catalog_id) {
  $dbschema = DB_SCHEMA;
  $sql = "SELECT catalog_path FROM $dbschema.catalog WHERE catalog_id=:c";
  $stmt = $db->prepare($sql);
  $stmt->execute(array("c" => $catalog_id));
  $row = $stmt->fetch(PDO::FETCH_ASSOC);
  if ( $row == null )
    throw new Exception("Catalog not fount: $catalog_id");
  $path = $row['catalog_path'];
  $r = new GCDataDB($path);
  return $r->db;
}

class SerieReader {
  function __construct($db) {
    $this->db = $db;
  }

  function read($id, $from, $to) {
    $catalog_id = 18; //TODO
    $timestamp_col = '"timestamp"';
    $value_col = "temp_ext";
    $table = "measures";

    $from = date('c', $from/1000);
    $to = date('c', $to/1000);

    $datadb = createCatalogDb($this->db, $catalog_id);
    $sql = "SELECT $timestamp_col as t, $value_col as v FROM $table where $timestamp_col >= :f::timestamp AND $timestamp_col <= :t::timestamp";
    $stmt = $datadb->prepare($sql);
    $stmt->execute(array("f" => $from, "t" => $to));

    return $stmt->fetchAll(PDO::FETCH_ASSOC);
  }
}

class Charts {
  function __construct() {
    $this->user = new GCUser();
    $this->db = GCApp::getDB();
    $this->workspaces_repo = new WorkspaceRepository(GCApp::getDB(), DB_SCHEMA.".charts_workspaces");
    $this->reader = new SerieReader($this->db);
  }

  function getSeries($ids, $from, $to) {
    $result = array();
    foreach($ids as $id) {
      $sql = "SELECT * from ".DB_SCHEMA.".misure_anagrafica where id = :id";
      $stmt = $this->db->prepare($sql);
      $stmt->execute(array('id' => $id));
      $s = $stmt->fetch(PDO::FETCH_ASSOC);
      if ( $s != null ) {
        $serie["id"] = $id;
        $serie["name"] = $s["nome"];
        $serie["units"] = $s["udm"];
        $serie["x"] = array();
        $serie["y"] = array();
        $points = $this->reader->read($id, $from, $to);
        foreach ( $points as $point ) {
          $serie["x"][] = strtotime($point["t"])*1000;
          $serie["y"][] = $point["v"];
        }
        $result[] = $serie;
      }
      else {
        $result[] = $this->__random($id, $from, $to);
      }
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
    $workspaces = $this->workspaces_repo->ofUser($this->user->getUsername());

    $result = array();
      foreach ( $workspaces as $workspace ) {
        $result []= array("name" => $workspace['name'],
          "id" => $workspace['id'],
          "public" => $workspace['is_public']
        );
      }
    return $result;
  }

  function workspace($id) {
    $workspace = $this->workspaces_repo->find($id);
    if ( !$workspace['is_public'] && !$workspace['user_id'] == $this->user->getUsername() )
      throw new Exception("You are not authorized");
    $workspace['data'] = json_decode($workspace['data']);
    return $workspace;
  }

  function update_workspace($id, $params) {
    if ( !$this->user->isAuthenticated() )
      throw new Exception("You are not authorized");

    $workspace = $this->workspaces_repo->find($id);

    if ( $workspace['user_id'] != $this->user->getUsername() )
      throw new Exception("You are not authorized");

    $workspace = $this->workspaces_repo->updateAttributes($workspace, $params);
    $workspace['data'] = json_encode(coalesce($workspace['data']));
    return $this->workspaces_repo->update($workspace);
  }

  function create_workspace($params) {
    if ( !$this->user->isAuthenticated() )
      throw new Exception("You are not authorized");

    $workspace = $this->workspaces_repo->updateAttributes(array(), $params);
    $workspace["user_id"] = $this->user->getUsername();
    $workspace["data"] = json_encode(coalesce($workspace['data']));

    return $this->workspaces_repo->insert($workspace);
  }

  function searchMeasure($text) {
    $sql = "SELECT ma.id as id, ma.nome as nome from ".DB_SCHEMA.".misure_anagrafica ma
      where ma.nome ILIKE :q OR ma.nome ILIKE :q2";
    $stmt = $this->db->prepare($sql);
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

    $sql = "SELECT ma.id as id, ma.nome as nome from ".DB_SCHEMA.".misure_feature mf
      JOIN ".DB_SCHEMA.".misure_anagrafica ma on ma.id = mf.id_misura
      where catalogo = :catalogo AND nome_tabella = :nome_tabella AND id_feature = :id_feature";
    $stmt = $this->db->prepare($sql);
    $stmt->execute(array('catalogo' => $catalog, 'nome_tabella' => $table, "id_feature" => $id));

    $result = array();
    while($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
      $result []= array("name" => $row['nome'], "id" => $row['id']);
    }

    return $result;
  }
}

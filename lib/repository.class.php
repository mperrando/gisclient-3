<?php
class Repository {
  function __construct($db, $table, $fields) {
    $this->fields = $fields;
    $this->db = $db;
    $this->table = $table;
  }

  function find($id) {
    $f = implode(', ', array_merge(array('id'), $this->fields));
    $sql = "SELECT ". $f ." FROM ".$this->table." where id = :id";
    $stmt = $this->db->prepare($sql);
    $stmt->execute(array('id' => $id));
    return $stmt->fetch(PDO::FETCH_ASSOC);
  }

  function update($obj) {
    $f = array();
    foreach($this->fields as $field) {
      if ( array_key_exists($field, $obj) )
        $f []= $field. " = :" .$field;
    }
    $sql = "UPDATE ". $this->table." SET ". implode(', ', $f) ." where id = :id";
    $stmt = $this->db->prepare($sql);
    $stmt->execute($this->fix_values($obj));
    return $this->find($obj['id']);
  }

  function insert($obj) {
    $f = array();
    foreach($this->fields as $field) {
      if ( array_key_exists($field, $obj) )
        $f []= $field;
      }

    $add_colon = function($s) { return ':'.$s; };
    $v = implode(', ', array_map($add_colon, $f));
    $f = implode(', ', $f);

    $sql = "INSERT INTO ". $this->table." (". $f .") VALUES (". $v .")";
    $stmt = $this->db->prepare($sql);
    //die(serialize($obj));
    $stmt->execute($this->fix_values($obj));
    return $this->find($this->db->lastInsertId());
  }

  function delete($id) {
    $sql = "DELETE FROM ".$this->table." where id = :id";
    $stmt = $this->db->prepare($sql);
    $stmt->execute(array('id' => $id));
  }

  function where($cond, $vals, $opts = []) {
    $f = implode(', ', array_merge(array('id'), $this->fields));
    $sql = "SELECT ". $f ." FROM ".$this->table." where $cond";
    if ( $opts['order'] != null )
      $sql .= " ORDER BY ". $opts['order'];
    $stmt = $this->db->prepare($sql);
    $stmt->execute($vals);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
  }

  function updateAttributes($obj, $values) {
    foreach($this->fields as $field) {
      if ( array_key_exists($field, $values) )
        $obj[$field] = $values[$field];
    }
    return $obj;
  }

  function fix_values($obj) {
    $result = array();
    foreach ( $obj as $k => $v ) {
      if ( is_bool($v) ) {
        $v = $v ? 'true' : 'false';
      }
      $result[$k] = $v;
    }
    return $result;
  }
}

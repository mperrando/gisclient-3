<?php
require_once '../../../config/config.php';
require_once ROOT_PATH . 'lib/GCService.php';
//require_once ROOT_PATH.'lib/ajax.class.php';
require_once 'charts.class.php';
require_once 'fake_charts.class.php';

$gcService = GCService::instance();
$gcService->startSession();

$charts = new Charts();

//die(json_encode($_REQUEST));
if ($_REQUEST['action'] == 'get_serie') {
  $id = $_REQUEST['id'];
  $from = $_REQUEST['from'];
  $to = $_REQUEST['to'];
  $output = $charts->getSerie($id, $from, $to);
}
else if ($_REQUEST['action'] == 'get_series') {
  if(empty($_REQUEST['ids']))
    $ids = array();
  else
    $ids = explode(",", $_REQUEST['ids']);
  $from = $_REQUEST['from'];
  $to = $_REQUEST['to'];
  $output = $charts->getSeries($ids, $from, $to);
}
else if ($_REQUEST['action'] == 'searchChart') {
  $mapset = $_REQUEST['mapset'];
  $queryString = $_REQUEST['query_string'];
  $output = $charts->searchChart($mapset, $queryString);
}
else if ($_REQUEST['action'] == 'workspaces_list') {
  $output = $charts->workspacesList();
}
else if ($_REQUEST['action'] == 'workspace') {
  $id = $_REQUEST['id'];
  $output = $charts->workspace($id);
}
else if ($_REQUEST['action'] == 'searchMeasure') {
  $output = $charts->searchMeasure($_REQUEST['text']);
}
else if ($_REQUEST['action'] == 'getSeriesForFeature') {
  $output = $charts->getSeriesForFeature($_REQUEST['featureId']);
}
else {
  http_response_code(422);
  $output["error"] = "Unrecognized action: " . $_REQUEST['action'];
}

die(json_encode($output));

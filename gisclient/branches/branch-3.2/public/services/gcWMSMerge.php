<?php
require_once "../../config/config.php";

// eventually handle debug at the mapserver level, including some timing information
$enableDebug = false;
$logfile = "/tmp/mapfile.debug";
if (defined('DEBUG') && DEBUG) {
	$enableDebug = true;
	$logfile = DEBUG_DIR . "/mapfile.debug";
}

/**
 * MapServer, at least of version 5.6, is not too happy if there are some
 * parameters in the request.
 */
function cleanWMSRequest($url) {
	// this list should propably include all WMS parameters
	$bannedParameters = array('version');
	$urlParts = parse_url($url);
	if (isset($urlParts['query'])) {
		parse_str($urlParts['query'], $queryParts);
		foreach ($queryParts as $paramName => $paramValue) {
			if(in_array(strtolower($paramName), $bannedParameters)) {
				unset($queryParts[$paramName]);
			}
		}
		$urlParts['query'] = http_build_query($queryParts);
	}
	$cleanUrl = '';
	if (isset($urlParts['scheme'])) {
		$cleanUrl .= $urlParts['scheme'] . '://';
	}

	if (isset($urlParts['user'])) {
		$cleanUrl .= $urlParts['user'];
		if (isset($urlParts['pass'])) {
			$cleanUrl .= ':'. $urlParts['pass'];
		}
		$cleanUrl .= '@';
	}
	if (isset($urlParts['host'])) {
		$cleanUrl .= $urlParts['host'];
	}
	if (isset($urlParts['path'])) {
		$cleanUrl .= $urlParts['path'];
	}
	if (isset($urlParts['query'])) {
		$cleanUrl .= '?'.$urlParts['query'];
	}
	if (isset($urlParts['fragment'])) {
		$cleanUrl .= $urlParts['fragment'];
	}
	return $cleanUrl;
}

// i config li facciamo arrivare dalla classe php che abbiamo fatto noi per la stampa
// li c'è il calcolo di scala, dimensioni etc
// c'è anche la legenda e la generazione dell'html
// questo file si occuperà solo di creare l'immagine e può essere usato anche per fare il download dell'immagine di mappa
$mapConfig = json_decode($_REQUEST['options'], true);

$oMap=ms_newMapObj('');
if(defined('PROJ_LIB')) $oMap->setConfigOption("PROJ_LIB", PROJ_LIB); 
$oMap->setSize(intval($mapConfig['size'][0]), intval($mapConfig['size'][1]));
$oMap->setProjection("init=".strtolower($mapConfig['srs']));
$oMap->extent->setextent($mapConfig['extent'][0], $mapConfig['extent'][1], $mapConfig['extent'][2], $mapConfig['extent'][3]);
if ($enableDebug) { 
	$oMap->setConfigOption("MS_ERRORFILE", $logfile);
	$oMap->set('debug', 5);
}
if(!empty($mapConfig['resolution'])) {
	$oMap->set('resolution', (int)$mapConfig['resolution']);
} else {
	$oMap->set('resolution', 72);
}
if(!empty($mapConfig['format']) && $mapConfig['format'] == 'gtiff') {
	$oMap->outputformat->set('name','GTiff');
	$oMap->outputformat->set('driver','GDAL/GTiff');
	$oMap->outputformat->set('extension','tif');
	$oMap->outputformat->set('mimetype','image/tiff');
	$oMap->outputformat->set('imagemode', MS_IMAGEMODE_RGB);
} else {
	$oMap->outputformat->set('name','PNG');
	$oMap->outputformat->set('driver','AGG/PNG');
	$oMap->outputformat->set('extension','png');
	$oMap->outputformat->set('imagemode', MS_IMAGEMODE_RGBA);
	$oMap->outputformat->set('transparent',MS_ON);
	$oMap->outputformat->setOption("INTERLACE", "OFF");
}
$oMap->web->set('imagepath', IMAGE_PATH);
$oMap->web->set('imageurl', IMAGE_URL);

$sessionId = null;
if(isset($mapConfig['GC_SESSION_ID']) && !empty($mapConfig['GC_SESSION_ID'])) $sessionId = $mapConfig['GC_SESSION_ID'];

foreach($mapConfig['layers'] as $key => $layer) {

	if(isset($layer['URL'])) {
		$url = $layer['URL'];
		if(substr($url, 0, -1) != '?') {
			if(substr($url, 0, -1) != '&') {
				if(!strpos($url, '?')) $url .= '?';
				else $url .= '&';
			}
		}
		
		if(!empty($layer['PARAMETERS']['PROJECT'])) $url .= 'PROJECT='.$layer['PARAMETERS']['PROJECT'];
		if(!empty($layer['PARAMETERS']['MAP'])) $url .= '&MAP='.$layer['PARAMETERS']['MAP'];
		if(!empty($layer['PARAMETERS']['TIME'])) $url .= '&TIME='.$layer['PARAMETERS']['TIME'];
		if(!empty($layer['PARAMETERS']['PREV_TIME'])) $url .= '&PREV_TIME='.$layer['PARAMETERS']['PREV_TIME'];
		if(!empty($layer['PARAMETERS']['REDLINEID'])) $url .= '&REDLINEID='.$layer['PARAMETERS']['REDLINEID'];
        if(!empty($layer['PARAMETERS']['LANG'])) $url .= '&LANG='.$layer['PARAMETERS']['LANG'];
		if(!empty($sessionId)) $url .= '&GC_SESSION_ID='.$sessionId;
		if(!empty($mapConfig['resolution'])) $url.= '&RESOLUTION='.$mapConfig['resolution'];
        $layerNames = '';
        if(!empty($layer['PARAMETERS']['LAYERS'])) {
            if(is_array($layer['PARAMETERS']['LAYERS'])) $layerNames = implode(',', $layer['PARAMETERS']['LAYERS']);
            else $layerNames = $layer['PARAMETERS']['LAYERS'];
        }

		$oLay = ms_newLayerObj($oMap);
		$oLay->set('name', 'print_layer_'.$key);
		$oLay->set('type', MS_LAYER_RASTER);
		if ($enableDebug) {
			$oLay->set('debug', 5);
		}
		$oLay->setConnectionType(MS_WMS);
		$oLay->set('connection', cleanWMSRequest($url));
		if(!empty($layer['PARAMETERS']['OPACITY']) && $layer['PARAMETERS']['OPACITY'] != 100) {
			$oLay->set('opacity', $layer['PARAMETERS']['OPACITY']);
            $oLay->setMetaData("wms_force_separate_request", 1);
		}
		if(!empty($layer['PARAMETERS']['SLD'])) {
			$oLay->setMetaData('wms_sld_url', $layer['PARAMETERS']['SLD']);
		}
		$oLay->setMetaData("wms_srs", $mapConfig['srs']);
		$oLay->setMetaData("wms_name", $layerNames);
		$oLay->setMetaData("wms_server_version", $layer['PARAMETERS']['VERSION']);
		$oLay->setMetaData("wms_format", $layer['PARAMETERS']['FORMAT']);
		$oLay->set('status',MS_ON);
	}
}

if(isset($mapConfig['scalebar']) && $mapConfig['scalebar'] && $mapConfig['format'] != 'gtiff') {
	$scalebarSize = array(200, 3);
	$fontSize = 7;
	if(!empty($mapConfig['resolution'])) {
		$scalebarSize[0] = round($scalebarSize[0] * ($mapConfig['resolution']/72));
		$scalebarSize[1] = round($scalebarSize[1] * ($mapConfig['resolution']/72));
		$fontSize = round($fontSize * ($mapConfig['resolution']/72));
	}
	$scalebar = '
	  SCALEBAR
		INTERVALS 4
		UNITS METERS
		COLOR 250 250 250
		BACKGROUNDCOLOR 100 100 100
		IMAGECOLOR 255 255 255
		OUTLINECOLOR 0 0 0
		SIZE '.$scalebarSize[0].' '.$scalebarSize[1].'
		STYLE 0
		TRANSPARENT ON
		POSTLABELCACHE TRUE
		LABEL
		  COLOR 0 0 0
		  FONT "verdana"
		  TYPE truetype
		  SIZE '.$fontSize.'
		END  # Label
	  END  # Reference
	';
	$oMap->setFontSet('../../fonts/fonts.list');
	$oMap->scalebar->updateFromString($scalebar);
}

ms_ResetErrorList();

$oImage = $oMap->draw();

if ($enableDebug) { 
	$oMap->save(DEBUG_DIR . 'debug.map');
}
if(isset($mapConfig['scalebar']) && $mapConfig['scalebar'] && $mapConfig['format'] != 'gtiff') {
	$oMap->embedScalebar($oImage);
	$oMap->drawLabelCache($oImage);
}

if(!empty($mapConfig['format']) && $mapConfig['format'] == 'gtiff') {
	header("Content-type: image/tiff");
} else {
	header("Content-type: image/png");
}

if($mapConfig['format'] == 'gtiff') {
    $oImage->saveImage($mapConfig['file_name'], $oMap);
} else if(!empty($mapConfig['save_image']) && isset($mapConfig['file_name'])) {
	$oImage->saveImage($mapConfig['file_name']);
} else {
	$oImage->saveImage('');
}

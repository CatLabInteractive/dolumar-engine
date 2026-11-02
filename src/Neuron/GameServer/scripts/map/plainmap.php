<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN"
"http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd"> 
<html>
	<head>
	
		<title>Dolumar <?=$name?> [ revision <?=APP_VERSION?> ]</title>
		
		<link href="<?=ABSOLUTE_URL?>client/javascript/windowjs/themes/default.css" rel="stylesheet" type="text/css" > 
		<link href="<?=ABSOLUTE_URL?>client/javascript/windowjs/themes/dolumar.css" rel="stylesheet" type="text/css" > 
		
		<!-- Dolumar CSS -->
		<link href="<?=ABSOLUTE_URL?>client/css/map.css" rel="stylesheet" type="text/css" >
		<link href="<?=ABSOLUTE_URL?>client/css/main.css" rel="stylesheet" type="text/css" >
		<link href="<?=ABSOLUTE_URL?>client/css/menu.css" rel="stylesheet" type="text/css" >
		<link href="<?=ABSOLUTE_URL?>client/css/forms.css" rel="stylesheet" type="text/css" >
		<link href="<?=ABSOLUTE_URL?>client/css/windows.css" rel="stylesheet" type="text/css" >
		<link href="<?=ABSOLUTE_URL?>client/css/icons.css" rel="stylesheet" type="text/css" >
		<link href="<?=ABSOLUTE_URL?>client/css/forum.css" rel="stylesheet" type="text/css" >
		
		<style type="text/css">
			*
			{
				margin: 0px;
				padding: 0px;
			}
			
			body
			{
				height: 100%;
			}
			
			html
			{
				height: 100%;
			}
		</style>
		
		<script type="text/javascript" src="<?=ABSOLUTE_URL?>client/javascript/prototype/prototype.js"></script>
		<script type="text/javascript" src="<?=ABSOLUTE_URL?>client/javascript/scriptaculous/scriptaculous.js"></script>
		
		<script type="text/javascript">
			var Game = new Object ();
			var CONFIG_GAME_URL = '<?=ABSOLUTE_URL?>';<?php
			echo "\n";
			echo Neuron_Core_Tools::getJavascriptParamDeclarations ($_GET);
			?>
			var CONFIG_IS_TESTSERVER = <?= IS_TESTSERVER ? 'true' : 'false'?>;
			var PARAM_NOGUI = true;
			
		</script>
		
		<script type="text/javascript" src="<?=ABSOLUTE_URL?>client/javascript/canvas/canvastext.js"></script>
		
		<script type="text/javascript" src="<?=ABSOLUTE_URL?>client/javascript/scrollmap.js"></script>
		<script type="text/javascript" src="<?=ABSOLUTE_URL?>client/javascript/minimap.js"></script>
		<script type="text/javascript" src="<?=ABSOLUTE_URL?>client/javascript/gamemap.js"></script>
		<script type="text/javascript" src="<?=ABSOLUTE_URL?>client/javascript/dolumar.js"></script>
		<script type="text/javascript" src="<?=ABSOLUTE_URL?>client/javascript/gui.js"></script>
		
		<script type="text/javascript" src="<?=ABSOLUTE_URL?>client/javascript/functions.js"></script>
		
		<script type="text/javascript" src="<?=ABSOLUTE_URL?>client/javascript/windowjs/window.js"></script>
		<script type="text/javascript" src="<?=ABSOLUTE_URL?>client/javascript/windowjs/window_ext.js"></script>
		
		<script type="text/javascript" src="<?=ABSOLUTE_URL?>client/javascript/forum.js"></script>
	</head>
	
	<body >
		<div id="bodyDiv" style="width: 100%; height: 100%;">
		</div>
	</body>
</html>

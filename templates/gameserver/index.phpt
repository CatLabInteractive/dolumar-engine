<?php 

	// Fetch server name
	$server = Neuron_GameServer::getServer ();
	$name = $server->getServerName ();
	
	header("Content-Type: text/html; charset=UTF-8");
	
	$text = Neuron_Core_Text::__getInstance ();
	
	
?><!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN"
"http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd"> 
<html xmlns="http://www.w3.org/1999/xhtml" xml:lang="en" lang="en">  
	<head>
	
		<title>Dolumar <?=$name?></title>
		
		<!-- JAVASCRIPT -->		
		<script type="text/javascript">
			var Game = new Object ();
			
			
			var CONFIG_GAME_NAME = 'Dolumar';
			
			var CONFIG_GAMESERVER_URL = '<?=GAMESERVER_ASSET_URL?>';
			var CONFIG_GAME_URL = '<?=ABSOLUTE_URL?>';
			var CONFIG_DISPATCH_URL = '<?=$dispatch_url?>';
			var CONFIG_GAME_DOMAIN = '<?=$_SERVER['SERVER_NAME']?>';
			var CONFIG_STATIC_GAME_URL = '<?=STATIC_ABSOLUTE_URL?>';<?php
			echo "\n";
			echo Neuron_Core_Tools::getJavascriptParamDeclarations ($_GET);
			?>

			<?php if (isset ($_SESSION['hide_advertisement']) && $_SESSION['hide_advertisement']) { ?>
				var CONFIG_HIDE_ADVERTISEMENT = false;
			<?php } else { ?>
				var CONFIG_HIDE_ADVERTISEMENT = false;
			<?php } ?>

			var CONFIG_IS_TESTSERVER = <?= IS_TESTSERVER ? 'true' : 'false'?>;
			var CONFIG_DATETIME_FORMAT = '<?=DATETIME?>';
			var RUNTIME_SESSION_ID = '<?=session_id()?>';
			var CONFIG_IS_PREMIUM = <?=$premium ? 'true' : 'false'?>;
			
			var CONFIG_ENV_SERVERID = '<?=Neuron_GameServer_Server::getInstance()->getServerId (); ?>';
			var CONFIG_ENV_SERVERNAME = '<?=str_replace (' ', '_', Neuron_GameServer_Server::getInstance()->getServerName ()); ?>';
			var CONFIG_ENV_CONTAINER = '<?=isset($_SESSION['opensocial_container'])?$_SESSION['opensocial_container'] : 'none'; ?>';
			var CONFIG_THEME = 'nuncio';
			var CONFIG_MAP_TILESIZE = <?=$map_tile_size?>;
			
			<?php if (defined ('GOOGLE_ANALYTICS')) { ?>
			
			var GOOGLE_ANALYTICS = '<?=GOOGLE_ANALYTICS?>';
			<?php } ?>
			
			<?php if (defined ('PIWIK_ANALYTICS')) { ?>
			
			var PIWIK_ANALYTICS = '<?=PIWIK_ANALYTICS?>';
			<?php } ?>
		</script>
		
		<!-- plugin for jssettings -->
		<?=$jssettings?>
		<!-- /plugin for jssettings -->
		
		<?php if (defined ('GOOGLE_ANALYTICS')) { ?>
			<!-- Google Analytics -->
			<script type="text/javascript">
				var gaJsHost = (("https:" == document.location.protocol) ? "https://ssl." : "http://www.");
				document.write(unescape("%3Cscript src='" + gaJsHost + "google-analytics.com/ga.js' type='text/javascript'%3E%3C/script%3E"));
			</script>
		<?php } ?>
		
		<?php if (defined ('PIWIK_ANALYTICS')) { ?>

			<!-- Piwik --> 
			<script type="text/javascript">
			var pkBaseURL = (("https:" == document.location.protocol) ? "https://stats.catlab.eu/" : "http://stats.catlab.eu/");
			document.write(unescape("%3Cscript src='" + pkBaseURL + "piwik.js' type='text/javascript'%3E%3C/script%3E"));
			</script><script type="text/javascript">

			</script><noscript><p><img src="http://stats.catlab.eu/piwik.php?idsite=<?=PIWIK_ANALYTICS?>" style="border:0" alt="" /></p></noscript>
			<!-- End Piwik Tracking Code -->

		<?php } ?>
		
		<script type="text/javascript" src="<?=$static_client_url?>javascript/prototype/prototype.js?version=<?=$application_version?>"></script>
		<script type="text/javascript" src="<?=$static_client_url?>javascript/scriptaculous/scriptaculous.js?version=<?=$application_version?>"></script>
		
		<script type="text/javascript" src="<?=$static_client_url?>javascript/windowjs/window.js?version=<?=$application_version?>"></script>
		<script type="text/javascript" src="<?=$static_client_url?>javascript/windowjs/window_ext.js?version=<?=$application_version?>"></script>
		
		<script type="text/javascript" src="<?=$static_client_url?>javascript/ajaxian/lazyloader.js?version=<?=$application_version?>"></script>
		
		<script type="text/javascript" src="<?=$static_client_url?>javascript/lightbox/lightbox.js?version=<?=$application_version?>"></script>
		<script type="text/javascript">
			LightboxOptions.resizeImages = true;
			LightboxOptions.fileLoadingImage = CONFIG_GAMESERVER_URL + 'javascript/lightbox/images/loading.gif';
			LightboxOptions.fileBottomNavCloseImage = CONFIG_GAMESERVER_URL + 'javascript/lightbox/images/closelabel.gif';
		</script>
			
		<script type="text/javascript" src="<?=$static_client_url?>javascript/canvas/canvastext.js?version=<?=$application_version?>"></script>
		<script type="text/javascript" src="<?=$static_client_url?>javascript/scrollmap.js?version=<?=$application_version?>"></script>
		<script type="text/javascript" src="<?=$static_client_url?>javascript/minimap.js?version=<?=$application_version?>"></script>
		<script type="text/javascript" src="<?=$static_client_url?>javascript/gamemap.js?version=<?=$application_version?>"></script>
		<script type="text/javascript" src="<?=$static_client_url?>javascript/gui.js?version=<?=$application_version?>"></script>
		<script type="text/javascript" src="<?=$static_client_url?>javascript/game.js?version=<?=$application_version?>"></script>
		<script type="text/javascript" src="<?=$static_client_url?>javascript/functions.js?version=<?=$application_version?>"></script>
		<script type="text/javascript" src="<?=$static_client_url?>javascript/forum.js?version=<?=$application_version?>"></script>
		<script type="text/javascript" src="<?=$static_client_url?>javascript/konami.js?version=<?=$application_version?>"></script>
		<script type="text/javascript" src="<?=$static_client_url?>javascript/advertisement.js?version=<?=$application_version?>"></script>
		<script type="text/javascript" src="<?=$static_client_url?>javascript/fancybox.js?version=<?=$application_version?>"></script>
		<script type="text/javascript" src="<?=$static_client_url?>javascript/modernizr/modernizr-1.5.min.js?version=<?=$application_version?>"></script>
		<script type="text/javascript" src="<?=$static_client_url?>javascript/extra/extra.js?version=<?=$application_version?>"></script> 
		
		<!-- CSS -->
		<link href="<?=$static_client_url?>javascript/windowjs/themes/default.css?version=<?=$application_version?>" rel="stylesheet" type="text/css" />
		<link href="<?=$static_client_url?>javascript/windowjs/themes/nuncio.css?version=<?=$application_version?>" rel="stylesheet" type="text/css" />
		
		<link href="<?=$static_client_url?>css/map.css?version=<?=$application_version?>" rel="stylesheet" type="text/css" />
		<link href="<?=$static_client_url?>css/main.css?version=<?=$application_version?>" rel="stylesheet" type="text/css" />
		<link href="<?=$static_client_url?>css/forms.css?version=<?=$application_version?>" rel="stylesheet" type="text/css" />
		<link href="<?=$static_client_url?>css/windows.css?version=<?=$application_version?>" rel="stylesheet" type="text/css" />

		<link href="<?=$static_client_url?>css/forum.css?version=<?=$application_version?>" rel="stylesheet" type="text/css" />
		<link href="<?=$static_client_url?>javascript/lightbox/lightbox.css?version=<?=$application_version?>" rel="stylesheet" type="text/css" />
		
		<link href="<?=$static_client_url?>css/<?=$text->get ('direction', 'main', 'main', 'ltr'); ?>.css?version=<?=$application_version?>" rel="stylesheet" type="text/css" />
		
		<!--[if IE 6]>
			<link href="client/css/ie6.css" rel="stylesheet" type="text/css" >
		<![endif]-->
		
		<!--[if IE]>
			<link href="client/css/ie.css" rel="stylesheet" type="text/css" >
		<![endif]-->
		
		<link rel="shortcut icon" href="<?=ABSOLUTE_URL?>favicon.ico" type="image/x-icon" />
		<link href="<?=ABSOLUTE_URL?>api/rss/" rel="alternate" type="application/rss+xml" title="Player Logs" />
		
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
		
		<!-- plugin for header -->
		<?=$header?>
		<!-- /plugin for header -->
		
	</head>
	
	<body class="<?php $premium ? 'premium' : false; ?> <?php if (!SHOW_ADS || isset ($_SESSION['hide_advertisement']) && $_SESSION['hide_advertisement']) { ?>no-advertisement<?php } ?>">
	
		<!-- plugin for body -->
		<?=$body?>
		<!-- /plugin for body -->
	
		<div id="bodyDiv" style="width: 100%; height: 100%;">
			<div id="server_stats" style="position: absolute; right: 2px; bottom: 2px; z-index: 1; color: white; text-align: right;">
				<?php if (isset ($_GET['DEBUG'])) { ?>
					Map PT: <span id="server_parsetime_map"></span><br />
					GUI PT: <span id="server_parsetime_gui"></span><br />
					MySQL: <span id="server_sqlcount"></span>
				<?php } else { ?>
					<!--
					<span>Rev. <?=$application_version?></span><br />
					<span class="clock" format="m/d H:i:s"><?=date('m/d H:i:s')?></span>
					-->
				<?php } ?>
			</div>
			
			<div id="map_cancel_warning" style="display: none;">
				<p class="false">
					<?=$text->get ('rightclick', 'main', 'main');?>
				</p>
			</div>
		
			<div id="loading_signal">
				<span>Loading...</span>
			</div>
		</div>
		
		<textarea id="profiler" readonly="readonly" style="display: none; z-index: 100000000; position: absolute; background: black; border: 2px solid gray; overflow: auto; color: white; padding: 5px; width: 900px; margin-left: -450px; height: 600px; margin-top: -300px; left: 50%; top: 50%;"></textarea>
		
		<?php if (isset ($_SESSION['tracker_html'])) { ?>
			<?=$_SESSION['tracker_html']?>
		<?php } ?>
		
	</body>
</html>

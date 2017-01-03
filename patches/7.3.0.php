<?php
$db = DevblocksPlatform::getDatabaseService();
$logger = DevblocksPlatform::getConsoleLog();
$settings = DevblocksPlatform::getPluginSettingsService();
$tables = $db->metaTables();

$client_id = $settings->get('wgm.facebook', 'client_id', null);
$client_secret = $settings->get('wgm.facebook', 'client_secret', null);

if(!is_null($client_id) || !is_null($client_secret)) {
	$credentials = [
		'client_id' => $client_id,
		'client_secret' => $client_secret,
	];
	
	$settings->set('wgm.facebook', 'credentials', $credentials, true, true);
	$settings->delete('wgm.facebook', ['client_id','client_secret','users']);
}

return TRUE;
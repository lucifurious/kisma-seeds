<?php
/**
 * common.config.php
 * This file contains the local application parameters that are shared between the background and web services
 *
 * @author %%author_name%% <%%author_email%%>
 * @filesource
 *
 * @var string $_archivePath
 */

//	And return our parameters array...
return array(
	'adminEmail'        => 'support@your-company.com',
	//	Debug log level
	'logLevel'          => 9,
	//	Set database name for global models
	'global.dbName'     => null,
	//	Authentication
	'auth.allowedUsers' => array( //	username => password
	),
);

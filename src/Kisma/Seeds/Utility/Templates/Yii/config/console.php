<?php
/**
 * console.php
 * This file contains the configuration information for running background tasks
 *
 * @author %%author_name%% <%%author_email%%>
 * @filesource
 *
 * @var array  $_dbConfig
 * @var string $_appPath
 * @var string $_appTag
 * @var string $_appName
 */

//**************************************************************************
//* Requirements
//**************************************************************************

//	Load the composer autoloader
require_once dirname( __DIR__ ) . '/vendor/autoload.php';

//*************************************************************************
//* Global Configuration Variables
//*************************************************************************

/**
 * Defaults
 */
$_cacheObject = null;
$_commonConfig = __DIR__ . '/common.config.php';
$_databaseConfig = __DIR__ . '/database.config.php';
$_dbCache = array( 'cache' => $_cacheObject );

/**
 * Application Base Values
 */
$_appPath = '%%app_path%%';
$_appName = '%%app_name%%';

/**
 * Application Paths
 */
$_logFilePath = '%%log_path%%';

/**
 * Database configuration(s)
 */
if ( file_exists( $_databaseConfig ) )
{
	/** @noinspection PhpIncludeInspection */
	require_once $_databaseConfig;

	//	Set up database caching
	$_dbCache = array(

		/**
		 * The cache database definition
		 */
		'%%database.cache.connection_id%%'     => array(
			'class'            => 'CDbConnection',
			'autoConnect'      => true,
			'connectionString' => 'mysql:host=' . $_dbConfig['host'] . ';dbname=' . $_dbConfig['name'] . ';',
			'username'         => $_dbConfig['user'],
			'password'         => $_dbConfig['password'],
			'emulatePrepare'   => true,
			'charset'          => 'utf8',
		),
		/**
		 * The database cache object
		 */
		'cache'                                => array(
			'class'                => '%%database.cache.class%%',
			'connectionID'         => '%%database.cache.connection_id%%',
			'cacheTableName'       => '%%database.cache.cache_table_name%%',
			'autoCreateCacheTable' => '%%database.cache.auto_create_cache_table%%',
		),
	);
}

//*************************************************************************
//* Put it all together!
//*************************************************************************

/** @noinspection PhpIncludeInspection */
return array(
	'basePath'    => dirname( __DIR__ ) . '/web/protected',
	'name'        => $_appName,
	'runtimePath' => $_logFilePath,
	//	preloading 'log' component
	'preload'     => array( 'log' ),
	//	autoloading model and component classes
	'import'      => array(
		//	System...
		'application.models.*',
		'application.components.*',
		'application.controllers.*',
	),
	//	application components
	'components'  => array_merge(

		array(
			'log'     => array(
				'class'  => 'CLogRouter',
				'routes' => array(
					array(
//						'class'             => 'pogostick.logging.CPSLiveLogRoute',
						'levels'            => 'error, warning, info, trace, debug',
						'maxFileSize'       => '10240',
						'logFile'           => 'console.' . gethostname() . '.log',
						'logPath'           => $_logFilePath,
//						'categoryWidth'     => 30,
//						'excludeCategories' => array(
//							'system.CModule',
//							'system.db.CDbConnection',
//							'system.db.CDbCommand',
//							'/^system.db.ar.(.*)+$/',
//							'system.web.filters.CFilterChain',
//						),
					),
				),
			),
			//      Database (Site)
			'db'      => array(
				'class'                 => 'CDbConnection',
				'autoConnect'           => true,
				'connectionString'      => 'mysql:host=' . $_dbConfig['host'] . ';dbname=' . $_dbConfig['name'] . ';',
				'username'              => $_dbConfig['user'],
				'password'              => $_dbConfig['password'],
				'emulatePrepare'        => true,
				'charset'               => 'utf8',
				'schemaCachingDuration' => 3600,
				'enableParamLogging'    => true,
			),
		),
		/**
		 * The database cache, if available
		 */
		$_dbCache
	),
	/**
	 * Application-level parameters
	 */
	'params'      => file_exists( $_commonConfig ) ? require_once( $_commonConfig ) : array(),
);

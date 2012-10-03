<?php
/**
 * web.php
 * This file is the main configuration file for web side
 *
 * @author %%author_name%% <%%author_email%%>
 * @filesource
 *
 * @var array  $_dbConfig
 * @var array  $_cacheConfig
 * @var string $_appPath
 * @var string $_repoTag
 * @var string $_appName
 */

//**************************************************************************
//* Requirements
//**************************************************************************

//	Load the composer junk
require_once dirname( __DIR__ ) . '/vendor/autoload.php';

//*************************************************************************
//* Global Configuration Variables
//*************************************************************************

/**
 * Defaults
 */
$_cacheObject = null;
$_defaultController = 'app';
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
	'basePath'          => dirname( __DIR__ ) . '/web/protected',
	'name'              => $_appName,
	'runtimePath'       => $_logFilePath,
	'defaultController' => $_defaultController,
	//	preloading 'log' component
	'preload'           => array( 'log' ),
	//	autoloading model and component classes
	'import'            => array(
		//	System...
		'application.models.*',
		'application.models.forms.*',
		'application.models.ima.*',
		'application.components.*',
		'application.controllers.*',
	),
	'modules'           => array(
		'gii' => array(
			'class'     => 'system.gii.GiiModule',
			'password'  => 'gii',
			'ipFilters' => array(
				'*',
			),
		),
	),
	//	application components
	'components'        => array_merge(

	//	our local config...
		array(
			//	Authentication manager...
			'authManager'  => array(
				'class'        => 'CDbAuthManager',
				'connectionID' => 'db',
			),
			'assetManager' => array(
				'class'      => 'CAssetManager',
				'basePath'   => 'public/assets',
				'baseUrl'    => '/public/assets',
				'linkAssets' => true,
			),
			'user'         => array(
				// enable cookie-based authentication
				'allowAutoLogin' => false,
				'loginUrl'       => array( $_defaultController . '/login' ),
			),
			'urlManager'   => array(
				'urlFormat'      => 'path',
				'showScriptName' => false,
				'rules'          => array(),
			),
			'clientScript' => array(
				'scriptMap' => array(
					'jquery.js'       => false,
					'jquery.min.js'   => false,
					'jqueryui.js'     => false,
					'jqueryui.min.js' => false,
				),
			),
			//      Database (Site)
			'db'           => array(
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
			'log'          => array(
				'class'  => 'CLogRouter',
				'routes' => array(
					array(
//						'class'             => 'pogostick.logging.CPSLiveLogRoute',
						'levels'            => 'error, warning, info, trace, debug',
						'maxFileSize'       => '102400',
						'logFile'           => 'web.' . gethostname() . '.log',
						'logPath'           => $_logFilePath,
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
		),
		/**
		 * The database cache, if available
		 */
		$_dbCache
	),
	/**
	 * Application-level parameters
	 */
	'params'            => file_exists( $_commonConfig ) ? require_once( $_commonConfig ) : array(),
);

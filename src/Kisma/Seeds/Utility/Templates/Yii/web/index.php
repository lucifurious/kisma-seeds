<?php
/**
 * index.php
 * Main entry point/bootstrap for sapi and cli processes
 *
 * @author %%author_name%% <%%author_email%%>
 */

/**
 * This bootstraps the Yii framework
 */
require_once dirname( __DIR__ ) . '/vendor/yii/framework/yii.php';

/**
 * Console/CLI entry point here
 */
if ( 'cli' == PHP_SAPI )
{
	/**
	 * Comment out the below line to turn off DEBUG mode
	 */
	defined( 'YII_DEBUG' ) or define( 'YII_DEBUG', true );

	//	Create a console application instance and go!
	Yii::createConsoleApplication( dirname( __DIR__ ) . '/config/console.php' )->run();
}
/**
 * Web/SAPI entry point here
 */
else
{
	/**
	 * Comment out the below line to turn off DEBUG mode
	 */
	defined( 'YII_DEBUG' ) or define( 'YII_DEBUG', true );
	defined( 'YII_TRACE_LEVEL' ) or define( 'YII_TRACE_LEVEL', 3 );

	//	Create a web application instance and launch
	Yii::createWebApplication( dirname( __DIR__ ) . '/config/web.php' )->run();
}

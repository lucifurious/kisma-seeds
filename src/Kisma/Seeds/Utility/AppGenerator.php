<?php
namespace Kisma\Seeds\Yii\Utility;
use \Kisma\Core\Utility\FileSystem;
use \Kisma\Core\Utility\Inflector;

/**
 * AppGenerator
 * Generates a base application suitable for framing
 *
 * Required Options:
 *
 *     $appName            string       The full name of this application
 *     $appMode            int          One of the AppType constants
 *     $initRepo           bool         If true, a git repo will be created for this app
 *     $repoName           string       The name of the repository
 *     $authorName         string       The name of the author. If not provided, it will be pulled from your git config
 *     $authorEmail        string       The email of the author. If not provided, it will be pulled from your git config
 *
 * Overridable Derived Options:
 *
 *     If these options are not set, they will be set based on the $repoName
 *
 *     $basePath           string       The root of where you want the repository created. Defaults to ./
 *     $appPath            string       The path where the application will live
 *     $logPath            string       The log path for the application (defaults to app_path/log)
 */
class AppGenerator extends \Kisma\Core\Seed implements \Kisma\Seeds\Interfaces\FrameworkNames
{
	//*************************************************************************
	//* Constants
	//*************************************************************************

	/**
	 * @var string The base location where template projects are stored
	 */
	const BaseTemplateLocation = '/Templates';
	/**
	 * @var string The relative location of the logs
	 */
	const DefaultLogPath = '/log';

	//**************************************************************************
	//* Private Members
	//**************************************************************************

	/**
	 * @var array The default locations of where stuff will go. Layout of generated dir
	 */
	protected $_defaultPaths = array(
		'config'     => '/config',
		'console'    => '/console',
		'log'        => '/log',
		'web'        => '/web',
	);
	/**
	 * @var array Where to find the templates for this generator
	 */
	protected $_templatePaths = array(
		self::Yii     => array(
			'base'       => '/Yii',
			'web'        => '/web',
			'console'    => '/console',
			'config'     => '/config',
		),
		/** Symfony Templates */
		self::Symfony => array(
			'base'       => '/Symfony',
			'web'        => '/web',
			'console'    => '/console',
			'config'     => '/config',
		),
		/** Zend Templates */
		self::Zend    => array(
			'base'       => '/Zend',
			'web'        => '/web',
			'console'    => '/console',
			'config'     => '/config',
		),
	);
	/**
	 * @var array The names of the config files in the template
	 */
	protected $_templateConfigFiles = array(
		'console.php',
		'common.config.php',
		'database.config.php',
		'web.php',
	);
	/**
	 * @var int
	 */
	protected $_appMode = self::Yii;
	/**
	 * @var string
	 */
	protected $_appName;
	/**
	 * @var string
	 */
	protected $_appPath;
	/**
	 * @var string
	 */
	protected $_basePath;
	/**
	 * @var string
	 */
	protected $_logPath;
	/**
	 * @var string
	 */
	protected $_repoPath;
	/**
	 * @var bool If false, no git repository will be created. You still need a repo name...
	 */
	protected $_initRepo = true;
	/**
	 * @var string
	 */
	protected $_repoName = null;
	/**
	 * @var bool If false, the project directory will not be seeded with the base template
	 */
	protected $_copyTemplate = true;
	/**
	 * @var bool If false, the customizing/macro replacement of the project directory will not happen
	 */
	protected $_customizeTemplate = true;
	/**
	 * @var string
	 */
	protected $_authorName;
	/**
	 * @var string
	 */
	protected $_authorEmail;
	/**
	 * @var string
	 */
	protected $_currentUser = null;
	/**
	 * @var array
	 */
	protected $_database
		= array(
			'host'     => 'database_host',
			'name'     => 'database_name',
			'user'     => 'database_user',
			'password' => 'database_password',
			'cache'    => array(
				'connection_id'           => 'db.cache',
				'class'                   => 'CDbCache',
				'cache_table_name'        => '_app_cache_t',
				'auto_create_cache_table' => true,
			),
		);
	/**
	 * @var array The tag replacement map of the skeleton application
	 */
	protected $_replacementMap
		= array(
			'app_name',
			'app_path',
			'app_version',
			'app_commit',
			'repo_name',
			'log_path',
			'runtime_path',
			'author_name',
			'author_email',
			'database.host',
			'database.name',
			'database.user',
			'database.password',
			'database.cache.connection_id',
			'database.cache.class',
			'database.cache.cache_table_name',
			'database.cache.auto_create_cache_table',
		);

	//*************************************************************************
	//* Public Methods
	//*************************************************************************

	/**
	 * @param array $options
	 */
	public function __construct( $options = array() )
	{
		$this->_initializeDefaults();
		parent::__construct( $options );
	}

	/**
	 * Generates the application
	 */
	public function generate()
	{
		try
		{
			//	Check the minimum requirements...
			if ( null === $this->_appName )
			{
				throw new \Kisma\Seeds\Exceptions\ConfigurationException( 'You must supply a value for "appName".' );
			}

			if ( null === $this->_repoName )
			{
				throw new \Kisma\Seeds\Exceptions\ConfigurationException( 'You must supply a value for "repoName".' );
			}

			//	Take the repo name and convert to a paths...
			$this->_buildAppPaths();

			switch ( $this->_appMode )
			{
				case self::Yii:
					$this->_generateYiiApp();
					break;

				default:
					throw new \Kisma\Seeds\Exceptions\FeatureNotImplementedException( 'The generator selected is not yet implemented.' );
			}
		}
		catch ( \Exception $_ex )
		{
			echo 'Error: ' . $_ex->getMessage();
			exit( 1 );
		}

		exit( 0 );
	}

	//**************************************************************************
	//* Private Methods
	//**************************************************************************

	/**
	 * Builds the path names (and paths themselves) based on app name
	 */
	protected function _buildAppPaths()
	{
		/**
		 * base_path/app_name/console
		 * base_path/app_name/config
		 * base_path/app_name/log
		 * base_path/app_name/web
		 */
		if ( null === $this->_basePath )
		{
			$this->_basePath = __DIR__;
		}

		if ( null === $this->_appPath )
		{
			$this->_appPath = FileSystem::makePath(
				$this->_basePath,
				Inflector::tag( $this->_repoName, true ),
				Inflector::tag( $this->_appName, true )
			);
		}

		if ( null === $this->_logPath )
		{
			$this->_logPath = FileSystem::makePath(
				$this->_appPath,
				self::DefaultLogPath
			);
		}

		foreach ( $this->_templatePaths as $_path )
		{
			FileSystem::makePath(
				$this->_appPath,
				$_path
			);
		}

	}

	/**
	 * Generates a Yii app from the base template
	 */
	protected function _generateYiiApp()
	{
		//	Run ginit on the repo if wanted
		if ( false !== $this->_initRepo )
		{
			try
			{
				$this->_initializeRepo();
			}
			catch ( \Exception $_ex )
			{
				echo 'Exception: ' . $_ex->getMessage();
				exit( -1 );
			}
		}

		if ( false !== $this->_copyTemplate )
		{
			try
			{
				$this->_copyBaseTemplate();
			}
			catch ( \Exception $_ex )
			{
				echo 'Exception: ' . $_ex->getMessage();
				exit( -1 );
			}
		}

		if ( false !== $this->_customizeTemplate )
		{
			try
			{
				$this->_customizeBaseTemplate();
			}
			catch ( \Exception $_ex )
			{
				echo 'Exception: ' . $_ex->getMessage();
				exit( -1 );
			}
		}
	}

	/**
	 * Constructs a replacement map with current values
	 *
	 * @param array $source
	 *
	 * @return array
	 */
	protected function _fillMap( $source = array() )
	{
		$_map = array();

		foreach ( $source as $_tag )
		{
			$_value = null;

			//	Specials
			if ( false !== strpos( $_tag, 'database.cache.' ) )
			{
				$_value = $this->_database['cache'][str_replace( 'database.cache.', null, $_tag )];
			}
			else if ( false !== strpos( $_tag, 'database.' ) )
			{
				$_value = $this->_database[str_replace( 'database.', null, $_tag )];
			}
			else if ( 'runtime_path' == $_tag )
			{
				$_value = $this->_logPath;
			}
			else
			{
				//	Just inflect the name and try to get directly
				try
				{
					$_value = \Kisma\Core\Utility\Option::get( $this, $_tag );
				}
				catch ( \Exception $_ex )
				{
					//	Ignore...
				}
			}

			if ( is_bool( $_value ) )
			{
				$_value = $_value ? 1 : 0;
			}

			$_map['%%' . $_tag . '%%'] = $_value;
		}

		return $_map;
	}

	/**
	 * Does object -> template tag replacements in order to customize
	 * the base template in the new project directory
	 */
	protected function _customizeBaseTemplate()
	{
		$_basePath = FileSystem::makePath( $this->_appPath, $this->_templatePaths['config'] );

		try
		{
			$_map = $this->_fillMap( $this->_replacementMap );

			if ( !empty( $_map ) )
			{
				foreach ( $this->_templateConfigFiles as $_file )
				{
					if ( !file_exists( $_basePath . $_file ) )
					{
						continue;
					}

					//	Read it
					if ( false === ( $_data = @file_get_contents( $_basePath . $_file ) ) )
					{
						continue;
					}

					//	Write it
					file_put_contents(
						$_basePath . $_file,
						str_ireplace(
							array_keys( $_map ),
							array_values( $_map ),
							$_data
						)
					);
				}
			}
		}
		catch ( \Exception $_ex )
		{
			echo 'Exception: ' . $_ex->getMessage();
			exit( -1 );
		}
	}

	/**
	 * Intializes a git repository with ginit
	 */
	protected function _initializeRepo()
	{
		$_repoTag = Inflector::tag( $this->_repoName, true );

		if ( null === $this->_repoPath )
		{
			$this->_repoPath = FileSystem::makePath( $this->_basePath, $_repoTag );
		}

		$_command =
			'git init ' .
				escapeshellarg( $_repoTag ) . ' ' .
				escapeshellarg( $this->_basePath . DIRECTORY_SEPARATOR );

		if ( false === exec( $_command, $_output, $_result ) || 0 != $_result )
		{
			$this->_errorOutput(
				'Failed to "git init" the supplied repo name "' . $this->_repoName . '".',
				$_output,
				$_result
			);
		}

		echo 'Repository initialized.';
	}

	/**
	 */
	protected function _copyBaseTemplate()
	{
		//	Copy base template to new path
		$_basePath = $this->_getTemplatePath();

		$_command = 'find ' .
			escapeshellarg( $_basePath . DIRECTORY_SEPARATOR ) .
			' -maxdepth 1 -mindepth 1 -not -name .git -exec cp -r {} ' .
			escapeshellarg( $this->_appPath . DIRECTORY_SEPARATOR ) . ' \;';

		if ( false === exec( $_command, $_output, $_result ) || 0 != $_result )
		{
			$this->_errorOutput( 'The copying of the base template failed . The output of the command is below . If you can fix it, go for it . ',
				$_output,
				$_result );
		}

		//	Make necessary directories...
		if ( !is_dir( $this->_appPath . DIRECTORY_SEPARATOR . 'web/public/assets' ) )
		{
			//	Create the assets directory
			@mkdir( $this->_appPath . DIRECTORY_SEPARATOR . 'web/public/assets', 02775, true );
		}
	}

	/**
	 * @param string $message
	 * @param array  $output
	 * @param int    $result
	 *
	 * @return void
	 */
	protected function _errorOutput( $message, $output = array(), $result = 1 )
	{
		$output = implode( PHP_EOL, $output );
		echo <<<TEXT

=============== AHOY MATEY! ===============

Something did not fare so well...

MESSAGE
=======
{$message}

COMMAND RESULT
==============
{$result}

COMMAND OUTPUT
==============
{$output}

=============== AHOY MATEY! ===============

TEXT;

		throw new \Kisma\Seeds\Exceptions\ApplicationException( $message );
	}

	/**
	 * Constructs a template path from the parts
	 *
	 * @param string $which The sub-template-directory
	 *
	 * @return string
	 */
	protected function _getTemplatePath( $which = 'base' )
	{
		return __DIR__ . self::BaseTemplateLocation . $this->_templatePaths[$this->_appMode][$which];
	}

	/**
	 * Initializes the object back to defaults
	 */
	protected function _initializeDefaults()
	{
		$this->_initRepo = true;
		$this->_appMode = self::Yii;
		$this->_appPath = $this->_appName = $this->_repoName = null;

		if ( empty( $this->_authorName ) )
		{
			$this->_authorName = trim( @`git config user.name`, PHP_EOL );
			$this->_authorEmail = trim( @`git config user.email`, PHP_EOL );
		}

		if ( empty( $this->_templatePaths ) )
		{
			$this->_templatePaths = array(
				self::Yii     => array(
					'base'    => self::BaseTemplateLocation,
					'web'     => '/web',
					'console' => '/console',
					'config'  => '/config',
				),
				self::Symfony => array(),
				self::Zend    => array(),
			);

		}

		$this->_currentUser = getenv( 'USER' );
	}

	//**************************************************************************
	//* Properties
	//**************************************************************************

}

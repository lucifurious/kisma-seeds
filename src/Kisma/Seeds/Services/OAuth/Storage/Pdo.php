<?php
/**
 * Pdo.php
 * OAuth PDO storage engine
 */
namespace Kisma\Seeds\Services\OAuth\Storage;
/**
 * Pdo
 */
class Pdo extends \Kisma\Core\Seed implements \Kisma\Seeds\Interfaces\OAuth\GrantCode, \Kisma\Seeds\Interfaces\OAuth\RefreshToken
{
	//*************************************************************************
	//* Constants
	//*************************************************************************

	/**
	 * @var string
	 */
	const Table_Clients = 'oauth_client_t';
	/**
	 * @var string
	 */
	const Table_Codes = 'oauth_auth_code_t';
	/**
	 * @var string
	 */
	const Table_Tokens = 'oauth_access_token_t';
	/**
	 * @var string
	 */
	const Table_Refresh = 'oauth_refresh_token_t';
	/**
	 * @var string
	 */
	const SaltyGoodness = 'rW64wRUk6Ocs+5c7JwQ{69U{]MBdIH';

	//*************************************************************************
	//* Members
	//*************************************************************************

	/**
	 * @var \PDO
	 */
	protected $_db;

	//*************************************************************************
	//* Methods
	//*************************************************************************

	/**
	 * @param \PDO $db
	 */
	public function __construct( \PDO $db )
	{
		parent::__construct();

		\Kisma\Core\Utility\Sql::setConnection( $this->_db = $db );
	}

	/**
	 * Release DB connection during destruct.
	 */
	public function __destruct()
	{
		$this->_db = null;
	}

	/**
	 * @param string $client_id
	 * @param string $client_secret
	 * @param string $redirect_uri
	 */
	public function addClient( $client_id, $client_secret, $redirect_uri )
	{
		try
		{
			$client_secret = $this->_hash( $client_secret, $client_id );
			$_tableName = self::Table_Clients;

			$_sql = <<<MYSQL
INSERT INTO {$_tableName} (
	client_id_text,
	client_secret_text,
	redirect_uri_text
)
VALUES
(
	:client_id_text,
	:client_secret_text,
	:redirect_uri_text
)
MYSQL;

			$_params = array(
				':client_id_text'     => $client_id,
				':client_secret_text' => $client_secret,
				':redirect_uri_text'  => $redirect_uri,
			);

			\Kisma\Core\Utility\Sql::execute( $_sql, $_params );
		}
		catch ( \PDOException $_ex )
		{
			$this->_handleException( $_ex );
		}
	}

	/**
	 * @param string $client_id
	 * @param string $client_secret
	 *
	 * @return bool
	 */
	public function checkClientCredentials( $client_id, $client_secret = null )
	{
		try
		{
			$_tableName = self::Table_Clients;

			$_sql = <<<SQL
SELECT
	client_secret_text
FROM
	{$_tableName}
WHERE
	client_id_text = :client_id_text
SQL;

			$_row = \Kisma\Core\Utility\Sql::find( $_sql, array( ':client_id_text' => $client_id ) );

			if ( null === $client_secret )
			{
				return false !== $_row;
			}

			return $this->_checkPassword( $client_secret, $_row['client_secret_text'], $client_id );
		}
		catch ( \PDOException $_ex )
		{
			$this->_handleException( $_ex );
		}
	}

	/**
	 * @param string $client_id
	 *
	 * @return array|bool|null
	 */
	public function getClientDetails( $client_id )
	{
		$_tableName = self::Table_Clients;

		$_sql = <<<SQL
SELECT
	redirect_uri_text as redirect_uri
FROM
	{$_tableName}
WHERE
	client_id_text = :client_id_text
SQL;

		try
		{
			if ( false === ( $_row = \Kisma\Core\Utility\Sql::find( $_sql, array( ':client_id_text' => $client_id ) ) ) )
			{
				return false;
			}

			return $_row;
		}
		catch ( \PDOException $_ex )
		{
			$this->_handleException( $_ex );
		}
	}

	/**
	 * Implements Storage::getAccessToken().
	 */
	public function getAccessToken( $token )
	{
		return $this->getToken( $token, false );
	}

	/**
	 * Implements Storage::setAccessToken().
	 */
	public function setAccessToken( $token, $client_id, $user_id, $expires, $scope = null )
	{
		$this->setToken( $token, $client_id, $user_id, $expires, $scope, false );
	}

	/**
	 * @see Storage::getRefreshToken()
	 */
	public function getRefreshToken( $token )
	{
		return $this->getToken( $token, true );
	}

	/**
	 * @see Storage::setRefreshToken()
	 */
	public function setRefreshToken( $token, $client_id, $user_id, $expires, $scope = null )
	{
		return $this->setToken( $token, $client_id, $user_id, $expires, $scope, true );
	}

	/**
	 * @see Storage::unsetRefreshToken()
	 */
	public function unsetRefreshToken( $token )
	{
		try
		{
			$_tableName = self::Table_Tokens;

			$_sql = <<<SQL
DELETE FROM
	{$_tableName}
WHERE
	refresh_token_text = :refresh_token_text
SQL;

			return \Kisma\Core\Utility\Sql::execute( $_sql, array( ':refresh_token_text' => $token ) );
		}
		catch ( \PDOException $_ex )
		{
			$this->_handleException( $_ex );
		}
	}

	/**
	 * Implements Storage::getAuthCode().
	 */
	public function getAuthCode( $code )
	{
		try
		{
			$_tableName = self::Table_Codes;

			$_sql = <<<SQL
SELECT
	code_text as code,
	client_id_text,
	user_id,
	redirect_uri_text as redirect_uri,
	expires_nbr as expires,
	scope_text as scope
FROM
	{$_tableName}
WHERE
	code_text = :code_text
SQL;

			if ( false === ( $_row = \Kisma\Core\Utility\Sql::find( $_sql, array( ':code_text' => $code ) ) ) )
			{
				return null;
			}

			return $_row;
		}
		catch ( \PDOException $_ex )
		{
			$this->_handleException( $_ex );
		}
	}

	/**
	 * Implements Storage::setAuthCode().
	 */
	public function setAuthCode( $code, $client_id, $user_id, $redirect_uri, $expires, $scope = null )
	{
		try
		{
			$_tableName = self::Table_Codes;

			$_sql = <<<SQL
INSERT INTO {$_tableName} (
	code_text,
	client_id_text,
	user_id,
	redirect_uri_text,
	expires_nbr,
	scope_text
)
VALUES
(
	:code_text,
	:client_id_text,
	:user_id,
	:redirect_uri_text,
	:expires_nbr,
	:scope_text
)
SQL;
			$_params = array(
				':code_text'         => $code,
				':client_id_text'    => $client_id,
				':user_id'           => $user_id,
				':redirect_uri_text' => $redirect_uri,
				':expires_nbr'       => $expires,
				':scope_text'        => $scope,
			);

			return \Kisma\Core\Utility\Sql::execute( $_sql, $_params, $this->_db );
		}
		catch ( \PDOException $_ex )
		{
			$this->_handleException( $_ex );
		}
	}

	/**
	 * @see Storage::checkRestrictedGrantType()
	 */
	public function checkRestrictedGrantType( $client_id, $grant_type )
	{
		return true; // Not implemented
	}

	/**
	 * Creates a refresh or access token
	 *
	 * @param string $token
	 * @param string $client_id
	 * @param mixed  $user_id
	 * @param int    $expires
	 * @param string $scope
	 * @param bool   $isRefresh
	 *
	 * @return int
	 */
	protected function setToken( $token, $client_id, $user_id, $expires, $scope, $isRefresh = true )
	{
		try
		{
			$_tableName = $isRefresh ? self::Table_Refresh : self::Table_Tokens;

			$_sql = <<<SQL
INSERT INTO {$_tableName} (
	token_text,
	client_id_text,
	user_id,
	expires_nbr,
	scope_text
)
VALUES
(
	:token_text,
	:client_id_text,
	:user_id,
	:expires_nbr,
	:scope_text
)
SQL;
			$_params = array(
				':token_text'     => $token,
				':client_id_text' => $client_id,
				':user_id'        => $user_id,
				':expires_nbr'    => $expires,
				':scope_text'     => $scope,
			);

			return \Kisma\Core\Utility\Sql::execute( $_sql, $_params );
		}
		catch ( \PDOException $_ex )
		{
			$this->_handleException( $_ex );
		}
	}

	/**
	 * Retrieves an access or refresh token.
	 *
	 * @param string $token
	 * @param bool   $isRefresh
	 *
	 * @return string
	 */
	protected function getToken( $token, $isRefresh = true )
	{
		try
		{
			$_tableName = $isRefresh ? self::Table_Refresh : self::Table_Tokens;
			$_tokenName = $isRefresh ? 'refresh_token' : 'oauth_token';

			$_sql = <<<SQL
SELECT
	token_text as {$_tokenName},
	client_id_text,
	expires_nbr as expires,
	scope_text as scope,
	user_id
FROM
	{$_tableName}
WHERE
	token_text = :token_text
SQL;
			$_params = array(
				':token_text' => $token,
			);

			if ( false === ( $_row = \Kisma\Core\Utility\Sql::find( $_sql, $_params ) ) )
			{
				return null;
			}

			return $_row;
		}
		catch ( \PDOException $_ex )
		{
			$this->_handleException( $_ex );
		}
	}

	/**
	 * @param string $client_secret
	 * @param string $client_id
	 *
	 * @return string
	 */
	private function _hash( $client_secret, $client_id )
	{
		return \Kisma\Core\Utility\Hasher::hash( $client_id . $client_secret . self::SaltyGoodness, 'blowfish' );
	}

	/**
	 * @param string $password
	 * @param string $client_id
	 * @param string $client_secret
	 *
	 * @return bool
	 */
	protected function _checkPassword( $password, $client_secret, $client_id )
	{
		return ( $password == $this->_hash( $client_secret, $client_id ) );
	}

	/**
	 * @param \Exception $exception
	 */
	protected function _handleException( $exception )
	{
		echo 'Database error: ' . $exception->getMessage();
		exit();
	}

}

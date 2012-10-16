<?php
/**
 * Client.php
 */
namespace Kisma\Seeds\Services\OAuth;
use Kisma\Core\Utility\Option;
use Kisma\Seeds\Exceptions\OAuth as Exceptions;

/**
 * Client
 * OAuth2.0 draft v10 client-side implementation.
 *
 * @author Originally written by Naitik Shah <naitik@facebook.com>.
 * @author Update to draft v10 by Edison Wong <hswong3i@pantarei-design.com>.
 * @author Adapted for Kisma by Jerry Ablan <jerryablan@gmail.com>
 */
abstract class Client extends \Kisma\Core\SeedBag
{
	//*************************************************************************
	//* Constants
	//*************************************************************************

	/**
	 * @var int The default Cache Lifetime (in seconds).
	 */
	const DefaultExpiration = 3600;
	/**
	 * @var string The default Base domain for the Cookie.
	 */
	const DefaultBaseDomain = null;
	/**
	 * @var string
	 */
	const DefaultUserAgent = 'oauth2-draft-v10';

	//*************************************************************************
	//* Members
	//*************************************************************************

	/**
	 * @var array Default curl options
	 */
	protected static $_curlOptions = array(
		CURLOPT_CONNECTTIMEOUT => 10,
		CURLOPT_RETURNTRANSFER => true,
		CURLOPT_HEADER         => true,
		CURLOPT_TIMEOUT        => 60,
		CURLOPT_USERAGENT      => self::DefaultUserAgent,
		CURLOPT_HTTPHEADER     => array( 'Accept: application/json' )
	);

	//*************************************************************************
	//* Methods
	//*************************************************************************

	/**
	 * @param array $options An associative array as below:
	 * - base_uri: The base URI for the OAuth2.0 endpoints.
	 * - code: (optional) The authorization code.
	 * - username: (optional) The username.
	 * - password: (optional) The password.
	 * - client_id: (optional) The application ID.
	 * - client_secret: (optional) The application secret.
	 * - authorize_uri: (optional) The end-user authorization endpoint URI.
	 * - access_token_uri: (optional) The token endpoint URI.
	 * - services_uri: (optional) The services endpoint URI.
	 * - cookie_support: (optional) TRUE to enable cookie support.
	 * - base_domain: (optional) The domain for the cookie.
	 * - file_upload_support: (optional) TRUE if file uploads are enabled.
	 */
	public function __construct( $options = array() )
	{
		if ( null !== ( $_baseUri = Option::get( $options, 'base_uri', null, true ) ) )
		{
			$this->set( 'base_uri', $_baseUri );
		}

		//	Use predefined OAuth2.0 params, or get it from $_REQUEST.
		foreach ( array( 'code', 'username', 'password' ) as $_key )
		{
			if ( null !== ( $_value = Option::get( $options, $_key, null, true ) ) )
			{
				$this->set( $_key, $_value );
			}
			elseif ( null !== ( $_value = \Kisma\Core\Utility\FilterInput::request( $_key ) ) )
			{
				$this->set( $_key, $_value );
			}
		}

		//	Endpoint URIs.
		foreach ( array( 'authorize_uri', 'access_token_uri', 'services_uri' ) as $_key )
		{
			if ( null !== ( $_value = Option::get( $options, $_key, null, true ) ) )
			{
				if ( 'http' == substr( $_value, 0, 4 ) )
				{
					$this->set( $_key, $_value );
				}
				else
				{
					$this->set( $_key, $_baseUri . $_value );
				}
			}
		}

		//	The rest go in the bag
		parent::__construct( $options );
	}

	/**
	 * Since $_SERVER['REQUEST_URI'] is only available on Apache, we generate an equivalent using other environment variables.
	 *
	 * @return string
	 */
	public function getRequestUri()
	{
		if ( null === ( $_uri = \Kisma\Core\Utility\FilterInput::server( 'REQUEST_URI' ) ) )
		{
			if ( null !== ( $_argv = \Kisma\Core\Utility\FilterInput::server( 'argv' ) ) )
			{
				$_uri = $_SERVER['SCRIPT_NAME'] . '?' . $_argv[0];
			}
			elseif ( isset( $_SERVER['QUERY_STRING'] ) )
			{
				$_uri = $_SERVER['SCRIPT_NAME'] . '?' . $_SERVER['QUERY_STRING'];
			}
			else
			{
				$_uri = $_SERVER['SCRIPT_NAME'];
			}
		}

		// Prevent multiple slashes to avoid cross site requests via the Form API.
		$_uri = '/' . ltrim( $_uri, '/' );

		return $_uri;
	}

	/**
	 * Make an API call.
	 *
	 * Support both OAuth2.0 or normal GET/POST API call, with relative
	 * or absolute URI.
	 *
	 * If no valid OAuth2.0 access token found in session object, this function
	 * will automatically switch as normal remote API call without "oauth_token"
	 * parameter.
	 *
	 * Assume server reply in JSON object and always decode during return. If
	 * you hope to issue a raw query, please use makeRequest().
	 *
	 * @param string $path        The target path, relative to base_path/service_uri or an absolute URI.
	 * @param string $method      (optional) The HTTP method (default 'GET').
	 * @param array  $_parameters (optional The GET/POST parameters.
	 *
	 * @throws \Kisma\Seeds\Exceptions\OAuth\ClientException
	 * @return array|mixed The JSON decoded response object.
	 */
	public function api( $path, $method = \Kisma\Core\Enums\HttpMethod::Get, $_parameters = array() )
	{
		if ( is_array( $method ) && empty( $_parameters ) )
		{
			$_parameters = $method;
			$method = \Kisma\Core\Enums\HttpMethod::Get;
		}

		//	json_encode all params values that are not strings.
		foreach ( $_parameters as $_key => $_value )
		{
			if ( !is_string( $_value ) )
			{
				$_parameters[$_key] = json_encode( $_value );
			}
		}

		$_result = json_decode( $this->_oauthRequest( $this->_getUri( $path ), $method, $_parameters ), true );

		//	Results are returned, errors are thrown.
		if ( null !== ( $_error = Option::get( $_result, 'error' ) ) )
		{
			$_ex = new Exceptions\ClientException( $_result );

			switch ( $_ex->getType() )
			{
				//	OAuth 2.0 Draft 10 style.
				case 'invalid_token':
					$this->setSession( null );
					break;

				default:
					$this->setSession( null );
					break;
			}

			throw $_ex;
		}

		return $_result;
	}

	/**
	 * @param array $session      (optional) The session object to be set. NULL if hope to frush existing session object.
	 * @param bool  $write_cookie (optional) TRUE if a cookie should be written. This value is ignored if cookie support has been disabled.
	 *
	 * @return \Kisma\Seeds\Services\OAuth\Client
	 */
	public function setSession( $session = null, $write_cookie = true )
	{
		$this->set( '_session', $this->_validateSessionObject( $session ) );
		$this->set( '_session_loaded', true );

		if ( $write_cookie )
		{
			$this->_setCookieFromSession( $this->get( '_session' ) );
		}

		return $this;
	}

	/**
	 * This will automatically look for a signed session via custom method,
	 * OAuth2.0 grant type with authorization_code, OAuth2.0 grant type with
	 * password, or cookie that we had already setup.
	 *
	 * @return array The valid session object with OAuth2.0 infomration, and NULL if not able to discover any cases.
	 */
	public function getSession()
	{
		if ( !$this->get( '_session_loaded' ) )
		{
			$_session = null;
			$_saveCookie = true;

			//	Try to get the login session from a custom method
			$_session = $this->_getSessionObject( null );
			$_session = $this->_validateSessionObject( $_session );

			//	grant_type == authorization_code.
			if ( null === $_session && null !== ( $_authCode = $this->get( 'code' ) ) )
			{
				$_token = $this->_getTokenFromAuthCode( $_authCode );
				$_session = $this->_getSessionObject( $_token );
				$_session = $this->_validateSessionObject( $_session );
			}

			//	grant_type == password.
			if ( null === $_session && null !== ( $_userName = $this->get( 'username' ) ) && null !== ( $_password = $this->get( 'password' ) ) )
			{
				$_token = $this->_getTokenFromUserCredentials( $_userName, $_password );
				$_session = $this->_getSessionObject( $_token );
				$_session = $this->_validateSessionObject( $_session );
			}

			// Try loading session from cookie if necessary.
			if ( null === $_session && $this->get( 'cookie_support' ) )
			{
				$_cookieName = $this->_getSessionCookieName();

				if ( null !== ( $_cookie = \Kisma\Core\Utility\FilterInput::cookie( $_cookieName ) ) )
				{
					$_session = array();

					parse_str( trim( get_magic_quotes_gpc() ? stripslashes( $_cookie ) : $_cookie, '"' ), $_session );
					$_session = $this->_validateSessionObject( $_session );

					//	Save only if we need to delete a invalid session cookie.
					$_saveCookie = empty( $_session );
				}
			}

			$this->setSession( $_session, $_saveCookie );
		}

		return $this->get( '_session' );
	}

	/**
	 * Gets an OAuth2.0 access token from session.
	 * This will trigger getSession() and so we MUST initialize with required configuration.
	 *
	 * @return array The valid OAuth2.0 access token, or null if none available.
	 */
	public function getAccessToken()
	{
		return Option::get( $this->getSession(), 'access_token' );
	}

	/**
	 * Try to get session object from custom method.
	 *
	 * By default we generate session object based on access_token response, or
	 * if it is provided from server with $_REQUEST. For sure, if it is provided
	 * by server it should follow our session object format.
	 *
	 * Session object provided by server can ensure the correct expires and
	 * base_domain setup as predefined in server, also you may get more useful
	 * information for custom functionality, too. BTW, this may require for
	 * additional remote call overhead.
	 *
	 * You may wish to override this function with your custom version due to
	 * your own server-side implementation.
	 *
	 * @param $access_token (optional) A valid access token in associative array as below:
	 * - access_token: A valid access_token generated by OAuth2.0 authorization endpoint.
	 * - expires_in: (optional) A valid expires_in generated by OAuth2.0 authorization endpoint.
	 * - refresh_token: (optional) A valid refresh_token generated by OAuth2.0 authorization endpoint.
	 * - scope: (optional) A valid scope generated by OAuth2.0 authorization endpoint.
	 *
	 * @return array A valid session object
	 */
	protected function _getSessionObject( $access_token = null )
	{
		$_session = null;

		//	Try to generate a local version of the session cookie
		if ( null !== ( $_token = Option::get( $access_token, 'access_token' ) ) )
		{
			$_session['access_token'] = $_token;
			$_session['base_domain'] = $this->get( 'base_domain', self::DefaultBaseDomain );
			$_session['expires'] = time() + Option::get( $access_token, 'expires_in', $this->get( 'expires_in', self::DefaultExpiration ) );
			$_session['refresh_token'] = Option::get( $access_token, 'refresh_token' );
			$_session['scope'] = Option::get( $access_token, 'scope' );
			$_session['secret'] = md5( base64_encode( pack( 'N6', mt_rand(), mt_rand(), mt_rand(), mt_rand(), mt_rand(), uniqid() ) ) );

			//	Create our signature
			$_session['sig'] = $this->_generateSignature( $_session, $this->get( 'client_secret' ) );
		}

		//	Try loading session from $_REQUEST.
		if ( null === $_session && null !== ( $_requestSession = \Kisma\Core\Utility\FilterInput::request( 'session' ) ) )
		{
			$_session = json_decode( get_magic_quotes_gpc() ? stripslashes( $_requestSession ) : $_requestSession, true );
		}

		return $_session;
	}

	/**
	 * Get access token from OAuth2.0 token endpoint with authorization code.
	 *
	 * This function will only be activated if both access token URI, client
	 * identifier and client secret are setup correctly.
	 *
	 * @param string $code Authorization code issued by authorization server's authorization endpoint.
	 *
	 * @return array A valid OAuth2.0 JSON decoded access token in associative array, or NULL if no token
	 */
	protected function _getTokenFromAuthCode( $code )
	{
		if ( $this->get( 'access_token_uri' ) && $this->get( 'client_id' ) && $this->get( 'client_secret' ) )
		{
			\Kisma\Core\Utility\Curl::setDecodeToArray( true );

			return \Kisma\Core\Utility\Curl::post(
				$this->get( 'access_token_uri' ),
				array(
					'grant_type'    => 'authorization_code',
					'client_id'     => $this->get( 'client_id' ),
					'client_secret' => $this->get( 'client_secret' ),
					'code'          => $code,
					'redirect_uri'  => $this->_getCurrentUri()
				),
				self::$_curlOptions
			);
		}

		return null;
	}

	/**
	 * Get access token from OAuth2.0 token endpoint with basic user
	 * credentials.
	 *
	 * This function will only be activated if both username and password
	 * are setup correctly.
	 *
	 * @param string $userName
	 * @param string $password
	 *
	 * @return array|null A valid OAuth2.0 JSON decoded access token in associative array, and NULL if not enough parameters or JSON decode failed.
	 */
	private function _getTokenFromUserCredentials( $userName, $password )
	{
		if ( $this->get( 'access_token_uri' ) && $this->get( 'client_id' ) && $this->get( 'client_secret' ) )
		{
			\Kisma\Core\Utility\Curl::setDecodeToArray( true );

			return \Kisma\Core\Utility\Curl::post(
				$this->get( 'access_token_uri' ),
				array(
					'grant_type'    => 'password',
					'client_id'     => $this->get( 'client_id' ),
					'client_secret' => $this->get( 'client_secret' ),
					'username'      => $userName,
					'password'      => $password
				),
				self::$_curlOptions
			);
		}

		return null;
	}

	/**
	 * Make an OAuth2.0 Request.
	 *
	 * Automatically append "oauth_token" in query parameters if not yet
	 * exists and able to discover a valid access token from session. Otherwise
	 * just ignore setup with "oauth_token" and handle the API call AS-IS, and
	 * so may issue a plain API call without OAuth2.0 protection.
	 *
	 * @param string $path        The target path, relative to base_path/service_uri or an absolute URI.
	 * @param string $method      (optional) The HTTP method (default 'GET').
	 * @param array  $_parameters (optional The GET/POST parameters.
	 *
	 * @return array The JSON decoded response object.
	 */
	protected function _oauthRequest( $path, $method = \Kisma\Core\Enums\HttpMethod::Get, $_parameters = array() )
	{
		if ( null === ( $_token = Option::get( $_parameters, 'oauth_token' ) ) )
		{
			if ( null !== ( $_token = $this->getAccessToken() ) )
			{
				$_parameters['oauth_token'] = $_token;
			}
		}

		return $this->_request( $path, $method, $_parameters );
	}

	/**
	 * Makes an HTTP request.
	 *
	 * @param string $path        The target path, relative to base_path/service_uri or an absolute URI.
	 * @param string $method      (optional) The HTTP method (default 'GET').
	 * @param array  $_parameters (optional The GET/POST parameters.
	 *
	 * @throws \Kisma\Seeds\Exceptions\OAuth\ClientException
	 * @return array The JSON decoded response object.
	 */
	protected function _request( $path, $method = \Kisma\Core\Enums\HttpMethod::Get, $_parameters = array() )
	{
		//	Disable the 'Expect: 100-continue' behavior.
		$_options = self::$_curlOptions;

		if ( !isset( $_options[CURLOPT_HTTPHEADER] ) )
		{
			$_options[CURLOPT_HTTPHEADER] = array( 'Expect:' );
		}
		else
		{
			$_options[CURLOPT_HTTPHEADER][] = 'Expect:';
		}

		if ( false === ( $_response = \Kisma\Core\Utility\Curl::request( $method, $path, $_parameters, $_options ) ) )
		{
			throw new Exceptions\ClientException( \Kisma\Core\Utility\Curl::getError() );
		}

		// Split the HTTP response into header and body.
		list( $_headers, $_body ) = explode( '\\r\\n\\r\\n', $_response );
		$_headers = explode( '\\r\\n', $_headers );

		//	Look for HTTP/1.1 4xx or HTTP/1.1 5xx error response.
		if ( false !== strpos( $_headers[0], 'HTTP/1.1 4' ) || false !== strpos( $_headers[0], 'HTTP/1.1 5' ) )
		{
			$_response = array( 'code' => 0, 'message' => null );

			if ( preg_match( '/^HTTP\/1.1 ([0-9]{3,3}) (.*)$/', $_headers[0], $_matches ) )
			{
				$_response['code'] = $_matches[1];
				$_response['message'] = $_matches[2];
			}

			//	In case a WWW-Authenticate header is returned, replace the description.
			foreach ( $_headers as $_header )
			{
				if ( preg_match( "/^WWW-Authenticate:.*error='(.*)'/", $_header, $_matches ) )
				{
					$_response['error'] = $_matches[1];
				}
			}

			return json_encode( $_response );
		}

		return $_body;
	}

	/**
	 * @return string The cookie name.
	 */
	private function _getSessionCookieName()
	{
		return 'oauth2_' . $this->get( 'client_id' );
	}

	/**
	 * Set a JS Cookie based on the _passed in_ session.
	 *
	 * It does not use the currently stored session - you need to explicitly pass it in.
	 *
	 * @param array $session The session to use for setting the cookie.
	 */
	protected function _setCookieFromSession( $session = null )
	{
		if ( !$this->get( 'cookie_support' ) )
		{
			return;
		}

		$_cookieName = $this->_getSessionCookieName();
		$_value = 'deleted';
		$_expires = time() - 3600;
		$_baseDomain = $this->get( 'base_domain', self::DefaultBaseDomain );

		if ( null !== $session )
		{
			$_value = '"' . http_build_query( $session, null, '&' ) . '"';
			$_baseDomain = Option::get( $session, 'base_domain', $_baseDomain );
			$_expires = Option::get( $session, 'expires', time() + $this->get( 'expires_in', self::DefaultExpiration ) );
		}

		//	Prepend dot if a domain is found.
		if ( !empty( $_baseDomain ) )
		{
			$_baseDomain = '.' . $_baseDomain;
		}

		//	If an existing cookie is not set, we do not need to delete it.
		if ( 'deleted' == $_value && null === \Kisma\Core\Utility\FilterInput::cookie( $_cookieName ) )
		{
			return;
		}

		if ( headers_sent() )
		{
			\Kisma\Core\Utility\Log::error( 'Could not set cookie. Headers already sent.' );
		}
		else
		{
			setcookie( $_cookieName, $_value, $_expires, '/', $_baseDomain );
		}
	}

	/**
	 * Validates a session_version = 3 style session object.
	 *
	 * @param array $session The session object.
	 *
	 * @return array|null The session object if it validates, NULL otherwise.
	 */
	protected function _validateSessionObject( $session )
	{
		// Make sure some essential fields exist.
		if ( is_array( $session ) && isset( $session['access_token'] ) && isset( $session['sig'] ) )
		{
			//	Validate the signature.
			$_sigFreeSession = $session;
			unset( $_sigFreeSession['sig'] );

			$_expected = $this->_generateSignature( $_sigFreeSession, $this->get( 'client_secret' ) );

			if ( $_expected != $session['sig'] )
			{
				\Kisma\Core\Utility\Log::error( 'Invalid session signature found in cookie.' );
				$session = null;
			}
		}
		else
		{
			$session = null;
		}

		return $session;
	}

	/**
	 * @return string The current URL.
	 */
	protected function _getCurrentUri()
	{
		$_protocol = isset( $_SERVER['HTTPS'] ) && $_SERVER['HTTPS'] == 'on' ? 'https://' : 'http://';

		$_currentUri = $_protocol . $_SERVER['HTTP_HOST'] . $this->getRequestUri();
		$_parts = parse_url( $_currentUri );

		$_query = $_port = null;

		if ( !empty( $_parts['query'] ) )
		{
			$_parameters = array();
			parse_str( $_parts['query'], $_parameters );
			$_parameters = array_filter( $_parameters );

			if ( !empty( $_parameters ) )
			{
				$_query = '?' . http_build_query( $_parameters, null, '&' );
			}
		}

		//	Use port if not default.
		if ( isset( $_parts['port'] ) && ( ( 'http://' === $_protocol && 80 !== $_parts['port'] ) || ( 'https://' === $_protocol && 443 !== $_parts['port'] ) ) )
		{
			$_port = ':' . $_parts['port'];
		}

		//	We can rebuild it!
		return $_protocol . $_parts['host'] . $_port . $_parts['path'] . $_query;
	}

	/**
	 * Build the URL for given path and parameters.
	 *
	 * @param string $path       (optional) The path.
	 * @param array  $parameters (optional) The query parameters in associative array.
	 *
	 * @return string The URL for the given parameters.
	 */
	protected function _getUri( $path = null, $parameters = array() )
	{
		$_url = $this->get( 'services_uri' ) ? $this->get( 'services_uri' ) : $this->get( 'base_uri' );

		if ( null !== $path )
		{
			if ( 'http' == substr( $path, 0, 4 ) )
			{
				$_url = $path;
			}
			else
			{
				$_url = rtrim( $_url, '/' ) . '/' . ltrim( $path, '/' );
			}
		}

		if ( !empty( $parameters ) )
		{
			$_url .= '?' . http_build_query( $parameters, null, '&' );
		}

		return $_url;
	}

	/**
	 * Generate a signature for the given params and secret.
	 *
	 * @param array  $parameters The parameters to sign.
	 * @param string $secret     The secret to sign with.
	 *
	 * @return string The generated signature
	 */
	protected function _generateSignature( $parameters, $secret )
	{
		ksort( $parameters );

		//	Generate the base string.
		$_base = null;

		foreach ( $parameters as $_key => $_value )
		{
			$_base .= $_key . '=' . $_value;
		}

		$_base .= $secret;

		return md5( $_base );
	}

}
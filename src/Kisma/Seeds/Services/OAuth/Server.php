<?php
/**
 * Server.php
 *
 *         OAuth 2.0 server in PHP, originally written for <a href="http://www.opendining.net/">Open Dining</a>.
 * Supports <a href="http://tools.ietf.org/html/draft-ietf-oauth-v2-20">IETF draft v20</a>.
 *
 * Source repo has sample servers implementations for
 * <a href="http://php.net/manual/en/book.pdo.php"> PHP Data Objects</a> and
 * <a href="http://www.mongodb.org/">MongoDB</a>. Easily adaptable to other
 * storage engines.
 *
 * PHP Data Objects supports a variety of databases, including MySQL,
 * Microsoft SQL Server, SQLite, and Oracle, so you can try out the sample
 * to see how it all works.
 *
 * @author Tim Ridgely <tim.ridgely@gmail.com>
 * @author Aaron Parecki <aaron@parecki.com>
 * @author Edison Wong <hswong3i@pantarei-design.com>
 * @author David Rochwerger <catch.dave@gmail.com>
 * @author Jerry Ablan <jerryablan@gmail.com>
 *
 * @see    http://code.google.com/p/oauth2-php/
 * @see    https://github.com/quizlet/oauth2-php
 */
namespace Kisma\Seeds\Services\OAuth;
use \Kisma\Core\Utility\Option;
use \Kisma\Core\Utility\FilterInput;
use \Kisma\Seeds\Exceptions as Exceptions;

/**
 * Server
 * Am OAuth 2.0 draft v20 server-side implementation.
 *
 * @TODO Add support for Message Authentication Code (MAC) token type.
 *
 * @author Originally written by Tim Ridgely <tim.ridgely@gmail.com>.
 * @author Updated to draft v10 by Aaron Parecki <aaron@parecki.com>.
 * @author Debug, coding style clean up and documented by Edison Wong <hswong3i@pantarei-design.com>.
 * @author Refactored (including separating from raw POST/GET) and updated to draft v20 by David Rochwerger <catch.dave@gmail.com>.
 * @author Adapted and Refactored by Jerry Ablan <jerryablan@gmail.com>
 */
class Server extends \Kisma\Core\SeedBag implements \Kisma\Seeds\Interfaces\OAuth\Server
{
	//*************************************************************************
	//* Private Members
	//*************************************************************************

	/**
	 * @var \Kisma\Seeds\Interfaces\OAuth\Storage Storage engine for authentication server
	 */
	protected $_storage;
	/**
	 * @var string Keep track of the old refresh token. So we can unset the old refresh tokens when a new one is issued.
	 */
	protected $_priorRefreshToken;

	//*************************************************************************
	//* Public Methods
	//*************************************************************************

	/**
	 * Constructor
	 */
	public function __construct( \Kisma\Seeds\Interfaces\OAuth\Storage $storage, $options = array() )
	{
		//	Start fresh
		$this->_resetOptions();
		$this->_storage = $storage;

		foreach ( $options as $_key => $_value )
		{
			$this->set( $_key, $_value );
		}
	}

	/**
	 * Check that a valid access token has been provided.
	 * The token is returned (as an associative array) if valid.
	 *
	 * The scope parameter defines any required scope that the token must have.
	 * If a scope param is provided and the token does not have the required
	 * scope, we bounce the request.
	 *
	 * Some implementations may choose to return a subset of the protected
	 * resource (i.e. "public" data) if the user has not provided an access
	 * token or if the access token is invalid or expired.
	 *
	 * The IETF spec says that we should send a 401 Unauthorized header and
	 * bail immediately so that's what the defaults are set to. You can catch
	 * the exception thrown and behave differently if you like (log errors, allow
	 * public access for missing tokens, etc)
	 *
	 * @param $token
	 * @param $scope A space-separated string of required scope(s), if you want to check for scope.
	 *
	 * @throws \Kisma\Seeds\Exceptions\AuthenticationException
	 * @return array
	 */
	public function verifyAccessToken( $token, $scope = null )
	{
		$_tokenType = $this->get( self::ConfigTokenType );
		$_realm = $this->get( self::ConfigRealm );

		//	Is the token good?
		if ( empty( $token ) )
		{
			throw new Exceptions\AuthenticationException(
				self::HttpBadRequest,
				$_tokenType,
				$_realm,
				self::Error_InvalidRequest,
				'The request is missing a required parameter; includes an unsupported parameter or parameter value; repeats the same parameter; uses more than one method for including an access token; or is otherwise malformed.',
				$scope
			);
		}

		//	Get the stored token data (from the implementing subclass)
		if ( null === ( $_token = $this->_storage->getAccessToken( $token ) ) )
		{
			throw new Exceptions\AuthenticationException(
				self::HttpUnauthorized,
				$_tokenType,
				$_realm,
				self::Error_InvalidGrant,
				'The access token provided is invalid.',
				$scope
			);
		}

		//	Check we have a well formed token
		if ( !isset( $token['expires'] ) || !isset( $token['client_id'] ) )
		{
			throw new Exceptions\AuthenticationException(
				self::HttpUnauthorized,
				$_tokenType,
				$_realm,
				self::Error_InvalidGrant,
				'Malformed token (missing "expires" or "client_id")',
				$scope
			);
		}

		//	Check token expiration (expires is a mandatory paramter)
		if ( isset( $token['expires'] ) && time() > $token['expires'] )
		{
			throw new Exceptions\AuthenticationException(
				self::HttpUnauthorized,
				$_tokenType,
				$_realm,
				self::Error_InvalidGrant,
				'The access token provided has expired.',
				$scope
			);
		}

		//	Check scope, if provided. If token doesn't have a scope, it's NULL/empty, or it's insufficient, then throw an error
		$_tokenScope = Option::get( $token, 'scope' );

		if ( !empty( $scope ) && ( null !== $_tokenScope || !$this->_checkScope( $scope, $_tokenScope ) ) )
		{
			throw new Exceptions\AuthenticationException(
				self::HttpForbidden,
				$_tokenType,
				$_realm,
				self::Error_InsufficientScope,
				'The request requires different privileges than provided by the access token.',
				$scope
			);
		}

		return $token;
	}

	/**
	 * This is a convenience function that can be used to get the token, which can then
	 * be passed to verifyAccessToken(). This method is an attempt at adhering to the
	 * constraints specified by the draft.
	 *
	 * As per the Bearer spec (draft 8, section 2) - there are three ways for a client
	 * to specify the bearer token, in order of preference: Authorization Header,
	 * POST and GET.
	 *
	 * NB: Resource servers MUST accept tokens via the Authorization scheme
	 * (http://tools.ietf.org/html/draft-ietf-oauth-v2-bearer-08#section-2).
	 *
	 * @todo Should TLS/SSL be enforced in this function?
	 *
	 * @see  http://tools.ietf.org/html/draft-ietf-oauth-v2-bearer-08#section-2.1
	 * @see  http://tools.ietf.org/html/draft-ietf-oauth-v2-bearer-08#section-2.2
	 * @see  http://tools.ietf.org/html/draft-ietf-oauth-v2-bearer-08#section-2.3
	 *
	 * Old Android version bug (at least with version 2.2)
	 * @see  http://code.google.com/p/android/issues/detail?id=6684
	 *
	 * We don't want to test this functionality as it relies on superglobals and headers:
	 * @codeCoverageIgnoreStart
	 */
	public function getBearerToken()
	{
		$_tokenType = $this->get( self::ConfigTokenType );
		$_realm = $this->get( self::ConfigRealm );
		$_postToken = FilterInput::post( self::TokenParameterName );
		$_getToken = FilterInput::get( INPUT_GET, self::TokenParameterName );

		if ( null === ( $_headers = trim( FilterInput::server( 'HTTP_AUTHORIZATION' ) ) ) )
		{
			if ( function_exists( 'apache_request_headers' ) )
			{
				$_requestHeaders = apache_request_headers();

				//	Server-side fix for bug in old Android versions (a nice side-effect of this fix means we don't care about capitalization for Authorization)
				$_requestHeaders = array_combine( array_map( 'ucwords', array_keys( $_requestHeaders ) ), array_values( $_requestHeaders ) );

				$_headers = Option::get( $_requestHeaders, 'Authorization' );
			}
		}

		// Check that exactly one method was used
		$_methodsUsed = !empty( $_headers ) + isset( $_getToken ) + isset( $_postToken );

		if ( $_methodsUsed > 1 )
		{
			throw new Exceptions\AuthenticationException(
				self::HttpBadRequest,
				$_tokenType,
				$_realm,
				self::Error_InvalidRequest,
				'Only one method may be used to authenticate at a time (Auth header, GET or POST).'
			);
		}
		elseif ( $_methodsUsed == 0 )
		{
			throw new Exceptions\AuthenticationException(
				self::HttpBadRequest,
				$_tokenType,
				$_realm,
				self::Error_InvalidRequest,
				'The access token was not found.'
			);
		}

		//	HEADER: Get the access token from the header
		if ( !empty( $_headers ) )
		{
			if ( !preg_match( '/' . self::TokenBearerHeaderName . '\s(\S+)/', $_headers, $_matches ) )
			{
				throw new Exceptions\AuthenticationException(
					self::HttpBadRequest,
					$_tokenType,
					$_realm,
					self::Error_InvalidRequest,
					'Malformed auth header'
				);
			}

			return $_matches[1];
		}

		//	POST: Get the token from POST data
		if ( null !== $_postToken )
		{
			if ( 'POST' != $_SERVER['REQUEST_METHOD'] )
			{
				throw new Exceptions\AuthenticationException(
					self::HttpBadRequest,
					$_tokenType,
					$_realm,
					self::Error_InvalidRequest,
					'When putting the token in the body, the method must be POST.'
				);
			}

			//	IETF specifies content-type. NB: Not all webservers populate this _SERVER variable
			if ( 'application/x-www-form-urlencoded' != FilterInput::server( 'CONTENT_TYPE' ) )
			{
				throw new Exceptions\AuthenticationException(
					self::HttpBadRequest,
					$_tokenType,
					$_realm,
					self::Error_InvalidRequest,
					'The content type for POST requests must be "application/x-www-form-urlencoded"'
				);
			}

			return $_postToken;
		}

		//	GET method
		return $_getToken;
	}

	/**
	 * Grant or deny a requested access token.
	 * This would be called from the "/token" endpoint as defined in the spec.
	 * Obviously, you can call your endpoint whatever you want.
	 *
	 * @param array $inputData The draft specifies that the parameters should be retrieved from POST, but you can override to whatever method you like.
	 * @param array $authHeaders
	 *
	 * @throws \Kisma\Seeds\Exceptions\ServerException
	 * @return void
	 */
	public function grantAccessToken( array $inputData = null, array $authHeaders = null )
	{
		$_filters = array(
			'grant_type'    => array(
				'filter'  => FILTER_VALIDATE_REGEXP,
				'options' => array( 'regexp' => self::RegExpFilter_GrantType ),
				'flags'   => FILTER_REQUIRE_SCALAR
			),
			'scope'         => array( 'flags' => FILTER_REQUIRE_SCALAR ),
			'code'          => array( 'flags' => FILTER_REQUIRE_SCALAR ),
			'redirect_uri'  => array( 'filter' => FILTER_SANITIZE_URL ),
			'username'      => array( 'flags' => FILTER_REQUIRE_SCALAR ),
			'password'      => array( 'flags' => FILTER_REQUIRE_SCALAR ),
			'refresh_token' => array( 'flags' => FILTER_REQUIRE_SCALAR ),
		);

		//	Input data by default can be either POST or GET
		if ( null !== $inputData )
		{
			$inputData = ( 'POST' == $_SERVER['REQUEST_METHOD'] ? $_POST : $_GET );
		}

		//	Basic authorization header
		if ( null === $authHeaders )
		{
			$authHeaders = $this->_getAuthorizationHeader();
		}

		//	Filter input data
		$_input = filter_var_array( $inputData, $_filters );

		//	Grant Type must be specified.
		if ( null === ( $_grantType = Option::get( $_input, 'grant_type' ) ) )
		{
			throw new Exceptions\ServerException(
				self::HttpBadRequest,
				self::Error_InvalidRequest,
				'Invalid grant_type parameter or parameter missing'
			);
		}

		//	Authorize the client
		$_client = $this->_getClientCredentials( $inputData, $authHeaders );

		if ( false === $this->_storage->checkClientCredentials( $_client[0], $_client[1] ) )
		{
			throw new Exceptions\ServerException(
				self::HttpBadRequest,
				self::Error_InvalidClient,
				'The client credentials are invalid'
			);
		}

		if ( !$this->_storage->checkRestrictedGrantType( $_client[0], $_grantType ) )
		{
			throw new Exceptions\ServerException(
				self::HttpBadRequest,
				self::Error_UnauthorizedClient,
				'The grant type is unauthorized for this client_id'
			);
		}

		//	Do the granting
		switch ( $_grantType )
		{
			//.........................................................................
			//. Code
			//.........................................................................

			case self::GrantTypeAuthCode:
				if ( !( $this->_storage instanceof \Kisma\Seeds\Interfaces\OAuth\GrantCode ) )
				{
					throw new Exceptions\ServerException(
						self::HttpBadRequest,
						self::Error_UnsupportedGrantType
					);
				}

				if ( null === ( $_code = Option::get( $_input, 'code' ) ) )
				{
					throw new Exceptions\ServerException(
						self::HttpBadRequest,
						self::Error_InvalidRequest,
						'Missing parameter: "code" is required'
					);
				}

				if ( $this->get( self::ConfigEnforceInputRedirect ) && null === ( $_redirectUri = Option::get( $_input, 'redirect_uri' ) ) )
				{
					throw new Exceptions\ServerException(
						self::HttpBadRequest,
						self::Error_InvalidRequest,
						'The redirect URI parameter is required.'
					);
				}

				//	Check the code exists
				/** @noinspection PhpUndefinedMethodInspection */
				if ( null === ( $_stored = $this->_storage->getAuthCode( $_code ) ) || $_client[0] != Option::get( $_stored, 'client_id' ) )
				{
					throw new Exceptions\ServerException(
						self::HttpBadRequest,
						self::Error_InvalidGrant,
						'Refresh token doesn\'t exist or is invalid for the client'
					);
				}

				//	Validate the redirect URI. If a redirect URI has been provided on input, it must be validated
				$_redirectUri = Option::get( $_input, 'redirect_uri' );

				if ( null !== $_redirectUri && !$this->_validateRedirectUri( $_redirectUri, Option::get( $_stored, 'redirect_uri' ) ) )
				{
					throw new Exceptions\ServerException(
						self::HttpBadRequest,
						self::Error_RedirectUriMismatch,
						'The redirect URI is missing or do not match'
					);
				}

				if ( Option::get( $_stored, 'expires' ) < time() )
				{
					throw new Exceptions\ServerException(
						self::HttpBadRequest,
						self::Error_InvalidGrant,
						'The authorization code has expired'
					);
				}
				break;

			//.........................................................................
			//. User Credentials
			//.........................................................................

			case self::GrantTypeUserCredentials:
				if ( !( $this->_storage instanceof \Kisma\Seeds\Interfaces\OAuth\GrantUser ) )
				{
					throw new Exceptions\ServerException(
						self::HttpBadRequest,
						self::Error_UnsupportedGrantType
					);
				}

				if ( null === ( $_userName = Option::get( $_input, 'username' ) ) || null === ( $_password = Option::get( $_input, 'password' ) ) )
				{
					throw new Exceptions\ServerException(
						self::HttpBadRequest,
						self::Error_InvalidRequest,
						'Missing parameters: "username" and "password" required'
					);
				}

				/** @noinspection PhpUndefinedMethodInspection */
				if ( false === ( $_stored = $this->_storage->checkUserCredentials( $_client[0], $_userName, $_password ) ) )
				{
					throw new Exceptions\ServerException(
						self::HttpBadRequest,
						self::Error_InvalidGrant
					);
				}
				break;

			//.........................................................................
			//. Client Credentials
			//.........................................................................

			case self::GrantTypeClientCredentials:
				if ( !( $this->_storage instanceof \DreamFactory\Interfaces\OAuth\GrantClient ) )
				{
					throw new Exceptions\ServerException(
						self::HttpBadRequest,
						self::Error_UnsupportedGrantType );
				}

				if ( empty( $_client[1] ) )
				{
					throw new Exceptions\ServerException(
						self::HttpBadRequest,
						self::Error_InvalidClient,
						'The client_secret is mandatory for the "client_credentials" grant type'
					);
				}

				// NB: We don't need to check for $_stored == false, because it was checked above already
				/** @noinspection PhpUndefinedMethodInspection */
				$_stored = $this->_storage->checkClientCredentialsGrant( $_client[0], $_client[1] );
				break;

			//.........................................................................
			//. Refresh Token
			//.........................................................................

			case self::GrantTypeRefreshToken:
				if ( !( $this->_storage instanceof IOAuth2RefreshTokens ) )
				{
					throw new Exceptions\ServerException(
						self::HttpBadRequest,
						self::Error_UnsupportedGrantType
					);
				}

				if ( null === ( $_refreshToken = Option::get( $_input, 'refresh_token' ) ) )
				{
					throw new Exceptions\ServerException(
						self::HttpBadRequest,
						self::Error_InvalidRequest,
						'Missing parameter: "refresh_token" required.'
					);
				}

				/** @noinspection PhpUndefinedMethodInspection */
				$_stored = $this->_storage->getRefreshToken( $_refreshToken );

				if ( $_stored === null || $_client[0] != Option::get( $_input, 'client_id' ) )
				{
					throw new Exceptions\ServerException(
						self::HttpBadRequest,
						self::Error_InvalidGrant,
						'Invalid refresh token'
					);
				}

				if ( Option::get( $_stored, 'expires' ) < time() )
				{
					throw new Exceptions\ServerException(
						self::HttpBadRequest,
						self::Error_InvalidGrant,
						'Refresh token has expired'
					);
				}

				//	Store the refresh token locally so we can delete it when a new refresh token is generated
				$this->_priorRefreshToken = $_refreshToken;
				break;

			//.........................................................................
			//. Implicit Grants
			//.........................................................................

			case self::GrantTypeImplicit:
				if ( !( $this->_storage instanceof \Kisma\Seeds\Interfaces\OAuth\GrantImplicit ) )
				{
					throw new Exceptions\ServerException(
						self::HttpBadRequest,
						self::Error_UnsupportedGrantType
					);
				}

				//@TODO: Implement Implicit Grant Types
				throw new Exceptions\ServerException(
					'501 Not Implemented',
					'This functionality is not yet implemented.'
				);

			//.........................................................................
			//. Extended/Custom Grants
			//.........................................................................

			case filter_var( $_grantType, FILTER_VALIDATE_URL ):
				if ( !( $this->_storage instanceof \Kisma\Seeds\Interfaces\OAuth\GrantExtension ) )
				{
					throw new Exceptions\ServerException(
						self::HttpBadRequest,
						self::Error_UnsupportedGrantType
					);
				}

				$_uri = filter_var( $_grantType, FILTER_VALIDATE_URL );

				/** @noinspection PhpUndefinedMethodInspection */
				if ( false === ( $_stored = $this->_storage->checkGrantExtension( $_uri, $inputData, $authHeaders ) ) )
				{
					throw new Exceptions\ServerException(
						self::HttpBadRequest,
						self::Error_InvalidGrant );
				}
				break;

			default :
				throw new Exceptions\ServerException(
					self::HttpBadRequest,
					self::Error_InvalidRequest,
					'Invalid grant_type parameter or parameter missing'
				);
		}

		$_scope = Option::get( $_input, 'scope' );

		if ( null === ( $_storedScope = Option::get( $_stored, 'scope' ) ) )
		{
			$_stored['scope'] = null;
		}

		//	Check scope, if provided
		if ( $_scope && ( !is_array( $_stored ) || empty( $_storedScope ) ) || !$this->_checkScope( $_scope, $_storedScope ) )
		{
			throw new Exceptions\ServerException(
				self::HttpBadRequest,
				self::Error_InvalidScope,
				'An unsupported scope was requested.'
			);
		}

		$_userId = Option::get( $_stored, 'user_id' );
		$_token = $this->_createAccessToken( $_client[0], $_userId, $_storedScope );

		//	Send response
		if ( !headers_sent() && 'cli' != PHP_SAPI )
		{
			header( 'Content-Type: application/json' );
			header( 'Cache-Control: no-store' );
		}

		echo json_encode( $_token );
	}

	//	@codeCoverageIgnoreEnd

	/**
	 * Redirect the user appropriately after approval.
	 *
	 * After the user has approved or denied the access request the
	 * authorization server should call this function to redirect the user
	 * appropriately.
	 *
	 * @param bool   $authorized TRUE or FALSE depending on whether the user authorized the access.* TRUE or FALSE depending on whether the user authorized the access.
	 * @param string $user_id    Identifier of user who authorized the client
	 * @param array  $params     An associative array as below:
	 * - response_type: The requested response: an access token, an
	 *                           authorization code, or both.
	 * - client_id: The client identifier as described in Section 2.
	 * - redirect_uri: An absolute URI to which the authorization server
	 *                           will redirect the user-agent to when the end-user authorization
	 *                           step is completed.
	 * - scope: (optional) The scope of the access request expressed as a
	 *                           list of space-delimited strings.
	 * - state: (optional) An opaque value used by the client to maintain
	 *                           state between the request and callback.
	 *
	 * @return void
	 */
	public function finishClientAuthorization( $authorized, $user_id = null, $params = array() )
	{
		list( $redirect_uri, $result ) = $this->getAuthResult( $authorized, $user_id, $params );

		header( 'HTTP/1.1 ' . self::HttpFound );
		header( 'Location: ' . $this->_buildUri( $redirect_uri, $result ) );
		exit();
	}

	/**
	 * @param bool   $authorized
	 * @param string $userId
	 * @param array  $parameters
	 *
	 * @throws \Kisma\Seeds\Exceptions\RedirectException
	 * @return array
	 */
	public function getAuthResult( $authorized, $userId = null, $parameters = array() )
	{
		$_result = array();
		$parameters = $this->getAuthorizeParams( $parameters );
		$parameters += array( 'scope' => null, 'state' => null );
		extract( $parameters );

		/**
		 * @var $state             string
		 * @var $scope             string
		 * @var $client_id         string
		 * @var $redirect_uri      string
		 * @var $response_type     string
		 */

		if ( null !== $state )
		{
			$_result['query']['state'] = $state;
		}

		if ( false === $authorized )
		{
			throw new Exceptions\RedirectException(
				$redirect_uri,
				self::Error_UserDenied,
				'User not allowed access to this resource.',
				$state
			);
		}

		if ( self::ResponseTypeAuthCode == $response_type )
		{
			$result['query']['code'] = $this->_createAuthCode( $client_id, $userId, $redirect_uri, $scope );
		}
		elseif ( self::ResponseTypeAccessToken == $response_type )
		{
			$_result['fragment'] = $this->_createAccessToken( $client_id, $userId, $scope );
		}

		return array( $redirect_uri, $_result );
	}

	//*************************************************************************
	//* Private Methods
	//*************************************************************************

	/**
	 * Pull the authorization request data out of the HTTP request.
	 *
	 * The redirect_uri is OPTIONAL as per draft 20. But your implementation can enforce it
	 * by setting Config_ENFORCE_INPUT_REDIRECT to true.
	 *
	 * The state is OPTIONAL but recommended to enforce CSRF. Draft 21 states, however, that
	 * CSRF protection is MANDATORY. You can enforce this by setting the ConfigEnforceState to true.
	 *
	 * @param array $inputData The draft specifies that the parameters should be retrieved from GET, but you can override to whatever method you like.
	 *
	 * @throws \Kisma\Seeds\Exceptions\RedirectException
	 * @throws \Kisma\Seeds\Exceptions\ServerException
	 *
	 * @return mixed The authorization parameters so the authorization server can prompt
	 *
	 * @see http://tools.ietf.org/html/draft-ietf-oauth-v2-20#section-4.1.1
	 * @see http://tools.ietf.org/html/draft-ietf-oauth-v2-21#section-10.12
	 */
	public function getAuthorizeParams( array $inputData = null )
	{
		$_filters = array(
			'client_id'     => array(
				'filter'  => FILTER_VALIDATE_REGEXP,
				'options' => array( 'regexp' => self::RegExpFilter_ClientId ),
				'flags'   => FILTER_REQUIRE_SCALAR
			),
			'response_type' => array( 'flags' => FILTER_REQUIRE_SCALAR ),
			'redirect_uri'  => array( 'filter' => FILTER_SANITIZE_URL ),
			'state'         => array( 'flags' => FILTER_REQUIRE_SCALAR ),
			'scope'         => array( 'flags' => FILTER_REQUIRE_SCALAR )
		);

		$_input = filter_var_array( $inputData = $inputData ? : $_GET, $_filters );
		$_state = Option::get( $_input, 'state' );
		$_scope = Option::get( $_input, 'scope' );

		//	Make sure a valid client id was supplied (we can not redirect because we were unable to verify the URI)
		if ( null === ( $_clientId = Option::get( $_input, 'client_id' ) ) )
		{
			throw new Exceptions\ServerException(
				self::HttpBadRequest,
				self::Error_InvalidClient,
				'Missing parameter: "client_id" required.'
			);
		}

		//	Get client details
		if ( false === ( $_clientDetails = $this->_storage->getClientDetails( $_clientId ) ) )
		{
			throw new Exceptions\ServerException(
				self::HttpBadRequest,
				self::Error_InvalidClient,
				'Invalid client ID' );
		}

		$_clientRedirectUri = Option::get( $_clientDetails, 'redirect_uri' );

		// Make sure a valid redirect_uri was supplied. If specified, it must match the stored URI.
		if ( null === ( $_redirectUri = Option::get( $_input, 'redirect_uri', $_clientRedirectUri ) ) )
		{
			throw new Exceptions\ServerException(
				self::HttpBadRequest,
				self::Error_RedirectUriMismatch,
				'Missing parameter: "redirect_uri" required.'
			);
		}

		if ( $this->get( self::ConfigEnforceInputRedirect ) && null === $_redirectUri )
		{
			throw new Exceptions\ServerException(
				self::HttpBadRequest,
				self::Error_RedirectUriMismatch,
				'Missing parameter: "redirect_uri" required.'
			);
		}

		//	Only need to validate if redirect_uri provided on input and stored.
		if ( null !== $_clientRedirectUri && null !== $_redirectUri && !$this->_validateRedirectUri( $_clientRedirectUri, $_clientRedirectUri ) )
		{
			throw new Exceptions\ServerException(
				self::HttpBadRequest,
				self::Error_RedirectUriMismatch,
				'The redirect URI provided is missing or does not match'
			);
		}

		// Select the redirect URI
		$_input['redirect_uri'] = $_redirectUri ? : $_clientRedirectUri;

		// type and client_id are required
		if ( null === ( $_responseType = Option::get( $_input, 'response_type' ) ) )
		{
			throw new Exceptions\RedirectException( $_redirectUri, self::Error_InvalidRequest, 'Invalid or missing response type.', $_state );
		}

		if ( self::ResponseTypeAuthCode != $_responseType && self::ResponseTypeAccessToken != $_responseType )
		{
			throw new Exceptions\RedirectException( $_redirectUri, self::Error_UnsupportedResponseType, null, $_state );
		}

		//	Validate that the requested scope is supported
		if ( empty( $_scope ) || !$this->_checkScope( $_scope, $this->get( self::ConfigSupportedScopes ) ) )
		{
			throw new Exceptions\RedirectException( $_redirectUri, self::Error_InvalidScope, 'An unsupported scope was requested.', $_state );
		}

		//	Validate state parameter exists (if configured to enforce this)
		if ( $this->get( self::ConfigEnforceState ) && empty( $_state ) )
		{
			throw new Exceptions\RedirectException(
				$_redirectUri,
				self::Error_InvalidRequest,
				'Missing parameter: "state" required.'
			);
		}

		//	Return retrieved client details together with input
		return ( $_input + $_clientDetails );
	}

	//*************************************************************************
	//* Private Methods
	//*************************************************************************

	/**
	 * Default configuration options are specified here.
	 */
	protected function _resetOptions()
	{
		$this->set( self::ConfigAccessLifetime, self::DefaultAccessTokenLifetime );
		$this->set( self::ConfigRefreshLifetime, self::DefaultRefreshTokenLifetime );
		$this->set( self::ConfigAuthLifetime, self::DefaultAuthCodeLifetime );
		$this->set( self::ConfigRealm, self::DefaultRealm );
		$this->set( self::ConfigTokenType, self::TokenTypeBearer );
		$this->set( self::ConfigEnforceInputRedirect, false );
		$this->set( self::ConfigEnforceState, false );
		$this->set( self::ConfigSupportedScopes, array() );
	}

	/**
	 * Check if everything in required scope is contained in available scope.
	 *
	 * @param string $requiredScope Required scope to be check with.
	 * @param string $availableScope
	 *
	 * @return bool TRUE if everything in required scope is contained in available scope,
	 *
	 * @see http://tools.ietf.org/html/draft-ietf-oauth-v2-20#section-7
	 */
	private function _checkScope( $requiredScope, $availableScope )
	{
		// The required scope should match or be a subset of the available scope
		if ( !is_array( $requiredScope ) )
		{
			$requiredScope = explode( ' ', trim( $requiredScope ) );
		}

		if ( !is_array( $availableScope ) )
		{
			$availableScope = explode( ' ', trim( $availableScope ) );
		}

		return ( 0 == count( array_diff( $requiredScope, $availableScope ) ) );
	}

	/**
	 * Internal function used to get the client credentials from HTTP basic
	 * auth or POST data.
	 *
	 * According to the spec (draft 20), the client_id can be provided in
	 * the Basic Authorization header (recommended) or via GET/POST.
	 *
	 * @param array $inputData
	 * @param array $authHeaders
	 *
	 * @throws \Kisma\Seeds\Exceptions\ServerException
	 * @return array A list containing the client identifier and password, for example
	 * @code
	 *      return array(
	 *      CLIENT_ID,
	 *      CLIENT_SECRET
	 * );
	 * @endcode
	 *
	 * @see http://tools.ietf.org/html/draft-ietf-oauth-v2-20#section-2.4.1
	 */
	protected function _getClientCredentials( array $inputData, array $authHeaders )
	{
		//	Basic Authentication is used
		if ( !empty( $authHeaders['PHP_AUTH_USER'] ) )
		{
			return array( $authHeaders['PHP_AUTH_USER'], $authHeaders['PHP_AUTH_PW'] );
		}
		elseif ( empty( $inputData['client_id'] ) )
		{
			// No credentials were specified
			throw new Exceptions\ServerException(
				self::HttpBadRequest,
				self::Error_InvalidClient,
				'Missing parameter: "client_id" required.'
			);
		}

		//	This method is not recommended, but is supported by specification
		return array(
			FilterInput::get( $inputData, 'client_id' ),
			FilterInput::get( $inputData, 'client_secret' ),
		);
	}

	/**
	 * Handle the creation of access token, also issue refresh token if support.
	 *
	 * This belongs in a separate factory, but to keep it simple, I'm just
	 * keeping it here.
	 *
	 * @param string $client_id Client identifier related to the access token.
	 * @param string $user_id
	 * @param string $scope     (optional) Scopes to be stored in space-separated string.
	 *
	 * @return array
	 *
	 * @see http://tools.ietf.org/html/draft-ietf-oauth-v2-20#section-5
	 */
	protected function _createAccessToken( $client_id, $user_id, $scope = null )
	{

		$_token = array(
			'access_token' => $this->_generateAccessToken(),
			'expires_in'   => $this->get( self::ConfigAccessLifetime ),
			'token_type'   => $this->get( self::ConfigTokenType ),
			'scope'        => $scope
		);

		$this->_storage->setAccessToken(
			$_token['access_token'],
			$client_id,
			$user_id,
			time() + $this->get( self::ConfigAccessLifetime ),
			$scope
		);

		// Issue a refresh token also, if we support them
		if ( $this->_storage instanceof \Kisma\Seeds\Interfaces\OAuth\RefreshToken )
		{
			$_token['refresh_token'] = $this->_generateAccessToken();

			/** @noinspection PhpUndefinedMethodInspection */
			$this->_storage->setRefreshToken(
				$_token['refresh_token'],
				$client_id,
				$user_id,
				time() + $this->get( self::ConfigRefreshLifetime ),
				$scope
			);

			//	If we've granted a new refresh token, expire the old one
			if ( $this->_priorRefreshToken )
			{
				/** @noinspection PhpUndefinedMethodInspection */
				$this->_storage->unsetRefreshToken( $this->_priorRefreshToken );
				unset( $this->_priorRefreshToken );
			}
		}

		return $_token;
	}

	/**
	 * Generates an unique access token.
	 *
	 * Implementing classes may want to override this function to implement
	 * other access token generation schemes.
	 *
	 * @return string A unique access token.
	 */
	protected function _generateAccessToken()
	{
		$_length = 40;

		if ( file_exists( '/dev/urandom' ) )
		{
			//	Get 100 bytes of random data
			$_seed = file_get_contents( '/dev/urandom', false, null, 0, 100 ) . uniqid( mt_rand(), true );
		}
		else
		{
			$_seed = \Kisma\Core\Utility\Hasher::generate( 100 );
		}

		return substr( \Kisma\Core\Utility\Hasher::hash( $_seed, 'sha512' ), 0, $_length );
	}

	/**
	 * Generates an unique auth code.
	 *
	 * Implementing classes may want to override this function to implement
	 * other auth code generation schemes.
	 *
	 * @return string The unique auth code.
	 */
	protected function _generateAuthCode()
	{
		return $this->_generateAccessToken();
	}

	/**
	 * Pull out the Authorization HTTP header and return it.
	 * According to draft 20, standard basic authorization is the only
	 * header variable required (this does not apply to extended grant types).
	 *
	 * Implementing classes may need to override this function if need be.
	 *
	 * @return array An array of the basic username and password provided.
	 */
	protected function _getAuthorizationHeader()
	{
		return array(
			'PHP_AUTH_USER' => FilterInput::server( 'PHP_AUTH_USER' ),
			'PHP_AUTH_PW'   => FilterInput::server( 'PHP_AUTH_PW' ),
		);
	}

	/**
	 * Internal method for validating redirect URI supplied
	 *
	 * @param string $inputUri
	 * @param string $storedUri
	 *
	 * @return bool
	 */
	protected function _validateRedirectUri( $inputUri, $storedUri )
	{
		if ( empty( $inputUri ) || empty( $storedUri ) )
		{
			return false;
		}

		return 0 === strcasecmp( substr( $inputUri, 0, strlen( $storedUri ) ), $storedUri );
	}

	/**
	 * Handle the creation of auth code.
	 *
	 * This belongs in a separate factory, but to keep it simple, I'm just keeping it here.
	 *
	 * @param string $client_id    Client identifier related to the access token.
	 * @param string $user_id
	 * @param string $redirect_uri An absolute URI to which the authorization server will
	 *                             redirect the user-agent to when the end-user authorization step is completed.
	 * @param string $scope        (optional) Scopes to be stored in space-separated string.
	 *
	 * @return string
	 */
	private function _createAuthCode( $client_id, $user_id, $redirect_uri, $scope = null )
	{
		/** @noinspection PhpUndefinedMethodInspection */
		$this->_storage->setAuthCode(
			$_authCode = $this->_generateAuthCode(),
			$client_id,
			$user_id,
			$redirect_uri,
			time() + $this->get( self::ConfigAuthLifetime ),
			$scope
		);

		return $_authCode;
	}

	/**
	 * Build the absolute URI based on supplied URI and parameters.
	 *
	 * @param string $uri    An absolute URI.
	 * @param array  $params Parameters to be append as GET.
	 *
	 * @return An absolute URI with supplied parameters.
	 */
	private function _buildUri( $uri, $params )
	{
		$_parsedUrl = parse_url( $uri );

		// Add our params to the parsed uri
		foreach ( $params as $_key => $_value )
		{
			$_parsedUrl[$_key] .= ( isset( $_parsedUrl[$_key] ) ? '&' : null ) . http_build_query( $_value );
		}

		// Rebuild
		return
			( ( isset( $_parsedUrl['scheme'] ) ) ? $_parsedUrl['scheme'] . '://' : '' )
			. ( ( isset( $_parsedUrl['user'] ) ) ? $_parsedUrl['user']
			. ( ( isset( $_parsedUrl['pass'] ) ) ? ':' . $_parsedUrl['pass'] : '' ) . '@' : '' )
			. ( ( isset( $_parsedUrl['host'] ) ) ? $_parsedUrl['host'] : '' )
			. ( ( isset( $_parsedUrl['port'] ) ) ? ':' . $_parsedUrl['port'] : '' )
			. ( ( isset( $_parsedUrl['path'] ) ) ? $_parsedUrl['path'] : '' )
			. ( ( isset( $_parsedUrl['query'] ) ) ? '?' . $_parsedUrl['query'] : '' )
			. ( ( isset( $_parsedUrl['fragment'] ) ) ? '#' . $_parsedUrl['fragment'] : '' );
	}

}

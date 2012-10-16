<?php
/**
 * Server.php
 */
namespace Kisma\Seeds\Services\OAuth;
use \Kisma\Core\Utility\Option;

/**
 * Server
 * An OAuth 2.0 server
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
	 * Creats a server
	 */
	public function __construct( Storage $storage, $config = array() )
	{
		$this->storage = $storage;

		//	Start fresh
		$this->_resetOptions();

		foreach ( $config as $_key => $_value )
		{
			$this->set( $_key, $_value );
		}
	}

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

	// Resource protecting (Section 5).

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
	 * @param $scope
	 * A space-separated string of required scope(s), if you want to check
	 * for scope.
	 *
	 * @throws \Kisma\Seeds\Exceptions\AuthenticationException
	 * @return array
	 */
	public function verifyAccessToken( $token, $scope = null )
	{
		$_tokenType = $this->get( self::ConfigTokenType );
		$_realm = $this->get( self::ConfigRealm );

		if ( empty( $token ) )
		{
			// Access token was not provided
			throw new \Kisma\Seeds\Exceptions\AuthenticationException( self::HttpBadRequest, $_tokenType, $_realm, self::Error_InvalidRequest, 'The request is missing a required parameter, includes an unsupported parameter or parameter value, repeats the same parameter, uses more than one method for including an access token, or is otherwise malformed.', $scope );
		}

		//	Get the stored token data (from the implementing subclass)
		if ( null === ( $_token = $this->_storage->getAccessToken( $token ) ) )
		{
			throw new \Kisma\Seeds\Exceptions\AuthenticationException(
				self::HttpUnauthorized, $_tokenType, $_realm, self::Error_InvalidGrant, 'The access token provided is invalid.', $scope );
		}

		// Check we have a well formed token
		if ( !isset( $token['expires'] ) || !isset( $token['client_id'] ) )
		{
			throw new \Kisma\Seeds\Exceptions\AuthenticationException(
				self::HttpUnauthorized,
				$_tokenType,
				$_realm,
				self::Error_InvalidGrant,
				'Malformed token (missing "expires" or "client_id")',
				$scope
			);
		}

		// Check token expiration (expires is a mandatory paramter)
		if ( isset( $token['expires'] ) && time() > $token['expires'] )
		{
			throw new \Kisma\Seeds\Exceptions\AuthenticationException(
				self::HttpUnauthorized,
				$_tokenType,
				$_realm,
				self::Error_InvalidGrant,
				'The access token provided has expired.',
				$scope
			);
		}

		// Check scope, if provided. If token doesn't have a scope, it's NULL/empty, or it's insufficient, then throw an error
		if ( !empty( $scope ) && ( !isset( $token['scope'] ) || !$token['scope'] || !$this->checkScope( $scope, $token['scope'] ) ) )
		{
			throw new \Kisma\Seeds\Exceptions\AuthenticationException(
				self::HttpForbidden,
				$_tokenType,
				$_realm,
				self::Error_InsufficientScope,
				'The request requires higher privileges than provided by the access token.',
				$scope
			);
		}

		return $token;
	}

	/**
	 * This is a convenience function that can be used to get the token, which can then
	 * be passed to verifyAccessToken(). The constraints specified by the draft are
	 * attempted to be adheared to in this method.
	 *
	 * As per the Bearer spec (draft 8, section 2) - there are three ways for a client
	 * to specify the bearer token, in order of preference: Authorization Header,
	 * POST and GET.
	 *
	 * NB: Resource servers MUST accept tokens via the Authorization scheme
	 * (http://tools.ietf.org/html/draft-ietf-oauth-v2-bearer-08#section-2).
	 *
	 * @todo Should we enforce TLS/SSL in this function?
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
		if ( null === ( $_headers = \Kisma\Core\Utility\FilterInput::server( 'HTTP_AUTHORIZATION' ) ) )
		{
			if ( function_exists( 'apache_request_headers' ) )
			{
				$_requestHeaders = apache_request_headers();

				//	Server-side fix for bug in old Android versions (a nice side-effect of this fix means we don't care about capitalization for Authorization)
				$_requestHeaders = array_combine( array_map( 'ucwords', array_keys( $_requestHeaders ) ), array_values( $_requestHeaders ) );

				$_headers = Option::get( $_requestHeaders, 'Authorization' );
			}
		}

		$_tokenType = $this->get( self::ConfigTokenType );
		$_realm = $this->get( self::ConfigRealm );

		// Check that exactly one method was used
		$_methodsUsed = !empty( $_headers ) + isset( $_GET[self::TokenParameterName] ) + isset( $_POST[self::TokenParameterName] );

		if ( $_methodsUsed > 1 )
		{
			throw new \Kisma\Seeds\Exceptions\AuthenticationException( self::HttpBadRequest, $_tokenType, $_realm, self::Error_InvalidRequest, 'Only one method may be used to authenticate at a time (Auth header, GET or POST).' );
		}
		elseif ( $_methodsUsed == 0 )
		{
			throw new \Kisma\Seeds\Exceptions\AuthenticationException( self::HttpBadRequest, $_tokenType, $_realm, self::Error_InvalidRequest, 'The access token was not found.' );
		}

		// HEADER: Get the access token from the header
		if ( !empty( $_headers ) )
		{
			if ( !preg_match( '/' . self::TokenBearerHeaderName . '\s(\S+)/', $_headers, $matches ) )
			{
				throw new \Kisma\Seeds\Exceptions\AuthenticationException( self::HttpBadRequest, $_tokenType, $_realm, self::Error_InvalidRequest, 'Malformed auth header' );
			}

			return $matches[1];
		}

		//	POST: Get the token from POST data
		if ( null !== ( $_token = \Kisma\Core\Utility\FilterInput::post( self::TokenParameterName ) ) )
		{
			if ( 'POST' != $_SERVER['REQUEST_METHOD'] )
			{
				throw new \Kisma\Seeds\Exceptions\AuthenticationException( self::HttpBadRequest, $_tokenType, $_realm, self::Error_InvalidRequest, 'When putting the token in the body, the method must be POST.' );
			}

			// IETF specifies content-type. NB: Not all webservers populate this _SERVER variable
			if ( isset( $_SERVER['CONTENT_TYPE'] ) && $_SERVER['CONTENT_TYPE'] != 'application/x-www-form-urlencoded' )
			{
				throw new \Kisma\Seeds\Exceptions\AuthenticationException( self::HttpBadRequest, $_tokenType, $_realm, self::Error_InvalidRequest, 'The content type for POST requests must be "application/x-www-form-urlencoded"' );
			}

			return $_token;
		}

		// GET method
		return $_GET[self::TokenParameterName];
	}

	/** @codeCoverageIgnoreEnd */

	/**
	 * Check if everything in required scope is contained in available scope.
	 *
	 * @param $required_scope
	 * Required scope to be check with.
	 *
	 * @return
	 *      TRUE if everything in required scope is contained in available scope,
	 *      and False if it isn't.
	 *
	 * @see http://tools.ietf.org/html/draft-ietf-oauth-v2-20#section-7
	 *
	 * @ingroup oauth2_section_7
	 */
	private function checkScope( $required_scope, $available_scope )
	{
		// The required scope should match or be a subset of the available scope
		if ( !is_array( $required_scope ) )
		{
			$required_scope = explode( ' ', trim( $required_scope ) );
		}

		if ( !is_array( $available_scope ) )
		{
			$available_scope = explode( ' ', trim( $available_scope ) );
		}

		return ( count( array_diff( $required_scope, $available_scope ) ) == 0 );
	}

	// Access token granting (Section 4).

	/**
	 * Grant or deny a requested access token.
	 * This would be called from the "/token" endpoint as defined in the spec.
	 * Obviously, you can call your endpoint whatever you want.
	 *
	 * @param $inputData - The draft specifies that the parameters should be
	 *                   retrieved from POST, but you can override to whatever method you like.
	 *
	 * @throws OAuth2ServerException
	 *
	 * @see http://tools.ietf.org/html/draft-ietf-oauth-v2-20#section-4
	 * @see http://tools.ietf.org/html/draft-ietf-oauth-v2-21#section-10.6
	 * @see http://tools.ietf.org/html/draft-ietf-oauth-v2-21#section-4.1.3
	 *
	 * @ingroup oauth2_section_4
	 */
	public function grantAccessToken( array $inputData = null, array $authHeaders = null )
	{
		$filters = array(
			"grant_type"    => array(
				"filter"  => FILTER_VALIDATE_REGEXP,
				"options" => array( "regexp" => self::GRANT_TYPE_REGEXP ),
				"flags"   => FILTER_REQUIRE_SCALAR
			),
			"scope"         => array( "flags" => FILTER_REQUIRE_SCALAR ),
			"code"          => array( "flags" => FILTER_REQUIRE_SCALAR ),
			"redirect_uri"  => array( "filter" => FILTER_SANITIZE_URL ),
			"username"      => array( "flags" => FILTER_REQUIRE_SCALAR ),
			"password"      => array( "flags" => FILTER_REQUIRE_SCALAR ),
			"refresh_token" => array( "flags" => FILTER_REQUIRE_SCALAR ),
		);

		// Input data by default can be either POST or GET
		if ( !isset( $inputData ) )
		{
			$inputData = ( $_SERVER['REQUEST_METHOD'] == 'POST' ) ? $_POST : $_GET;
		}

		// Basic authorization header
		$authHeaders = isset( $authHeaders ) ? $authHeaders : $this->getAuthorizationHeader();

		// Filter input data
		$input = filter_var_array( $inputData, $filters );

		// Grant Type must be specified.
		if ( !$input["grant_type"] )
		{
			throw new OAuth2ServerException( self::HttpBadRequest, self::Error_InvalidRequest, 'Invalid grant_type parameter or parameter missing' );
		}

		// Authorize the client
		$client = $this->getClientCredentials( $inputData, $authHeaders );

		if ( $this->storage->checkClientCredentials( $client[0], $client[1] ) === false )
		{
			throw new OAuth2ServerException( self::HttpBadRequest, self::Error_InvalidClient, 'The client credentials are invalid' );
		}

		if ( !$this->storage->checkRestrictedGrantType( $client[0], $input["grant_type"] ) )
		{
			throw new OAuth2ServerException( self::HttpBadRequest, self::ERROR_UNAUTHORIZED_CLIENT, 'The grant type is unauthorized for this client_id' );
		}

		// Do the granting
		switch ( $input["grant_type"] )
		{
			case self::GRANT_TYPE_AUTH_CODE:
				if ( !( $this->storage instanceof IOAuth2GrantCode ) )
				{
					throw new OAuth2ServerException( self::HttpBadRequest, self::ERROR_UNSUPPORTED_GRANT_TYPE );
				}

				if ( !$input["code"] )
				{
					throw new OAuth2ServerException( self::HttpBadRequest, self::Error_InvalidRequest, 'Missing parameter. "code" is required' );
				}

				if ( $this->get( self::Config_ENFORCE_INPUT_REDIRECT ) && !$input["redirect_uri"] )
				{
					throw new OAuth2ServerException( self::HttpBadRequest, self::Error_InvalidRequest, "The redirect URI parameter is required." );
				}

				$stored = $this->storage->getAuthCode( $input["code"] );

				// Check the code exists
				if ( $stored === null || $client[0] != $stored["client_id"] )
				{
					throw new OAuth2ServerException( self::HttpBadRequest, self::ERROR_INVALID_GRANT, "Refresh token doesn't exist or is invalid for the client" );
				}

				// Validate the redirect URI. If a redirect URI has been provided on input, it must be validated
				if ( $input["redirect_uri"] && !$this->validateRedirectUri( $input["redirect_uri"], $stored["redirect_uri"] ) )
				{
					throw new OAuth2ServerException( self::HttpBadRequest, self::ERROR_REDIRECT_URI_MISMATCH, "The redirect URI is missing or do not match" );
				}

				if ( $stored["expires"] < time() )
				{
					throw new OAuth2ServerException( self::HttpBadRequest, self::ERROR_INVALID_GRANT, "The authorization code has expired" );
				}
				break;

			case self::GRANT_TYPE_USER_CREDENTIALS:
				if ( !( $this->storage instanceof IOAuth2GrantUser ) )
				{
					throw new OAuth2ServerException( self::HttpBadRequest, self::ERROR_UNSUPPORTED_GRANT_TYPE );
				}

				if ( !$input["username"] || !$input["password"] )
				{
					throw new OAuth2ServerException( self::HttpBadRequest, self::Error_InvalidRequest, 'Missing parameters. "username" and "password" required' );
				}

				$stored = $this->storage->checkUserCredentials( $client[0], $input["username"], $input["password"] );

				if ( $stored === false )
				{
					throw new OAuth2ServerException( self::HttpBadRequest, self::ERROR_INVALID_GRANT );
				}
				break;

			case self::GRANT_TYPE_CLIENT_CREDENTIALS:
				if ( !( $this->storage instanceof IOAuth2GrantClient ) )
				{
					throw new OAuth2ServerException( self::HttpBadRequest, self::ERROR_UNSUPPORTED_GRANT_TYPE );
				}

				if ( empty( $client[1] ) )
				{
					throw new OAuth2ServerException( self::HttpBadRequest, self::Error_InvalidClient, 'The client_secret is mandatory for the "client_credentials" grant type' );
				}
				// NB: We don't need to check for $stored==false, because it was checked above already
				$stored = $this->storage->checkClientCredentialsGrant( $client[0], $client[1] );
				break;

			case self::GRANT_TYPE_REFRESH_TOKEN:
				if ( !( $this->storage instanceof IOAuth2RefreshTokens ) )
				{
					throw new OAuth2ServerException( self::HttpBadRequest, self::ERROR_UNSUPPORTED_GRANT_TYPE );
				}

				if ( !$input["refresh_token"] )
				{
					throw new OAuth2ServerException( self::HttpBadRequest, self::Error_InvalidRequest, 'No "refresh_token" parameter found' );
				}

				$stored = $this->storage->getRefreshToken( $input["refresh_token"] );

				if ( $stored === null || $client[0] != $stored["client_id"] )
				{
					throw new OAuth2ServerException( self::HttpBadRequest, self::ERROR_INVALID_GRANT, 'Invalid refresh token' );
				}

				if ( $stored["expires"] < time() )
				{
					throw new OAuth2ServerException( self::HttpBadRequest, self::ERROR_INVALID_GRANT, 'Refresh token has expired' );
				}

				// store the refresh token locally so we can delete it when a new refresh token is generated
				$this->_priorRefreshToken = $stored["refresh_token"];
				break;

			case self::GRANT_TYPE_IMPLICIT:
				/* TODO: NOT YET IMPLEMENTED */
				throw new OAuth2ServerException( '501 Not Implemented', 'This OAuth2 library is not yet complete. This functionality is not implemented yet.' );
				if ( !( $this->storage instanceof IOAuth2GrantImplicit ) )
				{
					throw new OAuth2ServerException( self::HttpBadRequest, self::ERROR_UNSUPPORTED_GRANT_TYPE );
				}

				break;

			// Extended grant types:
			case filter_var( $input["grant_type"], FILTER_VALIDATE_URL ):
				if ( !( $this->storage instanceof IOAuth2GrantExtension ) )
				{
					throw new OAuth2ServerException( self::HttpBadRequest, self::ERROR_UNSUPPORTED_GRANT_TYPE );
				}
				$uri = filter_var( $input["grant_type"], FILTER_VALIDATE_URL );
				$stored = $this->storage->checkGrantExtension( $uri, $inputData, $authHeaders );

				if ( $stored === false )
				{
					throw new OAuth2ServerException( self::HttpBadRequest, self::ERROR_INVALID_GRANT );
				}
				break;

			default :
				throw new OAuth2ServerException( self::HttpBadRequest, self::Error_InvalidRequest, 'Invalid grant_type parameter or parameter missing' );
		}

		if ( !isset( $stored["scope"] ) )
		{
			$stored["scope"] = null;
		}

		// Check scope, if provided
		if ( $input["scope"] && ( !is_array( $stored ) || !isset( $stored["scope"] ) || !$this->checkScope( $input["scope"], $stored["scope"] ) ) )
		{
			throw new OAuth2ServerException( self::HttpBadRequest, self::ERROR_INVALID_SCOPE, 'An unsupported scope was requested.' );
		}

		$user_id = isset( $stored['user_id'] ) ? $stored['user_id'] : null;
		$token = $this->createAccessToken( $client[0], $user_id, $stored['scope'] );

		// Send response
		$this->sendJsonHeaders();
		echo json_encode( $token );
	}

	/**
	 * Internal function used to get the client credentials from HTTP basic
	 * auth or POST data.
	 *
	 * According to the spec (draft 20), the client_id can be provided in
	 * the Basic Authorization header (recommended) or via GET/POST.
	 *
	 * @return
	 *      A list containing the client identifier and password, for example
	 * @code
	 *      return array(
	 *      CLIENT_ID,
	 *      CLIENT_SECRET
	 * );
	 * @endcode
	 *
	 * @see http://tools.ietf.org/html/draft-ietf-oauth-v2-20#section-2.4.1
	 *
	 * @ingroup oauth2_section_2
	 */
	protected function getClientCredentials( array $inputData, array $authHeaders )
	{

		// Basic Authentication is used
		if ( !empty( $authHeaders['PHP_AUTH_USER'] ) )
		{
			return array( $authHeaders['PHP_AUTH_USER'], $authHeaders['PHP_AUTH_PW'] );
		}
		elseif ( empty( $inputData['client_id'] ) )
		{ // No credentials were specified
			throw new OAuth2ServerException( self::HttpBadRequest, self::Error_InvalidClient, 'Client id was not found in the headers or body' );
		}
		else
		{
			// This method is not recommended, but is supported by specification
			return array( $inputData['client_id'], $inputData['client_secret'] );
		}
	}

	// End-user/client Authorization (Section 2 of IETF Draft).

	/**
	 * Pull the authorization request data out of the HTTP request.
	 * - The redirect_uri is OPTIONAL as per draft 20. But your implementation can enforce it
	 * by setting Config_ENFORCE_INPUT_REDIRECT to true.
	 * - The state is OPTIONAL but recommended to enforce CSRF. Draft 21 states, however, that
	 * CSRF protection is MANDATORY. You can enforce this by setting the Config_ENFORCE_STATE to true.
	 *
	 * @param $inputData - The draft specifies that the parameters should be
	 *                   retrieved from GET, but you can override to whatever method you like.
	 *
	 * @return
	 *      The authorization parameters so the authorization server can prompt
	 *      the user for approval if valid.
	 *
	 * @throws OAuth2ServerException
	 * @see http://tools.ietf.org/html/draft-ietf-oauth-v2-20#section-4.1.1
	 * @see http://tools.ietf.org/html/draft-ietf-oauth-v2-21#section-10.12
	 *
	 * @ingroup oauth2_section_3
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

		if ( null === $inputData )
		{
			$inputData = $_GET;
		}

		$_input = filter_var_array( $inputData, $_filters );
		$_state = Option::get( $_input, 'state' );
		$_scope = Option::get( $_input, 'scope' );

		//	Make sure a valid client id was supplied (we can not redirect because we were unable to verify the URI)
		if ( null === ( $_clientId = Option::get( $_input, 'client_id' ) ) )
		{
			throw new \Kisma\Seeds\Exceptions\ServerException( self::HttpBadRequest, self::Error_InvalidClient, 'No client id supplied' );
		}

		//	Get client details
		if ( false === ( $_clientDetails = $this->_storage->getClientDetails( $_clientId ) ) )
		{
			throw new \Kisma\Seeds\Exceptions\ServerException( self::HttpBadRequest, self::Error_InvalidClient, 'Client id does not exist' );
		}

		$_clientRedirectUri = Option::get( $_clientDetails, 'redirect_uri' );

		// Make sure a valid redirect_uri was supplied. If specified, it must match the stored URI.
		if ( null === ( $_redirectUri = Option::get( $_input, 'redirect_uri', $_clientRedirectUri ) ) )
		{
			throw new \Kisma\Seeds\Exceptions\ServerException( self::HttpBadRequest, self::Error_RedirectUriMismatch, 'No redirect URL was supplied or stored.' );
		}

		if ( $this->get( self::ConfigEnforceInputRedirect ) && null === $_redirectUri )
		{
			throw new ServerException( self::HttpBadRequest, self::Error_RedirectUriMismatch, 'The redirect URI is mandatory and was not supplied.' );
		}

		//	Only need to validate if redirect_uri provided on input and stored.
		if ( null !== $_clientRedirectUri && null !== $_redirectUri && !$this->validateRedirectUri( $_clientRedirectUri, $_clientRedirectUri ) )
		{
			throw new \Kisma\Seeds\Exceptions\ServerException( self::HttpBadRequest, self::Error_RedirectUriMismatch, 'The redirect URI provided is missing or does not match' );
		}

		// Select the redirect URI
		$_input['redirect_uri'] = $_redirectUri ? : $_clientRedirectUri;

		// type and client_id are required
		if ( null === ( $_responseType = Option::get( $input, 'response_type' ) ) )
		{
			throw new RedirectException( $_redirectUri, self::Error_InvalidRequest, 'Invalid or missing response type.', $_state );
		}

		if ( self::ResponseTypeAuthCode != $_responseType && self::ResponseTypeAccessToken != $_responseType )
		{
			throw new RedirectException( $_redirectUri, self::Error_UnsupportedResponseType, null, $_state );
		}

		//	Validate that the requested scope is supported
		if ( empty( $_scope ) || !$this->checkScope( $_scope, $this->get( self::ConfigSupportedScopes ) ) )
		{
			throw new RedirectException( $_redirectUri, self::Error_InvalidScope, 'An unsupported scope was requested.', $_state );
		}

		//	Validate state parameter exists (if configured to enforce this)
		if ( $this->get( self::ConfigEnforceState ) && empty( $_state ) )
		{
			throw new RedirectException( $_redirectUri, self::Error_InvalidRequest, 'The state parameter is required.' );
		}

		//	Return retrieved client details together with input
		return ( $_input + $_clientDetails );
	}

	/**
	 * Redirect the user appropriately after approval.
	 *
	 * After the user has approved or denied the access request the
	 * authorization server should call this function to redirect the user
	 * appropriately.
	 *
	 * @param $is_authorized
	 * TRUE or FALSE depending on whether the user authorized the access.
	 * @param $user_id
	 * Identifier of user who authorized the client
	 * @param $params
	 * An associative array as below:
	 * - response_type: The requested response: an access token, an
	 * authorization code, or both.
	 * - client_id: The client identifier as described in Section 2.
	 * - redirect_uri: An absolute URI to which the authorization server
	 * will redirect the user-agent to when the end-user authorization
	 * step is completed.
	 * - scope: (optional) The scope of the access request expressed as a
	 * list of space-delimited strings.
	 * - state: (optional) An opaque value used by the client to maintain
	 * state between the request and callback.
	 *
	 * @see http://tools.ietf.org/html/draft-ietf-oauth-v2-20#section-4
	 *
	 * @ingroup oauth2_section_4
	 */
	public function finishClientAuthorization( $is_authorized, $user_id = null, $params = array() )
	{
		list( $redirect_uri, $result ) = $this->getAuthResult( $is_authorized, $user_id, $params );
		$this->doRedirectUriCallback( $redirect_uri, $result );
	}

	/**
	 * @param bool   $is_authorized
	 * @param string $user_id
	 * @param array  $params
	 *
	 * @throws OAuth2RedirectException
	 * @return array
	 */
	public function getAuthResult( $is_authorized, $user_id = null, $params = array() )
	{
		$_result = array();
		$params = $this->getAuthorizeParams( $params );
		$params += array( 'scope' => null, 'state' => null );
		extract( $params );

		/**
		 * @var $state         string
		 * @var $redirect_uri  string
		 * @var $response_type string
		 * @var $response_type string
		 * @var $response_type string
		 */

		if ( null !== $state )
		{
			$result['query']['state'] = $state;
		}

		if ( false === $is_authorized )
		{
			throw new RedirectException( $redirect_uri, self::Error_UserDenied, 'User not allowed access to this resource.', $state );
		}
		else
		{
			if ( self::ResponseTypeAuthCode == $response_type )
			{
				$result['query']['code'] = $this->createAuthCode( $client_id, $user_id, $redirect_uri, $scope );
			}
			elseif ( $response_type == self::RESPONSE_TYPE_ACCESS_TOKEN )
			{
				$result["fragment"] = $this->createAccessToken( $client_id, $user_id, $scope );
			}
		}

		return array( $redirect_uri, $result );
	}

	// Other/utility functions.

	/**
	 * Redirect the user agent.
	 *
	 * Handle both redirect for success or error response.
	 *
	 * @param $redirect_uri
	 * An absolute URI to which the authorization server will redirect
	 * the user-agent to when the end-user authorization step is completed.
	 * @param $params
	 * Parameters to be pass though buildUri().
	 *
	 * @ingroup oauth2_section_4
	 */
	private function doRedirectUriCallback( $redirect_uri, $params )
	{
		header( "HTTP/1.1 " . self::HTTP_FOUND );
		header( "Location: " . $this->buildUri( $redirect_uri, $params ) );
		exit();
	}

	/**
	 * Build the absolute URI based on supplied URI and parameters.
	 *
	 * @param $uri
	 * An absolute URI.
	 * @param $params
	 * Parameters to be append as GET.
	 *
	 * @return
	 * An absolute URI with supplied parameters.
	 *
	 * @ingroup oauth2_section_4
	 */
	private function buildUri( $uri, $params )
	{
		$parse_url = parse_url( $uri );

		// Add our params to the parsed uri
		foreach ( $params as $k => $v )
		{
			if ( isset( $parse_url[$k] ) )
			{
				$parse_url[$k] .= "&" . http_build_query( $v );
			}
			else
			{
				$parse_url[$k] = http_build_query( $v );
			}
		}

		// Put humpty dumpty back together
		return
			( ( isset( $parse_url["scheme"] ) ) ? $parse_url["scheme"] . "://" : "" )
			. ( ( isset( $parse_url["user"] ) ) ? $parse_url["user"]
			. ( ( isset( $parse_url["pass"] ) ) ? ":" . $parse_url["pass"] : "" ) . "@" : "" )
			. ( ( isset( $parse_url["host"] ) ) ? $parse_url["host"] : "" )
			. ( ( isset( $parse_url["port"] ) ) ? ":" . $parse_url["port"] : "" )
			. ( ( isset( $parse_url["path"] ) ) ? $parse_url["path"] : "" )
			. ( ( isset( $parse_url["query"] ) ) ? "?" . $parse_url["query"] : "" )
			. ( ( isset( $parse_url["fragment"] ) ) ? "#" . $parse_url["fragment"] : "" );
	}

	/**
	 * Handle the creation of access token, also issue refresh token if support.
	 *
	 * This belongs in a separate factory, but to keep it simple, I'm just
	 * keeping it here.
	 *
	 * @param $client_id
	 * Client identifier related to the access token.
	 * @param $scope
	 * (optional) Scopes to be stored in space-separated string.
	 *
	 * @see http://tools.ietf.org/html/draft-ietf-oauth-v2-20#section-5
	 * @ingroup oauth2_section_5
	 */
	protected function createAccessToken( $client_id, $user_id, $scope = null )
	{

		$token = array(
			"access_token" => $this->genAccessToken(),
			"expires_in"   => $this->get( self::ConfigAccessLifetime ),
			"token_type"   => $this->get( self::ConfigTokenType ),
			"scope"        => $scope
		);

		$this->storage->setAccessToken( $token["access_token"],
			$client_id,
			$user_id,
			time() + $this->get( self::ConfigAccessLifetime ),
			$scope );

		// Issue a refresh token also, if we support them
		if ( $this->storage instanceof IOAuth2RefreshTokens )
		{
			$token["refresh_token"] = $this->genAccessToken();
			$this->storage->setRefreshToken( $token["refresh_token"],
				$client_id,
				$user_id,
				time() + $this->get( self::Config_REFRESH_LIFETIME ),
				$scope );

			// If we've granted a new refresh token, expire the old one
			if ( $this->_priorRefreshToken )
			{
				$this->storage->unsetRefreshToken( $this->_priorRefreshToken );
				unset( $this->_priorRefreshToken );
			}
		}

		return $token;
	}

	/**
	 * Handle the creation of auth code.
	 *
	 * This belongs in a separate factory, but to keep it simple, I'm just
	 * keeping it here.
	 *
	 * @param $client_id
	 * Client identifier related to the access token.
	 * @param $redirect_uri
	 * An absolute URI to which the authorization server will redirect the
	 * user-agent to when the end-user authorization step is completed.
	 * @param $scope
	 * (optional) Scopes to be stored in space-separated string.
	 *
	 * @ingroup oauth2_section_4
	 */
	private function _createAuthCode( $client_id, $user_id, $redirect_uri, $scope = null )
	{
		$code = $this->_generateAuthCode();

		$this->_storage->setAuthCode( $code, $client_id, $user_id, $redirect_uri, time() + $this->get( self::ConfigAuthLifetime ), $scope );

		return $code;
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
			'PHP_AUTH_USER' => \Kisma\Core\Utility\FilterInput::server( 'PHP_AUTH_USER' ),
			'PHP_AUTH_PW'   => \Kisma\Core\Utility\FilterInput::server( 'PHP_AUTH_PW' ),
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
	 * Send out HTTP headers for JSON.
	 */
	private function _sendJsonHeaders()
	{
		if ( 'cli' === php_sapi_name() || headers_sent() )
		{
			return;
		}

		header( 'Content-Type: application/json' );
		header( 'Cache-Control: no-store' );
	}

}

<?php
/**
 * Server.php
 */
namespace Kisma\Seeds\Interfaces\OAuth;
/**
 * Server
 * Server-side OAuth constants and interface
 */
interface Server
{
	//*************************************************************************
	//* Constants
	//*************************************************************************

	//.........................................................................
	//. Defaults
	//.........................................................................

	/**
	 * @var int
	 */
	const DefaultAccessTokenLifetime = 3600;
	/**
	 * @var int
	 */
	const DefaultRefreshTokenLifetime = 1209600;
	/**
	 * @var int
	 */
	const DefaultAuthCodeLifetime = 30;
	/**
	 * @var string
	 */
	const DefaultRealm = 'service';

	//*************************************************************************
	//* Configuration Options
	//*************************************************************************

	/**
	 * @var string The lifetime of access token in seconds.
	 */
	const ConfigAccessLifetime = 'access_token_lifetime';
	/**
	 * @var string The lifetime of refresh token in seconds.
	 */
	const ConfigRefreshLifetime = 'refresh_token_lifetime';
	/**
	 * @var string The lifetime of auth code in seconds.
	 */
	const ConfigAuthLifetime = 'auth_code_lifetime';
	/**
	 * @var string Array of scopes you want to support
	 */
	const ConfigSupportedScopes = 'supported_scopes';
	/**
	 * @var string Token type to respond with. Currently only "Bearer" supported.
	 */
	const ConfigTokenType = 'token_type';
	/**
	 * @var string
	 */
	const ConfigRealm = 'realm';
	/**
	 * @var string Set to true to enforce redirect_uri on input for both authorize and token steps.
	 */
	const ConfigEnforceInputRedirect = 'enforce_redirect';
	/**
	 * @var string Set to true to enforce state to be passed in authorization {@see http://tools.ietf.org/html/draft-ietf-oauth-v2-21#section-10.12}
	 */
	const ConfigEnforceState = 'enforce_state';

	//.........................................................................
	//. Miscellaneous
	//.........................................................................

	/**
	 * @var string Regex to filter out the client identifier (described in Section 2 of IETF draft).
	 */
	const RegExpFilter_ClientId = '/^[a-z0-9-_]{3,32}$/i';
	/**
	 * @var string Regex to filter out the grant type.
	 */
	const RegExpFilter_GrantType = '#^(authorization_code|token|password|client_credentials|refresh_token|http://.*)$#';
	/**
	 * @var string Used to define the name of the OAuth access token parameter (POST & GET).
	 */
	const TokenParameterName = 'access_token';
	/**
	 * @var string When using the bearer token type, there is a specific Authorization header required: "Bearer"
	 */
	const TokenBearerHeaderName = 'Bearer';

	//.........................................................................
	//. Responses
	//.........................................................................

	/**
	 * @var string possible authentication response types.
	 */
	const ResponseTypeAuthCode = 'code';
	/**
	 * @var string possible authentication response types.
	 */
	const ResponseTypeAccessToken = 'token';

	//.........................................................................
	//. Grant Types
	//.........................................................................

	/**
	 * @var string
	 */
	const GrantTypeAuthCode = 'authorization_code';
	/**
	 * @var string
	 */
	const GrantTypeImplicit = 'token';
	/**
	 * @var string
	 */
	const GrantTypeUserCredentials = 'password';
	/**
	 * @var string
	 */
	const GrantTypeClientCredentials = 'client_credentials';
	/**
	 * @var string
	 */
	const GrantTypeRefreshToken = 'refresh_token';
	/**
	 * @var string
	 */
	const GrantTypeExtensions = 'extensions';

	//.........................................................................
	//. Token Types
	//.........................................................................

	/**
	 * @var string
	 */
	const TokenTypeBearer = 'bearer';
	/**
	 * @var string
	 */
	const TokenTypeMac = 'mac';

	//.........................................................................
	//. HTTP Status Codes
	//.........................................................................

	/**
	 * @var string
	 */
	const HttpFound = '302';
	/**
	 * @var string
	 */
	const HttpBadRequest = '400';
	/**
	 * @var string
	 */
	const HttpUnauthorized = '401';
	/**
	 * @var string
	 */
	const HttpForbidden = '403';
	/**
	 * @var string
	 */
	const HttpUnavailable = '503';

	//.........................................................................
	//. Errors
	//.........................................................................

	/**
	 * @var string The request is missing a required parameter, includes an unsupported parameter or parameter value, or is otherwise malformed.
	 */
	const Error_InvalidRequest = 'invalid_request';
	/**
	 * @var string The client identifier provided is invalid.
	 */
	const Error_InvalidClient = 'invalid_client';
	/**
	 * @var string The client is not authorized to use the requested response type.
	 */
	const Error_UnauthorizedClient = 'unauthorized_client';
	/**
	 * @var string The redirection URI provided does not match a pre-registered value.
	 */
	const Error_RedirectUriMismatch = 'redirect_uri_mismatch';
	/**
	 * @var string The end-user or authorization server denied the request.
	 */
	const Error_UserDenied = 'access_denied';
	/**
	 * @var string The requested response type is not supported by the authorization server.
	 */
	const Error_UnsupportedResponseType = 'unsupported_response_type';
	/**
	 * @var string The requested scope is invalid, unknown, or malformed.
	 */
	const Error_InvalidScope = 'invalid_scope';
	/**
	 * @var string The provided authorization grant is invalid, expired, revoked, does not match the redirection URI used in the authorization request, or was issued to another client.
	 */
	const Error_InvalidGrant = 'invalid_grant';
	/**
	 * @var string The authorization grant is not supported by the authorization server.
	 */
	const Error_UnsupportedGrantType = 'unsupported_grant_type';
	/**
	 * @var string The request requires higher privileges than provided by the access token.
	 */
	const Error_InsufficientScope = 'invalid_scope';

	//.........................................................................
	//. Error Messages
	//.........................................................................

	/**
	 * @var string
	 */
	const ErrorMessage_InvalidRequest = 'The request is missing a required parameter; includes an unsupported parameter or parameter value; repeats the same parameter; uses more than one method for including an access token; or is otherwise malformed.';
	/**
	 * @var string
	 */
	const ErrorMessage_InvalidAccessToken = 'The access token provided is invalid.';
	/**
	 * @var string
	 */
	const ErrorMessage_MalformedToken = 'Malformed token (missing "expires" or "client_id")';
	/**
	 * @var string
	 */
	const ErrorMessage_InvalidToken = 'The access token was not found.';
	/**
	 * @var string
	 */
	const ErrorMessage_InsufficientScope = 'The request requires different privileges than provided by the access token.';
	/**
	 * @var string
	 */
	const ErrorMessage_ExpiredToken = 'The access token provided has expired.';
	/**
	 * @var string
	 */
	const ErrorMessage_InvalidMethod = 'Only one method may be used to authenticate at a time (Auth header, GET or POST).';
}

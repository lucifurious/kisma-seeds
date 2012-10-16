<?php
/**
 * Storage.php
 */
namespace Kisma\Seeds\Interfaces\OAuth;
/**
 * Storage
 * Implemented by OAuth servers for storing tokens
 */
interface Storage
{
	/**
	 * Make sure that the client credentials is valid.
	 *
	 * @param $client_id     Client identifier to be check with.
	 * @param $client_secret (optional) If a secret is required, check that they've given the right one.
	 *
	 * @return TRUE if the client credentials are valid, and MUST return FALSE if it isn't.
	 *
	 * @see http://tools.ietf.org/html/draft-ietf-oauth-v2-20#section-3.1
	 */
	public function checkClientCredentials( $client_id, $client_secret = null );

	/**
	 * Get client details corresponding client_id.
	 *
	 * OAuth says we should store request URIs for each registered client.
	 * Implement this function to grab the stored URI for a given client id.
	 *
	 * @param $client_id Client identifier to be check with.
	 *
	 * @return array Client details. Only mandatory item is the "registered redirect URI", and MUST return FALSE if the given client does not exist or is invalid.
	 */
	public function getClientDetails( $client_id );

	/**
	 * Look up the supplied oauth_token from storage.
	 * We need to retrieve access token data as we create and verify tokens.
	 *
	 * @param $oauth_token token to check
	 *
	 * @return An associative array as below, and return NULL if the supplied oauth_token is invalid:
	 * - client_id: Stored client identifier.
	 * - expires: Stored expiration in unix timestamp.
	 * - scope: (optional) Stored scope values in space-separated string.
	 */
	public function getAccessToken( $oauth_token );

	/**
	 * Store the supplied access token values to storage.
	 * We need to store access token data as we create and verify tokens.
	 *
	 * @param array  $oauth_token token to stored.
	 * @param string $client_id   Client identifier to be stored.
	 * @param string $user_id     User identifier to be stored.
	 * @param int    $expires     Expiration to be stored.
	 * @param string $scope       (optional) Scopes to be stored in space-separated string.
	 */
	public function setAccessToken( $oauth_token, $client_id, $user_id, $expires, $scope = null );

	/**
	 * Check restricted grant types of corresponding client identifier.
	 *
	 * If you want to restrict clients to certain grant types, override this
	 * function.
	 *
	 * @param $client_id  Client identifier to be check with.
	 * @param $grant_type Grant type to be check with, would be one of the values contained in OAuth2::GRANT_TYPE_REGEXP.
	 *
	 * @return TRUE if the grant type is supported by this client identifier, and FALSE if it isn't.
	 */
	public function checkRestrictedGrantType( $client_id, $grant_type );
}
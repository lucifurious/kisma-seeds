<?php
/**
 * GrantCode.php
 *
 * @copyright Copyright (c) 2012 DreamFactory Software, Inc.
 * @link      http://www.dreamfactory.com DreamFactory Software, Inc.
 * @author    Jerry Ablan <jerryablan@dreamfactory.com>
 *
 * @filesource
 */
namespace Kisma\Seeds\Interfaces\OAuth;
/**
 * GrantCode
 * Storage engines that support the "Authorization Code" grant type should implement this interface
 */
interface GrantCode extends Storage
{
	//*************************************************************************
	//* Methods
	//*************************************************************************

	/**
	 * @var string The Authorization Code grant type supports a response type of "code".
	 */
	const ResponseTypeCode = Server::ResponseTypeAuthCode;

	/**
	 * Fetch authorization code data (probably the most common grant type).
	 *
	 * Retrieve the stored data for the given authorization code.
	 *
	 * Required for OAuth2::GRANT_TYPE_AUTH_CODE.
	 *
	 * @param $code Authorization code to be check with.
	 *
	 * @return
	 *      An associative array as below, and NULL if the code is invalid:
	 * - client_id: Stored client identifier.
	 * - redirect_uri: Stored redirect URI.
	 * - expires: Stored expiration in unix timestamp.
	 * - scope: (optional) Stored scope values in space-separated string.
	 */
	public function getAuthCode( $code );

	/**
	 * Take the provided authorization code values and store them somewhere.
	 *
	 * This function should be the storage counterpart to getAuthCode().
	 *
	 * If storage fails for some reason, we're not currently checking for
	 * any sort of success/failure, so you should bail out of the script
	 * and provide a descriptive fail message.
	 *
	 * Required for OAuth2::GRANT_TYPE_AUTH_CODE.
	 *
	 * @param $code
	 * Authorization code to be stored.
	 * @param $client_id
	 * Client identifier to be stored.
	 * @param $user_id
	 * User identifier to be stored.
	 * @param $redirect_uri
	 * Redirect URI to be stored.
	 * @param $expires
	 * Expiration to be stored.
	 * @param $scope
	 * (optional) Scopes to be stored in space-separated string.
	 *
	 * @ingroup oauth2_section_4
	 */
	public function setAuthCode( $code, $client_id, $user_id, $redirect_uri, $expires, $scope = null );

}
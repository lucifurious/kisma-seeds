<?php
/**
 * GrantImplicit.php
 */
namespace Kisma\Seeds\Interfaces\OAuth;
/**
 * GrantImplicit
 * Storage engines that support the "Implicit" grant type should implement this interface
 */
interface GrantImplicit extends Storage
{
	//*************************************************************************
	//* Methods
	//*************************************************************************

	/**
	 * The Implicit grant type supports a response type of "token".
	 *
	 * @var string
	 * @see http://tools.ietf.org/html/draft-ietf-oauth-v2-20#section-1.4.2
	 * @see http://tools.ietf.org/html/draft-ietf-oauth-v2-20#section-4.2
	 */
	const ResponseTypeToken = Server::ResponseTypeAccessToken;
}
<?php
/**
 * RedirectException.php
 */
namespace Kisma\Seeds\Exceptions\OAuth;
/**
 * RedirectException
 * A default redirect exception
 */
class RedirectException extends ServerException
{
	//*************************************************************************
	//* Private Members
	//*************************************************************************

	/**
	 * @var string
	 */
	protected $_redirectUri;

	//*************************************************************************
	//* Public Methods
	//*************************************************************************

	/**
	 * @param string $redirectUri
	 * @param string $error
	 * @param string $errorDescription
	 * @param string $state
	 */
	public function __construct( $redirectUri, $error, $errorDescription = null, $state = null )
	{
		parent::__construct( \Kisma\Seeds\Interfaces\OAuth\Server::HttpFound, $error, $errorDescription );

		$this->_redirectUri = $redirectUri;

		if ( null !== $state )
		{
			$this->_error['state'] = $state;
		}
	}

	/**
	 * @param string $redirectUri
	 *
	 * @return RedirectException
	 */
	public function setRedirectUri( $redirectUri )
	{
		$this->_redirectUri = $redirectUri;

		return $this;
	}

	/**
	 * @return string
	 */
	public function getRedirectUri()
	{
		return $this->_redirectUri;
	}

	//*************************************************************************
	//* Private Methods
	//*************************************************************************

	/**
	 * Redirect the user
	 */
	protected function _sendHeaders()
	{
		$_parameters = array( 'query' => $this->_error );
		header( 'Location: ' . $this->_buildUri( $this->_redirectUri, $_parameters ) );
		exit();
	}

	/**
	 * Build the absolute URI based on supplied URI and parameters.
	 *
	 * @param string $uri        An absolute URI.
	 * @param array  $parameters Query parameters
	 *
	 * @return string The absolute URI with supplied parameters.
	 */
	protected function _buildUri( $uri, $parameters )
	{
		$_parsed = parse_url( $uri );

		// Add our params to the parsed uri
		foreach ( $parameters as $_key => $_value )
		{
			if ( isset( $_parsed[$_key] ) )
			{
				$_parsed[$_key] .= "&" . http_build_query( $_value );
			}
			else
			{
				$_parsed[$_key] = http_build_query( $_value );
			}
		}

		//	Build the url
		return
			( ( isset( $_parsed['scheme'] ) ) ? $_parsed['scheme'] . '://' : null ) .
			( ( isset( $_parsed['user'] ) ) ? $_parsed['user'] . ( ( isset( $_parsed['pass'] ) ) ? ':' . $_parsed['pass'] : null ) . '@' : null ) .
			( ( isset( $_parsed['host'] ) ) ? $_parsed['host'] : null ) .
			( ( isset( $_parsed['port'] ) ) ? ':' . $_parsed['port'] : null ) .
			( ( isset( $_parsed['path'] ) ) ? $_parsed['path'] : null ) .
			( ( isset( $_parsed['query'] ) ) ? '?' . $_parsed['query'] : null ) .
			( ( isset( $_parsed['fragment'] ) ) ? '#' . $_parsed['fragment'] : null );
	}
}

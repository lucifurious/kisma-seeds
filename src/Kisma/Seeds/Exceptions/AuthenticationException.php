<?php
/**
 * AuthenticationException.php
 */
namespace Kisma\Seeds\Exceptions;
/**
 * AuthenticationException
 * A default authentication exception
 */
class AuthenticationException extends ServerException
{
	//*************************************************************************
	//* Private Members
	//*************************************************************************

	/**
	 * @var string
	 */
	protected $_header;

	//*************************************************************************
	//* Public Methods
	//*************************************************************************

	/**
	 * @param string $httpStatus
	 * @param int    $tokenType
	 * @param string $realm
	 * @param string $error
	 * @param string $error_description
	 * @param string $scope
	 */
	public function __construct( $httpStatus, $tokenType, $realm, $error, $error_description = null, $scope = null )
	{
		parent::__construct( $httpStatus, $error, $error_description );

		if ( null !== $scope )
		{
			$this->_error['scope'] = $scope;
		}

		//	Build header
		$this->_header = sprintf( 'WWW-Authenticate: %s realm="%s"', ucwords( $tokenType ), $realm );

		foreach ( $this->_error as $_key => $_value )
		{
			$this->_header .= ', ' . $_key . '="' . $_value . '"';
		}
	}

	//*************************************************************************
	//* Private Methods
	//*************************************************************************

	/**
	 * Send out HTTP headers for JSON.
	 */
	protected function _sendHeaders()
	{
		header( $this->_header );
	}
}
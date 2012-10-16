<?php
/**
 * ServerException.php
 */
namespace Kisma\Seeds\Exceptions\OAuth;
/**
 * ServerException
 * A default server exception
 */
class ServerException extends \Exception
{
	//*************************************************************************
	//* Private Members
	//*************************************************************************

	/**
	 * @var int
	 */
	protected $_httpStatus;
	/**
	 * @var array
	 */
	protected $_error = array();

	//*************************************************************************
	//* Public Methods
	//*************************************************************************

	/**
	 * @param int    $httpStatus
	 * @param string $error
	 * @param string $errorDescription
	 */
	public function __construct( $httpStatus, $error, $errorDescription = null )
	{
		parent::__construct( $error, $httpStatus );

		$this->_httpStatus = $httpStatus;
		$this->_error['error'] = $error;
		$this->_error['error_description'] = $errorDescription;
	}

	/**
	 * @return string
	 */
	public function getDescription()
	{
		return $this->_error['error_description'];
	}

	/**
	 * @return string
	 */
	public function getHttpStatus()
	{
		return $this->_httpStatus;
	}

	/**
	 * Send out error message in JSON.
	 */
	public function sendHttpResponse()
	{
		$this->_sendHeaders( 'HTTP/1.1 ' . $this->_httpStatus );

		echo (string)$this;
		exit();
	}

	/**
	 * @see Exception::__toString()
	 */
	public function __toString()
	{
		return json_encode( $this->_error );
	}

	/**
	 * @param array $error
	 *
	 * @return ServerException
	 */
	public function setError( $error )
	{
		$this->_error = $error;

		return $this;
	}

	/**
	 * @return array
	 */
	public function getError()
	{
		return $this->_error;
	}

	//*************************************************************************
	//* Private Methods
	//*************************************************************************

	/**
	 * Send out HTTP headers for JSON.
	 */
	protected function _sendHeaders( $status = null )
	{
		if ( null !== $status )
		{
			header( $status );
		}

		header( 'Content-Type: application/json' );
		header( 'Cache-Control: no-store' );
	}

}
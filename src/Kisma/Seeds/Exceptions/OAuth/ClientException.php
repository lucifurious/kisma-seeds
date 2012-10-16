<?php
/**
 * ClientException.php
 */
namespace Kisma\Seeds\Exceptions\OAuth;
/**
 * ClientException
 * OAuth2.0 draft v10 exception handling.
 *
 * @author Originally written by Naitik Shah <naitik@facebook.com>.
 * @author Update to draft v10 by Edison Wong <hswong3i@pantarei-design.com>.
 * @author Adapted to Kisma by Jerry Ablan <jerryablan@gmail.com>
 */
class ClientException extends \Exception
{
	//*************************************************************************
	//* Private Members
	//*************************************************************************

	/**
	 * @var array The result from the API server that represents the exception information.
	 */
	protected $_result;

	//*************************************************************************
	//* Public Methods
	//*************************************************************************

	/**
	 * @param array $result
	 */
	public function __construct( $result )
	{
		$this->_result = $result;

		$_code = \Kisma\Core\Utility\Option::get( $result, 'code', 0 );

		//	OAuth 2.0 Draft 10 style
		if ( null === ( $_message = \Kisma\Core\Utility\Option::get( $result, 'error' ) ) )
		{
			if ( null === ( $_message = \Kisma\Core\Utility\Option::get( $result, 'message' ) ) )
			{
				$_message = 'Unknown Error. Check getResult()';
			}
		}

		parent::__construct( $_message, $_code );
	}

	/**
	 * @return array The result from the API server.
	 */
	public function getResult()
	{
		return $this->_result;
	}

	/**
	 * Returns the associated type for the error. This will default to 'Exception' when a type is not available.
	 *
	 * @return The type for the error.
	 */
	public function getType()
	{
		if ( null !== ( $_message = \Kisma\Core\Utility\Option::get( $this->_result, 'error' ) ) )
		{
			if ( is_string( $_message ) )
			{
				// OAuth 2.0 Draft 10 style
				return $_message;
			}
		}

		return 'Exception';
	}

	/**
	 * @return string The string representation of the error.
	 */
	public function __toString()
	{
		$_result = $this->getType() . ': ';

		if ( 0 != ( $_code = $this->getCode() ) )
		{
			$_result .= $_code . ': ';
		}

		return $_result . $this->getMessage();
	}
}

<?php
/**
 * FrameworkNames.php
 */
namespace Kisma\Seeds\Interfaces;
/**
 * FrameworkNames
 * Types of frameworks the generator knows about
 */
interface FrameworkNames
{
	//*************************************************************************
	//* Constants
	//*************************************************************************

	/**
	 * @var int
	 */
	const Yii = 'Yii';
	/**
	 * @var int
	 */
	const Zend = 'Zend';
	/**
	 * @var int
	 */
	const Symfony = 'Symfony';
	/**
	 * @var int
	 */
	const Silex = 'Silex';
	/**
	 * @var int
	 */
	const CakePhp = 'CakePhp';
	/**
	 * @var int
	 */
	const CodeIgniter = 'CodeIgniter';
}

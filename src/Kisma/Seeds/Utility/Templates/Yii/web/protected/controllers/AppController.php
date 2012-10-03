<?php
/**
 * AppController.php
 *
 * @author    %%author_name%% <%%author_email%%>
 * @filesource
 */
/**
 * AppController
 * This is the default/main controller
 */
class AppController extends CPSCRUDController
{
	//********************************************************************************
	//* Public Methods
	//********************************************************************************

	/**
	 * Initialize the controller
	 */
	public function init()
	{
		parent::init();

		PS::$errorCss = 'ui-state-error';

		//	We want merged update/create...
		$this->setSingleViewMode( true );

		//	Anyone can see these actions
		$this->addUserActions(
			self::ACCESS_TO_ANY,
			array(
				'error',
				'index',
				'exampleOne',
			)
		);

		//	Must be logged in
		$this->addUserActions(
			self::ACCESS_TO_AUTH,
			array(
				//	Add authorized-only actions here
				'exampleTwo',
			)
		);
	}

	/**
	 * Renders the open example one
	 */
	public function actionExampleOne()
	{
		$this->render( 'exampleOne' );
	}

	/**
	 * Renders the protected example two
	 */
	public function actionExampleTwo()
	{
		$this->render( 'exampleTwo' );
	}

}

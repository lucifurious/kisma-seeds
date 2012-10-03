<?php
/**
 * @var $model BaseModel
 * @var $this  CPSCRUDController
 */
if ( !PS::_ig() )
{
	$this->redirect( '/' );
}

$_formOptions = $this->setStandardFormOptions(
	$model,
	array(
		'title'       => PS::_gan() . ' :: Login',
		'header'      => 'Welcome to ' . PS::_gan(),
		'subHeader'   => '<p>Please enter your credentials and press <b>Login</b>.</p>',
		'breadcrumbs' => array( 'Login' ),
	)
);

$_fieldList = array();

$_fieldList[] = array(
	'html',
	'<fieldset><legend>Login</legend>'
);
$_fieldList[] = array(
	PS::DD_DATA_LOOKUP,
	'pod_id',
	array(
		'class'     => 'required',
		'data'      => 60,
		'dataName'  => 'id',
		'dataModel' => 'PodConfig'
	)
);
$_fieldList[] = array(
	PS::TEXT,
	'username',
	array(
		'size'      => 30,
		'maxlength' => 200,
		'class'     => 'required email'
	)
);
$_fieldList[] = array(
	PS::PASSWORD,
	'password',
	array(
		'size'  => 15,
		'class' => 'required'
	)
);
$_fieldList[] = array(
	'html',
	'</fieldset>'
);

$_fieldList[] = array(
	'html',
	PS::submitButton( 'Login' )
);

$_formOptions['fields'] = $_fieldList;

CPSForm::create( $_formOptions );

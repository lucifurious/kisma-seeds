<?php
/**
 * @var string        $content
 * @var AppController $this
 */
$_route = $this->route;

/**
 * Build this however you'd like. Format is:
 *<code>
 *     $_menuItems = array(
 *         'link name #1' => array(
 *             'href' => [href of link],
 *             'active' => true|false
 *            ),
 *
 *         'link name #2' => array(
 *             'href' => [href of link],
 *             'active' => true|false
 *             ),
 *
 *             //    etc. etc.
 * );
 *</code>
 */
$_menuItems = array(
	'Home'       => array(
		'href'   => '/',
		'active' => ( 'app/index' == $_route ),
	),
	'Example #1' => array(
		'href'   => '/app/exampleOne',
		'active' => ( 'app/exampleOne' == $_route ),
	),
	'Example #2' => array(
		'href'   => '/app/exampleTwo',
		'active' => ( 'app/exampleTwo' == $_route ),
	),
);

if ( PS::_ig() )
{
	$_menuItems['Login'] = array(
		'href'   => '/app/login',
		'active' => ( 'app/login' == $_route ),
	);
}
else
{
	$_menuItems['Logout'] = array(
		'href'   => '/app/logout',
		'active' => ( 'app/logout' == $_route ),
	);
}

$_liTags = null;

foreach ( $_menuItems as $_linkName => $_menuItem )
{
	$_liTags .= PS::tag(
		'li',
		array(
			'class' => $_menuItem['active'] ? 'active' : 'inactive',
		),
		PS::tag(
			'a',
			array(
				'href' => $_menuItem['href'],
			),
			$_linkName
		)
	);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <script type="text/javascript" src="http://ajax.googleapis.com/ajax/libs/jquery/1/jquery.min.js"></script>
    <script type="text/javascript" src="http://ajax.googleapis.com/ajax/libs/jqueryui/1/jquery-ui.min.js"></script>
    <title><?php echo PS::_gan(); ?></title>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
    <meta name="description" content="<?echo PS::_gan(); ?>">
    <meta name="author" content="Your Name">
    <meta name="language" content="en" />
    <meta charset="utf-8">
    <link rel="shortcut icon" href="http://www.yourdomain.com/favicon.ico" />
    <link rel="stylesheet" type="text/css" href="/public/vendor/bootstrap/css/bootstrap.min.css" />
    <link rel="stylesheet" type="text/css" href="/public/css/main.css" />
    <!--[if lt IE 9]>
	<script src="http://html5shim.googlecode.com/svn/trunk/html5.js" type="text/javascript"></script>    <![endif]-->
    <script src="/public/vendor/bootstrap/js/bootstrap.min.js" type="text/javascript"></script>
</head>
<body>
<div class="navbar navbar-fixed-top">
    <div class="navbar-inner">
        <div class="container">
            <a class="btn btn-navbar" data-toggle="collapse" data-target=".nav-collapse"> <span class="icon-bar"></span>
                <span class="icon-bar"></span> <span class="icon-bar"></span> </a> <a class="brand"
                                                                                      href="#"
                                                                                      style="padding-top:5px;padding-bottom:5px;">
            <img src="/public/images/logo-application.png">
			<?php echo PS::_gan(); ?></a>

            <div class="nav-collapse">
                <ul class="nav">
					<?php echo $_liTags; ?>
                </ul>
            </div>
        </div>
    </div>
</div>
<div class="container">
	<?php
	echo $content;
	?>
    <footer>
        <p>&copy; Your Name. <?php echo date( 'Y' ); ?>. All Rights Reserved.</p>
    </footer>
</div>
<!-- /container -->
<?php
include_once __DIR__ . '/_notification.php';
?>
</body>
</html>

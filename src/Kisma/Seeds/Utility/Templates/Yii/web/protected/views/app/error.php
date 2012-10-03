<?php
/**
 * @var $this AppController
 */
$this->pageTitle = PS::_gan() . ' - Error (' . $error['code'] . ')';
$this->setBreadcrumbs( array( ':(' ) );
?>
<h1>Error <?php echo $error['code']; ?></h1>
<h2><?php echo nl2br( CHtml::encode( $error['message'] ) ); ?></h2>
<p>
	The above error occurred when the Web server was processing your request. </p>
<p>
	If you think this is a server error, please contact support@yourplace.com. </p>
<p>
	Thank you. </p>
<div class="version"></div>
<script type="text/javascript">
/*<![CDATA[*/
if ( typeof(console) == 'object' ) {
	console.group("Application Log");
<?php
foreach ( $error as $index => $log )
{
	$time = date( 'H:i:s.', $log[3] ) . sprintf( '%03d', (int)( ( $log[3] - (int)$log[3] ) * 1000 ) );
	if ( $log[1] === CLogger::LEVEL_WARNING )
	{
		$func = 'warn';
	}
	else
	{
		if ( $log[1] === CLogger::LEVEL_ERROR )
		{
			$func = 'error';
		}
		else
		{
			$func = 'log';
		}
	}
	$content = CJavaScript::quote( "[$time][$log[1]][$log[2]] $log[0]" );
	echo "\tconsole.{$func}(\"{$content}\");\n";
}
?>
	console.groupEnd();
}
/*]]>*/
</script>

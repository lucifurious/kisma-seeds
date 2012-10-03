<div id="app-notify-container" style="display: none;">
	<div id="default">
		<h1>#{title}</h1>

		<p>#{text}</p>
	</div>
	<div id="sticky">
		<a class="ui-notify-close ui-notify-cross" href="#">x</a>

		<h1>#{title}</h1>

		<p>#{text}</p>
	</div>
	<div id="themed" class="ui-state-error"
		style="padding:10px; -moz-box-shadow:0 0 6px #980000; -webkit-box-shadow:0 0 6px #980000; box-shadow:0 0 6px #980000;">
		<a class="ui-notify-close" href="#"><span class="ui-icon ui-icon-close"
			style="float:right"></span></a>
		<span style="float:left; margin:2px 5px 0 0;"
			class="ui-icon ui-icon-alert"></span>

		<h1>#{title}</h1>

		<p>#{text}</p>

		<p style="text-align:center"><a class="ui-notify-close" href="#">Close
			Me</a></p>
	</div>
	<div id="with-icon">
		<a class="ui-notify-close ui-notify-cross" href="#">x</a>

		<div style="float:left;margin:0 10px 0 0"><img src="#{icon}"
			alt="warning" /></div>
		<h1>#{title}</h1>

		<p>#{text}</p>
	</div>
	<div id="with-button">
		<h1>#{title}</h1>

		<p>#{text}</p>

		<p style="margin-top:10px;text-align:center">
			<input type="button" class="confirm" value="Close Dialog" />
		</p>
	</div>
</div>
<script type="text/javascript">
//	Our notifier instance
var $_notifier = null;

/**
 * Easy notification creator
 * @param template
 * @param data
 * @param options
 */
function notify( template, data, options ) {
	if ( $_notifier ) {
		$_notifier.notify('create', template, data, options);
	} else {
		alert('No notifier available.');
	}
}

$(function() {
	$_notifier = $('div#app-notify-container').notify();
});
</script>

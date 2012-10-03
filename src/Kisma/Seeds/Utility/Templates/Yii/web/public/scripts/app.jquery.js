/**
 * app.jquery.js
 * The file contains client-side functions that are global to the entire application.
 * @author %%author_name%% <%%author_email%%>
 * @filesource
 */

/**
 * Stops the spinner and sets cursor back
 * @param eId The element id
 */
var _stopWaiting = function( eId ) {
	$('html').css({cursor : 'default'});
	$(eId + ' span.loading-indicator').hide();
};

/**
 * Starts the spinner and sets the cursor to wait
 * @param eId The element id
 */
var _startWaiting = function( eId ) {
	$(eId + ' span.loading-indicator').show();
	$('html').css({cursor : 'wait'});
};

/**
 * Document Ready
 * Put any global initialization code here.
 */
$(function() {
});

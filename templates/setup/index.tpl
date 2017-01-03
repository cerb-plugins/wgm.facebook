<h2>{'wgm.facebook.common'|devblocks_translate}</h2>
<form action="javascript:;" method="post" id="frmSetupFacebook" onsubmit="return false;">
	<input type="hidden" name="c" value="config">
	<input type="hidden" name="a" value="handleSectionAction">
	<input type="hidden" name="section" value="facebook">
	<input type="hidden" name="action" value="saveJson">
	<input type="hidden" name="_csrf_token" value="{$session.csrf_token}">
	
	<fieldset>
		<legend>Facebook Application</legend>
		
		<b>Consumer key:</b><br>
		<input type="text" name="client_id" value="{$credentials.client_id}" size="64"><br>
		<br>
		
		<b>Consumer secret:</b><br>
		<input type="password" name="client_secret" value="{$credentials.client_secret}" size="64"><br>
		<br>
		
		<div class="status"></div>
	
		<button type="button" class="submit"><span class="glyphicons glyphicons-circle-ok" style="color:rgb(0,180,0);"></span> {'common.save_changes'|devblocks_translate|capitalize}</button>	
	</fieldset>
</form>

<script type="text/javascript">
$(function() {
	$('#frmSetupFacebook BUTTON.submit')
		.click(function(e) {
			genericAjaxPost('frmSetupFacebook','',null,function(json) {
				$o = $.parseJSON(json);
				if(false == $o || false == $o.status) {
					Devblocks.showError('#frmSetupFacebook div.status',$o.error);
				} else {
					Devblocks.showSuccess('#frmSetupFacebook div.status',$o.message);
				}
			});
		})
	;
});
</script>
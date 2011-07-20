<h2>{'wgm.facebook.common'|devblocks_translate}</h2>
{if !$extensions.oauth}
<b>The oauth extension is not installed.</b>
{else}
<form action="javascript:;" method="post" id="frmSetupFacebook" onsubmit="return false;">
<input type="hidden" name="c" value="config">
<input type="hidden" name="a" value="handleSectionAction">
<input type="hidden" name="section" value="facebook">
<input type="hidden" name="action" value="saveJson">

<fieldset>
	<legend>Facebook Application</legend>
	
	<b>Consumer key:</b><br>
	<input type="text" name="client_id" value="{$params.client_id}" size="64"><br>
	<br>
	<b>Consumer secret:</b><br>
	<input type="text" name="client_secret" value="{$params.client_secret}" size="64"><br>
	<br>
	<div class="status"></div>

	<button type="button" class="submit"><span class="cerb-sprite2 sprite-tick-circle-frame"></span> {'common.save_changes'|devblocks_translate|capitalize}</button>	
</fieldset>

</form>

<form action="{devblocks_url}ajax.php{/devblocks_url}" method="post" id="frmAuthFacebook" style="display: {if $params.client_id && $params.client_secret}block{else}none{/if}">
<input type="hidden" name="c" value="config">
<input type="hidden" name="a" value="handleSectionAction">
<input type="hidden" name="section" value="facebook">
<input type="hidden" name="action" value="auth">
<fieldset>
	<legend>Facebook Auth</legend>
	<input type="submit" class="submit" value="Sign in with Facebook">
</fieldset>
</form>
{if !empty($params.users)}
<fieldset>
	<legend>Authorized Users</legend>
	<ul>
	{foreach $params.users as $user}
	<li>{$user.name}</li>
	{/foreach}
	</ul>
</fieldset>
{/if}
<script type="text/javascript">
$('#frmSetupFacebook BUTTON.submit')
	.click(function(e) {
		genericAjaxPost('frmSetupFacebook','',null,function(json) {
			$o = $.parseJSON(json);
			if(false == $o || false == $o.status) {
				Devblocks.showError('#frmSetupFacebook div.status',$o.error);
				$('#frmAuthFacebook').fadeOut();
			} else {
				Devblocks.showSuccess('#frmSetupFacebook div.status',$o.message);
				$('#frmAuthFacebook').fadeIn();
			}
		});
	})
;
</script>
{/if}
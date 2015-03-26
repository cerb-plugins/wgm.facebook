{if !empty($users)}
<b>User:</b>
<div style="margin-left:10px;margin-bottom:10px;">
	<select name="{$namePrefix}[user]">
	{foreach from=$users item=user}
	<option value="{$user.id}" {if $params.user == $user.id}selected="selected"{/if}>{$user.name}</option>
	{/foreach}
	</select>
</div>
{else}
<div class="ui-widget">
	<div class="ui-state-error ui-corner-all" style="padding:0 0.5em;margin:0.5em;"> 
		<p>
			<span class="ui-icon ui-icon-alert" style="float:left;margin-right:0.3em"></span> 
			<strong>Warning:</strong> No Facebook accounts are configured.  Posts will not be sent.
		</p>
	</div>
</div>
{/if}

<b>Update status:</b>
<div style="margin-left:10px;margin-bottom:10px;">
	<textarea name="{$namePrefix}[content]" rows="3" cols="45" style="width:100%;" class="placeholders">{$params.content}</textarea>
</div>

<script type="text/javascript">
$action = $('fieldset#{$namePrefix}');
$action.find('textarea').autosize();
</script>
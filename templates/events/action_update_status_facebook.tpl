<b>User:</b>
<div style="margin-left:10px;margin-bottom:10px;">
	<select name="{$namePrefix}[user]">
	{foreach $users as $user}
	<option value="{$user.id}"{if $params.user == $user.id} selected{/if}>{$user.name}</option>
	{/foreach}
	</select>
</div>

<b>Update status:</b>
<div style="margin-left:10px;margin-bottom:10px;">
	<textarea name="{$namePrefix}[content]" rows="10" cols="45" style="width:100%;" class="placeholders">{$params.content}</textarea>
</div>
